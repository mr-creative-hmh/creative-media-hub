<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { router } from '@inertiajs/vue3';
import { Filter, Sparkles, SlidersHorizontal, Check } from 'lucide-vue-next';

const props = defineProps<{
    genres: any[];
    filters: Record<string, any>;
    vibes?: string[];
}>();

const { t, isRTL } = useI18n();

const selectedGenre = ref(props.filters.genre || '');
const selectedResolution = ref(props.filters.resolution || '');
const selectedSort = ref(props.filters.sort || 'rating');
const selectedVibe = ref(props.filters.vibe || '');
const favoriteOnly = ref(props.filters.favorite_only === '1' || props.filters.favorite_only === true);

const applyFilters = () => {
    router.get(window.location.pathname, {
        ...props.filters,
        genre: selectedGenre.value || undefined,
        resolution: selectedResolution.value || undefined,
        sort: selectedSort.value || undefined,
        vibe: selectedVibe.value || undefined,
        favorite_only: favoriteOnly.value ? 1 : undefined,
    }, { preserveState: true });
};

const selectGenre = (slug: string) => {
    selectedGenre.value = selectedGenre.value === slug ? '' : slug;
    applyFilters();
};

const selectVibe = (v: string) => {
    selectedVibe.value = selectedVibe.value === v ? '' : v;
    applyFilters();
};
</script>

<template>
    <div class="glass-panel rounded-2xl p-4 mb-8 border border-white/10 space-y-4">
        <!-- Top Filter Bar: Genres Chips -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
            <button
                @click="selectGenre('')"
                class="px-3.5 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-all"
                :class="!selectedGenre ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20' : 'bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10'"
            >
                {{ t('common.all_genres') }}
            </button>
            <button
                v-for="g in genres"
                :key="g.id"
                @click="selectGenre(g.slug)"
                class="px-3.5 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-all"
                :class="selectedGenre === g.slug ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20' : 'bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10'"
            >
                {{ isRTL && g.name_ar ? g.name_ar : g.name_en }}
            </button>
        </div>

        <!-- AI Mood & Vibe Chips -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none pt-1 border-t border-white/5">
            <div class="flex items-center gap-1.5 text-xs font-extrabold text-cyan-400 shrink-0">
                <Sparkles class="w-3.5 h-3.5" />
                <span>{{ isRTL ? 'المزاج السينمائي:' : 'Vibe:' }}</span>
            </div>
            <button
                v-for="v in ['Mind-Bending', 'Heist Thriller', 'Epic Masterpiece', 'Intense Drama', '80s Nostalgia', 'Space Exploration']"
                :key="v"
                @click="selectVibe(v)"
                class="px-2.5 py-1 rounded-lg text-[11px] font-semibold whitespace-nowrap transition-all flex items-center gap-1"
                :class="selectedVibe === v ? 'bg-indigo-600 text-white border border-indigo-400' : 'bg-white/5 hover:bg-white/10 text-slate-400 border border-white/5'"
            >
                <span>{{ v }}</span>
                <Check v-if="selectedVibe === v" class="w-3 h-3" />
            </button>
        </div>

        <!-- Bottom Controls: Quality, Favorites & Sorting -->
        <div class="flex items-center justify-between flex-wrap gap-4 pt-2 border-t border-white/5">
            <!-- Quality / Resolution -->
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400 font-semibold">{{ t('common.quality') }}:</span>
                <select
                    v-model="selectedResolution"
                    @change="applyFilters"
                    class="h-8 rounded-lg bg-white/5 border border-white/10 text-xs text-slate-200 px-2.5 focus:border-cyan-500 outline-none"
                >
                    <option value="">{{ t('common.all_qualities') }}</option>
                    <option value="4K">4K UHD</option>
                    <option value="1080p">1080p FHD</option>
                    <option value="720p">720p HD</option>
                </select>
            </div>

            <!-- Favorites Checkbox -->
            <label class="flex items-center gap-2 text-xs font-semibold text-slate-300 cursor-pointer">
                <input
                    type="checkbox"
                    v-model="favoriteOnly"
                    @change="applyFilters"
                    class="rounded bg-white/10 border-white/20 text-cyan-500 focus:ring-cyan-500/20"
                />
                <span>{{ t('common.favorites_only') }}</span>
            </label>

            <!-- Sort By -->
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400 font-semibold">{{ t('common.sort_by') }}:</span>
                <select
                    v-model="selectedSort"
                    @change="applyFilters"
                    class="h-8 rounded-lg bg-white/5 border border-white/10 text-xs text-slate-200 px-2.5 focus:border-cyan-500 outline-none"
                >
                    <option value="rating">{{ t('common.top_rated') }}</option>
                    <option value="year">{{ t('common.newest') }}</option>
                    <option value="title">{{ t('common.title_az') }}</option>
                </select>
            </div>
        </div>
    </div>
</template>
