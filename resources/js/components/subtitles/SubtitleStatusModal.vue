<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { useSubtitleJob } from '@/composables/useSubtitleJob';
import { useI18n } from '@/i18n/useI18n';
import {
    ShieldCheck, Play, Pause, XCircle, X, Terminal,
    FileCheck2, AlertTriangle, Trash2, FileText, CheckCircle2,
    RefreshCw, Layers, ExternalLink
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
    cancelHealthJob
} = useSubtitleJob();

const { t, isRTL } = useI18n();

const terminalFilter = ref<'all' | 'success' | 'info' | 'error'>('all');

const filteredLogs = computed(() => {
    const logs = subtitleStatus.value.logs || [];
    if (terminalFilter.value === 'all') return logs;
    return logs.filter((l) => l.level === terminalFilter.value);
});

watch(() => subtitleStatus.value.logs?.length, async () => {
    await nextTick();
    const term = document.getElementById('subtitle-terminal-feed');
    if (term) term.scrollTop = term.scrollHeight;
});
</script>

<template>
    <div
        v-if="isSubtitleModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md transition-all duration-300 animate-in fade-in"
        @click.self="closeSubtitleModal"
    >
        <div class="relative w-full max-w-2xl rounded-3xl bg-[#080B12] border border-cyan-500/30 p-6 shadow-2xl shadow-cyan-500/10 space-y-6 overflow-hidden">
            <!-- Background Ambient Glow -->
            <div class="ambient-glow bg-cyan-500/15 w-80 h-80 -top-24 -right-24 pointer-events-none"></div>

            <!-- Modal Header -->
            <div class="flex items-center justify-between relative z-10 border-b border-white/10 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                        <ShieldCheck class="w-5 h-5" />
                    </div>
                    <div>
                        <h2 class="font-extrabold text-base text-white flex items-center gap-2">
                            <span>{{ isRTL ? 'فاحص ومطهر ملفات الترجمة المباشر' : 'Subtitle Health & Normalizer Studio' }}</span>
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase"
                                :class="{
                                    'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 animate-pulse': isSubtitleRunning,
                                    'bg-amber-500/20 text-amber-300 border border-amber-500/30': isSubtitlePaused,
                                    'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': isSubtitleCompleted,
                                    'bg-white/10 text-slate-400': subtitleStatus.status === 'idle' || subtitleStatus.status === 'cancelled',
                                }"
                            >
                                {{ subtitleStatus.status }}
                            </span>
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ isRTL ? 'فحص جودة الترميز، إصلاح UTF-8، وتوحيد لاحقات اللغات والتنظيف' : 'Background UTF-8 repair, stub cleaner, and language suffix standardizer.' }}
                        </p>
                    </div>
                </div>

                <button
                    @click="closeSubtitleModal"
                    class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white transition-colors cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Bento Stats Metrics Row -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 relative z-10">
                <div class="p-3 rounded-2xl bg-slate-950/60 border border-white/5">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">{{ isRTL ? 'الملفات المفحوصة' : 'Scanned Files' }}</span>
                    <span class="text-base font-black text-cyan-400 font-mono mt-0.5 block">
                        {{ subtitleStatus.processed_count }} / {{ subtitleStatus.total_files }}
                    </span>
                </div>
                <div class="p-3 rounded-2xl bg-slate-950/60 border border-white/5">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">{{ isRTL ? 'ترميز UTF-8 تم إصلاحه' : 'Fixed Encodings' }}</span>
                    <span class="text-base font-black text-emerald-400 font-mono mt-0.5 block">
                        {{ subtitleStatus.summary?.encoding_fixed_count || 0 }}
                    </span>
                </div>
                <div class="p-3 rounded-2xl bg-slate-950/60 border border-white/5">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">{{ isRTL ? 'ملفات وهمية حُذفت' : 'Stubs Cleaned' }}</span>
                    <span class="text-base font-black text-amber-400 font-mono mt-0.5 block">
                        {{ subtitleStatus.summary?.deleted_count || 0 }}
                    </span>
                </div>
                <div class="p-3 rounded-2xl bg-slate-950/60 border border-white/5">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">{{ isRTL ? 'تسميات تم توحيدها' : 'Renamed Standard' }}</span>
                    <span class="text-base font-black text-purple-400 font-mono mt-0.5 block">
                        {{ subtitleStatus.summary?.renamed_count || 0 }}
                    </span>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="space-y-1 relative z-10">
                <div class="w-full h-2.5 rounded-full bg-white/5 border border-white/10 overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500 transition-all duration-300"
                        :style="{ width: `${subtitleStatus.progress_percent || 0}%` }"
                    ></div>
                </div>
                <div class="flex justify-between text-[11px] font-mono text-slate-400">
                    <span class="truncate max-w-[320px]">{{ subtitleStatus.current_action || subtitleStatus.current_file || 'Waiting...' }}</span>
                    <span class="font-bold text-cyan-400">{{ subtitleStatus.progress_percent || 0 }}%</span>
                </div>
            </div>

            <!-- Live Terminal Output Feed -->
            <div class="relative z-10 rounded-2xl bg-black/60 border border-white/10 p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-white/5 pb-2.5">
                    <div class="flex items-center gap-2">
                        <Terminal class="w-3.5 h-3.5 text-cyan-400" />
                        <span class="text-xs font-bold text-slate-300">{{ isRTL ? 'سجل الفحص والتطهير المباشر' : 'Live Subtitle Audit Log' }}</span>
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

                <div id="subtitle-terminal-feed" class="h-44 overflow-y-auto font-mono text-[11px] space-y-1.5 pr-2 custom-scrollbar">
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
                        {{ isRTL ? 'لا توجد سجلات حالياً...' : 'Waiting for subtitle audit events...' }}
                    </div>
                </div>
            </div>

            <!-- Action Toolbar Footer -->
            <div class="flex items-center justify-between flex-wrap gap-3 pt-2 relative z-10 border-t border-white/10">
                <div class="flex items-center gap-2">
                    <!-- Pause Button -->
                    <button
                        v-if="isSubtitleRunning"
                        @click="pauseHealthJob"
                        class="px-4 py-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer"
                    >
                        <Pause class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}</span>
                    </button>

                    <!-- Resume Button -->
                    <button
                        v-if="isSubtitlePaused"
                        @click="resumeHealthJob"
                        class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-lg shadow-cyan-500/20"
                    >
                        <Play class="w-3.5 h-3.5 fill-current" />
                        <span>{{ isRTL ? 'استئناف' : 'Resume' }}</span>
                    </button>

                    <!-- Cancel Button -->
                    <button
                        v-if="isSubtitleRunning || isSubtitlePaused"
                        @click="cancelHealthJob"
                        class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/30 font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer"
                    >
                        <XCircle class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'إلغاء' : 'Cancel' }}</span>
                    </button>
                </div>

                <button
                    @click="closeSubtitleModal"
                    class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white text-xs font-bold transition-colors cursor-pointer"
                >
                    {{ isRTL ? 'إغلاق النافذة' : 'Close' }}
                </button>
            </div>
        </div>
    </div>
</template>
