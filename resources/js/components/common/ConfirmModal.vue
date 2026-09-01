<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { AlertTriangle, Trash2, RotateCcw, X, ShieldAlert, Sparkles } from 'lucide-vue-next';
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
    }
);

const emit = defineEmits(['confirm', 'cancel', 'close']);

const { isRTL } = useI18n();

const handleClose = () => {
    emit('cancel');
    emit('close');
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
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md transition-all duration-300 animate-in fade-in"
        @click.self="handleClose"
    >
        <div class="relative w-full max-w-lg rounded-3xl bg-[#080B12] border p-6 shadow-2xl space-y-5 overflow-hidden"
            :class="{
                'border-rose-500/40 shadow-rose-500/10': type === 'danger',
                'border-amber-500/40 shadow-amber-500/10': type === 'warning',
                'border-cyan-500/40 shadow-cyan-500/10': type === 'info',
            }"
        >
            <!-- Ambient Glow -->
            <div
                class="ambient-glow w-64 h-64 -top-20 -right-20 pointer-events-none"
                :class="{
                    'bg-rose-500/15': type === 'danger',
                    'bg-amber-500/15': type === 'warning',
                    'bg-cyan-500/15': type === 'info',
                }"
            ></div>

            <!-- Header -->
            <div class="flex items-start gap-3.5 relative z-10">
                <div
                    class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 border"
                    :class="{
                        'bg-rose-500/15 text-rose-400 border-rose-500/30': type === 'danger',
                        'bg-amber-500/15 text-amber-400 border-amber-500/30': type === 'warning',
                        'bg-cyan-500/15 text-cyan-400 border-cyan-500/30': type === 'info',
                    }"
                >
                    <Trash2 v-if="type === 'danger'" class="w-5 h-5" />
                    <RotateCcw v-else-if="type === 'warning'" class="w-5 h-5" />
                    <Sparkles v-else class="w-5 h-5" />
                </div>

                <div class="min-w-0 flex-1">
                    <h3 class="font-extrabold text-base text-white leading-tight">
                        {{ title }}
                    </h3>
                    <p class="text-xs text-slate-400 mt-1.5 leading-relaxed">
                        {{ message }}
                    </p>
                </div>

                <button
                    type="button"
                    @click="handleClose"
                    class="w-8 h-8 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white flex items-center justify-center transition-all cursor-pointer shrink-0"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Extra Slot Content (e.g. Move vs Copy Selector) -->
            <div v-if="$slots.extra || $slots.default" class="relative z-10">
                <slot name="extra" />
                <slot />
            </div>

            <!-- Footer Actions -->
            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-white/10 relative z-10">
                <button
                    type="button"
                    @click="handleClose"
                    class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10 text-xs font-bold transition-all cursor-pointer"
                >
                    {{ cancelText || (isRTL ? 'إلغاء' : 'Cancel') }}
                </button>

                <button
                    type="button"
                    @click="emit('confirm')"
                    :disabled="loading"
                    class="px-5 py-2.5 rounded-xl text-xs font-extrabold flex items-center gap-1.5 active:scale-95 transition-all cursor-pointer shadow-lg"
                    :class="{
                        'bg-rose-500 hover:bg-rose-400 text-slate-950 shadow-rose-500/20': type === 'danger',
                        'bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-amber-500/20': type === 'warning',
                        'bg-cyan-500 hover:bg-cyan-400 text-slate-950 shadow-cyan-500/20': type === 'info',
                    }"
                >
                    <span v-if="loading" class="w-3.5 h-3.5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
                    <span>{{ confirmText || (isRTL ? 'تأكيد' : 'Confirm') }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
