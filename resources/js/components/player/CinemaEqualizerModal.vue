<script setup lang="ts">
import {
    X,
    Sliders,
    Sparkles,
    Volume2,
    Moon,
    Clock,
    RotateCcw,
} from 'lucide-vue-next';
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
        class="animate-in fade-in absolute inset-0 z-40 flex items-center justify-center bg-black/60 p-4 backdrop-blur-md duration-200"
        @click.self="emit('close')"
    >
        <div
            class="w-full max-w-md space-y-6 overflow-hidden rounded-3xl border border-white/10 bg-slate-900/95 p-6 shadow-2xl"
        >
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-xl border border-purple-500/30 bg-purple-500/20 text-purple-400"
                    >
                        <Sliders class="h-4 w-4" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">
                            {{
                                t('player.audio_enhancements') ||
                                'Cinema Audio Studio'
                            }}
                        </h3>
                        <p class="text-[11px] text-slate-400">
                            {{
                                t('player.audio_studio_desc') ||
                                'Real-time Web Audio DSP & synchronization'
                            }}
                        </p>
                    </div>
                </div>
                <button
                    @click="emit('close')"
                    class="rounded-xl p-2 text-slate-400 transition-all hover:bg-white/10 hover:text-white"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Enhancement Toggles -->
            <div class="space-y-3">
                <!-- Vocal Boost -->
                <button
                    @click="emit('toggle-vocal-boost')"
                    class="flex w-full items-center justify-between rounded-2xl border p-3.5 text-left transition-all"
                    :class="
                        vocalBoost
                            ? 'border-purple-500/40 bg-purple-500/20 text-purple-200 shadow-lg shadow-purple-500/10'
                            : 'border-white/5 bg-white/5 text-slate-300 hover:bg-white/10'
                    "
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-xl"
                            :class="
                                vocalBoost
                                    ? 'bg-purple-500 font-bold text-slate-950'
                                    : 'bg-white/10 text-slate-400'
                            "
                        >
                            <Sparkles class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="text-xs font-bold text-white">
                                {{
                                    t('player.vocal_boost') ||
                                    'Vocal Clarity Boost'
                                }}
                            </div>
                            <div class="text-[11px] text-slate-400">
                                {{
                                    t('player.vocal_boost_desc') ||
                                    'Enhance dialogue presence @ 2.5kHz (+4.5dB)'
                                }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="relative h-5 w-10 rounded-full transition-colors"
                        :class="vocalBoost ? 'bg-purple-500' : 'bg-white/20'"
                    >
                        <div
                            class="absolute top-0.5 h-3.5 w-3.5 rounded-full bg-white transition-transform"
                            :class="vocalBoost ? 'right-1' : 'left-1'"
                        />
                    </div>
                </button>

                <!-- Bass Boost -->
                <button
                    @click="emit('toggle-bass-boost')"
                    class="flex w-full items-center justify-between rounded-2xl border p-3.5 text-left transition-all"
                    :class="
                        bassBoost
                            ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-200 shadow-lg shadow-cyan-500/10'
                            : 'border-white/5 bg-white/5 text-slate-300 hover:bg-white/10'
                    "
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-xl"
                            :class="
                                bassBoost
                                    ? 'bg-cyan-500 font-bold text-slate-950'
                                    : 'bg-white/10 text-slate-400'
                            "
                        >
                            <Volume2 class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="text-xs font-bold text-white">
                                {{
                                    t('player.bass_boost') || 'Deep Cinema Bass'
                                }}
                            </div>
                            <div class="text-[11px] text-slate-400">
                                {{
                                    t('player.bass_boost_desc') ||
                                    'Low-end shelf boost @ 180Hz (+5.0dB)'
                                }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="relative h-5 w-10 rounded-full transition-colors"
                        :class="bassBoost ? 'bg-cyan-500' : 'bg-white/20'"
                    >
                        <div
                            class="absolute top-0.5 h-3.5 w-3.5 rounded-full bg-white transition-transform"
                            :class="bassBoost ? 'right-1' : 'left-1'"
                        />
                    </div>
                </button>

                <!-- Night Mode -->
                <button
                    @click="emit('toggle-night-mode')"
                    class="flex w-full items-center justify-between rounded-2xl border p-3.5 text-left transition-all"
                    :class="
                        nightMode
                            ? 'border-amber-500/40 bg-amber-500/20 text-amber-200 shadow-lg shadow-amber-500/10'
                            : 'border-white/5 bg-white/5 text-slate-300 hover:bg-white/10'
                    "
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-xl"
                            :class="
                                nightMode
                                    ? 'bg-amber-500 font-bold text-slate-950'
                                    : 'bg-white/10 text-slate-400'
                            "
                        >
                            <Moon class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="text-xs font-bold text-white">
                                {{
                                    t('player.night_mode') ||
                                    'Night Mode (Dynamic Compression)'
                                }}
                            </div>
                            <div class="text-[11px] text-slate-400">
                                {{
                                    t('player.night_mode_desc') ||
                                    'Tame loud explosions while keeping whispers audible'
                                }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="relative h-5 w-10 rounded-full transition-colors"
                        :class="nightMode ? 'bg-amber-500' : 'bg-white/20'"
                    >
                        <div
                            class="absolute top-0.5 h-3.5 w-3.5 rounded-full bg-white transition-transform"
                            :class="nightMode ? 'right-1' : 'left-1'"
                        />
                    </div>
                </button>
            </div>

            <!-- Audio Delay Calibration -->
            <div class="space-y-3 border-t border-white/10 pt-3">
                <div class="flex items-center justify-between text-xs">
                    <span
                        class="flex items-center gap-1.5 font-semibold tracking-wider text-slate-400 uppercase"
                    >
                        <Clock class="h-3.5 w-3.5 text-cyan-400" />
                        {{ t('player.audio_sync') || 'Audio Sync Calibration' }}
                    </span>
                    <span
                        class="font-mono font-bold"
                        :class="
                            audioDelayMs === 0
                                ? 'text-slate-400'
                                : 'text-cyan-400'
                        "
                    >
                        {{
                            audioDelayMs === 0
                                ? 'In Sync (0ms)'
                                : audioDelayMs > 0
                                  ? `+${audioDelayMs}ms`
                                  : `${audioDelayMs}ms`
                        }}
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
                    class="h-2 w-full cursor-pointer rounded-full bg-white/20 accent-cyan-400"
                />

                <!-- Quick Nudge Buttons -->
                <div class="flex items-center gap-2">
                    <button
                        @click="emit('nudge-audio-delay', -100)"
                        class="rounded-xl border border-white/10 bg-white/5 px-2.5 py-1.5 text-xs font-bold text-white transition-all hover:bg-white/10"
                    >
                        -100ms
                    </button>
                    <button
                        @click="emit('nudge-audio-delay', -25)"
                        class="rounded-xl border border-white/10 bg-white/5 px-2.5 py-1.5 text-xs font-bold text-white transition-all hover:bg-white/10"
                    >
                        -25ms
                    </button>
                    <button
                        @click="emit('reset-audio-delay')"
                        class="flex flex-1 items-center justify-center gap-1 rounded-xl border border-white/10 bg-white/5 py-1.5 text-xs font-bold text-slate-400 transition-all hover:bg-white/10 hover:text-white"
                    >
                        <RotateCcw class="h-3 w-3" />
                        {{ t('player.reset') || 'Reset' }}
                    </button>
                    <button
                        @click="emit('nudge-audio-delay', 25)"
                        class="rounded-xl border border-white/10 bg-white/5 px-2.5 py-1.5 text-xs font-bold text-white transition-all hover:bg-white/10"
                    >
                        +25ms
                    </button>
                    <button
                        @click="emit('nudge-audio-delay', 100)"
                        class="rounded-xl border border-white/10 bg-white/5 px-2.5 py-1.5 text-xs font-bold text-white transition-all hover:bg-white/10"
                    >
                        +100ms
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
