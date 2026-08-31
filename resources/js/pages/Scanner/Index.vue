<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    ScanLine, FolderPlus, Play, Pause, Square, Trash2,
    CheckCircle2, AlertTriangle, RefreshCw, Film, Tv,
    HardDrive, Layers, ArrowRight, ArrowLeft
} from 'lucide-vue-next';

const props = defineProps<{
    directories: Array<{
        id: string;
        path: string;
        type: 'movies' | 'series' | 'mixed';
        auto_scan: boolean;
    }>;
    initialScanStatus: any;
    stats: {
        total_movies: number;
        total_series: number;
        total_episodes: number;
    };
}>();

const { t, isRTL } = useI18n();

const monitoredDirs = ref([...props.directories]);
const scanStatus = ref({ ...props.initialScanStatus });
const isProcessing = ref(false);
let isLoopRunning = false;

// New folder modal state
const showAddModal = ref(false);
const newPath = ref('C:/Media/Movies');
const newType = ref<'movies' | 'series' | 'mixed'>('movies');

// Clear Demo state
const isClearingDemo = ref(false);
const clearSuccessMessage = ref('');

const fetchStatus = async () => {
    try {
        const res = await fetch('/api/scanner/status');
        if (res.ok) {
            scanStatus.value = await res.json();
        }
    } catch (e) {}
};

const runBatchLoop = async () => {
    if (isLoopRunning) return;
    isLoopRunning = true;
    isProcessing.value = true;

    try {
        while (isLoopRunning && scanStatus.value.status === 'running') {
            const res = await fetch('/api/scanner/process-batch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
            });

            if (res.ok) {
                const data = await res.json();
                scanStatus.value = data.status;
                if (!data.has_more || scanStatus.value.status !== 'running') {
                    break;
                }
            } else {
                break;
            }
            // Small micro-delay for smooth rendering
            await new Promise((r) => setTimeout(r, 100));
        }
    } finally {
        isLoopRunning = false;
        isProcessing.value = false;
    }
};

const handleStartScan = async () => {
    try {
        scanStatus.value.status = 'running';
        const res = await fetch('/api/scanner/start', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({ directories: monitoredDirs.value }),
        });

        if (res.ok) {
            const data = await res.json();
            scanStatus.value = data.status;
            if (scanStatus.value.status === 'running' && (scanStatus.value.total_files > 0)) {
                runBatchLoop();
            }
        }
    } catch (e) {
        scanStatus.value.status = 'idle';
    }
};

const handlePauseScan = async () => {
    isLoopRunning = false;
    await fetch('/api/scanner/pause', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '' }
    });
    scanStatus.value.status = 'paused';
};

const handleResumeScan = async () => {
    await fetch('/api/scanner/resume', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '' }
    });
    scanStatus.value.status = 'running';
    runBatchLoop();
};

const handleCancelScan = async () => {
    isLoopRunning = false;
    await fetch('/api/scanner/cancel', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '' }
    });
    scanStatus.value.status = 'cancelled';
};

const handleAddDirectory = async () => {
    if (!newPath.value) return;
    try {
        const res = await fetch('/api/scanner/directories', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                path: newPath.value,
                type: newType.value,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            monitoredDirs.value = data.directories;
            showAddModal.value = false;
        }
    } finally {
        showAddModal.value = false;
    }
};

const handleRemoveDirectory = async (id: string) => {
    const res = await fetch('/api/scanner/directories', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
        },
        body: JSON.stringify({ id }),
    });

    if (res.ok) {
        const data = await res.json();
        monitoredDirs.value = data.directories;
    }
};

const handleClearDemoCatalog = async () => {
    if (!confirm(isRTL.value ? 'هل أنت متأكد من رغبتك في مسح كافة الوسائط التجريبية للبدء بفهرسة مجلداتك من الصفر؟' : 'Are you sure you want to clear all sample demo media and start with a fresh clean library?')) {
        return;
    }

    isClearingDemo.value = true;
    try {
        const res = await fetch('/api/library/clear-demo', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '' },
        });

        if (res.ok) {
            const data = await res.json();
            clearSuccessMessage.value = data.message;
            setTimeout(() => {
                router.reload();
            }, 1000);
        }
    } finally {
        isClearingDemo.value = false;
    }
};

onMounted(() => {
    if (scanStatus.value.status === 'running') {
        runBatchLoop();
    }
});

onUnmounted(() => {
    isLoopRunning = false;
});
</script>

<template>
    <Head :title="t('scanner.title')" />

    <AppLayout v-slot="{ play }">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                        <ScanLine class="w-5 h-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white">
                            {{ t('scanner.title') }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-0.5">
                            {{ t('scanner.subtitle') }}
                        </p>
                    </div>
                </div>

                <!-- Add Directory Button -->
                <button
                    @click="showAddModal = true"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-extrabold text-xs shadow-lg shadow-cyan-500/20 active:scale-95 transition-all cursor-pointer"
                >
                    <FolderPlus class="w-4 h-4" />
                    <span>{{ t('scanner.add_directory') }}</span>
                </button>
            </div>
        </div>

        <!-- 1. Background Scanner Status & Live Control Center -->
        <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-white/10 mb-8 space-y-6 shadow-sm">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                        {{ t('scanner.job_status') }}:
                    </span>
                    <span
                        v-if="scanStatus.status === 'running'"
                        class="cinema-badge bg-cyan-500/20 text-cyan-600 dark:text-cyan-300 border border-cyan-500/40 animate-pulse"
                    >
                        <RefreshCw class="w-3.5 h-3.5 animate-spin" />
                        {{ t('scanner.status_running') }}
                    </span>
                    <span
                        v-else-if="scanStatus.status === 'paused'"
                        class="cinema-badge bg-amber-500/20 text-amber-600 dark:text-amber-300 border border-amber-500/40"
                    >
                        <Pause class="w-3.5 h-3.5" />
                        {{ t('scanner.status_paused') }}
                    </span>
                    <span
                        v-else-if="scanStatus.status === 'completed'"
                        class="cinema-badge bg-emerald-500/20 text-emerald-600 dark:text-emerald-300 border border-emerald-500/40"
                    >
                        <CheckCircle2 class="w-3.5 h-3.5" />
                        {{ t('scanner.status_completed') }}
                    </span>
                    <span
                        v-else
                        class="cinema-badge bg-slate-500/20 text-slate-600 dark:text-slate-400 border border-slate-500/40"
                    >
                        {{ t('scanner.status_idle') }}
                    </span>
                </div>

                <!-- Action Controls: Start / Pause / Resume / Cancel -->
                <div class="flex items-center gap-3">
                    <button
                        v-if="scanStatus.status === 'idle' || scanStatus.status === 'completed' || scanStatus.status === 'cancelled'"
                        @click="handleStartScan"
                        class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-extrabold text-xs shadow-md shadow-cyan-500/30 active:scale-95 transition-all cursor-pointer"
                    >
                        <Play class="w-3.5 h-3.5 fill-current" />
                        <span>{{ t('scanner.start_scan') }}</span>
                    </button>

                    <button
                        v-if="scanStatus.status === 'running'"
                        @click="handlePauseScan"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-600 dark:text-amber-300 border border-amber-500/40 font-bold text-xs active:scale-95 transition-all cursor-pointer"
                    >
                        <Pause class="w-3.5 h-3.5" />
                        <span>{{ t('scanner.pause_scan') }}</span>
                    </button>

                    <button
                        v-if="scanStatus.status === 'paused'"
                        @click="handleResumeScan"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-600 dark:text-emerald-300 border border-emerald-500/40 font-bold text-xs active:scale-95 transition-all cursor-pointer"
                    >
                        <Play class="w-3.5 h-3.5 fill-current" />
                        <span>{{ t('scanner.resume_scan') }}</span>
                    </button>

                    <button
                        v-if="scanStatus.status === 'running' || scanStatus.status === 'paused'"
                        @click="handleCancelScan"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-600 dark:text-rose-300 border border-rose-500/40 font-bold text-xs active:scale-95 transition-all cursor-pointer"
                    >
                        <Square class="w-3.5 h-3.5 fill-current" />
                        <span>{{ t('scanner.cancel_scan') }}</span>
                    </button>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300">
                    <span class="truncate max-w-lg font-mono text-[11px] text-slate-600 dark:text-slate-400">
                        {{ scanStatus.current_file || (isRTL ? 'جاهز للفحص الافتراضي' : 'Ready for virtual indexing') }}
                    </span>
                    <span>{{ scanStatus.progress_percent || 0 }}% ({{ scanStatus.processed_files || 0 }}/{{ scanStatus.total_files || 0 }})</span>
                </div>
                <div class="w-full h-3 rounded-full bg-slate-200 dark:bg-white/10 overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500 rounded-full transition-all duration-300"
                        :style="{ width: `${scanStatus.progress_percent || 0}%` }"
                    ></div>
                </div>
            </div>

            <!-- Live Streaming Feed of Indexed Items -->
            <div v-if="scanStatus.scanned_items && scanStatus.scanned_items.length > 0" class="pt-2 border-t border-slate-200 dark:border-white/10 space-y-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    {{ isRTL ? 'أحدث العناصر المفهرسة في المكتبة:' : 'Recently Indexed Media Items:' }}
                </span>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    <div
                        v-for="(item, idx) in scanStatus.scanned_items"
                        :key="`scanned-${idx}`"
                        class="p-2.5 rounded-xl bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 flex items-center justify-between text-xs"
                    >
                        <div class="truncate mr-2">
                            <p class="font-bold text-slate-900 dark:text-white truncate">{{ item.title }}</p>
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 uppercase">{{ item.type }}</span>
                        </div>
                        <span class="cinema-badge bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 border border-cyan-500/20 text-[10px] shrink-0">
                            {{ item.resolution || '1080p' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Monitored Media Folders List -->
        <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-white/10 mb-8 space-y-4 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-white/10">
                <h3 class="font-bold text-base text-slate-900 dark:text-white">
                    {{ t('scanner.monitored_directories') }}
                </h3>
                <span class="cinema-badge bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 border border-cyan-500/30">
                    {{ monitoredDirs.length }} {{ isRTL ? 'مجلدات' : 'folders' }}
                </span>
            </div>

            <div v-if="monitoredDirs.length > 0" class="divide-y divide-slate-100 dark:divide-white/5">
                <div
                    v-for="dir in monitoredDirs"
                    :key="dir.id"
                    class="py-3.5 flex items-center justify-between flex-wrap gap-4 hover:bg-slate-50 dark:hover:bg-white/[0.02] px-2 rounded-xl transition-colors"
                >
                    <div class="flex items-center gap-3">
                        <HardDrive class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                        <div>
                            <p class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200">{{ dir.path }}</p>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ dir.type === 'movies' ? t('scanner.movies_type') : (dir.type === 'series' ? t('scanner.series_type') : t('scanner.mixed_type')) }}
                            </span>
                        </div>
                    </div>

                    <button
                        @click="handleRemoveDirectory(dir.id)"
                        class="p-2 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30 transition-colors cursor-pointer"
                        :title="t('common.remove')"
                    >
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <div v-else class="text-center py-8 text-slate-500 dark:text-slate-400 text-xs">
                {{ isRTL ? 'لا توجد مجلدات مضافة حالياً. انقر على إضافة مجلد جديد.' : 'No monitored folders added yet. Click "Add Media Folder" above.' }}
            </div>
        </div>

        <!-- 3. Clean Slate & Reset Demo Catalog -->
        <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-rose-500/30 bg-rose-500/5 dark:bg-rose-950/10 space-y-4 shadow-sm">
            <div class="flex items-start justify-between flex-wrap gap-4">
                <div class="flex items-start gap-3.5 max-w-2xl">
                    <div class="w-10 h-10 rounded-2xl bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30 flex items-center justify-center shrink-0 mt-0.5">
                        <AlertTriangle class="w-5 h-5" />
                    </div>
                    <div>
                        <h4 class="font-extrabold text-sm text-rose-700 dark:text-rose-300">
                            {{ t('scanner.clear_demo_title') }}
                        </h4>
                        <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 leading-relaxed">
                            {{ t('scanner.clear_demo_desc') }}
                        </p>
                    </div>
                </div>

                <button
                    @click="handleClearDemoCatalog"
                    :disabled="isClearingDemo"
                    class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-extrabold text-xs shadow-lg shadow-rose-600/30 active:scale-95 transition-all cursor-pointer"
                >
                    <span v-if="!isClearingDemo">{{ t('scanner.clear_demo_button') }}</span>
                    <span v-else>{{ isRTL ? 'جاري المسح...' : 'Clearing...' }}</span>
                </button>
            </div>

            <div v-if="clearSuccessMessage" class="p-3 rounded-xl bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 text-xs font-bold">
                {{ clearSuccessMessage }}
            </div>
        </div>

        <!-- Add Directory Modal -->
        <div
            v-if="showAddModal"
            class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4"
            @click.self="showAddModal = false"
        >
            <div class="glass-panel rounded-3xl p-6 sm:p-8 max-w-md w-full border border-slate-200 dark:border-white/15 shadow-2xl space-y-4 bg-white dark:bg-slate-900">
                <h3 class="font-bold text-lg text-slate-900 dark:text-white">
                    {{ t('scanner.add_directory') }}
                </h3>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            {{ t('scanner.folder_path') }}
                        </label>
                        <input
                            type="text"
                            v-model="newPath"
                            placeholder="e.g. C:/Movies or D:/Series"
                            class="w-full h-11 rounded-xl bg-slate-50 dark:bg-white/[0.04] border border-slate-300 dark:border-white/15 px-4 text-sm text-slate-900 dark:text-slate-100 font-mono focus:border-cyan-500 outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            {{ t('scanner.media_type') }}
                        </label>
                        <select
                            v-model="newType"
                            class="w-full h-11 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-white/15 px-3 text-sm text-slate-900 dark:text-slate-100 focus:border-cyan-500 outline-none"
                        >
                            <option value="movies">{{ t('scanner.movies_type') }}</option>
                            <option value="series">{{ t('scanner.series_type') }}</option>
                            <option value="mixed">{{ t('scanner.mixed_type') }}</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3">
                    <button
                        @click="showAddModal = false"
                        class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        @click="handleAddDirectory"
                        class="px-5 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-black cursor-pointer"
                    >
                        {{ t('common.save') }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
