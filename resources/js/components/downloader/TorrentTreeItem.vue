<script setup lang="ts">
import { ref, computed } from 'vue';
import { TorrentTreeNode } from '@/composables/useDownloader';
import {
    ChevronDown, ChevronRight, ChevronLeft,
    Folder, FolderOpen, Film, FileText, File,
    CheckSquare, MinusSquare, Square, Check
} from 'lucide-vue-next';

defineOptions({
    name: 'TorrentTreeItem'
});

const props = withDefaults(defineProps<{
    node: TorrentTreeNode;
    selectedIndexes: number[];
    depth?: number;
    isRTL?: boolean;
}>(), {
    depth: 0,
    isRTL: false,
});

const emit = defineEmits<{
    (e: 'toggleFile', index: number): void;
    (e: 'toggleFolder', indexes: number[], select: boolean): void;
}>();

const isOpen = ref(true);

const toggleOpen = () => {
    isOpen.value = !isOpen.value;
};

const formatBytes = (bytes: number) => {
    if (!bytes || bytes <= 0) return '0 B';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
};

const fileIndexes = computed<number[]>(() => {
    if (props.node.type === 'file') {
        return props.node.index !== undefined ? [props.node.index] : [];
    }
    return props.node.file_indexes || [];
});

const isAllSelected = computed(() => {
    if (fileIndexes.value.length === 0) return false;
    return fileIndexes.value.every(idx => props.selectedIndexes.includes(idx));
});

const isSomeSelected = computed(() => {
    if (isAllSelected.value) return false;
    return fileIndexes.value.some(idx => props.selectedIndexes.includes(idx));
});

const isFileSelected = computed(() => {
    if (props.node.type !== 'file' || props.node.index === undefined) return false;
    return props.selectedIndexes.includes(props.node.index);
});

const handleFolderCheckboxClick = (e: MouseEvent) => {
    e.stopPropagation();
    if (fileIndexes.value.length === 0) return;
    const targetState = !isAllSelected.value;
    emit('toggleFolder', fileIndexes.value, targetState);
};

const handleFileClick = () => {
    if (props.node.index !== undefined) {
        emit('toggleFile', props.node.index);
    }
};

const indentStyle = computed(() => {
    const px = props.depth * 16 + 8;
    return props.isRTL ? { paddingRight: px + 'px' } : { paddingLeft: px + 'px' };
});
</script>

<template>
    <div class="select-none text-xs">
        <!-- FOLDER ROW -->
        <div
            v-if="node.type === 'folder'"
            @click="toggleOpen"
            class="group flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-xl transition-all cursor-pointer hover:bg-white/[0.06] border border-transparent hover:border-white/10"
            :style="indentStyle"
        >
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <!-- Expand / Collapse Arrow -->
                <button
                    type="button"
                    class="p-0.5 rounded text-slate-400 group-hover:text-white transition-colors cursor-pointer"
                    @click.stop="toggleOpen"
                >
                    <ChevronDown v-if="isOpen" class="w-3.5 h-3.5" />
                    <ChevronLeft v-else-if="isRTL" class="w-3.5 h-3.5" />
                    <ChevronRight v-else class="w-3.5 h-3.5" />
                </button>

                <!-- Folder Checkbox (Tri-state) -->
                <button
                    type="button"
                    @click="handleFolderCheckboxClick"
                    class="p-0.5 rounded transition-transform active:scale-95 cursor-pointer text-slate-400 hover:text-purple-300"
                    :title="isRTL ? 'تحديد / إلغاء تحديد المجلد بالكامل' : 'Select / Deselect entire folder'"
                >
                    <CheckSquare v-if="isAllSelected" class="w-4 h-4 text-purple-400 fill-purple-500/20" />
                    <MinusSquare v-else-if="isSomeSelected" class="w-4 h-4 text-purple-300 fill-purple-400/20" />
                    <Square v-else class="w-4 h-4 text-slate-500 hover:text-slate-300" />
                </button>

                <!-- Folder Icon -->
                <FolderOpen v-if="isOpen" class="w-4 h-4 text-amber-400 shrink-0" />
                <Folder v-else class="w-4 h-4 text-amber-500/80 shrink-0" />

                <!-- Folder Name -->
                <span class="font-bold text-slate-200 truncate group-hover:text-white text-[12px]" :title="node.name">
                    {{ node.name }}
                </span>

                <!-- Video badge if folder has videos -->
                <span
                    v-if="node.video_count && node.video_count > 0"
                    class="px-1.5 py-0.2 rounded-md bg-purple-500/20 border border-purple-500/40 text-purple-300 text-[9px] font-medium shrink-0 flex items-center gap-1"
                >
                    <Film class="w-2.5 h-2.5" />
                    <span>{{ node.video_count }} {{ isRTL ? 'فيديو' : 'vid' }}</span>
                </span>
            </div>

            <!-- Folder Metadata -->
            <div class="flex items-center gap-2 text-[10px] font-mono text-slate-400 shrink-0">
                <span class="text-slate-500" v-if="node.file_count">
                    {{ node.file_count }} {{ isRTL ? 'ملف' : 'files' }}
                </span>
                <span class="text-slate-300 font-semibold">
                    {{ formatBytes(node.size) }}
                </span>
            </div>
        </div>

        <!-- FOLDER CHILDREN (RECURSIVE) -->
        <div v-if="node.type === 'folder' && isOpen && node.children && node.children.length > 0" class="space-y-0.5 mt-0.5">
            <TorrentTreeItem
                v-for="child in node.children"
                :key="child.path"
                :node="child"
                :selected-indexes="selectedIndexes"
                :depth="depth + 1"
                :is-r-t-l="isRTL"
                @toggle-file="emit('toggleFile', $event)"
                @toggle-folder="(idxs, sel) => emit('toggleFolder', idxs, sel)"
            />
        </div>

        <!-- FILE ROW -->
        <div
            v-else-if="node.type === 'file'"
            @click="handleFileClick"
            class="group flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-xl transition-all cursor-pointer border"
            :class="isFileSelected
                ? 'bg-purple-500/15 border-purple-500/40 text-white shadow-sm shadow-purple-950/20'
                : 'bg-black/20 border-transparent text-slate-400 hover:bg-white/[0.04] hover:border-white/5'"
            :style="indentStyle"
        >
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                <!-- Checkbox -->
                <div
                    class="w-4 h-4 rounded-md border flex items-center justify-center shrink-0 transition-all"
                    :class="isFileSelected
                        ? 'border-purple-400 bg-purple-500 text-slate-950 shadow-sm shadow-purple-500/40'
                        : 'border-slate-600 group-hover:border-slate-400 bg-black/30'"
                >
                    <Check v-if="isFileSelected" class="w-3 h-3 stroke-[3]" />
                </div>

                <!-- File Type Icon -->
                <Film v-if="node.is_video" class="w-3.5 h-3.5 text-cyan-400 shrink-0" />
                <FileText v-else-if="node.is_subtitle" class="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                <File v-else class="w-3.5 h-3.5 text-slate-500 shrink-0" />

                <!-- File Name -->
                <span
                    class="truncate text-[11px] font-mono tracking-tight"
                    :class="isFileSelected ? 'text-slate-100 font-medium' : 'text-slate-400 group-hover:text-slate-200'"
                    :title="node.name"
                >
                    {{ node.name }}
                </span>

                <!-- Video / Subtitle Tag -->
                <span
                    v-if="node.is_video"
                    class="px-1.5 py-0.2 rounded bg-cyan-500/15 text-cyan-300 border border-cyan-500/30 text-[9px] font-sans font-bold uppercase shrink-0"
                >
                    {{ node.extension || 'video' }}
                </span>
                <span
                    v-else-if="node.is_subtitle"
                    class="px-1.5 py-0.2 rounded bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 text-[9px] font-sans font-bold uppercase shrink-0"
                >
                    SUB
                </span>
            </div>

            <!-- File Size -->
            <span
                class="text-[10px] font-mono shrink-0"
                :class="isFileSelected ? 'text-purple-200 font-semibold' : 'text-slate-500'"
            >
                {{ formatBytes(node.size) }}
            </span>
        </div>
    </div>
</template>