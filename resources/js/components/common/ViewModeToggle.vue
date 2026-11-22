<script setup lang="ts">
import { LayoutGrid, Table2 } from '@lucide/vue';
import { onMounted, watch } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { cn } from '@/lib/utils';

export type ViewMode = 'table' | 'cards';

const props = withDefaults(
    defineProps<{
        modelValue: ViewMode;
        storageKey?: string;
        class?: string;
    }>(),
    {
        storageKey: undefined,
        class: undefined,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: ViewMode): void;
}>();

const { locale } = useLocale();

onMounted(() => {
    if (!props.storageKey || typeof window === 'undefined') {
        return;
    }
    const saved = localStorage.getItem(props.storageKey);
    if (saved === 'table' || saved === 'cards') {
        if (saved !== props.modelValue) {
            emit('update:modelValue', saved);
        }
    }
});

watch(
    () => props.modelValue,
    (val) => {
        if (props.storageKey && typeof window !== 'undefined') {
            localStorage.setItem(props.storageKey, val);
        }
    },
);
</script>

<template>
    <div
        role="group"
        :aria-label="locale === 'ar' ? 'طريقة العرض' : 'View mode'"
        :class="
            cn(
                'border-border/80 bg-card/80 hidden items-center rounded-xl border p-1 shadow-xs backdrop-blur-md md:inline-flex dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]',
                props.class,
            )
        "
    >
        <button
            type="button"
            :aria-pressed="modelValue === 'table'"
            :class="
                cn(
                    'inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-lg px-3 text-xs font-semibold transition-all duration-150 active:scale-95',
                    modelValue === 'table'
                        ? 'bg-primary text-primary-foreground shadow-xs'
                        : 'text-muted-foreground hover:text-foreground',
                )
            "
            @click="emit('update:modelValue', 'table')"
        >
            <Table2 class="size-3.5" aria-hidden="true" />
            <span>{{ locale === 'ar' ? 'جدول' : 'Table' }}</span>
        </button>

        <button
            type="button"
            :aria-pressed="modelValue === 'cards'"
            :class="
                cn(
                    'inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-lg px-3 text-xs font-semibold transition-all duration-150 active:scale-95',
                    modelValue === 'cards'
                        ? 'bg-primary text-primary-foreground shadow-xs'
                        : 'text-muted-foreground hover:text-foreground',
                )
            "
            @click="emit('update:modelValue', 'cards')"
        >
            <LayoutGrid class="size-3.5" aria-hidden="true" />
            <span>{{ locale === 'ar' ? 'بطاقات' : 'Cards' }}</span>
        </button>
    </div>
</template>
