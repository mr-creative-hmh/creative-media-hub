<script setup lang="ts">
withDefaults(
    defineProps<{
        className?: string;
        rounded?: string;
    }>(),
    {
        className: 'w-full h-4',
        rounded: 'rounded-lg',
    },
);
</script>

<template>
    <div
        :class="[
            'cf-shimmer border-border/40 bg-muted/55 relative overflow-hidden border',
            rounded,
            className,
        ]"
    />
</template>
