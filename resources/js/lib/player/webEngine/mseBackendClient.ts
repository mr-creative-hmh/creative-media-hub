/**
 * MSE Backend Client (REA-Engineered MediaSource Pipeline)
 *
 * Connects the browser's MediaSource engine to the backend zero-transcode fMP4 stream.
 * Feeds arriving fragmented MP4 (ftyp + moov + moof + mdat) chunks directly into
 * the GPU hardware decoder without client CPU overhead or complex MKV parsing.
 */

import { getBrowserCodecCapabilities } from '../codecCapabilities';

export interface MseBackendClientOptions {
    videoElement: HTMLVideoElement;
    streamUrl: string;
    onBuffering?: (isBuffering: boolean) => void;
    onOffsetResolved?: (actualStartSeconds: number) => void;
    onError?: (err: any) => void;
}

export class MseBackendClient {
    private videoElement: HTMLVideoElement;
    private baseStreamUrl: string;
    private mediaSource: MediaSource | null = null;
    private sourceBuffer: SourceBuffer | null = null;
    private objectUrl: string | null = null;
    private abortController: AbortController | null = null;
    private isDestroyed = false;
    private isAttached = false;
    private isAppending = false;
    private needsStartAlignment = true;
    private pendingBufferQueue: Uint8Array[] = [];
    private streamGeneration = 0;
    private seekLockPromise: Promise<void> = Promise.resolve();
    private onBuffering?: (isBuffering: boolean) => void;
    private onOffsetResolved?: (actualStartSeconds: number) => void;
    private onError?: (err: any) => void;

    constructor(options: MseBackendClientOptions) {
        this.videoElement = options.videoElement;
        this.baseStreamUrl = options.streamUrl;
        this.onBuffering = options.onBuffering;
        this.onOffsetResolved = options.onOffsetResolved;
        this.onError = options.onError;
    }

    public static isSupported(): boolean {
        return typeof window !== 'undefined' && 'MediaSource' in window;
    }

    /**
     * Start the MediaSource pipeline and begin streaming chunks.
     * Launches the initial fetch in parallel with MediaSource sourceopen to save latency.
     */
    public async start(startSeconds = 0): Promise<boolean> {
        if (!MseBackendClient.isSupported()) {
            return false;
        }

        this.isDestroyed = false;
        this.needsStartAlignment = true;
        const generation = ++this.streamGeneration;

        try {
            this.mediaSource = new MediaSource();
            this.objectUrl = URL.createObjectURL(this.mediaSource);

            const sourceOpenPromise = new Promise<void>((resolve, reject) => {
                if (!this.mediaSource) return reject();
                const onOpen = () => {
                    this.mediaSource?.removeEventListener('sourceopen', onOpen);
                    resolve();
                };
                this.mediaSource.addEventListener('sourceopen', onOpen);
            });

            this.videoElement.src = this.objectUrl;
            try {
                this.videoElement.currentTime = 0;
            } catch { }

            // Fire network request immediately without waiting for sourceopen
            const fetchPromise = this.initiateFetch(startSeconds, generation);

            await sourceOpenPromise;
            if (this.isDestroyed || generation !== this.streamGeneration) {
                return false;
            }

            this.isAttached = true;
            return await this.attachStreamResponse(fetchPromise, generation);
        } catch (err) {
            console.warn('[MseBackendClient] Start failed:', err);
            if (this.onError) this.onError(err);
            this.destroy();
            return false;
        }
    }

    /**
     * Initiate fetch request with browser codec capability hints.
     */
    private initiateFetch(startSeconds: number, generation: number): Promise<Response> {
        if (this.abortController) {
            this.abortController.abort();
        }
        this.abortController = new AbortController();

        const urlObj = new URL(this.baseStreamUrl, window.location.origin);
        if (startSeconds > 0) {
            urlObj.searchParams.set('start', String(Math.round(startSeconds * 1000) / 1000));
        }

        const caps = getBrowserCodecCapabilities();
        if (caps.mseHevc) urlObj.searchParams.set('cap_hevc', '1');
        if (caps.mseAv1) urlObj.searchParams.set('cap_av1', '1');
        if (caps.mseVp9) urlObj.searchParams.set('cap_vp9', '1');

        if (this.onBuffering && generation === this.streamGeneration) {
            this.onBuffering(true);
        }

        return fetch(urlObj.toString(), {
            signal: this.abortController.signal,
            headers: {
                Accept: 'video/mp4',
            },
        });
    }

    /**
     * Bind resolved fetch stream to SourceBuffer and pump fMP4 chunks.
     */
    private async attachStreamResponse(fetchPromise: Promise<Response>, generation: number): Promise<boolean> {
        if (!this.mediaSource || this.isDestroyed || generation !== this.streamGeneration) {
            return false;
        }

        try {
            const res = await fetchPromise;

            if (this.isDestroyed || generation !== this.streamGeneration) {
                try {
                    await res.body?.cancel();
                } catch { }
                return true;
            }

            if (!res.ok || !res.body) {
                throw new Error(`MSE stream HTTP error ${res.status}`);
            }

            const offsetHeader = res.headers.get('X-Stream-Start-Offset');
            if (offsetHeader !== null) {
                const actualStart = parseFloat(offsetHeader);
                if (!Number.isNaN(actualStart) && this.onOffsetResolved) {
                    this.onOffsetResolved(actualStart);
                }
            }

            // Retrieve codec from response header or use standard H.264/AAC string
            const headerCodec = res.headers.get('X-MSE-Codec');
            let mimeType = headerCodec || 'video/mp4; codecs="avc1.640028, mp4a.40.2"';

            if (!MediaSource.isTypeSupported(mimeType)) {
                mimeType = 'video/mp4; codecs="avc1.42e01e, mp4a.40.2"';
            }
            if (!MediaSource.isTypeSupported(mimeType)) {
                mimeType = 'video/mp4';
            }

            if (!this.sourceBuffer && this.mediaSource && this.mediaSource.readyState === 'open') {
                this.sourceBuffer = this.mediaSource.addSourceBuffer(mimeType);
                this.sourceBuffer.mode = 'segments';

                this.sourceBuffer.addEventListener('updateend', () => {
                    this.isAppending = false;
                    if (this.isDestroyed) return;

                    this.evictOldBuffers();
                    this.processPendingQueue();

                    if (this.needsStartAlignment && this.sourceBuffer && this.sourceBuffer.buffered.length > 0) {
                        const bufStart = this.sourceBuffer.buffered.start(0);
                        const bufEnd = this.sourceBuffer.buffered.end(0);
                        if (bufEnd > bufStart) {
                            this.needsStartAlignment = false;
                            const cur = this.videoElement.currentTime;
                            if ((cur < bufStart || cur > bufEnd) && Math.abs(cur - bufStart) > 0.25) {
                                try {
                                    this.videoElement.currentTime = bufStart + 0.01;
                                } catch { }
                            }
                            if (this.onBuffering) {
                                this.onBuffering(false);
                            }
                            if (this.videoElement.paused) {
                                this.videoElement.play().catch(() => { });
                            }
                        }
                    }
                });

                this.sourceBuffer.addEventListener('error', (e) => {
                    console.warn('[MseBackendClient] SourceBuffer error:', e);
                    if (this.onError) this.onError(e);
                });
            }

            const reader = res.body.getReader();
            this.pumpStreamReader(reader, generation);
            return true;
        } catch (e: any) {
            if (e.name === 'AbortError' || generation !== this.streamGeneration) {
                return true;
            }
            console.warn('[MseBackendClient] Stream fetch error:', e);
            if (this.onError) this.onError(e);
            return false;
        }
    }

    /**
     * Read stream chunks sequentially and queue for SourceBuffer append.
     */
    private async pumpStreamReader(reader: ReadableStreamDefaultReader<Uint8Array>, generation: number) {
        try {
            while (!this.isDestroyed && this.isAttached && generation === this.streamGeneration) {
                const { done, value } = await reader.read();
                if (generation !== this.streamGeneration || this.isDestroyed) {
                    try {
                        await reader.cancel();
                    } catch { }
                    break;
                }
                if (done) {
                    if (this.onBuffering) this.onBuffering(false);
                    break;
                }

                if (value && value.length > 0) {
                    this.appendBuffer(value, generation);
                    if (this.onBuffering && this.videoElement.readyState >= 3) {
                        this.onBuffering(false);
                    }
                }
            }
        } catch (err: any) {
            if (err.name !== 'AbortError' && !this.isDestroyed && generation === this.streamGeneration) {
                console.warn('[MseBackendClient] Stream reader error:', err);
                if (this.onError) this.onError(err);
            }
        }
    }

    /**
     * Evict buffered media > 30s behind currentTime to prevent QuotaExceededError.
     */
    private evictOldBuffers() {
        if (!this.sourceBuffer || this.sourceBuffer.updating || this.isDestroyed) return;
        try {
            if (this.sourceBuffer.buffered.length > 0) {
                const bufStart = this.sourceBuffer.buffered.start(0);
                const current = this.videoElement.currentTime;
                if (current - bufStart > 30) {
                    this.isAppending = true;
                    this.sourceBuffer.remove(bufStart, current - 20);
                }
            }
        } catch {
            this.isAppending = false;
        }
    }

    /**
     * Append chunk safely respecting SourceBuffer.updating state and stream generation.
     */
    private appendBuffer(chunk: Uint8Array, generation: number) {
        if (!this.sourceBuffer || this.isDestroyed || generation !== this.streamGeneration) return;

        if (this.isAppending || this.sourceBuffer.updating) {
            this.pendingBufferQueue.push(chunk);
        } else {
            try {
                this.isAppending = true;
                this.sourceBuffer.appendBuffer(chunk as unknown as BufferSource);
            } catch (e: any) {
                this.isAppending = false;
                if (e?.name === 'QuotaExceededError') {
                    this.pendingBufferQueue.unshift(chunk);
                    this.tryQuotaRecoveryEviction();
                } else {
                    this.pendingBufferQueue.push(chunk);
                }
            }
        }
    }

    private tryQuotaRecoveryEviction() {
        if (!this.sourceBuffer || this.sourceBuffer.updating || this.sourceBuffer.buffered.length === 0) return;
        const bufStart = this.sourceBuffer.buffered.start(0);
        const bufEnd = this.sourceBuffer.buffered.end(0);
        const current = this.videoElement.currentTime;
        const evictTo = Math.min(bufEnd - 1, Math.max(bufStart + 10, current - 5));
        if (evictTo > bufStart) {
            try {
                this.isAppending = true;
                this.sourceBuffer.remove(bufStart, evictTo);
            } catch {
                this.isAppending = false;
            }
        }
    }

    private processPendingQueue() {
        if (this.isDestroyed || this.pendingBufferQueue.length === 0 || !this.sourceBuffer || this.sourceBuffer.updating) {
            return;
        }
        const next = this.pendingBufferQueue.shift();
        if (next) {
            try {
                this.isAppending = true;
                this.sourceBuffer.appendBuffer(next as unknown as BufferSource);
            } catch (e: any) {
                this.isAppending = false;
                if (e?.name === 'QuotaExceededError') {
                    this.pendingBufferQueue.unshift(next);
                    this.tryQuotaRecoveryEviction();
                }
            }
        }
    }

    /**
     * Instant Seek: serialized via mutex + generation token, aborts active fetch reader,
     * flushes existing buffers, and streams new fMP4 segments starting from the specified timestamp.
     */
    public async seekTo(seconds: number): Promise<boolean> {
        if (this.isDestroyed || !this.mediaSource) return false;

        const myGeneration = ++this.streamGeneration;

        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
        this.pendingBufferQueue = [];

        const runSeek = async (): Promise<boolean> => {
            if (this.isDestroyed || !this.mediaSource || myGeneration !== this.streamGeneration) {
                return false;
            }

            if (this.onBuffering) this.onBuffering(true);

            // Safely clear old buffer segments
            if (this.sourceBuffer) {
                await new Promise<void>((resolve) => {
                    if (!this.sourceBuffer || !this.sourceBuffer.updating) return resolve();
                    const onEnd = () => {
                        this.sourceBuffer?.removeEventListener('updateend', onEnd);
                        resolve();
                    };
                    this.sourceBuffer.addEventListener('updateend', onEnd);
                });

                if (this.isDestroyed || myGeneration !== this.streamGeneration) {
                    return false;
                }

                try {
                    if (this.sourceBuffer && this.sourceBuffer.buffered.length > 0 && !this.sourceBuffer.updating) {
                        this.isAppending = true;
                        this.sourceBuffer.remove(0, Infinity);
                        await new Promise<void>((resolve) => {
                            if (!this.sourceBuffer) return resolve();
                            const onRemove = () => {
                                this.sourceBuffer?.removeEventListener('updateend', onRemove);
                                this.isAppending = false;
                                resolve();
                            };
                            this.sourceBuffer.addEventListener('updateend', onRemove);
                        });
                    }
                } catch { }
            }

            if (this.isDestroyed || myGeneration !== this.streamGeneration) {
                return false;
            }

            this.needsStartAlignment = true;
            try {
                if (this.sourceBuffer && !this.sourceBuffer.updating) {
                    this.sourceBuffer.timestampOffset = 0;
                }
                this.videoElement.currentTime = 0;
            } catch { }

            const fetchPromise = this.initiateFetch(seconds, myGeneration);
            return await this.attachStreamResponse(fetchPromise, myGeneration);
        };

        const chained = this.seekLockPromise.then(runSeek, runSeek);
        this.seekLockPromise = chained.then(() => { }, () => { });
        return chained;
    }

    /**
     * Tear down MediaSource, revoke URLs, and terminate active fetch connections.
     */
    public destroy() {
        this.isDestroyed = true;
        this.isAttached = false;
        this.isAppending = false;
        this.streamGeneration++;
        this.pendingBufferQueue = [];

        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }

        if (this.sourceBuffer && this.mediaSource && this.mediaSource.readyState === 'open') {
            try {
                this.mediaSource.removeSourceBuffer(this.sourceBuffer);
            } catch (e) { }
        }
        this.sourceBuffer = null;

        if (this.mediaSource && this.mediaSource.readyState === 'open') {
            try {
                this.mediaSource.endOfStream();
            } catch (e) { }
        }
        this.mediaSource = null;

        if (this.objectUrl) {
            URL.revokeObjectURL(this.objectUrl);
            this.objectUrl = null;
        }
    }
}

