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
use Illuminate\Support\Facades\DB;
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

    public function initScanForFolder(string $folderPath, string $typeHint = 'mixed'): array
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
                    'message' => "Folder scanner initialized for {$cleanPath}. Found {$totalFiles} files.",
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

        // Process files individually without holding long open transactions during network requests
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
        $jobData['scanned_items'] = array_slice($scannedItems, -40);
        $jobData['logs'] = array_slice($logs, -100);
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
            'latest_scanned' => array_slice($scannedItems, -6),
            'latest_logs' => array_slice($logs, -12),
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
                        'runtime_minutes' => $meta['runtime_minutes'] ?? ($meta['duration_minutes'] ?? $movie->runtime_minutes),
                    ]);

                    if (!empty($meta['genres'])) {
                        $genreIds = [];
                        foreach ($meta['genres'] as $gName) {
                            $g = \App\Models\Genre::firstOrCreate(
                                ['slug' => \Illuminate\Support\Str::slug($gName)],
                                ['name_en' => $gName, 'name_ar' => $gName]
                            );
                            $genreIds[] = $g->id;
                        }
                        $movie->genres()->sync($genreIds);
                    }
                }, 100);
                $enrichedCount++;
            } catch (\Throwable $e) {}
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

                    if (!empty($meta['genres'])) {
                        $genreIds = [];
                        foreach ($meta['genres'] as $gName) {
                            $g = \App\Models\Genre::firstOrCreate(
                                ['slug' => \Illuminate\Support\Str::slug($gName)],
                                ['name_en' => $gName, 'name_ar' => $gName]
                            );
                            $genreIds[] = $g->id;
                        }
                        $series->genres()->sync($genreIds);
                    }
                }, 100);
                $enrichedCount++;
            } catch (\Throwable $e) {}
        }

        return [
            'enriched_count' => $enrichedCount,
            'remaining_movies' => MediaItem::whereNull('overview_ar')->count(),
            'remaining_series' => Series::whereNull('overview_ar')->count(),
        ];
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

        // Hardware probe for exact resolution (including 576p, 540p, 480p, 360p, 240p) and duration
        $probed = $this->probeMediaSpecs($file['path']);
        $resolution = $probed['resolution'] ?? ($parsed['resolution'] ?? '1080p FHD');
        $videoCodec = $probed['video_codec'] ?? ($parsed['codec'] ?? 'H.264 / AVC');
        $audioCodec = $probed['audio_codec'] ?? ($parsed['audio'] ?? 'AAC');

        // 1. Perform Network & Artwork Search outside any database lock
        $meta = $this->metadata->aggregateMovieMetadata($cleanTitle, $year);
        $posterUrl = $meta['poster_path'] ?? ($file['local_poster'] ?? null);
        if (empty($posterUrl)) {
            $posterUrl = $this->webArtwork->searchAndDownloadArtwork($cleanTitle, $year, 'movie');
        }

        $backdropUrl = $meta['backdrop_path'] ?? ($file['local_backdrop'] ?? null);
        $runtimeMinutes = $probed['runtime_minutes'] ?? ($meta['runtime_minutes'] ?? ($meta['duration_minutes'] ?? 115));

        // 2. Perform SQLite write with automatic retry for busy lock resistance
        $movie = retry(4, function () use ($cleanTitle, $year, $meta, $file, $posterUrl, $backdropUrl, $resolution, $videoCodec, $audioCodec, $runtimeMinutes) {
            $m = MediaItem::create([
                'title' => $meta['title'] ?? $cleanTitle,
                'original_title' => $meta['original_title'] ?? $cleanTitle,
                'title_ar' => $meta['title_ar'] ?? null,
                'release_year' => $meta['release_year'] ?? ($meta['year'] ?? $year),
                'overview' => $meta['overview'] ?? "Enjoy watching {$cleanTitle}.",
                'overview_ar' => $meta['overview_ar'] ?? null,
                'rating' => $meta['rating'] ?? 7.5,
                'runtime_minutes' => $runtimeMinutes,
                'file_path' => $file['path'],
                'file_size_bytes' => $file['size_bytes'] ?? 0,
                'resolution' => $resolution,
                'video_codec' => $videoCodec,
                'audio_codec' => $audioCodec,
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

        if (!$series) {
            $series = retry(4, function () use ($showTitle, $year) {
                return Series::create([
                    'title' => $showTitle,
                    'original_title' => $showTitle,
                    'release_year' => $year,
                    'overview' => "Experience the complete series of {$showTitle}.",
                    'rating' => 8.0,
                    'status' => 'Continuing',
                ]);
            }, 150);
        }

        if (!$series->poster_path || !$series->overview || !$series->release_year || $series->overview === "Experience the complete series of {$showTitle}.") {
            try {
                $meta = $this->metadata->aggregateSeriesMetadata($showTitle, $series->release_year ?? $year);
                $posterUrl = $meta['poster_path'] ?? ($file['local_poster'] ?? null);
                if (empty($posterUrl)) {
                    $posterUrl = $this->webArtwork->searchAndDownloadArtwork($showTitle, $series->release_year ?? $year, 'series');
                }

                $backdropUrl = $meta['backdrop_path'] ?? ($file['local_backdrop'] ?? null);
                $seriesYear = $meta['release_year'] ?? ($meta['year'] ?? ($series->release_year ?? $year));

                retry(3, function () use ($series, $meta, $posterUrl, $backdropUrl, $seriesYear) {
                    $series->update([
                        'title_ar' => $meta['title_ar'] ?? $series->title_ar,
                        'overview' => $meta['overview'] ?? $series->overview,
                        'overview_ar' => $meta['overview_ar'] ?? $series->overview_ar,
                        'poster_path' => $posterUrl ?? $series->poster_path,
                        'backdrop_path' => $backdropUrl ?? $series->backdrop_path,
                        'rating' => $meta['rating'] ?? $series->rating,
                        'release_year' => $seriesYear ?? $series->release_year,
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
                }, 150);
            } catch (\Throwable $e) {}
        }

        // Hardware probe for exact episode specs (including 576p, 540p, 480p, 360p, 240p)
        $probed = $this->probeMediaSpecs($file['path']);
        $resolution = $probed['resolution'] ?? ($parsed['resolution'] ?? '1080p FHD');
        $videoCodec = $probed['video_codec'] ?? ($parsed['codec'] ?? 'H.264 / AVC');
        $audioCodec = $probed['audio_codec'] ?? ($parsed['audio'] ?? 'AAC');
        $runtimeMinutes = $probed['runtime_minutes'] ?? 45;

        $season = retry(4, function () use ($series, $seasonNum) {
            return Season::firstOrCreate(
                ['series_id' => $series->id, 'season_number' => $seasonNum],
                ['title' => "Season {$seasonNum}"]
            );
        }, 150);

        $episode = retry(4, function () use ($series, $season, $epNum, $epTitle, $seasonNum, $file, $resolution, $videoCodec, $audioCodec, $runtimeMinutes) {
            return Episode::updateOrCreate(
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
                    'runtime_minutes' => $runtimeMinutes,
                    'resolution' => $resolution,
                    'video_codec' => $videoCodec,
                    'audio_codec' => $audioCodec,
                ]
            );
        }, 150);

        $this->attachAllSubtitles($episode, $file);

        return $episode;
    }

    /**
     * Attach both external subtitle files and embedded container subtitle streams.
     */
        public function probeMediaSpecs(string $filePath): array
    {
        $specs = [
            'duration_seconds' => null,
            'runtime_minutes' => null,
            'resolution' => null,
            'video_codec' => null,
            'audio_codec' => null,
        ];

        $ffprobe = \App\Services\Media\FfmpegLocatorService::getFfprobePath();
        if (!$ffprobe || !\Illuminate\Support\Facades\File::exists($filePath)) {
            return $specs;
        }

        try {
            $escaped = escapeshellarg($filePath);
            $cmd = escapeshellarg($ffprobe) . " -v quiet -print_format json -show_format -show_streams {$escaped}";
            $output = @shell_exec($cmd);
            if (!$output) {
                return $specs;
            }

            $data = @json_decode($output, true);
            if (empty($data) || !is_array($data)) {
                return $specs;
            }

            // Duration
            if (!empty($data['format']['duration']) && is_numeric($data['format']['duration'])) {
                $dur = (float) $data['format']['duration'];
                $specs['duration_seconds'] = (int) round($dur);
                $specs['runtime_minutes'] = max(1, (int) round($dur / 60));
            }

            // Streams
            if (!empty($data['streams']) && is_array($data['streams'])) {
                foreach ($data['streams'] as $stream) {
                    $codecType = strtolower($stream['codec_type'] ?? '');
                    $codecName = strtolower($stream['codec_name'] ?? '');

                    if ($codecType === 'video' && empty($specs['resolution'])) {
                        $w = (int) ($stream['width'] ?? 0);
                        $h = (int) ($stream['height'] ?? 0);
                        if ($w > 0 && $h > 0) {
                            $specs['resolution'] = $this->nameParser->calculateResolutionFromDimensions($w, $h);
                        }

                        $specs['video_codec'] = match ($codecName) {
                            'hevc', 'h265' => 'HEVC / H.265',
                            'h264', 'avc' => 'H.264 / AVC',
                            'av01', 'av1' => 'AV1',
                            'vp9' => 'VP9',
                            'mpeg4', 'msmpeg4v3' => 'MPEG-4 / XviD',
                            default => strtoupper($codecName) ?: 'H.264 / AVC',
                        };
                    }

                    if ($codecType === 'audio' && empty($specs['audio_codec'])) {
                        $specs['audio_codec'] = match ($codecName) {
                            'eac3', 'ddp' => 'Dolby Digital Plus',
                            'ac3' => 'Dolby Digital',
                            'truehd' => 'Dolby TrueHD',
                            'dts' => 'DTS',
                            'flac' => 'FLAC',
                            'aac' => 'AAC',
                            'mp3' => 'MP3',
                            'opus' => 'Opus',
                            'vorbis' => 'Vorbis',
                            default => strtoupper($codecName) ?: 'AAC',
                        };
                    }
                }
            }
        } catch (\Throwable $e) {}

        return $specs;
    }

    protected function probeDurationSeconds(string $filePath): ?int
    {
        $specs = $this->probeMediaSpecs($filePath);
        return $specs['duration_seconds'];
    }

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
                    default => strtoupper($lang),
                };

                retry(3, function () use ($modelClass, $model, $sub, $lang, $langName) {
                    Subtitle::updateOrCreate(
                        [
                            'subtitlable_type' => $modelClass,
                            'subtitlable_id' => $model->id,
                            'file_path' => $sub['path'],
                        ],
                        [
                            'language' => $lang,
                            'language_name' => $langName . (!empty($sub['is_forced']) ? ' (Forced)' : ''),
                            'format' => strtolower(pathinfo($sub['path'], PATHINFO_EXTENSION)) ?: 'srt',
                            'is_embedded' => false,
                            'is_default' => $lang === 'ar' || $lang === 'en',
                        ]
                    );
                }, 150);
            }
        }

        // 2. Embedded Subtitle Tracks inside the video container
        if (!empty($filePath) && file_exists($filePath) && filesize($filePath) > 1024) {
            try {
                $embeddedTracks = $this->embeddedSubDetector->detectEmbeddedSubtitles($filePath);
                foreach ($embeddedTracks as $track) {
                    $streamIdx = $track['stream_index'] ?? 0;
                    $embeddedVirtualPath = "embedded:{$streamIdx}:{$filePath}";
                    $langCode = $track['language'] ?? 'und';
                    $langTitle = ($track['title'] ?: ($track['language_name'] ?? 'Track ' . ($streamIdx + 1))) . ' (Embedded)';

                    retry(3, function () use ($modelClass, $model, $embeddedVirtualPath, $langCode, $langTitle, $track) {
                        Subtitle::updateOrCreate(
                            [
                                'subtitlable_type' => $modelClass,
                                'subtitlable_id' => $model->id,
                                'file_path' => $embeddedVirtualPath,
                            ],
                            [
                                'language' => $langCode,
                                'language_name' => $langTitle,
                                'format' => $track['codec'] ?? 'srt',
                                'is_embedded' => true,
                                'is_default' => (bool) ($track['is_default'] ?? false),
                            ]
                        );
                    }, 150);
                }
            } catch (\Throwable $e) {}
        }
    }
}
