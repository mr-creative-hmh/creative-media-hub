<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Search,
    Sparkles,
    CheckCircle2,
    Film,
    Tv,
    Download,
    Star,
} from '@lucide/vue';

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
        <div
            class="rounded-3xl border border-white/10 bg-slate-900/60 p-6 backdrop-blur-xl"
        >
            <form
                @submit.prevent="handleSearch"
                class="flex items-center gap-3"
            >
                <div class="relative flex-1">
                    <Search
                        class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="customQuery"
                        type="text"
                        :placeholder="
                            isRTL
                                ? 'ابحث عن أي فيلم أو مسلسل عالمي لاستكشافه وتنزيله...'
                                : 'Search any movie or TV series on TMDB to scout and acquire...'
                        "
                        class="w-full rounded-2xl border border-white/10 bg-white/5 py-3 pr-4 pl-10 text-xs text-white placeholder-slate-500 transition-colors focus:border-cyan-400 focus:outline-none"
                    />
                </div>
                <button
                    type="submit"
                    class="flex shrink-0 items-center gap-2 rounded-2xl bg-cyan-500 px-6 py-3 text-xs font-black text-slate-950 shadow-lg shadow-cyan-500/20 transition-all hover:bg-cyan-400 active:scale-95"
                >
                    <Search class="h-4 w-4" />
                    {{ isRTL ? 'استكشاف' : 'Discover' }}
                </button>
            </form>
        </div>

        <!-- Trending Section -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Sparkles class="h-5 w-5 text-amber-400" />
                    <h3 class="text-base font-extrabold text-white">
                        {{
                            isRTL
                                ? 'الأكثر رواجاً هذا الأسبوع (TMDB Trending)'
                                : 'Trending Worldwide This Week'
                        }}
                    </h3>
                </div>
            </div>

            <!-- Trending Cards Grid -->
            <div
                class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
            >
                <div
                    v-for="item in trendingItems || []"
                    :key="item.id"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-white/10 bg-slate-900/60 backdrop-blur-xl transition-all hover:border-cyan-500/30"
                >
                    <!-- Poster -->
                    <div
                        class="relative aspect-[2/3] w-full overflow-hidden bg-slate-800"
                    >
                        <img
                            v-if="item.poster_path"
                            :src="`https://image.tmdb.org/t/p/w500${item.poster_path}`"
                            :alt="item.title || item.name"
                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                            loading="lazy"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center text-xs font-bold text-slate-600"
                        >
                            NO POSTER
                        </div>

                        <!-- Rating pill -->
                        <div
                            v-if="item.vote_average"
                            class="absolute top-2 right-2 flex items-center gap-0.5 rounded-md border border-white/10 bg-black/70 px-1.5 py-0.5 text-[10px] font-bold text-amber-400 backdrop-blur-md"
                        >
                            <Star class="h-3 w-3 fill-current" />
                            {{ item.vote_average.toFixed(1) }}
                        </div>

                        <!-- In-Library badge -->
                        <div
                            v-if="item.in_library"
                            class="absolute top-2 left-2 flex items-center gap-1 rounded-md bg-emerald-500/80 px-1.5 py-0.5 text-[10px] font-black text-slate-950 shadow-md backdrop-blur-md"
                        >
                            <CheckCircle2 class="h-3 w-3" />
                            In Library
                        </div>
                    </div>

                    <!-- Meta & Actions -->
                    <div
                        class="flex flex-1 flex-col justify-between space-y-2 p-3"
                    >
                        <div>
                            <h4
                                class="truncate text-xs font-bold text-white"
                                :title="item.title || item.name"
                            >
                                {{ item.title || item.name }}
                            </h4>
                            <div class="mt-0.5 text-[10px] text-slate-400">
                                {{
                                    (
                                        item.release_date ||
                                        item.first_air_date ||
                                        ''
                                    ).substring(0, 4)
                                }}
                            </div>
                        </div>

                        <button
                            v-if="!item.in_library"
                            @click="
                                emit(
                                    'search-torrent',
                                    item.title || item.name,
                                    item,
                                )
                            "
                            class="flex w-full items-center justify-center gap-1 rounded-xl border border-cyan-500/30 bg-cyan-500/10 py-1.5 text-[11px] font-bold text-cyan-300 transition-all hover:bg-cyan-500/20 active:scale-95"
                        >
                            <Download class="h-3 w-3" />
                            {{ isRTL ? 'اقتناص تورنت' : 'Scout' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
