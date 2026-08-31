<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    X, Search, Sparkles, Check, Image, Star, Calendar,
    Film, Tv, RefreshCw, AlertCircle, Save, SlidersHorizontal
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

const performSearch = async () => {
    if (!searchQuery.value.trim()) return;

    isSearching.value = true;
    searchError.value = '';
    searchResults.value = [];

    const endpoint = (props.type === 'series' || props.item?.seasons)
        ? `/api/series/search-metadata?query=${encodeURIComponent(searchQuery.value)}&year=${encodeURIComponent(searchYear.value)}`
        : `/api/media/search-metadata?query=${encodeURIComponent(searchQuery.value)}&year=${encodeURIComponent(searchYear.value)}`;

    try {
        const res = await fetch(endpoint);
        const data = await res.json();
        searchResults.value = data.results || [];
        if (searchResults.value.length === 0) {
            searchError.value = isRTL.value ? 'لم يتم العثور على نتائج. جرب تغيير كلمة البحث أو سنة الإصدار.' : 'No results found. Try adjusting the search query or year.';
        }
    } catch (e: any) {
        searchError.value = e.message || 'Search request failed.';
    } finally {
        isSearching.value = false;
    }
};

const applyMatch = async (result: any) => {
    isSaving.value = true;
    successMessage.value = '';

    const isSeries = props.type === 'series' || props.item?.seasons;
    const endpoint = isSeries
        ? `/api/series/${props.item.id}/fix-match`
        : `/api/media/${props.item.id}/fix-match`;

    try {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                title: result.title,
                provider: result.provider || (isSeries ? 'tvmaze' : 'tmdb'),
                id: result.id || result.tmdb_id || result.tvmaze_id,
                year: result.year,
                overview: result.overview,
                overview_ar: result.overview_ar,
                poster_path: result.poster_path,
                backdrop_path: result.backdrop_path,
                rating: result.rating,
                runtime_minutes: result.runtime_minutes,
            }),
        });

        const data = await res.json();
        if (data.success) {
            successMessage.value = isRTL.value ? 'تم تثبيت وتحديث البيانات والغلاف بنجاح!' : 'Metadata & artwork successfully matched!';
            emit('updated', data.media || data.series);
            setTimeout(() => {
                emit('close');
            }, 1200);
        }
    } catch (e: any) {
        searchError.value = e.message || 'Failed to apply match.';
    } finally {
        isSaving.value = false;
    }
};

const saveManualEdit = async () => {
    isSaving.value = true;
    searchError.value = '';
    successMessage.value = '';

    const isSeries = props.type === 'series' || props.item?.seasons;
    const endpoint = isSeries
        ? `/api/series/${props.item.id}/update-metadata`
        : `/api/media/${props.item.id}/update-metadata`;

    try {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                title: form.value.title,
                title_ar: form.value.title_ar,
                release_year: form.value.release_year ? parseInt(form.value.release_year) : null,
                rating: form.value.rating ? parseFloat(form.value.rating) : 7.5,
                overview: form.value.overview,
                overview_ar: form.value.overview_ar,
                poster_path: form.value.poster_path,
                backdrop_path: form.value.backdrop_path,
            }),
        });

        const data = await res.json();
        if (data.success) {
            successMessage.value = isRTL.value ? 'تم حفظ التعديلات اليدوية بنجاح!' : 'Manual metadata saved successfully!';
            emit('updated', data.media || data.series);
            setTimeout(() => {
                emit('close');
            }, 1200);
        }
    } catch (e: any) {
        searchError.value = e.message || 'Failed to save edits.';
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-in fade-in duration-200"
    >
        <div
            class="glass-panel w-full max-w-2xl max-h-[90vh] rounded-3xl border border-slate-200 dark:border-white/15 bg-white dark:bg-[#0E121E] shadow-2xl flex flex-col overflow-hidden text-slate-900 dark:text-white font-sans"
            :dir="isRTL ? 'rtl' : 'ltr'"
        >
            <!-- Header -->
            <div class="p-6 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 flex items-center justify-center">
                        <Sparkles class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-lg font-black tracking-tight">
                            {{ isRTL ? 'إدارة البيانات وتغيير الغلاف (Fix Match)' : 'Fix Match & Metadata Studio' }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate max-w-sm">
                            {{ item?.title }}
                        </p>
                    </div>
                </div>
                <button
                    @click="emit('close')"
                    class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 flex items-center justify-center text-slate-600 dark:text-slate-300 transition-colors cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Tab Switcher -->
            <div class="px-6 pt-4 flex gap-2 border-b border-slate-200 dark:border-white/10">
                <button
                    @click="activeTab = 'search'"
                    class="pb-3 px-4 text-xs font-bold transition-all relative cursor-pointer"
                    :class="activeTab === 'search' ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                >
                    <span class="flex items-center gap-1.5">
                        <Search class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'البحث التلقائي (TMDb / TVMaze)' : 'Search Online Providers' }}</span>
                    </span>
                    <span v-if="activeTab === 'search'" class="absolute bottom-0 inset-x-0 h-0.5 bg-cyan-500 rounded-full"></span>
                </button>
                <button
                    @click="activeTab = 'manual'"
                    class="pb-3 px-4 text-xs font-bold transition-all relative cursor-pointer"
                    :class="activeTab === 'manual' ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                >
                    <span class="flex items-center gap-1.5">
                        <SlidersHorizontal class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'التعديل اليدوي الكامل' : 'Manual Field Editor' }}</span>
                    </span>
                    <span v-if="activeTab === 'manual'" class="absolute bottom-0 inset-x-0 h-0.5 bg-cyan-500 rounded-full"></span>
                </button>
            </div>

            <!-- Success Alert -->
            <div v-if="successMessage" class="m-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-bold flex items-center gap-2">
                <Check class="w-4 h-4 shrink-0" />
                <span>{{ successMessage }}</span>
            </div>

            <!-- Error Alert -->
            <div v-if="searchError" class="m-6 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs font-bold flex items-center gap-2">
                <AlertCircle class="w-4 h-4 shrink-0" />
                <span>{{ searchError }}</span>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                <!-- TAB 1: Search Online -->
                <div v-if="activeTab === 'search'" class="space-y-4">
                    <form @submit.prevent="performSearch" class="flex gap-2">
                        <div class="relative flex-1">
                            <input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="isRTL ? 'اسم الفيلم أو المسلسل...' : 'Movie or TV Series Title...'"
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-white/15 bg-slate-50 dark:bg-slate-900/50 text-sm focus:outline-none focus:border-cyan-500"
                            />
                            <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        </div>
                        <input
                            v-model="searchYear"
                            type="text"
                            placeholder="Year"
                            class="w-24 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/15 bg-slate-50 dark:bg-slate-900/50 text-sm text-center focus:outline-none focus:border-cyan-500"
                        />
                        <button
                            type="submit"
                            :disabled="isSearching"
                            class="btn-cinema bg-cyan-500 hover:bg-cyan-400 text-slate-950 px-5 py-2.5 rounded-xl text-xs font-bold flex items-center gap-1.5 shrink-0 cursor-pointer"
                        >
                            <RefreshCw v-if="isSearching" class="w-3.5 h-3.5 animate-spin" />
                            <Search v-else class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'بحث' : 'Search' }}</span>
                        </button>
                    </form>

                    <!-- Results List -->
                    <div v-if="searchResults.length > 0" class="space-y-3 pt-2">
                        <div
                            v-for="res in searchResults"
                            :key="res.id || res.tmdb_id"
                            class="p-3.5 rounded-2xl glass-panel border border-slate-200 dark:border-white/10 hover:border-cyan-500/50 transition-all flex gap-4 items-center justify-between group"
                        >
                            <div class="flex items-center gap-3.5 min-w-0">
                                <img
                                    :src="res.poster_path || '/placeholder.jpg'"
                                    :alt="res.title"
                                    class="w-12 h-16 rounded-xl object-cover bg-slate-800 shrink-0 border border-slate-200 dark:border-white/10"
                                />
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-sm truncate">{{ res.title }}</h4>
                                        <span v-if="res.year" class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-white/10 text-slate-500 dark:text-slate-400">
                                            {{ res.year }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 mt-0.5 leading-relaxed">
                                        {{ res.overview || 'No overview available.' }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-1 text-[10px] text-cyan-600 dark:text-cyan-400 font-mono">
                                        <span>Provider: {{ res.provider?.toUpperCase() || 'TMDB' }}</span>
                                        <span v-if="res.rating">★ {{ res.rating }}</span>
                                    </div>
                                </div>
                            </div>

                            <button
                                @click="applyMatch(res)"
                                :disabled="isSaving"
                                class="btn-cinema bg-cyan-500 hover:bg-cyan-400 text-slate-950 px-4 py-2 rounded-xl text-xs font-bold shrink-0 cursor-pointer shadow-sm hover:scale-105 transition-transform"
                            >
                                <Check class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'اختيار ومطابقة' : 'Apply Match' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: Manual Field Editor -->
                <div v-else class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-400">{{ isRTL ? 'العنوان الرئيسي (English)' : 'Title (English)' }}</label>
                            <input
                                v-model="form.title"
                                type="text"
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-white/15 bg-slate-50 dark:bg-slate-900/50 text-sm focus:outline-none focus:border-cyan-500"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-400">{{ isRTL ? 'العنوان بالعربية' : 'Arabic Title' }}</label>
                            <input
                                v-model="form.title_ar"
                                type="text"
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-white/15 bg-slate-50 dark:bg-slate-900/50 text-sm focus:outline-none focus:border-cyan-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-400">{{ isRTL ? 'سنة الإصدار' : 'Release Year' }}</label>
                            <input
                                v-model="form.release_year"
                                type="number"
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-white/15 bg-slate-50 dark:bg-slate-900/50 text-sm focus:outline-none focus:border-cyan-500"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-400">{{ isRTL ? 'التقييم (من 10)' : 'Rating (out of 10)' }}</label>
                            <input
                                v-model="form.rating"
                                type="text"
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-white/15 bg-slate-50 dark:bg-slate-900/50 text-sm focus:outline-none focus:border-cyan-500"
                            />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-400">{{ isRTL ? 'رابط أو مسار صورة البوستر (Poster Image URL)' : 'Poster Image URL / Path' }}</label>
                        <input
                            v-model="form.poster_path"
                            type="text"
                            placeholder="https://... or /storage/posters/..."
                            class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-white/15 bg-slate-50 dark:bg-slate-900/50 text-sm focus:outline-none focus:border-cyan-500"
                        />
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-400">{{ isRTL ? 'رابط أو مسار خلفية العرض (Backdrop Image URL)' : 'Backdrop Image URL / Path' }}</label>
                        <input
                            v-model="form.backdrop_path"
                            type="text"
                            placeholder="https://... or /storage/backdrops/..."
                            class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-white/15 bg-slate-50 dark:bg-slate-900/50 text-sm focus:outline-none focus:border-cyan-500"
                        />
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-400">{{ isRTL ? 'نبذة عن القصة (Overview)' : 'Overview / Synopsis' }}</label>
                        <textarea
                            v-model="form.overview"
                            rows="3"
                            class="w-full p-3 rounded-xl border border-slate-200 dark:border-white/15 bg-slate-50 dark:bg-slate-900/50 text-sm focus:outline-none focus:border-cyan-500"
                        ></textarea>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            @click="saveManualEdit"
                            :disabled="isSaving"
                            class="btn-cinema bg-cyan-500 hover:bg-cyan-400 text-slate-950 px-6 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 cursor-pointer shadow-lg shadow-cyan-500/20"
                        >
                            <Save class="w-4 h-4" />
                            <span>{{ isRTL ? 'حفظ التعديلات' : 'Save Changes' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
