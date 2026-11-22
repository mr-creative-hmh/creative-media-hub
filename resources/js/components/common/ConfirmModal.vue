<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import {
    AlertTriangle,
    Trash2,
    RotateCcw,
    X,
    ShieldAlert,
    Sparkles,
    AlertCircle,
} from '@lucide/vue';
import { useI18n } from '@/i18n/useI18n';

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

const emit = defineEmits(['confirm', 'cancel', 'close']);

const { isRTL } = useI18n();

const handleClose = () => {
    emit('cancel');
    emit('close');
};

const handleConfirm = () => {
    emit('confirm');
};

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && props.show) {
        handleClose();
    }
};

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <div
        v-if="show"
        class="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-md transition-all duration-300"
        @click.self="handleClose"
    >
        <div
            class="relative w-full max-w-lg space-y-5 overflow-hidden rounded-3xl border bg-[#080B12] p-6 shadow-2xl"
            :class="{
                'border-rose-500/40 shadow-rose-500/10': type === 'danger',
                'border-amber-500/40 shadow-amber-500/10': type === 'warning',
                'border-cyan-500/40 shadow-cyan-500/10': type === 'info',
            }"
        >
            <!-- Ambient Glow -->
            <div
                class="ambient-glow pointer-events-none -top-20 -right-20 h-64 w-64"
                :class="{
                    'bg-rose-500/15': type === 'danger',
                    'bg-amber-500/15': type === 'warning',
                    'bg-cyan-500/15': type === 'info',
                }"
            ></div>

            <!-- Header -->
            <div class="relative z-10 flex items-start gap-3.5">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border"
                    :class="{
                        'border-rose-500/30 bg-rose-500/15 text-rose-400':
                            type === 'danger',
                        'border-amber-500/30 bg-amber-500/15 text-amber-400':
                            type === 'warning',
                        'border-cyan-500/30 bg-cyan-500/15 text-cyan-400':
                            type === 'info',
                    }"
                >
                    <Trash2 v-if="type === 'danger'" class="h-5 w-5" />
                    <RotateCcw v-else-if="type === 'warning'" class="h-5 w-5" />
                    <Sparkles v-else class="h-5 w-5" />
                </div>

                <div class="min-w-0 flex-1">
                    <h3
                        class="text-base leading-tight font-extrabold text-white"
                    >
                        {{ title }}
                    </h3>
                    <p
                        class="mt-1.5 text-xs leading-relaxed whitespace-pre-line text-slate-400"
                    >
                        {{ message }}
                    </p>
                </div>

                <button
                    type="button"
                    @click="handleClose"
                    class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-white/5 text-slate-400 transition-all hover:bg-white/10 hover:text-white"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Extra Slot Content -->
            <div v-if="$slots.extra || $slots.default" class="relative z-10">
                <slot name="extra" />
                <slot />
            </div>

            <!-- Footer Actions -->
            <div
                class="relative z-10 flex items-center justify-end gap-2.5 border-t border-white/10 pt-3"
            >
                <button
                    type="button"
                    @click="handleClose"
                    class="cursor-pointer rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-xs font-bold text-slate-300 transition-all hover:bg-white/10"
                >
                    {{ cancelText || (isRTL ? 'إلغاء' : 'Cancel') }}
                </button>

                <button
                    type="button"
                    @click="handleConfirm"
                    :disabled="loading"
                    class="flex cursor-pointer items-center gap-1.5 rounded-xl px-5 py-2.5 text-xs font-extrabold shadow-lg transition-all active:scale-95"
                    :class="{
                        'bg-rose-500 text-slate-950 shadow-rose-500/20 hover:bg-rose-400':
                            type === 'danger',
                        'bg-amber-500 text-slate-950 shadow-amber-500/20 hover:bg-amber-400':
                            type === 'warning',
                        'bg-cyan-500 text-slate-950 shadow-cyan-500/20 hover:bg-cyan-400':
                            type === 'info',
                    }"
                >
                    <span
                        v-if="loading"
                        class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-slate-950 border-t-transparent"
                    ></span>
                    <span>{{
                        confirmText || (isRTL ? 'تأكيد' : 'Confirm')
                    }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
