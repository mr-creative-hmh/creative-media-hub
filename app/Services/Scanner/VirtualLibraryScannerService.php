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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class VirtualLibraryScannerService
{
    protected FilesystemScannerService $fsScanner;
    protected SceneNameParserService $nameParser;
    protected MetadataAggregator $metadata;
    protected WebArtworkSearchService $webArtwork;

    public function __construct(
        FilesystemScannerService $fsScanner,
        SceneNameParserService $nameParser,
        MetadataAggregator $metadata,
        WebArtworkSearchService $webArtwork
    ) {
        $this->fsScanner = $fsScanner;
        $this->nameParser = $nameParser;
        $this->metadata = $metadata;
        $this->webArtwork = $webArtwork;
    }

    public function initScan(array $directories): array
    {
        $discoveredFiles = [];

        foreach ($directories as $dir) {
            $path = $dir['path'] ?? '';
            $type = $dir['type'] ?? 'mixed';
            $normPath = $this->fsScanner->normalizePath($path);

            if (!empty($path) && (is_dir($path) || is_dir($normPath))) {
                $targetPath = is_dir($path) ? $path : $normPath;
                $files = $this->fsScanner->scanDirectory($targetPath);
                foreach ($files as &$f) {
                    $f['suggested_type'] = $type;
                }
                unset($f);
                $discoveredFiles = array_merge($discoveredFiles, $files);
            }
        }

        $totalFiles = count($discoveredFiles);

        $initialLogs = [
            [
                'time' => now()->format('H:i:s'),
                'level' => 'info',
                'message' => "Scanner initialized across " . count($directories) . " monitored directories.",
            ],
            [
                'time' => now()->format('H:i:s'),
                'level' => $totalFiles > 0 ? 'success' : 'warning',
                'message' => "Discovered {$totalFiles} video media stream(s) ready for indexing.",
            ],
        ];

        $jobData = [
            'status' => $totalFiles > 0 ? 'running' : 'completed',
            'total_files' => $totalFiles,
            'processed_files' => 0,
            'progress_percent' => $totalFiles > 0 ? 0 : 100,
            'current_file' => '',
            'queue' => $discoveredFiles,
            'scanned_items' => [],
            'logs' => $initialLogs,
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        Cache::put('virtual_scan_job', $jobData, 86400);

        return [
            'success' => true,
            'total_files' => $totalFiles,
            'status' => $jobData['status'],
        ];
    }

    public function initScanForFolder(string $folderPath, string $type = 'mixed'): array
    {
        $discoveredFiles = [];
        $normPath = $this->fsScanner->normalizePath($folderPath);
        $target = is_dir($folderPath) ? $folderPath : (is_dir($normPath) ? $normPath : '');

        if (!empty($target)) {
            $files = $this->fsScanner->scanDirectory($target);
            foreach ($files as &$f) {
                $f['suggested_type'] = $type;
            }
            unset($f);
            $discoveredFiles = $files;
        }

        $totalFiles = count($discoveredFiles);

        $jobData = [
            'status' => $totalFiles > 0 ? 'running' : 'completed',
            'total_files' => $totalFiles,
            'processed_files' => 0,
            'progress_percent' => $totalFiles > 0 ? 0 : 100,
            'current_file' => '',
            'queue' => $discoveredFiles,
            'scanned_items' => [],
            'logs' => [
                [
                    'time' => now()->format('H:i:s'),
                    'level' => 'info',
                    'message' => "Targeted folder scan initiated: {$folderPath}",
                ],
                [
                    'time' => now()->format('H:i:s'),
                    'level' => $totalFiles > 0 ? 'success' : 'warning',
                    'message' => "Discovered {$totalFiles} media item(s) in folder.",
                ],
            ],
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        Cache::put('virtual_scan_job', $jobData, 86400);

        return [
            'success' => true,
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
        $jobData = Cache::get('virtual_scan_job');

        if (!$jobData || $jobData['status'] !== 'running') {
            return [
                'has_more' => false,
                'status' => $jobData ?? $this->getScanStatus(),
            ];
        }

        $queue = $jobData['queue'] ?? [];
        $batch = array_splice($queue, 0, $batchSize);

        $scannedItems = $jobData['scanned_items'] ?? [];
        $logs = $jobData['logs'] ?? [];

        foreach ($batch as $file) {
            try {
                $parsed = $this->nameParser->parse($file['path']);

                if (isset($file['suggested_type']) && $file['suggested_type'] === 'movies') {
                    $parsed['type'] = 'movie';
                } elseif (isset($file['suggested_type']) && $file['suggested_type'] === 'series') {
                    $parsed['type'] = 'series';
                }

                if ($parsed['type'] === 'series') {
                    $item = $this->indexSeriesEpisode($file, $parsed);
                    $mediaType = 'series';
                    $title = $parsed['series_title'] ?? $parsed['title'];
                } else {
                    $item = $this->indexMovie($file, $parsed);
                    $mediaType = 'movie';
                    $title = $item->title;
                }

                $jobData['processed_files']++;
                $jobData['current_file'] = $file['filename'];

                $scannedItems[] = [
                    'title' => $title,
                    'type' => $mediaType,
                    'file_path' => $file['path'],
                    'resolution' => $parsed['resolution'] ?? '1080p',
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
        }

        Cache::put('virtual_scan_job', $jobData, 86400);

        return [
            'has_more' => $hasMore,
            'status' => $jobData,
        ];
    }

    public function pauseScan(): void
    {
        $jobData = Cache::get('virtual_scan_job', [
            'status' => 'running',
            'total_files' => 0,
            'processed_files' => 0,
            'progress_percent' => 0,
            'current_file' => '',
            'queue' => [],
            'scanned_items' => [],
            'logs' => [],
        ]);
        $jobData['status'] = 'paused';
        $jobData['logs'][] = [
            'time' => now()->format('H:i:s'),
            'level' => 'warning',
            'message' => "Scan job paused by user.",
        ];
        Cache::put('virtual_scan_job', $jobData, 86400);
    }

    public function resumeScan(): void
    {
        $jobData = Cache::get('virtual_scan_job', [
            'status' => 'paused',
            'total_files' => 0,
            'processed_files' => 0,
            'progress_percent' => 0,
            'current_file' => '',
            'queue' => [],
            'scanned_items' => [],
            'logs' => [],
        ]);
        $jobData['status'] = 'running';
        $jobData['logs'][] = [
            'time' => now()->format('H:i:s'),
            'level' => 'info',
            'message' => "Scan job resumed.",
        ];
        Cache::put('virtual_scan_job', $jobData, 86400);
    }

    public function cancelScan(): void
    {
        $jobData = Cache::get('virtual_scan_job', [
            'status' => 'running',
            'total_files' => 0,
            'processed_files' => 0,
            'progress_percent' => 0,
            'current_file' => '',
            'queue' => [],
            'scanned_items' => [],
            'logs' => [],
        ]);
        $jobData['status'] = 'cancelled';
        $jobData['queue'] = [];
        $jobData['logs'][] = [
            'time' => now()->format('H:i:s'),
            'level' => 'error',
            'message' => "Scan job cancelled.",
        ];
        Cache::put('virtual_scan_job', $jobData, 86400);
    }

    public function getScanStatus(): array
    {
        return Cache::get('virtual_scan_job', [
            'status' => 'idle',
            'total_files' => 0,
            'processed_files' => 0,
            'progress_percent' => 0,
            'current_file' => '',
            'queue' => [],
            'scanned_items' => [],
            'logs' => [],
        ]);
    }

    public function enrichMissingMetadata(int $limit = 30): array
    {
        $items = MediaItem::whereNull('poster_path')
            ->orWhere('poster_path', '')
            ->orWhereNull('overview_ar')
            ->limit($limit)
            ->get();

        $updated = 0;
        foreach ($items as $item) {
            try {
                $meta = $this->metadata->aggregateMovieMetadata($item->title, $item->release_year);
                $poster = $meta['poster_path'] ?? null;
                if (!$poster) {
                    $poster = $this->webArtwork->searchAndDownloadArtwork($item->title, $item->release_year, 'movie');
                }

                if ($poster || !empty($meta['overview_ar']) || !empty($meta['title_ar'])) {
                    $item->update([
                        'poster_path' => $poster ?: $item->poster_path,
                        'overview_ar' => $meta['overview_ar'] ?? $item->overview_ar,
                        'title_ar' => $meta['title_ar'] ?? $item->title_ar,
                        'rating' => $meta['rating'] ?? $item->rating,
                    ]);
                    $updated++;
                }
            } catch (\Throwable $e) {}
        }

        return ['updated' => $updated, 'total' => $items->count()];
    }

    protected function indexMovie(array $file, array $parsed): MediaItem
    {
        $cleanTitle = $parsed['clean_title'] ?? ($parsed['title'] ?? pathinfo($file['filename'], PATHINFO_FILENAME));
        $year = $parsed['year'] ?? null;

        $meta = [];
        $posterUrl = $file['local_poster'] ?? null;
        $backdropUrl = $file['local_backdrop'] ?? null;

        try {
            $meta = $this->metadata->aggregateMovieMetadata($cleanTitle, $year);
            $posterUrl = $meta['poster_path'] ?? $posterUrl;
            if (empty($posterUrl)) {
                $posterUrl = $this->webArtwork->searchAndDownloadArtwork($cleanTitle, $year, 'movie');
            }
            $backdropUrl = $meta['backdrop_path'] ?? $backdropUrl;
        } catch (\Throwable $e) {}

        $movie = MediaItem::updateOrCreate(
            ['file_path' => $file['path']],
            [
                'title' => $meta['title'] ?? $cleanTitle,
                'original_title' => $meta['original_title'] ?? $cleanTitle,
                'title_ar' => $meta['title_ar'] ?? null,
                'release_year' => $meta['year'] ?? $year,
                'tmdb_id' => $meta['tmdb_id'] ?? null,
                'imdb_id' => $meta['imdb_id'] ?? null,
                'overview' => $meta['overview'] ?? "Enjoy watching {$cleanTitle}.",
                'overview_ar' => $meta['overview_ar'] ?? null,
                'poster_path' => $posterUrl,
                'backdrop_path' => $backdropUrl,
                'trailer_url' => $meta['trailer_url'] ?? null,
                'rating' => $meta['rating'] ?? 7.5,
                'runtime_minutes' => $meta['runtime_minutes'] ?? 115,
                'resolution' => $parsed['resolution'] ?? '1080p',
                'video_codec' => $parsed['codec'] ?? 'HEVC',
                'audio_codec' => $parsed['audio'] ?? 'AAC 5.1',
                'file_size_bytes' => $file['size_bytes'] ?? 0,
                'folder_path' => dirname($file['path']),
            ]
        );

        $genres = !empty($meta['genres']) ? $meta['genres'] : ['Action', 'Drama'];
        $genreIds = [];
        foreach ($genres as $gName) {
            try {
                $genre = Genre::firstOrCreate(
                    ['slug' => Str::slug($gName)],
                    ['name_en' => $gName, 'name_ar' => $gName]
                );
                $genreIds[] = $genre->id;
            } catch (\Throwable $e) {}
        }
        $movie->genres()->sync($genreIds);

        if (!empty($file['subtitles'])) {
            foreach ($file['subtitles'] as $sub) {
                $lang = $sub['language'] ?? 'und';
                $langName = match ($lang) {
                    'ar' => 'Arabic',
                    'en' => 'English',
                    'fr' => 'French',
                    'es' => 'Spanish',
                    'de' => 'German',
                    default => 'Original Subtitle',
                };

                Subtitle::updateOrCreate(
                    [
                        'subtitlable_id' => $movie->id,
                        'subtitlable_type' => MediaItem::class,
                        'file_path' => $sub['path'],
                    ],
                    [
                        'language' => $lang,
                        'language_name' => $langName,
                        'format' => $sub['extension'] ?? 'srt',
                    ]
                );
            }
        }

        return $movie;
    }

    protected function indexSeriesEpisode(array $file, array $parsed): Episode
    {
        $rawShowTitle = $parsed['series_title'] ?? ($parsed['clean_title'] ?? 'Unknown Series');
        $showTitle = $this->nameParser->cleanTitleString($rawShowTitle);
        $seasonNum = (int) ($parsed['season'] ?? 1);
        $epNum = (int) ($parsed['episode'] ?? 1);

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
            [
                'series_id' => $series->id,
                'season_number' => $seasonNum,
            ],
            [
                'title' => "Season {$seasonNum}",
                'overview' => "Season {$seasonNum} of {$showTitle}",
                'poster_path' => $series->poster_path,
            ]
        );

        $epTitle = $parsed['episode_title'] ?? "Episode {$epNum}";

        $episode = Episode::updateOrCreate(
            [
                'season_id' => $season->id,
                'episode_number' => $epNum,
            ],
            [
                'series_id' => $series->id,
                'title' => $epTitle,
                'file_path' => $file['path'],
                'file_size_bytes' => $file['size_bytes'] ?? 0,
                'overview' => "Episode {$epNum} of Season {$seasonNum}",
                'still_path' => $series->backdrop_path ?? $series->poster_path,
                'runtime_minutes' => 45,
                'air_date' => now()->subDays(max(1, 100 - ($epNum * 7)))->toDateString(),
            ]
        );

        if (!empty($file['subtitles'])) {
            foreach ($file['subtitles'] as $sub) {
                $lang = $sub['language'] ?? 'und';
                $langName = match ($lang) {
                    'ar' => 'Arabic',
                    'en' => 'English',
                    'fr' => 'French',
                    'es' => 'Spanish',
                    'de' => 'German',
                    default => 'Original Subtitle',
                };

                Subtitle::updateOrCreate(
                    [
                        'subtitlable_id' => $episode->id,
                        'subtitlable_type' => Episode::class,
                        'file_path' => $sub['path'],
                    ],
                    [
                        'language' => $lang,
                        'language_name' => $langName,
                        'format' => $sub['extension'] ?? 'srt',
                    ]
                );
            }
        }

        return $episode;
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
