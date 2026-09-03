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
    Type,
    SkipBack,
    SkipForward,
    Download,
    Search,
    Loader2
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
    playlist?: any[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'update:progress', val: number): void;
}>();

const { isRTL } = useI18n();

// Active Item for smooth in-player playlist navigation (Series episodes, Collections movies)
const activeItem = ref(props.item);

// Player DOM & State
const videoRef = ref<HTMLVideoElement | null>(null);
const playerContainerRef = ref<HTMLDivElement | null>(null);

const isPlaying = ref(false);
const isMuted = ref(false);
const volume = ref(1.0);

// Initialize initial progress from prop, item, or watchable metadata
const initialSec = Number(props.initialProgress) || Number(props.item?.progress_seconds) || Number(props.item?.initial_progress) || 0;
const currentTime = ref(initialSec > 0 ? initialSec : 0);

// Initialize duration from original file metadata immediately
const hasExactDuration = Number(props.item?.duration_seconds) > 0;
const initialDuration = hasExactDuration ? Number(props.item?.duration_seconds) : (Number(props.item?.runtime_minutes) ? Number(props.item.runtime_minutes) * 60 : 0);
const duration = ref(initialDuration > 0 ? initialDuration : 0);
const isDurationLocked = ref(hasExactDuration);

const bufferedPercent = ref(0);
const isFullscreen = ref(false);
const isControlsVisible = ref(true);
const isBuffering = ref(false);
const hasAppliedInitialSeek = ref(false);

// Active Modal/Drawer States
const showEqualizer = ref(false);
const showSubtitlesMenu = ref(false);
const showPlaybackSpeedMenu = ref(false);
const showSubtitleSearchModal = ref(false);
const playbackRate = ref(1.0);

// Playlist Management (Series & Collections)
const currentPlaylist = computed<any[]>(() => {
    if (props.playlist && Array.isArray(props.playlist) && props.playlist.length > 0) {
        return props.playlist;
    }
    if (activeItem.value?.playlist && Array.isArray(activeItem.value.playlist)) {
        return activeItem.value.playlist;
    }
    return [];
});

const currentIndex = computed(() => {
    if (!currentPlaylist.value || currentPlaylist.value.length === 0) return -1;
    return currentPlaylist.value.findIndex((p: any) => {
        const pType = p.type || (p.season_id || p.episode_number ? 'episode' : 'movie');
        const aType = activeItem.value?.type || (activeItem.value?.season_id || activeItem.value?.episode_number ? 'episode' : 'movie');
        return String(p.id) === String(activeItem.value?.id) && pType === aType;
    });
});

const hasPrevious = computed(() => currentIndex.value > 0);
const hasNext = computed(() => currentIndex.value >= 0 && currentIndex.value < currentPlaylist.value.length - 1);

// Subtitles State & Cue Engine
const availableSubtitles = ref<SubtitleItem[]>([]);
const selectedSubtitleId = ref<number | string>('off');
const subtitleDelay = ref<number>(0);
const subtitleFontSize = ref<'sm' | 'md' | 'lg' | 'xl'>('md');
const parsedCues = ref<CueItem[]>([]);
const activeCueText = ref<string>('');
const isFetchingSubtitle = ref(false);

// In-Player Subtitle Search State
const subtitleSearchQuery = ref('');
const subtitleSearchLang = ref('ar');
const isSearchingSubtitles = ref(false);
const isDownloadingSubtitle = ref(false);
const subtitleSearchResults = ref<any[]>([]);
const subtitleSearchError = ref<string | null>(null);

// Audio Enhancer & Equalizer State (Web Audio API)
const vocalBoost = ref(true);
const bassBoost = ref(false);
const nightMode = ref(false);
const audioDelayMs = ref(0); // Audio-to-video sync delay in milliseconds (0 to 1500ms)

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
let delayNode: DelayNode | null = null;
let gainNode: GainNode | null = null;
let isAudioPipelineInitialized = false;

let controlsTimeout: any = null;
let progressSaveInterval: any = null;
let bufferTrackInterval: any = null;
let serverCachePollInterval: any = null;

// Watch audio delay and update DelayNode in real-time
watch(audioDelayMs, (newVal) => {
    if (delayNode) {
        delayNode.delayTime.value = Math.max(0, newVal / 1000);
    }
});

const isEpisode = computed(() => {
    return activeItem.value?.type === 'episode' 
        || activeItem.value?.watchable_type === 'episode' 
        || !!activeItem.value?.episode_number 
        || !!activeItem.value?.season_id;
});

// Auto-detect if media requires Ultra-Fast Server Remuxing
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
const remuxStartOffset = ref(isRemuxStream.value && initialSec > 0 ? initialSec : 0);

// Stable Stream URL: Direct Stream vs Server-Side Remux
const streamUrl = computed(() => {
    if (!activeItem.value) return '';
    if (isRemuxStream.value) {
        const startParam = remuxStartOffset.value > 0 ? `?start=${Math.round(remuxStartOffset.value * 100) / 100}` : '';
        if (isEpisode.value) {
            return `/stream/remux/episode/${activeItem.value.id}${startParam}`;
        }
        return `/stream/remux/movie/${activeItem.value.id}${startParam}`;
    }

    if (isEpisode.value) {
        return `/stream/episode/${activeItem.value.id}`;
    }
    return `/stream/movie/${activeItem.value.id}`;
});

const changeActiveItem = (newItem: any) => {
    savePlaybackProgress();
    stopServerStreamingCache();
    activeItem.value = newItem;
    liveResolution.value = '';
    hasAppliedInitialSeek.value = false;
    currentTime.value = 0;
    const exactDur = Number(newItem?.duration_seconds) || 0;
    const fallbackDur = Number(newItem?.runtime_minutes) ? Number(newItem.runtime_minutes) * 60 : 0;
    duration.value = exactDur > 0 ? exactDur : fallbackDur;
    isDurationLocked.value = exactDur > 0;
    isRemuxStream.value = checkNeedsRemux(newItem);
    remuxStartOffset.value = 0;
    selectedSubtitleId.value = 'off';
    activeCueText.value = '';
    parsedCues.value = [];
    availableSubtitles.value = [];

    setTimeout(() => {
        fetchSubtitles();
        fetchMediaDuration();
        if (videoRef.value) {
            videoRef.value.load();
            videoRef.value.play().catch(() => {});
        }
    }, 50);

    showToast(isRTL.value ? `تشغيل: ${playerHeaderTitle.value}` : `Playing: ${playerHeaderTitle.value}`);
};

const playNext = () => {
    if (!hasNext.value) return;
    const nextItem = currentPlaylist.value[currentIndex.value + 1];
    if (nextItem) changeActiveItem(nextItem);
};

const playPrevious = () => {
    if (!hasPrevious.value) return;
    const prevItem = currentPlaylist.value[currentIndex.value - 1];
    if (prevItem) changeActiveItem(prevItem);
};

const onVideoEnded = () => {
    savePlaybackProgress();
    if (hasNext.value) {
        showToast(isRTL.value ? 'جاري الانتقال للعنصر التالي تلقائياً...' : 'Autoplaying next in 2s...');
        setTimeout(() => {
            playNext();
        }, 1800);
    }
};

watch(() => props.item, (newVal) => {
    if (newVal && newVal.id !== activeItem.value?.id) {
        changeActiveItem(newVal);
    }
});

const toggleRemuxStream = () => {
    isRemuxStream.value = !isRemuxStream.value;
    if (isRemuxStream.value) {
        remuxStartOffset.value = currentTime.value;
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
        remuxStartOffset.value = currentTime.value;
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

// Live dynamic stream resolution detector from video decoder dimensions
const liveResolution = ref<string>('');

const updateLiveResolution = () => {
    const v = videoRef.value;
    if (!v) return;
    const w = v.videoWidth;
    const h = v.videoHeight;
    if (w > 0 && h > 0) {
        if (h >= 2160 || w >= 3800) liveResolution.value = '4K UHD';
        else if (h >= 1440 || w >= 2500) liveResolution.value = '1440p 2K';
        else if (h >= 1000 || w >= 1900) liveResolution.value = '1080p FHD';
        else if (h >= 700 || w >= 1200) liveResolution.value = '720p HD';
        else if (h >= 540) liveResolution.value = '576p SD';
        else if (h >= 450) liveResolution.value = '480p SD';
        else if (h >= 340) liveResolution.value = '360p';
        else if (h >= 200) liveResolution.value = `${h}p`;
        else liveResolution.value = `${w}x${h}`;
    }
};

// Media Meta Display (Resolution, Codec, Audio)
const displayResolution = computed(() => {
    if (liveResolution.value) {
        return liveResolution.value;
    }
    const meta = activeItem.value?.resolution || activeItem.value?.video_resolution;
    if (meta && meta !== 'Unknown') {
        return meta;
    }
    return '';
});

const displayCodec = computed(() => {
    return activeItem.value?.video_codec || activeItem.value?.codec || 'H.264 / HEVC';
});

const displayAudio = computed(() => {
    return activeItem.value?.audio_codec || 'AAC 5.1';
});

const displayYear = computed(() => {
    return activeItem.value?.release_year || activeItem.value?.year || (activeItem.value?.series?.release_year ?? '');
});

// Extract Series Name, Season Number, and Episode Number robustly
const playerHeaderTitle = computed(() => {
    if (isEpisode.value) {
        let sName = '';
        if (isRTL.value) {
            sName = activeItem.value?.series?.title_ar || activeItem.value?.series_title_ar || activeItem.value?.series_name_ar || activeItem.value?.series?.title || activeItem.value?.series_title || '';
        } else {
            sName = activeItem.value?.series?.title || activeItem.value?.series_title || activeItem.value?.series_name || '';
        }

        // If sName is still empty, parse from item.title or fallback
        if (!sName && activeItem.value?.title) {
            const parts = activeItem.value.title.split(/\s*-\s*(?:Season|الموسم|S\d+)/i);
            if (parts[0] && !parts[0].toLowerCase().startsWith('episode') && !parts[0].toLowerCase().startsWith('الحلقة')) {
                sName = parts[0].trim();
            }
        }

        // Clean out raw season/episode codes or suffixes
        sName = sName.replace(/\s*-\s*S\d+E\d+.*$/gi, '')
                     .replace(/\s*-\s*(?:Season|الموسم)\s*\d+.*$/gi, '')
                     .replace(/^(?:Episode|الحلقة)\s*\d+\s*-\s*/gi, '')
                     .trim();

        if (!sName) {
            sName = isRTL.value ? 'مسلسل' : 'Series';
        }

        // Determine Season Number
        let s = activeItem.value?.season_number;
        if (!s && activeItem.value?.season?.season_number) s = activeItem.value.season.season_number;
        if (!s) {
            const m = (activeItem.value?.file_path || activeItem.value?.title || '').match(/S(\d+)E\d+/i);
            s = m ? parseInt(m[1], 10) : 1;
        }

        // Determine Episode Number
        let e = activeItem.value?.episode_number;
        if (!e) {
            const m = (activeItem.value?.file_path || activeItem.value?.title || '').match(/S\d+E(\d+)/i);
            e = m ? parseInt(m[1], 10) : 1;
        }

        const seasonLabel = isRTL.value ? `الموسم ${s}` : `Season ${s}`;
        const episodeLabel = isRTL.value ? `الحلقة ${e}` : `Episode ${e}`;

        return `${sName} - ${seasonLabel} - ${episodeLabel}`;
    }

    if (isRTL.value && activeItem.value?.title_ar) {
        return activeItem.value.title_ar;
    }
    return activeItem.value?.title || 'Media';
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
        const calc = Math.min(100, Math.max(0, (effectiveEnd / duration.value) * 100));
        if (calc > bufferedPercent.value) {
            bufferedPercent.value = calc;
        }
    }
    if (isRemuxStream.value && isPlaying.value) {
        const streamEst = Math.min(100, progressPercent.value + 6);
        if (streamEst > bufferedPercent.value) {
            bufferedPercent.value = streamEst;
        }
    }
};

// Check server background transcode cache progress
const checkServerCacheStatus = async () => {
    try {
        const type = isEpisode.value ? 'episode' : 'movie';
        const res = await fetch(`/api/stream/cache-status?type=${type}&id=${activeItem.value.id}`);
        if (res.ok) {
            const data = await res.json();
            if (data.is_cached) {
                bufferedPercent.value = 100;
                if (serverCachePollInterval) {
                    clearInterval(serverCachePollInterval);
                    serverCachePollInterval = null;
                }
            } else if (typeof data.cached_percent === 'number' && data.cached_percent > bufferedPercent.value) {
                bufferedPercent.value = data.cached_percent;
            }
        }
    } catch (e) {}
};

// Fetch Duration & Subtitles
const fetchMediaDuration = async () => {
    try {
        const type = isEpisode.value ? 'episode' : 'movie';
        const res = await fetch(`/api/media/duration?type=${type}&id=${activeItem.value.id}`);
        const data = await res.json();
        if (data.duration_seconds && data.duration_seconds > 0) {
            duration.value = data.duration_seconds;
            isDurationLocked.value = true;
        }
        if (data.resolution && data.resolution !== 'Unknown' && !liveResolution.value) {
            liveResolution.value = data.resolution;
        }
    } catch (e) {}
};

const fetchSubtitles = async () => {
    try {
        const type = isEpisode.value ? 'episode' : 'movie';
        const res = await fetch(`/api/subtitles/for-media?type=${type}&id=${activeItem.value.id}`);
        const data = await res.json();
        if (data.subtitles) {
            availableSubtitles.value = data.subtitles;
            // Subtitles default to OFF as requested by user
            selectedSubtitleId.value = 'off';
            parsedCues.value = [];
            activeCueText.value = '';
        }
    } catch (e) {}
};

// In-Player Subtitle Search & Download Actions
const openSubtitleSearchModal = () => {
    showSubtitlesMenu.value = false;
    showSubtitleSearchModal.value = true;
    let query = activeItem.value?.title || '';
    if (isEpisode.value) {
        let sName = activeItem.value?.series?.title || activeItem.value?.series_title || '';
        if (sName) {
            query = sName;
        }
    }
    subtitleSearchQuery.value = query;
    performSubtitleSearch();
};

const performSubtitleSearch = async () => {
    if (!subtitleSearchQuery.value.trim()) return;
    isSearchingSubtitles.value = true;
    subtitleSearchError.value = null;
    subtitleSearchResults.value = [];
    try {
        const mediaId = isEpisode.value
            ? (activeItem.value?.watchable_id || activeItem.value?.id)
            : activeItem.value?.id;

        const payload: Record<string, any> = {
            query: subtitleSearchQuery.value.trim(),
            language: subtitleSearchLang.value,
            media_id: mediaId,
            media_type: isEpisode.value ? 'episode' : 'movie',
        };

        if (isEpisode.value) {
            payload.season_number = activeItem.value?.season_number || (activeItem.value?.season?.season_number ?? 1);
            payload.episode_number = activeItem.value?.episode_number || 1;
        }

        const res = await fetch('/api/subtitles/verify-engine', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify(payload),
        });
        if (res.ok) {
            const data = await res.json();
            subtitleSearchResults.value = data.results || [];
        } else {
            subtitleSearchError.value = isRTL.value ? 'فشل البحث عن الترجمة' : 'Failed to search subtitles';
        }
    } catch (e: any) {
        subtitleSearchError.value = e.message || 'Error';
    } finally {
        isSearchingSubtitles.value = false;
    }
};

const downloadAndApplySubtitle = async (result: any) => {
    isDownloadingSubtitle.value = true;
    try {
        const mediaId = isEpisode.value
            ? (activeItem.value?.watchable_id || activeItem.value?.id)
            : activeItem.value?.id;

        const res = await fetch('/api/subtitles/download', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                media_id: mediaId,
                media_type: isEpisode.value ? 'episode' : 'movie',
                language: result.language || subtitleSearchLang.value,
                download_url: result.download_url || result.url,
                file_name: result.file_name,
                release: result.release,
            }),
        });
        if (res.ok) {
            const data = await res.json();
            if (data.subtitle) {
                const newSub = data.subtitle;
                const existingIdx = availableSubtitles.value.findIndex(s => String(s.id) === String(newSub.id));
                if (existingIdx >= 0) {
                    availableSubtitles.value[existingIdx] = newSub;
                } else {
                    availableSubtitles.value.push(newSub);
                }
                showSubtitleSearchModal.value = false;
                await selectSubtitle(newSub.id, false);
                showToast(isRTL.value ? 'تم تنزيل وتفعيل الترجمة بنجاح!' : 'Subtitle downloaded & activated!');
            }
        } else {
            showToast(isRTL.value ? 'تعذر تنزيل الترجمة' : 'Failed to download subtitle');
        }
    } catch (e) {
        showToast(isRTL.value ? 'حدث خطأ أثناء التنزيل' : 'Error downloading subtitle');
    } finally {
        isDownloadingSubtitle.value = false;
    }
};

// Web Audio API Pipeline (Voice Enhancer / Equalizer / Audio Delay Sync)
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

        // Native Delay Node for Audio/Video Sync Compensation
        delayNode = audioCtx.createDelay(5.0);
        delayNode.delayTime.value = Math.max(0, audioDelayMs.value / 1000);

        gainNode = audioCtx.createGain();
        gainNode.gain.value = volume.value;

        sourceNode.connect(voiceFilterNode);
        voiceFilterNode.connect(bassFilterNode);
        bassFilterNode.connect(trebleFilterNode);
        trebleFilterNode.connect(compressorNode);
        compressorNode.connect(delayNode);
        delayNode.connect(gainNode);
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
        remuxStartOffset.value = clamped;
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

const selectSubtitle = (subId: number | string, notify = true) => {
    selectedSubtitleId.value = subId;
    loadSubtitleTrack(subId);
    showSubtitlesMenu.value = false;
    if (notify) {
        const found = availableSubtitles.value.find(s => s.id === subId);
        showToast(subId === 'off' ? (isRTL.value ? 'الترجمة: معطلة' : 'Subtitles: Off') : (isRTL.value ? `تم اختيار: ${found?.language_name}` : `Selected: ${found?.language_name}`));
    }
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
    updateLiveResolution();
    const d = videoRef.value.duration;
    if (!isDurationLocked.value && !isRemuxStream.value && d && isFinite(d) && !isNaN(d) && d > 0) {
        duration.value = d;
    }

    // Direct stream seek to saved position once on load
    if (!hasAppliedInitialSeek.value) {
        hasAppliedInitialSeek.value = true;
        if (!isRemuxStream.value && initialSec > 0 && initialSec < duration.value - 5) {
            videoRef.value.currentTime = initialSec;
        }
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
    updateLiveResolution();
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
    if (!currentTime.value || currentTime.value <= 3 || !activeItem.value) return;
    const dur = Math.floor(duration.value);
    const current = Math.floor(currentTime.value);

    emit('update:progress', current);
    try {
        localStorage.setItem(`progress_${isEpisode.value ? 'episode' : 'movie'}_${activeItem.value.id}`, String(current));
        if (dur > 0) {
            localStorage.setItem(`duration_${isEpisode.value ? 'episode' : 'movie'}_${activeItem.value.id}`, String(dur));
        }

        await fetch('/api/watch-history/progress', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || ''
            },
            body: JSON.stringify({
                watchable_type: isEpisode.value ? 'episode' : 'media_item',
                watchable_id: activeItem.value.id,
                media_item_id: isEpisode.value ? (activeItem.value.series_id || activeItem.value.series?.id) : activeItem.value.id,
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
            id: activeItem.value?.id
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

    if (e.shiftKey && (e.key === 'N' || e.key === 'n')) {
        e.preventDefault();
        playNext();
        return;
    }
    if (e.shiftKey && (e.key === 'P' || e.key === 'p')) {
        e.preventDefault();
        playPrevious();
        return;
    }

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

    progressSaveInterval = setInterval(() => {
        if (isPlaying.value) {
            savePlaybackProgress();
        }
    }, 12000);

    // Track YouTube-Style Buffer Line continuously
    bufferTrackInterval = setInterval(() => {
        updateBufferProgress();
    }, 400);

    // Poll server background transcode cache status in Remux mode
    if (isRemuxStream.value) {
        checkServerCacheStatus();
        serverCachePollInterval = setInterval(() => {
            checkServerCacheStatus();
        }, 1500);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeyDown);
    clearInterval(progressSaveInterval);
    clearInterval(bufferTrackInterval);
    clearInterval(serverCachePollInterval);
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
                            <span v-if="displayResolution" class="px-2 py-0.5 rounded-md bg-cyan-500/20 border border-cyan-400/40 text-cyan-300 font-extrabold flex items-center gap-1">
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
            @canplay="updateLiveResolution"
            @play="onVideoPlay"
            @pause="onVideoPause"
            @waiting="onVideoWaiting"
            @playing="onVideoPlaying"
            @ended="onVideoEnded"
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
            :style="{ bottom: isControlsVisible ? '5.5rem' : '1.75rem' }"
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
                                class="absolute inset-y-0 left-0 bg-slate-200/75 dark:bg-white/60 rounded-full transition-all duration-300 shadow-[0_0_8px_rgba(255,255,255,0.4)]"
                                :style="{ width: `${bufferedPercent}%` }"
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
                    <!-- Left: Play/Pause, Next/Previous, Seek, Volume -->
                    <div class="flex items-center gap-1.5 sm:gap-2.5">
                        <!-- Previous Episode / Item Button -->
                        <button
                            v-if="currentPlaylist.length > 0"
                            @click="playPrevious"
                            :disabled="!hasPrevious"
                            class="w-9 h-9 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 flex items-center justify-center transition-all cursor-pointer active:scale-90 disabled:opacity-30 disabled:cursor-not-allowed"
                            :title="isRTL ? 'العنصر السابق (Shift+P)' : 'Previous (Shift+P)'"
                        >
                            <SkipBack class="w-4 h-4 fill-current" />
                        </button>

                        <button
                            @click="togglePlay"
                            class="w-10 h-10 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 flex items-center justify-center transition-transform hover:scale-105 active:scale-95 cursor-pointer shadow-lg shadow-cyan-500/20"
                            :title="isPlaying ? 'Pause (Space)' : 'Play (Space)'"
                        >
                            <Play v-if="!isPlaying" class="w-5 h-5 fill-current ml-0.5" />
                            <Pause v-else class="w-5 h-5 fill-current" />
                        </button>

                        <!-- Next Episode / Item Button -->
                        <button
                            v-if="currentPlaylist.length > 0"
                            @click="playNext"
                            :disabled="!hasNext"
                            class="w-9 h-9 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 flex items-center justify-center transition-all cursor-pointer active:scale-90 disabled:opacity-30 disabled:cursor-not-allowed"
                            :title="isRTL ? 'العنصر التالي (Shift+N)' : 'Next (Shift+N)'"
                        >
                            <SkipForward class="w-4 h-4 fill-current" />
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

                                    <!-- Online Subtitle Search & Download Trigger -->
                                    <div class="border-t border-white/10 pt-2">
                                        <button
                                            @click="openSubtitleSearchModal"
                                            class="w-full px-3 py-2 rounded-xl bg-purple-500/20 hover:bg-purple-500/30 text-purple-300 hover:text-white border border-purple-500/30 text-xs font-bold flex items-center justify-center gap-2 transition-all cursor-pointer shadow-sm"
                                        >
                                            <Search class="w-3.5 h-3.5" />
                                            <span>{{ isRTL ? 'بحث وتحميل ترجمة أونلاين...' : 'Search & Download Subtitles...' }}</span>
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
                                :class="vocalBoost || bassBoost || nightMode || audioDelayMs !== 0 ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
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

                                        <!-- Audio / Video Sync Slider (Delay compensation when sound is ahead) -->
                                        <div class="border-t border-white/10 pt-2.5 flex flex-col gap-2">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                                                    <Clock class="w-3.5 h-3.5 text-cyan-400" />
                                                    <span>{{ isRTL ? 'مزامنة وتأخير الصوت' : 'Audio Sync / Delay' }}</span>
                                                </span>
                                                <span class="text-xs font-mono font-bold text-cyan-300">
                                                    {{ audioDelayMs > 0 ? `+${audioDelayMs}` : audioDelayMs }} ms
                                                </span>
                                            </div>
                                            <div class="text-[10px] text-slate-400 leading-tight">
                                                {{ isRTL ? 'استخدم التأخير (+) عندما يسبق الصوت حركة الفيديو' : 'Add positive delay (+) when audio leads video' }}
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <input
                                                    type="range"
                                                    min="-500"
                                                    max="1500"
                                                    step="25"
                                                    v-model.number="audioDelayMs"
                                                    class="w-full h-1.5 bg-white/20 rounded-lg appearance-none cursor-pointer accent-cyan-400"
                                                />
                                            </div>
                                            <div class="flex items-center justify-between gap-1 pt-0.5">
                                                <button
                                                    v-for="preset in [0, 100, 250, 500]"
                                                    :key="preset"
                                                    @click="audioDelayMs = preset"
                                                    class="px-2 py-0.5 rounded-lg text-[10px] font-mono font-bold transition-all cursor-pointer"
                                                    :class="audioDelayMs === preset ? 'bg-cyan-500 text-slate-950' : 'bg-white/5 hover:bg-white/10 text-slate-400'"
                                                >
                                                    {{ preset === 0 ? '0ms' : `+${preset}ms` }}
                                                </button>
                                            </div>
                                        </div>
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

        <!-- In-Player Subtitle Search & Download Modal -->
        <transition name="fade">
            <div
                v-if="showSubtitleSearchModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
                @click.self="showSubtitleSearchModal = false"
            >
                <div class="relative w-full max-w-lg bg-slate-950 border border-purple-500/30 rounded-3xl p-6 shadow-2xl space-y-4 max-h-[85vh] flex flex-col">
                    <div class="flex items-center justify-between border-b border-white/10 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-purple-500/20 text-purple-400 flex items-center justify-center">
                                <Search class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="font-black text-sm text-white">
                                    {{ isRTL ? 'البحث عن ملفات الترجمة وتحميلها فوراً' : 'Search & Download Subtitles' }}
                                </h3>
                                <p class="text-[11px] text-slate-400">
                                    {{ isRTL ? 'محرك SubDL و OpenSubtitles المباشر' : 'SubDL & OpenSubtitles live engine' }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="showSubtitleSearchModal = false"
                            class="w-7 h-7 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center cursor-pointer"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Search Input & Lang Selector -->
                    <div class="space-y-2.5">
                        <div class="flex items-center gap-2">
                            <input
                                v-model="subtitleSearchQuery"
                                @keyup.enter="performSubtitleSearch"
                                type="text"
                                :placeholder="isRTL ? 'اسم الفيلم أو المسلسل...' : 'Movie or series title...'"
                                class="flex-1 px-4 py-2.5 rounded-xl bg-white/5 border border-white/15 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-purple-400"
                            />
                            <button
                                @click="performSubtitleSearch"
                                :disabled="isSearchingSubtitles"
                                class="px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer disabled:opacity-50"
                            >
                                <Loader2 v-if="isSearchingSubtitles" class="w-3.5 h-3.5 animate-spin" />
                                <Search v-else class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'بحث' : 'Search' }}</span>
                            </button>
                        </div>

                        <!-- Language Choice Pills -->
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-400">{{ isRTL ? 'اللغة:' : 'Language:' }}</span>
                            <button
                                v-for="l in [{ id: 'ar', label: 'العربية (AR)' }, { id: 'en', label: 'English (EN)' }]"
                                :key="l.id"
                                @click="subtitleSearchLang = l.id; performSubtitleSearch()"
                                class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer"
                                :class="subtitleSearchLang === l.id ? 'bg-purple-500 text-white' : 'bg-white/5 text-slate-400 hover:text-white'"
                            >
                                {{ l.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Results List -->
                    <div class="flex-1 overflow-y-auto space-y-2 pr-1 custom-scrollbar min-h-[160px]">
                        <div v-if="isSearchingSubtitles" class="py-12 flex flex-col items-center justify-center gap-3 text-slate-400">
                            <Loader2 class="w-7 h-7 text-purple-400 animate-spin" />
                            <span class="text-xs">{{ isRTL ? 'جاري فحص مزودي الترجمة...' : 'Scanning subtitle providers...' }}</span>
                        </div>

                        <div v-else-if="subtitleSearchResults.length === 0" class="py-12 text-center text-slate-400 text-xs">
                            {{ isRTL ? 'لا توجد نتائج بحث مطابقة. جرب البحث باسم مختلف.' : 'No matching subtitles found. Try refining search title.' }}
                        </div>

                        <div
                            v-else
                            v-for="(sub, idx) in subtitleSearchResults"
                            :key="idx"
                            class="p-3 rounded-2xl bg-white/[0.03] border border-white/10 hover:border-purple-500/40 transition-all flex items-center justify-between gap-3"
                        >
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300 text-[10px] font-mono font-bold uppercase">
                                        {{ sub.language || subtitleSearchLang }}
                                    </span>
                                    <span class="text-[11px] text-slate-400 truncate">
                                        {{ sub.provider || 'SubDL' }}
                                    </span>
                                </div>
                                <div class="text-xs font-bold text-white truncate" :title="sub.release || sub.file_name">
                                    {{ sub.release || sub.file_name || 'Subtitle Release Track' }}
                                </div>
                            </div>

                            <button
                                @click="downloadAndApplySubtitle(sub)"
                                :disabled="isDownloadingSubtitle"
                                class="shrink-0 px-3.5 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer disabled:opacity-50 shadow-md shadow-purple-500/20"
                            >
                                <Download class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'تحميل وتفعيل' : 'Get & Apply' }}</span>
                            </button>
                        </div>
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
