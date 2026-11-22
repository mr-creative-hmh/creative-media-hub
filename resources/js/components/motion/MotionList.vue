<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<{
        tag?: string;
        name?: string;
        appear?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        tag: 'div',
        name: 'cf-list',
        appear: false,
        class: undefined,
    },
);

function handleBeforeLeave(el: Element): void {
    if (!(el instanceof HTMLElement)) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const parentRect = el.parentElement?.getBoundingClientRect();

    el.style.width = `${rect.width}px`;
    el.style.height = `${rect.height}px`;

    if (parentRect) {
        el.style.top = `${rect.top - parentRect.top}px`;
        el.style.left = `${rect.left - parentRect.left}px`;
    }
}

function clearInlineMetrics(el: Element): void {
    if (!(el instanceof HTMLElement)) {
        return;
    }

    el.style.width = '';
    el.style.height = '';
    el.style.top = '';
    el.style.left = '';
}
</script>

<template>
    <TransitionGroup
        :tag="tag"
        :name="name"
        :appear="appear"
        :class="cn('relative', props.class)"
        @before-leave="handleBeforeLeave"
        @after-leave="clearInlineMetrics"
        @leave-cancelled="clearInlineMetrics"
    >
        <slot />
    </TransitionGroup>
</template>
