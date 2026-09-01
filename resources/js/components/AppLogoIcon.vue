<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 512 512"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <defs>
            <linearGradient id="cmh-icon-primary" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#06B6D4" />
                <stop offset="50%" stop-color="#3B82F6" />
                <stop offset="100%" stop-color="#6366F1" />
            </linearGradient>
            <linearGradient id="cmh-icon-accent" x1="0%" y1="100%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="#3B82F6" />
                <stop offset="100%" stop-color="#A855F7" />
            </linearGradient>
            <filter id="cmh-icon-glow" x="-20%" y="-20%" width="140%" height="140%">
                <feGaussianBlur stdDeviation="16" result="blur" />
                <feComposite in="SourceGraphic" in2="blur" operator="over" />
            </filter>
        </defs>

        <!-- Outer Rounded Squircle Base -->
        <rect x="32" y="32" width="448" height="448" rx="112" fill="#07090E" stroke="url(#cmh-icon-primary)" stroke-width="8" />
        <rect x="44" y="44" width="424" height="424" rx="100" fill="url(#cmh-icon-primary)" fill-opacity="0.12" />

        <!-- Stylized "C" Arc / Film Strip Ribbon -->
        <path d="M 330 140 C 240 100, 140 160, 140 256 C 140 352, 240 412, 330 372 C 354 362, 368 338, 358 314 C 348 290, 324 276, 300 286 C 246 310, 204 286, 204 256 C 204 226, 246 202, 300 226 C 324 236, 348 222, 358 198 C 368 174, 354 150, 330 140 Z" fill="url(#cmh-icon-primary)" />

        <!-- Stylized "M" & Play Prism Nexus -->
        <path d="M 270 196 L 380 256 L 270 316 Z" fill="url(#cmh-icon-accent)" />

        <!-- Film Sprocket Elements -->
        <circle cx="152" cy="190" r="14" fill="#06B6D4" />
        <circle cx="132" cy="256" r="16" fill="#3B82F6" />
        <circle cx="152" cy="322" r="14" fill="#6366F1" />

        <!-- Central Sparkle Core -->
        <circle cx="304" cy="256" r="8" fill="#FFFFFF" filter="url(#cmh-icon-glow)" />
    </svg>
</template>
