<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { router, Link, usePage } from '@inertiajs/vue3';
import { useDownloader } from '@/composables/useDownloader';
import { useActivityCenter } from '@/composables/useActivityCenter';
import AppLogo from '@/components/common/AppLogo.vue';
import AccentColorPicker from '@/components/common/AccentColorPicker.vue';
import LiveSearchDropdown from '@/components/common/LiveSearchDropdown.vue';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { home } from '@/routes';
import { index as moviesRoute } from '@/routes/movies';
import { index as downloadsRoute } from '@/routes/downloads';
import { logout } from '@/actions/App/Http/Controllers/AuthController';
import {
    Globe,
    DownloadCloud,
    Menu,
    Activity,
    RefreshCw,
    Pause,
    LogOut,
    PanelLeftClose,
    PanelLeftOpen,
} from '@lucide/vue';

withDefaults(
    defineProps<{
        isSidebarCollapsed?: boolean;
    }>(),
    {
        isSidebarCollapsed: false,
    },
);

const emit = defineEmits<{
    (e: 'toggle-sidebar'): void;
    (e: 'open-menu'): void;
}>();

const page = usePage();
const { t, locale, setLocale, isRTL } = useI18n();
const { activeDownloads, totalSpeedDownFormatted } = useDownloader();
const { isAnyRunning, isAnyPaused, activeJobsCount, openActivityCenter } =
    useActivityCenter();

const user = computed(() => (page.props.auth as any)?.user);
const isAdmin = computed(() =>
    Boolean(user.value?.is_admin || user.value?.role === 'admin'),
);

const searchQuery = ref('');

const handleSearch = (q?: string) => {
    const term = (typeof q === 'string' ? q : searchQuery.value).trim();
    if (term) {
        router.get(moviesRoute.url(), { search: term });
    }
};

const toggleLanguage = () => {
    setLocale(locale.value === 'ar' ? 'en' : 'ar');
};

const handleLogout = () => {
    router.post(logout.url());
};
</script>

<template>
    <header
        class="cf-glass-elevated border-border/80 sticky top-3 z-30 mx-3 mt-3 flex h-14 shrink-0 items-center justify-between gap-3 rounded-2xl border px-3.5 backdrop-blur-2xl transition-all lg:mx-6 lg:px-4"
    >
        <!-- Left: Logo & Sidebar / Mobile Drawer Toggles -->
        <div class="flex shrink-0 items-center gap-2.5 sm:gap-3">
            <!-- Mobile Menu Drawer Trigger -->
            <button
                type="button"
                @click="emit('open-menu')"
                class="bg-card border-border/80 text-muted-foreground hover:bg-muted hover:text-foreground flex size-9 cursor-pointer items-center justify-center rounded-xl border lg:hidden"
                :title="isRTL ? 'القائمة' : 'Menu'"
                :aria-label="isRTL ? 'القائمة' : 'Menu'"
            >
                <Menu class="size-4.5" />
            </button>

            <!-- Desktop Sidebar Toggle Button -->
            <button
                type="button"
                @click="emit('toggle-sidebar')"
                class="bg-card border-border/80 text-muted-foreground hover:bg-muted hover:text-foreground hidden size-9 cursor-pointer items-center justify-center rounded-xl border transition-colors lg:flex"
                :title="
                    isSidebarCollapsed
                        ? t('nav.expand_sidebar') || 'Expand Sidebar'
                        : t('nav.collapse_sidebar') || 'Collapse Sidebar'
                "
            >
                <component
                    :is="isSidebarCollapsed ? PanelLeftOpen : PanelLeftClose"
                    class="text-muted-foreground hover:text-foreground size-4 rtl:rotate-180"
                />
            </button>

            <!-- Mobile Logo Icon Only (Hidden on Desktop because Sidebar owns the desktop brand header) -->
            <Link
                :href="home.url()"
                prefetch
                class="flex shrink-0 items-center lg:hidden"
            >
                <AppLogo size="sm" :show-text="false" />
            </Link>
        </div>

        <!-- Center: Universal Instant Search Bar -->
        <div class="mx-1 max-w-xl min-w-0 flex-1 sm:mx-4">
            <LiveSearchDropdown
                v-model="searchQuery"
                :placeholder="t('common.search_placeholder')"
                variant="navbar"
                :auto-navigate="false"
                @submit="handleSearch"
            />
        </div>

        <!-- Right: Action Controls -->
        <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
            <!-- Active Downloads Quick Pill (Admin Only) -->
            <Link
                v-if="isAdmin && activeDownloads.length > 0"
                :href="downloadsRoute.url()"
                prefetch
                class="border-primary/40 bg-primary/10 text-primary hover:bg-primary/20 flex cursor-pointer items-center gap-2 rounded-xl border px-2.5 py-1.5 text-xs font-bold shadow-xs transition-all active:scale-95"
                :title="isRTL ? 'التنزيلات النشطة' : 'Active Downloads'"
            >
                <DownloadCloud
                    class="text-primary size-4 shrink-0 animate-bounce"
                />
                <span
                    class="bg-primary/25 py-0.2 text-primary-foreground rounded-md px-1.5 font-mono text-[10px] font-black"
                >
                    {{ activeDownloads.length }}
                </span>
                <span
                    class="text-foreground hidden font-mono text-[11px] sm:inline"
                >
                    {{ totalSpeedDownFormatted }}
                </span>
                <span
                    class="bg-primary size-1.5 shrink-0 animate-ping rounded-full"
                ></span>
            </Link>

            <!-- Universal Activity & Job Center Navbar Pill (Admin Only) -->
            <template v-if="isAdmin">
                <button
                    v-if="isAnyRunning || isAnyPaused"
                    type="button"
                    @click="openActivityCenter()"
                    class="flex cursor-pointer items-center gap-1.5 rounded-xl border px-2.5 py-1.5 text-xs font-bold transition-all active:scale-95 sm:gap-2"
                    :class="
                        isAnyRunning
                            ? 'border-primary/40 bg-primary/15 text-primary shadow-xs'
                            : 'border-amber-500/40 bg-amber-500/15 text-amber-400'
                    "
                    :title="
                        isRTL
                            ? 'مركز مراقبة وإدارة العمليات الموحد'
                            : 'Universal Job Center'
                    "
                >
                    <RefreshCw
                        v-if="isAnyRunning"
                        class="text-primary size-3.5 animate-spin"
                    />
                    <Pause v-else class="size-3.5 text-amber-400" />
                    <span class="hidden font-bold sm:inline">
                        {{ isRTL ? 'العمليات' : 'Jobs' }}
                    </span>
                    <span
                        class="bg-card/60 py-0.2 rounded-md px-1.5 font-mono text-[10px]"
                    >
                        {{ activeJobsCount }}
                    </span>
                </button>

                <button
                    v-else
                    type="button"
                    @click="openActivityCenter()"
                    class="border-border/80 bg-card text-muted-foreground hover:border-primary/40 hover:text-foreground hidden cursor-pointer items-center gap-1.5 rounded-xl border px-2.5 py-1.5 text-xs font-bold shadow-xs transition-all active:scale-95 sm:flex"
                    :title="
                        isRTL
                            ? 'مركز مراقبة وإدارة العمليات الموحد'
                            : 'Universal Job Center'
                    "
                >
                    <Activity class="text-primary size-3.5" />
                    <span>{{ isRTL ? 'العمليات' : 'Jobs' }}</span>
                </button>
            </template>

            <!-- Cinema Accent Color Picker -->
            <AccentColorPicker />

            <!-- Language Switcher -->
            <button
                type="button"
                @click="toggleLanguage"
                class="border-border/80 bg-card text-muted-foreground hover:border-primary/40 hover:text-foreground flex cursor-pointer items-center gap-1.5 rounded-xl border px-2.5 py-1.5 text-xs font-bold shadow-xs transition-all active:scale-95"
                :title="locale === 'ar' ? 'English' : 'عربي'"
            >
                <Globe class="text-primary size-3.5" />
                <span class="hidden sm:inline">{{
                    locale === 'ar' ? 'English' : 'عربي'
                }}</span>
                <span class="inline font-mono uppercase sm:hidden">{{
                    locale === 'ar' ? 'EN' : 'AR'
                }}</span>
            </button>

            <!-- Active User Profile Pill & Quick Logout -->
            <div
                v-if="user"
                class="border-border/80 bg-card/70 hidden items-center gap-1.5 rounded-xl border p-1 ps-2 text-xs sm:flex"
            >
                <div class="flex items-center gap-1.5">
                    <span class="text-base leading-none">
                        {{
                            user.avatar === 'director'
                                ? '🎬'
                                : user.avatar === 'robot'
                                  ? '🤖'
                                  : user.avatar === 'clapper'
                                    ? '🎞️'
                                    : user.avatar === 'star'
                                      ? '⭐'
                                      : user.avatar === 'reel'
                                        ? '🎥'
                                        : '🍿'
                        }}
                    </span>
                    <span
                        class="text-foreground max-w-[80px] truncate font-bold md:max-w-[120px]"
                    >
                        {{ user.name }}
                    </span>
                </div>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            @click="handleLogout"
                            class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 cursor-pointer rounded-lg p-1 transition-colors"
                        >
                            <LogOut class="size-3.5" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <span>{{
                            t('auth.switch_profile') || 'Switch Profile'
                        }}</span>
                    </TooltipContent>
                </Tooltip>
            </div>
        </div>
    </header>
</template>
