<script setup lang="ts">
import {
    ref,
    computed,
    watch,
    onMounted,
    onUnmounted,
    defineAsyncComponent,
} from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import {
    X,
    Play,
    Star,
    Sparkles,
    SlidersHorizontal,
    Layers,
} from '@lucide/vue';

const FixMatchModal = defineAsyncComponent(() => import('./FixMatchModal.vue'));

const props = withDefaults(
    defineProps<{
        item: any;
        isOpen?: boolean;
        show?: boolean;
        type?: string;
    }>(),
    {
        isOpen: true,
        show: true,
        type: 'movie',
    },
);

const emit = defineEmits(['close', 'play', 'downloadSub', 'updated']);

const { t, isRTL } = useI18n();

const showFixMatch = ref(false);
const fixMatchInitialTab = ref<
    'search' | 'direct_id' | 'collection' | 'manual'
>('search');
const localOverride = ref<Record<string, any> | null>(null);

watch(
    () => props.item,
    () => {
        localOverride.value = null;
    },
);

const currentItem = computed(() => {
    if (!props.item) {
        return null;
    }
    return localOverride.value
        ? { ...props.item, ...localOverride.value }
        : props.item;
});

const openFixMatchWithTab = (
    tab: 'search' | 'direct_id' | 'collection' | 'manual' = 'search',
) => {
    fixMatchInitialTab.value = tab;
    showFixMatch.value = true;
};

const handleMetadataUpdated = (updatedItem: any) => {
    localOverride.value = { ...localOverride.value, ...updatedItem };
    emit('updated', updatedItem);
};

const handleKeydown = (event: KeyboardEvent) => {
    if (
        event.key === 'Escape' &&
        !showFixMatch.value &&
        currentItem.value &&
        props.isOpen !== false &&
        props.show !== false
    ) {
        emit('close');
    }
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handleKeydown);
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleKeydown);
    }
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="currentItem && isOpen !== false && show !== false"
            role="dialog"
            aria-modal="true"
            :aria-label="currentItem.title"
            :dir="isRTL ? 'rtl' : 'ltr'"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/80 p-4 font-sans backdrop-blur-md"
            @click.self="emit('close')"
        >
            <div
                class="cf-glass-elevated border-border/80 relative my-8 w-full max-w-4xl overflow-hidden rounded-3xl border shadow-2xl"
            >
                <!-- Close Button (RTL-safe end-4) -->
                <button
                    type="button"
                    @click.stop="emit('close')"
                    class="border-border/80 hover:border-primary/40 absolute end-4 top-4 z-20 flex size-9 cursor-pointer items-center justify-center rounded-xl border bg-black/65 text-white backdrop-blur-md transition-colors hover:bg-black/90"
                    :title="t('common.close') || 'Close'"
                    :aria-label="t('common.close') || 'Close'"
                >
                    <X class="size-4.5" />
                </button>

                <!-- Backdrop Banner -->
                <div
                    class="bg-background relative aspect-video w-full overflow-hidden sm:aspect-[21/9]"
                >
                    <img
                        :src="
                            currentItem.backdrop_path ||
                            currentItem.poster_path ||
                            '/placeholder.jpg'
                        "
                        :alt="currentItem.title"
                        class="h-full w-full object-cover"
                    />
                    <div
                        class="from-card via-card/45 absolute inset-0 bg-gradient-to-t to-transparent"
                    ></div>
                </div>

                <!-- Content Body -->
                <div
                    class="relative z-10 -mt-16 space-y-6 p-6 sm:-mt-24 sm:p-8"
                >
                    <!-- Header with Poster & Basic Info -->
                    <div class="flex flex-col items-start gap-6 sm:flex-row">
                        <img
                            :src="currentItem.poster_path || '/placeholder.jpg'"
                            :alt="currentItem.title"
                            class="border-border/80 bg-muted w-32 shrink-0 rounded-2xl border-2 shadow-2xl sm:w-44"
                        />

                        <div class="flex-1 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="cinema-badge border-primary/35 bg-primary/15 text-primary border font-mono tabular-nums"
                                >
                                    {{ currentItem.resolution || '4K UHD' }}
                                </span>
                                <span
                                    class="cinema-badge flex items-center gap-1 border border-amber-500/30 bg-amber-500/20 text-amber-300 tabular-nums"
                                >
                                    <Star class="size-3.5 fill-current" />
                                    {{ currentItem.rating }}
                                </span>
                                <span
                                    v-if="currentItem.release_year"
                                    class="cinema-badge border-border/80 bg-muted/60 text-foreground border tabular-nums"
                                >
                                    {{ currentItem.release_year }}
                                </span>
                                <span
                                    v-if="currentItem.runtime_minutes"
                                    class="cinema-badge border-border/80 bg-muted/60 text-foreground border tabular-nums"
                                >
                                    {{ currentItem.runtime_minutes }}
                                    {{ t('common.minutes') }}
                                </span>
                            </div>

                            <h2
                                class="text-foreground text-2xl font-extrabold tracking-tight sm:text-4xl"
                            >
                                {{
                                    isRTL && currentItem.title_ar
                                        ? currentItem.title_ar
                                        : currentItem.title
                                }}
                            </h2>

                            <!-- Action Buttons -->
                            <div class="flex flex-wrap items-center gap-3 pt-2">
                                <button
                                    type="button"
                                    @click="emit('play', currentItem)"
                                    class="bg-primary text-primary-foreground shadow-primary/30 flex cursor-pointer items-center gap-2 rounded-xl px-6 py-3 text-xs font-extrabold shadow-lg transition-all hover:brightness-105 active:scale-95"
                                >
                                    <Play class="size-4 fill-current" />
                                    <span>{{ t('common.play_now') }}</span>
                                </button>

                                <button
                                    type="button"
                                    @click="openFixMatchWithTab('search')"
                                    class="cf-glass border-border/80 text-foreground hover:border-primary/40 flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-3 text-xs font-bold transition-all hover:bg-white/10 active:scale-95"
                                >
                                    <Sparkles class="text-primary size-4" />
                                    <span>{{
                                        isRTL
                                            ? 'تعديل البيانات والغلاف (Fix Match)'
                                            : 'Fix Match & Metadata'
                                    }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Synopsis -->
                    <div class="space-y-2">
                        <h3
                            class="text-muted-foreground text-xs font-bold tracking-wider uppercase"
                        >
                            {{ t('media.overview') }}
                        </h3>
                        <p
                            class="text-foreground/90 text-xs leading-relaxed sm:text-sm"
                        >
                            {{
                                isRTL && currentItem.overview_ar
                                    ? currentItem.overview_ar
                                    : currentItem.overview
                            }}
                        </p>
                    </div>

                    <!-- Collection Saga Banner -->
                    <div
                        v-if="currentItem.collection_name"
                        class="cf-glass border-primary/30 bg-primary/8 flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-3.5 sm:flex-nowrap"
                    >
                        <div class="flex min-w-0 items-center gap-2.5">
                            <Layers class="text-primary size-4 shrink-0" />
                            <div class="min-w-0">
                                <span
                                    class="text-primary block text-[10px] font-bold tracking-wider uppercase"
                                >
                                    {{
                                        isRTL
                                            ? 'جزء من سلسلة أفلام'
                                            : 'Part of Movie Collection'
                                    }}
                                </span>
                                <span
                                    class="text-foreground block truncate text-xs font-black"
                                >
                                    {{ currentItem.collection_name }}
                                </span>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                type="button"
                                @click="openFixMatchWithTab('collection')"
                                class="border-border/80 bg-card/80 text-foreground hover:border-primary/40 flex cursor-pointer items-center gap-1.5 rounded-xl border px-2.5 py-1.5 text-xs font-bold transition-all"
                                :title="
                                    isRTL
                                        ? 'تعديل السلسلة أو فك الارتباط'
                                        : 'Edit Collection or Detach'
                                "
                            >
                                <SlidersHorizontal
                                    class="text-primary size-3.5"
                                />
                                <span>{{
                                    isRTL ? 'إدارة السلسلة' : 'Edit Saga'
                                }}</span>
                            </button>
                            <Link
                                :href="`/collections/${currentItem.collection_name
                                    .toLowerCase()
                                    .replace(/[^a-z0-9]+/g, '-')
                                    .replace(/(^-|-$)/g, '')}`"
                                class="border-primary/35 bg-primary/15 text-primary hover:bg-primary hover:text-primary-foreground rounded-xl border px-3 py-1.5 text-xs font-bold transition-all"
                            >
                                {{
                                    isRTL
                                        ? 'عرض جميع أجزاء السلسلة'
                                        : 'View Full Saga'
                                }}
                            </Link>
                        </div>
                    </div>

                    <!-- Standalone Movie Link to Franchise Helper -->
                    <div
                        v-else
                        class="border-border/70 bg-muted/25 flex items-center justify-between gap-4 rounded-2xl border border-dashed p-3"
                    >
                        <div class="flex min-w-0 items-center gap-2.5">
                            <Layers
                                class="text-muted-foreground size-4 shrink-0"
                            />
                            <div class="text-muted-foreground min-w-0 text-xs">
                                <span
                                    class="text-foreground block font-semibold"
                                    >{{
                                        isRTL
                                            ? 'فيلم مستقل (غير مربوط بسلسلة)'
                                            : 'Standalone Movie (Not part of a franchise)'
                                    }}</span
                                >
                                <span
                                    class="text-muted-foreground text-[11px]"
                                    >{{
                                        isRTL
                                            ? 'يمكنك ربطه بسلسلة حالية أو إنشاء سلسلة جديدة بنقرة واحدة.'
                                            : 'You can link this movie to an existing franchise or create a new one.'
                                    }}</span
                                >
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="openFixMatchWithTab('collection')"
                            class="border-primary/30 bg-primary/10 text-primary hover:bg-primary/20 flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold transition-all"
                        >
                            <Layers class="size-3.5" />
                            <span>{{
                                isRTL ? 'ربط بسلسلة' : 'Link to Saga'
                            }}</span>
                        </button>
                    </div>

                    <!-- Technical Specs Box -->
                    <div
                        class="cf-glass border-border/80 grid grid-cols-2 gap-4 rounded-2xl border p-4 text-xs sm:grid-cols-4"
                    >
                        <div>
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{ t('media.resolution') }}</span
                            >
                            <span
                                class="text-foreground font-mono font-bold tabular-nums"
                                >{{ currentItem.resolution || '1080p' }}</span
                            >
                        </div>
                        <div>
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{ t('media.video_codec') }}</span
                            >
                            <span
                                class="text-foreground font-mono font-bold tabular-nums"
                                >{{ currentItem.video_codec || 'HEVC' }}</span
                            >
                        </div>
                        <div>
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{ t('media.audio_codec') }}</span
                            >
                            <span
                                class="text-foreground font-mono font-bold tabular-nums"
                                >{{
                                    currentItem.audio_codec || 'AAC 5.1'
                                }}</span
                            >
                        </div>
                        <div>
                            <span
                                class="text-muted-foreground block text-[10px] font-bold uppercase"
                                >{{
                                    isRTL ? 'المسار المحلي' : 'Local Path'
                                }}</span
                            >
                            <span
                                class="text-muted-foreground block truncate font-mono text-[10px]"
                                >{{ currentItem.file_path || 'Indexed' }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fix Match Modal -->
            <FixMatchModal
                v-if="showFixMatch"
                :show="showFixMatch"
                :item="currentItem"
                :initial-tab="fixMatchInitialTab"
                type="movie"
                @close="showFixMatch = false"
                @updated="handleMetadataUpdated"
            />
        </div>
    </Teleport>
</template>
