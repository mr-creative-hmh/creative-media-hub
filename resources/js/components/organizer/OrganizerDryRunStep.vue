<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Search,
    CheckSquare,
    Square,
    Edit3,
    RotateCcw,
    CheckCircle2,
    AlertTriangle,
    XCircle,
    ArrowRight,
    ArrowLeft,
    Play,
} from '@lucide/vue';
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
const statusFilter = ref<'all' | 'ready' | 'conflict' | 'warning' | 'skipped'>(
    'all',
);
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
        items = items.filter((item) => {
            if (statusFilter.value === 'ready') return item.status === 'ready';
            if (statusFilter.value === 'conflict')
                return item.status === 'conflict';
            if (statusFilter.value === 'warning')
                return item.status === 'warning';
            if (statusFilter.value === 'skipped')
                return item.status === 'skipped';
            return true;
        });
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        items = items.filter((item) => {
            return (
                (item.clean_title &&
                    item.clean_title.toLowerCase().includes(q)) ||
                (item.source_path &&
                    item.source_path.toLowerCase().includes(q)) ||
                (item.destination_path &&
                    item.destination_path.toLowerCase().includes(q))
            );
        });
    }

    return items;
});

// Counts
const totalCount = computed(() => props.planItems.length);
const readyCount = computed(
    () => props.planItems.filter((i) => i.status === 'ready').length,
);
const conflictCount = computed(
    () => props.planItems.filter((i) => i.status === 'conflict').length,
);
const warningCount = computed(
    () => props.planItems.filter((i) => i.status === 'warning').length,
);
const skippedCount = computed(
    () => props.planItems.filter((i) => i.status === 'skipped').length,
);

// Pagination
const totalPages = computed(
    () => Math.ceil(filteredItems.value.length / rowsPerPage.value) || 1,
);
const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;
    return filteredItems.value.slice(start, start + rowsPerPage.value);
});

// Selection helpers keyed by source_path so selections hold across pagination & filters
const isAllSelected = computed(() => {
    return (
        filteredItems.value.length > 0 &&
        filteredItems.value.every((i) => selectedPaths.value.has(i.source_path))
    );
});

const toggleSelectAll = () => {
    const next = new Set(selectedPaths.value);
    if (isAllSelected.value) {
        filteredItems.value.forEach((i) => next.delete(i.source_path));
    } else {
        filteredItems.value.forEach((i) => next.add(i.source_path));
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
    const selected = filteredItems.value.filter((i) =>
        selectedPaths.value.has(i.source_path),
    );
    if (selected.length > 0) {
        emit('batch-edit', selected);
    }
};
</script>

<template>
    <div class="space-y-6">
        <!-- Summary Stats Card -->
        <div
            class="flex flex-col justify-between gap-4 rounded-3xl border border-white/10 bg-slate-900/60 p-6 backdrop-blur-xl md:flex-row md:items-center"
        >
            <div class="grid flex-1 grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-2xl border border-white/5 bg-white/5 p-4">
                    <div
                        class="text-xs font-semibold tracking-wider text-slate-400 uppercase"
                    >
                        {{ isRTL ? 'إجمالي الملفات' : 'Total Items' }}
                    </div>
                    <div class="mt-1 text-xl font-black text-white">
                        {{ totalCount }}
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4"
                >
                    <div
                        class="text-xs font-semibold tracking-wider text-emerald-400 uppercase"
                    >
                        {{ isRTL ? 'جاهزة للنقل' : 'Ready' }}
                    </div>
                    <div class="mt-1 text-xl font-black text-emerald-300">
                        {{ readyCount }}
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-amber-500/20 bg-amber-500/10 p-4"
                >
                    <div
                        class="text-xs font-semibold tracking-wider text-amber-400 uppercase"
                    >
                        {{ isRTL ? 'تحذيرات / تكرار' : 'Conflicts' }}
                    </div>
                    <div class="mt-1 text-xl font-black text-amber-300">
                        {{ conflictCount }}
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-slate-500/20 bg-slate-500/10 p-4"
                >
                    <div
                        class="text-xs font-semibold tracking-wider text-slate-400 uppercase"
                    >
                        {{ isRTL ? 'مستبعدة' : 'Skipped' }}
                    </div>
                    <div class="mt-1 text-xl font-black text-slate-300">
                        {{ skippedCount }}
                    </div>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex shrink-0 items-center gap-3">
                <button
                    @click="emit('back')"
                    class="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-5 py-3 text-xs font-bold text-white transition-all hover:bg-white/10 active:scale-95"
                >
                    <ArrowLeft v-if="!isRTL" class="h-4 w-4" />
                    <ArrowRight v-else class="h-4 w-4" />
                    {{ isRTL ? 'العودة للإعدادات' : 'Back to Settings' }}
                </button>
                <button
                    @click="emit('execute')"
                    :disabled="readyCount === 0"
                    class="flex items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-emerald-400 px-6 py-3 text-xs font-black text-slate-950 shadow-xl shadow-emerald-500/20 transition-all hover:from-emerald-400 hover:to-emerald-300 active:scale-95 disabled:opacity-40"
                >
                    <Play class="h-4 w-4 fill-current" />
                    {{
                        isRTL
                            ? 'اعتماد وتنفيذ النقل الفعلي'
                            : 'Execute Plan on Disk'
                    }}
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div
            class="flex flex-col justify-between gap-3 md:flex-row md:items-center"
        >
            <!-- Filter Pills -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
                <button
                    @click="statusFilter = 'all'"
                    class="rounded-xl border px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-all"
                    :class="
                        statusFilter === 'all'
                            ? 'border-cyan-500/40 bg-cyan-500/20 text-cyan-300 shadow-sm'
                            : 'border-white/5 bg-white/5 text-slate-400 hover:bg-white/10'
                    "
                >
                    {{ isRTL ? 'الكل' : 'All' }} ({{ totalCount }})
                </button>
                <button
                    @click="statusFilter = 'ready'"
                    class="rounded-xl border px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-all"
                    :class="
                        statusFilter === 'ready'
                            ? 'border-emerald-500/40 bg-emerald-500/20 text-emerald-300 shadow-sm'
                            : 'border-white/5 bg-white/5 text-slate-400 hover:bg-white/10'
                    "
                >
                    {{ isRTL ? 'جاهزة' : 'Ready' }} ({{ readyCount }})
                </button>
                <button
                    @click="statusFilter = 'conflict'"
                    class="rounded-xl border px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-all"
                    :class="
                        statusFilter === 'conflict'
                            ? 'border-amber-500/40 bg-amber-500/20 text-amber-300 shadow-sm'
                            : 'border-white/5 bg-white/5 text-slate-400 hover:bg-white/10'
                    "
                >
                    {{ isRTL ? 'تعارضات' : 'Conflicts' }} ({{ conflictCount }})
                </button>
                <button
                    @click="statusFilter = 'warning'"
                    class="rounded-xl border px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-all"
                    :class="
                        statusFilter === 'warning'
                            ? 'border-orange-500/40 bg-orange-500/20 text-orange-300 shadow-sm'
                            : 'border-white/5 bg-white/5 text-slate-400 hover:bg-white/10'
                    "
                >
                    {{ isRTL ? 'تحذيرات' : 'Warnings' }} ({{ warningCount }})
                </button>
                <button
                    @click="statusFilter = 'skipped'"
                    class="rounded-xl border px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-all"
                    :class="
                        statusFilter === 'skipped'
                            ? 'border-slate-500/40 bg-slate-500/20 text-slate-200 shadow-sm'
                            : 'border-white/5 bg-white/5 text-slate-400 hover:bg-white/10'
                    "
                >
                    {{ isRTL ? 'مستبعدة' : 'Skipped' }} ({{ skippedCount }})
                </button>
            </div>

            <!-- Search Bar & Batch Edit Trigger -->
            <div class="flex items-center gap-2">
                <button
                    v-if="selectedPaths.size > 0"
                    @click="handleBatchEdit"
                    class="flex items-center gap-1.5 rounded-xl border border-purple-500/40 bg-purple-500/20 px-3 py-2 text-xs font-bold text-purple-300 transition-all hover:bg-purple-500/30"
                >
                    <Edit3 class="h-3.5 w-3.5" />
                    {{
                        isRTL
                            ? `تعديل (${selectedPaths.size}) محدد`
                            : `Batch Edit (${selectedPaths.size})`
                    }}
                </button>

                <div class="relative w-full sm:w-64">
                    <Search
                        class="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="
                            isRTL
                                ? 'بحث بالاسم أو المسار...'
                                : 'Search title or path...'
                        "
                        class="w-full rounded-xl border border-white/10 bg-white/5 py-2 ps-9 pe-4 text-xs text-white placeholder-slate-500 focus:border-cyan-400 focus:outline-none"
                    />
                </div>
            </div>
        </div>

        <!-- Dry Run Interactive Table -->
        <div
            class="overflow-hidden rounded-3xl border border-white/10 bg-slate-900/60 shadow-2xl backdrop-blur-xl"
        >
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead
                        class="border-b border-white/10 bg-white/5 font-bold tracking-wider text-slate-400 uppercase"
                    >
                        <tr>
                            <th class="w-10 p-3 text-center">
                                <button
                                    @click="toggleSelectAll"
                                    class="text-slate-400 hover:text-white"
                                >
                                    <CheckSquare
                                        v-if="isAllSelected"
                                        class="h-4 w-4 text-cyan-400"
                                    />
                                    <Square v-else class="h-4 w-4" />
                                </button>
                            </th>
                            <th class="p-3">
                                {{ isRTL ? 'الملف الحالي' : 'Original File' }}
                            </th>
                            <th class="p-3">
                                {{
                                    isRTL
                                        ? 'البيانات المكتشفة'
                                        : 'Detected Info'
                                }}
                            </th>
                            <th class="p-3">
                                {{
                                    isRTL
                                        ? 'المسار والاسم المقترح'
                                        : 'Proposed Destination'
                                }}
                            </th>
                            <th class="w-28 p-3 text-center">
                                {{ isRTL ? 'الحالة' : 'Status' }}
                            </th>
                            <th class="w-24 p-3 text-center">
                                {{ isRTL ? 'إجراءات' : 'Actions' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr
                            v-for="item in paginatedItems"
                            :key="item.source_path"
                            class="group transition-colors hover:bg-white/[0.02]"
                            :class="{
                                'bg-cyan-500/[0.04]': selectedPaths.has(
                                    item.source_path,
                                ),
                            }"
                        >
                            <!-- Select checkbox -->
                            <td class="p-3 text-center">
                                <button
                                    @click="toggleSelectRow(item.source_path)"
                                    class="text-slate-500 hover:text-white"
                                >
                                    <CheckSquare
                                        v-if="
                                            selectedPaths.has(item.source_path)
                                        "
                                        class="h-4 w-4 text-cyan-400"
                                    />
                                    <Square v-else class="h-4 w-4" />
                                </button>
                            </td>

                            <!-- Source Path -->
                            <td
                                class="max-w-xs truncate p-3 font-mono text-[11px] text-slate-300"
                                :title="item.source_path"
                            >
                                {{ item.source_path }}
                            </td>

                            <!-- Detected Meta -->
                            <td class="p-3 whitespace-nowrap">
                                <div
                                    class="flex flex-wrap items-center gap-1.5"
                                >
                                    <span
                                        v-if="item.type === 'movie'"
                                        class="rounded bg-cyan-500/20 px-1.5 py-0.5 text-[10px] font-bold text-cyan-300"
                                    >
                                        Movie
                                    </span>
                                    <span
                                        v-else
                                        class="rounded bg-purple-500/20 px-1.5 py-0.5 text-[10px] font-bold text-purple-300"
                                    >
                                        TV
                                    </span>
                                    <span
                                        class="text-xs font-bold text-white"
                                        >{{ item.clean_title }}</span
                                    >
                                    <span
                                        v-if="item.year"
                                        class="text-slate-400"
                                        >({{ item.year }})</span
                                    >
                                    <span
                                        v-if="
                                            item.season !== undefined &&
                                            item.episode !== undefined
                                        "
                                        class="font-mono font-bold text-cyan-400"
                                    >
                                        S{{
                                            String(item.season).padStart(
                                                2,
                                                '0',
                                            )
                                        }}E{{
                                            String(item.episode).padStart(
                                                2,
                                                '0',
                                            )
                                        }}
                                    </span>
                                </div>
                            </td>

                            <!-- Destination Path Preview -->
                            <td
                                class="max-w-sm truncate p-3 font-mono text-[11px] text-white"
                                :title="item.destination_path"
                            >
                                <span
                                    v-if="item.is_manually_edited"
                                    class="mr-1 rounded border border-amber-500/30 bg-amber-500/20 px-1.5 py-0.5 text-[9px] font-bold text-amber-300"
                                >
                                    Edited
                                </span>
                                {{ item.destination_path }}
                            </td>

                            <!-- Status Badge -->
                            <td class="p-3 text-center">
                                <span
                                    v-if="item.status === 'ready'"
                                    class="inline-flex items-center gap-1 rounded-lg border border-emerald-500/30 bg-emerald-500/20 px-2 py-1 text-[10px] font-bold text-emerald-300"
                                >
                                    <CheckCircle2 class="h-3 w-3" />
                                    Ready
                                </span>
                                <span
                                    v-else-if="item.status === 'conflict'"
                                    class="inline-flex items-center gap-1 rounded-lg border border-amber-500/30 bg-amber-500/20 px-2 py-1 text-[10px] font-bold text-amber-300"
                                >
                                    <AlertTriangle class="h-3 w-3" />
                                    Conflict
                                </span>
                                <span
                                    v-else-if="item.status === 'warning'"
                                    class="inline-flex items-center gap-1 rounded-lg border border-orange-500/30 bg-orange-500/20 px-2 py-1 text-[10px] font-bold text-orange-300"
                                >
                                    <AlertTriangle class="h-3 w-3" />
                                    Warning
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-500/30 bg-slate-500/20 px-2 py-1 text-[10px] font-bold text-slate-300"
                                >
                                    <XCircle class="h-3 w-3" />
                                    Skipped
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="p-3 text-center">
                                <div
                                    class="flex items-center justify-center gap-1"
                                >
                                    <button
                                        @click="emit('edit-item', item)"
                                        class="rounded-lg bg-white/5 p-1.5 text-slate-400 transition-colors hover:bg-cyan-500/20 hover:text-cyan-300"
                                        title="Edit this item"
                                    >
                                        <Edit3 class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        v-if="item.is_manually_edited"
                                        @click="emit('reset-item', item)"
                                        class="rounded-lg bg-white/5 p-1.5 text-slate-400 transition-colors hover:bg-amber-500/20 hover:text-amber-300"
                                        title="Reset to original"
                                    >
                                        <RotateCcw class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div
                class="flex items-center justify-between border-t border-white/10 p-4 text-xs text-slate-400"
            >
                <div>
                    {{
                        isRTL
                            ? `عرض صفحة ${currentPage} من ${totalPages}`
                            : `Showing page ${currentPage} of ${totalPages}`
                    }}
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="currentPage = Math.max(1, currentPage - 1)"
                        :disabled="currentPage === 1"
                        class="rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 font-bold text-white transition-all hover:bg-white/10 disabled:opacity-30"
                    >
                        {{ isRTL ? 'السابق' : 'Previous' }}
                    </button>
                    <button
                        @click="
                            currentPage = Math.min(totalPages, currentPage + 1)
                        "
                        :disabled="currentPage === totalPages"
                        class="rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 font-bold text-white transition-all hover:bg-white/10 disabled:opacity-30"
                    >
                        {{ isRTL ? 'التالي' : 'Next' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
