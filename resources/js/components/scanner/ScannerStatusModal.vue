<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { useScanner } from '@/composables/useScanner';
import { useI18n } from '@/i18n/useI18n';
import ConfirmModal from '@/components/common/ConfirmModal.vue';
import {
    ScanLine, Play, Pause, XCircle, X, Terminal, RotateCcw,
    RefreshCw, CheckCircle2, AlertCircle, ExternalLink, HardDrive, Trash2
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
    cancelScan
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
        title: isRTL.value ? 'إعادة فحص شاملة للمكتبة' : 'Fresh Full Library Rescan',
        message: isRTL.value ? 'سيتم تصفير الفهارس السابقة وإعادة فحص وتحميل بيانات وأغلفة جميع المجلدات المراقبة من جديد.' : 'This will wipe the previous scan and start a fresh library indexing across all monitored folders.',
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
        title: isRTL.value ? 'مسح كافة فهارس المكتبة والإحصائيات' : 'Clear Library Catalog & Analytics',
        message: isRTL.value ? 'تحذير: سيتم حذف كافة عناصر المكتبة وسجل المشاهدة والإحصائيات من قاعدة البيانات (لن يتم حذف الملفات من القرص). هل تريد المتابعة؟' : 'Warning: This will remove all indexed movies, series, watch histories, and analytics metrics from your library database (files on disk will NOT be deleted). Continue?',
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

watch(() => scanStatus.value.logs?.length, async () => {
    await nextTick();
    const term = document.getElementById('mini-terminal-feed');
    if (term) term.scrollTop = term.scrollHeight;
});
</script>

<template>
    <div
        v-if="isScanModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md transition-all duration-300 animate-in fade-in"
        @click.self="closeScanModal"
    >
        <div class="relative w-full max-w-2xl rounded-3xl bg-[#080B12] border border-cyan-500/30 p-6 shadow-2xl shadow-cyan-500/10 space-y-6 overflow-hidden">
            <!-- Background Glow -->
            <div class="ambient-glow bg-cyan-500/15 w-80 h-80 -top-24 -right-24 pointer-events-none"></div>

            <!-- Modal Header -->
            <div class="flex items-center justify-between relative z-10 border-b border-white/10 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                        <ScanLine class="w-5 h-5" />
                    </div>
                    <div>
                        <h2 class="font-extrabold text-base text-white flex items-center gap-2">
                            <span>{{ isRTL ? 'فاحص المكتبة الافتراضي المباشر' : 'Virtual Library Scanner' }}</span>
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase"
                                :class="{
                                    'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 animate-pulse': isScanning,
                                    'bg-amber-500/20 text-amber-300 border border-amber-500/30': isPaused,
                                    'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': scanStatus.status === 'completed',
                                    'bg-white/10 text-slate-400': scanStatus.status === 'idle' || scanStatus.status === 'cancelled',
                                }"
                            >
                                {{ scanStatus.status }}
                            </span>
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ isRTL ? 'التحكم والمتابعة الفورية لفهرسة الأفلام والمسلسلات' : 'Universal background media stream indexer and metadata fetcher.' }}
                        </p>
                    </div>
                </div>

                <button
                    @click="closeScanModal"
                    class="w-8 h-8 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white flex items-center justify-center transition-all cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Active Scan Controls & Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 relative z-10">
                <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">{{ isRTL ? 'الحالة' : 'Status' }}</span>
                    <span class="text-xs font-black text-white mt-1 capitalize block">{{ scanStatus.status }}</span>
                </div>
                <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">{{ isRTL ? 'الملفات المنجزة' : 'Indexed' }}</span>
                    <span class="text-xs font-black text-cyan-400 mt-1 block">{{ scanStatus.processed_files }} / {{ scanStatus.total_files }}</span>
                </div>
                <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">{{ isRTL ? 'نسبة الإنجاز' : 'Progress' }}</span>
                    <span class="text-xs font-black text-emerald-400 mt-1 block">{{ scanStatus.progress_percent }}%</span>
                </div>
                <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">{{ isRTL ? 'أحدث ملف' : 'Current Stream' }}</span>
                    <span class="text-[11px] font-bold text-slate-300 mt-1 truncate block font-mono" :title="scanStatus.current_file || undefined">
                        {{ scanStatus.current_file ? scanStatus.current_file.split('/').pop() : '-' }}
                    </span>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="space-y-1 relative z-10">
                <div class="w-full h-2.5 rounded-full bg-white/5 border border-white/10 overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500 transition-all duration-300"
                        :style="{ width: `${scanStatus.progress_percent || 0}%` }"
                    ></div>
                </div>
            </div>

            <!-- Live Terminal Output Feed -->
            <div class="relative z-10 rounded-2xl bg-black/60 border border-white/10 p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-white/5 pb-2.5">
                    <div class="flex items-center gap-2">
                        <Terminal class="w-3.5 h-3.5 text-cyan-400" />
                        <span class="text-xs font-bold text-slate-300">{{ isRTL ? 'سجل الفحص المباشر' : 'Live Indexer Stream' }}</span>
                    </div>

                    <div class="flex items-center gap-1.5 text-[10px]">
                        <button
                            v-for="flt in ['all', 'success', 'info', 'error'] as const"
                            :key="flt"
                            @click="terminalFilter = flt"
                            class="px-2 py-0.5 rounded-lg uppercase font-bold transition-all cursor-pointer"
                            :class="terminalFilter === flt ? 'bg-cyan-500 text-slate-950 font-black' : 'bg-white/5 text-slate-400 hover:text-white'"
                        >
                            {{ flt }}
                        </button>
                    </div>
                </div>

                <div id="mini-terminal-feed" class="h-44 overflow-y-auto font-mono text-[11px] space-y-1.5 pr-2 custom-scrollbar">
                    <div
                        v-for="(log, idx) in filteredLogs"
                        :key="idx"
                        class="flex items-start gap-2 leading-relaxed"
                    >
                        <span class="text-slate-600 shrink-0 select-none">[{{ log.time }}]</span>
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
                    <div v-if="filteredLogs.length === 0" class="text-slate-600 italic py-6 text-center">
                        {{ isRTL ? 'لا توجد سجلات حالياً...' : 'Waiting for scanner events...' }}
                    </div>
                </div>
            </div>

            <!-- Action Toolbar Footer -->
            <div class="flex items-center justify-between flex-wrap gap-3 pt-2 relative z-10">
                <div class="flex items-center gap-2">
                    <button
                        v-if="scanStatus.status === 'idle' || scanStatus.status === 'completed' || scanStatus.status === 'cancelled'"
                        @click="() => startFullScan()"
                        class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-extrabold text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-lg shadow-cyan-500/20"
                    >
                        <Play class="w-3.5 h-3.5 fill-current" />
                        <span>{{ isRTL ? 'بدء الفحص' : 'Start Scan' }}</span>
                    </button>

                    <button
                        v-if="isScanning"
                        @click="pauseScan"
                        class="px-4 py-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer"
                    >
                        <Pause class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                    </button>

                    <button
                        v-if="isPaused"
                        @click="resumeScan"
                        class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-lg shadow-cyan-500/20"
                    >
                        <Play class="w-3.5 h-3.5 fill-current" />
                        <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                    </button>

                    <button
                        v-if="isScanning || isPaused"
                        @click="cancelScan"
                        class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/30 font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer"
                    >
                        <XCircle class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'إلغاء' : 'Cancel' }}</span>
                    </button>

                    <button
                        @click="handleRescanFresh"
                        :disabled="isScanning"
                        class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-amber-300 border border-white/10 hover:border-amber-500/40 font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer"
                        title="Wipe previous index and start fresh full scan"
                    >
                        <RotateCcw class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'فحص شامل وجديد' : 'Fresh Rescan' }}</span>
                    </button>

                    <button
                        @click="handleClearCatalog"
                        :disabled="isScanning"
                        class="px-3 py-2 rounded-xl bg-white/5 hover:bg-rose-500/20 text-rose-400 border border-white/10 hover:border-rose-500/40 font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer"
                        title="Clear library database and analytics"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'مسح الفهارس' : 'Clear' }}</span>
                    </button>
                </div>

                <button
                    @click="navigateToFullScanner"
                    class="flex items-center gap-1.5 text-xs font-bold text-cyan-400 hover:text-cyan-300 hover:underline cursor-pointer"
                >
                    <span>{{ isRTL ? 'فتح صفحة الماسح المتقدمة' : 'Advanced Scanner Studio' }}</span>
                    <ExternalLink class="w-3.5 h-3.5" />
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
