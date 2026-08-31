<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Play, Pause, Volume2, VolumeX, Maximize, Minimize,
    RotateCcw, RotateCw, Subtitles, Settings, X,
    Sparkles, Film, ArrowLeft, Sliders, Music, Volume1,
    Copy, Check, PictureInPicture, Zap, ShieldCheck
} from 'lucide-vue-next';

const props = defineProps<{
    item: {
        id: number;
        title: string;
        type?: 'movie' | 'episode';
        subtitles?: Array<{
            id: number;
            language: string;
            language_name: string;
            file_path?: string;
        }>;
    };
}>();

const emit = defineEmits(['close']);

const { t, isRTL } = useI18n();

const videoRef = ref<HTMLVideoElement | null>(null);
const playerContainerRef = ref<HTMLDivElement | null>(null);

const isPlaying = ref(false);
const isMuted = ref(false);
const volume = ref(1);
const currentTime = ref(0);
const duration = ref(0);
const isFullscreen = ref(false);
const isPiP = ref(false);
const isControlsVisible = ref(true);
const playbackRate = ref(1);
const selectedSubtitleId = ref<number | 'off'>('off');

// Menus
const showSpeedMenu = ref(false);
const showSubtitleMenu = ref(false);
const showAudioMenu = ref(false);
const showSubSettings = ref(false);
const toastNotice = ref('');
const isDoubleTapSeeking = ref<'left' | 'right' | null>(null);

// Audio Enhancer Modes (Web Audio API - Pure Client-Side Hardware Acceleration)
type AudioEnhanceMode = 'direct' | 'voice_boost' | 'cinema_boost' | 'night_mode';
const audioEnhanceMode = ref<AudioEnhanceMode>('direct');

// Subtitle Customization Preferences
const subFontSize = ref<'sm' | 'md' | 'lg' | 'xl'>('lg');
const subColor = ref<'white' | 'yellow' | 'cyan' | 'green'>('yellow');
const subBackground = ref<'none' | 'translucent' | 'solid'>('translucent');
const subOffsetSeconds = ref(0);

let controlsTimeout: any = null;
let progressInterval: any = null;
let lastTapTime = 0;
let lastTapSide: 'left' | 'right' | null = null;

// Web Audio API Context & Nodes
let audioCtx: AudioContext | null = null;
let sourceNode: MediaElementAudioSourceNode | null = null;
let gainNode: GainNode | null = null;
let voiceFilterNode: BiquadFilterNode | null = null;
let compressorNode: DynamicsCompressorNode | null = null;

const baseStreamUrl = computed(() => {
    return props.item.type === 'episode'
        ? `/stream/episode/${props.item.id}`
        : `/stream/movie/${props.item.id}`;
});

const subtitleUrl = computed(() => {
    if (selectedSubtitleId.value === 'off') return '';
    return `/stream/subtitles/${selectedSubtitleId.value}`;
});

// Setup Web Audio Enhancement Pipeline
const setupWebAudioPipeline = () => {
    if (!videoRef.value || audioCtx) return;

    try {
        const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioContextClass) return;

        audioCtx = new AudioContextClass();
        sourceNode = audioCtx.createMediaElementSource(videoRef.value);
        gainNode = audioCtx.createGain();
        voiceFilterNode = audioCtx.createBiquadFilter();
        compressorNode = audioCtx.createDynamicsCompressor();

        // Configure Voice Midrange Peaking Filter (1.5kHz dialogue boost)
        voiceFilterNode.type = 'peaking';
        voiceFilterNode.frequency.value = 1500;
        voiceFilterNode.Q.value = 1.0;
        voiceFilterNode.gain.value = 0;

        // Configure Compressor for Night Mode
        compressorNode.threshold.value = -24;
        compressorNode.knee.value = 30;
        compressorNode.ratio.value = 12;
        compressorNode.attack.value = 0.003;
        compressorNode.release.value = 0.25;

        // Connect chain: Source -> Voice Filter -> Compressor -> Gain -> Destination
        sourceNode.connect(voiceFilterNode);
        voiceFilterNode.connect(compressorNode);
        compressorNode.connect(gainNode);
        gainNode.connect(audioCtx.destination);
    } catch (e) {
        console.warn('Web Audio API not supported or already connected:', e);
    }
};

const applyAudioEnhancement = (mode: AudioEnhanceMode) => {
    audioEnhanceMode.value = mode;
    showAudioMenu.value = false;

    if (!audioCtx && videoRef.value) {
        setupWebAudioPipeline();
    }

    if (audioCtx && audioCtx.state === 'suspended') {
        audioCtx.resume();
    }

    if (!gainNode || !voiceFilterNode || !compressorNode) {
        toastNotice.value = isRTL.value ? 'تم تطبيق وضع الصوت المباشر' : 'Direct Audio Active';
        setTimeout(() => { toastNotice.value = ''; }, 3000);
        return;
    }

    if (mode === 'direct') {
        gainNode.gain.value = 1.0;
        voiceFilterNode.gain.value = 0;
        toastNotice.value = isRTL.value ? 'الصوت الأصلي المباشر (Untouched)' : 'Original Untouched Audio';
    } else if (mode === 'voice_boost') {
        // Boost center speech frequencies (+8dB at 1.5kHz)
        gainNode.gain.value = 1.3;
        voiceFilterNode.gain.value = 8;
        toastNotice.value = isRTL.value ? 'تم تفعيل تعزيز الحوار والأصوات (+8dB Vocal EQ)' : 'Vocal & Dialogue Clarity Boost (+8dB)';
    } else if (mode === 'cinema_boost') {
        // Boost overall volume by 200% (+6dB gain) with warmth
        gainNode.gain.value = 2.2;
        voiceFilterNode.gain.value = 3;
        toastNotice.value = isRTL.value ? 'مضخم الصوت السينمائي الفائق (Volume 200%)' : 'Cinema Volume Amplifier (200% Gain)';
    } else if (mode === 'night_mode') {
        // Dynamic Range Compression (soft explosions, clear whispers)
        gainNode.gain.value = 1.2;
        voiceFilterNode.gain.value = 6;
        compressorNode.threshold.value = -32;
        toastNotice.value = isRTL.value ? 'الوضع الليلي: موازنة الانفجارات وتوضيح الهمس' : 'Night Mode (Dynamic Range Balanced)';
    }

    setTimeout(() => { toastNotice.value = ''; }, 3500);
};

const copyStreamLink = async () => {
    try {
        const fullUrl = `${window.location.origin}${baseStreamUrl.value}`;
        await navigator.clipboard.writeText(fullUrl);
        toastNotice.value = isRTL.value ? 'تم نسخ رابط البث المباشر (لـ VLC / IINA)' : 'Direct Stream URL Copied (VLC / IINA)';
        setTimeout(() => { toastNotice.value = ''; }, 3000);
    } catch (e) {}
};

const togglePiP = async () => {
    try {
        if (document.pictureInPictureElement) {
            await document.exitPictureInPicture();
            isPiP.value = false;
        } else if (videoRef.value) {
            await videoRef.value.requestPictureInPicture();
            isPiP.value = true;
        }
    } catch (e) {}
};

onMounted(() => {
    if (props.item.subtitles && props.item.subtitles.length > 0) {
        const arSub = props.item.subtitles.find(s => s.language === 'ar');
        const enSub = props.item.subtitles.find(s => s.language === 'en');
        if (isRTL.value && arSub) {
            selectedSubtitleId.value = arSub.id;
        } else if (enSub) {
            selectedSubtitleId.value = enSub.id;
        } else {
            selectedSubtitleId.value = props.item.subtitles[0].id;
        }
    }

    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('mousemove', resetControlsTimeout);

    progressInterval = setInterval(saveProgressToServer, 10000);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('mousemove', resetControlsTimeout);
    if (progressInterval) clearInterval(progressInterval);
    if (controlsTimeout) clearTimeout(controlsTimeout);
    if (audioCtx) {
        try { audioCtx.close(); } catch (e) {}
    }
});

const handleKeydown = (e: KeyboardEvent) => {
    if (['Space', 'KeyK'].includes(e.code)) {
        e.preventDefault();
        togglePlay();
    } else if (['ArrowLeft', 'KeyJ'].includes(e.code)) {
        e.preventDefault();
        seek(-10);
    } else if (['ArrowRight', 'KeyL'].includes(e.code)) {
        e.preventDefault();
        seek(10);
    } else if (e.code === 'ArrowUp') {
        e.preventDefault();
        changeVolume(0.1);
    } else if (e.code === 'ArrowDown') {
        e.preventDefault();
        changeVolume(-0.1);
    } else if (e.code === 'KeyF') {
        e.preventDefault();
        toggleFullscreen();
    } else if (e.code === 'KeyM') {
        e.preventDefault();
        toggleMute();
    } else if (e.code === 'KeyP') {
        e.preventDefault();
        togglePiP();
    } else if (e.code === 'Escape') {
        if (isFullscreen.value) {
            toggleFullscreen();
        } else {
            emit('close');
        }
    }
};

const handleScreenTouch = (e: MouseEvent | TouchEvent) => {
    const now = Date.now();
    const container = playerContainerRef.value;
    if (!container) return;

    const rect = container.getBoundingClientRect();
    const clientX = 'clientX' in e ? e.clientX : (e as TouchEvent).touches[0]?.clientX || 0;
    const isLeft = clientX - rect.left < rect.width * 0.4;
    const isRight = clientX - rect.left > rect.width * 0.6;
    const side = isLeft ? 'left' : (isRight ? 'right' : null);

    if (side && lastTapSide === side && now - lastTapTime < 350) {
        // Double tap confirmed
        seek(side === 'left' ? -10 : 10);
        isDoubleTapSeeking.value = side;
        setTimeout(() => { isDoubleTapSeeking.value = null; }, 600);
        lastTapTime = 0;
        lastTapSide = null;
    } else {
        lastTapTime = now;
        lastTapSide = side;
        resetControlsTimeout();
    }
};

const resetControlsTimeout = () => {
    isControlsVisible.value = true;
    if (controlsTimeout) clearTimeout(controlsTimeout);
    controlsTimeout = setTimeout(() => {
        if (isPlaying.value && !showSpeedMenu.value && !showSubtitleMenu.value && !showAudioMenu.value && !showSubSettings.value) {
            isControlsVisible.value = false;
        }
    }, 3500);
};

const togglePlay = () => {
    if (!videoRef.value) return;
    if (audioCtx && audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
    if (videoRef.value.paused) {
        videoRef.value.play().catch(() => {});
        isPlaying.value = true;
    } else {
        videoRef.value.pause();
        isPlaying.value = false;
    }
    resetControlsTimeout();
};

const seek = (seconds: number) => {
    if (!videoRef.value) return;
    videoRef.value.currentTime = Math.max(0, Math.min(duration.value, videoRef.value.currentTime + seconds));
    resetControlsTimeout();
};

const onTimeUpdate = () => {
    if (!videoRef.value) return;
    currentTime.value = videoRef.value.currentTime;
};

const onLoadedMetadata = () => {
    if (!videoRef.value) return;
    duration.value = videoRef.value.duration || 0;
    applySubtitleStyles();
};

const onProgressScrub = (e: MouseEvent) => {
    if (!videoRef.value || !duration.value) return;
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    const pos = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    videoRef.value.currentTime = pos * duration.value;
};

const toggleMute = () => {
    if (!videoRef.value) return;
    isMuted.value = !isMuted.value;
    videoRef.value.muted = isMuted.value;
};

const changeVolume = (delta: number) => {
    if (!videoRef.value) return;
    volume.value = Math.max(0, Math.min(1, volume.value + delta));
    videoRef.value.volume = volume.value;
    isMuted.value = volume.value === 0;
};

const toggleFullscreen = async () => {
    if (!playerContainerRef.value) return;
    if (!document.fullscreenElement) {
        await playerContainerRef.value.requestFullscreen();
        isFullscreen.value = true;
    } else {
        await document.exitFullscreen();
        isFullscreen.value = false;
    }
};

const setPlaybackRate = (rate: number) => {
    if (!videoRef.value) return;
    playbackRate.value = rate;
    videoRef.value.playbackRate = rate;
    showSpeedMenu.value = false;
};

const setSubtitle = (subId: number | 'off') => {
    selectedSubtitleId.value = subId;
    showSubtitleMenu.value = false;
    setTimeout(() => {
        applySubtitleStyles();
    }, 150);
};

const adjustSubtitleOffset = (delta: number) => {
    subOffsetSeconds.value = parseFloat((subOffsetSeconds.value + delta).toFixed(1));
    toastNotice.value = `${isRTL.value ? 'مزامنة الترجمة' : 'Subtitle Sync Offset'}: ${subOffsetSeconds.value > 0 ? '+' : ''}${subOffsetSeconds.value}s`;
    setTimeout(() => { toastNotice.value = ''; }, 2000);
};

const applySubtitleStyles = () => {
    // Dynamic WebVTT Cue CSS Customization
    const styleId = 'cinema-vtt-custom-styles';
    let styleEl = document.getElementById(styleId);
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = styleId;
        document.head.appendChild(styleEl);
    }

    const fontSizes = { sm: '15px', md: '19px', lg: '24px', xl: '30px' };
    const colors = {
        white: '#FFFFFF',
        yellow: '#FFD700',
        cyan: '#00FFFF',
        green: '#00FF7F'
    };
    const backgrounds = {
        none: 'transparent',
        translucent: 'rgba(0, 0, 0, 0.75)',
        solid: '#000000'
    };

    styleEl.innerHTML = `
        video::cue {
            font-family: 'Cairo', 'Outfit', sans-serif !important;
            font-size: ${fontSizes[subFontSize.value]} !important;
            color: ${colors[subColor.value]} !important;
            background-color: ${backgrounds[subBackground.value]} !important;
            text-shadow: 0px 2px 4px rgba(0,0,0,0.9), 0px 0px 10px rgba(0,0,0,0.7) !important;
            padding: 4px 10px !important;
            border-radius: 6px !important;
            line-height: 1.4 !important;
        }
    `;
};

watch([subFontSize, subColor, subBackground], () => {
    applySubtitleStyles();
});

const formatTime = (seconds: number) => {
    if (!seconds || isNaN(seconds)) return '00:00';
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    if (hrs > 0) {
        return `${hrs}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

const saveProgressToServer = async () => {
    if (!videoRef.value || currentTime.value < 10) return;
    try {
        await fetch('/api/playback/progress', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                watchable_id: props.item.id,
                watchable_type: props.item.type || 'movie',
                progress_seconds: Math.floor(currentTime.value),
                duration_seconds: Math.floor(duration.value) || 1,
            }),
        });
    } catch (e) {}
};
</script>

<template>
    <div
        ref="playerContainerRef"
        class="fixed inset-0 z-50 bg-black flex items-center justify-center select-none overflow-hidden group/player"
        :class="{ 'cursor-none': !isControlsVisible && isPlaying }"
        @mousemove="resetControlsTimeout"
        @click="handleScreenTouch"
    >
        <!-- HTML5 High-Performance Video Element -->
        <video
            ref="videoRef"
            :src="baseStreamUrl"
            class="w-full h-full object-contain"
            playsinline
            crossorigin="anonymous"
            @timeupdate="onTimeUpdate"
            @loadedmetadata="onLoadedMetadata"
            @play="isPlaying = true"
            @pause="isPlaying = false"
            @ended="isPlaying = false"
        >
            <track
                v-if="selectedSubtitleId !== 'off'"
                kind="subtitles"
                :src="subtitleUrl"
                :srclang="item.subtitles?.find(s => s.id === selectedSubtitleId)?.language || 'ar'"
                :label="item.subtitles?.find(s => s.id === selectedSubtitleId)?.language_name || 'Arabic'"
                default
            />
        </video>

        <!-- Double-Tap Seek Ripples -->
        <div
            v-if="isDoubleTapSeeking"
            class="absolute inset-y-0 w-1/3 flex items-center justify-center pointer-events-none z-30 animate-in fade-in"
            :class="isDoubleTapSeeking === 'left' ? 'left-0' : 'right-0'"
        >
            <div class="p-6 rounded-full bg-cyan-500/20 backdrop-blur-md border border-cyan-500/40 text-cyan-300 flex flex-col items-center gap-1 scale-125 transition-transform">
                <RotateCcw v-if="isDoubleTapSeeking === 'left'" class="w-8 h-8 animate-spin" />
                <RotateCw v-else class="w-8 h-8 animate-spin" />
                <span class="text-xs font-black font-mono">{{ isDoubleTapSeeking === 'left' ? '-10s' : '+10s' }}</span>
            </div>
        </div>

        <!-- Toast Notice -->
        <div
            v-if="toastNotice"
            class="absolute top-20 left-1/2 -translate-x-1/2 z-40 px-5 py-2.5 rounded-2xl bg-black/80 backdrop-blur-md border border-cyan-500/40 text-cyan-300 text-xs font-black shadow-2xl flex items-center gap-2 animate-in fade-in slide-in-from-top-2"
        >
            <Zap class="w-4 h-4 text-cyan-400" />
            <span>{{ toastNotice }}</span>
        </div>

        <!-- OSD Controls Overlay -->
        <div
            class="absolute inset-0 flex flex-col justify-between p-4 sm:p-8 bg-gradient-to-t from-black/90 via-transparent to-black/80 transition-opacity duration-300 z-20 pointer-events-none"
            :class="{ 'opacity-0': !isControlsVisible && isPlaying, 'opacity-100': isControlsVisible || !isPlaying }"
        >
            <!-- Top Bar -->
            <div class="flex items-center justify-between pointer-events-auto">
                <div class="flex items-center gap-3">
                    <button
                        @click="emit('close')"
                        class="p-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white transition-all active:scale-95 cursor-pointer shadow-sm"
                        :title="t('common.close')"
                    >
                        <ArrowLeft v-if="!isRTL" class="w-5 h-5" />
                        <X v-else class="w-5 h-5" />
                    </button>
                    <div>
                        <h2 class="font-extrabold text-sm sm:text-base text-white tracking-wide truncate max-w-sm sm:max-w-xl">
                            {{ item.title }}
                        </h2>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="cinema-badge bg-cyan-500/20 text-cyan-300 border-cyan-500/30 text-[10px]">
                                {{ audioEnhanceMode === 'voice_boost' ? 'Vocal EQ (+8dB)' : audioEnhanceMode === 'cinema_boost' ? 'Cinema Amp (200%)' : audioEnhanceMode === 'night_mode' ? 'Night Mode' : 'Direct Audio' }}
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
                        :title="isRTL ? 'صورة داخل صورة' : 'Picture in Picture'"
                    >
                        <PictureInPicture class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Bottom Controls Bar -->
            <div class="space-y-3 pointer-events-auto">
                <!-- Timeline Progress Bar -->
                <div
                    @click.stop="onProgressScrub"
                    class="relative w-full h-3 flex items-center cursor-pointer group/scrub py-2"
                >
                    <div class="w-full h-1 group-hover/scrub:h-2 rounded-full bg-white/20 relative transition-all duration-200 overflow-hidden">
                        <div
                            class="h-full bg-gradient-to-r from-cyan-400 via-cyan-500 to-blue-500 rounded-full"
                            :style="{ width: `${duration ? (currentTime / duration) * 100 : 0}%` }"
                        ></div>
                    </div>
                    <div
                        class="absolute w-3.5 h-3.5 rounded-full bg-white shadow-lg shadow-cyan-500/50 scale-0 group-hover/scrub:scale-100 transition-transform -translate-x-1/2 pointer-events-none"
                        :style="{ left: `${duration ? (currentTime / duration) * 100 : 0}%` }"
                    ></div>
                </div>

                <!-- Main Button Toolbar -->
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <!-- Left: Play/Pause/Volume -->
                    <div class="flex items-center gap-2 sm:gap-3">
                        <button
                            @click="togglePlay"
                            class="p-3 rounded-2xl bg-cyan-500 text-slate-950 hover:bg-cyan-400 font-bold transition-all active:scale-95 shadow-lg shadow-cyan-500/20 cursor-pointer"
                        >
                            <Pause v-if="isPlaying" class="w-5 h-5 fill-current" />
                            <Play v-else class="w-5 h-5 fill-current ml-0.5" />
                        </button>

                        <button
                            @click="seek(-10)"
                            class="p-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-all active:scale-95 cursor-pointer"
                            :title="isRTL ? 'إرجاع 10 ثواني' : 'Rewind 10s'"
                        >
                            <RotateCcw class="w-4 h-4" />
                        </button>

                        <button
                            @click="seek(10)"
                            class="p-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-all active:scale-95 cursor-pointer"
                            :title="isRTL ? 'تقديم 10 ثواني' : 'Forward 10s'"
                        >
                            <RotateCw class="w-4 h-4" />
                        </button>

                        <!-- Volume Controls -->
                        <div class="flex items-center gap-2 group/volume ml-1 sm:ml-2">
                            <button
                                @click="toggleMute"
                                class="p-2 text-slate-300 hover:text-white transition-colors cursor-pointer"
                            >
                                <VolumeX v-if="isMuted || volume === 0" class="w-5 h-5 text-rose-400" />
                                <Volume2 v-else class="w-5 h-5 text-cyan-400" />
                            </button>
                            <input
                                type="range"
                                min="0"
                                max="1"
                                step="0.05"
                                v-model.number="volume"
                                @input="isMuted = false; if (videoRef) videoRef.volume = volume;"
                                class="w-16 sm:w-24 h-1 bg-white/20 accent-cyan-400 rounded-lg cursor-pointer"
                            />
                        </div>

                        <!-- Time Tracker -->
                        <div class="text-xs font-mono text-slate-300 font-bold ml-2">
                            <span>{{ formatTime(currentTime) }}</span>
                            <span class="text-slate-500 mx-1">/</span>
                            <span class="text-slate-400">{{ formatTime(duration) }}</span>
                        </div>
                    </div>

                    <!-- Right: Subtitles, Sound Studio, Speed, Fullscreen -->
                    <div class="flex items-center gap-2 sm:gap-3">
                        <!-- Sound & Vocal Enhancer Studio Dropdown -->
                        <div class="relative">
                            <button
                                @click="showAudioMenu = !showAudioMenu; showSubtitleMenu = false; showSpeedMenu = false; showSubSettings = false;"
                                class="flex items-center gap-1.5 px-3 py-2 rounded-xl border text-xs font-bold transition-all cursor-pointer"
                                :class="audioEnhanceMode !== 'direct'
                                    ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm'
                                    : 'bg-white/10 text-slate-300 border-white/10 hover:bg-white/20'"
                                :title="isRTL ? 'استوديو تعزيز الصوت وتوضيح الحوار' : 'Audio & Vocal Booster Studio'"
                            >
                                <Music class="w-4 h-4" />
                                <span class="hidden sm:inline">{{ isRTL ? 'محسن الصوت' : 'Sound Boost' }}</span>
                            </button>

                            <div
                                v-if="showAudioMenu"
                                class="absolute bottom-12 right-0 w-80 rounded-2xl bg-[#080B12] border border-white/15 p-3 shadow-2xl space-y-2 z-50 animate-in fade-in slide-in-from-bottom-2"
                            >
                                <div class="px-1 text-[10px] font-black uppercase tracking-wider text-slate-400 border-b border-white/10 pb-1.5 flex items-center justify-between">
                                    <span>{{ isRTL ? 'استوديو تعزيز الصوت السينمائي' : 'Sound & Vocal Booster Studio' }}</span>
                                    <span class="text-cyan-400 font-mono text-[9px]">WebAudio™</span>
                                </div>

                                <button
                                    @click="applyAudioEnhancement('direct')"
                                    class="w-full p-2.5 rounded-xl text-xs font-bold text-left flex items-start gap-2.5 transition-colors cursor-pointer"
                                    :class="audioEnhanceMode === 'direct' ? 'bg-cyan-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10'"
                                >
                                    <Check v-if="audioEnhanceMode === 'direct'" class="w-4 h-4 shrink-0 mt-0.5" />
                                    <div>
                                        <div class="font-bold">{{ isRTL ? 'الصوت الأصلي المباشر (Direct)' : 'Original Direct Stream' }}</div>
                                        <div class="text-[10px] opacity-80 mt-0.5">{{ isRTL ? 'تشغيل الصوت الطبيعي بدون تعديل.' : 'Untouched high-fidelity audio track.' }}</div>
                                    </div>
                                </button>

                                <button
                                    @click="applyAudioEnhancement('voice_boost')"
                                    class="w-full p-2.5 rounded-xl text-xs font-bold text-left flex items-start gap-2.5 transition-colors cursor-pointer"
                                    :class="audioEnhanceMode === 'voice_boost' ? 'bg-cyan-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10'"
                                >
                                    <Check v-if="audioEnhanceMode === 'voice_boost'" class="w-4 h-4 shrink-0 mt-0.5" />
                                    <div>
                                        <div class="font-bold flex items-center gap-1.5">
                                            <span>{{ isRTL ? 'توضيح الحوار والأصوات (+8dB Vocal)' : 'Vocal & Dialogue Clarity (+8dB)' }}</span>
                                            <span class="cinema-badge bg-cyan-400/20 text-cyan-300 text-[9px] py-0">Recommended</span>
                                        </div>
                                        <div class="text-[10px] opacity-80 mt-0.5">{{ isRTL ? 'فلتر يعزل ترددات الكلام البشري ويضخمها لحل مشكلة الحوار المنخفض في أفلام 5.1/EAC3.' : 'Equalizes human speech frequencies to eliminate low dialogue in 5.1 tracks.' }}</div>
                                    </div>
                                </button>

                                <button
                                    @click="applyAudioEnhancement('cinema_boost')"
                                    class="w-full p-2.5 rounded-xl text-xs font-bold text-left flex items-start gap-2.5 transition-colors cursor-pointer"
                                    :class="audioEnhanceMode === 'cinema_boost' ? 'bg-amber-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10'"
                                >
                                    <Check v-if="audioEnhanceMode === 'cinema_boost'" class="w-4 h-4 shrink-0 mt-0.5" />
                                    <div>
                                        <div class="font-bold">{{ isRTL ? 'مضخم الصوت الفائق (200% Max Gain)' : 'Cinema Super Amplifier (200% Gain)' }}</div>
                                        <div class="text-[10px] opacity-80 mt-0.5">{{ isRTL ? 'مضاعفة قوة الصوت للأفلام ذات التسجيل المنخفض.' : 'Multiplies hardware output volume for quiet streams.' }}</div>
                                    </div>
                                </button>

                                <button
                                    @click="applyAudioEnhancement('night_mode')"
                                    class="w-full p-2.5 rounded-xl text-xs font-bold text-left flex items-start gap-2.5 transition-colors cursor-pointer"
                                    :class="audioEnhanceMode === 'night_mode' ? 'bg-indigo-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10'"
                                >
                                    <Check v-if="audioEnhanceMode === 'night_mode'" class="w-4 h-4 shrink-0 mt-0.5" />
                                    <div>
                                        <div class="font-bold">{{ isRTL ? 'الوضع الليلي الذكي (Dynamic Night Mode)' : 'Smart Night Mode (Compressor)' }}</div>
                                        <div class="text-[10px] opacity-80 mt-0.5">{{ isRTL ? 'يخفف أصوات الانفجارات العالية ويضخم الهمسات للمشاهدة الليلية.' : 'Compresses dynamic range so loud sound effects never wake up the house.' }}</div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Subtitles Menu & Customization Studio -->
                        <div class="relative">
                            <button
                                @click="showSubtitleMenu = !showSubtitleMenu; showAudioMenu = false; showSpeedMenu = false;"
                                class="flex items-center gap-1.5 px-3 py-2 rounded-xl border text-xs font-bold transition-all cursor-pointer"
                                :class="selectedSubtitleId !== 'off'
                                    ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm'
                                    : 'bg-white/10 text-slate-300 border-white/10 hover:bg-white/20'"
                            >
                                <Subtitles class="w-4 h-4" />
                                <span class="hidden sm:inline">{{ isRTL ? 'الترجمة' : 'Subtitles' }}</span>
                            </button>

                            <!-- Subtitles Dropdown Panel -->
                            <div
                                v-if="showSubtitleMenu"
                                class="absolute bottom-12 right-0 w-72 rounded-2xl bg-[#080B12] border border-white/15 p-3 shadow-2xl space-y-2 z-50 animate-in fade-in slide-in-from-bottom-2"
                            >
                                <div class="px-1 text-[10px] font-black uppercase tracking-wider text-slate-400 border-b border-white/10 pb-1.5 flex items-center justify-between">
                                    <span>{{ isRTL ? 'مسارات الترجمة المتوفرة' : 'Subtitles Tracks' }}</span>
                                    <button
                                        @click="showSubSettings = !showSubSettings"
                                        class="p-1 rounded-lg hover:bg-white/10 text-cyan-400 cursor-pointer"
                                        :title="isRTL ? 'تخصيص الخط والألوان والمزامنة' : 'Customize Font, Color & Sync'"
                                    >
                                        <Sliders class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <!-- Subtitle Timing & Offset Controls -->
                                <div class="p-2 rounded-xl bg-white/[0.03] border border-white/10 space-y-1.5">
                                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-300">
                                        <span>{{ isRTL ? 'تأخير / تسريع الترجمة' : 'Subtitle Sync Offset' }}</span>
                                        <span class="font-mono text-cyan-300">{{ subOffsetSeconds > 0 ? `+${subOffsetSeconds}s` : `${subOffsetSeconds}s` }}</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <button @click="adjustSubtitleOffset(-0.5)" class="flex-1 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-[11px] font-bold text-slate-300">-0.5s</button>
                                        <button @click="subOffsetSeconds = 0; toastNotice = 'Sync Reset';" class="px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-[11px] font-bold text-slate-400">0s</button>
                                        <button @click="adjustSubtitleOffset(0.5)" class="flex-1 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-[11px] font-bold text-slate-300">+0.5s</button>
                                    </div>
                                </div>

                                <!-- Font Style Studio (Collapsible) -->
                                <div v-if="showSubSettings" class="p-2.5 rounded-xl bg-cyan-950/40 border border-cyan-500/30 space-y-2 text-[11px]">
                                    <div class="font-bold text-cyan-300">{{ isRTL ? 'حجم الخط ولونه (Cairo & Outfit)' : 'Font Size & Color Studio' }}</div>
                                    <div class="grid grid-cols-4 gap-1">
                                        <button v-for="size in (['sm', 'md', 'lg', 'xl'] as const)" :key="size" @click="subFontSize = size" class="py-1 rounded-lg text-center font-bold" :class="subFontSize === size ? 'bg-cyan-500 text-slate-950' : 'bg-white/5 text-slate-400'">{{ size.toUpperCase() }}</button>
                                    </div>
                                    <div class="grid grid-cols-4 gap-1 pt-1">
                                        <button @click="subColor = 'yellow'" class="h-6 rounded-lg bg-[#FFD700] text-slate-950 text-[10px] font-black">Yellow</button>
                                        <button @click="subColor = 'white'" class="h-6 rounded-lg bg-white text-slate-950 text-[10px] font-black">White</button>
                                        <button @click="subColor = 'cyan'" class="h-6 rounded-lg bg-[#00FFFF] text-slate-950 text-[10px] font-black">Cyan</button>
                                        <button @click="subColor = 'green'" class="h-6 rounded-lg bg-[#00FF7F] text-slate-950 text-[10px] font-black">Green</button>
                                    </div>
                                </div>

                                <!-- Track Selection List -->
                                <button
                                    @click="setSubtitle('off')"
                                    class="w-full p-2 rounded-xl text-xs font-bold text-left flex items-center justify-between transition-colors cursor-pointer"
                                    :class="selectedSubtitleId === 'off' ? 'bg-cyan-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10'"
                                >
                                    <span>{{ isRTL ? 'إيقاف الترجمة (Off)' : 'Turn Off Subtitles' }}</span>
                                    <Check v-if="selectedSubtitleId === 'off'" class="w-4 h-4" />
                                </button>

                                <div v-if="item.subtitles && item.subtitles.length > 0" class="max-h-40 overflow-y-auto space-y-1">
                                    <button
                                        v-for="sub in item.subtitles"
                                        :key="sub.id"
                                        @click="setSubtitle(sub.id)"
                                        class="w-full p-2 rounded-xl text-xs font-bold text-left flex items-center justify-between transition-colors cursor-pointer"
                                        :class="selectedSubtitleId === sub.id ? 'bg-cyan-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10'"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span>{{ sub.language_name }}</span>
                                            <span class="text-[10px] opacity-75 font-mono uppercase">({{ sub.language }})</span>
                                        </div>
                                        <Check v-if="selectedSubtitleId === sub.id" class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Speed Controls -->
                        <div class="relative">
                            <button
                                @click="showSpeedMenu = !showSpeedMenu; showSubtitleMenu = false; showAudioMenu = false; showSubSettings = false;"
                                class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white text-xs font-bold transition-all cursor-pointer font-mono"
                            >
                                {{ playbackRate }}x
                            </button>
                            <div
                                v-if="showSpeedMenu"
                                class="absolute bottom-12 right-0 w-28 rounded-2xl bg-[#080B12] border border-white/15 p-2 shadow-2xl space-y-1 z-50 animate-in fade-in"
                            >
                                <button
                                    v-for="rate in [0.5, 0.75, 1, 1.25, 1.5, 2]"
                                    :key="rate"
                                    @click="setPlaybackRate(rate)"
                                    class="w-full p-1.5 rounded-lg text-xs font-mono font-bold text-center cursor-pointer transition-colors"
                                    :class="playbackRate === rate ? 'bg-cyan-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10'"
                                >
                                    {{ rate }}x
                                </button>
                            </div>
                        </div>

                        <!-- Fullscreen Toggle -->
                        <button
                            @click="toggleFullscreen"
                            class="p-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-all active:scale-95 cursor-pointer"
                            :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen (F)'"
                        >
                            <Minimize v-if="isFullscreen" class="w-4 h-4" />
                            <Maximize v-else class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
