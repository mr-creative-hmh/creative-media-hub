<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { formatEpisodeTitle, formatSeasonEpisodeTitle } from '@/lib/mediaTitle';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/components/layout/AppLayout.vue';
import WatchHistoryCard from '@/components/media/WatchHistoryCard.vue';
import {
    History,
    Clock,
    Play,
    Trash2,
    X,
    Film,
    Tv,
    Layers,
    Search,
    AlertTriangle,
    CheckCircle2,
    ChevronRight,
    ChevronLeft,
    Sparkles,
    Gamepad2,
} from 'lucide-vue-next';

const { t, isRTL } = useI18n();
const toast = useToast();

const displayItemTitle = (item: any): string => {
    if (item.type === 'episode' || item.category === 'series') {
        const series =
            isRTL.value && item.series_title_ar
                ? item.series_title_ar
                : item.series_title || item.title;
        const ep = formatSeasonEpisodeTitle(item, { isRTL: isRTL.value });
        return `${series} - ${ep}`;
    }
    return isRTL.value && item.title_ar ? item.title_ar : item.title || '';
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
const clearTargetCategory = ref<'all' | 'movie' | 'series' | 'collection'>(
    'all',
);
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
        list = list.filter(
            (i) => i.watchable_type === 'movie' || i.type === 'movie',
        );
    } else if (activeTab.value === 'series') {
        list = list.filter(
            (i) => i.watchable_type === 'episode' || i.type === 'episode',
        );
    } else if (activeTab.value === 'collection') {
        list = list.filter(
            (i) => i.category === 'collection' || !!i.collection_name,
        );
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter((i) => {
            const titleEn = (i.title || '').toLowerCase();
            const titleAr = (i.title_ar || '').toLowerCase();
            const seriesEn = (i.series_title || '').toLowerCase();
            const seriesAr = (i.series_title_ar || '').toLowerCase();
            const colName = (i.collection_name || '').toLowerCase();
            return (
                titleEn.includes(q) ||
                titleAr.includes(q) ||
                seriesEn.includes(q) ||
                seriesAr.includes(q) ||
                colName.includes(q)
            );
        });
    }

    return list;
});

// Categorized subsets for the 'All' multi-section view
const moviesList = computed(() => {
    return filteredItems.value.filter(
        (i) =>
            (i.watchable_type === 'movie' || i.type === 'movie') &&
            !i.collection_name,
    );
});

const collectionsList = computed(() => {
    return filteredItems.value.filter(
        (i) => i.category === 'collection' || !!i.collection_name,
    );
});

const seriesList = computed(() => {
    return filteredItems.value.filter(
        (i) => i.watchable_type === 'episode' || i.type === 'episode',
    );
});

// Remove individual item
const removeItem = async (item: any, e?: MouseEvent) => {
    if (e) e.stopPropagation();
    const itemId = item.history_id || item.watchable_id || item.id;
    if (removingIds.value.has(itemId)) return;

    const previousItems = [...allItems.value];
    const seriesId = item.series_id || item.series?.id || null;
    const watchableId = item.watchable_id || item.id;
    const isSeries =
        item.category === 'series' ||
        item.type === 'episode' ||
        item.watchable_type === 'episode';
    const watchableType =
        item.watchable_type || (isSeries ? 'episode' : 'movie');

    removingIds.value.add(itemId);

    // Optimistic removal from local list (for series, remove any cards with same seriesId or itemId)
    allItems.value = allItems.value.filter((i) => {
        const idMatches = (i.history_id || i.watchable_id || i.id) === itemId;
        const seriesMatches =
            seriesId && (i.series_id || i.series?.id) === seriesId;
        return !idMatches && !seriesMatches;
    });

    // Recompute counts
    updateCountsLocally();

    try {
        const params = new URLSearchParams({
            type: item.category || item.type || (isSeries ? 'series' : 'movie'),
            ...(watchableId ? { watchable_id: String(watchableId) } : {}),
            ...(watchableType ? { watchable_type: String(watchableType) } : {}),
            ...(seriesId
                ? { series_id: String(seriesId), is_series: '1' }
                : {}),
        });

        const res = await fetch(
            `/api/watch-history/${itemId}?${params.toString()}`,
            {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as any
                        )?.content || '',
                },
            },
        );

        if (!res.ok) {
            throw new Error(`Failed to remove item: HTTP ${res.status}`);
        }

        toast.success(t('watch_history.item_removed'));
    } catch (err) {
        console.error('Failed to remove item', err);
        // Rollback optimistic removal
        allItems.value = previousItems;
        updateCountsLocally();

        toast.error(
            t('watch_history.remove_failed') ||
                'Failed to remove item from watch history',
        );
    } finally {
        removingIds.value.delete(itemId);
    }
};

// Open clear confirmation modal
const openClearModal = (
    category: 'all' | 'movie' | 'series' | 'collection' = 'all',
) => {
    clearTargetCategory.value = category;
    isClearModalOpen.value = true;
};

// Execute clear all
const executeClear = async () => {
    isClearing.value = true;
    const cat = clearTargetCategory.value;
    const previousItems = [...allItems.value];
    const previousCounts = { ...counts.value };

    // Optimistically update
    if (cat === 'all') {
        allItems.value = [];
        counts.value = { all: 0, movies: 0, series: 0, collections: 0 };
    } else if (cat === 'movie') {
        allItems.value = allItems.value.filter(
            (i) => i.type !== 'movie' && i.watchable_type !== 'movie',
        );
    } else if (cat === 'series') {
        allItems.value = allItems.value.filter(
            (i) => i.type !== 'episode' && i.watchable_type !== 'episode',
        );
    } else if (cat === 'collection') {
        allItems.value = allItems.value.filter((i) => !i.collection_name);
    }
    updateCountsLocally();

    try {
        const res = await fetch(`/api/watch-history?type=${cat}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
        });

        if (!res.ok) {
            throw new Error(
                `Failed to clear watch history: HTTP ${res.status}`,
            );
        }

        toast.success(t('watch_history.cleared_success'));
    } catch (err) {
        console.error('Failed to clear watch history', err);
        // Rollback on failure
        allItems.value = previousItems;
        counts.value = previousCounts;
        updateCountsLocally();

        toast.error(
            t('watch_history.clear_failed') || 'Failed to clear watch history',
        );
    } finally {
        isClearing.value = false;
        isClearModalOpen.value = false;
    }
};

const updateCountsLocally = () => {
    const total = allItems.value.length;
    const m = allItems.value.filter(
        (i) => i.watchable_type === 'movie' || i.type === 'movie',
    ).length;
    const s = allItems.value.filter(
        (i) => i.watchable_type === 'episode' || i.type === 'episode',
    ).length;
    const c = allItems.value.filter(
        (i) => i.category === 'collection' || !!i.collection_name,
    ).length;
    counts.value = { all: total, movies: m, series: s, collections: c };
};

const isBandersnatchItem = (item: any) => {
    return (
        item?.id === 5764 ||
        item?.watchable_id === 5764 ||
        (item?.title && /bandersnatch/i.test(item.title))
    );
};

const playBandersnatch = (item: any, e: MouseEvent) => {
    e.stopPropagation();
    window.dispatchEvent(
        new CustomEvent('play-bandersnatch', { detail: item }),
    );
};

onMounted(() => {
    fetchWatchHistory();
});
</script>

<template>
    <AppLayout v-slot="{ play }">
        <Head :title="t('watch_history.title')" />
        <div class="space-y-8 pb-16">
            <!-- 1. Header Banner -->
            <div
                class="glass-panel relative overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-r from-slate-900/90 via-slate-900/60 to-cyan-950/40 p-6 shadow-xl sm:p-8 dark:border-white/10"
            >
                <div
                    class="ambient-glow pointer-events-none -top-32 -right-32 h-96 w-96 bg-cyan-500/10"
                ></div>

                <div
                    class="relative z-10 flex flex-col justify-between gap-6 md:flex-row md:items-center"
                >
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-400 shadow-lg shadow-cyan-500/10"
                            >
                                <History class="h-6 w-6" />
                            </div>
                            <div>
                                <h1
                                    class="flex items-center gap-3 font-sans text-2xl font-black tracking-tight text-slate-900 sm:text-3xl dark:text-white"
                                >
                                    <span>{{ t('watch_history.title') }}</span>
                                    <span
                                        class="rounded-full border border-cyan-500/30 bg-cyan-500/20 px-2.5 py-0.5 font-mono text-xs font-bold text-cyan-400"
                                    >
                                        {{ counts.all }}
                                    </span>
                                </h1>
                                <p
                                    class="text-xs font-medium text-slate-500 sm:text-sm dark:text-slate-400"
                                >
                                    {{ t('watch_history.subtitle') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Clear All Trigger Button -->
                    <div v-if="counts.all > 0" class="flex items-center gap-3">
                        <button
                            @click="openClearModal('all')"
                            class="flex cursor-pointer items-center gap-2 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-2.5 text-xs font-bold text-rose-400 shadow-sm transition-all hover:bg-rose-500/20 hover:text-rose-300 active:scale-95"
                        >
                            <Trash2 class="h-4 w-4" />
                            <span>{{ t('watch_history.clear_all') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 2. Controls & Filter Bar -->
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <!-- Category Tabs -->
                <div
                    class="glass-panel custom-scrollbar flex items-center gap-1.5 overflow-x-auto rounded-2xl border border-slate-200 p-1 dark:border-white/10"
                >
                    <button
                        @click="activeTab = 'all'"
                        class="flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold whitespace-nowrap transition-all active:scale-95"
                        :class="
                            activeTab === 'all'
                                ? 'bg-cyan-500 font-black text-slate-950 shadow-md shadow-cyan-500/20'
                                : 'text-slate-600 hover:bg-white/5 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                        "
                    >
                        <span>{{ t('watch_history.all') }}</span>
                        <span
                            class="py-0.2 rounded-full px-1.5 font-mono text-[10px]"
                            :class="
                                activeTab === 'all'
                                    ? 'bg-slate-950/20 font-black text-slate-950'
                                    : 'bg-white/10 text-slate-400'
                            "
                        >
                            {{ counts.all }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'movie'"
                        class="flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold whitespace-nowrap transition-all active:scale-95"
                        :class="
                            activeTab === 'movie'
                                ? 'bg-cyan-500 font-black text-slate-950 shadow-md shadow-cyan-500/20'
                                : 'text-slate-600 hover:bg-white/5 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                        "
                    >
                        <Film class="h-3.5 w-3.5" />
                        <span>{{ t('watch_history.movies') }}</span>
                        <span
                            class="py-0.2 rounded-full px-1.5 font-mono text-[10px]"
                            :class="
                                activeTab === 'movie'
                                    ? 'bg-slate-950/20 font-black text-slate-950'
                                    : 'bg-white/10 text-slate-400'
                            "
                        >
                            {{ counts.movies }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'series'"
                        class="flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold whitespace-nowrap transition-all active:scale-95"
                        :class="
                            activeTab === 'series'
                                ? 'bg-indigo-600 font-black text-white shadow-md shadow-indigo-600/20'
                                : 'text-slate-600 hover:bg-white/5 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                        "
                    >
                        <Tv class="h-3.5 w-3.5" />
                        <span>{{ t('watch_history.series') }}</span>
                        <span
                            class="py-0.2 rounded-full px-1.5 font-mono text-[10px]"
                            :class="
                                activeTab === 'series'
                                    ? 'bg-white/20 font-black text-white'
                                    : 'bg-white/10 text-slate-400'
                            "
                        >
                            {{ counts.series }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'collection'"
                        class="flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold whitespace-nowrap transition-all active:scale-95"
                        :class="
                            activeTab === 'collection'
                                ? 'bg-amber-500 font-black text-slate-950 shadow-md shadow-amber-500/20'
                                : 'text-slate-600 hover:bg-white/5 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                        "
                    >
                        <Layers class="h-3.5 w-3.5" />
                        <span>{{ t('watch_history.collections') }}</span>
                        <span
                            class="py-0.2 rounded-full px-1.5 font-mono text-[10px]"
                            :class="
                                activeTab === 'collection'
                                    ? 'bg-slate-950/20 font-black text-slate-950'
                                    : 'bg-white/10 text-slate-400'
                            "
                        >
                            {{ counts.collections }}
                        </span>
                    </button>
                </div>

                <!-- Search Filter Input -->
                <div class="relative w-full sm:w-72">
                    <Search
                        class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        type="text"
                        v-model="searchQuery"
                        :placeholder="t('watch_history.search_placeholder')"
                        class="h-10 w-full rounded-2xl border border-slate-200 bg-white/[0.04] pr-4 pl-10 font-sans text-xs text-slate-900 transition-all placeholder:text-slate-500 focus:border-cyan-500 focus:outline-none dark:border-white/10 dark:text-white"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute top-1/2 right-3 -translate-y-1/2 cursor-pointer p-1 text-slate-400 hover:text-white"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>

            <!-- Loading Skeleton -->
            <div
                v-if="isLoading"
                class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4"
            >
                <div
                    v-for="n in 6"
                    :key="n"
                    class="glass-panel animate-pulse space-y-3 overflow-hidden rounded-2xl p-3"
                >
                    <div class="aspect-video rounded-xl bg-white/5"></div>
                    <div class="h-4 w-3/4 rounded bg-white/10"></div>
                    <div class="h-3 w-1/2 rounded bg-white/5"></div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else-if="filteredItems.length === 0"
                class="glass-panel mx-auto mt-8 max-w-lg space-y-4 rounded-3xl border border-slate-200 p-12 text-center dark:border-white/10"
            >
                <div
                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-cyan-500/10 text-cyan-400"
                >
                    <Clock class="h-8 w-8" />
                </div>
                <h3
                    class="text-lg font-extrabold text-slate-900 dark:text-white"
                >
                    {{ t('watch_history.empty_title') }}
                </h3>
                <p
                    class="mx-auto max-w-sm text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                >
                    {{
                        searchQuery
                            ? t('watch_history.empty_filter_desc')
                            : t('watch_history.empty_desc')
                    }}
                </p>
                <div v-if="searchQuery" class="pt-2">
                    <button
                        @click="searchQuery = ''"
                        class="cursor-pointer rounded-xl bg-cyan-500 px-4 py-2 text-xs font-bold text-slate-950 transition-all hover:bg-cyan-400"
                    >
                        {{ t('common.clear_filters') }}
                    </button>
                </div>
            </div>

            <!-- 3. Categorized Sections (when activeTab is 'all' and no active search filter) -->
            <div
                v-else-if="activeTab === 'all' && !searchQuery"
                class="space-y-12"
            >
                <!-- Section A: Movies Watch History -->
                <div v-if="moviesList.length > 0" class="space-y-4">
                    <div
                        class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-white/10"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/20 bg-cyan-500/10 text-cyan-400"
                            >
                                <Film class="h-4 w-4" />
                            </div>
                            <h2
                                class="text-lg font-black tracking-wide text-slate-900 dark:text-white"
                            >
                                {{ t('watch_history.movies_section') }}
                            </h2>
                            <span
                                class="rounded-full border border-cyan-500/20 bg-cyan-500/10 px-2 py-0.5 font-mono text-xs font-bold text-cyan-400"
                            >
                                {{ moviesList.length }}
                            </span>
                        </div>

                        <button
                            @click="openClearModal('movie')"
                            class="flex cursor-pointer items-center gap-1 text-xs font-bold text-slate-400 transition-colors hover:text-rose-400"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            <span>{{
                                t('watch_history.clear_category', {
                                    category: t('watch_history.movies'),
                                })
                            }}</span>
                        </button>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4"
                    >
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
                    <div
                        class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-white/10"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10 text-amber-400"
                            >
                                <Layers class="h-4 w-4" />
                            </div>
                            <h2
                                class="text-lg font-black tracking-wide text-slate-900 dark:text-white"
                            >
                                {{ t('watch_history.collections_section') }}
                            </h2>
                            <span
                                class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 font-mono text-xs font-bold text-amber-400"
                            >
                                {{ collectionsList.length }}
                            </span>
                        </div>

                        <button
                            @click="openClearModal('collection')"
                            class="flex cursor-pointer items-center gap-1 text-xs font-bold text-slate-400 transition-colors hover:text-rose-400"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            <span>{{
                                t('watch_history.clear_category', {
                                    category: t('watch_history.collections'),
                                })
                            }}</span>
                        </button>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4"
                    >
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
                    <div
                        class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-white/10"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-xl border border-indigo-500/20 bg-indigo-500/10 text-indigo-400"
                            >
                                <Tv class="h-4 w-4" />
                            </div>
                            <h2
                                class="text-lg font-black tracking-wide text-slate-900 dark:text-white"
                            >
                                {{ t('watch_history.series_section') }}
                            </h2>
                            <span
                                class="rounded-full border border-indigo-500/20 bg-indigo-500/10 px-2 py-0.5 font-mono text-xs font-bold text-indigo-400"
                            >
                                {{ seriesList.length }}
                            </span>
                        </div>

                        <button
                            @click="openClearModal('series')"
                            class="flex cursor-pointer items-center gap-1 text-xs font-bold text-slate-400 transition-colors hover:text-rose-400"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            <span>{{
                                t('watch_history.clear_category', {
                                    category: t('watch_history.series'),
                                })
                            }}</span>
                        </button>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4"
                    >
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
                <div
                    class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-white/10"
                >
                    <span
                        class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                    >
                        {{
                            t('common.showing_results', {
                                from: 1,
                                to: filteredItems.length,
                                total: filteredItems.length,
                            })
                        }}
                    </span>

                    <button
                        v-if="activeTab !== 'all'"
                        @click="openClearModal(activeTab)"
                        class="flex cursor-pointer items-center gap-1 text-xs font-bold text-slate-400 transition-colors hover:text-rose-400"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                        <span>{{
                            t('watch_history.clear_category', {
                                category: t(
                                    `watch_history.${activeTab === 'movie' ? 'movies' : activeTab === 'series' ? 'series' : 'collections'}`,
                                ),
                            })
                        }}</span>
                    </button>
                </div>

                <div
                    class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4"
                >
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
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-md"
            @click.self="isClearModalOpen = false"
        >
            <div
                class="glass-panel animate-in fade-in zoom-in w-full max-w-md space-y-5 rounded-3xl border border-rose-500/30 bg-[#0B0F17] p-6 shadow-2xl duration-200"
            >
                <div class="flex items-center gap-3.5">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-rose-500/30 bg-rose-500/10 text-rose-500"
                    >
                        <AlertTriangle class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-white">
                            {{ t('watch_history.confirm_clear_title') }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ t('watch_history.confirm_clear_desc') }}
                        </p>
                    </div>
                </div>

                <div
                    class="flex items-center justify-end gap-3 border-t border-white/10 pt-3"
                >
                    <button
                        @click="isClearModalOpen = false"
                        class="cursor-pointer rounded-xl px-4 py-2 text-xs font-bold text-slate-300 transition-all hover:bg-white/10 hover:text-white"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        @click="executeClear"
                        :disabled="isClearing"
                        class="flex cursor-pointer items-center gap-2 rounded-xl bg-rose-600 px-5 py-2.5 text-xs font-black text-white shadow-lg shadow-rose-600/30 transition-all hover:bg-rose-500 active:scale-95 disabled:opacity-50"
                    >
                        <Trash2 class="h-4 w-4" />
                        <span>{{
                            isClearing
                                ? '...'
                                : t('watch_history.clear_confirm_btn')
                        }}</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
