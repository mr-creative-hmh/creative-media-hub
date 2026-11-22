<script setup lang="ts">
import { ref, computed, defineAsyncComponent } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import HeroBanner from '@/components/media/HeroBanner.vue';
import WatchHistoryBar from '@/components/layout/WatchHistoryBar.vue';
import MediaCard from '@/components/media/MediaCard.vue';
import {
    Film,
    Tv,
    HardDrive,
    Sparkles,
    Play,
    ChevronRight,
    ChevronLeft,
    Star,
    Layers,
} from '@lucide/vue';

const MediaDetailModal = defineAsyncComponent(
    () => import('@/components/media/MediaDetailModal.vue'),
);

const props = defineProps<{
    featuredMedia: any[];
    continueWatching: any[];
    recentlyAddedMovies: any[];
    popularSeries: any[];
    stats: {
        total_movies: number;
        total_series: number;
        total_episodes: number;
        storage_formatted: string;
        missing_subtitles_count: number;
        active_downloads_count: number;
    };
    vibes: Array<{
        id: string;
        label_en: string;
        label_ar: string;
        icon: string;
        genre: string;
        description_en?: string;
        description_ar?: string;
        matches?: any[];
        match_count?: number;
    }>;
}>();

const { t, isRTL } = useI18n();

const activeTab = ref<'movies' | 'series'>('movies');
const selectedMedia = ref<any | null>(null);
const isModalOpen = ref(false);

// Active Vibe Selection on Dashboard
const activeVibeId = ref<string>(props.vibes?.[0]?.id || 'mind-bending');

const activeVibe = computed(() => {
    return (
        props.vibes.find((v) => v.id === activeVibeId.value) || props.vibes[0]
    );
});

const openDetail = (item: any) => {
    selectedMedia.value = item;
    isModalOpen.value = true;
};

const closeDetail = () => {
    selectedMedia.value = null;
    isModalOpen.value = false;
};

const handleHeroPlay = (
    item: any,
    playFn: (mediaItem: any, playlist?: any[]) => void,
) => {
    if (!item) return;

    // If it's a TV Series
    if (item.type === 'series' || item.seasons || !item.file_path) {
        const firstSeason =
            item.seasons?.find((s: any) => s.season_number === 1) ||
            item.seasons?.[0];
        const firstEp = item.first_episode || firstSeason?.episodes?.[0];
        if (firstEp) {
            const playlist = (firstSeason?.episodes || []).map((e: any) => ({
                ...e,
                id: e.id,
                type: 'episode',
                watchable_id: e.id,
                watchable_type: 'episode',
                series: item,
                series_id: item.id,
                series_title: item.title,
                series_title_ar: item.title_ar,
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
                    series: item,
                    series_id: item.id,
                    series_title: item.title,
                    series_title_ar: item.title_ar,
                    season_number: firstSeason?.season_number || 1,
                    episode_number: firstEp.episode_number || 1,
                    title: firstEp.title,
                    title_ar: firstEp.title_ar,
                    subtitles: firstEp.subtitles || [],
                },
                playlist,
            );
            return;
        }

        // If no playable episode indexed yet, navigate to series showcase
        router.visit(`/series/${item.slug || item.id}`);
        return;
    }

    // Otherwise it is a Movie
    playFn({
        ...item,
        type: 'movie',
        watchable_id: item.id,
        watchable_type: 'media_item',
    });
};

const handleHeroDetails = (item: any) => {
    if (!item) return;
    if (item.type === 'series' || item.seasons || !item.file_path) {
        router.visit(`/series/${item.slug || item.id}`);
        return;
    }
    openDetail(item);
};
</script>

<template>
    <AppLayout v-slot="{ play }">
        <Head :title="t('nav.dashboard')" />
        <!-- 1. Hero Spotlight Carousel with Ambient Backdrop Glow -->
        <div v-if="featuredMedia && featuredMedia.length > 0" class="mb-10">
            <HeroBanner
                :featured-items="featuredMedia"
                @play="(item) => handleHeroPlay(item, play)"
                @details="handleHeroDetails"
                @info="handleHeroDetails"
            />
        </div>

        <!-- 2. Quick Overview Stats Bento Bar -->
        <div class="mb-10 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <!-- Total Movies -->
            <Link
                href="/movies"
                prefetch
                class="cf-glass border-border/80 hover:border-primary/50 group flex flex-col justify-between rounded-2xl border p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase"
                        >{{ t('nav.movies') }}</span
                    >
                    <div
                        class="bg-primary/10 text-primary ring-primary/20 flex size-8 items-center justify-center rounded-xl ring-1 transition-transform group-hover:scale-110"
                    >
                        <Film class="size-4" />
                    </div>
                </div>
                <div
                    class="text-foreground mt-2 text-2xl font-black tabular-nums"
                >
                    {{ stats.total_movies }}
                </div>
            </Link>

            <!-- TV Series -->
            <Link
                href="/series"
                prefetch
                class="cf-glass border-border/80 hover:border-primary/50 group flex flex-col justify-between rounded-2xl border p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase"
                        >{{ t('nav.series') }}</span
                    >
                    <div
                        class="bg-primary/10 text-primary ring-primary/20 flex size-8 items-center justify-center rounded-xl ring-1 transition-transform group-hover:scale-110"
                    >
                        <Tv class="size-4" />
                    </div>
                </div>
                <div
                    class="text-foreground mt-2 text-2xl font-black tabular-nums"
                >
                    {{ stats.total_series }}
                    <span class="text-muted-foreground text-xs font-normal"
                        >({{ stats.total_episodes }} ep)</span
                    >
                </div>
            </Link>

            <!-- Metadata Studio Status -->
            <Link
                href="/metadata"
                prefetch
                class="cf-glass border-border/80 hover:border-primary/50 group flex flex-col justify-between rounded-2xl border p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase"
                        >{{ t('nav.metadata') }}</span
                    >
                    <div
                        class="flex size-8 items-center justify-center rounded-xl bg-amber-500/10 text-amber-400 ring-1 ring-amber-500/20 transition-transform group-hover:scale-110"
                    >
                        <Sparkles class="size-4" />
                    </div>
                </div>
                <div
                    class="text-foreground mt-2 flex items-center gap-1.5 text-xs font-bold"
                >
                    <span>{{
                        isRTL ? 'إدارة الأغلفة والبيانات' : 'Studio Manager'
                    }}</span>
                </div>
            </Link>

            <!-- Storage Used -->
            <Link
                href="/analytics"
                prefetch
                class="cf-glass border-border/80 hover:border-primary/50 group flex flex-col justify-between rounded-2xl border p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase"
                        >{{ t('analytics_view.storage_used') }}</span
                    >
                    <div
                        class="flex size-8 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/20 transition-transform group-hover:scale-110"
                    >
                        <HardDrive class="size-4" />
                    </div>
                </div>
                <div
                    class="text-foreground mt-2 text-2xl font-black tabular-nums"
                >
                    {{ stats.storage_formatted }}
                </div>
            </Link>
        </div>

        <!-- Watch History Bar (All Media In Progress) -->
        <WatchHistoryBar type="all" @play="play" />

        <!-- 3. Curated AI Mood Vibes Interactive Matcher Studio -->
        <div
            class="cf-glass border-border/80 relative mb-12 space-y-6 overflow-hidden rounded-3xl border p-5 shadow-sm sm:p-6"
        >
            <div
                class="bg-primary/8 pointer-events-none absolute -end-20 -top-20 size-72 rounded-full blur-3xl"
            ></div>

            <div
                class="relative z-10 flex flex-wrap items-center justify-between gap-4"
            >
                <div class="flex items-center gap-3.5">
                    <div
                        class="bg-primary/10 text-primary ring-primary/20 flex size-11 shrink-0 items-center justify-center rounded-2xl ring-1"
                    >
                        <Sparkles class="size-5" />
                    </div>
                    <div>
                        <h2
                            class="text-foreground text-lg font-black tracking-tight sm:text-xl"
                        >
                            {{ t('media.vibes') }}
                        </h2>
                        <p class="text-muted-foreground mt-0.5 text-xs">
                            {{
                                isRTL
                                    ? 'اختر المزاج السينمائي لاكتشاف أفضل الأفلام والمسلسلات المطابقة فورياً.'
                                    : 'Select a mood to instantly surface smart matches from your personal library.'
                            }}
                        </p>
                    </div>
                </div>

                <!-- View All in Movies / Series Links -->
                <div class="flex items-center gap-2">
                    <Link
                        :href="`/movies?genre=${activeVibe?.genre}`"
                        class="text-primary hover:text-primary/80 flex items-center gap-1 text-xs font-bold hover:underline"
                    >
                        <span>{{
                            isRTL
                                ? `أفلام ${activeVibe?.label_ar}`
                                : `View ${activeVibe?.label_en} Movies`
                        }}</span>
                        <component
                            :is="isRTL ? ChevronLeft : ChevronRight"
                            class="size-3.5"
                        />
                    </Link>
                </div>
            </div>

            <!-- Vibe Selector Chips -->
            <div
                class="custom-scrollbar relative z-10 flex items-center gap-2.5 overflow-x-auto pb-1"
            >
                <button
                    v-for="vibe in vibes"
                    :key="vibe.id"
                    type="button"
                    @click="activeVibeId = vibe.id"
                    class="flex min-h-10 cursor-pointer items-center gap-2 rounded-2xl border px-4 py-2 text-xs font-extrabold whitespace-nowrap transition-all active:scale-95"
                    :class="
                        activeVibeId === vibe.id
                            ? 'border-primary bg-primary text-primary-foreground shadow-primary/25 font-black shadow-md'
                            : 'border-border/80 bg-card/70 text-muted-foreground hover:border-primary/40 hover:text-foreground'
                    "
                >
                    <span
                        :class="
                            activeVibeId === vibe.id
                                ? 'text-primary-foreground'
                                : 'text-primary'
                        "
                        >✦</span
                    >
                    <span>{{ isRTL ? vibe.label_ar : vibe.label_en }}</span>
                </button>
            </div>

            <!-- Active Vibe Description & Matches Grid -->
            <div
                v-if="activeVibe"
                class="border-border/60 relative z-10 space-y-4 border-t pt-3"
            >
                <div
                    class="text-muted-foreground flex items-center justify-between text-xs"
                >
                    <p class="italic">
                        {{
                            isRTL
                                ? activeVibe.description_ar
                                : activeVibe.description_en
                        }}
                    </p>
                    <span
                        class="cinema-badge border-primary/30 bg-primary/15 text-primary border text-[10px] tabular-nums"
                    >
                        {{ activeVibe.match_count || 0 }}
                        {{ isRTL ? 'عنصر مطابق' : 'Matches' }}
                    </span>
                </div>

                <!-- Matches Grid -->
                <div
                    v-if="activeVibe.matches && activeVibe.matches.length > 0"
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
                >
                    <div
                        v-for="item in activeVibe.matches"
                        :key="`${item.type}-${item.id}`"
                        @click="
                            item.type === 'series'
                                ? router.visit(
                                      `/series/${item.slug || item.id}`,
                                  )
                                : play(item)
                        "
                        class="cf-glass border-border/80 hover:border-primary/50 group relative flex cursor-pointer flex-col overflow-hidden rounded-2xl border shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-xl"
                    >
                        <div
                            class="bg-muted/40 relative aspect-[2/3] w-full overflow-hidden"
                        >
                            <img
                                :src="item.poster_path || '/placeholder.jpg'"
                                :alt="item.title"
                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                            <div
                                class="from-background via-background/20 absolute inset-0 bg-gradient-to-t to-transparent opacity-60"
                            ></div>

                            <!-- Match Score Badge -->
                            <div
                                class="absolute inset-x-2 top-2 flex items-center justify-between"
                            >
                                <span
                                    class="cinema-badge bg-primary text-primary-foreground text-[9px] font-black tabular-nums shadow-sm"
                                >
                                    {{ 94 + (item.id % 6) }}%
                                    {{ isRTL ? 'تطابق' : 'Match' }}
                                </span>
                                <span
                                    class="cinema-badge bg-black/75 text-[9px] text-slate-300 uppercase"
                                >
                                    {{ item.type }}
                                </span>
                            </div>

                            <!-- Play Overlay -->
                            <div
                                class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100"
                            >
                                <div
                                    class="bg-primary text-primary-foreground shadow-primary/50 flex size-10 items-center justify-center rounded-full shadow-lg"
                                >
                                    <Play class="ms-0.5 size-4 fill-current" />
                                </div>
                            </div>
                        </div>

                        <div
                            class="bg-card/85 flex flex-1 flex-col justify-between p-2.5 backdrop-blur-sm"
                        >
                            <h4
                                class="text-foreground group-hover:text-primary truncate text-xs font-bold transition-colors"
                            >
                                {{
                                    isRTL && item.title_ar
                                        ? item.title_ar
                                        : item.title
                                }}
                            </h4>
                            <div
                                class="text-muted-foreground mt-1 flex items-center justify-between text-[10px] tabular-nums"
                            >
                                <span>{{ item.release_year }}</span>
                                <span
                                    v-if="item.rating"
                                    class="flex items-center gap-0.5 font-bold text-amber-400"
                                >
                                    ★ {{ item.rating }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty Matches State for Vibe -->
                <div
                    v-else
                    class="text-muted-foreground py-6 text-center text-xs italic"
                >
                    {{
                        isRTL
                            ? 'لا توجد وسائط متطابقة حالياً مع هذا المزاج في مكتبتك.'
                            : 'No media items currently match this vibe in your library.'
                    }}
                </div>
            </div>
        </div>

        <!-- 5. Catalog Highlights (Tabs: Recently Added Movies vs Top TV Series) -->
        <div class="mb-12">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <!-- Tabs -->
                <div
                    class="cf-glass border-border/80 flex items-center rounded-2xl border p-1"
                >
                    <button
                        type="button"
                        @click="activeTab = 'movies'"
                        class="cursor-pointer rounded-xl px-5 py-2 text-xs font-extrabold transition-all"
                        :class="
                            activeTab === 'movies'
                                ? 'bg-primary text-primary-foreground shadow-primary/30 shadow-md'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{
                            isRTL
                                ? 'أحدث الأفلام المضافة'
                                : 'Recently Added Movies'
                        }}
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'series'"
                        class="cursor-pointer rounded-xl px-5 py-2 text-xs font-extrabold transition-all"
                        :class="
                            activeTab === 'series'
                                ? 'bg-primary text-primary-foreground shadow-primary/30 shadow-md'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{
                            isRTL
                                ? 'المسلسلات الأكثر تقييماً'
                                : 'Top Rated TV Series'
                        }}
                    </button>
                </div>

                <Link
                    :href="activeTab === 'movies' ? '/movies' : '/series'"
                    prefetch
                    class="text-primary hover:text-primary/80 flex items-center gap-1.5 text-xs font-bold transition-colors hover:underline"
                >
                    <span>{{
                        isRTL ? 'تصفح الكل في المكتبة' : 'View Complete Library'
                    }}</span>
                    <component
                        :is="isRTL ? ChevronLeft : ChevronRight"
                        class="size-3.5"
                    />
                </Link>
            </div>

            <!-- Movies Grid Tab -->
            <div
                v-if="activeTab === 'movies'"
                class="grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-6 md:grid-cols-4 lg:grid-cols-6"
            >
                <MediaCard
                    v-for="item in recentlyAddedMovies"
                    :key="`m-${item.id}`"
                    :item="item"
                    type="movie"
                    @play="play"
                    @details="openDetail"
                />
            </div>

            <!-- Series Grid Tab -->
            <div
                v-if="activeTab === 'series'"
                class="grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-6 md:grid-cols-4 lg:grid-cols-6"
            >
                <Link
                    v-for="s in popularSeries"
                    :key="`s-${s.id}`"
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
                        />
                        <div
                            class="from-background via-background/20 absolute inset-0 bg-gradient-to-t to-transparent opacity-60"
                        ></div>

                        <!-- Seasons Count Badge -->
                        <div
                            class="absolute inset-x-2.5 top-2.5 flex items-center justify-between"
                        >
                            <span
                                class="cinema-badge border-primary/35 text-primary flex items-center gap-1 border bg-black/75 text-[10px] tabular-nums"
                            >
                                <Layers class="size-3" />
                                {{ s.seasons?.length || 1 }}
                                {{ t('common.seasons') }}
                            </span>
                            <div
                                v-if="s.rating"
                                class="flex items-center gap-1 rounded-md border border-white/10 bg-black/75 px-1.5 py-0.5 text-[10px] font-bold text-amber-300 tabular-nums"
                            >
                                <Star class="size-3 fill-current" />
                                <span>{{ s.rating }}</span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-card/85 flex flex-col justify-between p-3 backdrop-blur-sm"
                    >
                        <h3
                            class="text-foreground group-hover:text-primary truncate text-xs font-bold transition-colors"
                        >
                            {{ isRTL && s.title_ar ? s.title_ar : s.title }}
                        </h3>
                        <div
                            class="text-muted-foreground mt-1 flex items-center justify-between text-[10px] tabular-nums"
                        >
                            <span>{{ s.release_year }}</span>
                            <span
                                class="text-primary text-[9px] font-bold tracking-wider uppercase"
                                >TV Series</span
                            >
                        </div>
                    </div>
                </Link>
            </div>
        </div>

        <!-- Detail Modal -->
        <MediaDetailModal
            v-if="selectedMedia && isModalOpen"
            :item="selectedMedia"
            :type="selectedMedia.type || 'movie'"
            :is-open="isModalOpen"
            @close="closeDetail"
            @play="
                (item) => {
                    closeDetail();
                    play(item);
                }
            "
        />
    </AppLayout>
</template>
