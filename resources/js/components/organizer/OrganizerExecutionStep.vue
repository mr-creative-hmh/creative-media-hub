<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { Play, Pause, StopCircle, RefreshCw } from '@lucide/vue';
import OrganizerLogViewer, {
    type OrganizerLogItem,
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

const { isRTL } = useI18n();

const isCompleted = computed(() => props.status === 'completed');
const isPaused = computed(() => props.status === 'paused');
const isRunning = computed(() => props.status === 'running');
</script>

<template>
    <div class="mx-auto max-w-4xl space-y-6">
        <!-- Progress Overview Card -->
        <div
            class="cf-glass-elevated border-border/80 space-y-6 rounded-3xl border p-8 shadow-2xl backdrop-blur-2xl"
        >
            <!-- Header & Status Badge -->
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-foreground text-xl font-extrabold">
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
                    <p class="text-muted-foreground mt-1 text-xs">
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
                        'border-primary/40 bg-primary/20 text-primary':
                            isRunning,
                        'border-amber-500/40 bg-amber-500/20 text-amber-300':
                            isPaused,
                        'border-emerald-500/40 bg-emerald-500/20 text-emerald-300':
                            isCompleted,
                        'border-destructive/40 bg-destructive/20 text-destructive':
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
                    class="flex items-center justify-between font-mono text-xs font-bold tabular-nums"
                >
                    <span class="text-muted-foreground">
                        {{ processedCount }} / {{ totalFiles }}
                        {{ isRTL ? 'ملف' : 'files' }}
                    </span>
                    <span class="text-primary text-sm font-black"
                        >{{ Math.round(progressPercent) }}%</span
                    >
                </div>
                <div
                    class="bg-muted/50 relative h-3 w-full overflow-hidden rounded-full"
                >
                    <div
                        class="h-full rounded-full transition-all duration-300 ease-out"
                        :class="isCompleted ? 'bg-emerald-400' : 'bg-primary'"
                        :style="{ width: `${progressPercent}%` }"
                    />
                </div>
            </div>

            <!-- Current File Activity -->
            <div
                v-if="currentFile && !isCompleted"
                class="cf-glass border-border/70 space-y-1 rounded-2xl border p-3.5 font-mono text-xs"
            >
                <div
                    class="text-muted-foreground text-[10px] tracking-wider uppercase"
                >
                    {{ currentAction || 'Processing' }}
                </div>
                <div
                    class="text-foreground truncate font-semibold"
                    :title="currentFile"
                >
                    {{ currentFile }}
                </div>
            </div>

            <!-- Control Buttons -->
            <div class="flex items-center justify-between pt-2">
                <button
                    v-if="isCompleted"
                    type="button"
                    @click="emit('reset')"
                    class="bg-primary text-primary-foreground shadow-primary/25 flex min-h-11 cursor-pointer items-center gap-2 rounded-2xl px-6 py-3 text-xs font-black shadow-xl transition-all hover:brightness-105 active:scale-95"
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
                        type="button"
                        @click="emit('pause')"
                        class="cf-glass border-border/80 text-foreground hover:border-primary/40 flex min-h-10 cursor-pointer items-center gap-1.5 rounded-xl border px-4 py-2.5 text-xs font-bold transition-all active:scale-95"
                    >
                        <Pause class="h-4 w-4" />
                        {{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}
                    </button>
                    <button
                        v-else-if="isPaused"
                        type="button"
                        @click="emit('resume')"
                        class="border-primary/40 bg-primary/20 text-primary hover:bg-primary/30 flex min-h-10 cursor-pointer items-center gap-1.5 rounded-xl border px-4 py-2.5 text-xs font-bold transition-all active:scale-95"
                    >
                        <Play class="h-4 w-4" />
                        {{ isRTL ? 'استئناف' : 'Resume' }}
                    </button>
                    <button
                        type="button"
                        @click="emit('cancel')"
                        class="border-destructive/35 bg-destructive/15 text-destructive hover:bg-destructive/25 flex min-h-10 cursor-pointer items-center gap-1.5 rounded-xl border px-4 py-2.5 text-xs font-bold transition-all active:scale-95"
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
