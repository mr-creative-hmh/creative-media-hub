<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { useScanner } from '@/composables/useScanner';
import { useI18n } from '@/i18n/useI18n';
import ConfirmModal from '@/components/common/ConfirmModal.vue';
import {
    ScanLine,
    Play,
    Pause,
    XCircle,
    X,
    Terminal,
    RotateCcw,
    RefreshCw,
    CheckCircle2,
    AlertCircle,
    ExternalLink,
    HardDrive,
    Trash2,
} from 'lucide-vue-next';

const {
    isScanModalOpen,
    scanStatus,
    isScanning,
    isPaused,
    closeScanModal,
    startFullScan,
    rescanFresh,
    clearCatalog,
    pauseScan,
    resumeScan,
    cancelScan,
} = useScanner();

const { t, isRTL } = useI18n();

const terminalFilter = ref<'all' | 'success' | 'info' | 'error'>('all');

const confirmModal = ref<{
    show: boolean;
    title: string;
    message: string;
    confirmText: string;
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
    return logs.filter((l) => l.level === terminalFilter.value);
});

const navigateToFullScanner = () => {
    closeScanModal();
    router.visit('/scanner');
};

const handleRescanFresh = () => {
    confirmModal.value = {
        show: true,
        title: isRTL.value
            ? 'إعادة فحص شاملة للمكتبة'
            : 'Fresh Full Library Rescan',
        message: isRTL.value
            ? 'سيتم تصفير الفهارس السابقة وإعادة فحص وتحميل بيانات وأغلفة جميع المجلدات المراقبة من جديد.'
            : 'This will wipe the previous scan and start a fresh library indexing across all monitored folders.',
        confirmText: isRTL.value ? 'بدء فحص شامل' : 'Start Fresh Scan',
        type: 'warning',
        action: async () => {
            confirmModal.value.show = false;
            await rescanFresh();
        },
    };
};

const handleClearCatalog = () => {
    confirmModal.value = {
        show: true,
        title: isRTL.value
            ? 'مسح كافة فهارس المكتبة والإحصائيات'
            : 'Clear Library Catalog & Analytics',
        message: isRTL.value
            ? 'تحذير: سيتم حذف كافة عناصر المكتبة وسجل المشاهدة والإحصائيات من قاعدة البيانات (لن يتم حذف الملفات من القرص). هل تريد المتابعة؟'
            : 'Warning: This will remove all indexed movies, series, watch histories, and analytics metrics from your library database (files on disk will NOT be deleted). Continue?',
        confirmText: isRTL.value ? 'تأكيد المسح الشامل' : 'Wipe Catalog',
        type: 'danger',
        action: async () => {
            confirmModal.value.show = false;
            await clearCatalog();
            router.reload();
        },
    };
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
        const term = document.getElementById('mini-terminal-feed');
        if (term) term.scrollTop = term.scrollHeight;
    },
);
</script>

<template>
    <div
        v-if="isScanModalOpen"
        class="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-md transition-all duration-300"
        @click.self="closeScanModal"
    >
        <div
            class="relative w-full max-w-2xl space-y-6 overflow-hidden rounded-3xl border border-cyan-500/30 bg-[#080B12] p-6 shadow-2xl shadow-cyan-500/10"
        >
            <!-- Background Glow -->
            <div
                class="ambient-glow pointer-events-none -top-24 -right-24 h-80 w-80 bg-cyan-500/15"
            ></div>

            <!-- Modal Header -->
            <div
                class="relative z-10 flex items-center justify-between border-b border-white/10 pb-4"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-2xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-400"
                    >
                        <ScanLine class="h-5 w-5" />
                    </div>
                    <div>
                        <h2
                            class="flex items-center gap-2 text-base font-extrabold text-white"
                        >
                            <span>{{
                                isRTL
                                    ? 'فاحص المكتبة الافتراضي المباشر'
                                    : 'Virtual Library Scanner'
                            }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase"
                                :class="{
                                    'animate-pulse border border-cyan-500/30 bg-cyan-500/20 text-cyan-300':
                                        isScanning,
                                    'border border-amber-500/30 bg-amber-500/20 text-amber-300':
                                        isPaused,
                                    'border border-emerald-500/30 bg-emerald-500/20 text-emerald-300':
                                        scanStatus.status === 'completed',
                                    'bg-white/10 text-slate-400':
                                        scanStatus.status === 'idle' ||
                                        scanStatus.status === 'cancelled',
                                }"
                            >
                                {{ scanStatus.status }}
                            </span>
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{
                                isRTL
                                    ? 'التحكم والمتابعة الفورية لفهرسة الأفلام والمسلسلات'
                                    : 'Universal background media stream indexer and metadata fetcher.'
                            }}
                        </p>
                    </div>
                </div>

                <button
                    @click="closeScanModal"
                    class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-xl bg-white/5 text-slate-400 transition-all hover:bg-white/10 hover:text-white"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Active Scan Controls & Stats -->
            <div class="relative z-10 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    class="rounded-2xl border border-white/10 bg-white/5 p-3.5"
                >
                    <span
                        class="block text-[10px] font-bold text-slate-500 uppercase"
                        >{{ isRTL ? 'الحالة' : 'Status' }}</span
                    >
                    <span
                        class="mt-1 block text-xs font-black text-white capitalize"
                        >{{ scanStatus.status }}</span
                    >
                </div>
                <div
                    class="rounded-2xl border border-white/10 bg-white/5 p-3.5"
                >
                    <span
                        class="block text-[10px] font-bold text-slate-500 uppercase"
                        >{{ isRTL ? 'الملفات المنجزة' : 'Indexed' }}</span
                    >
                    <span class="mt-1 block text-xs font-black text-cyan-400"
                        >{{ scanStatus.processed_files }} /
                        {{ scanStatus.total_files }}</span
                    >
                </div>
                <div
                    class="rounded-2xl border border-white/10 bg-white/5 p-3.5"
                >
                    <span
                        class="block text-[10px] font-bold text-slate-500 uppercase"
                        >{{ isRTL ? 'نسبة الإنجاز' : 'Progress' }}</span
                    >
                    <span class="mt-1 block text-xs font-black text-emerald-400"
                        >{{ scanStatus.progress_percent }}%</span
                    >
                </div>
                <div
                    class="rounded-2xl border border-white/10 bg-white/5 p-3.5"
                >
                    <span
                        class="block text-[10px] font-bold text-slate-500 uppercase"
                        >{{ isRTL ? 'أحدث ملف' : 'Current Stream' }}</span
                    >
                    <span
                        class="mt-1 block truncate font-mono text-[11px] font-bold text-slate-300"
                        :title="scanStatus.current_file || undefined"
                    >
                        {{
                            scanStatus.current_file
                                ? scanStatus.current_file.split('/').pop()
                                : '-'
                        }}
                    </span>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="relative z-10 space-y-1">
                <div
                    class="h-2.5 w-full overflow-hidden rounded-full border border-white/10 bg-white/5"
                >
                    <div
                        class="h-full bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500 transition-all duration-300"
                        :style="{
                            width: `${scanStatus.progress_percent || 0}%`,
                        }"
                    ></div>
                </div>
            </div>

            <!-- Live Terminal Output Feed -->
            <div
                class="relative z-10 space-y-3 rounded-2xl border border-white/10 bg-black/60 p-4"
            >
                <div
                    class="flex items-center justify-between border-b border-white/5 pb-2.5"
                >
                    <div class="flex items-center gap-2">
                        <Terminal class="h-3.5 w-3.5 text-cyan-400" />
                        <span class="text-xs font-bold text-slate-300">{{
                            isRTL ? 'سجل الفحص المباشر' : 'Live Indexer Stream'
                        }}</span>
                    </div>

                    <div class="flex items-center gap-1.5 text-[10px]">
                        <button
                            v-for="flt in [
                                'all',
                                'success',
                                'info',
                                'error',
                            ] as const"
                            :key="flt"
                            @click="terminalFilter = flt"
                            class="cursor-pointer rounded-lg px-2 py-0.5 font-bold uppercase transition-all"
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

                <div
                    id="mini-terminal-feed"
                    class="custom-scrollbar h-44 space-y-1.5 overflow-y-auto pr-2 font-mono text-[11px]"
                >
                    <div
                        v-for="(log, idx) in filteredLogs"
                        :key="idx"
                        class="flex items-start gap-2 leading-relaxed"
                    >
                        <span class="shrink-0 text-slate-600 select-none"
                            >[{{ log.time }}]</span
                        >
                        <span
                            :class="{
                                'text-emerald-400': log.level === 'success',
                                'text-cyan-300': log.level === 'info',
                                'text-amber-400': log.level === 'warning',
                                'font-bold text-rose-400':
                                    log.level === 'error',
                            }"
                        >
                            {{ log.message }}
                        </span>
                    </div>
                    <div
                        v-if="filteredLogs.length === 0"
                        class="py-6 text-center text-slate-600 italic"
                    >
                        {{
                            isRTL
                                ? 'لا توجد سجلات حالياً...'
                                : 'Waiting for scanner events...'
                        }}
                    </div>
                </div>
            </div>

            <!-- Action Toolbar Footer -->
            <div
                class="relative z-10 flex flex-wrap items-center justify-between gap-3 pt-2"
            >
                <div class="flex items-center gap-2">
                    <button
                        v-if="
                            scanStatus.status === 'idle' ||
                            scanStatus.status === 'completed' ||
                            scanStatus.status === 'cancelled'
                        "
                        @click="() => startFullScan()"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-cyan-500 px-4 py-2 text-xs font-extrabold text-slate-950 shadow-lg shadow-cyan-500/20 transition-all hover:bg-cyan-400"
                    >
                        <Play class="h-3.5 w-3.5 fill-current" />
                        <span>{{ isRTL ? 'بدء الفحص' : 'Start Scan' }}</span>
                    </button>

                    <button
                        v-if="isScanning"
                        @click="pauseScan"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/30 bg-amber-500/20 px-4 py-2 text-xs font-bold text-amber-300 transition-all hover:bg-amber-500/30"
                    >
                        <Pause class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                    </button>

                    <button
                        v-if="isPaused"
                        @click="resumeScan"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-cyan-500 px-4 py-2 text-xs font-bold text-slate-950 shadow-lg shadow-cyan-500/20 transition-all hover:bg-cyan-400"
                    >
                        <Play class="h-3.5 w-3.5 fill-current" />
                        <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                    </button>

                    <button
                        v-if="isScanning || isPaused"
                        @click="cancelScan"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/20 px-4 py-2 text-xs font-bold text-rose-300 transition-all hover:bg-rose-500/30"
                    >
                        <XCircle class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'إلغاء' : 'Cancel' }}</span>
                    </button>

                    <button
                        @click="handleRescanFresh"
                        :disabled="isScanning"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-white/10 bg-white/5 px-3.5 py-2 text-xs font-bold text-amber-300 transition-all hover:border-amber-500/40 hover:bg-white/10"
                        title="Wipe previous index and start fresh full scan"
                    >
                        <RotateCcw class="h-3.5 w-3.5" />
                        <span>{{
                            isRTL ? 'فحص شامل وجديد' : 'Fresh Rescan'
                        }}</span>
                    </button>

                    <button
                        @click="handleClearCatalog"
                        :disabled="isScanning"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-bold text-rose-400 transition-all hover:border-rose-500/40 hover:bg-rose-500/20"
                        title="Clear library database and analytics"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'مسح الفهارس' : 'Clear' }}</span>
                    </button>
                </div>

                <button
                    @click="navigateToFullScanner"
                    class="flex cursor-pointer items-center gap-1.5 text-xs font-bold text-cyan-400 hover:text-cyan-300 hover:underline"
                >
                    <span>{{
                        isRTL
                            ? 'فتح صفحة الماسح المتقدمة'
                            : 'Advanced Scanner Studio'
                    }}</span>
                    <ExternalLink class="h-3.5 w-3.5" />
                </button>
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
    </div>
</template>
