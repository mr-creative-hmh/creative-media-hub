import { ref, watch, onBeforeUnmount, type Ref } from 'vue';

export interface UseCinemaAudioPipelineOptions {
    itemKey?: Ref<string>;
    onToast?: (message: string) => void;
}

export function useCinemaAudioPipeline(options?: UseCinemaAudioPipelineOptions) {
    const vocalBoost = ref<boolean>(false);
    const bassBoost = ref<boolean>(false);
    const nightMode = ref<boolean>(false);
    const audioDelayMs = ref<number>(0);
    const isAudioPipelineInitialized = ref<boolean>(false);

    let audioCtx: AudioContext | null = null;
    let sourceNode: MediaElementAudioSourceNode | null = null;
    let voiceFilterNode: BiquadFilterNode | null = null;
    let bassFilterNode: BiquadFilterNode | null = null;
    let trebleFilterNode: BiquadFilterNode | null = null;
    let compressorNode: DynamicsCompressorNode | null = null;
    let delayNode: DelayNode | null = null;
    let gainNode: GainNode | null = null;

    // Load initial preferences
    try {
        vocalBoost.value = localStorage.getItem('cinema_vocal_boost') === 'true';
        bassBoost.value = localStorage.getItem('cinema_bass_boost') === 'true';
        nightMode.value = localStorage.getItem('cinema_night_mode') === 'true';
    } catch {}

    const initAudioPipeline = (videoEl: HTMLVideoElement | null) => {
        if (isAudioPipelineInitialized.value || !videoEl) return;
        try {
            const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
            if (!AudioContextClass) return;

            audioCtx = new AudioContextClass();
            if (audioCtx.state === 'suspended') {
                audioCtx.resume().catch(() => {});
            }

            sourceNode = audioCtx.createMediaElementSource(videoEl);

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

            // Single source of truth for audio-video delay compensation
            delayNode = audioCtx.createDelay(5.0);
            delayNode.delayTime.value = Math.max(0, audioDelayMs.value / 1000);

            gainNode = audioCtx.createGain();
            gainNode.gain.value = 1.0;

            sourceNode.connect(voiceFilterNode);
            voiceFilterNode.connect(bassFilterNode);
            bassFilterNode.connect(trebleFilterNode);
            trebleFilterNode.connect(compressorNode);
            compressorNode.connect(delayNode);
            delayNode.connect(gainNode);
            gainNode.connect(audioCtx.destination);

            isAudioPipelineInitialized.value = true;
        } catch (e) {
            console.warn('Web Audio pipeline initialization bypassed:', e);
        }
    };

    const resumeAudioContext = () => {
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume().catch(() => {});
        }
    };

    const updateAudioFilters = () => {
        if (!isAudioPipelineInitialized.value) return;
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

    const toggleVocalBoost = () => {
        vocalBoost.value = !vocalBoost.value;
        try {
            localStorage.setItem('cinema_vocal_boost', String(vocalBoost.value));
        } catch {}
        updateAudioFilters();
    };

    const toggleBassBoost = () => {
        bassBoost.value = !bassBoost.value;
        try {
            localStorage.setItem('cinema_bass_boost', String(bassBoost.value));
        } catch {}
        updateAudioFilters();
    };

    const toggleNightMode = () => {
        nightMode.value = !nightMode.value;
        try {
            localStorage.setItem('cinema_night_mode', String(nightMode.value));
        } catch {}
        updateAudioFilters();
    };

    const setAudioDelay = (delayVal: number) => {
        audioDelayMs.value = delayVal;
        if (options?.itemKey?.value) {
            try {
                localStorage.setItem(`audio_sync_${options.itemKey.value}`, String(delayVal));
            } catch {}
        }

        // Apply via Web Audio DelayNode in real-time with smooth parameter transition
        if (delayNode && audioCtx) {
            if (audioCtx.state === 'suspended') {
                audioCtx.resume().catch(() => {});
            }
            const sec = Math.max(0, Math.min(5.0, delayVal / 1000));
            delayNode.delayTime.setTargetAtTime(sec, audioCtx.currentTime, 0.05);
        }
    };

    const nudgeAudioDelay = (deltaMs: number) => {
        const nextVal = Math.min(3000, Math.max(-3000, audioDelayMs.value + deltaMs));
        setAudioDelay(nextVal);
    };

    const resetAudioDelay = () => {
        setAudioDelay(0);
    };

    const setVolumeGain = (vol: number) => {
        if (gainNode) {
            gainNode.gain.value = Math.max(0, Math.min(1, vol));
        }
    };

    onBeforeUnmount(() => {
        if (audioCtx) {
            try {
                audioCtx.close().catch(() => {});
            } catch {}
            audioCtx = null;
        }
        isAudioPipelineInitialized.value = false;
    });

    return {
        vocalBoost,
        bassBoost,
        nightMode,
        audioDelayMs,
        isAudioPipelineInitialized,
        initAudioPipeline,
        resumeAudioContext,
        updateAudioFilters,
        toggleVocalBoost,
        toggleBassBoost,
        toggleNightMode,
        setAudioDelay,
        nudgeAudioDelay,
        resetAudioDelay,
        setVolumeGain,
    };
}

