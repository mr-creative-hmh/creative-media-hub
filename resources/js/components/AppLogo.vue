<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex items-center gap-2.5 select-none">
        <div class="flex aspect-square size-8 items-center justify-center rounded-lg shadow-md shadow-cyan-500/10 shrink-0">
            <AppLogoIcon class="size-8" />
        </div>
        <div class="grid flex-1 text-left text-sm leading-tight">
            <span class="font-extrabold tracking-tight bg-gradient-to-r from-cyan-400 via-blue-400 to-indigo-400 bg-clip-text text-transparent">Creative Media Hub</span>
        </div>
    </div>
</template>
