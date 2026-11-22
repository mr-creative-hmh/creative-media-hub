<script setup lang="ts">
import { ref, computed } from 'vue';
import { formatBytes } from '@/lib/formatters';
import { TorrentTreeNode } from '@/composables/useDownloader';
import {
    ChevronDown,
    ChevronRight,
    ChevronLeft,
    Folder,
    FolderOpen,
    Film,
    FileText,
    File,
    CheckSquare,
    MinusSquare,
    Square,
    Check,
} from '@lucide/vue';

defineOptions({
    name: 'TorrentTreeItem',
});

const props = withDefaults(
    defineProps<{
        node: TorrentTreeNode;
        selectedIndexes: number[];
        depth?: number;
        isRTL?: boolean;
    }>(),
    {
        depth: 0,
        isRTL: false,
    },
);

const emit = defineEmits<{
    (e: 'toggleFile', index: number): void;
    (e: 'toggleFolder', indexes: number[], select: boolean): void;
}>();

const isOpen = ref(true);

const toggleOpen = () => {
    isOpen.value = !isOpen.value;
};

const fileIndexes = computed<number[]>(() => {
    if (props.node.type === 'file') {
        return props.node.index !== undefined ? [props.node.index] : [];
    }
    return props.node.file_indexes || [];
});

const isAllSelected = computed(() => {
    if (fileIndexes.value.length === 0) return false;
    return fileIndexes.value.every((idx) =>
        props.selectedIndexes.includes(idx),
    );
});

const isSomeSelected = computed(() => {
    if (isAllSelected.value) return false;
    return fileIndexes.value.some((idx) => props.selectedIndexes.includes(idx));
});

const isFileSelected = computed(() => {
    if (props.node.type !== 'file' || props.node.index === undefined)
        return false;
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
    return props.isRTL
        ? { paddingRight: px + 'px' }
        : { paddingLeft: px + 'px' };
});
</script>

<template>
    <div class="text-xs select-none">
        <!-- FOLDER ROW -->
        <div
            v-if="node.type === 'folder'"
            @click="toggleOpen"
            class="group flex cursor-pointer items-center justify-between gap-2 rounded-xl border border-transparent px-2.5 py-1.5 transition-all hover:border-white/10 hover:bg-white/[0.06]"
            :style="indentStyle"
        >
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <!-- Expand / Collapse Arrow -->
                <button
                    type="button"
                    class="cursor-pointer rounded p-0.5 text-slate-400 transition-colors group-hover:text-white"
                    @click.stop="toggleOpen"
                >
                    <ChevronDown v-if="isOpen" class="h-3.5 w-3.5" />
                    <ChevronLeft v-else-if="isRTL" class="h-3.5 w-3.5" />
                    <ChevronRight v-else class="h-3.5 w-3.5" />
                </button>

                <!-- Folder Checkbox (Tri-state) -->
                <button
                    type="button"
                    @click="handleFolderCheckboxClick"
                    class="cursor-pointer rounded p-0.5 text-slate-400 transition-transform hover:text-purple-300 active:scale-95"
                    :title="
                        isRTL
                            ? 'تحديد / إلغاء تحديد المجلد بالكامل'
                            : 'Select / Deselect entire folder'
                    "
                >
                    <CheckSquare
                        v-if="isAllSelected"
                        class="h-4 w-4 fill-purple-500/20 text-purple-400"
                    />
                    <MinusSquare
                        v-else-if="isSomeSelected"
                        class="h-4 w-4 fill-purple-400/20 text-purple-300"
                    />
                    <Square
                        v-else
                        class="h-4 w-4 text-slate-500 hover:text-slate-300"
                    />
                </button>

                <!-- Folder Icon -->
                <FolderOpen
                    v-if="isOpen"
                    class="h-4 w-4 shrink-0 text-amber-400"
                />
                <Folder v-else class="h-4 w-4 shrink-0 text-amber-500/80" />

                <!-- Folder Name -->
                <span
                    class="truncate text-[12px] font-bold text-slate-200 group-hover:text-white"
                    :title="node.name"
                >
                    {{ node.name }}
                </span>

                <!-- Video badge if folder has videos -->
                <span
                    v-if="node.video_count && node.video_count > 0"
                    class="py-0.2 flex shrink-0 items-center gap-1 rounded-md border border-purple-500/40 bg-purple-500/20 px-1.5 text-[9px] font-medium text-purple-300"
                >
                    <Film class="h-2.5 w-2.5" />
                    <span
                        >{{ node.video_count }}
                        {{ isRTL ? 'فيديو' : 'vid' }}</span
                    >
                </span>
            </div>

            <!-- Folder Metadata -->
            <div
                class="flex shrink-0 items-center gap-2 font-mono text-[10px] text-slate-400"
            >
                <span class="text-slate-500" v-if="node.file_count">
                    {{ node.file_count }} {{ isRTL ? 'ملف' : 'files' }}
                </span>
                <span class="font-semibold text-slate-300">
                    {{ formatBytes(node.size) }}
                </span>
            </div>
        </div>

        <!-- FOLDER CHILDREN (RECURSIVE) -->
        <div
            v-if="
                node.type === 'folder' &&
                isOpen &&
                node.children &&
                node.children.length > 0
            "
            class="mt-0.5 space-y-0.5"
        >
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
            class="group flex cursor-pointer items-center justify-between gap-2 rounded-xl border px-2.5 py-1.5 transition-all"
            :class="
                isFileSelected
                    ? 'border-purple-500/40 bg-purple-500/15 text-white shadow-sm shadow-purple-950/20'
                    : 'border-transparent bg-black/20 text-slate-400 hover:border-white/5 hover:bg-white/[0.04]'
            "
            :style="indentStyle"
        >
            <div class="flex min-w-0 flex-1 items-center gap-2.5">
                <!-- Checkbox -->
                <div
                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded-md border transition-all"
                    :class="
                        isFileSelected
                            ? 'border-purple-400 bg-purple-500 text-slate-950 shadow-sm shadow-purple-500/40'
                            : 'border-slate-600 bg-black/30 group-hover:border-slate-400'
                    "
                >
                    <Check v-if="isFileSelected" class="h-3 w-3 stroke-[3]" />
                </div>

                <!-- File Type Icon -->
                <Film
                    v-if="node.is_video"
                    class="h-3.5 w-3.5 shrink-0 text-cyan-400"
                />
                <FileText
                    v-else-if="node.is_subtitle"
                    class="h-3.5 w-3.5 shrink-0 text-emerald-400"
                />
                <File v-else class="h-3.5 w-3.5 shrink-0 text-slate-500" />

                <!-- File Name -->
                <span
                    class="truncate font-mono text-[11px] tracking-tight"
                    :class="
                        isFileSelected
                            ? 'font-medium text-slate-100'
                            : 'text-slate-400 group-hover:text-slate-200'
                    "
                    :title="node.name"
                >
                    {{ node.name }}
                </span>

                <!-- Video / Subtitle Tag -->
                <span
                    v-if="node.is_video"
                    class="py-0.2 shrink-0 rounded border border-cyan-500/30 bg-cyan-500/15 px-1.5 font-sans text-[9px] font-bold text-cyan-300 uppercase"
                >
                    {{ node.extension || 'video' }}
                </span>
                <span
                    v-else-if="node.is_subtitle"
                    class="py-0.2 shrink-0 rounded border border-emerald-500/30 bg-emerald-500/15 px-1.5 font-sans text-[9px] font-bold text-emerald-300 uppercase"
                >
                    SUB
                </span>
            </div>

            <!-- File Size -->
            <span
                class="shrink-0 font-mono text-[10px]"
                :class="
                    isFileSelected
                        ? 'font-semibold text-purple-200'
                        : 'text-slate-500'
                "
            >
                {{ formatBytes(node.size) }}
            </span>
        </div>
    </div>
</template>
