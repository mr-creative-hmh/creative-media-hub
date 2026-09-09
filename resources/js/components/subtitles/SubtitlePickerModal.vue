<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import {
    X, Search, Download, Check, Sparkles, AlertCircle,
    Loader2, Globe, Star, HardDrive, Filter, RefreshCw
} from 'lucide-vue-next';

interface SubtitleResult {
    provider: string;
    subtitle_id: string | number;
    language: string;
    language_raw?: string;
    release: string;
    file_name: string;
    download_url?: string;
    source?: string;
    format: string;
    downloads?: number;
    rating?: number;
}

interface MediaContext {
    id: number;
    type?: 'movie' | 'episode';
    title: string;
    title_ar?: string;
    year?: number;
    imdb_id?: string;
    season_number?: number;
    episode_number?: number;
    series_title?: string;
    season_info?: string;
    file_path?: string;
}

const props = defineProps<{
    isOpen: boolean;
    media: MediaContext | null;
    initialLanguage?: 'ar' | 'en' | string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'downloaded', subtitle: any): void;
}>();

const { t, isRTL } = useI18n();
const toast = useToast();

const targetLanguage = ref<'ar' | 'en'>((props.initialLanguage === 'en' ? 'en' : 'ar'));
const searchQuery = ref('');
const isLoading = ref(false);
const isDownloadingId = ref<string | number | null>(null);
const searchResults = ref<SubtitleResult[]>([]);
const enginesQueried = ref<string>('');
const hasSearched = ref(false);

const modalTitle = computed(() => {
    if (!props.media) return 'Search & Download Subtitles';
    if (props.media.type === 'episode') {
        const show = props.media.series_title || 'Series';
        const ep = props.media.season_info || `S${props.media.season_number}E${props.media.episode_number}`;
        return `${show} - ${ep} (${props.media.title})`;
    }
    return `${props.media.title} ${props.media.year ? `(${props.media.year})` : ''}`;
});

const filteredResults = computed(() => {
    if (!searchResults.value || !Array.isArray(searchResults.value)) return [];
    
    return searchResults.value.filter(sub => {
        if (!targetLanguage.value) return true;
        const subLang = (sub.language || '').toLowerCase();
        if (targetLanguage.value === 'ar') {
            return subLang === 'ar' || subLang === 'ara' || subLang.includes('arab');
        }
        return subLang === 'en' || subLang === 'eng' || subLang.includes('engl');
    });
});

const fetchSubtitles = async () => {
    if (!props.media) return;
    isLoading.value = true;
    hasSearched.value = true;
    searchResults.value = [];

    try {
        const queryParams = new URLSearchParams({
            query: searchQuery.value || (props.media.type === 'episode' ? (props.media.series_title || props.media.title) : props.media.title),
            language: targetLanguage.value,
            media_id: String(props.media.id),
            media_type: props.media.type || 'movie',
        });

        if (props.media.imdb_id) {
            queryParams.append('imdb_id', props.media.imdb_id);
        }
        if (props.media.year) {
            queryParams.append('year', String(props.media.year));
        }
        if (props.media.season_number) {
            queryParams.append('season_number', String(props.media.season_number));
        }
        if (props.media.episode_number) {
            queryParams.append('episode_number', String(props.media.episode_number));
        }

        const res = await fetch(`/api/subtitles/search?${queryParams.toString()}`);
        if (!res.ok) {
            throw new Error(`Search failed with status ${res.status}`);
        }

        const data = await res.json();
        searchResults.value = data.results || [];
        enginesQueried.value = data.meta?.engine_used || 'multi-source';
        
        if (searchResults.value.length === 0) {
            toast.info(
                targetLanguage.value === 'ar' 
                    ? 'لم يتم العثور على ترجمة عربية مطابقة، جرب تعديل اسم البحث أو محرك آخر' 
                    : 'No subtitles found for this query. Try adjusting title keywords.',
                'No Results'
            );
        }
    } catch (err: any) {
        toast.error(err.message || 'Failed to search subtitles', 'Search Error');
    } finally {
        isLoading.value = false;
    }
};

const handleDownload = async (sub: SubtitleResult) => {
    if (!props.media) return;
    isDownloadingId.value = sub.subtitle_id;

    try {
        const res = await fetch('/api/subtitles/download', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                media_id: props.media.id,
                media_type: props.media.type || 'movie',
                language: targetLanguage.value,
                download_url: sub.download_url,
                release: sub.release,
                file_name: sub.file_name,
                source: sub.source,
                file_id: sub.subtitle_id,
            }),
        });

        const data = await res.json();
        if (!res.ok || data.status === 'error') {
            throw new Error(data.message || 'Failed to download subtitle');
        }

        toast.success(
            targetLanguage.value === 'ar' 
                ? 'تم تحميل وتثبيت الترجمة العربية بنجاح بنقاء UTF-8!' 
                : 'Subtitle downloaded and attached successfully!',
            'Subtitle Ready'
        );

        emit('downloaded', data.subtitle);
        emit('close');
    } catch (err: any) {
        toast.error(err.message || 'Error downloading subtitle', 'Download Failed');
    } finally {
        isDownloadingId.value = null;
    }
};

const setLanguage = (lang: 'ar' | 'en') => {
    if (targetLanguage.value !== lang) {
        targetLanguage.value = lang;
        fetchSubtitles();
    }
};

watch(
    () => props.isOpen,
    (newVal) => {
        if (newVal && props.media) {
            targetLanguage.value = (props.initialLanguage === 'en' ? 'en' : 'ar');
            searchQuery.value = props.media.type === 'episode' 
                ? (props.media.series_title || props.media.title) 
                : props.media.title;
            fetchSubtitles();
        }
    },
    { immediate: true }
);
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[99990] flex items-center justify-center p-4 sm:p-6 bg-black/80 backdrop-blur-md transition-opacity"
        :dir="isRTL ? 'rtl' : 'ltr'"
    >
        <!-- Modal Dialog Container -->
        <div
            class="bg-[#0B0F19] border border-slate-800/90 rounded-2xl w-full max-w-4xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200"
            @click.stop
        >
            <!-- Header -->
            <div class="px-6 py-5 border-b border-slate-800/80 flex items-center justify-between bg-slate-900/40">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400 shrink-0">
                        <Sparkles class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base sm:text-lg font-bold text-slate-100 truncate">
                            {{ modalTitle }}
                        </h2>
                        <div class="flex items-center gap-2 mt-0.5 text-xs text-slate-400">
                            <span>{{ media?.type === 'episode' ? 'Episode' : 'Movie' }}</span>
                            <span v-if="media?.imdb_id" class="px-1.5 py-0.5 rounded bg-slate-800 font-mono text-[10px] text-cyan-300">
                                {{ media.imdb_id }}
                            </span>
                            <span v-if="enginesQueried" class="text-slate-500">
                                · Sources: {{ enginesQueried }}
                            </span>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    class="p-2 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/10 transition"
                    @click="emit('close')"
                    aria-label="Close"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Controls: Language Switcher & Keyword Query Input -->
            <div class="px-6 py-4 border-b border-slate-800/60 bg-slate-950/40 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                <!-- Language Selector -->
                <div class="flex items-center p-1 bg-slate-900/80 rounded-xl border border-slate-800 shrink-0">
                    <button
                        type="button"
                        class="px-4 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 flex items-center gap-2"
                        :class="targetLanguage === 'ar' ? 'bg-cyan-500 text-slate-950 shadow-md font-bold' : 'text-slate-400 hover:text-slate-200'"
                        @click="setLanguage('ar')"
                    >
                        <span>🇪🇬 / 🇸🇦 العربية</span>
                        <span class="text-[10px] opacity-80">(Arabic)</span>
                    </button>
                    <button
                        type="button"
                        class="px-4 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 flex items-center gap-2"
                        :class="targetLanguage === 'en' ? 'bg-cyan-500 text-slate-950 shadow-md font-bold' : 'text-slate-400 hover:text-slate-200'"
                        @click="setLanguage('en')"
                    >
                        <span>🇬🇧 English</span>
                        <span class="text-[10px] opacity-80">(الإنجليزية)</span>
                    </button>
                </div>

                <!-- Custom Search Bar -->
                <form class="flex-1 flex items-center gap-2" @submit.prevent="fetchSubtitles">
                    <div class="relative flex-1">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 rtl:left-auto rtl:right-3" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Title or keywords..."
                            class="w-full bg-slate-900/70 border border-slate-800 text-slate-200 text-xs rounded-xl pl-9 pr-4 rtl:pl-4 rtl:pr-9 py-2 focus:outline-none focus:border-cyan-500 transition placeholder:text-slate-500"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="isLoading"
                        class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium rounded-xl border border-slate-700 transition flex items-center gap-1.5 shrink-0 disabled:opacity-50"
                    >
                        <RefreshCw v-if="!isLoading" class="w-3.5 h-3.5" />
                        <Loader2 v-else class="w-3.5 h-3.5 animate-spin text-cyan-400" />
                        <span>Search</span>
                    </button>
                </form>
            </div>

            <!-- Content Body: Subtitle Result List -->
            <div class="flex-1 overflow-y-auto p-6 space-y-3 min-h-[300px]">
                <!-- Loading State -->
                <div v-if="isLoading" class="flex flex-col items-center justify-center py-16 gap-3 text-slate-400">
                    <Loader2 class="w-8 h-8 animate-spin text-cyan-400" />
                    <p class="text-sm font-medium">Aggregating subtitles from free high-speed providers...</p>
                    <p class="text-xs text-slate-500">Querying SubSense, YTS-Subs, OpenSubtitles, and SubDL</p>
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="filteredResults.length === 0 && hasSearched"
                    class="flex flex-col items-center justify-center py-16 text-center px-4"
                >
                    <div class="w-12 h-12 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-500 mb-3">
                        <AlertCircle class="w-6 h-6" />
                    </div>
                    <h3 class="text-sm font-semibold text-slate-200 mb-1">
                        {{ targetLanguage === 'ar' ? 'لا توجد ترجمة متوفرة لهذا الاسم' : 'No Subtitles Found' }}
                    </h3>
                    <p class="text-xs text-slate-400 max-w-sm mb-4">
                        {{ targetLanguage === 'ar'
                            ? 'حاول اختصار اسم البحث أعلاه (مثلاً كتابة الكلمة الأولى فقط أو اسم السلسلة) ثم النقر على Search مجدداً.'
                            : 'Try adjusting or shortening your search query keywords above, or switch language.' }}
                    </p>
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs text-slate-200 border border-slate-700 transition"
                        @click="fetchSubtitles"
                    >
                        Retry Search
                    </button>
                </div>

                <!-- Result Cards List -->
                <div
                    v-for="sub in filteredResults"
                    :key="sub.subtitle_id"
                    class="p-4 rounded-xl bg-slate-900/40 hover:bg-slate-900/80 border border-slate-800/80 hover:border-cyan-500/40 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4 group"
                >
                    <div class="min-w-0 flex-1 space-y-1.5">
                        <div class="flex items-center flex-wrap gap-2">
                            <!-- Provider Badge -->
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wide border uppercase"
                                :class="{
                                    'bg-cyan-500/10 text-cyan-400 border-cyan-500/30': sub.provider.includes('SubSense') || sub.provider.includes('OpenSubtitles'),
                                    'bg-emerald-500/10 text-emerald-400 border-emerald-500/30': sub.provider.includes('YTS'),
                                    'bg-indigo-500/10 text-indigo-400 border-indigo-500/30': sub.provider.includes('SubDL'),
                                    'bg-slate-800 text-slate-300 border-slate-700': !sub.provider.includes('SubSense') && !sub.provider.includes('YTS') && !sub.provider.includes('SubDL')
                                }"
                            >
                                {{ sub.provider }}
                            </span>

                            <!-- Format Badge -->
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-slate-800/90 text-slate-300 border border-slate-700 uppercase">
                                {{ sub.format || 'SRT' }}
                            </span>

                            <!-- Language Badge -->
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-800/60 text-slate-400">
                                {{ (sub.language === 'ar' || sub.language === 'ara') ? 'العربية (Arabic)' : 'English' }}
                            </span>

                            <!-- Rating/Downloads if available -->
                            <span v-if="sub.rating && sub.rating > 0" class="flex items-center gap-1 text-[10px] text-amber-400">
                                <Star class="w-3 h-3 fill-amber-400 text-amber-400" />
                                <span>{{ sub.rating.toFixed(1) }}</span>
                            </span>
                            <span v-if="sub.downloads && sub.downloads > 0" class="text-[10px] text-slate-500">
                                · {{ sub.downloads.toLocaleString() }} downloads
                            </span>
                        </div>

                        <!-- Subtitle Release File Name -->
                        <p class="text-xs font-medium text-slate-200 group-hover:text-cyan-300 transition line-clamp-2 break-all font-mono">
                            {{ sub.release || sub.file_name }}
                        </p>
                    </div>

                    <!-- Action Button: Download & Apply -->
                    <div class="shrink-0 flex items-center">
                        <button
                            type="button"
                            :disabled="isDownloadingId !== null"
                            class="w-full sm:w-auto px-4 py-2 rounded-xl text-xs font-semibold flex items-center justify-center gap-2 transition-all shadow-lg"
                            :class="isDownloadingId === sub.subtitle_id
                                ? 'bg-cyan-600 text-white cursor-wait'
                                : 'bg-cyan-500 hover:bg-cyan-400 text-slate-950 hover:shadow-cyan-500/20 active:scale-95'"
                            @click="handleDownload(sub)"
                        >
                            <Loader2 v-if="isDownloadingId === sub.subtitle_id" class="w-3.5 h-3.5 animate-spin" />
                            <Download v-else class="w-3.5 h-3.5" />
                            <span>{{ isDownloadingId === sub.subtitle_id ? 'Downloading...' : 'Download & Apply' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-slate-800/80 bg-slate-950/60 flex items-center justify-between text-xs text-slate-400">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>Free high-speed aggregator online. Subtitles are auto-converted to clean UTF-8.</span>
                </div>

                <button
                    type="button"
                    class="px-4 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition"
                    @click="emit('close')"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</template>
