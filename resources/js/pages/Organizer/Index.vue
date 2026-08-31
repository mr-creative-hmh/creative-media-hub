<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    FolderSync, FolderSearch, Eye, Play, CheckCircle2,
    AlertTriangle, FileVideo, ArrowRight, ArrowLeft, RefreshCw, Layers
} from 'lucide-vue-next';

const props = defineProps<{
    defaultMovieTemplate: string;
    defaultSeriesTemplate: string;
}>();

const { t, isRTL } = useI18n();

const sourcePath = ref('C:/Downloads/Incoming');
const targetRoot = ref('C:/Media');
const movieTemplate = ref(props.defaultMovieTemplate);
const seriesTemplate = ref(props.defaultSeriesTemplate);

const isScanning = ref(false);
const isDryRunning = ref(false);
const isExecuting = ref(false);

const scannedFiles = ref<any[]>([]);
const dryRunPlan = ref<any[]>([]);
const executionResult = ref<any | null>(null);

const scanFolder = async () => {
    isScanning.value = true;
    dryRunPlan.value = [];
    executionResult.value = null;

    try {
        const res = await fetch('/api/organizer/scan', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                source_path: sourcePath.value,
                recursive: true,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            scannedFiles.value = data.files || [];
            if (scannedFiles.value.length > 0) {
                await generatePreview();
            }
        }
    } finally {
        isScanning.value = false;
    }
};

const generatePreview = async () => {
    if (scannedFiles.value.length === 0) return;
    isDryRunning.value = true;

    try {
        const res = await fetch('/api/organizer/dry-run', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                files: scannedFiles.value,
                target_root: targetRoot.value,
                movie_template: movieTemplate.value,
                series_template: seriesTemplate.value,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            dryRunPlan.value = data.plan || [];
        }
    } finally {
        isDryRunning.value = false;
    }
};

const executeOrganize = async (mode: 'move' | 'copy') => {
    if (dryRunPlan.value.length === 0) return;
    isExecuting.value = true;

    try {
        const res = await fetch('/api/organizer/execute', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                plan: dryRunPlan.value,
                mode: mode,
            }),
        });

        if (res.ok) {
            executionResult.value = await res.json();
            dryRunPlan.value = [];
            scannedFiles.value = [];
        }
    } finally {
        isExecuting.value = false;
    }
};
</script>

<template>
    <Head :title="t('organizer.title')" />

    <AppLayout v-slot="{ play }">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30 flex items-center justify-center">
                    <FolderSync class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white">
                        {{ t('organizer.title') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-0.5">
                        {{ t('organizer.subtitle') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- 1. Source & Target Directory Config -->
        <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-white/10 mb-8 space-y-6 shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Source Folder -->
                <div class="space-y-2">
                    <label class="font-bold text-xs text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                        {{ t('organizer.source_folder') }}
                    </label>
                    <input
                        type="text"
                        v-model="sourcePath"
                        placeholder="e.g. C:/Downloads/Incoming"
                        class="w-full h-11 rounded-xl bg-slate-50 dark:bg-white/[0.04] border border-slate-300 dark:border-white/15 px-4 text-xs font-mono text-slate-900 dark:text-slate-100 focus:border-indigo-500 outline-none"
                    />
                </div>

                <!-- Destination Root -->
                <div class="space-y-2">
                    <label class="font-bold text-xs text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                        {{ t('organizer.target_root') }}
                    </label>
                    <input
                        type="text"
                        v-model="targetRoot"
                        placeholder="e.g. C:/Media or D:/PlexLibrary"
                        class="w-full h-11 rounded-xl bg-slate-50 dark:bg-white/[0.04] border border-slate-300 dark:border-white/15 px-4 text-xs font-mono text-slate-900 dark:text-slate-100 focus:border-indigo-500 outline-none"
                    />
                </div>
            </div>

            <!-- Templates -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2 border-t border-slate-200 dark:border-white/10">
                <div class="space-y-2">
                    <label class="font-bold text-xs text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                        {{ t('organizer.movie_pattern') }}
                    </label>
                    <input
                        type="text"
                        v-model="movieTemplate"
                        class="w-full h-10 rounded-xl bg-slate-50 dark:bg-white/[0.04] border border-slate-300 dark:border-white/15 px-4 text-xs font-mono text-slate-900 dark:text-slate-100 focus:border-indigo-500 outline-none"
                    />
                </div>

                <div class="space-y-2">
                    <label class="font-bold text-xs text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                        {{ t('organizer.series_pattern') }}
                    </label>
                    <input
                        type="text"
                        v-model="seriesTemplate"
                        class="w-full h-10 rounded-xl bg-slate-50 dark:bg-white/[0.04] border border-slate-300 dark:border-white/15 px-4 text-xs font-mono text-slate-900 dark:text-slate-100 focus:border-indigo-500 outline-none"
                    />
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-between flex-wrap gap-4 pt-4 border-t border-slate-200 dark:border-white/10">
                <button
                    @click="scanFolder"
                    :disabled="isScanning"
                    class="flex items-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs shadow-lg shadow-indigo-600/30 active:scale-95 transition-all cursor-pointer"
                >
                    <FolderSearch class="w-4 h-4" />
                    <span>{{ isScanning ? (isRTL ? 'جاري الفحص...' : 'Scanning Folder...') : (isRTL ? 'فحص مجلد التنزيلات' : 'Scan Source Folder') }}</span>
                </button>

                <div v-if="dryRunPlan.length > 0" class="flex items-center gap-3">
                    <button
                        @click="executeOrganize('copy')"
                        :disabled="isExecuting"
                        class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-extrabold text-xs active:scale-95 transition-all cursor-pointer"
                    >
                        <span>{{ isRTL ? 'نسخ منظم (Keep Original)' : 'Execute Copy' }}</span>
                    </button>
                    <button
                        @click="executeOrganize('move')"
                        :disabled="isExecuting"
                        class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-extrabold text-xs shadow-lg shadow-cyan-500/20 active:scale-95 transition-all cursor-pointer"
                    >
                        <Play class="w-3.5 h-3.5 fill-current" />
                        <span>{{ isRTL ? 'نقل منظم (Move & Organize)' : 'Execute Move' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 2. Dry-Run Diff Simulation Table -->
        <div v-if="dryRunPlan.length > 0" class="glass-panel rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-white/10 mb-8 space-y-4 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-white/10">
                <div>
                    <h3 class="font-bold text-base text-slate-900 dark:text-white">
                        {{ t('organizer.dry_run_preview') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ isRTL ? 'معاينة أسماء الملفات قبل التنفيذ الحقيقي' : 'Verify destination paths and filenames before executing physical disk changes' }}
                    </p>
                </div>
                <span class="cinema-badge bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30">
                    {{ dryRunPlan.length }} {{ isRTL ? 'ملفات جاهزة' : 'Planned' }}
                </span>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-white/5 overflow-x-auto">
                <div
                    v-for="(item, idx) in dryRunPlan"
                    :key="`plan-${idx}`"
                    class="py-3.5 grid grid-cols-1 lg:grid-cols-12 gap-3 items-center text-xs"
                >
                    <!-- Original Path -->
                    <div class="lg:col-span-5 truncate text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                        {{ item.original_filename || item.source }}
                    </div>

                    <!-- Arrow -->
                    <div class="lg:col-span-1 flex justify-center text-indigo-600 dark:text-indigo-400">
                        <component :is="isRTL ? ArrowLeft : ArrowRight" class="w-4 h-4" />
                    </div>

                    <!-- Destination Path -->
                    <div class="lg:col-span-6 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400 truncate">
                        {{ item.destination }}
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Execution Result Confirmation -->
        <div v-if="executionResult" class="glass-panel rounded-3xl p-6 sm:p-8 border border-emerald-500/40 bg-emerald-500/5 dark:bg-emerald-950/20 space-y-3 shadow-sm">
            <div class="flex items-center gap-3">
                <CheckCircle2 class="w-6 h-6 text-emerald-500" />
                <h4 class="font-extrabold text-sm text-emerald-700 dark:text-emerald-300">
                    {{ isRTL ? 'تم تنظيم الملفات بنجاح!' : 'Organization Completed Successfully!' }}
                </h4>
            </div>
            <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                {{ isRTL ? `تمت معالجة ${executionResult.count || 0} ملفات ونقلها بالهيكلة المحددة.` : `Successfully organized ${executionResult.count || 0} files according to standard templates.` }}
            </p>
        </div>
    </AppLayout>
</template>
