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

const props = defineProps<{
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
                        ? 'border-cyan-500 bg-cyan-500/10 shadow-xl shadow-cyan-500/10'
                        : 'border-white/10 bg-slate-900/60 hover:border-white/20'
                "
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3.5">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-cyan-500/20 text-cyan-400"
                        >
                            <Database class="h-6 w-6" />
                        </div>
                        <div>
                            <h3 class="text-lg font-extrabold text-white">
                                {{
                                    isRTL
                                        ? 'المكتبة الافتراضية المفهرسة'
                                        : 'Current Virtual Library'
                                }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-400">
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
                                ? 'border-cyan-400 bg-cyan-500 text-slate-950'
                                : 'border-white/20'
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
                        ? 'border-cyan-500 bg-cyan-500/10 shadow-xl shadow-cyan-500/10'
                        : 'border-white/10 bg-slate-900/60 hover:border-white/20'
                "
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3.5">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-purple-500/20 text-purple-400"
                        >
                            <FolderSearch class="h-6 w-6" />
                        </div>
                        <div>
                            <h3 class="text-lg font-extrabold text-white">
                                {{
                                    isRTL
                                        ? 'مجلد خارجي غير منظم (تحميلات / تورنت)'
                                        : 'Raw Unorganized Folder'
                                }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-400">
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
                                ? 'border-cyan-400 bg-cyan-500 text-slate-950'
                                : 'border-white/20'
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
            class="space-y-4 rounded-3xl border border-white/10 bg-slate-900/60 p-6 backdrop-blur-xl"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div v-if="sourceMode === 'folder'" class="space-y-1.5">
                    <label
                        class="block text-xs font-bold tracking-wider text-slate-300 uppercase"
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
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 font-mono text-xs text-white focus:border-cyan-400 focus:outline-none"
                    />
                </div>

                <div
                    class="space-y-1.5"
                    :class="{ 'md:col-span-2': sourceMode === 'virtual' }"
                >
                    <label
                        class="block text-xs font-bold tracking-wider text-slate-300 uppercase"
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
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 font-mono text-xs text-white focus:border-cyan-400 focus:outline-none"
                    />
                </div>
            </div>
        </div>

        <!-- Strategy Presets -->
        <div class="space-y-4">
            <h2
                class="flex items-center gap-2 text-sm font-extrabold tracking-wider text-white uppercase"
            >
                <Sliders class="h-4 w-4 text-cyan-400" />
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
                            ? 'border-cyan-500 bg-cyan-500/10 shadow-lg shadow-cyan-500/10'
                            : 'border-white/5 bg-slate-900/40 hover:border-white/15'
                    "
                >
                    <div class="mb-2 flex items-center justify-between">
                        <span
                            class="rounded-md bg-white/10 px-2 py-0.5 text-[10px] font-bold text-cyan-300"
                        >
                            {{ strat.tag }}
                        </span>
                        <div
                            class="flex h-5 w-5 items-center justify-center rounded-full border"
                            :class="
                                selectedStrategyId === strat.id
                                    ? 'border-cyan-400 bg-cyan-500 text-slate-950'
                                    : 'border-white/20'
                            "
                        >
                            <Check
                                v-if="selectedStrategyId === strat.id"
                                class="h-3 w-3 stroke-[3]"
                            />
                        </div>
                    </div>
                    <h4 class="text-sm font-bold text-white">
                        {{ isRTL ? strat.nameAr : strat.nameEn }}
                    </h4>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ isRTL ? strat.descAr : strat.descEn }}
                    </p>
                </div>
            </div>
            <!-- Collapsible Template Preview & Custom Editor -->
            <div class="pt-1">
                <button
                    type="button"
                    @click="showAdvancedCustom = !showAdvancedCustom"
                    class="flex items-center gap-1.5 text-xs font-bold text-slate-400 transition-colors hover:text-cyan-300"
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
                    class="mt-3 space-y-4 rounded-2xl border border-white/10 bg-slate-900/60 p-5"
                >
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-300">
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
                            class="w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-2 font-mono text-xs text-cyan-300 focus:border-cyan-400 focus:outline-none"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-300">
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
                            class="w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-2 font-mono text-xs text-cyan-300 focus:border-cyan-400 focus:outline-none"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Naming Preferences & Conflict Handling (Move vs Copy is chosen safely at execution step) -->
        <div
            class="space-y-5 rounded-3xl border border-white/10 bg-slate-900/60 p-6 backdrop-blur-xl"
        >
            <h3
                class="flex items-center gap-2 text-xs font-bold tracking-wider text-slate-300 uppercase"
            >
                <Tag class="h-4 w-4 text-cyan-400" />
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
                        class="flex items-center gap-1.5 text-xs font-semibold text-slate-400"
                    >
                        <Languages class="h-3.5 w-3.5 text-cyan-400" />
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
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-xs font-bold text-white focus:border-cyan-400 focus:outline-none"
                    >
                        <option
                            value="original"
                            class="bg-slate-900 text-white"
                        >
                            {{
                                isRTL
                                    ? 'العنوان الأصلي للمكتبة'
                                    : 'Original Metadata Title'
                            }}
                        </option>
                        <option value="english" class="bg-slate-900 text-white">
                            {{
                                isRTL ? 'الإنجليزية (English)' : 'English Title'
                            }}
                        </option>
                        <option value="arabic" class="bg-slate-900 text-white">
                            {{ isRTL ? 'العربية (Arabic)' : 'Arabic Title' }}
                        </option>
                    </select>
                </div>

                <!-- Conflict Strategy -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-400">
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
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-xs font-bold text-white focus:border-cyan-400 focus:outline-none"
                    >
                        <option value="skip" class="bg-slate-900 text-white">
                            {{
                                isRTL
                                    ? 'تخطي الملفات الموجودة مسبقاً (Skip)'
                                    : 'Skip if destination exists'
                            }}
                        </option>
                        <option
                            value="keep_higher_quality"
                            class="bg-slate-900 text-white"
                        >
                            {{
                                isRTL
                                    ? 'استبدال فقط إذا كانت النسخة الجديدة أعلى جودة'
                                    : 'Replace only if higher quality'
                            }}
                        </option>
                        <option value="rename" class="bg-slate-900 text-white">
                            {{
                                isRTL
                                    ? 'إعادة التسمية التلقائية مع إضافة لاحقة'
                                    : 'Rename with unique suffix'
                            }}
                        </option>
                        <option
                            value="overwrite"
                            class="bg-slate-900 text-white"
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
                    class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/5 p-3 transition-colors hover:border-white/20"
                >
                    <span class="text-xs font-semibold text-slate-300">
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
                        class="rounded border-white/20 bg-slate-900 text-cyan-500 focus:ring-cyan-500"
                    />
                </label>

                <label
                    class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/5 p-3 transition-colors hover:border-white/20"
                >
                    <span class="text-xs font-semibold text-slate-300">
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
                        class="rounded border-white/20 bg-slate-900 text-cyan-500 focus:ring-cyan-500"
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
            class="space-y-3 rounded-3xl border border-cyan-500/30 bg-cyan-500/10 p-5 backdrop-blur-xl"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-0.5">
                    <div
                        class="flex items-center gap-2 text-sm font-extrabold text-white"
                    >
                        <span
                            class="h-2 w-2 animate-pulse rounded-full"
                            :class="
                                planJobStatus?.status === 'paused'
                                    ? 'bg-amber-400'
                                    : 'bg-cyan-400'
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
                        class="max-w-xl truncate font-mono text-xs text-slate-300"
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
                        @click="emit('pause-plan')"
                        class="flex items-center gap-1.5 rounded-xl border border-amber-500/30 bg-amber-500/20 px-3.5 py-2 text-xs font-bold text-amber-300 hover:bg-amber-500/30"
                    >
                        <Pause class="h-3.5 w-3.5" />
                        {{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}
                    </button>
                    <button
                        v-else-if="planJobStatus?.status === 'paused'"
                        @click="emit('resume-plan')"
                        class="flex items-center gap-1.5 rounded-xl border border-emerald-500/30 bg-emerald-500/20 px-3.5 py-2 text-xs font-bold text-emerald-300 hover:bg-emerald-500/30"
                    >
                        <Play class="h-3.5 w-3.5" />
                        {{ isRTL ? 'استئناف' : 'Resume' }}
                    </button>
                    <button
                        @click="emit('cancel-plan')"
                        class="flex items-center gap-1.5 rounded-xl border border-red-500/30 bg-red-500/20 px-3.5 py-2 text-xs font-bold text-red-300 hover:bg-red-500/30"
                    >
                        <X class="h-3.5 w-3.5" />
                        {{ isRTL ? 'إلغاء' : 'Cancel' }}
                    </button>
                </div>
            </div>

            <div class="space-y-1">
                <div
                    class="h-2 w-full overflow-hidden rounded-full bg-white/10"
                >
                    <div
                        class="h-full bg-gradient-to-r from-cyan-500 to-emerald-400 transition-all duration-300"
                        :style="{
                            width: `${Math.min(100, Number(planJobStatus?.progress_percent || 0))}%`,
                        }"
                    />
                </div>
                <div
                    class="flex justify-between font-mono text-[11px] text-slate-400"
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
                @click="emit('start-dry-run')"
                :disabled="
                    isGeneratingPlan ||
                    planJobStatus?.status === 'running' ||
                    planJobStatus?.status === 'paused'
                "
                class="flex items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 to-cyan-400 px-8 py-3.5 text-sm font-black text-slate-950 shadow-xl shadow-cyan-500/20 transition-all hover:from-cyan-400 hover:to-cyan-300 active:scale-95 disabled:opacity-50"
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
