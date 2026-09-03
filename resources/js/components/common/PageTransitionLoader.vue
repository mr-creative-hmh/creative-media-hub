<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { Sparkles } from 'lucide-vue-next';

const { isRTL } = useI18n();

const isNavigating = ref(false);
let startTimer: any = null;
let removeStartListener: (() => void) | null = null;
let removeFinishListener: (() => void) | null = null;
let removeErrorListener: (() => void) | null = null;

onMounted(() => {
    removeStartListener = router.on('start', () => {
        // Debounce slightly to prevent micro-flashes on instant local cache visits
        startTimer = setTimeout(() => {
            isNavigating.value = true;
        }, 80);
    });

    removeFinishListener = router.on('finish', () => {
        if (startTimer) {
            clearTimeout(startTimer);
            startTimer = null;
        }
        isNavigating.value = false;
    });

    removeErrorListener = router.on('httpException' as any, () => {
        if (startTimer) {
            clearTimeout(startTimer);
            startTimer = null;
        }
        isNavigating.value = false;
    });
});

onBeforeUnmount(() => {
    if (removeStartListener) removeStartListener();
    if (removeFinishListener) removeFinishListener();
    if (removeErrorListener) removeErrorListener();
    if (startTimer) clearTimeout(startTimer);
});
</script>

<template>
    <transition name="cinema-island">
        <div
            v-if="isNavigating"
            class="fixed top-4 left-1/2 -translate-x-1/2 z-[99999] pointer-events-none select-none flex items-center"
            :dir="isRTL ? 'rtl' : 'ltr'"
        >
            <!-- Ambient Glow -->
            <div class="absolute -inset-1 bg-gradient-to-r from-cyan-500/30 via-indigo-500/30 to-purple-500/30 rounded-full blur-md animate-pulse"></div>

            <!-- Island Capsule -->
            <div class="relative px-3.5 py-1.5 rounded-full bg-slate-950/90 border border-cyan-500/40 backdrop-blur-2xl shadow-[0_8px_32px_rgba(6,182,212,0.3)] flex items-center gap-2.5">
                <!-- Mini Holographic Orbit Spinner -->
                <div class="relative w-4 h-4 flex items-center justify-center shrink-0">
                    <!-- Outer Orbit Track -->
                    <div class="absolute inset-0 rounded-full border border-white/10"></div>
                    <!-- Cyan Spinning Comet -->
                    <div class="absolute inset-0 rounded-full border-1.5 border-transparent border-t-cyan-400 border-r-cyan-300 animate-spin"></div>
                    <!-- Violet Counter-Spinning Comet -->
                    <div class="absolute inset-0.5 rounded-full border border-transparent border-b-purple-400 animate-spin-reverse"></div>
                    <!-- Core Glowing Dot -->
                    <div class="w-1 h-1 rounded-full bg-cyan-300 shadow-[0_0_6px_#22d3ee] animate-pulse"></div>
                </div>

                <!-- Localized Status Label -->
                <span class="text-xs font-bold text-slate-100 font-sans tracking-wide">
                    {{ isRTL ? 'جاري الانتقال والتحديث...' : 'Loading Page...' }}
                </span>

                <!-- Animated Equalizer Bars -->
                <div class="flex items-center gap-0.5 h-3 px-0.5">
                    <span class="w-0.5 bg-cyan-400 rounded-full animate-bar-1"></span>
                    <span class="w-0.5 bg-purple-400 rounded-full animate-bar-2"></span>
                    <span class="w-0.5 bg-cyan-300 rounded-full animate-bar-3"></span>
                </div>

                <!-- Subtle Sparkle Icon -->
                <Sparkles class="w-3 h-3 text-cyan-300/80 animate-pulse shrink-0" />
            </div>
        </div>
    </transition>
</template>

<style scoped>
@keyframes spin-reverse {
    from {
        transform: rotate(360deg);
    }
    to {
        transform: rotate(0deg);
    }
}

@keyframes bar-pulse-1 {
    0%, 100% { height: 3px; opacity: 0.4; }
    50% { height: 11px; opacity: 1; }
}

@keyframes bar-pulse-2 {
    0%, 100% { height: 9px; opacity: 0.9; }
    50% { height: 4px; opacity: 0.5; }
}

@keyframes bar-pulse-3 {
    0%, 100% { height: 5px; opacity: 0.5; }
    50% { height: 11px; opacity: 1; }
}

.animate-spin-reverse {
    animation: spin-reverse 1.2s linear infinite;
}

.animate-bar-1 {
    animation: bar-pulse-1 0.7s ease-in-out infinite;
}
.animate-bar-2 {
    animation: bar-pulse-2 0.6s ease-in-out infinite 0.1s;
}
.animate-bar-3 {
    animation: bar-pulse-3 0.8s ease-in-out infinite 0.2s;
}

.cinema-island-enter-active {
    transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

.cinema-island-leave-active {
    transition: all 0.2s cubic-bezier(0.4, 0, 1, 1);
}

.cinema-island-enter-from {
    opacity: 0;
    transform: translate(-50%, -14px) scale(0.92);
}

.cinema-island-leave-to {
    opacity: 0;
    transform: translate(-50%, -8px) scale(0.95);
}
</style>
