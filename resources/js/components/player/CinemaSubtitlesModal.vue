<script setup lang="ts">
import {
    X,
    Check,
    Search,
    Type,
    Clock,
    RotateCcw,
    Sparkles,
    Loader2,
    Volume2,
} from '@lucide/vue';
import { useI18n } from '@/i18n/useI18n';
import type { SubtitleItem } from '@/composables/player/useCinemaSubtitles';

const props = defineProps<{
    isOpen: boolean;
    subtitles: SubtitleItem[];
    selectedId: number | string;
    delay: number;
    font: 'cairo' | 'jakarta' | 'system';
    fontSize: 'sm' | 'md' | 'lg' | 'xl';
    isTranslating?: boolean;
    audioTracks?: Array<{
        id: number | string;
        name: string;
        language?: string;
        isSelected?: boolean;
    }>;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'select', id: number | string): void;
    (e: 'adjust-delay', delta: number): void;
    (e: 'reset-delay'): void;
    (e: 'set-font', font: 'cairo' | 'jakarta' | 'system'): void;
    (e: 'set-size', size: 'sm' | 'md' | 'lg' | 'xl'): void;
    (e: 'open-search'): void;
    (e: 'translate'): void;
    (e: 'select-audio', id: number | string): void;
}>();

const { t } = useI18n();
</script>

<template>
    <div
        v-if="isOpen"
        class="animate-in fade-in absolute inset-0 z-40 flex items-center justify-center bg-black/60 p-4 backdrop-blur-md duration-200"
        @click.self="emit('close')"
    >
        <div
            class="w-full max-w-md space-y-6 overflow-hidden rounded-3xl border border-white/10 bg-slate-900/95 p-6 shadow-2xl"
        >
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/20 text-cyan-400"
                    >
                        <Type class="h-4 w-4" />
                    </div>
                    <h3 class="text-base font-bold text-white">
                        {{
                            t('player.subtitles_settings') ||
                            'Subtitles & Typography'
                        }}
                    </h3>
                </div>
                <button
                    @click="emit('close')"
                    class="rounded-xl p-2 text-slate-400 transition-all hover:bg-white/10 hover:text-white"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Track Selection List -->
            <div class="space-y-2">
                <label
                    class="block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                >
                    {{ t('player.select_track') || 'Select Subtitle Track' }}
                </label>
                <div class="max-h-44 space-y-1.5 overflow-y-auto pr-1">
                    <!-- Off Option -->
                    <button
                        @click="emit('select', 'off')"
                        class="flex w-full items-center justify-between rounded-xl border px-3.5 py-2.5 text-left text-xs font-bold transition-all"
                        :class="
                            selectedId === 'off'
                                ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300 shadow-sm'
                                : 'border-white/5 bg-white/5 text-slate-300 hover:bg-white/10'
                        "
                    >
                        <span>{{ t('player.subtitles_off') || 'Off' }}</span>
                        <Check
                            v-if="selectedId === 'off'"
                            class="h-4 w-4 text-cyan-400"
                        />
                    </button>

                    <!-- Available Tracks -->
                    <button
                        v-for="sub in subtitles"
                        :key="sub.id"
                        @click="emit('select', sub.id)"
                        class="flex w-full items-center justify-between rounded-xl border px-3.5 py-2.5 text-left text-xs font-bold transition-all"
                        :class="
                            String(selectedId) === String(sub.id)
                                ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300 shadow-sm'
                                : 'border-white/5 bg-white/5 text-slate-300 hover:bg-white/10'
                        "
                    >
                        <div class="flex items-center gap-2 truncate">
                            <span
                                class="shrink-0 rounded border px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                :class="
                                    ['ar', 'ara', 'arabic'].includes(
                                        (sub.language || '').toLowerCase(),
                                    )
                                        ? 'border-emerald-500/30 bg-emerald-500/20 text-emerald-300'
                                        : 'border-sky-500/30 bg-sky-500/20 text-sky-300'
                                "
                            >
                                {{ sub.language || 'sub' }}
                            </span>
                            <span class="truncate">{{
                                sub.language_name || sub.language || 'Subtitle'
                            }}</span>
                            <span
                                v-if="sub.source_type === 'ai_translated'"
                                class="shrink-0 rounded border border-emerald-500/30 bg-emerald-500/20 px-1.5 py-0.5 text-[10px] text-emerald-300"
                            >
                                AI Arabic
                            </span>
                            <span
                                v-else-if="
                                    sub.is_embedded ||
                                    sub.source_type === 'embedded'
                                "
                                class="shrink-0 rounded border border-purple-500/30 bg-purple-500/20 px-1.5 py-0.5 text-[10px] text-purple-300"
                            >
                                {{ t('player.embedded') || 'Embedded' }}
                            </span>
                            <span
                                v-else
                                class="shrink-0 rounded border border-cyan-500/30 bg-cyan-500/15 px-1.5 py-0.5 text-[10px] text-cyan-300"
                            >
                                {{ t('player.downloaded') || 'Downloaded' }}
                            </span>
                            <span
                                v-if="sub.format"
                                class="shrink-0 rounded bg-white/10 px-1.5 py-0.5 text-[10px] text-slate-300 uppercase"
                            >
                                {{ sub.format }}
                            </span>
                        </div>
                        <Check
                            v-if="String(selectedId) === String(sub.id)"
                            class="h-4 w-4 shrink-0 text-cyan-400"
                        />
                    </button>
                </div>
            </div>

            <!-- Subtitle Delay Sync -->
            <div class="space-y-2 border-t border-white/10 pt-2">
                <div class="flex items-center justify-between text-xs">
                    <span
                        class="flex items-center gap-1.5 font-semibold tracking-wider text-slate-400 uppercase"
                    >
                        <Clock class="h-3.5 w-3.5 text-cyan-400" />
                        {{
                            t('player.subtitle_delay') || 'Subtitle Timing Sync'
                        }}
                    </span>
                    <span
                        class="font-mono font-bold"
                        :class="
                            delay === 0 ? 'text-slate-400' : 'text-cyan-400'
                        "
                    >
                        {{ delay > 0 ? `+${delay}s` : `${delay}s` }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="emit('adjust-delay', -0.5)"
                        class="rounded-xl border border-white/10 bg-white/5 px-2.5 py-1.5 text-xs font-bold text-white transition-all hover:bg-white/10"
                    >
                        -0.5s
                    </button>
                    <button
                        @click="emit('adjust-delay', -0.1)"
                        class="rounded-xl border border-white/10 bg-white/5 px-2.5 py-1.5 text-xs font-bold text-white transition-all hover:bg-white/10"
                    >
                        -0.1s
                    </button>
                    <button
                        @click="emit('reset-delay')"
                        class="flex flex-1 items-center justify-center gap-1 rounded-xl border border-white/10 bg-white/5 py-1.5 text-xs font-bold text-slate-400 transition-all hover:bg-white/10 hover:text-white"
                    >
                        <RotateCcw class="h-3 w-3" />
                        {{ t('player.reset') || 'Reset' }}
                    </button>
                    <button
                        @click="emit('adjust-delay', 0.1)"
                        class="rounded-xl border border-white/10 bg-white/5 px-2.5 py-1.5 text-xs font-bold text-white transition-all hover:bg-white/10"
                    >
                        +0.1s
                    </button>
                    <button
                        @click="emit('adjust-delay', 0.5)"
                        class="rounded-xl border border-white/10 bg-white/5 px-2.5 py-1.5 text-xs font-bold text-white transition-all hover:bg-white/10"
                    >
                        +0.5s
                    </button>
                </div>
            </div>

            <!-- Typography & Sizing -->
            <div class="grid grid-cols-2 gap-4 border-t border-white/10 pt-2">
                <!-- Font Family -->
                <div class="space-y-2">
                    <label
                        class="block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                    >
                        {{ t('player.font_family') || 'Font' }}
                    </label>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button
                            v-for="f in ['cairo', 'jakarta', 'system'] as const"
                            :key="f"
                            @click="emit('set-font', f)"
                            class="rounded-lg border py-1.5 text-xs font-bold capitalize transition-all"
                            :class="
                                font === f
                                    ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300 shadow-sm'
                                    : 'border-white/5 bg-white/5 text-slate-400 hover:bg-white/10'
                            "
                        >
                            {{
                                f === 'cairo'
                                    ? 'Cairo'
                                    : f === 'jakarta'
                                      ? 'Jakarta'
                                      : 'System'
                            }}
                        </button>
                    </div>
                </div>

                <!-- Font Size -->
                <div class="space-y-2">
                    <label
                        class="block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                    >
                        {{ t('player.font_size') || 'Size' }}
                    </label>
                    <div class="grid grid-cols-4 gap-1">
                        <button
                            v-for="s in ['sm', 'md', 'lg', 'xl'] as const"
                            :key="s"
                            @click="emit('set-size', s)"
                            class="rounded-lg border py-1.5 text-xs font-bold uppercase transition-all"
                            :class="
                                fontSize === s
                                    ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300 shadow-sm'
                                    : 'border-white/5 bg-white/5 text-slate-400 hover:bg-white/10'
                            "
                        >
                            {{ s }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- AI Arabic Translator & Online Search Triggers -->
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <button
                    type="button"
                    @click="emit('translate')"
                    :disabled="props.isTranslating"
                    class="flex cursor-pointer items-center justify-center gap-1.5 rounded-xl border border-emerald-500/40 bg-emerald-600/20 px-3 py-2.5 text-xs font-bold text-emerald-300 transition-all hover:bg-emerald-600/30 disabled:opacity-50"
                >
                    <Loader2
                        v-if="props.isTranslating"
                        class="h-4 w-4 animate-spin text-emerald-400"
                    />
                    <Sparkles v-else class="h-4 w-4 text-emerald-400" />
                    <span>{{
                        props.isTranslating
                            ? t('player.translating') || 'Translating...'
                            : t('player.translate_now') || 'AI Arabic Translate'
                    }}</span>
                </button>

                <button
                    @click="emit('open-search')"
                    class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-cyan-500/30 bg-cyan-500/10 px-3 py-2.5 text-xs font-bold text-cyan-300 transition-all hover:bg-cyan-500/20"
                >
                    <Search class="h-4 w-4" />
                    {{ t('player.search_online_subtitles') || 'Search Online' }}
                </button>
            </div>

            <!-- Optional Audio Tracks Selection -->
            <div
                v-if="audioTracks && audioTracks.length > 1"
                class="space-y-2 border-t border-white/10 pt-2"
            >
                <label
                    class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-slate-400 uppercase"
                >
                    <Volume2 class="h-3.5 w-3.5 text-cyan-400" />
                    {{ t('player.audio_track') || 'Audio Track' }}
                </label>
                <div class="max-h-28 space-y-1 overflow-y-auto pr-1">
                    <button
                        v-for="track in audioTracks"
                        :key="track.id"
                        @click="emit('select-audio', track.id)"
                        class="flex w-full items-center justify-between rounded-lg border px-3 py-1.5 text-left text-xs font-bold transition-all"
                        :class="
                            track.isSelected
                                ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300'
                                : 'border-white/5 bg-white/5 text-slate-300 hover:bg-white/10'
                        "
                    >
                        <span>{{ track.name }}</span>
                        <Check
                            v-if="track.isSelected"
                            class="h-3.5 w-3.5 text-cyan-400"
                        />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
