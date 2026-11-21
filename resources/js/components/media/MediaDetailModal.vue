<script setup lang="ts">
import { ref, computed, watch, defineAsyncComponent } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import {
    X,
    Play,
    Star,
    Sparkles,
    SlidersHorizontal,
    Layers,
} from 'lucide-vue-next';

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
</script>

<template>
    <div
        v-if="currentItem && isOpen !== false && show !== false"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/80 p-4 font-sans backdrop-blur-md"
        @click.self="emit('close')"
    >
        <div
            class="glass-panel relative my-8 w-full max-w-4xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-white/15 dark:bg-[#121622]"
        >
            <!-- Close Button -->
            <button
                @click.stop="emit('close')"
                class="absolute top-4 right-4 z-20 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full bg-black/60 text-white transition-colors hover:bg-black/90"
                title="Close"
            >
                <X class="h-5 w-5" />
            </button>

            <!-- Backdrop Banner -->
            <div
                class="relative aspect-video w-full overflow-hidden bg-slate-950 sm:aspect-[21/9]"
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
                    class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent"
                ></div>
            </div>

            <!-- Content Body -->
            <div class="relative z-10 -mt-16 space-y-6 p-6 sm:-mt-24 sm:p-8">
                <!-- Header with Poster & Basic Info -->
                <div class="flex flex-col items-start gap-6 sm:flex-row">
                    <img
                        :src="currentItem.poster_path || '/placeholder.jpg'"
                        :alt="currentItem.title"
                        class="w-32 shrink-0 rounded-2xl border-2 border-white/20 bg-slate-200 shadow-2xl sm:w-44 dark:bg-slate-800"
                    />

                    <div class="flex-1 space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="cinema-badge border border-cyan-500/30 bg-cyan-500/20 text-cyan-700 dark:text-cyan-400"
                            >
                                {{ currentItem.resolution || '4K UHD' }}
                            </span>
                            <span
                                class="cinema-badge flex items-center gap-1 border border-amber-500/30 bg-amber-500/20 text-amber-700 dark:text-amber-300"
                            >
                                <Star class="h-3.5 w-3.5 fill-current" />
                                {{ currentItem.rating }}
                            </span>
                            <span
                                v-if="currentItem.release_year"
                                class="cinema-badge border border-slate-200 bg-slate-100 text-slate-700 dark:border-white/10 dark:bg-white/10 dark:text-slate-300"
                            >
                                {{ currentItem.release_year }}
                            </span>
                            <span
                                v-if="currentItem.runtime_minutes"
                                class="cinema-badge border border-slate-200 bg-slate-100 text-slate-700 dark:border-white/10 dark:bg-white/10 dark:text-slate-300"
                            >
                                {{ currentItem.runtime_minutes }}
                                {{ t('common.minutes') }}
                            </span>
                        </div>

                        <h2
                            class="text-2xl font-extrabold text-slate-900 sm:text-4xl dark:text-white"
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
                                @click="emit('play', currentItem)"
                                class="flex cursor-pointer items-center gap-2 rounded-xl bg-cyan-500 px-6 py-3 text-xs font-extrabold text-slate-950 shadow-lg shadow-cyan-500/30 transition-all hover:bg-cyan-400 active:scale-95"
                            >
                                <Play class="h-4 w-4 fill-current" />
                                <span>{{ t('common.play_now') }}</span>
                            </button>

                            <button
                                @click="openFixMatchWithTab('search')"
                                class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-xs font-bold text-slate-700 transition-all hover:bg-slate-200 dark:border-white/10 dark:bg-white/10 dark:text-slate-200 dark:hover:bg-white/20"
                            >
                                <Sparkles class="h-4 w-4 text-cyan-500" />
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
                        class="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                    >
                        {{ t('media.overview') }}
                    </h3>
                    <p
                        class="text-xs leading-relaxed text-slate-700 sm:text-sm dark:text-slate-300"
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
                    class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-cyan-500/30 bg-gradient-to-r from-cyan-950/40 via-purple-950/30 to-indigo-950/40 p-3.5 sm:flex-nowrap"
                >
                    <div class="flex min-w-0 items-center gap-2.5">
                        <Layers class="h-4 w-4 shrink-0 text-cyan-400" />
                        <div class="min-w-0">
                            <span
                                class="block text-[10px] font-bold tracking-wider text-cyan-300 uppercase"
                            >
                                {{
                                    isRTL
                                        ? 'جزء من سلسلة أفلام'
                                        : 'Part of Movie Collection'
                                }}
                            </span>
                            <span
                                class="block truncate text-xs font-black text-white"
                            >
                                {{ currentItem.collection_name }}
                            </span>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            @click="openFixMatchWithTab('collection')"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-white/10 bg-white/10 px-2.5 py-1.5 text-xs font-bold text-white transition-all hover:bg-white/20"
                            :title="
                                isRTL
                                    ? 'تعديل السلسلة أو فك الارتباط'
                                    : 'Edit Collection or Detach'
                            "
                        >
                            <SlidersHorizontal
                                class="h-3.5 w-3.5 text-cyan-400"
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
                            class="rounded-xl border border-cyan-500/30 bg-cyan-500/20 px-3 py-1.5 text-xs font-bold text-cyan-300 transition-all hover:bg-cyan-500 hover:text-slate-950"
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
                    class="flex items-center justify-between gap-4 rounded-2xl border border-dashed border-slate-300 bg-slate-100/80 p-3 dark:border-white/10 dark:bg-white/5"
                >
                    <div class="flex min-w-0 items-center gap-2.5">
                        <Layers class="h-4 w-4 shrink-0 text-slate-400" />
                        <div
                            class="min-w-0 text-xs text-slate-600 dark:text-slate-400"
                        >
                            <span class="block font-semibold">{{
                                isRTL
                                    ? 'فيلم مستقل (غير مربوط بسلسلة)'
                                    : 'Standalone Movie (Not part of a franchise)'
                            }}</span>
                            <span
                                class="text-[11px] text-slate-500 dark:text-slate-500"
                                >{{
                                    isRTL
                                        ? 'يمكنك ربطه بسلسلة حالية أو إنشاء سلسلة جديدة بنقرة واحدة.'
                                        : 'You can link this movie to an existing franchise or create a new one.'
                                }}</span
                            >
                        </div>
                    </div>
                    <button
                        @click="openFixMatchWithTab('collection')"
                        class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl border border-cyan-500/20 bg-cyan-500/10 px-3 py-1.5 text-xs font-bold text-cyan-600 transition-all hover:bg-cyan-500/20 hover:text-cyan-300 dark:text-cyan-400"
                    >
                        <Layers class="h-3.5 w-3.5" />
                        <span>{{ isRTL ? 'ربط بسلسلة' : 'Link to Saga' }}</span>
                    </button>
                </div>

                <!-- Technical Specs Box -->
                <div
                    class="grid grid-cols-2 gap-4 rounded-2xl border border-slate-200 bg-slate-100 p-4 text-xs sm:grid-cols-4 dark:border-white/10 dark:bg-white/5"
                >
                    <div>
                        <span
                            class="block text-[10px] font-bold text-slate-500 uppercase dark:text-slate-400"
                            >{{ t('media.resolution') }}</span
                        >
                        <span
                            class="font-mono font-bold text-slate-900 dark:text-white"
                            >{{ currentItem.resolution || '1080p' }}</span
                        >
                    </div>
                    <div>
                        <span
                            class="block text-[10px] font-bold text-slate-500 uppercase dark:text-slate-400"
                            >{{ t('media.video_codec') }}</span
                        >
                        <span
                            class="font-mono font-bold text-slate-900 dark:text-white"
                            >{{ currentItem.video_codec || 'HEVC' }}</span
                        >
                    </div>
                    <div>
                        <span
                            class="block text-[10px] font-bold text-slate-500 uppercase dark:text-slate-400"
                            >{{ t('media.audio_codec') }}</span
                        >
                        <span
                            class="font-mono font-bold text-slate-900 dark:text-white"
                            >{{ currentItem.audio_codec || 'AAC 5.1' }}</span
                        >
                    </div>
                    <div>
                        <span
                            class="block text-[10px] font-bold text-slate-500 uppercase dark:text-slate-400"
                            >{{ isRTL ? 'المسار المحلي' : 'Local Path' }}</span
                        >
                        <span
                            class="block truncate font-mono text-[10px] text-slate-600 dark:text-slate-400"
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
</template>
