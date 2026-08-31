<script setup lang="ts">
import { useI18n } from '@/i18n/useI18n';
import {
    LayoutDashboard, Film, Tv, ScanLine, FolderSync,
    Subtitles, BarChart3, DownloadCloud, Settings
} from 'lucide-vue-next';

const { t, isRTL } = useI18n();

const navItems = [
    { nameKey: 'nav.dashboard', href: '/', icon: LayoutDashboard, pattern: '^/$|^/dashboard' },
    { nameKey: 'nav.movies', href: '/movies', icon: Film, pattern: '^/movies' },
    { nameKey: 'nav.series', href: '/series', icon: Tv, pattern: '^/series' },
    { nameKey: 'nav.scanner', href: '/scanner', icon: ScanLine, pattern: '^/scanner' },
    { nameKey: 'nav.organizer', href: '/organizer', icon: FolderSync, pattern: '^/organizer' },
    { nameKey: 'nav.subtitles', href: '/subtitles', icon: Subtitles, pattern: '^/subtitles' },
    { nameKey: 'nav.analytics', href: '/analytics', icon: BarChart3, pattern: '^/analytics' },
    { nameKey: 'nav.downloads', href: '/downloads', icon: DownloadCloud, pattern: '^/downloads' },
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
    <aside class="hidden lg:flex flex-col w-64 glass-panel border-r border-white/10 min-h-[calc(100vh-61px)] p-4 shrink-0 transition-all">
        <div class="space-y-1">
            <a
                v-for="item in navItems"
                :key="item.href"
                :href="item.href"
                class="flex items-center gap-3.5 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all group cursor-pointer"
                :class="isActive(item.pattern)
                    ? 'bg-gradient-to-r from-cyan-500/20 to-blue-600/20 text-cyan-400 border border-cyan-500/30 shadow-lg shadow-cyan-500/10'
                    : 'text-slate-400 hover:text-slate-100 hover:bg-white/[0.05] border border-transparent'"
            >
                <component
                    :is="item.icon"
                    class="w-4.5 h-4.5 transition-transform group-hover:scale-110"
                    :class="isActive(item.pattern) ? 'text-cyan-400' : 'text-slate-400 group-hover:text-slate-200'"
                />
                <span>{{ t(item.nameKey) }}</span>
            </a>
        </div>

        <!-- Virtual Scanner & Organizer Status Card in Sidebar -->
        <div class="mt-auto pt-4 space-y-2">
            <div class="rounded-2xl bg-gradient-to-b from-cyan-950/40 to-slate-900/60 border border-cyan-500/20 p-3.5 relative overflow-hidden">
                <div class="ambient-glow bg-cyan-500 w-20 h-20 -top-8 -right-8"></div>
                <div class="flex items-center justify-between mb-1">
                    <h4 class="font-bold text-xs text-cyan-300 uppercase tracking-wider">
                        {{ isRTL ? 'الفاحص الذكي' : 'Virtual Scanner' }}
                    </h4>
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                </div>
                <p class="text-[11px] text-slate-400 mb-2.5 leading-relaxed">
                    {{ isRTL ? 'إضافة المجلدات وفحص محتوياتها بالخلفية بدون نقل الملفات.' : 'Monitor local folders and index media in background without moving files.' }}
                </p>
                <a
                    href="/scanner"
                    class="inline-flex items-center justify-center w-full py-1.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/40 text-xs font-bold transition-colors"
                >
                    {{ isRTL ? 'فتح الفاحص الافتراضي' : 'Open Virtual Scanner' }}
                </a>
            </div>
        </div>
    </aside>
</template>
