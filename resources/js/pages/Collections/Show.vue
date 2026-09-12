<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/components/layout/AppLayout.vue';
import MediaDetailModal from '@/components/media/MediaDetailModal.vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Layers, Film, Star, Calendar, Clock, Play,
    ArrowLeft, ArrowRight, ShieldCheck, Sparkles, Tv, CheckCircle2,
    Info, Check, Search
} from 'lucide-vue-next';

interface MovieDetail {
    id: number;
    type?: string;
    title: string;
    title_ar?: string;
    original_title?: string;
    slug: string;
    slug_url: string;
    release_year?: number;
    rating?: number;
    poster_path?: string;
    backdrop_path?: string;
    overview?: string;
    overview_ar?: string;
    runtime_minutes?: number;
    resolution?: string;
    video_codec?: string;
    audio_codec?: string;
    file_path?: string;
    collection_name?: string;
    is_favorite?: boolean;
    trailer_url?: string;
    genres?: any[];
    people?: any[];
    directors?: any[];
    actors?: any[];
    subtitles?: any[];
    subtitles_count?: number;
    watch_history?: any;
    stream_url: string;
    remux_url: string;
}

interface CollectionDetail {
    name: string;
    slug: string;
    movies_count: number;
    total_parts?: number;
    is_complete?: boolean;
    completion_percentage?: number;
    missing_parts?: any[];
    year_span?: string;
    poster_path?: string;
    backdrop_path?: string;
    avg_rating?: number;
    movies: MovieDetail[];
}

const props = defineProps<{
    collection: CollectionDetail;
}>();

const { t, isRTL } = useI18n();

// Detail Modal state
const selectedDetailMovie = ref<any | null>(null);

const openMovieDetails = (movie: MovieDetail) => {
    selectedDetailMovie.value = movie;
};

const playCollectionMovie = (movie: any, playFn: (item: any, playlist?: any[]) => void) => {
    if (!movie) return;
    const playlist = (props.collection.movies || []).map((m: any) => ({
        ...m,
        type: 'movie',
        watchable_id: m.id,
        watchable_type: 'media_item',
    }));
    playFn(movie, playlist);
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
    <AppLayout v-slot="{ play }">
        <Head :title="`${collection.name} | ${isRTL ? 'سلسلة أفلام' : 'Movie Collection'}`" />

        <div class="space-y-8 pb-16">
            <!-- Top Back Navigation -->
            <div>
                <Link
                    href="/collections"
                    class="inline-flex items-center gap-2 text-xs font-bold text-slate-400 hover:text-cyan-400 transition-colors group cursor-pointer"
                >
                    <ArrowLeft v-if="!isRTL" class="w-4 h-4 transition-transform group-hover:-translate-x-1" />
                    <ArrowRight v-else class="w-4 h-4 transition-transform group-hover:translate-x-1" />
                    <span>{{ isRTL ? 'العودة لجميع سلاسل الأفلام' : 'Back to Movie Collections' }}</span>
                </Link>
            </div>

            <!-- Hero Banner for the Collection -->
            <div class="relative overflow-hidden rounded-3xl p-8 lg:p-12 border border-white/10 bg-gradient-to-br from-slate-900 via-[#0a0f1d] to-slate-950 shadow-2xl">
                <!-- Ambient Backdrop Glow -->
                <div
                    v-if="collection.backdrop_path"
                    class="absolute inset-0 bg-cover bg-center opacity-25 filter blur-sm scale-105 pointer-events-none"
                    :style="`background-image: url('${collection.backdrop_path}')`"
                ></div>
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/80 to-transparent pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row items-center md:items-end justify-between gap-8">
                    <!-- Poster + Meta -->
                    <div class="flex flex-col sm:flex-row items-center sm:items-end gap-6 text-center sm:text-left">
                        <div class="w-32 lg:w-40 aspect-[2/3] rounded-2xl overflow-hidden shadow-2xl bg-slate-900 border border-white/10 shrink-0 transform -rotate-1 hover:rotate-0 transition-transform">
                            <img
                                v-if="collection.poster_path"
                                :src="collection.poster_path"
                                :alt="collection.name"
                                class="w-full h-full object-cover"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-slate-700 bg-slate-900">
                                <Film class="w-10 h-10" />
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 text-xs font-bold">
                                    <Layers class="w-3.5 h-3.5" />
                                    <span>{{ isRTL ? 'سلسلة أفلام مجمعة' : 'Franchise Boxset' }}</span>
                                </div>
                                <div v-if="collection.is_complete" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-xs font-bold">
                                    <CheckCircle2 class="w-3.5 h-3.5" />
                                    <span>{{ isRTL ? 'السلسلة مكتملة 100%' : '100% Complete Franchise' }}</span>
                                </div>
                                <div v-else class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/20 border border-amber-500/30 text-amber-300 text-xs font-bold">
                                    <Clock class="w-3.5 h-3.5" />
                                    <span>{{ isRTL ? `مكتمل ${collection.completion_percentage}%` : `${collection.completion_percentage}% Owned` }}</span>
                                </div>
                            </div>

                            <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                                {{ collection.name }}
                            </h1>

                            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-4 text-xs font-semibold text-slate-300">
                                <div class="flex items-center gap-1.5">
                                    <Film class="w-3.5 h-3.5 text-cyan-400" />
                                    <span>{{ collection.movies_count }} {{ isRTL ? (collection.movies_count > 10 ? 'فيلماً مملوكاً' : 'أفلام مملوكة') : 'Movies Owned' }}</span>
                                    <span v-if="collection.total_parts && collection.total_parts > collection.movies_count" class="text-slate-400">
                                        ({{ isRTL ? `من أصل ${collection.total_parts}` : `of ${collection.total_parts}` }})
                                    </span>
                                </div>

                                <div v-if="collection.year_span" class="flex items-center gap-1.5">
                                    <Calendar class="w-3.5 h-3.5 text-cyan-400" />
                                    <span>{{ collection.year_span }}</span>
                                </div>

                                <div v-if="collection.avg_rating" class="flex items-center gap-1.5 text-amber-400 font-bold">
                                    <Star class="w-3.5 h-3.5 fill-amber-400" />
                                    <span>{{ collection.avg_rating }} / 10</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Play First Movie CTA -->
                    <div class="shrink-0 flex items-center gap-3">
                        <button
                            v-if="collection.movies.length > 0"
                            @click="playCollectionMovie(collection.movies[0], play)"
                            class="px-6 py-3.5 rounded-2xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs sm:text-sm flex items-center gap-2.5 shadow-xl shadow-cyan-500/25 transition-all cursor-pointer transform hover:scale-105"
                        >
                            <Play class="w-4 h-4 fill-slate-950" />
                            <span>{{ isRTL ? 'بدء تشغيل السلسلة من الجزء الأول' : 'Play Franchise From Part 1' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Movies in this Collection (Chronological) -->
            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="space-y-1">
                        <h2 class="text-xl lg:text-2xl font-black text-white tracking-tight">
                            {{ isRTL ? 'أفلام السلسلة المتوفرة بمكتبتك' : 'Owned Movies in this Saga' }}
                        </h2>
                        <p class="text-xs text-slate-400">
                            {{ isRTL ? 'مرتبة بالتسلسل الزمني للإنتاج' : 'Organized chronologically by release year' }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                    <div
                        v-for="(movie, idx) in collection.movies"
                        :key="movie.id"
                        class="group relative rounded-2xl overflow-hidden glass-panel border border-white/10 hover:border-cyan-500/50 transition-all duration-300 hover:shadow-2xl hover:shadow-cyan-500/10 flex flex-col bg-slate-900/60 cursor-pointer"
                        @click="openMovieDetails(movie)"
                    >
                        <!-- Poster Thumbnail -->
                        <div class="relative aspect-[2/3] w-full overflow-hidden bg-slate-950">
                            <img
                                v-if="movie.poster_path"
                                :src="movie.poster_path"
                                :alt="movie.title"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                loading="lazy"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-slate-700 bg-slate-950">
                                <Film class="w-10 h-10" />
                            </div>

                            <!-- Chronological Index Badge -->
                            <div class="absolute top-2 left-2 w-7 h-7 rounded-xl bg-cyan-500/90 text-slate-950 font-black text-xs flex items-center justify-center shadow-lg">
                                #{{ idx + 1 }}
                            </div>

                            <!-- Resolution Badge -->
                            <div v-if="movie.resolution" class="absolute top-2 right-2 px-2 py-0.5 rounded-md bg-black/70 backdrop-blur-md border border-white/20 text-[10px] font-black text-cyan-300">
                                {{ movie.resolution }}
                            </div>

                            <!-- Hover Quick Play Overlay -->
                            <div class="absolute inset-0 bg-black/60 backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-3">
                                <button
                                    @click.stop="playCollectionMovie(movie, play)"
                                    class="w-12 h-12 rounded-2xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 flex items-center justify-center shadow-xl shadow-cyan-500/30 transform hover:scale-110 transition-all cursor-pointer"
                                    :title="isRTL ? 'تشغيل' : 'Play Now'"
                                >
                                    <Play class="w-5 h-5 fill-slate-950 ml-0.5" />
                                </button>
                                <button
                                    @click.stop="openMovieDetails(movie)"
                                    class="w-10 h-10 rounded-2xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center border border-white/20 transform hover:scale-110 transition-all cursor-pointer"
                                    :title="isRTL ? 'تفاصيل' : 'Details'"
                                >
                                    <Info class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-4 flex-1 flex flex-col justify-between space-y-2">
                            <div>
                                <h3 class="text-sm font-bold text-white group-hover:text-cyan-400 transition-colors line-clamp-1" :title="movie.title">
                                    {{ isRTL && movie.title_ar ? movie.title_ar : movie.title }}
                                </h3>
                                <p v-if="isRTL && movie.title_ar" class="text-[11px] text-slate-400 line-clamp-1">
                                    {{ movie.title }}
                                </p>
                            </div>

                            <div class="flex items-center justify-between text-xs text-slate-400 pt-2 border-t border-white/5">
                                <span class="font-semibold">{{ movie.release_year }}</span>
                                <div v-if="movie.rating" class="flex items-center gap-1 font-bold text-amber-400">
                                    <Star class="w-3 h-3 fill-amber-400" />
                                    <span>{{ movie.rating }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Missing Franchise Titles (Media Scout Integration) -->
            <section v-if="collection.missing_parts && collection.missing_parts.length > 0" class="space-y-4 pt-6 border-t border-white/10">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="inline-flex items-center gap-2 text-xs font-bold text-amber-400 uppercase tracking-wider">
                            <Sparkles class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'استكشاف الأجزاء المتبقية عبر ميديا سكاوت' : 'Franchise Expansion via Media Scout' }}</span>
                        </div>
                        <h2 class="text-xl lg:text-2xl font-black text-white tracking-tight">
                            {{ isRTL ? 'أجزاء غير متوفرة في مكتبتك' : 'Missing From Your Library' }}
                        </h2>
                    </div>
                    <Link
                        :href="`/scout?query=${encodeURIComponent(collection.name.replace(' Collection', ''))}`"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-500/20 border border-amber-500/30 text-amber-300 font-bold text-xs hover:bg-amber-500/30 transition-all cursor-pointer"
                    >
                        <Search class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'بحث عن السلسلة في سكاوت' : 'Scout Entire Franchise' }}</span>
                    </Link>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                    <div
                        v-for="part in collection.missing_parts"
                        :key="part.tmdb_id || part.title"
                        class="relative rounded-2xl overflow-hidden glass-panel border border-amber-500/20 bg-slate-900/60 flex flex-col group p-2.5 space-y-3"
                    >
                        <div class="relative aspect-[2/3] w-full rounded-xl overflow-hidden bg-slate-950 border border-white/5">
                            <img
                                v-if="part.poster_path"
                                :src="part.poster_path"
                                :alt="part.title"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300 filter grayscale-[25%] group-hover:grayscale-0"
                                loading="lazy"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-slate-700 bg-slate-950">
                                <Film class="w-8 h-8" />
                            </div>
                            <div class="absolute top-2 right-2 px-2 py-0.5 rounded-md bg-black/80 border border-amber-500/30 text-[10px] font-black text-amber-300">
                                {{ part.release_year || 'TBA' }}
                            </div>
                        </div>

                        <div class="space-y-1.5 flex-1 flex flex-col justify-between">
                            <div>
                                <h4 class="text-xs font-bold text-white line-clamp-1 group-hover:text-amber-400 transition-colors" :title="part.title">
                                    {{ isRTL && part.title_ar ? part.title_ar : part.title }}
                                </h4>
                                <p v-if="isRTL && part.title_ar" class="text-[10px] text-slate-400 line-clamp-1">
                                    {{ part.title }}
                                </p>
                            </div>

                            <Link
                                :href="`/scout?query=${encodeURIComponent(part.title)}`"
                                class="w-full py-1.5 px-2 rounded-lg bg-amber-500 text-slate-950 text-[11px] font-black hover:bg-amber-400 transition-all flex items-center justify-center gap-1 cursor-pointer mt-2"
                            >
                                <Search class="w-3 h-3" />
                                <span>{{ isRTL ? 'بحث وتحميل' : 'Scout Torrent' }}</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Media Detail Modal for Collection Movies -->
            <MediaDetailModal
                v-if="selectedDetailMovie"
                :item="selectedDetailMovie"
                @close="selectedDetailMovie = null"
                @play="(m: any) => playCollectionMovie(m, play)"
                @toggle-favorite="handleToggleFavorite"
            />
        </div>
    </AppLayout>
</template>