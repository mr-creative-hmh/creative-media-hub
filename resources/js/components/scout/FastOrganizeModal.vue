<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    X,
    FolderSync,
    CheckCircle2,
    AlertCircle,
    Loader2,
    HardDrive,
    ArrowRight,
    Sparkles,
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
        const csrfToken =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') || '';
        const res = await fetch('/api/scout/organize-and-scan', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({}),
        });
        const data = await res.json();
        result.value = data;
        emit('organized');
    } catch (err: any) {
        errorMessage.value =
            err?.message || 'Failed to organize and scan files';
    } finally {
        running.value = false;
    }
};
</script>

<template>
    <div
        v-if="isOpen"
        class="animate-fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-md"
        :dir="isRTL ? 'rtl' : 'ltr'"
    >
        <div
            class="glass-panel relative flex w-full max-w-xl flex-col overflow-hidden rounded-3xl border border-cyan-500/20 bg-slate-900/95 shadow-2xl"
        >
            <!-- Ambient Glows -->
            <div
                class="ambient-glow pointer-events-none -top-16 -left-16 h-72 w-72 bg-emerald-500/10"
            ></div>
            <div
                class="ambient-glow pointer-events-none -right-16 -bottom-16 h-72 w-72 bg-cyan-500/10"
            ></div>

            <!-- Header -->
            <div
                class="relative z-10 flex items-center justify-between border-b border-white/10 bg-slate-950/40 p-6"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="rounded-xl border border-emerald-500/30 bg-emerald-500/20 p-2.5 text-emerald-400"
                    >
                        <FolderSync class="h-6 w-6" />
                    </div>
                    <div>
                        <h2 class="text-base font-black text-white">
                            {{
                                isRTL
                                    ? 'المنظم السريع لمكتبة الترفيه'
                                    : 'Fast Library Organizer (Scan)'
                            }}
                        </h2>
                        <p class="text-xs text-slate-400">
                            {{
                                isRTL
                                    ? 'نقل وإعادة تسمية الملفات المكتملة إلى H:\\Entertainment'
                                    : 'Move, format & index downloaded media into H:\\Entertainment'
                            }}
                        </p>
                    </div>
                </div>

                <button
                    @click="emit('close')"
                    class="cursor-pointer rounded-xl p-2 text-slate-400 transition-colors hover:bg-white/10 hover:text-white"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Body -->
            <div class="relative z-10 space-y-4 p-6">
                <div
                    class="glass-panel space-y-3 rounded-2xl border border-white/10 bg-slate-950/40 p-4"
                >
                    <div
                        class="flex items-center gap-2 text-xs font-bold text-cyan-400"
                    >
                        <HardDrive class="h-4 w-4" />
                        <span>{{
                            isRTL
                                ? 'المسار القياسي للمكتبة:'
                                : 'Canonical Target Storage:'
                        }}</span>
                    </div>
                    <ul
                        class="space-y-1.5 pl-6 font-mono text-xs text-slate-300"
                        :class="isRTL ? 'pr-6 pl-0' : ''"
                    >
                        <li class="flex items-center gap-2">
                            <span class="font-bold text-cyan-400">🎬</span>
                            <span
                                >H:\Entertainment\Movies\{Genre}\{Title}
                                ({Year})\...</span
                            >
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="font-bold text-purple-400">📺</span>
                            <span
                                >H:\Entertainment\TV Shows\{Show}
                                ({Years})\Season {SS}\...</span
                            >
                        </li>
                    </ul>
                    <p
                        class="border-t border-white/5 pt-2 text-[11px] text-slate-400"
                    >
                        {{
                            isRTL
                                ? 'سيتم نقل وتنسيق كافة ملفات الفيديو وملفات الترجمة المصاحبة (.srt) وفهرستها فوراً عبر الفاحص الذكي.'
                                : 'All video files and accompanying Arabic/English subtitles (.srt) will be canonicalized and immediately indexed into the database.'
                        }}
                    </p>
                </div>

                <!-- Result Message -->
                <div
                    v-if="result"
                    class="space-y-2 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-xs text-emerald-300"
                >
                    <div class="flex items-center gap-2 font-black">
                        <CheckCircle2
                            class="h-4 w-4 shrink-0 text-emerald-400"
                        />
                        <span>{{
                            isRTL
                                ? `تم تنظيم وفهرسة ${result.processed_count || 0} عنصر بنجاح!`
                                : `Successfully organized and scanned ${result.processed_count || 0} items!`
                        }}</span>
                    </div>
                    <div
                        v-if="result.items && result.items.length > 0"
                        class="max-h-32 space-y-1 overflow-y-auto border-t border-emerald-500/20 pt-1 text-[11px]"
                    >
                        <div
                            v-for="(item, idx) in result.items"
                            :key="idx"
                            class="truncate text-slate-300"
                        >
                            ✓ {{ item.destination || item.original }}
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <div
                    v-if="errorMessage"
                    class="flex items-center gap-3 rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 text-xs text-rose-400"
                >
                    <AlertCircle class="h-4 w-4 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="relative z-10 flex items-center justify-between border-t border-white/10 bg-slate-950/60 p-6"
            >
                <button
                    @click="emit('close')"
                    class="cursor-pointer rounded-xl px-4 py-2 text-xs font-semibold text-slate-400 transition-colors hover:bg-white/5 hover:text-white"
                >
                    {{ isRTL ? 'إلغاء' : 'Cancel' }}
                </button>

                <button
                    @click="executeOrganizeAndScan"
                    :disabled="running"
                    class="flex cursor-pointer items-center gap-2 rounded-xl bg-emerald-500 px-5 py-2.5 text-xs font-black text-slate-950 shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-400 active:scale-95 disabled:opacity-50"
                >
                    <Loader2 v-if="running" class="h-4 w-4 animate-spin" />
                    <Sparkles v-else class="h-4 w-4" />
                    <span>{{
                        running
                            ? isRTL
                                ? 'جارٍ التنظيم والفهرسة...'
                                : 'Organizing & Scanning...'
                            : isRTL
                              ? 'تنظيم وفحص الآن'
                              : 'Organize & Scan Now'
                    }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
