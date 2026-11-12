<script setup lang="ts">
import { computed } from 'vue';
import { X, Activity, Server, Cpu, HardDrive } from 'lucide-vue-next';
import { useI18n } from '@/i18n/useI18n';

const props = defineProps<{
    isOpen: boolean;
    item: any;
    isRemuxStream: boolean;
    streamUrl: string;
    currentTime: number;
    duration: number;
    bufferedPercent: number;
    liveResolution: string;
    displayCodec: string;
    displayAudio: string;
    remuxStartOffset: number;
    audioDelayMs: number;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const { t } = useI18n();
</script>

<template>
    <div
        v-if="isOpen"
        class="absolute top-20 right-4 sm:right-6 w-80 sm:w-96 rounded-2xl bg-black/85 border border-cyan-500/30 shadow-2xl p-4 text-xs font-mono text-slate-300 backdrop-blur-xl z-40 space-y-3 pointer-events-auto"
    >
        <!-- Header -->
        <div class="flex items-center justify-between pb-2 border-b border-white/10">
            <div class="flex items-center gap-2 text-cyan-400 font-bold">
                <Activity class="w-4 h-4" />
                <span>Stream Diagnostics HUD</span>
            </div>
            <button
                @click="emit('close')"
                class="p-1 rounded-lg hover:bg-white/10 text-slate-400 hover:text-white transition-colors"
            >
                <X class="w-4 h-4" />
            </button>
        </div>

        <!-- Metrics Grid -->
        <div class="space-y-1.5 text-[11px]">
            <div class="flex justify-between">
                <span class="text-slate-500">Pipeline Engine:</span>
                <span :class="isRemuxStream ? 'text-amber-400 font-bold' : 'text-emerald-400 font-bold'">
                    {{ isRemuxStream ? 'FFmpeg Remux (AAC Transcode)' : 'Native Direct (HTTP 206)' }}
                </span>
            </div>

            <div class="flex justify-between">
                <span class="text-slate-500">Resolution (Live):</span>
                <span class="text-white font-bold">{{ liveResolution || 'Detecting...' }}</span>
            </div>

            <div class="flex justify-between">
                <span class="text-slate-500">Video Codec:</span>
                <span class="text-white">{{ displayCodec }}</span>
            </div>

            <div class="flex justify-between">
                <span class="text-slate-500">Audio Codec:</span>
                <span class="text-white">{{ displayAudio }}</span>
            </div>

            <div class="flex justify-between">
                <span class="text-slate-500">Timeline PTS:</span>
                <span class="text-cyan-300 font-bold">{{ currentTime.toFixed(2) }}s / {{ duration.toFixed(2) }}s</span>
            </div>

            <div v-if="isRemuxStream" class="flex justify-between">
                <span class="text-slate-500">Remux Start Offset:</span>
                <span class="text-amber-300 font-bold">{{ remuxStartOffset.toFixed(2) }}s</span>
            </div>

            <div class="flex justify-between">
                <span class="text-slate-500">Audio Delay (DSP):</span>
                <span :class="audioDelayMs === 0 ? 'text-slate-400' : 'text-cyan-300 font-bold'">
                    {{ audioDelayMs }}ms
                </span>
            </div>

            <div class="flex justify-between">
                <span class="text-slate-500">Buffer Progress:</span>
                <span class="text-emerald-300">{{ bufferedPercent.toFixed(1) }}%</span>
            </div>

            <div class="pt-2 border-t border-white/10 text-[10px] text-slate-500 truncate" :title="streamUrl">
                <span class="block font-bold text-slate-400">Stream Source URL:</span>
                {{ streamUrl }}
            </div>
        </div>
    </div>
</template>

