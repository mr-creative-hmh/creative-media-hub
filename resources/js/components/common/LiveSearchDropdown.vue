<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import {
    Search, X, Star, Film, Tv, Layers, Sparkles, Loader2, ArrowRight, ArrowLeft
} from 'lucide-vue-next';

const props = withDefaults(defineProps<{
    modelValue?: string;
    placeholder?: string;
    variant?: 'navbar' | 'large';
    autoNavigate?: boolean; // if true, pressing enter with no selection goes to /movies?search=...
}>(), {
    modelValue: '',
    placeholder: '',
    variant: 'navbar',
    autoNavigate: true,
});

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

watch(() => props.modelValue, (newVal) => {
    if (newVal !== query.value) {
        query.value = newVal;
    }
});

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
        const response = await fetch(`/api/search/instant?q=${encodeURIComponent(searchTerm)}`, {
            signal: abortController.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

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
    if (selectedIndex.value >= 0 && selectedIndex.value < allItems.value.length) {
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
        selectedIndex.value = selectedIndex.value <= 0 ? allItems.value.length - 1 : selectedIndex.value - 1;
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
    if (query.value.trim().length >= 2 && (movies.value.length > 0 || collections.value.length > 0 || series.value.length > 0 || !isLoading.value)) {
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
                class="absolute top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none transition-colors"
                :class="[
                    isRTL ? 'right-3.5' : 'left-3.5',
                    variant === 'large' ? 'w-5 h-5' : 'w-4 h-4'
                ]"
            />

            <input
                ref="inputRef"
                type="text"
                v-model="query"
                @focus="onFocus"
                @keyup.enter="handleEnter"
                :placeholder="placeholder || (isRTL ? 'ابحث في الأفلام والمسلسلات...' : 'Search movies & series...')"
                class="w-full text-white placeholder:text-slate-500 bg-white/[0.04] border border-white/10 focus:outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all font-sans shadow-inner"
                :class="[
                    variant === 'large'
                        ? 'h-12 text-sm rounded-2xl'
                        : 'h-10 text-xs sm:text-sm rounded-2xl',
                    isRTL
                        ? (variant === 'large' ? 'pr-11 pl-20' : 'pr-10 pl-16')
                        : (variant === 'large' ? 'pl-11 pr-20' : 'pl-10 pr-16'),
                ]"
                autocomplete="off"
                spellcheck="false"
            />

            <!-- Trailing Controls: Loading Spinner, Clear Button, Enter hint -->
            <div
                class="absolute top-1/2 -translate-y-1/2 flex items-center gap-1.5"
                :class="isRTL ? 'left-2.5' : 'right-2.5'"
            >
                <Loader2
                    v-if="isLoading"
                    class="w-4 h-4 text-cyan-400 animate-spin"
                />

                <button
                    v-if="query"
                    type="button"
                    @click="clearQuery"
                    class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                    title="Clear search"
                >
                    <X class="w-3.5 h-3.5" />
                </button>

                <button
                    type="button"
                    @click="handleEnter"
                    class="hidden sm:flex items-center gap-1 px-2.5 py-1 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 border border-cyan-500/30 text-cyan-300 text-[11px] font-bold transition-all cursor-pointer shadow-sm"
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
                class="absolute left-0 right-0 top-full mt-2 z-50 bg-[#0d1117]/95 backdrop-blur-2xl border border-white/15 rounded-2xl shadow-2xl shadow-black/80 overflow-hidden"
            >
                <!-- Loading State Shimmer (when no cached results yet) -->
                <div v-if="isLoading && totalResults === 0" class="p-5 flex items-center justify-center gap-3 text-slate-400 text-xs">
                    <Loader2 class="w-4 h-4 text-cyan-400 animate-spin" />
                    <span>{{ isRTL ? 'جاري البحث في المكتبة...' : 'Searching library...' }}</span>
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="!isLoading && totalResults === 0"
                    class="p-6 text-center text-slate-400 space-y-1.5"
                >
                    <p class="text-xs sm:text-sm font-medium text-slate-300">
                        {{ isRTL ? 'لم يتم العثور على نتائج لـ' : 'No matches found for' }}
                        <span class="text-white font-bold">"{{ query }}"</span>
                    </p>
                    <p class="text-[11px] text-slate-500">
                        {{ isRTL ? 'جرّب البحث بكلمات أخرى أو تحقق من الحروف' : 'Try searching by a different word, actor, or year' }}
                    </p>
                </div>

                <!-- Results List -->
                <div v-else class="divide-y divide-white/5 max-h-[75vh] overflow-y-auto custom-scrollbar">
                    <!-- 1. Movies Section -->
                    <div v-if="movies.length > 0" class="p-2">
                        <div class="px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-cyan-400 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <Film class="w-3 h-3" />
                                <span>{{ isRTL ? 'الأفلام' : 'Movies' }}</span>
                            </span>
                            <span class="text-slate-500">{{ movies.length }}</span>
                        </div>

                        <div class="space-y-1 mt-1">
                            <div
                                v-for="movie in movies"
                                :key="movie.id"
                                @click="handleSelect(movie)"
                                class="group flex items-center gap-3 p-2 rounded-xl transition-all cursor-pointer select-none"
                                :class="allItems[selectedIndex]?.id === movie.id && allItems[selectedIndex]?.type === 'movie'
                                    ? 'bg-cyan-500/15 border border-cyan-500/30'
                                    : 'hover:bg-white/5 border border-transparent'"
                            >
                                <!-- Poster Thumbnail -->
                                <div class="w-10 h-14 rounded-lg overflow-hidden bg-slate-800 shrink-0 relative border border-white/10 shadow-sm">
                                    <img
                                        v-if="movie.poster"
                                        :src="movie.poster"
                                        :alt="movie.title"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                        loading="lazy"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center text-slate-600">
                                        <Film class="w-5 h-5" />
                                    </div>
                                </div>

                                <!-- Movie Details -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-xs sm:text-sm font-bold text-white group-hover:text-cyan-400 transition-colors truncate">
                                            {{ movie.title }}
                                        </h4>
                                        <span v-if="movie.year" class="text-[11px] text-slate-400 font-mono shrink-0">
                                            ({{ movie.year }})
                                        </span>
                                    </div>

                                    <p v-if="movie.title_ar && movie.title_ar !== movie.title" class="text-[11px] text-slate-400 truncate mt-0.5" dir="rtl">
                                        {{ movie.title_ar }}
                                    </p>

                                    <!-- Badges: Rating & Quality -->
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <div v-if="movie.rating" class="flex items-center gap-1 text-[10px] font-black text-amber-400 bg-amber-400/10 px-1.5 py-0.5 rounded-md border border-amber-400/20">
                                            <Star class="w-2.5 h-2.5 fill-amber-400" />
                                            <span>{{ movie.rating }}</span>
                                        </div>

                                        <span
                                            class="text-[10px] font-black px-1.5 py-0.5 rounded-md border uppercase tracking-wider"
                                            :class="movie.quality === '4K'
                                                ? 'bg-amber-500/20 text-amber-300 border-amber-500/30'
                                                : (movie.quality === '1080p'
                                                    ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30'
                                                    : 'bg-slate-700/40 text-slate-300 border-slate-600/40')"
                                        >
                                            {{ movie.quality }}
                                        </span>

                                        <span class="text-[10px] text-slate-500 font-medium ml-auto">
                                            {{ isRTL ? 'فيلم' : 'Movie' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Arrow indicator on hover -->
                                <div class="shrink-0 text-slate-500 group-hover:text-cyan-400 transition-colors px-1">
                                    <ArrowLeft v-if="isRTL" class="w-4 h-4 transition-transform group-hover:-translate-x-1" />
                                    <ArrowRight v-else class="w-4 h-4 transition-transform group-hover:translate-x-1" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Movie Collections Section -->
                    <div v-if="collections.length > 0" class="p-2">
                        <div class="px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-amber-400 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <Layers class="w-3 h-3" />
                                <span>{{ isRTL ? 'سلاسل ومجموعات الأفلام' : 'Franchises & Collections' }}</span>
                            </span>
                            <span class="text-slate-500">{{ collections.length }}</span>
                        </div>

                        <div class="space-y-1 mt-1">
                            <div
                                v-for="col in collections"
                                :key="col.slug"
                                @click="handleSelect(col)"
                                class="group flex items-center gap-3 p-2 rounded-xl transition-all cursor-pointer select-none"
                                :class="allItems[selectedIndex]?.slug === col.slug && allItems[selectedIndex]?.type === 'collection'
                                    ? 'bg-amber-500/15 border border-amber-500/30'
                                    : 'hover:bg-white/5 border border-transparent'"
                            >
                                <!-- Poster Thumbnail -->
                                <div class="w-10 h-14 rounded-lg overflow-hidden bg-slate-800 shrink-0 relative border border-white/10 shadow-sm">
                                    <img
                                        v-if="col.poster"
                                        :src="col.poster"
                                        :alt="col.title"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                        loading="lazy"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center text-slate-600">
                                        <Layers class="w-5 h-5" />
                                    </div>
                                </div>

                                <!-- Collection Details -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-xs sm:text-sm font-bold text-white group-hover:text-amber-400 transition-colors truncate">
                                            {{ col.title }}
                                        </h4>
                                        <span v-if="col.year" class="text-[11px] text-slate-400 font-mono shrink-0">
                                            ({{ col.year }})
                                        </span>
                                    </div>

                                    <!-- Badges: Rating & Movie Count -->
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <div v-if="col.rating" class="flex items-center gap-1 text-[10px] font-black text-amber-400 bg-amber-400/10 px-1.5 py-0.5 rounded-md border border-amber-400/20">
                                            <Star class="w-2.5 h-2.5 fill-amber-400" />
                                            <span>{{ col.rating }}</span>
                                        </div>

                                        <span class="text-[10px] font-black px-1.5 py-0.5 rounded-md bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                            {{ col.quality }}
                                        </span>

                                        <span class="text-[10px] text-slate-500 font-medium ml-auto">
                                            {{ isRTL ? 'سلسلة' : 'Collection' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Arrow indicator on hover -->
                                <div class="shrink-0 text-slate-500 group-hover:text-amber-400 transition-colors px-1">
                                    <ArrowLeft v-if="isRTL" class="w-4 h-4 transition-transform group-hover:-translate-x-1" />
                                    <ArrowRight v-else class="w-4 h-4 transition-transform group-hover:translate-x-1" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. TV Series Section -->
                    <div v-if="series.length > 0" class="p-2">
                        <div class="px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-purple-400 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <Tv class="w-3 h-3" />
                                <span>{{ isRTL ? 'المسلسلات التلفزيونية' : 'TV Series' }}</span>
                            </span>
                            <span class="text-slate-500">{{ series.length }}</span>
                        </div>

                        <div class="space-y-1 mt-1">
                            <div
                                v-for="show in series"
                                :key="show.id"
                                @click="handleSelect(show)"
                                class="group flex items-center gap-3 p-2 rounded-xl transition-all cursor-pointer select-none"
                                :class="allItems[selectedIndex]?.id === show.id && allItems[selectedIndex]?.type === 'series'
                                    ? 'bg-purple-500/15 border border-purple-500/30'
                                    : 'hover:bg-white/5 border border-transparent'"
                            >
                                <!-- Poster Thumbnail -->
                                <div class="w-10 h-14 rounded-lg overflow-hidden bg-slate-800 shrink-0 relative border border-white/10 shadow-sm">
                                    <img
                                        v-if="show.poster"
                                        :src="show.poster"
                                        :alt="show.title"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                        loading="lazy"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center text-slate-600">
                                        <Tv class="w-5 h-5" />
                                    </div>
                                </div>

                                <!-- Series Details -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-xs sm:text-sm font-bold text-white group-hover:text-purple-400 transition-colors truncate">
                                            {{ show.title }}
                                        </h4>
                                        <span v-if="show.year" class="text-[11px] text-slate-400 font-mono shrink-0">
                                            ({{ show.year }})
                                        </span>
                                    </div>

                                    <p v-if="show.title_ar && show.title_ar !== show.title" class="text-[11px] text-slate-400 truncate mt-0.5" dir="rtl">
                                        {{ show.title_ar }}
                                    </p>

                                    <!-- Badges: Rating & Seasons -->
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <div v-if="show.rating" class="flex items-center gap-1 text-[10px] font-black text-amber-400 bg-amber-400/10 px-1.5 py-0.5 rounded-md border border-amber-400/20">
                                            <Star class="w-2.5 h-2.5 fill-amber-400" />
                                            <span>{{ show.rating }}</span>
                                        </div>

                                        <span class="text-[10px] font-black px-1.5 py-0.5 rounded-md bg-purple-500/20 text-purple-300 border border-purple-500/30 font-mono">
                                            {{ show.quality }}
                                        </span>

                                        <span class="text-[10px] text-slate-500 font-medium ml-auto">
                                            {{ isRTL ? 'مسلسل' : 'Series' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Arrow indicator on hover -->
                                <div class="shrink-0 text-slate-500 group-hover:text-purple-400 transition-colors px-1">
                                    <ArrowLeft v-if="isRTL" class="w-4 h-4 transition-transform group-hover:-translate-x-1" />
                                    <ArrowRight v-else class="w-4 h-4 transition-transform group-hover:translate-x-1" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer: View All Results Action Bar -->
                    <div class="p-2 bg-white/[0.02] border-t border-white/5 flex items-center justify-between">
                        <button
                            type="button"
                            @click="handleEnter"
                            class="w-full py-2 px-3 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/25 text-cyan-400 text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <Sparkles class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? `عرض كافة النتائج لـ "${query}"` : `View all results for "${query}"` }}</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded-md bg-white/10 font-mono">Enter ↵</span>
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>
