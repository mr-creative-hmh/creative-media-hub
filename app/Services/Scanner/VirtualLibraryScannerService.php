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

        foreach ($allFiles as $file) {
            $currentStatus = $this->getScanStatus();
            if ($currentStatus['status'] === 'cancelled') {
                break;
            }

            while ($this->getScanStatus()['status'] === 'paused') {
                sleep(1);
            }

            $parsed = $file['parsed'] ?? $this->parser->parse($file['filename']);
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
                Log::error("VirtualScanner error processing {$file['path']}: " . $e->getMessage());
            }

            $processed++;
        }

        $this->updateScanStatus([
            'status' => 'completed',
            'progress_percent' => 100,
            'processed_files' => $processed,
            'scanned_items' => array_slice($recentItems, -15),
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
        $cleanTitle = $parsed['title'] ?? pathinfo($file['filename'], PATHINFO_FILENAME);
        $year = $parsed['year'] ?? null;

        // Fetch rich metadata
        $meta = $this->metadata->aggregateMovieMetadata($cleanTitle, $year);

        $posterUrl = $file['local_poster'] ?? ($meta['poster_url'] ?? null);

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
                'backdrop_path' => $meta['backdrop_url'] ?? null,
                'trailer_url' => $meta['trailer_url'] ?? null,
                'rating' => $meta['rating'] ?? 7.5,
                'runtime_minutes' => $meta['runtime'] ?? 115,
                'resolution' => $parsed['resolution'] ?? '1080p',
                'video_codec' => $parsed['codec'] ?? 'HEVC',
                'audio_codec' => $parsed['audio'] ?? 'AAC 5.1',
                'file_size_bytes' => $file['size_bytes'] ?? 0,
                'folder_path' => dirname($file['path']),
            ]
        );

        // Attach genres
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

        // Link local subtitles if found
        if (!empty($file['subtitles'])) {
            foreach ($file['subtitles'] as $sub) {
                Subtitle::updateOrCreate(
                    ['file_path' => $sub['path']],
                    [
                        'media_item_id' => $movie->id,
                        'language' => str_contains(strtolower($sub['filename']), 'ar') ? 'ar' : 'en',
                        'format' => $sub['extension'] ?? 'srt',
                        'source' => 'local_scan',
                    ]
                );
            }
        }

        return $movie;
    }

    protected function indexSeriesEpisode(array $file, array $parsed): Episode
    {
        $seriesTitle = $parsed['title'] ?? pathinfo($file['filename'], PATHINFO_FILENAME);
        $seasonNum = $parsed['season'] ?? 1;
        $episodeNum = $parsed['episode'] ?? 1;
        $year = $parsed['year'] ?? null;

        // 1. Series
        $seriesMeta = $this->metadata->aggregateSeriesMetadata($seriesTitle, $year);
        $posterUrl = $file['local_poster'] ?? ($seriesMeta['poster_url'] ?? null);

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
                'backdrop_path' => $seriesMeta['backdrop_url'] ?? null,
                'rating' => $seriesMeta['rating'] ?? 8.0,
                'folder_path' => dirname($file['path']),
            ]
        );

        // 2. Season
        $season = Season::firstOrCreate(
            ['series_id' => $series->id, 'season_number' => $seasonNum],
            ['title' => "Season {$seasonNum}"]
        );

        // 3. Episode
        $episode = Episode::updateOrCreate(
            ['file_path' => $file['path']],
            [
                'series_id' => $series->id,
                'season_id' => $season->id,
                'episode_number' => $episodeNum,
                'title' => "Episode {$episodeNum}",
                'overview' => "Episode {$episodeNum} of Season {$seasonNum}",
                'resolution' => $parsed['resolution'] ?? '1080p',
                'video_codec' => $parsed['codec'] ?? 'HEVC',
                'audio_codec' => $parsed['audio'] ?? 'AAC 5.1',
                'file_size_bytes' => $file['size_bytes'] ?? 0,
            ]
        );

        // Link local subtitles for episode
        if (!empty($file['subtitles'])) {
            foreach ($file['subtitles'] as $sub) {
                Subtitle::updateOrCreate(
                    ['file_path' => $sub['path']],
                    [
                        'episode_id' => $episode->id,
                        'language' => str_contains(strtolower($sub['filename']), 'ar') ? 'ar' : 'en',
                        'format' => $sub['extension'] ?? 'srt',
                        'source' => 'local_scan',
                    ]
                );
            }
        }

        return $episode;
    }
}
