<?php

namespace App\Services\Scanner;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\Genre;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Services\Metadata\MetadataAggregator;
use App\Services\Organizer\FilesystemScannerService;
use App\Services\Organizer\SceneNameParserService;
use Illuminate\Support\Facades\Cache;
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
        $this->updateScanStatus([
            'status' => 'cancelled',
            'progress_percent' => 0,
            'current_file' => 'Scan cancelled by user.',
            'finished_at' => now()->toIso8601String(),
        ]);
    }

    public function scanDirectories(array $directories): array
    {
        $allFiles = [];
        foreach ($directories as $dir) {
            $path = $dir['path'] ?? '';
            if (!empty($path) && is_dir($path)) {
                $scanned = $this->fsScanner->scanDirectory($path, true);
                foreach ($scanned as $f) {
                    $f['expected_type'] = $dir['type'] ?? 'mixed';
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
            ]);
            return ['success' => true, 'total' => 0, 'processed' => 0];
        }

        $this->updateScanStatus([
            'status' => 'running',
            'progress_percent' => 0,
            'total_files' => $total,
            'processed_files' => 0,
            'started_at' => now()->toIso8601String(),
            'errors' => [],
            'scanned_items' => [],
        ]);

        $processed = 0;
        $recentItems = [];

        foreach ($allFiles as $index => $file) {
            $currentStatus = $this->getScanStatus();
            if ($currentStatus['status'] === 'cancelled') {
                break;
            }

            // Check if paused
            while ($this->getScanStatus()['status'] === 'paused') {
                sleep(1);
            }

            $parsed = $this->parser->parse($file['filename']);
            $isSeries = ($file['expected_type'] === 'series') || ($parsed['type'] === 'series');

            $this->updateScanStatus([
                'current_file' => $file['filename'],
                'processed_files' => $processed + 1,
                'progress_percent' => (int) round((($processed + 1) / $total) * 100),
            ]);

            try {
                if ($isSeries) {
                    $item = $this->indexSeriesEpisode($file, $parsed);
                } else {
                    $item = $this->indexMovie($file, $parsed);
                }

                if ($item) {
                    $recentItems[] = [
                        'title' => $item->title ?? ($parsed['title'] ?? $file['filename']),
                        'type' => $isSeries ? 'series' : 'movie',
                        'resolution' => $parsed['resolution'] ?? '1080p',
                        'file_path' => $file['path'],
                    ];
                }
            } catch (\Throwable $e) {
                // Log non-fatal error
            }

            $processed++;
        }

        $this->updateScanStatus([
            'status' => 'completed',
            'progress_percent' => 100,
            'processed_files' => $processed,
            'scanned_items' => array_slice($recentItems, -10),
            'finished_at' => now()->toIso8601String(),
        ]);

        return [
            'success' => true,
            'total' => $total,
            'processed' => $processed,
            'items' => $recentItems,
        ];
    }

    protected function indexMovie(array $file, array $parsed): MediaItem
    {
        $title = $parsed['title'] ?? pathinfo($file['filename'], PATHINFO_FILENAME);
        $year = $parsed['year'] ?? null;

        // Fetch rich metadata via multi-provider aggregator
        $meta = $this->metadata->aggregateMovieMetadata($title, $year);

        $movie = MediaItem::updateOrCreate(
            ['file_path' => $file['path']],
            [
                'title' => $meta['title'] ?? $title,
                'original_title' => $meta['original_title'] ?? $title,
                'title_ar' => $meta['title_ar'] ?? null,
                'release_year' => $meta['year'] ?? $year,
                'tmdb_id' => $meta['tmdb_id'] ?? null,
                'imdb_id' => $meta['imdb_id'] ?? null,
                'overview' => $meta['overview'] ?? null,
                'overview_ar' => $meta['overview_ar'] ?? null,
                'poster_path' => $meta['poster_url'] ?? null,
                'backdrop_path' => $meta['backdrop_url'] ?? null,
                'trailer_url' => $meta['trailer_url'] ?? null,
                'rating' => $meta['rating'] ?? 0.0,
                'runtime_minutes' => $meta['runtime'] ?? null,
                'resolution' => $parsed['resolution'] ?? '1080p',
                'video_codec' => $parsed['codec'] ?? 'HEVC',
                'audio_codec' => $parsed['audio'] ?? 'AAC 5.1',
                'file_size_bytes' => $file['size_bytes'] ?? 0,
                'folder_path' => dirname($file['path']),
            ]
        );

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

        return $movie;
    }

    protected function indexSeriesEpisode(array $file, array $parsed): Episode
    {
        $seriesTitle = $parsed['title'] ?? pathinfo($file['filename'], PATHINFO_FILENAME);
        $seasonNum = $parsed['season'] ?? 1;
        $episodeNum = $parsed['episode'] ?? 1;
        $year = $parsed['year'] ?? null;

        // 1. Find or create Series
        $seriesMeta = $this->metadata->aggregateSeriesMetadata($seriesTitle, $year);
        $series = Series::firstOrCreate(
            ['title' => $seriesMeta['title'] ?? $seriesTitle],
            [
                'title_ar' => $seriesMeta['title_ar'] ?? null,
                'release_year' => $seriesMeta['year'] ?? $year,
                'tmdb_id' => $seriesMeta['tmdb_id'] ?? null,
                'tvmaze_id' => $seriesMeta['tvmaze_id'] ?? null,
                'overview' => $seriesMeta['overview'] ?? null,
                'overview_ar' => $seriesMeta['overview_ar'] ?? null,
                'poster_path' => $seriesMeta['poster_url'] ?? null,
                'backdrop_path' => $seriesMeta['backdrop_url'] ?? null,
                'rating' => $seriesMeta['rating'] ?? 0.0,
                'folder_path' => dirname($file['path']),
            ]
        );

        // 2. Find or create Season
        $season = Season::firstOrCreate(
            ['series_id' => $series->id, 'season_number' => $seasonNum],
            ['title' => "Season {$seasonNum}"]
        );

        // 3. Find or create Episode
        return Episode::updateOrCreate(
            ['file_path' => $file['path']],
            [
                'series_id' => $series->id,
                'season_id' => $season->id,
                'episode_number' => $episodeNum,
                'title' => "Episode {$episodeNum}",
                'resolution' => $parsed['resolution'] ?? '1080p',
                'video_codec' => $parsed['codec'] ?? 'HEVC',
                'audio_codec' => $parsed['audio'] ?? 'AAC 5.1',
                'file_size_bytes' => $file['size_bytes'] ?? 0,
            ]
        );
    }
}
