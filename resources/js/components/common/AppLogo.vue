<script setup lang="ts">
import { useI18n } from '@/i18n/useI18n';

withDefaults(
    defineProps<{
        size?: 'sm' | 'md' | 'lg' | 'xl';
        showText?: boolean;
        animated?: boolean;
    }>(),
    {
        size: 'md',
        showText: true,
        animated: true,
    }
);

const { t } = useI18n();
</script>

<template>
    <div class="inline-flex items-center gap-3 group select-none cursor-pointer">
        <!-- CMH Cinema Marquee Vector Mark (Creative Media Hub) -->
        <div
            class="relative shrink-0 flex items-center justify-center transition-all duration-300"
            :class="[
                size === 'sm'
                    ? 'w-8 h-8'
                    : size === 'lg'
                      ? 'w-12 h-12'
                      : size === 'xl'
                        ? 'w-16 h-16'
                        : 'w-10 h-10',
                animated ? 'group-hover:scale-105 group-hover:drop-shadow-[0_0_18px_var(--accent-glow)]' : '',
            ]"
        >
            <svg
                viewBox="0 0 100 100"
                class="w-full h-full"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <defs>
                    <!-- Dynamic Accent Gradients -->
                    <linearGradient id="cmh-border-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="var(--accent-primary, #06B6D4)" stop-opacity="0.9" />
                        <stop offset="50%" stop-color="rgba(255, 255, 255, 0.25)" />
                        <stop offset="100%" stop-color="var(--accent-secondary, #3B82F6)" stop-opacity="0.7" />
                    </linearGradient>

                    <linearGradient id="cmh-timeline-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="var(--accent-primary, #06B6D4)" />
                        <stop offset="100%" stop-color="var(--accent-secondary, #3B82F6)" />
                    </linearGradient>

                    <linearGradient id="cmh-play-core-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#FFFFFF" />
                        <stop offset="100%" stop-color="var(--accent-primary, #06B6D4)" />
                    </linearGradient>

                    <radialGradient id="cmh-ambient-glow" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="var(--accent-primary, #06B6D4)" stop-opacity="0.45" />
                        <stop offset="100%" stop-color="transparent" stop-opacity="0" />
                    </radialGradient>
                </defs>

                <!-- 1. Ambient Glow Backplate (Breathing Aura) -->
                <circle
                    cx="50"
                    cy="50"
                    r="44"
                    fill="url(#cmh-ambient-glow)"
                    :class="animated ? 'cmh-ambient-glow' : ''"
                />

                <!-- 2. Dark Cinema Squircle Shield -->
                <rect
                    x="5"
                    y="5"
                    width="90"
                    height="90"
                    rx="22"
                    fill="#080C16"
                    stroke="url(#cmh-border-grad)"
                    stroke-width="2.5"
                    class="transition-all duration-300 group-hover:stroke-[2.8px]"
                />

                <!-- 3. Top Cinema Film Strip / Clapper Perforations -->
                <rect x="14" y="12" width="72" height="8" rx="2" fill="rgba(255, 255, 255, 0.06)" />
                <rect x="18" y="13.5" width="6" height="5" rx="1" fill="#080C16" stroke="var(--accent-primary, #06B6D4)" stroke-width="0.75" />
                <rect x="37" y="13.5" width="6" height="5" rx="1" fill="#080C16" stroke="var(--accent-primary, #06B6D4)" stroke-width="0.75" />
                <rect x="57" y="13.5" width="6" height="5" rx="1" fill="#080C16" stroke="var(--accent-primary, #06B6D4)" stroke-width="0.75" />
                <rect x="76" y="13.5" width="6" height="5" rx="1" fill="#080C16" stroke="var(--accent-primary, #06B6D4)" stroke-width="0.75" />

                <!-- 4. Bold Letter C (Creative) - Cinema Curve in Brilliant White -->
                <path
                    d="M 33 26 L 23 26 C 16.5 26 13 30.5 13 37 L 13 63 C 13 69.5 16.5 74 23 74 L 33 74 L 33 65 L 24 65 C 22 65 21 63.5 21 61 L 21 39 C 21 36.5 22 35 24 35 L 33 35 Z"
                    fill="#FFFFFF"
                />

                <!-- 5. Bold Letter M (Media) - Cinema Play Core -->
                <path d="M 37 26 L 44 26 L 44 74 L 37 74 Z" fill="#FFFFFF" />
                <path d="M 56 26 L 63 26 L 63 74 L 56 74 Z" fill="#FFFFFF" />
                <path d="M 44 26 L 50 38 L 56 26 L 56 34 L 50 45 L 44 34 Z" fill="#FFFFFF" />
                <!-- Embedded Cinema Play Arrow (▶) in Media Core -->
                <path
                    d="M 46 47 C 46 45.8 47.4 45.1 48.4 45.8 L 56.5 52.8 C 57.5 53.6 57.5 55.2 56.5 56.0 L 48.4 63.0 C 47.4 63.7 46 63.0 46 61.8 Z"
                    fill="url(#cmh-play-core-grad)"
                    class="transition-transform duration-300"
                    :class="animated ? 'cmh-play-core' : ''"
                />

                <!-- 6. Bold Letter H (Hub) - Interconnected Signal Crossbar -->
                <path d="M 67 26 L 74 26 L 74 74 L 67 74 Z" fill="#FFFFFF" />
                <path d="M 80 26 L 87 26 L 87 74 L 80 74 Z" fill="#FFFFFF" />
                <rect x="74" y="46.5" width="6" height="7" fill="#FFFFFF" />
                <!-- Hub Nexus Node & Radiant Ring -->
                <circle
                    cx="77"
                    cy="50"
                    r="5"
                    fill="none"
                    stroke="var(--accent-primary, #06B6D4)"
                    stroke-width="1.8"
                    :class="animated ? 'cmh-hub-ring' : ''"
                />
                <circle cx="77" cy="50" r="2.2" fill="var(--accent-primary, #06B6D4)" />

                <!-- 7. Bottom Cinema Streaming Timeline -->
                <rect x="14" y="81" width="72" height="3" rx="1.5" fill="rgba(255, 255, 255, 0.12)" />
                <rect
                    x="14"
                    y="81"
                    width="48"
                    height="3"
                    rx="1.5"
                    fill="url(#cmh-timeline-grad)"
                    :class="animated ? 'cmh-stream-bar' : ''"
                />
                <circle
                    cx="62"
                    cy="82.5"
                    r="2.5"
                    fill="#FFFFFF"
                    :class="animated ? 'cmh-playhead-dot' : ''"
                />
            </svg>
        </div>

        <!-- Typography Branding using centralized translations -->
        <div v-if="showText" class="flex flex-col select-none leading-none">
            <span
                class="font-black tracking-tight text-white whitespace-nowrap inline-flex items-center gap-1.5"
                :class="[
                    size === 'sm'
                        ? 'text-sm'
                        : size === 'lg'
                          ? 'text-xl'
                          : size === 'xl'
                            ? 'text-2xl'
                            : 'text-base',
                ]"
            >
                <span>{{ t('app_name_first') }}</span>
                <span
                    class="font-black"
                    :class="animated ? 'cmh-brand-gradient' : 'text-transparent bg-clip-text bg-gradient-to-r from-accent-400 via-accent-300 to-accent-500'"
                >
                    {{ t('app_name_second') }}
                </span>
            </span>
            <span
                class="text-zinc-400 font-semibold tracking-wider uppercase mt-1 leading-none truncate max-w-[280px]"
                :class="size === 'sm' ? 'text-[9px]' : 'text-[10px]'"
            >
                {{ t('app_tagline') }}
            </span>
        </div>
    </div>
</template>

<style scoped>
@keyframes cmh-breathe {
    0%, 100% {
        opacity: 0.35;
        transform: scale(0.96);
    }
    50% {
        opacity: 0.75;
        transform: scale(1.05);
    }
}

@keyframes cmh-play-pulse {
    0%, 100% {
        filter: drop-shadow(0 0 2px var(--accent-primary, #06B6D4));
        transform: scale(1);
    }
    50% {
        filter: drop-shadow(0 0 7px var(--accent-primary, #06B6D4));
        transform: scale(1.06);
    }
}

@keyframes cmh-hub-beacon {
    0%, 100% {
        r: 4.8px;
        opacity: 0.45;
    }
    50% {
        r: 6.2px;
        opacity: 1;
    }
}

@keyframes cmh-timeline-stream {
    0%, 100% {
        width: 48px;
    }
    50% {
        width: 54px;
    }
}

@keyframes cmh-playhead-glide {
    0%, 100% {
        transform: translateX(0px);
    }
    50% {
        transform: translateX(6px);
    }
}

@keyframes cmh-grad-shift {
    0% {
        background-position: 0% center;
    }
    50% {
        background-position: 100% center;
    }
    100% {
        background-position: 0% center;
    }
}

.cmh-ambient-glow {
    transform-origin: 50px 50px;
    animation: cmh-breathe 4s ease-in-out infinite;
}

.cmh-play-core {
    transform-origin: 50px 55px;
    animation: cmh-play-pulse 3s ease-in-out infinite;
}

.group:hover .cmh-play-core {
    transform: scale(1.12);
    filter: drop-shadow(0 0 10px var(--accent-primary, #06B6D4));
}

.cmh-hub-ring {
    animation: cmh-hub-beacon 2.4s ease-in-out infinite;
}

.cmh-stream-bar {
    animation: cmh-timeline-stream 4s ease-in-out infinite;
}

.cmh-playhead-dot {
    animation: cmh-playhead-glide 4s ease-in-out infinite;
}

.cmh-brand-gradient {
    background-image: linear-gradient(110deg, var(--accent-400, #38BDF8) 0%, var(--accent-300, #67E8F9) 35%, var(--accent-500, #06B6D4) 70%, var(--accent-400, #38BDF8) 100%);
    background-size: 200% auto;
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: cmh-grad-shift 6s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .cmh-ambient-glow,
    .cmh-play-core,
    .cmh-hub-ring,
    .cmh-stream-bar,
    .cmh-playhead-dot,
    .cmh-brand-gradient {
        animation: none !important;
    }
}
</style>
