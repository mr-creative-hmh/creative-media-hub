<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/components/layout/AppLayout.vue';
import ContinueWatchingBar from '@/components/layout/ContinueWatchingBar.vue';
import { useI18n } from '@/i18n/useI18n';
import { 
    Layers, Search, Film, Star, Calendar, Play, 
    Sparkles, ArrowRight, ShieldCheck, Video, SlidersHorizontal
} from 'lucide-vue-next';

interface MovieItem {
    id: number;
    title: string;
    title_ar?: string;
    slug: string;
    release_year?: number;
    rating?: number;
    poster_path?: string;
    backdrop_path?: string;
    resolution?: string;
    runtime_minutes?: number;
}

interface MovieCollection {
    name: string;
    slug: string;
    movies_count: number;
    year_span?: string;
    avg_rating?: number;
    poster_path?: string;
    backdrop_path?: string;
    movies: MovieItem[];
}

const props = defineProps<{
    collections: MovieCollection[];
    filters: {
        search?: string;
    };
    total_collections: number;
    total_franchise_movies: number;
}>();

const { t, isRTL } = useI18n();
const searchQuery = ref(props.filters.search || '');

const handleSearch = () => {
    router.get('/collections', { search: searchQuery.value }, { preserveState: true, replace: true });
};

const clearSearch = () => {
    searchQuery.value = '';
    handleSearch();
};
</script>

<template>
    <AppLayout v-slot="{ play }">
        <Head :title="isRTL ? 'سلاسل الأفلام ومجموعات البوكس سيت' : 'Movie Collections & Boxsets'" />

        <div class="space-y-8 pb-12">
            <!-- Hero Banner -->
            <section class="relative rounded-3xl overflow-hidden glass-panel border border-cyan-500/20 p-6 lg:p-10 bg-gradient-to-br from-cyan-950/40 via-slate-900/60 to-purple-950/30">
                <div class="ambient-glow bg-cyan-500/10 w-96 h-96 -top-20 -left-20 pointer-events-none"></div>
                <div class="ambient-glow bg-purple-500/10 w-96 h-96 -bottom-20 -right-20 pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                    <div class="space-y-3 max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 text-xs font-bold uppercase tracking-wider">
                            <Layers class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'سلاسل الأفلام الكاملة' : 'Franchise Boxsets' }}</span>
                        </div>
                        <h1 class="text-3xl lg:text-4xl font-black text-white tracking-tight">
                            {{ isRTL ? 'سلاسل ومجموعات الأفلام العالمية' : 'Movie Collections & Sagas' }}
                        </h1>
                        <p class="text-sm lg:text-base text-slate-300 leading-relaxed">
                            {{ isRTL 
                                ? 'استكشف سلاسل الأفلام الكاملة مرتبة بالتسلسل الزمني للإنتاج (مثل هاري بوتر، سيد الخواتم، مارفل، فاست آند فيوريوس، وجون ويك).' 
                                : 'Browse entire movie franchises and boxsets chronologically organized (Harry Potter, Lord of the Rings, MCU, Fast & Furious, John Wick, Star Wars).' 
                            }}
                        </p>
                    </div>

                    <!-- Stats Badges -->
                    <div class="flex items-center gap-4 shrink-0">
                        <div class="p-4 rounded-2xl bg-white/[0.04] border border-white/10 text-center min-w-[110px]">
                            <div class="text-2xl font-black text-cyan-400">{{ total_collections }}</div>
                            <div class="text-[11px] text-slate-400 uppercase font-semibold mt-0.5">{{ isRTL ? 'سلسلة أفلام' : 'Collections' }}</div>
                        </div>
                        <div class="p-4 rounded-2xl bg-white/[0.04] border border-white/10 text-center min-w-[110px]">
                            <div class="text-2xl font-black text-purple-400">{{ total_franchise_movies }}</div>
                            <div class="text-[11px] text-slate-400 uppercase font-semibold mt-0.5">{{ isRTL ? 'فيلماً مجمعاً' : 'Total Films' }}</div>
                        </div>
                    </div>
                </div>

                <!-- Search Input Bar -->
                <div class="relative z-10 mt-6 max-w-xl">
                    <div class="relative">
                        <Search class="absolute top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-slate-400" :class="isRTL ? 'right-4' : 'left-4'" />
                        <input
                            v-model="searchQuery"
                            @keyup.enter="handleSearch"
                            type="text"
                            :placeholder="isRTL ? 'ابحث عن اسم السلسلة (مثال: هاري بوتر، Fast & Furious)...' : 'Search collections (e.g. Harry Potter, Fast & Furious, Marvel)...'"
                            class="w-full py-3 rounded-2xl bg-black/40 border border-white/15 text-sm text-white placeholder-slate-400 focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 transition-all shadow-inner"
                            :class="isRTL ? 'pr-11 pl-20' : 'pl-11 pr-20'"
                        />
                        <button
                            @click="handleSearch"
                            class="absolute top-1/2 -translate-y-1/2 px-3.5 py-1.5 rounded-xl bg-cyan-500 text-slate-950 text-xs font-black hover:bg-cyan-400 transition-all cursor-pointer"
                            :class="isRTL ? 'left-2' : 'right-2'"
                        >
                            {{ isRTL ? 'بحث' : 'Search' }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- In-Progress Continue Watching Bar (Collections Only) -->
            <ContinueWatchingBar type="collection" @play="play" />

            <!-- Collections Grid -->
            <div v-if="collections.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <Link
                    v-for="col in collections"
                    :key="col.slug"
                    :href="`/collections/${col.slug}`"
                    class="group relative rounded-3xl overflow-hidden glass-panel border border-white/10 hover:border-cyan-500/40 transition-all duration-300 hover:shadow-2xl hover:shadow-cyan-500/10 flex flex-col cursor-pointer bg-slate-900/40"
                >
                    <!-- Collection Backdrop / Cover -->
                    <div class="relative aspect-video w-full overflow-hidden bg-slate-950">
                        <img
                            v-if="col.backdrop_path || col.poster_path"
                            :src="col.backdrop_path || col.poster_path"
                            :alt="col.name"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                            loading="lazy"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-slate-900 to-slate-950 text-slate-700">
                            <Film class="w-12 h-12" />
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/50 to-transparent"></div>

                        <!-- Pill Badge for movie count -->
                        <div class="absolute top-3 right-3 flex items-center gap-1.5 px-3 py-1 rounded-full bg-black/70 backdrop-blur-md border border-white/20 text-xs font-black text-cyan-300 shadow-lg">
                            <Film class="w-3.5 h-3.5" />
                            <span>{{ col.movies_count }} {{ isRTL ? (col.movies_count > 10 ? 'فيلماً' : 'أفلام') : (col.movies_count === 1 ? 'Movie' : 'Movies') }}</span>
                        </div>

                        <!-- Year Span -->
                        <div v-if="col.year_span" class="absolute bottom-3 left-3 flex items-center gap-1 text-xs font-semibold text-slate-300 bg-black/60 backdrop-blur-md px-2.5 py-0.5 rounded-lg border border-white/10">
                            <Calendar class="w-3 h-3 text-cyan-400" />
                            <span>{{ col.year_span }}</span>
                        </div>
                    </div>

                    <!-- Collection Details -->
                    <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                        <div class="space-y-1.5">
                            <h3 class="text-base font-bold text-white group-hover:text-cyan-400 transition-colors line-clamp-1">
                                {{ col.name }}
                            </h3>

                            <!-- Movie titles preview chips -->
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                <span
                                    v-for="(m, idx) in col.movies.slice(0, 3)"
                                    :key="m.id"
                                    class="text-[10px] font-medium px-2 py-0.5 rounded-md bg-white/[0.05] border border-white/10 text-slate-300 truncate max-w-[140px]"
                                >
                                    {{ m.title }}
                                </span>
                                <span
                                    v-if="col.movies.length > 3"
                                    class="text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-cyan-500/20 text-cyan-300 border border-cyan-500/30"
                                >
                                    +{{ col.movies.length - 3 }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="flex items-center justify-between pt-2 border-t border-white/5 text-xs text-slate-400">
                            <div class="flex items-center gap-1 font-bold text-amber-400">
                                <Star class="w-3.5 h-3.5 fill-amber-400" />
                                <span>{{ col.avg_rating || '7.5' }}</span>
                            </div>

                            <span class="inline-flex items-center gap-1 text-cyan-400 font-bold group-hover:translate-x-1 transition-transform">
                                <span>{{ isRTL ? 'عرض السلسلة' : 'Explore Saga' }}</span>
                                <ArrowRight class="w-3.5 h-3.5" :class="isRTL ? 'rotate-180' : ''" />
                            </span>
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-16 px-4 rounded-3xl glass-panel border border-white/10 space-y-4 max-w-lg mx-auto">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400">
                    <Layers class="w-8 h-8" />
                </div>
                <h3 class="text-lg font-bold text-white">
                    {{ isRTL ? 'لم يتم العثور على سلاسل أفلام' : 'No Movie Collections Found' }}
                </h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    {{ isRTL 
                        ? 'عند قيامك بفهرسة مكتبة أفلامك التي تحتوي على أفلام ذات أجزاء متعددة (مثل هاري بوتر، حرب النجوم، فاست آند فيوريوس)، سيتم تجميعها تلقائياً هنا.' 
                        : 'When scanning your movie library with multi-part franchises (Harry Potter, Star Wars, Fast & Furious), they will automatically be grouped into collections here.' 
                    }}
                </p>
                <Link
                    href="/scanner"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-cyan-500 text-slate-950 font-bold text-xs hover:bg-cyan-400 transition-all cursor-pointer"
                >
                    <Sparkles class="w-4 h-4" />
                    <span>{{ isRTL ? 'تشغيل الفاحص الذكي' : 'Run Virtual Scanner' }}</span>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
