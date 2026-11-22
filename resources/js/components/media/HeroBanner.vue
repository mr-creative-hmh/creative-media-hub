<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Play,
    Info,
    Star,
    ChevronLeft,
    ChevronRight,
    Clock,
} from '@lucide/vue';

const props = defineProps<{
    items?: any[];
    item?: any;
    featuredItems?: any[];
}>();

const emit = defineEmits(['play', 'details', 'info']);

const { t, isRTL } = useI18n();

const slides = computed<any[]>(() => {
    if (props.items && Array.isArray(props.items) && props.items.length > 0) {
        return props.items;
    }
    if (
        props.featuredItems &&
        Array.isArray(props.featuredItems) &&
        props.featuredItems.length > 0
    ) {
        return props.featuredItems;
    }
    if (props.item) {
        return [props.item];
    }
    return [];
});

const currentIndex = ref(0);
const isPaused = ref(false);
let autoplayTimer: any = null;

// Touch swipe handling
let touchStartX = 0;
let touchEndX = 0;

const currentSlide = computed(() => {
    if (slides.value.length === 0) return null;
    return slides.value[currentIndex.value] || slides.value[0];
});

const nextSlide = () => {
    if (slides.value.length <= 1) return;
    currentIndex.value = (currentIndex.value + 1) % slides.value.length;
};

const prevSlide = () => {
    if (slides.value.length <= 1) return;
    currentIndex.value =
        (currentIndex.value - 1 + slides.value.length) % slides.value.length;
};

const goToSlide = (idx: number) => {
    currentIndex.value = idx;
};

const startAutoplay = () => {
    stopAutoplay();
    if (slides.value.length > 1) {
        autoplayTimer = setInterval(() => {
            if (!isPaused.value) {
                nextSlide();
            }
        }, 6000);
    }
};

const stopAutoplay = () => {
    if (autoplayTimer) {
        clearInterval(autoplayTimer);
        autoplayTimer = null;
    }
};

const handleTouchStart = (e: TouchEvent) => {
    touchStartX = e.changedTouches[0].screenX;
};

const handleTouchEnd = (e: TouchEvent) => {
    touchEndX = e.changedTouches[0].screenX;
    if (touchStartX - touchEndX > 50) {
        nextSlide();
    } else if (touchEndX - touchStartX > 50) {
        prevSlide();
    }
};

const handlePlay = () => {
    if (!currentSlide.value) return;
    stopAutoplay();
    emit('play', currentSlide.value);
};

const handleDetails = () => {
    if (!currentSlide.value) return;
    emit('details', currentSlide.value);
    emit('info', currentSlide.value);
};

watch(
    () => slides.value.length,
    () => {
        currentIndex.value = 0;
        startAutoplay();
    },
);

onMounted(() => {
    startAutoplay();
});

onUnmounted(() => {
    stopAutoplay();
});
</script>

<template>
    <div
        v-if="currentSlide"
        @mouseenter="isPaused = true"
        @mouseleave="isPaused = false"
        @touchstart="handleTouchStart"
        @touchend="handleTouchEnd"
        class="border-border/80 bg-background group relative mb-8 overflow-hidden rounded-3xl border shadow-2xl select-none"
    >
        <!-- Ambient Backdrop Images with Cross-Fade Transition -->
        <div
            class="bg-background relative aspect-[21/9] min-h-[380px] w-full overflow-hidden sm:aspect-[24/9] sm:min-h-[440px]"
        >
            <transition name="hero-fade">
                <img
                    :key="`${currentSlide.type || (currentSlide.seasons ? 'series' : 'movie')}-${currentSlide.id}-${currentIndex}`"
                    :src="
                        currentSlide.backdrop_path || currentSlide.poster_path
                    "
                    :alt="currentSlide.title"
                    class="h-full w-full object-cover object-center opacity-85 transition-transform duration-1000 group-hover:scale-103"
                />
            </transition>

            <!-- Multi-layer Cinematic Gradients -->
            <div
                class="from-background via-background/65 absolute inset-0 bg-gradient-to-t to-transparent"
            ></div>
            <div
                class="from-background/95 via-background/40 absolute inset-0 bg-gradient-to-r to-transparent"
                :class="
                    isRTL
                        ? 'from-background/95 via-background/40 bg-gradient-to-l to-transparent'
                        : ''
                "
            ></div>
        </div>

        <!-- Spotlight Content Overlay -->
        <div
            class="absolute inset-x-0 bottom-0 z-10 flex max-w-3xl flex-col justify-end p-6 sm:p-10"
        >
            <!-- Badges & Quality -->
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <span
                    v-if="
                        currentSlide.type === 'series' || currentSlide.seasons
                    "
                    class="cinema-badge border-primary/50 bg-primary/25 text-primary border text-[11px] font-bold tracking-wider uppercase backdrop-blur-md"
                >
                    TV Series
                </span>
                <span
                    class="cinema-badge border-primary/40 bg-primary/20 text-primary border font-mono text-[11px] font-bold tabular-nums backdrop-blur-md"
                >
                    {{ currentSlide.resolution || '4K UHD' }}
                </span>
                <span
                    class="cinema-badge flex items-center gap-1 border border-amber-500/40 bg-amber-500/20 text-[11px] font-bold text-amber-300 tabular-nums backdrop-blur-md"
                >
                    <Star class="size-3 fill-current" />
                    {{ currentSlide.rating || '8.5' }}
                </span>
                <span
                    v-if="currentSlide.release_year"
                    class="cinema-badge border border-white/15 bg-black/60 text-[11px] text-slate-200 tabular-nums"
                >
                    {{ currentSlide.release_year }}
                </span>
                <span
                    v-if="currentSlide.runtime_minutes"
                    class="cinema-badge flex items-center gap-1 border border-white/10 bg-black/60 text-[11px] text-slate-300 tabular-nums"
                >
                    <Clock class="text-primary size-3" />
                    {{ currentSlide.runtime_minutes }} {{ t('common.minutes') }}
                </span>
                <span
                    v-for="g in currentSlide.genres?.slice(0, 3)"
                    :key="g.id"
                    class="cinema-badge border border-white/10 bg-black/50 text-[11px] text-slate-200"
                >
                    {{ isRTL && g.name_ar ? g.name_ar : g.name_en }}
                </span>
            </div>

            <!-- Title -->
            <h1
                class="text-foreground mb-2.5 text-3xl leading-tight font-black tracking-tight drop-shadow-lg sm:text-5xl"
            >
                {{
                    isRTL && currentSlide.title_ar
                        ? currentSlide.title_ar
                        : currentSlide.title
                }}
            </h1>

            <!-- Tagline / Synopsis -->
            <p
                class="text-shadow mb-6 line-clamp-2 max-w-2xl text-xs leading-relaxed font-normal text-slate-200 drop-shadow sm:line-clamp-3 sm:text-sm"
            >
                {{
                    isRTL && currentSlide.overview_ar
                        ? currentSlide.overview_ar
                        : currentSlide.overview
                }}
            </p>

            <!-- CTA Action Buttons -->
            <div class="flex items-center gap-3.5">
                <button
                    type="button"
                    @click.stop="handlePlay"
                    class="bg-primary text-primary-foreground shadow-primary/25 flex cursor-pointer items-center gap-2.5 rounded-xl px-6 py-3 text-sm font-black shadow-lg transition-all hover:brightness-105 active:scale-[0.98]"
                >
                    <Play class="size-4 fill-current" />
                    <span>{{ t('common.play_now') }}</span>
                </button>
                <button
                    type="button"
                    @click.stop="handleDetails"
                    class="cf-glass hover:border-primary/40 flex cursor-pointer items-center gap-2 rounded-xl border border-white/15 px-5 py-3 text-sm font-bold text-white backdrop-blur-md transition-all hover:bg-white/15 active:scale-[0.98]"
                >
                    <Info class="text-primary size-4" />
                    <span>{{ t('common.details') }}</span>
                </button>
            </div>
        </div>

        <!-- Top End Slide Counter / Badges -->
        <div
            v-if="slides.length > 1"
            class="absolute end-5 top-5 z-20 flex items-center gap-2 sm:end-7 sm:top-7"
        >
            <div
                class="rounded-full border border-white/15 bg-black/60 px-3 py-1 font-mono text-[11px] font-bold text-slate-300 tabular-nums backdrop-blur-md"
            >
                <span class="text-primary font-extrabold">{{
                    String(currentIndex + 1).padStart(2, '0')
                }}</span>
                <span class="mx-1 opacity-40">/</span>
                <span>{{ String(slides.length).padStart(2, '0') }}</span>
            </div>
        </div>

        <!-- Floating Navigation Arrows -->
        <div
            v-if="slides.length > 1"
            class="pointer-events-none absolute inset-x-3 inset-y-0 z-20 flex items-center justify-between"
        >
            <button
                type="button"
                @click.stop="prevSlide"
                class="hover:bg-primary hover:text-primary-foreground pointer-events-auto flex size-10 cursor-pointer items-center justify-center rounded-full border border-white/15 bg-black/60 text-white opacity-0 shadow-lg backdrop-blur-md transition-all group-hover:opacity-100 hover:scale-110"
                :title="t('common.previous')"
            >
                <component
                    :is="isRTL ? ChevronRight : ChevronLeft"
                    class="size-5"
                />
            </button>
            <button
                type="button"
                @click.stop="nextSlide"
                class="hover:bg-primary hover:text-primary-foreground pointer-events-auto flex size-10 cursor-pointer items-center justify-center rounded-full border border-white/15 bg-black/60 text-white opacity-0 shadow-lg backdrop-blur-md transition-all group-hover:opacity-100 hover:scale-110"
                :title="t('common.next')"
            >
                <component
                    :is="isRTL ? ChevronLeft : ChevronRight"
                    class="size-5"
                />
            </button>
        </div>

        <!-- Bottom Animated Slide Indicators -->
        <div
            v-if="slides.length > 1"
            class="absolute end-6 bottom-4 z-20 flex items-center gap-2 sm:end-10 sm:bottom-6"
        >
            <button
                v-for="(_, idx) in slides"
                :key="idx"
                type="button"
                @click.stop="goToSlide(idx)"
                class="h-1.5 cursor-pointer overflow-hidden rounded-full transition-all duration-500"
                :class="
                    idx === currentIndex
                        ? 'bg-primary shadow-primary/50 w-8 shadow-md'
                        : 'w-2.5 bg-white/20 hover:bg-white/40'
                "
                :aria-label="`Slide ${idx + 1}`"
            ></button>
        </div>
    </div>
</template>

<style scoped>
.hero-fade-enter-active,
.hero-fade-leave-active {
    transition: opacity 0.35s ease-in-out;
}
.hero-fade-enter-from,
.hero-fade-leave-to {
    opacity: 0;
}
</style>
