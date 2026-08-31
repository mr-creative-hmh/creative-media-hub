<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { X, Play, Star, Film, Clock, Heart, Users, Subtitles, Download, Check } from 'lucide-vue-next';

const props = defineProps<{
    item: any;
}>();

const emit = defineEmits(['close', 'play', 'downloadSub']);

const { t, isRTL } = useI18n();

const isDownloadingAr = ref(false);
const isDownloadingEn = ref(false);

const handleDownloadSub = async (lang: string) => {
    if (lang === 'ar') isDownloadingAr.value = true;
    if (lang === 'en') isDownloadingEn.value = true;

    try {
        const res = await fetch('/api/subtitles/download', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                media_id: props.item.id,
                media_type: props.item.type || 'movie',
                language: lang,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            if (!props.item.subtitles) props.item.subtitles = [];
            props.item.subtitles.push(data.subtitle);
        }
    } finally {
        if (lang === 'ar') isDownloadingAr.value = false;
        if (lang === 'en') isDownloadingEn.value = false;
    }
};
</script>

<template>
    <div
        v-if="item"
        class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto font-sans"
        @click.self="emit('close')"
    >
        <div class="relative w-full max-w-4xl glass-panel rounded-3xl overflow-hidden border border-white/15 shadow-2xl my-8">
            <!-- Close Button -->
            <button
                @click="emit('close')"
                class="absolute top-4 right-4 z-20 w-9 h-9 rounded-full bg-black/60 hover:bg-black/90 text-white flex items-center justify-center transition-colors"
            >
                <X class="w-5 h-5" />
            </button>

            <!-- Backdrop Banner -->
            <div class="relative aspect-video sm:aspect-[21/9] w-full overflow-hidden bg-slate-950">
                <img
                    :src="item.backdrop_path || item.poster_path"
                    :alt="item.title"
                    class="w-full h-full object-cover"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-[#121622] via-[#121622]/60 to-transparent"></div>
            </div>

            <!-- Content Body -->
            <div class="p-6 sm:p-8 space-y-6 relative -mt-16 sm:-mt-24 z-10">
                <!-- Header with Poster & Basic Info -->
                <div class="flex flex-col sm:flex-row gap-6 items-start">
                    <img
                        :src="item.poster_path"
                        :alt="item.title"
                        class="w-32 sm:w-44 rounded-2xl shadow-2xl border-2 border-white/20 shrink-0"
                    />

                    <div class="flex-1 space-y-3">
                        <div class="flex items-center flex-wrap gap-2">
                            <span class="cinema-badge bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                                {{ item.resolution || '4K UHD' }}
                            </span>
                            <span class="cinema-badge bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center gap-1">
                                <Star class="w-3.5 h-3.5 fill-current" />
                                {{ item.rating }}
                            </span>
                            <span v-if="item.release_year" class="cinema-badge bg-white/10 text-slate-300 border border-white/10">
                                {{ item.release_year }}
                            </span>
                            <span v-if="item.runtime_minutes" class="cinema-badge bg-white/10 text-slate-300 border border-white/10">
                                {{ item.runtime_minutes }} {{ t('common.minutes') }}
                            </span>
                        </div>

                        <h2 class="text-2xl sm:text-4xl font-extrabold text-white">
                            {{ isRTL && item.title_ar ? item.title_ar : item.title }}
                        </h2>

                        <div class="flex items-center flex-wrap gap-1.5">
                            <span
                                v-for="g in item.genres"
                                :key="g.id"
                                class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/5 border border-white/10 text-slate-300"
                            >
                                {{ isRTL && g.name_ar ? g.name_ar : g.name_en }}
                            </span>
                        </div>

                        <!-- Main Play CTA -->
                        <div class="pt-2 flex items-center gap-3">
                            <button
                                @click="emit('play', item)"
                                class="flex items-center gap-2.5 px-6 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-extrabold text-sm shadow-xl shadow-cyan-500/30 hover:scale-105 active:scale-95 transition-all"
                            >
                                <Play class="w-4 h-4 fill-current" />
                                <span>{{ t('common.play_now') }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Synopsis -->
                <div class="space-y-2">
                    <h4 class="font-bold text-xs uppercase tracking-wider text-cyan-400">
                        {{ t('common.synopsis') }}
                    </h4>
                    <p class="text-sm text-slate-300 leading-relaxed">
                        {{ isRTL && item.overview_ar ? item.overview_ar : item.overview }}
                    </p>
                </div>

                <!-- Subtitles Section with 1-Click Free Download -->
                <div class="rounded-2xl bg-white/[0.03] border border-white/10 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Subtitles class="w-4 h-4 text-cyan-400" />
                            <h4 class="font-bold text-xs uppercase tracking-wider text-slate-200">
                                {{ t('common.subtitles_available') }}
                            </h4>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            @click="handleDownloadSub('ar')"
                            :disabled="isDownloadingAr"
                            class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold border transition-all"
                            :class="item.subtitles?.some((s: any) => s.language === 'ar')
                                ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40'
                                : 'bg-white/5 hover:bg-white/10 text-cyan-300 border-cyan-500/30'"
                        >
                            <Check v-if="item.subtitles?.some((s: any) => s.language === 'ar')" class="w-3.5 h-3.5" />
                            <Download v-else class="w-3.5 h-3.5" />
                            <span>{{ item.subtitles?.some((s: any) => s.language === 'ar') ? 'العربية (محمّلة)' : t('common.download_arabic_sub') }}</span>
                        </button>

                        <button
                            @click="handleDownloadSub('en')"
                            :disabled="isDownloadingEn"
                            class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold border transition-all"
                            :class="item.subtitles?.some((s: any) => s.language === 'en')
                                ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40'
                                : 'bg-white/5 hover:bg-white/10 text-cyan-300 border-cyan-500/30'"
                        >
                            <Check v-if="item.subtitles?.some((s: any) => s.language === 'en')" class="w-3.5 h-3.5" />
                            <Download v-else class="w-3.5 h-3.5" />
                            <span>{{ item.subtitles?.some((s: any) => s.language === 'en') ? 'English (Loaded)' : t('common.download_english_sub') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Cast & Crew Gallery -->
                <div v-if="item.people?.length" class="space-y-3">
                    <div class="flex items-center gap-2">
                        <Users class="w-4 h-4 text-cyan-400" />
                        <h4 class="font-bold text-xs uppercase tracking-wider text-slate-200">
                            {{ t('common.cast') }}
                        </h4>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
                        <div
                            v-for="p in item.people"
                            :key="p.id"
                            class="flex flex-col items-center text-center p-2.5 rounded-xl bg-white/[0.02] border border-white/5"
                        >
                            <img
                                :src="p.profile_path || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200'"
                                :alt="p.name"
                                class="w-14 h-14 rounded-full object-cover mb-2 border border-white/15"
                            />
                            <h5 class="font-bold text-xs text-slate-200 truncate w-full">
                                {{ isRTL && p.name_ar ? p.name_ar : p.name }}
                            </h5>
                            <p class="text-[10px] text-slate-400 truncate w-full">
                                {{ p.pivot?.character_name || p.pivot?.role }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
