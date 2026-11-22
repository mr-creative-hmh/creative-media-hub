<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { Tv, Film, Clock, Award, AlertTriangle, Sparkles } from '@lucide/vue';
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

const missingSeriesCount = computed(
    () => props.metrics?.missing_series_count || 0,
);
const missingEpisodesCount = computed(
    () => props.metrics?.missing_episodes_count || 0,
);
const collectionGapsCount = computed(
    () => props.metrics?.collection_gaps_count || 0,
);
const totalUpgradesCount = computed(() => {
    return (
        (props.upgradeMetrics?.low_resolution_count || 0) +
        (props.upgradeMetrics?.poor_quality_count || 0)
    );
});
</script>

<template>
    <div class="space-y-4">
        <!-- Skeleton when loading -->
        <ScoutMetricSkeleton v-if="isLoading" />

        <!-- Live Metrics Cards -->
        <div v-else class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <!-- Missing Series -->
            <div
                @click="emit('select-tab', 'series')"
                class="group cursor-pointer rounded-3xl border p-5 backdrop-blur-xl transition-all"
                :class="
                    activeTab === 'series'
                        ? 'border-purple-500 bg-purple-500/10 shadow-xl shadow-purple-500/10'
                        : 'border-white/10 bg-slate-900/60 hover:border-white/20'
                "
            >
                <div class="flex items-center justify-between">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-2xl bg-purple-500/20 text-purple-400"
                    >
                        <Tv class="h-5 w-5" />
                    </div>
                    <span
                        class="text-2xl font-black text-white transition-colors group-hover:text-purple-300"
                    >
                        {{ missingSeriesCount }}
                    </span>
                </div>
                <div class="mt-3">
                    <h4 class="text-xs font-bold text-white">
                        {{
                            isRTL ? 'مسلسلات ناقصة المواسم' : 'Incomplete Series'
                        }}
                    </h4>
                    <p class="mt-0.5 text-[11px] text-slate-400">
                        {{
                            isRTL
                                ? 'مسلسلات بحاجة لمواسم تالية'
                                : 'Series missing whole seasons'
                        }}
                    </p>
                </div>
            </div>

            <!-- Missing Episodes -->
            <div
                @click="emit('select-tab', 'episodes')"
                class="group cursor-pointer rounded-3xl border p-5 backdrop-blur-xl transition-all"
                :class="
                    activeTab === 'episodes'
                        ? 'border-amber-500 bg-amber-500/10 shadow-xl shadow-amber-500/10'
                        : 'border-white/10 bg-slate-900/60 hover:border-white/20'
                "
            >
                <div class="flex items-center justify-between">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-500/20 text-amber-400"
                    >
                        <Clock class="h-5 w-5" />
                    </div>
                    <span
                        class="text-2xl font-black text-white transition-colors group-hover:text-amber-300"
                    >
                        {{ missingEpisodesCount }}
                    </span>
                </div>
                <div class="mt-3">
                    <h4 class="text-xs font-bold text-white">
                        {{ isRTL ? 'حلقات فردية مفقودة' : 'Missing Episodes' }}
                    </h4>
                    <p class="mt-0.5 text-[11px] text-slate-400">
                        {{
                            isRTL
                                ? 'فجوات داخل المواسم المحفوظة'
                                : 'Single episode gaps in library'
                        }}
                    </p>
                </div>
            </div>

            <!-- Franchise Collection Gaps -->
            <div
                @click="emit('select-tab', 'collections')"
                class="group cursor-pointer rounded-3xl border p-5 backdrop-blur-xl transition-all"
                :class="
                    activeTab === 'collections'
                        ? 'border-cyan-500 bg-cyan-500/10 shadow-xl shadow-cyan-500/10'
                        : 'border-white/10 bg-slate-900/60 hover:border-white/20'
                "
            >
                <div class="flex items-center justify-between">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-2xl bg-cyan-500/20 text-cyan-400"
                    >
                        <Film class="h-5 w-5" />
                    </div>
                    <span
                        class="text-2xl font-black text-white transition-colors group-hover:text-cyan-300"
                    >
                        {{ collectionGapsCount }}
                    </span>
                </div>
                <div class="mt-3">
                    <h4 class="text-xs font-bold text-white">
                        {{ isRTL ? 'سلاسل أفلام غير مكتملة' : 'Franchise Gaps' }}
                    </h4>
                    <p class="mt-0.5 text-[11px] text-slate-400">
                        {{
                            isRTL
                                ? 'أفلام ناقصة من السلاسل'
                                : 'Missing boxset sequels & prequels'
                        }}
                    </p>
                </div>
            </div>

            <!-- Quality Upgrades Available -->
            <div
                @click="emit('select-tab', 'low_resolution')"
                class="group cursor-pointer rounded-3xl border p-5 backdrop-blur-xl transition-all"
                :class="
                    activeTab === 'low_resolution' ||
                    activeTab === 'poor_quality'
                        ? 'border-rose-500 bg-rose-500/10 shadow-xl shadow-rose-500/10'
                        : 'border-white/10 bg-slate-900/60 hover:border-white/20'
                "
            >
                <div class="flex items-center justify-between">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-500/20 text-rose-400"
                    >
                        <Sparkles class="h-5 w-5" />
                    </div>
                    <span
                        class="text-2xl font-black text-white transition-colors group-hover:text-rose-300"
                    >
                        {{ totalUpgradesCount }}
                    </span>
                </div>
                <div class="mt-3">
                    <h4 class="text-xs font-bold text-white">
                        {{ isRTL ? 'ترقيات جودة متاحة' : 'Quality Upgrades' }}
                    </h4>
                    <p class="mt-0.5 text-[11px] text-slate-400">
                        {{
                            isRTL
                                ? 'نسخ منخفضة الجودة (<720p أو CAM)'
                                : 'Low-res (<720p) or CAM copies'
                        }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
