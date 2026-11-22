<script setup lang="ts">
import { ref, defineAsyncComponent } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/components/layout/AppLayout.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';
import MotionList from '@/components/motion/MotionList.vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Layers,
    Film,
    Star,
    Calendar,
    Clock,
    Play,
    ArrowLeft,
    ArrowRight,
    Sparkles,
    CheckCircle2,
    Info,
    Search,
    Unlink,
    SlidersHorizontal,
    Plus,
} from '@lucide/vue';

const MediaDetailModal = defineAsyncComponent(
    () => import('@/components/media/MediaDetailModal.vue'),
);
const CollectionManagementModal = defineAsyncComponent(
    () => import('@/components/collections/CollectionManagementModal.vue'),
);

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

const { isRTL } = useI18n();

// Detail Modal state
const selectedDetailMovie = ref<any | null>(null);
const showManagementModal = ref(false);

// Detach Confirmation Modal state
const movieToDetach = ref<MovieDetail | null>(null);
const isDetaching = ref(false);

const handleCollectionChanged = () => {
    router.reload();
};

const openMovieDetails = (movie: MovieDetail) => {
    selectedDetailMovie.value = movie;
};

const quickDetachMovie = (movie: MovieDetail) => {
    movieToDetach.value = movie;
};

const confirmDetachMovie = async () => {
    if (!movieToDetach.value || isDetaching.value) return;
    const movie = movieToDetach.value;
    isDetaching.value = true;

    try {
        const res = await fetch('/api/collections/management/detach-movie', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
            body: JSON.stringify({ media_item_id: movie.id }),
        });

        if (res.ok) {
            props.collection.movies = props.collection.movies.filter(
                (m: any) => m.id !== movie.id,
            );
            props.collection.movies_count = props.collection.movies.length;
        }
    } catch (e) {
        console.error('Failed to detach movie', e);
    } finally {
        isDetaching.value = false;
        movieToDetach.value = null;
    }
};

const playCollectionMovie = (
    movie: any,
    playFn: (item: any, playlist?: any[]) => void,
) => {
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
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
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
        <Head
            :title="`${collection.name} | ${isRTL ? 'سلسلة أفلام' : 'Movie Collection'}`"
        />

        <div class="space-y-8 pb-16">
            <!-- Top Back Navigation -->
            <div>
                <Link
                    href="/collections"
                    class="cf-glass group border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-xl border px-4 py-2 text-xs font-bold transition-all active:scale-95"
                >
                    <ArrowLeft
                        v-if="!isRTL"
                        class="h-4 w-4 transition-transform group-hover:-translate-x-0.5"
                    />
                    <ArrowRight
                        v-else
                        class="h-4 w-4 transition-transform group-hover:translate-x-0.5"
                    />
                    <span>{{
                        isRTL
                            ? 'العودة لجميع سلاسل الأفلام'
                            : 'Back to Movie Collections'
                    }}</span>
                </Link>
            </div>

            <!-- Hero Banner for the Collection -->
            <div
                class="cf-glass-elevated border-border/80 bg-card relative overflow-hidden rounded-3xl border p-8 shadow-2xl lg:p-12"
            >
                <!-- Ambient Backdrop Glow -->
                <div
                    v-if="collection.backdrop_path"
                    class="pointer-events-none absolute inset-0 scale-105 bg-cover bg-center opacity-25 blur-sm filter"
                    :style="`background-image: url('${collection.backdrop_path}')`"
                ></div>
                <div
                    class="from-background via-background/80 pointer-events-none absolute inset-0 bg-gradient-to-t to-transparent"
                ></div>

                <div
                    class="relative z-10 flex flex-col items-center justify-between gap-8 md:flex-row md:items-end"
                >
                    <!-- Poster + Meta -->
                    <div
                        class="flex flex-col items-center gap-6 text-center sm:flex-row sm:items-end sm:text-start"
                    >
                        <div
                            class="aspect-[2/3] w-32 shrink-0 -rotate-1 transform overflow-hidden rounded-2xl border border-white/15 bg-slate-900 shadow-2xl transition-transform hover:rotate-0 lg:w-40"
                        >
                            <img
                                v-if="collection.poster_path"
                                :src="collection.poster_path"
                                :alt="collection.name"
                                class="h-full w-full object-cover"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center bg-slate-900 text-slate-700"
                            >
                                <Film class="h-10 w-10" />
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                class="flex flex-wrap items-center justify-center gap-2 sm:justify-start"
                            >
                                <div
                                    class="border-primary/35 bg-primary/15 text-primary inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold"
                                >
                                    <Layers class="h-3.5 w-3.5" />
                                    <span>{{
                                        isRTL
                                            ? 'سلسلة أفلام مجمعة'
                                            : 'Franchise Boxset'
                                    }}</span>
                                </div>
                                <div
                                    v-if="collection.is_complete"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/40 bg-emerald-500/20 px-3 py-1 text-xs font-bold text-emerald-300"
                                >
                                    <CheckCircle2 class="h-3.5 w-3.5" />
                                    <span>{{
                                        isRTL
                                            ? 'السلسلة مكتملة 100%'
                                            : '100% Complete Franchise'
                                    }}</span>
                                </div>
                                <div
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/20 px-3 py-1 text-xs font-bold text-amber-300 tabular-nums"
                                >
                                    <Clock class="h-3.5 w-3.5" />
                                    <span>{{
                                        isRTL
                                            ? `مكتمل ${collection.completion_percentage}%`
                                            : `${collection.completion_percentage}% Owned`
                                    }}</span>
                                </div>
                            </div>

                            <h1
                                class="text-foreground text-2xl font-black tracking-tight sm:text-4xl"
                            >
                                {{ collection.name }}
                            </h1>

                            <div
                                class="text-muted-foreground flex flex-wrap items-center justify-center gap-4 text-xs font-semibold tabular-nums sm:justify-start"
                            >
                                <div class="flex items-center gap-1.5">
                                    <Film class="text-primary h-3.5 w-3.5" />
                                    <span
                                        >{{ collection.movies_count }}
                                        {{
                                            isRTL
                                                ? collection.movies_count > 10
                                                    ? 'فيلماً مملوكاً'
                                                    : 'أفلام مملوكة'
                                                : 'Movies Owned'
                                        }}</span
                                    >
                                    <span
                                        v-if="
                                            collection.total_parts &&
                                            collection.total_parts >
                                                collection.movies_count
                                        "
                                        class="text-muted-foreground/75"
                                    >
                                        ({{
                                            isRTL
                                                ? `من أصل ${collection.total_parts}`
                                                : `of ${collection.total_parts}`
                                        }})
                                    </span>
                                </div>

                                <div
                                    v-if="collection.year_span"
                                    class="flex items-center gap-1.5"
                                >
                                    <Calendar
                                        class="text-primary h-3.5 w-3.5"
                                    />
                                    <span>{{ collection.year_span }}</span>
                                </div>

                                <div
                                    v-if="collection.avg_rating"
                                    class="flex items-center gap-1.5 font-bold text-amber-400"
                                >
                                    <Star class="h-3.5 w-3.5 fill-amber-400" />
                                    <span
                                        >{{ collection.avg_rating }} / 10</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Play First Movie CTA & Manage Button -->
                    <div class="flex shrink-0 flex-wrap items-center gap-3">
                        <button
                            v-if="collection.movies.length > 0"
                            type="button"
                            @click="
                                playCollectionMovie(collection.movies[0], play)
                            "
                            class="bg-primary text-primary-foreground shadow-primary/25 flex min-h-11 cursor-pointer items-center gap-2.5 rounded-2xl px-6 py-3 text-xs font-black shadow-xl transition-all hover:brightness-105 active:scale-95 sm:text-sm"
                        >
                            <Play class="h-4 w-4 fill-current" />
                            <span>{{
                                isRTL
                                    ? 'بدء تشغيل السلسلة من الجزء الأول'
                                    : 'Play Franchise From Part 1'
                            }}</span>
                        </button>
                        <button
                            type="button"
                            @click="showManagementModal = true"
                            class="cf-glass border-border/80 text-foreground hover:border-primary/40 flex min-h-11 cursor-pointer items-center gap-2 rounded-2xl border px-4 py-3 text-xs font-bold transition-all active:scale-95 sm:text-sm"
                            :title="
                                isRTL
                                    ? 'إدارة السلسلة وإضافة/إزالة أفلام'
                                    : 'Manage Franchise & Movies'
                            "
                        >
                            <SlidersHorizontal class="text-primary h-4 w-4" />
                            <span>{{
                                isRTL ? 'إدارة السلسلة' : 'Manage Saga'
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Movies in this Collection (Chronological) -->
            <section class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="space-y-1">
                        <h2
                            class="text-foreground text-xl font-black tracking-tight lg:text-2xl"
                        >
                            {{
                                isRTL
                                    ? 'أفلام السلسلة المتوفرة بمكتبتك'
                                    : 'Owned Movies in this Saga'
                            }}
                        </h2>
                        <p class="text-muted-foreground text-xs">
                            {{
                                isRTL
                                    ? 'مرتبة بالتسلسل الزمني للإنتاج'
                                    : 'Organized chronologically by release year'
                            }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showManagementModal = true"
                        class="border-primary/35 bg-primary/15 text-primary hover:bg-primary/25 flex min-h-10 cursor-pointer items-center gap-1.5 rounded-xl border px-3.5 py-2 text-xs font-bold transition-all active:scale-95"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        <span>{{
                            isRTL ? 'إضافة فيلم للسلسلة' : 'Add Movie'
                        }}</span>
                    </button>
                </div>

                <MotionList
                    class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
                >
                    <div
                        v-for="(movie, idx) in collection.movies"
                        :key="movie.id"
                        class="cf-glass group border-border/80 hover:border-primary/50 relative flex cursor-pointer flex-col overflow-hidden rounded-2xl border transition-all duration-200 hover:-translate-y-1 hover:shadow-2xl"
                        @click="openMovieDetails(movie)"
                    >
                        <!-- Poster Thumbnail -->
                        <div
                            class="relative aspect-[2/3] w-full overflow-hidden bg-slate-950"
                        >
                            <img
                                v-if="movie.poster_path"
                                :src="movie.poster_path"
                                :alt="movie.title"
                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                loading="lazy"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center bg-slate-950 text-slate-700"
                            >
                                <Film class="h-10 w-10" />
                            </div>

                            <!-- Chronological Index Badge -->
                            <div
                                class="bg-primary text-primary-foreground absolute start-2.5 top-2.5 flex h-7 min-w-7 items-center justify-center rounded-xl px-1.5 text-xs font-black tabular-nums shadow-lg"
                            >
                                #{{ idx + 1 }}
                            </div>

                            <!-- Resolution Badge -->
                            <div
                                v-if="movie.resolution"
                                class="border-primary/35 text-primary absolute end-2.5 top-2.5 rounded-md border bg-black/75 px-2 py-0.5 text-[10px] font-black tabular-nums backdrop-blur-md"
                            >
                                {{ movie.resolution }}
                            </div>

                            <!-- Hover Quick Play Overlay -->
                            <div
                                class="absolute inset-0 flex items-center justify-center gap-2.5 bg-black/60 opacity-0 backdrop-blur-[2px] transition-opacity group-hover:opacity-100"
                            >
                                <button
                                    type="button"
                                    @click.stop="
                                        playCollectionMovie(movie, play)
                                    "
                                    class="bg-primary text-primary-foreground shadow-primary/35 flex h-11 w-11 cursor-pointer items-center justify-center rounded-2xl shadow-xl transition-all hover:scale-110"
                                    :title="isRTL ? 'تشغيل' : 'Play Now'"
                                >
                                    <Play class="ms-0.5 h-5 w-5 fill-current" />
                                </button>
                                <button
                                    type="button"
                                    @click.stop="openMovieDetails(movie)"
                                    class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-2xl border border-white/20 bg-white/10 text-white transition-all hover:scale-110 hover:bg-white/20"
                                    :title="isRTL ? 'تفاصيل' : 'Details'"
                                >
                                    <Info class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    @click.stop="quickDetachMovie(movie)"
                                    class="border-destructive/40 bg-destructive/20 text-destructive hover:bg-destructive/35 flex h-9 w-9 cursor-pointer items-center justify-center rounded-2xl border transition-all hover:scale-110"
                                    :title="
                                        isRTL
                                            ? 'إزالة الفيلم من هذه السلسلة'
                                            : 'Remove from collection'
                                    "
                                >
                                    <Unlink class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div
                            class="flex flex-1 flex-col justify-between space-y-2 p-4"
                        >
                            <div>
                                <h3
                                    class="text-foreground group-hover:text-primary line-clamp-1 text-sm font-bold transition-colors"
                                    :title="movie.title"
                                >
                                    {{
                                        isRTL && movie.title_ar
                                            ? movie.title_ar
                                            : movie.title
                                    }}
                                </h3>
                                <p
                                    v-if="isRTL && movie.title_ar"
                                    class="text-muted-foreground line-clamp-1 text-[11px]"
                                >
                                    {{ movie.title }}
                                </p>
                            </div>

                            <div
                                class="border-border/60 text-muted-foreground flex items-center justify-between border-t pt-2 text-xs tabular-nums"
                            >
                                <span class="font-semibold">{{
                                    movie.release_year
                                }}</span>
                                <div
                                    v-if="movie.rating"
                                    class="flex items-center gap-1 font-bold text-amber-400"
                                >
                                    <Star class="h-3 w-3 fill-amber-400" />
                                    <span>{{ movie.rating }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </MotionList>
            </section>

            <!-- Missing Franchise Titles (Media Scout Integration) -->
            <section
                v-if="
                    collection.missing_parts &&
                    collection.missing_parts.length > 0
                "
                class="border-border/70 space-y-4 border-t pt-6"
            >
                <div
                    class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
                >
                    <div class="space-y-1">
                        <div
                            class="inline-flex items-center gap-2 text-xs font-bold tracking-wider text-amber-400 uppercase"
                        >
                            <Sparkles class="h-3.5 w-3.5" />
                            <span>{{
                                isRTL
                                    ? 'استكشاف الأجزاء المتبقية عبر ميديا سكاوت'
                                    : 'Franchise Expansion via Media Scout'
                            }}</span>
                        </div>
                        <h2
                            class="text-foreground text-xl font-black tracking-tight lg:text-2xl"
                        >
                            {{
                                isRTL
                                    ? 'أجزاء غير متوفرة في مكتبتك'
                                    : 'Missing From Your Library'
                            }}
                        </h2>
                    </div>
                    <Link
                        :href="`/scout?query=${encodeURIComponent(collection.name.replace(' Collection', ''))}`"
                        class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-xl border border-amber-500/35 bg-amber-500/15 px-4 py-2 text-xs font-bold text-amber-300 transition-all hover:bg-amber-500/25 active:scale-95"
                    >
                        <Search class="h-3.5 w-3.5" />
                        <span>{{
                            isRTL
                                ? 'بحث عن السلسلة في سكاوت'
                                : 'Scout Entire Franchise'
                        }}</span>
                    </Link>
                </div>

                <div
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
                >
                    <div
                        v-for="part in collection.missing_parts"
                        :key="part.tmdb_id || part.title"
                        class="cf-glass group relative flex flex-col space-y-3 overflow-hidden rounded-2xl border border-amber-500/25 p-2.5"
                    >
                        <div
                            class="border-border/60 relative aspect-[2/3] w-full overflow-hidden rounded-xl border bg-slate-950"
                        >
                            <img
                                v-if="part.poster_path"
                                :src="part.poster_path"
                                :alt="part.title"
                                class="h-full w-full object-cover grayscale-[25%] filter transition-transform duration-300 group-hover:scale-105 group-hover:grayscale-0"
                                loading="lazy"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center bg-slate-950 text-slate-700"
                            >
                                <Film class="h-8 w-8" />
                            </div>
                            <div
                                class="absolute end-2 top-2 rounded-md border border-amber-500/30 bg-black/80 px-2 py-0.5 text-[10px] font-black text-amber-300 tabular-nums"
                            >
                                {{ part.release_year || 'TBA' }}
                            </div>
                        </div>

                        <div
                            class="flex flex-1 flex-col justify-between space-y-1.5"
                        >
                            <div>
                                <h4
                                    class="text-foreground line-clamp-1 text-xs font-bold transition-colors group-hover:text-amber-400"
                                    :title="part.title"
                                >
                                    {{
                                        isRTL && part.title_ar
                                            ? part.title_ar
                                            : part.title
                                    }}
                                </h4>
                                <p
                                    v-if="isRTL && part.title_ar"
                                    class="text-muted-foreground line-clamp-1 text-[10px]"
                                >
                                    {{ part.title }}
                                </p>
                            </div>

                            <Link
                                :href="`/scout?query=${encodeURIComponent(part.title)}`"
                                class="mt-2 flex w-full cursor-pointer items-center justify-center gap-1 rounded-lg bg-amber-500 px-2 py-1.5 text-[11px] font-black text-slate-950 transition-all hover:bg-amber-400 active:scale-95"
                            >
                                <Search class="h-3 w-3" />
                                <span>{{
                                    isRTL ? 'بحث وتحميل' : 'Scout Torrent'
                                }}</span>
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

            <!-- Collection Management Studio Modal -->
            <CollectionManagementModal
                v-if="showManagementModal"
                :show="showManagementModal"
                :initial-collection-slug="collection.slug"
                @close="showManagementModal = false"
                @changed="handleCollectionChanged"
            />

            <!-- Accessible ConfirmModal for Movie Detach -->
            <ConfirmModal
                :show="!!movieToDetach"
                type="danger"
                :loading="isDetaching"
                :title="
                    isRTL
                        ? 'إزالة الفيلم من السلسلة'
                        : 'Remove Movie from Collection'
                "
                :message="
                    movieToDetach
                        ? isRTL
                            ? `هل أنت متأكد من إزالة فيلم &quot;${movieToDetach.title}&quot; من هذه السلسلة؟`
                            : `Are you sure you want to remove &quot;${movieToDetach.title}&quot; from this collection?`
                        : ''
                "
                :confirm-text="isRTL ? 'إزالة من السلسلة' : 'Remove'"
                @confirm="confirmDetachMovie"
                @close="movieToDetach = null"
            />
        </div>
    </AppLayout>
</template>
