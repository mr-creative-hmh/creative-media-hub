<script setup lang="ts">
import { ref, computed, watch, onMounted, defineAsyncComponent } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import { formatSpeed } from '@/lib/formatters';
import {
    useDownloader,
    DownloadItem,
    DownloaderInspection,
    TorrentFileItem,
    TorrentTreeNode,
} from '@/composables/useDownloader';
import AppLayout from '@/components/layout/AppLayout.vue';
import PageBanner from '@/components/common/PageBanner.vue';
import ClientPagination from '@/components/common/ClientPagination.vue';
import TorrentTreeItem from '@/components/downloader/TorrentTreeItem.vue';

const FastOrganizeModal = defineAsyncComponent(
    () => import('@/components/scout/FastOrganizeModal.vue'),
);
import {
    DownloadCloud,
    Plus,
    CheckCircle2,
    ArrowDown,
    ArrowUp,
    Play,
    Pause,
    Trash2,
    FolderSync,
    Sparkles,
    HelpCircle,
    Info,
    HardDrive,
    ShieldCheck,
    Film,
    Tv,
    Radio,
    Clock,
    RotateCcw,
    AlertTriangle,
    ExternalLink,
    Settings,
    Check,
    Folder,
    FolderOpen,
    FolderTree,
    ListFilter,
    Magnet,
    Zap,
    FileText,
    File,
    FileUp,
    CheckSquare,
    MinusSquare,
    Square,
    ChevronDown,
    ChevronUp,
    Copy,
    Eye,
    Layers,
    Activity,
    PlayCircle,
    FolderCheck,
} from '@lucide/vue';

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
    stopDownload,
    resumeDownload,
    retryDownload,
    organizeDownload,
    deleteDownload,
    daemonStatus,
    fetchDaemonStatus,
    stopDaemon,
} = useDownloader();

const activeFilter = ref<'all' | 'downloading' | 'paused' | 'completed'>('all');
const showAddModal = ref(false);
const showExplainModal = ref(false);
const showSettingsModal = ref(false);
const showFastOrganize = ref(false);

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
    default_download_path:
        props.settings?.default_download_path || 'H:\\Entertainment\\Downloads',
    movies_download_path:
        props.settings?.movies_download_path ||
        props.destinations?.movies ||
        'H:\\Entertainment\\Movies',
    series_download_path:
        props.settings?.series_download_path ||
        props.destinations?.series ||
        'H:\\Entertainment\\TV Shows',
    max_concurrent_downloads: props.settings?.max_concurrent_downloads || 3,
    download_speed_limit_kb: props.settings?.download_speed_limit_kb || 0,
});
const isSavingSettings = ref(false);

onMounted(() => {
    fetchDaemonStatus();
    if (
        props.initialDownloads &&
        props.initialDownloads.length > 0 &&
        downloads.value.length === 0
    ) {
        downloads.value = props.initialDownloads;
    }
    fetchDownloads();
});

const filteredItems = computed(() => {
    if (activeFilter.value === 'all') return downloads.value;
    if (activeFilter.value === 'downloading') {
        return downloads.value.filter(
            (d) => d.status === 'downloading' || d.status === 'queued',
        );
    }
    return downloads.value.filter((d) => d.status === activeFilter.value);
});

const currentPage = ref(1);
const perPage = ref(10);

watch(activeFilter, () => {
    currentPage.value = 1;
});

const paginatedDownloads = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return filteredItems.value.slice(start, start + perPage.value);
});

const defaultTargetFolder = computed(() => {
    if (newType.value === 'series') {
        return (
            settingsForm.value.series_download_path ||
            props.destinations?.series ||
            'H:\\Entertainment\\TV Shows'
        );
    }
    return (
        settingsForm.value.movies_download_path ||
        props.destinations?.movies ||
        'H:\\Entertainment\\Movies'
    );
});

const effectiveFolder = computed(() => {
    if (destinationType.value === 'custom' && customFolder.value.trim()) {
        return customFolder.value.trim();
    }
    return defaultTargetFolder.value;
});

// Expandable Details State & Diagnostics Actions
const expandedDetails = ref<Record<number, boolean>>({});
const copiedHash = ref<string | null>(null);

function toggleDetails(id: number) {
    expandedDetails.value[id] = !expandedDetails.value[id];
}

async function copyInfoHash(hash: string) {
    try {
        await navigator.clipboard.writeText(hash);
        copiedHash.value = hash;
        setTimeout(() => {
            if (copiedHash.value === hash) copiedHash.value = null;
        }, 2500);
        toast.success(
            isRTL.value
                ? 'تم نسخ InfoHash بنجاح'
                : 'InfoHash copied to clipboard',
        );
    } catch {
        // fallback
    }
}

const STAGES = [
    'downloading',
    'verifying',
    'organizing',
    'scanning',
    'ready',
] as const;

function getStageIndex(stage?: string | null): number {
    if (!stage) return 0;
    const idx = STAGES.indexOf(stage as any);
    return idx >= 0 ? idx : 0;
}

function getStageState(
    item: DownloadItem,
    stage: (typeof STAGES)[number],
): 'completed' | 'active' | 'pending' | 'failed' {
    if (item.status === 'failed') {
        return item.workflow_stage === stage
            ? 'failed'
            : getStageIndex(item.workflow_stage) > getStageIndex(stage)
              ? 'completed'
              : 'pending';
    }

    if (
        item.workflow_stage === 'ready' ||
        (item.status === 'completed' && !item.workflow_stage)
    ) {
        return 'completed';
    }

    const currentIdx = getStageIndex(item.workflow_stage || 'downloading');
    const stageIdx = getStageIndex(stage);

    if (stageIdx < currentIdx) return 'completed';
    if (stageIdx === currentIdx)
        return item.status === 'downloading' || item.status === 'completed'
            ? 'active'
            : 'pending';
    return 'pending';
}

function getItemWatchUrl(item: DownloadItem): string | null {
    if (!item.indexed_id) return null;
    return item.media_type === 'series'
        ? `/series/${item.indexed_id}`
        : `/movies/${item.indexed_id}`;
}

const organizingIds = ref<Record<number, boolean>>({});

const handleOrganize = async (item: DownloadItem) => {
    organizingIds.value[item.id] = true;
    try {
        const res = await organizeDownload(item.id);
        if (
            res &&
            (res.success ||
                res.workflow_stage === 'ready' ||
                res.organize_status === 'completed')
        ) {
            toast.success(
                isRTL.value
                    ? 'تم تنظيم وفهرسة الملف في المكتبة بنجاح!'
                    : 'Successfully organized and indexed into library!',
            );
        } else {
            toast.error(
                res?.error ||
                    (isRTL.value
                        ? 'تعذر تنظيم الملف'
                        : 'Could not organize media'),
            );
        }
    } catch (e: any) {
        toast.error(e.message || 'Organize error');
    } finally {
        organizingIds.value[item.id] = false;
        await fetchDownloads();
    }
};

const handleStop = async (item: DownloadItem) => {
    await stopDownload(item.id);
    await fetchDownloads();
    toast.info(isRTL.value ? 'تم إيقاف التنزيل' : 'Download stopped');
};

const getItemFiles = (item: DownloadItem) => {
    const tf = item.torrent_files;
    let rawFiles: any[] = [];
    if (
        tf &&
        Array.isArray(tf.detailed_files) &&
        tf.detailed_files.length > 0
    ) {
        rawFiles = tf.detailed_files;
    } else if (tf && Array.isArray(tf.files) && tf.files.length > 0) {
        rawFiles = tf.files;
    } else if (Array.isArray(tf) && tf.length > 0) {
        rawFiles = tf;
    }

    if (rawFiles.length === 0) {
        const total = item.total_bytes || 0;
        const downloaded =
            item.status === 'completed' ? total : item.downloaded_bytes || 0;
        const pct =
            item.status === 'completed'
                ? 100
                : total > 0
                  ? Math.min(100, Math.round((downloaded / total) * 100))
                  : 0;
        const fileName = item.destination_path
            ? item.destination_path.split(/[\\/]/).pop()
            : item.title || 'Media File';

        return [
            {
                index: 1,
                path: fileName || 'Media File',
                name: fileName || 'Media File',
                length: total,
                completedLength: downloaded,
                percent: pct,
                selected: true,
            },
        ];
    }

    return rawFiles.map((f: any, idx: number) => {
        const length = Number(f.length ?? f.size ?? 0);
        let completedLength = Number(
            f.completedLength ?? f.completed_length ?? 0,
        );
        if (
            item.status === 'completed' &&
            completedLength === 0 &&
            length > 0
        ) {
            completedLength = length;
        }
        let percent = f.percent ?? f.progress_percent;
        if (percent === undefined || percent === null) {
            percent =
                length > 0
                    ? Math.min(
                          100,
                          Math.round((completedLength / length) * 100),
                      )
                    : item.status === 'completed'
                      ? 100
                      : 0;
        }
        const filePath = f.path || f.name || f.filename || `File ${idx + 1}`;
        const fileName =
            f.name ||
            f.filename ||
            filePath.split(/[\\/]/).pop() ||
            `File ${idx + 1}`;

        return {
            index: f.index ?? idx + 1,
            path: filePath,
            name: fileName,
            length,
            completedLength,
            percent: Math.min(100, Math.max(0, Number(percent) || 0)),
            selected: f.selected !== false,
        };
    });
};

const getItemLogs = (item: DownloadItem) => {
    const tfLogs = item.torrent_files?.logs;
    if (Array.isArray(tfLogs) && tfLogs.length > 0) {
        return tfLogs;
    }
    const synthetic: any[] = [];
    if (item.created_at) {
        synthetic.push({
            time: new Date(item.created_at).toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            }),
            stage: 'download',
            message: 'Task created and queued for downloading.',
            level: 'info',
        });
    }
    if (item.downloaded_bytes > 0) {
        synthetic.push({
            time: item.updated_at
                ? new Date(item.updated_at).toLocaleTimeString([], {
                      hour: '2-digit',
                      minute: '2-digit',
                      second: '2-digit',
                  })
                : '--:--',
            stage: 'download',
            message:
                item.status === 'completed'
                    ? `Download payload finished (${formatBytes(item.total_bytes || item.downloaded_bytes)}).`
                    : `Downloading (${formatBytes(item.downloaded_bytes)} / ${formatBytes(item.total_bytes)}).`,
            level: item.status === 'completed' ? 'success' : 'info',
        });
    }
    if (item.organized_path) {
        synthetic.push({
            time: '--:--',
            stage: 'organize',
            message: `Organized and moved to: ${item.organized_path}`,
            level: 'success',
        });
    }
    if (item.indexed_id) {
        synthetic.push({
            time: '--:--',
            stage: 'scan',
            message: `Scanned & indexed into Virtual Library (MediaItem #${item.indexed_id}).`,
            level: 'success',
        });
    }
    if (item.workflow_stage === 'ready') {
        synthetic.push({
            time: '--:--',
            stage: 'ready',
            message: 'Media item is ready to stream!',
            level: 'success',
        });
    } else if (item.error_details) {
        synthetic.push({
            time: '--:--',
            stage: 'error',
            message: item.error_details,
            level: 'error',
        });
    }
    return synthetic;
};

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
                isRTL.value
                    ? 'تم فحص ملف التورنت وقراءة هيكل المجلدات بنجاح!'
                    : 'Torrent file inspected and folder hierarchy parsed successfully!',
                isRTL.value ? 'فحص ناجح' : 'Inspected',
            );
        } else {
            toast.error(
                isRTL.value
                    ? 'تعذر قراءة ملف التورنت. يرجى التأكد من سلامة الملف.'
                    : 'Failed to parse torrent file. Please check file integrity.',
                isRTL.value ? 'خطأ' : 'Error',
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
        .filter((f) => f.is_video || f.selected)
        .map((f) => f.index);

    if (videoIndexes.length > 0) {
        selectedFileIndexes.value = videoIndexes;
    } else if (res.files.length > 0) {
        // Fallback: select all files
        selectedFileIndexes.value = res.files.map((f) => f.index);
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
    selectedFileIndexes.value = inspectionData.value.files.map((f) => f.index);
};

const selectVideosOnly = () => {
    if (!inspectionData.value) return;
    const vids = inspectionData.value.files
        .filter((f) => f.is_video)
        .map((f) => f.index);
    selectedFileIndexes.value =
        vids.length > 0 ? vids : inspectionData.value.files.map((f) => f.index);
};

const clearAllFiles = () => {
    selectedFileIndexes.value = [];
};

const toggleFileSelection = (idx: number) => {
    if (selectedFileIndexes.value.includes(idx)) {
        selectedFileIndexes.value = selectedFileIndexes.value.filter(
            (i) => i !== idx,
        );
    } else {
        selectedFileIndexes.value.push(idx);
    }
};

const handleToggleFolder = (indexes: number[], targetState: boolean) => {
    if (targetState) {
        const toAdd = indexes.filter(
            (i) => !selectedFileIndexes.value.includes(i),
        );
        selectedFileIndexes.value = [...selectedFileIndexes.value, ...toAdd];
    } else {
        selectedFileIndexes.value = selectedFileIndexes.value.filter(
            (i) => !indexes.includes(i),
        );
    }
};

const selectedTotalBytes = computed(() => {
    if (!inspectionData.value) return 0;
    return inspectionData.value.files
        .filter((f) => selectedFileIndexes.value.includes(f.index))
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
                isRTL.value
                    ? 'تم حفظ إعدادات التنزيل بنجاح!'
                    : 'Downloader settings saved successfully!',
                isRTL.value ? 'تم الحفظ' : 'Saved',
            );
            showSettingsModal.value = false;
        }
    } finally {
        isSavingSettings.value = false;
    }
};
</script>

<template>
    <AppLayout v-slot="{ play }">
        <Head :title="t('nav.downloads')" />
        <!-- Top Header & Metrics -->
        <PageBanner
            :icon="DownloadCloud"
            :title="t('nav.downloads')"
            :description="
                isRTL
                    ? 'إدارة التنزيلات الحقيقية في الخلفية، ملفات التورنت المحددة بشجرة المجلدات، ومجلدات الوجهة المخصصة.'
                    : 'High-speed background downloads, selective folder tree torrents, and custom destination folders.'
            "
            class="mb-8"
        >
            <template #meta>
                <!-- Daemon Engine Status Pill -->
                <div
                    v-if="daemonStatus"
                    class="cf-glass inline-flex items-center gap-2 rounded-full border px-3 py-1 font-mono text-xs tabular-nums"
                    :class="
                        daemonStatus.running
                            ? 'border-primary/40 bg-primary/10 text-primary'
                            : 'border-border/80 text-muted-foreground'
                    "
                >
                    <span
                        class="size-2 rounded-full"
                        :class="
                            daemonStatus.running
                                ? 'bg-primary animate-pulse'
                                : 'bg-muted-foreground'
                        "
                    ></span>
                    <span class="font-sans font-bold">{{
                        isRTL ? 'محرك التنزيل:' : 'Engine:'
                    }}</span>
                    <span>{{
                        daemonStatus.running
                            ? daemonStatus.pids?.length > 0
                                ? `PID ${daemonStatus.pids[0]}`
                                : 'Active'
                            : isRTL
                              ? 'خامل (متوقف)'
                              : 'Idle'
                    }}</span>
                    <button
                        v-if="
                            daemonStatus.running && activeDownloads.length === 0
                        "
                        type="button"
                        @click="stopDaemon"
                        class="ms-1 cursor-pointer rounded-full border border-red-500/40 bg-red-500/20 px-2 py-0.5 text-[10px] font-bold text-red-300 transition-all hover:bg-red-500/30"
                        :title="
                            isRTL
                                ? 'إيقاف المحرك وتحرير الذاكرة'
                                : 'Stop Engine & Free RAM'
                        "
                    >
                        {{ isRTL ? 'إيقاف' : 'Stop' }}
                    </button>
                </div>
            </template>

            <template #actions>
                <!-- Primary CTA: Add Download -->
                <button
                    type="button"
                    @click="showAddModal = true"
                    class="bg-primary text-primary-foreground shadow-primary/25 flex min-h-10 cursor-pointer items-center gap-2 rounded-xl px-5 py-2.5 text-xs font-black shadow-lg transition-all hover:brightness-105 active:scale-95"
                >
                    <Plus class="size-4" />
                    <span>{{
                        isRTL ? 'إضافة تنزيل / تورنت' : 'Add Download'
                    }}</span>
                </button>

                <!-- Secondary CTA: Organize & Scan -->
                <button
                    type="button"
                    @click="showFastOrganize = true"
                    class="cf-glass flex min-h-10 cursor-pointer items-center gap-2 rounded-xl border border-emerald-500/35 px-3.5 py-2.5 text-xs font-bold text-emerald-300 shadow-sm transition-all hover:border-emerald-400/60 hover:bg-emerald-500/15 active:scale-95"
                    :title="t('scout.organize_and_scan_help')"
                >
                    <FolderSync class="size-4 text-emerald-400" />
                    <span>{{ t('scout.organize_and_scan') }}</span>
                </button>

                <!-- Utility Icon Button: Settings -->
                <button
                    type="button"
                    @click="showSettingsModal = true"
                    class="cf-glass border-border/80 text-foreground hover:border-primary/40 flex size-10 cursor-pointer items-center justify-center rounded-xl border shadow-sm transition-all active:scale-95"
                    :title="isRTL ? 'إعدادات التنزيل' : 'Downloader Settings'"
                    :aria-label="
                        isRTL ? 'إعدادات التنزيل' : 'Downloader Settings'
                    "
                >
                    <Settings class="text-primary size-4" />
                </button>

                <!-- Utility Icon Button: Guide -->
                <button
                    type="button"
                    @click="showExplainModal = true"
                    class="cf-glass border-border/80 text-foreground hover:border-primary/40 flex size-10 cursor-pointer items-center justify-center rounded-xl border shadow-sm transition-all active:scale-95"
                    :title="isRTL ? 'دليل المحرك' : 'Engine Guide'"
                    :aria-label="isRTL ? 'دليل المحرك' : 'Engine Guide'"
                >
                    <HelpCircle class="text-primary size-4" />
                </button>
            </template>
        </PageBanner>

        <!-- Real-Time Bandwidth & Queue Metrics Bar -->
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div
                class="cf-glass border-primary/30 bg-primary/5 flex items-center justify-between rounded-2xl border p-4 tabular-nums"
            >
                <div>
                    <span
                        class="text-muted-foreground block text-[11px] font-bold tracking-wider uppercase"
                        >{{ isRTL ? 'سرعة التنزيل' : 'Download Speed' }}</span
                    >
                    <span
                        class="text-primary mt-1 block font-mono text-xl font-black"
                        >{{ totalSpeedDownFormatted }}</span
                    >
                </div>
                <div
                    class="bg-primary/15 text-primary flex size-9 items-center justify-center rounded-xl"
                >
                    <ArrowDown class="size-4" />
                </div>
            </div>

            <div
                class="cf-glass border-border/80 flex items-center justify-between rounded-2xl border p-4 tabular-nums"
            >
                <div>
                    <span
                        class="text-muted-foreground block text-[11px] font-bold tracking-wider uppercase"
                        >{{
                            isRTL ? 'سرعة الرفع / التغذية' : 'Seeding Speed'
                        }}</span
                    >
                    <span
                        class="text-foreground mt-1 block font-mono text-xl font-black"
                        >{{ totalSpeedUpFormatted }}</span
                    >
                </div>
                <div
                    class="bg-primary/15 text-primary flex size-9 items-center justify-center rounded-xl"
                >
                    <ArrowUp class="size-4" />
                </div>
            </div>

            <div
                class="cf-glass flex items-center justify-between rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4 tabular-nums"
            >
                <div>
                    <span
                        class="text-muted-foreground block text-[11px] font-bold tracking-wider uppercase"
                        >{{ isRTL ? 'المهام النشطة' : 'Active Jobs' }}</span
                    >
                    <span
                        class="mt-1 block font-mono text-xl font-black text-emerald-400"
                        >{{ activeDownloads.length }}</span
                    >
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-400"
                >
                    <Radio class="size-4" />
                </div>
            </div>

            <div
                class="cf-glass border-border/80 flex items-center justify-between rounded-2xl border p-4 tabular-nums"
            >
                <div>
                    <span
                        class="text-muted-foreground block text-[11px] font-bold tracking-wider uppercase"
                        >{{ isRTL ? 'مكتملة ومفهرسة' : 'Completed' }}</span
                    >
                    <span
                        class="text-foreground mt-1 block font-mono text-xl font-black"
                        >{{ completedDownloads.length }}</span
                    >
                </div>
                <div
                    class="bg-muted text-foreground flex size-9 items-center justify-center rounded-xl"
                >
                    <CheckCircle2 class="size-4" />
                </div>
            </div>
        </div>

        <!-- Filter Pills Bar -->
        <div class="mb-6 flex items-center gap-2 overflow-x-auto pb-2">
            <button
                v-for="filter in [
                    { id: 'all', label_en: 'All Downloads', label_ar: 'الكل' },
                    {
                        id: 'downloading',
                        label_en: 'Downloading',
                        label_ar: 'قيد التنزيل',
                    },
                    { id: 'paused', label_en: 'Paused', label_ar: 'متوقف' },
                    {
                        id: 'completed',
                        label_en: 'Completed',
                        label_ar: 'المكتملة',
                    },
                ]"
                :key="filter.id"
                type="button"
                @click="activeFilter = filter.id as any"
                class="min-h-10 shrink-0 cursor-pointer rounded-full border px-4 py-2 text-xs font-bold transition-all active:scale-95"
                :class="
                    activeFilter === filter.id
                        ? 'border-primary bg-primary text-primary-foreground shadow-primary/25 font-black shadow-md'
                        : 'cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground'
                "
            >
                {{ isRTL ? filter.label_ar : filter.label_en }}
            </button>
        </div>

        <!-- Downloads Table / Cards -->
        <div
            v-if="filteredItems.length === 0"
            class="cf-glass border-border/80 space-y-4 rounded-3xl border p-12 text-center"
        >
            <DownloadCloud class="text-muted-foreground mx-auto size-12" />
            <h3 class="text-foreground text-base font-bold">
                {{
                    isRTL
                        ? 'لا توجد تنزيلات في هذه القائمة'
                        : 'No downloads in this view'
                }}
            </h3>
            <p class="text-muted-foreground mx-auto max-w-sm text-xs">
                {{
                    isRTL
                        ? 'أضف رابط تورنت أو ملف .torrent أو تحميل مباشر لبدء تنزيل المحتوى بدقة دون أي تلف.'
                        : 'Add a torrent file, magnet link, or direct stream to start downloading directly without corruption.'
                }}
            </p>
        </div>

        <div v-else class="space-y-4">
            <div
                v-for="item in paginatedDownloads"
                :key="item.id"
                class="cf-glass border-border/80 hover:border-primary/45 group relative space-y-4 overflow-hidden rounded-2xl border p-5 tabular-nums transition-all"
            >
                <!-- Top Row: Icon, Title, Status Badges & Action Buttons -->
                <div
                    class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3.5">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border shadow-inner"
                            :class="
                                item.media_type === 'series'
                                    ? 'border-indigo-500/30 bg-indigo-500/10 text-indigo-400'
                                    : 'border-cyan-500/30 bg-cyan-500/10 text-cyan-400'
                            "
                        >
                            <Magnet
                                v-if="item.download_type === 'torrent'"
                                class="h-5 w-5 text-purple-400"
                            />
                            <Tv
                                v-else-if="item.media_type === 'series'"
                                class="h-5 w-5"
                            />
                            <Film v-else class="h-5 w-5" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                <h4
                                    class="max-w-lg truncate text-sm font-extrabold text-white sm:text-base"
                                    :title="item.title"
                                >
                                    {{ item.title }}
                                </h4>
                                <span
                                    class="rounded-full px-2 py-0.5 font-mono text-[10px] font-bold tracking-wider uppercase"
                                    :class="{
                                        'border border-cyan-500/30 bg-cyan-500/20 text-cyan-300':
                                            item.status === 'downloading',
                                        'border border-emerald-500/30 bg-emerald-500/20 text-emerald-300':
                                            item.status === 'completed',
                                        'border border-amber-500/30 bg-amber-500/20 text-amber-300':
                                            item.status === 'paused',
                                        'border border-blue-500/30 bg-blue-500/20 text-blue-300':
                                            item.status === 'queued',
                                        'border border-rose-500/30 bg-rose-500/20 text-rose-300':
                                            item.status === 'failed',
                                    }"
                                >
                                    {{ item.status }}
                                </span>
                                <span
                                    v-if="item.download_type === 'torrent'"
                                    class="rounded-md border border-purple-500/30 bg-purple-500/20 px-2 py-0.5 font-mono text-[10px] font-bold text-purple-300"
                                >
                                    🧲 Torrent
                                </span>

                                <!-- Live Seeder / Peer Pills -->
                                <span
                                    v-if="
                                        item.download_type === 'torrent' &&
                                        (item.status === 'downloading' ||
                                            item.status === 'paused')
                                    "
                                    class="flex items-center gap-1 rounded-md border px-2 py-0.5 font-mono text-[10px] font-bold"
                                    :class="
                                        (item.num_seeders || 0) > 0
                                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
                                            : 'border-slate-700 bg-slate-800 text-slate-400'
                                    "
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            (item.num_seeders || 0) > 0
                                                ? 'animate-pulse bg-emerald-400'
                                                : 'bg-slate-500'
                                        "
                                    ></span>
                                    <span
                                        >{{ item.num_seeders || 0 }}
                                        {{
                                            isRTL ? 'موزع (Seeds)' : 'seeds'
                                        }}</span
                                    >
                                </span>

                                <span
                                    v-if="
                                        item.download_type === 'torrent' &&
                                        (item.status === 'downloading' ||
                                            item.status === 'paused')
                                    "
                                    class="flex items-center gap-1 rounded-md border px-2 py-0.5 font-mono text-[10px] font-bold"
                                    :class="
                                        (item.connections || 0) > 0
                                            ? 'border-cyan-500/30 bg-cyan-500/10 text-cyan-300'
                                            : 'border-slate-700 bg-slate-800 text-slate-400'
                                    "
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            (item.connections || 0) > 0
                                                ? 'bg-cyan-400'
                                                : 'bg-slate-500'
                                        "
                                    ></span>
                                    <span
                                        >{{ item.connections || 0 }}
                                        {{
                                            isRTL ? 'متصل (Peers)' : 'peers'
                                        }}</span
                                    >
                                </span>
                            </div>

                            <!-- Progress Bar & Specs -->
                            <div class="max-w-2xl space-y-1.5">
                                <div
                                    class="bg-muted h-2 w-full overflow-hidden rounded-full shadow-inner"
                                >
                                    <div
                                        class="relative h-full rounded-full transition-all duration-300"
                                        :class="
                                            item.status === 'completed'
                                                ? 'bg-emerald-400 shadow-sm shadow-emerald-400/50'
                                                : 'bg-primary'
                                        "
                                        :style="{
                                            width: `${getProgressPercent(item)}%`,
                                        }"
                                    ></div>
                                </div>

                                <div
                                    class="flex flex-wrap items-center gap-3 font-mono text-[11px] text-slate-400"
                                >
                                    <span
                                        >{{
                                            formatBytes(
                                                item.status === 'completed'
                                                    ? item.downloaded_bytes ||
                                                          item.total_bytes
                                                    : item.downloaded_bytes,
                                            )
                                        }}
                                        /
                                        {{ formatBytes(item.total_bytes) }} ({{
                                            getProgressPercent(item)
                                        }}%)</span
                                    >
                                    <span>•</span>
                                    <span
                                        v-if="item.status === 'downloading'"
                                        class="font-bold text-cyan-300"
                                    >
                                        ⚡ ⬇
                                        {{ formatSpeed(item.speed_bytes_sec) }}
                                    </span>
                                    <span
                                        v-if="
                                            item.status === 'downloading' &&
                                            (item.upload_speed_bytes_sec || 0) >
                                                0
                                        "
                                        class="text-indigo-300"
                                    >
                                        ⬆
                                        {{
                                            formatSpeed(
                                                item.upload_speed_bytes_sec ||
                                                    0,
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-if="item.status === 'downloading'"
                                        class="text-slate-300"
                                        >ETA: {{ getETA(item) }}</span
                                    >
                                    <span
                                        v-if="
                                            item.destination_folder ||
                                            item.destination_path
                                        "
                                        class="max-w-xs truncate text-[10px] text-slate-500"
                                        :title="
                                            item.destination_folder ||
                                            item.destination_path
                                        "
                                    >
                                        📁
                                        {{
                                            item.destination_folder ||
                                            item.destination_path
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons & Drawer Toggle -->
                    <div
                        class="flex shrink-0 items-center gap-2 self-end md:self-center"
                    >
                        <Link
                            v-if="
                                (item.workflow_stage === 'ready' ||
                                    item.status === 'completed') &&
                                getItemWatchUrl(item)
                            "
                            :href="getItemWatchUrl(item)!"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-emerald-500 px-3 py-1.5 text-xs font-black text-slate-950 shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-400"
                        >
                            <PlayCircle class="h-4 w-4" />
                            <span>{{ isRTL ? 'شاهد الآن' : 'Watch Now' }}</span>
                        </Link>

                        <button
                            v-if="
                                (item.status === 'completed' ||
                                    item.workflow_stage === 'failed' ||
                                    (!item.organized_path &&
                                        item.downloaded_bytes > 0)) &&
                                !getItemWatchUrl(item)
                            "
                            @click="handleOrganize(item)"
                            :disabled="organizingIds[item.id]"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-cyan-500/40 bg-cyan-500/20 px-3 py-1.5 text-xs font-bold text-cyan-300 transition-all hover:bg-cyan-500/30 disabled:opacity-50"
                            :title="
                                isRTL
                                    ? 'تنظيم وفهرسة في المكتبة الآن'
                                    : 'Organize and Scan into Library Now'
                            "
                        >
                            <FolderCheck
                                class="h-4 w-4"
                                :class="{
                                    'animate-spin': organizingIds[item.id],
                                }"
                            />
                            <span>{{
                                organizingIds[item.id]
                                    ? isRTL
                                        ? 'جاري التنظيم...'
                                        : 'Organizing...'
                                    : isRTL
                                      ? 'تنظيم وفهرسة'
                                      : 'Organize & Scan'
                            }}</span>
                        </button>

                        <button
                            v-if="item.status === 'downloading'"
                            @click="pauseDownload(item.id)"
                            class="cursor-pointer rounded-xl border border-white/10 bg-white/5 p-2 text-slate-300 transition-all hover:border-amber-500/40 hover:bg-amber-500/20 hover:text-amber-300"
                            title="Pause"
                        >
                            <Pause class="h-4 w-4" />
                        </button>

                        <button
                            v-if="
                                item.status === 'downloading' ||
                                item.status === 'paused'
                            "
                            @click="handleStop(item)"
                            class="cursor-pointer rounded-xl border border-white/10 bg-white/5 p-2 text-slate-300 transition-all hover:border-rose-500/40 hover:bg-rose-500/20 hover:text-rose-300"
                            title="Stop"
                        >
                            <Square class="h-4 w-4" />
                        </button>

                        <button
                            v-if="item.status === 'paused'"
                            @click="resumeDownload(item.id)"
                            class="cursor-pointer rounded-xl border border-white/10 bg-white/5 p-2 text-slate-300 transition-all hover:border-cyan-500/40 hover:bg-cyan-500/20 hover:text-cyan-300"
                            title="Resume"
                        >
                            <Play class="h-4 w-4" />
                        </button>

                        <button
                            v-if="item.status === 'failed'"
                            @click="retryDownload(item.id)"
                            class="cursor-pointer rounded-xl border border-white/10 bg-white/5 p-2 text-slate-300 transition-all hover:border-cyan-500/40 hover:bg-cyan-500/20 hover:text-cyan-300"
                            title="Retry"
                        >
                            <RotateCcw class="h-4 w-4" />
                        </button>

                        <!-- Details Drawer Toggle -->
                        <button
                            @click="toggleDetails(item.id)"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-white/10 bg-white/5 px-2.5 py-1.5 text-xs font-bold text-slate-300 transition-all hover:bg-white/10"
                            :title="
                                isRTL
                                    ? 'عرض تفاصيل التورنت والملفات'
                                    : 'Toggle Torrent Details & Files'
                            "
                        >
                            <Layers class="h-3.5 w-3.5 text-cyan-400" />
                            <span class="hidden sm:inline">{{
                                isRTL ? 'التفاصيل' : 'Details'
                            }}</span>
                            <ChevronDown
                                v-if="!expandedDetails[item.id]"
                                class="h-3.5 w-3.5 text-slate-400"
                            />
                            <ChevronUp
                                v-else
                                class="h-3.5 w-3.5 text-cyan-400"
                            />
                        </button>

                        <button
                            @click="deleteDownload(item.id)"
                            class="cursor-pointer rounded-xl border border-white/10 bg-white/5 p-2 text-slate-400 transition-all hover:border-rose-500/40 hover:bg-rose-500/20 hover:text-rose-400"
                            title="Delete"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- 5-Stage Post-Download Workflow Stepper -->
                <div class="border-t border-white/5 pt-2">
                    <div
                        class="flex items-center justify-between gap-1 overflow-x-auto py-1 sm:gap-2"
                    >
                        <div
                            v-for="(stageKey, idx) in STAGES"
                            :key="stageKey"
                            class="flex min-w-[90px] flex-1 items-center gap-1.5 sm:min-w-[110px]"
                        >
                            <div class="flex flex-1 items-center gap-1.5">
                                <!-- Stage Badge -->
                                <div
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full font-mono text-[11px] font-bold transition-all"
                                    :class="{
                                        'bg-emerald-500 text-slate-950 ring-2 ring-emerald-500/30':
                                            getStageState(item, stageKey) ===
                                            'completed',
                                        'animate-pulse bg-cyan-500 text-slate-950 ring-2 ring-cyan-500/40':
                                            getStageState(item, stageKey) ===
                                            'active',
                                        'border border-slate-700 bg-slate-800 text-slate-500':
                                            getStageState(item, stageKey) ===
                                            'pending',
                                        'bg-rose-500 text-white':
                                            getStageState(item, stageKey) ===
                                            'failed',
                                    }"
                                >
                                    <Check
                                        v-if="
                                            getStageState(item, stageKey) ===
                                            'completed'
                                        "
                                        class="h-3.5 w-3.5 stroke-[3]"
                                    />
                                    <span v-else>{{ idx + 1 }}</span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-[11px] font-bold capitalize"
                                        :class="{
                                            'text-emerald-300':
                                                getStageState(
                                                    item,
                                                    stageKey,
                                                ) === 'completed',
                                            'font-black text-cyan-300':
                                                getStageState(
                                                    item,
                                                    stageKey,
                                                ) === 'active',
                                            'text-slate-500':
                                                getStageState(
                                                    item,
                                                    stageKey,
                                                ) === 'pending',
                                            'text-rose-400':
                                                getStageState(
                                                    item,
                                                    stageKey,
                                                ) === 'failed',
                                        }"
                                    >
                                        {{ stageKey }}
                                    </span>
                                </div>
                            </div>

                            <!-- Connector Line (except for last step) -->
                            <div
                                v-if="idx < STAGES.length - 1"
                                class="h-0.5 w-3 shrink-0 rounded-full transition-all sm:w-6"
                                :class="
                                    getStageState(item, STAGES[idx + 1]) ===
                                        'completed' ||
                                    getStageState(item, STAGES[idx]) ===
                                        'completed'
                                        ? 'bg-emerald-500/50'
                                        : 'bg-slate-800'
                                "
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- Stalled / Error Warning Banner -->
                <div
                    v-if="
                        item.error_details &&
                        item.error_details.includes('Stalled')
                    "
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-300"
                >
                    <div class="flex items-center gap-2">
                        <AlertTriangle
                            class="h-4 w-4 shrink-0 text-amber-400"
                        />
                        <span>{{ item.error_details }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="retryDownload(item.id)"
                            class="cursor-pointer rounded-lg border border-amber-500/40 bg-amber-500/20 px-2.5 py-1 text-[11px] font-bold text-amber-200 transition-all hover:bg-amber-500/30"
                        >
                            {{
                                isRTL ? 'إعادة الفحص والاتصال' : 'Force Re-check'
                            }}
                        </button>
                    </div>
                </div>

                <!-- Expandable Torrent Details Drawer -->
                <transition name="fade">
                    <div
                        v-if="expandedDetails[item.id]"
                        class="glass-panel space-y-4 rounded-xl border border-cyan-500/20 bg-slate-950/60 p-4 font-mono text-xs"
                    >
                        <!-- Metadata Grid -->
                        <div
                            class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4"
                        >
                            <div
                                v-if="item.info_hash"
                                class="rounded-lg border border-white/10 bg-white/5 p-2.5"
                            >
                                <span
                                    class="mb-1 block font-sans text-[10px] font-bold text-slate-400 uppercase"
                                    >InfoHash</span
                                >
                                <div
                                    class="flex items-center justify-between gap-1"
                                >
                                    <span
                                        class="truncate text-[11px] text-slate-300"
                                        :title="item.info_hash"
                                        >{{ item.info_hash }}</span
                                    >
                                    <button
                                        @click="copyInfoHash(item.info_hash)"
                                        class="shrink-0 cursor-pointer rounded bg-white/10 p-1 text-slate-300 hover:bg-cyan-500/20 hover:text-cyan-300"
                                        :title="
                                            isRTL ? 'نسخ Hash' : 'Copy Hash'
                                        "
                                    >
                                        <Check
                                            v-if="copiedHash === item.info_hash"
                                            class="h-3.5 w-3.5 text-emerald-400"
                                        />
                                        <Copy v-else class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="item.aria2_gid"
                                class="rounded-lg border border-white/10 bg-white/5 p-2.5"
                            >
                                <span
                                    class="mb-1 block font-sans text-[10px] font-bold text-slate-400 uppercase"
                                    >Aria2 GID</span
                                >
                                <span class="text-[11px] text-cyan-300">{{
                                    item.aria2_gid
                                }}</span>
                            </div>

                            <div
                                v-if="item.torrent_files?.piece_length"
                                class="rounded-lg border border-white/10 bg-white/5 p-2.5"
                            >
                                <span
                                    class="mb-1 block font-sans text-[10px] font-bold text-slate-400 uppercase"
                                    >{{
                                        isRTL
                                            ? 'حجم القطع (Pieces)'
                                            : 'Piece Size'
                                    }}</span
                                >
                                <span class="text-[11px] text-slate-300">
                                    {{
                                        formatBytes(
                                            item.torrent_files.piece_length,
                                        )
                                    }}
                                    • {{ item.torrent_files.num_pieces || 0 }}
                                    {{ isRTL ? 'قطعة' : 'pieces' }}
                                </span>
                            </div>

                            <div
                                v-if="item.organized_path"
                                class="rounded-lg border border-white/10 bg-white/5 p-2.5"
                            >
                                <span
                                    class="mb-1 block font-sans text-[10px] font-bold text-slate-400 uppercase"
                                    >{{
                                        isRTL
                                            ? 'المسار المنظم النهائي'
                                            : 'Organized Library Path'
                                    }}</span
                                >
                                <span
                                    class="block truncate text-[11px] text-emerald-300"
                                    :title="item.organized_path"
                                >
                                    {{ item.organized_path }}
                                </span>
                            </div>
                        </div>

                        <!-- Per-File Breakdown List -->
                        <div
                            v-if="getItemFiles(item).length > 0"
                            class="space-y-2"
                        >
                            <span
                                class="flex items-center gap-1.5 font-sans text-[11px] font-bold text-slate-300"
                            >
                                <FileText class="h-3.5 w-3.5 text-cyan-400" />
                                <span
                                    >{{
                                        isRTL
                                            ? 'ملفات التنزيل ونسب الإنجاز'
                                            : 'Download Payload Files Progress'
                                    }}
                                    ({{ getItemFiles(item).length }})</span
                                >
                            </span>

                            <div
                                class="custom-scrollbar max-h-56 space-y-1.5 overflow-y-auto pr-1"
                            >
                                <div
                                    v-for="file in getItemFiles(item)"
                                    :key="file.index"
                                    class="flex items-center justify-between gap-3 rounded-lg border border-white/5 bg-white/[0.03] p-2 text-[11px]"
                                >
                                    <div class="min-w-0 flex-1">
                                        <span
                                            class="block truncate font-mono text-slate-300"
                                            :title="file.path"
                                            >{{ file.name || file.path }}</span
                                        >
                                        <div
                                            class="mt-1 flex items-center gap-2"
                                        >
                                            <div
                                                class="h-1.5 w-28 overflow-hidden rounded-full bg-white/10"
                                            >
                                                <div
                                                    class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-emerald-400 transition-all duration-300"
                                                    :style="{
                                                        width: `${file.percent}%`,
                                                    }"
                                                ></div>
                                            </div>
                                            <span
                                                class="font-mono text-[10px] font-bold text-cyan-400"
                                                >{{ file.percent }}%</span
                                            >
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right font-mono">
                                        <span
                                            class="font-semibold text-slate-300"
                                            >{{
                                                formatBytes(
                                                    file.completedLength,
                                                )
                                            }}</span
                                        >
                                        <span class="text-slate-500"> / </span>
                                        <span class="text-slate-400">{{
                                            formatBytes(file.length)
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ongoing Pipeline Operations & Event Log -->
                        <div class="space-y-2 border-t border-white/5 pt-3">
                            <div class="flex items-center justify-between">
                                <span
                                    class="flex items-center gap-1.5 font-sans text-[11px] font-bold text-slate-300"
                                >
                                    <Activity
                                        class="h-3.5 w-3.5 text-emerald-400"
                                    />
                                    <span>{{
                                        isRTL
                                            ? 'سجل العمليات والمراحل النشطة'
                                            : 'Ongoing Pipeline Operations Log'
                                    }}</span>
                                </span>
                                <span
                                    v-if="getItemLogs(item).length > 0"
                                    class="font-mono text-[10px] text-slate-500"
                                >
                                    {{ getItemLogs(item).length }}
                                    {{ isRTL ? 'حدث' : 'events' }}
                                </span>
                            </div>

                            <div
                                v-if="getItemLogs(item).length > 0"
                                class="custom-scrollbar max-h-40 space-y-1.5 overflow-y-auto rounded-xl border border-white/5 bg-black/40 p-3 pr-1 font-mono text-[11px]"
                            >
                                <div
                                    v-for="(log, lIdx) in getItemLogs(item)"
                                    :key="lIdx"
                                    class="flex items-start gap-2 py-0.5 leading-relaxed"
                                >
                                    <span
                                        class="mt-0.5 shrink-0 text-[10px] text-slate-500"
                                        >{{ log.time }}</span
                                    >
                                    <span
                                        class="py-0.2 shrink-0 rounded px-1.5 text-[9px] font-bold uppercase"
                                        :class="{
                                            'border border-cyan-500/30 bg-cyan-500/20 text-cyan-300':
                                                log.stage === 'download',
                                            'border border-purple-500/30 bg-purple-500/20 text-purple-300':
                                                log.stage === 'verify',
                                            'border border-indigo-500/30 bg-indigo-500/20 text-indigo-300':
                                                log.stage === 'organize',
                                            'border border-amber-500/30 bg-amber-500/20 text-amber-300':
                                                log.stage === 'scan',
                                            'border border-emerald-500/30 bg-emerald-500/20 text-emerald-300':
                                                log.stage === 'ready',
                                            'border border-rose-500/30 bg-rose-500/20 text-rose-300':
                                                log.stage === 'error',
                                        }"
                                    >
                                        {{ log.stage }}
                                    </span>
                                    <span
                                        class="min-w-0 flex-1 break-words"
                                        :class="{
                                            'text-emerald-300':
                                                log.level === 'success',
                                            'text-rose-400':
                                                log.level === 'error',
                                            'text-amber-300':
                                                log.level === 'warning',
                                            'text-slate-300':
                                                !log.level ||
                                                log.level === 'info',
                                        }"
                                    >
                                        {{ log.message }}
                                    </span>
                                </div>
                            </div>
                            <div
                                v-else
                                class="rounded-lg bg-black/20 p-2.5 text-[11px] text-slate-500 italic"
                            >
                                {{
                                    isRTL
                                        ? 'لا توجد سجلات بعد لهذه العملية'
                                        : 'No recorded operations yet for this task.'
                                }}
                            </div>
                        </div>
                    </div>
                </transition>
            </div>

            <ClientPagination
                :current-page="currentPage"
                :total-items="filteredItems.length"
                :per-page="perPage"
                :per-page-options="[10, 20, 50]"
                :scroll-to-top="true"
                @update:current-page="currentPage = $event"
                @update:per-page="
                    perPage = $event;
                    currentPage = 1;
                "
            />
        </div>

        <!-- Add Download Modal with Torrent File Selection & Folder Settings -->
        <transition name="fade">
            <div
                v-if="showAddModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-md"
                @click.self="showAddModal = false"
            >
                <div
                    class="cf-glass-elevated border-border/80 flex max-h-[92vh] w-full max-w-2xl flex-col space-y-5 rounded-3xl border p-6 shadow-2xl sm:p-8"
                >
                    <div
                        class="border-border/70 flex items-center justify-between border-b pb-3"
                    >
                        <h3
                            class="text-foreground flex items-center gap-2 text-base font-extrabold"
                        >
                            <DownloadCloud class="text-primary size-5" />
                            <span>{{
                                isRTL
                                    ? 'إضافة تنزيل وتورنت بهيكل المجلدات'
                                    : 'Add Download / Torrent Job'
                            }}</span>
                        </h3>
                    </div>

                    <div
                        class="custom-scrollbar flex-1 space-y-4 overflow-y-auto pr-1"
                    >
                        <!-- Mode Selector: Direct vs Torrent -->
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-bold text-slate-400"
                                >{{
                                    isRTL ? 'نوع التنزيل' : 'Download Mode'
                                }}</label
                            >
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="downloadMode = 'torrent'"
                                    class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-xs font-bold transition-all"
                                    :class="
                                        downloadMode === 'torrent'
                                            ? 'border-purple-400 bg-purple-600 text-white shadow-md shadow-purple-600/30'
                                            : 'border-white/10 bg-white/5 text-slate-400 hover:text-white'
                                    "
                                >
                                    <Magnet class="h-4 w-4" />
                                    <span
                                        >🧲
                                        {{
                                            isRTL
                                                ? 'تورنت / رابط Magnet / ملف .torrent'
                                                : 'Torrent / Magnet / File'
                                        }}</span
                                    >
                                </button>
                                <button
                                    type="button"
                                    @click="downloadMode = 'direct'"
                                    class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-xs font-bold transition-all"
                                    :class="
                                        downloadMode === 'direct'
                                            ? 'border-cyan-400 bg-cyan-500 font-black text-slate-950 shadow-md shadow-cyan-500/30'
                                            : 'border-white/10 bg-white/5 text-slate-400 hover:text-white'
                                    "
                                >
                                    <Zap class="h-4 w-4" />
                                    <span
                                        >⚡
                                        {{
                                            isRTL
                                                ? 'تحميل مباشر (HTTP/HTTPS)'
                                                : 'Direct HTTP / Stream'
                                        }}</span
                                    >
                                </button>
                            </div>
                        </div>

                        <!-- URL or File Input -->
                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <label class="text-xs font-bold text-slate-400">
                                    {{
                                        downloadMode === 'torrent'
                                            ? isRTL
                                                ? 'رابط Magnet أو مسار .torrent'
                                                : 'Magnet Link or Torrent File URL'
                                            : isRTL
                                              ? 'رابط الملف المباشر'
                                              : 'Direct File URL'
                                    }}
                                </label>
                                <span
                                    v-if="isInspecting || isUploadingTorrent"
                                    class="flex items-center gap-1 font-mono text-[11px] text-cyan-400"
                                >
                                    <Clock class="h-3 w-3 animate-spin" />
                                    {{
                                        isRTL
                                            ? 'جاري فحص الملفات...'
                                            : 'Inspecting files...'
                                    }}
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
                                    :placeholder="
                                        downloadMode === 'torrent'
                                            ? 'magnet:?xt=urn:btih:... or https://.../file.torrent'
                                            : 'https://example.com/movie.mp4'
                                    "
                                    class="h-11 flex-1 rounded-xl border border-white/15 bg-white/[0.04] px-4 font-mono text-xs text-cyan-300 outline-none focus:border-cyan-500"
                                />

                                <button
                                    type="button"
                                    @click="handleInspectUrl"
                                    :disabled="isInspecting || !newUrl.trim()"
                                    class="flex h-11 shrink-0 cursor-pointer items-center gap-1.5 rounded-xl bg-white/10 px-3.5 text-xs font-bold text-white transition-all hover:bg-white/20 disabled:opacity-40"
                                    :title="
                                        isRTL
                                            ? 'فحص الرابط وقراءة الملفات'
                                            : 'Inspect URL and parse files'
                                    "
                                >
                                    <Sparkles
                                        class="h-3.5 w-3.5 text-cyan-400"
                                    />
                                    <span>{{ isRTL ? 'فحص' : 'Inspect' }}</span>
                                </button>

                                <button
                                    v-if="downloadMode === 'torrent'"
                                    type="button"
                                    @click="triggerTorrentFileInput"
                                    :disabled="
                                        isUploadingTorrent || isInspecting
                                    "
                                    class="flex h-11 shrink-0 cursor-pointer items-center gap-1.5 rounded-xl border border-purple-500/40 bg-purple-600/25 px-3.5 text-xs font-bold text-purple-200 shadow-sm transition-all hover:bg-purple-600/40 hover:text-white disabled:opacity-40"
                                    :title="
                                        isRTL
                                            ? 'رفع ملف .torrent من جهازك وفحصه محلياً'
                                            : 'Upload local .torrent file directly'
                                    "
                                >
                                    <FileUp
                                        v-if="!isUploadingTorrent"
                                        class="h-3.5 w-3.5 text-purple-400"
                                    />
                                    <Clock
                                        v-else
                                        class="h-3.5 w-3.5 animate-spin text-purple-400"
                                    />
                                    <span class="hidden sm:inline">{{
                                        isRTL
                                            ? 'رفع .torrent'
                                            : 'Upload .torrent'
                                    }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Media Title & Type -->
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <label
                                    class="mb-1 block text-xs font-bold text-slate-400"
                                    >{{
                                        isRTL ? 'عنوان العمل' : 'Title & Year'
                                    }}</label
                                >
                                <input
                                    type="text"
                                    v-model="newTitle"
                                    placeholder="e.g. Inception (2010)"
                                    class="h-11 w-full rounded-xl border border-white/15 bg-white/[0.04] px-4 text-xs text-white outline-none focus:border-cyan-500"
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-bold text-slate-400"
                                    >{{
                                        isRTL ? 'نوع المحتوى' : 'Media Type'
                                    }}</label
                                >
                                <select
                                    v-model="newType"
                                    class="h-11 w-full cursor-pointer rounded-xl border border-white/15 bg-slate-900 px-3 text-xs text-white outline-none focus:border-cyan-500"
                                >
                                    <option value="movie">
                                        🎬 {{ isRTL ? 'فيلم' : 'Movie' }}
                                    </option>
                                    <option value="series">
                                        📺 {{ isRTL ? 'مسلسل' : 'Series' }}
                                    </option>
                                    <option value="subtitle">
                                        💬 {{ isRTL ? 'ترجمة' : 'Subtitle' }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Multi-File & Folder Selection for Torrent Mode -->
                        <div
                            v-if="
                                inspectionData &&
                                inspectionData.files &&
                                inspectionData.files.length > 0
                            "
                            class="space-y-3 rounded-2xl border border-purple-500/30 bg-purple-950/20 p-4"
                        >
                            <!-- Header with view toggle and filter actions -->
                            <div
                                class="flex flex-wrap items-center justify-between gap-2.5 border-b border-purple-500/20 pb-2"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex items-center gap-1.5 text-xs font-bold text-purple-200"
                                    >
                                        <FolderTree
                                            class="h-4 w-4 text-purple-400"
                                        />
                                        <span>{{
                                            isRTL
                                                ? 'هيكل الملفات والمجلدات'
                                                : 'Torrent Files & Folders'
                                        }}</span>
                                    </span>
                                    <span
                                        class="rounded-full border border-purple-500/30 bg-purple-500/20 px-2 py-0.5 font-mono text-[10px] text-purple-300"
                                    >
                                        {{ selectedFileIndexes.length }}/{{
                                            inspectionData.files.length
                                        }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <!-- View Mode Tabs (Tree vs Flat) -->
                                    <div
                                        class="flex items-center rounded-lg border border-white/10 bg-black/40 p-0.5 text-[11px]"
                                    >
                                        <button
                                            type="button"
                                            @click="viewMode = 'tree'"
                                            class="flex cursor-pointer items-center gap-1 rounded-md px-2.5 py-1 font-medium transition-all"
                                            :class="
                                                viewMode === 'tree'
                                                    ? 'bg-purple-600 text-white shadow-sm'
                                                    : 'text-slate-400 hover:text-white'
                                            "
                                            :title="
                                                isRTL
                                                    ? 'عرض شجرة المجلدات المتداخلة'
                                                    : 'View hierarchical folder tree'
                                            "
                                        >
                                            <FolderTree class="h-3 w-3" />
                                            <span>{{
                                                isRTL ? 'شجرة' : 'Tree'
                                            }}</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="viewMode = 'flat'"
                                            class="flex cursor-pointer items-center gap-1 rounded-md px-2.5 py-1 font-medium transition-all"
                                            :class="
                                                viewMode === 'flat'
                                                    ? 'bg-purple-600 text-white shadow-sm'
                                                    : 'text-slate-400 hover:text-white'
                                            "
                                            :title="
                                                isRTL
                                                    ? 'عرض قائمة مسطحة بالملفات'
                                                    : 'View flat list of files'
                                            "
                                        >
                                            <ListFilter class="h-3 w-3" />
                                            <span>{{
                                                isRTL ? 'قائمة' : 'List'
                                            }}</span>
                                        </button>
                                    </div>

                                    <!-- Quick Filters -->
                                    <div
                                        class="flex items-center gap-1 text-[10px]"
                                    >
                                        <button
                                            @click="selectAllFiles"
                                            type="button"
                                            class="cursor-pointer rounded-md bg-white/10 px-2 py-1 text-white transition-all hover:bg-white/20"
                                        >
                                            {{ isRTL ? 'تحديد الكل' : 'All' }}
                                        </button>
                                        <button
                                            @click="selectVideosOnly"
                                            type="button"
                                            class="cursor-pointer rounded-md bg-purple-500/30 px-2 py-1 text-purple-200 transition-all hover:bg-purple-500/50"
                                        >
                                            {{
                                                isRTL
                                                    ? 'الفيديوهات فقط'
                                                    : 'Videos'
                                            }}
                                        </button>
                                        <button
                                            @click="clearAllFiles"
                                            type="button"
                                            class="cursor-pointer rounded-md bg-white/5 px-2 py-1 text-slate-400 transition-all hover:bg-white/15"
                                        >
                                            {{ isRTL ? 'تفريغ' : 'Clear' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- TREE VIEW -->
                            <div
                                v-if="
                                    viewMode === 'tree' &&
                                    inspectionData.tree &&
                                    inspectionData.tree.length > 0
                                "
                                class="custom-scrollbar max-h-64 space-y-1 overflow-y-auto pr-1 sm:max-h-72"
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
                                class="custom-scrollbar max-h-64 space-y-1.5 overflow-y-auto pr-1 sm:max-h-72"
                            >
                                <div
                                    v-for="file in inspectionData.files"
                                    :key="file.index"
                                    @click="toggleFileSelection(file.index)"
                                    class="flex cursor-pointer items-center justify-between gap-2 rounded-xl border p-2 text-xs transition-all"
                                    :class="
                                        selectedFileIndexes.includes(file.index)
                                            ? 'border-purple-500/40 bg-purple-500/20 text-white shadow-sm'
                                            : 'border-transparent bg-black/20 text-slate-400 hover:bg-white/5'
                                    "
                                >
                                    <div
                                        class="flex min-w-0 flex-1 items-center gap-2"
                                    >
                                        <div
                                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-all"
                                            :class="
                                                selectedFileIndexes.includes(
                                                    file.index,
                                                )
                                                    ? 'border-purple-400 bg-purple-500 text-slate-950'
                                                    : 'border-slate-600 bg-black/40'
                                            "
                                        >
                                            <Check
                                                v-if="
                                                    selectedFileIndexes.includes(
                                                        file.index,
                                                    )
                                                "
                                                class="h-3 w-3 stroke-[3]"
                                            />
                                        </div>

                                        <Film
                                            v-if="file.is_video"
                                            class="h-3.5 w-3.5 shrink-0 text-cyan-400"
                                        />
                                        <FileText
                                            v-else-if="file.is_subtitle"
                                            class="h-3.5 w-3.5 shrink-0 text-emerald-400"
                                        />
                                        <File
                                            v-else
                                            class="h-3.5 w-3.5 shrink-0 text-slate-500"
                                        />

                                        <span
                                            class="truncate font-mono text-[11px]"
                                            :title="file.path"
                                        >
                                            {{ file.path }}
                                        </span>
                                    </div>

                                    <div
                                        class="flex shrink-0 items-center gap-2"
                                    >
                                        <span
                                            v-if="file.is_video"
                                            class="py-0.2 rounded border border-cyan-500/30 bg-cyan-500/15 px-1.5 text-[9px] font-bold text-cyan-300 uppercase"
                                        >
                                            video
                                        </span>
                                        <span
                                            class="font-mono text-[10px] text-slate-400"
                                            >{{ formatBytes(file.size) }}</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Stats -->
                            <div
                                class="flex items-center justify-between border-t border-purple-500/20 pt-2 font-mono text-[11px] text-purple-300"
                            >
                                <span class="flex items-center gap-2">
                                    <span>{{
                                        isRTL
                                            ? 'الحجم المحدد:'
                                            : 'Selected size:'
                                    }}</span>
                                    <strong class="text-white">{{
                                        formatBytes(selectedTotalBytes)
                                    }}</strong>
                                    <span class="text-[10px] text-slate-500"
                                        >/
                                        {{
                                            formatBytes(
                                                inspectionData.total_bytes,
                                            )
                                        }}</span
                                    >
                                </span>

                                <span
                                    v-if="inspectionData.folder_count"
                                    class="text-[10px] text-slate-400"
                                >
                                    {{ inspectionData.folder_count }}
                                    {{ isRTL ? 'مجلد' : 'folders' }}
                                </span>
                            </div>
                        </div>

                        <!-- Destination Folder Selector: Default vs Custom -->
                        <div class="space-y-2 border-t border-white/10 pt-1">
                            <div class="flex items-center justify-between">
                                <label
                                    class="block text-xs font-bold text-slate-400"
                                    >{{
                                        isRTL
                                            ? 'مجلد الحفظ النهائي'
                                            : 'Destination Download Folder'
                                    }}</label
                                >
                                <span class="text-[10px] text-slate-500">
                                    {{
                                        isRTL
                                            ? 'يتم حفظ الملفات مباشرة بالهيكل الداخلي'
                                            : 'Preserves internal folder layout'
                                    }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="destinationType = 'default'"
                                    class="flex cursor-pointer items-center gap-1.5 rounded-xl border px-3 py-2 text-left text-xs font-bold transition-all"
                                    :class="
                                        destinationType === 'default'
                                            ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300'
                                            : 'border-white/10 bg-white/5 text-slate-400'
                                    "
                                >
                                    <Folder class="h-3.5 w-3.5 shrink-0" />
                                    <div class="truncate">
                                        <div class="text-[11px] font-bold">
                                            {{
                                                isRTL
                                                    ? 'المجلد الافتراضي'
                                                    : 'Default Library'
                                            }}
                                        </div>
                                        <div
                                            class="truncate text-[9px] text-slate-400"
                                        >
                                            {{ defaultTargetFolder }}
                                        </div>
                                    </div>
                                </button>

                                <button
                                    type="button"
                                    @click="destinationType = 'custom'"
                                    class="flex cursor-pointer items-center gap-1.5 rounded-xl border px-3 py-2 text-left text-xs font-bold transition-all"
                                    :class="
                                        destinationType === 'custom'
                                            ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300'
                                            : 'border-white/10 bg-white/5 text-slate-400'
                                    "
                                >
                                    <HardDrive class="h-3.5 w-3.5 shrink-0" />
                                    <div>
                                        <div class="text-[11px] font-bold">
                                            {{
                                                isRTL
                                                    ? 'مجلد مخصص'
                                                    : 'Custom Folder'
                                            }}
                                        </div>
                                        <div class="text-[9px] text-slate-400">
                                            {{
                                                isRTL
                                                    ? 'تحديد مسار يدوي'
                                                    : 'Select custom path'
                                            }}
                                        </div>
                                    </div>
                                </button>
                            </div>

                            <div
                                v-if="destinationType === 'custom'"
                                class="pt-1"
                            >
                                <input
                                    type="text"
                                    v-model="customFolder"
                                    placeholder="e.g. D:\Downloads\Special"
                                    class="h-10 w-full rounded-xl border border-cyan-500/40 bg-white/[0.04] px-3 font-mono text-xs text-cyan-200 outline-none focus:border-cyan-400"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        class="border-border/70 flex items-center justify-end gap-3 border-t pt-3"
                    >
                        <button
                            type="button"
                            @click="showAddModal = false"
                            class="text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer rounded-xl px-4 py-2 text-xs font-bold"
                        >
                            {{ t('common.close') }}
                        </button>
                        <button
                            type="button"
                            @click="handleAddSubmit"
                            :disabled="
                                isSubmitting ||
                                !newTitle.trim() ||
                                (downloadMode === 'torrent' &&
                                    selectedFileIndexes.length === 0)
                            "
                            class="bg-primary text-primary-foreground shadow-primary/25 cursor-pointer rounded-xl px-6 py-2.5 text-xs font-black shadow-lg hover:brightness-105 disabled:opacity-50"
                        >
                            {{
                                isRTL ? 'بدء التنزيل المباشر' : 'Start Download'
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Downloader Settings Modal -->
        <transition name="fade">
            <div
                v-if="showSettingsModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-md"
                @click.self="showSettingsModal = false"
            >
                <div
                    class="cf-glass-elevated border-border/80 w-full max-w-lg space-y-5 rounded-3xl border p-6 shadow-2xl sm:p-8"
                >
                    <div
                        class="flex items-center justify-between border-b border-white/10 pb-3"
                    >
                        <h3
                            class="flex items-center gap-2 text-base font-extrabold text-white"
                        >
                            <Settings class="h-5 w-5 text-cyan-400" />
                            <span>{{
                                isRTL
                                    ? 'إعدادات مسارات التنزيل ومجلدات المكتبة'
                                    : 'Downloader & Destination Folders'
                            }}</span>
                        </h3>
                    </div>

                    <div class="space-y-3.5 text-xs">
                        <div>
                            <label
                                class="mb-1 block font-bold text-slate-300"
                                >{{
                                    isRTL
                                        ? 'مجلد الأفلام الافتراضي'
                                        : 'Default Movies Destination'
                                }}</label
                            >
                            <input
                                type="text"
                                v-model="settingsForm.movies_download_path"
                                class="h-10 w-full rounded-xl border border-white/15 bg-white/5 px-3 font-mono text-xs text-cyan-300 outline-none focus:border-cyan-500"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1 block font-bold text-slate-300"
                                >{{
                                    isRTL
                                        ? 'مجلد المسلسلات الافتراضي'
                                        : 'Default Series Destination'
                                }}</label
                            >
                            <input
                                type="text"
                                v-model="settingsForm.series_download_path"
                                class="h-10 w-full rounded-xl border border-white/15 bg-white/5 px-3 font-mono text-xs text-indigo-300 outline-none focus:border-indigo-500"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1 block font-bold text-slate-300"
                                >{{
                                    isRTL
                                        ? 'مجلد التنزيل المؤقت'
                                        : 'Default Download Staging Directory'
                                }}</label
                            >
                            <input
                                type="text"
                                v-model="settingsForm.default_download_path"
                                class="h-10 w-full rounded-xl border border-white/15 bg-white/5 px-3 font-mono text-xs text-slate-300 outline-none focus:border-cyan-500"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <div>
                                <label
                                    class="mb-1 block font-bold text-slate-300"
                                    >{{
                                        isRTL
                                            ? 'أقصى تنزيلات متزامنة'
                                            : 'Max Concurrent'
                                    }}</label
                                >
                                <input
                                    type="number"
                                    min="1"
                                    max="10"
                                    v-model.number="
                                        settingsForm.max_concurrent_downloads
                                    "
                                    class="h-10 w-full rounded-xl border border-white/15 bg-white/5 px-3 font-mono text-xs text-white outline-none focus:border-cyan-500"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block font-bold text-slate-300"
                                    >{{
                                        isRTL
                                            ? 'حد السرعة (0 = غير محدود)'
                                            : 'Speed Limit (KB/s)'
                                    }}</label
                                >
                                <input
                                    type="number"
                                    min="0"
                                    v-model.number="
                                        settingsForm.download_speed_limit_kb
                                    "
                                    class="h-10 w-full rounded-xl border border-white/15 bg-white/5 px-3 font-mono text-xs text-white outline-none focus:border-cyan-500"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-end gap-3 border-t border-white/10 pt-3"
                    >
                        <button
                            type="button"
                            @click="showSettingsModal = false"
                            class="cursor-pointer rounded-xl bg-white/5 px-4 py-2 text-xs font-bold text-slate-300 hover:bg-white/10"
                        >
                            {{ t('common.close') }}
                        </button>
                        <button
                            type="button"
                            @click="handleSaveSettings"
                            :disabled="isSavingSettings"
                            class="cursor-pointer rounded-xl bg-cyan-500 px-5 py-2.5 text-xs font-black text-slate-950 shadow-lg shadow-cyan-500/20 hover:bg-cyan-400"
                        >
                            {{
                                isSavingSettings
                                    ? isRTL
                                        ? 'جاري الحفظ...'
                                        : 'Saving...'
                                    : isRTL
                                      ? 'حفظ التغييرات'
                                      : 'Save Settings'
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Explain Modal -->
        <transition name="fade">
            <div
                v-if="showExplainModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-md"
                @click.self="showExplainModal = false"
            >
                <div
                    class="glass-panel w-full max-w-lg space-y-5 rounded-3xl border border-cyan-500/30 p-6 shadow-2xl sm:p-8"
                >
                    <div
                        class="flex items-center justify-between border-b border-white/10 pb-3"
                    >
                        <h3
                            class="flex items-center gap-2 text-base font-extrabold text-white"
                        >
                            <Info class="h-5 w-5 text-cyan-400" />
                            <span>{{
                                isRTL
                                    ? 'دليل محرك التنزيل المدمج'
                                    : 'Downloader Engine Guide'
                            }}</span>
                        </h3>
                    </div>

                    <div
                        class="space-y-3 text-xs leading-relaxed text-slate-300"
                    >
                        <div
                            class="space-y-1 rounded-xl border border-white/10 bg-white/5 p-3"
                        >
                            <span class="block font-bold text-cyan-400"
                                >1.
                                {{
                                    isRTL
                                        ? 'فحص التورنت وشجرة المجلدات'
                                        : 'Torrent Inspection & Folder Tree'
                                }}</span
                            >
                            <p class="text-slate-400">
                                {{
                                    isRTL
                                        ? 'يقوم المحرك بقراءة بنية ملف التورنت بالكامل وتوليد شجرة المجلدات الحقيقية لتحديد أي مجلد أو ملف دون أخطاء.'
                                        : 'Inspects the full torrent hierarchy and renders real folder trees so you can accurately select specific folders or files.'
                                }}
                            </p>
                        </div>

                        <div
                            class="space-y-1 rounded-xl border border-white/10 bg-white/5 p-3"
                        >
                            <span class="block font-bold text-indigo-400"
                                >2.
                                {{
                                    isRTL
                                        ? 'تنزيل حقيقي دون ملفات تالفة'
                                        : 'Direct BitTorrent & Stream Engine'
                                }}</span
                            >
                            <p class="text-slate-400">
                                {{
                                    isRTL
                                        ? 'يتم تنزيل المحتوى الفعلي عبر بروتوكول BitTorrent والتحميل المباشر وحفظ الملفات في المجلد المختار مباشرة دون تعديل أو تلف.'
                                        : 'Downloads real files with genuine chunk hashing directly into your destination directory without dummy placeholders or corruption.'
                                }}
                            </p>
                        </div>

                        <div
                            class="space-y-1 rounded-xl border border-white/10 bg-white/5 p-3"
                        >
                            <span class="block font-bold text-emerald-400"
                                >3.
                                {{
                                    isRTL
                                        ? 'تنظيم وفهرسة اختيارية'
                                        : 'Optional Disk Organizer'
                                }}</span
                            >
                            <p class="text-slate-400">
                                {{
                                    isRTL
                                        ? 'تظل ملفاتك محتفظة بهيكلها كما هي في التورنت، ويمكنك استخدام منظم الأقراص لاحقاً لترتيبها ومطابقتها متى أردت.'
                                        : 'Your files stay in their original downloaded structure. You can use Disk Organizer at any time if you wish to reorganize them.'
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="button"
                            @click="showExplainModal = false"
                            class="cursor-pointer rounded-xl bg-cyan-500 px-5 py-2.5 text-xs font-black text-slate-950 hover:bg-cyan-400"
                        >
                            {{ isRTL ? 'فهمت ذلك' : 'Got it' }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
        <FastOrganizeModal
            v-if="showFastOrganize"
            :is-open="showFastOrganize"
            @close="showFastOrganize = false"
            @organized="fetchDownloads"
        />
    </AppLayout>
</template>
