<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    X, Search, Sparkles, Check, Image as ImageIcon, Star, Calendar,
    Film, Tv, RefreshCw, AlertCircle, Save, SlidersHorizontal, Layers,
    Hash, Repeat, ArrowRightLeft, Wand2, Copy, FileVideo, Folder, HardDrive
} from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    item: any;
    type?: 'movie' | 'series';
}>();

const emit = defineEmits(['close', 'updated']);

const { isRTL, t } = useI18n();

const activeTab = ref<'search' | 'direct_id' | 'manual'>('search');
const searchQuery = ref('');
const searchYear = ref<string>('');
const directIdInput = ref('');
const isSearching = ref(false);
const isSaving = ref(false);
const searchResults = ref<any[]>([]);
const searchError = ref('');
const successMessage = ref('');
const copiedPath = ref(false);

// Manual form state
const form = ref({
    title: '',
    title_ar: '',
    release_year: '',
    rating: '7.5',
    collection_name: '',
    overview: '',
    overview_ar: '',
    poster_path: '',
    backdrop_path: '',
});

const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && props.show) {
        closeModal();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});

const closeModal = () => {
    searchError.value = '';
    successMessage.value = '';
    copiedPath.value = false;
    emit('close');
};

const cleanTitleForSearch = (raw: string): { title: string; year: string } => {
    if (!raw) return { title: '', year: '' };
    
    let clean = raw.trim();
    let year = '';
    
    // Extract 4-digit year if present (e.g., Inception (2010) or Inception.2010)
    const yearMatch = clean.match(/\b(19\d\d|20\d\d)\b/);
    if (yearMatch) {
        year = yearMatch[1];
        clean = clean.replace(yearMatch[0], ' ');
    }
    
    // Strip common release quality tags
    clean = clean.replace(/\b(BluRay|BDRip|BRRip|WEBRip|WEB-DL|HDRip|DVDRip|1080p|720p|2160p|4K|x264|x265|HEVC|AAC|DTS|REMUX)\b/gi, ' ');
    
    // Strip sequence prefixes like "1.", "02.", "3 - "
    clean = clean.replace(/^(\d{1,3})[-_.\s]+/, '');
    
    // Replace dots/underscores with spaces
    clean = clean.replace(/[._-]+/g, ' ').replace(/\s+/g, ' ').trim();
    
    return { title: clean, year };
};

watch(() => props.item, (newItem) => {
    if (newItem) {
        const rawTitle = newItem.title || '';
        const parsed = cleanTitleForSearch(rawTitle);
        
        searchQuery.value = parsed.title || rawTitle;
        searchYear.value = newItem.release_year ? String(newItem.release_year) : parsed.year;
        directIdInput.value = newItem.tmdb_id ? String(newItem.tmdb_id) : (newItem.imdb_id ? String(newItem.imdb_id) : '');
        
        form.value = {
            title: newItem.title || '',
            title_ar: newItem.title_ar || '',
            release_year: newItem.release_year ? String(newItem.release_year) : '',
            rating: newItem.rating ? String(newItem.rating) : '7.5',
            collection_name: newItem.collection_name || '',
            overview: newItem.overview || '',
            overview_ar: newItem.overview_ar || '',
            poster_path: newItem.poster_path || '',
            backdrop_path: newItem.backdrop_path || '',
        };
        searchResults.value = [];
        searchError.value = '';
        successMessage.value = '';
        copiedPath.value = false;
    }
}, { immediate: true });

const isSeriesType = computed(() => {
    return props.type === 'series' || props.item?.type === 'series' || !!props.item?.seasons || !!props.item?.seasons_count;
});

const copyFilePath = async (path?: string) => {
    const textToCopy = path || props.item?.file_path || props.item?.folder_path || '';
    if (!textToCopy) return;
    try {
        await navigator.clipboard.writeText(textToCopy);
        copiedPath.value = true;
        setTimeout(() => {
            copiedPath.value = false;
        }, 2500);
    } catch (e) {
        console.error('Failed to copy', e);
    }
};

const performSearch = async () => {
    if (!searchQuery.value.trim()) return;

    isSearching.value = true;
    searchError.value = '';
    searchResults.value = [];

    const mediaType = isSeriesType.value ? 'series' : 'movie';
    const cleanQ = searchQuery.value.replace(/[._]+/g, ' ').trim();
    const endpoint = mediaType === 'series'
        ? `/api/series/search-metadata?query=${encodeURIComponent(cleanQ)}&year=${encodeURIComponent(searchYear.value || '')}`
        : `/api/media/search-metadata?query=${encodeURIComponent(cleanQ)}&year=${encodeURIComponent(searchYear.value || '')}`;

    try {
        const res = await fetch(endpoint);
        if (res.ok) {
            const data = await res.json();
            searchResults.value = data.results || [];
            if (searchResults.value.length === 0) {
                searchError.value = isRTL.value 
                    ? 'لم يتم العثور على نتائج. جرب كتابة اسم مختلف أو إزالة السنة.' 
                    : 'No results found. Try refining the title or removing the year.';
            }
        } else {
            searchError.value = isRTL.value ? 'فشل البحث في مزودات البيانات العالمية.' : 'Failed to search online providers.';
        }
    } catch (e) {
        searchError.value = isRTL.value ? 'خطأ في الاتصال أثناء البحث عبر الإنترنت.' : 'Network error during search.';
    } finally {
        isSearching.value = false;
    }
};

const lookupAndApplyDirectId = async () => {
    if (!directIdInput.value.trim()) return;

    isSearching.value = true;
    searchError.value = '';
    successMessage.value = '';

    try {
        const res = await fetch('/api/metadata/lookup-id', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                id: props.item.id,
                type: isSeriesType.value ? 'series' : 'movie',
                external_id: directIdInput.value.trim(),
            }),
        });

        const data = await res.json();
        if (data.success) {
            successMessage.value = isRTL.value ? 'تم جلب وتطبيق البيانات بنجاح!' : 'Metadata applied successfully!';
            emit('updated', data.media || data.series);
            setTimeout(() => closeModal(), 1200);
        } else {
            searchError.value = data.message || (isRTL.value ? 'معرّف TMDb / IMDb غير صالح أو غير موجود.' : 'Invalid or not found TMDb / IMDb ID.');
        }
    } catch (e) {
        searchError.value = isRTL.value ? 'خطأ أثناء جلب المعرف المباشر.' : 'Error fetching direct ID metadata.';
    } finally {
        isSearching.value = false;
    }
};

const reparseFilename = async () => {
    if (!props.item?.id) return;
    isSearching.value = true;
    searchError.value = '';
    successMessage.value = '';

    const mediaType = isSeriesType.value ? 'series' : 'movie';
    try {
        const res = await fetch(`/api/metadata/${mediaType}/${props.item.id}/reparse`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });

        const data = await res.json();
        if (data.success) {
            successMessage.value = isRTL.value ? 'تمت إعادة معالجة اسم الملف بنجاح!' : 'Filename re-parsed and enriched successfully!';
            emit('updated', data.media || data.series);
            setTimeout(() => closeModal(), 1200);
        } else {
            searchError.value = data.message || (isRTL.value ? 'فشلت إعادة قراءة الملف.' : 'Failed to reparse filename.');
        }
    } catch (e) {
        searchError.value = isRTL.value ? 'خطأ في معالجة الملف.' : 'Error reparsing filename.';
    } finally {
        isSearching.value = false;
    }
};

const convertMediaType = async () => {
    if (!props.item?.id) return;
    isSearching.value = true;
    searchError.value = '';
    successMessage.value = '';

    const mediaType = isSeriesType.value ? 'series' : 'movie';
    try {
        const res = await fetch(`/api/metadata/${mediaType}/${props.item.id}/convert-type`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });

        const data = await res.json();
        if (data.success) {
            successMessage.value = isRTL.value ? 'تم تحويل نوع الوسائط بنجاح!' : 'Media type converted successfully!';
            emit('updated', data.media || data.series);
            setTimeout(() => closeModal(), 1200);
        } else {
            searchError.value = data.message || (isRTL.value ? 'فشل تحويل النوع.' : 'Failed to convert media type.');
        }
    } catch (e) {
        searchError.value = isRTL.value ? 'خطأ أثناء تحويل النوع.' : 'Error converting media type.';
    } finally {
        isSearching.value = false;
    }
};

const applyMatch = async (result: any) => {
    if (!props.item?.id) return;
    isSaving.value = true;
    searchError.value = '';

    const endpoint = isSeriesType.value
        ? `/api/series/${props.item.id}/fix-match`
        : `/api/media/${props.item.id}/fix-match`;

    try {
        const payload = {
            title: result.title,
            title_ar: result.title_ar || null,
            provider: result.provider || 'TMDb',
            id: String(result.id || result.tmdb_id || ''),
            year: result.release_year || result.year || null,
            overview: result.overview || null,
            overview_ar: result.overview_ar || null,
            poster_path: result.poster_path || null,
            backdrop_path: result.backdrop_path || null,
            rating: result.rating || null,
            runtime_minutes: result.runtime_minutes || null,
        };

        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify(payload),
        });

        if (res.ok) {
            const data = await res.json();
            const updated = data.media || data.series;
            emit('updated', updated);
            closeModal();
        } else {
            searchError.value = isRTL.value ? 'فشل تطبيق المطابقة.' : 'Failed to apply match.';
        }
    } catch (e) {
        searchError.value = isRTL.value ? 'خطأ في حفظ البيانات المطابقة.' : 'Error saving matched data.';
    } finally {
        isSaving.value = false;
    }
};

const saveManualEdit = async () => {
    if (!props.item?.id) return;
    isSaving.value = true;
    searchError.value = '';

    const endpoint = isSeriesType.value
        ? `/api/series/${props.item.id}/update-metadata`
        : `/api/media/${props.item.id}/update-metadata`;

    try {
        const payload = {
            title: form.value.title,
            title_ar: form.value.title_ar || null,
            release_year: form.value.release_year ? Number(form.value.release_year) : null,
            rating: form.value.rating ? Number(form.value.rating) : null,
            collection_name: form.value.collection_name || null,
            overview: form.value.overview || null,
            overview_ar: form.value.overview_ar || null,
            poster_path: form.value.poster_path || null,
            backdrop_path: form.value.backdrop_path || null,
        };

        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify(payload),
        });

        if (res.ok) {
            const data = await res.json();
            const updated = data.media || data.series;
            emit('updated', updated);
            closeModal();
        } else {
            searchError.value = isRTL.value ? 'فشل حفظ التعديلات اليدوية.' : 'Failed to save manual edits.';
        }
    } catch (e) {
        searchError.value = isRTL.value ? 'خطأ في حفظ التعديلات.' : 'Error saving manual edits.';
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fade-in">
        <div class="relative w-full max-w-2xl rounded-3xl glass-panel border border-cyan-500/30 bg-[#0B0F19] p-6 lg:p-8 shadow-2xl shadow-cyan-500/10 max-h-[92vh] flex flex-col space-y-4">
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-white/10 pb-3 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="p-2.5 rounded-2xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 shrink-0">
                        <Sparkles class="w-5 h-5 animate-pulse" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-bold text-white tracking-wide truncate">
                                {{ isRTL ? 'إصلاح وتعديل بيانات الوسائط' : 'Fix Match & Metadata Studio' }}
                            </h3>
                            <span class="text-xs px-2 py-0.5 rounded-full font-semibold shrink-0" :class="isSeriesType ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30'">
                                {{ isSeriesType ? (isRTL ? 'مسلسل' : 'Series') : (isRTL ? 'فيلم' : 'Movie') }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 truncate mt-0.5">
                            {{ item?.title || item?.file_path || item?.folder_path || '' }}
                        </p>
                    </div>
                </div>
                <button @click="closeModal" class="p-2 text-slate-400 hover:text-white rounded-xl hover:bg-white/5 transition-colors shrink-0">
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Full Physical Storage Location Bar -->
            <div class="p-3 rounded-2xl bg-slate-950/90 border border-white/10 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 shrink-0 shadow-inner">
                <div class="flex items-start sm:items-center gap-2 overflow-hidden min-w-0 flex-1">
                    <FileVideo v-if="!isSeriesType" class="w-4 h-4 text-cyan-400 shrink-0 mt-0.5 sm:mt-0" />
                    <Folder v-else class="w-4 h-4 text-purple-400 shrink-0 mt-0.5 sm:mt-0" />
                    <div class="min-w-0 flex-1">
                        <span class="text-[10px] text-slate-500 font-bold block uppercase tracking-wider mb-0.5">
                            {{ isRTL ? 'الموقع الفعلي على القرص' : 'Physical Disk Location' }}
                        </span>
                        <span class="font-mono text-slate-300 select-all break-all text-[11px] leading-tight block">
                            {{ item?.file_path || item?.folder_path || 'Unknown Path' }}
                        </span>
                    </div>
                </div>
                <button
                    @click="copyFilePath(item?.file_path || item?.folder_path)"
                    type="button"
                    class="px-3 py-1.5 rounded-xl bg-white/[0.07] hover:bg-white/[0.12] text-slate-200 text-xs font-semibold shrink-0 transition-all flex items-center justify-center gap-1.5 border border-white/10 hover:border-cyan-500/30 cursor-pointer active:scale-95"
                    :title="isRTL ? 'نسخ المسار الكامل إلى الحافظة' : 'Copy full file path to clipboard'"
                >
                    <Check v-if="copiedPath" class="w-3.5 h-3.5 text-emerald-400" />
                    <Copy v-else class="w-3.5 h-3.5 text-cyan-400" />
                    <span>{{ copiedPath ? (isRTL ? 'تم النسخ!' : 'Copied!') : (isRTL ? 'نسخ المسار' : 'Copy Path') }}</span>
                </button>
            </div>

            <!-- Tab Navigation & Action Bar -->
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/5 pb-2 shrink-0">
                <div class="flex items-center gap-1.5 p-1 bg-slate-900/80 rounded-2xl border border-white/5">
                    <button
                        @click="activeTab = 'search'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer"
                        :class="activeTab === 'search' ? 'bg-cyan-500 text-slate-950 shadow-md font-bold' : 'text-slate-400 hover:text-white'"
                    >
                        <Search class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'بحث أونلاين' : 'Online Search' }}</span>
                    </button>
                    <button
                        @click="activeTab = 'direct_id'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer"
                        :class="activeTab === 'direct_id' ? 'bg-cyan-500 text-slate-950 shadow-md font-bold' : 'text-slate-400 hover:text-white'"
                    >
                        <Hash class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'معرّف مباشر' : 'Direct ID' }}</span>
                    </button>
                    <button
                        @click="activeTab = 'manual'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer"
                        :class="activeTab === 'manual' ? 'bg-cyan-500 text-slate-950 shadow-md font-bold' : 'text-slate-400 hover:text-white'"
                    >
                        <SlidersHorizontal class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'تعديل يدوي' : 'Manual Edit' }}</span>
                    </button>
                </div>

                <!-- Quick Action Tools -->
                <div class="flex items-center gap-1.5">
                    <button
                        @click="reparseFilename"
                        :disabled="isSearching"
                        class="px-2.5 py-1.5 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-white/10 transition-all flex items-center gap-1 cursor-pointer"
                        :title="isRTL ? 'إعادة قراءة وتفكيك اسم الملف' : 'Re-parse filename using intelligent scene engine'"
                    >
                        <Repeat class="w-3.5 h-3.5 text-cyan-400" />
                        <span>{{ isRTL ? 'إعادة تفكيك' : 'Re-Parse' }}</span>
                    </button>
                    <button
                        @click="convertMediaType"
                        :disabled="isSearching"
                        class="px-2.5 py-1.5 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-white/10 transition-all flex items-center gap-1 cursor-pointer"
                        :title="isRTL ? 'تحويل بين فيلم ومسلسل' : 'Convert between Movie and Series'"
                    >
                        <ArrowRightLeft class="w-3.5 h-3.5 text-purple-400" />
                        <span>{{ isSeriesType ? (isRTL ? 'تحويل لفيلم' : 'To Movie') : (isRTL ? 'تحويل لمسلسل' : 'To Series') }}</span>
                    </button>
                </div>
            </div>

            <!-- Error and Success Banners -->
            <div v-if="searchError" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2 shrink-0">
                <AlertCircle class="w-4 h-4 shrink-0" />
                <span>{{ searchError }}</span>
            </div>
            <div v-if="successMessage" class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-center gap-2 shrink-0">
                <Check class="w-4 h-4 shrink-0" />
                <span>{{ successMessage }}</span>
            </div>

            <!-- Tab 1: Live Online Search -->
            <div v-if="activeTab === 'search'" class="flex-1 overflow-y-auto space-y-4 pr-1">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">{{ isRTL ? 'عنوان الوسائط' : 'Media Title / Query' }}</label>
                        <input
                            v-model="searchQuery"
                            @keyup.enter="performSearch"
                            type="text"
                            placeholder="e.g. The Dark Knight, Fast and Furious, Inception..."
                            class="w-full bg-slate-900/90 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">{{ isRTL ? 'السنة (اختياري)' : 'Year (Optional)' }}</label>
                        <input
                            v-model="searchYear"
                            @keyup.enter="performSearch"
                            type="text"
                            placeholder="2024"
                            class="w-full bg-slate-900/90 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all text-center"
                        />
                    </div>
                </div>

                <button
                    @click="performSearch"
                    :disabled="isSearching"
                    class="w-full py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-bold text-sm shadow-lg shadow-cyan-500/20 transition-all flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer"
                >
                    <RefreshCw v-if="isSearching" class="w-3.5 h-3.5 animate-spin" />
                    <Search v-else class="w-3.5 h-3.5" />
                    <span>{{ isRTL ? 'بحث في قواعد البيانات' : 'Search Metadata Providers' }}</span>
                </button>

                <!-- Search Results List -->
                <div v-if="searchResults.length > 0" class="space-y-2.5 mt-4">
                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                        {{ isRTL ? 'النتائج المتطابقة أونلاين' : 'Matched Online Candidates' }} ({{ searchResults.length }})
                    </h4>
                    <div class="grid grid-cols-1 gap-2.5 max-h-60 overflow-y-auto pr-1">
                        <div
                            v-for="res in searchResults"
                            :key="res.id"
                            class="p-3 rounded-2xl bg-slate-900/80 border border-white/10 hover:border-cyan-500/50 flex items-center justify-between gap-3 group transition-all"
                        >
                            <div class="flex items-center gap-3 overflow-hidden">
                                <img
                                    v-if="res.poster_path"
                                    :src="res.poster_path"
                                    class="w-12 h-16 object-cover rounded-lg shadow-md shrink-0 border border-white/10"
                                    alt="Poster"
                                />
                                <div v-else class="w-12 h-16 rounded-lg bg-slate-800 flex items-center justify-center shrink-0 border border-white/10 text-slate-600">
                                    <Film class="w-5 h-5" />
                                </div>
                                <div class="truncate">
                                    <div class="flex items-center gap-2">
                                        <h5 class="font-bold text-white text-sm truncate group-hover:text-cyan-400 transition-colors">
                                            {{ res.title || res.name }}
                                        </h5>
                                        <span v-if="res.release_year || res.first_air_year" class="text-xs text-slate-400">
                                            ({{ res.release_year || res.first_air_year }})
                                        </span>
                                    </div>
                                    <p v-if="res.title_ar" class="text-xs text-cyan-300 font-medium truncate mt-0.5">
                                        {{ res.title_ar }}
                                    </p>
                                    <p class="text-xs text-slate-400 truncate max-w-md mt-0.5">
                                        {{ res.overview || 'No overview available.' }}
                                    </p>
                                    <div class="flex items-center gap-3 mt-1 text-[11px] text-slate-400">
                                        <span class="flex items-center gap-1 text-amber-400 font-bold">
                                            <Star class="w-3 h-3 fill-amber-400" />
                                            {{ res.rating ? Number(res.rating).toFixed(1) : 'N/A' }}
                                        </span>
                                        <span class="font-bold text-cyan-400">{{ res.provider || 'TMDb' }}</span>
                                    </div>
                                </div>
                            </div>
                            <button
                                @click="applyMatch(res)"
                                :disabled="isSaving"
                                class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs shrink-0 transition-all flex items-center gap-1.5 shadow-md shadow-cyan-500/10 cursor-pointer"
                            >
                                <Check class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'تطبيق' : 'Apply' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Direct ID Lookup -->
            <div v-if="activeTab === 'direct_id'" class="space-y-4">
                <div class="p-4 rounded-2xl bg-cyan-500/5 border border-cyan-500/20 text-slate-300 text-xs leading-relaxed">
                    <p class="font-semibold text-cyan-400 mb-1">
                        {{ isRTL ? 'جلب مباشر بمعرف TMDb أو IMDb:' : 'Direct ID Lookup:' }}
                    </p>
                    <p>
                        {{ isRTL 
                            ? 'أدخل رقم معرّف الفيلم أو المسلسل من موقع TMDb (مثل: 27205 لفيلم Inception) أو معرّف IMDb (مثل: tt1375666) لجلب البيانات فوراً وتحديث العنوان والبوسترات والترجمة العربية.' 
                            : 'Enter the exact TMDb numeric ID (e.g. 27205) or IMDb ID (e.g. tt1375666) to instantly fetch and apply all bilingual metadata.' }}
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">{{ isRTL ? 'معرّف TMDb أو IMDb' : 'TMDb / IMDb ID' }}</label>
                    <div class="flex items-center gap-2">
                        <input
                            v-model="directIdInput"
                            @keyup.enter="lookupAndApplyDirectId"
                            type="text"
                            placeholder="e.g. 27205 or tt1375666"
                            class="flex-1 bg-slate-900/90 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all font-mono"
                        />
                        <button
                            @click="lookupAndApplyDirectId"
                            :disabled="isSearching"
                            class="px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-sm transition-all flex items-center gap-1.5 disabled:opacity-50 cursor-pointer"
                        >
                            <RefreshCw v-if="isSearching" class="w-4 h-4 animate-spin" />
                            <Wand2 v-else class="w-4 h-4" />
                            <span>{{ isRTL ? 'جلب وتطبيق' : 'Fetch & Apply' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Manual Edit Form -->
            <div v-if="activeTab === 'manual'" class="flex-1 overflow-y-auto space-y-4 pr-1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">{{ isRTL ? 'العنوان الأصلي (English)' : 'Title (English)' }}</label>
                        <input v-model="form.title" type="text" class="w-full bg-slate-900/90 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">{{ isRTL ? 'العنوان بالعربية' : 'Arabic Title' }}</label>
                        <input v-model="form.title_ar" type="text" class="w-full bg-slate-900/90 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500" dir="rtl" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">{{ isRTL ? 'سنة الإصدار' : 'Release Year' }}</label>
                        <input v-model="form.release_year" type="number" class="w-full bg-slate-900/90 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">{{ isRTL ? 'التقييم (0-10)' : 'Rating (0-10)' }}</label>
                        <input v-model="form.rating" type="text" class="w-full bg-slate-900/90 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">{{ isRTL ? 'اسم السلسلة / المجموعة' : 'Collection / Franchise Name' }}</label>
                        <input v-model="form.collection_name" type="text" placeholder="e.g. The Dark Knight Collection" class="w-full bg-slate-900/90 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">{{ isRTL ? 'رابط البوستر (Poster URL)' : 'Poster Image URL' }}</label>
                        <input v-model="form.poster_path" type="text" class="w-full bg-slate-900/90 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500 font-mono text-xs" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">{{ isRTL ? 'رابط الخلفية (Backdrop URL)' : 'Backdrop Image URL' }}</label>
                        <input v-model="form.backdrop_path" type="text" class="w-full bg-slate-900/90 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500 font-mono text-xs" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">{{ isRTL ? 'القصة (English Overview)' : 'English Overview' }}</label>
                        <textarea v-model="form.overview" rows="2" class="w-full bg-slate-900/90 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">{{ isRTL ? 'القصة بالعربية' : 'Arabic Overview' }}</label>
                        <textarea v-model="form.overview_ar" rows="2" class="w-full bg-slate-900/90 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500" dir="rtl"></textarea>
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <button
                        @click="saveManualEdit"
                        :disabled="isSaving"
                        class="px-6 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-sm transition-all flex items-center gap-2 shadow-lg shadow-cyan-500/20 disabled:opacity-50 cursor-pointer"
                    >
                        <Save class="w-4 h-4" />
                        <span>{{ isRTL ? 'حفظ التعديلات' : 'Save Changes' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
