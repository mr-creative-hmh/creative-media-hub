<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { useToast } from '@/composables/useToast';
import {
    Play, Pause, RotateCcw, Maximize2, Minimize2, ArrowLeft, ArrowRight,
    X, Volume2, VolumeX, Sparkles, Sliders, MessageSquare, Clock, Subtitles,
    SkipBack, SkipForward, AlertTriangle, RefreshCw, Check, Type, Plus, Minus
} from 'lucide-vue-next';

interface CueItem {
    start: number;
    end: number;
    text: string;
}

const props = withDefaults(defineProps<{
    episode?: any;
    episodeId?: number;
}>(), {
    episodeId: 5764,
});

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const { isRTL, t } = useI18n();
const toast = useToast();

const containerRef = ref<HTMLDivElement | null>(null);
const videoRef = ref<HTMLVideoElement | null>(null);

// Loading & Engine states
const isLoadingAssets = ref(true);
const isBuffering = ref(false);
const isPlaying = ref(false);
const isFullscreen = ref(false);
const showControls = ref(true);
const isVideoLoaded = ref(false);
const videoError = ref<string | null>(null);
let controlsTimer: any = null;

// Volume & Mute
const volume = ref(1.0);
const isMuted = ref(false);

// Timeline tracking
const currentMsDisplay = ref(0);
const durationMsDisplay = ref(18734000); // 5h 12m exact
const currentChapterName = ref<string>('1A');

// Stream URL (Standard Direct HTTP Range)
const activeEpisodeId = computed(() => props.episode?.id || props.episode?.watchable_id || props.episodeId || 5764);
const streamUrl = computed(() => `/stream/episode/${activeEpisodeId.value}`);

// Choice Translation automatically derived from app language (top bar selector removed)
const choiceLang = computed<'ar' | 'en' | 'fr'>(() => isRTL.value ? 'ar' : 'en');

// Interactive Choice HUD State
const currentChoiceMoment = ref<any>(null);
const choiceCaption = ref<string>('');
const choiceProgress = ref<number>(0);
const highlightedChoiceIndex = ref<number>(-1);
const isChoiceSelected = ref<boolean>(false);
const selectedDigits = ref<string[]>([]);
const isPhoneScene = computed(() => currentChoiceMoment.value?.type === 'scene:cs_bs_phone');

// Subtitles Engine State (matching CinemaPlayer)
const showSubtitles = ref(true);
const showSubtitlesMenu = ref(false);
const selectedSubtitleId = ref<number | string>('off');
const availableSubtitles = ref<any[]>([]);
const parsedCues = ref<CueItem[]>([]);
const activeCueText = ref<string>('');
const subtitleDelay = ref<number>(0); // in seconds
const subtitleFont = ref<'cairo' | 'jakarta' | 'system'>(
    (localStorage.getItem('cinema_subtitle_font') as any) || 'cairo'
);
const subtitleFontSize = ref<'sm' | 'md' | 'lg' | 'xl'>(
    (localStorage.getItem('cinema_subtitle_font_size') as any) || 'md'
);
const isFetchingSubtitle = ref<boolean>(false);

const subtitleFontFamily = computed(() => {
    switch (subtitleFont.value) {
        case 'jakarta':
            return "'Plus Jakarta Sans', 'Cairo', system-ui, sans-serif";
        case 'system':
            return "system-ui, -apple-system, 'Cairo', 'Plus Jakarta Sans', sans-serif";
        case 'cairo':
        default:
            return "'Cairo', 'Plus Jakarta Sans', system-ui, sans-serif";
    }
});

const setSubtitleFont = (fontKey: 'cairo' | 'jakarta' | 'system') => {
    subtitleFont.value = fontKey;
    try {
        localStorage.setItem('cinema_subtitle_font', fontKey);
    } catch {}
};

const setSubtitleFontSize = (size: 'sm' | 'md' | 'lg' | 'xl') => {
    subtitleFontSize.value = size;
    try {
        localStorage.setItem('cinema_subtitle_font_size', size);
    } catch {}
};

const adjustSubtitleDelay = (delta: number) => {
    subtitleDelay.value = Math.round((subtitleDelay.value + delta) * 10) / 10;
    if (videoRef.value) {
        updateActiveCue(videoRef.value.currentTime);
    }
};

// Audio Delay & Sync
const showSettings = ref(false);
const audioDelayMs = ref(0);
let audioCtx: AudioContext | null = null;
let delayNode: DelayNode | null = null;
let sourceNode: MediaElementAudioSourceNode | null = null;

// Engine References
let bv: any = null;
let choicePoints: any = null;
let momentsBySegment: any = null;
let segmentGroups: any = null;
let segmentMap: any = null;
let moments: any = null;
let ls: Storage = window.localStorage;

let timerId: any = 0;
let lastMs = 0;
let currentSegment: string | null = null;
let lastSegment: string | null = null;
let prevSegment: string | null = null;
let segmentTransition = false;
let lastMoments: Record<string, any> = {};
let nextChoice = -1;
let chosenNextSegment: string | null = null;
let chosenImpressionData: any = null;

const loadScript = (src: string): Promise<void> => {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();
            return;
        }
        const s = document.createElement('script');
        s.src = src;
        s.async = false;
        s.onload = () => resolve();
        s.onerror = (e) => reject(new Error(`Failed to load ${src}`));
        document.head.appendChild(s);
    });
};

const initEngine = async () => {
    isLoadingAssets.value = true;
    videoError.value = null;

    try {
        await loadScript('/vendor/bandersnatch/bandersnatch.js');
        await loadScript('/vendor/bandersnatch/SegmentMap.js');
        await loadScript('/vendor/bandersnatch/choices_en.js');
        await loadScript('/vendor/bandersnatch/choices_ar.js');
        await loadScript('/vendor/bandersnatch/choices_fr.js');

        const w = window as any;
        if (!w.bandersnatch || !w.SegmentMap) {
            throw new Error('Interactive metadata variables failed to initialize');
        }

        segmentMap = w.SegmentMap;
        bv = w.bandersnatch.videos['80988062'].interactiveVideoMoments.value;
        choicePoints = bv.choicePointNavigatorMetadata.choicePointsMetadata.choicePoints;
        momentsBySegment = bv.momentsBySegment;
        segmentGroups = bv.segmentGroups;

        // Initialize persistent state if not already
        if (!('initialized' in ls)) {
            for (let k in bv.stateHistory) {
                ls['persistentState_' + k] = JSON.stringify(bv.stateHistory[k]);
            }
            ls['initialized'] = 't';
        }

        updateChoiceTranslations();
        await fetchEpisodeSubtitles();
        isLoadingAssets.value = false;

        // Prepare initial segment or saved place
        const savedPlace = ls.getItem('bandersnatch_place');
        if (savedPlace) {
            playHash(savedPlace);
        } else {
            playSegment(segmentMap.initialSegment);
        }

        // Trigger autoplay if possible
        if (videoRef.value) {
            const p = videoRef.value.play();
            if (p !== undefined) {
                p.then(() => {
                    isPlaying.value = true;
                }).catch((err) => {
                    console.log('Autoplay deferred for user gesture:', err);
                    isPlaying.value = false;
                });
            }
        }
    } catch (err: any) {
        console.error('Failed to init Bandersnatch engine:', err);
        videoError.value = err.message || 'Failed to initialize Bandersnatch interactive engine';
        isLoadingAssets.value = false;
    }
};

const updateChoiceTranslations = () => {
    const w = window as any;
    let trans = w.en;
    if (choiceLang.value === 'ar' && w.ar) trans = w.ar;
    if (choiceLang.value === 'fr' && w.fr) trans = w.fr;

    moments = JSON.parse(JSON.stringify(momentsBySegment));
    for (let key in trans) {
        if (!moments[key]) continue;
        for (let i = 0; i < moments[key].length; i++) {
            if ('choices' in moments[key][i]) {
                for (let k = 0; k < moments[key][i].choices.length; k++) {
                    const c = moments[key][i].choices[k];
                    if (c.id && c.id in trans[key]) {
                        c.text = trans[key][c.id];
                    }
                }
            }
        }
    }
};

watch(choiceLang, () => {
    updateChoiceTranslations();
    if (currentChoiceMoment.value) {
        const seg = currentSegment;
        if (seg && moments[seg]) {
            const ms = getCurrentMs();
            const active = getMoments(seg, ms);
            for (let k in active) {
                if (active[k].choices) {
                    currentChoiceMoment.value = active[k];
                }
            }
        }
    }
});

const getCurrentMs = () => {
    if (!videoRef.value) return 0;
    return Math.round(videoRef.value.currentTime * 1000.0);
};

const preconditionToJS = (cond: any): string => {
    if (!cond) return 'true';
    if (cond[0] === 'persistentState') {
        return `JSON.parse(localStorage.getItem("persistentState_${cond[1]}") || "null")`;
    } else if (cond[0] === 'not') {
        return `!(${preconditionToJS(cond[1])})`;
    } else if (cond[0] === 'and') {
        return `(${cond.slice(1).map(preconditionToJS).join(' && ')})`;
    } else if (cond[0] === 'or') {
        return `(${cond.slice(1).map(preconditionToJS).join(' || ')})`;
    } else if (cond[0] === 'eql' && cond.length === 3) {
        return `(${preconditionToJS(cond[1])} == ${preconditionToJS(cond[2])})`;
    } else if (cond === false) {
        return 'false';
    } else if (cond === true) {
        return 'true';
    } else if (typeof cond === 'string') {
        return JSON.stringify(cond);
    }
    return 'true';
};

const evalPrecondition = (precondition: any): boolean => {
    if (!precondition) return true;
    try {
        const js = preconditionToJS(precondition);
        return Boolean(Function(`"use strict"; return (${js});`)());
    } catch {
        return true;
    }
};

const checkPrecondition = (preconditionId: string): boolean => {
    return evalPrecondition(bv.preconditions[preconditionId]);
};

const resolveSegmentGroup = (sg: string): string => {
    const results: string[] = [];
    if (!segmentGroups[sg]) return sg;
    for (let v of segmentGroups[sg]) {
        if (v.precondition && !checkPrecondition(v.precondition)) continue;
        if (v.segmentGroup) {
            results.push(resolveSegmentGroup(v.segmentGroup));
        } else if (v.segment) {
            results.push(v.segment);
        } else {
            if (typeof v === 'string' && !checkPrecondition(v)) continue;
            results.push(v);
        }
    }
    return results[0] || sg;
};

const getSegmentId = (ms: number): string | null => {
    if (!segmentMap?.segments) return null;
    for (const [k, v] of Object.entries(segmentMap.segments as Record<string, any>)) {
        if (ms >= v.startTimeMs && (!v.endTimeMs || ms < v.endTimeMs)) {
            return k;
        }
    }
    return null;
};

const getSegmentMs = (segmentId: string): number => {
    if (!segmentMap?.segments?.[segmentId]) return 0;
    return segmentMap.segments[segmentId].startTimeMs || 0;
};

const getMoments = (segmentId: string, ms: number): Record<string, any> => {
    const result: Record<string, any> = {};
    const segMoments = (moments && moments[segmentId]) ? moments[segmentId] : (momentsBySegment?.[segmentId] || []);
    for (let i = 0; i < segMoments.length; i++) {
        const m = segMoments[i];
        const momentId = `${segmentId}/${i}`;
        if (ms >= m.startMs && ms < m.endMs && evalPrecondition(m.precondition)) {
            result[momentId] = m;
        }
    }
    return result;
};

const onTimeUpdate = () => {
    if (!videoRef.value || isLoadingAssets.value) return;
    const ms = getCurrentMs();
    currentMsDisplay.value = ms;

    // Update Subtitles
    updateActiveCue(videoRef.value.currentTime);

    currentSegment = getSegmentId(ms);
    if (currentSegment) {
        currentChapterName.value = currentSegment;
    }
    const segment = currentSegment ? segmentMap.segments[currentSegment] : null;

    if (timerId) {
        clearTimeout(timerId);
        timerId = 0;
    }

    const timeElapsed = ms - lastMs;
    const seeked = timeElapsed < 0 || timeElapsed >= 2000;
    lastMs = ms;

    if (lastSegment !== currentSegment) {
        prevSegment = lastSegment;
        lastSegment = currentSegment;
        if (!seeked && prevSegment) {
            if (playNextSegment(prevSegment)) return;
        }
    }

    const naturalTransition = !seeked || segmentTransition;
    segmentTransition = false;

    const currentMoments = currentSegment ? getMoments(currentSegment, ms) : {};

    for (let k in lastMoments) {
        if (!(k in currentMoments)) {
            momentEnd(lastMoments[k]);
        }
    }

    for (let k in lastMoments) {
        if (k in currentMoments) {
            momentUpdate(lastMoments[k], ms);
        }
    }

    for (let k in currentMoments) {
        if (!(k in lastMoments)) {
            momentStart(currentMoments[k]);
        }
    }

    lastMoments = currentMoments;

    // Save place in local storage
    if (currentSegment) {
        ls.setItem('bandersnatch_place', '#' + currentSegment);
    }

    // High resolution timer
    let nextEvent = segment ? segment.endTimeMs : 0;
    for (let k in currentMoments) {
        if (currentMoments[k].endMs < nextEvent) nextEvent = currentMoments[k].endMs;
    }
    if (currentSegment) {
        const segMoments = (moments && moments[currentSegment]) || (momentsBySegment && momentsBySegment[currentSegment]) || [];
        for (let m of segMoments) {
            if (ms < m.startMs && m.startMs < nextEvent) nextEvent = m.startMs;
        }
    }
    const timeLeft = nextEvent - ms;
    if (timeLeft > 0 && timeLeft < 2000) {
        timerId = setTimeout(onTimeUpdate, timeLeft);
    }
};

const momentStart = (m: any) => {
    if (m.choices) {
        currentChoiceMoment.value = m;
        highlightedChoiceIndex.value = -1;
        isChoiceSelected.value = false;
        chosenNextSegment = null;
        chosenImpressionData = null;
        selectedDigits.value = [];
        if (m.id in choicePoints && choicePoints[m.id].description) {
            choiceCaption.value = choicePoints[m.id].description;
        } else {
            choiceCaption.value = '';
        }
    }
    applyImpression(m.impressionData);
};

const momentUpdate = (m: any, ms: number) => {
    if (m.choices) {
        const p = Math.max(0, Math.min(100, 100 - ((ms - m.startMs) * 100.0 / (m.endMs - m.startMs))));
        choiceProgress.value = p;
    }
};

const momentEnd = (m: any) => {
    if (m.choices) {
        currentChoiceMoment.value = null;
        choiceProgress.value = 0;
        selectedDigits.value = [];
    }
};

const applyImpression = (impressionData: any) => {
    if (impressionData && impressionData.type === 'userState') {
        for (const [variable, value] of Object.entries(impressionData.data.persistent)) {
            ls.setItem('persistentState_' + variable, JSON.stringify(value));
        }
    }
};

const playNextSegment = (prevSeg: string): boolean => {
    let nextSeg: string | null = null;

    // 1. Priority: segment chosen by user interactive decision
    if (chosenNextSegment) {
        nextSeg = chosenNextSegment;
        chosenNextSegment = null;
        chosenImpressionData = null;
        isChoiceSelected.value = false;
        currentChoiceMoment.value = null;
    } else if (nextChoice >= 0 && currentChoiceMoment.value?.choices?.[nextChoice]) {
        const x = currentChoiceMoment.value.choices[nextChoice];
        if (x.segmentId) nextSeg = x.segmentId;
        else if (x.sg) nextSeg = resolveSegmentGroup(x.sg);
        applyImpression(x.impressionData);
        nextChoice = -1;
        currentChoiceMoment.value = null;
        isChoiceSelected.value = false;
    }

    // 2. Default fallback if user let timer expire without choosing
    if (!nextSeg && prevSeg && prevSeg in segmentGroups) {
        nextSeg = resolveSegmentGroup(prevSeg);
    }
    if (!nextSeg && prevSeg && segmentMap.segments[prevSeg]?.defaultNext) {
        nextSeg = segmentMap.segments[prevSeg].defaultNext;
    }

    if (!nextSeg) return false;

    ls.setItem('breadcrumb_' + nextSeg, prevSeg);
    segmentTransition = true;
    return playSegment(nextSeg, true);
};

const playSegment = (segmentId: string, noSeek = false): boolean => {
    if (!segmentId) segmentId = segmentMap?.initialSegment || '1A';
    currentSegment = segmentId;
    currentChapterName.value = segmentId;
    const oldSegment = getSegmentId(getCurrentMs());
    if (!noSeek || oldSegment !== segmentId) {
        const ms = getSegmentMs(segmentId);
        seek(ms);
        return true;
    }
    return false;
};

const seek = (ms: number) => {
    if (!videoRef.value) return;
    const targetSec = ms / 1000.0;
    if (videoRef.value.readyState >= 1) {
        videoRef.value.currentTime = targetSec;
        updateActiveCue(targetSec);
    } else {
        const onLoaded = () => {
            if (videoRef.value) {
                videoRef.value.currentTime = targetSec;
                updateActiveCue(targetSec);
            }
            videoRef.value?.removeEventListener('loadedmetadata', onLoaded);
        };
        videoRef.value.addEventListener('loadedmetadata', onLoaded);
    }
    onTimeUpdate();
};

const playHash = (hash: string) => {
    if (!hash) return;
    hash = hash.replace(/^#/, '');
    if (hash[0] === 't') {
        seek(Number(Math.round(Number(hash.slice(1)) * 1000.0)));
    } else {
        const loc = hash.split('/');
        const segmentId = loc[0];
        if (loc.length > 1 && momentsBySegment?.[segmentId]?.[loc[1]]) {
            seek(momentsBySegment[segmentId][loc[1]].startMs);
        } else {
            seek(getSegmentMs(segmentId));
        }
    }
};

const handleChoice = (index: number | string) => {
    if (!currentChoiceMoment.value || !currentChoiceMoment.value.choices) return;
    if (isChoiceSelected.value) return;

    const numIndex = Number(index);
    if (isNaN(numIndex)) return;

    highlightedChoiceIndex.value = numIndex;
    isChoiceSelected.value = true;
    nextChoice = numIndex;

    const m = currentChoiceMoment.value;
    const x = m.choices[numIndex];
    if (!x) return;

    // Resolve target segment for this choice
    if (x.segmentId) {
        chosenNextSegment = x.segmentId;
    } else if (x.sg) {
        chosenNextSegment = resolveSegmentGroup(x.sg);
    }
    chosenImpressionData = x.impressionData;
    applyImpression(x.impressionData);

    // If immediate scene transition is allowed (not disabled), jump immediately
    if (!m.config?.disableImmediateSceneTransition) {
        currentChoiceMoment.value = null;
        isChoiceSelected.value = false;
        if (chosenNextSegment) {
            const seg = chosenNextSegment;
            chosenNextSegment = null;
            chosenImpressionData = null;
            playSegment(seg);
        } else if (prevSegment) {
            playNextSegment(prevSegment);
        }
    }
};

// Phone Keypad Interactive Feature
const selectDigit = (digit: string) => {
    if (selectedDigits.value.length < 5) {
        selectedDigits.value.push(digit);
        if (selectedDigits.value.length === 5) {
            const code = selectedDigits.value.join('');
            if (code === '20541') {
                handleChoice(0);
            } else {
                handleChoice(1);
            }
        }
    }
};

const backspaceDigit = () => {
    selectedDigits.value.pop();
};

const jumpForward = () => {
    const ms = getCurrentMs();
    const segmentId = getSegmentId(ms);
    if (!segmentId) return;

    let interactionMs = 0;
    const segMoments = momentsBySegment[segmentId] || [];
    for (let m of segMoments) {
        if (m.startMs > ms && (interactionMs === 0 || m.startMs < interactionMs)) {
            interactionMs = m.startMs;
        }
    }

    segmentTransition = true;
    if (interactionMs) {
        seek(interactionMs);
    } else {
        playNextSegment(segmentId);
    }
};

const jumpBack = () => {
    const ms = getCurrentMs();
    const segmentId = getSegmentId(ms);
    if (!segmentId) return;
    const segment = segmentMap.segments[segmentId];

    let interactionMs = 0;
    const segMoments = momentsBySegment[segmentId] || [];
    let inMoment = false;
    for (let m of segMoments) {
        if (m.endMs < ms && m.startMs > interactionMs) interactionMs = m.startMs;
        if (m.startMs !== segment?.startTimeMs && m.startMs <= ms && ms < m.endMs) inMoment = true;
    }

    segmentTransition = true;
    if (interactionMs) {
        seek(interactionMs);
    } else if (inMoment && segment) {
        seek(segment.startTimeMs);
    } else {
        const breadcrumb = 'breadcrumb_' + segmentId;
        const prev = ls.getItem(breadcrumb);
        if (prev && segmentMap.segments[prev]) {
            const prevSeg = segmentMap.segments[prev];
            interactionMs = prevSeg.startTimeMs;
            const pMoments = momentsBySegment[prev] || [];
            for (let m of pMoments) {
                if (m.startMs > interactionMs) interactionMs = m.startMs;
            }
            seek(interactionMs);
        } else {
            seek(0);
        }
    }
};

// Seamless Instant Reset (Fixed: no blocking confirm() popup)
const resetStory = () => {
    try {
        for (let k in localStorage) {
            if (k.startsWith('persistentState_') || k.startsWith('breadcrumb_') || k === 'bandersnatch_place') {
                localStorage.removeItem(k);
            }
        }
        if (bv?.stateHistory) {
            for (let k in bv.stateHistory) {
                ls['persistentState_' + k] = JSON.stringify(bv.stateHistory[k]);
            }
            ls['initialized'] = 't';
        }
    } catch {}

    chosenNextSegment = null;
    chosenImpressionData = null;
    isChoiceSelected.value = false;
    currentChoiceMoment.value = null;
    nextChoice = -1;
    selectedDigits.value = [];

    const initialSeg = segmentMap?.initialSegment || '1A';
    currentSegment = initialSeg;
    currentChapterName.value = initialSeg;
    seek(0);

    if (videoRef.value) {
        videoRef.value.currentTime = 0;
        videoRef.value.play().then(() => {
            isPlaying.value = true;
        }).catch(() => {});
    }

    toast.success(isRTL.value ? 'تمت إعادة ضبط مسار القصة وبدء التجربة من جديد' : 'Story decisions reset to beginning');
};

// Video Event Handlers
const onLoadedMetadata = () => {
    isVideoLoaded.value = true;
    if (videoRef.value?.duration) {
        durationMsDisplay.value = Math.round(videoRef.value.duration * 1000);
    }
};

const onCanPlay = () => {
    isBuffering.value = false;
};

const onPlay = () => {
    isPlaying.value = true;
    isBuffering.value = false;
};

const onPause = () => {
    isPlaying.value = false;
};

const onVideoError = () => {
    isBuffering.value = false;
    const err = videoRef.value?.error;
    console.error('Video error:', err);
    videoError.value = err ? `Error (${err.code}): ${err.message || 'Stream disconnected'}` : 'Unable to play video';
};

const togglePlay = () => {
    if (!videoRef.value) return;
    if (videoRef.value.paused) {
        videoRef.value.play().then(() => {
            isPlaying.value = true;
            showControls.value = false;
        }).catch((err) => {
            console.error('Playback failed:', err);
            toast.error(err.message || 'Unable to start playback');
        });
    } else {
        videoRef.value.pause();
        isPlaying.value = false;
        showControls.value = true;
    }
};

const toggleMute = () => {
    if (!videoRef.value) return;
    isMuted.value = !isMuted.value;
    videoRef.value.muted = isMuted.value;
};

const onVolumeChange = (newVal: number) => {
    volume.value = newVal;
    if (videoRef.value) {
        videoRef.value.volume = newVal;
        if (newVal > 0) isMuted.value = false;
    }
};

const toggleFullscreen = () => {
    if (!containerRef.value) return;
    if (!document.fullscreenElement) {
        containerRef.value.requestFullscreen().then(() => { isFullscreen.value = true; }).catch(() => {});
    } else {
        document.exitFullscreen().then(() => { isFullscreen.value = false; }).catch(() => {});
    }
};

const onMouseMove = () => {
    showControls.value = true;
    clearTimeout(controlsTimer);
    controlsTimer = setTimeout(() => {
        if (isPlaying.value && !currentChoiceMoment.value && !showSubtitlesMenu.value && !showSettings.value) {
            showControls.value = false;
        }
    }, 3500);
};

// Keyboard listener
const onKeyDown = (e: KeyboardEvent) => {
    if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return;

    if (e.code === 'Space') {
        e.preventDefault();
        togglePlay();
    } else if (e.code === 'KeyF') {
        toggleFullscreen();
    } else if (e.code === 'KeyR') {
        if (currentSegment) playSegment(currentSegment);
    } else if (e.code === 'KeyM') {
        toggleMute();
    } else if (e.code === 'KeyC') {
        showSubtitlesMenu.value = !showSubtitlesMenu.value;
    } else if (e.code === 'Escape') {
        if (showSubtitlesMenu.value) {
            showSubtitlesMenu.value = false;
        } else if (showSettings.value) {
            showSettings.value = false;
        } else if (isFullscreen.value) {
            document.exitFullscreen();
        } else {
            emit('close');
        }
    } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        if (currentChoiceMoment.value && currentChoiceMoment.value.choices?.length > 0 && !isChoiceSelected.value) {
            handleChoice(0);
        } else {
            jumpBack();
        }
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        if (currentChoiceMoment.value && currentChoiceMoment.value.choices?.length > 1 && !isChoiceSelected.value) {
            handleChoice(1);
        } else {
            jumpForward();
        }
    }
};

// Subtitle WebVTT Parser & Cue Management (matching CinemaPlayer)
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
    selectedSubtitleId.value = subId;
    if (subId === 'off') {
        parsedCues.value = [];
        activeCueText.value = '';
        return;
    }

    isFetchingSubtitle.value = true;
    try {
        const res = await fetch(`/stream/subtitles/${subId}`);
        if (res.ok) {
            const content = await res.text();
            parsedCues.value = parseWebVTTContent(content);
            if (videoRef.value) {
                updateActiveCue(videoRef.value.currentTime);
            }
        }
    } catch (e) {
        console.error('Failed to load subtitle track:', e);
    } finally {
        isFetchingSubtitle.value = false;
    }
};

const updateActiveCue = (timeSec: number) => {
    if (!showSubtitles.value || selectedSubtitleId.value === 'off' || !parsedCues.value.length) {
        activeCueText.value = '';
        return;
    }

    const adjustedTime = timeSec + subtitleDelay.value;
    const active = parsedCues.value.find(c => adjustedTime >= c.start && adjustedTime <= c.end);
    activeCueText.value = active ? active.text : '';
};

const fetchEpisodeSubtitles = async () => {
    try {
        const res = await fetch(`/api/subtitles/for-media?type=episode&id=${activeEpisodeId.value}`);
        if (res.ok) {
            const data = await res.json();
            if (data.subtitles && Array.isArray(data.subtitles)) {
                availableSubtitles.value = data.subtitles;

                // Auto-select Arabic if in RTL mode, otherwise match default or English
                const target = availableSubtitles.value.find(s => isRTL.value ? s.language === 'ar' : s.language === 'en')
                    || availableSubtitles.value.find(s => s.is_default)
                    || availableSubtitles.value[0];

                if (target) {
                    await loadSubtitleTrack(target.id);
                }
            }
        }
    } catch (e) {
        console.error('Failed to fetch subtitles:', e);
    }
};

// Web Audio API (Audio Delay Offset)
const initAudioContext = () => {
    if (!videoRef.value || audioCtx) return;
    try {
        const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioContextClass) return;
        audioCtx = new AudioContextClass();
        if (audioCtx.state === 'suspended') {
            audioCtx.resume().catch(() => {});
        }
        sourceNode = audioCtx.createMediaElementSource(videoRef.value);
        delayNode = audioCtx.createDelay(5.0);
        delayNode.delayTime.value = Math.max(0, audioDelayMs.value / 1000.0);
        sourceNode.connect(delayNode);
        delayNode.connect(audioCtx.destination);
    } catch {}
};

const updateAudioDelay = (val: number) => {
    audioDelayMs.value = val;
    initAudioContext();
    if (delayNode && audioCtx) {
        delayNode.delayTime.setTargetAtTime(Math.max(0, val / 1000.0), audioCtx.currentTime, 0.05);
    }
};

const formatTime = (ms: number) => {
    const totalSec = Math.floor(ms / 1000);
    const h = Math.floor(totalSec / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    if (h > 0) {
        return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    }
    return `${m}:${s.toString().padStart(2, '0')}`;
};

onMounted(() => {
    initEngine();
    window.addEventListener('keydown', onKeyDown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeyDown);
    if (timerId) clearTimeout(timerId);
    clearTimeout(controlsTimer);
    if (audioCtx) {
        audioCtx.close().catch(() => {});
    }
});
</script>

<template>
    <!-- Always LTR layout matching CinemaPlayer, even when Arabic locale is active -->
    <div
        ref="containerRef"
        dir="ltr"
        @mousemove="onMouseMove"
        class="fixed inset-0 z-[100] bg-black text-white select-none overflow-hidden flex flex-col font-sans"
    >
        <!-- LOADING SPINNER -->
        <div v-if="isLoadingAssets" class="absolute inset-0 z-50 flex flex-col items-center justify-center bg-black/95 backdrop-blur-md">
            <div class="relative w-16 h-16 mb-4">
                <div class="absolute inset-0 rounded-full border-4 border-red-500/20 animate-ping"></div>
                <div class="w-16 h-16 rounded-full border-4 border-t-red-500 border-r-transparent border-b-white/20 border-l-transparent animate-spin"></div>
            </div>
            <h3 class="text-lg font-black tracking-widest uppercase text-red-500 font-mono">
                {{ isRTL ? 'تحميل التجربة التفاعلية...' : 'Loading Interactive Experience...' }}
            </h3>
            <p class="text-xs text-slate-400 mt-1">Black Mirror: Bandersnatch</p>
        </div>

        <!-- ERROR STATE -->
        <div v-if="videoError" class="absolute inset-0 z-50 flex flex-col items-center justify-center bg-black/95 p-6 text-center">
            <div class="w-16 h-16 rounded-full bg-red-600/20 text-red-500 flex items-center justify-center mb-4 border border-red-500/30">
                <AlertTriangle class="w-8 h-8" />
            </div>
            <h3 class="text-lg font-bold text-white mb-2">{{ isRTL ? 'فشل التشغيل' : 'Playback Failed' }}</h3>
            <p class="text-xs text-slate-400 mb-6 max-w-md">{{ videoError }}</p>
            <div class="flex items-center gap-3">
                <button
                    @click="initEngine"
                    class="px-5 py-2.5 rounded-xl bg-red-600 hover:bg-red-500 text-white font-bold text-xs flex items-center gap-2 cursor-pointer shadow-lg shadow-red-600/40"
                >
                    <RefreshCw class="w-4 h-4" />
                    <span>{{ isRTL ? 'إعادة المحاولة' : 'Retry' }}</span>
                </button>
                <button
                    @click="emit('close')"
                    class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 font-bold text-xs cursor-pointer"
                >
                    {{ isRTL ? 'إغلاق' : 'Close' }}
                </button>
            </div>
        </div>

        <!-- BUFFERING SPINNER -->
        <div v-if="isBuffering && !isLoadingAssets" class="absolute inset-0 z-30 flex items-center justify-center pointer-events-none bg-black/30">
            <div class="w-12 h-12 rounded-full border-3 border-t-red-500 border-white/20 animate-spin"></div>
        </div>

        <!-- TOP BAR (AUTO-HIDES ON IDLE) - Top selector removed as requested -->
        <transition name="fade">
            <div
                v-if="showControls"
                class="absolute top-0 inset-x-0 z-40 p-4 bg-gradient-to-b from-black/90 via-black/50 to-transparent flex items-center justify-between pointer-events-auto"
            >
                <div class="flex items-center gap-3">
                    <button
                        @click="emit('close')"
                        class="px-3.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition-all flex items-center gap-2 cursor-pointer backdrop-blur-md border border-white/15 active:scale-95"
                    >
                        <ArrowLeft class="w-4 h-4" />
                        <span>{{ isRTL ? 'العودة للمكتبة' : 'Back to Library' }}</span>
                    </button>

                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-md bg-red-600/30 border border-red-500/50 text-red-400 text-[10px] font-mono font-black uppercase tracking-wider animate-pulse">
                            INTERACTIVE
                        </span>
                        <h2 class="font-extrabold text-sm sm:text-base text-white tracking-wide truncate max-w-xs sm:max-w-md">
                            Black Mirror: Bandersnatch
                        </h2>
                        <span class="text-xs font-mono px-2 py-0.5 rounded bg-white/10 text-slate-300 border border-white/10">
                            Chapter {{ currentChapterName }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Reset Story (Instant, Reliable) -->
                    <button
                        @click="resetStory"
                        class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-red-500/20 border border-white/15 hover:border-red-500/40 text-slate-300 hover:text-red-300 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                        :title="isRTL ? 'إعادة ضبط مسار القصة وبدء التجربة من جديد' : 'Reset Story Decisions to Beginning'"
                    >
                        <RotateCcw class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">{{ isRTL ? 'إعادة ضبط القصة' : 'Reset Story' }}</span>
                    </button>

                    <!-- Settings (Audio Sync) -->
                    <button
                        @click="showSettings = !showSettings; showSubtitlesMenu = false;"
                        class="p-2 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 text-slate-300 hover:text-white text-xs transition-all cursor-pointer"
                        :class="showSettings ? 'bg-red-600 text-white border-red-500' : ''"
                        title="Audio Delay"
                    >
                        <Sliders class="w-4 h-4" />
                    </button>

                    <!-- Fullscreen -->
                    <button
                        @click="toggleFullscreen"
                        class="p-2 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 text-slate-300 hover:text-white text-xs transition-all cursor-pointer"
                        :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen (F)'"
                    >
                        <Minimize2 v-if="isFullscreen" class="w-4 h-4" />
                        <Maximize2 v-else class="w-4 h-4" />
                    </button>

                    <!-- Close -->
                    <button
                        @click="emit('close')"
                        class="p-2 rounded-xl bg-white/10 hover:bg-rose-500/30 border border-white/15 hover:border-rose-500/50 text-slate-300 hover:text-rose-200 text-xs transition-all cursor-pointer"
                        title="Close (Esc)"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </transition>

        <!-- SETTINGS POPUP (AUDIO SYNC) -->
        <transition name="fade">
            <div
                v-if="showSettings"
                class="absolute top-16 right-4 z-50 p-4 rounded-2xl bg-black/90 border border-white/20 backdrop-blur-xl text-xs space-y-3 w-72 shadow-2xl"
            >
                <div class="flex items-center justify-between border-b border-white/10 pb-2">
                    <span class="font-bold text-white flex items-center gap-1.5">
                        <Sliders class="w-3.5 h-3.5 text-red-500" />
                        <span>{{ isRTL ? 'تأخير مزامنة الصوت' : 'Audio Sync Delay' }}</span>
                    </span>
                    <button @click="showSettings = false" class="text-slate-400 hover:text-white cursor-pointer">
                        <X class="w-3.5 h-3.5" />
                    </button>
                </div>

                <div>
                    <div class="flex items-center justify-between text-[11px] mb-1 font-mono">
                        <span class="text-slate-400">{{ isRTL ? 'تأخير الصوت:' : 'Audio Delay:' }}</span>
                        <span class="text-red-400 font-bold">{{ audioDelayMs }}ms</span>
                    </div>
                    <input
                        type="range"
                        min="-2000"
                        max="2000"
                        step="50"
                        :value="audioDelayMs"
                        @input="updateAudioDelay(Number(($event.target as HTMLInputElement).value))"
                        class="w-full accent-red-500 cursor-pointer"
                    />
                </div>

                <div class="flex items-center justify-between pt-1">
                    <button
                        @click="updateAudioDelay(0)"
                        class="px-2 py-1 rounded bg-white/10 hover:bg-white/20 text-[10px] text-slate-300 cursor-pointer"
                    >
                        {{ isRTL ? 'إعادة ضبط' : 'Reset Delay' }}
                    </button>
                    <span class="text-[9px] text-slate-500 font-mono">Direct Range Streaming</span>
                </div>
            </div>
        </transition>

        <!-- SUBTITLE POPUP MENU (matching CinemaPlayer) -->
        <transition name="fade">
            <div
                v-if="showSubtitlesMenu"
                class="absolute bottom-16 right-4 z-50 text-left w-84 max-w-[90vw] bg-slate-950/95 border border-white/15 rounded-2xl p-3.5 shadow-2xl backdrop-blur-xl flex flex-col gap-3 max-h-[80vh] overflow-y-auto custom-scrollbar"
            >
                <div class="flex items-center justify-between border-b border-white/10 pb-2">
                    <span class="text-xs font-bold text-white flex items-center gap-1.5">
                        <MessageSquare class="w-3.5 h-3.5 text-purple-400" />
                        {{ isRTL ? 'مسارات الترجمة' : 'Subtitle Tracks' }}
                    </span>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] text-slate-400">{{ availableSubtitles.length }} {{ isRTL ? 'متوفر' : 'available' }}</span>
                        <button @click="showSubtitlesMenu = false" class="text-slate-400 hover:text-white cursor-pointer">
                            <X class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Tracks List -->
                <div class="flex flex-col gap-1 max-h-40 overflow-y-auto custom-scrollbar">
                    <button
                        @click="loadSubtitleTrack('off')"
                        class="px-3 py-2 rounded-xl text-xs font-semibold flex items-center justify-between transition-all cursor-pointer"
                        :class="selectedSubtitleId === 'off' ? 'bg-purple-600 text-white' : 'text-slate-300 hover:bg-white/10'"
                    >
                        <span>{{ isRTL ? 'إيقاف الترجمة' : 'Off' }}</span>
                        <Check v-if="selectedSubtitleId === 'off'" class="w-3.5 h-3.5" />
                    </button>

                    <button
                        v-for="sub in availableSubtitles"
                        :key="sub.id"
                        @click="loadSubtitleTrack(sub.id)"
                        class="px-3 py-2 rounded-xl text-xs font-semibold flex items-center justify-between transition-all cursor-pointer"
                        :class="selectedSubtitleId === sub.id ? 'bg-purple-600 text-white' : 'text-slate-300 hover:bg-white/10'"
                    >
                        <div class="flex items-center gap-1.5 truncate">
                            <span class="uppercase text-[10px] px-1 py-0.5 rounded bg-black/40">{{ sub.language || 'CC' }}</span>
                            <span class="truncate">{{ sub.language_name || 'Subtitle' }}</span>
                            <span v-if="sub.is_default" class="text-[9px] px-1.5 py-0.5 rounded bg-white/15 text-slate-300 font-mono">Default</span>
                        </div>
                        <Check v-if="selectedSubtitleId === sub.id" class="w-3.5 h-3.5 shrink-0" />
                    </button>
                </div>

                <!-- Subtitle Size, Font & Sync Options (matching CinemaPlayer) -->
                <div v-if="selectedSubtitleId !== 'off'" class="border-t border-white/10 pt-2.5 flex flex-col gap-2.5">
                    <!-- Font Size -->
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-slate-400">{{ isRTL ? 'حجم الخط' : 'Font Size' }}</span>
                        <div class="flex items-center gap-1 bg-white/5 p-0.5 rounded-lg border border-white/10">
                            <button
                                v-for="size in (['sm', 'md', 'lg', 'xl'] as const)"
                                :key="size"
                                @click="setSubtitleFontSize(size)"
                                class="px-2 py-0.5 rounded text-[10px] font-bold uppercase transition-all cursor-pointer"
                                :class="subtitleFontSize === size ? 'bg-purple-600 text-white' : 'text-slate-400 hover:text-white'"
                            >
                                {{ size }}
                            </button>
                        </div>
                    </div>

                    <!-- Font Family / Style (Cairo, Jakarta, System) -->
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-slate-400">{{ isRTL ? 'نوع الخط' : 'Font Style' }}</span>
                        <div class="flex items-center gap-1 bg-white/5 p-0.5 rounded-lg border border-white/10">
                            <button
                                v-for="font in ([
                                    { id: 'cairo', label: 'Cairo' },
                                    { id: 'jakarta', label: 'Jakarta' },
                                    { id: 'system', label: 'System' },
                                ] as const)"
                                :key="font.id"
                                @click="setSubtitleFont(font.id)"
                                class="px-2 py-0.5 rounded text-[10px] font-bold transition-all cursor-pointer"
                                :class="subtitleFont === font.id ? 'bg-purple-600 text-white' : 'text-slate-400 hover:text-white'"
                            >
                                {{ font.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Subtitle Delay Sync -->
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-slate-400">{{ isRTL ? 'مزامنة التوقيت' : 'Timing Sync' }}</span>
                        <div class="flex items-center gap-1.5 font-mono">
                            <button
                                @click="adjustSubtitleDelay(-0.5)"
                                class="w-6 h-6 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-xs font-bold cursor-pointer"
                                title="-0.5s"
                            >
                                -
                            </button>
                            <span class="text-purple-400 font-bold px-1 text-xs">{{ subtitleDelay >= 0 ? `+${subtitleDelay}` : subtitleDelay }}s</span>
                            <button
                                @click="adjustSubtitleDelay(0.5)"
                                class="w-6 h-6 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-xs font-bold cursor-pointer"
                                title="+0.5s"
                            >
                                +
                            </button>
                            <button
                                v-if="subtitleDelay !== 0"
                                @click="adjustSubtitleDelay(-subtitleDelay)"
                                class="text-[10px] text-slate-400 hover:text-white ml-1 cursor-pointer"
                            >
                                {{ isRTL ? 'إعادة' : 'Reset' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- VIDEO CONTAINER -->
        <div class="relative flex-1 w-full h-full flex items-center justify-center bg-black overflow-hidden">
            <video
                ref="videoRef"
                :src="streamUrl"
                class="w-full h-full object-contain cursor-pointer"
                @timeupdate="onTimeUpdate"
                @loadedmetadata="onLoadedMetadata"
                @canplay="onCanPlay"
                @play="onPlay"
                @pause="onPause"
                @waiting="isBuffering = true"
                @playing="isBuffering = false"
                @error="onVideoError"
                @click="togglePlay"
                crossorigin="anonymous"
                playsinline
                preload="auto"
            ></video>

            <!-- SUBTITLE OVERLAY (Dynamic WebVTT with selected Font and Size) -->
            <div
                v-if="showSubtitles && selectedSubtitleId !== 'off' && activeCueText"
                class="absolute inset-x-0 z-30 flex items-center justify-center px-4 sm:px-8 pointer-events-none transition-all duration-150 ease-out"
                :style="{
                    bottom: currentChoiceMoment
                        ? '9.5rem'
                        : (showControls ? '5.5rem' : '2.5rem')
                }"
            >
                <div
                    class="subtitle-pill px-4 py-1.5 sm:px-6 sm:py-2.5 rounded-xl bg-black/85 text-white text-center font-bold tracking-normal shadow-2xl backdrop-blur-xs transition-all duration-100 max-w-4xl pointer-events-none border border-white/15"
                    dir="auto"
                    :class="{
                        'text-sm sm:text-base': subtitleFontSize === 'sm',
                        'text-base sm:text-lg': subtitleFontSize === 'md',
                        'text-lg sm:text-2xl': subtitleFontSize === 'lg',
                        'text-xl sm:text-3xl': subtitleFontSize === 'xl',
                    }"
                    :style="{
                        fontFamily: subtitleFontFamily,
                        textShadow: '0 2px 4px rgba(0,0,0,0.95), 0 0 3px #000, 1px 1px 2px #000, -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000',
                        lineHeight: 1.5,
                    }"
                    v-html="activeCueText"
                ></div>
            </div>

            <!-- PLAY / PAUSE LARGE ICON OVERLAY ON PAUSE -->
            <transition name="fade">
                <div
                    v-if="!isPlaying && !isLoadingAssets && !videoError"
                    @click="togglePlay"
                    class="absolute inset-0 flex flex-col items-center justify-center bg-black/40 backdrop-blur-xs cursor-pointer z-20"
                >
                    <div class="w-22 h-22 rounded-full bg-red-600/90 text-white flex items-center justify-center shadow-2xl shadow-red-600/50 hover:scale-110 active:scale-95 transition-all">
                        <Play class="w-10 h-10 fill-current ml-1" />
                    </div>
                    <span class="mt-4 px-4 py-1.5 rounded-full bg-black/70 text-xs font-mono font-bold text-slate-200 border border-white/10">
                        {{ isRTL ? 'انقر للتشغيل / مسافة' : 'Click to Play / Press Space' }}
                    </span>
                </div>
            </transition>

            <!-- INTERACTIVE CHOICE HUD OVERLAY -->
            <transition name="fade">
                <div
                    v-if="currentChoiceMoment && currentChoiceMoment.choices && currentChoiceMoment.choices.length > 0"
                    class="absolute bottom-16 inset-x-0 z-30 flex flex-col items-center px-4 pointer-events-auto"
                >
                    <!-- Choice Caption Question -->
                    <div v-if="choiceCaption" class="mb-4">
                        <span class="px-5 py-2 rounded-full bg-black/90 border border-white/30 backdrop-blur-md text-xs sm:text-sm font-black uppercase tracking-widest text-slate-100 shadow-2xl">
                            {{ choiceCaption }}
                        </span>
                    </div>

                    <!-- STANDARD 2-CHOICE BRANCH -->
                    <div v-if="!isPhoneScene" class="flex items-center justify-center gap-6 sm:gap-12 w-full max-w-2xl mb-4">
                        <button
                            v-for="(c, idx) in currentChoiceMoment.choices"
                            :key="c.id || idx"
                            @click.stop="handleChoice(Number(idx))"
                            :disabled="isChoiceSelected"
                            class="flex-1 py-3.5 sm:py-5 px-6 sm:px-8 rounded-2xl font-extrabold text-sm sm:text-lg tracking-wider uppercase transition-all duration-200 shadow-2xl backdrop-blur-md flex items-center justify-center text-center cursor-pointer active:scale-95"
                            :class="[
                                highlightedChoiceIndex === Number(idx)
                                    ? 'ring-4 ring-red-500 bg-red-600 text-white border-2 border-white scale-105 shadow-red-600/60'
                                    : (isChoiceSelected
                                        ? 'bg-black/40 text-slate-500 border border-white/10 opacity-50 cursor-not-allowed'
                                        : 'bg-black/80 hover:bg-red-600 text-white border-2 border-white/30 hover:border-red-500 hover:scale-105')
                            ]"
                        >
                            <span class="flex items-center gap-2">
                                <span v-if="highlightedChoiceIndex === Number(idx)" class="w-2.5 h-2.5 rounded-full bg-white animate-ping"></span>
                                {{ c.text || c.id }}
                            </span>
                        </button>
                    </div>

                    <!-- PHONE SCENE 5-DIGIT KEYPAD -->
                    <div v-else class="flex flex-col items-center bg-black/90 p-6 rounded-3xl border border-white/20 backdrop-blur-xl shadow-2xl mb-4 max-w-sm w-full">
                        <div class="text-xs font-mono text-slate-300 mb-3 font-bold">
                            {{ isRTL ? 'أدخل رمز الهاتف المكون من 5 أرقام:' : 'Enter 5-digit Telephone Code:' }}
                        </div>
                        <div class="flex items-center gap-3 mb-4 font-mono text-2xl font-bold">
                            <span
                                v-for="i in 5"
                                :key="i"
                                class="w-9 h-12 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center text-white"
                            >
                                {{ selectedDigits[i - 1] || '-' }}
                            </span>
                        </div>
                        <div class="grid grid-cols-5 gap-2 w-full">
                            <button
                                v-for="d in ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0']"
                                :key="d"
                                @click.stop="selectDigit(d)"
                                :disabled="isChoiceSelected"
                                class="h-10 rounded-xl bg-white/10 hover:bg-red-600 border border-white/15 hover:border-red-500 font-bold font-mono text-base transition-all cursor-pointer flex items-center justify-center active:scale-90"
                            >
                                {{ d }}
                            </button>
                        </div>
                        <button
                            @click.stop="backspaceDigit"
                            class="mt-3 text-[11px] text-slate-400 hover:text-white cursor-pointer"
                        >
                            {{ isRTL ? 'مسح الرقم الأخير' : 'Backspace' }}
                        </button>
                    </div>

                    <!-- COUNTDOWN TIMER PROGRESS BAR (NETFLIX STYLE) -->
                    <div class="w-full max-w-xl h-2 bg-white/20 rounded-full overflow-hidden backdrop-blur-sm">
                        <div
                            class="h-full bg-gradient-to-r from-red-600 via-rose-500 to-white transition-all duration-100 ease-linear rounded-full"
                            :style="{ width: `${choiceProgress}%` }"
                        ></div>
                    </div>
                </div>
            </transition>
        </div>

        <!-- BOTTOM CONTROLS BAR (AUTO-HIDES ON IDLE) -->
        <transition name="fade">
            <div
                v-if="showControls"
                class="absolute bottom-0 inset-x-0 z-40 p-4 bg-gradient-to-t from-black/95 via-black/60 to-transparent flex items-center justify-between pointer-events-auto"
            >
                <div class="flex items-center gap-3">
                    <!-- Play / Pause -->
                    <button
                        @click="togglePlay"
                        class="w-10 h-10 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all cursor-pointer active:scale-90"
                    >
                        <Pause v-if="isPlaying" class="w-5 h-5 fill-current" />
                        <Play v-else class="w-5 h-5 fill-current ml-0.5" />
                    </button>

                    <!-- Jump Back (Previous Scene) -->
                    <button
                        @click="jumpBack"
                        class="p-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white transition-all cursor-pointer active:scale-90"
                        :title="isRTL ? 'المشهد / القرار السابق (السهم الأيسر)' : 'Previous Scene / Decision (Left Arrow)'"
                    >
                        <SkipBack class="w-4 h-4" />
                    </button>

                    <!-- Jump Forward (Next Scene) -->
                    <button
                        @click="jumpForward"
                        class="p-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white transition-all cursor-pointer active:scale-90"
                        :title="isRTL ? 'المشهد / القرار التالي (السهم الأيمن)' : 'Next Scene / Decision (Right Arrow)'"
                    >
                        <SkipForward class="w-4 h-4" />
                    </button>

                    <!-- Volume Control -->
                    <div class="flex items-center gap-2 ml-2">
                        <button
                            @click="toggleMute"
                            class="p-2 text-slate-300 hover:text-white cursor-pointer"
                        >
                            <VolumeX v-if="isMuted || volume === 0" class="w-5 h-5" />
                            <Volume2 v-else class="w-5 h-5" />
                        </button>
                        <input
                            type="range"
                            min="0"
                            max="1"
                            step="0.05"
                            :value="isMuted ? 0 : volume"
                            @input="onVolumeChange(Number(($event.target as HTMLInputElement).value))"
                            class="w-20 accent-red-500 cursor-pointer hidden sm:block"
                        />
                    </div>

                    <!-- Time Display -->
                    <div class="font-mono text-xs text-slate-300 ml-2">
                        <span>{{ formatTime(currentMsDisplay) }}</span>
                        <span class="text-slate-500 mx-1">/</span>
                        <span class="text-slate-500">{{ formatTime(durationMsDisplay) }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Subtitles Menu Button (matching CinemaPlayer) -->
                    <div class="relative">
                        <button
                            @click="showSubtitlesMenu = !showSubtitlesMenu; showSettings = false;"
                            class="px-3 py-1.5 rounded-xl border text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                            :class="selectedSubtitleId !== 'off' ? 'bg-purple-600/30 border-purple-500 text-purple-200 shadow-lg shadow-purple-600/20' : 'bg-white/10 border-white/15 text-slate-400 hover:text-white'"
                            title="Subtitles & Audio (C)"
                        >
                            <MessageSquare class="w-3.5 h-3.5" />
                            <span>CC</span>
                        </button>
                    </div>

                    <!-- Fullscreen -->
                    <button
                        @click="toggleFullscreen"
                        class="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white cursor-pointer active:scale-90"
                        :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen (F)'"
                    >
                        <Minimize2 v-if="isFullscreen" class="w-4 h-4" />
                        <Maximize2 v-else class="w-4 h-4" />
                    </button>
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

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
}
</style>