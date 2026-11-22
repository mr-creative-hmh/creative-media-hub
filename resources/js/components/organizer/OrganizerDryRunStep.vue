<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import ClientPagination from '@/components/common/ClientPagination.vue';
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

watch([statusFilter, searchQuery, rowsPerPage], () => {
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
            class="cf-glass border-border/80 flex flex-col justify-between gap-4 rounded-3xl border p-6 backdrop-blur-xl md:flex-row md:items-center"
        >
            <div class="grid flex-1 grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="cf-glass border-border/70 rounded-2xl border p-4">
                    <div
                        class="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                    >
                        {{ isRTL ? 'إجمالي الملفات' : 'Total Items' }}
                    </div>
                    <div
                        class="text-foreground mt-1 text-xl font-black tabular-nums"
                    >
                        {{ totalCount }}
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-emerald-500/25 bg-emerald-500/10 p-4"
                >
                    <div
                        class="text-xs font-semibold tracking-wider text-emerald-400 uppercase"
                    >
                        {{ isRTL ? 'جاهزة للنقل' : 'Ready' }}
                    </div>
                    <div
                        class="mt-1 text-xl font-black text-emerald-300 tabular-nums"
                    >
                        {{ readyCount }}
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-amber-500/25 bg-amber-500/10 p-4"
                >
                    <div
                        class="text-xs font-semibold tracking-wider text-amber-400 uppercase"
                    >
                        {{ isRTL ? 'تحذيرات / تكرار' : 'Conflicts' }}
                    </div>
                    <div
                        class="mt-1 text-xl font-black text-amber-300 tabular-nums"
                    >
                        {{ conflictCount }}
                    </div>
                </div>
                <div class="cf-glass border-border/70 rounded-2xl border p-4">
                    <div
                        class="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                    >
                        {{ isRTL ? 'مستبعدة' : 'Skipped' }}
                    </div>
                    <div
                        class="text-muted-foreground mt-1 text-xl font-black tabular-nums"
                    >
                        {{ skippedCount }}
                    </div>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex shrink-0 items-center gap-3">
                <button
                    type="button"
                    @click="emit('back')"
                    class="cf-glass border-border/80 text-foreground hover:border-primary/40 flex min-h-11 cursor-pointer items-center gap-2 rounded-2xl border px-5 py-3 text-xs font-bold transition-all active:scale-95"
                >
                    <ArrowLeft v-if="!isRTL" class="h-4 w-4" />
                    <ArrowRight v-else class="h-4 w-4" />
                    {{ isRTL ? 'العودة للإعدادات' : 'Back to Settings' }}
                </button>
                <button
                    type="button"
                    @click="emit('execute')"
                    :disabled="readyCount === 0"
                    class="bg-primary text-primary-foreground shadow-primary/25 flex min-h-11 cursor-pointer items-center gap-2 rounded-2xl px-6 py-3 text-xs font-black shadow-xl transition-all hover:brightness-105 active:scale-95 disabled:opacity-40"
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
                    type="button"
                    @click="statusFilter = 'all'"
                    class="min-h-9 cursor-pointer rounded-full border px-3.5 py-1.5 text-xs font-bold whitespace-nowrap tabular-nums transition-all active:scale-95"
                    :class="
                        statusFilter === 'all'
                            ? 'border-primary bg-primary text-primary-foreground shadow-xs'
                            : 'cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground'
                    "
                >
                    {{ isRTL ? 'الكل' : 'All' }} ({{ totalCount }})
                </button>
                <button
                    type="button"
                    @click="statusFilter = 'ready'"
                    class="min-h-9 cursor-pointer rounded-full border px-3.5 py-1.5 text-xs font-bold whitespace-nowrap tabular-nums transition-all active:scale-95"
                    :class="
                        statusFilter === 'ready'
                            ? 'border-emerald-500 bg-emerald-500 text-slate-950 shadow-xs'
                            : 'cf-glass border-border/80 text-muted-foreground hover:text-foreground hover:border-emerald-500/40'
                    "
                >
                    {{ isRTL ? 'جاهزة' : 'Ready' }} ({{ readyCount }})
                </button>
                <button
                    type="button"
                    @click="statusFilter = 'conflict'"
                    class="min-h-9 cursor-pointer rounded-full border px-3.5 py-1.5 text-xs font-bold whitespace-nowrap tabular-nums transition-all active:scale-95"
                    :class="
                        statusFilter === 'conflict'
                            ? 'border-amber-500 bg-amber-500 text-slate-950 shadow-xs'
                            : 'cf-glass border-border/80 text-muted-foreground hover:text-foreground hover:border-amber-500/40'
                    "
                >
                    {{ isRTL ? 'تعارضات' : 'Conflicts' }} ({{ conflictCount }})
                </button>
                <button
                    type="button"
                    @click="statusFilter = 'warning'"
                    class="min-h-9 cursor-pointer rounded-full border px-3.5 py-1.5 text-xs font-bold whitespace-nowrap tabular-nums transition-all active:scale-95"
                    :class="
                        statusFilter === 'warning'
                            ? 'border-orange-500 bg-orange-500 text-slate-950 shadow-xs'
                            : 'cf-glass border-border/80 text-muted-foreground hover:text-foreground hover:border-orange-500/40'
                    "
                >
                    {{ isRTL ? 'تحذيرات' : 'Warnings' }} ({{ warningCount }})
                </button>
                <button
                    type="button"
                    @click="statusFilter = 'skipped'"
                    class="min-h-9 cursor-pointer rounded-full border px-3.5 py-1.5 text-xs font-bold whitespace-nowrap tabular-nums transition-all active:scale-95"
                    :class="
                        statusFilter === 'skipped'
                            ? 'border-primary bg-primary text-primary-foreground shadow-xs'
                            : 'cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground'
                    "
                >
                    {{ isRTL ? 'مستبعدة' : 'Skipped' }} ({{ skippedCount }})
                </button>
            </div>

            <!-- Search Bar & Batch Edit Trigger -->
            <div class="flex items-center gap-2">
                <button
                    v-if="selectedPaths.size > 0"
                    type="button"
                    @click="handleBatchEdit"
                    class="border-primary/40 bg-primary/15 text-primary hover:bg-primary/25 flex min-h-10 cursor-pointer items-center gap-1.5 rounded-xl border px-3.5 py-2 text-xs font-bold tabular-nums transition-all active:scale-95"
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
                        class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2"
                    />
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="
                            isRTL
                                ? 'بحث بالاسم أو المسار...'
                                : 'Search title or path...'
                        "
                        class="cf-field border-border/80 bg-card/80 text-foreground placeholder:text-muted-foreground focus:border-primary h-10 w-full rounded-xl border ps-9 pe-4 text-xs focus:outline-none"
                    />
                </div>
            </div>
        </div>

        <!-- Dry Run Interactive Table -->
        <div
            class="cf-table-wrapper cf-glass border-border/80 overflow-hidden rounded-3xl border shadow-2xl backdrop-blur-xl"
        >
            <div class="overflow-x-auto">
                <table
                    class="cf-table w-full border-collapse text-start text-xs"
                >
                    <thead
                        class="border-border/70 bg-muted/30 text-muted-foreground border-b font-bold tracking-wider uppercase"
                    >
                        <tr>
                            <th class="w-10 p-3 text-center">
                                <button
                                    type="button"
                                    @click="toggleSelectAll"
                                    class="text-muted-foreground hover:text-foreground cursor-pointer"
                                >
                                    <CheckSquare
                                        v-if="isAllSelected"
                                        class="text-primary h-4 w-4"
                                    />
                                    <Square v-else class="h-4 w-4" />
                                </button>
                            </th>
                            <th class="p-3 text-start">
                                {{ isRTL ? 'الملف الحالي' : 'Original File' }}
                            </th>
                            <th class="p-3 text-start">
                                {{
                                    isRTL
                                        ? 'البيانات المكتشفة'
                                        : 'Detected Info'
                                }}
                            </th>
                            <th class="p-3 text-start">
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
                    <tbody class="divide-border/50 divide-y">
                        <tr
                            v-for="item in paginatedItems"
                            :key="item.source_path"
                            class="group transition-colors hover:bg-white/[0.02]"
                            :class="{
                                'bg-primary/[0.06]': selectedPaths.has(
                                    item.source_path,
                                ),
                            }"
                        >
                            <!-- Select checkbox -->
                            <td class="p-3 text-center">
                                <button
                                    type="button"
                                    @click="toggleSelectRow(item.source_path)"
                                    class="text-muted-foreground hover:text-foreground cursor-pointer"
                                >
                                    <CheckSquare
                                        v-if="
                                            selectedPaths.has(item.source_path)
                                        "
                                        class="text-primary h-4 w-4"
                                    />
                                    <Square v-else class="h-4 w-4" />
                                </button>
                            </td>

                            <!-- Source Path -->
                            <td
                                class="text-muted-foreground max-w-xs truncate p-3 font-mono text-[11px]"
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
                                        class="border-primary/30 bg-primary/15 text-primary rounded border px-1.5 py-0.5 text-[10px] font-bold"
                                    >
                                        Movie
                                    </span>
                                    <span
                                        v-else
                                        class="rounded border border-purple-500/30 bg-purple-500/15 px-1.5 py-0.5 text-[10px] font-bold text-purple-300"
                                    >
                                        TV
                                    </span>
                                    <span
                                        class="text-foreground text-xs font-bold"
                                        >{{ item.clean_title }}</span
                                    >
                                    <span
                                        v-if="item.year"
                                        class="text-muted-foreground tabular-nums"
                                        >({{ item.year }})</span
                                    >
                                    <span
                                        v-if="
                                            item.season !== undefined &&
                                            item.episode !== undefined
                                        "
                                        class="text-primary font-mono font-bold tabular-nums"
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
                                class="text-foreground max-w-sm truncate p-3 font-mono text-[11px]"
                                :title="item.destination_path"
                            >
                                <span
                                    v-if="item.is_manually_edited"
                                    class="me-1 rounded border border-amber-500/30 bg-amber-500/20 px-1.5 py-0.5 text-[9px] font-bold text-amber-300"
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
                                    class="border-border bg-muted/50 text-muted-foreground inline-flex items-center gap-1 rounded-lg border px-2 py-1 text-[10px] font-bold"
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
                                        type="button"
                                        @click="emit('edit-item', item)"
                                        class="bg-muted/50 text-muted-foreground hover:bg-primary/20 hover:text-primary cursor-pointer rounded-lg p-1.5 transition-colors"
                                        title="Edit this item"
                                    >
                                        <Edit3 class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        v-if="item.is_manually_edited"
                                        type="button"
                                        @click="emit('reset-item', item)"
                                        class="bg-muted/50 text-muted-foreground cursor-pointer rounded-lg p-1.5 transition-colors hover:bg-amber-500/20 hover:text-amber-300"
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
            <div class="border-border/70 border-t p-4">
                <ClientPagination
                    :current-page="currentPage"
                    :total-items="filteredItems.length"
                    :per-page="rowsPerPage"
                    :per-page-options="[15, 30, 50]"
                    @update:current-page="currentPage = $event"
                    @update:per-page="rowsPerPage = $event"
                />
            </div>
        </div>
    </div>
</template>
