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
    Database, Filter, ArrowRight, Wand2
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
                ? `تم جلب وتحديث البيانات السحابية لـ ${data.result?.enriched_count || 0} عنصراً بنجاح!`
                : `Enriched ${data.result?.enriched_count || 0} items with Arabic metadata & artwork!`;
            setTimeout(() => {
                router.reload({ preserveState: false });
            }, 1200);
        }
    } catch (e) {
        toastMessage.value = isRTL.value ? 'حدث خطأ أثناء جلب البيانات.' : 'Error during metadata enrichment.';
    } finally {
        isBatchEnriching.value = false;
    }
};

const deleteItem = (item: any) => {
    confirmModal.value = {
        show: true,
        title: isRTL.value ? 'حذف من المكتبة' : 'Delete from Library',
        message: isRTL.value ? `هل أنت متأكد من حذف "${item.title}" من الفهرس وقاعدة البيانات؟` : `Are you sure you want to remove "${item.title}" from library database?`,
        confirmText: isRTL.value ? 'حذف' : 'Delete',
        type: 'danger',
        action: async () => {
            try {
                const res = await fetch(`/api/scanner/media/${item.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                    },
                });
                if (res.ok) {
                    router.reload({ preserveState: true });
                }
            } catch (e) {}
            confirmModal.value.show = false;
        },
    };
};
</script>

<template>
    <AppLayout>
        <Head :title="isRTL ? 'استوديو البيانات والأغلفة - Creative Stream' : 'Metadata & Cover Studio - Creative Stream'" />

        <div class="min-h-screen pb-20 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-8" :dir="isRTL ? 'rtl' : 'ltr'">
            <!-- Studio Hero Banner -->
            <div class="glass-panel p-6 sm:p-8 rounded-3xl border border-white/10 relative overflow-hidden bg-gradient-to-br from-slate-900/90 via-slate-900/60 to-cyan-950/30 shadow-2xl">
                <div class="absolute -right-20 -top-20 w-80 h-80 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -left-20 -bottom-20 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 rounded-full text-[11px] font-bold tracking-wider uppercase bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 flex items-center gap-1.5 shadow-sm">
                                <Sparkles class="w-3 h-3 text-cyan-400" />
                                <span>{{ isRTL ? 'استوديو البيانات الذكي' : 'Smart Metadata Studio' }}</span>
                            </span>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-mono bg-white/5 text-slate-400 border border-white/10">
                                TMDb & OMDb Online
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                            {{ isRTL ? 'إدارة البيانات الوصفية والأغلفة' : 'Library Metadata & Cover Studio' }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-400 max-w-2xl leading-relaxed">
                            {{ isRTL
                                ? 'فحص ومعاينة بطاقات الأفلام والمسلسلات، جلب العناوين والملخصات العربية، استبدال البوسترات والخلفيات بدقة سينمائية فائقة عبر TMDb و OMDb.'
                                : 'Inspect and curate your media library cards, enrich Arabic & English synopses, download high-res posters and backdrops with dual API providers.'
                            }}
                        </p>
                    </div>

                    <!-- Batch Actions -->
                    <div class="flex items-center gap-3 shrink-0 flex-wrap">
                        <button
                            type="button"
                            @click="enrichAllMissing"
                            :disabled="isBatchEnriching"
                            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-bold text-xs sm:text-sm flex items-center gap-2.5 shadow-lg shadow-cyan-500/25 transition-all active:scale-95 disabled:opacity-50 cursor-pointer"
                        >
                            <RefreshCw class="w-4 h-4" :class="isBatchEnriching ? 'animate-spin' : ''" />
                            <span>
                                {{ isBatchEnriching
                                    ? (isRTL ? 'جاري الجلب السحابي...' : 'Enriching via TMDb...')
                                    : (isRTL ? 'جلب وتحديث البيانات السحابية' : 'Enrich Missing Metadata')
                                }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Studio Stats Bar -->
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mt-8 pt-6 border-t border-white/10">
                    <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1">
                        <div class="text-[11px] text-slate-400 font-medium flex items-center gap-1.5">
                            <Database class="w-3.5 h-3.5 text-cyan-400" />
                            <span>{{ isRTL ? 'إجمالي العناصر' : 'Total Items' }}</span>
                        </div>
                        <div class="text-xl font-black text-white">{{ stats.total_items }}</div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1">
                        <div class="text-[11px] text-slate-400 font-medium flex items-center gap-1.5">
                            <Film class="w-3.5 h-3.5 text-blue-400" />
                            <span>{{ isRTL ? 'الأفلام' : 'Movies' }}</span>
                        </div>
                        <div class="text-xl font-black text-white">{{ stats.movies_count }}</div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1">
                        <div class="text-[11px] text-slate-400 font-medium flex items-center gap-1.5">
                            <Tv class="w-3.5 h-3.5 text-indigo-400" />
                            <span>{{ isRTL ? 'المسلسلات' : 'Series' }}</span>
                        </div>
                        <div class="text-xl font-black text-white">{{ stats.series_count }}</div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1" :class="stats.missing_arabic_count > 0 ? 'border-amber-500/30 bg-amber-500/5' : ''">
                        <div class="text-[11px] font-medium flex items-center gap-1.5" :class="stats.missing_arabic_count > 0 ? 'text-amber-400' : 'text-slate-400'">
                            <Globe class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'بحاجة لبيانات عربية' : 'Missing Arabic' }}</span>
                        </div>
                        <div class="text-xl font-black" :class="stats.missing_arabic_count > 0 ? 'text-amber-300' : 'text-white'">
                            {{ stats.missing_arabic_count }}
                        </div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1" :class="stats.missing_posters_count > 0 ? 'border-rose-500/30 bg-rose-500/5' : ''">
                        <div class="text-[11px] font-medium flex items-center gap-1.5" :class="stats.missing_posters_count > 0 ? 'text-rose-400' : 'text-slate-400'">
                            <ImageIcon class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'أغلفة مفقودة' : 'Missing Posters' }}</span>
                        </div>
                        <div class="text-xl font-black" :class="stats.missing_posters_count > 0 ? 'text-rose-300' : 'text-white'">
                            {{ stats.missing_posters_count }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast Notification -->
            <div v-if="toastMessage" class="p-4 rounded-2xl bg-cyan-500/20 border border-cyan-500/40 text-cyan-200 text-xs sm:text-sm font-semibold flex items-center gap-3 animate-in fade-in slide-in-from-top-2 duration-300 shadow-xl">
                <CheckCircle2 class="w-5 h-5 text-cyan-400 shrink-0" />
                <span>{{ toastMessage }}</span>
            </div>

            <!-- Filters & Search Toolbar -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <!-- Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto pb-2 md:pb-0 custom-scrollbar">
                    <button
                        type="button"
                        @click="applyFilter('all')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer border"
                        :class="activeFilter === 'all' ? 'bg-cyan-500 text-slate-950 border-cyan-400 shadow-md shadow-cyan-500/20' : 'bg-white/5 text-slate-300 border-white/10 hover:bg-white/10'"
                    >
                        {{ isRTL ? 'الكل' : 'All Media' }} ({{ stats.total_items }})
                    </button>

                    <button
                        type="button"
                        @click="applyFilter('missing_arabic')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer border flex items-center gap-1.5"
                        :class="activeFilter === 'missing_arabic' ? 'bg-amber-500 text-slate-950 border-amber-400 shadow-md shadow-amber-500/20' : 'bg-white/5 text-amber-300 border-white/10 hover:bg-white/10'"
                    >
                        <Globe class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'بيانات عربية ناقصة' : 'Missing Arabic' }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeFilter === 'missing_arabic' ? 'bg-black/20' : 'bg-amber-500/20 text-amber-300'">
                            {{ stats.missing_arabic_count }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="applyFilter('missing_posters')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer border flex items-center gap-1.5"
                        :class="activeFilter === 'missing_posters' ? 'bg-rose-500 text-slate-950 border-rose-400 shadow-md shadow-rose-500/20' : 'bg-white/5 text-rose-300 border-white/10 hover:bg-white/10'"
                    >
                        <ImageIcon class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'أغلفة مفقودة' : 'Missing Posters' }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeFilter === 'missing_posters' ? 'bg-black/20' : 'bg-rose-500/20 text-rose-300'">
                            {{ stats.missing_posters_count }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="applyFilter('movies')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer border flex items-center gap-1.5"
                        :class="activeFilter === 'movies' ? 'bg-blue-500 text-slate-950 border-blue-400' : 'bg-white/5 text-slate-300 border-white/10 hover:bg-white/10'"
                    >
                        <Film class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'أفلام' : 'Movies' }}</span>
                    </button>

                    <button
                        type="button"
                        @click="applyFilter('series')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer border flex items-center gap-1.5"
                        :class="activeFilter === 'series' ? 'bg-indigo-500 text-slate-950 border-indigo-400' : 'bg-white/5 text-slate-300 border-white/10 hover:bg-white/10'"
                    >
                        <Tv class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'مسلسلات' : 'Series' }}</span>
                    </button>
                </div>

                <!-- Search Input -->
                <form @submit.prevent="handleSearch" class="relative w-full md:w-72 shrink-0">
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="isRTL ? 'ابحث بالعنوان الإنجليزي أو العربي...' : 'Search title or Arabic name...'"
                        class="w-full pl-4 pr-10 py-2.5 rounded-2xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 transition-colors"
                        :class="isRTL ? 'text-right' : 'text-left'"
                    />
                    <button type="submit" class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-white cursor-pointer">
                        <Search class="w-4 h-4" />
                    </button>
                </form>
            </div>

            <!-- Media Grid Cards -->
            <div v-if="items.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                <div
                    v-for="item in items"
                    :key="`${item.type}_${item.id}`"
                    class="glass-panel rounded-3xl border border-white/10 bg-slate-900/60 overflow-hidden flex flex-col justify-between hover:border-cyan-500/40 hover:shadow-xl transition-all group duration-300"
                >
                    <div>
                        <!-- Media Card Artwork Header -->
                        <div class="relative aspect-video w-full overflow-hidden bg-slate-950">
                            <img
                                :src="item.backdrop_path || item.poster_path || '/placeholder.jpg'"
                                :alt="item.title"
                                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-transparent"></div>

                            <!-- Floating Badges -->
                            <div class="absolute top-3 left-3 flex items-center gap-1.5">
                                <span class="cinema-badge bg-black/70 backdrop-blur-md text-[10px] uppercase font-bold text-white border-white/15">
                                    {{ item.type === 'series' ? (isRTL ? 'مسلسل' : 'Series') : (isRTL ? 'فيلم' : 'Movie') }}
                                </span>
                                <span v-if="item.release_year" class="cinema-badge bg-black/70 backdrop-blur-md text-[10px] text-slate-300 border-white/15">
                                    {{ item.release_year }}
                                </span>
                            </div>

                            <div class="absolute top-3 right-3 flex items-center gap-1.5">
                                <span v-if="item.rating" class="cinema-badge bg-amber-500/90 text-slate-950 font-black text-[10px] flex items-center gap-0.5">
                                    <Star class="w-3 h-3 fill-current" />
                                    {{ item.rating }}
                                </span>
                            </div>

                            <!-- Poster Overlay Thumbnail -->
                            <div class="absolute bottom-3 left-3 w-12 h-16 rounded-xl overflow-hidden border border-white/20 shadow-2xl bg-slate-900 shrink-0">
                                <img
                                    :src="item.poster_path || '/placeholder.jpg'"
                                    :alt="item.title"
                                    class="w-full h-full object-cover"
                                />
                            </div>

                            <!-- Arabic Badge Indicator -->
                            <div class="absolute bottom-3 right-3">
                                <span
                                    v-if="item.has_arabic"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center gap-1"
                                >
                                    <Check class="w-3 h-3" />
                                    <span>{{ isRTL ? 'بيانات عربية متوفرة' : 'Arabic Ready' }}</span>
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 flex items-center gap-1"
                                >
                                    <AlertCircle class="w-3 h-3" />
                                    <span>{{ isRTL ? 'بحاجة لتعريب' : 'No Arabic' }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Card Details -->
                        <div class="p-4 space-y-2.5">
                            <div>
                                <h3 class="font-bold text-sm text-white truncate group-hover:text-cyan-400 transition-colors">
                                    {{ item.title }}
                                </h3>
                                <p v-if="item.title_ar" class="text-xs font-semibold text-cyan-300 truncate" dir="rtl">
                                    {{ item.title_ar }}
                                </p>
                            </div>

                            <p class="text-[11px] text-slate-400 line-clamp-2 leading-relaxed">
                                {{ item.overview_ar || item.overview || (isRTL ? 'لا يتوفر وصف موجز بعد.' : 'No overview available.') }}
                            </p>
                        </div>
                    </div>

                    <!-- Card Actions Footer -->
                    <div class="p-4 pt-0 border-t border-white/5 mt-2 flex items-center justify-between gap-2">
                        <button
                            type="button"
                            @click="openFixMatch(item)"
                            class="flex-1 px-3.5 py-2 rounded-xl bg-cyan-500/10 hover:bg-cyan-500 text-cyan-400 hover:text-slate-950 border border-cyan-500/20 font-bold text-xs flex items-center justify-center gap-1.5 transition-all active:scale-95 cursor-pointer shadow-sm"
                        >
                            <Sparkles class="w-3.5 h-3.5" />
                            <span>{{ isRTL ? 'استوديو المطابقة' : 'Fix Match' }}</span>
                        </button>

                        <button
                            type="button"
                            @click="deleteItem(item)"
                            class="w-8 h-8 rounded-xl bg-white/5 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 border border-white/10 flex items-center justify-center transition-colors cursor-pointer"
                            :title="isRTL ? 'حذف من المكتبة' : 'Delete from Library'"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="glass-panel p-12 rounded-3xl border border-white/10 text-center space-y-4 max-w-md mx-auto">
                <div class="w-14 h-14 rounded-2xl bg-white/5 text-slate-400 flex items-center justify-center mx-auto">
                    <Database class="w-6 h-6" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-white">
                        {{ isRTL ? 'لم يتم العثور على عناصر تطابق هذا الفلتر' : 'No items match your filter' }}
                    </h3>
                    <p class="text-xs text-slate-400">
                        {{ isRTL ? 'جرب البحث باسم آخر أو إعادة ضبط الفلاتر.' : 'Try changing search terms or switching the category filter.' }}
                    </p>
                </div>
                <button
                    type="button"
                    @click="applyFilter('all'); searchQuery = ''; handleSearch()"
                    class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs transition-all cursor-pointer"
                >
                    {{ isRTL ? 'عرض جميع العناصر' : 'Show All Media' }}
                </button>
            </div>
        </div>

        <!-- Modals -->
        <FixMatchModal
            :show="showFixModal"
            :item="selectedItemForFix"
            :type="selectedItemForFix?.type"
            @close="showFixModal = false"
            @updated="handleItemUpdated"
        />

        <ConfirmModal
            :show="confirmModal.show"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :confirm-text="confirmModal.confirmText"
            :type="confirmModal.type"
            @confirm="confirmModal.action"
            @cancel="confirmModal.show = false"
        />
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    height: 4px;
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 9999px;
}
</style>
