<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { Film, Search, Layers, CheckCircle2 } from '@lucide/vue';

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
                <Film class="h-5 w-5 text-cyan-400" />
                <h3 class="text-base font-extrabold text-white">
                    {{
                        isRTL
                            ? 'نواقص سلاسل الأفلام (Franchises)'
                            : 'Franchise Boxset Gaps'
                    }}
                </h3>
            </div>
            <span class="text-xs font-semibold text-slate-400">
                {{ collectionsList.length }}
                {{ isRTL ? 'سلسلة غير مكتملة' : 'incomplete collections' }}
            </span>
        </div>

        <!-- Empty State -->
        <div
            v-if="collectionsList.length === 0 && !isLoading"
            class="space-y-2 rounded-3xl border border-white/5 bg-slate-900/40 p-12 text-center"
        >
            <Film class="mx-auto h-8 w-8 text-slate-600" />
            <h4 class="text-sm font-bold text-white">
                {{
                    isRTL
                        ? 'جميع سلاسل الأفلام مكتملة!'
                        : 'All movie franchises are complete!'
                }}
            </h4>
            <p class="text-xs text-slate-500">
                {{
                    isRTL
                        ? 'لا توجد أجزاء ناقصة في السلاسل المحفوظة'
                        : 'No missing sequels or prequels detected.'
                }}
            </p>
        </div>

        <!-- Collections Cards -->
        <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div
                v-for="col in collectionsList"
                :key="col.collection_name || col.id"
                class="group space-y-4 rounded-3xl border border-white/10 bg-slate-900/60 p-5 backdrop-blur-xl transition-all hover:border-cyan-500/30"
            >
                <!-- Collection Header -->
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span
                                class="rounded bg-cyan-500/20 px-2 py-0.5 text-[10px] font-bold text-cyan-300"
                            >
                                Franchise
                            </span>
                            <span
                                class="font-mono text-xs font-semibold text-slate-400"
                            >
                                {{ col.collected_count || 0 }} /
                                {{ col.total_count || 0 }}
                                {{ isRTL ? 'أفلام' : 'movies' }}
                            </span>
                        </div>
                        <h4
                            class="mt-1 truncate text-sm font-bold text-white transition-colors group-hover:text-cyan-300"
                        >
                            {{ col.collection_name }}
                        </h4>
                    </div>

                    <!-- Search Button -->
                    <button
                        @click="
                            emit('search-torrent', col.collection_name, col)
                        "
                        class="shrink-0 rounded-xl border border-cyan-500/30 bg-cyan-500/10 p-2.5 text-cyan-300 transition-all hover:bg-cyan-500/20 active:scale-95"
                        :title="
                            isRTL
                                ? 'بحث عن السلسلة'
                                : 'Search torrents for collection'
                        "
                    >
                        <Search class="h-4 w-4" />
                    </button>
                </div>

                <!-- Progress Bar -->
                <div
                    class="relative h-2 w-full overflow-hidden rounded-full bg-white/10"
                >
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-cyan-400"
                        :style="{
                            width: `${((col.collected_count || 0) / Math.max(1, col.total_count || 1)) * 100}%`,
                        }"
                    />
                </div>

                <!-- Missing Movies -->
                <div
                    v-if="col.missing_movies && col.missing_movies.length > 0"
                    class="space-y-1.5 border-t border-white/5 pt-2"
                >
                    <div
                        v-for="(m, idx) in col.missing_movies.slice(0, 3)"
                        :key="idx"
                        class="flex items-center justify-between py-1 text-xs text-slate-300"
                    >
                        <span class="truncate">{{ m.title || m }}</span>
                        <button
                            @click="
                                emit('search-torrent', m.title || m, {
                                    type: 'movie',
                                })
                            "
                            class="ml-2 shrink-0 text-[11px] font-bold text-cyan-400 hover:text-cyan-300"
                        >
                            {{ isRTL ? 'بحث' : 'Scout' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
