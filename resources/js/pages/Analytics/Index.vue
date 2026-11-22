<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import PageBanner from '@/components/common/PageBanner.vue';
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
    Zap,
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

const { isRTL } = useI18n();

const surroundPercent = computed(() => {
    const total = props.stats.audio?.total_audio_tracks || 1;
    return Math.round(((props.stats.audio?.surround_count || 0) / total) * 100);
});
</script>

<template>
    <AppLayout>
        <Head
            :title="
                isRTL
                    ? 'إحصائيات المكتبة والتخزين'
                    : 'Library Analytics & Storage Intelligence'
            "
        />

        <!-- Header -->
        <PageBanner
            :icon="BarChart3"
            :title="
                isRTL
                    ? 'إحصائيات المكتبة والتخزين الذكي'
                    : 'Storage Analytics & Intelligence'
            "
            :badge="stats.total_storage_formatted"
            :description="
                isRTL
                    ? 'رؤى تفصيلية حول سعة الأقراص، توزيع الترميزات وجودة الفيديو، وأكبر الملفات استهلاكاً للمساحة.'
                    : 'Deep operational insights on physical drive health, codec efficiency, resolution footprints, and storage heavyweights.'
            "
            class="mb-8"
        />

        <!-- 1. Physical Host Storage Drives Monitor -->
        <div
            v-if="stats.host_drives && stats.host_drives.length > 0"
            class="mb-8"
        >
            <div class="mb-3 flex items-center justify-between">
                <h2
                    class="text-muted-foreground flex items-center gap-2 text-sm font-bold tracking-wider uppercase"
                >
                    <HardDrive class="text-primary size-4" />
                    <span>{{
                        isRTL
                            ? 'حالة الأقراص ومساحات التخزين المضيفة'
                            : 'Physical Host Drives & Capacity'
                    }}</span>
                </h2>
                <span
                    class="text-muted-foreground font-mono text-xs tabular-nums"
                    >{{ stats.host_drives.length }}
                    {{ isRTL ? 'أقراص نشطة' : 'Drives Detected' }}</span
                >
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="drive in stats.host_drives"
                    :key="drive.drive"
                    class="cf-glass border-border/80 hover:border-primary/45 group relative overflow-hidden rounded-3xl border p-5 shadow-sm transition-all"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div
                                class="border-border/80 bg-card/70 text-primary flex size-10 items-center justify-center rounded-xl border font-mono text-sm font-black"
                            >
                                {{ drive.drive }}
                            </div>
                            <div>
                                <div
                                    class="text-foreground flex items-center gap-1.5 text-sm font-bold"
                                >
                                    <span>{{
                                        isRTL
                                            ? `قرص التخزين ${drive.drive}`
                                            : `Storage Volume (${drive.drive})`
                                    }}</span>
                                </div>
                                <div
                                    class="text-muted-foreground text-[11px] tabular-nums"
                                >
                                    {{ drive.free_formatted }}
                                    {{ isRTL ? 'متاح' : 'free' }} /
                                    {{ drive.total_formatted }}
                                    {{ isRTL ? 'إجمالي' : 'total' }}
                                </div>
                            </div>
                        </div>
                        <span
                            class="rounded-full border px-2 py-0.5 font-mono text-[10px] font-bold tracking-wider uppercase tabular-nums"
                            :class="{
                                'border-emerald-500/30 bg-emerald-500/15 text-emerald-400':
                                    drive.status === 'normal',
                                'border-amber-500/30 bg-amber-500/15 text-amber-400':
                                    drive.status === 'warning',
                                'border-destructive/30 bg-destructive/15 text-destructive animate-pulse':
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
                            class="bg-muted/60 h-2.5 overflow-hidden rounded-full p-0.5"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="{
                                    'bg-primary': drive.status === 'normal',
                                    'bg-gradient-to-r from-amber-500 to-orange-500':
                                        drive.status === 'warning',
                                    'bg-destructive':
                                        drive.status === 'critical',
                                }"
                                :style="{
                                    width: `${Math.min(100, drive.used_percent)}%`,
                                }"
                            ></div>
                        </div>
                        <div
                            class="text-muted-foreground flex justify-between font-mono text-[10px] tabular-nums"
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
                class="cf-glass border-border/80 hover:border-primary/45 flex flex-col justify-between rounded-3xl border p-6 shadow-sm transition-all"
            >
                <div class="mb-4 flex items-center justify-between">
                    <span
                        class="text-muted-foreground text-xs font-bold tracking-wider uppercase"
                        >{{ isRTL ? 'مكتبة الأفلام' : 'Total Movies' }}</span
                    >
                    <Film class="text-primary size-5" />
                </div>
                <div>
                    <div
                        class="text-foreground text-3xl font-black tabular-nums sm:text-4xl"
                    >
                        {{ stats.total_movies }}
                    </div>
                    <div
                        class="text-muted-foreground mt-1 flex items-center justify-between text-xs tabular-nums"
                    >
                        <span>{{ stats.movie_storage_formatted }}</span>
                        <span class="text-primary font-mono font-bold"
                            >{{ stats.avg_movie_formatted }} /
                            {{ isRTL ? 'فيلم' : 'film' }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- TV Series & Episodes -->
            <div
                class="cf-glass border-border/80 hover:border-primary/45 flex flex-col justify-between rounded-3xl border p-6 shadow-sm transition-all"
            >
                <div class="mb-4 flex items-center justify-between">
                    <span
                        class="text-muted-foreground text-xs font-bold tracking-wider uppercase"
                        >{{
                            isRTL ? 'المسلسلات والحلقات' : 'TV Series & Episodes'
                        }}</span
                    >
                    <Tv class="text-primary size-5" />
                </div>
                <div>
                    <div
                        class="text-foreground text-3xl font-black tabular-nums sm:text-4xl"
                    >
                        {{ stats.total_series }}
                        <span class="text-muted-foreground text-sm font-normal"
                            >({{ stats.total_episodes }}
                            {{ isRTL ? 'حلقة' : 'eps' }})</span
                        >
                    </div>
                    <div
                        class="text-muted-foreground mt-1 flex items-center justify-between text-xs tabular-nums"
                    >
                        <span>{{ stats.episode_storage_formatted }}</span>
                        <span class="text-primary font-mono font-bold"
                            >{{ stats.avg_episode_formatted }} /
                            {{ isRTL ? 'حلقة' : 'ep' }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Modern Codec Efficiency -->
            <div
                class="cf-glass border-border/80 hover:border-primary/45 flex flex-col justify-between rounded-3xl border p-6 shadow-sm transition-all"
            >
                <div class="mb-4 flex items-center justify-between">
                    <span
                        class="text-muted-foreground text-xs font-bold tracking-wider uppercase"
                        >{{
                            isRTL
                                ? 'كفاءة الترميز الحديث'
                                : 'Modern Codec Adoption'
                        }}</span
                    >
                    <Sparkles class="size-5 text-emerald-400" />
                </div>
                <div>
                    <div
                        class="text-foreground text-3xl font-black tabular-nums sm:text-4xl"
                    >
                        {{ stats.modern_adoption_percent }}%
                    </div>
                    <div
                        class="text-muted-foreground mt-1 flex items-center justify-between text-xs"
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
                class="cf-glass border-border/80 hover:border-primary/45 flex flex-col justify-between rounded-3xl border p-6 shadow-sm transition-all"
            >
                <div class="mb-4 flex items-center justify-between">
                    <span
                        class="text-muted-foreground text-xs font-bold tracking-wider uppercase"
                        >{{
                            isRTL ? 'إجمالي وقت المشاهدة' : 'Hours Watched'
                        }}</span
                    >
                    <Clock class="size-5 text-amber-400" />
                </div>
                <div>
                    <div
                        class="text-foreground text-3xl font-black tabular-nums sm:text-4xl"
                    >
                        {{ stats.total_watch_hours || 0 }}
                        <span class="text-muted-foreground text-sm font-normal"
                            >h</span
                        >
                    </div>
                    <div class="text-muted-foreground mt-1 text-xs">
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
            class="cf-glass border-border/80 mb-8 space-y-4 rounded-3xl border p-6 shadow-sm"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <PieChart class="text-primary size-4" />
                    <h3 class="text-foreground text-sm font-bold">
                        {{
                            isRTL
                                ? 'توزيع التخزين: الأفلام مقابل المسلسلات'
                                : 'Library Storage Allocation: Movies vs Series'
                        }}
                    </h3>
                </div>
                <div
                    class="text-muted-foreground font-mono text-xs font-bold tabular-nums"
                >
                    {{ isRTL ? 'الإجمالي:' : 'Total:' }}
                    {{ stats.total_storage_formatted }}
                </div>
            </div>

            <!-- Segmented Bar -->
            <div
                class="bg-muted/60 flex h-4 gap-0.5 overflow-hidden rounded-full p-0.5"
            >
                <div
                    class="bg-primary relative h-full rounded-s-full transition-all duration-500"
                    :style="{ width: `${stats.movie_storage_percent}%` }"
                    :title="`Movies: ${stats.movie_storage_formatted} (${stats.movie_storage_percent}%)`"
                ></div>
                <div
                    class="relative h-full rounded-e-full bg-indigo-500 transition-all duration-500"
                    :style="{ width: `${stats.episode_storage_percent}%` }"
                    :title="`Series: ${stats.episode_storage_formatted} (${stats.episode_storage_percent}%)`"
                ></div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-xs tabular-nums">
                <div class="flex items-center gap-2">
                    <div class="bg-primary size-3 rounded-md"></div>
                    <span class="text-foreground/85 font-semibold"
                        >{{ isRTL ? 'الأفلام' : 'Movies' }}:</span
                    >
                    <span class="text-primary font-mono font-bold"
                        >{{ stats.movie_storage_formatted }} ({{
                            stats.movie_storage_percent
                        }}%)</span
                    >
                </div>
                <div class="flex items-center justify-end gap-2">
                    <div class="size-3 rounded-md bg-indigo-500"></div>
                    <span class="text-foreground/85 font-semibold"
                        >{{ isRTL ? 'المسلسلات' : 'TV Series' }}:</span
                    >
                    <span class="font-mono font-bold text-indigo-400"
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
                class="cf-glass border-border/80 space-y-4 rounded-3xl border p-6 shadow-sm sm:p-7"
            >
                <div
                    class="border-border/70 flex items-center justify-between border-b pb-3"
                >
                    <div class="flex items-center gap-2.5">
                        <Video class="text-primary size-5" />
                        <h3 class="text-foreground text-base font-bold">
                            {{
                                isRTL
                                    ? 'استهلاك التخزين حسب دقة العرض'
                                    : 'Storage by Video Resolution'
                            }}
                        </h3>
                    </div>
                    <span class="text-muted-foreground font-mono text-xs">{{
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
                            class="text-foreground/90 flex items-center justify-between text-xs font-bold tabular-nums"
                        >
                            <span class="flex items-center gap-1.5">
                                <span
                                    class="size-2 rounded-full"
                                    :class="{
                                        'bg-primary': key === '4k',
                                        'bg-blue-500': key === '1080p',
                                        'bg-indigo-500': key === '720p',
                                        'bg-muted-foreground': key === 'sd',
                                    }"
                                ></span>
                                <span>{{ item.label }}</span>
                                <span
                                    class="text-muted-foreground font-mono text-[10px] font-normal"
                                    >({{ item.count }}
                                    {{ isRTL ? 'ملف' : 'files' }})</span
                                >
                            </span>
                            <span class="text-primary font-mono"
                                >{{ item.formatted }} ({{
                                    item.percent
                                }}%)</span
                            >
                        </div>
                        <div
                            class="bg-muted/60 h-2 overflow-hidden rounded-full"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="{
                                    'bg-primary': key === '4k',
                                    'bg-blue-500': key === '1080p',
                                    'bg-indigo-500': key === '720p',
                                    'bg-muted-foreground': key === 'sd',
                                }"
                                :style="{ width: `${item.percent}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Codecs & Efficiency Breakdown -->
            <div
                class="cf-glass border-border/80 space-y-4 rounded-3xl border p-6 shadow-sm sm:p-7"
            >
                <div
                    class="border-border/70 flex items-center justify-between border-b pb-3"
                >
                    <div class="flex items-center gap-2.5">
                        <Cpu class="text-primary size-5" />
                        <h3 class="text-foreground text-base font-bold">
                            {{
                                isRTL
                                    ? 'كفاءة الترميز وترميزات الفيديو'
                                    : 'Video Codec Footprint'
                            }}
                        </h3>
                    </div>
                    <span
                        class="font-mono text-xs font-bold text-emerald-400 tabular-nums"
                    >
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
                            class="text-foreground/90 flex items-center justify-between text-xs font-bold tabular-nums"
                        >
                            <span class="flex items-center gap-1.5">
                                <span
                                    class="size-2 rounded-full"
                                    :class="{
                                        'bg-emerald-400': key === 'hevc',
                                        'bg-amber-500': key === 'h264',
                                        'bg-violet-400': key === 'av1',
                                        'bg-muted-foreground': key === 'other',
                                    }"
                                ></span>
                                <span>{{ item.label }}</span>
                                <span
                                    class="text-muted-foreground font-mono text-[10px] font-normal"
                                    >({{ item.count }}
                                    {{ isRTL ? 'ملف' : 'files' }})</span
                                >
                            </span>
                            <span class="text-foreground font-mono"
                                >{{ item.formatted }} ({{
                                    item.percent
                                }}%)</span
                            >
                        </div>
                        <div
                            class="bg-muted/60 h-2 overflow-hidden rounded-full"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="{
                                    'bg-emerald-400': key === 'hevc',
                                    'bg-amber-500': key === 'h264',
                                    'bg-violet-400': key === 'av1',
                                    'bg-muted-foreground': key === 'other',
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
                    class="border-border/70 mt-4 flex items-center justify-between border-t pt-3 text-xs tabular-nums"
                >
                    <span
                        class="text-muted-foreground flex items-center gap-1.5"
                    >
                        <Zap class="size-3.5 text-amber-400" />
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
                class="cf-glass border-border/80 flex flex-col justify-between rounded-3xl border p-6 shadow-sm"
            >
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <span
                            class="text-muted-foreground text-xs font-bold tracking-wider uppercase"
                            >{{
                                isRTL
                                    ? 'أنظمة الصوت المحيطي'
                                    : 'Audio Soundstage'
                            }}</span
                        >
                        <Volume2 class="text-primary size-4" />
                    </div>
                    <div
                        class="text-foreground mb-2 text-2xl font-black tabular-nums"
                    >
                        {{ surroundPercent }}%
                        <span
                            class="text-muted-foreground text-xs font-normal"
                            >{{
                                isRTL
                                    ? 'صوت محيطي 5.1/7.1'
                                    : '5.1 / 7.1 Surround'
                            }}</span
                        >
                    </div>
                    <div
                        class="bg-muted/60 mb-3 h-2 overflow-hidden rounded-full"
                    >
                        <div
                            class="bg-primary h-full rounded-full"
                            :style="{ width: `${surroundPercent}%` }"
                        ></div>
                    </div>
                </div>
                <div
                    class="border-border/70 text-muted-foreground flex items-center justify-between border-t pt-2 text-xs tabular-nums"
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
                class="cf-glass border-border/80 flex flex-col justify-between rounded-3xl border p-6 shadow-sm"
            >
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <span
                            class="text-muted-foreground text-xs font-bold tracking-wider uppercase"
                            >{{
                                isRTL
                                    ? 'نمو التخزين الأخير'
                                    : 'Recent Storage Inflow'
                            }}</span
                        >
                        <TrendingUp class="size-4 text-emerald-400" />
                    </div>
                    <div
                        class="text-foreground mb-1 text-2xl font-black tabular-nums"
                    >
                        {{ stats.recent_inflow?.days_7_formatted || '0 GB' }}
                    </div>
                    <p class="text-muted-foreground text-xs">
                        {{
                            isRTL
                                ? 'تمت إضافتها في آخر 7 أيام'
                                : 'Added during the last 7 days'
                        }}
                    </p>
                </div>
                <div
                    class="border-border/70 text-muted-foreground flex items-center justify-between border-t pt-2 text-xs tabular-nums"
                >
                    <span>{{ isRTL ? 'خلال 30 يوماً:' : 'Last 30 days:' }}</span>
                    <span class="font-mono font-bold text-emerald-400">{{
                        stats.recent_inflow?.days_30_formatted || '0 GB'
                    }}</span>
                </div>
            </div>

            <!-- Top Genres Overview -->
            <div
                class="cf-glass border-border/80 flex flex-col justify-between rounded-3xl border p-6 shadow-sm"
            >
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <span
                            class="text-muted-foreground text-xs font-bold tracking-wider uppercase"
                            >{{
                                isRTL ? 'أبرز التصنيفات' : 'Top Library Genres'
                            }}</span
                        >
                        <Layers class="text-primary size-4" />
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
                            class="border-border/80 bg-card/70 text-foreground/90 rounded-xl border px-2.5 py-1 text-xs font-semibold tabular-nums"
                        >
                            {{
                                isRTL
                                    ? genre.name_ar || genre.name_en
                                    : genre.name_en
                            }}
                            <span
                                class="text-primary font-mono text-[10px] font-bold"
                                >({{ genre.count }})</span
                            >
                        </span>
                    </div>
                </div>
                <div
                    class="border-border/70 text-muted-foreground border-t pt-2 text-[11px]"
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
            class="cf-glass border-border/80 mb-8 space-y-4 rounded-3xl border p-6 shadow-sm sm:p-8"
        >
            <div
                class="border-border/70 flex items-center justify-between border-b pb-3"
            >
                <div class="flex items-center gap-2.5">
                    <Disc3 class="text-primary size-5" />
                    <div>
                        <h3 class="text-foreground text-base font-bold">
                            {{
                                isRTL
                                    ? 'أكبر الملفات استهلاكاً للمساحة (Storage Heavyweights)'
                                    : 'Storage Heavyweights (Largest Files on Disk)'
                            }}
                        </h3>
                        <p class="text-muted-foreground text-xs">
                            {{
                                isRTL
                                    ? 'العناوين التي تستهلك أكبر قدر من السعة التخزينية في الأقراص.'
                                    : 'The individual media titles taking up the largest storage footprint.'
                            }}
                        </p>
                    </div>
                </div>
                <span
                    class="text-primary font-mono text-xs font-bold tabular-nums"
                    >Top 10</span
                >
            </div>

            <!-- Table of Heavyweights -->
            <div class="cf-table-wrapper">
                <table class="cf-table w-full text-start text-xs">
                    <thead>
                        <tr
                            class="border-border/70 text-muted-foreground border-b font-mono text-[10px] uppercase"
                        >
                            <th class="pb-3 text-start">
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
                            <th class="pb-3 text-end">
                                {{ isRTL ? 'الحجم' : 'File Size' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-border/40 divide-y tabular-nums">
                        <tr
                            v-for="(item, idx) in stats.heavyweights"
                            :key="item.id + '-' + item.type"
                            class="hover:bg-muted/35 transition-colors"
                        >
                            <td
                                class="text-foreground flex items-center gap-2 py-3 text-start font-semibold"
                            >
                                <span
                                    class="bg-primary/15 text-primary flex size-5 shrink-0 items-center justify-center rounded-full font-mono text-[10px] font-bold"
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
                                            ? 'bg-primary/15 text-primary'
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
                                class="text-foreground/85 py-3 text-center font-mono"
                            >
                                {{ item.resolution }}
                            </td>
                            <td
                                class="text-muted-foreground py-3 text-center font-mono"
                            >
                                {{ item.video_codec }}
                            </td>
                            <td
                                class="text-primary py-3 text-end font-mono font-bold"
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
