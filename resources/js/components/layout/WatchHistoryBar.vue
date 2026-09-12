<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { Link } from '@inertiajs/vue3';
import { Clock, ChevronRight, ChevronLeft } from 'lucide-vue-next';
import WatchHistoryCard from '@/components/media/WatchHistoryCard.vue';

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

const isBandersnatchItem = (item: any) => {
    return item?.id === 5764 ||
        item?.watchable_id === 5764 ||
        (item?.title && /bandersnatch/i.test(item.title));
};

const playBandersnatch = (item: any, e: MouseEvent) => {
    e.stopPropagation();
    window.dispatchEvent(new CustomEvent('play-bandersnatch', { detail: item }));
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
                    {{ title || t('watch_history.continue_watching') }}
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
            <WatchHistoryCard
                v-for="item in uniqueItems"
                :key="`${item.watchable_type || item.type}_${item.watchable_id || item.id}`"
                :item="item"
                @play="(it, pl) => emit('play', it, pl)"
                @remove="(it, e) => removeItem(it, e)"
                @play-interactive="(it, e) => playBandersnatch(it, e)"
            />
        </div>
    </div>
</template>