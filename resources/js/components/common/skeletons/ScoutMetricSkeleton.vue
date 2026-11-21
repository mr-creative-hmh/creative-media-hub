<script setup lang="ts">
import CinemaSkeleton from './CinemaSkeleton.vue';
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
            v-for="i in 4"
            :key="i"
            class="relative overflow-hidden rounded-2xl border border-white/[0.06] bg-black/40 p-5 backdrop-blur-md"
        >
            <div class="mb-4 flex items-center justify-between">
                <CinemaSkeleton className="w-24 h-4" />
                <CinemaSkeleton className="w-10 h-10" rounded="rounded-xl" />
            </div>
            <CinemaSkeleton className="w-20 h-8 mb-2" />
            <CinemaSkeleton className="w-36 h-3" />
        </div>
    </div>
</template>
