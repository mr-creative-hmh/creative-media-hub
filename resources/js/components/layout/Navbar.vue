<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { router } from '@inertiajs/vue3';
import {
    Search, Globe, LayoutDashboard, Film, Clapperboard, FolderSync,
    Subtitles, BarChart3, DownloadCloud, Menu, X, Tv
} from 'lucide-vue-next';

const { t, locale, setLocale, isRTL } = useI18n();
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
    { nameKey: 'nav.series', href: '/series', icon: Tv },
    { nameKey: 'nav.organizer', href: '/organizer', icon: FolderSync },
    { nameKey: 'nav.subtitles', href: '/subtitles', icon: Subtitles },
    { nameKey: 'nav.analytics', href: '/analytics', icon: BarChart3 },
    { nameKey: 'nav.downloads', href: '/downloads', icon: DownloadCloud },
];
</script>

<template>
    <header class="sticky top-0 z-40 w-full glass-panel border-b border-white/10 px-4 lg:px-8 py-3.5 flex items-center justify-between gap-4 transition-all">
        <!-- Logo & Mobile Toggle -->
        <div class="flex items-center gap-3">
            <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="lg:hidden p-2 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300">
                <Menu v-if="!isMobileMenuOpen" class="w-5 h-5" />
                <X v-else class="w-5 h-5" />
            </button>
            <a href="/" class="flex items-center gap-2.5 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-cyan-500 via-blue-600 to-indigo-600 flex items-center justify-center shadow-lg shadow-cyan-500/20 group-hover:scale-105 transition-transform">
                    <Clapperboard class="w-5 h-5 text-white" />
                </div>
                <div class="flex flex-col">
                    <span class="font-extrabold text-lg tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-white via-slate-100 to-cyan-400 font-sans">
                        {{ t('app_name') }}
                    </span>
                    <span class="text-[10px] text-slate-400 font-medium tracking-wider uppercase hidden sm:inline-block">
                        Ultra Cinema Suite
                    </span>
                </div>
            </a>
        </div>

        <!-- Universal Search Bar -->
        <div class="flex-1 max-w-xl mx-2 sm:mx-6">
            <form @submit.prevent="handleSearch" class="relative w-full">
                <Search class="absolute top-1/2 -translate-y-1/2 left-3.5 w-4 h-4 text-slate-400" />
                <input
                    type="text"
                    v-model="searchQuery"
                    :placeholder="t('common.search_placeholder')"
                    class="w-full h-10 rounded-2xl bg-white/[0.04] border border-white/10 pl-10 pr-4 text-xs sm:text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none focus:border-cyan-500/50 focus:bg-white/[0.07] transition-all font-sans"
                />
            </form>
        </div>

        <!-- Language Switcher & Controls -->
        <div class="flex items-center gap-2 sm:gap-3">
            <button
                @click="toggleLanguage"
                class="flex items-center gap-2 px-3 py-1.5 rounded-xl glass-panel border border-white/10 hover:border-cyan-500/40 text-xs font-bold text-slate-300 hover:text-white transition-all active:scale-95"
                :title="isRTL ? 'Switch to English' : 'التحويل إلى العربية'"
            >
                <Globe class="w-3.5 h-3.5 text-cyan-400" />
                <span>{{ locale === 'ar' ? 'English' : 'العربية' }}</span>
            </button>
        </div>
    </header>

    <!-- Mobile Drawer Menu -->
    <div v-if="isMobileMenuOpen" class="lg:hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex flex-col p-6 space-y-4">
        <div class="flex items-center justify-between pb-4 border-b border-white/10">
            <span class="font-extrabold text-white text-lg">{{ t('app_name') }}</span>
            <button @click="isMobileMenuOpen = false" class="p-2 rounded-lg bg-white/10 text-white">
                <X class="w-5 h-5" />
            </button>
        </div>

        <nav class="space-y-2 flex-1">
            <a
                v-for="item in mobileNavItems"
                :key="item.href"
                :href="item.href"
                @click="isMobileMenuOpen = false"
                class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/10 text-slate-200 font-bold text-sm"
            >
                <component :is="item.icon" class="w-5 h-5 text-cyan-400" />
                <span>{{ t(item.nameKey) }}</span>
            </a>
        </nav>
    </div>
</template>
