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
        }
    } finally {
        isExecuting.value = false;
    }
};

const toggleSelectAll = (e: any) => {
    const checked = e.target.checked;
    dryRunPlan.value.forEach(item => {
        if (item.status === 'ready') item.selected = checked;
    });
};
</script>

<template>
    <Head :title="t('organizer.title')" />

    <AppLayout v-slot="{ play }">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                    <FolderSync class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                        {{ t('organizer.title') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                        {{ t('organizer.subtitle') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Configuration Settings Card -->
        <div class="glass-panel rounded-3xl p-6 sm:p-8 mb-8 border border-white/10 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Source Folder -->
                <div class="space-y-2">
                    <label class="font-bold text-xs text-slate-300 uppercase tracking-wider">
                        {{ t('organizer.source_folder') }}
                    </label>
                    <input
                        type="text"
                        v-model="sourcePath"
                        placeholder="e.g. C:/Downloads/Incoming"
                        class="w-full h-11 rounded-xl bg-white/[0.04] border border-white/15 px-4 text-sm text-slate-100 font-mono focus:border-cyan-500 outline-none"
                    />
                </div>

                <!-- Target Root -->
                <div class="space-y-2">
                    <label class="font-bold text-xs text-slate-300 uppercase tracking-wider">
                        {{ t('organizer.target_folder') }}
                    </label>
                    <input
                        type="text"
                        v-model="targetRoot"
                        placeholder="e.g. C:/Media"
                        class="w-full h-11 rounded-xl bg-white/[0.04] border border-white/15 px-4 text-sm text-slate-100 font-mono focus:border-cyan-500 outline-none"
                    />
                </div>
            </div>

            <!-- Naming Templates -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-white/10">
                <div class="space-y-2">
                    <label class="font-bold text-xs text-slate-300 uppercase tracking-wider">
                        {{ t('organizer.movie_template') }}
                    </label>
                    <input
                        type="text"
                        v-model="movieTemplate"
                        class="w-full h-10 rounded-xl bg-white/[0.04] border border-white/15 px-4 text-xs text-slate-200 font-mono focus:border-cyan-500 outline-none"
                    />
                </div>
                <div class="space-y-2">
                    <label class="font-bold text-xs text-slate-300 uppercase tracking-wider">
                        {{ t('organizer.series_template') }}
                    </label>
                    <input
                        type="text"
                        v-model="seriesTemplate"
                        class="w-full h-10 rounded-xl bg-white/[0.04] border border-white/15 px-4 text-xs text-slate-200 font-mono focus:border-cyan-500 outline-none"
                    />
                </div>
            </div>

            <!-- Action Trigger Buttons -->
            <div class="flex items-center gap-4 pt-4 border-t border-white/10">
                <button
                    @click="scanFolder"
                    :disabled="isScanning"
                    class="flex items-center gap-2.5 px-6 py-3 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-extrabold text-sm shadow-xl shadow-cyan-500/20 active:scale-95 transition-all"
                >
                    <RefreshCw class="w-4 h-4" :class="isScanning ? 'animate-spin' : ''" />
                    <span>{{ isScanning ? (isRTL ? 'جاري الفحص...' : 'Scanning...') : t('organizer.scan_button') }}</span>
                </button>
            </div>
        </div>

        <!-- Dry Run Diff Table Results -->
        <div v-if="dryRunPlan.length > 0" class="glass-panel rounded-3xl p-6 border border-white/10 space-y-4 mb-8">
            <div class="flex items-center justify-between flex-wrap gap-4 pb-4 border-b border-white/10">
                <div class="flex items-center gap-2">
                    <Eye class="w-5 h-5 text-cyan-400" />
                    <h3 class="font-bold text-base text-white">
                        {{ isRTL ? 'معاينة التغييرات وخطة إعادة التسمية (Dry-Run)' : 'Restructuring Dry-Run Preview' }}
                    </h3>
                    <span class="cinema-badge bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">
                        {{ dryRunPlan.length }} {{ isRTL ? 'ملفات' : 'files' }}
                    </span>
                </div>

                <!-- Execution Action Buttons -->
                <div class="flex items-center gap-3">
                    <button
                        @click="executeOrganize('move')"
                        :disabled="isExecuting"
                        class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 font-black text-xs shadow-lg shadow-emerald-500/20 active:scale-95 transition-all"
                    >
                        <CheckCircle2 class="w-4 h-4" />
                        <span>{{ isExecuting ? (isRTL ? 'جاري التنظيم...' : 'Executing...') : t('organizer.execute_move') }}</span>
                    </button>
                </div>
            </div>

            <!-- Diff Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse font-mono" :dir="isRTL ? 'rtl' : 'ltr'">
                    <thead>
                        <tr class="border-b border-white/10 text-slate-400">
                            <th class="py-3 px-2 w-8">
                                <input type="checkbox" checked @change="toggleSelectAll" class="rounded bg-white/10 border-white/20 text-cyan-500" />
                            </th>
                            <th class="py-3 px-3">{{ t('organizer.source_path') }}</th>
                            <th class="py-3 px-3 w-8"></th>
                            <th class="py-3 px-3 text-cyan-400">{{ t('organizer.dest_path') }}</th>
                            <th class="py-3 px-3">{{ t('organizer.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr
                            v-for="(item, idx) in dryRunPlan"
                            :key="idx"
                            class="hover:bg-white/[0.03] transition-colors"
                        >
                            <td class="py-3 px-2">
                                <input
                                    type="checkbox"
                                    v-model="item.selected"
                                    :disabled="item.status !== 'ready'"
                                    class="rounded bg-white/10 border-white/20 text-cyan-500"
                                />
                            </td>
                            <td class="py-3 px-3 text-slate-300 max-w-xs truncate" :title="item.source_path">
                                {{ item.source_path }}
                            </td>
                            <td class="py-3 px-3 text-cyan-400">
                                <component :is="isRTL ? ArrowLeft : ArrowRight" class="w-3.5 h-3.5" />
                            </td>
                            <td class="py-3 px-3 font-bold text-emerald-400 max-w-sm truncate" :title="item.destination_path">
                                {{ item.destination_path }}
                            </td>
                            <td class="py-3 px-3">
                                <span
                                    v-if="item.status === 'ready'"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30"
                                >
                                    {{ t('organizer.ready') }}
                                </span>
                                <span
                                    v-else-if="item.status === 'collision_exists'"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30"
                                >
                                    {{ t('organizer.collision') }}
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-500/20 text-slate-400 border border-slate-500/30"
                                >
                                    {{ t('organizer.identical') }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Success Result Banner -->
        <div v-if="executionResult" class="glass-panel rounded-3xl p-6 border border-emerald-500/40 bg-emerald-950/20 mb-8">
            <div class="flex items-center gap-3">
                <CheckCircle2 class="w-6 h-6 text-emerald-400" />
                <div>
                    <h4 class="font-extrabold text-sm text-emerald-300">
                        {{ isRTL ? 'تم تنظيم الملفات بنجاح!' : 'Organization Completed Successfully!' }}
                    </h4>
                    <p class="text-xs text-slate-300 mt-0.5">
                        {{ executionResult.success_count }} {{ isRTL ? 'ملف تم نقله وهيكلته في المكتبة.' : 'files moved and structured into your library.' }}
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
