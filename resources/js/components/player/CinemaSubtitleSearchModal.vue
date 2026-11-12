<script setup lang="ts">
import { ref, watch } from 'vue';
import { X, Search, Download, Loader2, Check, Sparkles } from 'lucide-vue-next';
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
    (e: 'downloaded'): void;
    (e: 'translate'): void;
}>();

const { t } = useI18n();
const toast = useToast();

const searchQuery = ref('');
const isSearching = ref(false);
const searchResults = ref<any[]>([]);
const downloadingId = ref<string | number | null>(null);

const initQuery = () => {
    if (props.isEpisode) {
        searchQuery.value = props.item?.series?.title || props.item?.series_title || props.item?.title || '';
    } else {
        searchQuery.value = (props.item?.title || '').replace(/\s*\(\d{4}\).*$/, '').trim();
    }
};

watch(() => props.isOpen, (open) => {
    if (open) {
        initQuery();
        performSearch();
    } else {
        searchResults.value = [];
    }
});

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
    downloadingId.value = result.id;
    try {
        const type = props.isEpisode ? 'episode' : 'movie';
        const id = props.item?.watchable_id || props.item?.id;
        const res = await fetch('/api/subtitles/download', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                media_type: type,
                type,
                media_id: id,
                id,
                subtitle_id: result.id,
                download_url: result.download_url,
                language: result.language || 'ar',
                release: result.release_name || result.title,
                release_name: result.release_name || result.title,
                file_id: result.file_id || result.id,
            }),
        });

        if (res.ok) {
            toast.success(t('player.subtitle_downloaded') || 'Subtitle downloaded successfully!');
            emit('downloaded');
            emit('close');
        } else {
            toast.error(t('player.subtitle_download_failed') || 'Download failed');
        }
    } catch (e) {
        toast.error(t('player.subtitle_download_failed') || 'Download failed');
    } finally {
        downloadingId.value = null;
    }
};
</script>

<template>
    <div
        v-if="isOpen"
        class="absolute inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4 animate-in fade-in duration-200"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-lg rounded-3xl bg-slate-900/95 border border-white/10 shadow-2xl p-6 space-y-4">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2">
                    <Search class="w-5 h-5 text-cyan-400" />
                    <h3 class="text-base font-bold text-white">
                        {{ t('player.search_online_subtitles') || 'Search Online Subtitles' }}
                    </h3>
                </div>
                <button
                    @click="emit('close')"
                    class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 transition-all"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- AI Arabic Subtitle Translator Banner -->
            <div class="p-3.5 rounded-2xl bg-gradient-to-r from-emerald-500/10 via-cyan-500/10 to-transparent border border-emerald-500/30 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 flex items-center justify-center shrink-0">
                        <Sparkles class="w-4 h-4" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-white truncate">{{ t('player.instant_arabic_translation') || 'Instant AI Arabic Translation' }}</div>
                        <div class="text-[10px] text-emerald-300/80 truncate">{{ t('player.ai_synthesized_arabic') || 'Synthesize accurate Arabic subtitles from embedded English track' }}</div>
                    </div>
                </div>
                <button
                    type="button"
                    @click="emit('translate')"
                    :disabled="props.isTranslating"
                    class="shrink-0 px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer disabled:opacity-50 shadow-md shadow-emerald-500/20"
                >
                    <Loader2 v-if="props.isTranslating" class="w-3.5 h-3.5 animate-spin" />
                    <Sparkles v-else class="w-3.5 h-3.5" />
                    <span>{{ props.isTranslating ? (t('player.translating') || 'Translating...') : (t('player.translate_now') || 'Translate Now') }}</span>
                </button>
            </div>

            <!-- Search Form -->
            <form @submit.prevent="performSearch" class="flex items-center gap-2">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Movie or series title..."
                    class="flex-1 px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-cyan-400"
                />
                <button
                    type="submit"
                    :disabled="isSearching"
                    class="px-4 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 disabled:opacity-50 text-slate-950 text-xs font-bold transition-all flex items-center gap-1.5 shrink-0"
                >
                    <Loader2 v-if="isSearching" class="w-4 h-4 animate-spin" />
                    <Search v-else class="w-4 h-4" />
                    {{ t('common.search') || 'Search' }}
                </button>
            </form>

            <!-- Results List -->
            <div class="max-h-60 overflow-y-auto space-y-2 pr-1">
                <div v-if="isSearching" class="py-8 text-center text-slate-400 text-xs flex items-center justify-center gap-2">
                    <Loader2 class="w-4 h-4 animate-spin text-cyan-400" />
                    Searching providers...
                </div>

                <div v-else-if="searchResults.length === 0" class="py-8 text-center text-slate-500 text-xs flex flex-col items-center gap-2.5">
                    <span>{{ t('player.no_subtitles_found') || 'No subtitles found. Try adjusting query.' }}</span>
                    <button
                        type="button"
                        @click="emit('translate')"
                        :disabled="props.isTranslating"
                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold inline-flex items-center gap-2 cursor-pointer shadow-lg shadow-emerald-500/20 disabled:opacity-50 transition-all"
                    >
                        <Loader2 v-if="props.isTranslating" class="w-3.5 h-3.5 animate-spin" />
                        <Sparkles v-else class="w-3.5 h-3.5" />
                        <span>{{ props.isTranslating ? (t('player.translating_to_arabic') || 'Translating to Arabic...') : (t('player.generate_arabic_now') || 'Generate Arabic with AI') }}</span>
                    </button>
                </div>

                <div
                    v-for="res in searchResults"
                    :key="res.id"
                    class="p-3 rounded-2xl bg-white/5 border border-white/5 hover:border-white/10 transition-all flex items-center justify-between gap-3"
                >
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-bold text-white truncate">
                            {{ res.release_name || res.title || 'Subtitle' }}
                        </div>
                        <div class="text-[11px] text-slate-400 flex items-center gap-2 mt-0.5">
                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-cyan-500/20 text-cyan-300 font-bold uppercase">
                                {{ res.language || 'ar' }}
                            </span>
                            <span v-if="res.downloads" class="text-slate-500">
                                {{ res.downloads }} downloads
                            </span>
                        </div>
                    </div>

                    <button
                        @click="downloadSubtitle(res)"
                        :disabled="downloadingId === res.id"
                        class="p-2 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 text-xs font-bold transition-all disabled:opacity-50 shrink-0"
                        :title="t('player.download_subtitle') || 'Download'"
                    >
                        <Loader2 v-if="downloadingId === res.id" class="w-4 h-4 animate-spin" />
                        <Download v-else class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
