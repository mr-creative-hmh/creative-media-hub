<script setup lang="ts">
import type { ViewMode } from '@/components/common/ViewModeToggle.vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        viewMode?: ViewMode;
        title?: string;
        subtitle?: string;
        clickable?: boolean;
        class?: string;
    }>(),
    {
        viewMode: 'table',
        title: undefined,
        subtitle: undefined,
        clickable: false,
        class: undefined,
    },
);

const emit = defineEmits<{
    (e: 'click'): void;
}>();
</script>

<template>
    <!-- Dual-View Container Mode (when #table and #cards slots are provided) -->
    <div v-if="$slots.table || $slots.cards" :class="cn('w-full', props.class)">
        <div v-if="viewMode === 'table'" class="hidden md:block">
            <slot name="table" />
        </div>

        <div
            :class="
                viewMode === 'cards'
                    ? 'grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3'
                    : 'space-y-4 md:hidden'
            "
        >
            <slot name="cards" />
        </div>
    </div>

    <!-- Single Card Mode (default slot for individual mobile/grid card) -->
    <article
        v-else
        :class="
            cn(
                'border-border/80 bg-card flex h-full flex-col justify-between gap-3.5 rounded-2xl border p-5 shadow-xs transition-all',
                clickable
                    ? 'hover:border-primary/40 cursor-pointer hover:shadow-sm active:scale-[0.99]'
                    : '',
                props.class,
            )
        "
        @click="clickable ? emit('click') : undefined"
    >
        <div
            v-if="title || subtitle || $slots.header || $slots.badge"
            class="mb-1 flex items-start justify-between gap-2"
        >
            <div class="min-w-0 flex-1">
                <slot name="header">
                    <h3
                        v-if="title"
                        class="text-foreground truncate text-sm font-semibold"
                    >
                        {{ title }}
                    </h3>
                    <p
                        v-if="subtitle"
                        class="text-muted-foreground truncate text-xs"
                    >
                        {{ subtitle }}
                    </p>
                </slot>
            </div>
            <div v-if="$slots.badge" class="shrink-0">
                <slot name="badge" />
            </div>
        </div>

        <div class="space-y-2.5 text-sm">
            <slot />
        </div>

        <div
            v-if="$slots.footer"
            class="border-border/60 mt-1 flex flex-wrap items-center justify-between gap-2 border-t pt-3.5"
        >
            <slot name="footer" />
        </div>
    </article>
</template>
