<script setup lang="ts">
import { X, Check, Search, Type, Clock, RotateCcw, Sparkles, Loader2, Volume2 } from 'lucide-vue-next';
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
    audioTracks?: Array<{ id: number | string; name: string; language?: string; isSelected?: boolean }>;
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
        class="absolute inset-0 bg-black/60 backdrop-blur-md z-40 flex items-center justify-center p-4 animate-in fade-in duration-200"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-md rounded-3xl bg-slate-900/95 border border-white/10 shadow-2xl overflow-hidden p-6 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center border border-cyan-500/30">
                        <Type class="w-4 h-4" />
                    </div>
                    <h3 class="text-base font-bold text-white">
                        {{ t('player.subtitles_settings') || 'Subtitles & Typography' }}
                    </h3>
                </div>
                <button
                    @click="emit('close')"
                    class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 transition-all"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Track Selection List -->
            <div class="space-y-2">
                <label class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">
                    {{ t('player.select_track') || 'Select Subtitle Track' }}
                </label>
                <div class="max-h-44 overflow-y-auto space-y-1.5 pr-1">
                    <!-- Off Option -->
                    <button
                        @click="emit('select', 'off')"
                        class="w-full px-3.5 py-2.5 rounded-xl text-left text-xs font-bold transition-all flex items-center justify-between border"
                        :class="selectedId === 'off'
                            ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm'
                            : 'bg-white/5 text-slate-300 border-white/5 hover:bg-white/10'"
                    >
                        <span>{{ t('player.subtitles_off') || 'Off' }}</span>
                        <Check v-if="selectedId === 'off'" class="w-4 h-4 text-cyan-400" />
                    </button>

                    <!-- Available Tracks -->
                    <button
                        v-for="sub in subtitles"
                        :key="sub.id"
                        @click="emit('select', sub.id)"
                        class="w-full px-3.5 py-2.5 rounded-xl text-left text-xs font-bold transition-all flex items-center justify-between border"
                        :class="String(selectedId) === String(sub.id)
                            ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm'
                            : 'bg-white/5 text-slate-300 border-white/5 hover:bg-white/10'"
                    >
                        <div class="flex items-center gap-2 truncate">
                            <span
                                class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase border shrink-0"
                                :class="['ar', 'ara', 'arabic'].includes((sub.language || '').toLowerCase())
                                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                                    : 'bg-sky-500/20 text-sky-300 border-sky-500/30'"
                            >
                                {{ sub.language || 'sub' }}
                            </span>
                            <span class="truncate">{{ sub.language_name || sub.language || 'Subtitle' }}</span>
                            <span
                                v-if="sub.source_type === 'ai_translated'"
                                class="px-1.5 py-0.5 rounded text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 shrink-0"
                            >
                                AI Arabic
                            </span>
                            <span
                                v-else-if="sub.is_embedded || sub.source_type === 'embedded'"
                                class="px-1.5 py-0.5 rounded text-[10px] bg-purple-500/20 text-purple-300 border border-purple-500/30 shrink-0"
                            >
                                {{ t('player.embedded') || 'Embedded' }}
                            </span>
                            <span
                                v-else
                                class="px-1.5 py-0.5 rounded text-[10px] bg-cyan-500/15 text-cyan-300 border border-cyan-500/30 shrink-0"
                            >
                                {{ t('player.downloaded') || 'Downloaded' }}
                            </span>
                            <span v-if="sub.format" class="px-1.5 py-0.5 rounded text-[10px] bg-white/10 text-slate-300 uppercase shrink-0">
                                {{ sub.format }}
                            </span>
                        </div>
                        <Check v-if="String(selectedId) === String(sub.id)" class="w-4 h-4 text-cyan-400 shrink-0" />
                    </button>
                </div>
            </div>

            <!-- Subtitle Delay Sync -->
            <div class="space-y-2 pt-2 border-t border-white/10">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <Clock class="w-3.5 h-3.5 text-cyan-400" />
                        {{ t('player.subtitle_delay') || 'Subtitle Timing Sync' }}
                    </span>
                    <span class="font-mono font-bold" :class="delay === 0 ? 'text-slate-400' : 'text-cyan-400'">
                        {{ delay > 0 ? `+${delay}s` : `${delay}s` }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="emit('adjust-delay', -0.5)"
                        class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white text-xs font-bold transition-all"
                    >
                        -0.5s
                    </button>
                    <button
                        @click="emit('adjust-delay', -0.1)"
                        class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white text-xs font-bold transition-all"
                    >
                        -0.1s
                    </button>
                    <button
                        @click="emit('reset-delay')"
                        class="flex-1 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-slate-400 hover:text-white text-xs font-bold transition-all flex items-center justify-center gap-1"
                    >
                        <RotateCcw class="w-3 h-3" />
                        {{ t('player.reset') || 'Reset' }}
                    </button>
                    <button
                        @click="emit('adjust-delay', 0.1)"
                        class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white text-xs font-bold transition-all"
                    >
                        +0.1s
                    </button>
                    <button
                        @click="emit('adjust-delay', 0.5)"
                        class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white text-xs font-bold transition-all"
                    >
                        +0.5s
                    </button>
                </div>
            </div>

            <!-- Typography & Sizing -->
            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-white/10">
                <!-- Font Family -->
                <div class="space-y-2">
                    <label class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">
                        {{ t('player.font_family') || 'Font' }}
                    </label>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button
                            v-for="f in (['cairo', 'jakarta', 'system'] as const)"
                            :key="f"
                            @click="emit('set-font', f)"
                            class="py-1.5 rounded-lg text-xs font-bold transition-all border capitalize"
                            :class="font === f ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm' : 'bg-white/5 text-slate-400 border-white/5 hover:bg-white/10'"
                        >
                            {{ f === 'cairo' ? 'Cairo' : f === 'jakarta' ? 'Jakarta' : 'System' }}
                        </button>
                    </div>
                </div>

                <!-- Font Size -->
                <div class="space-y-2">
                    <label class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">
                        {{ t('player.font_size') || 'Size' }}
                    </label>
                    <div class="grid grid-cols-4 gap-1">
                        <button
                            v-for="s in (['sm', 'md', 'lg', 'xl'] as const)"
                            :key="s"
                            @click="emit('set-size', s)"
                            class="py-1.5 rounded-lg text-xs font-bold transition-all border uppercase"
                            :class="fontSize === s ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm' : 'bg-white/5 text-slate-400 border-white/5 hover:bg-white/10'"
                        >
                            {{ s }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- AI Arabic Translator & Online Search Triggers -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <button
                    type="button"
                    @click="emit('translate')"
                    :disabled="props.isTranslating"
                    class="py-2.5 px-3 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/30 border border-emerald-500/40 text-emerald-300 text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                >
                    <Loader2 v-if="props.isTranslating" class="w-4 h-4 animate-spin text-emerald-400" />
                    <Sparkles v-else class="w-4 h-4 text-emerald-400" />
                    <span>{{ props.isTranslating ? (t('player.translating') || 'Translating...') : (t('player.translate_now') || 'AI Arabic Translate') }}</span>
                </button>

                <button
                    @click="emit('open-search')"
                    class="py-2.5 px-3 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer"
                >
                    <Search class="w-4 h-4" />
                    {{ t('player.search_online_subtitles') || 'Search Online' }}
                </button>
            </div>

            <!-- Optional Audio Tracks Selection -->
            <div v-if="audioTracks && audioTracks.length > 1" class="space-y-2 pt-2 border-t border-white/10">
                <label class="text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                    <Volume2 class="w-3.5 h-3.5 text-cyan-400" />
                    {{ t('player.audio_track') || 'Audio Track' }}
                </label>
                <div class="max-h-28 overflow-y-auto space-y-1 pr-1">
                    <button
                        v-for="track in audioTracks"
                        :key="track.id"
                        @click="emit('select-audio', track.id)"
                        class="w-full px-3 py-1.5 rounded-lg text-left text-xs font-bold transition-all flex items-center justify-between border"
                        :class="track.isSelected ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40' : 'bg-white/5 text-slate-300 border-white/5 hover:bg-white/10'"
                    >
                        <span>{{ track.name }}</span>
                        <Check v-if="track.isSelected" class="w-3.5 h-3.5 text-cyan-400" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

