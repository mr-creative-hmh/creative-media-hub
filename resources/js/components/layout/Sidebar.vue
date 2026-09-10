<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useScanner } from '@/composables/useScanner';
import { useDownloader } from '@/composables/useDownloader';
import { useActivityCenter } from '@/composables/useActivityCenter';
import {
    LayoutDashboard, Film, History, Layers, Tv, ScanLine, FolderSync,
    Subtitles, BarChart3, DownloadCloud, Settings, Sparkles,
    RefreshCw, Pause, Play, HardDrive, BookOpen, Activity
} from 'lucide-vue-next';

const { t, isRTL } = useI18n();
const { scanStatus, isScanning, isPaused, openScanModal } = useScanner();
const { activeDownloads } = useDownloader();
const { isAnyRunning, isAnyPaused, activeJobsCount, openActivityCenter } = useActivityCenter();

const activeDownloadsCount = computed(() => activeDownloads.value.length);

const navItems = [
    { nameKey: 'nav.dashboard', href: '/', icon: LayoutDashboard, pattern: '^/$|^/dashboard' },
    { nameKey: 'nav.movies', href: '/movies', icon: Film, pattern: '^/movies' },
    { nameKey: 'nav.collections', href: '/collections', icon: Layers, pattern: '^/collections' },
    { nameKey: 'nav.series', href: '/series', icon: Tv, pattern: '^/series' },
    { nameKey: 'nav.watch_history', href: '/watch-history', icon: History, pattern: '^/watch-history' },
    { nameKey: 'nav.metadata', href: '/metadata', icon: Sparkles, pattern: '^/metadata' },
    { nameKey: 'nav.scanner', href: '/scanner', icon: ScanLine, pattern: '^/scanner' },
    { nameKey: 'nav.organizer', href: '/organizer', icon: FolderSync, pattern: '^/organizer' },
    { nameKey: 'nav.subtitles', href: '/subtitles', icon: Subtitles, pattern: '^/subtitles' },
    { nameKey: 'nav.analytics', href: '/analytics', icon: BarChart3, pattern: '^/analytics' },
    { nameKey: 'nav.downloads', href: '/downloads', icon: DownloadCloud, pattern: '^/downloads' },
    { nameKey: 'nav.docs', href: '/docs', icon: BookOpen, Activity, pattern: '^/docs|^/guide' },
    { nameKey: 'nav.settings', href: '/settings', icon: Settings, pattern: '^/settings' },
];

const isActive = (pattern: string) => {
    if (typeof window !== 'undefined') {
        return new RegExp(pattern).test(window.location.pathname);
    }
    return false;
};
</script>

<template>
    <aside class="hidden lg:flex flex-col w-64 glass-panel border-r border-white/10 min-h-[calc(100vh-61px)] p-4 shrink-0 transition-all bg-[#0A0D14]/80 space-y-4">
        <!-- Top Fast Scan Trigger -->
        <div>
            <button
                @click="openScanModal"
                class="w-full py-2.5 px-4 rounded-xl text-slate-950 font-black text-xs flex items-center justify-center gap-2 shadow-lg shadow-cyan-500/20 active:scale-[0.98] transition-all bg-gradient-to-r from-cyan-400 via-cyan-500 to-blue-500 hover:from-cyan-300 hover:to-blue-400 cursor-pointer"
            >
                <RefreshCw v-if="isScanning && !isPaused" class="w-4 h-4 animate-spin text-slate-950" />
                <Pause v-else-if="isPaused" class="w-4 h-4 text-slate-950 fill-current" />
                <HardDrive v-else class="w-4 h-4 text-slate-950" />
                <span>
                    {{ isScanning ? (isPaused ? (isRTL ? 'الفحص متوقف مؤقتاً' : 'Scan Paused') : `${isRTL ? 'جاري الفحص' : 'Scanning'} ${scanStatus.progress_percent || 0}%`) : (isRTL ? 'فحص ومراقبة المكتبة' : 'Scan Media Library') }}
                </span>
            </button>
        </div>

        <!-- Navigation Links (Instant SPA via Inertia Link) -->
        <div class="space-y-1">
            <Link
                v-for="item in navItems"
                :key="item.href"
                :href="item.href"
                class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all group cursor-pointer"
                :class="isActive(item.pattern)
                    ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 shadow-sm font-bold'
                    : 'text-slate-400 hover:text-white hover:bg-white/[0.05] border border-transparent'"
            >
                <div class="flex items-center gap-3.5">
                    <component
                        :is="item.icon"
                        class="w-4.5 h-4.5 transition-transform group-hover:scale-110"
                        :class="isActive(item.pattern) ? 'text-cyan-400' : 'text-slate-400 group-hover:text-slate-200'"
                    />
                    <span>{{ t(item.nameKey) }}</span>
                </div>

                <span
                    v-if="item.href === '/downloads' && activeDownloadsCount > 0"
                    class="px-2 py-0.5 rounded-full bg-cyan-500 text-slate-950 font-black text-[10px] animate-pulse"
                >
                    {{ activeDownloadsCount }}
                </span>
            </Link>
        </div>

        <!-- Universal Activity & Job Center in Sidebar Footer -->
        <div class="mt-auto pt-4 space-y-2">
            <div class="rounded-2xl bg-gradient-to-b from-cyan-950/40 to-slate-900/60 border border-cyan-500/20 p-3.5 relative overflow-hidden">
                <div class="ambient-glow bg-cyan-500 w-20 h-20 -top-8 -right-8 pointer-events-none"></div>
                <div class="flex items-center justify-between mb-1">
                    <h4 class="font-bold text-xs text-cyan-300 uppercase tracking-wider flex items-center gap-1.5">
                        <Activity class="w-3.5 h-3.5 text-cyan-400" />
                        <span>{{ isRTL ? 'مركز العمليات الموحد' : 'Universal Job Center' }}</span>
                    </h4>
                    <span
                        class="w-2 h-2 rounded-full"
                        :class="isAnyRunning ? 'bg-cyan-400 animate-ping' : isAnyPaused ? 'bg-amber-400' : 'bg-emerald-500 animate-pulse'"
                    ></span>
                </div>
                <p class="text-[11px] text-slate-400 mb-2.5 leading-relaxed">
                    {{ isRTL ? 'إدارة وتتبع الفاحص الافتراضي ومنظم القرص الفعلي وفاحص الترجمات مباشرة.' : 'Live unified control for Virtual Scanner, Disk Organizer, and Subtitles.' }}
                </p>
                <button
                    @click="openActivityCenter()"
                    class="inline-flex items-center justify-center w-full py-1.5 rounded-xl bg-cyan-500/20 text-cyan-300 hover:bg-cyan-500 hover:text-slate-950 border border-cyan-500/30 text-xs font-bold transition-all shadow-sm cursor-pointer"
                >
                    {{ isRTL ? 'فتح مركز العمليات الموحد' : 'Open Universal Job Center' }}
                </button>
            </div>
        </div>
    </aside>
</template>
