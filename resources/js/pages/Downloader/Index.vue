<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import { DownloadCloud, Plus, CheckCircle2 } from 'lucide-vue-next';

const props = defineProps<{
    downloads: any[];
}>();

const { t, isRTL } = useI18n();

const showAddModal = ref(false);
const newTitle = ref('');
const newUrl = ref('');
const newType = ref('movie');

const handleAddDownload = async () => {
    if (!newTitle.value) return;

    try {
        const res = await fetch('/api/downloads', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                title: newTitle.value,
                media_type: newType.value,
                source_url: newUrl.value,
            }),
        });

        if (res.ok) {
            window.location.reload();
        }
    } finally {
        showAddModal.value = false;
    }
};
</script>

<template>
    <Head :title="t('nav.downloads')" />

    <AppLayout v-slot="{ play }">
        <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                    <DownloadCloud class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                        {{ t('nav.downloads') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                        {{ isRTL ? 'مراقبة المجلدات التلقائية وإدارة طابور تنزيل الوسائط.' : 'Manage download queue and incoming folder watch triggers.' }}
                    </p>
                </div>
            </div>

            <button
                @click="showAddModal = true"
                class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-extrabold text-xs shadow-lg shadow-cyan-500/20 active:scale-95 transition-all"
            >
                <Plus class="w-4 h-4" />
                <span>{{ isRTL ? 'إضافة تنزيل جديد' : 'Add Download' }}</span>
            </button>
        </div>

        <!-- Active Downloads List -->
        <div class="glass-panel rounded-3xl p-6 border border-white/10 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <h3 class="font-bold text-base text-white">
                    {{ isRTL ? 'طابور التنزيلات النشطة' : 'Active Download Queue' }}
                </h3>
            </div>

            <div v-if="downloads.length > 0" class="divide-y divide-white/5">
                <div
                    v-for="d in downloads"
                    :key="d.id"
                    class="py-4 flex items-center justify-between flex-wrap gap-4 hover:bg-white/[0.02] px-2 rounded-xl transition-colors"
                >
                    <div class="flex-1 min-w-[200px]">
                        <h4 class="font-bold text-sm text-white">{{ d.title }}</h4>
                        <p class="text-xs text-slate-400 font-mono mt-0.5 truncate">{{ d.destination_path }}</p>
                        <div class="w-full h-1.5 rounded-full bg-white/10 mt-2 overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-cyan-400 to-blue-500 rounded-full" style="width: 45%"></div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <span class="cinema-badge bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">
                            {{ d.status }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-12">
                <CheckCircle2 class="w-12 h-12 text-slate-600 mx-auto mb-2" />
                <h4 class="font-bold text-white text-base">
                    {{ isRTL ? 'لا توجد تنزيلات نشطة حالياً' : 'No active downloads in queue' }}
                </h4>
            </div>
        </div>

        <!-- Add Modal -->
        <div
            v-if="showAddModal"
            class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4"
            @click.self="showAddModal = false"
        >
            <div class="glass-panel rounded-3xl p-6 sm:p-8 max-w-md w-full border border-white/15 shadow-2xl space-y-4">
                <h3 class="font-bold text-lg text-white">
                    {{ isRTL ? 'إضافة وسائط للتنزيل' : 'Add New Media Download' }}
                </h3>

                <div class="space-y-3">
                    <input
                        type="text"
                        v-model="newTitle"
                        placeholder="e.g. Gladiator II (2024)"
                        class="w-full h-11 rounded-xl bg-white/[0.04] border border-white/15 px-4 text-sm text-white focus:border-cyan-500 outline-none"
                    />
                    <input
                        type="text"
                        v-model="newUrl"
                        placeholder="Direct URL or Magnet Link"
                        class="w-full h-11 rounded-xl bg-white/[0.04] border border-white/15 px-4 text-sm text-white focus:border-cyan-500 outline-none"
                    />
                </div>

                <div class="flex items-center justify-end gap-3 pt-3">
                    <button
                        @click="showAddModal = false"
                        class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-bold text-slate-300"
                    >
                        {{ t('common.close') }}
                    </button>
                    <button
                        @click="handleAddDownload"
                        class="px-5 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-black"
                    >
                        {{ t('common.download') }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
