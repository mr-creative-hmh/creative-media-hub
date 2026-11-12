<script setup lang="ts">
import CinemaSkeleton from './CinemaSkeleton.vue';
</script>

<template>
    <div class="relative w-full h-[380px] md:h-[480px] rounded-3xl overflow-hidden bg-black/50 border border-white/[0.06] p-8 md:p-12 flex flex-col justify-end backdrop-blur-md">
        <CinemaSkeleton className="absolute inset-0 w-full h-full" rounded="rounded-3xl" />
        <div class="relative z-10 space-y-4 max-w-2xl">
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

