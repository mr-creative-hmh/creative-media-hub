<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    DownloadCloud, Plus, CheckCircle2, ArrowDown, ArrowUp,
    Play, Pause, Trash2, FolderSync, Sparkles, HelpCircle,
    Info, HardDrive, ShieldCheck, Film, Tv, Radio, Clock, Check
} from 'lucide-vue-next';

const props = defineProps<{
    downloads?: any[];
}>();

const { t, isRTL } = useI18n();

const activeFilter = ref<'all' | 'downloading' | 'queued' | 'completed'>('all');
const showAddModal = ref(false);
const showExplainModal = ref(false);

const newTitle = ref('');
const newUrl = ref('');
const newType = ref<'movie' | 'series'>('movie');
const newQuality = ref('1080p');

// Mock rich download queue items if database has only basic records
const items = ref([
    {
        id: 1,
        title: 'Dune: Part Two (2024)',
        type: 'movie',
        status: 'downloading',
        progress: 68,
        speed_down: '18.4 MB/s',
        speed_up: '1.2 MB/s',
        size_total: '6.4 GB',
        size_downloaded: '4.35 GB',
        eta: '1m 45s',
        destination: 'D:/MediaLibrary/Movies/Dune Part Two (2024)',
        peers: '84 (142 seeds)',
    },
    {
        id: 2,
        title: 'Shogun (2024) - Season 1 (E01-E10)',
        type: 'series',
        status: 'downloading',
        progress: 34,
        speed_down: '12.8 MB/s',
        speed_up: '820 KB/s',
        size_total: '14.2 GB',
        size_downloaded: '4.82 GB',
        eta: '11m 20s',
        destination: 'D:/MediaLibrary/TV Shows/Shogun (2024)',
        peers: '112 (320 seeds)',
    },
    {
        id: 3,
        title: 'Oppenheimer (2023) [IMAX Remaster]',
        type: 'movie',
        status: 'completed',
        progress: 100,
        speed_down: '0 KB/s',
        speed_up: '450 KB/s',
        size_total: '11.8 GB',
        size_downloaded: '11.8 GB',
        eta: 'Completed',
        destination: 'D:/MediaLibrary/Movies/Oppenheimer (2023)',
        peers: 'Seeding (1:1.8 ratio)',
    },
    {
        id: 4,
        title: 'Severance - S02E01 (2025)',
        type: 'series',
        status: 'completed',
        progress: 100,
        speed_down: '0 KB/s',
        speed_up: '210 KB/s',
        size_total: '1.8 GB',
        size_downloaded: '1.8 GB',
        eta: 'Completed',
        destination: 'D:/MediaLibrary/TV Shows/Severance/Season 02',
        peers: 'Seeding',
    },
    {
        id: 5,
        title: 'Gladiator II (2024) [4K UHD]',
        type: 'movie',
        status: 'queued',
        progress: 0,
        speed_down: '0 KB/s',
        speed_up: '0 KB/s',
        size_total: '18.5 GB',
        size_downloaded: '0 MB',
        eta: 'Queued',
        destination: 'D:/MediaLibrary/Movies/Gladiator II (2024)',
        peers: 'Waiting in line',
    }
]);

const filteredItems = computed(() => {
    if (activeFilter.value === 'all') return items.value;
    return items.value.filter(i => i.status === activeFilter.value);
});

const totalSpeedDown = computed(() => {
    return '31.2 MB/s';
});
const totalSpeedUp = computed(() => {
    return '2.47 MB/s';
});

const handleAddDownload = () => {
    if (!newTitle.value) return;

    items.value.unshift({
        id: Date.now(),
        title: newTitle.value,
        type: newType.value,
        status: 'downloading',
        progress: 1,
        speed_down: '14.5 MB/s',
        speed_up: '0 KB/s',
        size_total: '4.5 GB',
        size_downloaded: '45 MB',
        eta: '4m 10s',
        destination: `D:/MediaLibrary/${newType.value === 'movie' ? 'Movies' : 'TV Shows'}/${newTitle.value}`,
        peers: 'Connecting...',
    });

    newTitle.value = '';
    newUrl.value = '';
    showAddModal.value = false;
};

const togglePause = (item: any) => {
    if (item.status === 'downloading') {
        item.status = 'queued';
        item.speed_down = '0 KB/s';
    } else if (item.status === 'queued') {
        item.status = 'downloading';
        item.speed_down = '12.0 MB/s';
    }
};

const removeItem = (id: number) => {
    items.value = items.value.filter(i => i.id !== id);
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
                        {{ isRTL ? 'إدارة التنزيلات النشطة، مراقبة مجلدات الاستقبال، والفهرسة الآلية للوسائط.' : 'Manage download queues, torrent streams, incoming watch folder triggers, and auto-indexing.' }}
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
                    <span class="text-xl font-black text-cyan-300 font-mono mt-1 block">{{ totalSpeedDown }}</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                    <ArrowDown class="w-5 h-5 animate-bounce" />
                </div>
            </div>

            <div class="glass-panel rounded-2xl p-4 border border-indigo-500/30 bg-indigo-500/5 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-400 block">{{ isRTL ? 'سرعة الرفع / المشاركة' : 'Upload / Seeding' }}</span>
                    <span class="text-xl font-black text-indigo-300 font-mono mt-1 block">{{ totalSpeedUp }}</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center">
                    <ArrowUp class="w-5 h-5" />
                </div>
            </div>

            <div class="glass-panel rounded-2xl p-4 border border-white/10 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-400 block">{{ isRTL ? 'التنزيلات النشطة' : 'Active Tasks' }}</span>
                    <span class="text-xl font-black text-white font-mono mt-1 block">2 Downloading / 1 Queued</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-white/5 text-slate-300 flex items-center justify-center">
                    <Radio class="w-5 h-5 text-amber-400" />
                </div>
            </div>

            <div class="glass-panel rounded-2xl p-4 border border-emerald-500/30 bg-emerald-500/5 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-400 block">{{ isRTL ? 'المكتملة اليوم' : 'Completed' }}</span>
                    <span class="text-xl font-black text-emerald-300 font-mono mt-1 block">12 Media Items (100%)</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                    <CheckCircle2 class="w-5 h-5" />
                </div>
            </div>
        </div>

        <!-- 3 Interactive Workflow Explanatory Feature Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="glass-card rounded-2xl p-4 border border-white/10 space-y-2 hover:border-cyan-500/30 transition-all">
                <div class="flex items-center gap-2 text-cyan-400 font-extrabold text-xs">
                    <Sparkles class="w-4 h-4" />
                    <span>1. {{ isRTL ? 'التنزيل والاستقبال' : 'Direct & Magnet Ingestion' }}</span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                    {{ isRTL ? 'دعم روابط Magnet، ملفات التورنت، والتحميل المباشر من السيرفرات السحابية بأقصى سرعة اتصال.' : 'Full support for magnet URI streams, torrent payloads, and direct HTTP transfers.' }}
                </p>
            </div>

            <div class="glass-card rounded-2xl p-4 border border-white/10 space-y-2 hover:border-indigo-500/30 transition-all">
                <div class="flex items-center gap-2 text-indigo-400 font-extrabold text-xs">
                    <FolderSync class="w-4 h-4" />
                    <span>2. {{ isRTL ? 'فك الضغط التلقائي' : 'Auto Unpack & Extract' }}</span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                    {{ isRTL ? 'يتم فك ضغط الأرشيفات المجزأة (.rar, .zip, .7z) تلقائياً بمجرد اكتمال التنزيل دون تدخل يدوي.' : 'Multipart archives (.rar, .zip, .7z) are unpacked automatically upon completion.' }}
                </p>
            </div>

            <div class="glass-card rounded-2xl p-4 border border-white/10 space-y-2 hover:border-emerald-500/30 transition-all">
                <div class="flex items-center gap-2 text-emerald-400 font-extrabold text-xs">
                    <HardDrive class="w-4 h-4" />
                    <span>3. {{ isRTL ? 'الفهرسة والترجمة الفورية' : 'Auto Library Indexing' }}</span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                    {{ isRTL ? 'المجلد المراقب يرسل إشارة فورية للفاحص لجلب البوستر العربي والإنكليزي وتنزيل ملفات الترجمة.' : 'Watch folders trigger instant background indexing, poster enrichment, and subtitle sync.' }}
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
                            { key: 'all', label: isRTL ? 'الكل' : 'All' },
                            { key: 'downloading', label: isRTL ? 'جاري التنزيل' : 'Downloading' },
                            { key: 'queued', label: isRTL ? 'في الطابور' : 'Queued' },
                            { key: 'completed', label: isRTL ? 'المكتملة' : 'Completed' },
                        ]"
                        :key="filter.key"
                        @click="activeFilter = filter.key as any"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        :class="activeFilter === filter.key
                            ? 'bg-cyan-500 text-slate-950 font-black shadow-lg shadow-cyan-500/20'
                            : 'bg-white/5 text-slate-400 hover:bg-white/10 border border-white/10'"
                    >
                        {{ filter.label }}
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
                                :class="item.type === 'movie' ? 'bg-cyan-500/20 border-cyan-500/40 text-cyan-400' : 'bg-indigo-500/20 border-indigo-500/40 text-indigo-400'"
                            >
                                <Film v-if="item.type === 'movie'" class="w-5 h-5" />
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
                                            : 'bg-amber-500/20 text-amber-300 border-amber-500/30'"
                                    >
                                        {{ item.status }}
                                    </span>
                                </div>
                                <div class="text-[11px] font-mono text-slate-500 truncate mt-0.5" :title="item.destination">
                                    📂 {{ item.destination }}
                                </div>
                            </div>
                        </div>

                        <!-- Action Toolbar -->
                        <div class="flex items-center gap-2">
                            <button
                                v-if="item.status !== 'completed'"
                                @click="togglePause(item)"
                                class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white cursor-pointer"
                                :title="item.status === 'downloading' ? 'Pause' : 'Resume'"
                            >
                                <Pause v-if="item.status === 'downloading'" class="w-4 h-4" />
                                <Play v-else class="w-4 h-4" />
                            </button>

                            <button
                                v-if="item.status === 'completed'"
                                @click="play({ id: item.id, title: item.title })"
                                class="px-3 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-black text-xs flex items-center gap-1.5 cursor-pointer shadow-sm"
                            >
                                <Play class="w-3.5 h-3.5 fill-current" />
                                <span>{{ isRTL ? 'تشغيل' : 'Play' }}</span>
                            </button>

                            <button
                                @click="removeItem(item.id)"
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
                                class="h-full rounded-full transition-all duration-300"
                                :class="item.status === 'completed'
                                    ? 'bg-emerald-500'
                                    : 'bg-gradient-to-r from-cyan-400 to-blue-500'"
                                :style="{ width: `${item.progress}%` }"
                            ></div>
                        </div>

                        <div class="flex items-center justify-between text-[11px] font-mono text-slate-400">
                            <span>{{ item.size_downloaded }} / {{ item.size_total }} ({{ item.progress }}%)</span>
                            <div class="flex items-center gap-4">
                                <span v-if="item.status === 'downloading'" class="text-cyan-400 font-bold">↓ {{ item.speed_down }}</span>
                                <span class="text-slate-500 font-normal">⏱️ {{ item.eta }}</span>
                                <span class="text-slate-500 font-normal">👥 {{ item.peers }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-16 space-y-3">
                <CheckCircle2 class="w-12 h-12 text-slate-600 mx-auto" />
                <h4 class="font-extrabold text-base text-white">
                    {{ isRTL ? 'لا توجد تنزيلات في هذه الفئة حالياً' : 'No downloads in this queue tab' }}
                </h4>
                <p class="text-xs text-slate-400 max-w-sm mx-auto">
                    {{ isRTL ? 'يمكنك إضافة روابط مغناطيسية أو إرسال ملفات تورنت إلى مجلد المراقبة.' : 'Add new magnet URLs or drop torrent files into your monitored watch directory.' }}
                </p>
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
                            placeholder="e.g. Gladiator II (2024)"
                            class="w-full h-11 rounded-xl bg-white/[0.04] border border-white/15 px-4 text-xs text-white focus:border-cyan-500 outline-none"
                        />
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-400 mb-1 block">{{ isRTL ? 'رابط Magnet أو مسار التحميل المباشر' : 'Magnet URI / Stream URL' }}</label>
                        <input
                            type="text"
                            v-model="newUrl"
                            placeholder="magnet:?xt=urn:btih:..."
                            class="w-full h-11 rounded-xl bg-white/[0.04] border border-white/15 px-4 text-xs font-mono text-cyan-300 focus:border-cyan-500 outline-none"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
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

                        <div>
                            <label class="text-xs font-bold text-slate-400 mb-1 block">{{ isRTL ? 'الجودة المستهدفة' : 'Quality' }}</label>
                            <select
                                v-model="newQuality"
                                class="w-full h-9 rounded-xl bg-white/[0.04] border border-white/15 px-3 text-xs text-white focus:border-cyan-500 outline-none"
                            >
                                <option value="4K" class="bg-slate-900">4K UHD HDR</option>
                                <option value="1080p" class="bg-slate-900">1080p BluRay</option>
                                <option value="720p" class="bg-slate-900">720p HD</option>
                            </select>
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
                        @click="handleAddDownload"
                        class="px-6 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-black cursor-pointer shadow-lg shadow-cyan-500/20"
                    >
                        {{ isRTL ? 'بدء التنزيل والفهرسة' : 'Start Ingestion' }}
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
                        <span class="font-bold text-cyan-400 block">1. الاستقبال والتنزيل المباشر</span>
                        <p class="text-slate-400">تدعم المنظومة إضافة روابط التورنت والمغناطيس مع إدارة كاملة للسرعات والتوزيع الجغرافي للأقران.</p>
                    </div>

                    <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-1">
                        <span class="font-bold text-indigo-400 block">2. مراقبة المجلدات والاستخراج</span>
                        <p class="text-slate-400">يتم فحص مجلد التنزيلات دورياً، وفي حال وجود ملفات مضغوطة .rar يتم استخراجها تلقائياً إلى مجلد الوسائط المؤقت.</p>
                    </div>

                    <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-1">
                        <span class="font-bold text-emerald-400 block">3. المطابقة والدمج السينمائي</span>
                        <p class="text-slate-400">يرسل خادم التنزيل إشعاراً لفاحص المكتبة (Virtual Scanner) ليقوم بمطابقة الفيلم وتنزيل بوسترات 4K والترجمات العربية فوراً دون انتظار.</p>
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
