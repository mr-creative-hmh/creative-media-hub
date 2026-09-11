<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/components/layout/AppLayout.vue';
import FixMatchModal from '@/components/media/FixMatchModal.vue';
import { formatEpisodeTitle, getCleanEpisodeTitle } from '@/lib/mediaTitle';
import { useI18n } from '@/i18n/useI18n';
import { Play, Star, ArrowLeft, ArrowRight, Layers, Users, Subtitles, Film, Sparkles, SlidersHorizontal, Clock, Gamepad2 } from 'lucide-vue-next';

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
    return props.series.seasons?.find(s => s.id === selectedSeasonId.value) || props.series.seasons?.[0];
};

const handleMetadataUpdated = (updatedItem: any) => {
    Object.assign(props.series, updatedItem);
};

const isBandersnatch = (ep: any) => {
    return ep?.id === 5764 ||
        (ep?.title && /bandersnatch/i.test(ep.title)) ||
        (ep?.episode_number === 1 && selectedSeason()?.season_number === 0 && /black mirror/i.test(props.series?.title || ''));
};

const launchInteractiveBandersnatch = (ep: any, e?: Event) => {
    if (e) e.stopPropagation();
    window.dispatchEvent(new CustomEvent('play-bandersnatch', {
        detail: {
            ...ep,
            series: props.series,
            subtitles: ep.subtitles || []
        }
    }));
};

const playEpisode = (ep: any, playFn: (item: any, playlist?: any[]) => void) => {
    const seasonEps = selectedSeason()?.episodes || [];
    const playlist = seasonEps.map((e: any) => ({
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
        duration_seconds: e.duration_seconds || (e.runtime_minutes ? e.runtime_minutes * 60 : 0),
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
        season_number: selectedSeason()?.season_number || 1,
        episode_number: ep.episode_number,
        runtime_minutes: ep.runtime_minutes,
        duration_seconds: ep.duration_seconds || (ep.runtime_minutes ? ep.runtime_minutes * 60 : 0),
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
    <Head :title="isRTL && series.title_ar ? series.title_ar : series.title" />

    <AppLayout v-slot="{ play }">
        <!-- Top Back Bar & Actions -->
        <div class="flex items-center justify-between mb-6">
            <Link
                href="/series"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-700 dark:text-slate-200 text-xs font-bold transition-colors border border-slate-200 dark:border-white/10"
            >
                <ArrowLeft v-if="!isRTL" class="w-4 h-4" />
                <ArrowRight v-else class="w-4 h-4" />
                <span>{{ t('series.title') }}</span>
            </Link>

            <button
                @click="showFixMatch = true"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-600 dark:text-slate-300 text-xs font-bold transition-colors border border-slate-200 dark:border-white/10 cursor-pointer"
            >
                <SlidersHorizontal class="w-3.5 h-3.5" />
                <span>{{ t('series.fix_match') }}</span>
            </button>
        </div>

        <!-- Series Hero Banner Card -->
        <div class="relative rounded-3xl overflow-hidden mb-8 border border-slate-200 dark:border-white/10 min-h-[320px] sm:min-h-[420px] flex items-end shadow-2xl bg-slate-900">
            <div class="absolute inset-0 z-0">
                <img
                    :src="series.backdrop_path || series.poster_path || '/placeholder.jpg'"
                    :alt="series.title"
                    class="w-full h-full object-cover opacity-75"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/70 to-transparent"></div>
            </div>

            <!-- Content Header -->
            <div class="relative z-10 p-6 sm:p-10 flex flex-col sm:flex-row gap-6 items-end w-full">
                <img
                    :src="series.poster_path || '/placeholder.jpg'"
                    :alt="series.title"
                    class="w-28 sm:w-44 rounded-2xl border-2 border-white/20 shadow-2xl shrink-0 hidden sm:block bg-slate-800 object-cover aspect-[2/3]"
                />
                <div class="flex-1 space-y-3">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="cinema-badge bg-black/60 text-amber-300 border border-amber-500/30 flex items-center gap-1 font-bold">
                            <Star class="w-3.5 h-3.5 fill-current" />
                            {{ series.rating || '8.0' }}
                        </span>
                        <span v-if="series.release_year" class="cinema-badge bg-black/60 text-slate-200 border border-white/15 font-mono">
                            {{ series.release_year }}<template v-if="series.end_year && series.end_year !== series.release_year"> - {{ series.end_year }}</template>
                        </span>
                        <span class="cinema-badge bg-black/60 text-cyan-300 border border-cyan-500/30 font-bold">
                            {{ series.seasons?.length || 1 }} {{ t('series.seasons') }}
                        </span>
                        <span v-for="g in series.genres?.slice(0, 4)" :key="g.id" class="cinema-badge bg-black/60 text-slate-200 border border-white/10">
                            {{ isRTL && g.name_ar ? g.name_ar : g.name_en }}
                        </span>
                    </div>

                    <h1 class="text-3xl sm:text-5xl font-black text-white drop-shadow-md tracking-tight">
                        {{ isRTL && series.title_ar ? series.title_ar : series.title }}
                    </h1>

                    <p class="text-xs sm:text-sm text-slate-200 max-w-3xl leading-relaxed drop-shadow line-clamp-3">
                        {{ (isRTL && series.overview_ar) ? series.overview_ar : (series.overview || t('series.experience')) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Seasons Navigation Tabs -->
        <div class="mb-6 border-b border-slate-200 dark:border-white/10 pb-4">
            <div class="flex items-center gap-2 overflow-x-auto pb-1">
                <button
                    v-for="s in series.seasons"
                    :key="s.id"
                    @click="selectedSeasonId = s.id"
                    class="px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all whitespace-nowrap cursor-pointer flex items-center gap-2"
                    :class="selectedSeasonId === s.id
                        ? 'bg-cyan-500 text-slate-950 shadow-lg shadow-cyan-500/20 font-black'
                        : 'bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10'"
                >
                    <span>{{ isRTL && s.title_ar ? s.title_ar : (s.title || `${t('series.season')} ${s.season_number}`) }}</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded-md bg-black/20 font-mono font-normal">
                        {{ s.episodes?.length || 0 }} {{ t('series.eps') }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Episodes List Grid -->
        <div v-if="selectedSeason()?.episodes?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-12">
            <div
                v-for="ep in selectedSeason()?.episodes"
                :key="ep.id"
                @click="playEpisode(ep, play)"
                class="glass-panel group rounded-2xl overflow-hidden cursor-pointer border border-slate-200 dark:border-white/10 hover:border-cyan-500/50 transition-all flex flex-col shadow-sm bg-white dark:bg-[#121622]"
            >
                <div class="relative aspect-video w-full overflow-hidden bg-slate-900">
                    <img
                        :src="ep.still_path || series.backdrop_path || series.poster_path || '/placeholder.jpg'"
                        :alt="ep.title"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 opacity-90"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-80"></div>

                    <!-- Play Hover Overlay -->
                    <div v-if="isBandersnatch(ep)" class="absolute inset-0 flex flex-col items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity bg-black/65 backdrop-blur-xs p-3 text-center">
                        <button
                            @click.stop="launchInteractiveBandersnatch(ep, $event)"
                            class="w-full py-2 px-3 rounded-xl bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white font-black text-xs flex items-center justify-center gap-1.5 shadow-xl shadow-rose-600/50 hover:scale-105 active:scale-95 transition-all cursor-pointer"
                        >
                            <Gamepad2 class="w-4 h-4" />
                            <span>{{ isRTL ? 'بدء العرض التفاعلي' : 'Play Interactive Story' }}</span>
                        </button>
                        <button
                            @click.stop="playEpisode(ep, play)"
                            class="py-1 px-3 rounded-lg bg-white/10 hover:bg-white/20 text-slate-200 hover:text-white text-[10px] font-medium flex items-center justify-center gap-1 transition-colors cursor-pointer"
                        >
                            <Play class="w-3 h-3 fill-current" />
                            <span>{{ isRTL ? 'مشاهدة عادية' : 'Normal Playback' }}</span>
                        </button>
                    </div>
                    <div v-else class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40">
                        <div class="w-12 h-12 rounded-full bg-cyan-500 text-slate-950 flex items-center justify-center shadow-xl shadow-cyan-500/40 group-hover:scale-110 transition-transform">
                            <Play class="w-5 h-5 fill-current ml-0.5" />
                        </div>
                    </div>

                    <!-- Episode Number / Interactive Badge -->
                    <span
                        v-if="isBandersnatch(ep)"
                        class="absolute top-2.5 left-2.5 cinema-badge bg-gradient-to-r from-red-600 to-rose-600 text-white border border-red-400 text-[10px] font-black tracking-wider flex items-center gap-1 shadow-lg shadow-red-500/40 animate-pulse"
                    >
                        <Gamepad2 class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'فيلم تفاعلي' : 'INTERACTIVE' }}</span>
                    </span>
                    <span v-else class="absolute top-2.5 left-2.5 cinema-badge bg-black/80 text-cyan-300 border border-cyan-500/40 text-[10px] font-bold">
                        EP {{ ep.episode_number }}
                    </span>

                    <!-- Resolution & Subtitles Badges -->
                    <div class="absolute top-2.5 right-2.5 flex items-center gap-1">
                        <span v-if="ep.subtitles && ep.subtitles.length > 0" class="cinema-badge bg-black/80 text-emerald-400 border border-emerald-500/40 text-[9px] flex items-center gap-0.5">
                            <Subtitles class="w-2.5 h-2.5" />
                            <span>{{ ep.subtitles.length }} CC</span>
                        </span>
                        <span v-if="ep.resolution && ep.resolution !== 'Unknown'" class="cinema-badge bg-black/80 text-cyan-400 border border-cyan-500/40 text-[9px]">
                            {{ ep.resolution }}
                        </span>
                    </div>
                </div>

                <div class="p-4 flex-1 flex flex-col justify-between">
                    <div>
                        <h4 class="font-bold text-sm text-slate-900 dark:text-white group-hover:text-cyan-600 dark:group-hover:text-cyan-400 transition-colors line-clamp-1">
                            {{ isRTL && ep.title_ar ? ep.title_ar : ep.title }}
                        </h4>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 line-clamp-2 leading-relaxed">
                            {{ (isRTL && ep.overview_ar) ? ep.overview_ar : (ep.overview || `${t('series.season')} ${selectedSeason()?.season_number} ${t('series.episode')} ${ep.episode_number}`) }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between mt-3 text-[11px] text-slate-500 dark:text-slate-400 font-semibold border-t border-slate-100 dark:border-white/5 pt-2">
                        <span class="flex items-center gap-1">
                            <Clock class="w-3 h-3 text-cyan-500" />
                            <span>{{ ep.runtime_minutes ? ep.runtime_minutes + ' min' : t('series.ready_to_stream') }}</span>
                        </span>
                        <span class="font-mono text-[10px]">{{ ep.video_codec || 'HEVC' }}</span>
                    </div>

                    <button
                        v-if="isBandersnatch(ep)"
                        @click.stop="launchInteractiveBandersnatch(ep, $event)"
                        class="mt-2.5 w-full py-1.5 px-3 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 font-bold text-xs flex items-center justify-center gap-1.5 transition-all cursor-pointer hover:border-rose-500/60 shadow-sm"
                    >
                        <Gamepad2 class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'تجربة تفاعلية (اختر مسارك)' : 'Interactive Experience (Choose Your Path)' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="glass-panel rounded-3xl p-12 text-center text-slate-500 dark:text-slate-400 text-sm my-8">
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
