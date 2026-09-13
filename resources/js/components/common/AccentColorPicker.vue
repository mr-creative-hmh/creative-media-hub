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
    }
);

const { t, isRTL } = useI18n();
const { currentAccent, activePalette, setAccent, ACCENT_PALETTES } = useThemeAccent();

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
            class="flex items-center gap-2 px-3 py-1.5 rounded-xl glass-panel border border-white/10 hover:border-cyan-500/40 text-xs font-bold text-slate-300 hover:text-white transition-all active:scale-95 cursor-pointer shadow-sm group"
            :title="t('accent_theme.change_accent')"
            :aria-expanded="isOpen"
        >
            <span
                class="w-2.5 h-2.5 rounded-full shrink-0 transition-transform duration-300 group-hover:scale-125"
                :style="{
                    backgroundColor: activePalette.color,
                    boxShadow: '0 0 10px ' + activePalette.color,
                }"
            ></span>
            <Palette class="w-3.5 h-3.5 text-slate-400 group-hover:text-white transition-colors" />
            <span v-if="!compact" class="hidden sm:inline-block max-w-[90px] truncate text-slate-200">
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
                class="absolute mt-2 w-64 rounded-2xl p-2 bg-[#0B0F19]/95 backdrop-blur-2xl border border-white/15 shadow-2xl shadow-black/80 z-50 text-xs text-start"
                :class="isRTL ? 'left-0 origin-top-left' : 'right-0 origin-top-right'"
            >
                <!-- Menu Header -->
                <div class="px-2.5 py-2 mb-1 border-b border-white/10 flex items-center justify-between text-start">
                    <span class="font-bold text-white tracking-wide flex items-center gap-1.5">
                        <Palette class="w-3.5 h-3.5 text-cyan-400" />
                        {{ t('accent_theme.title') }}
                    </span>
                    <span class="text-[10px] text-slate-400 uppercase tracking-widest font-mono">
                        {{ ACCENT_PALETTES.length }}
                    </span>
                </div>

                <!-- Palettes List -->
                <div class="space-y-1 max-h-72 overflow-y-auto custom-scrollbar p-0.5">
                    <button
                        v-for="palette in ACCENT_PALETTES"
                        :key="palette.key"
                        @click="selectPalette(palette.key)"
                        class="w-full flex items-center justify-between p-2 rounded-xl transition-all cursor-pointer text-start"
                        :class="[
                            currentAccent === palette.key
                                ? 'bg-white/10 text-white font-bold ring-1 ring-white/20'
                                : 'text-slate-300 hover:bg-white/5 hover:text-white',
                        ]"
                    >
                        <div class="flex items-center gap-2.5 min-w-0">
                            <!-- Dual Swatch Gradient -->
                            <div
                                class="w-6 h-6 rounded-lg shrink-0 transition-transform duration-200 relative flex items-center justify-center shadow-sm"
                                :style="{
                                    background: `linear-gradient(135deg, ${palette.color} 0%, ${palette.secondaryColor} 100%)`,
                                    boxShadow: currentAccent === palette.key ? `0 0 12px ${palette.glowColor}` : 'none',
                                }"
                            >
                                <span
                                    v-if="currentAccent === palette.key"
                                    class="w-1.5 h-1.5 rounded-full bg-white shadow-xs"
                                ></span>
                            </div>

                            <!-- Name & Tone Description -->
                            <div class="flex flex-col min-w-0 text-start">
                                <span class="text-xs truncate font-semibold leading-tight">
                                    {{ t(palette.nameKey) }}
                                </span>
                                <span class="text-[10px] text-slate-400 truncate leading-tight mt-0.5">
                                    {{ t(palette.descKey) }}
                                </span>
                            </div>
                        </div>

                        <!-- Checkmark Slot (fixed width so all rows align uniformly) -->
                        <div class="w-5 flex items-center justify-center shrink-0">
                            <Check
                                v-if="currentAccent === palette.key"
                                class="w-4 h-4 text-cyan-400 shrink-0"
                            />
                        </div>
                    </button>
                </div>
            </div>
        </transition>
    </div>
</template>
