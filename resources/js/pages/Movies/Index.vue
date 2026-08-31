<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import HeroBanner from '@/components/media/HeroBanner.vue';
import ContinueWatchingBar from '@/components/layout/ContinueWatchingBar.vue';
import FilterBar from '@/components/media/FilterBar.vue';
import MediaCard from '@/components/media/MediaCard.vue';
import MediaDetailModal from '@/components/media/MediaDetailModal.vue';
import { Film } from 'lucide-vue-next';

const props = defineProps<{
    movies: {
        data: any[];
        links: any[];
        total: number;
    };
    genres: any[];
    heroItem: any;
    filters: Record<string, any>;
}>();

const { t, isRTL } = useI18n();

const selectedDetailItem = ref<any | null>(null);

const handleDetails = (item: any) => {
    selectedDetailItem.value = item;
};

const handleToggleFavorite = async (item: any) => {
    try {
        const res = await fetch(`/movies/${item.id}/favorite`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });
        if (res.ok) {
            const data = await res.json();
            item.is_favorite = data.is_favorite;
        }
    } catch (e) {
        console.error(e);
    }
};
</script>

<template>
    <Head :title="t('nav.movies')" />

    <AppLayout v-slot="{ play }">
        <!-- Spotlight Hero Banner -->
        <HeroBanner
            v-if="heroItem && !filters.search && !filters.genre"
            :item="heroItem"
            @play="play"
            @details="handleDetails"
        />

        <!-- In-Progress Continue Watching Bar -->
        <ContinueWatchingBar @play="play" />

        <!-- Filter & Search Studio -->
        <FilterBar :genres="genres" :filters="filters" />

        <!-- Media Grid Header -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <Film class="w-5 h-5 text-cyan-400" />
                <h2 class="font-extrabold text-xl text-white tracking-tight font-sans">
                    {{ t('nav.movies') }}
                </h2>
                <span class="text-xs text-slate-400 font-semibold px-2 py-0.5 rounded-full bg-white/5 border border-white/10">
                    {{ movies.total }}
                </span>
            </div>
        </div>

        <!-- Movies Grid -->
        <div v-if="movies.data.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-6">
            <MediaCard
                v-for="movie in movies.data"
                :key="movie.id"
                :item="movie"
                @play="play"
                @details="handleDetails"
                @toggleFavorite="handleToggleFavorite"
            />
        </div>

        <!-- Empty State -->
        <div v-else class="glass-panel rounded-3xl p-12 text-center my-8 border border-white/10">
            <Film class="w-12 h-12 text-slate-600 mx-auto mb-3" />
            <h3 class="font-bold text-lg text-slate-200 mb-1">
                {{ isRTL ? 'لم يتم العثور على أفلام' : 'No Movies Found' }}
            </h3>
            <p class="text-sm text-slate-400 max-w-md mx-auto">
                {{ isRTL ? 'جرّب تعديل خيارات البحث أو التصفية لعرض المزيد من الأفلام.' : 'Try adjusting your search query or filters to discover more titles.' }}
            </p>
        </div>

        <!-- Detail Modal -->
        <MediaDetailModal
            v-if="selectedDetailItem"
            :item="selectedDetailItem"
            @close="selectedDetailItem = null"
            @play="(item) => { selectedDetailItem = null; play(item); }"
        />
    </AppLayout>
</template>
