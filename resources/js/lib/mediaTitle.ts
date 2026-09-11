/**
 * Standardized media and episode title formatting across Creative Media Hub.
 *
 * Pattern:
 * "TV show [name] - Season [Number] - Episode [Number] - [Episode title if available]"
 * If episode title not available:
 * "TV show [name] - Season [Number] - Episode [Number]"
 */

export interface EpisodeTitleOptions {
    isRTL?: boolean;
    includeSeriesName?: boolean;
    fallbackSeriesName?: string;
}

/**
 * Checks if a title string is a generic placeholder like "Episode 1", "Ep 1", "الحلقة 1", "S01E01".
 */
export function isGenericEpisodeTitle(title?: string | null): boolean {
    if (!title) return true;
    const clean = title.trim();
    if (!clean) return true;
    if (/^(?:Episode|الحلقة|Ep|Part)\s*\d+$/i.test(clean)) return true;
    if (/^S\d+\s*E\d+$/i.test(clean)) return true;
    if (/^(?:Season|الموسم)\s*\d+\s*(?:[-:]*\s*)?(?:Episode|الحلقة)\s*\d+$/i.test(clean)) return true;
    return false;
}

/**
 * Sanitizes and extracts the pure episode title, stripping any accidental duplicate
 * series names, season/episode prefixes, or generic labels.
 */
export function extractPureEpisodeTitle(candidate?: string | null, seriesNames: string[] = []): string {
    if (!candidate) return '';
    let clean = candidate.trim();
    if (!clean) return '';

    // 1. Detect and collapse exact mirrored duplicate string (e.g. "X - Season 1 - Episode 10 - X - Season 1 - Episode 10")
    for (const sep of [' - ', ' : ', ' ']) {
        const parts = clean.split(sep);
        if (parts.length >= 2 && parts.length % 2 === 0) {
            const firstHalf = parts.slice(0, parts.length / 2).join(sep).trim();
            const secondHalf = parts.slice(parts.length / 2).join(sep).trim();
            if (firstHalf.toLowerCase() === secondHalf.toLowerCase()) {
                clean = firstHalf;
                break;
            }
        }
    }

    // 2. Check pattern: .*?(Season|الموسم)\s*\d+.*?[-:]*(Episode|الحلقة)\s*\d+[\s\-:]*(.*)$
    const seMatch = clean.match(/(?:Season|الموسم)\s*\d+.*?[-:]*(?:Episode|الحلقة)\s*\d+[\s\-:]*(.*)$/i);
    if (seMatch) {
        clean = seMatch[1] ? seMatch[1].trim() : '';
    } else {
        // 3. Check pattern: .*?S\d+\s*E\d+[\s\-:]*(.*)$
        const sxxExxMatch = clean.match(/S\d+\s*E\d+[\s\-:]*(.*)$/i);
        if (sxxExxMatch) {
            clean = sxxExxMatch[1] ? sxxExxMatch[1].trim() : '';
        }
    }

    // 4. Strip known series names from the beginning if still present
    for (const sName of seriesNames) {
        if (!sName) continue;
        const esc = sName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        clean = clean.replace(new RegExp('^' + esc + '[\\s\\-:]*', 'i'), '').trim();
    }

    // 5. Strip leading & trailing dashes or colons
    clean = clean.replace(/^[\s\-:]+/, '').replace(/[\s\-:]+$/, '').trim();

    if (isGenericEpisodeTitle(clean)) return '';
    return clean;
}

/**
 * Extracts a clean episode title, returning empty string if it's generic.
 */
export function getCleanEpisodeTitle(item: any, isRTL: boolean = false): string {
    if (!item) return '';

    const seriesNames = [
        item.series?.title, item.series?.title_ar,
        item.series_title, item.series_title_ar,
        item.series_name, item.series_name_ar
    ].filter(Boolean) as string[];

    let candidate = '';
    if (isRTL) {
        candidate = item.episode_title_ar || item.title_ar || item.episode_title || item.title || '';
    } else {
        candidate = item.episode_title || item.title || '';
    }

    let pure = extractPureEpisodeTitle(candidate, seriesNames);
    if (!pure && isRTL && (item.episode_title || item.title)) {
        pure = extractPureEpisodeTitle(item.episode_title || item.title, seriesNames);
    }
    return pure;
}

/**
 * Formats a series episode title according to the standardized convention:
 * "TV show [name] - Season [Number] - Episode [Number] - [Episode title if available]"
 */
export function formatEpisodeTitle(item: any, options: EpisodeTitleOptions = {}): string {
    if (!item) return '';

    const isRTL = options.isRTL ?? false;
    const includeSeries = options.includeSeriesName ?? true;

    // 1. Extract Series Name
    let sName = '';
    if (isRTL) {
        sName = item.series?.title_ar || item.series_title_ar || item.series_name_ar || 
                item.series?.title || item.series_title || item.series_name || '';
    } else {
        sName = item.series?.title || item.series_title || item.series_name || '';
    }

    if (!sName && item.title) {
        const parts = item.title.split(/\s*-\s*(?:Season|الموسم|S\d+)/i);
        if (parts[0] && !isGenericEpisodeTitle(parts[0])) {
            sName = parts[0].trim();
        }
    }

    // Clean out any lingering raw season tags from series name
    sName = sName.replace(/\s*-\s*S\d+E\d+.*$/gi, '')
                 .replace(/\s*-\s*(?:Season|الموسم)\s*\d+.*$/gi, '')
                 .trim();

    if (!sName && options.fallbackSeriesName) {
        sName = options.fallbackSeriesName;
    }

    // 2. Extract Season & Episode numbers
    let s = item.season_number;
    if (s === undefined || s === null) s = item.season?.season_number;
    if (s === undefined || s === null) {
        const m = (item.file_path || item.title || '').match(/S(\d+)E\d+/i);
        s = m ? parseInt(m[1], 10) : 1;
    }

    let e = item.episode_number;
    if (e === undefined || e === null) {
        const m = (item.file_path || item.title || '').match(/S\d+E(\d+)/i);
        e = m ? parseInt(m[1], 10) : 1;
    }

    // 3. Extract Real Pure Episode Title
    const epTitle = getCleanEpisodeTitle(item, isRTL);

    const seasonLabel = isRTL ? `الموسم ${s}` : `Season ${s}`;
    const episodeLabel = isRTL ? `الحلقة ${e}` : `Episode ${e}`;

    let base = '';
    if (includeSeries && sName) {
        base = `${sName} - ${seasonLabel} - ${episodeLabel}`;
    } else {
        base = `${seasonLabel} - ${episodeLabel}`;
    }

    return epTitle ? `${base} - ${epTitle}` : base;
}

/**
 * Format Season and Episode with Title (without Series Name prefix).
 * E.g.: "Season 1 - Episode 1 - The National Anthem"
 */
export function formatSeasonEpisodeTitle(item: any, options: Omit<EpisodeTitleOptions, 'includeSeriesName'> = {}): string {
    return formatEpisodeTitle(item, { ...options, includeSeriesName: false });
}