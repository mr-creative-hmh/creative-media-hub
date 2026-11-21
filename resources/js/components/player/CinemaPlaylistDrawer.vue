<script setup lang="ts">
import { computed } from 'vue';
import { X, Play, Clock, Tv, Film } from 'lucide-vue-next';
import { useI18n } from '@/i18n/useI18n';
import { formatEpisodeTitle } from '@/lib/mediaTitle';

const props = defineProps<{
    isOpen: boolean;
    playlist: any[];
    currentItemId: number | string;
    isEpisode: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'select', item: any): void;
}>();

const { isRTL, t } = useI18n();

const getTitle = (item: any) => {
    if (props.isEpisode) {
        return formatEpisodeTitle(item, {
            isRTL: isRTL.value,
            includeSeriesName: false,
        });
    }
    if (isRTL.value && item?.title_ar) {
        return item.title_ar;
    }
    return item?.title || 'Media';
};

const formatDuration = (sec?: number) => {
    if (!sec || sec <= 0) return '';
    const mins = Math.floor(sec / 60);
    return `${mins}m`;
};
</script>

<template>
    <div
        v-if="isOpen"
        class="animate-in fade-in absolute inset-0 z-40 flex justify-end bg-black/60 backdrop-blur-md duration-200"
        @click.self="emit('close')"
    >
        <div
            class="flex h-full w-full max-w-sm flex-col space-y-4 border-l border-white/10 bg-slate-900/95 p-6 shadow-2xl sm:max-w-md"
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-white/10 pb-3"
            >
                <div class="flex items-center gap-2">
                    <Tv v-if="isEpisode" class="h-5 w-5 text-purple-400" />
                    <Film v-else class="h-5 w-5 text-cyan-400" />
                    <h3 class="text-base font-bold text-white">
                        {{
                            isEpisode
                                ? t('player.episodes_list') || 'Episodes'
                                : t('player.collection_items') || 'Playlist'
                        }}
                    </h3>
                </div>
                <button
                    @click="emit('close')"
                    class="rounded-xl p-2 text-slate-400 transition-all hover:bg-white/10 hover:text-white"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- List -->
            <div class="flex-1 space-y-2 overflow-y-auto pr-1">
                <button
                    v-for="item in playlist"
                    :key="item.id"
                    @click="emit('select', item)"
                    class="group flex w-full items-center gap-3 rounded-2xl border p-3 text-left transition-all"
                    :class="
                        String(item.id) === String(currentItemId)
                            ? 'border-cyan-500/40 bg-cyan-500/20 text-white shadow-md shadow-cyan-500/10'
                            : 'border-white/5 bg-white/5 text-slate-300 hover:bg-white/10'
                    "
                >
                    <!-- Thumbnail or Placeholder -->
                    <div
                        class="relative flex h-10 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-white/10 bg-slate-800"
                    >
                        <img
                            v-if="item.still_path || item.poster_path"
                            :src="item.still_path || item.poster_path"
                            :alt="item.title"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                        <div
                            v-else
                            class="text-[10px] font-bold text-slate-500"
                        >
                            {{
                                item.episode_number
                                    ? `EP ${item.episode_number}`
                                    : 'MEDIA'
                            }}
                        </div>

                        <!-- Active playing overlay -->
                        <div
                            v-if="String(item.id) === String(currentItemId)"
                            class="absolute inset-0 flex items-center justify-center bg-cyan-500/40 backdrop-blur-[1px]"
                        >
                            <Play
                                class="h-4 w-4 animate-pulse fill-current text-white"
                            />
                        </div>
                    </div>

                    <!-- Meta -->
                    <div class="min-w-0 flex-1">
                        <div
                            class="truncate text-xs font-bold text-white transition-colors group-hover:text-cyan-300"
                        >
                            {{ getTitle(item) }}
                        </div>
                        <div
                            class="mt-0.5 flex items-center gap-2 text-[11px] text-slate-400"
                        >
                            <span
                                v-if="item.duration_seconds"
                                class="flex items-center gap-1 font-mono"
                            >
                                <Clock class="h-3 w-3 text-slate-500" />
                                {{ formatDuration(item.duration_seconds) }}
                            </span>
                            <span
                                v-if="item.resolution"
                                class="py-0.2 rounded bg-white/10 px-1.5 text-[9px] text-slate-300"
                            >
                                {{ item.resolution }}
                            </span>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>
