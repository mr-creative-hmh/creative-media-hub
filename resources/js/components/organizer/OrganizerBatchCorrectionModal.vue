<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { X, Check, Layers, Film, Tv, Tag, ArrowRight } from '@lucide/vue';
import type { PlanItem } from './OrganizerItemCorrectionModal.vue';

const props = defineProps<{
    isOpen: boolean;
    selectedItems: PlanItem[];
    movieTemplate: string;
    seriesTemplate: string;
    targetRoot: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'apply', updates: Partial<PlanItem>): void;
}>();

const { isRTL } = useI18n();

// Popular genres list
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

// Fields to selectively apply
const applyType = ref(false);
const typeVal = ref<'movie' | 'series'>('series');

const applySeriesTitle = ref(false);
const seriesTitleVal = ref('');

const applySeason = ref(false);
const seasonVal = ref<number>(1);

const applyCollection = ref(false);
const collectionVal = ref('');

const applyGenre = ref(false);
const genreVal = ref('');

const selectBatchGenre = (g: string) => {
    applyGenre.value = true;
    if (genreVal.value.trim().toLowerCase() === g.toLowerCase()) {
        genreVal.value = '';
    } else {
        genreVal.value = g;
    }
};

const isGenreActive = (g: string) => {
    if (!genreVal.value) return false;
    const current = genreVal.value.trim().toLowerCase();
    return (
        current === g.toLowerCase() ||
        current
            .split(',')
            .map((s) => s.trim().toLowerCase())
            .includes(g.toLowerCase())
    );
};

const clearBatchGenre = () => {
    genreVal.value = '';
};

// Live example preview for the first selected item
const previewFirstItemDest = computed(() => {
    const first = props.selectedItems[0];
    if (!first) return '';
    const isSeries =
        (applyType.value ? typeVal.value : first.type) === 'series';
    const pattern = isSeries ? props.seriesTemplate : props.movieTemplate;
    const sTitle = (
        applySeriesTitle.value && seriesTitleVal.value.trim()
            ? seriesTitleVal.value.trim()
            : first.clean_title || 'Unknown'
    )
        .replace(/[<>:"/\\|?*]/g, '')
        .trim();
    const sYear = first.year ? String(first.year) : '';
    const sColl = (
        applyCollection.value
            ? collectionVal.value.trim()
            : first.collection_name || ''
    )
        .replace(/[<>:"/\\|?*]/g, '')
        .trim();
    const sGenre = (
        applyGenre.value ? genreVal.value.trim() : first.genre || ''
    )
        .replace(/[<>:"/\\|?*]/g, '')
        .trim();
    const sRes = first.resolution || '1080p';
    const sNum = applySeason.value
        ? Number(seasonVal.value)
        : first.season || 1;
    const epNum = first.episode || 1;
    const ext = first.filename.split('.').pop() || 'mkv';

    let p = pattern
        .replace(/{Type}/g, isSeries ? 'TV Shows' : 'Movies')
        .replace(/{Title}/g, sTitle)
        .replace(/{CleanResolution}/g, sRes)
        .replace(/{Resolution}/g, sRes)
        .replace(/{ext}/g, ext);

    if (sColl) {
        p = p.replace(/{Collection}/g, sColl);
    } else {
        p = p
            .replace(/\/Collections\/{Collection}/g, '')
            .replace(/Collections\/{Collection}\//g, '')
            .replace(/\/{Collection}/g, '')
            .replace(/{Collection}\//g, '')
            .replace(/{Collection}/g, '');
    }

    if (sGenre) {
        p = p.replace(/{Genre}/g, sGenre);
    } else {
        p = p
            .replace(/\/{Genre}/g, '')
            .replace(/{Genre}\//g, '')
            .replace(/{Genre}/g, '');
    }

    if (sYear) {
        p = p.replace(/{Year}/g, sYear);
    } else {
        p = p
            .replace(/\s*\({Year}\)/g, '')
            .replace(/\({Year}\)/g, '')
            .replace(/{Year}/g, '');
    }

    if (isSeries) {
        p = p
            .replace(/{Season:02}/g, String(sNum).padStart(2, '0'))
            .replace(/{Episode:02}/g, String(epNum).padStart(2, '0'));
        p = p
            .replace(/\s*-\s*{EpisodeTitle}/g, '')
            .replace(/{EpisodeTitle}/g, '');
    }

    p = p.replace(/\/+/g, '/').replace(/\s+/g, ' ').trim();
    p = p
        .replace(/\[\s*\]/g, '')
        .replace(/\(\s*\)/g, '')
        .replace(/\s+\./g, '.');
    const root = (props.targetRoot || 'H:/Entertainment')
        .replace(/\\/g, '/')
        .replace(/\/+$/, '');
    return `${root}/${p}`.replace(/\/+/g, '/');
});

function handleApply() {
    const updates: Partial<PlanItem> = {};

    if (applyType.value) {
        updates.type = typeVal.value;
    }
    if (applySeriesTitle.value && seriesTitleVal.value.trim()) {
        updates.clean_title = seriesTitleVal.value.trim();
    }
    if (applySeason.value) {
        updates.season = Number(seasonVal.value) || 1;
    }
    if (applyCollection.value) {
        updates.collection_name = collectionVal.value.trim() || null;
    }
    if (applyGenre.value) {
        updates.genre = genreVal.value.trim() || null;
    }

    updates.status = 'ready';
    updates.selected = true;

    emit('apply', updates);
    emit('close');
}
</script>

<template>
    <div
        v-if="isOpen"
        class="animate-fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-md"
        :dir="isRTL ? 'rtl' : 'ltr'"
        @click.self="$emit('close')"
    >
        <div
            class="cf-glass-elevated border-border/80 bg-card/95 relative flex w-full max-w-xl flex-col overflow-hidden rounded-3xl border text-xs shadow-2xl"
        >
            <!-- Header -->
            <div
                class="border-border/70 bg-background/40 flex items-center justify-between border-b px-6 py-5"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="border-primary/35 bg-primary/15 text-primary flex h-10 w-10 items-center justify-center rounded-2xl border"
                    >
                        <Layers class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-foreground text-base font-black">
                            {{
                                isRTL
                                    ? 'تصحيح جماعي للملفات المحددة'
                                    : 'Batch Metadata Correction'
                            }}
                        </h3>
                        <p class="text-muted-foreground text-xs tabular-nums">
                            {{
                                isRTL
                                    ? `سيتم تطبيق التعديلات المختارة على (${selectedItems.length}) ملف محدد`
                                    : `Apply selected updates across (${selectedItems.length}) selected items`
                            }}
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

            <!-- Body -->
            <div
                class="custom-scrollbar max-h-[70vh] space-y-4 overflow-y-auto p-6"
            >
                <p class="text-muted-foreground">
                    {{
                        isRTL
                            ? 'اختر الحقول التي ترغب بتحديثها بشكل جماعي:'
                            : 'Check the fields you wish to bulk update:'
                    }}
                </p>

                <!-- 1. Type -->
                <div
                    class="cf-glass border-border/70 space-y-2 rounded-2xl border p-3.5"
                >
                    <label
                        class="text-foreground flex cursor-pointer items-center gap-2 font-bold select-none"
                    >
                        <input
                            type="checkbox"
                            v-model="applyType"
                            class="border-border bg-card text-primary cursor-pointer rounded"
                        />
                        <span>{{
                            isRTL
                                ? 'تعديل نوع الوسائط (Media Type)'
                                : 'Change Media Type'
                        }}</span>
                    </label>
                    <div v-if="applyType" class="grid grid-cols-2 gap-2 pt-1">
                        <button
                            type="button"
                            @click="typeVal = 'movie'"
                            :class="[
                                'flex cursor-pointer items-center justify-center gap-2 rounded-xl border py-2.5 font-bold transition-all',
                                typeVal === 'movie'
                                    ? 'border-primary bg-primary/20 text-primary ring-primary/50 shadow-md ring-1'
                                    : 'cf-glass border-border/70 text-muted-foreground hover:border-primary/40 hover:text-foreground',
                            ]"
                        >
                            <Film class="h-4 w-4" />
                            <span>{{
                                isRTL ? 'أفلام (Movies)' : 'Movies'
                            }}</span>
                        </button>
                        <button
                            type="button"
                            @click="typeVal = 'series'"
                            :class="[
                                'flex cursor-pointer items-center justify-center gap-2 rounded-xl border py-2.5 font-bold transition-all',
                                typeVal === 'series'
                                    ? 'border-purple-400 bg-purple-500/20 text-purple-300 shadow-md ring-1 ring-purple-500/50'
                                    : 'cf-glass border-border/70 text-muted-foreground hover:text-foreground hover:border-purple-500/40',
                            ]"
                        >
                            <Tv class="h-4 w-4" />
                            <span>{{
                                isRTL ? 'مسلسلات (TV Series)' : 'TV Series'
                            }}</span>
                        </button>
                    </div>
                </div>

                <!-- 2. Series / Media Title -->
                <div
                    class="cf-glass border-border/70 space-y-2 rounded-2xl border p-3.5"
                >
                    <label
                        class="text-foreground flex cursor-pointer items-center gap-2 font-bold select-none"
                    >
                        <input
                            type="checkbox"
                            v-model="applySeriesTitle"
                            class="border-border bg-card text-primary cursor-pointer rounded"
                        />
                        <span>{{
                            isRTL
                                ? 'تعديل اسم العنوان / المسلسل الموحد'
                                : 'Set Unified Title / Show Name'
                        }}</span>
                    </label>
                    <input
                        v-if="applySeriesTitle"
                        v-model="seriesTitleVal"
                        type="text"
                        class="cf-field border-border/80 bg-card/80 text-foreground focus:border-primary h-10 w-full rounded-xl border px-3 focus:outline-none"
                        placeholder="e.g. Breaking Bad"
                    />
                </div>

                <!-- 3. Season -->
                <div
                    class="cf-glass border-border/70 space-y-2 rounded-2xl border p-3.5"
                >
                    <label
                        class="text-foreground flex cursor-pointer items-center gap-2 font-bold select-none"
                    >
                        <input
                            type="checkbox"
                            v-model="applySeason"
                            class="border-border bg-card text-primary cursor-pointer rounded"
                        />
                        <span>{{
                            isRTL
                                ? 'تعديل رقم الموسم الموحد (Season)'
                                : 'Set Unified Season Number'
                        }}</span>
                    </label>
                    <input
                        v-if="applySeason"
                        v-model.number="seasonVal"
                        type="number"
                        min="1"
                        max="99"
                        class="cf-field border-border/80 bg-card/80 text-foreground focus:border-primary h-10 w-full rounded-xl border px-3 font-mono tabular-nums focus:outline-none"
                        placeholder="1"
                    />
                </div>

                <!-- 4. Collection Name -->
                <div
                    class="cf-glass border-border/70 space-y-2 rounded-2xl border p-3.5"
                >
                    <label
                        class="text-foreground flex cursor-pointer items-center gap-2 font-bold select-none"
                    >
                        <input
                            type="checkbox"
                            v-model="applyCollection"
                            class="border-border bg-card text-primary cursor-pointer rounded"
                        />
                        <span>{{
                            isRTL
                                ? 'تعيين اسم السلسلة / المجموعة (Collection)'
                                : 'Set Unified Collection / Franchise'
                        }}</span>
                    </label>
                    <input
                        v-if="applyCollection"
                        v-model="collectionVal"
                        type="text"
                        class="cf-field border-border/80 bg-card/80 text-foreground focus:border-primary h-10 w-full rounded-xl border px-3 focus:outline-none"
                        placeholder="e.g. Harry Potter Collection"
                    />
                </div>

                <!-- 5. Genre (Interactive Chips & Custom Input) -->
                <div
                    class="cf-glass border-border/70 space-y-2.5 rounded-2xl border p-3.5"
                >
                    <div class="flex items-center justify-between">
                        <label
                            class="text-foreground flex cursor-pointer items-center gap-2 font-bold select-none"
                        >
                            <input
                                type="checkbox"
                                v-model="applyGenre"
                                class="border-border bg-card text-primary cursor-pointer rounded"
                            />
                            <span>{{
                                isRTL
                                    ? 'تعيين التصنيف الأساسي الموحد (Genre)'
                                    : 'Set Unified Genre'
                            }}</span>
                        </label>
                        <button
                            v-if="applyGenre && genreVal"
                            type="button"
                            @click="clearBatchGenre"
                            class="text-destructive cursor-pointer text-[10px] font-bold hover:brightness-110"
                        >
                            {{ isRTL ? 'مسح' : 'Clear' }}
                        </button>
                    </div>

                    <template v-if="applyGenre">
                        <div class="space-y-1.5 pt-1">
                            <div
                                class="text-muted-foreground flex items-center gap-1 text-[10px] font-bold"
                            >
                                <Tag class="text-primary h-3 w-3" />
                                <span>{{
                                    isRTL
                                        ? 'انقر على التصنيف لاختياره مباشرة:'
                                        : 'Click to select / toggle genre chip:'
                                }}</span>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="g in commonGenres"
                                    :key="g"
                                    type="button"
                                    @click="selectBatchGenre(g)"
                                    :class="[
                                        'flex cursor-pointer items-center gap-1 rounded-lg border px-2 py-0.5 text-[10px] font-bold transition-all',
                                        isGenreActive(g)
                                            ? 'border-primary bg-primary/20 text-primary ring-primary/50 shadow-sm ring-1'
                                            : 'cf-glass border-border/70 text-muted-foreground hover:border-primary/35 hover:text-foreground',
                                    ]"
                                >
                                    <span>{{ g }}</span>
                                    <Check
                                        v-if="isGenreActive(g)"
                                        class="text-primary h-2.5 w-2.5"
                                    />
                                </button>
                            </div>
                        </div>

                        <div class="relative pt-1">
                            <input
                                v-model="genreVal"
                                type="text"
                                class="cf-field border-border/80 bg-card/80 text-foreground focus:border-primary h-10 w-full rounded-xl border ps-3 pe-8 focus:outline-none"
                                placeholder="e.g. Action, Drama, Sci-Fi"
                            />
                            <button
                                v-if="genreVal"
                                type="button"
                                @click="clearBatchGenre"
                                class="text-muted-foreground hover:text-foreground absolute end-2.5 top-1/2 -translate-y-1/2 cursor-pointer p-1"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Live Example Batch Preview Box -->
                <div
                    v-if="previewFirstItemDest"
                    class="border-primary/35 bg-primary/10 space-y-1.5 rounded-2xl border p-3.5"
                >
                    <div
                        class="text-primary flex items-center gap-1 text-[10px] font-bold"
                    >
                        <ArrowRight class="h-3 w-3 rtl:rotate-180" />
                        <span>{{
                            isRTL
                                ? 'معاينة النتيجة التقديرية لأول ملف محدد:'
                                : 'Preview for 1st Selected Item:'
                        }}</span>
                    </div>
                    <div
                        class="border-border/60 text-foreground rounded-xl border bg-black/50 p-2 font-mono text-[11px] break-all select-all"
                    >
                        {{ previewFirstItemDest }}
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="border-border/70 bg-background/50 flex items-center justify-end gap-3 border-t px-6 py-4"
            >
                <button
                    @click="$emit('close')"
                    type="button"
                    class="cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground h-10 cursor-pointer rounded-xl border px-4 font-bold transition-colors"
                >
                    {{ isRTL ? 'إلغاء' : 'Cancel' }}
                </button>

                <button
                    @click="handleApply"
                    type="button"
                    :disabled="
                        !applyType &&
                        !applySeriesTitle &&
                        !applySeason &&
                        !applyCollection &&
                        !applyGenre
                    "
                    class="bg-primary text-primary-foreground shadow-primary/25 flex h-10 cursor-pointer items-center gap-1.5 rounded-xl px-5 font-black shadow-lg transition-all hover:brightness-105 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Check class="h-4 w-4" />
                    <span>{{
                        isRTL ? 'تطبيق على المحددة' : 'Apply to Selected'
                    }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
