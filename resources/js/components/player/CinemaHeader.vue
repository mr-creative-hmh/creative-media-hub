<script setup lang="ts">
import { computed } from 'vue';
import { ArrowLeft, X, Sparkles, Tv, Film, Wand2 } from '@lucide/vue';
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
    return (
        props.item?.release_year ||
        props.item?.year ||
        (props.item?.series?.release_year ?? '')
    );
});
</script>

<template>
    <div
        class="pointer-events-auto absolute inset-x-0 top-0 z-30 flex items-center justify-between bg-gradient-to-b from-black/90 via-black/50 to-transparent p-4 transition-all duration-300 sm:p-6"
        :class="{
            'pointer-events-none -translate-y-4 opacity-0': !isControlsVisible,
        }"
    >
        <!-- Left: Back Button & Title -->
        <div class="flex min-w-0 items-center gap-3 sm:gap-4">
            <button
                @click="emit('close')"
                class="rounded-full bg-white/10 p-2 text-white backdrop-blur-md transition-all hover:bg-white/20 active:scale-95 sm:p-2.5"
                :title="t('common.back') || 'Back'"
            >
                <ArrowLeft class="h-5 w-5" />
            </button>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span
                        v-if="isEpisode"
                        class="flex items-center gap-1 rounded-md border border-purple-500/30 bg-purple-500/20 px-2 py-0.5 text-[10px] font-bold text-purple-300"
                    >
                        <Tv class="h-2.5 w-2.5" />
                        {{ t('media.tv_show') || 'TV' }}
                    </span>
                    <span
                        v-else
                        class="flex items-center gap-1 rounded-md border border-cyan-500/30 bg-cyan-500/20 px-2 py-0.5 text-[10px] font-bold text-cyan-300"
                    >
                        <Film class="h-2.5 w-2.5" />
                        {{ t('media.movie') || 'Movie' }}
                    </span>
                    <span
                        v-if="displayYear"
                        class="text-xs font-semibold text-slate-400"
                        >{{ displayYear }}</span
                    >
                </div>
                <h1
                    class="max-w-xs truncate text-sm font-bold text-white sm:max-w-xl sm:text-lg"
                    :title="playerHeaderTitle"
                >
                    {{ playerHeaderTitle }}
                </h1>
            </div>
        </div>

        <!-- Right: Badges, Engine Toggle, Close -->
        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <!-- Resolution Badge -->
            <span
                v-if="liveResolution || displayResolution"
                class="rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-2 py-1 text-[10px] font-bold text-cyan-400 shadow-sm sm:text-xs"
            >
                {{ liveResolution || displayResolution }}
            </span>

            <!-- K-Lite Shader Preset Toggle -->
            <button
                @click="emit('toggle-shader')"
                class="flex cursor-pointer items-center gap-1.5 rounded-xl border px-2 py-1 text-[10px] font-bold backdrop-blur-md transition-all sm:px-2.5 sm:py-1.5 sm:text-xs"
                :class="
                    shaderPreset && shaderPreset !== 'standard'
                        ? 'border-purple-500/40 bg-purple-500/20 text-purple-300 shadow-md shadow-purple-500/10 hover:bg-purple-500/30'
                        : 'border-white/15 bg-white/10 text-slate-300 hover:bg-white/20'
                "
                title="Cycle K-Lite Visual Shader Preset (Standard / Clarity / HDR Vivid / Night Vision)"
            >
                <Wand2 class="h-3 w-3" />
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
                class="flex cursor-pointer items-center gap-1.5 rounded-xl border px-2 py-1 text-[10px] font-bold backdrop-blur-md transition-all sm:px-3 sm:py-1.5 sm:text-xs"
                :class="{
                    'border-cyan-500/40 bg-cyan-500/20 text-cyan-300 shadow-md shadow-cyan-500/10 hover:bg-cyan-500/30':
                        engineMode === 'web' || (!engineMode && isRemuxStream),
                    'border-amber-500/40 bg-amber-500/20 text-amber-300 shadow-md shadow-amber-500/10 hover:bg-amber-500/30':
                        engineMode === 'remux',
                    'border-emerald-500/40 bg-emerald-500/20 text-emerald-300 shadow-md shadow-emerald-500/10 hover:bg-emerald-500/30':
                        engineMode === 'direct' ||
                        (!engineMode && !isRemuxStream),
                }"
                :title="
                    engineMode === 'web'
                        ? 'Web Media Engine active (In-Browser MSE). Click to switch.'
                        : isRemuxStream
                          ? 'Server Remux active (AAC). Click to switch.'
                          : 'Direct Native Stream (Hardware). Click to switch.'
                "
            >
                <Sparkles class="h-3 w-3 text-cyan-400" />
                <span class="hidden sm:inline">
                    {{
                        engineMode === 'web'
                            ? 'WEB (MSE)'
                            : isRemuxStream
                              ? 'REMUX (AAC)'
                              : 'DIRECT (HW)'
                    }}
                </span>
                <span class="sm:hidden">
                    {{
                        engineMode === 'web'
                            ? 'MSE'
                            : isRemuxStream
                              ? 'AAC'
                              : 'HW'
                    }}
                </span>
            </button>

            <!-- Close Button -->
            <button
                @click="emit('close')"
                class="rounded-full bg-white/10 p-2 text-white backdrop-blur-md transition-all hover:bg-red-500/20 hover:text-red-400 active:scale-95 sm:p-2.5"
                :title="t('common.close') || 'Close'"
            >
                <X class="h-5 w-5" />
            </button>
        </div>
    </div>
</template>
