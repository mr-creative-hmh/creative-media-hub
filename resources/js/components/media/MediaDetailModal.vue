<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { X, Play, Star, Film, Clock, Heart, Users, Subtitles, Download, Check, HardDrive, Cpu, Video, Sparkles, SlidersHorizontal } from 'lucide-vue-next';
import FixMatchModal from './FixMatchModal.vue';

const props = defineProps<{
    item: any;
}>();

const emit = defineEmits(['close', 'play', 'downloadSub', 'updated']);

const { t, isRTL } = useI18n();

const showFixMatch = ref(false);
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

const handleMetadataUpdated = (updatedItem: any) => {
    Object.assign(props.item, updatedItem);
    emit('updated', updatedItem);
};
</script>

<template>
    <div
        v-if="item"
        class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto font-sans"
        @click.self="emit('close')"
    >
        <div class="relative w-full max-w-4xl glass-panel rounded-3xl overflow-hidden border border-slate-200 dark:border-white/15 shadow-2xl my-8 bg-white dark:bg-[#121622]">
            <!-- Close Button -->
            <button
                @click="emit('close')"
                class="absolute top-4 right-4 z-20 w-9 h-9 rounded-full bg-black/60 hover:bg-black/90 text-white flex items-center justify-center transition-colors cursor-pointer"
            >
                <X class="w-5 h-5" />
            </button>

            <!-- Backdrop Banner -->
            <div class="relative aspect-video sm:aspect-[21/9] w-full overflow-hidden bg-slate-950">
                <img
                    :src="item.backdrop_path || item.poster_path || '/placeholder.jpg'"
                    :alt="item.title"
                    class="w-full h-full object-cover"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>
            </div>

            <!-- Content Body -->
            <div class="p-6 sm:p-8 space-y-6 relative -mt-16 sm:-mt-24 z-10">
                <!-- Header with Poster & Basic Info -->
                <div class="flex flex-col sm:flex-row gap-6 items-start">
                    <img
                        :src="item.poster_path || '/placeholder.jpg'"
                        :alt="item.title"
                        class="w-32 sm:w-44 rounded-2xl shadow-2xl border-2 border-white/20 shrink-0 bg-slate-200 dark:bg-slate-800"
                    />

                    <div class="flex-1 space-y-3">
                        <div class="flex items-center flex-wrap gap-2">
                            <span class="cinema-badge bg-cyan-500/20 text-cyan-700 dark:text-cyan-400 border border-cyan-500/30">
                                {{ item.resolution || '4K UHD' }}
                            </span>
                            <span class="cinema-badge bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30 flex items-center gap-1">
                                <Star class="w-3.5 h-3.5 fill-current" />
                                {{ item.rating }}
                            </span>
                            <span v-if="item.release_year" class="cinema-badge bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10">
                                {{ item.release_year }}
                            </span>
                            <span v-if="item.runtime_minutes" class="cinema-badge bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10">
                                {{ item.runtime_minutes }} {{ t('common.minutes') }}
                            </span>
                        </div>

                        <h2 class="text-2xl sm:text-4xl font-extrabold text-slate-900 dark:text-white">
                            {{ isRTL && item.title_ar ? item.title_ar : item.title }}
                        </h2>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-3 pt-2 flex-wrap">
                            <button
                                @click="emit('play', item)"
                                class="flex items-center gap-2 px-6 py-3 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-extrabold text-xs shadow-lg shadow-cyan-500/30 active:scale-95 transition-all cursor-pointer"
                            >
                                <Play class="w-4 h-4 fill-current" />
                                <span>{{ t('common.play_now') }}</span>
                            </button>

                            <button
                                @click="showFixMatch = true"
                                class="flex items-center gap-2 px-4 py-3 rounded-xl bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-700 dark:text-slate-200 font-bold text-xs border border-slate-200 dark:border-white/10 transition-all cursor-pointer"
                            >
                                <Sparkles class="w-4 h-4 text-cyan-500" />
                                <span>{{ isRTL ? 'تعديل البيانات والغلاف (Fix Match)' : 'Fix Match & Metadata' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Synopsis -->
                <div class="space-y-2">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        {{ t('media.overview') }}
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
                        {{ (isRTL && item.overview_ar) ? item.overview_ar : item.overview }}
                    </p>
                </div>

                <!-- Technical Specs Box -->
                <div class="p-4 rounded-2xl bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                    <div>
                        <span class="block text-[10px] text-slate-500 dark:text-slate-400 uppercase font-bold">{{ t('media.resolution') }}</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white">{{ item.resolution || '1080p' }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] text-slate-500 dark:text-slate-400 uppercase font-bold">{{ t('media.video_codec') }}</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white">{{ item.video_codec || 'HEVC' }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] text-slate-500 dark:text-slate-400 uppercase font-bold">{{ t('media.audio_codec') }}</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white">{{ item.audio_codec || 'AAC 5.1' }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] text-slate-500 dark:text-slate-400 uppercase font-bold">{{ isRTL ? 'المسار المحلي' : 'Local Path' }}</span>
                        <span class="font-mono text-[10px] text-slate-600 dark:text-slate-400 truncate block">{{ item.file_path || 'Indexed' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fix Match Modal -->
        <FixMatchModal
            :show="showFixMatch"
            :item="item"
            type="movie"
            @close="showFixMatch = false"
            @updated="handleMetadataUpdated"
        />
    </div>
</template>
