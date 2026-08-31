<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import HeroBanner from '@/components/media/HeroBanner.vue';
import FilterBar from '@/components/media/FilterBar.vue';
import { Tv, Star, Layers, ScanLine, Plus } from 'lucide-vue-next';

const props = defineProps<{
    seriesList: {
        data: any[];
        total: number;
    };
    genres: any[];
    heroSeries: any;
    filters: Record<string, any>;
}>();

const { t, isRTL } = useI18n();
</script>

<template>
    <Head :title="t('nav.series')" />

    <AppLayout v-slot="{ play }">
        <!-- Spotlight Hero -->
        <HeroBanner
            v-if="heroSeries && !filters.search && !filters.genre"
            :item="heroSeries"
            @play="play"
            @details="(item) => {}"
        />

        <FilterBar :genres="genres" :filters="filters" />

        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <Tv class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                <h2 class="font-extrabold text-xl text-slate-900 dark:text-white tracking-tight font-sans">
                    {{ t('nav.series') }}
                </h2>
                <span class="text-xs text-slate-600 dark:text-slate-400 font-semibold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10">
                    {{ seriesList.total }}
                </span>
            </div>
        </div>

        <div v-if="seriesList.data.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-6">
            <Link
                v-for="s in seriesList.data"
                :key="s.id"
                :href="`/series/${s.id}`"
                class="glass-card group relative rounded-2xl overflow-hidden cursor-pointer flex flex-col border border-slate-200 dark:border-white/10 hover:border-indigo-500/50 transition-all duration-300 shadow-sm hover:shadow-lg"
            >
                <div class="relative aspect-[2/3] w-full overflow-hidden bg-slate-200 dark:bg-slate-900">
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
                    <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                        {{ isRTL && s.title_ar ? s.title_ar : s.title }}
                    </h3>
                    <div class="flex items-center justify-between mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                        <span>{{ s.release_year }}</span>
                        <span class="text-indigo-600 dark:text-indigo-400 font-bold uppercase tracking-wider text-[10px]">TV Series</span>
                    </div>
                </div>
            </Link>
        </div>

        <!-- Empty State -->
        <div v-else class="glass-panel rounded-3xl p-12 text-center border border-slate-200 dark:border-white/10 space-y-4">
            <div class="w-16 h-16 rounded-3xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 mx-auto flex items-center justify-center">
                <ScanLine class="w-8 h-8" />
            </div>
            <h3 class="font-extrabold text-lg text-slate-900 dark:text-white">
                {{ isRTL ? 'لم يتم العثور على مسلسلات' : 'No TV Series Found' }}
            </h3>
            <p class="text-xs text-slate-600 dark:text-slate-400 max-w-md mx-auto leading-relaxed">
                {{ isRTL ? 'استخدم الفاحص الافتراضي لإضافة مجلدات المسلسلات على جهازك وفهرستها مع الحلقات والمواسم.' : 'Use the Virtual Scanner to add your local TV shows folders and index all seasons & episodes.' }}
            </p>
            <Link
                href="/scanner"
                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-600 text-white font-extrabold text-xs shadow-lg shadow-indigo-600/30 hover:bg-indigo-500 transition-all"
            >
                <Plus class="w-4 h-4" />
                <span>{{ isRTL ? 'إضافة مجلد وفحص المسلسلات' : 'Scan TV Series Folder' }}</span>
            </Link>
        </div>
    </AppLayout>
</template>
