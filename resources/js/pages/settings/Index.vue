<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    Settings as SettingsIcon, Save, Key, Globe, Sparkles,
    CheckCircle2, AlertCircle, ArrowUp, ArrowDown, ShieldCheck,
    Layers, Cpu, Database, HardDrive, RefreshCw, Zap, Check, X, Trash2,
    DownloadCloud, UploadCloud, RotateCcw, FileText, FileJson, Clock,
    AlertTriangle, Shield, CheckCheck, FolderArchive, Film, Tv, CheckSquare, Square
} from 'lucide-vue-next';
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
        : ['tvmaze', 'anilist', 'wikipedia', 'local_nfo', 'tmdb', 'omdb']
);

const sortedProviders = ref(
    [...allProviders.value].sort((a, b) => {
        const idxA = preferredOrder.value.indexOf(a.id);
        const idxB = preferredOrder.value.indexOf(b.id);
        return (idxA === -1 ? 99 : idxA) - (idxB === -1 ? 99 : idxB);
    })
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
const toastMessage = ref('');

// Test status per provider
const testingProviderId = ref<string | null>(null);
const testResults = ref<Record<string, { success: boolean; message: string; latency_ms?: number }>>({});

const testSingleProvider = async (providerId: string, customKey?: string) => {
    testingProviderId.value = providerId;
    try {
        const res = await fetch('/api/settings/test-provider', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                provider: providerId,
                key: customKey || (form.value as any)[`${providerId}_api_key`] || undefined,
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
    toastMessage.value = '';

    try {
        const res = await fetch('/api/settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                ...form.value,
                preferred_providers: preferredOrder.value,
            }),
        });

        if (res.ok) {
            setLocale(form.value.default_language);
            toastMessage.value = isRTL.value ? 'تم حفظ الإعدادات وترتيب المزودين بنجاح!' : 'Settings & provider priorities saved successfully!';
        }
    } finally {
        isSaving.value = false;
    }
};

// Media Stream Cache Management
const mediaCacheStats = ref(props.media_cache || {
    size_bytes: 0,
    formatted_size: '0 B',
    file_count: 0,
});
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
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
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

const selectedSections = ref<string[]>(['movies', 'series', 'subtitles', 'settings', 'watch_history']);

const toggleSection = (id: string) => {
    if (selectedSections.value.includes(id)) {
        selectedSections.value = selectedSections.value.filter(s => s !== id);
    } else {
        selectedSections.value.push(id);
    }
};

const selectAllSections = () => {
    selectedSections.value = availableSections.map(s => s.id);
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
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({ format: 'both' }),
        });

        const data = await res.json();
        if (res.ok && data.success) {
            backupSuccessMessage.value = isRTL.value
                ? `تم إنشاء لقطة احتياطية كاملة بنجاح (${data.name})!`
                : `Local server snapshot created successfully (${data.name})!`;
            await fetchBackupsList();
            setTimeout(() => { backupSuccessMessage.value = null; }, 6000);
        } else {
            backupErrorMessage.value = data.message || 'Failed to create backup.';
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

    const sectionLabels = selectedSections.value.map(id => {
        const s = availableSections.find(sec => sec.id === id);
        return isRTL.value ? s?.labelAr : s?.labelEn;
    }).join(', ');

    confirmModal.value = {
        show: true,
        title: isRTL.value ? 'تأكيد استعادة الأقسام المحددة' : 'Confirm Selective Restore',
        message: isRTL.value
            ? `سيتم استعادة الأقسام المختارة (${sectionLabels}) من الملف "${selectedFile.value.name}" بنمط "${restoreMode.value === 'overwrite' ? 'استبدال كامل للأقسام المختارة' : 'دمج وتحديث'}". الأقسام غير المختارة ستبقى آمنة وغير ممسوسة. سيتم أخذ لقطة أمان احتياطية تلقائياً قبل البدء. هل تريد المتابعة؟`
            : `Restore selected sections (${sectionLabels}) from "${selectedFile.value.name}" using "${restoreMode.value}" mode? Unselected sections will remain completely untouched. Continue?`,
        confirmText: isRTL.value ? 'تأكيد الاستعادة المحددة' : 'Proceed with Restore',
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
        selectedSections.value.forEach(sec => formData.append('sections[]', sec));

        const res = await fetch('/api/database/restore/upload', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: formData,
        });

        const data = await res.json();
        if (res.ok && data.success) {
            let details = '';
            if (data.restored_counts) {
                const parts = [];
                if (data.restored_counts.media_items !== undefined) parts.push(`${data.restored_counts.media_items} movies`);
                if (data.restored_counts.series !== undefined) parts.push(`${data.restored_counts.series} series`);
                if (data.restored_counts.subtitles !== undefined) parts.push(`${data.restored_counts.subtitles} subtitles`);
                if (parts.length > 0) details = ` (${parts.join(', ')})`;
            }
            backupSuccessMessage.value = isRTL.value
                ? `تمت استعادة الأقسام المحددة بنجاح!${details}`
                : `Selected sections restored successfully!${details}`;
            selectedFile.value = null;
            if (fileInputRef.value) fileInputRef.value.value = '';
            await fetchBackupsList();
            setTimeout(() => { backupSuccessMessage.value = null; }, 10000);
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

    const sectionLabels = selectedSections.value.map(id => {
        const s = availableSections.find(sec => sec.id === id);
        return isRTL.value ? s?.labelAr : s?.labelEn;
    }).join(', ');

    confirmModal.value = {
        show: true,
        title: isRTL.value ? 'استعادة الأقسام من لقطة محلية' : 'Restore Selected Sections from Snapshot',
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
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
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
                if (data.restored_counts.media_items !== undefined) parts.push(`${data.restored_counts.media_items} movies`);
                if (data.restored_counts.series !== undefined) parts.push(`${data.restored_counts.series} series`);
                if (data.restored_counts.subtitles !== undefined) parts.push(`${data.restored_counts.subtitles} subtitles`);
                if (parts.length > 0) details = ` (${parts.join(', ')})`;
            }
            backupSuccessMessage.value = isRTL.value
                ? `تمت استعادة الأقسام المحددة بنجاح من اللقطة ${filename}!${details}`
                : `Successfully restored selected sections from ${filename}!${details}`;
            await fetchBackupsList();
            setTimeout(() => { backupSuccessMessage.value = null; }, 10000);
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
                const res = await fetch(`/api/database/backups/${encodeURIComponent(backup.filename)}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                    },
                });
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
    <Head :title="t('settings_view.title')" />

    <AppLayout v-slot="{ play }">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                        <SettingsIcon class="w-5 h-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                            {{ t('settings_view.title') }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                            {{ t('settings_view.subtitle') }}
                        </p>
                    </div>
                </div>

                <button
                    @click="saveSettings"
                    :disabled="isSaving"
                    class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-xs flex items-center gap-2 active:scale-95 transition-all cursor-pointer shadow-lg shadow-cyan-500/25"
                >
                    <RefreshCw v-if="isSaving" class="w-4 h-4 animate-spin" />
                    <Save v-else class="w-4 h-4" />
                    <span>{{ t('settings_view.save') }}</span>
                </button>
            </div>
        </div>

        <!-- Toast Notice -->
        <div v-if="toastMessage" class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 shrink-0" />
                <span>{{ toastMessage }}</span>
            </div>
            <button @click="toastMessage = ''" class="cursor-pointer text-emerald-400 hover:text-emerald-300">✕</button>
        </div>

        <div class="space-y-8">
            <!-- 1. Metadata Providers Customization, Priority Ordering & Live Testing -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6">
                <div class="flex items-center justify-between flex-wrap gap-4 pb-4 border-b border-white/10">
                    <div class="flex items-center gap-2.5">
                        <Layers class="w-5 h-5 text-cyan-400" />
                        <div>
                            <h3 class="font-bold text-base text-white">
                                {{ isRTL ? 'أولويات مزودي البيانات والأغلفة وفحص الاتصال (Provider Priority & Test)' : 'Metadata Providers Priority Chain & Live Verification' }}
                            </h3>
                            <p class="text-xs text-slate-400">
                                {{ isRTL ? 'رتب أولوية البحث واضغط زر الفحص للتأكد من عمل المزود. المزودات المجانية تعمل مباشرة بدون أي مفتاح.' : 'Reorder priority chain and click test to verify provider health in real-time.' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Provider Priority Cards -->
                <div class="space-y-3">
                    <div
                        v-for="(prov, pIdx) in sortedProviders"
                        :key="prov.id"
                        class="p-4 rounded-2xl bg-white/5 border border-white/10 hover:border-cyan-500/40 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4 group"
                    >
                        <div class="flex items-center gap-3.5 min-w-0">
                            <!-- Priority Rank Badge -->
                            <div class="w-7 h-7 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 flex items-center justify-center font-black text-xs shrink-0">
                                #{{ pIdx + 1 }}
                            </div>

                            <div class="min-w-0 space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="font-black text-xs text-white">{{ prov.name }}</h4>
                                    <span
                                        class="cinema-badge text-[9px] font-bold"
                                        :class="prov.badgeColor === 'emerald'
                                            ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                                            : prov.badgeColor === 'cyan'
                                            ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30'
                                            : 'bg-amber-500/20 text-amber-300 border-amber-500/30'"
                                    >
                                        {{ prov.badge }}
                                    </span>

                                    <!-- Test Feedback Status Badge -->
                                    <span
                                        v-if="testResults[prov.id]"
                                        class="cinema-badge text-[9px] font-mono font-bold flex items-center gap-1"
                                        :class="testResults[prov.id].success ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border-rose-500/30'"
                                    >
                                        <Check v-if="testResults[prov.id].success" class="w-3 h-3" />
                                        <X v-else class="w-3 h-3" />
                                        <span>{{ testResults[prov.id].message }}</span>
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-400 line-clamp-1">
                                    {{ isRTL ? prov.descAr : prov.desc }}
                                </p>
                            </div>
                        </div>

                        <!-- Action & Move Buttons -->
                        <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                            <button
                                v-if="prov.id !== 'local_nfo'"
                                @click="testSingleProvider(prov.id)"
                                :disabled="testingProviderId === prov.id"
                                class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-cyan-500/20 text-slate-300 hover:text-cyan-300 border border-white/10 text-[11px] font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                            >
                                <RefreshCw v-if="testingProviderId === prov.id" class="w-3 h-3 animate-spin text-cyan-400" />
                                <Zap v-else class="w-3 h-3 text-cyan-400" />
                                <span>{{ isRTL ? 'فحص الاتصال' : 'Test Connection' }}</span>
                            </button>

                            <div class="flex items-center gap-1">
                                <button
                                    @click="moveUp(pIdx)"
                                    :disabled="pIdx === 0"
                                    class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer"
                                    title="Move Priority Up"
                                >
                                    <ArrowUp class="w-4 h-4" />
                                </button>
                                <button
                                    @click="moveDown(pIdx)"
                                    :disabled="pIdx === sortedProviders.length - 1"
                                    class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer"
                                    title="Move Priority Down"
                                >
                                    <ArrowDown class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. API Keys (For Keyed Providers) -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6">
                <div class="flex items-center gap-2.5 pb-4 border-b border-white/10">
                    <Key class="w-5 h-5 text-amber-400" />
                    <div>
                        <h3 class="font-bold text-base text-white">
                            {{ isRTL ? 'مفاتيح واجهات برمجة التطبيقات (API Keys)' : 'Provider API Keys (Optional)' }}
                        </h3>
                        <p class="text-xs text-slate-400">
                            {{ isRTL ? 'أدخل مفاتيح API الخاصة بك للمزودات التي تتطلب مفتاحاً للحصول على صور بدقة 4K وتقييمات IMDb.' : 'Enter API keys for enhanced 4K backdrops and official IMDb/Rotten Tomatoes ratings.' }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-300">TMDb API Key</label>
                            <button
                                @click="testSingleProvider('tmdb', form.tmdb_api_key)"
                                :disabled="testingProviderId === 'tmdb'"
                                class="text-[10px] text-cyan-400 hover:underline font-bold cursor-pointer"
                            >
                                {{ isRTL ? 'فحص المفتاح' : 'Test Key' }}
                            </button>
                        </div>
                        <input
                            v-model="form.tmdb_api_key"
                            type="password"
                            placeholder="Enter TMDb v3 API Read Key"
                            class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-300">OMDb API Key</label>
                            <button
                                @click="testSingleProvider('omdb', form.omdb_api_key)"
                                :disabled="testingProviderId === 'omdb'"
                                class="text-[10px] text-cyan-400 hover:underline font-bold cursor-pointer"
                            >
                                {{ isRTL ? 'فحص المفتاح' : 'Test Key' }}
                            </button>
                        </div>
                        <input
                            v-model="form.omdb_api_key"
                            type="password"
                            placeholder="Enter OMDb API Key"
                            class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                        />
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-300">OpenSubtitles.com User Key</label>
                            <button
                                @click="testSingleProvider('opensubtitles', form.opensubtitles_api_key)"
                                :disabled="testingProviderId === 'opensubtitles'"
                                class="text-[10px] text-cyan-400 hover:underline font-bold cursor-pointer"
                            >
                                {{ isRTL ? 'فحص الخدمة' : 'Test Service' }}
                            </button>
                        </div>
                        <input
                            v-model="form.opensubtitles_api_key"
                            type="password"
                            placeholder="Enter OpenSubtitles REST API Key"
                            class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                        />
                    </div>
                </div>
            </div>

            <!-- 3. Cinema Streaming & Transcoder Configuration -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6">
                <div class="flex items-center gap-2.5 pb-4 border-b border-white/10">
                    <Cpu class="w-5 h-5 text-cyan-400" />
                    <div>
                        <h3 class="font-bold text-base text-white">
                            {{ isRTL ? 'محرك تشغيل وترميز الصوت (Cinema Audio & Codec Engine)' : 'Cinema Audio & Codec Engine' }}
                        </h3>
                        <p class="text-xs text-slate-400">
                            {{ isRTL ? 'دعم تشغيل صيغ الصوت المتقدمة (E-AC-3, AC-3, DTS, TrueHD) بدون مشاكل انقطاع الصوت.' : 'Hardware & software audio decoding for Dolby Digital, DTS, and 5.1 multichannel audio.' }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-300">{{ isRTL ? 'مسار FFmpeg للترميز الفوري' : 'FFmpeg Binary Path' }}</label>
                        <input
                            v-model="form.ffmpeg_path"
                            type="text"
                            placeholder="ffmpeg or C:\ffmpeg\bin\ffmpeg.exe"
                            class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-300">{{ isRTL ? 'لغة الواجهة المفضلة' : 'Default Interface Language' }}</label>
                        <select
                            v-model="form.default_language"
                            class="w-full px-4 py-2.5 rounded-xl bg-[#0E121E] border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-500 font-bold"
                        >
                            <option value="ar">العربية (Arabic) - Default</option>
                            <option value="en">English</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 4. Media Cache & Storage Management -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-white/10">
                    <div class="flex items-center gap-2.5">
                        <HardDrive class="w-5 h-5 text-purple-400" />
                        <div>
                            <h3 class="font-bold text-base text-white">
                                {{ isRTL ? 'إدارة التخزين المؤقت للبث (Media Streams & Transcode Cache)' : 'Media Streams & Transcode Cache' }}
                            </h3>
                            <p class="text-xs text-slate-400">
                                {{ isRTL ? 'إدارة المساحة المستهلكة من قبل البث المباشر المحسن والملفات المؤقتة المحولة.' : 'Manage disk space occupied by server remux streams and transcode cache.' }}
                            </p>
                        </div>
                    </div>
                    <button
                        @click="refreshCacheStats"
                        class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white transition-all cursor-pointer"
                        title="Refresh Cache Stats"
                    >
                        <RefreshCw class="w-4 h-4" />
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Stat: Cache Size -->
                    <div class="p-4 rounded-2xl bg-white/5 border border-white/10 flex flex-col justify-between">
                        <span class="text-xs text-slate-400 font-semibold">{{ isRTL ? 'حجم الملفات المؤقتة' : 'Current Cache Size' }}</span>
                        <div class="text-2xl font-black text-cyan-400 mt-2 font-mono">
                            {{ mediaCacheStats.formatted_size }}
                        </div>
                    </div>

                    <!-- Stat: Cached Items Count -->
                    <div class="p-4 rounded-2xl bg-white/5 border border-white/10 flex flex-col justify-between">
                        <span class="text-xs text-slate-400 font-semibold">{{ isRTL ? 'عدد الملفات المؤقتة' : 'Cached Stream Files' }}</span>
                        <div class="text-2xl font-black text-purple-400 mt-2 font-mono">
                            {{ mediaCacheStats.file_count }}
                        </div>
                    </div>

                    <!-- Action: Clean Cache Button -->
                    <div class="p-4 rounded-2xl bg-white/5 border border-white/10 flex flex-col justify-between">
                        <span class="text-xs text-slate-400 font-semibold">{{ isRTL ? 'تحرير مساحة القرص' : 'Storage Cleanup' }}</span>
                        <button
                            type="button"
                            @click="clearMediaCache"
                            :disabled="isClearingCache || mediaCacheStats.file_count === 0"
                            class="mt-2 w-full py-2.5 px-4 rounded-xl bg-red-500/20 hover:bg-red-500/30 text-red-300 hover:text-white border border-red-500/30 text-xs font-bold flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shadow-lg"
                        >
                            <RefreshCw v-if="isClearingCache" class="w-4 h-4 animate-spin" />
                            <Trash2 v-else class="w-4 h-4 text-red-400" />
                            <span>{{ isClearingCache ? (isRTL ? 'جاري التنظيف...' : 'Clearing...') : (isRTL ? 'تنظيف كاش الوسائط' : 'Clean Media Cache') }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="cacheSuccessMessage" class="p-3 rounded-xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-xs font-bold flex items-center gap-2">
                    <CheckCircle2 class="w-4 h-4 shrink-0 text-emerald-400" />
                    <span>{{ cacheSuccessMessage }}</span>
                </div>
            </div>

            <!-- 5. Database Backup & Disaster Recovery -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6">
                <div class="flex items-center justify-between flex-wrap gap-4 pb-4 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                            <Database class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-base text-white flex items-center gap-2">
                                <span>{{ isRTL ? 'النسخ الاحتياطي واستعادة قاعدة البيانات' : 'Database Backup & Disaster Recovery' }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">
                                    {{ isRTL ? 'مباشر وآمن' : 'Live & Safe' }}
                                </span>
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ isRTL ? 'حفظ واستعادة فهارس المكتبة والأفلام والمسلسلات والترجمات برابط تنزيل مباشر أو لقطات محلية آمنة.' : 'Export and restore your entire library database, metadata, posters, subtitles, and settings with 1-click.' }}
                            </p>
                        </div>
                    </div>

                    <!-- Quick Top Actions -->
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="downloadDatabaseBackup"
                            class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-lg shadow-cyan-500/20"
                            :title="isRTL ? 'تنزيل نسخة احتياطية مباشرة لجهازك' : 'Download JSON Backup to your computer'"
                        >
                            <DownloadCloud class="w-4 h-4" />
                            <span>{{ isRTL ? 'تنزيل نسخة JSON' : 'Download Backup' }}</span>
                        </button>

                        <button
                            type="button"
                            @click="createServerSnapshot"
                            :disabled="isCreatingBackup"
                            class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer disabled:opacity-50"
                            :title="isRTL ? 'أخذ لقطة سريعة وحفظها على الخادم' : 'Create instant snapshot on server disk'"
                        >
                            <RefreshCw v-if="isCreatingBackup" class="w-4 h-4 animate-spin text-cyan-400" />
                            <Save v-else class="w-4 h-4 text-cyan-400" />
                            <span>{{ isCreatingBackup ? (isRTL ? 'جاري الحفظ...' : 'Saving...') : (isRTL ? 'حفظ لقطة على الخادم' : 'Save Snapshot') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Database Metrics Bar -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'الأفلام المفهرسة' : 'Indexed Movies' }}</span>
                        <div class="text-xl font-black text-white mt-1 font-mono">
                            {{ database_stats?.media_items || 0 }}
                        </div>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'المسلسلات والحلقات' : 'Series & Episodes' }}</span>
                        <div class="text-xl font-black text-cyan-400 mt-1 font-mono">
                            {{ database_stats?.series || 0 }} <span class="text-xs text-slate-400 font-normal">({{ database_stats?.episodes || 0 }} ep)</span>
                        </div>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'ملفات الترجمة' : 'Cataloged Subtitles' }}</span>
                        <div class="text-xl font-black text-purple-400 mt-1 font-mono">
                            {{ database_stats?.subtitles || 0 }}
                        </div>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ isRTL ? 'اللقطات المحفوظة' : 'Local Snapshots' }}</span>
                        <div class="text-xl font-black text-emerald-400 mt-1 font-mono">
                            {{ localBackups.length }}
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
                <div v-if="backupSuccessMessage" class="p-3.5 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-bold flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <CheckCircle2 class="w-4 h-4 shrink-0 text-emerald-400" />
                        <span>{{ backupSuccessMessage }}</span>
                    </div>
                    <button @click="backupSuccessMessage = null" class="cursor-pointer text-emerald-400 hover:text-emerald-200">✕</button>
                </div>

                <div v-if="backupErrorMessage" class="p-3.5 rounded-2xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs font-bold flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="w-4 h-4 shrink-0 text-rose-400" />
                        <span>{{ backupErrorMessage }}</span>
                    </div>
                    <button @click="backupErrorMessage = null" class="cursor-pointer text-rose-400 hover:text-rose-200">✕</button>
                </div>

                <!-- Selective Restore Configuration Panel -->
                <div class="p-5 rounded-2xl bg-white/[0.02] border border-white/10 space-y-4">
                    <div class="flex items-center justify-between flex-wrap gap-3 pb-3 border-b border-white/10">
                        <div>
                            <h4 class="text-sm font-black text-white flex items-center gap-2">
                                <span>{{ isRTL ? 'تخصيص أقسام الاستعادة (Selective Restore Sections)' : 'Selective Restore Sections' }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black" :class="selectedSections.length > 0 ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30'">
                                    {{ selectedSections.length }} / {{ availableSections.length }} {{ isRTL ? 'محدد' : 'selected' }}
                                </span>
                            </h4>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ isRTL ? 'اختر الأقسام التي ترغب في استعادتها تحديداً (مثل الأفلام فقط، أو المسلسلات فقط، أو الترجمات). الأقسام غير المحددة لن تُحذف ولن تتأثر إطلاقاً.' : 'Select precisely which sections to restore. Unselected sections will remain completely safe and untouched.' }}
                            </p>
                        </div>

                        <!-- Quick Select / Deselect All -->
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="selectAllSections"
                                class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                            >
                                <CheckCheck class="w-3.5 h-3.5 text-cyan-400" />
                                <span>{{ isRTL ? 'تحديد الكل' : 'Select All' }}</span>
                            </button>
                            <button
                                type="button"
                                @click="clearAllSections"
                                class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                            >
                                <X class="w-3.5 h-3.5 text-slate-400" />
                                <span>{{ isRTL ? 'إلغاء التحديد' : 'Clear All' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Section Cards Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                        <div
                            v-for="section in availableSections"
                            :key="section.id"
                            @click="toggleSection(section.id)"
                            class="p-3.5 rounded-xl border transition-all cursor-pointer flex flex-col justify-between group relative select-none"
                            :class="selectedSections.includes(section.id) ? 'bg-cyan-500/10 border-cyan-500/40 text-white shadow-lg shadow-cyan-500/5 ring-1 ring-cyan-500/30' : 'bg-white/[0.02] border-white/5 text-slate-400 hover:border-white/20 hover:text-slate-200'"
                        >
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <div class="w-7 h-7 rounded-lg flex items-center justify-center" :class="selectedSections.includes(section.id) ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-slate-400'">
                                        <Film v-if="section.id === 'movies'" class="w-4 h-4" />
                                        <Tv v-else-if="section.id === 'series'" class="w-4 h-4" />
                                        <FileText v-else-if="section.id === 'subtitles'" class="w-4 h-4" />
                                        <SettingsIcon v-else-if="section.id === 'settings'" class="w-4 h-4" />
                                        <Clock v-else class="w-4 h-4" />
                                    </div>

                                    <div class="w-5 h-5 rounded-md flex items-center justify-center transition-all" :class="selectedSections.includes(section.id) ? 'bg-cyan-500 text-slate-950 font-black' : 'border border-white/20'">
                                        <Check v-if="selectedSections.includes(section.id)" class="w-3.5 h-3.5" />
                                    </div>
                                </div>

                                <h5 class="text-xs font-black" :class="selectedSections.includes(section.id) ? 'text-white' : 'text-slate-300'">
                                    {{ isRTL ? section.labelAr : section.labelEn }}
                                </h5>
                                <p class="text-[10px] text-slate-400 mt-1 leading-snug">
                                    {{ isRTL ? section.descAr : section.descEn }}
                                </p>
                            </div>

                            <div v-if="section.countKey && database_stats" class="mt-2 pt-2 border-t border-white/5 text-[10px] font-mono text-cyan-400/80 font-bold">
                                {{ database_stats[section.countKey as keyof typeof database_stats] ?? 0 }} {{ isRTL ? 'عنصر حالي' : 'items' }}
                            </div>
                        </div>
                    </div>

                    <!-- Mode Selector & Safety Guarantee Banner -->
                    <div class="pt-2 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                        <!-- Mode Switcher -->
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-bold text-slate-300 shrink-0">
                                {{ isRTL ? 'طريقة استعادة الأقسام المحددة:' : 'Restore mode for selected sections:' }}
                            </span>
                            <div class="inline-flex rounded-xl bg-white/5 p-1 border border-white/10">
                                <button
                                    type="button"
                                    @click="restoreMode = 'overwrite'"
                                    class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer"
                                    :class="restoreMode === 'overwrite' ? 'bg-cyan-500 text-slate-950 font-black shadow-sm' : 'text-slate-400 hover:text-white'"
                                >
                                    {{ isRTL ? 'استبدال كامل (Clean Overwrite)' : 'Clean Overwrite' }}
                                </button>
                                <button
                                    type="button"
                                    @click="restoreMode = 'merge'"
                                    class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer"
                                    :class="restoreMode === 'merge' ? 'bg-purple-500 text-white font-black shadow-sm' : 'text-slate-400 hover:text-white'"
                                >
                                    {{ isRTL ? 'دمج وتحديث (Merge)' : 'Merge & Update' }}
                                </button>
                            </div>
                        </div>

                        <!-- Safety Notice -->
                        <div class="flex items-center gap-2 text-[11px] text-slate-400">
                            <Shield class="w-4 h-4 text-emerald-400 shrink-0" />
                            <span>{{ isRTL ? 'يتم حفظ لقطة أمان احتياطية تلقائياً قبل أي عملية استعادة.' : 'Automatic safety snapshot is created before every restore.' }}</span>
                        </div>
                    </div>
                </div>

                <!-- 2 Main Cards: Upload Restore & Snapshots List -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <!-- Card 1: Restore from File -->
                    <div class="p-5 rounded-2xl bg-white/[0.02] border border-white/10 space-y-4 flex flex-col justify-between">
                        <div class="space-y-3">
                            <div class="flex items-center gap-2 text-white font-bold text-sm">
                                <UploadCloud class="w-4 h-4 text-cyan-400" />
                                <span>{{ isRTL ? 'استعادة قاعدة البيانات من ملف خارجي' : 'Restore Database from File' }}</span>
                            </div>
                            <p class="text-xs text-slate-400 leading-relaxed">
                                {{ isRTL ? 'قم برفع ملف نسخة احتياطية بصيغة (.json) أو (.sqlite / .db). سيتم أخذ نسخة أمان احتياطية تلقائياً قبل البدء بالاستعادة.' : 'Upload a (.json) or (.sqlite / .db) backup file to restore library catalog and settings. An automatic safety backup is saved first.' }}
                            </p>

                            <!-- File Selector Dropzone -->
                            <div class="border-2 border-dashed border-white/10 rounded-2xl p-4 text-center hover:border-cyan-500/40 transition-colors">
                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    accept=".json,.sqlite,.db"
                                    class="hidden"
                                    @change="onFileSelected"
                                    id="backup-file-input"
                                />
                                <label for="backup-file-input" class="cursor-pointer flex flex-col items-center gap-2">
                                    <FolderArchive class="w-7 h-7 text-cyan-400" />
                                    <div class="text-xs font-bold text-slate-200">
                                        {{ selectedFile ? selectedFile.name : (isRTL ? 'اختر ملف النسخة الاحتياطية (.json / .sqlite)' : 'Choose backup file (.json, .sqlite, .db)') }}
                                    </div>
                                    <span class="text-[10px] text-slate-500">
                                        {{ selectedFile ? `${(selectedFile.size / 1024 / 1024).toFixed(2)} MB` : (isRTL ? 'انقر لاختيار ملف من جهازك' : 'Click to browse files') }}
                                    </span>
                                </label>
                            </div>

                            <!-- Mode Selector -->
                            <div class="space-y-1.5 pt-1">
                                <label class="text-xs font-bold text-slate-300 block">
                                    {{ isRTL ? 'طريقة الاستعادة:' : 'Restore Mode:' }}
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        @click="restoreMode = 'overwrite'"
                                        class="p-2.5 rounded-xl border text-xs font-bold text-left transition-all cursor-pointer"
                                        :class="restoreMode === 'overwrite' ? 'bg-cyan-500/15 border-cyan-500/40 text-cyan-300 ring-1 ring-cyan-500/30' : 'bg-white/5 border-white/10 text-slate-400 hover:text-white'"
                                    >
                                        <div class="font-black">{{ isRTL ? 'استبدال كامل (موصى به)' : 'Clean Overwrite' }}</div>
                                        <span class="text-[10px] opacity-75 block mt-0.5">{{ isRTL ? 'مطابقة النسخة تماماً' : 'Recommended for full restores' }}</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="restoreMode = 'merge'"
                                        class="p-2.5 rounded-xl border text-xs font-bold text-left transition-all cursor-pointer"
                                        :class="restoreMode === 'merge' ? 'bg-purple-500/15 border-purple-500/40 text-purple-300 ring-1 ring-purple-500/30' : 'bg-white/5 border-white/10 text-slate-400 hover:text-white'"
                                    >
                                        <div class="font-black">{{ isRTL ? 'دمج وتحديث' : 'Merge & Update' }}</div>
                                        <span class="text-[10px] opacity-75 block mt-0.5">{{ isRTL ? 'تحديث وإضافة دون حذف' : 'Keep existing records' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="promptRestoreUpload"
                            :disabled="!selectedFile || isRestoring || selectedSections.length === 0"
                            class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-xs flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shadow-lg shadow-cyan-500/20"
                        >
                            <RefreshCw v-if="isRestoring" class="w-4 h-4 animate-spin" />
                            <RotateCcw v-else class="w-4 h-4" />
                            <span>{{ isRestoring ? (isRTL ? 'جاري استعادة الأقسام المحددة...' : 'Restoring Selected Sections...') : (selectedSections.length === 0 ? (isRTL ? 'حدد قسماً واحداً على الأقل للاستعادة' : 'Select at least 1 section above') : (isRTL ? `استعادة ${selectedSections.length} أقسام محددة من الملف` : `Restore ${selectedSections.length} Selected Section(s)`)) }}</span>
                        </button>
                    </div>

                    <!-- Card 2: Local Server Snapshots List -->
                    <div class="p-5 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3 flex flex-col justify-between">
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2 text-white font-bold text-sm">
                                    <Clock class="w-4 h-4 text-emerald-400" />
                                    <span>{{ isRTL ? 'اللقطات المحفوظة محلياً على الخادم' : 'Saved Local Snapshots' }}</span>
                                </div>
                                <button
                                    type="button"
                                    @click="fetchBackupsList"
                                    class="text-[11px] text-cyan-400 hover:underline cursor-pointer flex items-center gap-1 font-bold"
                                >
                                    <RefreshCw class="w-3 h-3" />
                                    <span>{{ isRTL ? 'تحديث القائمة' : 'Refresh' }}</span>
                                </button>
                            </div>

                            <p class="text-xs text-slate-400 leading-relaxed">
                                {{ isRTL ? 'نسخ احتياطية مخزنة على القرص المحلي للاسترجاع السريع في أي وقت بنقرة واحدة.' : 'Instant rollback snapshots stored in storage/app/backups for quick recovery.' }}
                            </p>

                            <!-- Backups Table / Scrollable List -->
                            <div class="max-h-56 overflow-y-auto space-y-2 pr-1 custom-scrollbar">
                                <div
                                    v-for="backup in localBackups"
                                    :key="backup.filename"
                                    class="p-3 rounded-xl bg-white/[0.03] border border-white/5 flex items-center justify-between gap-3 hover:border-white/15 transition-all"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase"
                                                :class="backup.extension === 'json' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'bg-purple-500/20 text-purple-300 border border-purple-500/30'"
                                            >
                                                {{ backup.extension }}
                                            </span>
                                            <span class="text-xs font-bold text-slate-200 truncate block font-mono" :title="backup.filename">
                                                {{ backup.filename }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 text-[10px] text-slate-400 mt-1 font-mono">
                                            <span>{{ backup.size_formatted }}</span>
                                            <span>•</span>
                                            <span>{{ backup.created_at }}</span>
                                            <span v-if="backup.summary?.media_items" class="text-cyan-400 font-bold hidden sm:inline">
                                                ({{ backup.summary.media_items }} movies)
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <!-- Restore Button -->
                                        <button
                                            type="button"
                                            @click="promptRestoreLocal(backup)"
                                            :disabled="isRestoring || selectedSections.length === 0"
                                            class="p-1.5 rounded-lg bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                            :title="isRTL ? `استعادة الأقسام المحددة (${selectedSections.length}) من هذه اللقطة` : `Restore ${selectedSections.length} selected sections from this snapshot`"
                                        >
                                            <RotateCcw class="w-3.5 h-3.5" />
                                        </button>

                                        <!-- Download Button -->
                                        <a
                                            :href="`/api/database/backups/download/${encodeURIComponent(backup.filename)}`"
                                            class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white transition-colors cursor-pointer"
                                            :title="isRTL ? 'تنزيل إلى جهازك' : 'Download to your computer'"
                                            download
                                        >
                                            <DownloadCloud class="w-3.5 h-3.5" />
                                        </a>

                                        <!-- Delete Button -->
                                        <button
                                            type="button"
                                            @click="promptDeleteBackup(backup)"
                                            class="p-1.5 rounded-lg bg-rose-500/15 hover:bg-rose-500/25 text-rose-400 transition-colors cursor-pointer"
                                            :title="isRTL ? 'حذف اللقطة' : 'Delete snapshot'"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <div v-if="localBackups.length === 0" class="py-8 text-center text-xs text-slate-500 italic">
                                    {{ isRTL ? 'لا توجد لقطات محلية محفوظة حالياً. انقر "حفظ لقطة على الخادم" لإنشاء أول لقطة.' : 'No local snapshots saved yet. Click "Save Snapshot" to create your first backup.' }}
                                </div>
                            </div>
                        </div>

                        <!-- Footer Tip -->
                        <div class="p-2.5 rounded-xl bg-cyan-500/5 border border-cyan-500/15 flex items-center gap-2 text-[11px] text-cyan-300/80">
                            <Shield class="w-3.5 h-3.5 shrink-0 text-cyan-400" />
                            <span>{{ isRTL ? 'يتم حفظ اللقطات بأمان في مسار storage/app/backups على خادمك.' : 'Snapshots are securely preserved in storage/app/backups on your host.' }}</span>
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
