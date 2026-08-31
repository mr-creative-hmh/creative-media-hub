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
        class="glass-card group relative rounded-2xl overflow-hidden cursor-pointer flex flex-col border border-white/10 hover:border-cyan-500/40 transition-all duration-300"
    >
        <!-- Poster Container -->
        <div class="relative aspect-[2/3] w-full overflow-hidden bg-slate-900">
            <img
                :src="item.poster_path"
                :alt="item.title"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                loading="lazy"
            />

            <!-- Dark Overlay on Hover -->
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/30 to-transparent opacity-60 group-hover:opacity-80 transition-opacity"></div>

            <!-- Top Quality & Favorite Badges -->
            <div class="absolute top-2.5 inset-x-2.5 flex items-center justify-between z-10">
                <span class="cinema-badge bg-black/60 text-cyan-400 border border-cyan-500/30 text-[10px]">
                    {{ item.resolution || '1080p' }}
                </span>
                <button
                    @click.stop="emit('toggleFavorite', item)"
                    class="w-7 h-7 rounded-full bg-black/60 hover:bg-black/80 flex items-center justify-center transition-transform hover:scale-110"
                >
                    <Heart class="w-3.5 h-3.5" :class="item.is_favorite ? 'fill-red-500 text-red-500' : 'text-slate-300'" />
                </button>
            </div>

            <!-- Subtitle Badges on Poster -->
            <div class="absolute bottom-2.5 inset-x-2.5 flex items-center justify-between z-10">
                <div class="flex items-center gap-1">
                    <span
                        v-if="hasArSub"
                        class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30"
                        title="Arabic Subtitles Available"
                    >
                        AR
                    </span>
                    <span
                        v-if="hasEnSub"
                        class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30"
                        title="English Subtitles Available"
                    >
                        EN
                    </span>
                </div>

                <!-- Rating -->
                <div v-if="item.rating" class="flex items-center gap-1 px-1.5 py-0.5 rounded bg-black/60 text-amber-300 text-[10px] font-bold">
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
        <div class="p-3 flex flex-col flex-1 justify-between bg-slate-950/40">
            <h3 class="font-bold text-sm text-slate-100 truncate group-hover:text-cyan-400 transition-colors">
                {{ isRTL && item.title_ar ? item.title_ar : item.title }}
            </h3>
            <div class="flex items-center justify-between mt-1 text-[11px] text-slate-400">
                <span>{{ item.release_year }}</span>
                <span v-if="item.runtime_minutes">{{ item.runtime_minutes }} {{ t('common.minutes') }}</span>
                <span v-else-if="item.seasons?.length">{{ item.seasons.length }} {{ t('common.seasons') }}</span>
            </div>
        </div>
    </div>
</template>
