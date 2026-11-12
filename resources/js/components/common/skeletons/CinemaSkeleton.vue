<script setup lang="ts">
withDefaults(
    defineProps<{
        className?: string;
        rounded?: string;
    }>(),
    {
        className: 'w-full h-4',
        rounded: 'rounded-lg',
    }
);
</script>

<template>
    <div
        :class="[
            'relative overflow-hidden bg-white/[0.04] border border-white/[0.05]',
            rounded,
            className
        ]"
    >
        <div
            class="absolute inset-0 -translate-x-full animate-[shimmer_2s_infinite] bg-gradient-to-r from-transparent via-white/[0.07] to-transparent"
        />
    </div>
</template>

<style scoped>
@keyframes shimmer {
    100% {
        transform: translateX(100%);
    }
}
</style>

