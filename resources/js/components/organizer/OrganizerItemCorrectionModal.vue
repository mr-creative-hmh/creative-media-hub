<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    X, Edit3, RotateCcw, Check, Film, Tv, Folder,
    Calendar, Hash, Tag, Layers, Subtitles, AlertCircle, ArrowRight,
    Search, ChevronDown, Sparkles
} from 'lucide-vue-next';

export interface PlanItem {
    id: string;
    source_path: string;
    destination_path: string;
    filename: string;
    clean_title: string;
    collection_name?: string | null;
    collection_id?: number | null;
    genre?: string | null;
    type: 'movie' | 'series';
    year?: string | number | null;
    season?: number | null;
    episode?: number | null;
    episode_title?: string | null;
    resolution?: string | null;
    size_bytes?: number;
    size_formatted?: string;
    status: string;
    selected: boolean;
    subtitles?: Array<{
        source: string;
        destination: string;
        language?: string;
        exists?: boolean;
    }>;
    is_manually_edited?: boolean;
    original_item?: any;
}

const props = defineProps<{
    isOpen: boolean;
    item: PlanItem | null;
    movieTemplate: string;
    seriesTemplate: string;
    targetRoot: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save', updatedItem: PlanItem): void;
    (e: 'reset', item: PlanItem): void;
}>();

const { t, isRTL } = useI18n();

// Form state
const type = ref<'movie' | 'series'>('movie');
const customCategory = ref('');
const showCustomCategoryInput = ref(false);

const cleanTitle = ref('');
const year = ref('');
const season = ref<number | string>('');
const episode = ref<number | string>('');
const episodeTitle = ref('');
const collectionName = ref('');
const genre = ref('');
const resolution = ref('');
const customPathOverride = ref(false);
const manualDestinationPath = ref('');

// Popular genres dropdown suggestions
const commonGenres = [
    'Action', 'Adventure', 'Animation', 'Comedy', 'Crime',
    'Documentary', 'Drama', 'Family', 'Fantasy', 'History',
    'Horror', 'Music', 'Mystery', 'Romance', 'Science Fiction',
    'Thriller', 'War', 'Western'
];

const isGenreDropdownOpen = ref(false);

const filteredGenres = computed(() => {
    if (!genre.value) return commonGenres;
    const q = genre.value.trim().toLowerCase();
    const matched = commonGenres.filter(g => g.toLowerCase().includes(q));
    return matched.length > 0 ? matched : commonGenres;
});

const isGenreActive = (g: string) => {
    if (!genre.value) return false;
    const current = genre.value.trim().toLowerCase();
    return current === g.toLowerCase() || current.split(',').map(s => s.trim().toLowerCase()).includes(g.toLowerCase());
};

const selectGenre = (g: string) => {
    if (genre.value.trim().toLowerCase() === g.toLowerCase()) {
        genre.value = '';
    } else {
        genre.value = g;
    }
    isGenreDropdownOpen.value = false;
};

const clearGenre = () => {
    genre.value = '';
    isGenreDropdownOpen.value = false;
};

const setCategoryPreset = (categoryType: 'movie' | 'series', categoryFolder = '') => {
    type.value = categoryType;
    customCategory.value = categoryFolder;
    showCustomCategoryInput.value = false;
};

watch(() => props.item, (newItem) => {
    if (newItem) {
        type.value = newItem.type || 'movie';
        // Auto-detect custom category folder if item was placed in specialized path
        if (newItem.destination_path) {
            const norm = newItem.destination_path.replace(/\\/g, '/');
            if (norm.includes('/TV Shows/Arabic Series/')) {
                customCategory.value = 'TV Shows/Arabic Series';
            } else if (norm.includes('/Anime/')) {
                customCategory.value = 'Anime';
            } else if (norm.includes('/Documentaries/')) {
                customCategory.value = 'Documentaries';
            } else {
                customCategory.value = '';
            }
        } else {
            customCategory.value = '';
        }
        showCustomCategoryInput.value = !!customCategory.value && !['TV Shows/Arabic Series', 'Anime', 'Documentaries'].includes(customCategory.value);

        cleanTitle.value = newItem.clean_title || '';
        year.value = newItem.year ? String(newItem.year) : '';
        season.value = newItem.season !== null && newItem.season !== undefined ? newItem.season : 1;
        episode.value = newItem.episode !== null && newItem.episode !== undefined ? newItem.episode : 1;
        episodeTitle.value = newItem.episode_title || '';
        collectionName.value = newItem.collection_name || '';
        genre.value = newItem.genre || '';
        resolution.value = newItem.resolution || '1080p';
        manualDestinationPath.value = newItem.destination_path || '';
        customPathOverride.value = !!newItem.is_manually_edited && !isPathGeneratedFromTemplate(newItem.destination_path);
    }
}, { immediate: true });

function sanitizeSegment(val: string): string {
    return val.replace(/[<>:"/\\|?*]/g, '').trim();
}

function sanitizePathCategory(val: string): string {
    // Preserve forward slashes for nested categories like 'TV Shows/Arabic Series'
    return val
        .replace(/\\/g, '/')
        .replace(/[<>:"|?*]/g, '')
        .split('/')
        .map(s => s.trim())
        .filter(Boolean)
        .join('/');
}

function isPathGeneratedFromTemplate(destPath: string): boolean {
    return true;
}

// Compute live recalculated destination path
const calculatedDestination = computed(() => {
    if (customPathOverride.value && manualDestinationPath.value.trim()) {
        return manualDestinationPath.value.trim().replace(/\\/g, '/');
    }

    if (!props.item) return '';

    const root = (props.targetRoot || 'H:/Entertainment').replace(/\\/g, '/').replace(/\/+$/, '');
    const isSeries = type.value === 'series';
    const pattern = isSeries ? props.seriesTemplate : props.movieTemplate;

    const ext = props.item.filename.split('.').pop() || 'mkv';
    const sTitle = sanitizeSegment(cleanTitle.value || 'Unknown');
    const sYear = sanitizeSegment(year.value || '');
    const sColl = sanitizeSegment(collectionName.value || '');
    const sGenre = sanitizeSegment(genre.value || '');
    const sRes = sanitizeSegment(resolution.value || '1080p');
    const sEpTitle = sanitizeSegment(episodeTitle.value || '');
    const sNum = parseInt(String(season.value), 10) || 1;
    const epNum = parseInt(String(episode.value), 10) || 1;

    const effectiveTypeDir = customCategory.value.trim()
        ? sanitizePathCategory(customCategory.value.trim())
        : (isSeries ? 'TV Shows' : 'Movies');

    let path = pattern
        .replace(/{Type}/g, effectiveTypeDir)
        .replace(/{Title}/g, sTitle)
        .replace(/{CleanResolution}/g, sRes)
        .replace(/{Resolution}/g, sRes)
        .replace(/{ext}/g, ext);

    if (sColl) {
        path = path.replace(/{Collection}/g, sColl);
    } else {
        path = path
            .replace(/\/Collections\/{Collection}/g, '')
            .replace(/Collections\/{Collection}\//g, '')
            .replace(/\/{Collection}/g, '')
            .replace(/{Collection}\//g, '')
            .replace(/{Collection}/g, '');
    }

    if (sGenre) {
        path = path.replace(/{Genre}/g, sGenre);
    } else {
        path = path.replace(/\/{Genre}/g, '').replace(/{Genre}\//g, '').replace(/{Genre}/g, '');
    }

    if (sYear) {
        path = path.replace(/{Year}/g, sYear);
    } else {
        path = path.replace(/\s*\({Year}\)/g, '').replace(/\({Year}\)/g, '').replace(/{Year}/g, '');
    }

    if (isSeries) {
        path = path
            .replace(/{Season:02}/g, String(sNum).padStart(2, '0'))
            .replace(/{Episode:02}/g, String(epNum).padStart(2, '0'));

        if (sEpTitle) {
            path = path.replace(/{EpisodeTitle}/g, sEpTitle);
        } else {
            path = path.replace(/\s*-\s*{EpisodeTitle}/g, '').replace(/{EpisodeTitle}/g, '');
        }
    }

    // Clean double slashes and empty brackets
    path = path.replace(/\/+/g, '/').replace(/\s+/g, ' ').trim();
    path = path.replace(/\[\s*\]/g, '').replace(/\(\s*\)/g, '').replace(/\s+\./g, '.');

    return `${root}/${path}`.replace(/\/+/g, '/');
});

// Calculate companion subtitles destination based on updated destination path
const updatedSubtitles = computed(() => {
    if (!props.item?.subtitles || props.item.subtitles.length === 0) return [];

    const dest = calculatedDestination.value;
    const destDir = dest.substring(0, dest.lastIndexOf('/'));
    const fullFileName = dest.substring(dest.lastIndexOf('/') + 1);
    const destBase = fullFileName.substring(0, fullFileName.lastIndexOf('.')) || fullFileName;

    return props.item.subtitles.map(sub => {
        const lang = sub.language || 'und';
        const langSuffix = ['ar', 'en', 'fr', 'es', 'de'].includes(lang) ? `.${lang}` : '';
        const subExt = sub.destination?.split('.').pop() || 'srt';
        return {
            ...sub,
            destination: `${destDir}/${destBase}${langSuffix}.${subExt}`
        };
    });
});

function handleSave() {
    if (!props.item) return;

    // Preserve original_item for reset capability if not already stored
    const original = props.item.original_item || JSON.parse(JSON.stringify(props.item));

    const updated: PlanItem = {
        ...props.item,
        original_item: original,
        is_manually_edited: true,
        type: type.value,
        clean_title: cleanTitle.value.trim(),
        year: year.value.trim() ? year.value.trim() : null,
        season: type.value === 'series' ? (parseInt(String(season.value), 10) || 1) : null,
        episode: type.value === 'series' ? (parseInt(String(episode.value), 10) || 1) : null,
        episode_title: type.value === 'series' ? episodeTitle.value.trim() : null,
        collection_name: collectionName.value.trim() ? collectionName.value.trim() : null,
        genre: genre.value.trim() ? genre.value.trim() : null,
        resolution: resolution.value.trim() ? resolution.value.trim() : props.item.resolution,
        destination_path: calculatedDestination.value,
        subtitles: updatedSubtitles.value,
        status: 'ready',
        selected: true,
    };

    emit('save', updated);
    emit('close');
}

function handleReset() {
    if (!props.item) return;
    if (props.item.original_item) {
        emit('reset', props.item.original_item);
    }
    emit('close');
}
</script>

<template>
    <div
        v-if="isOpen && item"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fade-in"
        :dir="isRTL ? 'rtl' : 'ltr'"
    >
        <div class="relative w-full max-w-3xl max-h-[90vh] bg-slate-900 border border-white/10 rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <!-- Modal Header -->
            <div class="px-6 py-5 border-b border-white/10 flex items-center justify-between bg-slate-950/60">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center border border-cyan-500/30">
                        <Edit3 class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-black text-white flex items-center gap-2">
                            <span>{{ isRTL ? 'تصحيح وتعديل بيانات الملف يدوياً' : 'Manual Item Correction & Path Override' }}</span>
                            <span v-if="item.is_manually_edited" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                {{ isRTL ? 'تم التعديل سابقاً' : 'Edited' }}
                            </span>
                        </h3>
                        <p class="text-xs text-slate-400 font-mono truncate max-w-lg" :title="item.filename">
                            {{ item.filename }}
                        </p>
                    </div>
                </div>

                <button
                    @click="$emit('close')"
                    class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="p-6 overflow-y-auto space-y-6 flex-1 text-xs">
                <!-- Source Path Info -->
                <div class="p-3.5 rounded-2xl bg-black/40 border border-white/5 space-y-1">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                        {{ isRTL ? 'المسار الأصلي الحالي على القرص:' : 'Current Source Path on Disk:' }}
                    </div>
                    <div class="font-mono text-slate-300 truncate" :title="item.source_path">
                        {{ item.source_path }}
                    </div>
                </div>

                <!-- Media Type & Category Preset Selector -->
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <label class="block font-bold text-slate-300">
                            {{ isRTL ? 'التصنيف / نوع الوسائط ومجلد الوجهة:' : 'Category / Media Type & Target Folder:' }}
                        </label>
                        <button
                            type="button"
                            @click="showCustomCategoryInput = !showCustomCategoryInput"
                            class="text-[11px] text-cyan-400 hover:text-cyan-300 font-bold transition-colors cursor-pointer"
                        >
                            {{ showCustomCategoryInput ? (isRTL ? 'إخفاء التخصيص' : 'Hide Custom') : (isRTL ? 'تخصيص مجلد الفئة' : 'Custom Category Folder') }}
                        </button>
                    </div>

                    <!-- Category Preset Buttons -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                        <!-- Movies -->
                        <button
                            type="button"
                            @click="setCategoryPreset('movie')"
                            :class="[
                                'p-2.5 rounded-xl border font-bold flex flex-col items-center justify-center gap-1.5 transition-all cursor-pointer',
                                type === 'movie' && !customCategory
                                    ? 'bg-cyan-500/20 text-cyan-300 border-cyan-400 shadow-md ring-1 ring-cyan-500/50'
                                    : 'bg-white/5 text-slate-400 border-white/10 hover:text-white hover:bg-white/10'
                            ]"
                        >
                            <Film class="w-4 h-4" />
                            <span class="text-[11px]">{{ isRTL ? 'أفلام' : 'Movies' }}</span>
                        </button>

                        <!-- TV Series -->
                        <button
                            type="button"
                            @click="setCategoryPreset('series')"
                            :class="[
                                'p-2.5 rounded-xl border font-bold flex flex-col items-center justify-center gap-1.5 transition-all cursor-pointer',
                                type === 'series' && !customCategory
                                    ? 'bg-purple-500/20 text-purple-300 border-purple-400 shadow-md ring-1 ring-purple-500/50'
                                    : 'bg-white/5 text-slate-400 border-white/10 hover:text-white hover:bg-white/10'
                            ]"
                        >
                            <Tv class="w-4 h-4" />
                            <span class="text-[11px]">{{ isRTL ? 'مسلسلات' : 'TV Shows' }}</span>
                        </button>

                        <!-- Arabic Series -->
                        <button
                            type="button"
                            @click="setCategoryPreset('series', 'TV Shows/Arabic Series')"
                            :class="[
                                'p-2.5 rounded-xl border font-bold flex flex-col items-center justify-center gap-1.5 transition-all cursor-pointer',
                                customCategory === 'TV Shows/Arabic Series'
                                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-400 shadow-md ring-1 ring-emerald-500/50'
                                    : 'bg-white/5 text-slate-400 border-white/10 hover:text-white hover:bg-white/10'
                            ]"
                        >
                            <span class="text-sm">🕌</span>
                            <span class="text-[11px]">{{ isRTL ? 'مسلسلات عربية' : 'Arabic Series' }}</span>
                        </button>

                        <!-- Anime -->
                        <button
                            type="button"
                            @click="setCategoryPreset('series', 'Anime')"
                            :class="[
                                'p-2.5 rounded-xl border font-bold flex flex-col items-center justify-center gap-1.5 transition-all cursor-pointer',
                                customCategory === 'Anime'
                                    ? 'bg-rose-500/20 text-rose-300 border-rose-400 shadow-md ring-1 ring-rose-500/50'
                                    : 'bg-white/5 text-slate-400 border-white/10 hover:text-white hover:bg-white/10'
                            ]"
                        >
                            <span class="text-sm">🎌</span>
                            <span class="text-[11px]">{{ isRTL ? 'أنمي' : 'Anime' }}</span>
                        </button>

                        <!-- Documentaries -->
                        <button
                            type="button"
                            @click="setCategoryPreset('movie', 'Documentaries')"
                            :class="[
                                'p-2.5 rounded-xl border font-bold flex flex-col items-center justify-center gap-1.5 transition-all cursor-pointer col-span-2 sm:col-span-1',
                                customCategory === 'Documentaries'
                                    ? 'bg-amber-500/20 text-amber-300 border-amber-400 shadow-md ring-1 ring-amber-500/50'
                                    : 'bg-white/5 text-slate-400 border-white/10 hover:text-white hover:bg-white/10'
                            ]"
                        >
                            <span class="text-sm">📽️</span>
                            <span class="text-[11px]">{{ isRTL ? 'وثائقيات' : 'Documentary' }}</span>
                        </button>
                    </div>

                    <!-- Optional Custom Category Folder Input -->
                    <div v-if="showCustomCategoryInput" class="pt-1">
                        <input
                            v-model="customCategory"
                            type="text"
                            class="w-full bg-slate-950 border border-cyan-500/40 rounded-xl px-3.5 py-2 text-cyan-300 font-mono text-xs focus:outline-none focus:border-cyan-400"
                            :placeholder="type === 'series' ? 'e.g. TV Shows/Anime or Cartoons' : 'e.g. Movies/4K UHD or Animation'"
                        />
                    </div>
                </div>

                <!-- Core Metadata Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Title -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block font-bold text-slate-300">
                            {{ type === 'series' ? (isRTL ? 'اسم المسلسل النظيف:' : 'Clean Series Title:') : (isRTL ? 'اسم الفيلم النظيف:' : 'Clean Movie Title:') }}
                        </label>
                        <div class="relative">
                            <input
                                v-model="cleanTitle"
                                type="text"
                                class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400"
                                :placeholder="type === 'series' ? 'e.g. Breaking Bad' : 'e.g. Inception'"
                            />
                        </div>
                    </div>

                    <!-- Year -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-300">
                            {{ isRTL ? 'سنة الإنتاج (Year):' : 'Release Year:' }}
                        </label>
                        <input
                            v-model="year"
                            type="text"
                            maxlength="4"
                            class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400 font-mono"
                            placeholder="e.g. 2024"
                        />
                    </div>

                    <!-- Genre (Interactive Search & Autocomplete) -->
                    <div class="space-y-1.5 relative">
                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-slate-300">
                                {{ isRTL ? 'التصنيف الأساسي (Genre):' : 'Primary Genre:' }}
                            </label>
                            <button
                                v-if="genre"
                                type="button"
                                @click="clearGenre"
                                class="text-[10px] text-red-400 hover:text-red-300 font-bold transition-colors cursor-pointer"
                            >
                                {{ isRTL ? 'مسح التصنيف' : 'Clear' }}
                            </button>
                        </div>

                        <!-- Genre Input with Quick Clear and Dropdown Toggle -->
                        <div class="relative">
                            <input
                                v-model="genre"
                                @focus="isGenreDropdownOpen = true"
                                type="text"
                                class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 pr-8 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400"
                                placeholder="e.g. Action, Drama, Sci-Fi"
                            />
                            <button
                                v-if="genre"
                                type="button"
                                @click="clearGenre"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-white cursor-pointer"
                            >
                                <X class="w-3.5 h-3.5" />
                            </button>
                            <button
                                v-else
                                type="button"
                                @click="isGenreDropdownOpen = !isGenreDropdownOpen"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-white cursor-pointer"
                            >
                                <ChevronDown class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <!-- Filtered Dropdown Popover -->
                        <div
                            v-if="isGenreDropdownOpen"
                            class="absolute z-20 left-0 right-0 top-full mt-1 bg-slate-950 border border-white/20 rounded-2xl shadow-2xl p-2 max-h-48 overflow-y-auto space-y-1 backdrop-blur-xl"
                        >
                            <button
                                v-for="g in filteredGenres"
                                :key="g"
                                type="button"
                                @click="selectGenre(g)"
                                class="w-full text-left px-3 py-1.5 rounded-lg flex items-center justify-between text-xs transition-colors cursor-pointer"
                                :class="isGenreActive(g) ? 'bg-cyan-500/20 text-cyan-300 font-bold' : 'text-slate-300 hover:bg-white/10'"
                            >
                                <span>{{ g }}</span>
                                <Check v-if="isGenreActive(g)" class="w-3.5 h-3.5 text-cyan-400" />
                            </button>
                        </div>
                    </div>

                    <!-- Quick Select Genre Badges / Chips -->
                    <div class="space-y-1.5 md:col-span-2 pt-1">
                        <div class="text-[11px] font-bold text-slate-400 flex items-center gap-1.5">
                            <Tag class="w-3.5 h-3.5 text-cyan-400" />
                            <span>{{ isRTL ? 'تصنيفات شائعة للاختيار السريع بنقرة واحدة:' : 'Popular Genres (Click to Select / Toggle):' }}</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="g in commonGenres"
                                :key="g"
                                type="button"
                                @click="selectGenre(g)"
                                :class="[
                                    'px-2.5 py-1 rounded-xl text-[11px] border font-bold transition-all cursor-pointer flex items-center gap-1.5',
                                    isGenreActive(g)
                                        ? 'bg-cyan-500/25 border-cyan-400 text-cyan-300 shadow-md ring-1 ring-cyan-500/50 scale-105'
                                        : 'bg-white/5 border-white/10 text-slate-400 hover:text-white hover:bg-white/10'
                                ]"
                            >
                                <span>{{ g }}</span>
                                <Check v-if="isGenreActive(g)" class="w-3 h-3 text-cyan-400" />
                            </button>
                        </div>
                    </div>

                    <!-- Series Specific Fields -->
                    <template v-if="type === 'series'">
                        <div class="space-y-1.5">
                            <label class="block font-bold text-slate-300">
                                {{ isRTL ? 'رقم الموسم (Season):' : 'Season Number:' }}
                            </label>
                            <input
                                v-model.number="season"
                                type="number"
                                min="1"
                                max="99"
                                class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400 font-mono"
                                placeholder="1"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block font-bold text-slate-300">
                                {{ isRTL ? 'رقم الحلقة (Episode):' : 'Episode Number:' }}
                            </label>
                            <input
                                v-model.number="episode"
                                type="number"
                                min="1"
                                max="999"
                                class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400 font-mono"
                                placeholder="1"
                            />
                        </div>

                        <div class="space-y-1.5 md:col-span-2">
                            <label class="block font-bold text-slate-300">
                                {{ isRTL ? 'عنوان الحلقة (اختياري):' : 'Episode Title (Optional):' }}
                            </label>
                            <input
                                v-model="episodeTitle"
                                type="text"
                                class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400"
                                placeholder="e.g. Pilot, Ozymandias"
                            />
                        </div>
                    </template>

                    <!-- Movie Specific: Collection -->
                    <div v-if="type === 'movie'" class="space-y-1.5 md:col-span-2">
                        <label class="block font-bold text-slate-300 flex items-center justify-between">
                            <span>{{ isRTL ? 'مجموعة / سلسلة الفيلم (Collection / Franchise):' : 'Movie Collection / Franchise:' }}</span>
                            <span class="text-[10px] text-slate-500 font-normal">
                                {{ isRTL ? 'اتركه فارغاً إذا كان فيلماً منفرداً' : 'Leave blank for standalone films' }}
                            </span>
                        </label>
                        <input
                            v-model="collectionName"
                            type="text"
                            class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400"
                            placeholder="e.g. Harry Potter Collection, Marvel Cinematic Universe"
                        />
                    </div>
                </div>

                <!-- Custom Path Override Toggle -->
                <div class="pt-2 border-t border-white/10 space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                v-model="customPathOverride"
                                class="rounded border-white/20 bg-slate-950 text-cyan-500 focus:ring-0 cursor-pointer"
                            />
                            <span class="font-bold text-slate-200">
                                {{ isRTL ? 'تخصيص مسار الوجهة يدوياً بالكامل (Direct Path Override)' : 'Direct Path Override (Manual Custom Destination)' }}
                            </span>
                        </label>
                    </div>

                    <div v-if="customPathOverride">
                        <input
                            v-model="manualDestinationPath"
                            type="text"
                            class="w-full bg-slate-950 border border-cyan-500/50 rounded-xl px-3.5 py-2.5 text-cyan-300 font-mono text-xs focus:outline-none focus:border-cyan-400"
                            :placeholder="calculatedDestination"
                        />
                    </div>
                </div>

                <!-- Live Result Destination Preview -->
                <div class="p-4 rounded-2xl bg-cyan-950/20 border border-cyan-500/30 space-y-2.5">
                    <div class="flex items-center justify-between text-[11px] font-bold text-cyan-300">
                        <span class="flex items-center gap-1.5">
                            <ArrowRight class="w-3.5 h-3.5" :class="isRTL ? 'rotate-180' : ''" />
                            <span>{{ isRTL ? 'مسار الوجهة المنظم النهائي المتوقع:' : 'Calculated Final Destination Path:' }}</span>
                        </span>
                        <!-- Quick Token Badges -->
                        <div class="flex items-center gap-1.5 font-mono text-[10px]">
                            <span class="px-2 py-0.5 rounded-md bg-cyan-500/15 border border-cyan-400/30 text-cyan-300">
                                {{ customCategory || (type === 'series' ? 'TV Shows' : 'Movies') }}
                            </span>
                            <span v-if="genre" class="px-2 py-0.5 rounded-md bg-purple-500/15 border border-purple-400/30 text-purple-300">
                                {{ genre }}
                            </span>
                        </div>
                    </div>
                    <div class="font-mono text-xs text-white break-all bg-black/40 p-2.5 rounded-xl border border-white/5 select-all">
                        {{ calculatedDestination }}
                    </div>
                </div>

                <!-- Companion Subtitles Preview -->
                <div v-if="updatedSubtitles.length > 0" class="space-y-2">
                    <div class="text-[11px] font-bold text-slate-300 flex items-center gap-1.5">
                        <Subtitles class="w-3.5 h-3.5 text-cyan-400" />
                        <span>{{ isRTL ? `ملفات الترجمة المرافقة المتزامنة تلقائياً (${updatedSubtitles.length}):` : `Companion Subtitles Auto-Synced (${updatedSubtitles.length}):` }}</span>
                    </div>
                    <div class="space-y-1.5">
                        <div
                            v-for="(sub, idx) in updatedSubtitles"
                            :key="idx"
                            class="p-2.5 rounded-xl bg-black/30 border border-white/5 font-mono text-[11px] flex items-center justify-between gap-2 text-slate-300"
                        >
                            <span class="truncate flex-1" :title="sub.destination">{{ sub.destination }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 shrink-0">
                                {{ sub.language || 'und' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-white/10 bg-slate-950/80 flex items-center justify-between">
                <div>
                    <button
                        v-if="item.is_manually_edited"
                        @click="handleReset"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 border border-amber-500/30 flex items-center gap-1.5 transition-colors cursor-pointer"
                    >
                        <RotateCcw class="w-3.5 h-3.5" />
                        <span>{{ isRTL ? 'استعادة الاقتراح التلقائي' : 'Reset to Auto Proposal' }}</span>
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="$emit('close')"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                    >
                        {{ isRTL ? 'إلغاء' : 'Cancel' }}
                    </button>

                    <button
                        @click="handleSave"
                        type="button"
                        class="px-5 py-2.5 rounded-xl text-xs font-black bg-cyan-500 hover:bg-cyan-400 text-slate-950 shadow-lg shadow-cyan-500/25 flex items-center gap-1.5 transition-all hover:scale-105 active:scale-95 cursor-pointer"
                    >
                        <Check class="w-4 h-4" />
                        <span>{{ isRTL ? 'حفظ وتطبيق التصحيح' : 'Apply Corrections' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

