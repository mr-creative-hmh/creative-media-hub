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

const props = withDefaults(defineProps<{
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
}>(), {
    playbackRate: 1.0,
});

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
    <div class="flex items-center justify-between gap-2 sm:gap-4 pointer-events-auto">
        <!-- Left: Play/Pause, Next/Prev, Skip, Volume, Time -->
        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Prev Episode -->
            <button
                v-if="hasPlaylist"
                @click="emit('prev')"
                :disabled="!hasPrevious"
                class="p-2 rounded-xl text-white/80 hover:text-white hover:bg-white/10 disabled:opacity-30 disabled:hover:bg-transparent transition-all active:scale-95"
                :title="t('player.prev_episode') || 'Previous'"
            >
                <SkipBack class="w-4 h-4 sm:w-5 sm:h-5" />
            </button>

            <!-- Seek -10s -->
            <button
                @click="emit('seek-relative', -10)"
                class="p-2 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition-all active:scale-95"
                :title="t('player.seek_back_10') || 'Rewind 10s'"
            >
                <RotateCcw class="w-4 h-4 sm:w-5 sm:h-5" />
            </button>

            <!-- Main Play / Pause -->
            <button
                @click="emit('play-pause')"
                class="p-2.5 sm:p-3 rounded-2xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold shadow-lg shadow-cyan-500/25 transition-all active:scale-90"
                :title="isPlaying ? (t('player.pause') || 'Pause') : (t('player.play') || 'Play')"
            >
                <Pause v-if="isPlaying" class="w-5 h-5 sm:w-6 sm:h-6 fill-current" />
                <Play v-else class="w-5 h-5 sm:w-6 sm:h-6 fill-current translate-x-0.5" />
            </button>

            <!-- Seek +10s -->
            <button
                @click="emit('seek-relative', 10)"
                class="p-2 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition-all active:scale-95"
                :title="t('player.seek_forward_10') || 'Forward 10s'"
            >
                <RotateCw class="w-4 h-4 sm:w-5 sm:h-5" />
            </button>

            <!-- Next Episode -->
            <button
                v-if="hasPlaylist"
                @click="emit('next')"
                :disabled="!hasNext"
                class="p-2 rounded-xl text-white/80 hover:text-white hover:bg-white/10 disabled:opacity-30 disabled:hover:bg-transparent transition-all active:scale-95"
                :title="t('player.next_episode') || 'Next'"
            >
                <SkipForward class="w-4 h-4 sm:w-5 sm:h-5" />
            </button>

            <!-- Volume Control Group -->
            <div class="hidden sm:flex items-center gap-2 group/volume pl-1">
                <button
                    @click="emit('toggle-mute')"
                    class="p-2 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition-all"
                    :title="isMuted ? 'Unmute' : 'Mute'"
                >
                    <VolumeX v-if="isMuted || volume === 0" class="w-5 h-5 text-red-400" />
                    <Volume1 v-else-if="volume < 0.5" class="w-5 h-5" />
                    <Volume2 v-else class="w-5 h-5" />
                </button>
                <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.05"
                    :value="isMuted ? 0 : volume"
                    @input="handleVolumeInput"
                    class="w-16 md:w-24 h-1.5 rounded-full accent-cyan-400 bg-white/20 cursor-pointer opacity-70 group-hover/volume:opacity-100 transition-opacity"
                />
            </div>

            <!-- Time Display -->
            <div class="text-xs sm:text-sm font-mono text-white/90 pl-1 font-semibold select-none">
                <span>{{ formatTime(currentTime) }}</span>
                <span class="text-white/40 mx-1">/</span>
                <span class="text-white/60">{{ formatTime(duration) }}</span>
            </div>
        </div>

        <!-- Right: Action Buttons (Subtitles, EQ, Playlist, HUD, Fullscreen) -->
        <div class="flex items-center gap-1 sm:gap-2">
            <!-- Subtitles Button -->
            <button
                @click="emit('toggle-subtitles')"
                class="p-2 sm:p-2.5 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition-all active:scale-95 relative"
                :title="t('player.subtitles') || 'Subtitles'"
            >
                <MessageSquare class="w-4 h-4 sm:w-5 sm:h-5" />
                <span
                    v-if="hasSubtitles"
                    class="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-cyan-400 shadow-sm"
                />
            </button>

            <!-- Equalizer Button -->
            <button
                @click="emit('toggle-equalizer')"
                class="p-2 sm:p-2.5 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition-all active:scale-95"
                :title="t('player.audio_enhancements') || 'Audio Equalizer'"
            >
                <Sliders class="w-4 h-4 sm:w-5 sm:h-5" />
            </button>

            <!-- Playlist Drawer Button -->
            <button
                v-if="hasPlaylist"
                @click="emit('toggle-playlist')"
                class="p-2 sm:p-2.5 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition-all active:scale-95"
                :title="t('player.episodes_list') || 'Playlist'"
            >
                <Layers class="w-4 h-4 sm:w-5 sm:h-5" />
            </button>

            <!-- Diagnostics HUD Button -->
            <button
                @click="emit('toggle-diagnostics')"
                class="p-2 sm:p-2.5 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition-all active:scale-95 hidden md:flex"
                :title="t('player.stream_diagnostics') || 'Diagnostics HUD'"
            >
                <Activity class="w-4 h-4 sm:w-5 sm:h-5" />
            </button>

            <!-- Playback Speed Menu Button -->
            <div class="relative">
                <button
                    @click="showSpeedMenu = !showSpeedMenu"
                    class="px-2 sm:px-2.5 py-1.5 rounded-xl text-xs font-mono font-bold transition-all flex items-center gap-1 active:scale-95"
                    :class="playbackRate !== 1.0 ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40' : 'text-white/80 hover:text-white hover:bg-white/10'"
                    :title="t('player.playback_speed') || 'Playback Speed'"
                >
                    <span>{{ playbackRate }}x</span>
                </button>

                <!-- Speed Menu Dropdown -->
                <transition name="fade">
                    <div
                        v-if="showSpeedMenu"
                        class="absolute bottom-12 right-0 w-28 bg-slate-950/95 border border-white/10 rounded-2xl p-1 shadow-2xl backdrop-blur-xl z-50 flex flex-col gap-0.5"
                    >
                        <button
                            v-for="rate in [0.5, 0.75, 1.0, 1.25, 1.5, 2.0]"
                            :key="rate"
                            @click="emit('set-speed', rate); showSpeedMenu = false;"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold font-mono flex items-center justify-between transition-all"
                            :class="playbackRate === rate ? 'bg-cyan-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
                        >
                            <span>{{ rate }}x</span>
                            <Check v-if="playbackRate === rate" class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </transition>
            </div>

            <!-- Fullscreen Button -->
            <button
                @click="emit('toggle-fullscreen')"
                class="p-2 sm:p-2.5 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition-all active:scale-95"
                :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'"
            >
                <Minimize2 v-if="isFullscreen" class="w-4 h-4 sm:w-5 sm:h-5" />
                <Maximize2 v-else class="w-4 h-4 sm:w-5 sm:h-5" />
            </button>
        </div>
    </div>
</template>

