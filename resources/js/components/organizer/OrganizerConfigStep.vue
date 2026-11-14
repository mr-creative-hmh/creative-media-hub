<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Database, FolderSearch, Sliders, ChevronDown, Check,
    Play, Pause, X, Sparkles, Languages, Tag
} from 'lucide-vue-next';

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
    (e: 'update:conflictStrategy', val: 'skip' | 'overwrite' | 'rename' | 'keep_higher_quality'): void;
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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Virtual Library -->
            <div
                @click="emit('update:sourceMode', 'virtual')"
                class="relative rounded-3xl p-6 border transition-all cursor-pointer backdrop-blur-xl group"
                :class="sourceMode === 'virtual' ? 'bg-cyan-500/10 border-cyan-500 shadow-xl shadow-cyan-500/10' : 'bg-slate-900/60 border-white/10 hover:border-white/20'"
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                            <Database class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="font-extrabold text-lg text-white">
                                {{ isRTL ? 'المكتبة الافتراضية المفهرسة' : 'Current Virtual Library' }}
                            </h3>
                            <p class="text-xs text-slate-400 mt-1">
                                {{ isRTL ? 'تنظيم جميع الأفلام والمسلسلات الموجودة حالياً داخل قاعدة البيانات' : 'Organize all movies & series already scanned into your local database' }}
                            </p>
                        </div>
                    </div>
                    <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-colors"
                        :class="sourceMode === 'virtual' ? 'border-cyan-400 bg-cyan-500 text-slate-950' : 'border-white/20'">
                        <Check v-if="sourceMode === 'virtual'" class="w-3.5 h-3.5 stroke-[3]" />
                    </div>
                </div>
            </div>

            <!-- Custom Raw Folder -->
            <div
                @click="emit('update:sourceMode', 'folder')"
                class="relative rounded-3xl p-6 border transition-all cursor-pointer backdrop-blur-xl group"
                :class="sourceMode === 'folder' ? 'bg-cyan-500/10 border-cyan-500 shadow-xl shadow-cyan-500/10' : 'bg-slate-900/60 border-white/10 hover:border-white/20'"
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-purple-500/20 text-purple-400 flex items-center justify-center">
                            <FolderSearch class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="font-extrabold text-lg text-white">
                                {{ isRTL ? 'مجلد خارجي غير منظم (تحميلات / تورنت)' : 'Raw Unorganized Folder' }}
                            </h3>
                            <p class="text-xs text-slate-400 mt-1">
                                {{ isRTL ? 'مسح مجلد تورنت أو تحميلات وإعادة هيكلتها بالكامل' : 'Scan a raw downloads/torrent folder and generate a clean target structure' }}
                            </p>
                        </div>
                    </div>
                    <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-colors"
                        :class="sourceMode === 'folder' ? 'border-cyan-400 bg-cyan-500 text-slate-950' : 'border-white/20'">
                        <Check v-if="sourceMode === 'folder'" class="w-3.5 h-3.5 stroke-[3]" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Target Root & Source Folder Input Card -->
        <div class="p-6 rounded-3xl bg-slate-900/60 border border-white/10 backdrop-blur-xl space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-if="sourceMode === 'folder'" class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-300 uppercase tracking-wider block">
                        {{ isRTL ? 'مسار المجلد المصدر' : 'Source Folder Path' }}
                    </label>
                    <input
                        :value="sourceFolder"
                        @input="emit('update:sourceFolder', ($event.target as HTMLInputElement).value)"
                        type="text"
                        placeholder="e.g. D:/Downloads"
                        class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white font-mono text-xs focus:outline-none focus:border-cyan-400"
                    />
                </div>

                <div class="space-y-1.5" :class="{ 'md:col-span-2': sourceMode === 'virtual' }">
                    <label class="text-xs font-bold text-slate-300 uppercase tracking-wider block">
                        {{ isRTL ? 'مسار الوجهة الرئيسية للمكتبة المنظمة' : 'Destination Library Root Path' }}
                    </label>
                    <input
                        :value="targetRoot"
                        @input="emit('update:targetRoot', ($event.target as HTMLInputElement).value)"
                        type="text"
                        placeholder="e.g. H:/Entertainment"
                        class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white font-mono text-xs focus:outline-none focus:border-cyan-400"
                    />
                </div>
            </div>
        </div>

        <!-- Strategy Presets -->
        <div class="space-y-4">
            <h2 class="text-sm font-extrabold text-white uppercase tracking-wider flex items-center gap-2">
                <Sliders class="w-4 h-4 text-cyan-400" />
                {{ isRTL ? 'اختر نمط وهيكل التنظيم المفضل' : 'Choose Organization Preset & Structure' }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                    v-for="strat in strategies"
                    :key="strat.id"
                    @click="handleSelectStrategy(strat)"
                    class="p-5 rounded-2xl border transition-all cursor-pointer backdrop-blur-xl relative"
                    :class="selectedStrategyId === strat.id
                        ? 'bg-cyan-500/10 border-cyan-500 shadow-lg shadow-cyan-500/10'
                        : 'bg-slate-900/40 border-white/5 hover:border-white/15'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-white/10 text-cyan-300">
                            {{ strat.tag }}
                        </span>
                        <div class="w-5 h-5 rounded-full border flex items-center justify-center"
                            :class="selectedStrategyId === strat.id ? 'border-cyan-400 bg-cyan-500 text-slate-950' : 'border-white/20'">
                            <Check v-if="selectedStrategyId === strat.id" class="w-3 h-3 stroke-[3]" />
                        </div>
                    </div>
                    <h4 class="font-bold text-white text-sm">
                        {{ isRTL ? strat.nameAr : strat.nameEn }}
                    </h4>
                    <p class="text-xs text-slate-400 mt-1">
                        {{ isRTL ? strat.descAr : strat.descEn }}
                    </p>
                </div>
            </div>
            <!-- Collapsible Template Preview & Custom Editor -->
            <div class="pt-1">
                <button
                    type="button"
                    @click="showAdvancedCustom = !showAdvancedCustom"
                    class="text-xs font-bold text-slate-400 hover:text-cyan-300 transition-colors flex items-center gap-1.5"
                >
                    <ChevronDown class="w-4 h-4 transition-transform" :class="{ 'rotate-180': showAdvancedCustom }" />
                    {{ isRTL ? 'معاينة وتخصيص قوالب المسارات المتقدمة' : 'Preview & Customize Path Templates' }}
                </button>

                <div v-if="showAdvancedCustom || selectedStrategyId === 'custom'" class="mt-3 p-5 rounded-2xl bg-slate-900/60 border border-white/10 space-y-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-300 block">
                            {{ isRTL ? 'قالب مسار الأفلام' : 'Movie Path Template' }}
                        </label>
                        <input
                            :value="movieTemplate"
                            @input="emit('update:movieTemplate', ($event.target as HTMLInputElement).value)"
                            type="text"
                            dir="ltr"
                            class="w-full px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-cyan-300 font-mono text-xs focus:outline-none focus:border-cyan-400"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-300 block">
                            {{ isRTL ? 'قالب مسار المسلسلات' : 'Series Path Template' }}
                        </label>
                        <input
                            :value="seriesTemplate"
                            @input="emit('update:seriesTemplate', ($event.target as HTMLInputElement).value)"
                            type="text"
                            dir="ltr"
                            class="w-full px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-cyan-300 font-mono text-xs focus:outline-none focus:border-cyan-400"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Naming Preferences & Conflict Handling (Move vs Copy is chosen safely at execution step) -->
        <div class="p-6 rounded-3xl bg-slate-900/60 border border-white/10 backdrop-blur-xl space-y-5">
            <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-2">
                <Tag class="w-4 h-4 text-cyan-400" />
                {{ isRTL ? 'تفضيلات التسمية والتعارضات' : 'Naming Preferences & Conflict Rules' }}
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Title Language -->
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-400 flex items-center gap-1.5">
                        <Languages class="w-3.5 h-3.5 text-cyan-400" />
                        {{ isRTL ? 'لغة عناوين المجلدات والملفات' : 'Preferred Title Language' }}
                    </label>
                    <select
                        :value="titleLanguage"
                        @change="emit('update:titleLanguage', ($event.target as HTMLSelectElement).value as any)"
                        class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs font-bold focus:outline-none focus:border-cyan-400"
                    >
                        <option value="original" class="bg-slate-900 text-white">
                            {{ isRTL ? 'العنوان الأصلي للمكتبة' : 'Original Metadata Title' }}
                        </option>
                        <option value="english" class="bg-slate-900 text-white">
                            {{ isRTL ? 'الإنجليزية (English)' : 'English Title' }}
                        </option>
                        <option value="arabic" class="bg-slate-900 text-white">
                            {{ isRTL ? 'العربية (Arabic)' : 'Arabic Title' }}
                        </option>
                    </select>
                </div>

                <!-- Conflict Strategy -->
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-400 block">
                        {{ isRTL ? 'معالجة تضارب الملفات الموجودة' : 'Existing File Conflict Rule' }}
                    </label>
                    <select
                        :value="conflictStrategy"
                        @change="emit('update:conflictStrategy', ($event.target as HTMLSelectElement).value as any)"
                        class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs font-bold focus:outline-none focus:border-cyan-400"
                    >
                        <option value="skip" class="bg-slate-900 text-white">
                            {{ isRTL ? 'تخطي الملفات الموجودة مسبقاً (Skip)' : 'Skip if destination exists' }}
                        </option>
                        <option value="keep_higher_quality" class="bg-slate-900 text-white">
                            {{ isRTL ? 'استبدال فقط إذا كانت النسخة الجديدة أعلى جودة' : 'Replace only if higher quality' }}
                        </option>
                        <option value="rename" class="bg-slate-900 text-white">
                            {{ isRTL ? 'إعادة التسمية التلقائية مع إضافة لاحقة' : 'Rename with unique suffix' }}
                        </option>
                        <option value="overwrite" class="bg-slate-900 text-white">
                            {{ isRTL ? 'استبدال بدون تأكيد (Overwrite)' : 'Overwrite existing files' }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Naming Switches -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                <label class="flex items-center justify-between gap-3 p-3 rounded-xl bg-white/5 border border-white/10 cursor-pointer hover:border-white/20 transition-colors">
                    <span class="text-xs font-semibold text-slate-300">
                        {{ isRTL ? 'جلب أسماء الحلقات من TMDB تلقائياً' : 'Fetch Episode Titles from TMDB' }}
                    </span>
                    <input
                        type="checkbox"
                        :checked="fetchEpisodeTitles"
                        @change="emit('toggle-fetch-episode-titles')"
                        class="rounded border-white/20 bg-slate-900 text-cyan-500 focus:ring-cyan-500"
                    />
                </label>

                <label class="flex items-center justify-between gap-3 p-3 rounded-xl bg-white/5 border border-white/10 cursor-pointer hover:border-white/20 transition-colors">
                    <span class="text-xs font-semibold text-slate-300">
                        {{ isRTL ? 'إخفاء عنوان الحلقة والاكتفاء بـ SxxExx' : 'Omit Episode Title (SxxExx only)' }}
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
            v-if="isGeneratingPlan || planJobStatus?.status === 'running' || planJobStatus?.status === 'paused'"
            class="p-5 rounded-3xl bg-cyan-500/10 border border-cyan-500/30 backdrop-blur-xl space-y-3"
        >
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="space-y-0.5">
                    <div class="text-sm font-extrabold text-white flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full animate-pulse" :class="planJobStatus?.status === 'paused' ? 'bg-amber-400' : 'bg-cyan-400'" />
                        {{ planJobStatus?.status === 'paused'
                            ? (isRTL ? 'تم إيقاف فحص المحاكاة مؤقتاً' : 'Dry-Run Analysis Paused')
                            : (isRTL ? 'جارٍ فحص الملفات وبناء خطة التنظيم...' : 'Analyzing files & building Dry-Run plan...') }}
                    </div>
                    <p class="text-xs text-slate-300 font-mono truncate max-w-xl">
                        {{ planJobStatus?.current_item || (isRTL ? 'تهيئة الماسح...' : 'Preparing scanner...') }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-if="planJobStatus?.status === 'running'"
                        @click="emit('pause-plan')"
                        class="px-3.5 py-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 text-xs font-bold flex items-center gap-1.5"
                    >
                        <Pause class="w-3.5 h-3.5" />
                        {{ isRTL ? 'إيقاف مؤقت' : 'Pause' }}
                    </button>
                    <button
                        v-else-if="planJobStatus?.status === 'paused'"
                        @click="emit('resume-plan')"
                        class="px-3.5 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/30 text-xs font-bold flex items-center gap-1.5"
                    >
                        <Play class="w-3.5 h-3.5" />
                        {{ isRTL ? 'استئناف' : 'Resume' }}
                    </button>
                    <button
                        @click="emit('cancel-plan')"
                        class="px-3.5 py-2 rounded-xl bg-red-500/20 hover:bg-red-500/30 text-red-300 border border-red-500/30 text-xs font-bold flex items-center gap-1.5"
                    >
                        <X class="w-3.5 h-3.5" />
                        {{ isRTL ? 'إلغاء' : 'Cancel' }}
                    </button>
                </div>
            </div>

            <div class="space-y-1">
                <div class="w-full h-2 rounded-full bg-white/10 overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-cyan-500 to-emerald-400 transition-all duration-300"
                        :style="{ width: `${Math.min(100, Number(planJobStatus?.progress_percent || 0))}%` }"
                    />
                </div>
                <div class="flex justify-between text-[11px] font-mono text-slate-400">
                    <span>{{ planJobStatus?.processed || 0 }} / {{ planJobStatus?.total || 0 }}</span>
                    <span>{{ Math.round(Number(planJobStatus?.progress_percent || 0)) }}%</span>
                </div>
            </div>
        </div>

        <!-- Launch Dry-Run Button -->
        <div class="pt-2 flex justify-end">
            <button
                @click="emit('start-dry-run')"
                :disabled="isGeneratingPlan || planJobStatus?.status === 'running' || planJobStatus?.status === 'paused'"
                class="px-8 py-3.5 rounded-2xl bg-gradient-to-r from-cyan-500 to-cyan-400 hover:from-cyan-400 hover:to-cyan-300 disabled:opacity-50 text-slate-950 font-black text-sm shadow-xl shadow-cyan-500/20 transition-all active:scale-95 flex items-center gap-2"
            >
                <Sparkles class="w-4 h-4 fill-current" />
                {{ isRTL ? 'بدء الفحص والمحاكاة الآمنة (Dry Run)' : 'Start Safe Dry-Run Simulation' }}
            </button>
        </div>
    </div>
</template>

