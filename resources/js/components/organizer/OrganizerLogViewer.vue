<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import { Terminal, ChevronDown, ChevronUp } from 'lucide-vue-next';
import { useI18n } from '@/i18n/useI18n';

export interface OrganizerLogItem {
    time: string;
    level: 'info' | 'success' | 'error';
    message: string;
}

const props = defineProps<{
    logs: OrganizerLogItem[];
}>();

const { isRTL } = useI18n();

const isExpanded = ref(true);
const logContainerRef = ref<HTMLDivElement | null>(null);

watch(
    () => props.logs.length,
    () => {
        nextTick(() => {
            if (logContainerRef.value) {
                logContainerRef.value.scrollTop =
                    logContainerRef.value.scrollHeight;
            }
        });
    },
);
</script>

<template>
    <div
        class="overflow-hidden rounded-3xl border border-white/10 bg-slate-950/80 shadow-2xl backdrop-blur-xl"
    >
        <!-- Terminal Header -->
        <div
            @click="isExpanded = !isExpanded"
            class="flex cursor-pointer items-center justify-between border-b border-white/10 bg-white/5 px-5 py-3.5 select-none"
        >
            <div
                class="flex items-center gap-2.5 font-mono text-xs font-bold text-slate-300"
            >
                <Terminal class="h-4 w-4 text-cyan-400" />
                <span>Execution Logs ({{ logs.length }})</span>
            </div>
            <button class="text-slate-400 transition-colors hover:text-white">
                <ChevronUp v-if="isExpanded" class="h-4 w-4" />
                <ChevronDown v-else class="h-4 w-4" />
            </button>
        </div>

        <!-- Terminal Body -->
        <div
            v-show="isExpanded"
            ref="logContainerRef"
            class="max-h-64 scrollbar-thin space-y-1 overflow-y-auto p-4 font-mono text-[11px] select-text"
        >
            <div
                v-if="logs.length === 0"
                class="py-4 text-center text-slate-500"
            >
                Waiting for operation logs...
            </div>
            <div
                v-for="(log, idx) in logs"
                :key="idx"
                class="flex items-start gap-2 leading-relaxed"
            >
                <span class="shrink-0 font-mono text-slate-500">{{
                    log.time
                }}</span>
                <span
                    class="shrink-0 rounded px-1 text-[9px] font-bold uppercase"
                    :class="{
                        'bg-cyan-500/20 text-cyan-300': log.level === 'info',
                        'bg-emerald-500/20 text-emerald-300':
                            log.level === 'success',
                        'bg-rose-500/20 text-rose-300': log.level === 'error',
                    }"
                >
                    {{ log.level }}
                </span>
                <span
                    class="break-all"
                    :class="{
                        'text-slate-300': log.level === 'info',
                        'text-emerald-200': log.level === 'success',
                        'text-rose-200': log.level === 'error',
                    }"
                >
                    {{ log.message }}
                </span>
            </div>
        </div>
    </div>
</template>
