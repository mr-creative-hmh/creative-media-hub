<script setup lang="ts">
import { ref } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { Palette, Check } from 'lucide-vue-next';
import { useThemeAccent, type AccentColor } from '@/composables/useThemeAccent';
import { useI18n } from '@/i18n/useI18n';

withDefaults(
    defineProps<{
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);

const { t, isRTL } = useI18n();
const { currentAccent, activePalette, setAccent, ACCENT_PALETTES } =
    useThemeAccent();

const isOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

onClickOutside(dropdownRef, () => {
    isOpen.value = false;
});

const selectPalette = (key: AccentColor) => {
    setAccent(key);
    isOpen.value = false;
};
</script>

<template>
    <div ref="dropdownRef" class="relative inline-block text-start">
        <!-- Trigger Button -->
        <button
            @click="isOpen = !isOpen"
            type="button"
            class="glass-panel group flex cursor-pointer items-center gap-2 rounded-xl border border-white/10 px-3 py-1.5 text-xs font-bold text-slate-300 shadow-sm transition-all hover:border-cyan-500/40 hover:text-white active:scale-95"
            :title="t('accent_theme.change_accent')"
            :aria-expanded="isOpen"
        >
            <span
                class="h-2.5 w-2.5 shrink-0 rounded-full transition-transform duration-300 group-hover:scale-125"
                :style="{
                    backgroundColor: activePalette.color,
                    boxShadow: '0 0 10px ' + activePalette.color,
                }"
            ></span>
            <Palette
                class="h-3.5 w-3.5 text-slate-400 transition-colors group-hover:text-white"
            />
            <span
                v-if="!compact"
                class="hidden max-w-[90px] truncate text-slate-200 sm:inline-block"
            >
                {{ t(activePalette.nameKey) }}
            </span>
        </button>

        <!-- Dropdown Menu -->
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div
                v-if="isOpen"
                class="absolute z-50 mt-2 w-64 rounded-2xl border border-white/15 bg-[#0B0F19]/95 p-2 text-start text-xs shadow-2xl shadow-black/80 backdrop-blur-2xl"
                :class="
                    isRTL
                        ? 'left-0 origin-top-left'
                        : 'right-0 origin-top-right'
                "
            >
                <!-- Menu Header -->
                <div
                    class="mb-1 flex items-center justify-between border-b border-white/10 px-2.5 py-2 text-start"
                >
                    <span
                        class="flex items-center gap-1.5 font-bold tracking-wide text-white"
                    >
                        <Palette class="h-3.5 w-3.5 text-cyan-400" />
                        {{ t('accent_theme.title') }}
                    </span>
                    <span
                        class="font-mono text-[10px] tracking-widest text-slate-400 uppercase"
                    >
                        {{ ACCENT_PALETTES.length }}
                    </span>
                </div>

                <!-- Palettes List -->
                <div
                    class="custom-scrollbar max-h-72 space-y-1 overflow-y-auto p-0.5"
                >
                    <button
                        v-for="palette in ACCENT_PALETTES"
                        :key="palette.key"
                        @click="selectPalette(palette.key)"
                        class="flex w-full cursor-pointer items-center justify-between rounded-xl p-2 text-start transition-all"
                        :class="[
                            currentAccent === palette.key
                                ? 'bg-white/10 font-bold text-white ring-1 ring-white/20'
                                : 'text-slate-300 hover:bg-white/5 hover:text-white',
                        ]"
                    >
                        <div class="flex min-w-0 items-center gap-2.5">
                            <!-- Dual Swatch Gradient -->
                            <div
                                class="relative flex h-6 w-6 shrink-0 items-center justify-center rounded-lg shadow-sm transition-transform duration-200"
                                :style="{
                                    background: `linear-gradient(135deg, ${palette.color} 0%, ${palette.secondaryColor} 100%)`,
                                    boxShadow:
                                        currentAccent === palette.key
                                            ? `0 0 12px ${palette.glowColor}`
                                            : 'none',
                                }"
                            >
                                <span
                                    v-if="currentAccent === palette.key"
                                    class="h-1.5 w-1.5 rounded-full bg-white shadow-xs"
                                ></span>
                            </div>

                            <!-- Name & Tone Description -->
                            <div class="flex min-w-0 flex-col text-start">
                                <span
                                    class="truncate text-xs leading-tight font-semibold"
                                >
                                    {{ t(palette.nameKey) }}
                                </span>
                                <span
                                    class="mt-0.5 truncate text-[10px] leading-tight text-slate-400"
                                >
                                    {{ t(palette.descKey) }}
                                </span>
                            </div>
                        </div>

                        <!-- Checkmark Slot (fixed width so all rows align uniformly) -->
                        <div
                            class="flex w-5 shrink-0 items-center justify-center"
                        >
                            <Check
                                v-if="currentAccent === palette.key"
                                class="h-4 w-4 shrink-0 text-cyan-400"
                            />
                        </div>
                    </button>
                </div>
            </div>
        </transition>
    </div>
</template>
