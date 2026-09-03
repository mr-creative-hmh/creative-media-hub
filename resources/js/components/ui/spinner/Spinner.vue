<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    role="status"
    aria-label="Loading"
    :class="cn('relative inline-flex items-center justify-center shrink-0 size-4', props.class)"
  >
    <!-- Background Track -->
    <div class="absolute inset-0 rounded-full border-2 border-current opacity-20"></div>
    <!-- Spinning Accent Arc -->
    <div class="absolute inset-0 rounded-full border-2 border-transparent border-t-current animate-spin"></div>
    <!-- Inner Pulsing Sparkle Core -->
    <div class="w-[30%] h-[30%] rounded-full bg-current opacity-60 animate-pulse"></div>
  </div>
</template>

