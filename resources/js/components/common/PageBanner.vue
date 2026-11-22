<script setup lang="ts">
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        badge?: string;
        icon?: Component;
        class?: string;
    }>(),
    {
        description: undefined,
        badge: undefined,
        icon: undefined,
        class: undefined,
    },
);
</script>

<template>
    <section
        :class="
            cn(
                'cf-glass relative overflow-hidden rounded-3xl p-5 sm:p-6',
                props.class,
            )
        "
    >
        <div
            class="bg-primary/8 pointer-events-none absolute -end-16 -top-16 size-48 rounded-full blur-2xl"
        />
        <div
            class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex min-w-0 flex-1 items-start gap-3.5">
                <div
                    v-if="icon"
                    class="bg-primary/10 text-primary ring-primary/20 flex size-11 shrink-0 items-center justify-center rounded-xl ring-1"
                >
                    <component :is="icon" class="size-5" />
                </div>
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="text-foreground text-xl font-bold tracking-tight sm:text-2xl"
                        >
                            {{ title }}
                        </h1>
                        <span
                            v-if="badge"
                            class="bg-primary/10 text-primary ring-primary/20 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                        >
                            {{ badge }}
                        </span>
                    </div>
                    <p
                        v-if="description"
                        class="text-muted-foreground max-w-2xl text-sm"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>

            <div
                v-if="$slots.actions"
                class="flex flex-wrap items-center gap-2 sm:shrink-0"
            >
                <slot name="actions" />
            </div>
        </div>
    </section>
</template>
