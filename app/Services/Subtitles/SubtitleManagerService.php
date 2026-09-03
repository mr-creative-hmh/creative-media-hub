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

    protected SubDlService $subDl;

    public function __construct(OpenSubtitlesService $openSubtitles, SubDlService $subDl)
    {
        $this->openSubtitles = $openSubtitles;
        $this->subDl = $subDl;
    }

    public function findMissingSubtitles(): array
    {
        $missing = [];

        // Check Movies
        $movies = MediaItem::with('subtitles')->get();
        foreach ($movies as $movie) {
            $hasAr = $movie->subtitles->where('language', 'ar')->isNotEmpty();
            $hasEn = $movie->subtitles->where('language', 'en')->isNotEmpty();

            if (! $hasAr || ! $hasEn) {
                $missing[] = [
                    'id' => $movie->id,
                    'type' => 'movie',
                    'title' => $movie->title,
                    'title_ar' => $movie->title_ar,
                    'release_year' => $movie->release_year,
                    'file_path' => $movie->file_path,
                    'missing_ar' => ! $hasAr,
                    'missing_en' => ! $hasEn,
                ];
            }
        }

        // Check Series Episodes
        $episodes = Episode::with(['series', 'subtitles'])->get();
        foreach ($episodes as $ep) {
            $hasAr = $ep->subtitles->where('language', 'ar')->isNotEmpty();
            $hasEn = $ep->subtitles->where('language', 'en')->isNotEmpty();

            if (! $hasAr || ! $hasEn) {
                $missing[] = [
                    'id' => $ep->id,
                    'type' => 'episode',
                    'series_title' => $ep->series->title ?? 'Series',
                    'season_number' => $ep->season_id,
                    'episode_number' => $ep->episode_number,
                    'title' => $ep->title,
                    'file_path' => $ep->file_path,
                    'missing_ar' => ! $hasAr,
                    'missing_en' => ! $hasEn,
                ];
            }
        }

        return $missing;
    }

    /**
     * Download and attach a real subtitle from remote provider URL or automatic search.
     */
    public function downloadAndAttachRealSubtitle(
        MediaItem|Episode $media,
        string $lang = 'ar',
        ?string $downloadUrl = null,
        ?string $releaseName = null
    ): ?Subtitle {
        $langCode = strtolower(trim($lang));
        // Normalize 3-letter codes to 2-letter if needed
        if ($langCode === 'ara') {
            $langCode = 'ar';
        }
        if ($langCode === 'eng') {
            $langCode = 'en';
        }

        $detector = app(EmbeddedSubtitleDetectorService::class);
        $langName = $detector->getLanguageName($langCode);

        $content = null;

        // 1. If a direct download URL was provided from a real search result
        if (! empty($downloadUrl)) {
            try {
                $response = Http::timeout(15)
                    ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                    ->get($downloadUrl);

                if ($response->successful()) {
                    $rawBody = $response->body();
                    $content = $this->extractSubtitleContent($rawBody);
                }
            } catch (\Exception $e) {
                Log::warning("Failed to download subtitle from {$downloadUrl}: ".$e->getMessage());
            }
        }

        // 2. If no direct URL or download failed, perform automatic online search
        if (empty($content)) {
            $searchParams = [
                'language' => $langCode,
            ];

            if ($media instanceof Episode) {
                $media->loadMissing('series');
                $searchParams['query'] = $media->series?->title ?: 'Series';
                $searchParams['imdb_id'] = $media->series?->imdb_id;
                $searchParams['type'] = 'episode';
                $searchParams['season_number'] = $media->season_number ?: 1;
                $searchParams['episode_number'] = $media->episode_number ?: 1;
            } else {
                $searchParams['query'] = $media->title;
                $searchParams['imdb_id'] = $media->imdb_id;
                $searchParams['type'] = 'movie';
                $searchParams['year'] = $media->release_year;
            }

            $results = $this->openSubtitles->searchSubtitles($searchParams);
            if (! empty($results)) {
                $topResult = $results[0];
                if (! empty($topResult['download_url'])) {
                    try {
                        $res = Http::timeout(15)
                            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                            ->get($topResult['download_url']);

                        if ($res->successful()) {
                            $content = $this->extractSubtitleContent($res->body());
                            if (empty($releaseName)) {
                                $releaseName = $topResult['release'] ?? $topResult['file_name'] ?? null;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Auto-download subtitle fallback failed: '.$e->getMessage());
                    }
                }
            }
        }

        // 3. If still no valid subtitle content found, return null (never create fake dummy files!)
        if (empty($content) || ! str_contains($content, '-->')) {
            Log::info("No real subtitle content retrieved for {$media->title} [{$langCode}]");

            return null;
        }

        // 4. Determine safe destination path
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
        ], [
            'language_name' => $langName,
            'format' => 'srt',
            'file_path' => $srtPath,
            'is_default' => $langCode === 'en',
            'is_embedded' => false,
        ]);
    }

    /**
     * Extract, decompress, and UTF-8 sanitize subtitle body.
     */
    protected function extractSubtitleContent(string $rawBody): string
    {
        // Check for GZIP compression magic number (1f 8b)
        if (str_starts_with($rawBody, "\x1f\x8b")) {
            $decompressed = @gzdecode($rawBody);
            if ($decompressed !== false && ! empty($decompressed)) {
                $rawBody = $decompressed;
            }
        }

        // Check for ZIP file magic number (PK\x03\x04)
        if (str_starts_with($rawBody, "PK\x03\x04")) {
            $tempZip = tempnam(sys_get_temp_dir(), 'sub_zip_');
            File::put($tempZip, $rawBody);
            $zip = new \ZipArchive;
            if ($zip->open($tempZip) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->getNameIndex($i);
                    if (preg_match('/\.(srt|vtt|sub|ass)$/i', $entry)) {
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
        if (! mb_check_encoding($rawBody, 'UTF-8')) {
            $converted = @mb_convert_encoding($rawBody, 'UTF-8', 'Windows-1256');
            if ($converted && str_contains($converted, '-->')) {
                $rawBody = $converted;
            } else {
                $rawBody = @mb_convert_encoding($rawBody, 'UTF-8', 'ISO-8859-1');
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
}
