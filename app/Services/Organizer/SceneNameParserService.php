<?php

namespace App\Services\Organizer;

class SceneNameParserService
{
    /**
     * Map of Arabic ordinal words to integer values.
     */
    protected array $arabicNumerals = [
        'الاولى' => 1, 'الاول' => 1, 'الأولى' => 1, 'الأول' => 1, 'الاولي' => 1, 'الأولي' => 1,
        'واحد' => 1, 'واحدة' => 1,
        'الثانية' => 2, 'الثاني' => 2, 'اثنين' => 2, 'إثنين' => 2, 'اثنان' => 2, 'إثنان' => 2,
        'الثالثة' => 3, 'الثالث' => 3, 'ثلاثة' => 3, 'ثلاث' => 3,
        'الرابعة' => 4, 'الرابع' => 4, 'اربعة' => 4, 'أربعة' => 4, 'اربع' => 4, 'أربع' => 4,
        'الخامسة' => 5, 'الخامس' => 5, 'خمسة' => 5, 'خمس' => 5,
        'السادسة' => 6, 'السادس' => 6, 'ستة' => 6, 'ست' => 6,
        'السابعة' => 7, 'السابع' => 7, 'سبعة' => 7, 'سبع' => 7,
        'الثامنة' => 8, 'الثامن' => 8, 'ثمانية' => 8, 'ثماني' => 8, 'ثمان' => 8,
        'التاسعة' => 9, 'التاسع' => 9, 'تسعة' => 9, 'تسع' => 9,
        'العاشرة' => 10, 'العاشر' => 10, 'عشرة' => 10, 'عشر' => 10,

        // 11-19
        'الحادي عشر' => 11, 'الحادية عشر' => 11, 'الحادي عشرة' => 11, 'الحادية عشرة' => 11, 'إحدى عشر' => 11, 'أحد عشر' => 11, 'احدى عشر' => 11,
        'الثاني عشر' => 12, 'الثانية عشر' => 12, 'الثاني عشرة' => 12, 'الثانية عشرة' => 12, 'اثنا عشر' => 12, 'اثني عشر' => 12, 'اثنتا عشر' => 12, 'اثنتي عشر' => 12,
        'الثالث عشر' => 13, 'الثالثة عشر' => 13, 'الثالث عشرة' => 13, 'الثالثة عشرة' => 13, 'ثلاثة عشر' => 13, 'ثلاث عشر' => 13,
        'الرابع عشر' => 14, 'الرابعة عشر' => 14, 'الرابع عشرة' => 14, 'الرابعة عشرة' => 14, 'اربعة عشر' => 14, 'أربعة عشر' => 14,
        'الخامس عشر' => 15, 'الخامسة عشر' => 15, 'الخامس عشرة' => 15, 'الخامسة عشرة' => 15, 'خمسة عشر' => 15, 'خمس عشر' => 15,
        'السادس عشر' => 16, 'السادسة عشر' => 16, 'السادس عشرة' => 16, 'السادسة عشرة' => 16, 'ستة عشر' => 16, 'ست عشر' => 16,
        'السابع عشر' => 17, 'السابعة عشر' => 17, 'السابع عشرة' => 17, 'السابعة عشرة' => 17, 'سبعة عشر' => 17, 'سبع عشر' => 17,
        'الثامن عشر' => 18, 'الثامنة عشر' => 18, 'الثامن عشرة' => 18, 'الثامنة عشرة' => 18, 'ثمانية عشر' => 18, 'ثماني عشر' => 18,
        'التاسع عشر' => 19, 'التاسعة عشر' => 19, 'التاسع عشرة' => 19, 'التاسعة عشرة' => 19, 'تسعة عشر' => 19, 'تسع عشر' => 19,

        // 20
        'العشرون' => 20, 'العشرين' => 20, 'عشرون' => 20, 'عشرين' => 20,

        // 21-29 (both with and without 'ال' after 'و')
        'الحادي والعشرون' => 21, 'الحادية والعشرون' => 21, 'الحادي والعشرين' => 21, 'الحادية والعشرين' => 21,
        'الحادية وعشرون' => 21, 'الحادية وعشرين' => 21, 'الحادي وعشرون' => 21, 'الحادي وعشرين' => 21,
        'الواحد والعشرون' => 21, 'الواحدة والعشرون' => 21, 'الواحد والعشرين' => 21, 'الواحدة والعشرين' => 21,

        'الثاني والعشرون' => 22, 'الثانية والعشرون' => 22, 'الثاني والعشرين' => 22, 'الثانية والعشرين' => 22,
        'الثانية وعشرون' => 22, 'الثانية وعشرين' => 22, 'الثاني وعشرون' => 22, 'الثاني وعشرين' => 22,

        'الثالث والعشرون' => 23, 'الثالثة والعشرون' => 23, 'الثالث والعشرين' => 23, 'الثانية والعشرين' => 22, 'الثالثة والعشرين' => 23,
        'الثالثة وعشرون' => 23, 'الثالثة وعشرين' => 23, 'الثالث وعشرون' => 23, 'الثالث وعشرين' => 23,

        'الرابع والعشرون' => 24, 'الرابعة والعشرون' => 24, 'الرابع والعشرين' => 24, 'الرابعة والعشرين' => 24,
        'الرابعة وعشرون' => 24, 'الرابعة وعشرين' => 24, 'الرابع وعشرون' => 24, 'الرابع وعشرين' => 24,

        'الخامس والعشرون' => 25, 'الخامسة والعشرون' => 25, 'الخامس والعشرين' => 25, 'الخامسة والعشرين' => 25,
        'الخامسة وعشرون' => 25, 'الخامسة وعشرين' => 25, 'الخامس وعشرون' => 25, 'الخامس وعشرين' => 25,

        'السادس والعشرون' => 26, 'السادسة والعشرون' => 26, 'السادس والعشرين' => 26, 'السادسة والعشرين' => 26,
        'السادسة وعشرون' => 26, 'السادسة وعشرين' => 26, 'السادس وعشرون' => 26, 'السادس وعشرين' => 26,

        'السابع والعشرون' => 27, 'السابعة والعشرون' => 27, 'السابع والعشرين' => 27, 'السابعة والعشرين' => 27,
        'السابعة وعشرون' => 27, 'السابعة وعشرين' => 27, 'السابع وعشرون' => 27, 'السابع وعشرين' => 27,

        'الثامن والعشرون' => 28, 'الثامنة والعشرون' => 28, 'الثامن والعشرين' => 28, 'الثامنة والعشرين' => 28,
        'الثامنة وعشرون' => 28, 'الثامنة وعشرين' => 28, 'الثامن وعشرون' => 28, 'الثامن وعشرين' => 28,

        'التاسع والعشرون' => 29, 'التاسعة والعشرون' => 29, 'التاسع والعشرين' => 29, 'التاسعة والعشرين' => 29,
        'التاسعة وعشرون' => 29, 'التاسعة وعشرين' => 29, 'التاسع وعشرون' => 29, 'التاسع وعشرين' => 29,

        // 30 & Finale variants
        'الثلاثون' => 30, 'الثلاثين' => 30, 'ثلاثون' => 30, 'ثلاثين' => 30,
        'الثلاثون والاخيرة' => 30, 'الثلاثون والأخيرة' => 30, 'الثلاثون والآخيرة' => 30,
        'الثلاثون و الاخيرة' => 30, 'الثلاثون و الأخيرة' => 30, 'الثلاثون و الآخيرة' => 30,
        'الثلاثين والاخيرة' => 30, 'الثلاثين والأخيرة' => 30, 'الثلاثين و الاخيرة' => 30, 'الثلاثين و الأخيرة' => 30,
        'الاخيرة' => 30, 'الأخيرة' => 30, 'الآخيرة' => 30,

        // 31-39
        'الحادي والثلاثون' => 31, 'الحادية والثلاثون' => 31, 'الحادي والثلاثين' => 31, 'الحادية والثلاثين' => 31,
        'الثاني والثلاثون' => 32, 'الثانية والثلاثون' => 32, 'الثاني والثلاثين' => 32, 'الثانية والثلاثين' => 32,
        'الثالث والثلاثون' => 33, 'الثالثة والثلاثون' => 33, 'الثالث والثلاثين' => 33, 'الثالثة والثلاثين' => 33,
        'الرابع والثلاثون' => 34, 'الرابعة والثلاثون' => 34, 'الرابع والثلاثين' => 34, 'الرابعة والثلاثين' => 34,
        'الخامس والثلاثون' => 35, 'الخامسة والثلاثون' => 35, 'الخامس والثلاثين' => 35, 'الخامسة والثلاثين' => 35,
        'السادس والثلاثون' => 36, 'السادسة والثلاثون' => 36, 'السادس والثلاثين' => 36, 'السادسة والثلاثين' => 36,
        'السابع والثلاثون' => 37, 'السابعة والثلاثون' => 37, 'السابع والثلاثين' => 37, 'السابعة والثلاثين' => 37,
        'الثامن والثلاثون' => 38, 'الثامنة والثلاثون' => 38, 'الثامن والثلاثين' => 38, 'الثامنة والثلاثين' => 38,
        'التاسع والثلاثون' => 39, 'التاسعة والثلاثون' => 39, 'التاسع والثلاثين' => 39, 'التاسعة والثلاثين' => 39,

        // Decades up to 100
        'الاربعون' => 40, 'الأربعون' => 40, 'الاربعين' => 40, 'الأربعين' => 40,
        'الخمسون' => 50, 'الخمسين' => 50,
        'الستون' => 60, 'الستين' => 60,
        'السبعون' => 70, 'السبعين' => 70,
        'الثمانون' => 80, 'الثمانين' => 80,
        'التسعون' => 90, 'التسعين' => 90,
        'المائة' => 100, 'المئة' => 100,
    ];

    /**
     * Generic folder names that should not be used as media titles.
     */
    protected array $genericFolderNames = [
        'movies', 'movie', 'films', 'film', 'cinema', 'افلام', 'أفلام', 'فلم', 'فيلم',
        'series', 'tv', 'tv shows', 'tv-shows', 'shows', 'مسلسلات', 'مسلسل', 'برامج',
        'media', 'videos', 'video', 'downloads', 'download', 'incoming', 'completed',
        'new folder', 'temp', 'desktop', 'documents', 'hard drive', 'usb', 'external',
    ];

    /**
     * Parse any filename or full path into structured metadata.
     */
    public function parse(string $filenameOrPath): array
    {
        $normalized = str_replace('\\', '/', $filenameOrPath);
        $parts = array_values(array_filter(explode('/', $normalized)));
        $filename = end($parts) ?: '';
        $parentFolder = count($parts) > 1 ? $parts[count($parts) - 2] : '';
        $grandparentFolder = count($parts) > 2 ? $parts[count($parts) - 3] : '';

        // Normalize eastern Arabic digits (١, ٢, ٣...) to standard (1, 2, 3...)
        $filename = $this->convertArabicDigits($filename);
        $parentFolder = $this->convertArabicDigits($parentFolder);
        $grandparentFolder = $this->convertArabicDigits($grandparentFolder);

        $rawExt = pathinfo($filename, PATHINFO_EXTENSION);
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $validMediaExts = ['mp4', 'mkv', 'webm', 'avi', 'mov', 'm4v', 'flv', 'wmv', 'ts', 'm2ts', 'iso', 'srt', 'vtt', 'ass', 'sub', 'idx'];
        if (! in_array(strtolower($rawExt), $validMediaExts)) {
            $baseName = $filename;
            $extension = 'mkv';
        } else {
            $extension = strtolower($rawExt);
        }

        $isSample = $this->isSampleOrExtra($filename, $parentFolder);
        $isHidden = str_starts_with($filename, '.') || str_starts_with($filename, '._');

        $type = 'movie';
        $season = null;
        $episode = null;
        $episodeEnd = null;
        $episodeTitle = null;
        $seriesTitle = null;
        $year = null;
        $endYear = null;
        $edition = null;
        $part = null;
        $is3D = false;
        $airDate = null;
        $resolution = null;
        $codec = null;
        $audio = null;
        $source = null;
        $group = null;
        $isParentSeasonFolder = false;

        // Remove soft hyphens, zero-width spaces, and normalize typographical quotes/pipes
        $filename = str_replace(["\xC2\xAD", "\xE2\x80\x8B", '¦'], ['', '', '-'], $filename);
        $filename = str_replace('الحزء', 'الجزء', $filename);
        // Strip common YouTube channel and production promotional prefixes
        $cleanedPrefix = preg_replace('/^(?:Future Cinema & TV Productions|المستقبل للإنتاج|قناة .*? الرسمية)[\s\-–¦|]+(?:مسلسل\s+)?/ui', '', $filename);
        if ($cleanedPrefix !== null) {
            $filename = $cleanedPrefix;
        }

        $working = $baseName;
        $working = preg_replace('/\b(nine)-(nine)\b/i', 'Nine###Nine', $working);
        $working = preg_replace('/\b(brooklyn)-(nine)\b/i', 'Brooklyn###Nine', $working);

        // Check if path indicates series or movies library ancestor
        $isInsideSeriesTree = false;
        $isInsideMovieTree = false;
        foreach ($parts as $p) {
            $pLower = strtolower(trim($p));
            if (in_array($pLower, ['series', 'tv', 'tv shows', 'tv-shows', 'shows', 'مسلسلات', 'مسلسل', 'برامج', 'دراما', 'انمي', 'أنمي', 'anime'])) {
                $isInsideSeriesTree = true;
            }
            if (in_array($pLower, ['movies', 'films', 'cinema', 'أفلام', 'افلام', 'فيلم', 'movie', 'film', 'movies 4k', '4k movies', 'movies 1080p', 'action', 'horror', 'comedy', 'drama', 'sci-fi', 'animation', 'funny & animation'])) {
                $isInsideMovieTree = true;
            }
        }

        // Check if parent or grandparent indicates a Movie Collection / Boxset
        $isCollectionFolder = false;
        $detectedCollectionName = null;
        $collectionFolderPattern = '/^(.*?)(?:\s+(?:Collection|Trilogy|Anthology|Saga|Boxset|Pentalogy|Hexalogy|Heptalogy|Octalogy|Duology|Tetralogy|Franchise|سلسلة|أفلام|سلسلة أفلام))(?:\s+.*)?$/ui';
        if (preg_match($collectionFolderPattern, trim($parentFolder), $cMatch)) {
            $isCollectionFolder = true;
            $cleanPrefix = preg_replace('/(?:\s+\d+\s*[-–]\s*\d+|\s+[IVXLCDM]+\s*[-–]\s*[IVXLCDM]+|\s+19\d\d\s*[-–]\s*20\d\d)$/i', '', trim($cMatch[1]));
            $detectedCollectionName = trim($cleanPrefix);
        } elseif ($grandparentFolder && preg_match($collectionFolderPattern, trim($grandparentFolder), $gcMatch)) {
            $isCollectionFolder = true;
            $cleanPrefix = preg_replace('/(?:\s+\d+\s*[-–]\s*\d+|\s+[IVXLCDM]+\s*[-–]\s*[IVXLCDM]+|\s+19\d\d\s*[-–]\s*20\d\d)$/i', '', trim($gcMatch[1]));
            $detectedCollectionName = trim($cleanPrefix);
        }

        // Deep Franchise & Collection Knowledge Base Lookup
        if (! $detectedCollectionName) {
            $detectedCollectionName = $this->detectFranchiseOrCollection($baseName, $parentFolder, $grandparentFolder);
        }

        // 1. Check 3D
        if (preg_match('/\b(3d|mvc|sbs|tab|half-sbs|half-ou|3d-hsbs)\b/i', $working.' '.$parentFolder)) {
            $is3D = true;
        }

        // 2. Extract Edition tags
        $editionPatterns = [
            '/(?:\[|\()?(director\'?s\s*cut)(?:\]|\))?/i' => "Director's Cut",
            '/(?:\[|\()?(extended(?:\s*edition|\s*cut)?)(?:\]|\))?/i' => 'Extended Edition',
            '/(?:\[|\()?(ultimate(?:\s*edition|\s*cut)?)(?:\]|\))?/i' => 'Ultimate Edition',
            '/(?:\[|\()?(richard\s*donner\s*cut)(?:\]|\))?/i' => 'Richard Donner Cut',
            '/(?:\[|\()?(theatrical(?:\s*cut|\s*edition)?)(?:\]|\))?/i' => 'Theatrical Cut',
            '/(?:\[|\()?(unrated(?:\s*edition|\s*cut)?)(?:\]|\))?/i' => 'Unrated',
            '/(?:\[|\()?(remastered)(?:\]|\))?/i' => 'Remastered',
            '/(?:\[|\()?(special\s*edition)(?:\]|\))?/i' => 'Special Edition',
            '/(?:\[|\()?(criterion(?:\s*edition|\s*collection)?)(?:\]|\))?/i' => 'Criterion Collection',
            '/(?:\[|\()?(imax(?:\s*edition)?)(?:\]|\))?/i' => 'IMAX Edition',
            '/(?:\[|\(|\b)(final\s*cut)(?:\]|\)|\b)/i' => 'Final Cut',
            '/(?:\[|\()?(ova|oad)(?:\]|\))?/i' => 'OVA',
        ];

        foreach ($editionPatterns as $pattern => $edName) {
            if (preg_match($pattern, $working.' '.$parentFolder)) {
                $edition = $edName;
                break;
            }
        }

        // 2.5 Sequence numbering prefix on Collection Movies (e.g. "1.Ip.Man.2008.mp4", "2.Fast.2.Furious.2003", "1.Batman Begins (2005)")
        if (preg_match('/^(?:E)?(\d{1,2})[.\-\s_]+(.*?)$/i', trim($working), $numPrefixMatch)) {
            $restOfName = trim($numPrefixMatch[2]);
            $hasYearInRest = preg_match('/(19\d\d|20\d\d)/', $restOfName);
            if (($isInsideMovieTree || $isCollectionFolder || $hasYearInRest) && ! $isInsideSeriesTree && ! $isParentSeasonFolder) {
                $part = (int) $numPrefixMatch[1];
                $working = $restOfName;
                $baseName = $restOfName;
                if ($isCollectionFolder && empty($collectionName)) {
                    $collectionName = $detectedCollectionName;
                }
            }
        }

        // 3. Multi-Part Movie
        if (preg_match('/(?:\[|\(|\b)(?:part|pt|cd|disc|جزء|الجزء)\s*(\d{1,2})(?:\]|\)|\b)/ui', $working, $pMatches)) {
            $part = (int) $pMatches[1];
        }

        // 4. Extract Date-based daily shows (e.g. The Daily Show 2023-01-15.mkv)
        if (preg_match('/^(.*?)[._\-\s]+(20\d\d|19\d\d)[.\-_](\d{2})[.\-_](\d{2})(?:[._\-\s]*(.*?))?$/i', $working, $tvMatch)) {
            $type = 'series';
            $rawSeriesPart = $tvMatch[1];
            $airDate = "{$tvMatch[2]}-{$tvMatch[3]}-{$tvMatch[4]}";
            $season = (int) $tvMatch[2];
            $episode = (int) ($tvMatch[3].$tvMatch[4]);
            $seriesTitle = $this->cleanTitleString($rawSeriesPart);
            $cleanTitle = $seriesTitle;

            return [
                'original_filename' => $filename,
                'title' => $cleanTitle,
                'clean_title' => $cleanTitle,
                'series_title' => $cleanTitle,
                'collection_name' => $collectionName ?? $detectedCollectionName ?? null,
                'type' => 'series',
                'season' => $season,
                'episode' => $episode,
                'episode_end' => null,
                'episode_title' => null,
                'year' => (int) $tvMatch[2],
                'edition' => $edition,
                'part' => $part,
                'is_3d' => $is3D,
                'air_date' => $airDate,
                'resolution' => $resolution,
                'codec' => $codec,
                'audio' => $audio,
                'source' => $source,
                'group' => $group,
                'extension' => $extension,
                'is_sample' => $isSample,
                'is_hidden' => $isHidden,
            ];
        }

        // 5. Extract Year
        if (preg_match('/[\[\(]?\b(19\d\d|20\d\d)\s*[-–—]\s*(19\d\d|20\d\d)\b[\]\)]?/', $working, $yrMatches)) {
            $year = (int) $yrMatches[1];
            $endYear = (int) $yrMatches[2];
        } elseif (preg_match('/^(19\d\d|20\d\d)\s*[-_.]?\s*(.*)$/', $working, $yMatches)) {
            $year = (int) $yMatches[1];
            $working = $yMatches[2];
        } elseif (preg_match_all('/[\[\(]?\b(19\d\d|20\d\d)\b[\]\)]?/', $working, $yMatchesAll)) {
            $foundYears = $yMatchesAll[1];
            $year = (int) end($foundYears);
        }

        // 6. Extract Release Group at end
        if (preg_match('/-(?:\[)?([a-zA-Z0-9\.]+)(?:\])?$/i', $working, $gMatches)) {
            $groupCandidate = $gMatches[1];
            $isSeasonEp = preg_match('/[sS]\d{1,2}[eE]\d{1,3}|\b(?:ep|episode|part)\s*\d+|\b\d{1,2}x\d{1,3}\b/i', $groupCandidate);
            if (! $isSeasonEp && ! preg_match('/^(?:x264|x265|h264|h265|hevc|1080p|720p|576p|540p|480p|360p|240p|2160p|4k|aac|ddp|mp4|mkv|\d+)$/i', $groupCandidate) && ! preg_match('/^\d{1,3}\./', $groupCandidate)) {
                $group = $groupCandidate;
                $working = substr($working, 0, -strlen($gMatches[0]));
            }
        } elseif (preg_match('/\[([a-zA-Z0-9\.]+)\]$/i', $working, $gMatches)) {
            $group = $gMatches[1];
            $working = substr($working, 0, -strlen($gMatches[0]));
        }

        // 7. Detect Parent Folder Season context (English & Arabic)
        // Matches: Season 1, Season 01, S1, S01, الموسم الأول, الموسم 1, موسم 2, الجزء 1, etc.
        $seasonFolderRegex = '/^(?:Season|Series|Staffel|Saison|الموسم|موسم|الجزء|جزء)\s*(\d{1,2}|الاولى|الاول|الأولى|الأول|الثانية|الثاني|الثالثة|الثالث|الرابعة|الرابع|الخامسة|الخامس|السادسة|السادس|السابعة|السابع|الثامنة|الثامن|التاسعة|التاسع|العاشرة|العاشر)$/ui';

        if ($parentFolder && preg_match($seasonFolderRegex, trim($parentFolder), $sMatches)) {
            $type = 'series';
            $isParentSeasonFolder = true;
            $season = $this->resolveNumber($sMatches[1]);
        } elseif ($parentFolder && preg_match('/^[sS](\d{1,2})$/i', trim($parentFolder), $sMatches)) {
            $type = 'series';
            $isParentSeasonFolder = true;
            $season = (int) $sMatches[1];
        }

        // 8. Detect TV Series Patterns
        $isSeriesDetected = $isParentSeasonFolder;
        $rawSeriesPart = '';
        $rawAfterPart = '';

        // Pattern A: S00E00 Specials / Multi-episode S01E01E02 / S01E01-E02 / S01E01-02 / S01E01.E02
        if (preg_match('/^(.*?)[._\-\s]+[sS](\d{1,2})[eE](\d{1,3})(?:(?:[-_ .]?[eE]|[-_])(\d{1,3}))(?!\d|[kKpP])[._\-\s]*(.*?)$/i', $working, $tvMatch)) {
            $matchedEnd = (int) $tvMatch[4];
            $matchedEp = (int) $tvMatch[3];
            if ($matchedEnd > $matchedEp && $matchedEnd <= $matchedEp + 15) {
                $type = 'series';
                $isSeriesDetected = true;
                $rawSeriesPart = $tvMatch[1];
                $season = (int) $tvMatch[2];
                $episode = $matchedEp;
                $episodeEnd = $matchedEnd;
                $rawAfterPart = $tvMatch[5];
            }
        }
        // Pattern B: Standard S01E02 / S00E01 / S00E00
        elseif (preg_match('/^(.*?)(?:[._\-\s]|^)[sS](\d{1,2})[eE](\d{1,3})[._\-\s]*(.*?)$/i', $working, $tvMatch)) {
            $type = 'series';
            $isSeriesDetected = true;
            $rawSeriesPart = $tvMatch[1];
            $season = (int) $tvMatch[2];
            $episode = (int) $tvMatch[3];
            $rawAfterPart = $tvMatch[4];
        }
        // Pattern C: 1x02 (X format)
        elseif (preg_match('/^(.*?)[._\-\s]+(\d{1,2})x(\d{1,3})[._\-\s]*(.*?)$/i', $working, $tvMatch)) {
            $type = 'series';
            $isSeriesDetected = true;
            $rawSeriesPart = $tvMatch[1];
            $season = (int) $tvMatch[2];
            $episode = (int) $tvMatch[3];
            $rawAfterPart = $tvMatch[4];
        }
        // Pattern D: Arabic Season & Episode in filename (e.g. مسلسل الاختيار الموسم 1 الحلقة 05)
        elseif (preg_match('/^(.*?)(?:[._\-\s]+|^)(?:الموسم|موسم|الجزء|جزء)\s*(\d+|[\p{Arabic}\s]+?)[._\-\s]+(?:الحلقة|حلقة|ح)\s*(\d+|[\p{Arabic}\s]+?)(?:\s*[-–—_.]|\s*(?=[a-zA-Z0-9])|\s*$)(.*?)$/ui', $working, $tvMatch)) {
            $type = 'series';
            $isSeriesDetected = true;
            $rawSeriesPart = $tvMatch[1];
            $season = $this->resolveNumber($tvMatch[2]);
            $candidateEp = $this->resolveNumber($tvMatch[3]);
            if (preg_match('/\b(?:ep|episode)\s*(\d{1,3})\b/i', $working, $explicitEpMatch)) {
                $num = (int) $explicitEpMatch[1];
                if ($candidateEp === 1 && $num > 1) {
                    $candidateEp = $num;
                }
            }
            $episode = $candidateEp;
            $rawAfterPart = $tvMatch[4];
        }
        // Pattern E: Arabic Episode in filename (e.g. مسلسل الاختيار الحلقة 01 or الحلقة 05 or حلقة 12)
        elseif (preg_match('/^(.*?)(?:[._\-\s]+|^)(?:الحلقة|حلقة|ح)\s*(\d+|[\p{Arabic}\s]+?)(?:\s*[-–—_.]|\s*(?=[a-zA-Z0-9])|\s*$)(.*?)$/ui', $working, $tvMatch)) {
            $type = 'series';
            $isSeriesDetected = true;
            $rawSeriesPart = $tvMatch[1];
            $season = $season ?? 1;
            $candidateEp = $this->resolveNumber($tvMatch[2]);
            if (preg_match('/\b(?:ep|episode)\s*(\d{1,3})\b/i', $working, $explicitEpMatch)) {
                $num = (int) $explicitEpMatch[1];
                if ($candidateEp === 1 && $num > 1) {
                    $candidateEp = $num;
                }
            }
            $episode = $candidateEp;
            $rawAfterPart = $tvMatch[3];
        }
        // Pattern F: English Season & Episode text (e.g. Show Name Season 1 Episode 2)
        elseif (preg_match('/^(.*?)[._\-\s]+Season\s*(\d{1,2})\s*Episode\s*(\d{1,3})[._\-\s]*(.*?)$/i', $working, $tvMatch)) {
            $type = 'series';
            $isSeriesDetected = true;
            $rawSeriesPart = $tvMatch[1];
            $season = (int) $tvMatch[2];
            $episode = (int) $tvMatch[3];
            $rawAfterPart = $tvMatch[4];
        }
        // Pattern G: English Episode only in filename (e.g. Episode 04 or Ep 04 or E04)
        elseif (preg_match('/^(.*?)(?:[._\-\s]+|^)(?:Episode|Ep|E)\s*(\d{1,3})[._\-\s]*(.*?)$/i', $working, $tvMatch)) {
            $type = 'series';
            $isSeriesDetected = true;
            $rawSeriesPart = $tvMatch[1];
            $season = $season ?? 1;
            $episode = (int) $tvMatch[2];
            $rawAfterPart = $tvMatch[3];
        }
        // Pattern H: Pure numeric or simple episode number (e.g. "01.mp4", "1.mp4", "05 - Title.mkv")
        // Triggered when in a Season folder, Series folder, or when parent folder is a specific show name!
        elseif (preg_match('/^(?:E)?(\d{1,3})(?:\s*[-_.]\s*(.*?))?$/i', trim($working), $epMatch)) {
            $candidateEp = (int) $epMatch[1];
            $isParentGeneric = in_array(strtolower(trim($parentFolder)), $this->genericFolderNames);
            $hasYear = preg_match('/(19\d\d|20\d\d)/', $working);

            // Only trigger as series if not inside movie tree and no movie release year is present
            if (($isParentSeasonFolder || $isInsideSeriesTree || (! $isParentGeneric && ! empty($parentFolder))) && ! $isInsideMovieTree && ! $hasYear && ! $isCollectionFolder) {
                $type = 'series';
                $isSeriesDetected = true;
                $episode = $candidateEp;
                $season = $season ?? 1;
                $rawAfterPart = $epMatch[2] ?? '';
            }
        }
        // Pattern I: Known pilot/special named episode
        elseif ($parentFolder && ! in_array(strtolower($parentFolder), $this->genericFolderNames) && ! preg_match('/\b(19\d\d|20\d\d)\b/', $baseName)) {
            $knownEpisodeNames = ['pilot', 'winter is coming', 'the duel', 'finale', 'prologue', 'special', 'ova'];
            $cleanBaseLower = strtolower(trim(preg_replace('/[._\-]/', ' ', $baseName)));
            if (in_array($cleanBaseLower, $knownEpisodeNames) || $edition === 'OVA') {
                $type = 'series';
                $season = $season ?? 1;
                $episode = $episode ?? 1;
                $episodeTitle = $this->toProperTitleCase($cleanBaseLower);
                $seriesTitle = $this->cleanTitleString($parentFolder);
                $isSeriesDetected = true;
            }
        }

        // Quality and Codec Delimiters
        $qualityTokensRegex = '/\b(2160p|1440p|1080p|1080i|720p|576p|540p|480p|360p|240p|4k|2k|uhd|fhd|hd|sd|pal|ntsc|bluray|blu-ray|remux|bdrip|brrip|web-dl|webdl|webrip|web|hdtv|pdtv|dvdrip|dvd|vhsrip|vhs|x264|x265|h264|h265|hevc|avc|av1|xvid|divx|10bit|8bit|12bit|hdr|hdr10|hdr10\+|dv|dovi|dolbyvision|aac|aac2\.0|aac5\.1|ddp|ddp5\.1|ddp7\.1|ddp2\.0|dd\+|eac3|ac3|dts|dts-hd|dts-ma|atmos|truehd|flac|mp3|2\.0|5\.1|7\.1|مترجم|مدبلج|نسخة|بلوراي)\b/ui';

        if ($isSeriesDetected) {
            // Determine Series Title
            if (! empty($rawSeriesPart)) {
                $seriesTitle = $this->cleanTitleString($rawSeriesPart);
            }

            // If seriesTitle is missing or generic (e.g. file was just "01.mp4" or "Episode 05.mkv"), resolve from folder hierarchy
            if (empty($seriesTitle) || is_numeric($seriesTitle) || strtolower($seriesTitle) === 'unknown series' || in_array(strtolower($seriesTitle), $this->genericFolderNames)) {
                if ($isParentSeasonFolder && $grandparentFolder && ! in_array(strtolower($grandparentFolder), $this->genericFolderNames)) {
                    $seriesTitle = $this->cleanTitleString($grandparentFolder);
                } elseif ($parentFolder && ! in_array(strtolower($parentFolder), $this->genericFolderNames)) {
                    $seriesTitle = $this->cleanTitleString($parentFolder);
                } elseif ($grandparentFolder && ! in_array(strtolower($grandparentFolder), $this->genericFolderNames)) {
                    $seriesTitle = $this->cleanTitleString($grandparentFolder);
                }
            }

            // Extract dual Arabic and English series titles if available
            $seriesTitleAr = null;
            $seriesTitleEn = null;
            if ($seriesTitle && preg_match('/\p{Arabic}/u', $seriesTitle)) {
                $seriesTitleAr = $seriesTitle;
                $seriesTitleEn = $this->extractEnglishSeriesTitle($parentFolder, $filename, $seriesTitle);
            } elseif ($seriesTitle) {
                $seriesTitleEn = $seriesTitle;
            } else {
                $seriesTitleEn = $this->extractEnglishSeriesTitle($parentFolder, $filename);
            }

            // Episode Title from remaining string
            if ($rawAfterPart && ! $episodeTitle) {
                // If rawAfterPart is merely a secondary Latin series title + Ep number (e.g. "- Elnadam Ep 1"),
                // extract any secondary Latin series title and don't treat the show name as an episode title!
                if (preg_match('/^\s*[-–—_.]*\s*([a-zA-Z0-9\s\']+?)\s*(?:Ep|Episode|E|Part|P)\s*(\d{1,3})\b(?:\s*[-–—_.]*\s*(.*))?$/i', $rawAfterPart, $bilingualMatch)) {
                    if (empty($seriesTitleEn)) {
                        $seriesTitleEn = trim($bilingualMatch[1]);
                    }
                    $afterBilingual = trim($bilingualMatch[3] ?? '');
                    if (! empty($afterBilingual)) {
                        $cleanEp = $this->cleanTitleString($afterBilingual);
                        if (! empty($cleanEp) && ! is_numeric($cleanEp) && ! preg_match($qualityTokensRegex, $cleanEp)) {
                            $episodeTitle = $cleanEp;
                        }
                    }
                } else {
                    $cleanEp = $this->cleanTitleString($rawAfterPart);
                    if (! empty($cleanEp) && ! is_numeric($cleanEp) && ! preg_match($qualityTokensRegex, $cleanEp)) {
                        $episodeTitle = $cleanEp;
                    }
                }
            }

            // If episode title merely repeats the series title (Arabic or English) or is generic, clear it
            if ($episodeTitle) {
                $normEp = strtolower(trim(preg_replace('/[^a-z0-9]/i', '', $episodeTitle)));
                $normEn = strtolower(trim(preg_replace('/[^a-z0-9]/i', '', (string) $seriesTitleEn)));
                $normAr = trim(preg_replace('/[^\p{Arabic}\p{N}]/u', '', (string) $seriesTitleAr));
                $normEpAr = trim(preg_replace('/[^\p{Arabic}\p{N}]/u', '', $episodeTitle));

                $stripArticle = fn ($s) => preg_replace('/^(?:al|el|the)/i', '', $s);
                $rootEp = $stripArticle($normEp);
                $rootEn = $stripArticle($normEn);

                if (
                    preg_match('/^(?:episode|ep|part|حلقة|ح)\s*\d*$/ui', $episodeTitle) ||
                    ($normEn !== '' && preg_match('/^'.preg_quote($normEn, '/').'(?:ep|episode|part|e)?\d*$/i', $normEp)) ||
                    ($rootEn !== '' && $rootEp !== '' && ($rootEp === $rootEn || preg_match('/^'.preg_quote($rootEn, '/').'(?:ep|episode|part|e)?\d*$/i', $rootEp))) ||
                    ($normAr !== '' && preg_match('/^'.preg_quote($normAr, '/').'(?:حلقة|ح|جزء)?\d*$/ui', $normEpAr)) ||
                    $episodeTitle === $seriesTitleAr ||
                    $episodeTitle === $seriesTitle
                ) {
                    $episodeTitle = null;
                }
            }

            // Check parent/grandparent directory for series year and end_year
            $dirToCheck = $isParentSeasonFolder ? $grandparentFolder : $parentFolder;
            if ($dirToCheck && preg_match('/\b(19\d\d|20\d\d)(?:\s*[-–—]\s*(19\d\d|20\d\d))?\b/', $dirToCheck, $dirYMatch)) {
                if (! $year) {
                    $year = (int) $dirYMatch[1];
                }
                if (! empty($dirYMatch[2])) {
                    $endYear = (int) $dirYMatch[2];
                }
            } elseif ($grandparentFolder && preg_match('/\b(19\d\d|20\d\d)(?:\s*[-–—]\s*(19\d\d|20\d\d))?\b/', $grandparentFolder, $gpYMatch)) {
                if (! $year) {
                    $year = (int) $gpYMatch[1];
                }
                if (! empty($gpYMatch[2])) {
                    $endYear = (int) $gpYMatch[2];
                }
            }

            $cleanTitle = $seriesTitle ?: 'Unknown Series';
            $cleanTitle = str_replace(['Nine###Nine', 'Brooklyn###Nine'], ['Nine-Nine', 'Brooklyn Nine-Nine'], $cleanTitle);
        } else {
            // Movie Title Extraction
            $isGenericMovieFile = in_array(strtolower(trim($baseName)), ['movie', 'film', 'video', 'main', 'cd1', 'cd2', 'فيلم', 'فلم', 'فيديو']);

            if ($isGenericMovieFile && $parentFolder && ! in_array(strtolower($parentFolder), $this->genericFolderNames)) {
                // Use folder name as movie title (e.g. The Godfather (1972)/movie.mp4)
                $cleanTitle = $this->cleanTitleString($parentFolder);
                if (preg_match('/\b(19\d\d|20\d\d)\b/', $parentFolder, $dirYMatch)) {
                    $year = (int) $dirYMatch[1];
                }
            } else {
                if (preg_match('/\b(19\d\d|20\d\d)\b/', $working, $yMatch, PREG_OFFSET_CAPTURE)) {
                    $year = $year ?: (int) $yMatch[1][0];
                    $beforeYear = substr($working, 0, $yMatch[0][1]);
                    $cleanTitle = $this->cleanTitleString($beforeYear);
                } elseif (preg_match($qualityTokensRegex, $working, $qMatch, PREG_OFFSET_CAPTURE)) {
                    $beforeQuality = substr($working, 0, $qMatch[0][1]);
                    $cleanTitle = $this->cleanTitleString($beforeQuality);
                } else {
                    $cleanTitle = $this->cleanTitleString($working);
                }

                if (! $year && $parentFolder && preg_match('/\b(19\d\d|20\d\d)\b/', $parentFolder, $dirYMatch)) {
                    $year = (int) $dirYMatch[1];
                }
            }
        }

        if (empty($cleanTitle)) {
            $cleanTitle = $this->cleanTitleString($baseName);
        }

        // 10. Detect Technical Specs across full path & filename
        $fullSpecsString = $working.' '.$filename.' '.$parentFolder.' '.$grandparentFolder;

        if (preg_match('/\b(2160p|4k|uhd)\b/i', $fullSpecsString)) {
            $resolution = '4K UHD';
        } elseif (preg_match('/\b(1440p|2k|qhd)\b/i', $fullSpecsString)) {
            $resolution = '1440p 2K';
        } elseif (preg_match('/\b(1080p|1080i|fhd)\b/i', $fullSpecsString)) {
            $resolution = '1080p FHD';
        } elseif (preg_match('/\b(720p|720i|hd)\b/i', $fullSpecsString)) {
            $resolution = '720p HD';
        } elseif (preg_match('/\b(576p|576i|pal)\b/i', $fullSpecsString)) {
            $resolution = '576p SD';
        } elseif (preg_match('/\b(540p|qhd)\b/i', $fullSpecsString)) {
            $resolution = '540p';
        } elseif (preg_match('/\b(480p|480i|ntsc|sd|dvdrip|dvd|vhsrip|vhs)\b/i', $fullSpecsString)) {
            $resolution = '480p SD';
        } elseif (preg_match('/\b(360p)\b/i', $fullSpecsString)) {
            $resolution = '360p';
        } elseif (preg_match('/\b(240p)\b/i', $fullSpecsString)) {
            $resolution = '240p';
        }

        if (! $resolution && preg_match('/\b(\d{3,4})x(\d{3,4})\b/i', $fullSpecsString, $dimMatch)) {
            $resolution = $this->calculateResolutionFromDimensions((int) $dimMatch[1], (int) $dimMatch[2]);
        }

        if (preg_match('/\b(x265|h265|hevc)\b/i', $fullSpecsString)) {
            $codec = 'HEVC / H.265';
        } elseif (preg_match('/\b(x264|h264|avc)\b/i', $fullSpecsString)) {
            $codec = 'H.264 / AVC';
        } elseif (preg_match('/\b(av1)\b/i', $fullSpecsString)) {
            $codec = 'AV1';
        } elseif (preg_match('/\b(xvid|divx)\b/i', $fullSpecsString)) {
            $codec = 'XviD';
        }

        if (preg_match('/\b(atmos)\b/i', $fullSpecsString)) {
            $audio = 'Dolby Atmos';
        } elseif (preg_match('/\b(truehd)\b/i', $fullSpecsString)) {
            $audio = 'Dolby TrueHD';
        } elseif (preg_match('/\b(dts-hd|dts hd|dts-ma)\b/i', $fullSpecsString)) {
            $audio = 'DTS-HD MA';
        } elseif (preg_match('/\b(dts)\b/i', $fullSpecsString)) {
            $audio = 'DTS';
        } elseif (preg_match('/\b(dd\+|eac3|ddp5\.1|ddp)\b/i', $fullSpecsString)) {
            $audio = 'Dolby Digital Plus';
        } elseif (preg_match('/\b(dd5\.1|ac3|5\.1)\b/i', $fullSpecsString)) {
            $audio = '5.1 Surround';
        } elseif (preg_match('/\b(aac)\b/i', $fullSpecsString)) {
            $audio = 'AAC';
        }

        if (preg_match('/\b(bluray|remux|bdrip|brrip)\b/i', $fullSpecsString)) {
            $source = 'BluRay';
        } elseif (preg_match('/\b(web-dl|webdl|webrip|web)\b/i', $fullSpecsString)) {
            $source = 'WEBRip';
        } elseif (preg_match('/\b(hdtv|pdtv|dsr)\b/i', $fullSpecsString)) {
            $source = 'HDTV';
        } elseif (preg_match('/\b(dvdrip|dvd)\b/i', $fullSpecsString)) {
            $source = 'DVDRip';
        }

        return [
            'original_filename' => $filename,
            'title' => $cleanTitle,
            'clean_title' => $cleanTitle,
            'series_title' => $type === 'series' ? $cleanTitle : null,
            'series_title_ar' => $type === 'series' ? ($seriesTitleAr ?? $cleanTitle) : null,
            'series_title_en' => $type === 'series' ? $seriesTitleEn : null,
            'collection_name' => $type === 'series' ? null : ($collectionName ?? $detectedCollectionName ?? null),
            'type' => $type,
            'season' => $season ?? ($type === 'series' ? 1 : null),
            'episode' => $episode ?? ($type === 'series' ? 1 : null),
            'episode_end' => $episodeEnd,
            'episode_title' => $episodeTitle,
            'year' => $year,
            'end_year' => $endYear,
            'edition' => $edition,
            'part' => $part,
            'is_3d' => $is3D,
            'air_date' => $airDate,
            'resolution' => $resolution,
            'codec' => $codec,
            'audio' => $audio,
            'source' => $source,
            'group' => $group,
            'extension' => $extension,
            'is_sample' => $isSample,
            'is_hidden' => $isHidden,
        ];
    }

    /**
     * Resolve string or Arabic word to integer.
     */
    protected function resolveNumber(string|int $val): int
    {
        if (is_numeric($val)) {
            return (int) $val;
        }

        $trimmed = trim(preg_replace('/\s+/u', ' ', (string) $val));
        if (isset($this->arabicNumerals[$trimmed])) {
            return $this->arabicNumerals[$trimmed];
        }

        // Normalize conjunction 'و' not followed by 'ال' (e.g. 'التاسعة وعشرون' -> 'التاسعة والعشرون')
        $withAl = preg_replace('/(\s+و)(?!ال)/u', '$1ال', $trimmed);
        if (isset($this->arabicNumerals[$withAl])) {
            return $this->arabicNumerals[$withAl];
        }

        // Try stripping 'ال' if present
        $withoutAl = preg_replace('/^ال/', '', $trimmed);
        if (isset($this->arabicNumerals[$withoutAl])) {
            return $this->arabicNumerals[$withoutAl];
        }

        $norm = str_replace(['أ', 'إ', 'آ'], 'ا', $trimmed);
        $norm = preg_replace('/ي$/u', 'ى', $norm);
        foreach ($this->arabicNumerals as $k => $v) {
            $kNorm = str_replace(['أ', 'إ', 'آ'], 'ا', $k);
            $kNorm = preg_replace('/ي$/u', 'ى', $kNorm);
            if ($kNorm === $norm) {
                return $v;
            }
        }

        return 1;
    }

    /**
     * Extract Latin / English series title from dual-language parent folders or filenames.
     * E.g. 'FLARE Arts & Media - مسلسل الندم كامل 2016 - Al Nadam Full Series HD' -> 'Al Nadam'
     * E.g. 'مسلسل الندم الحلقة 1 - Elnadam Ep 1.mp4' -> 'Elnadam'
     */
    public function extractEnglishSeriesTitle(?string $parentFolder = '', ?string $filename = '', ?string $arabicTitle = null): ?string
    {
        $parentFolder = $parentFolder ?: '';
        $filename = $filename ?: '';
        // 1. Check parent folder segments
        if (! empty($parentFolder)) {
            $segments = preg_split('/\s*[-–—|¦]\s*/u', $parentFolder);
            foreach ($segments as $seg) {
                $seg = trim($seg);
                if (preg_match('/\p{Arabic}/u', $seg)) {
                    continue;
                }
                if (preg_match('/\b(?:Arts & Media|Media|Productions|Films|Pictures|Studios|FLARE|Official|Channel)\b/i', $seg) &&
                    ! preg_match('/\b(?:Series|Show)\b/i', $seg) && strlen($seg) < 25 && stripos($seg, 'Media') !== false) {
                    continue;
                }
                $cleaned = preg_replace('/\b(?:Full\s*Series|Complete\s*Series|Full|Complete|HD|FHD|UHD|720p|1080p|4k|TV|Series|Season\s*\d+)\b/i', ' ', $seg);
                $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));
                if (! empty($cleaned) && strlen($cleaned) >= 2 && ! is_numeric($cleaned) && ! in_array(strtolower($cleaned), $this->genericFolderNames)) {
                    return $cleaned;
                }
            }
        }

        // 2. Fallback: check filename for Latin title before Ep / Episode
        if (preg_match('/(?:^|[-–—])\s*([a-zA-Z\s\']+?)\s*(?:Ep|Episode|E\d|S\d)\b/i', $filename, $m)) {
            $title = trim($m[1]);
            if (strlen($title) >= 2 && ! in_array(strtolower($title), $this->genericFolderNames)) {
                return $title;
            }
        }

        return null;
    }

    /**
     * Convert Eastern Arabic numerals (١, ٢, ٣...) to Western (1, 2, 3...)
     */
    public function convertArabicDigits(string $str): string
    {
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabic, $latin, $str);
    }

    public function calculateResolutionFromDimensions(int $w, int $h): string
    {
        $minDim = min($w, $h);
        $maxDim = max($w, $h);

        if ($minDim >= 2000 || $maxDim >= 3800) {
            return '4K UHD';
        }
        if ($minDim >= 1400 || $maxDim >= 2500) {
            return '1440p 2K';
        }
        if ($minDim >= 1000 || $maxDim >= 1900) {
            return '1080p FHD';
        }
        if ($minDim >= 700 || $maxDim >= 1200) {
            return '720p HD';
        }
        if ($minDim >= 560) {
            return '576p SD';
        }
        if ($minDim >= 500) {
            return '540p';
        }
        if ($minDim >= 440 || ($minDim >= 400 && $maxDim >= 700)) {
            return '480p SD';
        }
        if ($minDim >= 320) {
            return '360p';
        }

        return '240p';
    }

    /**
     * Map of keywords/patterns to canonical movie collection and franchise names.
     */
    protected array $canonicalFranchises = [
        'fast & furious' => 'Fast & Furious',
        'fast and furious' => 'Fast & Furious',
        'furious 7' => 'Fast & Furious',
        'fast x' => 'Fast & Furious',
        'hobbs & shaw' => 'Fast & Furious',
        'hobbs and shaw' => 'Fast & Furious',
        'tokyo drift' => 'Fast & Furious',
        'fast five' => 'Fast & Furious',
        '2 fast 2 furious' => 'Fast & Furious',

        'transformers' => 'Transformers',
        'bumblebee' => 'Transformers',

        'harry potter' => 'Harry Potter',
        'fantastic beasts' => 'Harry Potter',

        'the matrix' => 'The Matrix',
        'matrix reloaded' => 'The Matrix',
        'matrix revolutions' => 'The Matrix',
        'matrix resurrections' => 'The Matrix',
        'animatrix' => 'The Matrix',

        'marvel cinematic universe' => 'Marvel Cinematic Universe',
        'iron man' => 'Marvel Cinematic Universe',
        'captain america' => 'Marvel Cinematic Universe',
        'avengers' => 'Marvel Cinematic Universe',
        'guardians of the galaxy' => 'Marvel Cinematic Universe',
        'ant-man' => 'Marvel Cinematic Universe',
        'black panther' => 'Marvel Cinematic Universe',
        'doctor strange' => 'Marvel Cinematic Universe',
        'shang-chi' => 'Marvel Cinematic Universe',
        'thor' => 'Marvel Cinematic Universe',

        'lord of the rings' => 'The Lord of the Rings',
        'the hobbit' => 'The Lord of the Rings',
        'fellowship of the ring' => 'The Lord of the Rings',
        'two towers' => 'The Lord of the Rings',
        'return of the king' => 'The Lord of the Rings',

        'star wars' => 'Star Wars',

        'john wick' => 'John Wick',
        'mission: impossible' => 'Mission: Impossible',
        'mission impossible' => 'Mission: Impossible',
        'james bond' => 'James Bond 007',
        '007' => 'James Bond 007',

        'pirates of the caribbean' => 'Pirates of the Caribbean',
        'jurassic park' => 'Jurassic Park',
        'jurassic world' => 'Jurassic Park',
        'indiana jones' => 'Indiana Jones',
        'the dark knight' => 'The Dark Knight',
        'alien' => 'Alien',
        'predator' => 'Predator',
        'die hard' => 'Die Hard',
        'the terminator' => 'The Terminator',
        'terminator' => 'The Terminator',
        'the hunger games' => 'The Hunger Games',
        'twilight' => 'The Twilight Saga',
        'mad max' => 'Mad Max',
        'planet of the apes' => 'Planet of the Apes',
        'back to the future' => 'Back to the Future',
        'men in black' => 'Men in Black',
        'the hangover' => 'The Hangover',
        'saw' => 'Saw',
        'jigsaw' => 'Saw',
        'the godfather' => 'The Godfather',
        'bourne' => 'Bourne',
        'rocky' => 'Rocky',
        'creed' => 'Rocky',
        'bad boys' => 'Bad Boys',

        'shrek' => 'Shrek',
        'puss in boots' => 'Shrek',
        'toy story' => 'Toy Story',
        'ice age' => 'Ice Age',
        'despicable me' => 'Despicable Me',
        'minions' => 'Despicable Me',
        'kung fu panda' => 'Kung Fu Panda',
        'how to train your dragon' => 'How to Train Your Dragon',
        'cars' => 'Cars',

        'final destination' => 'Final Destination',
        'the addams family' => 'The Addams Family',
        'addams family' => 'The Addams Family',
        'lethal weapon' => 'Lethal Weapon',
        'a nightmare on elm street' => 'A Nightmare on Elm Street',
        'nightmare on elm street' => 'A Nightmare on Elm Street',
        'friday the 13th' => 'Friday the 13th',
        'halloween' => 'Halloween',
        'child\'s play' => "Child's Play",
        'chucky' => "Child's Play",
        'underworld' => 'Underworld',
        'resident evil' => 'Resident Evil',
        'the purge' => 'The Purge',
        'purge' => 'The Purge',
        'عمر وسلمى' => 'عمر وسلمى',
    ];

    /**
     * Detect if a title or folder hierarchy belongs to a known movie franchise or collection.
     */
    public function detectFranchiseOrCollection(string $title, string $parentFolder = '', string $grandparentFolder = ''): ?string
    {
        $titleLower = strtolower(trim($title));
        $checkParent = trim($parentFolder);
        $checkGrandparent = trim($grandparentFolder);

        // Explicitly exclude TV Series known to clash with movie collections (e.g. The Lord of the Rings: The Rings of Power)
        if (str_contains($titleLower, 'rings of power') || str_contains(strtolower($checkParent), 'rings of power') || str_contains(strtolower($checkGrandparent), 'rings of power')) {
            return null;
        }

        // 1. If parent is a number or year, unwrap to grandparent
        if (preg_match('/^(\d{1,2}|19\d\d|20\d\d|cd\d+|disc\s*\d+|part\s*\d+)$/i', $checkParent)) {
            $checkParent = $checkGrandparent;
        }

        // 2. Check explicit Collection/Trilogy/Saga in folder names
        $colRegex = '/^(.*?)(?:\s+(?:Collection|Trilogy|Anthology|Saga|Boxset|Pentalogy|Hexalogy|Heptalogy|Octalogy|Duology|Tetralogy|Franchise|Series|مجموعة|سلسلة))(?:\s+.*)?$/ui';
        if (preg_match($colRegex, $checkParent, $m)) {
            $cleaned = preg_replace('/(?:\s+\d+\s*-\s*\d+|\s+[IVXLCDM]+\s*-\s*[IVXLCDM]+|\s+19\d\d\s*-\s*20\d\d)$/i', '', trim($m[1]));

            return trim($cleaned);
        }
        if ($checkGrandparent && preg_match($colRegex, $checkGrandparent, $gm)) {
            $cleaned = preg_replace('/(?:\s+\d+\s*-\s*\d+|\s+[IVXLCDM]+\s*-\s*[IVXLCDM]+|\s+19\d\d\s*-\s*20\d\d)$/i', '', trim($gm[1]));

            return trim($cleaned);
        }

        // 3. Match against canonical franchise database
        $searchTerms = [
            strtolower(trim($title)),
            strtolower($checkParent),
            strtolower($checkGrandparent),
        ];

        foreach ($this->canonicalFranchises as $keyword => $canonicalName) {
            foreach ($searchTerms as $term) {
                if (empty($term)) {
                    continue;
                }
                if ($term === $keyword || str_starts_with($term, $keyword) || str_contains($term, $keyword)) {
                    return $canonicalName;
                }
            }
        }

        return null;
    }

    public function cleanTitleString(string $raw): string
    {
        $s = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $raw);
        $s = preg_replace('/\[[^\]]*\]/u', ' ', $s);
        $s = preg_replace('/\([^\)]*\)/u', ' ', $s);
        $s = preg_replace('/\{[^\}]*\}/u', ' ', $s);
        $s = preg_replace('/[\[\]\(\)\{\}]/u', ' ', $s);
        $s = str_replace(['"', "'", '', ''], '', $s);

        // Strip leading Arabic prefix words: مسلسل, فيلم, برنامج
        $s = preg_replace('/^(?:مسلسل|فيلم|فلم|برنامج)\s+/ui', '', trim($s));

        // Strip season and episode markers
        $s = preg_replace('/\b(?:Season|Series|Staffel|Saison|الموسم|موسم|الجزء|جزء)\s*(\d+|[\p{Arabic}\s]+?)?\b/ui', ' ', $s);
        $s = preg_replace('/\b(?:الحلقة|حلقة|ح)\s*(\d+|[\p{Arabic}\s]+?)?\b/ui', ' ', $s);
        $s = preg_replace('/\b(?:Ep|Episode|E)\s*(\d+)\b/i', ' ', $s);
        $s = preg_replace('/\bS\d{1,2}\b/i', ' ', $s);
        $s = preg_replace('/\b(?:عشر|عشرة|وعشرون|وعشرين|والثلاثون|والثلاثين|والاخيرة|والأخيرة|و الأخيرة|و الاخيرة)\b/ui', ' ', $s);
        $s = preg_replace('/\b(?:2160p|1440p|1080p|1080i|720p|576p|540p|480p|360p|240p|4k|2k|bluray|remux|web-dl|webdl|webrip|hdtv|dvdrip|dvd|x264|x265|hevc|aac|dts|ac3|atmos|hdr|10bit|8bit|ddp5\.1|ddp)\b/i', ' ', $s);

        // Strip Arabic release tags
        $s = preg_replace('/\b(?:مترجم|مدبلج|نسخة\s*اصلية|نسخة\s*أصلية|نسخة|حصريا|جودة\s*عالية|عالي\s*الجودة|كامل|بلوراي|دي\s*في\s*دي|اونلاين|مشاهدة|تحميل)\b/ui', ' ', $s);

        $s = preg_replace('/\b(19\d\d|20\d\d)\b/', ' ', $s);

        // Protect hyphenated names (Spider-Man, X-Men, Ant-Man, Iron-Man)
        $s = preg_replace('/\b(spider)-(man)\b/i', 'Spider###Man', $s);
        $s = preg_replace('/\b(x)-(men)\b/i', 'X###Men', $s);
        $s = preg_replace('/\b(ant)-(man)\b/i', 'Ant###Man', $s);
        $s = preg_replace('/\b(iron)-(man)\b/i', 'Iron###Man', $s);
        $s = preg_replace('/\b(nine)-(nine)\b/i', 'Nine###Nine', $s);

        // Convert dots, underscores, plus signs, and hyphens to spaces
        $s = preg_replace('/[._+\-]/u', ' ', $s);
        $s = str_replace('###', '-', $s);

        // Collapse whitespace
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = trim($s, " \t\n\r\0\x0B-_.:");

        // Only apply English Title Case if there are Latin letters and not primarily Arabic
        if (preg_match('/[a-zA-Z]/', $s) && ! preg_match('/[\p{Arabic}]/u', $s)) {
            $s = $this->toProperTitleCase($s);
        }

        return $s;
    }

    public function toProperTitleCase(string $title): string
    {
        $rawWords = explode(' ', $title);
        $minorWords = ['a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'in', 'nor', 'of', 'on', 'or', 'so', 'the', 'to', 'up', 'yet', 'with', 'from'];

        $result = [];
        foreach ($rawWords as $idx => $word) {
            $prevWordEndedWithColon = ($idx > 0 && str_ends_with($rawWords[$idx - 1], ':'));

            // Handle hyphenated words (e.g. Spider-Man)
            if (str_contains($word, '-')) {
                $parts = explode('-', $word);
                $capitalizedParts = array_map(function ($p) {
                    if (empty($p)) {
                        return $p;
                    }

                    return ucfirst(strtolower($p));
                }, $parts);
                $word = implode('-', $capitalizedParts);
            } else {
                $lower = strtolower($word);
                if ($idx === 0 || $prevWordEndedWithColon || ! in_array($lower, $minorWords)) {
                    $word = ucfirst($lower);
                } else {
                    $word = $lower;
                }
            }
            $result[] = $word;
        }

        return implode(' ', $result);
    }

    public function isSampleOrExtra(string $filename, string $parentFolder): bool
    {
        $parentLower = strtolower(trim($parentFolder));
        if (in_array($parentLower, ['sample', 'samples', 'trailer', 'trailers', 'extra', 'extras', 'featurette', 'featurettes', 'behind the scenes', 'deleted scenes', 'bonus', 'shorts'])) {
            return true;
        }

        $base = strtolower(pathinfo($filename, PATHINFO_FILENAME));

        if (in_array($base, ['sample', 'trailer', 'teaser', 'extra', 'featurette', 'preview', 'short'])) {
            return true;
        }

        if (preg_match('/[\s.\-_\[\(](trailer|teaser|sample|featurette|behindthescenes|deleted[-_.\s]*scene|preview|bonus|short)[\s.\-_\]\)]*$/i', $base)) {
            return true;
        }

        if (preg_match('/^(sample|trailer|teaser)[\s.\-_]/i', $base)) {
            return true;
        }

        return false;
    }
}
