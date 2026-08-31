<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import FixMatchModal from '@/components/media/FixMatchModal.vue';
import { Play, Star, ArrowLeft, ArrowRight, Layers, Users, Subtitles, Film, CheckCircle2, Sparkles } from 'lucide-vue-next';

const props = defineProps<{
    series: any;
}>();

const { t, isRTL } = useI18n();

const showFixMatch = ref(false);
const selectedSeasonId = ref<number>(props.series.seasons?.[0]?.id || 1);

const selectedSeason = () => {
    return props.series.seasons?.find((s: any) => s.id === selectedSeasonId.value) || props.series.seasons?.[0];
};

const handleMetadataUpdated = (updatedItem: any) => {
    Object.assign(props.series, updatedItem);
};
</script>

<template>
    <Head :title="series.title" />

    <AppLayout v-slot="{ play }">
        <!-- Back navigation & Actions Bar -->
        <div class="mb-4 flex items-center justify-between">
            <Link href="/series" class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-400 hover:text-cyan-600 dark:hover:text-cyan-400 transition-colors">
                <component :is="isRTL ? ArrowRight : ArrowLeft" class="w-4 h-4" />
                <span>{{ isRTL ? 'العودة للمسلسلات' : 'Back to TV Series' }}</span>
            </Link>

            <button
                @click="showFixMatch = true"
                class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-700 dark:text-slate-200 font-bold text-xs border border-slate-200 dark:border-white/10 transition-all cursor-pointer shadow-sm"
            >
                <Sparkles class="w-3.5 h-3.5 text-cyan-500" />
                <span>{{ isRTL ? 'تعديل البيانات والغلاف (Fix Match)' : 'Fix Match & Metadata' }}</span>
            </button>
        </div>

        <!-- Series Backdrop Hero -->
        <div class="relative rounded-3xl overflow-hidden mb-8 border border-slate-200 dark:border-white/10 shadow-2xl">
            <div class="relative aspect-[21/9] w-full min-h-[320px] bg-slate-950">
                <img
                    :src="series.backdrop_path || series.poster_path || '/placeholder.jpg'"
                    :alt="series.title"
                    class="w-full h-full object-cover opacity-80"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/70 to-transparent"></div>
            </div>

            <!-- Content Header -->
            <div class="absolute bottom-0 inset-x-0 p-6 sm:p-10 flex flex-col sm:flex-row gap-6 items-end">
                <img
                    :src="series.poster_path || '/placeholder.jpg'"
                    :alt="series.title"
                    class="w-28 sm:w-40 rounded-2xl border-2 border-white/20 shadow-2xl shrink-0 hidden sm:block bg-slate-800"
                />
                <div class="flex-1 space-y-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="cinema-badge bg-black/60 text-amber-300 border border-amber-500/30 flex items-center gap-1">
                            <Star class="w-3.5 h-3.5 fill-current" />
                            {{ series.rating || '8.0' }}
                        </span>
                        <span v-if="series.release_year" class="cinema-badge bg-black/60 text-slate-200 border border-white/15">
                            {{ series.release_year }}
                        </span>
                        <span class="cinema-badge bg-black/60 text-cyan-300 border border-cyan-500/30">
                            {{ series.seasons?.length || 1 }} {{ isRTL ? 'مواسم' : 'Seasons' }}
                        </span>
                        <span v-for="g in series.genres?.slice(0, 3)" :key="g.id" class="cinema-badge bg-black/60 text-slate-200 border border-white/10">
                            {{ isRTL && g.name_ar ? g.name_ar : g.name_en }}
                        </span>
                    </div>

                    <h1 class="text-3xl sm:text-5xl font-black text-white drop-shadow-md">
                        {{ isRTL && series.title_ar ? series.title_ar : series.title }}
                    </h1>

                    <p class="text-xs sm:text-sm text-slate-200 max-w-3xl leading-relaxed drop-shadow line-clamp-3">
                        {{ isRTL && series.overview_ar ? series.overview_ar : (series.overview || 'Experience this complete series.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Seasons Tabs -->
        <div class="mb-6 border-b border-slate-200 dark:border-white/10 pb-4">
            <div class="flex items-center gap-2 overflow-x-auto">
                <button
                    v-for="s in series.seasons"
                    :key="s.id"
                    @click="selectedSeasonId = s.id"
                    class="px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all whitespace-nowrap cursor-pointer"
                    :class="selectedSeasonId === s.id
                        ? 'bg-cyan-500 text-slate-950 shadow-lg shadow-cyan-500/20'
                        : 'bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10'"
                >
                    {{ isRTL && s.title_ar ? s.title_ar : (s.title || `Season ${s.season_number}`) }}
                    <span class="text-[10px] ml-1 opacity-70">({{ s.episodes?.length || 0 }} {{ isRTL ? 'حلقات' : 'eps' }})</span>
                </button>
            </div>
        </div>

        <!-- Episodes List Grid -->
        <div v-if="selectedSeason()?.episodes?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-12">
            <div
                v-for="ep in selectedSeason()?.episodes"
                :key="ep.id"
                @click="play({ ...ep, watchable_id: ep.id, watchable_type: 'episode', subtitles: ep.subtitles || [], title: `${series.title} - S${selectedSeason().season_number}E${ep.episode_number} - ${ep.title}` })"
                class="glass-panel group rounded-2xl overflow-hidden cursor-pointer border border-slate-200 dark:border-white/10 hover:border-cyan-500/50 transition-all flex flex-col shadow-sm bg-white dark:bg-[#121622]"
            >
                <div class="relative aspect-video w-full overflow-hidden bg-slate-900">
                    <img
                        :src="ep.still_path || series.backdrop_path || series.poster_path || '/placeholder.jpg'"
                        :alt="ep.title"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 opacity-90"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-80"></div>

                    <!-- Play Hover Overlay -->
                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40">
                        <div class="w-12 h-12 rounded-full bg-cyan-500 text-slate-950 flex items-center justify-center shadow-xl shadow-cyan-500/40 group-hover:scale-110 transition-transform">
                            <Play class="w-5 h-5 fill-current ml-0.5" />
                        </div>
                    </div>

                    <!-- Episode Number Badge -->
                    <span class="absolute top-2.5 left-2.5 cinema-badge bg-black/80 text-cyan-300 border border-cyan-500/40 text-[10px]">
                        EP {{ ep.episode_number }}
                    </span>

                    <!-- Resolution & Subtitles Badges -->
                    <div class="absolute top-2.5 right-2.5 flex items-center gap-1">
                        <span v-if="ep.subtitles && ep.subtitles.length > 0" class="cinema-badge bg-black/80 text-emerald-400 border border-emerald-500/40 text-[9px] flex items-center gap-0.5">
                            <Subtitles class="w-2.5 h-2.5" />
                            <span>{{ ep.subtitles.length }} Sub</span>
                        </span>
                        <span class="cinema-badge bg-black/80 text-cyan-400 border border-cyan-500/40 text-[9px]">
                            {{ ep.resolution || '1080p' }}
                        </span>
                    </div>
                </div>

                <div class="p-4 flex-1 flex flex-col justify-between">
                    <div>
                        <h4 class="font-bold text-sm text-slate-900 dark:text-white group-hover:text-cyan-600 dark:group-hover:text-cyan-400 transition-colors line-clamp-1">
                            {{ isRTL && ep.title_ar ? ep.title_ar : ep.title }}
                        </h4>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 line-clamp-2 leading-relaxed">
                            {{ (isRTL && ep.overview_ar) ? ep.overview_ar : (ep.overview || `Season ${selectedSeason().season_number} Episode ${ep.episode_number}`) }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between mt-3 text-[11px] text-slate-500 dark:text-slate-400 font-semibold border-t border-slate-100 dark:border-white/5 pt-2">
                        <span>{{ ep.air_date || (isRTL ? 'جاهز للمشاهدة' : 'Ready to stream') }}</span>
                        <span class="font-mono text-[10px]">{{ ep.video_codec || 'HEVC' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="glass-panel rounded-3xl p-12 text-center text-slate-500 dark:text-slate-400 text-sm my-8">
            {{ isRTL ? 'لا توجد حلقات مفهرسة لهذا الموسم حالياً.' : 'No episodes indexed for this season yet.' }}
        </div>

        <!-- Fix Match Modal -->
        <FixMatchModal
            :show="showFixMatch"
            :item="series"
            type="series"
            @close="showFixMatch = false"
            @updated="handleMetadataUpdated"
        />
    </AppLayout>
</template>
