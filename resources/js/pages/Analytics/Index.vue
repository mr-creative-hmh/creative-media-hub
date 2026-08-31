<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import { BarChart3, HardDrive, Clock, Film, Tv, Cpu, Video, Sparkles } from 'lucide-vue-next';

const props = defineProps<{
    stats: {
        total_movies: number;
        total_series: number;
        total_episodes: number;
        total_storage_formatted: string;
        total_watch_hours: number;
        resolutions: {
            '4k': number;
            '1080p': number;
            '720p': number;
        };
        codecs: {
            hevc: number;
            h264: number;
            av1: number;
        };
        top_genres: any[];
    };
}>();

const { t, isRTL } = useI18n();
</script>

<template>
    <Head :title="t('analytics_view.title')" />

    <AppLayout v-slot="{ play }">
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                    <BarChart3 class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                        {{ t('analytics_view.title') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                        {{ isRTL ? 'إحصائيات شاملة لسعة التخزين، الجودات، والترميزات السينمائية.' : 'Complete library metrics, storage breakdown, and playback trends.' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Bento Grid Metrics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
            <!-- Total Movies -->
            <div class="glass-card rounded-3xl p-6 border border-white/10 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ t('analytics_view.total_movies') }}</span>
                    <Film class="w-5 h-5 text-cyan-400" />
                </div>
                <div class="text-3xl sm:text-4xl font-black text-white">{{ stats.total_movies }}</div>
            </div>

            <!-- TV Series -->
            <div class="glass-card rounded-3xl p-6 border border-white/10 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ t('analytics_view.total_series') }}</span>
                    <Tv class="w-5 h-5 text-indigo-400" />
                </div>
                <div class="text-3xl sm:text-4xl font-black text-white">{{ stats.total_series }}</div>
            </div>

            <!-- Storage Used -->
            <div class="glass-card rounded-3xl p-6 border border-white/10 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ t('analytics_view.storage_used') }}</span>
                    <HardDrive class="w-5 h-5 text-emerald-400" />
                </div>
                <div class="text-3xl sm:text-4xl font-black text-white">{{ stats.total_storage_formatted }}</div>
            </div>

            <!-- Watch Time -->
            <div class="glass-card rounded-3xl p-6 border border-white/10 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ t('analytics_view.watch_hours') }}</span>
                    <Clock class="w-5 h-5 text-amber-400" />
                </div>
                <div class="text-3xl sm:text-4xl font-black text-white">{{ stats.total_watch_hours }} {{ t('common.hours') }}</div>
            </div>
        </div>

        <!-- Two Columns Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Resolution Breakdown Card -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-4">
                <div class="flex items-center gap-2 mb-2">
                    <Video class="w-5 h-5 text-cyan-400" />
                    <h3 class="font-bold text-base text-white">{{ t('analytics_view.resolution_ratio') }}</h3>
                </div>

                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-xs font-bold text-slate-300 mb-1">
                            <span>4K UHD HDR</span>
                            <span>{{ stats.resolutions['4k'] }} items</span>
                        </div>
                        <div class="h-2 rounded-full bg-white/10 overflow-hidden">
                            <div class="h-full bg-cyan-400 rounded-full" style="width: 70%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between text-xs font-bold text-slate-300 mb-1">
                            <span>1080p Full HD</span>
                            <span>{{ stats.resolutions['1080p'] }} items</span>
                        </div>
                        <div class="h-2 rounded-full bg-white/10 overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width: 25%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Codec Breakdown Card -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-4">
                <div class="flex items-center gap-2 mb-2">
                    <Cpu class="w-5 h-5 text-indigo-400" />
                    <h3 class="font-bold text-base text-white">{{ t('analytics_view.codec_ratio') }}</h3>
                </div>

                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-xs font-bold text-slate-300 mb-1">
                            <span>HEVC / H.265 (High Efficiency)</span>
                            <span>{{ stats.codecs.hevc }} items</span>
                        </div>
                        <div class="h-2 rounded-full bg-white/10 overflow-hidden">
                            <div class="h-full bg-indigo-500 rounded-full" style="width: 80%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between text-xs font-bold text-slate-300 mb-1">
                            <span>H.264 / AVC</span>
                            <span>{{ stats.codecs.h264 }} items</span>
                        </div>
                        <div class="h-2 rounded-full bg-white/10 overflow-hidden">
                            <div class="h-full bg-purple-500 rounded-full" style="width: 20%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
