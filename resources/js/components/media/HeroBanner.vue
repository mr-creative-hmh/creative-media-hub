<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { Play, Info, Star, ChevronLeft, ChevronRight, Sparkles, Film, Video, Clock } from 'lucide-vue-next';

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
    if (props.featuredItems && Array.isArray(props.featuredItems) && props.featuredItems.length > 0) {
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
    currentIndex.value = (currentIndex.value - 1 + slides.value.length) % slides.value.length;
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

watch(() => slides.value.length, () => {
    currentIndex.value = 0;
    startAutoplay();
});

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
        class="relative rounded-3xl overflow-hidden mb-8 border border-white/10 shadow-2xl group select-none bg-slate-950"
    >
        <!-- Ambient Backdrop Images with Cross-Fade Transition -->
        <div class="relative aspect-[21/9] sm:aspect-[24/9] w-full min-h-[380px] sm:min-h-[440px] bg-slate-950 overflow-hidden">
            <transition name="hero-fade">
                <img
                    :key="`${currentSlide.type || (currentSlide.seasons ? 'series' : 'movie')}-${currentSlide.id}-${currentIndex}`"
                    :src="currentSlide.backdrop_path || currentSlide.poster_path"
                    :alt="currentSlide.title"
                    class="w-full h-full object-cover object-center group-hover:scale-103 transition-transform duration-1000 opacity-85"
                />
            </transition>

            <!-- Multi-layer Cinematic Gradients -->
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/65 to-transparent"></div>
            <div
                class="absolute inset-0 bg-gradient-to-r from-slate-950/95 via-slate-950/40 to-transparent"
                :class="isRTL ? 'bg-gradient-to-l from-slate-950/95 via-slate-950/40 to-transparent' : ''"
            ></div>
        </div>

        <!-- Spotlight Content Overlay -->
        <div class="absolute bottom-0 inset-x-0 p-6 sm:p-10 flex flex-col justify-end max-w-3xl z-10">
            <!-- Badges & Quality -->
            <div class="flex items-center flex-wrap gap-2 mb-3">
                <span v-if="currentSlide.type === 'series' || currentSlide.seasons" class="cinema-badge bg-indigo-500/30 text-indigo-300 border border-indigo-500/50 font-bold text-[11px] backdrop-blur-md uppercase tracking-wider">
                    TV Series
                </span>
                <span class="cinema-badge bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 font-bold text-[11px] backdrop-blur-md">
                    {{ currentSlide.resolution || '4K UHD' }}
                </span>
                <span class="cinema-badge bg-amber-500/20 text-amber-300 border border-amber-500/40 font-bold text-[11px] flex items-center gap-1 backdrop-blur-md">
                    <Star class="w-3 h-3 fill-current" />
                    {{ currentSlide.rating || '8.5' }}
                </span>
                <span v-if="currentSlide.release_year" class="cinema-badge bg-black/60 text-slate-200 border border-white/15 text-[11px]">
                    {{ currentSlide.release_year }}
                </span>
                <span v-if="currentSlide.runtime_minutes" class="cinema-badge bg-black/60 text-slate-300 border border-white/10 text-[11px] flex items-center gap-1">
                    <Clock class="w-3 h-3 text-cyan-400" />
                    {{ currentSlide.runtime_minutes }} {{ t('common.minutes') }}
                </span>
                <span
                    v-for="g in currentSlide.genres?.slice(0, 3)"
                    :key="g.id"
                    class="cinema-badge bg-black/50 text-slate-200 border border-white/10 text-[11px]"
                >
                    {{ isRTL && g.name_ar ? g.name_ar : g.name_en }}
                </span>
            </div>

            <!-- Title -->
            <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight drop-shadow-lg mb-2.5 leading-tight">
                {{ isRTL && currentSlide.title_ar ? currentSlide.title_ar : currentSlide.title }}
            </h1>

            <!-- Tagline / Synopsis -->
            <p class="text-xs sm:text-sm text-slate-200 line-clamp-2 sm:line-clamp-3 mb-6 font-normal drop-shadow leading-relaxed max-w-2xl text-shadow">
                {{ isRTL && currentSlide.overview_ar ? currentSlide.overview_ar : currentSlide.overview }}
            </p>

            <!-- CTA Action Buttons -->
            <div class="flex items-center gap-3.5">
                <button
                    @click.stop="handlePlay"
                    class="flex items-center gap-2.5 px-6 py-3 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-sm shadow-xl shadow-cyan-500/30 hover:scale-105 active:scale-95 transition-all cursor-pointer"
                >
                    <Play class="w-4 h-4 fill-current" />
                    <span>{{ t('common.play_now') }}</span>
                </button>
                <button
                    @click.stop="handleDetails"
                    class="flex items-center gap-2 px-5 py-3 rounded-xl bg-white/15 hover:bg-white/25 border border-white/20 text-white font-bold text-sm backdrop-blur-md hover:scale-105 active:scale-95 transition-all cursor-pointer"
                >
                    <Info class="w-4 h-4 text-cyan-300" />
                    <span>{{ t('common.details') }}</span>
                </button>
            </div>
        </div>

        <!-- Top Right Slide Counter / Badges -->
        <div v-if="slides.length > 1" class="absolute top-5 right-5 sm:top-7 sm:right-7 z-20 flex items-center gap-2">
            <div class="px-3 py-1 rounded-full bg-black/60 border border-white/15 backdrop-blur-md text-[11px] font-mono font-bold text-slate-300">
                <span class="text-cyan-400 font-extrabold">{{ String(currentIndex + 1).padStart(2, '0') }}</span>
                <span class="opacity-40 mx-1">/</span>
                <span>{{ String(slides.length).padStart(2, '0') }}</span>
            </div>
        </div>

        <!-- Floating Left/Right Navigation Arrows -->
        <div v-if="slides.length > 1" dir="ltr" class="absolute inset-y-0 inset-x-3 flex items-center justify-between pointer-events-none z-20">
            <button
                @click.stop="prevSlide"
                class="w-10 h-10 rounded-full bg-black/60 hover:bg-cyan-500 hover:text-slate-950 border border-white/15 text-white flex items-center justify-center backdrop-blur-md opacity-0 group-hover:opacity-100 transition-all pointer-events-auto cursor-pointer shadow-lg hover:scale-110"
                :title="t('common.previous')"
            >
                <ChevronLeft class="w-5 h-5" />
            </button>
            <button
                @click.stop="nextSlide"
                class="w-10 h-10 rounded-full bg-black/60 hover:bg-cyan-500 hover:text-slate-950 border border-white/15 text-white flex items-center justify-center backdrop-blur-md opacity-0 group-hover:opacity-100 transition-all pointer-events-auto cursor-pointer shadow-lg hover:scale-110"
                :title="t('common.next')"
            >
                <ChevronRight class="w-5 h-5" />
            </button>
        </div>

        <!-- Bottom Animated Slide Indicators -->
        <div v-if="slides.length > 1" dir="ltr" class="absolute bottom-4 right-6 sm:bottom-6 sm:right-10 z-20 flex items-center gap-2">
            <button
                v-for="(_, idx) in slides"
                :key="idx"
                @click.stop="goToSlide(idx)"
                class="h-1.5 rounded-full transition-all duration-500 cursor-pointer overflow-hidden"
                :class="idx === currentIndex ? 'w-8 bg-cyan-400 shadow-md shadow-cyan-400/50' : 'w-2.5 bg-white/20 hover:bg-white/40'"
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
