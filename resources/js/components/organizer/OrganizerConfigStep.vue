<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Database,
    FolderSearch,
    Sliders,
    ChevronDown,
    Check,
    Play,
    Pause,
    X,
    Sparkles,
    Languages,
    Tag,
} from '@lucide/vue';

export interface OrganizerStrategy {
    id: string;
    tag: string;
    nameAr: string;
    nameEn: string;
    descAr: string;
    descEn: string;
    movie: string;
    series: string;
    exampleMovie: string;
    exampleSeries: string;
}

defineProps<{
    sourceMode: 'virtual' | 'folder';
    sourceFolder: string;
    targetRoot: string;
    selectedStrategyId: string;
    strategies: OrganizerStrategy[];
    movieTemplate: string;
    seriesTemplate: string;
    conflictStrategy: 'skip' | 'overwrite' | 'rename' | 'keep_higher_quality';
    omitEpisodeTitle: boolean;
    fetchEpisodeTitles: boolean;
    titleLanguage: 'original' | 'english' | 'arabic';
    watcher: any;
    isGeneratingPlan: boolean;
    planJobStatus?: any;
}>();

const emit = defineEmits<{
    (e: 'update:sourceMode', val: 'virtual' | 'folder'): void;
    (e: 'update:sourceFolder', val: string): void;
    (e: 'update:targetRoot', val: string): void;
    (e: 'update:selectedStrategyId', val: string): void;
    (e: 'update:movieTemplate', val: string): void;
    (e: 'update:seriesTemplate', val: string): void;
    (
        e: 'update:conflictStrategy',
        val: 'skip' | 'overwrite' | 'rename' | 'keep_higher_quality',
    ): void;
    (e: 'update:titleLanguage', val: 'original' | 'english' | 'arabic'): void;
    (e: 'toggle-omit-episode-title'): void;
    (e: 'toggle-fetch-episode-titles'): void;
    (e: 'toggle-watcher'): void;
    (e: 'add-watch-folder', path: string): void;
    (e: 'remove-watch-folder', path: string): void;
    (e: 'start-dry-run'): void;
    (e: 'pause-plan'): void;
    (e: 'resume-plan'): void;
    (e: 'cancel-plan'): void;
}>();

const { isRTL } = useI18n();

const showAdvancedCustom = ref(false);

const handleSelectStrategy = (strat: OrganizerStrategy) => {
    emit('update:selectedStrategyId', strat.id);
    if (strat.id !== 'custom') {
        emit('update:movieTemplate', strat.movie);
        emit('update:seriesTemplate', strat.series);
    } else {
        showAdvancedCustom.value = true;
    }
};
</script>

<template>
    <div class="space-y-8">
        <!-- Source Selector: Virtual vs Folder -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Virtual Library -->
            <div
                @click="emit('update:sourceMode', 'virtual')"
                class="group relative cursor-pointer rounded-3xl border p-6 backdrop-blur-xl transition-all"
                :class="
                    sourceMode === 'virtual'
                        ? 'border-primary bg-primary/10 shadow-primary/10 shadow-xl'
                        : 'cf-glass border-border/80 hover:border-primary/40'
                "
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3.5">
                        <div
                            class="bg-primary/15 text-primary flex h-12 w-12 items-center justify-center rounded-2xl"
                        >
                            <Database class="h-6 w-6" />
                        </div>
                        <div>
                            <h3 class="text-foreground text-lg font-extrabold">
                                {{
                                    isRTL
                                        ? 'المكتبة الافتراضية المفهرسة'
                                        : 'Current Virtual Library'
                                }}
                            </h3>
                            <p class="text-muted-foreground mt-1 text-xs">
                                {{
                                    isRTL
                                        ? 'تنظيم جميع الأفلام والمسلسلات الموجودة حالياً داخل قاعدة البيانات'
                                        : 'Organize all movies & series already scanned into your local database'
                                }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="flex h-6 w-6 items-center justify-center rounded-full border-2 transition-colors"
                        :class="
                            sourceMode === 'virtual'
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border'
                        "
                    >
                        <Check
                            v-if="sourceMode === 'virtual'"
                            class="h-3.5 w-3.5 stroke-[3]"
                        />
                    </div>
                </div>
            </div>

            <!-- Custom Raw Folder -->
            <div
                @click="emit('update:sourceMode', 'folder')"
                class="group relative cursor-pointer rounded-3xl border p-6 backdrop-blur-xl transition-all"
                :class="
                    sourceMode === 'folder'
                        ? 'border-primary bg-primary/10 shadow-primary/10 shadow-xl'
                        : 'cf-glass border-border/80 hover:border-primary/40'
                "
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3.5">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-purple-500/15 text-purple-400"
                        >
                            <FolderSearch class="h-6 w-6" />
                        </div>
                        <div>
                            <h3 class="text-foreground text-lg font-extrabold">
                                {{
                                    isRTL
                                        ? 'مجلد خارجي غير منظم (تحميلات / تورنت)'
                                        : 'Raw Unorganized Folder'
                                }}
                            </h3>
                            <p class="text-muted-foreground mt-1 text-xs">
                                {{
                                    isRTL
                                        ? 'مسح مجلد تورنت أو تحميلات وإعادة هيكلتها بالكامل'
                                        : 'Scan a raw downloads/torrent folder and generate a clean target structure'
                                }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="flex h-6 w-6 items-center justify-center rounded-full border-2 transition-colors"
                        :class="
                            sourceMode === 'folder'
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border'
                        "
                    >
                        <Check
                            v-if="sourceMode === 'folder'"
                            class="h-3.5 w-3.5 stroke-[3]"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Target Root & Source Folder Input Card -->
        <div
            class="cf-glass border-border/80 space-y-4 rounded-3xl border p-6 backdrop-blur-xl"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div v-if="sourceMode === 'folder'" class="space-y-1.5">
                    <label
                        class="text-muted-foreground block text-xs font-bold tracking-wider uppercase"
                    >
                        {{
                            isRTL ? 'مسار المجلد المصدر' : 'Source Folder Path'
                        }}
                    </label>
                    <input
                        :value="sourceFolder"
                        @input="
                            emit(
                                'update:sourceFolder',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                        type="text"
                        placeholder="e.g. D:/Downloads"
                        class="cf-field border-border/80 bg-card/80 text-foreground focus:border-primary h-10 w-full rounded-xl border px-4 font-mono text-xs focus:outline-none"
                    />
                </div>

                <div
                    class="space-y-1.5"
                    :class="{ 'md:col-span-2': sourceMode === 'virtual' }"
                >
                    <label
                        class="text-muted-foreground block text-xs font-bold tracking-wider uppercase"
                    >
                        {{
                            isRTL
                                ? 'مسار الوجهة الرئيسية للمكتبة المنظمة'
                                : 'Destination Library Root Path'
                        }}
                    </label>
                    <input
                        :value="targetRoot"
                        @input="
                            emit(
                                'update:targetRoot',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                        type="text"
                        placeholder="e.g. H:/Entertainment"
                        class="cf-field border-border/80 bg-card/80 text-foreground focus:border-primary h-10 w-full rounded-xl border px-4 font-mono text-xs focus:outline-none"
                    />
                </div>
            </div>
        </div>

        <!-- Strategy Presets -->
        <div class="space-y-4">
            <h2
                class="text-foreground flex items-center gap-2 text-sm font-extrabold tracking-wider uppercase"
            >
                <Sliders class="text-primary h-4 w-4" />
                {{
                    isRTL
                        ? 'اختر نمط وهيكل التنظيم المفضل'
                        : 'Choose Organization Preset & Structure'
                }}
            </h2>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div
                    v-for="strat in strategies"
                    :key="strat.id"
                    @click="handleSelectStrategy(strat)"
                    class="relative cursor-pointer rounded-2xl border p-5 backdrop-blur-xl transition-all"
                    :class="
                        selectedStrategyId === strat.id
                            ? 'border-primary bg-primary/10 shadow-primary/10 shadow-lg'
                            : 'cf-glass border-border/70 hover:border-primary/40'
                    "
                >
                    <div class="mb-2 flex items-center justify-between">
                        <span
                            class="border-primary/30 bg-primary/15 text-primary rounded-md border px-2 py-0.5 text-[10px] font-bold"
                        >
                            {{ strat.tag }}
                        </span>
                        <div
                            class="flex h-5 w-5 items-center justify-center rounded-full border"
                            :class="
                                selectedStrategyId === strat.id
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border'
                            "
                        >
                            <Check
                                v-if="selectedStrategyId === strat.id"
                                class="h-3 w-3 stroke-[3]"
                            />
                        </div>
                    </div>
                    <h4 class="text-foreground text-sm font-bold">
                        {{ isRTL ? strat.nameAr : strat.nameEn }}
                    </h4>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {{ isRTL ? strat.descAr : strat.descEn }}
                    </p>
                </div>
            </div>

            <!-- Collapsible Template Preview & Custom Editor -->
            <div class="pt-1">
                <button
                    type="button"
                    @click="showAdvancedCustom = !showAdvancedCustom"
                    class="text-muted-foreground hover:text-primary flex cursor-pointer items-center gap-1.5 text-xs font-bold transition-colors"
                >
                    <ChevronDown
                        class="h-4 w-4 transition-transform"
                        :class="{ 'rotate-180': showAdvancedCustom }"
                    />
                    {{
                        isRTL
                            ? 'معاينة وتخصيص قوالب المسارات المتقدمة'
                            : 'Preview & Customize Path Templates'
                    }}
                </button>

                <div
                    v-if="showAdvancedCustom || selectedStrategyId === 'custom'"
                    class="cf-glass border-border/80 mt-3 space-y-4 rounded-2xl border p-5"
                >
                    <div class="space-y-1.5">
                        <label
                            class="text-muted-foreground block text-xs font-bold"
                        >
                            {{
                                isRTL
                                    ? 'قالب مسار الأفلام'
                                    : 'Movie Path Template'
                            }}
                        </label>
                        <input
                            :value="movieTemplate"
                            @input="
                                emit(
                                    'update:movieTemplate',
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                            type="text"
                            dir="ltr"
                            class="cf-field border-border/80 bg-card/80 text-primary focus:border-primary h-10 w-full rounded-xl border px-3.5 font-mono text-xs focus:outline-none"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label
                            class="text-muted-foreground block text-xs font-bold"
                        >
                            {{
                                isRTL
                                    ? 'قالب مسار المسلسلات'
                                    : 'Series Path Template'
                            }}
                        </label>
                        <input
                            :value="seriesTemplate"
                            @input="
                                emit(
                                    'update:seriesTemplate',
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                            type="text"
                            dir="ltr"
                            class="cf-field border-border/80 bg-card/80 text-primary focus:border-primary h-10 w-full rounded-xl border px-3.5 font-mono text-xs focus:outline-none"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Naming Preferences & Conflict Handling -->
        <div
            class="cf-glass border-border/80 space-y-5 rounded-3xl border p-6 backdrop-blur-xl"
        >
            <h3
                class="text-foreground flex items-center gap-2 text-xs font-bold tracking-wider uppercase"
            >
                <Tag class="text-primary h-4 w-4" />
                {{
                    isRTL
                        ? 'تفضيلات التسمية والتعارضات'
                        : 'Naming Preferences & Conflict Rules'
                }}
            </h3>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Title Language -->
                <div class="space-y-1.5">
                    <label
                        class="text-muted-foreground flex items-center gap-1.5 text-xs font-semibold"
                    >
                        <Languages class="text-primary h-3.5 w-3.5" />
                        {{
                            isRTL
                                ? 'لغة عناوين المجلدات والملفات'
                                : 'Preferred Title Language'
                        }}
                    </label>
                    <select
                        :value="titleLanguage"
                        @change="
                            emit(
                                'update:titleLanguage',
                                ($event.target as HTMLSelectElement)
                                    .value as any,
                            )
                        "
                        class="cf-glass border-border/80 bg-card text-foreground focus:border-primary h-10 w-full rounded-xl border px-4 text-xs font-bold focus:outline-none"
                    >
                        <option
                            value="original"
                            class="bg-card text-foreground"
                        >
                            {{
                                isRTL
                                    ? 'العنوان الأصلي للمكتبة'
                                    : 'Original Metadata Title'
                            }}
                        </option>
                        <option value="english" class="bg-card text-foreground">
                            {{
                                isRTL ? 'الإنجليزية (English)' : 'English Title'
                            }}
                        </option>
                        <option value="arabic" class="bg-card text-foreground">
                            {{ isRTL ? 'العربية (Arabic)' : 'Arabic Title' }}
                        </option>
                    </select>
                </div>

                <!-- Conflict Strategy -->
                <div class="space-y-1.5">
                    <label
                        class="text-muted-foreground block text-xs font-semibold"
                    >
                        {{
                            isRTL
                                ? 'معالجة تضارب الملفات الموجودة'
                                : 'Existing File Conflict Rule'
                        }}
                    </label>
                    <select
                        :value="conflictStrategy"
                        @change="
                            emit(
                                'update:conflictStrategy',
                                ($event.target as HTMLSelectElement)
                                    .value as any,
                            )
                        "
                        class="cf-glass border-border/80 bg-card text-foreground focus:border-primary h-10 w-full rounded-xl border px-4 text-xs font-bold focus:outline-none"
                    >
                        <option value="skip" class="bg-card text-foreground">
                            {{
                                isRTL
                                    ? 'تخطي الملفات الموجودة مسبقاً (Skip)'
                                    : 'Skip if destination exists'
                            }}
                        </option>
                        <option
                            value="keep_higher_quality"
                            class="bg-card text-foreground"
                        >
                            {{
                                isRTL
                                    ? 'استبدال فقط إذا كانت النسخة الجديدة أعلى جودة'
                                    : 'Replace only if higher quality'
                            }}
                        </option>
                        <option value="rename" class="bg-card text-foreground">
                            {{
                                isRTL
                                    ? 'إعادة التسمية التلقائية مع إضافة لاحقة'
                                    : 'Rename with unique suffix'
                            }}
                        </option>
                        <option
                            value="overwrite"
                            class="bg-card text-foreground"
                        >
                            {{
                                isRTL
                                    ? 'استبدال بدون تأكيد (Overwrite)'
                                    : 'Overwrite existing files'
                            }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Naming Switches -->
            <div class="grid grid-cols-1 gap-3 pt-1 sm:grid-cols-2">
                <label
                    class="cf-glass border-border/70 hover:border-primary/40 flex cursor-pointer items-center justify-between gap-3 rounded-xl border p-3 transition-colors"
                >
                    <span class="text-foreground text-xs font-semibold">
                        {{
                            isRTL
                                ? 'جلب أسماء الحلقات من TMDB تلقائياً'
                                : 'Fetch Episode Titles from TMDB'
                        }}
                    </span>
                    <input
                        type="checkbox"
                        :checked="fetchEpisodeTitles"
                        @change="emit('toggle-fetch-episode-titles')"
                        class="border-border bg-card text-primary focus:ring-primary rounded"
                    />
                </label>

                <label
                    class="cf-glass border-border/70 hover:border-primary/40 flex cursor-pointer items-center justify-between gap-3 rounded-xl border p-3 transition-colors"
                >
                    <span class="text-foreground text-xs font-semibold">
                        {{
                            isRTL
                                ? 'إخفاء عنوان الحلقة والاكتفاء بـ SxxExx'
                                : 'Omit Episode Title (SxxExx only)'
                        }}
                    </span>
                    <input
                        type="checkbox"
                        :checked="omitEpisodeTitle"
                        @change="emit('toggle-omit-episode-title')"
                        class="border-border bg-card text-primary focus:ring-primary rounded"
                    />
                </label>
            </div>
        </div>

        <!-- Inline Dry-Run Progress Panel -->
        <div
            v-if="
                isGeneratingPlan ||
                planJobStatus?.status === 'running' ||
                planJobStatus?.status === 'paused'
            "
            class="cf-glass-elevated border-primary/35 bg-primary/10 space-y-3 rounded-3xl border p-5 backdrop-blur-xl"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-0.5">
                    <div
                        class="text-foreground flex items-center gap-2 text-sm font-extrabold"
                    >
                        <span
                            class="h-2 w-2 animate-pulse rounded-full"
                            :class="
                                planJobStatus?.status === 'paused'
                                    ? 'bg-amber-400'
                                    : 'bg-primary'
                            "
                        />
                        {{
                            planJobStatus?.status === 'paused'
                                ? isRTL
                                    ? 'تم إيقاف فحص المحاكاة مؤقتاً'
                                    : 'Dry-Run Analysis Paused'
                                : isRTL
                                  ? 'جارٍ فحص الملفات وبناء خطة التنظيم...'
                                  : 'Analyzing files & building Dry-Run plan...'
                        }}
                    </div>
                    <p
                        class="text-muted-foreground max-w-xl truncate font-mono text-xs"
                    >
                        {{
                            planJobStatus?.current_item ||
                            (isRTL ? 'تهيئة الماسح...' : 'Preparing scanner...')
                        }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-if="planJobStatus?.status === 'running'"
                        type="button"
                        @click="emit('pause-plan')"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/35 bg-amber-500/20 px-3.5 py-2 text-xs font-bold text-amber-300 hover:bg-amber-500/30"
                    >
                        <Pause class="h-3.5 w-3.5" />
                        {{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}
                    </button>
                    <button
                        v-else-if="planJobStatus?.status === 'paused'"
                        type="button"
                        @click="emit('resume-plan')"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-emerald-500/35 bg-emerald-500/20 px-3.5 py-2 text-xs font-bold text-emerald-300 hover:bg-emerald-500/30"
                    >
                        <Play class="h-3.5 w-3.5" />
                        {{ isRTL ? 'استئناف' : 'Resume' }}
                    </button>
                    <button
                        type="button"
                        @click="emit('cancel-plan')"
                        class="border-destructive/35 bg-destructive/20 text-destructive hover:bg-destructive/30 flex cursor-pointer items-center gap-1.5 rounded-xl border px-3.5 py-2 text-xs font-bold"
                    >
                        <X class="h-3.5 w-3.5" />
                        {{ isRTL ? 'إلغاء' : 'Cancel' }}
                    </button>
                </div>
            </div>

            <div class="space-y-1">
                <div
                    class="bg-muted/50 h-2 w-full overflow-hidden rounded-full"
                >
                    <div
                        class="bg-primary h-full transition-all duration-300"
                        :style="{
                            width: `${Math.min(100, Number(planJobStatus?.progress_percent || 0))}%`,
                        }"
                    />
                </div>
                <div
                    class="text-muted-foreground flex justify-between font-mono text-[11px] tabular-nums"
                >
                    <span
                        >{{ planJobStatus?.processed || 0 }} /
                        {{ planJobStatus?.total || 0 }}</span
                    >
                    <span
                        >{{
                            Math.round(
                                Number(planJobStatus?.progress_percent || 0),
                            )
                        }}%</span
                    >
                </div>
            </div>
        </div>

        <!-- Launch Dry-Run Button -->
        <div class="flex justify-end pt-2">
            <button
                type="button"
                @click="emit('start-dry-run')"
                :disabled="
                    isGeneratingPlan ||
                    planJobStatus?.status === 'running' ||
                    planJobStatus?.status === 'paused'
                "
                class="bg-primary text-primary-foreground shadow-primary/25 flex min-h-12 cursor-pointer items-center gap-2 rounded-2xl px-8 py-3.5 text-sm font-black shadow-xl transition-all hover:brightness-105 active:scale-95 disabled:opacity-50"
            >
                <Sparkles class="h-4 w-4 fill-current" />
                {{
                    isRTL
                        ? 'بدء الفحص والمحاكاة الآمنة (Dry Run)'
                        : 'Start Safe Dry-Run Simulation'
                }}
            </button>
        </div>
    </div>
</template>
