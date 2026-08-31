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
            'logs' => [],
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

    public function addLog(string $message, string $level = 'info'): void
    {
        $status = $this->getScanStatus();
        $logs = $status['logs'] ?? [];
        $logs[] = [
            'time' => now()->format('H:i:s'),
            'level' => $level,
            'message' => $message,
        ];
        // Keep last 150 log lines
        $this->updateScanStatus(['logs' => array_slice($logs, -150)]);
    }

    public function pauseScan(): void
    {
        $this->updateScanStatus(['status' => 'paused']);
        $this->addLog('Scan paused by user.', 'warning');
    }

    public function resumeScan(): void
    {
        $this->updateScanStatus(['status' => 'running']);
        $this->addLog('Scan resumed.', 'info');
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
        $this->addLog('Scan cancelled.', 'error');
    }

    public function initScan(array $directories): array
    {
        $allFiles = [];
        $this->updateScanStatus([
            'status' => 'running',
            'progress_percent' => 0,
            'total_files' => 0,
            'processed_files' => 0,
            'started_at' => now()->toIso8601String(),
            'current_file' => 'Discovering files across monitored directories...',
            'errors' => [],
            'scanned_items' => [],
            'logs' => [],
        ]);

        $this->addLog("Scanning " . count($directories) . " monitored directories...", 'info');

        foreach ($directories as $dir) {
            $rawPath = is_array($dir) ? ($dir['path'] ?? '') : (string) $dir;
            if (!empty($rawPath)) {
                $this->addLog("Reading directory: {$rawPath}", 'info');
                $scanned = $this->fsScanner->scanDirectory($rawPath, true);
                $expectedType = is_array($dir) ? ($dir['type'] ?? 'mixed') : 'mixed';
                foreach ($scanned as $f) {
                    $f['expected_type'] = $expectedType;
                    $allFiles[] = $f;
                }
                $this->addLog("Found " . count($scanned) . " media files in {$rawPath}", 'success');
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
            $this->addLog('No media files found.', 'warning');
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
            'current_file' => "Queued {$total} files for indexing...",
        ]);

        $this->addLog("Starting indexing worker for {$total} files...", 'info');

        return ['success' => true, 'total' => $total, 'status' => $this->getScanStatus()];
    }

    public function initScanForFolder(string $path, string $type = 'mixed'): array
    {
        return $this->initScan([
            ['path' => $path, 'type' => $type]
        ]);
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
            $this->addLog("All library files processed and indexed successfully!", 'success');
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
                $cleanTitle = $parsed['clean_title'] ?? ($parsed['title'] ?? $file['filename']);

                if ($isSeries) {
                    $item = $this->indexSeriesEpisode($file, $parsed);
                    $this->addLog("Indexed TV Episode: {$cleanTitle} S{$parsed['season']}E{$parsed['episode']}", 'info');
                } else {
                    $item = $this->indexMovie($file, $parsed);
                    $this->addLog("Indexed Movie: {$cleanTitle} ({$parsed['year']})", 'info');
                }

                if (!empty($file['subtitles'])) {
                    $this->addLog("Linked " . count($file['subtitles']) . " subtitle(s) to {$cleanTitle}", 'info');
                }

                if ($item) {
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
                $this->addLog("Error indexing {$file['filename']}: " . $e->getMessage(), 'error');
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
            $this->addLog("Scan job finished. Total processed: {$processed} files.", 'success');
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
        $showTitle = $parsed['series_title'] ?? ($parsed['clean_title'] ?? 'Unknown Series');
        $seasonNum = $parsed['season'] ?? 1;
        $epNum = $parsed['episode'] ?? 1;

        // 1. Group under parent Series
        $series = Series::firstOrCreate(
            ['title' => $showTitle],
            [
                'original_title' => $showTitle,
                'release_year' => $parsed['year'] ?? null,
                'overview' => "Experience the complete series of {$showTitle}.",
                'rating' => 8.0,
                'status' => 'Continuing',
            ]
        );

        // Enrich Series metadata if missing poster/overview
        if (!$series->poster_path || !$series->overview || $series->overview === "Experience the complete series of {$showTitle}.") {
            $meta = $this->metadata->aggregateSeriesMetadata($showTitle, $parsed['year'] ?? null);
            $series->update([
                'title_ar' => $meta['title_ar'] ?? $series->title_ar,
                'overview' => $meta['overview'] ?? $series->overview,
                'overview_ar' => $meta['overview_ar'] ?? $series->overview_ar,
                'poster_path' => $file['local_poster'] ?? ($meta['poster_path'] ?? $series->poster_path),
                'backdrop_path' => $meta['backdrop_path'] ?? $series->backdrop_path,
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
        }

        // 2. Group under Season
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

        // 3. Create or update Episode
        $episode = Episode::updateOrCreate(
            [
                'season_id' => $season->id,
                'episode_number' => $epNum,
            ],
            [
                'series_id' => $series->id,
                'title' => "Episode {$epNum}",
                'file_path' => $file['path'],
                'file_size_bytes' => $file['size_bytes'] ?? 0,
                'overview' => "Episode {$epNum} of Season {$seasonNum}",
                'still_path' => $series->backdrop_path ?? $series->poster_path,
                'runtime_minutes' => 45,
                'resolution' => $parsed['resolution'] ?? '1080p',
                'video_codec' => $parsed['codec'] ?? 'HEVC',
                'audio_codec' => $parsed['audio'] ?? 'AAC 5.1',
            ]
        );

        // Attach Subtitles to Episode
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

    public function enrichMissingMetadata(int $limit = 20): array
    {
        $moviesEnriched = 0;
        $seriesEnriched = 0;

        // 1. Enrich Movies without posters
        $movies = MediaItem::whereNull('poster_path')
            ->orWhere('overview', 'like', 'Enjoy watching%')
            ->limit($limit)
            ->get();

        foreach ($movies as $m) {
            $meta = $this->metadata->aggregateMovieMetadata($m->title, $m->release_year);
            if (!empty($meta['poster_path']) || !empty($meta['overview'])) {
                $m->update([
                    'poster_path' => $meta['poster_path'] ?? $m->poster_path,
                    'backdrop_path' => $meta['backdrop_path'] ?? $m->backdrop_path,
                    'overview' => $meta['overview'] ?? $m->overview,
                    'overview_ar' => $meta['overview_ar'] ?? $m->overview_ar,
                    'rating' => $meta['rating'] ?? $m->rating,
                    'runtime_minutes' => $meta['runtime_minutes'] ?? $m->runtime_minutes,
                ]);
                $moviesEnriched++;
            }
        }

        // 2. Enrich Series without posters
        $seriesList = Series::whereNull('poster_path')
            ->orWhere('overview', 'like', 'Experience the complete%')
            ->limit($limit)
            ->get();

        foreach ($seriesList as $s) {
            $meta = $this->metadata->aggregateSeriesMetadata($s->title, $s->release_year);
            if (!empty($meta['poster_path']) || !empty($meta['overview'])) {
                $s->update([
                    'poster_path' => $meta['poster_path'] ?? $s->poster_path,
                    'backdrop_path' => $meta['backdrop_path'] ?? $s->backdrop_path,
                    'overview' => $meta['overview'] ?? $s->overview,
                    'overview_ar' => $meta['overview_ar'] ?? $s->overview_ar,
                    'rating' => $meta['rating'] ?? $s->rating,
                ]);
                $seriesEnriched++;
            }
        }

        return [
            'movies_enriched' => $moviesEnriched,
            'series_enriched' => $seriesEnriched,
            'total' => $moviesEnriched + $seriesEnriched,
        ];
    }
}
