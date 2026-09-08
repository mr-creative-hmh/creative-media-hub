import { isActivityCenterOpen, openActivityCenter, closeActivityCenter } from './useActivityCenterState';
import { ref, computed } from 'vue';

export interface PlanJobLog {
    time: string;
    level: 'info' | 'success' | 'error';
    message: string;
}

export interface PlanJobStatus {
    status: 'idle' | 'scanning' | 'generating' | 'paused' | 'completed' | 'cancelled';
    progress_percent: number;
    total_files: number;
    processed_count: number;
    current_file: string | null;
    current_action: string | null;
    logs: PlanJobLog[];
    plan_items: any[];
    started_at: string | null;
    updated_at: string | null;
}

const isPlanModalOpen = ref(false);
const isWorkerRunning = ref(false);
const isExplicitlyPaused = ref(false);
const isExplicitlyCancelled = ref(false);

const planJobStatus = ref<PlanJobStatus>({
    status: 'idle',
    progress_percent: 0,
    total_files: 0,
    processed_count: 0,
    current_file: null,
    current_action: null,
    logs: [],
    plan_items: [],
    started_at: null,
    updated_at: null,
});

export function useOrganizerPlan() {
    const isAnalyzing = computed(() => planJobStatus.value.status === 'scanning' || planJobStatus.value.status === 'generating');
    const isPaused = computed(() => planJobStatus.value.status === 'paused' || isExplicitlyPaused.value);
    const isCompleted = computed(() => planJobStatus.value.status === 'completed');

    const openPlanModal = () => {
        openActivityCenter('organizer');
    };

    const closePlanModal = () => {
        closeActivityCenter();
    };

    const getCsrfToken = () => (document.querySelector('meta[name="csrf-token"]') as any)?.content || '';

    const fetchPlanStatus = async () => {
        try {
            const res = await fetch('/api/organizer/plan/status');
            if (res.ok) {
                const data = await res.json();
                if (data && typeof data === 'object') {
                    if (isExplicitlyPaused.value) {
                        data.status = 'paused';
                    }
                    if (isExplicitlyCancelled.value) {
                        data.status = 'cancelled';
                    }
                    planJobStatus.value = data;
                    if ((data.status === 'generating' || data.status === 'scanning') && !isWorkerRunning.value && !isExplicitlyPaused.value && !isExplicitlyCancelled.value) {
                        isPlanModalOpen.value = true;
                        runBackgroundWorker();
                    }
                }
            }
        } catch (e) {
            console.error('Fetch plan status error:', e);
        }
    };

    const runBackgroundWorker = async () => {
        if (isWorkerRunning.value) return;
        isWorkerRunning.value = true;

        let consecutiveErrors = 0;

        while (!isExplicitlyCancelled.value && (planJobStatus.value.status === 'generating' || planJobStatus.value.status === 'paused' || isExplicitlyPaused.value)) {
            if (isExplicitlyPaused.value || planJobStatus.value.status === 'paused') {
                await new Promise((r) => setTimeout(r, 400));
                continue;
            }

            try {
                const res = await fetch('/api/organizer/plan/process-batch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify({ batch_size: 15 }),
                });

                if (res.ok) {
                    consecutiveErrors = 0;
                    const data = await res.json();
                    
                    if (isExplicitlyCancelled.value) {
                        planJobStatus.value.status = 'cancelled';
                        break;
                    }

                    if (data.status && typeof data.status === 'object') {
                        if (isExplicitlyPaused.value) {
                            data.status.status = 'paused';
                        }
                        planJobStatus.value = data.status;
                    }

                    if (!data.has_more || planJobStatus.value.status === 'completed' || planJobStatus.value.status === 'cancelled') {
                        break;
                    }
                } else {
                    consecutiveErrors++;
                    if (consecutiveErrors >= 5) {
                        await fetchPlanStatus();
                        if (planJobStatus.value.status !== 'generating') break;
                    }
                    await new Promise((r) => setTimeout(r, 1000));
                }
            } catch (e) {
                consecutiveErrors++;
                if (consecutiveErrors >= 5) {
                    await fetchPlanStatus();
                    if (planJobStatus.value.status !== 'generating') break;
                }
                await new Promise((r) => setTimeout(r, 1000));
            }

            await new Promise((r) => setTimeout(r, 80));
        }

        isWorkerRunning.value = false;
    };

    const startPlanJob = async (params: {
        source_path: string;
        target_root: string;
        movie_template?: string;
        series_template?: string;
        source_mode?: 'virtual' | 'folder';
        recursive?: boolean;
    }) => {
        isExplicitlyCancelled.value = false;
        isExplicitlyPaused.value = false;
        openPlanModal();
        planJobStatus.value.status = 'scanning';
        planJobStatus.value.logs = [{
            time: new Date().toLocaleTimeString(),
            level: 'info',
            message: 'Initializing media discovery...',
        }];

        try {
            const res = await fetch('/api/organizer/plan/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify(params),
            });

            if (res.ok) {
                const data = await res.json();
                if (data.status && typeof data.status === 'object') {
                    planJobStatus.value = data.status;
                }
                runBackgroundWorker();
            } else {
                const errData = await res.json().catch(() => ({}));
                planJobStatus.value.status = 'idle';
                alert(errData.message || 'No media files found in the specified source.');
                closePlanModal();
            }
        } catch (e) {
            console.error('Start plan job error:', e);
            planJobStatus.value.status = 'idle';
            closePlanModal();
        }
    };

    const pausePlanJob = async () => {
        isExplicitlyPaused.value = true;
        planJobStatus.value.status = 'paused';
        try {
            const res = await fetch('/api/organizer/plan/pause', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) {
                    planJobStatus.value = data.status;
                    planJobStatus.value.status = 'paused';
                }
            }
        } catch (e) {
            console.error('Pause plan job error:', e);
        }
    };

    const resumePlanJob = async () => {
        isExplicitlyPaused.value = false;
        planJobStatus.value.status = 'generating';
        try {
            const res = await fetch('/api/organizer/plan/resume', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) planJobStatus.value = data.status;
                runBackgroundWorker();
            }
        } catch (e) {
            console.error('Resume plan job error:', e);
        }
    };

    const cancelPlanJob = async () => {
        isExplicitlyCancelled.value = true;
        isExplicitlyPaused.value = false;
        isWorkerRunning.value = false;
        planJobStatus.value.status = 'cancelled';
        try {
            const res = await fetch('/api/organizer/plan/cancel', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) {
                    planJobStatus.value = data.status;
                    planJobStatus.value.status = 'cancelled';
                }
            }
        } catch (e) {
            console.error('Cancel plan job error:', e);
        }
    };

    return {
        isPlanModalOpen: isActivityCenterOpen,
        planJobStatus,
        isAnalyzing,
        isPaused,
        isCompleted,
        openPlanModal,
        closePlanModal,
        fetchPlanStatus,
        startPlanJob,
        pausePlanJob,
        resumePlanJob,
        cancelPlanJob,
        pauseExecution: pausePlanJob,
        resumeExecution: resumePlanJob,
        cancelExecution: cancelPlanJob,
        runBackgroundWorker,
    };
}