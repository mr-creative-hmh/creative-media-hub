<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    X, Search, Sparkles, Check, Image as ImageIcon, Star, Calendar,
    Film, Tv, RefreshCw, AlertCircle, Save, SlidersHorizontal, Layers,
    Hash, Repeat, ArrowRightLeft, Wand2
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
    emit('close');
};

watch(() => props.item, (newItem) => {
    if (newItem) {
        searchQuery.value = newItem.title || '';
        searchYear.value = newItem.release_year ? String(newItem.release_year) : '';
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
    }
}, { immediate: true });

const isSeriesType = computed(() => {
    return props.type === 'series' || props.item?.type === 'series' || !!props.item?.seasons || !!props.item?.seasons_count;
});

const performSearch = async () => {
    if (!searchQuery.value.trim()) return;

    isSearching.value = true;
    searchError.value = '';
    searchResults.value = [];

    const endpoint = isSeriesType.value
        ? `/api/series/search-metadata?query=${encodeURIComponent(searchQuery.value)}&year=${encodeURIComponent(searchYear.value || '')}`
        : `/api/media/search-metadata?query=${encodeURIComponent(searchQuery.value)}&year=${encodeURIComponent(searchYear.value || '')}`;

    try {
        const res = await fetch(endpoint);
        if (res.ok) {
            const data = await res.json();
            searchResults.value = data.results || [];
            if (searchResults.value.length === 0) {
                searchError.value = isRTL.value ? 'لم يتم العثور على نتائج. جرب كتابة اسم مختلف أو سنة الإنتاج.' : 'No results found. Try refining title or year.';
            }
        } else {
            searchError.value = isRTL.value ? 'فشل البحث في قواعد البيانات العالمية.' : 'Failed to search online providers.';
        }
    } catch (e) {
        searchError.value = isRTL.value ? 'خطأ في الاتصال بالإنترنت أثناء البحث.' : 'Network error during search.';
    } finally {
        isSearching.value = false;
    }
};

const lookupAndApplyDirectId = async () => {
    if (!directIdInput.value.trim()) return;

    isSearching.value = true;
    searchError.value = '';

    try {
        const res = await fetch('/api/metadata/lookup-id', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                id: directIdInput.value.trim(),
                type: isSeriesType.value ? 'series' : 'movie',
            }),
        });

        if (res.ok) {
            const data = await res.json();
            if (data.details) {
                await applyMatch(data.details);
            }
        } else {
            searchError.value = isRTL.value ? 'لم يتم العثور على هذا المعرف (TMDb / IMDb ID).' : 'Invalid or not found TMDb / IMDb ID.';
        }
    } catch (e) {
        searchError.value = isRTL.value ? 'خطأ في جلب بيانات المعرف.' : 'Error fetching ID metadata.';
    } finally {
        isSearching.value = false;
    }
};

const reparseFromFilename = async () => {
    if (!props.item?.id) return;
    isSaving.value = true;
    searchError.value = '';

    const mediaType = isSeriesType.value ? 'series' : 'movie';
    try {
        const res = await fetch(`/api/metadata/${mediaType}/${props.item.id}/reparse`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });

        if (res.ok) {
            const data = await res.json();
            emit('updated', data.item);
            closeModal();
        } else {
            searchError.value = isRTL.value ? 'فشل إعادة تحليل اسم الملف.' : 'Failed to reparse filename.';
        }
    } catch (e) {
        searchError.value = isRTL.value ? 'حدث خطأ أثناء إعادة التحليل.' : 'Error reparsing filename.';
    } finally {
        isSaving.value = false;
    }
};

const convertMediaType = async () => {
    if (!props.item?.id) return;
    isSaving.value = true;
    searchError.value = '';

    const mediaType = isSeriesType.value ? 'series' : 'movie';
    try {
        const res = await fetch(`/api/metadata/${mediaType}/${props.item.id}/convert-type`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });

        if (res.ok) {
            const data = await res.json();
            emit('updated', { id: data.new_id, type: data.new_type, title: props.item.title });
            closeModal();
            window.location.reload();
        } else {
            searchError.value = isRTL.value ? 'فشل تحويل نوع الوسائط.' : 'Failed to convert media type.';
        }
    } catch (e) {
        searchError.value = isRTL.value ? 'حدث خطأ أثناء تحويل الوسائط.' : 'Error converting media type.';
    } finally {
        isSaving.value = false;
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
            id: result.id || result.tmdb_id || null,
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
        <div class="relative w-full max-w-2xl rounded-3xl glass-panel border border-cyan-500/30 bg-[#0B0F19] p-6 lg:p-8 shadow-2xl shadow-cyan-500/10 max-h-[90vh] flex flex-col space-y-6">
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-white/10 pb-4 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-cyan-500/20 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                        <Wand2 class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <span>{{ isRTL ? 'استوديو تصحيح المطابقة ومعالجة الأخطاء' : 'Fix Match & Error Resolution Studio' }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-semibold" :class="isSeriesType ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30'">
                                {{ isSeriesType ? (isRTL ? 'مسلسل' : 'Series') : (isRTL ? 'فيلم' : 'Movie') }}
                            </span>
                        </h3>
                        <p class="text-xs text-slate-400 truncate max-w-md mt-0.5 font-mono">
                            {{ item?.file_path || item?.title }}
                        </p>
                    </div>
                </div>

                <button
                    @click="closeModal"
                    class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Tab Switcher & Quick Utility Buttons -->
            <div class="flex flex-wrap items-center justify-between gap-3 shrink-0">
                <div class="flex items-center p-1 rounded-xl bg-slate-900 border border-white/10">
                    <button
                        @click="activeTab = 'search'"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                        :class="activeTab === 'search' ? 'bg-cyan-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'"
                    >
                        <Search class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'بحث تلقائي' : 'Online Search' }}</span>
                    </button>
                    <button
                        @click="activeTab = 'direct_id'"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                        :class="activeTab === 'direct_id' ? 'bg-cyan-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'"
                    >
                        <Hash class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'مطابقة بالمعرف (ID)' : 'Direct ID Match' }}</span>
                    </button>
                    <button
                        @click="activeTab = 'manual'"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                        :class="activeTab === 'manual' ? 'bg-cyan-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'"
                    >
                        <SlidersHorizontal class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'تعديل يدوي' : 'Manual Edit' }}</span>
                    </button>
                </div>

                <!-- Quick Action Tools -->
                <div class="flex items-center gap-2">
                    <button
                        @click="reparseFromFilename"
                        :disabled="isSaving"
                        class="px-3 py-1.5 rounded-xl bg-white/[0.05] hover:bg-white/[0.1] border border-white/10 text-xs font-bold text-slate-300 hover:text-cyan-300 transition-all flex items-center gap-1.5 cursor-pointer"
                        :title="isRTL ? 'إعادة تحليل اسم الملف بالخوارزمية الذكية المطورة' : 'Re-parse file with updated algorithm'"
                    >
                        <Repeat class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'إعادة تحليل المسار' : 'Re-parse Path' }}</span>
                    </button>
                    <button
                        @click="convertMediaType"
                        :disabled="isSaving"
                        class="px-3 py-1.5 rounded-xl bg-purple-500/20 hover:bg-purple-500/30 border border-purple-500/30 text-xs font-bold text-purple-300 transition-all flex items-center gap-1.5 cursor-pointer"
                        :title="isRTL ? 'تحويل التصنيف من فيلم إلى مسلسل أو العكس' : 'Convert between Movie and Series'"
                    >
                        <ArrowRightLeft class="w-3.5 h-3.5" />
                        <span>{{ isSeriesType ? (isRTL ? 'تحويل لفيلم' : 'To Movie') : (isRTL ? 'تحويل لمسلسل' : 'To Series') }}</span>
                    </button>
                </div>
            </div>

            <!-- Error Notification Banner -->
            <div v-if="searchError" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2">
                <AlertCircle class="w-4 h-4 shrink-0" />
                <span>{{ searchError }}</span>
            </div>

            <!-- Tab 1: Live Online Search -->
            <div v-if="activeTab === 'search'" class="flex-1 overflow-y-auto space-y-4 pr-1">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ isRTL ? 'عنوان الوسائط' : 'Media Title' }}</label>
                        <input
                            v-model="searchQuery"
                            @keyup.enter="performSearch"
                            type="text"
                            placeholder="e.g. Harry Potter and the Chamber of Secrets"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-400 transition-colors"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">{{ isRTL ? 'سنة الإصدار' : 'Release Year' }}</label>
                        <div class="flex items-center gap-2">
                            <input
                                v-model="searchYear"
                                @keyup.enter="performSearch"
                                type="text"
                                placeholder="e.g. 2002"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-400 transition-colors"
                            />
                            <button
                                @click="performSearch"
                                :disabled="isSearching"
                                class="px-4 py-2.5 rounded-xl bg-cyan-500 text-slate-950 font-black text-xs hover:bg-cyan-400 transition-all flex items-center gap-1.5 shrink-0 cursor-pointer disabled:opacity-50"
                            >
                                <RefreshCw v-if="isSearching" class="w-3.5 h-3.5 animate-spin" />
                                <Search v-else class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'بحث' : 'Search' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Search Results List -->
                <div v-if="searchResults.length > 0" class="space-y-2.5 mt-4">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                        {{ isRTL ? 'النتائج المطابقة من قواعد البيانات' : 'Matched Online Candidates' }} ({{ searchResults.length }})
                    </h4>

                    <div
                        v-for="res in searchResults"
                        :key="res.id || res.tmdb_id"
                        class="p-3 rounded-2xl glass-panel border border-white/10 hover:border-cyan-500/40 bg-slate-900/50 hover:bg-slate-900/80 transition-all flex items-center justify-between gap-4"
                    >
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-12 h-16 rounded-lg bg-slate-950 overflow-hidden shrink-0 border border-white/10">
                                <img
                                    v-if="res.poster_path"
                                    :src="res.poster_path"
                                    :alt="res.title"
                                    class="w-full h-full object-cover"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center text-slate-700">
                                    <Film class="w-5 h-5" />
                                </div>
                            </div>

                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h5 class="text-sm font-bold text-white truncate">
                                        {{ isRTL && res.title_ar ? res.title_ar : res.title }}
                                    </h5>
                                    <span v-if="res.release_year || res.year" class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-white/[0.06] text-slate-300">
                                        {{ res.release_year || res.year }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-400 line-clamp-1">
                                    {{ res.overview || res.overview_ar || 'No overview available.' }}
                                </p>
                                <div class="flex items-center gap-3 text-[10px] text-slate-400">
                                    <span class="font-bold text-cyan-400">{{ res.provider || 'TMDb' }}</span>
                                    <span v-if="res.rating" class="text-amber-400 font-bold flex items-center gap-0.5">
                                        <Star class="w-3 h-3 fill-amber-400" />
                                        <span>{{ res.rating }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <button
                            @click="applyMatch(res)"
                            :disabled="isSaving"
                            class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs shrink-0 flex items-center gap-1.5 transition-all shadow-md cursor-pointer disabled:opacity-50"
                        >
                            <Check class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'تطبيق المطابقة' : 'Apply Match' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Direct ID Match (TMDb / IMDb ID) -->
            <div v-else-if="activeTab === 'direct_id'" class="flex-1 space-y-4">
                <div class="p-4 rounded-2xl bg-cyan-950/20 border border-cyan-500/20 space-y-2">
                    <h4 class="text-xs font-bold text-cyan-300 uppercase tracking-wider flex items-center gap-1.5">
                        <Sparkles class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'المطابقة المباشرة برقم المعرف' : 'Instant Direct ID Lookup' }}</span>
                    </h4>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        {{ isRTL 
                            ? 'أدخل رقم المعرف من موقع TheMovieDB (مثل 671) أو IMDb (مثل tt0241527) لجلب وتطبيق البوستر والمعلومات والترجمة العربية فوراً.' 
                            : 'Enter TMDb ID (e.g. 671) or IMDb ID (e.g. tt0241527) to instantly fetch and apply artwork, bilingual plot, and collection tags.' 
                        }}
                    </p>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">TMDb / IMDb ID</label>
                        <div class="flex items-center gap-2">
                            <input
                                v-model="directIdInput"
                                @keyup.enter="lookupAndApplyDirectId"
                                type="text"
                                placeholder="e.g. 671 or tt0241527"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-400 transition-colors font-mono"
                            />
                            <button
                                @click="lookupAndApplyDirectId"
                                :disabled="isSearching"
                                class="px-5 py-2.5 rounded-xl bg-cyan-500 text-slate-950 font-black text-xs hover:bg-cyan-400 transition-all flex items-center gap-1.5 shrink-0 cursor-pointer disabled:opacity-50"
                            >
                                <RefreshCw v-if="isSearching" class="w-3.5 h-3.5 animate-spin" />
                                <Wand2 v-else class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'جلب وتطبيق' : 'Fetch & Apply' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Manual Metadata Edit -->
            <div v-else-if="activeTab === 'manual'" class="flex-1 overflow-y-auto space-y-4 pr-1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">{{ isRTL ? 'العنوان الأصلي (إنجليزي)' : 'Title (English)' }}</label>
                        <input
                            v-model="form.title"
                            type="text"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-400"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">{{ isRTL ? 'العنوان العربي' : 'Title (Arabic)' }}</label>
                        <input
                            v-model="form.title_ar"
                            type="text"
                            dir="rtl"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-400"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">{{ isRTL ? 'سنة الإصدار' : 'Release Year' }}</label>
                        <input
                            v-model="form.release_year"
                            type="number"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-400"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">{{ isRTL ? 'التقييم (1-10)' : 'Rating (1-10)' }}</label>
                        <input
                            v-model="form.rating"
                            type="number"
                            step="0.1"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-400"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-300 mb-1">
                            {{ isRTL ? 'اسم سلسلة الأفلام (Collection / Boxset)' : 'Collection / Boxset Name' }}
                        </label>
                        <input
                            v-model="form.collection_name"
                            type="text"
                            placeholder="e.g. Harry Potter Collection"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-400"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-300 mb-1">{{ isRTL ? 'رابط البوستر (Poster URL)' : 'Poster Image URL' }}</label>
                        <input
                            v-model="form.poster_path"
                            type="text"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-400 font-mono"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-300 mb-1">{{ isRTL ? 'نبذة عن العمل (عربي)' : 'Overview (Arabic)' }}</label>
                        <textarea
                            v-model="form.overview_ar"
                            rows="2"
                            dir="rtl"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-400"
                        ></textarea>
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <button
                        @click="saveManualEdit"
                        :disabled="isSaving"
                        class="px-6 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs flex items-center gap-2 shadow-lg shadow-cyan-500/20 active:scale-95 transition-all cursor-pointer disabled:opacity-50"
                    >
                        <Save class="w-4 h-4" />
                        <span>{{ isRTL ? 'حفظ التعديلات' : 'Save Changes' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
