<script setup lang="ts">
import { useI18n } from '@/i18n/useI18n';
import { Play, Star, Heart, Subtitles, Film } from 'lucide-vue-next';

const props = defineProps<{
    item: any;
}>();

const emit = defineEmits(['play', 'details', 'toggleFavorite']);

const { t, isRTL } = useI18n();

const hasArSub = props.item.subtitles?.some((s: any) => s.language === 'ar');
const hasEnSub = props.item.subtitles?.some((s: any) => s.language === 'en');
</script>

<template>
    <div
        @click="emit('details', item)"
        class="glass-card group relative rounded-2xl overflow-hidden cursor-pointer flex flex-col border border-slate-200 dark:border-white/10 hover:border-cyan-500/50 transition-all duration-300 shadow-sm hover:shadow-lg"
    >
        <!-- Poster Container -->
        <div class="relative aspect-[2/3] w-full overflow-hidden bg-slate-200 dark:bg-slate-900">
            <img
                :src="item.poster_path || '/placeholder.jpg'"
                :alt="item.title"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                loading="lazy"
            />

            <!-- Dark Overlay on Hover -->
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/30 to-transparent opacity-60 group-hover:opacity-80 transition-opacity"></div>

            <!-- Top Quality & Favorite Badges (z-30, strictly above hover overlay and blur) -->
            <div class="absolute top-2.5 inset-x-2.5 flex items-center justify-between z-30 pointer-events-none">
                <span class="cinema-badge bg-black/70 text-cyan-400 border border-cyan-500/30 text-[10px] pointer-events-auto">
                    {{ item.resolution || '1080p' }}
                </span>
                <button
                    @click.stop="emit('toggleFavorite', item)"
                    class="w-7 h-7 rounded-full bg-black/70 hover:bg-black/90 flex items-center justify-center transition-all hover:scale-125 active:scale-90 pointer-events-auto z-40 cursor-pointer shadow-md backdrop-blur-xs border border-white/10"
                    :title="item.is_favorite ? 'Remove from favorites' : 'Add to favorites'"
                >
                    <Heart class="w-3.5 h-3.5 transition-colors" :class="item.is_favorite ? 'fill-red-500 text-red-500' : 'text-white hover:text-red-400'" />
                </button>
            </div>

            <!-- Subtitle Badges on Poster (z-30, strictly above hover overlay) -->
            <div class="absolute bottom-2.5 inset-x-2.5 flex items-center justify-between z-30 pointer-events-none">
                <div class="flex items-center gap-1 pointer-events-auto">
                    <span
                        v-if="hasArSub"
                        class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500/90 text-white shadow-sm"
                        title="Arabic Subtitles Available"
                    >
                        AR
                    </span>
                    <span
                        v-if="hasEnSub"
                        class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-500/90 text-white shadow-sm"
                        title="English Subtitles Available"
                    >
                        EN
                    </span>
                </div>

                <!-- Rating -->
                <div v-if="item.rating" class="flex items-center gap-1 px-1.5 py-0.5 rounded bg-black/70 text-amber-300 text-[10px] font-bold pointer-events-auto">
                    <Star class="w-3 h-3 fill-current" />
                    <span>{{ item.rating }}</span>
                </div>
            </div>

            <!-- Quick Play Button Hover Overlay -->
            <div
                @click.stop="emit('play', item)"
                class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/30 z-20"
            >
                <div class="w-12 h-12 rounded-full bg-cyan-500 text-slate-950 flex items-center justify-center shadow-xl shadow-cyan-500/50 group-hover:scale-110 active:scale-95 transition-transform">
                    <Play class="w-6 h-6 fill-current ml-0.5" />
                </div>
            </div>
        </div>

        <!-- Bottom Info -->
        <div class="p-3 flex flex-col flex-1 justify-between bg-white dark:bg-slate-950/40">
            <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 truncate group-hover:text-cyan-600 dark:group-hover:text-cyan-400 transition-colors">
                {{ isRTL && item.title_ar ? item.title_ar : item.title }}
            </h3>
            <div class="flex items-center justify-between mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                <span>{{ item.release_year }}</span>
                <span v-if="item.runtime_minutes">{{ item.runtime_minutes }} {{ t('common.minutes') }}</span>
                <span v-else-if="item.seasons?.length">{{ item.seasons.length }} {{ t('common.seasons') }}</span>
            </div>
        </div>
    </div>
</template>
