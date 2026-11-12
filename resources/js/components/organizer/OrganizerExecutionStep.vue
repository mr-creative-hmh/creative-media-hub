<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Play, Pause, StopCircle, CheckCircle2, AlertTriangle,
    RefreshCw, Layers, ArrowLeft, ArrowRight
} from 'lucide-vue-next';
import OrganizerLogViewer, { OrganizerLogItem } from '@/components/organizer/OrganizerLogViewer.vue';

const props = defineProps<{
    status: 'idle' | 'running' | 'paused' | 'completed' | 'cancelled' | 'error';
    progressPercent: number;
    totalFiles: number;
    processedCount: number;
    currentFile: string | null;
    currentAction: string | null;
    logs: OrganizerLogItem[];
}>();

const emit = defineEmits<{
    (e: 'pause'): void;
    (e: 'resume'): void;
    (e: 'cancel'): void;
    (e: 'reset'): void;
}>();

const { t, isRTL } = useI18n();

const isCompleted = computed(() => props.status === 'completed');
const isPaused = computed(() => props.status === 'paused');
const isRunning = computed(() => props.status === 'running');
</script>

<template>
    <div class="space-y-6 max-w-4xl mx-auto">
        <!-- Progress Overview Card -->
        <div class="p-8 rounded-3xl bg-slate-900/80 border border-white/10 backdrop-blur-2xl shadow-2xl space-y-6">
            <!-- Header & Status Badge -->
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-extrabold text-white">
                        {{ isCompleted
                            ? (isRTL ? 'اكتملت عملية التنظيم بنجاح!' : 'Organization Completed Successfully!')
                            : (isRTL ? 'جاري نقل وتنظيم الملفات...' : 'Executing File Operations...') }}
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        {{ isCompleted
                            ? (isRTL ? 'تم نقل وإعادة تسمية كافة الملفات وفق الهيكل المعتمد' : 'All approved items have been placed in their destination library')
                            : (isRTL ? 'يتم تطبيق النقل الفعلي وتحديث قاعدة البيانات' : 'Applying disk changes and updating library index') }}
                    </p>
                </div>

                <!-- Status Pill -->
                <div
                    class="px-3.5 py-1.5 rounded-xl font-bold text-xs border uppercase tracking-wider flex items-center gap-1.5"
                    :class="{
                        'bg-cyan-500/20 text-cyan-300 border-cyan-500/40': isRunning,
                        'bg-amber-500/20 text-amber-300 border-amber-500/40': isPaused,
                        'bg-emerald-500/20 text-emerald-300 border-emerald-500/40': isCompleted,
                        'bg-rose-500/20 text-rose-300 border-rose-500/40': props.status === 'error' || props.status === 'cancelled',
                    }"
                >
                    <span class="w-2 h-2 rounded-full bg-current animate-pulse" />
                    {{ props.status }}
                </div>
            </div>

            <!-- Big Progress Bar -->
            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs font-mono font-bold">
                    <span class="text-slate-400">
                        {{ processedCount }} / {{ totalFiles }} {{ isRTL ? 'ملف' : 'files' }}
                    </span>
                    <span class="text-cyan-400 text-sm font-black">{{ Math.round(progressPercent) }}%</span>
                </div>
                <div class="h-3 w-full rounded-full bg-white/10 overflow-hidden relative">
                    <div
                        class="h-full rounded-full transition-all duration-300 ease-out"
                        :class="isCompleted ? 'bg-emerald-400' : 'bg-gradient-to-r from-cyan-500 to-cyan-400'"
                        :style="{ width: `${progressPercent}%` }"
                    />
                </div>
            </div>

            <!-- Current File Activity -->
            <div v-if="currentFile && !isCompleted" class="p-3.5 rounded-2xl bg-white/5 border border-white/5 text-xs font-mono space-y-1">
                <div class="text-[10px] text-slate-500 uppercase tracking-wider">
                    {{ currentAction || 'Processing' }}
                </div>
                <div class="text-slate-300 truncate font-semibold" :title="currentFile">
                    {{ currentFile }}
                </div>
            </div>

            <!-- Control Buttons -->
            <div class="flex items-center justify-between pt-2">
                <button
                    v-if="isCompleted"
                    @click="emit('reset')"
                    class="px-6 py-3 rounded-2xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs shadow-xl shadow-cyan-500/20 transition-all flex items-center gap-2"
                >
                    <RefreshCw class="w-4 h-4" />
                    {{ isRTL ? 'بدء فحص أو تنظيم جديد' : 'Start New Organizer Task' }}
                </button>

                <div v-else class="flex items-center gap-3">
                    <button
                        v-if="isRunning"
                        @click="emit('pause')"
                        class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 border border-white/10 text-white font-bold text-xs transition-all flex items-center gap-1.5"
                    >
                        <Pause class="w-4 h-4" />
                        {{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}
                    </button>
                    <button
                        v-else-if="isPaused"
                        @click="emit('resume')"
                        class="px-4 py-2.5 rounded-xl bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 hover:bg-cyan-500/30 font-bold text-xs transition-all flex items-center gap-1.5"
                    >
                        <Play class="w-4 h-4" />
                        {{ isRTL ? 'استئناف' : 'Resume' }}
                    </button>
                    <button
                        @click="emit('cancel')"
                        class="px-4 py-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-300 font-bold text-xs transition-all flex items-center gap-1.5"
                    >
                        <StopCircle class="w-4 h-4" />
                        {{ isRTL ? 'إلغاء العملية' : 'Cancel' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Real-Time Log Viewer -->
        <OrganizerLogViewer :logs="logs" />
    </div>
</template>

