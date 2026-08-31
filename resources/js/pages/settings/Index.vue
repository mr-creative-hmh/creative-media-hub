<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    Settings as SettingsIcon, Save, Key, Globe, Sparkles,
    CheckCircle2, AlertCircle, ArrowUp, ArrowDown, ShieldCheck,
    Layers, Cpu, Database, HardDrive, RefreshCw, Zap, Check, X
} from 'lucide-vue-next';

const props = defineProps<{
    settings: {
        tmdb_api_key: string;
        omdb_api_key: string;
        opensubtitles_api_key: string;
        ffmpeg_path: string;
        default_language: string;
        auto_fetch_metadata: boolean;
        auto_fetch_subtitles: boolean;
        preferred_providers: string[];
    };
}>();

const { t, isRTL, setLocale } = useI18n();

const form = ref({
    tmdb_api_key: props.settings.tmdb_api_key || '',
    omdb_api_key: props.settings.omdb_api_key || '',
    opensubtitles_api_key: props.settings.opensubtitles_api_key || '',
    ffmpeg_path: props.settings.ffmpeg_path || 'ffmpeg',
    default_language: props.settings.default_language || 'ar',
    auto_fetch_metadata: props.settings.auto_fetch_metadata ?? true,
    auto_fetch_subtitles: props.settings.auto_fetch_subtitles ?? true,
});

// All available metadata providers with keyless vs keyed metadata
const allProviders = ref([
    {
        id: 'tvmaze',
        name: 'TVMaze Free API',
        type: 'keyless',
        desc: 'TV shows, seasons, episode summaries, air dates & posters. No rate-limit issues.',
        descAr: 'مسلسلات ومواسم وحلقات وأغلفة بدون أي مفتاح API وبدون قيود.',
        badge: '100% Free / Keyless',
        badgeColor: 'emerald',
    },
    {
        id: 'anilist',
        name: 'AniList GraphQL',
        type: 'keyless',
        desc: 'Comprehensive Anime & Asian drama metadata, titles, and posters.',
        descAr: 'قاعدة بيانات الأنمي والدراما الآسيوية والبوسترات المباشرة مجاناً.',
        badge: '100% Free / Keyless',
        badgeColor: 'emerald',
    },
    {
        id: 'wikipedia',
        name: 'Wikipedia / Wikidata API',
        type: 'keyless',
        desc: 'Plot summaries, direct Arabic translations & open artwork thumbnails.',
        descAr: 'ترجمات عربية للقصة والعناوين والبوسترات الحرة من ويكيبيديا.',
        badge: '100% Free / Keyless',
        badgeColor: 'emerald',
    },
    {
        id: 'local_nfo',
        name: 'Local NFO & Folder Art',
        type: 'keyless',
        desc: 'Reads local poster.jpg, fanart.jpg and .nfo files directly from hard drive.',
        descAr: 'قراءة ملفات البوستر والصور وملفات NFO المحلية من مجلد الوسائط مباشرة.',
        badge: 'Offline / Local Disk',
        badgeColor: 'cyan',
    },
    {
        id: 'tmdb',
        name: 'The Movie Database (TMDb)',
        type: 'keyed',
        desc: 'Cinema standard database with 4K backdrops, cast info & multi-language data.',
        descAr: 'قاعدة بيانات الأفلام العالمية مع خلفيات 4K وتفاصيل الممثلين والتقييمات.',
        badge: 'API Key Required',
        badgeColor: 'amber',
    },
    {
        id: 'omdb',
        name: 'Open Movie Database (OMDb)',
        type: 'keyed',
        desc: 'IMDb ratings, Rotten Tomatoes scores & Metacritic values.',
        descAr: 'تقييمات IMDb و Rotten Tomatoes و Metacritic الرسمية.',
        badge: 'API Key Required',
        badgeColor: 'amber',
    },
]);

// Sorted by user priority
const preferredOrder = ref<string[]>(
    props.settings.preferred_providers?.length
        ? props.settings.preferred_providers
        : ['tvmaze', 'anilist', 'wikipedia', 'local_nfo', 'tmdb', 'omdb']
);

const sortedProviders = ref(
    [...allProviders.value].sort((a, b) => {
        const idxA = preferredOrder.value.indexOf(a.id);
        const idxB = preferredOrder.value.indexOf(b.id);
        return (idxA === -1 ? 99 : idxA) - (idxB === -1 ? 99 : idxB);
    })
);

const moveUp = (index: number) => {
    if (index <= 0) return;
    const item = sortedProviders.value.splice(index, 1)[0];
    sortedProviders.value.splice(index - 1, 0, item);
    preferredOrder.value = sortedProviders.value.map((p) => p.id);
};

const moveDown = (index: number) => {
    if (index >= sortedProviders.value.length - 1) return;
    const item = sortedProviders.value.splice(index, 1)[0];
    sortedProviders.value.splice(index + 1, 0, item);
    preferredOrder.value = sortedProviders.value.map((p) => p.id);
};

const isSaving = ref(false);
const toastMessage = ref('');

// Test status per provider
const testingProviderId = ref<string | null>(null);
const testResults = ref<Record<string, { success: boolean; message: string; latency_ms?: number }>>({});

const testSingleProvider = async (providerId: string, customKey?: string) => {
    testingProviderId.value = providerId;
    try {
        const res = await fetch('/api/settings/test-provider', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                provider: providerId,
                key: customKey || (form.value as any)[`${providerId}_api_key`] || undefined,
            }),
        });

        const data = await res.json();
        testResults.value[providerId] = {
            success: res.ok && data.success,
            message: data.message,
            latency_ms: data.latency_ms,
        };
    } catch (e: any) {
        testResults.value[providerId] = {
            success: false,
            message: e.message || 'Connection failed.',
        };
    } finally {
        testingProviderId.value = null;
    }
};

const saveSettings = async () => {
    isSaving.value = true;
    toastMessage.value = '';

    try {
        const res = await fetch('/api/settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                ...form.value,
                preferred_providers: preferredOrder.value,
            }),
        });

        if (res.ok) {
            setLocale(form.value.default_language);
            toastMessage.value = isRTL.value ? 'تم حفظ الإعدادات وترتيب المزودين بنجاح!' : 'Settings & provider priorities saved successfully!';
        }
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <Head :title="t('settings_view.title')" />

    <AppLayout v-slot="{ play }">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                        <SettingsIcon class="w-5 h-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                            {{ t('settings_view.title') }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                            {{ t('settings_view.subtitle') }}
                        </p>
                    </div>
                </div>

                <button
                    @click="saveSettings"
                    :disabled="isSaving"
                    class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black text-xs flex items-center gap-2 active:scale-95 transition-all cursor-pointer shadow-lg shadow-cyan-500/25"
                >
                    <RefreshCw v-if="isSaving" class="w-4 h-4 animate-spin" />
                    <Save v-else class="w-4 h-4" />
                    <span>{{ t('settings_view.save') }}</span>
                </button>
            </div>
        </div>

        <!-- Toast Notice -->
        <div v-if="toastMessage" class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 shrink-0" />
                <span>{{ toastMessage }}</span>
            </div>
            <button @click="toastMessage = ''" class="cursor-pointer text-emerald-400 hover:text-emerald-300">✕</button>
        </div>

        <div class="space-y-8">
            <!-- 1. Metadata Providers Customization, Priority Ordering & Live Testing -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6">
                <div class="flex items-center justify-between flex-wrap gap-4 pb-4 border-b border-white/10">
                    <div class="flex items-center gap-2.5">
                        <Layers class="w-5 h-5 text-cyan-400" />
                        <div>
                            <h3 class="font-bold text-base text-white">
                                {{ isRTL ? 'أولويات مزودي البيانات والأغلفة وفحص الاتصال (Provider Priority & Test)' : 'Metadata Providers Priority Chain & Live Verification' }}
                            </h3>
                            <p class="text-xs text-slate-400">
                                {{ isRTL ? 'رتب أولوية البحث واضغط زر الفحص للتأكد من عمل المزود. المزودات المجانية تعمل مباشرة بدون أي مفتاح.' : 'Reorder priority chain and click test to verify provider health in real-time.' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Provider Priority Cards -->
                <div class="space-y-3">
                    <div
                        v-for="(prov, pIdx) in sortedProviders"
                        :key="prov.id"
                        class="p-4 rounded-2xl bg-white/5 border border-white/10 hover:border-cyan-500/40 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4 group"
                    >
                        <div class="flex items-center gap-3.5 min-w-0">
                            <!-- Priority Rank Badge -->
                            <div class="w-7 h-7 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 flex items-center justify-center font-black text-xs shrink-0">
                                #{{ pIdx + 1 }}
                            </div>

                            <div class="min-w-0 space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="font-black text-xs text-white">{{ prov.name }}</h4>
                                    <span
                                        class="cinema-badge text-[9px] font-bold"
                                        :class="prov.badgeColor === 'emerald'
                                            ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                                            : prov.badgeColor === 'cyan'
                                            ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30'
                                            : 'bg-amber-500/20 text-amber-300 border-amber-500/30'"
                                    >
                                        {{ prov.badge }}
                                    </span>

                                    <!-- Test Feedback Status Badge -->
                                    <span
                                        v-if="testResults[prov.id]"
                                        class="cinema-badge text-[9px] font-mono font-bold flex items-center gap-1"
                                        :class="testResults[prov.id].success ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border-rose-500/30'"
                                    >
                                        <Check v-if="testResults[prov.id].success" class="w-3 h-3" />
                                        <X v-else class="w-3 h-3" />
                                        <span>{{ testResults[prov.id].message }}</span>
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-400 line-clamp-1">
                                    {{ isRTL ? prov.descAr : prov.desc }}
                                </p>
                            </div>
                        </div>

                        <!-- Action & Move Buttons -->
                        <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                            <button
                                v-if="prov.id !== 'local_nfo'"
                                @click="testSingleProvider(prov.id)"
                                :disabled="testingProviderId === prov.id"
                                class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-cyan-500/20 text-slate-300 hover:text-cyan-300 border border-white/10 text-[11px] font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                            >
                                <RefreshCw v-if="testingProviderId === prov.id" class="w-3 h-3 animate-spin text-cyan-400" />
                                <Zap v-else class="w-3 h-3 text-cyan-400" />
                                <span>{{ isRTL ? 'فحص الاتصال' : 'Test Connection' }}</span>
                            </button>

                            <div class="flex items-center gap-1">
                                <button
                                    @click="moveUp(pIdx)"
                                    :disabled="pIdx === 0"
                                    class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer"
                                    title="Move Priority Up"
                                >
                                    <ArrowUp class="w-4 h-4" />
                                </button>
                                <button
                                    @click="moveDown(pIdx)"
                                    :disabled="pIdx === sortedProviders.length - 1"
                                    class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer"
                                    title="Move Priority Down"
                                >
                                    <ArrowDown class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. API Keys (For Keyed Providers) -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6">
                <div class="flex items-center gap-2.5 pb-4 border-b border-white/10">
                    <Key class="w-5 h-5 text-amber-400" />
                    <div>
                        <h3 class="font-bold text-base text-white">
                            {{ isRTL ? 'مفاتيح واجهات برمجة التطبيقات (API Keys)' : 'Provider API Keys (Optional)' }}
                        </h3>
                        <p class="text-xs text-slate-400">
                            {{ isRTL ? 'أدخل مفاتيح API الخاصة بك للمزودات التي تتطلب مفتاحاً للحصول على صور بدقة 4K وتقييمات IMDb.' : 'Enter API keys for enhanced 4K backdrops and official IMDb/Rotten Tomatoes ratings.' }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-300">TMDb API Key</label>
                            <button
                                @click="testSingleProvider('tmdb', form.tmdb_api_key)"
                                :disabled="testingProviderId === 'tmdb'"
                                class="text-[10px] text-cyan-400 hover:underline font-bold cursor-pointer"
                            >
                                {{ isRTL ? 'فحص المفتاح' : 'Test Key' }}
                            </button>
                        </div>
                        <input
                            v-model="form.tmdb_api_key"
                            type="password"
                            placeholder="Enter TMDb v3 API Read Key"
                            class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-300">OMDb API Key</label>
                            <button
                                @click="testSingleProvider('omdb', form.omdb_api_key)"
                                :disabled="testingProviderId === 'omdb'"
                                class="text-[10px] text-cyan-400 hover:underline font-bold cursor-pointer"
                            >
                                {{ isRTL ? 'فحص المفتاح' : 'Test Key' }}
                            </button>
                        </div>
                        <input
                            v-model="form.omdb_api_key"
                            type="password"
                            placeholder="Enter OMDb API Key"
                            class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                        />
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-300">OpenSubtitles.com User Key</label>
                            <button
                                @click="testSingleProvider('opensubtitles', form.opensubtitles_api_key)"
                                :disabled="testingProviderId === 'opensubtitles'"
                                class="text-[10px] text-cyan-400 hover:underline font-bold cursor-pointer"
                            >
                                {{ isRTL ? 'فحص الخدمة' : 'Test Service' }}
                            </button>
                        </div>
                        <input
                            v-model="form.opensubtitles_api_key"
                            type="password"
                            placeholder="Enter OpenSubtitles REST API Key"
                            class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                        />
                    </div>
                </div>
            </div>

            <!-- 3. Cinema Streaming & Transcoder Configuration -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6">
                <div class="flex items-center gap-2.5 pb-4 border-b border-white/10">
                    <Cpu class="w-5 h-5 text-cyan-400" />
                    <div>
                        <h3 class="font-bold text-base text-white">
                            {{ isRTL ? 'محرك تشغيل وترميز الصوت (Cinema Audio & Codec Engine)' : 'Cinema Audio & Codec Engine' }}
                        </h3>
                        <p class="text-xs text-slate-400">
                            {{ isRTL ? 'دعم تشغيل صيغ الصوت المتقدمة (E-AC-3, AC-3, DTS, TrueHD) بدون مشاكل انقطاع الصوت.' : 'Hardware & software audio decoding for Dolby Digital, DTS, and 5.1 multichannel audio.' }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-300">{{ isRTL ? 'مسار FFmpeg للترميز الفوري' : 'FFmpeg Binary Path' }}</label>
                        <input
                            v-model="form.ffmpeg_path"
                            type="text"
                            placeholder="ffmpeg or C:\ffmpeg\bin\ffmpeg.exe"
                            class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-300">{{ isRTL ? 'لغة الواجهة المفضلة' : 'Default Interface Language' }}</label>
                        <select
                            v-model="form.default_language"
                            class="w-full px-4 py-2.5 rounded-xl bg-[#0E121E] border border-white/10 text-xs text-white focus:outline-none focus:border-cyan-500 font-bold"
                        >
                            <option value="ar">العربية (Arabic) - Default</option>
                            <option value="en">English</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
