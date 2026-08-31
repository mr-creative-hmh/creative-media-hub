<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useDownloader, DownloadItem } from '@/composables/useDownloader';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    DownloadCloud, Plus, CheckCircle2, ArrowDown, ArrowUp,
    Play, Pause, Trash2, FolderSync, Sparkles, HelpCircle,
    Info, HardDrive, ShieldCheck, Film, Tv, Radio, Clock,
    RotateCcw, AlertTriangle, ExternalLink
} from 'lucide-vue-next';

const props = defineProps<{
    initialDownloads?: DownloadItem[];
}>();

const { t, isRTL } = useI18n();
const {
    downloads,
    activeDownloads,
    completedDownloads,
    totalSpeedDownFormatted,
    totalSpeedUpFormatted,
    formatBytes,
    getProgressPercent,
    getETA,
    fetchDownloads,
    addDownload,
    pauseDownload,
    resumeDownload,
    retryDownload,
    deleteDownload,
} = useDownloader();

const activeFilter = ref<'all' | 'downloading' | 'paused' | 'completed'>('all');
const showAddModal = ref(false);
const showExplainModal = ref(false);

const newTitle = ref('');
const newUrl = ref('');
const newType = ref<'movie' | 'series' | 'subtitle'>('movie');
const isSubmitting = ref(false);

onMounted(() => {
    if (props.initialDownloads && props.initialDownloads.length > 0 && downloads.value.length === 0) {
        downloads.value = props.initialDownloads;
    }
    fetchDownloads();
});

const filteredItems = computed(() => {
    if (activeFilter.value === 'all') return downloads.value;
    if (activeFilter.value === 'downloading') {
        return downloads.value.filter(d => d.status === 'downloading' || d.status === 'queued');
    }
    return downloads.value.filter(d => d.status === activeFilter.value);
});

const handleAddSubmit = async () => {
    if (!newTitle.value.trim()) return;
    isSubmitting.value = true;
    try {
        await addDownload(newTitle.value.trim(), newType.value, newUrl.value.trim() || undefined);
        newTitle.value = '';
        newUrl.value = '';
        showAddModal.value = false;
    } finally {
        isSubmitting.value = false;
    }
};

const handleQuickSeed = async (sampleTitle: string, type: 'movie' | 'series') => {
    await addDownload(sampleTitle, type);
};
</script>

<template>
    <Head :title="t('nav.downloads')" />

    <AppLayout v-slot="{ play }">
        <!-- Top Header & Metrics -->
        <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                    <DownloadCloud class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                        {{ t('nav.downloads') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                        {{ isRTL ? 'إدارة التنزيلات الحقيقية في الخلفية، مراقبة مجلدات الاستقبال، والفهرسة الآلية للوسائط.' : 'Manage background media downloads, incoming watch folders, and automated library indexing.' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button
                    @click="showExplainModal = true"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl glass-panel border border-white/10 hover:border-cyan-500/40 text-slate-300 hover:text-white font-bold text-xs transition-all cursor-pointer shadow-sm"
                >
                    <HelpCircle class="w-4 h-4 text-cyan-400" />
                    <span>{{ isRTL ? 'كيف تعمل التنزيلات والمراقبة؟' : 'How It Works (Guide)' }}</span>
                </button>

                <button
                    @click="showAddModal = true"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/20 active:scale-95 transition-all cursor-pointer"
                >
                    <Plus class="w-4 h-4" />
                    <span>{{ isRTL ? 'إضافة رابط / تورنت جديد' : 'Add New Download' }}</span>
                </button>
            </div>
        </div>

        <!-- Real-Time Bandwidth & Queue Metrics Bar -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="glass-panel rounded-2xl p-4 border border-cyan-500/30 bg-cyan-500/5 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-400 block">{{ isRTL ? 'سرعة التنزيل الحالية' : 'Download Speed' }}</span>
                    <span class="text-xl font-black text-cyan-300 font-mono mt-1 block">{{ totalSpeedDownFormatted }}</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                    <ArrowDown class="w-5 h-5" :class="{ 'animate-bounce': activeDownloads.length > 0 }" />
                </div>
            </div>

            <div class="glass-panel rounded-2xl p-4 border border-indigo-500/30 bg-indigo-500/5 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-400 block">{{ isRTL ? 'سرعة الرفع / المشاركة' : 'Upload / Seeding' }}</span>
                    <span class="text-xl font-black text-indigo-300 font-mono mt-1 block">{{ totalSpeedUpFormatted }}</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center">
                    <ArrowUp class="w-5 h-5" />
                </div>
            </div>

            <div class="glass-panel rounded-2xl p-4 border border-white/10 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-400 block">{{ isRTL ? 'المهام النشطة' : 'Active Tasks' }}</span>
                    <span class="text-xl font-black text-white font-mono mt-1 block">{{ activeDownloads.length }} {{ isRTL ? 'قيد التنزيل' : 'In Progress' }}</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-white/5 text-slate-300 flex items-center justify-center">
                    <Radio class="w-5 h-5" :class="activeDownloads.length > 0 ? 'text-amber-400 animate-pulse' : 'text-slate-500'" />
                </div>
            </div>

            <div class="glass-panel rounded-2xl p-4 border border-emerald-500/30 bg-emerald-500/5 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-400 block">{{ isRTL ? 'المكتملة في المكتبة' : 'Completed' }}</span>
                    <span class="text-xl font-black text-emerald-300 font-mono mt-1 block">{{ completedDownloads.length }} {{ isRTL ? 'عنصر' : 'Items' }}</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                    <CheckCircle2 class="w-5 h-5" />
                </div>
            </div>
        </div>

        <!-- 3 Interactive Workflow Explanatory Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="glass-card rounded-2xl p-4 border border-white/10 space-y-2 hover:border-cyan-500/30 transition-all">
                <div class="flex items-center gap-2 text-cyan-400 font-extrabold text-xs">
                    <Sparkles class="w-4 h-4" />
                    <span>1. {{ isRTL ? 'التنزيل في الخلفية' : 'Background Download Engine' }}</span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                    {{ isRTL ? 'يتم تنزيل الملفات في الخلفية بدون الحاجة للبقاء في هذه الصفحة مع إدارة تلقائية للسرعات واستئناف التنزيل.' : 'Downloads run asynchronously in background jobs with chunked buffering and resume capabilities.' }}
                </p>
            </div>

            <div class="glass-card rounded-2xl p-4 border border-white/10 space-y-2 hover:border-indigo-500/30 transition-all">
                <div class="flex items-center gap-2 text-indigo-400 font-extrabold text-xs">
                    <FolderSync class="w-4 h-4" />
                    <span>2. {{ isRTL ? 'فك الضغط التلقائي' : 'Auto Unpack & Extract' }}</span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                    {{ isRTL ? 'يتم فك ضغط الأرشيفات المجزأة (.rar, .zip, .7z) تلقائياً بمجرد اكتمال التنزيل دون أي تدخل يدوي.' : 'Multipart archives (.zip, .rar) are extracted automatically upon completion into target folders.' }}
                </p>
            </div>

            <div class="glass-card rounded-2xl p-4 border border-white/10 space-y-2 hover:border-emerald-500/30 transition-all">
                <div class="flex items-center gap-2 text-emerald-400 font-extrabold text-xs">
                    <HardDrive class="w-4 h-4" />
                    <span>3. {{ isRTL ? 'الفهرسة الفورية في المكتبة' : 'Instant Library Ingestion' }}</span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                    {{ isRTL ? 'بمجرد انتهاء التنزيل، يقوم الفاحص بجلب البوستر العربي والإنكليزي وتنزيل ملفات الترجمة ليكون الفيلم جاهزاً للمشاهدة فوراً.' : 'Completed downloads trigger instant background metadata enrichment, 4K posters, and subtitle sync.' }}
                </p>
            </div>
        </div>

        <!-- Download Queue Section -->
        <div class="glass-panel rounded-3xl p-6 border border-white/10 space-y-6">
            <!-- Filter Pills -->
            <div class="flex items-center justify-between flex-wrap gap-4 pb-4 border-b border-white/10">
                <div class="flex items-center gap-2">
                    <button
                        v-for="filter in [
                            { key: 'all', label: isRTL ? 'الكل' : 'All', count: downloads.length },
                            { key: 'downloading', label: isRTL ? 'جاري التنزيل' : 'Downloading', count: activeDownloads.length },
                            { key: 'paused', label: isRTL ? 'متوقف مؤقتاً' : 'Paused', count: downloads.filter(d => d.status === 'paused').length },
                            { key: 'completed', label: isRTL ? 'المكتملة' : 'Completed', count: completedDownloads.length },
                        ]"
                        :key="filter.key"
                        @click="activeFilter = filter.key as any"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                        :class="activeFilter === filter.key
                            ? 'bg-cyan-500 text-slate-950 font-black shadow-lg shadow-cyan-500/20'
                            : 'bg-white/5 text-slate-400 hover:bg-white/10 border border-white/10'"
                    >
                        <span>{{ filter.label }}</span>
                        <span v-if="filter.count > 0" class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeFilter === filter.key ? 'bg-slate-950 text-cyan-300' : 'bg-white/10 text-slate-300'">
                            {{ filter.count }}
                        </span>
                    </button>
                </div>

                <span class="text-xs text-slate-400 font-mono">{{ filteredItems.length }} {{ isRTL ? 'عنصر في القائمة' : 'items shown' }}</span>
            </div>

            <!-- Items List -->
            <div v-if="filteredItems.length > 0" class="space-y-3">
                <div
                    v-for="item in filteredItems"
                    :key="item.id"
                    class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 hover:border-white/20 transition-all space-y-3"
                >
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border"
                                :class="item.media_type === 'movie' ? 'bg-cyan-500/20 border-cyan-500/40 text-cyan-400' : 'bg-indigo-500/20 border-indigo-500/40 text-indigo-400'"
                            >
                                <Film v-if="item.media_type === 'movie'" class="w-5 h-5" />
                                <Tv v-else class="w-5 h-5" />
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-extrabold text-sm text-white truncate max-w-md">{{ item.title }}</h3>
                                    <span
                                        class="cinema-badge text-[10px]"
                                        :class="item.status === 'downloading'
                                            ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30'
                                            : item.status === 'completed'
                                            ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                                            : item.status === 'paused'
                                            ? 'bg-amber-500/20 text-amber-300 border-amber-500/30'
                                            : 'bg-rose-500/20 text-rose-300 border-rose-500/30'"
                                    >
                                        {{ item.status }}
                                    </span>
                                </div>
                                <div class="text-[11px] font-mono text-slate-500 truncate mt-0.5" :title="item.destination_path">
                                    📂 {{ item.destination_path }}
                                </div>
                            </div>
                        </div>

                        <!-- Action Toolbar -->
                        <div class="flex items-center gap-2">
                            <button
                                v-if="item.status === 'downloading'"
                                @click="pauseDownload(item.id)"
                                class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white cursor-pointer"
                                :title="isRTL ? 'إيقاف مؤقت' : 'Pause'"
                            >
                                <Pause class="w-4 h-4" />
                            </button>

                            <button
                                v-else-if="item.status === 'paused'"
                                @click="resumeDownload(item.id)"
                                class="p-2 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/30 cursor-pointer"
                                :title="isRTL ? 'استئناف التنزيل' : 'Resume'"
                            >
                                <Play class="w-4 h-4 fill-current" />
                            </button>

                            <button
                                v-else-if="item.status === 'failed'"
                                @click="retryDownload(item.id)"
                                class="p-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 cursor-pointer"
                                :title="isRTL ? 'إعادة المحاولة' : 'Retry'"
                            >
                                <RotateCcw class="w-4 h-4" />
                            </button>

                            <button
                                v-if="item.status === 'completed'"
                                @click="play({ id: item.id, title: item.title })"
                                class="px-3 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs flex items-center gap-1.5 cursor-pointer shadow-sm"
                            >
                                <Play class="w-3.5 h-3.5 fill-current" />
                                <span>{{ isRTL ? 'تشغيل الآن' : 'Play' }}</span>
                            </button>

                            <button
                                @click="deleteDownload(item.id)"
                                class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 cursor-pointer"
                                :title="t('common.delete')"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Progress Bar & Speed Stats -->
                    <div class="space-y-1.5">
                        <div class="w-full h-2 rounded-full bg-white/10 overflow-hidden relative">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="item.status === 'completed'
                                    ? 'bg-emerald-500'
                                    : item.status === 'paused'
                                    ? 'bg-amber-500'
                                    : 'bg-gradient-to-r from-cyan-400 via-cyan-500 to-blue-500'"
                                :style="{ width: `${getProgressPercent(item)}%` }"
                            ></div>
                        </div>

                        <div class="flex items-center justify-between text-[11px] font-mono text-slate-400">
                            <span>{{ formatBytes(item.downloaded_bytes) }} / {{ formatBytes(item.total_bytes) }} ({{ getProgressPercent(item) }}%)</span>
                            <div class="flex items-center gap-4">
                                <span v-if="item.status === 'downloading'" class="text-cyan-400 font-bold">↓ {{ formatBytes(item.speed_bytes_sec) }}/s</span>
                                <span class="text-slate-400 font-normal">⏱️ {{ getETA(item) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State with Quick Seed Suggestions -->
            <div v-else class="text-center py-12 space-y-4">
                <CheckCircle2 class="w-12 h-12 text-slate-600 mx-auto" />
                <div>
                    <h4 class="font-extrabold text-base text-white">
                        {{ isRTL ? 'لا توجد تنزيلات في هذه القائمة حالياً' : 'No downloads in this queue tab' }}
                    </h4>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto mt-1">
                        {{ isRTL ? 'يمكنك إضافة رابط تنزيل مباشر، أو تجربة إضافة أحد النماذج السريعة أدناه لاختبار التنزيل والفهرسة.' : 'Add a new media URL or try starting one of the sample media streams below to test.' }}
                    </p>
                </div>

                <!-- Quick Start Sample Streams -->
                <div class="flex items-center justify-center flex-wrap gap-2 pt-2">
                    <button
                        @click="handleQuickSeed('Gladiator II (2024)', 'movie')"
                        class="px-3.5 py-1.5 rounded-xl bg-white/5 hover:bg-cyan-500/20 border border-white/10 hover:border-cyan-500/40 text-xs font-bold text-slate-300 hover:text-cyan-300 transition-all cursor-pointer"
                    >
                        🎬 Gladiator II (2024)
                    </button>
                    <button
                        @click="handleQuickSeed('Dune: Part Two (2024)', 'movie')"
                        class="px-3.5 py-1.5 rounded-xl bg-white/5 hover:bg-cyan-500/20 border border-white/10 hover:border-cyan-500/40 text-xs font-bold text-slate-300 hover:text-cyan-300 transition-all cursor-pointer"
                    >
                        🎬 Dune: Part Two (2024)
                    </button>
                    <button
                        @click="handleQuickSeed('Shogun - S01E01 (2024)', 'series')"
                        class="px-3.5 py-1.5 rounded-xl bg-white/5 hover:bg-indigo-500/20 border border-white/10 hover:border-indigo-500/40 text-xs font-bold text-slate-300 hover:text-indigo-300 transition-all cursor-pointer"
                    >
                        📺 Shogun (2024)
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Download Modal -->
        <div
            v-if="showAddModal"
            class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4 animate-in fade-in"
            @click.self="showAddModal = false"
        >
            <div class="glass-panel rounded-3xl p-6 sm:p-8 max-w-lg w-full border border-cyan-500/30 shadow-2xl space-y-5">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <h3 class="font-extrabold text-base text-white flex items-center gap-2">
                        <DownloadCloud class="w-5 h-5 text-cyan-400" />
                        <span>{{ isRTL ? 'إضافة وسائط للتنزيل والفهرسة' : 'Add New Media Download' }}</span>
                    </h3>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-bold text-slate-400 mb-1 block">{{ isRTL ? 'عنوان العمل (فيلم أو مسلسل)' : 'Media Title & Year' }}</label>
                        <input
                            type="text"
                            v-model="newTitle"
                            placeholder="e.g. Oppenheimer (2023)"
                            class="w-full h-11 rounded-xl bg-white/[0.04] border border-white/15 px-4 text-xs text-white focus:border-cyan-500 outline-none"
                        />
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-400 mb-1 block">{{ isRTL ? 'رابط Magnet أو مسار التحميل المباشر' : 'Magnet URI / Stream URL' }}</label>
                        <input
                            type="text"
                            v-model="newUrl"
                            placeholder="https://example.com/video.mp4 or magnet:?xt=urn:btih:..."
                            class="w-full h-11 rounded-xl bg-white/[0.04] border border-white/15 px-4 text-xs font-mono text-cyan-300 focus:border-cyan-500 outline-none"
                        />
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-400 mb-1 block">{{ isRTL ? 'نوع المحتوى' : 'Type' }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="newType = 'movie'"
                                class="py-2 rounded-xl text-xs font-bold border cursor-pointer"
                                :class="newType === 'movie' ? 'bg-cyan-500 text-slate-950 font-black' : 'bg-white/5 border-white/10 text-slate-400'"
                            >
                                🎬 {{ isRTL ? 'فيلم' : 'Movie' }}
                            </button>
                            <button
                                type="button"
                                @click="newType = 'series'"
                                class="py-2 rounded-xl text-xs font-bold border cursor-pointer"
                                :class="newType === 'series' ? 'bg-cyan-500 text-slate-950 font-black' : 'bg-white/5 border-white/10 text-slate-400'"
                            >
                                📺 {{ isRTL ? 'مسلسل' : 'Series' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                    <button
                        @click="showAddModal = false"
                        class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-bold text-slate-300 cursor-pointer"
                    >
                        {{ t('common.close') }}
                    </button>
                    <button
                        @click="handleAddSubmit"
                        :disabled="isSubmitting || !newTitle.trim()"
                        class="px-6 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 disabled:opacity-50 text-slate-950 text-xs font-black cursor-pointer shadow-lg shadow-cyan-500/20"
                    >
                        {{ isRTL ? 'بدء التنزيل والفهرسة' : 'Start Download Job' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Explain Modal -->
        <div
            v-if="showExplainModal"
            class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4 animate-in fade-in"
            @click.self="showExplainModal = false"
        >
            <div class="glass-panel rounded-3xl p-6 sm:p-8 max-w-lg w-full border border-cyan-500/30 shadow-2xl space-y-5">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <h3 class="font-extrabold text-base text-white flex items-center gap-2">
                        <Info class="w-5 h-5 text-cyan-400" />
                        <span>{{ isRTL ? 'دليل دورة التنزيل والفهرسة التلقائية' : 'Download & Ingestion Lifecycle' }}</span>
                    </h3>
                </div>

                <div class="space-y-3 text-xs text-slate-300 leading-relaxed">
                    <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-1">
                        <span class="font-bold text-cyan-400 block">1. الاستقبال والتنزيل المباشر في الخلفية</span>
                        <p class="text-slate-400">يقوم محرك التنزيل باستقبال روابط التحميل والتورنت وتنزيل الحزم في الخلفية دون تعطيل تصفحك للموقع.</p>
                    </div>

                    <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-1">
                        <span class="font-bold text-indigo-400 block">2. مراقبة المجلدات والاستخراج</span>
                        <p class="text-slate-400">يتم فحص مجلد التنزيلات دورياً، وفي حال وجود ملفات مضغوطة .zip يتم استخراجها تلقائياً إلى مجلد الوسائط.</p>
                    </div>

                    <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-1">
                        <span class="font-bold text-emerald-400 block">3. المطابقة والفهرسة التلقائية</span>
                        <p class="text-slate-400">بمجرد اكتمال تنزيل الفيلم، يقوم الفاحص فورياً بمطابقته وجلب البوسترات والترجمات العربية ليظهر في مكتبتك جاهزاً للعرض.</p>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        @click="showExplainModal = false"
                        class="px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-black cursor-pointer"
                    >
                        {{ isRTL ? 'فهمت ذلك' : 'Got it' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
