<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import {
    Play,
    Pause,
    Volume2,
    VolumeX,
    Volume1,
    Maximize2,
    Minimize2,
    RotateCcw,
    RotateCw,
    Sliders,
    MessageSquare,
    Sparkles,
    Film,
    ArrowLeft,
    Check,
    Settings,
    X,
    FastForward,
    Tv,
    Clock,
    Activity,
    Layers,
    Type
} from 'lucide-vue-next';

interface SubtitleItem {
    id: number | string;
    language: string;
    language_name: string;
    format: string;
    is_embedded?: boolean;
    file_path?: string;
}

interface CueItem {
    start: number;
    end: number;
    text: string;
}

const props = defineProps<{
    item: any;
    initialProgress?: number;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'update:progress', val: number): void;
}>();

const { isRTL } = useI18n();

// Player DOM & State
const videoRef = ref<HTMLVideoElement | null>(null);
const playerContainerRef = ref<HTMLDivElement | null>(null);

const isPlaying = ref(false);
const isMuted = ref(false);
const volume = ref(1.0);
const currentTime = ref(0);
const duration = ref(0);
const bufferedPercent = ref(0);
const isFullscreen = ref(false);
const isControlsVisible = ref(true);
const isBuffering = ref(false);

// Active Modal/Drawer States
const showEqualizer = ref(false);
const showSubtitlesMenu = ref(false);
const showPlaybackSpeedMenu = ref(false);
const playbackRate = ref(1.0);

// Subtitles State & Cue Engine
const availableSubtitles = ref<SubtitleItem[]>([]);
const selectedSubtitleId = ref<number | string>('off');
const subtitleDelay = ref<number>(0); // in seconds
const subtitleFontSize = ref<'sm' | 'md' | 'lg' | 'xl'>('lg');
const parsedCues = ref<CueItem[]>([]);
const activeCueText = ref<string>('');
const isFetchingSubtitle = ref(false);

// Audio Enhancer & Equalizer State (Web Audio API)
const vocalBoost = ref(true);
const bassBoost = ref(false);
const nightMode = ref(false);

const toastNotice = ref<string | null>(null);
let toastTimeout: any = null;

const showToast = (msg: string) => {
    toastNotice.value = msg;
    clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toastNotice.value = null;
    }, 2800);
};

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

const isEpisode = computed(() => {
    return props.item.type === 'episode' 
        || props.item.watchable_type === 'episode' 
        || !!props.item.episode_number 
        || !!props.item.season_id;
});

const isRemuxStream = ref(false);

// Stream URL: Direct Stream vs Server-Side Remux (Zero-overhead on-the-fly AAC Transcode for DTS/MKV/AC3)
const streamUrl = computed(() => {
    const startOffset = Math.floor(currentTime.value);
    if (isRemuxStream.value) {
        if (isEpisode.value) {
            return `/stream/remux/episode/${props.item.id}?start=${startOffset}`;
        }
        return `/stream/remux/movie/${props.item.id}?start=${startOffset}`;
    }

    if (isEpisode.value) {
        return `/stream/episode/${props.item.id}`;
    }
    return `/stream/movie/${props.item.id}`;
});

const toggleRemuxStream = () => {
    isRemuxStream.value = !isRemuxStream.value;
    showToast(isRemuxStream.value 
        ? (isRTL ? 'تم تفعيل بث السيرفر مع إعادة ترميز الصوت AAC' : 'Enabled Server Remux with on-the-fly AAC audio')
        : (isRTL ? 'تم التبديل إلى البث المباشر للأجهزة' : 'Switched back to Native Direct Stream')
    );
};

const handleVideoError = () => {
    if (!isRemuxStream.value) {
        showToast(isRTL ? 'تنسيق غير مدعوم محلياً، يتم التبديل إلى بث السيرفر...' : 'Incompatible codec detected. Switching to Server Remux...');
        isRemuxStream.value = true;
    } else {
        showToast(isRTL ? 'تعذر تشغيل هذا الملف عبر المشغل.' : 'Playback error occurred.');
    }
};

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

// Media Meta Display (Resolution, Codec, Audio)
const displayResolution = computed(() => {
    return props.item.resolution || props.item.video_resolution || '1080p FHD';
});

const displayCodec = computed(() => {
    return props.item.video_codec || props.item.codec || 'H.264 / HEVC';
});

const displayAudio = computed(() => {
    return props.item.audio_codec || 'AAC 5.1';
});

const displayYear = computed(() => {
    return props.item.release_year || props.item.year || (props.item.series?.release_year ?? '');
});

const episodeFormatted = computed(() => {
    if (!isEpisode.value) return null;
    const s = props.item.season_number ?? (props.item.season?.season_number ?? 1);
    const e = props.item.episode_number ?? 1;
    const epTitle = props.item.title && !props.item.title.match(/^Episode \d+$/i) ? ` - ${props.item.title}` : '';
    return `S${String(s).padStart(2, '0')}E${String(e).padStart(2, '0')}${epTitle}`;
});

// =========================================================================
// WEBVTT / SRT CLIENT-SIDE CUE PARSER (100% Bulletproof Subtitle Overlay)
// =========================================================================
const parseTimestampToSeconds = (ts: string): number => {
    const parts = ts.trim().replace(',', '.').split(':');
    if (parts.length === 3) {
        return parseFloat(parts[0]) * 3600 + parseFloat(parts[1]) * 60 + parseFloat(parts[2]);
    } else if (parts.length === 2) {
        return parseFloat(parts[0]) * 60 + parseFloat(parts[1]);
    }
    return 0;
};

const parseWebVTTContent = (content: string): CueItem[] => {
    const lines = content.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
    const cues: CueItem[] = [];
    let currentStart = -1;
    let currentEnd = -1;
    let currentTextLines: string[] = [];

    const timeRegex = /((?:\d{1,2}:)?\d{2}:\d{2}[\.,]\d{2,3})\s*-->\s*((?:\d{1,2}:)?\d{2}:\d{2}[\.,]\d{2,3})/;

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        if (!line || line.startsWith('WEBVTT') || line.startsWith('NOTE')) {
            if (currentStart >= 0 && currentTextLines.length > 0) {
                cues.push({
                    start: currentStart,
                    end: currentEnd,
                    text: currentTextLines.join('\n')
                });
                currentStart = -1;
                currentEnd = -1;
                currentTextLines = [];
            }
            continue;
        }

        const match = line.match(timeRegex);
        if (match) {
            if (currentStart >= 0 && currentTextLines.length > 0) {
                cues.push({
                    start: currentStart,
                    end: currentEnd,
                    text: currentTextLines.join('\n')
                });
            }
            currentStart = parseTimestampToSeconds(match[1]);
            currentEnd = parseTimestampToSeconds(match[2]);
            currentTextLines = [];
        } else if (currentStart >= 0) {
            // Filter out numeric cue identifiers
            if (!line.match(/^\d+$/)) {
                // Strip HTML/WebVTT styling tags like <i>, <b>, <c.color>
                const cleanLine = line.replace(/<\/?[^>]+(>|$)/g, '');
                currentTextLines.push(cleanLine);
            }
        }
    }

    if (currentStart >= 0 && currentTextLines.length > 0) {
        cues.push({
            start: currentStart,
            end: currentEnd,
            text: currentTextLines.join('\n')
        });
    }

    return cues;
};

const loadSubtitleTrack = async (subId: number | string) => {
    if (subId === 'off') {
        parsedCues.value = [];
        activeCueText.value = '';
        return;
    }

    isFetchingSubtitle.value = true;
    try {
        const res = await fetch(`/stream/subtitles/${subId}`);
        if (res.ok) {
            const vttText = await res.text();
            parsedCues.value = parseWebVTTContent(vttText);
            updateActiveCue(currentTime.value);
        }
    } catch (e) {
        parsedCues.value = [];
    } finally {
        isFetchingSubtitle.value = false;
    }
};

const updateActiveCue = (currTime: number) => {
    if (parsedCues.value.length === 0 || selectedSubtitleId.value === 'off') {
        activeCueText.value = '';
        return;
    }

    const adjustedTime = currTime - subtitleDelay.value;
    const active = parsedCues.value.find(c => adjustedTime >= c.start && adjustedTime <= c.end);
    activeCueText.value = active ? active.text : '';
};

// Fetch Duration & Subtitles
const fetchMediaDuration = async () => {
    try {
        const type = isEpisode.value ? 'episode' : 'movie';
        const res = await fetch(`/api/media/duration?type=${type}&id=${props.item.id}`);
        if (res.ok) {
            const data = await res.json();
            if (data.duration_seconds && data.duration_seconds > 0) {
                if (!duration.value || duration.value <= 0) {
                    duration.value = data.duration_seconds;
                }
            }
        }
    } catch (e) {}
};

const fetchSubtitles = async () => {
    try {
        const type = isEpisode.value ? 'episode' : 'movie';
        const res = await fetch(`/api/subtitles/for-media?type=${type}&id=${props.item.id}`);
        if (res.ok) {
            const data = await res.json();
            const list = Array.isArray(data) ? data : (data.subtitles || []);
            if (Array.isArray(list) && list.length > 0) {
                availableSubtitles.value = list;
                
                // Auto-select Arabic or English subtitle if available
                const defaultSub = list.find((s: any) => s.is_default)
                    || list.find((s: any) => s.language === 'ar')
                    || list.find((s: any) => s.language === 'en')
                    || list[0];

                if (defaultSub) {
                    selectedSubtitleId.value = defaultSub.id;
                    loadSubtitleTrack(defaultSub.id);
                }
            }
        }
    } catch (e) {}
};

// Controls Visibility Management
const showControlsTemporarily = () => {
    isControlsVisible.value = true;
    clearTimeout(controlsTimeout);
    controlsTimeout = setTimeout(() => {
        if (isPlaying.value && !showEqualizer.value && !showSubtitlesMenu.value && !showPlaybackSpeedMenu.value) {
            isControlsVisible.value = false;
        }
    }, 4000);
};

// Playback Actions
const togglePlay = () => {
    if (!videoRef.value) return;
    if (videoRef.value.paused) {
        initAudioPipeline();
        videoRef.value.play().catch(() => {});
    } else {
        videoRef.value.pause();
    }
};

const seekRelative = (seconds: number) => {
    if (!videoRef.value) return;
    const target = Math.max(0, Math.min(duration.value || videoRef.value.duration || 0, videoRef.value.currentTime + seconds));
    videoRef.value.currentTime = target;
    currentTime.value = target;
    updateActiveCue(target);
    showControlsTemporarily();
};

const onScrubberInput = (e: Event) => {
    const val = parseFloat((e.target as HTMLInputElement).value);
    if (videoRef.value && duration.value > 0) {
        const target = (val / 100) * duration.value;
        videoRef.value.currentTime = target;
        currentTime.value = target;
        updateActiveCue(target);
    }
};

const toggleMute = () => {
    if (!videoRef.value) return;
    videoRef.value.muted = !videoRef.value.muted;
    isMuted.value = videoRef.value.muted;
};

const onVolumeInput = (e: Event) => {
    const val = parseFloat((e.target as HTMLInputElement).value);
    volume.value = val;
    if (videoRef.value) {
        videoRef.value.volume = val;
        videoRef.value.muted = val === 0;
        isMuted.value = val === 0;
    }
    if (gainNode) {
        gainNode.gain.value = val;
    }
};

const setPlaybackRate = (rate: number) => {
    playbackRate.value = rate;
    if (videoRef.value) {
        videoRef.value.playbackRate = rate;
    }
    showPlaybackSpeedMenu.value = false;
    showToast(isRTL.value ? `السرعة: ${rate}x` : `Playback Speed: ${rate}x`);
};

const toggleFullscreen = () => {
    if (!playerContainerRef.value) return;
    if (!document.fullscreenElement) {
        playerContainerRef.value.requestFullscreen().catch(() => {});
        isFullscreen.value = true;
    } else {
        document.exitFullscreen().catch(() => {});
        isFullscreen.value = false;
    }
};

const selectSubtitle = (subId: number | string) => {
    selectedSubtitleId.value = subId;
    loadSubtitleTrack(subId);
    showSubtitlesMenu.value = false;
    const found = availableSubtitles.value.find(s => s.id === subId);
    showToast(subId === 'off' ? (isRTL.value ? 'الترجمة: معطلة' : 'Subtitles: Off') : (isRTL.value ? `تم اختيار: ${found?.language_name}` : `Selected: ${found?.language_name}`));
};

// Audio Engine Setup (Web Audio API)
const initAudioPipeline = () => {
    if (isAudioPipelineInitialized || !videoRef.value) return;
    try {
        const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioContextClass) return;

        audioCtx = new AudioContextClass();
        sourceNode = audioCtx.createMediaElementSource(videoRef.value);

        // 1. Voice Clarity Filter (Peaking at 2.5kHz - Human Dialogue)
        voiceFilterNode = audioCtx.createBiquadFilter();
        voiceFilterNode.type = 'peaking';
        voiceFilterNode.frequency.value = 2500;
        voiceFilterNode.Q.value = 1.0;
        voiceFilterNode.gain.value = vocalBoost.value ? 5.5 : 0;

        // 2. Bass Filter (Low shelf at 120Hz)
        bassFilterNode = audioCtx.createBiquadFilter();
        bassFilterNode.type = 'lowshelf';
        bassFilterNode.frequency.value = 120;
        bassFilterNode.gain.value = bassBoost.value ? 4.0 : 0;

        // 3. Treble Polish (High shelf at 8kHz)
        trebleFilterNode = audioCtx.createBiquadFilter();
        trebleFilterNode.type = 'highshelf';
        trebleFilterNode.frequency.value = 8000;
        trebleFilterNode.gain.value = 2.0;

        // 4. Dynamics Compressor (Cinema Night Mode / Volume Leveling)
        compressorNode = audioCtx.createDynamicsCompressor();
        compressorNode.threshold.value = nightMode.value ? -24 : -12;
        compressorNode.knee.value = 15;
        compressorNode.ratio.value = nightMode.value ? 8 : 3;
        compressorNode.attack.value = 0.003;
        compressorNode.release.value = 0.25;

        // 5. Master Gain
        gainNode = audioCtx.createGain();
        gainNode.gain.value = volume.value;

        // Chain Nodes: Source -> Bass -> Voice -> Treble -> Compressor -> Gain -> Destination
        sourceNode.connect(bassFilterNode);
        bassFilterNode.connect(voiceFilterNode);
        voiceFilterNode.connect(trebleFilterNode);
        trebleFilterNode.connect(compressorNode);
        compressorNode.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        isAudioPipelineInitialized = true;
    } catch (e) {}
};

// Equalizer Toggles
const toggleVocalBoost = () => {
    vocalBoost.value = !vocalBoost.value;
    if (voiceFilterNode) {
        voiceFilterNode.gain.value = vocalBoost.value ? 5.5 : 0;
    }
    showToast(vocalBoost.value ? (isRTL.value ? 'تعزيز الحوار الصوتي: مفعل' : 'Vocal Clarity Boost: Enabled') : (isRTL.value ? 'تعزيز الحوار: معطل' : 'Vocal Boost: Disabled'));
};

const toggleBassBoost = () => {
    bassBoost.value = !bassBoost.value;
    if (bassFilterNode) {
        bassFilterNode.gain.value = bassBoost.value ? 4.5 : 0;
    }
    showToast(bassBoost.value ? (isRTL.value ? 'مضخم الصوت الجهوري (Bass): مفعل' : 'Bass Boost: Enabled') : (isRTL.value ? 'مضخم الصوت: معطل' : 'Bass Boost: Disabled'));
};

const toggleNightMode = () => {
    nightMode.value = !nightMode.value;
    if (compressorNode) {
        compressorNode.threshold.value = nightMode.value ? -26 : -12;
        compressorNode.ratio.value = nightMode.value ? 8 : 3;
    }
    showToast(nightMode.value ? (isRTL.value ? 'الوضع الليلي المتوازن: مفعل' : 'Night Cinema Compression: Enabled') : (isRTL.value ? 'الوضع الليلي: معطل' : 'Night Mode: Disabled'));
};

// Video Event Handlers
const onLoadedMetadata = () => {
    if (!videoRef.value) return;
    if (videoRef.value.duration && !isNaN(videoRef.value.duration) && videoRef.value.duration > 0) {
        duration.value = videoRef.value.duration;
    }

    // Resume saved progress
    const saved = props.initialProgress || 0;
    if (saved > 0 && saved < duration.value - 10) {
        videoRef.value.currentTime = saved;
        currentTime.value = saved;
    }

    videoRef.value.play().then(() => {
        isPlaying.value = true;
    }).catch(() => {});
};

const onTimeUpdate = () => {
    if (!videoRef.value) return;
    currentTime.value = videoRef.value.currentTime;
    if (videoRef.value.duration && !isNaN(videoRef.value.duration) && videoRef.value.duration > 0) {
        duration.value = videoRef.value.duration;
    }

    // Calculate Buffer Progress
    if (videoRef.value.buffered.length > 0 && duration.value > 0) {
        const bufferedEnd = videoRef.value.buffered.end(videoRef.value.buffered.length - 1);
        bufferedPercent.value = Math.min(100, (bufferedEnd / duration.value) * 100);
    }

    updateActiveCue(currentTime.value);
};

const onVideoPlay = () => {
    isPlaying.value = true;
    initAudioPipeline();
    if (audioCtx && audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
};

const onVideoPause = () => {
    isPlaying.value = false;
    savePlaybackProgress();
};

const onVideoWaiting = () => {
    isBuffering.value = true;
};

const onVideoPlaying = () => {
    isBuffering.value = false;
};

// Formatting Time
const formatTime = (seconds: number) => {
    if (!seconds || isNaN(seconds) || seconds < 0) return '00:00';
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);

    const m = mins < 10 ? `0${mins}` : `${mins}`;
    const s = secs < 10 ? `0${secs}` : `${secs}`;

    if (hrs > 0) {
        return `${hrs}:${m}:${s}`;
    }
    return `${m}:${s}`;
};

// Save Playback Progress to Server & LocalStorage
const savePlaybackProgress = async () => {
    const pos = Math.floor(currentTime.value);
    const dur = Math.floor(duration.value);

    if (dur <= 0 || pos <= 0) return;

    localStorage.setItem(`progress_${isEpisode.value ? 'episode' : 'movie'}_${props.item.id}`, String(pos));
    localStorage.setItem(`duration_${isEpisode.value ? 'episode' : 'movie'}_${props.item.id}`, String(dur));

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        await fetch('/api/playback/progress', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                watchable_id: props.item.id,
                watchable_type: isEpisode.value ? 'episode' : 'movie',
                progress_seconds: pos,
                position_seconds: pos,
                duration_seconds: dur,
                completed: pos >= dur * 0.9,
            }),
        });
    } catch (e) {}
};

// Keyboard Shortcuts
const onKeyDown = (e: KeyboardEvent) => {
    if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return;

    switch (e.key.toLowerCase()) {
        case ' ':
        case 'k':
            e.preventDefault();
            togglePlay();
            break;
        case 'arrowleft':
            e.preventDefault();
            seekRelative(-10);
            break;
        case 'arrowright':
            e.preventDefault();
            seekRelative(10);
            break;
        case 'arrowup':
            e.preventDefault();
            onVolumeInput({ target: { value: Math.min(1, volume.value + 0.1) } } as any);
            break;
        case 'arrowdown':
            e.preventDefault();
            onVolumeInput({ target: { value: Math.max(0, volume.value - 0.1) } } as any);
            break;
        case 'f':
            e.preventDefault();
            toggleFullscreen();
            break;
        case 'm':
            e.preventDefault();
            toggleMute();
            break;
        case 'c':
            e.preventDefault();
            showSubtitlesMenu.value = !showSubtitlesMenu.value;
            break;
        case 'escape':
            if (isFullscreen.value) {
                toggleFullscreen();
            } else {
                handleClose();
            }
            break;
    }
};

const handleClose = () => {
    savePlaybackProgress();
    emit('close');
};

onMounted(() => {
    window.addEventListener('keydown', onKeyDown);
    fetchSubtitles();
    fetchMediaDuration();

    if (!props.initialProgress) {
        const localSaved = Number(localStorage.getItem(`progress_${isEpisode.value ? 'episode' : 'movie'}_${props.item.id}`)) || 0;
        if (localSaved > 0) {
            currentTime.value = localSaved;
        }
    }

    progressSaveInterval = setInterval(() => {
        if (isPlaying.value) {
            savePlaybackProgress();
        }
    }, 15000);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeyDown);
    clearInterval(progressSaveInterval);
    clearTimeout(controlsTimeout);
    clearTimeout(toastTimeout);
    savePlaybackProgress();

    if (audioCtx) {
        audioCtx.close().catch(() => {});
    }
});
</script>

<template>
    <div
        ref="playerContainerRef"
        @mousemove="showControlsTemporarily"
        @click="showControlsTemporarily"
        class="fixed inset-0 z-50 bg-black flex items-center justify-center select-none overflow-hidden font-sans group"
        :class="{ 'cursor-none': !isControlsVisible && isPlaying }"
    >
        <!-- Toast Notification -->
        <transition name="fade">
            <div
                v-if="toastNotice"
                class="absolute top-24 left-1/2 -translate-x-1/2 z-50 px-5 py-2.5 rounded-2xl bg-black/90 border border-cyan-500/50 text-cyan-300 font-extrabold text-sm backdrop-blur-xl shadow-2xl flex items-center gap-2 pointer-events-none"
            >
                <Sparkles class="w-4 h-4 text-cyan-400" />
                <span>{{ toastNotice }}</span>
            </div>
        </transition>

        <!-- ========================================================= -->
        <!-- TOP STATUS LINE & MEDIA HEADER (Always Clear on Hover)    -->
        <!-- ========================================================= -->
        <transition name="fade">
            <div
                v-show="isControlsVisible || !isPlaying"
                class="absolute top-0 inset-x-0 p-4 sm:p-6 bg-gradient-to-b from-black/95 via-black/60 to-transparent flex items-center justify-between z-40 transition-opacity"
            >
                <!-- Left: Back Button + Title + Status Badges -->
                <div class="flex items-center gap-3 sm:gap-4">
                    <button
                        @click="handleClose"
                        class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center backdrop-blur-md transition-transform hover:scale-110 active:scale-95 cursor-pointer shadow-lg"
                        title="Back to Library"
                    >
                        <ArrowLeft class="w-5 h-5" :class="isRTL ? 'rotate-180' : ''" />
                    </button>

                    <div class="flex flex-col gap-1">
                        <!-- Primary Title & Episode Info -->
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h2 class="font-black text-sm sm:text-lg text-white tracking-wide truncate max-w-xs sm:max-w-xl">
                                {{ isRTL && item.title_ar ? item.title_ar : (item.series?.title ? `${item.series.title} - ${episodeFormatted}` : item.title) }}
                            </h2>

                            <!-- Year Badge -->
                            <span v-if="displayYear" class="px-2 py-0.5 rounded-md bg-white/10 text-slate-300 text-[11px] font-bold">
                                {{ displayYear }}
                            </span>
                        </div>

                        <!-- Technical Status Line (Resolution, Codec, Hardware Stream) -->
                        <div class="flex items-center gap-2 flex-wrap text-[11px] font-mono">
                            <!-- Quality Badge -->
                            <span class="px-2 py-0.5 rounded-md bg-cyan-500/20 border border-cyan-400/40 text-cyan-300 font-extrabold flex items-center gap-1">
                                <Film class="w-3 h-3 text-cyan-400" />
                                {{ displayResolution }}
                            </span>

                            <!-- Codec & Audio Badge -->
                            <span class="px-2 py-0.5 rounded-md bg-white/10 text-slate-300 font-semibold hidden sm:inline-flex items-center gap-1">
                                <Layers class="w-3 h-3 text-slate-400" />
                                {{ displayCodec }} • {{ displayAudio }}
                            </span>

                            <!-- Active Subtitle Indicator -->
                            <span v-if="selectedSubtitleId !== 'off'" class="px-2 py-0.5 rounded-md bg-purple-500/20 border border-purple-400/30 text-purple-300 font-bold flex items-center gap-1">
                                <MessageSquare class="w-3 h-3 text-purple-400" />
                                {{ availableSubtitles.find(s => s.id === selectedSubtitleId)?.language_name || 'Subtitles ON' }}
                            </span>

                            <!-- GPU Engine Badge -->
                            <span class="px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-400 font-bold hidden md:inline-flex items-center gap-1">
                                <Activity class="w-3 h-3 text-emerald-400" />
                                Direct Stream
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right: Close Button -->
                <div class="flex items-center gap-2">
                    <button
                        @click="handleClose"
                        class="w-10 h-10 rounded-full bg-white/10 hover:bg-red-500/80 text-white flex items-center justify-center backdrop-blur-md transition-all hover:scale-110 cursor-pointer shadow-lg"
                        title="Close Player"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>
            </div>
        </transition>

        <!-- Native Hardware Video Element -->
        <video
            ref="videoRef"
            :src="streamUrl"
            @timeupdate="onTimeUpdate"
            @loadedmetadata="onLoadedMetadata"
            @play="onVideoPlay"
            @pause="onVideoPause"
            @waiting="onVideoWaiting"
            @playing="onVideoPlaying"
            @click="togglePlay"
            class="w-full h-full object-contain bg-black"
            playsinline
            preload="auto"
        ></video>

        <!-- ========================================================= -->
        <!-- BULLETPROOF CUSTOM SUBTITLE OVERLAY (Crisp Arabic/English)-->
        <!-- ========================================================= -->
        <div
            v-if="selectedSubtitleId !== 'off' && activeCueText"
            class="absolute inset-x-0 z-30 flex items-center justify-center px-6 pointer-events-none transition-all duration-300 ease-out"
            :class="isControlsVisible ? 'bottom-24 sm:bottom-28' : 'bottom-8 sm:bottom-12'"
        >
            <div
                class="subtitle-pill px-4 py-1.5 sm:px-6 sm:py-2.5 rounded-xl bg-black/80 border border-white/10 text-white text-center font-bold tracking-wide shadow-2xl backdrop-blur-sm transition-all duration-150 max-w-3xl pointer-events-none"
                :class="{
                    'text-sm sm:text-base': subtitleFontSize === 'sm',
                    'text-base sm:text-xl': subtitleFontSize === 'md',
                    'text-lg sm:text-2xl leading-relaxed': subtitleFontSize === 'lg',
                    'text-xl sm:text-3xl leading-relaxed': subtitleFontSize === 'xl',
                }"
            >
                <p class="whitespace-pre-line select-none font-subtitle">{{ activeCueText }}</p>
            </div>
        </div>

        <!-- Buffering Spinner -->
        <div v-if="isBuffering" class="absolute inset-0 flex items-center justify-center pointer-events-none z-30">
            <div class="w-16 h-16 rounded-full border-4 border-cyan-500/20 border-t-cyan-500 animate-spin"></div>
        </div>

        <!-- Big Play Button Overlay when Paused -->
        <transition name="scale">
            <button
                v-if="!isPlaying && !isBuffering"
                @click="togglePlay"
                class="absolute w-20 h-20 rounded-full bg-cyan-500/90 text-slate-950 flex items-center justify-center shadow-2xl shadow-cyan-500/50 hover:scale-110 active:scale-95 transition-all z-20 cursor-pointer"
            >
                <Play class="w-8 h-8 fill-current ml-1" />
            </button>
        </transition>

        <!-- ========================================================= -->
        <!-- BOTTOM CINEMA CONTROLS BAR & SCRUBBER STATUS              -->
        <!-- ========================================================= -->
        <transition name="slide-up">
            <div
                v-show="isControlsVisible || !isPlaying"
                class="absolute bottom-0 inset-x-0 p-4 sm:p-6 bg-gradient-to-t from-black/95 via-black/75 to-transparent flex flex-col gap-3 z-40 transition-all"
            >
                <!-- Timeline Scrubber & Buffer Status -->
                <div class="flex items-center gap-3 w-full">
                    <span class="text-xs font-mono font-bold text-slate-200 min-w-[48px] text-right">
                        {{ formatTime(currentTime) }}
                    </span>

                    <div class="relative flex-1 group cursor-pointer flex items-center h-4">
                        <!-- Background Track -->
                        <div class="absolute inset-x-0 h-1.5 group-hover:h-2.5 bg-white/20 rounded-lg overflow-hidden transition-all pointer-events-none">
                            <!-- Buffered Line -->
                            <div
                                class="h-full bg-white/30 rounded-lg transition-all duration-200"
                                :style="{ width: `${bufferedPercent}%` }"
                            ></div>
                        </div>

                        <!-- Progress Range Input -->
                        <input
                            type="range"
                            min="0"
                            max="100"
                            step="0.1"
                            :value="progressPercent"
                            @input="onScrubberInput"
                            class="relative z-10 w-full h-1.5 group-hover:h-2.5 bg-transparent appearance-none cursor-pointer accent-cyan-400 transition-all"
                        />
                    </div>

                    <span class="text-xs font-mono font-bold text-slate-400 min-w-[48px]">
                        {{ formatTime(duration) }}
                    </span>
                </div>

                <!-- Main Controls Row -->
                <div class="flex items-center justify-between">
                    <!-- Left: Play/Pause, Seek, Volume -->
                    <div class="flex items-center gap-2 sm:gap-4">
                        <button
                            @click="togglePlay"
                            class="w-10 h-10 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 flex items-center justify-center transition-transform hover:scale-105 active:scale-95 cursor-pointer shadow-lg shadow-cyan-500/20"
                            :title="isPlaying ? 'Pause (Space)' : 'Play (Space)'"
                        >
                            <Play v-if="!isPlaying" class="w-5 h-5 fill-current ml-0.5" />
                            <Pause v-else class="w-5 h-5 fill-current" />
                        </button>

                        <button
                            @click="seekRelative(-10)"
                            class="w-9 h-9 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 flex items-center justify-center transition-all cursor-pointer"
                            title="Rewind 10s (Left Arrow)"
                        >
                            <RotateCcw class="w-4 h-4" />
                        </button>

                        <button
                            @click="seekRelative(10)"
                            class="w-9 h-9 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 flex items-center justify-center transition-all cursor-pointer"
                            title="Forward 10s (Right Arrow)"
                        >
                            <RotateCw class="w-4 h-4" />
                        </button>

                        <!-- Volume Slider -->
                        <div class="flex items-center gap-2 group ml-2">
                            <button
                                @click="toggleMute"
                                class="w-9 h-9 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 flex items-center justify-center transition-all cursor-pointer"
                                :title="isMuted ? 'Unmute (M)' : 'Mute (M)'"
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
                                @input="onVolumeInput"
                                class="w-16 sm:w-24 h-1.5 bg-white/20 rounded-lg appearance-none cursor-pointer accent-cyan-400"
                            />
                        </div>
                    </div>

                    <!-- Right: Equalizer, Subtitles, Speed, Fullscreen -->
                    <div class="flex items-center gap-2">
                        <!-- Playback Speed -->
                        <div class="relative">
                            <button
                                @click="showPlaybackSpeedMenu = !showPlaybackSpeedMenu; showSubtitlesMenu = false; showEqualizer = false;"
                                class="px-2.5 py-1.5 rounded-xl text-xs font-bold font-mono transition-all flex items-center gap-1 cursor-pointer"
                                :class="playbackRate !== 1.0 ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/40' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
                                title="Playback Speed"
                            >
                                <span>{{ playbackRate }}x</span>
                            </button>

                            <!-- Speed Dropdown -->
                            <transition name="scale">
                                <div
                                    v-if="showPlaybackSpeedMenu"
                                    class="absolute bottom-12 right-0 w-32 bg-slate-950/95 border border-white/10 rounded-2xl p-1.5 shadow-2xl backdrop-blur-xl z-50 flex flex-col gap-1"
                                >
                                    <button
                                        v-for="rate in [0.5, 0.75, 1.0, 1.25, 1.5, 2.0]"
                                        :key="rate"
                                        @click="setPlaybackRate(rate)"
                                        class="px-3 py-1.5 rounded-xl text-xs font-bold flex items-center justify-between transition-all cursor-pointer"
                                        :class="playbackRate === rate ? 'bg-cyan-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10'"
                                    >
                                        <span>{{ rate }}x</span>
                                        <Check v-if="playbackRate === rate" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </transition>
                        </div>

                        <!-- Audio Equalizer & Enhancer -->
                        <div class="relative">
                            <button
                                @click="showEqualizer = !showEqualizer; showSubtitlesMenu = false; showPlaybackSpeedMenu = false;"
                                class="w-9 h-9 rounded-xl transition-all flex items-center justify-center cursor-pointer"
                                :class="vocalBoost || bassBoost || nightMode ? 'bg-purple-500/20 text-purple-400 border border-purple-500/40' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
                                title="Cinema Audio Enhancer & Equalizer"
                            >
                                <Sliders class="w-4 h-4" />
                            </button>

                            <!-- Equalizer Popover -->
                            <transition name="scale">
                                <div
                                    v-if="showEqualizer"
                                    class="absolute bottom-12 right-0 w-64 bg-slate-950/95 border border-white/10 rounded-2xl p-4 shadow-2xl backdrop-blur-xl z-50 flex flex-col gap-3"
                                >
                                    <div class="flex items-center justify-between border-b border-white/10 pb-2">
                                        <span class="font-extrabold text-xs text-white flex items-center gap-1.5">
                                            <Sparkles class="w-3.5 h-3.5 text-purple-400" />
                                            {{ isRTL ? 'معالج الصوت السينمائي' : 'Studio Audio Enhancer' }}
                                        </span>
                                        <span class="text-[10px] font-mono text-purple-400 font-bold">DSP Active</span>
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <!-- Vocal Clarity Boost -->
                                        <button
                                            @click="toggleVocalBoost"
                                            class="w-full p-2.5 rounded-xl border flex items-center justify-between transition-all cursor-pointer text-left"
                                            :class="vocalBoost ? 'bg-cyan-500/20 border-cyan-400/50 text-cyan-300' : 'border-white/5 text-slate-400 hover:border-white/10'"
                                        >
                                            <div class="flex flex-col">
                                                <span class="text-xs font-bold text-white">{{ isRTL ? 'تعزيز نقاء الحوار' : 'Vocal Clarity Boost' }}</span>
                                                <span class="text-[10px] text-slate-400">{{ isRTL ? 'توضيح أصوات الممثلين والحديث' : 'Boost speech frequencies (2.5kHz)' }}</span>
                                            </div>
                                            <Check v-if="vocalBoost" class="w-4 h-4 text-cyan-400 shrink-0" />
                                        </button>

                                        <!-- Bass Boost -->
                                        <button
                                            @click="toggleBassBoost"
                                            class="w-full p-2.5 rounded-xl border flex items-center justify-between transition-all cursor-pointer text-left"
                                            :class="bassBoost ? 'bg-purple-500/20 border-purple-400/50 text-purple-300' : 'border-white/5 text-slate-400 hover:border-white/10'"
                                        >
                                            <div class="flex flex-col">
                                                <span class="text-xs font-bold text-white">{{ isRTL ? 'مضخم الصوت الجهوري (Bass)' : 'Cinema Bass Boost' }}</span>
                                                <span class="text-[10px] text-slate-400">{{ isRTL ? 'تعزيز الانفجارات والمؤثرات' : 'Low-frequency enhancement' }}</span>
                                            </div>
                                            <Check v-if="bassBoost" class="w-4 h-4 text-purple-400 shrink-0" />
                                        </button>

                                        <!-- Night Mode / Dynamic Leveling -->
                                        <button
                                            @click="toggleNightMode"
                                            class="w-full p-2.5 rounded-xl border flex items-center justify-between transition-all cursor-pointer text-left"
                                            :class="nightMode ? 'bg-amber-500/20 border-amber-400/50 text-amber-300' : 'border-white/5 text-slate-400 hover:border-white/10'"
                                        >
                                            <div class="flex flex-col">
                                                <span class="text-xs font-bold text-white">{{ isRTL ? 'الوضع الليلي المتوازن' : 'Night Mode (Anti-Loud)' }}</span>
                                                <span class="text-[10px] text-slate-400">{{ isRTL ? 'تقليل الفارق بين الانفجارات والحوار' : 'Dynamic compressor leveling' }}</span>
                                            </div>
                                            <Check v-if="nightMode" class="w-4 h-4 text-amber-400 shrink-0" />
                                        </button>
                                    </div>
                                </div>
                            </transition>
                        </div>

                        <!-- Subtitles Menu -->
                        <div class="relative">
                            <button
                                @click="showSubtitlesMenu = !showSubtitlesMenu; showEqualizer = false; showPlaybackSpeedMenu = false;"
                                class="w-9 h-9 rounded-xl transition-all flex items-center justify-center cursor-pointer"
                                :class="selectedSubtitleId !== 'off' ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/40' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
                                title="Subtitles Track (C)"
                            >
                                <MessageSquare class="w-4 h-4" />
                            </button>

                            <!-- Subtitles Dropdown -->
                            <transition name="scale">
                                <div
                                    v-if="showSubtitlesMenu"
                                    class="absolute bottom-12 w-72 bg-slate-950/95 border border-white/10 rounded-2xl p-3 shadow-2xl backdrop-blur-xl z-50 flex flex-col gap-2 max-h-80 overflow-y-auto"
                                    :class="isRTL ? 'left-0 text-right' : 'right-0 text-left'"
                                >
                                    <div class="flex items-center justify-between border-b border-white/10 pb-2 px-1">
                                        <span class="font-extrabold text-xs text-white">{{ isRTL ? 'مسارات الترجمة' : 'Subtitle Tracks' }}</span>
                                        <span class="text-[10px] text-slate-400 font-mono">{{ availableSubtitles.length }} tracks</span>
                                    </div>

                                    <!-- Subtitle Font Size Picker -->
                                    <div class="flex items-center justify-between px-2 py-1 bg-white/5 rounded-xl text-xs">
                                        <span class="text-slate-400 font-medium text-[11px] flex items-center gap-1">
                                            <Type class="w-3.5 h-3.5" />
                                            {{ isRTL ? 'حجم الخط' : 'Font Size' }}
                                        </span>
                                        <div class="flex items-center gap-1">
                                            <button
                                                v-for="s in (['sm', 'md', 'lg', 'xl'] as const)"
                                                :key="s"
                                                @click="subtitleFontSize = s"
                                                class="px-2 py-0.5 rounded-lg text-[10px] font-bold uppercase transition-all"
                                                :class="subtitleFontSize === s ? 'bg-cyan-500 text-slate-950' : 'text-slate-400 hover:text-white'"
                                            >
                                                {{ s }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Off Button -->
                                    <button
                                        @click="selectSubtitle('off')"
                                        class="px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-between transition-all cursor-pointer"
                                        :class="selectedSubtitleId === 'off' ? 'bg-white/20 text-white font-black' : 'text-slate-400 hover:bg-white/10 hover:text-white'"
                                    >
                                        <span>{{ isRTL ? 'إيقاف الترجمة' : 'Off / Disabled' }}</span>
                                        <Check v-if="selectedSubtitleId === 'off'" class="w-3.5 h-3.5 text-cyan-400" />
                                    </button>

                                    <!-- Subtitle List -->
                                    <button
                                        v-for="sub in availableSubtitles"
                                        :key="sub.id"
                                        @click="selectSubtitle(sub.id)"
                                        class="px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-between transition-all cursor-pointer text-left"
                                        :class="selectedSubtitleId === sub.id ? 'bg-cyan-500 text-slate-950 font-black' : 'text-slate-300 hover:bg-white/10'"
                                    >
                                        <div class="flex flex-col">
                                            <span class="truncate max-w-[200px]">{{ sub.language_name }}</span>
                                            <span class="text-[9px] opacity-70 uppercase font-mono">{{ sub.format }} {{ sub.is_embedded ? '• Embedded' : '• External' }}</span>
                                        </div>
                                        <Check v-if="selectedSubtitleId === sub.id" class="w-3.5 h-3.5 text-slate-950 shrink-0" />
                                    </button>
                                </div>
                            </transition>
                        </div>

                        <!-- Fullscreen -->
                        <button
                            @click="toggleFullscreen"
                            class="w-9 h-9 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 flex items-center justify-center transition-all cursor-pointer"
                            :title="isFullscreen ? 'Exit Fullscreen (F)' : 'Fullscreen (F)'"
                        >
                            <Minimize2 v-if="isFullscreen" class="w-4 h-4" />
                            <Maximize2 v-else class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Alexandria:wght@500;700;800&family=Cairo:wght@600;700;800&display=swap');

.font-subtitle {
    font-family: 'Cairo', 'Alexandria', 'Noto Sans Arabic', 'Segoe UI', system-ui, -apple-system, sans-serif;
    font-weight: 700;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.95), 0 0 10px rgba(0, 0, 0, 0.9);
    letter-spacing: 0.02em;
    line-height: 1.5;
}

.subtitle-pill {
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8), 0 2px 8px rgba(0, 0, 0, 0.6);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
}

.scale-enter-active,
.scale-leave-active {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.scale-enter-from,
.scale-leave-to {
    transform: scale(0.92);
    opacity: 0;
}
</style>
