<script setup lang="ts">
import { useI18n } from '@/i18n/useI18n';
import { LayoutDashboard, Film, Tv, FolderSync, Subtitles, BarChart3, DownloadCloud } from 'lucide-vue-next';

const { t, isRTL } = useI18n();

const navItems = [
    { nameKey: 'nav.dashboard', href: '/', icon: LayoutDashboard, pattern: '^/$|^/dashboard' },
    { nameKey: 'nav.movies', href: '/movies', icon: Film, pattern: '^/movies' },
    { nameKey: 'nav.series', href: '/series', icon: Tv, pattern: '^/series' },
    { nameKey: 'nav.organizer', href: '/organizer', icon: FolderSync, pattern: '^/organizer' },
    { nameKey: 'nav.subtitles', href: '/subtitles', icon: Subtitles, pattern: '^/subtitles' },
    { nameKey: 'nav.analytics', href: '/analytics', icon: BarChart3, pattern: '^/analytics' },
    { nameKey: 'nav.downloads', href: '/downloads', icon: DownloadCloud, pattern: '^/downloads' },
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
        <div class="space-y-1.5">
            <a
                v-for="item in navItems"
                :key="item.href"
                :href="item.href"
                class="flex items-center gap-3.5 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all group"
                :class="isActive(item.pattern)
                    ? 'bg-gradient-to-r from-cyan-500/20 to-blue-600/20 text-cyan-400 border border-cyan-500/30 shadow-lg shadow-cyan-500/10'
                    : 'text-slate-400 hover:text-slate-100 hover:bg-white/[0.05] border border-transparent'"
            >
                <component
                    :is="item.icon"
                    class="w-5 h-5 transition-transform group-hover:scale-110"
                    :class="isActive(item.pattern) ? 'text-cyan-400' : 'text-slate-400 group-hover:text-slate-200'"
                />
                <span>{{ t(item.nameKey) }}</span>
            </a>
        </div>

        <!-- Organizer Quick Status Card in Sidebar -->
        <div class="mt-auto pt-6">
            <div class="rounded-2xl bg-gradient-to-b from-cyan-950/40 to-slate-900/60 border border-cyan-500/20 p-4 relative overflow-hidden">
                <div class="ambient-glow bg-cyan-500 w-24 h-24 -top-8 -right-8"></div>
                <h4 class="font-bold text-xs text-cyan-300 uppercase tracking-wider mb-1">
                    {{ isRTL ? 'تنظيم القرص الذكي' : 'Smart Auto-Sort' }}
                </h4>
                <p class="text-xs text-slate-400 mb-3 leading-relaxed">
                    {{ isRTL ? 'هيكلة قياسية لمكتبات Plex و Jellyfin مع محاكاة التغييرات فورياً.' : 'Standardized Plex & Jellyfin folder hierarchies with 1-click dry run.' }}
                </p>
                <a
                    href="/organizer"
                    class="inline-flex items-center justify-center w-full py-2 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/40 text-xs font-bold transition-colors"
                >
                    {{ t('organizer.title') }}
                </a>
            </div>
        </div>
    </aside>
</template>
