<?php

namespace App\Services\Organizer;

class SceneNameParserService
{
    public function parse(string $filenameOrPath): array
    {
        $normalized = str_replace('\\', '/', $filenameOrPath);
        $parts = array_values(array_filter(explode('/', $normalized)));
        $filename = end($parts);
        $parentFolder = count($parts) > 1 ? $parts[count($parts) - 2] : '';
        $grandparentFolder = count($parts) > 2 ? $parts[count($parts) - 3] : '';

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        $type = 'movie';
        $season = null;
        $episode = null;
        $year = null;
        $resolution = null;
        $codec = null;
        $audio = null;
        $source = null;
        $group = null;
        $isParentSeasonFolder = false;

        $clean = preg_replace('/[._]/', ' ', $baseName);

        // 1. Detect TV Series from filename (S01E02, 1x02, Season 1 Episode 2, EP02, Episode 2)
        if (preg_match('/[sS](\d{1,2})[eE](\d{1,3})/i', $clean, $matches)) {
            $type = 'series';
            $season = (int) $matches[1];
            $episode = (int) $matches[2];
        } elseif (preg_match('/\b(\d{1,2})x(\d{1,3})\b/i', $clean, $matches)) {
            $type = 'series';
            $season = (int) $matches[1];
            $episode = (int) $matches[2];
        } elseif (preg_match('/\bSeason\s*(\d{1,2})\s*Episode\s*(\d{1,3})\b/i', $clean, $matches)) {
            $type = 'series';
            $season = (int) $matches[1];
            $episode = (int) $matches[2];
        } elseif (preg_match('/^(?:ep|episode)?\s*(\d{1,3})\s*[-_ ]/i', $clean, $matches)) {
            $episode = (int) $matches[1];
            $type = 'series';
        }

        // 2. Folder-Aware Series & Season Detection
        if ($parentFolder && preg_match('/^(?:Season|Series|Staffel|Saison)\s*(\d{1,2})$/i', trim($parentFolder), $sMatches)) {
            $type = 'series';
            $isParentSeasonFolder = true;
            if ($season === null) {
                $season = (int) $sMatches[1];
            }
        } elseif ($parentFolder && preg_match('/^[sS](\d{1,2})$/i', trim($parentFolder), $sMatches)) {
            $type = 'series';
            $isParentSeasonFolder = true;
            if ($season === null) {
                $season = (int) $sMatches[1];
            }
        }

        // Check if parent folder contains "Season X" like "The Sopranos Season 1"
        if (!$isParentSeasonFolder && $parentFolder && preg_match('/\b(?:Season|Series|Staffel|Saison)\s*(\d{1,2})\b/i', $parentFolder, $sMatches)) {
            $type = 'series';
            if ($season === null) {
                $season = (int) $sMatches[1];
            }
        }

        // 3. Detect Year (1900-2099)
        if (preg_match('/\b(19\d\d|20\d\d)\b/', $clean, $matches)) {
            $year = (int) $matches[1];
        } elseif ($parentFolder && preg_match('/\b(19\d\d|20\d\d)\b/', $parentFolder, $matches)) {
            $year = (int) $matches[1];
        }

        // 4. Detect Resolution
        if (preg_match('/\b(2160p|4k|uhd)\b/i', $clean . ' ' . $parentFolder)) {
            $resolution = '4K UHD';
        } elseif (preg_match('/\b(1080p|1080i|fhd)\b/i', $clean . ' ' . $parentFolder)) {
            $resolution = '1080p FHD';
        } elseif (preg_match('/\b(720p|hd)\b/i', $clean . ' ' . $parentFolder)) {
            $resolution = '720p HD';
        } elseif (preg_match('/\b(480p|sd|576p)\b/i', $clean . ' ' . $parentFolder)) {
            $resolution = '480p SD';
        }

        // 5. Detect Video Codec
        if (preg_match('/\b(x265|h265|hevc)\b/i', $clean)) {
            $codec = 'HEVC / H.265';
        } elseif (preg_match('/\b(x264|h264|avc)\b/i', $clean)) {
            $codec = 'H.264 / AVC';
        } elseif (preg_match('/\b(av1)\b/i', $clean)) {
            $codec = 'AV1';
        } elseif (preg_match('/\b(xvid|divx)\b/i', $clean)) {
            $codec = 'XviD';
        }

        // 6. Detect Audio
        if (preg_match('/\b(atmos)\b/i', $clean)) {
            $audio = 'Dolby Atmos';
        } elseif (preg_match('/\b(truehd)\b/i', $clean)) {
            $audio = 'Dolby TrueHD';
        } elseif (preg_match('/\b(dts-hd|dts hd|dts-ma)\b/i', $clean)) {
            $audio = 'DTS-HD MA';
        } elseif (preg_match('/\b(dts)\b/i', $clean)) {
            $audio = 'DTS';
        } elseif (preg_match('/\b(dd\+|eac3|ddp)\b/i', $clean)) {
            $audio = 'Dolby Digital Plus';
        } elseif (preg_match('/\b(dd5\.1|ac3|5\.1)\b/i', $clean)) {
            $audio = '5.1 Surround';
        } elseif (preg_match('/\b(aac)\b/i', $clean)) {
            $audio = 'AAC';
        }

        // 7. Detect Source
        if (preg_match('/\b(bluray|remux|bdrip|brrip)\b/i', $clean)) {
            $source = 'BluRay';
        } elseif (preg_match('/\b(web-dl|webdl|webrip|web)\b/i', $clean)) {
            $source = 'WEBRip';
        } elseif (preg_match('/\b(hdtv|pdtv|dsr)\b/i', $clean)) {
            $source = 'HDTV';
        } elseif (preg_match('/\b(dvdrip|dvd)\b/i', $clean)) {
            $source = 'DVDRip';
        }

        // 8. Extract Release Group
        if (preg_match('/-([a-zA-Z0-9]+)$/', $baseName, $matches)) {
            $group = $matches[1];
        }

        // 9. Extract Clean Title
        $titleCandidate = $clean;

        // Cut off scene tokens
        $cutoffPatterns = [
            '/[sS]\d{1,2}[eE]\d{1,3}.*$/i',
            '/\b\d{1,2}x\d{1,3}.*$/i',
            '/\b(?:Season|Series|Staffel|Saison)\s*\d{1,2}\b.*$/i',
            '/\bS\d{1,2}\b.*$/i',
            '/\b(19\d\d|20\d\d)\b.*$/',
            '/\b(2160p|1080p|720p|480p|4k|bluray|remux|web-dl|webdl|webrip|hdtv|dvdrip|x264|x265|hevc|aac)\b.*$/i',
        ];

        foreach ($cutoffPatterns as $pattern) {
            if (preg_match($pattern, $titleCandidate)) {
                $titleCandidate = preg_replace($pattern, '', $titleCandidate);
                break;
            }
        }

        $cleanTitle = $this->cleanTitleString($titleCandidate);

        // Fallbacks for series hierarchy
        if ($type === 'series') {
            if ($isParentSeasonFolder && $grandparentFolder) {
                // e.g. /The Sopranos/Season 1/01.mkv -> series name is grandparent "The Sopranos"
                $cleanTitle = $this->cleanTitleString($grandparentFolder);
            } elseif ($parentFolder && (empty($cleanTitle) || is_numeric($cleanTitle) || preg_match('/^\d{1,3}$/', $cleanTitle))) {
                // e.g. /The Sopranos Season 1/01.mkv -> parent cleaned is "The Sopranos"
                $cleanTitle = $this->cleanTitleString($parentFolder);
            }
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
            'year' => $year,
            'resolution' => $resolution,
            'codec' => $codec,
            'audio' => $audio,
            'source' => $source,
            'group' => $group,
            'extension' => $extension,
        ];
    }

    public function cleanTitleString(string $raw): string
    {
        // 1. Strip bracket tags like [YTS.MX], [1080p], (2024), {rarbg}
        $s = preg_replace('/\[[^\]]*\]/', ' ', $raw);
        $s = preg_replace('/\([^\)]*\)/', ' ', $s);
        $s = preg_replace('/\{[^\}]*\}/', ' ', $s);
        $s = preg_replace('/[\[\]\(\)\{\}]/', ' ', $s);

        // 2. Strip Season tokens: "Season 1", "Staffel 2", "S01", "S1", "Complete"
        $s = preg_replace('/\b(?:Season|Series|Staffel|Saison)\s*\d{1,2}\b/i', ' ', $s);
        $s = preg_replace('/\bS\d{1,2}\b/i', ' ', $s);
        $s = preg_replace('/\b(?:Complete|Anthology|Boxset|Collection)\b/i', ' ', $s);

        // 3. Strip common quality/scene tags
        $s = preg_replace('/\b(?:2160p|1080p|720p|480p|4k|bluray|remux|web-dl|webdl|webrip|hdtv|dvdrip|x264|x265|hevc|aac|dts|ac3|atmos)\b/i', ' ', $s);

        // 4. Strip year if at end
        $s = preg_replace('/\b(19\d\d|20\d\d)\b/', ' ', $s);

        // 5. Clean punctuation and spaces
        $s = preg_replace('/[._\-]/', ' ', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));

        return ucwords(strtolower($s));
    }
}
