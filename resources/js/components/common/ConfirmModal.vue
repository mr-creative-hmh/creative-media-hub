<script setup lang="ts">
import { Trash2, RotateCcw, Sparkles } from '@lucide/vue';
import { useI18n } from '@/i18n/useI18n';
import { Dialog, DialogContent } from '@/components/ui/dialog';

const props = withDefaults(
    defineProps<{
        show: boolean;
        title: string;
        message: string;
        confirmText?: string;
        cancelText?: string;
        type?: 'danger' | 'warning' | 'info';
        loading?: boolean;
    }>(),
    {
        confirmText: '',
        cancelText: '',
        type: 'info',
        loading: false,
    },
);

const emit = defineEmits<{
    (e: 'confirm'): void;
    (e: 'cancel'): void;
    (e: 'close'): void;
}>();

const { isRTL } = useI18n();

const handleClose = () => {
    emit('cancel');
    emit('close');
};

const handleConfirm = () => {
    emit('confirm');
};
</script>

<template>
    <Dialog :open="show" @update:open="(v) => !v && handleClose()">
        <DialogContent
            size="sm"
            class="cf-glass-elevated border-border/80 bg-card/95 relative overflow-hidden rounded-3xl border p-6 shadow-2xl"
            :class="{
                'border-destructive/40 shadow-destructive/10':
                    type === 'danger',
                'border-amber-500/40 shadow-amber-500/10': type === 'warning',
                'border-primary/40 shadow-primary/10': type === 'info',
            }"
            :show-close-button="true"
        >
            <!-- Ambient Glow -->
            <div
                class="ambient-glow pointer-events-none absolute -end-20 -top-20 h-64 w-64 rounded-full blur-3xl"
                :class="{
                    'bg-destructive/15': type === 'danger',
                    'bg-amber-500/15': type === 'warning',
                    'bg-primary/15': type === 'info',
                }"
            ></div>

            <!-- Header -->
            <div class="relative z-10 flex items-start gap-3.5">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border"
                    :class="{
                        'border-destructive/30 bg-destructive/15 text-destructive':
                            type === 'danger',
                        'border-amber-500/30 bg-amber-500/15 text-amber-400':
                            type === 'warning',
                        'border-primary/30 bg-primary/15 text-primary':
                            type === 'info',
                    }"
                >
                    <Trash2 v-if="type === 'danger'" class="h-5 w-5" />
                    <RotateCcw v-else-if="type === 'warning'" class="h-5 w-5" />
                    <Sparkles v-else class="h-5 w-5" />
                </div>

                <div class="min-w-0 flex-1">
                    <h3
                        class="text-foreground text-base leading-tight font-extrabold"
                    >
                        {{ title }}
                    </h3>
                    <p
                        class="text-muted-foreground mt-1.5 text-xs leading-relaxed whitespace-pre-line"
                    >
                        {{ message }}
                    </p>
                </div>
            </div>

            <!-- Extra Slot Content -->
            <div v-if="$slots.extra || $slots.default" class="relative z-10">
                <slot name="extra" />
                <slot />
            </div>

            <!-- Footer Actions -->
            <div
                class="border-border/60 relative z-10 flex items-center justify-end gap-2.5 border-t pt-3"
            >
                <button
                    type="button"
                    @click="handleClose"
                    class="border-border/80 cf-glass text-muted-foreground hover:border-primary/40 hover:text-foreground focus-visible:ring-primary/30 h-10 cursor-pointer rounded-xl border px-4 text-xs font-bold transition-all focus-visible:ring-2 focus-visible:outline-none active:scale-95"
                >
                    {{ cancelText || (isRTL ? 'إلغاء' : 'Cancel') }}
                </button>

                <button
                    type="button"
                    @click="handleConfirm"
                    :disabled="loading"
                    class="focus-visible:ring-primary/30 flex h-10 cursor-pointer items-center gap-1.5 rounded-xl px-5 text-xs font-extrabold shadow-lg transition-all focus-visible:ring-2 focus-visible:outline-none active:scale-95 disabled:pointer-events-none disabled:opacity-50"
                    :class="{
                        'bg-destructive text-destructive-foreground shadow-destructive/20 hover:bg-destructive/90':
                            type === 'danger',
                        'bg-amber-500 text-slate-950 shadow-amber-500/20 hover:bg-amber-400':
                            type === 'warning',
                        'bg-primary text-primary-foreground shadow-primary/25 hover:bg-primary/90':
                            type === 'info',
                    }"
                >
                    <span
                        v-if="loading"
                        class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"
                    ></span>
                    <span>{{
                        confirmText || (isRTL ? 'تأكيد' : 'Confirm')
                    }}</span>
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
