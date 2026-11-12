<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Database, FolderSearch, Sliders, ChevronDown, Check,
    Move, Copy, AlertTriangle, Play, Sparkles, FolderSync, Plus, Trash2, Eye
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
    actionType: 'move' | 'copy';
    conflictStrategy: 'skip' | 'overwrite' | 'rename' | 'keep_higher_quality';
    omitEpisodeTitle: boolean;
    fetchEpisodeTitles: boolean;
    titleLanguage: 'original' | 'english' | 'arabic';
    watcher: any;
    isGeneratingPlan: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:sourceMode', val: 'virtual' | 'folder'): void;
    (e: 'update:sourceFolder', val: string): void;
    (e: 'update:targetRoot', val: string): void;
    (e: 'update:selectedStrategyId', val: string): void;
    (e: 'update:movieTemplate', val: string): void;
    (e: 'update:seriesTemplate', val: string): void;
    (e: 'update:actionType', val: 'move' | 'copy'): void;
    (e: 'update:conflictStrategy', val: 'skip' | 'overwrite' | 'rename' | 'keep_higher_quality'): void;
    (e: 'update:titleLanguage', val: 'original' | 'english' | 'arabic'): void;
    (e: 'toggle-omit-episode-title'): void;
    (e: 'toggle-fetch-episode-titles'): void;
    (e: 'toggle-watcher'): void;
    (e: 'add-watch-folder', path: string): void;
    (e: 'remove-watch-folder', path: string): void;
    (e: 'start-dry-run'): void;
}>();

const { t, isRTL } = useI18n();

const showAdvancedCustom = ref(false);
const newFolderInput = ref('');

const handleSelectStrategy = (strat: OrganizerStrategy) => {
    emit('update:selectedStrategyId', strat.id);
    if (strat.id !== 'custom') {
        emit('update:movieTemplate', strat.movie);
        emit('update:seriesTemplate', strat.series);
    }
};

const handleAddFolder = () => {
    if (!newFolderInput.value.trim()) return;
    emit('add-watch-folder', newFolderInput.value.trim());
    newFolderInput.value = '';
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
        </div>

        <!-- Operation Mode (Move vs Copy) & Conflict Handling -->
        <div class="p-6 rounded-3xl bg-slate-900/60 border border-white/10 backdrop-blur-xl space-y-4">
            <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider block">
                {{ isRTL ? 'إعدادات النقل والتعارض' : 'Execution & Conflict Rules' }}
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Action Type -->
                <div class="flex items-center gap-2 p-1.5 rounded-xl bg-white/5 border border-white/10">
                    <button
                        @click="emit('update:actionType', 'move')"
                        class="flex-1 py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5"
                        :class="actionType === 'move' ? 'bg-cyan-500 text-slate-950 font-black shadow-sm' : 'text-slate-400 hover:text-white'"
                    >
                        <Move class="w-3.5 h-3.5" />
                        {{ isRTL ? 'نقل الملفات (Move)' : 'Move (Instant)' }}
                    </button>
                    <button
                        @click="emit('update:actionType', 'copy')"
                        class="flex-1 py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5"
                        :class="actionType === 'copy' ? 'bg-cyan-500 text-slate-950 font-black shadow-sm' : 'text-slate-400 hover:text-white'"
                    >
                        <Copy class="w-3.5 h-3.5" />
                        {{ isRTL ? 'نسخ الملفات (Copy)' : 'Copy (Preserve Original)' }}
                    </button>
                </div>

                <!-- Conflict Strategy -->
                <select
                    :value="conflictStrategy"
                    @change="emit('update:conflictStrategy', ($event.target as HTMLSelectElement).value as any)"
                    class="px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs font-bold focus:outline-none focus:border-cyan-400"
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

        <!-- Launch Dry-Run Button -->
        <div class="pt-2 flex justify-end">
            <button
                @click="emit('start-dry-run')"
                :disabled="isGeneratingPlan"
                class="px-8 py-3.5 rounded-2xl bg-gradient-to-r from-cyan-500 to-cyan-400 hover:from-cyan-400 hover:to-cyan-300 disabled:opacity-50 text-slate-950 font-black text-sm shadow-xl shadow-cyan-500/20 transition-all active:scale-95 flex items-center gap-2"
            >
                <Sparkles class="w-4 h-4 fill-current" />
                {{ isRTL ? 'بدء الفحص والمحاكاة الآمنة (Dry Run)' : 'Start Safe Dry-Run Simulation' }}
            </button>
        </div>
    </div>
</template>

