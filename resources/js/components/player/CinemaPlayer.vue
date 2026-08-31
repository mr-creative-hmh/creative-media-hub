<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Play, Pause, Volume2, VolumeX, Maximize, Minimize,
    X, RotateCcw, RotateCw, Subtitles, Settings, Check, Sparkles, ExternalLink
} from 'lucide-vue-next';

const props = defineProps<{
    item: {
        id: number;
        title: string;
        title_ar?: string;
        watchable_id?: number;
        watchable_type?: string;
        subtitles?: Array<{ id: number; language: string; language_name?: string; format?: string; file_path?: string }>;
    };
}>();

const emit = defineEmits(['close']);

const { isRTL, t } = useI18n();

const videoRef = ref<HTMLVideoElement | null>(null);
const playerContainer = ref<HTMLDivElement | null>(null);

const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(0);
const volume = ref(1);
const isMuted = ref(false);
const isFullscreen = ref(false);
const showControls = ref(true);
const playbackRate = ref(1.0);
const selectedSubtitleId = ref<number | 'off'>('off');
const showSubtitleMenu = ref(false);
const showSpeedMenu = ref(false);

let controlsTimeout: any = null;
let progressInterval: any = null;

const streamUrl = computed(() => {
    const isEpisode = props.item.watchable_type === 'episode';
    const mediaId = props.item.watchable_id || props.item.id;
    return isEpisode ? `/stream/episode/${mediaId}` : `/stream/movie/${mediaId}`;
});

const formatTime = (seconds: number) => {
    if (isNaN(seconds) || seconds < 0) return '00:00';
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);

    if (hrs > 0) {
        return `${hrs}:${mins < 10 ? '0' : ''}${mins}:${secs < 10 ? '0' : ''}${secs}`;
    }
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
};

const togglePlay = () => {
    if (!videoRef.value) return;
    if (videoRef.value.paused) {
        videoRef.value.play();
        isPlaying.value = true;
    } else {
        videoRef.value.pause();
        isPlaying.value = false;
    }
};

const seek = (e: MouseEvent) => {
    if (!videoRef.value) return;
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    const pos = (e.clientX - rect.left) / rect.width;
    videoRef.value.currentTime = pos * duration.value;
};

const skip = (seconds: number) => {
    if (!videoRef.value) return;
    videoRef.value.currentTime = Math.max(0, Math.min(duration.value, videoRef.value.currentTime + seconds));
};

const toggleMute = () => {
    if (!videoRef.value) return;
    isMuted.value = !isMuted.value;
    videoRef.value.muted = isMuted.value;
};

const setSpeed = (rate: number) => {
    if (!videoRef.value) return;
    playbackRate.value = rate;
    videoRef.value.playbackRate = rate;
    showSpeedMenu.value = false;
};

const applySubtitleTrack = (subId: number | 'off') => {
    selectedSubtitleId.value = subId;
    showSubtitleMenu.value = false;

    if (!videoRef.value) return;
    const tracks = videoRef.value.textTracks;
    
    if (subId === 'off') {
        for (let i = 0; i < tracks.length; i++) {
            tracks[i].mode = 'disabled';
        }
        return;
    }

    const targetSub = props.item.subtitles?.find(s => s.id === subId);
    if (!targetSub) return;

    for (let i = 0; i < tracks.length; i++) {
        const track = tracks[i];
        // Match by label or language
        if (track.label === (targetSub.language_name || targetSub.language) || track.language === targetSub.language) {
            track.mode = 'showing';
        } else {
            track.mode = 'disabled';
        }
    }
};

const toggleFullscreen = () => {
    if (!playerContainer.value) return;
    if (!document.fullscreenElement) {
        playerContainer.value.requestFullscreen().then(() => {
            isFullscreen.value = true;
        });
    } else {
        document.exitFullscreen().then(() => {
            isFullscreen.value = false;
        });
    }
};

const handleMouseMove = () => {
    showControls.value = true;
    clearTimeout(controlsTimeout);
    controlsTimeout = setTimeout(() => {
        if (isPlaying.value) {
            showControls.value = false;
            showSubtitleMenu.value = false;
            showSpeedMenu.value = false;
        }
    }, 3500);
};

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === ' ' || e.code === 'Space') {
        e.preventDefault();
        togglePlay();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        skip(isRTL.value ? -10 : 10);
    } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        skip(isRTL.value ? 10 : -10);
    } else if (e.key === 'f' || e.key === 'F') {
        e.preventDefault();
        toggleFullscreen();
    } else if (e.key === 'm' || e.key === 'M') {
        e.preventDefault();
        toggleMute();
    } else if (e.key === 'Escape') {
        emit('close');
    }
};

const reportProgress = async () => {
    if (!videoRef.value || duration.value === 0) return;
    try {
        await fetch('/api/playback/progress', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                watchable_id: props.item.watchable_id || props.item.id,
                watchable_type: props.item.watchable_type === 'episode' ? 'episode' : 'movie',
                progress_seconds: Math.floor(currentTime.value),
                duration_seconds: Math.floor(duration.value),
            }),
        });
    } catch (e) {
        // silent fail
    }
};

const getLanguageDisplayName = (sub: any) => {
    if (sub.language === 'ar' || sub.language_name === 'Arabic') return isRTL.value ? 'العربية (Arabic)' : 'Arabic (العربية)';
    if (sub.language === 'en' || sub.language_name === 'English') return 'English';
    if (sub.language === 'fr') return 'French (Français)';
    if (sub.language === 'es') return 'Spanish (Español)';
    if (sub.language === 'de') return 'German (Deutsch)';
    return sub.language_name || sub.language?.toUpperCase() || 'Subtitle';
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    progressInterval = setInterval(reportProgress, 5000);

    // Auto-select Arabic or first subtitle if available
    if (props.item.subtitles && props.item.subtitles.length > 0) {
        const arSub = props.item.subtitles.find(s => s.language === 'ar');
        const defaultSub = arSub || props.item.subtitles[0];
        setTimeout(() => {
            applySubtitleTrack(defaultSub.id);
        }, 800);
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    clearInterval(progressInterval);
    clearTimeout(controlsTimeout);
    reportProgress();
});
</script>

<template>
    <div
        ref="playerContainer"
        @mousemove="handleMouseMove"
        class="fixed inset-0 z-50 bg-black flex items-center justify-center overflow-hidden select-none font-sans"
    >
        <!-- HTML5 Video Element with Dynamic Subtitle Tracks -->
        <video
            ref="videoRef"
            :src="streamUrl"
            autoplay
            playsinline
            class="w-full h-full object-contain cursor-pointer"
            @click="togglePlay"
            @timeupdate="currentTime = videoRef?.currentTime || 0"
            @loadedmetadata="duration = videoRef?.duration || 0"
            @play="isPlaying = true"
            @pause="isPlaying = false"
        >
            <track
                v-for="sub in (item.subtitles || [])"
                :key="sub.id"
                :label="sub.language_name || sub.language"
                kind="subtitles"
                :srclang="sub.language || 'und'"
                :src="`/stream/subtitles/${sub.id}`"
            />
        </video>

        <!-- Top Header Bar -->
        <div
            class="absolute top-0 inset-x-0 p-6 bg-gradient-to-b from-black/80 via-black/40 to-transparent flex items-center justify-between transition-opacity duration-300 z-10"
            :class="showControls ? 'opacity-100' : 'opacity-0 pointer-events-none'"
        >
            <div class="flex items-center gap-3">
                <div class="cinema-badge bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                    Cinema Player
                </div>
                <h2 class="text-base sm:text-lg font-bold text-white drop-shadow truncate max-w-xl">
                    {{ isRTL && item.title_ar ? item.title_ar : item.title }}
                </h2>
            </div>
            <button
                @click="emit('close')"
                class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center backdrop-blur-md transition-colors cursor-pointer"
                title="Close Player (Esc)"
            >
                <X class="w-5 h-5" />
            </button>
        </div>

        <!-- Center Large Play Pulse Button when Paused -->
        <div
            v-if="!isPlaying"
            @click="togglePlay"
            class="absolute inset-0 flex items-center justify-center bg-black/30 cursor-pointer pointer-events-auto"
        >
            <div class="w-20 h-20 rounded-full bg-cyan-500/90 text-slate-950 flex items-center justify-center shadow-2xl shadow-cyan-500/50 hover:scale-110 transition-transform">
                <Play class="w-10 h-10 fill-current ml-1" />
            </div>
        </div>

        <!-- Bottom Controls Bar -->
        <div
            class="absolute bottom-0 inset-x-0 p-6 bg-gradient-to-t from-black/90 via-black/50 to-transparent transition-opacity duration-300 z-10"
            :class="showControls ? 'opacity-100' : 'opacity-0 pointer-events-none'"
        >
            <!-- Time Scrubber Progress Bar -->
            <div
                @click="seek"
                class="w-full h-2 rounded-full bg-white/20 hover:h-3 cursor-pointer transition-all relative mb-4 group"
            >
                <div
                    class="h-full bg-gradient-to-r from-cyan-400 via-blue-500 to-indigo-500 rounded-full relative"
                    :style="{ width: `${(currentTime / Math.max(1, duration)) * 100}%` }"
                >
                    <div class="absolute -right-1.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 bg-white rounded-full shadow opacity-0 group-hover:opacity-100 transition-opacity"></div>
                </div>
            </div>

            <div class="flex items-center justify-between gap-4 text-white">
                <!-- Left Play & Time Controls -->
                <div class="flex items-center gap-4">
                    <button @click="togglePlay" class="hover:text-cyan-400 transition-colors cursor-pointer">
                        <Play v-if="!isPlaying" class="w-6 h-6 fill-current" />
                        <Pause v-else class="w-6 h-6 fill-current" />
                    </button>
                    <button @click="skip(-10)" class="hover:text-cyan-400 transition-colors cursor-pointer" title="Rewind 10s">
                        <RotateCcw class="w-5 h-5" />
                    </button>
                    <button @click="skip(10)" class="hover:text-cyan-400 transition-colors cursor-pointer" title="Forward 10s">
                        <RotateCw class="w-5 h-5" />
                    </button>

                    <!-- Volume Control -->
                    <div class="flex items-center gap-2 group/vol">
                        <button @click="toggleMute" class="hover:text-cyan-400 transition-colors cursor-pointer">
                            <VolumeX v-if="isMuted || volume === 0" class="w-5 h-5 text-red-400" />
                            <Volume2 v-else class="w-5 h-5" />
                        </button>
                        <input
                            type="range"
                            min="0"
                            max="1"
                            step="0.05"
                            v-model.number="volume"
                            @input="if (videoRef) { videoRef.volume = volume; isMuted = false; }"
                            class="w-16 h-1 accent-cyan-400 bg-white/20 rounded-lg cursor-pointer"
                        />
                    </div>

                    <!-- Timestamps -->
                    <span class="text-xs font-mono text-slate-300">
                        {{ formatTime(currentTime) }} / {{ formatTime(duration) }}
                    </span>
                </div>

                <!-- Right Subtitle, Speed & Fullscreen Controls -->
                <div class="flex items-center gap-4 relative">
                    <!-- Subtitles Selector Menu -->
                    <div class="relative">
                        <button
                            @click="showSubtitleMenu = !showSubtitleMenu; showSpeedMenu = false"
                            class="hover:text-cyan-400 transition-colors p-1.5 rounded-lg hover:bg-white/10 cursor-pointer flex items-center gap-1"
                            :class="selectedSubtitleId !== 'off' ? 'text-cyan-400 font-bold bg-white/10' : 'text-slate-300'"
                            title="Subtitles Track Selection"
                        >
                            <Subtitles class="w-5 h-5" />
                            <span v-if="item.subtitles && item.subtitles.length > 0" class="text-[10px] font-mono px-1 rounded bg-cyan-500/20 text-cyan-300">
                                {{ item.subtitles.length }}
                            </span>
                        </button>

                        <div
                            v-if="showSubtitleMenu"
                            class="absolute bottom-12 right-0 glass-panel rounded-2xl p-2.5 w-60 shadow-2xl border border-white/20 space-y-1 text-xs bg-slate-950/95 backdrop-blur-xl z-30"
                        >
                            <div class="px-2 py-1 text-[10px] uppercase font-bold text-slate-400 border-b border-white/10 flex items-center justify-between">
                                <span>{{ isRTL ? 'مسارات الترجمة' : 'Subtitle Tracks' }}</span>
                                <span class="text-[10px] text-cyan-400">{{ (item.subtitles?.length || 0) }} {{ isRTL ? 'متوفر' : 'linked' }}</span>
                            </div>

                            <!-- Off Option -->
                            <button
                                @click="applySubtitleTrack('off')"
                                class="w-full flex items-center justify-between px-2.5 py-2 rounded-xl hover:bg-white/10 transition-colors cursor-pointer text-left"
                                :class="selectedSubtitleId === 'off' ? 'text-cyan-400 font-bold bg-cyan-500/10' : 'text-slate-300'"
                            >
                                <span>{{ isRTL ? 'إيقاف الترجمة' : 'Off (No Subtitles)' }}</span>
                                <Check v-if="selectedSubtitleId === 'off'" class="w-3.5 h-3.5 text-cyan-400" />
                            </button>

                            <!-- Linked Subtitles Tracks -->
                            <template v-if="item.subtitles && item.subtitles.length > 0">
                                <button
                                    v-for="sub in item.subtitles"
                                    :key="sub.id"
                                    @click="applySubtitleTrack(sub.id)"
                                    class="w-full flex items-center justify-between px-2.5 py-2 rounded-xl hover:bg-white/10 transition-colors cursor-pointer text-left"
                                    :class="selectedSubtitleId === sub.id ? 'text-cyan-400 font-bold bg-cyan-500/10' : 'text-slate-300'"
                                >
                                    <div class="flex items-center gap-1.5 truncate">
                                        <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                                        <span class="truncate">{{ getLanguageDisplayName(sub) }}</span>
                                    </div>
                                    <Check v-if="selectedSubtitleId === sub.id" class="w-3.5 h-3.5 text-cyan-400 shrink-0" />
                                </button>
                            </template>

                            <!-- No Subtitles Notice -->
                            <div v-else class="p-3 text-center text-slate-400 text-[11px] space-y-2">
                                <p>{{ isRTL ? 'لا توجد ملفات ترجمة مدمجة لهذا الفيديو.' : 'No subtitle files found for this video.' }}</p>
                                <a
                                    href="/subtitles"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 text-cyan-400 hover:underline font-bold text-[10px]"
                                >
                                    <span>{{ isRTL ? 'البحث عن ترجمة مجانية' : 'Find Subtitles in Hub' }}</span>
                                    <ExternalLink class="w-3 h-3" />
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Playback Speed Menu -->
                    <div class="relative">
                        <button
                            @click="showSpeedMenu = !showSpeedMenu; showSubtitleMenu = false"
                            class="text-xs font-mono text-slate-300 hover:text-cyan-400 p-1.5 rounded-lg hover:bg-white/10 transition-colors cursor-pointer"
                        >
                            {{ playbackRate }}x
                        </button>

                        <div
                            v-if="showSpeedMenu"
                            class="absolute bottom-12 right-0 glass-panel rounded-2xl p-2 w-32 shadow-2xl border border-white/20 space-y-1 text-xs bg-slate-950/95 backdrop-blur-xl z-30"
                        >
                            <button
                                v-for="rate in [0.75, 1.0, 1.25, 1.5, 2.0]"
                                :key="rate"
                                @click="setSpeed(rate)"
                                class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-xl hover:bg-white/10 transition-colors cursor-pointer font-mono"
                                :class="playbackRate === rate ? 'text-cyan-400 font-bold bg-cyan-500/10' : 'text-slate-300'"
                            >
                                <span>{{ rate }}x</span>
                                <Check v-if="playbackRate === rate" class="w-3.5 h-3.5 text-cyan-400" />
                            </button>
                        </div>
                    </div>

                    <!-- Fullscreen Toggle -->
                    <button @click="toggleFullscreen" class="hover:text-cyan-400 transition-colors cursor-pointer p-1">
                        <Minimize v-if="isFullscreen" class="w-5 h-5" />
                        <Maximize v-else class="w-5 h-5" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
