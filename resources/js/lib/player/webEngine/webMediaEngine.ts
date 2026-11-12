/**
 * WebMediaEngine: In-Browser Client-Side Media Engine (FFmpeg Replacement)
 * Demuxes MKV/WebM containers and transmuxes H.264 video to fMP4 via MediaSource Extensions (MSE).
 * Completely eliminates server FFmpeg processes for compatible streams.
 */

import { MatroskaDemuxer, type TrackInfo } from './ebmlReader';
import { Fmp4Transmuxer, type VideoSample } from './fmp4Transmuxer';
import { Ac3AudioProcessor } from './ac3AudioProcessor';

export interface WebMediaEngineOptions {
    streamUrl: string;
    videoElement: HTMLVideoElement;
    onReady?: () => void;
    onError?: (err: any) => void;
    onBuffering?: (buffering: boolean) => void;
}

export class WebMediaEngine {
    private streamUrl: string;
    private videoElement: HTMLVideoElement;
    private mediaSource: MediaSource | null = null;
    private sourceBuffer: SourceBuffer | null = null;
    private objectUrl: string = '';

    private demuxer: MatroskaDemuxer = new MatroskaDemuxer();
    private transmuxer: Fmp4Transmuxer = new Fmp4Transmuxer(90000);
    private audioProcessor: Ac3AudioProcessor = new Ac3AudioProcessor();

    private isAttached: boolean = false;
    private isDestroyed: boolean = false;
    private pendingBufferQueue: Uint8Array[] = [];
    private isAppending: boolean = false;
    private currentByteOffset: number = 0;
    private abortController: AbortController | null = null;

    private videoTrack: TrackInfo | null = null;
    private audioTrack: TrackInfo | null = null;

    constructor(options: WebMediaEngineOptions) {
        this.streamUrl = options.streamUrl;
        this.videoElement = options.videoElement;
    }

    /**
     * Check if the browser supports MediaSource Extensions and Web Media Engine.
     */
    public static isSupported(): boolean {
        return typeof window !== 'undefined' && 'MediaSource' in window;
    }

    /**
     * Start the Web Media Engine pipeline.
     */
    public async start(): Promise<boolean> {
        if (!WebMediaEngine.isSupported()) {
            return false;
        }

        try {
            this.mediaSource = new MediaSource();
            this.objectUrl = URL.createObjectURL(this.mediaSource);
            this.videoElement.src = this.objectUrl;

            await new Promise<void>((resolve, reject) => {
                if (!this.mediaSource) return reject();
                const onOpen = () => {
                    this.mediaSource?.removeEventListener('sourceopen', onOpen);
                    resolve();
                };
                this.mediaSource.addEventListener('sourceopen', onOpen);
            });

            this.isAttached = true;

            // Fetch the first 256KB to parse EBML header and Track Info
            const initialBytes = await this.fetchRange(0, 262143);
            if (!initialBytes || initialBytes.length === 0) {
                return false;
            }

            const headerParsed = this.demuxer.parseHeaderAndTracks(initialBytes);
            if (!headerParsed) {
                return false;
            }

            // Find primary video track
            for (const [_, track] of this.demuxer.tracks) {
                if (track.trackType === 'video' && !this.videoTrack) {
                    this.videoTrack = track;
                }
                if (track.trackType === 'audio' && !this.audioTrack) {
                    this.audioTrack = track;
                }
            }

            if (!this.videoTrack) {
                return false;
            }

            // Create MSE SourceBuffer for H.264 AVC1
            const mimeType = 'video/mp4; codecs="avc1.640029"';
            if (!MediaSource.isTypeSupported(mimeType)) {
                return false;
            }

            this.sourceBuffer = this.mediaSource.addSourceBuffer(mimeType);
            this.sourceBuffer.mode = 'segments';

            this.sourceBuffer.addEventListener('updateend', () => {
                this.isAppending = false;
                this.processPendingQueue();
            });

            // Generate and append fMP4 Initialization Segment
            const initSegment = this.transmuxer.generateInitSegment({
                id: this.videoTrack.trackNumber,
                width: this.videoTrack.width || 1920,
                height: this.videoTrack.height || 1080,
                codecPrivate: this.videoTrack.codecPrivate,
            });

            this.appendBuffer(initSegment);

            // Set duration if known
            if (this.demuxer.duration > 0 && isFinite(this.demuxer.duration)) {
                try {
                    this.mediaSource.duration = this.demuxer.duration;
                } catch (e) {}
            }

            // Start streaming clusters
            this.currentByteOffset = 262144;
            this.startClusterStreamLoop();

            return true;
        } catch (err) {
            console.warn('WebMediaEngine start failed, falling back:', err);
            this.destroy();
            return false;
        }
    }

    /**
     * Continuous streaming loop fetching next clusters.
     */
    private async startClusterStreamLoop() {
        if (this.isDestroyed || !this.isAttached) return;

        const chunkSize = 1024 * 512; // 512KB chunks

        try {
            while (!this.isDestroyed && this.isAttached) {
                // If buffered enough ahead (e.g. 20s), throttle fetching
                if (this.videoElement && this.videoElement.buffered.length > 0) {
                    const curTime = this.videoElement.currentTime;
                    const bufEnd = this.videoElement.buffered.end(this.videoElement.buffered.length - 1);
                    if (bufEnd - curTime > 25) {
                        await new Promise(r => setTimeout(r, 1000));
                        continue;
                    }
                }

                const bytes = await this.fetchRange(this.currentByteOffset, this.currentByteOffset + chunkSize - 1);
                if (!bytes || bytes.length === 0) {
                    break;
                }

                this.currentByteOffset += bytes.length;

                // Demux samples from cluster
                const samples = this.demuxer.parseCluster(bytes);
                if (samples && samples.length > 0 && this.videoTrack) {
                    const videoSamples: VideoSample[] = [];

                    for (const s of samples) {
                        if (s.trackNumber === this.videoTrack.trackNumber) {
                            const pts90k = Math.round(s.timecode * 90);
                            videoSamples.push({
                                dts: pts90k,
                                pts: pts90k,
                                duration: 3000,
                                isKeyframe: s.isKeyframe,
                                data: s.data,
                            });
                        }
                    }

                    if (videoSamples.length > 0) {
                        const mediaSegment = this.transmuxer.generateMediaSegment(this.videoTrack.trackNumber, videoSamples);
                        if (mediaSegment) {
                            this.appendBuffer(mediaSegment);
                        }
                    }
                }

                // Small pause between chunks to yield to browser UI thread
                await new Promise(r => setTimeout(r, 50));
            }
        } catch (e) {
            console.warn('WebMediaEngine cluster stream loop stopped:', e);
        }
    }

    /**
     * Append buffer safely handling SourceBuffer busy state.
     */
    private appendBuffer(chunk: Uint8Array) {
        if (!this.sourceBuffer || this.isDestroyed) return;

        if (this.isAppending || this.sourceBuffer.updating) {
            this.pendingBufferQueue.push(chunk);
        } else {
            try {
                this.isAppending = true;
                this.sourceBuffer.appendBuffer(chunk as unknown as BufferSource);
            } catch (e) {
                this.isAppending = false;
                this.pendingBufferQueue.push(chunk);
            }
        }
    }

    private processPendingQueue() {
        if (this.pendingBufferQueue.length > 0 && this.sourceBuffer && !this.sourceBuffer.updating) {
            const next = this.pendingBufferQueue.shift();
            if (next) {
                try {
                    this.isAppending = true;
                    this.sourceBuffer.appendBuffer(next as unknown as BufferSource);
                } catch (e) {
                    this.isAppending = false;
                }
            }
        }
    }

    /**
     * Fetch byte range via HTTP 206 Partial Content.
     */
    private async fetchRange(start: number, end: number): Promise<Uint8Array | null> {
        this.abortController = new AbortController();
        try {
            const res = await fetch(this.streamUrl, {
                headers: {
                    'Range': `bytes=${start}-${end}`,
                },
                signal: this.abortController.signal,
            });

            if (!res.ok && res.status !== 206 && res.status !== 200) {
                return null;
            }

            const buf = await res.arrayBuffer();
            return new Uint8Array(buf);
        } catch (e) {
            return null;
        }
    }

    /**
     * Seek in-browser by jumping byte offset to matched CuePoint.
     */
    public async seekTo(seconds: number): Promise<boolean> {
        const ms = seconds * 1000;
        let targetOffset = 0;

        if (this.demuxer.cues.length > 0) {
            // Find closest preceding cue point
            let closest = this.demuxer.cues[0];
            for (const c of this.demuxer.cues) {
                if (c.time <= ms && c.time > closest.time) {
                    closest = c;
                }
            }
            targetOffset = closest.clusterOffset;
        }

        if (targetOffset > 0) {
            this.currentByteOffset = targetOffset;
            if (this.sourceBuffer && !this.sourceBuffer.updating) {
                try {
                    this.sourceBuffer.abort();
                } catch (e) {}
            }
            this.pendingBufferQueue = [];
            this.videoElement.currentTime = seconds;
            return true;
        }

        return false;
    }

    /**
     * Clean up and release MSE resources.
     */
    public destroy() {
        this.isDestroyed = true;
        this.isAttached = false;
        if (this.abortController) {
            this.abortController.abort();
        }
        if (this.sourceBuffer && this.mediaSource && this.mediaSource.readyState === 'open') {
            try {
                this.mediaSource.removeSourceBuffer(this.sourceBuffer);
            } catch (e) {}
        }
        if (this.objectUrl) {
            URL.revokeObjectURL(this.objectUrl);
            this.objectUrl = '';
        }
        this.mediaSource = null;
        this.sourceBuffer = null;
    }
}
