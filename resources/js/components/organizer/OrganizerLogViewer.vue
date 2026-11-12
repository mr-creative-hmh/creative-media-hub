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

watch(() => props.logs.length, () => {
    nextTick(() => {
        if (logContainerRef.value) {
            logContainerRef.value.scrollTop = logContainerRef.value.scrollHeight;
        }
    });
});
</script>

<template>
    <div class="rounded-3xl bg-slate-950/80 border border-white/10 overflow-hidden shadow-2xl backdrop-blur-xl">
        <!-- Terminal Header -->
        <div
            @click="isExpanded = !isExpanded"
            class="px-5 py-3.5 bg-white/5 border-b border-white/10 flex items-center justify-between cursor-pointer select-none"
        >
            <div class="flex items-center gap-2.5 text-xs font-mono font-bold text-slate-300">
                <Terminal class="w-4 h-4 text-cyan-400" />
                <span>Execution Logs ({{ logs.length }})</span>
            </div>
            <button class="text-slate-400 hover:text-white transition-colors">
                <ChevronUp v-if="isExpanded" class="w-4 h-4" />
                <ChevronDown v-else class="w-4 h-4" />
            </button>
        </div>

        <!-- Terminal Body -->
        <div
            v-show="isExpanded"
            ref="logContainerRef"
            class="p-4 max-h-64 overflow-y-auto font-mono text-[11px] space-y-1 select-text scrollbar-thin"
        >
            <div v-if="logs.length === 0" class="text-slate-500 py-4 text-center">
                Waiting for operation logs...
            </div>
            <div
                v-for="(log, idx) in logs"
                :key="idx"
                class="flex items-start gap-2 leading-relaxed"
            >
                <span class="text-slate-500 shrink-0 font-mono">{{ log.time }}</span>
                <span
                    class="font-bold shrink-0 uppercase text-[9px] px-1 rounded"
                    :class="{
                        'bg-cyan-500/20 text-cyan-300': log.level === 'info',
                        'bg-emerald-500/20 text-emerald-300': log.level === 'success',
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

