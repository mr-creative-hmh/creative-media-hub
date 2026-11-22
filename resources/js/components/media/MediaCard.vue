<script setup lang="ts">
import { useI18n } from '@/i18n/useI18n';
import { Play, Star, Heart } from '@lucide/vue';

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
        class="cf-glass border-border/80 hover:border-primary/50 group relative flex cursor-pointer flex-col overflow-hidden rounded-2xl border shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-xl"
    >
        <!-- Poster Container -->
        <div class="bg-muted/40 relative aspect-[2/3] w-full overflow-hidden">
            <img
                :src="item.poster_path || '/placeholder.jpg'"
                :alt="item.title"
                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                loading="lazy"
            />

            <!-- Dark Overlay on Hover -->
            <div
                class="from-background via-background/30 absolute inset-0 bg-gradient-to-t to-transparent opacity-60 transition-opacity group-hover:opacity-80"
            ></div>

            <!-- Top Quality & Favorite Badges (z-30, strictly above hover overlay and blur) -->
            <div
                class="pointer-events-none absolute inset-x-2.5 top-2.5 z-30 flex items-center justify-between"
            >
                <span
                    class="cinema-badge border-primary/35 text-primary pointer-events-auto border bg-black/75 font-mono text-[10px] tabular-nums"
                >
                    {{ item.resolution || '1080p' }}
                </span>
                <button
                    type="button"
                    @click.stop="emit('toggleFavorite', item)"
                    class="pointer-events-auto z-40 flex size-7 cursor-pointer items-center justify-center rounded-full border border-white/10 bg-black/75 shadow-md backdrop-blur-xs transition-all hover:scale-115 hover:bg-black/90 active:scale-90"
                    :title="
                        item.is_favorite
                            ? 'Remove from favorites'
                            : 'Add to favorites'
                    "
                >
                    <Heart
                        class="size-3.5 transition-colors"
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
                    class="pointer-events-auto flex items-center gap-1 rounded-md border border-white/10 bg-black/75 px-1.5 py-0.5 text-[10px] font-bold text-amber-300 tabular-nums backdrop-blur-xs"
                >
                    <Star class="size-3 fill-current" />
                    <span>{{ item.rating }}</span>
                </div>
            </div>

            <!-- Quick Play Button Hover Overlay -->
            <div
                @click.stop="emit('play', item)"
                class="absolute inset-0 z-20 flex items-center justify-center bg-black/35 opacity-0 transition-opacity group-hover:opacity-100"
            >
                <div
                    class="bg-primary text-primary-foreground shadow-primary/40 flex size-12 items-center justify-center rounded-full shadow-xl transition-transform group-hover:scale-110 active:scale-95"
                >
                    <Play class="ms-0.5 size-6 fill-current" />
                </div>
            </div>
        </div>

        <!-- Bottom Info -->
        <div
            class="bg-card/85 flex flex-1 flex-col justify-between p-3 backdrop-blur-sm"
        >
            <h3
                class="text-foreground group-hover:text-primary truncate text-sm font-bold transition-colors"
            >
                {{ isRTL && item.title_ar ? item.title_ar : item.title }}
            </h3>
            <div
                class="text-muted-foreground mt-1 flex items-center justify-between text-[11px] tabular-nums"
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
