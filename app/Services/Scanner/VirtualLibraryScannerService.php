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
use App\Services\Organizer\FilesystemScannerService;
use App\Services\Organizer\SceneNameParserService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VirtualLibraryScannerService
{
    protected SceneNameParserService $parser;
    protected FilesystemScannerService $fsScanner;
    protected MetadataAggregator $metadata;

    public function __construct(
        SceneNameParserService $parser,
        FilesystemScannerService $fsScanner,
        MetadataAggregator $metadata
    ) {
        $this->parser = $parser;
        $this->fsScanner = $fsScanner;
        $this->metadata = $metadata;
    }

    public function getScanStatus(): array
    {
        return Cache::get('virtual_scan_job', [
            'status' => 'idle', // idle, running, paused, completed, cancelled
            'progress_percent' => 0,
            'total_files' => 0,
            'processed_files' => 0,
            'current_file' => '',
            'scanned_items' => [],
            'started_at' => null,
            'finished_at' => null,
            'errors' => [],
        ]);
    }

    public function updateScanStatus(array $data): void
    {
        $current = $this->getScanStatus();
        Cache::put('virtual_scan_job', array_merge($current, $data), 3600);
    }

    public function pauseScan(): void
    {
        $this->updateScanStatus(['status' => 'paused']);
    }

    public function resumeScan(): void
    {
        $this->updateScanStatus(['status' => 'running']);
    }

    public function cancelScan(): void
    {
        Cache::forget('virtual_scan_queue');
        $this->updateScanStatus([
            'status' => 'cancelled',
            'progress_percent' => 0,
            'current_file' => 'Scan cancelled by user.',
            'finished_at' => now()->toIso8601String(),
        ]);
    }

    public function initScan(array $directories): array
    {
        $allFiles = [];
        foreach ($directories as $dir) {
            $rawPath = is_array($dir) ? ($dir['path'] ?? '') : (string) $dir;
            if (!empty($rawPath)) {
                $scanned = $this->fsScanner->scanDirectory($rawPath, true);
                $expectedType = is_array($dir) ? ($dir['type'] ?? 'mixed') : 'mixed';
                foreach ($scanned as $f) {
                    $f['expected_type'] = $expectedType;
                    $allFiles[] = $f;
                }
            }
        }

        $total = count($allFiles);
        if ($total === 0) {
            $this->updateScanStatus([
                'status' => 'completed',
                'progress_percent' => 100,
                'total_files' => 0,
                'processed_files' => 0,
                'current_file' => 'No media files found in selected directories.',
                'finished_at' => now()->toIso8601String(),
                'scanned_items' => [],
            ]);
            Cache::forget('virtual_scan_queue');
            return ['success' => true, 'total' => 0, 'status' => $this->getScanStatus()];
        }

        Cache::put('virtual_scan_queue', $allFiles, 3600);

        $this->updateScanStatus([
            'status' => 'running',
            'progress_percent' => 0,
            'total_files' => $total,
            'processed_files' => 0,
            'started_at' => now()->toIso8601String(),
            'current_file' => 'Initializing virtual scanner...',
            'errors' => [],
            'scanned_items' => [],
        ]);

        return ['success' => true, 'total' => $total, 'status' => $this->getScanStatus()];
    }

    public function processNextBatch(int $batchSize = 3): array
    {
        $status = $this->getScanStatus();

        if ($status['status'] !== 'running') {
            return ['status' => $status, 'has_more' => false];
        }

        $queue = Cache::get('virtual_scan_queue', []);

        if (empty($queue)) {
            $this->updateScanStatus([
                'status' => 'completed',
                'progress_percent' => 100,
                'finished_at' => now()->toIso8601String(),
            ]);
            return ['status' => $this->getScanStatus(), 'has_more' => false];
        }

        $batch = array_splice($queue, 0, $batchSize);
        Cache::put('virtual_scan_queue', $queue, 3600);

        $processed = $status['processed_files'];
        $total = max(1, $status['total_files']);
        $recentItems = $status['scanned_items'] ?? [];

        foreach ($batch as $file) {
            $currentStatus = $this->getScanStatus();
            if ($currentStatus['status'] === 'cancelled') {
                return ['status' => $currentStatus, 'has_more' => false];
            }

            $parsed = $file['parsed'] ?? $this->parser->parse($file['path']);
            $isSeries = ($file['expected_type'] === 'series') || ($parsed['type'] === 'series');

            $processed++;

            try {
                if ($isSeries) {
                    $item = $this->indexSeriesEpisode($file, $parsed);
                } else {
                    $item = $this->indexMovie($file, $parsed);
                }

                if ($item) {
                    $cleanTitle = $parsed['clean_title'] ?? ($parsed['title'] ?? $file['filename']);
                    $recentItems[] = [
                        'title' => $cleanTitle,
                        'type' => $isSeries ? 'series' : 'movie',
                        'resolution' => $parsed['resolution'] ?? '1080p',
                        'file_path' => $file['path'],
                        'subtitles_count' => count($file['subtitles'] ?? []),
                    ];
                }
            } catch (\Throwable $e) {
                Log::error("Scanner error on file {$file['path']}: " . $e->getMessage());
            }

            $this->updateScanStatus([
                'current_file' => $file['filename'],
                'processed_files' => $processed,
                'progress_percent' => (int) min(100, round(($processed / $total) * 100)),
                'scanned_items' => array_slice($recentItems, -15),
            ]);
        }

        $hasMore = !empty($queue);
        if (!$hasMore) {
            $this->updateScanStatus([
                'status' => 'completed',
                'progress_percent' => 100,
                'finished_at' => now()->toIso8601String(),
            ]);
        }

        return [
            'status' => $this->getScanStatus(),
            'has_more' => $hasMore,
        ];
    }

    protected function indexMovie(array $file, array $parsed): MediaItem
    {
        $cleanTitle = $parsed['clean_title'] ?? ($parsed['title'] ?? pathinfo($file['filename'], PATHINFO_FILENAME));
        $year = $parsed['year'] ?? null;

        $meta = $this->metadata->aggregateMovieMetadata($cleanTitle, $year);
        $posterUrl = $file['local_poster'] ?? ($meta['poster_path'] ?? null);

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
                'backdrop_path' => $meta['backdrop_path'] ?? null,
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
            $genre = Genre::firstOrCreate(
                ['slug' => Str::slug($gName)],
                ['name_en' => $gName, 'name_ar' => $gName]
            );
            $genreIds[] = $genre->id;
        }
        $movie->genres()->sync($genreIds);

        // Attach subtitles
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
        $seriesTitle = $parsed['clean_title'] ?? ($parsed['title'] ?? pathinfo($file['filename'], PATHINFO_FILENAME));
        $seasonNum = (int) ($parsed['season'] ?? 1);
        $episodeNum = (int) ($parsed['episode'] ?? 1);
        $year = $parsed['year'] ?? null;

        $seriesMeta = $this->metadata->aggregateSeriesMetadata($seriesTitle, $year);
        $posterUrl = $file['local_poster'] ?? ($seriesMeta['poster_path'] ?? null);

        // 1. Unified Show Grouping
        $series = Series::firstOrCreate(
            ['title' => $seriesMeta['title'] ?? $seriesTitle],
            [
                'title_ar' => $seriesMeta['title_ar'] ?? null,
                'release_year' => $seriesMeta['year'] ?? $year,
                'tmdb_id' => $seriesMeta['tmdb_id'] ?? null,
                'tvmaze_id' => $seriesMeta['tvmaze_id'] ?? null,
                'overview' => $seriesMeta['overview'] ?? "Experience {$seriesTitle}.",
                'overview_ar' => $seriesMeta['overview_ar'] ?? null,
                'poster_path' => $posterUrl,
                'backdrop_path' => $seriesMeta['backdrop_path'] ?? null,
                'rating' => $seriesMeta['rating'] ?? 8.0,
                'folder_path' => dirname($file['path']),
            ]
        );

        // If poster or overview wasn't set earlier, enrich it now
        if (!$series->poster_path && $posterUrl) {
            $series->update(['poster_path' => $posterUrl]);
        }
        if (!$series->backdrop_path && !empty($seriesMeta['backdrop_path'])) {
            $series->update(['backdrop_path' => $seriesMeta['backdrop_path']]);
        }

        // 2. Unified Season Grouping
        $season = Season::firstOrCreate(
            ['series_id' => $series->id, 'season_number' => $seasonNum],
            ['title' => "Season {$seasonNum}"]
        );

        // 3. Unique Episode Record
        $episode = Episode::updateOrCreate(
            ['file_path' => $file['path']],
            [
                'series_id' => $series->id,
                'season_id' => $season->id,
                'episode_number' => $episodeNum,
                'title' => "Episode {$episodeNum}",
                'overview' => "Season {$seasonNum} Episode {$episodeNum}",
                'resolution' => $parsed['resolution'] ?? '1080p',
                'video_codec' => $parsed['codec'] ?? 'HEVC',
                'audio_codec' => $parsed['audio'] ?? 'AAC 5.1',
                'file_size_bytes' => $file['size_bytes'] ?? 0,
            ]
        );

        // 4. Attach Subtitles directly to the Episode
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
}
