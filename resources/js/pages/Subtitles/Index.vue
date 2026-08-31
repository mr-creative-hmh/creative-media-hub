<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import { Subtitles, Download, Check, AlertCircle, Sparkles } from 'lucide-vue-next';

const props = defineProps<{
    missingSubtitles: any[];
}>();

const { t, isRTL } = useI18n();

const downloadingId = ref<string | null>(null);

const downloadSub = async (item: any, lang: string) => {
    downloadingId.value = `${item.id}-${lang}`;
    try {
        const res = await fetch('/api/subtitles/download', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                media_id: item.id,
                media_type: item.type || 'movie',
                language: lang,
            }),
        });

        if (res.ok) {
            if (lang === 'ar') item.missing_ar = false;
            if (lang === 'en') item.missing_en = false;
        }
    } finally {
        downloadingId.value = null;
    }
};
</script>

<template>
    <Head :title="t('subtitles_view.title')" />

    <AppLayout v-slot="{ play }">
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                    <Subtitles class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                        {{ t('subtitles_view.title') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                        {{ t('subtitles_view.subtitle') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Missing Subtitles List -->
        <div class="glass-panel rounded-3xl p-6 border border-white/10 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2">
                    <AlertCircle class="w-5 h-5 text-amber-400" />
                    <h3 class="font-bold text-base text-white">
                        {{ isRTL ? 'قائمة الوسائط التي تنقصها ترجمة' : 'Media Missing Subtitles' }}
                    </h3>
                    <span class="cinema-badge bg-amber-500/20 text-amber-300 border border-amber-500/30">
                        {{ missingSubtitles.length }}
                    </span>
                </div>
            </div>

            <div v-if="missingSubtitles.length > 0" class="divide-y divide-white/5">
                <div
                    v-for="item in missingSubtitles"
                    :key="`${item.type}-${item.id}`"
                    class="py-4 flex items-center justify-between flex-wrap gap-4 hover:bg-white/[0.02] px-2 rounded-xl transition-colors"
                >
                    <div>
                        <h4 class="font-bold text-sm text-white">
                            {{ isRTL && item.title_ar ? item.title_ar : (item.title || item.series_title) }}
                            <span v-if="item.season_number" class="text-cyan-400 text-xs ml-1">
                                (S{{ item.season_number }}E{{ item.episode_number }})
                            </span>
                        </h4>
                        <p class="text-xs text-slate-400 font-mono mt-0.5 truncate max-w-lg">
                            {{ item.file_path }}
                        </p>
                    </div>

                    <!-- 1-Click Download Buttons -->
                    <div class="flex items-center gap-2.5">
                        <button
                            v-if="item.missing_ar"
                            @click="downloadSub(item, 'ar')"
                            :disabled="downloadingId === `${item.id}-ar`"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 text-xs font-bold transition-colors"
                        >
                            <Download class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'تحميل العربية' : 'Fetch Arabic' }}</span>
                        </button>
                        <span v-else class="flex items-center gap-1 text-[11px] text-emerald-400 font-bold px-2 py-1 bg-emerald-500/10 rounded">
                            <Check class="w-3 h-3" /> AR
                        </span>

                        <button
                            v-if="item.missing_en"
                            @click="downloadSub(item, 'en')"
                            :disabled="downloadingId === `${item.id}-en`"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-500/20 hover:bg-blue-500/30 text-blue-300 border border-blue-500/40 text-xs font-bold transition-colors"
                        >
                            <Download class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'تحميل الإنجليزية' : 'Fetch English' }}</span>
                        </button>
                        <span v-else class="flex items-center gap-1 text-[11px] text-blue-400 font-bold px-2 py-1 bg-blue-500/10 rounded">
                            <Check class="w-3 h-3" /> EN
                        </span>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-12">
                <Check class="w-12 h-12 text-emerald-400 mx-auto mb-2" />
                <h4 class="font-bold text-white text-base">
                    {{ isRTL ? 'كافة الوسائط مترجمة بالكامل!' : 'All Media Subtitles are Synchronized!' }}
                </h4>
            </div>
        </div>
    </AppLayout>
</template>
