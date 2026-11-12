<script setup lang="ts">
import CinemaSkeleton from './CinemaSkeleton.vue';

withDefaults(
    defineProps<{
        count?: number;
    }>(),
    {
        count: 1,
    }
);
</script>

<template>
    <div
        v-for="i in count"
        :key="i"
        class="group relative flex flex-col rounded-2xl bg-black/40 border border-white/[0.06] p-2.5 overflow-hidden backdrop-blur-sm"
    >
        <!-- Poster Aspect 2/3 -->
        <div class="relative w-full aspect-[2/3] rounded-xl overflow-hidden mb-3">
            <CinemaSkeleton className="w-full h-full" rounded="rounded-xl" />
            <!-- Simulated floating badge in top corner -->
            <div class="absolute top-2.5 right-2.5">
                <CinemaSkeleton className="w-12 h-5" rounded="rounded-md" />
            </div>
        </div>

        <!-- Meta info -->
        <div class="space-y-2 px-1 pb-1">
            <CinemaSkeleton className="w-4/5 h-4" />
            <div class="flex items-center justify-between pt-1">
                <CinemaSkeleton className="w-1/3 h-3" />
                <CinemaSkeleton className="w-1/4 h-3" />
            </div>
        </div>
    </div>
</template>

