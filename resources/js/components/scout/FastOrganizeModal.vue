<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    X, FolderSync, CheckCircle2, AlertCircle, Loader2,
    HardDrive, ArrowRight, Sparkles
} from 'lucide-vue-next';

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'organized'): void;
}>();

const { t, isRTL } = useI18n();

const running = ref(false);
const result = ref<any | null>(null);
const errorMessage = ref<string | null>(null);

const executeOrganizeAndScan = async () => {
    running.value = true;
    errorMessage.value = null;
    result.value = null;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/api/scout/organize-and-scan', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({}),
        });
        const data = await res.json();
        result.value = data;
        emit('organized');
    } catch (err: any) {
        errorMessage.value = err?.message || 'Failed to organize and scan files';
    } finally {
        running.value = false;
    }
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fade-in" :dir="isRTL ? 'rtl' : 'ltr'">
        <div class="relative w-full max-w-xl glass-panel border border-cyan-500/20 bg-slate-900/95 rounded-3xl shadow-2xl overflow-hidden flex flex-col">
            
            <!-- Ambient Glows -->
            <div class="ambient-glow bg-emerald-500/10 w-72 h-72 -top-16 -left-16 pointer-events-none"></div>
            <div class="ambient-glow bg-cyan-500/10 w-72 h-72 -bottom-16 -right-16 pointer-events-none"></div>

            <!-- Header -->
            <div class="relative z-10 flex items-center justify-between p-6 border-b border-white/10 bg-slate-950/40">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                        <FolderSync class="w-6 h-6" />
                    </div>
                    <div>
                        <h2 class="text-base font-black text-white">
                            {{ isRTL ? 'المنظم السريع لمكتبة الترفيه' : 'Fast Library Organizer (Scan)' }}
                        </h2>
                        <p class="text-xs text-slate-400">
                            {{ isRTL ? 'نقل وإعادة تسمية الملفات المكتملة إلى H:\\Entertainment' : 'Move, format & index downloaded media into H:\\Entertainment' }}
                        </p>
                    </div>
                </div>

                <button
                    @click="emit('close')"
                    class="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded-xl transition-colors cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Body -->
            <div class="relative z-10 p-6 space-y-4">
                <div class="p-4 rounded-2xl glass-panel border border-white/10 bg-slate-950/40 space-y-3">
                    <div class="flex items-center gap-2 text-xs font-bold text-cyan-400">
                        <HardDrive class="w-4 h-4" />
                        <span>{{ isRTL ? 'المسار القياسي للمكتبة:' : 'Canonical Target Storage:' }}</span>
                    </div>
                    <ul class="text-xs text-slate-300 space-y-1.5 pl-6 font-mono" :class="isRTL ? 'pr-6 pl-0' : ''">
                        <li class="flex items-center gap-2">
                            <span class="text-cyan-400 font-bold">🎬</span>
                            <span>H:\Entertainment\Movies\{Genre}\{Title} ({Year})\...</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-purple-400 font-bold">📺</span>
                            <span>H:\Entertainment\TV Shows\{Show} ({Years})\Season {SS}\...</span>
                        </li>
                    </ul>
                    <p class="text-[11px] text-slate-400 border-t border-white/5 pt-2">
                        {{ isRTL 
                            ? 'سيتم نقل وتنسيق كافة ملفات الفيديو وملفات الترجمة المصاحبة (.srt) وفهرستها فوراً عبر الفاحص الذكي.' 
                            : 'All video files and accompanying Arabic/English subtitles (.srt) will be canonicalized and immediately indexed into the database.' 
                        }}
                    </p>
                </div>

                <!-- Result Message -->
                <div v-if="result" class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs space-y-2">
                    <div class="flex items-center gap-2 font-black">
                        <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
                        <span>{{ isRTL ? `تم تنظيم وفهرسة ${result.processed_count || 0} عنصر بنجاح!` : `Successfully organized and scanned ${result.processed_count || 0} items!` }}</span>
                    </div>
                    <div v-if="result.items && result.items.length > 0" class="max-h-32 overflow-y-auto space-y-1 pt-1 border-t border-emerald-500/20 text-[11px]">
                        <div v-for="(item, idx) in result.items" :key="idx" class="truncate text-slate-300">
                            ✓ {{ item.destination || item.original }}
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="errorMessage" class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs flex items-center gap-3">
                    <AlertCircle class="w-4 h-4 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </div>
            </div>

            <!-- Footer -->
            <div class="relative z-10 p-6 border-t border-white/10 bg-slate-950/60 flex items-center justify-between">
                <button
                    @click="emit('close')"
                    class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer"
                >
                    {{ isRTL ? 'إلغاء' : 'Cancel' }}
                </button>

                <button
                    @click="executeOrganizeAndScan"
                    :disabled="running"
                    class="px-5 py-2.5 rounded-xl text-xs font-black bg-emerald-500 text-slate-950 hover:bg-emerald-400 shadow-lg shadow-emerald-500/20 active:scale-95 transition-all flex items-center gap-2 cursor-pointer disabled:opacity-50"
                >
                    <Loader2 v-if="running" class="w-4 h-4 animate-spin" />
                    <Sparkles v-else class="w-4 h-4" />
                    <span>{{ running ? (isRTL ? 'جارٍ التنظيم والفهرسة...' : 'Organizing & Scanning...') : (isRTL ? 'تنظيم وفحص الآن' : 'Organize & Scan Now') }}</span>
                </button>
            </div>

        </div>
    </div>
</template>
