<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { router, Link } from '@inertiajs/vue3';
import { useScanner } from '@/composables/useScanner';
import { useDownloader } from '@/composables/useDownloader';
import AppLogo from '@/components/common/AppLogo.vue';
import {
    Search, Globe, LayoutDashboard, Film, Layers, Clapperboard, FolderSync,
    Subtitles, BarChart3, DownloadCloud, Menu, X, Tv,
    Settings, ScanLine, Sparkles, RefreshCw, Pause, BookOpen
} from 'lucide-vue-next';

const { t, locale, setLocale, isRTL } = useI18n();
const { scanStatus, isScanning, isPaused, openScanModal } = useScanner();
const { activeDownloads, totalSpeedDownFormatted } = useDownloader();

const searchQuery = ref('');
const isMobileMenuOpen = ref(false);

const handleSearch = () => {
    if (searchQuery.value.trim()) {
        router.get('/movies', { search: searchQuery.value }, { preserveState: true });
    }
};

const toggleLanguage = () => {
    setLocale(locale.value === 'ar' ? 'en' : 'ar');
};

const mobileNavItems = [
    { nameKey: 'nav.dashboard', href: '/', icon: LayoutDashboard },
    { nameKey: 'nav.movies', href: '/movies', icon: Film },
    { nameKey: 'nav.collections', href: '/collections', icon: Layers },
    { nameKey: 'nav.series', href: '/series', icon: Tv },
    { nameKey: 'nav.metadata', href: '/metadata', icon: Sparkles },
    { nameKey: 'nav.scanner', href: '/scanner', icon: ScanLine },
    { nameKey: 'nav.organizer', href: '/organizer', icon: FolderSync },
    { nameKey: 'nav.subtitles', href: '/subtitles', icon: Subtitles },
    { nameKey: 'nav.analytics', href: '/analytics', icon: BarChart3 },
    { nameKey: 'nav.downloads', href: '/downloads', icon: DownloadCloud },
    { nameKey: 'nav.docs', href: '/docs', icon: BookOpen },
    { nameKey: 'nav.settings', href: '/settings', icon: Settings },
];
</script>

<template>
    <header class="sticky top-0 z-40 w-full glass-panel border-b border-white/10 bg-[#07090E]/90 backdrop-blur-xl px-4 lg:px-8 py-3 flex items-center justify-between gap-4 transition-all">
        <!-- Logo & Mobile Toggle -->
        <div class="flex items-center gap-3">
            <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="lg:hidden p-2 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 cursor-pointer">
                <Menu v-if="!isMobileMenuOpen" class="w-5 h-5" />
                <X v-else class="w-5 h-5" />
            </button>
            <Link href="/" class="flex items-center">
                <AppLogo size="md" :show-text="true" />
            </Link>
        </div>

        <!-- Universal Search Bar -->
        <div class="flex-1 max-w-xl mx-2 sm:mx-6">
            <form @submit.prevent="handleSearch" class="relative w-full">
                <Search class="absolute top-1/2 -translate-y-1/2 left-3.5 w-4 h-4 text-slate-400" />
                <input
                    type="text"
                    v-model="searchQuery"
                    :placeholder="t('common.search_placeholder')"
                    class="w-full h-10 rounded-2xl bg-white/[0.04] border border-white/10 pl-10 pr-4 text-xs sm:text-sm text-white placeholder:text-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500/30 transition-all font-sans shadow-sm"
                />
            </form>
        </div>

        <!-- Right Action Controls -->
        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Active Downloads Quick Pill -->
            <Link
                v-if="activeDownloads.length > 0"
                href="/downloads"
                class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-indigo-500/20 border border-indigo-500/40 text-indigo-300 text-xs font-bold transition-all hover:bg-indigo-500/30 cursor-pointer shadow-sm"
                :title="isRTL ? 'التنزيلات النشطة' : 'Active Downloads'"
            >
                <DownloadCloud class="w-3.5 h-3.5 animate-bounce text-indigo-400" />
                <span class="font-mono text-[11px]">{{ totalSpeedDownFormatted }}</span>
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-ping"></span>
            </Link>

            <!-- Global Scanner Status Button / Active Pill -->
            <button
                v-if="isScanning || isPaused"
                @click="openScanModal"
                class="flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs font-bold transition-all active:scale-95 cursor-pointer"
                :class="isScanning ? 'bg-cyan-500/20 border-cyan-500/40 text-cyan-300 shadow-lg shadow-cyan-500/10' : 'bg-amber-500/20 border-amber-500/40 text-amber-300'"
                :title="isRTL ? 'عرض فاحص المكتبة المباشر' : 'View Virtual Scanner'"
            >
                <RefreshCw v-if="isScanning" class="w-3.5 h-3.5 animate-spin text-cyan-400" />
                <Pause v-else class="w-3.5 h-3.5 text-amber-400" />
                <span class="font-mono">{{ scanStatus.progress_percent || 0 }}%</span>
                <span class="text-[11px] opacity-80 hidden md:inline-block">({{ scanStatus.processed_files }}/{{ scanStatus.total_files }})</span>
            </button>

            <button
                v-else
                @click="openScanModal"
                class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-xl glass-panel border border-white/10 hover:border-cyan-500/40 text-xs font-bold text-slate-300 hover:text-white transition-all active:scale-95 cursor-pointer shadow-sm"
                :title="isRTL ? 'فاحص المكتبة المباشر' : 'Live Virtual Scanner'"
            >
                <ScanLine class="w-3.5 h-3.5 text-cyan-400" />
                <span>{{ isRTL ? 'فاحص المكتبة' : 'Scan Library' }}</span>
            </button>

            <!-- Language Switcher -->
            <button
                @click="toggleLanguage"
                class="flex items-center gap-2 px-3 py-1.5 rounded-xl glass-panel border border-white/10 hover:border-cyan-500/40 text-xs font-bold text-slate-300 hover:text-white transition-all active:scale-95 cursor-pointer shadow-sm"
            >
                <Globe class="w-3.5 h-3.5 text-cyan-400" />
                <span>{{ locale === 'ar' ? 'English' : 'عربي' }}</span>
            </button>
        </div>
    </header>

    <!-- Mobile Drawer Menu (Instant SPA Links) -->
    <div v-if="isMobileMenuOpen" class="lg:hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex flex-col p-6 animate-in fade-in">
        <div class="flex items-center justify-between pb-6 border-b border-white/10">
            <AppLogo size="md" :show-text="true" />
            <button @click="isMobileMenuOpen = false" class="p-2 text-slate-400 hover:text-white cursor-pointer">
                <X class="w-6 h-6" />
            </button>
        </div>
        <div class="flex flex-col gap-2 pt-6">
            <Link
                v-for="item in mobileNavItems"
                :key="item.href"
                :href="item.href"
                @click="isMobileMenuOpen = false"
                class="flex items-center gap-3.5 p-3 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 font-bold text-sm"
            >
                <component :is="item.icon" class="w-5 h-5 text-cyan-400" />
                <span>{{ t(item.nameKey) }}</span>
            </Link>
        </div>
    </div>
</template>
