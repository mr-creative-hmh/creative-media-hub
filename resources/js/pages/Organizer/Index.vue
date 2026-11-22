<script setup lang="ts">
import {
    ref,
    computed,
    watch,
    onMounted,
    onUnmounted,
    defineAsyncComponent,
} from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import PageBanner from '@/components/common/PageBanner.vue';
import OrganizerConfigStep, {
    type OrganizerStrategy,
} from '@/components/organizer/OrganizerConfigStep.vue';
import type { PlanItem } from '@/components/organizer/OrganizerItemCorrectionModal.vue';
import { useOrganizerPlan } from '@/composables/useOrganizerPlan';
import { useToast } from '@/composables/useToast';
import { HardDrive, Play, Check, X } from '@lucide/vue';

const OrganizerDryRunStep = defineAsyncComponent(
    () => import('@/components/organizer/OrganizerDryRunStep.vue'),
);
const OrganizerExecutionStep = defineAsyncComponent(
    () => import('@/components/organizer/OrganizerExecutionStep.vue'),
);
const OrganizerItemCorrectionModal = defineAsyncComponent(
    () => import('@/components/organizer/OrganizerItemCorrectionModal.vue'),
);
const OrganizerBatchCorrectionModal = defineAsyncComponent(
    () => import('@/components/organizer/OrganizerBatchCorrectionModal.vue'),
);

const props = defineProps<{
    defaultMovieTemplate: string;
    defaultSeriesTemplate: string;
    defaultWorkingDir?: string;
    watcherStatus?: any;
}>();

const { t, isRTL } = useI18n();
const toast = useToast();

const step = ref<1 | 2 | 3>(1);
const sourceMode = ref<'virtual' | 'folder'>('virtual');
const sourceFolder = ref('D:/Downloads');
const targetRoot = ref(props.defaultWorkingDir || 'H:/Entertainment');

// Strategies catalog
const strategies: OrganizerStrategy[] = [
    {
        id: 'cmh',
        tag: 'Creative Media Hub',
        nameAr: 'نمط Creative Media Hub الذكي (موصى به)',
        nameEn: 'Creative Media Hub Smart Standard (Recommended)',
        descAr: 'تصنيف تلقائي متكامل: ينظم الأفلام حسب النوع ويجمع سلاسل الأفلام (Collections) تلقائياً مع معالجة الأفلام الفردية بنظافة.',
        descEn: 'Intelligent multi-tier structure: auto-detects movie collections/franchises and genres with clean standalone fallbacks.',
        movie: '{Type}/{Genre}/{Collection}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}',
        series: '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{CleanResolution}].{ext}',
        exampleMovie:
            'Movies/Fantasy/Harry Potter Collection/Harry Potter and the Goblet of Fire (2005)/... [1080p].mkv',
        exampleSeries:
            'TV Shows/Breaking Bad (2008)/Season 01/Breaking Bad - S01E01 - Pilot [1080p].mkv',
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
        exampleSeries:
            'TV Shows/Rick and Morty (2013)/Season 01/Rick and Morty - S01E01 - Pilot [1080p].mkv',
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
        exampleSeries:
            'TV Shows/Breaking Bad/Season 01/Breaking Bad S01E01.mkv',
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

const selectedStrategyId = ref('cmh');
const movieTemplate = ref(props.defaultMovieTemplate || strategies[0].movie);
const seriesTemplate = ref(props.defaultSeriesTemplate || strategies[0].series);
const conflictStrategy = ref<
    'skip' | 'overwrite' | 'rename' | 'keep_higher_quality'
>('skip');
const omitEpisodeTitle = ref(false);
const fetchEpisodeTitles = ref(true);
const titleLanguage = ref<'original' | 'english' | 'arabic'>('original');

// Plan Composable
const {
    startPlanJob,
    pausePlanJob,
    resumePlanJob,
    cancelPlanJob,
    planJobStatus,
    isAnalyzing,
} = useOrganizerPlan();

// Local Plan Items array for step 2 review & manual corrections
const localPlanItems = ref<PlanItem[]>([]);

// Modals
const showCorrectionModal = ref(false);
const activeEditItem = ref<PlanItem | null>(null);
const showBatchModal = ref(false);
const batchSelectedItems = ref<PlanItem[]>([]);
const showExecutionConfirmModal = ref(false);

// Execution mode selected at the end of Step 2
const executeMode = ref<'move' | 'copy'>('move');
const cleanupEmptyFolders = ref(true);

// Execution state
const executionStatus = ref<
    'idle' | 'running' | 'paused' | 'completed' | 'cancelled' | 'error'
>('idle');
const processedCount = ref(0);
const currentExecutionFile = ref<string | null>(null);
const currentExecutionAction = ref<string | null>(null);
const executionLogs = ref<any[]>([]);

const executionProgressPercent = computed(() => {
    if (!localPlanItems.value.length) return 0;
    return Math.min(
        100,
        (processedCount.value / localPlanItems.value.length) * 100,
    );
});

// Watch plan job completion from background worker
watch(
    () => planJobStatus.value.status,
    (newStatus) => {
        if (
            newStatus === 'completed' &&
            planJobStatus.value.plan_items.length > 0
        ) {
            localPlanItems.value = JSON.parse(
                JSON.stringify(planJobStatus.value.plan_items),
            );
            step.value = 2;
            const url = new URL(window.location.href);
            url.searchParams.set('step', '2');
            window.history.replaceState({}, '', url.toString());
            toast.success(
                isRTL.value
                    ? 'اكتمل توليد خطة التنظيم التجريبية'
                    : 'Dry-run plan generated successfully!',
            );
        }
    },
);

const handleStepNav = (e: Event) => {
    const detail = (e as CustomEvent<{ step?: 1 | 2 | 3 }>).detail;
    if (detail?.step) {
        step.value = detail.step;
    }
};

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    const requestedStep = params.get('step');

    try {
        const res = await fetch('/api/organizer/plan/status');
        if (res.ok) {
            const data = await res.json();
            if (data.plan_items && data.plan_items.length > 0) {
                localPlanItems.value = data.plan_items;
                if (requestedStep === '2' || data.status === 'completed') {
                    step.value = 2;
                }
            }
        }
    } catch {}

    window.addEventListener('cmh:navigate-step', handleStepNav);
    window.addEventListener('organizer:navigate-step', handleStepNav);
});

onUnmounted(() => {
    window.removeEventListener('cmh:navigate-step', handleStepNav);
    window.removeEventListener('organizer:navigate-step', handleStepNav);
    if (executionStatus.value === 'running') {
        pauseExecution();
    }
});

const startDryRun = async () => {
    try {
        await startPlanJob({
            source_path: sourceFolder.value,
            target_root: targetRoot.value,
            movie_template: movieTemplate.value,
            series_template: seriesTemplate.value,
            source_mode: sourceMode.value,
            title_language: titleLanguage.value,
            fetch_episode_titles: fetchEpisodeTitles.value,
        });
    } catch {
        toast.error(
            isRTL.value ? 'فشل بدء فحص الملفات' : 'Failed to launch plan job',
        );
    }
};

const openEditModal = (item: PlanItem) => {
    activeEditItem.value = JSON.parse(JSON.stringify(item));
    showCorrectionModal.value = true;
};

const handleApplyCorrection = (updatedItem: PlanItem) => {
    const idx = localPlanItems.value.findIndex(
        (i) => i.source_path === updatedItem.source_path,
    );
    if (idx !== -1) {
        localPlanItems.value[idx] = updatedItem;
        toast.success(
            isRTL.value ? 'تم حفظ التعديل بنجاح' : 'Correction applied!',
        );
    }
    showCorrectionModal.value = false;
};

const handleResetItem = (item: PlanItem) => {
    const idx = localPlanItems.value.findIndex(
        (i) => i.source_path === item.source_path,
    );
    if (idx !== -1 && item.original_item) {
        localPlanItems.value[idx] = JSON.parse(
            JSON.stringify(item.original_item),
        );
        toast.info(
            isRTL.value
                ? 'تمت استعادة البيانات الأصلية'
                : 'Item reset to original plan',
        );
    }
};

const openBatchModal = (items: PlanItem[]) => {
    batchSelectedItems.value = items;
    showBatchModal.value = true;
};

const handleApplyBatchCorrection = (updates: Partial<PlanItem>) => {
    for (const selected of batchSelectedItems.value) {
        const idx = localPlanItems.value.findIndex(
            (i) => i.source_path === selected.source_path,
        );
        if (idx !== -1) {
            localPlanItems.value[idx] = {
                ...localPlanItems.value[idx],
                ...updates,
                is_manually_edited: true,
            };
        }
    }
    toast.success(
        isRTL.value
            ? 'تم تطبيق التعديلات المجمعة بنجاح'
            : 'Batch corrections applied!',
    );
    showBatchModal.value = false;
};

// Execution Engine (Chunked Batch Runner)
const openExecutionConfirmation = () => {
    showExecutionConfirmModal.value = true;
};

const runBatchStep = async () => {
    if (executionStatus.value !== 'running') return;

    try {
        const res = await fetch('/api/organizer/execute/batch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
            body: JSON.stringify({ batch_size: 1 }),
        });

        if (!res.ok) {
            executionStatus.value = 'error';
            toast.error(
                isRTL.value
                    ? 'فشلت معالجة حزمة الملفات'
                    : 'Batch execution failed',
            );
            return;
        }

        const data = await res.json();
        const status = data.status;

        if (status) {
            processedCount.value = status.processed_count || 0;
            currentExecutionFile.value = status.current_file || null;
            currentExecutionAction.value = status.current_action || null;
            if (status.logs && status.logs.length > 0) {
                executionLogs.value = status.logs;
            }
            if (status.is_completed) {
                executionStatus.value = 'completed';
                processedCount.value = localPlanItems.value.length;
                toast.success(
                    isRTL.value
                        ? 'اكتملت جميع العمليات على القرص بنجاح!'
                        : 'All operations completed on disk!',
                );
                return;
            }
        }

        if (data.has_more && executionStatus.value === 'running') {
            setTimeout(runBatchStep, 60);
        } else {
            executionStatus.value = 'completed';
            processedCount.value = localPlanItems.value.length;
            toast.success(
                isRTL.value
                    ? 'اكتملت جميع العمليات على القرص بنجاح!'
                    : 'All operations completed on disk!',
            );
        }
    } catch {
        executionStatus.value = 'error';
        toast.error('Batch network error');
    }
};

const confirmAndExecutePlan = async () => {
    showExecutionConfirmModal.value = false;
    step.value = 3;
    executionStatus.value = 'running';
    processedCount.value = 0;
    executionLogs.value = [];

    try {
        const approvedItems = localPlanItems.value.filter(
            (i) => i.status === 'ready' || i.selected !== false,
        );
        const res = await fetch('/api/organizer/execute/init', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
            body: JSON.stringify({
                plan:
                    approvedItems.length > 0
                        ? approvedItems
                        : localPlanItems.value,
                mode: executeMode.value,
                cleanup_empty_folders: cleanupEmptyFolders.value,
            }),
        });

        if (!res.ok) {
            executionStatus.value = 'error';
            toast.error(
                isRTL.value
                    ? 'فشل تهيئة تنفيذ الخطة'
                    : 'Failed to initialize execution',
            );
            return;
        }

        const data = await res.json();
        if (data.status?.logs) {
            executionLogs.value = data.status.logs;
        }

        runBatchStep();
    } catch {
        executionStatus.value = 'error';
        toast.error('Execution request failed');
    }
};

const pauseExecution = async () => {
    executionStatus.value = 'paused';
    try {
        await fetch('/api/organizer/execute/pause', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
        });
    } catch {}
};

const resumeExecution = async () => {
    executionStatus.value = 'running';
    try {
        await fetch('/api/organizer/execute/resume', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
        });
        runBatchStep();
    } catch {}
};

const cancelExecution = async () => {
    executionStatus.value = 'cancelled';
    try {
        await fetch('/api/organizer/execute/cancel', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
        });
    } catch {}
};

const resetWorkflow = () => {
    step.value = 1;
    localPlanItems.value = [];
    executionStatus.value = 'idle';
    processedCount.value = 0;
    executionLogs.value = [];
};
</script>

<template>
    <AppLayout>
        <Head
            :title="
                isRTL ? 'منظم الأقراص والملفات الذكي' : 'Smart Disk Organizer'
            "
        />

        <div class="space-y-8 pb-12">
            <!-- PageBanner Header with Step Navigator -->
            <PageBanner
                :icon="HardDrive"
                :title="
                    isRTL
                        ? 'منظم الأقراص والمكتبة الذكي'
                        : 'Physical Disk Organizer'
                "
                :description="
                    isRTL
                        ? 'إعادة هيكلة وتسمية المجلدات والملفات وفق المعايير القياسية مع محاكاة آمنة وتعديلات يدوية'
                        : 'Standardize folder hierarchy, rename files cleanly with safe simulation and interactive manual corrections'
                "
                badge="Storage Studio"
            >
                <template #actions>
                    <!-- Step Navigator Indicator -->
                    <div
                        class="cf-glass border-border/80 flex items-center gap-1.5 rounded-2xl border p-1.5"
                    >
                        <button
                            type="button"
                            @click="step = 1"
                            :disabled="step === 3"
                            class="flex min-h-9 cursor-pointer items-center gap-2 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all active:scale-95"
                            :class="
                                step === 1
                                    ? 'bg-primary text-primary-foreground shadow-primary/25 shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-black/20 text-[10px] tabular-nums"
                                >1</span
                            >
                            <span>{{ isRTL ? 'الإعدادات' : 'Configure' }}</span>
                        </button>
                        <button
                            type="button"
                            @click="step = 2"
                            :disabled="
                                step === 1 ||
                                step === 3 ||
                                localPlanItems.length === 0
                            "
                            class="flex min-h-9 cursor-pointer items-center gap-2 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all active:scale-95"
                            :class="
                                step === 2
                                    ? 'bg-primary text-primary-foreground shadow-primary/25 shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40'
                            "
                        >
                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-black/20 text-[10px] tabular-nums"
                                >2</span
                            >
                            <span>{{
                                isRTL ? 'مراجعة وتعديل' : 'Review Plan'
                            }}</span>
                        </button>
                        <button
                            type="button"
                            @click="step = 3"
                            :disabled="step !== 3"
                            class="flex min-h-9 cursor-pointer items-center gap-2 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all active:scale-95"
                            :class="
                                step === 3
                                    ? 'bg-primary text-primary-foreground shadow-primary/25 shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40'
                            "
                        >
                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-black/20 text-[10px] tabular-nums"
                                >3</span
                            >
                            <span>{{
                                isRTL ? 'التنفيذ الحي' : 'Execute'
                            }}</span>
                        </button>
                    </div>
                </template>
            </PageBanner>

            <!-- Step 1: Configuration -->
            <OrganizerConfigStep
                v-if="step === 1"
                v-model:source-mode="sourceMode"
                v-model:source-folder="sourceFolder"
                v-model:target-root="targetRoot"
                v-model:selected-strategy-id="selectedStrategyId"
                v-model:movie-template="movieTemplate"
                v-model:series-template="seriesTemplate"
                v-model:conflict-strategy="conflictStrategy"
                v-model:title-language="titleLanguage"
                :strategies="strategies"
                :omit-episode-title="omitEpisodeTitle"
                :fetch-episode-titles="fetchEpisodeTitles"
                :watcher="props.watcherStatus"
                :is-generating-plan="isAnalyzing"
                :plan-job-status="planJobStatus"
                @toggle-omit-episode-title="
                    omitEpisodeTitle = !omitEpisodeTitle
                "
                @toggle-fetch-episode-titles="
                    fetchEpisodeTitles = !fetchEpisodeTitles
                "
                @start-dry-run="startDryRun"
                @pause-plan="pausePlanJob"
                @resume-plan="resumePlanJob"
                @cancel-plan="cancelPlanJob"
            />

            <!-- Step 2: Dry Run Review & Manual Corrections -->
            <OrganizerDryRunStep
                v-else-if="step === 2"
                :plan-items="localPlanItems"
                @back="step = 1"
                @execute="openExecutionConfirmation"
                @edit-item="openEditModal"
                @reset-item="handleResetItem"
                @batch-edit="openBatchModal"
            />

            <!-- Step 3: Execution Progress & Real-Time Logs -->
            <OrganizerExecutionStep
                v-else-if="step === 3"
                :status="executionStatus"
                :progress-percent="executionProgressPercent"
                :total-files="localPlanItems.length"
                :processed-count="processedCount"
                :current-file="currentExecutionFile"
                :current-action="currentExecutionAction"
                :logs="executionLogs"
                @pause="pauseExecution"
                @resume="resumeExecution"
                @cancel="cancelExecution"
                @reset="resetWorkflow"
            />
        </div>

        <!-- Modals -->
        <OrganizerItemCorrectionModal
            v-if="showCorrectionModal"
            :is-open="showCorrectionModal"
            :item="activeEditItem"
            :target-root="targetRoot"
            :movie-template="movieTemplate"
            :series-template="seriesTemplate"
            @close="showCorrectionModal = false"
            @save="handleApplyCorrection"
        />

        <OrganizerBatchCorrectionModal
            v-if="showBatchModal"
            :is-open="showBatchModal"
            :selected-items="batchSelectedItems"
            :target-root="targetRoot"
            :movie-template="movieTemplate"
            :series-template="seriesTemplate"
            @close="showBatchModal = false"
            @apply="handleApplyBatchCorrection"
        />

        <!-- Step 2 Execution Confirmation Modal (Move vs Copy selection at the end) -->
        <div
            v-if="showExecutionConfirmModal"
            class="animate-fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-md"
            :dir="isRTL ? 'rtl' : 'ltr'"
            @click.self="showExecutionConfirmModal = false"
        >
            <div
                class="cf-glass-elevated border-border/80 bg-card/95 relative flex w-full max-w-lg flex-col overflow-hidden rounded-3xl border shadow-2xl"
            >
                <div
                    class="border-border/70 bg-background/40 flex items-center justify-between border-b px-6 py-5"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="border-primary/35 bg-primary/15 text-primary flex h-10 w-10 items-center justify-center rounded-2xl border"
                        >
                            <Play class="h-5 w-5 fill-current" />
                        </div>
                        <div>
                            <h3
                                class="text-foreground text-base font-extrabold"
                            >
                                {{
                                    t('organizer.confirm_title') ||
                                    (isRTL
                                        ? 'تأكيد تنفيذ خطة التنظيم على القرص'
                                        : 'Confirm Plan Execution')
                                }}
                            </h3>
                            <p class="text-muted-foreground text-xs">
                                {{
                                    t('organizer.confirm_desc') ||
                                    (isRTL
                                        ? 'اختر نمط المعالجة قبل بدء النقل الفعلي'
                                        : 'Select action mode and confirm before applying changes')
                                }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="showExecutionConfirmModal = false"
                        class="border-border/60 bg-background/80 text-muted-foreground hover:bg-muted hover:text-foreground flex size-8 cursor-pointer items-center justify-center rounded-xl border transition-colors"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="space-y-5 p-6 text-xs">
                    <!-- Summary info -->
                    <div
                        class="cf-glass border-border/80 space-y-2 rounded-2xl border p-4"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground font-semibold">{{
                                t('organizer.approved_files') ||
                                (isRTL
                                    ? 'عدد الملفات المعتمدة:'
                                    : 'Approved files:')
                            }}</span>
                            <span
                                class="text-foreground font-mono font-bold tabular-nums"
                                >{{
                                    localPlanItems.filter(
                                        (i) => i.status === 'ready',
                                    ).length
                                }}
                                {{ isRTL ? 'ملف' : 'files' }}</span
                            >
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground font-semibold">{{
                                t('organizer.target_root') ||
                                (isRTL
                                    ? 'مسار الوجهة الرئيسي:'
                                    : 'Target root:')
                            }}</span>
                            <span
                                class="text-primary max-w-[240px] truncate font-mono font-bold"
                                :title="targetRoot"
                                >{{ targetRoot }}</span
                            >
                        </div>
                    </div>

                    <!-- Action selection: Move vs Copy -->
                    <div class="space-y-2">
                        <label
                            class="text-muted-foreground block text-[11px] font-bold tracking-wider uppercase"
                        >
                            {{
                                t('organizer.action_mode') ||
                                (isRTL
                                    ? 'اختر نوع العملية (Action Mode):'
                                    : 'Select Action Mode:')
                            }}
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <div
                                @click="executeMode = 'move'"
                                class="flex cursor-pointer flex-col gap-1.5 rounded-2xl border p-3.5 transition-all"
                                :class="
                                    executeMode === 'move'
                                        ? 'border-primary bg-primary/15 text-primary ring-primary/40 shadow-md ring-1'
                                        : 'cf-glass border-border/70 text-muted-foreground hover:border-primary/35 hover:text-foreground'
                                "
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-black">{{
                                        isRTL ? '🚚 نقل (Move)' : '🚚 Move'
                                    }}</span>
                                    <Check
                                        v-if="executeMode === 'move'"
                                        class="text-primary h-4 w-4"
                                    />
                                </div>
                                <p
                                    class="text-muted-foreground text-[10px] leading-relaxed"
                                >
                                    {{
                                        t('organizer.mode_move_desc') ||
                                        (isRTL
                                            ? 'نقل فوري وتحرير المساحة الأصلية (موصى به)'
                                            : 'Fast transfer, frees up source disk space')
                                    }}
                                </p>
                            </div>

                            <div
                                @click="executeMode = 'copy'"
                                class="flex cursor-pointer flex-col gap-1.5 rounded-2xl border p-3.5 transition-all"
                                :class="
                                    executeMode === 'copy'
                                        ? 'border-primary bg-primary/15 text-primary ring-primary/40 shadow-md ring-1'
                                        : 'cf-glass border-border/70 text-muted-foreground hover:border-primary/35 hover:text-foreground'
                                "
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-black">{{
                                        isRTL ? '📋 نسخ (Copy)' : '📋 Copy'
                                    }}</span>
                                    <Check
                                        v-if="executeMode === 'copy'"
                                        class="text-primary h-4 w-4"
                                    />
                                </div>
                                <p
                                    class="text-muted-foreground text-[10px] leading-relaxed"
                                >
                                    {{
                                        t('organizer.mode_copy_desc') ||
                                        (isRTL
                                            ? 'إبقاء الملفات الأصلية دون مساس (يتطلب مساحة إضافية)'
                                            : 'Preserves originals, requires duplicate space')
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Clean empty folders option -->
                    <div
                        v-if="executeMode === 'move'"
                        class="cf-glass border-border/70 rounded-xl border p-3"
                    >
                        <label
                            class="text-foreground flex cursor-pointer items-center gap-2 select-none"
                        >
                            <input
                                type="checkbox"
                                v-model="cleanupEmptyFolders"
                                class="border-border bg-card text-primary cursor-pointer rounded"
                            />
                            <span class="text-xs font-bold">{{
                                t('organizer.clean_empty_folders') ||
                                (isRTL
                                    ? 'تنظيف المجلدات الفارغة المتبقية في المصدر تلقائياً'
                                    : 'Clean up leftover empty source folders automatically')
                            }}</span>
                        </label>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="showExecutionConfirmModal = false"
                            class="cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground h-10 cursor-pointer rounded-xl border px-4 font-bold transition-colors"
                        >
                            {{
                                t('organizer.cancel') ||
                                (isRTL ? 'إلغاء' : 'Cancel')
                            }}
                        </button>
                        <button
                            type="button"
                            @click="confirmAndExecutePlan"
                            class="bg-primary text-primary-foreground shadow-primary/25 flex h-10 cursor-pointer items-center gap-2 rounded-xl px-6 font-black shadow-lg transition-all hover:brightness-105 active:scale-95"
                        >
                            <Play class="h-4 w-4 fill-current" />
                            <span>{{
                                t('organizer.confirm_btn') ||
                                (isRTL
                                    ? 'تأكيد وبدء المعالجة'
                                    : 'Confirm & Start Processing')
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
