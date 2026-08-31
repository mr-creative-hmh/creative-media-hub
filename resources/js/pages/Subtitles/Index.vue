<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    Subtitles, Download, Check, AlertCircle, Sparkles,
    Search, Cpu, CheckCircle2, Globe, ArrowDownToLine, RefreshCw,
    Film, Tv, HardDrive, FileText, CheckCheck
} from 'lucide-vue-next';

const props = defineProps<{
    missingSubtitles: any[];
}>();

const { t, isRTL } = useI18n();

const downloadingId = ref<string | null>(null);
const searchQuery = ref('Inception');
const searchLang = ref('ar');
const isSearching = ref(false);
const searchResults = ref<any[] | null>(null);
const toastMessage = ref('');

const performSearch = async () => {
    if (!searchQuery.value.trim()) return;
    isSearching.value = true;
    try {
        const res = await fetch('/api/subtitles/verify-engine', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                query: searchQuery.value.trim(),
                language: searchLang.value,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            searchResults.value = data.results || [];
        }
    } finally {
        isSearching.value = false;
    }
};

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
            toastMessage.value = isRTL.value
                ? `تم تحميل وربط ترجمة (${lang === 'ar' ? 'العربية' : 'English'}) بملف ${item.title} بنجاح!`
                : `Successfully downloaded and linked ${lang.toUpperCase()} subtitle for ${item.title}!`;
        }
    } finally {
        downloadingId.value = null;
    }
};
</script>

<template>
    <Head :title="t('subtitles_view.title')" />

    <AppLayout v-slot="{ play }">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
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

        <!-- Toast Notice -->
        <div v-if="toastMessage" class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 shrink-0" />
                <span>{{ toastMessage }}</span>
            </div>
            <button @click="toastMessage = ''" class="cursor-pointer text-emerald-400 hover:text-emerald-300">
                ✕
            </button>
        </div>

        <!-- 1. Live Free Subtitles Multi-Engine Search Studio -->
        <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 mb-8 space-y-6 shadow-sm">
            <div class="flex items-center justify-between flex-wrap gap-4 pb-4 border-b border-white/10">
                <div class="flex items-center gap-2.5">
                    <Cpu class="w-5 h-5 text-cyan-400" />
                    <div>
                        <h3 class="font-bold text-base text-white">
                            {{ isRTL ? 'محرك البحث المباشر عن الترجمات (SubDL + OpenSubtitles)' : 'Live Free Subtitle Search Engine' }}
                        </h3>
                        <p class="text-xs text-slate-400">
                            {{ isRTL ? 'البحث في سحابة SubDL و OpenSubtitles لجلب وتحميل ملفات الترجمة فورياً' : 'Query live SubDL & OpenSubtitles scrapers to verify free subtitles retrieval.' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap text-[11px] font-bold">
                    <span class="cinema-badge bg-emerald-500/20 text-emerald-300 border-emerald-500/30">
                        SubDL Free Cloud: Online
                    </span>
                    <span class="cinema-badge bg-cyan-500/20 text-cyan-300 border-cyan-500/30">
                        OpenSubtitles REST: Ready
                    </span>
                </div>
            </div>

            <!-- Search Inputs Bar -->
            <form @submit.prevent="performSearch" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="isRTL ? 'اكتب اسم الفيلم أو المسلسل...' : 'Search movie or episode title...'"
                        class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                    />
                    <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                </div>

                <select
                    v-model="searchLang"
                    class="w-full sm:w-44 px-3.5 py-2.5 rounded-xl bg-[#0E121E] border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-500 font-bold"
                >
                    <option value="ar">العربية (Arabic)</option>
                    <option value="en">English</option>
                    <option value="fr">Français (French)</option>
                    <option value="es">Español (Spanish)</option>
                    <option value="de">Deutsch (German)</option>
                </select>

                <button
                    type="submit"
                    :disabled="isSearching"
                    class="px-6 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs flex items-center justify-center gap-2 active:scale-95 transition-all cursor-pointer shadow-lg shadow-cyan-500/20 shrink-0"
                >
                    <RefreshCw v-if="isSearching" class="w-4 h-4 animate-spin" />
                    <Search v-else class="w-4 h-4" />
                    <span>{{ isRTL ? 'بحث عن الترجمات' : 'Search Subtitles' }}</span>
                </button>
            </form>

            <!-- Search Result Cards -->
            <div v-if="searchResults && searchResults.length > 0" class="space-y-3 pt-2">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                    {{ isRTL ? 'نتائج البحث المكتشفة:' : 'Discovered Subtitle Matches:' }}
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div
                        v-for="(sub, idx) in searchResults"
                        :key="idx"
                        class="p-4 rounded-2xl bg-white/5 border border-white/10 hover:border-cyan-500/40 transition-all flex flex-col justify-between space-y-3 group"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <span class="cinema-badge bg-cyan-500/20 text-cyan-300 border-cyan-500/30 text-[10px]">
                                        {{ sub.provider || 'SubDL Free' }}
                                    </span>
                                    <span class="cinema-badge bg-emerald-500/20 text-emerald-300 border-emerald-500/30 text-[10px] uppercase font-mono font-bold">
                                        {{ sub.language }}
                                    </span>
                                    <span v-if="sub.downloads" class="cinema-badge bg-white/10 text-slate-300 border-white/10 text-[10px]">
                                        {{ sub.downloads }} dl
                                    </span>
                                </div>
                                <h5 class="font-bold text-xs text-white truncate max-w-sm" :title="sub.release || sub.file_name">
                                    {{ sub.release || sub.file_name }}
                                </h5>
                                <p class="text-[11px] font-mono text-slate-400 truncate mt-0.5">
                                    {{ sub.file_name }}
                                </p>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-white/5 flex items-center justify-between">
                            <span class="text-[11px] text-slate-500 font-mono">SRT Format (UTF-8)</span>
                            <a
                                :href="sub.download_url"
                                target="_blank"
                                class="px-3.5 py-1.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500 text-cyan-300 hover:text-slate-950 border border-cyan-500/30 text-xs font-bold transition-all flex items-center gap-1.5"
                            >
                                <ArrowDownToLine class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'تحميل مباشر' : 'Download .SRT' }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Library Missing Subtitles Batch Table -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-base text-white flex items-center gap-2">
                    <FileText class="w-4 h-4 text-cyan-400" />
                    <span>{{ isRTL ? 'عناصر المكتبة التي تنقصها ترجمات' : 'Library Media Missing Subtitles' }}</span>
                </h3>
            </div>

            <div v-if="missingSubtitles && missingSubtitles.length > 0" class="space-y-3">
                <div
                    v-for="item in missingSubtitles"
                    :key="`${item.type}-${item.id}`"
                    class="glass-panel p-4 rounded-2xl border border-white/10 hover:border-cyan-500/40 transition-all flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
                >
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="w-10 h-10 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center shrink-0">
                            <Film v-if="item.type === 'movie'" class="w-5 h-5 text-cyan-400" />
                            <Tv v-else class="w-5 h-5 text-indigo-400" />
                        </div>

                        <div class="min-w-0 space-y-0.5">
                            <div class="flex items-center gap-2">
                                <span class="cinema-badge bg-white/10 text-slate-300 border-white/10 text-[9px]">
                                    {{ item.type === 'movie' ? (isRTL ? 'فيلم' : 'MOVIE') : (isRTL ? 'حلقة مسلسل' : 'EPISODE') }}
                                </span>
                                <h4 class="font-bold text-xs text-white truncate max-w-sm">{{ item.title }}</h4>
                            </div>
                            <p class="text-[11px] font-mono text-slate-400 truncate max-w-lg">{{ item.file_path }}</p>
                        </div>
                    </div>

                    <!-- Download Action Buttons -->
                    <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                        <button
                            v-if="item.missing_ar"
                            @click="downloadSub(item, 'ar')"
                            :disabled="downloadingId === `${item.id}-ar`"
                            class="px-3.5 py-1.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                        >
                            <RefreshCw v-if="downloadingId === `${item.id}-ar`" class="w-3.5 h-3.5 animate-spin" />
                            <Download v-else class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'تحميل ترجمة عربي' : 'Fetch Arabic' }}</span>
                        </button>
                        <span v-else class="cinema-badge bg-emerald-500/20 text-emerald-300 border-emerald-500/30 text-[10px] flex items-center gap-1">
                            <CheckCheck class="w-3 h-3" />
                            <span>AR OK</span>
                        </span>

                        <button
                            v-if="item.missing_en"
                            @click="downloadSub(item, 'en')"
                            :disabled="downloadingId === `${item.id}-en`"
                            class="px-3.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                        >
                            <RefreshCw v-if="downloadingId === `${item.id}-en`" class="w-3.5 h-3.5 animate-spin" />
                            <Download v-else class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'تحميل ترجمة إنجليزي' : 'Fetch English' }}</span>
                        </button>
                        <span v-else class="cinema-badge bg-emerald-500/20 text-emerald-300 border-emerald-500/30 text-[10px] flex items-center gap-1">
                            <CheckCheck class="w-3 h-3" />
                            <span>EN OK</span>
                        </span>
                    </div>
                </div>
            </div>

            <div v-else class="glass-panel rounded-3xl p-12 text-center text-slate-400 space-y-2">
                <CheckCircle2 class="w-8 h-8 text-emerald-400 mx-auto" />
                <p class="font-bold text-white">{{ isRTL ? 'كافة الأفلام والحلقات مربوطة بترجماتها بنجاح!' : 'All media items have linked subtitles!' }}</p>
                <p class="text-xs text-slate-500">{{ isRTL ? 'لا توجد ملفات ناقصة للترجمة العربية أو الإنجليزية.' : 'No missing Arabic or English subtitles found.' }}</p>
            </div>
        </div>
    </AppLayout>
</template>
