<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/components/layout/AppLayout.vue';
import FixMatchModal from '@/components/media/FixMatchModal.vue';
import { formatEpisodeTitle, getCleanEpisodeTitle } from '@/lib/mediaTitle';
import { useI18n } from '@/i18n/useI18n';
import {
    Play,
    Star,
    ArrowLeft,
    ArrowRight,
    Layers,
    Users,
    Subtitles,
    Film,
    Sparkles,
    SlidersHorizontal,
    Clock,
    Gamepad2,
} from 'lucide-vue-next';

const props = defineProps<{
    series: {
        id: number;
        title: string;
        title_ar?: string;
        overview?: string;
        overview_ar?: string;
        poster_path?: string;
        backdrop_path?: string;
        release_year?: number;
        end_year?: number;
        rating?: number;
        genres?: Array<{ id: number; name_en: string; name_ar?: string }>;
        seasons?: Array<{
            id: number;
            season_number: number;
            title?: string;
            title_ar?: string;
            episodes?: Array<{
                id: number;
                episode_number: number;
                title: string;
                title_ar?: string;
                overview?: string;
                overview_ar?: string;
                still_path?: string;
                air_date?: string;
                runtime_minutes?: number;
                resolution?: string;
                video_codec?: string;
                audio_codec?: string;
                subtitles?: any[];
            }>;
        }>;
    };
}>();

const { t, isRTL } = useI18n();

const selectedSeasonId = ref<number>(props.series.seasons?.[0]?.id || 0);
const showFixMatch = ref(false);

const selectedSeason = () => {
    return (
        props.series.seasons?.find((s) => s.id === selectedSeasonId.value) ||
        props.series.seasons?.[0]
    );
};

const handleMetadataUpdated = (updatedItem: any) => {
    Object.assign(props.series, updatedItem);
};

const isBandersnatch = (ep: any) => {
    return (
        ep?.id === 5764 ||
        (ep?.title && /bandersnatch/i.test(ep.title)) ||
        (ep?.episode_number === 1 &&
            selectedSeason()?.season_number === 0 &&
            /black mirror/i.test(props.series?.title || ''))
    );
};

const launchInteractiveBandersnatch = (ep: any, e?: Event) => {
    if (e) e.stopPropagation();
    window.dispatchEvent(
        new CustomEvent('play-bandersnatch', {
            detail: {
                ...ep,
                series: props.series,
                subtitles: ep.subtitles || [],
            },
        }),
    );
};

const playEpisode = (
    ep: any,
    playFn: (item: any, playlist?: any[]) => void,
) => {
    const seasons = (props.series.seasons || [])
        .slice()
        .sort(
            (a: any, b: any) => (a.season_number || 0) - (b.season_number || 0),
        );
    const allSeriesEps: any[] = [];
    seasons.forEach((season: any) => {
        const sNum = season.season_number || 1;
        const eps = (season.episodes || [])
            .slice()
            .sort(
                (a: any, b: any) =>
                    (a.episode_number || 0) - (b.episode_number || 0),
            );
        eps.forEach((e: any) => {
            allSeriesEps.push({
                ...e,
                id: e.id,
                type: 'episode',
                watchable_id: e.id,
                watchable_type: 'episode',
                series: props.series,
                series_id: props.series.id,
                season_number: sNum,
                episode_number: e.episode_number,
                runtime_minutes: e.runtime_minutes,
                duration_seconds:
                    e.duration_seconds ||
                    (e.runtime_minutes ? e.runtime_minutes * 60 : 0),
                resolution: e.resolution,
                video_codec: e.video_codec,
                audio_codec: e.audio_codec,
                subtitles: e.subtitles || [],
                title: e.title,
                title_ar: e.title_ar,
            });
        });
    });

    const playlist =
        allSeriesEps.length > 0
            ? allSeriesEps
            : (selectedSeason()?.episodes || []).map((e: any) => ({
                  ...e,
                  id: e.id,
                  type: 'episode',
                  watchable_id: e.id,
                  watchable_type: 'episode',
                  series: props.series,
                  series_id: props.series.id,
                  season_number: selectedSeason()?.season_number || 1,
                  episode_number: e.episode_number,
                  runtime_minutes: e.runtime_minutes,
                  duration_seconds:
                      e.duration_seconds ||
                      (e.runtime_minutes ? e.runtime_minutes * 60 : 0),
                  resolution: e.resolution,
                  video_codec: e.video_codec,
                  audio_codec: e.audio_codec,
                  subtitles: e.subtitles || [],
                  title: e.title,
                  title_ar: e.title_ar,
              }));

    const currentItem = {
        ...ep,
        id: ep.id,
        type: 'episode',
        watchable_id: ep.id,
        watchable_type: 'episode',
        series: props.series,
        series_id: props.series.id,
        season_number: ep.season_number || selectedSeason()?.season_number || 1,
        episode_number: ep.episode_number,
        runtime_minutes: ep.runtime_minutes,
        duration_seconds:
            ep.duration_seconds ||
            (ep.runtime_minutes ? ep.runtime_minutes * 60 : 0),
        resolution: ep.resolution,
        video_codec: ep.video_codec,
        audio_codec: ep.audio_codec,
        subtitles: ep.subtitles || [],
        title: ep.title,
        title_ar: ep.title_ar,
    };

    playFn(currentItem, playlist);
};
</script>

<template>
    <AppLayout v-slot="{ play }">
        <Head
            :title="isRTL && series.title_ar ? series.title_ar : series.title"
        />
        <Head
            :title="isRTL && series.title_ar ? series.title_ar : series.title"
        />
        <!-- Top Back Bar & Actions -->
        <div class="mb-6 flex items-center justify-between">
            <Link
                href="/series"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
            >
                <ArrowLeft v-if="!isRTL" class="h-4 w-4" />
                <ArrowRight v-else class="h-4 w-4" />
                <span>{{ t('series.title') }}</span>
            </Link>

            <button
                @click="showFixMatch = true"
                class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-200 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10"
            >
                <SlidersHorizontal class="h-3.5 w-3.5" />
                <span>{{ t('series.fix_match') }}</span>
            </button>
        </div>

        <!-- Series Hero Banner Card -->
        <div
            class="relative mb-8 flex min-h-[320px] items-end overflow-hidden rounded-3xl border border-slate-200 bg-slate-900 shadow-2xl sm:min-h-[420px] dark:border-white/10"
        >
            <div class="absolute inset-0 z-0">
                <img
                    :src="
                        series.backdrop_path ||
                        series.poster_path ||
                        '/placeholder.jpg'
                    "
                    :alt="series.title"
                    class="h-full w-full object-cover opacity-75"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/70 to-transparent"
                ></div>
            </div>

            <!-- Content Header -->
            <div
                class="relative z-10 flex w-full flex-col items-end gap-6 p-6 sm:flex-row sm:p-10"
            >
                <img
                    :src="series.poster_path || '/placeholder.jpg'"
                    :alt="series.title"
                    class="hidden aspect-[2/3] w-28 shrink-0 rounded-2xl border-2 border-white/20 bg-slate-800 object-cover shadow-2xl sm:block sm:w-44"
                />
                <div class="flex-1 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="cinema-badge flex items-center gap-1 border border-amber-500/30 bg-black/60 font-bold text-amber-300"
                        >
                            <Star class="h-3.5 w-3.5 fill-current" />
                            {{ series.rating || '8.0' }}
                        </span>
                        <span
                            v-if="series.release_year"
                            class="cinema-badge border border-white/15 bg-black/60 font-mono text-slate-200"
                        >
                            {{ series.release_year
                            }}<template
                                v-if="
                                    series.end_year &&
                                    series.end_year !== series.release_year
                                "
                            >
                                - {{ series.end_year }}</template
                            >
                        </span>
                        <span
                            class="cinema-badge border border-cyan-500/30 bg-black/60 font-bold text-cyan-300"
                        >
                            {{ series.seasons?.length || 1 }}
                            {{ t('series.seasons') }}
                        </span>
                        <span
                            v-for="g in series.genres?.slice(0, 4)"
                            :key="g.id"
                            class="cinema-badge border border-white/10 bg-black/60 text-slate-200"
                        >
                            {{ isRTL && g.name_ar ? g.name_ar : g.name_en }}
                        </span>
                    </div>

                    <h1
                        class="text-3xl font-black tracking-tight text-white drop-shadow-md sm:text-5xl"
                    >
                        {{
                            isRTL && series.title_ar
                                ? series.title_ar
                                : series.title
                        }}
                    </h1>

                    <p
                        class="line-clamp-3 max-w-3xl text-xs leading-relaxed text-slate-200 drop-shadow sm:text-sm"
                    >
                        {{
                            isRTL && series.overview_ar
                                ? series.overview_ar
                                : series.overview || t('series.experience')
                        }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Seasons Navigation Tabs -->
        <div class="mb-6 border-b border-slate-200 pb-4 dark:border-white/10">
            <div class="flex items-center gap-2 overflow-x-auto pb-1">
                <button
                    v-for="s in series.seasons"
                    :key="s.id"
                    @click="selectedSeasonId = s.id"
                    class="flex cursor-pointer items-center gap-2 rounded-xl px-5 py-2.5 text-xs font-bold whitespace-nowrap transition-all sm:text-sm"
                    :class="
                        selectedSeasonId === s.id
                            ? 'bg-cyan-500 font-black text-slate-950 shadow-lg shadow-cyan-500/20'
                            : 'border border-slate-200 bg-slate-100 text-slate-700 hover:bg-slate-200 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10'
                    "
                >
                    <span>{{
                        isRTL && s.title_ar
                            ? s.title_ar
                            : s.title ||
                              `${t('series.season')} ${s.season_number}`
                    }}</span>
                    <span
                        class="rounded-md bg-black/20 px-1.5 py-0.5 font-mono text-[10px] font-normal"
                    >
                        {{ s.episodes?.length || 0 }} {{ t('series.eps') }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Episodes List Grid -->
        <div
            v-if="selectedSeason()?.episodes?.length"
            class="mb-12 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
        >
            <div
                v-for="ep in selectedSeason()?.episodes"
                :key="ep.id"
                @click="playEpisode(ep, play)"
                class="glass-panel group flex cursor-pointer flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all hover:border-cyan-500/50 dark:border-white/10 dark:bg-[#121622]"
            >
                <div
                    class="relative aspect-video w-full overflow-hidden bg-slate-900"
                >
                    <img
                        :src="
                            ep.still_path ||
                            series.backdrop_path ||
                            series.poster_path ||
                            '/placeholder.jpg'
                        "
                        :alt="ep.title"
                        class="h-full w-full object-cover opacity-90 transition-transform duration-500 group-hover:scale-105"
                    />
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-80"
                    ></div>

                    <!-- Play Hover Overlay -->
                    <div
                        v-if="isBandersnatch(ep)"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/65 p-3 text-center opacity-0 backdrop-blur-xs transition-opacity group-hover:opacity-100"
                    >
                        <button
                            @click.stop="
                                launchInteractiveBandersnatch(ep, $event)
                            "
                            class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-rose-600 to-red-600 px-3 py-2 text-xs font-black text-white shadow-xl shadow-rose-600/50 transition-all hover:scale-105 hover:from-rose-500 hover:to-red-500 active:scale-95"
                        >
                            <Gamepad2 class="h-4 w-4" />
                            <span>{{
                                isRTL
                                    ? 'بدء العرض التفاعلي'
                                    : 'Play Interactive Story'
                            }}</span>
                        </button>
                        <button
                            @click.stop="playEpisode(ep, play)"
                            class="flex cursor-pointer items-center justify-center gap-1 rounded-lg bg-white/10 px-3 py-1 text-[10px] font-medium text-slate-200 transition-colors hover:bg-white/20 hover:text-white"
                        >
                            <Play class="h-3 w-3 fill-current" />
                            <span>{{
                                isRTL ? 'مشاهدة عادية' : 'Normal Playback'
                            }}</span>
                        </button>
                    </div>
                    <div
                        v-else
                        class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-cyan-500 text-slate-950 shadow-xl shadow-cyan-500/40 transition-transform group-hover:scale-110"
                        >
                            <Play class="ml-0.5 h-5 w-5 fill-current" />
                        </div>
                    </div>

                    <!-- Episode Number / Interactive Badge -->
                    <span
                        v-if="isBandersnatch(ep)"
                        class="cinema-badge absolute top-2.5 left-2.5 flex animate-pulse items-center gap-1 border border-red-400 bg-gradient-to-r from-red-600 to-rose-600 text-[10px] font-black tracking-wider text-white shadow-lg shadow-red-500/40"
                    >
                        <Gamepad2 class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'فيلم تفاعلي' : 'INTERACTIVE' }}</span>
                    </span>
                    <span
                        v-else
                        class="cinema-badge absolute top-2.5 left-2.5 border border-cyan-500/40 bg-black/80 text-[10px] font-bold text-cyan-300"
                    >
                        EP {{ ep.episode_number }}
                    </span>

                    <!-- Resolution & Subtitles Badges -->
                    <div
                        class="absolute top-2.5 right-2.5 flex items-center gap-1"
                    >
                        <span
                            v-if="ep.subtitles && ep.subtitles.length > 0"
                            class="cinema-badge flex items-center gap-0.5 border border-emerald-500/40 bg-black/80 text-[9px] text-emerald-400"
                        >
                            <Subtitles class="h-2.5 w-2.5" />
                            <span>{{ ep.subtitles.length }} CC</span>
                        </span>
                        <span
                            v-if="ep.resolution && ep.resolution !== 'Unknown'"
                            class="cinema-badge border border-cyan-500/40 bg-black/80 text-[9px] text-cyan-400"
                        >
                            {{ ep.resolution }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-1 flex-col justify-between p-4">
                    <div>
                        <h4
                            class="line-clamp-1 text-sm font-bold text-slate-900 transition-colors group-hover:text-cyan-600 dark:text-white dark:group-hover:text-cyan-400"
                        >
                            {{ isRTL && ep.title_ar ? ep.title_ar : ep.title }}
                        </h4>
                        <p
                            class="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400"
                        >
                            {{
                                isRTL && ep.overview_ar
                                    ? ep.overview_ar
                                    : ep.overview ||
                                      `${t('series.season')} ${selectedSeason()?.season_number} ${t('series.episode')} ${ep.episode_number}`
                            }}
                        </p>
                    </div>

                    <div
                        class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 text-[11px] font-semibold text-slate-500 dark:border-white/5 dark:text-slate-400"
                    >
                        <span class="flex items-center gap-1">
                            <Clock class="h-3 w-3 text-cyan-500" />
                            <span>{{
                                ep.runtime_minutes
                                    ? ep.runtime_minutes + ' min'
                                    : t('series.ready_to_stream')
                            }}</span>
                        </span>
                        <span class="font-mono text-[10px]">{{
                            ep.video_codec || 'HEVC'
                        }}</span>
                    </div>

                    <button
                        v-if="isBandersnatch(ep)"
                        @click.stop="launchInteractiveBandersnatch(ep, $event)"
                        class="mt-2.5 flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-1.5 text-xs font-bold text-rose-400 shadow-sm transition-all hover:border-rose-500/60 hover:bg-rose-500/20"
                    >
                        <Gamepad2 class="h-3.5 w-3.5" />
                        <span>{{
                            isRTL
                                ? 'تجربة تفاعلية (اختر مسارك)'
                                : 'Interactive Experience (Choose Your Path)'
                        }}</span>
                    </button>
                </div>
            </div>
        </div>

        <div
            v-else
            class="glass-panel my-8 rounded-3xl p-12 text-center text-sm text-slate-500 dark:text-slate-400"
        >
            {{ t('series.no_episodes') }}
        </div>

        <!-- Fix Match Modal -->
        <FixMatchModal
            :show="showFixMatch"
            :item="series"
            type="series"
            @close="showFixMatch = false"
            @updated="handleMetadataUpdated"
        />
    </AppLayout>
</template>
