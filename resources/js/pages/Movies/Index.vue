<script setup lang="ts">
import { ref, watch, defineAsyncComponent } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import HeroBanner from '@/components/media/HeroBanner.vue';
import WatchHistoryBar from '@/components/layout/WatchHistoryBar.vue';
import FilterBar from '@/components/media/FilterBar.vue';
import MediaCard from '@/components/media/MediaCard.vue';
import Pagination from '@/components/common/Pagination.vue';
import { Film, ScanLine, Plus } from 'lucide-vue-next';

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

        <!-- Filter & Search Studio (Zero Dropdowns) -->
        <FilterBar :genres="genres" :filters="filters" :show-vibes="true" />

        <!-- Media Grid Header -->
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <Film class="h-5 w-5 text-cyan-600 dark:text-cyan-400" />
                <h2
                    class="font-sans text-xl font-extrabold tracking-tight text-slate-900 dark:text-white"
                >
                    {{ t('nav.movies') }}
                </h2>
                <span
                    class="rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-400"
                >
                    {{ movies.total }}
                </span>
            </div>
        </div>

        <!-- Movies Grid -->
        <div v-if="movieList.length > 0" class="space-y-8">
            <div
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
            </div>

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
            class="glass-panel space-y-4 rounded-3xl border border-slate-200 p-12 text-center dark:border-white/10"
        >
            <div
                class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400"
            >
                <ScanLine class="h-8 w-8" />
            </div>
            <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">
                {{ isRTL ? 'لم يتم العثور على أفلام' : 'No Movies Found' }}
            </h3>
            <p
                class="mx-auto max-w-md text-xs leading-relaxed text-slate-600 dark:text-slate-400"
            >
                {{
                    isRTL
                        ? 'استخدم الفاحص الافتراضي لإضافة مجلدات الأفلام على جهازك وفهرستها تلقائياً.'
                        : 'Use the Virtual Scanner to add your local movie folders and index them in the background.'
                }}
            </p>
            <Link
                href="/scanner"
                class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-cyan-500 px-6 py-2.5 text-xs font-extrabold text-slate-950 shadow-lg shadow-cyan-500/20 transition-all hover:bg-cyan-400"
            >
                <Plus class="h-4 w-4" />
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
