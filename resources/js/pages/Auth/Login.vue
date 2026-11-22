<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import { useLocale } from '@/composables/useLocale';
import AppLogo from '@/components/common/AppLogo.vue';
import AppAmbientBackground from '@/components/common/AppAmbientBackground.vue';
import PinPad from '@/components/auth/PinPad.vue';
import { Globe, Lock, ShieldCheck, Sparkles } from '@lucide/vue';

interface Profile {
    id: number;
    name: string;
    username: string;
    role: string;
    avatar: string;
    is_kids: boolean;
    max_content_rating: string;
    has_pin: boolean;
    pin_length?: number;
}

const { t } = useI18n();
const { locale, isRtl, direction, switchLocale } = useLocale();

const profiles = ref<Profile[]>([]);
const loadingProfiles = ref(true);
const selectedProfile = ref<Profile | null>(null);
const authenticating = ref(false);
const errorMessage = ref<string | null>(null);
const pinPadRef = ref<InstanceType<typeof PinPad> | null>(null);

const fetchProfiles = async () => {
    loadingProfiles.value = true;
    try {
        const res = await fetch('/api/auth/profiles', {
            headers: {
                Accept: 'application/json',
            },
        });
        const data = await res.json();
        profiles.value = data.profiles || [];
    } catch {
        profiles.value = [];
    } finally {
        loadingProfiles.value = false;
    }
};

const handleSelectProfile = (profile: Profile) => {
    errorMessage.value = null;
    if (profile.has_pin) {
        selectedProfile.value = profile;
    } else {
        // Instant 1-click login for PIN-free profiles
        submitPin(profile, '');
    }
};

const handleBackToProfiles = () => {
    selectedProfile.value = null;
    errorMessage.value = null;
};

const submitPin = async (profileOrPin: Profile | string, maybePin?: string) => {
    const profile =
        typeof profileOrPin === 'object' ? profileOrPin : selectedProfile.value;
    const pin =
        typeof profileOrPin === 'string' ? profileOrPin : (maybePin ?? '');

    if (!profile) return;

    authenticating.value = true;
    errorMessage.value = null;

    try {
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement | null
            )?.content || '';

        const res = await fetch('/api/auth/verify-pin', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                user_id: profile.id,
                pin,
            }),
        });

        const data = await res.json();

        if (res.ok && data.success) {
            router.visit(data.redirect || '/');
        } else {
            errorMessage.value =
                data.message ||
                t('auth.pin_error') ||
                'Incorrect PIN. Please try again.';
            if (pinPadRef.value) {
                pinPadRef.value.triggerShake();
            }
        }
    } catch {
        errorMessage.value =
            'Failed to authenticate. Please check server connection.';
        if (pinPadRef.value) {
            pinPadRef.value.triggerShake();
        }
    } finally {
        authenticating.value = false;
    }
};

const toggleLanguage = () => {
    switchLocale();
};

onMounted(() => {
    fetchProfiles();
});
</script>

<template>
    <div
        :dir="direction"
        :lang="locale"
        class="bg-background text-foreground selection:bg-primary selection:text-primary-foreground relative flex min-h-screen flex-col justify-between overflow-hidden font-sans"
    >
        <Head :title="t('auth.whos_watching') || 'Who is Watching?'" />

        <!-- Pure Cinema Ambient Backdrop -->
        <AppAmbientBackground />

        <!-- Top Header: Logo & Controls -->
        <header
            class="relative z-10 mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-4 sm:px-6 sm:py-6"
        >
            <AppLogo :animated="true" size="md" />

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="toggleLanguage"
                    class="cf-glass-elevated border-border/80 text-muted-foreground hover:text-foreground inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3.5 py-1.5 text-xs font-semibold shadow-xs transition-all"
                >
                    <Globe class="text-primary size-3.5" />
                    <span>{{ locale === 'en' ? 'العربية' : 'English' }}</span>
                </button>
            </div>
        </header>

        <!-- Main Content Area with Page Transition -->
        <main
            class="relative z-10 mx-auto flex w-full max-w-5xl flex-1 flex-col items-center justify-center px-4 py-8"
        >
            <Transition name="cf-page" mode="out-in">
                <!-- State 1: Profile Selector Matrix ("Who's Watching?") -->
                <div
                    v-if="!selectedProfile"
                    key="profiles"
                    class="flex w-full flex-col items-center"
                >
                    <div class="mb-6 text-center sm:mb-10">
                        <span
                            class="bg-primary/10 border-primary/25 text-primary mb-3 inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold tracking-wide"
                        >
                            <Sparkles class="size-3.5" />
                            <span>{{ t('app_name') }}</span>
                        </span>
                        <h1
                            class="text-foreground text-2xl font-black tracking-tight sm:text-3xl md:text-5xl"
                        >
                            {{ t('auth.whos_watching') }}
                        </h1>
                        <p
                            class="text-muted-foreground mx-auto mt-2 max-w-md text-sm"
                        >
                            {{ t('auth.select_profile') }}
                        </p>
                    </div>

                    <!-- Loading Skeletons -->
                    <div
                        v-if="loadingProfiles"
                        class="flex flex-wrap justify-center gap-3 sm:gap-6"
                    >
                        <div
                            v-for="i in 3"
                            :key="i"
                            class="cf-glass border-border/80 cf-shimmer flex h-40 w-28 flex-col items-center justify-center rounded-3xl border p-4 sm:h-48 sm:w-36"
                        >
                            <div class="bg-muted/60 mb-4 size-20 rounded-2xl" />
                            <div class="bg-muted/60 h-3.5 w-16 rounded" />
                        </div>
                    </div>

                    <!-- Profiles Matrix -->
                    <div
                        v-else
                        class="flex max-w-3xl flex-wrap items-center justify-center gap-4 sm:gap-6 md:gap-8"
                    >
                        <button
                            v-for="profile in profiles"
                            :key="profile.id"
                            type="button"
                            @click="handleSelectProfile(profile)"
                            class="group flex cursor-pointer flex-col items-center rounded-3xl p-3 transition-all duration-300 hover:scale-105 focus:outline-none active:scale-95"
                        >
                            <!-- Avatar Card with Dynamic Accent Glow -->
                            <div
                                class="relative flex size-24 items-center justify-center rounded-3xl p-1.5 transition-all duration-300 group-hover:shadow-[0_0_35px_color-mix(in_oklab,var(--primary)_35%,transparent)] sm:size-28 md:size-32"
                                :class="[
                                    profile.role === 'admin'
                                        ? 'from-primary/30 via-card to-primary/15 border-primary/50 group-hover:border-primary border-2 bg-gradient-to-br'
                                        : profile.is_kids
                                          ? 'via-card border-2 border-amber-500/50 bg-gradient-to-br from-amber-500/30 to-emerald-600/15 group-hover:border-amber-400'
                                          : 'cf-glass-elevated border-border/80 group-hover:border-primary/70 border-2',
                                ]"
                            >
                                <!-- Large Emoji Avatar Symbol -->
                                <span
                                    class="text-4xl transition-transform duration-300 group-hover:scale-110 sm:text-5xl md:text-6xl"
                                >
                                    {{
                                        profile.avatar === 'director'
                                            ? '🎬'
                                            : profile.avatar === 'robot'
                                              ? '🤖'
                                              : profile.avatar === 'clapper'
                                                ? '🎞️'
                                                : profile.avatar === 'star'
                                                  ? '⭐'
                                                  : profile.avatar === 'reel'
                                                    ? '🎥'
                                                    : '🍿'
                                    }}
                                </span>

                                <!-- Role Badges -->
                                <span
                                    v-if="profile.role === 'admin'"
                                    class="bg-primary text-primary-foreground absolute -top-2 rounded-full px-2.5 py-0.5 font-mono text-[10px] font-black tracking-wider uppercase shadow-md"
                                >
                                    {{ t('auth.admin_badge') || 'Admin' }}
                                </span>
                                <span
                                    v-else-if="profile.is_kids"
                                    class="absolute -top-2 rounded-full bg-amber-500 px-2.5 py-0.5 font-mono text-[10px] font-black tracking-wider text-slate-950 uppercase shadow-md"
                                >
                                    {{ t('auth.kids_badge') || 'Kids' }}
                                </span>

                                <!-- PIN Protected Lock Icon -->
                                <span
                                    v-if="profile.has_pin"
                                    class="bg-background/85 border-border/80 text-primary absolute right-2 bottom-2 rounded-full border p-1.5 shadow-md backdrop-blur-sm"
                                    :title="t('auth.pin_protected')"
                                >
                                    <Lock class="size-3.5" />
                                </span>
                            </div>

                            <!-- Name & Subtext -->
                            <span
                                class="text-foreground/90 group-hover:text-foreground mt-3 text-base font-bold tracking-tight transition-colors sm:text-lg"
                            >
                                {{ profile.name }}
                            </span>
                            <span
                                class="text-muted-foreground group-hover:text-primary text-[11px] font-medium transition-colors"
                            >
                                {{
                                    profile.has_pin
                                        ? t('auth.pin_protected')
                                        : t('auth.one_click_access')
                                }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- State 2: Dedicated PIN Pad View -->
                <div
                    v-else
                    key="pinpad"
                    class="flex w-full items-center justify-center"
                >
                    <PinPad
                        ref="pinPadRef"
                        :profile="selectedProfile"
                        :loading="authenticating"
                        :error-message="errorMessage"
                        @submit="(pin) => submitPin(selectedProfile!, pin)"
                        @back="handleBackToProfiles"
                    />
                </div>
            </Transition>
        </main>

        <!-- Footer Notice -->
        <footer
            class="text-muted-foreground border-border/60 relative z-10 mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-3 border-t px-6 py-6 text-center text-xs sm:flex-row"
        >
            <div class="flex items-center gap-2">
                <ShieldCheck class="text-primary size-4" />
                <span>{{ t('app_tagline') }}</span>
            </div>
            <div class="text-muted-foreground font-mono text-[11px]">
                LAN Streaming Server v3.5
            </div>
        </footer>
    </div>
</template>
