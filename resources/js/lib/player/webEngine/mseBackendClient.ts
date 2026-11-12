/**
 * MSE Backend Client (REA-Engineered MediaSource Pipeline)
 *
 * Connects the browser's MediaSource engine to the backend zero-transcode fMP4 stream.
 * Feeds arriving fragmented MP4 (ftyp + moov + moof + mdat) chunks directly into
 * the GPU hardware decoder without client CPU overhead or complex MKV parsing.
 */

export interface MseBackendClientOptions {
    videoElement: HTMLVideoElement;
    streamUrl: string;
    onBuffering?: (isBuffering: boolean) => void;
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
    private pendingBufferQueue: Uint8Array[] = [];
    private onBuffering?: (isBuffering: boolean) => void;
    private onError?: (err: any) => void;

    constructor(options: MseBackendClientOptions) {
        this.videoElement = options.videoElement;
        this.baseStreamUrl = options.streamUrl;
        this.onBuffering = options.onBuffering;
        this.onError = options.onError;
    }

    public static isSupported(): boolean {
        return typeof window !== 'undefined' && 'MediaSource' in window;
    }

    /**
     * Start the MediaSource pipeline and begin streaming chunks.
     */
    public async start(startSeconds = 0): Promise<boolean> {
        if (!MseBackendClient.isSupported()) {
            return false;
        }

        this.isDestroyed = false;

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
            return await this.startStreamLoop(startSeconds);
        } catch (err) {
            console.warn('[MseBackendClient] Start failed:', err);
            if (this.onError) this.onError(err);
            this.destroy();
            return false;
        }
    }

    /**
     * Initiate fetch stream and pump fMP4 chunks into SourceBuffer.
     */
    private async startStreamLoop(startSeconds: number): Promise<boolean> {
        if (!this.mediaSource || this.isDestroyed) return false;

        this.abortController = new AbortController();
        const urlObj = new URL(this.baseStreamUrl, window.location.origin);
        if (startSeconds > 0) {
            urlObj.searchParams.set('start', String(Math.round(startSeconds * 1000) / 1000));
        }

        try {
            if (this.onBuffering) this.onBuffering(true);

            const res = await fetch(urlObj.toString(), {
                signal: this.abortController.signal,
                headers: {
                    'Accept': 'video/mp4',
                },
            });

            if (!res.ok || !res.body) {
                throw new Error(`MSE stream HTTP error ${res.status}`);
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
                    this.processPendingQueue();
                });

                this.sourceBuffer.addEventListener('error', (e) => {
                    console.warn('[MseBackendClient] SourceBuffer error:', e);
                    if (this.onError) this.onError(e);
                });
            }

            const reader = res.body.getReader();
            this.pumpStreamReader(reader);
            return true;
        } catch (e: any) {
            if (e.name === 'AbortError') {
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
    private async pumpStreamReader(reader: ReadableStreamDefaultReader<Uint8Array>) {
        try {
            while (!this.isDestroyed && this.isAttached) {
                const { done, value } = await reader.read();
                if (done) {
                    if (this.onBuffering) this.onBuffering(false);
                    break;
                }

                if (value && value.length > 0) {
                    this.appendBuffer(value);
                    if (this.onBuffering && this.videoElement.readyState >= 3) {
                        this.onBuffering(false);
                    }
                }
            }
        } catch (err: any) {
            if (err.name !== 'AbortError' && !this.isDestroyed) {
                console.warn('[MseBackendClient] Stream reader error:', err);
                if (this.onError) this.onError(err);
            }
        }
    }

    /**
     * Append chunk safely respecting SourceBuffer.updating state.
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
     * Instant Seek: aborts active fetch reader, flushes existing buffers,
     * and streams new fMP4 segments starting from the specified timestamp.
     */
    public async seekTo(seconds: number): Promise<boolean> {
        if (this.isDestroyed || !this.mediaSource) return false;

        // Abort in-flight reader
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }

        this.pendingBufferQueue = [];

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
            } catch (e) {}
        }

        return await this.startStreamLoop(seconds);
    }

    /**
     * Tear down MediaSource, revoke URLs, and terminate active fetch connections.
     */
    public destroy() {
        this.isDestroyed = true;
        this.isAttached = false;
        this.isAppending = false;
        this.pendingBufferQueue = [];

        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }

        if (this.sourceBuffer && this.mediaSource && this.mediaSource.readyState === 'open') {
            try {
                this.mediaSource.removeSourceBuffer(this.sourceBuffer);
            } catch (e) {}
        }
        this.sourceBuffer = null;

        if (this.mediaSource && this.mediaSource.readyState === 'open') {
            try {
                this.mediaSource.endOfStream();
            } catch (e) {}
        }
        this.mediaSource = null;

        if (this.objectUrl) {
            URL.revokeObjectURL(this.objectUrl);
            this.objectUrl = null;
        }
    }
}

