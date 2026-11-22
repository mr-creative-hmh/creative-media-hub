<script setup lang="ts">
import { computed } from 'vue';
import { X, Activity, Server, Cpu, HardDrive } from '@lucide/vue';
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
        class="pointer-events-auto absolute top-20 right-4 z-40 w-80 space-y-3 rounded-2xl border border-cyan-500/30 bg-black/85 p-4 font-mono text-xs text-slate-300 shadow-2xl backdrop-blur-xl sm:right-6 sm:w-96"
    >
        <!-- Header -->
        <div
            class="flex items-center justify-between border-b border-white/10 pb-2"
        >
            <div class="flex items-center gap-2 font-bold text-cyan-400">
                <Activity class="h-4 w-4" />
                <span>Stream Diagnostics HUD</span>
            </div>
            <button
                @click="emit('close')"
                class="rounded-lg p-1 text-slate-400 transition-colors hover:bg-white/10 hover:text-white"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <!-- Metrics Grid -->
        <div class="space-y-1.5 text-[11px]">
            <div class="flex justify-between">
                <span class="text-slate-500">Pipeline Engine:</span>
                <span
                    :class="
                        isRemuxStream
                            ? 'font-bold text-amber-400'
                            : 'font-bold text-emerald-400'
                    "
                >
                    {{
                        isRemuxStream
                            ? 'FFmpeg Remux (AAC Transcode)'
                            : 'Native Direct (HTTP 206)'
                    }}
                </span>
            </div>

            <div class="flex justify-between">
                <span class="text-slate-500">Resolution (Live):</span>
                <span class="font-bold text-white">{{
                    liveResolution || 'Detecting...'
                }}</span>
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
                <span class="font-bold text-cyan-300"
                    >{{ currentTime.toFixed(2) }}s /
                    {{ duration.toFixed(2) }}s</span
                >
            </div>

            <div v-if="isRemuxStream" class="flex justify-between">
                <span class="text-slate-500">Remux Start Offset:</span>
                <span class="font-bold text-amber-300"
                    >{{ remuxStartOffset.toFixed(2) }}s</span
                >
            </div>

            <div class="flex justify-between">
                <span class="text-slate-500">Audio Delay (DSP):</span>
                <span
                    :class="
                        audioDelayMs === 0
                            ? 'text-slate-400'
                            : 'font-bold text-cyan-300'
                    "
                >
                    {{ audioDelayMs }}ms
                </span>
            </div>

            <div class="flex justify-between">
                <span class="text-slate-500">Buffer Progress:</span>
                <span class="text-emerald-300"
                    >{{ bufferedPercent.toFixed(1) }}%</span
                >
            </div>

            <div
                class="truncate border-t border-white/10 pt-2 text-[10px] text-slate-500"
                :title="streamUrl"
            >
                <span class="block font-bold text-slate-400"
                    >Stream Source URL:</span
                >
                {{ streamUrl }}
            </div>
        </div>
    </div>
</template>
