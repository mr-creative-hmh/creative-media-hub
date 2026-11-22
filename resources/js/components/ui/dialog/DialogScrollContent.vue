<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from 'reka-ui';
import { X } from '@lucide/vue';
import { reactiveOmit } from '@vueuse/core';
import {
    DialogClose,
    DialogContent,
    DialogOverlay,
    DialogPortal,
    useForwardPropsEmits,
} from 'reka-ui';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<
        DialogContentProps & {
            class?: HTMLAttributes['class'];
            showCloseButton?: boolean;
            size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl';
        }
    >(),
    {
        showCloseButton: true,
        size: 'md',
    },
);
const emits = defineEmits<DialogContentEmits>();

const delegatedProps = reactiveOmit(
    props,
    'class',
    'size',
    'showCloseButton',
);
const forwarded = useForwardPropsEmits(delegatedProps, emits);
const { direction, isRtl, locale } = useLocale();

const sizeClass = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'sm:max-w-md';
        case 'lg':
            return 'sm:max-w-2xl';
        case 'xl':
            return 'sm:max-w-3xl';
        case '2xl':
            return 'sm:max-w-4xl';
        case '3xl':
            return 'sm:max-w-5xl';
        case '4xl':
            return 'sm:max-w-6xl';
        case 'md':
        default:
            return 'sm:max-w-lg';
    }
});

const hasCustomMaxWidth = computed(() =>
    /\b(?:sm:|md:|lg:|xl:|2xl:)?max-w-/.test(String(props.class || '')),
);
const hasCustomMaxHeight = computed(() =>
    /\b(?:sm:|md:|lg:|xl:|2xl:)?max-h-/.test(String(props.class || '')),
);
const hasCustomOverflow = computed(() =>
    /\boverflow-/.test(String(props.class || '')),
);
const hasCustomPadding = computed(() =>
    /\bp-(?:0|[0-9]+)\b/.test(String(props.class || '')),
);

function isFloatingPortalEvent(event: {
    detail?: { originalEvent?: Event | null };
    target?: EventTarget | null;
}): boolean {
    const target = event.detail?.originalEvent?.target ?? event.target;
    return (
        target instanceof Element &&
        target.closest('[data-floating-portal]') !== null
    );
}

function handlePointerDownOutside(event: {
    detail: { originalEvent: PointerEvent };
    preventDefault: () => void;
}): void {
    if (isFloatingPortalEvent(event)) {
        event.preventDefault();
        return;
    }
    const originalEvent = event.detail.originalEvent;
    const target = originalEvent.target;
    if (
        target instanceof HTMLElement &&
        (originalEvent.offsetX > target.clientWidth ||
            originalEvent.offsetY > target.clientHeight)
    ) {
        event.preventDefault();
    }
}

function handleFocusOutside(event: {
    detail?: { originalEvent?: Event | null };
    target?: EventTarget | null;
    preventDefault: () => void;
}): void {
    if (isFloatingPortalEvent(event)) {
        event.preventDefault();
    }
}
</script>

<template>
    <DialogPortal>
        <DialogOverlay
            class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/80 backdrop-blur-xs"
        >
            <DialogContent
                :dir="direction"
                :class="
                    cn(
                        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-96 data-[state=open]:zoom-in-96 relative z-50 my-8 grid w-[calc(100vw-1.5rem)] max-w-[calc(100vw-1.5rem)] gap-5 rounded-3xl border border-border/80 bg-card/95 shadow-2xl backdrop-blur-2xl duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] sm:w-full',
                        !hasCustomMaxHeight && 'max-h-[90dvh]',
                        !hasCustomOverflow && 'overflow-y-auto',
                        !hasCustomPadding && 'p-4 sm:p-6',
                        !hasCustomMaxWidth && sizeClass,
                        isRtl ? 'font-cairo' : 'font-karla',
                        props.class,
                    )
                "
                v-bind="{ ...$attrs, ...forwarded }"
                @pointer-down-outside="handlePointerDownOutside"
                @focus-outside="handleFocusOutside"
            >
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary/35 to-transparent"
                />
                <slot />

                <DialogClose
                    v-if="showCloseButton"
                    :aria-label="locale === 'ar' ? 'إغلاق' : 'Close'"
                    class="absolute top-4 end-4 z-20 flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-xl border border-border/60 bg-background/85 text-muted-foreground backdrop-blur-sm transition-colors outline-none hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-primary/30 disabled:pointer-events-none"
                >
                    <X class="size-4" />
                    <span class="sr-only">{{
                        locale === 'ar' ? 'إغلاق' : 'Close'
                    }}</span>
                </DialogClose>
            </DialogContent>
        </DialogOverlay>
    </DialogPortal>
</template>
