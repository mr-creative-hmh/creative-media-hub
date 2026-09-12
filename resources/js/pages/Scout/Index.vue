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
    CheckCircle2, XCircle, ChevronDown, ChevronUp
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
}>();

const { t, isRTL } = useI18n();

const metrics = ref<MetricData>(props.initialMetrics || {});
const seriesGaps = ref<any[]>(props.initialSeriesGaps || []);
const collectionGaps = ref<any[]>(props.initialCollectionGaps || []);
const flatEpisodes = ref<any[]>(props.initialEpisodes || []);
const flatSeasons = ref<any[]>(props.initialSeasons || []);
const flatMovies = ref<any[]>(props.initialMovies || []);

const activeTab = ref<'all' | 'series' | 'collections' | 'episodes' | 'movies'>('all');
const searchQuery = ref('');
const loading = ref(false);
const refreshing = ref(false);

// Expanded states for series accordions
const expandedSeries = ref<Record<number, boolean>>({});

// Modals
const selectedGap = ref<any | null>(null);
const isTorrentModalOpen = ref(false);
const isFastOrganizeOpen = ref(false);

let searchTimeout: any = null;

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

const openTorrentModal = (item: any) => {
    selectedGap.value = item;
    isTorrentModalOpen.value = true;
};

const onDownloadStarted = (payload: any) => {
    // Flag item as downloading locally if found
    if (payload?.gapItem?.id) {
        // Tag in flat lists
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

onMounted(() => {
    if (!props.initialSeriesGaps || props.initialSeriesGaps.length === 0) {
        fetchGaps();
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
                            <span>{{ isRTL ? 'مستكشف نواقص المكتبة' : 'Media Scout Radar' }}</span>
                        </div>
                        <h1 class="text-3xl lg:text-4xl font-black text-white tracking-tight">
                            {{ isRTL ? 'رادار اكتمال المكتبة ونواقص الوسائط' : 'Library Completion & Gap Radar' }}
                        </h1>
                        <p class="text-sm lg:text-base text-slate-300 leading-relaxed">
                            {{ isRTL 
                                ? 'يكتشف الحلقات والمواسم المفقودة من مسلسلاتك وأجزاء سلاسل الأفلام الناقصة بدقة، مع إمكانية البحث الفوري عن تورنت عالي الجودة والتنظيم التلقائي داخل H:\\Entertainment.' 
                                : 'Audits your library against TMDB to detect missing released episodes, unacquired seasons, and missing franchise sequels with 1-click torrent downloads and automated library organizing.' 
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
                            <span>{{ isRTL ? 'تنظيم وإضافة للمكتبة (فحص)' : 'Organize & Add to Library (Scan)' }}</span>
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
                                {{ isRTL ? 'أفلام سلاسل مفقودة' : 'Missing Franchise Films' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search Input Bar -->
                <div class="relative z-10 mt-6 max-w-xl">
                    <div class="relative">
                        <Search class="absolute top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-slate-400" :class="isRTL ? 'right-4' : 'left-4'" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="isRTL ? 'ابحث عن مسلسل، سلسلة أفلام، أو حلقة ناقصة...' : 'Search series, movie franchise, or missing episode...'"
                            class="w-full py-3 rounded-2xl bg-black/40 border border-white/15 text-sm text-white placeholder-slate-400 focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 transition-all shadow-inner"
                            :class="isRTL ? 'pr-11 pl-20' : 'pl-11 pr-20'"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute top-1/2 -translate-y-1/2 px-2.5 py-1 text-xs text-slate-400 hover:text-white transition-colors cursor-pointer"
                            :class="isRTL ? 'left-2' : 'right-2'"
                        >
                            {{ isRTL ? 'مسح' : 'Clear' }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- Navigation Tabs -->
            <div class="flex items-center gap-2 overflow-x-auto pb-2 border-b border-white/10">
                <button
                    @click="activeTab = 'all'"
                    :class="[
                        'px-4 py-2.5 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                        activeTab === 'all'
                            ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20'
                            : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                >
                    <Layers class="w-4 h-4" />
                    <span>{{ isRTL ? 'نظرة عامة على النواقص' : 'All Overview' }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-black/20">
                        {{ metrics.total_gaps_count || 0 }}
                    </span>
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

            <!-- Loading Spinner -->
            <div v-if="loading" class="py-16 text-center space-y-3">
                <Loader2 class="w-8 h-8 text-cyan-400 animate-spin mx-auto" />
                <p class="text-xs text-slate-400 font-medium">
                    {{ isRTL ? 'جارٍ تحميل وتحديث قائمة النواقص...' : 'Loading gap analysis...' }}
                </p>
            </div>

            <!-- MAIN CONTENT SECTIONS -->
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

                            <!-- Missing Episodes List -->
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

                                <!-- Missing Seasons if any -->
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
                                        @click="openTorrentModal(sGap)"
                                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-cyan-500 text-slate-950 hover:bg-cyan-400 shadow-md shadow-cyan-500/20 active:scale-95 transition-all flex items-center gap-1.5 cursor-pointer shrink-0"
                                    >
                                        <Compass class="w-3.5 h-3.5" />
                                        <span>{{ isRTL ? 'البحث عن تورنت' : 'Find Torrents' }}</span>
                                    </button>
                                </div>

                                <!-- Missing Episodes -->
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
                                        @click="openTorrentModal(ep)"
                                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-cyan-500 text-slate-950 hover:bg-cyan-400 shadow-md shadow-cyan-500/20 active:scale-95 transition-all flex items-center gap-1.5 cursor-pointer shrink-0"
                                    >
                                        <Compass class="w-3.5 h-3.5" />
                                        <span>{{ isRTL ? 'البحث عن تورنت' : 'Find Torrents' }}</span>
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

                <!-- SECTION 2: FRANCHISE / MOVIE COLLECTIONS GAPS (Grouped by Collection) -->
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

                    <!-- Collection Cards Grid -->
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

                                    <!-- Progress Bar -->
                                    <div class="space-y-1 pt-1">
                                        <div class="w-full h-2 rounded-full bg-slate-950 overflow-hidden border border-white/5">
                                            <div
                                                class="h-full rounded-full transition-all duration-500 bg-gradient-to-r from-purple-500 to-cyan-400"
                                                :style="{ width: `${cg.completion_percent}%` }"
                                            ></div>
                                        </div>
                                        <div class="flex items-center justify-between text-[11px] text-slate-400 font-semibold">
                                            <span class="text-emerald-400 font-bold">
                                                ✅ {{ cg.owned_count }} {{ isRTL ? 'أفلام متوفرة' : 'Films Owned' }}
                                            </span>
                                            <span class="text-amber-400 font-bold">
                                                ❌ {{ cg.missing_count }} {{ isRTL ? 'أفلام ناقصة' : 'Missing Films' }}
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
                                            @click="openTorrentModal(part)"
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

                <!-- SECTION 3: FLAT EPISODES VIEW (When on episodes tab) -->
                <div v-if="activeTab === 'episodes'" class="space-y-4">
                    <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
                        <Clock class="w-5 h-5 text-amber-400" />
                        <span>{{ isRTL ? 'كافة الحلقات الناقصة' : 'All Missing Episodes' }}</span>
                    </h2>

                    <div v-if="flatEpisodes.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="ep in flatEpisodes"
                            :key="ep.id"
                            class="p-4 rounded-2xl glass-panel border border-white/5 hover:border-cyan-500/30 bg-slate-900/40 transition-all flex items-start justify-between gap-4"
                        >
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
                                <span class="text-[11px] text-slate-500">
                                    {{ isRTL ? 'العرض:' : 'Aired:' }} {{ ep.air_date }}
                                </span>
                            </div>

                            <button
                                @click="openTorrentModal(ep)"
                                class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-cyan-500 text-slate-950 hover:bg-cyan-400 shadow-md shadow-cyan-500/20 active:scale-95 transition-all flex items-center gap-1.5 cursor-pointer shrink-0"
                            >
                                <Compass class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'تورنت' : 'Torrents' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: FLAT MOVIES VIEW (When on movies tab) -->
                <div v-if="activeTab === 'movies'" class="space-y-4">
                    <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
                        <Award class="w-5 h-5 text-purple-400" />
                        <span>{{ isRTL ? 'كافة أفلام السلاسل الناقصة' : 'All Missing Franchise Movies' }}</span>
                    </h2>

                    <div v-if="flatMovies.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="m in flatMovies"
                            :key="m.id"
                            class="p-4 rounded-2xl glass-panel border border-white/5 hover:border-purple-500/30 bg-slate-900/40 transition-all flex items-start justify-between gap-4"
                        >
                            <div class="space-y-1 min-w-0">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                    {{ m.collection_name }}
                                </span>
                                <h4 class="text-sm font-bold text-white truncate">
                                    {{ m.movie_title }}
                                </h4>
                                <span class="text-[11px] text-slate-400">
                                    {{ m.release_year }}
                                </span>
                            </div>

                            <button
                                @click="openTorrentModal(m)"
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
