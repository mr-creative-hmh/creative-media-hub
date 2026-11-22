<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import { formatBytes } from '@/lib/formatters';
import AppLayout from '@/components/layout/AppLayout.vue';
import PageBanner from '@/components/common/PageBanner.vue';
import { useThemeAccent } from '@/composables/useThemeAccent';
import {
    Settings as SettingsIcon,
    Save,
    Key,
    CheckCircle2,
    ArrowUp,
    ArrowDown,
    Layers,
    Cpu,
    Database,
    HardDrive,
    RefreshCw,
    Zap,
    Check,
    X,
    Trash2,
    DownloadCloud,
    UploadCloud,
    RotateCcw,
    FileText,
    Clock,
    AlertTriangle,
    Shield,
    CheckCheck,
    FolderArchive,
    Film,
    Tv,
    Palette,
} from '@lucide/vue';
import ConfirmModal from '@/components/common/ConfirmModal.vue';

const props = defineProps<{
    settings: {
        tmdb_api_key: string;
        omdb_api_key: string;
        opensubtitles_api_key: string;
        ffmpeg_path: string;
        default_language: string;
        auto_fetch_metadata: boolean;
        auto_fetch_subtitles: boolean;
        preferred_providers: string[];
    };
    media_cache?: {
        size_bytes: number;
        formatted_size: string;
        file_count: number;
    };
    initial_backups?: any[];
    database_stats?: {
        media_items: number;
        series: number;
        episodes: number;
        subtitles: number;
        genres: number;
    };
}>();

const { t, isRTL, setLocale } = useI18n();
const { currentAccent, activePalette, setAccent, ACCENT_PALETTES } =
    useThemeAccent();

const form = ref({
    tmdb_api_key: props.settings.tmdb_api_key || '',
    omdb_api_key: props.settings.omdb_api_key || '',
    opensubtitles_api_key: props.settings.opensubtitles_api_key || '',
    ffmpeg_path: props.settings.ffmpeg_path || 'ffmpeg',
    default_language: props.settings.default_language || 'ar',
    auto_fetch_metadata: props.settings.auto_fetch_metadata ?? true,
    auto_fetch_subtitles: props.settings.auto_fetch_subtitles ?? true,
});

// All available metadata providers with keyless vs keyed metadata
const allProviders = ref([
    {
        id: 'tvmaze',
        name: 'TVMaze Free API',
        type: 'keyless',
        desc: 'TV shows, seasons, episode summaries, air dates & posters. No rate-limit issues.',
        descAr: 'مسلسلات ومواسم وحلقات وأغلفة بدون أي مفتاح API وبدون قيود.',
        badge: '100% Free / Keyless',
        badgeColor: 'emerald',
    },
    {
        id: 'anilist',
        name: 'AniList GraphQL',
        type: 'keyless',
        desc: 'Comprehensive Anime & Asian drama metadata, titles, and posters.',
        descAr: 'قاعدة بيانات الأنمي والدراما الآسيوية والبوسترات المباشرة مجاناً.',
        badge: '100% Free / Keyless',
        badgeColor: 'emerald',
    },
    {
        id: 'wikipedia',
        name: 'Wikipedia / Wikidata API',
        type: 'keyless',
        desc: 'Plot summaries, direct Arabic translations & open artwork thumbnails.',
        descAr: 'ترجمات عربية للقصة والعناوين والبوسترات الحرة من ويكيبيديا.',
        badge: '100% Free / Keyless',
        badgeColor: 'emerald',
    },
    {
        id: 'local_nfo',
        name: 'Local NFO & Folder Art',
        type: 'keyless',
        desc: 'Reads local poster.jpg, fanart.jpg and .nfo files directly from hard drive.',
        descAr: 'قراءة ملفات البوستر والصور وملفات NFO المحلية من مجلد الوسائط مباشرة.',
        badge: 'Offline / Local Disk',
        badgeColor: 'cyan',
    },
    {
        id: 'tmdb',
        name: 'The Movie Database (TMDb)',
        type: 'keyed',
        desc: 'Cinema standard database with 4K backdrops, cast info & multi-language data.',
        descAr: 'قاعدة بيانات الأفلام العالمية مع خلفيات 4K وتفاصيل الممثلين والتقييمات.',
        badge: 'API Key Required',
        badgeColor: 'amber',
    },
    {
        id: 'omdb',
        name: 'Open Movie Database (OMDb)',
        type: 'keyed',
        desc: 'IMDb ratings, Rotten Tomatoes scores & Metacritic values.',
        descAr: 'تقييمات IMDb و Rotten Tomatoes و Metacritic الرسمية.',
        badge: 'API Key Required',
        badgeColor: 'amber',
    },
]);

// Sorted by user priority
const preferredOrder = ref<string[]>(
    props.settings.preferred_providers?.length
        ? props.settings.preferred_providers
        : ['tvmaze', 'anilist', 'wikipedia', 'local_nfo', 'tmdb', 'omdb'],
);

const sortedProviders = ref(
    [...allProviders.value].sort((a, b) => {
        const idxA = preferredOrder.value.indexOf(a.id);
        const idxB = preferredOrder.value.indexOf(b.id);
        return (idxA === -1 ? 99 : idxA) - (idxB === -1 ? 99 : idxB);
    }),
);

const moveUp = (index: number) => {
    if (index <= 0) return;
    const item = sortedProviders.value.splice(index, 1)[0];
    sortedProviders.value.splice(index - 1, 0, item);
    preferredOrder.value = sortedProviders.value.map((p) => p.id);
};

const moveDown = (index: number) => {
    if (index >= sortedProviders.value.length - 1) return;
    const item = sortedProviders.value.splice(index, 1)[0];
    sortedProviders.value.splice(index + 1, 0, item);
    preferredOrder.value = sortedProviders.value.map((p) => p.id);
};

const isSaving = ref(false);
const toast = useToast();

// Test status per provider
const testingProviderId = ref<string | null>(null);
const testResults = ref<
    Record<string, { success: boolean; message: string; latency_ms?: number }>
>({});

const testSingleProvider = async (providerId: string, customKey?: string) => {
    testingProviderId.value = providerId;
    try {
        const res = await fetch('/api/settings/test-provider', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
            body: JSON.stringify({
                provider: providerId,
                key:
                    customKey ||
                    (form.value as any)[`${providerId}_api_key`] ||
                    undefined,
            }),
        });

        const data = await res.json();
        testResults.value[providerId] = {
            success: res.ok && data.success,
            message: data.message,
            latency_ms: data.latency_ms,
        };
    } catch (e: any) {
        testResults.value[providerId] = {
            success: false,
            message: e.message || 'Connection failed.',
        };
    } finally {
        testingProviderId.value = null;
    }
};

const saveSettings = async () => {
    isSaving.value = true;

    try {
        const res = await fetch('/api/settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
            body: JSON.stringify({
                ...form.value,
                preferred_providers: preferredOrder.value,
            }),
        });

        if (res.ok) {
            setLocale(form.value.default_language);
            toast.success(
                isRTL.value
                    ? 'تم حفظ الإعدادات وترتيب المزودين بنجاح!'
                    : 'Settings & provider priorities saved successfully!',
                isRTL.value ? 'تم الحفظ' : 'Settings Saved',
            );
        }
    } finally {
        isSaving.value = false;
    }
};

// Media Stream Cache Management
const mediaCacheStats = ref(
    props.media_cache || {
        size_bytes: 0,
        formatted_size: '0 B',
        file_count: 0,
    },
);
const isClearingCache = ref(false);
const cacheSuccessMessage = ref<string | null>(null);

const clearMediaCache = async () => {
    isClearingCache.value = true;
    cacheSuccessMessage.value = null;
    try {
        const res = await fetch('/api/settings/clear-media-cache', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
        });
        const data = await res.json();
        if (data.success) {
            mediaCacheStats.value = data.stats;
            cacheSuccessMessage.value = isRTL.value
                ? `تم تنظيف الكاش بنجاح! تم تحرير ${data.freed_formatted} (${data.stats.file_count} ملفات متبقية)`
                : data.message;
            setTimeout(() => {
                cacheSuccessMessage.value = null;
            }, 6000);
        }
    } catch (e) {
        console.error('Failed to clear media cache:', e);
    } finally {
        isClearingCache.value = false;
    }
};

const refreshCacheStats = async () => {
    try {
        const res = await fetch('/api/settings/media-cache-stats');
        const data = await res.json();
        mediaCacheStats.value = data;
    } catch (e) {
        console.error('Failed to refresh cache stats:', e);
    }
};

// ==========================================
// Database Backup & Disaster Recovery State
// ==========================================
const localBackups = ref<any[]>(props.initial_backups || []);
const isCreatingBackup = ref(false);
const isRestoring = ref(false);
const backupSuccessMessage = ref<string | null>(null);
const backupErrorMessage = ref<string | null>(null);
const restoreMode = ref<'overwrite' | 'merge'>('overwrite');
const selectedFile = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

// Selective Database Sections
const availableSections = [
    {
        id: 'movies',
        labelEn: 'Movies & Collections',
        labelAr: 'الأفلام والمجموعات',
        descEn: 'Standalone movies, collections, file paths & movie genres',
        descAr: 'فهارس الأفلام، المجموعات، مسارات الملفات وتصنيفات الأفلام',
        icon: 'Film',
        countKey: 'media_items',
    },
    {
        id: 'series',
        labelEn: 'TV Series, Seasons & Episodes',
        labelAr: 'المسلسلات والمواسم والحلقات',
        descEn: 'TV shows, season catalogs, episode data & TV genres',
        descAr: 'المسلسلات، فهارس المواسم، بيانات الحلقات وتصنيفات المسلسلات',
        icon: 'Tv',
        countKey: 'series',
    },
    {
        id: 'subtitles',
        labelEn: 'Subtitles',
        labelAr: 'الترجمات المزامنة',
        descEn: 'All external and embedded subtitle records & language tags',
        descAr: 'سجلات ملفات الترجمة الخارجية والمدمجة وعلامات اللغات',
        icon: 'FileText',
        countKey: 'subtitles',
    },
    {
        id: 'settings',
        labelEn: 'System Settings & API Keys',
        labelAr: 'إعدادات النظام ومفاتيح الربط',
        descEn: 'API keys, provider priorities, library configs & preferences',
        descAr: 'مفاتيح API، أولويات المزودين، إعدادات المكتبة والخيارات',
        icon: 'SettingsIcon',
        countKey: null,
    },
    {
        id: 'watch_history',
        labelEn: 'Watch History & Progress',
        labelAr: 'سجل المشاهدة والتقدم',
        descEn: 'Resume timestamps, watched episodes & play history',
        descAr: 'نقاط الاستئناف، الحلقات المكتملة وسجل التشغيل',
        icon: 'Clock',
        countKey: null,
    },
];

const selectedSections = ref<string[]>([
    'movies',
    'series',
    'subtitles',
    'settings',
    'watch_history',
]);

const toggleSection = (id: string) => {
    if (selectedSections.value.includes(id)) {
        selectedSections.value = selectedSections.value.filter((s) => s !== id);
    } else {
        selectedSections.value.push(id);
    }
};

const selectAllSections = () => {
    selectedSections.value = availableSections.map((s) => s.id);
};

const clearAllSections = () => {
    selectedSections.value = [];
};

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

const triggerConfirmAction = async () => {
    if (confirmModal.value.action) {
        await confirmModal.value.action();
    }
};

const fetchBackupsList = async () => {
    try {
        const res = await fetch('/api/database/backups');
        if (res.ok) {
            const data = await res.json();
            localBackups.value = data.backups || [];
        }
    } catch (e) {
        console.error('Failed to fetch backups:', e);
    }
};

const downloadDatabaseBackup = () => {
    window.location.href = '/api/database/backup/export';
};

const createServerSnapshot = async () => {
    isCreatingBackup.value = true;
    backupSuccessMessage.value = null;
    backupErrorMessage.value = null;

    try {
        const res = await fetch('/api/database/backups/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
            body: JSON.stringify({ format: 'both' }),
        });

        const data = await res.json();
        if (res.ok && data.success) {
            backupSuccessMessage.value = isRTL.value
                ? `تم إنشاء لقطة احتياطية كاملة بنجاح (${data.name})!`
                : `Local server snapshot created successfully (${data.name})!`;
            await fetchBackupsList();
            setTimeout(() => {
                backupSuccessMessage.value = null;
            }, 6000);
        } else {
            backupErrorMessage.value =
                data.message || 'Failed to create backup.';
        }
    } catch (e: any) {
        backupErrorMessage.value = e.message || 'Error creating backup.';
    } finally {
        isCreatingBackup.value = false;
    }
};

const onFileSelected = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        selectedFile.value = target.files[0];
    }
};

const promptRestoreUpload = () => {
    if (!selectedFile.value) return;
    if (selectedSections.value.length === 0) {
        backupErrorMessage.value = isRTL.value
            ? 'يرجى تحديد قسم واحد على الأقل للاستعادة.'
            : 'Please select at least one section to restore.';
        return;
    }

    const sectionLabels = selectedSections.value
        .map((id) => {
            const s = availableSections.find((sec) => sec.id === id);
            return isRTL.value ? s?.labelAr : s?.labelEn;
        })
        .join(', ');

    confirmModal.value = {
        show: true,
        title: isRTL.value
            ? 'تأكيد استعادة الأقسام المحددة'
            : 'Confirm Selective Restore',
        message: isRTL.value
            ? `سيتم استعادة الأقسام المختارة (${sectionLabels}) من الملف "${selectedFile.value.name}" بنمط "${restoreMode.value === 'overwrite' ? 'استبدال كامل للأقسام المختارة' : 'دمج وتحديث'}". الأقسام غير المختارة ستبقى آمنة وغير ممسوسة. سيتم أخذ لقطة أمان احتياطية تلقائياً قبل البدء. هل تريد المتابعة؟`
            : `Restore selected sections (${sectionLabels}) from "${selectedFile.value.name}" using "${restoreMode.value}" mode? Unselected sections will remain completely untouched. Continue?`,
        confirmText: isRTL.value
            ? 'تأكيد الاستعادة المحددة'
            : 'Proceed with Restore',
        type: 'warning',
        action: async () => {
            confirmModal.value.show = false;
            await executeRestoreUpload();
        },
    };
};

const executeRestoreUpload = async () => {
    if (!selectedFile.value || selectedSections.value.length === 0) return;
    isRestoring.value = true;
    backupSuccessMessage.value = null;
    backupErrorMessage.value = null;

    try {
        const formData = new FormData();
        formData.append('backup_file', selectedFile.value);
        formData.append('mode', restoreMode.value);
        selectedSections.value.forEach((sec) =>
            formData.append('sections[]', sec),
        );

        const res = await fetch('/api/database/restore/upload', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
            body: formData,
        });

        const data = await res.json();
        if (res.ok && data.success) {
            let details = '';
            if (data.restored_counts) {
                const parts = [];
                if (data.restored_counts.media_items !== undefined)
                    parts.push(`${data.restored_counts.media_items} movies`);
                if (data.restored_counts.series !== undefined)
                    parts.push(`${data.restored_counts.series} series`);
                if (data.restored_counts.subtitles !== undefined)
                    parts.push(`${data.restored_counts.subtitles} subtitles`);
                if (parts.length > 0) details = ` (${parts.join(', ')})`;
            }
            backupSuccessMessage.value = isRTL.value
                ? `تمت استعادة الأقسام المحددة بنجاح!${details}`
                : `Selected sections restored successfully!${details}`;
            selectedFile.value = null;
            if (fileInputRef.value) fileInputRef.value.value = '';
            await fetchBackupsList();
            setTimeout(() => {
                backupSuccessMessage.value = null;
            }, 10000);
        } else {
            backupErrorMessage.value = data.message || 'Restore failed.';
        }
    } catch (e: any) {
        backupErrorMessage.value = e.message || 'Error restoring database.';
    } finally {
        isRestoring.value = false;
    }
};

const promptRestoreLocal = (backup: any) => {
    if (selectedSections.value.length === 0) {
        backupErrorMessage.value = isRTL.value
            ? 'يرجى تحديد قسم واحد على الأقل للاستعادة.'
            : 'Please select at least one section to restore.';
        return;
    }

    const sectionLabels = selectedSections.value
        .map((id) => {
            const s = availableSections.find((sec) => sec.id === id);
            return isRTL.value ? s?.labelAr : s?.labelEn;
        })
        .join(', ');

    confirmModal.value = {
        show: true,
        title: isRTL.value
            ? 'استعادة الأقسام من لقطة محلية'
            : 'Restore Selected Sections from Snapshot',
        message: isRTL.value
            ? `هل تريد استعادة الأقسام (${sectionLabels}) من اللقطة "${backup.filename}" بنمط "${restoreMode.value === 'overwrite' ? 'استبدال كامل' : 'دمج وتحديث'}"؟ الأقسام غير المحددة ستبقى آمنة وغير ممسوسة. سيتم أخذ نسخة أمان احتياطية قبل الاستعادة.`
            : `Restore selected sections (${sectionLabels}) from snapshot "${backup.filename}" with "${restoreMode.value}" mode? Unselected sections will remain completely untouched. Continue?`,
        confirmText: isRTL.value ? 'تأكيد الاستعادة الآن' : 'Restore Now',
        type: 'warning',
        action: async () => {
            confirmModal.value.show = false;
            await executeRestoreLocal(backup.filename);
        },
    };
};

const executeRestoreLocal = async (filename: string) => {
    if (selectedSections.value.length === 0) return;
    isRestoring.value = true;
    backupSuccessMessage.value = null;
    backupErrorMessage.value = null;

    try {
        const res = await fetch('/api/database/restore/local', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
            body: JSON.stringify({
                filename,
                mode: restoreMode.value,
                sections: selectedSections.value,
            }),
        });

        const data = await res.json();
        if (res.ok && data.success) {
            let details = '';
            if (data.restored_counts) {
                const parts = [];
                if (data.restored_counts.media_items !== undefined)
                    parts.push(`${data.restored_counts.media_items} movies`);
                if (data.restored_counts.series !== undefined)
                    parts.push(`${data.restored_counts.series} series`);
                if (data.restored_counts.subtitles !== undefined)
                    parts.push(`${data.restored_counts.subtitles} subtitles`);
                if (parts.length > 0) details = ` (${parts.join(', ')})`;
            }
            backupSuccessMessage.value = isRTL.value
                ? `تمت استعادة الأقسام المحددة بنجاح من اللقطة ${filename}!${details}`
                : `Successfully restored selected sections from ${filename}!${details}`;
            await fetchBackupsList();
            setTimeout(() => {
                backupSuccessMessage.value = null;
            }, 10000);
        } else {
            backupErrorMessage.value = data.message || 'Restore failed.';
        }
    } catch (e: any) {
        backupErrorMessage.value = e.message || 'Error restoring snapshot.';
    } finally {
        isRestoring.value = false;
    }
};

const promptDeleteBackup = (backup: any) => {
    confirmModal.value = {
        show: true,
        title: isRTL.value ? 'حذف اللقطة الاحتياطية' : 'Delete Backup Snapshot',
        message: isRTL.value
            ? `هل تريد حذف ملف اللقطة الاحتياطية "${backup.filename}" نهائياً من الخادم؟`
            : `Are you sure you want to permanently delete "${backup.filename}"?`,
        confirmText: isRTL.value ? 'حذف نهائي' : 'Delete',
        type: 'danger',
        action: async () => {
            confirmModal.value.show = false;
            try {
                const res = await fetch(
                    `/api/database/backups/${encodeURIComponent(backup.filename)}`,
                    {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN':
                                (
                                    document.querySelector(
                                        'meta[name="csrf-token"]',
                                    ) as any
                                )?.content || '',
                        },
                    },
                );
                if (res.ok) {
                    await fetchBackupsList();
                }
            } catch (e) {
                console.error('Delete backup error:', e);
            }
        },
    };
};
</script>

<template>
    <AppLayout>
        <Head :title="t('settings_view.title')" />
        <!-- Header -->
        <PageBanner
            :icon="SettingsIcon"
            :title="t('settings_view.title')"
            :description="t('settings_view.subtitle')"
            class="mb-8"
        >
            <template #actions>
                <button
                    type="button"
                    @click="saveSettings"
                    :disabled="isSaving"
                    class="bg-primary text-primary-foreground shadow-primary/25 flex min-h-10 cursor-pointer items-center gap-2 rounded-xl px-6 py-2.5 text-xs font-black shadow-lg transition-all hover:brightness-105 active:scale-95 disabled:opacity-50"
                >
                    <RefreshCw v-if="isSaving" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    <span>{{ t('settings_view.save') }}</span>
                </button>
            </template>
        </PageBanner>

        <div class="space-y-8">
            <!-- 0. Virtual Cinema Theme & Dynamic Accent Color System -->
            <div
                class="cf-glass border-border/80 space-y-6 rounded-3xl border p-6 sm:p-8"
            >
                <div
                    class="border-border/70 flex flex-wrap items-center justify-between gap-4 border-b pb-4"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="border-primary/30 bg-primary/10 text-primary flex size-9 items-center justify-center rounded-xl border"
                        >
                            <Palette class="size-5" />
                        </div>
                        <div>
                            <h3 class="text-foreground text-base font-bold">
                                {{ t('accent_theme.title') }}
                            </h3>
                            <p class="text-muted-foreground text-xs">
                                {{ t('accent_theme.subtitle') }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="border-border/80 bg-card/60 flex items-center gap-2 rounded-xl border px-3 py-1.5"
                    >
                        <span
                            class="size-3 rounded-full transition-transform duration-300"
                            :style="{
                                backgroundColor: activePalette.color,
                                boxShadow: '0 0 12px ' + activePalette.color,
                            }"
                        ></span>
                        <span class="text-foreground text-xs font-bold">
                            {{ t(activePalette.nameKey) }}
                        </span>
                    </div>
                </div>

                <!-- 8 Palette Selection Grid -->
                <div
                    class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <button
                        v-for="palette in ACCENT_PALETTES"
                        :key="palette.key"
                        type="button"
                        @click="setAccent(palette.key)"
                        class="group relative flex cursor-pointer flex-col justify-between gap-3 overflow-hidden rounded-2xl border p-4 text-start transition-all"
                        :class="[
                            currentAccent === palette.key
                                ? 'bg-card/85 shadow-xl ring-2 shadow-black/60'
                                : 'border-border/80 bg-card/40 hover:border-primary/40 hover:bg-card/70',
                        ]"
                        :style="{
                            borderColor:
                                currentAccent === palette.key
                                    ? palette.color
                                    : undefined,
                            boxShadow:
                                currentAccent === palette.key
                                    ? `0 0 20px ${palette.glowColor}`
                                    : undefined,
                        }"
                    >
                        <!-- Top Row: Swatch & Active Pill -->
                        <div class="flex w-full items-center justify-between">
                            <div
                                class="flex size-8 items-center justify-center rounded-xl shadow-md transition-transform group-hover:scale-110"
                                :style="{
                                    background: `linear-gradient(135deg, ${palette.color} 0%, ${palette.secondaryColor} 100%)`,
                                    boxShadow: `0 0 16px ${palette.glowColor}`,
                                }"
                            >
                                <span
                                    v-if="currentAccent === palette.key"
                                    class="size-2 rounded-full bg-white shadow-xs"
                                ></span>
                            </div>

                            <span
                                v-if="currentAccent === palette.key"
                                class="rounded-full px-2 py-0.5 text-[10px] font-black tracking-wider uppercase"
                                :style="{
                                    backgroundColor: palette.color + '25',
                                    color: palette.color,
                                    border: `1px solid ${palette.color}60`,
                                }"
                            >
                                {{ t('accent_theme.active') }}
                            </span>
                        </div>

                        <!-- Bottom Row: Title & Tone -->
                        <div class="w-full">
                            <div
                                class="text-foreground text-sm font-bold transition-colors"
                            >
                                {{ t(palette.nameKey) }}
                            </div>
                            <div
                                class="text-muted-foreground mt-0.5 line-clamp-1 text-[11px]"
                            >
                                {{ t(palette.descKey) }}
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- 1. Metadata Providers Customization, Priority Ordering & Live Testing -->
            <div
                class="cf-glass border-border/80 space-y-6 rounded-3xl border p-6 sm:p-8"
            >
                <div
                    class="border-border/70 flex flex-wrap items-center justify-between gap-4 border-b pb-4"
                >
                    <div class="flex items-center gap-2.5">
                        <Layers class="text-primary size-5" />
                        <div>
                            <h3 class="text-foreground text-base font-bold">
                                {{
                                    isRTL
                                        ? 'أولويات مزودي البيانات والأغلفة وفحص الاتصال (Provider Priority & Test)'
                                        : 'Metadata Providers Priority Chain & Live Verification'
                                }}
                            </h3>
                            <p class="text-muted-foreground text-xs">
                                {{
                                    isRTL
                                        ? 'رتب أولوية البحث واضغط زر الفحص للتأكد من عمل المزود. المزودات المجانية تعمل مباشرة بدون أي مفتاح.'
                                        : 'Reorder priority chain and click test to verify provider health in real-time.'
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Provider Priority Cards -->
                <div class="space-y-3">
                    <div
                        v-for="(prov, pIdx) in sortedProviders"
                        :key="prov.id"
                        class="border-border/80 bg-card/50 hover:border-primary/45 group flex flex-col justify-between gap-4 rounded-2xl border p-4 transition-all sm:flex-row sm:items-center"
                    >
                        <div class="flex min-w-0 items-center gap-3.5">
                            <!-- Priority Rank Badge -->
                            <div
                                class="border-primary/30 bg-primary/10 text-primary flex size-7 shrink-0 items-center justify-center rounded-xl border text-xs font-black tabular-nums"
                            >
                                #{{ pIdx + 1 }}
                            </div>

                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4
                                        class="text-foreground text-xs font-black"
                                    >
                                        {{ prov.name }}
                                    </h4>
                                    <span
                                        class="cinema-badge text-[9px] font-bold"
                                        :class="
                                            prov.badgeColor === 'emerald'
                                                ? 'border-emerald-500/30 bg-emerald-500/20 text-emerald-300'
                                                : prov.badgeColor === 'cyan'
                                                  ? 'border-primary/30 bg-primary/15 text-primary'
                                                  : 'border-amber-500/30 bg-amber-500/20 text-amber-300'
                                        "
                                    >
                                        {{ prov.badge }}
                                    </span>

                                    <!-- Test Feedback Status Badge -->
                                    <span
                                        v-if="testResults[prov.id]"
                                        class="cinema-badge flex items-center gap-1 font-mono text-[9px] font-bold tabular-nums"
                                        :class="
                                            testResults[prov.id].success
                                                ? 'border-emerald-500/30 bg-emerald-500/20 text-emerald-300'
                                                : 'border-destructive/30 bg-destructive/20 text-destructive'
                                        "
                                    >
                                        <Check
                                            v-if="testResults[prov.id].success"
                                            class="size-3"
                                        />
                                        <X v-else class="size-3" />
                                        <span>{{
                                            testResults[prov.id].message
                                        }}</span>
                                    </span>
                                </div>
                                <p
                                    class="text-muted-foreground line-clamp-1 text-[11px]"
                                >
                                    {{ isRTL ? prov.descAr : prov.desc }}
                                </p>
                            </div>
                        </div>

                        <!-- Action & Move Buttons -->
                        <div
                            class="flex shrink-0 items-center gap-2 self-end sm:self-center"
                        >
                            <button
                                v-if="prov.id !== 'local_nfo'"
                                type="button"
                                @click="testSingleProvider(prov.id)"
                                :disabled="testingProviderId === prov.id"
                                class="border-border/80 bg-card/70 text-foreground/85 hover:border-primary/40 hover:text-primary flex cursor-pointer items-center gap-1.5 rounded-xl border px-3 py-1.5 text-[11px] font-bold transition-all"
                            >
                                <RefreshCw
                                    v-if="testingProviderId === prov.id"
                                    class="text-primary size-3 animate-spin"
                                />
                                <Zap v-else class="text-primary size-3" />
                                <span>{{
                                    isRTL ? 'فحص الاتصال' : 'Test Connection'
                                }}</span>
                            </button>

                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    @click="moveUp(pIdx)"
                                    :disabled="pIdx === 0"
                                    class="bg-card/70 text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer rounded-xl p-2 transition-colors disabled:cursor-not-allowed disabled:opacity-30"
                                    title="Move Priority Up"
                                >
                                    <ArrowUp class="size-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="moveDown(pIdx)"
                                    :disabled="
                                        pIdx === sortedProviders.length - 1
                                    "
                                    class="bg-card/70 text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer rounded-xl p-2 transition-colors disabled:cursor-not-allowed disabled:opacity-30"
                                    title="Move Priority Down"
                                >
                                    <ArrowDown class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. API Keys (For Keyed Providers) -->
            <div
                class="cf-glass border-border/80 space-y-6 rounded-3xl border p-6 sm:p-8"
            >
                <div
                    class="border-border/70 flex items-center gap-2.5 border-b pb-4"
                >
                    <Key class="size-5 text-amber-400" />
                    <div>
                        <h3 class="text-foreground text-base font-bold">
                            {{
                                isRTL
                                    ? 'مفاتيح واجهات برمجة التطبيقات (API Keys)'
                                    : 'Provider API Keys (Optional)'
                            }}
                        </h3>
                        <p class="text-muted-foreground text-xs">
                            {{
                                isRTL
                                    ? 'أدخل مفاتيح API الخاصة بك للمزودات التي تتطلب مفتاحاً للحصول على صور بدقة 4K وتقييمات IMDb.'
                                    : 'Enter API keys for enhanced 4K backdrops and official IMDb/Rotten Tomatoes ratings.'
                            }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-foreground/85 text-xs font-bold"
                                >TMDb API Key</label
                            >
                            <button
                                type="button"
                                @click="
                                    testSingleProvider(
                                        'tmdb',
                                        form.tmdb_api_key,
                                    )
                                "
                                :disabled="testingProviderId === 'tmdb'"
                                class="text-primary cursor-pointer text-[10px] font-bold hover:underline"
                            >
                                {{ isRTL ? 'فحص المفتاح' : 'Test Key' }}
                            </button>
                        </div>
                        <input
                            v-model="form.tmdb_api_key"
                            type="password"
                            placeholder="Enter TMDb v3 API Read Key"
                            class="cf-field w-full rounded-xl px-4 py-2.5 text-xs"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-foreground/85 text-xs font-bold"
                                >OMDb API Key</label
                            >
                            <button
                                type="button"
                                @click="
                                    testSingleProvider(
                                        'omdb',
                                        form.omdb_api_key,
                                    )
                                "
                                :disabled="testingProviderId === 'omdb'"
                                class="text-primary cursor-pointer text-[10px] font-bold hover:underline"
                            >
                                {{ isRTL ? 'فحص المفتاح' : 'Test Key' }}
                            </button>
                        </div>
                        <input
                            v-model="form.omdb_api_key"
                            type="password"
                            placeholder="Enter OMDb API Key"
                            class="cf-field w-full rounded-xl px-4 py-2.5 text-xs"
                        />
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <div class="flex items-center justify-between">
                            <label class="text-foreground/85 text-xs font-bold"
                                >OpenSubtitles.com User Key</label
                            >
                            <button
                                type="button"
                                @click="
                                    testSingleProvider(
                                        'opensubtitles',
                                        form.opensubtitles_api_key,
                                    )
                                "
                                :disabled="
                                    testingProviderId === 'opensubtitles'
                                "
                                class="text-primary cursor-pointer text-[10px] font-bold hover:underline"
                            >
                                {{ isRTL ? 'فحص الخدمة' : 'Test Service' }}
                            </button>
                        </div>
                        <input
                            v-model="form.opensubtitles_api_key"
                            type="password"
                            placeholder="Enter OpenSubtitles REST API Key"
                            class="cf-field w-full rounded-xl px-4 py-2.5 text-xs"
                        />
                    </div>
                </div>
            </div>

            <!-- 3. Cinema Streaming & Transcoder Configuration -->
            <div
                class="cf-glass border-border/80 space-y-6 rounded-3xl border p-6 sm:p-8"
            >
                <div
                    class="border-border/70 flex items-center gap-2.5 border-b pb-4"
                >
                    <Cpu class="text-primary size-5" />
                    <div>
                        <h3 class="text-foreground text-base font-bold">
                            {{
                                isRTL
                                    ? 'محرك تشغيل وترميز الصوت (Cinema Audio & Codec Engine)'
                                    : 'Cinema Audio & Codec Engine'
                            }}
                        </h3>
                        <p class="text-muted-foreground text-xs">
                            {{
                                isRTL
                                    ? 'دعم تشغيل صيغ الصوت المتقدمة (E-AC-3, AC-3, DTS, TrueHD) بدون مشاكل انقطاع الصوت.'
                                    : 'Hardware & software audio decoding for Dolby Digital, DTS, and 5.1 multichannel audio.'
                            }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label class="text-foreground/85 text-xs font-bold">{{
                            isRTL
                                ? 'مسار FFmpeg للترميز الفوري'
                                : 'FFmpeg Binary Path'
                        }}</label>
                        <input
                            v-model="form.ffmpeg_path"
                            type="text"
                            placeholder="ffmpeg or C:\ffmpeg\bin\ffmpeg.exe"
                            class="cf-field w-full rounded-xl px-4 py-2.5 text-xs"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-foreground/85 text-xs font-bold">{{
                            isRTL
                                ? 'لغة الواجهة المفضلة'
                                : 'Default Interface Language'
                        }}</label>
                        <select
                            v-model="form.default_language"
                            class="cf-field bg-card w-full rounded-xl px-4 py-2.5 text-xs font-bold"
                        >
                            <option value="ar">
                                العربية (Arabic) - Default
                            </option>
                            <option value="en">English</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 4. Media Cache & Storage Management -->
            <div
                class="cf-glass border-border/80 space-y-6 rounded-3xl border p-6 sm:p-8"
            >
                <div
                    class="border-border/70 flex items-center justify-between border-b pb-4"
                >
                    <div class="flex items-center gap-2.5">
                        <HardDrive class="text-primary size-5" />
                        <div>
                            <h3 class="text-foreground text-base font-bold">
                                {{
                                    isRTL
                                        ? 'إدارة التخزين المؤقت للبث (Media Streams & Transcode Cache)'
                                        : 'Media Streams & Transcode Cache'
                                }}
                            </h3>
                            <p class="text-muted-foreground text-xs">
                                {{
                                    isRTL
                                        ? 'إدارة المساحة المستهلكة من قبل البث المباشر المحسن والملفات المؤقتة المحولة.'
                                        : 'Manage disk space occupied by server remux streams and transcode cache.'
                                }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="refreshCacheStats"
                        class="bg-card/70 text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer rounded-xl p-2 transition-all"
                        title="Refresh Cache Stats"
                    >
                        <RefreshCw class="size-4" />
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <!-- Stat: Cache Size -->
                    <div
                        class="border-border/80 bg-card/50 flex flex-col justify-between rounded-2xl border p-4 tabular-nums"
                    >
                        <span
                            class="text-muted-foreground text-xs font-semibold"
                            >{{
                                isRTL
                                    ? 'حجم الملفات المؤقتة'
                                    : 'Current Cache Size'
                            }}</span
                        >
                        <div
                            class="text-primary mt-2 font-mono text-2xl font-black"
                        >
                            {{ mediaCacheStats.formatted_size }}
                        </div>
                    </div>

                    <!-- Stat: Cached Items Count -->
                    <div
                        class="border-border/80 bg-card/50 flex flex-col justify-between rounded-2xl border p-4 tabular-nums"
                    >
                        <span
                            class="text-muted-foreground text-xs font-semibold"
                            >{{
                                isRTL
                                    ? 'عدد الملفات المؤقتة'
                                    : 'Cached Stream Files'
                            }}</span
                        >
                        <div
                            class="text-foreground mt-2 font-mono text-2xl font-black"
                        >
                            {{ mediaCacheStats.file_count }}
                        </div>
                    </div>

                    <!-- Action: Clean Cache Button -->
                    <div
                        class="border-border/80 bg-card/50 flex flex-col justify-between rounded-2xl border p-4"
                    >
                        <span
                            class="text-muted-foreground text-xs font-semibold"
                            >{{
                                isRTL ? 'تحرير مساحة القرص' : 'Storage Cleanup'
                            }}</span
                        >
                        <button
                            type="button"
                            @click="clearMediaCache"
                            :disabled="
                                isClearingCache ||
                                mediaCacheStats.file_count === 0
                            "
                            class="border-destructive/30 bg-destructive/15 text-destructive hover:bg-destructive mt-2 flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-xs font-bold shadow-sm transition-all hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <RefreshCw
                                v-if="isClearingCache"
                                class="size-4 animate-spin"
                            />
                            <Trash2 v-else class="size-4" />
                            <span>{{
                                isClearingCache
                                    ? isRTL
                                        ? 'جاري التنظيف...'
                                        : 'Clearing...'
                                    : isRTL
                                      ? 'تنظيف كاش الوسائط'
                                      : 'Clean Media Cache'
                            }}</span>
                        </button>
                    </div>
                </div>

                <div
                    v-if="cacheSuccessMessage"
                    class="flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/15 p-3 text-xs font-bold text-emerald-300"
                >
                    <CheckCircle2 class="size-4 shrink-0 text-emerald-400" />
                    <span>{{ cacheSuccessMessage }}</span>
                </div>
            </div>

            <!-- 5. Database Backup & Disaster Recovery -->
            <div
                class="cf-glass border-border/80 space-y-6 rounded-3xl border p-6 sm:p-8"
            >
                <div
                    class="border-border/70 flex flex-wrap items-center justify-between gap-4 border-b pb-4"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="border-primary/30 bg-primary/10 text-primary flex size-10 items-center justify-center rounded-2xl border"
                        >
                            <Database class="size-5" />
                        </div>
                        <div>
                            <h3
                                class="text-foreground flex items-center gap-2 text-base font-bold"
                            >
                                <span>{{
                                    isRTL
                                        ? 'النسخ الاحتياطي واستعادة قاعدة البيانات'
                                        : 'Database Backup & Disaster Recovery'
                                }}</span>
                                <span
                                    class="border-primary/30 bg-primary/15 text-primary rounded-full border px-2 py-0.5 text-[10px] font-black uppercase"
                                >
                                    {{ isRTL ? 'مباشر وآمن' : 'Live & Safe' }}
                                </span>
                            </h3>
                            <p class="text-muted-foreground mt-0.5 text-xs">
                                {{
                                    isRTL
                                        ? 'حفظ واستعادة فهارس المكتبة والأفلام والمسلسلات والترجمات برابط تنزيل مباشر أو لقطات محلية آمنة.'
                                        : 'Export and restore your entire library database, metadata, posters, subtitles, and settings with 1-click.'
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- Quick Top Actions -->
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="downloadDatabaseBackup"
                            class="bg-primary text-primary-foreground shadow-primary/20 flex cursor-pointer items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-black shadow-lg transition-all hover:brightness-105"
                            :title="
                                isRTL
                                    ? 'تنزيل نسخة احتياطية مباشرة لجهازك'
                                    : 'Download JSON Backup to your computer'
                            "
                        >
                            <DownloadCloud class="size-4" />
                            <span>{{
                                isRTL ? 'تنزيل نسخة JSON' : 'Download Backup'
                            }}</span>
                        </button>

                        <button
                            type="button"
                            @click="createServerSnapshot"
                            :disabled="isCreatingBackup"
                            class="cf-glass border-border/80 text-foreground hover:border-primary/40 flex cursor-pointer items-center gap-1.5 rounded-xl border px-4 py-2 text-xs font-bold transition-all disabled:opacity-50"
                            :title="
                                isRTL
                                    ? 'أخذ لقطة سريعة وحفظها على الخادم'
                                    : 'Create instant snapshot on server disk'
                            "
                        >
                            <RefreshCw
                                v-if="isCreatingBackup"
                                class="text-primary size-4 animate-spin"
                            />
                            <Save v-else class="text-primary size-4" />
                            <span>{{
                                isCreatingBackup
                                    ? isRTL
                                        ? 'جاري الحفظ...'
                                        : 'Saving...'
                                    : isRTL
                                      ? 'حفظ لقطة على الخادم'
                                      : 'Save Snapshot'
                            }}</span>
                        </button>
                    </div>
                </div>

                <!-- Database Metrics Bar -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div
                        class="border-border/80 bg-card/50 rounded-2xl border p-3.5 tabular-nums"
                    >
                        <span
                            class="text-muted-foreground block text-[10px] font-bold uppercase"
                            >{{
                                isRTL ? 'الأفلام المفهرسة' : 'Indexed Movies'
                            }}</span
                        >
                        <div
                            class="text-foreground mt-1 font-mono text-xl font-black"
                        >
                            {{ database_stats?.media_items || 0 }}
                        </div>
                    </div>
                    <div
                        class="border-border/80 bg-card/50 rounded-2xl border p-3.5 tabular-nums"
                    >
                        <span
                            class="text-muted-foreground block text-[10px] font-bold uppercase"
                            >{{
                                isRTL
                                    ? 'المسلسلات والحلقات'
                                    : 'Series & Episodes'
                            }}</span
                        >
                        <div
                            class="text-primary mt-1 font-mono text-xl font-black"
                        >
                            {{ database_stats?.series || 0 }}
                            <span
                                class="text-muted-foreground text-xs font-normal"
                                >({{ database_stats?.episodes || 0 }} ep)</span
                            >
                        </div>
                    </div>
                    <div
                        class="border-border/80 bg-card/50 rounded-2xl border p-3.5 tabular-nums"
                    >
                        <span
                            class="text-muted-foreground block text-[10px] font-bold uppercase"
                            >{{
                                isRTL ? 'ملفات الترجمة' : 'Cataloged Subtitles'
                            }}</span
                        >
                        <div
                            class="text-foreground mt-1 font-mono text-xl font-black"
                        >
                            {{ database_stats?.subtitles || 0 }}
                        </div>
                    </div>
                    <div
                        class="border-border/80 bg-card/50 rounded-2xl border p-3.5 tabular-nums"
                    >
                        <span
                            class="text-muted-foreground block text-[10px] font-bold uppercase"
                            >{{
                                isRTL ? 'اللقطات المحفوظة' : 'Local Snapshots'
                            }}</span
                        >
                        <div
                            class="mt-1 font-mono text-xl font-black text-emerald-400"
                        >
                            {{ localBackups.length }}
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
                <div
                    v-if="backupSuccessMessage"
                    class="flex items-center justify-between rounded-2xl border border-emerald-500/30 bg-emerald-500/15 p-3.5 text-xs font-bold text-emerald-300"
                >
                    <div class="flex items-center gap-2">
                        <CheckCircle2
                            class="size-4 shrink-0 text-emerald-400"
                        />
                        <span>{{ backupSuccessMessage }}</span>
                    </div>
                    <button
                        type="button"
                        @click="backupSuccessMessage = null"
                        class="cursor-pointer text-emerald-400 hover:text-emerald-200"
                    >
                        ✕
                    </button>
                </div>

                <div
                    v-if="backupErrorMessage"
                    class="border-destructive/30 bg-destructive/15 text-destructive flex items-center justify-between rounded-2xl border p-3.5 text-xs font-bold"
                >
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="size-4 shrink-0" />
                        <span>{{ backupErrorMessage }}</span>
                    </div>
                    <button
                        type="button"
                        @click="backupErrorMessage = null"
                        class="cursor-pointer opacity-80 hover:opacity-100"
                    >
                        ✕
                    </button>
                </div>

                <!-- Selective Restore Configuration Panel -->
                <div
                    class="border-border/80 bg-card/40 space-y-4 rounded-2xl border p-5"
                >
                    <div
                        class="border-border/70 flex flex-wrap items-center justify-between gap-3 border-b pb-3"
                    >
                        <div>
                            <h4
                                class="text-foreground flex items-center gap-2 text-sm font-black"
                            >
                                <span>{{
                                    isRTL
                                        ? 'تخصيص أقسام الاستعادة (Selective Restore Sections)'
                                        : 'Selective Restore Sections'
                                }}</span>
                                <span
                                    class="rounded-full border px-2 py-0.5 text-[10px] font-black tabular-nums"
                                    :class="
                                        selectedSections.length > 0
                                            ? 'border-primary/30 bg-primary/15 text-primary'
                                            : 'border-destructive/30 bg-destructive/15 text-destructive'
                                    "
                                >
                                    {{ selectedSections.length }} /
                                    {{ availableSections.length }}
                                    {{ isRTL ? 'محدد' : 'selected' }}
                                </span>
                            </h4>
                            <p class="text-muted-foreground mt-0.5 text-xs">
                                {{
                                    isRTL
                                        ? 'اختر الأقسام التي ترغب في استعادتها تحديداً (مثل الأفلام فقط، أو المسلسلات فقط، أو الترجمات). الأقسام غير المحددة لن تُحذف ولن تتأثر إطلاقاً.'
                                        : 'Select precisely which sections to restore. Unselected sections will remain completely safe and untouched.'
                                }}
                            </p>
                        </div>

                        <!-- Quick Select / Deselect All -->
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="selectAllSections"
                                class="bg-card/70 text-foreground/85 hover:bg-muted flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all"
                            >
                                <CheckCheck class="text-primary size-3.5" />
                                <span>{{
                                    isRTL ? 'تحديد الكل' : 'Select All'
                                }}</span>
                            </button>
                            <button
                                type="button"
                                @click="clearAllSections"
                                class="bg-card/70 text-muted-foreground hover:bg-muted hover:text-foreground flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all"
                            >
                                <X class="size-3.5" />
                                <span>{{
                                    isRTL ? 'إلغاء التحديد' : 'Clear All'
                                }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Section Cards Grid -->
                    <div
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5"
                    >
                        <div
                            v-for="section in availableSections"
                            :key="section.id"
                            @click="toggleSection(section.id)"
                            class="group relative flex cursor-pointer flex-col justify-between rounded-xl border p-3.5 transition-all select-none"
                            :class="
                                selectedSections.includes(section.id)
                                    ? 'border-primary/45 bg-primary/12 text-foreground ring-primary/30 shadow-sm ring-1'
                                    : 'border-border/60 bg-card/30 text-muted-foreground hover:border-border hover:text-foreground'
                            "
                        >
                            <div>
                                <div
                                    class="mb-2 flex items-center justify-between gap-2"
                                >
                                    <div
                                        class="flex size-7 items-center justify-center rounded-lg"
                                        :class="
                                            selectedSections.includes(
                                                section.id,
                                            )
                                                ? 'bg-primary/20 text-primary'
                                                : 'bg-muted/60 text-muted-foreground'
                                        "
                                    >
                                        <Film
                                            v-if="section.id === 'movies'"
                                            class="size-4"
                                        />
                                        <Tv
                                            v-else-if="section.id === 'series'"
                                            class="size-4"
                                        />
                                        <FileText
                                            v-else-if="
                                                section.id === 'subtitles'
                                            "
                                            class="size-4"
                                        />
                                        <SettingsIcon
                                            v-else-if="
                                                section.id === 'settings'
                                            "
                                            class="size-4"
                                        />
                                        <Clock v-else class="size-4" />
                                    </div>

                                    <div
                                        class="flex size-5 items-center justify-center rounded-md transition-all"
                                        :class="
                                            selectedSections.includes(
                                                section.id,
                                            )
                                                ? 'bg-primary text-primary-foreground font-black'
                                                : 'border-border border'
                                        "
                                    >
                                        <Check
                                            v-if="
                                                selectedSections.includes(
                                                    section.id,
                                                )
                                            "
                                            class="size-3.5"
                                        />
                                    </div>
                                </div>

                                <h5
                                    class="text-xs font-black"
                                    :class="
                                        selectedSections.includes(section.id)
                                            ? 'text-foreground'
                                            : 'text-foreground/80'
                                    "
                                >
                                    {{
                                        isRTL
                                            ? section.labelAr
                                            : section.labelEn
                                    }}
                                </h5>
                                <p
                                    class="mt-1 text-[10px] leading-snug text-slate-400"
                                >
                                    {{
                                        isRTL ? section.descAr : section.descEn
                                    }}
                                </p>
                            </div>

                            <div
                                v-if="section.countKey && database_stats"
                                class="mt-2 border-t border-white/5 pt-2 font-mono text-[10px] font-bold text-cyan-400/80"
                            >
                                {{
                                    database_stats[
                                        section.countKey as keyof typeof database_stats
                                    ] ?? 0
                                }}
                                {{ isRTL ? 'عنصر حالي' : 'items' }}
                            </div>
                        </div>
                    </div>

                    <!-- Mode Selector & Safety Guarantee Banner -->
                    <div
                        class="flex flex-col items-stretch justify-between gap-4 pt-2 md:flex-row md:items-center"
                    >
                        <!-- Mode Switcher -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="text-foreground/85 shrink-0 text-xs font-bold"
                            >
                                {{
                                    isRTL
                                        ? 'طريقة استعادة الأقسام المحددة:'
                                        : 'Restore mode for selected sections:'
                                }}
                            </span>
                            <div
                                class="border-border/80 bg-card/70 inline-flex rounded-xl border p-1"
                            >
                                <button
                                    type="button"
                                    @click="restoreMode = 'overwrite'"
                                    class="cursor-pointer rounded-lg px-3 py-1 text-xs font-bold transition-all"
                                    :class="
                                        restoreMode === 'overwrite'
                                            ? 'bg-primary text-primary-foreground font-black shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                >
                                    {{
                                        isRTL
                                            ? 'استبدال كامل (Clean Overwrite)'
                                            : 'Clean Overwrite'
                                    }}
                                </button>
                                <button
                                    type="button"
                                    @click="restoreMode = 'merge'"
                                    class="cursor-pointer rounded-lg px-3 py-1 text-xs font-bold transition-all"
                                    :class="
                                        restoreMode === 'merge'
                                            ? 'bg-primary text-primary-foreground font-black shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                >
                                    {{
                                        isRTL
                                            ? 'دمج وتحديث (Merge)'
                                            : 'Merge & Update'
                                    }}
                                </button>
                            </div>
                        </div>

                        <!-- Safety Notice -->
                        <div
                            class="text-muted-foreground flex items-center gap-2 text-[11px]"
                        >
                            <Shield class="size-4 shrink-0 text-emerald-400" />
                            <span>{{
                                isRTL
                                    ? 'يتم حفظ لقطة أمان احتياطية تلقائياً قبل أي عملية استعادة.'
                                    : 'Automatic safety snapshot is created before every restore.'
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- 2 Main Cards: Upload Restore & Snapshots List -->
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <!-- Card 1: Restore from File -->
                    <div
                        class="border-border/80 bg-card/40 flex flex-col justify-between space-y-4 rounded-2xl border p-5"
                    >
                        <div class="space-y-3">
                            <div
                                class="text-foreground flex items-center gap-2 text-sm font-bold"
                            >
                                <UploadCloud class="text-primary size-4" />
                                <span>{{
                                    isRTL
                                        ? 'استعادة قاعدة البيانات من ملف خارجي'
                                        : 'Restore Database from File'
                                }}</span>
                            </div>
                            <p
                                class="text-muted-foreground text-xs leading-relaxed"
                            >
                                {{
                                    isRTL
                                        ? 'قم برفع ملف نسخة احتياطية بصيغة (.json) أو (.sqlite / .db). سيتم أخذ نسخة أمان احتياطية تلقائياً قبل البدء بالاستعادة.'
                                        : 'Upload a (.json) or (.sqlite / .db) backup file to restore library catalog and settings. An automatic safety backup is saved first.'
                                }}
                            </p>

                            <!-- File Selector Dropzone -->
                            <div
                                class="border-border/80 hover:border-primary/45 rounded-2xl border-2 border-dashed p-4 text-center transition-colors"
                            >
                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    accept=".json,.sqlite,.db"
                                    class="hidden"
                                    @change="onFileSelected"
                                    id="backup-file-input"
                                />
                                <label
                                    for="backup-file-input"
                                    class="flex cursor-pointer flex-col items-center gap-2"
                                >
                                    <FolderArchive
                                        class="text-primary size-7"
                                    />
                                    <div
                                        class="text-foreground text-xs font-bold"
                                    >
                                        {{
                                            selectedFile
                                                ? selectedFile.name
                                                : isRTL
                                                  ? 'اختر ملف النسخة الاحتياطية (.json / .sqlite)'
                                                  : 'Choose backup file (.json, .sqlite, .db)'
                                        }}
                                    </div>
                                    <span
                                        class="text-muted-foreground text-[10px] tabular-nums"
                                    >
                                        {{
                                            selectedFile
                                                ? formatBytes(selectedFile.size)
                                                : isRTL
                                                  ? 'انقر لاختيار ملف من جهازك'
                                                  : 'Click to browse files'
                                        }}
                                    </span>
                                </label>
                            </div>

                            <!-- Mode Selector -->
                            <div class="space-y-1.5 pt-1">
                                <label
                                    class="text-foreground/85 block text-xs font-bold"
                                >
                                    {{
                                        isRTL
                                            ? 'طريقة الاستعادة:'
                                            : 'Restore Mode:'
                                    }}
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        @click="restoreMode = 'overwrite'"
                                        class="cursor-pointer rounded-xl border p-2.5 text-start text-xs font-bold transition-all"
                                        :class="
                                            restoreMode === 'overwrite'
                                                ? 'border-primary/45 bg-primary/15 text-primary ring-primary/30 ring-1'
                                                : 'border-border/80 bg-card/50 text-muted-foreground hover:text-foreground'
                                        "
                                    >
                                        <div class="font-black">
                                            {{
                                                isRTL
                                                    ? 'استبدال كامل (موصى به)'
                                                    : 'Clean Overwrite'
                                            }}
                                        </div>
                                        <span
                                            class="mt-0.5 block text-[10px] opacity-75"
                                            >{{
                                                isRTL
                                                    ? 'مطابقة النسخة تماماً'
                                                    : 'Recommended for full restores'
                                            }}</span
                                        >
                                    </button>

                                    <button
                                        type="button"
                                        @click="restoreMode = 'merge'"
                                        class="cursor-pointer rounded-xl border p-2.5 text-start text-xs font-bold transition-all"
                                        :class="
                                            restoreMode === 'merge'
                                                ? 'border-primary/45 bg-primary/15 text-primary ring-primary/30 ring-1'
                                                : 'border-border/80 bg-card/50 text-muted-foreground hover:text-foreground'
                                        "
                                    >
                                        <div class="font-black">
                                            {{
                                                isRTL
                                                    ? 'دمج وتحديث'
                                                    : 'Merge & Update'
                                            }}
                                        </div>
                                        <span
                                            class="mt-0.5 block text-[10px] opacity-75"
                                            >{{
                                                isRTL
                                                    ? 'تحديث وإضافة دون حذف'
                                                    : 'Keep existing records'
                                            }}</span
                                        >
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="promptRestoreUpload"
                            :disabled="
                                !selectedFile ||
                                isRestoring ||
                                selectedSections.length === 0
                            "
                            class="bg-primary text-primary-foreground shadow-primary/20 flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-xs font-black shadow-lg transition-all hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <RefreshCw
                                v-if="isRestoring"
                                class="size-4 animate-spin"
                            />
                            <RotateCcw v-else class="size-4" />
                            <span>{{
                                isRestoring
                                    ? isRTL
                                        ? 'جاري استعادة الأقسام المحددة...'
                                        : 'Restoring Selected Sections...'
                                    : selectedSections.length === 0
                                      ? isRTL
                                          ? 'حدد قسماً واحداً على الأقل للاستعادة'
                                          : 'Select at least 1 section above'
                                      : isRTL
                                        ? `استعادة ${selectedSections.length} أقسام محددة من الملف`
                                        : `Restore ${selectedSections.length} Selected Section(s)`
                            }}</span>
                        </button>
                    </div>

                    <!-- Card 2: Local Server Snapshots List -->
                    <div
                        class="border-border/80 bg-card/40 flex flex-col justify-between space-y-3 rounded-2xl border p-5"
                    >
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <div
                                    class="text-foreground flex items-center gap-2 text-sm font-bold"
                                >
                                    <Clock class="size-4 text-emerald-400" />
                                    <span>{{
                                        isRTL
                                            ? 'اللقطات المحفوظة محلياً على الخادم'
                                            : 'Saved Local Snapshots'
                                    }}</span>
                                </div>
                                <button
                                    type="button"
                                    @click="fetchBackupsList"
                                    class="text-primary flex cursor-pointer items-center gap-1 text-[11px] font-bold hover:underline"
                                >
                                    <RefreshCw class="size-3" />
                                    <span>{{
                                        isRTL ? 'تحديث القائمة' : 'Refresh'
                                    }}</span>
                                </button>
                            </div>

                            <p
                                class="text-muted-foreground text-xs leading-relaxed"
                            >
                                {{
                                    isRTL
                                        ? 'نسخ احتياطية مخزنة على القرص المحلي للاسترجاع السريع في أي وقت بنقرة واحدة.'
                                        : 'Instant rollback snapshots stored in storage/app/backups for quick recovery.'
                                }}
                            </p>

                            <!-- Backups Table / Scrollable List -->
                            <div
                                class="custom-scrollbar max-h-56 space-y-2 overflow-y-auto pe-1"
                            >
                                <div
                                    v-for="backup in localBackups"
                                    :key="backup.filename"
                                    class="border-border/70 bg-card/60 hover:border-primary/40 flex items-center justify-between gap-3 rounded-xl border p-3 transition-all"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="py-0.2 rounded px-1.5 text-[9px] font-black uppercase"
                                                :class="
                                                    backup.extension === 'json'
                                                        ? 'border-primary/30 bg-primary/15 text-primary border'
                                                        : 'border border-purple-500/30 bg-purple-500/20 text-purple-300'
                                                "
                                            >
                                                {{ backup.extension }}
                                            </span>
                                            <span
                                                class="text-foreground block truncate font-mono text-xs font-bold"
                                                :title="backup.filename"
                                            >
                                                {{ backup.filename }}
                                            </span>
                                        </div>
                                        <div
                                            class="text-muted-foreground mt-1 flex items-center gap-2 font-mono text-[10px] tabular-nums"
                                        >
                                            <span>{{
                                                backup.size_formatted
                                            }}</span>
                                            <span>•</span>
                                            <span>{{ backup.created_at }}</span>
                                            <span
                                                v-if="
                                                    backup.summary?.media_items
                                                "
                                                class="text-primary hidden font-bold sm:inline"
                                            >
                                                ({{
                                                    backup.summary.media_items
                                                }}
                                                movies)
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        class="flex shrink-0 items-center gap-1.5"
                                    >
                                        <!-- Restore Button -->
                                        <button
                                            type="button"
                                            @click="promptRestoreLocal(backup)"
                                            :disabled="
                                                isRestoring ||
                                                selectedSections.length === 0
                                            "
                                            class="bg-primary/15 text-primary hover:bg-primary/25 cursor-pointer rounded-lg p-1.5 transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                            :title="
                                                isRTL
                                                    ? `استعادة الأقسام المحددة (${selectedSections.length}) من هذه اللقطة`
                                                    : `Restore ${selectedSections.length} selected sections from this snapshot`
                                            "
                                        >
                                            <RotateCcw class="size-3.5" />
                                        </button>

                                        <!-- Download Button -->
                                        <a
                                            :href="`/api/database/backups/download/${encodeURIComponent(backup.filename)}`"
                                            class="bg-card text-foreground/85 hover:bg-muted hover:text-foreground cursor-pointer rounded-lg p-1.5 transition-colors"
                                            :title="
                                                isRTL
                                                    ? 'تنزيل إلى جهازك'
                                                    : 'Download to your computer'
                                            "
                                            download
                                        >
                                            <DownloadCloud class="size-3.5" />
                                        </a>

                                        <!-- Delete Button -->
                                        <button
                                            type="button"
                                            @click="promptDeleteBackup(backup)"
                                            class="bg-destructive/15 text-destructive hover:bg-destructive/25 cursor-pointer rounded-lg p-1.5 transition-colors"
                                            :title="
                                                isRTL
                                                    ? 'حذف اللقطة'
                                                    : 'Delete snapshot'
                                            "
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-if="localBackups.length === 0"
                                    class="text-muted-foreground py-8 text-center text-xs italic"
                                >
                                    {{
                                        isRTL
                                            ? 'لا توجد لقطات محلية محفوظة حالياً. انقر "حفظ لقطة على الخادم" لإنشاء أول لقطة.'
                                            : 'No local snapshots saved yet. Click "Save Snapshot" to create your first backup.'
                                    }}
                                </div>
                            </div>
                        </div>

                        <!-- Footer Tip -->
                        <div
                            class="border-primary/20 bg-primary/5 text-primary/90 flex items-center gap-2 rounded-xl border p-2.5 text-[11px]"
                        >
                            <Shield class="text-primary size-3.5 shrink-0" />
                            <span>{{
                                isRTL
                                    ? 'يتم حفظ اللقطات بأمان في مسار storage/app/backups على خادمك.'
                                    : 'Snapshots are securely preserved in storage/app/backups on your host.'
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Global Action Confirmation Dialog -->
        <ConfirmModal
            :show="confirmModal.show"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :confirm-text="confirmModal.confirmText"
            :type="confirmModal.type"
            @confirm="triggerConfirmAction"
            @close="confirmModal.show = false"
        />
    </AppLayout>
</template>
