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
    Sparkles,
} from '@lucide/vue';

defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'organized'): void;
}>();

const { isRTL } = useI18n();

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
        @click.self="emit('close')"
    >
        <div
            class="cf-glass-elevated border-border/80 relative flex w-full max-w-xl flex-col overflow-hidden rounded-3xl border shadow-2xl"
        >
            <!-- Ambient Glows -->
            <div
                class="bg-primary/10 pointer-events-none absolute -start-16 -top-16 size-72 rounded-full blur-3xl"
            ></div>
            <div
                class="pointer-events-none absolute -end-16 -bottom-16 size-72 rounded-full bg-emerald-500/10 blur-3xl"
            ></div>

            <!-- Header -->
            <div
                class="border-border/70 bg-card/50 relative z-10 flex items-center justify-between border-b p-6"
            >
                <div class="flex items-center gap-3.5">
                    <div
                        class="bg-primary/12 text-primary border-primary/25 flex size-11 shrink-0 items-center justify-center rounded-2xl border shadow-xs"
                    >
                        <FolderSync class="size-5" />
                    </div>
                    <div>
                        <h2
                            class="text-foreground text-base font-black tracking-tight"
                        >
                            {{
                                isRTL
                                    ? 'المنظم السريع لمكتبة الترفيه'
                                    : 'Fast Library Organizer (Scan)'
                            }}
                        </h2>
                        <p class="text-muted-foreground text-xs">
                            {{
                                isRTL
                                    ? 'نقل وإعادة تسمية الملفات المكتملة إلى H:\\Entertainment'
                                    : 'Move, format & index downloaded media into H:\\Entertainment'
                            }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="emit('close')"
                    class="text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer rounded-xl p-2 transition-colors"
                >
                    <X class="size-5" />
                </button>
            </div>

            <!-- Body -->
            <div class="relative z-10 space-y-4 p-6">
                <div
                    class="cf-glass border-border/80 space-y-3 rounded-2xl border p-4"
                >
                    <div
                        class="text-primary flex items-center gap-2 text-xs font-bold"
                    >
                        <HardDrive class="size-4" />
                        <span>{{
                            isRTL
                                ? 'المسار القياسي للمكتبة:'
                                : 'Canonical Target Storage:'
                        }}</span>
                    </div>
                    <ul
                        class="text-foreground/90 space-y-1.5 ps-6 font-mono text-xs"
                    >
                        <li class="flex items-center gap-2">
                            <span class="text-primary font-bold">🎬</span>
                            <span
                                >H:\Entertainment\Movies\{Genre}\{Title}
                                ({Year})\...</span
                            >
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-primary font-bold">📺</span>
                            <span
                                >H:\Entertainment\TV Shows\{Show}
                                ({Years})\Season {SS}\...</span
                            >
                        </li>
                    </ul>
                    <p
                        class="border-border/60 text-muted-foreground border-t pt-2 text-[11px]"
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
                    class="space-y-2 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-xs text-emerald-300 tabular-nums"
                >
                    <div class="flex items-center gap-2 font-black">
                        <CheckCircle2
                            class="size-4 shrink-0 text-emerald-400"
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
                            class="text-foreground/85 truncate"
                        >
                            ✓ {{ item.destination || item.original }}
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <div
                    v-if="errorMessage"
                    class="border-destructive/30 bg-destructive/10 text-destructive flex items-center gap-3 rounded-2xl border p-4 text-xs"
                >
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="border-border/70 bg-card/60 relative z-10 flex items-center justify-between border-t p-5"
            >
                <button
                    type="button"
                    @click="emit('close')"
                    class="text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer rounded-xl px-4 py-2 text-xs font-semibold transition-colors"
                >
                    {{ isRTL ? 'إلغاء' : 'Cancel' }}
                </button>

                <button
                    type="button"
                    @click="executeOrganizeAndScan"
                    :disabled="running"
                    class="bg-primary text-primary-foreground shadow-primary/25 flex cursor-pointer items-center gap-2 rounded-xl px-5 py-2.5 text-xs font-black shadow-lg transition-all hover:brightness-105 active:scale-95 disabled:opacity-50"
                >
                    <Loader2 v-if="running" class="size-4 animate-spin" />
                    <Sparkles v-else class="size-4" />
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
