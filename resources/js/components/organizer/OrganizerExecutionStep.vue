<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Play,
    Pause,
    StopCircle,
    CheckCircle2,
    AlertTriangle,
    RefreshCw,
    Layers,
    ArrowLeft,
    ArrowRight,
} from 'lucide-vue-next';
import OrganizerLogViewer, {
    OrganizerLogItem,
} from '@/components/organizer/OrganizerLogViewer.vue';

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
    <div class="mx-auto max-w-4xl space-y-6">
        <!-- Progress Overview Card -->
        <div
            class="space-y-6 rounded-3xl border border-white/10 bg-slate-900/80 p-8 shadow-2xl backdrop-blur-2xl"
        >
            <!-- Header & Status Badge -->
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-extrabold text-white">
                        {{
                            isCompleted
                                ? isRTL
                                    ? 'اكتملت عملية التنظيم بنجاح!'
                                    : 'Organization Completed Successfully!'
                                : isRTL
                                  ? 'جاري نقل وتنظيم الملفات...'
                                  : 'Executing File Operations...'
                        }}
                    </h2>
                    <p class="mt-1 text-xs text-slate-400">
                        {{
                            isCompleted
                                ? isRTL
                                    ? 'تم نقل وإعادة تسمية كافة الملفات وفق الهيكل المعتمد'
                                    : 'All approved items have been placed in their destination library'
                                : isRTL
                                  ? 'يتم تطبيق النقل الفعلي وتحديث قاعدة البيانات'
                                  : 'Applying disk changes and updating library index'
                        }}
                    </p>
                </div>

                <!-- Status Pill -->
                <div
                    class="flex items-center gap-1.5 rounded-xl border px-3.5 py-1.5 text-xs font-bold tracking-wider uppercase"
                    :class="{
                        'border-cyan-500/40 bg-cyan-500/20 text-cyan-300':
                            isRunning,
                        'border-amber-500/40 bg-amber-500/20 text-amber-300':
                            isPaused,
                        'border-emerald-500/40 bg-emerald-500/20 text-emerald-300':
                            isCompleted,
                        'border-rose-500/40 bg-rose-500/20 text-rose-300':
                            props.status === 'error' ||
                            props.status === 'cancelled',
                    }"
                >
                    <span
                        class="h-2 w-2 animate-pulse rounded-full bg-current"
                    />
                    {{ props.status }}
                </div>
            </div>

            <!-- Big Progress Bar -->
            <div class="space-y-2">
                <div
                    class="flex items-center justify-between font-mono text-xs font-bold"
                >
                    <span class="text-slate-400">
                        {{ processedCount }} / {{ totalFiles }}
                        {{ isRTL ? 'ملف' : 'files' }}
                    </span>
                    <span class="text-sm font-black text-cyan-400"
                        >{{ Math.round(progressPercent) }}%</span
                    >
                </div>
                <div
                    class="relative h-3 w-full overflow-hidden rounded-full bg-white/10"
                >
                    <div
                        class="h-full rounded-full transition-all duration-300 ease-out"
                        :class="
                            isCompleted
                                ? 'bg-emerald-400'
                                : 'bg-gradient-to-r from-cyan-500 to-cyan-400'
                        "
                        :style="{ width: `${progressPercent}%` }"
                    />
                </div>
            </div>

            <!-- Current File Activity -->
            <div
                v-if="currentFile && !isCompleted"
                class="space-y-1 rounded-2xl border border-white/5 bg-white/5 p-3.5 font-mono text-xs"
            >
                <div
                    class="text-[10px] tracking-wider text-slate-500 uppercase"
                >
                    {{ currentAction || 'Processing' }}
                </div>
                <div
                    class="truncate font-semibold text-slate-300"
                    :title="currentFile"
                >
                    {{ currentFile }}
                </div>
            </div>

            <!-- Control Buttons -->
            <div class="flex items-center justify-between pt-2">
                <button
                    v-if="isCompleted"
                    @click="emit('reset')"
                    class="flex items-center gap-2 rounded-2xl bg-cyan-500 px-6 py-3 text-xs font-black text-slate-950 shadow-xl shadow-cyan-500/20 transition-all hover:bg-cyan-400"
                >
                    <RefreshCw class="h-4 w-4" />
                    {{
                        isRTL
                            ? 'بدء فحص أو تنظيم جديد'
                            : 'Start New Organizer Task'
                    }}
                </button>

                <div v-else class="flex items-center gap-3">
                    <button
                        v-if="isRunning"
                        @click="emit('pause')"
                        class="flex items-center gap-1.5 rounded-xl border border-white/10 bg-white/10 px-4 py-2.5 text-xs font-bold text-white transition-all hover:bg-white/15"
                    >
                        <Pause class="h-4 w-4" />
                        {{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}
                    </button>
                    <button
                        v-else-if="isPaused"
                        @click="emit('resume')"
                        class="flex items-center gap-1.5 rounded-xl border border-cyan-500/40 bg-cyan-500/20 px-4 py-2.5 text-xs font-bold text-cyan-300 transition-all hover:bg-cyan-500/30"
                    >
                        <Play class="h-4 w-4" />
                        {{ isRTL ? 'استئناف' : 'Resume' }}
                    </button>
                    <button
                        @click="emit('cancel')"
                        class="flex items-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-2.5 text-xs font-bold text-rose-300 transition-all hover:bg-rose-500/20"
                    >
                        <StopCircle class="h-4 w-4" />
                        {{ isRTL ? 'إلغاء العملية' : 'Cancel' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Real-Time Log Viewer -->
        <OrganizerLogViewer :logs="logs" />
    </div>
</template>
