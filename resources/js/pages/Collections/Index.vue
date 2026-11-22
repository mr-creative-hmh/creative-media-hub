<script setup lang="ts">
import { ref, computed, watch, defineAsyncComponent } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/components/layout/AppLayout.vue';
import WatchHistoryBar from '@/components/layout/WatchHistoryBar.vue';
import PageBanner from '@/components/common/PageBanner.vue';
import FilterPills, {
    type FilterPillOption,
} from '@/components/common/FilterPills.vue';
import MotionList from '@/components/motion/MotionList.vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Layers,
    Search,
    Film,
    Star,
    Calendar,
    Sparkles,
    ArrowRight,
    SlidersHorizontal,
    CheckCircle2,
    RotateCcw,
    RefreshCw,
    X,
    ChevronDown,
    ArrowDownAZ,
    ArrowUpZA,
    ArrowDownWideNarrow,
    ArrowUpNarrowWide,
    Filter,
} from '@lucide/vue';

const CollectionManagementModal = defineAsyncComponent(
    () => import('@/components/collections/CollectionManagementModal.vue'),
);

interface MovieItem {
    id: number;
    title: string;
    title_ar?: string;
    slug: string;
    release_year?: number;
    rating?: number;
    poster_path?: string;
    backdrop_path?: string;
    resolution?: string;
    runtime_minutes?: number;
}

interface GenreItem {
    id: number;
    name?: string;
    name_en?: string;
    name_ar?: string;
    slug?: string;
}

interface MovieCollection {
    name: string;
    slug: string;
    movies_count: number;
    total_parts?: number;
    is_complete?: boolean;
    completion_percentage?: number;
    missing_parts?: any[];
    year_span?: string;
    earliest_year?: number;
    latest_year?: number;
    avg_rating?: number;
    poster_path?: string;
    backdrop_path?: string;
    genres?: GenreItem[];
    movies: MovieItem[];
}

const props = defineProps<{
    collections: MovieCollection[];
    all_genres?: GenreItem[];
    filters: {
        search?: string;
        sort?: string;
        direction?: string;
        status?: string;
        genre?: string;
    };
    total_collections: number;
    total_franchise_movies: number;
}>();

const { isRTL } = useI18n();

// Filter & Sort State
const searchQuery = ref(props.filters.search || '');
const selectedSort = ref<
    'name' | 'rating' | 'movies_count' | 'year' | 'completion'
>((props.filters.sort as any) || 'name');
const sortDirection = ref<'asc' | 'desc'>(
    (props.filters.direction as any) ||
        (['rating', 'movies_count', 'year', 'completion'].includes(
            selectedSort.value,
        )
            ? 'desc'
            : 'asc'),
);
const activeStatus = ref<
    'all' | 'complete' | 'in_progress' | 'top_rated' | 'large'
>((props.filters.status as any) || 'all');
const selectedGenre = ref<string | number>(props.filters.genre || '');

const isRefreshing = ref(false);
const showManagementModal = ref(false);

const toggleSortDirection = () => {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
};

watch(selectedSort, (newSort) => {
    if (newSort === 'name') {
        sortDirection.value = 'asc';
    } else {
        sortDirection.value = 'desc';
    }
});

const clearSearch = () => {
    searchQuery.value = '';
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedSort.value = 'name';
    sortDirection.value = 'asc';
    activeStatus.value = 'all';
    selectedGenre.value = '';
};

const hasActiveFilters = computed(() => {
    return !!(
        searchQuery.value.trim() ||
        selectedSort.value !== 'name' ||
        sortDirection.value !== 'asc' ||
        activeStatus.value !== 'all' ||
        selectedGenre.value
    );
});

const refreshCollections = () => {
    if (isRefreshing.value) return;
    isRefreshing.value = true;
    router.get(
        '/collections',
        { refresh: true, search: searchQuery.value || undefined },
        {
            preserveState: false,
            preserveScroll: true,
            onFinish: () => {
                isRefreshing.value = false;
            },
        },
    );
};

// Count computations
const completeCount = computed(
    () => props.collections.filter((c) => c.is_complete).length,
);
const inProgressCount = computed(
    () => props.collections.filter((c) => !c.is_complete).length,
);
const topRatedCount = computed(
    () => props.collections.filter((c) => (c.avg_rating || 0) >= 8.0).length,
);
const largeSagasCount = computed(
    () => props.collections.filter((c) => (c.movies_count || 0) >= 4).length,
);

const statusFilterOptions = computed<FilterPillOption[]>(() => [
    {
        value: 'all',
        label: isRTL.value ? 'جميع السلاسل' : 'All Sagas',
        count: props.collections.length,
    },
    {
        value: 'complete',
        label: isRTL.value ? 'مكتملة 100%' : 'Complete',
        count: completeCount.value,
    },
    {
        value: 'in_progress',
        label: isRTL.value ? 'ينقصها أجزاء' : 'Missing Parts',
        count: inProgressCount.value,
    },
    {
        value: 'top_rated',
        label: isRTL.value ? 'الأعلى تقييماً (8.0+)' : 'Top Rated (8.0+)',
        count: topRatedCount.value,
    },
    {
        value: 'large',
        label: isRTL.value ? 'سلاسل كبرى (4+ أفلام)' : 'Epic Sagas (4+)',
        count: largeSagasCount.value,
    },
]);

// Available genres for dropdown
const availableGenres = computed(() => {
    if (props.all_genres && props.all_genres.length > 0) {
        return props.all_genres;
    }
    const map = new Map<number, GenreItem>();
    for (const col of props.collections) {
        for (const g of col.genres || []) {
            if (!map.has(g.id)) {
                map.set(g.id, g);
            }
        }
    }
    return Array.from(map.values()).sort((a, b) => {
        const nameA = a.name_en || a.name || a.slug || '';
        const nameB = b.name_en || b.name || b.slug || '';
        return nameA.localeCompare(nameB);
    });
});

// Client-side reactive filtered & sorted list
const filteredAndSortedCollections = computed(() => {
    let list = [...props.collections];

    // 1. Text Search Filter (name, slug, movie title, arabic title)
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        list = list.filter((col) => {
            const nameMatch = col.name?.toLowerCase().includes(q);
            const slugMatch = col.slug?.toLowerCase().includes(q);
            const movieMatch = col.movies?.some(
                (m) =>
                    m.title?.toLowerCase().includes(q) ||
                    (m.title_ar && m.title_ar.toLowerCase().includes(q)),
            );
            return nameMatch || slugMatch || movieMatch;
        });
    }

    // 2. Status Tab Filter
    if (activeStatus.value === 'complete') {
        list = list.filter((col) => col.is_complete);
    } else if (activeStatus.value === 'in_progress') {
        list = list.filter((col) => !col.is_complete);
    } else if (activeStatus.value === 'top_rated') {
        list = list.filter((col) => (col.avg_rating || 0) >= 8.0);
    } else if (activeStatus.value === 'large') {
        list = list.filter((col) => (col.movies_count || 0) >= 4);
    }

    // 3. Genre Filter
    if (selectedGenre.value) {
        const target = String(selectedGenre.value).toLowerCase();
        list = list.filter((col) => {
            return col.genres?.some(
                (g) =>
                    String(g.id) === target ||
                    (g.slug && g.slug.toLowerCase() === target) ||
                    (g.name_en && g.name_en.toLowerCase() === target) ||
                    (g.name && g.name.toLowerCase() === target) ||
                    (g.name_ar && g.name_ar.toLowerCase() === target),
            );
        });
    }

    // 4. Sorting
    list.sort((a, b) => {
        let res = 0;
        if (selectedSort.value === 'name') {
            res = a.name.localeCompare(b.name, undefined, {
                sensitivity: 'base',
                numeric: true,
            });
        } else if (selectedSort.value === 'rating') {
            res = (a.avg_rating || 0) - (b.avg_rating || 0);
        } else if (selectedSort.value === 'movies_count') {
            res = (a.movies_count || 0) - (b.movies_count || 0);
        } else if (selectedSort.value === 'year') {
            const aYear =
                a.latest_year ||
                a.movies?.[a.movies.length - 1]?.release_year ||
                0;
            const bYear =
                b.latest_year ||
                b.movies?.[b.movies.length - 1]?.release_year ||
                0;
            res = aYear - bYear;
        } else if (selectedSort.value === 'completion') {
            res =
                (a.completion_percentage || 0) - (b.completion_percentage || 0);
        }

        return sortDirection.value === 'asc' ? res : -res;
    });

    return list;
});
</script>

<template>
    <AppLayout v-slot="{ play }">
        <Head
            :title="
                isRTL
                    ? 'سلاسل الأفلام ومجموعات البوكس سيت'
                    : 'Movie Collections & Boxsets'
            "
        />

        <div class="space-y-8 pb-12">
            <!-- Hero PageBanner -->
            <PageBanner
                :icon="Layers"
                :title="
                    isRTL
                        ? 'سلاسل ومجموعات الأفلام العالمية'
                        : 'Movie Collections & Sagas'
                "
                :description="
                    isRTL
                        ? 'استكشف سلاسل الأفلام الكاملة مرتبة بالتسلسل الزمني للإنتاج مع تتبع الأجزاء المملوكة والمفقودة وترتيب السلاسل حسب رغبتك.'
                        : 'Browse entire movie franchises chronologically organized with full control over sorting, filters, and missing saga parts.'
                "
                :badge="isRTL ? 'سلاسل الأفلام الكاملة' : 'Franchise Boxsets'"
            >
                <template #actions>
                    <div class="flex flex-wrap items-center gap-3">
                        <div
                            class="cf-glass border-border/80 min-w-[96px] rounded-2xl border px-4 py-2.5 text-center"
                        >
                            <div
                                class="text-primary text-xl font-black tabular-nums"
                            >
                                {{ total_collections }}
                            </div>
                            <div
                                class="text-muted-foreground mt-0.5 text-[10px] font-semibold tracking-wider uppercase"
                            >
                                {{ isRTL ? 'سلسلة أفلام' : 'Collections' }}
                            </div>
                        </div>
                        <div
                            class="cf-glass border-border/80 min-w-[96px] rounded-2xl border px-4 py-2.5 text-center"
                        >
                            <div
                                class="text-foreground text-xl font-black tabular-nums"
                            >
                                {{ total_franchise_movies }}
                            </div>
                            <div
                                class="text-muted-foreground mt-0.5 text-[10px] font-semibold tracking-wider uppercase"
                            >
                                {{ isRTL ? 'فيلماً مجمعاً' : 'Total Films' }}
                            </div>
                        </div>
                    </div>
                </template>
            </PageBanner>

            <!-- In-Progress Continue Watching Bar (Collections Only) -->
            <WatchHistoryBar type="collection" @play="play" />

            <!-- Collections Filter & Sort Control Bar -->
            <div
                class="cf-glass border-border/80 space-y-4 rounded-3xl border p-4 shadow-xl sm:p-5"
            >
                <!-- Top Row: Instant Search & Sort Options & Studio Actions -->
                <div
                    class="flex flex-col items-stretch justify-between gap-4 lg:flex-row lg:items-center"
                >
                    <!-- Live Instant Search -->
                    <div class="relative max-w-md flex-1">
                        <Search
                            class="text-muted-foreground pointer-events-none absolute top-1/2 h-4 w-4 -translate-y-1/2"
                            :class="isRTL ? 'right-3.5' : 'left-3.5'"
                        />
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="
                                isRTL
                                    ? 'ابحث في السلاسل أو أسماء الأفلام (مثال: Batman, هاري بوتر)...'
                                    : 'Filter collections or movie titles (e.g. Batman, Matrix)...'
                            "
                            class="cf-field border-border/80 bg-background/60 text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-primary/20 h-10 w-full rounded-2xl border py-2 text-xs focus:ring-2 focus:outline-none sm:text-sm"
                            :class="isRTL ? 'pr-10 pl-9' : 'pr-9 pl-10'"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            @click="clearSearch"
                            class="text-muted-foreground hover:text-foreground absolute top-1/2 -translate-y-1/2 cursor-pointer p-1 transition-colors"
                            :class="isRTL ? 'left-2.5' : 'right-2.5'"
                            title="Clear search"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <!-- Sort Controls & Studio Tools -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Genre Dropdown -->
                        <div class="relative min-w-[130px] sm:min-w-[145px]">
                            <select
                                v-model="selectedGenre"
                                class="cf-glass border-border/80 text-foreground hover:border-primary/40 focus:border-primary h-10 w-full cursor-pointer appearance-none rounded-2xl border px-3.5 text-xs font-bold transition-all focus:outline-none"
                                :class="isRTL ? 'pr-3.5 pl-8' : 'pr-8 pl-3.5'"
                            >
                                <option
                                    value=""
                                    class="bg-card text-foreground"
                                >
                                    {{
                                        isRTL ? 'جميع التصنيفات' : 'All Genres'
                                    }}
                                </option>
                                <option
                                    v-for="g in availableGenres"
                                    :key="g.id"
                                    :value="g.id"
                                    class="bg-card text-foreground"
                                >
                                    {{
                                        isRTL && g.name_ar
                                            ? g.name_ar
                                            : g.name_en || g.name || g.slug
                                    }}
                                </option>
                            </select>
                            <ChevronDown
                                class="text-muted-foreground pointer-events-none absolute top-1/2 h-3.5 w-3.5 -translate-y-1/2"
                                :class="isRTL ? 'left-3' : 'right-3'"
                            />
                        </div>

                        <!-- Sort By Dropdown -->
                        <div class="relative min-w-[160px] sm:min-w-[185px]">
                            <select
                                v-model="selectedSort"
                                class="cf-glass border-border/80 text-foreground hover:border-primary/40 focus:border-primary h-10 w-full cursor-pointer appearance-none rounded-2xl border px-3.5 text-xs font-bold transition-all focus:outline-none"
                                :class="isRTL ? 'pr-3.5 pl-8' : 'pr-8 pl-3.5'"
                            >
                                <option
                                    value="name"
                                    class="bg-card text-foreground"
                                >
                                    {{
                                        isRTL
                                            ? 'الأبجدي (A - Z)'
                                            : 'Alphabetical (A - Z)'
                                    }}
                                </option>
                                <option
                                    value="rating"
                                    class="bg-card text-foreground"
                                >
                                    {{
                                        isRTL
                                            ? 'التقييم الأعلى ⭐'
                                            : 'Highest Rating ⭐'
                                    }}
                                </option>
                                <option
                                    value="movies_count"
                                    class="bg-card text-foreground"
                                >
                                    {{
                                        isRTL
                                            ? 'الأكثر أفلاماً 🎬'
                                            : 'Most Films 🎬'
                                    }}
                                </option>
                                <option
                                    value="year"
                                    class="bg-card text-foreground"
                                >
                                    {{
                                        isRTL
                                            ? 'أحدث إصدار 📅'
                                            : 'Newest Release 📅'
                                    }}
                                </option>
                                <option
                                    value="completion"
                                    class="bg-card text-foreground"
                                >
                                    {{
                                        isRTL
                                            ? 'نسبة الاكتمال 💯'
                                            : 'Completion % 💯'
                                    }}
                                </option>
                            </select>
                            <ChevronDown
                                class="text-muted-foreground pointer-events-none absolute top-1/2 h-3.5 w-3.5 -translate-y-1/2"
                                :class="isRTL ? 'left-3' : 'right-3'"
                            />
                        </div>

                        <!-- Sort Direction Toggle Button -->
                        <button
                            type="button"
                            @click="toggleSortDirection"
                            class="cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground flex size-10 cursor-pointer items-center justify-center rounded-2xl border transition-all active:scale-95"
                            :title="
                                isRTL
                                    ? sortDirection === 'asc'
                                        ? 'تصاعدي (انقر للتبديل لتنازلي)'
                                        : 'تنازلي (انقر للتبديل لتصاعدي)'
                                    : sortDirection === 'asc'
                                      ? 'Ascending (Click for Descending)'
                                      : 'Descending (Click for Ascending)'
                            "
                        >
                            <ArrowDownAZ
                                v-if="
                                    selectedSort === 'name' &&
                                    sortDirection === 'asc'
                                "
                                class="text-primary h-4 w-4"
                            />
                            <ArrowUpZA
                                v-else-if="
                                    selectedSort === 'name' &&
                                    sortDirection === 'desc'
                                "
                                class="text-primary h-4 w-4"
                            />
                            <ArrowDownWideNarrow
                                v-else-if="sortDirection === 'desc'"
                                class="text-primary h-4 w-4"
                            />
                            <ArrowUpNarrowWide
                                v-else
                                class="text-primary h-4 w-4"
                            />
                        </button>

                        <!-- Studio Management Button -->
                        <button
                            type="button"
                            @click="showManagementModal = true"
                            class="border-primary/35 bg-primary/15 text-primary hover:border-primary/50 hover:bg-primary/25 flex h-10 cursor-pointer items-center gap-1.5 rounded-2xl border px-3.5 text-xs font-bold transition-all active:scale-95"
                            :title="
                                isRTL
                                    ? 'إدارة وتدقيق وتعديل السلاسل'
                                    : 'Manage & Curate Collections'
                            "
                        >
                            <SlidersHorizontal class="h-3.5 w-3.5" />
                            <span class="hidden sm:inline">{{
                                isRTL ? 'إدارة السلاسل' : 'Manage Franchises'
                            }}</span>
                        </button>

                        <!-- Resync Cache Button -->
                        <button
                            type="button"
                            @click="refreshCollections"
                            :disabled="isRefreshing"
                            class="cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-primary flex h-10 cursor-pointer items-center gap-1.5 rounded-2xl border px-3 text-xs font-semibold transition-all active:scale-95 disabled:opacity-50"
                            :title="
                                isRTL
                                    ? 'مزامنة وتحديث بيانات السلاسل'
                                    : 'Resync collections status'
                            "
                        >
                            <RefreshCw
                                class="h-3.5 w-3.5"
                                :class="{
                                    'text-primary animate-spin': isRefreshing,
                                }"
                            />
                            <span class="hidden md:inline">{{
                                isRefreshing
                                    ? isRTL
                                        ? 'جارٍ التحديث...'
                                        : 'Syncing...'
                                    : isRTL
                                      ? 'مزامنة'
                                      : 'Sync'
                            }}</span>
                        </button>
                    </div>
                </div>

                <!-- Bottom Row: FilterPills & Results Count / Reset -->
                <div
                    class="border-border/60 flex flex-wrap items-center justify-between gap-3 border-t pt-3"
                >
                    <FilterPills
                        :model-value="activeStatus"
                        :options="statusFilterOptions"
                        @update:model-value="(v) => (activeStatus = v as any)"
                    />

                    <!-- Active Filter Indicator & Reset -->
                    <div
                        class="text-muted-foreground flex items-center gap-3 text-xs tabular-nums"
                    >
                        <span>
                            {{
                                isRTL
                                    ? `عرض ${filteredAndSortedCollections.length} من أصل ${collections.length} سلسلة`
                                    : `Showing ${filteredAndSortedCollections.length} of ${collections.length} sagas`
                            }}
                        </span>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            @click="resetFilters"
                            class="text-primary flex cursor-pointer items-center gap-1 text-xs font-bold transition-colors hover:brightness-110"
                        >
                            <RotateCcw class="h-3 w-3" />
                            <span>{{ isRTL ? 'إعادة ضبط' : 'Reset' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Collections Grid -->
            <MotionList
                v-if="filteredAndSortedCollections.length > 0"
                class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <Link
                    v-for="col in filteredAndSortedCollections"
                    :key="col.slug"
                    :href="`/collections/${col.slug}`"
                    class="cf-glass group border-border/80 hover:border-primary/50 relative flex cursor-pointer flex-col overflow-hidden rounded-3xl border transition-all duration-200 hover:-translate-y-1 hover:shadow-2xl"
                >
                    <!-- Collection Backdrop / Cover -->
                    <div
                        class="relative aspect-video w-full overflow-hidden bg-slate-950"
                    >
                        <img
                            v-if="col.backdrop_path || col.poster_path"
                            :src="col.backdrop_path || col.poster_path"
                            :alt="col.name"
                            class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                            loading="lazy"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center bg-gradient-to-br from-slate-900 to-slate-950 text-slate-700"
                        >
                            <Film class="h-12 w-12" />
                        </div>
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/50 to-transparent"
                        ></div>

                        <!-- Pill Badge for movie count & completion status -->
                        <div
                            class="absolute end-3 top-3 flex items-center gap-1.5"
                        >
                            <div
                                v-if="col.is_complete"
                                class="flex items-center gap-1.5 rounded-full border border-emerald-500/40 bg-emerald-950/85 px-3 py-1 text-xs font-black text-emerald-400 shadow-lg backdrop-blur-md"
                            >
                                <CheckCircle2 class="h-3.5 w-3.5" />
                                <span>{{ isRTL ? 'مكتملة' : 'Complete' }}</span>
                            </div>
                            <div
                                v-else
                                class="flex items-center gap-1.5 rounded-full border border-amber-500/35 bg-black/75 px-3 py-1 text-xs font-black text-amber-300 tabular-nums shadow-lg backdrop-blur-md"
                            >
                                <Film class="h-3.5 w-3.5 text-amber-400" />
                                <span
                                    >{{ col.movies_count }} /
                                    {{ col.total_parts || col.movies_count }}
                                    {{
                                        isRTL
                                            ? col.movies_count > 10
                                                ? 'فيلماً'
                                                : 'أفلام'
                                            : 'Films'
                                    }}</span
                                >
                            </div>
                        </div>

                        <!-- Year Span -->
                        <div
                            v-if="col.year_span"
                            class="absolute start-3 bottom-3 flex items-center gap-1 rounded-lg border border-white/15 bg-black/70 px-2.5 py-0.5 text-xs font-semibold text-slate-200 tabular-nums backdrop-blur-md"
                        >
                            <Calendar class="text-primary h-3 w-3" />
                            <span>{{ col.year_span }}</span>
                        </div>

                        <!-- Progress Bar at bottom of media -->
                        <div
                            v-if="
                                col.total_parts &&
                                col.total_parts > col.movies_count
                            "
                            class="absolute inset-x-0 bottom-0 h-1 overflow-hidden bg-black/60"
                        >
                            <div
                                class="bg-primary h-full transition-all duration-500"
                                :style="{
                                    width: `${col.completion_percentage}%`,
                                }"
                            ></div>
                        </div>
                    </div>

                    <!-- Collection Details -->
                    <div
                        class="flex flex-1 flex-col justify-between space-y-4 p-5"
                    >
                        <div class="space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <h3
                                    class="text-foreground group-hover:text-primary line-clamp-1 text-base font-bold transition-colors"
                                >
                                    {{ col.name }}
                                </h3>
                                <span
                                    v-if="
                                        col.missing_parts &&
                                        col.missing_parts.length > 0
                                    "
                                    class="shrink-0 rounded-md border border-amber-500/30 bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold text-amber-300 tabular-nums"
                                >
                                    {{
                                        isRTL
                                            ? `مفقود ${col.missing_parts.length}`
                                            : `-${col.missing_parts.length}`
                                    }}
                                </span>
                            </div>

                            <!-- Movie titles preview chips -->
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                <span
                                    v-for="m in (
                                        (Array.isArray(col.movies)
                                            ? col.movies
                                            : Object.values(
                                                  col.movies || {},
                                              )) as any[]
                                    ).slice(0, 3)"
                                    :key="(m as any).id"
                                    class="border-border/70 bg-muted/40 text-muted-foreground max-w-[140px] truncate rounded-md border px-2 py-0.5 text-[10px] font-medium"
                                >
                                    {{ (m as any).title }}
                                </span>
                                <span
                                    v-if="
                                        (Array.isArray(col.movies)
                                            ? col.movies.length
                                            : Object.keys(col.movies || {})
                                                  .length) > 3
                                    "
                                    class="border-primary/30 bg-primary/15 text-primary rounded-md border px-1.5 py-0.5 text-[10px] font-bold tabular-nums"
                                >
                                    +{{
                                        (Array.isArray(col.movies)
                                            ? col.movies.length
                                            : Object.keys(col.movies || {})
                                                  .length) - 3
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div
                            class="border-border/60 text-muted-foreground flex items-center justify-between border-t pt-2.5 text-xs"
                        >
                            <div
                                class="flex items-center gap-1 font-bold text-amber-400 tabular-nums"
                            >
                                <Star class="h-3.5 w-3.5 fill-amber-400" />
                                <span>{{ col.avg_rating || '7.5' }}</span>
                            </div>

                            <span
                                class="text-primary inline-flex items-center gap-1 font-bold transition-transform group-hover:translate-x-1 rtl:group-hover:-translate-x-1"
                            >
                                <span>{{
                                    isRTL ? 'عرض السلسلة' : 'Explore Saga'
                                }}</span>
                                <ArrowRight
                                    class="h-3.5 w-3.5 rtl:rotate-180"
                                />
                            </span>
                        </div>
                    </div>
                </Link>
            </MotionList>

            <!-- Empty Filter State -->
            <div
                v-else-if="collections.length > 0"
                class="cf-glass border-border/80 mx-auto max-w-lg space-y-4 rounded-3xl border px-4 py-16 text-center"
            >
                <div
                    class="border-primary/25 bg-primary/10 text-primary mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border"
                >
                    <Filter class="h-8 w-8" />
                </div>
                <h3 class="text-foreground text-lg font-bold">
                    {{
                        isRTL
                            ? 'لا توجد سلاسل مطابقة للتصفية'
                            : 'No Matching Collections Found'
                    }}
                </h3>
                <p class="text-muted-foreground text-xs leading-relaxed">
                    {{
                        isRTL
                            ? 'لم يتم العثور على أي سلسلة تطابق معايير البحث أو التصفية الحالية. يمكنك إعادة تعيين التصفية لعرض جميع السلاسل.'
                            : 'No movie collections match your current search or filter criteria. You can reset filters to view all sagas.'
                    }}
                </p>
                <button
                    type="button"
                    @click="resetFilters"
                    class="bg-primary text-primary-foreground shadow-primary/25 inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-xl px-5 py-2.5 text-xs font-bold shadow-sm transition-all hover:brightness-105 active:scale-95"
                >
                    <RotateCcw class="h-4 w-4" />
                    <span>{{
                        isRTL ? 'إعادة ضبط خيارات التصفية' : 'Reset Filters'
                    }}</span>
                </button>
            </div>

            <!-- Completely Empty Library State -->
            <div
                v-else
                class="cf-glass border-border/80 mx-auto max-w-lg space-y-4 rounded-3xl border px-4 py-16 text-center"
            >
                <div
                    class="border-primary/25 bg-primary/10 text-primary mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border"
                >
                    <Layers class="h-8 w-8" />
                </div>
                <h3 class="text-foreground text-lg font-bold">
                    {{
                        isRTL
                            ? 'لم يتم العثور على سلاسل أفلام'
                            : 'No Movie Collections Found'
                    }}
                </h3>
                <p class="text-muted-foreground text-xs leading-relaxed">
                    {{
                        isRTL
                            ? 'عند قيامك بفهرسة مكتبة أفلامك التي تحتوي على أفلام ذات أجزاء متعددة (مثل هاري بوتر، حرب النجوم، فاست آند فيوريوس)، سيتم تجميعها تلقائياً هنا.'
                            : 'When scanning your movie library with multi-part franchises (Harry Potter, Star Wars, Fast & Furious), they will automatically be grouped into collections here.'
                    }}
                </p>
                <Link
                    href="/scanner"
                    class="bg-primary text-primary-foreground shadow-primary/25 inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-xl px-5 py-2.5 text-xs font-bold shadow-sm transition-all hover:brightness-105 active:scale-95"
                >
                    <Sparkles class="h-4 w-4" />
                    <span>{{
                        isRTL ? 'تشغيل الفاحص الذكي' : 'Run Virtual Scanner'
                    }}</span>
                </Link>
            </div>
        </div>

        <CollectionManagementModal
            v-if="showManagementModal"
            :show="showManagementModal"
            @close="showManagementModal = false"
            @changed="refreshCollections"
        />
    </AppLayout>
</template>
