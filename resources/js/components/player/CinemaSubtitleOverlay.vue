<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    activeCueText: string;
    fontFamily: string;
    fontSize: 'sm' | 'md' | 'lg' | 'xl';
    isControlsVisible: boolean;
}>();

const fontSizeClasses = computed(() => {
    switch (props.fontSize) {
        case 'sm':
            return 'text-sm sm:text-base leading-snug';
        case 'lg':
            return 'text-lg sm:text-2xl leading-normal';
        case 'xl':
            return 'text-xl sm:text-3xl leading-relaxed';
        case 'md':
        default:
            return 'text-base sm:text-xl leading-relaxed';
    }
});
</script>

<template>
    <div
        v-if="activeCueText"
        class="pointer-events-none absolute inset-x-0 z-20 flex items-center justify-center px-4 transition-all duration-200 ease-out sm:px-8"
        :style="{ bottom: isControlsVisible ? '6.5rem' : '2.5rem' }"
    >
        <div
            class="pointer-events-none max-w-4xl rounded-2xl border border-white/10 bg-black/60 px-4 py-2 text-center font-bold tracking-normal text-white shadow-2xl backdrop-blur-md transition-all duration-150 sm:px-6 sm:py-2.5"
            dir="auto"
            :class="fontSizeClasses"
            :style="{
                fontFamily: fontFamily,
                textShadow:
                    '0 2px 4px rgba(0,0,0,0.9), 0 0 2px rgba(0,0,0,0.8)',
            }"
            v-html="activeCueText"
        />
    </div>
</template>
