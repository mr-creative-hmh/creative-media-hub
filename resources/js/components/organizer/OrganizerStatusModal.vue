<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { useOrganizerPlan } from '@/composables/useOrganizerPlan';
import { useI18n } from '@/i18n/useI18n';
import {
    ScanLine, Play, Pause, XCircle, X, Terminal, RotateCcw,
    RefreshCw, CheckCircle2, AlertCircle, Sparkles, FolderCheck, HardDrive
} from 'lucide-vue-next';

const emit = defineEmits<{
    (e: 'planReady', plan: any[]): void;
}>();

const {
    isPlanModalOpen,
    planJobStatus,
    isAnalyzing,
    isPaused,
    isCompleted,
    closePlanModal,
    pausePlanJob,
    resumePlanJob,
    cancelPlanJob,
} = useOrganizerPlan();

const { t, isRTL } = useI18n();

const terminalFilter = ref<'all' | 'success' | 'info' | 'error'>('all');

const filteredLogs = computed(() => {
    const logs = planJobStatus.value.logs || [];
    if (terminalFilter.value === 'all') return logs;
    return logs.filter((l) => l.level === terminalFilter.value);
});

const handleViewPlan = () => {
    emit('planReady', planJobStatus.value.plan_items || []);
    closePlanModal();
};

watch(() => planJobStatus.value.logs?.length, async () => {
    await nextTick();
    const term = document.getElementById('organizer-terminal-feed');
    if (term) term.scrollTop = term.scrollHeight;
});
</script>

<template>
    <div
        v-if="isPlanModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md transition-all duration-300 animate-in fade-in"
        @click.self="closePlanModal"
    >
        <div class="relative w-full max-w-2xl rounded-3xl bg-[#080B12] border border-cyan-500/30 p-6 shadow-2xl shadow-cyan-500/10 space-y-6 overflow-hidden">
            <!-- Background Glow -->
            <div class="ambient-glow bg-cyan-500/15 w-80 h-80 -top-24 -right-24 pointer-events-none"></div>

            <!-- Modal Header -->
            <div class="flex items-center justify-between relative z-10 border-b border-white/10 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                        <Sparkles class="w-5 h-5" :class="isAnalyzing ? 'animate-pulse' : ''" />
                    </div>
                    <div>
                        <h2 class="font-extrabold text-base text-white flex items-center gap-2">
                            <span>{{ isRTL ? 'مهمة تحليل وتنظيم الوسائط (الخلفية)' : 'Media Organizer Background Job' }}</span>
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase"
                                :class="{
                                    'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 animate-pulse': isAnalyzing,
                                    'bg-amber-500/20 text-amber-400 border border-amber-500/30': isPaused,
                                    'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30': isCompleted,
                                    'bg-rose-500/20 text-rose-400 border border-rose-500/30': planJobStatus.status === 'cancelled',
                                    'bg-white/10 text-slate-400': planJobStatus.status === 'idle',
                                }"
                            >
                                {{ planJobStatus.status }}
                            </span>
                        </h2>
                        <p class="text-xs text-slate-400">
                            {{ isRTL ? 'معالجة ذكية وتصنيف فوري للملفات دون انتهاء مهلة الطلب' : 'Background chunked analysis with zero request timeouts' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="closePlanModal"
                        class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Live Progress Bar Section -->
            <div class="space-y-2 relative z-10">
                <div class="flex items-center justify-between text-xs font-mono">
                    <span class="text-slate-300 font-bold flex items-center gap-2">
                        <HardDrive class="w-3.5 h-3.5 text-cyan-400" />
                        <span>{{ planJobStatus.processed_count }} / {{ planJobStatus.total_files }} {{ isRTL ? 'ملف' : 'files' }}</span>
                    </span>
                    <span class="text-cyan-400 font-black text-sm">{{ planJobStatus.progress_percent }}%</span>
                </div>

                <div class="w-full h-3 bg-slate-950 rounded-full overflow-hidden border border-white/10 p-0.5">
                    <div
                        class="h-full bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500 rounded-full transition-all duration-300 shadow-lg shadow-cyan-500/50"
                        :style="{ width: `${planJobStatus.progress_percent}%` }"
                    ></div>
                </div>

                <!-- Current Action Banner -->
                <div class="p-2.5 rounded-xl bg-slate-950/80 border border-white/10 text-xs font-mono flex items-center justify-between text-slate-300 truncate">
                    <div class="truncate flex items-center gap-2">
                        <span class="text-[10px] uppercase font-bold text-cyan-400 font-sans shrink-0">
                            {{ isRTL ? 'النشاط:' : 'Action:' }}
                        </span>
                        <span class="truncate text-slate-200">
                            {{ planJobStatus.current_action || (isRTL ? 'جاري الانتظار...' : 'Waiting...') }}
                        </span>
                    </div>
                    <span v-if="planJobStatus.current_file" class="text-[11px] text-slate-400 truncate max-w-[200px] shrink-0">
                        {{ planJobStatus.current_file }}
                    </span>
                </div>
            </div>

            <!-- Mini Terminal Log Feed (Matching Virtual Scanner) -->
            <div class="relative z-10 space-y-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-xs font-bold text-slate-300">
                        <Terminal class="w-4 h-4 text-cyan-400" />
                        <span>{{ isRTL ? 'سجل العمليات المباشر' : 'Live Analysis Log' }}</span>
                    </div>

                    <!-- Filter Pills -->
                    <div class="flex items-center gap-1 bg-slate-950/80 p-1 rounded-xl border border-white/10 text-[10px]">
                        <button
                            v-for="flt in (['all', 'success', 'info', 'error'] as const)"
                            :key="flt"
                            @click="terminalFilter = flt"
                            class="px-2 py-0.5 rounded-lg capitalize transition-colors cursor-pointer"
                            :class="terminalFilter === flt ? 'bg-cyan-500 text-slate-950 font-bold' : 'text-slate-400 hover:text-white'"
                        >
                            {{ flt }}
                        </button>
                    </div>
                </div>

                <!-- Terminal Window -->
                <div
                    id="organizer-terminal-feed"
                    class="h-44 overflow-y-auto rounded-2xl bg-slate-950 border border-white/10 p-3 font-mono text-[11px] space-y-1.5 custom-scrollbar text-left"
                >
                    <div v-if="filteredLogs.length === 0" class="text-slate-600 text-center py-10">
                        {{ isRTL ? 'لا توجد سجلات بعد...' : 'No activity logged yet...' }}
                    </div>

                    <div
                        v-for="(log, idx) in filteredLogs"
                        :key="idx"
                        class="flex items-start gap-2 leading-relaxed"
                    >
                        <span class="text-slate-600 shrink-0 select-none">[{{ log.time }}]</span>
                        <span
                            class="uppercase font-bold text-[9px] px-1 py-0.2 rounded shrink-0 select-none"
                            :class="{
                                'text-cyan-400 bg-cyan-500/10': log.level === 'info',
                                'text-emerald-400 bg-emerald-500/10': log.level === 'success',
                                'text-rose-400 bg-rose-500/10': log.level === 'error',
                            }"
                        >
                            {{ log.level }}
                        </span>
                        <span
                            class="break-all"
                            :class="{
                                'text-slate-300': log.level === 'info',
                                'text-emerald-300': log.level === 'success',
                                'text-rose-300': log.level === 'error',
                            }"
                        >
                            {{ log.message }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Action Controls (Matching ScannerStatusModal) -->
            <div class="flex items-center justify-between relative z-10 border-t border-white/10 pt-4">
                <div class="flex items-center gap-2">
                    <button
                        v-if="isAnalyzing"
                        @click="pausePlanJob"
                        class="px-4 py-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer"
                    >
                        <Pause class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                    </button>

                    <button
                        v-if="isPaused"
                        @click="resumePlanJob"
                        class="px-4 py-2 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/30 text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer"
                    >
                        <Play class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                    </button>

                    <button
                        v-if="isAnalyzing || isPaused"
                        @click="cancelPlanJob"
                        class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/30 text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer"
                    >
                        <XCircle class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'إلغاء المهمة' : 'Cancel' }}</span>
                    </button>
                </div>

                <div>
                    <button
                        v-if="isCompleted"
                        @click="handleViewPlan"
                        class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/25 flex items-center gap-2 transition-all cursor-pointer hover:scale-105"
                    >
                        <CheckCircle2 class="w-4 h-4 stroke-[3]" />
                        <span>{{ isRTL ? 'استعراض خطة التنظيم المقترحة' : 'View Organization Plan' }}</span>
                    </button>

                    <button
                        v-else
                        @click="closePlanModal"
                        class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white text-xs font-bold transition-colors cursor-pointer"
                    >
                        {{ isRTL ? 'إغلاق النافذة' : 'Close' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>