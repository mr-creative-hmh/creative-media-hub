import { isActivityCenterOpen, openActivityCenter, closeActivityCenter } from './useActivityCenterState';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

export interface ScanLog {
    time: string;
    level: 'info' | 'success' | 'warning' | 'error';
    message: string;
}

export interface ScanJobStatus {
    status: 'idle' | 'scanning' | 'paused' | 'completed' | 'cancelled';
    total_files: number;
    processed_files: number;
    progress_percent: number;
    current_file: string | null;
    scanned_items?: any[];
    logs?: ScanLog[];
    started_at?: string;
    updated_at?: string;
}

export interface LibraryStats {
    total_movies: number;
    total_series: number;
    total_episodes: number;
    total_subtitles: number;
    total_collections: number;
    storage_size_formatted: string;
}

// Global shared reactive state
const isScanModalOpen = ref(false);
const isWorkerRunning = ref(false);
const isExplicitlyPaused = ref(false);
const isExplicitlyCancelled = ref(false);
const liveStats = ref<LibraryStats | null>(null);

const scanStatus = ref<ScanJobStatus>({
    status: 'idle',
    total_files: 0,
    processed_files: 0,
    progress_percent: 0,
    current_file: '',
    scanned_items: [],
    logs: [],
});

export function useScanner() {
    const isScanning = computed(() => scanStatus.value.status === 'scanning' && !isExplicitlyPaused.value && !isExplicitlyCancelled.value);
    const isPaused = computed(() => scanStatus.value.status === 'paused' || isExplicitlyPaused.value);

    const getCsrfToken = () => (document.querySelector('meta[name="csrf-token"]') as any)?.content || '';

    const openScanModal = () => {
        openActivityCenter('scanner');
        fetchStatus();
    };

    const closeScanModal = () => {
        closeActivityCenter();
    };

    const fetchStatus = async () => {
        try {
            const res = await fetch('/api/scanner/status');
            if (res.ok) {
                const data = await res.json();
                if (data && typeof data === 'object' && data.status) {
                    if (isExplicitlyCancelled.value) {
                        data.status = 'cancelled';
                    } else if (isExplicitlyPaused.value) {
                        data.status = 'paused';
                    }
                    scanStatus.value = data;
                    if (data.stats) {
                        liveStats.value = data.stats;
                    }
                    if (data.status === 'scanning' && !isWorkerRunning.value && !isExplicitlyPaused.value && !isExplicitlyCancelled.value) {
                        runBackgroundWorker();
                    }
                }
            }
        } catch (e) {}
    };

    const runBackgroundWorker = async () => {
        if (isWorkerRunning.value) return;
        isWorkerRunning.value = true;

        let consecutiveErrors = 0;

        while (!isExplicitlyCancelled.value && (scanStatus.value.status === 'scanning' || scanStatus.value.status === 'paused' || isExplicitlyPaused.value)) {
            if (isExplicitlyPaused.value || scanStatus.value.status === 'paused') {
                // If paused, wait and poll status without terminating worker
                await new Promise((r) => setTimeout(r, 500));
                continue;
            }

            try {
                const res = await fetch('/api/scanner/process-batch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify({ batch_size: 10 }),
                });

                if (res.ok) {
                    consecutiveErrors = 0;
                    const data = await res.json();

                    if (isExplicitlyCancelled.value) {
                        scanStatus.value.status = 'cancelled';
                        break;
                    }

                    if (data.status && typeof data.status === 'object') {
                        if (isExplicitlyPaused.value) {
                            data.status.status = 'paused';
                        }
                        scanStatus.value = data.status;
                    }

                    if (data.stats) {
                        liveStats.value = data.stats;
                    } else if (data.status && data.status.stats) {
                        liveStats.value = data.status.stats;
                    }

                    if (!data.has_more || scanStatus.value.status === 'completed' || scanStatus.value.status === 'cancelled') {
                        break;
                    }
                } else {
                    consecutiveErrors++;
                    if (consecutiveErrors >= 6) {
                        await fetchStatus();
                        if (scanStatus.value.status !== 'scanning') break;
                    }
                    await new Promise((r) => setTimeout(r, 1000));
                }
            } catch (e) {
                consecutiveErrors++;
                if (consecutiveErrors >= 6) {
                    await fetchStatus();
                    if (scanStatus.value.status !== 'scanning') break;
                }
                await new Promise((r) => setTimeout(r, 1000));
            }

            await new Promise((r) => setTimeout(r, 80));
        }

        isWorkerRunning.value = false;
    };

    const startFullScan = async (directories?: any[], scanMode: 'incremental' | 'fresh' = 'incremental') => {
        isExplicitlyCancelled.value = false;
        isExplicitlyPaused.value = false;
        try {
            const res = await fetch('/api/scanner/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ directories: directories || [], scan_mode: scanMode }),
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) {
                    scanStatus.value = data.status;
                }
                runBackgroundWorker();
            }
        } catch (e) {}
    };

    const rescanFresh = async () => {
        isExplicitlyCancelled.value = false;
        isExplicitlyPaused.value = false;
        try {
            const res = await fetch('/api/scanner/rescan-fresh', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({}),
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) {
                    scanStatus.value = data.status;
                }
                runBackgroundWorker();
            }
        } catch (e) {}
    };

    const scanFolder = async (path: string, type: string = 'mixed', fresh: boolean = false, scanMode: 'incremental' | 'fresh' = 'incremental') => {
        isExplicitlyCancelled.value = false;
        isExplicitlyPaused.value = false;
        try {
            const res = await fetch('/api/scanner/scan-folder', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ path, type, fresh, scan_mode: scanMode }),
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) {
                    scanStatus.value = data.status;
                }
                runBackgroundWorker();
            }
        } catch (e) {}
    };

    const clearCatalog = async () => {
        try {
            const res = await fetch('/api/scanner/clear-catalog', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) {
                    scanStatus.value = data.status;
                }
                return true;
            }
        } catch (e) {}
        return false;
    };

    const pauseScan = async () => {
        isExplicitlyPaused.value = true;
        scanStatus.value.status = 'paused';
        try {
            const res = await fetch('/api/scanner/pause', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) {
                    scanStatus.value = data.status;
                    scanStatus.value.status = 'paused';
                }
            }
        } catch (e) {}
    };

    const resumeScan = async () => {
        isExplicitlyPaused.value = false;
        isExplicitlyCancelled.value = false;
        scanStatus.value.status = 'scanning';
        try {
            const res = await fetch('/api/scanner/resume', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) {
                    scanStatus.value = data.status;
                }
                runBackgroundWorker();
            }
        } catch (e) {}
    };

    const cancelScan = async () => {
        isExplicitlyCancelled.value = true;
        isExplicitlyPaused.value = false;
        isWorkerRunning.value = false;
        scanStatus.value.status = 'cancelled';
        try {
            const res = await fetch('/api/scanner/cancel', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) {
                    scanStatus.value = data.status;
                    scanStatus.value.status = 'cancelled';
                }
            }
        } catch (e) {}
    };

    return {
        isScanModalOpen: isActivityCenterOpen,
        scanStatus,
        liveStats,
        isScanning,
        isPaused,
        openScanModal,
        closeScanModal,
        fetchStatus,
        startFullScan,
        rescanFresh,
        scanFolder,
        clearCatalog,
        pauseScan,
        resumeScan,
        cancelScan,
        runBackgroundWorker,
    };
}