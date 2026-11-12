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
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all border flex items-center gap-2"
                :class="activeSubTab === 'low_resolution'
                    ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 shadow-sm'
                    : 'bg-white/5 text-slate-400 border-white/5 hover:bg-white/10'"
            >
                <Sparkles class="w-3.5 h-3.5" />
                {{ isRTL ? 'ملفات منخفضة الدقة (<720p)' : 'Low Resolution (<720p)' }}
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-black/30 text-white">
                    {{ (lowResItems || []).length }}
                </span>
            </button>

            <button
                @click="emit('switch-tab', 'poor_quality')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all border flex items-center gap-2"
                :class="activeSubTab === 'poor_quality'
                    ? 'bg-rose-500/20 text-rose-300 border-rose-500/40 shadow-sm'
                    : 'bg-white/5 text-slate-400 border-white/5 hover:bg-white/10'"
            >
                <AlertTriangle class="w-3.5 h-3.5" />
                {{ isRTL ? 'نسخ السينما والترجمات الكورية (CAM)' : 'CAM & Hardcoded Subtitles' }}
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-black/30 text-white">
                    {{ (poorQualityItems || []).length }}
                </span>
            </button>
        </div>

        <!-- Empty State -->
        <div v-if="items.length === 0 && !isLoading" class="p-12 rounded-3xl bg-slate-900/40 border border-white/5 text-center space-y-2">
            <Sparkles class="w-8 h-8 text-emerald-500 mx-auto" />
            <h4 class="text-sm font-bold text-white">{{ isRTL ? 'جودة المكتبة فائقة وممتازة!' : 'All media files are pristine quality!' }}</h4>
            <p class="text-xs text-slate-500">{{ isRTL ? 'لا توجد ملفات بحاجة لترقية في هذا القسم' : 'No low quality files detected.' }}</p>
        </div>

        <!-- Items Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="item in items"
                :key="item.id"
                class="p-4 rounded-2xl bg-slate-900/60 border border-white/10 hover:border-white/20 backdrop-blur-xl transition-all space-y-3 group"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                :class="activeSubTab === 'low_resolution' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300'">
                                {{ item.resolution || item.quality_flag || 'Upgrade' }}
                            </span>
                            <span v-if="item.year" class="text-[11px] text-slate-400 font-semibold">{{ item.year }}</span>
                        </div>
                        <h4 class="text-xs font-bold text-white mt-1 truncate group-hover:text-cyan-300 transition-colors">
                            {{ item.title }}
                        </h4>
                    </div>

                    <button
                        @click="emit('search-torrent', item.title, item)"
                        class="p-2 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 transition-all active:scale-95 shrink-0"
                        :title="isRTL ? 'بحث عن نسخة 1080p/4K أعلى جودة' : 'Search 1080p/4K upgrade'"
                    >
                        <Search class="w-3.5 h-3.5" />
                    </button>
                </div>

                <div class="text-[10px] font-mono text-slate-500 truncate" :title="item.file_path">
                    {{ item.file_path }}
                </div>
            </div>
        </div>
    </div>
</template>

