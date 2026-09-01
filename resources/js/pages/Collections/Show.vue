<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/components/layout/AppLayout.vue';
import { useI18n } from '@/i18n/useI18n';
import { 
    Layers, Film, Star, Calendar, Clock, Play, 
    ArrowLeft, ArrowRight, ShieldCheck, Sparkles, Tv, CheckCircle2
} from 'lucide-vue-next';

interface MovieDetail {
    id: number;
    title: string;
    title_ar?: string;
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
    genres?: any[];
    subtitles_count?: number;
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
</script>

<template>
    <AppLayout>
        <Head :title="`${collection.name} | ${isRTL ? 'سلسلة أفلام' : 'Movie Collection'}`" />

        <div class="space-y-8 pb-16">
            <!-- Top Back Navigation -->
            <div>
                <Link
                    href="/collections"
                    class="inline-flex items-center gap-2 text-xs font-bold text-slate-400 hover:text-cyan-400 transition-colors group cursor-pointer"
                >
                    <ArrowLeft class="w-4 h-4 transition-transform group-hover:-translate-x-1" :class="isRTL ? 'rotate-180 group-hover:translate-x-1' : ''" />
                    <span>{{ isRTL ? 'العودة لجميع سلاسل الأفلام' : 'Back to All Collections' }}</span>
                </Link>
            </div>

            <!-- Collection Hero Banner -->
            <div class="relative rounded-3xl overflow-hidden glass-panel border border-white/10 bg-slate-950/80">
                <!-- Background Backdrop with Ambient Vignette -->
                <div class="absolute inset-0 z-0">
                    <img
                        v-if="collection.backdrop_path || collection.poster_path"
                        :src="collection.backdrop_path || collection.poster_path"
                        :alt="collection.name"
                        class="w-full h-full object-cover opacity-25 filter blur-sm scale-105"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/80 to-transparent"></div>
                    <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/70 to-transparent"></div>
                </div>

                <div class="relative z-10 p-6 lg:p-10 flex flex-col md:flex-row items-center md:items-start gap-8">
                    <!-- Collection Poster -->
                    <div class="w-48 lg:w-56 shrink-0 aspect-[2/3] rounded-2xl overflow-hidden shadow-2xl border border-white/20 relative group bg-slate-900">
                        <img
                            v-if="collection.poster_path"
                            :src="collection.poster_path"
                            :alt="collection.name"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center text-slate-700">
                            <Layers class="w-12 h-12" />
                        </div>
                    </div>

                    <!-- Collection Meta & Description -->
                    <div class="flex-1 space-y-4 text-center md:text-start">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 text-xs font-bold uppercase tracking-wider">
                            <Layers class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'سلسلة أفلام مجمعة' : 'Complete Movie Franchise' }}</span>
                        </div>

                        <h1 class="text-3xl lg:text-5xl font-black text-white tracking-tight">
                            {{ collection.name }}
                        </h1>

                        <!-- Badges Row -->
                        <div class="flex flex-wrap items-center justify-center md:justify-start gap-3 text-xs">
                            <span class="px-3 py-1 rounded-xl bg-white/[0.06] border border-white/10 text-slate-200 font-bold flex items-center gap-1.5">
                                <Film class="w-3.5 h-3.5 text-cyan-400" />
                                <span>{{ collection.movies_count }} {{ isRTL ? (collection.movies_count > 10 ? 'فيلماً' : 'أفلام') : 'Films' }}</span>
                            </span>

                            <span v-if="collection.year_span" class="px-3 py-1 rounded-xl bg-white/[0.06] border border-white/10 text-slate-200 font-bold flex items-center gap-1.5">
                                <Calendar class="w-3.5 h-3.5 text-purple-400" />
                                <span>{{ collection.year_span }}</span>
                            </span>

                            <span class="px-3 py-1 rounded-xl bg-amber-500/20 border border-amber-500/30 text-amber-300 font-black flex items-center gap-1.5">
                                <Star class="w-3.5 h-3.5 fill-amber-400" />
                                <span>{{ collection.avg_rating || '7.5' }} TMDb</span>
                            </span>
                        </div>

                        <p class="text-sm text-slate-300 max-w-3xl leading-relaxed">
                            {{ isRTL 
                                ? `تتضمن هذه السلسلة ${collection.movies_count} أفلام مرتبة بالتسلسل الزمني للقصة وتاريخ الإصدار مع توفير أعلى جودة صوت وصورة وترجمة.`
                                : `This franchise contains ${collection.movies_count} movies presented in release order with high-definition audio, video, and verified subtitles.`
                            }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Movies in this Collection Timeline / Grid -->
            <div class="space-y-6">
                <div class="flex items-center justify-between border-b border-white/10 pb-4">
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">
                        <Film class="w-5 h-5 text-cyan-400" />
                        <span>{{ isRTL ? 'أفلام السلسلة بالتسلسل الزمني' : 'Films in Chronological Release Order' }}</span>
                    </h2>
                    <span class="text-xs text-slate-400 font-medium">
                        {{ collection.movies.length }} {{ isRTL ? 'أجزاء متوفرة' : 'parts available' }}
                    </span>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="(movie, idx) in collection.movies"
                        :key="movie.id"
                        class="group rounded-2xl glass-panel border border-white/10 hover:border-cyan-500/40 p-4 lg:p-5 transition-all duration-300 bg-slate-900/40 hover:bg-slate-900/80 flex flex-col md:flex-row items-start md:items-center justify-between gap-6"
                    >
                        <!-- Left: Index + Poster + Info -->
                        <div class="flex items-center gap-4 lg:gap-6 flex-1 min-w-0">
                            <!-- Chronological Order Number Badge -->
                            <div class="w-8 h-8 rounded-xl bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 flex items-center justify-center font-black text-sm shrink-0">
                                #{{ idx + 1 }}
                            </div>

                            <!-- Poster Thumbnail -->
                            <div class="w-16 lg:w-20 aspect-[2/3] rounded-xl overflow-hidden bg-slate-950 shrink-0 border border-white/10 shadow-md">
                                <img
                                    v-if="movie.poster_path"
                                    :src="movie.poster_path"
                                    :alt="movie.title"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                                    loading="lazy"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center text-slate-700">
                                    <Film class="w-6 h-6" />
                                </div>
                            </div>

                            <!-- Title & Specs -->
                            <div class="space-y-1 min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base lg:text-lg font-bold text-white group-hover:text-cyan-400 transition-colors truncate">
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

                        <!-- Right: Actions (Play & Details) -->
                        <div class="flex items-center gap-3 w-full md:w-auto shrink-0 justify-end pt-2 md:pt-0 border-t md:border-t-0 border-white/5">
                            <Link
                                :href="movie.slug_url"
                                class="px-4 py-2.5 rounded-xl bg-white/[0.05] hover:bg-white/[0.1] border border-white/10 text-xs font-bold text-slate-200 transition-all cursor-pointer"
                            >
                                {{ isRTL ? 'التفاصيل' : 'Details' }}
                            </Link>

                            <button
                                @click="$root?.$emit ? $root.$emit('play-media', movie) : null"
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
    </AppLayout>
</template>
