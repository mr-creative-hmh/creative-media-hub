<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import Navbar from './Navbar.vue';
import Sidebar from './Sidebar.vue';
import CinemaPlayer from '@/components/player/CinemaPlayer.vue';
import ScannerStatusModal from '@/components/scanner/ScannerStatusModal.vue';
import { useScanner } from '@/composables/useScanner';

const { isRTL } = useI18n();
const { fetchStatus } = useScanner();

const activePlayerItem = ref<any | null>(null);

onMounted(() => {
    // Lock application permanently to Pure Cinema Dark Mode
    document.documentElement.classList.add('dark');
    document.documentElement.classList.remove('light');

    // Initialize global background scanner worker
    fetchStatus();
});

const handlePlay = (item: any) => {
    activePlayerItem.value = item;
};

const closePlayer = () => {
    activePlayerItem.value = null;
};
</script>

<template>
    <div
        class="min-h-screen bg-[#07090E] text-slate-100 flex flex-col font-sans relative transition-colors duration-300 selection:bg-cyan-500/30 selection:text-cyan-300"
        :dir="isRTL ? 'rtl' : 'ltr'"
    >
        <!-- Ambient Decorative Lighting Spheres -->
        <div class="ambient-glow bg-cyan-500/20 w-96 h-96 top-0 left-1/4 pointer-events-none"></div>
        <div class="ambient-glow bg-indigo-500/15 w-[30rem] h-[30rem] top-96 right-10 pointer-events-none"></div>

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

        <!-- Global Fast Background Scanner Status Modal -->
        <ScannerStatusModal />
    </div>
</template>
