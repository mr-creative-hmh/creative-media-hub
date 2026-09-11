<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';

import { useOrganizerPlan } from '@/composables/useOrganizerPlan';
import {
    Trash2, FolderSync, FolderSearch, ArrowRight, ArrowLeft, Play, Pause, CheckCircle2,
    AlertTriangle, Copy, Move, RefreshCw, Layers, ShieldCheck, Sparkles,
    FolderCheck, FileText, Check, Database, Sliders, ChevronDown,
    Search, Filter, CheckSquare, Square, Film, Tv, HardDrive, Terminal,
    StopCircle, ExternalLink, XCircle
} from 'lucide-vue-next';

const props = defineProps<{
    defaultMovieTemplate: string;
    defaultSeriesTemplate: string;
    defaultWorkingDir?: string;
    watcherStatus?: any;
}>();

const { t, isRTL } = useI18n();

const step = ref<1 | 2 | 3>(1);
const sourceMode = ref<'virtual' | 'folder'>('virtual');
const sourceFolder = ref('D:/Downloads');
const targetRoot = ref(props.defaultWorkingDir || 'H:/Entertainment');
const isScanning = ref(false);
const scannedFiles = ref<any[]>([]);

// Organizing Strategies Preset Catalog
const strategies = [
    {
        id: 'cmh',
        tag: 'Creative Media Hub',
        nameAr: 'نمط Creative Media Hub الذكي (موصى به)',
        nameEn: 'Creative Media Hub Smart Standard (Recommended)',
        descAr: 'تصنيف تلقائي متكامل: ينظم الأفلام حسب النوع ويجمع سلاسل الأفلام (Collections) تلقائياً مع معالجة الأفلام الفردية بنظافة.',
        descEn: 'Intelligent multi-tier structure: auto-detects movie collections/franchises and genres with clean standalone fallbacks.',
        movie: '{Type}/{Genre}/{Collection}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}',
        series: '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{CleanResolution}].{ext}',
        exampleMovie: 'Movies/Fantasy/Harry Potter Collection/Harry Potter and the Goblet of Fire (2005)/... [1080p].mkv',
        exampleSeries: 'TV Shows/Breaking Bad (2008)/Season 01/Breaking Bad - S01E01 - Pilot [1080p].mkv',
    },
    {
        id: 'plex',
        tag: 'Plex / Emby',
        nameAr: 'نمط Plex و Emby القياسي',
        nameEn: 'Plex & Emby Standard',
        descAr: 'المعيار المعتمد لمخدمات Plex و Emby: مجلد مخصص لكل فيلم ومجلدات مواسم للمسلسلات.',
        descEn: 'Industry standard: dedicated folder per movie, Season subfolders for series.',
        movie: '{Type}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}',
        series: '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{CleanResolution}].{ext}',
        exampleMovie: 'Movies/Inception (2010)/Inception (2010) [1080p].mkv',
        exampleSeries: 'TV Shows/Rick and Morty (2013)/Season 01/Rick and Morty - S01E01 - Pilot [1080p].mkv',
    },
    {
        id: 'jellyfin',
        tag: 'Jellyfin / Kodi',
        nameAr: 'نمط Jellyfin و Kodi النظيف',
        nameEn: 'Jellyfin & Kodi Clean Standard',
        descAr: 'هيكل مجلدات مبسط ومثالي لمخدمات Jellyfin، Kodi، و Infuse.',
        descEn: 'Clean minimalist structure optimized for Jellyfin, Kodi, and Infuse.',
        movie: '{Type}/{Title} ({Year})/{Title} ({Year}).{ext}',
        series: '{Type}/{Title}/Season {Season:02}/{Title} S{Season:02}E{Episode:02}.{ext}',
        exampleMovie: 'Movies/Interstellar (2014)/Interstellar (2014).mp4',
        exampleSeries: 'TV Shows/Breaking Bad/Season 01/Breaking Bad S01E01.mkv',
    },
    {
        id: 'custom',
        tag: 'Dynamic Syntax',
        nameAr: 'تخصيص يدوي متقدم للرموز',
        nameEn: 'Custom Pattern Builder',
        descAr: 'صمم القوالب بحرية تامة باستخدام الرموز الديناميكية المتاحة.',
        descEn: 'Build completely custom folder and file naming patterns with tokens.',
        movie: '{Type}/{Genre}/{Collection}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}',
        series: '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{CleanResolution}].{ext}',
        exampleMovie: 'Customizable syntax below',
        exampleSeries: 'Customizable syntax below',
    },
];


// Watcher & Auto-Organize State
const watcher = ref(props.watcherStatus || {
    enabled: false,
    watched_folders: [],
    target_root: 'H:/Entertainment',
    last_check: null,
    auto_execute: false,
    items_organized_count: 0,
});
const isTogglingWatcher = ref(false);
const isRunningWatcher = ref(false);
const newWatchFolder = ref('');
const omitEpisodeTitle = ref(false);

const toggleOmitEpisodeTitle = () => {
    omitEpisodeTitle.value = !omitEpisodeTitle.value;
    if (omitEpisodeTitle.value) {
        seriesTemplate.value = seriesTemplate.value.replace(' - {EpisodeTitle}', '').replace('{EpisodeTitle}', '');
    } else if (!seriesTemplate.value.includes('{EpisodeTitle}')) {
        seriesTemplate.value = seriesTemplate.value.replace('[{CleanResolution}]', '- {EpisodeTitle} [{CleanResolution}]')
            .replace('[{Resolution}]', '- {EpisodeTitle} [{Resolution}]');
    }
};

const toggleWatcher = async () => {
    isTogglingWatcher.value = true;
    try {
        const res = await (window as any).axios.post('/api/organizer/watcher/toggle', {
            enabled: !watcher.value.enabled,
        });
        watcher.value = res.data;
    } catch (e) {
        console.error(e);
    } finally {
        isTogglingWatcher.value = false;
    }
};

const addWatchFolder = async () => {
    if (!newWatchFolder.value.trim()) return;
    try {
        const res = await (window as any).axios.post('/api/organizer/watcher/folder', {
            action: 'add',
            path: newWatchFolder.value.trim(),
        });
        watcher.value = res.data.status;
        newWatchFolder.value = '';
    } catch (e) {
        console.error(e);
    }
};

const removeWatchFolder = async (folderPath: string) => {
    try {
        const res = await (window as any).axios.post('/api/organizer/watcher/folder', {
            action: 'remove',
            path: folderPath,
        });
        watcher.value = res.data.status;
    } catch (e) {
        console.error(e);
    }
};

const runWatcherNow = async () => {
    isRunningWatcher.value = true;
    try {
        const res = await (window as any).axios.post('/api/organizer/watcher/run-now', {
            dry_run: false,
        });
        alert(`Watcher finished! Scanned ${res.data.total_scanned} files, organized ${res.data.ready_count} items.`);
    } catch (e) {
        console.error(e);
    } finally {
        isRunningWatcher.value = false;
    }
};

const selectedStrategy = ref('cmh');
const movieTemplate = ref(props.defaultMovieTemplate || '{Type}/{Genre}/{Collection}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}');
const seriesTemplate = ref(props.defaultSeriesTemplate || '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{CleanResolution}].{ext}');
const showCustomTemplates = ref(false);
const groupCollections = ref(true);
const isWatcherExpanded = ref(false);

const toggleGroupCollections = () => {
    groupCollections.value = !groupCollections.value;
    if (!groupCollections.value) {
        movieTemplate.value = movieTemplate.value
            .replace('{Collection}/', '')
            .replace('/{Collection}', '');
    } else if (!movieTemplate.value.includes('{Collection}')) {
        if (movieTemplate.value.includes('{Genre}/')) {
            movieTemplate.value = movieTemplate.value.replace('{Genre}/', '{Genre}/{Collection}/');
        } else if (movieTemplate.value.includes('{Type}/')) {
            movieTemplate.value = movieTemplate.value.replace('{Type}/', '{Type}/{Collection}/');
        } else {
            movieTemplate.value = '{Collection}/' + movieTemplate.value;
        }
    }
};

const selectStrategy = (id: string) => {
    selectedStrategy.value = id;
    const strat = strategies.find(s => s.id === id);
    if (strat) {
        movieTemplate.value = strat.movie;
        seriesTemplate.value = strat.series;
        showCustomTemplates.value = id === 'custom';
        if (id === 'cmh') {
            groupCollections.value = true;
        }
    }
};

// Available template tokens
const tokens = [
    { token: '{Type}', desc: 'Movies / TV Shows' },
    { token: '{Genre}', desc: 'Primary Genre (e.g. Fantasy, Action)' },
    { token: '{Collection}', desc: 'Collection / Franchise (e.g. Harry Potter Collection)' },
    { token: '{Title}', desc: 'Clean Title (e.g. Inception)' },
    { token: '{Year}', desc: 'Release Year (e.g. 2010)' },
    { token: '{CleanResolution}', desc: 'Standardized Resolution (e.g. 1080p, 2160p)' },
    { token: '{Resolution}', desc: 'Detected Resolution (1080p, 2160p, 720p)' },
    { token: '{Codec}', desc: 'HEVC / x264 / AV1' },
    { token: '{Source}', desc: 'BluRay / WEBRip / WEB-DL / HDTV' },
    { token: '{Genres}', desc: 'Joined Genres (e.g. Action & Sci-Fi)' },
    { token: '{Edition}', desc: "Director's Cut / Extended" },
    { token: '{Group}', desc: 'Release Group (e.g. FLUX)' },
    { token: '{Season:02}', desc: '01, 02, 03' },
    { token: '{Episode:02}', desc: '01, 02, 03' },
    { token: '{EpisodeTitle}', desc: 'Episode Name' },
    { token: '{FirstLetter}', desc: 'A, B, C... #' },
    { token: '{ext}', desc: 'File Extension (mkv, mp4)' },
];

const appendToken = (target: 'movie' | 'series', token: string) => {
    if (target === 'movie') {
        movieTemplate.value += token;
    } else {
        seriesTemplate.value += token;
    }
};

// Plan Generation & Execution State
const isGeneratingPlan = ref(false);
const plan = ref<any[]>([]);
const executeMode = ref<'move' | 'copy'>('move');
const cleanupEmptyFolders = ref(true);
const showExecuteConfirm = ref(false);

// Step 2 Filters & Pagination
const searchQuery = ref('');
const filterType = ref<'all' | 'movie' | 'series' | 'collection' | 'subtitles'>('all');
const filterStatus = ref<'all' | 'ready' | 'collision_exists' | 'identical'>('all');
const currentPage = ref(1);
const perPage = ref(20);

const movieItemsCount = computed(() => plan.value.filter(i => i.type === 'movie').length);
const seriesItemsCount = computed(() => plan.value.filter(i => i.type === 'series').length);
const collectionItemsCount = computed(() => plan.value.filter(i => !!i.collection_name).length);
const subtitleItemsCount = computed(() => plan.value.filter(i => Array.isArray(i.subtitles) && i.subtitles.length > 0).length);

const filteredPlan = computed(() => {
    return plan.value.filter(item => {
        const query = searchQuery.value.trim().toLowerCase();
        const matchesSearch = !query ||
            (item.filename && item.filename.toLowerCase().includes(query)) ||
            (item.clean_title && item.clean_title.toLowerCase().includes(query)) ||
            (item.destination_path && item.destination_path.toLowerCase().includes(query)) ||
            (item.collection_name && item.collection_name.toLowerCase().includes(query));

        const matchesType = (() => {
            if (filterType.value === 'all') return true;
            if (filterType.value === 'movie') return item.type === 'movie';
            if (filterType.value === 'series') return item.type === 'series';
            if (filterType.value === 'collection') return !!item.collection_name;
            if (filterType.value === 'subtitles') return Array.isArray(item.subtitles) && item.subtitles.length > 0;
            return true;
        })();

        const matchesStatus = filterStatus.value === 'all' || item.status === filterStatus.value;

        return matchesSearch && matchesType && matchesStatus;
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredPlan.value.length / perPage.value)));

const paginatedPlan = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return filteredPlan.value.slice(start, start + perPage.value);
});

const readyItemsCount = computed(() => plan.value.filter(i => i.status === 'ready').length);
const selectedItemsCount = computed(() => plan.value.filter(i => i.selected).length);

// Step 3 Live Execution & Streaming Status
const isExecuting = ref(false);
const isExecutionPaused = ref(false);
const isExecutionCancelled = ref(false);
const executionStatus = ref({
    is_active: false,
    is_completed: false,
    is_cancelled: false,
    mode: 'move',
    total_items: 0,
    total_bytes: 0,
    total_bytes_formatted: '0 GB',
    processed_count: 0,
    successful_count: 0,
    failed_count: 0,
    cleaned_folders_count: 0,
    progress_percent: 0,
    current_action: 'Idle',
    current_file: '',
    current_destination: '',
    logs: [] as Array<{ time: string; type: string; message: string }>,
    errors: [] as string[],
    completed_items: [] as any[],
});

let executionPollTimer: any = null;
const logTerminalRef = ref<HTMLDivElement | null>(null);

const scrollLogsToBottom = () => {
    nextTick(() => {
        if (logTerminalRef.value) {
            logTerminalRef.value.scrollTop = logTerminalRef.value.scrollHeight;
        }
    });
};

// ==========================================
// Folder Browser State & Methods
// ==========================================
const showFolderBrowser = ref(false);
const browserTarget = ref<'source' | 'target'>('source');
const browserCurrentPath = ref<string | null>(null);
const browserParentPath = ref<string | null>(null);
const browserDrives = ref<Array<{ name: string; path: string; is_drive: boolean }>>([]);
const browserShortcuts = ref<Array<{ name: string; path: string }>>([]);
const browserDirectories = ref<Array<{ name: string; path: string; has_children: boolean; is_writable: boolean }>>([]);
const browserFilter = ref('');
const isLoadingBrowser = ref(false);

const filteredBrowserDirs = computed(() => {
    if (!browserFilter.value.trim()) return browserDirectories.value;
    const q = browserFilter.value.toLowerCase();
    return browserDirectories.value.filter(d => d.name.toLowerCase().includes(q));
});

const openFolderBrowser = async (target: 'source' | 'target') => {
    browserTarget.value = target;
    browserFilter.value = '';
    showFolderBrowser.value = true;
    const startPath = target === 'source' ? sourceFolder.value : targetRoot.value;
    await browsePath(startPath || null);
};

const browsePath = async (path: string | null) => {
    isLoadingBrowser.value = true;
    try {
        const url = path ? `/api/organizer/browse-directory?path=${encodeURIComponent(path)}` : '/api/organizer/browse-directory';
        const res = await fetch(url);
        const data = await res.json();
        browserCurrentPath.value = data.current_path;
        browserParentPath.value = data.parent_path;
        browserDrives.value = data.drives || [];
        browserShortcuts.value = data.shortcuts || [];
        browserDirectories.value = data.directories || [];
    } catch (e) {
        console.error('Directory browse error:', e);
    } finally {
        isLoadingBrowser.value = false;
    }
};

const selectCurrentBrowserFolder = () => {
    if (!browserCurrentPath.value) return;
    if (browserTarget.value === 'source') {
        sourceFolder.value = browserCurrentPath.value;
    } else {
        targetRoot.value = browserCurrentPath.value;
    }
    showFolderBrowser.value = false;
};

const selectDirectoryItem = (dirPath: string) => {
    if (browserTarget.value === 'source') {
        sourceFolder.value = dirPath;
    } else {
        targetRoot.value = dirPath;
    }
    showFolderBrowser.value = false;
};

// ==========================================
// Virtual Scanner-Style Background Plan Job
// ==========================================
const {
    isPlanModalOpen,
    planJobStatus,
    isAnalyzing: isPlanAnalyzing,
    isPaused: isPlanPaused,
    openPlanModal,
    closePlanModal,
    startPlanJob,
    fetchPlanStatus,
    pausePlanJob,
    resumePlanJob,
    cancelPlanJob,
} = useOrganizerPlan();

const handleStartPlanGeneration = async () => {
    if (sourceMode.value === 'folder' && !sourceFolder.value.trim()) {
        alert(isRTL.value ? 'يرجى إدخال أو تحديد مسار المجلد المصدري أولاً.' : 'Please enter or select a source directory first.');
        return;
    }
    await startPlanJob({
        source_path: sourceFolder.value,
        target_root: targetRoot.value,
        movie_template: movieTemplate.value,
        series_template: seriesTemplate.value,
        source_mode: sourceMode.value,
        recursive: true,
    });
};

const onPlanReady = (items: any[]) => {
    if (items && items.length > 0) {
        plan.value = items;
        currentPage.value = 1;
        step.value = 2;
    }
};

// Scan Virtual or Physical source
const startScan = async () => {
    isScanning.value = true;
    try {
        if (sourceMode.value === 'virtual') {
            const res = await fetch('/api/organizer/load-virtual');
            const data = await res.json();
            scannedFiles.value = data.files || [];
        } else {
            const res = await fetch('/api/organizer/scan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
                body: JSON.stringify({
                    source_path: sourceFolder.value,
                    recursive: true,
                }),
            });
            const data = await res.json();
            scannedFiles.value = data.files || [];
        }

        await generateDryRun();
    } catch (e) {
        console.error('Scan error:', e);
    } finally {
        isScanning.value = false;
    }
};

const generateDryRun = async () => {
    if (!scannedFiles.value.length) return;

    isGeneratingPlan.value = true;
    try {
        const res = await fetch('/api/organizer/dry-run', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                files: scannedFiles.value,
                target_root: targetRoot.value,
                movie_template: movieTemplate.value,
                series_template: seriesTemplate.value,
            }),
        });
        const data = await res.json();
        plan.value = data.plan || [];
        currentPage.value = 1;
        step.value = 2;
    } catch (e) {
        console.error('Dry-run error:', e);
    } finally {
        isGeneratingPlan.value = false;
    }
};

// Execute Plan with Real-time Loop (Like Media Scanner)
const executePlan = async () => {
    showExecuteConfirm.value = false;
    isExecuting.value = true;
    step.value = 3;

    try {
        // 1. Initialize queue on backend
        const initRes = await fetch('/api/organizer/execute/init', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                plan: plan.value,
                mode: executeMode.value,
                cleanup_empty_folders: cleanupEmptyFolders.value,
            }),
        });

        const initData = await initRes.json();
        if (initData.status) {
            executionStatus.value = initData.status;
        }

        // 2. Start batch runner loop
        runNextExecutionBatch();
    } catch (e) {
        console.error('Execute init error:', e);
        isExecuting.value = false;
    }
};

let executionBatchRetryCount = 0;
const MAX_EXECUTION_RETRIES = 5;

const runNextExecutionBatch = async () => {
    if (!isExecuting.value) return;

    try {
        const res = await fetch('/api/organizer/execute/batch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({ batch_size: 1 }),
        });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        }

        const data = await res.json();
        executionBatchRetryCount = 0; // Reset retries on success

        if (data.status) {
            executionStatus.value = data.status;
            scrollLogsToBottom();
        }

        if (data.has_more && isExecuting.value) {
            // Next item after 150ms pause to keep UI active and responsive
            setTimeout(runNextExecutionBatch, 150);
        } else {
            isExecuting.value = false;
        }
    } catch (e: any) {
        console.error('Batch execution error:', e);
        if (executionBatchRetryCount < MAX_EXECUTION_RETRIES && isExecuting.value) {
            executionBatchRetryCount++;
            console.warn(`Retrying execution (attempt ${executionBatchRetryCount}/${MAX_EXECUTION_RETRIES})...`);
            setTimeout(runNextExecutionBatch, 2000);
        } else {
            isExecuting.value = false;
            if (executionStatus.value && executionStatus.value.logs) {
                executionStatus.value.logs.push({
                    time: new Date().toLocaleTimeString(),
                    type: 'error',
                    message: `Operation paused: ${e.message || e}. Click "Resume Execution" to continue remaining files.`,
                });
                scrollLogsToBottom();
            }
        }
    }
};

const resumeExecution = () => {
    isExecuting.value = true;
    executionBatchRetryCount = 0;
    runNextExecutionBatch();
};

const cancelExecution = async () => {
    try {
        const res = await fetch('/api/organizer/execute/cancel', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });
        const data = await res.json();
        executionStatus.value = data;
        isExecuting.value = false;
    } catch (e) {
        console.error('Cancel error:', e);
    }
};

const toggleSelectAll = (checked: boolean) => {
    plan.value.forEach(item => {
        if (item.status === 'ready') {
            item.selected = checked;
        }
    });
};

const resetOrganizer = () => {
    step.value = 1;
    plan.value = [];
    scannedFiles.value = [];
    isExecuting.value = false;
};

const handleStepNav = (e: any) => {
    if (e.detail?.step) {
        step.value = e.detail.step;
        if (planJobStatus.value.plan_items && planJobStatus.value.plan_items.length > 0) {
            onPlanReady(planJobStatus.value.plan_items);
        }
    }
};

watch(
    () => [planJobStatus.value.status, planJobStatus.value.plan_items],
    ([newStatus, items]) => {
        if (newStatus === 'completed' && Array.isArray(items) && items.length > 0) {
            onPlanReady(items);
        }
    },
    { deep: true, immediate: true }
);

onMounted(async () => {
    if (typeof window !== 'undefined') {
        window.addEventListener('cmh:navigate-step', handleStepNav);
    }
    await fetchPlanStatus();

    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('step') === '2') {
            if (planJobStatus.value.plan_items && planJobStatus.value.plan_items.length > 0) {
                onPlanReady(planJobStatus.value.plan_items);
            } else {
                step.value = 2;
            }
        } else if (planJobStatus.value.status === 'completed' && planJobStatus.value.plan_items && planJobStatus.value.plan_items.length > 0 && plan.value.length === 0) {
            onPlanReady(planJobStatus.value.plan_items);
        }
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('cmh:navigate-step', handleStepNav);
    }
    if (executionPollTimer) {
        clearInterval(executionPollTimer);
    }
});
</script>

<template>
    <Head :title="t('nav.organizer')" />

    <AppLayout v-slot="{ play }">
        <!-- Header -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400 shadow-lg shadow-cyan-500/10">
                        <FolderSync class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
                            <span>{{ t('nav.organizer') }}</span>
                            <span class="text-xs px-2.5 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 font-bold uppercase tracking-wider">
                                Physical Disk Studio
                            </span>
                        </h1>
                        <p class="text-sm text-slate-400 mt-0.5">
                            {{ isRTL ? 'إعادة تسمية وتنظيم ملفات الوسائط الفعلية وفق المعايير السينمائية العالمية بدون أي فقدان للبيانات' : 'Standardize, rename, and physically restructure media on disk with zero data loss' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Wizard Stepper Breadcrumb & Active Plan Job Indicator -->
            <div class="flex flex-wrap items-center gap-3">
                <button
                    v-if="isPlanAnalyzing || isPlanPaused"
                    type="button"
                    @click="openPlanModal"
                    class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 hover:bg-cyan-500/25 text-xs font-bold transition-all cursor-pointer animate-pulse shadow-lg shadow-cyan-500/10"
                >
                    <RefreshCw v-if="isPlanAnalyzing" class="w-3.5 h-3.5 animate-spin" />
                    <Pause v-else class="w-3.5 h-3.5 text-amber-400" />
                    <span>{{ isRTL ? 'مهمة التنظيم (' + planJobStatus.progress_percent + '%)' : 'Plan Job Active (' + planJobStatus.progress_percent + '%)' }}</span>
                </button>

                <div class="flex items-center gap-2 bg-slate-900/60 border border-white/10 rounded-2xl p-1.5 backdrop-blur-xl">
                <button
                    @click="step = 1"
                    :disabled="isExecuting"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2 cursor-pointer"
                    :class="step === 1 ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20' : 'text-slate-400 hover:text-white'"
                >
                    <span class="w-5 h-5 rounded-full bg-black/20 flex items-center justify-center text-[10px]">1</span>
                    <span>{{ isRTL ? 'الإعداد والمسح' : 'Configure' }}</span>
                </button>

                <ArrowRight class="w-3.5 h-3.5 text-slate-600" :class="isRTL ? 'rotate-180' : ''" />

                <button
                    @click="if (plan.length) step = 2;"
                    :disabled="!plan.length || isExecuting"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2"
                    :class="step === 2 ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20' : 'text-slate-400 hover:text-white disabled:opacity-40 disabled:cursor-not-allowed'"
                >
                    <span class="w-5 h-5 rounded-full bg-black/20 flex items-center justify-center text-[10px]">2</span>
                    <span>{{ isRTL ? 'المعاينة والتحقق' : 'Dry-Run Preview' }}</span>
                </button>

                <ArrowRight class="w-3.5 h-3.5 text-slate-600" :class="isRTL ? 'rotate-180' : ''" />

                <button
                    :disabled="!executionStatus.processed_count && step !== 3"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2"
                    :class="step === 3 ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20' : 'text-slate-400 hover:text-white disabled:opacity-40 disabled:cursor-not-allowed'"
                >
                    <span class="w-5 h-5 rounded-full bg-black/20 flex items-center justify-center text-[10px]">3</span>
                    <span>{{ isRTL ? 'التنفيذ الحي' : 'Live Execution' }}</span>
                </button>
            </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- STEP 1: CONFIGURE SOURCE, TARGET & PRESET STRATEGY       -->
        <!-- ========================================================= -->
        <div v-if="step === 1" class="space-y-8">
            <!-- Source Selector -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Virtual Library Option -->
                <div
                    @click="sourceMode = 'virtual'"
                    class="relative rounded-3xl p-6 border transition-all cursor-pointer backdrop-blur-xl group"
                    :class="sourceMode === 'virtual' ? 'bg-cyan-500/10 border-cyan-500 shadow-xl shadow-cyan-500/10' : 'bg-slate-900/60 border-white/10 hover:border-white/20'"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3.5">
                            <div class="w-12 h-12 rounded-2xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                                <Database class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="font-extrabold text-lg text-white">
                                    {{ isRTL ? 'المكتبة الافتراضية المفهرسة' : 'Current Virtual Library' }}
                                </h3>
                                <p class="text-xs text-slate-400 mt-1">
                                    {{ isRTL ? 'تنظيم جميع الأفلام والمسلسلات الموجودة حالياً داخل قاعدة البيانات' : 'Organize all movies & series already scanned into your local database' }}
                                </p>
                            </div>
                        </div>
                        <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-colors"
                            :class="sourceMode === 'virtual' ? 'border-cyan-400 bg-cyan-500 text-slate-950' : 'border-white/20'">
                            <Check v-if="sourceMode === 'virtual'" class="w-3.5 h-3.5 stroke-[3]" />
                        </div>
                    </div>
                </div>

                <!-- Custom Raw Folder Option -->
                <div
                    @click="sourceMode = 'folder'"
                    class="relative rounded-3xl p-6 border transition-all cursor-pointer backdrop-blur-xl group"
                    :class="sourceMode === 'folder' ? 'bg-cyan-500/10 border-cyan-500 shadow-xl shadow-cyan-500/10' : 'bg-slate-900/60 border-white/10 hover:border-white/20'"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3.5">
                            <div class="w-12 h-12 rounded-2xl bg-purple-500/20 text-purple-400 flex items-center justify-center">
                                <FolderSearch class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="font-extrabold text-lg text-white">
                                    {{ isRTL ? 'مجلد خارجي غير منظم (تحميلات / تورنت)' : 'Raw Unorganized Folder' }}
                                </h3>
                                <p class="text-xs text-slate-400 mt-1">
                                    {{ isRTL ? 'مسح مجلد تورنت أو تحميلات وإعادة هيكلتها بالكامل' : 'Scan a raw downloads/torrent folder and generate a clean target structure' }}
                                </p>
                            </div>
                        </div>
                        <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-colors"
                            :class="sourceMode === 'folder' ? 'border-cyan-400 bg-cyan-500 text-slate-950' : 'border-white/20'">
                            <Check v-if="sourceMode === 'folder'" class="w-3.5 h-3.5 stroke-[3]" />
                        </div>
                    </div>

                    <div v-if="sourceMode === 'folder'" class="mt-4 pt-4 border-t border-white/10 space-y-3" @click.stop>
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-slate-300">
                                {{ isRTL ? 'مسار المجلد المصدري' : 'Source Folder Path' }}
                            </label>
                            <span class="text-[11px] text-slate-400">
                                {{ isRTL ? 'اختر مجلد التحميلات أو التورنت' : 'Select raw media or downloads directory' }}
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <input
                                v-model="sourceFolder"
                                type="text"
                                placeholder="D:/Downloads"
                                class="flex-1 bg-slate-950/80 border border-white/20 rounded-xl px-4 py-2.5 text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-cyan-400"
                            />
                            <button
                                type="button"
                                @click="openFolderBrowser('source')"
                                class="px-4 py-2.5 rounded-xl bg-cyan-500/15 hover:bg-cyan-500/25 border border-cyan-500/30 text-cyan-300 font-bold text-xs flex items-center gap-2 transition-all cursor-pointer hover:scale-105 active:scale-95"
                            >
                                <FolderSearch class="w-4 h-4 text-cyan-400" />
                                <span>{{ isRTL ? 'استعراض...' : 'Browse...' }}</span>
                            </button>
                        </div>
                        <!-- Quick Source Chips -->
                        <div class="flex flex-wrap items-center gap-1.5 pt-1">
                            <span class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mr-1">{{ isRTL ? 'اختصارات سريعة:' : 'Quick Shortcuts:' }}</span>
                            <button
                                v-for="chip in ['D:/Downloads', 'C:/Users/hasan/Downloads', 'H:/Entertainment/Movies', 'H:/Entertainment/TV Shows']"
                                :key="chip"
                                type="button"
                                @click="sourceFolder = chip"
                                class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-[11px] font-mono text-slate-300 transition-all cursor-pointer hover:border-cyan-400/50 hover:text-cyan-300"
                            >
                                {{ chip }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Target Working Root Directory -->
                        <!-- Target Working Root Directory -->
            <div class="rounded-3xl p-6 bg-slate-900/60 border border-white/10 backdrop-blur-xl space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <HardDrive class="w-5 h-5 text-cyan-400" />
                        <h3 class="font-extrabold text-base text-white">
                            {{ isRTL ? 'المجلد الهدف المنظم (Target Destination Root)' : 'Destination Media Root Directory' }}
                        </h3>
                    </div>
                    <span class="text-[11px] text-cyan-400/80 font-mono">
                        {{ isRTL ? 'سيتم إنشاء مجلدات Movies و TV Shows داخله' : 'Creates Movies/ & TV Shows/ here' }}
                    </span>
                </div>
                <p class="text-xs text-slate-400">
                    {{ isRTL ? 'المجلد الرئيسي الذي ستنقل أو تنسخ إليه الأفلام والمسلسلات وفق الهيكل القياسي' : 'The master root folder where clean Movies and TV Shows folders will be created.' }}
                </p>
                <div class="flex gap-2">
                    <input
                        v-model="targetRoot"
                        type="text"
                        placeholder="H:/Entertainment"
                        class="flex-1 bg-slate-950/80 border border-white/20 rounded-xl px-4 py-3 text-sm text-cyan-300 font-mono focus:outline-none focus:border-cyan-400"
                    />
                    <button
                        type="button"
                        @click="openFolderBrowser('target')"
                        class="px-5 py-3 rounded-xl bg-cyan-500/15 hover:bg-cyan-500/25 border border-cyan-500/30 text-cyan-300 font-bold text-xs flex items-center gap-2 transition-all cursor-pointer hover:scale-105 active:scale-95"
                    >
                        <FolderSearch class="w-4 h-4 text-cyan-400" />
                        <span>{{ isRTL ? 'استعراض...' : 'Browse...' }}</span>
                    </button>
                </div>
                <!-- Quick Target Chips -->
                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                    <span class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mr-1">{{ isRTL ? 'وجهات مقترحة:' : 'Recommended Targets:' }}</span>
                    <button
                        v-for="chip in ['H:/Entertainment', 'H:/Entertainment/Movies', 'H:/Entertainment/TV Shows']"
                        :key="chip"
                        type="button"
                        @click="targetRoot = chip"
                        class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-[11px] font-mono text-slate-300 transition-all cursor-pointer hover:border-cyan-400/50 hover:text-cyan-300"
                    >
                        {{ chip }}
                    </button>
                </div>
            </div>

            <!-- Preset Strategy Catalog -->
            <div class="rounded-3xl p-6 sm:p-8 bg-slate-900/70 border border-white/10 backdrop-blur-2xl space-y-6 shadow-2xl">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-white/10 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                            <Layers class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-black text-lg text-white">
                                {{ isRTL ? 'استراتيجيات وقوالب التسمية القياسية' : 'Naming & Structure Presets' }}
                            </h3>
                            <p class="text-xs text-slate-400">
                                {{ isRTL ? 'اختر هيكل المجلدات المفضل لديك. الأنماط الكاملة موضحة لكل من الأفلام والمسلسلات:' : 'Choose your desired library structure. Full movie & series patterns are displayed below:' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 2-Column Wide Grid with Full Pattern Visibility -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div
                        v-for="strat in strategies"
                        :key="strat.id"
                        @click="selectStrategy(strat.id)"
                        class="p-5 rounded-3xl border transition-all cursor-pointer relative flex flex-col justify-between gap-4 group"
                        :class="selectedStrategy === strat.id
                            ? 'bg-cyan-500/10 border-cyan-400 shadow-xl shadow-cyan-500/15 ring-1 ring-cyan-400/50'
                            : 'bg-slate-950/60 border-white/10 hover:border-white/20 hover:bg-slate-950/80'"
                    >
                        <!-- Card Header -->
                        <div>
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                                        :class="selectedStrategy === strat.id ? 'bg-cyan-500 text-slate-950' : 'bg-white/10 text-slate-300'">
                                        {{ strat.tag }}
                                    </span>
                                    <h4 class="font-black text-base text-white">
                                        {{ isRTL ? strat.nameAr : strat.nameEn }}
                                    </h4>
                                </div>

                                <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center shrink-0 transition-colors"
                                    :class="selectedStrategy === strat.id ? 'border-cyan-400 bg-cyan-400 text-slate-950' : 'border-white/20'">
                                    <Check v-if="selectedStrategy === strat.id" class="w-3.5 h-3.5 stroke-[3]" />
                                </div>
                            </div>

                            <p class="text-xs text-slate-400 leading-relaxed">
                                {{ isRTL ? strat.descAr : strat.descEn }}
                            </p>
                        </div>

                        <!-- Full Pattern Visibility Boxes (Movies & TV Shows) -->
                        <div class="space-y-2.5 pt-2 border-t border-white/10 font-mono text-xs">
                            <!-- Movie Pattern -->
                            <div class="rounded-xl bg-slate-950/90 border border-white/10 p-3 space-y-1">
                                <div class="flex items-center justify-between text-[10px] font-sans font-extrabold uppercase text-cyan-400">
                                    <div class="flex items-center gap-1.5">
                                        <Film class="w-3.5 h-3.5" />
                                        <span>Movie Pattern</span>
                                    </div>
                                </div>
                                <div class="text-cyan-200 font-bold break-all whitespace-normal leading-relaxed text-[11px]">
                                    {{ strat.movie }}
                                </div>
                                <div class="text-[10px] text-slate-500 truncate pt-0.5 font-sans italic" :title="strat.exampleMovie">
                                    <span class="text-slate-400 font-bold not-italic">e.g. </span>{{ strat.exampleMovie }}
                                </div>
                            </div>

                            <!-- Series Pattern -->
                            <div class="rounded-xl bg-slate-950/90 border border-white/10 p-3 space-y-1">
                                <div class="flex items-center justify-between text-[10px] font-sans font-extrabold uppercase text-purple-400">
                                    <div class="flex items-center gap-1.5">
                                        <Tv class="w-3.5 h-3.5" />
                                        <span>Series Pattern</span>
                                    </div>
                                </div>
                                <div class="text-purple-200 font-bold break-all whitespace-normal leading-relaxed text-[11px]">
                                    {{ strat.series }}
                                </div>
                                <div class="text-[10px] text-slate-500 truncate pt-0.5 font-sans italic" :title="strat.exampleSeries">
                                    <span class="text-slate-400 font-bold not-italic">e.g. </span>{{ strat.exampleSeries }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom Template Editor -->
                <div v-if="showCustomTemplates || selectedStrategy === 'custom'" class="pt-4 border-t border-white/10 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">
                                {{ isRTL ? 'قالب تسمية الأفلام' : 'Movie Naming Pattern' }}
                            </label>
                            <input
                                v-model="movieTemplate"
                                type="text"
                                class="w-full bg-slate-950/80 border border-white/20 rounded-xl px-3 py-2 text-xs font-mono text-cyan-300"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">
                                {{ isRTL ? 'قالب تسمية المسلسلات' : 'Series Naming Pattern' }}
                            </label>
                            <input
                                v-model="seriesTemplate"
                                type="text"
                                class="w-full bg-slate-950/80 border border-white/20 rounded-xl px-3 py-2 text-xs font-mono text-purple-300"
                            />
                        </div>
                    </div>

                    <!-- Available Dynamic Tokens -->
                    <div>
                        <span class="text-[11px] font-bold text-slate-400 block mb-2">
                            {{ isRTL ? 'انقر لإضافة الرموز الديناميكية:' : 'Click to append dynamic token:' }}
                        </span>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="tok in tokens"
                                :key="tok.token"
                                @click="appendToken('movie', tok.token)"
                                class="px-2 py-1 rounded-lg bg-white/5 hover:bg-cyan-500/20 text-slate-300 hover:text-cyan-300 text-[11px] font-mono border border-white/10 transition-colors cursor-pointer"
                                :title="tok.desc"
                            >
                                {{ tok.token }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            
                <!-- Smart Toggles: Collections & Episode Title -->
                <div class="flex flex-wrap items-center gap-6 pt-3 border-t border-white/5">
                    <!-- Group Movies into Collections Toggle -->
                    <label class="flex items-center gap-2 cursor-pointer select-none text-xs text-slate-300 hover:text-white transition-colors">
                        <input
                            type="checkbox"
                            :checked="groupCollections"
                            @change="toggleGroupCollections"
                            class="rounded border-white/20 bg-slate-900 text-amber-500 focus:ring-amber-400 cursor-pointer"
                        />
                        <span class="font-medium flex items-center gap-1.5">
                            <span>📦</span>
                            <span>{{ isRTL ? 'تجميع سلاسل ومجموعات الأفلام تلقائياً (Collections)' : 'Auto-Group Movie Collections ({Collection})' }}</span>
                        </span>
                    </label>

                    <!-- Episode Title Toggle -->
                    <label class="flex items-center gap-2 cursor-pointer select-none text-xs text-slate-300 hover:text-white transition-colors">
                        <input
                            type="checkbox"
                            :checked="omitEpisodeTitle"
                            @change="toggleOmitEpisodeTitle"
                            class="rounded border-white/20 bg-slate-900 text-purple-600 focus:ring-purple-500 cursor-pointer"
                        />
                        <span class="font-medium">
                            {{ isRTL ? 'إلغاء اسم الحلقة من المسلسلات (رقم الحلقة فقط مثل S01E01)' : 'Omit Episode Title (Clean Show - S01E01 format)' }}
                        </span>
                    </label>
                </div>

                <!-- Directory Watcher & Auto-Organize Panel (Collapsible) -->
                <div class="mt-6 rounded-2xl bg-slate-900/60 border border-white/10 overflow-hidden transition-all">
                    <div
                        @click="isWatcherExpanded = !isWatcherExpanded"
                        class="p-4 flex items-center justify-between flex-wrap gap-2 cursor-pointer hover:bg-white/[0.02] transition-colors select-none"
                    >
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                                <FolderSync class="w-4 h-4" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="text-xs font-bold text-white">
                                        {{ isRTL ? 'مراقب المجلدات التلقائي (Background Watcher)' : 'Automated Directory Watcher' }}
                                    </h4>
                                    <span
                                        :class="watcher.enabled ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-slate-800 text-slate-400 border-white/10'"
                                        class="px-2 py-0.5 rounded-full border text-[10px] font-bold flex items-center gap-1"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="watcher.enabled ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'"></span>
                                        {{ watcher.enabled ? (isRTL ? 'مفعّل' : 'Active') : (isRTL ? 'متوقف' : 'Disabled') }}
                                    </span>
                                    <span v-if="watcher.watched_folders && watcher.watched_folders.length" class="text-[10px] text-slate-500 font-mono">
                                        ({{ watcher.watched_folders.length }} {{ isRTL ? 'مجلدات' : 'folders' }})
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-400">
                                    {{ isRTL ? 'يراقب مجلدات التنزيل وينظم الوسائط المكتملة تلقائياً' : 'Monitors download directories and auto-organizes completed media' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2" @click.stop>
                            <button
                                @click="runWatcherNow"
                                :disabled="isRunningWatcher"
                                class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer disabled:opacity-50"
                            >
                                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isRunningWatcher }" />
                                <span>{{ isRTL ? 'فحص فوري' : 'Scan Now' }}</span>
                            </button>
                            <button
                                @click="toggleWatcher"
                                :disabled="isTogglingWatcher"
                                :class="watcher.enabled ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-slate-800 text-slate-400 border-white/10'"
                                class="px-3 py-1.5 rounded-xl border text-xs font-bold flex items-center gap-1.5 transition-colors cursor-pointer"
                            >
                                {{ watcher.enabled ? (isRTL ? 'إيقاف' : 'Pause') : (isRTL ? 'تشغيل' : 'Enable') }}
                            </button>
                            <button
                                type="button"
                                @click="isWatcherExpanded = !isWatcherExpanded"
                                class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer"
                                :title="isWatcherExpanded ? 'Collapse' : 'Expand'"
                            >
                                <ChevronDown class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': isWatcherExpanded }" />
                            </button>
                        </div>
                    </div>

                    <!-- Watched Folders List & Settings (Expanded) -->
                    <div v-show="isWatcherExpanded" class="p-4 pt-0 space-y-3 border-t border-white/5">
                        <div class="space-y-2 pt-3">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                                {{ isRTL ? 'المجلدات المراقبة حالياً:' : 'Currently Watched Folders:' }}
                            </span>
                            <div v-if="watcher.watched_folders && watcher.watched_folders.length" class="flex flex-wrap gap-2">
                                <div
                                    v-for="folder in watcher.watched_folders"
                                    :key="folder"
                                    class="px-2.5 py-1 rounded-lg bg-slate-950 border border-white/10 text-xs font-mono text-cyan-300 flex items-center gap-2"
                                >
                                    <span>{{ folder }}</span>
                                    <button
                                        @click="removeWatchFolder(folder)"
                                        class="text-slate-500 hover:text-red-400 transition-colors cursor-pointer"
                                        title="Remove"
                                    >
                                        &times;
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-xs text-slate-500 italic">
                                {{ isRTL ? 'لا توجد مجلدات مراقبة مضافة حالياً.' : 'No directories currently registered for auto-watching.' }}
                            </p>

                            <!-- Add Folder Input -->
                            <div class="flex items-center gap-2 pt-1">
                                <input
                                    v-model="newWatchFolder"
                                    type="text"
                                    :placeholder="isRTL ? 'مسار مجلد للمراقبة (مثال D:/Downloads)' : 'Directory path to watch (e.g. D:/Downloads)'"
                                    class="bg-slate-950 border border-white/10 rounded-xl px-3 py-1.5 text-xs text-slate-300 flex-1 font-mono"
                                    @keydown.enter.prevent="addWatchFolder"
                                />
                                <button
                                    @click="addWatchFolder"
                                    class="px-3 py-1.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold transition-colors cursor-pointer"
                                >
                                    {{ isRTL ? 'إضافة للمراقبة' : 'Add Folder' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Start Action Button -->
            <div class="flex justify-end">
                <button
                    @click="handleStartPlanGeneration"
                    :disabled="isPlanAnalyzing || (sourceMode === 'folder' && !sourceFolder.trim())"
                    class="px-8 py-4 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-base shadow-xl shadow-cyan-500/25 flex items-center gap-3 transition-all hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                >
                    <RefreshCw v-if="isScanning" class="w-5 h-5 animate-spin" />
                    <Sparkles v-else class="w-5 h-5" />
                    <span>{{ isScanning ? (isRTL ? 'جاري المسح وإنشاء خطة التنظيم التجريبية...' : 'Scanning & Generating Dry-Run...') : (isRTL ? 'بدء فحص وتجهيز الخطة (Dry-Run)' : 'Generate Organization Plan') }}</span>
                    <ArrowRight class="w-5 h-5" :class="isRTL ? 'rotate-180' : ''" />
                </button>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- STEP 2: INTERACTIVE DRY-RUN TABLE & SELECTION            -->
        <!-- ========================================================= -->
        <div v-else-if="step === 2" class="space-y-6">
            <!-- Plan Summary Toolbar -->
            <div class="p-6 rounded-3xl bg-slate-900/60 border border-white/10 backdrop-blur-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-white flex items-center gap-2">
                        <span>{{ isRTL ? 'خطة التنظيم المقترحة' : 'Organization Plan Preview' }}</span>
                        <span class="px-2.5 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300 text-xs font-mono font-bold">
                            {{ plan.length }} Files ({{ readyItemsCount }} Ready)
                        </span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        {{ isRTL ? 'راجع مسارات الوجهة المكتملة، وقم بتحديد أو استثناء الملفات قبل التنفيذ النهائي' : 'Review target standardized paths. Select or deselect items before executing.' }}
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="step = 1"
                        class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs flex items-center gap-2 cursor-pointer transition-colors"
                    >
                        <ArrowLeft class="w-4 h-4" :class="isRTL ? 'rotate-180' : ''" />
                        <span>{{ isRTL ? 'تعديل الإعدادات' : 'Back to Settings' }}</span>
                    </button>

                    <button
                        @click="showExecuteConfirm = true"
                        :disabled="selectedItemsCount === 0"
                        class="px-6 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/20 flex items-center gap-2 cursor-pointer transition-all hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Play class="w-4 h-4 fill-current" />
                        <span>{{ isRTL ? `تنفيذ الخطة (${selectedItemsCount} ملف)` : `Execute Plan (${selectedItemsCount} Files)` }}</span>
                    </button>
                </div>
            </div>

            <!-- Filters & Search Toolbar -->
            <div class="flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-900/40 p-4 rounded-2xl border border-white/10">
                <div class="relative w-full sm:w-80">
                    <Search class="w-4 h-4 text-slate-400 absolute start-3.5 top-1/2 -translate-y-1/2" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="isRTL ? 'بحث بالاسم أو المسار...' : 'Filter files or paths...'"
                        class="w-full bg-slate-950 border border-white/15 rounded-xl ps-9 pe-4 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400"
                    />
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
                    <!-- Media & Category Filters -->
                    <div class="flex flex-wrap items-center bg-slate-950 border border-white/15 rounded-xl p-1 text-xs gap-1">
                        <button
                            @click="filterType = 'all'; currentPage = 1;"
                            class="px-2.5 py-1 rounded-lg transition-colors cursor-pointer font-medium"
                            :class="filterType === 'all' ? 'bg-cyan-500 text-slate-950 font-bold shadow' : 'text-slate-400 hover:text-white'"
                        >
                            {{ isRTL ? 'الكل' : 'All' }} ({{ plan.length }})
                        </button>
                        <button
                            @click="filterType = 'movie'; currentPage = 1;"
                            class="px-2.5 py-1 rounded-lg transition-colors cursor-pointer flex items-center gap-1 font-medium"
                            :class="filterType === 'movie' ? 'bg-cyan-500 text-slate-950 font-bold shadow' : 'text-slate-400 hover:text-white'"
                        >
                            <Film class="w-3 h-3" />
                            <span>{{ isRTL ? 'أفلام' : 'Movies' }}</span>
                            <span class="text-[10px] opacity-75">({{ movieItemsCount }})</span>
                        </button>
                        <button
                            @click="filterType = 'series'; currentPage = 1;"
                            class="px-2.5 py-1 rounded-lg transition-colors cursor-pointer flex items-center gap-1 font-medium"
                            :class="filterType === 'series' ? 'bg-cyan-500 text-slate-950 font-bold shadow' : 'text-slate-400 hover:text-white'"
                        >
                            <Tv class="w-3 h-3" />
                            <span>{{ isRTL ? 'مسلسلات' : 'TV' }}</span>
                            <span class="text-[10px] opacity-75">({{ seriesItemsCount }})</span>
                        </button>
                        <button
                            @click="filterType = 'collection'; currentPage = 1;"
                            class="px-2.5 py-1 rounded-lg transition-colors cursor-pointer flex items-center gap-1 font-medium"
                            :class="filterType === 'collection' ? 'bg-amber-500 text-slate-950 font-bold shadow' : 'text-slate-400 hover:text-amber-300'"
                        >
                            <span>📦</span>
                            <span>{{ isRTL ? 'سلاسل' : 'Collections' }}</span>
                            <span class="text-[10px] opacity-75">({{ collectionItemsCount }})</span>
                        </button>
                        <button
                            @click="filterType = 'subtitles'; currentPage = 1;"
                            class="px-2.5 py-1 rounded-lg transition-colors cursor-pointer flex items-center gap-1 font-medium"
                            :class="filterType === 'subtitles' ? 'bg-purple-500 text-white font-bold shadow' : 'text-slate-400 hover:text-purple-300'"
                        >
                            <span>💬</span>
                            <span>{{ isRTL ? 'ترجمات' : 'Subs' }}</span>
                            <span class="text-[10px] opacity-75">({{ subtitleItemsCount }})</span>
                        </button>
                    </div>

                    <!-- Bulk Select -->
                    <div class="flex items-center gap-2">
                        <button
                            @click="toggleSelectAll(true)"
                            class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-slate-300 text-xs font-bold transition-colors cursor-pointer"
                        >
                            Select All
                        </button>
                        <button
                            @click="toggleSelectAll(false)"
                            class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-slate-300 text-xs font-bold transition-colors cursor-pointer"
                        >
                            Deselect All
                        </button>
                    </div>
                </div>
            </div>

            <!-- Plan Table -->
            <div class="rounded-3xl border border-white/10 bg-slate-900/60 backdrop-blur-xl overflow-hidden shadow-2xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950/80 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider border-b border-white/10">
                            <tr>
                                <th class="p-4 w-12 text-center">
                                    <span class="sr-only">Select</span>
                                </th>
                                <th class="p-4">{{ isRTL ? 'الاسم والمصنف' : 'Title & Type' }}</th>
                                <th class="p-4">{{ isRTL ? 'المسار الأصلي الحالي' : 'Original Source' }}</th>
                                <th class="p-4 text-cyan-400">{{ isRTL ? 'المسار المنظم الجديد' : 'Standardized Destination' }}</th>
                                <th class="p-4 text-center">{{ isRTL ? 'الحجم' : 'Size' }}</th>
                                <th class="p-4 text-center">{{ isRTL ? 'الحالة' : 'Status' }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 font-mono">
                            <tr
                                v-for="item in paginatedPlan"
                                :key="item.id"
                                class="hover:bg-white/5 transition-colors group"
                                :class="item.selected ? 'bg-cyan-500/[0.03]' : 'opacity-60'"
                            >
                                <td class="p-4 text-center" @click="item.status === 'ready' && (item.selected = !item.selected)">
                                    <input
                                        type="checkbox"
                                        v-model="item.selected"
                                        :disabled="item.status !== 'ready'"
                                        class="rounded bg-slate-950 border-white/20 text-cyan-500 focus:ring-0 cursor-pointer"
                                    />
                                </td>
                                <td class="p-4 font-sans">
                                    <div class="flex items-start gap-2.5">
                                        <Film v-if="item.type === 'movie'" class="w-4 h-4 text-cyan-400 shrink-0 mt-0.5" />
                                        <Tv v-else class="w-4 h-4 text-purple-400 shrink-0 mt-0.5" />
                                        <div class="space-y-1">
                                            <div class="font-extrabold text-white text-xs leading-snug">{{ item.clean_title }}</div>
                                            
                                            <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                                <!-- Collection Badge -->
                                                <span
                                                    v-if="item.collection_name"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/15 border border-amber-500/30 text-amber-300 text-[10px] font-bold"
                                                    :title="`Collection: ${item.collection_name}`"
                                                >
                                                    <span>📦</span>
                                                    <span>{{ item.collection_name }}</span>
                                                </span>

                                                <!-- Primary Genre Badge -->
                                                <span
                                                    v-if="item.genre"
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-slate-300 text-[10px]"
                                                >
                                                    {{ item.genre }}
                                                </span>

                                                <!-- TV Episode -->
                                                <span v-if="item.season && item.episode" class="text-[10px] text-purple-300 font-mono">
                                                    S{{ String(item.season).padStart(2, '0') }}E{{ String(item.episode).padStart(2, '0') }}
                                                </span>

                                                <!-- Resolution -->
                                                <span class="text-[10px] text-slate-500 font-mono">
                                                    {{ item.resolution }}
                                                </span>

                                                <!-- Companion Subtitles Pill -->
                                                <span
                                                    v-if="item.subtitles && item.subtitles.length"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 text-[10px] font-bold cursor-help"
                                                    :title="item.subtitles.map((s: any) => typeof s === 'string' ? s : (s.source_name || s.name || s.source_path)).join('\n')"
                                                >
                                                    <span>💬</span>
                                                    <span>{{ item.subtitles.length }} {{ isRTL ? 'ترجمة' : 'Subs' }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-slate-400 truncate max-w-xs text-[11px]" :title="item.source_path">
                                    {{ item.source_path }}
                                </td>
                                <td class="p-4 truncate max-w-sm text-[11px]" :title="item.destination_path">
                                    <div class="text-cyan-300 font-bold truncate">
                                        {{ item.destination_path }}
                                    </div>
                                    <div v-if="item.collection_name" class="text-[10px] text-amber-400/90 font-sans flex items-center gap-1 mt-0.5">
                                        <span>📁</span>
                                        <span>{{ item.collection_name }}</span>
                                    </div>
                                </td>
                                <td class="p-4 text-center text-slate-400">
                                    {{ item.size_formatted }}
                                </td>
                                <td class="p-4 text-center">
                                    <span
                                        v-if="item.status === 'ready'"
                                        class="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-[10px] font-bold"
                                    >
                                        Ready
                                    </span>
                                    <span
                                        v-else-if="item.status === 'identical'"
                                        class="px-2.5 py-1 rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/30 text-[10px] font-bold"
                                    >
                                        Already Standardized
                                    </span>
                                    <span
                                        v-else
                                        class="px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 text-[10px] font-bold"
                                    >
                                        Destination Exists
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="p-4 border-t border-white/10 bg-slate-950/60 flex items-center justify-between text-xs text-slate-400">
                    <div>
                        Showing {{ (currentPage - 1) * perPage + 1 }} to {{ Math.min(currentPage * perPage, filteredPlan.length) }} of {{ filteredPlan.length }} files
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="currentPage = Math.max(1, currentPage - 1)"
                            :disabled="currentPage === 1"
                            class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white font-bold disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                        >
                            Previous
                        </button>
                        <span class="font-mono font-bold text-white px-2">{{ currentPage }} / {{ totalPages }}</span>
                        <button
                            @click="currentPage = Math.min(totalPages, currentPage + 1)"
                            :disabled="currentPage === totalPages"
                            class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white font-bold disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- STEP 3: LIVE EXECUTION PROGRESS & COMPLETION SCREEN      -->
        <!-- ========================================================= -->
        <div v-else-if="step === 3" class="space-y-6 max-w-4xl mx-auto">
            <!-- Progress Overview Card -->
            <div class="p-8 rounded-3xl bg-slate-900/80 border border-white/10 backdrop-blur-2xl shadow-2xl space-y-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center"
                            :class="executionStatus.is_completed ? 'bg-emerald-500/20 text-emerald-400' : 'bg-cyan-500/20 text-cyan-400'">
                            <CheckCircle2 v-if="executionStatus.is_completed" class="w-8 h-8" />
                            <RefreshCw v-else class="w-8 h-8 animate-spin" />
                        </div>
                        <div>
                            <h2 class="text-2xl font-black text-white">
                                {{ executionStatus.is_completed
                                    ? (isRTL ? 'اكتملت عملية تنظيم المكتبة بنجاح!' : 'Physical Organization Completed!')
                                    : (isRTL ? 'جاري نقل وإعادة هيكلة الملفات على القرص...' : 'Reorganizing Media Files on Disk...') }}
                            </h2>
                            <p class="text-xs text-slate-400 font-mono mt-1 truncate max-w-xl">
                                {{ executionStatus.current_action }}
                            </p>
                        </div>
                    </div>

                    <!-- Mode Badge -->
                    <span class="px-3 py-1.5 rounded-xl font-mono text-xs font-bold uppercase"
                        :class="executeMode === 'move' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'bg-purple-500/20 text-purple-300 border border-purple-500/30'">
                        {{ executeMode === 'move' ? 'Move Mode' : 'Copy Mode' }}
                    </span>
                </div>

                <!-- Horizontal Glowing Progress Bar -->
                <div class="space-y-2">
                    <div class="flex justify-between text-xs font-mono font-bold">
                        <span class="text-slate-400">Progress</span>
                        <span class="text-cyan-400 font-black text-sm">{{ executionStatus.progress_percent }}%</span>
                    </div>
                    <div class="w-full h-3.5 rounded-full bg-slate-950 overflow-hidden p-0.5 border border-white/10">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-cyan-400 via-blue-500 to-purple-500 transition-all duration-300 shadow-[0_0_15px_rgba(34,211,238,0.5)]"
                            :style="{ width: `${executionStatus.progress_percent}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Stats Counters Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-white/5">
                        <div class="text-[11px] text-slate-400 font-bold">Total Files</div>
                        <div class="text-lg font-black text-white font-mono mt-1">{{ executionStatus.total_items }}</div>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-white/5">
                        <div class="text-[11px] text-slate-400 font-bold">Processed</div>
                        <div class="text-lg font-black text-cyan-400 font-mono mt-1">{{ executionStatus.processed_count }}</div>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-white/5">
                        <div class="text-[11px] text-slate-400 font-bold">Successful</div>
                        <div class="text-lg font-black text-emerald-400 font-mono mt-1">{{ executionStatus.successful_count }}</div>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-white/5">
                        <div class="text-[11px] text-slate-400 font-bold">Cleaned Folders</div>
                        <div class="text-lg font-black text-amber-400 font-mono mt-1">{{ executionStatus.cleaned_folders_count || 0 }}</div>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-white/5">
                        <div class="text-[11px] text-slate-400 font-bold">Failed</div>
                        <div class="text-lg font-black font-mono mt-1" :class="executionStatus.failed_count > 0 ? 'text-red-400' : 'text-slate-500'">
                            {{ executionStatus.failed_count }}
                        </div>
                    </div>
                </div>

                <!-- Live Terminal Console Stream -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs font-mono text-slate-400">
                        <div class="flex items-center gap-2">
                            <Terminal class="w-4 h-4 text-cyan-400" />
                            <span>Live Operation Console Log</span>
                        </div>
                        <span class="text-[10px] text-slate-500">{{ executionStatus.logs.length }} events</span>
                    </div>
                    <div
                        ref="logTerminalRef"
                        class="h-48 overflow-y-auto rounded-2xl bg-slate-950 p-4 font-mono text-[11px] border border-white/10 space-y-1.5 select-text"
                    >
                        <div
                            v-for="(log, idx) in executionStatus.logs"
                            :key="idx"
                            class="flex items-start gap-2.5 leading-relaxed"
                            :class="log.type === 'error' ? 'text-red-400' : (log.type === 'success' ? 'text-emerald-400' : 'text-slate-300')"
                        >
                            <span class="text-slate-600 shrink-0">[{{ log.time }}]</span>
                            <span class="break-all">{{ log.message }}</span>
                        </div>
                    </div>
                </div>

                <!-- Action Controls / Completion Buttons -->
                <div class="pt-4 border-t border-white/10 flex flex-wrap items-center justify-between gap-4">
                    <button
                        v-if="isExecuting"
                        @click="cancelExecution"
                        class="px-5 py-2.5 rounded-xl bg-red-500/20 hover:bg-red-500/30 text-red-300 border border-red-500/30 text-xs font-bold flex items-center gap-2 cursor-pointer transition-colors"
                    >
                        <StopCircle class="w-4 h-4" />
                        <span>Cancel Operation</span>
                    </button>

                    <div v-else-if="!executionStatus.is_completed" class="flex flex-wrap items-center gap-3 w-full justify-between">
                        <div class="flex items-center gap-2 text-xs text-amber-300 bg-amber-500/10 border border-amber-500/20 px-3.5 py-2 rounded-xl font-bold">
                            <span>⚠️ Execution Paused / Incomplete</span>
                            <span class="text-slate-400 font-normal font-mono">({{ executionStatus.processed_count }} of {{ executionStatus.total_items }} files processed)</span>
                        </div>

                        <div class="flex items-center gap-3">
                            <button
                                @click="step = 2"
                                class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold flex items-center gap-2 cursor-pointer transition-colors"
                            >
                                <ArrowRight class="w-4 h-4 rotate-180" />
                                <span>Back to Review</span>
                            </button>

                            <button
                                @click="resumeExecution"
                                class="px-6 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/20 flex items-center gap-2 cursor-pointer transition-all hover:scale-105"
                            >
                                <Play class="w-4 h-4 fill-current" />
                                <span>Resume Execution ({{ Math.max(0, executionStatus.total_items - executionStatus.processed_count) }} Remaining)</span>
                            </button>
                        </div>
                    </div>

                    <div v-else class="flex flex-wrap items-center gap-3 w-full justify-between">
                        <button
                            @click="resetOrganizer"
                            class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold flex items-center gap-2 cursor-pointer transition-colors"
                        >
                            <RefreshCw class="w-4 h-4" />
                            <span>Organize Another Folder</span>
                        </button>

                        <div class="flex items-center gap-3">
                            <Link
                                href="/movies"
                                class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold flex items-center gap-2 cursor-pointer transition-colors"
                            >
                                <Film class="w-4 h-4 text-cyan-400" />
                                <span>Browse Movies</span>
                            </Link>

                            <Link
                                href="/series"
                                class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold flex items-center gap-2 cursor-pointer transition-colors"
                            >
                                <Tv class="w-4 h-4 text-purple-400" />
                                <span>Browse Series</span>
                            </Link>

                            <Link
                                href="/scanner"
                                class="px-6 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/20 flex items-center gap-2 cursor-pointer transition-all hover:scale-105"
                            >
                                <Sparkles class="w-4 h-4" />
                                <span>Scan in Library Scanner</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Execute Confirmation Modal -->
        <ConfirmModal
            :show="showExecuteConfirm"
            :title="isRTL ? 'تأكيد تنفيذ خطة التنظيم الفعلية على القرص' : 'Confirm Physical Disk Execution'"
            :message="isRTL
                ? `سيتم ${executeMode === 'move' ? 'نقل وتحديث مسارات' : 'نسخ'} ${selectedItemsCount} ملف وسائط إلى مسار الوجهة المعياري. هل تريد المتابعة؟`
                : `Are you sure you want to ${executeMode} and reorganize ${selectedItemsCount} media files on physical disk?`"
            :confirm-text="isRTL ? 'بدء التنفيذ الفوري' : 'Start Physical Organization'"
            :cancel-text="isRTL ? 'ط¥ظ„ط؛ط§ط،' : 'Cancel'"
            @confirm="executePlan"
            @close="showExecuteConfirm = false"
        >
            <template #extra>
                <div class="mt-4 p-4 rounded-2xl bg-slate-950 border border-white/10 space-y-3 text-xs">
                    <label class="block font-bold text-slate-300">
                        {{ isRTL ? 'طريقة معالجة الملفات:' : 'File Execution Mode:' }}
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            @click="executeMode = 'move'"
                            class="p-3 rounded-xl border flex items-center gap-2.5 transition-all cursor-pointer text-left"
                            :class="executeMode === 'move' ? 'bg-cyan-500/20 border-cyan-400 text-cyan-300 font-bold' : 'border-white/10 text-slate-400 hover:border-white/20'"
                        >
                            <Move class="w-4 h-4 shrink-0" />
                            <div>
                                <div class="font-extrabold text-white text-xs">Move (Recommended)</div>
                                <div class="text-[10px] text-slate-400">Instant on same drive, updates database</div>
                            </div>
                        </button>

                        <button
                            type="button"
                            @click="executeMode = 'copy'"
                            class="p-3 rounded-xl border flex items-center gap-2.5 transition-all cursor-pointer text-left"
                            :class="executeMode === 'copy' ? 'bg-purple-500/20 border-purple-400 text-purple-300 font-bold' : 'border-white/10 text-slate-400 hover:border-white/20'"
                        >
                            <Copy class="w-4 h-4 shrink-0" />
                            <div>
                                <div class="font-extrabold text-white text-xs">Copy</div>
                                <div class="text-[10px] text-slate-400">Keeps originals, requires extra disk space</div>
                            </div>
                        </button>
                    </div>

                    <!-- Auto-Clean Toggle -->
                    <div v-if="executeMode === 'move'" class="flex items-center justify-between p-3 rounded-xl bg-white/5 border border-white/10 mt-3">
                        <div class="flex items-center gap-2.5">
                            <Trash2 class="w-4 h-4 text-amber-400 shrink-0" />
                            <div>
                                <div class="font-bold text-white text-xs">{{ isRTL ? 'تنظيف المجلدات الفارغة بعد النقل' : 'Auto-clean empty leftover folders' }}</div>
                                <div class="text-[10px] text-slate-400">{{ isRTL ? 'حذف مجلدات التورنت الفارغة والملفات الزائدة' : 'Deletes empty source directories & leftover junk files' }}</div>
                            </div>
                        </div>
                        <input
                            type="checkbox"
                            v-model="cleanupEmptyFolders"
                            class="rounded bg-slate-950 border-white/20 text-cyan-500 w-4 h-4 cursor-pointer focus:ring-0"
                        />
                    </div>
                </div>
            </template>
        </ConfirmModal>
    
        <!-- ========================================================= -->
        <!-- MODAL 1: SYSTEM DIRECTORY BROWSER                         -->
        <!-- ========================================================= -->
        <div v-if="showFolderBrowser" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-950/80 backdrop-blur-md animate-in fade-in duration-200">
            <div class="relative w-full max-w-3xl rounded-3xl bg-slate-900 border border-white/15 shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">
                <!-- Header -->
                <div class="p-5 border-b border-white/10 flex items-center justify-between bg-slate-950/50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                            <FolderOpen class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-white">
                                {{ isRTL
                                    ? (browserTarget === 'source' ? 'اختيار المجلد المصدري' : 'اختيار مجلد الوجهة')
                                    : (browserTarget === 'source' ? 'Select Source Directory' : 'Select Destination Directory') }}
                            </h3>
                            <p class="text-xs text-slate-400">
                                {{ isRTL ? 'تصفح وحدات التخزين والمجلدات على نظامك بسهولة' : 'Navigate system drives and folders seamlessly' }}
                            </p>
                        </div>
                    </div>
                    <button
                        @click="showFolderBrowser = false"
                        class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white transition-colors cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Navigation Toolbar -->
                <div class="p-4 border-b border-white/10 bg-slate-950/30 space-y-3">
                    <!-- Quick System Drives -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[10px] uppercase font-extrabold tracking-wider text-slate-500">{{ isRTL ? 'الأقراص:' : 'Drives:' }}</span>
                        <button
                            v-for="drive in browserDrives"
                            :key="drive.path"
                            @click="browsePath(drive.path)"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold font-mono transition-all cursor-pointer flex items-center gap-1.5"
                            :class="browserCurrentPath === drive.path || browserCurrentPath === drive.path.slice(0, 2)
                                ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20'
                                : 'bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10'"
                        >
                            <HardDrive class="w-3.5 h-3.5" />
                            <span>{{ drive.path.slice(0, 2) }}</span>
                        </button>

                        <div class="h-4 w-px bg-white/10 mx-1 hidden sm:block"></div>

                        <!-- Quick Shortcuts -->
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button
                                v-for="sc in browserShortcuts"
                                :key="sc.path"
                                @click="browsePath(sc.path)"
                                class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-[11px] text-slate-300 hover:text-cyan-300 transition-all cursor-pointer"
                            >
                                {{ sc.name }}
                            </button>
                        </div>
                    </div>

                    <!-- Current Path Bar & Parent Navigation -->
                    <div class="flex items-center gap-2 bg-slate-950 border border-white/15 rounded-xl px-3 py-2 text-xs font-mono">
                        <button
                            v-if="browserParentPath"
                            @click="browsePath(browserParentPath)"
                            class="p-1 rounded-lg bg-white/10 hover:bg-white/20 text-cyan-300 transition-colors cursor-pointer flex items-center gap-1 text-xs px-2"
                            :title="isRTL ? 'الرجوع للمجلد السابق' : 'Go to parent directory'"
                        >
                            <FolderUp class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'أعلى' : 'Up' }}</span>
                        </button>
                        <Compass class="w-4 h-4 text-cyan-400 shrink-0" />
                        <span class="text-slate-400 select-all truncate flex-1 font-semibold text-slate-200">
                            {{ browserCurrentPath || (isRTL ? 'جذور وحدات التخزين' : 'Root Drive Selection') }}
                        </span>
                        <input
                            v-if="browserDirectories.length > 5"
                            v-model="browserFilter"
                            type="text"
                            :placeholder="isRTL ? 'تصفية المجلدات...' : 'Filter folders...'"
                            class="bg-white/5 border border-white/10 rounded-lg px-2.5 py-1 text-xs text-white placeholder-slate-500 w-36 focus:outline-none focus:border-cyan-400 font-sans"
                        />
                    </div>
                </div>

                <!-- Directory Listing Content -->
                <div class="flex-1 overflow-y-auto p-4 space-y-1.5 min-h-[250px] custom-scrollbar">
                    <div v-if="isLoadingBrowser" class="flex flex-col items-center justify-center py-16 text-slate-400 space-y-3">
                        <Loader2 class="w-8 h-8 text-cyan-400 animate-spin" />
                        <span class="text-xs">{{ isRTL ? 'جاري قراءة المجلدات...' : 'Reading filesystem...' }}</span>
                    </div>

                    <!-- Root Drive Pickers when no path is active -->
                    <div v-else-if="!browserCurrentPath" class="grid grid-cols-2 sm:grid-cols-3 gap-3 py-4">
                        <button
                            v-for="drive in browserDrives"
                            :key="drive.path"
                            @click="browsePath(drive.path)"
                            class="p-4 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 flex flex-col items-center justify-center text-center gap-2 group transition-all cursor-pointer hover:border-cyan-400/40"
                        >
                            <HardDrive class="w-8 h-8 text-cyan-400 group-hover:scale-110 transition-transform" />
                            <div class="font-extrabold text-sm text-white">{{ drive.name }}</div>
                            <div class="text-[11px] text-slate-400 font-mono">{{ drive.path }}</div>
                        </button>
                    </div>

                    <!-- Empty Directory -->
                    <div v-else-if="filteredBrowserDirs.length === 0" class="flex flex-col items-center justify-center py-14 text-slate-400 space-y-2">
                        <Folder class="w-10 h-10 text-slate-600" />
                        <span class="text-xs">{{ isRTL ? 'لا توجد مجلدات فرعية في هذا المسار.' : 'No subdirectories found in this folder.' }}</span>
                    </div>

                    <!-- Directories List -->
                    <div
                        v-else
                        v-for="dir in filteredBrowserDirs"
                        :key="dir.path"
                        class="flex items-center justify-between p-2.5 rounded-xl bg-white/[0.03] hover:bg-white/[0.08] border border-white/5 hover:border-white/15 transition-all group"
                    >
                        <button
                            type="button"
                            @click="browsePath(dir.path)"
                            class="flex items-center gap-3 flex-1 text-left cursor-pointer overflow-hidden"
                        >
                            <Folder class="w-5 h-5 text-cyan-400/80 group-hover:text-cyan-300 shrink-0 group-hover:scale-110 transition-transform" />
                            <div class="truncate">
                                <span class="text-sm font-semibold text-white group-hover:text-cyan-200 transition-colors">{{ dir.name }}</span>
                                <span v-if="dir.has_children" class="ml-2 text-[10px] px-1.5 py-0.5 rounded bg-white/10 text-slate-400">
                                    {{ isRTL ? 'مجلدات' : 'subfolders' }}
                                </span>
                            </div>
                        </button>

                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                type="button"
                                @click="selectDirectoryItem(dir.path)"
                                class="px-2.5 py-1 rounded-lg bg-cyan-500/20 hover:bg-cyan-500 text-cyan-300 hover:text-slate-950 font-bold text-xs transition-all cursor-pointer"
                            >
                                {{ isRTL ? 'اختيار هذا المجلد' : 'Select' }}
                            </button>
                            <button
                                type="button"
                                @click="browsePath(dir.path)"
                                class="p-1 rounded-lg hover:bg-white/10 text-slate-400 hover:text-white transition-colors cursor-pointer"
                                :title="isRTL ? 'الدخول للمجلد' : 'Open folder'"
                            >
                                <ChevronRight class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-4 border-t border-white/10 bg-slate-950/60 flex items-center justify-between gap-3">
                    <button
                        type="button"
                        @click="showFolderBrowser = false"
                        class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white text-xs font-bold transition-colors cursor-pointer"
                    >
                        {{ isRTL ? 'إلغاء' : 'Cancel' }}
                    </button>

                    <button
                        type="button"
                        :disabled="!browserCurrentPath"
                        @click="selectCurrentBrowserFolder"
                        class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-extrabold text-xs shadow-lg shadow-cyan-500/20 flex items-center gap-2 transition-all disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                    >
                        <Check class="w-4 h-4 stroke-[3]" />
                        <span>{{ isRTL ? 'تأكيد اختيار المجلد الحالي' : 'Use Current Folder' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- MODAL 2: VIRTUAL SCANNER-STYLE BACKGROUND PLAN MODAL      -->
        <!-- ========================================================= -->
        

    </AppLayout>
</template>
