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
    AlertTriangle, HelpCircle, Layers, Copy, FileVideo, Folder, HardDrive,
    FileEdit
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
const copiedItemId = ref<number | string | null>(null);

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

const copyFilePath = async (item: any) => {
    const textToCopy = item.file_path || item.folder_path || '';
    if (!textToCopy) return;
    try {
        await navigator.clipboard.writeText(textToCopy);
        copiedItemId.value = item.id;
        setTimeout(() => {
            copiedItemId.value = null;
        }, 2500);
    } catch (e) {
        console.error('Failed to copy', e);
    }
};

const handleItemUpdated = (updatedItem: any) => {
    showFixModal.value = false;
    toastMessage.value = isRTL.value 
        ? `تم تحديث بيانات "${updatedItem.title}" بنجاح!` 
        : `Metadata updated for "${updatedItem.title}"!`;
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
        const data = await res.json();
        if (data.success) {
            handleItemUpdated(data.media || data.series);
        }
    } catch (e) {
        console.error('Reparse error', e);
    } finally {
        isOperating.value = false;
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
        const data = await res.json();
        if (data.success) {
            router.reload({ preserveScroll: true });
        }
    } catch (e) {
        console.error('Convert error', e);
    } finally {
        isOperating.value = false;
    }
};

const confirmRenameFile = (item: any) => {
    const rawPath = item.file_path || item.folder_path || '';
    const oldName = rawPath.split(/[\\/]/).pop() || '';
    const ext = oldName.includes('.') ? '.' + oldName.split('.').pop() : '';
    const cleanTitle = (item.title || 'media').replace(/[\\/:*?"<>|]/g, ' ').trim();
    const newName = `${cleanTitle}${item.release_year ? ` (${item.release_year})` : ''}${item.type === 'movie' ? ext : ''}`;

    confirmModal.value = {
        show: true,
        title: isRTL.value ? 'إعادة تسمية الملف الفعلي على القرص' : 'Rename Physical File on Disk',
        message: isRTL.value
            ? `هل تريد إعادة تسمية الملف من:\n"${oldName}"\n\nإلى الاسم القياسي الجديد:\n"${newName}"؟`
            : `Do you want to rename the physical file on disk from:\n"${oldName}"\n\nto the clean standard title:\n"${newName}"?`,
        confirmText: isRTL.value ? 'تأكيد إعادة التسمية' : 'Rename File Now',
        type: 'info',
        action: async () => {
            isOperating.value = true;
            try {
                const res = await fetch(`/api/metadata/${item.type}/${item.id}/rename-file`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                    },
                });
                const data = await res.json();
                if (data.success) {
                    toastMessage.value = data.message || (isRTL.value ? 'تمت إعادة تسمية الملف بنجاح!' : 'File renamed successfully!');
                    handleItemUpdated(data.media || data.series);
                } else {
                    toastMessage.value = data.message || (isRTL.value ? 'فشلت إعادة التسمية.' : 'Failed to rename file.');
                }
            } catch (e) {
                console.error(e);
            } finally {
                isOperating.value = false;
            }
        },
    };
};

const triggerBatchEnrich = async () => {
    confirmModal.value = {
        show: true,
        title: isRTL.value ? 'بدء المعالجة الذكية الشاملة' : 'Trigger Automated Batch Metadata Enrichment',
        message: isRTL.value
            ? 'سيقوم النظام بالبحث في قواعد بيانات TMDb و Wikipedia لجميع العناصر غير المطابقة وتحميل البوسترات وترجمة الملخصات تلقائياً في الخلفية. هل تريد المتابعة؟'
            : 'The engine will query TMDb, Wikipedia, and subtitle databases for all pending and unmatched media items in the background. Do you want to proceed?',
        confirmText: isRTL.value ? 'بدء الآن' : 'Start Auto-Enrich',
        type: 'info',
        action: async () => {
            isBatchEnriching.value = true;
            try {
                const res = await fetch('/api/metadata/batch-enrich', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                    },
                });
                const data = await res.json();
                toastMessage.value = data.message || (isRTL.value ? 'اكتملت المعالجة الذكية!' : 'Batch enrichment finished!');
                router.reload({ preserveScroll: true });
            } catch (e) {
                console.error(e);
            } finally {
                isBatchEnriching.value = false;
            }
        },
    };
};
</script>

<template>
    <AppLayout>
        <Head :title="isRTL ? 'استوديو إصلاح وتصحيح البيانات' : 'Fix Match & Metadata Studio'" />

        <div class="space-y-8 max-w-7xl mx-auto pb-16">
            <!-- Toast Notification -->
            <div
                v-if="toastMessage"
                class="fixed top-6 right-6 z-50 p-4 rounded-2xl bg-cyan-500/90 text-slate-950 font-bold shadow-2xl backdrop-blur-md flex items-center gap-3 animate-fade-in border border-cyan-400"
            >
                <CheckCircle2 class="w-5 h-5" />
                <span>{{ toastMessage }}</span>
            </div>

            <!-- Header Showcase Hero -->
            <div class="relative overflow-hidden rounded-3xl p-8 lg:p-10 border border-white/10 bg-gradient-to-br from-slate-900 via-[#0c1222] to-slate-950 shadow-2xl">
                <div class="absolute -top-24 -right-24 w-96 h-96 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-xs font-semibold uppercase tracking-wider mb-4">
                            <Sparkles class="w-3.5 h-3.5 animate-pulse" />
                            <span>{{ isRTL ? 'استوديو تصحيح ومطابقة البيانات' : 'Metadata Correction & Triage Studio' }}</span>
                        </div>
                        <h1 class="text-3xl lg:text-4xl font-black text-white tracking-tight">
                            {{ isRTL ? 'إصلاح وتصحيح مكتبة الوسائط' : 'Fix Match & Error Resolution Studio' }}
                        </h1>
                        <p class="text-slate-400 text-sm max-w-2xl mt-2 leading-relaxed">
                            {{ isRTL 
                                ? 'استعراض المسار الفعلي للملفات، إصلاح التسميات غير المطابقة، إعادة تسمية الملفات على القرص لتطابق العناوين، وجلب البوسترات والخلفيات بدقة.' 
                                : 'Inspect physical disk paths, fix unmatched titles, rename disk files to match standard titles, and download pristine artwork.' }}
                        </p>
                    </div>

                    <!-- Batch Trigger Action -->
                    <button
                        @click="triggerBatchEnrich"
                        :disabled="isBatchEnriching"
                        class="px-6 py-3.5 rounded-2xl bg-gradient-to-r from-cyan-500 via-blue-600 to-purple-600 hover:from-cyan-400 hover:to-purple-500 text-slate-950 font-black text-sm shadow-xl shadow-cyan-500/20 transition-all flex items-center gap-2.5 shrink-0 self-start md:self-center disabled:opacity-50 cursor-pointer active:scale-95"
                    >
                        <RefreshCw v-if="isBatchEnriching" class="w-4 h-4 animate-spin" />
                        <Wand2 v-else class="w-4 h-4" />
                        <span>{{ isRTL ? 'معالجة ومطابقة شاملة تلقائية' : 'Auto-Fix All Unmatched' }}</span>
                    </button>
                </div>

                <!-- Live Quick Stats Counter -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mt-8 pt-6 border-t border-white/10">
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/5">
                        <span class="text-xs text-slate-400 block">{{ isRTL ? 'إجمالي العناصر' : 'Total Items' }}</span>
                        <span class="text-xl font-black text-white mt-1 block">{{ stats.total_items }}</span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-amber-500/5 border border-amber-500/10">
                        <span class="text-xs text-amber-300 block">{{ isRTL ? 'غير مطابق' : 'Unmatched' }}</span>
                        <span class="text-xl font-black text-amber-400 mt-1 block">{{ stats.unmatched_count ?? 0 }}</span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-rose-500/5 border border-rose-500/10">
                        <span class="text-xs text-rose-300 block">{{ isRTL ? 'بدون بوستر' : 'Missing Posters' }}</span>
                        <span class="text-xl font-black text-rose-400 mt-1 block">{{ stats.missing_posters_count }}</span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-purple-500/5 border border-purple-500/10">
                        <span class="text-xs text-purple-300 block">{{ isRTL ? 'بدون عربي' : 'Missing Arabic' }}</span>
                        <span class="text-xl font-black text-purple-400 mt-1 block">{{ stats.missing_arabic_count }}</span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-cyan-500/5 border border-cyan-500/10">
                        <span class="text-xs text-cyan-300 block">{{ isRTL ? 'أفلام' : 'Movies' }}</span>
                        <span class="text-xl font-black text-cyan-400 mt-1 block">{{ stats.movies_count }}</span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-emerald-500/5 border border-emerald-500/10">
                        <span class="text-xs text-emerald-300 block">{{ isRTL ? 'مسلسلات' : 'Series' }}</span>
                        <span class="text-xl font-black text-emerald-400 mt-1 block">{{ stats.series_count }}</span>
                    </div>
                </div>
            </div>

            <!-- Triage Filters & Search Bar -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <!-- Filter Tabs -->
                <div class="flex flex-wrap items-center gap-1.5 p-1.5 bg-slate-900/90 rounded-2xl border border-white/10 w-full md:w-auto">
                    <button
                        @click="applyFilter('all')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeFilter === 'all' ? 'bg-cyan-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'"
                    >
                        {{ isRTL ? 'الكل' : 'All Media' }}
                    </button>
                    <button
                        @click="applyFilter('unmatched')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeFilter === 'unmatched' ? 'bg-amber-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'"
                    >
                        {{ isRTL ? 'غير مطابق' : 'Unmatched' }}
                        <span v-if="stats.unmatched_count" class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] bg-black/30">
                            {{ stats.unmatched_count }}
                        </span>
                    </button>
                    <button
                        @click="applyFilter('missing_posters')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeFilter === 'missing_posters' ? 'bg-rose-500 text-white shadow-md' : 'text-slate-400 hover:text-white'"
                    >
                        {{ isRTL ? 'بدون بوستر' : 'Missing Posters' }}
                    </button>
                    <button
                        @click="applyFilter('missing_arabic')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeFilter === 'missing_arabic' ? 'bg-purple-500 text-white shadow-md' : 'text-slate-400 hover:text-white'"
                    >
                        {{ isRTL ? 'بدون عربي' : 'Missing Arabic' }}
                    </button>
                    <button
                        @click="applyFilter('movies')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeFilter === 'movies' ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'"
                    >
                        {{ isRTL ? 'أفلام فقط' : 'Movies Only' }}
                    </button>
                    <button
                        @click="applyFilter('series')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeFilter === 'series' ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'"
                    >
                        {{ isRTL ? 'مسلسلات فقط' : 'Series Only' }}
                    </button>
                </div>

                <!-- Instant Search Input -->
                <div class="relative w-full md:w-80">
                    <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input
                        v-model="searchQuery"
                        @keyup.enter="handleSearch"
                        type="text"
                        :placeholder="isRTL ? 'ابحث بالاسم أو المسار...' : 'Search title or disk path...'"
                        class="w-full bg-slate-900/90 border border-white/10 rounded-2xl pl-10 pr-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all font-mono"
                    />
                </div>
            </div>

            <!-- Items List Container -->
            <div v-if="items.length > 0" class="space-y-4">
                <div
                    v-for="item in items"
                    :key="`${item.type}-${item.id}`"
                    class="p-4 rounded-3xl bg-slate-900/60 hover:bg-slate-900/90 border border-white/10 hover:border-cyan-500/40 transition-all flex flex-col gap-3 group"
                >
                    <!-- Top Row: Poster, Title & Actions -->
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <!-- Left: Poster + Core Info -->
                        <div class="flex items-center gap-4 flex-1 min-w-0">
                            <!-- Poster Preview -->
                            <div class="w-14 aspect-[2/3] rounded-xl overflow-hidden bg-slate-950 shrink-0 border border-white/10 relative shadow-md">
                                <img
                                    v-if="item.poster_path"
                                    :src="item.poster_path"
                                    :alt="item.title"
                                    class="w-full h-full object-cover"
                                    loading="lazy"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center text-slate-700 bg-slate-900">
                                    <Film v-if="item.type === 'movie'" class="w-5 h-5 text-cyan-400/50" />
                                    <Tv v-else class="w-5 h-5 text-purple-400/50" />
                                </div>

                                <!-- Match Status Dot -->
                                <div
                                    class="absolute top-1 right-1 w-2.5 h-2.5 rounded-full border border-black/80 shadow-sm"
                                    :class="item.is_matched ? 'bg-emerald-400 shadow-emerald-400/50' : 'bg-amber-400 animate-pulse'"
                                    :title="item.is_matched ? 'TMDb Matched' : 'Unmatched / Pending Fix'"
                                ></div>
                            </div>

                            <!-- Title & Metadata Badges -->
                            <div class="space-y-1.5 min-w-0 flex-1">
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

                                    <span v-if="item.title_ar" class="text-xs text-cyan-300 truncate font-medium" dir="rtl">
                                        ({{ item.title_ar }})
                                    </span>

                                    <span v-if="item.release_year" class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-white/[0.06] text-slate-300">
                                        {{ item.release_year }}
                                    </span>
                                </div>

                                <!-- Status Badges -->
                                <div class="flex flex-wrap items-center gap-2">
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

                                    <span v-if="item.collection_name" class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-300 border border-cyan-500/20">
                                        <Layers class="w-2.5 h-2.5" />
                                        <span>{{ item.collection_name }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Action Buttons -->
                        <div class="flex items-center gap-2 shrink-0 self-end md:self-center">
                            <!-- Rename File on Disk -->
                            <button
                                @click="confirmRenameFile(item)"
                                :disabled="isOperating"
                                class="p-2.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/20 text-emerald-300 text-xs font-bold transition-all cursor-pointer"
                                :title="isRTL ? 'إعادة تسمية الملف الفعلي على القرص ليطابق العنوان النظيف' : 'Rename physical file on disk to clean standard title'"
                            >
                                <FileEdit class="w-4 h-4" />
                            </button>

                            <!-- Quick Re-Parse -->
                            <button
                                @click="quickReparse(item)"
                                :disabled="isOperating"
                                class="p-2.5 rounded-xl bg-white/[0.05] hover:bg-white/[0.1] border border-white/10 text-slate-300 hover:text-cyan-300 text-xs font-bold transition-all cursor-pointer"
                                :title="isRTL ? 'إعادة تفكيك اسم الملف بالخوارزمية الذكية' : 'Re-parse file with intelligent parser'"
                            >
                                <Repeat class="w-4 h-4" />
                            </button>

                            <!-- Quick Convert Type -->
                            <button
                                @click="quickConvert(item)"
                                :disabled="isOperating"
                                class="p-2.5 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/20 text-purple-300 text-xs font-bold transition-all cursor-pointer"
                                :title="item.type === 'movie' ? (isRTL ? 'تحويل لمسلسل' : 'Convert to Series') : (isRTL ? 'تحويل لفيلم' : 'Convert to Movie')"
                            >
                                <ArrowRightLeft class="w-4 h-4" />
                            </button>

                            <!-- Fix Match Modal Trigger -->
                            <button
                                @click="openFixMatch(item)"
                                class="px-4 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs flex items-center gap-1.5 shadow-md shadow-cyan-500/10 transition-all cursor-pointer active:scale-95"
                            >
                                <Wand2 class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'إصلاح ومطابقة' : 'Fix Match' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Bottom Row: Full Physical Storage File Path Box (Un-truncated, Fully Visible & Copyable) -->
                    <div class="p-2.5 rounded-2xl bg-slate-950/80 border border-white/5 hover:border-white/10 transition-colors flex items-center justify-between gap-3 shadow-inner">
                        <div class="flex items-start sm:items-center gap-2 overflow-hidden min-w-0 flex-1">
                            <FileVideo v-if="item.type === 'movie'" class="w-3.5 h-3.5 text-cyan-400 shrink-0 mt-0.5 sm:mt-0" />
                            <Folder v-else class="w-3.5 h-3.5 text-purple-400 shrink-0 mt-0.5 sm:mt-0" />
                            <div class="min-w-0 flex-1">
                                <span class="font-mono text-slate-300 select-all break-all text-[11px] leading-relaxed block">
                                    {{ item.file_path || item.folder_path || 'No Path' }}
                                </span>
                            </div>
                        </div>
                        <button
                            @click="copyFilePath(item)"
                            type="button"
                            class="px-2.5 py-1 rounded-lg bg-white/[0.06] hover:bg-white/[0.12] text-slate-300 text-[11px] font-semibold shrink-0 transition-all flex items-center gap-1 border border-white/5 hover:border-cyan-500/30 cursor-pointer active:scale-95"
                            :title="isRTL ? 'نسخ المسار بالكامل' : 'Copy full file path'"
                        >
                            <Check v-if="copiedItemId === item.id" class="w-3 h-3 text-emerald-400" />
                            <Copy v-else class="w-3 h-3 text-slate-400" />
                            <span>{{ copiedItemId === item.id ? (isRTL ? 'تم النسخ!' : 'Copied!') : (isRTL ? 'نسخ المسار' : 'Copy') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="p-16 rounded-3xl border border-dashed border-white/10 text-center space-y-4">
                <div class="w-16 h-16 rounded-full bg-slate-900 border border-white/10 flex items-center justify-center mx-auto text-slate-600">
                    <CheckCircle2 class="w-8 h-8 text-emerald-400" />
                </div>
                <h3 class="text-xl font-bold text-white">
                    {{ isRTL ? 'لا توجد عناصر بحاجة للإصلاح في هذا القسم' : 'All Media Clean & Matched!' }}
                </h3>
                <p class="text-slate-400 text-sm max-w-md mx-auto">
                    {{ isRTL ? 'جميع ملفات الوسائط الخاصة بك تمت مطابقتها بنجاح مع البوسترات والعناوين.' : 'All media items in your library are properly indexed and matched with online metadata.' }}
                </p>
            </div>
        </div>

        <!-- Fix Match Studio Modal -->
        <FixMatchModal
            :show="showFixModal"
            :item="selectedItemForFix"
            :type="selectedItemForFix?.type"
            @close="showFixModal = false"
            @updated="handleItemUpdated"
        />

        <!-- Confirm Modal Dialog -->
        <ConfirmModal
            :show="confirmModal.show"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :confirm-text="confirmModal.confirmText"
            :type="confirmModal.type"
            @confirm="confirmModal.action"
            @close="confirmModal.show = false"
        />
    </AppLayout>
</template>
