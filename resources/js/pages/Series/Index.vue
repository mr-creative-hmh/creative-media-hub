<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import HeroBanner from '@/components/media/HeroBanner.vue';
import WatchHistoryBar from '@/components/layout/WatchHistoryBar.vue';
import FilterBar from '@/components/media/FilterBar.vue';
import Pagination from '@/components/common/Pagination.vue';
import MediaCardSkeleton from '@/components/common/skeletons/MediaCardSkeleton.vue';
import MotionList from '@/components/motion/MotionList.vue';
import { Tv, Star, Layers, ScanLine, Plus, Heart } from '@lucide/vue';

defineProps<{
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

const isNavigating = ref(false);
let removeStartListener: (() => void) | null = null;
let removeFinishListener: (() => void) | null = null;

onMounted(() => {
    removeStartListener = router.on('start', () => {
        isNavigating.value = true;
    });
    removeFinishListener = router.on('finish', () => {
        isNavigating.value = false;
    });
});

onUnmounted(() => {
    if (removeStartListener) removeStartListener();
    if (removeFinishListener) removeFinishListener();
});

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

        <!-- Filter & Search Studio -->
        <FilterBar :genres="genres" :filters="filters" :show-vibes="true" />

        <!-- Series Grid Header -->
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div
                    class="bg-primary/10 text-primary ring-primary/20 flex size-8 items-center justify-center rounded-xl ring-1"
                >
                    <Tv class="size-4" />
                </div>
                <h2
                    class="text-foreground font-sans text-xl font-extrabold tracking-tight"
                >
                    {{ t('nav.series') }}
                </h2>
                <span
                    class="border-primary/25 bg-primary/10 text-primary rounded-full border px-2.5 py-0.5 text-xs font-semibold tabular-nums"
                >
                    {{ seriesList.total }}
                </span>
            </div>
        </div>

        <!-- Series Skeleton or Grid -->
        <div v-if="isNavigating" class="space-y-8">
            <div
                class="grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-6 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
            >
                <MediaCardSkeleton :count="12" />
            </div>
        </div>

        <div v-else-if="seriesList.data.length > 0" class="space-y-8">
            <MotionList
                class="grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-6 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
            >
                <Link
                    v-for="s in seriesList.data"
                    :key="s.id"
                    :href="`/series/${s.slug || s.id}`"
                    class="cf-glass border-border/80 hover:border-primary/50 group relative flex cursor-pointer flex-col overflow-hidden rounded-2xl border shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-xl"
                >
                    <div
                        class="bg-muted/40 relative aspect-[2/3] w-full overflow-hidden"
                    >
                        <img
                            :src="s.poster_path || '/placeholder.jpg'"
                            :alt="s.title"
                            class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                            loading="lazy"
                        />
                        <div
                            class="from-background via-background/20 absolute inset-0 bg-gradient-to-t to-transparent opacity-60"
                        ></div>

                        <!-- Seasons Count Badge & Favorite Toggle -->
                        <div
                            class="pointer-events-none absolute inset-x-2.5 top-2.5 z-30 flex items-center justify-between"
                        >
                            <span
                                class="cinema-badge border-primary/35 text-primary pointer-events-auto flex items-center gap-1 border bg-black/75 text-[10px] tabular-nums"
                            >
                                <Layers class="size-3" />
                                {{ s.seasons_count ?? s.seasons?.length ?? 1 }}
                                {{ t('common.seasons') }}
                            </span>
                            <div
                                class="pointer-events-auto flex items-center gap-1.5"
                            >
                                <div
                                    v-if="s.rating"
                                    class="flex items-center gap-1 rounded-md border border-white/10 bg-black/75 px-1.5 py-0.5 text-[10px] font-bold text-amber-300 tabular-nums backdrop-blur-xs"
                                >
                                    <Star class="size-3 fill-current" />
                                    <span>{{ s.rating }}</span>
                                </div>
                                <button
                                    type="button"
                                    @click.prevent.stop="
                                        handleToggleFavorite(s)
                                    "
                                    class="z-40 flex size-7 cursor-pointer items-center justify-center rounded-full border border-white/10 bg-black/75 shadow-md backdrop-blur-xs transition-all hover:scale-115 hover:bg-black/90 active:scale-90"
                                    :title="
                                        s.is_favorite
                                            ? 'Remove from favorites'
                                            : 'Add to favorites'
                                    "
                                >
                                    <Heart
                                        class="size-3.5 transition-colors"
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
                        class="bg-card/85 flex flex-col justify-between p-3 backdrop-blur-sm"
                    >
                        <h3
                            class="text-foreground group-hover:text-primary truncate text-sm font-bold transition-colors"
                        >
                            {{ isRTL && s.title_ar ? s.title_ar : s.title }}
                        </h3>
                        <div
                            class="text-muted-foreground mt-1 flex items-center justify-between text-[11px] tabular-nums"
                        >
                            <span>{{ s.release_year }}</span>
                            <span
                                class="text-primary text-[10px] font-bold tracking-wider uppercase"
                                >TV Series</span
                            >
                        </div>
                    </div>
                </Link>
            </MotionList>

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
            class="cf-glass border-border/80 space-y-4 rounded-3xl border p-12 text-center"
        >
            <div
                class="bg-primary/10 text-primary ring-primary/20 mx-auto flex size-16 items-center justify-center rounded-3xl ring-1"
            >
                <ScanLine class="size-8" />
            </div>
            <h3 class="text-foreground text-lg font-extrabold">
                {{ isRTL ? 'لم يتم العثور على مسلسلات' : 'No TV Series Found' }}
            </h3>
            <p
                class="text-muted-foreground mx-auto max-w-md text-xs leading-relaxed"
            >
                {{
                    isRTL
                        ? 'استخدم الفاحص الافتراضي لإضافة مجلدات المسلسلات على جهازك وفهرستها مع الحلقات والمواسم.'
                        : 'Use the Virtual Scanner to add your local TV shows folders and index all seasons & episodes.'
                }}
            </p>
            <Link
                href="/scanner"
                class="bg-primary text-primary-foreground shadow-primary/20 inline-flex cursor-pointer items-center gap-2 rounded-xl px-6 py-2.5 text-xs font-extrabold shadow-lg transition-all hover:brightness-105 active:scale-95"
            >
                <Plus class="size-4" />
                <span>{{
                    isRTL ? 'إضافة مجلد وفحص المسلسلات' : 'Scan Series Folder'
                }}</span>
            </Link>
        </div>
    </AppLayout>
</template>
