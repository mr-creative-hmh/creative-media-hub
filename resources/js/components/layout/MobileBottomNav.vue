<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import {
    LayoutDashboard,
    Film,
    Tv,
    Layers,
    Compass,
    History,
} from '@lucide/vue';

const { t, isRTL } = useI18n();

const navItems = [
    {
        nameKey: 'nav.dashboard',
        href: '/',
        icon: LayoutDashboard,
        pattern: '^/$|^/dashboard',
    },
    { nameKey: 'nav.movies', href: '/movies', icon: Film, pattern: '^/movies' },
    { nameKey: 'nav.series', href: '/series', icon: Tv, pattern: '^/series' },
    {
        nameKey: 'nav.collections',
        href: '/collections',
        icon: Layers,
        pattern: '^/collections',
    },
    { nameKey: 'nav.scout', href: '/scout', icon: Compass, pattern: '^/scout' },
    {
        nameKey: 'nav.watch_history',
        href: '/watch-history',
        icon: History,
        pattern: '^/watch-history',
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
    <nav
        class="glass-panel fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-[#07090E]/95 px-2 py-1.5 shadow-2xl backdrop-blur-2xl transition-all duration-300 lg:hidden"
        :dir="isRTL ? 'rtl' : 'ltr'"
    >
        <div class="mx-auto flex max-w-lg items-center justify-around">
            <Link
                v-for="item in navItems"
                :key="item.href"
                :href="item.href"
                class="group relative flex cursor-pointer flex-col items-center justify-center rounded-2xl px-1.5 py-1 transition-all duration-200"
                :class="
                    isActive(item.pattern)
                        ? 'font-black text-cyan-400'
                        : 'font-medium text-slate-400 hover:text-white'
                "
            >
                <!-- Active Indicator Glow Pill -->
                <div
                    v-if="isActive(item.pattern)"
                    class="absolute -top-1.5 h-1 w-6 rounded-full bg-gradient-to-r from-cyan-400 to-blue-500 shadow-lg shadow-cyan-400/50"
                ></div>

                <!-- Icon with active scale -->
                <div
                    class="rounded-xl p-1 transition-all duration-200"
                    :class="
                        isActive(item.pattern)
                            ? 'scale-110 bg-cyan-500/15 shadow-inner'
                            : 'group-hover:scale-105'
                    "
                >
                    <component
                        :is="item.icon"
                        class="h-5 w-5 transition-transform"
                        :class="
                            isActive(item.pattern)
                                ? 'text-cyan-400'
                                : 'text-slate-400 group-hover:text-slate-200'
                        "
                    />
                </div>

                <!-- Label -->
                <span
                    class="mt-0.5 max-w-[62px] truncate text-[10px] leading-tight tracking-tight"
                >
                    {{ t(item.nameKey) }}
                </span>
            </Link>
        </div>
    </nav>
</template>
