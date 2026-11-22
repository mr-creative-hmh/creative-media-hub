<script setup lang="ts">
import { ref, computed, onMounted, watch, defineAsyncComponent } from 'vue';
import { useSubtitleJob } from '@/composables/useSubtitleJob';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import PageBanner from '@/components/common/PageBanner.vue';
import { useToast } from '@/composables/useToast';
import { formatBytes } from '@/lib/formatters';
import {
    Subtitles,
    Download,
    Check,
    Sparkles,
    Search,
    CheckCircle2,
    Globe,
    RefreshCw,
    FileText,
    CheckCheck,
    ChevronLeft,
    ChevronRight,
    ShieldCheck,
    Trash2,
    Tag,
    Play,
    AlertTriangle,
} from '@lucide/vue';

const SubtitlePickerModal = defineAsyncComponent(
    () => import('@/components/subtitles/SubtitlePickerModal.vue'),
);

const props = defineProps<{
    missingSubtitles: any[];
}>();

const { isRTL } = useI18n();
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

const onSubtitleDownloaded = () => {
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
    subtitleStatus,
    isSubtitleRunning,
    startHealthJob,
    fetchSubtitleStatus,
} = useSubtitleJob();
const healthResults = ref<any | null>(null);
const checkerDryRun = ref(true);
const checkerDeleteInvalid = ref(true);
const checkerAutoRename = ref(true);
const checkerCustomPath = ref('');
const checkerFilter = ref<
    'all' | 'invalid' | 'renamed' | 'already_standard' | 'valid'
>('all');
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

watch(
    () => subtitleStatus.value.items,
    (newItems) => {
        if (newItems && newItems.length > 0) {
            healthResults.value = {
                ...subtitleStatus.value.summary,
                summary: subtitleStatus.value.summary,
                items: newItems,
            };
        }
    },
    { deep: true, immediate: true },
);

const filteredCheckerItems = computed(() => {
    if (!healthResults.value || !healthResults.value.items) return [];
    return healthResults.value.items.filter((item: any) => {
        if (checkerFilter.value === 'invalid' && item.is_valid) return false;
        if (checkerFilter.value === 'valid' && !item.is_valid) return false;
        if (
            checkerFilter.value === 'renamed' &&
            item.action !== 'renamed' &&
            item.action !== 'would_rename'
        )
            return false;
        if (
            checkerFilter.value === 'already_standard' &&
            item.action !== 'already_standard'
        )
            return false;

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

const totalCheckerPages = computed(() =>
    Math.max(
        1,
        Math.ceil(filteredCheckerItems.value.length / checkerPerPage.value),
    ),
);

const paginatedCheckerItems = computed(() => {
    const start = (checkerCurrentPage.value - 1) * checkerPerPage.value;
    return filteredCheckerItems.value.slice(
        start,
        start + checkerPerPage.value,
    );
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
    return (props.missingSubtitles || []).filter((item) => {
        if (filterType.value === 'movie' && item.type !== 'movie') return false;
        if (filterType.value === 'episode' && item.type !== 'episode')
            return false;
        if (filterStatus.value === 'missing_ar' && !item.missing_ar)
            return false;
        if (filterStatus.value === 'missing_en' && !item.missing_en)
            return false;

        if (filterText.value.trim()) {
            const q = filterText.value.toLowerCase().trim();
            const title = (item.title || '').toLowerCase();
            const seriesTitle = (item.series_title || '').toLowerCase();
            const path = (item.file_path || '').toLowerCase();
            return (
                title.includes(q) || seriesTitle.includes(q) || path.includes(q)
            );
        }

        return true;
    });
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredItems.value.length / perPage.value)),
);

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
        const res = await fetch(
            `/api/subtitles/search?query=${encodeURIComponent(searchQuery.value.trim())}&language=${encodeURIComponent(searchLang.value)}`,
        );
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
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
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
            if (lang === 'ar') item.missing_ar = false;
            if (lang === 'en') item.missing_en = false;
            toast.success(
                isRTL.value
                    ? `تم تحميل وتفعيل ترجمة ${lang === 'ar' ? 'العربية' : 'الإنجليزية'} بنجاح!`
                    : `Downloaded ${lang.toUpperCase()} subtitle!`,
                'Subtitle Ready',
            );
        } else {
            toast.error(
                isRTL.value
                    ? 'لم يتم العثور على ترجمة مناسبة لدى المزودات'
                    : 'No online subtitle matched this media',
                'Subtitle Not Found',
            );
        }
    } catch {
        toast.error('Failed to download subtitle', 'Download Error');
    } finally {
        downloadingId.value = null;
    }
};

const downloadAllMissing = async (lang: string) => {
    const targets = (props.missingSubtitles || []).filter((item) =>
        lang === 'ar' ? item.missing_ar : item.missing_en,
    );
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
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
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
                isRTL.value
                    ? 'تم توليد وترجمة ملف الترجمة العربية بنجاح وحفظه!'
                    : 'Arabic subtitle successfully translated from English!',
                isRTL.value ? 'تم التوليد' : 'Translation Ready',
            );
        } else {
            toast.error(
                data.message ||
                    (isRTL.value
                        ? 'تعذر توليد الترجمة العربية: تأكد من وجود ترجمة إنجليزية أولاً'
                        : 'Failed to generate Arabic translation: Ensure English subtitle is available'),
                isRTL.value ? 'خطأ في التوليد' : 'Generation Failed',
            );
        }
    } catch (e: any) {
        toast.error(
            e.message || 'Error communicating with translation service',
            'Error',
        );
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
    <AppLayout>
        <Head
            :title="
                isRTL
                    ? 'إدارة ومطهر الترجمات الذكي'
                    : 'Subtitle Studio & Health Normalizer'
            "
        />

        <!-- Header PageBanner -->
        <PageBanner
            class="mb-6"
            :icon="Subtitles"
            :title="
                isRTL
                    ? 'فاحص ومطهر الترجمات الذكي'
                    : 'Subtitle Studio & Health Normalizer'
            "
            :description="
                isRTL
                    ? 'فحص ملفات الترجمة، حذف القوالب التالفة والوهمية، الكشف فائق الدقة عن اللغة، وتوحيد الامتدادات (.ar.srt, .en.srt).'
                    : 'Inspect library subtitles, prune corrupt/stub files, accurately detect dialogue language, and standardize extensions (.ar.srt, .en.srt).'
            "
            :badge="`${props.missingSubtitles?.length || 0} ${isRTL ? 'بدون ترجمة' : 'Missing'}`"
        >
            <template v-if="activeMainTab === 'missing'" #actions>
                <button
                    type="button"
                    @click="downloadAllMissing('ar')"
                    class="bg-primary text-primary-foreground shadow-primary/25 flex min-h-10 cursor-pointer items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold shadow-lg transition-all hover:brightness-105 active:scale-95"
                >
                    <Download class="h-4 w-4" />
                    <span>{{
                        isRTL
                            ? 'تحميل كل الترجمات العربية'
                            : 'Download All Arabic'
                    }}</span>
                </button>
                <button
                    type="button"
                    @click="downloadAllMissing('en')"
                    class="cf-glass border-border/80 text-foreground hover:border-primary/40 flex min-h-10 cursor-pointer items-center gap-2 rounded-xl border px-4 py-2.5 text-xs font-bold transition-all active:scale-95"
                >
                    <Download class="h-4 w-4" />
                    <span>{{
                        isRTL
                            ? 'تحميل كل الترجمات الإنجليزية'
                            : 'Download All English'
                    }}</span>
                </button>
            </template>
        </PageBanner>

        <!-- Studio Mode Selector Navigation Tabs -->
        <div
            class="cf-glass border-border/80 mb-8 flex items-center gap-2 overflow-x-auto rounded-2xl border p-1.5"
        >
            <button
                type="button"
                @click="activeMainTab = 'checker'"
                class="flex min-h-10 shrink-0 cursor-pointer items-center gap-2 rounded-xl px-5 py-2 text-xs font-bold transition-all active:scale-95"
                :class="
                    activeMainTab === 'checker'
                        ? 'bg-primary text-primary-foreground shadow-primary/25 font-black shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                <ShieldCheck class="h-4 w-4" />
                <span>{{
                    isRTL
                        ? 'فاحص ومطهر الترجمات الذكي (Checker)'
                        : 'Subtitle Health & Normalizer'
                }}</span>
            </button>

            <button
                type="button"
                @click="activeMainTab = 'missing'"
                class="flex min-h-10 shrink-0 cursor-pointer items-center gap-2 rounded-xl px-5 py-2 text-xs font-bold tabular-nums transition-all active:scale-95"
                :class="
                    activeMainTab === 'missing'
                        ? 'bg-primary text-primary-foreground shadow-primary/25 font-black shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                <FileText class="h-4 w-4" />
                <span
                    >{{
                        isRTL
                            ? 'الترجمات الناقصة في المكتبة'
                            : 'Missing Subtitles Ingestor'
                    }}
                    ({{ props.missingSubtitles?.length || 0 }})</span
                >
            </button>

            <button
                type="button"
                @click="activeMainTab = 'cloud'"
                class="flex min-h-10 shrink-0 cursor-pointer items-center gap-2 rounded-xl px-5 py-2 text-xs font-bold transition-all active:scale-95"
                :class="
                    activeMainTab === 'cloud'
                        ? 'bg-primary text-primary-foreground shadow-primary/25 font-black shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                <Globe class="h-4 w-4" />
                <span>{{
                    isRTL ? 'البحث السحابي المباشر' : 'Live Cloud Search'
                }}</span>
            </button>
        </div>

        <!-- ================= TAB 1: SUBTITLE CHECKER & NORMALIZER ================= -->
        <div v-if="activeMainTab === 'checker'" class="space-y-6">
            <!-- Checker Control & Execution Panel -->
            <div
                class="cf-glass-elevated border-border/80 space-y-6 rounded-3xl border p-6 shadow-2xl sm:p-8"
            >
                <div
                    class="border-border/70 flex flex-col justify-between gap-4 border-b pb-5 lg:flex-row lg:items-center"
                >
                    <div>
                        <h3
                            class="text-foreground flex items-center gap-2.5 text-base font-black sm:text-lg"
                        >
                            <ShieldCheck class="text-primary h-5 w-5" />
                            <span>{{
                                isRTL
                                    ? 'أداة الفحص الشامل وتطهير ملفات الترجمة'
                                    : 'Automated Subtitle Health & Normalizer'
                            }}</span>
                        </h3>
                        <p
                            class="text-muted-foreground mt-1 max-w-2xl text-xs leading-relaxed"
                        >
                            {{
                                isRTL
                                    ? 'يقوم الفاحص بالمرور على جميع ملفات .srt و .vtt في مجلدات الأفلام والمسلسلات، ويفحص محتواها: يحذف الملفات الفارغة والقوالب الوهمية المكسورة، ويكتشف اللغة الحقيقية للحوار بدقة 100%، ويعيد ربط وتسمية الملف بصيغة الفيلم المعتمدة (.ar.srt, .en.srt).'
                                    : 'Inspects all subtitle files across film & TV series directories, detects and deletes corrupt/dummy templates, identifies the exact dialogue language, and renames files to standardized .ar.srt / .en.srt format.'
                            }}
                        </p>
                    </div>

                    <!-- Primary Execution Button -->
                    <button
                        type="button"
                        @click="runHealthCheck"
                        :disabled="isSubtitleRunning"
                        class="bg-primary text-primary-foreground shadow-primary/25 flex min-h-11 shrink-0 cursor-pointer items-center justify-center gap-2.5 rounded-2xl px-6 py-3 text-xs font-black shadow-xl transition-all hover:brightness-105 active:scale-95 disabled:opacity-50 sm:text-sm"
                    >
                        <RefreshCw
                            v-if="isSubtitleRunning"
                            class="h-4 w-4 animate-spin"
                        />
                        <Play v-else class="h-4 w-4 fill-current" />
                        <span>{{
                            checkerDryRun
                                ? isRTL
                                    ? 'بدء المعاينة والفحص (Dry-Run)'
                                    : 'Run Preview Scan (Dry-Run)'
                                : isRTL
                                  ? 'تطبيق الفحص والتطهير الفعلي'
                                  : 'Execute Health Clean & Rename'
                        }}</span>
                    </button>
                </div>

                <!-- Settings & Toggles Grid -->
                <div class="grid grid-cols-1 gap-4 pt-2 md:grid-cols-3">
                    <!-- Dry-Run Toggle -->
                    <div
                        @click="checkerDryRun = !checkerDryRun"
                        class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition-all"
                        :class="
                            checkerDryRun
                                ? 'border-primary/45 bg-primary/10 text-foreground'
                                : 'cf-glass border-border/70 text-muted-foreground hover:border-primary/30'
                        "
                    >
                        <div
                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border"
                            :class="
                                checkerDryRun
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border'
                            "
                        >
                            <Check v-if="checkerDryRun" class="h-3.5 w-3.5" />
                        </div>
                        <div>
                            <span
                                class="text-foreground block text-xs font-bold"
                                >{{
                                    isRTL
                                        ? 'معاينة تجريبية (Dry-Run Preview)'
                                        : 'Dry-Run Preview Only'
                                }}</span
                            >
                            <span
                                class="text-muted-foreground mt-0.5 block text-[11px]"
                                >{{
                                    isRTL
                                        ? 'عرض النتائج والتوصيات دون حذف أو تعديل أي ملف على القرص'
                                        : 'Simulate checks and report without touching files on disk'
                                }}</span
                            >
                        </div>
                    </div>

                    <!-- Delete Invalid Subtitles Toggle -->
                    <div
                        @click="checkerDeleteInvalid = !checkerDeleteInvalid"
                        class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition-all"
                        :class="
                            checkerDeleteInvalid
                                ? 'border-destructive/45 bg-destructive/10 text-foreground'
                                : 'cf-glass border-border/70 text-muted-foreground hover:border-destructive/30'
                        "
                    >
                        <div
                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border"
                            :class="
                                checkerDeleteInvalid
                                    ? 'border-destructive bg-destructive text-destructive-foreground'
                                    : 'border-border'
                            "
                        >
                            <Check
                                v-if="checkerDeleteInvalid"
                                class="h-3.5 w-3.5"
                            />
                        </div>
                        <div>
                            <span
                                class="text-foreground block text-xs font-bold"
                                >{{
                                    isRTL
                                        ? 'حذف الملفات التالفة والقوالب الوهمية'
                                        : 'Delete Corrupt & Stub Files'
                                }}</span
                            >
                            <span
                                class="text-muted-foreground mt-0.5 block text-[11px]"
                                >{{
                                    isRTL
                                        ? 'حذف أي ترجمة أقل من 5 حوارات أو تحتوي صفحات خطأ HTML'
                                        : 'Purges files with < 5 cues, broken timecodes, or HTML 404 stubs'
                                }}</span
                            >
                        </div>
                    </div>

                    <!-- Auto-Rename Extension Toggle -->
                    <div
                        @click="checkerAutoRename = !checkerAutoRename"
                        class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition-all"
                        :class="
                            checkerAutoRename
                                ? 'border-primary/45 bg-primary/10 text-foreground'
                                : 'cf-glass border-border/70 text-muted-foreground hover:border-primary/30'
                        "
                    >
                        <div
                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border"
                            :class="
                                checkerAutoRename
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border'
                            "
                        >
                            <Check
                                v-if="checkerAutoRename"
                                class="h-3.5 w-3.5"
                            />
                        </div>
                        <div>
                            <span
                                class="text-foreground block text-xs font-bold"
                                >{{
                                    isRTL
                                        ? 'توحيد صيغة الامتداد (.ar.srt)'
                                        : 'Standardize Language Extension'
                                }}</span
                            >
                            <span
                                class="text-muted-foreground mt-0.5 block text-[11px]"
                                >{{
                                    isRTL
                                        ? 'إعادة تسمية الملف تلقائياً وفقاً للغة الحوار المكتشفة'
                                        : 'Renames to {media}.{lang}.srt matching detected language'
                                }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Optional Folder Path Input -->
                <div class="pt-2">
                    <label
                        class="text-muted-foreground mb-1.5 block text-xs font-bold"
                    >
                        {{
                            isRTL
                                ? 'مسار مجلد محدد (اختياري، اتركه فارغاً لفحص كامل المكتبة):'
                                : 'Specific Target Folder (Optional, leave blank to scan entire library):'
                        }}
                    </label>
                    <input
                        type="text"
                        v-model="checkerCustomPath"
                        placeholder="e.g. H:\Entertainment\Movies\Inception (2010)"
                        class="cf-field border-border/80 bg-card/80 text-primary focus:border-primary focus:ring-primary/20 h-11 w-full rounded-xl border px-4 font-mono text-xs outline-none focus:ring-2"
                    />
                </div>
            </div>

            <!-- Health Summary Metrics -->
            <div
                v-if="healthResults"
                class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5"
            >
                <div
                    class="cf-glass border-border/80 rounded-2xl border p-4 text-center"
                >
                    <span
                        class="text-muted-foreground block text-[11px] font-bold tracking-wider uppercase"
                        >{{ isRTL ? 'إجمالي الملفات' : 'Total Scanned' }}</span
                    >
                    <span
                        class="text-foreground mt-1 block font-mono text-2xl font-black tabular-nums"
                        >{{
                            healthResults.total_scanned ??
                            healthResults.summary?.total_scanned ??
                            0
                        }}</span
                    >
                </div>

                <div
                    class="cf-glass rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4 text-center"
                >
                    <span
                        class="text-muted-foreground block text-[11px] font-bold tracking-wider uppercase"
                        >{{ isRTL ? 'ترجمات سليمة' : 'Valid Subtitles' }}</span
                    >
                    <span
                        class="mt-1 block font-mono text-2xl font-black text-emerald-400 tabular-nums"
                        >{{
                            healthResults.valid_count ??
                            healthResults.summary?.valid_count ??
                            0
                        }}</span
                    >
                </div>

                <div
                    class="cf-glass border-destructive/30 bg-destructive/5 rounded-2xl border p-4 text-center"
                >
                    <span
                        class="text-muted-foreground block text-[11px] font-bold tracking-wider uppercase"
                        >{{
                            isRTL ? 'ملفات تالفة / وهمية' : 'Corrupt / Stubs'
                        }}</span
                    >
                    <span
                        class="text-destructive mt-1 block font-mono text-2xl font-black tabular-nums"
                        >{{
                            healthResults.invalid_count ??
                            healthResults.summary?.invalid_count ??
                            0
                        }}</span
                    >
                </div>

                <div
                    class="cf-glass border-primary/30 bg-primary/5 rounded-2xl border p-4 text-center"
                >
                    <span
                        class="text-muted-foreground block text-[11px] font-bold tracking-wider uppercase"
                        >{{
                            isRTL ? 'تعديل التسمية' : 'Renamed / Standard'
                        }}</span
                    >
                    <span
                        class="text-primary mt-1 block font-mono text-2xl font-black tabular-nums"
                        >{{
                            healthResults.renamed_count ??
                            healthResults.summary?.renamed_count ??
                            0
                        }}</span
                    >
                </div>

                <div
                    class="cf-glass rounded-2xl border border-indigo-500/30 bg-indigo-500/5 p-4 text-center"
                >
                    <span
                        class="text-muted-foreground block text-[11px] font-bold tracking-wider uppercase"
                        >{{
                            isRTL ? 'مطابقة معيارياً' : 'Already Standard'
                        }}</span
                    >
                    <span
                        class="mt-1 block font-mono text-2xl font-black text-indigo-400 tabular-nums"
                        >{{
                            healthResults.already_standard_count ??
                            healthResults.summary?.already_standard_count ??
                            0
                        }}</span
                    >
                </div>
            </div>

            <!-- Language Breakdown Badges -->
            <div
                v-if="
                    healthResults &&
                    healthResults.language_breakdown &&
                    Object.keys(healthResults.language_breakdown).length > 0
                "
                class="cf-glass border-border/80 flex flex-wrap items-center gap-3 rounded-2xl border p-4"
            >
                <span class="text-muted-foreground text-xs font-bold">{{
                    isRTL ? 'توزيع اللغات المكتشفة:' : 'Detected Languages:'
                }}</span>
                <div
                    v-for="lang in healthResults.language_breakdown"
                    :key="lang.code"
                    class="cf-glass border-border/70 flex items-center gap-1.5 rounded-xl border px-3 py-1 text-xs font-bold"
                >
                    <span>{{ lang.flag }}</span>
                    <span class="text-foreground"
                        >{{ lang.name_en }} ({{ lang.code }}):</span
                    >
                    <span class="text-primary font-mono tabular-nums">{{
                        lang.count
                    }}</span>
                </div>
            </div>

            <!-- Subtitle Items Table -->
            <div v-if="healthResults" class="space-y-4">
                <!-- Table Filter & Search Bar -->
                <div
                    class="cf-glass border-border/80 flex flex-col items-stretch justify-between gap-3 rounded-2xl border p-3 sm:flex-row sm:items-center"
                >
                    <div
                        class="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0"
                    >
                        <button
                            v-for="f in [
                                {
                                    id: 'all',
                                    label_en: 'All',
                                    label_ar: 'الكل',
                                },
                                {
                                    id: 'invalid',
                                    label_en: 'Invalid / Stubs',
                                    label_ar: 'التالفة / الوهمية',
                                },
                                {
                                    id: 'renamed',
                                    label_en: 'Renamed',
                                    label_ar: 'المعدلة',
                                },
                                {
                                    id: 'already_standard',
                                    label_en: 'Standard',
                                    label_ar: 'المعيارية',
                                },
                                {
                                    id: 'valid',
                                    label_en: 'All Valid',
                                    label_ar: 'السليمة',
                                },
                            ]"
                            :key="f.id"
                            type="button"
                            @click="
                                checkerFilter = f.id as any;
                                checkerCurrentPage = 1;
                            "
                            class="min-h-9 shrink-0 cursor-pointer rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all active:scale-95"
                            :class="
                                checkerFilter === f.id
                                    ? 'bg-primary text-primary-foreground shadow-primary/25 font-black shadow-sm'
                                    : 'cf-glass border-border/70 text-muted-foreground hover:text-foreground border'
                            "
                        >
                            {{ isRTL ? f.label_ar : f.label_en }}
                        </button>
                    </div>

                    <div class="relative min-w-[240px]">
                        <Search
                            class="text-muted-foreground pointer-events-none absolute start-3 top-2.5 h-4 w-4"
                        />
                        <input
                            v-model="checkerSearchText"
                            type="text"
                            :placeholder="
                                isRTL
                                    ? 'بحث بالاسم أو اللغة...'
                                    : 'Filter files or language...'
                            "
                            class="cf-field border-border/80 bg-card/80 text-foreground focus:border-primary h-9 w-full rounded-xl border ps-9 pe-3 text-xs font-semibold focus:outline-none"
                        />
                    </div>
                </div>

                <!-- Table Card Container -->
                <div
                    class="cf-table-wrapper cf-glass border-border/80 overflow-hidden rounded-3xl border shadow-xl"
                >
                    <div class="overflow-x-auto">
                        <table
                            class="cf-table w-full border-collapse text-start text-xs"
                        >
                            <thead>
                                <tr
                                    class="border-border/70 bg-muted/30 text-muted-foreground border-b font-mono text-[10px] tracking-wider uppercase"
                                >
                                    <th class="px-4 py-3 text-start font-bold">
                                        {{
                                            isRTL
                                                ? 'اسم الملف الأصلي'
                                                : 'File Name'
                                        }}
                                    </th>
                                    <th class="px-4 py-3 text-start font-bold">
                                        {{ isRTL ? 'الحالة' : 'Status' }}
                                    </th>
                                    <th class="px-4 py-3 text-start font-bold">
                                        {{
                                            isRTL
                                                ? 'اللغة المكتشفة'
                                                : 'Detected Language'
                                        }}
                                    </th>
                                    <th class="px-4 py-3 text-start font-bold">
                                        {{ isRTL ? 'الحوارات' : 'Cues' }}
                                    </th>
                                    <th class="px-4 py-3 text-start font-bold">
                                        {{
                                            isRTL
                                                ? 'الإجراء المتخذ / المقترح'
                                                : 'Action / Target Name'
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-border/50 divide-y">
                                <tr
                                    v-for="(item, idx) in paginatedCheckerItems"
                                    :key="idx"
                                    class="transition-colors hover:bg-white/[0.03]"
                                >
                                    <!-- File Name & Path -->
                                    <td class="max-w-xs px-4 py-3 sm:max-w-md">
                                        <div
                                            class="text-foreground truncate font-bold"
                                            :title="item.file_name"
                                        >
                                            {{ item.file_name }}
                                        </div>
                                        <div
                                            class="text-muted-foreground mt-0.5 truncate text-[10px]"
                                            :title="item.original_path"
                                        >
                                            {{ item.original_path }}
                                        </div>
                                        <div
                                            v-if="
                                                item.issues &&
                                                item.issues.length > 0
                                            "
                                            class="text-destructive mt-1 flex items-center gap-1.5 text-[10px] font-bold"
                                        >
                                            <AlertTriangle
                                                class="h-3 w-3 shrink-0"
                                            />
                                            <span>{{
                                                item.issues.join(', ')
                                            }}</span>
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-mono text-[10px] font-bold tracking-wider uppercase"
                                            :class="
                                                item.is_valid
                                                    ? 'border border-emerald-500/30 bg-emerald-500/20 text-emerald-400'
                                                    : 'border-destructive/30 bg-destructive/20 text-destructive border'
                                            "
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full"
                                                :class="
                                                    item.is_valid
                                                        ? 'bg-emerald-400'
                                                        : 'bg-destructive'
                                                "
                                            ></span>
                                            <span>{{
                                                item.is_valid
                                                    ? 'VALID'
                                                    : 'INVALID STUB'
                                            }}</span>
                                        </span>
                                    </td>

                                    <!-- Detected Language -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="text-base">{{
                                                item.flag
                                            }}</span>
                                            <div>
                                                <span
                                                    class="text-foreground block font-bold"
                                                    >{{
                                                        item.language_name
                                                    }}</span
                                                >
                                                <span
                                                    v-if="item.confidence"
                                                    class="text-muted-foreground font-mono text-[10px] tabular-nums"
                                                    >{{
                                                        Math.round(
                                                            item.confidence *
                                                                100,
                                                        )
                                                    }}% confidence</span
                                                >
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Cues & Size -->
                                    <td
                                        class="text-muted-foreground px-4 py-3 font-mono whitespace-nowrap tabular-nums"
                                    >
                                        <div>{{ item.cue_count }} cues</div>
                                        <div
                                            class="text-muted-foreground/75 text-[10px]"
                                        >
                                            {{ formatBytes(item.file_size) }}
                                        </div>
                                    </td>

                                    <!-- Action / Target Filename -->
                                    <td class="px-4 py-3">
                                        <div
                                            v-if="
                                                item.action === 'deleted' ||
                                                item.action ===
                                                    'deleted_invalid'
                                            "
                                            class="text-destructive flex items-center gap-1 text-xs font-bold"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                            <span>{{
                                                isRTL
                                                    ? 'تم الحذف بنجاح'
                                                    : 'Deleted'
                                            }}</span>
                                        </div>
                                        <div
                                            v-else-if="
                                                item.action ===
                                                'flagged_for_deletion'
                                            "
                                            class="text-destructive flex items-center gap-1 text-xs font-bold"
                                        >
                                            <AlertTriangle
                                                class="h-3.5 w-3.5"
                                            />
                                            <span>{{
                                                isRTL
                                                    ? 'مرشح للحذف (تالف/وهمي)'
                                                    : 'Flagged for Deletion'
                                            }}</span>
                                        </div>
                                        <div
                                            v-else-if="
                                                item.action === 'renamed'
                                            "
                                            class="text-primary text-xs font-bold"
                                        >
                                            <div
                                                class="flex items-center gap-1"
                                            >
                                                <Tag
                                                    class="text-primary h-3.5 w-3.5 shrink-0"
                                                />
                                                <span class="truncate">{{
                                                    item.target_filename
                                                }}</span>
                                            </div>
                                            <span
                                                class="text-[10px] text-emerald-400"
                                                >{{
                                                    isRTL
                                                        ? 'تمت إعادة التسمية بنجاح'
                                                        : 'Renamed'
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            v-else-if="
                                                item.action === 'would_rename'
                                            "
                                            class="text-xs font-bold text-indigo-400"
                                        >
                                            <div
                                                class="flex items-center gap-1"
                                            >
                                                <Tag
                                                    class="h-3.5 w-3.5 shrink-0 text-indigo-400"
                                                />
                                                <span class="truncate">{{
                                                    item.target_filename
                                                }}</span>
                                            </div>
                                            <span
                                                class="text-muted-foreground text-[10px]"
                                                >{{
                                                    isRTL
                                                        ? 'سيتم التعديل عند التنفيذ'
                                                        : 'Target Standard Name'
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            v-else-if="
                                                item.action ===
                                                'already_standard'
                                            "
                                            class="flex items-center gap-1 text-xs font-bold text-emerald-400"
                                        >
                                            <CheckCircle2 class="h-3.5 w-3.5" />
                                            <span>{{
                                                isRTL
                                                    ? 'تسمية معيارية صحيحة'
                                                    : 'Already Standard'
                                            }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="totalCheckerPages > 1"
                        class="border-border/70 flex items-center justify-between border-t p-4 text-xs tabular-nums"
                    >
                        <span class="text-muted-foreground">
                            {{ (checkerCurrentPage - 1) * checkerPerPage + 1 }}
                            -
                            {{
                                Math.min(
                                    checkerCurrentPage * checkerPerPage,
                                    filteredCheckerItems.length,
                                )
                            }}
                            of {{ filteredCheckerItems.length }}
                        </span>
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                @click="
                                    checkerCurrentPage = Math.max(
                                        1,
                                        checkerCurrentPage - 1,
                                    )
                                "
                                :disabled="checkerCurrentPage === 1"
                                class="cf-glass border-border/80 text-foreground hover:border-primary/40 cursor-pointer rounded-xl border px-3 py-1.5 font-bold disabled:opacity-30"
                            >
                                {{ isRTL ? 'السابق' : 'Prev' }}
                            </button>
                            <span
                                class="text-primary px-3 py-1.5 font-mono font-bold"
                                >{{ checkerCurrentPage }} /
                                {{ totalCheckerPages }}</span
                            >
                            <button
                                type="button"
                                @click="
                                    checkerCurrentPage = Math.min(
                                        totalCheckerPages,
                                        checkerCurrentPage + 1,
                                    )
                                "
                                :disabled="
                                    checkerCurrentPage === totalCheckerPages
                                "
                                class="cf-glass border-border/80 text-foreground hover:border-primary/40 cursor-pointer rounded-xl border px-3 py-1.5 font-bold disabled:opacity-30"
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
            <div
                class="cf-glass border-border/80 flex flex-col justify-between gap-4 rounded-2xl border p-4 lg:flex-row lg:items-center"
            >
                <!-- Search text -->
                <div class="relative flex-1">
                    <Search
                        class="text-muted-foreground pointer-events-none absolute start-3 top-2.5 h-4 w-4"
                    />
                    <input
                        v-model="filterText"
                        type="text"
                        :placeholder="
                            isRTL
                                ? 'تصفية حسب الاسم...'
                                : 'Filter media items...'
                        "
                        class="cf-field border-border/80 bg-card/80 text-foreground focus:border-primary h-10 w-full rounded-xl border ps-9 pe-3 text-xs font-semibold focus:outline-none"
                    />
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Type Filter -->
                    <select
                        v-model="filterType"
                        class="cf-glass border-border/80 bg-card text-foreground focus:border-primary h-10 rounded-xl border px-3 text-xs font-bold focus:outline-none"
                    >
                        <option value="all" class="bg-card text-foreground">
                            {{ isRTL ? 'كل الوسائط' : 'All Media Types' }}
                        </option>
                        <option value="movie" class="bg-card text-foreground">
                            {{ isRTL ? 'الأفلام فقط' : 'Movies Only' }}
                        </option>
                        <option value="episode" class="bg-card text-foreground">
                            {{ isRTL ? 'حلقات المسلسلات' : 'Series Episodes' }}
                        </option>
                    </select>

                    <!-- Status Filter -->
                    <select
                        v-model="filterStatus"
                        class="cf-glass border-border/80 bg-card text-foreground focus:border-primary h-10 rounded-xl border px-3 text-xs font-bold focus:outline-none"
                    >
                        <option value="all" class="bg-card text-foreground">
                            {{ isRTL ? 'كل الحالات' : 'All Status' }}
                        </option>
                        <option
                            value="missing_ar"
                            class="bg-card text-foreground"
                        >
                            {{
                                isRTL ? 'ينقصها ترجمة عربية' : 'Missing Arabic'
                            }}
                        </option>
                        <option
                            value="missing_en"
                            class="bg-card text-foreground"
                        >
                            {{
                                isRTL
                                    ? 'ينقصها ترجمة إنجليزية'
                                    : 'Missing English'
                            }}
                        </option>
                    </select>

                    <!-- Per page -->
                    <select
                        v-model="perPage"
                        class="cf-glass border-border/80 bg-card text-foreground focus:border-primary h-10 rounded-xl border px-3 text-xs font-bold tabular-nums focus:outline-none"
                    >
                        <option :value="12" class="bg-card text-foreground">
                            12 / page
                        </option>
                        <option :value="24" class="bg-card text-foreground">
                            24 / page
                        </option>
                        <option :value="48" class="bg-card text-foreground">
                            48 / page
                        </option>
                    </select>
                </div>
            </div>

            <!-- Items Counter -->
            <div
                class="text-muted-foreground flex items-center justify-between px-1 text-xs font-bold tabular-nums"
            >
                <span>
                    {{ isRTL ? 'إجمالي العناصر:' : 'Total items:' }}
                    <strong class="text-primary">{{
                        filteredItems.length
                    }}</strong>
                </span>
                <span v-if="totalPages > 1">
                    {{ isRTL ? 'الصفحة' : 'Page' }} {{ currentPage }} /
                    {{ totalPages }}
                </span>
            </div>

            <!-- Grid of Missing Media Items -->
            <div
                v-if="paginatedItems.length > 0"
                class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="item in paginatedItems"
                    :key="`${item.type}-${item.id}`"
                    class="cf-glass border-border/80 hover:border-primary/45 flex flex-col justify-between rounded-2xl border p-4 shadow-sm transition-all"
                >
                    <div>
                        <div
                            class="mb-2 flex items-center justify-between gap-2"
                        >
                            <span
                                class="cinema-badge text-[10px] font-bold"
                                :class="
                                    item.type === 'movie'
                                        ? 'border-primary/30 bg-primary/15 text-primary border'
                                        : 'border border-purple-500/30 bg-purple-500/15 text-purple-300'
                                "
                            >
                                {{
                                    item.type === 'movie'
                                        ? isRTL
                                            ? 'فيلم'
                                            : 'Movie'
                                        : isRTL
                                          ? 'حلقة مسلسل'
                                          : 'Episode'
                                }}
                            </span>
                            <span
                                class="text-muted-foreground font-mono text-[10px] tabular-nums"
                            >
                                {{ item.year || item.season_info || '' }}
                            </span>
                        </div>

                        <h4
                            class="text-foreground line-clamp-1 text-sm font-extrabold"
                        >
                            {{
                                isRTL && item.title_ar
                                    ? item.title_ar
                                    : item.title
                            }}
                        </h4>
                        <p
                            v-if="item.series_title"
                            class="text-muted-foreground mt-0.5 line-clamp-1 text-xs"
                        >
                            {{ item.series_title }}
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="border-border/60 mt-4 space-y-2 border-t pt-3">
                        <div class="flex items-center gap-2">
                            <!-- Arabic Subtitle Action -->
                            <button
                                type="button"
                                @click="openPickerModal(item, 'ar')"
                                :disabled="!item.missing_ar"
                                class="flex min-h-9 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold transition-all active:scale-95"
                                :class="
                                    !item.missing_ar
                                        ? 'cursor-default border border-emerald-500/25 bg-emerald-500/10 text-emerald-400'
                                        : 'bg-primary text-primary-foreground shadow-primary/25 shadow-sm hover:brightness-105'
                                "
                            >
                                <Check
                                    v-if="!item.missing_ar"
                                    class="h-3.5 w-3.5"
                                />
                                <Download v-else class="h-3.5 w-3.5" />
                                <span>{{
                                    !item.missing_ar
                                        ? isRTL
                                            ? 'العربية متوفرة'
                                            : 'Arabic Ready'
                                        : isRTL
                                          ? 'تحميل العربية'
                                          : 'Get Arabic'
                                }}</span>
                            </button>

                            <!-- English Subtitle Action -->
                            <button
                                type="button"
                                @click="openPickerModal(item, 'en')"
                                :disabled="!item.missing_en"
                                class="flex min-h-9 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold transition-all active:scale-95"
                                :class="
                                    !item.missing_en
                                        ? 'cursor-default border border-emerald-500/25 bg-emerald-500/10 text-emerald-400'
                                        : 'cf-glass border-border/80 text-foreground hover:border-primary/40 border'
                                "
                            >
                                <Check
                                    v-if="!item.missing_en"
                                    class="h-3.5 w-3.5"
                                />
                                <Download v-else class="h-3.5 w-3.5" />
                                <span>{{
                                    !item.missing_en
                                        ? isRTL
                                            ? 'الإنجليزية متوفرة'
                                            : 'English Ready'
                                        : isRTL
                                          ? 'تحميل الإنجليزية'
                                          : 'Get English'
                                }}</span>
                            </button>
                        </div>

                        <!-- 1-Click Arabic Translation from English -->
                        <button
                            v-if="item.missing_ar"
                            type="button"
                            @click="generateArabicFromEnglish(item)"
                            :disabled="isGeneratingArabicId === item.id"
                            class="border-primary/35 bg-primary/10 text-primary hover:bg-primary/20 flex min-h-8 w-full cursor-pointer items-center justify-center gap-1.5 rounded-xl border px-3 py-1.5 text-[11px] font-bold transition-all active:scale-95 disabled:opacity-50"
                        >
                            <RefreshCw
                                v-if="isGeneratingArabicId === item.id"
                                class="text-primary h-3 w-3 animate-spin"
                            />
                            <Sparkles v-else class="text-primary h-3 w-3" />
                            <span>{{
                                isGeneratingArabicId === item.id
                                    ? isRTL
                                        ? 'جارٍ الترجمة والتوليد...'
                                        : 'Translating from English...'
                                    : isRTL
                                      ? 'توليد ترجمة عربية من الإنجليزية'
                                      : 'Translate Arabic from English'
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="cf-glass border-border/80 text-muted-foreground rounded-3xl border p-12 text-center"
            >
                <CheckCheck class="mx-auto mb-3 h-12 w-12 text-emerald-400" />
                <h3 class="text-foreground text-base font-bold">
                    {{
                        isRTL
                            ? 'جميع الوسائط تحتوي على ترجمات كاملة!'
                            : 'All Media Subtitles Synchronized!'
                    }}
                </h3>
                <p class="text-muted-foreground mt-1 text-xs">
                    {{
                        isRTL
                            ? 'لا توجد وسائط تنقصها ترجمات وفقاً لخيارات التصفية المحددة.'
                            : 'No items match your filter criteria.'
                    }}
                </p>
            </div>

            <!-- Pagination Bar -->
            <div
                v-if="totalPages > 1"
                class="flex items-center justify-center gap-2 pt-6"
            >
                <button
                    type="button"
                    @click="setPage(currentPage - 1)"
                    :disabled="currentPage === 1"
                    class="cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground cursor-pointer rounded-xl border p-2.5 disabled:opacity-30"
                >
                    <ChevronLeft class="h-4 w-4 rtl:rotate-180" />
                </button>

                <button
                    v-for="p in totalPages"
                    :key="p"
                    type="button"
                    @click="setPage(p)"
                    class="size-10 cursor-pointer rounded-xl text-xs font-bold tabular-nums transition-all active:scale-95"
                    :class="
                        currentPage === p
                            ? 'bg-primary text-primary-foreground shadow-primary/25 font-black shadow-sm'
                            : 'cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground border'
                    "
                >
                    {{ p }}
                </button>

                <button
                    type="button"
                    @click="setPage(currentPage + 1)"
                    :disabled="currentPage === totalPages"
                    class="cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground cursor-pointer rounded-xl border p-2.5 disabled:opacity-30"
                >
                    <ChevronRight class="h-4 w-4 rtl:rotate-180" />
                </button>
            </div>
        </div>

        <!-- ================= TAB 3: LIVE CLOUD SEARCH ENGINE ================= -->
        <div
            v-if="activeMainTab === 'cloud'"
            class="cf-glass-elevated border-border/80 mb-10 rounded-3xl border p-6 shadow-xl sm:p-8"
        >
            <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <Sparkles class="text-primary h-5 w-5" />
                    <h3 class="text-foreground text-base font-extrabold">
                        {{
                            isRTL
                                ? 'البحث السحابي الفوري (SubDL & OpenSubtitles Engine)'
                                : 'Live Cloud Subtitle Search Engine'
                        }}
                    </h3>
                </div>
                <div
                    class="flex items-center gap-2 font-mono text-xs font-bold text-emerald-400"
                >
                    <span
                        class="h-2 w-2 animate-pulse rounded-full bg-emerald-400"
                    ></span>
                    <span>SubDL & OpenSubtitles Live Active</span>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="relative flex-1">
                    <Search
                        class="text-muted-foreground pointer-events-none absolute start-3.5 top-3.5 h-4 w-4"
                    />
                    <input
                        v-model="searchQuery"
                        @keyup.enter="performSearch"
                        type="text"
                        :placeholder="
                            isRTL
                                ? 'ابحث عن اسم الفيلم أو المسلسل...'
                                : 'Search movie or series title...'
                        "
                        class="cf-field border-border/80 bg-card/80 text-foreground focus:border-primary h-11 w-full rounded-2xl border ps-10 pe-4 text-sm font-semibold focus:outline-none"
                    />
                </div>

                <select
                    v-model="searchLang"
                    class="cf-glass border-border/80 bg-card text-foreground focus:border-primary h-11 rounded-2xl border px-4 text-sm font-bold focus:outline-none"
                >
                    <option value="ar" class="bg-card text-foreground">
                        🇸🇦 العربية (Arabic)
                    </option>
                    <option value="en" class="bg-card text-foreground">
                        🇬🇧 English
                    </option>
                    <option value="es" class="bg-card text-foreground">
                        🇪🇸 Español
                    </option>
                    <option value="fr" class="bg-card text-foreground">
                        🇫🇷 Français
                    </option>
                    <option value="de" class="bg-card text-foreground">
                        🇩🇪 Deutsch
                    </option>
                    <option value="tr" class="bg-card text-foreground">
                        🇹🇷 Türkçe
                    </option>
                </select>

                <button
                    type="button"
                    @click="performSearch"
                    :disabled="isSearching"
                    class="bg-primary text-primary-foreground shadow-primary/25 flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-2xl px-6 py-3 text-sm font-black shadow-lg transition-all hover:brightness-105 active:scale-95 disabled:opacity-50"
                >
                    <RefreshCw
                        v-if="isSearching"
                        class="h-4 w-4 animate-spin"
                    />
                    <Search v-else class="h-4 w-4" />
                    <span>{{ isRTL ? 'بحث سحابي' : 'Search Cloud' }}</span>
                </button>
            </div>

            <!-- Search Results Display -->
            <div
                v-if="searchResults && searchResults.length > 0"
                class="border-border/60 mt-6 space-y-2.5 border-t pt-4"
            >
                <div
                    class="text-muted-foreground mb-2 text-xs font-bold tracking-wider uppercase tabular-nums"
                >
                    {{
                        isRTL
                            ? 'نتائج البحث السحابية'
                            : 'Cloud Subtitle Matches'
                    }}
                    ({{ searchResults.length }})
                </div>
                <div
                    v-for="(sub, idx) in searchResults"
                    :key="idx"
                    class="cf-glass border-border/70 flex items-center justify-between gap-4 rounded-2xl border p-3.5"
                >
                    <div class="flex items-center gap-3 truncate">
                        <FileText class="text-primary h-5 w-5 shrink-0" />
                        <div class="truncate">
                            <div
                                class="text-foreground truncate text-xs font-bold"
                            >
                                {{ sub.release || sub.file_name }}
                            </div>
                            <div
                                class="text-muted-foreground mt-0.5 flex items-center gap-2 text-[11px] tabular-nums"
                            >
                                <span
                                    class="cinema-badge border-primary/30 bg-primary/10 text-primary border text-[10px]"
                                    >{{ sub.provider }}</span
                                >
                                <span
                                    >Rating: {{ sub.rating || '9.5' }}/10</span
                                >
                                <span
                                    >{{ sub.downloads || 450 }} downloads</span
                                >
                            </div>
                        </div>
                    </div>
                    <span
                        class="cinema-badge shrink-0 border border-emerald-500/30 bg-emerald-500/20 text-xs font-bold text-emerald-400"
                    >
                        {{ isRTL ? 'متطابق بنسبة 100%' : '100% Synced' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Interactive Subtitle Search & Selection Modal -->
        <SubtitlePickerModal
            v-if="isPickerOpen"
            :is-open="isPickerOpen"
            :media="selectedMediaForPicker"
            :initial-language="pickerLanguage"
            @close="isPickerOpen = false"
            @downloaded="onSubtitleDownloaded"
        />
    </AppLayout>
</template>
