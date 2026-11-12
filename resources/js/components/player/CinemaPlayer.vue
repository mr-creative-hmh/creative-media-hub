<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
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
    return showSubtitlesModal.value || showSubtitleSearchModal.value || showEqualizerModal.value || showPlaylistDrawer.value || showDiagnosticsHud.value;
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
    return currentPlaylist.value.findIndex((p: any) => String(p.id) === String(playback.activeItem.value?.id));
});

const hasNext = computed(() => {
    return currentIndex.value >= 0 && currentIndex.value < currentPlaylist.value.length - 1;
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
    const fallbackDur = Number(newItem?.runtime_minutes) ? Number(newItem.runtime_minutes) * 60 : 0;
    playback.duration.value = exactDur > 0 ? exactDur : fallbackDur;
    playback.isDurationLocked.value = exactDur > 0;
    playback.isRemuxStream.value = playback.checkNeedsRemux(newItem);

    subtitles.selectedSubtitleId.value = 'off';
    subtitles.parsedCues.value = [];
    subtitles.activeCueText.value = '';

    setTimeout(() => {
        const type = playback.isEpisode.value ? 'episode' : 'movie';
        subtitles.fetchSubtitlesForMedia(type, newItem.watchable_id || newItem.id);
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
        subtitles.selectedSubtitleId.value
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
    const type = playback.isEpisode.value ? 'episode' : 'movie';
    const id = playback.activeItem.value?.watchable_id || playback.activeItem.value?.id;
    if (id) {
        subtitles.fetchSubtitlesForMedia(type, id);
    }

    if (playback.itemKey.value) {
        const savedSync = localStorage.getItem(`audio_sync_${playback.itemKey.value}`);
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
        class="fixed inset-0 z-50 bg-black flex items-center justify-center select-none overflow-hidden font-sans group/player"
        :class="{ 'cursor-none': !playback.isControlsVisible.value && playback.isPlaying.value }"
    >
        <!-- Video Viewport -->
        <video
            ref="videoRef"
            :src="playback.effectiveVideoSrc.value"
            class="w-full h-full object-contain cursor-pointer"
            autoplay
            playsinline
            preload="auto"
            crossorigin="anonymous"
            @loadedmetadata="playback.onLoadedMetadata"
            @play="handlePlay"
            @pause="handlePause"
            @timeupdate="playback.onTimeUpdate"
            @ended="handleVideoEnded"
            @waiting="playback.isBuffering.value = true"
            @playing="playback.isBuffering.value = false; playback.updateLiveResolution()"
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
                class="absolute pointer-events-none z-30 w-20 h-20 rounded-full bg-black/60 backdrop-blur-md border border-white/20 flex items-center justify-center text-white shadow-2xl"
            >
                <svg v-if="playback.isPlaying.value" class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z" />
                </svg>
                <svg v-else class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
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
                class="absolute left-16 z-30 pointer-events-none flex items-center gap-2 px-5 py-2.5 rounded-full bg-black/70 backdrop-blur-md border border-white/20 text-white font-bold text-sm tracking-wide shadow-2xl animate-pulse"
            >
                <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.334 4zM4.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0011 16V8a1 1 0 00-1.6-.8l-5.334 4z" />
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
                class="absolute right-16 z-30 pointer-events-none flex items-center gap-2 px-5 py-2.5 rounded-full bg-black/70 backdrop-blur-md border border-white/20 text-white font-bold text-sm tracking-wide shadow-2xl animate-pulse"
            >
                <span>+10s</span>
                <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.933 12.8a1 1 0 000-1.6L6.6 7.2A1 1 0 005 8v8a1 1 0 001.6.8l5.333-4zM19.933 12.8a1 1 0 000-1.6l-5.333-4A1 1 0 0013 8v8a1 1 0 001.6.8l5.333-4z" />
                </svg>
            </div>
        </Transition>

        <!-- Loading / Buffering Spinner Overlay -->
        <Transition name="fade">
            <div
                v-if="playback.isBuffering.value"
                class="absolute inset-0 flex items-center justify-center pointer-events-none z-30 bg-black/45 backdrop-blur-xs transition-all"
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
            :live-resolution="playback.liveResolution.value"
            :is-controls-visible="playback.isControlsVisible.value"
            @close="emit('close')"
            @toggle-stream="playback.toggleStreamEngine"
        />

        <!-- Bottom Controls Dock -->
        <div
            class="absolute bottom-0 inset-x-0 p-4 sm:p-6 bg-gradient-to-t from-black/95 via-black/60 to-transparent z-30 transition-all duration-300 pointer-events-auto space-y-2"
            :class="{ 'opacity-0 translate-y-4 pointer-events-none': !playback.isControlsVisible.value }"
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
            @open-search="showSubtitlesModal = false; showSubtitleSearchModal = true"
            @translate="handleTranslate"
        />

        <CinemaSubtitleSearchModal
            :is-open="showSubtitleSearchModal"
            :item="playback.activeItem.value"
            :is-episode="playback.isEpisode.value"
            :is-translating="subtitles.isTranslatingSubtitle.value"
            @close="showSubtitleSearchModal = false"
            @translate="handleTranslate"
            @downloaded="() => {
                const type = playback.isEpisode.value ? 'episode' : 'movie';
                const id = playback.activeItem.value?.watchable_id || playback.activeItem.value?.id;
                subtitles.fetchSubtitlesForMedia(type, id);
            }"
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
            :display-codec="playback.activeItem.value?.video_codec || playback.activeItem.value?.codec || 'H.264'"
            :display-audio="playback.activeItem.value?.audio_codec || 'AAC'"
            :remux-start-offset="playback.remuxStartOffset.value"
            :audio-delay-ms="audioPipeline.audioDelayMs.value"
            @close="showDiagnosticsHud = false"
        />
    </div>
</template>
