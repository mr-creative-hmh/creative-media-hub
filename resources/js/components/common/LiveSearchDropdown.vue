<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import {
    Search,
    X,
    Star,
    Film,
    Tv,
    Layers,
    Sparkles,
    Loader2,
    ArrowRight,
    ArrowLeft,
} from '@lucide/vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        placeholder?: string;
        variant?: 'navbar' | 'large';
        autoNavigate?: boolean; // if true, pressing enter with no selection goes to /movies?search=...
    }>(),
    {
        modelValue: '',
        placeholder: '',
        variant: 'navbar',
        autoNavigate: true,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', val: string): void;
    (e: 'submit', query: string): void;
    (e: 'select', item: SearchItem): void;
}>();

interface SearchItem {
    id: number | string;
    slug: string;
    title: string;
    title_ar: string | null;
    year: number | null;
    rating: number | null;
    poster: string | null;
    quality: string;
    type: 'movie' | 'series' | 'collection';
    url: string;
}

interface SearchResponse {
    query: string;
    movies: SearchItem[];
    collections?: SearchItem[];
    series: SearchItem[];
    total: number;
}

const { t, isRTL } = useI18n();

const query = ref(props.modelValue);
const isOpen = ref(false);
const isLoading = ref(false);
const movies = ref<SearchItem[]>([]);
const collections = ref<SearchItem[]>([]);
const series = ref<SearchItem[]>([]);
const totalResults = ref(0);
const selectedIndex = ref(-1);

const containerRef = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);

// In-memory instant client-side cache: Map<query, SearchResponse>
const searchCache = new Map<string, SearchResponse>();

// Active abort controller to cancel in-flight requests
let abortController: AbortController | null = null;
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

// Combined list of all items for keyboard navigation
const allItems = computed<SearchItem[]>(() => [
    ...movies.value,
    ...collections.value,
    ...series.value,
]);

watch(
    () => props.modelValue,
    (newVal) => {
        if (newVal !== query.value) {
            query.value = newVal;
        }
    },
);

watch(query, (newVal) => {
    emit('update:modelValue', newVal);
    onQueryChange(newVal);
});

const onQueryChange = (val: string) => {
    const trimmed = val.trim();
    selectedIndex.value = -1;

    if (trimmed.length < 2) {
        movies.value = [];
        collections.value = [];
        series.value = [];
        totalResults.value = 0;
        isLoading.value = false;
        isOpen.value = false;
        if (abortController) {
            abortController.abort();
            abortController = null;
        }
        return;
    }

    // 1. Check in-memory instant cache (0ms response)
    const cacheKey = trimmed.toLowerCase();
    if (searchCache.has(cacheKey)) {
        const cached = searchCache.get(cacheKey)!;
        movies.value = cached.movies || [];
        collections.value = cached.collections || [];
        series.value = cached.series || [];
        totalResults.value = cached.total || 0;
        isLoading.value = false;
        isOpen.value = true;
        return;
    }

    // 2. Debounce fetch for new queries (200ms)
    if (debounceTimer) clearTimeout(debounceTimer);
    isLoading.value = true;
    isOpen.value = true;

    debounceTimer = setTimeout(() => {
        executeSearch(trimmed);
    }, 200);
};

const executeSearch = async (searchTerm: string) => {
    if (abortController) {
        abortController.abort();
    }
    abortController = new AbortController();

    try {
        const response = await fetch(
            `/api/search/instant?q=${encodeURIComponent(searchTerm)}`,
            {
                signal: abortController.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) throw new Error('Search failed');

        const data: SearchResponse = await response.json();

        // Save to cache
        searchCache.set(searchTerm.toLowerCase(), data);

        // Update if query still matches
        if (query.value.trim().toLowerCase() === searchTerm.toLowerCase()) {
            movies.value = data.movies || [];
            collections.value = data.collections || [];
            series.value = data.series || [];
            totalResults.value = data.total || 0;
            isLoading.value = false;
            isOpen.value = true;
        }
    } catch (err: any) {
        if (err.name !== 'AbortError') {
            isLoading.value = false;
        }
    }
};

const handleSelect = (item: SearchItem) => {
    isOpen.value = false;
    emit('select', item);
    router.visit(item.url);
};

const handleEnter = () => {
    if (
        selectedIndex.value >= 0 &&
        selectedIndex.value < allItems.value.length
    ) {
        handleSelect(allItems.value[selectedIndex.value]);
        return;
    }

    if (query.value.trim()) {
        isOpen.value = false;
        emit('submit', query.value.trim());
        if (props.autoNavigate) {
            router.get('/movies', { search: query.value.trim() });
        }
    }
};

const handleKeyDown = (e: KeyboardEvent) => {
    if (!isOpen.value || allItems.value.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex.value = (selectedIndex.value + 1) % allItems.value.length;
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex.value =
            selectedIndex.value <= 0
                ? allItems.value.length - 1
                : selectedIndex.value - 1;
    } else if (e.key === 'Escape') {
        isOpen.value = false;
    }
};

const clearQuery = () => {
    query.value = '';
    isOpen.value = false;
    movies.value = [];
    collections.value = [];
    series.value = [];
    totalResults.value = 0;
    emit('submit', '');
    inputRef.value?.focus();
};

const onFocus = () => {
    if (
        query.value.trim().length >= 2 &&
        (movies.value.length > 0 ||
            collections.value.length > 0 ||
            series.value.length > 0 ||
            !isLoading.value)
    ) {
        isOpen.value = true;
    }
};

// Dismiss when clicking outside
const handleClickOutside = (e: MouseEvent) => {
    if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    if (debounceTimer) clearTimeout(debounceTimer);
    if (abortController) abortController.abort();
});
</script>

<template>
    <div ref="containerRef" class="relative w-full" @keydown="handleKeyDown">
        <!-- Search Input Bar -->
        <div class="relative w-full">
            <Search
                class="pointer-events-none absolute top-1/2 -translate-y-1/2 text-slate-400 transition-colors"
                :class="[
                    isRTL ? 'right-3.5' : 'left-3.5',
                    variant === 'large' ? 'h-5 w-5' : 'h-4 w-4',
                ]"
            />

            <input
                ref="inputRef"
                type="text"
                v-model="query"
                @focus="onFocus"
                @keyup.enter="handleEnter"
                :placeholder="
                    placeholder ||
                    (isRTL
                        ? 'ابحث في الأفلام والمسلسلات...'
                        : 'Search movies & series...')
                "
                class="w-full border border-white/10 bg-white/[0.04] font-sans text-white shadow-inner transition-all placeholder:text-slate-500 focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 focus:outline-none"
                :class="[
                    variant === 'large'
                        ? 'h-12 rounded-2xl text-sm'
                        : 'h-10 rounded-2xl text-xs sm:text-sm',
                    isRTL
                        ? variant === 'large'
                            ? 'pr-11 pl-20'
                            : 'pr-10 pl-16'
                        : variant === 'large'
                          ? 'pr-20 pl-11'
                          : 'pr-16 pl-10',
                ]"
                autocomplete="off"
                spellcheck="false"
            />

            <!-- Trailing Controls: Loading Spinner, Clear Button, Enter hint -->
            <div
                class="absolute top-1/2 flex -translate-y-1/2 items-center gap-1.5"
                :class="isRTL ? 'left-2.5' : 'right-2.5'"
            >
                <Loader2
                    v-if="isLoading"
                    class="h-4 w-4 animate-spin text-cyan-400"
                />

                <button
                    v-if="query"
                    type="button"
                    @click="clearQuery"
                    class="cursor-pointer rounded-lg p-1 text-slate-400 transition-colors hover:bg-white/10 hover:text-white"
                    title="Clear search"
                >
                    <X class="h-3.5 w-3.5" />
                </button>

                <button
                    type="button"
                    @click="handleEnter"
                    class="hidden cursor-pointer items-center gap-1 rounded-xl border border-cyan-500/30 bg-cyan-500/20 px-2.5 py-1 text-[11px] font-bold text-cyan-300 shadow-sm transition-all hover:bg-cyan-500/30 sm:flex"
                >
                    <span>{{ isRTL ? 'بحث' : 'Search' }}</span>
                </button>
            </div>
        </div>

        <!-- YTS-Style Floating Live Search Results Dropdown -->
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1 scale-[0.98]"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-1 scale-[0.98]"
        >
            <div
                v-if="isOpen && query.trim().length >= 2"
                class="absolute top-full right-0 left-0 z-50 mt-2 overflow-hidden rounded-2xl border border-white/15 bg-[#0d1117]/95 shadow-2xl shadow-black/80 backdrop-blur-2xl"
            >
                <!-- Loading State Shimmer (when no cached results yet) -->
                <div
                    v-if="isLoading && totalResults === 0"
                    class="flex items-center justify-center gap-3 p-5 text-xs text-slate-400"
                >
                    <Loader2 class="h-4 w-4 animate-spin text-cyan-400" />
                    <span>{{
                        isRTL
                            ? 'جاري البحث في المكتبة...'
                            : 'Searching library...'
                    }}</span>
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="!isLoading && totalResults === 0"
                    class="space-y-1.5 p-6 text-center text-slate-400"
                >
                    <p class="text-xs font-medium text-slate-300 sm:text-sm">
                        {{
                            isRTL
                                ? 'لم يتم العثور على نتائج لـ'
                                : 'No matches found for'
                        }}
                        <span class="font-bold text-white">"{{ query }}"</span>
                    </p>
                    <p class="text-[11px] text-slate-500">
                        {{
                            isRTL
                                ? 'جرّب البحث بكلمات أخرى أو تحقق من الحروف'
                                : 'Try searching by a different word, actor, or year'
                        }}
                    </p>
                </div>

                <!-- Results List -->
                <div
                    v-else
                    class="custom-scrollbar max-h-[75vh] divide-y divide-white/5 overflow-y-auto"
                >
                    <!-- 1. Movies Section -->
                    <div v-if="movies.length > 0" class="p-2">
                        <div
                            class="flex items-center justify-between px-3 py-1.5 text-[10px] font-black tracking-wider text-cyan-400 uppercase"
                        >
                            <span class="flex items-center gap-1.5">
                                <Film class="h-3 w-3" />
                                <span>{{ isRTL ? 'الأفلام' : 'Movies' }}</span>
                            </span>
                            <span class="text-slate-500">{{
                                movies.length
                            }}</span>
                        </div>

                        <div class="mt-1 space-y-1">
                            <div
                                v-for="movie in movies"
                                :key="movie.id"
                                @click="handleSelect(movie)"
                                class="group flex cursor-pointer items-center gap-3 rounded-xl p-2 transition-all select-none"
                                :class="
                                    allItems[selectedIndex]?.id === movie.id &&
                                    allItems[selectedIndex]?.type === 'movie'
                                        ? 'border border-cyan-500/30 bg-cyan-500/15'
                                        : 'border border-transparent hover:bg-white/5'
                                "
                            >
                                <!-- Poster Thumbnail -->
                                <div
                                    class="relative h-14 w-10 shrink-0 overflow-hidden rounded-lg border border-white/10 bg-slate-800 shadow-sm"
                                >
                                    <img
                                        v-if="movie.poster"
                                        :src="movie.poster"
                                        :alt="movie.title"
                                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                        loading="lazy"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center text-slate-600"
                                    >
                                        <Film class="h-5 w-5" />
                                    </div>
                                </div>

                                <!-- Movie Details -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h4
                                            class="truncate text-xs font-bold text-white transition-colors group-hover:text-cyan-400 sm:text-sm"
                                        >
                                            {{ movie.title }}
                                        </h4>
                                        <span
                                            v-if="movie.year"
                                            class="shrink-0 font-mono text-[11px] text-slate-400"
                                        >
                                            ({{ movie.year }})
                                        </span>
                                    </div>

                                    <p
                                        v-if="
                                            movie.title_ar &&
                                            movie.title_ar !== movie.title
                                        "
                                        class="mt-0.5 truncate text-[11px] text-slate-400"
                                        dir="rtl"
                                    >
                                        {{ movie.title_ar }}
                                    </p>

                                    <!-- Badges: Rating & Quality -->
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <div
                                            v-if="movie.rating"
                                            class="flex items-center gap-1 rounded-md border border-amber-400/20 bg-amber-400/10 px-1.5 py-0.5 text-[10px] font-black text-amber-400"
                                        >
                                            <Star
                                                class="h-2.5 w-2.5 fill-amber-400"
                                            />
                                            <span>{{ movie.rating }}</span>
                                        </div>

                                        <span
                                            class="rounded-md border px-1.5 py-0.5 text-[10px] font-black tracking-wider uppercase"
                                            :class="
                                                movie.quality === '4K'
                                                    ? 'border-amber-500/30 bg-amber-500/20 text-amber-300'
                                                    : movie.quality === '1080p'
                                                      ? 'border-cyan-500/30 bg-cyan-500/20 text-cyan-300'
                                                      : 'border-slate-600/40 bg-slate-700/40 text-slate-300'
                                            "
                                        >
                                            {{ movie.quality }}
                                        </span>

                                        <span
                                            class="ml-auto text-[10px] font-medium text-slate-500"
                                        >
                                            {{ isRTL ? 'فيلم' : 'Movie' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Arrow indicator on hover -->
                                <div
                                    class="shrink-0 px-1 text-slate-500 transition-colors group-hover:text-cyan-400"
                                >
                                    <ArrowLeft
                                        v-if="isRTL"
                                        class="h-4 w-4 transition-transform group-hover:-translate-x-1"
                                    />
                                    <ArrowRight
                                        v-else
                                        class="h-4 w-4 transition-transform group-hover:translate-x-1"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Movie Collections Section -->
                    <div v-if="collections.length > 0" class="p-2">
                        <div
                            class="flex items-center justify-between px-3 py-1.5 text-[10px] font-black tracking-wider text-amber-400 uppercase"
                        >
                            <span class="flex items-center gap-1.5">
                                <Layers class="h-3 w-3" />
                                <span>{{
                                    isRTL
                                        ? 'سلاسل ومجموعات الأفلام'
                                        : 'Franchises & Collections'
                                }}</span>
                            </span>
                            <span class="text-slate-500">{{
                                collections.length
                            }}</span>
                        </div>

                        <div class="mt-1 space-y-1">
                            <div
                                v-for="col in collections"
                                :key="col.slug"
                                @click="handleSelect(col)"
                                class="group flex cursor-pointer items-center gap-3 rounded-xl p-2 transition-all select-none"
                                :class="
                                    allItems[selectedIndex]?.slug ===
                                        col.slug &&
                                    allItems[selectedIndex]?.type ===
                                        'collection'
                                        ? 'border border-amber-500/30 bg-amber-500/15'
                                        : 'border border-transparent hover:bg-white/5'
                                "
                            >
                                <!-- Poster Thumbnail -->
                                <div
                                    class="relative h-14 w-10 shrink-0 overflow-hidden rounded-lg border border-white/10 bg-slate-800 shadow-sm"
                                >
                                    <img
                                        v-if="col.poster"
                                        :src="col.poster"
                                        :alt="col.title"
                                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                        loading="lazy"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center text-slate-600"
                                    >
                                        <Layers class="h-5 w-5" />
                                    </div>
                                </div>

                                <!-- Collection Details -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h4
                                            class="truncate text-xs font-bold text-white transition-colors group-hover:text-amber-400 sm:text-sm"
                                        >
                                            {{ col.title }}
                                        </h4>
                                        <span
                                            v-if="col.year"
                                            class="shrink-0 font-mono text-[11px] text-slate-400"
                                        >
                                            ({{ col.year }})
                                        </span>
                                    </div>

                                    <!-- Badges: Rating & Movie Count -->
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <div
                                            v-if="col.rating"
                                            class="flex items-center gap-1 rounded-md border border-amber-400/20 bg-amber-400/10 px-1.5 py-0.5 text-[10px] font-black text-amber-400"
                                        >
                                            <Star
                                                class="h-2.5 w-2.5 fill-amber-400"
                                            />
                                            <span>{{ col.rating }}</span>
                                        </div>

                                        <span
                                            class="rounded-md border border-amber-500/30 bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-black text-amber-300"
                                        >
                                            {{ col.quality }}
                                        </span>

                                        <span
                                            class="ml-auto text-[10px] font-medium text-slate-500"
                                        >
                                            {{ isRTL ? 'سلسلة' : 'Collection' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Arrow indicator on hover -->
                                <div
                                    class="shrink-0 px-1 text-slate-500 transition-colors group-hover:text-amber-400"
                                >
                                    <ArrowLeft
                                        v-if="isRTL"
                                        class="h-4 w-4 transition-transform group-hover:-translate-x-1"
                                    />
                                    <ArrowRight
                                        v-else
                                        class="h-4 w-4 transition-transform group-hover:translate-x-1"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. TV Series Section -->
                    <div v-if="series.length > 0" class="p-2">
                        <div
                            class="flex items-center justify-between px-3 py-1.5 text-[10px] font-black tracking-wider text-purple-400 uppercase"
                        >
                            <span class="flex items-center gap-1.5">
                                <Tv class="h-3 w-3" />
                                <span>{{
                                    isRTL ? 'المسلسلات التلفزيونية' : 'TV Series'
                                }}</span>
                            </span>
                            <span class="text-slate-500">{{
                                series.length
                            }}</span>
                        </div>

                        <div class="mt-1 space-y-1">
                            <div
                                v-for="show in series"
                                :key="show.id"
                                @click="handleSelect(show)"
                                class="group flex cursor-pointer items-center gap-3 rounded-xl p-2 transition-all select-none"
                                :class="
                                    allItems[selectedIndex]?.id === show.id &&
                                    allItems[selectedIndex]?.type === 'series'
                                        ? 'border border-purple-500/30 bg-purple-500/15'
                                        : 'border border-transparent hover:bg-white/5'
                                "
                            >
                                <!-- Poster Thumbnail -->
                                <div
                                    class="relative h-14 w-10 shrink-0 overflow-hidden rounded-lg border border-white/10 bg-slate-800 shadow-sm"
                                >
                                    <img
                                        v-if="show.poster"
                                        :src="show.poster"
                                        :alt="show.title"
                                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                        loading="lazy"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center text-slate-600"
                                    >
                                        <Tv class="h-5 w-5" />
                                    </div>
                                </div>

                                <!-- Series Details -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h4
                                            class="truncate text-xs font-bold text-white transition-colors group-hover:text-purple-400 sm:text-sm"
                                        >
                                            {{ show.title }}
                                        </h4>
                                        <span
                                            v-if="show.year"
                                            class="shrink-0 font-mono text-[11px] text-slate-400"
                                        >
                                            ({{ show.year }})
                                        </span>
                                    </div>

                                    <p
                                        v-if="
                                            show.title_ar &&
                                            show.title_ar !== show.title
                                        "
                                        class="mt-0.5 truncate text-[11px] text-slate-400"
                                        dir="rtl"
                                    >
                                        {{ show.title_ar }}
                                    </p>

                                    <!-- Badges: Rating & Seasons -->
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <div
                                            v-if="show.rating"
                                            class="flex items-center gap-1 rounded-md border border-amber-400/20 bg-amber-400/10 px-1.5 py-0.5 text-[10px] font-black text-amber-400"
                                        >
                                            <Star
                                                class="h-2.5 w-2.5 fill-amber-400"
                                            />
                                            <span>{{ show.rating }}</span>
                                        </div>

                                        <span
                                            class="rounded-md border border-purple-500/30 bg-purple-500/20 px-1.5 py-0.5 font-mono text-[10px] font-black text-purple-300"
                                        >
                                            {{ show.quality }}
                                        </span>

                                        <span
                                            class="ml-auto text-[10px] font-medium text-slate-500"
                                        >
                                            {{ isRTL ? 'مسلسل' : 'Series' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Arrow indicator on hover -->
                                <div
                                    class="shrink-0 px-1 text-slate-500 transition-colors group-hover:text-purple-400"
                                >
                                    <ArrowLeft
                                        v-if="isRTL"
                                        class="h-4 w-4 transition-transform group-hover:-translate-x-1"
                                    />
                                    <ArrowRight
                                        v-else
                                        class="h-4 w-4 transition-transform group-hover:translate-x-1"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer: View All Results Action Bar -->
                    <div
                        class="flex items-center justify-between border-t border-white/5 bg-white/[0.02] p-2"
                    >
                        <button
                            type="button"
                            @click="handleEnter"
                            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-cyan-500/25 bg-cyan-500/10 px-3 py-2 text-xs font-bold text-cyan-400 transition-all hover:bg-cyan-500/20"
                        >
                            <Sparkles class="h-3.5 w-3.5" />
                            <span>{{
                                isRTL
                                    ? `عرض كافة النتائج لـ "${query}"`
                                    : `View all results for "${query}"`
                            }}</span>
                            <span
                                class="rounded-md bg-white/10 px-1.5 py-0.5 font-mono text-[10px]"
                                >Enter ↵</span
                            >
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>
