<script setup lang="ts">
import WatchHistoryBar from './WatchHistoryBar.vue';

defineProps<{
    type?: 'all' | 'movie' | 'series' | 'episode' | 'collection';
    title?: string;
}>();

const emit = defineEmits(['play', 'item-removed']);
</script>

<template>
    <WatchHistoryBar
        :type="type"
        :title="title"
        @play="(item) => emit('play', item)"
        @item-removed="(item) => emit('item-removed', item)"
    />
</template>