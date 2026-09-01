<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    X, Search, Sparkles, Check, Image as ImageIcon, Star, Calendar,
    Film, Tv, RefreshCw, AlertCircle, Save, SlidersHorizontal, Layers
} from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    item: any;
    type?: 'movie' | 'series';
}>();

const emit = defineEmits(['close', 'updated']);

const { isRTL, t } = useI18n();

const activeTab = ref<'search' | 'manual'>('search');
const searchQuery = ref('');
const searchYear = ref<string>('');
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
        form.value = {
            title: newItem.title || '',
            title_ar: newItem.title_ar || '',
            release_year: newItem.release_year ? String(newItem.release_year) : '',
            rating: newItem.rating ? String(newItem.rating) : '7.5',
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
    return props.type === 'series' || !!props.item?.seasons || !!props.item?.seasons_count;
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
                searchError.value = isRTL.value ? 'لم يتم العثور على نتائج. جرب كتابة الاسم بدقة أكثر.' : 'No results found. Try refining title or year.';
            }
        } else {
            searchError.value = isRTL.value ? 'فشل البحث في مزودات البيانات.' : 'Failed to search online providers.';
        }
    } catch (e) {
        searchError.value = isRTL.value ? 'خطأ في الاتصال أثناء البحث.' : 'Network error during search.';
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
            searchError.value = isRTL.value ? 'فشل تطبيق المطابقة وحفظ البيانات.' : 'Failed to apply match.';
        }
    } catch (e) {
        searchError.value = isRTL.value ? 'خطأ غير متوقع أثناء الحفظ.' : 'Error saving matched data.';
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
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify(form.value),
        });

        if (res.ok) {
            const data = await res.json();
            const updated = data.media || data.series;
            emit('updated', updated);
            closeModal();
        } else {
            searchError.value = isRTL.value ? 'فشل حفظ التعديلات اليدوية.' : 'Failed to save manual changes.';
        }
    } catch (e) {
        searchError.value = isRTL.value ? 'خطأ في الاتصال أثناء حفظ البيانات.' : 'Error saving metadata.';
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <div
        v-if="show"
        @click.self="closeModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/85 backdrop-blur-md animate-in fade-in duration-200"
    >
        <div
            class="glass-panel w-full max-w-2xl max-h-[90vh] rounded-3xl border border-white/15 bg-[#0E121E] shadow-2xl flex flex-col overflow-hidden text-white font-sans"
            :dir="isRTL ? 'rtl' : 'ltr'"
        >
            <!-- Header -->
            <div class="p-6 border-b border-white/10 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
                        <Sparkles class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-lg font-black tracking-tight text-white flex items-center gap-2">
                            <span>{{ isRTL ? 'استوديو تصحيح المطابقة والبيانات' : 'Fix Match & Metadata Studio' }}</span>
                            <span class="text-[10px] px-2 py-0.5 rounded-md font-bold uppercase tracking-wider" :class="isSeriesType ? 'bg-indigo-500/20 text-indigo-300' : 'bg-cyan-500/20 text-cyan-300'">
                                {{ isSeriesType ? (isRTL ? 'مسلسل' : 'Series') : (isRTL ? 'فيلم' : 'Movie') }}
                            </span>
                        </h3>
                        <p class="text-xs text-slate-400 truncate max-w-sm">
                            {{ item?.title }}
                            <span v-if="item?.title_ar" class="text-slate-500 mr-1">({{ item?.title_ar }})</span>
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click.stop="closeModal"
                    class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center text-slate-300 transition-colors cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Tab Switcher -->
            <div class="px-6 pt-4 flex gap-2 border-b border-white/10">
                <button
                    type="button"
                    @click="activeTab = 'search'"
                    class="pb-3 px-4 text-xs font-bold transition-all relative cursor-pointer"
                    :class="activeTab === 'search' ? 'text-cyan-400' : 'text-slate-400 hover:text-slate-200'"
                >
                    <span class="flex items-center gap-1.5">
                        <Search class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'بحث سحابي (TMDb / OMDb)' : 'Search Online Providers' }}</span>
                    </span>
                    <span v-if="activeTab === 'search'" class="absolute bottom-0 inset-x-0 h-0.5 bg-cyan-500 rounded-full"></span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'manual'"
                    class="pb-3 px-4 text-xs font-bold transition-all relative cursor-pointer"
                    :class="activeTab === 'manual' ? 'text-cyan-400' : 'text-slate-400 hover:text-slate-200'"
                >
                    <span class="flex items-center gap-1.5">
                        <SlidersHorizontal class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'تعديل البيانات يدوياً' : 'Manual Field Editor' }}</span>
                    </span>
                    <span v-if="activeTab === 'manual'" class="absolute bottom-0 inset-x-0 h-0.5 bg-cyan-500 rounded-full"></span>
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 overflow-y-auto max-h-[60vh] space-y-4 custom-scrollbar">
                <div v-if="searchError" class="p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center gap-2">
                    <AlertCircle class="w-4 h-4 shrink-0" />
                    <span>{{ searchError }}</span>
                </div>

                <!-- 1. Search Online Tab -->
                <div v-if="activeTab === 'search'" class="space-y-4">
                    <form @submit.prevent="performSearch" class="flex gap-2">
                        <div class="relative flex-1">
                            <input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="isRTL ? 'اسم الفيلم أو المسلسل...' : 'Movie or TV Show title...'"
                                class="w-full px-4 py-2.5 rounded-2xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 transition-colors"
                            />
                        </div>
                        <input
                            v-model="searchYear"
                            type="text"
                            :placeholder="isRTL ? 'السنة' : 'Year'"
                            class="w-20 px-3 py-2.5 rounded-2xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 text-center focus:outline-none focus:border-cyan-500 transition-colors"
                        />
                        <button
                            type="submit"
                            :disabled="isSearching || !searchQuery.trim()"
                            class="px-5 py-2.5 rounded-2xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs flex items-center gap-2 transition-all active:scale-95 disabled:opacity-50 cursor-pointer shadow-lg shadow-cyan-500/20"
                        >
                            <RefreshCw v-if="isSearching" class="w-3.5 h-3.5 animate-spin" />
                            <Search v-else class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'بحث' : 'Search' }}</span>
                        </button>
                    </form>

                    <!-- Search Results Cards -->
                    <div v-if="searchResults.length > 0" class="space-y-2.5 pt-2">
                        <div
                            v-for="res in searchResults"
                            :key="res.id || res.tmdb_id || res.imdb_id"
                            class="p-3.5 rounded-2xl bg-white/5 border border-white/10 hover:border-cyan-500/50 transition-all flex items-center justify-between gap-4 group"
                        >
                            <div class="flex items-center gap-3.5 min-w-0">
                                <img
                                    :src="res.poster_path || '/placeholder.jpg'"
                                    :alt="res.title"
                                    class="w-12 h-16 rounded-xl object-cover bg-slate-900 border border-white/10 shrink-0 shadow-md"
                                />
                                <div class="min-w-0 space-y-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="cinema-badge bg-cyan-500/20 text-cyan-300 border-cyan-500/30 text-[10px]">
                                            {{ res.provider?.toUpperCase() || 'TMDB' }}
                                        </span>
                                        <span v-if="res.release_year || res.year" class="cinema-badge bg-white/10 text-slate-300 border-white/10 text-[10px]">
                                            {{ res.release_year || res.year }}
                                        </span>
                                        <span v-if="res.rating" class="cinema-badge bg-amber-500/20 text-amber-300 border-amber-500/30 text-[10px] flex items-center gap-0.5">
                                            <Star class="w-3 h-3 fill-current" />
                                            {{ res.rating }}
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-xs text-white truncate max-w-sm">
                                        {{ res.title }}
                                        <span v-if="res.title_ar" class="text-cyan-400 font-semibold text-[11px]">({{ res.title_ar }})</span>
                                    </h4>
                                    <p class="text-[11px] text-slate-400 line-clamp-1 max-w-md">
                                        {{ res.overview_ar || res.overview || (isRTL ? 'لا يتوفر وصف موجز.' : 'No overview provided.') }}
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="applyMatch(res)"
                                :disabled="isSaving"
                                class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs shrink-0 flex items-center gap-1.5 transition-all active:scale-95 cursor-pointer shadow-md shadow-cyan-500/20"
                            >
                                <Check class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'تطبيق المطابقة' : 'Apply Match' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 2. Manual Field Editor Tab -->
                <div v-else class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[11px] font-bold text-slate-400">{{ isRTL ? 'الاسم بالإنجليزية' : 'Title (English)' }}</label>
                            <input
                                v-model="form.title"
                                type="text"
                                class="w-full px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-500"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[11px] font-bold text-slate-400">{{ isRTL ? 'الاسم بالعربية' : 'Arabic Title' }}</label>
                            <input
                                v-model="form.title_ar"
                                type="text"
                                class="w-full px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[11px] font-bold text-slate-400">{{ isRTL ? 'سنة الإنتاج' : 'Release Year' }}</label>
                            <input
                                v-model="form.release_year"
                                type="number"
                                class="w-full px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-500"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[11px] font-bold text-slate-400">{{ isRTL ? 'التقييم (من 10)' : 'Rating (/10)' }}</label>
                            <input
                                v-model="form.rating"
                                type="text"
                                class="w-full px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-500"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-slate-400">{{ isRTL ? 'نبذة الفيلم / المسلسل (إنجليزية)' : 'Overview (English)' }}</label>
                        <textarea
                            v-model="form.overview"
                            rows="2"
                            class="w-full px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-500"
                        ></textarea>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-slate-400">{{ isRTL ? 'القصة / الوصف بالعربية' : 'Arabic Overview' }}</label>
                        <textarea
                            v-model="form.overview_ar"
                            rows="2"
                            dir="rtl"
                            class="w-full px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-500 text-right font-sans"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[11px] font-bold text-slate-400">{{ isRTL ? 'رابط البوستر (Poster URL)' : 'Poster URL' }}</label>
                            <input
                                v-model="form.poster_path"
                                type="text"
                                class="w-full px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-500 text-left font-mono"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[11px] font-bold text-slate-400">{{ isRTL ? 'رابط الخلفية (Backdrop URL)' : 'Backdrop URL' }}</label>
                            <input
                                v-model="form.backdrop_path"
                                type="text"
                                class="w-full px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-500 text-left font-mono"
                            />
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button
                            type="button"
                            @click="saveManualEdit"
                            :disabled="isSaving"
                            class="px-6 py-2.5 rounded-2xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs flex items-center gap-2 transition-all active:scale-95 cursor-pointer shadow-lg shadow-cyan-500/20"
                        >
                            <Save class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'حفظ التعديلات' : 'Save Changes' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 9999px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.25);
}
</style>
