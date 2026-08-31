import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

export interface ScanLog {
    time: string;
    level: 'info' | 'success' | 'warning' | 'error';
    message: string;
}

export interface ScanJobStatus {
    status: 'idle' | 'running' | 'paused' | 'completed' | 'cancelled';
    total_files: number;
    processed_files: number;
    progress_percent: number;
    current_file: string;
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
    const isScanning = computed(() => scanStatus.value.status === 'running');
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
                scanStatus.value = data;
                if (data.status === 'running' && !isWorkerRunning.value) {
                    runBackgroundWorker();
                }
            }
        } catch (e) {}
    };

    const runBackgroundWorker = async () => {
        if (isWorkerRunning.value) return;
        isWorkerRunning.value = true;

        while (scanStatus.value.status === 'running') {
            try {
                const res = await fetch('/api/scanner/process-batch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                    },
                    body: JSON.stringify({ batch_size: 4 }),
                });

                if (res.ok) {
                    const data = await res.json();
                    scanStatus.value = data.status;

                    if (!data.has_more || data.status.status === 'completed' || data.status.status === 'cancelled') {
                        break;
                    }
                } else {
                    break;
                }
            } catch (e) {
                break;
            }
            await new Promise((r) => setTimeout(r, 450));
        }

        isWorkerRunning.value = false;
    };

    const startFullScan = async () => {
        try {
            const res = await fetch('/api/scanner/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
                body: JSON.stringify({}),
            });
            if (res.ok) {
                const data = await res.json();
                scanStatus.value = data.status;
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
                scanStatus.value = data.status;
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
                scanStatus.value = data.status;
                return true;
            }
        } catch (e) {}
        return false;
    };

    const pauseScan = async () => {
        try {
            const res = await fetch('/api/scanner/pause', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
            });
            if (res.ok) {
                const data = await res.json();
                scanStatus.value = data.status;
            }
        } catch (e) {}
    };

    const resumeScan = async () => {
        try {
            const res = await fetch('/api/scanner/resume', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
            });
            if (res.ok) {
                const data = await res.json();
                scanStatus.value = data.status;
                runBackgroundWorker();
            }
        } catch (e) {}
    };

    const cancelScan = async () => {
        try {
            const res = await fetch('/api/scanner/cancel', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
            });
            if (res.ok) {
                const data = await res.json();
                scanStatus.value = data.status;
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
        clearCatalog,
        pauseScan,
        resumeScan,
        cancelScan,
        runBackgroundWorker,
    };
}
