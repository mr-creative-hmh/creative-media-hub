<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/components/layout/AppLayout.vue';
import MediaDetailModal from '@/components/media/MediaDetailModal.vue';
import { useI18n } from '@/i18n/useI18n';
import { 
    Layers, Film, Star, Calendar, Clock, Play, 
    ArrowLeft, ArrowRight, ShieldCheck, Sparkles, Tv, CheckCircle2,
    Info, Check
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
                            <div v-else class="w-full h-full flex items-center justify-center text-slate-700">
                                <Layers class="w-10 h-10 text-cyan-400/60" />
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-xs font-semibold uppercase tracking-wider">
                                <Sparkles class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'سلسلة أفلام سينمائية' : 'Movie Franchise Boxset' }}</span>
                            </div>

                            <h1 class="text-3xl lg:text-4xl font-black text-white tracking-tight">
                                {{ collection.name }}
                            </h1>

                            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-4 text-xs font-medium text-slate-300">
                                <span class="flex items-center gap-1.5 text-cyan-300 font-bold bg-cyan-500/10 px-2.5 py-1 rounded-lg border border-cyan-500/20">
                                    <Film class="w-4 h-4" />
                                    <span>{{ collection.movies_count }} {{ isRTL ? 'أفلام متوفرة' : 'Films' }}</span>
                                </span>

                                <span v-if="collection.year_span" class="flex items-center gap-1.5 bg-white/[0.05] px-2.5 py-1 rounded-lg border border-white/5 text-slate-300">
                                    <Calendar class="w-3.5 h-3.5 text-slate-400" />
                                    <span>{{ collection.year_span }}</span>
                                </span>

                                <span v-if="collection.avg_rating" class="flex items-center gap-1.5 text-amber-400 font-bold bg-amber-500/10 px-2.5 py-1 rounded-lg border border-amber-500/20">
                                    <Star class="w-3.5 h-3.5 fill-amber-400" />
                                    <span>{{ collection.avg_rating }} / 10</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Hero Quick Action -->
                    <div v-if="collection.movies.length > 0" class="shrink-0">
                        <button
                            @click="playCollectionMovie(collection.movies[0], play)"
                            class="px-6 py-3.5 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-sm shadow-xl shadow-cyan-500/20 active:scale-95 transition-all flex items-center gap-2.5 cursor-pointer"
                        >
                            <Play class="w-4 h-4 fill-current" />
                            <span>{{ isRTL ? 'بدء مشاهدة السلسلة' : 'Start Franchise' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Chronological Movie List (Saga Progression) -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-white tracking-wide flex items-center gap-2">
                        <Layers class="w-5 h-5 text-cyan-400" />
                        <span>{{ isRTL ? 'تسلسل أفلام السلسلة' : 'Chronological Franchise Sagas' }}</span>
                    </h2>
                    <span class="text-xs text-slate-400 font-medium">
                        {{ collection.movies_count }} {{ isRTL ? 'أفلام مرتبة زمنياً' : 'films in order' }}
                    </span>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="(movie, index) in collection.movies"
                        :key="movie.id"
                        class="p-4 rounded-3xl bg-slate-900/60 hover:bg-slate-900/90 border border-white/10 hover:border-cyan-500/40 transition-all flex flex-col md:flex-row items-center justify-between gap-4 group"
                    >
                        <!-- Left: Index + Poster + Info -->
                        <div class="flex items-center gap-4 w-full md:w-auto flex-1 min-w-0">
                            <!-- Number Indicator -->
                            <div class="w-8 h-8 rounded-xl bg-white/[0.05] border border-white/10 text-slate-400 group-hover:text-cyan-400 group-hover:border-cyan-500/30 flex items-center justify-center font-black text-xs shrink-0 transition-colors">
                                #{{ index + 1 }}
                            </div>

                            <!-- Poster Thumbnail -->
                            <div
                                @click="openMovieDetails(movie)"
                                class="w-14 aspect-[2/3] rounded-xl overflow-hidden bg-slate-950 shrink-0 border border-white/10 relative shadow-md cursor-pointer group-hover:scale-105 transition-transform"
                            >
                                <img
                                    v-if="movie.poster_path"
                                    :src="movie.poster_path"
                                    :alt="movie.title"
                                    class="w-full h-full object-cover"
                                    loading="lazy"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center text-slate-700 bg-slate-900">
                                    <Film class="w-5 h-5" />
                                </div>
                            </div>

                            <!-- Title & Specs -->
                            <div class="space-y-1 min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3
                                        @click="openMovieDetails(movie)"
                                        class="text-base lg:text-lg font-bold text-white group-hover:text-cyan-400 transition-colors truncate cursor-pointer"
                                    >
                                        {{ isRTL && movie.title_ar ? movie.title_ar : movie.title }}
                                    </h3>
                                    <span v-if="movie.release_year" class="text-xs font-semibold px-2 py-0.5 rounded-md bg-white/[0.06] border border-white/10 text-slate-300">
                                        {{ movie.release_year }}
                                    </span>
                                </div>

                                <p v-if="movie.overview || movie.overview_ar" class="text-xs text-slate-400 line-clamp-2 leading-relaxed">
                                    {{ isRTL && movie.overview_ar ? movie.overview_ar : movie.overview }}
                                </p>

                                <div class="flex flex-wrap items-center gap-3 pt-1 text-[11px] text-slate-400">
                                    <span v-if="movie.rating" class="flex items-center gap-1 text-amber-400 font-bold">
                                        <Star class="w-3 h-3 fill-amber-400" />
                                        <span>{{ movie.rating }}</span>
                                    </span>
                                    <span v-if="movie.runtime_minutes" class="flex items-center gap-1">
                                        <Clock class="w-3 h-3 text-cyan-400" />
                                        <span>{{ movie.runtime_minutes }} {{ isRTL ? 'د' : 'min' }}</span>
                                    </span>
                                    <span v-if="movie.resolution" class="px-1.5 py-0.5 rounded bg-cyan-500/10 text-cyan-300 font-bold text-[10px] border border-cyan-500/20">
                                        {{ movie.resolution }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Actions (Details Modal & Play) -->
                        <div class="flex items-center gap-2.5 w-full md:w-auto shrink-0 justify-end pt-2 md:pt-0 border-t md:border-t-0 border-white/5">
                            <button
                                @click="openMovieDetails(movie)"
                                class="px-4 py-2.5 rounded-xl bg-white/[0.06] hover:bg-white/[0.12] border border-white/10 hover:border-cyan-500/30 text-xs font-bold text-slate-200 transition-all flex items-center gap-1.5 cursor-pointer"
                            >
                                <Info class="w-3.5 h-3.5 text-cyan-400" />
                                <span>{{ isRTL ? 'التفاصيل' : 'Details' }}</span>
                            </button>

                            <button
                                @click="playCollectionMovie(movie, play)"
                                class="px-5 py-2.5 rounded-xl bg-cyan-500 text-slate-950 font-black text-xs hover:bg-cyan-400 flex items-center gap-2 shadow-lg shadow-cyan-500/20 active:scale-95 transition-all cursor-pointer"
                            >
                                <Play class="w-4 h-4 fill-current" />
                                <span>{{ isRTL ? 'مشاهدة الآن' : 'Play Now' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Movie Details Modal -->
        <MediaDetailModal
            :show="!!selectedDetailMovie"
            :item="selectedDetailMovie"
            @close="selectedDetailMovie = null"
            @play="playCollectionMovie(selectedDetailMovie, play)"
            @toggle-favorite="handleToggleFavorite"
        />
    </AppLayout>
</template>
