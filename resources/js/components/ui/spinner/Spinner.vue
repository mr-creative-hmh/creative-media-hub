<script setup lang="ts">
import { Loader2Icon } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const { locale, t } = useLocale();
const loadingLabel = () => {
    const val = t('common.loading');
    return val !== 'common.loading'
        ? val
        : locale.value === 'ar'
          ? 'جاري التحميل...'
          : 'Loading';
};
</script>

<template>
    <Loader2Icon
        role="status"
        :aria-label="loadingLabel()"
        :class="cn('size-4 animate-spin', props.class)"
    />
</template>
