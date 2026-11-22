<script setup lang="ts">
import CinemaSkeleton from './CinemaSkeleton.vue';

withDefaults(
    defineProps<{
        count?: number;
    }>(),
    {
        count: 1,
    },
);
</script>

<template>
    <div
        v-for="i in count"
        :key="i"
        class="cf-glass group border-border/60 relative flex flex-col overflow-hidden rounded-2xl border p-2.5 backdrop-blur-sm"
    >
        <!-- Poster Aspect 2/3 -->
        <div
            class="relative mb-3 aspect-[2/3] w-full overflow-hidden rounded-xl"
        >
            <CinemaSkeleton className="w-full h-full" rounded="rounded-xl" />
            <!-- Simulated floating badge in top corner -->
            <div class="absolute end-2.5 top-2.5">
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
