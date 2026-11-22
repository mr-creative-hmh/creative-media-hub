<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Play,
    Clock,
    X,
    Tv,
    Film,
    Layers,
    Gamepad2,
    Subtitles,
} from '@lucide/vue';
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
    subtitles?: any[];
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
    return (
        props.item.type === 'episode' ||
        props.item.category === 'series' ||
        props.item.watchable_type === 'episode'
    );
});

const isCollection = computed(() => {
    return (
        props.item.category === 'collection' ||
        Boolean(props.item.collection_name)
    );
});

const isInteractive = computed(() => {
    return (
        props.item.id === 5764 ||
        props.item.watchable_id === 5764 ||
        (props.item.title && /bandersnatch/i.test(props.item.title))
    );
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
    const raw =
        isRTL.value && props.item.title_ar
            ? props.item.title_ar
            : props.item.title;
    const parts = (raw || '').split(/\s*-\s*(?:Season|الموسم|S\d+)/i);
    return parts[0]?.trim() || raw;
});

const seasonNum = computed(() => props.item.season_number ?? 1);
const episodeNum = computed(() => props.item.episode_number ?? 1);

const episodeCode = computed(() => {
    if (isRTL.value) {
        return `م${seasonNum.value} · ح${episodeNum.value}`;
    }
    return `S${seasonNum.value} · E${episodeNum.value}`;
});

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
    return isRTL.value
        ? `الحلقة ${episodeNum.value}`
        : `Episode ${episodeNum.value}`;
});

const mainTitle = computed(() => {
    if (isEpisode.value) {
        return seriesName.value || props.item.title;
    }
    return isRTL.value && props.item.title_ar
        ? props.item.title_ar
        : props.item.title;
});

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
        return isRTL.value
            ? `${props.item.remaining_formatted} متبقي`
            : `${props.item.remaining_formatted} left`;
    }

    if (props.item.duration_seconds && props.item.progress_seconds) {
        const remainingSec = Math.max(
            0,
            props.item.duration_seconds - props.item.progress_seconds,
        );
        const mins = Math.round(remainingSec / 60);
        if (mins > 60) {
            const hrs = Math.floor(mins / 60);
            const rMins = mins % 60;
            return isRTL.value
                ? `${hrs} س ${rMins} د`
                : `${hrs}h ${rMins}m left`;
        }
        return isRTL.value ? `${mins} د متبقية` : `${mins}m left`;
    }

    return null;
});

const subtitlesCount = computed(() => {
    return Array.isArray(props.item.subtitles)
        ? props.item.subtitles.length
        : 0;
});
</script>

<template>
    <div
        @click="emit('play', item, item.playlist)"
        class="cf-glass border-border/80 hover:border-primary/45 group relative flex cursor-pointer flex-col overflow-hidden rounded-2xl border transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl"
    >
        <!-- Thumbnail Media Frame (16:9 Aspect Ratio) -->
        <div class="bg-background relative aspect-video w-full overflow-hidden">
            <img
                :src="item.backdrop_path || item.poster_path"
                :alt="mainTitle"
                class="h-full w-full object-cover opacity-85 transition-transform duration-500 group-hover:scale-105 group-hover:opacity-100"
                loading="lazy"
            />

            <!-- Top Vignette Gradient -->
            <div
                class="pointer-events-none absolute inset-x-0 top-0 z-10 h-16 bg-gradient-to-b from-black/80 via-black/30 to-transparent"
            ></div>
            <!-- Bottom Vignette Gradient -->
            <div
                class="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-16 bg-gradient-to-t from-black/85 via-black/40 to-transparent"
            ></div>

            <!-- Center Hover Play Overlay (z-20) -->
            <div
                v-if="isInteractive"
                class="absolute inset-0 z-20 flex items-center justify-center gap-2 bg-black/40 opacity-0 backdrop-blur-xs transition-opacity group-hover:opacity-100"
            >
                <button
                    type="button"
                    @click.stop="emit('play', item, item.playlist)"
                    class="bg-primary text-primary-foreground shadow-primary/50 flex size-10 cursor-pointer items-center justify-center rounded-full shadow-xl transition-transform hover:scale-110 active:scale-95"
                    :title="isRTL ? 'مشاهدة عادية' : 'Normal Playback'"
                >
                    <Play class="ms-0.5 size-4 fill-current" />
                </button>
                <button
                    type="button"
                    @click.stop="(e) => emit('play-interactive', item, e)"
                    class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-gradient-to-r from-rose-600 to-red-500 px-2.5 py-1.5 text-xs font-bold text-white shadow-xl shadow-rose-600/50 transition-transform hover:scale-105 hover:from-rose-500 hover:to-red-400 active:scale-95"
                    :title="
                        isRTL
                            ? 'بدء التجربة التفاعلية'
                            : 'Launch Interactive Experience'
                    "
                >
                    <Gamepad2 class="size-3.5" />
                    <span>{{ isRTL ? 'تفاعلي' : 'Interactive' }}</span>
                </button>
            </div>
            <div
                v-else
                class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center bg-black/40 opacity-0 backdrop-blur-xs transition-opacity group-hover:opacity-100"
            >
                <div
                    class="bg-primary text-primary-foreground shadow-primary/50 pointer-events-auto flex size-11 items-center justify-center rounded-full shadow-xl transition-transform group-hover:scale-110 active:scale-95"
                >
                    <Play class="ms-0.5 size-4.5 fill-current" />
                </div>
            </div>

            <!-- Top Badges & Action Row (z-30, strictly above blur overlay) -->
            <div
                class="pointer-events-none absolute inset-x-2.5 top-2.5 z-30 flex items-center justify-between"
            >
                <!-- Left: Media Type / Episode / Collection Pill -->
                <div
                    class="pointer-events-auto flex flex-wrap items-center gap-1.5"
                >
                    <span
                        v-if="isInteractive"
                        class="pointer-events-auto flex animate-pulse items-center gap-1 rounded-md bg-gradient-to-r from-red-600 to-rose-500 px-2 py-0.5 text-[10px] font-black tracking-wider text-white uppercase shadow-lg shadow-rose-600/30 backdrop-blur-md"
                    >
                        <Gamepad2 class="size-3" />
                        <span>{{ isRTL ? 'تفاعلي' : 'Interactive' }}</span>
                    </span>

                    <span
                        v-else-if="isEpisode"
                        class="border-primary/35 bg-primary/90 text-primary-foreground pointer-events-auto flex items-center gap-1 rounded-md border px-2 py-0.5 font-mono text-[10px] font-black tracking-wider tabular-nums shadow-md backdrop-blur-md"
                    >
                        <Tv class="size-3" />
                        <span>{{ episodeCode }}</span>
                    </span>

                    <span
                        v-else-if="isCollection"
                        class="pointer-events-auto flex items-center gap-1 rounded-md bg-amber-500/90 px-2 py-0.5 text-[10px] font-black tracking-wider text-slate-950 uppercase shadow-md backdrop-blur-md"
                    >
                        <Layers class="size-3" />
                        <span class="max-w-[120px] truncate">{{
                            item.collection_name ||
                            t('watch_history.collections')
                        }}</span>
                    </span>

                    <span
                        v-else
                        class="border-primary/35 text-primary pointer-events-auto flex items-center gap-1 rounded-md border bg-black/70 px-2 py-0.5 text-[10px] font-bold tracking-wider backdrop-blur-md"
                    >
                        <Film class="size-3" />
                        <span>{{ isRTL ? 'فيلم' : 'Movie' }}</span>
                    </span>
                </div>

                <!-- Right: Remove / Delete Button (z-40) -->
                <button
                    type="button"
                    @click.stop="(e) => emit('remove', item, e)"
                    :title="t('watch_history.remove_tooltip')"
                    aria-label="Remove from history"
                    class="pointer-events-auto z-40 flex size-7 shrink-0 cursor-pointer items-center justify-center rounded-full border border-white/20 bg-black/80 text-slate-200 opacity-80 shadow-xl backdrop-blur-md transition-all group-hover:opacity-100 hover:border-rose-500 hover:bg-rose-600 hover:text-white active:scale-90 sm:opacity-0"
                >
                    <X class="size-3.5" />
                </button>
            </div>

            <!-- Floating Remaining Time Badge (Bottom-End of Thumbnail, z-30) -->
            <div
                v-if="remainingText"
                class="pointer-events-none absolute end-2.5 bottom-2.5 z-30"
            >
                <span
                    class="flex items-center gap-1 rounded-md border border-white/10 bg-black/80 px-2 py-0.5 font-mono text-[10px] font-bold text-slate-200 tabular-nums shadow-sm backdrop-blur-md"
                >
                    <Clock class="text-primary size-2.5" />
                    <span>{{ remainingText }}</span>
                </span>
            </div>

            <!-- Progress Bar at Base of Thumbnail (z-30) -->
            <div
                class="pointer-events-none absolute inset-x-0 bottom-0 z-30 h-1.5 bg-black/70"
            >
                <div
                    class="bg-primary h-full rounded-e-full shadow-sm transition-all duration-300"
                    :style="{ width: `${item.percent}%` }"
                ></div>
            </div>
        </div>

        <!-- Structured Info Body -->
        <div class="flex flex-1 flex-col justify-between gap-1.5 p-3.5">
            <!-- Line 1: Main Title (Series Name or Movie Name) -->
            <div class="flex min-w-0 items-center justify-between gap-2">
                <h4
                    class="text-foreground group-hover:text-primary flex-1 truncate text-sm font-black tracking-tight transition-colors"
                    :title="mainTitle"
                >
                    {{ mainTitle }}
                </h4>
                <div class="flex shrink-0 items-center gap-1">
                    <span
                        v-if="subtitlesCount > 0"
                        class="flex items-center gap-1 rounded border border-emerald-500/30 bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[9px] font-bold text-emerald-400 tabular-nums"
                        :title="
                            isRTL
                                ? `يتوفر ${subtitlesCount} ملفات ترجمة`
                                : `${subtitlesCount} subtitle tracks available`
                        "
                    >
                        <Subtitles class="size-2.5" />
                        <span>{{ subtitlesCount }} CC</span>
                    </span>
                    <span
                        v-if="item.resolution"
                        class="border-border/80 bg-muted/40 text-primary shrink-0 rounded border px-1.5 py-0.5 font-mono text-[9px] tabular-nums"
                    >
                        {{ item.resolution }}
                    </span>
                </div>
            </div>

            <!-- Line 2: Dedicated Subtitle (Episode info or Collection info) -->
            <div
                v-if="isEpisode"
                class="flex min-w-0 items-center gap-1.5 text-xs"
            >
                <span
                    class="border-primary/25 bg-primary/15 text-primary inline-flex shrink-0 items-center rounded border px-1.5 py-0.5 font-mono text-[10px] font-bold tabular-nums"
                >
                    {{ episodeCode }}
                </span>
                <span
                    class="text-muted-foreground flex-1 truncate text-xs font-medium"
                    :title="episodeTitle"
                >
                    {{ episodeTitle }}
                </span>
            </div>

            <div
                v-else-if="isCollection"
                class="flex min-w-0 items-center gap-1.5 text-xs font-medium text-amber-400"
            >
                <Layers class="size-3.5 shrink-0" />
                <span class="truncate" :title="item.collection_name">
                    {{ item.collection_name }}
                </span>
            </div>

            <div
                v-else
                class="text-muted-foreground flex min-w-0 items-center gap-2 text-xs tabular-nums"
            >
                <span class="flex items-center gap-1">
                    <Film class="text-primary size-3" />
                    <span>{{ isRTL ? 'فيلم سينمائي' : 'Feature Film' }}</span>
                </span>
                <span v-if="item.release_year" class="text-muted-foreground/80">
                    • {{ item.release_year }}
                </span>
            </div>

            <!-- Line 3: Structured Footer with Timestamp & Percentage -->
            <div
                class="border-border/60 mt-1.5 flex items-center justify-between border-t pt-2 text-[11px] tabular-nums"
            >
                <div
                    class="text-muted-foreground flex items-center gap-1.5 font-mono text-[11px]"
                >
                    <span class="text-primary font-semibold">{{
                        item.current_time_formatted
                    }}</span>
                    <span class="opacity-50">/</span>
                    <span>{{ item.duration_formatted }}</span>
                </div>

                <div class="flex items-center gap-1.5">
                    <span
                        class="border-border/80 bg-muted/40 text-foreground rounded border px-1.5 py-0.5 font-mono text-[10px] font-bold tabular-nums"
                    >
                        {{ item.percent }}%
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
