<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import Navbar from './Navbar.vue';
import Sidebar from './Sidebar.vue';
import MobileBottomNav from './MobileBottomNav.vue';
import CinemaPlayer from '@/components/player/CinemaPlayer.vue';
import BandersnatchPlayer from '@/components/player/BandersnatchPlayer.vue';
import UnifiedJobCenterModal from '@/components/activity/UnifiedJobCenterModal.vue';
import PageTransitionLoader from '@/components/common/PageTransitionLoader.vue';
import GlobalToaster from '@/components/common/GlobalToaster.vue';
import { useScanner } from '@/composables/useScanner';
import { useDownloader } from '@/composables/useDownloader';

const { t, isRTL } = useI18n();
const { fetchStatus } = useScanner();
const { startBackgroundWorker } = useDownloader();

const activePlayerItem = ref<any | null>(null);
const activeBandersnatchEpisode = ref<any | null>(null);

const handlePlay = (item: any, playlist?: any[]) => {
    if (playlist && Array.isArray(playlist)) {
        item.playlist = playlist;
    }
    activePlayerItem.value = item;
};

const closePlayer = () => {
    activePlayerItem.value = null;
};

const handlePlayBandersnatch = (ep: any) => {
    activeBandersnatchEpisode.value = ep;
};

const closeBandersnatch = () => {
    activeBandersnatchEpisode.value = null;
};

const onPlayBandersnatchEvent = (event: any) => {
    if (event.detail) {
        handlePlayBandersnatch(event.detail);
    }
};

onMounted(() => {
    // Lock application permanently to Pure Cinema Dark Mode
    document.documentElement.classList.add('dark');
    document.documentElement.classList.remove('light');

    // Listen for global custom event to trigger Bandersnatch Interactive Experience
    window.addEventListener('play-bandersnatch', onPlayBandersnatchEvent);

    // Initialize global background scanner worker
    fetchStatus();

    // Initialize global background downloader worker
    startBackgroundWorker();
});

onUnmounted(() => {
    window.removeEventListener('play-bandersnatch', onPlayBandersnatchEvent);
});
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col bg-[#07090E] font-sans text-slate-100 transition-colors duration-300 selection:bg-cyan-500/30 selection:text-cyan-300"
        :dir="isRTL ? 'rtl' : 'ltr'"
    >
        <!-- Ambient Decorative Lighting Spheres -->
        <div
            class="ambient-glow pointer-events-none top-0 left-1/4 h-96 w-96 bg-cyan-500/20"
        ></div>
        <div
            class="ambient-glow pointer-events-none top-96 right-10 h-[30rem] w-[30rem] bg-indigo-500/15"
        ></div>

        <!-- Sticky Header Navbar -->
        <Navbar />

        <!-- Main Body Wrapper -->
        <div class="flex w-full flex-1">
            <Sidebar />
            <main
                class="relative z-10 mx-auto w-full max-w-7xl flex-1 overflow-x-hidden p-4 pb-24 lg:p-8 lg:pb-8"
            >
                <slot
                    :play="handlePlay"
                    :play-bandersnatch="handlePlayBandersnatch"
                />
            </main>
        </div>

        <!-- Mobile Bottom Navigation Bar (Thumb Friendly) -->
        <MobileBottomNav />

        <!-- Global Premium App Footer -->
        <footer
            class="relative z-10 mt-auto border-t border-slate-800/80 bg-[#07090E]/90 px-4 py-6 backdrop-blur-md lg:px-8"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 text-xs text-slate-400 sm:flex-row"
            >
                <div class="flex items-center gap-3">
                    <img
                        src="/favicon.svg"
                        alt="Creative Media Hub"
                        class="h-6 w-6 shrink-0"
                    />
                    <span class="font-bold tracking-wide text-slate-200"
                        >Creative Media Hub</span
                    >
                </div>
                <div class="flex items-center gap-2 text-center sm:text-right">
                    <span class="font-medium text-slate-400">{{
                        t('created_by')
                    }}</span>
                    <span
                        class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-cyan-500"
                    ></span>
                </div>
            </div>
        </footer>

        <!-- Global Cinema Video Player Modal -->
        <CinemaPlayer
            v-if="activePlayerItem"
            :item="activePlayerItem"
            :playlist="activePlayerItem.playlist || []"
            :initial-progress="
                activePlayerItem.progress_seconds ||
                activePlayerItem.initial_progress ||
                0
            "
            @close="closePlayer"
        />

        <!-- Black Mirror: Bandersnatch Interactive Experience Player -->
        <BandersnatchPlayer
            v-if="activeBandersnatchEpisode"
            :episode="activeBandersnatchEpisode"
            @close="closeBandersnatch"
        />

        <!-- Global Unified Universal Activity Center Modal -->
        <UnifiedJobCenterModal />

        <!-- Global Cinema Page Transition Island Loader -->
        <PageTransitionLoader />

        <!-- Centralized High-Z-Index Global Bottom Toaster -->
        <GlobalToaster />
    </div>
</template>
