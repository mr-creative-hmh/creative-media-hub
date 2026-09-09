<script setup lang="ts">
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import { useScanner } from '@/composables/useScanner';
import AppLayout from '@/components/layout/AppLayout.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';
import {
    ScanLine, FolderPlus, Play, Pause, XCircle, RotateCcw,
    CheckCircle2, AlertCircle, FileVideo, HardDrive, Terminal,
    Layers, Cpu, RefreshCw, Trash2, Folder, Film, Tv, Sparkles,
    Check, Filter, Clock, Info, ShieldAlert, ShieldCheck, ArrowRight, Image as ImageIcon, MessageSquare
} from 'lucide-vue-next';

const props = defineProps<{
    directories: Array<{ id?: string; path: string; type: string; count?: number }>;
    scanStatus: any;
    stats: {
        total_movies: number;
        total_series: number;
        total_episodes: number;
        total_subtitles: number;
        total_collections?: number;
        storage_size_formatted: string;
    };
}>();

const { t, isRTL } = useI18n();
const {
    scanStatus,
    liveStats,
    isScanning,
    isPaused,
    pauseScan,
    resumeScan,
    cancelScan,
    startFullScan,
    rescanFresh,
    scanFolder,
    clearCatalog,
    runBackgroundWorker,
    fetchStatus
} = useScanner();

const currentStats = computed(() => {
    return liveStats.value || props.stats;
});

const monitoredDirs = ref([...props.directories]);
const newDirPath = ref('');
const newDirType = ref('mixed');
const isAddingDir = ref(false);
const isBatchEnriching = ref(false);
const isClearing = ref(false);
const toast = useToast();
const toastMessage = ref('');
watch(toastMessage, (val: string) => {
    if (val) {
        toast.info(val);
        toastMessage.value = '';
    }
});
const terminalFilter = ref<'all' | 'success' | 'info' | 'error'>('all');

const confirmModal = ref<{
    show: boolean;
    title: string;
    message: string;
    confirmText: string;
    cancelText?: string;
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
            toastMessage.value = isRTL.value ? 'تمت إضافة المجلد بنجاح!' : 'Directory added successfully!';
        }
    } finally {
        isAddingDir.value = false;
    }
};

const promptRemoveDirectory = (idx: number, dir: any) => {
    confirmModal.value = {
        show: true,
        title: isRTL.value ? 'إزالة مجلد مراقب' : 'Remove Monitored Folder',
        message: isRTL.value ? `هل أنت متأكد من إزالة "${dir.path}" من قائمة المجلدات المراقبة؟ (لن يتم حذف الملفات من القرص).` : `Are you sure you want to remove "${dir.path}" from monitored library folders? (Files on disk will NOT be deleted).`,
        confirmText: isRTL.value ? 'إزالة' : 'Remove Folder',
        type: 'danger',
        action: async () => {
            confirmModal.value.show = false;
            try {
                const res = await fetch(`/api/scanner/directories/${idx}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                    },
                });
                if (res.ok) {
                    const data = await res.json();
                    monitoredDirs.value = data.directories || [];
                    toastMessage.value = isRTL.value ? 'تمت إزالة المجلد بنجاح.' : 'Directory removed.';
                }
            } catch (e) {}
        },
    };
};

const startScan = async () => {
    if (monitoredDirs.value.length === 0) {
        toastMessage.value = isRTL.value ? 'يرجى إضافة مجلد واحد على الأقل للمسح.' : 'Please add at least one folder to scan.';
        return;
    }
    await startFullScan(monitoredDirs.value, 'incremental');
    toastMessage.value = isRTL.value ? 'بدأ المسح التدريجي للمكتبة في الخلفية...' : 'Incremental library scan started in background...';
};

const promptRescanIncremental = () => {
    confirmModal.value = {
        show: true,
        title: isRTL.value ? 'إعادة مسح تدريجي للمكتبة' : 'Incremental Library Rescan',
        message: isRTL.value ? 'سيتم فحص المجلدات المراقبة وإضافة الملفات الجديدة فقط (المفهرسة مسبقاً سيتم تخطيها). متابعة؟' : 'This will scan monitored folders and add only new files (previously indexed items will be skipped). Continue?',
        confirmText: isRTL.value ? 'بدء مسح تدريجي' : 'Start Incremental Scan',
        type: 'info',
        action: async () => {
            confirmModal.value.show = false;
            await startFullScan(monitoredDirs.value, 'incremental');
            toastMessage.value = isRTL.value ? 'بدأ المسح التدريجي للمكتبة!' : 'Incremental library scan started!';
        },
    };
};

const promptRescanFresh = () => {
    confirmModal.value = {
        show: true,
        title: isRTL.value ? 'إعادة مسح شاملة ونظيفة' : 'Fresh Full Library Rescan',
        message: isRTL.value ? 'هل أنت متأكد من مسح الفهرس السابق وبدء مسح جديد ونظيف لجميع المجلدات المراقبة؟' : 'Are you sure you want to wipe the previous scan and start a fresh library indexing across all monitored folders?',
        confirmText: isRTL.value ? 'بدء مسح نظيف' : 'Start Fresh Scan',
        type: 'warning',
        action: async () => {
            confirmModal.value.show = false;
            await rescanFresh();
            toastMessage.value = isRTL.value ? 'تم مسح الفهرس القديم وبدأ المسح النظيف!' : 'Previous index wiped. Fresh scan started!';
        },
    };
};

const promptClearCatalog = () => {
    confirmModal.value = {
        show: true,
        title: isRTL.value ? 'مسح قاعدة بيانات المكتبة' : 'Clear Library Catalog & Analytics',
        message: isRTL.value ? 'تحذير: سيتم مسح جميع الأفلام والمسلسلات وسجلات المشاهدة من قاعدة البيانات (لن تُحذف ملفاتك من القرص). هل ترغب بالمتابعة؟' : 'Warning: This will remove all indexed movies, series, watch histories, and analytics metrics from your library database (files on disk will NOT be deleted). Continue?',
        confirmText: isRTL.value ? 'مسح الفهرس' : 'Wipe Catalog',
        type: 'danger',
        action: async () => {
            confirmModal.value.show = false;
            isClearing.value = true;
            try {
                await clearCatalog();
                toastMessage.value = isRTL.value ? 'تم مسح قاعدة بيانات الفهرس بنجاح.' : 'Library catalog & analytics cleared.';
                setTimeout(() => { router.reload(); }, 800);
            } finally {
                isClearing.value = false;
            }
        },
    };
};

const scanSingleFolder = async (dir: { path: string; type: string }) => {
    await scanFolder(dir.path, dir.type, false, 'incremental');
    toastMessage.value = isRTL.value ? `جاري المسح التدريجي: ${dir.path}` : `Incremental scan started for: ${dir.path}`;
};

const promptFreshRescanFolder = (dir: { path: string; type: string }) => {
    confirmModal.value = {
        show: true,
        title: isRTL.value ? 'إعادة مسح نظيفة للمجلد' : 'Fresh Rescan Folder',
        message: isRTL.value ? `سيتم مسح الفهرس السابق لهذا المجلد وإعادة فهرسة كل شيء من الصفر: "${dir.path}". متابعة؟` : `This will wipe previous index for this folder and re-scan everything from scratch: "${dir.path}". Continue?`,
        confirmText: isRTL.value ? 'إعادة مسح نظيف' : 'Fresh Rescan',
        type: 'warning',
        action: async () => {
            confirmModal.value.show = false;
            await scanFolder(dir.path, dir.type, true, 'fresh');
            toastMessage.value = isRTL.value ? `بدأ المسح النظيف للمجلد: ${dir.path}` : `Fresh scan started for: ${dir.path}`;
        },
    };
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
                ? `تم بنجاح جلب بوسترات وبيانات ${data.result?.enriched_count || data.result?.total || 0} عنصر!`
                : `Successfully downloaded posters & metadata for ${data.result?.enriched_count || data.result?.total || 0} items!`;
            setTimeout(() => { router.reload(); }, 1200);
        }
    } finally {
        isBatchEnriching.value = false;
    }
};

const triggerConfirmAction = async () => {
    if (confirmModal.value.action) {
        await confirmModal.value.action();
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

                <div class="flex items-center flex-wrap gap-2.5">
                    <Link
                        href="/subtitles?tab=checker"
                        class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 text-xs font-bold transition-all cursor-pointer"
                    >
                        <ShieldCheck class="w-4 h-4 text-cyan-400" />
                        <span>{{ isRTL ? 'فاحص ومطهر الترجمات' : 'Subtitle Checker' }}</span>
                    </Link>

                    <button
                        @click="enrichMissingPosters"
                        :disabled="isBatchEnriching"
                        class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10 text-xs font-bold transition-all cursor-pointer"
                    >
                        <RefreshCw v-if="isBatchEnriching" class="w-4 h-4 animate-spin text-cyan-400" />
                        <Sparkles v-else class="w-4 h-4 text-cyan-400" />
                        <span>{{ isRTL ? 'تحميل الأغلفة الناقصة تلقائياً' : 'Auto-Download Missing Artwork' }}</span>
                    </button>

                    <button
                        @click="promptRescanFresh"
                        :disabled="isScanning"
                        class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-amber-300 border border-white/10 hover:border-amber-500/40 text-xs font-bold transition-all cursor-pointer"
                    >
                        <RotateCcw class="w-4 h-4" />
                        <span>{{ isRTL ? 'إعادة فحص شاملة للمكتبة' : 'Fresh Full Rescan' }}</span>
                    </button>

                    <button
                        @click="promptRescanIncremental"
                        :disabled="isScanning"
                        class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-cyan-300 border border-white/10 hover:border-cyan-500/40 text-xs font-bold transition-all cursor-pointer"
                    >
                        <RotateCcw class="w-4 h-4" />
                        <span>{{ isRTL ? 'إعادة فحص تدريجي' : 'Incremental Rescan' }}</span>
                    </button>

                    <button
                        @click="promptClearCatalog"
                        :disabled="isClearing || isScanning"
                        class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 text-xs font-bold transition-all cursor-pointer"
                    >
                        <Trash2 class="w-4 h-4" />
                        <span>{{ isRTL ? 'مسح فهارس المكتبة والإحصائيات' : 'Clear Library Catalog' }}</span>
                    </button>

                    <button
                        @click="startScan"
                        :disabled="isScanning"
                        class="flex items-center gap-2 px-5 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-black shadow-lg shadow-cyan-500/20 active:scale-95 transition-all cursor-pointer"
                    >
                        <Play class="w-4 h-4 fill-current" />
                        <span>{{ isRTL ? 'بدء فحص تدريجي للمجلدات' : 'Start Incremental Scan' }}</span>
                    </button>
                </div>
            </div>


        </div>

        <!-- 1. Stats Bento Row (Live Reactive Stats + 5th Collections Card) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
            <div class="glass-panel p-5 rounded-2xl border border-white/10 flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center shrink-0">
                    <Film class="w-5 h-5" />
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">{{ isRTL ? 'الأفلام المفهرسة' : 'Indexed Movies' }}</span>
                    <span class="text-2xl font-black text-white mt-0.5 block">{{ currentStats.total_movies }}</span>
                </div>
            </div>

            <div class="glass-panel p-5 rounded-2xl border border-white/10 flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center shrink-0">
                    <Tv class="w-5 h-5" />
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">{{ isRTL ? 'المسلسلات المفهرسة' : 'TV Series' }}</span>
                    <span class="text-2xl font-black text-white mt-0.5 block">{{ currentStats.total_series }} <span class="text-xs text-slate-500">({{ currentStats.total_episodes }} ep)</span></span>
                </div>
            </div>

            <div class="glass-panel p-5 rounded-2xl border border-white/10 flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0">
                    <Layers class="w-5 h-5" />
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">{{ isRTL ? 'سلاسل الأفلام' : 'Collections' }}</span>
                    <span class="text-2xl font-black text-white mt-0.5 block">{{ currentStats.total_collections || 0 }}</span>
                </div>
            </div>

            <div class="glass-panel p-5 rounded-2xl border border-white/10 flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center shrink-0">
                    <HardDrive class="w-5 h-5" />
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">{{ isRTL ? 'حجم الوسائط' : 'Indexed Size' }}</span>
                    <span class="text-2xl font-black text-white mt-0.5 block">{{ currentStats.storage_size_formatted }}</span>
                </div>
            </div>

            <div class="glass-panel p-5 rounded-2xl border border-white/10 flex items-center gap-4 col-span-2 sm:col-span-1">
                <div class="w-11 h-11 rounded-2xl bg-purple-500/10 text-purple-400 flex items-center justify-center shrink-0">
                    <MessageSquare class="w-5 h-5" />
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">{{ isRTL ? 'ملفات الترجمة' : 'Subtitles' }}</span>
                    <span class="text-2xl font-black text-white mt-0.5 block">{{ currentStats.total_subtitles }}</span>
                </div>
            </div>
        </div>

        <!-- 2. Active Scan Progress & Control Bar -->
        <div v-if="isScanning || scanStatus.status === 'scanning' || scanStatus.status === 'paused'" class="glass-panel rounded-3xl p-6 border border-cyan-500/30 mb-8 space-y-4 shadow-lg shadow-cyan-500/5 relative overflow-hidden">
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
                                        {{ isRTL ? 'فهرسة نشطة' : 'Active Index' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <button
                            @click="promptRemoveDirectory(idx, dir)"
                            class="text-slate-500 hover:text-rose-400 transition-colors p-1.5 rounded-lg hover:bg-rose-500/10 cursor-pointer"
                            :title="isRTL ? 'إزالة المجلد' : 'Remove folder'"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="pt-3 border-t border-white/5 flex items-center justify-between flex-wrap gap-2">
                        <span class="text-[11px] text-slate-400 flex items-center gap-1">
                            <CheckCircle2 class="w-3.5 h-3.5 text-emerald-400" />
                            <span>{{ isRTL ? 'جاهز للفحص' : 'Ready' }}</span>
                        </span>

                        <div class="flex items-center gap-2">
                            <button
                                @click="scanSingleFolder(dir)"
                                :disabled="isScanning"
                                class="px-3 py-1.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                            >
                                <Play class="w-3 h-3 fill-current" />
                                <span>{{ isRTL ? 'فحص تدريجي' : 'Inc. Scan' }}</span>
                            </button>

                            <button
                                @click="promptFreshRescanFolder(dir)"
                                :disabled="isScanning"
                                class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-amber-500/20 text-amber-300 border border-white/10 hover:border-amber-500/30 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                                :title="isRTL ? 'إعادة فحص جديدة لهذا المجلد ومسح عناصره السابقة' : 'Wipe previous items from this folder and rescan freshly'"
                            >
                                <RotateCcw class="w-3 h-3" />
                                <span>{{ isRTL ? 'فحص جديد' : 'Fresh' }}</span>
                            </button>
                        </div>
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
                        class="w-full ps-10 pe-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                    />
                    <FolderPlus class="w-4 h-4 absolute start-3 top-1/2 -translate-y-1/2 text-slate-400" />
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <select
                        v-model="newDirType"
                        class="py-2.5 px-3 rounded-xl bg-[#080B12] border border-white/10 text-xs text-slate-300 focus:outline-none focus:border-cyan-500 cursor-pointer"
                    >
                        <option value="mixed">{{ isRTL ? 'مختلط (أفلام ومسلسلات)' : 'Mixed Content' }}</option>
                        <option value="movies">{{ isRTL ? 'أفلام فقط' : 'Movies Only' }}</option>
                        <option value="series">{{ isRTL ? 'مسلسلات فقط' : 'TV Series Only' }}</option>
                    </select>

                    <button
                        type="submit"
                        :disabled="isAddingDir || !newDirPath.trim()"
                        class="px-4 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-extrabold flex items-center gap-1.5 transition-all cursor-pointer whitespace-nowrap disabled:opacity-50"
                    >
                        <FolderPlus class="w-4 h-4" />
                        <span>{{ isRTL ? 'إضافة المجلد' : 'Add Folder' }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- 4. Real-time Diagnostic Terminal Feed -->
        <div class="glass-panel rounded-3xl p-6 border border-white/10 space-y-4">
            <div class="flex items-center justify-between flex-wrap gap-3 border-b border-white/10 pb-4">
                <div class="flex items-center gap-2">
                    <Terminal class="w-4 h-4 text-cyan-400" />
                    <h3 class="font-extrabold text-sm text-white">{{ isRTL ? 'سجل العمليات والفهرسة المباشر' : 'Diagnostic Real-Time Console' }}</h3>
                </div>

                <!-- Filter badges -->
                <div class="flex items-center gap-1 text-[10px]">
                    <button
                        v-for="flt in ['all', 'success', 'info', 'error'] as const"
                        :key="flt"
                        @click="terminalFilter = flt"
                        class="px-2.5 py-1 rounded-lg uppercase font-bold transition-all cursor-pointer"
                        :class="terminalFilter === flt ? 'bg-cyan-500 text-slate-950 font-black' : 'bg-white/5 text-slate-400 hover:text-white'"
                    >
                        {{ flt }}
                    </button>
                </div>
            </div>

            <!-- Terminal Output Window -->
            <div id="terminal-feed" class="h-64 overflow-y-auto font-mono text-xs space-y-1.5 p-4 rounded-2xl bg-black/80 border border-white/5 custom-scrollbar">
                <div
                    v-for="(log, idx) in filteredLogs"
                    :key="idx"
                    class="flex items-start gap-2.5 leading-relaxed"
                >
                    <span class="text-slate-600 select-none shrink-0">[{{ log.time }}]</span>
                    <span
                        :class="{
                            'text-emerald-400': log.level === 'success',
                            'text-cyan-300': log.level === 'info',
                            'text-amber-400': log.level === 'warning',
                            'text-rose-400 font-bold': log.level === 'error',
                        }"
                    >
                        {{ log.message }}
                    </span>
                </div>

                <div v-if="filteredLogs.length === 0" class="text-slate-600 italic py-12 text-center">
                    {{ isRTL ? 'لا توجد عمليات حالية. اضغط على "بدء الفحص" لبدء الفهرسة.' : 'Scanner console idle. Start a scan to watch live events.' }}
                </div>
            </div>
        </div>

        <!-- Custom Confirm Modal -->
        <ConfirmModal
            :show="confirmModal.show"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :confirm-text="confirmModal.confirmText"
            :type="confirmModal.type"
            @confirm="triggerConfirmAction"
            @cancel="confirmModal.show = false"
        />
    </AppLayout>
</template>
