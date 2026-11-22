<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import { useScanner } from '@/composables/useScanner';
import AppLayout from '@/components/layout/AppLayout.vue';
import PageBanner from '@/components/common/PageBanner.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';
import {
    ScanLine,
    FolderPlus,
    Play,
    Pause,
    XCircle,
    RotateCcw,
    CheckCircle2,
    HardDrive,
    Terminal,
    Layers,
    RefreshCw,
    Trash2,
    Folder,
    Film,
    Tv,
    Sparkles,
    ShieldCheck,
    MessageSquare,
    SlidersHorizontal,
    ChevronDown,
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
const isToolsMenuOpen = ref(false);
const toolsMenuRef = ref<HTMLElement | null>(null);
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
            } catch {}
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

const handleDocumentClick = (e: MouseEvent) => {
    if (
        isToolsMenuOpen.value &&
        toolsMenuRef.value &&
        !toolsMenuRef.value.contains(e.target as Node)
    ) {
        isToolsMenuOpen.value = false;
    }
};

const handleDocumentKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && isToolsMenuOpen.value) {
        isToolsMenuOpen.value = false;
    }
};

onMounted(() => {
    fetchStatus();
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleDocumentKeydown);
});

onUnmounted(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleDocumentKeydown);
});
</script>

<template>
    <AppLayout>
        <Head :title="t('scanner_view.title')" />

        <!-- Header PageBanner -->
        <PageBanner
            class="mb-8"
            :icon="ScanLine"
            :title="t('scanner_view.title')"
            :description="t('scanner_view.subtitle')"
            :badge="`${monitoredDirs.length} ${isRTL ? 'مجلدات مراقبة' : 'Folders'}`"
        >
            <template #actions>
                <button
                    type="button"
                    @click="startScan"
                    :disabled="isScanning"
                    class="bg-primary text-primary-foreground shadow-primary/25 flex min-h-10 cursor-pointer items-center gap-2 rounded-xl px-5 py-2 text-xs font-black shadow-lg transition-all hover:brightness-105 active:scale-95 disabled:opacity-50"
                >
                    <Play class="h-4 w-4 fill-current" />
                    <span>{{
                        isRTL
                            ? 'بدء فحص تدريجي للمجلدات'
                            : 'Start Incremental Scan'
                    }}</span>
                </button>

                <button
                    type="button"
                    @click="enrichMissingPosters"
                    :disabled="isBatchEnriching"
                    class="cf-glass border-border/80 text-foreground hover:border-primary/40 flex min-h-10 cursor-pointer items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-bold transition-all active:scale-95 disabled:opacity-50"
                >
                    <RefreshCw
                        v-if="isBatchEnriching"
                        class="text-primary h-4 w-4 animate-spin"
                    />
                    <Sparkles v-else class="text-primary h-4 w-4" />
                    <span>{{
                        isRTL ? 'تحميل الأغلفة الناقصة' : 'Auto-Download Artwork'
                    }}</span>
                </button>

                <div ref="toolsMenuRef" class="relative">
                    <button
                        type="button"
                        @click.stop="isToolsMenuOpen = !isToolsMenuOpen"
                        :aria-expanded="isToolsMenuOpen"
                        aria-haspopup="menu"
                        class="cf-glass border-border/80 text-foreground hover:border-primary/40 flex min-h-10 cursor-pointer items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-bold transition-all active:scale-95"
                    >
                        <SlidersHorizontal class="text-primary h-4 w-4" />
                        <span>{{ isRTL ? 'أدوات' : 'Tools' }}</span>
                        <ChevronDown
                            class="text-muted-foreground h-3.5 w-3.5 transition-transform duration-200"
                            :class="isToolsMenuOpen ? 'rotate-180' : ''"
                        />
                    </button>

                    <div
                        v-if="isToolsMenuOpen"
                        role="menu"
                        class="cf-glass-popover border-border/80 absolute end-0 top-full z-40 mt-2 min-w-[230px] rounded-2xl border p-1.5 shadow-2xl"
                    >
                        <button
                            type="button"
                            role="menuitem"
                            :disabled="isScanning"
                            @click="
                                isToolsMenuOpen = false;
                                promptRescanIncremental();
                            "
                            class="text-foreground hover:bg-primary/10 hover:text-primary flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-start text-xs font-bold transition-colors disabled:opacity-50"
                        >
                            <RotateCcw class="text-primary h-4 w-4 shrink-0" />
                            <span>{{
                                isRTL
                                    ? 'إعادة فحص تدريجي'
                                    : 'Incremental Rescan'
                            }}</span>
                        </button>

                        <button
                            type="button"
                            role="menuitem"
                            :disabled="isScanning"
                            @click="
                                isToolsMenuOpen = false;
                                promptRescanFresh();
                            "
                            class="flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-start text-xs font-bold text-amber-300 transition-colors hover:bg-amber-500/15 disabled:opacity-50"
                        >
                            <RotateCcw class="h-4 w-4 shrink-0" />
                            <span>{{
                                isRTL
                                    ? 'إعادة فحص شاملة للمكتبة'
                                    : 'Fresh Full Rescan'
                            }}</span>
                        </button>

                        <Link
                            href="/subtitles?tab=checker"
                            role="menuitem"
                            @click="isToolsMenuOpen = false"
                            class="text-foreground hover:bg-primary/10 hover:text-primary flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-start text-xs font-bold transition-colors"
                        >
                            <ShieldCheck
                                class="text-primary h-4 w-4 shrink-0"
                            />
                            <span>{{
                                isRTL
                                    ? 'فاحص ومطهر الترجمات'
                                    : 'Subtitle Checker'
                            }}</span>
                        </Link>

                        <div class="border-border/70 my-1 border-t" />

                        <button
                            type="button"
                            role="menuitem"
                            :disabled="isClearing || isScanning"
                            @click="
                                isToolsMenuOpen = false;
                                promptClearCatalog();
                            "
                            class="text-destructive hover:bg-destructive/15 flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-start text-xs font-bold transition-colors disabled:opacity-50"
                        >
                            <Trash2 class="h-4 w-4 shrink-0" />
                            <span>{{
                                isRTL
                                    ? 'مسح فهارس المكتبة والإحصائيات'
                                    : 'Clear Library Catalog'
                            }}</span>
                        </button>
                    </div>
                </div>
            </template>
        </PageBanner>

        <!-- 1. Stats Bento Row (Live Reactive Stats + 5th Collections Card) -->
        <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <div
                class="cf-glass border-border/80 flex items-center gap-4 rounded-2xl border p-5"
            >
                <div
                    class="bg-primary/15 text-primary flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl"
                >
                    <Film class="h-5 w-5" />
                </div>
                <div>
                    <span
                        class="text-muted-foreground block text-xs font-bold tracking-wider uppercase"
                        >{{ isRTL ? 'الأفلام المفهرسة' : 'Indexed Movies' }}</span
                    >
                    <span
                        class="text-foreground mt-0.5 block text-2xl font-black tabular-nums"
                        >{{ currentStats.total_movies }}</span
                    >
                </div>
            </div>

            <div
                class="cf-glass border-border/80 flex items-center gap-4 rounded-2xl border p-5"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-500/15 text-indigo-400"
                >
                    <Tv class="h-5 w-5" />
                </div>
                <div>
                    <span
                        class="text-muted-foreground block text-xs font-bold tracking-wider uppercase"
                        >{{ isRTL ? 'المسلسلات المفهرسة' : 'TV Series' }}</span
                    >
                    <span
                        class="text-foreground mt-0.5 block text-2xl font-black tabular-nums"
                        >{{ currentStats.total_series }}
                        <span class="text-muted-foreground text-xs"
                            >({{ currentStats.total_episodes }} ep)</span
                        ></span
                    >
                </div>
            </div>

            <div
                class="cf-glass border-border/80 flex items-center gap-4 rounded-2xl border p-5"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-400"
                >
                    <Layers class="h-5 w-5" />
                </div>
                <div>
                    <span
                        class="text-muted-foreground block text-xs font-bold tracking-wider uppercase"
                        >{{ isRTL ? 'سلاسل الأفلام' : 'Collections' }}</span
                    >
                    <span
                        class="text-foreground mt-0.5 block text-2xl font-black tabular-nums"
                        >{{ currentStats.total_collections || 0 }}</span
                    >
                </div>
            </div>

            <div
                class="cf-glass border-border/80 flex items-center gap-4 rounded-2xl border p-5"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-400"
                >
                    <HardDrive class="h-5 w-5" />
                </div>
                <div>
                    <span
                        class="text-muted-foreground block text-xs font-bold tracking-wider uppercase"
                        >{{ isRTL ? 'حجم الوسائط' : 'Indexed Size' }}</span
                    >
                    <span
                        class="text-foreground mt-0.5 block text-2xl font-black tabular-nums"
                        >{{ currentStats.storage_size_formatted }}</span
                    >
                </div>
            </div>

            <div
                class="cf-glass border-border/80 col-span-2 flex items-center gap-4 rounded-2xl border p-5 sm:col-span-1"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-purple-500/15 text-purple-400"
                >
                    <MessageSquare class="h-5 w-5" />
                </div>
                <div>
                    <span
                        class="text-muted-foreground block text-xs font-bold tracking-wider uppercase"
                        >{{ isRTL ? 'ملفات الترجمة' : 'Subtitles' }}</span
                    >
                    <span
                        class="text-foreground mt-0.5 block text-2xl font-black tabular-nums"
                        >{{ currentStats.total_subtitles }}</span
                    >
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
            class="cf-glass-elevated border-primary/35 shadow-primary/10 relative mb-8 space-y-4 overflow-hidden rounded-3xl border p-6 shadow-lg"
        >
            <div
                class="ambient-glow bg-primary/15 pointer-events-none absolute -end-20 -top-20 h-80 w-80"
            ></div>

            <div
                class="relative z-10 flex flex-wrap items-center justify-between gap-4"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="bg-primary/20 text-primary flex h-9 w-9 items-center justify-center rounded-xl"
                    >
                        <RefreshCw class="h-5 w-5 animate-spin" />
                    </div>
                    <div>
                        <h3
                            class="text-foreground flex items-center gap-2 text-sm font-extrabold"
                        >
                            <span>{{
                                isRTL
                                    ? 'جاري فهرسة المكتبة وتحميل الأغلفة...'
                                    : 'Virtual Scanner Active & Indexing...'
                            }}</span>
                            <span
                                class="cinema-badge border-primary/35 bg-primary/15 text-primary border text-[10px] tabular-nums"
                            >
                                {{ scanStatus.processed_files }} /
                                {{ scanStatus.total_files }}
                            </span>
                        </h3>
                        <p
                            class="text-muted-foreground mt-0.5 max-w-lg truncate text-xs"
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
                        type="button"
                        @click="pauseScan"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/35 bg-amber-500/20 px-3.5 py-1.5 text-xs font-bold text-amber-300 hover:bg-amber-500/30"
                    >
                        <Pause class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                    </button>
                    <button
                        v-if="isPaused"
                        type="button"
                        @click="resumeScan"
                        class="bg-primary text-primary-foreground flex cursor-pointer items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-bold hover:brightness-105"
                    >
                        <Play class="h-3.5 w-3.5 fill-current" />
                        <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                    </button>
                    <button
                        type="button"
                        @click="cancelScan"
                        class="border-destructive/35 bg-destructive/20 text-destructive hover:bg-destructive/30 flex cursor-pointer items-center gap-1.5 rounded-xl border px-3.5 py-1.5 text-xs font-bold"
                    >
                        <XCircle class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'إلغاء' : 'Cancel' }}</span>
                    </button>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="relative z-10 space-y-1">
                <div
                    class="border-border/80 bg-muted/40 h-3 w-full overflow-hidden rounded-full border"
                >
                    <div
                        class="bg-primary relative h-full transition-all duration-300"
                        :style="{
                            width: `${scanStatus.progress_percent || 0}%`,
                        }"
                    ></div>
                </div>
                <div
                    class="text-muted-foreground flex justify-between text-[11px] font-bold tabular-nums"
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

        <!-- 3. Monitored Folders Grid (CreativeFlow Style) -->
        <div class="mb-8 space-y-4">
            <div class="flex items-center justify-between">
                <h3
                    class="text-foreground flex items-center gap-2 text-base font-bold"
                >
                    <Folder class="text-primary h-4 w-4" />
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
                    class="cf-glass group border-border/80 hover:border-primary/45 flex flex-col justify-between space-y-4 rounded-2xl border p-5 transition-all"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <div
                                class="bg-primary/15 text-primary mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                            >
                                <Folder class="h-4 w-4" />
                            </div>
                            <div class="min-w-0">
                                <h4
                                    class="text-foreground truncate font-mono text-xs font-bold"
                                    :title="dir.path"
                                >
                                    {{ dir.path }}
                                </h4>
                                <div class="mt-1 flex items-center gap-2">
                                    <span
                                        class="cinema-badge border-primary/35 bg-primary/15 text-primary border text-[10px]"
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
                                    <span
                                        class="text-muted-foreground text-[11px]"
                                    >
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
                            type="button"
                            @click="promptRemoveDirectory(idx, dir)"
                            class="text-muted-foreground hover:bg-destructive/15 hover:text-destructive cursor-pointer rounded-lg p-1.5 transition-colors"
                            :title="isRTL ? 'إزالة المجلد' : 'Remove folder'"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div
                        class="border-border/60 flex flex-wrap items-center justify-between gap-2 border-t pt-3"
                    >
                        <span
                            class="text-muted-foreground flex items-center gap-1 text-[11px]"
                        >
                            <CheckCircle2
                                class="h-3.5 w-3.5 text-emerald-400"
                            />
                            <span>{{ isRTL ? 'جاهز للفحص' : 'Ready' }}</span>
                        </span>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="scanSingleFolder(dir)"
                                :disabled="isScanning"
                                class="border-primary/35 bg-primary/15 text-primary hover:bg-primary/25 flex cursor-pointer items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold transition-all active:scale-95 disabled:opacity-50"
                            >
                                <Play class="h-3 w-3 fill-current" />
                                <span>{{
                                    isRTL ? 'فحص تدريجي' : 'Inc. Scan'
                                }}</span>
                            </button>

                            <button
                                type="button"
                                @click="promptFreshRescanFolder(dir)"
                                :disabled="isScanning"
                                class="cf-glass border-border/80 flex cursor-pointer items-center gap-1.5 rounded-xl border px-2.5 py-1.5 text-xs font-bold text-amber-300 transition-all hover:border-amber-500/40 hover:bg-amber-500/15 active:scale-95 disabled:opacity-50"
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
                class="cf-glass border-border/80 text-muted-foreground space-y-2 rounded-3xl border p-10 text-center"
            >
                <Folder class="text-primary/60 mx-auto h-8 w-8" />
                <p class="text-foreground text-sm font-bold">
                    {{
                        isRTL
                            ? 'لم تقم بإضافة أي مجلدات للمكتبة بعد'
                            : 'No media folders added yet'
                    }}
                </p>
                <p class="text-muted-foreground mx-auto max-w-md text-xs">
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
                class="cf-glass border-border/80 flex flex-col items-center gap-3 rounded-2xl border p-4 sm:flex-row"
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
                        class="cf-field border-border/80 bg-card/80 text-foreground placeholder:text-muted-foreground focus:border-primary h-10 w-full rounded-xl border ps-10 pe-4 text-xs focus:outline-none"
                    />
                    <FolderPlus
                        class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2"
                    />
                </div>

                <div class="flex w-full items-center gap-2 sm:w-auto">
                    <select
                        v-model="newDirType"
                        class="cf-glass border-border/80 bg-card text-foreground focus:border-primary h-10 cursor-pointer rounded-xl border px-3 text-xs font-bold focus:outline-none"
                    >
                        <option value="mixed" class="bg-card text-foreground">
                            {{
                                isRTL ? 'مختلط (أفلام ومسلسلات)' : 'Mixed Content'
                            }}
                        </option>
                        <option value="movies" class="bg-card text-foreground">
                            {{ isRTL ? 'أفلام فقط' : 'Movies Only' }}
                        </option>
                        <option value="series" class="bg-card text-foreground">
                            {{ isRTL ? 'مسلسلات فقط' : 'TV Series Only' }}
                        </option>
                    </select>

                    <button
                        type="submit"
                        :disabled="isAddingDir || !newDirPath.trim()"
                        class="bg-primary text-primary-foreground shadow-primary/25 flex min-h-10 cursor-pointer items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-extrabold whitespace-nowrap shadow-sm transition-all hover:brightness-105 active:scale-95 disabled:opacity-50"
                    >
                        <FolderPlus class="h-4 w-4" />
                        <span>{{ isRTL ? 'إضافة المجلد' : 'Add Folder' }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- 4. Real-time Diagnostic Terminal Feed -->
        <div class="cf-glass border-border/80 space-y-4 rounded-3xl border p-6">
            <div
                class="border-border/70 flex flex-wrap items-center justify-between gap-3 border-b pb-4"
            >
                <div class="flex items-center gap-2">
                    <Terminal class="text-primary h-4 w-4" />
                    <h3 class="text-foreground text-sm font-extrabold">
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
                        type="button"
                        @click="terminalFilter = flt"
                        class="cursor-pointer rounded-lg px-2.5 py-1 font-bold uppercase transition-all"
                        :class="
                            terminalFilter === flt
                                ? 'bg-primary text-primary-foreground font-black shadow-xs'
                                : 'bg-muted/50 text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ flt }}
                    </button>
                </div>
            </div>

            <!-- Terminal Output Window -->
            <div
                id="terminal-feed"
                class="custom-scrollbar border-border/60 h-64 space-y-1.5 overflow-y-auto rounded-2xl border bg-black/80 p-4 font-mono text-xs tabular-nums"
            >
                <div
                    v-for="(log, idx) in filteredLogs"
                    :key="idx"
                    class="flex items-start gap-2.5 leading-relaxed"
                >
                    <span class="shrink-0 text-slate-500 select-none"
                        >[{{ log.time }}]</span
                    >
                    <span
                        :class="{
                            'text-emerald-400': log.level === 'success',
                            'text-primary': log.level === 'info',
                            'text-amber-400': log.level === 'warning',
                            'text-destructive font-bold': log.level === 'error',
                        }"
                    >
                        {{ log.message }}
                    </span>
                </div>

                <div
                    v-if="filteredLogs.length === 0"
                    class="text-muted-foreground py-12 text-center italic"
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
