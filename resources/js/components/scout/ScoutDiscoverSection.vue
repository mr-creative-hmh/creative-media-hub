<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { Search, Sparkles, CheckCircle2, Film, Tv, Download, Star } from 'lucide-vue-next';

const props = defineProps<{
    trendingItems?: any[];
    isLoading?: boolean;
}>();

const emit = defineEmits<{
    (e: 'search-torrent', query: string, meta?: any): void;
    (e: 'search-custom', query: string): void;
}>();

const { t, isRTL } = useI18n();

const customQuery = ref('');

const handleSearch = () => {
    if (!customQuery.value.trim()) return;
    emit('search-custom', customQuery.value.trim());
};
</script>

<template>
    <div class="space-y-6">
        <!-- Search Bar -->
        <div class="p-6 rounded-3xl bg-slate-900/60 border border-white/10 backdrop-blur-xl">
            <form @submit.prevent="handleSearch" class="flex items-center gap-3">
                <div class="relative flex-1">
                    <Search class="w-4 h-4 text-slate-400 absolute top-1/2 -translate-y-1/2 left-3.5" />
                    <input
                        v-model="customQuery"
                        type="text"
                        :placeholder="isRTL ? 'ابحث عن أي فيلم أو مسلسل عالمي لاستكشافه وتنزيله...' : 'Search any movie or TV series on TMDB to scout and acquire...'"
                        class="w-full pl-10 pr-4 py-3 rounded-2xl bg-white/5 border border-white/10 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-cyan-400 transition-colors"
                    />
                </div>
                <button
                    type="submit"
                    class="px-6 py-3 rounded-2xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/20 transition-all active:scale-95 flex items-center gap-2 shrink-0"
                >
                    <Search class="w-4 h-4" />
                    {{ isRTL ? 'استكشاف' : 'Discover' }}
                </button>
            </form>
        </div>

        <!-- Trending Section -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Sparkles class="w-5 h-5 text-amber-400" />
                    <h3 class="text-base font-extrabold text-white">
                        {{ isRTL ? 'الأكثر رواجاً هذا الأسبوع (TMDB Trending)' : 'Trending Worldwide This Week' }}
                    </h3>
                </div>
            </div>

            <!-- Trending Cards Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <div
                    v-for="item in (trendingItems || [])"
                    :key="item.id"
                    class="rounded-2xl bg-slate-900/60 border border-white/10 hover:border-cyan-500/30 overflow-hidden backdrop-blur-xl transition-all group flex flex-col"
                >
                    <!-- Poster -->
                    <div class="aspect-[2/3] w-full bg-slate-800 relative overflow-hidden">
                        <img
                            v-if="item.poster_path"
                            :src="`https://image.tmdb.org/t/p/w500${item.poster_path}`"
                            :alt="item.title || item.name"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                            loading="lazy"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center text-slate-600 text-xs font-bold">
                            NO POSTER
                        </div>

                        <!-- Rating pill -->
                        <div v-if="item.vote_average" class="absolute top-2 right-2 px-1.5 py-0.5 rounded-md bg-black/70 backdrop-blur-md text-amber-400 text-[10px] font-bold flex items-center gap-0.5 border border-white/10">
                            <Star class="w-3 h-3 fill-current" />
                            {{ item.vote_average.toFixed(1) }}
                        </div>

                        <!-- In-Library badge -->
                        <div v-if="item.in_library" class="absolute top-2 left-2 px-1.5 py-0.5 rounded-md bg-emerald-500/80 backdrop-blur-md text-slate-950 text-[10px] font-black flex items-center gap-1 shadow-md">
                            <CheckCircle2 class="w-3 h-3" />
                            In Library
                        </div>
                    </div>

                    <!-- Meta & Actions -->
                    <div class="p-3 flex-1 flex flex-col justify-between space-y-2">
                        <div>
                            <h4 class="text-xs font-bold text-white truncate" :title="item.title || item.name">
                                {{ item.title || item.name }}
                            </h4>
                            <div class="text-[10px] text-slate-400 mt-0.5">
                                {{ (item.release_date || item.first_air_date || '').substring(0, 4) }}
                            </div>
                        </div>

                        <button
                            v-if="!item.in_library"
                            @click="emit('search-torrent', item.title || item.name, item)"
                            class="w-full py-1.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 text-[11px] font-bold transition-all flex items-center justify-center gap-1 active:scale-95"
                        >
                            <Download class="w-3 h-3" />
                            {{ isRTL ? 'اقتناص تورنت' : 'Scout' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

