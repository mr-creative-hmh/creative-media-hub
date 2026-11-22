<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { router, Link } from '@inertiajs/vue3';
import { useScanner } from '@/composables/useScanner';
import { useDownloader } from '@/composables/useDownloader';
import { useOrganizerPlan } from '@/composables/useOrganizerPlan';
import { useSubtitleJob } from '@/composables/useSubtitleJob';
import { useActivityCenter } from '@/composables/useActivityCenter';
import AppLogo from '@/components/common/AppLogo.vue';
import AccentColorPicker from '@/components/common/AccentColorPicker.vue';
import LiveSearchDropdown from '@/components/common/LiveSearchDropdown.vue';
import { home } from '@/routes';
import { index as moviesRoute } from '@/routes/movies';
import { index as collectionsRoute } from '@/routes/collections';
import { index as seriesRoute } from '@/routes/series';
import { index as watchHistoryRoute } from '@/routes/watch-history';
import { index as metadataRoute } from '@/routes/metadata';
import { index as scannerRoute } from '@/routes/scanner';
import { index as organizerRoute } from '@/routes/organizer';
import { index as scoutRoute } from '@/routes/scout';
import { index as subtitlesRoute } from '@/routes/subtitles';
import { index as analyticsRoute } from '@/routes/analytics';
import { index as downloadsRoute } from '@/routes/downloads';
import { index as docsRoute } from '@/routes/docs';
import { index as settingsRoute } from '@/routes/settings';
import {
    Search,
    Globe,
    LayoutDashboard,
    History,
    Film,
    Layers,
    Clapperboard,
    FolderSync,
    Subtitles,
    BarChart3,
    DownloadCloud,
    Menu,
    X,
    Tv,
    Settings,
    ScanLine,
    Sparkles,
    Activity,
    RefreshCw,
    Pause,
    BookOpen,
    Compass,
} from '@lucide/vue';

const { t, locale, setLocale, isRTL } = useI18n();
const { scanStatus, isScanning, isPaused, openScanModal } = useScanner();
const { activeDownloads, totalSpeedDownFormatted } = useDownloader();
const {
    planJobStatus,
    isAnalyzing,
    isPaused: isOrgPaused,
    openPlanModal,
} = useOrganizerPlan();
const {
    subtitleStatus,
    isSubtitleRunning,
    isSubtitlePaused,
    openSubtitleModal,
} = useSubtitleJob();
const { isAnyRunning, isAnyPaused, activeJobsCount, openActivityCenter } =
    useActivityCenter();

const searchQuery = ref('');
const isMobileMenuOpen = ref(false);

const handleSearch = (q?: string) => {
    const term = (typeof q === 'string' ? q : searchQuery.value).trim();
    if (term) {
        router.get(moviesRoute.url(), { search: term });
    }
};

const toggleLanguage = () => {
    setLocale(locale.value === 'ar' ? 'en' : 'ar');
};

const mobileNavItems = [
    { nameKey: 'nav.dashboard', href: home.url(), icon: LayoutDashboard },
    { nameKey: 'nav.movies', href: moviesRoute.url(), icon: Film },
    { nameKey: 'nav.collections', href: collectionsRoute.url(), icon: Layers },
    { nameKey: 'nav.series', href: seriesRoute.url(), icon: Tv },
    { nameKey: 'nav.scout', href: scoutRoute.url(), icon: Compass },
    {
        nameKey: 'nav.watch_history',
        href: watchHistoryRoute.url(),
        icon: History,
    },
    { nameKey: 'nav.metadata', href: metadataRoute.url(), icon: Sparkles },
    { nameKey: 'nav.scanner', href: scannerRoute.url(), icon: ScanLine },
    { nameKey: 'nav.organizer', href: organizerRoute.url(), icon: FolderSync },
    { nameKey: 'nav.subtitles', href: subtitlesRoute.url(), icon: Subtitles },
    { nameKey: 'nav.analytics', href: analyticsRoute.url(), icon: BarChart3 },
    {
        nameKey: 'nav.downloads',
        href: downloadsRoute.url(),
        icon: DownloadCloud,
    },
    { nameKey: 'nav.docs', href: docsRoute.url(), icon: BookOpen },
    { nameKey: 'nav.settings', href: settingsRoute.url(), icon: Settings },
];
</script>

<template>
    <header
        class="glass-panel sticky top-0 z-40 flex w-full items-center justify-between gap-4 border-b border-white/10 bg-[#07090E]/90 px-4 py-3 backdrop-blur-xl transition-all lg:px-8"
    >
        <!-- Logo & Mobile Toggle -->
        <div class="flex items-center gap-3">
            <button
                @click="isMobileMenuOpen = !isMobileMenuOpen"
                class="cursor-pointer rounded-lg bg-white/5 p-2 text-slate-300 hover:bg-white/10 lg:hidden"
            >
                <Menu v-if="!isMobileMenuOpen" class="h-5 w-5" />
                <X v-else class="h-5 w-5" />
            </button>
            <Link :href="home.url()" prefetch class="flex items-center">
                <AppLogo size="md" :show-text="true" />
            </Link>
        </div>

        <!-- Universal Instant Search Bar (YTS-Style Live Flyout) -->
        <div class="mx-2 max-w-xl flex-1 sm:mx-6">
            <LiveSearchDropdown
                v-model="searchQuery"
                :placeholder="t('common.search_placeholder')"
                variant="navbar"
                :auto-navigate="false"
                @submit="handleSearch"
            />
        </div>

        <!-- Right Action Controls -->
        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Active Downloads Quick Pill -->
            <Link
                v-if="activeDownloads.length > 0"
                :href="downloadsRoute.url()"
                prefetch
                class="animate-fade-in flex cursor-pointer items-center gap-2 rounded-xl border border-cyan-500/40 bg-gradient-to-r from-indigo-500/20 to-cyan-500/20 px-2.5 py-1.5 text-xs font-bold text-cyan-300 shadow-sm transition-all hover:bg-cyan-500/30 hover:shadow-cyan-500/20 active:scale-95 sm:px-3"
                :title="isRTL ? 'التنزيلات النشطة' : 'Active Downloads'"
            >
                <DownloadCloud
                    class="h-4 w-4 shrink-0 animate-bounce text-cyan-400"
                />
                <span
                    class="py-0.2 rounded-md bg-cyan-500/30 px-1.5 font-mono text-[10px] font-black text-cyan-200"
                >
                    {{ activeDownloads.length }}
                </span>
                <span
                    class="hidden font-mono text-[11px] text-slate-200 sm:inline"
                    >{{ totalSpeedDownFormatted }}</span
                >
                <span
                    class="h-1.5 w-1.5 shrink-0 animate-ping rounded-full bg-cyan-400"
                ></span>
            </Link>

            <!-- Universal Activity & Job Center Navbar Pill -->
            <button
                v-if="isAnyRunning || isAnyPaused"
                @click="openActivityCenter()"
                class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-1.5 text-xs font-bold transition-all active:scale-95"
                :class="
                    isAnyRunning
                        ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300 shadow-lg shadow-cyan-500/10'
                        : 'border-amber-500/40 bg-amber-500/20 text-amber-300'
                "
                :title="
                    isRTL
                        ? 'مركز مراقبة وإدارة العمليات الموحد'
                        : 'Universal Job Center'
                "
            >
                <RefreshCw
                    v-if="isAnyRunning"
                    class="h-3.5 w-3.5 animate-spin text-cyan-400"
                />
                <Pause v-else class="h-3.5 w-3.5 text-amber-400" />
                <span class="font-bold">{{
                    isRTL ? 'العمليات النشطة' : 'Active Jobs'
                }}</span>
                <span
                    class="rounded-md bg-white/10 px-1.5 py-0.5 font-mono text-[10px]"
                    >{{ activeJobsCount }}</span
                >
            </button>

            <button
                v-else
                @click="openActivityCenter()"
                class="glass-panel hidden cursor-pointer items-center gap-1.5 rounded-xl border border-white/10 px-3 py-1.5 text-xs font-bold text-slate-300 shadow-sm transition-all hover:border-cyan-500/40 hover:text-white active:scale-95 sm:flex"
                :title="
                    isRTL
                        ? 'مركز مراقبة وإدارة العمليات الموحد'
                        : 'Universal Job Center'
                "
            >
                <Activity class="h-3.5 w-3.5 text-cyan-400" />
                <span>{{ isRTL ? 'مركز العمليات' : 'Job Center' }}</span>
            </button>

            <!-- Cinema Accent Color Picker -->
            <AccentColorPicker />

            <!-- Language Switcher -->
            <button
                @click="toggleLanguage"
                class="glass-panel flex cursor-pointer items-center gap-2 rounded-xl border border-white/10 px-3 py-1.5 text-xs font-bold text-slate-300 shadow-sm transition-all hover:border-cyan-500/40 hover:text-white active:scale-95"
            >
                <Globe class="h-3.5 w-3.5 text-cyan-400" />
                <span>{{ locale === 'ar' ? 'English' : 'عربي' }}</span>
            </button>
        </div>

        <!-- Mobile Drawer Menu (Instant SPA Links) -->
        <Teleport to="body">
            <div
                v-if="isMobileMenuOpen"
                :dir="isRTL ? 'rtl' : 'ltr'"
                class="animate-in fade-in fixed inset-0 z-50 flex flex-col bg-black/80 p-6 backdrop-blur-md lg:hidden"
            >
                <div
                    class="flex items-center justify-between border-b border-white/10 pb-6"
                >
                    <AppLogo size="md" :show-text="true" />
                    <div class="flex items-center gap-2">
                        <AccentColorPicker :compact="true" />
                        <button
                            @click="isMobileMenuOpen = false"
                            class="cursor-pointer p-2 text-slate-400 hover:text-white"
                        >
                            <X class="h-6 w-6" />
                        </button>
                    </div>
                </div>
                <div class="flex flex-col gap-2 pt-6">
                    <Link
                        v-for="item in mobileNavItems"
                        :key="item.href"
                        :href="item.href"
                        prefetch
                        @click="isMobileMenuOpen = false"
                        class="flex items-center gap-3.5 rounded-xl p-3 text-sm font-bold text-slate-300 hover:bg-white/10 hover:text-white"
                    >
                        <component
                            :is="item.icon"
                            class="h-5 w-5 text-cyan-400"
                        />
                        <span>{{ t(item.nameKey) }}</span>
                    </Link>
                </div>
            </div>
        </Teleport>
    </header>
</template>
