<script setup lang="ts">
import { ref, computed } from 'vue';
import {
    Play,
    Pause,
    SkipBack,
    SkipForward,
    RotateCcw,
    RotateCw,
    Volume2,
    Volume1,
    VolumeX,
    MessageSquare,
    Sliders,
    Layers,
    Activity,
    Maximize2,
    Minimize2,
    Check,
} from 'lucide-vue-next';
import { useI18n } from '@/i18n/useI18n';

const props = withDefaults(
    defineProps<{
        isPlaying: boolean;
        isMuted: boolean;
        volume: number;
        currentTime: number;
        duration: number;
        formatTime: (sec: number) => string;
        hasNext: boolean;
        hasPrevious: boolean;
        isControlsVisible: boolean;
        hasSubtitles: boolean;
        isFullscreen: boolean;
        hasPlaylist?: boolean;
        playbackRate?: number;
    }>(),
    {
        playbackRate: 1.0,
    },
);

const emit = defineEmits<{
    (e: 'play-pause'): void;
    (e: 'next'): void;
    (e: 'prev'): void;
    (e: 'seek-relative', sec: number): void;
    (e: 'toggle-mute'): void;
    (e: 'volume-change', val: number): void;
    (e: 'toggle-subtitles'): void;
    (e: 'toggle-equalizer'): void;
    (e: 'toggle-playlist'): void;
    (e: 'toggle-diagnostics'): void;
    (e: 'toggle-fullscreen'): void;
    (e: 'set-speed', rate: number): void;
}>();

const { t } = useI18n();
const showSpeedMenu = ref(false);

const handleVolumeInput = (e: Event) => {
    const val = parseFloat((e.target as HTMLInputElement).value);
    emit('volume-change', val);
};
</script>

<template>
    <div
        class="pointer-events-auto flex items-center justify-between gap-2 sm:gap-4"
    >
        <!-- Left: Play/Pause, Next/Prev, Skip, Volume, Time -->
        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Prev Episode -->
            <button
                v-if="hasPlaylist"
                @click="emit('prev')"
                :disabled="!hasPrevious"
                class="rounded-xl p-2 text-white/80 transition-all hover:bg-white/10 hover:text-white active:scale-95 disabled:opacity-30 disabled:hover:bg-transparent"
                :title="t('player.prev_episode') || 'Previous'"
            >
                <SkipBack class="h-4 w-4 sm:h-5 sm:w-5" />
            </button>

            <!-- Seek -10s -->
            <button
                @click="emit('seek-relative', -10)"
                class="rounded-xl p-2 text-white/80 transition-all hover:bg-white/10 hover:text-white active:scale-95"
                :title="t('player.seek_back_10') || 'Rewind 10s'"
            >
                <RotateCcw class="h-4 w-4 sm:h-5 sm:w-5" />
            </button>

            <!-- Main Play / Pause -->
            <button
                @click="emit('play-pause')"
                class="rounded-2xl bg-cyan-500 p-2.5 font-bold text-slate-950 shadow-lg shadow-cyan-500/25 transition-all hover:bg-cyan-400 active:scale-90 sm:p-3"
                :title="
                    isPlaying
                        ? t('player.pause') || 'Pause'
                        : t('player.play') || 'Play'
                "
            >
                <Pause
                    v-if="isPlaying"
                    class="h-5 w-5 fill-current sm:h-6 sm:w-6"
                />
                <Play
                    v-else
                    class="h-5 w-5 translate-x-0.5 fill-current sm:h-6 sm:w-6"
                />
            </button>

            <!-- Seek +10s -->
            <button
                @click="emit('seek-relative', 10)"
                class="rounded-xl p-2 text-white/80 transition-all hover:bg-white/10 hover:text-white active:scale-95"
                :title="t('player.seek_forward_10') || 'Forward 10s'"
            >
                <RotateCw class="h-4 w-4 sm:h-5 sm:w-5" />
            </button>

            <!-- Next Episode -->
            <button
                v-if="hasPlaylist"
                @click="emit('next')"
                :disabled="!hasNext"
                class="rounded-xl p-2 text-white/80 transition-all hover:bg-white/10 hover:text-white active:scale-95 disabled:opacity-30 disabled:hover:bg-transparent"
                :title="t('player.next_episode') || 'Next'"
            >
                <SkipForward class="h-4 w-4 sm:h-5 sm:w-5" />
            </button>

            <!-- Volume Control Group -->
            <div class="group/volume hidden items-center gap-2 pl-1 sm:flex">
                <button
                    @click="emit('toggle-mute')"
                    class="rounded-xl p-2 text-white/80 transition-all hover:bg-white/10 hover:text-white"
                    :title="isMuted ? 'Unmute' : 'Mute'"
                >
                    <VolumeX
                        v-if="isMuted || volume === 0"
                        class="h-5 w-5 text-red-400"
                    />
                    <Volume1 v-else-if="volume < 0.5" class="h-5 w-5" />
                    <Volume2 v-else class="h-5 w-5" />
                </button>
                <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.05"
                    :value="isMuted ? 0 : volume"
                    @input="handleVolumeInput"
                    class="h-1.5 w-16 cursor-pointer rounded-full bg-white/20 accent-cyan-400 opacity-70 transition-opacity group-hover/volume:opacity-100 md:w-24"
                />
            </div>

            <!-- Time Display -->
            <div
                class="pl-1 font-mono text-xs font-semibold text-white/90 select-none sm:text-sm"
            >
                <span>{{ formatTime(currentTime) }}</span>
                <span class="mx-1 text-white/40">/</span>
                <span class="text-white/60">{{ formatTime(duration) }}</span>
            </div>
        </div>

        <!-- Right: Action Buttons (Subtitles, EQ, Playlist, HUD, Fullscreen) -->
        <div class="flex items-center gap-1 sm:gap-2">
            <!-- Subtitles Button -->
            <button
                @click="emit('toggle-subtitles')"
                class="relative rounded-xl p-2 text-white/80 transition-all hover:bg-white/10 hover:text-white active:scale-95 sm:p-2.5"
                :title="t('player.subtitles') || 'Subtitles'"
            >
                <MessageSquare class="h-4 w-4 sm:h-5 sm:w-5" />
                <span
                    v-if="hasSubtitles"
                    class="absolute top-1.5 right-1.5 h-1.5 w-1.5 rounded-full bg-cyan-400 shadow-sm"
                />
            </button>

            <!-- Equalizer Button -->
            <button
                @click="emit('toggle-equalizer')"
                class="rounded-xl p-2 text-white/80 transition-all hover:bg-white/10 hover:text-white active:scale-95 sm:p-2.5"
                :title="t('player.audio_enhancements') || 'Audio Equalizer'"
            >
                <Sliders class="h-4 w-4 sm:h-5 sm:w-5" />
            </button>

            <!-- Playlist Drawer Button -->
            <button
                v-if="hasPlaylist"
                @click="emit('toggle-playlist')"
                class="rounded-xl p-2 text-white/80 transition-all hover:bg-white/10 hover:text-white active:scale-95 sm:p-2.5"
                :title="t('player.episodes_list') || 'Playlist'"
            >
                <Layers class="h-4 w-4 sm:h-5 sm:w-5" />
            </button>

            <!-- Diagnostics HUD Button -->
            <button
                @click="emit('toggle-diagnostics')"
                class="hidden rounded-xl p-2 text-white/80 transition-all hover:bg-white/10 hover:text-white active:scale-95 sm:p-2.5 md:flex"
                :title="t('player.stream_diagnostics') || 'Diagnostics HUD'"
            >
                <Activity class="h-4 w-4 sm:h-5 sm:w-5" />
            </button>

            <!-- Playback Speed Menu Button -->
            <div class="relative">
                <button
                    @click="showSpeedMenu = !showSpeedMenu"
                    class="flex items-center gap-1 rounded-xl px-2 py-1.5 font-mono text-xs font-bold transition-all active:scale-95 sm:px-2.5"
                    :class="
                        playbackRate !== 1.0
                            ? 'border border-cyan-500/40 bg-cyan-500/20 text-cyan-300'
                            : 'text-white/80 hover:bg-white/10 hover:text-white'
                    "
                    :title="t('player.playback_speed') || 'Playback Speed'"
                >
                    <span>{{ playbackRate }}x</span>
                </button>

                <!-- Speed Menu Dropdown -->
                <transition name="fade">
                    <div
                        v-if="showSpeedMenu"
                        class="absolute right-0 bottom-12 z-50 flex w-28 flex-col gap-0.5 rounded-2xl border border-white/10 bg-slate-950/95 p-1 shadow-2xl backdrop-blur-xl"
                    >
                        <button
                            v-for="rate in [0.5, 0.75, 1.0, 1.25, 1.5, 2.0]"
                            :key="rate"
                            @click="
                                emit('set-speed', rate);
                                showSpeedMenu = false;
                            "
                            class="flex items-center justify-between rounded-xl px-3 py-1.5 font-mono text-xs font-bold transition-all"
                            :class="
                                playbackRate === rate
                                    ? 'bg-cyan-500 font-black text-slate-950'
                                    : 'text-slate-300 hover:bg-white/10 hover:text-white'
                            "
                        >
                            <span>{{ rate }}x</span>
                            <Check
                                v-if="playbackRate === rate"
                                class="h-3.5 w-3.5"
                            />
                        </button>
                    </div>
                </transition>
            </div>

            <!-- Fullscreen Button -->
            <button
                @click="emit('toggle-fullscreen')"
                class="rounded-xl p-2 text-white/80 transition-all hover:bg-white/10 hover:text-white active:scale-95 sm:p-2.5"
                :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'"
            >
                <Minimize2 v-if="isFullscreen" class="h-4 w-4 sm:h-5 sm:w-5" />
                <Maximize2 v-else class="h-4 w-4 sm:h-5 sm:w-5" />
            </button>
        </div>
    </div>
</template>
