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
        class="absolute inset-x-0 flex items-center justify-center px-4 sm:px-8 pointer-events-none transition-all duration-200 ease-out z-20"
        :style="{ bottom: isControlsVisible ? '6.5rem' : '2.5rem' }"
    >
        <div
            class="px-4 py-2 sm:px-6 sm:py-2.5 rounded-2xl bg-black/60 text-white text-center font-bold tracking-normal shadow-2xl backdrop-blur-md pointer-events-none border border-white/10 max-w-4xl transition-all duration-150"
            dir="auto"
            :class="fontSizeClasses"
            :style="{
                fontFamily: fontFamily,
                textShadow: '0 2px 4px rgba(0,0,0,0.9), 0 0 2px rgba(0,0,0,0.8)',
            }"
            v-html="activeCueText"
        />
    </div>
</template>

