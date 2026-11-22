<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { router } from '@inertiajs/vue3';
import {
    Sparkles,
    Star,
    Calendar,
    Clock,
    Layers,
    Check,
    RotateCcw,
    Heart,
    ArrowUpDown,
    Globe,
} from '@lucide/vue';
import SvgFlag from '@/components/common/SvgFlag.vue';
import LiveSearchDropdown from '@/components/common/LiveSearchDropdown.vue';

const props = defineProps<{
    genres: any[];
    filters: Record<string, any>;
    showVibes?: boolean;
}>();

const { t, isRTL } = useI18n();

const searchQuery = ref(props.filters.search || '');
const selectedGenre = ref(props.filters.genre || '');
const selectedOrigin = ref(props.filters.origin || '');
const selectedResolution = ref(props.filters.resolution || '');
const selectedSort = ref(props.filters.sort || 'rating');
const selectedVibe = ref(props.filters.vibe || '');
const favoriteOnly = ref(
    props.filters.favorite_only === '1' ||
        props.filters.favorite_only === 1 ||
        props.filters.favorite_only === true,
);

watch(
    () => props.filters,
    (newFilters) => {
        searchQuery.value = newFilters.search || '';
        selectedGenre.value = newFilters.genre || '';
        selectedOrigin.value = newFilters.origin || '';
        selectedResolution.value = newFilters.resolution || '';
        selectedSort.value = newFilters.sort || 'rating';
        selectedVibe.value = newFilters.vibe || '';
        favoriteOnly.value =
            newFilters.favorite_only === '1' ||
            newFilters.favorite_only === 1 ||
            newFilters.favorite_only === true;
    },
    { deep: true },
);

const hasActiveFilters = computed(() => {
    return !!(
        searchQuery.value.trim() ||
        selectedGenre.value ||
        selectedOrigin.value ||
        selectedResolution.value ||
        (selectedSort.value && selectedSort.value !== 'rating') ||
        selectedVibe.value ||
        favoriteOnly.value
    );
});

const applyFilters = () => {
    router.get(
        window.location.pathname,
        {
            ...props.filters,
            search: searchQuery.value.trim() || undefined,
            genre: selectedGenre.value || undefined,
            origin: selectedOrigin.value || undefined,
            resolution: selectedResolution.value || undefined,
            sort: selectedSort.value || undefined,
            vibe: selectedVibe.value || undefined,
            favorite_only: favoriteOnly.value ? 1 : undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const handleFilterBarSearch = (q?: string) => {
    const term = (typeof q === 'string' ? q : searchQuery.value).trim();
    searchQuery.value = term;

    selectedOrigin.value = '';
    selectedGenre.value = '';
    selectedVibe.value = '';

    router.get(
        window.location.pathname,
        {
            search: term || undefined,
            sort: selectedSort.value || undefined,
            resolution: selectedResolution.value || undefined,
            favorite_only: favoriteOnly.value ? 1 : undefined,
        },
        { preserveState: false, preserveScroll: true },
    );
};

const selectOrigin = (orig: string) => {
    selectedOrigin.value = selectedOrigin.value === orig ? '' : orig;
    applyFilters();
};

const selectGenre = (slug: string) => {
    selectedGenre.value = selectedGenre.value === slug ? '' : slug;
    applyFilters();
};

const selectResolution = (res: string) => {
    selectedResolution.value = selectedResolution.value === res ? '' : res;
    applyFilters();
};

const selectSort = (sortKey: string) => {
    selectedSort.value = sortKey;
    applyFilters();
};

const selectVibe = (v: string) => {
    selectedVibe.value = selectedVibe.value === v ? '' : v;
    applyFilters();
};

const toggleFavorite = () => {
    favoriteOnly.value = !favoriteOnly.value;
    applyFilters();
};

const resetAllFilters = () => {
    searchQuery.value = '';
    selectedGenre.value = '';
    selectedOrigin.value = '';
    selectedResolution.value = '';
    selectedSort.value = 'rating';
    selectedVibe.value = '';
    favoriteOnly.value = false;
    router.get(
        window.location.pathname,
        {},
        { preserveState: true, preserveScroll: true },
    );
};

const regionalOrigins = [
    { id: '', label_en: 'All Regions', label_ar: 'جميع الدول', flag: '🌍' },
    {
        id: 'arabic',
        label_en: 'Arabic Cinema',
        label_ar: 'سينما ومسلسلات عربية',
        flag: '🇸🇦',
    },
    {
        id: 'indian',
        label_en: 'Bollywood & Indian',
        label_ar: 'سينما هندية (بوليوود)',
        flag: '🇮🇳',
    },
    {
        id: 'asian',
        label_en: 'Anime & Asian',
        label_ar: 'إنيمي وسينما آسيوية',
        flag: '🇯🇵',
    },
    {
        id: 'turkish',
        label_en: 'Turkish Cinema',
        label_ar: 'سينما وأعمال تركية',
        flag: '🇹🇷',
    },
    {
        id: 'hollywood',
        label_en: 'Hollywood & Western',
        label_ar: 'سينما هوليوود وعالمي',
        flag: '🇺🇸',
    },
    {
        id: 'european',
        label_en: 'European Cinema',
        label_ar: 'سينما أوروبية',
        flag: '🇪🇺',
    },
];

const curatedVibes = [
    {
        id: 'Mind-Bending',
        label_en: 'Mind-Bending Sci-Fi',
        label_ar: 'خيال علمي مشوق',
    },
    { id: 'Adrenaline', label_en: 'Adrenaline Rush', label_ar: 'أكشن وإثارة' },
    {
        id: 'Late-Night Noir',
        label_en: 'Late-Night Noir',
        label_ar: 'غموض وجريمة',
    },
    {
        id: 'Emotional Resonance',
        label_en: 'Emotional Drama',
        label_ar: 'دراما مؤثرة',
    },
    {
        id: 'Epic Worlds',
        label_en: 'Epic Worlds',
        label_ar: 'مغامرة وفانتازيا',
    },
];
</script>

<template>
    <div
        class="cf-glass border-border/80 relative mb-8 space-y-5 overflow-hidden rounded-3xl border p-4 shadow-sm sm:p-5"
    >
        <!-- Prominent Search Input Bar with Live Flyout -->
        <div class="relative w-full max-w-xl">
            <LiveSearchDropdown
                v-model="searchQuery"
                variant="large"
                :placeholder="
                    isRTL
                        ? 'ابحث بالاسم، الممثل، المخرج أو سنة الإنتاج...'
                        : 'Search by title, cast, director, or release year...'
                "
                :auto-navigate="false"
                @submit="handleFilterBarSearch"
            />
        </div>

        <!-- 0. Regional Origin Carousel -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <div
                    class="text-muted-foreground flex items-center gap-2 text-xs font-bold"
                >
                    <Globe class="text-primary size-3.5" />
                    <span>{{
                        isRTL
                            ? 'التصنيف حسب الدولة والإنتاج (سينما عالمية)'
                            : 'Regional Origin & World Cinema'
                    }}</span>
                </div>

                <button
                    v-if="hasActiveFilters"
                    type="button"
                    @click="resetAllFilters"
                    class="text-destructive hover:text-destructive/80 flex cursor-pointer items-center gap-1.5 text-xs font-bold transition-colors"
                >
                    <RotateCcw class="size-3.5" />
                    <span>{{ t('common.clear_filters') }}</span>
                </button>
            </div>

            <div
                class="custom-scrollbar flex items-center gap-2 overflow-x-auto pb-1"
            >
                <button
                    v-for="orig in regionalOrigins"
                    :key="orig.id"
                    type="button"
                    @click="selectOrigin(orig.id)"
                    class="flex min-h-9 cursor-pointer items-center gap-2 rounded-2xl border px-3.5 py-1.5 text-xs font-bold whitespace-nowrap transition-all active:scale-95"
                    :class="
                        selectedOrigin === orig.id
                            ? 'border-primary bg-primary text-primary-foreground font-black shadow-xs'
                            : 'border-border/80 bg-card/70 text-muted-foreground hover:border-primary/40 hover:text-foreground'
                    "
                >
                    <SvgFlag :origin="orig.id" size="sm" />
                    <span>{{ isRTL ? orig.label_ar : orig.label_en }}</span>
                    <Check
                        v-if="selectedOrigin === orig.id"
                        class="size-3.5 stroke-[3]"
                    />
                </button>
            </div>
        </div>

        <!-- 1. Genres Pill Carousel -->
        <div class="border-border/60 space-y-2 border-t pt-2.5">
            <div
                class="text-muted-foreground flex items-center gap-2 text-xs font-bold"
            >
                <Layers class="text-primary size-3.5" />
                <span>{{ t('common.genres') }}</span>
            </div>

            <div
                class="custom-scrollbar flex items-center gap-2 overflow-x-auto pb-1"
            >
                <button
                    type="button"
                    @click="selectGenre('')"
                    class="min-h-9 cursor-pointer rounded-2xl border px-4 py-1.5 text-xs font-bold whitespace-nowrap transition-all active:scale-95"
                    :class="
                        !selectedGenre
                            ? 'border-primary bg-primary text-primary-foreground font-black shadow-xs'
                            : 'border-border/80 bg-card/70 text-muted-foreground hover:border-primary/40 hover:text-foreground'
                    "
                >
                    {{ t('common.all_genres') }}
                </button>

                <button
                    v-for="g in genres"
                    :key="g.id"
                    type="button"
                    @click="selectGenre(g.slug)"
                    class="flex min-h-9 cursor-pointer items-center gap-1.5 rounded-2xl border px-4 py-1.5 text-xs font-bold whitespace-nowrap transition-all active:scale-95"
                    :class="
                        selectedGenre === g.slug
                            ? 'border-primary bg-primary text-primary-foreground font-black shadow-xs'
                            : 'border-border/80 bg-card/70 text-muted-foreground hover:border-primary/40 hover:text-foreground'
                    "
                >
                    <span>{{
                        isRTL && g.name_ar ? g.name_ar : g.name_en
                    }}</span>
                    <Check
                        v-if="selectedGenre === g.slug"
                        class="size-3.5 stroke-[3]"
                    />
                </button>
            </div>
        </div>

        <!-- 2. AI Mood & Vibe Segmented Chips -->
        <div
            v-if="showVibes"
            class="border-border/60 space-y-2 border-t pt-2.5"
        >
            <div
                class="text-muted-foreground flex items-center gap-2 text-xs font-bold"
            >
                <Sparkles class="text-primary size-3.5" />
                <span>{{ t('common.vibe') }}</span>
            </div>

            <div
                class="custom-scrollbar flex items-center gap-2 overflow-x-auto pb-1"
            >
                <button
                    v-for="v in curatedVibes"
                    :key="v.id"
                    type="button"
                    @click="selectVibe(v.id)"
                    class="flex min-h-9 cursor-pointer items-center gap-1.5 rounded-xl border px-3.5 py-1.5 text-xs font-bold whitespace-nowrap transition-all active:scale-95"
                    :class="
                        selectedVibe === v.id
                            ? 'border-primary bg-primary text-primary-foreground font-black shadow-xs'
                            : 'border-border/80 bg-card/70 text-muted-foreground hover:border-primary/40 hover:text-foreground'
                    "
                >
                    <span
                        class="text-xs"
                        :class="
                            selectedVibe === v.id
                                ? 'text-primary-foreground'
                                : 'text-primary'
                        "
                        >✦</span
                    >
                    <span>{{ isRTL ? v.label_ar : v.label_en }}</span>
                    <Check
                        v-if="selectedVibe === v.id"
                        class="size-3 stroke-[3]"
                    />
                </button>
            </div>
        </div>

        <!-- 3. Quality & Sorting Interactive Segmented Controls -->
        <div
            class="border-border/60 flex flex-wrap items-center justify-between gap-4 border-t pt-3"
        >
            <!-- Quality Segmented Control -->
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-muted-foreground text-xs font-bold"
                    >{{ t('common.quality') }}:</span
                >
                <div
                    class="border-border/80 bg-card/70 flex items-center rounded-2xl border p-1"
                >
                    <button
                        v-for="res in [
                            { key: '', label: t('common.all_qualities') },
                            { key: '4K', label: '4K UHD' },
                            { key: '1080p', label: '1080p FHD' },
                            { key: '720p', label: '720p HD' },
                        ]"
                        :key="res.key"
                        type="button"
                        @click="selectResolution(res.key)"
                        class="cursor-pointer rounded-xl px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-all"
                        :class="
                            selectedResolution === res.key
                                ? 'bg-primary text-primary-foreground font-black shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ res.label }}
                    </button>
                </div>
            </div>

            <!-- Sorting Segmented Control -->
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-muted-foreground text-xs font-bold"
                    >{{ t('common.sort_by') }}:</span
                >
                <div
                    class="border-border/80 bg-card/70 flex items-center rounded-2xl border p-1"
                >
                    <button
                        v-for="s in [
                            {
                                key: 'rating',
                                label: t('common.sort_rating'),
                                icon: Star,
                            },
                            {
                                key: 'date_added',
                                label: t('common.sort_recent'),
                                icon: Clock,
                            },
                            {
                                key: 'year',
                                label: t('common.sort_year'),
                                icon: Calendar,
                            },
                            {
                                key: 'title',
                                label: t('common.sort_title'),
                                icon: ArrowUpDown,
                            },
                        ]"
                        :key="s.key"
                        type="button"
                        @click="selectSort(s.key)"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-all"
                        :class="
                            selectedSort === s.key
                                ? 'bg-primary text-primary-foreground font-black shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        <component :is="s.icon" class="size-3.5" />
                        <span>{{ s.label }}</span>
                    </button>
                </div>
            </div>

            <!-- Favorites Toggle Button -->
            <button
                type="button"
                @click="toggleFavorite"
                class="flex min-h-9 cursor-pointer items-center gap-1.5 rounded-2xl border px-3.5 py-1.5 text-xs font-bold transition-all active:scale-95"
                :class="
                    favoriteOnly
                        ? 'border-rose-500/40 bg-rose-500/20 text-rose-400 shadow-xs'
                        : 'border-border/80 bg-card/70 text-muted-foreground hover:border-primary/40 hover:text-foreground'
                "
            >
                <Heart
                    class="size-3.5"
                    :class="{ 'fill-rose-500 text-rose-500': favoriteOnly }"
                />
                <span>{{ t('common.favorite') }}</span>
            </button>
        </div>
    </div>
</template>
