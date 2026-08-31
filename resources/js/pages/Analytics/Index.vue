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
                <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                    <BarChart3 class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white">
                        {{ t('analytics_view.title') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-0.5">
                        {{ isRTL ? 'إحصائيات المكتبة الكاملة وتفاصيل التخزين والجودة.' : 'Complete library metrics, storage breakdown, and playback trends.' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Bento Grid Metrics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
            <!-- Total Movies -->
            <div class="glass-card rounded-3xl p-6 border border-slate-200 dark:border-white/10 flex flex-col justify-between shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('analytics_view.total_movies') }}</span>
                    <Film class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                </div>
                <div class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white">{{ stats.total_movies }}</div>
            </div>

            <!-- TV Series -->
            <div class="glass-card rounded-3xl p-6 border border-slate-200 dark:border-white/10 flex flex-col justify-between shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('analytics_view.total_series') }}</span>
                    <Tv class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white">{{ stats.total_series }}</div>
            </div>

            <!-- Storage Used -->
            <div class="glass-card rounded-3xl p-6 border border-slate-200 dark:border-white/10 flex flex-col justify-between shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('analytics_view.storage_used') }}</span>
                    <HardDrive class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white">{{ stats.total_storage_formatted }}</div>
            </div>

            <!-- Watch Time -->
            <div class="glass-card rounded-3xl p-6 border border-slate-200 dark:border-white/10 flex flex-col justify-between shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ isRTL ? 'ساعات المشاهدة' : 'Hours Watched' }}</span>
                    <Clock class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white">{{ stats.total_watch_hours || 0 }} h</div>
            </div>
        </div>

        <!-- 2-Column Quality & Codec Distribution -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Resolutions -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-white/10 space-y-4 shadow-sm">
                <div class="flex items-center gap-2.5 pb-3 border-b border-slate-200 dark:border-white/10">
                    <Video class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                    <h3 class="font-bold text-base text-slate-900 dark:text-white">
                        {{ isRTL ? 'توزيع جودة الفيديو' : 'Video Resolution Distribution' }}
                    </h3>
                </div>

                <div class="space-y-3">
                    <div>
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            <span>4K Ultra HD</span>
                            <span>{{ stats.resolutions?.['4k'] || 0 }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-200 dark:bg-white/10 overflow-hidden">
                            <div class="h-full bg-cyan-500 rounded-full" :style="{ width: `${Math.min(100, ((stats.resolutions?.['4k'] || 0) / Math.max(1, stats.total_movies + stats.total_episodes)) * 100)}%` }"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            <span>1080p Full HD</span>
                            <span>{{ stats.resolutions?.['1080p'] || 0 }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-200 dark:bg-white/10 overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" :style="{ width: `${Math.min(100, ((stats.resolutions?.['1080p'] || 0) / Math.max(1, stats.total_movies + stats.total_episodes)) * 100)}%` }"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            <span>720p HD</span>
                            <span>{{ stats.resolutions?.['720p'] || 0 }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-200 dark:bg-white/10 overflow-hidden">
                            <div class="h-full bg-indigo-500 rounded-full" :style="{ width: `${Math.min(100, ((stats.resolutions?.['720p'] || 0) / Math.max(1, stats.total_movies + stats.total_episodes)) * 100)}%` }"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Codecs -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-white/10 space-y-4 shadow-sm">
                <div class="flex items-center gap-2.5 pb-3 border-b border-slate-200 dark:border-white/10">
                    <Cpu class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    <h3 class="font-bold text-base text-slate-900 dark:text-white">
                        {{ isRTL ? 'توزيع ترميزات الفيديو' : 'Video Codec Breakdown' }}
                    </h3>
                </div>

                <div class="space-y-3">
                    <div>
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            <span>HEVC / H.265 (High Efficiency)</span>
                            <span>{{ stats.codecs?.hevc || 0 }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-200 dark:bg-white/10 overflow-hidden">
                            <div class="h-full bg-emerald-500 rounded-full" :style="{ width: `${Math.min(100, ((stats.codecs?.hevc || 0) / Math.max(1, stats.total_movies + stats.total_episodes)) * 100)}%` }"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            <span>AVC / H.264 (Standard)</span>
                            <span>{{ stats.codecs?.h264 || 0 }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-200 dark:bg-white/10 overflow-hidden">
                            <div class="h-full bg-amber-500 rounded-full" :style="{ width: `${Math.min(100, ((stats.codecs?.h264 || 0) / Math.max(1, stats.total_movies + stats.total_episodes)) * 100)}%` }"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            <span>AV1 (Next-Gen)</span>
                            <span>{{ stats.codecs?.av1 || 0 }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-200 dark:bg-white/10 overflow-hidden">
                            <div class="h-full bg-violet-500 rounded-full" :style="{ width: `${Math.min(100, ((stats.codecs?.av1 || 0) / Math.max(1, stats.total_movies + stats.total_episodes)) * 100)}%` }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
