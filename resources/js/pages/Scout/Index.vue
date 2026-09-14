<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/components/layout/AppLayout.vue';
import { useI18n } from '@/i18n/useI18n';
import TorrentSuggestionsModal from '@/components/scout/TorrentSuggestionsModal.vue';
import FastOrganizeModal from '@/components/scout/FastOrganizeModal.vue';
import {
    Compass, RefreshCw, Search, Film, Tv, Layers, AlertCircle,
    Download, Check, FolderSync, Sparkles, Loader2, Calendar,
    TrendingUp, Award, Clock, Star, ArrowRight, ShieldCheck,
    CheckCircle2, XCircle, ChevronDown, ChevronUp, Eye, X
} from 'lucide-vue-next';

interface MetricData {
    missing_episodes_count?: number;
    missing_seasons_count?: number;
    missing_movies_count?: number;
    incomplete_franchises_count?: number;
    incomplete_series_count?: number;
    total_gaps_count?: number;
    series_completion_rate?: number;
    collections_completion_rate?: number;
    total_library_series?: number;
    total_library_movies?: number;
}

const props = defineProps<{
    initialMetrics: MetricData;
    initialSeriesGaps?: any[];
    initialCollectionGaps?: any[];
    initialEpisodes?: any[];
    initialSeasons?: any[];
    initialMovies?: any[];
    initialTrending?: any[];
}>();

const { t, isRTL } = useI18n();

const metrics = ref<MetricData>(props.initialMetrics || {});
const seriesGaps = ref<any[]>(props.initialSeriesGaps || []);
const collectionGaps = ref<any[]>(props.initialCollectionGaps || []);
const flatEpisodes = ref<any[]>(props.initialEpisodes || []);
const flatSeasons = ref<any[]>(props.initialSeasons || []);
const flatMovies = ref<any[]>(props.initialMovies || []);

const activeTab = ref<'all' | 'discover' | 'series' | 'collections' | 'episodes' | 'movies'>('all');
const searchQuery = ref('');
const loading = ref(false);
const refreshing = ref(false);

// Discover Tab State
const discoverQuery = ref('');
const discoverType = ref<'all' | 'movie' | 'tv'>('all');
const discoverStyle = ref<'all' | 'animated' | 'live_action'>('all');
const discoverResults = ref<any[]>(props.initialTrending || []);
const discoverLoading = ref(false);

const filteredDiscoverResults = computed(() => {
    let items = discoverResults.value;
    if (discoverStyle.value === 'animated') {
        items = items.filter((it: any) => it.is_animated === true);
    } else if (discoverStyle.value === 'live_action') {
        items = items.filter((it: any) => it.is_animated === false);
    }
    return items;
});

const inspectingSeries = ref<any | null>(null);
const inspectingSeasons = ref<any[]>([]);
const isSeriesInspectorOpen = ref(false);
const inspectingLoading = ref(false);

// Expanded states for series accordions
const expandedSeries = ref<Record<number, boolean>>({});

// Modals
const selectedGap = ref<any | null>(null);
const isTorrentModalOpen = ref(false);
const isFastOrganizeOpen = ref(false);

let searchTimeout: any = null;
let discoverTimeout: any = null;

const toggleSeriesAccordion = (seriesId: number) => {
    expandedSeries.value[seriesId] = !expandedSeries.value[seriesId];
};

const fetchGaps = async () => {
    loading.value = true;
    try {
        const qParams = new URLSearchParams({
            query: searchQuery.value,
        });
        const res = await fetch(`/api/scout/gaps?${qParams.toString()}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.metrics) metrics.value = data.metrics;
        if (data.series_gaps) seriesGaps.value = data.series_gaps;
        if (data.collection_gaps) collectionGaps.value = data.collection_gaps;
        if (data.episodes) flatEpisodes.value = data.episodes;
        if (data.seasons) flatSeasons.value = data.seasons;
        if (data.collections) flatMovies.value = data.collections;
    } catch (err) {
        console.error('Failed to fetch gaps', err);
    } finally {
        loading.value = false;
    }
};

const refreshGaps = async () => {
    refreshing.value = true;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/api/scout/refresh', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({}),
        });
        const data = await res.json();
        if (data.metrics) metrics.value = data.metrics;
        if (data.series_gaps) seriesGaps.value = data.series_gaps;
        if (data.collection_gaps) collectionGaps.value = data.collection_gaps;
        await fetchGaps();
    } catch (err) {
        console.error('Failed to refresh gaps', err);
    } finally {
        refreshing.value = false;
    }
};

const searchDiscover = async () => {
    discoverLoading.value = true;
    try {
        const qParams = new URLSearchParams({
            query: discoverQuery.value,
            type: discoverType.value,
        });
        const res = await fetch(`/api/scout/discover/search?${qParams.toString()}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        discoverResults.value = data.results || [];
    } catch (err) {
        console.error('Discover search failed', err);
    } finally {
        discoverLoading.value = false;
    }
};

const openSeriesInspector = async (media: any) => {
    inspectingSeries.value = media;
    isSeriesInspectorOpen.value = true;
    inspectingLoading.value = true;
    inspectingSeasons.value = [];

    try {
        const res = await fetch(`/api/scout/discover/series-details?tmdb_id=${media.tmdb_id}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.seasons) {
            inspectingSeasons.value = data.seasons;
        }
    } catch (err) {
        console.error('Failed to load series details', err);
    } finally {
        inspectingLoading.value = false;
    }
};

const openDiscoverTorrentModal = (item: any, seasonNum?: number) => {
    const isSeries = item.media_type === 'series' || item.media_type === 'tv' || item.first_air_date;
    const yearVal = item.release_year || item.year;
    const gapPayload = {
        id: `discover_${item.tmdb_id}_${seasonNum || 1}`,
        type: isSeries ? (seasonNum ? 'season' : 'series') : 'movie',
        title: item.title,
        movie_title: !isSeries ? item.title : undefined,
        series_title: isSeries ? item.title : undefined,
        release_year: yearVal,
        year: yearVal,
        air_date: item.first_air_date,
        season_number: seasonNum || 1,
        poster_path: item.poster_path,
        still_path: null,
        backdrop_path: item.backdrop_path,
        tmdb_id: item.tmdb_id,
        imdb_id: item.imdb_id || null,
        is_animated: Boolean(item.is_animated),
    };
    openTorrentModal(gapPayload);
};

const openTorrentModal = (item: any) => {
    // Explicit type guarantee
    const cloned = { ...item };
    if (!cloned.type) {
        if (cloned.episode_number) {
            cloned.type = 'episode';
        } else if (cloned.season_number && !cloned.episode_number) {
            cloned.type = 'season';
        } else if (cloned.movie_title) {
            cloned.type = 'movie';
        }
    }
    selectedGap.value = cloned;
    isTorrentModalOpen.value = true;
};

const onDownloadStarted = (payload: any) => {
    if (payload?.gapItem?.id) {
        const ep = flatEpisodes.value.find(e => e.id === payload.gapItem.id);
        if (ep) ep.is_downloading = true;
        const mov = flatMovies.value.find(m => m.id === payload.gapItem.id);
        if (mov) mov.is_downloading = true;
    }
};

const onFastOrganized = () => {
    refreshGaps();
};

watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchGaps();
    }, 300);
});

watch([discoverQuery, discoverType], () => {
    clearTimeout(discoverTimeout);
    discoverTimeout = setTimeout(() => {
        searchDiscover();
    }, 350);
});

onMounted(() => {
    if (!props.initialSeriesGaps || props.initialSeriesGaps.length === 0) {
        fetchGaps();
    }
    if ((!props.initialTrending || props.initialTrending.length === 0) && activeTab.value === 'discover') {
        searchDiscover();
    }
});
</script>

<template>
    <AppLayout>
        <Head :title="isRTL ? 'مستكشف الوسائط والرادار الذكي' : 'Media Scout & Library Completion Radar'" />

        <div class="space-y-8 pb-16" :dir="isRTL ? 'rtl' : 'ltr'">
            
            <!-- Hero Banner -->
            <section class="relative rounded-3xl overflow-hidden glass-panel border border-cyan-500/20 p-6 lg:p-10 bg-gradient-to-br from-cyan-950/40 via-slate-900/60 to-purple-950/30">
                <div class="ambient-glow bg-cyan-500/10 w-96 h-96 -top-20 -left-20 pointer-events-none"></div>
                <div class="ambient-glow bg-purple-500/10 w-96 h-96 -bottom-20 -right-20 pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                    <div class="space-y-3 max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 text-xs font-bold uppercase tracking-wider">
                            <Compass class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'مستكشف نواقص المكتبة واستكشاف الجديد' : 'Media Scout Radar & Discovery' }}</span>
                        </div>
                        <h1 class="text-3xl lg:text-4xl font-black text-white tracking-tight">
                            {{ isRTL ? 'رادار اكتمال المكتبة واستكشاف الوسائط الجديدة' : 'Library Completion & Media Discovery Radar' }}
                        </h1>
                        <p class="text-sm lg:text-base text-slate-300 leading-relaxed">
                            {{ isRTL 
                                ? 'يكتشف الحلقات والمواسم المفقودة بدقة من مسلسلاتك، ويتيح استكشاف وبحث كافة الأفلام والمسلسلات الجديدة عبر TMDb مع روابط تورنت عالية السرعة والتنظيم التلقائي.' 
                                : 'Audits your library against TMDB for missing released episodes and seasons, plus live search & discovery for new unowned movies and TV series with 1-click torrent downloads.' 
                            }}
                        </p>
                    </div>

                    <!-- Header Quick Actions -->
                    <div class="flex items-center gap-3 shrink-0 flex-wrap">
                        <!-- Fast Organize & Scan Button -->
                        <button
                            @click="isFastOrganizeOpen = true"
                            class="px-4 py-2.5 rounded-xl text-xs font-black bg-emerald-500 text-slate-950 hover:bg-emerald-400 shadow-lg shadow-emerald-500/20 active:scale-95 transition-all flex items-center gap-2 cursor-pointer"
                            :title="isRTL ? 'تنظيم وفحص الملفات في H:\\Entertainment' : 'Organize and add to Library (Scan)'"
                        >
                            <FolderSync class="w-4 h-4" />
                            <span>{{ isRTL ? 'تنظيم وإضافة للمكتبة' : 'Organize & Scan Library' }}</span>
                        </button>

                        <!-- Refresh Button -->
                        <button
                            @click="refreshGaps"
                            :disabled="refreshing"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-800/80 hover:bg-slate-700 text-white border border-white/10 shadow-md active:scale-95 transition-all flex items-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': refreshing }" />
                            <span>{{ refreshing ? (isRTL ? 'جارٍ الفحص...' : 'Auditing...') : (isRTL ? 'فحص وتحديث الرادار' : 'Scan & Refresh Gaps') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Stats Badges -->
                <div class="relative z-10 grid grid-cols-2 md:grid-cols-4 gap-4 mt-8 pt-6 border-t border-white/10">
                    <!-- Series Completion -->
                    <div class="p-4 rounded-2xl bg-white/[0.04] border border-white/10 flex items-center gap-4">
                        <div class="p-3 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                            <Tv class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="text-2xl font-black text-emerald-400 tracking-tight">
                                {{ metrics.series_completion_rate || 100 }}%
                            </div>
                            <div class="text-[11px] text-slate-400 font-semibold uppercase">
                                {{ isRTL ? 'اكتمال المسلسلات' : 'Series Completion' }}
                            </div>
                        </div>
                    </div>

                    <!-- Franchise Completion -->
                    <div class="p-4 rounded-2xl bg-white/[0.04] border border-white/10 flex items-center gap-4">
                        <div class="p-3 rounded-xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                            <Film class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="text-2xl font-black text-cyan-400 tracking-tight">
                                {{ metrics.collections_completion_rate || 100 }}%
                            </div>
                            <div class="text-[11px] text-slate-400 font-semibold uppercase">
                                {{ isRTL ? 'اكتمال سلاسل الأفلام' : 'Franchise Completion' }}
                            </div>
                        </div>
                    </div>

                    <!-- Missing Episodes -->
                    <div class="p-4 rounded-2xl bg-white/[0.04] border border-white/10 flex items-center gap-4">
                        <div class="p-3 rounded-xl bg-amber-500/20 text-amber-400 border border-amber-500/30">
                            <Layers class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="text-2xl font-black text-amber-400 tracking-tight">
                                {{ metrics.missing_episodes_count || 0 }}
                            </div>
                            <div class="text-[11px] text-slate-400 font-semibold uppercase">
                                {{ isRTL ? 'حلقات ناقصة' : 'Missing Episodes' }}
                            </div>
                        </div>
                    </div>

                    <!-- Missing Franchise Movies -->
                    <div class="p-4 rounded-2xl bg-white/[0.04] border border-white/10 flex items-center gap-4">
                        <div class="p-3 rounded-xl bg-purple-500/20 text-purple-400 border border-purple-500/30">
                            <Award class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="text-2xl font-black text-purple-400 tracking-tight">
                                {{ metrics.missing_movies_count || 0 }}
                            </div>
                            <div class="text-[11px] text-slate-400 font-semibold uppercase">
                                {{ isRTL ? 'أفلام سلاسل مفقودة' : 'Missing Movies' }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Navigation Tabs & Search Controls -->
            <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
                <!-- Tabs Row -->
                <div class="flex items-center gap-2 overflow-x-auto pb-2 lg:pb-0 scrollbar-none">
                    <!-- Discover New Media Tab (Highlighted) -->
                    <button
                        @click="activeTab = 'discover'"
                        :class="[
                            'px-4 py-2.5 rounded-2xl text-xs font-black transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                            activeTab === 'discover'
                                ? 'bg-gradient-to-r from-cyan-500 to-purple-600 text-white shadow-lg shadow-cyan-500/20 scale-105'
                                : 'text-cyan-400 hover:text-white bg-cyan-950/30 border border-cyan-500/30 hover:bg-cyan-900/40'
                        ]"
                    >
                        <Sparkles class="w-4 h-4 text-amber-300" />
                        <span>{{ isRTL ? 'استكشاف وسائط جديدة (TMDb)' : 'Discover New Media' }}</span>
                    </button>

                    <button
                        @click="activeTab = 'all'"
                        :class="[
                            'px-4 py-2.5 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                            activeTab === 'all'
                                ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20'
                                : 'text-slate-400 hover:text-white hover:bg-white/5'
                        ]"
                    >
                        <Compass class="w-4 h-4" />
                        <span>{{ isRTL ? 'كافة نواقص المكتبة' : 'All Library Gaps' }}</span>
                    </button>

                    <button
                        @click="activeTab = 'series'"
                        :class="[
                            'px-4 py-2.5 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                            activeTab === 'series'
                                ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20'
                                : 'text-slate-400 hover:text-white hover:bg-white/5'
                        ]"
                    >
                        <Tv class="w-4 h-4" />
                        <span>{{ isRTL ? 'نواقص المسلسلات' : 'TV Series Gaps' }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-black/20">
                            {{ seriesGaps.length }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'collections'"
                        :class="[
                            'px-4 py-2.5 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                            activeTab === 'collections'
                                ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20'
                                : 'text-slate-400 hover:text-white hover:bg-white/5'
                        ]"
                    >
                        <Film class="w-4 h-4" />
                        <span>{{ isRTL ? 'سلاسل الأفلام غير المكتملة' : 'Franchise Gaps' }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-black/20">
                            {{ collectionGaps.length }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'episodes'"
                        :class="[
                            'px-4 py-2.5 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                            activeTab === 'episodes'
                                ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20'
                                : 'text-slate-400 hover:text-white hover:bg-white/5'
                        ]"
                    >
                        <Clock class="w-4 h-4" />
                        <span>{{ isRTL ? 'جميع الحلقات الناقصة' : 'Missing Episodes' }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-black/20">
                            {{ metrics.missing_episodes_count || 0 }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'movies'"
                        :class="[
                            'px-4 py-2.5 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                            activeTab === 'movies'
                                ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20'
                                : 'text-slate-400 hover:text-white hover:bg-white/5'
                        ]"
                    >
                        <Award class="w-4 h-4" />
                        <span>{{ isRTL ? 'جميع أفلام السلاسل الناقصة' : 'Missing Movies' }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-black/20">
                            {{ metrics.missing_movies_count || 0 }}
                        </span>
                    </button>
                </div>

                <!-- Search Input for Library Gaps (Only shown when not on discover tab) -->
                <div v-if="activeTab !== 'discover'" class="relative min-w-[280px]">
                    <Search class="w-4 h-4 text-slate-400 absolute top-1/2 -translate-y-1/2" :class="isRTL ? 'right-3.5' : 'left-3.5'" />
                    <input
                        type="text"
                        v-model="searchQuery"
                        :placeholder="isRTL ? 'ابحث في النواقص...' : 'Filter gap titles...'"
                        class="w-full bg-slate-900/80 border border-white/10 rounded-2xl py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 transition-all"
                        :class="isRTL ? 'pr-10 pl-4' : 'pl-10 pr-4'"
                    />
                </div>
            </div>

            <!-- TAB 0: DISCOVER NEW MEDIA (TMDb Live Search & Trending) -->
            <div v-if="activeTab === 'discover'" class="space-y-6">
                
                <!-- Discover Control Bar -->
                <div class="glass-panel border border-cyan-500/30 rounded-3xl p-6 bg-slate-900/60 space-y-4">
                    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
                        <!-- TMDb Live Search Input -->
                        <div class="relative flex-1">
                            <Search class="w-5 h-5 text-cyan-400 absolute top-1/2 -translate-y-1/2" :class="isRTL ? 'right-4' : 'left-4'" />
                            <input
                                type="text"
                                v-model="discoverQuery"
                                :placeholder="isRTL ? 'ابحث عن أي فيلم أو مسلسل جديد في قاعدة بيانات TMDb العالمية...' : 'Search any new movie or TV series on TMDb database...'"
                                class="w-full bg-slate-950/80 border border-white/10 rounded-2xl py-3 text-sm text-white placeholder-slate-400 focus:outline-none focus:border-cyan-400 shadow-inner"
                                :class="isRTL ? 'pr-12 pl-4' : 'pl-12 pr-4'"
                            />
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Media Type Filter Pills -->
                            <div class="flex items-center gap-1 bg-slate-950/80 p-1 rounded-2xl border border-white/10 shrink-0">
                                <button
                                    @click="discoverType = 'all'"
                                    :class="[
                                        'px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer',
                                        discoverType === 'all' ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    {{ isRTL ? 'الكل' : 'All Media' }}
                                </button>
                                <button
                                    @click="discoverType = 'movie'"
                                    :class="[
                                        'px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer',
                                        discoverType === 'movie' ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    🎬 {{ isRTL ? 'أفلام' : 'Movies' }}
                                </button>
                                <button
                                    @click="discoverType = 'tv'"
                                    :class="[
                                        'px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer',
                                        discoverType === 'tv' ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    📺 {{ isRTL ? 'مسلسلات' : 'TV Shows' }}
                                </button>
                            </div>

                            <!-- Style Filter Pills (Animated vs Live-Action) -->
                            <div class="flex items-center gap-1 bg-slate-950/80 p-1 rounded-2xl border border-white/10 shrink-0">
                                <button
                                    @click="discoverStyle = 'all'"
                                    :class="[
                                        'px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer',
                                        discoverStyle === 'all' ? 'bg-purple-500 text-white shadow-md shadow-purple-500/20' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    {{ isRTL ? 'كل الأنماط' : 'All Styles' }}
                                </button>
                                <button
                                    @click="discoverStyle = 'animated'"
                                    :class="[
                                        'px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer',
                                        discoverStyle === 'animated' ? 'bg-emerald-500 text-slate-950 shadow-md shadow-emerald-500/20' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    🎨 {{ isRTL ? 'رسوم متحركة' : 'Animated' }}
                                </button>
                                <button
                                    @click="discoverStyle = 'live_action'"
                                    :class="[
                                        'px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer',
                                        discoverStyle === 'live_action' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    🎭 {{ isRTL ? 'تمثيل حي' : 'Live-Action' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span class="flex items-center gap-1.5">
                            <Sparkles class="w-3.5 h-3.5 text-amber-400" />
                            <span>{{ discoverQuery ? (isRTL ? `نتائج البحث عن "${discoverQuery}":` : `Search results for "${discoverQuery}":`) : (isRTL ? 'الأكثر رواجاً هذا الأسبوع على TMDb:' : '🔥 Trending This Week on TMDb:') }}</span>
                        </span>
                        <span v-if="discoverLoading" class="flex items-center gap-1.5 text-cyan-400 font-bold">
                            <Loader2 class="w-3.5 h-3.5 animate-spin" />
                            <span>{{ isRTL ? 'جارٍ البحث في TMDb...' : 'Querying TMDb...' }}</span>
                        </span>
                    </div>
                </div>

                <!-- Discover Results Grid -->
                <div v-if="filteredDiscoverResults.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                    <div
                        v-for="item in filteredDiscoverResults"
                        :key="item.tmdb_id"
                        class="group rounded-3xl overflow-hidden glass-panel border border-white/10 hover:border-cyan-500/40 bg-slate-900/50 transition-all duration-300 flex flex-col justify-between"
                    >
                        <!-- Poster Container -->
                        <div class="relative aspect-[2/3] w-full overflow-hidden bg-slate-950">
                            <img
                                v-if="item.poster_path"
                                :src="item.poster_path"
                                :alt="item.title"
                                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                loading="lazy"
                                @error="(e: any) => (e.target.style.display = 'none')"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-slate-700">
                                <Film v-if="item.media_type === 'movie'" class="w-12 h-12" />
                                <Tv v-else class="w-12 h-12" />
                            </div>

                            <!-- Rating Badge -->
                            <div v-if="item.rating" class="absolute top-2.5 left-2.5 px-2 py-0.5 rounded-lg text-[10px] font-black bg-black/80 backdrop-blur-md text-amber-400 border border-white/10 shadow-md flex items-center gap-1">
                                <Star class="w-3 h-3 fill-amber-400 text-amber-400" />
                                <span>{{ item.rating }}</span>
                            </div>

                            <!-- Media Type & Style Chips (Top Right) -->
                            <div class="absolute top-2.5 right-2.5 flex flex-col items-end gap-1">
                                <span
                                    v-if="item.is_animated !== undefined"
                                    :class="[
                                        'px-2 py-0.5 rounded-lg text-[10px] font-black backdrop-blur-md border shadow-md',
                                        item.is_animated
                                            ? 'bg-emerald-500/90 text-slate-950 border-emerald-400'
                                            : 'bg-amber-500/90 text-slate-950 border-amber-400'
                                    ]"
                                >
                                    {{ item.is_animated ? (isRTL ? '🎨 رسوم متحركة' : '🎨 Animated') : (isRTL ? '🎭 تمثيل حي' : '🎭 Live-Action') }}
                                </span>
                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider bg-black/80 backdrop-blur-md text-white border border-white/10">
                                    {{ item.media_type === 'series' || item.media_type === 'tv' ? (isRTL ? '📺 مسلسل' : '📺 Series') : (isRTL ? '🎬 فيلم' : '🎬 Movie') }}
                                </span>
                            </div>

                            <!-- In Library Status Overlay -->
                            <div class="absolute bottom-2 inset-x-2 flex items-center justify-center">
                                <span
                                    v-if="item.in_library"
                                    class="w-full text-center px-2 py-1 rounded-xl text-[11px] font-black bg-emerald-500/95 text-slate-950 backdrop-blur-md shadow-md flex items-center justify-center gap-1"
                                >
                                    <CheckCircle2 class="w-3.5 h-3.5" />
                                    <span>{{ isRTL ? 'متوفر بالمكتبة' : 'In Library' }}</span>
                                </span>
                                <span
                                    v-else
                                    class="w-full text-center px-2 py-1 rounded-xl text-[11px] font-black bg-cyan-500/95 text-slate-950 backdrop-blur-md shadow-md flex items-center justify-center gap-1"
                                >
                                    <Sparkles class="w-3.5 h-3.5" />
                                    <span>{{ isRTL ? 'جديد / غير متوفر' : 'New / Not Owned' }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Card Content & Action Button -->
                        <div class="p-3.5 space-y-2.5 flex-1 flex flex-col justify-between">
                            <div>
                                <h4 class="text-xs font-black text-white truncate" :title="item.title">
                                    {{ item.title }}
                                </h4>
                                <div class="flex items-center justify-between text-[11px] text-slate-400 mt-0.5">
                                    <span class="font-bold text-slate-300">
                                        {{ item.release_year || item.year || item.first_air_date }}
                                    </span>
                                    <span v-if="item.owned_episodes_count !== undefined && item.owned_episodes_count > 0" class="text-emerald-400 font-bold">
                                        {{ item.owned_episodes_count }} {{ isRTL ? 'حلقة' : 'eps' }}
                                    </span>
                                    <span v-else-if="item.is_animated" class="text-emerald-400/90 text-[10px] font-semibold">
                                        {{ isRTL ? 'رسوم متحركة' : 'Animated' }}
                                    </span>
                                    <span v-else class="text-amber-400/90 text-[10px] font-semibold">
                                        {{ isRTL ? 'تمثيل حي' : 'Live-Action' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="space-y-1.5 pt-1">
                                <!-- Series Inspector Button -->
                                <button
                                    v-if="item.media_type === 'series' || item.media_type === 'tv'"
                                    @click="openSeriesInspector(item)"
                                    class="w-full py-1.5 rounded-xl text-xs font-bold bg-white/10 hover:bg-white/20 text-white transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                >
                                    <Eye class="w-3.5 h-3.5" />
                                    <span>{{ isRTL ? 'فحص المواسم' : 'Inspect Seasons' }}</span>
                                </button>

                                <!-- Direct Find Torrents Button -->
                                <button
                                    @click="openDiscoverTorrentModal(item)"
                                    class="w-full py-1.5 rounded-xl text-xs font-black bg-cyan-500 hover:bg-cyan-400 text-slate-950 shadow-md shadow-cyan-500/20 active:scale-95 transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                >
                                    <Compass class="w-3.5 h-3.5" />
                                    <span>{{ isRTL ? 'البحث عن تورنت' : 'Find Torrents' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-16 px-4 rounded-3xl glass-panel border border-white/10 space-y-3">
                    <Search class="w-10 h-10 text-slate-500 mx-auto" />
                    <h3 class="text-base font-bold text-white">
                        {{ isRTL ? 'لم يتم العثور على نتائج' : 'No Results Found' }}
                    </h3>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto">
                        {{ discoverResults.length > 0
                            ? (isRTL ? 'لا توجد نتائج تطابق نمط العرض المحدد (رسوم متحركة / تمثيل حي). جرّب اختيار "كل الأنماط".' : 'No items match the selected style filter (Animated / Live-Action). Try selecting "All Styles".')
                            : (isRTL ? 'جرّب البحث باسم الفيلم أو المسلسل باللغة الإنجليزية أو العربية.' : 'Try searching by English or Arabic title to discover new media.') }}
                    </p>
                </div>
            </div>

            <!-- Loading Spinner for Gap Audits -->
            <div v-else-if="loading" class="py-16 text-center space-y-3">
                <Loader2 class="w-8 h-8 text-cyan-400 animate-spin mx-auto" />
                <p class="text-xs text-slate-400 font-medium">
                    {{ isRTL ? 'جارٍ فحص وتحليل نواقص المكتبة...' : 'Loading library gap analysis...' }}
                </p>
            </div>

            <!-- MAIN CONTENT SECTIONS (LIBRARY GAPS) -->
            <div v-else class="space-y-10">

                <!-- SECTION 1: TV SERIES GAPS (Grouped by Show) -->
                <div v-if="activeTab === 'all' || activeTab === 'series'" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Tv class="w-5 h-5 text-cyan-400" />
                            <h2 class="text-xl font-black text-white tracking-tight">
                                {{ isRTL ? 'المسلسلات التي تحتوي على حلقات أو مواسم ناقصة' : 'TV Series with Missing Content' }}
                            </h2>
                        </div>
                        <span class="text-xs font-bold text-slate-400">
                            {{ seriesGaps.length }} {{ isRTL ? 'مسلسلات' : 'Series' }}
                        </span>
                    </div>

                    <!-- Series Cards Grid -->
                    <div v-if="seriesGaps.length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div
                            v-for="sg in seriesGaps"
                            :key="sg.series_id"
                            class="rounded-3xl overflow-hidden glass-panel border border-white/10 hover:border-cyan-500/30 bg-slate-900/40 transition-all p-6 flex flex-col justify-between space-y-5"
                        >
                            <!-- Show Header -->
                            <div class="flex items-start gap-4">
                                <img
                                    v-if="sg.poster_path"
                                    :src="sg.poster_path"
                                    :alt="sg.series_title"
                                    class="w-20 h-28 object-cover rounded-2xl border border-white/10 shadow-lg shrink-0"
                                    loading="lazy"
                                    @error="(e: any) => (e.target.style.display = 'none')"
                                />
                                <div v-else class="w-20 h-28 rounded-2xl bg-slate-950 border border-white/10 flex items-center justify-center text-slate-600 shrink-0">
                                    <Tv class="w-8 h-8" />
                                </div>

                                <div class="space-y-2 flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <h3 class="text-lg font-black text-white truncate">
                                            {{ sg.series_title }}
                                        </h3>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 shrink-0">
                                            {{ sg.completion_percent }}%
                                        </span>
                                    </div>
                                    <p v-if="sg.series_title_ar" class="text-xs text-slate-400 truncate">
                                        {{ sg.series_title_ar }}
                                    </p>

                                    <!-- Completion Bar -->
                                    <div class="space-y-1 pt-1">
                                        <div class="w-full h-2 rounded-full bg-slate-950 overflow-hidden border border-white/5">
                                            <div
                                                class="h-full rounded-full transition-all duration-500"
                                                :class="sg.completion_percent >= 90 ? 'bg-gradient-to-r from-cyan-500 to-emerald-400' : 'bg-gradient-to-r from-amber-500 to-cyan-400'"
                                                :style="{ width: `${sg.completion_percent}%` }"
                                            ></div>
                                        </div>
                                        <div class="flex items-center justify-between text-[11px] text-slate-400 font-semibold">
                                            <span>{{ sg.local_episodes_count }} {{ isRTL ? 'حلقة متوفرة' : 'Episodes Owned' }}</span>
                                            <span class="text-amber-400 font-bold">
                                                {{ sg.missing_episodes_count }} {{ isRTL ? 'حلقة ناقصة' : 'Missing' }}
                                                <span v-if="sg.missing_seasons_count > 0">
                                                    + {{ sg.missing_seasons_count }} {{ isRTL ? 'مواسم' : 'Seasons' }}
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Missing Content Breakdown -->
                            <div class="space-y-2.5 pt-2 border-t border-white/5">
                                <div class="text-xs font-bold text-slate-300 flex items-center justify-between">
                                    <span>{{ isRTL ? 'الحلقات والمواسم المطلوب تحميلها:' : 'Available Missing Content:' }}</span>
                                    <button
                                        v-if="sg.missing_episodes.length > 2"
                                        @click="toggleSeriesAccordion(sg.series_id)"
                                        class="text-[11px] text-cyan-400 hover:text-cyan-300 flex items-center gap-1 cursor-pointer"
                                    >
                                        <span>{{ expandedSeries[sg.series_id] ? (isRTL ? 'إخفاء' : 'Show Less') : (isRTL ? `عرض الكل (${sg.missing_episodes.length})` : `Show All (${sg.missing_episodes.length})`) }}</span>
                                        <component :is="expandedSeries[sg.series_id] ? ChevronUp : ChevronDown" class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <!-- Missing Entire Seasons (Distinct Season Pack) -->
                                <div
                                    v-for="sGap in sg.missing_seasons"
                                    :key="sGap.id"
                                    class="p-3 rounded-2xl bg-black/40 border border-purple-500/30 flex items-center justify-between gap-3"
                                >
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                                {{ sGap.season_name }}
                                            </span>
                                            <span class="text-xs font-bold text-white">
                                                {{ isRTL ? `الموسم بالكامل (${sGap.episode_count} حلقة)` : `Entire Season (${sGap.episode_count} eps)` }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-slate-400">
                                            {{ isRTL ? 'تاريخ العرض:' : 'Aired:' }} {{ sGap.air_date }}
                                        </div>
                                    </div>

                                    <button
                                        @click="openTorrentModal({ ...sGap, type: 'season' })"
                                        class="px-3.5 py-1.5 rounded-xl text-xs font-black bg-purple-500 text-white hover:bg-purple-400 shadow-md shadow-purple-500/20 active:scale-95 transition-all flex items-center gap-1.5 cursor-pointer shrink-0"
                                    >
                                        <Compass class="w-3.5 h-3.5" />
                                        <span>{{ isRTL ? 'حزمة الموسم' : 'Find Season Pack' }}</span>
                                    </button>
                                </div>

                                <!-- Missing Specific Episodes -->
                                <div
                                    v-for="ep in (expandedSeries[sg.series_id] ? sg.missing_episodes : sg.missing_episodes.slice(0, 2))"
                                    :key="ep.id"
                                    class="p-3 rounded-2xl bg-black/40 border border-white/5 hover:border-cyan-500/30 transition-all flex items-center justify-between gap-3"
                                >
                                    <div class="space-y-0.5 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/30 shrink-0">
                                                {{ ep.episode_code }}
                                            </span>
                                            <span class="text-xs font-bold text-white truncate">
                                                {{ isRTL && ep.episode_title_ar ? ep.episode_title_ar : ep.episode_title }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-slate-400 flex items-center gap-3">
                                            <span>{{ isRTL ? 'تاريخ العرض:' : 'Aired:' }} {{ ep.air_date }}</span>
                                            <span v-if="ep.rating" class="text-amber-400 font-bold flex items-center gap-0.5">
                                                ★ {{ ep.rating }}
                                            </span>
                                        </div>
                                    </div>

                                    <button
                                        @click="openTorrentModal({ ...ep, type: 'episode' })"
                                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500 text-slate-950 hover:bg-amber-400 shadow-md shadow-amber-500/20 active:scale-95 transition-all flex items-center gap-1.5 cursor-pointer shrink-0"
                                    >
                                        <Compass class="w-3.5 h-3.5" />
                                        <span>{{ isRTL ? 'البحث عن الحلقة' : 'Find Episode' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Series Empty State -->
                    <div v-else class="text-center py-12 px-4 rounded-3xl glass-panel border border-white/10 space-y-2">
                        <CheckCircle2 class="w-10 h-10 text-emerald-400 mx-auto" />
                        <h3 class="text-base font-bold text-white">
                            {{ isRTL ? 'كافة مسلسلاتك مكتملة بنسبة 100%' : 'All TV Series are 100% Complete!' }}
                        </h3>
                        <p class="text-xs text-slate-400">
                            {{ isRTL ? 'لا توجد أي حلقات ناقصة من المواسم المعروضة في مكتبتك.' : 'No missing released episodes detected across your library.' }}
                        </p>
                    </div>
                </div>

                <!-- SECTION 2: FRANCHISE / MOVIE COLLECTIONS GAPS -->
                <div v-if="activeTab === 'all' || activeTab === 'collections'" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Film class="w-5 h-5 text-purple-400" />
                            <h2 class="text-xl font-black text-white tracking-tight">
                                {{ isRTL ? 'سلاسل ومجموعات الأفلام غير المكتملة' : 'Incomplete Movie Franchises' }}
                            </h2>
                        </div>
                        <span class="text-xs font-bold text-slate-400">
                            {{ collectionGaps.length }} {{ isRTL ? 'سلاسل أفلام' : 'Franchises' }}
                        </span>
                    </div>

                    <!-- Collections Grid -->
                    <div v-if="collectionGaps.length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div
                            v-for="cg in collectionGaps"
                            :key="cg.collection_id"
                            class="rounded-3xl overflow-hidden glass-panel border border-white/10 hover:border-purple-500/30 bg-slate-900/40 transition-all p-6 flex flex-col justify-between space-y-5"
                        >
                            <!-- Collection Header -->
                            <div class="flex items-start gap-4">
                                <img
                                    v-if="cg.poster_path"
                                    :src="cg.poster_path"
                                    :alt="cg.collection_name"
                                    class="w-20 h-28 object-cover rounded-2xl border border-white/10 shadow-lg shrink-0"
                                    loading="lazy"
                                    @error="(e: any) => (e.target.style.display = 'none')"
                                />
                                <div v-else class="w-20 h-28 rounded-2xl bg-slate-950 border border-white/10 flex items-center justify-center text-slate-600 shrink-0">
                                    <Film class="w-8 h-8" />
                                </div>

                                <div class="space-y-2 flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <h3 class="text-lg font-black text-white truncate">
                                            {{ cg.collection_name }}
                                        </h3>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-purple-500/20 text-purple-300 border border-purple-500/30 shrink-0">
                                            {{ cg.completion_percent }}%
                                        </span>
                                    </div>

                                    <!-- Completion Bar -->
                                    <div class="space-y-1 pt-1">
                                        <div class="w-full h-2 rounded-full bg-slate-950 overflow-hidden border border-white/5">
                                            <div
                                                class="h-full rounded-full transition-all duration-500 bg-gradient-to-r from-purple-500 to-cyan-400"
                                                :style="{ width: `${cg.completion_percent}%` }"
                                            ></div>
                                        </div>
                                        <div class="flex items-center justify-between text-[11px] text-slate-400 font-semibold">
                                            <span>{{ cg.owned_count }} {{ isRTL ? 'أجزاء متوفرة' : 'Films Owned' }}</span>
                                            <span class="text-amber-400 font-bold">
                                                {{ cg.missing_count }} {{ isRTL ? 'أجزاء مفقودة' : 'Missing Parts' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Saga Breakdown (Parts with In Library vs Missing Badges) -->
                            <div class="space-y-2.5 pt-2 border-t border-white/5">
                                <div class="text-xs font-bold text-slate-300">
                                    {{ isRTL ? 'أجزاء السلسلة (المتوفرة والناقصة):' : 'Franchise Films (Owned vs Missing):' }}
                                </div>

                                <div class="space-y-2">
                                    <div
                                        v-for="part in cg.parts"
                                        :key="part.id"
                                        class="p-3 rounded-2xl flex items-center justify-between gap-3 transition-all"
                                        :class="part.in_library ? 'bg-white/[0.02] border border-white/5' : 'bg-amber-500/5 border border-amber-500/30 shadow-inner'"
                                    >
                                        <div class="flex items-center gap-3 min-w-0">
                                            <img
                                                v-if="part.poster_path"
                                                :src="part.poster_path"
                                                :alt="part.movie_title"
                                                class="w-8 h-12 object-cover rounded-lg border border-white/10 shrink-0"
                                                loading="lazy"
                                                @error="(e: any) => (e.target.style.display = 'none')"
                                            />
                                            <div class="space-y-0.5 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <h4 class="text-xs font-bold text-white truncate max-w-xs">
                                                        {{ part.movie_title }}
                                                    </h4>
                                                    <span v-if="part.release_year" class="text-[10px] text-slate-400 font-semibold">
                                                        ({{ part.release_year }})
                                                    </span>
                                                </div>

                                                <!-- Status Chip -->
                                                <div class="flex items-center gap-2">
                                                    <span
                                                        v-if="part.in_library"
                                                        class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-md border border-emerald-500/20"
                                                    >
                                                        <CheckCircle2 class="w-3 h-3 text-emerald-400" />
                                                        <span>{{ isRTL ? 'متوفر بالمكتبة' : 'In Library' }}</span>
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-md border border-amber-500/30"
                                                    >
                                                        <XCircle class="w-3 h-3 text-amber-400" />
                                                        <span>{{ isRTL ? 'مفقود' : 'Missing' }}</span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Action if missing -->
                                        <button
                                            v-if="!part.in_library"
                                            @click="openTorrentModal({ ...part, type: 'collection_movie' })"
                                            class="px-3 py-1.5 rounded-xl text-xs font-bold bg-cyan-500 text-slate-950 hover:bg-cyan-400 shadow-md shadow-cyan-500/20 active:scale-95 transition-all flex items-center gap-1.5 cursor-pointer shrink-0"
                                        >
                                            <Compass class="w-3.5 h-3.5" />
                                            <span>{{ isRTL ? 'البحث عن تورنت' : 'Find Torrents' }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Collections Empty State -->
                    <div v-else class="text-center py-12 px-4 rounded-3xl glass-panel border border-white/10 space-y-2">
                        <CheckCircle2 class="w-10 h-10 text-emerald-400 mx-auto" />
                        <h3 class="text-base font-bold text-white">
                            {{ isRTL ? 'كافة سلاسل الأفلام مكتملة تماماً!' : 'All Movie Franchises are 100% Complete!' }}
                        </h3>
                        <p class="text-xs text-slate-400">
                            {{ isRTL ? 'لا توجد أي أجزاء أفلام ناقصة من سلاسل بوكس سيت مكتبتك.' : 'All parts of your movie sagas are present in your library.' }}
                        </p>
                    </div>
                </div>

                <!-- SECTION 3: FLAT EPISODES VIEW -->
                <div v-if="activeTab === 'episodes'" class="space-y-4">
                    <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
                        <Clock class="w-5 h-5 text-amber-400" />
                        <span>{{ isRTL ? 'كافة الحلقات الناقصة' : 'All Missing Episodes' }}</span>
                    </h2>

                    <div v-if="flatEpisodes.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="ep in flatEpisodes"
                            :key="ep.id"
                            class="p-4 rounded-2xl glass-panel border border-white/5 hover:border-cyan-500/30 bg-slate-900/40 transition-all flex items-center justify-between gap-4"
                        >
                            <div class="flex items-center gap-3.5 min-w-0">
                                <img
                                    v-if="ep.series_poster || ep.still_path"
                                    :src="ep.series_poster || ep.still_path"
                                    :alt="ep.series_title"
                                    class="w-11 h-16 object-cover rounded-xl border border-white/10 shrink-0 shadow-md"
                                    loading="lazy"
                                    @error="(e: any) => (e.target.style.display = 'none')"
                                />
                                <div v-else class="w-11 h-16 rounded-xl bg-slate-950 border border-white/10 flex items-center justify-center text-slate-600 shrink-0">
                                    <Tv class="w-5 h-5" />
                                </div>
                                <div class="space-y-1 min-w-0">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                        {{ ep.episode_code }}
                                    </span>
                                    <h4 class="text-sm font-bold text-white truncate">
                                        {{ ep.series_title }}
                                    </h4>
                                    <p class="text-xs text-slate-400 truncate">
                                        {{ isRTL && ep.episode_title_ar ? ep.episode_title_ar : ep.episode_title }}
                                    </p>
                                    <span class="text-[11px] text-slate-500 block">
                                        {{ isRTL ? 'العرض:' : 'Aired:' }} {{ ep.air_date }}
                                    </span>
                                </div>
                            </div>

                            <button
                                @click="openTorrentModal({ ...ep, type: 'episode' })"
                                class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500 text-slate-950 hover:bg-amber-400 shadow-md shadow-amber-500/20 active:scale-95 transition-all flex items-center gap-1.5 cursor-pointer shrink-0"
                            >
                                <Compass class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'تورنت' : 'Torrents' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: FLAT MOVIES VIEW -->
                <div v-if="activeTab === 'movies'" class="space-y-4">
                    <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
                        <Award class="w-5 h-5 text-purple-400" />
                        <span>{{ isRTL ? 'كافة أفلام السلاسل الناقصة' : 'All Missing Franchise Movies' }}</span>
                    </h2>

                    <div v-if="flatMovies.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="m in flatMovies"
                            :key="m.id"
                            class="p-4 rounded-2xl glass-panel border border-white/5 hover:border-purple-500/30 bg-slate-900/40 transition-all flex items-center justify-between gap-4"
                        >
                            <div class="flex items-center gap-3.5 min-w-0">
                                <img
                                    v-if="m.poster_path || m.collection_poster"
                                    :src="m.poster_path || m.collection_poster"
                                    :alt="m.movie_title"
                                    class="w-11 h-16 object-cover rounded-xl border border-white/10 shrink-0 shadow-md"
                                    loading="lazy"
                                    @error="(e: any) => (e.target.style.display = 'none')"
                                />
                                <div v-else class="w-11 h-16 rounded-xl bg-slate-950 border border-white/10 flex items-center justify-center text-slate-600 shrink-0">
                                    <Film class="w-5 h-5" />
                                </div>
                                <div class="space-y-1 min-w-0">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-purple-500/20 text-purple-300 border border-purple-500/30 inline-block truncate max-w-[200px]">
                                        {{ m.collection_name }}
                                    </span>
                                    <h4 class="text-sm font-bold text-white truncate">
                                        {{ m.movie_title }}
                                    </h4>
                                    <span class="text-[11px] text-slate-400 block">
                                        {{ m.release_year }}
                                    </span>
                                </div>
                            </div>

                            <button
                                @click="openTorrentModal({ ...m, type: 'collection_movie' })"
                                class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-cyan-500 text-slate-950 hover:bg-cyan-400 shadow-md shadow-cyan-500/20 active:scale-95 transition-all flex items-center gap-1.5 cursor-pointer shrink-0"
                            >
                                <Compass class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'تورنت' : 'Torrents' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <!-- Series Seasons Inspector Modal (For Discover) -->
        <div v-if="isSeriesInspectorOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/85 backdrop-blur-md animate-fade-in" :dir="isRTL ? 'rtl' : 'ltr'">
            <div class="relative w-full max-w-2xl max-h-[85vh] glass-panel border border-cyan-500/20 bg-slate-900/95 rounded-3xl shadow-2xl overflow-hidden flex flex-col">
                
                <!-- Inspector Header -->
                <div class="p-6 border-b border-white/10 flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <img
                            v-if="inspectingSeries?.poster_path"
                            :src="inspectingSeries.poster_path"
                            :alt="inspectingSeries.title"
                            class="w-14 h-20 object-cover rounded-xl border border-white/10 shadow"
                        />
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">
                                    {{ isRTL ? 'مواسم المسلسل' : 'Series Seasons' }}
                                </span>
                                <span class="text-xs text-slate-400">{{ inspectingSeries?.year }}</span>
                            </div>
                            <h3 class="text-lg font-black text-white mt-1">
                                {{ inspectingSeries?.title }}
                            </h3>
                        </div>
                    </div>

                    <button @click="isSeriesInspectorOpen = false" class="p-2 text-slate-400 hover:text-white rounded-xl hover:bg-white/10">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Inspector Seasons List -->
                <div class="p-6 overflow-y-auto space-y-3 flex-1">
                    <div v-if="inspectingLoading" class="py-12 text-center">
                        <Loader2 class="w-7 h-7 text-cyan-400 animate-spin mx-auto" />
                    </div>

                    <div
                        v-else
                        v-for="s in inspectingSeasons"
                        :key="s.season_number"
                        class="p-4 rounded-2xl glass-panel border border-white/5 hover:border-cyan-500/30 bg-slate-950/40 flex items-center justify-between gap-4"
                    >
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl bg-purple-500/20 border border-purple-500/30 flex items-center justify-center font-black text-purple-300 text-sm">
                                S{{ s.season_number }}
                            </span>
                            <div>
                                <h4 class="text-xs font-black text-white">
                                    {{ s.name }}
                                </h4>
                                <div class="text-[11px] text-slate-400 flex items-center gap-2 mt-0.5">
                                    <span>{{ s.episode_count }} {{ isRTL ? 'حلقة' : 'episodes' }}</span>
                                    <span>•</span>
                                    <span>{{ s.air_date }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span
                                v-if="s.in_library"
                                class="px-2.5 py-1 rounded-xl text-[10px] font-black bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1"
                            >
                                <CheckCircle2 class="w-3 h-3" />
                                <span>{{ isRTL ? 'متوفر' : 'Owned' }}</span>
                            </span>
                            <button
                                @click="openDiscoverTorrentModal(inspectingSeries, s.season_number)"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold bg-cyan-500 hover:bg-cyan-400 text-slate-950 shadow-md flex items-center gap-1 cursor-pointer"
                            >
                                <Compass class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'بحث تورنت' : 'Find Torrents' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modals -->
        <TorrentSuggestionsModal
            :is-open="isTorrentModalOpen"
            :gap-item="selectedGap"
            @close="isTorrentModalOpen = false"
            @download-started="onDownloadStarted"
        />

        <FastOrganizeModal
            :is-open="isFastOrganizeOpen"
            @close="isFastOrganizeOpen = false"
            @organized="onFastOrganized"
        />

    </AppLayout>
</template>
