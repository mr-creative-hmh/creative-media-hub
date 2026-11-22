<script lang="ts" setup>
import {
    CircleCheck,
    Info,
    Loader2,
    AlertOctagon,
    AlertTriangle,
    X,
} from '@lucide/vue';
import type { ToasterProps } from 'vue-sonner';
import { Toaster as Sonner } from 'vue-sonner';
import { useLocale } from '@/composables/useLocale';
import { cn } from '@/lib/utils';

const props = withDefaults(defineProps<ToasterProps>(), {
    position: 'top-center',
    expand: false,
    closeButton: true,
    duration: 4200,
    gap: 10,
});

const { isRtl } = useLocale();
</script>

<template>
    <Sonner
        v-bind="props"
        :dir="isRtl ? 'rtl' : 'ltr'"
        :class="cn('toaster group', isRtl ? 'font-cairo' : 'font-karla', props.class)"
        :toast-options="{
            classes: {
                toast: 'group toast ct-hud-toast',
                title: 'ct-hud-toast-title',
                description: 'ct-hud-toast-description',
                actionButton: 'ct-hud-toast-action',
                cancelButton: 'ct-hud-toast-cancel',
                closeButton: 'ct-hud-toast-close',
                success: 'ct-hud-toast-success',
                error: 'ct-hud-toast-error',
                warning: 'ct-hud-toast-warning',
                info: 'ct-hud-toast-info',
            },
        }"
        :style="{
            '--normal-bg': 'var(--card)',
            '--normal-text': 'var(--foreground)',
            '--normal-border': 'var(--border)',
            '--border-radius': '14px',
            '--width': '390px',
        }"
    >
        <template #success-icon>
            <div
                class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-emerald-500/12 text-emerald-600 shadow-2xs ring-1 ring-emerald-500/25 dark:bg-emerald-500/15 dark:text-emerald-400"
            >
                <CircleCheck class="size-4 stroke-[2.25]" />
            </div>
        </template>
        <template #info-icon>
            <div
                class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-primary/12 text-primary shadow-2xs ring-1 ring-primary/25 dark:bg-primary/15"
            >
                <Info class="size-4 stroke-[2.25]" />
            </div>
        </template>
        <template #warning-icon>
            <div
                class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-amber-500/12 text-amber-600 shadow-2xs ring-1 ring-amber-500/25 dark:bg-amber-500/15 dark:text-amber-400"
            >
                <AlertTriangle class="size-4 stroke-[2.25]" />
            </div>
        </template>
        <template #error-icon>
            <div
                class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-rose-500/12 text-rose-600 shadow-2xs ring-1 ring-rose-500/25 dark:bg-rose-500/15 dark:text-rose-400"
            >
                <AlertOctagon class="size-4 stroke-[2.25]" />
            </div>
        </template>
        <template #loading-icon>
            <div
                class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-primary/12 text-primary shadow-2xs ring-1 ring-primary/25"
            >
                <Loader2 class="size-4 animate-spin" />
            </div>
        </template>
        <template #close-icon>
            <X class="size-3.5 stroke-[2.25]" />
        </template>
    </Sonner>
</template>
