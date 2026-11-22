<script setup lang="ts">
import { ref, computed, onMounted, watch, defineAsyncComponent } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/components/layout/AppLayout.vue';
import PageBanner from '@/components/common/PageBanner.vue';
import { useI18n } from '@/i18n/useI18n';
import { formatBytes } from '@/lib/formatters';
import ScoutFranchiseSkeleton from '@/components/common/skeletons/ScoutFranchiseSkeleton.vue';

const TorrentSuggestionsModal = defineAsyncComponent(
    () => import('@/components/scout/TorrentSuggestionsModal.vue'),
);
const FastOrganizeModal = defineAsyncComponent(
    () => import('@/components/scout/FastOrganizeModal.vue'),
);
import {
    Compass,
    RefreshCw,
    Search,
    Film,
    Tv,
    Layers,
    AlertCircle,
    Download,
    Check,
    FolderSync,
    Sparkles,
    Loader2,
    Calendar,
    TrendingUp,
    Award,
    Clock,
    Star,
    ArrowRight,
    ShieldCheck,
    CheckCircle2,
    XCircle,
    ChevronDown,
    ChevronUp,
    Eye,
    X,
    AlertTriangle,
    ShieldAlert,
    ArrowDownUp,
    HardDrive,
    Filter,
    Subtitles,
    Volume2,
    Flame,
} from '@lucide/vue';

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
const lowResData = ref<any>(
    props.initialLowRes || { movies: [], episodes: [], total_count: 0 },
);
const poorQualityData = ref<any>(
    props.initialPoorQuality || { items: [], total_count: 0, by_category: {} },
);
const upgradesLoading = ref(false);

// Reactive synchronization for deferred Inertia props
watch(
    () => props.initialSeriesGaps,
    (val) => {
        if (val) seriesGaps.value = val;
    },
    { immediate: true },
);
watch(
    () => props.initialCollectionGaps,
    (val) => {
        if (val) collectionGaps.value = val;
    },
    { immediate: true },
);
watch(
    () => props.initialEpisodes,
    (val) => {
        if (val) flatEpisodes.value = val;
    },
    { immediate: true },
);
watch(
    () => props.initialSeasons,
    (val) => {
        if (val) flatSeasons.value = val;
    },
    { immediate: true },
);
watch(
    () => props.initialMovies,
    (val) => {
        if (val) flatMovies.value = val;
    },
    { immediate: true },
);
watch(
    () => props.initialTrending,
    (val) => {
        if (val) discoverResults.value = val;
    },
    { immediate: true },
);
watch(
    () => props.initialLowRes,
    (val) => {
        if (val) lowResData.value = val;
    },
    { immediate: true },
);
watch(
    () => props.initialPoorQuality,
    (val) => {
        if (val) poorQualityData.value = val;
    },
    { immediate: true },
);
watch(
    () => props.initialMetrics,
    (val) => {
        if (val) metrics.value = val;
    },
    { immediate: true },
);
watch(
    () => props.initialUpgradeMetrics,
    (val) => {
        if (val) upgradeMetrics.value = val;
    },
    { immediate: true },
);

// Upgrade Tab Specific Filters & View State
const lowResMediaType = ref<'all' | 'movie' | 'episode'>('all');
const lowResResolution = ref<string>('all');
const lowResEpisodeView = ref<'grouped' | 'individual'>('grouped');
const expandedLowResSeries = ref<Record<string, boolean>>({});
const individualEpisodesLimit = ref(60);
const poorQualityCategory = ref<string>('all');
const poorQualityMediaType = ref<'all' | 'movie' | 'episode'>('all');

const activeTab = ref<
    | 'all'
    | 'discover'
    | 'series'
    | 'collections'
    | 'episodes'
    | 'movies'
    | 'all_upgrades'
    | 'low_resolution'
    | 'poor_quality'
>('all');

// Primary Navigation Mode: 'gaps' | 'upgrades' | 'discover'
const navMode = computed<'gaps' | 'upgrades' | 'discover'>({
    get: () => {
        if (activeTab.value === 'discover') return 'discover';
        if (
            activeTab.value === 'all_upgrades' ||
            activeTab.value === 'low_resolution' ||
            activeTab.value === 'poor_quality'
        )
            return 'upgrades';
        return 'gaps';
    },
    set: (mode: 'gaps' | 'upgrades' | 'discover') => {
        if (mode === 'discover') {
            activeTab.value = 'discover';
        } else if (mode === 'upgrades') {
            if (
                activeTab.value !== 'all_upgrades' &&
                activeTab.value !== 'low_resolution' &&
                activeTab.value !== 'poor_quality'
            ) {
                activeTab.value = 'all_upgrades';
            }
        } else {
            if (
                activeTab.value === 'discover' ||
                activeTab.value === 'all_upgrades' ||
                activeTab.value === 'low_resolution' ||
                activeTab.value === 'poor_quality'
            ) {
                activeTab.value = 'all';
            }
        }
    },
});

const toggleLowResSeries = (id: string) => {
    expandedLowResSeries.value[id] = !expandedLowResSeries.value[id];
};

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
            headers: { Accept: 'application/json' },
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
        const csrfToken =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') || '';
        const res = await fetch('/api/scout/refresh', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
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
        if (data.low_resolution || data.low_res)
            lowResData.value = data.low_resolution || data.low_res;
        if (data.poor_quality) poorQualityData.value = data.poor_quality;
        if (data.upgrade_metrics || data.metrics)
            upgradeMetrics.value = data.upgrade_metrics || data.metrics;
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
        const res = await fetch(
            `/api/scout/discover/search?${qParams.toString()}`,
            {
                headers: { Accept: 'application/json' },
            },
        );
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
        const res = await fetch(
            `/api/scout/discover/series-details?tmdb_id=${media.tmdb_id}`,
            {
                headers: { Accept: 'application/json' },
            },
        );
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
    const isSeries =
        item.media_type === 'series' ||
        item.media_type === 'tv' ||
        Boolean(item.first_air_date) ||
        Boolean(item.name && !item.movie_title);
    const titleVal =
        item.title ||
        item.name ||
        item.original_title ||
        item.original_name ||
        '';
    const yearVal =
        item.release_year ||
        item.year ||
        (item.first_air_date
            ? new Date(item.first_air_date).getFullYear()
            : item.release_date
              ? new Date(item.release_date).getFullYear()
              : undefined);
    const gapPayload = {
        id: `discover_${item.tmdb_id || item.id}_${seasonNum || 1}`,
        type: isSeries ? (seasonNum ? 'season' : 'series') : 'movie',
        title: titleVal,
        name: item.name,
        original_title: item.original_title,
        original_name: item.original_name,
        movie_title: !isSeries ? titleVal : undefined,
        series_title: isSeries ? titleVal : undefined,
        release_year: yearVal,
        year: yearVal,
        air_date: item.first_air_date || item.release_date,
        season_number: seasonNum || 1,
        poster_path: item.poster_path,
        still_path: item.still_path || null,
        backdrop_path: item.backdrop_path,
        tmdb_id: item.tmdb_id || item.id,
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
        } else if (cloned.series_title) {
            cloned.type = 'series';
        }
    }
    if (cloned.type === 'tv') {
        cloned.type = 'series';
    }
    if (!cloned.title) {
        cloned.title =
            cloned.movie_title ||
            cloned.series_title ||
            cloned.name ||
            cloned.original_title ||
            cloned.original_name ||
            '';
    }
    selectedGap.value = cloned;
    isTorrentModalOpen.value = true;
};

const openUpgradeTorrentModal = (
    item: any,
    category: 'low_resolution' | 'poor_quality',
) => {
    const isEp = item.type === 'episode';
    const sNum = item.season_number ? Number(item.season_number) : 1;
    const epNum = item.episode_number ? Number(item.episode_number) : 1;

    const gapPayload = {
        id:
            item.id ||
            `upgrade_${item.local_id || (item.title ? item.title.replace(/\s+/g, '_') : 'item')}`,
        type: isEp ? 'episode' : 'movie',
        title: item.title,
        movie_title: !isEp ? item.title : undefined,
        series_title: isEp ? item.series_title || item.title : undefined,
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

const openSeriesUpgradeTorrentModal = (group: any) => {
    const gapPayload = {
        id: `upgrade_series_${group.series_id || group.id}`,
        type: 'series',
        title: group.series_title,
        series_title: group.series_title,
        series_title_ar: group.series_title_ar,
        release_year: group.release_year,
        year: group.release_year,
        poster_path: group.poster_path,
        tmdb_id: group.tmdb_id,
        imdb_id: group.imdb_id || null,
        season_number: group.episodes?.[0]?.season_number || 1,
        upgrade_mode: true,
        clean_only: true,
        upgrade_category: 'low_resolution',
    };
    openTorrentModal(gapPayload);
};

const formatBytesShort = (bytes: number): string => formatBytes(bytes);

const fetchUpgrades = async (forceRefresh = false) => {
    upgradesLoading.value = true;
    try {
        const qParams = new URLSearchParams({
            refresh: forceRefresh ? '1' : '0',
            query: searchQuery.value,
        });
        const res = await fetch(`/api/scout/upgrades?${qParams.toString()}`, {
            headers: { Accept: 'application/json' },
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
        list = list.filter(
            (m: any) =>
                (m.title && m.title.toLowerCase().includes(q)) ||
                (m.title_ar && m.title_ar.includes(q)),
        );
    }
    if (lowResResolution.value !== 'all') {
        list = list.filter((m: any) =>
            (m.current_resolution || m.resolution || '')
                .toLowerCase()
                .includes(lowResResolution.value.toLowerCase()),
        );
    }
    return list;
});

const filteredLowResEpisodes = computed(() => {
    let list = lowResData.value.episodes || [];
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(
            (e: any) =>
                (e.title && e.title.toLowerCase().includes(q)) ||
                (e.series_title && e.series_title.toLowerCase().includes(q)) ||
                (e.series_title_ar && e.series_title_ar.includes(q)),
        );
    }
    if (lowResResolution.value !== 'all') {
        list = list.filter((e: any) =>
            (e.current_resolution || e.resolution || '')
                .toLowerCase()
                .includes(lowResResolution.value.toLowerCase()),
        );
    }
    return list;
});

const visibleLowResEpisodes = computed(() => {
    return filteredLowResEpisodes.value.slice(0, individualEpisodesLimit.value);
});

const filteredLowResSeriesGroups = computed(() => {
    const groupsMap: Record<string, any> = {};
    for (const ep of filteredLowResEpisodes.value) {
        const key = ep.series_id
            ? `series_${ep.series_id}`
            : `title_${ep.series_title || 'TV Series'}`;
        if (!groupsMap[key]) {
            groupsMap[key] = {
                id: key,
                series_id: ep.series_id,
                series_title: ep.series_title || 'TV Series',
                series_title_ar: ep.series_title_ar,
                poster_path: ep.series_poster_path || ep.poster_path,
                release_year: ep.release_year,
                tmdb_id: ep.tmdb_id,
                imdb_id: ep.imdb_id,
                episodes_count: 0,
                resolutions: [] as string[],
                total_size_bytes: 0,
                episodes: [] as any[],
            };
        }
        groupsMap[key].episodes_count++;
        groupsMap[key].total_size_bytes += Number(ep.file_size_bytes || 0);
        const res = ep.current_resolution || ep.resolution || 'SD';
        if (!groupsMap[key].resolutions.includes(res)) {
            groupsMap[key].resolutions.push(res);
        }
        groupsMap[key].episodes.push(ep);
    }

    return Object.values(groupsMap)
        .map((g: any) => ({
            ...g,
            total_size_human: formatBytesShort(g.total_size_bytes),
        }))
        .sort((a: any, b: any) => b.episodes_count - a.episodes_count);
});

const filteredPoorQualityItems = computed(() => {
    let list = poorQualityData.value.items || [];
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(
            (it: any) =>
                (it.title && it.title.toLowerCase().includes(q)) ||
                (it.series_title &&
                    it.series_title.toLowerCase().includes(q)) ||
                (it.evidence && it.evidence.toLowerCase().includes(q)),
        );
    }
    if (poorQualityCategory.value !== 'all') {
        list = list.filter(
            (it: any) => it.flag_category === poorQualityCategory.value,
        );
    }
    if (poorQualityMediaType.value !== 'all') {
        list = list.filter((it: any) => it.type === poorQualityMediaType.value);
    }
    return list;
});

const onDownloadStarted = (payload: any) => {
    if (payload?.gapItem?.id) {
        const ep = flatEpisodes.value.find((e) => e.id === payload.gapItem.id);
        if (ep) ep.is_downloading = true;
        const mov = flatMovies.value.find((m) => m.id === payload.gapItem.id);
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
        if (
            activeTab.value === 'all_upgrades' ||
            activeTab.value === 'low_resolution' ||
            activeTab.value === 'poor_quality'
        ) {
            fetchUpgrades();
        }
    }, 300);
});

watch(activeTab, (tab) => {
    if (
        (tab === 'all_upgrades' ||
            tab === 'low_resolution' ||
            tab === 'poor_quality') &&
        !lowResData.value.movies?.length &&
        !poorQualityData.value.items?.length
    ) {
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
    if (
        (!props.initialTrending || props.initialTrending.length === 0) &&
        activeTab.value === 'discover'
    ) {
        searchDiscover();
    }
});
</script>

<template>
    <AppLayout>
        <Head
            :title="
                isRTL
                    ? 'مستكشف الوسائط والرادار الذكي'
                    : 'Media Scout & Library Completion Radar'
            "
        />

        <div class="space-y-8 pb-16" :dir="isRTL ? 'rtl' : 'ltr'">
            <!-- Hero Banner -->
            <PageBanner
                :icon="Compass"
                :title="
                    isRTL
                        ? 'رادار اكتمال المكتبة واستكشاف الوسائط الجديدة'
                        : 'Library Completion & Media Discovery Radar'
                "
                :badge="
                    isRTL
                        ? 'مستكشف نواقص المكتبة واستكشاف الجديد'
                        : 'Media Scout Radar & Discovery'
                "
                :description="
                    isRTL
                        ? 'يكتشف الحلقات والمواسم المفقودة بدقة من مسلسلاتك، ويتيح استكشاف وبحث كافة الأفلام والمسلسلات الجديدة عبر TMDb مع روابط تورنت عالية السرعة والتنظيم التلقائي.'
                        : 'Audits your library against TMDB for missing released episodes and seasons, plus live search & discovery for new unowned movies and TV series with 1-click torrent downloads.'
                "
            >
                <template #actions>
                    <!-- Fast Organize & Scan Button -->
                    <button
                        type="button"
                        @click="isFastOrganizeOpen = true"
                        class="bg-primary text-primary-foreground shadow-primary/25 flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-black shadow-lg transition-all hover:brightness-105 active:scale-95"
                        :title="
                            isRTL
                                ? 'تنظيم وفحص الملفات في H:\\Entertainment'
                                : 'Organize and add to Library (Scan)'
                        "
                    >
                        <FolderSync class="size-4" />
                        <span>{{
                            isRTL
                                ? 'تنظيم وإضافة للمكتبة'
                                : 'Organize & Scan Library'
                        }}</span>
                    </button>

                    <!-- Refresh Button -->
                    <button
                        type="button"
                        @click="refreshGaps"
                        :disabled="refreshing"
                        class="cf-glass border-border/80 text-foreground hover:border-primary/40 flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-2.5 text-xs font-bold shadow-sm transition-all active:scale-95 disabled:opacity-50"
                    >
                        <RefreshCw
                            class="text-primary size-4"
                            :class="{ 'animate-spin': refreshing }"
                        />
                        <span>{{
                            refreshing
                                ? isRTL
                                    ? 'جارٍ الفحص...'
                                    : 'Auditing...'
                                : isRTL
                                  ? 'فحص وتحديث الرادار'
                                  : 'Scan & Refresh Gaps'
                        }}</span>
                    </button>
                </template>
            </PageBanner>

            <!-- Stats Badges -->
            <div
                class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6 lg:gap-4"
            >
                <!-- Series Completion -->
                <div
                    @click="activeTab = 'series'"
                    class="cf-glass border-border/80 hover:border-primary/45 group flex cursor-pointer items-center gap-3 rounded-2xl border p-4 tabular-nums transition-all active:scale-95"
                    :title="
                        isRTL ? 'عرض نواقص المسلسلات' : 'View TV series gaps'
                    "
                >
                    <div
                        class="shrink-0 rounded-xl border border-emerald-500/30 bg-emerald-500/15 p-3 text-emerald-400 transition-transform group-hover:scale-105"
                    >
                        <Tv class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <div
                            class="text-2xl font-black tracking-tight text-emerald-400"
                        >
                            {{ metrics.series_completion_rate || 100 }}%
                        </div>
                        <div
                            class="text-muted-foreground truncate text-[11px] font-semibold uppercase"
                        >
                            {{
                                isRTL ? 'اكتمال المسلسلات' : 'Series Completion'
                            }}
                        </div>
                    </div>
                </div>

                <!-- Franchise Completion -->
                <div
                    @click="activeTab = 'collections'"
                    class="cf-glass border-border/80 hover:border-primary/45 group flex cursor-pointer items-center gap-3 rounded-2xl border p-4 tabular-nums transition-all active:scale-95"
                    :title="
                        isRTL
                            ? 'عرض نواقص سلاسل الأفلام'
                            : 'View movie franchise gaps'
                    "
                >
                    <div
                        class="border-primary/30 bg-primary/15 text-primary shrink-0 rounded-xl border p-3 transition-transform group-hover:scale-105"
                    >
                        <Film class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <div
                            class="text-primary text-2xl font-black tracking-tight"
                        >
                            {{ metrics.collections_completion_rate || 100 }}%
                        </div>
                        <div
                            class="text-muted-foreground truncate text-[11px] font-semibold uppercase"
                        >
                            {{
                                isRTL
                                    ? 'اكتمال سلاسل الأفلام'
                                    : 'Franchise Completion'
                            }}
                        </div>
                    </div>
                </div>

                <!-- Missing Episodes -->
                <div
                    @click="activeTab = 'episodes'"
                    class="cf-glass border-border/80 hover:border-primary/45 group flex cursor-pointer items-center gap-3 rounded-2xl border p-4 tabular-nums transition-all active:scale-95"
                    :title="
                        isRTL
                            ? 'عرض جميع الحلقات الناقصة'
                            : 'View all missing episodes'
                    "
                >
                    <div
                        class="shrink-0 rounded-xl border border-amber-500/30 bg-amber-500/15 p-3 text-amber-400 transition-transform group-hover:scale-105"
                    >
                        <Layers class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <div
                            class="text-2xl font-black tracking-tight text-amber-400"
                        >
                            {{ metrics.missing_episodes_count || 0 }}
                        </div>
                        <div
                            class="text-muted-foreground truncate text-[11px] font-semibold uppercase"
                        >
                            {{ isRTL ? 'حلقات ناقصة' : 'Missing Episodes' }}
                        </div>
                    </div>
                </div>

                <!-- Missing Franchise Movies -->
                <div
                    @click="activeTab = 'movies'"
                    class="cf-glass border-border/80 hover:border-primary/45 group flex cursor-pointer items-center gap-3 rounded-2xl border p-4 tabular-nums transition-all active:scale-95"
                    :title="
                        isRTL
                            ? 'عرض جميع أفلام السلاسل الناقصة'
                            : 'View all missing franchise movies'
                    "
                >
                    <div
                        class="border-primary/30 bg-primary/15 text-primary shrink-0 rounded-xl border p-3 transition-transform group-hover:scale-105"
                    >
                        <Award class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <div
                            class="text-foreground text-2xl font-black tracking-tight"
                        >
                            {{ metrics.missing_movies_count || 0 }}
                        </div>
                        <div
                            class="text-muted-foreground truncate text-[11px] font-semibold uppercase"
                        >
                            {{ isRTL ? 'أفلام سلاسل مفقودة' : 'Missing Movies' }}
                        </div>
                    </div>
                </div>

                <!-- Low Resolution Upgrades Card (<720p) -->
                <div
                    @click="activeTab = 'low_resolution'"
                    class="cf-glass group flex cursor-pointer items-center gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/[0.06] p-4 tabular-nums transition-all hover:border-amber-400/60 hover:bg-amber-500/10 active:scale-95"
                    :title="
                        isRTL
                            ? 'عرض كافة الملفات الأقل من 720p وترقيتها فوراً'
                            : 'View all files below 720p for instant 1080p upgrade'
                    "
                >
                    <div
                        class="shrink-0 rounded-xl border border-amber-500/40 bg-amber-500/20 p-3 text-amber-300 transition-transform group-hover:scale-105"
                    >
                        <ArrowDownUp class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <div
                            class="flex items-center gap-1.5 text-2xl font-black tracking-tight text-amber-400"
                        >
                            <span>{{
                                lowResData.total_count ||
                                upgradeMetrics.total_low_res ||
                                0
                            }}</span>
                            <span
                                class="rounded border border-amber-500/30 bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-bold text-amber-300"
                                >&lt;720p</span
                            >
                        </div>
                        <div
                            class="text-muted-foreground truncate text-[11px] font-semibold uppercase"
                        >
                            {{ isRTL ? 'ترقية دقة (<720p)' : 'Low Resolution' }}
                        </div>
                    </div>
                </div>

                <!-- CAM & Hardcoded Subs Card -->
                <div
                    @click="activeTab = 'poor_quality'"
                    class="cf-glass group flex cursor-pointer items-center gap-3 rounded-2xl border border-rose-500/30 bg-rose-500/[0.06] p-4 tabular-nums transition-all hover:border-rose-400/60 hover:bg-rose-500/10 active:scale-95"
                    :title="
                        isRTL
                            ? 'عرض نسخ السينما والترجمات الكورية/الصينية المدمجة'
                            : 'View forensically detected CAM & hardcoded subtitles for clean replacement'
                    "
                >
                    <div
                        class="shrink-0 rounded-xl border border-rose-500/40 bg-rose-500/20 p-3 text-rose-300 transition-transform group-hover:scale-105"
                    >
                        <AlertTriangle class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <div
                            class="flex items-center gap-1.5 text-2xl font-black tracking-tight text-rose-400"
                        >
                            <span>{{
                                poorQualityData.total_count ||
                                upgradeMetrics.total_poor_quality ||
                                0
                            }}</span>
                            <span
                                class="rounded border border-rose-500/30 bg-rose-500/20 px-1.5 py-0.5 text-[10px] font-bold text-rose-300"
                                >{{ isRTL ? 'سينما/مدمج' : 'CAM/HC' }}</span
                            >
                        </div>
                        <div
                            class="text-muted-foreground truncate text-[11px] font-semibold uppercase"
                        >
                            {{
                                isRTL ? 'نسخ CAM وترجمة مدمجة' : 'CAM & HC Subs'
                            }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reorganized Workspace & Navigation Control Center -->
            <div class="space-y-4">
                <!-- Top Workspace Segmented Bar & Search Input -->
                <div
                    class="cf-glass border-border/80 flex flex-col items-stretch justify-between gap-4 rounded-2xl border p-2 shadow-xl lg:flex-row lg:items-center"
                >
                    <!-- Primary Workspace Segments -->
                    <div
                        class="border-border/80 bg-card/70 flex shrink-0 scrollbar-none items-center gap-1.5 overflow-x-auto rounded-xl border p-1"
                    >
                        <!-- Segment 1: Library Gaps Radar -->
                        <button
                            type="button"
                            @click="navMode = 'gaps'"
                            :class="[
                                'flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold whitespace-nowrap transition-all',
                                navMode === 'gaps'
                                    ? 'bg-primary text-primary-foreground shadow-primary/25 font-black shadow-md'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                        >
                            <Compass class="size-4" />
                            <span>{{
                                isRTL
                                    ? 'رادار نواقص المكتبة'
                                    : 'Library Gaps Radar'
                            }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-black tabular-nums"
                                :class="
                                    navMode === 'gaps'
                                        ? 'bg-primary-foreground/20 text-primary-foreground'
                                        : 'bg-primary/15 text-primary'
                                "
                            >
                                {{
                                    (metrics.missing_episodes_count || 0) +
                                    (metrics.missing_movies_count || 0)
                                }}
                            </span>
                        </button>

                        <!-- Segment 2: Quality Upgrades (<720p & CAM/HC) -->
                        <button
                            type="button"
                            @click="navMode = 'upgrades'"
                            :class="[
                                'flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold whitespace-nowrap transition-all',
                                navMode === 'upgrades'
                                    ? 'bg-primary text-primary-foreground shadow-primary/25 font-black shadow-md'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                        >
                            <ArrowDownUp class="size-4" />
                            <span>{{
                                isRTL
                                    ? 'ترقية الجودات والنسخ'
                                    : 'Quality Upgrades'
                            }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-black tabular-nums"
                                :class="
                                    navMode === 'upgrades'
                                        ? 'bg-primary-foreground/20 text-primary-foreground'
                                        : 'bg-amber-500/20 text-amber-300'
                                "
                            >
                                {{
                                    (lowResData.total_count || 0) +
                                    (poorQualityData.total_count || 0)
                                }}
                            </span>
                        </button>

                        <!-- Segment 3: Discover New Media (TMDb) -->
                        <button
                            type="button"
                            @click="navMode = 'discover'"
                            :class="[
                                'flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold whitespace-nowrap transition-all',
                                navMode === 'discover'
                                    ? 'bg-primary text-primary-foreground shadow-primary/25 font-black shadow-md'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                        >
                            <Sparkles class="size-4" />
                            <span>{{
                                isRTL
                                    ? 'استكشاف وسائط جديدة (TMDb)'
                                    : 'Discover New Media'
                            }}</span>
                        </button>
                    </div>

                    <!-- Filter Search Input -->
                    <div
                        v-if="navMode !== 'discover'"
                        class="relative min-w-[260px] lg:w-80"
                    >
                        <Search
                            class="text-muted-foreground pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2"
                        />
                        <input
                            type="text"
                            v-model="searchQuery"
                            :placeholder="
                                navMode === 'upgrades'
                                    ? isRTL
                                        ? 'ابحث في عناصر الترقية...'
                                        : 'Filter upgrade titles...'
                                    : isRTL
                                      ? 'ابحث في النواقص...'
                                      : 'Filter gap titles...'
                            "
                            class="cf-field w-full rounded-xl py-2 ps-10 pe-4 text-xs"
                        />
                    </div>
                </div>

                <!-- Contextual Sub-Tabs (Clean, non-cramped second tier) -->
                <!-- A. Library Gaps Sub-Tabs -->
                <div
                    v-if="navMode === 'gaps'"
                    class="flex scrollbar-none items-center gap-2 overflow-x-auto pb-1"
                >
                    <button
                        @click="activeTab = 'all'"
                        :class="[
                            'flex cursor-pointer items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-bold whitespace-nowrap transition-all',
                            activeTab === 'all'
                                ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300 shadow-sm'
                                : 'border-transparent text-slate-400 hover:bg-white/5 hover:text-white',
                        ]"
                    >
                        <Compass class="h-3.5 w-3.5" />
                        <span>{{
                            isRTL ? 'كافة نواقص المكتبة' : 'All Library Gaps'
                        }}</span>
                    </button>

                    <button
                        @click="activeTab = 'series'"
                        :class="[
                            'flex cursor-pointer items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-bold whitespace-nowrap transition-all',
                            activeTab === 'series'
                                ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300 shadow-sm'
                                : 'border-transparent text-slate-400 hover:bg-white/5 hover:text-white',
                        ]"
                    >
                        <Tv class="h-3.5 w-3.5" />
                        <span>{{
                            isRTL ? 'نواقص المسلسلات' : 'TV Series Gaps'
                        }}</span>
                        <span
                            class="rounded-full bg-cyan-500/20 px-1.5 py-0.5 text-[10px] font-black text-cyan-300"
                        >
                            {{ seriesGaps.length }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'collections'"
                        :class="[
                            'flex cursor-pointer items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-bold whitespace-nowrap transition-all',
                            activeTab === 'collections'
                                ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300 shadow-sm'
                                : 'border-transparent text-slate-400 hover:bg-white/5 hover:text-white',
                        ]"
                    >
                        <Film class="h-3.5 w-3.5" />
                        <span>{{
                            isRTL ? 'سلاسل الأفلام غير المكتملة' : 'Franchise Gaps'
                        }}</span>
                        <span
                            class="rounded-full bg-cyan-500/20 px-1.5 py-0.5 text-[10px] font-black text-cyan-300"
                        >
                            {{ collectionGaps.length }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'episodes'"
                        :class="[
                            'flex cursor-pointer items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-bold whitespace-nowrap transition-all',
                            activeTab === 'episodes'
                                ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300 shadow-sm'
                                : 'border-transparent text-slate-400 hover:bg-white/5 hover:text-white',
                        ]"
                    >
                        <Clock class="h-3.5 w-3.5" />
                        <span>{{
                            isRTL ? 'جميع الحلقات الناقصة' : 'Missing Episodes'
                        }}</span>
                        <span
                            class="rounded-full bg-cyan-500/20 px-1.5 py-0.5 text-[10px] font-black text-cyan-300"
                        >
                            {{ metrics.missing_episodes_count || 0 }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'movies'"
                        :class="[
                            'flex cursor-pointer items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-bold whitespace-nowrap transition-all',
                            activeTab === 'movies'
                                ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300 shadow-sm'
                                : 'border-transparent text-slate-400 hover:bg-white/5 hover:text-white',
                        ]"
                    >
                        <Award class="h-3.5 w-3.5" />
                        <span>{{
                            isRTL
                                ? 'جميع أفلام السلاسل الناقصة'
                                : 'Missing Movies'
                        }}</span>
                        <span
                            class="rounded-full bg-cyan-500/20 px-1.5 py-0.5 text-[10px] font-black text-cyan-300"
                        >
                            {{ metrics.missing_movies_count || 0 }}
                        </span>
                    </button>
                </div>

                <!-- B. Quality Upgrades Sub-Tabs -->
                <div
                    v-if="navMode === 'upgrades'"
                    class="flex scrollbar-none items-center gap-2 overflow-x-auto pb-1"
                >
                    <button
                        @click="activeTab = 'all_upgrades'"
                        :class="[
                            'flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-2 text-xs font-bold whitespace-nowrap transition-all',
                            activeTab === 'all_upgrades'
                                ? 'border-amber-400/60 bg-gradient-to-r from-rose-500 to-amber-500 font-black text-white shadow-md shadow-amber-500/20'
                                : 'border-white/10 bg-white/5 text-slate-300 hover:bg-white/10 hover:text-white',
                        ]"
                    >
                        <Flame class="h-3.5 w-3.5" />
                        <span>{{
                            isRTL
                                ? 'كافة ترقيات الجودة والأعطال'
                                : 'All Quality Upgrades'
                        }}</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px] font-black"
                            :class="
                                activeTab === 'all_upgrades'
                                    ? 'bg-black/30 text-white'
                                    : 'bg-white/10 text-slate-300'
                            "
                        >
                            {{
                                (poorQualityData.total_count || 0) +
                                (lowResData.total_count || 0)
                            }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'poor_quality'"
                        :class="[
                            'flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-2 text-xs font-bold whitespace-nowrap transition-all',
                            activeTab === 'poor_quality'
                                ? 'border-rose-400 bg-rose-500 font-black text-white shadow-md shadow-rose-500/20'
                                : 'border-rose-500/30 bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 hover:text-white',
                        ]"
                    >
                        <AlertTriangle class="h-3.5 w-3.5" />
                        <span>{{
                            isRTL
                                ? 'نسخ السينما والترجمة المدمجة (CAM & HC)'
                                : 'CAM, HC & Forensic Rips'
                        }}</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px] font-black"
                            :class="
                                activeTab === 'poor_quality'
                                    ? 'bg-black/30 text-white'
                                    : 'bg-rose-500/20 text-rose-300'
                            "
                        >
                            {{ poorQualityData.total_count || 0 }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'low_resolution'"
                        :class="[
                            'flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-2 text-xs font-bold whitespace-nowrap transition-all',
                            activeTab === 'low_resolution'
                                ? 'border-amber-400 bg-amber-500 font-black text-slate-950 shadow-md shadow-amber-500/20'
                                : 'border-amber-500/30 bg-amber-500/10 text-amber-400 hover:bg-amber-500/20 hover:text-white',
                        ]"
                    >
                        <ArrowDownUp class="h-3.5 w-3.5" />
                        <span>{{
                            isRTL
                                ? 'ترقية الجودات الضعيفة (<720p)'
                                : 'Low Resolution (<720p)'
                        }}</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px] font-black"
                            :class="
                                activeTab === 'low_resolution'
                                    ? 'bg-black/20 text-slate-950'
                                    : 'bg-amber-500/20 text-amber-300'
                            "
                        >
                            {{ lowResData.total_count || 0 }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- TAB 0: DISCOVER NEW MEDIA (TMDb Live Search & Trending) -->
            <div v-if="activeTab === 'discover'" class="space-y-6">
                <!-- Discover Control Bar -->
                <div
                    class="cf-glass border-border/80 space-y-4 rounded-3xl border p-6"
                >
                    <div
                        class="flex flex-col items-stretch justify-between gap-4 lg:flex-row lg:items-center"
                    >
                        <!-- TMDb Live Search Input -->
                        <div class="relative flex-1">
                            <Search
                                class="text-primary pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2"
                            />
                            <input
                                type="text"
                                v-model="discoverQuery"
                                :placeholder="
                                    isRTL
                                        ? 'ابحث عن أي فيلم أو مسلسل جديد في قاعدة بيانات TMDb العالمية...'
                                        : 'Search any new movie or TV series on TMDb database...'
                                "
                                class="cf-field w-full rounded-2xl py-3 ps-12 pe-4 text-sm"
                            />
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Media Type Filter Pills -->
                            <div
                                class="border-border/80 bg-card/70 flex shrink-0 items-center gap-1 rounded-2xl border p-1"
                            >
                                <button
                                    type="button"
                                    @click="discoverType = 'all'"
                                    :class="[
                                        'cursor-pointer rounded-xl px-3 py-1.5 text-xs font-bold transition-all',
                                        discoverType === 'all'
                                            ? 'bg-primary text-primary-foreground shadow-primary/20 shadow-md'
                                            : 'text-muted-foreground hover:text-foreground',
                                    ]"
                                >
                                    {{ isRTL ? 'الكل' : 'All Media' }}
                                </button>
                                <button
                                    type="button"
                                    @click="discoverType = 'movie'"
                                    :class="[
                                        'cursor-pointer rounded-xl px-3 py-1.5 text-xs font-bold transition-all',
                                        discoverType === 'movie'
                                            ? 'bg-primary text-primary-foreground shadow-primary/20 shadow-md'
                                            : 'text-muted-foreground hover:text-foreground',
                                    ]"
                                >
                                    🎬 {{ isRTL ? 'أفلام' : 'Movies' }}
                                </button>
                                <button
                                    type="button"
                                    @click="discoverType = 'tv'"
                                    :class="[
                                        'cursor-pointer rounded-xl px-3 py-1.5 text-xs font-bold transition-all',
                                        discoverType === 'tv'
                                            ? 'bg-primary text-primary-foreground shadow-primary/20 shadow-md'
                                            : 'text-muted-foreground hover:text-foreground',
                                    ]"
                                >
                                    📺 {{ isRTL ? 'مسلسلات' : 'TV Shows' }}
                                </button>
                            </div>

                            <!-- Style Filter Pills (Animated vs Live-Action) -->
                            <div
                                class="border-border/80 bg-card/70 flex shrink-0 items-center gap-1 rounded-2xl border p-1"
                            >
                                <button
                                    type="button"
                                    @click="discoverStyle = 'all'"
                                    :class="[
                                        'cursor-pointer rounded-xl px-3 py-1.5 text-xs font-bold transition-all',
                                        discoverStyle === 'all'
                                            ? 'bg-primary text-primary-foreground shadow-primary/20 shadow-md'
                                            : 'text-muted-foreground hover:text-foreground',
                                    ]"
                                >
                                    {{ isRTL ? 'كل الأنماط' : 'All Styles' }}
                                </button>
                                <button
                                    type="button"
                                    @click="discoverStyle = 'animated'"
                                    :class="[
                                        'cursor-pointer rounded-xl px-3 py-1.5 text-xs font-bold transition-all',
                                        discoverStyle === 'animated'
                                            ? 'bg-primary text-primary-foreground shadow-primary/20 shadow-md'
                                            : 'text-muted-foreground hover:text-foreground',
                                    ]"
                                >
                                    🎨 {{ isRTL ? 'رسوم متحركة' : 'Animated' }}
                                </button>
                                <button
                                    type="button"
                                    @click="discoverStyle = 'live_action'"
                                    :class="[
                                        'cursor-pointer rounded-xl px-3 py-1.5 text-xs font-bold transition-all',
                                        discoverStyle === 'live_action'
                                            ? 'bg-primary text-primary-foreground shadow-primary/20 shadow-md'
                                            : 'text-muted-foreground hover:text-foreground',
                                    ]"
                                >
                                    🎭 {{ isRTL ? 'تمثيل حي' : 'Live-Action' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="text-muted-foreground flex items-center justify-between text-xs"
                    >
                        <span class="flex items-center gap-1.5">
                            <Sparkles class="text-primary size-3.5" />
                            <span>{{
                                discoverQuery
                                    ? isRTL
                                        ? `نتائج البحث عن "${discoverQuery}":`
                                        : `Search results for "${discoverQuery}":`
                                    : isRTL
                                      ? 'الأكثر رواجاً هذا الأسبوع على TMDb:'
                                      : '🔥 Trending This Week on TMDb:'
                            }}</span>
                        </span>
                        <span
                            v-if="discoverLoading"
                            class="text-primary flex items-center gap-1.5 font-bold"
                        >
                            <Loader2 class="size-3.5 animate-spin" />
                            <span>{{
                                isRTL
                                    ? 'جارٍ البحث في TMDb...'
                                    : 'Querying TMDb...'
                            }}</span>
                        </span>
                    </div>
                </div>

                <!-- Discover Skeletons Grid (6 items) -->
                <div
                    v-if="discoverLoading"
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
                >
                    <div
                        v-for="s in 6"
                        :key="s"
                        class="cf-glass border-border/60 flex flex-col justify-between overflow-hidden rounded-3xl border p-2"
                    >
                        <div
                            class="cf-shimmer bg-muted/60 relative aspect-[2/3] w-full overflow-hidden rounded-2xl"
                        ></div>
                        <div class="space-y-2 p-3">
                            <div
                                class="cf-shimmer bg-muted/60 h-4 w-4/5 rounded"
                            ></div>
                            <div class="flex items-center justify-between">
                                <div
                                    class="cf-shimmer bg-muted/40 h-3 w-1/3 rounded"
                                ></div>
                                <div
                                    class="cf-shimmer bg-muted/40 h-3 w-1/4 rounded"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Discover Results Grid -->
                <div
                    v-else-if="filteredDiscoverResults.length > 0"
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
                >
                    <div
                        v-for="item in filteredDiscoverResults"
                        :key="item.tmdb_id"
                        class="cf-glass border-border/80 hover:border-primary/45 group flex flex-col justify-between overflow-hidden rounded-3xl border transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl"
                    >
                        <!-- Poster Container -->
                        <div
                            class="relative aspect-[2/3] w-full overflow-hidden bg-slate-950"
                        >
                            <img
                                v-if="item.poster_path"
                                :src="item.poster_path"
                                :alt="item.title"
                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                loading="lazy"
                                @error="
                                    (e: any) =>
                                        (e.target.style.display = 'none')
                                "
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center text-slate-700"
                            >
                                <Film
                                    v-if="item.media_type === 'movie'"
                                    class="h-12 w-12"
                                />
                                <Tv v-else class="h-12 w-12" />
                            </div>

                            <!-- Rating Badge -->
                            <div
                                v-if="item.rating"
                                class="absolute top-2.5 left-2.5 flex items-center gap-1 rounded-lg border border-white/10 bg-black/80 px-2 py-0.5 text-[10px] font-black text-amber-400 shadow-md backdrop-blur-md"
                            >
                                <Star
                                    class="h-3 w-3 fill-amber-400 text-amber-400"
                                />
                                <span>{{ item.rating }}</span>
                            </div>

                            <!-- Media Type & Style Chips (Top Right) -->
                            <div
                                class="absolute top-2.5 right-2.5 flex flex-col items-end gap-1"
                            >
                                <span
                                    v-if="item.is_animated !== undefined"
                                    :class="[
                                        'rounded-lg border px-2 py-0.5 text-[10px] font-black shadow-md backdrop-blur-md',
                                        item.is_animated
                                            ? 'border-emerald-400 bg-emerald-500/90 text-slate-950'
                                            : 'border-amber-400 bg-amber-500/90 text-slate-950',
                                    ]"
                                >
                                    {{
                                        item.is_animated
                                            ? isRTL
                                                ? '🎨 رسوم متحركة'
                                                : '🎨 Animated'
                                            : isRTL
                                              ? '🎭 تمثيل حي'
                                              : '🎭 Live-Action'
                                    }}
                                </span>
                                <span
                                    class="rounded-lg border border-white/10 bg-black/80 px-2 py-0.5 text-[10px] font-black tracking-wider text-white uppercase backdrop-blur-md"
                                >
                                    {{
                                        item.media_type === 'series' ||
                                        item.media_type === 'tv'
                                            ? isRTL
                                                ? '📺 مسلسل'
                                                : '📺 Series'
                                            : isRTL
                                              ? '🎬 فيلم'
                                              : '🎬 Movie'
                                    }}
                                </span>
                            </div>

                            <!-- In Library Status Overlay -->
                            <div
                                class="absolute inset-x-2 bottom-2 flex items-center justify-center"
                            >
                                <span
                                    v-if="item.in_library"
                                    class="flex w-full items-center justify-center gap-1 rounded-xl bg-emerald-500/95 px-2 py-1 text-center text-[11px] font-black text-slate-950 shadow-md backdrop-blur-md"
                                >
                                    <CheckCircle2 class="h-3.5 w-3.5" />
                                    <span>{{
                                        isRTL ? 'متوفر بالمكتبة' : 'In Library'
                                    }}</span>
                                </span>
                                <span
                                    v-else
                                    class="flex w-full items-center justify-center gap-1 rounded-xl bg-cyan-500/95 px-2 py-1 text-center text-[11px] font-black text-slate-950 shadow-md backdrop-blur-md"
                                >
                                    <Sparkles class="h-3.5 w-3.5" />
                                    <span>{{
                                        isRTL
                                            ? 'جديد / غير متوفر'
                                            : 'New / Not Owned'
                                    }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Card Content & Action Button -->
                        <div
                            class="flex flex-1 flex-col justify-between space-y-2.5 p-3.5"
                        >
                            <div>
                                <h4
                                    class="truncate text-xs font-black text-white"
                                    :title="item.title"
                                >
                                    {{ item.title }}
                                </h4>
                                <div
                                    class="mt-0.5 flex items-center justify-between text-[11px] text-slate-400"
                                >
                                    <span class="font-bold text-slate-300">
                                        {{
                                            item.release_year ||
                                            item.year ||
                                            item.first_air_date
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            item.owned_episodes_count !==
                                                undefined &&
                                            item.owned_episodes_count > 0
                                        "
                                        class="font-bold text-emerald-400"
                                    >
                                        {{ item.owned_episodes_count }}
                                        {{ isRTL ? 'حلقة' : 'eps' }}
                                    </span>
                                    <span
                                        v-else-if="item.is_animated"
                                        class="text-[10px] font-semibold text-emerald-400/90"
                                    >
                                        {{ isRTL ? 'رسوم متحركة' : 'Animated' }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-[10px] font-semibold text-amber-400/90"
                                    >
                                        {{ isRTL ? 'تمثيل حي' : 'Live-Action' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="space-y-1.5 pt-1">
                                <!-- Series Inspector Button -->
                                <button
                                    v-if="
                                        item.media_type === 'series' ||
                                        item.media_type === 'tv'
                                    "
                                    @click="openSeriesInspector(item)"
                                    class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-white/10 py-1.5 text-xs font-bold text-white transition-all hover:bg-white/20"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                    <span>{{
                                        isRTL
                                            ? 'فحص المواسم'
                                            : 'Inspect Seasons'
                                    }}</span>
                                </button>

                                <!-- Direct Find Torrents Button -->
                                <button
                                    @click="openDiscoverTorrentModal(item)"
                                    class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-cyan-500 py-1.5 text-xs font-black text-slate-950 shadow-md shadow-cyan-500/20 transition-all hover:bg-cyan-400 active:scale-95"
                                >
                                    <Compass class="h-3.5 w-3.5" />
                                    <span>{{
                                        isRTL
                                            ? 'البحث عن تورنت'
                                            : 'Find Torrents'
                                    }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="glass-panel space-y-3 rounded-3xl border border-white/10 px-4 py-16 text-center"
                >
                    <Search class="mx-auto h-10 w-10 text-slate-500" />
                    <h3 class="text-base font-bold text-white">
                        {{
                            isRTL
                                ? 'لم يتم العثور على نتائج'
                                : 'No Results Found'
                        }}
                    </h3>
                    <p class="mx-auto max-w-sm text-xs text-slate-400">
                        {{
                            discoverResults.length > 0
                                ? isRTL
                                    ? 'لا توجد نتائج تطابق نمط العرض المحدد (رسوم متحركة / تمثيل حي). جرّب اختيار "كل الأنماط".'
                                    : 'No items match the selected style filter (Animated / Live-Action). Try selecting "All Styles".'
                                : isRTL
                                  ? 'جرّب البحث باسم الفيلم أو المسلسل باللغة الإنجليزية أو العربية.'
                                  : 'Try searching by English or Arabic title to discover new media.'
                        }}
                    </p>
                </div>
            </div>

            <!-- Loading Skeletons & Spinner for Gap Audits & Quality Upgrades -->
            <div
                v-else-if="
                    loading || (upgradesLoading && navMode === 'upgrades')
                "
                class="space-y-6 py-6"
            >
                <div class="flex items-center justify-center gap-3 text-center">
                    <Loader2 class="h-6 w-6 animate-spin text-cyan-400" />
                    <p class="text-xs font-semibold text-slate-300">
                        {{
                            isRTL
                                ? 'جارٍ فحص وتحليل الوسائط وجودات الملفات...'
                                : 'Auditing media library and running deep forensic checks...'
                        }}
                    </p>
                </div>
                <div
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="n in 6"
                        :key="n"
                        class="glass-panel animate-pulse space-y-4 rounded-3xl border border-white/10 bg-slate-900/40 p-5"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="h-20 w-14 shrink-0 rounded-xl bg-white/10"
                            ></div>
                            <div class="flex-1 space-y-2">
                                <div
                                    class="h-4 w-3/4 rounded bg-white/10"
                                ></div>
                                <div class="h-3 w-1/2 rounded bg-white/5"></div>
                                <div class="h-3 w-1/3 rounded bg-white/5"></div>
                            </div>
                        </div>
                        <div class="h-12 rounded-xl bg-white/5"></div>
                        <div class="h-9 rounded-xl bg-white/10"></div>
                    </div>
                </div>
            </div>

            <!-- MAIN CONTENT SECTIONS (LIBRARY GAPS) -->
            <div v-else class="space-y-10">
                <!-- SECTION 1: TV SERIES GAPS (Grouped by Show) -->
                <div
                    v-if="activeTab === 'all' || activeTab === 'series'"
                    class="space-y-4"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Tv class="h-5 w-5 text-cyan-400" />
                            <h2
                                class="text-xl font-black tracking-tight text-white"
                            >
                                {{
                                    isRTL
                                        ? 'المسلسلات التي تحتوي على حلقات أو مواسم ناقصة'
                                        : 'TV Series with Missing Content'
                                }}
                            </h2>
                        </div>
                        <span class="text-xs font-bold text-slate-400">
                            {{ seriesGaps.length }}
                            {{ isRTL ? 'مسلسلات' : 'Series' }}
                        </span>
                    </div>

                    <!-- Series Cards Grid -->
                    <div
                        v-if="seriesGaps.length > 0"
                        class="grid grid-cols-1 gap-6 lg:grid-cols-2"
                    >
                        <div
                            v-for="sg in seriesGaps"
                            :key="sg.series_id"
                            class="glass-panel flex flex-col justify-between space-y-5 overflow-hidden rounded-3xl border border-white/10 bg-slate-900/40 p-6 transition-all hover:border-cyan-500/30"
                        >
                            <!-- Show Header -->
                            <div class="flex items-start gap-4">
                                <img
                                    v-if="sg.poster_path"
                                    :src="sg.poster_path"
                                    :alt="sg.series_title"
                                    class="h-28 w-20 shrink-0 rounded-2xl border border-white/10 object-cover shadow-lg"
                                    loading="lazy"
                                    @error="
                                        (e: any) =>
                                            (e.target.style.display = 'none')
                                    "
                                />
                                <div
                                    v-else
                                    class="flex h-28 w-20 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-slate-950 text-slate-600"
                                >
                                    <Tv class="h-8 w-8" />
                                </div>

                                <div class="min-w-0 flex-1 space-y-2">
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <h3
                                            class="truncate text-lg font-black text-white"
                                        >
                                            {{ sg.series_title }}
                                        </h3>
                                        <span
                                            class="shrink-0 rounded-full border border-cyan-500/30 bg-cyan-500/20 px-2.5 py-0.5 text-xs font-black text-cyan-300"
                                        >
                                            {{ sg.completion_percent }}%
                                        </span>
                                    </div>
                                    <p
                                        v-if="sg.series_title_ar"
                                        class="truncate text-xs text-slate-400"
                                    >
                                        {{ sg.series_title_ar }}
                                    </p>

                                    <!-- Completion Bar -->
                                    <div class="space-y-1 pt-1">
                                        <div
                                            class="h-2 w-full overflow-hidden rounded-full border border-white/5 bg-slate-950"
                                        >
                                            <div
                                                class="h-full rounded-full transition-all duration-500"
                                                :class="
                                                    sg.completion_percent >= 90
                                                        ? 'bg-gradient-to-r from-cyan-500 to-emerald-400'
                                                        : 'bg-gradient-to-r from-amber-500 to-cyan-400'
                                                "
                                                :style="{
                                                    width: `${sg.completion_percent}%`,
                                                }"
                                            ></div>
                                        </div>
                                        <div
                                            class="flex items-center justify-between text-[11px] font-semibold text-slate-400"
                                        >
                                            <span
                                                >{{ sg.local_episodes_count }}
                                                {{
                                                    isRTL
                                                        ? 'حلقة متوفرة'
                                                        : 'Episodes Owned'
                                                }}</span
                                            >
                                            <span
                                                class="font-bold text-amber-400"
                                            >
                                                {{ sg.missing_episodes_count }}
                                                {{
                                                    isRTL
                                                        ? 'حلقة ناقصة'
                                                        : 'Missing'
                                                }}
                                                <span
                                                    v-if="
                                                        sg.missing_seasons_count >
                                                        0
                                                    "
                                                >
                                                    +
                                                    {{
                                                        sg.missing_seasons_count
                                                    }}
                                                    {{
                                                        isRTL
                                                            ? 'مواسم'
                                                            : 'Seasons'
                                                    }}
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Missing Content Breakdown -->
                            <div
                                class="space-y-2.5 border-t border-white/5 pt-2"
                            >
                                <div
                                    class="flex items-center justify-between text-xs font-bold text-slate-300"
                                >
                                    <span>{{
                                        isRTL
                                            ? 'الحلقات والمواسم المطلوب تحميلها:'
                                            : 'Available Missing Content:'
                                    }}</span>
                                    <button
                                        v-if="sg.missing_episodes.length > 2"
                                        @click="
                                            toggleSeriesAccordion(sg.series_id)
                                        "
                                        class="flex cursor-pointer items-center gap-1 text-[11px] text-cyan-400 hover:text-cyan-300"
                                    >
                                        <span>{{
                                            expandedSeries[sg.series_id]
                                                ? isRTL
                                                    ? 'إخفاء'
                                                    : 'Show Less'
                                                : isRTL
                                                  ? `عرض الكل (${sg.missing_episodes.length})`
                                                  : `Show All (${sg.missing_episodes.length})`
                                        }}</span>
                                        <component
                                            :is="
                                                expandedSeries[sg.series_id]
                                                    ? ChevronUp
                                                    : ChevronDown
                                            "
                                            class="h-3.5 w-3.5"
                                        />
                                    </button>
                                </div>

                                <!-- Missing Entire Seasons (Distinct Season Pack) -->
                                <div
                                    v-for="sGap in sg.missing_seasons"
                                    :key="sGap.id"
                                    class="flex items-center justify-between gap-3 rounded-2xl border border-purple-500/30 bg-black/40 p-3"
                                >
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="rounded-md border border-purple-500/30 bg-purple-500/20 px-2 py-0.5 text-[10px] font-black text-purple-300"
                                            >
                                                {{ sGap.season_name }}
                                            </span>
                                            <span
                                                class="text-xs font-bold text-white"
                                            >
                                                {{
                                                    isRTL
                                                        ? `الموسم بالكامل (${sGap.episode_count} حلقة)`
                                                        : `Entire Season (${sGap.episode_count} eps)`
                                                }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-slate-400">
                                            {{
                                                isRTL
                                                    ? 'تاريخ العرض:'
                                                    : 'Aired:'
                                            }}
                                            {{ sGap.air_date }}
                                        </div>
                                    </div>

                                    <button
                                        @click="
                                            openTorrentModal({
                                                ...sGap,
                                                type: 'season',
                                            })
                                        "
                                        class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl bg-purple-500 px-3.5 py-1.5 text-xs font-black text-white shadow-md shadow-purple-500/20 transition-all hover:bg-purple-400 active:scale-95"
                                    >
                                        <Compass class="h-3.5 w-3.5" />
                                        <span>{{
                                            isRTL
                                                ? 'حزمة الموسم'
                                                : 'Find Season Pack'
                                        }}</span>
                                    </button>
                                </div>

                                <!-- Missing Specific Episodes -->
                                <div
                                    v-for="ep in expandedSeries[sg.series_id]
                                        ? sg.missing_episodes
                                        : sg.missing_episodes.slice(0, 2)"
                                    :key="ep.id"
                                    class="flex items-center justify-between gap-3 rounded-2xl border border-white/5 bg-black/40 p-3 transition-all hover:border-cyan-500/30"
                                >
                                    <div class="min-w-0 space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="shrink-0 rounded-md border border-amber-500/30 bg-amber-500/20 px-2 py-0.5 text-[10px] font-black text-amber-300"
                                            >
                                                {{ ep.episode_code }}
                                            </span>
                                            <span
                                                class="truncate text-xs font-bold text-white"
                                            >
                                                {{
                                                    isRTL && ep.episode_title_ar
                                                        ? ep.episode_title_ar
                                                        : ep.episode_title
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="flex items-center gap-3 text-[11px] text-slate-400"
                                        >
                                            <span
                                                >{{
                                                    isRTL
                                                        ? 'تاريخ العرض:'
                                                        : 'Aired:'
                                                }}
                                                {{ ep.air_date }}</span
                                            >
                                            <span
                                                v-if="ep.rating"
                                                class="flex items-center gap-0.5 font-bold text-amber-400"
                                            >
                                                ★ {{ ep.rating }}
                                            </span>
                                        </div>
                                    </div>

                                    <button
                                        @click="
                                            openTorrentModal({
                                                ...ep,
                                                type: 'episode',
                                            })
                                        "
                                        class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl bg-amber-500 px-3.5 py-1.5 text-xs font-bold text-slate-950 shadow-md shadow-amber-500/20 transition-all hover:bg-amber-400 active:scale-95"
                                    >
                                        <Compass class="h-3.5 w-3.5" />
                                        <span>{{
                                            isRTL
                                                ? 'البحث عن الحلقة'
                                                : 'Find Episode'
                                        }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Series Empty State -->
                    <div
                        v-else
                        class="glass-panel space-y-2 rounded-3xl border border-white/10 px-4 py-12 text-center"
                    >
                        <CheckCircle2
                            class="mx-auto h-10 w-10 text-emerald-400"
                        />
                        <h3 class="text-base font-bold text-white">
                            {{
                                isRTL
                                    ? 'كافة مسلسلاتك مكتملة بنسبة 100%'
                                    : 'All TV Series are 100% Complete!'
                            }}
                        </h3>
                        <p class="text-xs text-slate-400">
                            {{
                                isRTL
                                    ? 'لا توجد أي حلقات ناقصة من المواسم المعروضة في مكتبتك.'
                                    : 'No missing released episodes detected across your library.'
                            }}
                        </p>
                    </div>
                </div>

                <!-- SECTION 2: FRANCHISE / MOVIE COLLECTIONS GAPS -->
                <div
                    v-if="activeTab === 'all' || activeTab === 'collections'"
                    class="space-y-4"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Film class="h-5 w-5 text-purple-400" />
                            <h2
                                class="text-xl font-black tracking-tight text-white"
                            >
                                {{
                                    isRTL
                                        ? 'سلاسل ومجموعات الأفلام غير المكتملة'
                                        : 'Incomplete Movie Franchises'
                                }}
                            </h2>
                        </div>
                        <span class="text-xs font-bold text-slate-400">
                            {{ collectionGaps.length }}
                            {{ isRTL ? 'سلاسل أفلام' : 'Franchises' }}
                        </span>
                    </div>

                    <!-- Collections Skeletons State -->
                    <div v-if="loading">
                        <ScoutFranchiseSkeleton :count="2" />
                    </div>

                    <!-- Collections Grid -->
                    <div
                        v-else-if="collectionGaps.length > 0"
                        class="grid grid-cols-1 gap-6 lg:grid-cols-2"
                    >
                        <div
                            v-for="cg in collectionGaps"
                            :key="cg.collection_id"
                            class="glass-panel flex flex-col justify-between space-y-5 overflow-hidden rounded-3xl border border-white/10 bg-slate-900/40 p-6 transition-all hover:border-purple-500/30"
                        >
                            <!-- Collection Header -->
                            <div class="flex items-start gap-4">
                                <img
                                    v-if="cg.poster_path"
                                    :src="cg.poster_path"
                                    :alt="cg.collection_name"
                                    class="h-28 w-20 shrink-0 rounded-2xl border border-white/10 object-cover shadow-lg"
                                    loading="lazy"
                                    @error="
                                        (e: any) =>
                                            (e.target.style.display = 'none')
                                    "
                                />
                                <div
                                    v-else
                                    class="flex h-28 w-20 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-slate-950 text-slate-600"
                                >
                                    <Film class="h-8 w-8" />
                                </div>

                                <div class="min-w-0 flex-1 space-y-2">
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <h3
                                            class="truncate text-lg font-black text-white"
                                        >
                                            {{ cg.collection_name }}
                                        </h3>
                                        <span
                                            class="shrink-0 rounded-full border border-purple-500/30 bg-purple-500/20 px-2.5 py-0.5 text-xs font-black text-purple-300"
                                        >
                                            {{ cg.completion_percent }}%
                                        </span>
                                    </div>

                                    <!-- Completion Bar -->
                                    <div class="space-y-1 pt-1">
                                        <div
                                            class="h-2 w-full overflow-hidden rounded-full border border-white/5 bg-slate-950"
                                        >
                                            <div
                                                class="h-full rounded-full bg-gradient-to-r from-purple-500 to-cyan-400 transition-all duration-500"
                                                :style="{
                                                    width: `${cg.completion_percent}%`,
                                                }"
                                            ></div>
                                        </div>
                                        <div
                                            class="flex items-center justify-between text-[11px] font-semibold text-slate-400"
                                        >
                                            <span
                                                >{{ cg.owned_count }}
                                                {{
                                                    isRTL
                                                        ? 'أجزاء متوفرة'
                                                        : 'Films Owned'
                                                }}</span
                                            >
                                            <span
                                                class="font-bold text-amber-400"
                                            >
                                                {{ cg.missing_count }}
                                                {{
                                                    isRTL
                                                        ? 'أجزاء مفقودة'
                                                        : 'Missing Parts'
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Saga Breakdown (Parts with In Library vs Missing Badges) -->
                            <div
                                class="space-y-2.5 border-t border-white/5 pt-2"
                            >
                                <div class="text-xs font-bold text-slate-300">
                                    {{
                                        isRTL
                                            ? 'أجزاء السلسلة (المتوفرة والناقصة):'
                                            : 'Franchise Films (Owned vs Missing):'
                                    }}
                                </div>

                                <div class="space-y-2">
                                    <div
                                        v-for="part in cg.parts"
                                        :key="part.id"
                                        class="flex items-center justify-between gap-3 rounded-2xl p-3 transition-all"
                                        :class="
                                            part.in_library
                                                ? 'border border-white/5 bg-white/[0.02]'
                                                : 'border border-amber-500/30 bg-amber-500/5 shadow-inner'
                                        "
                                    >
                                        <div
                                            class="flex min-w-0 items-center gap-3"
                                        >
                                            <img
                                                v-if="part.poster_path"
                                                :src="part.poster_path"
                                                :alt="part.movie_title"
                                                class="h-12 w-8 shrink-0 rounded-lg border border-white/10 object-cover"
                                                loading="lazy"
                                                @error="
                                                    (e: any) =>
                                                        (e.target.style.display =
                                                            'none')
                                                "
                                            />
                                            <div class="min-w-0 space-y-0.5">
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <h4
                                                        class="max-w-xs truncate text-xs font-bold text-white"
                                                    >
                                                        {{ part.movie_title }}
                                                    </h4>
                                                    <span
                                                        v-if="part.release_year"
                                                        class="text-[10px] font-semibold text-slate-400"
                                                    >
                                                        ({{
                                                            part.release_year
                                                        }})
                                                    </span>
                                                </div>

                                                <!-- Status Chip -->
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <span
                                                        v-if="part.in_library"
                                                        class="inline-flex items-center gap-1 rounded-md border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-400"
                                                    >
                                                        <CheckCircle2
                                                            class="h-3 w-3 text-emerald-400"
                                                        />
                                                        <span>{{
                                                            isRTL
                                                                ? 'متوفر بالمكتبة'
                                                                : 'In Library'
                                                        }}</span>
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="inline-flex items-center gap-1 rounded-md border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold text-amber-400"
                                                    >
                                                        <XCircle
                                                            class="h-3 w-3 text-amber-400"
                                                        />
                                                        <span>{{
                                                            isRTL
                                                                ? 'مفقود'
                                                                : 'Missing'
                                                        }}</span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Action if missing -->
                                        <button
                                            v-if="!part.in_library"
                                            @click="
                                                openTorrentModal({
                                                    ...part,
                                                    type: 'collection_movie',
                                                })
                                            "
                                            class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl bg-cyan-500 px-3 py-1.5 text-xs font-bold text-slate-950 shadow-md shadow-cyan-500/20 transition-all hover:bg-cyan-400 active:scale-95"
                                        >
                                            <Compass class="h-3.5 w-3.5" />
                                            <span>{{
                                                isRTL
                                                    ? 'البحث عن تورنت'
                                                    : 'Find Torrents'
                                            }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Collections Empty State -->
                    <div
                        v-else
                        class="glass-panel space-y-2 rounded-3xl border border-white/10 px-4 py-12 text-center"
                    >
                        <CheckCircle2
                            class="mx-auto h-10 w-10 text-emerald-400"
                        />
                        <h3 class="text-base font-bold text-white">
                            {{
                                isRTL
                                    ? 'كافة سلاسل الأفلام مكتملة تماماً!'
                                    : 'All Movie Franchises are 100% Complete!'
                            }}
                        </h3>
                        <p class="text-xs text-slate-400">
                            {{
                                isRTL
                                    ? 'لا توجد أي أجزاء أفلام ناقصة من سلاسل بوكس سيت مكتبتك.'
                                    : 'All parts of your movie sagas are present in your library.'
                            }}
                        </p>
                    </div>
                </div>

                <!-- SECTION 3: FLAT EPISODES VIEW -->
                <div v-if="activeTab === 'episodes'" class="space-y-4">
                    <h2
                        class="flex items-center gap-2 text-xl font-black tracking-tight text-white"
                    >
                        <Clock class="h-5 w-5 text-amber-400" />
                        <span>{{
                            isRTL
                                ? 'كافة الحلقات الناقصة'
                                : 'All Missing Episodes'
                        }}</span>
                    </h2>

                    <div
                        v-if="flatEpisodes.length > 0"
                        class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="ep in flatEpisodes"
                            :key="ep.id"
                            class="glass-panel flex items-center justify-between gap-4 rounded-2xl border border-white/5 bg-slate-900/40 p-4 transition-all hover:border-cyan-500/30"
                        >
                            <div class="flex min-w-0 items-center gap-3.5">
                                <img
                                    v-if="ep.series_poster || ep.still_path"
                                    :src="ep.series_poster || ep.still_path"
                                    :alt="ep.series_title"
                                    class="h-16 w-11 shrink-0 rounded-xl border border-white/10 object-cover shadow-md"
                                    loading="lazy"
                                    @error="
                                        (e: any) =>
                                            (e.target.style.display = 'none')
                                    "
                                />
                                <div
                                    v-else
                                    class="flex h-16 w-11 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-slate-950 text-slate-600"
                                >
                                    <Tv class="h-5 w-5" />
                                </div>
                                <div class="min-w-0 space-y-1">
                                    <span
                                        class="rounded-md border border-amber-500/30 bg-amber-500/20 px-2 py-0.5 text-[10px] font-black text-amber-300"
                                    >
                                        {{ ep.episode_code }}
                                    </span>
                                    <h4
                                        class="truncate text-sm font-bold text-white"
                                    >
                                        {{ ep.series_title }}
                                    </h4>
                                    <p class="truncate text-xs text-slate-400">
                                        {{
                                            isRTL && ep.episode_title_ar
                                                ? ep.episode_title_ar
                                                : ep.episode_title
                                        }}
                                    </p>
                                    <span
                                        class="block text-[11px] text-slate-500"
                                    >
                                        {{ isRTL ? 'العرض:' : 'Aired:' }}
                                        {{ ep.air_date }}
                                    </span>
                                </div>
                            </div>

                            <button
                                @click="
                                    openTorrentModal({ ...ep, type: 'episode' })
                                "
                                class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl bg-amber-500 px-3.5 py-1.5 text-xs font-bold text-slate-950 shadow-md shadow-amber-500/20 transition-all hover:bg-amber-400 active:scale-95"
                            >
                                <Compass class="h-3.5 w-3.5" />
                                <span>{{ isRTL ? 'تورنت' : 'Torrents' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: FLAT MOVIES VIEW -->
                <div v-if="activeTab === 'movies'" class="space-y-4">
                    <h2
                        class="flex items-center gap-2 text-xl font-black tracking-tight text-white"
                    >
                        <Award class="h-5 w-5 text-purple-400" />
                        <span>{{
                            isRTL
                                ? 'كافة أفلام السلاسل الناقصة'
                                : 'All Missing Franchise Movies'
                        }}</span>
                    </h2>

                    <div
                        v-if="flatMovies.length > 0"
                        class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="m in flatMovies"
                            :key="m.id"
                            class="glass-panel flex items-center justify-between gap-4 rounded-2xl border border-white/5 bg-slate-900/40 p-4 transition-all hover:border-purple-500/30"
                        >
                            <div class="flex min-w-0 items-center gap-3.5">
                                <img
                                    v-if="m.poster_path || m.collection_poster"
                                    :src="m.poster_path || m.collection_poster"
                                    :alt="m.movie_title"
                                    class="h-16 w-11 shrink-0 rounded-xl border border-white/10 object-cover shadow-md"
                                    loading="lazy"
                                    @error="
                                        (e: any) =>
                                            (e.target.style.display = 'none')
                                    "
                                />
                                <div
                                    v-else
                                    class="flex h-16 w-11 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-slate-950 text-slate-600"
                                >
                                    <Film class="h-5 w-5" />
                                </div>
                                <div class="min-w-0 space-y-1">
                                    <span
                                        class="inline-block max-w-[200px] truncate rounded-md border border-purple-500/30 bg-purple-500/20 px-2 py-0.5 text-[10px] font-black text-purple-300"
                                    >
                                        {{ m.collection_name }}
                                    </span>
                                    <h4
                                        class="truncate text-sm font-bold text-white"
                                    >
                                        {{ m.movie_title }}
                                    </h4>
                                    <span
                                        class="block text-[11px] text-slate-400"
                                    >
                                        {{ m.release_year }}
                                    </span>
                                </div>
                            </div>

                            <button
                                @click="
                                    openTorrentModal({
                                        ...m,
                                        type: 'collection_movie',
                                    })
                                "
                                class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl bg-cyan-500 px-3.5 py-1.5 text-xs font-bold text-slate-950 shadow-md shadow-cyan-500/20 transition-all hover:bg-cyan-400 active:scale-95"
                            >
                                <Compass class="h-3.5 w-3.5" />
                                <span>{{ isRTL ? 'تورنت' : 'Torrents' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SECTION 5 & 6: QUALITY UPGRADES WORKSPACE (FORENSIC ALERTS FIRST, THEN LOW-RES) -->
                <div
                    v-if="
                        activeTab === 'all_upgrades' ||
                        activeTab === 'poor_quality'
                    "
                    class="space-y-6"
                >
                    <!-- Forensic CAM / HC Header Card -->
                    <div
                        class="glass-panel space-y-4 rounded-3xl border border-rose-500/30 bg-gradient-to-r from-rose-950/40 via-slate-900/60 to-slate-900/80 p-6"
                    >
                        <div
                            class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center"
                        >
                            <div class="flex items-start gap-4">
                                <div
                                    class="shrink-0 rounded-2xl border border-rose-500/30 bg-rose-500/20 p-3.5 text-rose-400"
                                >
                                    <ShieldAlert class="h-6 w-6" />
                                </div>
                                <div class="space-y-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h2
                                            class="text-xl font-black tracking-tight text-white"
                                        >
                                            {{
                                                isRTL
                                                    ? 'تنبيهات الجودة الجنائية: نسخ CAM والترجمات المدمجة (HC)'
                                                    : 'Forensic Alerts: CAM & Hardcoded Subs (HC)'
                                            }}
                                        </h2>
                                        <span
                                            class="rounded-full border border-rose-500/30 bg-rose-500/20 px-2.5 py-0.5 text-xs font-black text-rose-300"
                                        >
                                            {{
                                                filteredPoorQualityItems.length
                                            }}
                                            {{
                                                isRTL
                                                    ? 'نسخة رديئة مرصودة'
                                                    : 'flagged releases'
                                            }}
                                        </span>
                                    </div>
                                    <p
                                        class="max-w-3xl text-xs leading-relaxed text-slate-300"
                                    >
                                        {{
                                            isRTL
                                                ? 'نظام تدقيق جنائي عميق يرصد التسجيلات السينمائية (CAM / Telesync) والنسخ التي تحتوي على ترجمات أجنبية مطبوعة داخل الفيديو (HC / Korean / Chinese / Arabic) أو شعارات مواقع القرصنة وضغط البث المنخفض وصوت Mono، حتى لو أعيدت تسمية الملفات. استبدلها بنقرة واحدة بنسخ 1080p/4K نقية.'
                                                : 'Multi-layer forensic engine detecting cinema recordings (CAM/TS), burned-in foreign subtitles (KORSUB, HC, watermarks), anomalous mono audio, and severe bitrate starvation—even on renamed library files. Use 1-click replacement for pristine WEB-DL/BluRay copies.'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <button
                                @click="fetchUpgrades(true)"
                                :disabled="upgradesLoading"
                                class="flex shrink-0 cursor-pointer items-center gap-2 rounded-xl border border-rose-500/40 bg-rose-500/20 px-4 py-2 text-xs font-bold text-rose-300 hover:bg-rose-500/30 disabled:opacity-50"
                            >
                                <RefreshCw
                                    class="h-3.5 w-3.5"
                                    :class="{ 'animate-spin': upgradesLoading }"
                                />
                                <span>{{
                                    isRTL
                                        ? 'إعادة الفحص الجنائي'
                                        : 'Re-run Forensics'
                                }}</span>
                            </button>
                        </div>

                        <!-- Filter Sub-bar -->
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-3"
                        >
                            <!-- Issue Category Filter -->
                            <div
                                class="flex flex-wrap items-center gap-1 rounded-xl border border-white/10 bg-black/40 p-1"
                            >
                                <button
                                    @click="poorQualityCategory = 'all'"
                                    :class="[
                                        'cursor-pointer rounded-lg px-3 py-1 text-xs font-bold transition-all',
                                        poorQualityCategory === 'all'
                                            ? 'bg-rose-500 font-black text-white shadow'
                                            : 'text-slate-400 hover:text-white',
                                    ]"
                                >
                                    {{ isRTL ? 'الكل' : 'All Issues' }} ({{
                                        poorQualityData.total_count || 0
                                    }})
                                </button>
                                <button
                                    @click="
                                        poorQualityCategory = 'hardcoded_subs'
                                    "
                                    :class="[
                                        'cursor-pointer rounded-lg px-3 py-1 text-xs font-bold transition-all',
                                        poorQualityCategory === 'hardcoded_subs'
                                            ? 'bg-rose-500 font-black text-white shadow'
                                            : 'text-slate-400 hover:text-white',
                                    ]"
                                >
                                    <Subtitles
                                        class="mr-1 inline h-3.5 w-3.5"
                                    />
                                    {{
                                        isRTL
                                            ? 'ترجمات مدمجة (HC)'
                                            : 'Hardcoded Subs (HC)'
                                    }}
                                    ({{
                                        poorQualityData.by_category
                                            ?.hardcoded_subs || 0
                                    }})
                                </button>
                                <button
                                    @click="
                                        poorQualityCategory = 'cam_recorded'
                                    "
                                    :class="[
                                        'cursor-pointer rounded-lg px-3 py-1 text-xs font-bold transition-all',
                                        poorQualityCategory === 'cam_recorded'
                                            ? 'bg-rose-500 font-black text-white shadow'
                                            : 'text-slate-400 hover:text-white',
                                    ]"
                                >
                                    🎥
                                    {{
                                        isRTL
                                            ? 'تسجيلات سينما (CAM)'
                                            : 'CAM Recordings'
                                    }}
                                    ({{
                                        poorQualityData.by_category
                                            ?.cam_recorded || 0
                                    }})
                                </button>
                                <button
                                    @click="
                                        poorQualityCategory =
                                            'watermarked_or_low_audio'
                                    "
                                    :class="[
                                        'cursor-pointer rounded-lg px-3 py-1 text-xs font-bold transition-all',
                                        poorQualityCategory ===
                                        'watermarked_or_low_audio'
                                            ? 'bg-rose-500 font-black text-white shadow'
                                            : 'text-slate-400 hover:text-white',
                                    ]"
                                >
                                    <Volume2 class="mr-1 inline h-3.5 w-3.5" />
                                    {{
                                        isRTL
                                            ? 'شعارات / بيت-ريت / مونو'
                                            : 'Watermark / Bitrate / Mono'
                                    }}
                                    ({{
                                        poorQualityData.by_category
                                            ?.watermarked_or_low_audio || 0
                                    }})
                                </button>
                            </div>

                            <!-- Media Type Filter -->
                            <div
                                class="flex items-center gap-1 rounded-xl border border-white/10 bg-black/40 p-1"
                            >
                                <button
                                    @click="poorQualityMediaType = 'all'"
                                    :class="[
                                        'cursor-pointer rounded-lg px-3 py-1 text-xs font-bold transition-all',
                                        poorQualityMediaType === 'all'
                                            ? 'border border-rose-500/40 bg-rose-500/30 font-bold text-rose-300'
                                            : 'text-slate-400 hover:text-white',
                                    ]"
                                >
                                    {{ isRTL ? 'الكل' : 'All' }}
                                </button>
                                <button
                                    @click="poorQualityMediaType = 'movie'"
                                    :class="[
                                        'cursor-pointer rounded-lg px-3 py-1 text-xs font-bold transition-all',
                                        poorQualityMediaType === 'movie'
                                            ? 'border border-rose-500/40 bg-rose-500/30 font-bold text-rose-300'
                                            : 'text-slate-400 hover:text-white',
                                    ]"
                                >
                                    🎬 {{ isRTL ? 'أفلام' : 'Movies' }}
                                </button>
                                <button
                                    @click="poorQualityMediaType = 'episode'"
                                    :class="[
                                        'cursor-pointer rounded-lg px-3 py-1 text-xs font-bold transition-all',
                                        poorQualityMediaType === 'episode'
                                            ? 'border border-rose-500/40 bg-rose-500/30 font-bold text-rose-300'
                                            : 'text-slate-400 hover:text-white',
                                    ]"
                                >
                                    📺 {{ isRTL ? 'مسلسلات' : 'Series' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Forensic Items Grid -->
                    <div
                        v-if="filteredPoorQualityItems.length > 0"
                        class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="it in filteredPoorQualityItems"
                            :key="it.id"
                            class="glass-panel group flex flex-col justify-between gap-4 rounded-3xl border border-rose-500/25 bg-slate-900/60 p-5 transition-all hover:border-rose-500/60 hover:shadow-xl hover:shadow-rose-500/10"
                        >
                            <div class="space-y-3">
                                <!-- Top Row: Issue Badges -->
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <span
                                        class="flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[10px] font-black tracking-wider uppercase"
                                        :class="
                                            it.flag_category === 'cam_recorded'
                                                ? 'border-red-500/30 bg-red-500/20 text-red-300'
                                                : it.flag_category ===
                                                    'hardcoded_subs'
                                                  ? 'border-amber-500/30 bg-amber-500/20 text-amber-300'
                                                  : 'border-rose-500/30 bg-rose-500/20 text-rose-300'
                                        "
                                    >
                                        <AlertCircle class="h-3 w-3" />
                                        <span>{{
                                            isRTL && it.badge_ar
                                                ? it.badge_ar
                                                : it.badge_en
                                        }}</span>
                                    </span>

                                    <span
                                        class="rounded-md px-2 py-0.5 text-[9px] font-black uppercase"
                                        :class="
                                            it.severity === 'high'
                                                ? 'border border-red-500/30 bg-red-500/20 text-red-400'
                                                : 'border border-amber-500/30 bg-amber-500/20 text-amber-400'
                                        "
                                    >
                                        {{ it.severity }}
                                        {{ isRTL ? 'أهمية' : 'priority' }}
                                    </span>
                                </div>

                                <!-- Media Info Row -->
                                <div class="flex items-start gap-3.5">
                                    <div class="relative shrink-0">
                                        <img
                                            v-if="it.poster_path"
                                            :src="it.poster_path"
                                            :alt="it.title"
                                            class="h-20 w-14 rounded-xl border border-white/10 object-cover shadow-md"
                                            loading="lazy"
                                            @error="
                                                (e: any) =>
                                                    (e.target.style.display =
                                                        'none')
                                            "
                                        />
                                        <div
                                            v-else
                                            class="flex h-20 w-14 items-center justify-center rounded-xl border border-white/10 bg-slate-950 text-slate-600"
                                        >
                                            <Film
                                                v-if="it.type === 'movie'"
                                                class="h-6 w-6"
                                            />
                                            <Tv v-else class="h-6 w-6" />
                                        </div>
                                        <span
                                            v-if="it.current_resolution"
                                            class="py-0.2 absolute -right-1.5 -bottom-1.5 rounded border border-white/10 bg-rose-600 px-1.5 text-[9px] font-black text-white shadow"
                                        >
                                            {{ it.current_resolution }}
                                        </span>
                                    </div>

                                    <div class="min-w-0 flex-1 space-y-1">
                                        <h4
                                            class="truncate text-sm font-black text-white"
                                            :title="it.title"
                                        >
                                            {{ it.title }}
                                        </h4>
                                        <p
                                            v-if="
                                                it.series_title &&
                                                it.type === 'episode'
                                            "
                                            class="truncate text-xs font-semibold text-purple-300"
                                        >
                                            {{ it.series_title }}
                                        </p>
                                        <div
                                            class="flex flex-wrap items-center gap-1.5 text-[11px] text-slate-400"
                                        >
                                            <span v-if="it.release_year">{{
                                                it.release_year
                                            }}</span>
                                            <span v-if="it.release_year"
                                                >•</span
                                            >
                                            <span
                                                class="font-semibold text-rose-300"
                                                >{{
                                                    it.current_resolution
                                                }}</span
                                            >
                                            <span v-if="it.file_size_human"
                                                >•</span
                                            >
                                            <span v-if="it.file_size_human">{{
                                                it.file_size_human
                                            }}</span>
                                            <span
                                                v-if="
                                                    it.video_codec &&
                                                    it.video_codec !== 'Unknown'
                                                "
                                                class="py-0.2 rounded border border-white/10 bg-white/5 px-1.5 font-mono text-[10px] text-slate-300"
                                            >
                                                {{ it.video_codec }}
                                            </span>
                                        </div>
                                        <p
                                            v-if="it.file_path"
                                            class="truncate font-mono text-[10px] text-slate-500"
                                            :title="it.file_path"
                                        >
                                            {{ it.file_path }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Forensic Evidence Box -->
                                <div
                                    class="space-y-1 rounded-xl border border-white/10 bg-black/50 p-2.5 font-mono text-[11px]"
                                >
                                    <div
                                        class="flex items-center gap-1 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                    >
                                        <Eye class="h-3 w-3 text-rose-400" />
                                        <span>{{
                                            isRTL
                                                ? 'دليل الفحص والتحليل:'
                                                : 'Forensic Evidence:'
                                        }}</span>
                                    </div>
                                    <p
                                        class="text-xs leading-snug break-words text-slate-200"
                                    >
                                        {{ it.evidence }}
                                    </p>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <button
                                @click="
                                    openUpgradeTorrentModal(it, 'poor_quality')
                                "
                                class="mt-1 flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-rose-500 to-red-600 py-2.5 text-xs font-black text-white shadow-lg shadow-rose-500/20 transition-all hover:from-rose-400 hover:to-red-500 active:scale-95"
                                :title="
                                    isRTL
                                        ? 'البحث عن نسخة نقية واستبعاد أي ترجمة مدمجة'
                                        : 'Find clean 1080p/4K WEB-DL or BluRay release'
                                "
                            >
                                <ShieldCheck class="h-4 w-4 text-white" />
                                <span>{{
                                    isRTL
                                        ? 'استبدال بنسخة نقية (WEB-DL / BluRay)'
                                        : 'Find Clean Replacement'
                                }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Empty State (Only in dedicated poor_quality tab or when all_upgrades has nothing) -->
                    <div
                        v-else-if="activeTab === 'poor_quality'"
                        class="glass-panel space-y-3 rounded-3xl border border-emerald-500/20 bg-emerald-950/10 px-4 py-16 text-center"
                    >
                        <ShieldCheck
                            class="mx-auto h-12 w-12 text-emerald-400"
                        />
                        <h3 class="text-lg font-black text-white">
                            {{
                                isRTL
                                    ? 'مكتبتك نظيفة وخالية من نسخ CAM والترجمات المدمجة!'
                                    : 'Pristine Library! No CAM or Hardcoded Subs Detected'
                            }}
                        </h3>
                        <p class="mx-auto max-w-md text-xs text-slate-400">
                            {{
                                isRTL
                                    ? 'تم فحص وسائط مكتبتك المنظمة، ولم يتم العثور على أي تسجيلات سينمائية رديئة أو ترجمات كورية/صينية مطبوعة.'
                                    : 'All audited files in your organized media folders are confirmed clean with proper audio tracks and no burned-in foreign subtitles.'
                            }}
                        </p>
                    </div>
                </div>

                <!-- LOW RESOLUTION (<720p) UPGRADES -->
                <div
                    v-if="
                        activeTab === 'all_upgrades' ||
                        activeTab === 'low_resolution'
                    "
                    class="space-y-6"
                >
                    <!-- Header Card -->
                    <div
                        class="glass-panel space-y-4 rounded-3xl border border-amber-500/30 bg-gradient-to-r from-amber-950/40 via-slate-900/60 to-slate-900/80 p-6"
                    >
                        <div
                            class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center"
                        >
                            <div class="flex items-start gap-4">
                                <div
                                    class="shrink-0 rounded-2xl border border-amber-500/30 bg-amber-500/20 p-3.5 text-amber-400"
                                >
                                    <ArrowDownUp class="h-6 w-6" />
                                </div>
                                <div class="space-y-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h2
                                            class="text-xl font-black tracking-tight text-white"
                                        >
                                            {{
                                                isRTL
                                                    ? 'ترقية الجودات الضعيفة (أقل من 720p)'
                                                    : 'Low Resolution Upgrades (<720p)'
                                            }}
                                        </h2>
                                        <span
                                            class="rounded-full border border-amber-500/30 bg-amber-500/20 px-2.5 py-0.5 text-xs font-black text-amber-300"
                                        >
                                            {{
                                                filteredLowResMovies.length +
                                                filteredLowResEpisodes.length
                                            }}
                                            {{
                                                isRTL
                                                    ? 'عنصر بحاجة للترقية'
                                                    : 'items flagged'
                                            }}
                                        </span>
                                    </div>
                                    <p
                                        class="max-w-3xl text-xs leading-relaxed text-slate-300"
                                    >
                                        {{
                                            isRTL
                                                ? 'يعرض فقط الملفات القديمة أو الضعيفة في مكتبتك ذات الدقة الأقل من 720p (مثل 480p SD، 360p، 576p SD). جودات 720p و 1080p و 4K مستثناة تماماً. يمكنك ترقية المسلسلات بالكامل أو الحلقات فردياً بنقرة واحدة.'
                                                : 'Strictly audits media below 720p (480p SD, 360p, 576p SD, Unknown) using both probed height and filename tags. Standard 720p/1080p/4K files are excluded. Upgrade full seasons or individual items in one click.'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <button
                                @click="fetchUpgrades(true)"
                                :disabled="upgradesLoading"
                                class="flex shrink-0 cursor-pointer items-center gap-2 rounded-xl border border-amber-500/40 bg-amber-500/20 px-4 py-2 text-xs font-bold text-amber-300 hover:bg-amber-500/30 disabled:opacity-50"
                            >
                                <RefreshCw
                                    class="h-3.5 w-3.5"
                                    :class="{ 'animate-spin': upgradesLoading }"
                                />
                                <span>{{
                                    isRTL
                                        ? 'إعادة فحص الدقة'
                                        : 'Re-scan Resolution'
                                }}</span>
                            </button>
                        </div>

                        <!-- Filter Sub-bar -->
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-3"
                        >
                            <!-- Media Type Filter -->
                            <div
                                class="flex items-center gap-1 rounded-xl border border-white/10 bg-black/40 p-1"
                            >
                                <button
                                    @click="lowResMediaType = 'all'"
                                    :class="[
                                        'cursor-pointer rounded-lg px-3 py-1 text-xs font-bold transition-all',
                                        lowResMediaType === 'all'
                                            ? 'bg-amber-500 font-black text-slate-950 shadow'
                                            : 'text-slate-400 hover:text-white',
                                    ]"
                                >
                                    {{ isRTL ? 'الكل' : 'All Media' }} ({{
                                        lowResData.total_count || 0
                                    }})
                                </button>
                                <button
                                    @click="lowResMediaType = 'movie'"
                                    :class="[
                                        'cursor-pointer rounded-lg px-3 py-1 text-xs font-bold transition-all',
                                        lowResMediaType === 'movie'
                                            ? 'bg-amber-500 font-black text-slate-950 shadow'
                                            : 'text-slate-400 hover:text-white',
                                    ]"
                                >
                                    🎬 {{ isRTL ? 'أفلام' : 'Movies' }} ({{
                                        lowResData.movies?.length || 0
                                    }})
                                </button>
                                <button
                                    @click="lowResMediaType = 'episode'"
                                    :class="[
                                        'cursor-pointer rounded-lg px-3 py-1 text-xs font-bold transition-all',
                                        lowResMediaType === 'episode'
                                            ? 'bg-amber-500 font-black text-slate-950 shadow'
                                            : 'text-slate-400 hover:text-white',
                                    ]"
                                >
                                    📺 {{ isRTL ? 'حلقات' : 'Episodes' }} ({{
                                        lowResData.episodes?.length || 0
                                    }})
                                </button>
                            </div>

                            <!-- Resolution Filter -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span
                                    class="text-xs font-semibold text-slate-400"
                                    >{{
                                        isRTL ? 'الدقة الحالية:' : 'Filter Res:'
                                    }}</span
                                >
                                <button
                                    v-for="r in [
                                        'all',
                                        '480p',
                                        '576p',
                                        '360p',
                                        'SD',
                                        'Unknown',
                                    ]"
                                    :key="r"
                                    @click="lowResResolution = r"
                                    :class="[
                                        'cursor-pointer rounded-lg border px-2.5 py-1 text-xs font-bold transition-all',
                                        lowResResolution === r
                                            ? 'border-amber-400 bg-amber-500/30 text-amber-300'
                                            : 'border-white/10 bg-white/5 text-slate-400 hover:text-white',
                                    ]"
                                >
                                    {{
                                        r === 'all'
                                            ? isRTL
                                                ? 'كافة الدقات'
                                                : 'All Sub-720p'
                                            : r
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Movies Grid -->
                    <div
                        v-if="
                            (lowResMediaType === 'all' ||
                                lowResMediaType === 'movie') &&
                            filteredLowResMovies.length > 0
                        "
                        class="space-y-3"
                    >
                        <h3
                            class="flex items-center gap-2 text-sm font-black tracking-wider text-amber-400 uppercase"
                        >
                            <Film class="h-4 w-4" />
                            <span>{{
                                isRTL
                                    ? 'أفلام مكتبتك بدقة أقل من 720p'
                                    : 'Library Movies Below 720p'
                            }}</span>
                            <span
                                class="rounded-full border border-amber-500/30 bg-amber-500/20 px-2 py-0.5 text-[10px] text-amber-300"
                                >{{ filteredLowResMovies.length }}</span
                            >
                        </h3>

                        <div
                            class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                        >
                            <div
                                v-for="m in filteredLowResMovies"
                                :key="m.id"
                                class="glass-panel group flex items-center justify-between gap-4 rounded-2xl border border-amber-500/20 bg-slate-900/50 p-4 transition-all hover:border-amber-500/50 hover:shadow-lg hover:shadow-amber-500/5"
                            >
                                <div
                                    class="flex min-w-0 flex-1 items-center gap-3.5"
                                >
                                    <div class="relative shrink-0">
                                        <img
                                            v-if="m.poster_path"
                                            :src="m.poster_path"
                                            :alt="m.title"
                                            class="h-18 w-12 rounded-xl border border-white/10 object-cover shadow-md"
                                            loading="lazy"
                                            @error="
                                                (e: any) =>
                                                    (e.target.style.display =
                                                        'none')
                                            "
                                        />
                                        <div
                                            v-else
                                            class="flex h-18 w-12 items-center justify-center rounded-xl border border-white/10 bg-slate-950 text-slate-600"
                                        >
                                            <Film class="h-6 w-6" />
                                        </div>
                                        <span
                                            class="py-0.2 absolute -right-1.5 -bottom-1.5 rounded bg-amber-500 px-1.5 text-[9px] font-black text-slate-950 shadow"
                                        >
                                            {{ m.current_resolution }}
                                        </span>
                                    </div>

                                    <div class="min-w-0 flex-1 space-y-1">
                                        <h4
                                            class="truncate text-sm font-bold text-white"
                                            :title="m.title"
                                        >
                                            {{
                                                isRTL && m.title_ar
                                                    ? m.title_ar
                                                    : m.title
                                            }}
                                        </h4>
                                        <div
                                            class="flex flex-wrap items-center gap-2 text-xs text-slate-400"
                                        >
                                            <span v-if="m.release_year">{{
                                                m.release_year
                                            }}</span>
                                            <span v-if="m.release_year">•</span>
                                            <span
                                                class="font-semibold text-amber-300"
                                                >{{ m.file_size_human }}</span
                                            >
                                            <span
                                                v-if="
                                                    m.video_codec &&
                                                    m.video_codec !== 'Unknown'
                                                "
                                                >•</span
                                            >
                                            <span
                                                v-if="
                                                    m.video_codec &&
                                                    m.video_codec !== 'Unknown'
                                                "
                                                class="font-mono text-[10px]"
                                                >{{ m.video_codec }}</span
                                            >
                                        </div>
                                        <p
                                            v-if="m.file_path"
                                            class="truncate font-mono text-[10px] text-slate-500"
                                            :title="m.file_path"
                                        >
                                            {{ m.file_path }}
                                        </p>
                                        <div
                                            class="flex items-center gap-1.5 pt-0.5"
                                        >
                                            <span
                                                class="rounded border border-cyan-500/30 bg-cyan-500/15 px-1.5 py-0.5 text-[10px] font-bold text-cyan-300"
                                            >
                                                {{
                                                    isRTL
                                                        ? 'الترقية المقترحة: 1080p BluRay'
                                                        : 'Target: 1080p BluRay'
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <button
                                    @click="
                                        openUpgradeTorrentModal(
                                            m,
                                            'low_resolution',
                                        )
                                    "
                                    class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 px-3.5 py-2 text-xs font-black text-slate-950 shadow-md shadow-amber-500/20 transition-all hover:from-amber-400 hover:to-amber-300 active:scale-95"
                                    :title="
                                        isRTL
                                            ? 'البحث عن ترقية تورنت نظيفة 1080p/4K'
                                            : 'Search 1080p/4K upgrade torrents'
                                    "
                                >
                                    <Compass class="h-3.5 w-3.5" />
                                    <span>{{
                                        isRTL ? 'ترقية 1080p' : 'Upgrade'
                                    }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Episodes Section (Grouped by Series vs Individual Paginated) -->
                    <div
                        v-if="
                            (lowResMediaType === 'all' ||
                                lowResMediaType === 'episode') &&
                            filteredLowResEpisodes.length > 0
                        "
                        class="space-y-4"
                    >
                        <div
                            class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
                        >
                            <h3
                                class="flex items-center gap-2 text-sm font-black tracking-wider text-amber-400 uppercase"
                            >
                                <Tv class="h-4 w-4" />
                                <span>{{
                                    isRTL
                                        ? 'حلقات المسلسلات بدقة أقل من 720p'
                                        : 'TV Episodes Below 720p'
                                }}</span>
                                <span
                                    class="rounded-full border border-amber-500/30 bg-amber-500/20 px-2 py-0.5 text-[10px] text-amber-300"
                                    >{{ filteredLowResEpisodes.length }}</span
                                >
                            </h3>

                            <!-- Grouped vs Individual View Toggle -->
                            <div
                                class="flex items-center gap-1 self-start rounded-xl border border-white/10 bg-black/40 p-1 sm:self-auto"
                            >
                                <button
                                    @click="lowResEpisodeView = 'grouped'"
                                    :class="[
                                        'flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1 text-xs font-bold transition-all',
                                        lowResEpisodeView === 'grouped'
                                            ? 'bg-amber-500 font-black text-slate-950 shadow'
                                            : 'text-slate-400 hover:text-white',
                                    ]"
                                >
                                    <Layers class="h-3.5 w-3.5" />
                                    <span
                                        >{{
                                            isRTL
                                                ? 'تجميع حسب المسلسل'
                                                : 'Grouped by Series'
                                        }}
                                        ({{
                                            filteredLowResSeriesGroups.length
                                        }})</span
                                    >
                                </button>
                                <button
                                    @click="lowResEpisodeView = 'individual'"
                                    :class="[
                                        'flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1 text-xs font-bold transition-all',
                                        lowResEpisodeView === 'individual'
                                            ? 'bg-amber-500 font-black text-slate-950 shadow'
                                            : 'text-slate-400 hover:text-white',
                                    ]"
                                >
                                    <Tv class="h-3.5 w-3.5" />
                                    <span>{{
                                        isRTL
                                            ? 'حلقات منفردة'
                                            : 'Individual Episodes'
                                    }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Grouped by Series Cards -->
                        <div
                            v-if="lowResEpisodeView === 'grouped'"
                            class="grid grid-cols-1 gap-4 md:grid-cols-2"
                        >
                            <div
                                v-for="group in filteredLowResSeriesGroups"
                                :key="group.id"
                                class="glass-panel overflow-hidden rounded-3xl border border-amber-500/25 bg-slate-900/60 transition-all hover:border-amber-500/50"
                            >
                                <div
                                    class="flex items-center justify-between gap-4 p-4"
                                >
                                    <div
                                        class="flex min-w-0 flex-1 items-center gap-3.5"
                                    >
                                        <div class="relative shrink-0">
                                            <img
                                                v-if="group.poster_path"
                                                :src="group.poster_path"
                                                :alt="group.series_title"
                                                class="h-18 w-12 rounded-xl border border-white/10 object-cover shadow-md"
                                                loading="lazy"
                                                @error="
                                                    (e: any) =>
                                                        (e.target.style.display =
                                                            'none')
                                                "
                                            />
                                            <div
                                                v-else
                                                class="flex h-18 w-12 items-center justify-center rounded-xl border border-white/10 bg-slate-950 text-slate-600"
                                            >
                                                <Tv class="h-6 w-6" />
                                            </div>
                                            <span
                                                class="py-0.2 absolute -right-1.5 -bottom-1.5 rounded bg-amber-500 px-1.5 text-[9px] font-black text-slate-950 shadow"
                                            >
                                                {{ group.worst_resolution }}
                                            </span>
                                        </div>

                                        <div class="min-w-0 flex-1 space-y-1">
                                            <h4
                                                class="truncate text-sm font-black text-white"
                                                :title="group.series_title"
                                            >
                                                {{ group.series_title }}
                                            </h4>
                                            <div
                                                class="flex flex-wrap items-center gap-2 text-xs text-slate-400"
                                            >
                                                <span
                                                    class="rounded-md border border-amber-500/30 bg-amber-500/20 px-2 py-0.5 text-[10px] font-black text-amber-300"
                                                >
                                                    {{ group.episode_count }}
                                                    {{
                                                        isRTL
                                                            ? 'حلقة ضعيفة'
                                                            : 'episodes'
                                                    }}
                                                </span>
                                                <span>•</span>
                                                <span
                                                    class="font-semibold text-slate-300"
                                                    >{{
                                                        formatBytesShort(
                                                            group.total_size,
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="flex shrink-0 items-center gap-2"
                                    >
                                        <button
                                            @click="
                                                openSeriesUpgradeTorrentModal(
                                                    group,
                                                )
                                            "
                                            class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 px-3 py-2 text-xs font-black text-slate-950 shadow-md shadow-amber-500/20 transition-all hover:from-amber-400 hover:to-amber-300 active:scale-95"
                                            :title="
                                                isRTL
                                                    ? 'البحث عن موسم كامل 1080p/4K'
                                                    : 'Search 1080p/4K Season Pack'
                                            "
                                        >
                                            <Compass class="h-3.5 w-3.5" />
                                            <span>{{
                                                isRTL
                                                    ? 'ترقية الموسم'
                                                    : 'Upgrade Pack'
                                            }}</span>
                                        </button>

                                        <button
                                            @click="
                                                toggleLowResSeries(group.id)
                                            "
                                            class="cursor-pointer rounded-xl border border-white/10 bg-white/5 p-2 text-slate-300 transition-all hover:bg-white/10"
                                            :title="
                                                isRTL
                                                    ? 'عرض/إخفاء الحلقات'
                                                    : 'Toggle episodes'
                                            "
                                        >
                                            <ChevronUp
                                                v-if="
                                                    expandedLowResSeries[
                                                        group.id
                                                    ]
                                                "
                                                class="h-4 w-4"
                                            />
                                            <ChevronDown
                                                v-else
                                                class="h-4 w-4"
                                            />
                                        </button>
                                    </div>
                                </div>

                                <!-- Expandable Episodes List -->
                                <div
                                    v-if="expandedLowResSeries[group.id]"
                                    class="max-h-72 divide-y divide-white/5 overflow-y-auto border-t border-white/10 bg-black/30"
                                >
                                    <div
                                        v-for="ep in group.episodes"
                                        :key="ep.id"
                                        class="flex items-center justify-between gap-3 px-4 py-2.5 transition-colors hover:bg-white/5"
                                    >
                                        <div
                                            class="flex min-w-0 flex-1 items-center gap-2.5"
                                        >
                                            <span
                                                class="shrink-0 rounded border border-purple-500/30 bg-purple-500/20 px-1.5 py-0.5 text-[10px] font-black text-purple-300"
                                            >
                                                {{ ep.episode_code }}
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p
                                                    class="truncate text-xs font-semibold text-slate-200"
                                                    :title="ep.episode_title"
                                                >
                                                    {{ ep.episode_title }}
                                                </p>
                                                <p
                                                    class="truncate font-mono text-[10px] text-slate-400"
                                                    :title="ep.file_path"
                                                >
                                                    {{ ep.current_resolution }}
                                                    • {{ ep.file_size_human }}
                                                    <span
                                                        v-if="
                                                            ep.video_codec &&
                                                            ep.video_codec !==
                                                                'Unknown'
                                                        "
                                                        >•
                                                        {{
                                                            ep.video_codec
                                                        }}</span
                                                    >
                                                </p>
                                            </div>
                                        </div>

                                        <button
                                            @click="
                                                openUpgradeTorrentModal(
                                                    ep,
                                                    'low_resolution',
                                                )
                                            "
                                            class="shrink-0 cursor-pointer rounded-lg border border-amber-500/30 bg-amber-500/20 px-2.5 py-1 text-[11px] font-black text-amber-300 transition-all hover:bg-amber-500/30"
                                        >
                                            {{ isRTL ? 'ترقية' : 'Upgrade' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Individual Episodes Grid (Capped + Show More) -->
                        <div v-else class="space-y-4">
                            <div
                                class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                            >
                                <div
                                    v-for="ep in visibleLowResEpisodes"
                                    :key="ep.id"
                                    class="glass-panel group flex items-center justify-between gap-4 rounded-2xl border border-amber-500/20 bg-slate-900/50 p-4 transition-all hover:border-amber-500/50 hover:shadow-lg hover:shadow-amber-500/5"
                                >
                                    <div
                                        class="flex min-w-0 flex-1 items-center gap-3.5"
                                    >
                                        <div class="relative shrink-0">
                                            <img
                                                v-if="
                                                    ep.poster_path ||
                                                    ep.still_path
                                                "
                                                :src="
                                                    ep.still_path ||
                                                    ep.poster_path
                                                "
                                                :alt="ep.title"
                                                class="h-18 w-12 rounded-xl border border-white/10 object-cover shadow-md"
                                                loading="lazy"
                                                @error="
                                                    (e: any) =>
                                                        (e.target.style.display =
                                                            'none')
                                                "
                                            />
                                            <div
                                                v-else
                                                class="flex h-18 w-12 items-center justify-center rounded-xl border border-white/10 bg-slate-950 text-slate-600"
                                            >
                                                <Tv class="h-6 w-6" />
                                            </div>
                                            <span
                                                class="py-0.2 absolute -right-1.5 -bottom-1.5 rounded bg-amber-500 px-1.5 text-[9px] font-black text-slate-950 shadow"
                                            >
                                                {{ ep.current_resolution }}
                                            </span>
                                        </div>

                                        <div class="min-w-0 flex-1 space-y-1">
                                            <div
                                                class="flex items-center gap-1.5"
                                            >
                                                <span
                                                    class="py-0.2 shrink-0 rounded border border-purple-500/30 bg-purple-500/20 px-1.5 text-[10px] font-black text-purple-300"
                                                >
                                                    {{ ep.episode_code }}
                                                </span>
                                                <span
                                                    class="truncate text-xs font-bold text-white"
                                                    :title="ep.series_title"
                                                >
                                                    {{ ep.series_title }}
                                                </span>
                                            </div>
                                            <h4
                                                class="truncate text-xs text-slate-300"
                                                :title="ep.episode_title"
                                            >
                                                {{ ep.episode_title }}
                                            </h4>
                                            <div
                                                class="flex items-center gap-2 text-xs text-slate-400"
                                            >
                                                <span
                                                    class="font-semibold text-amber-300"
                                                    >{{
                                                        ep.file_size_human
                                                    }}</span
                                                >
                                                <span
                                                    v-if="
                                                        ep.video_codec &&
                                                        ep.video_codec !==
                                                            'Unknown'
                                                    "
                                                    >•</span
                                                >
                                                <span
                                                    v-if="
                                                        ep.video_codec &&
                                                        ep.video_codec !==
                                                            'Unknown'
                                                    "
                                                    class="font-mono text-[10px]"
                                                    >{{ ep.video_codec }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <button
                                        @click="
                                            openUpgradeTorrentModal(
                                                ep,
                                                'low_resolution',
                                            )
                                        "
                                        class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 px-3.5 py-2 text-xs font-black text-slate-950 shadow-md shadow-amber-500/20 transition-all hover:from-amber-400 hover:to-amber-300 active:scale-95"
                                        :title="
                                            isRTL
                                                ? 'البحث عن حلقة 1080p نقية'
                                                : 'Search 1080p upgrade episode'
                                        "
                                    >
                                        <Compass class="h-3.5 w-3.5" />
                                        <span>{{
                                            isRTL ? 'ترقية' : 'Upgrade'
                                        }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Load More Button -->
                            <div
                                v-if="
                                    visibleLowResEpisodes.length <
                                    filteredLowResEpisodes.length
                                "
                                class="flex justify-center pt-2"
                            >
                                <button
                                    @click="individualEpisodesLimit += 60"
                                    class="flex cursor-pointer items-center gap-2 rounded-xl border border-amber-500/40 bg-amber-500/20 px-6 py-2.5 text-xs font-black text-amber-300 transition-all hover:bg-amber-500/30"
                                >
                                    <span>
                                        {{
                                            isRTL
                                                ? `عرض 60 حلقة إضافية (${visibleLowResEpisodes.length} من أصل ${filteredLowResEpisodes.length})`
                                                : `Load More Episodes (${visibleLowResEpisodes.length} of ${filteredLowResEpisodes.length})`
                                        }}
                                    </span>
                                    <ChevronDown class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State for Low Resolution -->
                    <div
                        v-if="
                            filteredLowResMovies.length === 0 &&
                            filteredLowResEpisodes.length === 0 &&
                            (activeTab === 'low_resolution' ||
                                filteredPoorQualityItems.length === 0)
                        "
                        class="glass-panel space-y-3 rounded-3xl border border-emerald-500/20 bg-emerald-950/10 px-4 py-16 text-center"
                    >
                        <CheckCircle2
                            class="mx-auto h-12 w-12 text-emerald-400"
                        />
                        <h3 class="text-lg font-black text-white">
                            {{
                                activeTab === 'all_upgrades'
                                    ? isRTL
                                        ? 'مكتبتك في قمة النقاء! لا توجد ترقيات أو تنبيهات'
                                        : 'Library Audit Clean! No Upgrades Needed'
                                    : isRTL
                                      ? 'رائع! لا توجد وسائط بدقة ضعيفة في مكتبتك'
                                      : 'All Media Meets HD Quality Standard!'
                            }}
                        </h3>
                        <p class="mx-auto max-w-md text-xs text-slate-400">
                            {{
                                isRTL
                                    ? 'كافة الأفلام والحلقات في مكتبتك بدقة 720p HD أو 1080p أو 4K وخالية من نسخ السينما والترجمات المدمجة.'
                                    : 'Every movie and episode in your library is 720p HD, 1080p Full HD, or 4K Ultra HD with clean audio and no burned-in foreign subtitles.'
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Series Seasons Inspector Modal (For Discover) -->
        <div
            v-if="isSeriesInspectorOpen"
            class="animate-fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4 backdrop-blur-md"
            :dir="isRTL ? 'rtl' : 'ltr'"
        >
            <div
                class="glass-panel relative flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-3xl border border-cyan-500/20 bg-slate-900/95 shadow-2xl"
            >
                <!-- Inspector Header -->
                <div
                    class="flex items-start justify-between gap-4 border-b border-white/10 p-6"
                >
                    <div class="flex items-center gap-3">
                        <img
                            v-if="inspectingSeries?.poster_path"
                            :src="inspectingSeries.poster_path"
                            :alt="inspectingSeries.title"
                            class="h-20 w-14 rounded-xl border border-white/10 object-cover shadow"
                        />
                        <div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded-md border border-cyan-500/30 bg-cyan-500/20 px-2 py-0.5 text-[10px] font-black text-cyan-300"
                                >
                                    {{
                                        isRTL
                                            ? 'مواسم المسلسل'
                                            : 'Series Seasons'
                                    }}
                                </span>
                                <span class="text-xs text-slate-400">{{
                                    inspectingSeries?.year
                                }}</span>
                            </div>
                            <h3 class="mt-1 text-lg font-black text-white">
                                {{ inspectingSeries?.title }}
                            </h3>
                        </div>
                    </div>

                    <button
                        @click="isSeriesInspectorOpen = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-white/10 hover:text-white"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Inspector Seasons List -->
                <div class="flex-1 space-y-3 overflow-y-auto p-6">
                    <!-- Season Row Skeletons (4 items) -->
                    <div v-if="inspectingLoading" class="space-y-3">
                        <div
                            v-for="s in 4"
                            :key="s"
                            class="flex animate-pulse items-center justify-between gap-4 rounded-2xl border border-white/5 bg-slate-950/40 p-4"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="h-10 w-10 rounded-xl bg-white/10"
                                ></div>
                                <div class="space-y-2">
                                    <div
                                        class="h-3.5 w-32 rounded bg-white/10"
                                    ></div>
                                    <div
                                        class="h-2.5 w-20 rounded bg-white/5"
                                    ></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div
                                    class="h-7 w-20 rounded-xl bg-white/10"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        v-for="s in inspectingSeasons"
                        :key="s.season_number"
                        class="glass-panel flex items-center justify-between gap-4 rounded-2xl border border-white/5 bg-slate-950/40 p-4 hover:border-cyan-500/30"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-xl border border-purple-500/30 bg-purple-500/20 text-sm font-black text-purple-300"
                            >
                                S{{ s.season_number }}
                            </span>
                            <div>
                                <h4 class="text-xs font-black text-white">
                                    {{ s.name }}
                                </h4>
                                <div
                                    class="mt-0.5 flex items-center gap-2 text-[11px] text-slate-400"
                                >
                                    <span
                                        >{{ s.episode_count }}
                                        {{ isRTL ? 'حلقة' : 'episodes' }}</span
                                    >
                                    <span>•</span>
                                    <span>{{ s.air_date }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span
                                v-if="s.in_library"
                                class="flex items-center gap-1 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-[10px] font-black text-emerald-400"
                            >
                                <CheckCircle2 class="h-3 w-3" />
                                <span>{{ isRTL ? 'متوفر' : 'Owned' }}</span>
                            </span>
                            <button
                                @click="
                                    openDiscoverTorrentModal(
                                        inspectingSeries,
                                        s.season_number,
                                    )
                                "
                                class="flex cursor-pointer items-center gap-1 rounded-xl bg-cyan-500 px-3 py-1.5 text-xs font-bold text-slate-950 shadow-md hover:bg-cyan-400"
                            >
                                <Compass class="h-3.5 w-3.5" />
                                <span>{{
                                    isRTL ? 'بحث تورنت' : 'Find Torrents'
                                }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <TorrentSuggestionsModal
            v-if="isTorrentModalOpen"
            :is-open="isTorrentModalOpen"
            :gap-item="selectedGap"
            @close="isTorrentModalOpen = false"
            @download-started="onDownloadStarted"
        />

        <FastOrganizeModal
            v-if="isFastOrganizeOpen"
            :is-open="isFastOrganizeOpen"
            @close="isFastOrganizeOpen = false"
            @organized="onFastOrganized"
        />
    </AppLayout>
</template>
