import { isActivityCenterOpen, openActivityCenter, closeActivityCenter } from './useActivityCenterState';
import { ref, computed } from 'vue';

export interface SubtitleJobLog {
    time: string;
    level: 'info' | 'success' | 'warning' | 'error';
    message: string;
}

export interface SubtitleJobSummary {
    valid_count: number;
    invalid_count: number;
    deleted_count: number;
    renamed_count: number;
    already_standard_count: number;
    encoding_fixed_count: number;
    language_breakdown: Record<string, {
        code: string;
        name_en: string;
        name_ar: string;
        flag: string;
        count: number;
    }>;
}

export interface SubtitleJobStatus {
    status: 'idle' | 'running' | 'paused' | 'completed' | 'cancelled';
    progress_percent: number;
    total_files: number;
    processed_count: number;
    current_file: string | null;
    current_action: string | null;
    logs: SubtitleJobLog[];
    summary: SubtitleJobSummary;
    items: any[];
    started_at: string | null;
    updated_at: string | null;
}

const isSubtitleModalOpen = ref(false);
const isWorkerRunning = ref(false);
const isExplicitlyPaused = ref(false);
const isExplicitlyCancelled = ref(false);

const subtitleStatus = ref<SubtitleJobStatus>({
    status: 'idle',
    progress_percent: 0,
    total_files: 0,
    processed_count: 0,
    current_file: null,
    current_action: null,
    logs: [],
    summary: {
        valid_count: 0,
        invalid_count: 0,
        deleted_count: 0,
        renamed_count: 0,
        already_standard_count: 0,
        encoding_fixed_count: 0,
        language_breakdown: {},
    },
    items: [],
    started_at: null,
    updated_at: null,
});

export function useSubtitleJob() {
    const isSubtitleRunning = computed(() => subtitleStatus.value.status === 'running');
    const isSubtitlePaused = computed(() => subtitleStatus.value.status === 'paused' || isExplicitlyPaused.value);
    const isSubtitleCompleted = computed(() => subtitleStatus.value.status === 'completed');

    const openSubtitleModal = () => {
        openActivityCenter('subtitles');
    };

    const closeSubtitleModal = () => {
        closeActivityCenter();
    };

    const getCsrfToken = () => (document.querySelector('meta[name="csrf-token"]') as any)?.content || '';

    const fetchSubtitleStatus = async () => {
        try {
            const res = await fetch('/api/subtitles/health-check/status');
            if (res.ok) {
                const data = await res.json();
                if (data && typeof data === 'object') {
                    if (isExplicitlyPaused.value) {
                        data.status = 'paused';
                    }
                    if (isExplicitlyCancelled.value) {
                        data.status = 'cancelled';
                    }
                    subtitleStatus.value = data;
                    if (data.status === 'running' && !isWorkerRunning.value && !isExplicitlyPaused.value && !isExplicitlyCancelled.value) {
                        isSubtitleModalOpen.value = true;
                        runBackgroundWorker();
                    }
                }
            }
        } catch (e) {
            console.error('Fetch subtitle job status error:', e);
        }
    };

    const runBackgroundWorker = async () => {
        if (isWorkerRunning.value) return;
        isWorkerRunning.value = true;

        let consecutiveErrors = 0;

        while (!isExplicitlyCancelled.value && (subtitleStatus.value.status === 'running' || subtitleStatus.value.status === 'paused' || isExplicitlyPaused.value)) {
            if (isExplicitlyPaused.value || subtitleStatus.value.status === 'paused') {
                await new Promise((r) => setTimeout(r, 400));
                continue;
            }

            try {
                const res = await fetch('/api/subtitles/health-check/batch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify({ batch_size: 25 }),
                });

                if (res.ok) {
                    consecutiveErrors = 0;
                    const data = await res.json();

                    if (isExplicitlyCancelled.value) {
                        subtitleStatus.value.status = 'cancelled';
                        break;
                    }

                    if (data.status && typeof data.status === 'object') {
                        if (isExplicitlyPaused.value) {
                            data.status.status = 'paused';
                        }
                        subtitleStatus.value = data.status;
                    }

                    if (!data.has_more || subtitleStatus.value.status === 'completed' || subtitleStatus.value.status === 'cancelled') {
                        break;
                    }
                } else {
                    consecutiveErrors++;
                    if (consecutiveErrors >= 5) {
                        await fetchSubtitleStatus();
                        if (subtitleStatus.value.status !== 'running') break;
                    }
                    await new Promise((r) => setTimeout(r, 1000));
                }
            } catch (e) {
                consecutiveErrors++;
                if (consecutiveErrors >= 5) {
                    await fetchSubtitleStatus();
                    if (subtitleStatus.value.status !== 'running') break;
                }
                await new Promise((r) => setTimeout(r, 1000));
            }

            await new Promise((r) => setTimeout(r, 60));
        }

        isWorkerRunning.value = false;
    };

    const startHealthJob = async (options: {
        dry_run?: boolean;
        delete_invalid?: boolean;
        auto_rename?: boolean;
        target_path?: string | null;
    } = {}) => {
        isExplicitlyCancelled.value = false;
        isExplicitlyPaused.value = false;
        openSubtitleModal();

        subtitleStatus.value.status = 'running';
        subtitleStatus.value.logs = [{
            time: new Date().toLocaleTimeString(),
            level: 'info',
            message: 'Starting subtitle health and normalizer audit...',
        }];

        try {
            const res = await fetch('/api/subtitles/health-check/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify(options),
            });

            if (res.ok) {
                const data = await res.json();
                if (data.status && typeof data.status === 'object') {
                    subtitleStatus.value = data.status;
                }
                runBackgroundWorker();
            } else {
                subtitleStatus.value.status = 'idle';
                closeSubtitleModal();
            }
        } catch (e) {
            console.error('Start subtitle job error:', e);
            subtitleStatus.value.status = 'idle';
            closeSubtitleModal();
        }
    };

    const pauseHealthJob = async () => {
        isExplicitlyPaused.value = true;
        subtitleStatus.value.status = 'paused';
        try {
            const res = await fetch('/api/subtitles/health-check/pause', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) {
                    subtitleStatus.value = data.status;
                    subtitleStatus.value.status = 'paused';
                }
            }
        } catch (e) {
            console.error('Pause subtitle job error:', e);
        }
    };

    const resumeHealthJob = async () => {
        isExplicitlyPaused.value = false;
        subtitleStatus.value.status = 'running';
        try {
            const res = await fetch('/api/subtitles/health-check/resume', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) subtitleStatus.value = data.status;
                runBackgroundWorker();
            }
        } catch (e) {
            console.error('Resume subtitle job error:', e);
        }
    };

    const cancelHealthJob = async () => {
        isExplicitlyCancelled.value = true;
        isExplicitlyPaused.value = false;
        isWorkerRunning.value = false;
        subtitleStatus.value.status = 'cancelled';
        try {
            const res = await fetch('/api/subtitles/health-check/cancel', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) {
                    subtitleStatus.value = data.status;
                    subtitleStatus.value.status = 'cancelled';
                }
            }
        } catch (e) {
            console.error('Cancel subtitle job error:', e);
        }
    };

    return {
        isSubtitleModalOpen: isActivityCenterOpen,
        subtitleStatus,
        isSubtitleRunning,
        isSubtitlePaused,
        isSubtitleCompleted,
        openSubtitleModal,
        closeSubtitleModal,
        fetchSubtitleStatus,
        startHealthJob,
        pauseHealthJob,
        resumeHealthJob,
        cancelHealthJob,
    };
}
