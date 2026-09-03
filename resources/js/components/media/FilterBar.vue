<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { router } from '@inertiajs/vue3';
import {
    Filter, Sparkles, Star, Calendar, Clock,
    Layers, Check, RotateCcw, Heart, Film, ArrowUpDown, Tv, Globe,
    Search, X
} from 'lucide-vue-next';

const props = defineProps<{
    genres: any[];
    filters: Record<string, any>;
    showVibes?: boolean;
}>();

const { t, isRTL } = useI18n();

const searchQuery = ref(props.filters.search || '');
const selectedGenre = ref(props.filters.genre || '');
const selectedOrigin = ref(props.filters.origin || '');
const selectedResolution = ref(props.filters.resolution || '');
const selectedSort = ref(props.filters.sort || 'rating');
const selectedVibe = ref(props.filters.vibe || '');
const favoriteOnly = ref(props.filters.favorite_only === '1' || props.filters.favorite_only === 1 || props.filters.favorite_only === true);

const hasActiveFilters = computed(() => {
    return !!(searchQuery.value.trim() || selectedGenre.value || selectedOrigin.value || selectedResolution.value || (selectedSort.value && selectedSort.value !== 'rating') || selectedVibe.value || favoriteOnly.value);
});

const applyFilters = () => {
    router.get(window.location.pathname, {
        ...props.filters,
        search: searchQuery.value.trim() || undefined,
        genre: selectedGenre.value || undefined,
        origin: selectedOrigin.value || undefined,
        resolution: selectedResolution.value || undefined,
        sort: selectedSort.value || undefined,
        vibe: selectedVibe.value || undefined,
        favorite_only: favoriteOnly.value ? 1 : undefined,
    }, { preserveState: true, preserveScroll: true });
};

const handleSearch = () => {
    applyFilters();
};

const clearSearch = () => {
    searchQuery.value = '';
    applyFilters();
};

const selectOrigin = (orig: string) => {
    selectedOrigin.value = selectedOrigin.value === orig ? '' : orig;
    applyFilters();
};

const selectGenre = (slug: string) => {
    selectedGenre.value = selectedGenre.value === slug ? '' : slug;
    applyFilters();
};

const selectResolution = (res: string) => {
    selectedResolution.value = selectedResolution.value === res ? '' : res;
    applyFilters();
};

const selectSort = (sortKey: string) => {
    selectedSort.value = sortKey;
    applyFilters();
};

const selectVibe = (v: string) => {
    selectedVibe.value = selectedVibe.value === v ? '' : v;
    applyFilters();
};

const toggleFavorite = () => {
    favoriteOnly.value = !favoriteOnly.value;
    applyFilters();
};

const resetAllFilters = () => {
    searchQuery.value = '';
    selectedGenre.value = '';
    selectedOrigin.value = '';
    selectedResolution.value = '';
    selectedSort.value = 'rating';
    selectedVibe.value = '';
    favoriteOnly.value = false;
    router.get(window.location.pathname, {}, { preserveState: true, preserveScroll: true });
};

const regionalOrigins = [
    { id: '', label_en: 'All Regions', label_ar: 'جميع الدول', flag: '🌍' },
    { id: 'arabic', label_en: 'Arabic Cinema', label_ar: 'سينما ومسلسلات عربية', flag: '🇸🇦' },
    { id: 'indian', label_en: 'Bollywood & Indian', label_ar: 'سينما هندية (بوليوود)', flag: '🇮🇳' },
    { id: 'asian', label_en: 'Anime & Asian', label_ar: 'إنيمي وسينما آسيوية', flag: '🇯🇵' },
    { id: 'turkish', label_en: 'Turkish Cinema', label_ar: 'سينما وأعمال تركية', flag: '🇹🇷' },
    { id: 'hollywood', label_en: 'Hollywood & Western', label_ar: 'سينما هوليوود وعالمي', flag: '🇺🇸' },
    { id: 'european', label_en: 'European Cinema', label_ar: 'سينما أوروبية', flag: '🇪🇺' },
];

const curatedVibes = [
    { id: 'Mind-Bending', label_en: 'Mind-Bending Sci-Fi', label_ar: 'خيال علمي مشوق' },
    { id: 'Adrenaline', label_en: 'Adrenaline Rush', label_ar: 'أكشن وإثارة' },
    { id: 'Late-Night Noir', label_en: 'Late-Night Noir', label_ar: 'غموض وجريمة' },
    { id: 'Emotional Resonance', label_en: 'Emotional Drama', label_ar: 'دراما مؤثرة' },
    { id: 'Epic Worlds', label_en: 'Epic Worlds', label_ar: 'مغامرة وفانتازيا' },
];
</script>

<template>
    <div class="glass-panel rounded-3xl p-5 mb-8 border border-slate-200 dark:border-white/10 space-y-5 shadow-sm relative overflow-hidden">
        <!-- Collections-Style Prominent Search Input Bar -->
        <div class="relative w-full max-w-xl">
            <div class="relative">
                <Search
                    class="absolute top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-slate-400"
                    :class="isRTL ? 'right-4' : 'left-4'"
                />
                <input
                    v-model="searchQuery"
                    @keyup.enter="handleSearch"
                    type="text"
                    :placeholder="isRTL ? 'ابحث بالاسم، الممثل، المخرج أو سنة الإنتاج...' : 'Search by title, cast, director, or release year...'"
                    class="w-full py-3 rounded-2xl bg-black/30 dark:bg-black/40 border border-slate-200 dark:border-white/15 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 transition-all shadow-inner"
                    :class="isRTL ? 'pr-11 pl-28' : 'pl-11 pr-28'"
                />
                <div
                    class="absolute top-1/2 -translate-y-1/2 flex items-center gap-1.5"
                    :class="isRTL ? 'left-2' : 'right-2'"
                >
                    <button
                        v-if="searchQuery"
                        @click="clearSearch"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-all cursor-pointer"
                        title="Clear"
                    >
                        <X class="w-3.5 h-3.5" />
                    </button>
                    <button
                        @click="handleSearch"
                        class="px-3.5 py-1.5 rounded-xl bg-cyan-500 text-slate-950 text-xs font-black hover:bg-cyan-400 transition-all cursor-pointer shadow-md shadow-cyan-500/20"
                    >
                        {{ isRTL ? 'بحث' : 'Search' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- 0. Regional Origin Carousel (Arabic, Indian/Bollywood, Anime/Asian, Turkish, Hollywood, European) -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400">
                    <Globe class="w-3.5 h-3.5 text-cyan-600 dark:text-cyan-400" />
                    <span>{{ isRTL ? 'التصنيف حسب الدولة والإنتاج (سينما عالمية)' : 'Regional Origin & World Cinema' }}</span>
                </div>

                <button
                    v-if="hasActiveFilters"
                    @click="resetAllFilters"
                    class="flex items-center gap-1.5 text-xs font-bold text-rose-500 hover:text-rose-400 cursor-pointer transition-colors"
                >
                    <RotateCcw class="w-3.5 h-3.5" />
                    <span>{{ t('common.clear_filters') }}</span>
                </button>
            </div>

            <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
                <button
                    v-for="orig in regionalOrigins"
                    :key="orig.id"
                    @click="selectOrigin(orig.id)"
                    class="px-3.5 py-1.5 rounded-2xl text-xs font-bold whitespace-nowrap transition-all cursor-pointer shadow-sm active:scale-95 flex items-center gap-1.5"
                    :class="selectedOrigin === orig.id
                        ? 'bg-gradient-to-r from-cyan-500 to-blue-500 text-slate-950 font-black shadow-cyan-500/20'
                        : 'bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10'"
                >
                    <span class="text-sm">{{ orig.flag }}</span>
                    <span>{{ isRTL ? orig.label_ar : orig.label_en }}</span>
                    <Check v-if="selectedOrigin === orig.id" class="w-3.5 h-3.5 stroke-[3]" />
                </button>
            </div>
        </div>

        <!-- 1. Genres Pill Carousel -->
        <div class="space-y-2 pt-2 border-t border-slate-200 dark:border-white/5">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400">
                <Layers class="w-3.5 h-3.5 text-cyan-600 dark:text-cyan-400" />
                <span>{{ t('common.genres') }}</span>
            </div>

            <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
                <button
                    @click="selectGenre('')"
                    class="px-4 py-2 rounded-2xl text-xs font-bold whitespace-nowrap transition-all cursor-pointer shadow-sm active:scale-95"
                    :class="!selectedGenre
                        ? 'bg-cyan-500 text-slate-950 font-black shadow-cyan-500/20'
                        : 'bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10'"
                >
                    {{ t('common.all_genres') }}
                </button>

                <button
                    v-for="g in genres"
                    :key="g.id"
                    @click="selectGenre(g.slug)"
                    class="px-4 py-2 rounded-2xl text-xs font-bold whitespace-nowrap transition-all cursor-pointer shadow-sm active:scale-95 flex items-center gap-1.5"
                    :class="selectedGenre === g.slug
                        ? 'bg-cyan-500 text-slate-950 font-black shadow-cyan-500/20'
                        : 'bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10'"
                >
                    <span>{{ isRTL && g.name_ar ? g.name_ar : g.name_en }}</span>
                    <Check v-if="selectedGenre === g.slug" class="w-3.5 h-3.5 stroke-[3]" />
                </button>
            </div>
        </div>

        <!-- 2. AI Mood & Vibe Segmented Chips -->
        <div v-if="showVibes" class="space-y-2 pt-2 border-t border-slate-200 dark:border-white/5">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400">
                <Sparkles class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                <span>{{ t('common.vibe') }}</span>
            </div>

            <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
                <button
                    v-for="v in curatedVibes"
                    :key="v.id"
                    @click="selectVibe(v.id)"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all flex items-center gap-1.5 cursor-pointer shadow-sm active:scale-95"
                    :class="selectedVibe === v.id
                        ? 'bg-indigo-600 text-white font-black shadow-md shadow-indigo-600/30'
                        : 'bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10'"
                >
                    <span class="text-indigo-400 text-xs">✦</span>
                    <span>{{ isRTL ? v.label_ar : v.label_en }}</span>
                    <Check v-if="selectedVibe === v.id" class="w-3 h-3 stroke-[3]" />
                </button>
            </div>
        </div>

        <!-- 3. Quality & Sorting Interactive Segmented Controls -->
        <div class="flex items-center justify-between flex-wrap gap-4 pt-3 border-t border-slate-200 dark:border-white/5">
            <!-- Quality Segmented Control -->
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ t('common.quality') }}:</span>
                <div class="flex items-center p-1 rounded-2xl bg-slate-100 dark:bg-black/40 border border-slate-200 dark:border-white/10">
                    <button
                        v-for="res in [
                            { key: '', label: t('common.all_qualities') },
                            { key: '4K', label: '4K UHD' },
                            { key: '1080p', label: '1080p FHD' },
                            { key: '720p', label: '720p HD' },
                        ]"
                        :key="res.key"
                        @click="selectResolution(res.key)"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
                        :class="selectedResolution === res.key
                            ? 'bg-cyan-500 text-slate-950 font-black shadow-sm'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                    >
                        {{ res.label }}
                    </button>
                </div>
            </div>

            <!-- Sorting Segmented Control -->
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ t('common.sort_by') }}:</span>
                <div class="flex items-center p-1 rounded-2xl bg-slate-100 dark:bg-black/40 border border-slate-200 dark:border-white/10">
                    <button
                        v-for="s in [
                            { key: 'rating', label: t('common.sort_rating'), icon: Star },
                            { key: 'date_added', label: t('common.sort_recent'), icon: Clock },
                            { key: 'year', label: t('common.sort_year'), icon: Calendar },
                            { key: 'title', label: t('common.sort_title'), icon: ArrowUpDown },
                        ]"
                        :key="s.key"
                        @click="selectSort(s.key)"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer whitespace-nowrap"
                        :class="selectedSort === s.key
                            ? 'bg-indigo-600 text-white font-black shadow-sm'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                    >
                        <component :is="s.icon" class="w-3.5 h-3.5" />
                        <span>{{ s.label }}</span>
                    </button>
                </div>
            </div>

            <!-- Favorites Toggle Button -->
            <button
                @click="toggleFavorite"
                class="px-3.5 py-1.5 rounded-2xl text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer border"
                :class="favoriteOnly
                    ? 'bg-rose-500/20 text-rose-400 border-rose-500/40 shadow-sm'
                    : 'bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-white/10'"
            >
                <Heart class="w-3.5 h-3.5" :class="{ 'fill-rose-500 text-rose-500': favoriteOnly }" />
                <span>{{ t('common.favorite') }}</span>
            </button>
        </div>
    </div>
</template>
