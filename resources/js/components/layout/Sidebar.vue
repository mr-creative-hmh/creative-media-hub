<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useScanner } from '@/composables/useScanner';
import { useDownloader } from '@/composables/useDownloader';
import { useActivityCenter } from '@/composables/useActivityCenter';
import AppLogo from '@/components/common/AppLogo.vue';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { home } from '@/routes';
import { index as moviesRoute } from '@/routes/movies';
import { index as collectionsRoute } from '@/routes/collections';
import { index as seriesRoute } from '@/routes/series';
import { index as watchHistoryRoute } from '@/routes/watch-history';
import { index as metadataRoute } from '@/routes/metadata';
import { index as scannerRoute } from '@/routes/scanner';
import { index as organizerRoute } from '@/routes/organizer';
import { index as scoutRoute } from '@/routes/scout';
import { index as subtitlesRoute } from '@/routes/subtitles';
import { index as analyticsRoute } from '@/routes/analytics';
import { index as downloadsRoute } from '@/routes/downloads';
import { index as docsRoute } from '@/routes/docs';
import { index as settingsRoute } from '@/routes/settings';
import { index as usersRoute } from '@/routes/users';
import { logout } from '@/actions/App/Http/Controllers/AuthController';
import {
    LayoutDashboard,
    Film,
    Tv,
    Layers,
    History,
    Compass,
    ScanLine,
    FolderSync,
    Subtitles,
    Sparkles,
    DownloadCloud,
    Users,
    BarChart3,
    BookOpen,
    Settings,
    RefreshCw,
    Pause,
    HardDrive,
    Activity,
    LogOut,
    ChevronDown,
    PanelLeftClose,
    PanelLeftOpen,
} from '@lucide/vue';

withDefaults(
    defineProps<{
        isCollapsed?: boolean;
    }>(),
    {
        isCollapsed: false,
    },
);

const emit = defineEmits<{
    (e: 'toggle-collapse'): void;
}>();

const page = usePage();
const { t, isRTL } = useI18n();
const { scanStatus, isScanning, isPaused, openScanModal } = useScanner();
const { activeDownloads } = useDownloader();
const { isAnyRunning, isAnyPaused, activeJobsCount, openActivityCenter } =
    useActivityCenter();

const user = computed(() => (page.props.auth as any)?.user);
const isAdmin = computed(() =>
    Boolean(user.value?.is_admin || user.value?.role === 'admin'),
);
const activeDownloadsCount = computed(() => activeDownloads.value.length);

interface NavSection {
    id: string;
    labelKey: string;
    defaultOpen?: boolean;
    adminOnly?: boolean;
    items: {
        nameKey: string;
        href: string;
        icon: any;
        pattern: string;
        adminOnly?: boolean;
    }[];
}

const navSections = computed<NavSection[]>(() => [
    {
        id: 'cinema',
        labelKey: 'nav.section_cinema',
        defaultOpen: true,
        items: [
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
            {
                nameKey: 'nav.watch_history',
                href: watchHistoryRoute.url(),
                icon: History,
                pattern: '^/watch-history',
            },
        ],
    },
    {
        id: 'discovery',
        labelKey: 'nav.section_discovery',
        defaultOpen: true,
        adminOnly: true,
        items: [
            {
                nameKey: 'nav.scout',
                href: scoutRoute.url(),
                icon: Compass,
                pattern: '^/scout',
                adminOnly: true,
            },
        ],
    },
    {
        id: 'studio',
        labelKey: 'nav.section_studio',
        defaultOpen: true,
        adminOnly: true,
        items: [
            {
                nameKey: 'nav.scanner',
                href: scannerRoute.url(),
                icon: ScanLine,
                pattern: '^/scanner',
            },
            {
                nameKey: 'nav.organizer',
                href: organizerRoute.url(),
                icon: FolderSync,
                pattern: '^/organizer',
            },
            {
                nameKey: 'nav.subtitles',
                href: subtitlesRoute.url(),
                icon: Subtitles,
                pattern: '^/subtitles',
            },
            {
                nameKey: 'nav.metadata',
                href: metadataRoute.url(),
                icon: Sparkles,
                pattern: '^/metadata',
            },
            {
                nameKey: 'nav.downloads',
                href: downloadsRoute.url(),
                icon: DownloadCloud,
                pattern: '^/downloads',
            },
            {
                nameKey: 'nav.manage_profiles',
                href: usersRoute.url(),
                icon: Users,
                pattern: '^/users',
            },
        ],
    },
    {
        id: 'system',
        labelKey: 'nav.section_system',
        defaultOpen: false,
        items: [
            {
                nameKey: 'nav.analytics',
                href: analyticsRoute.url(),
                icon: BarChart3,
                pattern: '^/analytics',
                adminOnly: true,
            },
            {
                nameKey: 'nav.docs',
                href: docsRoute.url(),
                icon: BookOpen,
                pattern: '^/docs|^/guide',
            },
            {
                nameKey: 'nav.settings',
                href: settingsRoute.url(),
                icon: Settings,
                pattern: '^/settings',
                adminOnly: true,
            },
        ],
    },
]);

const openMap = reactive<Record<string, boolean>>({});

function isSectionOpen(section: NavSection): boolean {
    if (section.id in openMap) {
        return openMap[section.id];
    }
    return section.defaultOpen ?? true;
}

function toggleSection(section: NavSection): void {
    openMap[section.id] = !isSectionOpen(section);
}

const isCurrentUrl = (pattern: string) => {
    const pathname = page.url.split('?')[0];
    return new RegExp(pattern).test(pathname);
};

const handleLogout = () => {
    router.post(logout.url());
};
</script>

<template>
    <aside
        class="border-border/80 bg-sidebar/90 sticky top-0 z-30 hidden h-screen shrink-0 flex-col border-e backdrop-blur-2xl transition-[width] duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] lg:flex"
        :class="isCollapsed ? 'w-[4.5rem]' : 'w-64'"
    >
        <!-- Top Anchored Brand Header in Sidebar -->
        <div
            class="border-border/60 flex shrink-0 items-center border-b px-3.5 py-3.5"
            :class="isCollapsed ? 'justify-center' : 'justify-start'"
        >
            <Link :href="home.url()" prefetch class="flex items-center">
                <AppLogo size="md" :show-text="!isCollapsed" />
            </Link>
        </div>

        <!-- Fast Scan Action Trigger (Admin Only) -->
        <div v-if="isAdmin" class="shrink-0 px-3 pt-3">
            <Tooltip v-if="isCollapsed">
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        @click="openScanModal"
                        class="from-primary to-primary/80 text-primary-foreground shadow-primary/25 flex size-11 w-full cursor-pointer items-center justify-center rounded-2xl bg-gradient-to-r shadow-sm transition-all hover:brightness-105 active:scale-95"
                    >
                        <RefreshCw
                            v-if="isScanning && !isPaused"
                            class="size-5 animate-spin"
                        />
                        <Pause
                            v-else-if="isPaused"
                            class="size-5 fill-current"
                        />
                        <HardDrive v-else class="size-5" />
                    </button>
                </TooltipTrigger>
                <TooltipContent :side="isRTL ? 'left' : 'right'">
                    <span>{{
                        isScanning
                            ? isPaused
                                ? t('sidebar.scan_paused')
                                : `${t('sidebar.scanning')} ${scanStatus.progress_percent || 0}%`
                            : t('sidebar.scan_media_library')
                    }}</span>
                </TooltipContent>
            </Tooltip>

            <button
                v-else
                type="button"
                @click="openScanModal"
                class="from-primary via-primary to-primary/85 text-primary-foreground shadow-primary/30 flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-gradient-to-r px-4 py-2.5 text-xs font-black shadow-sm transition-all hover:brightness-105 active:scale-[0.98]"
            >
                <RefreshCw
                    v-if="isScanning && !isPaused"
                    class="size-4 animate-spin"
                />
                <Pause v-else-if="isPaused" class="size-4 fill-current" />
                <HardDrive v-else class="size-4" />
                <span class="truncate">
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

        <!-- Grouped Navigation Links (Middle Scroll Area) -->
        <div
            class="custom-scrollbar min-h-0 flex-1 space-y-3 overflow-x-hidden overflow-y-auto px-3 py-3 pe-1"
        >
            <div
                v-for="(section, sIdx) in navSections"
                :key="section.id"
                v-show="!section.adminOnly || isAdmin"
                :class="
                    sIdx > 0 && !isCollapsed
                        ? 'border-border/60 border-t pt-2.5'
                        : ''
                "
            >
                <!-- Section Header (Expanded Mode) -->
                <button
                    v-if="!isCollapsed"
                    type="button"
                    class="text-muted-foreground/80 hover:text-foreground mb-1 flex w-full cursor-pointer items-center justify-between rounded-lg px-2 py-1 text-start text-[10px] font-black tracking-wider uppercase transition-colors"
                    :aria-expanded="isSectionOpen(section)"
                    @click="toggleSection(section)"
                >
                    <span>{{ t(section.labelKey) }}</span>
                    <ChevronDown
                        class="size-3.5 transition-transform duration-150"
                        :class="
                            isSectionOpen(section)
                                ? 'rotate-0'
                                : '-rotate-90 rtl:rotate-90'
                        "
                        aria-hidden="true"
                    />
                </button>

                <!-- Section Links -->
                <nav
                    v-show="isCollapsed || isSectionOpen(section)"
                    class="space-y-1"
                >
                    <template v-for="item in section.items" :key="item.nameKey">
                        <template v-if="!item.adminOnly || isAdmin">
                            <!-- Collapsed Icon Rail Link with Tooltip -->
                            <Tooltip v-if="isCollapsed">
                                <TooltipTrigger as-child>
                                    <Link
                                        :href="item.href"
                                        prefetch
                                        class="group relative flex size-11 w-full cursor-pointer items-center justify-center rounded-2xl transition-all"
                                        :class="
                                            isCurrentUrl(item.pattern)
                                                ? 'bg-primary/15 text-primary border-primary/30 border font-bold shadow-xs'
                                                : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground border border-transparent'
                                        "
                                    >
                                        <component
                                            :is="item.icon"
                                            class="size-5 transition-transform group-hover:scale-110"
                                            :class="
                                                isCurrentUrl(item.pattern)
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground'
                                            "
                                        />
                                        <span
                                            v-if="
                                                item.href ===
                                                    downloadsRoute.url() &&
                                                activeDownloadsCount > 0
                                            "
                                            class="bg-primary text-primary-foreground absolute -end-1 -top-1 flex size-4 items-center justify-center rounded-full font-mono text-[9px] font-bold"
                                        >
                                            {{ activeDownloadsCount }}
                                        </span>
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent
                                    :side="isRTL ? 'left' : 'right'"
                                >
                                    <span>{{ t(item.nameKey) }}</span>
                                </TooltipContent>
                            </Tooltip>

                            <!-- Expanded Nav Link -->
                            <Link
                                v-else
                                :href="item.href"
                                prefetch
                                class="group flex cursor-pointer items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold transition-all"
                                :class="
                                    isCurrentUrl(item.pattern)
                                        ? 'border-primary/35 bg-primary/15 text-primary border font-bold shadow-xs'
                                        : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground border border-transparent'
                                "
                            >
                                <div class="flex items-center gap-3">
                                    <component
                                        :is="item.icon"
                                        class="size-4.5 transition-transform group-hover:scale-110"
                                        :class="
                                            isCurrentUrl(item.pattern)
                                                ? 'text-primary'
                                                : 'text-muted-foreground group-hover:text-foreground'
                                        "
                                    />
                                    <span class="truncate">{{
                                        t(item.nameKey)
                                    }}</span>
                                </div>

                                <span
                                    v-if="
                                        item.href === downloadsRoute.url() &&
                                        activeDownloadsCount > 0
                                    "
                                    class="bg-primary text-primary-foreground animate-pulse rounded-full px-2 py-0.5 font-mono text-[10px] font-black"
                                >
                                    {{ activeDownloadsCount }}
                                </span>
                            </Link>
                        </template>
                    </template>
                </nav>
            </div>
        </div>

        <!-- Sidebar Footer: Pinned Bottom User Profile Badge & Activity Trigger -->
        <div
            class="border-border/70 bg-sidebar/95 shrink-0 space-y-2 border-t p-3"
        >
            <!-- Universal Job Center Card (Admin Only) -->
            <div v-if="isAdmin && !isCollapsed">
                <button
                    type="button"
                    @click="openActivityCenter()"
                    class="border-primary/25 bg-primary/5 hover:bg-primary/10 flex w-full cursor-pointer items-center justify-between rounded-xl border p-2 text-xs transition-colors"
                >
                    <div class="text-primary flex items-center gap-2 font-bold">
                        <Activity class="size-3.5" />
                        <span>{{
                            t('jobs.universal_job_center') || 'Job Center'
                        }}</span>
                    </div>
                    <span
                        class="bg-primary/20 py-0.2 text-primary rounded-md px-1.5 font-mono text-[10px] font-bold"
                    >
                        {{ activeJobsCount }}
                    </span>
                </button>
            </div>

            <!-- Active User Profile Bento Card -->
            <div v-if="user">
                <!-- Collapsed Profile Button -->
                <Tooltip v-if="isCollapsed">
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            @click="handleLogout"
                            class="border-border/80 bg-card/60 hover:bg-muted mx-auto flex size-10.5 cursor-pointer items-center justify-center rounded-2xl border text-xl shadow-xs transition-all"
                        >
                            <span>
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
                        </button>
                    </TooltipTrigger>
                    <TooltipContent :side="isRTL ? 'left' : 'right'">
                        <div>
                            <p class="font-bold">{{ user.name }}</p>
                            <p class="text-muted-foreground text-[10px]">
                                {{
                                    t('auth.switch_profile') || 'Switch Profile'
                                }}
                            </p>
                        </div>
                    </TooltipContent>
                </Tooltip>

                <!-- Expanded Profile Card -->
                <div
                    v-else
                    class="cf-glass border-border/80 flex items-center gap-2.5 rounded-2xl border p-2.5"
                >
                    <div
                        class="from-primary/25 to-card border-primary/30 relative flex size-10 shrink-0 items-center justify-center rounded-xl border bg-gradient-to-br text-xl shadow-xs"
                    >
                        <span>
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
                    </div>
                    <div class="min-w-0 flex-1 leading-tight">
                        <div class="text-foreground truncate text-xs font-bold">
                            {{ user.name }}
                        </div>
                        <div class="mt-0.5 flex items-center gap-1.5">
                            <span
                                class="border-primary/25 bg-primary/15 py-0.2 text-primary rounded-full border px-1.5 font-mono text-[9px] font-extrabold uppercase"
                            >
                                {{
                                    isAdmin
                                        ? t('auth.admin_badge') || 'Admin'
                                        : t('users.viewer') || 'Viewer'
                                }}
                            </span>
                            <span
                                v-if="user.is_kids"
                                class="py-0.2 rounded-full border border-amber-500/25 bg-amber-500/15 px-1.5 font-mono text-[9px] font-extrabold text-amber-400 uppercase"
                            >
                                {{ t('auth.kids_badge') || 'Kids' }}
                            </span>
                        </div>
                    </div>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                @click="handleLogout"
                                class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 cursor-pointer rounded-lg p-1.5 transition-colors"
                            >
                                <LogOut class="size-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent :side="isRTL ? 'left' : 'right'">
                            <span>{{
                                t('auth.switch_profile') || 'Switch Profile'
                            }}</span>
                        </TooltipContent>
                    </Tooltip>
                </div>
            </div>

            <!-- Collapse Toggle Button at bottom of sidebar -->
            <button
                type="button"
                @click="emit('toggle-collapse')"
                class="text-muted-foreground hover:bg-muted/60 hover:text-foreground hidden w-full cursor-pointer items-center justify-center gap-2 rounded-xl py-1.5 text-xs font-semibold transition-colors lg:flex"
                :title="
                    isCollapsed
                        ? t('nav.expand_sidebar') || 'Expand Sidebar'
                        : t('nav.collapse_sidebar') || 'Collapse Sidebar'
                "
            >
                <component
                    :is="isCollapsed ? PanelLeftOpen : PanelLeftClose"
                    class="size-4 rtl:rotate-180"
                />
                <span v-if="!isCollapsed" class="text-[11px]">
                    {{ t('nav.collapse_sidebar') || 'Collapse' }}
                </span>
            </button>
        </div>
    </aside>
</template>
