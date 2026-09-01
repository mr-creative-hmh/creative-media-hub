<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { Play, Clock, Sparkles } from 'lucide-vue-next';

const { t, isRTL } = useI18n();
const items = ref<any[]>([]);

const emit = defineEmits(['play']);

const loadItems = async () => {
    try {
        const res = await fetch('/api/continue-watching');
        if (res.ok) {
            const data = await res.json();
            items.value = Array.isArray(data) ? data : (data.items || []);
        }
    } catch (e) {
        console.error('Failed to load continue watching items', e);
    }
};

onMounted(() => {
    loadItems();
});
</script>

<template>
    <div v-if="items.length > 0" class="mb-10">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <Clock class="w-5 h-5 text-cyan-400" />
                <h3 class="font-black text-base text-slate-900 dark:text-white uppercase tracking-wider font-sans">
                    {{ t('common.continue_watching') }}
                </h3>
                <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                    {{ items.length }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
            <div
                v-for="item in items"
                :key="item.id"
                @click="emit('play', item)"
                class="glass-panel group relative rounded-2xl overflow-hidden cursor-pointer border border-slate-200 dark:border-white/10 hover:border-cyan-500/40 hover:shadow-xl hover:shadow-cyan-500/10 transition-all flex flex-col bg-white dark:bg-[#07090E]"
            >
                <!-- Thumbnail Backdrop -->
                <div class="relative aspect-video w-full overflow-hidden bg-slate-900">
                    <img
                        :src="item.backdrop_path || item.poster_path"
                        :alt="item.title"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 opacity-85 group-hover:opacity-100"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent"></div>

                    <!-- Play overlay button -->
                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40 backdrop-blur-xs">
                        <div class="w-12 h-12 rounded-full bg-cyan-500 text-slate-950 flex items-center justify-center shadow-xl shadow-cyan-500/50 group-hover:scale-110 active:scale-95 transition-transform">
                            <Play class="w-5 h-5 fill-current ml-0.5" />
                        </div>
                    </div>

                    <!-- Progress bar on thumbnail bottom -->
                    <div class="absolute bottom-0 inset-x-0 h-1.5 bg-black/60">
                        <div
                            class="h-full bg-gradient-to-r from-cyan-400 to-blue-500 rounded-r-full"
                            :style="{ width: `${item.percent}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Info footer -->
                <div class="p-3.5 flex items-center justify-between">
                    <div class="truncate flex-1">
                        <h4 class="font-bold text-sm text-slate-900 dark:text-white truncate group-hover:text-cyan-400 transition-colors">
                            {{ isRTL && item.title_ar ? item.title_ar : item.title }}
                        </h4>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                {{ item.percent }}% {{ isRTL ? 'مكتمل' : 'watched' }}
                            </span>
                            <span class="text-[10px] text-slate-400 opacity-60">•</span>
                            <span class="text-[11px] font-mono text-cyan-400">
                                {{ item.current_time_formatted }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
