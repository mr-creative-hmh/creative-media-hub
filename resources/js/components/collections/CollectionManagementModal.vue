<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    X,
    Layers,
    Search,
    Sparkles,
    RefreshCw,
    Trash2,
    Unlink,
    Plus,
    Film,
    CheckCircle2,
    Clock,
    SlidersHorizontal,
    AlertCircle,
    ExternalLink,
    Edit3,
    Check,
    Calendar,
    Star,
    ArrowRight,
    ArrowLeft,
} from '@lucide/vue';

const props = defineProps<{
    show: boolean;
    initialCollectionSlug?: string;
}>();

const emit = defineEmits(['close', 'changed']);

const { t, isRTL } = useI18n();

interface CollectionMovie {
    id: number;
    title: string;
    title_ar?: string;
    release_year?: number;
    rating?: number;
    poster_path?: string;
    resolution?: string;
    collection_id?: number;
    collection_id_source?: string;
    file_path?: string;
    folder_path?: string;
}

interface CollectionOverview {
    name: string;
    slug: string;
    collection_id?: number;
    source?: string;
    poster_path?: string;
    backdrop_path?: string;
    year_span?: string;
    movies_count: number;
    total_parts: number;
    is_complete: boolean;
    completion_percentage: number;
    movies: CollectionMovie[];
}

const collections = ref<CollectionOverview[]>([]);
const stats = ref<any>({});
const isLoading = ref(false);
const searchQuery = ref('');
const activeFilter = ref<'all' | 'complete' | 'incomplete'>('all');
const selectedCollection = ref<CollectionOverview | null>(null);

// Search & Add Movie State
const movieSearchQuery = ref('');
const movieSearchResults = ref<any[]>([]);
const isSearchingMovies = ref(false);
const isAddingMovie = ref(false);

// Edit Collection Metadata State
const isEditingMetadata = ref(false);
const editName = ref('');
const editTmdbId = ref('');
const isSavingMetadata = ref(false);

// TMDb Sync State
const isSyncingTmdb = ref(false);
const syncResult = ref<any>(null);
const actionFeedback = ref<{
    type: 'success' | 'error';
    message: string;
} | null>(null);

const filteredCollections = computed(() => {
    let list = collections.value;
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(
            (c) =>
                c.name.toLowerCase().includes(q) ||
                c.movies.some((m) => m.title.toLowerCase().includes(q)),
        );
    }
    if (activeFilter.value === 'complete') {
        list = list.filter((c) => c.is_complete);
    } else if (activeFilter.value === 'incomplete') {
        list = list.filter((c) => !c.is_complete);
    }
    return list;
});

const fetchOverview = async () => {
    isLoading.value = true;
    try {
        const res = await fetch('/api/collections/management/overview');
        if (res.ok) {
            const data = await res.json();
            collections.value = data.collections || [];
            stats.value = data.stats || {};

            // Auto-select collection if specified or preserve current selection
            if (props.initialCollectionSlug) {
                const found = collections.value.find(
                    (c) => c.slug === props.initialCollectionSlug,
                );
                if (found) selectedCollection.value = found;
            } else if (selectedCollection.value) {
                const found = collections.value.find(
                    (c) => c.name === selectedCollection.value?.name,
                );
                selectedCollection.value =
                    found || collections.value[0] || null;
            } else if (collections.value.length > 0) {
                selectedCollection.value = collections.value[0];
            }
        }
    } catch (e) {
        console.error('Failed to load collections overview', e);
    } finally {
        isLoading.value = false;
    }
};

const selectCollection = (col: CollectionOverview) => {
    selectedCollection.value = col;
    isEditingMetadata.value = false;
    movieSearchQuery.value = '';
    movieSearchResults.value = [];
    actionFeedback.value = null;
};

// 1-Click Detach Movie
const detachMovie = async (movie: CollectionMovie) => {
    if (
        !confirm(
            isRTL.value
                ? `هل أنت متأكد من إزالة فيلم "${movie.title}" من هذه السلسلة؟`
                : `Remove "${movie.title}" from this collection?`,
        )
    ) {
        return;
    }

    try {
        const res = await fetch('/api/collections/management/detach-movie', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
            body: JSON.stringify({ media_item_id: movie.id }),
        });

        if (res.ok) {
            const data = await res.json();
            actionFeedback.value = { type: 'success', message: data.message };

            // Optimistic update in selected collection
            if (selectedCollection.value) {
                selectedCollection.value.movies =
                    selectedCollection.value.movies.filter(
                        (m) => m.id !== movie.id,
                    );
                selectedCollection.value.movies_count =
                    selectedCollection.value.movies.length;
            }

            emit('changed');
            fetchOverview();
        }
    } catch (e) {
        actionFeedback.value = {
            type: 'error',
            message: isRTL.value
                ? 'فشل فك ارتباط الفيلم.'
                : 'Failed to detach movie.',
        };
    }
};

// Live Search Movies in Library to Add
watch(movieSearchQuery, async (newQuery) => {
    if (!newQuery || newQuery.trim().length < 2) {
        movieSearchResults.value = [];
        return;
    }
    isSearchingMovies.value = true;
    try {
        const res = await fetch(
            `/api/collections/management/search-movies?q=${encodeURIComponent(newQuery.trim())}`,
        );
        if (res.ok) {
            const data = await res.json();
            movieSearchResults.value = data.movies || [];
        }
    } catch (e) {
        console.error(e);
    } finally {
        isSearchingMovies.value = false;
    }
});

// Assign Movie to Collection
const assignMovieToCollection = async (movie: any) => {
    if (!selectedCollection.value) return;
    isAddingMovie.value = true;

    try {
        const res = await fetch('/api/collections/management/assign-movie', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
            body: JSON.stringify({
                media_item_id: movie.id,
                collection_name: selectedCollection.value.name,
                collection_id: selectedCollection.value.collection_id,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            actionFeedback.value = { type: 'success', message: data.message };
            movieSearchQuery.value = '';
            movieSearchResults.value = [];
            emit('changed');
            await fetchOverview();
        }
    } catch (e) {
        actionFeedback.value = {
            type: 'error',
            message: isRTL.value
                ? 'فشل إضافة الفيلم للسلسلة.'
                : 'Failed to add movie.',
        };
    } finally {
        isAddingMovie.value = false;
    }
};

// Start Editing Collection Info
const startEditing = () => {
    if (!selectedCollection.value) return;
    editName.value = selectedCollection.value.name;
    editTmdbId.value = selectedCollection.value.collection_id
        ? String(selectedCollection.value.collection_id)
        : '';
    isEditingMetadata.value = true;
};

// Save Collection Info
const saveCollectionMetadata = async () => {
    if (!selectedCollection.value || !editName.value.trim()) return;
    isSavingMetadata.value = true;

    try {
        const res = await fetch(
            '/api/collections/management/update-collection',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as any
                        )?.content || '',
                },
                body: JSON.stringify({
                    current_name: selectedCollection.value.name,
                    new_name: editName.value.trim(),
                    collection_id: editTmdbId.value.trim()
                        ? Number(editTmdbId.value.trim())
                        : null,
                }),
            },
        );

        if (res.ok) {
            const data = await res.json();
            actionFeedback.value = { type: 'success', message: data.message };
            isEditingMetadata.value = false;
            emit('changed');
            await fetchOverview();
        }
    } catch (e) {
        actionFeedback.value = {
            type: 'error',
            message: isRTL.value
                ? 'فشل تحديث بيانات السلسلة.'
                : 'Failed to update collection.',
        };
    } finally {
        isSavingMetadata.value = false;
    }
};

// 1-Click Sync TMDb Collections
const syncAllWithTmdb = async () => {
    if (isSyncingTmdb.value) return;
    isSyncingTmdb.value = true;
    syncResult.value = null;
    actionFeedback.value = null;

    try {
        const res = await fetch('/api/collections/management/sync-tmdb', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as any)
                        ?.content || '',
            },
            body: JSON.stringify({ force: false }),
        });

        if (res.ok) {
            const data = await res.json();
            syncResult.value = data;
            actionFeedback.value = { type: 'success', message: data.message };
            emit('changed');
            await fetchOverview();
        }
    } catch (e) {
        actionFeedback.value = {
            type: 'error',
            message: isRTL.value
                ? 'فشلت عملية المزامنة مع TMDb.'
                : 'TMDb sync failed.',
        };
    } finally {
        isSyncingTmdb.value = false;
    }
};

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            fetchOverview();
        }
    },
);

onMounted(() => {
    if (props.show) {
        fetchOverview();
    }
});
</script>

<template>
    <div
        v-if="show"
        class="animate-fadeIn fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-3 backdrop-blur-md sm:p-6"
        @click.self="emit('close')"
    >
        <div
            class="glass-panel relative flex h-[92vh] max-h-[900px] w-full max-w-6xl flex-col overflow-hidden rounded-3xl border border-cyan-500/20 bg-gradient-to-br from-slate-950 via-slate-900/95 to-slate-950 shadow-2xl"
        >
            <!-- Modal Header -->
            <div
                class="flex shrink-0 flex-wrap items-center justify-between gap-4 border-b border-white/10 bg-white/[0.02] p-4 sm:p-6"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-2xl border border-cyan-500/30 bg-cyan-500/20 text-cyan-400"
                    >
                        <Layers class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2
                                class="text-lg font-black tracking-tight text-white sm:text-xl"
                            >
                                {{
                                    isRTL
                                        ? 'استوديو إدارة وتدقيق سلاسل الأفلام'
                                        : 'Franchise & Collection Studio'
                                }}
                            </h2>
                            <span
                                class="rounded-md border border-cyan-500/30 bg-cyan-500/20 px-2 py-0.5 text-[10px] font-bold tracking-wider text-cyan-300 uppercase"
                            >
                                Permanent Fix
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">
                            {{
                                isRTL
                                    ? 'إدارة أفلام السلاسل، فك الارتباط الخاطئ، وتنسيق الأجزاء مع قاعدة بيانات TMDb الرسمية.'
                                    : 'Manage collection members, unlink wrong movies, and synchronize franchises with official TMDb collections.'
                            }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <!-- TMDb Sync Button -->
                    <button
                        @click="syncAllWithTmdb"
                        :disabled="isSyncingTmdb"
                        class="flex cursor-pointer items-center gap-2 rounded-xl bg-cyan-500 px-3.5 py-2 text-xs font-black text-slate-950 shadow-lg shadow-cyan-500/20 transition-all hover:bg-cyan-400 disabled:opacity-50"
                        :title="
                            isRTL
                                ? 'مزامنة وتدقيق جميع السلاسل مع TMDb'
                                : 'Sync all collections with TMDb'
                        "
                    >
                        <RefreshCw
                            class="h-3.5 w-3.5"
                            :class="{ 'animate-spin': isSyncingTmdb }"
                        />
                        <span>{{
                            isSyncingTmdb
                                ? isRTL
                                    ? 'جارٍ المزامنة...'
                                    : 'Syncing...'
                                : isRTL
                                  ? 'مزامنة مع TMDb'
                                  : 'Sync All TMDb'
                        }}</span>
                    </button>

                    <!-- Close Button -->
                    <button
                        @click="emit('close')"
                        class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-xl bg-white/10 text-slate-300 transition-all hover:bg-white/20 hover:text-white"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Feedback Banner -->
            <div
                v-if="actionFeedback"
                class="flex items-center justify-between border-b px-6 py-2 text-xs font-semibold transition-all"
                :class="
                    actionFeedback.type === 'success'
                        ? 'border-emerald-500/30 bg-emerald-950/70 text-emerald-300'
                        : 'border-red-500/30 bg-red-950/70 text-red-300'
                "
            >
                <div class="flex items-center gap-2">
                    <CheckCircle2
                        v-if="actionFeedback.type === 'success'"
                        class="h-4 w-4 text-emerald-400"
                    />
                    <AlertCircle v-else class="h-4 w-4 text-red-400" />
                    <span>{{ actionFeedback.message }}</span>
                </div>
                <button
                    @click="actionFeedback = null"
                    class="cursor-pointer text-xs opacity-70 hover:opacity-100"
                >
                    &times;
                </button>
            </div>

            <!-- Modal Body Two-Column Grid -->
            <div class="flex flex-1 flex-col overflow-hidden md:flex-row">
                <!-- LEFT PANEL: Collections List -->
                <div
                    class="flex w-full shrink-0 flex-col border-b border-white/10 bg-slate-950/40 md:w-80 md:border-r md:border-b-0 lg:w-96"
                >
                    <!-- Search & Filter Controls -->
                    <div class="space-y-2.5 border-b border-white/10 p-3.5">
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                                :class="isRTL ? 'right-3' : 'left-3'"
                            />
                            <input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="
                                    isRTL
                                        ? 'ابحث في السلاسل...'
                                        : 'Search collections...'
                                "
                                class="w-full rounded-xl border border-white/10 bg-black/40 py-1.5 text-xs text-white placeholder-slate-400 focus:border-cyan-400 focus:outline-none"
                                :class="isRTL ? 'pr-8 pl-3' : 'pr-3 pl-8'"
                            />
                        </div>

                        <!-- Filter Tabs -->
                        <div
                            class="grid grid-cols-3 gap-1 rounded-xl border border-white/5 bg-black/40 p-1 text-[11px] font-bold"
                        >
                            <button
                                @click="activeFilter = 'all'"
                                class="cursor-pointer rounded-lg py-1 text-center transition-all"
                                :class="
                                    activeFilter === 'all'
                                        ? 'bg-cyan-500/20 font-black text-cyan-300'
                                        : 'text-slate-400 hover:text-white'
                                "
                            >
                                {{ isRTL ? 'الكل' : 'All' }} ({{
                                    collections.length
                                }})
                            </button>
                            <button
                                @click="activeFilter = 'complete'"
                                class="cursor-pointer rounded-lg py-1 text-center transition-all"
                                :class="
                                    activeFilter === 'complete'
                                        ? 'bg-emerald-500/20 font-black text-emerald-300'
                                        : 'text-slate-400 hover:text-white'
                                "
                            >
                                {{ isRTL ? 'مكتملة' : 'Complete' }}
                            </button>
                            <button
                                @click="activeFilter = 'incomplete'"
                                class="cursor-pointer rounded-lg py-1 text-center transition-all"
                                :class="
                                    activeFilter === 'incomplete'
                                        ? 'bg-amber-500/20 font-black text-amber-300'
                                        : 'text-slate-400 hover:text-white'
                                "
                            >
                                {{ isRTL ? 'ناقصة' : 'Gaps' }}
                            </button>
                        </div>
                    </div>

                    <!-- Scrollable Collections List -->
                    <div
                        class="custom-scrollbar flex-1 space-y-1.5 overflow-y-auto p-2"
                    >
                        <div
                            v-for="col in filteredCollections"
                            :key="col.slug"
                            @click="selectCollection(col)"
                            class="group flex cursor-pointer items-center gap-3 rounded-2xl border p-2.5 transition-all"
                            :class="
                                selectedCollection?.slug === col.slug
                                    ? 'border-cyan-500/50 bg-cyan-950/40 shadow-md'
                                    : 'border-white/5 bg-white/[0.02] hover:border-white/10 hover:bg-white/[0.06]'
                            "
                        >
                            <!-- Mini Poster -->
                            <div
                                class="h-14 w-10 shrink-0 overflow-hidden rounded-lg border border-white/10 bg-slate-900"
                            >
                                <img
                                    v-if="col.poster_path"
                                    :src="col.poster_path"
                                    :alt="col.name"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-slate-700"
                                >
                                    <Film class="h-4 w-4" />
                                </div>
                            </div>

                            <!-- Meta -->
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex items-center justify-between gap-1"
                                >
                                    <h4
                                        class="truncate text-xs font-bold text-white transition-colors group-hover:text-cyan-400"
                                    >
                                        {{ col.name }}
                                    </h4>
                                    <span
                                        v-if="col.is_complete"
                                        class="h-2 w-2 shrink-0 rounded-full bg-emerald-400"
                                        :title="isRTL ? 'مكتملة' : 'Complete'"
                                    ></span>
                                </div>
                                <div
                                    class="mt-1 flex items-center gap-2 text-[10px] text-slate-400"
                                >
                                    <span
                                        >{{ col.movies_count }}
                                        {{ isRTL ? 'أفلام' : 'films' }}</span
                                    >
                                    <span
                                        v-if="col.collection_id"
                                        class="font-mono text-cyan-400/80"
                                        >#{{ col.collection_id }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="filteredCollections.length === 0"
                            class="p-8 text-center text-xs text-slate-500"
                        >
                            {{
                                isRTL
                                    ? 'لم يتم العثور على سلاسل مطابقة.'
                                    : 'No matching collections found.'
                            }}
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL: Selected Collection Management -->
                <div
                    v-if="selectedCollection"
                    class="custom-scrollbar flex flex-1 flex-col space-y-6 overflow-y-auto p-4 sm:p-6"
                >
                    <!-- Collection Header & Info Banner -->
                    <div
                        class="flex flex-col items-start justify-between gap-6 rounded-3xl border border-white/10 bg-white/[0.03] p-5 sm:flex-row sm:items-center"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="h-24 w-16 shrink-0 overflow-hidden rounded-xl border border-white/10 bg-slate-900 shadow-lg"
                            >
                                <img
                                    v-if="selectedCollection.poster_path"
                                    :src="selectedCollection.poster_path"
                                    :alt="selectedCollection.name"
                                    class="h-full w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-slate-700"
                                >
                                    <Film class="h-6 w-6" />
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-xl font-black text-white">
                                        {{ selectedCollection.name }}
                                    </h3>
                                    <span
                                        v-if="selectedCollection.collection_id"
                                        class="rounded-md border border-cyan-500/30 bg-cyan-500/20 px-2 py-0.5 font-mono text-[10px] font-bold text-cyan-300"
                                    >
                                        TMDb #{{
                                            selectedCollection.collection_id
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="flex items-center gap-3 text-xs text-slate-300"
                                >
                                    <span class="font-bold text-cyan-400"
                                        >{{ selectedCollection.movies_count }}
                                        {{
                                            isRTL ? 'أفلام مملوكة' : 'Owned'
                                        }}</span
                                    >
                                    <span class="text-slate-500">•</span>
                                    <span v-if="selectedCollection.year_span">{{
                                        selectedCollection.year_span
                                    }}</span>
                                    <span class="text-slate-500">•</span>
                                    <span
                                        :class="
                                            selectedCollection.is_complete
                                                ? 'font-bold text-emerald-400'
                                                : 'font-bold text-amber-400'
                                        "
                                    >
                                        {{
                                            selectedCollection.is_complete
                                                ? isRTL
                                                    ? 'مكتملة 100%'
                                                    : '100% Complete'
                                                : `${selectedCollection.completion_percentage}% Owned`
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-2">
                            <a
                                :href="`/collections/${selectedCollection.slug}`"
                                target="_blank"
                                class="flex items-center gap-1.5 rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-bold text-slate-300 transition-all hover:bg-white/10"
                            >
                                <span>{{
                                    isRTL ? 'عرض الصفحة' : 'View Page'
                                }}</span>
                                <ExternalLink class="h-3 w-3" />
                            </a>
                            <button
                                @click="
                                    isEditingMetadata = !isEditingMetadata;
                                    startEditing();
                                "
                                class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-cyan-500/30 bg-cyan-500/20 px-3 py-1.5 text-xs font-bold text-cyan-300 transition-all hover:bg-cyan-500/30"
                            >
                                <Edit3 class="h-3 w-3" />
                                <span>{{
                                    isRTL ? 'تعديل البيانات' : 'Edit Info'
                                }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Metadata Edit Section (Collapsible) -->
                    <div
                        v-if="isEditingMetadata"
                        class="space-y-3 rounded-2xl border border-cyan-500/30 bg-cyan-950/20 p-4"
                    >
                        <h4
                            class="text-xs font-black tracking-wider text-cyan-300 uppercase"
                        >
                            {{
                                isRTL
                                    ? 'تعديل اسم السلسلة ومعرف TMDb'
                                    : 'Edit Collection Metadata'
                            }}
                        </h4>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-[10px] font-bold text-slate-400 uppercase"
                                    >{{
                                        isRTL
                                            ? 'اسم السلسلة'
                                            : 'Collection Name'
                                    }}</label
                                >
                                <input
                                    v-model="editName"
                                    type="text"
                                    class="w-full rounded-xl border border-white/15 bg-black/50 px-3 py-2 text-xs text-white focus:border-cyan-400 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-[10px] font-bold text-slate-400 uppercase"
                                    >{{
                                        isRTL
                                            ? 'معرف TMDb Collection ID'
                                            : 'TMDb Collection ID'
                                    }}</label
                                >
                                <input
                                    v-model="editTmdbId"
                                    type="text"
                                    placeholder="e.g. 1769700"
                                    class="w-full rounded-xl border border-white/15 bg-black/50 px-3 py-2 font-mono text-xs text-white focus:border-cyan-400 focus:outline-none"
                                />
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button
                                @click="isEditingMetadata = false"
                                class="cursor-pointer rounded-xl bg-white/10 px-3 py-1.5 text-xs font-bold text-slate-300 transition-all hover:bg-white/20"
                            >
                                {{ isRTL ? 'إلغاء' : 'Cancel' }}
                            </button>
                            <button
                                @click="saveCollectionMetadata"
                                :disabled="isSavingMetadata"
                                class="cursor-pointer rounded-xl bg-cyan-500 px-4 py-1.5 text-xs font-black text-slate-950 transition-all hover:bg-cyan-400 disabled:opacity-50"
                            >
                                {{
                                    isSavingMetadata
                                        ? isRTL
                                            ? 'جارٍ الحفظ...'
                                            : 'Saving...'
                                        : isRTL
                                          ? 'حفظ التعديلات'
                                          : 'Save Changes'
                                }}
                            </button>
                        </div>
                    </div>

                    <!-- Member Movies Section -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4
                                class="flex items-center gap-2 text-sm font-black tracking-wider text-white uppercase"
                            >
                                <Film class="h-4 w-4 text-cyan-400" />
                                <span>{{
                                    isRTL
                                        ? 'الأفلام المرتبطة بهذه السلسلة'
                                        : 'Movies Assigned to this Franchise'
                                }}</span>
                                <span
                                    class="rounded-full bg-white/10 px-2 py-0.5 font-mono text-xs text-slate-300"
                                >
                                    {{ selectedCollection.movies.length }}
                                </span>
                            </h4>
                            <span class="text-[11px] text-slate-400">
                                {{
                                    isRTL
                                        ? 'انقر على فك الارتباط لإزالة الفيلم الخاطئ فوراً'
                                        : 'Click unlink to detach any incorrect movie instantly'
                                }}
                            </span>
                        </div>

                        <!-- Movies Cards / Rows -->
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div
                                v-for="movie in selectedCollection.movies"
                                :key="movie.id"
                                class="group flex items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/[0.03] p-3 transition-all hover:border-cyan-500/40"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <div
                                        class="h-14 w-10 shrink-0 overflow-hidden rounded-lg border border-white/10 bg-slate-900"
                                    >
                                        <img
                                            v-if="movie.poster_path"
                                            :src="movie.poster_path"
                                            :alt="movie.title"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center text-slate-700"
                                        >
                                            <Film class="h-4 w-4" />
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <h5
                                            class="truncate text-xs font-bold text-white transition-colors group-hover:text-cyan-300"
                                            :title="movie.title"
                                        >
                                            {{ movie.title }}
                                        </h5>
                                        <p
                                            v-if="movie.title_ar"
                                            class="truncate text-[10px] text-slate-400"
                                        >
                                            {{ movie.title_ar }}
                                        </p>
                                        <div
                                            class="mt-1 flex items-center gap-2 text-[10px] text-slate-400"
                                        >
                                            <span v-if="movie.release_year">{{
                                                movie.release_year
                                            }}</span>
                                            <span
                                                v-if="movie.resolution"
                                                class="text-cyan-400"
                                                >{{ movie.resolution }}</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <!-- 1-Click Remove / Detach Button -->
                                <button
                                    @click="detachMovie(movie)"
                                    class="shrink-0 cursor-pointer rounded-xl border border-red-500/20 bg-red-500/10 p-2 text-red-400 transition-all hover:bg-red-500/25 hover:text-red-300"
                                    :title="
                                        isRTL
                                            ? 'إزالة الفيلم من هذه السلسلة'
                                            : 'Remove movie from this collection'
                                    "
                                >
                                    <Unlink class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="selectedCollection.movies.length === 0"
                            class="rounded-2xl border border-dashed border-white/10 p-6 text-center text-xs text-slate-500"
                        >
                            {{
                                isRTL
                                    ? 'لا توجد أفلام مرتبطة بهذه السلسلة حالياً.'
                                    : 'No movies currently assigned to this collection.'
                            }}
                        </div>
                    </div>

                    <!-- ADD MOVIE TO COLLECTION -->
                    <div
                        class="space-y-3 rounded-2xl border border-white/10 bg-white/[0.02] p-4"
                    >
                        <div class="flex items-center justify-between">
                            <h4
                                class="flex items-center gap-1.5 text-xs font-black tracking-wider text-cyan-300 uppercase"
                            >
                                <Plus class="h-4 w-4" />
                                <span>{{
                                    isRTL
                                        ? 'إضافة فيلم آخر من المكتبة إلى هذه السلسلة'
                                        : 'Add Movie from Library to this Franchise'
                                }}</span>
                            </h4>
                        </div>

                        <div class="relative">
                            <Search
                                class="absolute top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                :class="isRTL ? 'right-3' : 'left-3'"
                            />
                            <input
                                v-model="movieSearchQuery"
                                type="text"
                                :placeholder="
                                    isRTL
                                        ? 'ابحث عن اسم الفيلم بالإنجليزية أو العربية...'
                                        : 'Search movie title to link to this saga...'
                                "
                                class="w-full rounded-xl border border-white/10 bg-black/40 py-2 text-xs text-white placeholder-slate-400 focus:border-cyan-400 focus:outline-none"
                                :class="isRTL ? 'pr-9 pl-3' : 'pr-3 pl-9'"
                            />
                        </div>

                        <!-- Search Dropdown Results -->
                        <div
                            v-if="movieSearchResults.length > 0"
                            class="max-h-48 space-y-1.5 overflow-y-auto rounded-xl border border-white/10 bg-slate-950/80 p-2"
                        >
                            <div
                                v-for="resMovie in movieSearchResults"
                                :key="resMovie.id"
                                class="flex items-center justify-between gap-2 rounded-xl bg-white/[0.03] p-2 transition-all hover:bg-white/[0.08]"
                            >
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <div
                                        class="h-10 w-7 shrink-0 overflow-hidden rounded bg-slate-900"
                                    >
                                        <img
                                            v-if="resMovie.poster_path"
                                            :src="resMovie.poster_path"
                                            class="h-full w-full object-cover"
                                        />
                                    </div>
                                    <div class="min-w-0">
                                        <span
                                            class="block truncate text-xs font-bold text-white"
                                            >{{ resMovie.title }} ({{
                                                resMovie.release_year || 'N/A'
                                            }})</span
                                        >
                                        <span
                                            v-if="resMovie.collection_name"
                                            class="block truncate text-[10px] text-amber-400"
                                        >
                                            {{
                                                isRTL
                                                    ? 'حالياً في:'
                                                    : 'Currently in:'
                                            }}
                                            {{ resMovie.collection_name }}
                                        </span>
                                        <span
                                            v-else
                                            class="block text-[10px] text-slate-500"
                                        >
                                            {{
                                                isRTL
                                                    ? 'فيلم مستقل'
                                                    : 'Standalone film'
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <button
                                    @click="assignMovieToCollection(resMovie)"
                                    :disabled="isAddingMovie"
                                    class="flex shrink-0 cursor-pointer items-center gap-1 rounded-lg bg-cyan-500 px-2.5 py-1 text-[11px] font-bold text-slate-950 transition-all hover:bg-cyan-400 disabled:opacity-50"
                                >
                                    <Plus class="h-3 w-3" />
                                    <span>{{
                                        isRTL ? 'إضافة' : 'Assign'
                                    }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
