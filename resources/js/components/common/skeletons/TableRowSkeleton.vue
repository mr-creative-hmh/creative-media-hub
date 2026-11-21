<script setup lang="ts">
import CinemaSkeleton from './CinemaSkeleton.vue';

withDefaults(
    defineProps<{
        rows?: number;
        columns?: number;
    }>(),
    {
        rows: 5,
        columns: 6,
    },
);
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="r in rows"
            :key="r"
            class="flex items-center gap-4 rounded-xl border border-white/[0.04] bg-white/[0.02] p-4"
        >
            <div class="w-6 shrink-0">
                <CinemaSkeleton className="w-5 h-5" rounded="rounded" />
            </div>
            <div class="h-16 w-12 shrink-0">
                <CinemaSkeleton
                    className="w-full h-full"
                    rounded="rounded-md"
                />
            </div>
            <div class="flex-1 space-y-2">
                <CinemaSkeleton className="w-1/3 h-4" />
                <CinemaSkeleton className="w-2/3 h-3" />
            </div>
            <div class="hidden w-28 shrink-0 md:block">
                <CinemaSkeleton className="w-full h-6" rounded="rounded-full" />
            </div>
            <div class="hidden w-24 shrink-0 sm:block">
                <CinemaSkeleton className="w-full h-4" />
            </div>
            <div class="w-20 shrink-0">
                <CinemaSkeleton className="w-full h-8" rounded="rounded-lg" />
            </div>
        </div>
    </div>
</template>
