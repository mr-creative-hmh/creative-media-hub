<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import {
    LayoutDashboard,
    Film,
    Tv,
    Layers,
    Compass,
    History
} from 'lucide-vue-next';

const { t, isRTL } = useI18n();

const navItems = [
    { nameKey: 'nav.dashboard', href: '/', icon: LayoutDashboard, pattern: '^/$|^/dashboard' },
    { nameKey: 'nav.movies', href: '/movies', icon: Film, pattern: '^/movies' },
    { nameKey: 'nav.series', href: '/series', icon: Tv, pattern: '^/series' },
    { nameKey: 'nav.collections', href: '/collections', icon: Layers, pattern: '^/collections' },
    { nameKey: 'nav.scout', href: '/scout', icon: Compass, pattern: '^/scout' },
    { nameKey: 'nav.watch_history', href: '/watch-history', icon: History, pattern: '^/watch-history' },
];

const isActive = (pattern: string) => {
    if (typeof window !== 'undefined') {
        return new RegExp(pattern).test(window.location.pathname);
    }
    return false;
};
</script>

<template>
    <nav
        class="lg:hidden fixed bottom-0 inset-x-0 z-40 glass-panel border-t border-white/10 bg-[#07090E]/95 backdrop-blur-2xl shadow-2xl px-2 py-1.5 transition-all duration-300"
        :dir="isRTL ? 'rtl' : 'ltr'"
    >
        <div class="flex items-center justify-around max-w-lg mx-auto">
            <Link
                v-for="item in navItems"
                :key="item.href"
                :href="item.href"
                class="relative flex flex-col items-center justify-center py-1 px-1.5 rounded-2xl transition-all duration-200 group cursor-pointer"
                :class="isActive(item.pattern)
                    ? 'text-cyan-400 font-black'
                    : 'text-slate-400 hover:text-white font-medium'"
            >
                <!-- Active Indicator Glow Pill -->
                <div
                    v-if="isActive(item.pattern)"
                    class="absolute -top-1.5 w-6 h-1 rounded-full bg-gradient-to-r from-cyan-400 to-blue-500 shadow-lg shadow-cyan-400/50"
                ></div>

                <!-- Icon with active scale -->
                <div
                    class="p-1 rounded-xl transition-all duration-200"
                    :class="isActive(item.pattern) ? 'bg-cyan-500/15 scale-110 shadow-inner' : 'group-hover:scale-105'"
                >
                    <component
                        :is="item.icon"
                        class="w-5 h-5 transition-transform"
                        :class="isActive(item.pattern) ? 'text-cyan-400' : 'text-slate-400 group-hover:text-slate-200'"
                    />
                </div>

                <!-- Label -->
                <span class="text-[10px] tracking-tight truncate max-w-[62px] mt-0.5 leading-tight">
                    {{ t(item.nameKey) }}
                </span>
            </Link>
        </div>
    </nav>
</template>
