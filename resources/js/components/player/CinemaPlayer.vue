<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { MoviPlayer } from 'movi-player/vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Play, Pause, Volume2, VolumeX, Maximize, Minimize,
    RotateCcw, RotateCw, Subtitles, X,
    Sparkles, ArrowLeft, Volume1,
    Check, PictureInPicture
} from 'lucide-vue-next';

const props = defineProps<{
    item: {
        id: number;
        title: string;
        title_ar?: string;
        type?: 'movie' | 'episode';
        file_path?: string;
        progress_seconds?: number;
        subtitles?: Array<{
            id: number;
            language: string;
            language_name: string;
            file_path?: string;
            is_embedded?: boolean;
            format?: string;
        }>;
    };
}>();

const emit = defineEmits(['close']);

const { t, isRTL } = useI18n();

// Player Engine: 'movi' (WebCodecs WASM) vs 'native' (HTML5 Video + Custom Cinema UI)
const playerEngine = ref<'movi' | 'native'>(
    (localStorage.getItem('preferred_player_engine') as any) || 'movi'
);

const setPlayerEngine = (engine: 'movi' | 'native') => {
    playerEngine.value = engine;
    localStorage.setItem('preferred_player_engine', engine);
    toastNotice.value = engine === 'movi'
        ? (isRTL.value ? 'تم تفعيل مشغل MoviPlayer (WebCodecs WASM)' : 'Active: MoviPlayer (WebCodecs WASM)')
        : (isRTL.value ? 'تم تفعيل مشغل Cinema المخصص' : 'Active: Cinema Custom Player');
    setTimeout(() => { toastNotice.value = ''; }, 3000);
};

const moviRef = ref<any>(null);

const onMoviTimeUpdate = (e: any) => {
    const el = moviRef.value?.element?.value || moviRef.value?.$el || moviRef.value;
    if (el && typeof el.currentTime === 'number') {
        currentTime.value = el.currentTime;
        if (el.duration && isFinite(el.duration) && el.duration > 0) {
            duration.value = el.duration;
        }
    }
};

const videoRef = ref<HTMLVideoElement | null>(null);
const playerContainerRef = ref<HTMLDivElement | null>(null);
const scrubberRef = ref<HTMLDivElement | null>(null);

const isPlaying = ref(false);
const isMuted = ref(false);
const volume = ref(1);
const currentTime = ref(0);
const streamStartOffset = ref(0);
const duration = ref<number>(
    (Number(props.item.runtime_minutes) > 0 ? Number(props.item.runtime_minutes) * 60 : 0) ||
    Number(props.item.duration_seconds) ||
    Number(localStorage.getItem(`duration_${props.item.type || 'episode'}_${props.item.id}`)) ||
    1320
);
const isFullscreen = ref(false);
const isPiP = ref(false);
const isControlsVisible = ref(true);
const playbackRate = ref(1);

// Scrubber state
const isScrubbing = ref(false);
const hoverTime = ref<number | null>(null);
const hoverPos = ref(0);

// Subtitles state
const availableSubtitles = ref<any[]>(props.item.subtitles || []);
const selectedSubtitleId = ref<number | 'off'>('off');
const showSubtitleMenu = ref(false);
const subOffsetSeconds = ref(0);

// Menus & Notifications


const openInVlc = () => {
    const rawUrl = window.location.origin + (isEpisode.value ? `/stream/episode/${props.item.id}` : `/stream/movie/${props.item.id}`);
    window.location.href = `vlc://${rawUrl}`;
};

const openInPotPlayer = () => {
    const rawUrl = window.location.origin + (isEpisode.value ? `/stream/episode/${props.item.id}` : `/stream/movie/${props.item.id}`);
    window.location.href = `potplayer://${rawUrl}`;
};




const showSpeedMenu = ref(false);
const showAudioMenu = ref(false);
const toastNotice = ref('');

// Audio Enhancer Modes (Web Audio API)
type AudioEnhanceMode = 'direct' | 'voice_boost' | 'cinema_boost' | 'night_mode';
const audioEnhanceMode = ref<AudioEnhanceMode>('direct');

let audioCtx: AudioContext | null = null;
let sourceNode: MediaElementAudioSourceNode | null = null;
let voiceFilterNode: BiquadFilterNode | null = null;
let bassFilterNode: BiquadFilterNode | null = null;
let trebleFilterNode: BiquadFilterNode | null = null;
let compressorNode: DynamicsCompressorNode | null = null;
let gainNode: GainNode | null = null;
let isAudioPipelineInitialized = false;

let controlsTimeout: any = null;
let progressSaveInterval: any = null;

// Check if item is episode or movie
// Check if WebCodecs WASM demuxer player is required (for MKV, 10-bit HEVC, AVI)
const isWasmRequired = computed(() => {
    const path = props.item.file_path || '';
    const ext = path.split('.').pop()?.toLowerCase();
    return ['mkv', 'avi', 'wmv', 'ts'].includes(ext || '') || true; // MoviPlayer handles all containers seamlessly
});

const isEpisode = computed(() => {
    return props.item.type === 'episode' 
        || props.item.watchable_type === 'episode' 
        || !!props.item.episode_number 
        || !!props.item.season_id;
});

// Stream URL: Pure Direct HTTP Range Stream (Zero FFmpeg)
const streamUrl = computed(() => {
    if (isEpisode.value) {
        return `/stream/episode/${props.item.id}`;
    }
    return `/stream/movie/${props.item.id}`;
});

// Active Subtitle WebVTT URL
const subtitleUrl = computed(() => {
    if (selectedSubtitleId.value === 'off') return '';
    return `/stream/subtitles/${selectedSubtitleId.value}`;
});

// Progress Percent for Scrubber
const progressPercent = computed(() => {
    if (!duration.value || duration.value <= 0) return 0;
    return Math.min(100, Math.max(0, (currentTime.value / duration.value) * 100));
});

// Fetch Subtitles if missing
const fetchMediaDuration = async () => {
    try {
        const type = isEpisode.value ? 'episode' : 'movie';
        const res = await fetch(`/api/media/duration?type=${type}&id=${props.item.id}`);
        if (res.ok) {
            const data = await res.json();
            if (data.duration_seconds && data.duration_seconds > 0) {
                duration.value = data.duration_seconds;
            }
        }
    } catch (e) {
        console.warn('Failed to fetch duration', e);
    }
};

const fetchSubtitles = async () => {
    const type = isEpisode.value ? 'episode' : 'movie';
    try {
        const res = await fetch(`/api/subtitles/for-media?type=${type}&id=${props.item.id}`);
        if (res.ok) {
            const data = await res.json();
            if (data.subtitles && data.subtitles.length > 0) {
                availableSubtitles.value = data.subtitles;
                autoSelectBestSubtitle();
            }
        }
    } catch (e) {
        console.error('Failed to fetch subtitles', e);
    }
};

const autoSelectBestSubtitle = () => {
    if (availableSubtitles.value.length === 0) return;
    
    const arSub = availableSubtitles.value.find(s => s.language === 'ar');
    const enSub = availableSubtitles.value.find(s => s.language === 'en');
    
    if (arSub) {
        selectedSubtitleId.value = arSub.id;
    } else if (enSub) {
        selectedSubtitleId.value = enSub.id;
    } else {
        selectedSubtitleId.value = availableSubtitles.value[0].id;
    }
    
    enableTrackMode();
};

const enableTrackMode = () => {
    nextTick(() => {
        if (!videoRef.value) return;
        const tracks = videoRef.value.textTracks;
        for (let i = 0; i < tracks.length; i++) {
            if (selectedSubtitleId.value !== 'off') {
                tracks[i].mode = 'showing';
            } else {
                tracks[i].mode = 'disabled';
            }
        }
    });
};

const setSubtitle = (subId: number | 'off') => {
    selectedSubtitleId.value = subId;
    showSubtitleMenu.value = false;
    enableTrackMode();
    
    if (subId === 'off') {
        toastNotice.value = isRTL.value ? 'تم إيقاف الترجمة' : 'Subtitles Off';
    } else {
        const track = availableSubtitles.value.find(s => s.id === subId);
        toastNotice.value = `${isRTL.value ? 'الترجمة:' : 'Subtitles:'} ${track?.language_name || 'Active'}`;
    }
    setTimeout(() => { toastNotice.value = ''; }, 2000);
};

const adjustSubtitleOffset = (delta: number) => {
    subOffsetSeconds.value = Math.round((subOffsetSeconds.value + delta) * 10) / 10;
    toastNotice.value = `${isRTL.value ? 'توقيت الترجمة' : 'Subtitle Sync'}: ${subOffsetSeconds.value > 0 ? '+' : ''}${subOffsetSeconds.value}s`;
    setTimeout(() => { toastNotice.value = ''; }, 2000);
};

// Play / Pause Toggle
const togglePlay = () => {
    if (!videoRef.value) return;
    initAudioContext();
    if (videoRef.value.paused) {
        videoRef.value.play().catch(e => console.warn('Play interrupted', e));
        isPlaying.value = true;
    } else {
        videoRef.value.pause();
        isPlaying.value = false;
        saveWatchProgress();
    }
};

// Scrubber Click & Drag Handlers
const handleScrubberClick = (e: MouseEvent) => {
    if (!scrubberRef.value || !duration.value) return;
    const rect = scrubberRef.value.getBoundingClientRect();
    const pos = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    seek(pos * duration.value);
};

const handleScrubberMouseMove = (e: MouseEvent) => {
    if (!scrubberRef.value || !duration.value) return;
    const rect = scrubberRef.value.getBoundingClientRect();
    const pos = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    hoverPos.value = e.clientX - rect.left;
    hoverTime.value = pos * duration.value;
};

const handleScrubberMouseLeave = () => {
    hoverTime.value = null;
};

const seek = (seconds: number) => {
    if (!videoRef.value) return;
    const target = Math.max(0, Math.min(duration.value || 1320, seconds));
    currentTime.value = target;
    streamStartOffset.value = Math.floor(target);
    
    nextTick(() => {
        if (videoRef.value) {
            videoRef.value.currentTime = 0;
            videoRef.value.play().catch(() => {});
        }
    });
};

const seekRelative = (delta: number) => {
    seek(currentTime.value + delta);
};

const setVolume = (val: number) => {
    if (!videoRef.value) return;
    initAudioContext();
    volume.value = Math.max(0, Math.min(1, val));
    videoRef.value.volume = volume.value;
    isMuted.value = volume.value === 0;
};

const toggleMute = () => {
    if (!videoRef.value) return;
    initAudioContext();
    isMuted.value = !isMuted.value;
    videoRef.value.muted = isMuted.value;
};

const setSpeed = (rate: number) => {
    if (!videoRef.value) return;
    playbackRate.value = rate;
    videoRef.value.playbackRate = rate;
    showSpeedMenu.value = false;
};

// Audio Engine Setup (Web Audio API)
const initAudioContext = () => {
    if (isAudioPipelineInitialized || !videoRef.value) return;
    try {
        const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioContextClass) return;

        audioCtx = new AudioContextClass();
        sourceNode = audioCtx.createMediaElementSource(videoRef.value);

        voiceFilterNode = audioCtx.createBiquadFilter();
        voiceFilterNode.type = 'peaking';
        voiceFilterNode.frequency.value = 2500;
        voiceFilterNode.Q.value = 1.0;
        voiceFilterNode.gain.value = 0;

        bassFilterNode = audioCtx.createBiquadFilter();
        bassFilterNode.type = 'lowshelf';
        bassFilterNode.frequency.value = 90;
        bassFilterNode.gain.value = 0;

        trebleFilterNode = audioCtx.createBiquadFilter();
        trebleFilterNode.type = 'highshelf';
        trebleFilterNode.frequency.value = 7000;
        trebleFilterNode.gain.value = 0;

        compressorNode = audioCtx.createDynamicsCompressor();
        compressorNode.threshold.value = -24;
        compressorNode.knee.value = 30;
        compressorNode.ratio.value = 12;
        compressorNode.attack.value = 0.003;
        compressorNode.release.value = 0.25;

        gainNode = audioCtx.createGain();
        gainNode.gain.value = 1.0;

        sourceNode.connect(voiceFilterNode);
        voiceFilterNode.connect(bassFilterNode);
        bassFilterNode.connect(trebleFilterNode);
        trebleFilterNode.connect(compressorNode);
        compressorNode.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        isAudioPipelineInitialized = true;
    } catch (e) {
        console.warn('Web Audio API initialized with direct fallback', e);
    }
};

const setAudioEnhance = (mode: AudioEnhanceMode) => {
    initAudioContext();
    audioEnhanceMode.value = mode;
    showAudioMenu.value = false;

    if (!audioCtx || !voiceFilterNode || !bassFilterNode || !trebleFilterNode || !gainNode) return;

    if (audioCtx.state === 'suspended') {
        audioCtx.resume();
    }

    const now = audioCtx.currentTime;
    if (mode === 'direct') {
        voiceFilterNode.gain.setTargetAtTime(0, now, 0.05);
        bassFilterNode.gain.setTargetAtTime(0, now, 0.05);
        trebleFilterNode.gain.setTargetAtTime(0, now, 0.05);
        gainNode.gain.setTargetAtTime(1.0, now, 0.05);
        toastNotice.value = isRTL.value ? 'الصوت المباشر الأصلي' : 'Direct Pure Audio';
    } else if (mode === 'voice_boost') {
        voiceFilterNode.gain.setTargetAtTime(8.5, now, 0.05);
        bassFilterNode.gain.setTargetAtTime(-3.5, now, 0.05);
        trebleFilterNode.gain.setTargetAtTime(2.0, now, 0.05);
        gainNode.gain.setTargetAtTime(1.25, now, 0.05);
        toastNotice.value = isRTL.value ? 'معزز وضوح الحوار والأصوات' : 'Dialogue Clarity Booster';
    } else if (mode === 'cinema_boost') {
        voiceFilterNode.gain.setTargetAtTime(3.5, now, 0.05);
        bassFilterNode.gain.setTargetAtTime(6.5, now, 0.05);
        trebleFilterNode.gain.setTargetAtTime(4.0, now, 0.05);
        gainNode.gain.setTargetAtTime(1.15, now, 0.05);
        toastNotice.value = isRTL.value ? 'المسرح السينمائي المحيطي 3D' : 'Cinema Surround 3D Mode';
    } else if (mode === 'night_mode') {
        voiceFilterNode.gain.setTargetAtTime(5.0, now, 0.05);
        bassFilterNode.gain.setTargetAtTime(-9.0, now, 0.05);
        trebleFilterNode.gain.setTargetAtTime(-2.0, now, 0.05);
        gainNode.gain.setTargetAtTime(0.95, now, 0.05);
        toastNotice.value = isRTL.value ? 'الوضع الليلي (تخفيض الانفجارات)' : 'Night Mode (Explosion Dampener)';
    }

    setTimeout(() => { toastNotice.value = ''; }, 2500);
};

// Toggle Fullscreen & PiP
const toggleFullscreen = () => {
    if (!playerContainerRef.value) return;
    if (!document.fullscreenElement) {
        playerContainerRef.value.requestFullscreen().catch(e => console.warn(e));
        isFullscreen.value = true;
    } else {
        document.exitFullscreen().catch(e => console.warn(e));
        isFullscreen.value = false;
    }
};

const togglePiP = async () => {
    if (!videoRef.value) return;
    try {
        if (document.pictureInPictureElement) {
            await document.exitPictureInPicture();
            isPiP.value = false;
        } else {
            await videoRef.value.requestPictureInPicture();
            isPiP.value = true;
        }
    } catch (e) {
        console.warn('PiP error', e);
    }
};

// UI Controls Auto-Hide
const showControlsTemporarily = () => {
    isControlsVisible.value = true;
    clearTimeout(controlsTimeout);
    if (isPlaying.value && !showSpeedMenu.value && !showSubtitleMenu.value && !showAudioMenu.value) {
        controlsTimeout = setTimeout(() => {
            isControlsVisible.value = false;
        }, 3500);
    }
};

// Watch Progress Reporting
const saveWatchProgress = async () => {
    if (!videoRef.value || duration.value <= 0) return;
    const pos = Math.floor(videoRef.value.currentTime);
    const dur = Math.floor(duration.value);
    
    if (pos < 2) return;

    localStorage.setItem(`progress_${isEpisode.value ? 'episode' : 'movie'}_${props.item.id}`, String(pos));
    localStorage.setItem(`duration_${isEpisode.value ? 'episode' : 'movie'}_${props.item.id}`, String(dur));

    try {
        await fetch('/api/playback/progress', {
            method: 'POST',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                watchable_id: props.item.id,
                watchable_type: isEpisode.value ? 'episode' : 'movie',
                progress_seconds: pos,
                duration_seconds: dur,
            }),
        });
    } catch (e) {}
};

// Time Formatter
const formatTime = (secs: number) => {
    if (!isFinite(secs) || isNaN(secs) || secs < 0) return '00:00';
    const h = Math.floor(secs / 3600);
    const m = Math.floor((secs % 3600) / 60);
    const s = Math.floor(secs % 60);
    if (h > 0) {
        return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
};

// Video Element Event Handlers
const onTimeUpdate = () => {
    if (!videoRef.value || isScrubbing.value) return;
    currentTime.value = streamStartOffset.value + videoRef.value.currentTime;

    // Only set duration if completely uninitialized and video.duration is a full length (> 120s)
    if (duration.value <= 0 && isFinite(videoRef.value.duration) && videoRef.value.duration > 120) {
        duration.value = videoRef.value.duration;
    }
    
    if (currentTime.value > 2 && props.item.id) {
        localStorage.setItem(`progress_${isEpisode.value ? 'episode' : 'movie'}_${props.item.id}`, String(Math.floor(currentTime.value)));
        if (duration.value > 0) {
            localStorage.setItem(`duration_${isEpisode.value ? 'episode' : 'movie'}_${props.item.id}`, String(Math.floor(duration.value)));
        }
    }
};

const onLoadedMetadata = () => {
    if (!videoRef.value) return;
    
    // Handle finite duration or fallback to database runtime/duration
    if (isFinite(videoRef.value.duration) && videoRef.value.duration > 0) {
        duration.value = videoRef.value.duration;
    } else if (props.item.duration_seconds && props.item.duration_seconds > 0) {
        duration.value = props.item.duration_seconds;
    } else if (props.item.runtime_minutes && props.item.runtime_minutes > 0) {
        duration.value = props.item.runtime_minutes * 60;
    } else {
        duration.value = Number(localStorage.getItem(`duration_${isEpisode.value ? 'episode' : 'movie'}_${props.item.id}`)) || 0;
    }
    
    const localSaved = Number(localStorage.getItem(`progress_${isEpisode.value ? 'episode' : 'movie'}_${props.item.id}`)) || 0;
    const resumePos = (props.item.progress_seconds && props.item.progress_seconds > 3) 
        ? props.item.progress_seconds 
        : localSaved;

    if (resumePos > 3 && resumePos < duration.value - 10) {
        videoRef.value.currentTime = resumePos;
        currentTime.value = resumePos;
        toastNotice.value = `${isRTL.value ? 'استئناف من:' : 'Resumed at:'} ${formatTime(resumePos)}`;
        setTimeout(() => { toastNotice.value = ''; }, 3000);
    }

    enableTrackMode();
};

const onVideoPlay = () => {
    isPlaying.value = true;
    showControlsTemporarily();
    if (audioCtx && audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
};

const onVideoPause = () => {
    isPlaying.value = false;
    isControlsVisible.value = true;
};

// Keyboard Shortcuts
const onKeyDown = (e: KeyboardEvent) => {
    if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return;

    if (e.code === 'Space' || e.code === 'KeyK') {
        e.preventDefault();
        togglePlay();
    } else if (e.code === 'ArrowRight') {
        e.preventDefault();
        seekRelative(isRTL.value ? -10 : 10);
    } else if (e.code === 'ArrowLeft') {
        e.preventDefault();
        seekRelative(isRTL.value ? 10 : -10);
    } else if (e.code === 'ArrowUp') {
        e.preventDefault();
        setVolume(volume.value + 0.1);
    } else if (e.code === 'ArrowDown') {
        e.preventDefault();
        setVolume(volume.value - 0.1);
    } else if (e.code === 'KeyF') {
        e.preventDefault();
        toggleFullscreen();
    } else if (e.code === 'KeyM') {
        e.preventDefault();
        toggleMute();
    } else if (e.code === 'Escape') {
        if (!isFullscreen.value) {
            handleClose();
        }
    }
};

const handleClose = () => {
    saveWatchProgress();
    emit('close');
};

const getFlagForLang = (lang: string) => {
    const map: Record<string, string> = {
        ar: '🇸🇦', en: '🇬🇧', es: '🇪🇸', fr: '🇫🇷', de: '🇩🇪',
        it: '🇮🇹', pt: '🇵🇹', ru: '🇷🇺', tr: '🇹🇷', fa: '🇮🇷',
        ja: '🇯🇵', ko: '🇰🇷', zh: '🇨🇳', hi: '🇮🇳', id: '🇮🇩',
        nl: '🇳🇱', pl: '🇵🇱', sv: '🇸🇪', da: '🇩🇰', no: '🇳🇴',
        fi: '🇫🇮', el: '🇬🇷', he: '🇮🇱', vi: '🇻🇳', th: '🇹🇭',
        ur: '🇵🇰', ro: '🇷🇴', cs: '🇨🇿', hu: '🇭🇺', uk: '🇺🇦'
    };
    return map[lang] || '🌐';
};

watch(() => props.item.id, () => {
    availableSubtitles.value = props.item.subtitles || [];
    fetchMediaDuration();
    if (availableSubtitles.value.length === 0) {
        fetchSubtitles();
    } else {
        autoSelectBestSubtitle();
    }
});

onMounted(() => {
    window.addEventListener('keydown', onKeyDown);
    window.addEventListener('beforeunload', saveWatchProgress);
    fetchMediaDuration();
    if (availableSubtitles.value.length === 0) {
        fetchSubtitles();
    } else {
        autoSelectBestSubtitle();
    }

    progressSaveInterval = setInterval(() => {
        if (isPlaying.value) {
            saveWatchProgress();
        }
    }, 4000);

    nextTick(() => {
        if (videoRef.value) {
            videoRef.value.play().catch(() => {});
        }
    });
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeyDown);
    window.removeEventListener('beforeunload', saveWatchProgress);
    if (progressSaveInterval) clearInterval(progressSaveInterval);
    if (controlsTimeout) clearTimeout(controlsTimeout);
    saveWatchProgress();
    if (audioCtx) {
        try { audioCtx.close(); } catch (e) {}
    }
});
</script>

<template>
    <div
        ref="playerContainerRef"
        @mousemove="showControlsTemporarily"
        @click="showControlsTemporarily"
        class="fixed inset-0 z-50 bg-black flex items-center justify-center select-none overflow-hidden font-sans"
        :class="{ 'cursor-none': !isControlsVisible && isPlaying && playerEngine === 'native' }"
    >
        <!-- Toast Notification -->
        <transition name="fade">
            <div
                v-if="toastNotice"
                class="absolute top-20 left-1/2 -translate-x-1/2 z-50 px-5 py-2.5 rounded-2xl bg-black/90 border border-cyan-500/50 text-cyan-300 font-extrabold text-sm backdrop-blur-xl shadow-2xl flex items-center gap-2 pointer-events-none"
            >
                <Sparkles class="w-4 h-4 text-cyan-400" />
                <span>{{ toastNotice }}</span>
            </div>
        </transition>

        <!-- Floating Top Bar (Always accessible for Switching & Exit) -->
        <transition name="fade">
            <div
                v-show="isControlsVisible || playerEngine === 'movi'"
                class="absolute top-0 inset-x-0 p-4 sm:p-6 bg-gradient-to-b from-black/90 via-black/40 to-transparent flex items-center justify-between z-40 transition-opacity"
            >
                <div class="flex items-center gap-4">
                    <button
                        @click="handleClose"
                        class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center backdrop-blur-md transition-transform hover:scale-110 active:scale-95 cursor-pointer"
                        title="Back to Library"
                    >
                        <ArrowLeft class="w-5 h-5" :class="isRTL ? 'rotate-180' : ''" />
                    </button>
                    <div>
                        <h2 class="font-extrabold text-sm sm:text-base text-white tracking-wide truncate max-w-xs sm:max-w-md">
                            {{ isRTL && item.title_ar ? item.title_ar : item.title }}
                        </h2>
                        <span class="text-[10px] font-mono text-cyan-400">
                            {{ playerEngine === 'movi' ? 'WebCodecs WASM Engine (Direct Zero-Transcode)' : 'Cinema Custom Engine' }}
                        </span>
                    </div>
                </div>

                <!-- Engine Switcher & Close Button -->
                <div class="flex items-center gap-3">
                    <!-- Engine Toggle Pill -->
                    <div class="flex items-center bg-black/70 border border-white/20 rounded-2xl p-1 text-xs font-bold shadow-lg backdrop-blur-md">
                        <button
                            @click="setPlayerEngine('movi')"
                            class="px-3 py-1.5 rounded-xl transition-all cursor-pointer flex items-center gap-1.5"
                            :class="playerEngine === 'movi' ? 'bg-cyan-500 text-slate-950 font-black shadow-md shadow-cyan-500/20' : 'text-slate-400 hover:text-white'"
                        >
                            <span>🚀 MoviPlayer (WASM)</span>
                        </button>
                        <button
                            @click="setPlayerEngine('native')"
                            class="px-3 py-1.5 rounded-xl transition-all cursor-pointer flex items-center gap-1.5"
                            :class="playerEngine === 'native' ? 'bg-cyan-500 text-slate-950 font-black shadow-md shadow-cyan-500/20' : 'text-slate-400 hover:text-white'"
                        >
                            <span>🎬 Cinema Custom</span>
                        </button>
                    </div>

                    <button
                        @click="handleClose"
                        class="w-10 h-10 rounded-full bg-white/10 hover:bg-red-500/80 text-white flex items-center justify-center backdrop-blur-md transition-all hover:scale-110 cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>
            </div>
        </transition>

        <!-- ========================================================= -->
        <!-- ENGINE 1: MOVI PLAYER (WebCodecs WASM - Standalone)      -->
        <!-- ========================================================= -->
        <div v-if="playerEngine === 'movi'" class="w-full h-full flex items-center justify-center bg-black pt-16 pb-4 px-4">
            <MoviPlayer
                ref="moviRef"
                :src="streamUrl"
                playsinline
                controls
                autoplay
                class="w-full h-full max-h-[90vh] object-contain rounded-2xl shadow-2xl overflow-hidden"
                @timeupdate="onMoviTimeUpdate"
                @play="isPlaying = true"
                @pause="isPlaying = false"
            >
                <track
                    v-if="selectedSubtitleId !== 'off'"
                    key="active-sub-track-movi"
                    kind="subtitles"
                    :src="subtitleUrl"
                    :srclang="availableSubtitles.find(s => s.id === selectedSubtitleId)?.language || 'ar'"
                    :label="availableSubtitles.find(s => s.id === selectedSubtitleId)?.language_name || 'Arabic'"
                    default
                />
            </MoviPlayer>
        </div>

        <!-- ========================================================= -->
        <!-- ENGINE 2: CINEMA CUSTOM PLAYER (Native Video + Overlays)  -->
        <!-- ========================================================= -->
        <template v-else>
            <!-- Native Video Element -->
            <video
                ref="videoRef"
                :src="streamUrl"
                @timeupdate="onTimeUpdate"
                @loadedmetadata="onLoadedMetadata"
                @play="onVideoPlay"
                @pause="onVideoPause"
                @click="togglePlay"
                class="w-full h-full object-contain bg-black"
                playsinline
                crossorigin="anonymous"
            >
                <track
                    v-if="selectedSubtitleId !== 'off'"
                    key="active-sub-track-native"
                    kind="subtitles"
                    :src="subtitleUrl"
                    :srclang="availableSubtitles.find(s => s.id === selectedSubtitleId)?.language || 'ar'"
                    :label="availableSubtitles.find(s => s.id === selectedSubtitleId)?.language_name || 'Arabic'"
                    default
                />
            </video>

            <!-- Center Big Play Button on Click -->
            <div
                v-if="!isPlaying && isControlsVisible"
                @click="togglePlay"
                class="absolute z-20 w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-cyan-500/90 text-slate-950 flex items-center justify-center shadow-2xl shadow-cyan-500/40 hover:scale-110 active:scale-95 transition-all cursor-pointer backdrop-blur-md"
            >
                <Play class="w-9 h-9 sm:w-11 sm:h-11 fill-current ml-1" />
            </div>

            <!-- Custom Bottom Controls Bar -->
            <transition name="fade">
                <div
                    v-show="isControlsVisible"
                    class="absolute bottom-0 inset-x-0 p-4 sm:p-6 bg-gradient-to-t from-black/95 via-black/75 to-transparent flex flex-col gap-3.5 z-30 transition-opacity"
                >
                    <!-- Custom Layered Scrubber Bar -->
                    <div
                        ref="scrubberRef"
                        @click="handleScrubberClick"
                        @mousemove="handleScrubberMouseMove"
                        @mouseleave="handleScrubberMouseLeave"
                        class="relative w-full h-7 flex items-center cursor-pointer group/scrub"
                    >
                        <!-- Hover Time Tooltip -->
                        <div
                            v-if="hoverTime !== null"
                            class="absolute -top-8 px-2.5 py-1 rounded-md bg-slate-900 border border-white/20 text-[11px] font-mono font-bold text-cyan-300 pointer-events-none -translate-x-1/2 shadow-lg"
                            :style="{ left: `${hoverPos}px` }"
                        >
                            {{ formatTime(hoverTime) }}
                        </div>

                        <!-- Scrubber Track -->
                        <div class="w-full h-2 group-hover/scrub:h-3 rounded-full bg-white/20 overflow-hidden relative transition-all duration-200">
                            <div
                                class="h-full bg-gradient-to-r from-cyan-400 to-blue-500 rounded-full transition-[width] duration-75"
                                :style="{ width: `${progressPercent}%` }"
                            ></div>
                        </div>

                        <!-- Glowing Thumb Pin -->
                        <div
                            class="absolute top-1/2 -translate-y-1/2 w-4 h-4 rounded-full bg-cyan-400 shadow-[0_0_12px_rgba(34,211,238,0.8)] border-2 border-white pointer-events-none transition-transform duration-75 group-hover/scrub:scale-125"
                            :style="{ left: `calc(${progressPercent}% - 8px)` }"
                        ></div>
                    </div>

                    <!-- Bottom Controls Row -->
                    <div class="flex items-center justify-between gap-4">
                        <!-- Left: Play/Pause, Rewind, Fast Forward, Volume -->
                        <div class="flex items-center gap-2 sm:gap-3">
                            <button
                                @click="togglePlay"
                                class="w-10 h-10 rounded-full bg-white/10 hover:bg-cyan-500 hover:text-slate-950 text-white flex items-center justify-center transition-all cursor-pointer"
                            >
                                <Pause v-if="isPlaying" class="w-5 h-5 fill-current" />
                                <Play v-else class="w-5 h-5 fill-current ml-0.5" />
                            </button>

                            <button
                                @click="seekRelative(-10)"
                                class="w-9 h-9 rounded-full bg-white/5 hover:bg-white/15 text-slate-300 hover:text-white flex items-center justify-center transition-all cursor-pointer"
                                title="Rewind 10s"
                            >
                                <RotateCcw class="w-4 h-4" />
                            </button>

                            <button
                                @click="seekRelative(10)"
                                class="w-9 h-9 rounded-full bg-white/5 hover:bg-white/15 text-slate-300 hover:text-white flex items-center justify-center transition-all cursor-pointer"
                                title="Forward 10s"
                            >
                                <RotateCw class="w-4 h-4" />
                            </button>

                            <!-- Volume Slider -->
                            <div class="flex items-center gap-2 group/vol pl-2">
                                <button
                                    @click="toggleMute"
                                    class="w-9 h-9 rounded-full bg-white/5 hover:bg-white/15 text-slate-300 hover:text-white flex items-center justify-center transition-all cursor-pointer"
                                >
                                    <VolumeX v-if="isMuted || volume === 0" class="w-4 h-4 text-red-400" />
                                    <Volume1 v-else-if="volume < 0.5" class="w-4 h-4" />
                                    <Volume2 v-else class="w-4 h-4" />
                                </button>
                                <input
                                    type="range"
                                    min="0"
                                    max="1"
                                    step="0.05"
                                    :value="isMuted ? 0 : volume"
                                    @input="setVolume(Number(($event.target as HTMLInputElement).value))"
                                    class="w-16 sm:w-20 h-1.5 rounded-lg appearance-none bg-white/20 accent-cyan-400 cursor-pointer"
                                />
                            </div>

                            <!-- Time Indicator -->
                            <div class="text-xs font-mono font-bold text-slate-300 pl-2">
                                {{ formatTime(currentTime) }} <span class="opacity-40">/</span> {{ formatTime(duration) }}
                            </div>
                        </div>

                        <!-- Right: Subtitles, Speed, Audio Enhancer, PiP, Fullscreen -->
                        <div class="flex items-center gap-2">
                            <!-- Subtitles Menu Button -->
                            <div class="relative">
                                <button
                                    @click="showSubtitleMenu = !showSubtitleMenu"
                                    class="w-9 h-9 rounded-full transition-all flex items-center justify-center cursor-pointer"
                                    :class="selectedSubtitleId !== 'off' ? 'bg-cyan-500 text-slate-950' : 'bg-white/10 text-white hover:bg-white/20'"
                                    title="Subtitles"
                                >
                                    <Subtitles class="w-4 h-4" />
                                </button>

                                <!-- Subtitle Dropdown Menu -->
                                <div
                                    v-if="showSubtitleMenu"
                                    class="absolute bottom-12 right-0 w-64 p-3 rounded-2xl bg-slate-900/95 border border-white/10 text-xs shadow-2xl backdrop-blur-2xl z-50 space-y-1"
                                >
                                    <div class="font-bold text-slate-400 px-2 py-1 uppercase tracking-wider text-[10px]">
                                        {{ isRTL ? 'الترجمات المتاحة' : 'Subtitles' }}
                                    </div>
                                    <button
                                        @click="selectSubtitle('off')"
                                        class="w-full px-3 py-2 rounded-xl text-left flex items-center justify-between transition-colors cursor-pointer"
                                        :class="selectedSubtitleId === 'off' ? 'bg-cyan-500/20 text-cyan-300 font-bold' : 'text-slate-300 hover:bg-white/5'"
                                    >
                                        <span>{{ isRTL ? 'إيقاف الترجمة' : 'Off' }}</span>
                                        <Check v-if="selectedSubtitleId === 'off'" class="w-3.5 h-3.5 text-cyan-400" />
                                    </button>
                                    <button
                                        v-for="sub in availableSubtitles"
                                        :key="sub.id"
                                        @click="selectSubtitle(sub.id)"
                                        class="w-full px-3 py-2 rounded-xl text-left flex items-center justify-between transition-colors cursor-pointer"
                                        :class="selectedSubtitleId === sub.id ? 'bg-cyan-500/20 text-cyan-300 font-bold' : 'text-slate-300 hover:bg-white/5'"
                                    >
                                        <div class="flex items-center gap-2 truncate">
                                            <span>{{ sub.language_name || sub.language.toUpperCase() }}</span>
                                            <span v-if="sub.is_embedded" class="text-[9px] px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300">MKV Track</span>
                                        </div>
                                        <Check v-if="selectedSubtitleId === sub.id" class="w-3.5 h-3.5 text-cyan-400 shrink-0" />
                                    </button>
                                </div>
                            </div>

                            <!-- Speed Menu -->
                            <div class="relative">
                                <button
                                    @click="showSpeedMenu = !showSpeedMenu"
                                    class="h-9 px-2.5 rounded-full bg-white/10 hover:bg-white/20 text-white font-mono text-xs font-bold flex items-center justify-center transition-all cursor-pointer"
                                    title="Playback Speed"
                                >
                                    {{ playbackRate }}x
                                </button>
                                <div
                                    v-if="showSpeedMenu"
                                    class="absolute bottom-12 right-0 w-28 p-2 rounded-2xl bg-slate-900/95 border border-white/10 text-xs shadow-2xl backdrop-blur-2xl z-50 space-y-1"
                                >
                                    <button
                                        v-for="rate in [0.5, 0.75, 1, 1.25, 1.5, 2]"
                                        :key="rate"
                                        @click="setSpeed(rate)"
                                        class="w-full px-3 py-1.5 rounded-xl text-left flex items-center justify-between transition-colors cursor-pointer"
                                        :class="playbackRate === rate ? 'bg-cyan-500/20 text-cyan-300 font-bold' : 'text-slate-300 hover:bg-white/5'"
                                    >
                                        <span>{{ rate }}x</span>
                                        <Check v-if="playbackRate === rate" class="w-3 h-3 text-cyan-400" />
                                    </button>
                                </div>
                            </div>

                            <!-- Picture-in-Picture -->
                            <button
                                @click="togglePiP"
                                class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all cursor-pointer"
                                title="Picture-in-Picture"
                            >
                                <PictureInPicture class="w-4 h-4" />
                            </button>

                            <!-- Fullscreen Toggle -->
                            <button
                                @click="toggleFullscreen"
                                class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all cursor-pointer"
                                title="Fullscreen"
                            >
                                <Minimize v-if="isFullscreen" class="w-4 h-4" />
                                <Maximize v-else class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </template>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
