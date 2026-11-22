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
            class="hover:border-border/80 hover:bg-muted/50 group flex cursor-pointer items-center justify-between gap-2 rounded-xl border border-transparent px-2.5 py-1.5 transition-all"
            :style="indentStyle"
        >
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <!-- Expand / Collapse Arrow -->
                <button
                    type="button"
                    class="text-muted-foreground group-hover:text-foreground cursor-pointer rounded p-0.5 transition-colors"
                    @click.stop="toggleOpen"
                >
                    <ChevronDown v-if="isOpen" class="size-3.5" />
                    <ChevronLeft v-else-if="isRTL" class="size-3.5" />
                    <ChevronRight v-else class="size-3.5" />
                </button>

                <!-- Folder Checkbox (Tri-state) -->
                <button
                    type="button"
                    @click="handleFolderCheckboxClick"
                    class="text-muted-foreground hover:text-primary cursor-pointer rounded p-0.5 transition-transform active:scale-95"
                    :title="
                        isRTL
                            ? 'تحديد / إلغاء تحديد المجلد بالكامل'
                            : 'Select / Deselect entire folder'
                    "
                >
                    <CheckSquare
                        v-if="isAllSelected"
                        class="fill-primary/20 text-primary size-4"
                    />
                    <MinusSquare
                        v-else-if="isSomeSelected"
                        class="fill-primary/15 text-primary/80 size-4"
                    />
                    <Square
                        v-else
                        class="text-muted-foreground hover:text-foreground size-4"
                    />
                </button>

                <!-- Folder Icon -->
                <FolderOpen
                    v-if="isOpen"
                    class="size-4 shrink-0 text-amber-400"
                />
                <Folder v-else class="size-4 shrink-0 text-amber-500/80" />

                <!-- Folder Name -->
                <span
                    class="text-foreground truncate text-[12px] font-bold"
                    :title="node.name"
                >
                    {{ node.name }}
                </span>

                <!-- Video badge if folder has videos -->
                <span
                    v-if="node.video_count && node.video_count > 0"
                    class="border-primary/35 bg-primary/15 py-0.2 text-primary flex shrink-0 items-center gap-1 rounded-md border px-1.5 text-[9px] font-medium tabular-nums"
                >
                    <Film class="size-2.5" />
                    <span
                        >{{ node.video_count }}
                        {{ isRTL ? 'فيديو' : 'vid' }}</span
                    >
                </span>
            </div>

            <!-- Folder Metadata -->
            <div
                class="text-muted-foreground flex shrink-0 items-center gap-2 font-mono text-[10px] tabular-nums"
            >
                <span v-if="node.file_count">
                    {{ node.file_count }} {{ isRTL ? 'ملف' : 'files' }}
                </span>
                <span class="text-foreground font-semibold">
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
                    ? 'border-primary/40 bg-primary/15 text-foreground shadow-xs'
                    : 'bg-card/30 text-muted-foreground hover:border-border/60 hover:bg-muted/35 border-transparent'
            "
            :style="indentStyle"
        >
            <div class="flex min-w-0 flex-1 items-center gap-2.5">
                <!-- Checkbox -->
                <div
                    class="flex size-4 shrink-0 items-center justify-center rounded-md border transition-all"
                    :class="
                        isFileSelected
                            ? 'border-primary bg-primary text-primary-foreground shadow-primary/30 shadow-xs'
                            : 'border-border bg-card/50 group-hover:border-primary/50'
                    "
                >
                    <Check v-if="isFileSelected" class="size-3 stroke-[3]" />
                </div>

                <!-- File Type Icon -->
                <Film
                    v-if="node.is_video"
                    class="text-primary size-3.5 shrink-0"
                />
                <FileText
                    v-else-if="node.is_subtitle"
                    class="size-3.5 shrink-0 text-emerald-400"
                />
                <File v-else class="text-muted-foreground size-3.5 shrink-0" />

                <!-- File Name -->
                <span
                    class="truncate font-mono text-[11px] tracking-tight"
                    :class="
                        isFileSelected
                            ? 'text-foreground font-medium'
                            : 'text-muted-foreground group-hover:text-foreground'
                    "
                    :title="node.name"
                >
                    {{ node.name }}
                </span>

                <!-- Video / Subtitle Tag -->
                <span
                    v-if="node.is_video"
                    class="border-primary/30 bg-primary/15 py-0.2 text-primary shrink-0 rounded border px-1.5 font-sans text-[9px] font-bold uppercase"
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
                class="shrink-0 font-mono text-[10px] tabular-nums"
                :class="
                    isFileSelected
                        ? 'text-primary font-semibold'
                        : 'text-muted-foreground'
                "
            >
                {{ formatBytes(node.size) }}
            </span>
        </div>
    </div>
</template>
