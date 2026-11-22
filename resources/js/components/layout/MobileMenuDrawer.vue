<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useLocale } from '@/composables/useLocale';
import AppLogo from '@/components/common/AppLogo.vue';
import AccentColorPicker from '@/components/common/AccentColorPicker.vue';
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
    ChevronDown,
    LogOut,
    Globe,
    X,
} from '@lucide/vue';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const page = usePage();
const { t } = useI18n();
const { isRtl, switchLocale } = useLocale();

const user = computed(() => (page.props.auth as any)?.user);
const isAdmin = computed(() =>
    Boolean(user.value?.is_admin || user.value?.role === 'admin'),
);

interface NavDrawerItem {
    nameKey: string;
    href: string;
    icon: any;
    pattern: string;
    adminOnly?: boolean;
}

interface NavDrawerSection {
    id: string;
    labelKey: string;
    items: NavDrawerItem[];
    defaultOpen?: boolean;
    adminOnly?: boolean;
}

const navSections = computed<NavDrawerSection[]>(() => [
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
        items: [
            {
                nameKey: 'nav.scout',
                href: scoutRoute.url(),
                icon: Compass,
                pattern: '^/scout',
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

function isSectionOpen(section: NavDrawerSection): boolean {
    if (section.id in openMap) {
        return openMap[section.id];
    }
    return section.defaultOpen ?? true;
}

function toggleSection(section: NavDrawerSection): void {
    openMap[section.id] = !isSectionOpen(section);
}

function closeDrawer() {
    emit('update:open', false);
}

function handleLogout() {
    closeDrawer();
    router.post(logout.url());
}

function handleEscapeKey(event: KeyboardEvent) {
    if (event.key === 'Escape' && props.open) {
        closeDrawer();
    }
}

const isCurrentUrl = (pattern: string) => {
    if (typeof window !== 'undefined') {
        return new RegExp(pattern).test(window.location.pathname);
    }
    return false;
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handleEscapeKey);
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleEscapeKey);
    }
});
</script>

<template>
    <Teleport to="body">
        <div class="contents lg:hidden">
            <!-- Backdrop -->
            <Transition name="cf-drawer-backdrop">
                <div
                    v-if="props.open"
                    class="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm"
                    aria-hidden="true"
                    @click="closeDrawer"
                />
            </Transition>

            <!-- Slide-in Drawer -->
            <Transition :name="isRtl ? 'cf-drawer-rtl' : 'cf-drawer-ltr'">
                <aside
                    v-if="props.open"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="isRtl ? 'قائمة التنقل' : 'Navigation menu'"
                    :dir="isRtl ? 'rtl' : 'ltr'"
                    :class="[
                        'border-border/80 bg-card/95 fixed inset-y-0 z-50 flex w-[85%] max-w-sm flex-col justify-between overflow-hidden shadow-2xl backdrop-blur-2xl',
                        isRtl ? 'right-0 border-l' : 'left-0 border-r',
                    ]"
                >
                    <!-- Drawer Header: Brand & Profile Card -->
                    <div
                        class="border-border/70 from-primary/10 via-card to-card shrink-0 space-y-3 border-b bg-gradient-to-b p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <Link
                                :href="home.url()"
                                class="flex min-w-0 flex-1 items-center"
                                @click="closeDrawer"
                            >
                                <AppLogo size="sm" :show-text="true" />
                            </Link>
                            <button
                                type="button"
                                class="bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-xl transition-colors"
                                :title="isRtl ? 'إغلاق' : 'Close'"
                                :aria-label="isRtl ? 'إغلاق' : 'Close'"
                                @click="closeDrawer"
                            >
                                <X class="size-4" />
                            </button>
                        </div>

                        <!-- Active Profile Bento Card -->
                        <div
                            v-if="user"
                            class="border-border/80 bg-muted/40 flex items-center gap-3 rounded-2xl border p-3"
                        >
                            <div
                                class="from-primary/25 to-card border-primary/30 relative flex size-11 shrink-0 items-center justify-center rounded-2xl border bg-gradient-to-br text-2xl shadow-sm"
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
                                <div
                                    class="text-foreground truncate text-xs font-black"
                                >
                                    {{ user.name }}
                                </div>
                                <div class="mt-1 flex items-center gap-1.5">
                                    <span
                                        class="border-primary/25 bg-primary/15 text-primary rounded-full border px-2 py-0.5 font-mono text-[9px] font-extrabold uppercase"
                                    >
                                        {{
                                            isAdmin
                                                ? t('auth.admin_badge') ||
                                                  'Admin'
                                                : t('users.viewer') || 'Viewer'
                                        }}
                                    </span>
                                    <span
                                        v-if="user.is_kids"
                                        class="rounded-full border border-amber-500/25 bg-amber-500/15 px-2 py-0.5 font-mono text-[9px] font-extrabold text-amber-400 uppercase"
                                    >
                                        {{ t('auth.kids_badge') || 'Kids' }}
                                    </span>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="handleLogout"
                                class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded-xl p-2 transition-colors"
                                :title="
                                    t('auth.switch_profile') || 'Switch Profile'
                                "
                            >
                                <LogOut class="size-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Navigation Links with Section Collapse -->
                    <div class="flex-1 space-y-4 overflow-y-auto p-4">
                        <div
                            v-for="(section, sIdx) in navSections"
                            :key="section.id"
                            v-show="!section.adminOnly || isAdmin"
                            :class="
                                sIdx > 0 ? 'border-border/60 border-t pt-3' : ''
                            "
                        >
                            <button
                                type="button"
                                class="text-muted-foreground/85 hover:text-foreground mb-2 flex w-full cursor-pointer items-center justify-between rounded-lg px-2 py-1 text-start text-[10px] font-black tracking-wider uppercase transition-colors"
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

                            <nav
                                v-show="isSectionOpen(section)"
                                class="space-y-1.5"
                            >
                                <template
                                    v-for="item in section.items"
                                    :key="item.nameKey"
                                >
                                    <Link
                                        v-if="!item.adminOnly || isAdmin"
                                        :href="item.href"
                                        prefetch
                                        class="flex min-h-[44px] cursor-pointer items-center justify-between rounded-2xl border px-3 py-2 text-xs font-bold transition-all"
                                        :class="
                                            isCurrentUrl(item.pattern)
                                                ? 'border-primary bg-primary text-primary-foreground shadow-primary/25 font-black shadow-sm'
                                                : 'border-border/50 bg-card/60 text-foreground hover:border-border hover:bg-muted'
                                        "
                                        @click="closeDrawer"
                                    >
                                        <div
                                            class="flex min-w-0 items-center gap-2.5"
                                        >
                                            <div
                                                class="flex size-7 shrink-0 items-center justify-center rounded-xl transition-colors"
                                                :class="
                                                    isCurrentUrl(item.pattern)
                                                        ? 'bg-primary-foreground/20 text-primary-foreground'
                                                        : 'bg-primary/10 text-primary'
                                                "
                                            >
                                                <component
                                                    :is="item.icon"
                                                    class="size-3.5"
                                                />
                                            </div>
                                            <span class="truncate">{{
                                                t(item.nameKey)
                                            }}</span>
                                        </div>
                                        <span
                                            v-if="isCurrentUrl(item.pattern)"
                                            class="bg-primary-foreground size-2 shrink-0 rounded-full"
                                        />
                                    </Link>
                                </template>
                            </nav>
                        </div>
                    </div>

                    <!-- Drawer Footer: Accent Picker, Language & Logout -->
                    <div
                        class="border-border/80 bg-card/80 relative z-30 shrink-0 space-y-3 border-t p-4 backdrop-blur-md"
                    >
                        <div class="flex items-center justify-between px-1">
                            <span
                                class="text-muted-foreground text-[10px] font-bold"
                            >
                                {{ isRtl ? 'لون السمة:' : 'Brand Accent:' }}
                            </span>
                            <AccentColorPicker
                                :compact="true"
                                placement="top"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="border-border bg-background text-muted-foreground hover:text-foreground flex min-h-11 cursor-pointer items-center justify-center gap-1.5 rounded-xl border text-xs font-bold transition-colors active:scale-[0.98]"
                                @click="switchLocale()"
                            >
                                <Globe class="text-primary size-3.5" />
                                <span>{{ isRtl ? 'English' : 'عربي' }}</span>
                            </button>

                            <button
                                type="button"
                                class="bg-destructive/10 text-destructive hover:bg-destructive/20 flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-xl text-xs font-bold transition-colors active:scale-[0.98]"
                                @click="handleLogout"
                            >
                                <LogOut class="size-3.5" />
                                <span>{{
                                    isRtl ? 'تسجيل الخروج' : 'Logout'
                                }}</span>
                            </button>
                        </div>
                    </div>
                </aside>
            </Transition>
        </div>
    </Teleport>
</template>
