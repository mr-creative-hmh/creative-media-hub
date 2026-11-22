<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    BarChart3,
    HardDrive,
    Clock,
    Film,
    Tv,
    Cpu,
    Video,
    Sparkles,
    Volume2,
    TrendingUp,
    Layers,
    ShieldCheck,
    AlertTriangle,
    Zap,
    ArrowUpRight,
    PieChart,
    Disc3,
} from '@lucide/vue';

interface HostDrive {
    drive: string;
    total_formatted: string;
    free_formatted: string;
    used_formatted: string;
    total_bytes: number;
    free_bytes: number;
    used_bytes: number;
    used_percent: number;
    status: 'normal' | 'warning' | 'critical';
}

interface BreakdownItem {
    label: string;
    badge?: string;
    bytes: number;
    count: number;
    formatted: string;
    percent: number;
}

interface HeavyweightItem {
    id: number;
    title: string;
    title_ar?: string;
    type: 'movie' | 'episode';
    size_bytes: number;
    size_formatted: string;
    resolution: string;
    video_codec: string;
    audio_codec: string;
}

const props = defineProps<{
    stats: {
        total_movies: number;
        total_series: number;
        total_episodes: number;
        total_storage_bytes: number;
        total_storage_formatted: string;
        movie_storage_bytes: number;
        movie_storage_formatted: string;
        movie_storage_percent: number;
        episode_storage_bytes: number;
        episode_storage_formatted: string;
        episode_storage_percent: number;
        avg_movie_formatted: string;
        avg_episode_formatted: string;
        avg_series_formatted: string;
        total_watch_hours: number;
        host_drives: HostDrive[];
        resolutions: Record<string, BreakdownItem>;
        codecs: Record<string, BreakdownItem>;
        modern_adoption_percent: number;
        potential_savings_formatted: string;
        audio: {
            surround_count: number;
            stereo_count: number;
            total_audio_tracks: number;
        };
        heavyweights: HeavyweightItem[];
        recent_inflow: {
            days_7_formatted: string;
            days_30_formatted: string;
        };
        top_genres: any[];
    };
}>();

const { t, isRTL } = useI18n();

const surroundPercent = computed(() => {
    const total = props.stats.audio?.total_audio_tracks || 1;
    return Math.round(((props.stats.audio?.surround_count || 0) / total) * 100);
});
</script>

<template>
    <AppLayout v-slot="{ play }">
        <Head
            :title="
                isRTL
                    ? 'إحصائيات المكتبة والتخزين'
                    : 'Library Analytics & Storage Intelligence'
            "
        />
        <!-- Header -->
        <div class="mb-8">
            <div class="mb-2 flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl border border-cyan-500/30 bg-gradient-to-tr from-cyan-500/20 to-blue-500/20 text-cyan-400 shadow-lg shadow-cyan-500/10"
                >
                    <BarChart3 class="h-6 w-6" />
                </div>
                <div>
                    <h1
                        class="flex items-center gap-2.5 text-2xl font-extrabold text-slate-900 sm:text-3xl dark:text-white"
                    >
                        <span>{{
                            isRTL
                                ? 'إحصائيات المكتبة والتخزين الذكي'
                                : 'Storage Analytics & Intelligence'
                        }}</span>
                        <span
                            class="rounded-full border border-cyan-500/30 bg-cyan-500/15 px-2.5 py-0.5 font-mono text-xs font-bold text-cyan-500 dark:text-cyan-300"
                        >
                            {{ stats.total_storage_formatted }}
                        </span>
                    </h1>
                    <p
                        class="mt-0.5 text-xs text-slate-600 sm:text-sm dark:text-slate-400"
                    >
                        {{
                            isRTL
                                ? 'رؤى تفصيلية حول سعة الأقراص، توزيع الترميزات وجودة الفيديو، وأكبر الملفات استهلاكاً للمساحة.'
                                : 'Deep operational insights on physical drive health, codec efficiency, resolution footprints, and storage heavyweights.'
                        }}
                    </p>
                </div>
            </div>
        </div>

        <!-- 1. Physical Host Storage Drives Monitor -->
        <div
            v-if="stats.host_drives && stats.host_drives.length > 0"
            class="mb-8"
        >
            <div class="mb-3 flex items-center justify-between">
                <h2
                    class="flex items-center gap-2 text-sm font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                >
                    <HardDrive class="h-4 w-4 text-cyan-500" />
                    <span>{{
                        isRTL
                            ? 'حالة الأقراص ومساحات التخزين المضيفة'
                            : 'Physical Host Drives & Capacity'
                    }}</span>
                </h2>
                <span class="font-mono text-xs text-slate-400"
                    >{{ stats.host_drives.length }}
                    {{ isRTL ? 'أقراص نشطة' : 'Drives Detected' }}</span
                >
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="drive in stats.host_drives"
                    :key="drive.drive"
                    class="glass-card group relative overflow-hidden rounded-3xl border border-slate-200 p-5 shadow-sm transition-all hover:border-cyan-500/40 dark:border-white/10"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 font-mono text-sm font-black text-slate-800 dark:border-white/10 dark:bg-white/5 dark:text-cyan-400"
                            >
                                {{ drive.drive }}
                            </div>
                            <div>
                                <div
                                    class="flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-white"
                                >
                                    <span>{{
                                        isRTL
                                            ? `قرص التخزين ${drive.drive}`
                                            : `Storage Volume (${drive.drive})`
                                    }}</span>
                                </div>
                                <div
                                    class="text-[11px] text-slate-500 dark:text-slate-400"
                                >
                                    {{ drive.free_formatted }}
                                    {{ isRTL ? 'متاح' : 'free' }} /
                                    {{ drive.total_formatted }}
                                    {{ isRTL ? 'إجمالي' : 'total' }}
                                </div>
                            </div>
                        </div>
                        <span
                            class="rounded-full border px-2 py-0.5 font-mono text-[10px] font-bold tracking-wider uppercase"
                            :class="{
                                'border-emerald-500/30 bg-emerald-500/15 text-emerald-600 dark:text-emerald-400':
                                    drive.status === 'normal',
                                'border-amber-500/30 bg-amber-500/15 text-amber-600 dark:text-amber-400':
                                    drive.status === 'warning',
                                'animate-pulse border-red-500/30 bg-red-500/15 text-red-600 dark:text-red-400':
                                    drive.status === 'critical',
                            }"
                        >
                            {{ drive.used_percent }}%
                            {{ isRTL ? 'مستخدم' : 'used' }}
                        </span>
                    </div>

                    <!-- Progress Bar -->
                    <div class="space-y-1">
                        <div
                            class="h-2.5 overflow-hidden rounded-full bg-slate-100 p-0.5 dark:bg-white/10"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="{
                                    'bg-gradient-to-r from-emerald-500 to-cyan-500':
                                        drive.status === 'normal',
                                    'bg-gradient-to-r from-amber-500 to-orange-500':
                                        drive.status === 'warning',
                                    'bg-gradient-to-r from-red-500 to-rose-600':
                                        drive.status === 'critical',
                                }"
                                :style="{
                                    width: `${Math.min(100, drive.used_percent)}%`,
                                }"
                            ></div>
                        </div>
                        <div
                            class="flex justify-between font-mono text-[10px] text-slate-400"
                        >
                            <span
                                >{{ drive.used_formatted }}
                                {{ isRTL ? 'مستهلك' : 'used' }}</span
                            >
                            <span
                                >{{ drive.free_formatted }}
                                {{ isRTL ? 'متبقي' : 'remaining' }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Core Metrics Bento Grid -->
        <div
            class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-4"
        >
            <!-- Total Movies -->
            <div
                class="glass-card flex flex-col justify-between rounded-3xl border border-slate-200 p-6 shadow-sm dark:border-white/10"
            >
                <div class="mb-4 flex items-center justify-between">
                    <span
                        class="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                        >{{ isRTL ? 'مكتبة الأفلام' : 'Total Movies' }}</span
                    >
                    <Film class="h-5 w-5 text-cyan-600 dark:text-cyan-400" />
                </div>
                <div>
                    <div
                        class="text-3xl font-black text-slate-900 sm:text-4xl dark:text-white"
                    >
                        {{ stats.total_movies }}
                    </div>
                    <div
                        class="mt-1 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400"
                    >
                        <span>{{ stats.movie_storage_formatted }}</span>
                        <span class="font-mono font-bold text-cyan-500"
                            >{{ stats.avg_movie_formatted }} /
                            {{ isRTL ? 'فيلم' : 'film' }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- TV Series & Episodes -->
            <div
                class="glass-card flex flex-col justify-between rounded-3xl border border-slate-200 p-6 shadow-sm dark:border-white/10"
            >
                <div class="mb-4 flex items-center justify-between">
                    <span
                        class="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                        >{{
                            isRTL ? 'المسلسلات والحلقات' : 'TV Series & Episodes'
                        }}</span
                    >
                    <Tv class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div>
                    <div
                        class="text-3xl font-black text-slate-900 sm:text-4xl dark:text-white"
                    >
                        {{ stats.total_series }}
                        <span class="text-sm font-normal text-slate-400"
                            >({{ stats.total_episodes }}
                            {{ isRTL ? 'حلقة' : 'eps' }})</span
                        >
                    </div>
                    <div
                        class="mt-1 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400"
                    >
                        <span>{{ stats.episode_storage_formatted }}</span>
                        <span class="font-mono font-bold text-indigo-400"
                            >{{ stats.avg_episode_formatted }} /
                            {{ isRTL ? 'حلقة' : 'ep' }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Modern Codec Efficiency -->
            <div
                class="glass-card flex flex-col justify-between rounded-3xl border border-slate-200 p-6 shadow-sm dark:border-white/10"
            >
                <div class="mb-4 flex items-center justify-between">
                    <span
                        class="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                        >{{
                            isRTL
                                ? 'كفاءة الترميز الحديث'
                                : 'Modern Codec Adoption'
                        }}</span
                    >
                    <Sparkles
                        class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
                    />
                </div>
                <div>
                    <div
                        class="text-3xl font-black text-slate-900 sm:text-4xl dark:text-white"
                    >
                        {{ stats.modern_adoption_percent }}%
                    </div>
                    <div
                        class="mt-1 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400"
                    >
                        <span>HEVC + AV1</span>
                        <span class="font-mono font-bold text-emerald-400">{{
                            isRTL ? 'كفاءة عالية' : 'High Efficiency'
                        }}</span>
                    </div>
                </div>
            </div>

            <!-- Watch Time & Activity -->
            <div
                class="glass-card flex flex-col justify-between rounded-3xl border border-slate-200 p-6 shadow-sm dark:border-white/10"
            >
                <div class="mb-4 flex items-center justify-between">
                    <span
                        class="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                        >{{
                            isRTL ? 'إجمالي وقت المشاهدة' : 'Hours Watched'
                        }}</span
                    >
                    <Clock class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <div
                        class="text-3xl font-black text-slate-900 sm:text-4xl dark:text-white"
                    >
                        {{ stats.total_watch_hours || 0 }}
                        <span class="text-sm font-normal text-slate-400"
                            >h</span
                        >
                    </div>
                    <div
                        class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{
                            isRTL
                                ? 'تتبع فوري لسجل المشاهدة'
                                : 'Continuous history tracking'
                        }}
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Movies vs Series Storage Allocation Bar -->
        <div
            class="glass-panel mb-8 space-y-4 rounded-3xl border border-slate-200 p-6 shadow-sm dark:border-white/10"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <PieChart class="h-4 w-4 text-cyan-500" />
                    <h3
                        class="text-sm font-bold text-slate-900 dark:text-white"
                    >
                        {{
                            isRTL
                                ? 'توزيع التخزين: الأفلام مقابل المسلسلات'
                                : 'Library Storage Allocation: Movies vs Series'
                        }}
                    </h3>
                </div>
                <div class="font-mono text-xs font-bold text-slate-400">
                    {{ isRTL ? 'الإجمالي:' : 'Total:' }}
                    {{ stats.total_storage_formatted }}
                </div>
            </div>

            <!-- Segmented Bar -->
            <div
                class="flex h-4 gap-0.5 overflow-hidden rounded-full bg-slate-100 p-0.5 dark:bg-white/10"
            >
                <div
                    class="group/bar relative h-full rounded-l-full bg-cyan-500 transition-all duration-500"
                    :style="{ width: `${stats.movie_storage_percent}%` }"
                    :title="`Movies: ${stats.movie_storage_formatted} (${stats.movie_storage_percent}%)`"
                ></div>
                <div
                    class="group/bar relative h-full rounded-r-full bg-indigo-500 transition-all duration-500"
                    :style="{ width: `${stats.episode_storage_percent}%` }"
                    :title="`Series: ${stats.episode_storage_formatted} (${stats.episode_storage_percent}%)`"
                ></div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-xs">
                <div class="flex items-center gap-2">
                    <div class="h-3 w-3 rounded-md bg-cyan-500"></div>
                    <span
                        class="font-semibold text-slate-700 dark:text-slate-300"
                        >{{ isRTL ? 'الأفلام' : 'Movies' }}:</span
                    >
                    <span
                        class="font-mono font-bold text-cyan-600 dark:text-cyan-400"
                        >{{ stats.movie_storage_formatted }} ({{
                            stats.movie_storage_percent
                        }}%)</span
                    >
                </div>
                <div class="flex items-center justify-end gap-2">
                    <div class="h-3 w-3 rounded-md bg-indigo-500"></div>
                    <span
                        class="font-semibold text-slate-700 dark:text-slate-300"
                        >{{ isRTL ? 'المسلسلات' : 'TV Series' }}:</span
                    >
                    <span
                        class="font-mono font-bold text-indigo-600 dark:text-indigo-400"
                        >{{ stats.episode_storage_formatted }} ({{
                            stats.episode_storage_percent
                        }}%)</span
                    >
                </div>
            </div>
        </div>

        <!-- 4. Quality & Codec Distribution Grid -->
        <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Resolutions Storage Footprint -->
            <div
                class="glass-panel space-y-4 rounded-3xl border border-slate-200 p-6 shadow-sm sm:p-7 dark:border-white/10"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-white/10"
                >
                    <div class="flex items-center gap-2.5">
                        <Video
                            class="h-5 w-5 text-cyan-600 dark:text-cyan-400"
                        />
                        <h3
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            {{
                                isRTL
                                    ? 'استهلاك التخزين حسب دقة العرض'
                                    : 'Storage by Video Resolution'
                            }}
                        </h3>
                    </div>
                    <span class="font-mono text-xs text-slate-400">{{
                        isRTL ? 'جيجابايت وعناصر' : 'GB & Count'
                    }}</span>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="(item, key) in stats.resolutions"
                        :key="key"
                        class="space-y-1.5"
                    >
                        <div
                            class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300"
                        >
                            <span class="flex items-center gap-1.5">
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :class="{
                                        'bg-cyan-400': key === '4k',
                                        'bg-blue-500': key === '1080p',
                                        'bg-indigo-500': key === '720p',
                                        'bg-slate-400': key === 'sd',
                                    }"
                                ></span>
                                <span>{{ item.label }}</span>
                                <span
                                    class="font-mono text-[10px] font-normal text-slate-400"
                                    >({{ item.count }}
                                    {{ isRTL ? 'ملف' : 'files' }})</span
                                >
                            </span>
                            <span
                                class="font-mono text-cyan-500 dark:text-cyan-300"
                                >{{ item.formatted }} ({{
                                    item.percent
                                }}%)</span
                            >
                        </div>
                        <div
                            class="h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="{
                                    'bg-cyan-400': key === '4k',
                                    'bg-blue-500': key === '1080p',
                                    'bg-indigo-500': key === '720p',
                                    'bg-slate-400': key === 'sd',
                                }"
                                :style="{ width: `${item.percent}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Codecs & Efficiency Breakdown -->
            <div
                class="glass-panel space-y-4 rounded-3xl border border-slate-200 p-6 shadow-sm sm:p-7 dark:border-white/10"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-white/10"
                >
                    <div class="flex items-center gap-2.5">
                        <Cpu
                            class="h-5 w-5 text-indigo-600 dark:text-indigo-400"
                        />
                        <h3
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            {{
                                isRTL
                                    ? 'كفاءة الترميز وترميزات الفيديو'
                                    : 'Video Codec Footprint'
                            }}
                        </h3>
                    </div>
                    <span class="font-mono text-xs font-bold text-emerald-400">
                        {{ stats.modern_adoption_percent }}%
                        {{ isRTL ? 'حديث' : 'Modern' }}
                    </span>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="(item, key) in stats.codecs"
                        :key="key"
                        class="space-y-1.5"
                    >
                        <div
                            class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300"
                        >
                            <span class="flex items-center gap-1.5">
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :class="{
                                        'bg-emerald-400': key === 'hevc',
                                        'bg-amber-500': key === 'h264',
                                        'bg-violet-400': key === 'av1',
                                        'bg-slate-400': key === 'other',
                                    }"
                                ></span>
                                <span>{{ item.label }}</span>
                                <span
                                    class="font-mono text-[10px] font-normal text-slate-400"
                                    >({{ item.count }}
                                    {{ isRTL ? 'ملف' : 'files' }})</span
                                >
                            </span>
                            <span
                                class="font-mono text-slate-800 dark:text-slate-200"
                                >{{ item.formatted }} ({{
                                    item.percent
                                }}%)</span
                            >
                        </div>
                        <div
                            class="h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="{
                                    'bg-emerald-400': key === 'hevc',
                                    'bg-amber-500': key === 'h264',
                                    'bg-violet-400': key === 'av1',
                                    'bg-slate-400': key === 'other',
                                }"
                                :style="{ width: `${item.percent}%` }"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- Potential Savings Note -->
                <div
                    v-if="
                        stats.potential_savings_formatted &&
                        stats.potential_savings_formatted !== '0 GB'
                    "
                    class="mt-4 flex items-center justify-between border-t border-slate-200 pt-3 text-xs dark:border-white/10"
                >
                    <span
                        class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400"
                    >
                        <Zap class="h-3.5 w-3.5 text-amber-400" />
                        <span>{{
                            isRTL
                                ? 'توفير تقديري عند الترقية إلى HEVC:'
                                : 'Potential reclaimable space via HEVC upgrade:'
                        }}</span>
                    </span>
                    <span class="font-mono font-bold text-emerald-400"
                        >~{{ stats.potential_savings_formatted }}</span
                    >
                </div>
            </div>
        </div>

        <!-- 5. Sound Systems & Surround Sound Distribution -->
        <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-3">
            <!-- Surround vs Stereo -->
            <div
                class="glass-panel flex flex-col justify-between rounded-3xl border border-slate-200 p-6 shadow-sm dark:border-white/10"
            >
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <span
                            class="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >{{
                                isRTL
                                    ? 'أنظمة الصوت المحيطي'
                                    : 'Audio Soundstage'
                            }}</span
                        >
                        <Volume2 class="h-4 w-4 text-cyan-400" />
                    </div>
                    <div
                        class="mb-2 text-2xl font-black text-slate-900 dark:text-white"
                    >
                        {{ surroundPercent }}%
                        <span class="text-xs font-normal text-slate-400">{{
                            isRTL ? 'صوت محيطي 5.1/7.1' : '5.1 / 7.1 Surround'
                        }}</span>
                    </div>
                    <div
                        class="mb-3 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10"
                    >
                        <div
                            class="h-full rounded-full bg-cyan-500"
                            :style="{ width: `${surroundPercent}%` }"
                        ></div>
                    </div>
                </div>
                <div
                    class="flex items-center justify-between border-t border-slate-200 pt-2 text-xs text-slate-500 dark:border-white/10 dark:text-slate-400"
                >
                    <span
                        >{{ stats.audio?.surround_count || 0 }}
                        {{ isRTL ? 'محيطي' : 'Surround' }}</span
                    >
                    <span
                        >{{ stats.audio?.stereo_count || 0 }}
                        {{ isRTL ? 'ستيريو' : 'Stereo' }}</span
                    >
                </div>
            </div>

            <!-- Recent Additions Inflow -->
            <div
                class="glass-panel flex flex-col justify-between rounded-3xl border border-slate-200 p-6 shadow-sm dark:border-white/10"
            >
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <span
                            class="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >{{
                                isRTL
                                    ? 'نمو التخزين الأخير'
                                    : 'Recent Storage Inflow'
                            }}</span
                        >
                        <TrendingUp class="h-4 w-4 text-emerald-400" />
                    </div>
                    <div
                        class="mb-1 text-2xl font-black text-slate-900 dark:text-white"
                    >
                        {{ stats.recent_inflow?.days_7_formatted || '0 GB' }}
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{
                            isRTL
                                ? 'تمت إضافتها في آخر 7 أيام'
                                : 'Added during the last 7 days'
                        }}
                    </p>
                </div>
                <div
                    class="flex items-center justify-between border-t border-slate-200 pt-2 text-xs text-slate-500 dark:border-white/10 dark:text-slate-400"
                >
                    <span>{{ isRTL ? 'خلال 30 يوماً:' : 'Last 30 days:' }}</span>
                    <span class="font-mono font-bold text-emerald-400">{{
                        stats.recent_inflow?.days_30_formatted || '0 GB'
                    }}</span>
                </div>
            </div>

            <!-- Top Genres Overview -->
            <div
                class="glass-panel flex flex-col justify-between rounded-3xl border border-slate-200 p-6 shadow-sm dark:border-white/10"
            >
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <span
                            class="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >{{
                                isRTL ? 'أبرز التصنيفات' : 'Top Library Genres'
                            }}</span
                        >
                        <Layers class="h-4 w-4 text-indigo-400" />
                    </div>
                    <div
                        class="flex max-h-24 flex-wrap gap-1.5 overflow-y-auto"
                    >
                        <span
                            v-for="genre in (stats.top_genres || []).slice(
                                0,
                                6,
                            )"
                            :key="genre.name_en"
                            class="rounded-xl border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                        >
                            {{
                                isRTL
                                    ? genre.name_ar || genre.name_en
                                    : genre.name_en
                            }}
                            <span
                                class="font-mono text-[10px] font-bold text-cyan-500"
                                >({{ genre.count }})</span
                            >
                        </span>
                    </div>
                </div>
                <div
                    class="border-t border-slate-200 pt-2 text-[11px] text-slate-400 dark:border-white/10"
                >
                    {{
                        isRTL
                            ? 'مرتبة حسب حجم المحتوى'
                            : 'Ranked by title abundance'
                    }}
                </div>
            </div>
        </div>

        <!-- 6. Storage Heavyweights (Top 10 Largest Items on Disk) -->
        <div
            v-if="stats.heavyweights && stats.heavyweights.length > 0"
            class="glass-panel mb-8 space-y-4 rounded-3xl border border-slate-200 p-6 shadow-sm sm:p-8 dark:border-white/10"
        >
            <div
                class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-white/10"
            >
                <div class="flex items-center gap-2.5">
                    <Disc3 class="h-5 w-5 text-cyan-500" />
                    <div>
                        <h3
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            {{
                                isRTL
                                    ? 'أكبر الملفات استهلاكاً للمساحة (Storage Heavyweights)'
                                    : 'Storage Heavyweights (Largest Files on Disk)'
                            }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{
                                isRTL
                                    ? 'العناوين التي تستهلك أكبر قدر من السعة التخزينية في الأقراص.'
                                    : 'The individual media titles taking up the largest storage footprint.'
                            }}
                        </p>
                    </div>
                </div>
                <span class="font-mono text-xs font-bold text-cyan-400"
                    >Top 10</span
                >
            </div>

            <!-- Table of Heavyweights -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr
                            class="border-b border-slate-200 font-mono text-[10px] text-slate-400 uppercase dark:border-white/10"
                        >
                            <th class="pb-3">
                                {{ isRTL ? 'العنوان' : 'Title' }}
                            </th>
                            <th class="pb-3 text-center">
                                {{ isRTL ? 'النوع' : 'Type' }}
                            </th>
                            <th class="pb-3 text-center">
                                {{ isRTL ? 'الدقة' : 'Resolution' }}
                            </th>
                            <th class="pb-3 text-center">
                                {{ isRTL ? 'الترميز' : 'Codec' }}
                            </th>
                            <th class="pb-3 text-right">
                                {{ isRTL ? 'الحجم' : 'File Size' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-white/5"
                    >
                        <tr
                            v-for="(item, idx) in stats.heavyweights"
                            :key="item.id + '-' + item.type"
                            class="transition-colors hover:bg-slate-50 dark:hover:bg-white/5"
                        >
                            <td
                                class="flex items-center gap-2 py-3 font-semibold text-slate-900 dark:text-white"
                            >
                                <span
                                    class="flex h-5 w-5 items-center justify-center rounded-full bg-cyan-500/10 font-mono text-[10px] font-bold text-cyan-500"
                                >
                                    {{ idx + 1 }}
                                </span>
                                <span class="max-w-xs truncate sm:max-w-md">
                                    {{
                                        isRTL
                                            ? item.title_ar || item.title
                                            : item.title
                                    }}
                                </span>
                            </td>
                            <td class="py-3 text-center">
                                <span
                                    class="rounded-full px-2 py-0.5 font-mono text-[10px] font-bold uppercase"
                                    :class="
                                        item.type === 'movie'
                                            ? 'bg-cyan-500/15 text-cyan-400'
                                            : 'bg-indigo-500/15 text-indigo-400'
                                    "
                                >
                                    {{
                                        item.type === 'movie'
                                            ? isRTL
                                                ? 'فيلم'
                                                : 'Movie'
                                            : isRTL
                                              ? 'حلقة'
                                              : 'Episode'
                                    }}
                                </span>
                            </td>
                            <td
                                class="py-3 text-center font-mono text-slate-600 dark:text-slate-300"
                            >
                                {{ item.resolution }}
                            </td>
                            <td
                                class="py-3 text-center font-mono text-slate-500 dark:text-slate-400"
                            >
                                {{ item.video_codec }}
                            </td>
                            <td
                                class="py-3 text-right font-mono font-bold text-cyan-500 dark:text-cyan-300"
                            >
                                {{ item.size_formatted }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
