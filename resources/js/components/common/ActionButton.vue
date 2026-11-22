<script setup lang="ts">
import {
    AlertCircle,
    Check,
    Download,
    Save,
    Send,
    Sparkles,
    Trash2,
    Upload,
} from '@lucide/vue';
import type { Component, HTMLAttributes } from 'vue';
import { computed, onBeforeUnmount, ref, useSlots, watch } from 'vue';
import { Button, type ButtonVariants } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { ActionPreset, ActionState } from '@/composables/useActionState';
import { useLocale } from '@/composables/useLocale';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        state?: ActionState;
        loading?: boolean;
        autoSuccess?: boolean;
        action?: ActionPreset;
        icon?: Component;
        variant?: ButtonVariants['variant'];
        size?: ButtonVariants['size'];
        type?: 'button' | 'submit' | 'reset';
        disabled?: boolean;
        label?: string;
        loadingText?: string;
        successText?: string;
        errorText?: string;
        progress?: number | null;
        successDurationMs?: number;
        errorDurationMs?: number;
        class?: HTMLAttributes['class'];
        onClick?: (event: MouseEvent) => unknown;
    }>(),
    {
        state: undefined,
        loading: false,
        autoSuccess: false,
        action: 'default',
        icon: undefined,
        variant: 'default',
        size: 'default',
        type: 'button',
        disabled: false,
        label: undefined,
        loadingText: undefined,
        successText: undefined,
        errorText: undefined,
        progress: null,
        successDurationMs: 1800,
        errorDurationMs: 2400,
        class: undefined,
        onClick: undefined,
    },
);

const slots = useSlots();
const { isRtl, t } = useLocale();

const internalState = ref<ActionState>('idle');
let tempTimer: ReturnType<typeof setTimeout> | null = null;

function clearTempTimer(): void {
    if (tempTimer !== null) {
        clearTimeout(tempTimer);
        tempTimer = null;
    }
}

function triggerTempState(next: 'success' | 'error', durationMs: number): void {
    clearTempTimer();
    internalState.value = next;
    if (durationMs > 0) {
        tempTimer = setTimeout(() => {
            internalState.value = 'idle';
            tempTimer = null;
        }, durationMs);
    }
}

watch(
    () => props.loading,
    (next, prev) => {
        if (
            props.autoSuccess &&
            prev === true &&
            next === false &&
            !props.state
        ) {
            triggerTempState('success', props.successDurationMs);
        }
    },
);

onBeforeUnmount(() => {
    clearTempTimer();
});

const effectiveState = computed<ActionState>(() => {
    if (props.state !== undefined) {
        return props.state;
    }
    if (internalState.value !== 'idle') {
        return internalState.value;
    }
    if (props.loading) {
        return 'loading';
    }
    return 'idle';
});

const isIconOnly = computed(
    () =>
        (props.size === 'icon' ||
            props.size === 'icon-sm' ||
            props.size === 'icon-lg') &&
        !slots.default &&
        !props.label,
);

function tr(key: string, enFallback: string, arFallback: string): string {
    const translated = t(key);
    if (translated !== key) {
        return translated;
    }
    return isRtl.value ? arFallback : enFallback;
}

const presetConfig = computed(() => {
    switch (props.action) {
        case 'save':
            return {
                icon: Save,
                loading: tr(
                    'common.actionStates.saving',
                    'Saving...',
                    'جاري الحفظ...',
                ),
                success: tr('common.actionStates.saved', 'Saved', 'تم الحفظ'),
                error: tr(
                    'common.actionStates.saveFailed',
                    'Save failed',
                    'تعذر الحفظ',
                ),
            };
        case 'download':
            return {
                icon: Download,
                loading: tr(
                    'common.actionStates.downloading',
                    'Downloading...',
                    'جاري التنزيل...',
                ),
                success: tr(
                    'common.actionStates.downloaded',
                    'Downloaded',
                    'تم التنزيل',
                ),
                error: tr(
                    'common.actionStates.downloadFailed',
                    'Download failed',
                    'تعذر التنزيل',
                ),
            };
        case 'generate':
            return {
                icon: Sparkles,
                loading: tr(
                    'common.actionStates.generating',
                    'Generating...',
                    'جاري الإنشاء...',
                ),
                success: tr(
                    'common.actionStates.generated',
                    'Generated',
                    'تم الإنشاء',
                ),
                error: tr(
                    'common.actionStates.failed',
                    'Failed',
                    'فشلت العملية',
                ),
            };
        case 'upload':
            return {
                icon: Upload,
                loading: tr(
                    'common.actionStates.uploading',
                    'Uploading...',
                    'جاري الرفع...',
                ),
                success: tr(
                    'common.actionStates.uploaded',
                    'Uploaded',
                    'تم الرفع',
                ),
                error: tr(
                    'common.actionStates.uploadFailed',
                    'Upload failed',
                    'فشل الرفع',
                ),
            };
        case 'send':
            return {
                icon: Send,
                loading: tr(
                    'common.actionStates.sending',
                    'Sending...',
                    'جاري الإرسال...',
                ),
                success: tr('common.actionStates.sent', 'Sent', 'تم الإرسال'),
                error: tr(
                    'common.actionStates.sendFailed',
                    'Send failed',
                    'تعذر الإرسال',
                ),
            };
        case 'delete':
            return {
                icon: Trash2,
                loading: tr(
                    'common.actionStates.deleting',
                    'Deleting...',
                    'جاري الحذف...',
                ),
                success: tr(
                    'common.actionStates.deleted',
                    'Deleted',
                    'تم الحذف',
                ),
                error: tr(
                    'common.actionStates.deleteFailed',
                    'Delete failed',
                    'تعذر الحذف',
                ),
            };
        case 'default':
        default:
            return {
                icon: undefined,
                loading: tr(
                    'common.actionStates.processing',
                    'Processing...',
                    'جاري المعالجة...',
                ),
                success: tr('common.actionStates.done', 'Done', 'تم'),
                error: tr(
                    'common.actionStates.failed',
                    'Failed',
                    'فشلت العملية',
                ),
            };
    }
});

const idleIcon = computed(() => props.icon ?? presetConfig.value.icon);

const resolvedLoadingText = computed(() => {
    const base = props.loadingText ?? presetConfig.value.loading;
    if (
        props.progress !== null &&
        props.progress !== undefined &&
        Number.isFinite(props.progress)
    ) {
        const clamped = Math.max(0, Math.min(100, Math.round(props.progress)));
        return `${base} ${clamped}%`;
    }
    return base;
});

const resolvedSuccessText = computed(
    () => props.successText ?? presetConfig.value.success,
);

const resolvedErrorText = computed(
    () => props.errorText ?? presetConfig.value.error,
);

const liveAnnouncement = computed(() => {
    if (effectiveState.value === 'loading') {
        return resolvedLoadingText.value;
    }
    if (effectiveState.value === 'success') {
        return resolvedSuccessText.value;
    }
    if (effectiveState.value === 'error') {
        return resolvedErrorText.value;
    }
    return '';
});

function isPromiseLike(value: unknown): value is PromiseLike<unknown> {
    return (
        value !== null &&
        (typeof value === 'object' || typeof value === 'function') &&
        'then' in value &&
        typeof (value as PromiseLike<unknown>).then === 'function'
    );
}

async function handleClick(event: MouseEvent): Promise<void> {
    if (props.disabled || effectiveState.value === 'loading') {
        return;
    }
    if (!props.onClick) {
        return;
    }

    const result = props.onClick(event);
    if (!isPromiseLike(result)) {
        return;
    }

    if (props.state === undefined) {
        clearTempTimer();
        internalState.value = 'loading';
    }

    try {
        await result;
        if (props.state === undefined) {
            triggerTempState('success', props.successDurationMs);
        }
    } catch {
        if (props.state === undefined) {
            triggerTempState('error', props.errorDurationMs);
        }
    }
}
</script>

<template>
    <Button
        :type="type"
        :variant="variant"
        :size="size"
        :disabled="disabled || effectiveState === 'loading'"
        :aria-busy="effectiveState === 'loading' ? 'true' : undefined"
        :data-action-state="effectiveState"
        :class="
            cn(
                'group relative overflow-hidden',
                effectiveState === 'error' &&
                    'animate-shake ring-destructive/30 ring-2',
                effectiveState === 'success' &&
                    variant !== 'destructive' &&
                    'ring-2 ring-emerald-500/25',
                props.class,
            )
        "
        @click="handleClick"
    >
        <span class="grid place-items-center">
            <!-- Idle Layer -->
            <span
                :aria-hidden="effectiveState !== 'idle' ? 'true' : undefined"
                :class="
                    cn(
                        'col-start-1 row-start-1 inline-flex items-center justify-center gap-2 transition-all duration-150',
                        effectiveState === 'idle'
                            ? 'scale-100 opacity-100'
                            : 'pointer-events-none scale-95 opacity-0 select-none',
                    )
                "
            >
                <component
                    :is="idleIcon"
                    v-if="idleIcon && !slots.icon"
                    :class="
                        cn(
                            'size-4 shrink-0 transition-transform duration-150 motion-reduce:transform-none',
                            action === 'download' &&
                                'group-hover:translate-y-0.5',
                        )
                    "
                    aria-hidden="true"
                />
                <slot name="icon" />
                <slot>
                    <span v-if="label">{{ label }}</span>
                </slot>
            </span>

            <!-- Loading Layer -->
            <span
                :aria-hidden="effectiveState !== 'loading' ? 'true' : undefined"
                :class="
                    cn(
                        'col-start-1 row-start-1 inline-flex items-center justify-center gap-2 transition-all duration-150',
                        effectiveState === 'loading'
                            ? 'scale-100 opacity-100'
                            : 'pointer-events-none scale-95 opacity-0 select-none',
                    )
                "
            >
                <Spinner class="size-4 shrink-0" />
                <span v-if="!isIconOnly" class="tabular-nums">
                    {{ resolvedLoadingText }}
                </span>
            </span>

            <!-- Success Layer -->
            <span
                :aria-hidden="effectiveState !== 'success' ? 'true' : undefined"
                :class="
                    cn(
                        'col-start-1 row-start-1 inline-flex items-center justify-center gap-2 transition-all duration-150',
                        effectiveState === 'success'
                            ? 'scale-100 opacity-100'
                            : 'pointer-events-none scale-95 opacity-0 select-none',
                    )
                "
            >
                <Check class="size-4 shrink-0" aria-hidden="true" />
                <span v-if="!isIconOnly">{{ resolvedSuccessText }}</span>
            </span>

            <!-- Error Layer -->
            <span
                :aria-hidden="effectiveState !== 'error' ? 'true' : undefined"
                :class="
                    cn(
                        'col-start-1 row-start-1 inline-flex items-center justify-center gap-2 transition-all duration-150',
                        effectiveState === 'error'
                            ? 'scale-100 opacity-100'
                            : 'pointer-events-none scale-95 opacity-0 select-none',
                    )
                "
            >
                <AlertCircle class="size-4 shrink-0" aria-hidden="true" />
                <span v-if="!isIconOnly">{{ resolvedErrorText }}</span>
            </span>
        </span>

        <span class="sr-only" aria-live="polite">
            {{ liveAnnouncement }}
        </span>
    </Button>
</template>
