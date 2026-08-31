<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import HeroBanner from '@/components/media/HeroBanner.vue';
import ContinueWatchingBar from '@/components/layout/ContinueWatchingBar.vue';
import FilterBar from '@/components/media/FilterBar.vue';
import MediaCard from '@/components/media/MediaCard.vue';
import MediaDetailModal from '@/components/media/MediaDetailModal.vue';
import Pagination from '@/components/common/Pagination.vue';
import { Film, ScanLine, Plus } from 'lucide-vue-next';

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
            v-if="heroItem && !filters.search && !filters.genre && !filters.vibe"
            :item="heroItem"
            @play="play"
            @details="handleDetails"
        />

        <!-- In-Progress Continue Watching Bar -->
        <ContinueWatchingBar @play="play" />

        <!-- Filter & Search Studio (Zero Dropdowns) -->
        <FilterBar :genres="genres" :filters="filters" :show-vibes="true" />

        <!-- Media Grid Header -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <Film class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                <h2 class="font-extrabold text-xl text-slate-900 dark:text-white tracking-tight font-sans">
                    {{ t('nav.movies') }}
                </h2>
                <span class="text-xs text-slate-600 dark:text-slate-400 font-semibold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10">
                    {{ movies.total }}
                </span>
            </div>
        </div>

        <!-- Movies Grid -->
        <div v-if="movies.data.length > 0" class="space-y-8">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-6">
                <MediaCard
                    v-for="movie in movies.data"
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
        <div v-else class="glass-panel rounded-3xl p-12 text-center border border-slate-200 dark:border-white/10 space-y-4">
            <div class="w-16 h-16 rounded-3xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 mx-auto flex items-center justify-center">
                <ScanLine class="w-8 h-8" />
            </div>
            <h3 class="font-extrabold text-lg text-slate-900 dark:text-white">
                {{ isRTL ? 'لم يتم العثور على أفلام' : 'No Movies Found' }}
            </h3>
            <p class="text-xs text-slate-600 dark:text-slate-400 max-w-md mx-auto leading-relaxed">
                {{ isRTL ? 'استخدم الفاحص الافتراضي لإضافة مجلدات الأفلام على جهازك وفهرستها تلقائياً.' : 'Use the Virtual Scanner to add your local movie folders and index them in the background.' }}
            </p>
            <Link
                href="/scanner"
                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-cyan-500 text-slate-950 font-extrabold text-xs shadow-lg shadow-cyan-500/20 hover:bg-cyan-400 transition-all cursor-pointer"
            >
                <Plus class="w-4 h-4" />
                <span>{{ isRTL ? 'إضافة مجلد وفحص الأفلام' : 'Scan Movies Folder' }}</span>
            </Link>
        </div>

        <!-- Detail Modal -->
        <MediaDetailModal
            v-if="selectedDetailItem"
            :item="selectedDetailItem"
            type="movie"
            :is-open="!!selectedDetailItem"
            @close="selectedDetailItem = null"
            @play="(item) => { selectedDetailItem = null; play(item); }"
        />
    </AppLayout>
</template>
