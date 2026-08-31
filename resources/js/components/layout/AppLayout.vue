<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import Navbar from './Navbar.vue';
import Sidebar from './Sidebar.vue';
import CinemaPlayer from '@/components/player/CinemaPlayer.vue';

const { isRTL } = useI18n();

const activePlayerItem = ref<any | null>(null);

const handlePlay = (item: any) => {
    activePlayerItem.value = item;
};

const closePlayer = () => {
    activePlayerItem.value = null;
};
</script>

<template>
    <div class="min-h-screen bg-[#07090E] text-slate-100 flex flex-col font-sans relative" :dir="isRTL ? 'rtl' : 'ltr'">
        <!-- Ambient Decorative Lighting Spheres -->
        <div class="ambient-glow bg-cyan-600/20 w-96 h-96 top-0 left-1/4"></div>
        <div class="ambient-glow bg-indigo-600/15 w-[30rem] h-[30rem] top-96 right-10"></div>

        <!-- Sticky Header Navbar -->
        <Navbar />

        <!-- Main Body Wrapper -->
        <div class="flex-1 flex w-full">
            <Sidebar />
            <main class="flex-1 p-4 lg:p-8 max-w-7xl mx-auto w-full overflow-x-hidden relative z-10">
                <slot :play="handlePlay" />
            </main>
        </div>

        <!-- Global Cinema Video Player Modal -->
        <CinemaPlayer
            v-if="activePlayerItem"
            :item="activePlayerItem"
            @close="closePlayer"
        />
    </div>
</template>
