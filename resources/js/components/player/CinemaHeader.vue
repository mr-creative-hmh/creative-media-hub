<script setup lang="ts">
import { computed } from 'vue';
import { ArrowLeft, X, Sparkles, Tv, Film, Wand2 } from 'lucide-vue-next';
import { useI18n } from '@/i18n/useI18n';
import { formatEpisodeTitle } from '@/lib/mediaTitle';

const props = defineProps<{
    item: any;
    isEpisode: boolean;
    isRemuxStream: boolean;
    engineMode?: 'web' | 'remux' | 'direct';
    shaderPreset?: 'standard' | 'clarity' | 'vivid' | 'night';
    liveResolution?: string;
    displayResolution?: string;
    displayCodec?: string;
    displayAudio?: string;
    isControlsVisible: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'toggle-stream'): void;
    (e: 'toggle-shader'): void;
}>();

const { isRTL, t } = useI18n();

const playerHeaderTitle = computed(() => {
    if (props.isEpisode) {
        return formatEpisodeTitle(props.item, {
            isRTL: isRTL.value,
            includeSeriesName: true,
        });
    }

    if (isRTL.value && props.item?.title_ar) {
        return props.item.title_ar;
    }
    return props.item?.title || 'Media';
});

const displayYear = computed(() => {
    return props.item?.release_year || props.item?.year || (props.item?.series?.release_year ?? '');
});
</script>

<template>
    <div
        class="absolute top-0 inset-x-0 p-4 sm:p-6 bg-gradient-to-b from-black/90 via-black/50 to-transparent flex items-center justify-between z-30 transition-all duration-300 pointer-events-auto"
        :class="{ 'opacity-0 -translate-y-4 pointer-events-none': !isControlsVisible }"
    >
        <!-- Left: Back Button & Title -->
        <div class="flex items-center gap-3 sm:gap-4 min-w-0">
            <button
                @click="emit('close')"
                class="p-2 sm:p-2.5 rounded-full bg-white/10 hover:bg-white/20 text-white backdrop-blur-md transition-all active:scale-95"
                :title="t('common.back') || 'Back'"
            >
                <ArrowLeft class="w-5 h-5" />
            </button>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span v-if="isEpisode" class="px-2 py-0.5 rounded-md bg-purple-500/20 text-purple-300 text-[10px] font-bold border border-purple-500/30 flex items-center gap-1">
                        <Tv class="w-2.5 h-2.5" />
                        {{ t('media.tv_show') || 'TV' }}
                    </span>
                    <span v-else class="px-2 py-0.5 rounded-md bg-cyan-500/20 text-cyan-300 text-[10px] font-bold border border-cyan-500/30 flex items-center gap-1">
                        <Film class="w-2.5 h-2.5" />
                        {{ t('media.movie') || 'Movie' }}
                    </span>
                    <span v-if="displayYear" class="text-xs text-slate-400 font-semibold">{{ displayYear }}</span>
                </div>
                <h1 class="text-sm sm:text-lg font-bold text-white truncate max-w-xs sm:max-w-xl" :title="playerHeaderTitle">
                    {{ playerHeaderTitle }}
                </h1>
            </div>
        </div>

        <!-- Right: Badges, Engine Toggle, Close -->
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <!-- Resolution Badge -->
            <span
                v-if="liveResolution || displayResolution"
                class="px-2 py-1 rounded-lg bg-cyan-500/10 text-cyan-400 text-[10px] sm:text-xs font-bold border border-cyan-500/30 shadow-sm"
            >
                {{ liveResolution || displayResolution }}
            </span>

            <!-- K-Lite Shader Preset Toggle -->
            <button
                @click="emit('toggle-shader')"
                class="px-2 sm:px-2.5 py-1 sm:py-1.5 rounded-xl text-[10px] sm:text-xs font-bold transition-all border flex items-center gap-1.5 backdrop-blur-md cursor-pointer"
                :class="shaderPreset && shaderPreset !== 'standard'
                    ? 'bg-purple-500/20 text-purple-300 border-purple-500/40 hover:bg-purple-500/30 shadow-md shadow-purple-500/10'
                    : 'bg-white/10 text-slate-300 border-white/15 hover:bg-white/20'"
                title="Cycle K-Lite Visual Shader Preset (Standard / Clarity / HDR Vivid / Night Vision)"
            >
                <Wand2 class="w-3 h-3" />
                <span class="hidden md:inline">
                    {{
                        shaderPreset === 'clarity'
                            ? 'Sharpen'
                            : shaderPreset === 'vivid'
                                ? 'HDR Vivid'
                                : shaderPreset === 'night'
                                    ? 'Night'
                                    : 'Shader'
                    }}
                </span>
            </button>

            <!-- Stream Engine Mode Toggle -->
            <button
                @click="emit('toggle-stream')"
                class="px-2 sm:px-3 py-1 sm:py-1.5 rounded-xl text-[10px] sm:text-xs font-bold transition-all border flex items-center gap-1.5 backdrop-blur-md cursor-pointer"
                :class="{
                    'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 hover:bg-cyan-500/30 shadow-md shadow-cyan-500/10': engineMode === 'web' || (!engineMode && isRemuxStream),
                    'bg-amber-500/20 text-amber-300 border-amber-500/40 hover:bg-amber-500/30 shadow-md shadow-amber-500/10': engineMode === 'remux',
                    'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 hover:bg-emerald-500/30 shadow-md shadow-emerald-500/10': engineMode === 'direct' || (!engineMode && !isRemuxStream),
                }"
                :title="engineMode === 'web' ? 'Web Media Engine active (In-Browser MSE). Click to switch.' : (isRemuxStream ? 'Server Remux active (AAC). Click to switch.' : 'Direct Native Stream (Hardware). Click to switch.')"
            >
                <Sparkles class="w-3 h-3 text-cyan-400" />
                <span class="hidden sm:inline">
                    {{ engineMode === 'web' ? 'WEB (MSE)' : (isRemuxStream ? 'REMUX (AAC)' : 'DIRECT (HW)') }}
                </span>
                <span class="sm:hidden">
                    {{ engineMode === 'web' ? 'MSE' : (isRemuxStream ? 'AAC' : 'HW') }}
                </span>
            </button>

            <!-- Close Button -->
            <button
                @click="emit('close')"
                class="p-2 sm:p-2.5 rounded-full bg-white/10 hover:bg-red-500/20 hover:text-red-400 text-white backdrop-blur-md transition-all active:scale-95"
                :title="t('common.close') || 'Close'"
            >
                <X class="w-5 h-5" />
            </button>
        </div>
    </div>
</template>

