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
    Activity,
    ScanLine,
    FolderSync,
    ShieldCheck,
    Play,
    Pause,
    XCircle,
    X,
    Terminal,
    RotateCcw,
    RefreshCw,
    CheckCircle2,
    AlertCircle,
    ExternalLink,
    HardDrive,
    FileText,
    Check,
    AlertTriangle,
    Trash2,
    ArrowUpRight,
    Copy,
} from '@lucide/vue';

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
watch(
    () => activeLogs.value.length,
    async () => {
        await nextTick();
        const term = document.getElementById('unified-terminal-feed');
        if (term) term.scrollTop = term.scrollHeight;
    },
);

// Copy logs to clipboard
const copyTerminalLogs = () => {
    const text = activeLogs.value
        .map((l) => `[${l.time}] [${l.level?.toUpperCase()}] ${l.message}`)
        .join('\n');
    navigator.clipboard.writeText(text);
    copiedLogs.value = true;
    setTimeout(() => {
        copiedLogs.value = false;
    }, 2000);
};

// Confirm Cancel Job
const handleCancelActiveJob = () => {
    const engineName =
        activeTab.value === 'scanner'
            ? isRTL.value
                ? 'فاحص المكتبة الافتراضي'
                : 'Virtual Scanner'
            : activeTab.value === 'organizer'
              ? isRTL.value
                  ? 'منظم القرص الفعلي'
                  : 'Disk Organizer'
              : isRTL.value
                ? 'فاحص ومطهر الترجمات'
                : 'Subtitle Studio';

    confirmModal.value = {
        show: true,
        title: isRTL.value
            ? `إلغاء عملية ${engineName}`
            : `Cancel ${engineName} Operation`,
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
        window.dispatchEvent(
            new CustomEvent('cmh:navigate-step', { detail: { step: 2 } }),
        );
    }
    router.visit(path);
};
</script>

<template>
    <div
        v-if="isActivityCenterOpen"
        class="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-3 backdrop-blur-md transition-all duration-300 sm:p-4"
        @click.self="closeActivityCenter"
    >
        <div
            class="relative flex max-h-[92vh] w-full max-w-3xl flex-col space-y-6 overflow-hidden rounded-3xl border border-cyan-500/30 bg-[#080B12] p-5 shadow-2xl shadow-cyan-500/10 sm:p-7"
        >
            <!-- Background Ambient Glow -->
            <div
                class="ambient-glow pointer-events-none -top-28 -right-28 h-96 w-96 bg-cyan-500/15"
            ></div>
            <div
                class="ambient-glow pointer-events-none -bottom-28 -left-28 h-96 w-96 bg-indigo-500/10"
            ></div>

            <!-- Master Header -->
            <div
                class="relative z-10 flex shrink-0 items-center justify-between border-b border-white/10 pb-4"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-2xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-400 shadow-lg shadow-cyan-500/10"
                    >
                        <Activity class="h-5 w-5 animate-pulse" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h2
                                class="text-base font-black tracking-wide text-white sm:text-lg"
                            >
                                {{
                                    isRTL
                                        ? 'مركز مراقبة وإدارة العمليات الموحد'
                                        : 'Universal Job & Activity Center'
                                }}
                            </h2>
                            <span
                                class="flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[10px] font-black tracking-wider uppercase"
                                :class="
                                    isAnyRunning
                                        ? 'animate-pulse border border-cyan-500/40 bg-cyan-500/20 text-cyan-300'
                                        : isAnyPaused
                                          ? 'border border-amber-500/40 bg-amber-500/20 text-amber-300'
                                          : 'border border-white/10 bg-white/5 text-slate-400'
                                "
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="
                                        isAnyRunning
                                            ? 'animate-ping bg-cyan-400'
                                            : isAnyPaused
                                              ? 'bg-amber-400'
                                              : 'bg-slate-500'
                                    "
                                ></span>
                                {{
                                    isAnyRunning
                                        ? `${activeJobsCount} ${isRTL ? 'عملية نشطة' : 'Running'}`
                                        : isAnyPaused
                                          ? isRTL
                                              ? 'متوقف مؤقتاً'
                                              : 'Paused'
                                          : isRTL
                                            ? 'خامل'
                                            : 'Idle'
                                }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{
                                isRTL
                                    ? 'متابعة حية للفاحص الافتراضي، ومنظم القرص الفعلي، ومطهر الترجمات في نافذة واحدة.'
                                    : 'Unified live streaming, pause/resume controls, and telemetry across all engines.'
                            }}
                        </p>
                    </div>
                </div>

                <!-- Header Actions -->
                <div class="flex items-center gap-2">
                    <!-- Global Pause/Resume All (if multiple or any) -->
                    <button
                        v-if="isAnyRunning"
                        @click="pauseAll"
                        class="hidden cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/30 bg-amber-500/15 px-3 py-1.5 text-xs font-bold text-amber-300 transition-all hover:bg-amber-500/25 sm:flex"
                        :title="
                            isRTL
                                ? 'إيقاف جميع العمليات مؤقتاً'
                                : 'Pause All Active Jobs'
                        "
                    >
                        <Pause class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'إيقاف الكل' : 'Pause All' }}</span>
                    </button>
                    <button
                        v-else-if="isAnyPaused"
                        @click="resumeAll"
                        class="hidden cursor-pointer items-center gap-1.5 rounded-xl border border-cyan-500/30 bg-cyan-500/20 px-3 py-1.5 text-xs font-bold text-cyan-300 transition-all hover:bg-cyan-500/30 sm:flex"
                        :title="
                            isRTL
                                ? 'استئناف جميع العمليات المتوقفة'
                                : 'Resume All Paused Jobs'
                        "
                    >
                        <Play class="h-3.5 w-3.5 fill-current" />
                        <span>{{ isRTL ? 'استئناف الكل' : 'Resume All' }}</span>
                    </button>

                    <button
                        @click="closeActivityCenter"
                        class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-xl border border-white/5 bg-white/5 text-slate-400 transition-all hover:bg-white/10 hover:text-white"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- 3-Engine Unified Selector Tabs -->
            <div class="relative z-10 grid shrink-0 grid-cols-3 gap-2 sm:gap-3">
                <!-- Tab 1: Virtual Scanner -->
                <button
                    @click="activeTab = 'scanner'"
                    class="group relative cursor-pointer overflow-hidden rounded-2xl border p-3 text-left transition-all sm:p-3.5"
                    :class="
                        activeTab === 'scanner'
                            ? 'border-cyan-400/50 bg-cyan-500/15 shadow-lg ring-1 shadow-cyan-500/10 ring-cyan-500/30'
                            : 'border-white/10 bg-white/[0.03] hover:border-white/20 hover:bg-white/[0.05]'
                    "
                >
                    <div class="mb-1.5 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <ScanLine
                                class="h-4 w-4 transition-transform group-hover:scale-110"
                                :class="
                                    activeTab === 'scanner'
                                        ? 'text-cyan-400'
                                        : 'text-slate-400'
                                "
                            />
                            <span
                                class="text-xs font-bold text-white sm:text-sm"
                            >
                                {{
                                    isRTL
                                        ? 'الفاحص الافتراضي'
                                        : 'Virtual Scanner'
                                }}
                            </span>
                        </div>
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="
                                isScanning
                                    ? 'animate-ping bg-cyan-400'
                                    : isScanPaused
                                      ? 'bg-amber-400'
                                      : 'bg-slate-600'
                            "
                        ></span>
                    </div>
                    <div
                        class="flex items-center justify-between font-mono text-[11px]"
                    >
                        <span
                            :class="
                                isScanning
                                    ? 'font-bold text-cyan-300'
                                    : isScanPaused
                                      ? 'font-bold text-amber-300'
                                      : 'text-slate-400'
                            "
                        >
                            {{ scanStatus.status }}
                        </span>
                        <span class="font-bold text-slate-300">
                            {{ scanStatus.progress_percent }}%
                        </span>
                    </div>
                </button>

                <!-- Tab 2: Disk Organizer -->
                <button
                    @click="activeTab = 'organizer'"
                    class="group relative cursor-pointer overflow-hidden rounded-2xl border p-3 text-left transition-all sm:p-3.5"
                    :class="
                        activeTab === 'organizer'
                            ? 'border-emerald-400/50 bg-emerald-500/15 shadow-lg ring-1 shadow-emerald-500/10 ring-emerald-500/30'
                            : 'border-white/10 bg-white/[0.03] hover:border-white/20 hover:bg-white/[0.05]'
                    "
                >
                    <div class="mb-1.5 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <FolderSync
                                class="h-4 w-4 transition-transform group-hover:scale-110"
                                :class="
                                    activeTab === 'organizer'
                                        ? 'text-emerald-400'
                                        : 'text-slate-400'
                                "
                            />
                            <span
                                class="text-xs font-bold text-white sm:text-sm"
                            >
                                {{ isRTL ? 'منظم القرص' : 'Disk Organizer' }}
                            </span>
                        </div>
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="
                                isAnalyzing
                                    ? 'animate-ping bg-emerald-400'
                                    : isOrgPaused
                                      ? 'bg-amber-400'
                                      : 'bg-slate-600'
                            "
                        ></span>
                    </div>
                    <div
                        class="flex items-center justify-between font-mono text-[11px]"
                    >
                        <span
                            :class="
                                isAnalyzing
                                    ? 'font-bold text-emerald-300'
                                    : isOrgPaused
                                      ? 'font-bold text-amber-300'
                                      : 'text-slate-400'
                            "
                        >
                            {{ planJobStatus.status }}
                        </span>
                        <span class="font-bold text-slate-300">
                            {{ planJobStatus.progress_percent }}%
                        </span>
                    </div>
                </button>

                <!-- Tab 3: Subtitle Studio -->
                <button
                    @click="activeTab = 'subtitles'"
                    class="group relative cursor-pointer overflow-hidden rounded-2xl border p-3 text-left transition-all sm:p-3.5"
                    :class="
                        activeTab === 'subtitles'
                            ? 'border-purple-400/50 bg-purple-500/15 shadow-lg ring-1 shadow-purple-500/10 ring-purple-500/30'
                            : 'border-white/10 bg-white/[0.03] hover:border-white/20 hover:bg-white/[0.05]'
                    "
                >
                    <div class="mb-1.5 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <ShieldCheck
                                class="h-4 w-4 transition-transform group-hover:scale-110"
                                :class="
                                    activeTab === 'subtitles'
                                        ? 'text-purple-400'
                                        : 'text-slate-400'
                                "
                            />
                            <span
                                class="text-xs font-bold text-white sm:text-sm"
                            >
                                {{
                                    isRTL ? 'مطهر الترجمات' : 'Subtitle Studio'
                                }}
                            </span>
                        </div>
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="
                                isSubtitleRunning
                                    ? 'animate-ping bg-purple-400'
                                    : isSubtitlePaused
                                      ? 'bg-amber-400'
                                      : 'bg-slate-600'
                            "
                        ></span>
                    </div>
                    <div
                        class="flex items-center justify-between font-mono text-[11px]"
                    >
                        <span
                            :class="
                                isSubtitleRunning
                                    ? 'font-bold text-purple-300'
                                    : isSubtitlePaused
                                      ? 'font-bold text-amber-300'
                                      : 'text-slate-400'
                            "
                        >
                            {{ subtitleStatus.status }}
                        </span>
                        <span class="font-bold text-slate-300">
                            {{ subtitleStatus.progress_percent }}%
                        </span>
                    </div>
                </button>
            </div>

            <!-- Scrollable Content Area -->
            <div
                class="custom-scrollbar relative z-10 flex-1 space-y-4 overflow-y-auto pr-1"
            >
                <!-- ENGINE 1: VIRTUAL SCANNER PANEL -->
                <div v-if="activeTab === 'scanner'" class="space-y-4">
                    <!-- Bento Metrics Grid -->
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3"
                        >
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                >{{ isRTL ? 'حالة الفحص' : 'Scan State' }}</span
                            >
                            <span
                                class="mt-1 block text-xs font-black text-white capitalize"
                                >{{ scanStatus.status }}</span
                            >
                        </div>
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3"
                        >
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                >{{
                                    isRTL ? 'الملفات المفهرسة' : 'Indexed Files'
                                }}</span
                            >
                            <span
                                class="mt-1 block font-mono text-xs font-black text-cyan-400"
                                >{{ scanStatus.processed_files }} /
                                {{ scanStatus.total_files }}</span
                            >
                        </div>
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3"
                        >
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                >{{
                                    isRTL ? 'نسبة الإنجاز' : 'Completion'
                                }}</span
                            >
                            <span
                                class="mt-1 block font-mono text-xs font-black text-emerald-400"
                                >{{ scanStatus.progress_percent }}%</span
                            >
                        </div>
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3"
                        >
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                >{{
                                    isRTL ? 'الملف الجاري' : 'Current Stream'
                                }}</span
                            >
                            <span
                                class="mt-1 block truncate font-mono text-[11px] font-bold text-slate-300"
                                :title="scanStatus.current_file || undefined"
                            >
                                {{
                                    scanStatus.current_file
                                        ? scanStatus.current_file
                                              .split('/')
                                              .pop()
                                        : '-'
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- Glowing Progress Bar -->
                    <div
                        class="h-2 w-full overflow-hidden rounded-full border border-white/10 bg-white/5"
                    >
                        <div
                            class="h-full bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500 transition-all duration-300"
                            :style="{
                                width: `${scanStatus.progress_percent || 0}%`,
                            }"
                        ></div>
                    </div>
                </div>

                <!-- ENGINE 2: DISK ORGANIZER PANEL -->
                <div v-else-if="activeTab === 'organizer'" class="space-y-4">
                    <!-- Bento Metrics Grid -->
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3"
                        >
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                >{{
                                    isRTL ? 'حالة التوليد' : 'Plan State'
                                }}</span
                            >
                            <span
                                class="mt-1 block text-xs font-black text-white capitalize"
                                >{{ planJobStatus.status }}</span
                            >
                        </div>
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3"
                        >
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                >{{
                                    isRTL ? 'العناصر المحللة' : 'Items Analyzed'
                                }}</span
                            >
                            <span
                                class="mt-1 block font-mono text-xs font-black text-emerald-400"
                                >{{ planJobStatus.processed_count }} /
                                {{ planJobStatus.total_files }}</span
                            >
                        </div>
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3"
                        >
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                >{{
                                    isRTL ? 'نسبة الخطة' : 'Plan Progress'
                                }}</span
                            >
                            <span
                                class="mt-1 block font-mono text-xs font-black text-cyan-400"
                                >{{ planJobStatus.progress_percent }}%</span
                            >
                        </div>
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3"
                        >
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                >{{
                                    isRTL ? 'الإجراء الحالي' : 'Current Action'
                                }}</span
                            >
                            <span
                                class="mt-1 block truncate font-mono text-[11px] font-bold text-slate-300"
                            >
                                {{
                                    planJobStatus.current_action ||
                                    (planJobStatus.current_file
                                        ? planJobStatus.current_file
                                              .split('/')
                                              .pop()
                                        : '-')
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- Glowing Progress Bar -->
                    <div
                        class="h-2 w-full overflow-hidden rounded-full border border-white/10 bg-white/5"
                    >
                        <div
                            class="h-full bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500 transition-all duration-300"
                            :style="{
                                width: `${planJobStatus.progress_percent || 0}%`,
                            }"
                        ></div>
                    </div>
                </div>

                <!-- ENGINE 3: SUBTITLE STUDIO PANEL -->
                <div v-else-if="activeTab === 'subtitles'" class="space-y-4">
                    <!-- Bento Metrics Grid -->
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-5">
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3"
                        >
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                >{{ isRTL ? 'الحالة' : 'Status' }}</span
                            >
                            <span
                                class="mt-1 block text-xs font-black text-white capitalize"
                                >{{ subtitleStatus.status }}</span
                            >
                        </div>
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3"
                        >
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                >{{ isRTL ? 'ملفات مفحوصة' : 'Scanned' }}</span
                            >
                            <span
                                class="mt-1 block font-mono text-xs font-black text-purple-400"
                                >{{ subtitleStatus.processed_count }} /
                                {{ subtitleStatus.total_files }}</span
                            >
                        </div>
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3"
                        >
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                >{{
                                    isRTL ? 'ترميز معالج' : 'UTF-8 Fixed'
                                }}</span
                            >
                            <span
                                class="mt-1 block font-mono text-xs font-black text-emerald-400"
                                >{{
                                    subtitleStatus.summary.encoding_fixed_count
                                }}</span
                            >
                        </div>
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3"
                        >
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                >{{ isRTL ? 'تسمية معيارية' : 'Renamed' }}</span
                            >
                            <span
                                class="mt-1 block font-mono text-xs font-black text-cyan-400"
                                >{{
                                    subtitleStatus.summary.renamed_count
                                }}</span
                            >
                        </div>
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3"
                        >
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                >{{
                                    isRTL ? 'ملفات تالفة' : 'Deleted/Bad'
                                }}</span
                            >
                            <span
                                class="mt-1 block font-mono text-xs font-black text-amber-400"
                                >{{
                                    subtitleStatus.summary.deleted_count +
                                    subtitleStatus.summary.invalid_count
                                }}</span
                            >
                        </div>
                    </div>

                    <!-- Glowing Progress Bar -->
                    <div
                        class="h-2 w-full overflow-hidden rounded-full border border-white/10 bg-white/5"
                    >
                        <div
                            class="h-full bg-gradient-to-r from-purple-500 via-pink-500 to-cyan-500 transition-all duration-300"
                            :style="{
                                width: `${subtitleStatus.progress_percent || 0}%`,
                            }"
                        ></div>
                    </div>
                </div>

                <!-- Unified Live Terminal Output Feed -->
                <div
                    class="space-y-2.5 rounded-2xl border border-white/10 bg-black/70 p-3.5 sm:p-4"
                >
                    <div
                        class="flex items-center justify-between border-b border-white/5 pb-2"
                    >
                        <div class="flex items-center gap-2">
                            <Terminal class="h-3.5 w-3.5 text-cyan-400" />
                            <span class="text-xs font-bold text-slate-300">
                                {{
                                    activeTab === 'scanner'
                                        ? isRTL
                                            ? 'سجل الفاحص الافتراضي المباشر'
                                            : 'Live Virtual Scanner Stream'
                                        : activeTab === 'organizer'
                                          ? isRTL
                                              ? 'سجل منظم القرص المباشر'
                                              : 'Live Disk Organizer Stream'
                                          : isRTL
                                            ? 'سجل فاحص الترجمات المباشر'
                                            : 'Live Subtitle Audit Stream'
                                }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Copy Terminal Output -->
                            <button
                                @click="copyTerminalLogs"
                                class="flex cursor-pointer items-center gap-1 rounded-lg bg-white/5 px-2 py-0.5 text-[10px] font-bold text-slate-400 transition-all hover:bg-white/10 hover:text-white"
                                :title="isRTL ? 'نسخ السجلات' : 'Copy Logs'"
                            >
                                <Check
                                    v-if="copiedLogs"
                                    class="h-3 w-3 text-emerald-400"
                                />
                                <Copy v-else class="h-3 w-3" />
                                <span>{{
                                    copiedLogs
                                        ? isRTL
                                            ? 'تم النسخ'
                                            : 'Copied!'
                                        : isRTL
                                          ? 'نسخ'
                                          : 'Copy'
                                }}</span>
                            </button>

                            <!-- Level Filters -->
                            <div class="flex items-center gap-1 text-[10px]">
                                <button
                                    v-for="flt in [
                                        'all',
                                        'success',
                                        'info',
                                        'error',
                                    ] as const"
                                    :key="flt"
                                    @click="terminalFilter = flt"
                                    class="cursor-pointer rounded-lg px-2 py-0.5 font-bold uppercase transition-all"
                                    :class="
                                        terminalFilter === flt
                                            ? activeTab === 'scanner'
                                                ? 'bg-cyan-500 text-slate-950'
                                                : activeTab === 'organizer'
                                                  ? 'bg-emerald-500 text-slate-950'
                                                  : 'bg-purple-500 text-white'
                                            : 'bg-white/5 text-slate-400 hover:text-white'
                                    "
                                >
                                    {{ flt }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        id="unified-terminal-feed"
                        class="custom-scrollbar h-44 space-y-1.5 overflow-y-auto pr-2 font-mono text-[11px] sm:h-48"
                    >
                        <div
                            v-for="(log, idx) in activeLogs"
                            :key="idx"
                            class="flex items-start gap-2 leading-relaxed"
                        >
                            <span class="shrink-0 text-slate-600 select-none"
                                >[{{ log.time }}]</span
                            >
                            <span
                                :class="{
                                    'text-emerald-400': log.level === 'success',
                                    'text-cyan-300': log.level === 'info',
                                    'text-amber-400': log.level === 'warning',
                                    'font-bold text-rose-400':
                                        log.level === 'error',
                                }"
                            >
                                {{ log.message }}
                            </span>
                        </div>
                        <div
                            v-if="activeLogs.length === 0"
                            class="py-8 text-center text-slate-600 italic"
                        >
                            {{
                                isRTL
                                    ? 'بانتظار أحداث وسجلات المعالجة...'
                                    : 'Waiting for telemetry and engine stream events...'
                            }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Unified Action Toolbar Footer -->
            <div
                class="relative z-10 flex shrink-0 flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-3"
            >
                <!-- Left Engine Actions (Pause/Resume/Cancel) -->
                <div class="flex items-center gap-2">
                    <!-- SCANNER CONTROLS -->
                    <template v-if="activeTab === 'scanner'">
                        <button
                            v-if="
                                scanStatus.status === 'idle' ||
                                scanStatus.status === 'completed' ||
                                scanStatus.status === 'cancelled'
                            "
                            @click="() => startFullScan()"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-cyan-500 px-4 py-2 text-xs font-extrabold text-slate-950 shadow-lg shadow-cyan-500/20 transition-all hover:bg-cyan-400"
                        >
                            <Play class="h-3.5 w-3.5 fill-current" />
                            <span>{{
                                isRTL ? 'بدء الفحص' : 'Start Scan'
                            }}</span>
                        </button>

                        <button
                            v-if="isScanning"
                            @click="pauseScan"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/30 bg-amber-500/20 px-4 py-2 text-xs font-bold text-amber-300 transition-all hover:bg-amber-500/30"
                        >
                            <Pause class="h-3.5 w-3.5" />
                            <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                        </button>

                        <button
                            v-if="isScanPaused"
                            @click="resumeScan"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-cyan-500 px-4 py-2 text-xs font-bold text-slate-950 shadow-lg shadow-cyan-500/20 transition-all hover:bg-cyan-400"
                        >
                            <Play class="h-3.5 w-3.5 fill-current" />
                            <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                        </button>
                    </template>

                    <!-- ORGANIZER CONTROLS -->
                    <template v-else-if="activeTab === 'organizer'">
                        <button
                            v-if="isAnalyzing"
                            @click="pausePlanJob"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/30 bg-amber-500/20 px-4 py-2 text-xs font-bold text-amber-300 transition-all hover:bg-amber-500/30"
                        >
                            <Pause class="h-3.5 w-3.5" />
                            <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                        </button>

                        <button
                            v-if="isOrgPaused"
                            @click="resumePlanJob"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-emerald-500 px-4 py-2 text-xs font-bold text-slate-950 shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-400"
                        >
                            <Play class="h-3.5 w-3.5 fill-current" />
                            <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                        </button>

                        <button
                            v-if="planJobStatus.status === 'completed'"
                            @click="navigateToEnginePage('/organizer?step=2')"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-emerald-500 px-4 py-2 text-xs font-black text-slate-950 shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-400"
                        >
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            <span>{{
                                isRTL
                                    ? 'استعراض الخطة في المنظم'
                                    : 'View Plan in Organizer'
                            }}</span>
                        </button>
                    </template>

                    <!-- SUBTITLES CONTROLS -->
                    <template v-else-if="activeTab === 'subtitles'">
                        <button
                            v-if="isSubtitleRunning"
                            @click="pauseHealthJob"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/30 bg-amber-500/20 px-4 py-2 text-xs font-bold text-amber-300 transition-all hover:bg-amber-500/30"
                        >
                            <Pause class="h-3.5 w-3.5" />
                            <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                        </button>

                        <button
                            v-if="isSubtitlePaused"
                            @click="resumeHealthJob"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-purple-500 px-4 py-2 text-xs font-bold text-white shadow-lg shadow-purple-500/20 transition-all hover:bg-purple-400"
                        >
                            <Play class="h-3.5 w-3.5 fill-current" />
                            <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                        </button>
                    </template>

                    <!-- Universal Cancel for active tab -->
                    <button
                        v-if="
                            (activeTab === 'scanner' &&
                                (isScanning || isScanPaused)) ||
                            (activeTab === 'organizer' &&
                                (isAnalyzing || isOrgPaused)) ||
                            (activeTab === 'subtitles' &&
                                (isSubtitleRunning || isSubtitlePaused))
                        "
                        @click="handleCancelActiveJob"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/15 px-3.5 py-2 text-xs font-bold text-rose-300 transition-all hover:bg-rose-500/25"
                    >
                        <XCircle class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'إلغاء العملية' : 'Cancel' }}</span>
                    </button>
                </div>

                <!-- Right Destination Navigation & Close -->
                <div class="flex items-center gap-2">
                    <button
                        v-if="activeTab === 'scanner'"
                        @click="navigateToEnginePage('/scanner')"
                        class="flex cursor-pointer items-center gap-1 rounded-xl border border-white/5 bg-white/5 px-3 py-1.5 text-xs font-bold text-slate-300 transition-all hover:bg-white/10 hover:text-white"
                    >
                        <span>{{
                            isRTL ? 'صفحة الفاحص' : 'Scanner Page'
                        }}</span>
                        <ArrowUpRight class="h-3 w-3" />
                    </button>

                    <button
                        v-else-if="activeTab === 'organizer'"
                        @click="navigateToEnginePage('/organizer')"
                        class="flex cursor-pointer items-center gap-1 rounded-xl border border-white/5 bg-white/5 px-3 py-1.5 text-xs font-bold text-slate-300 transition-all hover:bg-white/10 hover:text-white"
                    >
                        <span>{{
                            isRTL ? 'صفحة المنظم' : 'Organizer Page'
                        }}</span>
                        <ArrowUpRight class="h-3 w-3" />
                    </button>

                    <button
                        v-else-if="activeTab === 'subtitles'"
                        @click="navigateToEnginePage('/subtitles')"
                        class="flex cursor-pointer items-center gap-1 rounded-xl border border-white/5 bg-white/5 px-3 py-1.5 text-xs font-bold text-slate-300 transition-all hover:bg-white/10 hover:text-white"
                    >
                        <span>{{
                            isRTL ? 'استوديو الترجمات' : 'Subtitle Studio'
                        }}</span>
                        <ArrowUpRight class="h-3 w-3" />
                    </button>

                    <button
                        @click="closeActivityCenter"
                        class="cursor-pointer rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-xs font-bold text-slate-300 transition-all hover:bg-white/10 hover:text-white"
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
