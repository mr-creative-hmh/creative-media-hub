<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { router } from '@inertiajs/vue3';
import { Search, Globe, Film, Clapperboard, FolderSync, Subtitles, BarChart3, Download, Menu, X } from 'lucide-vue-next';

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
</script>

<template>
    <header class="sticky top-0 z-40 w-full glass-panel border-b border-white/10 px-4 lg:px-8 py-3.5 flex items-center justify-between gap-4 transition-all">
        <!-- Logo & Mobile Toggle -->
        <div class="flex items-center gap-3">
            <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="lg:hidden p-2 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300">
                <Menu v-if="!isMobileMenuOpen" class="w-5 h-5" />
                <X v-else class="w-5 h-5" />
            </button>
            <a href="/movies" class="flex items-center gap-2.5 group">
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
        <div class="flex-1 max-w-xl mx-2">
            <form @submit.prevent="handleSearch" class="relative group">
                <Search class="absolute top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-cyan-400 transition-colors" :class="isRTL ? 'right-3.5' : 'left-3.5'" />
                <input
                    type="text"
                    v-model="searchQuery"
                    :placeholder="t('common.search_placeholder')"
                    class="w-full h-10 rounded-full bg-white/[0.04] border border-white/10 focus:border-cyan-500/50 focus:bg-white/[0.07] focus:ring-2 focus:ring-cyan-500/20 text-sm text-slate-100 placeholder-slate-500 transition-all outline-none"
                    :class="isRTL ? 'pr-10 pl-4 text-right' : 'pl-10 pr-4 text-left'"
                />
            </form>
        </div>

        <!-- Language & Actions -->
        <div class="flex items-center gap-2.5">
            <!-- Language Switcher Button -->
            <button
                @click="toggleLanguage"
                class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-semibold text-slate-200 transition-colors shadow-sm"
                :title="locale === 'ar' ? 'Switch to English' : 'التحويل للغة العربية'"
            >
                <Globe class="w-3.5 h-3.5 text-cyan-400" />
                <span>{{ locale === 'ar' ? 'English' : 'العربية' }}</span>
            </button>
        </div>
    </header>
</template>
