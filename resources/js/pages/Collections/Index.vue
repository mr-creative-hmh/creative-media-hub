<script setup lang="ts">
import { ref, computed, watch, defineAsyncComponent } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/components/layout/AppLayout.vue';
import WatchHistoryBar from '@/components/layout/WatchHistoryBar.vue';
import { useI18n } from '@/i18n/useI18n';

const CollectionManagementModal = defineAsyncComponent(
    () => import('@/components/collections/CollectionManagementModal.vue'),
);
import {
    Layers,
    Search,
    Film,
    Star,
    Calendar,
    Play,
    Sparkles,
    ArrowRight,
    Video,
    SlidersHorizontal,
    CheckCircle2,
    Clock,
    RotateCcw,
    RefreshCw,
    X,
    ChevronDown,
    ArrowDownAZ,
    ArrowUpZA,
    ArrowDownWideNarrow,
    ArrowUpNarrowWide,
    Filter,
} from 'lucide-vue-next';

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

const { t, isRTL } = useI18n();

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
    // Sensible default directions when switching sort types
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
            <!-- Hero Banner -->
            <section
                class="glass-panel relative overflow-hidden rounded-3xl border border-cyan-500/20 bg-gradient-to-br from-cyan-950/40 via-slate-900/60 to-purple-950/30 p-6 lg:p-10"
            >
                <div
                    class="ambient-glow pointer-events-none -top-20 -left-20 h-96 w-96 bg-cyan-500/10"
                ></div>
                <div
                    class="ambient-glow pointer-events-none -right-20 -bottom-20 h-96 w-96 bg-purple-500/10"
                ></div>

                <div
                    class="relative z-10 flex flex-col items-start justify-between gap-6 md:flex-row md:items-center"
                >
                    <div class="max-w-2xl space-y-3">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-cyan-500/30 bg-cyan-500/20 px-3 py-1 text-xs font-bold tracking-wider text-cyan-300 uppercase"
                        >
                            <Layers class="h-3.5 w-3.5" />
                            <span>{{
                                isRTL
                                    ? 'سلاسل الأفلام الكاملة'
                                    : 'Franchise Boxsets'
                            }}</span>
                        </div>
                        <h1
                            class="text-3xl font-black tracking-tight text-white lg:text-4xl"
                        >
                            {{
                                isRTL
                                    ? 'سلاسل ومجموعات الأفلام العالمية'
                                    : 'Movie Collections & Sagas'
                            }}
                        </h1>
                        <p
                            class="text-sm leading-relaxed text-slate-300 lg:text-base"
                        >
                            {{
                                isRTL
                                    ? 'استكشف سلاسل الأفلام الكاملة مرتبة بالتسلسل الزمني للإنتاج مع تتبع الأجزاء المملوكة والمفقودة وترتيب السلاسل حسب رغبتك.'
                                    : 'Browse entire movie franchises chronologically organized with full control over sorting, filters, and missing saga parts.'
                            }}
                        </p>
                    </div>

                    <!-- Stats Badges -->
                    <div class="flex shrink-0 items-center gap-4">
                        <div
                            class="min-w-[110px] rounded-2xl border border-white/10 bg-white/[0.04] p-4 text-center"
                        >
                            <div class="text-2xl font-black text-cyan-400">
                                {{ total_collections }}
                            </div>
                            <div
                                class="mt-0.5 text-[11px] font-semibold text-slate-400 uppercase"
                            >
                                {{ isRTL ? 'سلسلة أفلام' : 'Collections' }}
                            </div>
                        </div>
                        <div
                            class="min-w-[110px] rounded-2xl border border-white/10 bg-white/[0.04] p-4 text-center"
                        >
                            <div class="text-2xl font-black text-purple-400">
                                {{ total_franchise_movies }}
                            </div>
                            <div
                                class="mt-0.5 text-[11px] font-semibold text-slate-400 uppercase"
                            >
                                {{ isRTL ? 'فيلماً مجمعاً' : 'Total Films' }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- In-Progress Continue Watching Bar (Collections Only) -->
            <WatchHistoryBar type="collection" @play="play" />

            <!-- Collections Filter & Sort Control Bar -->
            <div
                class="glass-panel space-y-4 rounded-3xl border border-white/10 bg-slate-900/60 p-4 shadow-xl backdrop-blur-xl sm:p-5"
            >
                <!-- Top Row: Instant Search & Sort Options & Studio Actions -->
                <div
                    class="flex flex-col items-stretch justify-between gap-4 lg:flex-row lg:items-center"
                >
                    <!-- Live Instant Search -->
                    <div class="relative max-w-md flex-1">
                        <Search
                            class="absolute top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
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
                            class="w-full rounded-2xl border border-white/10 bg-black/40 py-2.5 text-xs text-white placeholder-slate-400 shadow-inner transition-all focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 focus:outline-none sm:text-sm"
                            :class="isRTL ? 'pr-10 pl-9' : 'pr-9 pl-10'"
                        />
                        <button
                            v-if="searchQuery"
                            @click="clearSearch"
                            class="absolute top-1/2 -translate-y-1/2 cursor-pointer p-1 text-slate-400 transition-colors hover:text-white"
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
                                class="w-full cursor-pointer appearance-none rounded-2xl border border-white/10 bg-white/5 px-3.5 py-2.5 pr-8 text-xs font-bold text-white transition-all hover:bg-white/10 focus:border-cyan-400 focus:outline-none"
                                :class="isRTL ? 'pr-3.5 pl-8' : 'pr-8 pl-3.5'"
                            >
                                <option
                                    value=""
                                    class="bg-slate-900 text-white"
                                >
                                    {{
                                        isRTL ? 'جميع التصنيفات' : 'All Genres'
                                    }}
                                </option>
                                <option
                                    v-for="g in availableGenres"
                                    :key="g.id"
                                    :value="g.id"
                                    class="bg-slate-900 text-white"
                                >
                                    {{
                                        isRTL && g.name_ar
                                            ? g.name_ar
                                            : g.name_en || g.name || g.slug
                                    }}
                                </option>
                            </select>
                            <ChevronDown
                                class="pointer-events-none absolute top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                                :class="isRTL ? 'left-3' : 'right-3'"
                            />
                        </div>

                        <!-- Sort By Dropdown -->
                        <div class="relative min-w-[160px] sm:min-w-[185px]">
                            <select
                                v-model="selectedSort"
                                class="w-full cursor-pointer appearance-none rounded-2xl border border-white/10 bg-white/5 px-3.5 py-2.5 text-xs font-bold text-white transition-all hover:bg-white/10 focus:border-cyan-400 focus:outline-none"
                                :class="isRTL ? 'pr-3.5 pl-8' : 'pr-8 pl-3.5'"
                            >
                                <option
                                    value="name"
                                    class="bg-slate-900 text-white"
                                >
                                    {{
                                        isRTL
                                            ? 'الأبجدي (A - Z)'
                                            : 'Alphabetical (A - Z)'
                                    }}
                                </option>
                                <option
                                    value="rating"
                                    class="bg-slate-900 text-white"
                                >
                                    {{
                                        isRTL
                                            ? 'التقييم الأعلى ⭐'
                                            : 'Highest Rating ⭐'
                                    }}
                                </option>
                                <option
                                    value="movies_count"
                                    class="bg-slate-900 text-white"
                                >
                                    {{
                                        isRTL
                                            ? 'الأكثر أفلاماً 🎬'
                                            : 'Most Films 🎬'
                                    }}
                                </option>
                                <option
                                    value="year"
                                    class="bg-slate-900 text-white"
                                >
                                    {{
                                        isRTL
                                            ? 'أحدث إصدار 📅'
                                            : 'Newest Release 📅'
                                    }}
                                </option>
                                <option
                                    value="completion"
                                    class="bg-slate-900 text-white"
                                >
                                    {{
                                        isRTL
                                            ? 'نسبة الاكتمال 💯'
                                            : 'Completion % 💯'
                                    }}
                                </option>
                            </select>
                            <ChevronDown
                                class="pointer-events-none absolute top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                                :class="isRTL ? 'left-3' : 'right-3'"
                            />
                        </div>

                        <!-- Sort Direction Toggle Button -->
                        <button
                            @click="toggleSortDirection"
                            class="flex cursor-pointer items-center justify-center rounded-2xl border border-white/10 bg-white/5 p-2.5 text-slate-300 transition-all hover:border-cyan-500/30 hover:bg-white/10 hover:text-white"
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
                                class="h-4 w-4 text-cyan-400"
                            />
                            <ArrowUpZA
                                v-else-if="
                                    selectedSort === 'name' &&
                                    sortDirection === 'desc'
                                "
                                class="h-4 w-4 text-cyan-400"
                            />
                            <ArrowDownWideNarrow
                                v-else-if="sortDirection === 'desc'"
                                class="h-4 w-4 text-cyan-400"
                            />
                            <ArrowUpNarrowWide
                                v-else
                                class="h-4 w-4 text-cyan-400"
                            />
                        </button>

                        <!-- Studio Management Button -->
                        <button
                            @click="showManagementModal = true"
                            class="flex cursor-pointer items-center gap-1.5 rounded-2xl border border-cyan-500/30 bg-cyan-500/20 px-3.5 py-2.5 text-xs font-bold text-cyan-300 transition-all hover:border-cyan-500/50 hover:bg-cyan-500/30"
                            :title="
                                isRTL
                                    ? 'إدارة وتدقيق وتعديل السلاسل'
                                    : 'Manage & Curate Collections'
                            "
                        >
                            <SlidersHorizontal
                                class="h-3.5 w-3.5 text-cyan-400"
                            />
                            <span class="hidden sm:inline">{{
                                isRTL ? 'إدارة السلاسل' : 'Manage Franchises'
                            }}</span>
                        </button>

                        <!-- Resync Cache Button -->
                        <button
                            @click="refreshCollections"
                            :disabled="isRefreshing"
                            class="flex cursor-pointer items-center gap-1.5 rounded-2xl border border-white/10 bg-white/5 p-2.5 text-xs font-semibold text-slate-400 transition-all hover:border-cyan-500/30 hover:bg-cyan-500/10 hover:text-cyan-400 disabled:opacity-50 sm:px-3 sm:py-2.5"
                            :title="
                                isRTL
                                    ? 'مزامنة وتحديث بيانات السلاسل'
                                    : 'Resync collections status'
                            "
                        >
                            <RefreshCw
                                class="h-3.5 w-3.5"
                                :class="{
                                    'animate-spin text-cyan-400': isRefreshing,
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

                <!-- Bottom Row: Status Filter Chips & Results Count / Reset -->
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-white/5 pt-3"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            @click="activeStatus = 'all'"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all"
                            :class="
                                activeStatus === 'all'
                                    ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20'
                                    : 'bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white'
                            "
                        >
                            <Layers class="h-3.5 w-3.5" />
                            <span>{{
                                isRTL ? 'جميع السلاسل' : 'All Sagas'
                            }}</span>
                            <span
                                class="py-0.2 rounded-md px-1.5 text-[10px]"
                                :class="
                                    activeStatus === 'all'
                                        ? 'bg-slate-950/30 text-slate-950'
                                        : 'bg-white/10 text-slate-300'
                                "
                            >
                                {{ collections.length }}
                            </span>
                        </button>

                        <button
                            @click="activeStatus = 'complete'"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all"
                            :class="
                                activeStatus === 'complete'
                                    ? 'bg-emerald-500 text-slate-950 shadow-md shadow-emerald-500/20'
                                    : 'bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white'
                            "
                        >
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            <span>{{
                                isRTL ? 'مكتملة 100%' : 'Complete'
                            }}</span>
                            <span
                                class="py-0.2 rounded-md px-1.5 text-[10px]"
                                :class="
                                    activeStatus === 'complete'
                                        ? 'bg-slate-950/30 text-slate-950'
                                        : 'bg-white/10 text-slate-300'
                                "
                            >
                                {{ completeCount }}
                            </span>
                        </button>

                        <button
                            @click="activeStatus = 'in_progress'"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all"
                            :class="
                                activeStatus === 'in_progress'
                                    ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20'
                                    : 'bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white'
                            "
                        >
                            <Clock class="h-3.5 w-3.5" />
                            <span>{{
                                isRTL ? 'ينقصها أجزاء' : 'Missing Parts'
                            }}</span>
                            <span
                                class="py-0.2 rounded-md px-1.5 text-[10px]"
                                :class="
                                    activeStatus === 'in_progress'
                                        ? 'bg-slate-950/30 text-slate-950'
                                        : 'bg-white/10 text-slate-300'
                                "
                            >
                                {{ inProgressCount }}
                            </span>
                        </button>

                        <button
                            @click="activeStatus = 'top_rated'"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all"
                            :class="
                                activeStatus === 'top_rated'
                                    ? 'bg-amber-400 text-slate-950 shadow-md shadow-amber-400/20'
                                    : 'bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white'
                            "
                        >
                            <Star class="h-3.5 w-3.5 fill-current" />
                            <span>{{
                                isRTL
                                    ? 'الأعلى تقييماً (8.0+)'
                                    : 'Top Rated (8.0+)'
                            }}</span>
                            <span
                                class="py-0.2 rounded-md px-1.5 text-[10px]"
                                :class="
                                    activeStatus === 'top_rated'
                                        ? 'bg-slate-950/30 text-slate-950'
                                        : 'bg-white/10 text-slate-300'
                                "
                            >
                                {{ topRatedCount }}
                            </span>
                        </button>

                        <button
                            @click="activeStatus = 'large'"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all"
                            :class="
                                activeStatus === 'large'
                                    ? 'bg-purple-500 text-slate-950 shadow-md shadow-purple-500/20'
                                    : 'bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white'
                            "
                        >
                            <Film class="h-3.5 w-3.5" />
                            <span>{{
                                isRTL
                                    ? 'سلاسل كبرى (4+ أفلام)'
                                    : 'Epic Sagas (4+)'
                            }}</span>
                            <span
                                class="py-0.2 rounded-md px-1.5 text-[10px]"
                                :class="
                                    activeStatus === 'large'
                                        ? 'bg-slate-950/30 text-slate-950'
                                        : 'bg-white/10 text-slate-300'
                                "
                            >
                                {{ largeSagasCount }}
                            </span>
                        </button>
                    </div>

                    <!-- Active Filter Indicator & Reset -->
                    <div class="flex items-center gap-3 text-xs text-slate-400">
                        <span>
                            {{
                                isRTL
                                    ? `عرض ${filteredAndSortedCollections.length} من أصل ${collections.length} سلسلة`
                                    : `Showing ${filteredAndSortedCollections.length} of ${collections.length} sagas`
                            }}
                        </span>
                        <button
                            v-if="hasActiveFilters"
                            @click="resetFilters"
                            class="flex cursor-pointer items-center gap-1 text-xs font-bold text-cyan-400 transition-colors hover:text-cyan-300"
                        >
                            <RotateCcw class="h-3 w-3" />
                            <span>{{ isRTL ? 'إعادة ضبط' : 'Reset' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Collections Grid -->
            <div
                v-if="filteredAndSortedCollections.length > 0"
                class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <Link
                    v-for="col in filteredAndSortedCollections"
                    :key="col.slug"
                    :href="`/collections/${col.slug}`"
                    class="group glass-panel relative flex cursor-pointer flex-col overflow-hidden rounded-3xl border border-white/10 bg-slate-900/40 transition-all duration-300 hover:border-cyan-500/40 hover:shadow-2xl hover:shadow-cyan-500/10"
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
                            class="absolute top-3 right-3 flex items-center gap-1.5"
                        >
                            <div
                                v-if="col.is_complete"
                                class="flex items-center gap-1.5 rounded-full border border-emerald-500/40 bg-emerald-950/80 px-3 py-1 text-xs font-black text-emerald-400 shadow-lg backdrop-blur-md"
                            >
                                <CheckCircle2 class="h-3.5 w-3.5" />
                                <span>{{ isRTL ? 'مكتملة' : 'Complete' }}</span>
                            </div>
                            <div
                                v-else
                                class="flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-black/70 px-3 py-1 text-xs font-black text-amber-300 shadow-lg backdrop-blur-md"
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
                            class="absolute bottom-3 left-3 flex items-center gap-1 rounded-lg border border-white/10 bg-black/60 px-2.5 py-0.5 text-xs font-semibold text-slate-300 backdrop-blur-md"
                        >
                            <Calendar class="h-3 w-3 text-cyan-400" />
                            <span>{{ col.year_span }}</span>
                        </div>

                        <!-- Progress Bar at bottom of media -->
                        <div
                            v-if="
                                col.total_parts &&
                                col.total_parts > col.movies_count
                            "
                            class="absolute inset-x-0 bottom-0 h-1 overflow-hidden bg-black/50"
                        >
                            <div
                                class="h-full bg-gradient-to-r from-amber-500 to-cyan-400 transition-all duration-500"
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
                                    class="line-clamp-1 text-base font-bold text-white transition-colors group-hover:text-cyan-400"
                                >
                                    {{ col.name }}
                                </h3>
                                <span
                                    v-if="
                                        col.missing_parts &&
                                        col.missing_parts.length > 0
                                    "
                                    class="shrink-0 rounded-md border border-amber-500/30 bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold text-amber-300"
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
                                    v-for="(m, idx) in (
                                        (Array.isArray(col.movies)
                                            ? col.movies
                                            : Object.values(
                                                  col.movies || {},
                                              )) as any[]
                                    ).slice(0, 3)"
                                    :key="(m as any).id"
                                    class="max-w-[140px] truncate rounded-md border border-white/10 bg-white/[0.05] px-2 py-0.5 text-[10px] font-medium text-slate-300"
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
                                    class="rounded-md border border-cyan-500/30 bg-cyan-500/20 px-1.5 py-0.5 text-[10px] font-bold text-cyan-300"
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
                            class="flex items-center justify-between border-t border-white/5 pt-2 text-xs text-slate-400"
                        >
                            <div
                                class="flex items-center gap-1 font-bold text-amber-400"
                            >
                                <Star class="h-3.5 w-3.5 fill-amber-400" />
                                <span>{{ col.avg_rating || '7.5' }}</span>
                            </div>

                            <span
                                class="inline-flex items-center gap-1 font-bold text-cyan-400 transition-transform group-hover:translate-x-1"
                            >
                                <span>{{
                                    isRTL ? 'عرض السلسلة' : 'Explore Saga'
                                }}</span>
                                <ArrowRight
                                    class="h-3.5 w-3.5"
                                    :class="isRTL ? 'rotate-180' : ''"
                                />
                            </span>
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Empty Filter State -->
            <div
                v-else-if="collections.length > 0"
                class="glass-panel mx-auto max-w-lg space-y-4 rounded-3xl border border-white/10 px-4 py-16 text-center"
            >
                <div
                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border border-cyan-500/20 bg-cyan-500/10 text-cyan-400"
                >
                    <Filter class="h-8 w-8" />
                </div>
                <h3 class="text-lg font-bold text-white">
                    {{
                        isRTL
                            ? 'لا توجد سلاسل مطابقة للتصفية'
                            : 'No Matching Collections Found'
                    }}
                </h3>
                <p class="text-xs leading-relaxed text-slate-400">
                    {{
                        isRTL
                            ? 'لم يتم العثور على أي سلسلة تطابق معايير البحث أو التصفية الحالية. يمكنك إعادة تعيين التصفية لعرض جميع السلاسل.'
                            : 'No movie collections match your current search or filter criteria. You can reset filters to view all sagas.'
                    }}
                </p>
                <button
                    @click="resetFilters"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-cyan-500 px-5 py-2.5 text-xs font-bold text-slate-950 transition-all hover:bg-cyan-400"
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
                class="glass-panel mx-auto max-w-lg space-y-4 rounded-3xl border border-white/10 px-4 py-16 text-center"
            >
                <div
                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border border-cyan-500/20 bg-cyan-500/10 text-cyan-400"
                >
                    <Layers class="h-8 w-8" />
                </div>
                <h3 class="text-lg font-bold text-white">
                    {{
                        isRTL
                            ? 'لم يتم العثور على سلاسل أفلام'
                            : 'No Movie Collections Found'
                    }}
                </h3>
                <p class="text-xs leading-relaxed text-slate-400">
                    {{
                        isRTL
                            ? 'عند قيامك بفهرسة مكتبة أفلامك التي تحتوي على أفلام ذات أجزاء متعددة (مثل هاري بوتر، حرب النجوم، فاست آند فيوريوس)، سيتم تجميعها تلقائياً هنا.'
                            : 'When scanning your movie library with multi-part franchises (Harry Potter, Star Wars, Fast & Furious), they will automatically be grouped into collections here.'
                    }}
                </p>
                <Link
                    href="/scanner"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-cyan-500 px-5 py-2.5 text-xs font-bold text-slate-950 transition-all hover:bg-cyan-400"
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
