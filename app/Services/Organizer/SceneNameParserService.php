<?php

namespace App\Services\Organizer;

class SceneNameParserService
{
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

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        // Check if hidden or sample file
        $isSample = $this->isSampleOrExtra($filename, $parentFolder);
        $isHidden = str_starts_with($filename, '.') || str_starts_with($filename, '._');

        $type = 'movie';
        $season = null;
        $episode = null;
        $episodeEnd = null;
        $episodeTitle = null;
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

        $rawTitleCandidate = $baseName;

        // 1. Check 3D
        if (preg_match('/\b(3d|mvc|sbs|tab|half-sbs|half-ou|3d-hsbs)\b/i', $rawTitleCandidate . ' ' . $parentFolder)) {
            $is3D = true;
        }

        // 2. Extract Edition tags (Director's Cut, Extended, IMAX, Unrated, etc.)
        $editionPatterns = [
            '/(?:\[|\()?(director\'?s\s*cut)(?:\]|\))?/i' => "Director's Cut",
            '/(?:\[|\()?(extended(?:\s*edition|\s*cut)?)(?:\]|\))?/i' => 'Extended Edition',
            '/(?:\[|\()?(final\s*cut)(?:\]|\))?/i' => 'Final Cut',
            '/(?:\[|\()?(richard\s*donner\s*cut)(?:\]|\))?/i' => "Richard Donner Cut",
            '/(?:\[|\()?(theatrical(?:\s*cut|\s*edition)?)(?:\]|\))?/i' => 'Theatrical Cut',
            '/(?:\[|\()?(unrated(?:\s*edition|\s*cut)?)(?:\]|\))?/i' => 'Unrated',
            '/(?:\[|\()?(remastered)(?:\]|\))?/i' => 'Remastered',
            '/(?:\[|\()?(special\s*edition)(?:\]|\))?/i' => 'Special Edition',
            '/(?:\[|\()?(criterion(?:\s*edition|\s*collection)?)(?:\]|\))?/i' => 'Criterion Collection',
            '/(?:\[|\()?(imax(?:\s*edition)?)(?:\]|\))?/i' => 'IMAX Edition',
            '/(?:\[|\()?(ova|oad)(?:\]|\))?/i' => 'OVA',
        ];

        foreach ($editionPatterns as $pattern => $edName) {
            if (preg_match($pattern, $rawTitleCandidate . ' ' . $parentFolder)) {
                $edition = $edName;
                break;
            }
        }

        // 3. Multi-Part Movie (Part 1, Part 2, CD1, CD2)
        if (preg_match('/(?:\[|\(|\b)(?:part|pt|cd|disc)\s*(\d{1,2})(?:\]|\)|\b)/i', $rawTitleCandidate, $pMatches)) {
            $part = (int) $pMatches[1];
        }

        // 4. Extract Release Group (-NTb, -YTS, -TERMiNAL, -RARBG, [GROUP])
        if (preg_match('/-(?:\[)?([a-zA-Z0-9_\.]+)(?:\])?$/i', $baseName, $gMatches)) {
            $group = $gMatches[1];
        } elseif (preg_match('/\[([a-zA-Z0-9_\.]+)\]$/i', $baseName, $gMatches)) {
            $group = $gMatches[1];
        }

        // 5. Detect TV Series Patterns
        // S01E01E02 / S01E01-E03 (Multi-episode)
        if (preg_match('/[sS](\d{1,2})[eE](\d{1,3})(?:(?:[eE]|[-_ ])(\d{1,3}))/i', $rawTitleCandidate, $mMatches)) {
            $type = 'series';
            $season = (int) $mMatches[1];
            $episode = (int) $mMatches[2];
            $episodeEnd = (int) $mMatches[3];
        }
        // S01E02 or S00E01 (Standard)
        elseif (preg_match('/[sS](\d{1,2})[eE](\d{1,3})/i', $rawTitleCandidate, $mMatches)) {
            $type = 'series';
            $season = (int) $mMatches[1];
            $episode = (int) $mMatches[2];
        }
        // 1x02 (X format)
        elseif (preg_match('/\b(\d{1,2})x(\d{1,3})\b/i', $rawTitleCandidate, $mMatches)) {
            $type = 'series';
            $season = (int) $mMatches[1];
            $episode = (int) $mMatches[2];
        }
        // Season 1 Episode 2
        elseif (preg_match('/\bSeason\s*(\d{1,2})\s*Episode\s*(\d{1,3})\b/i', $rawTitleCandidate, $mMatches)) {
            $type = 'series';
            $season = (int) $mMatches[1];
            $episode = (int) $mMatches[2];
        }
        // Date-based daily shows: YYYY-MM-DD or YYYY.MM.DD
        elseif (preg_match('/\b(20\d\d|19\d\d)[.\-_](\d{2})[.\-_](\d{2})\b/', $rawTitleCandidate, $dMatches)) {
            $type = 'series';
            $airDate = "{$dMatches[1]}-{$dMatches[2]}-{$dMatches[3]}";
            $season = (int) $dMatches[1];
            $episode = (int) ($dMatches[2] . $dMatches[3]);
        }
        // EP02 / Episode 2
        elseif (preg_match('/^(?:ep|episode)?\s*(\d{1,3})\s*[-_ ]/i', $rawTitleCandidate, $mMatches)) {
            $episode = (int) $mMatches[1];
            $type = 'series';
        }

        // Folder-based Series detection (Breaking Bad/Season 01/01.mkv)
        if ($parentFolder && preg_match('/^(?:Season|Series|Staffel|Saison)\s*(\d{1,2})$/i', trim($parentFolder), $sMatches)) {
            $type = 'series';
            $isParentSeasonFolder = true;
            if ($season === null) $season = (int) $sMatches[1];
        } elseif ($parentFolder && preg_match('/^[sS](\d{1,2})$/i', trim($parentFolder), $sMatches)) {
            $type = 'series';
            $isParentSeasonFolder = true;
            if ($season === null) $season = (int) $sMatches[1];
        } elseif ($parentFolder && preg_match('/\b(?:Season|Series|Staffel|Saison)\s*(\d{1,2})\b/i', $parentFolder, $sMatches)) {
            $type = 'series';
            if ($season === null) $season = (int) $sMatches[1];
        }
        // Series folder with raw episode name (e.g. Breaking Bad/Pilot.mkv or Game of Thrones/Winter Is Coming.mkv)
        elseif ($parentFolder && !in_array(strtolower($parentFolder), ['movies', 'films', 'downloads', 'video', 'videos']) && !preg_match('/\b(19\d\d|20\d\d)\b/', $baseName)) {
            $knownEpisodeNames = ['pilot', 'winter is coming', 'the duel', 'finale', 'prologue', 'special', 'ova'];
            $cleanBaseLower = strtolower(trim(preg_replace('/[._\-]/', ' ', $baseName)));
            if (in_array($cleanBaseLower, $knownEpisodeNames) || $edition === 'OVA') {
                $type = 'series';
                $season = 1;
                $episode = 1;
                $episodeTitle = $this->toProperTitleCase($cleanBaseLower);
            }
        }

        // 6. Detect Year (1900-2099)
        // Year at beginning: 2020 Movie Title.mkv
        if (preg_match('/^(19\d\d|20\d\d)\s*[-_.]?\s*(.*)$/', $rawTitleCandidate, $yMatches)) {
            $year = (int) $yMatches[1];
            $rawTitleCandidate = $yMatches[2];
        }
        // Years anywhere in filename (take latest plausible year or parent folder)
        elseif (preg_match_all('/\b(19\d\d|20\d\d)\b/', $rawTitleCandidate, $yMatchesAll)) {
            $foundYears = $yMatchesAll[1];
            $year = (int) end($foundYears);
        } elseif ($parentFolder && preg_match('/\b(19\d\d|20\d\d)\b/', $parentFolder, $pyMatches)) {
            $year = (int) $pyMatches[1];
        }

        // 7. Detect Resolution
        $combString = $rawTitleCandidate . ' ' . $parentFolder;
        if (preg_match('/\b(2160p|4k|uhd)\b/i', $combString)) {
            $resolution = '4K UHD';
        } elseif (preg_match('/\b(1080p|1080i|fhd)\b/i', $combString)) {
            $resolution = '1080p FHD';
        } elseif (preg_match('/\b(720p|hd)\b/i', $combString)) {
            $resolution = '720p HD';
        } elseif (preg_match('/\b(480p|sd|576p)\b/i', $combString)) {
            $resolution = '480p SD';
        }

        // 8. Detect Video Codec & Audio
        if (preg_match('/\b(x265|h265|hevc)\b/i', $combString)) {
            $codec = 'HEVC / H.265';
        } elseif (preg_match('/\b(x264|h264|avc)\b/i', $combString)) {
            $codec = 'H.264 / AVC';
        } elseif (preg_match('/\b(av1)\b/i', $combString)) {
            $codec = 'AV1';
        } elseif (preg_match('/\b(xvid|divx)\b/i', $combString)) {
            $codec = 'XviD';
        }

        if (preg_match('/\b(atmos)\b/i', $combString)) {
            $audio = 'Dolby Atmos';
        } elseif (preg_match('/\b(truehd)\b/i', $combString)) {
            $audio = 'Dolby TrueHD';
        } elseif (preg_match('/\b(dts-hd|dts hd|dts-ma)\b/i', $combString)) {
            $audio = 'DTS-HD MA';
        } elseif (preg_match('/\b(dts)\b/i', $combString)) {
            $audio = 'DTS';
        } elseif (preg_match('/\b(dd\+|eac3|ddp)\b/i', $combString)) {
            $audio = 'Dolby Digital Plus';
        } elseif (preg_match('/\b(dd5\.1|ac3|5\.1)\b/i', $combString)) {
            $audio = '5.1 Surround';
        } elseif (preg_match('/\b(aac)\b/i', $combString)) {
            $audio = 'AAC';
        }

        // 9. Detect Source
        if (preg_match('/\b(bluray|remux|bdrip|brrip)\b/i', $combString)) {
            $source = 'BluRay';
        } elseif (preg_match('/\b(web-dl|webdl|webrip|web)\b/i', $combString)) {
            $source = 'WEBRip';
        } elseif (preg_match('/\b(hdtv|pdtv|dsr)\b/i', $combString)) {
            $source = 'HDTV';
        } elseif (preg_match('/\b(dvdrip|dvd)\b/i', $combString)) {
            $source = 'DVDRip';
        }

        // 10. Extract Episode Title (if series) & Clean Show / Movie Title
        $cleanWorking = $rawTitleCandidate;

        // Series with S01E01 Episode Title extraction
        if ($type === 'series') {
            // Pattern: Show Name - S01E01 - Episode Title
            if (preg_match('/^(.*?)\s*[-_.]?\s*[sS](\d{1,2})[eE](\d{1,3})\s*[-_.]?\s*(.*?)$/i', $cleanWorking, $sMatch)) {
                $seriesPart = $sMatch[1];
                $afterPart = $sMatch[4];
                $cleanSeries = $this->cleanTitleString($seriesPart);
                $cleanEpTitle = $this->cleanEpisodeTitleString($afterPart);
                if (!empty($cleanEpTitle)) {
                    $episodeTitle = $cleanEpTitle;
                }
                $cleanTitle = !empty($cleanSeries) ? $cleanSeries : $this->cleanTitleString($parentFolder ?: $baseName);
            }
            // Pattern: Show Name 1x01 Episode Title
            elseif (preg_match('/^(.*?)\s*[-_.]?\s*(\d{1,2})x(\d{1,3})\s*[-_.]?\s*(.*?)$/i', $cleanWorking, $sMatch)) {
                $seriesPart = $sMatch[1];
                $afterPart = $sMatch[4];
                $cleanSeries = $this->cleanTitleString($seriesPart);
                $cleanEpTitle = $this->cleanEpisodeTitleString($afterPart);
                if (!empty($cleanEpTitle)) {
                    $episodeTitle = $cleanEpTitle;
                }
                $cleanTitle = !empty($cleanSeries) ? $cleanSeries : $this->cleanTitleString($parentFolder ?: $baseName);
            } else {
                $cleanTitle = $this->extractAndCleanStandardTitle($cleanWorking);
            }

            // Path-based hierarchy fallback for Series
            if ($isParentSeasonFolder && $grandparentFolder) {
                $cleanTitle = $this->cleanTitleString($grandparentFolder);
            } elseif ($parentFolder && (empty($cleanTitle) || is_numeric($cleanTitle) || preg_match('/^\d{1,3}$/', $cleanTitle) || !empty($episodeTitle))) {
                $cleanTitle = $this->cleanTitleString($parentFolder);
            }
        } else {
            // Movies
            $cleanTitle = $this->extractAndCleanStandardTitle($cleanWorking);
        }

        if (empty($cleanTitle)) {
            $cleanTitle = $this->cleanTitleString($baseName);
        }

        return [
            'original_filename' => $filename,
            'title' => $cleanTitle,
            'clean_title' => $cleanTitle,
            'series_title' => $type === 'series' ? $cleanTitle : null,
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

    protected function extractAndCleanStandardTitle(string $raw): string
    {
        $title = $raw;

        // Cut off scene quality tags
        $cutoffPatterns = [
            '/[sS]\d{1,2}[eE]\d{1,3}.*$/i',
            '/\b\d{1,2}x\d{1,3}.*$/i',
            '/\b(?:Season|Series|Staffel|Saison)\s*\d{1,2}\b.*$/i',
            '/\bS\d{1,2}\b.*$/i',
            '/\b(19\d\d|20\d\d)\b.*$/',
            '/\b(2160p|1080p|720p|480p|4k|bluray|remux|web-dl|webdl|webrip|hdtv|dvdrip|x264|x265|hevc|aac)\b.*$/i',
        ];

        foreach ($cutoffPatterns as $pattern) {
            if (preg_match($pattern, $title)) {
                $title = preg_replace($pattern, '', $title);
                break;
            }
        }

        return $this->cleanTitleString($title);
    }

    public function cleanTitleString(string $raw): string
    {
        // Strip emojis
        $s = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $raw);

        // Strip bracket tags like [YTS.MX], [1080p], (2024), {rarbg}
        $s = preg_replace('/\[[^\]]*\]/', ' ', $s);
        $s = preg_replace('/\([^\)]*\)/', ' ', $s);
        $s = preg_replace('/\{[^\}]*\}/', ' ', $s);
        $s = preg_replace('/[\[\]\(\)\{\}]/', ' ', $s);

        // Strip quotes
        $s = str_replace(['"', "'", '“', '”'], '', $s);

        // Strip Season tokens
        $s = preg_replace('/\b(?:Season|Series|Staffel|Saison)\s*\d{1,2}\b/i', ' ', $s);
        $s = preg_replace('/\bS\d{1,2}\b/i', ' ', $s);
        $s = preg_replace('/\b(?:Complete|Anthology|Boxset|Collection)\b/i', ' ', $s);

        // Strip quality tags
        $s = preg_replace('/\b(?:2160p|1080p|720p|480p|4k|bluray|remux|web-dl|webdl|webrip|hdtv|dvdrip|x264|x265|hevc|aac|dts|ac3|atmos|hdr|mvc)\b/i', ' ', $s);

        // Strip years
        $s = preg_replace('/\b(19\d\d|20\d\d)\b/', ' ', $s);

        // Protect known hyphenated names (Spider-Man, X-Men, Ant-Man, Iron-Man)
        $protectedHyphens = [
            'Spider-Man' => 'XPROTECTEDSPIDERMANX',
            'X-Men' => 'XPROTECTEDXMENX',
            'Ant-Man' => 'XPROTECTEDANTMANX',
            'Iron-Man' => 'XPROTECTEDIRONMANX',
            'He-Man' => 'XPROTECTEDHEMANX',
            'Sci-Fi' => 'XPROTECTEDSCIFIX',
        ];

        foreach ($protectedHyphens as $orig => $placeholder) {
            $s = preg_replace('/\b' . preg_quote($orig, '/') . '\b/i', $placeholder, $s);
        }

        // Replace separators (dots, underscores, dashes, @, #, $) with spaces
        $s = preg_replace('/[._\-@#$]/u', ' ', $s);
        $s = trim(preg_replace('/\s+/u', ' ', $s));

        // Restore protected hyphens
        foreach ($protectedHyphens as $orig => $placeholder) {
            $s = str_replace($placeholder, $orig, $s);
        }

        // Preserve case if Unicode (Japanese, Arabic, Cyrillic, Chinese)
        if (preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Arabic}\p{Cyrillic}\p{Hangul}]/u', $s)) {
            return $s;
        }

        return $this->toProperTitleCase($s);
    }

    public function toProperTitleCase(string $title): string
    {
        $words = preg_split('/\s+/', strtolower($title));
        if (empty($words) || empty($words[0])) return '';

        $lowercaseWords = [
            'a', 'an', 'the', 'and', 'but', 'or', 'for', 'nor', 'on', 'at', 'to', 'from',
            'by', 'with', 'in', 'of', 'over', 'into'
        ];

        $capitalized = [];
        $afterColon = false;

        foreach ($words as $idx => $w) {
            $hasTrailingColon = str_ends_with($w, ':');
            $cleanWord = rtrim($w, ':');

            if ($cleanWord === 'xprotectedspidermanx' || str_contains($cleanWord, 'spider-man')) {
                $capitalized[] = 'Spider-Man' . ($hasTrailingColon ? ':' : '');
                $afterColon = $hasTrailingColon;
                continue;
            }
            if ($cleanWord === 'xprotectedxmenx' || str_contains($cleanWord, 'x-men')) {
                $capitalized[] = 'X-Men' . ($hasTrailingColon ? ':' : '');
                $afterColon = $hasTrailingColon;
                continue;
            }

            $wCap = ucfirst($cleanWord) . ($hasTrailingColon ? ':' : '');

            if ($idx === 0 || $afterColon || !in_array($cleanWord, $lowercaseWords)) {
                $capitalized[] = $wCap;
            } else {
                $capitalized[] = $w;
            }

            $afterColon = $hasTrailingColon;
        }

        return implode(' ', $capitalized);
    }

    protected function cleanEpisodeTitleString(string $raw): string
    {
        // Strip group and quality tags from the tail
        $s = preg_replace('/-(?:\[)?[a-zA-Z0-9_\.]+(?:\])?$/i', '', $raw);
        $s = preg_replace('/\[[^\]]*\]/', '', $s);
        $s = preg_replace('/\(.*?\)/', '', $s);
        $s = preg_replace('/\b(2160p|1080p|720p|480p|4k|bluray|remux|web-dl|webdl|webrip|hdtv|dvdrip|x264|x265|hevc|aac)\b.*$/i', '', $s);
        $s = preg_replace('/[._\-]/', ' ', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));

        return $this->toProperTitleCase($s);
    }

    public function isSampleOrExtra(string $filename, string $parentFolder): bool
    {
        $lower = strtolower($filename . ' ' . $parentFolder);
        return preg_match('/\b(sample|trailer|trailers|featurette|featurettes|extras|behindthescenes|deletedscenes|short)\b/i', $lower) === 1;
    }
}
