<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
</script>

<template>
    <AppLayout v-slot="slotProps">
        <slot v-bind="slotProps" />
    </AppLayout>
</template>
