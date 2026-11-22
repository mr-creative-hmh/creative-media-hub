<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<{
        motionKey?: string | number;
        appear?: boolean;
        mode?: 'out-in' | 'in-out' | 'default';
        class?: HTMLAttributes['class'];
    }>(),
    {
        motionKey: undefined,
        appear: true,
        mode: 'out-in',
        class: undefined,
    },
);
</script>

<template>
    <Transition name="cf-page" :mode="mode" :appear="appear">
        <div :key="motionKey" :class="cn('w-full', props.class)">
            <slot />
        </div>
    </Transition>
</template>
