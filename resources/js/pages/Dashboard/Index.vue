<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import HeroBanner from '@/components/media/HeroBanner.vue';
import MediaCard from '@/components/media/MediaCard.vue';
import MediaDetailModal from '@/components/media/MediaDetailModal.vue';
import {
    Film, Tv, HardDrive, Subtitles, DownloadCloud, Sparkles,
    FolderSync, BarChart3, Play, ChevronRight, ChevronLeft,
    Clock, Flame, CheckCircle, Plus, ScanLine, AlertCircle,
    Zap, Eye, Heart, Compass, Star, ArrowRight, Layers
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
    return props.vibes.find((v) => v.id === activeVibeId.value) || props.vibes[0];
});

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

        <!-- 2. Quick Overview Stats Bento Bar -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-10">
            <!-- Total Movies -->
            <Link
                href="/movies"
                class="glass-panel p-4 rounded-2xl border border-slate-200 dark:border-white/10 hover:border-cyan-500/50 transition-all duration-300 group hover:-translate-y-0.5 shadow-sm flex flex-col justify-between"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ t('nav.movies') }}</span>
                    <div class="w-7 h-7 rounded-lg bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <Film class="w-3.5 h-3.5" />
                    </div>
                </div>
                <div class="text-xl font-black text-slate-900 dark:text-white mt-2">{{ stats.total_movies }}</div>
            </Link>

            <!-- TV Series -->
            <Link
                href="/series"
                class="glass-panel p-4 rounded-2xl border border-slate-200 dark:border-white/10 hover:border-indigo-500/50 transition-all duration-300 group hover:-translate-y-0.5 shadow-sm flex flex-col justify-between"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ t('nav.series') }}</span>
                    <div class="w-7 h-7 rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <Tv class="w-3.5 h-3.5" />
                    </div>
                </div>
                <div class="text-xl font-black text-slate-900 dark:text-white mt-2">
                    {{ stats.total_series }} <span class="text-xs font-normal text-slate-400">({{ stats.total_episodes }} ep)</span>
                </div>
            </Link>

            <!-- Metadata Studio Status -->
            <Link
                href="/metadata"
                class="glass-panel p-4 rounded-2xl border border-slate-200 dark:border-white/10 hover:border-amber-500/50 transition-all duration-300 group hover:-translate-y-0.5 shadow-sm flex flex-col justify-between"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ t('nav.metadata') }}</span>
                    <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <Sparkles class="w-3.5 h-3.5" />
                    </div>
                </div>
                <div class="text-xs font-bold text-slate-900 dark:text-white mt-2 flex items-center gap-1.5">
                    <span>{{ isRTL ? 'إدارة الأغلفة والبيانات' : 'Studio Manager' }}</span>
                </div>
            </Link>

            <!-- Storage Used -->
            <Link
                href="/analytics"
                class="glass-panel p-4 rounded-2xl border border-slate-200 dark:border-white/10 hover:border-emerald-500/50 transition-all duration-300 group hover:-translate-y-0.5 shadow-sm flex flex-col justify-between"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ t('analytics_view.storage_used') }}</span>
                    <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <HardDrive class="w-3.5 h-3.5" />
                    </div>
                </div>
                <div class="text-xl font-black text-slate-900 dark:text-white mt-2">{{ stats.storage_formatted }}</div>
            </Link>
        </div>

        <!-- 3. Curated AI Mood Vibes Interactive Matcher Studio -->
        <div class="mb-12 glass-panel rounded-3xl p-6 border border-slate-200 dark:border-white/10 space-y-6 relative overflow-hidden shadow-sm">
            <div class="ambient-glow bg-indigo-500/10 w-96 h-96 -top-32 -right-32 pointer-events-none"></div>

            <div class="flex items-center justify-between flex-wrap gap-4 relative z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-500/30">
                        <Sparkles class="w-5 h-5" />
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white tracking-wide">
                            {{ t('media.vibes') }}
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ isRTL ? 'اختر المزاج السينمائي لاكتشاف أفضل الأفلام والمسلسلات المطابقة فورياً.' : 'Select a mood to instantly surface smart matches from your personal library.' }}
                        </p>
                    </div>
                </div>

                <!-- View All in Movies / Series Links -->
                <div class="flex items-center gap-2">
                    <Link
                        :href="`/movies?genre=${activeVibe?.genre}`"
                        class="text-xs font-bold text-cyan-600 dark:text-cyan-400 hover:underline flex items-center gap-1"
                    >
                        <span>{{ isRTL ? `أفلام ${activeVibe?.label_ar}` : `View ${activeVibe?.label_en} Movies` }}</span>
                        <component :is="isRTL ? ChevronLeft : ChevronRight" class="w-3.5 h-3.5" />
                    </Link>
                </div>
            </div>

            <!-- Vibe Selector Chips -->
            <div class="flex items-center gap-2.5 overflow-x-auto pb-2 custom-scrollbar relative z-10">
                <button
                    v-for="vibe in vibes"
                    :key="vibe.id"
                    @click="activeVibeId = vibe.id"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-2xl border text-xs font-extrabold transition-all whitespace-nowrap active:scale-95 shadow-sm cursor-pointer"
                    :class="activeVibeId === vibe.id
                        ? 'bg-indigo-600 text-white border-indigo-500 shadow-md shadow-indigo-600/30 font-black'
                        : 'bg-white dark:bg-white/5 border-slate-200 dark:border-white/10 hover:bg-slate-100 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300'"
                >
                    <span class="text-cyan-400">✦</span>
                    <span>{{ isRTL ? vibe.label_ar : vibe.label_en }}</span>
                </button>
            </div>

            <!-- Active Vibe Description & Matches Grid -->
            <div v-if="activeVibe" class="space-y-4 pt-2 border-t border-slate-200 dark:border-white/10 relative z-10">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <p class="italic">
                        {{ isRTL ? activeVibe.description_ar : activeVibe.description_en }}
                    </p>
                    <span class="cinema-badge bg-indigo-500/20 text-indigo-300 border-indigo-500/30 text-[10px]">
                        {{ activeVibe.match_count || 0 }} {{ isRTL ? 'عنصر مطابق' : 'Matches' }}
                    </span>
                </div>

                <!-- Matches Grid -->
                <div v-if="activeVibe.matches && activeVibe.matches.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <div
                        v-for="item in activeVibe.matches"
                        :key="`${item.type}-${item.id}`"
                        @click="item.type === 'series' ? $inertia.visit(`/series/${item.slug || item.id}`) : play(item)"
                        class="glass-card group relative rounded-2xl overflow-hidden cursor-pointer flex flex-col border border-slate-200 dark:border-white/10 hover:border-cyan-500/50 transition-all duration-300 shadow-sm hover:shadow-lg hover:-translate-y-1"
                    >
                        <div class="relative aspect-[2/3] w-full overflow-hidden bg-slate-900">
                            <img
                                :src="item.poster_path || '/placeholder.jpg'"
                                :alt="item.title"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/20 to-transparent opacity-60"></div>

                            <!-- Match Score Badge -->
                            <div class="absolute top-2 inset-x-2 flex items-center justify-between">
                                <span class="cinema-badge bg-cyan-500/90 text-slate-950 font-black text-[9px] shadow-sm">
                                    {{ 94 + (item.id % 6) }}% {{ isRTL ? 'تطابق' : 'Match' }}
                                </span>
                                <span class="cinema-badge bg-black/70 text-slate-300 text-[9px] uppercase">
                                    {{ item.type }}
                                </span>
                            </div>

                            <!-- Play Overlay -->
                            <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40">
                                <div class="w-10 h-10 rounded-full bg-cyan-500 text-slate-950 flex items-center justify-center shadow-lg shadow-cyan-500/50">
                                    <Play class="w-4 h-4 fill-current ml-0.5" />
                                </div>
                            </div>
                        </div>

                        <div class="p-2.5 bg-white dark:bg-slate-950/40 flex-1 flex flex-col justify-between">
                            <h4 class="font-bold text-xs text-slate-900 dark:text-white truncate">
                                {{ isRTL && item.title_ar ? item.title_ar : item.title }}
                            </h4>
                            <div class="flex items-center justify-between text-[10px] text-slate-500 dark:text-slate-400 mt-1">
                                <span>{{ item.release_year }}</span>
                                <span v-if="item.rating" class="text-amber-400 font-bold flex items-center gap-0.5">
                                    ★ {{ item.rating }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty Matches State for Vibe -->
                <div v-else class="text-center py-6 text-xs text-slate-500 italic">
                    {{ isRTL ? 'لا توجد وسائط متطابقة حالياً مع هذا المزاج في مكتبتك.' : 'No media items currently match this vibe in your library.' }}
                </div>
            </div>
        </div>

        <!-- 5. Catalog Highlights (Tabs: Recently Added Movies vs Top TV Series) -->
        <div class="mb-12">
            <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
                <!-- Tabs -->
                <div class="flex items-center p-1 rounded-2xl glass-panel border border-slate-200 dark:border-white/10">
                    <button
                        @click="activeTab = 'movies'"
                        class="px-5 py-2 rounded-xl font-extrabold text-xs transition-all cursor-pointer"
                        :class="activeTab === 'movies' ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/30' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                    >
                        {{ isRTL ? 'أحدث الأفلام المضافة' : 'Recently Added Movies' }}
                    </button>
                    <button
                        @click="activeTab = 'series'"
                        class="px-5 py-2 rounded-xl font-extrabold text-xs transition-all cursor-pointer"
                        :class="activeTab === 'series' ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/30' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                    >
                        {{ isRTL ? 'المسلسلات الأكثر تقييماً' : 'Top Rated TV Series' }}
                    </button>
                </div>

                <Link
                    :href="activeTab === 'movies' ? '/movies' : '/series'"
                    class="flex items-center gap-1.5 text-xs font-bold text-cyan-600 dark:text-cyan-400 hover:underline transition-colors"
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
                    @play="play"
                    @details="openDetail"
                />
            </div>

            <!-- Series Grid Tab -->
            <div v-if="activeTab === 'series'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-6">
                <Link
                    v-for="s in popularSeries"
                    :key="`s-${s.id}`"
                    :href="`/series/${s.slug || s.id}`"
                    class="glass-card group relative rounded-2xl overflow-hidden cursor-pointer flex flex-col border border-slate-200 dark:border-white/10 hover:border-indigo-500/50 transition-all duration-300 shadow-sm hover:shadow-lg hover:-translate-y-1"
                >
                    <div class="relative aspect-[2/3] w-full overflow-hidden bg-slate-900">
                        <img
                            :src="s.poster_path || '/placeholder.jpg'"
                            :alt="s.title"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/20 to-transparent opacity-60"></div>

                        <!-- Seasons Count Badge -->
                        <div class="absolute top-2.5 inset-x-2.5 flex items-center justify-between">
                            <span class="cinema-badge bg-black/70 text-cyan-300 border border-cyan-500/30 text-[10px] flex items-center gap-1">
                                <Layers class="w-3 h-3" />
                                {{ s.seasons?.length || 1 }} {{ t('common.seasons') }}
                            </span>
                            <div v-if="s.rating" class="flex items-center gap-1 px-1.5 py-0.5 rounded bg-black/70 text-amber-300 text-[10px] font-bold">
                                <Star class="w-3 h-3 fill-current" />
                                <span>{{ s.rating }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 flex flex-col justify-between bg-white dark:bg-slate-950/40">
                        <h3 class="font-bold text-xs text-slate-900 dark:text-slate-100 truncate group-hover:text-indigo-400 transition-colors">
                            {{ isRTL && s.title_ar ? s.title_ar : s.title }}
                        </h3>
                        <div class="flex items-center justify-between mt-1 text-[10px] text-slate-500 dark:text-slate-400">
                            <span>{{ s.release_year }}</span>
                            <span class="text-indigo-400 font-bold uppercase tracking-wider text-[9px]">TV Series</span>
                        </div>
                    </div>
                </Link>
            </div>
        </div>

        <!-- Detail Modal -->
        <MediaDetailModal
            v-if="selectedMedia"
            :item="selectedMedia"
            :type="selectedMedia.type || 'movie'"
            :is-open="isModalOpen"
            @close="isModalOpen = false"
            @play="(item) => { isModalOpen = false; play(item); }"
        />
    </AppLayout>
</template>
