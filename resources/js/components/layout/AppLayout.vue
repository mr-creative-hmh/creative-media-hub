<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import Navbar from './Navbar.vue';
import Sidebar from './Sidebar.vue';
import CinemaPlayer from '@/components/player/CinemaPlayer.vue';
import UnifiedJobCenterModal from '@/components/activity/UnifiedJobCenterModal.vue';
import PageTransitionLoader from '@/components/common/PageTransitionLoader.vue';
import GlobalToaster from '@/components/common/GlobalToaster.vue';
import { useScanner } from '@/composables/useScanner';
import { useDownloader } from '@/composables/useDownloader';

const { t, isRTL } = useI18n();
const { fetchStatus } = useScanner();
const { startBackgroundWorker } = useDownloader();

const activePlayerItem = ref<any | null>(null);

onMounted(() => {
    // Lock application permanently to Pure Cinema Dark Mode
    document.documentElement.classList.add('dark');
    document.documentElement.classList.remove('light');

    // Initialize global background scanner worker
    fetchStatus();

    // Initialize global background downloader worker
    startBackgroundWorker();
});

const handlePlay = (item: any, playlist?: any[]) => {
    if (playlist && Array.isArray(playlist)) {
        item.playlist = playlist;
    }
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

        <!-- Global Premium App Footer -->
        <footer class="border-t border-slate-800/80 bg-[#07090E]/90 backdrop-blur-md py-6 px-4 lg:px-8 mt-auto relative z-10">
            <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
                <div class="flex items-center gap-3">
                    <img src="/favicon.svg" alt="Creative Media Hub" class="w-6 h-6 shrink-0" />
                    <span class="font-bold text-slate-200 tracking-wide">Creative Media Hub</span>
                </div>
                <div class="flex items-center gap-2 text-center sm:text-right">
                    <span class="text-slate-400 font-medium">{{ t('created_by') }}</span>
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-cyan-500 animate-pulse"></span>
                </div>
            </div>
        </footer>

        <!-- Global Cinema Video Player Modal -->
        <CinemaPlayer
            v-if="activePlayerItem"
            :item="activePlayerItem"
            :playlist="activePlayerItem.playlist || []"
            :initial-progress="activePlayerItem.progress_seconds || activePlayerItem.initial_progress || 0"
            @close="closePlayer"
        />

        <!-- Global Unified Universal Activity Center Modal -->
        <UnifiedJobCenterModal />

        <!-- Global Cinema Page Transition Island Loader -->
        <PageTransitionLoader />

        <!-- Centralized High-Z-Index Global Bottom Toaster -->
        <GlobalToaster />
    </div>
</template>
