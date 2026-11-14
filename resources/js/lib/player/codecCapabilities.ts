/**
 * Browser Codec & Container Capability Helper
 * Probes MediaSource Extensions (MSE) and native <video> element codec support once per session
 * to route compatible H.264, HEVC, AV1, and VP9 streams with zero unnecessary CPU transcoding.
 */

export interface BrowserCodecCapabilities {
    mseSupported: boolean;
    mseHevc: boolean;
    mseAv1: boolean;
    mseVp9: boolean;
    nativeHevcMp4: boolean;
    nativeAv1Mp4: boolean;
    nativeVp9Webm: boolean;
}

let cachedCapabilities: BrowserCodecCapabilities | null = null;

function testMseSupport(mimeCandidates: string[]): boolean {
    if (typeof window === 'undefined' || !('MediaSource' in window)) {
        return false;
    }
    return mimeCandidates.some((mime) => {
        try {
            return MediaSource.isTypeSupported(mime);
        } catch {
            return false;
        }
    });
}

export function getBrowserCodecCapabilities(): BrowserCodecCapabilities {
    if (cachedCapabilities) {
        return cachedCapabilities;
    }

    const mseSupported = typeof window !== 'undefined' && 'MediaSource' in window;
    const videoEl = typeof document !== 'undefined' ? document.createElement('video') : null;

    const canNative = (mimes: string[]): boolean => {
        if (!videoEl) {
            return false;
        }
        return mimes.some((m) => {
            try {
                const res = videoEl.canPlayType(m);
                return res === 'probably' || res === 'maybe';
            } catch {
                return false;
            }
        });
    };

    cachedCapabilities = {
        mseSupported,
        mseHevc: testMseSupport([
            'video/mp4; codecs="hvc1.1.6.L93.B0, mp4a.40.2"',
            'video/mp4; codecs="hev1.1.6.L93.B0, mp4a.40.2"',
            'video/mp4; codecs="hvc1.1.6.L93.B0"',
        ]),
        mseAv1: testMseSupport([
            'video/mp4; codecs="av01.0.04M.08, mp4a.40.2"',
            'video/mp4; codecs="av01.0.04M.08"',
        ]),
        mseVp9: testMseSupport([
            'video/mp4; codecs="vp09.00.10.08, mp4a.40.2"',
            'video/mp4; codecs="vp09.00.10.08"',
        ]),
        nativeHevcMp4: canNative([
            'video/mp4; codecs="hvc1.1.6.L93.B0, mp4a.40.2"',
            'video/mp4; codecs="hev1.1.6.L93.B0, mp4a.40.2"',
        ]),
        nativeAv1Mp4: canNative([
            'video/mp4; codecs="av01.0.04M.08, mp4a.40.2"',
        ]),
        nativeVp9Webm: canNative([
            'video/webm; codecs="vp9, opus"',
            'video/webm; codecs="vp09.00.10.08, opus"',
        ]),
    };

    return cachedCapabilities;
}

/**
 * Determine whether a media item can stream via zero-server HTTP 206 byte-range direct playback
 * in the current browser without container remuxing or audio transcoding.
 */
export function canDirectPlayMedia(mediaItem: any): boolean {
    if (!mediaItem) {
        return false;
    }

    const caps = getBrowserCodecCapabilities();
    const path = String(mediaItem.file_path || '').toLowerCase();
    const video = String(mediaItem.video_codec || mediaItem.codec || '').toLowerCase();
    const audio = String(mediaItem.audio_codec || '').toLowerCase();

    const isDirectContainer = path.endsWith('.mp4') || path.endsWith('.m4v') || path.endsWith('.webm');
    if (!isDirectContainer) {
        return false;
    }

    const isUnsupportedAudio =
        audio.includes('ac3') ||
        audio.includes('ac-3') ||
        audio.includes('eac3') ||
        audio.includes('ec-3') ||
        audio.includes('dolby') ||
        audio.includes('dts') ||
        audio.includes('truehd') ||
        audio.includes('mlp') ||
        audio.includes('pcm');

    if (isUnsupportedAudio) {
        return false;
    }

    if (video.includes('mpeg') || video.includes('xvid') || video.includes('divx') || video.includes('vc1') || video.includes('wmv')) {
        return false;
    }

    if ((video.includes('h.264') || video.includes('h264') || video.includes('avc')) && !video.includes('hevc')) {
        return true;
    }

    if (video.includes('hevc') || video.includes('h.265') || video.includes('h265')) {
        return caps.nativeHevcMp4;
    }

    if (video.includes('av1')) {
        return caps.nativeAv1Mp4;
    }

    if (video.includes('vp9')) {
        return caps.nativeVp9Webm;
    }

    return !video;
}

/**
 * Select the optimal playback engine mode for a media file:
 *  1. 'direct' -> Native MP4/M4V/WebM with browser-supported video and stereo audio (0% server CPU).
 *  2. 'web'    -> HEVC/AV1/VP9 videos supported only via MediaSource Extensions (zero-copy fMP4 MSE).
 *  3. 'remux'  -> Default server remux + LAV BS.775 stereo downmix for MKV/AVI/TS or surround audio.
 */
export function selectOptimalEngineMode(mediaItem: any): 'direct' | 'remux' | 'web' {
    if (canDirectPlayMedia(mediaItem)) {
        return 'direct';
    }

    if (!mediaItem) {
        return 'remux';
    }

    const caps = getBrowserCodecCapabilities();
    const video = String(mediaItem.video_codec || mediaItem.codec || '').toLowerCase();

    if (
        caps.mseSupported &&
        (
            ((video.includes('hevc') || video.includes('h.265') || video.includes('h265')) && caps.mseHevc && !caps.nativeHevcMp4) ||
            (video.includes('av1') && caps.mseAv1 && !caps.nativeAv1Mp4) ||
            (video.includes('vp9') && caps.mseVp9 && !caps.nativeVp9Webm)
        )
    ) {
        return 'web';
    }

    return 'remux';
}

