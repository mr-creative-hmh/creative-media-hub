<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useScanner } from '@/composables/useScanner';
import { useDownloader } from '@/composables/useDownloader';
import { useActivityCenter } from '@/composables/useActivityCenter';
import {
    LayoutDashboard,
    Film,
    History,
    Layers,
    Tv,
    ScanLine,
    FolderSync,
    Subtitles,
    BarChart3,
    DownloadCloud,
    Settings,
    Sparkles,
    Compass,
    RefreshCw,
    Pause,
    Play,
    HardDrive,
    BookOpen,
    Activity,
} from 'lucide-vue-next';

const { t, isRTL } = useI18n();
const { scanStatus, isScanning, isPaused, openScanModal } = useScanner();
const { activeDownloads } = useDownloader();
const { isAnyRunning, isAnyPaused, activeJobsCount, openActivityCenter } =
    useActivityCenter();

const activeDownloadsCount = computed(() => activeDownloads.value.length);

const navItems = [
    {
        nameKey: 'nav.dashboard',
        href: '/',
        icon: LayoutDashboard,
        pattern: '^/$|^/dashboard',
    },
    { nameKey: 'nav.movies', href: '/movies', icon: Film, pattern: '^/movies' },
    {
        nameKey: 'nav.collections',
        href: '/collections',
        icon: Layers,
        pattern: '^/collections',
    },
    { nameKey: 'nav.series', href: '/series', icon: Tv, pattern: '^/series' },
    {
        nameKey: 'nav.watch_history',
        href: '/watch-history',
        icon: History,
        pattern: '^/watch-history',
    },
    {
        nameKey: 'nav.metadata',
        href: '/metadata',
        icon: Sparkles,
        pattern: '^/metadata',
    },
    {
        nameKey: 'nav.scanner',
        href: '/scanner',
        icon: ScanLine,
        pattern: '^/scanner',
    },
    {
        nameKey: 'nav.organizer',
        href: '/organizer',
        icon: FolderSync,
        pattern: '^/organizer',
    },
    { nameKey: 'nav.scout', href: '/scout', icon: Compass, pattern: '^/scout' },
    {
        nameKey: 'nav.subtitles',
        href: '/subtitles',
        icon: Subtitles,
        pattern: '^/subtitles',
    },
    {
        nameKey: 'nav.analytics',
        href: '/analytics',
        icon: BarChart3,
        pattern: '^/analytics',
    },
    {
        nameKey: 'nav.downloads',
        href: '/downloads',
        icon: DownloadCloud,
        pattern: '^/downloads',
    },
    {
        nameKey: 'nav.docs',
        href: '/docs',
        icon: BookOpen,
        pattern: '^/docs|^/guide',
    },
    {
        nameKey: 'nav.settings',
        href: '/settings',
        icon: Settings,
        pattern: '^/settings',
    },
];

const isActive = (pattern: string) => {
    if (typeof window !== 'undefined') {
        return new RegExp(pattern).test(window.location.pathname);
    }
    return false;
};
</script>

<template>
    <aside
        class="glass-panel hidden min-h-[calc(100vh-61px)] w-64 shrink-0 flex-col space-y-4 border-r border-white/10 bg-[#0A0D14]/80 p-4 transition-all lg:flex"
    >
        <!-- Top Fast Scan Trigger -->
        <div>
            <button
                @click="openScanModal"
                class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-cyan-400 via-cyan-500 to-blue-500 px-4 py-2.5 text-xs font-black text-slate-950 shadow-lg shadow-cyan-500/20 transition-all hover:from-cyan-300 hover:to-blue-400 active:scale-[0.98]"
            >
                <RefreshCw
                    v-if="isScanning && !isPaused"
                    class="h-4 w-4 animate-spin text-slate-950"
                />
                <Pause
                    v-else-if="isPaused"
                    class="h-4 w-4 fill-current text-slate-950"
                />
                <HardDrive v-else class="h-4 w-4 text-slate-950" />
                <span>
                    {{
                        isScanning
                            ? isPaused
                                ? t('sidebar.scan_paused')
                                : `${t('sidebar.scanning')} ${scanStatus.progress_percent || 0}%`
                            : t('sidebar.scan_media_library')
                    }}
                </span>
            </button>
        </div>

        <!-- Navigation Links (Instant SPA via Inertia Link) -->
        <div class="space-y-1">
            <Link
                v-for="item in navItems"
                :key="item.href"
                :href="item.href"
                class="group flex cursor-pointer items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-semibold transition-all"
                :class="
                    isActive(item.pattern)
                        ? 'border border-cyan-500/30 bg-cyan-500/20 font-bold text-cyan-400 shadow-sm'
                        : 'border border-transparent text-slate-400 hover:bg-white/[0.05] hover:text-white'
                "
            >
                <div class="flex items-center gap-3.5">
                    <component
                        :is="item.icon"
                        class="h-4.5 w-4.5 transition-transform group-hover:scale-110"
                        :class="
                            isActive(item.pattern)
                                ? 'text-cyan-400'
                                : 'text-slate-400 group-hover:text-slate-200'
                        "
                    />
                    <span>{{ t(item.nameKey) }}</span>
                </div>

                <span
                    v-if="
                        item.href === '/downloads' && activeDownloadsCount > 0
                    "
                    class="animate-pulse rounded-full bg-cyan-500 px-2 py-0.5 text-[10px] font-black text-slate-950"
                >
                    {{ activeDownloadsCount }}
                </span>
            </Link>
        </div>

        <!-- Universal Activity & Job Center in Sidebar Footer -->
        <div class="mt-auto space-y-2 pt-4">
            <div
                class="relative overflow-hidden rounded-2xl border border-cyan-500/20 bg-gradient-to-b from-cyan-950/40 to-slate-900/60 p-3.5"
            >
                <div
                    class="ambient-glow pointer-events-none -top-8 -right-8 h-20 w-20 bg-cyan-500"
                ></div>
                <div class="mb-1 flex items-center justify-between">
                    <h4
                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-cyan-300 uppercase"
                    >
                        <Activity class="h-3.5 w-3.5 text-cyan-400" />
                        <span>{{ t('jobs.universal_job_center') }}</span>
                    </h4>
                    <span
                        class="h-2 w-2 rounded-full"
                        :class="
                            isAnyRunning
                                ? 'animate-ping bg-cyan-400'
                                : isAnyPaused
                                  ? 'bg-amber-400'
                                  : 'animate-pulse bg-emerald-500'
                        "
                    ></span>
                </div>
                <p class="mb-2.5 text-[11px] leading-relaxed text-slate-400">
                    {{ t('jobs.sidebar_job_center_desc') }}
                </p>
                <button
                    @click="openActivityCenter()"
                    class="inline-flex w-full cursor-pointer items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/20 py-1.5 text-xs font-bold text-cyan-300 shadow-sm transition-all hover:bg-cyan-500 hover:text-slate-950"
                >
                    {{ t('jobs.open_universal_job_center') }}
                </button>
            </div>
        </div>
    </aside>
</template>
