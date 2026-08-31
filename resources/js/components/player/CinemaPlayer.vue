<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Play, Pause, Volume2, VolumeX, Maximize, Minimize,
    X, RotateCcw, RotateCw, Subtitles, Settings, Check, Sparkles,
    ExternalLink, Music, HelpCircle, Copy, AlertTriangle, Tv, Sliders,
    Type, Palette, Clock, PictureInPicture2
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
const showSubSettings = ref(false);
const audioMode = ref<'direct' | 'aac'>('direct');
const toastNotice = ref('');
const isDoubleTapSeeking = ref<'left' | 'right' | null>(null);

// Subtitle Customization Preferences
const subFontSize = ref<'sm' | 'md' | 'lg' | 'xl'>('lg');
const subColor = ref<'white' | 'yellow' | 'cyan' | 'green'>('yellow');
const subBackground = ref<'none' | 'translucent' | 'solid'>('translucent');
const subOffsetSeconds = ref(0);

let controlsTimeout: any = null;
let progressInterval: any = null;
let lastTapTime = 0;
let lastTapSide: 'left' | 'right' | null = null;

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
        ? (isRTL.value ? 'تم تفعيل وضع تحويل الصوت إلى AAC المتوافق' : 'Enhanced AAC Audio Transcode Mode Activated')
        : (isRTL.value ? 'تم تفعيل وضع البث المباشر الأصلي' : 'Direct Original Stream Mode Activated');

    setTimeout(() => {
        if (videoRef.value) {
            videoRef.value.currentTime = savedTime;
            videoRef.value.play().catch(() => {});
            isPlaying.value = true;
        }
    }, 250);
    setTimeout(() => { toastNotice.value = ''; }, 3000);
};

const copyStreamLink = () => {
    const fullUrl = `${window.location.origin}${baseStreamUrl.value}`;
    navigator.clipboard.writeText(fullUrl);
    toastNotice.value = isRTL.value ? 'تم نسخ رابط البث المباشر (VLC/IINA)!' : 'Direct stream URL copied to clipboard!';
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

const seek = (time: number) => {
    if (!videoRef.value) return;
    videoRef.value.currentTime = Math.max(0, Math.min(time, duration.value));
    currentTime.value = videoRef.value.currentTime;
};

const skip = (seconds: number) => {
    if (!videoRef.value) return;
    seek(videoRef.value.currentTime + seconds);
    isDoubleTapSeeking.value = seconds < 0 ? 'left' : 'right';
    setTimeout(() => { isDoubleTapSeeking.value = null; }, 500);
};

const toggleMute = () => {
    if (!videoRef.value) return;
    videoRef.value.muted = !videoRef.value.muted;
    isMuted.value = videoRef.value.muted;
};

const setVolume = (val: number) => {
    if (!videoRef.value) return;
    volume.value = Math.max(0, Math.min(1, val));
    videoRef.value.volume = volume.value;
    videoRef.value.muted = volume.value === 0;
    isMuted.value = videoRef.value.muted;
};

const setSpeed = (rate: number) => {
    if (!videoRef.value) return;
    playbackRate.value = rate;
    videoRef.value.playbackRate = rate;
    showSpeedMenu.value = false;
};

const toggleFullscreen = () => {
    if (!playerContainer.value) return;
    if (!document.fullscreenElement) {
        playerContainer.value.requestFullscreen().then(() => {
            isFullscreen.value = true;
        }).catch(() => {});
    } else {
        document.exitFullscreen().then(() => {
            isFullscreen.value = false;
        }).catch(() => {});
    }
};

const togglePiP = async () => {
    if (!videoRef.value) return;
    try {
        if (document.pictureInPictureElement) {
            await document.exitPictureInPicture();
        } else if (document.pictureInPictureEnabled) {
            await videoRef.value.requestPictureInPicture();
        }
    } catch (e) {}
};

const selectSubtitle = (subId: number | 'off') => {
    selectedSubtitleId.value = subId;
    showSubtitleMenu.value = false;

    if (!videoRef.value) return;
    const tracks = videoRef.value.textTracks;

    for (let i = 0; i < tracks.length; i++) {
        if (subId === 'off') {
            tracks[i].mode = 'disabled';
        } else {
            const trackId = parseInt(tracks[i].id);
            tracks[i].mode = trackId === subId ? 'showing' : 'disabled';
        }
    }
};

const adjustSubOffset = (delta: number) => {
    subOffsetSeconds.value = Math.round((subOffsetSeconds.value + delta) * 10) / 10;
    toastNotice.value = `${isRTL.value ? 'مزامنة الترجمة' : 'Subtitle Sync'}: ${subOffsetSeconds.value > 0 ? '+' : ''}${subOffsetSeconds.value}s`;
    setTimeout(() => { toastNotice.value = ''; }, 2000);
};

const handleTimeUpdate = () => {
    if (!videoRef.value) return;
    currentTime.value = videoRef.value.currentTime;
    duration.value = videoRef.value.duration || 0;
};

const handleLoadedMetadata = () => {
    if (!videoRef.value) return;
    duration.value = videoRef.value.duration || 0;
    if (selectedSubtitleId.value !== 'off') {
        selectSubtitle(selectedSubtitleId.value);
    }
};

const resetControlsTimeout = () => {
    showControls.value = true;
    clearTimeout(controlsTimeout);
    if (isPlaying.value) {
        controlsTimeout = setTimeout(() => {
            showControls.value = false;
            showSubtitleMenu.value = false;
            showSpeedMenu.value = false;
            showAudioMenu.value = false;
            showSubSettings.value = false;
        }, 3500);
    }
};

const handleVideoAreaClick = (e: MouseEvent) => {
    const target = e.currentTarget as HTMLElement;
    const rect = target.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const isLeft = x < rect.width * 0.4;
    const isRight = x > rect.width * 0.6;

    const now = Date.now();
    const side = isLeft ? 'left' : isRight ? 'right' : null;

    if (now - lastTapTime < 300 && lastTapSide === side && side !== null) {
        if (side === 'left') skip(-10);
        if (side === 'right') skip(10);
        lastTapTime = 0;
        lastTapSide = null;
    } else {
        lastTapTime = now;
        lastTapSide = side;
        togglePlay();
    }
};

const handleKeydown = (e: KeyboardEvent) => {
    if (['input', 'textarea'].includes((e.target as HTMLElement).tagName.toLowerCase())) return;

    switch (e.key.toLowerCase()) {
        case ' ':
        case 'k':
            e.preventDefault();
            togglePlay();
            break;
        case 'arrowleft':
        case 'j':
            e.preventDefault();
            skip(-10);
            break;
        case 'arrowright':
        case 'l':
            e.preventDefault();
            skip(10);
            break;
        case 'arrowup':
            e.preventDefault();
            setVolume(volume.value + 0.1);
            break;
        case 'arrowdown':
            e.preventDefault();
            setVolume(volume.value - 0.1);
            break;
        case 'f':
            e.preventDefault();
            toggleFullscreen();
            break;
        case 'm':
            e.preventDefault();
            toggleMute();
            break;
        case 'p':
            e.preventDefault();
            togglePiP();
            break;
        case 'escape':
            if (showSubSettings.value) {
                showSubSettings.value = false;
            } else if (!isFullscreen.value) {
                emit('close');
            }
            break;
    }
    resetControlsTimeout();
};

const saveProgress = async () => {
    if (!currentTime.value || !duration.value) return;

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
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('mousemove', resetControlsTimeout);
    progressInterval = setInterval(saveProgress, 10000);

    if (props.item.subtitles && props.item.subtitles.length > 0) {
        const arSub = props.item.subtitles.find(s => s.language === 'ar');
        selectedSubtitleId.value = arSub ? arSub.id : props.item.subtitles[0].id;
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('mousemove', resetControlsTimeout);
    clearTimeout(controlsTimeout);
    clearInterval(progressInterval);
    saveProgress();
});
</script>

<template>
    <div
        ref="playerContainer"
        class="fixed inset-0 z-50 bg-black flex items-center justify-center select-none overflow-hidden"
        :class="{ 'cursor-none': !showControls && isPlaying }"
        @mousemove="resetControlsTimeout"
        @mouseleave="showControls = false"
    >
        <div class="ambient-glow bg-cyan-500/10 w-[45rem] h-[45rem] top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 pointer-events-none"></div>

        <video
            ref="videoRef"
            class="w-full h-full object-contain relative z-10"
            :src="streamUrl"
            playsinline
            preload="auto"
            @timeupdate="handleTimeUpdate"
            @loadedmetadata="handleLoadedMetadata"
            @play="isPlaying = true"
            @pause="isPlaying = false"
            @ended="isPlaying = false"
        >
            <track
                v-for="sub in item.subtitles || []"
                :key="sub.id"
                :id="sub.id.toString()"
                :src="`/stream/subtitles/${sub.id}`"
                kind="subtitles"
                :srclang="sub.language || 'und'"
                :label="sub.language_name || sub.language"
                :default="selectedSubtitleId === sub.id"
            />
        </video>

        <div v-if="isDoubleTapSeeking === 'left'" class="absolute left-8 top-1/2 -translate-y-1/2 z-20 pointer-events-none flex flex-col items-center gap-2 p-5 rounded-full bg-black/60 backdrop-blur-md border border-white/20 animate-in zoom-in-75">
            <RotateCcw class="w-8 h-8 text-cyan-400 animate-spin" />
            <span class="text-xs font-black text-white font-mono">-10s</span>
        </div>
        <div v-if="isDoubleTapSeeking === 'right'" class="absolute right-8 top-1/2 -translate-y-1/2 z-20 pointer-events-none flex flex-col items-center gap-2 p-5 rounded-full bg-black/60 backdrop-blur-md border border-white/20 animate-in zoom-in-75">
            <RotateCw class="w-8 h-8 text-cyan-400 animate-spin" />
            <span class="text-xs font-black text-white font-mono">+10s</span>
        </div>

        <div class="absolute inset-0 z-10 cursor-pointer" @click="handleVideoAreaClick"></div>

        <div v-if="toastNotice" class="absolute top-20 left-1/2 -translate-x-1/2 z-40 px-5 py-2.5 rounded-2xl bg-black/80 backdrop-blur-md border border-cyan-500/40 text-cyan-300 font-bold text-xs shadow-2xl flex items-center gap-2 animate-in fade-in slide-in-from-top-2">
            <Sparkles class="w-4 h-4 text-cyan-400" />
            <span>{{ toastNotice }}</span>
        </div>

        <div
            class="absolute inset-0 z-30 flex flex-col justify-between p-4 sm:p-8 pointer-events-none transition-opacity duration-300 bg-gradient-to-t from-black/95 via-transparent to-black/80"
            :class="showControls ? 'opacity-100' : 'opacity-0'"
        >
            <div class="flex items-center justify-between pointer-events-auto gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/40 flex items-center justify-center shrink-0">
                        <Play class="w-5 h-5 fill-current ml-0.5" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-extrabold text-base sm:text-lg text-white truncate max-w-xl">
                            {{ isRTL && item.title_ar ? item.title_ar : item.title }}
                        </h2>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="cinema-badge bg-cyan-500/20 text-cyan-300 border-cyan-500/30 text-[10px]">
                                {{ audioMode === 'aac' ? 'AAC 2.0 (Enhanced Compatibility)' : 'Direct Stream' }}
                            </span>
                            <span v-if="selectedSubtitleId !== 'off'" class="cinema-badge bg-indigo-500/20 text-indigo-300 border-indigo-500/30 text-[10px]">
                                CC: {{ item.subtitles?.find(s => s.id === selectedSubtitleId)?.language_name || 'Active' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="copyStreamLink"
                        class="p-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white transition-all cursor-pointer shadow-sm"
                        :title="isRTL ? 'نسخ رابط البث لتشغيله في VLC أو IINA' : 'Copy Direct Stream URL (VLC / IINA)'"
                    >
                        <Copy class="w-4 h-4" />
                    </button>

                    <button
                        @click="togglePiP"
                        class="p-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white transition-all cursor-pointer shadow-sm"
                        :title="isRTL ? 'صورة داخل صورة' : 'Picture-in-Picture'"
                    >
                        <PictureInPicture2 class="w-4 h-4" />
                    </button>

                    <button
                        @click="emit('close')"
                        class="p-2.5 rounded-2xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/30 transition-all cursor-pointer"
                        :title="t('common.close')"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <div class="space-y-3 pointer-events-auto">
                <div class="space-y-1">
                    <div class="relative group cursor-pointer py-2" @click="(e) => {
                        const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
                        const percent = (e.clientX - rect.left) / rect.width;
                        seek(percent * duration);
                    }">
                        <div class="h-1.5 group-hover:h-2.5 rounded-full bg-white/20 transition-all overflow-hidden relative">
                            <div
                                class="h-full bg-gradient-to-r from-cyan-400 via-cyan-500 to-indigo-500 rounded-full transition-all relative"
                                :style="{ width: `${(currentTime / (duration || 1)) * 100}%` }"
                            ></div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs font-mono font-bold text-slate-400">
                        <span>{{ formatTime(currentTime) }}</span>
                        <span>{{ formatTime(duration) }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <button
                            @click="togglePlay"
                            class="w-10 h-10 rounded-2xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 flex items-center justify-center transition-all active:scale-95 cursor-pointer shadow-lg shadow-cyan-500/30"
                        >
                            <Pause v-if="isPlaying" class="w-5 h-5 fill-current" />
                            <Play v-else class="w-5 h-5 fill-current ml-0.5" />
                        </button>

                        <button
                            @click="skip(-10)"
                            class="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white transition-all cursor-pointer"
                            :title="isRTL ? 'رجوع 10 ثواني (J)' : 'Skip backward 10s (J)'"
                        >
                            <RotateCcw class="w-4 h-4" />
                        </button>

                        <button
                            @click="skip(10)"
                            class="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white transition-all cursor-pointer"
                            :title="isRTL ? 'تقديم 10 ثواني (L)' : 'Skip forward 10s (L)'"
                        >
                            <RotateCw class="w-4 h-4" />
                        </button>

                        <div class="flex items-center gap-2 group relative">
                            <button
                                @click="toggleMute"
                                class="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white transition-all cursor-pointer"
                            >
                                <VolumeX v-if="isMuted || volume === 0" class="w-4 h-4 text-rose-400" />
                                <Volume2 v-else class="w-4 h-4" />
                            </button>
                            <input
                                type="range"
                                min="0"
                                max="1"
                                step="0.05"
                                :value="isMuted ? 0 : volume"
                                @input="(e: any) => setVolume(parseFloat(e.target.value))"
                                class="w-16 sm:w-20 h-1.5 bg-white/20 rounded-lg appearance-none cursor-pointer accent-cyan-400"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-2 relative">
                        <div class="relative">
                            <button
                                @click="showSubtitleMenu = !showSubtitleMenu; showSpeedMenu = false; showAudioMenu = false; showSubSettings = false;"
                                class="flex items-center gap-1.5 px-3 py-2 rounded-xl border text-xs font-bold transition-all cursor-pointer"
                                :class="selectedSubtitleId !== 'off'
                                    ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm'
                                    : 'bg-white/10 text-slate-300 border-white/10 hover:bg-white/20'"
                            >
                                <Subtitles class="w-4 h-4" />
                                <span>{{ isRTL ? 'الترجمة' : 'Subtitles' }}</span>
                            </button>

                            <div
                                v-if="showSubtitleMenu"
                                class="absolute bottom-12 right-0 w-64 rounded-2xl bg-[#080B12] border border-white/15 p-2.5 shadow-2xl space-y-1 z-50 animate-in fade-in slide-in-from-bottom-2"
                            >
                                <div class="px-2.5 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-400 border-b border-white/10 flex items-center justify-between">
                                    <span>{{ isRTL ? 'ملفات الترجمة' : 'Subtitle Tracks' }}</span>
                                    <button
                                        @click="showSubSettings = true; showSubtitleMenu = false;"
                                        class="text-cyan-400 hover:underline flex items-center gap-1 cursor-pointer"
                                    >
                                        <Sliders class="w-3 h-3" />
                                        <span>{{ isRTL ? 'تخصيص' : 'Style' }}</span>
                                    </button>
                                </div>

                                <button
                                    @click="selectSubtitle('off')"
                                    class="w-full px-3 py-2 rounded-xl text-xs font-bold text-left flex items-center justify-between cursor-pointer transition-colors"
                                    :class="selectedSubtitleId === 'off' ? 'bg-cyan-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10'"
                                >
                                    <span>{{ isRTL ? 'إيقاف الترجمة' : 'Off' }}</span>
                                    <Check v-if="selectedSubtitleId === 'off'" class="w-3.5 h-3.5" />
                                </button>

                                <button
                                    v-for="sub in item.subtitles || []"
                                    :key="sub.id"
                                    @click="selectSubtitle(sub.id)"
                                    class="w-full px-3 py-2 rounded-xl text-xs font-bold text-left flex items-center justify-between cursor-pointer transition-colors"
                                    :class="selectedSubtitleId === sub.id ? 'bg-cyan-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10'"
                                >
                                    <span class="truncate">{{ sub.language_name || sub.language }} ({{ sub.format || 'SRT' }})</span>
                                    <Check v-if="selectedSubtitleId === sub.id" class="w-3.5 h-3.5" />
                                </button>

                                <div v-if="!item.subtitles || item.subtitles.length === 0" class="px-3 py-2 text-xs text-slate-500 italic text-center">
                                    {{ isRTL ? 'لا توجد ترجمات مرتبطة' : 'No subtitles attached' }}
                                </div>
                            </div>
                        </div>

                        <div class="relative">
                            <button
                                @click="showAudioMenu = !showAudioMenu; showSubtitleMenu = false; showSpeedMenu = false; showSubSettings = false;"
                                class="flex items-center gap-1.5 px-3 py-2 rounded-xl border text-xs font-bold transition-all cursor-pointer"
                                :class="audioMode === 'aac'
                                    ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 shadow-sm'
                                    : 'bg-white/10 text-slate-300 border-white/10 hover:bg-white/20'"
                                :title="isRTL ? 'وضع الصوت والتوافقية' : 'Audio Compatibility Mode'"
                            >
                                <Music class="w-4 h-4" />
                                <span class="hidden sm:inline">{{ audioMode === 'aac' ? 'AAC Audio' : 'Direct Audio' }}</span>
                            </button>

                            <div
                                v-if="showAudioMenu"
                                class="absolute bottom-12 right-0 w-72 rounded-2xl bg-[#080B12] border border-white/15 p-3 shadow-2xl space-y-2 z-50 animate-in fade-in slide-in-from-bottom-2"
                            >
                                <div class="px-1 text-[10px] font-black uppercase tracking-wider text-slate-400 border-b border-white/10 pb-1.5">
                                    {{ isRTL ? 'إعدادات تشغيل الصوت' : 'Audio Stream & Compatibility' }}
                                </div>

                                <button
                                    @click="switchAudioMode('direct')"
                                    class="w-full p-2.5 rounded-xl text-xs font-bold text-left flex items-start gap-2.5 transition-colors cursor-pointer"
                                    :class="audioMode === 'direct' ? 'bg-cyan-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10'"
                                >
                                    <Check v-if="audioMode === 'direct'" class="w-4 h-4 shrink-0 mt-0.5" />
                                    <div>
                                        <div class="font-bold">{{ isRTL ? 'البث المباشر (Direct Stream)' : 'Direct Stream (Passthrough)' }}</div>
                                        <div class="text-[10px] opacity-80 mt-0.5">{{ isRTL ? 'تشغيل الصوت الأصلي للملف بأعلى نقاوة.' : 'Original untouched audio tracks for modern devices.' }}</div>
                                    </div>
                                </button>

                                <button
                                    @click="switchAudioMode('aac')"
                                    class="w-full p-2.5 rounded-xl text-xs font-bold text-left flex items-start gap-2.5 transition-colors cursor-pointer"
                                    :class="audioMode === 'aac' ? 'bg-amber-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10'"
                                >
                                    <Check v-if="audioMode === 'aac'" class="w-4 h-4 shrink-0 mt-0.5" />
                                    <div>
                                        <div class="font-bold">{{ isRTL ? 'تحويل الصوت إلى AAC (لحل مشكلة انعدام الصوت)' : 'AAC Transcode Mode (Fix Silent EAC3)' }}</div>
                                        <div class="text-[10px] opacity-80 mt-0.5">{{ isRTL ? 'تحويل صوتي فوري في الخلفية لضمان عمل الصوت في جميع المتصفحات.' : 'Real-time AAC transcode for EAC3/AC3 audio compatibility.' }}</div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <div class="relative">
                            <button
                                @click="showSpeedMenu = !showSpeedMenu; showSubtitleMenu = false; showAudioMenu = false; showSubSettings = false;"
                                class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white text-xs font-bold transition-all cursor-pointer font-mono"
                            >
                                {{ playbackRate }}x
                            </button>

                            <div
                                v-if="showSpeedMenu"
                                class="absolute bottom-12 right-0 w-32 rounded-2xl bg-[#080B12] border border-white/15 p-1.5 shadow-2xl space-y-0.5 z-50 animate-in fade-in slide-in-from-bottom-2"
                            >
                                <button
                                    v-for="rate in [0.5, 0.75, 1.0, 1.25, 1.5, 1.75, 2.0]"
                                    :key="rate"
                                    @click="setSpeed(rate)"
                                    class="w-full px-3 py-1.5 rounded-xl text-xs font-bold text-left flex items-center justify-between cursor-pointer font-mono"
                                    :class="playbackRate === rate ? 'bg-cyan-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10'"
                                >
                                    <span>{{ rate }}x</span>
                                    <Check v-if="playbackRate === rate" class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>

                        <button
                            @click="toggleFullscreen"
                            class="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white transition-all cursor-pointer"
                            :title="isRTL ? 'شاشة كاملة (F)' : 'Fullscreen (F)'"
                        >
                            <Minimize v-if="isFullscreen" class="w-4 h-4" />
                            <Maximize v-else class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showSubSettings"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-in fade-in"
            @click.self="showSubSettings = false"
        >
            <div class="relative w-full max-w-md rounded-3xl bg-[#080B12] border border-cyan-500/30 p-6 shadow-2xl shadow-cyan-500/10 space-y-5 overflow-hidden">
                <div class="ambient-glow bg-cyan-500/15 w-64 h-64 -top-20 -right-20 pointer-events-none"></div>

                <div class="flex items-center justify-between border-b border-white/10 pb-3 relative z-10">
                    <div class="flex items-center gap-2.5">
                        <Sliders class="w-5 h-5 text-cyan-400" />
                        <h3 class="font-extrabold text-sm text-white">{{ isRTL ? 'استوديو تخصيص مظهر الترجمة' : 'Subtitle Styling & Sync Studio' }}</h3>
                    </div>
                    <button @click="showSubSettings = false" class="p-1 rounded-lg bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-2 relative z-10">
                    <span class="text-xs font-bold text-slate-400 block">{{ isRTL ? 'حجم خط الترجمة' : 'Font Size' }}</span>
                    <div class="grid grid-cols-4 gap-2">
                        <button
                            v-for="s in [
                                { key: 'sm', label: isRTL ? 'صغير' : 'Small' },
                                { key: 'md', label: isRTL ? 'متوسط' : 'Medium' },
                                { key: 'lg', label: isRTL ? 'كبير' : 'Large' },
                                { key: 'xl', label: isRTL ? 'ضخم' : 'Extra' },
                            ]"
                            :key="s.key"
                            @click="subFontSize = s.key as any"
                            class="py-2 rounded-xl text-xs font-bold transition-all cursor-pointer"
                            :class="subFontSize === s.key ? 'bg-cyan-500 text-slate-950 font-black' : 'bg-white/5 text-slate-300 hover:bg-white/10 border border-white/10'"
                        >
                            {{ s.label }}
                        </button>
                    </div>
                </div>

                <div class="space-y-2 relative z-10">
                    <span class="text-xs font-bold text-slate-400 block">{{ isRTL ? 'لون الترجمة' : 'Font Color' }}</span>
                    <div class="grid grid-cols-4 gap-2">
                        <button
                            v-for="c in [
                                { key: 'yellow', label: isRTL ? 'ذهبي / أصفر' : 'Yellow', hex: '#FFD700' },
                                { key: 'white', label: isRTL ? 'أبيض' : 'White', hex: '#FFFFFF' },
                                { key: 'cyan', label: isRTL ? 'سماوي' : 'Cyan', hex: '#00FFFF' },
                                { key: 'green', label: isRTL ? 'أخضر' : 'Green', hex: '#00FF7F' },
                            ]"
                            :key="c.key"
                            @click="subColor = c.key as any"
                            class="py-2 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                            :class="subColor === c.key ? 'bg-cyan-500 text-slate-950 font-black' : 'bg-white/5 text-slate-300 hover:bg-white/10 border border-white/10'"
                        >
                            <span class="w-2.5 h-2.5 rounded-full border border-black/40" :style="{ backgroundColor: c.hex }"></span>
                            <span>{{ c.label }}</span>
                        </button>
                    </div>
                </div>

                <div class="space-y-2 relative z-10 pt-2 border-t border-white/10">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-400">{{ isRTL ? 'مزامنة وتأخير/تقديم الترجمة' : 'Subtitle Timing Offset' }}</span>
                        <span class="font-mono font-bold text-cyan-400">{{ subOffsetSeconds > 0 ? '+' : '' }}{{ subOffsetSeconds }}s</span>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <button
                            @click="adjustSubOffset(-0.5)"
                            class="flex-1 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-bold text-slate-300 border border-white/10 cursor-pointer"
                        >
                            -0.5s
                        </button>
                        <button
                            @click="subOffsetSeconds = 0"
                            class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-bold text-slate-400 border border-white/10 cursor-pointer"
                        >
                            {{ isRTL ? 'إعادة ضبط' : 'Reset' }}
                        </button>
                        <button
                            @click="adjustSubOffset(0.5)"
                            class="flex-1 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-bold text-slate-300 border border-white/10 cursor-pointer"
                        >
                            +0.5s
                        </button>
                    </div>
                </div>

                <div class="pt-2 relative z-10">
                    <button
                        @click="showSubSettings = false"
                        class="w-full py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-xs font-black cursor-pointer shadow-lg shadow-cyan-500/20"
                    >
                        {{ isRTL ? 'تطبيق وحفظ' : 'Apply & Close' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
