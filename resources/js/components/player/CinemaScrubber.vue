<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    currentTime: number;
    duration: number;
    bufferedPercent: number;
    progressPercent: number;
    formatTime: (sec: number) => string;
}>();

const emit = defineEmits<{
    (e: 'seek', seconds: number): void;
}>();

const scrubberRef = ref<HTMLDivElement | null>(null);
const isHovering = ref(false);
const hoverPercent = ref(0);
const hoverTime = ref(0);

const handleMouseMove = (e: MouseEvent) => {
    if (!scrubberRef.value || !props.duration || props.duration <= 0) return;
    const rect = scrubberRef.value.getBoundingClientRect();
    const x = Math.max(0, Math.min(e.clientX - rect.left, rect.width));
    const pct = (x / rect.width) * 100;
    hoverPercent.value = pct;
    hoverTime.value = (pct / 100) * props.duration;
};

const handleClick = (e: MouseEvent) => {
    if (!scrubberRef.value || !props.duration || props.duration <= 0) return;
    const rect = scrubberRef.value.getBoundingClientRect();
    const x = Math.max(0, Math.min(e.clientX - rect.left, rect.width));
    const target = (x / rect.width) * props.duration;
    emit('seek', target);
};
</script>

<template>
    <div
        ref="scrubberRef"
        class="relative py-3 group cursor-pointer select-none"
        @mouseenter="isHovering = true"
        @mouseleave="isHovering = false"
        @mousemove="handleMouseMove"
        @click="handleClick"
    >
        <!-- Hover Time Tooltip -->
        <div
            v-if="isHovering && duration > 0"
            class="absolute bottom-8 -translate-x-1/2 px-2.5 py-1 rounded-lg bg-slate-900/90 text-white text-xs font-mono font-bold border border-white/20 shadow-xl backdrop-blur-md pointer-events-none transition-opacity"
            :style="{ left: `${hoverPercent}%` }"
        >
            {{ formatTime(hoverTime) }}
        </div>

        <!-- Scrubber Track Background -->
        <div class="h-1.5 sm:h-2 w-full rounded-full bg-white/20 relative overflow-hidden transition-all group-hover:h-2.5 sm:group-hover:h-3">
            <!-- Buffered Progress -->
            <div
                class="absolute inset-y-0 left-0 bg-white/30 rounded-full transition-all duration-300"
                :style="{ width: `${bufferedPercent}%` }"
            />

            <!-- Played Progress -->
            <div
                class="absolute inset-y-0 left-0 bg-gradient-to-r from-cyan-500 to-cyan-400 rounded-full"
                :style="{ width: `${progressPercent}%` }"
            />
        </div>

        <!-- Scrubber Scrubber Thumb -->
        <div
            class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-3.5 h-3.5 sm:w-4 sm:h-4 rounded-full bg-white shadow-lg shadow-cyan-500/50 scale-0 group-hover:scale-100 transition-transform pointer-events-none border-2 border-cyan-400"
            :style="{ left: `${progressPercent}%` }"
        />
    </div>
</template>

