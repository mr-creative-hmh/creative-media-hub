<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Play,
    Info,
    Star,
    ChevronLeft,
    ChevronRight,
    Sparkles,
    Film,
    Video,
    Clock,
} from 'lucide-vue-next';

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
        class="group relative mb-8 overflow-hidden rounded-3xl border border-white/10 bg-slate-950 shadow-2xl select-none"
    >
        <!-- Ambient Backdrop Images with Cross-Fade Transition -->
        <div
            class="relative aspect-[21/9] min-h-[380px] w-full overflow-hidden bg-slate-950 sm:aspect-[24/9] sm:min-h-[440px]"
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
                class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/65 to-transparent"
            ></div>
            <div
                class="absolute inset-0 bg-gradient-to-r from-slate-950/95 via-slate-950/40 to-transparent"
                :class="
                    isRTL
                        ? 'bg-gradient-to-l from-slate-950/95 via-slate-950/40 to-transparent'
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
                    class="cinema-badge border border-indigo-500/50 bg-indigo-500/30 text-[11px] font-bold tracking-wider text-indigo-300 uppercase backdrop-blur-md"
                >
                    TV Series
                </span>
                <span
                    class="cinema-badge border border-cyan-500/40 bg-cyan-500/20 text-[11px] font-bold text-cyan-300 backdrop-blur-md"
                >
                    {{ currentSlide.resolution || '4K UHD' }}
                </span>
                <span
                    class="cinema-badge flex items-center gap-1 border border-amber-500/40 bg-amber-500/20 text-[11px] font-bold text-amber-300 backdrop-blur-md"
                >
                    <Star class="h-3 w-3 fill-current" />
                    {{ currentSlide.rating || '8.5' }}
                </span>
                <span
                    v-if="currentSlide.release_year"
                    class="cinema-badge border border-white/15 bg-black/60 text-[11px] text-slate-200"
                >
                    {{ currentSlide.release_year }}
                </span>
                <span
                    v-if="currentSlide.runtime_minutes"
                    class="cinema-badge flex items-center gap-1 border border-white/10 bg-black/60 text-[11px] text-slate-300"
                >
                    <Clock class="h-3 w-3 text-cyan-400" />
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
                class="mb-2.5 text-3xl leading-tight font-black tracking-tight text-white drop-shadow-lg sm:text-5xl"
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
                    @click.stop="handlePlay"
                    class="flex cursor-pointer items-center gap-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 px-6 py-3 text-sm font-black text-slate-950 shadow-xl shadow-cyan-500/30 transition-all hover:scale-105 hover:from-cyan-400 hover:to-blue-500 active:scale-95"
                >
                    <Play class="h-4 w-4 fill-current" />
                    <span>{{ t('common.play_now') }}</span>
                </button>
                <button
                    @click.stop="handleDetails"
                    class="flex cursor-pointer items-center gap-2 rounded-xl border border-white/20 bg-white/15 px-5 py-3 text-sm font-bold text-white backdrop-blur-md transition-all hover:scale-105 hover:bg-white/25 active:scale-95"
                >
                    <Info class="h-4 w-4 text-cyan-300" />
                    <span>{{ t('common.details') }}</span>
                </button>
            </div>
        </div>

        <!-- Top Right Slide Counter / Badges -->
        <div
            v-if="slides.length > 1"
            class="absolute top-5 right-5 z-20 flex items-center gap-2 sm:top-7 sm:right-7"
        >
            <div
                class="rounded-full border border-white/15 bg-black/60 px-3 py-1 font-mono text-[11px] font-bold text-slate-300 backdrop-blur-md"
            >
                <span class="font-extrabold text-cyan-400">{{
                    String(currentIndex + 1).padStart(2, '0')
                }}</span>
                <span class="mx-1 opacity-40">/</span>
                <span>{{ String(slides.length).padStart(2, '0') }}</span>
            </div>
        </div>

        <!-- Floating Left/Right Navigation Arrows -->
        <div
            v-if="slides.length > 1"
            dir="ltr"
            class="pointer-events-none absolute inset-x-3 inset-y-0 z-20 flex items-center justify-between"
        >
            <button
                @click.stop="prevSlide"
                class="pointer-events-auto flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-white/15 bg-black/60 text-white opacity-0 shadow-lg backdrop-blur-md transition-all group-hover:opacity-100 hover:scale-110 hover:bg-cyan-500 hover:text-slate-950"
                :title="t('common.previous')"
            >
                <ChevronLeft class="h-5 w-5" />
            </button>
            <button
                @click.stop="nextSlide"
                class="pointer-events-auto flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-white/15 bg-black/60 text-white opacity-0 shadow-lg backdrop-blur-md transition-all group-hover:opacity-100 hover:scale-110 hover:bg-cyan-500 hover:text-slate-950"
                :title="t('common.next')"
            >
                <ChevronRight class="h-5 w-5" />
            </button>
        </div>

        <!-- Bottom Animated Slide Indicators -->
        <div
            v-if="slides.length > 1"
            dir="ltr"
            class="absolute right-6 bottom-4 z-20 flex items-center gap-2 sm:right-10 sm:bottom-6"
        >
            <button
                v-for="(_, idx) in slides"
                :key="idx"
                @click.stop="goToSlide(idx)"
                class="h-1.5 cursor-pointer overflow-hidden rounded-full transition-all duration-500"
                :class="
                    idx === currentIndex
                        ? 'w-8 bg-cyan-400 shadow-md shadow-cyan-400/50'
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
