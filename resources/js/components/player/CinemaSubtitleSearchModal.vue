<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { X, Search, Download, Loader2, Sparkles, Star } from '@lucide/vue';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';

const props = defineProps<{
    isOpen: boolean;
    item: any;
    isEpisode: boolean;
    isTranslating?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'downloaded', subtitle?: any): void;
    (e: 'translate'): void;
}>();

const { isRTL, t } = useI18n();
const toast = useToast();

const searchQuery = ref('');
const isSearching = ref(false);
const searchResults = ref<any[]>([]);
const downloadingId = ref<string | number | null>(null);
const selectedLangFilter = ref<'ar,en' | 'ar' | 'en'>('ar,en');
const selectedSourceFilter = ref<
    'all' | 'subsense' | 'opensubtitles' | 'subdl' | 'yts'
>('all');

const normalizeLangCode = (lang: unknown): string => {
    const l = String(lang || '')
        .toLowerCase()
        .trim();
    if (l === 'ara' || l.includes('arab') || l === 'ar') return 'ar';
    if (l === 'eng' || l.includes('engl') || l === 'en') return 'en';
    return l || 'en';
};

const getItemId = (res: any): string =>
    String(res?.subtitle_id ?? res?.id ?? '');

const languageFilteredResults = computed(() => {
    const list = Array.isArray(searchResults.value)
        ? [...searchResults.value]
        : [];
    const filtered = list.filter((res) => {
        const l = normalizeLangCode(res.language);
        if (selectedLangFilter.value === 'ar') return l === 'ar';
        if (selectedLangFilter.value === 'en') return l === 'en';
        return true;
    });

    // Always enforce Arabic-first sorting when showing All (AR First)
    if (selectedLangFilter.value === 'ar,en') {
        filtered.sort((a, b) => {
            const rank = (code: string) =>
                code === 'ar' ? 0 : code === 'en' ? 1 : 2;
            const cmp =
                rank(normalizeLangCode(a.language)) -
                rank(normalizeLangCode(b.language));
            if (cmp !== 0) return cmp;
            return (
                (Number(b.rating) || 0) - (Number(a.rating) || 0) ||
                (Number(b.downloads) || 0) - (Number(a.downloads) || 0)
            );
        });
    }

    return filtered;
});

const matchesSourceFilter = (
    res: any,
    filterKey: 'subsense' | 'opensubtitles' | 'subdl' | 'yts',
): boolean => {
    const provider = String(res?.provider || '').toLowerCase();
    const source = String(res?.source || '').toLowerCase();
    if (filterKey === 'subsense') return provider.includes('subsense');
    if (filterKey === 'opensubtitles')
        return source === 'opensubtitles' || provider.includes('opensubtitles');
    if (filterKey === 'subdl')
        return source === 'subdl' || provider.includes('subdl');
    if (filterKey === 'yts')
        return source.includes('yts') || provider.includes('yts');
    return true;
};

const sourceCounts = computed(() => {
    const items = languageFilteredResults.value;
    return {
        all: items.length,
        subsense: items.filter((r) => matchesSourceFilter(r, 'subsense'))
            .length,
        opensubtitles: items.filter((r) =>
            matchesSourceFilter(r, 'opensubtitles'),
        ).length,
        subdl: items.filter((r) => matchesSourceFilter(r, 'subdl')).length,
        yts: items.filter((r) => matchesSourceFilter(r, 'yts')).length,
    };
});

const filteredResults = computed(() => {
    const items = languageFilteredResults.value;
    if (selectedSourceFilter.value === 'all') {
        return items;
    }
    return items.filter((r) =>
        matchesSourceFilter(r, selectedSourceFilter.value as any),
    );
});

const initQuery = () => {
    if (props.isEpisode) {
        searchQuery.value =
            props.item?.series?.title ||
            props.item?.series_title ||
            props.item?.title ||
            '';
    } else {
        searchQuery.value = (props.item?.title || '')
            .replace(/\s*\(\d{4}\).*$/, '')
            .trim();
    }
};

watch(
    () => props.isOpen,
    (open) => {
        if (open) {
            selectedLangFilter.value = 'ar,en';
            selectedSourceFilter.value = 'all';
            initQuery();
            performSearch();
        } else {
            searchResults.value = [];
        }
    },
);

const setLangFilter = (langFilter: 'ar,en' | 'ar' | 'en') => {
    selectedLangFilter.value = langFilter;
    selectedSourceFilter.value = 'all';
    performSearch();
};

const performSearch = async () => {
    if (!searchQuery.value.trim()) return;
    isSearching.value = true;
    try {
        const type = props.isEpisode ? 'episode' : 'movie';
        const id = props.item?.watchable_id || props.item?.id;
        const qParams = new URLSearchParams({
            media_type: type,
            type: type,
            media_id: String(id),
            id: String(id),
            query: searchQuery.value,
            q: searchQuery.value,
            language: selectedLangFilter.value,
            languages: selectedLangFilter.value,
        });
        const res = await fetch(`/api/subtitles/search?${qParams.toString()}`);
        const data = await res.json();
        searchResults.value = Array.isArray(data.results) ? data.results : [];
    } catch (e) {
        toast.error(t('player.subtitle_search_error') || 'Search failed');
    } finally {
        isSearching.value = false;
    }
};

const downloadSubtitle = async (result: any) => {
    const itemId = getItemId(result);
    downloadingId.value = itemId;
    try {
        const type = props.isEpisode ? 'episode' : 'movie';
        const id = props.item?.watchable_id || props.item?.id;
        const releaseLabel =
            result.release ||
            result.release_name ||
            result.title ||
            result.file_name ||
            'Downloaded';
        const fileName = result.file_name || `${releaseLabel}.srt`;
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
                media_type: type,
                type,
                media_id: id,
                id,
                subtitle_id: itemId,
                file_id: String(result.file_id ?? itemId),
                download_url: result.download_url,
                language: normalizeLangCode(result.language || 'ar'),
                release: releaseLabel,
                release_name: releaseLabel,
                file_name: fileName,
                source: result.source || 'opensubtitles',
            }),
        });

        const data = await res.json().catch(() => ({}));
        if (res.ok && data?.subtitle) {
            toast.success(
                t('player.subtitle_downloaded') ||
                    (isRTL.value
                        ? 'تم تحميل وتفعيل الترجمة تلقائياً!'
                        : 'Subtitle downloaded & activated!'),
            );
            emit('downloaded', data.subtitle);
            emit('close');
        } else {
            toast.error(
                data?.message ||
                    t('player.subtitle_download_failed') ||
                    'Download failed',
            );
        }
    } catch (e) {
        toast.error(t('player.subtitle_download_failed') || 'Download failed');
    } finally {
        downloadingId.value = null;
    }
};

const getProviderBadgeClass = (res: any): string => {
    const provider = String(res?.provider || res?.source || '').toLowerCase();
    if (provider.includes('yts'))
        return 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30';
    if (provider.includes('subdl'))
        return 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30';
    if (provider.includes('subsense'))
        return 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30';
    return 'bg-purple-500/15 text-purple-300 border-purple-500/30';
};
</script>

<template>
    <div
        v-if="isOpen"
        class="animate-in fade-in absolute inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-md duration-200"
        @click.self="emit('close')"
    >
        <div
            class="w-full max-w-2xl space-y-4 rounded-3xl border border-white/10 bg-slate-900/95 p-6 shadow-2xl"
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-white/10 pb-3"
            >
                <div class="flex items-center gap-2">
                    <Search class="h-5 w-5 text-cyan-400" />
                    <h3 class="text-base font-bold text-white">
                        {{
                            t('player.search_online_subtitles') ||
                            'Search Online Subtitles'
                        }}
                    </h3>
                </div>
                <button
                    @click="emit('close')"
                    class="rounded-xl p-2 text-slate-400 transition-all hover:bg-white/10 hover:text-white"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- AI Arabic Subtitle Translator Banner -->
            <div
                class="flex items-center justify-between gap-3 rounded-2xl border border-emerald-500/30 bg-gradient-to-r from-emerald-500/10 via-cyan-500/10 to-transparent p-3.5"
            >
                <div class="flex min-w-0 items-center gap-2.5">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-emerald-500/30 bg-emerald-500/20 text-emerald-400"
                    >
                        <Sparkles class="h-4 w-4" />
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-bold text-white">
                            {{
                                t('player.instant_arabic_translation') ||
                                'Instant AI Arabic Translation'
                            }}
                        </div>
                        <div class="truncate text-[10px] text-emerald-300/80">
                            {{
                                t('player.ai_synthesized_arabic') ||
                                'Synthesize accurate Arabic subtitles from embedded English track'
                            }}
                        </div>
                    </div>
                </div>
                <button
                    type="button"
                    @click="emit('translate')"
                    :disabled="props.isTranslating"
                    class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-md shadow-emerald-500/20 transition-all hover:bg-emerald-500 disabled:opacity-50"
                >
                    <Loader2
                        v-if="props.isTranslating"
                        class="h-3.5 w-3.5 animate-spin"
                    />
                    <Sparkles v-else class="h-3.5 w-3.5" />
                    <span>{{
                        props.isTranslating
                            ? t('player.translating') || 'Translating...'
                            : t('player.translate_now') || 'Translate Now'
                    }}</span>
                </button>
            </div>

            <!-- Search Form -->
            <form
                @submit.prevent="performSearch"
                class="flex items-center gap-2"
            >
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Movie or series title..."
                    class="flex-1 rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-xs text-white placeholder-slate-500 focus:border-cyan-400 focus:outline-none"
                />
                <button
                    type="submit"
                    :disabled="isSearching"
                    class="flex shrink-0 items-center gap-1.5 rounded-xl bg-cyan-500 px-4 py-2.5 text-xs font-bold text-slate-950 transition-all hover:bg-cyan-400 disabled:opacity-50"
                >
                    <Loader2 v-if="isSearching" class="h-4 w-4 animate-spin" />
                    <Search v-else class="h-4 w-4" />
                    {{ t('common.search') || 'Search' }}
                </button>
            </form>

            <!-- Language & Source Filters -->
            <div
                class="flex flex-col justify-between gap-2 pt-1 sm:flex-row sm:items-center"
            >
                <!-- Language Filter Pills -->
                <div
                    class="flex items-center gap-1.5 rounded-xl border border-white/10 bg-white/5 p-1"
                >
                    <button
                        type="button"
                        @click="setLangFilter('ar,en')"
                        class="cursor-pointer rounded-lg px-2.5 py-1 text-[11px] font-bold transition-all"
                        :class="
                            selectedLangFilter === 'ar,en'
                                ? 'bg-emerald-500 text-slate-950 shadow-sm'
                                : 'text-slate-300 hover:text-white'
                        "
                    >
                        {{ isRTL ? 'الكل (العربية أولاً)' : 'All (AR First)' }}
                    </button>
                    <button
                        type="button"
                        @click="setLangFilter('ar')"
                        class="cursor-pointer rounded-lg px-2.5 py-1 text-[11px] font-bold transition-all"
                        :class="
                            selectedLangFilter === 'ar'
                                ? 'bg-emerald-500 text-slate-950 shadow-sm'
                                : 'text-slate-300 hover:text-white'
                        "
                    >
                        {{ isRTL ? 'العربية (AR)' : 'Arabic (AR)' }}
                    </button>
                    <button
                        type="button"
                        @click="setLangFilter('en')"
                        class="cursor-pointer rounded-lg px-2.5 py-1 text-[11px] font-bold transition-all"
                        :class="
                            selectedLangFilter === 'en'
                                ? 'bg-cyan-500 text-slate-950 shadow-sm'
                                : 'text-slate-300 hover:text-white'
                        "
                    >
                        {{ isRTL ? 'الإنجليزية (EN)' : 'English (EN)' }}
                    </button>
                </div>

                <!-- Source Filter Pills -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        type="button"
                        @click="selectedSourceFilter = 'all'"
                        class="cursor-pointer rounded-lg border px-2 py-1 text-[10px] font-bold transition-all"
                        :class="
                            selectedSourceFilter === 'all'
                                ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300'
                                : 'border-white/10 bg-white/5 text-slate-400 hover:text-white'
                        "
                    >
                        {{ isRTL ? 'كل المصادر' : 'All Sources' }} ({{
                            sourceCounts.all
                        }})
                    </button>
                    <button
                        v-if="sourceCounts.subsense > 0"
                        type="button"
                        @click="selectedSourceFilter = 'subsense'"
                        class="cursor-pointer rounded-lg border px-2 py-1 text-[10px] font-bold transition-all"
                        :class="
                            selectedSourceFilter === 'subsense'
                                ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300'
                                : 'border-white/10 bg-white/5 text-slate-400 hover:text-white'
                        "
                    >
                        SubSense ({{ sourceCounts.subsense }})
                    </button>
                    <button
                        v-if="sourceCounts.opensubtitles > 0"
                        type="button"
                        @click="selectedSourceFilter = 'opensubtitles'"
                        class="cursor-pointer rounded-lg border px-2 py-1 text-[10px] font-bold transition-all"
                        :class="
                            selectedSourceFilter === 'opensubtitles'
                                ? 'border-purple-500/40 bg-purple-500/20 text-purple-300'
                                : 'border-white/10 bg-white/5 text-slate-400 hover:text-white'
                        "
                    >
                        OpenSubtitles ({{ sourceCounts.opensubtitles }})
                    </button>
                    <button
                        v-if="sourceCounts.subdl > 0"
                        type="button"
                        @click="selectedSourceFilter = 'subdl'"
                        class="cursor-pointer rounded-lg border px-2 py-1 text-[10px] font-bold transition-all"
                        :class="
                            selectedSourceFilter === 'subdl'
                                ? 'border-indigo-500/40 bg-indigo-500/20 text-indigo-300'
                                : 'border-white/10 bg-white/5 text-slate-400 hover:text-white'
                        "
                    >
                        SubDL ({{ sourceCounts.subdl }})
                    </button>
                    <button
                        v-if="sourceCounts.yts > 0"
                        type="button"
                        @click="selectedSourceFilter = 'yts'"
                        class="cursor-pointer rounded-lg border px-2 py-1 text-[10px] font-bold transition-all"
                        :class="
                            selectedSourceFilter === 'yts'
                                ? 'border-emerald-500/40 bg-emerald-500/20 text-emerald-300'
                                : 'border-white/10 bg-white/5 text-slate-400 hover:text-white'
                        "
                    >
                        YTS ({{ sourceCounts.yts }})
                    </button>
                </div>
            </div>

            <!-- Results List -->
            <div class="max-h-72 space-y-2 overflow-y-auto pr-1">
                <div
                    v-if="isSearching"
                    class="flex items-center justify-center gap-2 py-8 text-center text-xs text-slate-400"
                >
                    <Loader2 class="h-4 w-4 animate-spin text-cyan-400" />
                    {{
                        isRTL
                            ? 'جارٍ البحث في مزودات الترجمة...'
                            : 'Searching providers...'
                    }}
                </div>

                <div
                    v-else-if="filteredResults.length === 0"
                    class="flex flex-col items-center gap-2.5 py-8 text-center text-xs text-slate-500"
                >
                    <span>{{
                        t('player.no_subtitles_found') ||
                        'No subtitles found. Try adjusting query or filters.'
                    }}</span>
                    <button
                        type="button"
                        @click="emit('translate')"
                        :disabled="props.isTranslating"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-500 disabled:opacity-50"
                    >
                        <Loader2
                            v-if="props.isTranslating"
                            class="h-3.5 w-3.5 animate-spin"
                        />
                        <Sparkles v-else class="h-3.5 w-3.5" />
                        <span>{{
                            props.isTranslating
                                ? t('player.translating_to_arabic') ||
                                  'Translating to Arabic...'
                                : t('player.generate_arabic_now') ||
                                  'Generate Arabic with AI'
                        }}</span>
                    </button>
                </div>

                <div
                    v-for="res in filteredResults"
                    :key="getItemId(res)"
                    class="flex items-center justify-between gap-3 rounded-2xl border border-white/5 bg-white/5 p-3 transition-all hover:border-cyan-500/30"
                >
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 flex flex-wrap items-center gap-1.5">
                            <!-- Language Pill -->
                            <span
                                class="rounded border px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                :class="
                                    normalizeLangCode(res.language) === 'ar'
                                        ? 'border-emerald-500/30 bg-emerald-500/20 text-emerald-300'
                                        : 'border-sky-500/30 bg-sky-500/20 text-sky-300'
                                "
                            >
                                {{ normalizeLangCode(res.language) }}
                            </span>

                            <!-- Provider / Source Pill -->
                            <span
                                class="rounded border px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                :class="getProviderBadgeClass(res)"
                            >
                                {{
                                    res.provider ||
                                    res.source ||
                                    'OpenSubtitles'
                                }}
                            </span>

                            <!-- Format Badge -->
                            <span
                                class="rounded bg-white/10 px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-300 uppercase"
                            >
                                {{ res.format || 'srt' }}
                            </span>

                            <!-- Rating & Downloads -->
                            <span
                                v-if="res.rating && Number(res.rating) > 0"
                                class="inline-flex items-center gap-0.5 text-[10px] font-semibold text-amber-400"
                            >
                                <Star
                                    class="h-3 w-3 fill-amber-400 text-amber-400"
                                />
                                {{ Number(res.rating).toFixed(1) }}
                            </span>
                            <span
                                v-if="
                                    res.downloads && Number(res.downloads) > 0
                                "
                                class="text-[10px] text-slate-400"
                            >
                                · {{ Number(res.downloads).toLocaleString() }}
                            </span>
                        </div>
                        <div
                            class="truncate font-mono text-xs font-medium text-white"
                            :title="
                                res.release ||
                                res.file_name ||
                                res.release_name ||
                                res.title
                            "
                        >
                            {{
                                res.release ||
                                res.file_name ||
                                res.release_name ||
                                res.title ||
                                'Subtitle'
                            }}
                        </div>
                    </div>

                    <button
                        @click="downloadSubtitle(res)"
                        :disabled="downloadingId === getItemId(res)"
                        class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl border border-cyan-500/30 bg-cyan-500/15 px-3 py-2 text-xs font-bold text-cyan-300 transition-all hover:bg-cyan-500/25 disabled:opacity-50"
                        :title="
                            t('player.download_subtitle') ||
                            'Download & Activate'
                        "
                    >
                        <Loader2
                            v-if="downloadingId === getItemId(res)"
                            class="h-4 w-4 animate-spin"
                        />
                        <Download v-else class="h-4 w-4" />
                        <span class="hidden sm:inline">{{
                            isRTL ? 'تطبيق' : 'Apply'
                        }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
