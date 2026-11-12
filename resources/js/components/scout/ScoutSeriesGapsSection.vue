<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { Tv, Search, Download, ChevronRight, Layers, Sparkles } from 'lucide-vue-next';

const props = defineProps<{
    seriesGaps?: any[];
    isLoading?: boolean;
}>();

const emit = defineEmits<{
    (e: 'search-torrent', query: string, meta?: any): void;
}>();

const { t, isRTL } = useI18n();

const gapsList = computed(() => props.seriesGaps || []);
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <Tv class="w-5 h-5 text-purple-400" />
                <h3 class="text-base font-extrabold text-white">
                    {{ isRTL ? 'نواقص المسلسلات التلفزيونية' : 'TV Series Gaps' }}
                </h3>
            </div>
            <span class="text-xs text-slate-400 font-semibold">
                {{ gapsList.length }} {{ isRTL ? 'مسلسل بحاجة لإكمال' : 'series require attention' }}
            </span>
        </div>

        <!-- Empty State -->
        <div v-if="gapsList.length === 0 && !isLoading" class="p-12 rounded-3xl bg-slate-900/40 border border-white/5 text-center space-y-2">
            <Tv class="w-8 h-8 text-slate-600 mx-auto" />
            <h4 class="text-sm font-bold text-white">{{ isRTL ? 'مكتبة المسلسلات مكتملة تماماً!' : 'All series are 100% complete!' }}</h4>
            <p class="text-xs text-slate-500">{{ isRTL ? 'لا توجد مواسم أو حلقات مفقودة حالياً' : 'No missing seasons or episodes detected.' }}</p>
        </div>

        <!-- Series Gaps Cards -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
                v-for="series in gapsList"
                :key="series.id || series.series_id"
                class="p-5 rounded-3xl bg-slate-900/60 border border-white/10 hover:border-purple-500/30 backdrop-blur-xl transition-all space-y-4 group"
            >
                <!-- Series Meta -->
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-12 h-16 rounded-xl bg-slate-800 overflow-hidden shrink-0 border border-white/10">
                            <img
                                v-if="series.poster_path"
                                :src="series.poster_path"
                                :alt="series.title"
                                class="w-full h-full object-cover"
                                loading="lazy"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-slate-600 text-xs">
                                TV
                            </div>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-sm font-bold text-white truncate group-hover:text-purple-300 transition-colors">
                                {{ isRTL && series.title_ar ? series.title_ar : series.title }}
                            </h4>
                            <div class="text-[11px] text-slate-400 flex items-center gap-2 mt-1">
                                <span v-if="series.year" class="font-semibold">{{ series.year }}</span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] bg-purple-500/20 text-purple-300 font-bold">
                                    {{ series.missing_seasons_count || 0 }} {{ isRTL ? 'موسم ناقص' : 'seasons missing' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Search Button -->
                    <button
                        @click="emit('search-torrent', series.title, series)"
                        class="p-2.5 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/30 text-purple-300 transition-all active:scale-95 shrink-0"
                        :title="isRTL ? 'بحث عن تورنت للمسلسل' : 'Search torrents for this series'"
                    >
                        <Search class="w-4 h-4" />
                    </button>
                </div>

                <!-- Missing Seasons / Episodes Tags -->
                <div v-if="series.missing_items && series.missing_items.length > 0" class="flex flex-wrap gap-1.5 pt-2 border-t border-white/5">
                    <span
                        v-for="(item, idx) in series.missing_items.slice(0, 6)"
                        :key="idx"
                        class="px-2 py-1 rounded-lg text-[10px] font-mono font-bold bg-white/5 border border-white/10 text-slate-300 flex items-center gap-1"
                    >
                        {{ item.name || item.code || `Season ${item.season}` }}
                    </span>
                    <span v-if="series.missing_items.length > 6" class="px-2 py-1 rounded-lg text-[10px] font-mono text-slate-500">
                        +{{ series.missing_items.length - 6 }} more
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

