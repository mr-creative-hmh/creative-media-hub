<script setup lang="ts">
import { useI18n } from '@/i18n/useI18n';

withDefaults(defineProps<{
    size?: 'sm' | 'md' | 'lg' | 'xl';
    showText?: boolean;
    animated?: boolean;
}>(), {
    size: 'md',
    showText: true,
    animated: true,
});

const { t, isRTL } = useI18n();
</script>

<template>
    <div class="inline-flex items-center gap-3 group select-none">
        <!-- SVG Custom Vector Logo -->
        <div
            class="relative shrink-0 flex items-center justify-center transition-transform duration-300"
            :class="[
                size === 'sm' ? 'w-8 h-8' : size === 'lg' ? 'w-12 h-12' : size === 'xl' ? 'w-16 h-16' : 'w-10 h-10',
                animated ? 'group-hover:scale-105 group-hover:rotate-1' : ''
            ]"
        >
            <svg
                viewBox="0 0 512 512"
                class="w-full h-full drop-shadow-lg"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <defs>
                    <linearGradient id="app-logo-grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#06B6D4" />
                        <stop offset="50%" stop-color="#3B82F6" />
                        <stop offset="100%" stop-color="#6366F1" />
                    </linearGradient>
                    <linearGradient id="app-logo-grad2" x1="0%" y1="100%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#3B82F6" />
                        <stop offset="100%" stop-color="#A855F7" />
                    </linearGradient>
                </defs>

                <!-- Base Squircle -->
                <rect x="32" y="32" width="448" height="448" rx="112" fill="#0A0E18" stroke="url(#app-logo-grad1)" stroke-width="8" />
                <rect x="44" y="44" width="424" height="424" rx="100" fill="url(#app-logo-grad1)" fill-opacity="0.12" />

                <!-- Stylized "C" Arc / Film Ribbon -->
                <path d="M 330 140 C 240 100, 140 160, 140 256 C 140 352, 240 412, 330 372 C 354 362, 368 338, 358 314 C 348 290, 324 276, 300 286 C 246 310, 204 286, 204 256 C 204 226, 246 202, 300 226 C 324 236, 348 222, 358 198 C 368 174, 354 150, 330 140 Z" fill="url(#app-logo-grad1)" />

                <!-- Stylized "M" & Play Prism Nexus -->
                <path d="M 270 196 L 380 256 L 270 316 Z" fill="url(#app-logo-grad2)" />

                <!-- Sprockets -->
                <circle cx="152" cy="190" r="14" fill="#06B6D4" />
                <circle cx="132" cy="256" r="16" fill="#3B82F6" />
                <circle cx="152" cy="322" r="14" fill="#6366F1" />

                <!-- Sparkle -->
                <circle cx="304" cy="256" r="8" fill="#FFFFFF" />
            </svg>
        </div>

        <!-- Typography Branding with unified Arabic font -->
        <div v-if="showText" class="flex flex-col">
            <span
                class="font-black tracking-tight text-white leading-none"
                :class="size === 'sm' ? 'text-sm' : size === 'lg' ? 'text-xl' : size === 'xl' ? 'text-2xl' : 'text-base'"
            >
                {{ t('app_name') }}
            </span>
            <span
                class="text-slate-400 font-bold tracking-wider uppercase mt-1 leading-none"
                :class="size === 'sm' ? 'text-[9px]' : 'text-[10px]'"
            >
                {{ t('app_tagline') }}
            </span>
        </div>
    </div>
</template>
