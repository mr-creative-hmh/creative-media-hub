import { ref, computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';

export interface TorrentFileItem {
    index: number;
    path: string;
    size: number;
    is_video: boolean;
    selected: boolean;
}

export interface DownloadItem {
    id: number;
    title: string;
    media_type: 'movie' | 'series' | 'subtitle';
    download_type?: 'direct' | 'torrent';
    source_url?: string;
    destination_path?: string;
    destination_folder?: string;
    torrent_files?: TorrentFileItem[] | null;
    selected_files?: any[] | null;
    info_hash?: string | null;
    total_bytes: number;
    downloaded_bytes: number;
    status: 'queued' | 'downloading' | 'paused' | 'completed' | 'failed';
    speed_bytes_sec: number;
    error_message?: string;
    created_at?: string;
    updated_at?: string;
}

export interface DownloaderInspection {
    download_type: 'direct' | 'torrent';
    title: string;
    media_type: 'movie' | 'series';
    total_bytes: number;
    info_hash?: string | null;
    files: TorrentFileItem[];
    default_folder: string;
    destinations: {
        movies: string;
        series: string;
        default: string;
    };
}

const downloads = ref<DownloadItem[]>([]);
const isWorkerRunning = ref(false);
let workerInterval: any = null;

export function useDownloader() {
    const { isRTL } = useI18n();

    const activeDownloads = computed(() =>
        downloads.value.filter(d => d.status === 'downloading' || d.status === 'queued')
    );

    const completedDownloads = computed(() =>
        downloads.value.filter(d => d.status === 'completed')
    );

    const totalSpeedBytesSec = computed(() =>
        downloads.value.reduce((acc, d) => d.status === 'downloading' ? acc + (d.speed_bytes_sec || 0) : acc, 0)
    );

    const totalSpeedDownFormatted = computed(() => {
        const bytes = totalSpeedBytesSec.value;
        if (bytes <= 0) return '0.0 MB/s';
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB/s`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB/s`;
    });

    const totalSpeedUpFormatted = computed(() => {
        // Upload/Seeding simulation proportional to active downloads
        const active = activeDownloads.value.length;
        if (active === 0) return '0.0 MB/s';
        return `${(active * 0.85).toFixed(1)} MB/s`;
    });

    const formatBytes = (bytes: number) => {
        if (!bytes || bytes <= 0) return '0 MB';
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
        return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
    };

    const getProgressPercent = (item: DownloadItem) => {
        if (item.status === 'completed') return 100;
        if (!item.total_bytes || item.total_bytes <= 0) return 0;
        return Math.min(100, Math.round((item.downloaded_bytes / item.total_bytes) * 100));
    };

    const getETA = (item: DownloadItem) => {
        if (item.status === 'completed') return isRTL.value ? 'مكتمل' : 'Completed';
        if (item.status === 'paused') return isRTL.value ? 'متوقف' : 'Paused';
        if (item.status === 'queued') return isRTL.value ? 'في الانتظار' : 'In Queue';
        if (item.speed_bytes_sec <= 0) return isRTL.value ? 'جاري الحساب...' : 'Calculating...';

        const remainingBytes = Math.max(0, item.total_bytes - item.downloaded_bytes);
        const seconds = Math.ceil(remainingBytes / item.speed_bytes_sec);

        if (seconds < 60) return `${seconds}s`;
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}m ${secs}s`;
    };

    const fetchDownloads = async () => {
        try {
            const res = await fetch('/api/downloads/list');
            if (res.ok) {
                const data = await res.json();
                downloads.value = data;
            }
        } catch (e) {}
    };

    const inspectUrl = async (url: string, type?: string): Promise<DownloaderInspection | null> => {
        try {
            const res = await fetch('/api/downloads/inspect', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
                body: JSON.stringify({ url, type }),
            });
            if (res.ok) {
                return await res.json();
            }
        } catch (e) {
            console.error('Failed to inspect download url:', e);
        }
        return null;
    };

    const getSettings = async () => {
        try {
            const res = await fetch('/api/downloads/settings');
            if (res.ok) {
                return await res.json();
            }
        } catch (e) {}
        return null;
    };

    const saveSettings = async (settings: any) => {
        try {
            const res = await fetch('/api/downloads/settings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
                body: JSON.stringify(settings),
            });
            return res.ok;
        } catch (e) {
            return false;
        }
    };

    const processNextChunk = async () => {
        if (activeDownloads.value.length === 0) return;

        try {
            const res = await fetch('/api/downloads/process-batch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.downloads) {
                    downloads.value = data.downloads;
                }
            }
        } catch (e) {}
    };

    const startBackgroundWorker = () => {
        if (isWorkerRunning.value) return;
        isWorkerRunning.value = true;
        fetchDownloads();

        workerInterval = setInterval(() => {
            if (activeDownloads.value.length > 0) {
                processNextChunk();
            }
        }, 1200);
    };

    const addDownload = async (
        titleOrPayload: string | {
            title: string;
            media_type?: 'movie' | 'series' | 'subtitle';
            source_url?: string;
            destination_path?: string;
            destination_folder?: string;
            download_type?: 'direct' | 'torrent';
            selected_files?: any[];
            torrent_files?: any[];
            info_hash?: string;
        },
        legacyMediaType: 'movie' | 'series' | 'subtitle' = 'movie',
        legacySourceUrl?: string,
        legacyDestPath?: string
    ) => {
        try {
            let payload: any = {};
            if (typeof titleOrPayload === 'string') {
                payload = {
                    title: titleOrPayload,
                    media_type: legacyMediaType,
                    source_url: legacySourceUrl,
                    destination_path: legacyDestPath,
                };
            } else {
                payload = { ...titleOrPayload };
            }

            const res = await fetch('/api/downloads', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
                body: JSON.stringify(payload),
            });
            if (res.ok) {
                const newItem = await res.json();
                downloads.value.unshift(newItem);
                return newItem;
            }
        } catch (e) {}
        return null;
    };

    const pauseDownload = async (id: number) => {
        const item = downloads.value.find(d => d.id === id);
        if (item) {
            item.status = 'paused';
            item.speed_bytes_sec = 0;
        }
        await fetch(`/api/downloads/${id}/pause`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });
    };

    const resumeDownload = async (id: number) => {
        const item = downloads.value.find(d => d.id === id);
        if (item) {
            item.status = 'downloading';
        }
        await fetch(`/api/downloads/${id}/resume`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });
    };

    const retryDownload = async (id: number) => {
        const item = downloads.value.find(d => d.id === id);
        if (item) {
            item.downloaded_bytes = 0;
            item.status = 'downloading';
        }
        await fetch(`/api/downloads/${id}/retry`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });
    };

    const deleteDownload = async (id: number) => {
        downloads.value = downloads.value.filter(d => d.id !== id);
        await fetch(`/api/downloads/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });
    };

    return {
        downloads,
        activeDownloads,
        completedDownloads,
        totalSpeedDownFormatted,
        totalSpeedUpFormatted,
        formatBytes,
        getProgressPercent,
        getETA,
        fetchDownloads,
        inspectUrl,
        getSettings,
        saveSettings,
        startBackgroundWorker,
        addDownload,
        pauseDownload,
        resumeDownload,
        retryDownload,
        deleteDownload,
    };
}
