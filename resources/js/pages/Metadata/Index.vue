<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import FixMatchModal from '@/components/media/FixMatchModal.vue';
import {
    Sparkles, Search, Image as ImageIcon, Globe, Film, Tv,
    Star, RefreshCw, CheckCircle2, AlertCircle, Play,
    SlidersHorizontal, Edit, ExternalLink, Check, X, Trash2
} from 'lucide-vue-next';

const props = defineProps<{
    items: Array<any>;
    stats: {
        total_items: number;
        missing_posters_count: number;
        missing_arabic_count: number;
        movies_count: number;
        series_count: number;
    };
    filters: {
        filter?: string;
        search?: string;
    };
}>();

const { t, isRTL } = useI18n();

const activeFilter = ref(props.filters?.filter || 'all');
const searchQuery = ref(props.filters?.search || '');
const isBatchEnriching = ref(false);
const isClearing = ref(false);
const toastMessage = ref('');

// Fix Match Modal State
const selectedItemForFix = ref<any | null>(null);
const showFixModal = ref(false);

const applyFilter = (filterKey: string) => {
    activeFilter.value = filterKey;
    router.get('/metadata', {
        filter: filterKey,
        search: searchQuery.value || undefined,
    }, { preserveState: true });
};

const handleSearch = () => {
    router.get('/metadata', {
        filter: activeFilter.value,
        search: searchQuery.value || undefined,
    }, { preserveState: true });
};

const openFixMatch = (item: any) => {
    selectedItemForFix.value = item;
    showFixModal.value = true;
};

const handleItemUpdated = (updatedItem: any) => {
    if (selectedItemForFix.value) {
        Object.assign(selectedItemForFix.value, updatedItem);
    }
    toastMessage.value = isRTL.value ? 'تم تحديث البيانات والأغلفة بنجاح!' : 'Metadata & artwork updated successfully!';
};

const handleBatchEnrich = async () => {
    isBatchEnriching.value = true;
    toastMessage.value = '';

    try {
        const res = await fetch('/api/metadata/batch-enrich', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });

        if (res.ok) {
            const data = await res.json();
            toastMessage.value = isRTL.value
                ? `تم تحديث وتحميل بيانات ${data.result?.total || 0} عنصر بنجاح!`
                : `Successfully enriched ${data.result?.total || 0} items with posters & metadata!`;
            setTimeout(() => {
                router.reload();
            }, 1000);
        }
    } finally {
        isBatchEnriching.value = false;
    }
};

const handleDeleteItem = async (item: any) => {
    if (!confirm(isRTL.value ? `هل أنت متأكد من حذف "${item.title}" من فهارس المكتبة؟ (لن يتم حذف الملف من القرص)` : `Remove "${item.title}" from library index? (Disk file will NOT be deleted)`)) {
        return;
    }

    try {
        const res = await fetch(`/api/media/${item.id}?type=${item.type || 'movie'}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });

        if (res.ok) {
            const idx = props.items.indexOf(item);
            if (idx !== -1) props.items.splice(idx, 1);
            toastMessage.value = isRTL.value ? `تم حذف "${item.title}" من المكتبة.` : `"${item.title}" removed from library.`;
        }
    } catch (e) {}
};

const handleClearAllMedia = async () => {
    if (confirm(isRTL.value ? 'تحذير: سيتم حذف كافة عناصر المكتبة المفهرسة من قاعدة البيانات (لن يتم حذف الملفات من القرص الصلب). هل تريد المتابعة؟' : 'Warning: This will remove all indexed movies and series from your library database (files on disk will NOT be deleted). Continue?')) {
        isClearing.value = true;
        try {
            const res = await fetch('/api/scanner/clear-catalog', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
            });
            if (res.ok) {
                toastMessage.value = isRTL.value ? 'تم تفريغ فهارس المكتبة بنجاح.' : 'Library catalog cleared.';
                setTimeout(() => { router.reload(); }, 800);
            }
        } finally {
            isClearing.value = false;
        }
    }
};
</script>

<template>
    <Head :title="isRTL ? 'استوديو بيانات وأغلفة المكتبة' : 'Library Metadata & Cover Studio'" />

    <AppLayout v-slot="{ play }">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                        <Sparkles class="w-5 h-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                            {{ isRTL ? 'استوديو بيانات وأغلفة المكتبة' : 'Library Metadata & Cover Studio' }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                            {{ isRTL ? 'فحص وتعديل وتحديث الأغلفة والبيانات العربية والإنجليزية' : 'Manage movie & TV metadata, fetch missing posters, and fix unmatched titles.' }}
                        </p>
                    </div>
                </div>

                <!-- Batch Actions -->
                <div class="flex items-center gap-2.5">
                    <button
                        @click="handleClearAllMedia"
                        :disabled="isClearing"
                        class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 text-xs font-bold transition-all cursor-pointer"
                        :title="isRTL ? 'تفريغ فهارس المكتبة بالكامل' : 'Wipe entire library catalog from database'"
                    >
                        <Trash2 class="w-4 h-4 text-rose-400" />
                        <span>{{ isRTL ? 'تفريغ المكتبة' : 'Clear Library' }}</span>
                    </button>

                    <button
                        @click="handleBatchEnrich"
                        :disabled="isBatchEnriching"
                        class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-extrabold text-xs shadow-lg shadow-cyan-500/20 active:scale-95 transition-all cursor-pointer"
                    >
                        <RefreshCw v-if="isBatchEnriching" class="w-4 h-4 animate-spin" />
                        <ImageIcon v-else class="w-4 h-4" />
                        <span>{{ isRTL ? 'جلب الأغلفة الناقصة تلقائياً' : 'Auto-Fetch Missing Posters' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div v-if="toastMessage" class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 shrink-0" />
                <span>{{ toastMessage }}</span>
            </div>
            <button @click="toastMessage = ''" class="cursor-pointer text-emerald-400 hover:text-emerald-300">
                <X class="w-4 h-4" />
            </button>
        </div>

        <!-- 1. Stats Row -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
            <div
                @click="applyFilter('all')"
                class="glass-panel p-4 rounded-2xl border border-white/10 hover:border-cyan-500/50 cursor-pointer transition-all flex flex-col justify-between"
                :class="activeFilter === 'all' ? 'border-cyan-500/50 bg-cyan-500/5' : ''"
            >
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ isRTL ? 'إجمالي المحتوى' : 'Total Media' }}</span>
                <div class="text-2xl font-black text-white mt-2">{{ stats.total_items }}</div>
            </div>

            <div
                @click="applyFilter('missing_posters')"
                class="glass-panel p-4 rounded-2xl border border-white/10 hover:border-amber-500/50 cursor-pointer transition-all flex flex-col justify-between"
                :class="activeFilter === 'missing_posters' ? 'border-amber-500/50 bg-amber-500/5' : ''"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ isRTL ? 'بدون غلاف' : 'Missing Posters' }}</span>
                    <ImageIcon class="w-4 h-4 text-amber-400" />
                </div>
                <div class="text-2xl font-black mt-2" :class="stats.missing_posters_count > 0 ? 'text-amber-400' : 'text-emerald-400'">
                    {{ stats.missing_posters_count }}
                </div>
            </div>

            <div
                @click="applyFilter('missing_arabic')"
                class="glass-panel p-4 rounded-2xl border border-white/10 hover:border-indigo-500/50 cursor-pointer transition-all flex flex-col justify-between"
                :class="activeFilter === 'missing_arabic' ? 'border-indigo-500/50 bg-indigo-500/5' : ''"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ isRTL ? 'بدون ترجمة عربية' : 'Missing Arabic' }}</span>
                    <Globe class="w-4 h-4 text-indigo-400" />
                </div>
                <div class="text-2xl font-black mt-2 text-indigo-400">
                    {{ stats.missing_arabic_count }}
                </div>
            </div>

            <div
                @click="applyFilter('movies')"
                class="glass-panel p-4 rounded-2xl border border-white/10 hover:border-cyan-500/50 cursor-pointer transition-all flex flex-col justify-between"
                :class="activeFilter === 'movies' ? 'border-cyan-500/50 bg-cyan-500/5' : ''"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ isRTL ? 'أفلام' : 'Movies' }}</span>
                    <Film class="w-4 h-4 text-cyan-400" />
                </div>
                <div class="text-2xl font-black text-white mt-2">{{ stats.movies_count }}</div>
            </div>

            <div
                @click="applyFilter('series')"
                class="glass-panel p-4 rounded-2xl border border-white/10 hover:border-cyan-500/50 cursor-pointer transition-all flex flex-col justify-between"
                :class="activeFilter === 'series' ? 'border-cyan-500/50 bg-cyan-500/5' : ''"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ isRTL ? 'مسلسلات' : 'TV Series' }}</span>
                    <Tv class="w-4 h-4 text-cyan-400" />
                </div>
                <div class="text-2xl font-black text-white mt-2">{{ stats.series_count }}</div>
            </div>
        </div>

        <!-- 2. Search & Filters Bar -->
        <div class="glass-panel rounded-2xl p-4 border border-white/10 mb-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0">
                <button
                    v-for="tab in [
                        { id: 'all', label: isRTL ? 'الكل' : 'All Media' },
                        { id: 'missing_posters', label: isRTL ? 'بدون غلاف' : 'Missing Covers' },
                        { id: 'missing_arabic', label: isRTL ? 'بدون عربي' : 'Missing Arabic' },
                        { id: 'movies', label: isRTL ? 'أفلام' : 'Movies' },
                        { id: 'series', label: isRTL ? 'مسلسلات' : 'Series' },
                    ]"
                    :key="tab.id"
                    @click="applyFilter(tab.id)"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer"
                    :class="activeFilter === tab.id
                        ? 'bg-cyan-500 text-slate-950 font-black shadow-md shadow-cyan-500/20'
                        : 'bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10'"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Search Form -->
            <form @submit.prevent="handleSearch" class="relative w-full sm:w-80">
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="isRTL ? 'البحث بالاسم أو العنوان...' : 'Search by title...'"
                    class="w-full pl-10 pr-4 py-2 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                />
                <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            </form>
        </div>

        <!-- 3. Media Items Table / Grid -->
        <div v-if="items.length > 0" class="space-y-3">
            <div
                v-for="item in items"
                :key="`${item.type}-${item.id}`"
                class="glass-panel p-4 rounded-2xl border border-white/10 hover:border-cyan-500/40 transition-all flex flex-col md:flex-row items-start md:items-center justify-between gap-4 group"
            >
                <!-- Item Info -->
                <div class="flex items-center gap-4 min-w-0 flex-1">
                    <!-- Thumbnail Poster or Missing Placeholder -->
                    <div class="w-14 h-20 rounded-xl bg-slate-900 border border-white/10 overflow-hidden shrink-0 relative flex items-center justify-center shadow-md">
                        <img
                            v-if="item.poster_path"
                            :src="item.poster_path"
                            :alt="item.title"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                        />
                        <div v-else class="flex flex-col items-center justify-center p-2 text-center text-slate-500">
                            <ImageIcon class="w-5 h-5 text-amber-400 mb-1" />
                            <span class="text-[8px] font-bold uppercase">{{ isRTL ? 'بلا غلاف' : 'No Poster' }}</span>
                        </div>
                    </div>

                    <!-- Title & Metas -->
                    <div class="min-w-0 space-y-1 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="cinema-badge bg-cyan-500/20 text-cyan-300 border-cyan-500/30 text-[9px]">
                                {{ item.type === 'series' ? 'TV SERIES' : 'MOVIE' }}
                            </span>
                            <span v-if="item.release_year" class="text-xs font-semibold text-slate-400">
                                {{ item.release_year }}
                            </span>
                            <span v-if="item.rating" class="text-xs text-amber-400 font-bold flex items-center gap-1">
                                <Star class="w-3 h-3 fill-current" />
                                <span>{{ item.rating }}</span>
                            </span>
                            <span v-if="item.title_ar" class="cinema-badge bg-indigo-500/20 text-indigo-300 border-indigo-500/30 text-[9px]">
                                AR: {{ item.title_ar }}
                            </span>
                        </div>

                        <h3 class="font-bold text-sm text-white truncate max-w-lg">
                            {{ item.title }}
                        </h3>

                        <p class="text-xs text-slate-400 line-clamp-1 max-w-2xl">
                            {{ item.overview || item.overview_ar || (isRTL ? 'لا يوجد وصف تفصيلي متوفر حالياً.' : 'No description available.') }}
                        </p>
                    </div>
                </div>

                <!-- Row Actions -->
                <div class="flex items-center gap-2 self-end md:self-center shrink-0">
                    <button
                        v-if="item.type === 'movie'"
                        @click="play(item)"
                        class="p-2.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 text-xs font-bold transition-all cursor-pointer"
                        :title="isRTL ? 'تشغيل الفيلم' : 'Play movie'"
                    >
                        <Play class="w-4 h-4 fill-current" />
                    </button>

                    <button
                        @click="openFixMatch(item)"
                        class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10 hover:border-cyan-500/40 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                        :title="isRTL ? 'مطابقة تلقائية وتحديث البيانات' : 'Fix match & fetch metadata'"
                    >
                        <Sparkles class="w-3.5 h-3.5 text-cyan-400" />
                        <span>{{ isRTL ? 'مطابقة وتعديل' : 'Fix Match' }}</span>
                    </button>

                    <button
                        @click="handleDeleteItem(item)"
                        class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 transition-all cursor-pointer"
                        :title="isRTL ? 'حذف من فهارس المكتبة' : 'Remove from library'"
                    >
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="glass-panel rounded-3xl p-12 text-center text-slate-400 border border-white/10 space-y-3">
            <Sparkles class="w-10 h-10 text-cyan-500/50 mx-auto" />
            <h3 class="font-bold text-white text-base">
                {{ isRTL ? 'لا توجد عناصر مطابقة للفلاتر الحالية' : 'No media items found' }}
            </h3>
            <p class="text-xs text-slate-500 max-w-sm mx-auto">
                {{ isRTL ? 'جرب تغيير خيارات التصفية أو ابدأ فحصاً جديداً للمكتبة لاكتشاف الأفلام والمسلسلات.' : 'Try changing your filters or start a library scan to index your movies and TV shows.' }}
            </p>
        </div>

        <!-- Fix Match Modal -->
        <FixMatchModal
            v-if="showFixModal"
            :item="selectedItemForFix"
            @close="showFixModal = false"
            @updated="handleItemUpdated"
        />
    </AppLayout>
</template>
