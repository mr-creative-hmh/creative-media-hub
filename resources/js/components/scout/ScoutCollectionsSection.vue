<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { Film, Search, Layers, CheckCircle2 } from 'lucide-vue-next';

const props = defineProps<{
    collectionGaps?: any[];
    isLoading?: boolean;
}>();

const emit = defineEmits<{
    (e: 'search-torrent', query: string, meta?: any): void;
}>();

const { t, isRTL } = useI18n();

const collectionsList = computed(() => props.collectionGaps || []);
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <Film class="w-5 h-5 text-cyan-400" />
                <h3 class="text-base font-extrabold text-white">
                    {{ isRTL ? 'نواقص سلاسل الأفلام (Franchises)' : 'Franchise Boxset Gaps' }}
                </h3>
            </div>
            <span class="text-xs text-slate-400 font-semibold">
                {{ collectionsList.length }} {{ isRTL ? 'سلسلة غير مكتملة' : 'incomplete collections' }}
            </span>
        </div>

        <!-- Empty State -->
        <div v-if="collectionsList.length === 0 && !isLoading" class="p-12 rounded-3xl bg-slate-900/40 border border-white/5 text-center space-y-2">
            <Film class="w-8 h-8 text-slate-600 mx-auto" />
            <h4 class="text-sm font-bold text-white">{{ isRTL ? 'جميع سلاسل الأفلام مكتملة!' : 'All movie franchises are complete!' }}</h4>
            <p class="text-xs text-slate-500">{{ isRTL ? 'لا توجد أجزاء ناقصة في السلاسل المحفوظة' : 'No missing sequels or prequels detected.' }}</p>
        </div>

        <!-- Collections Cards -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
                v-for="col in collectionsList"
                :key="col.collection_name || col.id"
                class="p-5 rounded-3xl bg-slate-900/60 border border-white/10 hover:border-cyan-500/30 backdrop-blur-xl transition-all space-y-4 group"
            >
                <!-- Collection Header -->
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-500/20 text-cyan-300">
                                Franchise
                            </span>
                            <span class="text-xs text-slate-400 font-semibold font-mono">
                                {{ col.collected_count || 0 }} / {{ col.total_count || 0 }} {{ isRTL ? 'أفلام' : 'movies' }}
                            </span>
                        </div>
                        <h4 class="text-sm font-bold text-white mt-1 truncate group-hover:text-cyan-300 transition-colors">
                            {{ col.collection_name }}
                        </h4>
                    </div>

                    <!-- Search Button -->
                    <button
                        @click="emit('search-torrent', col.collection_name, col)"
                        class="p-2.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 transition-all active:scale-95 shrink-0"
                        :title="isRTL ? 'بحث عن السلسلة' : 'Search torrents for collection'"
                    >
                        <Search class="w-4 h-4" />
                    </button>
                </div>

                <!-- Progress Bar -->
                <div class="h-2 w-full rounded-full bg-white/10 overflow-hidden relative">
                    <div
                        class="h-full bg-gradient-to-r from-cyan-500 to-cyan-400 rounded-full"
                        :style="{ width: `${((col.collected_count || 0) / Math.max(1, col.total_count || 1)) * 100}%` }"
                    />
                </div>

                <!-- Missing Movies -->
                <div v-if="col.missing_movies && col.missing_movies.length > 0" class="space-y-1.5 pt-2 border-t border-white/5">
                    <div
                        v-for="(m, idx) in col.missing_movies.slice(0, 3)"
                        :key="idx"
                        class="flex items-center justify-between text-xs text-slate-300 py-1"
                    >
                        <span class="truncate">{{ m.title || m }}</span>
                        <button
                            @click="emit('search-torrent', m.title || m, { type: 'movie' })"
                            class="text-cyan-400 hover:text-cyan-300 font-bold text-[11px] shrink-0 ml-2"
                        >
                            {{ isRTL ? 'بحث' : 'Scout' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

