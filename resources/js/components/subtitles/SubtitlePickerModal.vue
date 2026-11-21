<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import {
    X,
    Search,
    Download,
    Check,
    Sparkles,
    AlertCircle,
    Loader2,
    Globe,
    Star,
    HardDrive,
    Filter,
    RefreshCw,
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

const targetLanguage = ref<'ar' | 'en'>(
    props.initialLanguage === 'en' ? 'en' : 'ar',
);
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
        const ep =
            props.media.season_info ||
            `S${props.media.season_number}E${props.media.episode_number}`;
        return `${show} - ${ep} (${props.media.title})`;
    }
    return `${props.media.title} ${props.media.year ? `(${props.media.year})` : ''}`;
});

const filteredResults = computed(() => {
    if (!searchResults.value || !Array.isArray(searchResults.value)) return [];

    return searchResults.value.filter((sub) => {
        if (!targetLanguage.value) return true;
        const subLang = (sub.language || '').toLowerCase();
        if (targetLanguage.value === 'ar') {
            return (
                subLang === 'ar' ||
                subLang === 'ara' ||
                subLang.includes('arab')
            );
        }
        return (
            subLang === 'en' || subLang === 'eng' || subLang.includes('engl')
        );
    });
});

const fetchSubtitles = async () => {
    if (!props.media) return;
    isLoading.value = true;
    hasSearched.value = true;
    searchResults.value = [];

    try {
        const queryParams = new URLSearchParams({
            query:
                searchQuery.value ||
                (props.media.type === 'episode'
                    ? props.media.series_title || props.media.title
                    : props.media.title),
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
            queryParams.append(
                'season_number',
                String(props.media.season_number),
            );
        }
        if (props.media.episode_number) {
            queryParams.append(
                'episode_number',
                String(props.media.episode_number),
            );
        }

        const res = await fetch(
            `/api/subtitles/search?${queryParams.toString()}`,
        );
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
                'No Results',
            );
        }
    } catch (err: any) {
        toast.error(
            err.message || 'Failed to search subtitles',
            'Search Error',
        );
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
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content || '',
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
            'Subtitle Ready',
        );

        emit('downloaded', data.subtitle);
        emit('close');
    } catch (err: any) {
        toast.error(
            err.message || 'Error downloading subtitle',
            'Download Failed',
        );
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

const isTranslating = ref(false);

const handleGenerateArabicFromEnglish = async () => {
    if (!props.media) return;
    isTranslating.value = true;
    try {
        const res = await fetch('/api/subtitles/generate-arabic', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content || '',
            },
            body: JSON.stringify({
                media_id: props.media.id,
                media_type: props.media.type || 'movie',
            }),
        });

        const data = await res.json();
        if (!res.ok || data.status === 'error') {
            throw new Error(
                data.message || 'Failed to generate Arabic translation',
            );
        }

        toast.success(
            isRTL.value
                ? 'تم توليد وترجمة ملف الترجمة العربية بنجاح وحفظه بدقة زمنية تامة!'
                : 'Arabic subtitle successfully translated from English!',
            'Translation Ready',
        );

        emit('downloaded', data.subtitle);
        emit('close');
    } catch (err: any) {
        toast.error(
            err.message || 'Error generating Arabic subtitle',
            'Translation Failed',
        );
    } finally {
        isTranslating.value = false;
    }
};

watch(
    () => props.isOpen,
    (newVal) => {
        if (newVal && props.media) {
            targetLanguage.value = props.initialLanguage === 'en' ? 'en' : 'ar';
            searchQuery.value =
                props.media.type === 'episode'
                    ? props.media.series_title || props.media.title
                    : props.media.title;
            fetchSubtitles();
        }
    },
    { immediate: true },
);
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[99990] flex items-center justify-center bg-black/80 p-4 backdrop-blur-md transition-opacity sm:p-6"
        :dir="isRTL ? 'rtl' : 'ltr'"
    >
        <!-- Modal Dialog Container -->
        <div
            class="animate-in fade-in zoom-in-95 flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-800/90 bg-[#0B0F19] shadow-2xl duration-200"
            @click.stop
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-slate-800/80 bg-slate-900/40 px-6 py-5"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-400"
                    >
                        <Sparkles class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <h2
                            class="truncate text-base font-bold text-slate-100 sm:text-lg"
                        >
                            {{ modalTitle }}
                        </h2>
                        <div
                            class="mt-0.5 flex items-center gap-2 text-xs text-slate-400"
                        >
                            <span>{{
                                media?.type === 'episode'
                                    ? isRTL
                                        ? 'حلقة'
                                        : 'Episode'
                                    : isRTL
                                      ? 'فيلم'
                                      : 'Movie'
                            }}</span>
                            <span
                                v-if="media?.imdb_id"
                                class="rounded bg-slate-800 px-1.5 py-0.5 font-mono text-[10px] text-cyan-300"
                            >
                                {{ media.imdb_id }}
                            </span>
                            <span v-if="enginesQueried" class="text-slate-500">
                                · {{ isRTL ? 'المصادر:' : 'Sources:' }}
                                {{ enginesQueried }}
                            </span>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-xl p-2 text-slate-400 transition hover:bg-white/10 hover:text-slate-100"
                    @click="emit('close')"
                    :aria-label="isRTL ? 'إغلاق' : 'Close'"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Controls: Language Switcher & Keyword Query Input -->
            <div
                class="flex flex-col items-stretch justify-between gap-4 border-b border-slate-800/60 bg-slate-950/40 px-6 py-4 sm:flex-row sm:items-center"
            >
                <!-- Language Selector -->
                <div
                    class="flex shrink-0 items-center rounded-xl border border-slate-800 bg-slate-900/80 p-1"
                >
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg px-4 py-1.5 text-xs font-semibold transition-all duration-200"
                        :class="
                            targetLanguage === 'ar'
                                ? 'bg-cyan-500 font-bold text-slate-950 shadow-md'
                                : 'text-slate-400 hover:text-slate-200'
                        "
                        @click="setLanguage('ar')"
                    >
                        <span>🇪🇬 / 🇸🇦 العربية</span>
                        <span class="text-[10px] opacity-80">(Arabic)</span>
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg px-4 py-1.5 text-xs font-semibold transition-all duration-200"
                        :class="
                            targetLanguage === 'en'
                                ? 'bg-cyan-500 font-bold text-slate-950 shadow-md'
                                : 'text-slate-400 hover:text-slate-200'
                        "
                        @click="setLanguage('en')"
                    >
                        <span>🇬🇧 English</span>
                        <span class="text-[10px] opacity-80">(الإنجليزية)</span>
                    </button>
                </div>

                <!-- Custom Search Bar -->
                <form
                    class="flex flex-1 items-center gap-2"
                    @submit.prevent="fetchSubtitles"
                >
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400 rtl:right-3 rtl:left-auto"
                        />
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="
                                isRTL
                                    ? 'العنوان أو كلمات مفتاحية...'
                                    : 'Title or keywords...'
                            "
                            class="w-full rounded-xl border border-slate-800 bg-slate-900/70 py-2 pr-4 pl-9 text-xs text-slate-200 transition placeholder:text-slate-500 focus:border-cyan-500 focus:outline-none rtl:pr-9 rtl:pl-4"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="isLoading"
                        class="flex shrink-0 items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-medium text-slate-200 transition hover:bg-slate-700 disabled:opacity-50"
                    >
                        <RefreshCw v-if="!isLoading" class="h-3.5 w-3.5" />
                        <Loader2
                            v-else
                            class="h-3.5 w-3.5 animate-spin text-cyan-400"
                        />
                        <span>{{ isRTL ? 'بحث' : 'Search' }}</span>
                    </button>

                    <button
                        v-if="targetLanguage === 'ar'"
                        type="button"
                        :disabled="isTranslating"
                        @click="handleGenerateArabicFromEnglish"
                        class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl border border-cyan-500/40 bg-gradient-to-r from-cyan-500/20 to-blue-500/20 px-3.5 py-2 text-xs font-bold text-cyan-300 transition hover:from-cyan-500/30 hover:to-blue-500/30 active:scale-95 disabled:opacity-50"
                        :title="
                            isRTL
                                ? 'توليد ترجمة عربية كاملة من ملف الترجمة الإنجليزية'
                                : 'Translate full Arabic SRT from English subtitle'
                        "
                    >
                        <Loader2
                            v-if="isTranslating"
                            class="h-3.5 w-3.5 animate-spin"
                        />
                        <Sparkles v-else class="h-3.5 w-3.5 text-cyan-400" />
                        <span>{{
                            isTranslating
                                ? isRTL
                                    ? 'جارٍ التوليد...'
                                    : 'Translating...'
                                : isRTL
                                  ? 'توليد من الإنجليزية'
                                  : 'Translate from EN'
                        }}</span>
                    </button>
                </form>
            </div>

            <!-- Content Body: Subtitle Result List -->
            <div class="min-h-[300px] flex-1 space-y-3 overflow-y-auto p-6">
                <!-- Loading State -->
                <div
                    v-if="isLoading"
                    class="flex flex-col items-center justify-center gap-3 py-16 text-slate-400"
                >
                    <Loader2 class="h-8 w-8 animate-spin text-cyan-400" />
                    <p class="text-sm font-medium">
                        {{
                            isRTL
                                ? 'جاري تجميع الترجمات من مزودات مجانية فائقة السرعة...'
                                : 'Aggregating subtitles from free high-speed providers...'
                        }}
                    </p>
                    <p class="text-xs text-slate-500">
                        {{
                            isRTL
                                ? 'استعلام من SubSense و YTS-Subs و OpenSubtitles و SubDL'
                                : 'Querying SubSense, YTS-Subs, OpenSubtitles, and SubDL'
                        }}
                    </p>
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="filteredResults.length === 0 && hasSearched"
                    class="flex flex-col items-center justify-center px-4 py-16 text-center"
                >
                    <div
                        class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-800 bg-slate-900 text-slate-500"
                    >
                        <AlertCircle class="h-6 w-6" />
                    </div>
                    <h3 class="mb-1 text-sm font-semibold text-slate-200">
                        {{
                            targetLanguage === 'ar'
                                ? 'لا توجد ترجمة متوفرة لهذا الاسم'
                                : 'No Subtitles Found'
                        }}
                    </h3>
                    <p class="mb-4 max-w-sm text-xs text-slate-400">
                        {{
                            targetLanguage === 'ar'
                                ? 'حاول اختصار اسم البحث أعلاه (مثلاً كتابة الكلمة الأولى فقط أو اسم السلسلة) ثم النقر على Search مجدداً.'
                                : 'Try adjusting or shortening your search query keywords above, or switch language.'
                        }}
                    </p>
                    <button
                        type="button"
                        class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-xs text-slate-200 transition hover:bg-slate-700"
                        @click="fetchSubtitles"
                    >
                        {{ isRTL ? 'إعادة المحاولة' : 'Retry Search' }}
                    </button>

                    <!-- Instant Arabic Translation Fallback Box -->
                    <div
                        v-if="targetLanguage === 'ar'"
                        class="mx-auto mt-6 flex max-w-md flex-col items-center gap-2 rounded-xl border border-cyan-500/30 bg-cyan-500/10 p-4"
                    >
                        <div
                            class="flex items-center gap-2 text-xs font-bold text-cyan-400"
                        >
                            <Sparkles class="h-4 w-4 animate-pulse" />
                            <span>{{
                                isRTL
                                    ? 'توليد ذكي فوري من ملف الترجمة الإنجليزية'
                                    : 'Instant Translation from English'
                            }}</span>
                        </div>
                        <p class="text-center text-[11px] text-slate-400">
                            {{
                                isRTL
                                    ? 'يقوم النظام بترجمة وتوليد ملف ترجمة عربي سينمائي كامل وفوري مع الحفاظ التام على التوقيت بالمللي ثانية.'
                                    : 'Generate a full Modern Standard Arabic SRT directly translated from the English subtitle.'
                            }}
                        </p>
                        <button
                            type="button"
                            :disabled="isTranslating"
                            @click="handleGenerateArabicFromEnglish"
                            class="mt-1 flex cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2 text-xs font-bold text-slate-950 shadow-lg shadow-cyan-500/20 transition-all hover:from-cyan-400 hover:to-blue-500 active:scale-95 disabled:opacity-50"
                        >
                            <Loader2
                                v-if="isTranslating"
                                class="h-4 w-4 animate-spin"
                            />
                            <Sparkles v-else class="h-4 w-4" />
                            <span>{{
                                isTranslating
                                    ? isRTL
                                        ? 'جارٍ الترجمة والتوليد...'
                                        : 'Translating...'
                                    : isRTL
                                      ? 'توليد ترجمة عربية من الإنجليزية الآن'
                                      : 'Generate Arabic from English Now'
                            }}</span>
                        </button>
                    </div>
                </div>

                <!-- Result Cards List -->
                <div
                    v-for="sub in filteredResults"
                    :key="sub.subtitle_id"
                    class="group flex flex-col justify-between gap-4 rounded-xl border border-slate-800/80 bg-slate-900/40 p-4 transition-all hover:border-cyan-500/40 hover:bg-slate-900/80 sm:flex-row sm:items-center"
                >
                    <div class="min-w-0 flex-1 space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Provider Badge -->
                            <span
                                class="rounded-md border px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase"
                                :class="{
                                    'border-cyan-500/30 bg-cyan-500/10 text-cyan-400':
                                        sub.provider.includes('SubSense') ||
                                        sub.provider.includes('OpenSubtitles'),
                                    'border-emerald-500/30 bg-emerald-500/10 text-emerald-400':
                                        sub.provider.includes('YTS'),
                                    'border-indigo-500/30 bg-indigo-500/10 text-indigo-400':
                                        sub.provider.includes('SubDL'),
                                    'border-slate-700 bg-slate-800 text-slate-300':
                                        !sub.provider.includes('SubSense') &&
                                        !sub.provider.includes('YTS') &&
                                        !sub.provider.includes('SubDL'),
                                }"
                            >
                                {{ sub.provider }}
                            </span>

                            <!-- Format Badge -->
                            <span
                                class="rounded-md border border-slate-700 bg-slate-800/90 px-2 py-0.5 font-mono text-[10px] font-bold text-slate-300 uppercase"
                            >
                                {{ sub.format || 'SRT' }}
                            </span>

                            <!-- Language Badge -->
                            <span
                                class="rounded-md bg-slate-800/60 px-2 py-0.5 text-[10px] font-semibold text-slate-400"
                            >
                                {{
                                    sub.language === 'ar' ||
                                    sub.language === 'ara'
                                        ? 'العربية (Arabic)'
                                        : 'English'
                                }}
                            </span>

                            <!-- Rating/Downloads if available -->
                            <span
                                v-if="sub.rating && sub.rating > 0"
                                class="flex items-center gap-1 text-[10px] text-amber-400"
                            >
                                <Star
                                    class="h-3 w-3 fill-amber-400 text-amber-400"
                                />
                                <span>{{ sub.rating.toFixed(1) }}</span>
                            </span>
                            <span
                                v-if="sub.downloads && sub.downloads > 0"
                                class="text-[10px] text-slate-500"
                            >
                                · {{ sub.downloads.toLocaleString() }}
                                {{ isRTL ? 'تحميل' : 'downloads' }}
                            </span>
                        </div>

                        <!-- Subtitle Release File Name -->
                        <p
                            class="line-clamp-2 font-mono text-xs font-medium break-all text-slate-200 transition group-hover:text-cyan-300"
                        >
                            {{ sub.release || sub.file_name }}
                        </p>
                    </div>

                    <!-- Action Button: Download & Apply -->
                    <div class="flex shrink-0 items-center">
                        <button
                            type="button"
                            :disabled="isDownloadingId !== null"
                            class="flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold shadow-lg transition-all sm:w-auto"
                            :class="
                                isDownloadingId === sub.subtitle_id
                                    ? 'cursor-wait bg-cyan-600 text-white'
                                    : 'bg-cyan-500 text-slate-950 hover:bg-cyan-400 hover:shadow-cyan-500/20 active:scale-95'
                            "
                            @click="handleDownload(sub)"
                        >
                            <Loader2
                                v-if="isDownloadingId === sub.subtitle_id"
                                class="h-3.5 w-3.5 animate-spin"
                            />
                            <Download v-else class="h-3.5 w-3.5" />
                            <span>{{
                                isDownloadingId === sub.subtitle_id
                                    ? isRTL
                                        ? 'جارٍ التحميل...'
                                        : 'Downloading...'
                                    : isRTL
                                      ? 'تحميل وتطبيق'
                                      : 'Download & Apply'
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="flex items-center justify-between border-t border-slate-800/80 bg-slate-950/60 px-6 py-4 text-xs text-slate-400"
            >
                <div class="flex items-center gap-2">
                    <span
                        class="inline-block h-2 w-2 rounded-full bg-emerald-400"
                    ></span>
                    <span>{{
                        isRTL
                            ? 'تجميع مباشر من مزودات مجانية. تحويل تلقائي لنص UTF-8 النقي.'
                            : 'Free high-speed aggregator online. Subtitles are auto-converted to clean UTF-8.'
                    }}</span>
                </div>

                <button
                    type="button"
                    class="rounded-xl bg-slate-800 px-4 py-1.5 text-slate-300 transition hover:bg-slate-700"
                    @click="emit('close')"
                >
                    {{ isRTL ? 'إغلاق' : 'Close' }}
                </button>
            </div>
        </div>
    </div>
</template>
