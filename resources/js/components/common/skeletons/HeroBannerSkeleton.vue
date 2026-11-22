<script setup lang="ts">
import CinemaSkeleton from './CinemaSkeleton.vue';
</script>

<template>
    <div
        class="cf-glass-elevated border-border/70 relative flex h-[380px] w-full flex-col justify-end overflow-hidden rounded-3xl border p-8 backdrop-blur-md md:h-[480px] md:p-12"
    >
        <CinemaSkeleton
            className="absolute inset-0 w-full h-full"
            rounded="rounded-3xl"
        />
        <div class="relative z-10 max-w-2xl space-y-4">
            <CinemaSkeleton className="w-28 h-6" rounded="rounded-full" />
            <CinemaSkeleton className="w-3/4 h-10 md:h-12" />
            <CinemaSkeleton className="w-full h-4" />
            <CinemaSkeleton className="w-4/5 h-4" />
            <div class="flex items-center gap-4 pt-4">
                <CinemaSkeleton className="w-36 h-12" rounded="rounded-xl" />
                <CinemaSkeleton className="w-36 h-12" rounded="rounded-xl" />
            </div>
        </div>
    </div>
</template>
