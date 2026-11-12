<script setup lang="ts">
import { X, Sliders, Sparkles, Volume2, Moon, Clock, RotateCcw } from 'lucide-vue-next';
import { useI18n } from '@/i18n/useI18n';

const props = defineProps<{
    isOpen: boolean;
    vocalBoost: boolean;
    bassBoost: boolean;
    nightMode: boolean;
    audioDelayMs: number;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'toggle-vocal-boost'): void;
    (e: 'toggle-bass-boost'): void;
    (e: 'toggle-night-mode'): void;
    (e: 'set-audio-delay', ms: number): void;
    (e: 'nudge-audio-delay', deltaMs: number): void;
    (e: 'reset-audio-delay'): void;
}>();

const { t } = useI18n();

const handleSliderInput = (e: Event) => {
    const val = parseInt((e.target as HTMLInputElement).value, 10);
    emit('set-audio-delay', val);
};
</script>

<template>
    <div
        v-if="isOpen"
        class="absolute inset-0 bg-black/60 backdrop-blur-md z-40 flex items-center justify-center p-4 animate-in fade-in duration-200"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-md rounded-3xl bg-slate-900/95 border border-white/10 shadow-2xl overflow-hidden p-6 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-purple-500/20 text-purple-400 flex items-center justify-center border border-purple-500/30">
                        <Sliders class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">
                            {{ t('player.audio_enhancements') || 'Cinema Audio Studio' }}
                        </h3>
                        <p class="text-[11px] text-slate-400">
                            {{ t('player.audio_studio_desc') || 'Real-time Web Audio DSP & synchronization' }}
                        </p>
                    </div>
                </div>
                <button
                    @click="emit('close')"
                    class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 transition-all"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Enhancement Toggles -->
            <div class="space-y-3">
                <!-- Vocal Boost -->
                <button
                    @click="emit('toggle-vocal-boost')"
                    class="w-full p-3.5 rounded-2xl border text-left transition-all flex items-center justify-between"
                    :class="vocalBoost
                        ? 'bg-purple-500/20 text-purple-200 border-purple-500/40 shadow-lg shadow-purple-500/10'
                        : 'bg-white/5 text-slate-300 border-white/5 hover:bg-white/10'"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="vocalBoost ? 'bg-purple-500 text-slate-950 font-bold' : 'bg-white/10 text-slate-400'">
                            <Sparkles class="w-4 h-4" />
                        </div>
                        <div>
                            <div class="text-xs font-bold text-white">
                                {{ t('player.vocal_boost') || 'Vocal Clarity Boost' }}
                            </div>
                            <div class="text-[11px] text-slate-400">
                                {{ t('player.vocal_boost_desc') || 'Enhance dialogue presence @ 2.5kHz (+4.5dB)' }}
                            </div>
                        </div>
                    </div>
                    <div class="w-10 h-5 rounded-full transition-colors relative" :class="vocalBoost ? 'bg-purple-500' : 'bg-white/20'">
                        <div class="w-3.5 h-3.5 rounded-full bg-white absolute top-0.5 transition-transform" :class="vocalBoost ? 'right-1' : 'left-1'" />
                    </div>
                </button>

                <!-- Bass Boost -->
                <button
                    @click="emit('toggle-bass-boost')"
                    class="w-full p-3.5 rounded-2xl border text-left transition-all flex items-center justify-between"
                    :class="bassBoost
                        ? 'bg-cyan-500/20 text-cyan-200 border-cyan-500/40 shadow-lg shadow-cyan-500/10'
                        : 'bg-white/5 text-slate-300 border-white/5 hover:bg-white/10'"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="bassBoost ? 'bg-cyan-500 text-slate-950 font-bold' : 'bg-white/10 text-slate-400'">
                            <Volume2 class="w-4 h-4" />
                        </div>
                        <div>
                            <div class="text-xs font-bold text-white">
                                {{ t('player.bass_boost') || 'Deep Cinema Bass' }}
                            </div>
                            <div class="text-[11px] text-slate-400">
                                {{ t('player.bass_boost_desc') || 'Low-end shelf boost @ 180Hz (+5.0dB)' }}
                            </div>
                        </div>
                    </div>
                    <div class="w-10 h-5 rounded-full transition-colors relative" :class="bassBoost ? 'bg-cyan-500' : 'bg-white/20'">
                        <div class="w-3.5 h-3.5 rounded-full bg-white absolute top-0.5 transition-transform" :class="bassBoost ? 'right-1' : 'left-1'" />
                    </div>
                </button>

                <!-- Night Mode -->
                <button
                    @click="emit('toggle-night-mode')"
                    class="w-full p-3.5 rounded-2xl border text-left transition-all flex items-center justify-between"
                    :class="nightMode
                        ? 'bg-amber-500/20 text-amber-200 border-amber-500/40 shadow-lg shadow-amber-500/10'
                        : 'bg-white/5 text-slate-300 border-white/5 hover:bg-white/10'"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="nightMode ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-white/10 text-slate-400'">
                            <Moon class="w-4 h-4" />
                        </div>
                        <div>
                            <div class="text-xs font-bold text-white">
                                {{ t('player.night_mode') || 'Night Mode (Dynamic Compression)' }}
                            </div>
                            <div class="text-[11px] text-slate-400">
                                {{ t('player.night_mode_desc') || 'Tame loud explosions while keeping whispers audible' }}
                            </div>
                        </div>
                    </div>
                    <div class="w-10 h-5 rounded-full transition-colors relative" :class="nightMode ? 'bg-amber-500' : 'bg-white/20'">
                        <div class="w-3.5 h-3.5 rounded-full bg-white absolute top-0.5 transition-transform" :class="nightMode ? 'right-1' : 'left-1'" />
                    </div>
                </button>
            </div>

            <!-- Audio Delay Calibration -->
            <div class="space-y-3 pt-3 border-t border-white/10">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <Clock class="w-3.5 h-3.5 text-cyan-400" />
                        {{ t('player.audio_sync') || 'Audio Sync Calibration' }}
                    </span>
                    <span class="font-mono font-bold" :class="audioDelayMs === 0 ? 'text-slate-400' : 'text-cyan-400'">
                        {{ audioDelayMs === 0 ? 'In Sync (0ms)' : (audioDelayMs > 0 ? `+${audioDelayMs}ms` : `${audioDelayMs}ms`) }}
                    </span>
                </div>

                <!-- Slider -->
                <input
                    type="range"
                    min="-1000"
                    max="1000"
                    step="25"
                    :value="audioDelayMs"
                    @input="handleSliderInput"
                    class="w-full h-2 rounded-full accent-cyan-400 bg-white/20 cursor-pointer"
                />

                <!-- Quick Nudge Buttons -->
                <div class="flex items-center gap-2">
                    <button
                        @click="emit('nudge-audio-delay', -100)"
                        class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white text-xs font-bold transition-all"
                    >
                        -100ms
                    </button>
                    <button
                        @click="emit('nudge-audio-delay', -25)"
                        class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white text-xs font-bold transition-all"
                    >
                        -25ms
                    </button>
                    <button
                        @click="emit('reset-audio-delay')"
                        class="flex-1 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-slate-400 hover:text-white text-xs font-bold transition-all flex items-center justify-center gap-1"
                    >
                        <RotateCcw class="w-3 h-3" />
                        {{ t('player.reset') || 'Reset' }}
                    </button>
                    <button
                        @click="emit('nudge-audio-delay', 25)"
                        class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white text-xs font-bold transition-all"
                    >
                        +25ms
                    </button>
                    <button
                        @click="emit('nudge-audio-delay', 100)"
                        class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white text-xs font-bold transition-all"
                    >
                        +100ms
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

