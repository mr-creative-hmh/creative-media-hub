<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import FixMatchModal from '@/components/media/FixMatchModal.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';
import {
    Sparkles, Search, Image as ImageIcon, Globe, Film, Tv,
    Star, RefreshCw, CheckCircle2, AlertCircle, Play,
    SlidersHorizontal, Edit, ExternalLink, Check, X, Trash2,
    Database, Filter, ArrowRight, Wand2, Repeat, ArrowRightLeft,
    AlertTriangle, HelpCircle, Layers
} from 'lucide-vue-next';

const props = defineProps<{
    items: Array<any>;
    stats: {
        total_items: number;
        unmatched_count?: number;
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
const isOperating = ref(false);
const toastMessage = ref('');

// Fix Match Modal State
const selectedItemForFix = ref<any | null>(null);
const showFixModal = ref(false);

const confirmModal = ref<{
    show: boolean;
    title: string;
    message: string;
    confirmText: string;
    type: 'danger' | 'warning' | 'info';
    action: () => Promise<void> | void;
}>({
    show: false,
    title: '',
    message: '',
    confirmText: '',
    type: 'danger',
    action: () => {},
});

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
    showFixModal.value = false;
    toastMessage.value = isRTL.value ? `تم تحديث بيانات "${updatedItem.title}" بنجاح!` : `Metadata updated for "${updatedItem.title}"!`;
    const target = props.items.find((i) => i.id === updatedItem.id && i.type === updatedItem.type);
    if (target) {
        Object.assign(target, updatedItem);
    }
    setTimeout(() => {
        toastMessage.value = '';
    }, 4000);
};

const quickReparse = async (item: any) => {
    isOperating.value = true;
    try {
        const res = await fetch(`/api/metadata/${item.type}/${item.id}/reparse`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });
        if (res.ok) {
            const data = await res.json();
            toastMessage.value = isRTL.value ? `تمت إعادة التحليل: ${data.message}` : data.message;
            Object.assign(item, data.item);
        }
    } catch (e) {
        toastMessage.value = isRTL.value ? 'فشل إعادة تحليل الملف.' : 'Failed to reparse file.';
    } finally {
        isOperating.value = false;
        setTimeout(() => { toastMessage.value = ''; }, 4000);
    }
};

const quickConvert = async (item: any) => {
    isOperating.value = true;
    try {
        const res = await fetch(`/api/metadata/${item.type}/${item.id}/convert-type`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
        });
        if (res.ok) {
            const data = await res.json();
            toastMessage.value = isRTL.value ? `تم التحويل: ${data.message}` : data.message;
            setTimeout(() => { router.reload({ preserveState: false }); }, 1000);
        }
    } catch (e) {
        toastMessage.value = isRTL.value ? 'فشل تحويل النوع.' : 'Failed to convert media type.';
    } finally {
        isOperating.value = false;
        setTimeout(() => { toastMessage.value = ''; }, 4000);
    }
};

const enrichAllMissing = async () => {
    isBatchEnriching.value = true;
    try {
        const res = await fetch('/api/metadata/batch-enrich', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({ limit: 50 }),
        });
        if (res.ok) {
            const data = await res.json();
            toastMessage.value = isRTL.value
                ? `تم بنجاح جلب وتحديث بيانات ${data.result?.enriched_count || 0} عنصراً مع البوسترات!`
                : `Enriched ${data.result?.enriched_count || 0} items with Arabic metadata & artwork!`;
            setTimeout(() => {
                router.reload({ preserveState: false });
            }, 1200);
        }
    } catch (e) {
        toastMessage.value = isRTL.value ? 'حدث خطأ أثناء المعالجة الجماعية.' : 'Error during metadata enrichment.';
    } finally {
        isBatchEnriching.value = false;
    }
};

const deleteItem = (item: any) => {
    confirmModal.value = {
        show: true,
        title: isRTL.value ? 'حذف من المكتبة' : 'Delete from Library',
        message: isRTL.value 
            ? `هل أنت متأكد من حذف "${item.title}" من قاعدة البيانات؟ (لن يتم حذف الملف الفعلي من القرص)`
            : `Are you sure you want to remove "${item.title}" from the library index? (Original file will not be deleted)`,
        confirmText: isRTL.value ? 'نعم، حذف' : 'Yes, Delete',
        type: 'danger',
        action: async () => {
            const endpoint = item.type === 'series' ? `/api/series/${item.id}` : `/api/media/${item.id}`;
            try {
                const res = await fetch(endpoint, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                    },
                });
                if (res.ok) {
                    confirmModal.value.show = false;
                    toastMessage.value = isRTL.value ? `تم حذف "${item.title}"` : `Deleted "${item.title}"`;
                    setTimeout(() => { router.reload({ preserveState: false }); }, 800);
                }
            } catch (e) {}
        }
    };
};
</script>

<template>
    <AppLayout>
        <Head :title="isRTL ? 'استوديو البيانات الوصفية ومعالجة أخطاء الفهرسة' : 'Metadata Studio & Error Triage'" />

        <div class="space-y-8 pb-16">
            <!-- Toast Feedback Banner -->
            <div
                v-if="toastMessage"
                class="fixed bottom-6 z-50 p-4 rounded-2xl glass-panel border border-cyan-500/40 bg-slate-950/95 text-cyan-300 font-bold text-xs shadow-2xl flex items-center gap-3 animate-slide-up"
                :class="isRTL ? 'left-6' : 'right-6'"
            >
                <CheckCircle2 class="w-4 h-4 text-cyan-400 shrink-0" />
                <span>{{ toastMessage }}</span>
            </div>

            <!-- Header Banner -->
            <section class="relative rounded-3xl overflow-hidden glass-panel border border-cyan-500/20 p-6 lg:p-10 bg-gradient-to-br from-cyan-950/40 via-slate-900/60 to-purple-950/30">
                <div class="ambient-glow bg-cyan-500/10 w-96 h-96 -top-20 -left-20 pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                    <div class="space-y-3 max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 text-xs font-bold uppercase tracking-wider">
                            <Wand2 class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'استوديو المعالجة وتصحيح المطابقة' : 'Fix Match & Metadata Studio' }}</span>
                        </div>
                        <h1 class="text-3xl lg:text-4xl font-black text-white tracking-tight">
                            {{ isRTL ? 'إدارة البيانات الوصفية وحل أخطاء الفهرسة' : 'Metadata Management & Error Triage' }}
                        </h1>
                        <p class="text-sm text-slate-300 leading-relaxed">
                            {{ isRTL 
                                ? 'تحكم كامل في أسماء وتصنيفات الوسائط، تصحيح المطابقات الخاطئة، تحويل الأفلام إلى مسلسلات بنقرة واحدة، وتحديث البوسترات والترجمة العربية.' 
                                : 'Fix unmatched files, resolve scanner misidentifications, convert between movies and series with 1-click, and auto-fetch posters and Arabic plots.' 
                            }}
                        </p>
                    </div>

                    <!-- Batch Action Button -->
                    <div class="shrink-0 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <button
                            @click="enrichAllMissing"
                            :disabled="isBatchEnriching"
                            class="px-5 py-3 rounded-2xl bg-cyan-500 text-slate-950 font-black text-xs hover:bg-cyan-400 flex items-center justify-center gap-2 shadow-lg shadow-cyan-500/20 active:scale-95 transition-all cursor-pointer disabled:opacity-50"
                        >
                            <RefreshCw v-if="isBatchEnriching" class="w-4 h-4 animate-spin" />
                            <Sparkles v-else class="w-4 h-4" />
                            <span>
                                {{ isBatchEnriching 
                                    ? (isRTL ? 'جاري التحديث التلقائي...' : 'Enriching in Background...') 
                                    : (isRTL ? 'معالجة وتحديث تلقائي جماعي' : 'Batch Auto-Resolve & Fix') 
                                }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Stats Counters Grid -->
                <div class="relative z-10 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mt-8 pt-6 border-t border-white/10">
                    <div class="p-3.5 rounded-2xl bg-white/[0.04] border border-white/10 text-center">
                        <div class="text-xl font-black text-white">{{ stats.total_items }}</div>
                        <div class="text-[10px] text-slate-400 font-semibold uppercase mt-0.5">{{ isRTL ? 'إجمالي العناصر' : 'Total Items' }}</div>
                    </div>
                    <div 
                        @click="applyFilter('unmatched')"
                        class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-center cursor-pointer hover:bg-amber-500/20 transition-colors"
                    >
                        <div class="text-xl font-black text-amber-400">{{ stats.unmatched_count ?? 0 }}</div>
                        <div class="text-[10px] text-amber-300 font-bold uppercase mt-0.5 flex items-center justify-center gap-1">
                            <AlertTriangle class="w-3 h-3" />
                            <span>{{ isRTL ? 'أخطاء / غير مطابق' : 'Unmatched / Errors' }}</span>
                        </div>
                    </div>
                    <div 
                        @click="applyFilter('missing_posters')"
                        class="p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-center cursor-pointer hover:bg-rose-500/20 transition-colors"
                    >
                        <div class="text-xl font-black text-rose-400">{{ stats.missing_posters_count }}</div>
                        <div class="text-[10px] text-rose-300 font-semibold uppercase mt-0.5">{{ isRTL ? 'بدون بوستر' : 'No Artwork' }}</div>
                    </div>
                    <div 
                        @click="applyFilter('missing_arabic')"
                        class="p-3.5 rounded-2xl bg-purple-500/10 border border-purple-500/30 text-center cursor-pointer hover:bg-purple-500/20 transition-colors"
                    >
                        <div class="text-xl font-black text-purple-400">{{ stats.missing_arabic_count }}</div>
                        <div class="text-[10px] text-purple-300 font-semibold uppercase mt-0.5">{{ isRTL ? 'بدون عربي' : 'No Arabic Plot' }}</div>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-white/[0.04] border border-white/10 text-center">
                        <div class="text-xl font-black text-cyan-400">{{ stats.movies_count }}</div>
                        <div class="text-[10px] text-slate-400 font-semibold uppercase mt-0.5">{{ isRTL ? 'أفلام' : 'Movies' }}</div>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-white/[0.04] border border-white/10 text-center">
                        <div class="text-xl font-black text-purple-400">{{ stats.series_count }}</div>
                        <div class="text-[10px] text-slate-400 font-semibold uppercase mt-0.5">{{ isRTL ? 'مسلسلات' : 'Series' }}</div>
                    </div>
                </div>
            </section>

            <!-- Filters Bar & Search -->
            <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                <!-- Filter Pills -->
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        @click="applyFilter('all')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeFilter === 'all' ? 'bg-cyan-500 text-slate-950 shadow-md font-black' : 'bg-white/[0.05] text-slate-300 hover:text-white border border-white/10'"
                    >
                        {{ isRTL ? 'الكل' : 'All Items' }}
                    </button>
                    <button
                        @click="applyFilter('unmatched')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                        :class="activeFilter === 'unmatched' ? 'bg-amber-500 text-slate-950 shadow-md font-black' : 'bg-amber-500/10 text-amber-300 hover:bg-amber-500/20 border border-amber-500/30'"
                    >
                        <AlertTriangle class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'أخطاء الفهرسة وغير المطابق' : 'Unmatched & Errors' }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-black/40 font-mono">{{ stats.unmatched_count ?? 0 }}</span>
                    </button>
                    <button
                        @click="applyFilter('missing_posters')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeFilter === 'missing_posters' ? 'bg-rose-500 text-white shadow-md font-black' : 'bg-white/[0.05] text-slate-300 hover:text-white border border-white/10'"
                    >
                        {{ isRTL ? 'بدون بوستر' : 'Missing Artwork' }}
                    </button>
                    <button
                        @click="applyFilter('missing_arabic')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeFilter === 'missing_arabic' ? 'bg-purple-500 text-white shadow-md font-black' : 'bg-white/[0.05] text-slate-300 hover:text-white border border-white/10'"
                    >
                        {{ isRTL ? 'بدون لغة عربية' : 'Missing Arabic' }}
                    </button>
                    <button
                        @click="applyFilter('movies')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeFilter === 'movies' ? 'bg-cyan-500 text-slate-950 shadow-md font-black' : 'bg-white/[0.05] text-slate-300 hover:text-white border border-white/10'"
                    >
                        {{ isRTL ? 'أفلام فقط' : 'Movies Only' }}
                    </button>
                    <button
                        @click="applyFilter('series')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeFilter === 'series' ? 'bg-purple-500 text-white shadow-md font-black' : 'bg-white/[0.05] text-slate-300 hover:text-white border border-white/10'"
                    >
                        {{ isRTL ? 'مسلسلات فقط' : 'Series Only' }}
                    </button>
                </div>

                <!-- Search Input -->
                <div class="relative w-full md:w-72">
                    <Search class="absolute top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" :class="isRTL ? 'right-3' : 'left-3'" />
                    <input
                        v-model="searchQuery"
                        @keyup.enter="handleSearch"
                        type="text"
                        :placeholder="isRTL ? 'بحث بالاسم أو المسار...' : 'Search title or path...'"
                        class="w-full py-2 rounded-xl bg-slate-900/80 border border-white/10 text-xs text-white placeholder-slate-400 focus:outline-none focus:border-cyan-400 transition-colors"
                        :class="isRTL ? 'pr-9 pl-3' : 'pl-9 pr-3'"
                    />
                </div>
            </div>

            <!-- Items List -->
            <div v-if="items.length > 0" class="space-y-3">
                <div
                    v-for="item in items"
                    :key="`${item.type}-${item.id}`"
                    class="group rounded-2xl glass-panel border border-white/10 hover:border-cyan-500/30 p-4 transition-all duration-200 bg-slate-900/40 hover:bg-slate-900/80 flex flex-col md:flex-row items-start md:items-center justify-between gap-4"
                >
                    <!-- Left: Poster + Info -->
                    <div class="flex items-center gap-4 flex-1 min-w-0">
                        <!-- Poster Preview -->
                        <div class="w-14 aspect-[2/3] rounded-xl overflow-hidden bg-slate-950 shrink-0 border border-white/10 relative">
                            <img
                                v-if="item.poster_path"
                                :src="item.poster_path"
                                :alt="item.title"
                                class="w-full h-full object-cover"
                                loading="lazy"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-slate-700">
                                <Film v-if="item.type === 'movie'" class="w-5 h-5" />
                                <Tv v-else class="w-5 h-5" />
                            </div>

                            <!-- Match Status Dot -->
                            <div
                                class="absolute top-1 right-1 w-2.5 h-2.5 rounded-full border border-black/80"
                                :class="item.is_matched ? 'bg-emerald-400 shadow-sm shadow-emerald-400/50' : 'bg-amber-400 animate-pulse'"
                                :title="item.is_matched ? 'TMDb Matched' : 'Unmatched / Pending Fix'"
                            ></div>
                        </div>

                        <!-- Details & Path -->
                        <div class="space-y-1 min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-[10px] font-black uppercase px-2 py-0.5 rounded-md"
                                    :class="item.type === 'series' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30'"
                                >
                                    {{ item.type }}
                                </span>

                                <h3 class="text-sm font-bold text-white truncate group-hover:text-cyan-400 transition-colors">
                                    {{ item.title }}
                                </h3>

                                <span v-if="item.title_ar" class="text-xs text-slate-400 truncate" dir="rtl">
                                    ({{ item.title_ar }})
                                </span>

                                <span v-if="item.release_year" class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-white/[0.06] text-slate-300">
                                    {{ item.release_year }}
                                </span>
                            </div>

                            <!-- Physical File Path & Collection Tag -->
                            <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-400">
                                <p class="truncate font-mono text-[10px] text-slate-400 max-w-md">
                                    {{ item.file_path }}
                                </p>
                                <span v-if="item.collection_name" class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded bg-cyan-500/10 text-cyan-300 border border-cyan-500/20">
                                    <Layers class="w-2.5 h-2.5" />
                                    <span>{{ item.collection_name }}</span>
                                </span>
                            </div>

                            <!-- Badges Status -->
                            <div class="flex flex-wrap items-center gap-2 pt-0.5">
                                <span
                                    class="text-[10px] px-2 py-0.5 rounded-md font-bold flex items-center gap-1"
                                    :class="item.is_matched ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-300 border border-amber-500/30'"
                                >
                                    <CheckCircle2 v-if="item.is_matched" class="w-3 h-3" />
                                    <AlertTriangle v-else class="w-3 h-3" />
                                    <span>{{ item.is_matched ? 'TMDb Matched' : (isRTL ? 'غير مطابق' : 'Unmatched') }}</span>
                                </span>

                                <span
                                    class="text-[10px] px-2 py-0.5 rounded-md font-bold flex items-center gap-1"
                                    :class="item.has_arabic ? 'bg-purple-500/10 text-purple-300 border border-purple-500/20' : 'bg-white/[0.05] text-slate-400 border border-white/10'"
                                >
                                    <Globe class="w-3 h-3" />
                                    <span>{{ item.has_arabic ? (isRTL ? 'عربي متوفر' : 'Arabic Ready') : (isRTL ? 'بدون عربي' : 'No Arabic') }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Direct Interactive Actions -->
                    <div class="flex items-center gap-2 shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-white/5 w-full md:w-auto justify-end">
                        <!-- Quick Re-Parse -->
                        <button
                            @click="quickReparse(item)"
                            :disabled="isOperating"
                            class="p-2 rounded-xl bg-white/[0.05] hover:bg-white/[0.1] border border-white/10 text-slate-300 hover:text-cyan-300 text-xs font-bold transition-all cursor-pointer"
                            :title="isRTL ? 'إعادة تحليل اسم الملف بالخوارزمية الذكية' : 'Re-parse file with intelligent parser'"
                        >
                            <Repeat class="w-4 h-4" />
                        </button>

                        <!-- Quick Convert Type -->
                        <button
                            @click="quickConvert(item)"
                            :disabled="isOperating"
                            class="p-2 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/20 text-purple-300 text-xs font-bold transition-all cursor-pointer"
                            :title="item.type === 'movie' ? (isRTL ? 'تحويل لمسلسل' : 'Convert to Series') : (isRTL ? 'تحويل لفيلم' : 'Convert to Movie')"
                        >
                            <ArrowRightLeft class="w-4 h-4" />
                        </button>

                        <!-- Fix Match Modal Trigger -->
                        <button
                            @click="openFixMatch(item)"
                            class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs flex items-center gap-1.5 shadow-md shadow-cyan-500/10 transition-all cursor-pointer"
                        >
                            <Wand2 class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'تصحيح المطابقة' : 'Fix Match' }}</span>
                        </button>

                        <!-- Delete Button -->
                        <button
                            @click="deleteItem(item)"
                            class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-400 text-xs font-bold transition-all cursor-pointer"
                            :title="isRTL ? 'حذف من الفهرس' : 'Remove from Index'"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-16 px-4 rounded-3xl glass-panel border border-white/10 space-y-4 max-w-lg mx-auto">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400">
                    <CheckCircle2 class="w-8 h-8" />
                </div>
                <h3 class="text-lg font-bold text-white">
                    {{ isRTL ? 'المكتبة في أفضل حالاتها' : 'No Items Need Fixing' }}
                </h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    {{ isRTL 
                        ? 'جميع عناصر الوسائط تمت مطابقتها وتزويدها بالبوسترات والبيانات الوصفية بنجاح.' 
                        : 'All media files in this filter have verified metadata, artwork, and clean titles.' 
                    }}
                </p>
            </div>
        </div>

        <!-- Fix Match Modal -->
        <FixMatchModal
            :show="showFixModal"
            :item="selectedItemForFix"
            :type="selectedItemForFix?.type"
            @close="showFixModal = false"
            @updated="handleItemUpdated"
        />

        <!-- Confirm Action Modal -->
        <ConfirmModal
            :show="confirmModal.show"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :confirm-text="confirmModal.confirmText"
            :type="confirmModal.type"
            @close="confirmModal.show = false"
            @confirm="confirmModal.action"
        />
    </AppLayout>
</template>
