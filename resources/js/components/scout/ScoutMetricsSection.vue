<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { Tv, Film, Clock, Award, AlertTriangle, Sparkles } from 'lucide-vue-next';
import ScoutMetricSkeleton from '@/components/common/skeletons/ScoutMetricSkeleton.vue';

const props = defineProps<{
    metrics?: any;
    upgradeMetrics?: any;
    activeTab: string;
    isLoading?: boolean;
}>();

const emit = defineEmits<{
    (e: 'select-tab', tab: string): void;
}>();

const { t, isRTL } = useI18n();

const missingSeriesCount = computed(() => props.metrics?.missing_series_count || 0);
const missingEpisodesCount = computed(() => props.metrics?.missing_episodes_count || 0);
const collectionGapsCount = computed(() => props.metrics?.collection_gaps_count || 0);
const totalUpgradesCount = computed(() => {
    return (props.upgradeMetrics?.low_resolution_count || 0) + (props.upgradeMetrics?.poor_quality_count || 0);
});
</script>

<template>
    <div class="space-y-4">
        <!-- Skeleton when loading -->
        <ScoutMetricSkeleton v-if="isLoading" />

        <!-- Live Metrics Cards -->
        <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Missing Series -->
            <div
                @click="emit('select-tab', 'series')"
                class="p-5 rounded-3xl border transition-all cursor-pointer backdrop-blur-xl group"
                :class="activeTab === 'series'
                    ? 'bg-purple-500/10 border-purple-500 shadow-xl shadow-purple-500/10'
                    : 'bg-slate-900/60 border-white/10 hover:border-white/20'"
            >
                <div class="flex items-center justify-between">
                    <div class="w-10 h-10 rounded-2xl bg-purple-500/20 text-purple-400 flex items-center justify-center">
                        <Tv class="w-5 h-5" />
                    </div>
                    <span class="text-2xl font-black text-white group-hover:text-purple-300 transition-colors">
                        {{ missingSeriesCount }}
                    </span>
                </div>
                <div class="mt-3">
                    <h4 class="text-xs font-bold text-white">
                        {{ isRTL ? 'مسلسلات ناقصة المواسم' : 'Incomplete Series' }}
                    </h4>
                    <p class="text-[11px] text-slate-400 mt-0.5">
                        {{ isRTL ? 'مسلسلات بحاجة لمواسم تالية' : 'Series missing whole seasons' }}
                    </p>
                </div>
            </div>

            <!-- Missing Episodes -->
            <div
                @click="emit('select-tab', 'episodes')"
                class="p-5 rounded-3xl border transition-all cursor-pointer backdrop-blur-xl group"
                :class="activeTab === 'episodes'
                    ? 'bg-amber-500/10 border-amber-500 shadow-xl shadow-amber-500/10'
                    : 'bg-slate-900/60 border-white/10 hover:border-white/20'"
            >
                <div class="flex items-center justify-between">
                    <div class="w-10 h-10 rounded-2xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
                        <Clock class="w-5 h-5" />
                    </div>
                    <span class="text-2xl font-black text-white group-hover:text-amber-300 transition-colors">
                        {{ missingEpisodesCount }}
                    </span>
                </div>
                <div class="mt-3">
                    <h4 class="text-xs font-bold text-white">
                        {{ isRTL ? 'حلقات فردية مفقودة' : 'Missing Episodes' }}
                    </h4>
                    <p class="text-[11px] text-slate-400 mt-0.5">
                        {{ isRTL ? 'فجوات داخل المواسم المحفوظة' : 'Single episode gaps in library' }}
                    </p>
                </div>
            </div>

            <!-- Franchise Collection Gaps -->
            <div
                @click="emit('select-tab', 'collections')"
                class="p-5 rounded-3xl border transition-all cursor-pointer backdrop-blur-xl group"
                :class="activeTab === 'collections'
                    ? 'bg-cyan-500/10 border-cyan-500 shadow-xl shadow-cyan-500/10'
                    : 'bg-slate-900/60 border-white/10 hover:border-white/20'"
            >
                <div class="flex items-center justify-between">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                        <Film class="w-5 h-5" />
                    </div>
                    <span class="text-2xl font-black text-white group-hover:text-cyan-300 transition-colors">
                        {{ collectionGapsCount }}
                    </span>
                </div>
                <div class="mt-3">
                    <h4 class="text-xs font-bold text-white">
                        {{ isRTL ? 'سلاسل أفلام غير مكتملة' : 'Franchise Gaps' }}
                    </h4>
                    <p class="text-[11px] text-slate-400 mt-0.5">
                        {{ isRTL ? 'أفلام ناقصة من السلاسل' : 'Missing boxset sequels & prequels' }}
                    </p>
                </div>
            </div>

            <!-- Quality Upgrades Available -->
            <div
                @click="emit('select-tab', 'low_resolution')"
                class="p-5 rounded-3xl border transition-all cursor-pointer backdrop-blur-xl group"
                :class="activeTab === 'low_resolution' || activeTab === 'poor_quality'
                    ? 'bg-rose-500/10 border-rose-500 shadow-xl shadow-rose-500/10'
                    : 'bg-slate-900/60 border-white/10 hover:border-white/20'"
            >
                <div class="flex items-center justify-between">
                    <div class="w-10 h-10 rounded-2xl bg-rose-500/20 text-rose-400 flex items-center justify-center">
                        <Sparkles class="w-5 h-5" />
                    </div>
                    <span class="text-2xl font-black text-white group-hover:text-rose-300 transition-colors">
                        {{ totalUpgradesCount }}
                    </span>
                </div>
                <div class="mt-3">
                    <h4 class="text-xs font-bold text-white">
                        {{ isRTL ? 'ترقيات جودة متاحة' : 'Quality Upgrades' }}
                    </h4>
                    <p class="text-[11px] text-slate-400 mt-0.5">
                        {{ isRTL ? 'نسخ منخفضة الجودة (<720p أو CAM)' : 'Low-res (<720p) or CAM copies' }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

