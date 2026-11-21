<script setup lang="ts">
import CinemaSkeleton from './CinemaSkeleton.vue';

withDefaults(
    defineProps<{
        count?: number;
    }>(),
    {
        count: 2,
    },
);
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="i in count"
            :key="i"
            class="relative overflow-hidden rounded-2xl border border-white/[0.06] bg-black/40 p-6 backdrop-blur-md"
        >
            <div class="flex flex-col items-start gap-6 md:flex-row">
                <!-- Collection Poster Skeleton -->
                <div class="aspect-[2/3] w-32 shrink-0 md:w-36">
                    <CinemaSkeleton
                        className="w-full h-full"
                        rounded="rounded-xl"
                    />
                </div>

                <!-- Info & Parts Skeleton -->
                <div class="w-full flex-1 space-y-4">
                    <div class="flex items-center justify-between">
                        <CinemaSkeleton className="w-64 h-6" />
                        <CinemaSkeleton
                            className="w-24 h-6"
                            rounded="rounded-full"
                        />
                    </div>

                    <!-- Progress bar skeleton -->
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <CinemaSkeleton className="w-20 h-3" />
                            <CinemaSkeleton className="w-12 h-3" />
                        </div>
                        <CinemaSkeleton
                            className="w-full h-2"
                            rounded="rounded-full"
                        />
                    </div>

                    <!-- Mini movie items row -->
                    <div
                        class="grid grid-cols-2 gap-3 pt-2 sm:grid-cols-4 md:grid-cols-5"
                    >
                        <div v-for="j in 5" :key="j" class="space-y-2">
                            <CinemaSkeleton
                                className="w-full aspect-[2/3]"
                                rounded="rounded-lg"
                            />
                            <CinemaSkeleton className="w-full h-3" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
