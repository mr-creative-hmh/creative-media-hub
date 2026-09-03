<script setup lang="ts">
import { computed } from 'vue';
import { Film, Sparkles } from 'lucide-vue-next';

interface Props {
    statusText?: string;
    subText?: string;
    size?: 'sm' | 'md' | 'lg';
    showStatus?: boolean;
    isRTL?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
    showStatus: true,
    isRTL: false,
});

const defaultTitle = computed(() => {
    if (props.statusText) return props.statusText;
    return props.isRTL ? 'جاري ضبط وتدفق البث...' : 'Buffering Cinema Stream...';
});
</script>

<template>
    <div class="cinema-loader-wrapper flex flex-col items-center justify-center gap-4 select-none pointer-events-none" :dir="isRTL ? 'rtl' : 'ltr'">
        <!-- Ambient Glow Aura behind the loader -->
        <div class="relative flex items-center justify-center">
            <div class="absolute -inset-4 bg-gradient-to-r from-cyan-500/20 via-purple-500/20 to-blue-500/20 rounded-full blur-xl animate-pulse"></div>

            <!-- Outer Orbital Track & Comet Ring -->
            <div
                class="relative flex items-center justify-center"
                :class="{
                    'w-16 h-16': size === 'sm',
                    'w-24 h-24': size === 'md',
                    'w-32 h-32': size === 'lg',
                }"
            >
                <!-- Outer Gradient Orbit Track -->
                <div class="absolute inset-0 rounded-full border border-white/10 shadow-[inset_0_0_12px_rgba(255,255,255,0.05)]"></div>

                <!-- Primary Rotating Cyan Comet Ring -->
                <div class="absolute inset-0 rounded-full border-2 border-transparent border-t-cyan-400 border-r-cyan-500/50 animate-spin-glow"></div>

                <!-- Secondary Counter-Rotating Purple Accent Ring -->
                <div class="absolute inset-1.5 rounded-full border-2 border-transparent border-b-purple-400 border-l-fuchsia-500/40 animate-spin-reverse-glow"></div>

                <!-- Inner Ambient Pulse Core with Film Icon -->
                <div
                    class="relative flex items-center justify-center rounded-full bg-slate-950/90 border border-white/15 shadow-2xl backdrop-blur-md"
                    :class="{
                        'w-8 h-8': size === 'sm',
                        'w-12 h-12': size === 'md',
                        'w-16 h-16': size === 'lg',
                    }"
                >
                    <div class="absolute inset-0 rounded-full bg-gradient-to-tr from-cyan-500/20 to-purple-500/20 animate-core-breath"></div>
                    <Film
                        class="text-cyan-300 relative z-10 animate-pulse"
                        :class="{
                            'w-3.5 h-3.5': size === 'sm',
                            'w-5 h-5': size === 'md',
                            'w-7 h-7': size === 'lg',
                        }"
                    />
                </div>

                <!-- Satellite Orbiting Sparkle Bead -->
                <div class="satellite-orbit absolute inset-0 pointer-events-none">
                    <div class="satellite-bead w-2 h-2 rounded-full bg-cyan-300 shadow-[0_0_8px_#22d3ee]"></div>
                </div>
            </div>
        </div>

        <!-- Glassmorphism Loading Status Card -->
        <transition name="fade">
            <div
                v-if="showStatus"
                class="status-pill px-4 py-2 sm:px-5 sm:py-2.5 rounded-2xl bg-slate-950/85 border border-white/15 backdrop-blur-xl shadow-2xl shadow-cyan-950/50 flex items-center gap-3"
            >
                <!-- Mini Animated Equalizer Soundwaves -->
                <div class="flex items-center gap-0.5 h-3.5 px-0.5" title="Buffering">
                    <span class="eq-bar w-0.5 bg-cyan-400 rounded-full animate-eq-1"></span>
                    <span class="eq-bar w-0.5 bg-purple-400 rounded-full animate-eq-2"></span>
                    <span class="eq-bar w-0.5 bg-cyan-300 rounded-full animate-eq-3"></span>
                    <span class="eq-bar w-0.5 bg-purple-300 rounded-full animate-eq-4"></span>
                </div>

                <!-- Main Status Text & Optional Subtext -->
                <div class="flex flex-col text-center sm:text-start">
                    <span class="text-xs font-black tracking-wide text-slate-100 flex items-center gap-1.5 font-sans">
                        <span>{{ defaultTitle }}</span>
                    </span>
                    <span v-if="subText" class="text-[10px] font-semibold text-slate-400 font-mono">
                        {{ subText }}
                    </span>
                </div>

                <!-- Subtle Ambient Sparkle -->
                <Sparkles class="w-3.5 h-3.5 text-cyan-400/80 animate-pulse hidden sm:block shrink-0" />
            </div>
        </transition>
    </div>
</template>

<style scoped>
@keyframes spin-glow {
    0% {
        transform: rotate(0deg);
        filter: drop-shadow(0 0 4px rgba(34, 211, 238, 0.4));
    }
    50% {
        filter: drop-shadow(0 0 10px rgba(34, 211, 238, 0.8));
    }
    100% {
        transform: rotate(360deg);
        filter: drop-shadow(0 0 4px rgba(34, 211, 238, 0.4));
    }
}

@keyframes spin-reverse-glow {
    0% {
        transform: rotate(360deg);
        filter: drop-shadow(0 0 3px rgba(192, 132, 252, 0.3));
    }
    50% {
        filter: drop-shadow(0 0 8px rgba(192, 132, 252, 0.7));
    }
    100% {
        transform: rotate(0deg);
        filter: drop-shadow(0 0 3px rgba(192, 132, 252, 0.3));
    }
}

@keyframes core-breath {
    0%, 100% {
        opacity: 0.4;
        transform: scale(0.9);
    }
    50% {
        opacity: 0.9;
        transform: scale(1.1);
    }
}

@keyframes satellite-spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

@keyframes eq-wave-1 {
    0%, 100% { height: 4px; opacity: 0.4; }
    50% { height: 14px; opacity: 1; }
}

@keyframes eq-wave-2 {
    0%, 100% { height: 12px; opacity: 0.9; }
    50% { height: 5px; opacity: 0.5; }
}

@keyframes eq-wave-3 {
    0%, 100% { height: 6px; opacity: 0.6; }
    50% { height: 16px; opacity: 1; }
}

@keyframes eq-wave-4 {
    0%, 100% { height: 14px; opacity: 1; }
    50% { height: 4px; opacity: 0.4; }
}

.animate-spin-glow {
    animation: spin-glow 1.4s cubic-bezier(0.45, 0.05, 0.55, 0.95) infinite;
}

.animate-spin-reverse-glow {
    animation: spin-reverse-glow 2.2s cubic-bezier(0.45, 0.05, 0.55, 0.95) infinite;
}

.animate-core-breath {
    animation: core-breath 2s ease-in-out infinite;
}

.satellite-orbit {
    animation: satellite-spin 2.8s linear infinite;
}

.satellite-bead {
    position: absolute;
    top: -2px;
    left: 50%;
    transform: translateX(-50%);
}

.animate-eq-1 {
    animation: eq-wave-1 0.8s ease-in-out infinite;
}
.animate-eq-2 {
    animation: eq-wave-2 0.7s ease-in-out infinite 0.15s;
}
.animate-eq-3 {
    animation: eq-wave-3 0.9s ease-in-out infinite 0.3s;
}
.animate-eq-4 {
    animation: eq-wave-4 0.75s ease-in-out infinite 0.1s;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease, transform 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: scale(0.95) translateY(4px);
}
</style>
