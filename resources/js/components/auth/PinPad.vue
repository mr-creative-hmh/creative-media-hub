<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { useLocale } from '@/composables/useLocale';
import { Delete, ArrowLeft, ShieldAlert } from '@lucide/vue';

const props = defineProps<{
    profile: {
        id: number;
        name: string;
        username?: string;
        avatar: string;
        role: string;
        is_kids?: boolean;
        pin_length?: number;
    };
    loading?: boolean;
    errorMessage?: string | null;
}>();

const emit = defineEmits<{
    (e: 'submit', pin: string): void;
    (e: 'back'): void;
}>();

const { t } = useI18n();
const { isRtl } = useLocale();

const pin = ref<string>('');
const isShaking = ref(false);

const expectedLength = computed(() => {
    const len = Number(props.profile?.pin_length);
    return len === 4 || len === 6 || len === 8 ? len : 4;
});

const appendDigit = (digit: string) => {
    if (props.loading) return;
    if (pin.value.length < expectedLength.value) {
        pin.value += digit;
        if (pin.value.length === expectedLength.value) {
            triggerSubmit();
        }
    }
};

const backspace = () => {
    if (props.loading) return;
    pin.value = pin.value.slice(0, -1);
};

const clear = () => {
    if (props.loading) return;
    pin.value = '';
};

const triggerSubmit = () => {
    if (pin.value.length === expectedLength.value) {
        emit('submit', pin.value);
    }
};

const triggerShake = () => {
    isShaking.value = true;
    setTimeout(() => {
        isShaking.value = false;
        pin.value = '';
    }, 600);
};

defineExpose({
    triggerShake,
    clear,
});

const handleKeyDown = (e: KeyboardEvent) => {
    if (props.loading) return;
    if (/^[0-9]$/.test(e.key)) {
        appendDigit(e.key);
    } else if (e.key === 'Backspace') {
        backspace();
    } else if (e.key === 'Escape') {
        emit('back');
    } else if (e.key === 'Enter') {
        triggerSubmit();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <div
        class="cf-glass-elevated border-border/80 animate-in fade-in zoom-in-95 mx-auto flex w-full max-w-sm flex-col items-center rounded-3xl border p-6 shadow-2xl backdrop-blur-2xl duration-300 select-none sm:p-8"
    >
        <!-- Back Button -->
        <button
            type="button"
            @click="emit('back')"
            class="text-muted-foreground hover:text-foreground bg-card/60 border-border/80 hover:border-border mb-6 inline-flex cursor-pointer items-center gap-2 self-start rounded-xl border px-3 py-1.5 text-xs font-semibold transition-colors"
        >
            <ArrowLeft class="size-4" :class="isRtl ? 'rotate-180' : ''" />
            <span>{{ t('auth.switch_profile') || 'Change Profile' }}</span>
        </button>

        <!-- Selected Profile Header -->
        <div class="mb-6 flex flex-col items-center">
            <div
                class="from-primary/30 via-card to-primary/10 border-primary/40 relative mb-3 flex size-20 items-center justify-center rounded-2xl border bg-gradient-to-br p-1 shadow-[0_0_30px_color-mix(in_oklab,var(--primary)_30%,transparent)]"
            >
                <span class="text-4xl">
                    {{
                        profile.avatar === 'director'
                            ? '🎬'
                            : profile.avatar === 'robot'
                              ? '🤖'
                              : profile.avatar === 'clapper'
                                ? '🎞️'
                                : profile.avatar === 'star'
                                  ? '⭐'
                                  : profile.avatar === 'reel'
                                    ? '🎥'
                                    : '🍿'
                    }}
                </span>
                <span
                    v-if="profile.role === 'admin'"
                    class="bg-primary text-primary-foreground absolute -right-1 -bottom-1 rounded-md px-1.5 py-0.5 font-mono text-[9px] font-black tracking-wider uppercase shadow-md"
                >
                    {{ t('auth.admin_badge') || 'Admin' }}
                </span>
                <span
                    v-else-if="profile.is_kids"
                    class="absolute -right-1 -bottom-1 rounded-md bg-amber-500 px-1.5 py-0.5 font-mono text-[9px] font-black tracking-wider text-slate-950 uppercase shadow-md"
                >
                    {{ t('auth.kids_badge') || 'Kids' }}
                </span>
            </div>
            <h2
                class="text-foreground text-center text-xl font-bold tracking-tight"
            >
                {{ profile.name }}
            </h2>
            <p class="text-muted-foreground mt-0.5 text-xs">
                {{
                    t('auth.enter_pin_count', { count: expectedLength }) ||
                    t('auth.enter_pin') ||
                    `Enter ${expectedLength}-Digit PIN`
                }}
            </p>
        </div>

        <!-- PIN Dots Display -->
        <div
            class="mb-8 flex h-10 items-center justify-center gap-3 transition-transform"
            :class="{ 'cf-animate-error-shake text-destructive': isShaking }"
        >
            <div
                v-for="i in expectedLength"
                :key="i"
                class="size-4 rounded-full border-2 transition-all duration-200"
                :class="[
                    pin.length >= i
                        ? 'bg-primary border-primary scale-110 shadow-[0_0_14px_var(--primary)]'
                        : 'border-border/80 bg-background/80',
                ]"
            />
        </div>

        <!-- Error Message -->
        <div
            v-if="errorMessage"
            class="bg-destructive/15 border-destructive/30 text-destructive-foreground animate-in fade-in mb-5 flex w-full items-center gap-2 rounded-xl border px-3.5 py-2.5 text-xs"
        >
            <ShieldAlert class="text-destructive size-4 shrink-0" />
            <span>{{ errorMessage }}</span>
        </div>

        <!-- Tactile 3x4 Numeric Keypad with dir="ltr" to maintain standard ordering in RTL -->
        <div dir="ltr" class="grid w-full max-w-[280px] grid-cols-3 gap-3.5">
            <button
                v-for="num in ['1', '2', '3', '4', '5', '6', '7', '8', '9']"
                :key="num"
                type="button"
                @click="appendDigit(num)"
                :disabled="loading"
                class="bg-card/90 border-border/80 hover:border-primary/50 hover:bg-muted text-foreground flex h-14 cursor-pointer items-center justify-center rounded-2xl border font-mono text-xl font-bold shadow-md transition-all duration-150 active:scale-95 disabled:opacity-50"
            >
                {{ num }}
            </button>

            <!-- Bottom Row: Clear, 0, Backspace -->
            <button
                type="button"
                @click="clear"
                :disabled="loading || pin.length === 0"
                class="bg-card/50 border-border/80 text-muted-foreground hover:text-foreground hover:bg-muted flex h-14 cursor-pointer items-center justify-center rounded-2xl border text-xs font-bold uppercase transition-all duration-150 active:scale-95 disabled:opacity-30"
            >
                {{ t('common.clear') || 'Clear' }}
            </button>

            <button
                type="button"
                @click="appendDigit('0')"
                :disabled="loading"
                class="bg-card/90 border-border/80 hover:border-primary/50 hover:bg-muted text-foreground flex h-14 cursor-pointer items-center justify-center rounded-2xl border font-mono text-xl font-bold shadow-md transition-all duration-150 active:scale-95 disabled:opacity-50"
            >
                0
            </button>

            <button
                type="button"
                @click="backspace"
                :disabled="loading || pin.length === 0"
                class="bg-card/50 border-border/80 text-muted-foreground hover:text-foreground hover:bg-muted flex h-14 cursor-pointer items-center justify-center rounded-2xl border transition-all duration-150 active:scale-95 disabled:opacity-30"
                :aria-label="t('common.delete') || 'Backspace'"
            >
                <Delete class="size-5" />
            </button>
        </div>

        <!-- Submit Button if PIN matches expectedLength -->
        <button
            v-if="pin.length === expectedLength"
            type="button"
            @click="triggerSubmit"
            :disabled="loading"
            class="bg-primary text-primary-foreground shadow-primary/25 hover:bg-primary/90 mt-6 flex w-full max-w-[280px] cursor-pointer items-center justify-center gap-2 rounded-xl py-3 text-sm font-bold shadow-lg transition-all active:scale-98"
        >
            <span>{{ t('common.continue') || 'Continue' }}</span>
        </button>
    </div>
</template>
