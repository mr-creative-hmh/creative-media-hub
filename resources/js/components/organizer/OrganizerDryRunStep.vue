<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Search, CheckSquare, Square, Edit3, RotateCcw,
    CheckCircle2, AlertTriangle, XCircle, ArrowRight, ArrowLeft,
    Play
} from 'lucide-vue-next';
import type { PlanItem } from '@/components/organizer/OrganizerItemCorrectionModal.vue';

const props = defineProps<{
    planItems: PlanItem[];
}>();

const emit = defineEmits<{
    (e: 'back'): void;
    (e: 'execute'): void;
    (e: 'edit-item', item: PlanItem): void;
    (e: 'reset-item', item: PlanItem): void;
    (e: 'batch-edit', items: PlanItem[]): void;
}>();

const { isRTL } = useI18n();

const searchQuery = ref('');
const statusFilter = ref<'all' | 'ready' | 'conflict' | 'warning' | 'skipped'>('all');
const selectedPaths = ref<Set<string>>(new Set());
const currentPage = ref(1);
const rowsPerPage = ref(15);

watch([statusFilter, searchQuery], () => {
    currentPage.value = 1;
});

// Filtered and Searched Plan Items
const filteredItems = computed(() => {
    let items = props.planItems;

    if (statusFilter.value !== 'all') {
        items = items.filter(item => {
            if (statusFilter.value === 'ready') return item.status === 'ready';
            if (statusFilter.value === 'conflict') return item.status === 'conflict';
            if (statusFilter.value === 'warning') return item.status === 'warning';
            if (statusFilter.value === 'skipped') return item.status === 'skipped';
            return true;
        });
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        items = items.filter(item => {
            return (item.clean_title && item.clean_title.toLowerCase().includes(q)) ||
                   (item.source_path && item.source_path.toLowerCase().includes(q)) ||
                   (item.destination_path && item.destination_path.toLowerCase().includes(q));
        });
    }

    return items;
});

// Counts
const totalCount = computed(() => props.planItems.length);
const readyCount = computed(() => props.planItems.filter(i => i.status === 'ready').length);
const conflictCount = computed(() => props.planItems.filter(i => i.status === 'conflict').length);
const warningCount = computed(() => props.planItems.filter(i => i.status === 'warning').length);
const skippedCount = computed(() => props.planItems.filter(i => i.status === 'skipped').length);

// Pagination
const totalPages = computed(() => Math.ceil(filteredItems.value.length / rowsPerPage.value) || 1);
const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;
    return filteredItems.value.slice(start, start + rowsPerPage.value);
});

// Selection helpers keyed by source_path so selections hold across pagination & filters
const isAllSelected = computed(() => {
    return filteredItems.value.length > 0 && filteredItems.value.every(i => selectedPaths.value.has(i.source_path));
});

const toggleSelectAll = () => {
    const next = new Set(selectedPaths.value);
    if (isAllSelected.value) {
        filteredItems.value.forEach(i => next.delete(i.source_path));
    } else {
        filteredItems.value.forEach(i => next.add(i.source_path));
    }
    selectedPaths.value = next;
};

const toggleSelectRow = (sourcePath: string) => {
    const next = new Set(selectedPaths.value);
    if (next.has(sourcePath)) {
        next.delete(sourcePath);
    } else {
        next.add(sourcePath);
    }
    selectedPaths.value = next;
};

const handleBatchEdit = () => {
    const selected = filteredItems.value.filter(i => selectedPaths.value.has(i.source_path));
    if (selected.length > 0) {
        emit('batch-edit', selected);
    }
};
</script>

<template>
    <div class="space-y-6">
        <!-- Summary Stats Card -->
        <div class="p-6 rounded-3xl bg-slate-900/60 border border-white/10 backdrop-blur-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 flex-1">
                <div class="p-4 rounded-2xl bg-white/5 border border-white/5">
                    <div class="text-xs text-slate-400 font-semibold uppercase tracking-wider">
                        {{ isRTL ? 'إجمالي الملفات' : 'Total Items' }}
                    </div>
                    <div class="text-xl font-black text-white mt-1">{{ totalCount }}</div>
                </div>
                <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20">
                    <div class="text-xs text-emerald-400 font-semibold uppercase tracking-wider">
                        {{ isRTL ? 'جاهزة للنقل' : 'Ready' }}
                    </div>
                    <div class="text-xl font-black text-emerald-300 mt-1">{{ readyCount }}</div>
                </div>
                <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20">
                    <div class="text-xs text-amber-400 font-semibold uppercase tracking-wider">
                        {{ isRTL ? 'تحذيرات / تكرار' : 'Conflicts' }}
                    </div>
                    <div class="text-xl font-black text-amber-300 mt-1">{{ conflictCount }}</div>
                </div>
                <div class="p-4 rounded-2xl bg-slate-500/10 border border-slate-500/20">
                    <div class="text-xs text-slate-400 font-semibold uppercase tracking-wider">
                        {{ isRTL ? 'مستبعدة' : 'Skipped' }}
                    </div>
                    <div class="text-xl font-black text-slate-300 mt-1">{{ skippedCount }}</div>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center gap-3 shrink-0">
                <button
                    @click="emit('back')"
                    class="px-5 py-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-xs transition-all active:scale-95 flex items-center gap-2"
                >
                    <ArrowLeft v-if="!isRTL" class="w-4 h-4" />
                    <ArrowRight v-else class="w-4 h-4" />
                    {{ isRTL ? 'العودة للإعدادات' : 'Back to Settings' }}
                </button>
                <button
                    @click="emit('execute')"
                    :disabled="readyCount === 0"
                    class="px-6 py-3 rounded-2xl bg-gradient-to-r from-emerald-500 to-emerald-400 hover:from-emerald-400 hover:to-emerald-300 disabled:opacity-40 text-slate-950 font-black text-xs shadow-xl shadow-emerald-500/20 transition-all active:scale-95 flex items-center gap-2"
                >
                    <Play class="w-4 h-4 fill-current" />
                    {{ isRTL ? 'اعتماد وتنفيذ النقل الفعلي' : 'Execute Plan on Disk' }}
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            <!-- Filter Pills -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
                <button
                    @click="statusFilter = 'all'"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border whitespace-nowrap"
                    :class="statusFilter === 'all' ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm' : 'bg-white/5 text-slate-400 border-white/5 hover:bg-white/10'"
                >
                    {{ isRTL ? 'الكل' : 'All' }} ({{ totalCount }})
                </button>
                <button
                    @click="statusFilter = 'ready'"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border whitespace-nowrap"
                    :class="statusFilter === 'ready' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 shadow-sm' : 'bg-white/5 text-slate-400 border-white/5 hover:bg-white/10'"
                >
                    {{ isRTL ? 'جاهزة' : 'Ready' }} ({{ readyCount }})
                </button>
                <button
                    @click="statusFilter = 'conflict'"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border whitespace-nowrap"
                    :class="statusFilter === 'conflict' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 shadow-sm' : 'bg-white/5 text-slate-400 border-white/5 hover:bg-white/10'"
                >
                    {{ isRTL ? 'تعارضات' : 'Conflicts' }} ({{ conflictCount }})
                </button>
                <button
                    @click="statusFilter = 'warning'"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border whitespace-nowrap"
                    :class="statusFilter === 'warning' ? 'bg-orange-500/20 text-orange-300 border-orange-500/40 shadow-sm' : 'bg-white/5 text-slate-400 border-white/5 hover:bg-white/10'"
                >
                    {{ isRTL ? 'تحذيرات' : 'Warnings' }} ({{ warningCount }})
                </button>
                <button
                    @click="statusFilter = 'skipped'"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border whitespace-nowrap"
                    :class="statusFilter === 'skipped' ? 'bg-slate-500/20 text-slate-200 border-slate-500/40 shadow-sm' : 'bg-white/5 text-slate-400 border-white/5 hover:bg-white/10'"
                >
                    {{ isRTL ? 'مستبعدة' : 'Skipped' }} ({{ skippedCount }})
                </button>
            </div>

            <!-- Search Bar & Batch Edit Trigger -->
            <div class="flex items-center gap-2">
                <button
                    v-if="selectedPaths.size > 0"
                    @click="handleBatchEdit"
                    class="px-3 py-2 rounded-xl bg-purple-500/20 text-purple-300 border border-purple-500/40 font-bold text-xs hover:bg-purple-500/30 transition-all flex items-center gap-1.5"
                >
                    <Edit3 class="w-3.5 h-3.5" />
                    {{ isRTL ? `تعديل (${selectedPaths.size}) محدد` : `Batch Edit (${selectedPaths.size})` }}
                </button>

                <div class="relative w-full sm:w-64">
                    <Search class="w-4 h-4 text-slate-400 absolute top-1/2 -translate-y-1/2 start-3" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="isRTL ? 'بحث بالاسم أو المسار...' : 'Search title or path...'"
                        class="w-full ps-9 pe-4 py-2 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-cyan-400"
                    />
                </div>
            </div>
        </div>

        <!-- Dry Run Interactive Table -->
        <div class="rounded-3xl bg-slate-900/60 border border-white/10 backdrop-blur-xl overflow-hidden shadow-2xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-white/5 text-slate-400 uppercase tracking-wider font-bold border-b border-white/10">
                        <tr>
                            <th class="p-3 w-10 text-center">
                                <button @click="toggleSelectAll" class="text-slate-400 hover:text-white">
                                    <CheckSquare v-if="isAllSelected" class="w-4 h-4 text-cyan-400" />
                                    <Square v-else class="w-4 h-4" />
                                </button>
                            </th>
                            <th class="p-3">{{ isRTL ? 'الملف الحالي' : 'Original File' }}</th>
                            <th class="p-3">{{ isRTL ? 'البيانات المكتشفة' : 'Detected Info' }}</th>
                            <th class="p-3">{{ isRTL ? 'المسار والاسم المقترح' : 'Proposed Destination' }}</th>
                            <th class="p-3 w-28 text-center">{{ isRTL ? 'الحالة' : 'Status' }}</th>
                            <th class="p-3 w-24 text-center">{{ isRTL ? 'إجراءات' : 'Actions' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr
                            v-for="item in paginatedItems"
                            :key="item.source_path"
                            class="hover:bg-white/[0.02] transition-colors group"
                            :class="{ 'bg-cyan-500/[0.04]': selectedPaths.has(item.source_path) }"
                        >
                            <!-- Select checkbox -->
                            <td class="p-3 text-center">
                                <button @click="toggleSelectRow(item.source_path)" class="text-slate-500 hover:text-white">
                                    <CheckSquare v-if="selectedPaths.has(item.source_path)" class="w-4 h-4 text-cyan-400" />
                                    <Square v-else class="w-4 h-4" />
                                </button>
                            </td>

                            <!-- Source Path -->
                            <td class="p-3 max-w-xs font-mono text-[11px] text-slate-300 truncate" :title="item.source_path">
                                {{ item.source_path }}
                            </td>

                            <!-- Detected Meta -->
                            <td class="p-3 whitespace-nowrap">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span v-if="item.type === 'movie'" class="px-1.5 py-0.5 rounded bg-cyan-500/20 text-cyan-300 text-[10px] font-bold">
                                        Movie
                                    </span>
                                    <span v-else class="px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300 text-[10px] font-bold">
                                        TV
                                    </span>
                                    <span class="font-bold text-white text-xs">{{ item.clean_title }}</span>
                                    <span v-if="item.year" class="text-slate-400">({{ item.year }})</span>
                                    <span v-if="item.season !== undefined && item.episode !== undefined" class="font-mono text-cyan-400 font-bold">
                                        S{{ String(item.season).padStart(2, '0') }}E{{ String(item.episode).padStart(2, '0') }}
                                    </span>
                                </div>
                            </td>

                            <!-- Destination Path Preview -->
                            <td class="p-3 max-w-sm font-mono text-[11px] text-white truncate" :title="item.destination_path">
                                <span v-if="item.is_manually_edited" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 mr-1">
                                    Edited
                                </span>
                                {{ item.destination_path }}
                            </td>

                            <!-- Status Badge -->
                            <td class="p-3 text-center">
                                <span
                                    v-if="item.status === 'ready'"
                                    class="px-2 py-1 rounded-lg text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 inline-flex items-center gap-1"
                                >
                                    <CheckCircle2 class="w-3 h-3" />
                                    Ready
                                </span>
                                <span
                                    v-else-if="item.status === 'conflict'"
                                    class="px-2 py-1 rounded-lg text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 inline-flex items-center gap-1"
                                >
                                    <AlertTriangle class="w-3 h-3" />
                                    Conflict
                                </span>
                                <span
                                    v-else-if="item.status === 'warning'"
                                    class="px-2 py-1 rounded-lg text-[10px] font-bold bg-orange-500/20 text-orange-300 border border-orange-500/30 inline-flex items-center gap-1"
                                >
                                    <AlertTriangle class="w-3 h-3" />
                                    Warning
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-1 rounded-lg text-[10px] font-bold bg-slate-500/20 text-slate-300 border border-slate-500/30 inline-flex items-center gap-1"
                                >
                                    <XCircle class="w-3 h-3" />
                                    Skipped
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="p-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button
                                        @click="emit('edit-item', item)"
                                        class="p-1.5 rounded-lg bg-white/5 hover:bg-cyan-500/20 text-slate-400 hover:text-cyan-300 transition-colors"
                                        title="Edit this item"
                                    >
                                        <Edit3 class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        v-if="item.is_manually_edited"
                                        @click="emit('reset-item', item)"
                                        class="p-1.5 rounded-lg bg-white/5 hover:bg-amber-500/20 text-slate-400 hover:text-amber-300 transition-colors"
                                        title="Reset to original"
                                    >
                                        <RotateCcw class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="p-4 border-t border-white/10 flex items-center justify-between text-xs text-slate-400">
                <div>
                    {{ isRTL ? `عرض صفحة ${currentPage} من ${totalPages}` : `Showing page ${currentPage} of ${totalPages}` }}
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="currentPage = Math.max(1, currentPage - 1)"
                        :disabled="currentPage === 1"
                        class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 disabled:opacity-30 border border-white/10 text-white font-bold transition-all"
                    >
                        {{ isRTL ? 'السابق' : 'Previous' }}
                    </button>
                    <button
                        @click="currentPage = Math.min(totalPages, currentPage + 1)"
                        :disabled="currentPage === totalPages"
                        class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 disabled:opacity-30 border border-white/10 text-white font-bold transition-all"
                    >
                        {{ isRTL ? 'التالي' : 'Next' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
