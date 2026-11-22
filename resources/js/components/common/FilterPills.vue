<script setup lang="ts">
import { useLocale } from '@/composables/useLocale';
import { cn } from '@/lib/utils';

export interface FilterPillOption {
    value: string;
    label: string;
    count?: number;
}

const props = withDefaults(
    defineProps<{
        modelValue: string;
        options: FilterPillOption[];
        class?: string;
    }>(),
    {
        class: undefined,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const { locale } = useLocale();

const formatNumber = (count: number): string => {
    return new Intl.NumberFormat(
        locale.value === 'ar' ? 'ar-EG' : 'en-US',
    ).format(count);
};
</script>

<template>
    <div
        :class="
            cn(
                'flex [scrollbar-width:none] items-center gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden',
                props.class,
            )
        "
        role="tablist"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="tab"
            :aria-selected="modelValue === option.value"
            :class="
                cn(
                    'inline-flex min-h-10 shrink-0 cursor-pointer items-center gap-1.5 rounded-full border px-3.5 text-xs font-semibold transition-all active:scale-95',
                    modelValue === option.value
                        ? 'border-primary bg-primary text-primary-foreground shadow-xs'
                        : 'border-border bg-card text-muted-foreground hover:border-primary/40 hover:text-foreground',
                )
            "
            @click="emit('update:modelValue', option.value)"
        >
            <span>{{ option.label }}</span>
            <span
                v-if="option.count !== undefined"
                :class="
                    cn(
                        'rounded-full px-1.5 py-0.5 text-[11px] tabular-nums',
                        modelValue === option.value
                            ? 'bg-primary-foreground/20 text-primary-foreground'
                            : 'bg-muted text-muted-foreground',
                    )
                "
            >
                {{ formatNumber(option.count) }}
            </span>
        </button>
    </div>
</template>
