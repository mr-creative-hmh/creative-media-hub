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

        // 1. Check 3D
        if (preg_match('/\b(3d|mvc|sbs|tab|half-sbs|half-ou|3d-hsbs)\b/i', $working . ' ' . $parentFolder)) {
            $is3D = true;
        }

        // 2. Extract Edition tags
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
            if (preg_match($pattern, $working . ' ' . $parentFolder)) {
                $edition = $edName;
                break;
            }
        }

        // 3. Multi-Part Movie
        if (preg_match('/(?:\[|\(|\b)(?:part|pt|cd|disc)\s*(\d{1,2})(?:\]|\)|\b)/i', $working, $pMatches)) {
            $part = (int) $pMatches[1];
        }

        // 4. Extract Date-based daily shows FIRST (e.g. The Daily Show 2023-01-15.mkv)
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

        // 5. Extract Year:
        // Priority 1: Year at beginning (2020 Movie Title.mkv)
        if (preg_match('/^(19\d\d|20\d\d)\s*[-_.]?\s*(.*)$/', $working, $yMatches)) {
            $year = (int) $yMatches[1];
            $working = $yMatches[2];
        }
        // Priority 2: Year in parentheses or brackets in filename
        elseif (preg_match_all('/[\[\(]?\b(19\d\d|20\d\d)\b[\]\)]?/', $working, $yMatchesAll)) {
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

        // 7. Detect Folder-based Series context
        if ($parentFolder && preg_match('/^(?:Season|Series|Staffel|Saison)\s*(\d{1,2})$/i', trim($parentFolder), $sMatches)) {
            $type = 'series';
            $isParentSeasonFolder = true;
            $season = (int) $sMatches[1];
        } elseif ($parentFolder && preg_match('/^[sS](\d{1,2})$/i', trim($parentFolder), $sMatches)) {
            $type = 'series';
            $isParentSeasonFolder = true;
            $season = (int) $sMatches[1];
        }

        // 8. Detect TV Series Patterns
        $isSeriesDetected = $isParentSeasonFolder;
        $rawSeriesPart = '';
        $rawAfterPart = '';

        // S00E00 Specials / Multi-episode S01E01E02 / S01E01-E02
        if (preg_match('/^(.*?)[._\-\s]+[sS](\d{1,2})[eE](\d{1,3})(?:(?:[eE]|[-_ ])(\d{1,3}))[._\-\s]*(.*?)$/i', $working, $tvMatch)) {
            $type = 'series';
            $isSeriesDetected = true;
            $rawSeriesPart = $tvMatch[1];
            $season = (int) $tvMatch[2];
            $episode = (int) $tvMatch[3];
            $episodeEnd = (int) $tvMatch[4];
            $rawAfterPart = $tvMatch[5];
        }
        // Standard S01E02 / S00E01 / S00E00
        elseif (preg_match('/^(.*?)(?:[._\-\s]|^)[sS](\d{1,2})[eE](\d{1,3})[._\-\s]*(.*?)$/i', $working, $tvMatch)) {
            $type = 'series';
            $isSeriesDetected = true;
            $rawSeriesPart = $tvMatch[1];
            $season = (int) $tvMatch[2];
            $episode = (int) $tvMatch[3];
            $rawAfterPart = $tvMatch[4];
        }
        // 1x02 (X format, e.g. Show-Name-1x01-Episode_Title)
        elseif (preg_match('/^(.*?)[._\-\s]+(\d{1,2})x(\d{1,3})[._\-\s]*(.*?)$/i', $working, $tvMatch)) {
            $type = 'series';
            $isSeriesDetected = true;
            $rawSeriesPart = $tvMatch[1];
            $season = (int) $tvMatch[2];
            $episode = (int) $tvMatch[3];
            $rawAfterPart = $tvMatch[4];
        }
        // Season 1 Episode 2
        elseif (preg_match('/^(.*?)[._\-\s]+Season\s*(\d{1,2})\s*Episode\s*(\d{1,3})[._\-\s]*(.*?)$/i', $working, $tvMatch)) {
            $type = 'series';
            $isSeriesDetected = true;
            $rawSeriesPart = $tvMatch[1];
            $season = (int) $tvMatch[2];
            $episode = (int) $tvMatch[3];
            $rawAfterPart = $tvMatch[4];
        }
        // Numbered episode filename inside Season folder: 05 - Kissed by Fire.mkv or 05.mkv
        elseif ($isParentSeasonFolder && preg_match('/^(\d{1,3})\s*[-_.]?\s*(.*?)$/', $working, $epMatch)) {
            $type = 'series';
            $isSeriesDetected = true;
            $episode = (int) $epMatch[1];
            $rawAfterPart = $epMatch[2];
            $seriesTitle = $grandparentFolder ? $this->cleanTitleString($grandparentFolder) : 'Unknown Series';
        }
        // Series folder with single named episode (e.g. Breaking Bad/Pilot.mkv)
        elseif ($parentFolder && !in_array(strtolower($parentFolder), ['movies', 'films', 'downloads', 'media', 'videos', 'incoming']) && !preg_match('/\b(19\d\d|20\d\d)\b/', $baseName)) {
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

        // 9. Quality and Codec Delimiters
        $qualityTokensRegex = '/\b(2160p|1440p|1080p|1080i|720p|576p|540p|480p|360p|240p|4k|2k|uhd|fhd|hd|sd|pal|ntsc|bluray|blu-ray|remux|bdrip|brrip|web-dl|webdl|webrip|web|hdtv|pdtv|dvdrip|dvd|vhsrip|vhs|x264|x265|h264|h265|hevc|avc|av1|xvid|divx|10bit|8bit|12bit|hdr|hdr10|hdr10\+|dv|dovi|dolbyvision|aac|aac2\.0|aac5\.1|ddp|ddp5\.1|ddp7\.1|ddp2\.0|dd\+|eac3|ac3|dts|dts-hd|dts-ma|atmos|truehd|flac|mp3|2\.0|5\.1|7\.1)\b/i';

        if ($isSeriesDetected) {
            if (!empty($rawSeriesPart)) {
                if (preg_match('/^(.*?)[._\-\s]+(19\d\d|20\d\d)$/i', $rawSeriesPart, $yMatch)) {
                    $rawSeriesPart = $yMatch[1];
                    $year = $year ?: (int) $yMatch[2];
                }
                $seriesTitle = $this->cleanTitleString($rawSeriesPart);
            }

            if (!empty($rawAfterPart)) {
                // Remove bracketed content before truncating
                $cleanedRaw = preg_replace('/\[[^\]]*\]/', '', $rawAfterPart);
                if (preg_match($qualityTokensRegex, $cleanedRaw, $qMatch, PREG_OFFSET_CAPTURE)) {
                    $rawEp = substr($cleanedRaw, 0, $qMatch[0][1]);
                } else {
                    $rawEp = $cleanedRaw;
                }

                $cleanEp = $this->cleanEpisodeTitleString($rawEp);
                if (!empty($cleanEp)) {
                    $episodeTitle = $cleanEp;
                }
            }

            if (empty($seriesTitle) || is_numeric($seriesTitle) || strtolower($seriesTitle) === 'unknown series') {
                if ($isParentSeasonFolder && $grandparentFolder) {
                    $seriesTitle = $this->cleanTitleString($grandparentFolder);
                } elseif ($parentFolder && !in_array(strtolower($parentFolder), ['downloads', 'movies', 'media', 'tv shows', 'incoming', 'videos'])) {
                    $seriesTitle = $this->cleanTitleString($parentFolder);
                }
            }

            // Also check parent/grandparent directory for series year if still missing (e.g. "Rick and Morty (2013)")
            if (!$year) {
                $dirToCheck = $isParentSeasonFolder ? $grandparentFolder : $parentFolder;
                if ($dirToCheck && preg_match('/\b(19\d\d|20\d\d)\b/', $dirToCheck, $dirYMatch)) {
                    $year = (int) $dirYMatch[1];
                }
            }

            $cleanTitle = $seriesTitle ?: 'Unknown Series';
        } else {
            // Movie Title Extraction
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

        // Dimension based resolution fallback (e.g. 1920x1080, 1280x720, 720x480, 640x360)
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
        $s = preg_replace('/\[[^\]]*\]/', ' ', $s);
        $s = preg_replace('/\([^\)]*\)/', ' ', $s);
        $s = preg_replace('/\{[^\}]*\}/', ' ', $s);
        $s = preg_replace('/[\[\]\(\)\{\}]/', ' ', $s);
        $s = str_replace(['"', "'", '“', '”'], '', $s);

        $s = preg_replace('/\b(?:Season|Series|Staffel|Saison)\s*\d{1,2}\b/i', ' ', $s);
        $s = preg_replace('/\bS\d{1,2}\b/i', ' ', $s);
        $s = preg_replace('/\b(?:2160p|1440p|1080p|1080i|720p|576p|540p|480p|360p|240p|4k|2k|bluray|remux|web-dl|webdl|webrip|hdtv|dvdrip|dvd|x264|x265|hevc|aac|dts|ac3|atmos|hdr|10bit|8bit|ddp5\.1|ddp)\b/i', ' ', $s);
        $s = preg_replace('/\b(19\d\d|20\d\d)\b/', ' ', $s);

        // Protect hyphenated names (Spider-Man, X-Men, Ant-Man, Iron-Man)
        $protected = [
            'Spider-Man' => 'XPROTECTEDSPIDERMANX',
            'X-Men' => 'XPROTECTEDXMENX',
            'Ant-Man' => 'XPROTECTEDANTMANX',
            'Iron-Man' => 'XPROTECTEDIRONMANX',
        ];
        foreach ($protected as $orig => $token) {
            $s = preg_replace('/\b' . preg_quote($orig, '/') . '\b/i', $token, $s);
        }

        $s = preg_replace('/[._\-@#$]/u', ' ', $s);
        $s = trim(preg_replace('/\s+/u', ' ', $s));

        foreach ($protected as $orig => $token) {
            $s = str_replace($token, $orig, $s);
        }

        return $this->toProperTitleCase($s);
    }

    protected function cleanEpisodeTitleString(string $raw): string
    {
        // 1. Remove bracketed blocks completely [1080p FHD], (2025), etc.
        $s = preg_replace('/\[[^\]]*\]/', ' ', $raw);
        $s = preg_replace('/\(.*?\)/', ' ', $s);
        $s = preg_replace('/\{.*?\}/', ' ', $s);

        // 2. Truncate at quality/codec tokens or release tags
        $s = preg_replace('/\b(2160p|1440p|1080p|1080i|720p|576p|540p|480p|360p|240p|4k|2k|uhd|fhd|hd|sd|bluray|blu-ray|remux|bdrip|brrip|web-dl|webdl|webrip|web|hdtv|pdtv|dvdrip|dvd|x264|x265|h264|h265|hevc|avc|av1|xvid|divx|10bit|8bit|hdr|hdr10|dv|aac|ddp|ac3|dts|mp3)\b.*$/i', ' ', $s);

        // 3. Remove trailing scene group or unclosed brackets
        $s = preg_replace('/[-_.\s]*[\[\(\{].*$/', ' ', $s);
        $s = preg_replace('/[._\-]/', ' ', $s);

        // 4. Strip surrounding symbols and whitespace
        $s = trim($s, " \t\n\r\0\x0B-_:;,./\[]{}()");
        $s = trim(preg_replace('/\s+/', ' ', $s));

        // 5. If it's just "Episode 1", "Episode 01", "Ep 1", "S01E01", or empty -> return empty string
        if (empty($s) || preg_match('/^(episode|ep|part)\s*\d+$/i', $s) || preg_match('/^s\d+e\d+$/i', $s)) {
            return '';
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

    public function isSampleOrExtra(string $filename, string $parentFolder): bool
    {
        $lower = strtolower($filename . ' ' . $parentFolder);
        return preg_match('/\b(sample|trailer|trailers|featurette|featurettes|extras|behindthescenes|deletedscenes|short)\b/i', $lower) === 1;
    }
}
