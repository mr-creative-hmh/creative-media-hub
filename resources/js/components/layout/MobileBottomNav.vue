<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useLocale } from '@/composables/useLocale';
import { home } from '@/routes';
import { index as moviesRoute } from '@/routes/movies';
import { index as seriesRoute } from '@/routes/series';
import { index as collectionsRoute } from '@/routes/collections';
import { LayoutDashboard, Film, Tv, Layers, Menu } from '@lucide/vue';

withDefaults(
    defineProps<{
        isMenuOpen?: boolean;
    }>(),
    {
        isMenuOpen: false,
    },
);

const emit = defineEmits<{
    (e: 'open-menu'): void;
}>();

const { t } = useI18n();
const { isRtl } = useLocale();

const navItems = [
    {
        nameKey: 'nav.dashboard',
        href: home.url(),
        icon: LayoutDashboard,
        pattern: '^/$|^/dashboard',
    },
    {
        nameKey: 'nav.movies',
        href: moviesRoute.url(),
        icon: Film,
        pattern: '^/movies',
    },
    {
        nameKey: 'nav.series',
        href: seriesRoute.url(),
        icon: Tv,
        pattern: '^/series',
    },
    {
        nameKey: 'nav.collections',
        href: collectionsRoute.url(),
        icon: Layers,
        pattern: '^/collections',
    },
];

const isCurrentUrl = (pattern: string) => {
    if (typeof window !== 'undefined') {
        return new RegExp(pattern).test(window.location.pathname);
    }
    return false;
};
</script>

<template>
    <nav
        aria-label="Mobile navigation"
        :dir="isRtl ? 'rtl' : 'ltr'"
        class="border-border/80 cf-glass-elevated fixed inset-x-3 bottom-3 z-40 mx-auto mb-[env(safe-area-inset-bottom)] max-w-md rounded-3xl border px-2 py-1.5 shadow-2xl backdrop-blur-2xl transition-all select-none lg:hidden"
    >
        <div class="grid grid-cols-5 items-center">
            <!-- Main 4 Cinema Links -->
            <Link
                v-for="item in navItems"
                :key="item.href"
                :href="item.href"
                prefetch
                class="flex min-h-[48px] cursor-pointer flex-col items-center justify-center rounded-2xl px-1 py-1 transition-all duration-200 active:scale-90"
                :class="
                    isCurrentUrl(item.pattern)
                        ? 'bg-primary/15 text-primary font-bold shadow-xs'
                        : 'text-muted-foreground hover:bg-muted/40 hover:text-foreground'
                "
            >
                <component
                    :is="item.icon"
                    class="size-5 transition-transform"
                    :class="
                        isCurrentUrl(item.pattern)
                            ? 'scale-105 stroke-[2.25]'
                            : 'stroke-[1.75]'
                    "
                />
                <span
                    class="mt-0.5 max-w-[60px] truncate text-center text-[10px]"
                    :class="
                        isCurrentUrl(item.pattern)
                            ? 'font-black'
                            : 'font-semibold'
                    "
                >
                    {{ t(item.nameKey) }}
                </span>
            </Link>

            <!-- 5. More Menu Trigger -->
            <button
                type="button"
                class="flex min-h-[48px] cursor-pointer flex-col items-center justify-center rounded-2xl px-1 py-1 transition-all duration-200 active:scale-90"
                :class="
                    isMenuOpen
                        ? 'bg-primary/15 text-primary font-bold shadow-xs'
                        : 'text-muted-foreground hover:bg-muted/40 hover:text-foreground'
                "
                :title="t('nav.more') || (isRtl ? 'المزيد' : 'More')"
                @click="emit('open-menu')"
            >
                <Menu
                    class="size-5 transition-transform"
                    :class="
                        isMenuOpen ? 'scale-105 stroke-[2.25]' : 'stroke-[1.75]'
                    "
                />
                <span
                    class="mt-0.5 text-center text-[10px]"
                    :class="isMenuOpen ? 'font-black' : 'font-semibold'"
                >
                    {{ t('nav.more') || (isRtl ? 'المزيد' : 'More') }}
                </span>
            </button>
        </div>
    </nav>
</template>
