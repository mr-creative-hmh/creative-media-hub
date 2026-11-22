<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/components/layout/AppLayout.vue';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import {
    Users,
    Plus,
    Pencil,
    KeyRound,
    Trash2,
    Shield,
    Lock,
    Sparkles,
    AlertTriangle,
} from '@lucide/vue';

interface UserProfile {
    id: number;
    name: string;
    username: string;
    role: string;
    avatar: string;
    is_kids: boolean;
    max_content_rating: string;
    blocked_genres: string[];
    is_active: boolean;
    has_pin: boolean;
    pin_length?: number;
    watch_history_count?: number;
}

const props = withDefaults(
    defineProps<{
        initialUsers?: UserProfile[];
    }>(),
    {
        initialUsers: () => [],
    },
);

const { t } = useI18n();
const { success: toastSuccess, error: toastError } = useToast();

const users = ref<UserProfile[]>([...props.initialUsers]);
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showResetPinModal = ref(false);
const showDeleteModal = ref(false);
const selectedUser = ref<UserProfile | null>(null);
const userToDelete = ref<UserProfile | null>(null);
const saving = ref(false);

const availableAvatars = [
    { id: 'director', emoji: '🎬', label: 'Director' },
    { id: 'popcorn', emoji: '🍿', label: 'Popcorn' },
    { id: 'robot', emoji: '🤖', label: 'Robot' },
    { id: 'clapper', emoji: '🎞️', label: 'Clapper' },
    { id: 'star', emoji: '⭐', label: 'Star' },
    { id: 'reel', emoji: '🎥', label: 'Reel' },
];

const availableGenres = [
    'Action',
    'Adventure',
    'Animation',
    'Comedy',
    'Crime',
    'Documentary',
    'Drama',
    'Family',
    'Fantasy',
    'History',
    'Horror',
    'Music',
    'Mystery',
    'Romance',
    'Sci-Fi',
    'Thriller',
    'War',
    'Western',
];

// Form States
const form = ref({
    name: '',
    username: '',
    role: 'user',
    avatar: 'popcorn',
    is_kids: false,
    max_content_rating: 'ALL',
    blocked_genres: [] as string[],
    pin: '',
    pin_length: 4,
    is_active: true,
});

const newPin = ref('');

const getCsrfToken = (): string =>
    (
        document.querySelector(
            'meta[name="csrf-token"]',
        ) as HTMLMetaElement | null
    )?.content || '';

const openCreateModal = () => {
    form.value = {
        name: '',
        username: '',
        role: 'user',
        avatar: 'popcorn',
        is_kids: false,
        max_content_rating: 'ALL',
        blocked_genres: [],
        pin: '',
        pin_length: 4,
        is_active: true,
    };
    showCreateModal.value = true;
};

const openEditModal = (user: UserProfile) => {
    selectedUser.value = user;
    form.value = {
        name: user.name,
        username: user.username,
        role: user.role,
        avatar: user.avatar || 'popcorn',
        is_kids: user.is_kids,
        max_content_rating: user.max_content_rating || 'ALL',
        blocked_genres: [...(user.blocked_genres || [])],
        pin: '',
        pin_length: user.pin_length || 4,
        is_active: user.is_active ?? true,
    };
    showEditModal.value = true;
};

const openPinModal = (user: UserProfile) => {
    selectedUser.value = user;
    newPin.value = '';
    showResetPinModal.value = true;
};

const openDeleteModal = (user: UserProfile) => {
    userToDelete.value = user;
    showDeleteModal.value = true;
};

const toggleGenre = (genre: string) => {
    const idx = form.value.blocked_genres.indexOf(genre);
    if (idx >= 0) {
        form.value.blocked_genres.splice(idx, 1);
    } else {
        form.value.blocked_genres.push(genre);
    }
};

const handleKidsToggle = () => {
    if (form.value.is_kids) {
        form.value.max_content_rating = 'PG';
        if (!form.value.blocked_genres.includes('Horror')) {
            form.value.blocked_genres.push('Horror');
        }
    }
};

const handleCreateUser = async () => {
    if (!form.value.name || !form.value.username) {
        toastError('Name and Username are required.');
        return;
    }

    saving.value = true;
    try {
        const res = await fetch('/api/users', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(form.value),
        });

        const data = await res.json();
        if (res.ok && data.success) {
            users.value.push(data.user);
            showCreateModal.value = false;
            toastSuccess(
                data.message || t('users.saved_success') || 'Profile created.',
            );
        } else {
            toastError(data.message || 'Failed to create user.');
        }
    } catch {
        toastError('Network error creating user.');
    } finally {
        saving.value = false;
    }
};

const handleUpdateUser = async () => {
    if (!selectedUser.value) return;

    saving.value = true;
    try {
        const res = await fetch(`/api/users/${selectedUser.value.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(form.value),
        });

        const data = await res.json();
        if (res.ok && data.success) {
            const idx = users.value.findIndex(
                (u) => u.id === selectedUser.value!.id,
            );
            if (idx >= 0) {
                users.value[idx] = data.user;
            }
            showEditModal.value = false;
            toastSuccess(
                data.message || t('users.saved_success') || 'Profile updated.',
            );
        } else {
            toastError(data.message || 'Failed to update user.');
        }
    } catch {
        toastError('Network error updating user.');
    } finally {
        saving.value = false;
    }
};

const handleResetPin = async () => {
    if (!selectedUser.value) return;

    saving.value = true;
    try {
        const res = await fetch(
            `/api/users/${selectedUser.value.id}/reset-pin`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    pin: newPin.value || null,
                }),
            },
        );

        const data = await res.json();
        if (res.ok && data.success) {
            selectedUser.value.has_pin = data.has_pin;
            const idx = users.value.findIndex(
                (u) => u.id === selectedUser.value!.id,
            );
            if (idx >= 0) {
                users.value[idx].has_pin = data.has_pin;
            }
            showResetPinModal.value = false;
            toastSuccess(data.message || 'PIN updated successfully.');
        } else {
            toastError(data.message || 'Failed to update PIN.');
        }
    } catch {
        toastError('Network error resetting PIN.');
    } finally {
        saving.value = false;
    }
};

const handleDeleteUser = async () => {
    if (!userToDelete.value) return;

    saving.value = true;
    try {
        const res = await fetch(`/api/users/${userToDelete.value.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        const data = await res.json();
        if (res.ok && data.success) {
            users.value = users.value.filter(
                (u) => u.id !== userToDelete.value!.id,
            );
            showDeleteModal.value = false;
            userToDelete.value = null;
            toastSuccess(data.message || 'Profile deleted.');
        } else {
            toastError(data.message || 'Failed to delete profile.');
        }
    } catch {
        toastError('Network error deleting profile.');
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="t('users.title') || 'User Management Studio'" />

        <div
            class="animate-in fade-in mx-auto max-w-7xl space-y-8 px-4 py-6 duration-300 lg:px-8"
        >
            <!-- Header Banner -->
            <div
                class="border-border/80 flex flex-col items-start justify-between gap-4 border-b pb-6 sm:flex-row sm:items-center"
            >
                <div class="flex items-center gap-3.5">
                    <div
                        class="from-primary/20 to-primary/5 border-primary/30 text-primary shadow-primary/10 rounded-2xl border bg-gradient-to-br p-3 shadow-lg"
                    >
                        <Users class="size-6" />
                    </div>
                    <div>
                        <h1
                            class="text-foreground text-2xl font-black tracking-tight sm:text-3xl"
                        >
                            {{ t('users.title') }}
                        </h1>
                        <p
                            class="text-muted-foreground mt-1 text-xs sm:text-sm"
                        >
                            {{ t('users.subtitle') }}
                        </p>
                    </div>
                </div>

                <Button
                    variant="gradient"
                    size="default"
                    @click="openCreateModal"
                >
                    <Plus class="size-4" />
                    <span>{{ t('users.create_user') }}</span>
                </Button>
            </div>

            <!-- Profiles Grid -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="user in users"
                    :key="user.id"
                    class="group cf-glass-elevated border-border/80 hover:border-primary/40 relative flex flex-col justify-between rounded-3xl border p-5 shadow-xl transition-all duration-300"
                >
                    <!-- Top User Info -->
                    <div>
                        <div class="mb-4 flex items-start justify-between">
                            <!-- Avatar Card -->
                            <div
                                class="from-primary/25 to-card border-primary/30 relative flex size-16 items-center justify-center rounded-2xl border bg-gradient-to-br p-1 shadow-md"
                            >
                                <span class="text-3xl">
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
                                    v-if="user.role === 'admin'"
                                    class="py-0.2 bg-primary text-primary-foreground absolute -right-1 -bottom-1 rounded-md px-1.5 font-mono text-[9px] font-black uppercase shadow-sm"
                                >
                                    {{ t('auth.admin_badge') || 'Admin' }}
                                </span>
                            </div>

                            <!-- Badges -->
                            <div class="flex flex-col items-end gap-1.5">
                                <span
                                    class="rounded-full px-2.5 py-0.5 font-mono text-[10px] font-bold tracking-wider uppercase shadow-xs"
                                    :class="
                                        user.role === 'admin'
                                            ? 'bg-primary/15 text-primary border-primary/30 border'
                                            : 'bg-muted text-muted-foreground border-border/80 border'
                                    "
                                >
                                    {{
                                        user.role === 'admin'
                                            ? t('users.administrator')
                                            : t('users.viewer')
                                    }}
                                </span>

                                <span
                                    v-if="user.is_kids"
                                    class="rounded-full border border-amber-500/30 bg-amber-500/15 px-2 py-0.5 font-mono text-[10px] font-bold text-amber-400 uppercase"
                                >
                                    {{
                                        t('users.kids_profile_badge') ||
                                        'Kids Profile'
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Name & Username -->
                        <h3
                            class="text-foreground group-hover:text-primary text-lg font-bold tracking-tight transition-colors"
                        >
                            {{ user.name }}
                        </h3>
                        <p class="text-muted-foreground font-mono text-xs">
                            @{{ user.username }}
                        </p>

                        <!-- Security & Parental Specs -->
                        <div
                            class="border-border/80 mt-4 space-y-2 border-t pt-3 text-xs"
                        >
                            <!-- PIN Status -->
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-muted-foreground flex items-center gap-1.5"
                                >
                                    <Lock class="text-primary size-3.5" />
                                    <span>{{ t('users.pin_auth') }}</span>
                                </span>
                                <span
                                    class="text-[11px] font-semibold"
                                    :class="
                                        user.has_pin
                                            ? 'text-primary'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{
                                        user.has_pin
                                            ? t('users.pin_protected')
                                            : t('users.one_click_access')
                                    }}
                                </span>
                            </div>

                            <!-- Maturity Rating Cap -->
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-muted-foreground flex items-center gap-1.5"
                                >
                                    <Shield class="size-3.5 text-amber-400" />
                                    <span>{{
                                        t('users.max_rating_badge')
                                    }}</span>
                                </span>
                                <span
                                    class="bg-muted rounded px-1.5 py-0.5 font-mono text-[10px] font-bold text-amber-300"
                                >
                                    {{ user.max_content_rating || 'ALL' }}
                                </span>
                            </div>

                            <!-- Blocked Genres -->
                            <div
                                v-if="
                                    user.blocked_genres &&
                                    user.blocked_genres.length > 0
                                "
                                class="pt-1"
                            >
                                <span
                                    class="text-muted-foreground mb-1 block text-[11px]"
                                >
                                    {{ t('users.blocked_genres_title') }}:
                                </span>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="bg in user.blocked_genres"
                                        :key="bg"
                                        class="py-0.2 bg-destructive/15 text-destructive border-destructive/25 rounded border px-1.5 text-[10px]"
                                    >
                                        {{ bg }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Controls -->
                    <div
                        class="border-border/80 mt-6 flex items-center justify-between gap-2 border-t pt-4"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            class="flex-1"
                            @click="openPinModal(user)"
                            :title="t('users.reset_pin')"
                        >
                            <KeyRound class="text-primary size-3.5" />
                            <span>PIN</span>
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            class="flex-1"
                            @click="openEditModal(user)"
                            :title="t('users.edit_profile')"
                        >
                            <Pencil class="size-3.5 text-amber-400" />
                            <span>{{ t('users.edit_profile') }}</span>
                        </Button>

                        <Button
                            variant="destructive"
                            size="icon-sm"
                            @click="openDeleteModal(user)"
                            :title="t('users.delete_profile')"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Create / Edit Profile Modal (Reka UI Dialog) -->
            <Dialog
                :open="showCreateModal || showEditModal"
                @update:open="
                    (val) => {
                        if (!val) {
                            showCreateModal = false;
                            showEditModal = false;
                        }
                    }
                "
            >
                <DialogContent size="lg">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                showCreateModal
                                    ? t('users.create_user')
                                    : t('users.edit_user')
                            }}
                        </DialogTitle>
                        <DialogDescription
                            class="text-muted-foreground text-xs"
                        >
                            {{ t('users.subtitle') }}
                        </DialogDescription>
                    </DialogHeader>

                    <!-- Form Inputs -->
                    <div class="space-y-4 py-2">
                        <!-- Avatar Selection -->
                        <div>
                            <label
                                class="text-muted-foreground mb-2 block text-xs font-bold tracking-wider uppercase"
                            >
                                {{ t('users.avatar_label') }}
                            </label>
                            <div class="flex flex-wrap gap-2.5">
                                <button
                                    v-for="av in availableAvatars"
                                    :key="av.id"
                                    type="button"
                                    @click="form.avatar = av.id"
                                    class="flex size-12 cursor-pointer items-center justify-center rounded-2xl border text-2xl transition-all"
                                    :class="
                                        form.avatar === av.id
                                            ? 'bg-primary/20 border-primary scale-105 shadow-md'
                                            : 'bg-card border-border/80 hover:border-primary/50'
                                    "
                                >
                                    {{ av.emoji }}
                                </button>
                            </div>
                        </div>

                        <!-- Name & Username -->
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label
                                    class="text-muted-foreground mb-1 block text-xs font-bold tracking-wider uppercase"
                                >
                                    {{ t('users.name_label') }}
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    placeholder="e.g. Cinema Admin"
                                    class="cf-field w-full rounded-xl px-3.5 py-2.5 text-sm"
                                />
                            </div>

                            <div>
                                <label
                                    class="text-muted-foreground mb-1 block text-xs font-bold tracking-wider uppercase"
                                >
                                    {{ t('users.username_label') }}
                                </label>
                                <input
                                    v-model="form.username"
                                    type="text"
                                    placeholder="e.g. admin"
                                    class="cf-field w-full rounded-xl px-3.5 py-2.5 font-mono text-sm"
                                />
                            </div>
                        </div>

                        <!-- Role Selection -->
                        <div>
                            <label
                                class="text-muted-foreground mb-1 block text-xs font-bold tracking-wider uppercase"
                            >
                                {{ t('users.role_label') }}
                            </label>
                            <select
                                v-model="form.role"
                                class="cf-field w-full rounded-xl px-3.5 py-2.5 text-sm"
                            >
                                <option value="user">
                                    {{ t('users.role_user') }}
                                </option>
                                <option value="admin">
                                    {{ t('users.role_admin') }}
                                </option>
                            </select>
                        </div>

                        <!-- PIN if creating -->
                        <div v-if="showCreateModal">
                            <label
                                class="text-muted-foreground mb-1 block text-xs font-bold tracking-wider uppercase"
                            >
                                {{ t('users.pin_label') }}
                            </label>
                            <input
                                v-model="form.pin"
                                type="password"
                                maxlength="8"
                                placeholder="e.g. 1234 (Leave blank for 1-click access)"
                                class="cf-field w-full rounded-xl px-3.5 py-2.5 font-mono text-sm"
                            />
                        </div>

                        <!-- Parental Controls Section -->
                        <div class="border-border/80 border-t pt-2">
                            <div class="mb-3 flex items-center justify-between">
                                <div>
                                    <span
                                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-amber-400 uppercase"
                                    >
                                        <Shield class="size-3.5" />
                                        <span>{{
                                            t('users.parental_controls')
                                        }}</span>
                                    </span>
                                    <p
                                        class="text-muted-foreground text-[11px]"
                                    >
                                        {{ t('users.parental_controls_desc') }}
                                    </p>
                                </div>

                                <label
                                    class="flex cursor-pointer items-center gap-2"
                                >
                                    <input
                                        v-model="form.is_kids"
                                        type="checkbox"
                                        @change="handleKidsToggle"
                                        class="text-primary bg-card border-border/80 size-4 rounded"
                                    />
                                    <span
                                        class="text-foreground text-xs font-bold"
                                    >
                                        {{ t('users.kids_mode_label') }}
                                    </span>
                                </label>
                            </div>

                            <!-- Rating Cap -->
                            <div class="mb-3">
                                <label
                                    class="text-muted-foreground mb-1 block text-xs font-medium"
                                >
                                    {{ t('users.max_rating_label') }}
                                </label>
                                <select
                                    v-model="form.max_content_rating"
                                    class="cf-field w-full rounded-xl px-3.5 py-2.5 text-sm"
                                >
                                    <option value="ALL">
                                        {{ t('users.rating_all') }}
                                    </option>
                                    <option value="G">
                                        {{ t('users.rating_g') }}
                                    </option>
                                    <option value="PG">
                                        {{ t('users.rating_pg') }}
                                    </option>
                                    <option value="PG-13">
                                        {{ t('users.rating_pg13') }}
                                    </option>
                                    <option value="R">
                                        {{ t('users.rating_r') }}
                                    </option>
                                </select>
                            </div>

                            <!-- Blocked Genres Chips -->
                            <div>
                                <label
                                    class="text-muted-foreground mb-1.5 block text-xs font-medium"
                                >
                                    {{ t('users.blocked_genres_label') }}
                                </label>
                                <div
                                    class="bg-background/60 border-border/80 flex max-h-28 flex-wrap gap-1.5 overflow-y-auto rounded-xl border p-2"
                                >
                                    <button
                                        v-for="genre in availableGenres"
                                        :key="genre"
                                        type="button"
                                        @click="toggleGenre(genre)"
                                        class="cursor-pointer rounded-lg px-2.5 py-1 text-xs font-medium transition-all"
                                        :class="
                                            form.blocked_genres.includes(genre)
                                                ? 'bg-destructive/15 text-destructive border-destructive/30 border'
                                                : 'bg-card text-muted-foreground border-border/80 hover:text-foreground border'
                                        "
                                    >
                                        {{ genre }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <DialogFooter>
                        <Button
                            variant="outline"
                            @click="
                                showCreateModal = false;
                                showEditModal = false;
                            "
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="gradient"
                            :disabled="saving"
                            @click="
                                showCreateModal
                                    ? handleCreateUser()
                                    : handleUpdateUser()
                            "
                        >
                            {{
                                saving
                                    ? t('users.saving')
                                    : t('users.save_profile')
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Reset PIN Modal (Reka UI Dialog) -->
            <Dialog
                :open="showResetPinModal"
                @update:open="(val) => (showResetPinModal = val)"
            >
                <DialogContent size="sm">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <KeyRound class="text-primary size-4" />
                            <span>{{
                                t('users.reset_pin_title', {
                                    name: selectedUser?.name ?? '',
                                })
                            }}</span>
                        </DialogTitle>
                        <DialogDescription
                            class="text-muted-foreground text-xs"
                        >
                            {{ t('users.new_pin_hint') }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="py-2">
                        <label
                            class="text-muted-foreground mb-1 block text-xs font-medium"
                        >
                            {{ t('users.new_pin_label') }}
                        </label>
                        <input
                            v-model="newPin"
                            type="password"
                            maxlength="8"
                            :placeholder="t('users.new_pin_placeholder')"
                            class="cf-field w-full rounded-xl px-3.5 py-2.5 font-mono text-sm"
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            @click="showResetPinModal = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="default"
                            :disabled="saving"
                            @click="handleResetPin"
                        >
                            {{
                                saving
                                    ? t('users.updating')
                                    : t('users.update_pin')
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete Confirmation Modal (Reka UI Dialog) -->
            <Dialog
                :open="showDeleteModal"
                @update:open="(val) => (showDeleteModal = val)"
            >
                <DialogContent size="sm">
                    <DialogHeader>
                        <DialogTitle
                            class="text-destructive flex items-center gap-2"
                        >
                            <AlertTriangle class="size-4" />
                            <span>{{ t('users.delete_user') }}</span>
                        </DialogTitle>
                        <DialogDescription
                            class="text-muted-foreground text-xs"
                        >
                            {{
                                t('users.delete_confirm_named', {
                                    name: userToDelete?.name ?? '',
                                }) ||
                                'Are you sure you want to delete this profile? This action cannot be undone.'
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            @click="showDeleteModal = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            :disabled="saving"
                            @click="handleDeleteUser"
                        >
                            {{ t('common.delete') || 'Delete' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
