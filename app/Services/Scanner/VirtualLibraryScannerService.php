<?php

namespace App\Services\Scanner;

use App\Models\Episode;
use App\Models\Genre;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Models\Subtitle;
use App\Services\Metadata\LibraryMasterIndexService;
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

    protected LibraryMasterIndexService $masterIndex;

    protected array $tmdbSeasonCache = [];

    public function __construct(
        FilesystemScannerService $fsScanner,
        SceneNameParserService $nameParser,
        MetadataAggregator $metadata,
        WebArtworkSearchService $webArtwork,
        EmbeddedSubtitleDetectorService $embeddedSubDetector,
        MediaProbeService $mediaProbe,
        ?LibraryMasterIndexService $masterIndex = null
    ) {
        $this->fsScanner = $fsScanner;
        $this->nameParser = $nameParser;
        $this->metadata = $metadata;
        $this->webArtwork = $webArtwork;
        $this->embeddedSubDetector = $embeddedSubDetector;
        $this->mediaProbe = $mediaProbe;
        $this->masterIndex = $masterIndex ?: app(LibraryMasterIndexService::class);
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

        // If paused, immediately return without processing any files and keep has_more = true
        if (($jobData['status'] ?? '') === 'paused') {
            return [
                'status' => 'paused',
                'has_more' => true,
                'progress_percent' => $jobData['progress_percent'] ?? 0,
                'processed_files' => $jobData['processed_files'] ?? 0,
                'total_files' => $jobData['total_files'] ?? 0,
                'current_file' => null,
                'latest_scanned' => [],
                'latest_logs' => [],
            ];
        }

        // If cancelled, immediately return has_more = false
        if (($jobData['status'] ?? '') === 'cancelled') {
            return [
                'status' => 'cancelled',
                'has_more' => false,
                'progress_percent' => $jobData['progress_percent'] ?? 0,
                'processed_files' => $jobData['processed_files'] ?? 0,
                'total_files' => $jobData['total_files'] ?? 0,
                'current_file' => null,
                'latest_scanned' => [],
                'latest_logs' => [],
            ];
        }

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
                    'resolution' => $indexed->resolution ?? ($file['parsed']['resolution'] ?? null),
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

        // Check if an asynchronous pause or cancel occurred while this batch was running
        $latestInCache = Cache::get('virtual_scanner_job_status');
        if ($latestInCache && ($latestInCache['status'] === 'paused')) {
            $jobData['status'] = 'paused';
        } elseif ($latestInCache && ($latestInCache['status'] === 'cancelled')) {
            $jobData['status'] = 'cancelled';
            $jobData['queue'] = [];
            $hasMore = false;
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

    /**
     * Detects if an incoming video file corresponds to an existing movie that was
     * physically moved/relocated or upgraded in resolution/quality.
     */
    protected function detectRelocatedOrUpgradedMovie(array $file, array $parsed, array $probeData, string $resolution, string $videoCodec, string $audioCodec): ?MediaItem
    {
        $newPath = str_replace('\\', '/', $file['path']);
        $newDir = dirname($newPath);
        $fileSize = $file['size_bytes'] ?? (file_exists($file['path']) ? filesize($file['path']) : 0);

        // 1. Check exact size match where old path is missing on disk (Fast relocation detection)
        if ($fileSize > 0) {
            $sizeMatches = MediaItem::where('file_size_bytes', $fileSize)->get();
            foreach ($sizeMatches as $cand) {
                if (! file_exists($cand->file_path)) {
                    // Exact file relocation detected!
                    $cand->file_path = $newPath;
                    $cand->folder_path = $newDir;
                    $cand->save();

                    $this->relocateSubtitlesForMovie($cand, $file['path']);

                    return $cand;
                }
            }
        }

        // 2. Semantic Title + Year matching (Quality/Resolution upgrade detection where file size changed)
        $cleanTitle = $parsed['clean_title'] ?? ($parsed['title'] ?? pathinfo($file['filename'], PATHINFO_FILENAME));
        $year = $parsed['year'] ?? null;

        $titleQuery = MediaItem::where('title', 'like', $cleanTitle);
        if ($year) {
            $titleQuery->where('release_year', $year);
        }
        $candidates = $titleQuery->get();

        // Also search by normalized alphanumeric title if no direct title match
        if ($candidates->isEmpty()) {
            $normClean = preg_replace('/[^a-z0-9]/i', '', $cleanTitle);
            if (! empty($normClean)) {
                $candidates = MediaItem::all()->filter(function ($cand) use ($normClean, $year) {
                    if ($year && $cand->release_year && (int) $cand->release_year !== (int) $year) {
                        return false;
                    }

                    return preg_replace('/[^a-z0-9]/i', '', $cand->title) === $normClean;
                });
            }
        }

        foreach ($candidates as $cand) {
            if (! file_exists($cand->file_path)) {
                // Movie was replaced/upgraded! Update existing record in-place without duplicating
                $cand->file_path = $newPath;
                $cand->folder_path = $newDir;
                $cand->file_size_bytes = $fileSize;
                $cand->resolution = $resolution;
                $cand->video_codec = $videoCodec;
                $cand->audio_codec = $audioCodec;
                if (! empty($probeData['duration']) && $probeData['duration'] > 0) {
                    $cand->duration_seconds = (int) round($probeData['duration']);
                    $cand->runtime_minutes = (int) round($probeData['duration'] / 60);
                }
                $cand->save();

                $this->relocateSubtitlesForMovie($cand, $file['path']);

                return $cand;
            }
        }

        return null;
    }

    protected function relocateSubtitlesForMovie(MediaItem $movie, string $newVideoPath): void
    {
        $newDir = dirname($newVideoPath);
        $subs = Subtitle::where('media_item_id', $movie->id)->get();
        foreach ($subs as $sub) {
            if (! file_exists($sub->file_path)) {
                $base = pathinfo($sub->file_path, PATHINFO_BASENAME);
                $candidatePath = $newDir.'/'.$base;
                if (file_exists($candidatePath)) {
                    $sub->file_path = str_replace('\\', '/', $candidatePath);
                    $sub->save();
                }
            }
        }
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

        $resolution = (! empty($probeData['resolution']) && $probeData['resolution'] !== 'Unknown')
            ? $probeData['resolution']
            : ($parsed['resolution'] ?? 'Unknown');
        $videoCodec = (! empty($probeData['video_codec']) && $probeData['video_codec'] !== 'Unknown')
            ? $probeData['video_codec']
            : ($parsed['codec'] ?? 'Unknown');
        $audioCodec = (! empty($probeData['audio_codec']) && $probeData['audio_codec'] !== 'Unknown')
            ? $probeData['audio_codec']
            : ($parsed['audio'] ?? 'Unknown');
        $runtimeMinutes = ($probeData['duration'] ?? 0) > 0 ? (int) round($probeData['duration'] / 60) : 110;

        // Check for moved or upgraded existing movie record
        $relocated = $this->detectRelocatedOrUpgradedMovie($file, $parsed, $probeData, $resolution, $videoCodec, $audioCodec);
        if ($relocated) {
            $this->attachAllSubtitles($relocated, $file);

            return $relocated;
        }

        $posterUrl = $file['local_poster'] ?? null;
        $backdropUrl = $file['local_backdrop'] ?? null;

        // Perform rapid metadata search (TMDb / Online) with tight timeout
        $meta = $this->masterIndex->lookupMovie($cleanTitle, $year) ?? [];
        try {
            if (empty($meta)) {
                $meta = $this->metadata->aggregateMovieMetadata($cleanTitle, $year, 'en', true);
            }
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
        $epEnd = isset($parsed['episode_end']) ? (int) $parsed['episode_end'] : null;
        $epTitle = $parsed['episode_title'] ?? "Episode {$epNum}";
        $year = $parsed['year'] ?? null;
        $endYear = $parsed['end_year'] ?? null;

        $epPath = str_replace('\\', '/', $file['path']);
        $epDir = dirname($epPath);
        $epDirBase = strtolower(basename($epDir));
        $seriesFolder = preg_match('/^(season\s*\d+|s\d+|specials?)$/i', $epDirBase) ? dirname($epDir) : $epDir;
        $seriesFolderNorm = str_replace('\\', '/', $seriesFolder);

        // 1. First, match Series by exact folder_path (handles relocated/renamed series folders)
        $series = Series::where('folder_path', $seriesFolderNorm)->first();

        // 2. If not found by folder_path, match by title / original_title
        if (! $series) {
            $series = Series::where('title', 'like', $showTitle)
                ->orWhere('original_title', 'like', $showTitle)
                ->first();
        }

        // 3. If not found, match by normalized alphanumeric title (e.g. "Sense8" vs "Sense 8")
        if (! $series) {
            $normShowTitle = preg_replace('/[^a-z0-9]/i', '', $showTitle);
            if (! empty($normShowTitle)) {
                $series = Series::all()->first(function ($s) use ($normShowTitle) {
                    return preg_replace('/[^a-z0-9]/i', '', $s->title) === $normShowTitle
                        || preg_replace('/[^a-z0-9]/i', '', $s->original_title ?? '') === $normShowTitle;
                });
            }
        }

        // If existing series was found, update missing year, end_year, or folder_path if moved
        if ($series) {
            $updates = [];
            if ($series->folder_path !== $seriesFolderNorm && is_dir($seriesFolderNorm)) {
                $updates['folder_path'] = $seriesFolderNorm;
            }
            if (empty($series->release_year) && $year) {
                $updates['release_year'] = $year;
            }
            if (empty($series->end_year) && $endYear) {
                $updates['end_year'] = $endYear;
            }
            if (! empty($updates)) {
                $series->update($updates);
            }
        } else {
            // Before creating a new series, query TMDB metadata to check if TMDb ID already exists
            $meta = [];
            try {
                $meta = $this->metadata->aggregateSeriesMetadata($showTitle, $year, 'en', true);
            } catch (\Throwable $e) {
                // ignore
            }

            if (! empty($meta['tmdb_id'])) {
                $seriesByTmdb = Series::where('tmdb_id', $meta['tmdb_id'])->first();
                if ($seriesByTmdb) {
                    $series = $seriesByTmdb;
                    if ($series->folder_path !== $seriesFolderNorm && is_dir($seriesFolderNorm)) {
                        $series->folder_path = $seriesFolderNorm;
                        $series->save();
                    }
                }
            }

            if (! $series) {
                $series = retry(4, function () use ($showTitle, $year, $endYear, $seriesFolderNorm) {
                    return Series::create([
                        'title' => $showTitle,
                        'original_title' => $showTitle,
                        'release_year' => $year,
                        'end_year' => $endYear,
                        'folder_path' => $seriesFolderNorm,
                        'overview' => "Experience the complete series of {$showTitle}.",
                        'rating' => 8.0,
                        'status' => $endYear ? 'Ended' : 'Continuing',
                    ]);
                }, 150);

                if (! empty($meta)) {
                    $posterUrl = $meta['poster_path'] ?? ($file['local_poster'] ?? null);
                    $backdropUrl = $meta['backdrop_path'] ?? ($file['local_backdrop'] ?? null);
                    $seriesYear = $meta['release_year'] ?? ($meta['year'] ?? $year);
                    $seriesEndYear = $meta['end_year'] ?? $endYear;
                    $seriesStatus = ! empty($meta['status']) ? $meta['status'] : ($seriesEndYear ? 'Ended' : $series->status);

                    try {
                        $series->update([
                            'title_ar' => $meta['title_ar'] ?? null,
                            'overview' => $meta['overview'] ?? $series->overview,
                            'overview_ar' => $meta['overview_ar'] ?? null,
                            'tmdb_id' => $meta['tmdb_id'] ?? null,
                            'imdb_id' => $meta['imdb_id'] ?? null,
                            'poster_path' => $posterUrl,
                            'backdrop_path' => $backdropUrl,
                            'release_year' => $seriesYear,
                            'end_year' => $seriesEndYear,
                            'status' => $seriesStatus,
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
                    } catch (\Throwable $e) {
                        Log::warning("Could not sync metadata to series {$series->id}: ".$e->getMessage());
                    }
                }
            }
        }

        $season = Season::firstOrCreate(
            ['series_id' => $series->id, 'season_number' => $seasonNum],
            ['title' => "Season {$seasonNum}"]
        );

        // Probe actual file for accurate technical metadata
        $probeData = $this->mediaProbe->probe($file['path']);
        $resolution = (! empty($probeData['resolution']) && $probeData['resolution'] !== 'Unknown')
            ? $probeData['resolution']
            : ($parsed['resolution'] ?? 'Unknown');
        $videoCodec = (! empty($probeData['video_codec']) && $probeData['video_codec'] !== 'Unknown')
            ? $probeData['video_codec']
            : ($parsed['codec'] ?? 'Unknown');
        $audioCodec = (! empty($probeData['audio_codec']) && $probeData['audio_codec'] !== 'Unknown')
            ? $probeData['audio_codec']
            : ($parsed['audio'] ?? 'Unknown');
        $runtimeMinutes = ($probeData['duration'] ?? 0) > 0 ? (int) round($probeData['duration'] / 60) : 45;

        $fileNormPath = str_replace('\\', '/', $file['path']);
        $fileSizeBytes = $file['size_bytes'] ?? (file_exists($file['path']) ? filesize($file['path']) : 0);
        $epNumbers = ($epEnd && $epEnd > $epNum) ? range($epNum, $epEnd) : [$epNum];

        $processedEpisodes = [];

        foreach ($epNumbers as $currentEpNum) {
            $existingEp = Episode::where('series_id', $series->id)
                ->where('season_id', $season->id)
                ->where('episode_number', $currentEpNum)
                ->first();

            if ($existingEp) {
                if ($existingEp->file_path !== $fileNormPath) {
                    $existingEp->file_path = $fileNormPath;
                    $existingEp->file_size_bytes = $fileSizeBytes;
                    $existingEp->resolution = $resolution;
                    $existingEp->video_codec = $videoCodec;
                    $existingEp->audio_codec = $audioCodec;
                    if (! empty($probeData['duration']) && $probeData['duration'] > 0) {
                        $existingEp->duration_seconds = (int) round($probeData['duration']);
                        $existingEp->runtime_minutes = (int) round($probeData['duration'] / 60);
                    }
                    $existingEp->save();
                }
                $this->attachAllSubtitles($existingEp, $file);
                $processedEpisodes[] = $existingEp;

                continue;
            }

            // Resolve bilingual episode metadata from TMDb if series has tmdb_id
            $currEpTitle = ($currentEpNum === $epNum && ! empty($parsed['episode_title']))
                ? $parsed['episode_title']
                : "Episode {$currentEpNum}";
            $currEpTitleAr = null;
            $currEpOverview = "Episode {$currentEpNum}";
            $currEpOverviewAr = null;
            $currEpStillPath = null;
            $currEpRating = 0;
            $currEpAirDate = null;
            $currRuntimeMinutes = $runtimeMinutes;

            if ($series->tmdb_id) {
                $cacheKey = "{$series->tmdb_id}_s{$seasonNum}";
                if (! isset($this->tmdbSeasonCache[$cacheKey])) {
                    $this->tmdbSeasonCache[$cacheKey] = $this->metadata->getSeasonEpisodesBilingual($series->tmdb_id, $seasonNum);
                }
                $tmdbEp = $this->tmdbSeasonCache[$cacheKey][$currentEpNum] ?? null;
                if ($tmdbEp) {
                    $currEpTitle = $tmdbEp['title'];
                    $currEpTitleAr = $tmdbEp['title_ar'] ?? null;
                    $currEpOverview = $tmdbEp['overview'] ?: $currEpOverview;
                    $currEpOverviewAr = $tmdbEp['overview_ar'] ?? null;
                    $currEpStillPath = $tmdbEp['still_path'] ?? null;
                    $currEpRating = $tmdbEp['rating'] ?? 0;
                    $currEpAirDate = $tmdbEp['air_date'] ?? null;
                    if (! empty($tmdbEp['runtime_minutes']) && $currRuntimeMinutes === 45) {
                        $currRuntimeMinutes = $tmdbEp['runtime_minutes'];
                    }
                }
            }

            $newEp = retry(4, function () use ($series, $season, $currentEpNum, $currEpTitle, $currEpTitleAr, $currEpOverview, $currEpOverviewAr, $currEpStillPath, $currEpRating, $currEpAirDate, $fileNormPath, $fileSizeBytes, $resolution, $videoCodec, $audioCodec, $currRuntimeMinutes, $probeData) {
                return Episode::create([
                    'series_id' => $series->id,
                    'season_id' => $season->id,
                    'episode_number' => $currentEpNum,
                    'title' => $currEpTitle,
                    'title_ar' => $currEpTitleAr,
                    'overview' => $currEpOverview,
                    'overview_ar' => $currEpOverviewAr,
                    'still_path' => $currEpStillPath,
                    'rating' => $currEpRating,
                    'air_date' => $currEpAirDate,
                    'runtime_minutes' => $currRuntimeMinutes,
                    'duration_seconds' => ! empty($probeData['duration']) && $probeData['duration'] > 0 ? (int) round($probeData['duration']) : ($currRuntimeMinutes * 60),
                    'file_path' => $fileNormPath,
                    'file_size_bytes' => $fileSizeBytes,
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
                ]);
            }, 150);

            $this->attachAllSubtitles($newEp, $file);
            $processedEpisodes[] = $newEp;
        }

        return $processedEpisodes[0] ?? $existingEp;
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
        $normalizedVideoPath = str_replace('\\', '/', $file['path'] ?? '');

        // Clean up obsolete embedded tracks for this model that point to non-existent or obsolete video paths
        Subtitle::where('subtitlable_type', get_class($model))
            ->where('subtitlable_id', $model->id)
            ->where('is_embedded', true)
            ->where('file_path', 'NOT LIKE', "%:{$normalizedVideoPath}")
            ->delete();

        // Get existing normalized subtitle paths
        $existingSubs = Subtitle::where('subtitlable_type', get_class($model))
            ->where('subtitlable_id', $model->id)
            ->get();

        $existingPathsMap = [];
        foreach ($existingSubs as $es) {
            $existingPathsMap[str_replace('\\', '/', $es->file_path)] = $es;
        }

        $hasDefault = $existingSubs->contains('is_default', true);
        $hasArabic = $existingSubs->contains('language', 'ar');

        // 1. External Subtitle Files matching video
        if (! empty($file['subtitles'])) {
            foreach ($file['subtitles'] as $sub) {
                $subPath = str_replace('\\', '/', $sub['path'] ?? '');
                if (empty($subPath)) {
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

                $isDefault = false;
                if (! $hasDefault && ! $hasArabic && $lang === 'ar') {
                    $isDefault = true;
                    $hasDefault = true;
                    $hasArabic = true;
                } elseif (! $hasDefault && empty($existingPathsMap)) {
                    $isDefault = true;
                    $hasDefault = true;
                }

                Subtitle::updateOrCreate(
                    [
                        'subtitlable_type' => get_class($model),
                        'subtitlable_id' => $model->id,
                        'file_path' => $subPath,
                    ],
                    [
                        'language' => $lang,
                        'language_name' => $langName,
                        'format' => $sub['format'] ?? pathinfo($subPath, PATHINFO_EXTENSION),
                        'is_embedded' => false,
                        'is_default' => $isDefault,
                    ]
                );

                $existingPathsMap[$subPath] = true;
            }
        }

        // 2. Embedded subtitle tracks from container header
        try {
            $embedded = $this->embeddedSubDetector->detectEmbeddedSubtitles($normalizedVideoPath);
            foreach ($embedded as $sub) {
                $subKey = "embedded:{$sub['stream_index']}:{$normalizedVideoPath}";
                $lang = $sub['language'] ?? 'und';
                $langName = $sub['language_name'] ?? 'Embedded Track';

                $isDefault = false;
                if (! $hasDefault && ! $hasArabic && $lang === 'ar') {
                    $isDefault = true;
                    $hasDefault = true;
                    $hasArabic = true;
                }

                Subtitle::updateOrCreate(
                    [
                        'subtitlable_type' => get_class($model),
                        'subtitlable_id' => $model->id,
                        'file_path' => $subKey,
                    ],
                    [
                        'language' => $lang,
                        'language_name' => $langName,
                        'format' => $sub['codec'] ?? 'subrip',
                        'is_embedded' => true,
                        'is_default' => $isDefault,
                    ]
                );

                $existingPathsMap[$subKey] = true;
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
                        'release_year' => $series->release_year ?? ($meta['release_year'] ?? $meta['year'] ?? null),
                        'end_year' => $series->end_year ?? ($meta['end_year'] ?? null),
                        'status' => (! empty($meta['status']) && $series->status === 'Continuing') ? $meta['status'] : $series->status,
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
