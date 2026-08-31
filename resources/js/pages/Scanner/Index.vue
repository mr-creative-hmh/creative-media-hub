<script setup lang="ts">
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useScanner } from '@/composables/useScanner';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    ScanLine, FolderPlus, Play, Pause, XCircle, RotateCcw,
    CheckCircle2, AlertCircle, FileVideo, HardDrive, Terminal,
    Layers, Cpu, RefreshCw, Trash2, Folder, Film, Tv, Sparkles,
    Check, Filter, Clock, Info, ShieldAlert, ArrowRight, Image as ImageIcon
} from 'lucide-vue-next';

const props = defineProps<{
    directories: Array<{ id?: string; path: string; type: string; count?: number }>;
    scanStatus: any;
    stats: {
        total_movies: number;
        total_series: number;
        total_episodes: number;
        total_subtitles: number;
        storage_size_formatted: string;
    };
}>();

const { t, isRTL } = useI18n();
const {
    scanStatus,
    isScanning,
    isPaused,
    pauseScan,
    resumeScan,
    cancelScan,
    runBackgroundWorker,
    fetchStatus
} = useScanner();

const monitoredDirs = ref([...props.directories]);
const newDirPath = ref('');
const newDirType = ref('mixed');
const isAddingDir = ref(false);
const isBatchEnriching = ref(false);
const toastMessage = ref('');
const terminalFilter = ref<'all' | 'success' | 'info' | 'error'>('all');

const filteredLogs = computed(() => {
    const logs = scanStatus.value.logs || [];
    if (terminalFilter.value === 'all') return logs;
    return logs.filter((l: any) => l.level === terminalFilter.value);
});

const addDirectory = async () => {
    if (!newDirPath.value.trim()) return;
    isAddingDir.value = true;
    try {
        const res = await fetch('/api/scanner/directories', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                path: newDirPath.value.trim(),
                type: newDirType.value,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            monitoredDirs.value = data.directories;
            newDirPath.value = '';
            toastMessage.value = isRTL.value ? 'تم إضافة المجلد بنجاح!' : 'Directory added successfully!';
        }
    } finally {
        isAddingDir.value = false;
    }
};

const removeDirectory = async (idxOrDir: number | any) => {
    try {
        const index = typeof idxOrDir === 'number' ? idxOrDir : monitoredDirs.value.indexOf(idxOrDir);
        const res = await fetch(`/api/scanner/directories/${index}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });
        if (res.ok) {
            const data = await res.json();
            monitoredDirs.value = data.directories || [];
            toastMessage.value = isRTL.value ? 'تم إزالة المجلد من المراقبة.' : 'Directory removed.';
        }
    } catch (e) {}
};

const startScan = async () => {
    if (monitoredDirs.value.length === 0) {
        toastMessage.value = isRTL.value ? 'يرجى إضافة مجلد واحد على الأقل للفحص.' : 'Please add at least one folder to scan.';
        return;
    }
    try {
        const res = await fetch('/api/scanner/start', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                directories: monitoredDirs.value,
            }),
        });
        const data = await res.json();
        scanStatus.value = data.status;
        runBackgroundWorker();
    } catch (e) {}
};

const scanSingleFolder = async (dir: { path: string; type: string }) => {
    try {
        const res = await fetch('/api/scanner/scan-folder', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                path: dir.path,
                type: dir.type,
            }),
        });
        const data = await res.json();
        scanStatus.value = data.status;
        runBackgroundWorker();
    } catch (e) {}
};

const enrichMissingPosters = async () => {
    isBatchEnriching.value = true;
    try {
        const res = await fetch('/api/library/enrich-missing', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });
        if (res.ok) {
            const data = await res.json();
            toastMessage.value = isRTL.value
                ? `تم تحديث وتحميل بيانات ${data.result?.total || 0} عنصر بنجاح!`
                : `Successfully downloaded posters & metadata for ${data.result?.total || 0} items!`;
            setTimeout(() => { router.reload(); }, 1200);
        }
    } finally {
        isBatchEnriching.value = false;
    }
};

watch(() => scanStatus.value.logs?.length, async () => {
    await nextTick();
    const term = document.getElementById('terminal-feed');
    if (term) term.scrollTop = term.scrollHeight;
});

onMounted(() => {
    fetchStatus();
});
</script>

<template>
    <Head :title="t('scanner_view.title')" />

    <AppLayout v-slot="{ play }">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                        <ScanLine class="w-5 h-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                            {{ t('scanner_view.title') }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                            {{ t('scanner_view.subtitle') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="enrichMissingPosters"
                        :disabled="isBatchEnriching"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10 text-xs font-bold transition-all cursor-pointer"
                    >
                        <RefreshCw v-if="isBatchEnriching" class="w-4 h-4 animate-spin text-cyan-400" />
                        <ImageIcon v-else class="w-4 h-4 text-cyan-400" />
                        <span>{{ isRTL ? 'جلب الأغلفة الناقصة' : 'Fetch Missing Posters' }}</span>
                    </button>

                    <button
                        v-if="!isScanning"
                        @click="startScan"
                        class="flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/25 active:scale-95 transition-all cursor-pointer"
                    >
                        <Play class="w-4 h-4 fill-current" />
                        <span>{{ isRTL ? 'بدء فحص كافة المجلدات' : 'Start Full Library Scan' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Toast Notice -->
        <div v-if="toastMessage" class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 shrink-0" />
                <span>{{ toastMessage }}</span>
            </div>
            <button @click="toastMessage = ''" class="cursor-pointer text-emerald-400 hover:text-emerald-300">
                <XCircle class="w-4 h-4" />
            </button>
        </div>

        <!-- 1. Stats Row (Creative-FileFlow Architecture) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            <div class="glass-panel p-4 rounded-2xl border border-white/10">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ isRTL ? 'الأفلام المفهرسة' : 'Indexed Movies' }}</span>
                    <Film class="w-4 h-4 text-cyan-400" />
                </div>
                <div class="text-2xl font-black text-white mt-2">{{ stats.total_movies }}</div>
            </div>

            <div class="glass-panel p-4 rounded-2xl border border-white/10">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ isRTL ? 'المسلسلات' : 'TV Series' }}</span>
                    <Tv class="w-4 h-4 text-indigo-400" />
                </div>
                <div class="text-2xl font-black text-white mt-2">{{ stats.total_series }}</div>
            </div>

            <div class="glass-panel p-4 rounded-2xl border border-white/10">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ isRTL ? 'الحلقات المكتشفة' : 'Total Episodes' }}</span>
                    <FileVideo class="w-4 h-4 text-emerald-400" />
                </div>
                <div class="text-2xl font-black text-white mt-2">{{ stats.total_episodes }}</div>
            </div>

            <div class="glass-panel p-4 rounded-2xl border border-white/10">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ isRTL ? 'الحجم الكلي' : 'Indexed Size' }}</span>
                    <HardDrive class="w-4 h-4 text-amber-400" />
                </div>
                <div class="text-2xl font-black text-white mt-2">{{ stats.storage_size_formatted || '0 GB' }}</div>
            </div>
        </div>

        <!-- 2. Active Scan Progress & Control Bar -->
        <div v-if="isScanning || scanStatus.status === 'running' || scanStatus.status === 'paused'" class="glass-panel rounded-3xl p-6 border border-cyan-500/30 mb-8 space-y-4 shadow-lg shadow-cyan-500/5 relative overflow-hidden">
            <div class="ambient-glow bg-cyan-500/20 w-80 h-80 -top-20 -right-20 pointer-events-none"></div>

            <div class="flex items-center justify-between flex-wrap gap-4 relative z-10">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                        <RefreshCw class="w-5 h-5 animate-spin" />
                    </div>
                    <div>
                        <h3 class="font-extrabold text-sm text-white flex items-center gap-2">
                            <span>{{ isRTL ? 'جاري فهرسة المكتبة وتحميل الأغلفة...' : 'Virtual Scanner Active & Indexing...' }}</span>
                            <span class="cinema-badge bg-cyan-500/20 text-cyan-300 border-cyan-500/30 text-[10px]">
                                {{ scanStatus.processed_files }} / {{ scanStatus.total_files }}
                            </span>
                        </h3>
                        <p class="text-xs text-slate-400 truncate max-w-lg mt-0.5">
                            {{ scanStatus.current_file || (isRTL ? 'جاري قراءة الملفات...' : 'Processing media streams...') }}
                        </p>
                    </div>
                </div>

                <!-- Control Buttons -->
                <div class="flex items-center gap-2">
                    <button
                        v-if="isScanning"
                        @click="pauseScan"
                        class="px-3.5 py-1.5 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs font-bold flex items-center gap-1.5 cursor-pointer hover:bg-amber-500/30"
                    >
                        <Pause class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                    </button>
                    <button
                        v-if="isPaused"
                        @click="resumeScan"
                        class="px-3.5 py-1.5 rounded-xl bg-cyan-500 text-slate-950 font-bold text-xs flex items-center gap-1.5 cursor-pointer hover:bg-cyan-400"
                    >
                        <Play class="w-3.5 h-3.5 fill-current" />
                        <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                    </button>
                    <button
                        @click="cancelScan"
                        class="px-3.5 py-1.5 rounded-xl bg-rose-500/20 text-rose-300 border border-rose-500/30 text-xs font-bold flex items-center gap-1.5 cursor-pointer hover:bg-rose-500/30"
                    >
                        <XCircle class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'إلغاء' : 'Cancel' }}</span>
                    </button>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="space-y-1 relative z-10">
                <div class="w-full h-3 rounded-full bg-white/5 border border-white/10 overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500 transition-all duration-300 relative"
                        :style="{ width: `${scanStatus.progress_percent || 0}%` }"
                    ></div>
                </div>
                <div class="flex justify-between text-[11px] font-bold text-slate-400">
                    <span>{{ isRTL ? 'نسبة الإنجاز' : 'Progress' }}: {{ scanStatus.progress_percent || 0 }}%</span>
                    <span>{{ scanStatus.processed_files }} / {{ scanStatus.total_files }} {{ isRTL ? 'ملف' : 'files' }}</span>
                </div>
            </div>
        </div>

        <!-- 3. Monitored Folders Grid (Creative-FileFlow Style) -->
        <div class="space-y-4 mb-8">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-base text-white flex items-center gap-2">
                    <Folder class="w-4 h-4 text-cyan-400" />
                    <span>{{ isRTL ? 'المجلدات المراقبة والمفهرسة' : 'Monitored Library Directories' }}</span>
                </h3>
            </div>

            <!-- Folders List or Empty State -->
            <div v-if="monitoredDirs.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                    v-for="(dir, idx) in monitoredDirs"
                    :key="dir.path"
                    class="glass-panel p-5 rounded-2xl border border-white/10 hover:border-cyan-500/40 transition-all flex flex-col justify-between space-y-4 group"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center shrink-0 mt-0.5">
                                <Folder class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-bold text-xs text-white truncate font-mono" :title="dir.path">
                                    {{ dir.path }}
                                </h4>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="cinema-badge bg-cyan-500/20 text-cyan-300 border-cyan-500/30 text-[10px]">
                                        {{ dir.type === 'movies' ? (isRTL ? 'أفلام' : 'Movies') : dir.type === 'series' ? (isRTL ? 'مسلسلات' : 'TV Series') : (isRTL ? 'مختلط' : 'Mixed') }}
                                    </span>
                                    <span class="text-[11px] text-slate-400">
                                        {{ isRTL ? 'فهرسة مستمرة' : 'Active Index' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <button
                            @click="removeDirectory(idx)"
                            class="text-slate-500 hover:text-rose-400 transition-colors p-1 cursor-pointer"
                            title="Remove folder"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="pt-2 border-t border-white/5 flex items-center justify-between">
                        <span class="text-[11px] text-slate-400 flex items-center gap-1">
                            <CheckCircle2 class="w-3.5 h-3.5 text-emerald-400" />
                            <span>{{ isRTL ? 'جاهز للفحص' : 'Ready to scan' }}</span>
                        </span>
                        <button
                            @click="scanSingleFolder(dir)"
                            :disabled="isScanning"
                            class="px-3 py-1.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                        >
                            <Play class="w-3 h-3 fill-current" />
                            <span>{{ isRTL ? 'فحص هذا المجلد' : 'Scan Folder' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Clean Empty State when no folders added -->
            <div v-else class="glass-panel rounded-3xl p-10 text-center text-slate-400 border border-white/10 space-y-2">
                <Folder class="w-8 h-8 text-cyan-500/50 mx-auto" />
                <p class="font-bold text-white text-sm">
                    {{ isRTL ? 'لم تقم بإضافة أي مجلدات للمكتبة بعد' : 'No media folders added yet' }}
                </p>
                <p class="text-xs text-slate-500 max-w-md mx-auto">
                    {{ isRTL ? 'أدخل مسار مجلد الأفلام أو المسلسلات أدناه للبدء بالفهرسة والمسح التلقائي.' : 'Enter your movies or TV shows folder path below to start scanning and indexing your personal library.' }}
                </p>
            </div>

            <!-- Add Folder Form -->
            <form @submit.prevent="addDirectory" class="glass-panel p-4 rounded-2xl border border-white/10 flex flex-col sm:flex-row items-center gap-3">
                <div class="relative flex-1 w-full">
                    <input
                        v-model="newDirPath"
                        type="text"
                        :placeholder="isRTL ? 'مثال: D:/Movies أو /Volumes/Media/TV' : 'e.g. D:/Movies or C:/Users/hasan/Videos'"
                        class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                    />
                    <FolderPlus class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                </div>
                <select
                    v-model="newDirType"
                    class="w-full sm:w-36 px-3 py-2.5 rounded-xl bg-[#0E121E] border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-500"
                >
                    <option value="mixed">{{ isRTL ? 'مختلط (Mixed)' : 'Mixed Content' }}</option>
                    <option value="movies">{{ isRTL ? 'أفلام فقط' : 'Movies Only' }}</option>
                    <option value="series">{{ isRTL ? 'مسلسلات فقط' : 'Series Only' }}</option>
                </select>
                <button
                    type="submit"
                    :disabled="isAddingDir || !newDirPath.trim()"
                    class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs flex items-center justify-center gap-1.5 active:scale-95 transition-all cursor-pointer shrink-0"
                >
                    <FolderPlus class="w-4 h-4" />
                    <span>{{ isRTL ? 'إضافة مجلد' : 'Add Folder' }}</span>
                </button>
            </form>
        </div>

        <!-- 4. Interactive Live Diagnostic Terminal Feed (Creative-FileFlow Style) -->
        <div class="glass-panel rounded-3xl p-6 border border-white/10 space-y-4 mb-8">
            <div class="flex items-center justify-between flex-wrap gap-4 pb-3 border-b border-white/10">
                <div class="flex items-center gap-2.5">
                    <Terminal class="w-5 h-5 text-cyan-400" />
                    <div>
                        <h3 class="font-bold text-sm text-white">
                            {{ isRTL ? 'سجل العمليات المباشر (Scanner Live Terminal)' : 'Scanner Live Terminal' }}
                        </h3>
                        <p class="text-xs text-slate-400">
                            {{ isRTL ? 'تتبع فوري لاكتشاف الملفات ومطابقة الأغلفة والترجمات' : 'Real-time feed of file discovery, metadata matching, and subtitle linking.' }}
                        </p>
                    </div>
                </div>

                <!-- Log Filter Buttons -->
                <div class="flex items-center gap-1.5 bg-white/5 p-1 rounded-xl border border-white/10">
                    <button
                        v-for="flt in [
                            { id: 'all', label: isRTL ? 'الكل' : 'All' },
                            { id: 'success', label: isRTL ? 'نجاح' : 'Success' },
                            { id: 'info', label: isRTL ? 'معلومات' : 'Info' },
                            { id: 'error', label: isRTL ? 'أخطاء' : 'Errors' },
                        ]"
                        :key="flt.id"
                        @click="terminalFilter = flt.id as any"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer"
                        :class="terminalFilter === flt.id ? 'bg-cyan-500 text-slate-950 font-black' : 'text-slate-400 hover:text-white'"
                    >
                        {{ flt.label }}
                    </button>
                </div>
            </div>

            <!-- Terminal Window -->
            <div
                id="terminal-feed"
                class="bg-[#05070B] rounded-2xl p-4 font-mono text-[11px] h-56 overflow-y-auto space-y-1.5 border border-white/5 scroll-smooth"
            >
                <div v-if="filteredLogs.length === 0" class="text-slate-500 py-6 text-center">
                    {{ isRTL ? 'لا توجد سجلات حالياً. ابدأ الفحص لعرض العمليات.' : 'No terminal events logged yet. Start a scan to view real-time operations.' }}
                </div>

                <div
                    v-for="(log, lIdx) in filteredLogs"
                    :key="lIdx"
                    class="flex items-start gap-2.5 leading-relaxed"
                >
                    <span class="text-slate-500 shrink-0 select-none">[{{ log.time }}]</span>
                    <span
                        class="font-bold shrink-0 select-none uppercase text-[10px] px-1 rounded"
                        :class="{
                            'bg-emerald-500/20 text-emerald-400': log.level === 'success',
                            'bg-cyan-500/20 text-cyan-400': log.level === 'info',
                            'bg-amber-500/20 text-amber-400': log.level === 'warning',
                            'bg-rose-500/20 text-rose-400': log.level === 'error',
                        }"
                    >
                        {{ log.level }}
                    </span>
                    <span
                        class="break-all"
                        :class="{
                            'text-emerald-300': log.level === 'success',
                            'text-slate-300': log.level === 'info',
                            'text-amber-300': log.level === 'warning',
                            'text-rose-300': log.level === 'error',
                        }"
                    >
                        {{ log.message }}
                    </span>
                </div>
            </div>
        </div>

        <!-- 5. Recent Scanned Items Grid -->
        <div v-if="scanStatus.scanned_items?.length" class="space-y-4">
            <h3 class="font-bold text-sm text-white flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-emerald-400" />
                <span>{{ isRTL ? 'آخر الملفات المفهرسة حديثاً' : 'Recently Indexed Media' }}</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div
                    v-for="item in scanStatus.scanned_items"
                    :key="item.file_path"
                    class="glass-panel p-3.5 rounded-2xl border border-white/10 flex items-center justify-between gap-3"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex items-center gap-1.5">
                            <span class="cinema-badge bg-cyan-500/20 text-cyan-300 border-cyan-500/30 text-[9px]">
                                {{ item.type === 'series' ? 'TV' : 'MOVIE' }}
                            </span>
                            <span class="cinema-badge bg-white/10 text-slate-300 border-white/10 text-[9px]">
                                {{ item.resolution }}
                            </span>
                            <span v-if="item.subtitles_count > 0" class="cinema-badge bg-emerald-500/20 text-emerald-300 border-emerald-500/30 text-[9px]">
                                +{{ item.subtitles_count }} SUB
                            </span>
                        </div>
                        <h4 class="font-bold text-xs text-white truncate max-w-xs">{{ item.title }}</h4>
                    </div>
                    <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
