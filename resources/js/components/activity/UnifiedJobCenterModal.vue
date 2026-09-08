<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { useActivityCenter } from '@/composables/useActivityCenter';
import { useScanner } from '@/composables/useScanner';
import { useOrganizerPlan } from '@/composables/useOrganizerPlan';
import { useSubtitleJob } from '@/composables/useSubtitleJob';
import { useI18n } from '@/i18n/useI18n';
import ConfirmModal from '@/components/common/ConfirmModal.vue';
import {
    Activity, ScanLine, FolderSync, ShieldCheck, Play, Pause,
    XCircle, X, Terminal, RotateCcw, RefreshCw, CheckCircle2,
    AlertCircle, ExternalLink, HardDrive, FileText, Check,
    AlertTriangle, Trash2, ArrowUpRight, Copy
} from 'lucide-vue-next';

const {
    isActivityCenterOpen,
    activeTab,
    activeJobsCount,
    isAnyRunning,
    isAnyPaused,
    isAnyActive,
    closeActivityCenter,
    pauseAll,
    resumeAll,
    cancelAll,
} = useActivityCenter();

const {
    scanStatus,
    isScanning,
    isPaused: isScanPaused,
    startFullScan,
    pauseScan,
    resumeScan,
    cancelScan,
} = useScanner();

const {
    planJobStatus,
    isAnalyzing,
    isPaused: isOrgPaused,
    pausePlanJob,
    resumePlanJob,
    cancelPlanJob,
} = useOrganizerPlan();

const {
    subtitleStatus,
    isSubtitleRunning,
    isSubtitlePaused,
    isSubtitleCompleted,
    pauseHealthJob,
    resumeHealthJob,
    cancelHealthJob,
} = useSubtitleJob();

const { t, isRTL } = useI18n();

// Terminal filtering
const terminalFilter = ref<'all' | 'success' | 'info' | 'error'>('all');
const copiedLogs = ref(false);

const confirmModal = ref<{
    show: boolean;
    title: string;
    message: string;
    confirmText: string;
    type: 'danger' | 'warning' | 'info';
    action: () => Promise<void> | void;
}>({
    show: false,
    title: '',
    message: '',
    confirmText: '',
    type: 'danger',
    action: () => {},
});

// Logs for active engine
const activeLogs = computed(() => {
    let logs: any[] = [];
    if (activeTab.value === 'scanner') {
        logs = scanStatus.value.logs || [];
    } else if (activeTab.value === 'organizer') {
        logs = planJobStatus.value.logs || [];
    } else if (activeTab.value === 'subtitles') {
        logs = subtitleStatus.value.logs || [];
    }

    if (terminalFilter.value === 'all') return logs;
    return logs.filter((l) => l.level === terminalFilter.value);
});

// Auto-scroll terminal when new logs arrive
watch(() => activeLogs.value.length, async () => {
    await nextTick();
    const term = document.getElementById('unified-terminal-feed');
    if (term) term.scrollTop = term.scrollHeight;
});

// Copy logs to clipboard
const copyTerminalLogs = () => {
    const text = activeLogs.value.map(l => `[${l.time}] [${l.level?.toUpperCase()}] ${l.message}`).join('\n');
    navigator.clipboard.writeText(text);
    copiedLogs.value = true;
    setTimeout(() => {
        copiedLogs.value = false;
    }, 2000);
};

// Confirm Cancel Job
const handleCancelActiveJob = () => {
    const engineName = activeTab.value === 'scanner'
        ? (isRTL.value ? 'فاحص المكتبة الافتراضي' : 'Virtual Scanner')
        : activeTab.value === 'organizer'
            ? (isRTL.value ? 'منظم القرص الفعلي' : 'Disk Organizer')
            : (isRTL.value ? 'فاحص ومطهر الترجمات' : 'Subtitle Studio');

    confirmModal.value = {
        show: true,
        title: isRTL.value ? `إلغاء عملية ${engineName}` : `Cancel ${engineName} Operation`,
        message: isRTL.value
            ? 'هل أنت متأكد من إيقاف وإلغاء العملية الحالية بشكل نهائي؟'
            : `Are you sure you want to stop and cancel the current ${engineName} operation?`,
        confirmText: isRTL.value ? 'تأكيد الإلغاء' : 'Confirm Cancel',
        type: 'danger',
        action: async () => {
            confirmModal.value.show = false;
            if (activeTab.value === 'scanner') {
                await cancelScan();
            } else if (activeTab.value === 'organizer') {
                await cancelPlanJob();
            } else if (activeTab.value === 'subtitles') {
                await cancelHealthJob();
            }
        },
    };
};

const triggerConfirmAction = async () => {
    if (confirmModal.value.action) {
        await confirmModal.value.action();
    }
};

const navigateToEnginePage = (path: string) => {
    closeActivityCenter();
    if (typeof window !== 'undefined' && path.includes('step=2')) {
        window.dispatchEvent(new CustomEvent('cmh:navigate-step', { detail: { step: 2 } }));
    }
    router.visit(path);
};
</script>

<template>
    <div
        v-if="isActivityCenterOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md transition-all duration-300 animate-in fade-in"
        @click.self="closeActivityCenter"
    >
        <div class="relative w-full max-w-3xl rounded-3xl bg-[#080B12] border border-cyan-500/30 p-5 sm:p-7 shadow-2xl shadow-cyan-500/10 space-y-6 overflow-hidden max-h-[92vh] flex flex-col">
            <!-- Background Ambient Glow -->
            <div class="ambient-glow bg-cyan-500/15 w-96 h-96 -top-28 -right-28 pointer-events-none"></div>
            <div class="ambient-glow bg-indigo-500/10 w-96 h-96 -bottom-28 -left-28 pointer-events-none"></div>

            <!-- Master Header -->
            <div class="flex items-center justify-between relative z-10 border-b border-white/10 pb-4 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 flex items-center justify-center shadow-lg shadow-cyan-500/10">
                        <Activity class="w-5 h-5 animate-pulse" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h2 class="font-black text-base sm:text-lg text-white tracking-wide">
                                {{ isRTL ? 'مركز مراقبة وإدارة العمليات الموحد' : 'Universal Job & Activity Center' }}
                            </h2>
                            <span
                                class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1.5"
                                :class="isAnyRunning
                                    ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 animate-pulse'
                                    : isAnyPaused
                                        ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40'
                                        : 'bg-white/5 text-slate-400 border border-white/10'"
                            >
                                <span
                                    class="w-1.5 h-1.5 rounded-full"
                                    :class="isAnyRunning ? 'bg-cyan-400 animate-ping' : isAnyPaused ? 'bg-amber-400' : 'bg-slate-500'"
                                ></span>
                                {{ isAnyRunning ? `${activeJobsCount} ${isRTL ? 'عملية نشطة' : 'Running'}` : isAnyPaused ? (isRTL ? 'متوقف مؤقتاً' : 'Paused') : (isRTL ? 'خامل' : 'Idle') }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ isRTL ? 'متابعة حية للفاحص الافتراضي، ومنظم القرص الفعلي، ومطهر الترجمات في نافذة واحدة.' : 'Unified live streaming, pause/resume controls, and telemetry across all engines.' }}
                        </p>
                    </div>
                </div>

                <!-- Header Actions -->
                <div class="flex items-center gap-2">
                    <!-- Global Pause/Resume All (if multiple or any) -->
                    <button
                        v-if="isAnyRunning"
                        @click="pauseAll"
                        class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/30 text-amber-300 text-xs font-bold transition-all cursor-pointer"
                        :title="isRTL ? 'إيقاف جميع العمليات مؤقتاً' : 'Pause All Active Jobs'"
                    >
                        <Pause class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'إيقاف الكل' : 'Pause All' }}</span>
                    </button>
                    <button
                        v-else-if="isAnyPaused"
                        @click="resumeAll"
                        class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 border border-cyan-500/30 text-cyan-300 text-xs font-bold transition-all cursor-pointer"
                        :title="isRTL ? 'استئناف جميع العمليات المتوقفة' : 'Resume All Paused Jobs'"
                    >
                        <Play class="w-3.5 h-3.5 fill-current" />
                        <span>{{ isRTL ? 'استئناف الكل' : 'Resume All' }}</span>
                    </button>

                    <button
                        @click="closeActivityCenter"
                        class="w-8 h-8 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white flex items-center justify-center transition-all cursor-pointer border border-white/5"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- 3-Engine Unified Selector Tabs -->
            <div class="grid grid-cols-3 gap-2 sm:gap-3 relative z-10 shrink-0">
                <!-- Tab 1: Virtual Scanner -->
                <button
                    @click="activeTab = 'scanner'"
                    class="p-3 sm:p-3.5 rounded-2xl border text-left transition-all relative overflow-hidden group cursor-pointer"
                    :class="activeTab === 'scanner'
                        ? 'bg-cyan-500/15 border-cyan-400/50 shadow-lg shadow-cyan-500/10 ring-1 ring-cyan-500/30'
                        : 'bg-white/[0.03] border-white/10 hover:border-white/20 hover:bg-white/[0.05]'"
                >
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="flex items-center gap-2">
                            <ScanLine
                                class="w-4 h-4 transition-transform group-hover:scale-110"
                                :class="activeTab === 'scanner' ? 'text-cyan-400' : 'text-slate-400'"
                            />
                            <span class="text-xs sm:text-sm font-bold text-white">
                                {{ isRTL ? 'الفاحص الافتراضي' : 'Virtual Scanner' }}
                            </span>
                        </div>
                        <span
                            class="w-2 h-2 rounded-full"
                            :class="isScanning ? 'bg-cyan-400 animate-ping' : isScanPaused ? 'bg-amber-400' : 'bg-slate-600'"
                        ></span>
                    </div>
                    <div class="flex items-center justify-between text-[11px] font-mono">
                        <span :class="isScanning ? 'text-cyan-300 font-bold' : isScanPaused ? 'text-amber-300 font-bold' : 'text-slate-400'">
                            {{ scanStatus.status }}
                        </span>
                        <span class="text-slate-300 font-bold">
                            {{ scanStatus.progress_percent }}%
                        </span>
                    </div>
                </button>

                <!-- Tab 2: Disk Organizer -->
                <button
                    @click="activeTab = 'organizer'"
                    class="p-3 sm:p-3.5 rounded-2xl border text-left transition-all relative overflow-hidden group cursor-pointer"
                    :class="activeTab === 'organizer'
                        ? 'bg-emerald-500/15 border-emerald-400/50 shadow-lg shadow-emerald-500/10 ring-1 ring-emerald-500/30'
                        : 'bg-white/[0.03] border-white/10 hover:border-white/20 hover:bg-white/[0.05]'"
                >
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="flex items-center gap-2">
                            <FolderSync
                                class="w-4 h-4 transition-transform group-hover:scale-110"
                                :class="activeTab === 'organizer' ? 'text-emerald-400' : 'text-slate-400'"
                            />
                            <span class="text-xs sm:text-sm font-bold text-white">
                                {{ isRTL ? 'منظم القرص' : 'Disk Organizer' }}
                            </span>
                        </div>
                        <span
                            class="w-2 h-2 rounded-full"
                            :class="isAnalyzing ? 'bg-emerald-400 animate-ping' : isOrgPaused ? 'bg-amber-400' : 'bg-slate-600'"
                        ></span>
                    </div>
                    <div class="flex items-center justify-between text-[11px] font-mono">
                        <span :class="isAnalyzing ? 'text-emerald-300 font-bold' : isOrgPaused ? 'text-amber-300 font-bold' : 'text-slate-400'">
                            {{ planJobStatus.status }}
                        </span>
                        <span class="text-slate-300 font-bold">
                            {{ planJobStatus.progress_percent }}%
                        </span>
                    </div>
                </button>

                <!-- Tab 3: Subtitle Studio -->
                <button
                    @click="activeTab = 'subtitles'"
                    class="p-3 sm:p-3.5 rounded-2xl border text-left transition-all relative overflow-hidden group cursor-pointer"
                    :class="activeTab === 'subtitles'
                        ? 'bg-purple-500/15 border-purple-400/50 shadow-lg shadow-purple-500/10 ring-1 ring-purple-500/30'
                        : 'bg-white/[0.03] border-white/10 hover:border-white/20 hover:bg-white/[0.05]'"
                >
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="flex items-center gap-2">
                            <ShieldCheck
                                class="w-4 h-4 transition-transform group-hover:scale-110"
                                :class="activeTab === 'subtitles' ? 'text-purple-400' : 'text-slate-400'"
                            />
                            <span class="text-xs sm:text-sm font-bold text-white">
                                {{ isRTL ? 'مطهر الترجمات' : 'Subtitle Studio' }}
                            </span>
                        </div>
                        <span
                            class="w-2 h-2 rounded-full"
                            :class="isSubtitleRunning ? 'bg-purple-400 animate-ping' : isSubtitlePaused ? 'bg-amber-400' : 'bg-slate-600'"
                        ></span>
                    </div>
                    <div class="flex items-center justify-between text-[11px] font-mono">
                        <span :class="isSubtitleRunning ? 'text-purple-300 font-bold' : isSubtitlePaused ? 'text-amber-300 font-bold' : 'text-slate-400'">
                            {{ subtitleStatus.status }}
                        </span>
                        <span class="text-slate-300 font-bold">
                            {{ subtitleStatus.progress_percent }}%
                        </span>
                    </div>
                </button>
            </div>

            <!-- Scrollable Content Area -->
            <div class="space-y-4 overflow-y-auto pr-1 flex-1 custom-scrollbar relative z-10">
                <!-- ENGINE 1: VIRTUAL SCANNER PANEL -->
                <div v-if="activeTab === 'scanner'" class="space-y-4">
                    <!-- Bento Metrics Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'حالة الفحص' : 'Scan State' }}</span>
                            <span class="text-xs font-black text-white mt-1 capitalize block">{{ scanStatus.status }}</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'الملفات المفهرسة' : 'Indexed Files' }}</span>
                            <span class="text-xs font-black text-cyan-400 mt-1 block font-mono">{{ scanStatus.processed_files }} / {{ scanStatus.total_files }}</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'نسبة الإنجاز' : 'Completion' }}</span>
                            <span class="text-xs font-black text-emerald-400 mt-1 block font-mono">{{ scanStatus.progress_percent }}%</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'الملف الجاري' : 'Current Stream' }}</span>
                            <span class="text-[11px] font-bold text-slate-300 mt-1 truncate block font-mono" :title="scanStatus.current_file || undefined">
                                {{ scanStatus.current_file ? scanStatus.current_file.split('/').pop() : '-' }}
                            </span>
                        </div>
                    </div>

                    <!-- Glowing Progress Bar -->
                    <div class="w-full h-2 rounded-full bg-white/5 border border-white/10 overflow-hidden">
                        <div
                            class="h-full bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500 transition-all duration-300"
                            :style="{ width: `${scanStatus.progress_percent || 0}%` }"
                        ></div>
                    </div>
                </div>

                <!-- ENGINE 2: DISK ORGANIZER PANEL -->
                <div v-else-if="activeTab === 'organizer'" class="space-y-4">
                    <!-- Bento Metrics Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'حالة التوليد' : 'Plan State' }}</span>
                            <span class="text-xs font-black text-white mt-1 capitalize block">{{ planJobStatus.status }}</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'العناصر المحللة' : 'Items Analyzed' }}</span>
                            <span class="text-xs font-black text-emerald-400 mt-1 block font-mono">{{ planJobStatus.processed_count }} / {{ planJobStatus.total_files }}</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'نسبة الخطة' : 'Plan Progress' }}</span>
                            <span class="text-xs font-black text-cyan-400 mt-1 block font-mono">{{ planJobStatus.progress_percent }}%</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'الإجراء الحالي' : 'Current Action' }}</span>
                            <span class="text-[11px] font-bold text-slate-300 mt-1 truncate block font-mono">
                                {{ planJobStatus.current_action || (planJobStatus.current_file ? planJobStatus.current_file.split('/').pop() : '-') }}
                            </span>
                        </div>
                    </div>

                    <!-- Glowing Progress Bar -->
                    <div class="w-full h-2 rounded-full bg-white/5 border border-white/10 overflow-hidden">
                        <div
                            class="h-full bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500 transition-all duration-300"
                            :style="{ width: `${planJobStatus.progress_percent || 0}%` }"
                        ></div>
                    </div>
                </div>

                <!-- ENGINE 3: SUBTITLE STUDIO PANEL -->
                <div v-else-if="activeTab === 'subtitles'" class="space-y-4">
                    <!-- Bento Metrics Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                        <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'الحالة' : 'Status' }}</span>
                            <span class="text-xs font-black text-white mt-1 capitalize block">{{ subtitleStatus.status }}</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'ملفات مفحوصة' : 'Scanned' }}</span>
                            <span class="text-xs font-black text-purple-400 mt-1 block font-mono">{{ subtitleStatus.processed_count }} / {{ subtitleStatus.total_files }}</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'ترميز معالج' : 'UTF-8 Fixed' }}</span>
                            <span class="text-xs font-black text-emerald-400 mt-1 block font-mono">{{ subtitleStatus.summary.encoding_fixed_count }}</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'تسمية معيارية' : 'Renamed' }}</span>
                            <span class="text-xs font-black text-cyan-400 mt-1 block font-mono">{{ subtitleStatus.summary.renamed_count }}</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-white/[0.03] border border-white/10">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'ملفات تالفة' : 'Deleted/Bad' }}</span>
                            <span class="text-xs font-black text-amber-400 mt-1 block font-mono">{{ subtitleStatus.summary.deleted_count + subtitleStatus.summary.invalid_count }}</span>
                        </div>
                    </div>

                    <!-- Glowing Progress Bar -->
                    <div class="w-full h-2 rounded-full bg-white/5 border border-white/10 overflow-hidden">
                        <div
                            class="h-full bg-gradient-to-r from-purple-500 via-pink-500 to-cyan-500 transition-all duration-300"
                            :style="{ width: `${subtitleStatus.progress_percent || 0}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Unified Live Terminal Output Feed -->
                <div class="rounded-2xl bg-black/70 border border-white/10 p-3.5 sm:p-4 space-y-2.5">
                    <div class="flex items-center justify-between border-b border-white/5 pb-2">
                        <div class="flex items-center gap-2">
                            <Terminal class="w-3.5 h-3.5 text-cyan-400" />
                            <span class="text-xs font-bold text-slate-300">
                                {{ activeTab === 'scanner'
                                    ? (isRTL ? 'سجل الفاحص الافتراضي المباشر' : 'Live Virtual Scanner Stream')
                                    : activeTab === 'organizer'
                                        ? (isRTL ? 'سجل منظم القرص المباشر' : 'Live Disk Organizer Stream')
                                        : (isRTL ? 'سجل فاحص الترجمات المباشر' : 'Live Subtitle Audit Stream')
                                }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Copy Terminal Output -->
                            <button
                                @click="copyTerminalLogs"
                                class="px-2 py-0.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white text-[10px] font-bold flex items-center gap-1 transition-all cursor-pointer"
                                :title="isRTL ? 'نسخ السجلات' : 'Copy Logs'"
                            >
                                <Check v-if="copiedLogs" class="w-3 h-3 text-emerald-400" />
                                <Copy v-else class="w-3 h-3" />
                                <span>{{ copiedLogs ? (isRTL ? 'تم النسخ' : 'Copied!') : (isRTL ? 'نسخ' : 'Copy') }}</span>
                            </button>

                            <!-- Level Filters -->
                            <div class="flex items-center gap-1 text-[10px]">
                                <button
                                    v-for="flt in ['all', 'success', 'info', 'error'] as const"
                                    :key="flt"
                                    @click="terminalFilter = flt"
                                    class="px-2 py-0.5 rounded-lg uppercase font-bold transition-all cursor-pointer"
                                    :class="terminalFilter === flt
                                        ? (activeTab === 'scanner' ? 'bg-cyan-500 text-slate-950' : activeTab === 'organizer' ? 'bg-emerald-500 text-slate-950' : 'bg-purple-500 text-white')
                                        : 'bg-white/5 text-slate-400 hover:text-white'"
                                >
                                    {{ flt }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="unified-terminal-feed" class="h-44 sm:h-48 overflow-y-auto font-mono text-[11px] space-y-1.5 pr-2 custom-scrollbar">
                        <div
                            v-for="(log, idx) in activeLogs"
                            :key="idx"
                            class="flex items-start gap-2 leading-relaxed"
                        >
                            <span class="text-slate-600 shrink-0 select-none">[{{ log.time }}]</span>
                            <span
                                :class="{
                                    'text-emerald-400': log.level === 'success',
                                    'text-cyan-300': log.level === 'info',
                                    'text-amber-400': log.level === 'warning',
                                    'text-rose-400 font-bold': log.level === 'error',
                                }"
                            >
                                {{ log.message }}
                            </span>
                        </div>
                        <div v-if="activeLogs.length === 0" class="text-slate-600 italic py-8 text-center">
                            {{ isRTL ? 'بانتظار أحداث وسجلات المعالجة...' : 'Waiting for telemetry and engine stream events...' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Unified Action Toolbar Footer -->
            <div class="flex items-center justify-between flex-wrap gap-3 pt-3 border-t border-white/10 relative z-10 shrink-0">
                <!-- Left Engine Actions (Pause/Resume/Cancel) -->
                <div class="flex items-center gap-2">
                    <!-- SCANNER CONTROLS -->
                    <template v-if="activeTab === 'scanner'">
                        <button
                            v-if="scanStatus.status === 'idle' || scanStatus.status === 'completed' || scanStatus.status === 'cancelled'"
                            @click="() => startFullScan()"
                            class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-extrabold text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-lg shadow-cyan-500/20"
                        >
                            <Play class="w-3.5 h-3.5 fill-current" />
                            <span>{{ isRTL ? 'بدء الفحص' : 'Start Scan' }}</span>
                        </button>

                        <button
                            v-if="isScanning"
                            @click="pauseScan"
                            class="px-4 py-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer"
                        >
                            <Pause class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                        </button>

                        <button
                            v-if="isScanPaused"
                            @click="resumeScan"
                            class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-lg shadow-cyan-500/20"
                        >
                            <Play class="w-3.5 h-3.5 fill-current" />
                            <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                        </button>
                    </template>

                    <!-- ORGANIZER CONTROLS -->
                    <template v-else-if="activeTab === 'organizer'">
                        <button
                            v-if="isAnalyzing"
                            @click="pausePlanJob"
                            class="px-4 py-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer"
                        >
                            <Pause class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                        </button>

                        <button
                            v-if="isOrgPaused"
                            @click="resumePlanJob"
                            class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-lg shadow-emerald-500/20"
                        >
                            <Play class="w-3.5 h-3.5 fill-current" />
                            <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                        </button>

                        <button
                            v-if="planJobStatus.status === 'completed'"
                            @click="navigateToEnginePage('/organizer?step=2')"
                            class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-lg shadow-emerald-500/20"
                        >
                            <CheckCircle2 class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'استعراض الخطة في المنظم' : 'View Plan in Organizer' }}</span>
                        </button>
                    </template>

                    <!-- SUBTITLES CONTROLS -->
                    <template v-else-if="activeTab === 'subtitles'">
                        <button
                            v-if="isSubtitleRunning"
                            @click="pauseHealthJob"
                            class="px-4 py-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer"
                        >
                            <Pause class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                        </button>

                        <button
                            v-if="isSubtitlePaused"
                            @click="resumeHealthJob"
                            class="px-4 py-2 rounded-xl bg-purple-500 hover:bg-purple-400 text-white font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-lg shadow-purple-500/20"
                        >
                            <Play class="w-3.5 h-3.5 fill-current" />
                            <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                        </button>
                    </template>

                    <!-- Universal Cancel for active tab -->
                    <button
                        v-if="(activeTab === 'scanner' && (isScanning || isScanPaused)) ||
                              (activeTab === 'organizer' && (isAnalyzing || isOrgPaused)) ||
                              (activeTab === 'subtitles' && (isSubtitleRunning || isSubtitlePaused))"
                        @click="handleCancelActiveJob"
                        class="px-3.5 py-2 rounded-xl bg-rose-500/15 hover:bg-rose-500/25 text-rose-300 border border-rose-500/30 font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer"
                    >
                        <XCircle class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'إلغاء العملية' : 'Cancel' }}</span>
                    </button>
                </div>

                <!-- Right Destination Navigation & Close -->
                <div class="flex items-center gap-2">
                    <button
                        v-if="activeTab === 'scanner'"
                        @click="navigateToEnginePage('/scanner')"
                        class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white text-xs font-bold flex items-center gap-1 transition-all cursor-pointer border border-white/5"
                    >
                        <span>{{ isRTL ? 'صفحة الفاحص' : 'Scanner Page' }}</span>
                        <ArrowUpRight class="w-3 h-3" />
                    </button>

                    <button
                        v-else-if="activeTab === 'organizer'"
                        @click="navigateToEnginePage('/organizer')"
                        class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white text-xs font-bold flex items-center gap-1 transition-all cursor-pointer border border-white/5"
                    >
                        <span>{{ isRTL ? 'صفحة المنظم' : 'Organizer Page' }}</span>
                        <ArrowUpRight class="w-3 h-3" />
                    </button>

                    <button
                        v-else-if="activeTab === 'subtitles'"
                        @click="navigateToEnginePage('/subtitles')"
                        class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white text-xs font-bold flex items-center gap-1 transition-all cursor-pointer border border-white/5"
                    >
                        <span>{{ isRTL ? 'استوديو الترجمات' : 'Subtitle Studio' }}</span>
                        <ArrowUpRight class="w-3 h-3" />
                    </button>

                    <button
                        @click="closeActivityCenter"
                        class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white text-xs font-bold transition-all cursor-pointer border border-white/10"
                    >
                        {{ isRTL ? 'إخفاء بالخلفية' : 'Minimize' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Global Action Confirmation Dialog -->
        <ConfirmModal
            :show="confirmModal.show"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :confirm-text="confirmModal.confirmText"
            :type="confirmModal.type"
            @confirm="triggerConfirmAction"
            @close="confirmModal.show = false"
        />
    </div>
</template>
