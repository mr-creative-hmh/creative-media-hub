<script setup lang="ts">
import { useToast, type LocalizedText } from '@/composables/useToast';
import { useI18n } from '@/i18n/useI18n';
import {
    CheckCircle2,
    AlertCircle,
    AlertTriangle,
    Info,
    X,
} from 'lucide-vue-next';

const { toasts, dismiss } = useToast();
const { isRTL, locale } = useI18n();

const resolveText = (text?: LocalizedText): string => {
    if (!text) return '';
    if (typeof text === 'string') return text;
    if (isRTL.value || locale.value === 'ar') {
        return text.ar || text.en || '';
    }
    return text.en || text.ar || '';
};
</script>

<template>
    <div
        class="pointer-events-none fixed bottom-6 left-1/2 z-[99999] flex w-full max-w-md -translate-x-1/2 flex-col items-center gap-2.5 px-4 select-none"
        :dir="isRTL ? 'rtl' : 'ltr'"
        aria-live="polite"
    >
        <TransitionGroup
            name="toast-slide"
            tag="div"
            class="flex w-full flex-col items-center gap-2.5"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto flex w-full items-start gap-3 rounded-2xl border p-3.5 text-xs shadow-2xl backdrop-blur-2xl transition-all duration-300 sm:text-sm"
                :class="{
                    'border-emerald-500/40 bg-slate-950/95 text-slate-100 shadow-[0_8px_30px_rgba(16,185,129,0.18)]':
                        toast.type === 'success',
                    'border-rose-500/40 bg-slate-950/95 text-slate-100 shadow-[0_8px_30px_rgba(244,63,94,0.18)]':
                        toast.type === 'error',
                    'border-amber-500/40 bg-slate-950/95 text-slate-100 shadow-[0_8px_30px_rgba(245,158,11,0.18)]':
                        toast.type === 'warning',
                    'border-cyan-500/40 bg-slate-950/95 text-slate-100 shadow-[0_8px_30px_rgba(6,182,212,0.18)]':
                        toast.type === 'info',
                }"
            >
                <!-- Status Icon -->
                <div class="mt-0.5 shrink-0">
                    <CheckCircle2
                        v-if="toast.type === 'success'"
                        class="h-4 w-4 text-emerald-400"
                    />
                    <AlertCircle
                        v-else-if="toast.type === 'error'"
                        class="h-4 w-4 text-rose-400"
                    />
                    <AlertTriangle
                        v-else-if="toast.type === 'warning'"
                        class="h-4 w-4 text-amber-400"
                    />
                    <Info v-else class="h-4 w-4 text-cyan-400" />
                </div>

                <!-- Message Body with RTL/LTR alignment -->
                <div
                    class="min-w-0 flex-1"
                    :class="isRTL ? 'text-right' : 'text-left'"
                >
                    <p
                        v-if="resolveText(toast.title)"
                        class="mb-0.5 text-xs font-semibold tracking-wide uppercase"
                        :class="{
                            'text-emerald-400': toast.type === 'success',
                            'text-rose-400': toast.type === 'error',
                            'text-amber-400': toast.type === 'warning',
                            'text-cyan-400': toast.type === 'info',
                        }"
                    >
                        {{ resolveText(toast.title) }}
                    </p>
                    <p class="leading-snug break-words text-slate-200">
                        {{ resolveText(toast.message) }}
                    </p>
                </div>

                <!-- Dismiss Button -->
                <button
                    type="button"
                    class="ms-auto shrink-0 rounded-lg p-1 text-slate-400 transition hover:bg-white/10 hover:text-slate-100"
                    @click="dismiss(toast.id)"
                    :aria-label="isRTL ? 'إغلاق الإشعار' : 'Close notification'"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-slide-enter-active,
.toast-slide-leave-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.toast-slide-enter-from {
    opacity: 0;
    transform: translateY(24px) scale(0.95);
}

.toast-slide-leave-to {
    opacity: 0;
    transform: translateY(12px) scale(0.95);
}
</style>
