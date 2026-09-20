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
    CheckCircle2, XCircle, ChevronDown, ChevronUp, Eye, X,
    AlertTriangle, ShieldAlert, ArrowDownUp, HardDrive, Filter, Subtitles, Volume2, Flame
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

interface UpgradeMetrics {
    total_low_res?: number;
    low_res_movies?: number;
    low_res_episodes?: number;
    total_poor_quality?: number;
    cam_recorded?: number;
    hardcoded_subs?: number;
    watermarked_or_low_audio?: number;
    audited_movies_count?: number;
    audited_episodes_count?: number;
}

const props = defineProps<{
    initialMetrics: MetricData;
    initialSeriesGaps?: any[];
    initialCollectionGaps?: any[];
    initialEpisodes?: any[];
    initialSeasons?: any[];
    initialMovies?: any[];
    initialTrending?: any[];
    initialUpgradeMetrics?: UpgradeMetrics;
    initialLowRes?: {
        movies?: any[];
        episodes?: any[];
        total_count?: number;
    };
    initialPoorQuality?: {
        items?: any[];
        total_count?: number;
        by_category?: Record<string, number>;
    };
}>();

const { t, isRTL } = useI18n();

const metrics = ref<MetricData>(props.initialMetrics || {});
const seriesGaps = ref<any[]>(props.initialSeriesGaps || []);
const collectionGaps = ref<any[]>(props.initialCollectionGaps || []);
const flatEpisodes = ref<any[]>(props.initialEpisodes || []);
const flatSeasons = ref<any[]>(props.initialSeasons || []);
const flatMovies = ref<any[]>(props.initialMovies || []);

// Quality Upgrades State (Sub-720p & CAM/HC Detection)
const upgradeMetrics = ref<UpgradeMetrics>(props.initialUpgradeMetrics || {});
const lowResData = ref<any>(props.initialLowRes || { movies: [], episodes: [], total_count: 0 });
const poorQualityData = ref<any>(props.initialPoorQuality || { items: [], total_count: 0, by_category: {} });
const upgradesLoading = ref(false);

// Upgrade Tab Specific Filters
const lowResMediaType = ref<'all' | 'movie' | 'episode'>('all');
const lowResResolution = ref<string>('all');
const poorQualityCategory = ref<string>('all');
const poorQualityMediaType = ref<'all' | 'movie' | 'episode'>('all');

const activeTab = ref<'all' | 'discover' | 'series' | 'collections' | 'episodes' | 'movies' | 'low_resolution' | 'poor_quality'>('all');

// Primary Navigation Mode: 'gaps' | 'upgrades' | 'discover'
const navMode = computed<'gaps' | 'upgrades' | 'discover'>({
    get: () => {
        if (activeTab.value === 'discover') return 'discover';
        if (activeTab.value === 'low_resolution' || activeTab.value === 'poor_quality') return 'upgrades';
        return 'gaps';
    },
    set: (mode: 'gaps' | 'upgrades' | 'discover') => {
        if (mode === 'discover') {
            activeTab.value = 'discover';
        } else if (mode === 'upgrades') {
            if (activeTab.value !== 'low_resolution' && activeTab.value !== 'poor_quality') {
                activeTab.value = 'low_resolution';
            }
        } else {
            if (activeTab.value === 'discover' || activeTab.value === 'low_resolution' || activeTab.value === 'poor_quality') {
                activeTab.value = 'all';
            }
        }
    }
});

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
        if (data.episodes) flatEpisodes.value = data.episodes;
        if (data.seasons) flatSeasons.value = data.seasons;
        if (data.collections) flatMovies.value = data.collections;
        if (data.low_resolution || data.low_res) lowResData.value = data.low_resolution || data.low_res;
        if (data.poor_quality) poorQualityData.value = data.poor_quality;
        if (data.upgrade_metrics || data.metrics) upgradeMetrics.value = data.upgrade_metrics || data.metrics;
        await fetchGaps();
        await fetchUpgrades(true);
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

const openUpgradeTorrentModal = (item: any, category: 'low_resolution' | 'poor_quality') => {
    const isEp = item.type === 'episode';
    const sNum = item.season_number ? Number(item.season_number) : 1;
    const epNum = item.episode_number ? Number(item.episode_number) : 1;
    
    const gapPayload = {
        id: item.id || `upgrade_${item.local_id || (item.title ? item.title.replace(/\s+/g, '_') : 'item')}`,
        type: isEp ? 'episode' : 'movie',
        title: item.title,
        movie_title: !isEp ? item.title : undefined,
        series_title: isEp ? (item.series_title || item.title) : undefined,
        series_title_ar: item.series_title_ar,
        episode_title: isEp ? item.episode_title : undefined,
        season_number: isEp ? sNum : undefined,
        episode_number: isEp ? epNum : undefined,
        release_year: item.release_year || item.year,
        year: item.release_year || item.year,
        poster_path: item.poster_path,
        still_path: item.still_path,
        backdrop_path: item.backdrop_path,
        tmdb_id: item.tmdb_id,
        imdb_id: item.imdb_id || null,
        is_animated: Boolean(item.is_animated),
        current_resolution: item.current_resolution || item.resolution,
        upgrade_mode: true,
        clean_only: true,
        upgrade_category: category,
        badge_en: item.badge_en,
        badge_ar: item.badge_ar,
        evidence: item.evidence,
        file_path: item.file_path,
        video_codec: item.video_codec,
        audio_codec: item.audio_codec,
        file_size_human: item.file_size_human,
    };
    openTorrentModal(gapPayload);
};

const fetchUpgrades = async (forceRefresh = false) => {
    upgradesLoading.value = true;
    try {
        const qParams = new URLSearchParams({
            refresh: forceRefresh ? '1' : '0',
            query: searchQuery.value,
        });
        const res = await fetch(`/api/scout/upgrades?${qParams.toString()}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.upgrade_metrics) upgradeMetrics.value = data.upgrade_metrics;
        else if (data.metrics) upgradeMetrics.value = data.metrics;

        if (data.low_resolution) lowResData.value = data.low_resolution;
        else if (data.low_res) lowResData.value = data.low_res;

        if (data.poor_quality) poorQualityData.value = data.poor_quality;
    } catch (err) {
        console.error('Failed to fetch quality upgrades', err);
    } finally {
        upgradesLoading.value = false;
    }
};

const filteredLowResMovies = computed(() => {
    let list = lowResData.value.movies || [];
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter((m: any) => 
            (m.title && m.title.toLowerCase().includes(q)) || 
            (m.title_ar && m.title_ar.includes(q))
        );
    }
    if (lowResResolution.value !== 'all') {
        list = list.filter((m: any) => (m.current_resolution || m.resolution || '').toLowerCase().includes(lowResResolution.value.toLowerCase()));
    }
    return list;
});

const filteredLowResEpisodes = computed(() => {
    let list = lowResData.value.episodes || [];
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter((e: any) => 
            (e.title && e.title.toLowerCase().includes(q)) || 
            (e.series_title && e.series_title.toLowerCase().includes(q)) ||
            (e.series_title_ar && e.series_title_ar.includes(q))
        );
    }
    if (lowResResolution.value !== 'all') {
        list = list.filter((e: any) => (e.current_resolution || e.resolution || '').toLowerCase().includes(lowResResolution.value.toLowerCase()));
    }
    return list;
});

const filteredPoorQualityItems = computed(() => {
    let list = poorQualityData.value.items || [];
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter((it: any) => 
            (it.title && it.title.toLowerCase().includes(q)) || 
            (it.series_title && it.series_title.toLowerCase().includes(q)) ||
            (it.evidence && it.evidence.toLowerCase().includes(q))
        );
    }
    if (poorQualityCategory.value !== 'all') {
        list = list.filter((it: any) => it.flag_category === poorQualityCategory.value);
    }
    if (poorQualityMediaType.value !== 'all') {
        list = list.filter((it: any) => it.type === poorQualityMediaType.value);
    }
    return list;
});

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
        if (activeTab.value === 'low_resolution' || activeTab.value === 'poor_quality') {
            fetchUpgrades();
        }
    }, 300);
});

watch(activeTab, (tab) => {
    if ((tab === 'low_resolution' || tab === 'poor_quality') && (!lowResData.value.movies?.length && !poorQualityData.value.items?.length)) {
        fetchUpgrades();
    }
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
    if (!props.initialLowRes && !props.initialPoorQuality) {
        fetchUpgrades();
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
                <div class="relative z-10 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 lg:gap-4 mt-8 pt-6 border-t border-white/10">
                    <!-- Series Completion -->
                    <div
                        @click="activeTab = 'series'"
                        class="p-4 rounded-2xl bg-white/[0.04] hover:bg-emerald-500/10 border border-white/10 hover:border-emerald-500/30 cursor-pointer transition-all active:scale-95 flex items-center gap-3 group"
                        :title="isRTL ? 'عرض نواقص المسلسلات' : 'View TV series gaps'"
                    >
                        <div class="p-3 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 shrink-0 group-hover:scale-105 transition-transform">
                            <Tv class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-2xl font-black text-emerald-400 tracking-tight">
                                {{ metrics.series_completion_rate || 100 }}%
                            </div>
                            <div class="text-[11px] text-slate-400 font-semibold uppercase truncate">
                                {{ isRTL ? 'اكتمال المسلسلات' : 'Series Completion' }}
                            </div>
                        </div>
                    </div>

                    <!-- Franchise Completion -->
                    <div
                        @click="activeTab = 'collections'"
                        class="p-4 rounded-2xl bg-white/[0.04] hover:bg-cyan-500/10 border border-white/10 hover:border-cyan-500/30 cursor-pointer transition-all active:scale-95 flex items-center gap-3 group"
                        :title="isRTL ? 'عرض نواقص سلاسل الأفلام' : 'View movie franchise gaps'"
                    >
                        <div class="p-3 rounded-xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 shrink-0 group-hover:scale-105 transition-transform">
                            <Film class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-2xl font-black text-cyan-400 tracking-tight">
                                {{ metrics.collections_completion_rate || 100 }}%
                            </div>
                            <div class="text-[11px] text-slate-400 font-semibold uppercase truncate">
                                {{ isRTL ? 'اكتمال سلاسل الأفلام' : 'Franchise Completion' }}
                            </div>
                        </div>
                    </div>

                    <!-- Missing Episodes -->
                    <div
                        @click="activeTab = 'episodes'"
                        class="p-4 rounded-2xl bg-white/[0.04] hover:bg-amber-500/10 border border-white/10 hover:border-amber-500/30 cursor-pointer transition-all active:scale-95 flex items-center gap-3 group"
                        :title="isRTL ? 'عرض جميع الحلقات الناقصة' : 'View all missing episodes'"
                    >
                        <div class="p-3 rounded-xl bg-amber-500/20 text-amber-400 border border-amber-500/30 shrink-0 group-hover:scale-105 transition-transform">
                            <Layers class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-2xl font-black text-amber-400 tracking-tight">
                                {{ metrics.missing_episodes_count || 0 }}
                            </div>
                            <div class="text-[11px] text-slate-400 font-semibold uppercase truncate">
                                {{ isRTL ? 'حلقات ناقصة' : 'Missing Episodes' }}
                            </div>
                        </div>
                    </div>

                    <!-- Missing Franchise Movies -->
                    <div
                        @click="activeTab = 'movies'"
                        class="p-4 rounded-2xl bg-white/[0.04] hover:bg-purple-500/10 border border-white/10 hover:border-purple-500/30 cursor-pointer transition-all active:scale-95 flex items-center gap-3 group"
                        :title="isRTL ? 'عرض جميع أفلام السلاسل الناقصة' : 'View all missing franchise movies'"
                    >
                        <div class="p-3 rounded-xl bg-purple-500/20 text-purple-400 border border-purple-500/30 shrink-0 group-hover:scale-105 transition-transform">
                            <Award class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-2xl font-black text-purple-400 tracking-tight">
                                {{ metrics.missing_movies_count || 0 }}
                            </div>
                            <div class="text-[11px] text-slate-400 font-semibold uppercase truncate">
                                {{ isRTL ? 'أفلام سلاسل مفقودة' : 'Missing Movies' }}
                            </div>
                        </div>
                    </div>

                    <!-- Low Resolution Upgrades Card (<720p) -->
                    <div
                        @click="activeTab = 'low_resolution'"
                        class="p-4 rounded-2xl bg-amber-500/[0.06] border border-amber-500/30 hover:border-amber-400/60 hover:bg-amber-500/10 cursor-pointer transition-all active:scale-95 flex items-center gap-3 group"
                        :title="isRTL ? 'عرض كافة الملفات الأقل من 720p وترقيتها فوراً' : 'View all files below 720p for instant 1080p upgrade'"
                    >
                        <div class="p-3 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/40 shrink-0 group-hover:scale-105 transition-transform">
                            <ArrowDownUp class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-2xl font-black text-amber-400 tracking-tight flex items-center gap-1.5">
                                <span>{{ lowResData.total_count || upgradeMetrics.total_low_res || 0 }}</span>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30">&lt;720p</span>
                            </div>
                            <div class="text-[11px] text-slate-400 font-semibold uppercase truncate">
                                {{ isRTL ? 'ترقية دقة (<720p)' : 'Low Resolution' }}
                            </div>
                        </div>
                    </div>

                    <!-- CAM & Hardcoded Subs Card -->
                    <div
                        @click="activeTab = 'poor_quality'"
                        class="p-4 rounded-2xl bg-rose-500/[0.06] border border-rose-500/30 hover:border-rose-400/60 hover:bg-rose-500/10 cursor-pointer transition-all active:scale-95 flex items-center gap-3 group"
                        :title="isRTL ? 'عرض نسخ السينما والترجمات الكورية/الصينية المدمجة' : 'View forensically detected CAM & hardcoded subtitles for clean replacement'"
                    >
                        <div class="p-3 rounded-xl bg-rose-500/20 text-rose-300 border border-rose-500/40 shrink-0 group-hover:scale-105 transition-transform">
                            <AlertTriangle class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-2xl font-black text-rose-400 tracking-tight flex items-center gap-1.5">
                                <span>{{ poorQualityData.total_count || upgradeMetrics.total_poor_quality || 0 }}</span>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-300 border border-rose-500/30">{{ isRTL ? 'سينما/مدمج' : 'CAM/HC' }}</span>
                            </div>
                            <div class="text-[11px] text-slate-400 font-semibold uppercase truncate">
                                {{ isRTL ? 'نسخ CAM وترجمة مدمجة' : 'CAM & HC Subs' }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Reorganized Workspace & Navigation Control Center -->
            <div class="space-y-4">
                <!-- Top Workspace Segmented Bar & Search Input -->
                <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4 p-2 rounded-2xl bg-slate-900/80 border border-white/10 backdrop-blur-xl shadow-xl">
                    <!-- Primary Workspace Segments -->
                    <div class="flex items-center gap-1.5 p-1 rounded-xl bg-slate-950/70 border border-white/10 overflow-x-auto scrollbar-none shrink-0">
                        <!-- Segment 1: Library Gaps Radar -->
                        <button
                            @click="navMode = 'gaps'"
                            :class="[
                                'px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                                navMode === 'gaps'
                                    ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/25 font-black'
                                    : 'text-slate-400 hover:text-white hover:bg-white/5'
                            ]"
                        >
                            <Compass class="w-4 h-4" />
                            <span>{{ isRTL ? 'رادار نواقص المكتبة' : 'Library Gaps Radar' }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black" :class="navMode === 'gaps' ? 'bg-black/20 text-slate-950' : 'bg-cyan-500/20 text-cyan-300'">
                                {{ (metrics.missing_episodes_count || 0) + (metrics.missing_movies_count || 0) }}
                            </span>
                        </button>

                        <!-- Segment 2: Quality Upgrades (<720p & CAM/HC) -->
                        <button
                            @click="navMode = 'upgrades'"
                            :class="[
                                'px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                                navMode === 'upgrades'
                                    ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/25 font-black'
                                    : 'text-slate-400 hover:text-white hover:bg-white/5'
                            ]"
                        >
                            <ArrowDownUp class="w-4 h-4" />
                            <span>{{ isRTL ? 'ترقية الجودات والنسخ' : 'Quality Upgrades' }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black" :class="navMode === 'upgrades' ? 'bg-black/20 text-slate-950' : 'bg-amber-500/20 text-amber-300'">
                                {{ (lowResData.total_count || 0) + (poorQualityData.total_count || 0) }}
                            </span>
                        </button>

                        <!-- Segment 3: Discover New Media (TMDb) -->
                        <button
                            @click="navMode = 'discover'"
                            :class="[
                                'px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                                navMode === 'discover'
                                    ? 'bg-gradient-to-r from-cyan-500 to-purple-600 text-white shadow-md shadow-purple-500/25 font-black'
                                    : 'text-slate-400 hover:text-white hover:bg-white/5'
                            ]"
                        >
                            <Sparkles class="w-4 h-4 text-amber-300" />
                            <span>{{ isRTL ? 'استكشاف وسائط جديدة (TMDb)' : 'Discover New Media' }}</span>
                        </button>
                    </div>

                    <!-- Filter Search Input (Shown for Gaps and Upgrades; Discover has its own TMDb search bar) -->
                    <div v-if="navMode !== 'discover'" class="relative min-w-[260px] lg:w-80">
                        <Search class="w-4 h-4 text-slate-400 absolute top-1/2 -translate-y-1/2" :class="isRTL ? 'right-3.5' : 'left-3.5'" />
                        <input
                            type="text"
                            v-model="searchQuery"
                            :placeholder="navMode === 'upgrades' ? (isRTL ? 'ابحث في عناصر الترقية...' : 'Filter upgrade titles...') : (isRTL ? 'ابحث في النواقص...' : 'Filter gap titles...')"
                            class="w-full bg-slate-950/80 border border-white/10 rounded-xl py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 transition-all"
                            :class="isRTL ? 'pr-10 pl-4' : 'pl-10 pr-4'"
                        />
                    </div>
                </div>

                <!-- Contextual Sub-Tabs (Clean, non-cramped second tier) -->
                <!-- A. Library Gaps Sub-Tabs -->
                <div v-if="navMode === 'gaps'" class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
                    <button
                        @click="activeTab = 'all'"
                        :class="[
                            'px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer border',
                            activeTab === 'all'
                                ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm'
                                : 'text-slate-400 hover:text-white hover:bg-white/5 border-transparent'
                        ]"
                    >
                        <Compass class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'كافة نواقص المكتبة' : 'All Library Gaps' }}</span>
                    </button>

                    <button
                        @click="activeTab = 'series'"
                        :class="[
                            'px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer border',
                            activeTab === 'series'
                                ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm'
                                : 'text-slate-400 hover:text-white hover:bg-white/5 border-transparent'
                        ]"
                    >
                        <Tv class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'نواقص المسلسلات' : 'TV Series Gaps' }}</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-cyan-500/20 text-cyan-300">
                            {{ seriesGaps.length }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'collections'"
                        :class="[
                            'px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer border',
                            activeTab === 'collections'
                                ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm'
                                : 'text-slate-400 hover:text-white hover:bg-white/5 border-transparent'
                        ]"
                    >
                        <Film class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'سلاسل الأفلام غير المكتملة' : 'Franchise Gaps' }}</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-cyan-500/20 text-cyan-300">
                            {{ collectionGaps.length }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'episodes'"
                        :class="[
                            'px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer border',
                            activeTab === 'episodes'
                                ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm'
                                : 'text-slate-400 hover:text-white hover:bg-white/5 border-transparent'
                        ]"
                    >
                        <Clock class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'جميع الحلقات الناقصة' : 'Missing Episodes' }}</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-cyan-500/20 text-cyan-300">
                            {{ metrics.missing_episodes_count || 0 }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'movies'"
                        :class="[
                            'px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer border',
                            activeTab === 'movies'
                                ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm'
                                : 'text-slate-400 hover:text-white hover:bg-white/5 border-transparent'
                        ]"
                    >
                        <Award class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'جميع أفلام السلاسل الناقصة' : 'Missing Movies' }}</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-cyan-500/20 text-cyan-300">
                            {{ metrics.missing_movies_count || 0 }}
                        </span>
                    </button>
                </div>

                <!-- B. Quality Upgrades Sub-Tabs -->
                <div v-if="navMode === 'upgrades'" class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
                    <button
                        @click="activeTab = 'low_resolution'"
                        :class="[
                            'px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer border',
                            activeTab === 'low_resolution'
                                ? 'bg-amber-500 text-slate-950 border-amber-400 shadow-md shadow-amber-500/20 font-black'
                                : 'text-amber-400 hover:text-white bg-amber-500/10 border-amber-500/30 hover:bg-amber-500/20'
                        ]"
                    >
                        <ArrowDownUp class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'ترقية الجودات الضعيفة (<720p)' : 'Low Resolution (<720p)' }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black" :class="activeTab === 'low_resolution' ? 'bg-black/20 text-slate-950' : 'bg-amber-500/20 text-amber-300'">
                            {{ lowResData.total_count || 0 }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'poor_quality'"
                        :class="[
                            'px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer border',
                            activeTab === 'poor_quality'
                                ? 'bg-rose-500 text-white border-rose-400 shadow-md shadow-rose-500/20 font-black'
                                : 'text-rose-400 hover:text-white bg-rose-500/10 border-rose-500/30 hover:bg-rose-500/20'
                        ]"
                    >
                        <AlertTriangle class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'استبدال نسخ السينما والترجمة المدمجة (CAM & HC)' : 'CAM & Hardcoded Subs' }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black" :class="activeTab === 'poor_quality' ? 'bg-black/30 text-white' : 'bg-rose-500/20 text-rose-300'">
                            {{ poorQualityData.total_count || 0 }}
                        </span>
                    </button>
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

            <!-- Loading Spinner for Gap Audits & Quality Upgrades -->
            <div v-else-if="loading || (upgradesLoading && (activeTab === 'low_resolution' || activeTab === 'poor_quality'))" class="py-16 text-center space-y-3">
                <Loader2 class="w-8 h-8 text-cyan-400 animate-spin mx-auto" />
                <p class="text-xs text-slate-400 font-medium">
                    {{ isRTL ? 'جارٍ فحص وتحليل الوسائط وجودات الملفات...' : 'Auditing media library and analyzing video quality...' }}
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

                <!-- SECTION 5: LOW RESOLUTION (<720p) UPGRADES -->
                <div v-if="activeTab === 'low_resolution'" class="space-y-6">
                    <!-- Header Card -->
                    <div class="glass-panel border border-amber-500/30 rounded-3xl p-6 bg-gradient-to-r from-amber-950/40 via-slate-900/60 to-slate-900/80 space-y-4">
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div class="p-3.5 rounded-2xl bg-amber-500/20 text-amber-400 border border-amber-500/30 shrink-0">
                                    <ArrowDownUp class="w-6 h-6" />
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h2 class="text-xl font-black text-white tracking-tight">
                                            {{ isRTL ? 'ترقية الجودات الضعيفة (أقل من 720p)' : 'Low Resolution Upgrades (<720p)' }}
                                        </h2>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                            {{ (filteredLowResMovies.length + filteredLowResEpisodes.length) }} {{ isRTL ? 'عنصر بحاجة للترقية' : 'items flagged' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-300 leading-relaxed max-w-3xl">
                                        {{ isRTL 
                                            ? 'يعرض فقط الملفات القديمة أو الضعيفة في مكتبتك ذات الدقة الأقل من 720p (مثل 480p SD، 360p، 576p SD). جودات 720p و 1080p و 4K مستثناة تماماً. يمكنك بنقرة واحدة استبدالها بنسخ نقية 1080p أو 4K.' 
                                            : 'Strictly audits media in your library below 720p (480p SD, 360p, 576p SD, Unknown). Standard 720p, 1080p, and 4K media are excluded. Use 1-click upgrade to fetch pristine 1080p/4K releases.' 
                                        }}
                                    </p>
                                </div>
                            </div>

                            <button
                                @click="fetchUpgrades(true)"
                                :disabled="upgradesLoading"
                                class="px-4 py-2 rounded-xl text-xs font-bold bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 flex items-center gap-2 cursor-pointer shrink-0 disabled:opacity-50"
                            >
                                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': upgradesLoading }" />
                                <span>{{ isRTL ? 'إعادة فحص الدقة' : 'Re-scan Resolution' }}</span>
                            </button>
                        </div>

                        <!-- Filter Sub-bar -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-white/10">
                            <!-- Media Type Filter -->
                            <div class="flex items-center gap-1 bg-black/40 p-1 rounded-xl border border-white/10">
                                <button
                                    @click="lowResMediaType = 'all'"
                                    :class="[
                                        'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                        lowResMediaType === 'all' ? 'bg-amber-500 text-slate-950 font-black shadow' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    {{ isRTL ? 'الكل' : 'All Media' }} ({{ lowResData.total_count || 0 }})
                                </button>
                                <button
                                    @click="lowResMediaType = 'movie'"
                                    :class="[
                                        'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                        lowResMediaType === 'movie' ? 'bg-amber-500 text-slate-950 font-black shadow' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    🎬 {{ isRTL ? 'أفلام' : 'Movies' }} ({{ lowResData.movies?.length || 0 }})
                                </button>
                                <button
                                    @click="lowResMediaType = 'episode'"
                                    :class="[
                                        'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                        lowResMediaType === 'episode' ? 'bg-amber-500 text-slate-950 font-black shadow' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    📺 {{ isRTL ? 'حلقات' : 'Episodes' }} ({{ lowResData.episodes?.length || 0 }})
                                </button>
                            </div>

                            <!-- Resolution Filter -->
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-xs text-slate-400 font-semibold">{{ isRTL ? 'الدقة الحالية:' : 'Filter Res:' }}</span>
                                <button
                                    v-for="r in ['all', '480p', '576p', '360p', 'SD', 'Unknown']"
                                    :key="r"
                                    @click="lowResResolution = r"
                                    :class="[
                                        'px-2.5 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer border',
                                        lowResResolution === r
                                            ? 'bg-amber-500/30 text-amber-300 border-amber-400'
                                            : 'bg-white/5 text-slate-400 border-white/10 hover:text-white'
                                    ]"
                                >
                                    {{ r === 'all' ? (isRTL ? 'كافة الدقات' : 'All Sub-720p') : r }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Movies Grid -->
                    <div v-if="(lowResMediaType === 'all' || lowResMediaType === 'movie') && filteredLowResMovies.length > 0" class="space-y-3">
                        <h3 class="text-sm font-black text-amber-400 uppercase tracking-wider flex items-center gap-2">
                            <Film class="w-4 h-4" />
                            <span>{{ isRTL ? 'أفلام مكتبتك بدقة أقل من 720p' : 'Library Movies Below 720p' }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-500/20 text-amber-300 border border-amber-500/30">{{ filteredLowResMovies.length }}</span>
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div
                                v-for="m in filteredLowResMovies"
                                :key="m.id"
                                class="p-4 rounded-2xl glass-panel border border-amber-500/20 hover:border-amber-500/50 bg-slate-900/50 transition-all flex items-center justify-between gap-4 group hover:shadow-lg hover:shadow-amber-500/5"
                            >
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <div class="relative shrink-0">
                                        <img
                                            v-if="m.poster_path"
                                            :src="m.poster_path"
                                            :alt="m.title"
                                            class="w-12 h-18 object-cover rounded-xl border border-white/10 shadow-md"
                                            loading="lazy"
                                            @error="(e: any) => (e.target.style.display = 'none')"
                                        />
                                        <div v-else class="w-12 h-18 rounded-xl bg-slate-950 border border-white/10 flex items-center justify-center text-slate-600">
                                            <Film class="w-6 h-6" />
                                        </div>
                                        <span class="absolute -bottom-1.5 -right-1.5 px-1.5 py-0.2 rounded text-[9px] font-black bg-amber-500 text-slate-950 shadow">
                                            {{ m.current_resolution }}
                                        </span>
                                    </div>

                                    <div class="space-y-1 min-w-0">
                                        <h4 class="text-sm font-bold text-white truncate" :title="m.title">
                                            {{ isRTL && m.title_ar ? m.title_ar : m.title }}
                                        </h4>
                                        <div class="flex items-center gap-2 text-xs text-slate-400 flex-wrap">
                                            <span v-if="m.release_year">{{ m.release_year }}</span>
                                            <span>•</span>
                                            <span class="text-amber-300 font-semibold">{{ m.file_size_human }}</span>
                                            <span v-if="m.video_codec !== 'Unknown'">•</span>
                                            <span v-if="m.video_codec !== 'Unknown'" class="font-mono text-[10px]">{{ m.video_codec }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 pt-0.5">
                                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-cyan-500/15 text-cyan-300 border border-cyan-500/30">
                                                {{ isRTL ? 'الترقية المقترحة: 1080p BluRay' : 'Target: 1080p BluRay' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <button
                                    @click="openUpgradeTorrentModal(m, 'low_resolution')"
                                    class="px-3.5 py-2 rounded-xl text-xs font-black bg-gradient-to-r from-amber-500 to-amber-400 text-slate-950 hover:from-amber-400 hover:to-amber-300 shadow-md shadow-amber-500/20 active:scale-95 transition-all flex items-center gap-1.5 cursor-pointer shrink-0"
                                    :title="isRTL ? 'البحث عن ترقية تورنت نظيفة 1080p/4K' : 'Search 1080p/4K upgrade torrents'"
                                >
                                    <Compass class="w-3.5 h-3.5" />
                                    <span>{{ isRTL ? 'ترقية 1080p' : 'Upgrade' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Episodes Grid -->
                    <div v-if="(lowResMediaType === 'all' || lowResMediaType === 'episode') && filteredLowResEpisodes.length > 0" class="space-y-3">
                        <h3 class="text-sm font-black text-amber-400 uppercase tracking-wider flex items-center gap-2">
                            <Tv class="w-4 h-4" />
                            <span>{{ isRTL ? 'حلقات المسلسلات بدقة أقل من 720p' : 'TV Episodes Below 720p' }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-500/20 text-amber-300 border border-amber-500/30">{{ filteredLowResEpisodes.length }}</span>
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div
                                v-for="ep in filteredLowResEpisodes"
                                :key="ep.id"
                                class="p-4 rounded-2xl glass-panel border border-amber-500/20 hover:border-amber-500/50 bg-slate-900/50 transition-all flex items-center justify-between gap-4 group hover:shadow-lg hover:shadow-amber-500/5"
                            >
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <div class="relative shrink-0">
                                        <img
                                            v-if="ep.poster_path || ep.still_path"
                                            :src="ep.still_path || ep.poster_path"
                                            :alt="ep.title"
                                            class="w-12 h-18 object-cover rounded-xl border border-white/10 shadow-md"
                                            loading="lazy"
                                            @error="(e: any) => (e.target.style.display = 'none')"
                                        />
                                        <div v-else class="w-12 h-18 rounded-xl bg-slate-950 border border-white/10 flex items-center justify-center text-slate-600">
                                            <Tv class="w-6 h-6" />
                                        </div>
                                        <span class="absolute -bottom-1.5 -right-1.5 px-1.5 py-0.2 rounded text-[9px] font-black bg-amber-500 text-slate-950 shadow">
                                            {{ ep.current_resolution }}
                                        </span>
                                    </div>

                                    <div class="space-y-1 min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="px-1.5 py-0.2 rounded text-[10px] font-black bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                                {{ ep.episode_code }}
                                            </span>
                                            <span class="text-xs font-bold text-white truncate" :title="ep.series_title">
                                                {{ ep.series_title }}
                                            </span>
                                        </div>
                                        <h4 class="text-xs text-slate-300 truncate" :title="ep.episode_title">
                                            {{ ep.episode_title }}
                                        </h4>
                                        <div class="flex items-center gap-2 text-xs text-slate-400">
                                            <span class="text-amber-300 font-semibold">{{ ep.file_size_human }}</span>
                                            <span>•</span>
                                            <span class="text-[10px] font-mono">{{ ep.video_codec }}</span>
                                        </div>
                                    </div>
                                </div>

                                <button
                                    @click="openUpgradeTorrentModal(ep, 'low_resolution')"
                                    class="px-3.5 py-2 rounded-xl text-xs font-black bg-gradient-to-r from-amber-500 to-amber-400 text-slate-950 hover:from-amber-400 hover:to-amber-300 shadow-md shadow-amber-500/20 active:scale-95 transition-all flex items-center gap-1.5 cursor-pointer shrink-0"
                                    :title="isRTL ? 'البحث عن حلقة 1080p نقية' : 'Search 1080p upgrade episode'"
                                >
                                    <Compass class="w-3.5 h-3.5" />
                                    <span>{{ isRTL ? 'ترقية' : 'Upgrade' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="filteredLowResMovies.length === 0 && filteredLowResEpisodes.length === 0" class="text-center py-16 px-4 rounded-3xl glass-panel border border-emerald-500/20 bg-emerald-950/10 space-y-3">
                        <CheckCircle2 class="w-12 h-12 text-emerald-400 mx-auto" />
                        <h3 class="text-lg font-black text-white">
                            {{ isRTL ? 'رائع! لا توجد وسائط بدقة ضعيفة في مكتبتك' : 'All Media Meets HD Quality Standard!' }}
                        </h3>
                        <p class="text-xs text-slate-400 max-w-md mx-auto">
                            {{ isRTL ? 'كافة الأفلام والحلقات في مكتبتك بدقة 720p HD أو 1080p أو 4K ولا توجد أي ملفات بدقة منخفضة.' : 'Every movie and episode in your library is 720p HD, 1080p Full HD, or 4K Ultra HD. No sub-720p files found.' }}
                        </p>
                    </div>
                </div>

                <!-- SECTION 6: CAM & HARDCODED SUBTITLES (HC) REPLACEMENTS -->
                <div v-if="activeTab === 'poor_quality'" class="space-y-6">
                    <!-- Header Card -->
                    <div class="glass-panel border border-rose-500/30 rounded-3xl p-6 bg-gradient-to-r from-rose-950/40 via-slate-900/60 to-slate-900/80 space-y-4">
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div class="p-3.5 rounded-2xl bg-rose-500/20 text-rose-400 border border-rose-500/30 shrink-0">
                                    <AlertTriangle class="w-6 h-6" />
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h2 class="text-xl font-black text-white tracking-tight">
                                            {{ isRTL ? 'استبدال نسخ السينما (CAM) والترجمات الأجنبية المدمجة (HC)' : 'CAM & Hardcoded Subtitle Rips (HC)' }}
                                        </h2>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                            {{ filteredPoorQualityItems.length }} {{ isRTL ? 'نسخة رديئة مرصودة' : 'flagged releases' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-300 leading-relaxed max-w-3xl">
                                        {{ isRTL 
                                            ? 'نظام تدقيق جنائي عميق يرصد التسجيلات السينمائية (CAM / Telesync) والنسخ التي تحتوي على ترجمات أجنبية مطبوعة داخل الفيديو (HC / Korean / Chinese / Arabic) أو شعارات مواقع القرصنة وصوت Mono المشبوه، حتى لو كانت ملفاتك منظمة ومعاد تسميتها في H:\\Entertainment. يمكنك بضغطة واحدة استبدالها بنسخ نقية وخالية من الترجمات المطبوعة.' 
                                            : 'Multi-layer forensics detecting cinema recordings (CAM/TS), hardcoded foreign subtitles (KORSUB, Chinese HC, watermarks), and anomalous mono audio on already-organized library files. 1-click replacement locates clean 1080p/4K WEB-DL/BluRay copies without burned-in text.' 
                                        }}
                                    </p>
                                </div>
                            </div>

                            <button
                                @click="fetchUpgrades(true)"
                                :disabled="upgradesLoading"
                                class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/40 flex items-center gap-2 cursor-pointer shrink-0 disabled:opacity-50"
                            >
                                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': upgradesLoading }" />
                                <span>{{ isRTL ? 'إعادة الفحص الجنائي' : 'Re-run Forensics' }}</span>
                            </button>
                        </div>

                        <!-- Filter Sub-bar -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-white/10">
                            <!-- Issue Category Filter -->
                            <div class="flex items-center gap-1 bg-black/40 p-1 rounded-xl border border-white/10 flex-wrap">
                                <button
                                    @click="poorQualityCategory = 'all'"
                                    :class="[
                                        'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                        poorQualityCategory === 'all' ? 'bg-rose-500 text-white font-black shadow' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    {{ isRTL ? 'الكل' : 'All Issues' }} ({{ poorQualityData.total_count || 0 }})
                                </button>
                                <button
                                    @click="poorQualityCategory = 'hardcoded_subs'"
                                    :class="[
                                        'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                        poorQualityCategory === 'hardcoded_subs' ? 'bg-rose-500 text-white font-black shadow' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    <Subtitles class="w-3.5 h-3.5 inline mr-1" />
                                    {{ isRTL ? 'ترجمات مدمجة (HC)' : 'Hardcoded Subs (HC)' }} ({{ poorQualityData.by_category?.hardcoded_subs || 0 }})
                                </button>
                                <button
                                    @click="poorQualityCategory = 'cam_recorded'"
                                    :class="[
                                        'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                        poorQualityCategory === 'cam_recorded' ? 'bg-rose-500 text-white font-black shadow' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    🎥 {{ isRTL ? 'تسجيلات سينما (CAM)' : 'CAM Recordings' }} ({{ poorQualityData.by_category?.cam_recorded || 0 }})
                                </button>
                                <button
                                    @click="poorQualityCategory = 'watermarked_or_low_audio'"
                                    :class="[
                                        'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                        poorQualityCategory === 'watermarked_or_low_audio' ? 'bg-rose-500 text-white font-black shadow' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    <Volume2 class="w-3.5 h-3.5 inline mr-1" />
                                    {{ isRTL ? 'شعارات / صوت مونو' : 'Watermark / Mono Audio' }} ({{ poorQualityData.by_category?.watermarked_or_low_audio || 0 }})
                                </button>
                            </div>

                            <!-- Media Type Filter -->
                            <div class="flex items-center gap-1 bg-black/40 p-1 rounded-xl border border-white/10">
                                <button
                                    @click="poorQualityMediaType = 'all'"
                                    :class="[
                                        'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                        poorQualityMediaType === 'all' ? 'bg-rose-500/30 text-rose-300 font-bold border border-rose-500/40' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    {{ isRTL ? 'الكل' : 'All' }}
                                </button>
                                <button
                                    @click="poorQualityMediaType = 'movie'"
                                    :class="[
                                        'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                        poorQualityMediaType === 'movie' ? 'bg-rose-500/30 text-rose-300 font-bold border border-rose-500/40' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    🎬 {{ isRTL ? 'أفلام' : 'Movies' }}
                                </button>
                                <button
                                    @click="poorQualityMediaType = 'episode'"
                                    :class="[
                                        'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                        poorQualityMediaType === 'episode' ? 'bg-rose-500/30 text-rose-300 font-bold border border-rose-500/40' : 'text-slate-400 hover:text-white'
                                    ]"
                                >
                                    📺 {{ isRTL ? 'مسلسلات' : 'Series' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Items Grid -->
                    <div v-if="filteredPoorQualityItems.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="it in filteredPoorQualityItems"
                            :key="it.id"
                            class="p-5 rounded-3xl glass-panel border border-rose-500/25 hover:border-rose-500/60 bg-slate-900/60 transition-all flex flex-col justify-between gap-4 group hover:shadow-xl hover:shadow-rose-500/10"
                        >
                            <div class="space-y-3">
                                <!-- Top Row: Issue Badges -->
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1 border"
                                        :class="it.flag_category === 'cam_recorded' ? 'bg-red-500/20 text-red-300 border-red-500/30' : (it.flag_category === 'hardcoded_subs' ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' : 'bg-rose-500/20 text-rose-300 border-rose-500/30')"
                                    >
                                        <AlertCircle class="w-3 h-3" />
                                        <span>{{ isRTL && it.badge_ar ? it.badge_ar : it.badge_en }}</span>
                                    </span>

                                    <span
                                        class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase"
                                        :class="it.severity === 'high' ? 'bg-red-500/20 text-red-400 border border-red-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'"
                                    >
                                        {{ it.severity }} {{ isRTL ? 'أهمية' : 'priority' }}
                                    </span>
                                </div>

                                <!-- Media Info Row -->
                                <div class="flex items-start gap-3.5">
                                    <div class="relative shrink-0">
                                        <img
                                            v-if="it.poster_path"
                                            :src="it.poster_path"
                                            :alt="it.title"
                                            class="w-14 h-20 object-cover rounded-xl border border-white/10 shadow-md"
                                            loading="lazy"
                                            @error="(e: any) => (e.target.style.display = 'none')"
                                        />
                                        <div v-else class="w-14 h-20 rounded-xl bg-slate-950 border border-white/10 flex items-center justify-center text-slate-600">
                                            <Film v-if="it.type === 'movie'" class="w-6 h-6" />
                                            <Tv v-else class="w-6 h-6" />
                                        </div>
                                    </div>

                                    <div class="space-y-1 min-w-0">
                                        <h4 class="text-sm font-black text-white truncate" :title="it.title">
                                            {{ it.title }}
                                        </h4>
                                        <p v-if="it.series_title && it.type === 'episode'" class="text-xs text-purple-300 font-semibold truncate">
                                            {{ it.series_title }}
                                        </p>
                                        <div class="flex items-center gap-2 text-xs text-slate-400 flex-wrap">
                                            <span v-if="it.release_year">{{ it.release_year }}</span>
                                            <span>•</span>
                                            <span class="text-rose-300 font-semibold">{{ it.current_resolution }}</span>
                                            <span v-if="it.file_size_human">•</span>
                                            <span v-if="it.file_size_human">{{ it.file_size_human }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Forensic Evidence Box -->
                                <div class="p-2.5 rounded-xl bg-black/50 border border-white/10 space-y-1 font-mono text-[11px]">
                                    <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider flex items-center gap-1">
                                        <Eye class="w-3 h-3 text-rose-400" />
                                        <span>{{ isRTL ? 'دليل الفحص والتحليل:' : 'Forensic Evidence:' }}</span>
                                    </div>
                                    <p class="text-slate-200 leading-snug text-xs break-words">
                                        {{ it.evidence }}
                                    </p>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <button
                                @click="openUpgradeTorrentModal(it, 'poor_quality')"
                                class="w-full py-2.5 rounded-xl text-xs font-black bg-gradient-to-r from-rose-500 to-red-600 hover:from-rose-400 hover:to-red-500 text-white shadow-lg shadow-rose-500/20 active:scale-95 transition-all flex items-center justify-center gap-2 cursor-pointer mt-1"
                                :title="isRTL ? 'البحث عن نسخة نقية واستبعاد أي ترجمة مدمجة' : 'Find clean 1080p/4K WEB-DL or BluRay release'"
                            >
                                <ShieldCheck class="w-4 h-4 text-white" />
                                <span>{{ isRTL ? 'استبدال بنسخة نقية (WEB-DL / BluRay)' : 'Find Clean Replacement' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-16 px-4 rounded-3xl glass-panel border border-emerald-500/20 bg-emerald-950/10 space-y-3">
                        <ShieldCheck class="w-12 h-12 text-emerald-400 mx-auto" />
                        <h3 class="text-lg font-black text-white">
                            {{ isRTL ? 'مكتبتك نظيفة وخالية من نسخ CAM والترجمات المدمجة!' : 'Pristine Library! No CAM or Hardcoded Subs Detected' }}
                        </h3>
                        <p class="text-xs text-slate-400 max-w-md mx-auto">
                            {{ isRTL ? 'تم فحص وسائط مكتبتك المنظمة، ولم يتم العثور على أي تسجيلات سينمائية رديئة أو ترجمات كورية/صينية مطبوعة.' : 'All audited files in your organized media folders are confirmed clean with proper audio tracks and no burned-in foreign subtitles.' }}
                        </p>
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
