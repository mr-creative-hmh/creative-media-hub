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
        class="absolute inset-0 bg-black/60 backdrop-blur-md z-40 flex justify-end animate-in fade-in duration-200"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-sm sm:max-w-md h-full bg-slate-900/95 border-l border-white/10 shadow-2xl p-6 flex flex-col space-y-4">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2">
                    <Tv v-if="isEpisode" class="w-5 h-5 text-purple-400" />
                    <Film v-else class="w-5 h-5 text-cyan-400" />
                    <h3 class="text-base font-bold text-white">
                        {{ isEpisode ? (t('player.episodes_list') || 'Episodes') : (t('player.collection_items') || 'Playlist') }}
                    </h3>
                </div>
                <button
                    @click="emit('close')"
                    class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 transition-all"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- List -->
            <div class="flex-1 overflow-y-auto space-y-2 pr-1">
                <button
                    v-for="item in playlist"
                    :key="item.id"
                    @click="emit('select', item)"
                    class="w-full p-3 rounded-2xl border text-left transition-all flex items-center gap-3 group"
                    :class="String(item.id) === String(currentItemId)
                        ? 'bg-cyan-500/20 text-white border-cyan-500/40 shadow-md shadow-cyan-500/10'
                        : 'bg-white/5 text-slate-300 border-white/5 hover:bg-white/10'"
                >
                    <!-- Thumbnail or Placeholder -->
                    <div class="w-16 h-10 rounded-lg overflow-hidden bg-slate-800 shrink-0 relative flex items-center justify-center border border-white/10">
                        <img
                            v-if="item.still_path || item.poster_path"
                            :src="item.still_path || item.poster_path"
                            :alt="item.title"
                            class="w-full h-full object-cover"
                            loading="lazy"
                        />
                        <div v-else class="text-slate-500 text-[10px] font-bold">
                            {{ item.episode_number ? `EP ${item.episode_number}` : 'MEDIA' }}
                        </div>

                        <!-- Active playing overlay -->
                        <div
                            v-if="String(item.id) === String(currentItemId)"
                            class="absolute inset-0 bg-cyan-500/40 backdrop-blur-[1px] flex items-center justify-center"
                        >
                            <Play class="w-4 h-4 text-white fill-current animate-pulse" />
                        </div>
                    </div>

                    <!-- Meta -->
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-bold text-white truncate group-hover:text-cyan-300 transition-colors">
                            {{ getTitle(item) }}
                        </div>
                        <div class="text-[11px] text-slate-400 flex items-center gap-2 mt-0.5">
                            <span v-if="item.duration_seconds" class="flex items-center gap-1 font-mono">
                                <Clock class="w-3 h-3 text-slate-500" />
                                {{ formatDuration(item.duration_seconds) }}
                            </span>
                            <span v-if="item.resolution" class="px-1.5 py-0.2 rounded text-[9px] bg-white/10 text-slate-300">
                                {{ item.resolution }}
                            </span>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>

