<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import HeroBanner from '@/components/media/HeroBanner.vue';
import MediaCard from '@/components/media/MediaCard.vue';
import MediaDetailModal from '@/components/media/MediaDetailModal.vue';
import {
    Film, Tv, HardDrive, Subtitles, DownloadCloud, Sparkles,
    FolderSync, BarChart3, Play, ChevronRight, ChevronLeft,
    Clock, Flame, CheckCircle, Plus
} from 'lucide-vue-next';

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
    }>;
}>();

const { t, isRTL } = useI18n();

const activeTab = ref<'movies' | 'series'>('movies');
const selectedMedia = ref<any | null>(null);
const isModalOpen = ref(false);

const openDetail = (item: any) => {
    selectedMedia.value = item;
    isModalOpen.value = true;
};
</script>

<template>
    <Head :title="t('nav.dashboard')" />

    <AppLayout v-slot="{ play }">
        <!-- 1. Hero Spotlight Carousel with Ambient Backdrop Glow -->
        <div v-if="featuredMedia && featuredMedia.length > 0" class="mb-10">
            <HeroBanner
                :featured-items="featuredMedia"
                @play="(item) => play(item)"
                @info="(item) => openDetail(item)"
            />
        </div>

        <!-- 2. Bento Grid Quick Overview Stats Bar -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 mb-10">
            <!-- Total Movies -->
            <Link
                href="/movies"
                class="glass-panel p-4 rounded-2xl border border-white/10 hover:border-cyan-500/40 transition-all duration-300 group hover:-translate-y-0.5 shadow-lg shadow-black/40 flex flex-col justify-between"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ t('analytics_view.total_movies') }}</span>
                    <div class="w-7 h-7 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <Film class="w-3.5 h-3.5" />
                    </div>
                </div>
                <div class="text-2xl font-black text-white mt-2">{{ stats.total_movies }}</div>
            </Link>

            <!-- Total TV Series -->
            <Link
                href="/series"
                class="glass-panel p-4 rounded-2xl border border-white/10 hover:border-indigo-500/40 transition-all duration-300 group hover:-translate-y-0.5 shadow-lg shadow-black/40 flex flex-col justify-between"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ t('analytics_view.total_series') }}</span>
                    <div class="w-7 h-7 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <Tv class="w-3.5 h-3.5" />
                    </div>
                </div>
                <div class="text-2xl font-black text-white mt-2">{{ stats.total_series }}</div>
            </Link>

            <!-- Storage Used -->
            <Link
                href="/analytics"
                class="glass-panel p-4 rounded-2xl border border-white/10 hover:border-emerald-500/40 transition-all duration-300 group hover:-translate-y-0.5 shadow-lg shadow-black/40 flex flex-col justify-between"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ t('analytics_view.storage_used') }}</span>
                    <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <HardDrive class="w-3.5 h-3.5" />
                    </div>
                </div>
                <div class="text-xl font-black text-white mt-2">{{ stats.storage_formatted }}</div>
            </Link>

            <!-- Missing Subtitles Alert -->
            <Link
                href="/subtitles"
                class="glass-panel p-4 rounded-2xl border border-white/10 hover:border-amber-500/40 transition-all duration-300 group hover:-translate-y-0.5 shadow-lg shadow-black/40 flex flex-col justify-between"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ isRTL ? 'نقص الترجمة' : 'Missing Subs' }}</span>
                    <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <Subtitles class="w-3.5 h-3.5" />
                    </div>
                </div>
                <div class="text-2xl font-black text-white mt-2" :class="stats.missing_subtitles_count > 0 ? 'text-amber-400' : 'text-emerald-400'">
                    {{ stats.missing_subtitles_count }}
                </div>
            </Link>

            <!-- Active Downloads Queue -->
            <Link
                href="/downloads"
                class="glass-panel p-4 rounded-2xl border border-white/10 hover:border-cyan-500/40 transition-all duration-300 group hover:-translate-y-0.5 shadow-lg shadow-black/40 flex flex-col justify-between"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ t('nav.downloads') }}</span>
                    <div class="w-7 h-7 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <DownloadCloud class="w-3.5 h-3.5" />
                    </div>
                </div>
                <div class="text-2xl font-black text-white mt-2">{{ stats.active_downloads_count }}</div>
            </Link>

            <!-- Organizer Action Shortcut -->
            <Link
                href="/organizer"
                class="glass-panel p-4 rounded-2xl border border-cyan-500/30 bg-cyan-950/20 hover:border-cyan-400 transition-all duration-300 group hover:-translate-y-0.5 shadow-lg shadow-cyan-950/30 flex flex-col justify-between"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-cyan-300 uppercase tracking-wider">{{ isRTL ? 'منظّم الملفات' : 'Organizer' }}</span>
                    <div class="w-7 h-7 rounded-lg bg-cyan-500/20 text-cyan-300 flex items-center justify-center group-hover:rotate-45 transition-transform">
                        <FolderSync class="w-3.5 h-3.5" />
                    </div>
                </div>
                <div class="text-xs font-bold text-slate-300 mt-2 flex items-center gap-1">
                    <span>{{ isRTL ? 'فحص وإعادة تسمية' : 'Scan & Sort' }}</span>
                    <component :is="isRTL ? ChevronLeft : ChevronRight" class="w-3 h-3" />
                </div>
            </Link>
        </div>

        <!-- 3. Continue Watching Row (If items in progress) -->
        <div v-if="continueWatching && continueWatching.length > 0" class="mb-12">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <Clock class="w-5 h-5 text-cyan-400" />
                    <h2 class="text-lg sm:text-xl font-black text-white tracking-wide">
                        {{ t('media.continue_watching') }}
                    </h2>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                <div
                    v-for="item in continueWatching"
                    :key="`${item.type}-${item.id}`"
                    @click="play(item)"
                    class="group relative rounded-2xl overflow-hidden glass-panel border border-white/10 hover:border-cyan-500/50 cursor-pointer transition-all duration-300 hover:-translate-y-1 shadow-lg shadow-black/50"
                >
                    <div class="aspect-[16/9] w-full bg-slate-900 overflow-hidden relative">
                        <img
                            :src="item.backdrop_url || item.poster_url || '/placeholder.jpg'"
                            :alt="item.title"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                        
                        <!-- Play Hover Button -->
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40">
                            <div class="w-10 h-10 rounded-full bg-cyan-500 text-slate-950 flex items-center justify-center shadow-lg shadow-cyan-500/50">
                                <Play class="w-5 h-5 fill-current ml-0.5" />
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="absolute bottom-0 inset-x-0 h-1 bg-white/20">
                            <div class="h-full bg-cyan-400" :style="{ width: `${item.progress_percent}%` }"></div>
                        </div>
                    </div>

                    <div class="p-3">
                        <h4 class="font-bold text-xs text-white truncate">{{ isRTL && item.title_ar ? item.title_ar : item.title }}</h4>
                        <p class="text-[10px] text-slate-400 mt-0.5">{{ item.progress_percent }}% • {{ item.current_time_formatted }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Curated AI Mood Vibes Quick Discovery -->
        <div class="mb-12">
            <div class="flex items-center gap-2 mb-4">
                <Sparkles class="w-5 h-5 text-indigo-400" />
                <h2 class="text-lg sm:text-xl font-black text-white tracking-wide">
                    {{ isRTL ? 'استكشف حسب المزاج والجو السينمائي (AI Vibes)' : 'Explore by Mood & Vibe (AI Matcher)' }}
                </h2>
            </div>

            <div class="flex items-center gap-2.5 overflow-x-auto pb-2 scrollbar-none">
                <Link
                    v-for="vibe in vibes"
                    :key="vibe.id"
                    :href="`/movies?genre=${vibe.genre}`"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-2xl glass-panel border border-white/10 hover:border-cyan-500/40 hover:bg-cyan-500/10 text-xs font-bold text-slate-200 hover:text-white transition-all whitespace-nowrap active:scale-95 shadow-md shadow-black/30"
                >
                    <span class="text-cyan-400">✦</span>
                    <span>{{ isRTL ? vibe.label_ar : vibe.label_en }}</span>
                </Link>
            </div>
        </div>

        <!-- 5. Catalog Highlights (Tabs: Recently Added Movies vs Top TV Series) -->
        <div class="mb-12">
            <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
                <!-- Tabs -->
                <div class="flex items-center p-1 rounded-2xl glass-panel border border-white/10">
                    <button
                        @click="activeTab = 'movies'"
                        class="px-5 py-2 rounded-xl font-extrabold text-xs transition-all cursor-pointer"
                        :class="activeTab === 'movies' ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/30' : 'text-slate-400 hover:text-white'"
                    >
                        {{ isRTL ? 'أحدث الأفلام المضافة' : 'Recently Added Movies' }}
                    </button>
                    <button
                        @click="activeTab = 'series'"
                        class="px-5 py-2 rounded-xl font-extrabold text-xs transition-all cursor-pointer"
                        :class="activeTab === 'series' ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/30' : 'text-slate-400 hover:text-white'"
                    >
                        {{ isRTL ? 'المسلسلات الأكثر تقييماً' : 'Top Rated TV Series' }}
                    </button>
                </div>

                <Link
                    :href="activeTab === 'movies' ? '/movies' : '/series'"
                    class="flex items-center gap-1.5 text-xs font-bold text-cyan-400 hover:text-cyan-300 transition-colors"
                >
                    <span>{{ isRTL ? 'تصفح الكل في المكتبة' : 'View Complete Library' }}</span>
                    <component :is="isRTL ? ChevronLeft : ChevronRight" class="w-3.5 h-3.5" />
                </Link>
            </div>

            <!-- Movies Grid Tab -->
            <div v-if="activeTab === 'movies'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-6">
                <MediaCard
                    v-for="item in recentlyAddedMovies"
                    :key="`m-${item.id}`"
                    :item="item"
                    type="movie"
                    @play="play(item)"
                    @click="openDetail(item)"
                />
            </div>

            <!-- Series Grid Tab -->
            <div v-if="activeTab === 'series'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-6">
                <MediaCard
                    v-for="item in popularSeries"
                    :key="`s-${item.id}`"
                    :item="item"
                    type="series"
                    @play="openDetail(item)"
                    @click="openDetail(item)"
                />
            </div>
        </div>

        <!-- Detail Modal -->
        <MediaDetailModal
            v-if="selectedMedia"
            :item="selectedMedia"
            :type="selectedMedia.seasons ? 'series' : 'movie'"
            :is-open="isModalOpen"
            @close="isModalOpen = false"
            @play="(item) => { isModalOpen = false; play(item); }"
        />
    </AppLayout>
</template>
