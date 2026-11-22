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
    CheckCircle2,
    Check,
    ArrowUpRight,
    Copy,
} from '@lucide/vue';

const {
    isActivityCenterOpen,
    activeTab,
    activeJobsCount,
    isAnyRunning,
    isAnyPaused,
    closeActivityCenter,
    pauseAll,
    resumeAll,
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
    pauseHealthJob,
    resumeHealthJob,
    cancelHealthJob,
} = useSubtitleJob();

const { isRTL } = useI18n();

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
            class="cf-glass-elevated border-border/80 bg-card/95 relative flex max-h-[92vh] w-full max-w-3xl flex-col space-y-6 overflow-hidden rounded-3xl border p-5 shadow-2xl sm:p-7"
        >
            <!-- Background Ambient Glow -->
            <div
                class="ambient-glow bg-primary/15 pointer-events-none absolute -end-28 -top-28 h-96 w-96"
            ></div>
            <div
                class="ambient-glow pointer-events-none absolute -start-28 -bottom-28 h-96 w-96 bg-indigo-500/10"
            ></div>

            <!-- Master Header -->
            <div
                class="border-border/70 relative z-10 flex shrink-0 items-center justify-between border-b pb-4"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="border-primary/35 bg-primary/15 text-primary shadow-primary/10 flex h-10 w-10 items-center justify-center rounded-2xl border shadow-lg"
                    >
                        <Activity class="h-5 w-5 animate-pulse" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h2
                                class="text-foreground text-base font-black tracking-wide sm:text-lg"
                            >
                                {{
                                    isRTL
                                        ? 'مركز مراقبة وإدارة العمليات الموحد'
                                        : 'Universal Job & Activity Center'
                                }}
                            </h2>
                            <span
                                class="flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[10px] font-black tracking-wider uppercase tabular-nums"
                                :class="
                                    isAnyRunning
                                        ? 'border-primary/40 bg-primary/20 text-primary animate-pulse border'
                                        : isAnyPaused
                                          ? 'border border-amber-500/40 bg-amber-500/20 text-amber-300'
                                          : 'border-border bg-muted/50 text-muted-foreground border'
                                "
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="
                                        isAnyRunning
                                            ? 'bg-primary animate-ping'
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
                        <p class="text-muted-foreground mt-0.5 text-xs">
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
                    <button
                        v-if="isAnyRunning"
                        type="button"
                        @click="pauseAll"
                        class="hidden cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/35 bg-amber-500/15 px-3 py-1.5 text-xs font-bold text-amber-300 transition-all hover:bg-amber-500/25 sm:flex"
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
                        type="button"
                        @click="resumeAll"
                        class="border-primary/35 bg-primary/20 text-primary hover:bg-primary/30 hidden cursor-pointer items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold transition-all sm:flex"
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
                        type="button"
                        @click="closeActivityCenter"
                        class="border-border/60 bg-background/80 text-muted-foreground hover:bg-muted hover:text-foreground flex size-8 cursor-pointer items-center justify-center rounded-xl border transition-all"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- 3-Engine Unified Selector Tabs -->
            <div class="relative z-10 grid shrink-0 grid-cols-3 gap-2 sm:gap-3">
                <!-- Tab 1: Virtual Scanner -->
                <button
                    type="button"
                    @click="activeTab = 'scanner'"
                    class="group relative cursor-pointer overflow-hidden rounded-2xl border p-3 text-start transition-all sm:p-3.5"
                    :class="
                        activeTab === 'scanner'
                            ? 'border-primary bg-primary/15 shadow-primary/10 ring-primary/30 shadow-lg ring-1'
                            : 'cf-glass border-border/70 hover:border-primary/35'
                    "
                >
                    <div class="mb-1.5 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <ScanLine
                                class="h-4 w-4 transition-transform group-hover:scale-110"
                                :class="
                                    activeTab === 'scanner'
                                        ? 'text-primary'
                                        : 'text-muted-foreground'
                                "
                            />
                            <span
                                class="text-foreground text-xs font-bold sm:text-sm"
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
                                    ? 'bg-primary animate-ping'
                                    : isScanPaused
                                      ? 'bg-amber-400'
                                      : 'bg-slate-600'
                            "
                        ></span>
                    </div>
                    <div
                        class="flex items-center justify-between font-mono text-[11px] tabular-nums"
                    >
                        <span
                            :class="
                                isScanning
                                    ? 'text-primary font-bold'
                                    : isScanPaused
                                      ? 'font-bold text-amber-300'
                                      : 'text-muted-foreground'
                            "
                        >
                            {{ scanStatus.status }}
                        </span>
                        <span class="text-foreground font-bold">
                            {{ scanStatus.progress_percent }}%
                        </span>
                    </div>
                </button>

                <!-- Tab 2: Disk Organizer -->
                <button
                    type="button"
                    @click="activeTab = 'organizer'"
                    class="group relative cursor-pointer overflow-hidden rounded-2xl border p-3 text-start transition-all sm:p-3.5"
                    :class="
                        activeTab === 'organizer'
                            ? 'border-primary bg-primary/15 shadow-primary/10 ring-primary/30 shadow-lg ring-1'
                            : 'cf-glass border-border/70 hover:border-primary/35'
                    "
                >
                    <div class="mb-1.5 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <FolderSync
                                class="h-4 w-4 transition-transform group-hover:scale-110"
                                :class="
                                    activeTab === 'organizer'
                                        ? 'text-primary'
                                        : 'text-muted-foreground'
                                "
                            />
                            <span
                                class="text-foreground text-xs font-bold sm:text-sm"
                            >
                                {{ isRTL ? 'منظم القرص' : 'Disk Organizer' }}
                            </span>
                        </div>
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="
                                isAnalyzing
                                    ? 'bg-primary animate-ping'
                                    : isOrgPaused
                                      ? 'bg-amber-400'
                                      : 'bg-slate-600'
                            "
                        ></span>
                    </div>
                    <div
                        class="flex items-center justify-between font-mono text-[11px] tabular-nums"
                    >
                        <span
                            :class="
                                isAnalyzing
                                    ? 'text-primary font-bold'
                                    : isOrgPaused
                                      ? 'font-bold text-amber-300'
                                      : 'text-muted-foreground'
                            "
                        >
                            {{ planJobStatus.status }}
                        </span>
                        <span class="text-foreground font-bold">
                            {{ planJobStatus.progress_percent }}%
                        </span>
                    </div>
                </button>

                <!-- Tab 3: Subtitle Studio -->
                <button
                    type="button"
                    @click="activeTab = 'subtitles'"
                    class="group relative cursor-pointer overflow-hidden rounded-2xl border p-3 text-start transition-all sm:p-3.5"
                    :class="
                        activeTab === 'subtitles'
                            ? 'border-primary bg-primary/15 shadow-primary/10 ring-primary/30 shadow-lg ring-1'
                            : 'cf-glass border-border/70 hover:border-primary/35'
                    "
                >
                    <div class="mb-1.5 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <ShieldCheck
                                class="h-4 w-4 transition-transform group-hover:scale-110"
                                :class="
                                    activeTab === 'subtitles'
                                        ? 'text-primary'
                                        : 'text-muted-foreground'
                                "
                            />
                            <span
                                class="text-foreground text-xs font-bold sm:text-sm"
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
                                    ? 'bg-primary animate-ping'
                                    : isSubtitlePaused
                                      ? 'bg-amber-400'
                                      : 'bg-slate-600'
                            "
                        ></span>
                    </div>
                    <div
                        class="flex items-center justify-between font-mono text-[11px] tabular-nums"
                    >
                        <span
                            :class="
                                isSubtitleRunning
                                    ? 'text-primary font-bold'
                                    : isSubtitlePaused
                                      ? 'font-bold text-amber-300'
                                      : 'text-muted-foreground'
                            "
                        >
                            {{ subtitleStatus.status }}
                        </span>
                        <span class="text-foreground font-bold">
                            {{ subtitleStatus.progress_percent }}%
                        </span>
                    </div>
                </button>
            </div>

            <!-- Scrollable Content Area -->
            <div
                class="custom-scrollbar relative z-10 flex-1 space-y-4 overflow-y-auto pe-1"
            >
                <!-- ENGINE 1: VIRTUAL SCANNER PANEL -->
                <div v-if="activeTab === 'scanner'" class="space-y-4">
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                        <div
                            class="cf-glass border-border/70 rounded-2xl border p-3"
                        >
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{ isRTL ? 'حالة الفحص' : 'Scan State' }}</span
                            >
                            <span
                                class="text-foreground mt-1 block text-xs font-black capitalize"
                                >{{ scanStatus.status }}</span
                            >
                        </div>
                        <div
                            class="cf-glass border-border/70 rounded-2xl border p-3"
                        >
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{
                                    isRTL ? 'الملفات المفهرسة' : 'Indexed Files'
                                }}</span
                            >
                            <span
                                class="text-primary mt-1 block font-mono text-xs font-black tabular-nums"
                                >{{ scanStatus.processed_files }} /
                                {{ scanStatus.total_files }}</span
                            >
                        </div>
                        <div
                            class="cf-glass border-border/70 rounded-2xl border p-3"
                        >
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{
                                    isRTL ? 'نسبة الإنجاز' : 'Completion'
                                }}</span
                            >
                            <span
                                class="mt-1 block font-mono text-xs font-black text-emerald-400 tabular-nums"
                                >{{ scanStatus.progress_percent }}%</span
                            >
                        </div>
                        <div
                            class="cf-glass border-border/70 rounded-2xl border p-3"
                        >
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{
                                    isRTL ? 'الملف الجاري' : 'Current Stream'
                                }}</span
                            >
                            <span
                                class="text-foreground mt-1 block truncate font-mono text-[11px] font-bold"
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

                    <div
                        class="border-border/70 bg-muted/50 h-2 w-full overflow-hidden rounded-full border"
                    >
                        <div
                            class="bg-primary h-full transition-all duration-300"
                            :style="{
                                width: `${scanStatus.progress_percent || 0}%`,
                            }"
                        ></div>
                    </div>
                </div>

                <!-- ENGINE 2: DISK ORGANIZER PANEL -->
                <div v-else-if="activeTab === 'organizer'" class="space-y-4">
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                        <div
                            class="cf-glass border-border/70 rounded-2xl border p-3"
                        >
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{
                                    isRTL ? 'حالة التوليد' : 'Plan State'
                                }}</span
                            >
                            <span
                                class="text-foreground mt-1 block text-xs font-black capitalize"
                                >{{ planJobStatus.status }}</span
                            >
                        </div>
                        <div
                            class="cf-glass border-border/70 rounded-2xl border p-3"
                        >
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{
                                    isRTL ? 'العناصر المحللة' : 'Items Analyzed'
                                }}</span
                            >
                            <span
                                class="mt-1 block font-mono text-xs font-black text-emerald-400 tabular-nums"
                                >{{ planJobStatus.processed_count }} /
                                {{ planJobStatus.total_files }}</span
                            >
                        </div>
                        <div
                            class="cf-glass border-border/70 rounded-2xl border p-3"
                        >
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{
                                    isRTL ? 'نسبة الخطة' : 'Plan Progress'
                                }}</span
                            >
                            <span
                                class="text-primary mt-1 block font-mono text-xs font-black tabular-nums"
                                >{{ planJobStatus.progress_percent }}%</span
                            >
                        </div>
                        <div
                            class="cf-glass border-border/70 rounded-2xl border p-3"
                        >
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{
                                    isRTL ? 'الإجراء الحالي' : 'Current Action'
                                }}</span
                            >
                            <span
                                class="text-foreground mt-1 block truncate font-mono text-[11px] font-bold"
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

                    <div
                        class="border-border/70 bg-muted/50 h-2 w-full overflow-hidden rounded-full border"
                    >
                        <div
                            class="bg-primary h-full transition-all duration-300"
                            :style="{
                                width: `${planJobStatus.progress_percent || 0}%`,
                            }"
                        ></div>
                    </div>
                </div>

                <!-- ENGINE 3: SUBTITLE STUDIO PANEL -->
                <div v-else-if="activeTab === 'subtitles'" class="space-y-4">
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-5">
                        <div
                            class="cf-glass border-border/70 rounded-2xl border p-3"
                        >
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{ isRTL ? 'الحالة' : 'Status' }}</span
                            >
                            <span
                                class="text-foreground mt-1 block text-xs font-black capitalize"
                                >{{ subtitleStatus.status }}</span
                            >
                        </div>
                        <div
                            class="cf-glass border-border/70 rounded-2xl border p-3"
                        >
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{ isRTL ? 'ملفات مفحوصة' : 'Scanned' }}</span
                            >
                            <span
                                class="text-primary mt-1 block font-mono text-xs font-black tabular-nums"
                                >{{ subtitleStatus.processed_count }} /
                                {{ subtitleStatus.total_files }}</span
                            >
                        </div>
                        <div
                            class="cf-glass border-border/70 rounded-2xl border p-3"
                        >
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{
                                    isRTL ? 'ترميز معالج' : 'UTF-8 Fixed'
                                }}</span
                            >
                            <span
                                class="mt-1 block font-mono text-xs font-black text-emerald-400 tabular-nums"
                                >{{
                                    subtitleStatus.summary.encoding_fixed_count
                                }}</span
                            >
                        </div>
                        <div
                            class="cf-glass border-border/70 rounded-2xl border p-3"
                        >
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{ isRTL ? 'تسمية معيارية' : 'Renamed' }}</span
                            >
                            <span
                                class="text-primary mt-1 block font-mono text-xs font-black tabular-nums"
                                >{{
                                    subtitleStatus.summary.renamed_count
                                }}</span
                            >
                        </div>
                        <div
                            class="cf-glass border-border/70 rounded-2xl border p-3"
                        >
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{
                                    isRTL ? 'ملفات تالفة' : 'Deleted/Bad'
                                }}</span
                            >
                            <span
                                class="mt-1 block font-mono text-xs font-black text-amber-400 tabular-nums"
                                >{{
                                    subtitleStatus.summary.deleted_count +
                                    subtitleStatus.summary.invalid_count
                                }}</span
                            >
                        </div>
                    </div>

                    <div
                        class="border-border/70 bg-muted/50 h-2 w-full overflow-hidden rounded-full border"
                    >
                        <div
                            class="bg-primary h-full transition-all duration-300"
                            :style="{
                                width: `${subtitleStatus.progress_percent || 0}%`,
                            }"
                        ></div>
                    </div>
                </div>

                <!-- Unified Live Terminal Output Feed -->
                <div
                    class="border-border/70 space-y-2.5 rounded-2xl border bg-black/80 p-3.5 sm:p-4"
                >
                    <div
                        class="border-border/60 flex items-center justify-between border-b pb-2"
                    >
                        <div class="flex items-center gap-2">
                            <Terminal class="text-primary h-3.5 w-3.5" />
                            <span class="text-foreground text-xs font-bold">
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
                                type="button"
                                @click="copyTerminalLogs"
                                class="text-muted-foreground hover:text-foreground flex cursor-pointer items-center gap-1 rounded-lg bg-white/5 px-2 py-0.5 text-[10px] font-bold transition-all hover:bg-white/10"
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
                                    type="button"
                                    @click="terminalFilter = flt"
                                    class="cursor-pointer rounded-lg px-2 py-0.5 font-bold uppercase transition-all"
                                    :class="
                                        terminalFilter === flt
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:text-foreground bg-white/5'
                                    "
                                >
                                    {{ flt }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        id="unified-terminal-feed"
                        class="custom-scrollbar h-44 space-y-1.5 overflow-y-auto pe-2 font-mono text-[11px] tabular-nums sm:h-48"
                    >
                        <div
                            v-for="(log, idx) in activeLogs"
                            :key="idx"
                            class="flex items-start gap-2 leading-relaxed"
                        >
                            <span class="shrink-0 text-slate-500 select-none"
                                >[{{ log.time }}]</span
                            >
                            <span
                                :class="{
                                    'text-emerald-400': log.level === 'success',
                                    'text-primary': log.level === 'info',
                                    'text-amber-400': log.level === 'warning',
                                    'text-destructive font-bold':
                                        log.level === 'error',
                                }"
                            >
                                {{ log.message }}
                            </span>
                        </div>
                        <div
                            v-if="activeLogs.length === 0"
                            class="text-muted-foreground py-8 text-center italic"
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
                class="border-border/70 relative z-10 flex shrink-0 flex-wrap items-center justify-between gap-3 border-t pt-3"
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
                            type="button"
                            @click="() => startFullScan()"
                            class="bg-primary text-primary-foreground shadow-primary/25 flex cursor-pointer items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-extrabold shadow-lg transition-all hover:brightness-105"
                        >
                            <Play class="h-3.5 w-3.5 fill-current" />
                            <span>{{
                                isRTL ? 'بدء الفحص' : 'Start Scan'
                            }}</span>
                        </button>

                        <button
                            v-if="isScanning"
                            type="button"
                            @click="pauseScan"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/35 bg-amber-500/20 px-4 py-2 text-xs font-bold text-amber-300 transition-all hover:bg-amber-500/30"
                        >
                            <Pause class="h-3.5 w-3.5" />
                            <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                        </button>

                        <button
                            v-if="isScanPaused"
                            type="button"
                            @click="resumeScan"
                            class="bg-primary text-primary-foreground shadow-primary/25 flex cursor-pointer items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-bold shadow-lg transition-all hover:brightness-105"
                        >
                            <Play class="h-3.5 w-3.5 fill-current" />
                            <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                        </button>
                    </template>

                    <!-- ORGANIZER CONTROLS -->
                    <template v-else-if="activeTab === 'organizer'">
                        <button
                            v-if="isAnalyzing"
                            type="button"
                            @click="pausePlanJob"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/35 bg-amber-500/20 px-4 py-2 text-xs font-bold text-amber-300 transition-all hover:bg-amber-500/30"
                        >
                            <Pause class="h-3.5 w-3.5" />
                            <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                        </button>

                        <button
                            v-if="isOrgPaused"
                            type="button"
                            @click="resumePlanJob"
                            class="bg-primary text-primary-foreground shadow-primary/25 flex cursor-pointer items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-bold shadow-lg transition-all hover:brightness-105"
                        >
                            <Play class="h-3.5 w-3.5 fill-current" />
                            <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                        </button>

                        <button
                            v-if="planJobStatus.status === 'completed'"
                            type="button"
                            @click="navigateToEnginePage('/organizer?step=2')"
                            class="bg-primary text-primary-foreground shadow-primary/25 flex cursor-pointer items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-black shadow-lg transition-all hover:brightness-105"
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
                            type="button"
                            @click="pauseHealthJob"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/35 bg-amber-500/20 px-4 py-2 text-xs font-bold text-amber-300 transition-all hover:bg-amber-500/30"
                        >
                            <Pause class="h-3.5 w-3.5" />
                            <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                        </button>

                        <button
                            v-if="isSubtitlePaused"
                            type="button"
                            @click="resumeHealthJob"
                            class="bg-primary text-primary-foreground shadow-primary/25 flex cursor-pointer items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-bold shadow-lg transition-all hover:brightness-105"
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
                        type="button"
                        @click="handleCancelActiveJob"
                        class="border-destructive/35 bg-destructive/15 text-destructive hover:bg-destructive/25 flex cursor-pointer items-center gap-1.5 rounded-xl border px-3.5 py-2 text-xs font-bold transition-all"
                    >
                        <XCircle class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'إلغاء العملية' : 'Cancel' }}</span>
                    </button>
                </div>

                <!-- Right Destination Navigation & Close -->
                <div class="flex items-center gap-2">
                    <button
                        v-if="activeTab === 'scanner'"
                        type="button"
                        @click="navigateToEnginePage('/scanner')"
                        class="cf-glass border-border/80 text-foreground hover:border-primary/40 flex cursor-pointer items-center gap-1 rounded-xl border px-3 py-1.5 text-xs font-bold transition-all"
                    >
                        <span>{{
                            isRTL ? 'صفحة الفاحص' : 'Scanner Page'
                        }}</span>
                        <ArrowUpRight class="h-3 w-3" />
                    </button>

                    <button
                        v-else-if="activeTab === 'organizer'"
                        type="button"
                        @click="navigateToEnginePage('/organizer')"
                        class="cf-glass border-border/80 text-foreground hover:border-primary/40 flex cursor-pointer items-center gap-1 rounded-xl border px-3 py-1.5 text-xs font-bold transition-all"
                    >
                        <span>{{
                            isRTL ? 'صفحة المنظم' : 'Organizer Page'
                        }}</span>
                        <ArrowUpRight class="h-3 w-3" />
                    </button>

                    <button
                        v-else-if="activeTab === 'subtitles'"
                        type="button"
                        @click="navigateToEnginePage('/subtitles')"
                        class="cf-glass border-border/80 text-foreground hover:border-primary/40 flex cursor-pointer items-center gap-1 rounded-xl border px-3 py-1.5 text-xs font-bold transition-all"
                    >
                        <span>{{
                            isRTL ? 'استوديو الترجمات' : 'Subtitle Studio'
                        }}</span>
                        <ArrowUpRight class="h-3 w-3" />
                    </button>

                    <button
                        type="button"
                        @click="closeActivityCenter"
                        class="cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground cursor-pointer rounded-xl border px-4 py-2 text-xs font-bold transition-all"
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
