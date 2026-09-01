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

// Global shared reactive state
const isScanModalOpen = ref(false);
const isWorkerRunning = ref(false);

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
    const isScanning = computed(() => scanStatus.value.status === 'scanning');
    const isPaused = computed(() => scanStatus.value.status === 'paused');

    const openScanModal = () => {
        isScanModalOpen.value = true;
        fetchStatus();
    };

    const closeScanModal = () => {
        isScanModalOpen.value = false;
    };

    const fetchStatus = async () => {
        try {
            const res = await fetch('/api/scanner/status');
            if (res.ok) {
                const data = await res.json();
                if (data && typeof data === 'object' && data.status) {
                    scanStatus.value = data;
                    if (data.status === 'scanning' && !isWorkerRunning.value) {
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

        while (scanStatus.value.status === 'scanning' || scanStatus.value.status === 'paused') {
            if (scanStatus.value.status === 'paused') {
                // If paused, wait and poll status without terminating worker
                await new Promise((r) => setTimeout(r, 600));
                continue;
            }

            try {
                const res = await fetch('/api/scanner/process-batch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                    },
                    body: JSON.stringify({ batch_size: 10 }),
                });

                if (res.ok) {
                    consecutiveErrors = 0;
                    const data = await res.json();
                    if (data.status && typeof data.status === 'object') {
                        scanStatus.value = data.status;
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

            await new Promise((r) => setTimeout(r, 100));
        }

        isWorkerRunning.value = false;
    };

    const startFullScan = async (directories?: any[]) => {
        try {
            const res = await fetch('/api/scanner/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
                body: JSON.stringify({ directories: directories || [] }),
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
        try {
            const res = await fetch('/api/scanner/rescan-fresh', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
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

    const scanFolder = async (path: string, type: string = 'mixed', fresh: boolean = false) => {
        try {
            const res = await fetch('/api/scanner/scan-folder', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
                body: JSON.stringify({ path, type, fresh }),
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
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
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
        try {
            scanStatus.value.status = 'paused';
            const res = await fetch('/api/scanner/pause', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) {
                    scanStatus.value = data.status;
                }
            }
        } catch (e) {}
    };

    const resumeScan = async () => {
        try {
            scanStatus.value.status = 'scanning';
            const res = await fetch('/api/scanner/resume', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
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
        try {
            scanStatus.value.status = 'cancelled';
            const res = await fetch('/api/scanner/cancel', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status) {
                    scanStatus.value = data.status;
                }
            }
        } catch (e) {}
    };

    return {
        isScanModalOpen,
        scanStatus,
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
