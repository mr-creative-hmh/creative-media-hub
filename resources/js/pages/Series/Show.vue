<script setup lang="ts">
import { ref, computed, watch, defineAsyncComponent } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/components/layout/AppLayout.vue';
import ClientPagination from '@/components/common/ClientPagination.vue';
import MotionList from '@/components/motion/MotionList.vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Play,
    Star,
    ArrowLeft,
    ArrowRight,
    Subtitles,
    SlidersHorizontal,
    Clock,
    Gamepad2,
} from '@lucide/vue';

const FixMatchModal = defineAsyncComponent(
    () => import('@/components/media/FixMatchModal.vue'),
);

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

const currentPage = ref(1);
const perPage = ref(24);

watch([selectedSeasonId, perPage], () => {
    currentPage.value = 1;
});

const seasonEpisodes = computed(() => selectedSeason()?.episodes || []);

const paginatedEpisodes = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return seasonEpisodes.value.slice(start, start + perPage.value);
});

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

        <!-- Top Back Bar & Actions -->
        <div class="mb-6 flex items-center justify-between gap-3">
            <Link
                href="/series"
                class="cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground inline-flex min-h-10 items-center gap-2 rounded-xl border px-4 py-2 text-xs font-bold transition-all active:scale-95"
            >
                <ArrowLeft v-if="!isRTL" class="h-4 w-4" />
                <ArrowRight v-else class="h-4 w-4" />
                <span>{{ t('series.title') }}</span>
            </Link>

            <button
                type="button"
                @click="showFixMatch = true"
                class="cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground inline-flex min-h-10 cursor-pointer items-center gap-1.5 rounded-xl border px-3.5 py-2 text-xs font-bold transition-all active:scale-95"
            >
                <SlidersHorizontal class="text-primary h-3.5 w-3.5" />
                <span>{{ t('series.fix_match') }}</span>
            </button>
        </div>

        <!-- Series Hero Banner Card -->
        <div
            class="cf-glass-elevated border-border/80 bg-card relative mb-8 flex min-h-[320px] items-end overflow-hidden rounded-3xl border shadow-2xl sm:min-h-[420px]"
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
                    class="from-background via-background/75 absolute inset-0 bg-gradient-to-t to-transparent"
                ></div>
            </div>

            <!-- Content Header -->
            <div
                class="relative z-10 flex w-full flex-col items-end gap-6 p-6 sm:flex-row sm:p-10"
            >
                <img
                    :src="series.poster_path || '/placeholder.jpg'"
                    :alt="series.title"
                    class="bg-muted hidden aspect-[2/3] w-28 shrink-0 rounded-2xl border-2 border-white/15 object-cover shadow-2xl sm:block sm:w-44"
                />
                <div class="flex-1 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="cinema-badge flex items-center gap-1 border border-amber-500/30 bg-black/70 font-bold text-amber-300 tabular-nums"
                        >
                            <Star class="h-3.5 w-3.5 fill-current" />
                            {{ series.rating || '8.0' }}
                        </span>
                        <span
                            v-if="series.release_year"
                            class="cinema-badge border border-white/15 bg-black/70 font-mono text-slate-200 tabular-nums"
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
                            class="cinema-badge border-primary/35 text-primary border bg-black/70 font-bold tabular-nums"
                        >
                            {{ series.seasons?.length || 1 }}
                            {{ t('series.seasons') }}
                        </span>
                        <span
                            v-for="g in series.genres?.slice(0, 4)"
                            :key="g.id"
                            class="cinema-badge border border-white/15 bg-black/70 text-slate-200"
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
        <div class="border-border/70 mb-6 border-b pb-4">
            <div
                class="flex [scrollbar-width:none] items-center gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
                role="tablist"
            >
                <button
                    v-for="s in series.seasons"
                    :key="s.id"
                    type="button"
                    role="tab"
                    :aria-selected="selectedSeasonId === s.id"
                    @click="selectedSeasonId = s.id"
                    class="inline-flex min-h-10 shrink-0 cursor-pointer items-center gap-2 rounded-full border px-4 py-2 text-xs font-bold whitespace-nowrap transition-all active:scale-95 sm:text-sm"
                    :class="
                        selectedSeasonId === s.id
                            ? 'border-primary bg-primary text-primary-foreground shadow-primary/25 shadow-sm'
                            : 'cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground'
                    "
                >
                    <span>{{
                        isRTL && s.title_ar
                            ? s.title_ar
                            : s.title ||
                              `${t('series.season')} ${s.season_number}`
                    }}</span>
                    <span
                        class="rounded-full px-2 py-0.5 font-mono text-[11px] font-semibold tabular-nums"
                        :class="
                            selectedSeasonId === s.id
                                ? 'bg-primary-foreground/20 text-primary-foreground'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{ s.episodes?.length || 0 }} {{ t('series.eps') }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Episodes List Grid -->
        <div v-if="seasonEpisodes.length" class="mb-12 space-y-6">
            <MotionList
                class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="ep in paginatedEpisodes"
                    :key="ep.id"
                    @click="playEpisode(ep, play)"
                    class="cf-glass group border-border/80 hover:border-primary/50 flex cursor-pointer flex-col overflow-hidden rounded-2xl border shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl"
                >
                    <div
                        class="relative aspect-video w-full overflow-hidden bg-slate-950"
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
                            loading="lazy"
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
                                type="button"
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
                                type="button"
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
                                class="bg-primary text-primary-foreground shadow-primary/40 flex h-12 w-12 items-center justify-center rounded-full shadow-xl transition-transform group-hover:scale-110"
                            >
                                <Play class="ms-0.5 h-5 w-5 fill-current" />
                            </div>
                        </div>

                        <!-- Episode Number / Interactive Badge -->
                        <span
                            v-if="isBandersnatch(ep)"
                            class="cinema-badge absolute start-2.5 top-2.5 flex animate-pulse items-center gap-1 border border-red-400 bg-gradient-to-r from-red-600 to-rose-600 text-[10px] font-black tracking-wider text-white shadow-lg shadow-red-500/40"
                        >
                            <Gamepad2 class="h-3.5 w-3.5" />
                            <span>{{
                                isRTL ? 'فيلم تفاعلي' : 'INTERACTIVE'
                            }}</span>
                        </span>
                        <span
                            v-else
                            class="cinema-badge border-primary/40 text-primary absolute start-2.5 top-2.5 border bg-black/80 text-[10px] font-bold tabular-nums"
                        >
                            EP {{ ep.episode_number }}
                        </span>

                        <!-- Resolution & Subtitles Badges -->
                        <div
                            class="absolute end-2.5 top-2.5 flex items-center gap-1"
                        >
                            <span
                                v-if="ep.subtitles && ep.subtitles.length > 0"
                                class="cinema-badge flex items-center gap-0.5 border border-emerald-500/40 bg-black/80 text-[9px] text-emerald-400 tabular-nums"
                            >
                                <Subtitles class="h-2.5 w-2.5" />
                                <span>{{ ep.subtitles.length }} CC</span>
                            </span>
                            <span
                                v-if="
                                    ep.resolution && ep.resolution !== 'Unknown'
                                "
                                class="cinema-badge border-primary/40 text-primary border bg-black/80 text-[9px] tabular-nums"
                            >
                                {{ ep.resolution }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-1 flex-col justify-between p-4">
                        <div>
                            <h4
                                class="text-foreground group-hover:text-primary line-clamp-1 text-sm font-bold transition-colors"
                            >
                                {{
                                    isRTL && ep.title_ar
                                        ? ep.title_ar
                                        : ep.title
                                }}
                            </h4>
                            <p
                                class="text-muted-foreground mt-1 line-clamp-2 text-xs leading-relaxed"
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
                            class="border-border/60 text-muted-foreground mt-3 flex items-center justify-between border-t pt-2 text-[11px] font-semibold tabular-nums"
                        >
                            <span class="flex items-center gap-1">
                                <Clock class="text-primary h-3 w-3" />
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
                            type="button"
                            @click.stop="
                                launchInteractiveBandersnatch(ep, $event)
                            "
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
            </MotionList>

            <ClientPagination
                :current-page="currentPage"
                :total-items="seasonEpisodes.length"
                :per-page="perPage"
                :per-page-options="[12, 24, 48]"
                :scroll-to-top="true"
                @update:current-page="currentPage = $event"
                @update:per-page="perPage = $event"
            />
        </div>

        <div
            v-else
            class="cf-glass border-border/80 text-muted-foreground my-8 rounded-3xl border p-12 text-center text-sm"
        >
            {{ t('series.no_episodes') }}
        </div>

        <!-- Fix Match Modal -->
        <FixMatchModal
            v-if="showFixMatch"
            :show="showFixMatch"
            :item="series"
            type="series"
            @close="showFixMatch = false"
            @updated="handleMetadataUpdated"
        />
    </AppLayout>
</template>
