<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from '@lucide/vue';

const props = defineProps<{
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    from?: number;
    to?: number;
    total?: number;
    currentPage?: number;
    lastPage?: number;
}>();

const { t, isRTL } = useI18n();

const cleanedLinks = computed(() => {
    if (!props.links || props.links.length <= 3) return [];
    // Filter out Laravel's default 'pagination.previous' and 'pagination.next' text labels if present
    return props.links.slice(1, -1);
});

const prevLink = computed(() => props.links?.[0]?.url || null);
const nextLink = computed(
    () => props.links?.[props.links.length - 1]?.url || null,
);
</script>

<template>
    <div
        v-if="links && links.length > 3"
        class="flex flex-col items-center justify-between gap-4 border-t border-slate-200 pt-6 pb-2 sm:flex-row dark:border-white/10"
    >
        <!-- Info Counter -->
        <div
            v-if="total && from && to"
            class="text-xs font-medium text-slate-500 dark:text-slate-400"
        >
            <span>{{
                isRTL
                    ? `عرض ${from} إلى ${to} من أصل ${total} عنصر`
                    : `Showing ${from} to ${to} of ${total} titles`
            }}</span>
        </div>
        <div v-else></div>

        <!-- Page Buttons -->
        <div class="flex flex-wrap items-center justify-center gap-1.5">
            <!-- Previous Button -->
            <Link
                v-if="prevLink"
                :href="prevLink"
                preserve-scroll
                preserve-state
                class="flex cursor-pointer items-center gap-1 rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 shadow-sm transition-all hover:bg-slate-200 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10"
                :title="t('common.previous')"
            >
                <component
                    :is="isRTL ? ChevronRight : ChevronLeft"
                    class="h-4 w-4"
                />
                <span class="hidden sm:inline">{{ t('common.previous') }}</span>
            </Link>
            <span
                v-else
                class="flex cursor-not-allowed items-center gap-1 rounded-xl border border-slate-200/50 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-400 opacity-50 select-none dark:border-white/5 dark:bg-white/[0.02] dark:text-slate-600"
            >
                <component
                    :is="isRTL ? ChevronRight : ChevronLeft"
                    class="h-4 w-4"
                />
                <span class="hidden sm:inline">{{ t('common.previous') }}</span>
            </span>

            <!-- Page Number Chips -->
            <template v-for="(link, idx) in cleanedLinks" :key="idx">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    class="flex h-9 min-w-9 cursor-pointer items-center justify-center rounded-xl px-2.5 text-xs font-extrabold transition-all"
                    :class="
                        link.active
                            ? 'bg-cyan-500 font-black text-slate-950 shadow-md shadow-cyan-500/30'
                            : 'border border-slate-200 bg-slate-100 text-slate-700 hover:bg-slate-200 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10'
                    "
                >
                    {{ link.label }}
                </Link>
                <span
                    v-else
                    class="flex h-9 min-w-8 items-center justify-center text-xs font-bold text-slate-400 select-none dark:text-slate-600"
                >
                    {{ link.label }}
                </span>
            </template>

            <!-- Next Button -->
            <Link
                v-if="nextLink"
                :href="nextLink"
                preserve-scroll
                preserve-state
                class="flex cursor-pointer items-center gap-1 rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 shadow-sm transition-all hover:bg-slate-200 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10"
                :title="t('common.next')"
            >
                <span class="hidden sm:inline">{{ t('common.next') }}</span>
                <component
                    :is="isRTL ? ChevronLeft : ChevronRight"
                    class="h-4 w-4"
                />
            </Link>
            <span
                v-else
                class="flex cursor-not-allowed items-center gap-1 rounded-xl border border-slate-200/50 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-400 opacity-50 select-none dark:border-white/5 dark:bg-white/[0.02] dark:text-slate-600"
            >
                <span class="hidden sm:inline">{{ t('common.next') }}</span>
                <component
                    :is="isRTL ? ChevronLeft : ChevronRight"
                    class="h-4 w-4"
                />
            </span>
        </div>
    </div>
</template>
