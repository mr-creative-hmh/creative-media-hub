import { ref, computed, nextTick, type Ref } from 'vue';
import { MseBackendClient } from '@/lib/player/webEngine/mseBackendClient';
import { canDirectPlayMedia } from '@/lib/player/codecCapabilities';

export interface UseCinemaPlaybackOptions {
    item: Ref<any>;
    initialProgress?: number;
    playlist?: Ref<any[]>;
    videoRef?: Ref<HTMLVideoElement | null>;
    playerContainerRef?: Ref<HTMLDivElement | null>;
    onTimeUpdateCallback?: (currentTime: number) => void;
    onItemChange?: (item: any) => void;
    onToast?: (message: string) => void;
    t?: (key: string, params?: any) => string;
}

export function useCinemaPlayback(options: UseCinemaPlaybackOptions) {
    const activeItem = ref<any>(options.item.value);
    const videoRef = options.videoRef || ref<HTMLVideoElement | null>(null);
    const playerContainerRef = options.playerContainerRef || ref<HTMLDivElement | null>(null);

    const isPlaying = ref(false);
    const isMuted = ref(false);
    const volume = ref(1.0);

    const pendingInitialSeek = ref<number>(
        Number(options.initialProgress) ||
        Number(options.item.value?.progress_seconds) ||
        Number(options.item.value?.initial_progress) ||
        0
    );
    const currentTime = ref(pendingInitialSeek.value > 0 ? pendingInitialSeek.value : 0);

    const hasExactDuration = Number(options.item.value?.duration_seconds) > 0;
    const initialDuration = hasExactDuration
        ? Number(options.item.value?.duration_seconds)
        : (Number(options.item.value?.runtime_minutes) ? Number(options.item.value.runtime_minutes) * 60 : 0);
    const duration = ref(initialDuration > 0 ? initialDuration : 0);
    const isDurationLocked = ref(hasExactDuration);

    const bufferedPercent = ref(0);
    const isFullscreen = ref(false);
    const isControlsVisible = ref(true);
    const isBuffering = ref(false);
    const liveResolution = ref<string>('');

    const isEpisode = computed(() => {
        return !!(
            activeItem.value?.series_id ||
            activeItem.value?.season_id ||
            activeItem.value?.episode_number !== undefined ||
            activeItem.value?.type === 'episode'
        );
    });

    const itemKey = computed(() => {
        if (!activeItem.value) return 'unknown';
        const type = isEpisode.value ? 'ep' : 'mv';
        const targetId = activeItem.value?.watchable_id || activeItem.value?.id;
        return `${type}_${targetId}`;
    });

    const checkNeedsRemux = (mediaItem: any): boolean => {
        if (!mediaItem) return false;
        try {
            const type = isEpisode.value ? 'episode' : 'movie';
            const targetId = mediaItem?.watchable_id || mediaItem?.id;
            const savedMode = localStorage.getItem(`stream_mode_${type}_${targetId}`);
            if (savedMode === 'direct') return false;
            if (savedMode === 'remux') return true;
        } catch { }

        return !canDirectPlayMedia(mediaItem);
    };

    const getInitialEngineMode = (): 'web' | 'remux' | 'direct' => {
        try {
            const type = isEpisode.value ? 'episode' : 'movie';
            const targetId = options.item.value?.watchable_id || options.item.value?.id;
            const savedEngine = localStorage.getItem(`stream_engine_${type}_${targetId}`);
            if (savedEngine === 'web' || savedEngine === 'remux' || savedEngine === 'direct') {
                return savedEngine;
            }
        } catch { }
        return checkNeedsRemux(options.item.value) ? 'web' : 'direct';
    };

    const isRemuxStream = ref(checkNeedsRemux(options.item.value));
    const engineMode = ref<'web' | 'remux' | 'direct'>(getInitialEngineMode());
    const playbackRate = ref<number>(1.0);
    const remuxStartOffset = ref(isRemuxStream.value && pendingInitialSeek.value > 0 ? pendingInitialSeek.value : 0);
    const isServerCached = ref(false);
    const cachedStreamUrl = ref('');
    const webEngineInstance = ref<MseBackendClient | null>(null);
    const isSeekPending = ref(false);

    const destroyWebEngine = () => {
        if (webEngineInstance.value) {
            webEngineInstance.value.destroy();
            webEngineInstance.value = null;
        }
    };

    const initWebEngine = async (): Promise<boolean> => {
        destroyWebEngine();
        if (!videoRef.value || !activeItem.value || engineMode.value !== 'web') return false;

        const targetId = activeItem.value?.watchable_id || activeItem.value?.id;
        if (!targetId) return false;

        const mseStreamUrl = isEpisode.value ? `/stream/mse/episode/${targetId}` : `/stream/mse/movie/${targetId}`;

        isBuffering.value = true;
        const engine = new MseBackendClient({
            videoElement: videoRef.value,
            streamUrl: mseStreamUrl,
            onBuffering: (buffering) => {
                isBuffering.value = buffering;
            },
            onOffsetResolved: (actualStartSeconds) => {
                remuxStartOffset.value = actualStartSeconds;
                const raw = videoRef.value?.currentTime || 0;
                currentTime.value = actualStartSeconds + raw;
                if (options.onTimeUpdateCallback) {
                    options.onTimeUpdateCallback(currentTime.value);
                }
            },
            onError: (err) => {
                console.warn('[CinemaPlayback] MSE backend client error, falling back:', err);
            },
        });

        const startAt = pendingInitialSeek.value > 0 ? pendingInitialSeek.value : currentTime.value;
        isRemuxStream.value = true;
        remuxStartOffset.value = startAt;
        currentTime.value = startAt;
        if (pendingInitialSeek.value > 0) {
            isSeekPending.value = true;
            pendingInitialSeek.value = 0;
        }

        const success = await engine.start(startAt);
        isSeekPending.value = false;
        isBuffering.value = false;

        if (success) {
            webEngineInstance.value = engine;
            if (videoRef.value && playbackRate.value !== 1.0) {
                videoRef.value.playbackRate = playbackRate.value;
            }
            return true;
        } else {
            console.warn('[CinemaPlayback] MseBackendClient start failed, smoothly falling back to server remux');
            engineMode.value = 'remux';
            isRemuxStream.value = true;
            if (options.onToast) {
                options.onToast('Falling back to Server Remux (AAC)');
            }
            if (videoRef.value) {
                videoRef.value.src = streamUrl.value;
                videoRef.value.load();
                videoRef.value.playbackRate = playbackRate.value;
                videoRef.value.play().catch(() => { });
            }
            return false;
        }
    };

    const setPlaybackRate = (rate: number) => {
        playbackRate.value = rate;
        if (videoRef.value) {
            videoRef.value.playbackRate = rate;
        }
        if (options.onToast) {
            options.onToast(`Playback Speed: ${rate}x`);
        }
    };

    // Stream URL without double-delay pollution
    const streamUrl = computed(() => {
        if (!activeItem.value) return '';

        const targetId = activeItem.value?.watchable_id || activeItem.value?.id;
        if (!targetId) return '';

        if (engineMode.value === 'web') {
            const params: string[] = [];
            if (remuxStartOffset.value > 0) {
                params.push(`start=${Math.round(remuxStartOffset.value * 1000) / 1000}`);
            }
            const queryString = params.length > 0 ? `?${params.join('&')}` : '';
            if (isEpisode.value) {
                return `/stream/mse/episode/${targetId}${queryString}`;
            }
            return `/stream/mse/movie/${targetId}${queryString}`;
        }

        if (isRemuxStream.value) {
            if (isServerCached.value && cachedStreamUrl.value) {
                return cachedStreamUrl.value;
            }
            const params: string[] = [];
            if (remuxStartOffset.value > 0) {
                params.push(`start=${Math.round(remuxStartOffset.value * 1000) / 1000}`);
            }
            const queryString = params.length > 0 ? `?${params.join('&')}` : '';
            if (isEpisode.value) {
                return `/stream/remux/episode/${targetId}${queryString}`;
            }
            return `/stream/remux/movie/${targetId}${queryString}`;
        }

        if (isEpisode.value) {
            return `/stream/episode/${targetId}`;
        }
        return `/stream/movie/${targetId}`;
    });

    const effectiveVideoSrc = computed<string | undefined>(() => {
        if (engineMode.value === 'web') {
            return undefined;
        }
        return streamUrl.value;
    });

    const progressPercent = computed(() => {
        if (!duration.value || duration.value <= 0) return 0;
        return Math.min(100, Math.max(0, (currentTime.value / duration.value) * 100));
    });

    const togglePlay = () => {
        if (!videoRef.value) return;
        if (videoRef.value.paused) {
            videoRef.value.play().catch(() => { });
        } else {
            videoRef.value.pause();
        }
    };

    const setVolume = (val: number) => {
        volume.value = Math.max(0, Math.min(1, val));
        if (videoRef.value) {
            videoRef.value.volume = volume.value;
        }
        if (volume.value > 0 && isMuted.value) {
            isMuted.value = false;
            if (videoRef.value) videoRef.value.muted = false;
        }
    };

    const toggleMute = () => {
        isMuted.value = !isMuted.value;
        if (videoRef.value) {
            videoRef.value.muted = isMuted.value;
        }
    };

    const toggleFullscreen = () => {
        const el = playerContainerRef.value;
        if (!el) return;
        if (!document.fullscreenElement) {
            el.requestFullscreen().catch(() => { });
            isFullscreen.value = true;
        } else {
            document.exitFullscreen().catch(() => { });
            isFullscreen.value = false;
        }
    };

    const toggleStreamEngine = async () => {
        destroyWebEngine();

        if (engineMode.value === 'web') {
            engineMode.value = 'remux';
            isRemuxStream.value = true;
        } else if (engineMode.value === 'remux') {
            engineMode.value = 'direct';
            isRemuxStream.value = false;
        } else {
            engineMode.value = 'web';
            isRemuxStream.value = true;
        }

        remuxStartOffset.value = currentTime.value;
        try {
            const type = isEpisode.value ? 'episode' : 'movie';
            const targetId = activeItem.value?.watchable_id || activeItem.value?.id;
            localStorage.setItem(`stream_engine_${type}_${targetId}`, engineMode.value);
            localStorage.setItem(`stream_mode_${type}_${targetId}`, isRemuxStream.value ? 'remux' : 'direct');
        } catch { }

        if (options.onToast) {
            const label = engineMode.value === 'web'
                ? 'Web Media Engine active (In-Browser MSE)'
                : (engineMode.value === 'remux' ? 'Server Remux active (AAC Sync)' : 'Direct Native Stream (Hardware)');
            options.onToast(label);
        }

        if (engineMode.value === 'web') {
            await initWebEngine();
        } else {
            nextTick(() => {
                if (videoRef.value) {
                    videoRef.value.src = streamUrl.value;
                    videoRef.value.load();
                    videoRef.value.playbackRate = playbackRate.value;
                    videoRef.value.play().catch(() => { });
                }
            });
        }
    };

    let currentSeekToken = 0;

    const executeSeek = async (targetSecs: number) => {
        const clamped = Math.max(0, Math.min(duration.value || 3600, targetSecs));
        currentTime.value = clamped;
        if (options.onTimeUpdateCallback) {
            options.onTimeUpdateCallback(clamped);
        }

        if (engineMode.value === 'web' && webEngineInstance.value) {
            isBuffering.value = true;
            isRemuxStream.value = true;
            isSeekPending.value = true;
            remuxStartOffset.value = clamped;
            try {
                await webEngineInstance.value.seekTo(clamped);
                if (videoRef.value) {
                    if (playbackRate.value !== 1.0) {
                        videoRef.value.playbackRate = playbackRate.value;
                    }
                    if (videoRef.value.paused) {
                        videoRef.value.play().catch(() => { });
                    }
                }
            } catch (e) {
                console.warn('[CinemaPlayback] MseBackendClient seek error:', e);
            } finally {
                isSeekPending.value = false;
                isBuffering.value = false;
            }
            return;
        }

        if (isRemuxStream.value && !isServerCached.value) {
            isBuffering.value = true;
            const seekToken = ++currentSeekToken;
            let actualTarget = clamped;

            if (clamped > 2) {
                try {
                    const type = isEpisode.value ? 'episode' : 'movie';
                    const id = activeItem.value?.watchable_id || activeItem.value?.id;
                    const res = await fetch(`/api/media/seek-keyframe?type=${type}&id=${id}&time=${clamped}`);
                    if (res.ok) {
                        const data = await res.json();
                        if (typeof data.keyframe === 'number' && data.keyframe >= 0) {
                            actualTarget = data.keyframe;
                        }
                    }
                } catch (e) { }
            }

            if (seekToken !== currentSeekToken) return;

            isSeekPending.value = true;
            remuxStartOffset.value = actualTarget;
            currentTime.value = actualTarget;
            if (options.onTimeUpdateCallback) {
                options.onTimeUpdateCallback(actualTarget);
            }

            await nextTick();
            if (seekToken !== currentSeekToken) return;

            if (videoRef.value) {
                videoRef.value.src = streamUrl.value;
                videoRef.value.load();
                videoRef.value.playbackRate = playbackRate.value;
                videoRef.value.play().catch(() => { });
            }
        } else if (videoRef.value) {
            videoRef.value.currentTime = clamped;
            videoRef.value.playbackRate = playbackRate.value;
            if (videoRef.value.paused) {
                videoRef.value.play().catch(() => { });
            }
        }
    };

    let seekDebounceTimer: any = null;
    const seekRelative = (seconds: number) => {
        const current = currentTime.value;
        const target = Math.max(0, Math.min(duration.value || 0, current + seconds));
        currentTime.value = target;
        if (options.onTimeUpdateCallback) {
            options.onTimeUpdateCallback(target);
        }

        clearTimeout(seekDebounceTimer);
        seekDebounceTimer = setTimeout(() => {
            executeSeek(currentTime.value);
        }, 150);
    };

    const updateBufferProgress = () => {
        if (!videoRef.value || !duration.value || duration.value <= 0 || !isFinite(duration.value)) return;
        const buf = videoRef.value.buffered;
        if (buf && buf.length > 0) {
            let maxBufferedEnd = 0;
            for (let i = 0; i < buf.length; i++) {
                const end = buf.end(i);
                if (end > maxBufferedEnd) {
                    maxBufferedEnd = end;
                }
            }
            const effectiveEnd = (isRemuxStream.value && remuxStartOffset.value > 0)
                ? remuxStartOffset.value + maxBufferedEnd
                : maxBufferedEnd;
            const calc = Math.min(100, Math.max(0, (effectiveEnd / duration.value) * 100));
            if (calc > bufferedPercent.value) {
                bufferedPercent.value = calc;
            }
        }
    };

    const onTimeUpdate = () => {
        if (!videoRef.value) return;

        const rawCurrent = videoRef.value.currentTime;
        if (isSeekPending.value) {
            if (rawCurrent > 1.0) {
                return;
            }
            isSeekPending.value = false;
        }

        if ((isRemuxStream.value || engineMode.value === 'web') && remuxStartOffset.value > 0) {
            currentTime.value = remuxStartOffset.value + rawCurrent;
        } else {
            currentTime.value = rawCurrent;
        }

        if (!isDurationLocked.value && !isRemuxStream.value && engineMode.value !== 'web') {
            const d = videoRef.value.duration;
            if (d && isFinite(d) && !isNaN(d) && d > 0) {
                duration.value = d;
            }
        }

        updateBufferProgress();
        if (options.onTimeUpdateCallback) {
            options.onTimeUpdateCallback(currentTime.value);
        }
    };

    const updateLiveResolution = () => {
        const v = videoRef.value;
        if (!v) return;
        const w = v.videoWidth;
        const h = v.videoHeight;
        if (w > 0 && h > 0) {
            if (h >= 2160 || w >= 3800) liveResolution.value = '4K UHD';
            else if (h >= 1440 || w >= 2500) liveResolution.value = '1440p 2K';
            else if (h >= 1000 || w >= 1900) liveResolution.value = '1080p FHD';
            else if (h >= 700 || w >= 1200) liveResolution.value = '720p HD';
            else if (h >= 540) liveResolution.value = '576p SD';
            else if (h >= 450) liveResolution.value = '480p SD';
            else if (h >= 340) liveResolution.value = '360p';
            else if (h >= 200) liveResolution.value = `${h}p`;
            else liveResolution.value = `${w}x${h}`;
        }
    };

    const onLoadedMetadata = () => {
        updateLiveResolution();
        if (!videoRef.value) return;

        videoRef.value.playbackRate = playbackRate.value;

        if (!isDurationLocked.value && !isRemuxStream.value && engineMode.value !== 'web') {
            const d = videoRef.value.duration;
            if (d && isFinite(d) && !isNaN(d) && d > 0) {
                duration.value = d;
            }
        }

        if (pendingInitialSeek.value > 0 && !isRemuxStream.value && engineMode.value !== 'web') {
            videoRef.value.currentTime = pendingInitialSeek.value;
        }

        videoRef.value.play().then(() => {
            isPlaying.value = true;
        }).catch((err) => {
            console.warn('Autoplay prevented or waiting for interaction:', err);
        });
    };

    const savePlaybackProgress = async (isCompleted = false) => {
        if (!activeItem.value || !duration.value || duration.value <= 0) return;
        const timeSec = Math.floor(currentTime.value);
        const durSec = Math.floor(duration.value);
        const progressPct = durSec > 0 ? (timeSec / durSec) * 100 : 0;
        const completed = isCompleted || progressPct >= 92;

        try {
            await fetch('/api/playback/progress', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    watchable_id: activeItem.value.watchable_id || activeItem.value.id,
                    watchable_type: isEpisode.value ? 'episode' : 'movie',
                    progress_seconds: completed ? 0 : timeSec,
                    duration_seconds: durSec,
                    is_completed: completed,
                }),
            });
        } catch (e) { }
    };

    const formatTime = (seconds: number) => {
        if (!seconds || isNaN(seconds) || !isFinite(seconds) || seconds <= 0) return '00:00';
        const hrs = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);

        const m = mins < 10 ? `0${mins}` : `${mins}`;
        const s = secs < 10 ? `0${secs}` : `${secs}`;

        if (hrs > 0) {
            return `${hrs}:${m}:${s}`;
        }
        return `${mins}:${s}`;
    };

    return {
        activeItem,
        videoRef,
        playerContainerRef,
        isPlaying,
        isMuted,
        volume,
        currentTime,
        duration,
        isDurationLocked,
        bufferedPercent,
        isFullscreen,
        isControlsVisible,
        isBuffering,
        liveResolution,
        isRemuxStream,
        engineMode,
        playbackRate,
        setPlaybackRate,
        remuxStartOffset,
        isServerCached,
        cachedStreamUrl,
        isEpisode,
        itemKey,
        streamUrl,
        progressPercent,
        togglePlay,
        setVolume,
        toggleMute,
        toggleFullscreen,
        toggleStreamEngine,
        executeSeek,
        seekRelative,
        onTimeUpdate,
        onLoadedMetadata,
        updateLiveResolution,
        savePlaybackProgress,
        formatTime,
        checkNeedsRemux,
        effectiveVideoSrc,
        initWebEngine,
        destroyWebEngine,
        webEngineInstance,
    };
}

