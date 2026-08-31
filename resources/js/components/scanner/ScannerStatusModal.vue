<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { useScanner } from '@/composables/useScanner';
import { useI18n } from '@/i18n/useI18n';
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

const filteredLogs = computed(() => {
    const logs = scanStatus.value.logs || [];
    if (terminalFilter.value === 'all') return logs;
    return logs.filter((l) => l.level === terminalFilter.value);
});

const navigateToFullScanner = () => {
    closeScanModal();
    router.visit('/scanner');
};

const handleRescanFresh = async () => {
    if (confirm(isRTL.value ? 'هل أنت متأكد من رغبتك في إعادة فحص المكتبة بالكامل ومسح الفهارس السابقة؟' : 'Are you sure you want to wipe the previous scan and start a fresh library indexing?')) {
        await rescanFresh();
    }
};

const handleClearCatalog = async () => {
    if (confirm(isRTL.value ? 'تحذير: سيتم حذف كافة عناصر المكتبة المفهرسة من قاعدة البيانات (لن يتم حذف الملفات من القرص الصلب). هل تريد المتابعة؟' : 'Warning: This will remove all indexed movies and series from your library database (files on disk will NOT be deleted). Continue?')) {
        await clearCatalog();
        router.reload();
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

            <!-- Progress & Controls Section -->
            <div class="space-y-4 relative z-10">
                <!-- Progress Header -->
                <div class="flex items-center justify-between text-xs font-bold">
                    <span class="text-slate-300 flex items-center gap-1.5">
                        <RefreshCw v-if="isScanning" class="w-3.5 h-3.5 animate-spin text-cyan-400" />
                        <span class="text-white">{{ isRTL ? 'نسبة الإنجاز' : 'Scan Progress' }}: {{ scanStatus.progress_percent || 0 }}%</span>
                    </span>
                    <span class="text-cyan-400 font-mono">
                        {{ scanStatus.processed_files }} / {{ scanStatus.total_files }} {{ isRTL ? 'ملف' : 'files' }}
                    </span>
                </div>

                <!-- Progress Bar -->
                <div class="w-full h-3 rounded-full bg-white/5 border border-white/10 overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500 transition-all duration-300"
                        :style="{ width: `${scanStatus.progress_percent || 0}%` }"
                    ></div>
                </div>

                <!-- Current File Ticker -->
                <div class="p-3 rounded-xl bg-white/5 border border-white/10 flex items-center justify-between text-xs">
                    <span class="text-slate-400 shrink-0 font-medium">{{ isRTL ? 'الملف الحالي:' : 'Current Stream:' }}</span>
                    <span class="text-slate-200 font-mono truncate max-w-sm ml-2">
                        {{ scanStatus.current_file || (isRTL ? 'لا توجد عمليات جارية' : 'No active stream') }}
                    </span>
                </div>

                <!-- Action Controls -->
                <div class="flex items-center justify-between flex-wrap gap-2 pt-2">
                    <div class="flex items-center flex-wrap gap-2">
                        <button
                            v-if="isScanning"
                            @click="pauseScan"
                            class="px-4 py-2 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs font-bold flex items-center gap-1.5 cursor-pointer hover:bg-amber-500/30 active:scale-95"
                        >
                            <Pause class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                        </button>
                        <button
                            v-if="isPaused"
                            @click="resumeScan"
                            class="px-4 py-2 rounded-xl bg-cyan-500 text-slate-950 font-bold text-xs flex items-center gap-1.5 cursor-pointer hover:bg-cyan-400 active:scale-95"
                        >
                            <Play class="w-3.5 h-3.5 fill-current" />
                            <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                        </button>
                        <button
                            v-if="isScanning || isPaused"
                            @click="cancelScan"
                            class="px-4 py-2 rounded-xl bg-rose-500/20 text-rose-300 border border-rose-500/30 text-xs font-bold flex items-center gap-1.5 cursor-pointer hover:bg-rose-500/30 active:scale-95"
                        >
                            <XCircle class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'إلغاء' : 'Cancel' }}</span>
                        </button>
                        <button
                            v-if="!isScanning && !isPaused"
                            @click="startFullScan"
                            class="px-5 py-2 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-xs flex items-center gap-1.5 cursor-pointer shadow-lg shadow-cyan-500/20 active:scale-95"
                        >
                            <Play class="w-3.5 h-3.5 fill-current" />
                            <span>{{ isRTL ? 'بدء فحص شامل' : 'Start Full Scan' }}</span>
                        </button>

                        <button
                            @click="handleRescanFresh"
                            :disabled="isScanning"
                            class="px-3 py-2 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 text-xs font-bold flex items-center gap-1.5 cursor-pointer active:scale-95"
                            :title="isRTL ? 'إعادة الفحص من الصفر ومسح الفهارس السابقة' : 'Wipe previous scan and rescan'"
                        >
                            <RotateCcw class="w-3.5 h-3.5 text-indigo-400" />
                            <span>{{ isRTL ? 'إعادة الفحص' : 'Rescan Fresh' }}</span>
                        </button>

                        <button
                            @click="handleClearCatalog"
                            :disabled="isScanning"
                            class="px-3 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 text-xs font-bold flex items-center gap-1.5 cursor-pointer active:scale-95"
                            :title="isRTL ? 'تفريغ الفهارس من قاعدة البيانات' : 'Clear library catalog'"
                        >
                            <Trash2 class="w-3.5 h-3.5 text-rose-400" />
                            <span>{{ isRTL ? 'تفريغ' : 'Clear' }}</span>
                        </button>
                    </div>

                    <button
                        @click="navigateToFullScanner"
                        class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10 text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer"
                    >
                        <ExternalLink class="w-3.5 h-3.5 text-cyan-400" />
                        <span>{{ isRTL ? 'فتح مركز الفاحص الكامل' : 'Open Full Scanner Hub' }}</span>
                    </button>
                </div>
            </div>

            <!-- Mini Live Terminal Feed -->
            <div class="space-y-2 relative z-10 pt-2 border-t border-white/10">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 flex items-center gap-1.5">
                        <Terminal class="w-3.5 h-3.5 text-cyan-400" />
                        <span>{{ isRTL ? 'السجل المباشر للعمليات' : 'Live Event Terminal' }}</span>
                    </span>

                    <div class="flex items-center gap-1">
                        <button
                            v-for="flt in [
                                { id: 'all', label: isRTL ? 'الكل' : 'All' },
                                { id: 'success', label: isRTL ? 'نجاح' : 'Success' },
                                { id: 'error', label: isRTL ? 'أخطاء' : 'Errors' },
                            ]"
                            :key="flt.id"
                            @click="terminalFilter = flt.id as any"
                            class="px-2 py-0.5 rounded-lg text-[10px] font-bold transition-all cursor-pointer"
                            :class="terminalFilter === flt.id ? 'bg-cyan-500 text-slate-950' : 'text-slate-500 hover:text-white'"
                        >
                            {{ flt.label }}
                        </button>
                    </div>
                </div>

                <div
                    id="mini-terminal-feed"
                    class="bg-[#04060A] rounded-2xl p-3.5 font-mono text-[10px] h-36 overflow-y-auto space-y-1 border border-white/5 scroll-smooth"
                >
                    <div v-if="filteredLogs.length === 0" class="text-slate-600 text-center py-4">
                        {{ isRTL ? 'لا توجد سجلات حالية.' : 'No terminal events logged yet.' }}
                    </div>

                    <div
                        v-for="(log, lIdx) in filteredLogs"
                        :key="lIdx"
                        class="flex items-start gap-2 leading-relaxed"
                    >
                        <span class="text-slate-600 shrink-0">[{{ log.time }}]</span>
                        <span
                            class="font-bold shrink-0 uppercase text-[9px] px-1 rounded"
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
        </div>
    </div>
</template>
