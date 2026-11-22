<script setup lang="ts">
import { useI18n } from '@/i18n/useI18n';
import { Play, Star, Heart, Subtitles, Film } from '@lucide/vue';

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
        class="glass-card group relative flex cursor-pointer flex-col overflow-hidden rounded-2xl border border-slate-200 shadow-sm transition-all duration-300 hover:border-cyan-500/50 hover:shadow-lg dark:border-white/10"
    >
        <!-- Poster Container -->
        <div
            class="relative aspect-[2/3] w-full overflow-hidden bg-slate-200 dark:bg-slate-900"
        >
            <img
                :src="item.poster_path || '/placeholder.jpg'"
                :alt="item.title"
                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                loading="lazy"
            />

            <!-- Dark Overlay on Hover -->
            <div
                class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/30 to-transparent opacity-60 transition-opacity group-hover:opacity-80"
            ></div>

            <!-- Top Quality & Favorite Badges (z-30, strictly above hover overlay and blur) -->
            <div
                class="pointer-events-none absolute inset-x-2.5 top-2.5 z-30 flex items-center justify-between"
            >
                <span
                    class="cinema-badge pointer-events-auto border border-cyan-500/30 bg-black/70 text-[10px] text-cyan-400"
                >
                    {{ item.resolution || '1080p' }}
                </span>
                <button
                    @click.stop="emit('toggleFavorite', item)"
                    class="pointer-events-auto z-40 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full border border-white/10 bg-black/70 shadow-md backdrop-blur-xs transition-all hover:scale-125 hover:bg-black/90 active:scale-90"
                    :title="
                        item.is_favorite
                            ? 'Remove from favorites'
                            : 'Add to favorites'
                    "
                >
                    <Heart
                        class="h-3.5 w-3.5 transition-colors"
                        :class="
                            item.is_favorite
                                ? 'fill-red-500 text-red-500'
                                : 'text-white hover:text-red-400'
                        "
                    />
                </button>
            </div>

            <!-- Subtitle Badges on Poster (z-30, strictly above hover overlay) -->
            <div
                class="pointer-events-none absolute inset-x-2.5 bottom-2.5 z-30 flex items-center justify-between"
            >
                <div class="pointer-events-auto flex items-center gap-1">
                    <span
                        v-if="hasArSub"
                        class="rounded bg-emerald-500/90 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-sm"
                        title="Arabic Subtitles Available"
                    >
                        AR
                    </span>
                    <span
                        v-if="hasEnSub"
                        class="rounded bg-blue-500/90 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-sm"
                        title="English Subtitles Available"
                    >
                        EN
                    </span>
                </div>

                <!-- Rating -->
                <div
                    v-if="item.rating"
                    class="pointer-events-auto flex items-center gap-1 rounded bg-black/70 px-1.5 py-0.5 text-[10px] font-bold text-amber-300"
                >
                    <Star class="h-3 w-3 fill-current" />
                    <span>{{ item.rating }}</span>
                </div>
            </div>

            <!-- Quick Play Button Hover Overlay -->
            <div
                @click.stop="emit('play', item)"
                class="absolute inset-0 z-20 flex items-center justify-center bg-black/30 opacity-0 transition-opacity group-hover:opacity-100"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-cyan-500 text-slate-950 shadow-xl shadow-cyan-500/50 transition-transform group-hover:scale-110 active:scale-95"
                >
                    <Play class="ml-0.5 h-6 w-6 fill-current" />
                </div>
            </div>
        </div>

        <!-- Bottom Info -->
        <div
            class="flex flex-1 flex-col justify-between bg-white p-3 dark:bg-slate-950/40"
        >
            <h3
                class="truncate text-sm font-bold text-slate-900 transition-colors group-hover:text-cyan-600 dark:text-slate-100 dark:group-hover:text-cyan-400"
            >
                {{ isRTL && item.title_ar ? item.title_ar : item.title }}
            </h3>
            <div
                class="mt-1 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400"
            >
                <span>{{ item.release_year }}</span>
                <span v-if="item.runtime_minutes"
                    >{{ item.runtime_minutes }} {{ t('common.minutes') }}</span
                >
                <span v-else-if="item.seasons?.length"
                    >{{ item.seasons.length }} {{ t('common.seasons') }}</span
                >
            </div>
        </div>
    </div>
</template>
