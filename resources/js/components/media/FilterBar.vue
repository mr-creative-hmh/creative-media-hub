<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { router } from '@inertiajs/vue3';
import {
    Filter, Sparkles, Star, Calendar, Clock,
    Layers, Check, RotateCcw, Heart, Film, ArrowUpDown, Tv
} from 'lucide-vue-next';

const props = defineProps<{
    genres: any[];
    filters: Record<string, any>;
    showVibes?: boolean;
}>();

const { t, isRTL } = useI18n();

const selectedGenre = ref(props.filters.genre || '');
const selectedResolution = ref(props.filters.resolution || '');
const selectedSort = ref(props.filters.sort || 'rating');
const selectedVibe = ref(props.filters.vibe || '');
const favoriteOnly = ref(props.filters.favorite_only === '1' || props.filters.favorite_only === 1 || props.filters.favorite_only === true);

const hasActiveFilters = computed(() => {
    return !!(selectedGenre.value || selectedResolution.value || (selectedSort.value && selectedSort.value !== 'rating') || selectedVibe.value || favoriteOnly.value);
});

const applyFilters = () => {
    router.get(window.location.pathname, {
        ...props.filters,
        genre: selectedGenre.value || undefined,
        resolution: selectedResolution.value || undefined,
        sort: selectedSort.value || undefined,
        vibe: selectedVibe.value || undefined,
        favorite_only: favoriteOnly.value ? 1 : undefined,
    }, { preserveState: true, preserveScroll: true });
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
    selectedGenre.value = '';
    selectedResolution.value = '';
    selectedSort.value = 'rating';
    selectedVibe.value = '';
    favoriteOnly.value = false;
    router.get(window.location.pathname, {}, { preserveState: true, preserveScroll: true });
};

const curatedVibes = [
    { id: 'Mind-Bending', label_en: 'Mind-Bending Sci-Fi', label_ar: 'خيال علمي مشوق' },
    { id: 'Adrenaline', label_en: 'Adrenaline Rush', label_ar: 'أكشن وإثارة' },
    { id: 'Late-Night Noir', label_en: 'Late-Night Noir', label_ar: 'غموض وجريمة' },
    { id: 'Emotional Resonance', label_en: 'Emotional Drama', label_ar: 'دراما مؤثرة' },
    { id: 'Epic Worlds', label_en: 'Epic Worlds', label_ar: 'مغامرة وفانتازيا' },
];
</script>

<template>
    <div class="glass-panel rounded-3xl p-5 mb-8 border border-slate-200 dark:border-white/10 space-y-4 shadow-sm relative overflow-hidden">
        <!-- 1. Genres Pill Carousel -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400">
                    <Layers class="w-3.5 h-3.5 text-cyan-600 dark:text-cyan-400" />
                    <span>{{ t('common.genres') }}</span>
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
        <div class="space-y-2 pt-2 border-t border-slate-200 dark:border-white/5">
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

        <!-- 3. Quality & Sorting Interactive Segmented Controls (No Dropdowns!) -->
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
