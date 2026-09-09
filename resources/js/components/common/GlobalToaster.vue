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
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[99999] pointer-events-none flex flex-col items-center gap-2.5 w-full max-w-md px-4 select-none"
        :dir="isRTL ? 'rtl' : 'ltr'"
        aria-live="polite"
    >
        <TransitionGroup
            name="toast-slide"
            tag="div"
            class="flex flex-col items-center gap-2.5 w-full"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto w-full flex items-start gap-3 p-3.5 rounded-2xl border backdrop-blur-2xl shadow-2xl transition-all duration-300 text-xs sm:text-sm"
                :class="{
                    'bg-slate-950/95 border-emerald-500/40 text-slate-100 shadow-[0_8px_30px_rgba(16,185,129,0.18)]': toast.type === 'success',
                    'bg-slate-950/95 border-rose-500/40 text-slate-100 shadow-[0_8px_30px_rgba(244,63,94,0.18)]': toast.type === 'error',
                    'bg-slate-950/95 border-amber-500/40 text-slate-100 shadow-[0_8px_30px_rgba(245,158,11,0.18)]': toast.type === 'warning',
                    'bg-slate-950/95 border-cyan-500/40 text-slate-100 shadow-[0_8px_30px_rgba(6,182,212,0.18)]': toast.type === 'info',
                }"
            >
                <!-- Status Icon -->
                <div class="shrink-0 mt-0.5">
                    <CheckCircle2
                        v-if="toast.type === 'success'"
                        class="w-4 h-4 text-emerald-400"
                    />
                    <AlertCircle
                        v-else-if="toast.type === 'error'"
                        class="w-4 h-4 text-rose-400"
                    />
                    <AlertTriangle
                        v-else-if="toast.type === 'warning'"
                        class="w-4 h-4 text-amber-400"
                    />
                    <Info
                        v-else
                        class="w-4 h-4 text-cyan-400"
                    />
                </div>

                <!-- Message Body with RTL/LTR alignment -->
                <div class="flex-1 min-w-0" :class="isRTL ? 'text-right' : 'text-left'">
                    <p
                        v-if="resolveText(toast.title)"
                        class="font-semibold text-xs tracking-wide uppercase mb-0.5"
                        :class="{
                            'text-emerald-400': toast.type === 'success',
                            'text-rose-400': toast.type === 'error',
                            'text-amber-400': toast.type === 'warning',
                            'text-cyan-400': toast.type === 'info',
                        }"
                    >
                        {{ resolveText(toast.title) }}
                    </p>
                    <p class="text-slate-200 leading-snug break-words">
                        {{ resolveText(toast.message) }}
                    </p>
                </div>

                <!-- Dismiss Button -->
                <button
                    type="button"
                    class="shrink-0 p-1 rounded-lg text-slate-400 hover:text-slate-100 hover:bg-white/10 transition ms-auto"
                    @click="dismiss(toast.id)"
                    :aria-label="isRTL ? 'إغلاق الإشعار' : 'Close notification'"
                >
                    <X class="w-3.5 h-3.5" />
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
