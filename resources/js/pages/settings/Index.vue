<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    Settings, Key, Globe, ShieldCheck, CheckCircle2,
    Sparkles, Save, Layers, Cpu, Subtitles
} from 'lucide-vue-next';

const props = defineProps<{
    settings: {
        tmdb_api_key: string;
        omdb_api_key: string;
        opensubtitles_api_key: string;
        default_language: 'ar' | 'en';
        auto_fetch_metadata: boolean;
        auto_fetch_subtitles: boolean;
        preferred_providers: string[];
    };
}>();

const { t, isRTL } = useI18n();

const form = ref({ ...props.settings });
const isSaving = ref(false);
const showSuccess = ref(false);

const handleSave = async () => {
    isSaving.value = true;
    showSuccess.value = false;

    try {
        const res = await fetch('/api/settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify(form.value),
        });

        if (res.ok) {
            showSuccess.value = true;
            setTimeout(() => {
                showSuccess.value = false;
            }, 3500);
        }
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <Head :title="t('settings.title')" />

    <AppLayout v-slot="{ play }">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                        <Settings class="w-5 h-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white">
                            {{ t('settings.title') }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-0.5">
                            {{ t('settings.subtitle') }}
                        </p>
                    </div>
                </div>

                <button
                    @click="handleSave"
                    :disabled="isSaving"
                    class="flex items-center gap-2 px-6 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-extrabold text-xs shadow-lg shadow-cyan-500/20 active:scale-95 transition-all cursor-pointer"
                >
                    <Save class="w-4 h-4" />
                    <span>{{ isSaving ? (isRTL ? 'جاري الحفظ...' : 'Saving...') : t('common.save') }}</span>
                </button>
            </div>
        </div>

        <!-- Success Alert -->
        <div v-if="showSuccess" class="glass-panel p-4 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-xs font-bold flex items-center gap-2 mb-6">
            <CheckCircle2 class="w-4 h-4" />
            <span>{{ t('settings.save_success') }}</span>
        </div>

        <div class="space-y-8">
            <!-- 1. Metadata Scrapers API Keys -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-white/10 space-y-6 shadow-sm">
                <div class="flex items-center gap-2.5 pb-3 border-b border-slate-200 dark:border-white/10">
                    <Key class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                    <h3 class="font-bold text-base text-slate-900 dark:text-white">
                        {{ isRTL ? 'مفاتيح واجهات API للمزودات' : 'Metadata Provider API Keys' }}
                    </h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- TMDb Key -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="font-bold text-xs text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                {{ t('settings.tmdb_key') }}
                            </label>
                            <span class="text-[10px] text-cyan-700 dark:text-cyan-400 font-bold bg-cyan-500/10 px-2 py-0.5 rounded">
                                Recommended for Arabic & HD Posters
                            </span>
                        </div>
                        <input
                            type="password"
                            v-model="form.tmdb_api_key"
                            placeholder="Enter TMDb API Read Access Token or API Key v3"
                            class="w-full h-11 rounded-xl bg-slate-50 dark:bg-white/[0.04] border border-slate-300 dark:border-white/15 px-4 text-xs text-slate-900 dark:text-slate-100 font-mono focus:border-cyan-500 outline-none"
                        />
                    </div>

                    <!-- OMDb Key -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="font-bold text-xs text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                {{ t('settings.omdb_key') }}
                            </label>
                            <span class="text-[10px] text-indigo-700 dark:text-indigo-400 font-bold bg-indigo-500/10 px-2 py-0.5 rounded">
                                IMDb Ratings & Metascores
                            </span>
                        </div>
                        <input
                            type="password"
                            v-model="form.omdb_api_key"
                            placeholder="Enter OMDb API Key"
                            class="w-full h-11 rounded-xl bg-slate-50 dark:bg-white/[0.04] border border-slate-300 dark:border-white/15 px-4 text-xs text-slate-900 dark:text-slate-100 font-mono focus:border-cyan-500 outline-none"
                        />
                    </div>

                    <!-- OpenSubtitles Key -->
                    <div class="space-y-2 md:col-span-2">
                        <div class="flex items-center justify-between">
                            <label class="font-bold text-xs text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                {{ t('settings.opensubtitles_key') }}
                            </label>
                            <span class="text-[10px] text-emerald-700 dark:text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded">
                                64-bit Audio Sync Hash Matching
                            </span>
                        </div>
                        <input
                            type="password"
                            v-model="form.opensubtitles_api_key"
                            placeholder="Enter OpenSubtitles.com REST API Key"
                            class="w-full h-11 rounded-xl bg-slate-50 dark:bg-white/[0.04] border border-slate-300 dark:border-white/15 px-4 text-xs text-slate-900 dark:text-slate-100 font-mono focus:border-cyan-500 outline-none"
                        />
                    </div>
                </div>
            </div>

            <!-- 2. Active Fallback Providers Chain (100% Free Tiers) -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-white/10 space-y-4 shadow-sm">
                <div class="flex items-center gap-2.5 pb-3 border-b border-slate-200 dark:border-white/10">
                    <Cpu class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    <h3 class="font-bold text-base text-slate-900 dark:text-white">
                        {{ t('settings.fallback_providers') }}
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div class="p-3.5 rounded-2xl glass-card border border-slate-200 dark:border-white/10 flex items-center justify-between shadow-sm">
                        <div>
                            <h4 class="font-bold text-xs text-slate-900 dark:text-white">TMDb (The Movie Database)</h4>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Arabic & English metadata, 4K backdrops</p>
                        </div>
                        <span class="cinema-badge bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 text-[10px]">Active</span>
                    </div>

                    <div class="p-3.5 rounded-2xl glass-card border border-slate-200 dark:border-white/10 flex items-center justify-between shadow-sm">
                        <div>
                            <h4 class="font-bold text-xs text-slate-900 dark:text-white">TVMaze (100% Free)</h4>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">TV seasons, episodes & stills (No Key Required)</p>
                        </div>
                        <span class="cinema-badge bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 text-[10px]">Free Tier</span>
                    </div>

                    <div class="p-3.5 rounded-2xl glass-card border border-slate-200 dark:border-white/10 flex items-center justify-between shadow-sm">
                        <div>
                            <h4 class="font-bold text-xs text-slate-900 dark:text-white">AniList / Jikan GraphQL</h4>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Anime specialist catalog (100% Free)</p>
                        </div>
                        <span class="cinema-badge bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 text-[10px]">Free Tier</span>
                    </div>

                    <div class="p-3.5 rounded-2xl glass-card border border-slate-200 dark:border-white/10 flex items-center justify-between shadow-sm">
                        <div>
                            <h4 class="font-bold text-xs text-slate-900 dark:text-white">Wikipedia & Wikidata</h4>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Multilingual encyclopedia synopsis fallback</p>
                        </div>
                        <span class="cinema-badge bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 text-[10px]">Free Tier</span>
                    </div>

                    <div class="p-3.5 rounded-2xl glass-card border border-slate-200 dark:border-white/10 flex items-center justify-between shadow-sm">
                        <div>
                            <h4 class="font-bold text-xs text-slate-900 dark:text-white">Local NFO & XML Reader</h4>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Kodi & TinyMediaManager offline files</p>
                        </div>
                        <span class="cinema-badge bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 text-[10px]">Offline</span>
                    </div>
                </div>
            </div>

            <!-- 3. Automated Behavior Preferences -->
            <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-white/10 space-y-4 shadow-sm">
                <div class="flex items-center gap-2.5 pb-3 border-b border-slate-200 dark:border-white/10">
                    <Globe class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                    <h3 class="font-bold text-base text-slate-900 dark:text-white">
                        {{ isRTL ? 'تفضيلات اللغة والأتمتة' : 'Language & Automation Preferences' }}
                    </h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="font-bold text-xs text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                            {{ t('settings.preferred_language') }}
                        </label>
                        <select
                            v-model="form.default_language"
                            class="w-full h-11 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-white/15 px-3 text-sm text-slate-900 dark:text-slate-100 focus:border-cyan-500 outline-none"
                        >
                            <option value="ar">العربية (Arabic Preferred)</option>
                            <option value="en">English (Original English)</option>
                        </select>
                    </div>

                    <div class="space-y-3 pt-6">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="form.auto_fetch_metadata"
                                class="rounded bg-slate-100 dark:bg-white/10 border-slate-300 dark:border-white/20 text-cyan-500 focus:ring-cyan-500"
                            />
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ t('settings.auto_fetch_meta') }}</span>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="form.auto_fetch_subtitles"
                                class="rounded bg-slate-100 dark:bg-white/10 border-slate-300 dark:border-white/20 text-cyan-500 focus:ring-cyan-500"
                            />
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ t('settings.auto_fetch_subs') }}</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
