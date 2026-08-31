<script setup lang="ts">
import { useI18n } from '@/i18n/useI18n';
import { Play, Info, Star, Sparkles, Film, Video } from 'lucide-vue-next';

const props = defineProps<{
    item: any;
}>();

const emit = defineEmits(['play', 'details']);

const { t, isRTL } = useI18n();
</script>

<template>
    <div v-if="item" class="relative rounded-3xl overflow-hidden mb-8 border border-white/10 shadow-2xl group">
        <!-- Ambient Backdrop Image -->
        <div class="relative aspect-[21/9] sm:aspect-[24/9] w-full min-h-[360px] bg-slate-950 overflow-hidden">
            <img
                :src="item.backdrop_path || item.poster_path"
                :alt="item.title"
                class="w-full h-full object-cover object-center group-hover:scale-102 transition-transform duration-700 opacity-75"
            />
            <!-- Multi-layer Cinema Gradients -->
            <div class="absolute inset-0 bg-gradient-to-t from-[#07090E] via-[#07090E]/60 to-transparent"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-[#07090E]/90 via-transparent to-[#07090E]/30" :class="isRTL ? 'bg-gradient-to-l' : ''"></div>
        </div>

        <!-- Spotlight Content Overlay -->
        <div class="absolute bottom-0 inset-x-0 p-6 sm:p-10 flex flex-col justify-end max-w-3xl">
            <!-- Badges & Quality -->
            <div class="flex items-center flex-wrap gap-2.5 mb-3">
                <span class="cinema-badge bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                    {{ item.resolution || '4K UHD HDR' }}
                </span>
                <span class="cinema-badge bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center gap-1">
                    <Star class="w-3 h-3 fill-current" />
                    {{ item.rating || '8.8' }}
                </span>
                <span v-if="item.release_year" class="cinema-badge bg-white/10 text-slate-300 border border-white/15">
                    {{ item.release_year }}
                </span>
                <span v-for="g in item.genres?.slice(0, 3)" :key="g.id" class="cinema-badge bg-white/5 text-slate-300 border border-white/10">
                    {{ isRTL && g.name_ar ? g.name_ar : g.name_en }}
                </span>
            </div>

            <!-- Title -->
            <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight drop-shadow-md mb-2 leading-tight">
                {{ isRTL && item.title_ar ? item.title_ar : item.title }}
            </h1>

            <!-- Tagline / Synopsis -->
            <p class="text-sm sm:text-base text-slate-300 line-clamp-2 sm:line-clamp-3 mb-6 font-normal drop-shadow leading-relaxed max-w-2xl">
                {{ isRTL && item.overview_ar ? item.overview_ar : item.overview }}
            </p>

            <!-- CTA Action Buttons -->
            <div class="flex items-center gap-3.5">
                <button
                    @click="emit('play', item)"
                    class="flex items-center gap-2.5 px-6 py-3 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-extrabold text-sm shadow-xl shadow-cyan-500/30 hover:scale-105 active:scale-95 transition-all"
                >
                    <Play class="w-4 h-4 fill-current" />
                    <span>{{ t('common.play_now') }}</span>
                </button>
                <button
                    @click="emit('details', item)"
                    class="flex items-center gap-2 px-5 py-3 rounded-xl bg-white/10 hover:bg-white/15 border border-white/15 text-white font-bold text-sm backdrop-blur-md hover:scale-105 active:scale-95 transition-all"
                >
                    <Info class="w-4 h-4 text-cyan-400" />
                    <span>{{ t('common.details') }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
