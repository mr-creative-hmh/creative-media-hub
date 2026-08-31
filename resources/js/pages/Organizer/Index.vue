<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    FolderSync, Play, Check, AlertCircle, Sparkles, Folder,
    HardDrive, ArrowRight, ShieldCheck, RefreshCw, FileVideo,
    CheckCircle2, Copy, Move, SlidersHorizontal, Terminal,
    Calendar, Layers, FileType, CheckSquare, Square, Info
} from 'lucide-vue-next';

const props = defineProps<{
    defaultMovieTemplate: string;
    defaultSeriesTemplate: string;
}>();

const { t, isRTL } = useI18n();

// Source Choice
const sourceMode = ref<'virtual' | 'folder'>('virtual');
const sourcePath = ref('');
const targetPath = ref('D:/Media/Organized');
const executionMode = ref<'move' | 'copy'>('move');

// Strategy Presets
const selectedStrategy = ref<'plex' | 'scene' | 'timeline' | 'custom'>('plex');
const movieTemplate = ref(props.defaultMovieTemplate);
const seriesTemplate = ref(props.defaultSeriesTemplate);

const strategies = [
    {
        id: 'plex',
        title: 'Plex & Jellyfin Standard',
        titleAr: 'المعيار القياسي (Plex / Jellyfin)',
        desc: 'Movies/{Title} ({Year})/{Title} ({Year}) & TV Shows/{Title}/Season XX',
        descAr: 'ترتيب حسب الأفلام والمجلدات الخاصة ومسلسلات مع مجلدات المواسم المنظمة.',
        badge: 'Recommended',
        badgeColor: 'cyan',
    },
    {
        id: 'scene',
        title: 'Scene Release Standard',
        titleAr: 'معيار مجموعات المشهد (Scene Standard)',
        desc: '{Title}.{Year}.{Resolution}.{Codec}',
        descAr: 'تسمية نظيفة تتضمن الدقة والترميز بدون مجلدات إضافية.',
        badge: 'Compact',
        badgeColor: 'indigo',
    },
    {
        id: 'timeline',
        title: 'Chronological by Year',
        titleAr: 'ترتيب زمني حسب السنة',
        desc: '{Year}/{Title} ({Year})',
        descAr: 'إنشاء مجلدات رئيسية لكل سنة إنتاج وتجميع الأفلام والمسلسلات بداخلها.',
        badge: 'Timeline',
        badgeColor: 'amber',
    },
];

const handleStrategyChange = (stratId: 'plex' | 'scene' | 'timeline' | 'custom') => {
    selectedStrategy.value = stratId;
    if (stratId === 'plex') {
        movieTemplate.value = '{Type}/{Title} ({Year})/{Title} ({Year}) [{Resolution}].{ext}';
        seriesTemplate.value = '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} [{Resolution}].{ext}';
    } else if (stratId === 'scene') {
        movieTemplate.value = 'Movies/{Title}.{Year}.{Resolution}.{ext}';
        seriesTemplate.value = 'TV Shows/{Title}.S{Season:02}E{Episode:02}.{Resolution}.{ext}';
    } else if (stratId === 'timeline') {
        movieTemplate.value = 'Movies/{Year}/{Title} ({Year}).{ext}';
        seriesTemplate.value = 'TV Shows/{Year}/{Title}/Season {Season:02}/{Title} - S{Season:02}E{Episode:02}.{ext}';
    }
};

// Workflow States
const scannedFiles = ref<any[]>([]);
const isScanningSource = ref(false);
const isGeneratingPlan = ref(false);
const stagedPlan = ref<any[] | null>(null);
const isExecuting = ref(false);
const executionResult = ref<any | null>(null);
const executionLogs = ref<Array<{ time: string; level: string; message: string }>>([]);

const selectAll = ref(true);

const toggleSelectAll = () => {
    selectAll.value = !selectAll.value;
    if (stagedPlan.value) {
        stagedPlan.value.forEach((item: any) => {
            item.selected = selectAll.value;
        });
    }
};

const selectedCount = computed(() => {
    if (!stagedPlan.value) return 0;
    return stagedPlan.value.filter((i: any) => i.selected !== false).length;
});

const loadFiles = async () => {
    isScanningSource.value = true;
    stagedPlan.value = null;
    executionResult.value = null;
    scannedFiles.value = [];

    try {
        if (sourceMode.value === 'virtual') {
            const res = await fetch('/api/organizer/load-virtual');
            const data = await res.json();
            scannedFiles.value = data.files || [];
        } else {
            if (!sourcePath.value.trim()) return;
            const res = await fetch('/api/organizer/scan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
                body: JSON.stringify({
                    source_path: sourcePath.value.trim(),
                    recursive: true,
                }),
            });
            const data = await res.json();
            scannedFiles.value = data.files || [];
        }

        if (scannedFiles.value.length > 0) {
            generateDryRun();
        }
    } finally {
        isScanningSource.value = false;
    }
};

const generateDryRun = async () => {
    if (scannedFiles.value.length === 0) return;
    isGeneratingPlan.value = true;
    stagedPlan.value = null;

    try {
        const res = await fetch('/api/organizer/dry-run', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                files: scannedFiles.value,
                target_root: targetPath.value.trim(),
                movie_template: movieTemplate.value,
                series_template: seriesTemplate.value,
            }),
        });

        const data = await res.json();
        stagedPlan.value = (data.plan || []).map((p: any) => ({ ...p, selected: true }));
    } finally {
        isGeneratingPlan.value = false;
    }
};

const executePlan = async () => {
    if (!stagedPlan.value || stagedPlan.value.length === 0) return;

    const selectedOperations = stagedPlan.value.filter((i: any) => i.selected !== false);
    if (selectedOperations.length === 0) return;

    isExecuting.value = true;
    executionResult.value = null;
    executionLogs.value = [];

    executionLogs.value.push({
        time: new Date().toLocaleTimeString(),
        level: 'info',
        message: `Starting physical ${executionMode.value} operation for ${selectedOperations.length} items...`,
    });

    try {
        const res = await fetch('/api/organizer/execute', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                plan: selectedOperations,
                mode: executionMode.value,
            }),
        });

        const data = await res.json();
        executionResult.value = data;

        if (data.status === 'completed') {
            executionLogs.value.push({
                time: new Date().toLocaleTimeString(),
                level: 'success',
                message: `Successfully processed ${data.processed_count} files! Database paths updated in real-time.`,
            });
        }
    } catch (e: any) {
        executionLogs.value.push({
            time: new Date().toLocaleTimeString(),
            level: 'error',
            message: `Execution failed: ${e.message || 'Unknown error'}`,
        });
    } finally {
        isExecuting.value = false;
    }
};
</script>

<template>
    <Head :title="t('organizer_view.title')" />

    <AppLayout v-slot="{ play }">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                        <FolderSync class="w-5 h-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                            {{ t('organizer_view.title') }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                            {{ t('organizer_view.subtitle') }}
                        </p>
                    </div>
                </div>

                <!-- Mode Indicator Badge -->
                <div class="flex items-center gap-2">
                    <button
                        @click="executionMode = 'move'"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                        :class="executionMode === 'move' ? 'bg-cyan-500 text-slate-950 font-black shadow-lg shadow-cyan-500/20' : 'bg-white/5 text-slate-400 border border-white/10'"
                    >
                        <Move class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'نقل وتحديث المسارات بالداتابيز (Move)' : 'Move & Sync DB Paths' }}</span>
                    </button>
                    <button
                        @click="executionMode = 'copy'"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                        :class="executionMode === 'copy' ? 'bg-cyan-500 text-slate-950 font-black shadow-lg shadow-cyan-500/20' : 'bg-white/5 text-slate-400 border border-white/10'"
                    >
                        <Copy class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'نسخ مع الحفاظ على الأصل (Copy)' : 'Copy (Preserve Source)' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 1. Strategy Presets Row (Creative-FileFlow Architecture) -->
        <div class="space-y-3 mb-8">
            <h3 class="font-bold text-sm text-white flex items-center gap-2">
                <SlidersHorizontal class="w-4 h-4 text-cyan-400" />
                <span>{{ isRTL ? 'اختر استراتيجية ونمط الترتيب (Naming Strategy)' : 'Select Organizing Strategy' }}</span>
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div
                    v-for="st in strategies"
                    :key="st.id"
                    @click="handleStrategyChange(st.id as any)"
                    class="glass-panel p-5 rounded-2xl border transition-all cursor-pointer flex flex-col justify-between space-y-3 relative group"
                    :class="selectedStrategy === st.id ? 'border-cyan-500/60 bg-cyan-500/5 shadow-md shadow-cyan-500/10' : 'border-white/10 hover:border-white/20'"
                >
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-black text-sm text-white">{{ isRTL ? st.titleAr : st.title }}</h4>
                            <span
                                class="cinema-badge text-[9px]"
                                :class="st.badgeColor === 'cyan' ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30' : 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30'"
                            >
                                {{ st.badge }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">{{ isRTL ? st.descAr : st.desc }}</p>
                    </div>

                    <div class="text-[11px] font-mono text-cyan-400/80 truncate pt-2 border-t border-white/5">
                        {{ st.desc }}
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Source & Target Selection Bar -->
        <div class="glass-panel rounded-3xl p-6 border border-white/10 space-y-5 mb-8">
            <!-- Source Selector Tabs -->
            <div class="flex items-center gap-3 border-b border-white/10 pb-4">
                <button
                    @click="sourceMode = 'virtual'"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer"
                    :class="sourceMode === 'virtual' ? 'bg-cyan-500 text-slate-950 font-black' : 'bg-white/5 text-slate-400 border border-white/10'"
                >
                    {{ isRTL ? 'تحميل الملفات من فحص المكتبة الافتراضية' : 'Load from Virtual Library Scan' }}
                </button>
                <button
                    @click="sourceMode = 'folder'"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer"
                    :class="sourceMode === 'folder' ? 'bg-cyan-500 text-slate-950 font-black' : 'bg-white/5 text-slate-400 border border-white/10'"
                >
                    {{ isRTL ? 'فحص مجلد محدد على الهارد ديسك' : 'Scan Specific Disk Folder' }}
                </button>
            </div>

            <!-- Path Inputs -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-if="sourceMode === 'folder'" class="space-y-1.5">
                    <label class="text-[11px] font-bold text-slate-400">{{ isRTL ? 'مسار المجلد المصدر (Source Folder)' : 'Source Directory Path' }}</label>
                    <div class="relative">
                        <input
                            v-model="sourcePath"
                            type="text"
                            placeholder="e.g. D:/Downloads/Unsorted"
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                        />
                        <Folder class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    </div>
                </div>

                <div class="space-y-1.5" :class="sourceMode === 'virtual' ? 'md:col-span-2' : ''">
                    <label class="text-[11px] font-bold text-slate-400">{{ isRTL ? 'مسار الوجهة المنظمة (Target Library Root)' : 'Destination Library Root' }}</label>
                    <div class="relative">
                        <input
                            v-model="targetPath"
                            type="text"
                            placeholder="e.g. D:/Media/Organized or C:/Users/hasan/Videos"
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                        />
                        <HardDrive class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    </div>
                </div>
            </div>

            <!-- Action Button -->
            <div class="flex justify-end pt-2">
                <button
                    @click="loadFiles"
                    :disabled="isScanningSource"
                    class="px-6 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs flex items-center gap-2 active:scale-95 transition-all cursor-pointer shadow-lg shadow-cyan-500/20"
                >
                    <RefreshCw v-if="isScanningSource" class="w-4 h-4 animate-spin" />
                    <Play v-else class="w-4 h-4 fill-current" />
                    <span>{{ isRTL ? 'إنشاء خطة الترتيب والمطابقة (Generate Plan)' : 'Generate Staged Organizing Plan' }}</span>
                </button>
            </div>
        </div>

        <!-- 3. Dry Run Staged Operations Table (Creative-FileFlow Architecture) -->
        <div v-if="stagedPlan && stagedPlan.length > 0" class="glass-panel rounded-3xl p-6 border border-white/10 space-y-5 mb-8">
            <div class="flex items-center justify-between flex-wrap gap-4 pb-4 border-b border-white/10">
                <div class="flex items-center gap-3">
                    <button
                        @click="toggleSelectAll"
                        class="text-xs font-bold text-slate-400 hover:text-white flex items-center gap-1.5 cursor-pointer"
                    >
                        <CheckSquare v-if="selectAll" class="w-4 h-4 text-cyan-400" />
                        <Square v-else class="w-4 h-4" />
                        <span>{{ isRTL ? 'تحديد الكل' : 'Select All' }} ({{ selectedCount }} / {{ stagedPlan.length }})</span>
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="executePlan"
                        :disabled="isExecuting || selectedCount === 0"
                        class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 font-black text-xs flex items-center gap-2 active:scale-95 transition-all cursor-pointer shadow-lg shadow-emerald-500/25"
                    >
                        <RefreshCw v-if="isExecuting" class="w-4 h-4 animate-spin" />
                        <ShieldCheck v-else class="w-4 h-4" />
                        <span>{{ isRTL ? `تنفيذ الترتيب الفعلي (${selectedCount} ملف)` : `Execute Physical Organization (${selectedCount} Files)` }}</span>
                    </button>
                </div>
            </div>

            <!-- Operations Rows -->
            <div class="space-y-3 max-h-[50vh] overflow-y-auto">
                <div
                    v-for="(op, oIdx) in stagedPlan"
                    :key="oIdx"
                    class="p-4 rounded-2xl bg-white/5 border border-white/10 hover:border-cyan-500/40 transition-all flex items-start gap-4"
                >
                    <input
                        type="checkbox"
                        v-model="op.selected"
                        class="mt-1 w-4 h-4 rounded text-cyan-500 focus:ring-0 bg-white/10 border-white/20 cursor-pointer"
                    />

                    <div class="min-w-0 flex-1 space-y-2">
                        <!-- Source Path -->
                        <div class="flex items-center gap-2 text-xs">
                            <span class="cinema-badge bg-rose-500/20 text-rose-300 border-rose-500/30 text-[9px] uppercase font-mono">
                                {{ isRTL ? 'المسار الأصلي' : 'SRC' }}
                            </span>
                            <span class="font-mono text-slate-400 truncate text-[11px]">{{ op.source_path }}</span>
                        </div>

                        <!-- Destination Path -->
                        <div class="flex items-center gap-2 text-xs">
                            <span class="cinema-badge bg-emerald-500/20 text-emerald-300 border-emerald-500/30 text-[9px] uppercase font-mono">
                                {{ isRTL ? 'المسار المنظم الجديد' : 'DEST' }}
                            </span>
                            <span class="font-mono text-emerald-300 font-bold truncate text-[11px]">{{ op.destination_path }}</span>
                        </div>

                        <!-- Companion Subtitles -->
                        <div v-if="op.subtitles && op.subtitles.length > 0" class="flex items-center gap-2 pt-1">
                            <span class="text-[10px] text-slate-400 font-bold">{{ isRTL ? 'ملفات الترجمة المرافقة:' : 'Attached Subtitles:' }}</span>
                            <span
                                v-for="sub in op.subtitles"
                                :key="sub.path"
                                class="cinema-badge bg-indigo-500/20 text-indigo-300 border-indigo-500/30 text-[9px]"
                            >
                                {{ sub.language?.toUpperCase() || 'SUB' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Execution Live Terminal Logs -->
        <div v-if="executionLogs.length > 0" class="glass-panel rounded-3xl p-6 border border-white/10 space-y-3 mb-8">
            <div class="flex items-center gap-2.5 pb-2 border-b border-white/10">
                <Terminal class="w-5 h-5 text-cyan-400" />
                <h3 class="font-bold text-sm text-white">
                    {{ isRTL ? 'سجل عمليات النقل والتحديث (Execution Terminal)' : 'Organizer Execution Terminal' }}
                </h3>
            </div>
            <div class="bg-[#05070B] rounded-2xl p-4 font-mono text-[11px] h-44 overflow-y-auto space-y-1.5 border border-white/5">
                <div
                    v-for="(log, lIdx) in executionLogs"
                    :key="lIdx"
                    class="flex items-start gap-2.5 leading-relaxed"
                >
                    <span class="text-slate-500 shrink-0">[{{ log.time }}]</span>
                    <span
                        class="font-bold shrink-0 uppercase text-[10px] px-1 rounded"
                        :class="log.level === 'success' ? 'bg-emerald-500/20 text-emerald-400' : log.level === 'error' ? 'bg-rose-500/20 text-rose-400' : 'bg-cyan-500/20 text-cyan-400'"
                    >
                        {{ log.level }}
                    </span>
                    <span :class="log.level === 'success' ? 'text-emerald-300' : log.level === 'error' ? 'text-rose-300' : 'text-slate-300'">
                        {{ log.message }}
                    </span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
