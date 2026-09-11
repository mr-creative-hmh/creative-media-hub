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
        @play="(item, playlist) => emit('play', item, playlist)"
        @item-removed="(item) => emit('item-removed', item)"
    />
</template>