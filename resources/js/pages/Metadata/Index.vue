<script setup lang="ts">
import { ref, computed, watch, defineAsyncComponent } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import { formatBytes } from '@/lib/formatters';
import AppLayout from '@/components/layout/AppLayout.vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';
import PageBanner from '@/components/common/PageBanner.vue';

const FixMatchModal = defineAsyncComponent(
    () => import('@/components/media/FixMatchModal.vue'),
);
import {
    Sparkles,
    Search,
    Globe,
    Film,
    Tv,
    RefreshCw,
    CheckCircle2,
    Check,
    Trash2,
    Wand2,
    Repeat,
    ArrowRightLeft,
    AlertTriangle,
    Layers,
    Copy,
    FileVideo,
    Folder,
    FileEdit,
} from '@lucide/vue';

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

// Local reactive items array
const localItems = ref<any[]>([...props.items]);
watch(
    () => props.items,
    (newVal) => {
        localItems.value = [...newVal];
    },
);

const activeFilter = ref(props.filters?.filter || 'all');
const searchQuery = ref(props.filters?.search || '');
const isBatchEnriching = ref(false);
const isOperating = ref(false);
const globalToast = useToast();
const copiedItemId = ref<number | string | null>(null);

// Fix Match Modal State
const selectedItemForFix = ref<any | null>(null);
const showFixModal = ref(false);

const confirmModal = ref<{
    show: boolean;
    title: string;
    message: string;
    confirmText: string;
    cancelText?: string;
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

const showToast = (
    msg: string,
    type: 'success' | 'info' | 'danger' = 'success',
) => {
    if (type === 'danger') {
        globalToast.error(msg);
    } else if (type === 'info') {
        globalToast.info(msg);
    } else {
        globalToast.success(msg);
    }
};

const applyFilter = (filterKey: string) => {
    activeFilter.value = filterKey;
    router.get(
        '/metadata',
        {
            filter: filterKey,
            search: searchQuery.value || undefined,
        },
        { preserveState: true },
    );
};

const handleSearch = () => {
    router.get(
        '/metadata',
        {
            filter: activeFilter.value,
            search: searchQuery.value || undefined,
        },
        { preserveState: true },
    );
};

const openFixMatch = (item: any) => {
    selectedItemForFix.value = item;
    showFixModal.value = true;
};

const cardVerifications = ref<
    Record<
        string,
        {
            checking?: boolean;
            exists?: boolean;
            size_formatted?: string;
            candidate_path?: string | null;
        }
    >
>({});

const verifyCardDisk = async (item: any) => {
    const key = `${item.type}_${item.id}`;
    cardVerifications.value[key] = { checking: true };
    try {
        const res = await fetch(
            `/api/metadata/${item.type}/${item.id}/verify-file`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as any
                        )?.content || '',
                },
            },
        );
        const data = await res.json();
        if (data.success) {
            cardVerifications.value[key] = {
                checking: false,
                exists: data.exists,
                size_formatted: data.size_formatted,
                candidate_path: data.candidate_path,
            };
        } else {
            cardVerifications.value[key] = { checking: false, exists: false };
        }
    } catch (e) {
        cardVerifications.value[key] = { checking: false, exists: false };
    }
};

const autoRelinkCard = async (item: any, newPath: string) => {
    const key = `${item.type}_${item.id}`;
    cardVerifications.value[key] = { checking: true };
    try {
        const res = await fetch(
            `/api/metadata/${item.type}/${item.id}/relocate-file`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as any
                        )?.content || '',
                },
                body: JSON.stringify({ new_path: newPath }),
            },
        );
        const data = await res.json();
        if (data.success) {
            if (item.type === 'movie' && data.item) {
                item.file_path = data.item.file_path;
                item.folder_path = data.item.folder_path;
            } else if (data.series) {
                item.folder_path = data.series.folder_path;
            }
            cardVerifications.value[key] = {
                checking: false,
                exists: true,
                size_formatted: data.item?.size
                    ? formatBytes(data.item.size)
                    : undefined,
            };
        } else {
            cardVerifications.value[key] = { checking: false, exists: false };
        }
    } catch (e) {
        cardVerifications.value[key] = { checking: false, exists: false };
    }
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
    showToast(
        isRTL.value
            ? `تم تحديث بيانات "${updatedItem.title}" بنجاح!`
            : `Metadata updated for "${updatedItem.title}"!`,
        'success',
    );

    // If currently filtered by unmatched/missing and the item is now resolved, remove it locally
    if (
        (activeFilter.value === 'unmatched' && updatedItem.is_matched) ||
        (activeFilter.value === 'missing_posters' && updatedItem.has_poster) ||
        (activeFilter.value === 'missing_arabic' && updatedItem.has_arabic)
    ) {
        localItems.value = localItems.value.filter(
            (i) => !(i.id === updatedItem.id && i.type === updatedItem.type),
        );
    } else {
        const target = localItems.value.find(
            (i) => i.id === updatedItem.id && i.type === updatedItem.type,
        );
        if (target) {
            Object.assign(target, updatedItem);
        }
    }

    // Refresh server state to update all stats and lists
    router.reload();
};

const quickReparse = async (item: any) => {
    isOperating.value = true;
    try {
        const res = await fetch(
            `/api/metadata/${item.type}/${item.id}/reparse`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as any
                        )?.content || '',
                },
            },
        );
        const data = await res.json();
        if (data.success) {
            handleItemUpdated(data.media || data.series);
        } else {
            showToast(data.message || 'Reparse failed.', 'danger');
        }
    } catch (e) {
        console.error('Reparse error', e);
        showToast('Reparse error occurred.', 'danger');
    } finally {
        isOperating.value = false;
    }
};

const quickConvert = async (item: any) => {
    isOperating.value = true;
    try {
        const res = await fetch(
            `/api/metadata/${item.type}/${item.id}/convert-type`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as any
                        )?.content || '',
                },
            },
        );
        const data = await res.json();
        if (data.success) {
            showToast(data.message || 'Media type converted!', 'info');
            router.reload();
        } else {
            showToast(data.message || 'Conversion failed.', 'danger');
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
    const cleanTitle = (item.title || 'media')
        .replace(/[\\/:*?"<>|]/g, ' ')
        .trim();
    const newName = `${cleanTitle}${item.release_year ? ` (${item.release_year})` : ''}${item.type === 'movie' ? ext : ''}`;

    const isSeries = item.type === 'series';

    confirmModal.value = {
        show: true,
        title: isSeries
            ? isRTL.value
                ? 'إعادة تسمية مجلد وحلقات المسلسل على القرص'
                : 'Rename Series Folder & Episodes on Disk'
            : isRTL.value
              ? 'إعادة تسمية ملف الفيلم ومجلده على القرص'
              : 'Rename Physical Movie File & Folder on Disk',
        message: isSeries
            ? isRTL.value
                ? `هل تريد إعادة تسمية مجلد المسلسل (${oldName}) وجميع ملفات الحلقات لتطابق الاسم القياسي الجديد:
"${newName}"؟`
                : `Do you want to rename the series folder (${oldName}) and all physical episode files on disk to match:
"${newName}"?`
            : isRTL.value
              ? `هل تريد إعادة تسمية الملف من:
"${oldName}"

إلى الاسم القياسي الجديد:
"${newName}"؟`
              : `Do you want to rename the physical file on disk from:
"${oldName}"

to the clean standard title:
"${newName}"?`,
        confirmText: isSeries
            ? isRTL.value
                ? 'تأكيد إعادة تسمية المسلسل والحلقات'
                : 'Rename Series & Episodes Now'
            : isRTL.value
              ? 'تأكيد إعادة التسمية'
              : 'Rename File Now',
        type: 'info',
        action: async () => {
            confirmModal.value.show = false;
            isOperating.value = true;
            try {
                const res = await fetch(
                    `/api/metadata/${item.type}/${item.id}/rename-file`,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN':
                                (
                                    document.querySelector(
                                        'meta[name="csrf-token"]',
                                    ) as any
                                )?.content || '',
                        },
                    },
                );
                const data = await res.json();
                if (data.success) {
                    showToast(
                        data.message ||
                            (isRTL.value
                                ? 'تمت إعادة تسمية الملف بنجاح!'
                                : 'File renamed successfully!'),
                        'success',
                    );
                    handleItemUpdated(data.media || data.series);
                } else {
                    showToast(
                        data.message ||
                            (isRTL.value
                                ? 'فشلت إعادة التسمية.'
                                : 'Failed to rename file.'),
                        'danger',
                    );
                }
            } catch (e) {
                console.error(e);
                showToast('Error renaming file.', 'danger');
            } finally {
                isOperating.value = false;
            }
        },
    };
};

const confirmDeleteItem = (item: any) => {
    confirmModal.value = {
        show: true,
        title: isRTL.value
            ? 'حذف من فهرس المكتبة'
            : 'Remove from Library Index',
        message: isRTL.value
            ? `هل أنت متأكد من حذف "${item.title}" من فهرس المكتبة؟
(ملاحظة: لن يتم حذف الملف الفعلي من القرص الصلب)`
            : `Are you sure you want to remove "${item.title}" from the library index?
(Note: The physical file on your disk will NOT be deleted).`,
        confirmText: isRTL.value ? 'حذف من الفهرس' : 'Remove Index',
        cancelText: isRTL.value ? 'إلغاء' : 'Cancel',
        type: 'danger',
        action: async () => {
            confirmModal.value.show = false;
            isOperating.value = true;
            try {
                const res = await fetch(
                    `/api/metadata/${item.type}/${item.id}`,
                    {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN':
                                (
                                    document.querySelector(
                                        'meta[name="csrf-token"]',
                                    ) as any
                                )?.content || '',
                        },
                    },
                );
                const data = await res.json();
                if (data.success) {
                    showToast(
                        data.message ||
                            (isRTL.value
                                ? 'تم حذف العنصر من الفهرس!'
                                : 'Item removed from library index!'),
                        'info',
                    );
                    localItems.value = localItems.value.filter(
                        (i) => !(i.id === item.id && i.type === item.type),
                    );
                    router.reload();
                } else {
                    showToast(
                        data.message || 'Failed to delete index.',
                        'danger',
                    );
                }
            } catch (e) {
                console.error(e);
                showToast('Error deleting index.', 'danger');
            } finally {
                isOperating.value = false;
            }
        },
    };
};

const triggerBatchEnrich = async () => {
    confirmModal.value = {
        show: true,
        title: isRTL.value
            ? 'بدء المعالجة الذكية الشاملة'
            : 'Trigger Automated Batch Metadata Enrichment',
        message: isRTL.value
            ? 'سيقوم النظام بالبحث في قواعد بيانات TMDb و Wikipedia لجميع العناصر غير المطابقة وتحميل البوسترات وترجمة الملخصات تلقائياً في الخلفية. هل تريد المتابعة؟'
            : 'The engine will query TMDb, Wikipedia, and subtitle databases for all pending and unmatched media items in the background. Do you want to proceed?',
        confirmText: isRTL.value ? 'بدء الآن' : 'Start Auto-Enrich',
        type: 'info',
        action: async () => {
            confirmModal.value.show = false;
            isBatchEnriching.value = true;
            showToast(
                isRTL.value
                    ? 'جارٍ تشغيل المعالجة الذكية في الخلفية...'
                    : 'Batch enrichment running in background...',
                'info',
            );
            try {
                const res = await fetch('/api/metadata/batch-enrich', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            (
                                document.querySelector(
                                    'meta[name="csrf-token"]',
                                ) as any
                            )?.content || '',
                    },
                });
                const data = await res.json();
                showToast(
                    data.message ||
                        (isRTL.value
                            ? 'اكتملت المعالجة الذكية!'
                            : 'Batch enrichment finished!'),
                    'success',
                );
                router.reload();
            } catch (e) {
                console.error(e);
                showToast('Error running batch enrichment.', 'danger');
            } finally {
                isBatchEnriching.value = false;
            }
        },
    };
};
</script>

<template>
    <AppLayout>
        <Head
            :title="
                isRTL
                    ? 'استوديو إصلاح وتصحيح البيانات'
                    : 'Fix Match & Metadata Studio'
            "
        />

        <div class="mx-auto max-w-7xl space-y-8 pb-24">
            <!-- Header Showcase Hero -->
            <PageBanner
                :icon="Sparkles"
                :title="
                    isRTL
                        ? 'إصلاح وتصحيح مكتبة الوسائط'
                        : 'Fix Match & Error Resolution Studio'
                "
                :badge="
                    isRTL
                        ? 'استوديو تصحيح ومطابقة البيانات'
                        : 'Metadata Correction & Triage Studio'
                "
                :description="
                    isRTL
                        ? 'استعراض المسار الفعلي للملفات، إصلاح التسميات غير المطابقة، إعادة تسمية الملفات على القرص، حذف العناصر غير المرغوبة من الفهرس، وجلب البوسترات بدقة.'
                        : 'Inspect physical disk paths, fix unmatched titles, rename disk files to standard titles, remove obsolete indexes, and fetch pristine artwork.'
                "
            >
                <template #actions>
                    <button
                        type="button"
                        @click="triggerBatchEnrich"
                        :disabled="isBatchEnriching"
                        class="bg-primary text-primary-foreground shadow-primary/25 flex shrink-0 cursor-pointer items-center gap-2.5 rounded-2xl px-6 py-3 text-sm font-black shadow-lg transition-all hover:brightness-105 active:scale-95 disabled:opacity-50"
                    >
                        <RefreshCw
                            v-if="isBatchEnriching"
                            class="size-4 animate-spin"
                        />
                        <Wand2 v-else class="size-4" />
                        <span>{{
                            isRTL
                                ? 'معالجة ومطابقة شاملة تلقائية'
                                : 'Auto-Fix All Unmatched'
                        }}</span>
                    </button>
                </template>
            </PageBanner>

            <!-- Live Quick Stats Counter -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div
                    class="cf-glass border-border/80 rounded-2xl border p-3.5 tabular-nums"
                >
                    <span class="text-muted-foreground block text-xs">{{
                        isRTL ? 'إجمالي العناصر' : 'Total Items'
                    }}</span>
                    <span
                        class="text-foreground mt-1 block text-xl font-black"
                        >{{ stats.total_items }}</span
                    >
                </div>
                <div
                    class="cf-glass rounded-2xl border border-amber-500/25 bg-amber-500/5 p-3.5 tabular-nums"
                >
                    <span class="block text-xs text-amber-300">{{
                        isRTL ? 'غير مطابق' : 'Unmatched'
                    }}</span>
                    <span
                        class="mt-1 block text-xl font-black text-amber-400"
                        >{{ stats.unmatched_count ?? 0 }}</span
                    >
                </div>
                <div
                    class="cf-glass rounded-2xl border border-rose-500/25 bg-rose-500/5 p-3.5 tabular-nums"
                >
                    <span class="block text-xs text-rose-300">{{
                        isRTL ? 'بدون بوستر' : 'Missing Posters'
                    }}</span>
                    <span class="mt-1 block text-xl font-black text-rose-400">{{
                        stats.missing_posters_count
                    }}</span>
                </div>
                <div
                    class="cf-glass border-primary/25 bg-primary/5 rounded-2xl border p-3.5 tabular-nums"
                >
                    <span class="text-primary block text-xs">{{
                        isRTL ? 'بدون عربي' : 'Missing Arabic'
                    }}</span>
                    <span class="text-primary mt-1 block text-xl font-black">{{
                        stats.missing_arabic_count
                    }}</span>
                </div>
                <div
                    class="cf-glass border-border/80 rounded-2xl border p-3.5 tabular-nums"
                >
                    <span class="text-muted-foreground block text-xs">{{
                        isRTL ? 'أفلام' : 'Movies'
                    }}</span>
                    <span
                        class="text-foreground mt-1 block text-xl font-black"
                        >{{ stats.movies_count }}</span
                    >
                </div>
                <div
                    class="cf-glass border-border/80 rounded-2xl border p-3.5 tabular-nums"
                >
                    <span class="text-muted-foreground block text-xs">{{
                        isRTL ? 'مسلسلات' : 'Series'
                    }}</span>
                    <span
                        class="text-foreground mt-1 block text-xl font-black"
                        >{{ stats.series_count }}</span
                    >
                </div>
            </div>

            <!-- Triage Filters & Search Bar -->
            <div
                class="flex flex-col items-center justify-between gap-4 md:flex-row"
            >
                <!-- Filter Tabs -->
                <div
                    class="cf-glass border-border/80 flex w-full flex-wrap items-center gap-1.5 rounded-2xl border p-1.5 md:w-auto"
                >
                    <button
                        type="button"
                        @click="applyFilter('all')"
                        class="cursor-pointer rounded-xl px-4 py-2 text-xs font-bold transition-all"
                        :class="
                            activeFilter === 'all'
                                ? 'bg-primary text-primary-foreground shadow-primary/20 shadow-md'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ isRTL ? 'الكل' : 'All Media' }}
                    </button>
                    <button
                        type="button"
                        @click="applyFilter('unmatched')"
                        class="cursor-pointer rounded-xl px-4 py-2 text-xs font-bold transition-all"
                        :class="
                            activeFilter === 'unmatched'
                                ? 'bg-primary text-primary-foreground shadow-primary/20 shadow-md'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ isRTL ? 'غير مطابق' : 'Unmatched' }}
                        <span
                            v-if="stats.unmatched_count"
                            class="py-0.2 ms-1 rounded-full bg-black/25 px-1.5 text-[10px] tabular-nums"
                        >
                            {{ stats.unmatched_count }}
                        </span>
                    </button>
                    <button
                        type="button"
                        @click="applyFilter('missing_posters')"
                        class="cursor-pointer rounded-xl px-4 py-2 text-xs font-bold transition-all"
                        :class="
                            activeFilter === 'missing_posters'
                                ? 'bg-primary text-primary-foreground shadow-primary/20 shadow-md'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ isRTL ? 'بدون بوستر' : 'Missing Posters' }}
                    </button>
                    <button
                        type="button"
                        @click="applyFilter('missing_arabic')"
                        class="cursor-pointer rounded-xl px-4 py-2 text-xs font-bold transition-all"
                        :class="
                            activeFilter === 'missing_arabic'
                                ? 'bg-primary text-primary-foreground shadow-primary/20 shadow-md'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ isRTL ? 'بدون عربي' : 'Missing Arabic' }}
                    </button>
                    <button
                        type="button"
                        @click="applyFilter('movies')"
                        class="cursor-pointer rounded-xl px-4 py-2 text-xs font-bold transition-all"
                        :class="
                            activeFilter === 'movies'
                                ? 'bg-primary text-primary-foreground shadow-primary/20 shadow-md'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ isRTL ? 'أفلام فقط' : 'Movies Only' }}
                    </button>
                    <button
                        type="button"
                        @click="applyFilter('series')"
                        class="cursor-pointer rounded-xl px-4 py-2 text-xs font-bold transition-all"
                        :class="
                            activeFilter === 'series'
                                ? 'bg-primary text-primary-foreground shadow-primary/20 shadow-md'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ isRTL ? 'مسلسلات فقط' : 'Series Only' }}
                    </button>
                </div>

                <!-- Instant Search Input -->
                <div class="relative w-full md:w-80">
                    <Search
                        class="text-muted-foreground pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2"
                    />
                    <input
                        v-model="searchQuery"
                        @keyup.enter="handleSearch"
                        type="text"
                        :placeholder="
                            isRTL
                                ? 'ابحث بالاسم أو المسار...'
                                : 'Search title or disk path...'
                        "
                        class="cf-field w-full rounded-2xl py-2.5 ps-10 pe-4 font-mono text-xs"
                    />
                </div>
            </div>

            <!-- Items List Container -->
            <div v-if="localItems.length > 0" class="space-y-4">
                <div
                    v-for="item in localItems"
                    :key="`${item.type}-${item.id}`"
                    class="cf-glass border-border/80 hover:border-primary/45 group flex flex-col gap-3 rounded-3xl border p-4 transition-all"
                >
                    <!-- Top Row: Poster, Title & Actions -->
                    <div
                        class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center"
                    >
                        <!-- Left: Poster + Core Info -->
                        <div class="flex min-w-0 flex-1 items-center gap-4">
                            <!-- Poster Preview -->
                            <div
                                class="relative aspect-[2/3] w-14 shrink-0 overflow-hidden rounded-xl border border-white/10 bg-slate-950 shadow-md"
                            >
                                <img
                                    v-if="item.poster_path"
                                    :src="item.poster_path"
                                    :alt="item.title"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center bg-slate-900 text-slate-700"
                                >
                                    <Film
                                        v-if="item.type === 'movie'"
                                        class="h-5 w-5 text-cyan-400/50"
                                    />
                                    <Tv
                                        v-else
                                        class="h-5 w-5 text-purple-400/50"
                                    />
                                </div>

                                <!-- Match Status Dot -->
                                <div
                                    class="absolute top-1 right-1 h-2.5 w-2.5 rounded-full border border-black/80 shadow-sm"
                                    :class="
                                        item.is_matched
                                            ? 'bg-emerald-400 shadow-emerald-400/50'
                                            : 'animate-pulse bg-amber-400'
                                    "
                                    :title="
                                        item.is_matched
                                            ? 'TMDb Matched'
                                            : 'Unmatched / Pending Fix'
                                    "
                                ></div>
                            </div>

                            <!-- Title & Metadata Badges -->
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded-md px-2 py-0.5 text-[10px] font-black uppercase"
                                        :class="
                                            item.type === 'series'
                                                ? 'border border-purple-500/30 bg-purple-500/20 text-purple-300'
                                                : 'border border-cyan-500/30 bg-cyan-500/20 text-cyan-300'
                                        "
                                    >
                                        {{ item.type }}
                                    </span>

                                    <h3
                                        class="truncate text-sm font-bold text-white transition-colors group-hover:text-cyan-400"
                                    >
                                        {{ item.title }}
                                    </h3>

                                    <span
                                        v-if="item.title_ar"
                                        class="truncate text-xs font-medium text-cyan-300"
                                        dir="rtl"
                                    >
                                        ({{ item.title_ar }})
                                    </span>

                                    <span
                                        v-if="item.release_year"
                                        class="rounded bg-white/[0.06] px-1.5 py-0.5 text-[10px] font-semibold text-slate-300"
                                    >
                                        {{ item.release_year }}
                                    </span>
                                </div>

                                <!-- Status Badges -->
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-bold"
                                        :class="
                                            item.is_matched
                                                ? 'border border-emerald-500/20 bg-emerald-500/10 text-emerald-400'
                                                : 'border border-amber-500/30 bg-amber-500/10 text-amber-300'
                                        "
                                    >
                                        <CheckCircle2
                                            v-if="item.is_matched"
                                            class="h-3 w-3"
                                        />
                                        <AlertTriangle v-else class="h-3 w-3" />
                                        <span>{{
                                            item.is_matched
                                                ? 'TMDb Matched'
                                                : isRTL
                                                  ? 'غير مطابق'
                                                  : 'Unmatched'
                                        }}</span>
                                    </span>

                                    <span
                                        class="flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-bold"
                                        :class="
                                            item.has_arabic
                                                ? 'border border-purple-500/20 bg-purple-500/10 text-purple-300'
                                                : 'border border-white/10 bg-white/[0.05] text-slate-400'
                                        "
                                    >
                                        <Globe class="h-3 w-3" />
                                        <span>{{
                                            item.has_arabic
                                                ? isRTL
                                                    ? 'عربي متوفر'
                                                    : 'Arabic Ready'
                                                : isRTL
                                                  ? 'بدون عربي'
                                                  : 'No Arabic'
                                        }}</span>
                                    </span>

                                    <span
                                        v-if="item.collection_name"
                                        class="inline-flex items-center gap-1 rounded border border-cyan-500/20 bg-cyan-500/10 px-2 py-0.5 text-[10px] font-bold text-cyan-300"
                                    >
                                        <Layers class="h-2.5 w-2.5" />
                                        <span>{{ item.collection_name }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Action Buttons -->
                        <div
                            class="flex shrink-0 items-center gap-2 self-end md:self-center"
                        >
                            <!-- Rename File on Disk -->
                            <button
                                @click="confirmRenameFile(item)"
                                :disabled="isOperating"
                                class="cursor-pointer rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-2.5 text-xs font-bold text-emerald-300 transition-all hover:bg-emerald-500/20"
                                :title="
                                    isRTL
                                        ? 'إعادة تسمية الملف الفعلي على القرص ليطابق العنوان النظيف'
                                        : 'Rename physical file on disk to clean standard title'
                                "
                            >
                                <FileEdit class="h-4 w-4" />
                            </button>

                            <!-- Quick Re-Parse -->
                            <button
                                @click="quickReparse(item)"
                                :disabled="isOperating"
                                class="cursor-pointer rounded-xl border border-white/10 bg-white/[0.05] p-2.5 text-xs font-bold text-slate-300 transition-all hover:bg-white/[0.1] hover:text-cyan-300"
                                :title="
                                    isRTL
                                        ? 'إعادة تفكيك اسم الملف بالخوارزمية الذكية'
                                        : 'Re-parse file with intelligent parser'
                                "
                            >
                                <Repeat class="h-4 w-4" />
                            </button>

                            <!-- Quick Convert Type -->
                            <button
                                @click="quickConvert(item)"
                                :disabled="isOperating"
                                class="cursor-pointer rounded-xl border border-purple-500/20 bg-purple-500/10 p-2.5 text-xs font-bold text-purple-300 transition-all hover:bg-purple-500/20"
                                :title="
                                    item.type === 'movie'
                                        ? isRTL
                                            ? 'تحويل لمسلسل'
                                            : 'Convert to Series'
                                        : isRTL
                                          ? 'تحويل لفيلم'
                                          : 'Convert to Movie'
                                "
                            >
                                <ArrowRightLeft class="h-4 w-4" />
                            </button>

                            <!-- Delete Index from Library -->
                            <button
                                @click="confirmDeleteItem(item)"
                                :disabled="isOperating"
                                class="cursor-pointer rounded-xl border border-rose-500/20 bg-rose-500/10 p-2.5 text-xs font-bold text-rose-400 transition-all hover:bg-rose-500/20 hover:text-rose-300"
                                :title="
                                    isRTL
                                        ? 'حذف من فهرس المكتبة'
                                        : 'Remove item from library index'
                                "
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>

                            <!-- Fix Match Modal Trigger -->
                            <button
                                type="button"
                                @click="openFixMatch(item)"
                                class="bg-primary text-primary-foreground shadow-primary/20 flex cursor-pointer items-center gap-1.5 rounded-xl px-4 py-2.5 text-xs font-black shadow-md transition-all hover:brightness-105 active:scale-95"
                            >
                                <Wand2 class="size-3.5" />
                                <span>{{
                                    isRTL ? 'إصلاح ومطابقة' : 'Fix Match'
                                }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Bottom Row: Full Physical Storage File Path Box (Un-truncated, Fully Visible & Copyable) -->
                    <div
                        class="flex items-center justify-between gap-3 rounded-2xl border border-white/5 bg-slate-950/80 p-2.5 shadow-inner transition-colors hover:border-white/10"
                    >
                        <div
                            class="flex min-w-0 flex-1 items-start gap-2 overflow-hidden sm:items-center"
                        >
                            <FileVideo
                                v-if="item.type === 'movie'"
                                class="mt-0.5 h-3.5 w-3.5 shrink-0 text-cyan-400 sm:mt-0"
                            />
                            <Folder
                                v-else
                                class="mt-0.5 h-3.5 w-3.5 shrink-0 text-purple-400 sm:mt-0"
                            />
                            <div class="min-w-0 flex-1">
                                <span
                                    class="block font-mono text-[11px] leading-relaxed break-all text-slate-300 select-all"
                                >
                                    {{
                                        item.file_path ||
                                        item.folder_path ||
                                        'No Path'
                                    }}
                                </span>
                            </div>
                        </div>
                        <button
                            @click="copyFilePath(item)"
                            type="button"
                            class="flex shrink-0 cursor-pointer items-center gap-1 rounded-lg border border-white/5 bg-white/[0.06] px-2.5 py-1 text-[11px] font-semibold text-slate-300 transition-all hover:border-cyan-500/30 hover:bg-white/[0.12] active:scale-95"
                            :title="
                                isRTL
                                    ? 'نسخ المسار بالكامل'
                                    : 'Copy full file path'
                            "
                        >
                            <Check
                                v-if="copiedItemId === item.id"
                                class="h-3 w-3 text-emerald-400"
                            />
                            <Copy v-else class="h-3 w-3 text-slate-400" />
                            <span>{{
                                copiedItemId === item.id
                                    ? isRTL
                                        ? 'تم النسخ!'
                                        : 'Copied!'
                                    : isRTL
                                      ? 'نسخ المسار'
                                      : 'Copy'
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="space-y-4 rounded-3xl border border-dashed border-white/10 p-16 text-center"
            >
                <div
                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-white/10 bg-slate-900 text-slate-600"
                >
                    <CheckCircle2 class="h-8 w-8 text-emerald-400" />
                </div>
                <h3 class="text-xl font-bold text-white">
                    {{
                        isRTL
                            ? 'لا توجد عناصر بحاجة للإصلاح في هذا القسم'
                            : 'All Media Clean & Matched!'
                    }}
                </h3>
                <p class="mx-auto max-w-md text-sm text-slate-400">
                    {{
                        isRTL
                            ? 'جميع ملفات الوسائط الخاصة بك تمت مطابقتها بنجاح مع البوسترات والعناوين.'
                            : 'All media items in your library are properly indexed and matched with online metadata.'
                    }}
                </p>
            </div>
        </div>

        <!-- Fix Match Studio Modal -->
        <FixMatchModal
            v-if="showFixModal"
            :show="showFixModal"
            :item="selectedItemForFix"
            :type="selectedItemForFix?.type"
            @close="showFixModal = false"
            @updated="handleItemUpdated"
            @deleted="confirmDeleteItem"
        />

        <!-- Confirm Modal Dialog -->
        <ConfirmModal
            :show="confirmModal.show"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :confirm-text="confirmModal.confirmText"
            :cancel-text="confirmModal.cancelText"
            :type="confirmModal.type"
            @confirm="confirmModal.action"
            @close="confirmModal.show = false"
            @cancel="confirmModal.show = false"
        />
    </AppLayout>
</template>
