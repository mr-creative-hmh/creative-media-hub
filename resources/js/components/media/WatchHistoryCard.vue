<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { Play, Clock, X, Tv, Film, Layers, Gamepad2 } from 'lucide-vue-next';
import { getCleanEpisodeTitle } from '@/lib/mediaTitle';

export interface WatchHistoryItem {
    id: number;
    history_id?: number;
    watchable_id?: number;
    watchable_type?: string;
    type?: string;
    category?: string;
    title: string;
    title_ar?: string;
    series_title?: string;
    series_title_ar?: string;
    season_number?: number;
    episode_number?: number;
    episode_title?: string;
    episode_title_ar?: string;
    collection_name?: string;
    release_year?: number;
    duration_formatted?: string;
    current_time_formatted?: string;
    remaining_formatted?: string;
    duration_seconds?: number;
    progress_seconds?: number;
    percent?: number;
    resolution?: string;
    video_codec?: string;
    audio_codec?: string;
    backdrop_path?: string;
    poster_path?: string;
    playlist?: any[];
}

const props = defineProps<{
    item: WatchHistoryItem;
    accent?: 'cyan' | 'indigo' | 'amber' | 'auto';
}>();

const emit = defineEmits<{
    (e: 'play', item: WatchHistoryItem, playlist?: any[]): void;
    (e: 'remove', item: WatchHistoryItem, event: MouseEvent): void;
    (e: 'play-interactive', item: WatchHistoryItem, event: MouseEvent): void;
}>();

const { t, isRTL } = useI18n();

const isEpisode = computed(() => {
    return props.item.type === 'episode' ||
        props.item.category === 'series' ||
        props.item.watchable_type === 'episode';
});

const isCollection = computed(() => {
    return props.item.category === 'collection' || Boolean(props.item.collection_name);
});

const isInteractive = computed(() => {
    return props.item.id === 5764 ||
        props.item.watchable_id === 5764 ||
        (props.item.title && /bandersnatch/i.test(props.item.title));
});

// Series title extracted cleanly
const seriesName = computed(() => {
    if (!isEpisode.value) return '';
    if (isRTL.value && props.item.series_title_ar) {
        return props.item.series_title_ar;
    }
    if (props.item.series_title) {
        return props.item.series_title;
    }
    // Fallback: strip season/episode patterns from main title
    const raw = (isRTL.value && props.item.title_ar) ? props.item.title_ar : props.item.title;
    const parts = (raw || '').split(/\s*-\s*(?:Season|الموسم|S\d+)/i);
    return parts[0]?.trim() || raw;
});

// Season & Episode numbers
const seasonNum = computed(() => props.item.season_number ?? 1);
const episodeNum = computed(() => props.item.episode_number ?? 1);

// Episode code badge string: e.g. "S1 · E22" or "م1 · ح22"
const episodeCode = computed(() => {
    if (isRTL.value) {
        return `م${seasonNum.value} · ح${episodeNum.value}`;
    }
    return `S${seasonNum.value} · E${episodeNum.value}`;
});

// Clean Episode title
const episodeTitle = computed(() => {
    if (!isEpisode.value) return '';
    if (isRTL.value && props.item.episode_title_ar) {
        return props.item.episode_title_ar;
    }
    if (props.item.episode_title) {
        return props.item.episode_title;
    }
    const clean = getCleanEpisodeTitle(props.item, isRTL.value);
    if (clean) return clean;
    return isRTL.value ? `الحلقة ${episodeNum.value}` : `Episode ${episodeNum.value}`;
});

// Main title (Heading Line)
const mainTitle = computed(() => {
    if (isEpisode.value) {
        return seriesName.value || props.item.title;
    }
    return (isRTL.value && props.item.title_ar) ? props.item.title_ar : props.item.title;
});

// Remaining time string
const remainingText = computed(() => {
    if (props.item.remaining_formatted) {
        const parts = props.item.remaining_formatted.split(':');
        if (parts.length === 2) {
            const mins = parseInt(parts[0], 10);
            return isRTL.value ? `${mins} د متبقية` : `${mins}m left`;
        } else if (parts.length === 3) {
            const hrs = parseInt(parts[0], 10);
            const mins = parseInt(parts[1], 10);
            return isRTL.value ? `${hrs} س ${mins} د` : `${hrs}h ${mins}m left`;
        }
        return isRTL.value ? `${props.item.remaining_formatted} متبقي` : `${props.item.remaining_formatted} left`;
    }

    if (props.item.duration_seconds && props.item.progress_seconds) {
        const remainingSec = Math.max(0, props.item.duration_seconds - props.item.progress_seconds);
        const mins = Math.round(remainingSec / 60);
        if (mins > 60) {
            const hrs = Math.floor(mins / 60);
            const rMins = mins % 60;
            return isRTL.value ? `${hrs} س ${rMins} د` : `${hrs}h ${rMins}m left`;
        }
        return isRTL.value ? `${mins} د متبقية` : `${mins}m left`;
    }

    return null;
});

// Color gradient for the progress bar based on media type
const progressGradient = computed(() => {
    if (isEpisode.value) {
        return 'from-indigo-500 to-cyan-400';
    }
    if (isCollection.value) {
        return 'from-amber-400 to-orange-500';
    }
    return 'from-cyan-400 to-blue-500';
});
</script>

<template>
    <div
        @click="emit('play', item, item.playlist)"
        class="glass-panel group relative rounded-2xl overflow-hidden cursor-pointer border border-slate-200/80 dark:border-white/10 hover:border-cyan-500/40 hover:shadow-xl hover:shadow-cyan-500/10 transition-all duration-300 flex flex-col bg-white dark:bg-[#07090E]"
    >
        <!-- Thumbnail Media Frame (16:9 Aspect Ratio) -->
        <div class="relative aspect-video w-full overflow-hidden bg-slate-950">
            <img
                :src="item.backdrop_path || item.poster_path"
                :alt="mainTitle"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 opacity-85 group-hover:opacity-100"
                loading="lazy"
            />

            <!-- Top Vignette Gradient -->
            <div class="absolute inset-x-0 top-0 h-16 bg-gradient-to-b from-black/80 via-black/30 to-transparent pointer-events-none z-10"></div>
            <!-- Bottom Vignette Gradient -->
            <div class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/85 via-black/40 to-transparent pointer-events-none z-10"></div>

            <!-- Top Badges Row -->
            <div class="absolute top-2.5 inset-x-2.5 flex items-center justify-between z-20 pointer-events-none">
                <!-- Left: Media Type / Episode / Collection Pill -->
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span
                        v-if="isInteractive"
                        class="px-2 py-0.5 rounded-md bg-gradient-to-r from-red-600 to-rose-500 backdrop-blur-md text-white text-[10px] font-black uppercase tracking-wider flex items-center gap-1 shadow-lg shadow-rose-600/30 animate-pulse pointer-events-auto"
                    >
                        <Gamepad2 class="w-3 h-3" />
                        <span>{{ isRTL ? 'تفاعلي' : 'Interactive' }}</span>
                    </span>

                    <span
                        v-else-if="isEpisode"
                        class="px-2 py-0.5 rounded-md bg-indigo-600/90 backdrop-blur-md text-white text-[10px] font-mono font-black tracking-wider flex items-center gap-1 shadow-md border border-indigo-400/30 pointer-events-auto"
                    >
                        <Tv class="w-3 h-3" />
                        <span>{{ episodeCode }}</span>
                    </span>

                    <span
                        v-else-if="isCollection"
                        class="px-2 py-0.5 rounded-md bg-amber-500/90 backdrop-blur-md text-slate-950 text-[10px] font-black uppercase tracking-wider flex items-center gap-1 shadow-md pointer-events-auto"
                    >
                        <Layers class="w-3 h-3" />
                        <span class="max-w-[120px] truncate">{{ item.collection_name || t('watch_history.collections') }}</span>
                    </span>

                    <span
                        v-else
                        class="px-2 py-0.5 rounded-md bg-black/60 backdrop-blur-md text-cyan-300 text-[10px] font-bold tracking-wider flex items-center gap-1 border border-cyan-500/30 pointer-events-auto"
                    >
                        <Film class="w-3 h-3" />
                        <span>{{ isRTL ? 'فيلم' : 'Movie' }}</span>
                    </span>
                </div>

                <!-- Right: Remove Button -->
                <button
                    @click.stop="(e) => emit('remove', item, e)"
                    :title="t('watch_history.remove_tooltip')"
                    class="w-7 h-7 rounded-full bg-black/70 hover:bg-rose-600 text-slate-300 hover:text-white flex items-center justify-center transition-all opacity-0 group-hover:opacity-100 backdrop-blur-md border border-white/20 hover:border-rose-500 cursor-pointer shadow-lg active:scale-90 pointer-events-auto shrink-0"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
            </div>

            <!-- Floating Remaining Time Badge (Bottom-End of Thumbnail) -->
            <div
                v-if="remainingText"
                class="absolute bottom-2.5 end-2.5 z-20 pointer-events-none"
            >
                <span class="px-2 py-0.5 rounded-md bg-black/80 backdrop-blur-md text-[10px] font-mono font-bold text-slate-200 border border-white/10 shadow-sm flex items-center gap-1">
                    <Clock class="w-2.5 h-2.5 text-cyan-400" />
                    <span>{{ remainingText }}</span>
                </span>
            </div>

            <!-- Center Hover Play Overlay -->
            <div v-if="isInteractive" class="absolute inset-0 z-20 flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity bg-black/50 backdrop-blur-xs">
                <button
                    @click.stop="emit('play', item, item.playlist)"
                    class="w-10 h-10 rounded-full bg-cyan-500 hover:bg-cyan-400 text-slate-950 flex items-center justify-center shadow-xl shadow-cyan-500/50 hover:scale-110 active:scale-95 transition-transform cursor-pointer"
                    :title="isRTL ? 'مشاهدة عادية' : 'Normal Playback'"
                >
                    <Play class="w-4 h-4 fill-current ml-0.5" />
                </button>
                <button
                    @click.stop="(e) => emit('play-interactive', item, e)"
                    class="px-2.5 py-1.5 rounded-xl bg-gradient-to-r from-rose-600 to-red-500 hover:from-rose-500 hover:to-red-400 text-white flex items-center gap-1.5 shadow-xl shadow-rose-600/50 hover:scale-105 active:scale-95 transition-transform text-xs font-bold cursor-pointer"
                    :title="isRTL ? 'بدء التجربة التفاعلية' : 'Launch Interactive Experience'"
                >
                    <Gamepad2 class="w-3.5 h-3.5" />
                    <span>{{ isRTL ? 'تفاعلي' : 'Interactive' }}</span>
                </button>
            </div>
            <div v-else class="absolute inset-0 z-20 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40 backdrop-blur-xs">
                <div class="w-11 h-11 rounded-full bg-cyan-500 text-slate-950 flex items-center justify-center shadow-xl shadow-cyan-500/50 group-hover:scale-110 active:scale-95 transition-transform">
                    <Play class="w-4.5 h-4.5 fill-current ml-0.5" />
                </div>
            </div>

            <!-- Progress Bar at Base of Thumbnail -->
            <div class="absolute bottom-0 inset-x-0 h-1.5 bg-black/70 z-20">
                <div
                    class="h-full bg-gradient-to-r rounded-r-full shadow-sm transition-all duration-300"
                    :class="progressGradient"
                    :style="{ width: `${item.percent}%` }"
                ></div>
            </div>
        </div>

        <!-- Structured Info Body -->
        <div class="p-3.5 flex flex-col justify-between flex-1 gap-1.5">
            <!-- Line 1: Main Title (Series Name or Movie Name) -->
            <div class="flex items-center justify-between gap-2 min-w-0">
                <h4
                    class="font-black text-sm text-slate-900 dark:text-white truncate group-hover:text-cyan-400 transition-colors tracking-tight flex-1"
                    :title="mainTitle"
                >
                    {{ mainTitle }}
                </h4>
                <span
                    v-if="item.resolution"
                    class="px-1.5 py-0.2 rounded bg-slate-100 dark:bg-white/5 text-[9px] font-mono text-cyan-600 dark:text-cyan-300 border border-slate-200 dark:border-white/10 shrink-0"
                >
                    {{ item.resolution }}
                </span>
            </div>

            <!-- Line 2: Dedicated Subtitle (Episode info or Collection info) -->
            <!-- Case A: Episode with separate badge and episode name -->
            <div v-if="isEpisode" class="flex items-center gap-1.5 min-w-0 text-xs">
                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border border-indigo-500/25 font-mono text-[10px] font-bold shrink-0">
                    {{ episodeCode }}
                </span>
                <span
                    class="truncate font-medium text-slate-600 dark:text-slate-300 text-xs flex-1"
                    :title="episodeTitle"
                >
                    {{ episodeTitle }}
                </span>
            </div>

            <!-- Case B: Movie in Collection -->
            <div v-else-if="isCollection" class="flex items-center gap-1.5 min-w-0 text-xs text-amber-500 dark:text-amber-400 font-medium">
                <Layers class="w-3.5 h-3.5 shrink-0" />
                <span class="truncate" :title="item.collection_name">
                    {{ item.collection_name }}
                </span>
            </div>

            <!-- Case C: Standalone Movie -->
            <div v-else class="flex items-center gap-2 min-w-0 text-xs text-slate-500 dark:text-slate-400">
                <span class="flex items-center gap-1">
                    <Film class="w-3 h-3 text-cyan-500" />
                    <span>{{ isRTL ? 'فيلم سينمائي' : 'Feature Film' }}</span>
                </span>
                <span v-if="item.release_year" class="text-slate-400 dark:text-slate-500">
                    • {{ item.release_year }}
                </span>
            </div>

            <!-- Line 3: Structured Footer with Timestamp & Percentage -->
            <div class="flex items-center justify-between mt-1.5 pt-2 border-t border-slate-100 dark:border-white/5 text-[11px]">
                <div class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                    <span class="text-cyan-600 dark:text-cyan-400 font-semibold">{{ item.current_time_formatted }}</span>
                    <span class="text-slate-400 dark:text-slate-600">/</span>
                    <span class="text-slate-500 dark:text-slate-400">{{ item.duration_formatted }}</span>
                </div>

                <div class="flex items-center gap-1.5">
                    <span class="font-bold font-mono text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-white/5 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/5">
                        {{ item.percent }}%
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
