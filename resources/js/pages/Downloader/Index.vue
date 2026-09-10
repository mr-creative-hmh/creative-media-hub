<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import { useDownloader, DownloadItem, DownloaderInspection, TorrentFileItem, TorrentTreeNode } from '@/composables/useDownloader';
import AppLayout from '@/components/layout/AppLayout.vue';
import TorrentTreeItem from '@/components/downloader/TorrentTreeItem.vue';
import {
    DownloadCloud, Plus, CheckCircle2, ArrowDown, ArrowUp,
    Play, Pause, Trash2, FolderSync, Sparkles, HelpCircle,
    Info, HardDrive, ShieldCheck, Film, Tv, Radio, Clock,
    RotateCcw, AlertTriangle, ExternalLink, Settings, Check,
    Folder, FolderOpen, FolderTree, ListFilter, Magnet, Zap,
    FileText, File, FileUp, CheckSquare, MinusSquare, Square
} from 'lucide-vue-next';

const props = defineProps<{
    initialDownloads?: DownloadItem[];
    destinations?: {
        movies: string;
        series: string;
        default: string;
    };
    settings?: {
        default_download_path: string;
        movies_download_path: string;
        series_download_path: string;
        max_concurrent_downloads: number;
        download_speed_limit_kb: number;
    };
}>();

const { t, isRTL } = useI18n();
const toast = useToast();
const {
    downloads,
    activeDownloads,
    completedDownloads,
    totalSpeedDownFormatted,
    totalSpeedUpFormatted,
    formatBytes,
    getProgressPercent,
    getETA,
    fetchDownloads,
    inspectUrl,
    inspectTorrentFile,
    getSettings,
    saveSettings,
    addDownload,
    pauseDownload,
    resumeDownload,
    retryDownload,
    deleteDownload,
} = useDownloader();

const activeFilter = ref<'all' | 'downloading' | 'paused' | 'completed'>('all');
const showAddModal = ref(false);
const showExplainModal = ref(false);
const showSettingsModal = ref(false);

// New Download Form State
const downloadMode = ref<'direct' | 'torrent'>('torrent');
const newTitle = ref('');
const newUrl = ref('');
const newType = ref<'movie' | 'series' | 'subtitle'>('movie');
const destinationType = ref<'default' | 'custom'>('default');
const customFolder = ref('');
const isInspecting = ref(false);
const isUploadingTorrent = ref(false);
const isSubmitting = ref(false);
const inspectionData = ref<DownloaderInspection | null>(null);
const selectedFileIndexes = ref<number[]>([]);
const viewMode = ref<'tree' | 'flat'>('tree');
const torrentFileInputRef = ref<HTMLInputElement | null>(null);

// Downloader Settings State
const settingsForm = ref({
    default_download_path: props.settings?.default_download_path || 'H:\\Entertainment\\Downloads',
    movies_download_path: props.settings?.movies_download_path || props.destinations?.movies || 'H:\\Entertainment\\Movies',
    series_download_path: props.settings?.series_download_path || props.destinations?.series || 'H:\\Entertainment\\TV Shows',
    max_concurrent_downloads: props.settings?.max_concurrent_downloads || 3,
    download_speed_limit_kb: props.settings?.download_speed_limit_kb || 0,
});
const isSavingSettings = ref(false);

onMounted(() => {
    if (props.initialDownloads && props.initialDownloads.length > 0 && downloads.value.length === 0) {
        downloads.value = props.initialDownloads;
    }
    fetchDownloads();
});

const filteredItems = computed(() => {
    if (activeFilter.value === 'all') return downloads.value;
    if (activeFilter.value === 'downloading') {
        return downloads.value.filter(d => d.status === 'downloading' || d.status === 'queued');
    }
    return downloads.value.filter(d => d.status === activeFilter.value);
});

const defaultTargetFolder = computed(() => {
    if (newType.value === 'series') {
        return settingsForm.value.series_download_path || props.destinations?.series || 'H:\\Entertainment\\TV Shows';
    }
    return settingsForm.value.movies_download_path || props.destinations?.movies || 'H:\\Entertainment\\Movies';
});

const effectiveFolder = computed(() => {
    if (destinationType.value === 'custom' && customFolder.value.trim()) {
        return customFolder.value.trim();
    }
    return defaultTargetFolder.value;
});

// Auto or Manual URL Inspect
const handleInspectUrl = async () => {
    if (!newUrl.value.trim()) return;
    isInspecting.value = true;
    try {
        const res = await inspectUrl(newUrl.value.trim(), downloadMode.value);
        if (res) {
            applyInspectionResult(res);
        }
    } finally {
        isInspecting.value = false;
    }
};

const triggerTorrentFileInput = () => {
    if (torrentFileInputRef.value) {
        torrentFileInputRef.value.click();
    }
};

const handleTorrentFileChange = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    isUploadingTorrent.value = true;
    try {
        const res = await inspectTorrentFile(file);
        if (res) {
            applyInspectionResult(res);
            toast.success(
                isRTL.value ? 'تم فحص ملف التورنت وقراءة هيكل المجلدات بنجاح!' : 'Torrent file inspected and folder hierarchy parsed successfully!',
                isRTL.value ? 'فحص ناجح' : 'Inspected'
            );
        } else {
            toast.error(
                isRTL.value ? 'تعذر قراءة ملف التورنت. يرجى التأكد من سلامة الملف.' : 'Failed to parse torrent file. Please check file integrity.',
                isRTL.value ? 'خطأ' : 'Error'
            );
        }
    } catch (err: any) {
        toast.error(err?.message || 'Error parsing torrent file');
    } finally {
        isUploadingTorrent.value = false;
        // Reset file input so user can re-select the same file if needed
        if (target) target.value = '';
    }
};

const applyInspectionResult = (res: DownloaderInspection) => {
    inspectionData.value = res;
    downloadMode.value = res.download_type || 'torrent';
    if (res.title && (!newTitle.value || newTitle.value.trim() === '')) {
        newTitle.value = res.title;
    }
    if (res.media_type) {
        newType.value = res.media_type;
    }
    // Select video files by default
    const videoIndexes = res.files
        .filter(f => f.is_video || f.selected)
        .map(f => f.index);

    if (videoIndexes.length > 0) {
        selectedFileIndexes.value = videoIndexes;
    } else if (res.files.length > 0) {
        // Fallback: select all files
        selectedFileIndexes.value = res.files.map(f => f.index);
    }
    // Default to tree view if tree nodes exist
    if (res.tree && res.tree.length > 0) {
        viewMode.value = 'tree';
    } else {
        viewMode.value = 'flat';
    }
};

const handleUrlPasteOrChange = () => {
    const url = newUrl.value.trim();
    if (url.startsWith('magnet:') || url.endsWith('.torrent')) {
        downloadMode.value = 'torrent';
    }
    if (url.length > 10) {
        handleInspectUrl();
    }
};

const selectAllFiles = () => {
    if (!inspectionData.value) return;
    selectedFileIndexes.value = inspectionData.value.files.map(f => f.index);
};

const selectVideosOnly = () => {
    if (!inspectionData.value) return;
    const vids = inspectionData.value.files.filter(f => f.is_video).map(f => f.index);
    selectedFileIndexes.value = vids.length > 0 ? vids : inspectionData.value.files.map(f => f.index);
};

const clearAllFiles = () => {
    selectedFileIndexes.value = [];
};

const toggleFileSelection = (idx: number) => {
    if (selectedFileIndexes.value.includes(idx)) {
        selectedFileIndexes.value = selectedFileIndexes.value.filter(i => i !== idx);
    } else {
        selectedFileIndexes.value.push(idx);
    }
};

const handleToggleFolder = (indexes: number[], targetState: boolean) => {
    if (targetState) {
        const toAdd = indexes.filter(i => !selectedFileIndexes.value.includes(i));
        selectedFileIndexes.value = [...selectedFileIndexes.value, ...toAdd];
    } else {
        selectedFileIndexes.value = selectedFileIndexes.value.filter(i => !indexes.includes(i));
    }
};

const selectedTotalBytes = computed(() => {
    if (!inspectionData.value) return 0;
    return inspectionData.value.files
        .filter(f => selectedFileIndexes.value.includes(f.index))
        .reduce((sum, f) => sum + (f.size || 0), 0);
});

const handleAddSubmit = async () => {
    if (!newTitle.value.trim()) return;
    isSubmitting.value = true;
    try {
        await addDownload({
            title: newTitle.value.trim(),
            media_type: newType.value,
            source_url: newUrl.value.trim() || undefined,
            download_type: downloadMode.value,
            destination_folder: effectiveFolder.value,
            selected_files: selectedFileIndexes.value,
            torrent_files: inspectionData.value?.files || undefined,
            info_hash: inspectionData.value?.info_hash || undefined,
        });

        newTitle.value = '';
        newUrl.value = '';
        inspectionData.value = null;
        selectedFileIndexes.value = [];
        showAddModal.value = false;
    } finally {
        isSubmitting.value = false;
    }
};

const handleSaveSettings = async () => {
    isSavingSettings.value = true;
    try {
        const ok = await saveSettings(settingsForm.value);
        if (ok) {
            toast.success(
                isRTL.value ? 'تم حفظ إعدادات التنزيل بنجاح!' : 'Downloader settings saved successfully!',
                isRTL.value ? 'تم الحفظ' : 'Saved'
            );
            showSettingsModal.value = false;
        }
    } finally {
        isSavingSettings.value = false;
    }
};
</script>

<template>
    <Head :title="t('nav.downloads')" />

    <AppLayout v-slot="{ play }">
        <!-- Top Header & Metrics -->
        <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center shadow-lg shadow-cyan-500/10">
                    <DownloadCloud class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                        {{ t('nav.downloads') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                        {{ isRTL ? 'إدارة التنزيلات الحقيقية في الخلفية، ملفات التورنت المحددة بشجرة المجلدات، ومجلدات الوجهة المخصصة.' : 'High-speed background downloads, selective folder tree torrents, and custom destination folders.' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button
                    @click="showSettingsModal = true"
                    class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl glass-panel border border-white/10 hover:border-cyan-500/40 text-slate-300 hover:text-white font-bold text-xs transition-all cursor-pointer shadow-sm"
                    title="Downloader Settings"
                >
                    <Settings class="w-4 h-4 text-cyan-400" />
                    <span class="hidden sm:inline">{{ isRTL ? 'إعدادات التنزيل' : 'Settings' }}</span>
                </button>

                <button
                    @click="showExplainModal = true"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl glass-panel border border-white/10 hover:border-cyan-500/40 text-slate-300 hover:text-white font-bold text-xs transition-all cursor-pointer shadow-sm"
                >
                    <HelpCircle class="w-4 h-4 text-cyan-400" />
                    <span>{{ isRTL ? 'دليل المحرك' : 'Guide' }}</span>
                </button>

                <button
                    @click="showAddModal = true"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/20 active:scale-95 transition-all cursor-pointer"
                >
                    <Plus class="w-4 h-4" />
                    <span>{{ isRTL ? 'إضافة تنزيل / تورنت' : 'Add Download' }}</span>
                </button>
            </div>
        </div>

        <!-- Real-Time Bandwidth & Queue Metrics Bar -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="glass-panel rounded-2xl p-4 border border-cyan-500/30 bg-cyan-500/5 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">{{ isRTL ? 'سرعة التنزيل' : 'Download Speed' }}</span>
                    <span class="text-xl font-black text-cyan-400 mt-1 block font-mono">{{ totalSpeedDownFormatted }}</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                    <ArrowDown class="w-4 h-4" />
                </div>
            </div>

            <div class="glass-panel rounded-2xl p-4 border border-indigo-500/30 bg-indigo-500/5 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">{{ isRTL ? 'سرعة الرفع / التغذية' : 'Seeding Speed' }}</span>
                    <span class="text-xl font-black text-indigo-400 mt-1 block font-mono">{{ totalSpeedUpFormatted }}</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center">
                    <ArrowUp class="w-4 h-4" />
                </div>
            </div>

            <div class="glass-panel rounded-2xl p-4 border border-emerald-500/30 bg-emerald-500/5 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">{{ isRTL ? 'المهام النشطة' : 'Active Jobs' }}</span>
                    <span class="text-xl font-black text-emerald-400 mt-1 block font-mono">{{ activeDownloads.length }}</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                    <Radio class="w-4 h-4" />
                </div>
            </div>

            <div class="glass-panel rounded-2xl p-4 border border-white/10 bg-white/5 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">{{ isRTL ? 'مكتملة ومفهرسة' : 'Completed' }}</span>
                    <span class="text-xl font-black text-white mt-1 block font-mono">{{ completedDownloads.length }}</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-white/10 text-slate-300 flex items-center justify-center">
                    <CheckCircle2 class="w-4 h-4" />
                </div>
            </div>
        </div>

        <!-- Filter Pills Bar -->
        <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2">
            <button
                v-for="filter in [
                    { id: 'all', label_en: 'All Downloads', label_ar: 'الكل' },
                    { id: 'downloading', label_en: 'Downloading', label_ar: 'قيد التنزيل' },
                    { id: 'paused', label_en: 'Paused', label_ar: 'متوقف' },
                    { id: 'completed', label_en: 'Completed', label_ar: 'المكتملة' },
                ]"
                :key="filter.id"
                @click="activeFilter = filter.id as any"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer"
                :class="activeFilter === filter.id ? 'bg-cyan-500 text-slate-950 font-black shadow-md shadow-cyan-500/20' : 'glass-panel border border-white/10 text-slate-400 hover:text-white'"
            >
                {{ isRTL ? filter.label_ar : filter.label_en }}
            </button>
        </div>

        <!-- Downloads Table / Cards -->
        <div v-if="filteredItems.length === 0" class="glass-panel rounded-3xl p-12 text-center border border-white/10 space-y-4">
            <DownloadCloud class="w-12 h-12 text-slate-600 mx-auto" />
            <h3 class="text-base font-bold text-white">{{ isRTL ? 'لا توجد تنزيلات في هذه القائمة' : 'No downloads in this view' }}</h3>
            <p class="text-xs text-slate-400 max-w-sm mx-auto">
                {{ isRTL ? 'أضف رابط تورنت أو ملف .torrent أو تحميل مباشر لبدء تنزيل المحتوى بدقة دون أي تلف.' : 'Add a torrent file, magnet link, or direct stream to start downloading directly without corruption.' }}
            </p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="item in filteredItems"
                :key="item.id"
                class="glass-panel rounded-2xl p-4 border border-white/10 hover:border-cyan-500/40 transition-all flex flex-col md:flex-row items-start md:items-center justify-between gap-4 group bg-white/[0.02]"
            >
                <div class="flex items-center gap-3.5 flex-1 min-w-0">
                    <div
                        class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border"
                        :class="item.media_type === 'series' ? 'bg-indigo-500/10 border-indigo-500/30 text-indigo-400' : 'bg-cyan-500/10 border-cyan-500/30 text-cyan-400'"
                    >
                        <Magnet v-if="item.download_type === 'torrent'" class="w-5 h-5 text-purple-400" />
                        <Tv v-else-if="item.media_type === 'series'" class="w-5 h-5" />
                        <Film v-else class="w-5 h-5" />
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <h4 class="font-extrabold text-sm text-white truncate max-w-md" :title="item.title">
                                {{ item.title }}
                            </h4>
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider"
                                :class="{
                                    'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30': item.status === 'downloading',
                                    'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': item.status === 'completed',
                                    'bg-amber-500/20 text-amber-300 border border-amber-500/30': item.status === 'paused',
                                    'bg-blue-500/20 text-blue-300 border border-blue-500/30': item.status === 'queued',
                                    'bg-rose-500/20 text-rose-300 border border-rose-500/30': item.status === 'failed',
                                }"
                            >
                                {{ item.status }}
                            </span>
                            <span v-if="item.download_type === 'torrent'" class="px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300 text-[10px] font-mono font-bold">
                                🧲 Torrent
                            </span>
                        </div>

                        <!-- Progress Bar & Specs -->
                        <div class="space-y-1.5 max-w-xl">
                            <div class="w-full h-1.5 rounded-full bg-white/10 overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-300"
                                    :class="item.status === 'completed' ? 'bg-emerald-400' : 'bg-gradient-to-r from-cyan-400 to-blue-500'"
                                    :style="{ width: `${getProgressPercent(item)}%` }"
                                ></div>
                            </div>

                            <div class="flex items-center gap-3 text-[11px] text-slate-400 font-mono">
                                <span>{{ formatBytes(item.downloaded_bytes) }} / {{ formatBytes(item.total_bytes) }} ({{ getProgressPercent(item) }}%)</span>
                                <span>•</span>
                                <span v-if="item.status === 'downloading'" class="text-cyan-300">⚡ {{ (item.speed_bytes_sec / (1024 * 1024)).toFixed(1) }} MB/s</span>
                                <span v-if="item.status === 'downloading'">ETA: {{ getETA(item) }}</span>
                                <span v-if="item.destination_folder || item.destination_path" class="text-slate-500 text-[10px] truncate max-w-xs" :title="item.destination_folder || item.destination_path">
                                    📁 {{ item.destination_folder || item.destination_path }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2 shrink-0 self-end md:self-center">
                    <button
                        v-if="item.status === 'downloading'"
                        @click="pauseDownload(item.id)"
                        class="p-2 rounded-xl bg-white/5 hover:bg-amber-500/20 border border-white/10 hover:border-amber-500/40 text-slate-300 hover:text-amber-300 transition-all cursor-pointer"
                        title="Pause"
                    >
                        <Pause class="w-4 h-4" />
                    </button>
                    <button
                        v-if="item.status === 'paused'"
                        @click="resumeDownload(item.id)"
                        class="p-2 rounded-xl bg-white/5 hover:bg-cyan-500/20 border border-white/10 hover:border-cyan-500/40 text-slate-300 hover:text-cyan-300 transition-all cursor-pointer"
                        title="Resume"
                    >
                        <Play class="w-4 h-4" />
                    </button>
                    <button
                        v-if="item.status === 'failed'"
                        @click="retryDownload(item.id)"
                        class="p-2 rounded-xl bg-white/5 hover:bg-cyan-500/20 border border-white/10 hover:border-cyan-500/40 text-slate-300 hover:text-cyan-300 transition-all cursor-pointer"
                        title="Retry"
                    >
                        <RotateCcw class="w-4 h-4" />
                    </button>
                    <button
                        @click="deleteDownload(item.id)"
                        class="p-2 rounded-xl bg-white/5 hover:bg-rose-500/20 border border-white/10 hover:border-rose-500/40 text-slate-400 hover:text-rose-400 transition-all cursor-pointer"
                        title="Delete"
                    >
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Download Modal with Torrent File Selection & Folder Settings -->
        <transition name="fade">
            <div
                v-if="showAddModal"
                class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4"
                @click.self="showAddModal = false"
            >
                <div class="glass-panel rounded-3xl p-6 sm:p-8 max-w-2xl w-full border border-cyan-500/30 shadow-2xl space-y-5 max-h-[92vh] flex flex-col">
                    <div class="flex items-center justify-between border-b border-white/10 pb-3">
                        <h3 class="font-extrabold text-base text-white flex items-center gap-2">
                            <DownloadCloud class="w-5 h-5 text-cyan-400" />
                            <span>{{ isRTL ? 'إضافة تنزيل وتورنت بهيكل المجلدات' : 'Add Download / Torrent Job' }}</span>
                        </h3>
                    </div>

                    <div class="flex-1 overflow-y-auto space-y-4 pr-1 custom-scrollbar">
                        <!-- Mode Selector: Direct vs Torrent -->
                        <div>
                            <label class="text-xs font-bold text-slate-400 mb-1.5 block">{{ isRTL ? 'نوع التنزيل' : 'Download Mode' }}</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="downloadMode = 'torrent'"
                                    class="py-2.5 px-4 rounded-xl text-xs font-bold border cursor-pointer flex items-center justify-center gap-2 transition-all"
                                    :class="downloadMode === 'torrent' ? 'bg-purple-600 text-white border-purple-400 shadow-md shadow-purple-600/30' : 'bg-white/5 border-white/10 text-slate-400 hover:text-white'"
                                >
                                    <Magnet class="w-4 h-4" />
                                    <span>🧲 {{ isRTL ? 'تورنت / رابط Magnet / ملف .torrent' : 'Torrent / Magnet / File' }}</span>
                                </button>
                                <button
                                    type="button"
                                    @click="downloadMode = 'direct'"
                                    class="py-2.5 px-4 rounded-xl text-xs font-bold border cursor-pointer flex items-center justify-center gap-2 transition-all"
                                    :class="downloadMode === 'direct' ? 'bg-cyan-500 text-slate-950 font-black border-cyan-400 shadow-md shadow-cyan-500/30' : 'bg-white/5 border-white/10 text-slate-400 hover:text-white'"
                                >
                                    <Zap class="w-4 h-4" />
                                    <span>⚡ {{ isRTL ? 'تحميل مباشر (HTTP/HTTPS)' : 'Direct HTTP / Stream' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- URL or File Input -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-bold text-slate-400">
                                    {{ downloadMode === 'torrent' ? (isRTL ? 'رابط Magnet أو مسار .torrent' : 'Magnet Link or Torrent File URL') : (isRTL ? 'رابط الملف المباشر' : 'Direct File URL') }}
                                </label>
                                <span v-if="isInspecting || isUploadingTorrent" class="text-[11px] text-cyan-400 flex items-center gap-1 font-mono">
                                    <Clock class="w-3 h-3 animate-spin" />
                                    {{ isRTL ? 'جاري فحص الملفات...' : 'Inspecting files...' }}
                                </span>
                            </div>

                            <!-- Hidden file input for .torrent uploads -->
                            <input
                                type="file"
                                ref="torrentFileInputRef"
                                accept=".torrent"
                                class="hidden"
                                @change="handleTorrentFileChange"
                            />

                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    v-model="newUrl"
                                    @blur="handleUrlPasteOrChange"
                                    @keyup.enter="handleInspectUrl"
                                    :placeholder="downloadMode === 'torrent' ? 'magnet:?xt=urn:btih:... or https://.../file.torrent' : 'https://example.com/movie.mp4'"
                                    class="flex-1 h-11 rounded-xl bg-white/[0.04] border border-white/15 px-4 text-xs font-mono text-cyan-300 focus:border-cyan-500 outline-none"
                                />

                                <button
                                    type="button"
                                    @click="handleInspectUrl"
                                    :disabled="isInspecting || !newUrl.trim()"
                                    class="px-3.5 h-11 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition-all cursor-pointer disabled:opacity-40 flex items-center gap-1.5 shrink-0"
                                    :title="isRTL ? 'فحص الرابط وقراءة الملفات' : 'Inspect URL and parse files'"
                                >
                                    <Sparkles class="w-3.5 h-3.5 text-cyan-400" />
                                    <span>{{ isRTL ? 'فحص' : 'Inspect' }}</span>
                                </button>

                                <button
                                    v-if="downloadMode === 'torrent'"
                                    type="button"
                                    @click="triggerTorrentFileInput"
                                    :disabled="isUploadingTorrent || isInspecting"
                                    class="px-3.5 h-11 rounded-xl bg-purple-600/25 hover:bg-purple-600/40 border border-purple-500/40 text-purple-200 hover:text-white text-xs font-bold transition-all cursor-pointer disabled:opacity-40 flex items-center gap-1.5 shrink-0 shadow-sm"
                                    :title="isRTL ? 'رفع ملف .torrent من جهازك وفحصه محلياً' : 'Upload local .torrent file directly'"
                                >
                                    <FileUp v-if="!isUploadingTorrent" class="w-3.5 h-3.5 text-purple-400" />
                                    <Clock v-else class="w-3.5 h-3.5 animate-spin text-purple-400" />
                                    <span class="hidden sm:inline">{{ isRTL ? 'رفع .torrent' : 'Upload .torrent' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Media Title & Type -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <label class="text-xs font-bold text-slate-400 mb-1 block">{{ isRTL ? 'عنوان العمل' : 'Title & Year' }}</label>
                                <input
                                    type="text"
                                    v-model="newTitle"
                                    placeholder="e.g. Inception (2010)"
                                    class="w-full h-11 rounded-xl bg-white/[0.04] border border-white/15 px-4 text-xs text-white focus:border-cyan-500 outline-none"
                                />
                            </div>

                            <div>
                                <label class="text-xs font-bold text-slate-400 mb-1 block">{{ isRTL ? 'نوع المحتوى' : 'Media Type' }}</label>
                                <select
                                    v-model="newType"
                                    class="w-full h-11 rounded-xl bg-slate-900 border border-white/15 px-3 text-xs text-white focus:border-cyan-500 outline-none cursor-pointer"
                                >
                                    <option value="movie">🎬 {{ isRTL ? 'فيلم' : 'Movie' }}</option>
                                    <option value="series">📺 {{ isRTL ? 'مسلسل' : 'Series' }}</option>
                                    <option value="subtitle">💬 {{ isRTL ? 'ترجمة' : 'Subtitle' }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Multi-File & Folder Selection for Torrent Mode -->
                        <div v-if="inspectionData && inspectionData.files && inspectionData.files.length > 0" class="border border-purple-500/30 rounded-2xl p-4 bg-purple-950/20 space-y-3">
                            <!-- Header with view toggle and filter actions -->
                            <div class="flex items-center justify-between flex-wrap gap-2.5 pb-2 border-b border-purple-500/20">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-purple-200 flex items-center gap-1.5">
                                        <FolderTree class="w-4 h-4 text-purple-400" />
                                        <span>{{ isRTL ? 'هيكل الملفات والمجلدات' : 'Torrent Files & Folders' }}</span>
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full bg-purple-500/20 border border-purple-500/30 text-[10px] font-mono text-purple-300">
                                        {{ selectedFileIndexes.length }}/{{ inspectionData.files.length }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <!-- View Mode Tabs (Tree vs Flat) -->
                                    <div class="flex items-center rounded-lg bg-black/40 p-0.5 border border-white/10 text-[11px]">
                                        <button
                                            type="button"
                                            @click="viewMode = 'tree'"
                                            class="px-2.5 py-1 rounded-md transition-all flex items-center gap-1 cursor-pointer font-medium"
                                            :class="viewMode === 'tree' ? 'bg-purple-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'"
                                            :title="isRTL ? 'عرض شجرة المجلدات المتداخلة' : 'View hierarchical folder tree'"
                                        >
                                            <FolderTree class="w-3 h-3" />
                                            <span>{{ isRTL ? 'شجرة' : 'Tree' }}</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="viewMode = 'flat'"
                                            class="px-2.5 py-1 rounded-md transition-all flex items-center gap-1 cursor-pointer font-medium"
                                            :class="viewMode === 'flat' ? 'bg-purple-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'"
                                            :title="isRTL ? 'عرض قائمة مسطحة بالملفات' : 'View flat list of files'"
                                        >
                                            <ListFilter class="w-3 h-3" />
                                            <span>{{ isRTL ? 'قائمة' : 'List' }}</span>
                                        </button>
                                    </div>

                                    <!-- Quick Filters -->
                                    <div class="flex items-center gap-1 text-[10px]">
                                        <button
                                            @click="selectAllFiles"
                                            type="button"
                                            class="px-2 py-1 rounded-md bg-white/10 hover:bg-white/20 text-white cursor-pointer transition-all"
                                        >
                                            {{ isRTL ? 'تحديد الكل' : 'All' }}
                                        </button>
                                        <button
                                            @click="selectVideosOnly"
                                            type="button"
                                            class="px-2 py-1 rounded-md bg-purple-500/30 hover:bg-purple-500/50 text-purple-200 cursor-pointer transition-all"
                                        >
                                            {{ isRTL ? 'الفيديوهات فقط' : 'Videos' }}
                                        </button>
                                        <button
                                            @click="clearAllFiles"
                                            type="button"
                                            class="px-2 py-1 rounded-md bg-white/5 hover:bg-white/15 text-slate-400 cursor-pointer transition-all"
                                        >
                                            {{ isRTL ? 'تفريغ' : 'Clear' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- TREE VIEW -->
                            <div
                                v-if="viewMode === 'tree' && inspectionData.tree && inspectionData.tree.length > 0"
                                class="max-h-64 sm:max-h-72 overflow-y-auto space-y-1 custom-scrollbar pr-1"
                            >
                                <TorrentTreeItem
                                    v-for="node in inspectionData.tree"
                                    :key="node.path"
                                    :node="node"
                                    :selected-indexes="selectedFileIndexes"
                                    :is-r-t-l="isRTL"
                                    @toggle-file="toggleFileSelection"
                                    @toggle-folder="handleToggleFolder"
                                />
                            </div>

                            <!-- FLAT LIST VIEW -->
                            <div
                                v-else
                                class="max-h-64 sm:max-h-72 overflow-y-auto space-y-1.5 custom-scrollbar pr-1"
                            >
                                <div
                                    v-for="file in inspectionData.files"
                                    :key="file.index"
                                    @click="toggleFileSelection(file.index)"
                                    class="p-2 rounded-xl flex items-center justify-between gap-2 text-xs cursor-pointer transition-all border"
                                    :class="selectedFileIndexes.includes(file.index)
                                        ? 'bg-purple-500/20 border-purple-500/40 text-white shadow-sm'
                                        : 'bg-black/20 border-transparent text-slate-400 hover:bg-white/5'"
                                >
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <div
                                            class="w-4 h-4 rounded border flex items-center justify-center shrink-0 transition-all"
                                            :class="selectedFileIndexes.includes(file.index)
                                                ? 'border-purple-400 bg-purple-500 text-slate-950'
                                                : 'border-slate-600 bg-black/40'"
                                        >
                                            <Check v-if="selectedFileIndexes.includes(file.index)" class="w-3 h-3 stroke-[3]" />
                                        </div>

                                        <Film v-if="file.is_video" class="w-3.5 h-3.5 text-cyan-400 shrink-0" />
                                        <FileText v-else-if="file.is_subtitle" class="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                                        <File v-else class="w-3.5 h-3.5 text-slate-500 shrink-0" />

                                        <span class="truncate text-[11px] font-mono" :title="file.path">
                                            {{ file.path }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <span
                                            v-if="file.is_video"
                                            class="px-1.5 py-0.2 rounded bg-cyan-500/15 text-cyan-300 border border-cyan-500/30 text-[9px] font-bold uppercase"
                                        >
                                            video
                                        </span>
                                        <span class="text-[10px] font-mono text-slate-400">{{ formatBytes(file.size) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Stats -->
                            <div class="text-[11px] font-mono text-purple-300 flex items-center justify-between pt-2 border-t border-purple-500/20">
                                <span class="flex items-center gap-2">
                                    <span>{{ isRTL ? 'الحجم المحدد:' : 'Selected size:' }}</span>
                                    <strong class="text-white">{{ formatBytes(selectedTotalBytes) }}</strong>
                                    <span class="text-slate-500 text-[10px]">/ {{ formatBytes(inspectionData.total_bytes) }}</span>
                                </span>

                                <span v-if="inspectionData.folder_count" class="text-slate-400 text-[10px]">
                                    {{ inspectionData.folder_count }} {{ isRTL ? 'مجلد' : 'folders' }}
                                </span>
                            </div>
                        </div>

                        <!-- Destination Folder Selector: Default vs Custom -->
                        <div class="space-y-2 pt-1 border-t border-white/10">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-bold text-slate-400 block">{{ isRTL ? 'مجلد الحفظ النهائي' : 'Destination Download Folder' }}</label>
                                <span class="text-[10px] text-slate-500">
                                    {{ isRTL ? 'يتم حفظ الملفات مباشرة بالهيكل الداخلي' : 'Preserves internal folder layout' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="destinationType = 'default'"
                                    class="py-2 px-3 rounded-xl text-xs font-bold border cursor-pointer flex items-center gap-1.5 transition-all text-left"
                                    :class="destinationType === 'default' ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40' : 'bg-white/5 border-white/10 text-slate-400'"
                                >
                                    <Folder class="w-3.5 h-3.5 shrink-0" />
                                    <div class="truncate">
                                        <div class="text-[11px] font-bold">{{ isRTL ? 'المجلد الافتراضي' : 'Default Library' }}</div>
                                        <div class="text-[9px] text-slate-400 truncate">{{ defaultTargetFolder }}</div>
                                    </div>
                                </button>

                                <button
                                    type="button"
                                    @click="destinationType = 'custom'"
                                    class="py-2 px-3 rounded-xl text-xs font-bold border cursor-pointer flex items-center gap-1.5 transition-all text-left"
                                    :class="destinationType === 'custom' ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40' : 'bg-white/5 border-white/10 text-slate-400'"
                                >
                                    <HardDrive class="w-3.5 h-3.5 shrink-0" />
                                    <div>
                                        <div class="text-[11px] font-bold">{{ isRTL ? 'مجلد مخصص' : 'Custom Folder' }}</div>
                                        <div class="text-[9px] text-slate-400">{{ isRTL ? 'تحديد مسار يدوي' : 'Select custom path' }}</div>
                                    </div>
                                </button>
                            </div>

                            <div v-if="destinationType === 'custom'" class="pt-1">
                                <input
                                    type="text"
                                    v-model="customFolder"
                                    placeholder="e.g. D:\Downloads\Special"
                                    class="w-full h-10 rounded-xl bg-white/[0.04] border border-cyan-500/40 px-3 text-xs font-mono text-cyan-200 focus:border-cyan-400 outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                        <button
                            type="button"
                            @click="showAddModal = false"
                            class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-bold text-slate-300 cursor-pointer"
                        >
                            {{ t('common.close') }}
                        </button>
                        <button
                            type="button"
                            @click="handleAddSubmit"
                            :disabled="isSubmitting || !newTitle.trim() || (downloadMode === 'torrent' && selectedFileIndexes.length === 0)"
                            class="px-6 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 disabled:opacity-50 text-slate-950 text-xs font-black cursor-pointer shadow-lg shadow-cyan-500/20"
                        >
                            {{ isRTL ? 'بدء التنزيل المباشر' : 'Start Download' }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Downloader Settings Modal -->
        <transition name="fade">
            <div
                v-if="showSettingsModal"
                class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4"
                @click.self="showSettingsModal = false"
            >
                <div class="glass-panel rounded-3xl p-6 sm:p-8 max-w-lg w-full border border-white/20 shadow-2xl space-y-5">
                    <div class="flex items-center justify-between border-b border-white/10 pb-3">
                        <h3 class="font-extrabold text-base text-white flex items-center gap-2">
                            <Settings class="w-5 h-5 text-cyan-400" />
                            <span>{{ isRTL ? 'إعدادات مسارات التنزيل ومجلدات المكتبة' : 'Downloader & Destination Folders' }}</span>
                        </h3>
                    </div>

                    <div class="space-y-3.5 text-xs">
                        <div>
                            <label class="font-bold text-slate-300 block mb-1">{{ isRTL ? 'مجلد الأفلام الافتراضي' : 'Default Movies Destination' }}</label>
                            <input
                                type="text"
                                v-model="settingsForm.movies_download_path"
                                class="w-full h-10 rounded-xl bg-white/5 border border-white/15 px-3 text-xs font-mono text-cyan-300 focus:border-cyan-500 outline-none"
                            />
                        </div>

                        <div>
                            <label class="font-bold text-slate-300 block mb-1">{{ isRTL ? 'مجلد المسلسلات الافتراضي' : 'Default Series Destination' }}</label>
                            <input
                                type="text"
                                v-model="settingsForm.series_download_path"
                                class="w-full h-10 rounded-xl bg-white/5 border border-white/15 px-3 text-xs font-mono text-indigo-300 focus:border-indigo-500 outline-none"
                            />
                        </div>

                        <div>
                            <label class="font-bold text-slate-300 block mb-1">{{ isRTL ? 'مجلد التنزيل المؤقت' : 'Default Download Staging Directory' }}</label>
                            <input
                                type="text"
                                v-model="settingsForm.default_download_path"
                                class="w-full h-10 rounded-xl bg-white/5 border border-white/15 px-3 text-xs font-mono text-slate-300 focus:border-cyan-500 outline-none"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <div>
                                <label class="font-bold text-slate-300 block mb-1">{{ isRTL ? 'أقصى تنزيلات متزامنة' : 'Max Concurrent' }}</label>
                                <input
                                    type="number"
                                    min="1"
                                    max="10"
                                    v-model.number="settingsForm.max_concurrent_downloads"
                                    class="w-full h-10 rounded-xl bg-white/5 border border-white/15 px-3 text-xs text-white focus:border-cyan-500 outline-none font-mono"
                                />
                            </div>
                            <div>
                                <label class="font-bold text-slate-300 block mb-1">{{ isRTL ? 'حد السرعة (0 = غير محدود)' : 'Speed Limit (KB/s)' }}</label>
                                <input
                                    type="number"
                                    min="0"
                                    v-model.number="settingsForm.download_speed_limit_kb"
                                    class="w-full h-10 rounded-xl bg-white/5 border border-white/15 px-3 text-xs text-white focus:border-cyan-500 outline-none font-mono"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                        <button
                            type="button"
                            @click="showSettingsModal = false"
                            class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-bold text-slate-300 cursor-pointer"
                        >
                            {{ t('common.close') }}
                        </button>
                        <button
                            type="button"
                            @click="handleSaveSettings"
                            :disabled="isSavingSettings"
                            class="px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs cursor-pointer shadow-lg shadow-cyan-500/20"
                        >
                            {{ isSavingSettings ? (isRTL ? 'جاري الحفظ...' : 'Saving...') : (isRTL ? 'حفظ التغييرات' : 'Save Settings') }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Explain Modal -->
        <transition name="fade">
            <div
                v-if="showExplainModal"
                class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4"
                @click.self="showExplainModal = false"
            >
                <div class="glass-panel rounded-3xl p-6 sm:p-8 max-w-lg w-full border border-cyan-500/30 shadow-2xl space-y-5">
                    <div class="flex items-center justify-between border-b border-white/10 pb-3">
                        <h3 class="font-extrabold text-base text-white flex items-center gap-2">
                            <Info class="w-5 h-5 text-cyan-400" />
                            <span>{{ isRTL ? 'دليل محرك التنزيل المدمج' : 'Downloader Engine Guide' }}</span>
                        </h3>
                    </div>

                    <div class="space-y-3 text-xs text-slate-300 leading-relaxed">
                        <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-1">
                            <span class="font-bold text-cyan-400 block">1. {{ isRTL ? 'فحص التورنت وشجرة المجلدات' : 'Torrent Inspection & Folder Tree' }}</span>
                            <p class="text-slate-400">{{ isRTL ? 'يقوم المحرك بقراءة بنية ملف التورنت بالكامل وتوليد شجرة المجلدات الحقيقية لتحديد أي مجلد أو ملف دون أخطاء.' : 'Inspects the full torrent hierarchy and renders real folder trees so you can accurately select specific folders or files.' }}</p>
                        </div>

                        <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-1">
                            <span class="font-bold text-indigo-400 block">2. {{ isRTL ? 'تنزيل حقيقي دون ملفات تالفة' : 'Direct BitTorrent & Stream Engine' }}</span>
                            <p class="text-slate-400">{{ isRTL ? 'يتم تنزيل المحتوى الفعلي عبر بروتوكول BitTorrent والتحميل المباشر وحفظ الملفات في المجلد المختار مباشرة دون تعديل أو تلف.' : 'Downloads real files with genuine chunk hashing directly into your destination directory without dummy placeholders or corruption.' }}</p>
                        </div>

                        <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-1">
                            <span class="font-bold text-emerald-400 block">3. {{ isRTL ? 'تنظيم وفهرسة اختيارية' : 'Optional Disk Organizer' }}</span>
                            <p class="text-slate-400">{{ isRTL ? 'تظل ملفاتك محتفظة بهيكلها كما هي في التورنت، ويمكنك استخدام منظم الأقراص لاحقاً لترتيبها ومطابقتها متى أردت.' : 'Your files stay in their original downloaded structure. You can use Disk Organizer at any time if you wish to reorganize them.' }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="button"
                            @click="showExplainModal = false"
                            class="px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-black cursor-pointer"
                        >
                            {{ isRTL ? 'فهمت ذلك' : 'Got it' }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </AppLayout>
</template>