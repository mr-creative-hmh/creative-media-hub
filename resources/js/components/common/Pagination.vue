<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { ChevronLeft, ChevronRight } from '@lucide/vue';

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
        class="border-border/70 flex flex-col items-center justify-between gap-4 border-t pt-6 pb-2 sm:flex-row"
    >
        <!-- Info Counter -->
        <div
            v-if="total && from && to"
            class="text-muted-foreground text-xs font-medium tabular-nums"
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
                class="cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground focus-visible:ring-primary/30 flex min-h-10 cursor-pointer items-center gap-1 rounded-xl border px-3 py-2 text-xs font-bold shadow-sm transition-all focus-visible:ring-2 focus-visible:outline-none active:scale-95"
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
                class="border-border/40 bg-muted/20 text-muted-foreground/40 flex min-h-10 cursor-not-allowed items-center gap-1 rounded-xl border px-3 py-2 text-xs font-bold opacity-50 select-none"
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
                    class="focus-visible:ring-primary/30 flex size-9 min-w-9 cursor-pointer items-center justify-center rounded-xl text-xs tabular-nums transition-all focus-visible:ring-2 focus-visible:outline-none active:scale-95"
                    :class="
                        link.active
                            ? 'bg-primary text-primary-foreground shadow-primary/25 font-bold shadow-sm'
                            : 'cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground border'
                    "
                >
                    {{ link.label }}
                </Link>
                <span
                    v-else
                    class="text-muted-foreground/40 flex size-9 min-w-8 items-center justify-center text-xs font-bold tabular-nums select-none"
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
                class="cf-glass border-border/80 text-muted-foreground hover:border-primary/40 hover:text-foreground focus-visible:ring-primary/30 flex min-h-10 cursor-pointer items-center gap-1 rounded-xl border px-3 py-2 text-xs font-bold shadow-sm transition-all focus-visible:ring-2 focus-visible:outline-none active:scale-95"
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
                class="border-border/40 bg-muted/20 text-muted-foreground/40 flex min-h-10 cursor-not-allowed items-center gap-1 rounded-xl border px-3 py-2 text-xs font-bold opacity-50 select-none"
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
