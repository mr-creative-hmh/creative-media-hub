<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { formatEpisodeTitle, formatSeasonEpisodeTitle } from '@/lib/mediaTitle';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import WatchHistoryCard from '@/components/media/WatchHistoryCard.vue';
import {
    History, Clock, Play, Trash2, X, Film, Tv, Layers,
    Search, AlertTriangle, CheckCircle2, ChevronRight, ChevronLeft, Sparkles, Gamepad2
} from 'lucide-vue-next';

const { t, isRTL } = useI18n();

const displayItemTitle = (item: any): string => {
    if (item.type === 'episode' || item.category === 'series') {
        const series = (isRTL.value && item.series_title_ar) ? item.series_title_ar : (item.series_title || item.title);
        const ep = formatSeasonEpisodeTitle(item, { isRTL: isRTL.value });
        return `${series} - ${ep}`;
    }
    return (isRTL.value && item.title_ar) ? item.title_ar : (item.title || '');
};

const activeTab = ref<'all' | 'movie' | 'series' | 'collection'>('all');
const searchQuery = ref('');
const isLoading = ref(true);

const allItems = ref<any[]>([]);
const counts = ref({
    all: 0,
    movies: 0,
    series: 0,
    collections: 0,
});

// Confirmation Modal State
const isClearModalOpen = ref(false);
const clearTargetCategory = ref<'all' | 'movie' | 'series' | 'collection'>('all');
const isClearing = ref(false);
const removingIds = ref<Set<number>>(new Set());

const fetchWatchHistory = async () => {
    isLoading.value = true;
    try {
        const res = await fetch('/api/watch-history?type=all');
        if (res.ok) {
            const data = await res.json();
            allItems.value = data.items || [];
            if (data.counts) {
                counts.value = data.counts;
            }
        }
    } catch (e) {
        console.error('Failed to fetch watch history', e);
    } finally {
        isLoading.value = false;
    }
};

// Filtered list based on search and active tab
const filteredItems = computed(() => {
    let list = allItems.value;

    if (activeTab.value === 'movie') {
        list = list.filter((i) => i.watchable_type === 'movie' || i.type === 'movie');
    } else if (activeTab.value === 'series') {
        list = list.filter((i) => i.watchable_type === 'episode' || i.type === 'episode');
    } else if (activeTab.value === 'collection') {
        list = list.filter((i) => i.category === 'collection' || !!i.collection_name);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter((i) => {
            const titleEn = (i.title || '').toLowerCase();
            const titleAr = (i.title_ar || '').toLowerCase();
            const seriesEn = (i.series_title || '').toLowerCase();
            const seriesAr = (i.series_title_ar || '').toLowerCase();
            const colName = (i.collection_name || '').toLowerCase();
            return titleEn.includes(q) || titleAr.includes(q) || seriesEn.includes(q) || seriesAr.includes(q) || colName.includes(q);
        });
    }

    return list;
});

// Categorized subsets for the 'All' multi-section view
const moviesList = computed(() => {
    return filteredItems.value.filter((i) => (i.watchable_type === 'movie' || i.type === 'movie') && (!i.collection_name));
});

const collectionsList = computed(() => {
    return filteredItems.value.filter((i) => (i.category === 'collection' || !!i.collection_name));
});

const seriesList = computed(() => {
    return filteredItems.value.filter((i) => i.watchable_type === 'episode' || i.type === 'episode');
});

// Remove individual item
const removeItem = async (item: any, e?: MouseEvent) => {
    if (e) e.stopPropagation();
    const itemId = item.history_id || item.id;
    if (removingIds.value.has(itemId)) return;

    removingIds.value.add(itemId);

    // Optimistic removal from local list
    allItems.value = allItems.value.filter((i) => (i.history_id || i.id) !== itemId);

    // Recompute counts
    updateCountsLocally();

    try {
        await fetch(`/api/watch-history/${itemId}?type=${item.category || item.type}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });

        window.dispatchEvent(new CustomEvent('app-toast', {
            detail: {
                type: 'success',
                message: t('watch_history.item_removed'),
            }
        }));
    } catch (err) {
        console.error('Failed to remove item', err);
    } finally {
        removingIds.value.delete(itemId);
    }
};

// Open clear confirmation modal
const openClearModal = (category: 'all' | 'movie' | 'series' | 'collection' = 'all') => {
    clearTargetCategory.value = category;
    isClearModalOpen.value = true;
};

// Execute clear all
const executeClear = async () => {
    isClearing.value = true;
    try {
        const cat = clearTargetCategory.value;
        const res = await fetch(`/api/watch-history?type=${cat}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });

        if (res.ok) {
            if (cat === 'all') {
                allItems.value = [];
                counts.value = { all: 0, movies: 0, series: 0, collections: 0 };
            } else if (cat === 'movie') {
                allItems.value = allItems.value.filter((i) => i.type !== 'movie');
            } else if (cat === 'series') {
                allItems.value = allItems.value.filter((i) => i.type !== 'episode');
            } else if (cat === 'collection') {
                allItems.value = allItems.value.filter((i) => !i.collection_name);
            }
            updateCountsLocally();

            window.dispatchEvent(new CustomEvent('app-toast', {
                detail: {
                    type: 'success',
                    message: t('watch_history.cleared_success'),
                }
            }));
        }
    } catch (err) {
        console.error('Failed to clear watch history', err);
    } finally {
        isClearing.value = false;
        isClearModalOpen.value = false;
    }
};

const updateCountsLocally = () => {
    const total = allItems.value.length;
    const m = allItems.value.filter((i) => i.watchable_type === 'movie' || i.type === 'movie').length;
    const s = allItems.value.filter((i) => i.watchable_type === 'episode' || i.type === 'episode').length;
    const c = allItems.value.filter((i) => i.category === 'collection' || !!i.collection_name).length;
    counts.value = { all: total, movies: m, series: s, collections: c };
};

const isBandersnatchItem = (item: any) => {
    return item?.id === 5764 ||
        item?.watchable_id === 5764 ||
        (item?.title && /bandersnatch/i.test(item.title));
};

const playBandersnatch = (item: any, e: MouseEvent) => {
    e.stopPropagation();
    window.dispatchEvent(new CustomEvent('play-bandersnatch', { detail: item }));
};

onMounted(() => {
    fetchWatchHistory();
});
</script>

<template>
    <Head :title="t('watch_history.title')" />

    <AppLayout v-slot="{ play }">
        <div class="space-y-8 pb-16">
            <!-- 1. Header Banner -->
            <div class="relative overflow-hidden rounded-3xl p-6 sm:p-8 glass-panel border border-slate-200 dark:border-white/10 bg-gradient-to-r from-slate-900/90 via-slate-900/60 to-cyan-950/40 shadow-xl">
                <div class="ambient-glow bg-cyan-500/10 w-96 h-96 -top-32 -right-32 pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 flex items-center justify-center shadow-lg shadow-cyan-500/10">
                                <History class="w-6 h-6" />
                            </div>
                            <div>
                                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight font-sans flex items-center gap-3">
                                    <span>{{ t('watch_history.title') }}</span>
                                    <span class="text-xs font-mono font-bold px-2.5 py-0.5 rounded-full bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                                        {{ counts.all }}
                                    </span>
                                </h1>
                                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-medium">
                                    {{ t('watch_history.subtitle') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Clear All Trigger Button -->
                    <div v-if="counts.all > 0" class="flex items-center gap-3">
                        <button
                            @click="openClearModal('all')"
                            class="px-4 py-2.5 rounded-2xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-rose-500/30 transition-all font-bold text-xs flex items-center gap-2 active:scale-95 cursor-pointer shadow-sm"
                        >
                            <Trash2 class="w-4 h-4" />
                            <span>{{ t('watch_history.clear_all') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 2. Controls & Filter Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <!-- Category Tabs -->
                <div class="flex items-center gap-1.5 p-1 rounded-2xl glass-panel border border-slate-200 dark:border-white/10 overflow-x-auto custom-scrollbar">
                    <button
                        @click="activeTab = 'all'"
                        class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer active:scale-95"
                        :class="activeTab === 'all'
                            ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20 font-black'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/5'"
                    >
                        <span>{{ t('watch_history.all') }}</span>
                        <span class="text-[10px] font-mono px-1.5 py-0.2 rounded-full" :class="activeTab === 'all' ? 'bg-slate-950/20 text-slate-950 font-black' : 'bg-white/10 text-slate-400'">
                            {{ counts.all }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'movie'"
                        class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer active:scale-95"
                        :class="activeTab === 'movie'
                            ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20 font-black'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/5'"
                    >
                        <Film class="w-3.5 h-3.5" />
                        <span>{{ t('watch_history.movies') }}</span>
                        <span class="text-[10px] font-mono px-1.5 py-0.2 rounded-full" :class="activeTab === 'movie' ? 'bg-slate-950/20 text-slate-950 font-black' : 'bg-white/10 text-slate-400'">
                            {{ counts.movies }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'series'"
                        class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer active:scale-95"
                        :class="activeTab === 'series'
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20 font-black'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/5'"
                    >
                        <Tv class="w-3.5 h-3.5" />
                        <span>{{ t('watch_history.series') }}</span>
                        <span class="text-[10px] font-mono px-1.5 py-0.2 rounded-full" :class="activeTab === 'series' ? 'bg-white/20 text-white font-black' : 'bg-white/10 text-slate-400'">
                            {{ counts.series }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'collection'"
                        class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer active:scale-95"
                        :class="activeTab === 'collection'
                            ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/5'"
                    >
                        <Layers class="w-3.5 h-3.5" />
                        <span>{{ t('watch_history.collections') }}</span>
                        <span class="text-[10px] font-mono px-1.5 py-0.2 rounded-full" :class="activeTab === 'collection' ? 'bg-slate-950/20 text-slate-950 font-black' : 'bg-white/10 text-slate-400'">
                            {{ counts.collections }}
                        </span>
                    </button>
                </div>

                <!-- Search Filter Input -->
                <div class="relative w-full sm:w-72">
                    <Search class="absolute top-1/2 -translate-y-1/2 left-3.5 w-4 h-4 text-slate-400" />
                    <input
                        type="text"
                        v-model="searchQuery"
                        :placeholder="t('watch_history.search_placeholder')"
                        class="w-full h-10 rounded-2xl bg-white/[0.04] border border-slate-200 dark:border-white/10 pl-10 pr-4 text-xs text-slate-900 dark:text-white placeholder:text-slate-500 focus:outline-none focus:border-cyan-500 transition-all font-sans"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute top-1/2 -translate-y-1/2 right-3 p-1 text-slate-400 hover:text-white cursor-pointer"
                    >
                        <X class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            <!-- Loading Skeleton -->
            <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6">
                <div v-for="n in 6" :key="n" class="glass-panel rounded-2xl overflow-hidden animate-pulse space-y-3 p-3">
                    <div class="aspect-video bg-white/5 rounded-xl"></div>
                    <div class="h-4 bg-white/10 rounded w-3/4"></div>
                    <div class="h-3 bg-white/5 rounded w-1/2"></div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else-if="filteredItems.length === 0"
                class="glass-panel rounded-3xl p-12 text-center border border-slate-200 dark:border-white/10 space-y-4 max-w-lg mx-auto mt-8"
            >
                <div class="w-16 h-16 rounded-3xl bg-cyan-500/10 text-cyan-400 mx-auto flex items-center justify-center">
                    <Clock class="w-8 h-8" />
                </div>
                <h3 class="font-extrabold text-lg text-slate-900 dark:text-white">
                    {{ t('watch_history.empty_title') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto leading-relaxed">
                    {{ searchQuery ? t('watch_history.empty_filter_desc') : t('watch_history.empty_desc') }}
                </p>
                <div v-if="searchQuery" class="pt-2">
                    <button
                        @click="searchQuery = ''"
                        class="px-4 py-2 rounded-xl bg-cyan-500 text-slate-950 text-xs font-bold hover:bg-cyan-400 transition-all cursor-pointer"
                    >
                        {{ t('common.clear_filters') }}
                    </button>
                </div>
            </div>

            <!-- 3. Categorized Sections (when activeTab is 'all' and no active search filter) -->
            <div v-else-if="activeTab === 'all' && !searchQuery" class="space-y-12">
                <!-- Section A: Movies Watch History -->
                <div v-if="moviesList.length > 0" class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-white/10 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center border border-cyan-500/20">
                                <Film class="w-4 h-4" />
                            </div>
                            <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-wide">
                                {{ t('watch_history.movies_section') }}
                            </h2>
                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                                {{ moviesList.length }}
                            </span>
                        </div>

                        <button
                            @click="openClearModal('movie')"
                            class="text-xs font-bold text-slate-400 hover:text-rose-400 transition-colors flex items-center gap-1 cursor-pointer"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                            <span>{{ t('watch_history.clear_category', { category: t('watch_history.movies') }) }}</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6">
                        <WatchHistoryCard
                            v-for="item in moviesList"
                            :key="`${item.watchable_type}_${item.watchable_id}`"
                            :item="item"
                            @play="play"
                            @remove="removeItem"
                            @play-interactive="playBandersnatch"
                        />
                    </div>
                </div>

                <!-- Section B: Collections Watch History -->
                <div v-if="collectionsList.length > 0" class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-white/10 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center border border-amber-500/20">
                                <Layers class="w-4 h-4" />
                            </div>
                            <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-wide">
                                {{ t('watch_history.collections_section') }}
                            </h2>
                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                {{ collectionsList.length }}
                            </span>
                        </div>

                        <button
                            @click="openClearModal('collection')"
                            class="text-xs font-bold text-slate-400 hover:text-rose-400 transition-colors flex items-center gap-1 cursor-pointer"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                            <span>{{ t('watch_history.clear_category', { category: t('watch_history.collections') }) }}</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6">
                        <WatchHistoryCard
                            v-for="item in collectionsList"
                            :key="`${item.watchable_type}_${item.watchable_id}`"
                            :item="item"
                            @play="play"
                            @remove="removeItem"
                            @play-interactive="playBandersnatch"
                        />
                    </div>
                </div>

                <!-- Section C: Series Watch History -->
                <div v-if="seriesList.length > 0" class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-white/10 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center border border-indigo-500/20">
                                <Tv class="w-4 h-4" />
                            </div>
                            <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-wide">
                                {{ t('watch_history.series_section') }}
                            </h2>
                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                {{ seriesList.length }}
                            </span>
                        </div>

                        <button
                            @click="openClearModal('series')"
                            class="text-xs font-bold text-slate-400 hover:text-rose-400 transition-colors flex items-center gap-1 cursor-pointer"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                            <span>{{ t('watch_history.clear_category', { category: t('watch_history.series') }) }}</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6">
                        <WatchHistoryCard
                            v-for="item in seriesList"
                            :key="`${item.watchable_type}_${item.watchable_id}`"
                            :item="item"
                            @play="play"
                            @remove="removeItem"
                            @play-interactive="playBandersnatch"
                        />
                    </div>
                </div>
            </div>

            <!-- 4. Single-Category / Search Results Grid -->
            <div v-else class="space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-white/10 pb-3">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                        {{ t('common.showing_results', { from: 1, to: filteredItems.length, total: filteredItems.length }) }}
                    </span>

                    <button
                        v-if="activeTab !== 'all'"
                        @click="openClearModal(activeTab)"
                        class="text-xs font-bold text-slate-400 hover:text-rose-400 transition-colors flex items-center gap-1 cursor-pointer"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        <span>{{ t('watch_history.clear_category', { category: t(`watch_history.${activeTab === 'movie' ? 'movies' : (activeTab === 'series' ? 'series' : 'collections')}`) }) }}</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6">
                    <WatchHistoryCard
                        v-for="item in filteredItems"
                        :key="`${item.watchable_type}_${item.watchable_id}`"
                        :item="item"
                        @play="play"
                        @remove="removeItem"
                        @play-interactive="playBandersnatch"
                    />
                </div>
            </div>
        </div>

        <!-- Confirmation Modal for Clear All / Clear Category -->
        <div
            v-if="isClearModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
            @click.self="isClearModalOpen = false"
        >
            <div class="glass-panel w-full max-w-md rounded-3xl p-6 border border-rose-500/30 bg-[#0B0F17] shadow-2xl space-y-5 animate-in fade-in zoom-in duration-200">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center shrink-0 border border-rose-500/30">
                        <AlertTriangle class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-white">
                            {{ t('watch_history.confirm_clear_title') }}
                        </h3>
                        <p class="text-xs text-slate-400 mt-1">
                            {{ t('watch_history.confirm_clear_desc') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                    <button
                        @click="isClearModalOpen = false"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-300 hover:text-white hover:bg-white/10 transition-all cursor-pointer"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        @click="executeClear"
                        :disabled="isClearing"
                        class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-black transition-all flex items-center gap-2 active:scale-95 shadow-lg shadow-rose-600/30 cursor-pointer disabled:opacity-50"
                    >
                        <Trash2 class="w-4 h-4" />
                        <span>{{ isClearing ? '...' : t('watch_history.clear_confirm_btn') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
