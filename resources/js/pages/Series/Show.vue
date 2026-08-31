<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import { Play, Star, ArrowLeft, ArrowRight, Layers, Users } from 'lucide-vue-next';

const props = defineProps<{
    series: any;
}>();

const { t, isRTL } = useI18n();

const selectedSeasonId = ref<number>(props.series.seasons?.[0]?.id || 1);

const selectedSeason = () => {
    return props.series.seasons?.find((s: any) => s.id === selectedSeasonId.value) || props.series.seasons?.[0];
};
</script>

<template>
    <Head :title="series.title" />

    <AppLayout v-slot="{ play }">
        <!-- Back navigation -->
        <div class="mb-4">
            <Link href="/series" class="inline-flex items-center gap-2 text-xs font-bold text-slate-400 hover:text-cyan-400 transition-colors">
                <component :is="isRTL ? ArrowRight : ArrowLeft" class="w-4 h-4" />
                <span>{{ isRTL ? 'العودة إلى المسلسلات' : 'Back to TV Series' }}</span>
            </Link>
        </div>

        <!-- Series Backdrop Hero -->
        <div class="relative rounded-3xl overflow-hidden mb-8 border border-white/10 shadow-2xl">
            <div class="relative aspect-[21/9] w-full min-h-[320px] bg-slate-950">
                <img
                    :src="series.backdrop_path || series.poster_path"
                    :alt="series.title"
                    class="w-full h-full object-cover opacity-70"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-[#07090E] via-[#07090E]/60 to-transparent"></div>
            </div>

            <!-- Content Header -->
            <div class="absolute bottom-0 inset-x-0 p-6 sm:p-10 flex flex-col sm:flex-row gap-6 items-end">
                <img
                    :src="series.poster_path"
                    :alt="series.title"
                    class="w-28 sm:w-40 rounded-2xl border-2 border-white/20 shadow-2xl shrink-0 hidden sm:block"
                />
                <div class="flex-1 space-y-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="cinema-badge bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center gap-1">
                            <Star class="w-3.5 h-3.5 fill-current" />
                            {{ series.rating }}
                        </span>
                        <span class="cinema-badge bg-white/10 text-slate-200 border border-white/10">
                            {{ series.release_year }}
                        </span>
                        <span class="cinema-badge bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">
                            {{ series.network || 'TV' }}
                        </span>
                    </div>

                    <h1 class="text-3xl sm:text-5xl font-black text-white">
                        {{ isRTL && series.title_ar ? series.title_ar : series.title }}
                    </h1>

                    <p class="text-xs sm:text-sm text-slate-300 max-w-3xl leading-relaxed">
                        {{ isRTL && series.overview_ar ? series.overview_ar : series.overview }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Seasons Tabs -->
        <div class="mb-6 border-b border-white/10 pb-4">
            <div class="flex items-center gap-2 overflow-x-auto">
                <button
                    v-for="s in series.seasons"
                    :key="s.id"
                    @click="selectedSeasonId = s.id"
                    class="px-5 py-2 rounded-xl text-sm font-bold transition-all whitespace-nowrap"
                    :class="selectedSeasonId === s.id
                        ? 'bg-cyan-500 text-slate-950 shadow-lg shadow-cyan-500/20'
                        : 'bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10'"
                >
                    {{ isRTL && s.title_ar ? s.title_ar : (s.title || `Season ${s.season_number}`) }}
                </button>
            </div>
        </div>

        <!-- Episodes List Grid -->
        <div v-if="selectedSeason()?.episodes?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="ep in selectedSeason()?.episodes"
                :key="ep.id"
                @click="play({ ...ep, watchable_id: ep.id, watchable_type: 'episode', title: `${series.title} - S${selectedSeason().season_number}E${ep.episode_number} - ${ep.title}` })"
                class="glass-card group rounded-2xl overflow-hidden cursor-pointer border border-white/10 hover:border-cyan-500/40 transition-all flex flex-col"
            >
                <div class="relative aspect-video w-full overflow-hidden bg-slate-900">
                    <img
                        :src="ep.still_path || series.backdrop_path"
                        :alt="ep.title"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent opacity-80"></div>

                    <!-- Play Hover Overlay -->
                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40">
                        <div class="w-12 h-12 rounded-full bg-cyan-500 text-slate-950 flex items-center justify-center shadow-xl shadow-cyan-500/40 group-hover:scale-110 transition-transform">
                            <Play class="w-6 h-6 fill-current ml-0.5" />
                        </div>
                    </div>

                    <!-- Episode Number Badge -->
                    <span class="absolute top-2.5 left-2.5 cinema-badge bg-black/70 text-cyan-300 border border-cyan-500/30 text-[10px]">
                        EP {{ ep.episode_number }}
                    </span>
                </div>

                <div class="p-4 flex-1 flex flex-col justify-between">
                    <div>
                        <h4 class="font-bold text-sm text-white group-hover:text-cyan-400 transition-colors">
                            {{ isRTL && ep.title_ar ? ep.title_ar : ep.title }}
                        </h4>
                        <p class="text-xs text-slate-400 mt-1 line-clamp-2 leading-relaxed">
                            {{ isRTL && ep.overview_ar ? ep.overview_ar : (ep.overview || 'No summary available.') }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between mt-3 text-[11px] text-slate-400 font-semibold border-t border-white/5 pt-2">
                        <span>{{ ep.air_date || '' }}</span>
                        <span>{{ ep.runtime_minutes ? `${ep.runtime_minutes} min` : '' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
