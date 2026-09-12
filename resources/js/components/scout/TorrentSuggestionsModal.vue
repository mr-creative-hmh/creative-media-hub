<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    X, Download, Check, AlertCircle, Loader2, Sparkles,
    HardDrive, FolderSync, ShieldCheck, ArrowUpRight, Film, Tv,
    Zap, AlertTriangle, CheckCircle2, ShieldAlert, ArrowDownUp
} from 'lucide-vue-next';

const props = defineProps<{
    isOpen: boolean;
    gapItem: any | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'downloadStarted', payload: any): void;
}>();

const { t, isRTL } = useI18n();

const loading = ref(false);
const torrents = ref<any[]>([]);
const selectedQuality = ref<string>('all');
const hideZeroSeeds = ref<boolean>(true);
const autoOrganize = ref<boolean>(true);
const downloadingHash = ref<string | null>(null);
const downloadedHashes = ref<Record<string, boolean>>({});
const errorMessage = ref<string | null>(null);
const zeroSeedWarningTorrent = ref<any | null>(null);

const qualities = ['all', '4K', '1080p', '720p'];

watch(() => props.isOpen, async (newVal) => {
    if (newVal && props.gapItem) {
        await fetchTorrents();
    } else {
        torrents.value = [];
        selectedQuality.value = 'all';
        errorMessage.value = null;
        zeroSeedWarningTorrent.value = null;
    }
});

const getSeeds = (t: any): number => {
    return Number(t.seeders ?? t.seeds ?? 0);
};

const getPeers = (t: any): number => {
    return Number(t.leechers ?? t.peers ?? 0);
};

const getSource = (t: any): string => {
    return String(t.source ?? t.source_indexer ?? 'Web Tracker');
};

const fetchTorrents = async () => {
    if (!props.gapItem) return;
    loading.value = true;
    errorMessage.value = null;
    torrents.value = [];

    try {
        const params: Record<string, any> = {
            type: props.gapItem.type,
            title: props.gapItem.type === 'collection_movie'
                ? props.gapItem.movie_title
                : props.gapItem.series_title,
            imdb_id: props.gapItem.imdb_id,
            tmdb_id: props.gapItem.tmdb_id,
        };

        if (props.gapItem.type === 'episode') {
            params.season = props.gapItem.season_number;
            params.episode = props.gapItem.episode_number;
        } else if (props.gapItem.type === 'season') {
            params.season = props.gapItem.season_number;
        } else if (props.gapItem.type === 'collection_movie') {
            params.year = props.gapItem.release_year;
        }

        const queryParams = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => {
            if (v !== null && v !== undefined) queryParams.append(k, String(v));
        });
        const res = await fetch(`/api/scout/torrents?${queryParams.toString()}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        torrents.value = data.torrents || [];
    } catch (err: any) {
        errorMessage.value = err?.message || 'Failed to search torrents';
    } finally {
        loading.value = false;
    }
};

const filteredTorrents = computed(() => {
    let list = torrents.value;

    if (selectedQuality.value !== 'all') {
        list = list.filter(t => t.resolution === selectedQuality.value);
    }

    if (hideZeroSeeds.value) {
        const withSeeds = list.filter(t => getSeeds(t) > 0);
        if (withSeeds.length > 0) {
            list = withSeeds;
        }
    }

    return list;
});

const handleDownloadClick = (torrent: any) => {
    if (getSeeds(torrent) === 0) {
        zeroSeedWarningTorrent.value = torrent;
    } else {
        startDownload(torrent);
    }
};

const confirmZeroSeedDownload = () => {
    if (zeroSeedWarningTorrent.value) {
        const tor = zeroSeedWarningTorrent.value;
        zeroSeedWarningTorrent.value = null;
        startDownload(tor);
    }
};

const startDownload = async (torrent: any) => {
    if (!props.gapItem || !torrent.magnet_url) return;
    downloadingHash.value = torrent.info_hash;
    errorMessage.value = null;

    try {
        const mediaType = (props.gapItem.type === 'episode' || props.gapItem.type === 'season') ? 'series' : 'movie';
        const metadata: Record<string, any> = {
            gap_id: props.gapItem.id,
            auto_organize: autoOrganize.value,
        };

        if (mediaType === 'series') {
            metadata.series_title = props.gapItem.series_title;
            metadata.series_id = props.gapItem.series_id;
            metadata.season_number = props.gapItem.season_number;
            metadata.episode_number = props.gapItem.episode_number || 1;
            metadata.episode_title = props.gapItem.episode_title;
            metadata.resolution = torrent.resolution;
        } else {
            metadata.movie_title = props.gapItem.movie_title;
            metadata.release_year = props.gapItem.release_year;
            metadata.collection_name = props.gapItem.collection_name;
            metadata.collection_id = props.gapItem.collection_id;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/api/scout/download', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                title: torrent.title,
                magnet_url: torrent.magnet_url,
                media_type: mediaType,
                auto_organize: autoOrganize.value,
                info_hash: torrent.info_hash,
                metadata,
            }),
        });

        const data = await res.json();
        if (data.success) {
            downloadedHashes.value[torrent.info_hash] = true;
            emit('downloadStarted', {
                gapItem: props.gapItem,
                downloadId: data.download_id,
                title: torrent.title,
            });
        }
    } catch (err: any) {
        errorMessage.value = err?.message || 'Failed to queue download';
    } finally {
        downloadingHash.value = null;
    }
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/85 backdrop-blur-md animate-fade-in" :dir="isRTL ? 'rtl' : 'ltr'">
        <div class="relative w-full max-w-4xl max-h-[92vh] glass-panel border border-cyan-500/20 bg-slate-900/95 rounded-3xl shadow-2xl overflow-hidden flex flex-col">
            
            <!-- Ambient Glow Background -->
            <div class="ambient-glow bg-cyan-500/10 w-96 h-96 -top-24 -left-24 pointer-events-none"></div>
            <div class="ambient-glow bg-purple-500/10 w-96 h-96 -bottom-24 -right-24 pointer-events-none"></div>

            <!-- Header Section -->
            <div class="relative z-10 flex items-start justify-between p-6 border-b border-white/10 bg-slate-950/50">
                <div class="flex items-center gap-4">
                    <img
                        v-if="gapItem?.still_path || gapItem?.poster_path || gapItem?.series_poster"
                        :src="gapItem?.still_path || gapItem?.poster_path || gapItem?.series_poster"
                        class="w-16 h-24 object-cover rounded-2xl border border-white/10 shadow-lg shrink-0"
                        alt="Poster"
                    />
                    <div v-else class="w-16 h-24 rounded-2xl bg-slate-800 border border-white/10 flex items-center justify-center text-slate-600 shrink-0">
                        <Film v-if="gapItem?.type === 'collection_movie'" class="w-8 h-8" />
                        <Tv v-else class="w-8 h-8" />
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 flex items-center gap-1">
                                <Sparkles class="w-3 h-3" />
                                <span>{{ gapItem?.episode_code || gapItem?.season_name || gapItem?.collection_name || (isRTL ? 'عنصر مفقود' : 'Missing Item') }}</span>
                            </span>
                            <span v-if="gapItem?.air_date || gapItem?.release_year" class="text-xs text-slate-400 font-semibold px-2 py-0.5 rounded-md bg-white/5 border border-white/10">
                                {{ gapItem?.air_date || gapItem?.release_year }}
                            </span>
                            <span v-if="gapItem?.part_number" class="text-xs text-purple-300 font-bold px-2 py-0.5 rounded-md bg-purple-500/20 border border-purple-500/30">
                                {{ isRTL ? `الجزء ${gapItem.part_number}` : `Part ${gapItem.part_number}` }}
                            </span>
                        </div>
                        <h2 class="text-xl font-black text-white leading-tight">
                            {{ gapItem?.series_title || gapItem?.movie_title }}
                        </h2>
                        <p v-if="gapItem?.episode_title" class="text-xs text-slate-300 font-medium">
                            {{ isRTL && gapItem?.episode_title_ar ? gapItem.episode_title_ar : gapItem.episode_title }}
                        </p>
                        <p v-else-if="gapItem?.collection_name && gapItem?.type === 'collection_movie'" class="text-xs text-slate-400">
                            {{ isRTL ? `ضمن سلسلة: ${gapItem.collection_name}` : `Franchise: ${gapItem.collection_name}` }}
                        </p>
                    </div>
                </div>

                <button
                    @click="emit('close')"
                    class="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded-xl transition-colors cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Seeders & Download Speed Guidance Banner -->
            <div class="relative z-10 mx-6 mt-4 p-3.5 rounded-2xl bg-slate-950/70 border border-cyan-500/20 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400 shrink-0">
                        <Zap class="w-4 h-4" />
                    </div>
                    <div>
                        <h4 class="font-bold text-white flex items-center gap-1.5">
                            <span>{{ isRTL ? 'إرشادات عدد الموزعين (Seeders)' : 'Seeders & Download Speed Guide' }}</span>
                            <span class="text-[10px] text-cyan-400 font-normal px-1.5 py-0.5 rounded bg-cyan-500/10 border border-cyan-500/20">
                                {{ isRTL ? 'الأولوية للأعلى سرعة' : 'Sorted by highest seeds' }}
                            </span>
                        </h4>
                        <p class="text-[11px] text-slate-400">
                            {{ isRTL ? 'اختر تورنت يحتوي على 5+ موزعين لبدء التحميل فوراً بأقصى سرعة وتفادي التوقف.' : 'Choose torrents with 5+ seeders for high speed and to avoid stalling at 0%.' }}
                        </p>
                    </div>
                </div>

                <!-- Seed Legend Pills -->
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>5+ {{ isRTL ? 'موزع: فائق السرعة' : 'Seeds: High Speed' }}</span>
                    </span>
                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
                        <span>2–4 {{ isRTL ? 'موزع: متوسط' : 'Seeds: Fair' }}</span>
                    </span>
                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                        <span>0–1 {{ isRTL ? 'خامل / توقف 0%' : 'Seeds: Stalled' }}</span>
                    </span>
                </div>
            </div>

            <!-- Controls & Options Bar -->
            <div class="relative z-10 px-6 py-3 border-b border-white/10 bg-slate-900/70 flex flex-wrap items-center justify-between gap-3 mt-3">
                <!-- Quality Filters -->
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1 bg-black/40 p-1 rounded-xl border border-white/10">
                        <button
                            v-for="q in qualities"
                            :key="q"
                            @click="selectedQuality = q"
                            :class="[
                                'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                selectedQuality === q
                                    ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20'
                                    : 'text-slate-400 hover:text-white hover:bg-white/5'
                            ]"
                        >
                            {{ q === 'all' ? (isRTL ? 'كافة الجودات' : 'All Qualities') : q }}
                        </button>
                    </div>

                    <!-- Hide 0-seed dead torrents toggle -->
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-slate-300 select-none bg-black/30 border border-white/10 px-3 py-1.5 rounded-xl hover:border-cyan-500/30 transition-all">
                        <input
                            type="checkbox"
                            v-model="hideZeroSeeds"
                            class="w-3.5 h-3.5 rounded text-cyan-500 focus:ring-cyan-500/40 bg-black/60 border-white/20 cursor-pointer"
                        />
                        <span>{{ isRTL ? 'إخفاء التورنت المتوقف (0 موزع)' : 'Hide 0-Seed Dead Torrents' }}</span>
                    </label>
                </div>

                <!-- Organize and add to Library (Scan) Option Checkbox -->
                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-200 select-none bg-emerald-950/40 border border-emerald-500/30 px-3.5 py-1.5 rounded-xl hover:bg-emerald-950/60 transition-colors">
                    <input
                        type="checkbox"
                        v-model="autoOrganize"
                        class="w-4 h-4 rounded text-emerald-500 focus:ring-emerald-500/40 bg-black/60 border-white/20 cursor-pointer"
                    />
                    <FolderSync class="w-3.5 h-3.5 text-emerald-400" />
                    <span>{{ isRTL ? 'تنظيم وإضافة للمكتبة (فحص) في H:\\Entertainment' : 'Organize and add to Library (Scan) into H:\\Entertainment' }}</span>
                </label>
            </div>

            <!-- Content Area / Torrent List -->
            <div class="relative z-10 flex-1 overflow-y-auto p-6 space-y-3">
                <!-- Loading State -->
                <div v-if="loading" class="py-16 text-center space-y-3">
                    <Loader2 class="w-8 h-8 text-cyan-400 animate-spin mx-auto" />
                    <p class="text-xs text-slate-400 font-medium">
                        {{ isRTL ? 'جارٍ البحث عن أفضل الروابط وترتيبها حسب عدد الموزعين والسرعة...' : 'Searching multiple indexers and ranking by seeders...' }}
                    </p>
                </div>

                <!-- Error Message -->
                <div v-else-if="errorMessage" class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs flex items-center gap-3">
                    <AlertCircle class="w-4 h-4 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </div>

                <!-- No Results State -->
                <div v-else-if="filteredTorrents.length === 0" class="py-12 text-center space-y-3">
                    <div class="w-12 h-12 mx-auto rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center text-slate-500">
                        <AlertCircle class="w-6 h-6" />
                    </div>
                    <p class="text-sm font-bold text-white">
                        {{ isRTL ? 'لم يتم العثور على تورنت نشط' : 'No Active Torrents Found' }}
                    </p>
                    <p class="text-xs text-slate-400 max-w-md mx-auto">
                        {{ isRTL ? 'جرّب تغيير فلتر الجودة أو إلغاء تحديد إخفاء الروابط الخاملة.' : 'Try changing the resolution filter or unchecking the dead torrent filter.' }}
                    </p>
                </div>

                <!-- Torrent Results List -->
                <div
                    v-for="(torrent, index) in filteredTorrents"
                    :key="torrent.info_hash"
                    :class="[
                        'p-4 rounded-2xl glass-panel transition-all flex flex-col md:flex-row items-start md:items-center justify-between gap-4',
                        index === 0 && getSeeds(torrent) >= 5
                            ? 'border-2 border-cyan-400/50 bg-cyan-950/20 shadow-lg shadow-cyan-500/10'
                            : 'border border-white/5 hover:border-cyan-500/30 bg-slate-950/40'
                    ]"
                >
                    <div class="space-y-2 flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <!-- Top Recommended Choice Tag -->
                            <span
                                v-if="index === 0 && getSeeds(torrent) >= 5"
                                class="px-2.5 py-0.5 rounded-md text-[10px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/40 flex items-center gap-1 shadow-sm"
                            >
                                <Sparkles class="w-3 h-3 text-amber-400" />
                                <span>{{ isRTL ? 'الخيار الأفضل والموصى به' : 'Recommended (Highest Seeds)' }}</span>
                            </span>

                            <!-- Resolution Badge -->
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-md text-[10px] font-black',
                                    torrent.resolution === '4K'
                                        ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30'
                                        : 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30'
                                ]"
                            >
                                {{ torrent.resolution || 'HD' }}
                            </span>

                            <!-- Quality & Codec Badges -->
                            <span v-if="torrent.quality_tag || torrent.quality" class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-slate-300">
                                {{ torrent.quality_tag || torrent.quality }}
                            </span>

                            <!-- Source Indexer -->
                            <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-black/40 border border-white/5">
                                {{ getSource(torrent) }}
                            </span>
                        </div>

                        <!-- Title -->
                        <h4 class="text-xs font-bold text-white truncate max-w-xl" :title="torrent.title">
                            {{ torrent.title }}
                        </h4>

                        <!-- Metadata row: Size & Seed Health Badge -->
                        <div class="flex items-center gap-3 flex-wrap text-[11px] font-medium">
                            <span class="text-slate-300 font-bold px-2 py-0.5 rounded bg-white/5 border border-white/5">
                                💾 {{ torrent.size_human }}
                            </span>

                            <!-- Prominent Seeders Badge with Color & Speed Label -->
                            <span
                                v-if="getSeeds(torrent) >= 10"
                                class="px-2.5 py-0.5 rounded-full font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center gap-1 shadow-sm"
                            >
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                <span>👤 {{ getSeeds(torrent) }} {{ isRTL ? 'موزع (فائق السرعة)' : 'Seeds (High Speed)' }}</span>
                            </span>
                            <span
                                v-else-if="getSeeds(torrent) >= 3"
                                class="px-2.5 py-0.5 rounded-full font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 flex items-center gap-1"
                            >
                                <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
                                <span>👤 {{ getSeeds(torrent) }} {{ isRTL ? 'موزعين (مستقر)' : 'Seeds (Stable)' }}</span>
                            </span>
                            <span
                                v-else-if="getSeeds(torrent) >= 1"
                                class="px-2.5 py-0.5 rounded-full font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 flex items-center gap-1"
                            >
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                <span>⚠️ {{ getSeeds(torrent) }} {{ isRTL ? 'موزع (سرعة بطيئة)' : 'Seed (Slow)' }}</span>
                            </span>
                            <span
                                v-else
                                class="px-2.5 py-0.5 rounded-full font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 flex items-center gap-1"
                            >
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                <span>🛑 0 {{ isRTL ? 'موزع (متوقف عند 0%)' : 'Seeds (Will Stall at 0%)' }}</span>
                            </span>

                            <span v-if="getPeers(torrent) > 0" class="text-slate-500 text-[10px]">
                                📥 {{ getPeers(torrent) }} {{ isRTL ? 'محمل' : 'peers' }}
                            </span>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="shrink-0 flex items-center gap-2">
                        <button
                            v-if="downloadedHashes[torrent.info_hash]"
                            disabled
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-1.5 shadow-sm"
                        >
                            <Check class="w-4 h-4 text-emerald-400" />
                            <span>{{ isRTL ? 'تمت الإضافة بنجاح' : 'Queued' }}</span>
                        </button>

                        <button
                            v-else
                            @click="handleDownloadClick(torrent)"
                            :disabled="downloadingHash === torrent.info_hash"
                            :class="[
                                'px-4 py-2 rounded-xl text-xs font-bold active:scale-95 transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50',
                                getSeeds(torrent) === 0
                                    ? 'bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/30'
                                    : 'bg-cyan-500 text-slate-950 hover:bg-cyan-400 shadow-md shadow-cyan-500/20'
                            ]"
                        >
                            <Loader2 v-if="downloadingHash === torrent.info_hash" class="w-4 h-4 animate-spin" />
                            <Download v-else class="w-4 h-4" />
                            <span>{{ downloadingHash === torrent.info_hash ? (isRTL ? 'جارٍ الإضافة...' : 'Queueing...') : (isRTL ? 'تحميل للمكتبة' : 'Download') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 0-Seed Warning Modal Overlay -->
            <div v-if="zeroSeedWarningTorrent" class="absolute inset-0 z-30 flex items-center justify-center p-6 bg-black/85 backdrop-blur-md animate-fade-in">
                <div class="max-w-md w-full p-6 rounded-3xl glass-panel border border-rose-500/30 bg-slate-900 shadow-2xl space-y-4 text-center">
                    <div class="w-12 h-12 mx-auto rounded-2xl bg-rose-500/20 border border-rose-500/40 flex items-center justify-center text-rose-400">
                        <ShieldAlert class="w-6 h-6" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-base font-black text-white">
                            {{ isRTL ? 'تنبيه: لا يوجد موزعون (0 Seeds)!' : 'Warning: 0 Active Seeders!' }}
                        </h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            {{ isRTL 
                                ? 'هذا التورنت لا يحتوي على أي موزع نشط حالياً، وسيتوقف عند 0% ولن يكتمل التحميل (مثلما حدث سابقاً). نوصي باختيار رابط يحتوي على موزعين نشطين.'
                                : 'This torrent has 0 active seeders and will likely stall at 0% without downloading the movie. We strongly recommend choosing a release with active seeders.' }}
                        </p>
                    </div>
                    <div class="flex items-center justify-center gap-3 pt-2">
                        <button
                            @click="zeroSeedWarningTorrent = null"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-cyan-500 text-slate-950 hover:bg-cyan-400 transition-colors cursor-pointer"
                        >
                            {{ isRTL ? 'اختيار رابط نشط (موصى به)' : 'Pick Active Release (Recommended)' }}
                        </button>
                        <button
                            @click="confirmZeroSeedDownload"
                            class="px-4 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                        >
                            {{ isRTL ? 'المتابعة على أي حال' : 'Proceed Anyway' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer Section -->
            <div class="relative z-10 p-4 border-t border-white/10 bg-slate-950/60 flex items-center justify-between text-xs text-slate-400">
                <span class="flex items-center gap-1.5">
                    <HardDrive class="w-3.5 h-3.5 text-cyan-400" />
                    <span>{{ isRTL ? 'القرص المستهدف:' : 'Target Media Drive:' }} <strong class="text-white">H:\Entertainment</strong></span>
                </span>
                <button
                    @click="emit('close')"
                    class="px-3.5 py-1.5 rounded-lg hover:bg-white/10 text-slate-300 transition-colors cursor-pointer"
                >
                    {{ isRTL ? 'إغلاق' : 'Close' }}
                </button>
            </div>

        </div>
    </div>
</template>
