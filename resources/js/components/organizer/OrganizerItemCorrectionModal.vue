<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    X,
    Edit3,
    RotateCcw,
    Check,
    Film,
    Tv,
    Tag,
    Subtitles,
    ArrowRight,
    ChevronDown,
} from '@lucide/vue';

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

const { isRTL } = useI18n();

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
    'Action',
    'Adventure',
    'Animation',
    'Comedy',
    'Crime',
    'Documentary',
    'Drama',
    'Family',
    'Fantasy',
    'History',
    'Horror',
    'Music',
    'Mystery',
    'Romance',
    'Science Fiction',
    'Thriller',
    'War',
    'Western',
];

const isGenreDropdownOpen = ref(false);

const filteredGenres = computed(() => {
    if (!genre.value) return commonGenres;
    const q = genre.value.trim().toLowerCase();
    const matched = commonGenres.filter((g) => g.toLowerCase().includes(q));
    return matched.length > 0 ? matched : commonGenres;
});

const isGenreActive = (g: string) => {
    if (!genre.value) return false;
    const current = genre.value.trim().toLowerCase();
    return (
        current === g.toLowerCase() ||
        current
            .split(',')
            .map((s) => s.trim().toLowerCase())
            .includes(g.toLowerCase())
    );
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

const setCategoryPreset = (
    categoryType: 'movie' | 'series',
    categoryFolder = '',
) => {
    type.value = categoryType;
    customCategory.value = categoryFolder;
    showCustomCategoryInput.value = false;
};

watch(
    () => props.item,
    (newItem) => {
        if (newItem) {
            type.value = newItem.type || 'movie';
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
            showCustomCategoryInput.value =
                !!customCategory.value &&
                !['TV Shows/Arabic Series', 'Anime', 'Documentaries'].includes(
                    customCategory.value,
                );

            cleanTitle.value = newItem.clean_title || '';
            year.value = newItem.year ? String(newItem.year) : '';
            season.value =
                newItem.season !== null && newItem.season !== undefined
                    ? newItem.season
                    : 1;
            episode.value =
                newItem.episode !== null && newItem.episode !== undefined
                    ? newItem.episode
                    : 1;
            episodeTitle.value = newItem.episode_title || '';
            collectionName.value = newItem.collection_name || '';
            genre.value = newItem.genre || '';
            resolution.value = newItem.resolution || '1080p';
            manualDestinationPath.value = newItem.destination_path || '';
            customPathOverride.value =
                !!newItem.is_manually_edited && !isPathGeneratedFromTemplate();
        }
    },
    { immediate: true },
);

function sanitizeSegment(val: string): string {
    return val.replace(/[<>:"/\\|?*]/g, '').trim();
}

function sanitizePathCategory(val: string): string {
    return val
        .replace(/\\/g, '/')
        .replace(/[<>:"|?*]/g, '')
        .split('/')
        .map((s) => s.trim())
        .filter(Boolean)
        .join('/');
}

function isPathGeneratedFromTemplate(): boolean {
    return true;
}

// Compute live recalculated destination path
const calculatedDestination = computed(() => {
    if (customPathOverride.value && manualDestinationPath.value.trim()) {
        return manualDestinationPath.value.trim().replace(/\\/g, '/');
    }

    if (!props.item) return '';

    const root = (props.targetRoot || 'H:/Entertainment')
        .replace(/\\/g, '/')
        .replace(/\/+$/, '');
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
        : isSeries
          ? 'TV Shows'
          : 'Movies';

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
        path = path
            .replace(/\/{Genre}/g, '')
            .replace(/{Genre}\//g, '')
            .replace(/{Genre}/g, '');
    }

    if (sYear) {
        path = path.replace(/{Year}/g, sYear);
    } else {
        path = path
            .replace(/\s*\({Year}\)/g, '')
            .replace(/\({Year}\)/g, '')
            .replace(/{Year}/g, '');
    }

    if (isSeries) {
        path = path
            .replace(/{Season:02}/g, String(sNum).padStart(2, '0'))
            .replace(/{Episode:02}/g, String(epNum).padStart(2, '0'));

        if (sEpTitle) {
            path = path.replace(/{EpisodeTitle}/g, sEpTitle);
        } else {
            path = path
                .replace(/\s*-\s*{EpisodeTitle}/g, '')
                .replace(/{EpisodeTitle}/g, '');
        }
    }

    path = path.replace(/\/+/g, '/').replace(/\s+/g, ' ').trim();
    path = path
        .replace(/\[\s*\]/g, '')
        .replace(/\(\s*\)/g, '')
        .replace(/\s+\./g, '.');

    return `${root}/${path}`.replace(/\/+/g, '/');
});

// Calculate companion subtitles destination based on updated destination path
const updatedSubtitles = computed(() => {
    if (!props.item?.subtitles || props.item.subtitles.length === 0) return [];

    const dest = calculatedDestination.value;
    const destDir = dest.substring(0, dest.lastIndexOf('/'));
    const fullFileName = dest.substring(dest.lastIndexOf('/') + 1);
    const destBase =
        fullFileName.substring(0, fullFileName.lastIndexOf('.')) ||
        fullFileName;

    return props.item.subtitles.map((sub) => {
        const lang = sub.language || 'und';
        const langSuffix = ['ar', 'en', 'fr', 'es', 'de'].includes(lang)
            ? `.${lang}`
            : '';
        const subExt = sub.destination?.split('.').pop() || 'srt';
        return {
            ...sub,
            destination: `${destDir}/${destBase}${langSuffix}.${subExt}`,
        };
    });
});

function handleSave() {
    if (!props.item) return;

    const original =
        props.item.original_item || JSON.parse(JSON.stringify(props.item));

    const updated: PlanItem = {
        ...props.item,
        original_item: original,
        is_manually_edited: true,
        type: type.value,
        clean_title: cleanTitle.value.trim(),
        year: year.value.trim() ? year.value.trim() : null,
        season:
            type.value === 'series'
                ? parseInt(String(season.value), 10) || 1
                : null,
        episode:
            type.value === 'series'
                ? parseInt(String(episode.value), 10) || 1
                : null,
        episode_title:
            type.value === 'series' ? episodeTitle.value.trim() : null,
        collection_name: collectionName.value.trim()
            ? collectionName.value.trim()
            : null,
        genre: genre.value.trim() ? genre.value.trim() : null,
        resolution: resolution.value.trim()
            ? resolution.value.trim()
            : props.item.resolution,
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
        class="animate-fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-md"
        :dir="isRTL ? 'rtl' : 'ltr'"
        @click.self="$emit('close')"
    >
        <div
            class="cf-glass-elevated border-border/80 bg-card/95 relative flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl border shadow-2xl"
        >
            <!-- Modal Header -->
            <div
                class="border-border/70 bg-background/40 flex items-center justify-between border-b px-6 py-5"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="border-primary/35 bg-primary/15 text-primary flex h-10 w-10 items-center justify-center rounded-2xl border"
                    >
                        <Edit3 class="h-5 w-5" />
                    </div>
                    <div>
                        <h3
                            class="text-foreground flex items-center gap-2 text-base font-black"
                        >
                            <span>{{
                                isRTL
                                    ? 'تصحيح وتعديل بيانات الملف يدوياً'
                                    : 'Manual Item Correction & Path Override'
                            }}</span>
                            <span
                                v-if="item.is_manually_edited"
                                class="rounded-full border border-amber-500/30 bg-amber-500/20 px-2 py-0.5 text-[10px] font-bold text-amber-300"
                            >
                                {{ isRTL ? 'تم التعديل سابقاً' : 'Edited' }}
                            </span>
                        </h3>
                        <p
                            class="text-muted-foreground max-w-lg truncate font-mono text-xs"
                            :title="item.filename"
                        >
                            {{ item.filename }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="$emit('close')"
                    class="border-border/60 bg-background/80 text-muted-foreground hover:bg-muted hover:text-foreground flex size-8 cursor-pointer items-center justify-center rounded-xl border transition-colors"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div
                class="custom-scrollbar flex-1 space-y-6 overflow-y-auto p-6 text-xs"
            >
                <!-- Source Path Info -->
                <div
                    class="cf-glass border-border/70 space-y-1 rounded-2xl border p-3.5"
                >
                    <div
                        class="text-muted-foreground text-[10px] font-bold tracking-wider uppercase"
                    >
                        {{
                            isRTL
                                ? 'المسار الأصلي الحالي على القرص:'
                                : 'Current Source Path on Disk:'
                        }}
                    </div>
                    <div
                        class="text-foreground truncate font-mono"
                        :title="item.source_path"
                    >
                        {{ item.source_path }}
                    </div>
                </div>

                <!-- Media Type & Category Preset Selector -->
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <label class="text-foreground block font-bold">
                            {{
                                isRTL
                                    ? 'التصنيف / نوع الوسائط ومجلد الوجهة:'
                                    : 'Category / Media Type & Target Folder:'
                            }}
                        </label>
                        <button
                            type="button"
                            @click="
                                showCustomCategoryInput =
                                    !showCustomCategoryInput
                            "
                            class="text-primary cursor-pointer text-[11px] font-bold transition-colors hover:brightness-110"
                        >
                            {{
                                showCustomCategoryInput
                                    ? isRTL
                                        ? 'إخفاء التخصيص'
                                        : 'Hide Custom'
                                    : isRTL
                                      ? 'تخصيص مجلد الفئة'
                                      : 'Custom Category Folder'
                            }}
                        </button>
                    </div>

                    <!-- Category Preset Buttons -->
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        <!-- Movies -->
                        <button
                            type="button"
                            @click="setCategoryPreset('movie')"
                            :class="[
                                'flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border p-2.5 font-bold transition-all',
                                type === 'movie' && !customCategory
                                    ? 'border-primary bg-primary/20 text-primary ring-primary/50 shadow-md ring-1'
                                    : 'cf-glass border-border/70 text-muted-foreground hover:border-primary/40 hover:text-foreground',
                            ]"
                        >
                            <Film class="h-4 w-4" />
                            <span class="text-[11px]">{{
                                isRTL ? 'أفلام' : 'Movies'
                            }}</span>
                        </button>

                        <!-- TV Series -->
                        <button
                            type="button"
                            @click="setCategoryPreset('series')"
                            :class="[
                                'flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border p-2.5 font-bold transition-all',
                                type === 'series' && !customCategory
                                    ? 'border-purple-400 bg-purple-500/20 text-purple-300 shadow-md ring-1 ring-purple-500/50'
                                    : 'cf-glass border-border/70 text-muted-foreground hover:text-foreground hover:border-purple-500/40',
                            ]"
                        >
                            <Tv class="h-4 w-4" />
                            <span class="text-[11px]">{{
                                isRTL ? 'مسلسلات' : 'TV Shows'
                            }}</span>
                        </button>

                        <!-- Arabic Series -->
                        <button
                            type="button"
                            @click="
                                setCategoryPreset(
                                    'series',
                                    'TV Shows/Arabic Series',
                                )
                            "
                            :class="[
                                'flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border p-2.5 font-bold transition-all',
                                customCategory === 'TV Shows/Arabic Series'
                                    ? 'border-emerald-400 bg-emerald-500/20 text-emerald-300 shadow-md ring-1 ring-emerald-500/50'
                                    : 'cf-glass border-border/70 text-muted-foreground hover:text-foreground hover:border-emerald-500/40',
                            ]"
                        >
                            <span class="text-sm">🕌</span>
                            <span class="text-[11px]">{{
                                isRTL ? 'مسلسلات عربية' : 'Arabic Series'
                            }}</span>
                        </button>

                        <!-- Anime -->
                        <button
                            type="button"
                            @click="setCategoryPreset('series', 'Anime')"
                            :class="[
                                'flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border p-2.5 font-bold transition-all',
                                customCategory === 'Anime'
                                    ? 'border-rose-400 bg-rose-500/20 text-rose-300 shadow-md ring-1 ring-rose-500/50'
                                    : 'cf-glass border-border/70 text-muted-foreground hover:text-foreground hover:border-rose-500/40',
                            ]"
                        >
                            <span class="text-sm">🎌</span>
                            <span class="text-[11px]">{{
                                isRTL ? 'أنمي' : 'Anime'
                            }}</span>
                        </button>

                        <!-- Documentaries -->
                        <button
                            type="button"
                            @click="setCategoryPreset('movie', 'Documentaries')"
                            :class="[
                                'col-span-2 flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border p-2.5 font-bold transition-all sm:col-span-1',
                                customCategory === 'Documentaries'
                                    ? 'border-amber-400 bg-amber-500/20 text-amber-300 shadow-md ring-1 ring-amber-500/50'
                                    : 'cf-glass border-border/70 text-muted-foreground hover:text-foreground hover:border-amber-500/40',
                            ]"
                        >
                            <span class="text-sm">📽️</span>
                            <span class="text-[11px]">{{
                                isRTL ? 'وثائقيات' : 'Documentary'
                            }}</span>
                        </button>
                    </div>

                    <!-- Optional Custom Category Folder Input -->
                    <div v-if="showCustomCategoryInput" class="pt-1">
                        <input
                            v-model="customCategory"
                            type="text"
                            class="cf-field border-primary/40 bg-card/80 text-primary focus:border-primary h-10 w-full rounded-xl border px-3.5 font-mono text-xs focus:outline-none"
                            :placeholder="
                                type === 'series'
                                    ? 'e.g. TV Shows/Anime or Cartoons'
                                    : 'e.g. Movies/4K UHD or Animation'
                            "
                        />
                    </div>
                </div>

                <!-- Core Metadata Grid -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <!-- Title -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-foreground block font-bold">
                            {{
                                type === 'series'
                                    ? isRTL
                                        ? 'اسم المسلسل النظيف:'
                                        : 'Clean Series Title:'
                                    : isRTL
                                      ? 'اسم الفيلم النظيف:'
                                      : 'Clean Movie Title:'
                            }}
                        </label>
                        <input
                            v-model="cleanTitle"
                            type="text"
                            class="cf-field border-border/80 bg-card/80 text-foreground placeholder:text-muted-foreground focus:border-primary h-10 w-full rounded-xl border px-3.5 focus:outline-none"
                            :placeholder="
                                type === 'series'
                                    ? 'e.g. Breaking Bad'
                                    : 'e.g. Inception'
                            "
                        />
                    </div>

                    <!-- Year -->
                    <div class="space-y-1.5">
                        <label class="text-foreground block font-bold">
                            {{ isRTL ? 'سنة الإنتاج (Year):' : 'Release Year:' }}
                        </label>
                        <input
                            v-model="year"
                            type="text"
                            maxlength="4"
                            class="cf-field border-border/80 bg-card/80 text-foreground placeholder:text-muted-foreground focus:border-primary h-10 w-full rounded-xl border px-3.5 font-mono tabular-nums focus:outline-none"
                            placeholder="e.g. 2024"
                        />
                    </div>

                    <!-- Genre (Interactive Search & Autocomplete) -->
                    <div class="relative space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-foreground block font-bold">
                                {{
                                    isRTL
                                        ? 'التصنيف الأساسي (Genre):'
                                        : 'Primary Genre:'
                                }}
                            </label>
                            <button
                                v-if="genre"
                                type="button"
                                @click="clearGenre"
                                class="text-destructive cursor-pointer text-[10px] font-bold transition-colors hover:brightness-110"
                            >
                                {{ isRTL ? 'مسح التصنيف' : 'Clear' }}
                            </button>
                        </div>

                        <div class="relative">
                            <input
                                v-model="genre"
                                @focus="isGenreDropdownOpen = true"
                                type="text"
                                class="cf-field border-border/80 bg-card/80 text-foreground placeholder:text-muted-foreground focus:border-primary h-10 w-full rounded-xl border ps-3.5 pe-8 focus:outline-none"
                                placeholder="e.g. Action, Drama, Sci-Fi"
                            />
                            <button
                                v-if="genre"
                                type="button"
                                @click="clearGenre"
                                class="text-muted-foreground hover:text-foreground absolute end-2.5 top-1/2 -translate-y-1/2 cursor-pointer p-1"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                            <button
                                v-else
                                type="button"
                                @click="
                                    isGenreDropdownOpen = !isGenreDropdownOpen
                                "
                                class="text-muted-foreground hover:text-foreground absolute end-2.5 top-1/2 -translate-y-1/2 cursor-pointer p-1"
                            >
                                <ChevronDown class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <!-- Filtered Dropdown Popover -->
                        <div
                            v-if="isGenreDropdownOpen"
                            class="cf-glass-popover border-border/80 absolute inset-x-0 top-full z-20 mt-1 max-h-48 space-y-1 overflow-y-auto rounded-2xl border p-2 shadow-2xl backdrop-blur-xl"
                        >
                            <button
                                v-for="g in filteredGenres"
                                :key="g"
                                type="button"
                                @click="selectGenre(g)"
                                class="flex w-full cursor-pointer items-center justify-between rounded-lg px-3 py-1.5 text-start text-xs transition-colors"
                                :class="
                                    isGenreActive(g)
                                        ? 'bg-primary/20 text-primary font-bold'
                                        : 'text-foreground hover:bg-muted/60'
                                "
                            >
                                <span>{{ g }}</span>
                                <Check
                                    v-if="isGenreActive(g)"
                                    class="text-primary h-3.5 w-3.5"
                                />
                            </button>
                        </div>
                    </div>

                    <!-- Quick Select Genre Badges / Chips -->
                    <div class="space-y-1.5 pt-1 md:col-span-2">
                        <div
                            class="text-muted-foreground flex items-center gap-1.5 text-[11px] font-bold"
                        >
                            <Tag class="text-primary h-3.5 w-3.5" />
                            <span>{{
                                isRTL
                                    ? 'تصنيفات شائعة للاختيار السريع بنقرة واحدة:'
                                    : 'Popular Genres (Click to Select / Toggle):'
                            }}</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="g in commonGenres"
                                :key="g"
                                type="button"
                                @click="selectGenre(g)"
                                :class="[
                                    'flex cursor-pointer items-center gap-1.5 rounded-xl border px-2.5 py-1 text-[11px] font-bold transition-all',
                                    isGenreActive(g)
                                        ? 'border-primary bg-primary/20 text-primary ring-primary/40 scale-105 shadow-sm ring-1'
                                        : 'cf-glass border-border/70 text-muted-foreground hover:border-primary/35 hover:text-foreground',
                                ]"
                            >
                                <span>{{ g }}</span>
                                <Check
                                    v-if="isGenreActive(g)"
                                    class="text-primary h-3 w-3"
                                />
                            </button>
                        </div>
                    </div>

                    <!-- Series Specific Fields -->
                    <template v-if="type === 'series'">
                        <div class="space-y-1.5">
                            <label class="text-foreground block font-bold">
                                {{
                                    isRTL
                                        ? 'رقم الموسم (Season):'
                                        : 'Season Number:'
                                }}
                            </label>
                            <input
                                v-model.number="season"
                                type="number"
                                min="1"
                                max="99"
                                class="cf-field border-border/80 bg-card/80 text-foreground placeholder:text-muted-foreground focus:border-primary h-10 w-full rounded-xl border px-3.5 font-mono tabular-nums focus:outline-none"
                                placeholder="1"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-foreground block font-bold">
                                {{
                                    isRTL
                                        ? 'رقم الحلقة (Episode):'
                                        : 'Episode Number:'
                                }}
                            </label>
                            <input
                                v-model.number="episode"
                                type="number"
                                min="1"
                                max="999"
                                class="cf-field border-border/80 bg-card/80 text-foreground placeholder:text-muted-foreground focus:border-primary h-10 w-full rounded-xl border px-3.5 font-mono tabular-nums focus:outline-none"
                                placeholder="1"
                            />
                        </div>

                        <div class="space-y-1.5 md:col-span-2">
                            <label class="text-foreground block font-bold">
                                {{
                                    isRTL
                                        ? 'عنوان الحلقة (اختياري):'
                                        : 'Episode Title (Optional):'
                                }}
                            </label>
                            <input
                                v-model="episodeTitle"
                                type="text"
                                class="cf-field border-border/80 bg-card/80 text-foreground placeholder:text-muted-foreground focus:border-primary h-10 w-full rounded-xl border px-3.5 focus:outline-none"
                                placeholder="e.g. Pilot, Ozymandias"
                            />
                        </div>
                    </template>

                    <!-- Movie Specific: Collection -->
                    <div
                        v-if="type === 'movie'"
                        class="space-y-1.5 md:col-span-2"
                    >
                        <label
                            class="text-foreground flex items-center justify-between font-bold"
                        >
                            <span>{{
                                isRTL
                                    ? 'مجموعة / سلسلة الفيلم (Collection / Franchise):'
                                    : 'Movie Collection / Franchise:'
                            }}</span>
                            <span
                                class="text-muted-foreground text-[10px] font-normal"
                            >
                                {{
                                    isRTL
                                        ? 'اتركه فارغاً إذا كان فيلماً منفرداً'
                                        : 'Leave blank for standalone films'
                                }}
                            </span>
                        </label>
                        <input
                            v-model="collectionName"
                            type="text"
                            class="cf-field border-border/80 bg-card/80 text-foreground placeholder:text-muted-foreground focus:border-primary h-10 w-full rounded-xl border px-3.5 focus:outline-none"
                            placeholder="e.g. Harry Potter Collection, Marvel Cinematic Universe"
                        />
                    </div>
                </div>

                <!-- Custom Path Override Toggle -->
                <div class="border-border/70 space-y-3 border-t pt-2">
                    <div class="flex items-center justify-between">
                        <label
                            class="flex cursor-pointer items-center gap-2 select-none"
                        >
                            <input
                                type="checkbox"
                                v-model="customPathOverride"
                                class="border-border bg-card text-primary cursor-pointer rounded focus:ring-0"
                            />
                            <span class="text-foreground font-bold">
                                {{
                                    isRTL
                                        ? 'تخصيص مسار الوجهة يدوياً بالكامل (Direct Path Override)'
                                        : 'Direct Path Override (Manual Custom Destination)'
                                }}
                            </span>
                        </label>
                    </div>

                    <div v-if="customPathOverride">
                        <input
                            v-model="manualDestinationPath"
                            type="text"
                            class="cf-field border-primary/50 bg-card/80 text-primary focus:border-primary h-10 w-full rounded-xl border px-3.5 font-mono text-xs focus:outline-none"
                            :placeholder="calculatedDestination"
                        />
                    </div>
                </div>

                <!-- Live Result Destination Preview -->
                <div
                    class="border-primary/35 bg-primary/10 space-y-2.5 rounded-2xl border p-4"
                >
                    <div
                        class="text-primary flex items-center justify-between text-[11px] font-bold"
                    >
                        <span class="flex items-center gap-1.5">
                            <ArrowRight class="h-3.5 w-3.5 rtl:rotate-180" />
                            <span>{{
                                isRTL
                                    ? 'مسار الوجهة المنظم النهائي المتوقع:'
                                    : 'Calculated Final Destination Path:'
                            }}</span>
                        </span>
                        <div
                            class="flex items-center gap-1.5 font-mono text-[10px]"
                        >
                            <span
                                class="border-primary/35 bg-primary/15 text-primary rounded-md border px-2 py-0.5"
                            >
                                {{
                                    customCategory ||
                                    (type === 'series' ? 'TV Shows' : 'Movies')
                                }}
                            </span>
                            <span
                                v-if="genre"
                                class="rounded-md border border-purple-400/30 bg-purple-500/15 px-2 py-0.5 text-purple-300"
                            >
                                {{ genre }}
                            </span>
                        </div>
                    </div>
                    <div
                        class="border-border/60 text-foreground rounded-xl border bg-black/50 p-2.5 font-mono text-xs break-all select-all"
                    >
                        {{ calculatedDestination }}
                    </div>
                </div>

                <!-- Companion Subtitles Preview -->
                <div v-if="updatedSubtitles.length > 0" class="space-y-2">
                    <div
                        class="text-foreground flex items-center gap-1.5 text-[11px] font-bold"
                    >
                        <Subtitles class="text-primary h-3.5 w-3.5" />
                        <span>{{
                            isRTL
                                ? `ملفات الترجمة المرافقة المتزامنة تلقائياً (${updatedSubtitles.length}):`
                                : `Companion Subtitles Auto-Synced (${updatedSubtitles.length}):`
                        }}</span>
                    </div>
                    <div class="space-y-1.5">
                        <div
                            v-for="(sub, idx) in updatedSubtitles"
                            :key="idx"
                            class="cf-glass border-border/70 text-foreground flex items-center justify-between gap-2 rounded-xl border p-2.5 font-mono text-[11px]"
                        >
                            <span
                                class="flex-1 truncate"
                                :title="sub.destination"
                                >{{ sub.destination }}</span
                            >
                            <span
                                class="border-primary/30 bg-primary/10 text-primary shrink-0 rounded border px-1.5 py-0.5 text-[10px] font-bold uppercase"
                            >
                                {{ sub.language || 'und' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div
                class="border-border/70 bg-background/50 flex items-center justify-between border-t px-6 py-4"
            >
                <div>
                    <button
                        v-if="item.is_manually_edited"
                        @click="handleReset"
                        type="button"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/35 px-4 py-2 text-xs font-bold text-amber-400 transition-colors hover:bg-amber-500/15 hover:text-amber-300"
                    >
                        <RotateCcw class="h-3.5 w-3.5" />
                        <span>{{
                            isRTL
                                ? 'استعادة الاقتراح التلقائي'
                                : 'Reset to Auto Proposal'
                        }}</span>
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="$emit('close')"
                        type="button"
                        class="cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground h-10 cursor-pointer rounded-xl border px-4 text-xs font-bold transition-colors"
                    >
                        {{ isRTL ? 'إلغاء' : 'Cancel' }}
                    </button>

                    <button
                        @click="handleSave"
                        type="button"
                        class="bg-primary text-primary-foreground shadow-primary/25 flex h-10 cursor-pointer items-center gap-1.5 rounded-xl px-5 text-xs font-black shadow-lg transition-all hover:brightness-105 active:scale-95"
                    >
                        <Check class="h-4 w-4" />
                        <span>{{
                            isRTL ? 'حفظ وتطبيق التصحيح' : 'Apply Corrections'
                        }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
