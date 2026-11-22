<script setup lang="ts">
import { computed } from 'vue';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { useI18n } from '@/i18n/useI18n';

const props = withDefaults(
    defineProps<{
        currentPage: number;
        totalItems: number;
        perPage: number;
        perPageOptions?: number[];
        itemLabel?: string;
        scrollToTop?: boolean;
    }>(),
    {
        scrollToTop: false,
    },
);

const emit = defineEmits<{
    (e: 'update:currentPage', page: number): void;
    (e: 'update:perPage', perPage: number): void;
}>();

const { t, isRTL } = useI18n();

const safePerPage = computed(() => Math.max(1, props.perPage));

const lastPage = computed(() =>
    Math.max(1, Math.ceil(props.totalItems / safePerPage.value)),
);

const safeCurrentPage = computed(() =>
    Math.min(Math.max(1, props.currentPage), lastPage.value),
);

const shouldShow = computed(() => {
    if (props.totalItems === 0) return false;
    if (lastPage.value > 1) return true;
    if (props.perPageOptions && props.perPageOptions.length > 0) {
        return props.totalItems > Math.min(...props.perPageOptions);
    }
    return false;
});

const fromItem = computed(() => {
    if (props.totalItems === 0) return 0;
    return (safeCurrentPage.value - 1) * safePerPage.value + 1;
});

const toItem = computed(() =>
    Math.min(safeCurrentPage.value * safePerPage.value, props.totalItems),
);

const counterText = computed(() => {
    const suffix = props.itemLabel ? ` ${props.itemLabel}` : '';
    if (isRTL.value) {
        return `عرض ${fromItem.value} إلى ${toItem.value} من أصل ${props.totalItems}${suffix}`;
    }
    return `Showing ${fromItem.value} to ${toItem.value} of ${props.totalItems}${suffix}`;
});

const visiblePages = computed<Array<number | '...'>>(() => {
    const total = lastPage.value;
    if (total <= 7) {
        return Array.from({ length: total }, (_, idx) => idx + 1);
    }

    const current = safeCurrentPage.value;
    const rawPages = new Set<number>([1, total]);
    for (let p = current - 1; p <= current + 1; p++) {
        if (p >= 1 && p <= total) {
            rawPages.add(p);
        }
    }

    const sorted = Array.from(rawPages).sort((a, b) => a - b);
    const result: Array<number | '...'> = [];

    for (let i = 0; i < sorted.length; i++) {
        const page = sorted[i];
        const prev = sorted[i - 1];
        if (prev !== undefined) {
            if (page - prev === 2) {
                result.push(prev + 1);
            } else if (page - prev > 2) {
                result.push('...');
            }
        }
        result.push(page);
    }

    return result;
});

const goToPage = (page: number) => {
    if (page < 1 || page > lastPage.value || page === safeCurrentPage.value) {
        return;
    }
    emit('update:currentPage', page);
    if (props.scrollToTop && typeof window !== 'undefined') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

const onPerPageChange = (event: Event) => {
    const target = event.target as HTMLSelectElement | null;
    if (!target) return;
    const nextPerPage = Number(target.value);
    if (!Number.isFinite(nextPerPage) || nextPerPage <= 0) return;
    emit('update:perPage', nextPerPage);
    if (props.scrollToTop && typeof window !== 'undefined') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};
</script>

<template>
    <div
        v-if="shouldShow"
        class="border-border/70 flex flex-col items-center justify-between gap-4 border-t pt-6 pb-2 sm:flex-row"
    >
        <!-- Info Counter + Optional Per-Page Selector -->
        <div class="flex flex-wrap items-center gap-3">
            <span
                class="text-muted-foreground text-xs font-medium tabular-nums"
            >
                {{ counterText }}
            </span>

            <select
                v-if="perPageOptions && perPageOptions.length > 0"
                :value="perPage"
                aria-label="Items per page"
                class="cf-field border-border/80 bg-background/80 text-foreground h-9 rounded-xl border px-2.5 text-xs font-semibold tabular-nums"
                @change="onPerPageChange"
            >
                <option
                    v-for="option in perPageOptions"
                    :key="option"
                    :value="option"
                >
                    {{ isRTL ? `${option} / صفحة` : `${option} / page` }}
                </option>
            </select>
        </div>

        <!-- Page Buttons -->
        <div class="flex flex-wrap items-center justify-center gap-1.5">
            <!-- Previous Button -->
            <button
                type="button"
                :disabled="safeCurrentPage <= 1"
                :title="t('common.previous')"
                class="flex min-h-10 items-center gap-1 rounded-xl border px-3 py-2 text-xs font-bold transition-all focus-visible:ring-2 focus-visible:outline-none"
                :class="
                    safeCurrentPage <= 1
                        ? 'border-border/40 bg-muted/20 text-muted-foreground/40 cursor-not-allowed opacity-50 select-none'
                        : 'cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground focus-visible:ring-primary/30 cursor-pointer shadow-sm active:scale-95'
                "
                @click="goToPage(safeCurrentPage - 1)"
            >
                <component
                    :is="isRTL ? ChevronRight : ChevronLeft"
                    class="h-4 w-4"
                />
                <span class="hidden sm:inline">{{ t('common.previous') }}</span>
            </button>

            <!-- Page Number Chips -->
            <template
                v-for="(item, idx) in visiblePages"
                :key="`${item}-${idx}`"
            >
                <button
                    v-if="item !== '...'"
                    type="button"
                    class="focus-visible:ring-primary/30 flex size-9 min-w-9 cursor-pointer items-center justify-center rounded-xl text-xs tabular-nums transition-all focus-visible:ring-2 focus-visible:outline-none active:scale-95"
                    :class="
                        item === safeCurrentPage
                            ? 'bg-primary text-primary-foreground shadow-primary/25 font-bold shadow-sm'
                            : 'cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground border'
                    "
                    @click="goToPage(item)"
                >
                    {{ item }}
                </button>
                <span
                    v-else
                    class="text-muted-foreground/40 flex size-9 min-w-8 items-center justify-center text-xs font-bold tabular-nums select-none"
                >
                    ...
                </span>
            </template>

            <!-- Next Button -->
            <button
                type="button"
                :disabled="safeCurrentPage >= lastPage"
                :title="t('common.next')"
                class="flex min-h-10 items-center gap-1 rounded-xl border px-3 py-2 text-xs font-bold transition-all focus-visible:ring-2 focus-visible:outline-none"
                :class="
                    safeCurrentPage >= lastPage
                        ? 'border-border/40 bg-muted/20 text-muted-foreground/40 cursor-not-allowed opacity-50 select-none'
                        : 'cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground focus-visible:ring-primary/30 cursor-pointer shadow-sm active:scale-95'
                "
                @click="goToPage(safeCurrentPage + 1)"
            >
                <span class="hidden sm:inline">{{ t('common.next') }}</span>
                <component
                    :is="isRTL ? ChevronLeft : ChevronRight"
                    class="h-4 w-4"
                />
            </button>
        </div>
    </div>
</template>
