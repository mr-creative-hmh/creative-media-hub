<?php

namespace App\Services\Organizer;

class SceneNameParserService
{
    /**
     * Map of Arabic ordinal words to integer values.
     */
    protected array $arabicNumerals = [
        'الاولى' => 1, 'الاول' => 1, 'الأولى' => 1, 'الأول' => 1,
        'الثانية' => 2, 'الثاني' => 2,
        'الثالثة' => 3, 'الثالث' => 3,
        'الرابعة' => 4, 'الرابع' => 4,
        'الخامسة' => 5, 'الخامس' => 5,
        'السادسة' => 6, 'السادس' => 6,
        'السابعة' => 7, 'السابع' => 7,
        'الثامنة' => 8, 'الثامن' => 8,
        'التاسعة' => 9, 'التاسع' => 9,
        'العاشرة' => 10, 'العاشر' => 10,
        'الحادية عشرة' => 11, 'الحادي عشر' => 11,
        'الثانية عشرة' => 12, 'الثاني عشر' => 12,
        'الثالثة عشرة' => 13, 'الثالث عشر' => 13,
        'الرابعة عشرة' => 14, 'الرابع عشر' => 14,
        'الخامسة عشرة' => 15, 'الخامس عشر' => 15,
        'السادسة عشرة' => 16, 'السادس عشر' => 16,
        'السابعة عشرة' => 17, 'السابع عشر' => 17,
        'الثامنة عشرة' => 18, 'الثامن عشر' => 18,
        'التاسعة عشرة' => 19, 'التاسع عشر' => 19,
        'العشرون' => 20, 'العشرين' => 20,
    ];

    /**
     * Generic folder names that should not be used as media titles.
     */
    protected array $genericFolderNames = [
        'movies', 'movie', 'films', 'film', 'cinema', 'افلام', 'أفلام', 'فلم', 'فيلم',
        'series', 'tv', 'tv shows', 'tv-shows', 'shows', 'مسلسلات', 'مسلسل', 'برامج',
        'media', 'videos', 'video', 'downloads', 'download', 'incoming', 'completed',
        'new folder', 'temp', 'desktop', 'documents', 'hard drive', 'usb', 'external'
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
        if (!in_array(strtolower($rawExt), $validMediaExts)) {
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

        $working = $baseName;

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
        if (preg_match('/^(.*?)(?:\s+(?:Collection|Trilogy|Anthology|Saga|Boxset|سلسلة|أفلام|سلسلة أفلام))$/ui', trim($parentFolder), $cMatch)) {
            $isCollectionFolder = true;
            $detectedCollectionName = trim($parentFolder);
        } elseif ($grandparentFolder && preg_match('/^(.*?)(?:\s+(?:Collection|Trilogy|Anthology|Saga|Boxset|سلسلة|أفلام|سلسلة أفلام))$/ui', trim($grandparentFolder), $gcMatch)) {
            $isCollectionFolder = true;
            $detectedCollectionName = trim($grandparentFolder);
        }

        // 1. Check 3D
        if (preg_match('/\b(3d|mvc|sbs|tab|half-sbs|half-ou|3d-hsbs)\b/i', $working . ' ' . $parentFolder)) {
            $is3D = true;
        }

        // 2. Extract Edition tags
        $editionPatterns = [
            '/(?:\[|\()?(director\'?s\s*cut)(?:\]|\))?/i' => "Director's Cut",
            '/(?:\[|\()?(extended(?:\s*edition|\s*cut)?)(?:\]|\))?/i' => 'Extended Edition',
            '/(?:\[|\()?(ultimate(?:\s*edition|\s*cut)?)(?:\]|\))?/i' => 'Ultimate Edition',
            '/(?:\[|\()?(richard\s*donner\s*cut)(?:\]|\))?/i' => "Richard Donner Cut",
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
            if (preg_match($pattern, $working . ' ' . $parentFolder)) {
                $edition = $edName;
                break;
            }
        }

        // 2.5 Sequence numbering prefix on Collection Movies (e.g. "1.Ip.Man.2008.mp4", "2.Fast.2.Furious.2003", "1.Batman Begins (2005)")
        if (preg_match('/^(?:E)?(\d{1,3})[.\-\s_]+(.*?)$/i', trim($working), $numPrefixMatch)) {
            $restOfName = trim($numPrefixMatch[2]);
            $hasYearInRest = preg_match('/(19\d\d|20\d\d)/', $restOfName);
            if (($isInsideMovieTree || $isCollectionFolder || $hasYearInRest) && !$isInsideSeriesTree && !$isParentSeasonFolder) {
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
            $episode = (int) ($tvMatch[3] . $tvMatch[4]);
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
        if (preg_match('/^(19\d\d|20\d\d)\s*[-_.]?\s*(.*)$/', $working, $yMatches)) {
            $year = (int) $yMatches[1];
            $working = $yMatches[2];
        } elseif (preg_match_all('/[\[\(]?\b(19\d\d|20\d\d)\b[\]\)]?/', $working, $yMatchesAll)) {
            $foundYears = $yMatchesAll[1];
            $year = (int) end($foundYears);
        }

        // 6. Extract Release Group at end
        if (preg_match('/-(?:\[)?([a-zA-Z0-9\.]+)(?:\])?$/i', $working, $gMatches)) {
            $groupCandidate = $gMatches[1];
            if (!preg_match('/^(?:x264|x265|h264|h265|hevc|1080p|720p|576p|540p|480p|360p|240p|2160p|4k|aac|ddp|mp4|mkv|\d+)$/i', $groupCandidate)) {
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

        // Pattern A: S00E00 Specials / Multi-episode S01E01E02 / S01E01-E02
        if (preg_match('/^(.*?)[._\-\s]+[sS](\d{1,2})[eE](\d{1,3})(?:(?:[eE]|[-_ ])(\d{1,3}))[._\-\s]*(.*?)$/i', $working, $tvMatch)) {
            $type = 'series';
            $isSeriesDetected = true;
            $rawSeriesPart = $tvMatch[1];
            $season = (int) $tvMatch[2];
            $episode = (int) $tvMatch[3];
            $episodeEnd = (int) $tvMatch[4];
            $rawAfterPart = $tvMatch[5];
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
        elseif (preg_match('/^(.*?)(?:[._\-\s]+|^)(?:الموسم|موسم|الجزء|جزء)\s*(\d+|[\p{Arabic}]+)[._\-\s]+(?:الحلقة|حلقة|ح)\s*(\d+|[\p{Arabic}]+)[._\-\s]*(.*?)$/ui', $working, $tvMatch)) {
            $type = 'series';
            $isSeriesDetected = true;
            $rawSeriesPart = $tvMatch[1];
            $season = $this->resolveNumber($tvMatch[2]);
            $episode = $this->resolveNumber($tvMatch[3]);
            $rawAfterPart = $tvMatch[4];
        }
        // Pattern E: Arabic Episode in filename (e.g. مسلسل الاختيار الحلقة 01 or الحلقة 05 or حلقة 12)
        elseif (preg_match('/^(.*?)(?:[._\-\s]+|^)(?:الحلقة|حلقة|ح)\s*(\d+|[\p{Arabic}]+)[._\-\s]*(.*?)$/ui', $working, $tvMatch)) {
            $type = 'series';
            $isSeriesDetected = true;
            $rawSeriesPart = $tvMatch[1];
            $season = $season ?? 1;
            $episode = $this->resolveNumber($tvMatch[2]);
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
            if (($isParentSeasonFolder || $isInsideSeriesTree || (!$isParentGeneric && !empty($parentFolder))) && !$isInsideMovieTree && !$hasYear && !$isCollectionFolder) {
                $type = 'series';
                $isSeriesDetected = true;
                $episode = $candidateEp;
                $season = $season ?? 1;
                $rawAfterPart = $epMatch[2] ?? '';
            }
        }
        // Pattern I: Known pilot/special named episode
        elseif ($parentFolder && !in_array(strtolower($parentFolder), $this->genericFolderNames) && !preg_match('/\b(19\d\d|20\d\d)\b/', $baseName)) {
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
            if (!empty($rawSeriesPart)) {
                $seriesTitle = $this->cleanTitleString($rawSeriesPart);
            }

            // If seriesTitle is missing or generic (e.g. file was just "01.mp4" or "Episode 05.mkv"), resolve from folder hierarchy
            if (empty($seriesTitle) || is_numeric($seriesTitle) || strtolower($seriesTitle) === 'unknown series' || in_array(strtolower($seriesTitle), $this->genericFolderNames)) {
                if ($isParentSeasonFolder && $grandparentFolder && !in_array(strtolower($grandparentFolder), $this->genericFolderNames)) {
                    $seriesTitle = $this->cleanTitleString($grandparentFolder);
                } elseif ($parentFolder && !in_array(strtolower($parentFolder), $this->genericFolderNames)) {
                    $seriesTitle = $this->cleanTitleString($parentFolder);
                } elseif ($grandparentFolder && !in_array(strtolower($grandparentFolder), $this->genericFolderNames)) {
                    $seriesTitle = $this->cleanTitleString($grandparentFolder);
                }
            }

            // Episode Title from remaining string
            if ($rawAfterPart && !$episodeTitle) {
                $cleanEp = $this->cleanTitleString($rawAfterPart);
                if (!empty($cleanEp) && !is_numeric($cleanEp) && !preg_match($qualityTokensRegex, $cleanEp)) {
                    $episodeTitle = $cleanEp;
                }
            }

            // Check parent/grandparent directory for series year if missing
            if (!$year) {
                $dirToCheck = $isParentSeasonFolder ? $grandparentFolder : $parentFolder;
                if ($dirToCheck && preg_match('/\b(19\d\d|20\d\d)\b/', $dirToCheck, $dirYMatch)) {
                    $year = (int) $dirYMatch[1];
                }
            }

            $cleanTitle = $seriesTitle ?: 'Unknown Series';
        } else {
            // Movie Title Extraction
            $isGenericMovieFile = in_array(strtolower(trim($baseName)), ['movie', 'film', 'video', 'main', 'cd1', 'cd2', 'فيلم', 'فلم', 'فيديو']);

            if ($isGenericMovieFile && $parentFolder && !in_array(strtolower($parentFolder), $this->genericFolderNames)) {
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

                if (!$year && $parentFolder && preg_match('/\b(19\d\d|20\d\d)\b/', $parentFolder, $dirYMatch)) {
                    $year = (int) $dirYMatch[1];
                }
            }
        }

        if (empty($cleanTitle)) {
            $cleanTitle = $this->cleanTitleString($baseName);
        }

        // 10. Detect Technical Specs across full path & filename
        $fullSpecsString = $working . ' ' . $filename . ' ' . $parentFolder . ' ' . $grandparentFolder;

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

        if (!$resolution && preg_match('/\b(\d{3,4})x(\d{3,4})\b/i', $fullSpecsString, $dimMatch)) {
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
            'collection_name' => $collectionName ?? $detectedCollectionName ?? null,
            'type' => $type,
            'season' => $season ?? ($type === 'series' ? 1 : null),
            'episode' => $episode ?? ($type === 'series' ? 1 : null),
            'episode_end' => $episodeEnd,
            'episode_title' => $episodeTitle,
            'year' => $year,
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

        $trimmed = trim($val);
        if (isset($this->arabicNumerals[$trimmed])) {
            return $this->arabicNumerals[$trimmed];
        }

        // Try stripping 'ال' if present
        $withoutAl = preg_replace('/^ال/', '', $trimmed);
        if (isset($this->arabicNumerals[$withoutAl])) {
            return $this->arabicNumerals[$withoutAl];
        }

        return 1;
    }

    /**
     * Convert Eastern Arabic numerals (١, ٢, ٣...) to Western (1, 2, 3...)
     */
    public function convertArabicDigits(string $str): string
    {
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin  = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
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
        $s = preg_replace('/\b(?:Season|Series|Staffel|Saison|الموسم|موسم|الجزء|جزء)\s*(\d+|[\p{Arabic}]+)?\b/ui', ' ', $s);
        $s = preg_replace('/\b(?:الحلقة|حلقة|ح)\s*(\d+|[\p{Arabic}]+)?\b/ui', ' ', $s);
        $s = preg_replace('/\bS\d{1,2}\b/i', ' ', $s);
        $s = preg_replace('/\b(?:2160p|1440p|1080p|1080i|720p|576p|540p|480p|360p|240p|4k|2k|bluray|remux|web-dl|webdl|webrip|hdtv|dvdrip|dvd|x264|x265|hevc|aac|dts|ac3|atmos|hdr|10bit|8bit|ddp5\.1|ddp)\b/i', ' ', $s);
        
        // Strip Arabic release tags
        $s = preg_replace('/\b(?:مترجم|مدبلج|نسخة\s*اصلية|نسخة\s*أصلية|نسخة|حصريا|جودة\s*عالية|عالي\s*الجودة|كامل|بلوراي|دي\s*في\s*دي|اونلاين|مشاهدة|تحميل)\b/ui', ' ', $s);
        
        $s = preg_replace('/\b(19\d\d|20\d\d)\b/', ' ', $s);

        // Protect hyphenated names (Spider-Man, X-Men, Ant-Man, Iron-Man)
        $s = preg_replace('/\b(spider)-(man)\b/i', 'Spider###Man', $s);
        $s = preg_replace('/\b(x)-(men)\b/i', 'X###Men', $s);
        $s = preg_replace('/\b(ant)-(man)\b/i', 'Ant###Man', $s);
        $s = preg_replace('/\b(iron)-(man)\b/i', 'Iron###Man', $s);

        // Convert dots, underscores, plus signs, and hyphens to spaces
        $s = preg_replace('/[._+\-]/u', ' ', $s);
        $s = str_replace('###', '-', $s);

        // Collapse whitespace
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = trim($s, " \t\n\r\0\x0B-_.:");

        // Only apply English Title Case if there are Latin letters and not primarily Arabic
        if (preg_match('/[a-zA-Z]/', $s) && !preg_match('/[\p{Arabic}]/u', $s)) {
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
                $capitalizedParts = array_map(function($p) use ($minorWords, $idx) {
                    if (empty($p)) return $p;
                    return ucfirst(strtolower($p));
                }, $parts);
                $word = implode('-', $capitalizedParts);
            } else {
                $lower = strtolower($word);
                if ($idx === 0 || $prevWordEndedWithColon || !in_array($lower, $minorWords)) {
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
        $combined = strtolower($filename . ' ' . $parentFolder);
        return (bool) preg_match('/\b(sample|trailer|trailers|featurette|featurettes|behindthescenes|deleted|deletedscenes|extra|extras|preview|bonus|interview|short)\b/i', $combined);
    }
}
