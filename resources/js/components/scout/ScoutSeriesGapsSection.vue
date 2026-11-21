<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Tv,
    Search,
    Download,
    ChevronRight,
    Layers,
    Sparkles,
} from 'lucide-vue-next';

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
                <Tv class="h-5 w-5 text-purple-400" />
                <h3 class="text-base font-extrabold text-white">
                    {{
                        isRTL ? 'نواقص المسلسلات التلفزيونية' : 'TV Series Gaps'
                    }}
                </h3>
            </div>
            <span class="text-xs font-semibold text-slate-400">
                {{ gapsList.length }}
                {{ isRTL ? 'مسلسل بحاجة لإكمال' : 'series require attention' }}
            </span>
        </div>

        <!-- Empty State -->
        <div
            v-if="gapsList.length === 0 && !isLoading"
            class="space-y-2 rounded-3xl border border-white/5 bg-slate-900/40 p-12 text-center"
        >
            <Tv class="mx-auto h-8 w-8 text-slate-600" />
            <h4 class="text-sm font-bold text-white">
                {{
                    isRTL
                        ? 'مكتبة المسلسلات مكتملة تماماً!'
                        : 'All series are 100% complete!'
                }}
            </h4>
            <p class="text-xs text-slate-500">
                {{
                    isRTL
                        ? 'لا توجد مواسم أو حلقات مفقودة حالياً'
                        : 'No missing seasons or episodes detected.'
                }}
            </p>
        </div>

        <!-- Series Gaps Cards -->
        <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div
                v-for="series in gapsList"
                :key="series.id || series.series_id"
                class="group space-y-4 rounded-3xl border border-white/10 bg-slate-900/60 p-5 backdrop-blur-xl transition-all hover:border-purple-500/30"
            >
                <!-- Series Meta -->
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="h-16 w-12 shrink-0 overflow-hidden rounded-xl border border-white/10 bg-slate-800"
                        >
                            <img
                                v-if="series.poster_path"
                                :src="series.poster_path"
                                :alt="series.title"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center text-xs text-slate-600"
                            >
                                TV
                            </div>
                        </div>
                        <div class="min-w-0">
                            <h4
                                class="truncate text-sm font-bold text-white transition-colors group-hover:text-purple-300"
                            >
                                {{
                                    isRTL && series.title_ar
                                        ? series.title_ar
                                        : series.title
                                }}
                            </h4>
                            <div
                                class="mt-1 flex items-center gap-2 text-[11px] text-slate-400"
                            >
                                <span
                                    v-if="series.year"
                                    class="font-semibold"
                                    >{{ series.year }}</span
                                >
                                <span
                                    class="rounded bg-purple-500/20 px-1.5 py-0.5 text-[10px] font-bold text-purple-300"
                                >
                                    {{ series.missing_seasons_count || 0 }}
                                    {{
                                        isRTL ? 'موسم ناقص' : 'seasons missing'
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Search Button -->
                    <button
                        @click="emit('search-torrent', series.title, series)"
                        class="shrink-0 rounded-xl border border-purple-500/30 bg-purple-500/10 p-2.5 text-purple-300 transition-all hover:bg-purple-500/20 active:scale-95"
                        :title="
                            isRTL
                                ? 'بحث عن تورنت للمسلسل'
                                : 'Search torrents for this series'
                        "
                    >
                        <Search class="h-4 w-4" />
                    </button>
                </div>

                <!-- Missing Seasons / Episodes Tags -->
                <div
                    v-if="
                        series.missing_items && series.missing_items.length > 0
                    "
                    class="flex flex-wrap gap-1.5 border-t border-white/5 pt-2"
                >
                    <span
                        v-for="(item, idx) in series.missing_items.slice(0, 6)"
                        :key="idx"
                        class="flex items-center gap-1 rounded-lg border border-white/10 bg-white/5 px-2 py-1 font-mono text-[10px] font-bold text-slate-300"
                    >
                        {{ item.name || item.code || `Season ${item.season}` }}
                    </span>
                    <span
                        v-if="series.missing_items.length > 6"
                        class="rounded-lg px-2 py-1 font-mono text-[10px] text-slate-500"
                    >
                        +{{ series.missing_items.length - 6 }} more
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
