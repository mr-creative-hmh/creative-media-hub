<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';
import {
    FolderSync, FolderSearch, ArrowRight, ArrowLeft, Play, CheckCircle2,
    AlertTriangle, Copy, Move, RefreshCw, Layers, ShieldCheck, Sparkles,
    FolderCheck, FileText, Check, Database, Sliders, ChevronDown
} from 'lucide-vue-next';

const props = defineProps<{
    defaultMovieTemplate: string;
    defaultSeriesTemplate: string;
    defaultWorkingDir?: string;
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
        nameKey: 'organizer.strategy_plex',
        nameAr: 'نمط Plex و Emby القياسي (موصى به)',
        nameEn: 'Plex & Emby Standard (Recommended)',
        descAr: 'ترتيب متكامل مع مجلد مخصص لكل فيلم، ومجلدات مواسم للمسلسلات.',
        descEn: 'Dedicated folder per movie with resolution tag, season folders for series.',
        movie: '{Type}/{Title} ({Year})/{Title} ({Year}) [{Resolution}].{ext}',
        series: '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{Resolution}].{ext}',
    },
    {
        id: 'jellyfin',
        nameKey: 'organizer.strategy_jellyfin',
        nameAr: 'نمط Jellyfin و Kodi المبسط',
        nameEn: 'Jellyfin & Kodi Clean Standard',
        descAr: 'تسمية نظيفة قياسية متوافقة تماماً مع Jellyfin و Infuse.',
        descEn: 'Clean standardized folder structure optimized for Jellyfin & Infuse.',
        movie: '{Type}/{Title} ({Year})/{Title} ({Year}).{ext}',
        series: '{Type}/{Title}/Season {Season:02}/{Title} S{Season:02}E{Episode:02}.{ext}',
    },
    {
        id: 'flat',
        nameKey: 'organizer.strategy_flat',
        nameAr: 'المكتبة المسطحة (بدون مجلدات فرعية)',
        nameEn: 'Flat Directory (No Subfolders)',
        descAr: 'تخزين جميع الأفلام والحلقات مباشرة في مجلد واحد مع علامات الجودة.',
        descEn: 'All media files stored directly in Movies/TV root without individual subfolders.',
        movie: '{Type}/{Title} ({Year}) [{Resolution}].{ext}',
        series: '{Type}/{Title} - S{Season:02}E{Episode:02} [{Resolution}].{ext}',
    },
    {
        id: 'alphabetical',
        nameKey: 'organizer.strategy_az',
        nameAr: 'الترتيب الأبجدي (A - Z)',
        nameEn: 'Alphabetical Categorized (A - Z)',
        descAr: 'توزيع الأفلام والمسلسلات في مجلدات حسب الحرف الأول لتسهيل التصفح.',
        descEn: 'Organizes movies and series into subfolders by first letter (A, B, C...).',
        movie: '{Type}/{FirstLetter}/{Title} ({Year})/{Title} ({Year}).{ext}',
        series: '{Type}/{FirstLetter}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02}.{ext}',
    },
    {
        id: 'resolution',
        nameKey: 'organizer.strategy_res',
        nameAr: 'التجميع حسب الجودة والدقة',
        nameEn: 'Resolution Partitioned (4K, 1080p, 720p)',
        descAr: 'فصل مكتبة 4K UHD عن 1080p و 720p للحفاظ على مساحات التخزين.',
        descEn: 'Separates 4K UHD, 1080p, and 720p content into dedicated root categories.',
        movie: '{Type}/{Resolution}/{Title} ({Year}) [{Codec}].{ext}',
        series: '{Type}/{Resolution}/{Title}/Season {Season:02}/{Title} - S{Season:02}E{Episode:02}.{ext}',
    },
    {
        id: 'scene',
        nameKey: 'organizer.strategy_scene',
        nameAr: 'نمط فرق الإصدار والنسخ الخاصة (Scene Edition)',
        nameEn: 'Scene & Edition Pattern (Director Cut / Group)',
        descAr: 'يحافظ على النسخ الخاصة (IMAX, Extended, Remaster) واسم فريق الإصدار.',
        descEn: 'Preserves special editions, IMAX cuts, codecs, and release group tags.',
        movie: '{Type}/{Title} ({Year})/{Title} ({Year}) {Edition} [{Resolution}] [{Codec}]-[Group].{ext}',
        series: '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} [{Resolution}]-[Group].{ext}',
    },
    {
        id: 'custom',
        nameKey: 'organizer.strategy_custom',
        nameAr: 'تخصيص يدوي حر (Custom Tokens)',
        nameEn: 'Custom Pattern Builder',
        descAr: 'بناء نمطك الخاص من خلال أزرار العناصر والرموز المتغيرة.',
        descEn: 'Construct your own custom folder and naming schema using interactive tokens.',
        movie: props.defaultMovieTemplate,
        series: props.defaultSeriesTemplate,
    },
];

const selectedStrategyId = ref('plex');
const movieTemplate = ref(props.defaultMovieTemplate);
const seriesTemplate = ref(props.defaultSeriesTemplate);
const activeTargetField = ref<'movie' | 'series'>('movie');

const availableTokens = [
    { token: '{Type}', label: 'Type (Movies/TV Shows)' },
    { token: '{Title}', label: 'Media Title' },
    { token: '{Year}', label: 'Release Year' },
    { token: '{Resolution}', label: '1080p / 4K' },
    { token: '{Codec}', label: 'x264 / HEVC' },
    { token: '{FirstLetter}', label: 'First Letter (A-Z)' },
    { token: '{Edition}', label: 'Edition (IMAX/Extended)' },
    { token: '{Group}', label: 'Release Group' },
    { token: '{Season:02}', label: 'Season S01' },
    { token: '{Episode:02}', label: 'Episode E01' },
    { token: '{EpisodeTitle}', label: 'Episode Title' },
    { token: '{ext}', label: 'File Extension (.mkv)' },
];

const selectStrategy = (strat: typeof strategies[0]) => {
    selectedStrategyId.value = strat.id;
    movieTemplate.value = strat.movie;
    seriesTemplate.value = strat.series;
    if (scannedFiles.value.length > 0) {
        generateDryRun();
    }
};

const insertToken = (tok: string) => {
    selectedStrategyId.value = 'custom';
    if (activeTargetField.value === 'movie') {
        movieTemplate.value += tok;
    } else {
        seriesTemplate.value += tok;
    }
};

const setDefaultWorkingDir = () => {
    if (props.defaultWorkingDir) {
        targetRoot.value = props.defaultWorkingDir;
    }
};

// Dry Run Plan State
const isGeneratingPlan = ref(false);
const plan = ref<any[]>([]);
const executeMode = ref<'move' | 'copy'>('move');
const isExecuting = ref(false);
const executionResult = ref<any | null>(null);

// Confirmation Modal State
const showExecuteConfirm = ref(false);

const readyCount = computed(() => plan.value.filter(i => i.status === 'ready' && i.selected).length);
const collisionCount = computed(() => plan.value.filter(i => i.status === 'collision_exists').length);
const identicalCount = computed(() => plan.value.filter(i => i.status === 'identical').length);

const startSourceScan = async () => {
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
                body: JSON.stringify({ source_path: sourceFolder.value, recursive: true }),
            });
            const data = await res.json();
            scannedFiles.value = data.files || [];
        }

        if (scannedFiles.value.length > 0) {
            await generateDryRun();
            step.value = 2;
        }
    } finally {
        isScanning.value = false;
    }
};

const generateDryRun = async () => {
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
    } finally {
        isGeneratingPlan.value = false;
    }
};

const handleExecuteClick = () => {
    showExecuteConfirm.value = true;
};

const executePlan = async () => {
    showExecuteConfirm.value = false;
    isExecuting.value = true;
    try {
        const res = await fetch('/api/organizer/execute', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                plan: plan.value,
                mode: executeMode.value,
            }),
        });
        executionResult.value = await res.json();
        step.value = 3;
    } finally {
        isExecuting.value = false;
    }
};

const toggleSelectAll = (checked: boolean) => {
    plan.value.forEach(item => {
        if (item.status === 'ready') {
            item.selected = checked;
        }
    });
};
</script>

<template>
    <Head :title="t('nav.organizer')" />

    <AppLayout v-slot="{ play }">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                    <FolderSync class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                        {{ t('nav.organizer') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                        {{ isRTL ? 'إعادة هيكلة وتسمية مكتبة الوسائط الفعلية على القرص الصلب بأعلى معايير الدقة والأمان.' : 'Physical disk restructuring & scene release renamer with automated collision prevention.' }}
                    </p>
                </div>
            </div>

            <!-- Multi-step Indicator -->
            <div class="flex items-center gap-2 glass-panel px-4 py-2 rounded-2xl border border-white/10 text-xs font-bold">
                <span :class="step === 1 ? 'text-cyan-400 font-extrabold' : 'text-slate-400'">1. {{ isRTL ? 'المصدر والقالب' : 'Source & Preset' }}</span>
                <ArrowRight v-if="!isRTL" class="w-3.5 h-3.5 text-slate-600" />
                <ArrowLeft v-else class="w-3.5 h-3.5 text-slate-600" />
                <span :class="step === 2 ? 'text-cyan-400 font-extrabold' : 'text-slate-400'">2. {{ isRTL ? 'المعاينة والتدقيق' : 'Dry-Run Preview' }}</span>
                <ArrowRight v-if="!isRTL" class="w-3.5 h-3.5 text-slate-600" />
                <ArrowLeft v-else class="w-3.5 h-3.5 text-slate-600" />
                <span :class="step === 3 ? 'text-cyan-400 font-extrabold' : 'text-slate-400'">3. {{ isRTL ? 'التنفيذ' : 'Execute' }}</span>
            </div>
        </div>

        <!-- STEP 1: Configuration & Preset Selection -->
        <div v-if="step === 1" class="space-y-6">
            <!-- Source & Destination Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Source Selection -->
                <div class="glass-panel rounded-3xl p-6 border border-white/10 space-y-4">
                    <h3 class="font-extrabold text-base text-white flex items-center gap-2">
                        <FolderSearch class="w-4 h-4 text-cyan-400" />
                        <span>{{ isRTL ? 'مصدر الملفات المراد تنظيمها' : 'Source Media Origin' }}</span>
                    </h3>

                    <div class="grid grid-cols-2 gap-3">
                        <button
                            @click="sourceMode = 'virtual'"
                            class="p-4 rounded-2xl border text-left transition-all cursor-pointer"
                            :class="sourceMode === 'virtual' ? 'bg-cyan-500/20 border-cyan-500/50 text-white shadow-lg shadow-cyan-500/10' : 'bg-white/5 border-white/10 text-slate-400 hover:bg-white/10'"
                        >
                            <div class="flex items-center justify-between mb-1">
                                <Database class="w-5 h-5 text-cyan-400" />
                                <Check v-if="sourceMode === 'virtual'" class="w-4 h-4 text-cyan-400" />
                            </div>
                            <div class="font-bold text-sm text-white">{{ isRTL ? 'المكتبة المفهرسة الحالية' : 'Virtual Scanned Catalog' }}</div>
                            <div class="text-[11px] text-slate-400 mt-1">{{ isRTL ? 'تحميل جميع الملفات المفحوصة سلفاً' : 'Use all media already indexed in database' }}</div>
                        </button>

                        <button
                            @click="sourceMode = 'folder'"
                            class="p-4 rounded-2xl border text-left transition-all cursor-pointer"
                            :class="sourceMode === 'folder' ? 'bg-cyan-500/20 border-cyan-500/50 text-white shadow-lg shadow-cyan-500/10' : 'bg-white/5 border-white/10 text-slate-400 hover:bg-white/10'"
                        >
                            <div class="flex items-center justify-between mb-1">
                                <FolderSearch class="w-5 h-5 text-indigo-400" />
                                <Check v-if="sourceMode === 'folder'" class="w-4 h-4 text-indigo-400" />
                            </div>
                            <div class="font-bold text-sm text-white">{{ isRTL ? 'مجلد خارجي مخصص' : 'Custom Disk Folder' }}</div>
                            <div class="text-[11px] text-slate-400 mt-1">{{ isRTL ? 'فحص مجلد تنزيلات جديد على القرص' : 'Scan any incoming downloads folder' }}</div>
                        </button>
                    </div>

                    <div v-if="sourceMode === 'folder'" class="pt-2">
                        <label class="text-xs font-bold text-slate-400 mb-1 block">{{ isRTL ? 'مسار المجلد المصدر' : 'Source Directory Path' }}</label>
                        <input
                            type="text"
                            v-model="sourceFolder"
                            placeholder="e.g. C:/Downloads/Torrents"
                            class="w-full h-11 rounded-xl bg-white/[0.04] border border-white/15 px-4 text-xs font-mono text-white focus:border-cyan-500 outline-none"
                        />
                    </div>
                </div>

                <!-- Destination Target Root -->
                <div class="glass-panel rounded-3xl p-6 border border-white/10 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-extrabold text-base text-white flex items-center gap-2">
                            <FolderCheck class="w-4 h-4 text-emerald-400" />
                            <span>{{ isRTL ? 'مجلد الوجهة المنظمة الرئيسي' : 'Destination Library Root' }}</span>
                        </h3>

                        <button
                            v-if="defaultWorkingDir"
                            @click="setDefaultWorkingDir"
                            class="px-3 py-1 rounded-xl bg-cyan-500/15 hover:bg-cyan-500/25 border border-cyan-500/30 text-cyan-300 text-[11px] font-bold transition-all cursor-pointer shadow-sm flex items-center gap-1.5"
                            :title="defaultWorkingDir"
                        >
                            <Sparkles class="w-3 h-3 text-cyan-400" />
                            <span>{{ isRTL ? 'تعيين المجلد الافتراضي' : 'Set Default (Working Dir)' }}</span>
                        </button>
                    </div>

                    <div class="space-y-2">
                        <input
                            type="text"
                            v-model="targetRoot"
                            placeholder="e.g. D:/MediaLibrary"
                            class="w-full h-11 rounded-xl bg-white/[0.04] border border-white/15 px-4 text-xs font-mono text-white focus:border-cyan-500 outline-none"
                        />
                        <p class="text-[11px] text-slate-500">
                            {{ isRTL ? 'سيتم إنشاء المجلدات الفرعية (Movies, TV Shows) تلقائياً داخل هذا المسار.' : 'Organized subdirectories (Movies, TV Shows) will be created automatically here.' }}
                        </p>
                    </div>

                    <div class="p-3 rounded-2xl bg-white/[0.02] border border-white/5 flex items-center gap-3">
                        <ShieldCheck class="w-5 h-5 text-cyan-400 shrink-0" />
                        <span class="text-xs text-slate-300">
                            {{ isRTL ? 'حماية عدم الاستبدال: لن يتم الكتابة فوق أي ملف مطابق موجود مسبقاً.' : 'Zero-loss guarantee: Existing files are protected from accidental overwrites.' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Organizing Strategy Presets Grid -->
            <div class="glass-panel rounded-3xl p-6 border border-white/10 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div>
                        <h3 class="font-extrabold text-base text-white flex items-center gap-2">
                            <Layers class="w-4 h-4 text-cyan-400" />
                            <span>{{ isRTL ? 'استراتيجيات وقوالب التنظيم القياسية' : 'Organizing Strategy Presets' }}</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ isRTL ? 'اختر نمط التسمية المعتمد في أنظمة السينما المنزلية وخوادم الوسائط.' : 'Select an industry-standard organizing schema or build your own custom structure.' }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                        v-for="strat in strategies"
                        :key="strat.id"
                        @click="selectStrategy(strat)"
                        class="p-4 rounded-2xl border transition-all cursor-pointer flex flex-col justify-between"
                        :class="selectedStrategyId === strat.id
                            ? 'bg-cyan-500/20 border-cyan-500/50 shadow-lg shadow-cyan-500/10'
                            : 'bg-white/[0.03] border-white/10 hover:bg-white/[0.06]'"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-black text-sm text-white">
                                    {{ isRTL ? strat.nameAr : strat.nameEn }}
                                </h4>
                                <div v-if="selectedStrategyId === strat.id" class="w-5 h-5 rounded-full bg-cyan-500 text-slate-950 flex items-center justify-center">
                                    <Check class="w-3.5 h-3.5 stroke-[3]" />
                                </div>
                            </div>
                            <p class="text-xs text-slate-400 leading-relaxed mb-3">
                                {{ isRTL ? strat.descAr : strat.descEn }}
                            </p>
                        </div>

                        <div class="space-y-1 bg-black/40 p-2.5 rounded-xl border border-white/5 font-mono text-[10px] text-cyan-300 truncate">
                            <div class="truncate">🎬 {{ strat.movie }}</div>
                            <div class="truncate">📺 {{ strat.series }}</div>
                        </div>
                    </div>
                </div>

                <!-- Custom Token Interactive Selector Studio -->
                <div class="pt-4 border-t border-white/10 space-y-4">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <Sliders class="w-4 h-4 text-cyan-400" />
                            <h4 class="font-extrabold text-sm text-white">{{ isRTL ? 'محرر القوالب المخصص ومحدد الرموز' : 'Custom Pattern Builder & Token Palette' }}</h4>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                @click="activeTargetField = 'movie'"
                                class="px-3 py-1 rounded-xl text-xs font-bold transition-all cursor-pointer"
                                :class="activeTargetField === 'movie' ? 'bg-cyan-500 text-slate-950 font-black' : 'bg-white/5 text-slate-400 border border-white/10'"
                            >
                                🎬 {{ isRTL ? 'قالب الأفلام' : 'Movie Template' }}
                            </button>
                            <button
                                @click="activeTargetField = 'series'"
                                class="px-3 py-1 rounded-xl text-xs font-bold transition-all cursor-pointer"
                                :class="activeTargetField === 'series' ? 'bg-cyan-500 text-slate-950 font-black' : 'bg-white/5 text-slate-400 border border-white/10'"
                            >
                                📺 {{ isRTL ? 'قالب المسلسلات' : 'Series Template' }}
                            </button>
                        </div>
                    </div>

                    <!-- Token Pills Palette -->
                    <div class="flex flex-wrap gap-1.5 p-3 rounded-2xl bg-black/30 border border-white/5">
                        <button
                            v-for="tok in availableTokens"
                            :key="tok.token"
                            @click="insertToken(tok.token)"
                            class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-cyan-500/20 border border-white/10 hover:border-cyan-500/40 text-xs font-mono text-slate-300 hover:text-cyan-300 transition-all cursor-pointer active:scale-95"
                            :title="tok.label"
                        >
                            + {{ tok.token }}
                        </button>
                    </div>

                    <!-- Live Inputs -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-slate-400 mb-1 block">🎬 {{ isRTL ? 'قالب مسار الأفلام' : 'Movie Path Pattern' }}</label>
                            <input
                                type="text"
                                v-model="movieTemplate"
                                @focus="activeTargetField = 'movie'"
                                class="w-full h-10 rounded-xl bg-white/[0.04] border border-white/15 px-3 text-xs font-mono text-cyan-300 focus:border-cyan-500 outline-none"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-400 mb-1 block">📺 {{ isRTL ? 'قالب مسار المسلسلات' : 'TV Series Path Pattern' }}</label>
                            <input
                                type="text"
                                v-model="seriesTemplate"
                                @focus="activeTargetField = 'series'"
                                class="w-full h-10 rounded-xl bg-white/[0.04] border border-white/15 px-3 text-xs font-mono text-cyan-300 focus:border-cyan-500 outline-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Next Button -->
                <div class="pt-4 flex justify-end">
                    <button
                        @click="startSourceScan"
                        :disabled="isScanning"
                        class="px-8 py-3 rounded-2xl bg-cyan-500 hover:bg-cyan-400 disabled:opacity-50 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/20 flex items-center gap-2 cursor-pointer transition-all active:scale-95"
                    >
                        <RefreshCw v-if="isScanning" class="w-4 h-4 animate-spin" />
                        <FolderSync v-else class="w-4 h-4" />
                        <span>{{ isScanning ? (isRTL ? 'جاري الفحص وإعداد الخطة...' : 'Scanning & Building Plan...') : (isRTL ? 'بدء فحص الملفات ومعاينة الخطة' : 'Scan & Generate Dry-Run Plan') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- STEP 2: Dry-Run Preview Table -->
        <div v-else-if="step === 2" class="space-y-6">
            <!-- Metrics Summary Bar -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-panel p-4 rounded-2xl border border-white/10">
                    <div class="text-xs text-slate-400 font-bold">{{ isRTL ? 'إجمالي الملفات' : 'Total Items' }}</div>
                    <div class="text-xl font-extrabold text-white mt-1">{{ plan.length }}</div>
                </div>
                <div class="glass-panel p-4 rounded-2xl border border-emerald-500/20 bg-emerald-500/5">
                    <div class="text-xs text-emerald-400 font-bold">{{ isRTL ? 'جاهزة للنقل / النسخ' : 'Ready to Organize' }}</div>
                    <div class="text-xl font-extrabold text-emerald-300 mt-1">{{ readyCount }}</div>
                </div>
                <div class="glass-panel p-4 rounded-2xl border border-amber-500/20 bg-amber-500/5">
                    <div class="text-xs text-amber-400 font-bold">{{ isRTL ? 'ملفات متطابقة سلفاً' : 'Already In Place' }}</div>
                    <div class="text-xl font-extrabold text-amber-300 mt-1">{{ identicalCount }}</div>
                </div>
                <div class="glass-panel p-4 rounded-2xl border border-rose-500/20 bg-rose-500/5">
                    <div class="text-xs text-rose-400 font-bold">{{ isRTL ? 'تعارضات موجودة' : 'Collision Exists' }}</div>
                    <div class="text-xl font-extrabold text-rose-300 mt-1">{{ collisionCount }}</div>
                </div>
            </div>

            <!-- Execution Mode & Action Bar -->
            <div class="glass-panel p-4 rounded-2xl border border-white/10 flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-slate-300">{{ isRTL ? 'طريقة العملية:' : 'Operation Mode:' }}</span>
                    <div class="flex items-center gap-2">
                        <button
                            @click="executeMode = 'move'"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                            :class="executeMode === 'move' ? 'bg-cyan-500 text-slate-950 font-black' : 'bg-white/5 text-slate-400 border border-white/10'"
                        >
                            <Move class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'نقل الملفات (Move - توفير مساحة)' : 'Move Files (Recommended)' }}</span>
                        </button>
                        <button
                            @click="executeMode = 'copy'"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                            :class="executeMode === 'copy' ? 'bg-indigo-500 text-white font-black' : 'bg-white/5 text-slate-400 border border-white/10'"
                        >
                            <Copy class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'نسخ الملفات (Copy - إبقاء الأصل)' : 'Copy Files (Keep Source)' }}</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="step = 1"
                        class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 text-xs font-bold border border-white/10 cursor-pointer"
                    >
                        {{ isRTL ? 'رجوع لتغيير القالب' : 'Back to Settings' }}
                    </button>

                    <button
                        @click="handleExecuteClick"
                        :disabled="readyCount === 0 || isExecuting"
                        class="px-6 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 disabled:opacity-50 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/20 flex items-center gap-2 cursor-pointer transition-all active:scale-95"
                    >
                        <RefreshCw v-if="isExecuting" class="w-4 h-4 animate-spin" />
                        <Play v-else class="w-4 h-4 fill-current" />
                        <span>{{ isRTL ? `تنفيذ عملية التنظيم (${readyCount} ملف)` : `Execute Plan (${readyCount} items)` }}</span>
                    </button>
                </div>
            </div>

            <!-- Dry-Run Table -->
            <div class="glass-panel rounded-3xl p-6 border border-white/10 overflow-x-auto space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            :checked="readyCount === plan.filter(i => i.status === 'ready').length"
                            @change="(e: any) => toggleSelectAll(e.target.checked)"
                            class="rounded border-white/20 text-cyan-500 focus:ring-cyan-500 cursor-pointer"
                        />
                        <span class="text-xs font-bold text-slate-400">{{ isRTL ? 'تحديد الكل' : 'Select All Ready' }}</span>
                    </div>

                    <span class="text-xs text-slate-400 font-mono">{{ plan.length }} items mapped</span>
                </div>

                <div class="divide-y divide-white/5">
                    <div
                        v-for="(item, idx) in plan"
                        :key="idx"
                        class="py-3 flex items-start gap-3 hover:bg-white/[0.02] px-2 rounded-xl transition-colors text-xs"
                    >
                        <input
                            type="checkbox"
                            v-model="item.selected"
                            :disabled="item.status !== 'ready'"
                            class="mt-1 rounded border-white/20 text-cyan-500 focus:ring-cyan-500 cursor-pointer"
                        />

                        <div class="flex-1 min-w-0 space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-extrabold text-white">{{ item.clean_title }}</span>
                                <span v-if="item.year" class="cinema-badge bg-white/5 text-slate-400 text-[10px]">{{ item.year }}</span>
                                <span class="cinema-badge bg-cyan-500/20 text-cyan-300 text-[10px]">{{ item.resolution }}</span>
                                <span v-if="item.size_formatted" class="text-[10px] text-slate-500 font-mono">{{ item.size_formatted }}</span>
                            </div>

                            <!-- Source Path -->
                            <div class="text-[11px] font-mono text-slate-500 truncate" :title="item.source_path">
                                <span class="text-slate-400 font-bold">FROM:</span> {{ item.source_path }}
                            </div>

                            <!-- Destination Path -->
                            <div class="text-[11px] font-mono text-cyan-300 truncate" :title="item.destination_path">
                                <span class="text-cyan-400 font-bold">TO:</span> {{ item.destination_path }}
                            </div>

                            <!-- Attached Subtitles -->
                            <div v-if="item.subtitles && item.subtitles.length > 0" class="flex flex-wrap gap-2 pt-1">
                                <span
                                    v-for="(sub, sIdx) in item.subtitles"
                                    :key="sIdx"
                                    class="cinema-badge bg-indigo-500/20 text-indigo-300 border-indigo-500/30 text-[9px]"
                                >
                                    CC: {{ sub.language }} (Auto-linked)
                                </span>
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <div class="shrink-0">
                            <span
                                v-if="item.status === 'ready'"
                                class="cinema-badge bg-emerald-500/20 text-emerald-300 border border-emerald-500/30"
                            >
                                {{ isRTL ? 'جاهز' : 'Ready' }}
                            </span>
                            <span
                                v-else-if="item.status === 'identical'"
                                class="cinema-badge bg-amber-500/20 text-amber-300 border border-amber-500/30"
                            >
                                {{ isRTL ? 'متطابق' : 'Identical' }}
                            </span>
                            <span
                                v-else
                                class="cinema-badge bg-rose-500/20 text-rose-300 border border-rose-500/30"
                            >
                                {{ isRTL ? 'ملف موجود' : 'Conflict' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 3: Execution Results -->
        <div v-else-if="step === 3" class="space-y-6">
            <div class="glass-panel rounded-3xl p-8 border border-white/10 text-center space-y-4 max-w-xl mx-auto">
                <div class="w-16 h-16 rounded-3xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 flex items-center justify-center mx-auto">
                    <CheckCircle2 class="w-8 h-8" />
                </div>

                <h2 class="text-2xl font-black text-white">
                    {{ isRTL ? 'اكتملت عملية تنظيم المكتبة بنجاح!' : 'Media Library Organized Successfully!' }}
                </h2>

                <p class="text-xs sm:text-sm text-slate-400">
                    {{ isRTL ? `تمت معالجة وتسمية ${executionResult?.processed || 0} ملفاً ونقلها إلى المسار المحدد بدقة.` : `Successfully organized ${executionResult?.processed || 0} media items into target library structure.` }}
                </p>

                <div class="pt-4 flex items-center justify-center gap-3">
                    <button
                        @click="step = 1; plan = [];"
                        class="px-6 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-slate-200 text-xs font-bold cursor-pointer"
                    >
                        {{ isRTL ? 'تنظيم مجلد آخر' : 'Organize Another Folder' }}
                    </button>
                    <a
                        href="/movies"
                        class="px-6 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-black cursor-pointer shadow-lg shadow-cyan-500/20"
                    >
                        {{ isRTL ? 'عرض مكتبة الأفلام' : 'Explore Movies' }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Execution Confirmation Modal -->
        <ConfirmModal
            v-if="showExecuteConfirm"
            variant="warning"
            :title="isRTL ? 'تأكيد تنظيم وإعادة هيكلة الملفات' : 'Confirm Physical Disk Organization'"
            :message="isRTL ? `أنت على وشك ${executeMode === 'move' ? 'نقل' : 'نسخ'} عدد ${readyCount} ملف في القرص الصلب وفقاً للقالب المحدد. هل ترغب في المتابعة؟` : `You are about to ${executeMode} ${readyCount} files on disk using the chosen organizing template. Proceed?`"
            :confirm-text="isRTL ? 'بدء التنفيذ الآن' : 'Execute Now'"
            :cancel-text="t('common.cancel')"
            @confirm="executePlan"
            @cancel="showExecuteConfirm = false"
        />
    </AppLayout>
</template>
