<?php

namespace App\Services\Scanner;

use App\Models\Episode;
use App\Models\Genre;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Models\Subtitle;
use App\Services\Metadata\MetadataAggregator;
use App\Services\Metadata\TmdbProvider;
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

    protected MediaProbeService $mediaProbe;

    public function __construct(
        FilesystemScannerService $fsScanner,
        SceneNameParserService $nameParser,
        MetadataAggregator $metadata,
        WebArtworkSearchService $webArtwork,
        EmbeddedSubtitleDetectorService $embeddedSubDetector,
        MediaProbeService $mediaProbe
    ) {
        $this->fsScanner = $fsScanner;
        $this->nameParser = $nameParser;
        $this->metadata = $metadata;
        $this->webArtwork = $webArtwork;
        $this->embeddedSubDetector = $embeddedSubDetector;
        $this->mediaProbe = $mediaProbe;
    }

    public function getScanStatus(): array
    {
        return Cache::get('virtual_scanner_job_status', [
            'status' => 'idle',
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

    public function initScan(array $directories, string $scanMode = 'incremental'): array
    {
        $allFiles = [];
        foreach ($directories as $dir) {
            $path = $dir['path'] ?? '';
            $typeHint = $dir['type'] ?? 'mixed';
            if (! empty($path) && (is_dir($path) || is_dir(str_replace('\\', '/', $path)))) {
                $scanned = $this->fsScanner->scanDirectory($path, true);
                foreach ($scanned as $f) {
                    $f['type_hint'] = $typeHint;
                    $allFiles[] = $f;
                }
            }
        }

        // In incremental mode, filter out already-indexed files
        if ($scanMode === 'incremental') {
            $allFiles = $this->filterAlreadyIndexedFiles($allFiles);
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
            'scan_mode' => $scanMode,
            'logs' => [
                [
                    'time' => now()->format('H:i:s'),
                    'level' => 'info',
                    'message' => "Scanner initialized ({$scanMode}). Found {$totalFiles} video files to index.",
                ],
            ],
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        Cache::put('virtual_scanner_job_status', $jobData, now()->addHours(6));

        return [
            'total_files' => $totalFiles,
            'status' => $jobData['status'],
            'scan_mode' => $scanMode,
        ];
    }

    public function initScanForFolder(string $folderPath, string $typeHint = 'mixed', string $scanMode = 'incremental'): array
    {
        $allFiles = [];
        $cleanPath = rtrim(str_replace('\\', '/', $folderPath), '/');

        if (is_dir($cleanPath)) {
            $scanned = $this->fsScanner->scanDirectory($cleanPath, true);
            foreach ($scanned as $f) {
                $f['type_hint'] = $typeHint;
                $allFiles[] = $f;
            }
        }

        // In incremental mode, filter out already-indexed files
        if ($scanMode === 'incremental') {
            $allFiles = $this->filterAlreadyIndexedFiles($allFiles);
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
            'scan_mode' => $scanMode,
            'logs' => [
                [
                    'time' => now()->format('H:i:s'),
                    'level' => 'info',
                    'message' => "Folder scanner initialized for {$cleanPath} ({$scanMode}). Found {$totalFiles} files.",
                ],
            ],
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        Cache::put('virtual_scanner_job_status', $jobData, now()->addHours(6));

        return [
            'total_files' => $totalFiles,
            'status' => $jobData['status'],
            'scan_mode' => $scanMode,
        ];
    }

    public function processBatch(int $batchSize = 10): array
    {
        return $this->processNextBatch($batchSize);
    }

    public function processNextBatch(int $batchSize = 10): array
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

            return [
                'status' => $jobData['status'],
                'has_more' => false,
                'progress_percent' => $jobData['progress_percent'] ?? 100,
                'processed_files' => $jobData['processed_files'] ?? 0,
                'total_files' => $jobData['total_files'] ?? 0,
                'current_file' => null,
                'latest_scanned' => [],
                'latest_logs' => [],
            ];
        }

        $queue = $jobData['queue'];
        $scannedItems = $jobData['scanned_items'] ?? [];
        $logs = $jobData['logs'] ?? [];

        $batch = array_splice($queue, 0, $batchSize);

        // Process files with high performance
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
                    'resolution' => $file['parsed']['resolution'] ?? ($probeData['resolution'] ?? null),
                    'subtitles_count' => count($file['subtitles'] ?? []),
                ];

                $subCount = count($file['subtitles'] ?? []);
                $subText = $subCount > 0 ? " (+{$subCount} subtitles)" : '';

                $logs[] = [
                    'time' => now()->format('H:i:s'),
                    'level' => 'success',
                    'message' => "Indexed: [{$mediaType}] {$title}{$subText}",
                ];
            } catch (\Throwable $e) {
                $jobData['processed_files']++;
                $logs[] = [
                    'time' => now()->format('H:i:s'),
                    'level' => 'error',
                    'message' => "Failed to index {$file['filename']}: ".substr($e->getMessage(), 0, 80),
                ];
            }
        }

        $jobData['queue'] = $queue;
        $jobData['scanned_items'] = array_slice($scannedItems, -40);
        $jobData['logs'] = array_slice($logs, -100);
        $jobData['updated_at'] = now()->toDateTimeString();

        $total = max(1, $jobData['total_files']);
        $jobData['progress_percent'] = min(100, (int) round(($jobData['processed_files'] / $total) * 100));

        $hasMore = count($queue) > 0;
        if (! $hasMore) {
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
            'latest_scanned' => array_slice($scannedItems, -10),
            'latest_logs' => array_slice($logs, -15),
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

        // Probe actual file for accurate technical metadata
        $probeData = $this->mediaProbe->probe($file['path']);

        // Use probed data > parsed filename data > fallback to Unknown
        // Note: probeData now returns array with null/0 defaults, not null
        $resolution = $probeData['resolution'] ?? $parsed['resolution'] ?? 'Unknown';
        $videoCodec = $probeData['video_codec'] ?? $parsed['codec'] ?? 'Unknown';
        $audioCodec = $probeData['audio_codec'] ?? $parsed['audio'] ?? 'Unknown';
        $runtimeMinutes = ($probeData['duration'] ?? 0) > 0 ? (int) round($probeData['duration'] / 60) : 110;

        $posterUrl = $file['local_poster'] ?? null;
        $backdropUrl = $file['local_backdrop'] ?? null;

        // Perform rapid metadata search (TMDb / Online) with tight timeout
        $meta = [];
        try {
            $meta = $this->metadata->aggregateMovieMetadata($cleanTitle, $year, 'en', true);
            if (! empty($meta['poster_path']) && empty($posterUrl)) {
                $posterUrl = $meta['poster_path'];
            }
            if (! empty($meta['backdrop_path']) && empty($backdropUrl)) {
                $backdropUrl = $meta['backdrop_path'];
            }
            if (! empty($meta['runtime_minutes'])) {
                $runtimeMinutes = $meta['runtime_minutes'];
            }
        } catch (\Throwable $e) {
        }

        $movie = retry(4, function () use ($cleanTitle, $year, $meta, $file, $posterUrl, $backdropUrl, $resolution, $videoCodec, $audioCodec, $runtimeMinutes, $probeData) {
            $colName = $meta['collection_name'] ?? TmdbProvider::inferCollectionFromTitle($meta['title'] ?? $cleanTitle);
            $origLang = $meta['original_language'] ?? (preg_match('/\p{Arabic}/u', $cleanTitle) ? 'ar' : 'en');
            $origCountry = $meta['origin_country'] ?? (preg_match('/\p{Arabic}/u', $cleanTitle) ? 'EG' : null);

            $m = MediaItem::create([
                'title' => $meta['title'] ?? $cleanTitle,
                'original_title' => $meta['original_title'] ?? $cleanTitle,
                'title_ar' => $meta['title_ar'] ?? null,
                'release_year' => $meta['release_year'] ?? ($meta['year'] ?? $year),
                'overview' => $meta['overview'] ?? "Enjoy watching {$cleanTitle}.",
                'overview_ar' => $meta['overview_ar'] ?? null,
                'collection_name' => $colName,
                'collection_id' => $meta['collection_id'] ?? null,
                'collection_poster' => $meta['collection_poster'] ?? null,
                'original_language' => $origLang,
                'origin_country' => $origCountry,
                'tmdb_id' => $meta['tmdb_id'] ?? null,
                'imdb_id' => $meta['imdb_id'] ?? null,
                'rating' => $meta['rating'] ?? 7.5,
                'runtime_minutes' => $runtimeMinutes,
                'duration_seconds' => ! empty($probeData['duration']) && $probeData['duration'] > 0 ? (int) round($probeData['duration']) : ($runtimeMinutes * 60),
                'file_path' => $file['path'],
                'folder_path' => str_replace('\\', '/', dirname($file['path'])),
                'file_size_bytes' => $file['size_bytes'] ?? 0,
                'resolution' => $resolution,
                'video_codec' => $videoCodec,
                'audio_codec' => $audioCodec,
                'video_profile' => $probeData['video_profile'] ?? null,
                'video_bitrate' => $probeData['video_bitrate'] ?? 0,
                'audio_channels' => $probeData['audio_channels'] ?? 0,
                'audio_channel_layout' => $probeData['audio_channel_layout'] ?? null,
                'audio_bitrate' => $probeData['audio_bitrate'] ?? 0,
                'framerate' => $probeData['framerate'] ?? 0,
                'container_format' => $probeData['container'] ?? null,
                'hdr_format' => $probeData['hdr_format'] ?? null,
                'color_space' => $probeData['color_space'] ?? null,
                'color_transfer' => $probeData['color_transfer'] ?? null,
                'total_bitrate' => $probeData['total_bitrate'] ?? 0,
                'poster_path' => $posterUrl,
                'backdrop_path' => $backdropUrl,
                'is_favorite' => false,
            ]);

            if (! empty($meta['genres'])) {
                $genreIds = [];
                foreach ($meta['genres'] as $gName) {
                    $genre = Genre::firstOrCreate(
                        ['slug' => Str::slug($gName)],
                        ['name_en' => $gName, 'name_ar' => $gName]
                    );
                    $genreIds[] = $genre->id;
                }
                $m->genres()->sync($genreIds);
            }

            return $m;
        }, 150);

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
        $year = $parsed['year'] ?? null;

        $series = Series::where('title', 'like', $showTitle)
            ->orWhere('original_title', 'like', $showTitle)
            ->first();

        $epPath = str_replace('\\', '/', $file['path']);
        $epDir = dirname($epPath);
        $epDirBase = strtolower(basename($epDir));
        $seriesFolder = preg_match('/^(season\s*\d+|s\d+|specials?)$/i', $epDirBase) ? dirname($epDir) : $epDir;

        if (! $series) {
            $series = retry(4, function () use ($showTitle, $year, $seriesFolder) {
                return Series::create([
                    'title' => $showTitle,
                    'original_title' => $showTitle,
                    'release_year' => $year,
                    'folder_path' => $seriesFolder,
                    'overview' => "Experience the complete series of {$showTitle}.",
                    'rating' => 8.0,
                    'status' => 'Continuing',
                ]);
            }, 150);

            try {
                $meta = $this->metadata->aggregateSeriesMetadata($showTitle, $year, 'en', true);
                $posterUrl = $meta['poster_path'] ?? ($file['local_poster'] ?? null);
                $backdropUrl = $meta['backdrop_path'] ?? ($file['local_backdrop'] ?? null);
                $seriesYear = $meta['release_year'] ?? ($meta['year'] ?? $year);

                retry(3, function () use ($series, $meta, $posterUrl, $backdropUrl, $seriesYear) {
                    $series->update([
                        'title_ar' => $meta['title_ar'] ?? null,
                        'overview' => $meta['overview'] ?? $series->overview,
                        'overview_ar' => $meta['overview_ar'] ?? null,
                        'tmdb_id' => $meta['tmdb_id'] ?? null,
                        'imdb_id' => $meta['imdb_id'] ?? null,
                        'poster_path' => $posterUrl,
                        'backdrop_path' => $backdropUrl,
                        'release_year' => $seriesYear,
                        'rating' => $meta['rating'] ?? 8.0,
                    ]);

                    if (! empty($meta['genres'])) {
                        $genreIds = [];
                        foreach ($meta['genres'] as $gName) {
                            $g = Genre::firstOrCreate(
                                ['slug' => Str::slug($gName)],
                                ['name_en' => $gName, 'name_ar' => $gName]
                            );
                            $genreIds[] = $g->id;
                        }
                        $series->genres()->sync($genreIds);
                    }
                }, 100);
            } catch (\Throwable $e) {
            }
        }

        $season = Season::firstOrCreate(
            ['series_id' => $series->id, 'season_number' => $seasonNum],
            ['title' => "Season {$seasonNum}"]
        );

        $existingEp = Episode::where('file_path', $file['path'])->first();
        if ($existingEp) {
            $this->attachAllSubtitles($existingEp, $file);

            return $existingEp;
        }

        // Probe actual file for accurate technical metadata
        $probeData = $this->mediaProbe->probe($file['path']);

        // Use probed data > parsed filename data > fallback to Unknown
        // Note: probeData now returns array with null/0 defaults, not null
        $resolution = $probeData['resolution'] ?? $parsed['resolution'] ?? 'Unknown';
        $videoCodec = $probeData['video_codec'] ?? $parsed['codec'] ?? 'Unknown';
        $audioCodec = $probeData['audio_codec'] ?? $parsed['audio'] ?? 'Unknown';
        $runtimeMinutes = ($probeData['duration'] ?? 0) > 0 ? (int) round($probeData['duration'] / 60) : 45;

        $episode = retry(4, function () use ($series, $season, $epNum, $epTitle, $file, $resolution, $videoCodec, $audioCodec, $runtimeMinutes, $probeData) {
            $ep = Episode::create([
                'series_id' => $series->id,
                'season_id' => $season->id,
                'episode_number' => $epNum,
                'title' => $epTitle,
                'overview' => "Episode {$epNum}",
                'runtime_minutes' => $runtimeMinutes,
                'duration_seconds' => ! empty($probeData['duration']) && $probeData['duration'] > 0 ? (int) round($probeData['duration']) : ($runtimeMinutes * 60),
                'file_path' => $file['path'],
                'file_size_bytes' => $file['size_bytes'] ?? 0,
                'resolution' => $resolution,
                'video_codec' => $videoCodec,
                'audio_codec' => $audioCodec,
                'video_profile' => $probeData['video_profile'] ?? null,
                'video_bitrate' => $probeData['video_bitrate'] ?? 0,
                'audio_channels' => $probeData['audio_channels'] ?? 0,
                'audio_channel_layout' => $probeData['audio_channel_layout'] ?? null,
                'audio_bitrate' => $probeData['audio_bitrate'] ?? 0,
                'framerate' => $probeData['framerate'] ?? 0,
                'container_format' => $probeData['container'] ?? null,
                'hdr_format' => $probeData['hdr_format'] ?? null,
                'color_space' => $probeData['color_space'] ?? null,
                'color_transfer' => $probeData['color_transfer'] ?? null,
                'total_bitrate' => $probeData['total_bitrate'] ?? 0,
                'still_path' => null,
            ]);

            return $ep;
        }, 150);

        $this->attachAllSubtitles($episode, $file);

        return $episode;
    }

    protected function filterAlreadyIndexedFiles(array $files): array
    {
        if (empty($files)) {
            return [];
        }

        $paths = array_column($files, 'path');
        $paths = array_filter($paths, fn ($p) => ! empty($p));

        if (empty($paths)) {
            return $files;
        }

        // Get all already indexed file paths in a single query
        $indexedPaths = MediaItem::whereIn('file_path', $paths)
            ->pluck('file_path')
            ->toArray();

        $indexedEpisodes = Episode::whereIn('file_path', $paths)
            ->pluck('file_path')
            ->toArray();

        $allIndexed = array_merge($indexedPaths, $indexedEpisodes);

        // Filter out files that are already indexed
        return array_filter($files, fn ($f) => ! in_array($f['path'] ?? '', $allIndexed, true));
    }

    protected function attachAllSubtitles(mixed $model, array $file): void
    {
        $existingSubPaths = $model->subtitles()->pluck('file_path')->toArray();
        $isFirst = count($existingSubPaths) === 0;

        // 1. External Subtitle Files matching video
        if (! empty($file['subtitles'])) {
            foreach ($file['subtitles'] as $sub) {
                $subPath = $sub['path'] ?? '';
                if (in_array($subPath, $existingSubPaths, true)) {
                    continue;
                }

                $lang = $sub['language'] ?? 'und';
                $langName = $sub['language_name'] ?? 'Unknown';

                if ($lang === 'und') {
                    $detected = $this->embeddedSubDetector->resolveLanguageFromContext($lang, '', $subPath, 0);
                    if ($detected !== 'und') {
                        $lang = $detected;
                        $langName = $this->embeddedSubDetector->getLanguageName($lang);
                    }
                }

                $model->subtitles()->create([
                    'language' => $lang,
                    'language_name' => $langName,
                    'format' => $sub['format'] ?? 'srt',
                    'file_path' => $subPath,
                    'is_embedded' => false,
                    'is_default' => $isFirst,
                ]);

                $existingSubPaths[] = $subPath;
                $isFirst = false;
            }
        }

        // 2. Embedded subtitle tracks from container header
        try {
            $embedded = $this->embeddedSubDetector->detectEmbeddedSubtitles($file['path']);
            foreach ($embedded as $sub) {
                $subKey = "embedded:{$sub['stream_index']}:{$file['path']}";
                if (in_array($subKey, $existingSubPaths, true)) {
                    continue;
                }

                $model->subtitles()->create([
                    'language' => $sub['language'] ?? 'und',
                    'language_name' => $sub['language_name'] ?? 'Embedded Track',
                    'format' => $sub['codec'] ?? 'subrip',
                    'file_path' => $subKey,
                    'is_embedded' => true,
                    'is_default' => $isFirst,
                ]);

                $existingSubPaths[] = $subKey;
                $isFirst = false;
            }
        } catch (\Throwable $e) {
        }
    }

    public function enrichMissingMetadata(int $limit = 50): array
    {
        $enrichedCount = 0;

        $movies = MediaItem::whereNull('poster_path')
            ->orWhereNull('overview_ar')
            ->orWhereNull('title_ar')
            ->orWhere('overview', 'like', 'Enjoy watching%')
            ->take($limit)
            ->get();

        foreach ($movies as $movie) {
            try {
                $meta = $this->metadata->aggregateMovieMetadata($movie->title, $movie->release_year);
                $poster = $meta['poster_path'] ?? null;
                if (empty($poster)) {
                    $poster = $this->webArtwork->searchAndDownloadArtwork($movie->title, $movie->release_year, 'movie');
                }

                retry(3, function () use ($movie, $meta, $poster) {
                    $movie->update([
                        'title_ar' => $meta['title_ar'] ?? $movie->title_ar,
                        'overview_ar' => $meta['overview_ar'] ?? $movie->overview_ar,
                        'overview' => $meta['overview'] ?? $movie->overview,
                        'tmdb_id' => $meta['tmdb_id'] ?? $movie->tmdb_id,
                        'imdb_id' => $meta['imdb_id'] ?? $movie->imdb_id,
                        'poster_path' => $poster ?? $movie->poster_path,
                        'backdrop_path' => $meta['backdrop_path'] ?? $movie->backdrop_path,
                        'rating' => $meta['rating'] ?? $movie->rating,
                        'runtime_minutes' => $meta['runtime_minutes'] ?? $movie->runtime_minutes,
                    ]);

                    if (! empty($meta['genres'])) {
                        $genreIds = [];
                        foreach ($meta['genres'] as $gName) {
                            $g = Genre::firstOrCreate(
                                ['slug' => Str::slug($gName)],
                                ['name_en' => $gName, 'name_ar' => $gName]
                            );
                            $genreIds[] = $g->id;
                        }
                        $movie->genres()->sync($genreIds);
                    }
                }, 100);
                $enrichedCount++;
            } catch (\Throwable $e) {
            }
        }

        $seriesList = Series::whereNull('poster_path')
            ->orWhereNull('overview_ar')
            ->orWhereNull('title_ar')
            ->orWhere('overview', 'like', 'Experience the complete series%')
            ->take($limit)
            ->get();

        foreach ($seriesList as $series) {
            try {
                $meta = $this->metadata->aggregateSeriesMetadata($series->title, $series->release_year);
                $poster = $meta['poster_path'] ?? null;
                if (empty($poster)) {
                    $poster = $this->webArtwork->searchAndDownloadArtwork($series->title, $series->release_year, 'series');
                }

                retry(3, function () use ($series, $meta, $poster) {
                    $series->update([
                        'title_ar' => $meta['title_ar'] ?? $series->title_ar,
                        'overview_ar' => $meta['overview_ar'] ?? $series->overview_ar,
                        'overview' => $meta['overview'] ?? $series->overview,
                        'tmdb_id' => $meta['tmdb_id'] ?? $series->tmdb_id,
                        'imdb_id' => $meta['imdb_id'] ?? $series->imdb_id,
                        'poster_path' => $poster ?? $series->poster_path,
                        'backdrop_path' => $meta['backdrop_path'] ?? $series->backdrop_path,
                        'rating' => $meta['rating'] ?? $series->rating,
                    ]);

                    if (! empty($meta['genres'])) {
                        $genreIds = [];
                        foreach ($meta['genres'] as $gName) {
                            $g = Genre::firstOrCreate(
                                ['slug' => Str::slug($gName)],
                                ['name_en' => $gName, 'name_ar' => $gName]
                            );
                            $genreIds[] = $g->id;
                        }
                        $series->genres()->sync($genreIds);
                    }
                }, 100);
                $enrichedCount++;
            } catch (\Throwable $e) {
            }
        }

        return [
            'enriched_count' => $enrichedCount,
            'remaining_movies' => MediaItem::whereNull('overview_ar')->count(),
            'remaining_series' => Series::whereNull('overview_ar')->count(),
        ];
    }
}
