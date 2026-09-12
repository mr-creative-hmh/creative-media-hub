<?php

namespace App\Services\Subtitles;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Subtitle;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubtitleManagerService
{
    protected OpenSubtitlesService $openSubtitles;

    protected SubSenseService $subSense;

    protected YtsSubsService $ytsSubs;

    protected ?SubDlService $subDl = null;

    public function __construct(
        OpenSubtitlesService $openSubtitles,
        SubSenseService $subSense,
        YtsSubsService $ytsSubs
    ) {
        $this->openSubtitles = $openSubtitles;
        $this->subSense = $subSense;
        $this->ytsSubs = $ytsSubs;
        try {
            $this->subDl = app(SubDlService::class);
        } catch (\Throwable $e) {
            $this->subDl = null;
        }
    }

    /**
     * Scan database for media items missing Arabic or English subtitles.
     */
    public function findMissingSubtitles(): array
    {
        $missing = [];

        // 1. Movies
        $movies = MediaItem::all();
        foreach ($movies as $movie) {
            $hasAr = Subtitle::where('subtitlable_id', $movie->id)
                ->where('subtitlable_type', MediaItem::class)
                ->whereIn('language', ['ar', 'ara'])
                ->exists();

            $hasEn = Subtitle::where('subtitlable_id', $movie->id)
                ->where('subtitlable_type', MediaItem::class)
                ->whereIn('language', ['en', 'eng'])
                ->exists();

            if (! $hasAr || ! $hasEn) {
                $missing[] = [
                    'id' => $movie->id,
                    'type' => 'movie',
                    'title' => $movie->title,
                    'title_ar' => $movie->title_ar ?? null,
                    'year' => $movie->release_year,
                    'file_path' => $movie->file_path,
                    'missing_ar' => ! $hasAr,
                    'missing_en' => ! $hasEn,
                    'imdb_id' => $movie->imdb_id,
                ];
            }
        }

        // 2. Episodes
        $episodes = Episode::with('series')->get();
        foreach ($episodes as $episode) {
            $hasAr = Subtitle::where('subtitlable_id', $episode->id)
                ->where('subtitlable_type', Episode::class)
                ->whereIn('language', ['ar', 'ara'])
                ->exists();

            $hasEn = Subtitle::where('subtitlable_id', $episode->id)
                ->where('subtitlable_type', Episode::class)
                ->whereIn('language', ['en', 'eng'])
                ->exists();

            if (! $hasAr || ! $hasEn) {
                $missing[] = [
                    'id' => $episode->id,
                    'type' => 'episode',
                    'title' => $episode->title,
                    'series_title' => $episode->series?->title,
                    'season_info' => "S{$episode->season_number}E{$episode->episode_number}",
                    'season_number' => $episode->season_number,
                    'episode_number' => $episode->episode_number,
                    'file_path' => $episode->file_path,
                    'missing_ar' => ! $hasAr,
                    'missing_en' => ! $hasEn,
                    'imdb_id' => $episode->series?->imdb_id,
                ];
            }
        }

        return $missing;
    }

    /**
     * Unified multi-engine online search across free and authenticated providers.
     */
    public function searchOnline(
        string $title,
        string|array $lang = 'ar',
        ?string $imdbId = null,
        string $type = 'movie',
        ?int $season = null,
        ?int $episode = null,
        ?int $year = null
    ): array {
        $results = [];

        // 1. SubSense Stremio Aggregator
        if (! empty($imdbId)) {
            $subSenseResults = $this->subSense->searchSubtitles(
                $imdbId,
                $type,
                $season,
                $episode,
                $lang,
                20
            );
            $results = array_merge($results, $subSenseResults);
        }

        // 2. YTS-Subs (Movies)
        if ($type === 'movie' && ! empty($imdbId)) {
            $ytsResults = $this->ytsSubs->searchSubtitles($imdbId, $lang);
            $results = array_merge($results, $ytsResults);
        }

        // 3. OpenSubtitles
        $openSubs = $this->openSubtitles->searchSubtitles([
            'query' => $title,
            'imdb_id' => $imdbId,
            'language' => is_array($lang) ? implode(',', $lang) : $lang,
            'type' => $type,
            'season_number' => $season,
            'episode_number' => $episode,
            'year' => $year,
        ]);
        $results = array_merge($results, $openSubs);

        // 4. SubDL
        if ($this->subDl) {
            $langArray = is_array($lang) ? $lang : explode(',', (string) $lang);
            $subDlResults = $this->subDl->searchSubtitles(
                $title,
                $year ? (int) $year : null,
                array_map('strtoupper', $langArray),
                $type,
                $season,
                $episode
            );
            $results = array_merge($results, $subDlResults);
        }

        return $results;
    }

    /**
     * Download and attach a real subtitle from remote provider URL or automatic multi-source search.
     */
    public function downloadAndAttachRealSubtitle(
        MediaItem|Episode $media,
        string $lang = 'ar',
        ?string $downloadUrl = null,
        ?string $releaseName = null,
        ?string $source = null,
        mixed $fileId = null
    ): ?Subtitle {
        $langCode = strtolower(trim($lang));
        if ($langCode === 'ara') {
            $langCode = 'ar';
        }
        if ($langCode === 'eng') {
            $langCode = 'en';
        }

        $detector = app(EmbeddedSubtitleDetectorService::class);
        $langName = $detector->getLanguageName($langCode);

        $content = null;

        // 1. If OpenSubtitles file ID without a direct URL
        if (empty($downloadUrl) && ! empty($fileId) && ($source === 'opensubtitles' || is_numeric($fileId))) {
            $rawBody = $this->openSubtitles->downloadSubtitle((string) $fileId);
            if ($rawBody) {
                $content = $this->extractSubtitleContent($rawBody);
            }
        }

        // 2. If a direct download URL was provided from a user selection or search result
        if (empty($content) && ! empty($downloadUrl)) {
            $content = $this->fetchFromUrl($downloadUrl);
        }

        // 3. If no direct URL or download failed, perform automatic multi-source online search
        if (empty($content)) {
            $isEpisode = ($media instanceof Episode);
            $mediaType = $isEpisode ? 'episode' : 'movie';

            if ($isEpisode) {
                $media->loadMissing('series');
                $title = $media->series?->title ?: 'Series';
                $imdbId = $media->series?->imdb_id;
                $season = $media->season_number ?: 1;
                $episode = $media->episode_number ?: 1;
                $year = $media->series?->release_year;
            } else {
                $title = $media->title;
                $imdbId = $media->imdb_id;
                $season = null;
                $episode = null;
                $year = $media->release_year;
            }

            if (empty($imdbId) && ! empty($title)) {
                $imdbId = $this->openSubtitles->resolveImdbId($title, $mediaType, $year);
                if ($imdbId) {
                    if ($isEpisode && $media->series) {
                        $media->series->update(['imdb_id' => $imdbId]);
                    } elseif (! $isEpisode) {
                        $media->update(['imdb_id' => $imdbId]);
                    }
                }
            }

            $candidates = [];

            // Primary: SubSense multi-source aggregator (Free, No Key, Movies + TV)
            if (! empty($imdbId)) {
                $candidates = $this->subSense->searchSubtitles($imdbId, $mediaType, $season, $episode, [$langCode], 10);
            }

            // Secondary: YTS-Subs (Movies)
            if (empty($candidates) && $mediaType === 'movie' && ! empty($imdbId)) {
                $candidates = $this->ytsSubs->searchSubtitles($imdbId, [$langCode]);
            }

            // Tertiary: OpenSubtitles REST / v3
            if (empty($candidates)) {
                $candidates = $this->openSubtitles->searchSubtitles([
                    'query' => $title,
                    'imdb_id' => $imdbId,
                    'type' => $mediaType,
                    'season_number' => $season,
                    'episode_number' => $episode,
                    'year' => $year,
                    'language' => $langCode,
                ]);
            }

            // Attempt download from top candidates
            foreach ($candidates as $cand) {
                if (! empty($cand['download_url'])) {
                    $content = $this->fetchFromUrl($cand['download_url']);
                    if (! empty($content)) {
                        if (empty($releaseName)) {
                            $releaseName = $cand['release'] ?? $cand['file_name'] ?? null;
                        }
                        break;
                    }
                } elseif (! empty($cand['subtitle_id']) && str_starts_with((string) $cand['subtitle_id'], 'os_')) {
                    $osFileId = substr((string) $cand['subtitle_id'], 3);
                    $rawBody = $this->openSubtitles->downloadSubtitle($osFileId);
                    if ($rawBody) {
                        $extracted = $this->extractSubtitleContent($rawBody);
                        if (! empty($extracted) && str_contains($extracted, '-->')) {
                            $content = $extracted;
                            break;
                        }
                    }
                }
            }
        }

        // 4. If still no valid subtitle content found, return null (or fallback to English translation if Arabic requested)
        if (empty($content) || ! str_contains($content, '-->')) {
            if ($langCode === 'ar') {
                Log::info("No direct Arabic subtitle found for {$media->title}. Attempting translation from English...");
                try {
                    $translated = app(SubtitleTranslatorService::class)->generateArabicForMedia($media);
                    if ($translated) {
                        return $translated;
                    }
                } catch (\Throwable $e) {
                    Log::warning("Translation fallback failed for {$media->title}: ".$e->getMessage());
                }
            }

            Log::info("No real subtitle content retrieved for {$media->title} [{$langCode}]");

            return null;
        }

        // 5. Determine safe destination path
        $destDir = storage_path('app/subtitles');
        if ($media->file_path) {
            $candidateDir = pathinfo($media->file_path, PATHINFO_DIRNAME);
            try {
                if (! File::isDirectory($candidateDir)) {
                    @File::makeDirectory($candidateDir, 0755, true);
                }
                if (File::isDirectory($candidateDir) && is_writable($candidateDir)) {
                    $destDir = $candidateDir;
                }
            } catch (\Throwable $e) {
                // Keep storage_path fallback
            }
        }

        if (! File::isDirectory($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $baseName = $media->file_path ? pathinfo($media->file_path, PATHINFO_FILENAME) : "media_{$media->id}";
        $srtPath = "{$destDir}/{$baseName}.{$langCode}.srt";

        File::put($srtPath, $content);

        return Subtitle::updateOrCreate([
            'subtitlable_id' => $media->id,
            'subtitlable_type' => get_class($media),
            'language' => $langCode,
            'is_embedded' => false,
        ], [
            'language_name' => $langName,
            'format' => 'srt',
            'file_path' => $srtPath,
            'is_default' => ($langCode === 'ar'),
        ]);
    }

    /**
     * Fetch and extract subtitle content from any supported remote URL.
     */
    protected function fetchFromUrl(string $url): ?string
    {
        try {
            // OpenSubtitles REST API download endpoint
            if (preg_match('#api\.opensubtitles\.com/api/v1/download/(\d+)#i', $url, $m)) {
                $rawBody = $this->openSubtitles->downloadSubtitle($m[1]);
                if ($rawBody) {
                    return $this->extractSubtitleContent($rawBody);
                }
            }

            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ];
            if (str_contains($url, 'yts-subs.com')) {
                $headers['Referer'] = 'https://yts-subs.com/';
            }
            if (str_contains($url, 'subdl.com')) {
                $headers['Referer'] = 'https://subdl.com/';
            }
            if (str_contains($url, 'opensubtitles.org') || str_contains($url, 'opensubtitles.com')) {
                $headers['Referer'] = 'https://www.opensubtitles.org/';
            }

            $response = Http::timeout(15)->withoutVerifying()->withHeaders($headers)->get($url);

            if ($response->successful()) {
                return $this->extractSubtitleContent($response->body());
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to fetch subtitle from {$url}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Extract, decompress, and UTF-8 sanitize subtitle body.
     */
    public function extractSubtitleContent(string $rawBody): string
    {
        // Check for GZIP compression magic number (1f 8b)
        if (str_starts_with($rawBody, "\x1f\x8b")) {
            $decompressed = @gzdecode($rawBody);
            if ($decompressed !== false && ! empty($decompressed)) {
                $rawBody = $decompressed;
            }
        }

        // Check for ZIP file magic number (PK\x03\x04 or PK\x05\x06)
        if (str_starts_with($rawBody, "PK\x03\x04") || str_starts_with($rawBody, "PK\x05\x06")) {
            $tempZip = tempnam(sys_get_temp_dir(), 'sub_zip_');
            File::put($tempZip, $rawBody);
            $zip = new \ZipArchive;
            if ($zip->open($tempZip) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->getNameIndex($i);
                    if (preg_match('/\.(srt|vtt|sub|ass|ssa)$/i', $entry)) {
                        $extracted = $zip->getFromIndex($i);
                        if (! empty($extracted)) {
                            $rawBody = $extracted;
                            break;
                        }
                    }
                }
                $zip->close();
            }
            @unlink($tempZip);
        }

        // Strip UTF-8 BOM if present
        if (str_starts_with($rawBody, "\xEF\xBB\xBF")) {
            $rawBody = substr($rawBody, 3);
        }

        // Detect Windows-1256 (Arabic) or ISO-8859-1 if non-UTF-8
        $detector = app(SubtitleLanguageDetectorService::class);
        $rawBody = $detector->sanitizeToUtf8($rawBody);

        // If SSA / ASS format, seamlessly convert to clean standard SRT
        if (stripos($rawBody, '[Script Info]') !== false || stripos($rawBody, 'Dialogue:') !== false) {
            $converted = $this->convertAssOrSsaToSrt($rawBody);
            if (! empty($converted)) {
                $rawBody = $converted;
            }
        }

        return trim($rawBody);
    }

    /**
     * Preserved for backward-compatible test fixtures.
     */
    public function downloadAndAttachMockSubtitle(MediaItem|Episode $media, string $lang = 'ar'): Subtitle
    {
        $langCode = strtolower($lang);
        $detector = app(EmbeddedSubtitleDetectorService::class);
        $langName = $detector->getLanguageName($langCode);

        $destDir = $media->file_path ? pathinfo($media->file_path, PATHINFO_DIRNAME) : storage_path('app/subtitles');
        $baseName = $media->file_path ? pathinfo($media->file_path, PATHINFO_FILENAME) : "media_{$media->id}";

        if (! File::isDirectory($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $srtPath = "{$destDir}/{$baseName}.{$langCode}.srt";

        $sampleContent = $langCode === 'ar' ?
"1\n00:00:01,000 --> 00:00:04,500\n[موسيقى سينمائية]\n\n2\n00:00:05,000 --> 00:00:09,000\nمرحباً بكم في مكتبة الوسائط الإبداعية.\n" :
"1\n00:00:01,000 --> 00:00:04,500\n[Cinematic Theme]\n\n2\n00:00:05,000 --> 00:00:09,000\nWelcome to Creative Media Hub.\n";

        File::put($srtPath, $sampleContent);

        return Subtitle::updateOrCreate([
            'subtitlable_id' => $media->id,
            'subtitlable_type' => get_class($media),
            'language' => $langCode,
        ], [
            'language_name' => $langName,
            'format' => 'srt',
            'file_path' => $srtPath,
            'is_default' => $langCode === 'en',
            'is_embedded' => false,
        ]);
    }

    /**
     * Convert SubStation Alpha (SSA) and Advanced SubStation Alpha (ASS) subtitles into clean standard SRT.
     */
    public function convertAssOrSsaToSrt(string $raw): string
    {
        $lines = explode("\n", $raw);
        $cues = [];
        $formatFields = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (stripos($trimmed, 'Format:') === 0) {
                $parts = explode(':', $trimmed, 2);
                $fields = array_map('trim', explode(',', $parts[1] ?? ''));
                $formatFields = array_map('strtolower', $fields);

                continue;
            }

            if (stripos($trimmed, 'Dialogue:') === 0) {
                $colonPos = strpos($trimmed, ':');
                $rest = substr($trimmed, $colonPos + 1);

                $fieldCount = $formatFields ? count($formatFields) : 10;
                $parts = explode(',', $rest, $fieldCount);

                if (count($parts) < 3) {
                    continue;
                }

                $startIndex = 1;
                $endIndex = 2;
                $textIndex = count($parts) - 1;

                if ($formatFields) {
                    $sIdx = array_search('start', $formatFields);
                    $eIdx = array_search('end', $formatFields);
                    $tIdx = array_search('text', $formatFields);
                    if ($sIdx !== false) {
                        $startIndex = $sIdx;
                    }
                    if ($eIdx !== false) {
                        $endIndex = $eIdx;
                    }
                    if ($tIdx !== false) {
                        $textIndex = $tIdx;
                    }
                }

                $start = trim($parts[$startIndex] ?? '');
                $end = trim($parts[$endIndex] ?? '');
                $text = trim($parts[$textIndex] ?? '');

                // Drop drawing commands (e.g. {\p1} ... {\p0})
                if (str_contains($text, '{\p1}') || str_contains($text, '{\p2}')) {
                    continue;
                }

                // Remove ASS style/position tags e.g. {\an8}, {\b1}, {\c&H...&}
                $text = preg_replace('/\{[^}]*\}/u', '', $text);
                // Convert \N or \n into real newlines, \h into space
                $text = str_replace(['\\N', '\\n', '\\h'], ["\n", "\n", ' '], $text);
                $text = trim($text);

                if (empty($text)) {
                    continue;
                }

                $srtStart = $this->formatAssTimeToSrt($start);
                $srtEnd = $this->formatAssTimeToSrt($end);

                if ($srtStart && $srtEnd) {
                    $cues[] = [
                        'start' => $srtStart,
                        'end' => $srtEnd,
                        'text' => $text,
                    ];
                }
            }
        }

        if (empty($cues)) {
            return '';
        }

        $srt = '';
        $idx = 1;
        foreach ($cues as $cue) {
            $srt .= "{$idx}\n{$cue['start']} --> {$cue['end']}\n{$cue['text']}\n\n";
            $idx++;
        }

        return trim($srt)."\n";
    }

    /**
     * Format SSA/ASS timestamp (H:MM:SS.CC) into standard SRT timestamp (HH:MM:SS,mmm).
     */
    protected function formatAssTimeToSrt(string $time): ?string
    {
        if (! preg_match('/^(\d+):(\d{2}):(\d{2})[,\.](\d{1,3})$/', trim($time), $m)) {
            return null;
        }

        $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $min = $m[2];
        $s = $m[3];
        $ms = $m[4];
        if (strlen($ms) === 2) {
            $ms = $ms.'0';
        } elseif (strlen($ms) === 1) {
            $ms = $ms.'00';
        } elseif (strlen($ms) > 3) {
            $ms = substr($ms, 0, 3);
        }

        return "{$h}:{$min}:{$s},{$ms}";
    }

    /**
     * Generate an Arabic subtitle from an existing or downloaded English subtitle.
     */
    public function generateArabicSubtitle(MediaItem|Episode $media, ?int $sourceSubtitleId = null): ?Subtitle
    {
        return app(SubtitleTranslatorService::class)->generateArabicForMedia($media, $sourceSubtitleId);
    }
}
