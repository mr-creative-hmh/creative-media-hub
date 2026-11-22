<script setup lang="ts">
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import { useScanner } from '@/composables/useScanner';
import AppLayout from '@/components/layout/AppLayout.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';
import {
    ScanLine,
    FolderPlus,
    Play,
    Pause,
    XCircle,
    RotateCcw,
    CheckCircle2,
    AlertCircle,
    FileVideo,
    HardDrive,
    Terminal,
    Layers,
    Cpu,
    RefreshCw,
    Trash2,
    Folder,
    Film,
    Tv,
    Sparkles,
    Check,
    Filter,
    Clock,
    Info,
    ShieldAlert,
    ShieldCheck,
    ArrowRight,
    Image as ImageIcon,
    MessageSquare,
} from '@lucide/vue';

const props = defineProps<{
    directories: Array<{
        id?: string;
        path: string;
        type: string;
        count?: number;
    }>;
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
    fetchStatus,
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
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
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
            toastMessage.value = isRTL.value
                ? 'تمت إضافة المجلد بنجاح!'
                : 'Directory added successfully!';
        }
    } finally {
        isAddingDir.value = false;
    }
};

const promptRemoveDirectory = (idx: number, dir: any) => {
    confirmModal.value = {
        show: true,
        title: isRTL.value ? 'إزالة مجلد مراقب' : 'Remove Monitored Folder',
        message: isRTL.value
            ? `هل أنت متأكد من إزالة "${dir.path}" من قائمة المجلدات المراقبة؟ (لن يتم حذف الملفات من القرص).`
            : `Are you sure you want to remove "${dir.path}" from monitored library folders? (Files on disk will NOT be deleted).`,
        confirmText: isRTL.value ? 'إزالة' : 'Remove Folder',
        type: 'danger',
        action: async () => {
            confirmModal.value.show = false;
            try {
                const res = await fetch(`/api/scanner/directories/${idx}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN':
                            (
                                document.querySelector(
                                    'meta[name="csrf-token"]',
                                ) as any
                            )?.content || '',
                    },
                });
                if (res.ok) {
                    const data = await res.json();
                    monitoredDirs.value = data.directories || [];
                    toastMessage.value = isRTL.value
                        ? 'تمت إزالة المجلد بنجاح.'
                        : 'Directory removed.';
                }
            } catch (e) {}
        },
    };
};

const startScan = async () => {
    if (monitoredDirs.value.length === 0) {
        toastMessage.value = isRTL.value
            ? 'يرجى إضافة مجلد واحد على الأقل للمسح.'
            : 'Please add at least one folder to scan.';
        return;
    }
    await startFullScan(monitoredDirs.value, 'incremental');
    toastMessage.value = isRTL.value
        ? 'بدأ المسح التدريجي للمكتبة في الخلفية...'
        : 'Incremental library scan started in background...';
};

const promptRescanIncremental = () => {
    confirmModal.value = {
        show: true,
        title: isRTL.value
            ? 'إعادة مسح تدريجي للمكتبة'
            : 'Incremental Library Rescan',
        message: isRTL.value
            ? 'سيتم فحص المجلدات المراقبة وإضافة الملفات الجديدة فقط (المفهرسة مسبقاً سيتم تخطيها). متابعة؟'
            : 'This will scan monitored folders and add only new files (previously indexed items will be skipped). Continue?',
        confirmText: isRTL.value ? 'بدء مسح تدريجي' : 'Start Incremental Scan',
        type: 'info',
        action: async () => {
            confirmModal.value.show = false;
            await startFullScan(monitoredDirs.value, 'incremental');
            toastMessage.value = isRTL.value
                ? 'بدأ المسح التدريجي للمكتبة!'
                : 'Incremental library scan started!';
        },
    };
};

const promptRescanFresh = () => {
    confirmModal.value = {
        show: true,
        title: isRTL.value
            ? 'إعادة مسح شاملة ونظيفة'
            : 'Fresh Full Library Rescan',
        message: isRTL.value
            ? 'هل أنت متأكد من مسح الفهرس السابق وبدء مسح جديد ونظيف لجميع المجلدات المراقبة؟'
            : 'Are you sure you want to wipe the previous scan and start a fresh library indexing across all monitored folders?',
        confirmText: isRTL.value ? 'بدء مسح نظيف' : 'Start Fresh Scan',
        type: 'warning',
        action: async () => {
            confirmModal.value.show = false;
            await rescanFresh();
            toastMessage.value = isRTL.value
                ? 'تم مسح الفهرس القديم وبدأ المسح النظيف!'
                : 'Previous index wiped. Fresh scan started!';
        },
    };
};

const promptClearCatalog = () => {
    confirmModal.value = {
        show: true,
        title: isRTL.value
            ? 'مسح قاعدة بيانات المكتبة'
            : 'Clear Library Catalog & Analytics',
        message: isRTL.value
            ? 'تحذير: سيتم مسح جميع الأفلام والمسلسلات وسجلات المشاهدة من قاعدة البيانات (لن تُحذف ملفاتك من القرص). هل ترغب بالمتابعة؟'
            : 'Warning: This will remove all indexed movies, series, watch histories, and analytics metrics from your library database (files on disk will NOT be deleted). Continue?',
        confirmText: isRTL.value ? 'مسح الفهرس' : 'Wipe Catalog',
        type: 'danger',
        action: async () => {
            confirmModal.value.show = false;
            isClearing.value = true;
            try {
                await clearCatalog();
                toastMessage.value = isRTL.value
                    ? 'تم مسح قاعدة بيانات الفهرس بنجاح.'
                    : 'Library catalog & analytics cleared.';
                setTimeout(() => {
                    router.reload();
                }, 800);
            } finally {
                isClearing.value = false;
            }
        },
    };
};

const scanSingleFolder = async (dir: { path: string; type: string }) => {
    await scanFolder(dir.path, dir.type, false, 'incremental');
    toastMessage.value = isRTL.value
        ? `جاري المسح التدريجي: ${dir.path}`
        : `Incremental scan started for: ${dir.path}`;
};

const promptFreshRescanFolder = (dir: { path: string; type: string }) => {
    confirmModal.value = {
        show: true,
        title: isRTL.value ? 'إعادة مسح نظيفة للمجلد' : 'Fresh Rescan Folder',
        message: isRTL.value
            ? `سيتم مسح الفهرس السابق لهذا المجلد وإعادة فهرسة كل شيء من الصفر: "${dir.path}". متابعة؟`
            : `This will wipe previous index for this folder and re-scan everything from scratch: "${dir.path}". Continue?`,
        confirmText: isRTL.value ? 'إعادة مسح نظيف' : 'Fresh Rescan',
        type: 'warning',
        action: async () => {
            confirmModal.value.show = false;
            await scanFolder(dir.path, dir.type, true, 'fresh');
            toastMessage.value = isRTL.value
                ? `بدأ المسح النظيف للمجلد: ${dir.path}`
                : `Fresh scan started for: ${dir.path}`;
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
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
        });
        if (res.ok) {
            const data = await res.json();
            toastMessage.value = isRTL.value
                ? `تم بنجاح جلب بوسترات وبيانات ${data.result?.enriched_count || data.result?.total || 0} عنصر!`
                : `Successfully downloaded posters & metadata for ${data.result?.enriched_count || data.result?.total || 0} items!`;
            setTimeout(() => {
                router.reload();
            }, 1200);
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

watch(
    () => scanStatus.value.logs?.length,
    async () => {
        await nextTick();
        const term = document.getElementById('terminal-feed');
        if (term) term.scrollTop = term.scrollHeight;
    },
);

onMounted(() => {
    fetchStatus();
});
</script>

<template>
    <AppLayout v-slot="{ play }">
        <Head :title="t('scanner_view.title')" />
        <!-- Header -->
        <div class="mb-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-2xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-400"
                    >
                        <ScanLine class="h-5 w-5" />
                    </div>
                    <div>
                        <h1
                            class="text-2xl font-extrabold text-white sm:text-3xl"
                        >
                            {{ t('scanner_view.title') }}
                        </h1>
                        <p class="mt-0.5 text-xs text-slate-400 sm:text-sm">
                            {{ t('scanner_view.subtitle') }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <Link
                        href="/subtitles?tab=checker"
                        class="flex cursor-pointer items-center gap-2 rounded-xl border border-cyan-500/30 bg-cyan-500/10 px-3.5 py-2 text-xs font-bold text-cyan-300 transition-all hover:bg-cyan-500/20"
                    >
                        <ShieldCheck class="h-4 w-4 text-cyan-400" />
                        <span>{{
                            isRTL ? 'فاحص ومطهر الترجمات' : 'Subtitle Checker'
                        }}</span>
                    </Link>

                    <button
                        @click="enrichMissingPosters"
                        :disabled="isBatchEnriching"
                        class="flex cursor-pointer items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3.5 py-2 text-xs font-bold text-slate-300 transition-all hover:bg-white/10"
                    >
                        <RefreshCw
                            v-if="isBatchEnriching"
                            class="h-4 w-4 animate-spin text-cyan-400"
                        />
                        <Sparkles v-else class="h-4 w-4 text-cyan-400" />
                        <span>{{
                            isRTL
                                ? 'تحميل الأغلفة الناقصة تلقائياً'
                                : 'Auto-Download Missing Artwork'
                        }}</span>
                    </button>

                    <button
                        @click="promptRescanFresh"
                        :disabled="isScanning"
                        class="flex cursor-pointer items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3.5 py-2 text-xs font-bold text-amber-300 transition-all hover:border-amber-500/40 hover:bg-white/10"
                    >
                        <RotateCcw class="h-4 w-4" />
                        <span>{{
                            isRTL
                                ? 'إعادة فحص شاملة للمكتبة'
                                : 'Fresh Full Rescan'
                        }}</span>
                    </button>

                    <button
                        @click="promptRescanIncremental"
                        :disabled="isScanning"
                        class="flex cursor-pointer items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3.5 py-2 text-xs font-bold text-cyan-300 transition-all hover:border-cyan-500/40 hover:bg-white/10"
                    >
                        <RotateCcw class="h-4 w-4" />
                        <span>{{
                            isRTL ? 'إعادة فحص تدريجي' : 'Incremental Rescan'
                        }}</span>
                    </button>

                    <button
                        @click="promptClearCatalog"
                        :disabled="isClearing || isScanning"
                        class="flex cursor-pointer items-center gap-2 rounded-xl border border-rose-500/30 bg-rose-500/10 px-3.5 py-2 text-xs font-bold text-rose-400 transition-all hover:bg-rose-500/20"
                    >
                        <Trash2 class="h-4 w-4" />
                        <span>{{
                            isRTL
                                ? 'مسح فهارس المكتبة والإحصائيات'
                                : 'Clear Library Catalog'
                        }}</span>
                    </button>

                    <button
                        @click="startScan"
                        :disabled="isScanning"
                        class="flex cursor-pointer items-center gap-2 rounded-xl bg-cyan-500 px-5 py-2 text-xs font-black text-slate-950 shadow-lg shadow-cyan-500/20 transition-all hover:bg-cyan-400 active:scale-95"
                    >
                        <Play class="h-4 w-4 fill-current" />
                        <span>{{
                            isRTL
                                ? 'بدء فحص تدريجي للمجلدات'
                                : 'Start Incremental Scan'
                        }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 1. Stats Bento Row (Live Reactive Stats + 5th Collections Card) -->
        <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <div
                class="glass-panel flex items-center gap-4 rounded-2xl border border-white/10 p-5"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-cyan-500/10 text-cyan-400"
                >
                    <Film class="h-5 w-5" />
                </div>
                <div>
                    <span
                        class="block text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >{{ isRTL ? 'الأفلام المفهرسة' : 'Indexed Movies' }}</span
                    >
                    <span class="mt-0.5 block text-2xl font-black text-white">{{
                        currentStats.total_movies
                    }}</span>
                </div>
            </div>

            <div
                class="glass-panel flex items-center gap-4 rounded-2xl border border-white/10 p-5"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-400"
                >
                    <Tv class="h-5 w-5" />
                </div>
                <div>
                    <span
                        class="block text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >{{ isRTL ? 'المسلسلات المفهرسة' : 'TV Series' }}</span
                    >
                    <span class="mt-0.5 block text-2xl font-black text-white"
                        >{{ currentStats.total_series }}
                        <span class="text-xs text-slate-500"
                            >({{ currentStats.total_episodes }} ep)</span
                        ></span
                    >
                </div>
            </div>

            <div
                class="glass-panel flex items-center gap-4 rounded-2xl border border-white/10 p-5"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-400"
                >
                    <Layers class="h-5 w-5" />
                </div>
                <div>
                    <span
                        class="block text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >{{ isRTL ? 'سلاسل الأفلام' : 'Collections' }}</span
                    >
                    <span class="mt-0.5 block text-2xl font-black text-white">{{
                        currentStats.total_collections || 0
                    }}</span>
                </div>
            </div>

            <div
                class="glass-panel flex items-center gap-4 rounded-2xl border border-white/10 p-5"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-400"
                >
                    <HardDrive class="h-5 w-5" />
                </div>
                <div>
                    <span
                        class="block text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >{{ isRTL ? 'حجم الوسائط' : 'Indexed Size' }}</span
                    >
                    <span class="mt-0.5 block text-2xl font-black text-white">{{
                        currentStats.storage_size_formatted
                    }}</span>
                </div>
            </div>

            <div
                class="glass-panel col-span-2 flex items-center gap-4 rounded-2xl border border-white/10 p-5 sm:col-span-1"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-purple-500/10 text-purple-400"
                >
                    <MessageSquare class="h-5 w-5" />
                </div>
                <div>
                    <span
                        class="block text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >{{ isRTL ? 'ملفات الترجمة' : 'Subtitles' }}</span
                    >
                    <span class="mt-0.5 block text-2xl font-black text-white">{{
                        currentStats.total_subtitles
                    }}</span>
                </div>
            </div>
        </div>

        <!-- 2. Active Scan Progress & Control Bar -->
        <div
            v-if="
                isScanning ||
                scanStatus.status === 'scanning' ||
                scanStatus.status === 'paused'
            "
            class="glass-panel relative mb-8 space-y-4 overflow-hidden rounded-3xl border border-cyan-500/30 p-6 shadow-lg shadow-cyan-500/5"
        >
            <div
                class="ambient-glow pointer-events-none -top-20 -right-20 h-80 w-80 bg-cyan-500/20"
            ></div>

            <div
                class="relative z-10 flex flex-wrap items-center justify-between gap-4"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-500/20 text-cyan-400"
                    >
                        <RefreshCw class="h-5 w-5 animate-spin" />
                    </div>
                    <div>
                        <h3
                            class="flex items-center gap-2 text-sm font-extrabold text-white"
                        >
                            <span>{{
                                isRTL
                                    ? 'جاري فهرسة المكتبة وتحميل الأغلفة...'
                                    : 'Virtual Scanner Active & Indexing...'
                            }}</span>
                            <span
                                class="cinema-badge border-cyan-500/30 bg-cyan-500/20 text-[10px] text-cyan-300"
                            >
                                {{ scanStatus.processed_files }} /
                                {{ scanStatus.total_files }}
                            </span>
                        </h3>
                        <p
                            class="mt-0.5 max-w-lg truncate text-xs text-slate-400"
                        >
                            {{
                                scanStatus.current_file ||
                                (isRTL
                                    ? 'جاري قراءة الملفات...'
                                    : 'Processing media streams...')
                            }}
                        </p>
                    </div>
                </div>

                <!-- Control Buttons -->
                <div class="flex items-center gap-2">
                    <button
                        v-if="isScanning"
                        @click="pauseScan"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/30 bg-amber-500/20 px-3.5 py-1.5 text-xs font-bold text-amber-300 hover:bg-amber-500/30"
                    >
                        <Pause class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                    </button>
                    <button
                        v-if="isPaused"
                        @click="resumeScan"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-cyan-500 px-3.5 py-1.5 text-xs font-bold text-slate-950 hover:bg-cyan-400"
                    >
                        <Play class="h-3.5 w-3.5 fill-current" />
                        <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                    </button>
                    <button
                        @click="cancelScan"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/20 px-3.5 py-1.5 text-xs font-bold text-rose-300 hover:bg-rose-500/30"
                    >
                        <XCircle class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'إلغاء' : 'Cancel' }}</span>
                    </button>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="relative z-10 space-y-1">
                <div
                    class="h-3 w-full overflow-hidden rounded-full border border-white/10 bg-white/5"
                >
                    <div
                        class="relative h-full bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500 transition-all duration-300"
                        :style="{
                            width: `${scanStatus.progress_percent || 0}%`,
                        }"
                    ></div>
                </div>
                <div
                    class="flex justify-between text-[11px] font-bold text-slate-400"
                >
                    <span
                        >{{ isRTL ? 'نسبة الإنجاز' : 'Progress' }}:
                        {{ scanStatus.progress_percent || 0 }}%</span
                    >
                    <span
                        >{{ scanStatus.processed_files }} /
                        {{ scanStatus.total_files }}
                        {{ isRTL ? 'ملف' : 'files' }}</span
                    >
                </div>
            </div>
        </div>

        <!-- 3. Monitored Folders Grid (Creative-FileFlow Style) -->
        <div class="mb-8 space-y-4">
            <div class="flex items-center justify-between">
                <h3
                    class="flex items-center gap-2 text-base font-bold text-white"
                >
                    <Folder class="h-4 w-4 text-cyan-400" />
                    <span>{{
                        isRTL
                            ? 'المجلدات المراقبة والمفهرسة'
                            : 'Monitored Library Directories'
                    }}</span>
                </h3>
            </div>

            <!-- Folders List or Empty State -->
            <div
                v-if="monitoredDirs.length > 0"
                class="grid grid-cols-1 gap-4 md:grid-cols-2"
            >
                <div
                    v-for="(dir, idx) in monitoredDirs"
                    :key="dir.path"
                    class="glass-panel group flex flex-col justify-between space-y-4 rounded-2xl border border-white/10 p-5 transition-all hover:border-cyan-500/40"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <div
                                class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-400"
                            >
                                <Folder class="h-4 w-4" />
                            </div>
                            <div class="min-w-0">
                                <h4
                                    class="truncate font-mono text-xs font-bold text-white"
                                    :title="dir.path"
                                >
                                    {{ dir.path }}
                                </h4>
                                <div class="mt-1 flex items-center gap-2">
                                    <span
                                        class="cinema-badge border-cyan-500/30 bg-cyan-500/20 text-[10px] text-cyan-300"
                                    >
                                        {{
                                            dir.type === 'movies'
                                                ? isRTL
                                                    ? 'أفلام'
                                                    : 'Movies'
                                                : dir.type === 'series'
                                                  ? isRTL
                                                      ? 'مسلسلات'
                                                      : 'TV Series'
                                                  : isRTL
                                                    ? 'مختلط'
                                                    : 'Mixed'
                                        }}
                                    </span>
                                    <span class="text-[11px] text-slate-400">
                                        {{
                                            isRTL
                                                ? 'فهرسة نشطة'
                                                : 'Active Index'
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <button
                            @click="promptRemoveDirectory(idx, dir)"
                            class="cursor-pointer rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-rose-500/10 hover:text-rose-400"
                            :title="isRTL ? 'إزالة المجلد' : 'Remove folder'"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-t border-white/5 pt-3"
                    >
                        <span
                            class="flex items-center gap-1 text-[11px] text-slate-400"
                        >
                            <CheckCircle2
                                class="h-3.5 w-3.5 text-emerald-400"
                            />
                            <span>{{ isRTL ? 'جاهز للفحص' : 'Ready' }}</span>
                        </span>

                        <div class="flex items-center gap-2">
                            <button
                                @click="scanSingleFolder(dir)"
                                :disabled="isScanning"
                                class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-cyan-500/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-bold text-cyan-400 transition-all hover:bg-cyan-500/20"
                            >
                                <Play class="h-3 w-3 fill-current" />
                                <span>{{
                                    isRTL ? 'فحص تدريجي' : 'Inc. Scan'
                                }}</span>
                            </button>

                            <button
                                @click="promptFreshRescanFolder(dir)"
                                :disabled="isScanning"
                                class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-white/10 bg-white/5 px-2.5 py-1.5 text-xs font-bold text-amber-300 transition-all hover:border-amber-500/30 hover:bg-amber-500/20"
                                :title="
                                    isRTL
                                        ? 'إعادة فحص جديدة لهذا المجلد ومسح عناصره السابقة'
                                        : 'Wipe previous items from this folder and rescan freshly'
                                "
                            >
                                <RotateCcw class="h-3 w-3" />
                                <span>{{ isRTL ? 'فحص جديد' : 'Fresh' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clean Empty State when no folders added -->
            <div
                v-else
                class="glass-panel space-y-2 rounded-3xl border border-white/10 p-10 text-center text-slate-400"
            >
                <Folder class="mx-auto h-8 w-8 text-cyan-500/50" />
                <p class="text-sm font-bold text-white">
                    {{
                        isRTL
                            ? 'لم تقم بإضافة أي مجلدات للمكتبة بعد'
                            : 'No media folders added yet'
                    }}
                </p>
                <p class="mx-auto max-w-md text-xs text-slate-500">
                    {{
                        isRTL
                            ? 'أدخل مسار مجلد الأفلام أو المسلسلات أدناه للبدء بالفهرسة والمسح التلقائي.'
                            : 'Enter your movies or TV shows folder path below to start scanning and indexing your personal library.'
                    }}
                </p>
            </div>

            <!-- Add Folder Form -->
            <form
                @submit.prevent="addDirectory"
                class="glass-panel flex flex-col items-center gap-3 rounded-2xl border border-white/10 p-4 sm:flex-row"
            >
                <div class="relative w-full flex-1">
                    <input
                        v-model="newDirPath"
                        type="text"
                        :placeholder="
                            isRTL
                                ? 'مثال: D:/Movies أو /Volumes/Media/TV'
                                : 'e.g. D:/Movies or C:/Users/hasan/Videos'
                        "
                        class="w-full rounded-xl border border-white/10 bg-white/5 py-2.5 ps-10 pe-4 text-xs text-white placeholder-slate-500 focus:border-cyan-500 focus:outline-none"
                    />
                    <FolderPlus
                        class="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                    />
                </div>

                <div class="flex w-full items-center gap-2 sm:w-auto">
                    <select
                        v-model="newDirType"
                        class="cursor-pointer rounded-xl border border-white/10 bg-[#080B12] px-3 py-2.5 text-xs text-slate-300 focus:border-cyan-500 focus:outline-none"
                    >
                        <option value="mixed">
                            {{
                                isRTL ? 'مختلط (أفلام ومسلسلات)' : 'Mixed Content'
                            }}
                        </option>
                        <option value="movies">
                            {{ isRTL ? 'أفلام فقط' : 'Movies Only' }}
                        </option>
                        <option value="series">
                            {{ isRTL ? 'مسلسلات فقط' : 'TV Series Only' }}
                        </option>
                    </select>

                    <button
                        type="submit"
                        :disabled="isAddingDir || !newDirPath.trim()"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-cyan-500 px-4 py-2.5 text-xs font-extrabold whitespace-nowrap text-slate-950 transition-all hover:bg-cyan-400 disabled:opacity-50"
                    >
                        <FolderPlus class="h-4 w-4" />
                        <span>{{ isRTL ? 'إضافة المجلد' : 'Add Folder' }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- 4. Real-time Diagnostic Terminal Feed -->
        <div
            class="glass-panel space-y-4 rounded-3xl border border-white/10 p-6"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-4"
            >
                <div class="flex items-center gap-2">
                    <Terminal class="h-4 w-4 text-cyan-400" />
                    <h3 class="text-sm font-extrabold text-white">
                        {{
                            isRTL
                                ? 'سجل العمليات والفهرسة المباشر'
                                : 'Diagnostic Real-Time Console'
                        }}
                    </h3>
                </div>

                <!-- Filter badges -->
                <div class="flex items-center gap-1 text-[10px]">
                    <button
                        v-for="flt in [
                            'all',
                            'success',
                            'info',
                            'error',
                        ] as const"
                        :key="flt"
                        @click="terminalFilter = flt"
                        class="cursor-pointer rounded-lg px-2.5 py-1 font-bold uppercase transition-all"
                        :class="
                            terminalFilter === flt
                                ? 'bg-cyan-500 font-black text-slate-950'
                                : 'bg-white/5 text-slate-400 hover:text-white'
                        "
                    >
                        {{ flt }}
                    </button>
                </div>
            </div>

            <!-- Terminal Output Window -->
            <div
                id="terminal-feed"
                class="custom-scrollbar h-64 space-y-1.5 overflow-y-auto rounded-2xl border border-white/5 bg-black/80 p-4 font-mono text-xs"
            >
                <div
                    v-for="(log, idx) in filteredLogs"
                    :key="idx"
                    class="flex items-start gap-2.5 leading-relaxed"
                >
                    <span class="shrink-0 text-slate-600 select-none"
                        >[{{ log.time }}]</span
                    >
                    <span
                        :class="{
                            'text-emerald-400': log.level === 'success',
                            'text-cyan-300': log.level === 'info',
                            'text-amber-400': log.level === 'warning',
                            'font-bold text-rose-400': log.level === 'error',
                        }"
                    >
                        {{ log.message }}
                    </span>
                </div>

                <div
                    v-if="filteredLogs.length === 0"
                    class="py-12 text-center text-slate-600 italic"
                >
                    {{
                        isRTL
                            ? 'لا توجد عمليات حالية. اضغط على "بدء الفحص" لبدء الفهرسة.'
                            : 'Scanner console idle. Start a scan to watch live events.'
                    }}
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
