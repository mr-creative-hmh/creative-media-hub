<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/components/layout/AppLayout.vue';
import PageBanner from '@/components/common/PageBanner.vue';
import FilterPills, {
    type FilterPillOption,
} from '@/components/common/FilterPills.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';
import MotionList from '@/components/motion/MotionList.vue';
import CinemaSkeleton from '@/components/common/skeletons/CinemaSkeleton.vue';
import WatchHistoryCard from '@/components/media/WatchHistoryCard.vue';
import {
    History,
    Clock,
    Trash2,
    X,
    Film,
    Tv,
    Layers,
    Search,
} from '@lucide/vue';

const { t, isRTL } = useI18n();
const toast = useToast();

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

const filterPillOptions = computed<FilterPillOption[]>(() => [
    {
        value: 'all',
        label: t('watch_history.all'),
        count: counts.value.all,
    },
    {
        value: 'movie',
        label: t('watch_history.movies'),
        count: counts.value.movies,
    },
    {
        value: 'series',
        label: t('watch_history.series'),
        count: counts.value.series,
    },
    {
        value: 'collection',
        label: t('watch_history.collections'),
        count: counts.value.collections,
    },
]);

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

    // Optimistic removal from local list
    allItems.value = allItems.value.filter((i) => {
        const idMatches = (i.history_id || i.watchable_id || i.id) === itemId;
        const seriesMatches =
            seriesId && (i.series_id || i.series?.id) === seriesId;
        return !idMatches && !seriesMatches;
    });

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
            <!-- 1. Header PageBanner -->
            <PageBanner
                :icon="History"
                :title="t('watch_history.title')"
                :description="t('watch_history.subtitle')"
                :badge="String(counts.all)"
            >
                <template v-if="counts.all > 0" #actions>
                    <button
                        type="button"
                        @click="openClearModal('all')"
                        class="border-destructive/35 bg-destructive/15 text-destructive hover:bg-destructive/25 flex min-h-10 cursor-pointer items-center gap-2 rounded-2xl border px-4 py-2.5 text-xs font-bold shadow-sm transition-all active:scale-95"
                    >
                        <Trash2 class="h-4 w-4" />
                        <span>{{ t('watch_history.clear_all') }}</span>
                    </button>
                </template>
            </PageBanner>

            <!-- 2. Controls & Filter Bar -->
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <!-- Category FilterPills -->
                <FilterPills
                    :model-value="activeTab"
                    :options="filterPillOptions"
                    @update:model-value="(v) => (activeTab = v as any)"
                />

                <!-- Search Filter Input -->
                <div class="relative w-full sm:w-72">
                    <Search
                        class="text-muted-foreground pointer-events-none absolute top-1/2 h-4 w-4 -translate-y-1/2"
                        :class="isRTL ? 'right-3.5' : 'left-3.5'"
                    />
                    <input
                        type="text"
                        v-model="searchQuery"
                        :placeholder="t('watch_history.search_placeholder')"
                        class="cf-field border-border/80 bg-card/80 text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-primary/20 h-10 w-full rounded-2xl border font-sans text-xs transition-all focus:ring-2 focus:outline-none"
                        :class="isRTL ? 'pr-10 pl-9' : 'pr-9 pl-10'"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        @click="searchQuery = ''"
                        class="text-muted-foreground hover:text-foreground absolute top-1/2 -translate-y-1/2 cursor-pointer p-1"
                        :class="isRTL ? 'left-3' : 'right-3'"
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
                    class="cf-glass border-border/60 space-y-3 overflow-hidden rounded-2xl border p-3"
                >
                    <CinemaSkeleton
                        className="aspect-video w-full"
                        rounded="rounded-xl"
                    />
                    <CinemaSkeleton className="h-4 w-3/4" />
                    <CinemaSkeleton className="h-3 w-1/2" />
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else-if="filteredItems.length === 0"
                class="cf-glass border-border/80 mx-auto mt-8 max-w-lg space-y-4 rounded-3xl border p-12 text-center"
            >
                <div
                    class="border-primary/25 bg-primary/10 text-primary mx-auto flex h-16 w-16 items-center justify-center rounded-3xl border"
                >
                    <Clock class="h-8 w-8" />
                </div>
                <h3 class="text-foreground text-lg font-extrabold">
                    {{ t('watch_history.empty_title') }}
                </h3>
                <p
                    class="text-muted-foreground mx-auto max-w-sm text-xs leading-relaxed"
                >
                    {{
                        searchQuery
                            ? t('watch_history.empty_filter_desc')
                            : t('watch_history.empty_desc')
                    }}
                </p>
                <div v-if="searchQuery" class="pt-2">
                    <button
                        type="button"
                        @click="searchQuery = ''"
                        class="bg-primary text-primary-foreground shadow-primary/25 min-h-10 cursor-pointer rounded-xl px-4 py-2 text-xs font-bold shadow-sm transition-all hover:brightness-105 active:scale-95"
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
                        class="border-border/70 flex items-center justify-between border-b pb-3"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="border-primary/25 bg-primary/10 text-primary flex h-8 w-8 items-center justify-center rounded-xl border"
                            >
                                <Film class="h-4 w-4" />
                            </div>
                            <h2
                                class="text-foreground text-lg font-black tracking-wide"
                            >
                                {{ t('watch_history.movies_section') }}
                            </h2>
                            <span
                                class="border-primary/25 bg-primary/10 text-primary rounded-full border px-2 py-0.5 font-mono text-xs font-bold tabular-nums"
                            >
                                {{ moviesList.length }}
                            </span>
                        </div>

                        <button
                            type="button"
                            @click="openClearModal('movie')"
                            class="text-muted-foreground hover:text-destructive flex cursor-pointer items-center gap-1 text-xs font-bold transition-colors"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            <span>{{
                                t('watch_history.clear_category', {
                                    category: t('watch_history.movies'),
                                })
                            }}</span>
                        </button>
                    </div>

                    <MotionList
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
                    </MotionList>
                </div>

                <!-- Section B: Collections Watch History -->
                <div v-if="collectionsList.length > 0" class="space-y-4">
                    <div
                        class="border-border/70 flex items-center justify-between border-b pb-3"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-xl border border-amber-500/25 bg-amber-500/10 text-amber-400"
                            >
                                <Layers class="h-4 w-4" />
                            </div>
                            <h2
                                class="text-foreground text-lg font-black tracking-wide"
                            >
                                {{ t('watch_history.collections_section') }}
                            </h2>
                            <span
                                class="rounded-full border border-amber-500/25 bg-amber-500/10 px-2 py-0.5 font-mono text-xs font-bold text-amber-400 tabular-nums"
                            >
                                {{ collectionsList.length }}
                            </span>
                        </div>

                        <button
                            type="button"
                            @click="openClearModal('collection')"
                            class="text-muted-foreground hover:text-destructive flex cursor-pointer items-center gap-1 text-xs font-bold transition-colors"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            <span>{{
                                t('watch_history.clear_category', {
                                    category: t('watch_history.collections'),
                                })
                            }}</span>
                        </button>
                    </div>

                    <MotionList
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
                    </MotionList>
                </div>

                <!-- Section C: Series Watch History -->
                <div v-if="seriesList.length > 0" class="space-y-4">
                    <div
                        class="border-border/70 flex items-center justify-between border-b pb-3"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="border-primary/25 bg-primary/10 text-primary flex h-8 w-8 items-center justify-center rounded-xl border"
                            >
                                <Tv class="h-4 w-4" />
                            </div>
                            <h2
                                class="text-foreground text-lg font-black tracking-wide"
                            >
                                {{ t('watch_history.series_section') }}
                            </h2>
                            <span
                                class="border-primary/25 bg-primary/10 text-primary rounded-full border px-2 py-0.5 font-mono text-xs font-bold tabular-nums"
                            >
                                {{ seriesList.length }}
                            </span>
                        </div>

                        <button
                            type="button"
                            @click="openClearModal('series')"
                            class="text-muted-foreground hover:text-destructive flex cursor-pointer items-center gap-1 text-xs font-bold transition-colors"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            <span>{{
                                t('watch_history.clear_category', {
                                    category: t('watch_history.series'),
                                })
                            }}</span>
                        </button>
                    </div>

                    <MotionList
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
                    </MotionList>
                </div>
            </div>

            <!-- 4. Single-Category / Search Results Grid -->
            <div v-else class="space-y-4">
                <div
                    class="border-border/70 flex items-center justify-between border-b pb-3"
                >
                    <span
                        class="text-muted-foreground text-xs font-bold tracking-wider uppercase tabular-nums"
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
                        type="button"
                        @click="openClearModal(activeTab)"
                        class="text-muted-foreground hover:text-destructive flex cursor-pointer items-center gap-1 text-xs font-bold transition-colors"
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

                <MotionList
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
                </MotionList>
            </div>
        </div>

        <!-- Accessible ConfirmModal for Clear All / Clear Category -->
        <ConfirmModal
            :show="isClearModalOpen"
            type="danger"
            :loading="isClearing"
            :title="t('watch_history.confirm_clear_title')"
            :message="t('watch_history.confirm_clear_desc')"
            :confirm-text="t('watch_history.clear_confirm_btn')"
            :cancel-text="t('common.cancel')"
            @confirm="executeClear"
            @close="isClearModalOpen = false"
        />
    </AppLayout>
</template>
