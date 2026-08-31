<?php

namespace App\Services\Organizer;

class SceneNameParserService
{
    public function parse(string $filenameOrPath): array
    {
        $filename = pathinfo($filenameOrPath, PATHINFO_FILENAME);
        $extension = pathinfo($filenameOrPath, PATHINFO_EXTENSION);

        // Normalize separators: dots, underscores to spaces
        $clean = preg_replace('/[._]/', ' ', $filename);

        $type = 'movie';
        $season = null;
        $episode = null;
        $year = null;
        $resolution = null;
        $codec = null;
        $audio = null;
        $source = null;
        $group = null;

        // 1. Detect TV Series (S01E02, 1x02, Season 1 Episode 2)
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
        }

        // 2. Detect Year (1900-2099)
        if (preg_match('/\b(19\d\d|20\d\d)\b/', $clean, $matches)) {
            $year = (int) $matches[1];
        }

        // 3. Detect Resolution
        if (preg_match('/\b(2160p|4k|uhd)\b/i', $clean)) {
            $resolution = '4K UHD';
        } elseif (preg_match('/\b(1080p|1080i|fhd)\b/i', $clean)) {
            $resolution = '1080p FHD';
        } elseif (preg_match('/\b(720p|hd)\b/i', $clean)) {
            $resolution = '720p HD';
        } elseif (preg_match('/\b(480p|sd|576p)\b/i', $clean)) {
            $resolution = '480p SD';
        }

        // 4. Detect Video Codec
        if (preg_match('/\b(x265|h265|hevc)\b/i', $clean)) {
            $codec = 'HEVC / H.265';
        } elseif (preg_match('/\b(x264|h264|avc)\b/i', $clean)) {
            $codec = 'H.264 / AVC';
        } elseif (preg_match('/\b(av1)\b/i', $clean)) {
            $codec = 'AV1';
        } elseif (preg_match('/\b(xvid|divx)\b/i', $clean)) {
            $codec = 'XviD';
        }

        // 5. Detect Audio
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

        // 6. Detect Source
        if (preg_match('/\b(bluray|remux|bdrip|brrip)\b/i', $clean)) {
            $source = 'BluRay';
        } elseif (preg_match('/\b(web-dl|webdl|webrip|web)\b/i', $clean)) {
            $source = 'WEBRip';
        } elseif (preg_match('/\b(hdtv|pdtv|dsr)\b/i', $clean)) {
            $source = 'HDTV';
        } elseif (preg_match('/\b(dvdrip|dvd)\b/i', $clean)) {
            $source = 'DVDRip';
        }

        // 7. Extract Release Group (usually at the very end after a dash)
        if (preg_match('/-([a-zA-Z0-9]+)$/', $filename, $matches)) {
            $group = $matches[1];
        }

        // 8. Clean up Title: strip everything from season/episode or year onward
        $title = $clean;
        $cutoffPatterns = [
            '/[sS]\d{1,2}[eE]\d{1,3}.*$/i',
            '/\b\d{1,2}x\d{1,3}.*$/i',
            '/\b(19\d\d|20\d\d)\b.*$/',
            '/\b(2160p|1080p|720p|480p|4k|bluray|web-dl|webrip|hdtv|dvdrip|x264|x265|hevc|aac)\b.*$/i',
        ];

        foreach ($cutoffPatterns as $pattern) {
            if (preg_match($pattern, $title)) {
                $title = preg_replace($pattern, '', $title);
                break;
            }
        }

        // Clean extra punctuation and trim
        $title = preg_replace('/[\[\]\(\)\{\}\-]/', ' ', $title);
        $title = trim(preg_replace('/\s+/', ' ', $title));
        $title = ucwords(strtolower($title));

        if (empty($title)) {
            $title = $filename;
        }

        return [
            'original_filename' => $filename . '.' . $extension,
            'clean_title' => $title,
            'type' => $type,
            'season' => $season,
            'episode' => $episode,
            'year' => $year,
            'resolution' => $resolution,
            'codec' => $codec,
            'audio' => $audio,
            'source' => $source,
            'group' => $group,
            'extension' => strtolower($extension),
        ];
    }
}
