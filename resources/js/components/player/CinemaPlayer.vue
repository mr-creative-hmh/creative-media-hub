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

// Initialize duration from original file metadata immediately
const initialDuration = Number(props.item?.duration_seconds) || (Number(props.item?.runtime_minutes) ? Number(props.item.runtime_minutes) * 60 : 0);
const duration = ref(initialDuration > 0 ? initialDuration : 0);
const isDurationLocked = ref(initialDuration > 0);

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
const subtitleFontSize = ref<'sm' | 'md' | 'lg' | 'xl'>('md');
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
let bufferTrackInterval: any = null;

const isEpisode = computed(() => {
    return props.item?.type === 'episode' 
        || props.item?.watchable_type === 'episode' 
        || !!props.item?.episode_number 
        || !!props.item?.season_id;
});

// Auto-detect if media requires Ultra-Fast Server Remuxing (MKV containers, DTS, Dolby Digital Plus, AC3, EAC3, HEVC, AVI, XviD)
const checkNeedsRemux = (item: any) => {
    if (!item) return false;
    const path = (item.file_path || '').toLowerCase();
    const audio = (item.audio_codec || '').toLowerCase();
    const video = (item.video_codec || '').toLowerCase();
    const isMkvOrAvi = path.endsWith('.mkv') || path.endsWith('.avi') || path.endsWith('.ts') || path.endsWith('.wmv');
    const isSurroundAudio = audio.includes('dts') || audio.includes('dolby') || audio.includes('ac3') || audio.includes('eac3') || audio.includes('truehd') || audio.includes('surround');
    const isNonH264Video = video.includes('hevc') || video.includes('h.265') || video.includes('265') || video.includes('mpeg') || video.includes('xvid') || video.includes('divx');
    return isMkvOrAvi || isSurroundAudio || isNonH264Video;
};

const isRemuxStream = ref(checkNeedsRemux(props.item));
const remuxStartOffset = ref(props.initialProgress ? Math.floor(props.initialProgress) : 0);

// Stable Stream URL: Direct Stream vs Server-Side Remux
const streamUrl = computed(() => {
    if (isRemuxStream.value) {
        const startParam = remuxStartOffset.value > 0 ? `?start=${remuxStartOffset.value}` : '';
        if (isEpisode.value) {
            return `/stream/remux/episode/${props.item.id}${startParam}`;
        }
        return `/stream/remux/movie/${props.item.id}${startParam}`;
    }

    if (isEpisode.value) {
        return `/stream/episode/${props.item.id}`;
    }
    return `/stream/movie/${props.item.id}`;
});

const toggleRemuxStream = () => {
    isRemuxStream.value = !isRemuxStream.value;
    if (isRemuxStream.value) {
        remuxStartOffset.value = Math.floor(currentTime.value);
    } else {
        remuxStartOffset.value = 0;
    }
    showToast(isRemuxStream.value 
        ? (isRTL.value ? 'تم تفعيل البث المباشر المحسن (Server Remux)' : 'Enabled Ultra-Fast Server Remux')
        : (isRTL.value ? 'تم التبديل للبث المباشر الأصلي' : 'Switched to Native Direct Stream')
    );
    setTimeout(() => {
        if (videoRef.value) {
            videoRef.value.load();
            videoRef.value.play().catch(() => {});
        }
    }, 100);
};

const handleVideoError = () => {
    if (!isRemuxStream.value) {
        showToast(isRTL.value ? 'جاري التحويل للبث المحسن (Remux)...' : 'Switching to Ultra-Fast Server Remux...');
        isRemuxStream.value = true;
        remuxStartOffset.value = Math.floor(currentTime.value);
        setTimeout(() => {
            if (videoRef.value) {
                videoRef.value.load();
                videoRef.value.play().catch(() => {});
            }
        }, 100);
    } else {
        showToast(isRTL.value ? 'حدث خطأ في تشغيل البث.' : 'Playback error occurred.');
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
    return props.item?.resolution || props.item?.video_resolution || '1080p FHD';
});

const displayCodec = computed(() => {
    return props.item?.video_codec || props.item?.codec || 'H.264 / HEVC';
});

const displayAudio = computed(() => {
    return props.item?.audio_codec || 'AAC 5.1';
});

const displayYear = computed(() => {
    return props.item?.release_year || props.item?.year || (props.item?.series?.release_year ?? '');
});

// Clean Header Title (Arabic in Arabic mode only, English in English mode only. No generic bracket titles like (Episode 5))
const playerHeaderTitle = computed(() => {
    if (isEpisode.value) {
        let cleanSeriesName = '';
        if (isRTL.value) {
            cleanSeriesName = props.item?.series?.title_ar || props.item?.title_ar || props.item?.series?.title || props.item?.title || 'مسلسل';
        } else {
            cleanSeriesName = props.item?.series?.title || props.item?.title || 'Series';
        }
        cleanSeriesName = cleanSeriesName.replace(/\s*-\s*S\d+E\d+\s*-\s*Episode\s*\d+/gi, '').replace(/\s*-\s*S\d+E\d+/gi, '').trim();

        const s = props.item?.season_number ?? (props.item?.season?.season_number ?? 1);
        const e = props.item?.episode_number ?? 1;

        const seasonLabel = isRTL.value ? `الموسم ${s}` : `Season ${s}`;
        const episodeLabel = isRTL.value ? `الحلقة ${e}` : `Episode ${e}`;

        let formatted = `${cleanSeriesName} - ${seasonLabel} - ${episodeLabel}`;
        
        const rawEpTitle = (isRTL.value && props.item?.title_ar) ? props.item.title_ar : (props.item?.title || '');
        const isGeneric = !rawEpTitle 
            || !!rawEpTitle.match(/^(?:Episode|حلقة|الحلقة)\s*\d+$/i) 
            || rawEpTitle === `Episode ${e}`
            || rawEpTitle === `الحلقة ${e}`
            || rawEpTitle.includes(`S${s}E${e}`) 
            || rawEpTitle.includes(`S0${s}E0${e}`);
            
        if (!isGeneric) {
            const cleanEpTitle = rawEpTitle.replace(/^(?:Episode|الحلقة)\s*\d+:\s*/i, '').replace(/^[^-]+-\s*S\d+E\d+\s*-\s*/i, '').trim();
            if (cleanEpTitle && cleanEpTitle !== cleanSeriesName && !cleanEpTitle.match(/^(?:Episode|الحلقة)\s*\d+$/i)) {
                formatted += ` (${cleanEpTitle})`;
            }
        }
        return formatted;
    }

    return (isRTL.value && props.item?.title_ar) ? props.item.title_ar : (props.item?.title || 'Media');
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
                    text: currentTextLines.join('<br>')
                });
                currentStart = -1;
                currentEnd = -1;
                currentTextLines = [];
            }
            continue;
        }

        const timeMatch = line.match(timeRegex);
        if (timeMatch) {
            if (currentStart >= 0 && currentTextLines.length > 0) {
                cues.push({
                    start: currentStart,
                    end: currentEnd,
                    text: currentTextLines.join('<br>')
                });
                currentTextLines = [];
            }
            currentStart = parseTimestampToSeconds(timeMatch[1]);
            currentEnd = parseTimestampToSeconds(timeMatch[2]);
        } else if (currentStart >= 0 && !line.match(/^\d+$/)) {
            currentTextLines.push(line);
        }
    }

    if (currentStart >= 0 && currentTextLines.length > 0) {
        cues.push({
            start: currentStart,
            end: currentEnd,
            text: currentTextLines.join('<br>')
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
        const content = await res.text();
        parsedCues.value = parseWebVTTContent(content);
        updateActiveCue(currentTime.value);
    } catch (e) {
        console.error('Failed to load subtitle track:', e);
    } finally {
        isFetchingSubtitle.value = false;
    }
};

const updateActiveCue = (timeSec: number) => {
    if (selectedSubtitleId.value === 'off' || !parsedCues.value.length) {
        activeCueText.value = '';
        return;
    }

    const adjustedTime = timeSec + subtitleDelay.value;
    const active = parsedCues.value.find(c => adjustedTime >= c.start && adjustedTime <= c.end);
    activeCueText.value = active ? active.text : '';
};

// YouTube-Style Continuous Buffer Calculation (during play AND pause)
const updateBufferProgress = () => {
    if (!videoRef.value || !duration.value || duration.value <= 0 || !isFinite(duration.value)) return;
    const buf = videoRef.value.buffered;
    if (buf && buf.length > 0) {
        let maxBufferedEnd = 0;
        for (let i = 0; i < buf.length; i++) {
            const end = buf.end(i);
            if (end > maxBufferedEnd) {
                maxBufferedEnd = end;
            }
        }
        const effectiveEnd = (isRemuxStream.value && remuxStartOffset.value > 0)
            ? remuxStartOffset.value + maxBufferedEnd
            : maxBufferedEnd;
        bufferedPercent.value = Math.min(100, Math.max(0, (effectiveEnd / duration.value) * 100));
    }
};

// Fetch Duration & Subtitles
const fetchMediaDuration = async () => {
    try {
        const type = isEpisode.value ? 'episode' : 'movie';
        const res = await fetch(`/api/media/duration?type=${type}&id=${props.item.id}`);
        const data = await res.json();
        if (data.duration_seconds && data.duration_seconds > 0) {
            duration.value = data.duration_seconds;
            isDurationLocked.value = true;
        }
    } catch (e) {}
};

const fetchSubtitles = async () => {
    try {
        const type = isEpisode.value ? 'episode' : 'movie';
        const res = await fetch(`/api/subtitles/for-media?type=${type}&id=${props.item.id}`);
        const data = await res.json();
        if (data.subtitles) {
            availableSubtitles.value = data.subtitles;
            // Auto-select Arabic if available, else first English subtitle
            const arSub = data.subtitles.find((s: any) => s.language === 'ara' || s.language === 'ar');
            const enSub = data.subtitles.find((s: any) => s.language === 'eng' || s.language === 'en');
            if (arSub) {
                selectedSubtitleId.value = arSub.id;
                loadSubtitleTrack(arSub.id);
            } else if (enSub) {
                selectedSubtitleId.value = enSub.id;
                loadSubtitleTrack(enSub.id);
            }
        }
    } catch (e) {}
};

// Web Audio API Pipeline (Voice Enhancer / Equalizer)
const initAudioPipeline = () => {
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
        voiceFilterNode.gain.value = vocalBoost.value ? 4.5 : 0;

        bassFilterNode = audioCtx.createBiquadFilter();
        bassFilterNode.type = 'lowshelf';
        bassFilterNode.frequency.value = 180;
        bassFilterNode.gain.value = bassBoost.value ? 5.0 : 0;

        trebleFilterNode = audioCtx.createBiquadFilter();
        trebleFilterNode.type = 'highshelf';
        trebleFilterNode.frequency.value = 6000;
        trebleFilterNode.gain.value = 0;

        compressorNode = audioCtx.createDynamicsCompressor();
        compressorNode.threshold.value = nightMode.value ? -24 : -12;
        compressorNode.knee.value = 10;
        compressorNode.ratio.value = nightMode.value ? 12 : 3;
        compressorNode.attack.value = 0.003;
        compressorNode.release.value = 0.25;

        gainNode = audioCtx.createGain();
        gainNode.gain.value = volume.value;

        sourceNode.connect(voiceFilterNode);
        voiceFilterNode.connect(bassFilterNode);
        bassFilterNode.connect(trebleFilterNode);
        trebleFilterNode.connect(compressorNode);
        compressorNode.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        isAudioPipelineInitialized = true;
    } catch (e) {
        console.warn('Web Audio pipeline initialization bypassed:', e);
    }
};

const updateAudioFilters = () => {
    if (!isAudioPipelineInitialized) return;
    if (voiceFilterNode) {
        voiceFilterNode.gain.value = vocalBoost.value ? 4.5 : 0;
    }
    if (bassFilterNode) {
        bassFilterNode.gain.value = bassBoost.value ? 5.0 : 0;
    }
    if (compressorNode) {
        compressorNode.threshold.value = nightMode.value ? -24 : -12;
        compressorNode.ratio.value = nightMode.value ? 12 : 3;
    }
};

const togglePlay = () => {
    if (!videoRef.value) return;
    if (videoRef.value.paused) {
        initAudioPipeline();
        videoRef.value.play().catch(() => {});
    } else {
        videoRef.value.pause();
    }
};

// Ultra-Fast Seeking in Remux & Native Stream
const executeSeek = (targetSecs: number) => {
    const clamped = Math.max(0, Math.min(duration.value || 3600, targetSecs));
    currentTime.value = clamped;
    updateActiveCue(clamped);

    if (isRemuxStream.value) {
        remuxStartOffset.value = Math.floor(clamped);
        setTimeout(() => {
            if (videoRef.value) {
                videoRef.value.load();
                videoRef.value.play().catch(() => {});
            }
        }, 50);
    } else if (videoRef.value) {
        videoRef.value.currentTime = clamped;
    }
};

const seekRelative = (seconds: number) => {
    const current = currentTime.value || (videoRef.value ? videoRef.value.currentTime : 0);
    const target = Math.max(0, Math.min(duration.value || 0, current + seconds));
    executeSeek(target);
    showControlsTemporarily();
};

const onScrubberInput = (e: Event) => {
    const val = parseFloat((e.target as HTMLInputElement).value);
    if (duration.value > 0) {
        const target = (val / 100) * duration.value;
        currentTime.value = target;
        updateActiveCue(target);
        if (!isRemuxStream.value && videoRef.value) {
            videoRef.value.currentTime = target;
        }
    }
};

const onScrubberChange = (e: Event) => {
    const val = parseFloat((e.target as HTMLInputElement).value);
    if (duration.value > 0) {
        const target = (val / 100) * duration.value;
        executeSeek(target);
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

const adjustSubtitleDelay = (delta: number) => {
    subtitleDelay.value = parseFloat((subtitleDelay.value + delta).toFixed(2));
    updateActiveCue(currentTime.value);
    showToast(isRTL.value ? `تأخير الترجمة: ${subtitleDelay.value} ثانية` : `Subtitle Sync: ${subtitleDelay.value > 0 ? '+' : ''}${subtitleDelay.value}s`);
};

const toggleVocalBoost = () => {
    vocalBoost.value = !vocalBoost.value;
    updateAudioFilters();
    showToast(vocalBoost.value ? (isRTL.value ? 'تم تفعيل تعزيز الحوار والوضوح' : 'Vocal Clarity Boost Enabled') : (isRTL.value ? 'تم تعطيل تعزيز الحوار' : 'Vocal Boost Disabled'));
};

const toggleBassBoost = () => {
    bassBoost.value = !bassBoost.value;
    updateAudioFilters();
    showToast(bassBoost.value ? (isRTL.value ? 'تم تفعيل التضخيم السينمائي (Bass)' : 'Cinema Bass Boost Enabled') : (isRTL.value ? 'تم تعطيل التضخيم' : 'Bass Boost Disabled'));
};

const toggleNightMode = () => {
    nightMode.value = !nightMode.value;
    updateAudioFilters();
    showToast(nightMode.value ? (isRTL.value ? 'تم تفعيل الوضع الليلي (ضغط الصوت)' : 'Night Mode Active') : (isRTL.value ? 'تم تعطيل الوضع الليلي' : 'Night Mode Disabled'));
};

const showControlsTemporarily = () => {
    isControlsVisible.value = true;
    clearTimeout(controlsTimeout);
    controlsTimeout = setTimeout(() => {
        if (isPlaying.value && !showEqualizer.value && !showSubtitlesMenu.value && !showPlaybackSpeedMenu.value) {
            isControlsVisible.value = false;
        }
    }, 3200);
};

// Playback Lifecycle & Progress Handlers
const onLoadedMetadata = () => {
    if (!videoRef.value) return;
    const d = videoRef.value.duration;
    if (!isDurationLocked.value && !isRemuxStream.value && d && isFinite(d) && !isNaN(d) && d > 0) {
        duration.value = d;
    }

    const saved = props.initialProgress || 0;
    if (saved > 0 && saved < duration.value - 10) {
        executeSeek(saved);
    }

    videoRef.value.play().then(() => {
        isPlaying.value = true;
    }).catch(() => {});
};

const onTimeUpdate = () => {
    if (!videoRef.value) return;
    
    const rawCurrent = videoRef.value.currentTime;
    if (isRemuxStream.value && remuxStartOffset.value > 0) {
        currentTime.value = remuxStartOffset.value + rawCurrent;
    } else {
        currentTime.value = rawCurrent;
    }

    // In Remux mode, NEVER overwrite the locked original file duration with partial chunk durations
    if (!isDurationLocked.value && !isRemuxStream.value) {
        const d = videoRef.value.duration;
        if (d && isFinite(d) && !isNaN(d) && d > 0) {
            duration.value = d;
        }
    }

    updateBufferProgress();
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
    updateBufferProgress();
};

const onVideoWaiting = () => {
    isBuffering.value = true;
};

const onVideoPlaying = () => {
    isBuffering.value = false;
};

// Formatting Time (Handles Hours, Minutes, Seconds with zero NaN or Infinity bugs)
const formatTime = (seconds: number) => {
    if (!seconds || isNaN(seconds) || !isFinite(seconds) || seconds <= 0) return '00:00';
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);

    const m = mins < 10 ? `0${mins}` : `${mins}`;
    const s = secs < 10 ? `0${secs}` : `${secs}`;

    if (hrs > 0) {
        return `${hrs}:${m}:${s}`;
    }
    return `${mins}:${s}`;
};

const savePlaybackProgress = async () => {
    if (!currentTime.value || currentTime.value <= 5) return;
    const dur = Math.floor(duration.value);
    const current = Math.floor(currentTime.value);

    emit('update:progress', current);
    try {
        localStorage.setItem(`progress_${isEpisode.value ? 'episode' : 'movie'}_${props.item.id}`, String(current));
        if (dur > 0) {
            localStorage.setItem(`duration_${isEpisode.value ? 'episode' : 'movie'}_${props.item.id}`, String(dur));
        }

        await fetch('/api/watch-history/progress', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || ''
            },
            body: JSON.stringify({
                watchable_type: isEpisode.value ? 'episode' : 'media_item',
                watchable_id: props.item.id,
                media_item_id: isEpisode.value ? (props.item.series_id || props.item.series?.id) : props.item.id,
                progress_seconds: current,
                duration_seconds: dur,
                completed: dur > 0 && current >= dur - 30
            })
        });
    } catch (e) {}
};

const stopServerStreamingCache = () => {
    try {
        const payload = JSON.stringify({
            type: isEpisode.value ? 'episode' : 'movie',
            id: props.item?.id
        });
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/api/stream/stop', payload);
        } else {
            fetch('/api/stream/stop', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: true
            }).catch(() => {});
        }
    } catch (e) {}
};

// Keyboard Shortcuts
const onKeyDown = (e: KeyboardEvent) => {
    if (['input', 'textarea'].includes((e.target as HTMLElement).tagName.toLowerCase())) return;

    switch (e.key) {
        case ' ':
        case 'k':
            e.preventDefault();
            togglePlay();
            showControlsTemporarily();
            break;
        case 'ArrowLeft':
            e.preventDefault();
            seekRelative(-10);
            break;
        case 'ArrowRight':
            e.preventDefault();
            seekRelative(10);
            break;
        case 'ArrowUp':
            e.preventDefault();
            volume.value = Math.min(1, volume.value + 0.1);
            if (videoRef.value) videoRef.value.volume = volume.value;
            showControlsTemporarily();
            break;
        case 'ArrowDown':
            e.preventDefault();
            volume.value = Math.max(0, volume.value - 0.1);
            if (videoRef.value) videoRef.value.volume = volume.value;
            showControlsTemporarily();
            break;
        case 'f':
            e.preventDefault();
            toggleFullscreen();
            break;
        case 'm':
            e.preventDefault();
            toggleMute();
            showControlsTemporarily();
            break;
        case 'Escape':
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
    stopServerStreamingCache();
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

    // Track YouTube-Style Buffer Line continuously (even when paused)
    bufferTrackInterval = setInterval(() => {
        updateBufferProgress();
    }, 600);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeyDown);
    clearInterval(progressSaveInterval);
    clearInterval(bufferTrackInterval);
    clearTimeout(controlsTimeout);
    clearTimeout(toastTimeout);
    savePlaybackProgress();
    stopServerStreamingCache();

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
                                {{ playerHeaderTitle }}
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
                                {{ displayCodec }} · {{ displayAudio }}
                            </span>

                            <!-- Active Subtitle Indicator -->
                            <span v-if="selectedSubtitleId !== 'off'" class="px-2 py-0.5 rounded-md bg-purple-500/20 border border-purple-400/30 text-purple-300 font-bold flex items-center gap-1">
                                <MessageSquare class="w-3 h-3 text-purple-400" />
                                {{ availableSubtitles.find(s => s.id === selectedSubtitleId)?.language_name || 'Subtitles ON' }}
                            </span>

                            <!-- Stream Engine Badge & Toggle -->
                            <button
                                @click="toggleRemuxStream"
                                class="px-2.5 py-0.5 rounded-md font-bold text-[11px] hidden md:inline-flex items-center gap-1.5 transition-all cursor-pointer"
                                :class="isRemuxStream ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 hover:bg-cyan-500/30' : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 hover:bg-emerald-500/30'"
                                :title="isRemuxStream ? 'Ultra-Fast Remux active (Zero CPU video copy + AAC audio). Click to toggle Direct Stream.' : 'Direct Stream active. Click to toggle Server Remux.'"
                            >
                                <Activity class="w-3 h-3" :class="isRemuxStream ? 'text-cyan-400' : 'text-emerald-400'" />
                                <span>{{ isRemuxStream ? 'Ultra-Fast Remux (AAC)' : 'Direct Stream' }}</span>
                            </button>
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

        <!-- ========================================================= -->
        <!-- CORE VIDEO HTML5 ELEMENT & HARDWARE PIPELINE             -->
        <!-- ========================================================= -->
        <video
            ref="videoRef"
            :src="streamUrl"
            @timeupdate="onTimeUpdate"
            @progress="updateBufferProgress"
            @loadedmetadata="onLoadedMetadata"
            @play="onVideoPlay"
            @pause="onVideoPause"
            @waiting="onVideoWaiting"
            @playing="onVideoPlaying"
            @error="handleVideoError"
            @click="togglePlay"
            crossorigin="anonymous"
            playsinline
            class="w-full h-full object-contain cursor-pointer"
        ></video>

        <!-- Subtitle Overlay (Dynamic WebVTT Rendering with Crystal-Clear Arabic/English Typography) -->
        <div
            v-if="selectedSubtitleId !== 'off' && activeCueText"
            class="absolute inset-x-0 z-30 flex items-center justify-center px-4 sm:px-8 pointer-events-none transition-all duration-200 ease-out"
            :style="{ bottom: isControlsVisible ? '13%' : '5.5%' }"
        >
            <div
                class="subtitle-pill px-4 py-1.5 sm:px-5 sm:py-2 rounded-lg bg-black/75 text-white text-center font-bold tracking-wide shadow-2xl backdrop-blur-xs transition-all duration-100 max-w-4xl pointer-events-none border border-white/5"
                :class="{
                    'text-sm sm:text-base': subtitleFontSize === 'sm',
                    'text-base sm:text-lg': subtitleFontSize === 'md',
                    'text-lg sm:text-2xl': subtitleFontSize === 'lg',
                    'text-xl sm:text-3xl': subtitleFontSize === 'xl',
                }"
                style="font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Noto Sans Arabic', 'Cairo', sans-serif; text-shadow: 0 2px 4px #000, 0 0 2px #000, 1px 1px 2px #000; line-height: 1.4;"
                v-html="activeCueText"
            ></div>
        </div>

        <!-- Buffering Spinner -->
        <div
            v-if="isBuffering"
            class="absolute inset-0 flex items-center justify-center pointer-events-none z-30 bg-black/30 backdrop-blur-xs"
        >
            <div class="w-16 h-16 rounded-full border-4 border-cyan-500/20 border-t-cyan-400 animate-spin"></div>
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
                class="absolute bottom-0 inset-x-0 p-4 sm:p-6 bg-gradient-to-t from-black/95 via-black/85 to-transparent flex flex-col gap-3 z-40 transition-all border-t border-white/5 backdrop-blur-xl"
            >
                <!-- Timeline Scrubber & YouTube-Style Buffer Status -->
                <div class="flex items-center gap-3.5 w-full">
                    <!-- Current Position -->
                    <span class="text-xs font-mono font-bold text-cyan-300 min-w-[48px] text-right tracking-tight drop-shadow">
                        {{ formatTime(currentTime) }}
                    </span>

                    <!-- Interactive Seeker Bar -->
                    <div class="relative flex-1 group/track cursor-pointer flex items-center h-6">
                        <!-- Visual Track Container -->
                        <div class="absolute inset-x-0 h-2 group-hover/track:h-3 bg-white/20 rounded-full overflow-hidden transition-all pointer-events-none shadow-inner">
                            <!-- High-Contrast Light Gray YouTube-Style Buffered Bar -->
                            <div
                                class="absolute inset-y-0 left-0 bg-slate-200/50 dark:bg-white/40 rounded-full transition-all duration-300"
                                :style="{ width: `${Math.max(progressPercent, bufferedPercent)}%` }"
                            ></div>
                            <!-- Active Played Gradient Fill -->
                            <div
                                class="absolute inset-y-0 left-0 bg-gradient-to-r from-cyan-500 via-sky-400 to-blue-500 rounded-full shadow-[0_0_12px_rgba(6,182,212,0.8)] transition-all"
                                :style="{ width: `${progressPercent}%` }"
                            ></div>
                        </div>

                        <!-- Interactive Range Input Overlay -->
                        <input
                            type="range"
                            min="0"
                            max="100"
                            step="0.05"
                            :value="progressPercent"
                            @input="onScrubberInput"
                            @change="onScrubberChange"
                            class="relative z-10 w-full h-full opacity-0 cursor-pointer"
                        />

                        <!-- Glowing Thumb Playhead -->
                        <div
                            class="absolute w-3.5 h-3.5 bg-white rounded-full shadow-[0_0_10px_rgba(6,182,212,1)] border-2 border-cyan-400 pointer-events-none transition-transform duration-75 group-hover/track:scale-125"
                            :style="{ left: `calc(${progressPercent}% - 7px)` }"
                        ></div>
                    </div>

                    <!-- Total End Time (Locked to original file duration) -->
                    <span class="text-xs font-mono font-bold text-slate-300 min-w-[48px] tracking-tight drop-shadow">
                        {{ formatTime(duration) }}
                    </span>
                </div>

                <!-- Main Controls Row -->
                <div class="flex items-center justify-between pt-1">
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
                            class="w-9 h-9 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 flex items-center justify-center transition-all cursor-pointer active:scale-90"
                            title="Rewind 10s (Left Arrow)"
                        >
                            <RotateCcw class="w-4 h-4" />
                        </button>

                        <button
                            @click="seekRelative(10)"
                            class="w-9 h-9 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 flex items-center justify-center transition-all cursor-pointer active:scale-90"
                            title="Forward 10s (Right Arrow)"
                        >
                            <RotateCw class="w-4 h-4" />
                        </button>

                        <!-- Glowing Custom Volume Slider -->
                        <div class="flex items-center gap-2 group/vol ml-2">
                            <button
                                @click="toggleMute"
                                class="w-9 h-9 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 flex items-center justify-center transition-all cursor-pointer"
                                :title="isMuted ? 'Unmute (M)' : 'Mute (M)'"
                            >
                                <VolumeX v-if="isMuted || volume === 0" class="w-4 h-4 text-rose-400" />
                                <Volume1 v-else-if="volume < 0.5" class="w-4 h-4 text-cyan-300" />
                                <Volume2 v-else class="w-4 h-4 text-cyan-400" />
                            </button>

                            <!-- Volume Gradient Bar -->
                            <div class="relative w-16 sm:w-24 flex items-center cursor-pointer h-6">
                                <div class="absolute inset-x-0 h-1.5 group-hover/vol:h-2 bg-white/20 rounded-full overflow-hidden pointer-events-none transition-all shadow-inner">
                                    <div
                                        class="h-full bg-gradient-to-r from-cyan-400 to-blue-500 rounded-full transition-all shadow-[0_0_8px_rgba(6,182,212,0.5)]"
                                        :style="{ width: `${(isMuted ? 0 : volume) * 100}%` }"
                                    ></div>
                                </div>
                                <input
                                    type="range"
                                    min="0"
                                    max="1"
                                    step="0.02"
                                    :value="isMuted ? 0 : volume"
                                    @input="onVolumeInput"
                                    class="relative z-10 w-full h-full opacity-0 cursor-pointer"
                                />
                            </div>
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
                                        :class="playbackRate === rate ? 'bg-cyan-500 text-slate-950' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
                                    >
                                        <span>{{ rate }}x</span>
                                        <Check v-if="playbackRate === rate" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </transition>
                        </div>

                        <!-- Subtitles Menu Button -->
                        <div class="relative">
                            <button
                                @click="showSubtitlesMenu = !showSubtitlesMenu; showEqualizer = false; showPlaybackSpeedMenu = false;"
                                class="w-9 h-9 rounded-xl flex items-center justify-center transition-all cursor-pointer"
                                :class="selectedSubtitleId !== 'off' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/40' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
                                title="Subtitles & Audio"
                            >
                                <MessageSquare class="w-4 h-4" />
                            </button>

                            <!-- Subtitles Dropdown & Settings Drawer -->
                            <transition name="scale">
                                <div
                                    v-if="showSubtitlesMenu"
                                    class="absolute bottom-12 right-0 w-72 bg-slate-950/95 border border-white/10 rounded-2xl p-3 shadow-2xl backdrop-blur-xl z-50 flex flex-col gap-3 max-h-96 overflow-y-auto"
                                >
                                    <div class="flex items-center justify-between border-b border-white/10 pb-2">
                                        <span class="text-xs font-bold text-white flex items-center gap-1.5">
                                            <MessageSquare class="w-3.5 h-3.5 text-purple-400" />
                                            {{ isRTL ? 'مسارات الترجمة' : 'Subtitle Tracks' }}
                                        </span>
                                        <span class="text-[10px] text-slate-400">{{ availableSubtitles.length }} available</span>
                                    </div>

                                    <!-- Tracks List -->
                                    <div class="flex flex-col gap-1 max-h-40 overflow-y-auto">
                                        <button
                                            @click="selectSubtitle('off')"
                                            class="px-3 py-2 rounded-xl text-xs font-semibold flex items-center justify-between transition-all cursor-pointer"
                                            :class="selectedSubtitleId === 'off' ? 'bg-purple-500 text-white' : 'text-slate-300 hover:bg-white/10'"
                                        >
                                            <span>{{ isRTL ? 'إيقاف الترجمة' : 'Off' }}</span>
                                            <Check v-if="selectedSubtitleId === 'off'" class="w-3.5 h-3.5" />
                                        </button>

                                        <button
                                            v-for="sub in availableSubtitles"
                                            :key="sub.id"
                                            @click="selectSubtitle(sub.id)"
                                            class="px-3 py-2 rounded-xl text-xs font-semibold flex items-center justify-between transition-all cursor-pointer"
                                            :class="selectedSubtitleId === sub.id ? 'bg-purple-500 text-white' : 'text-slate-300 hover:bg-white/10'"
                                        >
                                            <div class="flex items-center gap-1.5 truncate">
                                                <span class="uppercase text-[10px] px-1 py-0.5 rounded bg-black/40">{{ sub.language || 'CC' }}</span>
                                                <span class="truncate">{{ sub.language_name || 'Subtitle' }}</span>
                                            </div>
                                            <Check v-if="selectedSubtitleId === sub.id" class="w-3.5 h-3.5 shrink-0" />
                                        </button>
                                    </div>

                                    <!-- Subtitle Size & Sync Options -->
                                    <div v-if="selectedSubtitleId !== 'off'" class="border-t border-white/10 pt-2.5 flex flex-col gap-2">
                                        <!-- Font Size -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-[11px] text-slate-400">{{ isRTL ? 'حجم الخط' : 'Font Size' }}</span>
                                            <div class="flex items-center gap-1 bg-white/5 p-0.5 rounded-lg border border-white/10">
                                                <button
                                                    v-for="size in (['sm', 'md', 'lg', 'xl'] as const)"
                                                    :key="size"
                                                    @click="subtitleFontSize = size"
                                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase transition-all cursor-pointer"
                                                    :class="subtitleFontSize === size ? 'bg-purple-500 text-white' : 'text-slate-400 hover:text-white'"
                                                >
                                                    {{ size }}
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Subtitle Delay Sync -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-[11px] text-slate-400">{{ isRTL ? 'تزامن الوقت' : 'Timing Sync' }}</span>
                                            <div class="flex items-center gap-1.5">
                                                <button
                                                    @click="adjustSubtitleDelay(-0.5)"
                                                    class="w-6 h-6 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-xs font-bold cursor-pointer"
                                                    title="-0.5s"
                                                >
                                                    -
                                                </button>
                                                <span class="text-[11px] font-mono font-bold text-slate-300 w-12 text-center">
                                                    {{ subtitleDelay > 0 ? `+${subtitleDelay}` : subtitleDelay }}s
                                                </span>
                                                <button
                                                    @click="adjustSubtitleDelay(0.5)"
                                                    class="w-6 h-6 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-xs font-bold cursor-pointer"
                                                    title="+0.5s"
                                                >
                                                    +
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </transition>
                        </div>

                        <!-- Audio Enhancer & Equalizer Modal -->
                        <div class="relative">
                            <button
                                @click="showEqualizer = !showEqualizer; showSubtitlesMenu = false; showPlaybackSpeedMenu = false;"
                                class="w-9 h-9 rounded-xl flex items-center justify-center transition-all cursor-pointer"
                                :class="vocalBoost || bassBoost || nightMode ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
                                title="Cinema Audio Enhancer"
                            >
                                <Sliders class="w-4 h-4" />
                            </button>

                            <!-- Equalizer Dropdown -->
                            <transition name="scale">
                                <div
                                    v-if="showEqualizer"
                                    class="absolute bottom-12 right-0 w-72 bg-slate-950/95 border border-white/10 rounded-2xl p-3.5 shadow-2xl backdrop-blur-xl z-50 flex flex-col gap-3"
                                >
                                    <div class="flex items-center justify-between border-b border-white/10 pb-2">
                                        <span class="text-xs font-bold text-white flex items-center gap-1.5">
                                            <Sparkles class="w-3.5 h-3.5 text-cyan-400" />
                                            {{ isRTL ? 'معالج الصوت السينمائي' : 'Cinema Audio DSP' }}
                                        </span>
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <!-- Vocal Clarity Boost -->
                                        <button
                                            @click="toggleVocalBoost"
                                            class="p-2.5 rounded-xl border flex items-center justify-between transition-all cursor-pointer text-left"
                                            :class="vocalBoost ? 'bg-cyan-500/20 border-cyan-500/50 text-white' : 'bg-white/5 border-white/10 text-slate-400 hover:text-slate-200'"
                                        >
                                            <div>
                                                <div class="text-xs font-bold text-cyan-300">{{ isRTL ? 'تعزيز الحوار (Vocal Boost)' : 'Vocal Clarity Boost' }}</div>
                                                <div class="text-[10px] text-slate-400">{{ isRTL ? 'توضيح أصوات الممثلين وعزل الضوضاء' : 'Enhance dialogue clarity over background music' }}</div>
                                            </div>
                                            <div class="w-4 h-4 rounded-full border flex items-center justify-center" :class="vocalBoost ? 'border-cyan-400 bg-cyan-400 text-slate-950' : 'border-slate-600'">
                                                <Check v-if="vocalBoost" class="w-3 h-3" />
                                            </div>
                                        </button>

                                        <!-- Cinema Bass Boost -->
                                        <button
                                            @click="toggleBassBoost"
                                            class="p-2.5 rounded-xl border flex items-center justify-between transition-all cursor-pointer text-left"
                                            :class="bassBoost ? 'bg-cyan-500/20 border-cyan-500/50 text-white' : 'bg-white/5 border-white/10 text-slate-400 hover:text-slate-200'"
                                        >
                                            <div>
                                                <div class="text-xs font-bold text-cyan-300">{{ isRTL ? 'تضخيم الباس (Bass Boost)' : 'Cinema Bass Boost' }}</div>
                                                <div class="text-[10px] text-slate-400">{{ isRTL ? 'تعميق الترددات المنخفضة والمؤثرات' : 'Deepen sub-frequencies and explosions' }}</div>
                                            </div>
                                            <div class="w-4 h-4 rounded-full border flex items-center justify-center" :class="bassBoost ? 'border-cyan-400 bg-cyan-400 text-slate-950' : 'border-slate-600'">
                                                <Check v-if="bassBoost" class="w-3 h-3" />
                                            </div>
                                        </button>

                                        <!-- Night Mode / Dynamic Compression -->
                                        <button
                                            @click="toggleNightMode"
                                            class="p-2.5 rounded-xl border flex items-center justify-between transition-all cursor-pointer text-left"
                                            :class="nightMode ? 'bg-cyan-500/20 border-cyan-500/50 text-white' : 'bg-white/5 border-white/10 text-slate-400 hover:text-slate-200'"
                                        >
                                            <div>
                                                <div class="text-xs font-bold text-cyan-300">{{ isRTL ? 'الوضع الليلي (Night Mode)' : 'Night Mode (DRC)' }}</div>
                                                <div class="text-[10px] text-slate-400">{{ isRTL ? 'موازنة الصوت الهادئ والإنفجارات الصاخبة' : 'Compress dynamic range to avoid loud jumps' }}</div>
                                            </div>
                                            <div class="w-4 h-4 rounded-full border flex items-center justify-center" :class="nightMode ? 'border-cyan-400 bg-cyan-400 text-slate-950' : 'border-slate-600'">
                                                <Check v-if="nightMode" class="w-3 h-3" />
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </transition>
                        </div>

                        <!-- Fullscreen Button -->
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
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.scale-enter-active,
.scale-leave-active {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.scale-enter-from,
.scale-leave-to {
    opacity: 0;
    transform: scale(0.92);
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translateY(16px);
}
</style>
