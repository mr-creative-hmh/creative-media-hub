<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { computed } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useLocale } from '@/composables/useLocale';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        subtitle?: string;
        description?: string;
        icon?: Component | null;
        size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl';
        maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl';
        showCloseButton?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        title: undefined,
        subtitle: undefined,
        description: undefined,
        icon: null,
        size: undefined,
        maxWidth: 'lg',
        showCloseButton: true,
        class: undefined,
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'close'): void;
}>();

const resolvedSize = computed(() => props.size ?? props.maxWidth ?? 'lg');

const { locale } = useLocale();

const modalAriaLabel = computed(
    () => props.title || (locale.value === 'ar' ? 'نافذة حوار' : 'Dialog'),
);

const resolvedSubtitle = computed(() => props.subtitle || props.description);

function handleOpenChange(value: boolean) {
    emit('update:open', value);
    if (!value) {
        emit('close');
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent
            :aria-label="modalAriaLabel"
            :size="resolvedSize"
            :show-close-button="showCloseButton"
            :class="
                cn(
                    'flex max-h-[90dvh] flex-col gap-0 overflow-hidden p-0',
                    props.class,
                )
            "
        >
            <DialogHeader
                v-if="title || resolvedSubtitle || icon || $slots.header"
                class="border-border/70 shrink-0 border-b px-5 pe-12 pt-5 pb-4 sm:px-6"
            >
                <slot name="header">
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            v-if="icon"
                            class="border-primary/20 bg-primary/12 text-primary flex size-10 shrink-0 items-center justify-center rounded-2xl border shadow-xs"
                        >
                            <component :is="icon" class="size-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <DialogTitle
                                v-if="title"
                                as="h3"
                                class="text-foreground text-base leading-snug font-black tracking-tight"
                            >
                                {{ title }}
                            </DialogTitle>
                            <DialogDescription
                                v-if="resolvedSubtitle"
                                class="text-muted-foreground mt-0.5 text-xs leading-relaxed"
                            >
                                {{ resolvedSubtitle }}
                            </DialogDescription>
                        </div>
                    </div>
                </slot>
            </DialogHeader>

            <div
                class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4 sm:px-6"
            >
                <slot />
            </div>

            <DialogFooter
                v-if="$slots.footer"
                class="border-border/70 shrink-0 flex-wrap border-t px-5 py-3.5 sm:px-6"
            >
                <slot name="footer" />
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
