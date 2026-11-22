<script setup lang="ts">
import {
    ref,
    computed,
    onMounted,
    onUnmounted,
    defineAsyncComponent,
} from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import Navbar from './Navbar.vue';
import Sidebar from './Sidebar.vue';
import MobileBottomNav from './MobileBottomNav.vue';
import MobileMenuDrawer from './MobileMenuDrawer.vue';
import AppAmbientBackground from '@/components/common/AppAmbientBackground.vue';
import PageTransitionLoader from '@/components/common/PageTransitionLoader.vue';
import GlobalToaster from '@/components/common/GlobalToaster.vue';
import MotionPage from '@/components/motion/MotionPage.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useScanner } from '@/composables/useScanner';
import { useDownloader } from '@/composables/useDownloader';
import { useActivityCenter } from '@/composables/useActivityCenter';

const CinemaPlayer = defineAsyncComponent(
    () => import('@/components/player/CinemaPlayer.vue'),
);
const BandersnatchPlayer = defineAsyncComponent(
    () => import('@/components/player/BandersnatchPlayer.vue'),
);
const UnifiedJobCenterModal = defineAsyncComponent(
    () => import('@/components/activity/UnifiedJobCenterModal.vue'),
);

const page = usePage();
const { t, isRTL } = useI18n();
const { fetchStatus } = useScanner();
const { startBackgroundWorker } = useDownloader();
const { isActivityCenterOpen } = useActivityCenter();

const activePlayerItem = ref<any | null>(null);
const activeBandersnatchEpisode = ref<any | null>(null);

// Persisted Sidebar collapsed state
const isSidebarCollapsed = ref(
    typeof window !== 'undefined'
        ? localStorage.getItem('cmh_sidebar_collapsed') === 'true'
        : false,
);

const isMobileMenuOpen = ref(false);

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    if (typeof window !== 'undefined') {
        localStorage.setItem(
            'cmh_sidebar_collapsed',
            String(isSidebarCollapsed.value),
        );
    }
};

const pageTransitionKey = computed(
    () => `${page.component}:${page.url.split('?')[0]}`,
);

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
    // Lock application to Pure Cinema Dark Mode
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
    if (typeof window !== 'undefined') {
        window.removeEventListener(
            'play-bandersnatch',
            onPlayBandersnatchEvent,
        );
    }
});
</script>

<template>
    <TooltipProvider :delay-duration="220" :skip-delay-duration="300">
        <div
            class="bg-background text-foreground selection:bg-primary/30 selection:text-primary relative flex min-h-screen w-full overflow-x-clip font-sans transition-colors duration-300"
            :dir="isRTL ? 'rtl' : 'ltr'"
        >
            <!-- Cinema Ambient Lighting & Crosshair Grid -->
            <AppAmbientBackground />

            <!-- Direct Child 1: Full-Height Sticky Sidebar Column (Desktop) -->
            <Sidebar
                :is-collapsed="isSidebarCollapsed"
                @toggle-collapse="toggleSidebar"
            />

            <!-- Direct Child 2: Content Column Beside Sidebar -->
            <div class="relative z-10 flex min-w-0 flex-1 flex-col">
                <!-- Sticky Floating Header Navbar inside Content Column -->
                <Navbar
                    :is-sidebar-collapsed="isSidebarCollapsed"
                    @toggle-sidebar="toggleSidebar"
                    @open-menu="isMobileMenuOpen = true"
                />

                <!-- Main Content Area -->
                <main
                    class="mx-auto w-full max-w-7xl flex-1 p-4 pb-24 lg:p-6 lg:pb-8"
                >
                    <MotionPage :motion-key="pageTransitionKey">
                        <slot
                            :play="handlePlay"
                            :play-bandersnatch="handlePlayBandersnatch"
                        />
                    </MotionPage>
                </main>

                <!-- Global Cinema Footer (Aligned beside sidebar) -->
                <footer
                    class="border-border/80 bg-background/85 relative z-10 mt-auto border-t px-4 py-6 backdrop-blur-md lg:px-8"
                >
                    <div
                        class="text-muted-foreground mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 text-xs sm:flex-row"
                    >
                        <div class="flex items-center gap-3">
                            <img
                                src="/favicon.svg"
                                alt="Creative Media Hub"
                                class="size-6 shrink-0"
                            />
                            <span
                                class="text-foreground font-bold tracking-wide"
                            >
                                Creative Media Hub
                            </span>
                        </div>
                        <div
                            class="flex items-center gap-2 text-center sm:text-right"
                        >
                            <span class="text-muted-foreground font-medium">
                                {{ t('created_by') }}
                            </span>
                            <span
                                class="bg-primary inline-block size-1.5 animate-pulse rounded-full"
                            ></span>
                        </div>
                    </div>
                </footer>
            </div>

            <!-- Mobile Bottom Dock (Floating pill) -->
            <MobileBottomNav
                :is-menu-open="isMobileMenuOpen"
                @open-menu="isMobileMenuOpen = true"
            />

            <!-- Mobile Menu Drawer (Slide-in) -->
            <MobileMenuDrawer
                :open="isMobileMenuOpen"
                @update:open="(val) => (isMobileMenuOpen = val)"
            />

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

            <!-- Global Unified Activity Center Modal -->
            <UnifiedJobCenterModal v-if="isActivityCenterOpen" />

            <!-- Global Cinema Page Transition Island Loader -->
            <PageTransitionLoader />

            <!-- Centralized High-Z-Index Global HUD Toaster -->
            <GlobalToaster />
        </div>
    </TooltipProvider>
</template>
