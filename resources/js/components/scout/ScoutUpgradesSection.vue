<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { Sparkles, AlertTriangle, Search, Film, Tv } from 'lucide-vue-next';

const props = defineProps<{
    lowResItems?: any[];
    poorQualityItems?: any[];
    activeSubTab: 'low_resolution' | 'poor_quality';
    isLoading?: boolean;
}>();

const emit = defineEmits<{
    (e: 'search-torrent', query: string, meta?: any): void;
    (e: 'switch-tab', tab: 'low_resolution' | 'poor_quality'): void;
}>();

const { t, isRTL } = useI18n();

const items = computed(() => {
    if (props.activeSubTab === 'low_resolution') {
        return props.lowResItems || [];
    }
    return props.poorQualityItems || [];
});
</script>

<template>
    <div class="space-y-6">
        <!-- Sub Tabs -->
        <div class="flex items-center gap-2">
            <button
                @click="emit('switch-tab', 'low_resolution')"
                class="flex items-center gap-2 rounded-xl border px-4 py-2 text-xs font-bold transition-all"
                :class="
                    activeSubTab === 'low_resolution'
                        ? 'border-amber-500/40 bg-amber-500/20 text-amber-300 shadow-sm'
                        : 'border-white/5 bg-white/5 text-slate-400 hover:bg-white/10'
                "
            >
                <Sparkles class="h-3.5 w-3.5" />
                {{
                    isRTL
                        ? 'ملفات منخفضة الدقة (<720p)'
                        : 'Low Resolution (<720p)'
                }}
                <span
                    class="rounded-full bg-black/30 px-1.5 py-0.5 text-[10px] text-white"
                >
                    {{ (lowResItems || []).length }}
                </span>
            </button>

            <button
                @click="emit('switch-tab', 'poor_quality')"
                class="flex items-center gap-2 rounded-xl border px-4 py-2 text-xs font-bold transition-all"
                :class="
                    activeSubTab === 'poor_quality'
                        ? 'border-rose-500/40 bg-rose-500/20 text-rose-300 shadow-sm'
                        : 'border-white/5 bg-white/5 text-slate-400 hover:bg-white/10'
                "
            >
                <AlertTriangle class="h-3.5 w-3.5" />
                {{
                    isRTL
                        ? 'نسخ السينما والترجمات الكورية (CAM)'
                        : 'CAM & Hardcoded Subtitles'
                }}
                <span
                    class="rounded-full bg-black/30 px-1.5 py-0.5 text-[10px] text-white"
                >
                    {{ (poorQualityItems || []).length }}
                </span>
            </button>
        </div>

        <!-- Empty State -->
        <div
            v-if="items.length === 0 && !isLoading"
            class="space-y-2 rounded-3xl border border-white/5 bg-slate-900/40 p-12 text-center"
        >
            <Sparkles class="mx-auto h-8 w-8 text-emerald-500" />
            <h4 class="text-sm font-bold text-white">
                {{
                    isRTL
                        ? 'جودة المكتبة فائقة وممتازة!'
                        : 'All media files are pristine quality!'
                }}
            </h4>
            <p class="text-xs text-slate-500">
                {{
                    isRTL
                        ? 'لا توجد ملفات بحاجة لترقية في هذا القسم'
                        : 'No low quality files detected.'
                }}
            </p>
        </div>

        <!-- Items Grid -->
        <div
            v-else
            class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
        >
            <div
                v-for="item in items"
                :key="item.id"
                class="group space-y-3 rounded-2xl border border-white/10 bg-slate-900/60 p-4 backdrop-blur-xl transition-all hover:border-white/20"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span
                                class="rounded px-1.5 py-0.5 text-[10px] font-bold"
                                :class="
                                    activeSubTab === 'low_resolution'
                                        ? 'bg-amber-500/20 text-amber-300'
                                        : 'bg-rose-500/20 text-rose-300'
                                "
                            >
                                {{
                                    item.resolution ||
                                    item.quality_flag ||
                                    'Upgrade'
                                }}
                            </span>
                            <span
                                v-if="item.year"
                                class="text-[11px] font-semibold text-slate-400"
                                >{{ item.year }}</span
                            >
                        </div>
                        <h4
                            class="mt-1 truncate text-xs font-bold text-white transition-colors group-hover:text-cyan-300"
                        >
                            {{ item.title }}
                        </h4>
                    </div>

                    <button
                        @click="emit('search-torrent', item.title, item)"
                        class="shrink-0 rounded-xl border border-cyan-500/30 bg-cyan-500/10 p-2 text-cyan-300 transition-all hover:bg-cyan-500/20 active:scale-95"
                        :title="
                            isRTL
                                ? 'بحث عن نسخة 1080p/4K أعلى جودة'
                                : 'Search 1080p/4K upgrade'
                        "
                    >
                        <Search class="h-3.5 w-3.5" />
                    </button>
                </div>

                <div
                    class="truncate font-mono text-[10px] text-slate-500"
                    :title="item.file_path"
                >
                    {{ item.file_path }}
                </div>
            </div>
        </div>
    </div>
</template>
