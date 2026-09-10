<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { Link } from '@inertiajs/vue3';
import { Play, Clock, X, ChevronRight, ChevronLeft, Layers, Tv, Film } from 'lucide-vue-next';

const props = withDefaults(defineProps<{
    type?: 'all' | 'movie' | 'series' | 'episode' | 'collection';
    title?: string;
}>(), {
    type: 'all',
});

const { t, isRTL } = useI18n();
const items = ref<any[]>([]);
const removingIds = ref<Set<number>>(new Set());

const emit = defineEmits(['play', 'item-removed']);

// Defense-in-depth frontend deduplication
const uniqueItems = computed(() => {
    const seen = new Set<string>();
    return items.value.filter((item) => {
        const key = `${item.watchable_type || item.type}_${item.watchable_id || item.id}`;
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });
});

const loadItems = async () => {
    try {
        const url = props.type && props.type !== 'all'
            ? `/api/watch-history?type=${props.type}`
            : '/api/watch-history';
        const res = await fetch(url);
        if (res.ok) {
            const data = await res.json();
            items.value = Array.isArray(data) ? data : (data.items || []);
        }
    } catch (e) {
        console.error('Failed to load watch history items', e);
    }
};

const removeItem = async (item: any, e: MouseEvent) => {
    e.stopPropagation();
    const itemId = item.history_id || item.id;
    if (removingIds.value.has(itemId)) return;

    // Optimistic UI removal
    removingIds.value.add(itemId);
    items.value = items.value.filter((i) => (i.history_id || i.id) !== itemId);
    emit('item-removed', item);

    try {
        const res = await fetch(`/api/watch-history/${itemId}?type=${item.category || item.type}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });

        // Broadcast toast
        window.dispatchEvent(new CustomEvent('app-toast', {
            detail: {
                type: 'success',
                message: t('watch_history.item_removed') || 'Item removed from watch history',
            }
        }));
    } catch (err) {
        console.error('Failed to delete watch history item', err);
    } finally {
        removingIds.value.delete(itemId);
    }
};

onMounted(() => {
    loadItems();
});
</script>

<template>
    <div v-if="uniqueItems.length > 0" class="mb-10">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <Clock class="w-5 h-5 text-cyan-400" />
                <h3 class="font-black text-base text-slate-900 dark:text-white uppercase tracking-wider font-sans">
                    {{ title || t('watch_history.title') }}
                </h3>
                <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                    {{ uniqueItems.length }}
                </span>
            </div>

            <!-- View All Link to Dedicated Watch History Hub -->
            <Link
                href="/watch-history"
                class="flex items-center gap-1.5 text-xs font-bold text-cyan-500 hover:text-cyan-400 transition-colors group cursor-pointer"
            >
                <span>{{ t('watch_history.view_all') }}</span>
                <component :is="isRTL ? ChevronLeft : ChevronRight" class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" />
            </Link>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
            <div
                v-for="item in uniqueItems"
                :key="`${item.watchable_type || item.type}_${item.watchable_id || item.id}`"
                @click="emit('play', item)"
                class="glass-panel group relative rounded-2xl overflow-hidden cursor-pointer border border-slate-200 dark:border-white/10 hover:border-cyan-500/40 hover:shadow-xl hover:shadow-cyan-500/10 transition-all flex flex-col bg-white dark:bg-[#07090E]"
            >
                <!-- Thumbnail Backdrop -->
                <div class="relative aspect-video w-full overflow-hidden bg-slate-900">
                    <img
                        :src="item.backdrop_path || item.poster_path"
                        :alt="item.title"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 opacity-85 group-hover:opacity-100"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent"></div>

                    <!-- Category / Collection Badge -->
                    <div class="absolute top-2.5 left-2.5 flex items-center gap-1.5 z-10">
                        <span
                            v-if="item.category === 'collection' || item.collection_name"
                            class="px-2 py-0.5 rounded-md bg-amber-500/80 backdrop-blur-md text-slate-950 text-[10px] font-black uppercase tracking-wider flex items-center gap-1 shadow-sm"
                        >
                            <Layers class="w-3 h-3" />
                            <span class="max-w-[120px] truncate">{{ item.collection_name || t('watch_history.collections') }}</span>
                        </span>
                        <span
                            v-else-if="item.type === 'episode' || item.category === 'series'"
                            class="px-2 py-0.5 rounded-md bg-indigo-600/80 backdrop-blur-md text-white text-[10px] font-black tracking-wider flex items-center gap-1 shadow-sm"
                        >
                            <Tv class="w-3 h-3" />
                            <span>S{{ item.season_number }} E{{ item.episode_number }}</span>
                        </span>
                    </div>

                    <!-- Delete / Remove Button (hover trigger with tooltip) -->
                    <button
                        @click="(e) => removeItem(item, e)"
                        :title="t('watch_history.remove_tooltip')"
                        class="absolute top-2.5 right-2.5 z-20 w-7 h-7 rounded-full bg-black/60 hover:bg-rose-600 text-slate-300 hover:text-white flex items-center justify-center transition-all opacity-0 group-hover:opacity-100 backdrop-blur-sm border border-white/20 hover:border-rose-500 cursor-pointer shadow-md active:scale-90"
                    >
                        <X class="w-3.5 h-3.5" />
                    </button>

                    <!-- Play overlay button -->
                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40 backdrop-blur-xs">
                        <div class="w-12 h-12 rounded-full bg-cyan-500 text-slate-950 flex items-center justify-center shadow-xl shadow-cyan-500/50 group-hover:scale-110 active:scale-95 transition-transform">
                            <Play class="w-5 h-5 fill-current ml-0.5" />
                        </div>
                    </div>

                    <!-- Progress bar on thumbnail bottom -->
                    <div class="absolute bottom-0 inset-x-0 h-1.5 bg-black/60">
                        <div
                            class="h-full bg-gradient-to-r from-cyan-400 to-blue-500 rounded-r-full"
                            :style="{ width: `${item.percent}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Info footer -->
                <div class="p-3.5 flex items-center justify-between">
                    <div class="truncate flex-1">
                        <h4 class="font-bold text-sm text-slate-900 dark:text-white truncate group-hover:text-cyan-400 transition-colors">
                            {{ isRTL && item.title_ar ? item.title_ar : item.title }}
                        </h4>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                {{ item.percent }}% {{ isRTL ? 'مكتمل' : 'watched' }}
                            </span>
                            <span class="text-[10px] text-slate-400 opacity-60">•</span>
                            <span class="text-[11px] font-mono text-cyan-400">
                                {{ item.current_time_formatted }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
