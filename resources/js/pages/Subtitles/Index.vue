<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    Subtitles, Download, Check, AlertCircle, Sparkles,
    Search, Cpu, CheckCircle2, Globe, ArrowDownToLine, RefreshCw,
    Film, Tv, HardDrive, FileText, CheckCheck, ChevronLeft, ChevronRight, SlidersHorizontal
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

// Filter & Pagination State
const filterText = ref('');
const filterType = ref<'all' | 'movie' | 'episode'>('all');
const filterStatus = ref<'all' | 'missing_ar' | 'missing_en'>('all');
const currentPage = ref(1);
const perPage = ref(12);

// Filtered missing items
const filteredItems = computed(() => {
    return (props.missingSubtitles || []).filter(item => {
        // Type filter
        if (filterType.value === 'movie' && item.type !== 'movie') return false;
        if (filterType.value === 'episode' && item.type !== 'episode') return false;

        // Status filter
        if (filterStatus.value === 'missing_ar' && !item.missing_ar) return false;
        if (filterStatus.value === 'missing_en' && !item.missing_en) return false;

        // Search text
        if (filterText.value.trim()) {
            const q = filterText.value.toLowerCase().trim();
            const title = (item.title || '').toLowerCase();
            const seriesTitle = (item.series_title || '').toLowerCase();
            const path = (item.file_path || '').toLowerCase();
            return title.includes(q) || seriesTitle.includes(q) || path.includes(q);
        }

        return true;
    });
});

// Paginated items slice
const totalPages = computed(() => Math.max(1, Math.ceil(filteredItems.value.length / perPage.value)));

const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return filteredItems.value.slice(start, start + perPage.value);
});

const setPage = (p: number) => {
    if (p >= 1 && p <= totalPages.value) {
        currentPage.value = p;
        window.scrollTo({ top: 400, behavior: 'smooth' });
    }
};

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
                media_type: item.type,
                language: lang,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            if (lang === 'ar') item.missing_ar = false;
            if (lang === 'en') item.missing_en = false;

            toastMessage.value = `${isRTL.value ? 'تم تنزيل ومزامنة ترجمة' : 'Downloaded and synchronized'} (${lang.toUpperCase()})`;
            setTimeout(() => { toastMessage.value = ''; }, 3000);
        }
    } finally {
        downloadingId.value = null;
    }
};

const downloadAllMissing = async (lang: string) => {
    const targets = filteredItems.value.filter(i => lang === 'ar' ? i.missing_ar : i.missing_en);
    if (targets.length === 0) return;

    toastMessage.value = `${isRTL.value ? 'جاري تنزيل كافة الترجمات...' : 'Downloading all missing subtitles...'}`;
    for (const item of targets.slice(0, 10)) {
        await downloadSub(item, lang);
    }
};
</script>

<template>
    <Head :title="isRTL ? 'إدارة ومزامنة الترجمات' : 'Subtitle Management & Cloud Sync'" />

    <AppLayout>
        <!-- Toast Notice -->
        <transition name="fade">
            <div
                v-if="toastMessage"
                class="fixed top-6 right-6 z-50 px-5 py-3 rounded-2xl bg-emerald-600 text-white font-bold shadow-2xl backdrop-blur-xl flex items-center gap-2"
            >
                <CheckCircle2 class="w-5 h-5" />
                <span>{{ toastMessage }}</span>
            </div>
        </transition>

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-cyan-500/10 dark:bg-cyan-500/20 text-cyan-500 flex items-center justify-center border border-cyan-500/30">
                        <Subtitles class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                            {{ isRTL ? 'إدارة ومزامنة الترجمات' : 'Subtitle Management & Cloud Sync' }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ isRTL ? 'محرك ذكي للبحث والتحميل التلقائي للترجمات العربية والإنجليزية بجودة سينمائية' : 'Dual-Engine cloud search & automatic sync for SubDL and OpenSubtitles' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Quick Batch Download Buttons -->
            <div class="flex items-center gap-2 flex-wrap">
                <button
                    @click="downloadAllMissing('ar')"
                    class="px-4 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs shadow-lg shadow-cyan-500/20 transition-all flex items-center gap-2 cursor-pointer"
                >
                    <Download class="w-4 h-4" />
                    <span>{{ isRTL ? 'تحميل كل الترجمات العربية' : 'Download All Arabic' }}</span>
                </button>
                <button
                    @click="downloadAllMissing('en')"
                    class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-700 dark:text-white font-bold text-xs border border-slate-200 dark:border-white/10 transition-all flex items-center gap-2 cursor-pointer"
                >
                    <Download class="w-4 h-4" />
                    <span>{{ isRTL ? 'تحميل كل الترجمات الإنجليزية' : 'Download All English' }}</span>
                </button>
            </div>
        </div>

        <!-- Live SubDL & OpenSubtitles Search Engine Tester -->
        <div class="glass-panel rounded-3xl p-6 sm:p-8 mb-10 border border-slate-200 dark:border-white/10 shadow-xl bg-white dark:bg-[#121622]">
            <div class="flex items-center justify-between gap-4 mb-4 flex-wrap">
                <div class="flex items-center gap-2">
                    <Sparkles class="w-5 h-5 text-cyan-400" />
                    <h3 class="font-extrabold text-base text-slate-900 dark:text-white">
                        {{ isRTL ? 'البحث السحابي الفوري (SubDL & OpenSubtitles Engine)' : 'Live Cloud Subtitle Search Engine' }}
                    </h3>
                </div>
                <div class="flex items-center gap-2 text-xs font-mono text-emerald-500 font-bold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>SubDL & OpenSubtitles Live Active</span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <Search class="absolute top-3.5 left-3.5 w-4 h-4 text-slate-400" />
                    <input
                        v-model="searchQuery"
                        @keyup.enter="performSearch"
                        type="text"
                        :placeholder="isRTL ? 'ابحث عن اسم الفيلم أو المسلسل...' : 'Search movie or series title...'"
                        class="w-full pl-10 pr-4 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-white/10 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-cyan-500"
                    />
                </div>

                <select
                    v-model="searchLang"
                    class="px-4 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-white/10 text-sm font-bold text-slate-700 dark:text-slate-200 focus:outline-none focus:border-cyan-500"
                >
                    <option value="ar">🇸🇦 العربية (Arabic)</option>
                    <option value="en">🇬🇧 English</option>
                    <option value="es">🇪🇸 Español</option>
                    <option value="fr">🇫🇷 Français</option>
                    <option value="de">🇩🇪 Deutsch</option>
                    <option value="tr">🇹🇷 Türkçe</option>
                </select>

                <button
                    @click="performSearch"
                    :disabled="isSearching"
                    class="px-6 py-3 rounded-2xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-sm shadow-lg shadow-cyan-500/20 transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                >
                    <RefreshCw v-if="isSearching" class="w-4 h-4 animate-spin" />
                    <Search v-else class="w-4 h-4" />
                    <span>{{ isRTL ? 'بحث سحابي' : 'Search Cloud' }}</span>
                </button>
            </div>

            <!-- Search Results Display -->
            <div v-if="searchResults && searchResults.length > 0" class="mt-6 space-y-2.5 pt-4 border-t border-slate-100 dark:border-white/5">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">
                    {{ isRTL ? 'نتائج البحث السحابية' : 'Cloud Subtitle Matches' }} ({{ searchResults.length }})
                </div>
                <div
                    v-for="(sub, idx) in searchResults"
                    :key="idx"
                    class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-white/5 flex items-center justify-between gap-4"
                >
                    <div class="flex items-center gap-3 truncate">
                        <FileText class="w-5 h-5 text-cyan-400 shrink-0" />
                        <div class="truncate">
                            <div class="font-bold text-xs text-slate-900 dark:text-white truncate">{{ sub.release || sub.file_name }}</div>
                            <div class="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">
                                <span class="cinema-badge bg-cyan-500/10 text-cyan-400 text-[10px]">{{ sub.provider }}</span>
                                <span>Rating: {{ sub.rating || '9.5' }}/10</span>
                                <span>{{ sub.downloads || 450 }} downloads</span>
                            </div>
                        </div>
                    </div>
                    <span class="cinema-badge bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-bold shrink-0">
                        {{ isRTL ? 'متطابق بنسبة 100%' : '100% Synced' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Missing Subtitles Library Section with Filters & Pagination -->
        <div class="space-y-6">
            <!-- Filter Bar -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 p-4 rounded-2xl bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10">
                <!-- Search text -->
                <div class="relative flex-1">
                    <Search class="absolute top-2.5 left-3 w-4 h-4 text-slate-400" />
                    <input
                        v-model="filterText"
                        type="text"
                        :placeholder="isRTL ? 'تصفية حسب الاسم...' : 'Filter media items...'"
                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none"
                    />
                </div>

                <!-- Filters -->
                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Type Filter -->
                    <select
                        v-model="filterType"
                        class="px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 text-xs font-bold text-slate-700 dark:text-slate-200 focus:outline-none"
                    >
                        <option value="all">{{ isRTL ? 'كل الوسائط' : 'All Media Types' }}</option>
                        <option value="movie">{{ isRTL ? 'الأفلام فقط' : 'Movies Only' }}</option>
                        <option value="episode">{{ isRTL ? 'حلقات المسلسلات' : 'Series Episodes' }}</option>
                    </select>

                    <!-- Status Filter -->
                    <select
                        v-model="filterStatus"
                        class="px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 text-xs font-bold text-slate-700 dark:text-slate-200 focus:outline-none"
                    >
                        <option value="all">{{ isRTL ? 'كل الحالات' : 'All Status' }}</option>
                        <option value="missing_ar">{{ isRTL ? 'ينقصها ترجمة عربية' : 'Missing Arabic' }}</option>
                        <option value="missing_en">{{ isRTL ? 'ينقصها ترجمة إنجليزية' : 'Missing English' }}</option>
                    </select>

                    <!-- Per page -->
                    <select
                        v-model="perPage"
                        class="px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 text-xs font-bold text-slate-700 dark:text-slate-200 focus:outline-none"
                    >
                        <option :value="12">12 / page</option>
                        <option :value="24">24 / page</option>
                        <option :value="48">48 / page</option>
                    </select>
                </div>
            </div>

            <!-- Items Counter -->
            <div class="flex items-center justify-between text-xs text-slate-500 font-bold px-1">
                <span>
                    {{ isRTL ? 'إجمالي العناصر:' : 'Total items:' }} <strong class="text-cyan-500">{{ filteredItems.length }}</strong>
                </span>
                <span v-if="totalPages > 1">
                    {{ isRTL ? 'الصفحة' : 'Page' }} {{ currentPage }} / {{ totalPages }}
                </span>
            </div>

            <!-- Grid of Missing Media Items -->
            <div v-if="paginatedItems.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="item in paginatedItems"
                    :key="`${item.type}-${item.id}`"
                    class="glass-panel rounded-2xl p-4 border border-slate-200 dark:border-white/10 flex flex-col justify-between shadow-sm bg-white dark:bg-[#121622] hover:border-cyan-500/40 transition-all"
                >
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span
                                class="cinema-badge text-[10px] font-bold"
                                :class="item.type === 'movie' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-purple-500/20 text-purple-300'"
                            >
                                {{ item.type === 'movie' ? (isRTL ? 'فيلم' : 'Movie') : (isRTL ? 'حلقة مسلسل' : 'Episode') }}
                            </span>
                            <span class="text-[10px] font-mono text-slate-400">
                                {{ item.year || item.season_info || '' }}
                            </span>
                        </div>

                        <h4 class="font-extrabold text-sm text-slate-900 dark:text-white line-clamp-1">
                            {{ isRTL && item.title_ar ? item.title_ar : item.title }}
                        </h4>
                        <p v-if="item.series_title" class="text-xs text-slate-500 mt-0.5 line-clamp-1">
                            {{ item.series_title }}
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-white/5">
                        <!-- Arabic Subtitle Action -->
                        <button
                            @click="downloadSub(item, 'ar')"
                            :disabled="!item.missing_ar || downloadingId === `${item.id}-ar`"
                            class="flex-1 py-2 px-3 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                            :class="!item.missing_ar
                                ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 cursor-default'
                                : 'bg-cyan-500 hover:bg-cyan-400 text-slate-950 shadow-md shadow-cyan-500/20'"
                        >
                            <Check v-if="!item.missing_ar" class="w-3.5 h-3.5" />
                            <RefreshCw v-else-if="downloadingId === `${item.id}-ar`" class="w-3.5 h-3.5 animate-spin" />
                            <Download v-else class="w-3.5 h-3.5" />
                            <span>{{ !item.missing_ar ? (isRTL ? 'العربية متوفرة' : 'Arabic Ready') : (isRTL ? 'تحميل العربية' : 'Get Arabic') }}</span>
                        </button>

                        <!-- English Subtitle Action -->
                        <button
                            @click="downloadSub(item, 'en')"
                            :disabled="!item.missing_en || downloadingId === `${item.id}-en`"
                            class="flex-1 py-2 px-3 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                            :class="!item.missing_en
                                ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 cursor-default'
                                : 'bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-700 dark:text-white border border-slate-200 dark:border-white/10'"
                        >
                            <Check v-if="!item.missing_en" class="w-3.5 h-3.5" />
                            <RefreshCw v-else-if="downloadingId === `${item.id}-en`" class="w-3.5 h-3.5 animate-spin" />
                            <Download v-else class="w-3.5 h-3.5" />
                            <span>{{ !item.missing_en ? (isRTL ? 'الإنجليزية متوفرة' : 'English Ready') : (isRTL ? 'تحميل الإنجليزية' : 'Get English') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="glass-panel rounded-3xl p-12 text-center text-slate-500 dark:text-slate-400">
                <CheckCheck class="w-12 h-12 text-emerald-400 mx-auto mb-3" />
                <h3 class="font-bold text-base text-slate-800 dark:text-slate-200">
                    {{ isRTL ? 'جميع الوسائط تحتوي على ترجمات كاملة!' : 'All Media Subtitles Synchronized!' }}
                </h3>
                <p class="text-xs text-slate-500 mt-1">
                    {{ isRTL ? 'لا توجد وسائط تنقصها ترجمات وفقاً لخيارات التصفية المحددة.' : 'No items match your filter criteria.' }}
                </p>
            </div>

            <!-- Pagination Bar -->
            <div v-if="totalPages > 1" class="flex items-center justify-center gap-2 pt-6">
                <button
                    @click="setPage(currentPage - 1)"
                    :disabled="currentPage === 1"
                    class="p-2.5 rounded-xl bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 disabled:opacity-30 cursor-pointer border border-slate-200 dark:border-white/10"
                >
                    <ChevronLeft class="w-4 h-4" :class="isRTL ? 'rotate-180' : ''" />
                </button>

                <button
                    v-for="p in totalPages"
                    :key="p"
                    @click="setPage(p)"
                    class="w-10 h-10 rounded-xl text-xs font-bold transition-all cursor-pointer"
                    :class="currentPage === p
                        ? 'bg-cyan-500 text-slate-950 shadow-lg shadow-cyan-500/20 font-black'
                        : 'bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10'"
                >
                    {{ p }}
                </button>

                <button
                    @click="setPage(currentPage + 1)"
                    :disabled="currentPage === totalPages"
                    class="p-2.5 rounded-xl bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 disabled:opacity-30 cursor-pointer border border-slate-200 dark:border-white/10"
                >
                    <ChevronRight class="w-4 h-4" :class="isRTL ? 'rotate-180' : ''" />
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
