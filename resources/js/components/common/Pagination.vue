<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-vue-next';

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
const nextLink = computed(() => props.links?.[props.links.length - 1]?.url || null);
</script>

<template>
    <div
        v-if="links && links.length > 3"
        class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-6 pb-2 border-t border-slate-200 dark:border-white/10"
    >
        <!-- Info Counter -->
        <div v-if="total && from && to" class="text-xs text-slate-500 dark:text-slate-400 font-medium">
            <span>{{ isRTL ? `عرض ${from} إلى ${to} من أصل ${total} عنصر` : `Showing ${from} to ${to} of ${total} titles` }}</span>
        </div>
        <div v-else></div>

        <!-- Page Buttons -->
        <div class="flex items-center gap-1.5 flex-wrap justify-center">
            <!-- Previous Button -->
            <Link
                v-if="prevLink"
                :href="prevLink"
                preserve-scroll
                preserve-state
                class="flex items-center gap-1 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 border border-slate-200 dark:border-white/10 transition-all cursor-pointer shadow-sm"
                :title="t('common.previous')"
            >
                <component :is="isRTL ? ChevronRight : ChevronLeft" class="w-4 h-4" />
                <span class="hidden sm:inline">{{ t('common.previous') }}</span>
            </Link>
            <span
                v-else
                class="flex items-center gap-1 px-3 py-2 rounded-xl text-xs font-bold text-slate-400 dark:text-slate-600 bg-slate-50 dark:bg-white/[0.02] border border-slate-200/50 dark:border-white/5 cursor-not-allowed opacity-50 select-none"
            >
                <component :is="isRTL ? ChevronRight : ChevronLeft" class="w-4 h-4" />
                <span class="hidden sm:inline">{{ t('common.previous') }}</span>
            </span>

            <!-- Page Number Chips -->
            <template v-for="(link, idx) in cleanedLinks" :key="idx">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    class="min-w-9 h-9 px-2.5 rounded-xl text-xs font-extrabold flex items-center justify-center transition-all cursor-pointer"
                    :class="link.active
                        ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/30 font-black'
                        : 'text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 border border-slate-200 dark:border-white/10'"
                >
                    {{ link.label }}
                </Link>
                <span
                    v-else
                    class="min-w-8 h-9 flex items-center justify-center text-xs font-bold text-slate-400 dark:text-slate-600 select-none"
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
                class="flex items-center gap-1 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 border border-slate-200 dark:border-white/10 transition-all cursor-pointer shadow-sm"
                :title="t('common.next')"
            >
                <span class="hidden sm:inline">{{ t('common.next') }}</span>
                <component :is="isRTL ? ChevronLeft : ChevronRight" class="w-4 h-4" />
            </Link>
            <span
                v-else
                class="flex items-center gap-1 px-3 py-2 rounded-xl text-xs font-bold text-slate-400 dark:text-slate-600 bg-slate-50 dark:bg-white/[0.02] border border-slate-200/50 dark:border-white/5 cursor-not-allowed opacity-50 select-none"
            >
                <span class="hidden sm:inline">{{ t('common.next') }}</span>
                <component :is="isRTL ? ChevronLeft : ChevronRight" class="w-4 h-4" />
            </span>
        </div>
    </div>
</template>
