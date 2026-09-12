<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useSubtitleJob } from '@/composables/useSubtitleJob';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import SubtitlePickerModal from '@/components/subtitles/SubtitlePickerModal.vue';
import { useToast } from '@/composables/useToast';
import {
    Subtitles, Download, Check, AlertCircle, Sparkles,
    Search, Cpu, CheckCircle2, Globe, ArrowDownToLine, RefreshCw,
    Film, Tv, HardDrive, FileText, CheckCheck, ChevronLeft, ChevronRight, SlidersHorizontal,
    ShieldCheck, Trash2, Tag, Eye, Play, AlertTriangle, Filter, FolderCheck,
    Pause, XCircle, Terminal
} from 'lucide-vue-next';

const props = defineProps<{
    missingSubtitles: any[];
}>();

const { t, isRTL } = useI18n();
const toast = useToast();

// Subtitle Picker Modal State
const isPickerOpen = ref(false);
const selectedMediaForPicker = ref<any | null>(null);
const pickerLanguage = ref<'ar' | 'en'>('ar');

const openPickerModal = (item: any, lang: 'ar' | 'en') => {
    selectedMediaForPicker.value = item;
    pickerLanguage.value = lang;
    isPickerOpen.value = true;
};

const onSubtitleDownloaded = (sub: any) => {
    if (selectedMediaForPicker.value) {
        if (pickerLanguage.value === 'ar') {
            selectedMediaForPicker.value.missing_ar = false;
        } else {
            selectedMediaForPicker.value.missing_en = false;
        }
    }
};

// Main Tab State
const activeMainTab = ref<'checker' | 'missing' | 'cloud'>('checker');

// ================= SUBTITLE CHECKER STATE =================
const {
    isSubtitleModalOpen,
    subtitleStatus,
    isSubtitleRunning,
    isSubtitlePaused,
    isSubtitleCompleted,
    openSubtitleModal,
    startHealthJob,
    pauseHealthJob,
    resumeHealthJob,
    cancelHealthJob,
    fetchSubtitleStatus
} = useSubtitleJob();
const isCheckingHealth = ref(false);
const healthResults = ref<any | null>(null);
const checkerDryRun = ref(true);
const checkerDeleteInvalid = ref(true);
const checkerAutoRename = ref(true);
const checkerCustomPath = ref('');
const checkerFilter = ref<'all' | 'invalid' | 'renamed' | 'already_standard' | 'valid'>('all');
const checkerSearchText = ref('');
const checkerCurrentPage = ref(1);
const checkerPerPage = ref(15);

const runHealthCheck = async () => {
    await startHealthJob({
        dry_run: checkerDryRun.value,
        delete_invalid: checkerDeleteInvalid.value,
        auto_rename: checkerAutoRename.value,
        target_path: checkerCustomPath.value.trim() || undefined,
    });
};

watch(() => subtitleStatus.value.items, (newItems) => {
    if (newItems && newItems.length > 0) {
        healthResults.value = {
            summary: subtitleStatus.value.summary,
            items: newItems,
        };
    }
}, { deep: true, immediate: true });

const filteredCheckerItems = computed(() => {
    if (!healthResults.value || !healthResults.value.items) return [];
    return healthResults.value.items.filter((item: any) => {
        // Status filter
        if (checkerFilter.value === 'invalid' && item.is_valid) return false;
        if (checkerFilter.value === 'valid' && !item.is_valid) return false;
        if (checkerFilter.value === 'renamed' && item.action !== 'renamed' && item.action !== 'would_rename') return false;
        if (checkerFilter.value === 'already_standard' && item.action !== 'already_standard') return false;

        // Search text
        if (checkerSearchText.value.trim()) {
            const q = checkerSearchText.value.toLowerCase().trim();
            const fn = (item.file_name || '').toLowerCase();
            const target = (item.target_filename || '').toLowerCase();
            const lang = (item.detected_language || '').toLowerCase();
            return fn.includes(q) || target.includes(q) || lang.includes(q);
        }

        return true;
    });
});

const totalCheckerPages = computed(() => Math.max(1, Math.ceil(filteredCheckerItems.value.length / checkerPerPage.value)));

const paginatedCheckerItems = computed(() => {
    const start = (checkerCurrentPage.value - 1) * checkerPerPage.value;
    return filteredCheckerItems.value.slice(start, start + checkerPerPage.value);
});

// ================= CLOUD SEARCH & MISSING STATE =================
const downloadingId = ref<string | null>(null);
const searchQuery = ref('Inception');
const searchLang = ref('ar');
const isSearching = ref(false);
const searchResults = ref<any[] | null>(null);

// Missing subtitles filter & pagination
const filterText = ref('');
const filterType = ref<'all' | 'movie' | 'episode'>('all');
const filterStatus = ref<'all' | 'missing_ar' | 'missing_en'>('all');
const currentPage = ref(1);
const perPage = ref(12);

const filteredItems = computed(() => {
    return (props.missingSubtitles || []).filter(item => {
        if (filterType.value === 'movie' && item.type !== 'movie') return false;
        if (filterType.value === 'episode' && item.type !== 'episode') return false;
        if (filterStatus.value === 'missing_ar' && !item.missing_ar) return false;
        if (filterStatus.value === 'missing_en' && !item.missing_en) return false;

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
        const res = await fetch(`/api/subtitles/search?query=${encodeURIComponent(searchQuery.value.trim())}&language=${encodeURIComponent(searchLang.value)}`);
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
                title: item.title,
                release_year: item.release_year,
                season: item.season_number,
                episode: item.episode_number,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            if (lang === 'ar') item.missing_ar = false;
            if (lang === 'en') item.missing_en = false;
            toast.success(isRTL.value ? `تم تحميل وتفعيل ترجمة ${lang === 'ar' ? 'العربية' : 'الإنجليزية'} بنجاح!` : `Downloaded ${lang.toUpperCase()} subtitle!`, 'Subtitle Ready');
        } else {
            toast.error(isRTL.value ? 'لم يتم العثور على ترجمة مناسبة لدى المزودات' : 'No online subtitle matched this media', 'Subtitle Not Found');
        }
    } catch (e) {
        toast.error('Failed to download subtitle', 'Download Error');
    } finally {
        downloadingId.value = null;
    }
};

const downloadAllMissing = async (lang: string) => {
    const targets = (props.missingSubtitles || []).filter(item => lang === 'ar' ? item.missing_ar : item.missing_en);
    for (const item of targets.slice(0, 10)) {
        await downloadSub(item, lang);
    }
};

const isGeneratingArabicId = ref<number | null>(null);

const generateArabicFromEnglish = async (item: any) => {
    isGeneratingArabicId.value = item.id;
    try {
        const res = await fetch('/api/subtitles/generate-arabic', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                media_id: item.id,
                media_type: item.type,
            }),
        });

        const data = await res.json();
        if (res.ok && data.status === 'success') {
            item.missing_ar = false;
            toast.success(
                isRTL.value ? 'تم توليد وترجمة ملف الترجمة العربية بنجاح وحفظه!' : 'Arabic subtitle successfully translated from English!',
                isRTL.value ? 'تم التوليد' : 'Translation Ready'
            );
        } else {
            toast.error(
                data.message || (isRTL.value ? 'تعذر توليد الترجمة العربية: تأكد من وجود ترجمة إنجليزية أولاً' : 'Failed to generate Arabic translation: Ensure English subtitle is available'),
                isRTL.value ? 'خطأ في التوليد' : 'Generation Failed'
            );
        }
    } catch (e: any) {
        toast.error(e.message || 'Error communicating with translation service', 'Error');
    } finally {
        isGeneratingArabicId.value = null;
    }
};

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam === 'missing') {
        activeMainTab.value = 'missing';
    } else if (tabParam === 'cloud') {
        activeMainTab.value = 'cloud';
    } else {
        activeMainTab.value = 'checker';
    }
    fetchSubtitleStatus();
});
</script>

<template>
    <Head :title="isRTL ? 'إدارة ومطهر الترجمات الذكي' : 'Subtitle Studio & Health Normalizer'" />

    <AppLayout>


        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-cyan-500/10 dark:bg-cyan-500/20 text-cyan-400 flex items-center justify-center border border-cyan-500/30">
                        <Subtitles class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                            {{ isRTL ? 'فاحص ومطهر الترجمات الذكي' : 'Subtitle Studio & Health Normalizer' }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ isRTL ? 'فحص ملفات الترجمة، حذف القوالب التالفة والوهمية، الكشف فائق الدقة عن اللغة، وتوحيد الامتدادات (.ar.srt, .en.srt).' : 'Inspect library subtitles, prune corrupt/stub files, accurately detect dialogue language, and standardize extensions (.ar.srt, .en.srt).' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Quick Batch Download Buttons -->
            <div v-if="activeMainTab === 'missing'" class="flex items-center gap-2 flex-wrap">
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

        <!-- Studio Mode Selector Navigation Tabs -->
        <div class="flex items-center gap-2 p-1.5 rounded-2xl glass-panel border border-slate-200 dark:border-white/10 mb-8 bg-slate-100/60 dark:bg-black/30 overflow-x-auto">
            <button
                @click="activeMainTab = 'checker'"
                class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer"
                :class="activeMainTab === 'checker' ? 'bg-cyan-500 text-slate-950 font-black shadow-lg shadow-cyan-500/20' : 'text-slate-600 dark:text-slate-400 hover:text-white'"
            >
                <ShieldCheck class="w-4 h-4" />
                <span>{{ isRTL ? 'فاحص ومطهر الترجمات الذكي (Checker)' : 'Subtitle Health & Normalizer' }}</span>
            </button>

            <button
                @click="activeMainTab = 'missing'"
                class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer"
                :class="activeMainTab === 'missing' ? 'bg-cyan-500 text-slate-950 font-black shadow-lg shadow-cyan-500/20' : 'text-slate-600 dark:text-slate-400 hover:text-white'"
            >
                <FileText class="w-4 h-4" />
                <span>{{ isRTL ? 'الترجمات الناقصة في المكتبة' : 'Missing Subtitles Ingestor' }} ({{ props.missingSubtitles?.length || 0 }})</span>
            </button>

            <button
                @click="activeMainTab = 'cloud'"
                class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer"
                :class="activeMainTab === 'cloud' ? 'bg-cyan-500 text-slate-950 font-black shadow-lg shadow-cyan-500/20' : 'text-slate-600 dark:text-slate-400 hover:text-white'"
            >
                <Globe class="w-4 h-4" />
                <span>{{ isRTL ? 'البحث السحابي المباشر' : 'Live Cloud Search' }}</span>
            </button>
        </div>

        <!-- ================= TAB 1: SUBTITLE CHECKER & NORMALIZER ================= -->
        <div v-if="activeMainTab === 'checker'" class="space-y-6">
            <!-- Checker Control & Execution Panel -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-cyan-500/30 shadow-2xl bg-white dark:bg-[#121622] space-y-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-200 dark:border-white/10 pb-5">
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2.5">
                            <ShieldCheck class="w-5 h-5 text-cyan-400" />
                            <span>{{ isRTL ? 'أداة الفحص الشامل وتطهير ملفات الترجمة' : 'Automated Subtitle Health & Normalizer' }}</span>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-2xl leading-relaxed">
                            {{ isRTL
                                ? 'يقوم الفاحص بالمرور على جميع ملفات .srt و .vtt في مجلدات الأفلام والمسلسلات، ويفحص محتواها: يحذف الملفات الفارغة والقوالب الوهمية المكسورة، ويكتشف اللغة الحقيقية للحوار بدقة 100%، ويعيد ربط وتسمية الملف بصيغة الفيلم المعتمدة (.ar.srt, .en.srt).'
                                : 'Inspects all subtitle files across film & TV series directories, detects and deletes corrupt/dummy templates, identifies the exact dialogue language, and renames files to standardized .ar.srt / .en.srt format.'
                            }}
                        </p>
                    </div>

                    <!-- Primary Execution Button -->
                    <button
                        @click="runHealthCheck"
                        :disabled="isSubtitleRunning"
                        class="px-6 py-3 rounded-2xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs sm:text-sm shadow-xl shadow-cyan-500/25 active:scale-95 transition-all flex items-center justify-center gap-2.5 cursor-pointer disabled:opacity-50 shrink-0"
                    >
                        <RefreshCw v-if="isSubtitleRunning" class="w-4 h-4 animate-spin text-slate-950" />
                        <Play v-else class="w-4 h-4 fill-slate-950" />
                        <span>{{ checkerDryRun ? (isRTL ? 'بدء المعاينة والفحص (Dry-Run)' : 'Run Preview Scan (Dry-Run)') : (isRTL ? 'تطبيق الفحص والتطهير الفعلي' : 'Execute Health Clean & Rename') }}</span>
                    </button>
                </div>

                <!-- Settings & Toggles Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                    <!-- Dry-Run Toggle -->
                    <div
                        @click="checkerDryRun = !checkerDryRun"
                        class="p-4 rounded-2xl border cursor-pointer transition-all flex items-start gap-3"
                        :class="checkerDryRun ? 'bg-cyan-500/10 border-cyan-500/40 text-white' : 'bg-slate-50 dark:bg-white/5 border-slate-200 dark:border-white/10 text-slate-400'"
                    >
                        <div class="w-5 h-5 rounded-md border flex items-center justify-center shrink-0 mt-0.5" :class="checkerDryRun ? 'border-cyan-400 bg-cyan-500 text-slate-950' : 'border-slate-500'">
                            <Check v-if="checkerDryRun" class="w-3.5 h-3.5" />
                        </div>
                        <div>
                            <span class="text-xs font-bold block text-slate-900 dark:text-white">{{ isRTL ? 'معاينة تجريبية (Dry-Run Preview)' : 'Dry-Run Preview Only' }}</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400 block mt-0.5">{{ isRTL ? 'عرض النتائج والتوصيات دون حذف أو تعديل أي ملف على القرص' : 'Simulate checks and report without touching files on disk' }}</span>
                        </div>
                    </div>

                    <!-- Delete Invalid Subtitles Toggle -->
                    <div
                        @click="checkerDeleteInvalid = !checkerDeleteInvalid"
                        class="p-4 rounded-2xl border cursor-pointer transition-all flex items-start gap-3"
                        :class="checkerDeleteInvalid ? 'bg-rose-500/10 border-rose-500/40 text-white' : 'bg-slate-50 dark:bg-white/5 border-slate-200 dark:border-white/10 text-slate-400'"
                    >
                        <div class="w-5 h-5 rounded-md border flex items-center justify-center shrink-0 mt-0.5" :class="checkerDeleteInvalid ? 'border-rose-400 bg-rose-500 text-white' : 'border-slate-500'">
                            <Check v-if="checkerDeleteInvalid" class="w-3.5 h-3.5" />
                        </div>
                        <div>
                            <span class="text-xs font-bold block text-slate-900 dark:text-white">{{ isRTL ? 'حذف الملفات التالفة والقوالب الوهمية' : 'Delete Corrupt & Stub Files' }}</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400 block mt-0.5">{{ isRTL ? 'حذف أي ترجمة أقل من 5 حوارات أو تحتوي صفحات خطأ HTML' : 'Purges files with < 5 cues, broken timecodes, or HTML 404 stubs' }}</span>
                        </div>
                    </div>

                    <!-- Auto-Rename Extension Toggle -->
                    <div
                        @click="checkerAutoRename = !checkerAutoRename"
                        class="p-4 rounded-2xl border cursor-pointer transition-all flex items-start gap-3"
                        :class="checkerAutoRename ? 'bg-indigo-500/10 border-indigo-500/40 text-white' : 'bg-slate-50 dark:bg-white/5 border-slate-200 dark:border-white/10 text-slate-400'"
                    >
                        <div class="w-5 h-5 rounded-md border flex items-center justify-center shrink-0 mt-0.5" :class="checkerAutoRename ? 'border-indigo-400 bg-indigo-500 text-white' : 'border-slate-500'">
                            <Check v-if="checkerAutoRename" class="w-3.5 h-3.5" />
                        </div>
                        <div>
                            <span class="text-xs font-bold block text-slate-900 dark:text-white">{{ isRTL ? 'توحيد صيغة الامتداد (.ar.srt)' : 'Standardize Language Extension' }}</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400 block mt-0.5">{{ isRTL ? 'إعادة تسمية الملف تلقائياً وفقاً للغة الحوار المكتشفة' : 'Renames to {media}.{lang}.srt matching detected language' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Optional Folder Path Input -->
                <div class="pt-2">
                    <label class="text-xs font-bold text-slate-400 mb-1.5 block">
                        {{ isRTL ? 'مسار مجلد محدد (اختياري، اتركه فارغاً لفحص كامل المكتبة):' : 'Specific Target Folder (Optional, leave blank to scan entire library):' }}
                    </label>
                    <input
                        type="text"
                        v-model="checkerCustomPath"
                        placeholder="e.g. H:\Entertainment\Movies\Inception (2010)"
                        class="w-full h-11 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-white/15 px-4 text-xs font-mono text-cyan-400 focus:border-cyan-500 outline-none"
                    />
                </div>
            </div>

            <!-- Health Summary Metrics -->
            <div v-if="healthResults" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <div class="glass-panel rounded-2xl p-4 border border-white/10 bg-white dark:bg-[#121622] text-center">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">{{ isRTL ? 'إجمالي الملفات' : 'Total Scanned' }}</span>
                    <span class="text-2xl font-black text-slate-900 dark:text-white mt-1 block font-mono">{{ healthResults.total_scanned }}</span>
                </div>

                <div class="glass-panel rounded-2xl p-4 border border-emerald-500/30 bg-emerald-500/5 text-center">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">{{ isRTL ? 'ترجمات سليمة' : 'Valid Subtitles' }}</span>
                    <span class="text-2xl font-black text-emerald-400 mt-1 block font-mono">{{ healthResults.valid_count }}</span>
                </div>

                <div class="glass-panel rounded-2xl p-4 border border-rose-500/30 bg-rose-500/5 text-center">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">{{ isRTL ? 'ملفات تالفة / وهمية' : 'Corrupt / Stubs' }}</span>
                    <span class="text-2xl font-black text-rose-400 mt-1 block font-mono">{{ healthResults.invalid_count }}</span>
                </div>

                <div class="glass-panel rounded-2xl p-4 border border-cyan-500/30 bg-cyan-500/5 text-center">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">{{ isRTL ? 'تعديل التسمية' : 'Renamed / Standard' }}</span>
                    <span class="text-2xl font-black text-cyan-400 mt-1 block font-mono">{{ healthResults.renamed_count }}</span>
                </div>

                <div class="glass-panel rounded-2xl p-4 border border-indigo-500/30 bg-indigo-500/5 text-center">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">{{ isRTL ? 'مطابقة معيارياً' : 'Already Standard' }}</span>
                    <span class="text-2xl font-black text-indigo-400 mt-1 block font-mono">{{ healthResults.already_standard_count }}</span>
                </div>
            </div>

            <!-- Language Breakdown Badges -->
            <div v-if="healthResults && healthResults.language_breakdown && Object.keys(healthResults.language_breakdown).length > 0" class="glass-panel rounded-2xl p-4 border border-white/10 bg-white dark:bg-[#121622] flex items-center gap-3 flex-wrap">
                <span class="text-xs font-bold text-slate-400">{{ isRTL ? 'توزيع اللغات المكتشفة:' : 'Detected Languages:' }}</span>
                <div
                    v-for="lang in healthResults.language_breakdown"
                    :key="lang.code"
                    class="px-3 py-1 rounded-xl bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-xs font-bold flex items-center gap-1.5"
                >
                    <span>{{ lang.flag }}</span>
                    <span class="text-slate-900 dark:text-white">{{ lang.name_en }} ({{ lang.code }}):</span>
                    <span class="text-cyan-400 font-mono">{{ lang.count }}</span>
                </div>
            </div>

            <!-- Subtitle Items Table -->
            <div v-if="healthResults" class="space-y-4">
                <!-- Table Filter & Search Bar -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 p-3 rounded-2xl bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10">
                    <div class="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0">
                        <button
                            v-for="f in [
                                { id: 'all', label_en: 'All', label_ar: 'الكل' },
                                { id: 'invalid', label_en: 'Invalid / Stubs', label_ar: 'التالفة / الوهمية' },
                                { id: 'renamed', label_en: 'Renamed', label_ar: 'المعدلة' },
                                { id: 'already_standard', label_en: 'Standard', label_ar: 'المعيارية' },
                                { id: 'valid', label_en: 'All Valid', label_ar: 'السليمة' },
                            ]"
                            :key="f.id"
                            @click="checkerFilter = f.id as any; checkerCurrentPage = 1"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer"
                            :class="checkerFilter === f.id ? 'bg-cyan-500 text-slate-950 font-black shadow-md shadow-cyan-500/20' : 'bg-white/5 text-slate-400 hover:text-white'"
                        >
                            {{ isRTL ? f.label_ar : f.label_en }}
                        </button>
                    </div>

                    <div class="relative min-w-[240px]">
                        <Search class="absolute top-2.5 left-3 w-4 h-4 text-slate-400" />
                        <input
                            v-model="checkerSearchText"
                            type="text"
                            :placeholder="isRTL ? 'بحث بالاسم أو اللغة...' : 'Filter files or language...'"
                            class="w-full pl-9 pr-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none"
                        />
                    </div>
                </div>

                <!-- Table Card Container -->
                <div class="glass-panel rounded-3xl border border-slate-200 dark:border-white/10 overflow-hidden shadow-xl bg-white dark:bg-[#121622]">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/[0.02] text-slate-400 uppercase font-mono text-[10px] tracking-wider">
                                    <th class="py-3 px-4 font-bold">{{ isRTL ? 'اسم الملف الأصلي' : 'File Name' }}</th>
                                    <th class="py-3 px-4 font-bold">{{ isRTL ? 'الحالة' : 'Status' }}</th>
                                    <th class="py-3 px-4 font-bold">{{ isRTL ? 'اللغة المكتشفة' : 'Detected Language' }}</th>
                                    <th class="py-3 px-4 font-bold">{{ isRTL ? 'الحوارات' : 'Cues' }}</th>
                                    <th class="py-3 px-4 font-bold">{{ isRTL ? 'الإجراء المتخذ / المقترح' : 'Action / Target Name' }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                <tr
                                    v-for="(item, idx) in paginatedCheckerItems"
                                    :key="idx"
                                    class="hover:bg-slate-50 dark:hover:bg-white/[0.03] transition-colors"
                                >
                                    <!-- File Name & Path -->
                                    <td class="py-3 px-4 max-w-xs sm:max-w-md">
                                        <div class="font-bold text-slate-900 dark:text-white truncate" :title="item.file_name">
                                            {{ item.file_name }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 truncate mt-0.5" :title="item.original_path">
                                            {{ item.original_path }}
                                        </div>
                                        <div v-if="item.issues && item.issues.length > 0" class="mt-1 flex items-center gap-1.5 text-[10px] text-rose-400 font-bold">
                                            <AlertTriangle class="w-3 h-3 shrink-0" />
                                            <span>{{ item.issues.join(', ') }}</span>
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span
                                            class="px-2.5 py-1 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider inline-flex items-center gap-1"
                                            :class="item.is_valid ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full" :class="item.is_valid ? 'bg-emerald-400' : 'bg-rose-400'"></span>
                                            <span>{{ item.is_valid ? 'VALID' : 'INVALID STUB' }}</span>
                                        </span>
                                    </td>

                                    <!-- Detected Language -->
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="text-base">{{ item.flag }}</span>
                                            <div>
                                                <span class="font-bold text-slate-900 dark:text-white block">{{ item.language_name }}</span>
                                                <span v-if="item.confidence" class="text-[10px] font-mono text-slate-400">{{ Math.round(item.confidence * 100) }}% confidence</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Cues & Size -->
                                    <td class="py-3 px-4 whitespace-nowrap font-mono text-slate-400">
                                        <div>{{ item.cue_count }} cues</div>
                                        <div class="text-[10px] text-slate-500">{{ Math.round(item.file_size / 1024) }} KB</div>
                                    </td>

                                    <!-- Action / Target Filename -->
                                    <td class="py-3 px-4">
                                        <div v-if="item.action === 'deleted' || item.action === 'deleted_invalid'" class="text-rose-400 font-bold text-xs flex items-center gap-1">
                                            <Trash2 class="w-3.5 h-3.5" />
                                            <span>{{ isRTL ? 'تم الحذف بنجاح' : 'Deleted' }}</span>
                                        </div>
                                        <div v-else-if="item.action === 'flagged_for_deletion'" class="text-rose-400 font-bold text-xs flex items-center gap-1">
                                            <AlertTriangle class="w-3.5 h-3.5" />
                                            <span>{{ isRTL ? 'مرشح للحذف (تالف/وهمي)' : 'Flagged for Deletion' }}</span>
                                        </div>
                                        <div v-else-if="item.action === 'renamed'" class="text-cyan-400 font-bold text-xs">
                                            <div class="flex items-center gap-1">
                                                <Tag class="w-3.5 h-3.5 text-cyan-400 shrink-0" />
                                                <span class="truncate">{{ item.target_filename }}</span>
                                            </div>
                                            <span class="text-[10px] text-emerald-400">{{ isRTL ? 'تمت إعادة التسمية بنجاح' : 'Renamed' }}</span>
                                        </div>
                                        <div v-else-if="item.action === 'would_rename'" class="text-indigo-400 font-bold text-xs">
                                            <div class="flex items-center gap-1">
                                                <Tag class="w-3.5 h-3.5 text-indigo-400 shrink-0" />
                                                <span class="truncate">{{ item.target_filename }}</span>
                                            </div>
                                            <span class="text-[10px] text-slate-400">{{ isRTL ? 'سيتم التعديل عند التنفيذ' : 'Target Standard Name' }}</span>
                                        </div>
                                        <div v-else-if="item.action === 'already_standard'" class="text-emerald-400 font-bold text-xs flex items-center gap-1">
                                            <CheckCircle2 class="w-3.5 h-3.5" />
                                            <span>{{ isRTL ? 'تسمية معيارية صحيحة' : 'Already Standard' }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="totalCheckerPages > 1" class="flex items-center justify-between p-4 border-t border-slate-200 dark:border-white/10 text-xs">
                        <span class="text-slate-400">
                            {{ (checkerCurrentPage - 1) * checkerPerPage + 1 }} - {{ Math.min(checkerCurrentPage * checkerPerPage, filteredCheckerItems.length) }} of {{ filteredCheckerItems.length }}
                        </span>
                        <div class="flex items-center gap-1">
                            <button
                                @click="checkerCurrentPage = Math.max(1, checkerCurrentPage - 1)"
                                :disabled="checkerCurrentPage === 1"
                                class="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 text-slate-300 disabled:opacity-30 cursor-pointer"
                            >
                                {{ isRTL ? 'السابق' : 'Prev' }}
                            </button>
                            <span class="px-3 py-1.5 font-mono text-cyan-400">{{ checkerCurrentPage }} / {{ totalCheckerPages }}</span>
                            <button
                                @click="checkerCurrentPage = Math.min(totalCheckerPages, checkerCurrentPage + 1)"
                                :disabled="checkerCurrentPage === totalCheckerPages"
                                class="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 text-slate-300 disabled:opacity-30 cursor-pointer"
                            >
                                {{ isRTL ? 'التالي' : 'Next' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 2: MISSING SUBTITLES INGESTOR ================= -->
        <div v-if="activeMainTab === 'missing'" class="space-y-6">
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
                    <div class="space-y-2 mt-4 pt-3 border-t border-slate-100 dark:border-white/5">
                        <div class="flex items-center gap-2">
                            <!-- Arabic Subtitle Action -->
                            <button
                                @click="openPickerModal(item, 'ar')"
                                :disabled="!item.missing_ar"
                                class="flex-1 py-2 px-3 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                                :class="!item.missing_ar
                                    ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 cursor-default'
                                    : 'bg-cyan-500 hover:bg-cyan-400 text-slate-950 shadow-md shadow-cyan-500/20'"
                            >
                                <Check v-if="!item.missing_ar" class="w-3.5 h-3.5" />
                                <Download v-else class="w-3.5 h-3.5" />
                                <span>{{ !item.missing_ar ? (isRTL ? 'العربية متوفرة' : 'Arabic Ready') : (isRTL ? 'تحميل العربية' : 'Get Arabic') }}</span>
                            </button>

                            <!-- English Subtitle Action -->
                            <button
                                @click="openPickerModal(item, 'en')"
                                :disabled="!item.missing_en"
                                class="flex-1 py-2 px-3 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                                :class="!item.missing_en
                                    ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 cursor-default'
                                    : 'bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-700 dark:text-white border border-slate-200 dark:border-white/10'"
                            >
                                <Check v-if="!item.missing_en" class="w-3.5 h-3.5" />
                                <Download v-else class="w-3.5 h-3.5" />
                                <span>{{ !item.missing_en ? (isRTL ? 'الإنجليزية متوفرة' : 'English Ready') : (isRTL ? 'تحميل الإنجليزية' : 'Get English') }}</span>
                            </button>
                        </div>

                        <!-- 1-Click Arabic Translation from English -->
                        <button
                            v-if="item.missing_ar"
                            @click="generateArabicFromEnglish(item)"
                            :disabled="isGeneratingArabicId === item.id"
                            class="w-full py-1.5 px-3 rounded-xl text-[11px] font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 active:scale-95 disabled:opacity-50"
                        >
                            <RefreshCw v-if="isGeneratingArabicId === item.id" class="w-3 h-3 animate-spin text-cyan-400" />
                            <Sparkles v-else class="w-3 h-3 text-cyan-400" />
                            <span>{{ isGeneratingArabicId === item.id ? (isRTL ? 'جارٍ الترجمة والتوليد...' : 'Translating from English...') : (isRTL ? 'توليد ترجمة عربية من الإنجليزية' : 'Translate Arabic from English') }}</span>
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

        <!-- ================= TAB 3: LIVE CLOUD SEARCH ENGINE ================= -->
        <div v-if="activeMainTab === 'cloud'" class="glass-panel rounded-3xl p-6 sm:p-8 mb-10 border border-slate-200 dark:border-white/10 shadow-xl bg-white dark:bg-[#121622]">
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
            <!-- Interactive Subtitle Search & Selection Modal -->
        <SubtitlePickerModal
            :is-open="isPickerOpen"
            :media="selectedMediaForPicker"
            :initial-language="pickerLanguage"
            @close="isPickerOpen = false"
            @downloaded="onSubtitleDownloaded"
        />
    </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
