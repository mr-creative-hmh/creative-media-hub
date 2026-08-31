<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { Play, Clock } from 'lucide-vue-next';

const { t, isRTL } = useI18n();
const items = ref<any[]>([]);

const emit = defineEmits(['play']);

onMounted(async () => {
    try {
        const res = await fetch('/api/continue-watching');
        if (res.ok) {
            items.value = await res.json();
        }
    } catch (e) {
        console.error('Failed to load continue watching items', e);
    }
});
</script>

<template>
    <div v-if="items.length > 0" class="mb-8">
        <div class="flex items-center gap-2 mb-3.5">
            <Clock class="w-4 h-4 text-cyan-400" />
            <h3 class="font-bold text-sm text-slate-200 uppercase tracking-wider font-sans">
                {{ t('common.continue_watching') }}
            </h3>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
            <div
                v-for="item in items"
                :key="item.id"
                @click="emit('play', item)"
                class="glass-card group relative rounded-2xl overflow-hidden cursor-pointer border border-white/10 hover:border-cyan-500/40 transition-all flex flex-col"
            >
                <!-- Thumbnail -->
                <div class="relative aspect-video w-full overflow-hidden bg-slate-900">
                    <img
                        :src="item.backdrop_path || item.poster_path"
                        :alt="item.title"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 opacity-80 group-hover:opacity-100"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-[#07090E] via-transparent to-transparent"></div>

                    <!-- Play overlay button -->
                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40">
                        <div class="w-11 h-11 rounded-full bg-cyan-500 text-slate-950 flex items-center justify-center shadow-lg shadow-cyan-500/40 group-hover:scale-110 transition-transform">
                            <Play class="w-5 h-5 fill-current ml-0.5" />
                        </div>
                    </div>

                    <!-- Progress bar on thumbnail bottom -->
                    <div class="absolute bottom-0 inset-x-0 h-1 bg-slate-800">
                        <div class="h-full bg-gradient-to-r from-cyan-400 to-blue-500" :style="{ width: `${item.percent}%` }"></div>
                    </div>
                </div>

                <!-- Info footer -->
                <div class="p-3 flex items-center justify-between">
                    <div class="truncate">
                        <h4 class="font-bold text-sm text-slate-100 truncate group-hover:text-cyan-400 transition-colors">
                            {{ isRTL && item.title_ar ? item.title_ar : item.title }}
                        </h4>
                        <p class="text-[11px] text-slate-400">
                            {{ item.percent }}% {{ isRTL ? 'مكتمل' : 'completed' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
