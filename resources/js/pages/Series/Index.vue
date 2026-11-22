<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import HeroBanner from '@/components/media/HeroBanner.vue';
import WatchHistoryBar from '@/components/layout/WatchHistoryBar.vue';
import FilterBar from '@/components/media/FilterBar.vue';
import Pagination from '@/components/common/Pagination.vue';
import { Tv, Star, Layers, ScanLine, Plus, Heart } from '@lucide/vue';

const props = defineProps<{
    seriesList: {
        data: any[];
        links: any[];
        from?: number;
        to?: number;
        total: number;
        current_page?: number;
        last_page?: number;
    };
    genres: any[];
    heroSeries?: any;
    heroItems?: any[];
    filters: Record<string, any>;
}>();

const { t, isRTL } = useI18n();

const handleDetails = (item: any) => {
    if (!item) return;
    const identifier = item.slug || item.id;
    router.visit(`/series/${identifier}`);
};

const handlePlaySeries = (
    seriesItem: any,
    playFn: (item: any, playlist?: any[]) => void,
) => {
    if (!seriesItem) return;
    const seasons = (seriesItem.seasons || [])
        .slice()
        .sort(
            (a: any, b: any) => (a.season_number || 0) - (b.season_number || 0),
        );
    const allEps: any[] = [];
    seasons.forEach((s: any) => {
        const sNum = s.season_number || 1;
        const eps = (s.episodes || [])
            .slice()
            .sort(
                (a: any, b: any) =>
                    (a.episode_number || 0) - (b.episode_number || 0),
            );
        eps.forEach((e: any) => {
            allEps.push({
                ...e,
                id: e.id,
                type: 'episode',
                watchable_id: e.id,
                watchable_type: 'episode',
                series: seriesItem,
                series_id: seriesItem.id,
                series_title: seriesItem.title,
                series_title_ar: seriesItem.title_ar,
                season_number: sNum,
                episode_number: e.episode_number || 1,
                title: e.title,
                title_ar: e.title_ar,
                subtitles: e.subtitles || [],
            });
        });
    });

    const firstSeason = seasons[0];
    const firstEp =
        allEps[0] || firstSeason?.episodes?.[0] || seriesItem.first_episode;
    if (firstEp) {
        const playlist =
            allEps.length > 0
                ? allEps
                : (firstSeason?.episodes || []).map((e: any) => ({
                      ...e,
                      id: e.id,
                      type: 'episode',
                      watchable_id: e.id,
                      watchable_type: 'episode',
                      series: seriesItem,
                      series_id: seriesItem.id,
                      series_title: seriesItem.title,
                      series_title_ar: seriesItem.title_ar,
                      season_number: firstSeason?.season_number || 1,
                      episode_number: e.episode_number || 1,
                      title: e.title,
                      title_ar: e.title_ar,
                      subtitles: e.subtitles || [],
                  }));

        playFn(
            {
                ...firstEp,
                id: firstEp.id,
                type: 'episode',
                watchable_id: firstEp.id,
                watchable_type: 'episode',
                series: seriesItem,
                series_id: seriesItem.id,
                series_title: seriesItem.title,
                series_title_ar: seriesItem.title_ar,
                season_number:
                    firstEp.season_number || firstSeason?.season_number || 1,
                episode_number: firstEp.episode_number || 1,
                title: firstEp.title,
                title_ar: firstEp.title_ar,
                subtitles: firstEp.subtitles || [],
            },
            playlist,
        );
    } else {
        handleDetails(seriesItem);
    }
};

const handleToggleFavorite = async (s: any) => {
    try {
        const res = await fetch(`/series/${s.id}/favorite`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
        });
        if (res.ok) {
            const data = await res.json();
            s.is_favorite = data.is_favorite;
        }
    } catch (e) {
        console.error('Failed to toggle series favorite:', e);
    }
};
</script>

<template>
    <AppLayout v-slot="{ play }">
        <Head :title="t('nav.series')" />
        <!-- Spotlight Hero -->
        <HeroBanner
            v-if="
                heroSeries && !filters.search && !filters.genre && !filters.vibe
            "
            :items="heroItems || (heroSeries ? [heroSeries] : [])"
            @play="(item) => handlePlaySeries(item, play)"
            @details="handleDetails"
            @info="handleDetails"
        />

        <!-- In-Progress Continue Watching Bar (Series Episodes Only) -->
        <WatchHistoryBar type="series" @play="play" />

        <!-- Filter & Search Studio (Zero Dropdowns) -->
        <FilterBar :genres="genres" :filters="filters" :show-vibes="true" />

        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <Tv class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                <h2
                    class="font-sans text-xl font-extrabold tracking-tight text-slate-900 dark:text-white"
                >
                    {{ t('nav.series') }}
                </h2>
                <span
                    class="rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-400"
                >
                    {{ seriesList.total }}
                </span>
            </div>
        </div>

        <div v-if="seriesList.data.length > 0" class="space-y-8">
            <div
                class="grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-6 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
            >
                <Link
                    v-for="s in seriesList.data"
                    :key="s.id"
                    :href="`/series/${s.slug || s.id}`"
                    class="glass-card group relative flex cursor-pointer flex-col overflow-hidden rounded-2xl border border-slate-200 shadow-sm transition-all duration-300 hover:border-indigo-500/50 hover:shadow-lg dark:border-white/10"
                >
                    <div
                        class="relative aspect-[2/3] w-full overflow-hidden bg-slate-200 dark:bg-slate-900"
                    >
                        <img
                            :src="s.poster_path || '/placeholder.jpg'"
                            :alt="s.title"
                            class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                        />
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/20 to-transparent opacity-60"
                        ></div>

                        <!-- Seasons Count Badge & Favorite Toggle (z-30, strictly above hover overlay and blur) -->
                        <div
                            class="pointer-events-none absolute inset-x-2.5 top-2.5 z-30 flex items-center justify-between"
                        >
                            <span
                                class="cinema-badge pointer-events-auto flex items-center gap-1 border border-cyan-500/30 bg-black/70 text-[10px] text-cyan-300"
                            >
                                <Layers class="h-3 w-3" />
                                {{ s.seasons_count ?? s.seasons?.length ?? 1 }}
                                {{ t('common.seasons') }}
                            </span>
                            <div
                                class="pointer-events-auto flex items-center gap-1.5"
                            >
                                <div
                                    v-if="s.rating"
                                    class="flex items-center gap-1 rounded bg-black/70 px-1.5 py-0.5 text-[10px] font-bold text-amber-300"
                                >
                                    <Star class="h-3 w-3 fill-current" />
                                    <span>{{ s.rating }}</span>
                                </div>
                                <button
                                    type="button"
                                    @click.prevent.stop="
                                        handleToggleFavorite(s)
                                    "
                                    class="z-40 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full border border-white/10 bg-black/70 shadow-md backdrop-blur-xs transition-all hover:scale-125 hover:bg-black/90 active:scale-90"
                                    :title="
                                        s.is_favorite
                                            ? 'Remove from favorites'
                                            : 'Add to favorites'
                                    "
                                >
                                    <Heart
                                        class="h-3.5 w-3.5 transition-colors"
                                        :class="
                                            s.is_favorite
                                                ? 'fill-red-500 text-red-500'
                                                : 'text-white hover:text-red-400'
                                        "
                                    />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-col justify-between bg-white p-3 dark:bg-slate-950/40"
                    >
                        <h3
                            class="truncate text-sm font-bold text-slate-900 transition-colors group-hover:text-indigo-600 dark:text-slate-100 dark:group-hover:text-indigo-400"
                        >
                            {{ isRTL && s.title_ar ? s.title_ar : s.title }}
                        </h3>
                        <div
                            class="mt-1 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            <span>{{ s.release_year }}</span>
                            <span
                                class="text-[10px] font-bold tracking-wider text-indigo-600 uppercase dark:text-indigo-400"
                                >TV Series</span
                            >
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Pagination Bar -->
            <Pagination
                :links="seriesList.links"
                :from="seriesList.from"
                :to="seriesList.to"
                :total="seriesList.total"
                :current-page="seriesList.current_page"
                :last-page="seriesList.last_page"
            />
        </div>

        <!-- Empty State -->
        <div
            v-else
            class="glass-panel space-y-4 rounded-3xl border border-slate-200 p-12 text-center dark:border-white/10"
        >
            <div
                class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
            >
                <ScanLine class="h-8 w-8" />
            </div>
            <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">
                {{ isRTL ? 'لم يتم العثور على مسلسلات' : 'No TV Series Found' }}
            </h3>
            <p
                class="mx-auto max-w-md text-xs leading-relaxed text-slate-600 dark:text-slate-400"
            >
                {{
                    isRTL
                        ? 'استخدم الفاحص الافتراضي لإضافة مجلدات المسلسلات على جهازك وفهرستها مع الحلقات والمواسم.'
                        : 'Use the Virtual Scanner to add your local TV shows folders and index all seasons & episodes.'
                }}
            </p>
            <Link
                href="/scanner"
                class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-xs font-extrabold text-white shadow-lg shadow-indigo-600/20 transition-all hover:bg-indigo-500"
            >
                <Plus class="h-4 w-4" />
                <span>{{
                    isRTL ? 'إضافة مجلد وفحص المسلسلات' : 'Scan Series Folder'
                }}</span>
            </Link>
        </div>
    </AppLayout>
</template>
