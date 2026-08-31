<?php

namespace App\Services\Scanner;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\Genre;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Models\Subtitle;
use App\Services\Metadata\MetadataAggregator;
use App\Services\Metadata\WebArtworkSearchService;
use App\Services\Organizer\FilesystemScannerService;
use App\Services\Organizer\SceneNameParserService;
use App\Services\Subtitles\EmbeddedSubtitleDetectorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class VirtualLibraryScannerService
{
    protected FilesystemScannerService $fsScanner;
    protected SceneNameParserService $nameParser;
    protected MetadataAggregator $metadata;
    protected WebArtworkSearchService $webArtwork;
    protected EmbeddedSubtitleDetectorService $embeddedSubDetector;

    public function __construct(
        FilesystemScannerService $fsScanner,
        SceneNameParserService $nameParser,
        MetadataAggregator $metadata,
        WebArtworkSearchService $webArtwork,
        EmbeddedSubtitleDetectorService $embeddedSubDetector
    ) {
        $this->fsScanner = $fsScanner;
        $this->nameParser = $nameParser;
        $this->metadata = $metadata;
        $this->webArtwork = $webArtwork;
        $this->embeddedSubDetector = $embeddedSubDetector;
    }

    public function getScanStatus(): array
    {
        return Cache::get('virtual_scanner_job_status', [
            'status' => 'idle', // idle, scanning, paused, completed, cancelled
            'progress_percent' => 0,
            'total_files' => 0,
            'processed_files' => 0,
            'current_file' => null,
            'scanned_items' => [],
            'logs' => [],
            'started_at' => null,
            'updated_at' => null,
        ]);
    }

    public function initScan(array $directories): array
    {
        $allFiles = [];
        foreach ($directories as $dir) {
            $path = $dir['path'] ?? '';
            $typeHint = $dir['type'] ?? 'mixed';
            if (!empty($path) && (is_dir($path) || is_dir(str_replace('\\', '/', $path)))) {
                $scanned = $this->fsScanner->scanDirectory($path, true);
                foreach ($scanned as $f) {
                    $f['type_hint'] = $typeHint;
                    $allFiles[] = $f;
                }
            }
        }

        $totalFiles = count($allFiles);

        $jobData = [
            'status' => $totalFiles > 0 ? 'scanning' : 'completed',
            'progress_percent' => 0,
            'total_files' => $totalFiles,
            'processed_files' => 0,
            'current_file' => null,
            'queue' => $allFiles,
            'scanned_items' => [],
            'logs' => [
                [
                    'time' => now()->format('H:i:s'),
                    'level' => 'info',
                    'message' => "Scanner initialized. Found {$totalFiles} video files to index.",
                ]
            ],
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        Cache::put('virtual_scanner_job_status', $jobData, now()->addHours(6));

        return [
            'total_files' => $totalFiles,
            'status' => $jobData['status'],
        ];
    }

    public function processBatch(int $batchSize = 4): array
    {
        return $this->processNextBatch($batchSize);
    }

    public function processNextBatch(int $batchSize = 4): array
    {
        $jobData = $this->getScanStatus();

        if (empty($jobData['queue']) || $jobData['status'] !== 'scanning') {
            if ($jobData['status'] === 'scanning') {
                $jobData['status'] = 'completed';
                $jobData['progress_percent'] = 100;
                $jobData['logs'][] = [
                    'time' => now()->format('H:i:s'),
                    'level' => 'success',
                    'message' => 'Library scan completed successfully!',
                ];
                Cache::put('virtual_scanner_job_status', $jobData, now()->addHours(6));
            }
            return ['status' => $jobData['status'], 'progress_percent' => $jobData['progress_percent'], 'processed' => 0];
        }

        $queue = $jobData['queue'];
        $scannedItems = $jobData['scanned_items'] ?? [];
        $logs = $jobData['logs'] ?? [];

        $batch = array_splice($queue, 0, $batchSize);

        foreach ($batch as $file) {
            try {
                $jobData['current_file'] = $file['filename'];
                $indexed = $this->processFileItem($file);
                $jobData['processed_files']++;

                $mediaType = $file['parsed']['type'] ?? 'movie';
                $title = $indexed->title ?? ($indexed->name ?? $file['filename']);

                $scannedItems[] = [
                    'id' => $indexed->id,
                    'title' => $title,
                    'type' => $mediaType,
                    'file' => $file['filename'],
                    'resolution' => $file['parsed']['resolution'] ?? '1080p',
                    'subtitles_count' => count($file['subtitles'] ?? []),
                ];

                $subCount = count($file['subtitles'] ?? []);
                $subText = $subCount > 0 ? " (+{$subCount} subtitles)" : "";

                $logs[] = [
                    'time' => now()->format('H:i:s'),
                    'level' => 'success',
                    'message' => "Indexed: [{$mediaType}] {$title} {$subText}",
                ];
            } catch (\Throwable $e) {
                $jobData['processed_files']++;
                $logs[] = [
                    'time' => now()->format('H:i:s'),
                    'level' => 'error',
                    'message' => "Failed to index {$file['filename']}: {$e->getMessage()}",
                ];
            }
        }

        $jobData['queue'] = $queue;
        $jobData['scanned_items'] = array_slice($scannedItems, -30);
        $jobData['logs'] = array_slice($logs, -80);
        $jobData['updated_at'] = now()->toDateTimeString();

        $total = max(1, $jobData['total_files']);
        $jobData['progress_percent'] = min(100, (int) round(($jobData['processed_files'] / $total) * 100));

        $hasMore = count($queue) > 0;
        if (!$hasMore) {
            $jobData['status'] = 'completed';
            $jobData['progress_percent'] = 100;
            $jobData['logs'][] = [
                'time' => now()->format('H:i:s'),
                'level' => 'success',
                'message' => 'All files processed and indexed successfully!',
            ];
        }

        Cache::put('virtual_scanner_job_status', $jobData, now()->addHours(6));

        return [
            'status' => $jobData['status'],
            'has_more' => $hasMore,
            'progress_percent' => $jobData['progress_percent'],
            'processed_files' => $jobData['processed_files'],
            'total_files' => $jobData['total_files'],
            'current_file' => $jobData['current_file'],
            'latest_scanned' => array_slice($scannedItems, -5),
            'latest_logs' => array_slice($logs, -10),
        ];
    }

    public function pauseScan(): void
    {
        $job = $this->getScanStatus();
        $job['status'] = 'paused';
        $job['logs'][] = [
            'time' => now()->format('H:i:s'),
            'level' => 'warning',
            'message' => 'Scan paused by user.',
        ];
        Cache::put('virtual_scanner_job_status', $job, now()->addHours(6));
    }

    public function resumeScan(): void
    {
        $job = $this->getScanStatus();
        $job['status'] = 'scanning';
        $job['logs'][] = [
            'time' => now()->format('H:i:s'),
            'level' => 'info',
            'message' => 'Scan resumed.',
        ];
        Cache::put('virtual_scanner_job_status', $job, now()->addHours(6));
    }

    public function cancelScan(): void
    {
        $job = $this->getScanStatus();
        $job['status'] = 'cancelled';
        $job['queue'] = [];
        $job['logs'][] = [
            'time' => now()->format('H:i:s'),
            'level' => 'error',
            'message' => 'Scan cancelled by user.',
        ];
        Cache::put('virtual_scanner_job_status', $job, now()->addHours(6));
    }

    public function processFileItem(array $file): mixed
    {
        $parsed = $file['parsed'] ?? $this->nameParser->parse($file['path']);
        $typeHint = $file['type_hint'] ?? 'mixed';

        $isSeries = ($parsed['type'] === 'series') || ($typeHint === 'series');

        if ($isSeries) {
            return $this->indexSeriesEpisode($file, $parsed);
        }

        return $this->indexMovie($file, $parsed);
    }

    protected function indexMovie(array $file, array $parsed): MediaItem
    {
        $cleanTitle = $parsed['clean_title'] ?? ($parsed['title'] ?? pathinfo($file['filename'], PATHINFO_FILENAME));
        $year = $parsed['year'] ?? null;

        $existing = MediaItem::where('file_path', $file['path'])->first();
        if ($existing) {
            $this->attachAllSubtitles($existing, $file);
            return $existing;
        }

        $meta = $this->metadata->aggregateMovieMetadata($cleanTitle, $year);
        $posterUrl = $meta['poster_path'] ?? ($file['local_poster'] ?? null);
        if (empty($posterUrl)) {
            $posterUrl = $this->webArtwork->searchAndDownloadArtwork($cleanTitle, $year, 'movie');
        }

        $backdropUrl = $meta['backdrop_path'] ?? ($file['local_backdrop'] ?? null);

        $movie = MediaItem::create([
            'title' => $meta['title'] ?? $cleanTitle,
            'original_title' => $meta['original_title'] ?? $cleanTitle,
            'title_ar' => $meta['title_ar'] ?? null,
            'release_year' => $meta['year'] ?? $year,
            'overview' => $meta['overview'] ?? "Enjoy watching {$cleanTitle}.",
            'overview_ar' => $meta['overview_ar'] ?? null,
            'rating' => $meta['rating'] ?? 7.5,
            'duration_minutes' => $meta['duration_minutes'] ?? 115,
            'file_path' => $file['path'],
            'file_size_bytes' => $file['size_bytes'] ?? 0,
            'resolution' => $parsed['resolution'] ?? '1080p',
            'video_codec' => $parsed['codec'] ?? 'x264',
            'audio_codec' => $parsed['audio'] ?? 'AAC',
            'source' => $parsed['source'] ?? 'WEB-DL',
            'poster_path' => $posterUrl,
            'backdrop_path' => $backdropUrl,
            'is_favorite' => false,
        ]);

        if (!empty($meta['genres'])) {
            $genreIds = [];
            foreach ($meta['genres'] as $gName) {
                $genre = Genre::firstOrCreate(
                    ['slug' => Str::slug($gName)],
                    ['name_en' => $gName, 'name_ar' => $gName]
                );
                $genreIds[] = $genre->id;
            }
            $movie->genres()->sync($genreIds);
        }

        $this->attachAllSubtitles($movie, $file);

        return $movie;
    }

    protected function indexSeriesEpisode(array $file, array $parsed): Episode
    {
        $rawShowTitle = $parsed['series_title'] ?? ($parsed['clean_title'] ?? 'Unknown Series');
        $showTitle = $this->nameParser->cleanTitleString($rawShowTitle);
        $seasonNum = (int) ($parsed['season'] ?? 1);
        $epNum = (int) ($parsed['episode'] ?? 1);
        $epTitle = $parsed['episode_title'] ?? "Episode {$epNum}";

        $series = Series::where('title', 'like', $showTitle)
            ->orWhere('original_title', 'like', $showTitle)
            ->first();

        if (!$series) {
            $series = Series::create([
                'title' => $showTitle,
                'original_title' => $showTitle,
                'release_year' => $parsed['year'] ?? null,
                'overview' => "Experience the complete series of {$showTitle}.",
                'rating' => 8.0,
                'status' => 'Continuing',
            ]);
        }

        if (!$series->poster_path || !$series->overview || $series->overview === "Experience the complete series of {$showTitle}.") {
            try {
                $meta = $this->metadata->aggregateSeriesMetadata($showTitle, $parsed['year'] ?? null);
                $posterUrl = $meta['poster_path'] ?? ($file['local_poster'] ?? null);
                if (empty($posterUrl)) {
                    $posterUrl = $this->webArtwork->searchAndDownloadArtwork($showTitle, $parsed['year'] ?? null, 'series');
                }

                $backdropUrl = $meta['backdrop_path'] ?? ($file['local_backdrop'] ?? null);

                $series->update([
                    'title_ar' => $meta['title_ar'] ?? $series->title_ar,
                    'overview' => $meta['overview'] ?? $series->overview,
                    'overview_ar' => $meta['overview_ar'] ?? $series->overview_ar,
                    'poster_path' => $posterUrl ?? $series->poster_path,
                    'backdrop_path' => $backdropUrl ?? $series->backdrop_path,
                    'rating' => $meta['rating'] ?? $series->rating,
                    'release_year' => $meta['year'] ?? $series->release_year,
                ]);

                if (!empty($meta['genres'])) {
                    $genreIds = [];
                    foreach ($meta['genres'] as $gName) {
                        $genre = Genre::firstOrCreate(
                            ['slug' => Str::slug($gName)],
                            ['name_en' => $gName, 'name_ar' => $gName]
                        );
                        $genreIds[] = $genre->id;
                    }
                    $series->genres()->sync($genreIds);
                }
            } catch (\Throwable $e) {}
        }

        $season = Season::firstOrCreate(
            ['series_id' => $series->id, 'season_number' => $seasonNum],
            ['title' => "Season {$seasonNum}"]
        );

        $episode = Episode::updateOrCreate(
            [
                'series_id' => $series->id,
                'season_id' => $season->id,
                'episode_number' => $epNum,
            ],
            [
                'title' => $epTitle,
                'overview' => "Episode {$epNum} of Season {$seasonNum}.",
                'file_path' => $file['path'],
                'file_size_bytes' => $file['size_bytes'] ?? 0,
                'runtime_minutes' => 45,
                'resolution' => $parsed['resolution'] ?? '1080p',
                'video_codec' => $parsed['codec'] ?? 'x264',
                'audio_codec' => $parsed['audio'] ?? 'AAC',
            ]
        );

        $this->attachAllSubtitles($episode, $file);

        return $episode;
    }

    /**
     * Attach both external subtitle files and embedded container subtitle streams.
     */
    protected function attachAllSubtitles(mixed $model, array $file): void
    {
        $filePath = $file['path'] ?? '';
        $modelClass = get_class($model);

        // 1. External Subtitle Files (SRT, ASS, VTT, etc.)
        if (!empty($file['subtitles'])) {
            foreach ($file['subtitles'] as $sub) {
                $lang = $sub['language'] ?? 'und';
                $langName = match ($lang) {
                    'ar' => 'Arabic',
                    'en' => 'English',
                    'fr' => 'French',
                    'es' => 'Spanish',
                    'de' => 'German',
                    'it' => 'Italian',
                    'ja' => 'Japanese',
                    'ko' => 'Korean',
                    'zh' => 'Chinese',
                    'ru' => 'Russian',
                    'pt' => 'Portuguese',
                    'tr' => 'Turkish',
                    default => 'External Subtitle',
                };

                Subtitle::updateOrCreate(
                    [
                        'subtitlable_id' => $model->id,
                        'subtitlable_type' => $modelClass,
                        'file_path' => $sub['path'],
                    ],
                    [
                        'language' => $lang,
                        'language_name' => $langName,
                        'format' => $sub['extension'] ?? 'srt',
                        'is_embedded' => false,
                        'is_default' => $lang === 'ar' || $lang === 'en',
                    ]
                );
            }
        }

        // 2. Embedded Subtitle Streams inside the container (MKV / MP4)
        if ($filePath && file_exists($filePath)) {
            $embeddedTracks = $this->embeddedSubDetector->detectEmbeddedSubtitles($filePath);
            foreach ($embeddedTracks as $track) {
                $virtualPath = "embedded:{$track['stream_index']}:{$filePath}";

                Subtitle::updateOrCreate(
                    [
                        'subtitlable_id' => $model->id,
                        'subtitlable_type' => $modelClass,
                        'file_path' => $virtualPath,
                    ],
                    [
                        'language' => $track['language'],
                        'language_name' => $track['language_name'],
                        'format' => $track['codec'] ?? 'srt',
                        'is_embedded' => true,
                        'is_default' => false,
                    ]
                );
            }
        }
    }

    public function processSingleFile(string $filePath, string $typeHint = 'movie'): ?array
    {
        return $this->processFileItem([
            'path' => $filePath,
            'filename' => basename($filePath),
            'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
            'size_bytes' => file_exists($filePath) ? filesize($filePath) : 1500000000,
            'type_hint' => $typeHint === 'series' ? 'series' : 'movies',
        ]);
    }
}
