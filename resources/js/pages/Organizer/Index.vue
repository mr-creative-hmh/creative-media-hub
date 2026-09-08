<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
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
const sourceFolder = ref('');
const targetRoot = ref(props.defaultWorkingDir || 'D:/MediaLibrary');
const isScanning = ref(false);
const scannedFiles = ref<any[]>([]);

// Organizing Strategies Preset Catalog
const strategies = [
    {
        id: 'plex',
        tag: 'Plex / Emby',
        nameAr: 'ظ†ظ…ط· Plex ظˆ Emby ط§ظ„ظ‚ظٹط§ط³ظٹ (ظ…ظˆطµظ‰ ط¨ظ‡)',
        nameEn: 'Plex & Emby Standard (Recommended)',
        descAr: 'ظ…ط¬ظ„ط¯ ظ…ط®طµطµ ظ„ظƒظ„ ظپظٹظ„ظ… ظ…ط¹ ط¹ظ„ط§ظ…ط© ط§ظ„ط¯ظ‚ط©طŒ ظˆظ…ط¬ظ„ط¯ط§طھ ظ…ظˆط§ط³ظ… ظƒط§ظ…ظ„ط© ظ„ظ„ظ…ط³ظ„ط³ظ„ط§طھ.',
        descEn: 'Industry standard: dedicated folder per movie, Season subfolders for series.',
        movie: '{Type}/{Title} ({Year})/{Title} ({Year}) [{Resolution}].{ext}',
        series: '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{Resolution}].{ext}',
        exampleMovie: 'Movies/Inception (2010)/Inception (2010) [1080p].mkv',
        exampleSeries: 'TV Shows/Rick and Morty (2013)/Season 01/Rick and Morty - S01E01 - Pilot [1080p].mkv',
    },
    {
        id: 'jellyfin',
        tag: 'Jellyfin / Kodi',
        nameAr: 'ظ†ظ…ط· Jellyfin ظˆ Kodi ط§ظ„ظ†ط¸ظٹظپ',
        nameEn: 'Jellyfin & Kodi Clean Standard',
        descAr: 'ظ‡ظٹظƒظ„ ظ…ط¬ظ„ط¯ط§طھ ظ…ط¨ط³ط· ظˆظ…ط«ط§ظ„ظٹ ظ„ظ…ط®ط¯ظ…ط§طھ JellyfinطŒ KodiطŒ ظˆ Infuse.',
        descEn: 'Clean minimalist structure optimized for Jellyfin, Kodi, and Infuse.',
        movie: '{Type}/{Title} ({Year})/{Title} ({Year}).{ext}',
        series: '{Type}/{Title}/Season {Season:02}/{Title} S{Season:02}E{Episode:02}.{ext}',
        exampleMovie: 'Movies/Interstellar (2014)/Interstellar (2014).mp4',
        exampleSeries: 'TV Shows/Breaking Bad/Season 01/Breaking Bad S01E01.mkv',
    },
    {
        id: 'flat',
        tag: 'Single Directory',
        nameAr: 'ط§ظ„طھط®ط²ظٹظ† ط§ظ„ظ…ط¨ط§ط´ط± (ط¨ط¯ظˆظ† ظ…ط¬ظ„ط¯ط§طھ ظپط±ط¹ظٹط©)',
        nameEn: 'Flat Directory (No Subfolders)',
        descAr: 'ط¬ظ…ظٹط¹ ط§ظ„ظ…ظ„ظپط§طھ ظپظٹ ظ…ط¬ظ„ط¯ ظˆط§ط­ط¯ ظ„ظƒظ„ ظ†ظˆط¹ ط¯ظˆظ† ظ…ط¬ظ„ط¯ط§طھ ظپط±ط¹ظٹط© ظ„ظƒظ„ ظپظٹظ„ظ….',
        descEn: 'Direct single-level storage without nested movie folders.',
        movie: '{Type}/{Title} ({Year}) [{Resolution}].{ext}',
        series: '{Type}/{Title} - S{Season:02}E{Episode:02} [{Resolution}].{ext}',
        exampleMovie: 'Movies/Gladiator (2000) [1080p].mp4',
        exampleSeries: 'TV Shows/Stranger Things - S01E01 [1080p].mkv',
    },
    {
        id: 'alphabetical',
        tag: 'A - Z Index',
        nameAr: 'ط§ظ„طھط±طھظٹط¨ ط§ظ„ط£ط¨ط¬ط¯ظٹ (A - Z)',
        nameEn: 'Alphabetical Categorized (A - Z)',
        descAr: 'طھظ‚ط³ظٹظ… ط§ظ„ط£ظپظ„ط§ظ… ظˆط§ظ„ظ…ط³ظ„ط³ظ„ط§طھ ط¯ط§ط®ظ„ ظ…ط¬ظ„ط¯ط§طھ ط­ط³ط¨ ط§ظ„ط­ط±ظپ ط§ظ„ط£ظˆظ„ ظ„طھط³ظ‡ظٹظ„ ط§ظ„طھطµظپط­.',
        descEn: 'Sorts media into A-Z parent directories for massive libraries.',
        movie: '{Type}/{FirstLetter}/{Title} ({Year})/{Title} ({Year}).{ext}',
        series: '{Type}/{FirstLetter}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02}.{ext}',
        exampleMovie: 'Movies/I/Inception (2010)/Inception (2010).mkv',
        exampleSeries: 'TV Shows/R/Rick and Morty (2013)/Season 01/Rick and Morty - S01E01.mkv',
    },
    {
        id: 'resolution',
        tag: '4K / 1080p Tiered',
        nameAr: 'ط§ظ„طھظ‚ط³ظٹظ… ط­ط³ط¨ ط§ظ„ط¬ظˆط¯ط© ظˆط§ظ„ط¯ظ‚ط©',
        nameEn: 'Resolution Partitioned (4K, 1080p, 720p)',
        descAr: 'ظپطµظ„ ط£ظپظ„ط§ظ… 4K UHD ظˆ 1080p ظپظٹ ظ…ط¬ظ„ط¯ط§طھ ط±ط¦ظٹط³ظٹط© ظ…ط³طھظ‚ظ„ط© ط­ط³ط¨ ط§ظ„ط¬ظˆط¯ط©.',
        descEn: 'Isolates 4K UHD, 1080p FHD, and 720p content into dedicated root tiers.',
        movie: '{Type}/{Resolution}/{Title} ({Year}) [{Source}].{ext}',
        series: '{Type}/{Resolution}/{Title}/Season {Season:02}/{Title} - S{Season:02}E{Episode:02}.{ext}',
        exampleMovie: 'Movies/1080p/Dune (2021) [BluRay].mkv',
        exampleSeries: 'TV Shows/1080p/The Last of Us/Season 01/The Last of Us - S01E01.mkv',
    },
        {
        id: 'genre',
        tag: 'Genre Categorized',
        nameAr: 'طھطµظ†ظٹظپ ط­ط³ط¨ ط§ظ„ظ†ظˆط¹ (Action, Drama, Sci-Fi)',
        nameEn: 'Genre Partitioned (Action, Drama, Sci-Fi)',
        descAr: 'ظٹظ†ط¸ظ… ط§ظ„ط£ظپظ„ط§ظ… ظˆط§ظ„ظ…ط³ظ„ط³ظ„ط§طھ ط¯ط§ط®ظ„ ظ…ط¬ظ„ط¯ط§طھ ط¨ط­ط³ط¨ ط§ظ„طھطµظ†ظٹظپ ط§ظ„ط£ط³ط§ط³ظٹ طھظ„ظ‚ط§ط¦ظٹط§ظ‹.',
        descEn: 'Groups movies and series into folders based on their primary genres automatically.',
        movie: '{Type}/{Genre}/{Title} ({Year})/{Title} ({Year}) [{Resolution}].{ext}',
        series: '{Type}/{Genre}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} [{Resolution}].{ext}',
        exampleMovie: 'Movies/Action/Inception (2010)/Inception (2010) [1080p].mkv',
        exampleSeries: 'TV Shows/Animation/Rick and Morty (2013)/Season 01/Rick and Morty - S01E01 [1080p].mkv',
    },
    {
        id: 'source',
        tag: 'Source Quality (BluRay/WEB)',
        nameAr: 'ط§ظ„طھظ‚ط³ظٹظ… ط­ط³ط¨ ظ…طµط¯ط± ط§ظ„ط¬ظˆط¯ط© (BluRayطŒ WEB-DLطŒ WEBRip)',
        nameEn: 'Source Quality Partitioned (BluRay, WEB-DL, WEBRip, HDTV)',
        descAr: 'ظٹظپطµظ„ ط§ظ„ظ†ط³ط® ط¹ط§ظ„ظٹط© ط§ظ„ط¬ظˆط¯ط© (BluRay Remux) ط¹ظ† ط§ظ„ظ†ط³ط® ط§ظ„ظ…ط¶ط؛ظˆط·ط© (WEBRip/WEB-DL) ظ„ط£ظپط¶ظ„ طھط¬ط±ط¨ط© ظ…ط´ط§ظ‡ط¯ط©.',
        descEn: 'Separates high-fidelity BluRay remuxes from compressed WEB-DL/WEBRip releases.',
        movie: '{Type}/{Source}/{Title} ({Year})/{Title} ({Year}) [{Resolution} {Codec}].{ext}',
        series: '{Type}/{Source}/{Title}/Season {Season:02}/{Title} - S{Season:02}E{Episode:02}.{ext}',
        exampleMovie: 'Movies/BluRay/Dune (2021)/Dune (2021) [2160p HEVC].mkv',
        exampleSeries: 'TV Shows/WEB-DL/The Last of Us/Season 01/The Last of Us - S01E01.mkv',
    },
{
        id: 'custom',
        tag: 'Dynamic Syntax',
        nameAr: 'طھط®طµظٹطµ ظٹط¯ظˆظٹ ظ…طھظ‚ط¯ظ… ظ„ظ„ط±ظ…ظˆط²',
        nameEn: 'Custom Pattern Builder',
        descAr: 'طµظ…ظ… ط§ظ„ظ‚ظˆط§ظ„ط¨ ط¨ط­ط±ظٹط© طھط§ظ…ط© ط¨ط§ط³طھط®ط¯ط§ظ… ط§ظ„ط±ظ…ظˆط² ط§ظ„ط¯ظٹظ†ط§ظ…ظٹظƒظٹط© ط§ظ„ظ…طھط§ط­ط©.',
        descEn: 'Build completely custom folder and file naming patterns with tokens.',
        movie: '{Type}/{Title} ({Year})/{Title} ({Year}) [{Resolution}].{ext}',
        series: '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{Resolution}].{ext}',
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

const selectedStrategy = ref('plex');
const movieTemplate = ref(props.defaultMovieTemplate);
const seriesTemplate = ref(props.defaultSeriesTemplate);
const showCustomTemplates = ref(false);

const selectStrategy = (id: string) => {
    selectedStrategy.value = id;
    const strat = strategies.find(s => s.id === id);
    if (strat) {
        movieTemplate.value = strat.movie;
        seriesTemplate.value = strat.series;
        showCustomTemplates.value = id === 'custom';
    }
};

// Available template tokens
const tokens = [
    { token: '{Type}', desc: 'Movies / TV Shows' },
    { token: '{Title}', desc: 'Clean Title (e.g. Inception)' },
    { token: '{Year}', desc: 'Release Year (e.g. 2010)' },
    { token: '{Resolution}', desc: '1080p / 2160p / 720p' },
    { token: '{Codec}', desc: 'HEVC / x264 / AV1' },
    { token: '{Source}', desc: 'BluRay / WEBRip / WEB-DL / HDTV' },
    { token: '{Genre}', desc: 'Primary Genre (e.g. Action)' },
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
const filterType = ref<'all' | 'movie' | 'series'>('all');
const filterStatus = ref<'all' | 'ready' | 'collision_exists' | 'identical'>('all');
const currentPage = ref(1);
const perPage = ref(20);

const filteredPlan = computed(() => {
    return plan.value.filter(item => {
        const matchesSearch = !searchQuery.value.trim() ||
            item.filename.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            item.clean_title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            item.destination_path.toLowerCase().includes(searchQuery.value.toLowerCase());

        const matchesType = filterType.value === 'all' || item.type === filterType.value;
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

const runNextExecutionBatch = async () => {
    if (!isExecuting.value) return;

    try {
        const res = await fetch('/api/organizer/execute/batch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({ batch_size: 2 }),
        });

        const data = await res.json();
        if (data.status) {
            executionStatus.value = data.status;
            scrollLogsToBottom();
        }

        if (data.has_more && isExecuting.value) {
            // Next batch after a tiny tick to keep UI responsive
            setTimeout(runNextExecutionBatch, 150);
        } else {
            isExecuting.value = false;
        }
    } catch (e) {
        console.error('Batch error:', e);
        isExecuting.value = false;
    }
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

onMounted(() => {
    fetchPlanStatus();
});

onUnmounted(() => {
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
                            {{ isRTL ? 'ط¥ط¹ط§ط¯ط© طھط³ظ…ظٹط© ظˆطھظ†ط¸ظٹظ… ظ…ظ„ظپط§طھ ط§ظ„ظˆط³ط§ط¦ط· ط§ظ„ظپط¹ظ„ظٹط© ظˆظپظ‚ ط§ظ„ظ…ط¹ط§ظٹظٹط± ط§ظ„ط³ظٹظ†ظ…ط§ط¦ظٹط© ط§ظ„ط¹ط§ظ„ظ…ظٹط©' : 'Standardize, rename, and physically restructure media on disk with zero data loss' }}
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
                    <span>{{ isRTL ? 'ط§ظ„ط¥ط¹ط¯ط§ط¯ ظˆط§ظ„ظ…ط³ط­' : 'Configure' }}</span>
                </button>

                <ArrowRight class="w-3.5 h-3.5 text-slate-600" :class="isRTL ? 'rotate-180' : ''" />

                <button
                    @click="if (plan.length) step = 2;"
                    :disabled="!plan.length || isExecuting"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2"
                    :class="step === 2 ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20' : 'text-slate-400 hover:text-white disabled:opacity-40 disabled:cursor-not-allowed'"
                >
                    <span class="w-5 h-5 rounded-full bg-black/20 flex items-center justify-center text-[10px]">2</span>
                    <span>{{ isRTL ? 'ط§ظ„ظ…ط¹ط§ظٹظ†ط© ظˆط§ظ„طھط­ظ‚ظ‚' : 'Dry-Run Preview' }}</span>
                </button>

                <ArrowRight class="w-3.5 h-3.5 text-slate-600" :class="isRTL ? 'rotate-180' : ''" />

                <button
                    :disabled="!executionStatus.processed_count && step !== 3"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2"
                    :class="step === 3 ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20' : 'text-slate-400 hover:text-white disabled:opacity-40 disabled:cursor-not-allowed'"
                >
                    <span class="w-5 h-5 rounded-full bg-black/20 flex items-center justify-center text-[10px]">3</span>
                    <span>{{ isRTL ? 'ط§ظ„طھظ†ظپظٹط° ط§ظ„ط­ظٹ' : 'Live Execution' }}</span>
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
                                    {{ isRTL ? 'ط§ظ„ظ…ظƒطھط¨ط© ط§ظ„ط§ظپطھط±ط§ط¶ظٹط© ط§ظ„ظ…ظپظ‡ط±ط³ط©' : 'Current Virtual Library' }}
                                </h3>
                                <p class="text-xs text-slate-400 mt-1">
                                    {{ isRTL ? 'طھظ†ط¸ظٹظ… ط¬ظ…ظٹط¹ ط§ظ„ط£ظپظ„ط§ظ… ظˆط§ظ„ظ…ط³ظ„ط³ظ„ط§طھ ط§ظ„ظ…ظˆط¬ظˆط¯ط© ط­ط§ظ„ظٹط§ظ‹ ط¯ط§ط®ظ„ ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ' : 'Organize all movies & series already scanned into your local database' }}
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
                                v-for="chip in ['D:/Downloads', 'D:/Downloads/Videos', 'H:/Entertainment', 'H:/Entertainment/Movies', 'H:/Entertainment/TV Shows']"
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
                        v-for="chip in ['H:/Entertainment', 'H:/Entertainment/Movies', 'H:/Entertainment/TV Shows', 'D:/MediaLibrary', 'C:/Users/hasan/Herd/creative-media-hub/storage/app/media']"
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
                                {{ isRTL ? 'ط§ط³طھط±ط§طھظٹط¬ظٹط§طھ ظˆظ‚ظˆط§ظ„ط¨ ط§ظ„طھط³ظ…ظٹط© ط§ظ„ظ‚ظٹط§ط³ظٹط©' : 'Naming & Structure Presets' }}
                            </h3>
                            <p class="text-xs text-slate-400">
                                {{ isRTL ? 'ط§ط®طھط± ظ‡ظٹظƒظ„ ط§ظ„ظ…ط¬ظ„ط¯ط§طھ ط§ظ„ظ…ظپط¶ظ„ ظ„ط¯ظٹظƒ. ط§ظ„ط£ظ†ظ…ط§ط· ط§ظ„ظƒط§ظ…ظ„ط© ظ…ظˆط¶ط­ط© ظ„ظƒظ„ ظ…ظ† ط§ظ„ط£ظپظ„ط§ظ… ظˆط§ظ„ظ…ط³ظ„ط³ظ„ط§طھ:' : 'Choose your desired library structure. Full movie & series patterns are displayed below:' }}
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
                                {{ isRTL ? 'ظ‚ط§ظ„ط¨ طھط³ظ…ظٹط© ط§ظ„ط£ظپظ„ط§ظ…' : 'Movie Naming Pattern' }}
                            </label>
                            <input
                                v-model="movieTemplate"
                                type="text"
                                class="w-full bg-slate-950/80 border border-white/20 rounded-xl px-3 py-2 text-xs font-mono text-cyan-300"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">
                                {{ isRTL ? 'ظ‚ط§ظ„ط¨ طھط³ظ…ظٹط© ط§ظ„ظ…ط³ظ„ط³ظ„ط§طھ' : 'Series Naming Pattern' }}
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
                            {{ isRTL ? 'ط§ظ†ظ‚ط± ظ„ط¥ط¶ط§ظپط© ط§ظ„ط±ظ…ظˆط² ط§ظ„ط¯ظٹظ†ط§ظ…ظٹظƒظٹط©:' : 'Click to append dynamic token:' }}
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

            
                <!-- Episode Title Toggle -->
                <div class="flex items-center gap-3 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer select-none text-xs text-slate-300">
                        <input
                            type="checkbox"
                            :checked="omitEpisodeTitle"
                            @change="toggleOmitEpisodeTitle"
                            class="rounded border-white/20 bg-slate-900 text-purple-600 focus:ring-purple-500"
                        />
                        <span class="font-medium">
                            {{ isRTL ? 'إلغاء اسم الحلقة من المسلسلات (رقم الحلقة فقط مثل S01E01)' : 'Omit Episode Title (Clean Show - S01E01 format)' }}
                        </span>
                    </label>
                </div>

                <!-- Directory Watcher & Auto-Organize Panel -->
                <div class="mt-6 p-4 rounded-2xl bg-slate-900/60 border border-white/10 space-y-4">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                                <FolderSync class="w-4 h-4" />
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-white">
                                    {{ isRTL ? 'مراقب المجلدات التلقائي (Background Watcher)' : 'Automated Directory Watcher' }}
                                </h4>
                                <p class="text-[11px] text-slate-400">
                                    {{ isRTL ? 'يراقب مجلدات التنزيل وينظم الوسائط المكتملة تلقائياً إلى مكتبة الترفيه' : 'Monitors download directories and auto-organizes completed media into Entertainment' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                @click="runWatcherNow"
                                :disabled="isRunningWatcher"
                                class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer disabled:opacity-50"
                            >
                                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isRunningWatcher }" />
                                {{ isRTL ? 'فحص وتنظيم فوري' : 'Scan & Organize Now' }}
                            </button>
                            <button
                                @click="toggleWatcher"
                                :disabled="isTogglingWatcher"
                                :class="watcher.enabled ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-slate-800 text-slate-400 border-white/10'"
                                class="px-3 py-1.5 rounded-xl border text-xs font-bold flex items-center gap-1.5 transition-colors cursor-pointer"
                            >
                                <span class="w-2 h-2 rounded-full" :class="watcher.enabled ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'"></span>
                                {{ watcher.enabled ? (isRTL ? 'المراقب مفعّل' : 'Watcher Active') : (isRTL ? 'المراقب متوقف' : 'Watcher Disabled') }}
                            </button>
                        </div>
                    </div>

                    <!-- Watched Folders List -->
                    <div class="space-y-2 pt-2 border-t border-white/5">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                            {{ isRTL ? 'المجلدات المراقبة حالياً:' : 'Currently Watched Folders:' }}
                        </span>
                        <div class="flex flex-wrap gap-2">
                            <div
                                v-for="folder in watcher.watched_folders"
                                :key="folder"
                                class="px-2.5 py-1 rounded-lg bg-slate-950 border border-white/10 text-xs font-mono text-cyan-300 flex items-center gap-2"
                            >
                                <span>{{ folder }}</span>
                                <button
                                    @click="removeWatchFolder(folder)"
                                    class="text-slate-500 hover:text-red-400 transition-colors"
                                    title="Remove"
                                >
                                    &times;
                                </button>
                            </div>
                        </div>

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

            <!-- Start Action Button -->
            <div class="flex justify-end">
                <button
                    @click="handleStartPlanGeneration"
                    :disabled="isPlanAnalyzing || (sourceMode === 'folder' && !sourceFolder.trim())"
                    class="px-8 py-4 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-base shadow-xl shadow-cyan-500/25 flex items-center gap-3 transition-all hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                >
                    <RefreshCw v-if="isScanning" class="w-5 h-5 animate-spin" />
                    <Sparkles v-else class="w-5 h-5" />
                    <span>{{ isScanning ? (isRTL ? 'ط¬ط§ط±ظٹ ط§ظ„ظ…ط³ط­ ظˆط¥ظ†ط´ط§ط، ط®ط·ط© ط§ظ„طھظ†ط¸ظٹظ…...' : 'Scanning & Generating Dry-Run...') : (isRTL ? 'ط¨ط¯ط، ظپط­طµ ظˆطھط¬ظ‡ظٹط² ط§ظ„ط®ط·ط© (Dry-Run)' : 'Generate Organization Plan') }}</span>
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
                        <span>{{ isRTL ? 'ط®ط·ط© ط§ظ„طھظ†ط¸ظٹظ… ط§ظ„ظ…ظ‚طھط±ط­ط©' : 'Organization Plan Preview' }}</span>
                        <span class="px-2.5 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300 text-xs font-mono font-bold">
                            {{ plan.length }} Files ({{ readyItemsCount }} Ready)
                        </span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        {{ isRTL ? 'ط±ط§ط¬ط¹ ظ…ط³ط§ط±ط§طھ ط§ظ„ظˆط¬ظ‡ط© ط§ظ„ظ…ظƒطھظ…ظ„ط©طŒ ظˆظ‚ظ… ط¨طھط­ط¯ظٹط¯ ط£ظˆ ط§ط³طھط«ظ†ط§ط، ط§ظ„ظ…ظ„ظپط§طھ ظ‚ط¨ظ„ ط§ظ„طھظ†ظپظٹط° ط§ظ„ظ†ظ‡ط§ط¦ظٹ' : 'Review target standardized paths. Select or deselect items before executing.' }}
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="step = 1"
                        class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs flex items-center gap-2 cursor-pointer transition-colors"
                    >
                        <ArrowLeft class="w-4 h-4" :class="isRTL ? 'rotate-180' : ''" />
                        <span>{{ isRTL ? 'طھط¹ط¯ظٹظ„ ط§ظ„ط¥ط¹ط¯ط§ط¯ط§طھ' : 'Back to Settings' }}</span>
                    </button>

                    <button
                        @click="showExecuteConfirm = true"
                        :disabled="selectedItemsCount === 0"
                        class="px-6 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/20 flex items-center gap-2 cursor-pointer transition-all hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Play class="w-4 h-4 fill-current" />
                        <span>{{ isRTL ? `طھظ†ظپظٹط° ط§ظ„ط®ط·ط© (${selectedItemsCount} ظ…ظ„ظپ)` : `Execute Plan (${selectedItemsCount} Files)` }}</span>
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
                        :placeholder="isRTL ? 'ط¨ط­ط« ط¨ط§ظ„ط§ط³ظ… ط£ظˆ ط§ظ„ظ…ط³ط§ط±...' : 'Filter files or paths...'"
                        class="w-full bg-slate-950 border border-white/15 rounded-xl ps-9 pe-4 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400"
                    />
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
                    <!-- Media Type Filter -->
                    <div class="flex items-center bg-slate-950 border border-white/15 rounded-xl p-1 text-xs">
                        <button
                            @click="filterType = 'all'; currentPage = 1;"
                            class="px-3 py-1 rounded-lg transition-colors cursor-pointer"
                            :class="filterType === 'all' ? 'bg-cyan-500 text-slate-950 font-bold' : 'text-slate-400 hover:text-white'"
                        >
                            All
                        </button>
                        <button
                            @click="filterType = 'movie'; currentPage = 1;"
                            class="px-3 py-1 rounded-lg transition-colors cursor-pointer flex items-center gap-1"
                            :class="filterType === 'movie' ? 'bg-cyan-500 text-slate-950 font-bold' : 'text-slate-400 hover:text-white'"
                        >
                            <Film class="w-3 h-3" /> Movies
                        </button>
                        <button
                            @click="filterType = 'series'; currentPage = 1;"
                            class="px-3 py-1 rounded-lg transition-colors cursor-pointer flex items-center gap-1"
                            :class="filterType === 'series' ? 'bg-cyan-500 text-slate-950 font-bold' : 'text-slate-400 hover:text-white'"
                        >
                            <Tv class="w-3 h-3" /> Series
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
                                <th class="p-4">{{ isRTL ? 'ط§ظ„ط§ط³ظ… ط§ظ„ظ…طµظ†ظپ' : 'Title & Type' }}</th>
                                <th class="p-4">{{ isRTL ? 'ط§ظ„ظ…ط³ط§ط± ط§ظ„ط­ط§ظ„ظٹ' : 'Original Source' }}</th>
                                <th class="p-4 text-cyan-400">{{ isRTL ? 'ط§ظ„ظ…ط³ط§ط± ط§ظ„ظ…ظ†ط¸ظ… ط§ظ„ط¬ط¯ظٹط¯' : 'Standardized Destination' }}</th>
                                <th class="p-4 text-center">{{ isRTL ? 'ط§ظ„ط­ط¬ظ…' : 'Size' }}</th>
                                <th class="p-4 text-center">{{ isRTL ? 'ط§ظ„ط­ط§ظ„ط©' : 'Status' }}</th>
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
                                    <div class="flex items-center gap-2">
                                        <Film v-if="item.type === 'movie'" class="w-4 h-4 text-cyan-400 shrink-0" />
                                        <Tv v-else class="w-4 h-4 text-purple-400 shrink-0" />
                                        <div>
                                            <div class="font-extrabold text-white">{{ item.clean_title }}</div>
                                            <div class="text-[10px] text-slate-500 font-mono">
                                                <span v-if="item.season && item.episode">S{{ String(item.season).padStart(2, '0') }}E{{ String(item.episode).padStart(2, '0') }} â€¢ </span>
                                                <span>{{ item.resolution }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-slate-400 truncate max-w-xs text-[11px]" :title="item.source_path">
                                    {{ item.source_path }}
                                </td>
                                <td class="p-4 text-cyan-300 font-bold truncate max-w-sm text-[11px]" :title="item.destination_path">
                                    {{ item.destination_path }}
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
                                    ? (isRTL ? 'ط§ظƒطھظ…ظ„طھ ط¹ظ…ظ„ظٹط© طھظ†ط¸ظٹظ… ط§ظ„ظ…ظƒطھط¨ط© ط¨ظ†ط¬ط§ط­!' : 'Physical Organization Completed!')
                                    : (isRTL ? 'ط¬ط§ط±ظٹ ظ†ظ‚ظ„ ظˆط¥ط¹ط§ط¯ط© ظ‡ظٹظƒظ„ط© ط§ظ„ظ…ظ„ظپط§طھ ط¹ظ„ظ‰ ط§ظ„ظ‚ط±طµ...' : 'Reorganizing Media Files on Disk...') }}
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
            :title="isRTL ? 'طھط£ظƒظٹط¯ طھظ†ظپظٹط° ط®ط·ط© ط§ظ„طھظ†ط¸ظٹظ… ط§ظ„ظپط¹ظ„ظٹط© ط¹ظ„ظ‰ ط§ظ„ظ‚ط±طµ' : 'Confirm Physical Disk Execution'"
            :message="isRTL
                ? `ط³ظٹطھظ… ${executeMode === 'move' ? 'ظ†ظ‚ظ„ ظˆطھط­ط¯ظٹط« ظ…ط³ط§ط±ط§طھ' : 'ظ†ط³ط®'} ${selectedItemsCount} ظ…ظ„ظپ ظˆط³ط§ط¦ط· ط¥ظ„ظ‰ ظ…ط³ط§ط± ط§ظ„ظˆط¬ظ‡ط© ط§ظ„ظ…ط¹ظٹط§ط±ظٹ. ظ‡ظ„ طھط±ظٹط¯ ط§ظ„ظ…طھط§ط¨ط¹ط©طں`
                : `Are you sure you want to ${executeMode} and reorganize ${selectedItemsCount} media files on physical disk?`"
            :confirm-text="isRTL ? 'ط¨ط¯ط، ط§ظ„طھظ†ظپظٹط° ط§ظ„ظپظˆط±ظٹ' : 'Start Physical Organization'"
            :cancel-text="isRTL ? 'ط¥ظ„ط؛ط§ط،' : 'Cancel'"
            @confirm="executePlan"
            @close="showExecuteConfirm = false"
        >
            <template #extra>
                <div class="mt-4 p-4 rounded-2xl bg-slate-950 border border-white/10 space-y-3 text-xs">
                    <label class="block font-bold text-slate-300">
                        {{ isRTL ? 'ط·ط±ظٹظ‚ط© ظ…ط¹ط§ظ„ط¬ط© ط§ظ„ظ…ظ„ظپط§طھ:' : 'File Execution Mode:' }}
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
                                <div class="font-bold text-white text-xs">{{ isRTL ? 'طھظ†ط¸ظٹظپ ط§ظ„ظ…ط¬ظ„ط¯ط§طھ ط§ظ„ظپط§ط±ط؛ط© ط¨ط¹ط¯ ط§ظ„ظ†ظ‚ظ„' : 'Auto-clean empty leftover folders' }}</div>
                                <div class="text-[10px] text-slate-400">{{ isRTL ? 'ط­ط°ظپ ظ…ط¬ظ„ط¯ط§طھ ط§ظ„طھظˆط±ظ†طھ ط§ظ„ظپط§ط±ط؛ط© ظˆط§ظ„ظ…ظ„ظپط§طھ ط§ظ„ط²ط§ط¦ط¯ط©' : 'Deletes empty source directories & leftover junk files' }}</div>
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
