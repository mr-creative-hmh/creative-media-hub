<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Play, Pause, Volume2, VolumeX, Maximize, Minimize,
    X, RotateCcw, RotateCw, Subtitles, Settings, Check, Sparkles,
    ExternalLink, Music, HelpCircle, Copy, AlertTriangle
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
const showAudioMenu = ref(false);
const audioMode = ref<'direct' | 'aac'>('direct');
const toastNotice = ref('');

let controlsTimeout: any = null;
let progressInterval: any = null;

const baseStreamUrl = computed(() => {
    const isEpisode = props.item.watchable_type === 'episode';
    const mediaId = props.item.watchable_id || props.item.id;
    return isEpisode ? `/stream/episode/${mediaId}` : `/stream/movie/${mediaId}`;
});

const streamUrl = computed(() => {
    if (audioMode.value === 'aac') {
        return `${baseStreamUrl.value}?audio_mode=aac&start=${Math.floor(currentTime.value)}`;
    }
    return baseStreamUrl.value;
});

const switchAudioMode = (mode: 'direct' | 'aac') => {
    const savedTime = videoRef.value?.currentTime || 0;
    audioMode.value = mode;
    showAudioMenu.value = false;
    toastNotice.value = mode === 'aac'
        ? (isRTL.value ? 'تم تفعيل وضع تحويل الصوت إلى AAC المتوافق' : 'Enhanced AAC Audio Mode Activated')
        : (isRTL.value ? 'تم تفعيل وضع البث المباشر الأصلي' : 'Direct Original Stream Mode Activated');

    setTimeout(() => {
        if (videoRef.value) {
            videoRef.value.currentTime = savedTime;
            videoRef.value.play().catch(() => {});
            isPlaying.value = true;
        }
    }, 200);
};

const copyStreamLink = () => {
    const fullUrl = `${window.location.origin}${baseStreamUrl.value}`;
    navigator.clipboard.writeText(fullUrl);
    toastNotice.value = isRTL.value ? 'تم نسخ رابط البث المباشر!' : 'Stream URL copied to clipboard!';
    setTimeout(() => { toastNotice.value = ''; }, 2500);
};

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

const setSubtitle = (subId: number | 'off') => {
    selectedSubtitleId.value = subId;
    showSubtitleMenu.value = false;

    if (!videoRef.value) return;
    const textTracks = videoRef.value.textTracks;

    for (let i = 0; i < textTracks.length; i++) {
        const track = textTracks[i];
        if (subId === 'off') {
            track.mode = 'disabled';
        } else {
            const trackId = parseInt(track.id);
            if (trackId === subId) {
                track.mode = 'showing';
            } else {
                track.mode = 'disabled';
            }
        }
    }
};

const toggleFullscreen = () => {
    if (!playerContainer.value) return;
    if (!document.fullscreenElement) {
        playerContainer.value.requestFullscreen().catch(() => {});
        isFullscreen.value = true;
    } else {
        document.exitFullscreen().catch(() => {});
        isFullscreen.value = false;
    }
};

const handleMouseMove = () => {
    showControls.value = true;
    clearTimeout(controlsTimeout);
    controlsTimeout = setTimeout(() => {
        if (isPlaying.value && !showSubtitleMenu.value && !showSpeedMenu.value && !showAudioMenu.value) {
            showControls.value = false;
        }
    }, 3500);
};

const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === ' ' || e.key === 'k') {
        e.preventDefault();
        togglePlay();
    } else if (e.key === 'ArrowRight') {
        skip(isRTL.value ? -10 : 10);
    } else if (e.key === 'ArrowLeft') {
        skip(isRTL.value ? 10 : -10);
    } else if (e.key === 'f') {
        toggleFullscreen();
    } else if (e.key === 'm') {
        toggleMute();
    } else if (e.key === 'Escape') {
        if (!document.fullscreenElement) {
            emit('close');
        }
    }
};

const saveProgress = async () => {
    if (!videoRef.value || duration.value <= 0) return;
    try {
        await fetch('/api/playback/progress', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                watchable_id: props.item.watchable_id || props.item.id,
                watchable_type: props.item.watchable_type || 'movie',
                progress_seconds: Math.floor(currentTime.value),
                duration_seconds: Math.floor(duration.value),
            }),
        });
    } catch (e) {}
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
    progressInterval = setInterval(saveProgress, 10000);

    // Auto-select Arabic subtitle if available
    if (props.item.subtitles && props.item.subtitles.length > 0) {
        const arSub = props.item.subtitles.find(s => s.language === 'ar');
        if (arSub) {
            setTimeout(() => {
                setSubtitle(arSub.id);
            }, 600);
        }
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    clearTimeout(controlsTimeout);
    clearInterval(progressInterval);
    saveProgress();
});
</script>

<template>
    <div
        ref="playerContainer"
        @mousemove="handleMouseMove"
        @mouseleave="showControls = false"
        class="fixed inset-0 z-50 bg-black flex items-center justify-center font-sans overflow-hidden select-none"
        :class="{ 'cursor-none': !showControls && isPlaying }"
        :dir="isRTL ? 'rtl' : 'ltr'"
    >
        <!-- HTML5 Cinema Video Element -->
        <video
            ref="videoRef"
            :src="streamUrl"
            class="w-full h-full object-contain bg-black"
            playsinline
            autoplay
            crossorigin="anonymous"
            @click="togglePlay"
            @timeupdate="currentTime = videoRef?.currentTime || 0"
            @loadedmetadata="duration = videoRef?.duration || 0; isPlaying = true"
            @ended="isPlaying = false"
        >
            <!-- Dynamic WebVTT Tracks -->
            <track
                v-for="sub in item.subtitles"
                :key="sub.id"
                :id="String(sub.id)"
                :src="`/stream/subtitles/${sub.id}`"
                kind="subtitles"
                :srclang="sub.language"
                :label="sub.language_name || (sub.language === 'ar' ? 'Arabic' : 'English')"
                :default="sub.language === 'ar'"
            />
        </video>

        <!-- Toast Notice Feedback -->
        <div
            v-if="toastNotice"
            class="absolute top-20 left-1/2 -translate-x-1/2 px-4 py-2 rounded-2xl bg-black/80 backdrop-blur-xl border border-cyan-500/50 text-cyan-300 font-bold text-xs shadow-2xl z-30 animate-in fade-in"
        >
            {{ toastNotice }}
        </div>

        <!-- Top Navigation Overlay Bar -->
        <div
            class="absolute top-0 inset-x-0 p-6 bg-gradient-to-b from-black/90 via-black/40 to-transparent flex items-center justify-between transition-opacity duration-300 z-20"
            :class="{ 'opacity-0 pointer-events-none': !showControls }"
        >
            <div class="flex items-center gap-3 min-w-0">
                <button
                    @click="emit('close')"
                    class="w-10 h-10 rounded-2xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all cursor-pointer active:scale-95"
                    title="Exit Player (Esc)"
                >
                    <X class="w-5 h-5" />
                </button>
                <div class="min-w-0">
                    <h2 class="font-extrabold text-base text-white truncate max-w-xl">
                        {{ item.title }}
                    </h2>
                    <p v-if="item.title_ar" class="text-xs text-cyan-400 font-arabic truncate max-w-xl">
                        {{ item.title_ar }}
                    </p>
                </div>
            </div>

            <!-- Header Quick Actions (Audio Mode & Stream Copy) -->
            <div class="flex items-center gap-2">
                <button
                    @click="switchAudioMode(audioMode === 'direct' ? 'aac' : 'direct')"
                    class="px-3.5 py-1.5 rounded-xl border text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-md"
                    :class="audioMode === 'aac' ? 'bg-cyan-500 text-slate-950 font-black border-cyan-400' : 'bg-white/10 hover:bg-white/20 text-slate-200 border-white/15'"
                    :title="audioMode === 'aac' ? 'Enhanced AAC Active' : 'Switch to Enhanced AAC if no audio'"
                >
                    <Music class="w-3.5 h-3.5" />
                    <span>{{ audioMode === 'aac' ? 'Enhanced Audio: AAC' : (isRTL ? 'حل مشكلة انقطاع الصوت' : 'Audio: Direct') }}</span>
                </button>

                <button
                    @click="copyStreamLink"
                    class="p-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white transition-all cursor-pointer"
                    title="Copy direct stream URL for VLC"
                >
                    <Copy class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- Center Click-to-Play Indicator Overlay -->
        <div
            v-if="!isPlaying"
            @click="togglePlay"
            class="absolute inset-0 flex items-center justify-center bg-black/40 cursor-pointer z-10"
        >
            <div class="w-20 h-20 rounded-full bg-cyan-500/90 text-slate-950 flex items-center justify-center shadow-2xl shadow-cyan-500/40 hover:scale-110 active:scale-95 transition-all">
                <Play class="w-8 h-8 fill-current ml-1" />
            </div>
        </div>

        <!-- Bottom Controller Bar -->
        <div
            class="absolute bottom-0 inset-x-0 p-6 bg-gradient-to-t from-black/95 via-black/60 to-transparent space-y-3 transition-opacity duration-300 z-20"
            :class="{ 'opacity-0 pointer-events-none': !showControls }"
        >
            <!-- Interactive Scrubber Timeline -->
            <div
                @click="seek"
                class="group/timeline relative w-full h-2 hover:h-3 rounded-full bg-white/20 cursor-pointer transition-all flex items-center"
            >
                <div
                    class="h-full bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500 rounded-full relative"
                    :style="{ width: `${(currentTime / Math.max(1, duration)) * 100}%` }"
                >
                    <div class="absolute right-0 top-1/2 -translate-y-1/2 w-3.5 h-3.5 rounded-full bg-white shadow-md scale-0 group-hover/timeline:scale-100 transition-transform"></div>
                </div>
            </div>

            <!-- Controller Buttons Row -->
            <div class="flex items-center justify-between flex-wrap gap-4 text-white">
                <!-- Left Buttons: Play, Skips, Volume, Timers -->
                <div class="flex items-center gap-3">
                    <button
                        @click="togglePlay"
                        class="p-2.5 rounded-xl hover:bg-white/10 text-white transition-colors cursor-pointer"
                    >
                        <Pause v-if="isPlaying" class="w-5 h-5" />
                        <Play v-else class="w-5 h-5 fill-current" />
                    </button>

                    <button
                        @click="skip(-10)"
                        class="p-2 rounded-xl hover:bg-white/10 text-slate-300 hover:text-white transition-colors cursor-pointer"
                        title="Skip 10s Backward"
                    >
                        <RotateCcw class="w-4 h-4" />
                    </button>

                    <button
                        @click="skip(10)"
                        class="p-2 rounded-xl hover:bg-white/10 text-slate-300 hover:text-white transition-colors cursor-pointer"
                        title="Skip 10s Forward"
                    >
                        <RotateCw class="w-4 h-4" />
                    </button>

                    <!-- Volume Slider -->
                    <div class="flex items-center gap-1.5 group/vol">
                        <button
                            @click="toggleMute"
                            class="p-2 rounded-xl hover:bg-white/10 text-slate-300 hover:text-white transition-colors cursor-pointer"
                        >
                            <VolumeX v-if="isMuted || volume === 0" class="w-4 h-4 text-rose-400" />
                            <Volume2 v-else class="w-4 h-4" />
                        </button>
                        <input
                            type="range"
                            min="0"
                            max="1"
                            step="0.05"
                            v-model.number="volume"
                            @input="if (videoRef) { videoRef.volume = volume; isMuted = volume === 0; }"
                            class="w-16 h-1 bg-white/20 rounded-full appearance-none cursor-pointer accent-cyan-400"
                        />
                    </div>

                    <!-- Timers -->
                    <div class="text-xs font-mono font-bold text-slate-300 ml-2">
                        <span>{{ formatTime(currentTime) }}</span>
                        <span class="text-slate-500 mx-1">/</span>
                        <span class="text-slate-400">{{ formatTime(duration) }}</span>
                    </div>
                </div>

                <!-- Right Buttons: Subtitles Menu, Audio Menu, Speed, Fullscreen -->
                <div class="flex items-center gap-2 relative">
                    <!-- Subtitles Track Selector Menu -->
                    <div class="relative">
                        <button
                            @click="showSubtitleMenu = !showSubtitleMenu; showSpeedMenu = false; showAudioMenu = false"
                            class="p-2.5 rounded-xl hover:bg-white/10 transition-colors cursor-pointer flex items-center gap-1"
                            :class="selectedSubtitleId !== 'off' ? 'text-cyan-400 font-bold' : 'text-slate-300'"
                            title="Subtitles Track"
                        >
                            <Subtitles class="w-4 h-4" />
                            <span v-if="item.subtitles?.length" class="text-[10px]">{{ item.subtitles.length }}</span>
                        </button>

                        <!-- Subtitles Dropdown -->
                        <div
                            v-if="showSubtitleMenu"
                            class="absolute bottom-12 right-0 w-52 rounded-2xl bg-[#0E121E]/95 backdrop-blur-xl border border-white/15 p-2 shadow-2xl space-y-1 z-30"
                        >
                            <div class="px-3 py-1.5 text-[11px] font-bold text-slate-400 border-b border-white/10 uppercase tracking-wider">
                                {{ isRTL ? 'ملفات الترجمة' : 'Subtitle Tracks' }}
                            </div>

                            <button
                                @click="setSubtitle('off')"
                                class="w-full px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-between hover:bg-white/10 transition-colors cursor-pointer"
                                :class="selectedSubtitleId === 'off' ? 'text-cyan-400 bg-cyan-500/10' : 'text-slate-300'"
                            >
                                <span>{{ isRTL ? 'إيقاف الترجمة (Off)' : 'Off' }}</span>
                                <Check v-if="selectedSubtitleId === 'off'" class="w-3.5 h-3.5" />
                            </button>

                            <button
                                v-for="sub in item.subtitles"
                                :key="sub.id"
                                @click="setSubtitle(sub.id)"
                                class="w-full px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-between hover:bg-white/10 transition-colors cursor-pointer"
                                :class="selectedSubtitleId === sub.id ? 'text-cyan-400 bg-cyan-500/10' : 'text-slate-300'"
                            >
                                <span class="truncate">{{ sub.language_name || sub.language?.toUpperCase() }} ({{ sub.format || 'SRT' }})</span>
                                <Check v-if="selectedSubtitleId === sub.id" class="w-3.5 h-3.5 shrink-0" />
                            </button>
                        </div>
                    </div>

                    <!-- Audio Mode Dropdown -->
                    <div class="relative">
                        <button
                            @click="showAudioMenu = !showAudioMenu; showSubtitleMenu = false; showSpeedMenu = false"
                            class="p-2.5 rounded-xl hover:bg-white/10 transition-colors cursor-pointer flex items-center gap-1"
                            :class="audioMode === 'aac' ? 'text-cyan-400' : 'text-slate-300'"
                            title="Audio Codec Settings"
                        >
                            <Music class="w-4 h-4" />
                        </button>

                        <div
                            v-if="showAudioMenu"
                            class="absolute bottom-12 right-0 w-60 rounded-2xl bg-[#0E121E]/95 backdrop-blur-xl border border-white/15 p-2 shadow-2xl space-y-1 z-30"
                        >
                            <div class="px-3 py-1.5 text-[11px] font-bold text-slate-400 border-b border-white/10 uppercase tracking-wider">
                                {{ isRTL ? 'وضع فك ترميز الصوت' : 'Audio Stream Mode' }}
                            </div>

                            <button
                                @click="switchAudioMode('direct')"
                                class="w-full px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-between hover:bg-white/10 transition-colors cursor-pointer"
                                :class="audioMode === 'direct' ? 'text-cyan-400 bg-cyan-500/10' : 'text-slate-300'"
                            >
                                <div class="text-left">
                                    <div>Direct Stream</div>
                                    <div class="text-[10px] text-slate-500 font-normal">Original DTS / EAC3 / AAC</div>
                                </div>
                                <Check v-if="audioMode === 'direct'" class="w-3.5 h-3.5" />
                            </button>

                            <button
                                @click="switchAudioMode('aac')"
                                class="w-full px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-between hover:bg-white/10 transition-colors cursor-pointer"
                                :class="audioMode === 'aac' ? 'text-cyan-400 bg-cyan-500/10' : 'text-slate-300'"
                            >
                                <div class="text-left">
                                    <div>Enhanced AAC Transcode</div>
                                    <div class="text-[10px] text-slate-500 font-normal">Universal Browser Stereo/5.1</div>
                                </div>
                                <Check v-if="audioMode === 'aac'" class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Speed Menu -->
                    <div class="relative">
                        <button
                            @click="showSpeedMenu = !showSpeedMenu; showSubtitleMenu = false; showAudioMenu = false"
                            class="px-2 py-1 rounded-xl hover:bg-white/10 text-xs font-bold text-slate-300 hover:text-white transition-colors cursor-pointer"
                        >
                            {{ playbackRate }}x
                        </button>

                        <div
                            v-if="showSpeedMenu"
                            class="absolute bottom-12 right-0 w-28 rounded-2xl bg-[#0E121E]/95 backdrop-blur-xl border border-white/15 p-2 shadow-2xl space-y-1 z-30"
                        >
                            <button
                                v-for="rate in [0.75, 1.0, 1.25, 1.5, 2.0]"
                                :key="rate"
                                @click="setSpeed(rate)"
                                class="w-full px-3 py-1.5 rounded-xl text-xs font-bold flex items-center justify-between hover:bg-white/10 transition-colors cursor-pointer"
                                :class="playbackRate === rate ? 'text-cyan-400 bg-cyan-500/10' : 'text-slate-300'"
                            >
                                <span>{{ rate }}x</span>
                                <Check v-if="playbackRate === rate" class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Fullscreen Toggle -->
                    <button
                        @click="toggleFullscreen"
                        class="p-2.5 rounded-xl hover:bg-white/10 text-slate-300 hover:text-white transition-colors cursor-pointer"
                    >
                        <Minimize v-if="isFullscreen" class="w-4 h-4" />
                        <Maximize v-else class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
