<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { Sparkles } from 'lucide-vue-next';

const { isRTL } = useI18n();

const isNavigating = ref(false);
let startTimer: ReturnType<typeof setTimeout> | null = null;
let removeStartListener: (() => void) | null = null;
let removeFinishListener: (() => void) | null = null;
let removeHttpErrorListener: (() => void) | null = null;
let removeNetworkErrorListener: (() => void) | null = null;

const stopLoading = () => {
    if (startTimer !== null) {
        clearTimeout(startTimer);
        startTimer = null;
    }
    isNavigating.value = false;
};

onMounted(() => {
    removeStartListener = router.on('start', () => {
        if (startTimer !== null) {
            clearTimeout(startTimer);
            startTimer = null;
        }
        // Debounce slightly to prevent micro-flashes on instant local cache visits
        startTimer = setTimeout(() => {
            startTimer = null;
            isNavigating.value = true;
        }, 80);
    });

    removeFinishListener = router.on('finish', stopLoading);
    removeHttpErrorListener = router.on('httpException', stopLoading);
    removeNetworkErrorListener = router.on('networkError', stopLoading);
});

onBeforeUnmount(() => {
    if (removeStartListener) removeStartListener();
    if (removeFinishListener) removeFinishListener();
    if (removeHttpErrorListener) removeHttpErrorListener();
    if (removeNetworkErrorListener) removeNetworkErrorListener();
    stopLoading();
});
</script>

<template>
    <transition name="cinema-island">
        <div
            v-if="isNavigating"
            class="pointer-events-none fixed top-4 left-1/2 z-[99999] flex -translate-x-1/2 items-center select-none"
            :dir="isRTL ? 'rtl' : 'ltr'"
        >
            <!-- Ambient Glow -->
            <div
                class="absolute -inset-1 animate-pulse rounded-full bg-gradient-to-r from-cyan-500/30 via-indigo-500/30 to-purple-500/30 blur-md"
            ></div>

            <!-- Island Capsule -->
            <div
                class="relative flex items-center gap-2.5 rounded-full border border-cyan-500/40 bg-slate-950/90 px-3.5 py-1.5 shadow-[0_8px_32px_rgba(6,182,212,0.3)] backdrop-blur-2xl"
            >
                <!-- Mini Holographic Orbit Spinner -->
                <div
                    class="relative flex h-4 w-4 shrink-0 items-center justify-center"
                >
                    <!-- Outer Orbit Track -->
                    <div
                        class="absolute inset-0 rounded-full border border-white/10"
                    ></div>
                    <!-- Cyan Spinning Comet -->
                    <div
                        class="border-1.5 absolute inset-0 animate-spin rounded-full border-transparent border-t-cyan-400 border-r-cyan-300"
                    ></div>
                    <!-- Violet Counter-Spinning Comet -->
                    <div
                        class="animate-spin-reverse absolute inset-0.5 rounded-full border border-transparent border-b-purple-400"
                    ></div>
                    <!-- Core Glowing Dot -->
                    <div
                        class="h-1 w-1 animate-pulse rounded-full bg-cyan-300 shadow-[0_0_6px_#22d3ee]"
                    ></div>
                </div>

                <!-- Localized Status Label -->
                <span
                    class="font-sans text-xs font-bold tracking-wide text-slate-100"
                >
                    {{
                        isRTL ? 'جاري الانتقال والتحديث...' : 'Loading Page...'
                    }}
                </span>

                <!-- Animated Equalizer Bars -->
                <div class="flex h-3 items-center gap-0.5 px-0.5">
                    <span
                        class="animate-bar-1 w-0.5 rounded-full bg-cyan-400"
                    ></span>
                    <span
                        class="animate-bar-2 w-0.5 rounded-full bg-purple-400"
                    ></span>
                    <span
                        class="animate-bar-3 w-0.5 rounded-full bg-cyan-300"
                    ></span>
                </div>

                <!-- Subtle Sparkle Icon -->
                <Sparkles
                    class="h-3 w-3 shrink-0 animate-pulse text-cyan-300/80"
                />
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
    0%,
    100% {
        height: 3px;
        opacity: 0.4;
    }
    50% {
        height: 11px;
        opacity: 1;
    }
}

@keyframes bar-pulse-2 {
    0%,
    100% {
        height: 9px;
        opacity: 0.9;
    }
    50% {
        height: 4px;
        opacity: 0.5;
    }
}

@keyframes bar-pulse-3 {
    0%,
    100% {
        height: 5px;
        opacity: 0.5;
    }
    50% {
        height: 11px;
        opacity: 1;
    }
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
