<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, defineAsyncComponent } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import HeroBanner from '@/components/media/HeroBanner.vue';
import WatchHistoryBar from '@/components/layout/WatchHistoryBar.vue';
import FilterBar from '@/components/media/FilterBar.vue';
import MediaCard from '@/components/media/MediaCard.vue';
import MediaCardSkeleton from '@/components/common/skeletons/MediaCardSkeleton.vue';
import Pagination from '@/components/common/Pagination.vue';
import MotionList from '@/components/motion/MotionList.vue';
import { Film, ScanLine, Plus } from '@lucide/vue';

const MediaDetailModal = defineAsyncComponent(
    () => import('@/components/media/MediaDetailModal.vue'),
);

const props = defineProps<{
    movies: {
        data: any[];
        links: any[];
        from?: number;
        to?: number;
        total: number;
        current_page?: number;
        last_page?: number;
    };
    genres: any[];
    heroItem?: any;
    heroItems?: any[];
    filters: Record<string, any>;
    activeMovie?: any;
    autoPlay?: boolean;
}>();

const { t, isRTL } = useI18n();

const selectedDetailItem = ref<any | null>(
    props.activeMovie ? { ...props.activeMovie } : null,
);
const movieList = ref<any[]>(props.movies.data.map((m) => ({ ...m })));
const localHeroItems = ref<any[]>(
    props.heroItems ? props.heroItems.map((h) => ({ ...h })) : [],
);
const localHeroItem = ref<any | null>(
    props.heroItem ? { ...props.heroItem } : null,
);

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

watch(
    () => props.movies.data,
    (newData) => {
        movieList.value = newData.map((m) => ({ ...m }));
    },
);

watch(
    () => props.heroItems,
    (newHeroItems) => {
        localHeroItems.value = newHeroItems
            ? newHeroItems.map((h) => ({ ...h }))
            : [];
    },
);

watch(
    () => props.heroItem,
    (newHeroItem) => {
        localHeroItem.value = newHeroItem ? { ...newHeroItem } : null;
    },
);

watch(
    () => props.activeMovie,
    (newMovie) => {
        if (newMovie) {
            selectedDetailItem.value = { ...newMovie };
        }
    },
    { immediate: true },
);

const handleDetails = (item: any) => {
    selectedDetailItem.value = { ...item };
};

const handleCloseModal = () => {
    selectedDetailItem.value = null;
    if (
        window.location.pathname.startsWith('/movies/') ||
        window.location.pathname.startsWith('/movie/')
    ) {
        window.history.replaceState(
            {},
            '',
            '/movies' + (window.location.search || ''),
        );
    }
};

const handleToggleFavorite = async (item: any) => {
    try {
        const res = await fetch(`/movies/${item.id}/favorite`, {
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
            movieList.value = movieList.value.map((m) =>
                m.id === item.id ? { ...m, is_favorite: data.is_favorite } : m,
            );
            if (
                selectedDetailItem.value &&
                selectedDetailItem.value.id === item.id
            ) {
                selectedDetailItem.value = {
                    ...selectedDetailItem.value,
                    is_favorite: data.is_favorite,
                };
            }
        }
    } catch (e) {
        console.error(e);
    }
};

const handleMediaUpdated = (updatedItem: any) => {
    if (!updatedItem) return;

    movieList.value = movieList.value.map((m) =>
        m.id === updatedItem.id ? { ...m, ...updatedItem } : m,
    );

    if (localHeroItems.value.length > 0) {
        localHeroItems.value = localHeroItems.value.map((h) =>
            h.id === updatedItem.id ? { ...h, ...updatedItem } : h,
        );
    }

    if (localHeroItem.value && localHeroItem.value.id === updatedItem.id) {
        localHeroItem.value = { ...localHeroItem.value, ...updatedItem };
    }

    if (
        selectedDetailItem.value &&
        selectedDetailItem.value.id === updatedItem.id
    ) {
        selectedDetailItem.value = {
            ...selectedDetailItem.value,
            ...updatedItem,
        };
    }
};
</script>

<template>
    <AppLayout v-slot="{ play }">
        <Head :title="t('nav.movies')" />
        <!-- Spotlight Hero Banner -->
        <HeroBanner
            v-if="
                localHeroItem &&
                !filters.search &&
                !filters.genre &&
                !filters.vibe
            "
            :items="
                localHeroItems.length > 0
                    ? localHeroItems
                    : localHeroItem
                      ? [localHeroItem]
                      : []
            "
            @play="play"
            @details="handleDetails"
        />

        <!-- In-Progress Continue Watching Bar (Movies Only) -->
        <WatchHistoryBar type="movie" @play="play" />

        <!-- Filter & Search Studio -->
        <FilterBar :genres="genres" :filters="filters" :show-vibes="true" />

        <!-- Media Grid Header -->
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div
                    class="bg-primary/10 text-primary ring-primary/20 flex size-8 items-center justify-center rounded-xl ring-1"
                >
                    <Film class="size-4" />
                </div>
                <h2
                    class="text-foreground font-sans text-xl font-extrabold tracking-tight"
                >
                    {{ t('nav.movies') }}
                </h2>
                <span
                    class="border-primary/25 bg-primary/10 text-primary rounded-full border px-2.5 py-0.5 text-xs font-semibold tabular-nums"
                >
                    {{ movies.total }}
                </span>
            </div>
        </div>

        <!-- Movies Skeleton or Grid -->
        <div v-if="isNavigating" class="space-y-8">
            <div
                class="grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-6 md:grid-cols-4 lg:grid-cols-6"
            >
                <MediaCardSkeleton :count="12" />
            </div>
        </div>

        <div v-else-if="movieList.length > 0" class="space-y-8">
            <MotionList
                class="grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-6 md:grid-cols-4 lg:grid-cols-6"
            >
                <MediaCard
                    v-for="movie in movieList"
                    :key="movie.id"
                    :item="movie"
                    type="movie"
                    @play="play"
                    @details="handleDetails"
                    @toggleFavorite="handleToggleFavorite"
                />
            </MotionList>

            <!-- Pagination Bar -->
            <Pagination
                :links="movies.links"
                :from="movies.from"
                :to="movies.to"
                :total="movies.total"
                :current-page="movies.current_page"
                :last-page="movies.last_page"
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
                {{ isRTL ? 'لم يتم العثور على أفلام' : 'No Movies Found' }}
            </h3>
            <p
                class="text-muted-foreground mx-auto max-w-md text-xs leading-relaxed"
            >
                {{
                    isRTL
                        ? 'استخدم الفاحص الافتراضي لإضافة مجلدات الأفلام على جهازك وفهرستها تلقائياً.'
                        : 'Use the Virtual Scanner to add your local movie folders and index them in the background.'
                }}
            </p>
            <Link
                href="/scanner"
                class="bg-primary text-primary-foreground shadow-primary/20 inline-flex cursor-pointer items-center gap-2 rounded-xl px-6 py-2.5 text-xs font-extrabold shadow-lg transition-all hover:brightness-105 active:scale-95"
            >
                <Plus class="size-4" />
                <span>{{
                    isRTL ? 'إضافة مجلد وفحص الأفلام' : 'Scan Movies Folder'
                }}</span>
            </Link>
        </div>

        <!-- Detail Modal -->
        <MediaDetailModal
            v-if="selectedDetailItem"
            :item="selectedDetailItem"
            type="movie"
            :is-open="!!selectedDetailItem"
            @close="handleCloseModal"
            @play="
                (item) => {
                    handleCloseModal();
                    play(item);
                }
            "
            @updated="handleMediaUpdated"
        />
    </AppLayout>
</template>
