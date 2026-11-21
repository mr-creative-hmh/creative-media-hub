<script setup lang="ts">
import {
    ref,
    computed,
    onMounted,
    onBeforeUnmount,
    watch,
    nextTick,
} from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import CinemaLoader from '@/components/player/CinemaLoader.vue';
import CinemaHeader from '@/components/player/CinemaHeader.vue';
import CinemaScrubber from '@/components/player/CinemaScrubber.vue';
import CinemaControlsBar from '@/components/player/CinemaControlsBar.vue';
import CinemaSubtitleOverlay from '@/components/player/CinemaSubtitleOverlay.vue';
import CinemaSubtitlesModal from '@/components/player/CinemaSubtitlesModal.vue';
import CinemaSubtitleSearchModal from '@/components/player/CinemaSubtitleSearchModal.vue';
import CinemaEqualizerModal from '@/components/player/CinemaEqualizerModal.vue';
import CinemaPlaylistDrawer from '@/components/player/CinemaPlaylistDrawer.vue';
import CinemaDiagnosticsHud from '@/components/player/CinemaDiagnosticsHud.vue';

import { useCinemaPlayback } from '@/composables/player/useCinemaPlayback';
import { useCinemaAudioPipeline } from '@/composables/player/useCinemaAudioPipeline';
import { useCinemaSubtitles } from '@/composables/player/useCinemaSubtitles';
import { useCinemaKeyboardShortcuts } from '@/composables/player/useCinemaKeyboardShortcuts';

const props = defineProps<{
    item: any;
    initialProgress?: number;
    playlist?: any[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'update:progress', val: number): void;
}>();

const { isRTL, t } = useI18n();
const toast = useToast();

// Modal visibility flags
const showSubtitlesModal = ref(false);
const showSubtitleSearchModal = ref(false);
const showEqualizerModal = ref(false);
const showPlaylistDrawer = ref(false);
const showDiagnosticsHud = ref(false);

// Template refs
const videoRef = ref<HTMLVideoElement | null>(null);
const playerContainerRef = ref<HTMLDivElement | null>(null);

// Interactive visual feedback states
const touchFeedback = ref<'forward' | 'backward' | null>(null);
let touchFeedbackTimer: any = null;
const triggerTouchFeedback = (dir: 'forward' | 'backward') => {
    touchFeedback.value = dir;
    clearTimeout(touchFeedbackTimer);
    touchFeedbackTimer = setTimeout(() => {
        touchFeedback.value = null;
    }, 800);
};

const showCenterPlayIndicator = ref(false);
let centerPlayTimer: any = null;
const triggerCenterPlayIndicator = () => {
    showCenterPlayIndicator.value = true;
    clearTimeout(centerPlayTimer);
    centerPlayTimer = setTimeout(() => {
        showCenterPlayIndicator.value = false;
    }, 600);
};

const isAnyModalOpen = () => {
    return (
        showSubtitlesModal.value ||
        showSubtitleSearchModal.value ||
        showEqualizerModal.value ||
        showPlaylistDrawer.value ||
        showDiagnosticsHud.value
    );
};

const closeTopModal = () => {
    if (showSubtitleSearchModal.value) {
        showSubtitleSearchModal.value = false;
        return true;
    }
    if (showSubtitlesModal.value) {
        showSubtitlesModal.value = false;
        return true;
    }
    if (showEqualizerModal.value) {
        showEqualizerModal.value = false;
        return true;
    }
    if (showPlaylistDrawer.value) {
        showPlaylistDrawer.value = false;
        return true;
    }
    if (showDiagnosticsHud.value) {
        showDiagnosticsHud.value = false;
        return true;
    }
    return false;
};

// Controls visibility timer
let controlsTimeout: any = null;
const showControlsTemporarily = () => {
    playback.isControlsVisible.value = true;
    clearTimeout(controlsTimeout);
    if (playback.isPlaying.value) {
        controlsTimeout = setTimeout(() => {
            if (!isAnyModalOpen()) {
                playback.isControlsVisible.value = false;
            }
        }, 3500);
    }
};

// Playback Composable
const playback = useCinemaPlayback({
    item: computed(() => props.item),
    initialProgress: props.initialProgress,
    videoRef,
    playerContainerRef,
    onTimeUpdateCallback: (time) => {
        subtitles.updateActiveCue(time);
        emit('update:progress', time);
    },
    onToast: (msg) => toast.info(msg),
    t,
});

// Audio Pipeline Composable (Web Audio API single-source delay & boosts)
const audioPipeline = useCinemaAudioPipeline({
    itemKey: playback.itemKey,
    onToast: (msg) => toast.info(msg),
});

// Subtitles Composable
const subtitles = useCinemaSubtitles({
    onToast: (msg) => toast.info(msg),
});

// K-Lite MPC-HC Shader Presets
type ShaderPreset = 'standard' | 'clarity' | 'vivid' | 'night';
const SHADER_ORDER: ShaderPreset[] = ['standard', 'clarity', 'vivid', 'night'];
const SHADER_LABELS: Record<ShaderPreset, string> = {
    standard: 'Standard (Native)',
    clarity: 'K-Lite Clarity (Sharpen)',
    vivid: 'HDR Vivid',
    night: 'Night Vision',
};
const initialShader = (() => {
    try {
        const saved = localStorage.getItem(
            'cinema_shader_preset',
        ) as ShaderPreset | null;
        if (saved && SHADER_ORDER.includes(saved)) return saved;
    } catch {}
    return 'standard' as ShaderPreset;
})();
const shaderPreset = ref<ShaderPreset>(initialShader);

const cycleShaderPreset = () => {
    const idx = SHADER_ORDER.indexOf(shaderPreset.value);
    const next = SHADER_ORDER[(idx + 1) % SHADER_ORDER.length];
    shaderPreset.value = next;
    try {
        localStorage.setItem('cinema_shader_preset', next);
    } catch {}
    toast.info(`Shader: ${SHADER_LABELS[next]}`);
};

const videoShaderStyle = computed(() => {
    switch (shaderPreset.value) {
        case 'clarity':
            return {
                filter: 'url(#klite-luma-sharpen) contrast(1.04) saturate(1.05)',
            };
        case 'vivid':
            return { filter: 'contrast(1.12) saturate(1.18) brightness(1.03)' };
        case 'night':
            return { filter: 'brightness(1.18) contrast(1.08) saturate(1.04)' };
        default:
            return undefined;
    }
});

const handleTogglePlay = () => {
    playback.togglePlay();
    triggerCenterPlayIndicator();
};

const handleSeekRelative = (seconds: number) => {
    playback.seekRelative(seconds);
    if (seconds > 0) {
        triggerTouchFeedback('forward');
    } else {
        triggerTouchFeedback('backward');
    }
};

// Playlist navigation
const currentPlaylist = computed(() => {
    return props.playlist && props.playlist.length > 0 ? props.playlist : [];
});

const currentIndex = computed(() => {
    return currentPlaylist.value.findIndex(
        (p: any) => String(p.id) === String(playback.activeItem.value?.id),
    );
});

const hasNext = computed(() => {
    return (
        currentIndex.value >= 0 &&
        currentIndex.value < currentPlaylist.value.length - 1
    );
});

const hasPrevious = computed(() => {
    return currentIndex.value > 0;
});

const playNext = () => {
    if (!hasNext.value) return;
    const nextItem = currentPlaylist.value[currentIndex.value + 1];
    if (nextItem) changeActiveMedia(nextItem);
};

const playPrevious = () => {
    if (!hasPrevious.value) return;
    const prevItem = currentPlaylist.value[currentIndex.value - 1];
    if (prevItem) changeActiveMedia(prevItem);
};

const changeActiveMedia = (newItem: any) => {
    playback.savePlaybackProgress();
    playback.destroyWebEngine();
    playback.activeItem.value = newItem;
    playback.currentTime.value = 0;
    playback.remuxStartOffset.value = 0;
    const exactDur = Number(newItem?.duration_seconds) || 0;
    const fallbackDur = Number(newItem?.runtime_minutes)
        ? Number(newItem.runtime_minutes) * 60
        : 0;
    playback.duration.value = exactDur > 0 ? exactDur : fallbackDur;
    playback.isDurationLocked.value = exactDur > 0;
    const nextMode = playback.selectOptimalEngine(newItem);
    playback.engineMode.value = nextMode;
    playback.isRemuxStream.value = nextMode !== 'direct';

    subtitles.selectedSubtitleId.value = 'off';
    subtitles.parsedCues.value = [];
    subtitles.activeCueText.value = '';

    setTimeout(() => {
        const type = playback.isEpisode.value ? 'episode' : 'movie';
        subtitles.fetchSubtitlesForMedia(
            type,
            newItem.watchable_id || newItem.id,
        );
        if (playback.engineMode.value === 'web') {
            playback.initWebEngine();
        } else if (videoRef.value) {
            videoRef.value.load();
            videoRef.value.play().catch(() => {});
        }
    }, 50);
};

// Keyboard Shortcuts Composable
useCinemaKeyboardShortcuts({
    onTogglePlay: () => handleTogglePlay(),
    onSeekRelative: (sec) => handleSeekRelative(sec),
    onVolumeUp: () => playback.setVolume(playback.volume.value + 0.05),
    onVolumeDown: () => playback.setVolume(playback.volume.value - 0.05),
    onToggleMute: () => playback.toggleMute(),
    onToggleFullscreen: () => playback.toggleFullscreen(),
    onToggleSubtitles: () => {
        showSubtitlesModal.value = !showSubtitlesModal.value;
    },
    onToggleEqualizer: () => {
        showEqualizerModal.value = !showEqualizerModal.value;
    },
    onToggleDiagnostics: () => {
        showDiagnosticsHud.value = !showDiagnosticsHud.value;
    },
    onClose: () => emit('close'),
    isModalOpen: isAnyModalOpen,
    closeTopModal: closeTopModal,
});

// Video Element Handlers
const handlePlay = () => {
    playback.isPlaying.value = true;
    try {
        if (videoRef.value) {
            audioPipeline.initAudioPipeline(videoRef.value);
            audioPipeline.resumeAudioContext();
        }
    } catch (e) {
        console.warn('Audio pipeline init deferred:', e);
    }
    showControlsTemporarily();
};

const handlePause = () => {
    playback.isPlaying.value = false;
    playback.savePlaybackProgress();
    playback.isControlsVisible.value = true;
};

const handleVolumeChange = (val: number) => {
    playback.setVolume(val);
    audioPipeline.setVolumeGain(val);
};

const handleTranslate = async () => {
    await subtitles.translateSubtitleToArabic(
        playback.activeItem.value,
        playback.isEpisode.value,
        subtitles.selectedSubtitleId.value,
    );
};

const handleSeek = (seconds: number) => {
    playback.executeSeek(seconds);
};

const handleVideoEnded = () => {
    playback.savePlaybackProgress(true);
    if (hasNext.value) {
        toast.info(t('player.autoplay_next') || 'Playing next episode...');
        setTimeout(() => {
            if (hasNext.value) {
                playNext();
            }
        }, 1500);
    } else {
        toast.info(t('player.playback_completed') || 'Playback completed');
    }
};

// Lifecycle
let progressInterval: any = null;
onMounted(() => {
    subtitles.updateActiveCue(playback.currentTime.value);
    const type = playback.isEpisode.value ? 'episode' : 'movie';
    const id =
        playback.activeItem.value?.watchable_id ||
        playback.activeItem.value?.id;
    if (id) {
        subtitles.fetchSubtitlesForMedia(type, id);
    }

    if (playback.itemKey.value) {
        const savedSync = localStorage.getItem(
            `audio_sync_${playback.itemKey.value}`,
        );
        if (savedSync) {
            audioPipeline.setAudioDelay(parseInt(savedSync, 10));
        }
    }

    progressInterval = setInterval(() => {
        if (playback.isPlaying.value) {
            playback.savePlaybackProgress();
        }
    }, 15000);

    if (playback.engineMode.value === 'web') {
        playback.initWebEngine();
    }
});

onBeforeUnmount(() => {
    playback.destroyWebEngine();
    clearInterval(progressInterval);
    clearTimeout(controlsTimeout);
    clearTimeout(touchFeedbackTimer);
    clearTimeout(centerPlayTimer);
    playback.savePlaybackProgress();
});
</script>

<template>
    <div
        ref="playerContainerRef"
        dir="ltr"
        @mousemove="showControlsTemporarily"
        @click="showControlsTemporarily"
        class="group/player fixed inset-0 z-50 flex items-center justify-center overflow-hidden bg-black font-sans select-none"
        :class="{
            'cursor-none':
                !playback.isControlsVisible.value && playback.isPlaying.value,
        }"
    >
        <!-- Inline SVG K-Lite MPC-HC Luma Sharpen Shader -->
        <svg
            aria-hidden="true"
            class="pointer-events-none absolute h-0 w-0"
            focusable="false"
        >
            <defs>
                <filter id="klite-luma-sharpen">
                    <feConvolveMatrix
                        order="3"
                        kernelMatrix="0 -0.22 0 -0.22 1.88 -0.22 0 -0.22 0"
                        preserveAlpha="true"
                    />
                </filter>
            </defs>
        </svg>

        <!-- Video Viewport -->
        <video
            ref="videoRef"
            :src="playback.effectiveVideoSrc.value"
            :style="videoShaderStyle"
            class="h-full w-full cursor-pointer object-contain"
            autoplay
            playsinline
            preload="auto"
            crossorigin="anonymous"
            @loadedmetadata="playback.onLoadedMetadata"
            @play="handlePlay"
            @pause="handlePause"
            @timeupdate="playback.onTimeUpdate"
            @ended="handleVideoEnded"
            @error="playback.handleDirectVideoError"
            @waiting="playback.isBuffering.value = true"
            @playing="
                playback.isBuffering.value = false;
                playback.updateLiveResolution();
            "
            @click="handleTogglePlay"
        />

        <!-- Center Play/Pause Animated Ripple Indicator -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-75"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-300 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-125"
        >
            <div
                v-if="showCenterPlayIndicator"
                class="pointer-events-none absolute z-30 flex h-20 w-20 items-center justify-center rounded-full border border-white/20 bg-black/60 text-white shadow-2xl backdrop-blur-md"
            >
                <svg
                    v-if="playback.isPlaying.value"
                    class="h-10 w-10"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path d="M8 5v14l11-7z" />
                </svg>
                <svg
                    v-else
                    class="h-10 w-10"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" />
                </svg>
            </div>
        </Transition>

        <!-- Floating Seek Feedback Pills (-10s / +10s) -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-x-4 scale-90"
            enter-to-class="opacity-100 translate-x-0 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-x-0 scale-100"
            leave-to-class="opacity-0 -translate-x-4 scale-90"
        >
            <div
                v-if="touchFeedback === 'backward'"
                class="pointer-events-none absolute left-16 z-30 flex animate-pulse items-center gap-2 rounded-full border border-white/20 bg-black/70 px-5 py-2.5 text-sm font-bold tracking-wide text-white shadow-2xl backdrop-blur-md"
            >
                <svg
                    class="h-5 w-5 text-amber-400"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.334 4zM4.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0011 16V8a1 1 0 00-1.6-.8l-5.334 4z"
                    />
                </svg>
                <span>-10s</span>
            </div>
        </Transition>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-x-4 scale-90"
            enter-to-class="opacity-100 translate-x-0 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-x-0 scale-100"
            leave-to-class="opacity-0 translate-x-4 scale-90"
        >
            <div
                v-if="touchFeedback === 'forward'"
                class="pointer-events-none absolute right-16 z-30 flex animate-pulse items-center gap-2 rounded-full border border-white/20 bg-black/70 px-5 py-2.5 text-sm font-bold tracking-wide text-white shadow-2xl backdrop-blur-md"
            >
                <span>+10s</span>
                <svg
                    class="h-5 w-5 text-amber-400"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M11.933 12.8a1 1 0 000-1.6L6.6 7.2A1 1 0 005 8v8a1 1 0 001.6.8l5.333-4zM19.933 12.8a1 1 0 000-1.6l-5.333-4A1 1 0 0013 8v8a1 1 0 001.6.8l5.333-4z"
                    />
                </svg>
            </div>
        </Transition>

        <!-- Loading / Buffering Spinner Overlay -->
        <Transition name="fade">
            <div
                v-if="playback.isBuffering.value"
                class="pointer-events-none absolute inset-0 z-30 flex items-center justify-center bg-black/45 backdrop-blur-xs transition-all"
            >
                <CinemaLoader
                    size="md"
                    :is-r-t-l="isRTL"
                    :status-text="t('player.buffering_stream')"
                />
            </div>
        </Transition>

        <!-- Subtitle Overlay -->
        <CinemaSubtitleOverlay
            :active-cue-text="subtitles.activeCueText.value"
            :font-family="subtitles.subtitleFontFamily.value"
            :font-size="subtitles.subtitleFontSize.value"
            :is-controls-visible="playback.isControlsVisible.value"
        />

        <!-- Top Header Bar -->
        <CinemaHeader
            :item="playback.activeItem.value"
            :is-episode="playback.isEpisode.value"
            :is-remux-stream="playback.isRemuxStream.value"
            :engine-mode="playback.engineMode.value"
            :shader-preset="shaderPreset"
            :live-resolution="playback.liveResolution.value"
            :is-controls-visible="playback.isControlsVisible.value"
            @close="emit('close')"
            @toggle-stream="playback.toggleStreamEngine"
            @toggle-shader="cycleShaderPreset"
        />

        <!-- Bottom Controls Dock -->
        <div
            class="pointer-events-auto absolute inset-x-0 bottom-0 z-30 space-y-2 bg-gradient-to-t from-black/95 via-black/60 to-transparent p-4 transition-all duration-300 sm:p-6"
            :class="{
                'pointer-events-none translate-y-4 opacity-0':
                    !playback.isControlsVisible.value,
            }"
        >
            <!-- Scrubber Timeline -->
            <CinemaScrubber
                :current-time="playback.currentTime.value"
                :duration="playback.duration.value"
                :buffered-percent="playback.bufferedPercent.value"
                :progress-percent="playback.progressPercent.value"
                :format-time="playback.formatTime"
                @seek="handleSeek"
            />

            <!-- Control Bar Buttons -->
            <CinemaControlsBar
                :is-playing="playback.isPlaying.value"
                :is-muted="playback.isMuted.value"
                :volume="playback.volume.value"
                :current-time="playback.currentTime.value"
                :duration="playback.duration.value"
                :format-time="playback.formatTime"
                :has-next="hasNext"
                :has-previous="hasPrevious"
                :is-controls-visible="playback.isControlsVisible.value"
                :has-subtitles="subtitles.availableSubtitles.value.length > 0"
                :has-playlist="currentPlaylist.length > 1"
                :is-fullscreen="playback.isFullscreen.value"
                :playback-rate="playback.playbackRate.value"
                @play-pause="handleTogglePlay"
                @next="playNext"
                @prev="playPrevious"
                @seek-relative="handleSeekRelative"
                @toggle-mute="playback.toggleMute"
                @volume-change="handleVolumeChange"
                @set-speed="playback.setPlaybackRate"
                @toggle-subtitles="showSubtitlesModal = !showSubtitlesModal"
                @toggle-equalizer="showEqualizerModal = !showEqualizerModal"
                @toggle-playlist="showPlaylistDrawer = !showPlaylistDrawer"
                @toggle-diagnostics="showDiagnosticsHud = !showDiagnosticsHud"
                @toggle-fullscreen="playback.toggleFullscreen"
            />
        </div>

        <!-- Modals & Drawers -->
        <CinemaSubtitlesModal
            :is-open="showSubtitlesModal"
            :subtitles="subtitles.availableSubtitles.value"
            :selected-id="subtitles.selectedSubtitleId.value"
            :delay="subtitles.subtitleDelay.value"
            :font="subtitles.subtitleFont.value"
            :font-size="subtitles.subtitleFontSize.value"
            :is-translating="subtitles.isTranslatingSubtitle.value"
            @close="showSubtitlesModal = false"
            @select="subtitles.loadSubtitleTrack"
            @adjust-delay="subtitles.adjustSubtitleDelay"
            @reset-delay="subtitles.resetSubtitleDelay"
            @set-font="subtitles.setSubtitleFont"
            @set-size="subtitles.setSubtitleFontSize"
            @open-search="
                showSubtitlesModal = false;
                showSubtitleSearchModal = true;
            "
            @translate="handleTranslate"
        />

        <CinemaSubtitleSearchModal
            :is-open="showSubtitleSearchModal"
            :item="playback.activeItem.value"
            :is-episode="playback.isEpisode.value"
            :is-translating="subtitles.isTranslatingSubtitle.value"
            @close="showSubtitleSearchModal = false"
            @translate="handleTranslate"
            @downloaded="
                (downloadedSub) => {
                    const type = playback.isEpisode.value ? 'episode' : 'movie';
                    const id =
                        playback.activeItem.value?.watchable_id ||
                        playback.activeItem.value?.id;
                    subtitles.fetchSubtitlesForMedia(
                        type,
                        id,
                        downloadedSub?.id,
                    );
                }
            "
        />

        <CinemaEqualizerModal
            :is-open="showEqualizerModal"
            :vocal-boost="audioPipeline.vocalBoost.value"
            :bass-boost="audioPipeline.bassBoost.value"
            :night-mode="audioPipeline.nightMode.value"
            :audio-delay-ms="audioPipeline.audioDelayMs.value"
            @close="showEqualizerModal = false"
            @toggle-vocal-boost="audioPipeline.toggleVocalBoost"
            @toggle-bass-boost="audioPipeline.toggleBassBoost"
            @toggle-night-mode="audioPipeline.toggleNightMode"
            @set-audio-delay="audioPipeline.setAudioDelay"
            @nudge-audio-delay="audioPipeline.nudgeAudioDelay"
            @reset-audio-delay="audioPipeline.resetAudioDelay"
        />

        <CinemaPlaylistDrawer
            :is-open="showPlaylistDrawer"
            :playlist="currentPlaylist"
            :current-item-id="playback.activeItem.value?.id"
            :is-episode="playback.isEpisode.value"
            @close="showPlaylistDrawer = false"
            @select="changeActiveMedia"
        />

        <CinemaDiagnosticsHud
            :is-open="showDiagnosticsHud"
            :item="playback.activeItem.value"
            :is-remux-stream="playback.isRemuxStream.value"
            :stream-url="playback.streamUrl.value"
            :current-time="playback.currentTime.value"
            :duration="playback.duration.value"
            :buffered-percent="playback.bufferedPercent.value"
            :live-resolution="playback.liveResolution.value"
            :display-codec="
                playback.activeItem.value?.video_codec ||
                playback.activeItem.value?.codec ||
                'H.264'
            "
            :display-audio="playback.activeItem.value?.audio_codec || 'AAC'"
            :remux-start-offset="playback.remuxStartOffset.value"
            :audio-delay-ms="audioPipeline.audioDelayMs.value"
            @close="showDiagnosticsHud = false"
        />
    </div>
</template>
