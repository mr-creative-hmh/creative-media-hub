<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import { Link } from '@inertiajs/vue3';
import { Clock, ChevronRight, ChevronLeft } from 'lucide-vue-next';
import WatchHistoryCard from '@/components/media/WatchHistoryCard.vue';

const props = withDefaults(
    defineProps<{
        type?: 'all' | 'movie' | 'series' | 'episode' | 'collection';
        title?: string;
    }>(),
    {
        type: 'all',
    },
);

const { t, isRTL } = useI18n();
const toast = useToast();
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
        const url =
            props.type && props.type !== 'all'
                ? `/api/watch-history?type=${props.type}`
                : '/api/watch-history';
        const res = await fetch(url);
        if (res.ok) {
            const data = await res.json();
            items.value = Array.isArray(data) ? data : data.items || [];
        }
    } catch (e) {
        console.error('Failed to load watch history items', e);
    }
};

const removeItem = async (item: any, e: MouseEvent) => {
    e.stopPropagation();
    const itemId = item.history_id || item.watchable_id || item.id;
    if (removingIds.value.has(itemId)) return;

    const previousItems = [...items.value];
    const seriesId = item.series_id || item.series?.id || null;
    const watchableId = item.watchable_id || item.id;
    const isSeries =
        item.category === 'series' ||
        item.type === 'episode' ||
        item.watchable_type === 'episode';
    const watchableType =
        item.watchable_type || (isSeries ? 'episode' : 'movie');

    removingIds.value.add(itemId);

    // Optimistic UI removal - if it's a series, remove any cards with this seriesId or itemId
    items.value = items.value.filter((i) => {
        const idMatches = (i.history_id || i.watchable_id || i.id) === itemId;
        const seriesMatches =
            seriesId && (i.series_id || i.series?.id) === seriesId;
        return !idMatches && !seriesMatches;
    });
    emit('item-removed', item);

    try {
        const params = new URLSearchParams({
            type: item.category || item.type || (isSeries ? 'series' : 'movie'),
            ...(watchableId ? { watchable_id: String(watchableId) } : {}),
            ...(watchableType ? { watchable_type: String(watchableType) } : {}),
            ...(seriesId
                ? { series_id: String(seriesId), is_series: '1' }
                : {}),
        });

        const res = await fetch(
            `/api/watch-history/${itemId}?${params.toString()}`,
            {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as any
                        )?.content || '',
                },
            },
        );

        if (!res.ok) {
            throw new Error(`Failed to remove item: HTTP ${res.status}`);
        }

        toast.success(
            t('watch_history.item_removed') ||
                'Item removed from watch history',
        );
    } catch (err) {
        console.error('Failed to delete watch history item', err);
        // Rollback optimistic removal
        items.value = previousItems;

        toast.error(
            t('watch_history.remove_failed') ||
                'Failed to remove item from watch history',
        );
    } finally {
        removingIds.value.delete(itemId);
    }
};

const isBandersnatchItem = (item: any) => {
    return (
        item?.id === 5764 ||
        item?.watchable_id === 5764 ||
        (item?.title && /bandersnatch/i.test(item.title))
    );
};

const playBandersnatch = (item: any, e: MouseEvent) => {
    e.stopPropagation();
    window.dispatchEvent(
        new CustomEvent('play-bandersnatch', { detail: item }),
    );
};

onMounted(() => {
    loadItems();
});
</script>

<template>
    <div v-if="uniqueItems.length > 0" class="mb-10">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <Clock class="h-5 w-5 text-cyan-400" />
                <h3
                    class="font-sans text-base font-black tracking-wider text-slate-900 uppercase dark:text-white"
                >
                    {{ title || t('watch_history.continue_watching') }}
                </h3>
                <span
                    class="rounded-full border border-cyan-500/20 bg-cyan-500/10 px-2 py-0.5 font-mono text-xs font-bold text-cyan-400"
                >
                    {{ uniqueItems.length }}
                </span>
            </div>

            <!-- View All Link to Dedicated Watch History Hub -->
            <Link
                href="/watch-history"
                class="group flex cursor-pointer items-center gap-1.5 text-xs font-bold text-cyan-500 transition-colors hover:text-cyan-400"
            >
                <span>{{ t('watch_history.view_all') }}</span>
                <component
                    :is="isRTL ? ChevronLeft : ChevronRight"
                    class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"
                />
            </Link>
        </div>

        <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4"
        >
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
