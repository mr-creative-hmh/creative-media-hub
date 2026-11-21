<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { useSubtitleJob } from '@/composables/useSubtitleJob';
import { useI18n } from '@/i18n/useI18n';
import {
    ShieldCheck,
    Play,
    Pause,
    XCircle,
    X,
    Terminal,
    FileCheck2,
    AlertTriangle,
    Trash2,
    FileText,
    CheckCircle2,
    RefreshCw,
    Layers,
    ExternalLink,
} from 'lucide-vue-next';

const {
    isSubtitleModalOpen,
    subtitleStatus,
    isSubtitleRunning,
    isSubtitlePaused,
    isSubtitleCompleted,
    closeSubtitleModal,
    pauseHealthJob,
    resumeHealthJob,
    cancelHealthJob,
} = useSubtitleJob();

const { t, isRTL } = useI18n();

const terminalFilter = ref<'all' | 'success' | 'info' | 'error'>('all');

const filteredLogs = computed(() => {
    const logs = subtitleStatus.value.logs || [];
    if (terminalFilter.value === 'all') return logs;
    return logs.filter((l) => l.level === terminalFilter.value);
});

watch(
    () => subtitleStatus.value.logs?.length,
    async () => {
        await nextTick();
        const term = document.getElementById('subtitle-terminal-feed');
        if (term) term.scrollTop = term.scrollHeight;
    },
);
</script>

<template>
    <div
        v-if="isSubtitleModalOpen"
        class="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-md transition-all duration-300"
        @click.self="closeSubtitleModal"
    >
        <div
            class="relative w-full max-w-2xl space-y-6 overflow-hidden rounded-3xl border border-cyan-500/30 bg-[#080B12] p-6 shadow-2xl shadow-cyan-500/10"
        >
            <!-- Background Ambient Glow -->
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
                        <ShieldCheck class="h-5 w-5" />
                    </div>
                    <div>
                        <h2
                            class="flex items-center gap-2 text-base font-extrabold text-white"
                        >
                            <span>{{
                                isRTL
                                    ? 'فاحص ومطهر ملفات الترجمة المباشر'
                                    : 'Subtitle Health & Normalizer Studio'
                            }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase"
                                :class="{
                                    'animate-pulse border border-cyan-500/30 bg-cyan-500/20 text-cyan-300':
                                        isSubtitleRunning,
                                    'border border-amber-500/30 bg-amber-500/20 text-amber-300':
                                        isSubtitlePaused,
                                    'border border-emerald-500/30 bg-emerald-500/20 text-emerald-300':
                                        isSubtitleCompleted,
                                    'bg-white/10 text-slate-400':
                                        subtitleStatus.status === 'idle' ||
                                        subtitleStatus.status === 'cancelled',
                                }"
                            >
                                {{ subtitleStatus.status }}
                            </span>
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{
                                isRTL
                                    ? 'فحص جودة الترميز، إصلاح UTF-8، وتوحيد لاحقات اللغات والتنظيف'
                                    : 'Background UTF-8 repair, stub cleaner, and language suffix standardizer.'
                            }}
                        </p>
                    </div>
                </div>

                <button
                    @click="closeSubtitleModal"
                    class="cursor-pointer rounded-xl bg-white/5 p-2 text-slate-400 transition-colors hover:bg-white/10 hover:text-white"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Bento Stats Metrics Row -->
            <div class="relative z-10 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                <div
                    class="rounded-2xl border border-white/5 bg-slate-950/60 p-3"
                >
                    <span
                        class="block text-[10px] font-bold text-slate-500 uppercase"
                        >{{
                            isRTL ? 'الملفات المفحوصة' : 'Scanned Files'
                        }}</span
                    >
                    <span
                        class="mt-0.5 block font-mono text-base font-black text-cyan-400"
                    >
                        {{ subtitleStatus.processed_count }} /
                        {{ subtitleStatus.total_files }}
                    </span>
                </div>
                <div
                    class="rounded-2xl border border-white/5 bg-slate-950/60 p-3"
                >
                    <span
                        class="block text-[10px] font-bold text-slate-500 uppercase"
                        >{{
                            isRTL ? 'ترميز UTF-8 تم إصلاحه' : 'Fixed Encodings'
                        }}</span
                    >
                    <span
                        class="mt-0.5 block font-mono text-base font-black text-emerald-400"
                    >
                        {{ subtitleStatus.summary?.encoding_fixed_count || 0 }}
                    </span>
                </div>
                <div
                    class="rounded-2xl border border-white/5 bg-slate-950/60 p-3"
                >
                    <span
                        class="block text-[10px] font-bold text-slate-500 uppercase"
                        >{{
                            isRTL ? 'ملفات وهمية حُذفت' : 'Stubs Cleaned'
                        }}</span
                    >
                    <span
                        class="mt-0.5 block font-mono text-base font-black text-amber-400"
                    >
                        {{ subtitleStatus.summary?.deleted_count || 0 }}
                    </span>
                </div>
                <div
                    class="rounded-2xl border border-white/5 bg-slate-950/60 p-3"
                >
                    <span
                        class="block text-[10px] font-bold text-slate-500 uppercase"
                        >{{
                            isRTL ? 'تسميات تم توحيدها' : 'Renamed Standard'
                        }}</span
                    >
                    <span
                        class="mt-0.5 block font-mono text-base font-black text-purple-400"
                    >
                        {{ subtitleStatus.summary?.renamed_count || 0 }}
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
                            width: `${subtitleStatus.progress_percent || 0}%`,
                        }"
                    ></div>
                </div>
                <div
                    class="flex justify-between font-mono text-[11px] text-slate-400"
                >
                    <span class="max-w-[320px] truncate">{{
                        subtitleStatus.current_action ||
                        subtitleStatus.current_file ||
                        'Waiting...'
                    }}</span>
                    <span class="font-bold text-cyan-400"
                        >{{ subtitleStatus.progress_percent || 0 }}%</span
                    >
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
                            isRTL
                                ? 'سجل الفحص والتطهير المباشر'
                                : 'Live Subtitle Audit Log'
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
                    id="subtitle-terminal-feed"
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
                                : 'Waiting for subtitle audit events...'
                        }}
                    </div>
                </div>
            </div>

            <!-- Action Toolbar Footer -->
            <div
                class="relative z-10 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-2"
            >
                <div class="flex items-center gap-2">
                    <!-- Pause Button -->
                    <button
                        v-if="isSubtitleRunning"
                        @click="pauseHealthJob"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/30 bg-amber-500/20 px-4 py-2 text-xs font-bold text-amber-300 transition-all hover:bg-amber-500/30"
                    >
                        <Pause class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                    </button>

                    <!-- Resume Button -->
                    <button
                        v-if="isSubtitlePaused"
                        @click="resumeHealthJob"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-cyan-500 px-4 py-2 text-xs font-bold text-slate-950 shadow-lg shadow-cyan-500/20 transition-all hover:bg-cyan-400"
                    >
                        <Play class="h-3.5 w-3.5 fill-current" />
                        <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                    </button>

                    <!-- Cancel Button -->
                    <button
                        v-if="isSubtitleRunning || isSubtitlePaused"
                        @click="cancelHealthJob"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/20 px-4 py-2 text-xs font-bold text-rose-300 transition-all hover:bg-rose-500/30"
                    >
                        <XCircle class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'إلغاء' : 'Cancel' }}</span>
                    </button>
                </div>

                <button
                    @click="closeSubtitleModal"
                    class="cursor-pointer rounded-xl bg-white/5 px-4 py-2 text-xs font-bold text-slate-400 transition-colors hover:bg-white/10 hover:text-white"
                >
                    {{ isRTL ? 'إغلاق النافذة' : 'Close' }}
                </button>
            </div>
        </div>
    </div>
</template>
