<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { useOrganizerPlan } from '@/composables/useOrganizerPlan';
import { useI18n } from '@/i18n/useI18n';
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
    Sparkles,
    FolderCheck,
    HardDrive,
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

watch(
    () => planJobStatus.value.logs?.length,
    async () => {
        await nextTick();
        const term = document.getElementById('organizer-terminal-feed');
        if (term) term.scrollTop = term.scrollHeight;
    },
);
</script>

<template>
    <div
        v-if="isPlanModalOpen"
        class="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-md transition-all duration-300"
        @click.self="closePlanModal"
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
                        <Sparkles
                            class="h-5 w-5"
                            :class="isAnalyzing ? 'animate-pulse' : ''"
                        />
                    </div>
                    <div>
                        <h2
                            class="flex items-center gap-2 text-base font-extrabold text-white"
                        >
                            <span>{{
                                isRTL
                                    ? 'مهمة تحليل وتنظيم الوسائط (الخلفية)'
                                    : 'Media Organizer Background Job'
                            }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase"
                                :class="{
                                    'animate-pulse border border-cyan-500/30 bg-cyan-500/20 text-cyan-400':
                                        isAnalyzing,
                                    'border border-amber-500/30 bg-amber-500/20 text-amber-400':
                                        isPaused,
                                    'border border-emerald-500/30 bg-emerald-500/20 text-emerald-400':
                                        isCompleted,
                                    'border border-rose-500/30 bg-rose-500/20 text-rose-400':
                                        planJobStatus.status === 'cancelled',
                                    'bg-white/10 text-slate-400':
                                        planJobStatus.status === 'idle',
                                }"
                            >
                                {{ planJobStatus.status }}
                            </span>
                        </h2>
                        <p class="text-xs text-slate-400">
                            {{
                                isRTL
                                    ? 'معالجة ذكية وتصنيف فوري للملفات دون انتهاء مهلة الطلب'
                                    : 'Background chunked analysis with zero request timeouts'
                            }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="closePlanModal"
                        class="cursor-pointer rounded-xl p-2 text-slate-400 transition-colors hover:bg-white/10 hover:text-white"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- Live Progress Bar Section -->
            <div class="relative z-10 space-y-2">
                <div
                    class="flex items-center justify-between font-mono text-xs"
                >
                    <span
                        class="flex items-center gap-2 font-bold text-slate-300"
                    >
                        <HardDrive class="h-3.5 w-3.5 text-cyan-400" />
                        <span
                            >{{ planJobStatus.processed_count }} /
                            {{ planJobStatus.total_files }}
                            {{ isRTL ? 'ملف' : 'files' }}</span
                        >
                    </span>
                    <span class="text-sm font-black text-cyan-400"
                        >{{ planJobStatus.progress_percent }}%</span
                    >
                </div>

                <div
                    class="h-3 w-full overflow-hidden rounded-full border border-white/10 bg-slate-950 p-0.5"
                >
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500 shadow-lg shadow-cyan-500/50 transition-all duration-300"
                        :style="{ width: `${planJobStatus.progress_percent}%` }"
                    ></div>
                </div>

                <!-- Current Action Banner -->
                <div
                    class="flex items-center justify-between truncate rounded-xl border border-white/10 bg-slate-950/80 p-2.5 font-mono text-xs text-slate-300"
                >
                    <div class="flex items-center gap-2 truncate">
                        <span
                            class="shrink-0 font-sans text-[10px] font-bold text-cyan-400 uppercase"
                        >
                            {{ isRTL ? 'النشاط:' : 'Action:' }}
                        </span>
                        <span class="truncate text-slate-200">
                            {{
                                planJobStatus.current_action ||
                                (isRTL ? 'جاري الانتظار...' : 'Waiting...')
                            }}
                        </span>
                    </div>
                    <span
                        v-if="planJobStatus.current_file"
                        class="max-w-[200px] shrink-0 truncate text-[11px] text-slate-400"
                    >
                        {{ planJobStatus.current_file }}
                    </span>
                </div>
            </div>

            <!-- Mini Terminal Log Feed (Matching Virtual Scanner) -->
            <div class="relative z-10 space-y-2">
                <div class="flex items-center justify-between">
                    <div
                        class="flex items-center gap-2 text-xs font-bold text-slate-300"
                    >
                        <Terminal class="h-4 w-4 text-cyan-400" />
                        <span>{{
                            isRTL ? 'سجل العمليات المباشر' : 'Live Analysis Log'
                        }}</span>
                    </div>

                    <!-- Filter Pills -->
                    <div
                        class="flex items-center gap-1 rounded-xl border border-white/10 bg-slate-950/80 p-1 text-[10px]"
                    >
                        <button
                            v-for="flt in [
                                'all',
                                'success',
                                'info',
                                'error',
                            ] as const"
                            :key="flt"
                            @click="terminalFilter = flt"
                            class="cursor-pointer rounded-lg px-2 py-0.5 capitalize transition-colors"
                            :class="
                                terminalFilter === flt
                                    ? 'bg-cyan-500 font-bold text-slate-950'
                                    : 'text-slate-400 hover:text-white'
                            "
                        >
                            {{ flt }}
                        </button>
                    </div>
                </div>

                <!-- Terminal Window -->
                <div
                    id="organizer-terminal-feed"
                    class="custom-scrollbar h-44 space-y-1.5 overflow-y-auto rounded-2xl border border-white/10 bg-slate-950 p-3 text-left font-mono text-[11px]"
                >
                    <div
                        v-if="filteredLogs.length === 0"
                        class="py-10 text-center text-slate-600"
                    >
                        {{
                            isRTL
                                ? 'لا توجد سجلات بعد...'
                                : 'No activity logged yet...'
                        }}
                    </div>

                    <div
                        v-for="(log, idx) in filteredLogs"
                        :key="idx"
                        class="flex items-start gap-2 leading-relaxed"
                    >
                        <span class="shrink-0 text-slate-600 select-none"
                            >[{{ log.time }}]</span
                        >
                        <span
                            class="py-0.2 shrink-0 rounded px-1 text-[9px] font-bold uppercase select-none"
                            :class="{
                                'bg-cyan-500/10 text-cyan-400':
                                    log.level === 'info',
                                'bg-emerald-500/10 text-emerald-400':
                                    log.level === 'success',
                                'bg-rose-500/10 text-rose-400':
                                    log.level === 'error',
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
            <div
                class="relative z-10 flex items-center justify-between border-t border-white/10 pt-4"
            >
                <div class="flex items-center gap-2">
                    <button
                        v-if="isAnalyzing"
                        @click="pausePlanJob"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/30 bg-amber-500/20 px-4 py-2 text-xs font-bold text-amber-300 transition-all hover:bg-amber-500/30"
                    >
                        <Pause class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                    </button>

                    <button
                        v-if="isPaused"
                        @click="resumePlanJob"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-cyan-500/30 bg-cyan-500/20 px-4 py-2 text-xs font-bold text-cyan-300 transition-all hover:bg-cyan-500/30"
                    >
                        <Play class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                    </button>

                    <button
                        v-if="isAnalyzing || isPaused"
                        @click="cancelPlanJob"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/20 px-4 py-2 text-xs font-bold text-rose-300 transition-all hover:bg-rose-500/30"
                    >
                        <XCircle class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'إلغاء المهمة' : 'Cancel' }}</span>
                    </button>
                </div>

                <div>
                    <button
                        v-if="isCompleted"
                        @click="handleViewPlan"
                        class="flex cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 px-6 py-2.5 text-xs font-black text-slate-950 shadow-lg shadow-cyan-500/25 transition-all hover:scale-105 hover:from-cyan-400 hover:to-blue-500"
                    >
                        <CheckCircle2 class="h-4 w-4 stroke-[3]" />
                        <span>{{
                            isRTL
                                ? 'استعراض خطة التنظيم المقترحة'
                                : 'View Organization Plan'
                        }}</span>
                    </button>

                    <button
                        v-else
                        @click="closePlanModal"
                        class="cursor-pointer rounded-xl bg-white/5 px-4 py-2 text-xs font-bold text-slate-400 transition-colors hover:bg-white/10 hover:text-white"
                    >
                        {{ isRTL ? 'إغلاق النافذة' : 'Close' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
