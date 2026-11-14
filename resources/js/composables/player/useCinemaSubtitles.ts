import { ref, computed, type Ref } from 'vue';

export interface SubtitleItem {
    id: number | string;
    language: string;
    language_name: string;
    format: string;
    is_embedded?: boolean;
    file_path?: string;
    is_default?: boolean | number;
    source_type?: 'external' | 'ai_translated' | 'embedded';
    url?: string;
}

export interface CueItem {
    start: number;
    end: number;
    text: string;
}

export interface UseCinemaSubtitlesOptions {
    onToast?: (message: string) => void;
}

export function useCinemaSubtitles(options?: UseCinemaSubtitlesOptions) {
    const availableSubtitles = ref<SubtitleItem[]>([]);
    const selectedSubtitleId = ref<number | string>('off');
    const subtitleDelay = ref<number>(0);
    const parsedCues = ref<CueItem[]>([]);
    const activeCueText = ref<string>('');
    const isFetchingSubtitle = ref<boolean>(false);
    const isTranslatingSubtitle = ref<boolean>(false);
    let lastKnownTime = 0;

    const subtitleFontSize = ref<'sm' | 'md' | 'lg' | 'xl'>(
        (localStorage.getItem('cinema_subtitle_size') as any) || 'md'
    );
    const subtitleFont = ref<'cairo' | 'jakarta' | 'system'>(
        (localStorage.getItem('cinema_subtitle_font') as any) || 'cairo'
    );

    const subtitleFontFamily = computed(() => {
        switch (subtitleFont.value) {
            case 'cairo':
                return "'Cairo', system-ui, sans-serif";
            case 'jakarta':
                return "'Plus Jakarta Sans', system-ui, sans-serif";
            case 'system':
            default:
                return "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        }
    });

    const setSubtitleFont = (fontKey: 'cairo' | 'jakarta' | 'system') => {
        subtitleFont.value = fontKey;
        try {
            localStorage.setItem('cinema_subtitle_font', fontKey);
        } catch {}
    };

    const setSubtitleFontSize = (sizeKey: 'sm' | 'md' | 'lg' | 'xl') => {
        subtitleFontSize.value = sizeKey;
        try {
            localStorage.setItem('cinema_subtitle_size', sizeKey);
        } catch {}
    };

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

    const updateActiveCue = (timeSec: number) => {
        lastKnownTime = timeSec;
        if (selectedSubtitleId.value === 'off' || !parsedCues.value.length) {
            activeCueText.value = '';
            return;
        }

        // Subtitle delay is applied to the time search
        const adjustedTime = timeSec + subtitleDelay.value;
        const active = parsedCues.value.find(c => adjustedTime >= c.start && adjustedTime <= c.end);
        activeCueText.value = active ? active.text : '';
    };

    const loadSubtitleTrack = async (subId: number | string, isManualSelection: boolean = true) => {
        if (subId === 'off') {
            selectedSubtitleId.value = 'off';
            parsedCues.value = [];
            activeCueText.value = '';
            if (isManualSelection) {
                try {
                    localStorage.setItem('cinema_subtitle_pref', 'off');
                } catch {}
            }
            return;
        }

        selectedSubtitleId.value = subId;
        isFetchingSubtitle.value = true;
        try {
            const res = await fetch(`/stream/subtitles/${subId}`);
            const content = await res.text();
            parsedCues.value = parseWebVTTContent(content);
            updateActiveCue(lastKnownTime);
            // Clear explicit 'off' preference only on manual selection; never pollute with English fallback
            if (isManualSelection) {
                try {
                    localStorage.setItem('cinema_subtitle_pref', 'auto');
                } catch {}
            }
        } catch (e) {
            console.error('Failed to load subtitle track:', e);
        } finally {
            isFetchingSubtitle.value = false;
        }
    };

    const adjustSubtitleDelay = (delta: number) => {
        subtitleDelay.value = parseFloat((subtitleDelay.value + delta).toFixed(2));
        updateActiveCue(lastKnownTime);
    };

    const resetSubtitleDelay = () => {
        subtitleDelay.value = 0;
        updateActiveCue(lastKnownTime);
    };

    const isArabicSubtitle = (s: SubtitleItem): boolean => {
        const lang = (s.language || '').toLowerCase().trim();
        const langName = (s.language_name || '').toLowerCase();
        return lang === 'ar' || lang === 'ara' || lang === 'arabic' || langName.includes('arab') || langName.includes('عرب');
    };

    const isEnglishSubtitle = (s: SubtitleItem): boolean => {
        const lang = (s.language || '').toLowerCase().trim();
        const langName = (s.language_name || '').toLowerCase();
        return lang === 'en' || lang === 'eng' || lang === 'english' || langName.includes('eng');
    };

    const findBestSubtitle = (tracks: SubtitleItem[]): SubtitleItem | null => {
        if (!tracks || tracks.length === 0) return null;

        let savedPref: string | null = null;
        try {
            savedPref = localStorage.getItem('cinema_subtitle_pref');
        } catch {}

        // Only stay silent if the user explicitly turned subtitles Off
        if (savedPref === 'off') {
            return null;
        }

        // 1. Strict Arabic priority (prefer external/downloaded or AI-translated Arabic first)
        const arabicExternal = tracks.find(s => isArabicSubtitle(s) && !s.is_embedded);
        if (arabicExternal) return arabicExternal;

        const arabicTrack = tracks.find(s => isArabicSubtitle(s));
        if (arabicTrack) return arabicTrack;

        // 2. English fallback priority when Arabic is unavailable
        const englishExternal = tracks.find(s => isEnglishSubtitle(s) && !s.is_embedded);
        if (englishExternal) return englishExternal;

        const englishTrack = tracks.find(s => isEnglishSubtitle(s));
        if (englishTrack) return englishTrack;

        // 3. Default flag or first track
        const defaultTrack = tracks.find(s => s.is_default);
        if (defaultTrack) return defaultTrack;

        return tracks[0];
    };

    const autoSelectAndLoadSubtitle = async (tracks: SubtitleItem[]) => {
        const best = findBestSubtitle(tracks);
        if (best) {
            await loadSubtitleTrack(best.id, false);
        } else {
            selectedSubtitleId.value = 'off';
            parsedCues.value = [];
            activeCueText.value = '';
        }
    };

    const fetchSubtitlesForMedia = async (type: 'movie' | 'episode', id: number | string, forceSelectId?: number | string) => {
        try {
            const res = await fetch(`/api/subtitles/for-media?type=${type}&id=${id}`);
            const data = await res.json();
            if (data.subtitles && Array.isArray(data.subtitles)) {
                availableSubtitles.value = data.subtitles;

                // Auto-select newly downloaded or translated subtitle immediately
                if (forceSelectId !== undefined && forceSelectId !== null && forceSelectId !== '') {
                    const matched = data.subtitles.find((s: SubtitleItem) => String(s.id) === String(forceSelectId));
                    await loadSubtitleTrack(matched ? matched.id : forceSelectId, true);
                    return;
                }

                if (selectedSubtitleId.value !== 'off') {
                    const stillExists = data.subtitles.some((s: SubtitleItem) => String(s.id) === String(selectedSubtitleId.value));
                    if (!stillExists) {
                        await autoSelectAndLoadSubtitle(data.subtitles);
                    }
                } else {
                    let savedPref: string | null = null;
                    try {
                        savedPref = localStorage.getItem('cinema_subtitle_pref');
                    } catch {}
                    if (savedPref !== 'off') {
                        await autoSelectAndLoadSubtitle(data.subtitles);
                    }
                }
            }
        } catch (e) {
            console.warn('Failed to fetch subtitles:', e);
        }
    };

    const translateSubtitleToArabic = async (
        mediaItem: any,
        isEpisode: boolean,
        sourceSubId?: number | string
    ): Promise<boolean> => {
        if (!mediaItem || isTranslatingSubtitle.value) return false;

        isTranslatingSubtitle.value = true;
        const mediaId = mediaItem?.watchable_id || mediaItem?.id;
        const mediaType = isEpisode ? 'episode' : 'movie';

        if (options?.onToast) {
            options.onToast('Translating English subtitle to Arabic via AI...');
        }

        try {
            const payload: Record<string, any> = {
                media_id: mediaId,
                media_type: mediaType,
            };
            if (sourceSubId && sourceSubId !== 'off') {
                payload.subtitle_id = sourceSubId;
            }

            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
            const res = await fetch('/api/subtitles/translate-arabic', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json();
            if (!res.ok || data.status === 'error') {
                throw new Error(data.message || 'Failed to generate Arabic translation');
            }

            if (data.subtitle) {
                const newSub = data.subtitle;
                const existingIdx = availableSubtitles.value.findIndex(s => String(s.id) === String(newSub.id));
                if (existingIdx >= 0) {
                    availableSubtitles.value[existingIdx] = newSub;
                } else {
                    availableSubtitles.value.push(newSub);
                }

                // Immediately activate the newly translated Arabic track
                await loadSubtitleTrack(newSub.id);

                if (options?.onToast) {
                    options.onToast('Arabic subtitle generated & activated successfully!');
                }
                return true;
            }
            return false;
        } catch (e: any) {
            const errMsg = e.message || 'Arabic translation failed';
            if (options?.onToast) {
                options.onToast(errMsg);
            }
            return false;
        } finally {
            isTranslatingSubtitle.value = false;
        }
    };

    return {
        availableSubtitles,
        selectedSubtitleId,
        subtitleDelay,
        parsedCues,
        activeCueText,
        isFetchingSubtitle,
        isTranslatingSubtitle,
        subtitleFontSize,
        subtitleFont,
        subtitleFontFamily,
        setSubtitleFont,
        setSubtitleFontSize,
        loadSubtitleTrack,
        updateActiveCue,
        adjustSubtitleDelay,
        resetSubtitleDelay,
        autoSelectAndLoadSubtitle,
        fetchSubtitlesForMedia,
        translateSubtitleToArabic,
    };
}

