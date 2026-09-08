<?php

namespace App\Services\Organizer;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\Genre;
use App\Models\MediaItem;
use App\Models\Series;
use App\Models\Subtitle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Services\Organizer\ZeroKeyGenreClassifierService;

class PhysicalOrganizerService
{
    protected SceneNameParserService $parser;

    protected const CACHE_KEY = 'organizer_execution_state';
    protected const PLAN_CACHE_KEY = 'organizer_plan_state';

    public function __construct(SceneNameParserService $parser)
    {
        $this->parser = $parser;
    }

    public function generateDryRun(array $scannedFiles, string $targetRoot, ?string $moviePattern = null, ?string $seriesPattern = null): array
    {
        $moviePattern = $moviePattern ?: AppSetting::get('movie_naming_template', '{Type}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}');
        $seriesPattern = $seriesPattern ?: AppSetting::get('series_naming_template', '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{CleanResolution}].{ext}');

        $targetRoot = rtrim(str_replace('\\', '/', $targetRoot), '/');
        $plan = [];

        foreach ($scannedFiles as $file) {
            $plan[] = $this->generatePlanItem($file, $targetRoot, $moviePattern, $seriesPattern);
        }

        return $plan;
    }

    /**
     * Generate plan item for a single media file.
     */
    public function generatePlanItem(array $file, string $targetRoot, ?string $moviePattern = null, ?string $seriesPattern = null): array
    {
        $moviePattern = $moviePattern ?: AppSetting::get('movie_naming_template', '{Type}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}');
        $seriesPattern = $seriesPattern ?: AppSetting::get('series_naming_template', '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{CleanResolution}].{ext}');
        $targetRoot = rtrim(str_replace('\\', '/', $targetRoot), '/');

        $filePath = $file['path'] ?? ($file['filename'] ?? '');
        $parsed = $file['parsed'] ?? $this->parser->parse($filePath);
        $isSeries = ($parsed['type'] ?? 'movie') === 'series';

        $pattern = $isSeries ? $seriesPattern : $moviePattern;
        $typeDir = $isSeries ? 'TV Shows' : 'Movies';

        $cleanTitle = $this->sanitizePathSegment($parsed['clean_title'] ?? ($parsed['title'] ?? ($parsed['series_title'] ?? 'Unknown')));
        $year = ! empty($parsed['year']) ? (string) $parsed['year'] : '';
        $seasonNum = isset($parsed['season']) ? (int) $parsed['season'] : 1;
        $episodeNum = isset($parsed['episode']) ? (int) $parsed['episode'] : 1;
        $epTitle = ! empty($parsed['episode_title']) ? $this->sanitizePathSegment($parsed['episode_title']) : '';
        if (preg_match('/^(episode|ep|part)\s*\d+$/i', $epTitle)) {
            $epTitle = '';
        }

        // If resolution is missing from parser but file exists on disk, probe via FFprobe
        $resTag = $parsed['resolution'] ?? null;
        if (empty($resTag) && file_exists($filePath)) {
            $probed = app(FilesystemScannerService::class)->probeResolution($filePath);
            if ($probed) {
                $resTag = $probed;
                $parsed['resolution'] = $probed;
            }
        }
        $resTag = $resTag ?: '1080p FHD';

        $firstChar = mb_strtoupper(mb_substr($cleanTitle, 0, 1));
        $firstLetter = preg_match('/^[A-Z0-9]$/i', $firstChar) ? $firstChar : '#';

        // Resolve genres for {Genre} and {Genres} tokens
        $genresList = $file['genres'] ?? [];
        if (empty($genresList)) {
            if ($isSeries) {
                $dbSeries = Series::where('title', 'like', "%{$cleanTitle}%")->with('genres')->first();
                if ($dbSeries && $dbSeries->genres->isNotEmpty()) {
                    $genresList = $dbSeries->genres->pluck('name_en')->toArray();
                }
            } else {
                $dbMovie = MediaItem::where('title', 'like', "%{$cleanTitle}%")->with('genres')->first();
                if ($dbMovie && $dbMovie->genres->isNotEmpty()) {
                    $genresList = $dbMovie->genres->pluck('name_en')->toArray();
                }
            }
        }

        if (empty($genresList)) {
            $classifier = app(ZeroKeyGenreClassifierService::class);
            $coll = $parsed['collection_name'] ?? ($file['collection_name'] ?? null);
            $resolved = $classifier->resolveGenres($cleanTitle, $coll);
            $primaryGenre = $this->sanitizePathSegment($resolved['primary']);
            $joinedGenres = $this->sanitizePathSegment($resolved['joined']);
        } else {
            $primaryGenre = ! empty($genresList[0]) ? $this->sanitizePathSegment($genresList[0]) : 'Action & Adventure';
            $joinedGenres = ! empty($genresList) ? $this->sanitizePathSegment(implode(' & ', array_slice($genresList, 0, 2))) : $primaryGenre;
        }

        $collName = $parsed['collection_name'] ?? ($file['collection_name'] ?? '');
        $cleanRes = $this->cleanResolutionTag($resTag);

        $tokens = [
            '{Type}' => $typeDir,
            '{Title}' => $cleanTitle,
            '{Year}' => $year,
            '{Collection}' => $collName ? $this->sanitizePathSegment($collName) : '',
            '{Genre}' => $primaryGenre,
            '{Genres}' => $joinedGenres,
            '{Resolution}' => $this->sanitizePathSegment($resTag),
            '{CleanResolution}' => $cleanRes,
            '{ResolutionClean}' => $cleanRes,
            '{Codec}' => $this->sanitizePathSegment($parsed['codec'] ?: 'x264'),
            '{Source}' => $this->sanitizePathSegment($parsed['source'] ?? ''),
            '{Edition}' => $this->sanitizePathSegment($parsed['edition'] ?? ''),
            '{Group}' => $this->sanitizePathSegment($parsed['group'] ?? 'MEDIA'),
            '{FirstLetter}' => $firstLetter,
            '{Season:02}' => sprintf('%02d', $seasonNum),
            '{Episode:02}' => sprintf('%02d', $episodeNum),
            '{EpisodeTitle}' => $epTitle,
            '{ext}' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION) ?: ($parsed['extension'] ?? 'mkv')),
        ];

        $relPath = $pattern;
        if (empty($epTitle) || ! empty($file['omit_episode_title'])) {
            $relPath = str_replace(' - {EpisodeTitle}', '', $relPath);
            $relPath = str_replace('{EpisodeTitle}', '', $relPath);
        }
        // If movie has no collection, cleanly unwrap collection directory segment
        if (empty($collName)) {
            $relPath = str_replace(['/Collections/{Collection}', 'Collections/{Collection}/', 'Collections/{Collection}', '/{Collection}', '{Collection}/', '{Collection}'], '', $relPath);
        }
        $relPath = str_replace(array_keys($tokens), array_values($tokens), $relPath);

        // Clean double slashes, unknown years, or empty brackets/dangling delimiters
        $relPath = preg_replace('#/+#', '/', $relPath);
        $relPath = preg_replace('/\s+-\s*\[/', ' [', $relPath);
        $relPath = preg_replace('/\s+-\s*\./', '.', $relPath);
        $relPath = str_replace(['(Unknown Year)', '[Unknown Year]', 'Unknown Year', '()', '[]', '( )', '[ ]', ' - .', ' .'], ['', '', '', '', '', '', '', '.', '.'], $relPath);
        $relPath = preg_replace('/\s+/', ' ', $relPath);
        $relPath = trim($relPath, '/');

        // Sanitize each directory level in relPath
        $segments = explode('/', $relPath);
        $cleanSegments = [];
        foreach ($segments as $idx => $segment) {
            if ($idx === count($segments) - 1) {
                $ext = pathinfo($segment, PATHINFO_EXTENSION);
                $base = pathinfo($segment, PATHINFO_FILENAME);
                $cleanSegments[] = $this->sanitizePathSegment($base).($ext ? ".{$ext}" : '');
            } else {
                $cleanSegments[] = $this->sanitizePathSegment($segment);
            }
        }
        $cleanRelPath = implode('/', $cleanSegments);

        $destination = "{$targetRoot}/{$cleanRelPath}";
        $source = str_replace('\\', '/', $filePath);

        $exists = File::exists($destination);
        $isIdentical = strtolower(trim($source)) === strtolower(trim($destination));

        $status = 'ready';
        if ($isIdentical) {
            $status = 'identical';
        } elseif ($exists) {
            $status = 'collision_exists';
        }

        $item = [
            'id' => uniqid('plan_'),
            'source_path' => $source,
            'destination_path' => $destination,
            'filename' => $file['filename'] ?? basename($filePath),
            'clean_title' => $cleanTitle,
            'type' => $parsed['type'] ?? ($isSeries ? 'series' : 'movie'),
            'year' => $parsed['year'] ?? null,
            'season' => $isSeries ? $seasonNum : null,
            'episode' => $isSeries ? $episodeNum : null,
            'episode_title' => $epTitle,
            'resolution' => $parsed['resolution'] ?? '1080p',
            'size_bytes' => $file['size_bytes'] ?? 0,
            'size_formatted' => $file['size_formatted'] ?? '',
            'status' => $status,
            'selected' => $status === 'ready',
            'subtitles' => [],
        ];

        // Subtitle mapping with language preserving and standardization (.ar.srt / .en.srt)
        if (! empty($file['subtitles'])) {
            $destDir = pathinfo($destination, PATHINFO_DIRNAME);
            $destBase = pathinfo($destination, PATHINFO_FILENAME);

            foreach ($file['subtitles'] as $sub) {
                $subPath = $sub['path'] ?? '';
                $subExt = strtolower($sub['extension'] ?? (pathinfo($subPath, PATHINFO_EXTENSION) ?: 'srt'));
                $lang = $sub['language'] ?? 'und';

                // Robust language detection from filename or tags
                $subFilenameLower = strtolower(basename($subPath));
                if ($lang === 'und' || empty($lang)) {
                    if (preg_match('/(\.ar|\barabic\b|\bara\b|_ar\.)/i', $subFilenameLower)) {
                        $lang = 'ar';
                    } elseif (preg_match('/(\.en|\benglish\b|\beng\b|_en\.)/i', $subFilenameLower)) {
                        $lang = 'en';
                    }
                }

                $langSuffix = in_array($lang, ['ar', 'en', 'fr', 'es', 'de']) ? ".{$lang}" : '';

                $subDest = "{$destDir}/{$destBase}{$langSuffix}.{$subExt}";
                $subSource = str_replace('\\', '/', $subPath);

                $item['subtitles'][] = [
                    'source' => $subSource,
                    'destination' => $subDest,
                    'language' => $lang,
                    'exists' => File::exists($subDest),
                ];
            }
        }

        return $item;
    }

    /**
     * Initialize Stateful Batch Plan Generation (Zero 30s-timeout risk)
     */
        /**
     * Get real-time status of the background plan generation job.
     */
    public function getPlanJobStatus(): array
    {
        return Cache::get(self::PLAN_CACHE_KEY, [
            'status' => 'idle',
            'progress_percent' => 0,
            'total_files' => 0,
            'processed_count' => 0,
            'current_file' => null,
            'current_action' => 'Idle',
            'logs' => [],
            'plan_items' => [],
            'started_at' => null,
            'updated_at' => null,
        ]);
    }

    /**
     * Start background plan generation job (Mirroring Virtual Library Scanner).
     */
    public function startPlanJob(
        string $sourcePath,
        string $targetRoot,
        ?string $moviePattern = null,
        ?string $seriesPattern = null,
        string $sourceMode = 'folder',
        bool $recursive = true,
        array $options = []
    ): array {
        $files = $options['files'] ?? null;
        if ($files === null) {
            if ($sourceMode === 'virtual') {
                $movies = MediaItem::with('subtitles')->get()->map(function ($m) {
                    return [
                        'path' => $m->file_path,
                        'filename' => basename($m->file_path),
                        'size_bytes' => $m->file_size_bytes,
                        'size_formatted' => $m->file_size_bytes ? round($m->file_size_bytes / (1024 * 1024 * 1024), 2).' GB' : '1.4 GB',
                        'parsed' => [
                            'type' => 'movie',
                            'title' => $m->title,
                            'clean_title' => $m->title,
                            'year' => $m->release_year,
                            'resolution' => $m->resolution ?? '1080p',
                            'codec' => $m->video_codec ?? 'HEVC',
                        ],
                        'subtitles' => $m->subtitles->map(fn ($s) => ['path' => $s->file_path, 'language' => $s->language])->toArray(),
                    ];
                });

                $episodes = Episode::with(['season.series', 'subtitles'])->get()->map(function ($ep) {
                    return [
                        'path' => $ep->file_path,
                        'filename' => basename($ep->file_path),
                        'size_bytes' => $ep->file_size_bytes,
                        'size_formatted' => $ep->file_size_bytes ? round($ep->file_size_bytes / (1024 * 1024), 1).' MB' : '450 MB',
                        'parsed' => [
                            'type' => 'series',
                            'series_title' => $ep->season?->series?->title ?? 'TV Show',
                            'season' => $ep->season?->season_number ?? 1,
                            'episode' => $ep->episode_number,
                            'resolution' => $ep->resolution ?? '1080p',
                            'codec' => $ep->video_codec ?? 'HEVC',
                        ],
                        'subtitles' => $ep->subtitles->map(fn ($s) => ['path' => $s->file_path, 'language' => $s->language])->toArray(),
                    ];
                });

                $files = $movies->concat($episodes)->values()->filter(fn ($f) => ! empty($f['path']) && file_exists($f['path']))->values()->toArray();
            } else {
                $scanner = app(FilesystemScannerService::class);
                $files = $scanner->scanDirectory($sourcePath, $recursive);
            }
        }

        $total = count($files);

        if ($total === 0) {
            $state = [
                'status' => 'idle',
                'progress_percent' => 0,
                'total_files' => 0,
                'processed_count' => 0,
                'current_file' => null,
                'current_action' => 'No media files found in specified source.',
                'logs' => [
                    [
                        'time' => now()->format('H:i:s'),
                        'level' => 'error',
                        'message' => "No media files found in {$sourcePath}.",
                    ],
                ],
                'plan_items' => [],
                'started_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
            Cache::put(self::PLAN_CACHE_KEY, $state, now()->addHours(6));

            return [
                'success' => false,
                'message' => 'No media files found in the specified source.',
                'status' => $state,
            ];
        }

        $sourceLabel = $sourceMode === 'virtual' ? 'Virtual Library' : basename($sourcePath);

        $state = [
            'status' => 'generating',
            'progress_percent' => 0,
            'total_files' => $total,
            'processed_count' => 0,
            'current_file' => null,
            'current_action' => "Found {$total} media files. Starting organization analysis...",
            'source_path' => $sourcePath,
            'target_root' => $targetRoot,
            'movie_pattern' => $moviePattern,
            'series_pattern' => $seriesPattern,
            'pending_queue' => $files,
            'plan_items' => [],
            'logs' => [
                [
                    'time' => now()->format('H:i:s'),
                    'level' => 'info',
                    'message' => "Discovered {$total} media files in [{$sourceLabel}]. Background organization job started.",
                ],
            ],
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        Cache::put(self::PLAN_CACHE_KEY, $state, now()->addHours(6));

        return [
            'success' => true,
            'is_active' => true,
            'status' => $state,
            'total_files' => $total,
        ];
    }

    /**
     * Backward-compatibility alias for initPlanGeneration.
     */
    public function initPlanGeneration(string $sourcePath, string $targetRoot, ?string $moviePattern = null, ?string $seriesPattern = null, bool $recursive = true, array $options = []): array
    {
        $res = $this->startPlanJob($sourcePath, $targetRoot, $moviePattern, $seriesPattern, 'folder', $recursive, $options);
        $res['is_active'] = ($res['status']['status'] ?? '') === 'generating';
        $res['total_files'] = $res['total_files'] ?? ($res['status']['total_files'] ?? 0);
        return $res;
    }

    /**
     * Process next batch of the background plan job (Matches Virtual Scanner Worker).
     */
    public function processPlanJobBatch(int $batchSize = 15): array
    {
        $state = Cache::get(self::PLAN_CACHE_KEY);

        if (! $state) {
            return [
                'success' => true,
                'has_more' => false,
                'status' => $this->getPlanJobStatus(),
                'plan' => [],
                'total_files' => 0,
                'processed_count' => 0,
                'progress_percent' => 0,
                'is_completed' => false,
            ];
        }

        if (($state['status'] ?? '') === 'paused') {
            return [
                'success' => true,
                'has_more' => true,
                'status' => $state,
                'plan' => $state['plan_items'] ?? [],
                'total_files' => (int) ($state['total_files'] ?? 0),
                'processed_count' => (int) ($state['processed_count'] ?? 0),
                'progress_percent' => $state['progress_percent'] ?? 0,
                'is_completed' => false,
            ];
        }

        if (($state['status'] ?? '') === 'cancelled') {
            return [
                'success' => true,
                'has_more' => false,
                'status' => $state,
                'plan' => $state['plan_items'] ?? [],
                'total_files' => (int) ($state['total_files'] ?? 0),
                'processed_count' => (int) ($state['processed_count'] ?? 0),
                'progress_percent' => $state['progress_percent'] ?? 0,
                'is_completed' => false,
            ];
        }

        if (($state['status'] ?? '') !== 'generating' || empty($state['pending_queue'])) {
            if ($state && ($state['status'] ?? '') === 'generating') {
                $state['status'] = 'completed';
                $state['progress_percent'] = 100;
                $state['current_action'] = 'Organization plan completed successfully.';
                Cache::put(self::PLAN_CACHE_KEY, $state, now()->addHours(6));
            }

            return [
                'success' => true,
                'has_more' => false,
                'status' => $state ?: $this->getPlanJobStatus(),
            ];
        }

        $queue = $state['pending_queue'];
        $batch = array_splice($queue, 0, $batchSize);
        $state['pending_queue'] = $queue;

        $targetRoot = $state['target_root'];
        $moviePattern = $state['movie_pattern'];
        $seriesPattern = $state['series_pattern'];

        foreach ($batch as $file) {
            $filename = $file['filename'] ?? basename($file['path'] ?? '');
            $state['current_file'] = $filename;
            $state['current_action'] = "Analyzing {$filename}...";

            try {
                $item = $this->generatePlanItem($file, $targetRoot, $moviePattern, $seriesPattern);
                $state['plan_items'][] = $item;
                $state['processed_count']++;

                $destRel = basename(dirname($item['destination_path'])) . '/' . basename($item['destination_path']);
                $state['logs'][] = [
                    'time' => now()->format('H:i:s'),
                    'level' => 'success',
                    'message' => "Mapped: {$item['clean_title']} ({$item['year']}) [{$item['resolution']}] -> {$destRel}",
                ];
            } catch (\Throwable $e) {
                $state['processed_count']++;
                $state['logs'][] = [
                    'time' => now()->format('H:i:s'),
                    'level' => 'error',
                    'message' => "Error analyzing {$filename}: " . $e->getMessage(),
                ];
            }
        }

        $total = max(1, (int) $state['total_files']);
        $processed = (int) $state['processed_count'];
        $state['progress_percent'] = min(100, (int) round(($processed / $total) * 100));

        if (count($state['logs']) > 150) {
            $state['logs'] = array_slice($state['logs'], -150);
        }

        $isDone = empty($state['pending_queue']) || $processed >= $total;
        if ($isDone) {
            $state['status'] = 'completed';
            $state['progress_percent'] = 100;
            $state['current_action'] = "Plan generated successfully for {$total} media files.";
            $state['logs'][] = [
                'time' => now()->format('H:i:s'),
                'level' => 'info',
                'message' => "Finished generating organization plan for all {$total} files.",
            ];
        }

        $state['updated_at'] = now()->toDateTimeString();

        $latestInCache = Cache::get(self::PLAN_CACHE_KEY);
        if ($latestInCache && ($latestInCache['status'] === 'paused')) {
            $state['status'] = 'paused';
        } elseif ($latestInCache && ($latestInCache['status'] === 'cancelled')) {
            $state['status'] = 'cancelled';
            $state['pending_queue'] = [];
            $isDone = true;
        }

        Cache::put(self::PLAN_CACHE_KEY, $state, now()->addHours(6));

        return [
            'success' => true,
            'has_more' => ! $isDone,
            'status' => $state,
            'plan' => $state['plan_items'] ?? [],
            'total_files' => $total,
            'processed_count' => $processed,
            'progress_percent' => $state['progress_percent'],
            'is_completed' => $isDone,
        ];
    }

    /**
     * Backward-compatibility alias for processPlanBatch.
     */
    public function processPlanBatch(int $batchSize = 25): array
    {
        return $this->processPlanJobBatch($batchSize);
    }

    public function getPlanGenerationStatus(): array
    {
        return $this->getPlanJobStatus();
    }

    public function cancelPlanGeneration(): array
    {
        return $this->cancelPlanJob();
    }

    public function pausePlanJob(): array
    {
        $state = Cache::get(self::PLAN_CACHE_KEY);
        if ($state) {
            $state['status'] = 'paused';
            $state['current_action'] = 'Job paused by user.';
            $state['logs'][] = [
                'time' => now()->format('H:i:s'),
                'level' => 'info',
                'message' => 'Organization analysis paused.',
            ];
            Cache::put(self::PLAN_CACHE_KEY, $state, now()->addHours(6));
        }

        return ['success' => true, 'status' => $state ?: $this->getPlanJobStatus()];
    }

    public function resumePlanJob(): array
    {
        $state = Cache::get(self::PLAN_CACHE_KEY);
        if ($state) {
            $state['status'] = 'generating';
            $state['current_action'] = 'Resuming organization analysis...';
            $state['logs'][] = [
                'time' => now()->format('H:i:s'),
                'level' => 'info',
                'message' => 'Organization analysis resumed.',
            ];
            Cache::put(self::PLAN_CACHE_KEY, $state, now()->addHours(6));
        }

        return ['success' => true, 'status' => $state ?: $this->getPlanJobStatus()];
    }

    public function cancelPlanJob(): array
    {
        $state = Cache::get(self::PLAN_CACHE_KEY);
        if ($state) {
            $state['status'] = 'cancelled';
            $state['current_action'] = 'Job cancelled by user.';
            $state['logs'][] = [
                'time' => now()->format('H:i:s'),
                'level' => 'info',
                'message' => 'Organization analysis cancelled.',
            ];
            Cache::put(self::PLAN_CACHE_KEY, $state, now()->addHours(6));
        }

        return ['success' => true, 'status' => $state ?: $this->getPlanJobStatus(), 'cancelled' => true];
    }

    public function cleanResolutionTag(?string $raw): string
    {
        if (empty($raw)) {
            return '1080p';
        }
        $r = strtolower(trim($raw));
        if (str_contains($r, '4k') || str_contains($r, '2160')) return '4K';
        if (str_contains($r, '1440') || str_contains($r, '2k')) return '1440p';
        if (str_contains($r, '1080')) return '1080p';
        if (str_contains($r, '720')) return '720p';
        if (str_contains($r, '576')) return '576p';
        if (str_contains($r, '540')) return '540p';
        if (str_contains($r, '480')) return '480p';
        if (str_contains($r, '360')) return '360p';
        if (str_contains($r, '240')) return '240p';

        return $raw;
    }

    public function sanitizePathSegment(string $name): string
    {
        // Replace colons with hyphen separator
        $name = str_replace(':', ' - ', $name);

        // Strip illegal filesystem characters
        $name = str_replace(['<', '>', '"', '/', '\\', '|', '?', '*'], '', $name);

        // Collapse multiple spaces
        $name = preg_replace('/\s+/', ' ', $name);

        // Windows prohibits trailing dots and spaces in directory and file names
        $name = trim($name, " .\t\n\r\0\x0B");

        return $name ?: 'Unknown';
    }

    /**
     * Initialize Batch Execution (Stateful Queue like MediaScanner)
     */
    public function initExecution(array $plan, string $mode = 'move', bool $cleanupEmptyFolders = true): array
    {
        $selected = array_values(array_filter($plan, function ($item) {
            return ! empty($item['selected']) && ($item['status'] ?? 'ready') !== 'identical';
        }));

        $totalBytes = array_sum(array_column($selected, 'size_bytes'));

        $state = [
            'is_active' => count($selected) > 0,
            'is_completed' => count($selected) === 0,
            'is_cancelled' => false,
            'mode' => $mode,
            'cleanup_empty_folders' => $cleanupEmptyFolders,
            'total_items' => count($selected),
            'total_bytes' => $totalBytes,
            'total_bytes_formatted' => $this->formatBytes($totalBytes),
            'processed_count' => 0,
            'successful_count' => 0,
            'failed_count' => 0,
            'cleaned_folders_count' => 0,
            'current_file' => '',
            'current_destination' => '',
            'current_action' => count($selected) > 0 ? "Preparing {$mode} queue..." : 'No items selected.',
            'progress_percent' => 0,
            'started_at' => date('Y-m-d H:i:s'),
            'logs' => [
                [
                    'time' => date('H:i:s'),
                    'type' => 'info',
                    'message' => "Initialized {$mode} queue with ".count($selected).' files ('.$this->formatBytes($totalBytes).')'.($cleanupEmptyFolders ? ' [Auto-Cleanup Enabled]' : ''),
                ],
            ],
            'queue' => $selected,
            'source_dirs_to_clean' => [],
            'errors' => [],
            'completed_items' => [],
        ];

        Cache::put(self::CACHE_KEY, $state, now()->addHours(2));

        return $state;
    }

    /**
     * Process next batch of items (Non-blocking SSE / Ajax loop)
     */
    public function processNextBatch(int $batchSize = 2): array
    {
        $state = Cache::get(self::CACHE_KEY);

        if (! $state || ! empty($state['is_cancelled'])) {
            return [
                'has_more' => false,
                'status' => $state ?: $this->getExecutionStatus(),
            ];
        }

        if (! empty($state['is_paused'])) {
            return [
                'has_more' => true,
                'status' => $state,
            ];
        }

        if (empty($state['is_active']) || empty($state['queue'])) {
            $state['is_active'] = false;
            $state['is_completed'] = true;
            $state['progress_percent'] = 100;
            $state['current_action'] = 'All operations completed!';
            Cache::put(self::CACHE_KEY, $state, now()->addHours(2));

            return [
                'has_more' => false,
                'status' => $state,
            ];
        }

        $queue = $state['queue'];
        $batch = array_splice($queue, 0, $batchSize);
        $mode = $state['mode'] ?? 'move';

        foreach ($batch as $item) {
            $source = $item['source_path'];
            $dest = $item['destination_path'];
            $filename = $item['filename'] ?? basename($source);

            $state['current_file'] = $filename;
            $state['current_destination'] = $dest;
            $state['current_action'] = ucfirst($mode)."ing {$filename}...";

            try {
                if (! File::exists($source)) {
                    $state['failed_count']++;
                    $errMsg = "Source not found: {$filename}";
                    $state['errors'][] = $errMsg;
                    $state['logs'][] = [
                        'time' => date('H:i:s'),
                        'type' => 'error',
                        'message' => "[ERROR] {$errMsg}",
                    ];
                    $state['processed_count']++;

                    continue;
                }

                $destDir = pathinfo($dest, PATHINFO_DIRNAME);
                if (! File::isDirectory($destDir)) {
                    File::makeDirectory($destDir, 0755, true, true);
                }

                $sourceDir = pathinfo($source, PATHINFO_DIRNAME);
                if (! in_array($sourceDir, $state['source_dirs_to_clean'])) {
                    $state['source_dirs_to_clean'][] = $sourceDir;
                }

                if ($mode === 'move') {
                    File::move($source, $dest);
                    $this->updateDatabasePath($source, $dest);
                } else {
                    File::copy($source, $dest);
                }

                // Handle Subtitles
                $subsCount = 0;
                if (! empty($item['subtitles'])) {
                    foreach ($item['subtitles'] as $sub) {
                        $subSource = $sub['source'];
                        $subDest = $sub['destination'];

                        if (File::exists($subSource)) {
                            $subDestDir = pathinfo($subDest, PATHINFO_DIRNAME);
                            if (! File::isDirectory($subDestDir)) {
                                File::makeDirectory($subDestDir, 0755, true, true);
                            }

                            $subSourceDir = pathinfo($subSource, PATHINFO_DIRNAME);
                            if (! in_array($subSourceDir, $state['source_dirs_to_clean'])) {
                                $state['source_dirs_to_clean'][] = $subSourceDir;
                            }

                            if ($mode === 'move') {
                                File::move($subSource, $subDest);
                                Subtitle::where('file_path', $subSource)->update(['file_path' => $subDest]);
                            } else {
                                File::copy($subSource, $subDest);
                            }
                            $subsCount++;
                        }
                    }
                }

                $state['successful_count']++;
                $state['logs'][] = [
                    'time' => date('H:i:s'),
                    'type' => 'success',
                    'message' => '['.strtoupper($mode)."] {$filename} -> ".basename($dest).($subsCount > 0 ? " (+{$subsCount} subs)" : ''),
                ];
                $state['completed_items'][] = [
                    'source' => $source,
                    'destination' => $dest,
                    'title' => $item['clean_title'],
                    'size_formatted' => $item['size_formatted'],
                ];
            } catch (\Throwable $e) {
                $state['failed_count']++;
                $err = "Failed {$filename}: ".$e->getMessage();
                $state['errors'][] = $err;
                $state['logs'][] = [
                    'time' => date('H:i:s'),
                    'type' => 'error',
                    'message' => "[ERROR] {$err}",
                ];
                Log::error('PhysicalOrganizerService error: '.$e->getMessage());
            }

            $state['processed_count']++;
        }

        $state['queue'] = $queue;
        $total = max(1, $state['total_items']);
        $state['progress_percent'] = min(100, (int) round(($state['processed_count'] / $total) * 100));

        // When queue is empty, perform automatic cleanup of empty folders left behind!
        if (empty($queue)) {
            if ($mode === 'move' && ! empty($state['cleanup_empty_folders'])) {
                $cleanedCount = $this->cleanEmptyDirectories($state['source_dirs_to_clean'], $state['logs']);
                $state['cleaned_folders_count'] = $cleanedCount;
                if ($cleanedCount > 0) {
                    $state['logs'][] = [
                        'time' => date('H:i:s'),
                        'type' => 'success',
                        'message' => "🧹 Cleaned up {$cleanedCount} empty leftover source folder".($cleanedCount > 1 ? 's' : '').'.',
                    ];
                }
            }

            $state['is_active'] = false;
            $state['is_completed'] = true;
            $state['progress_percent'] = 100;
            $state['current_action'] = "Execution complete! Processed {$state['processed_count']} files.";
            $state['logs'][] = [
                'time' => date('H:i:s'),
                'type' => 'success',
                'message' => "🎉 All files organized successfully ({$state['successful_count']} success, {$state['failed_count']} failed)",
            ];
        }

        // Keep at most 100 recent logs
        if (count($state['logs']) > 100) {
            $state['logs'] = array_slice($state['logs'], -100);
        }

        // Safeguard against in-flight pause or cancel
        $latest = Cache::get(self::CACHE_KEY);
        if ($latest && ! empty($latest['is_paused'])) {
            $state['is_paused'] = true;
        }
        if ($latest && ! empty($latest['is_cancelled'])) {
            $state['is_cancelled'] = true;
            $state['is_active'] = false;
            $state['queue'] = [];
        }

        Cache::put(self::CACHE_KEY, $state, now()->addHours(2));

        return [
            'has_more' => ! empty($queue) && empty($state['is_cancelled']),
            'status' => $state,
        ];
    }

    /**
     * Clean up empty source folders left behind after moving media files.
     * Recursively checks parent directories and removes them if empty or only containing junk.
     */
    protected function cleanEmptyDirectories(array $sourceDirs, array &$logs): int
    {
        $videoExtensions = ['mp4', 'mkv', 'avi', 'mov', 'm4v', 'webm', 'ts', 'wmv', 'flv', 'iso'];
        $disposableJunk = [
            'thumbs.db', 'desktop.ini', '.ds_store', 'ehthumbs.db',
            'www.yts.mx.txt', 'www.yts.lt.txt', 'www.yts.bz.txt', 'www.yify-torrents.com.txt',
            'yify.txt', 'torrent-downloaded-from.txt',
        ];
        $disposableExtensions = ['txt', 'nfo', 'url', 'website', 'lnk', 'ini', 'db', 'torrent', 'sample', 'log'];

        $cleanedCount = 0;
        $allDirs = [];

        // 1. Gather all subdirectories recursively inside every source directory
        foreach ($sourceDirs as $sourceDir) {
            $sourceDir = rtrim(str_replace('\\', '/', $sourceDir), '/');
            if (! File::isDirectory($sourceDir)) {
                continue;
            }

            if (preg_match('#^[A-Za-z]:/?$#', $sourceDir) || in_array(strtolower($sourceDir), ['/var', '/usr', '/home', '/etc', 'c:/', 'd:/', 'c:', 'd:'])) {
                continue;
            }

            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($iterator as $item) {
                    if ($item->isDir()) {
                        $allDirs[] = str_replace('\\', '/', $item->getPathname());
                    }
                }
            } catch (\Throwable $e) {
            }

            $allDirs[] = $sourceDir;
        }

        $allDirs = array_unique($allDirs);
        // Sort by length descending so child subfolders are evaluated and deleted before parents
        usort($allDirs, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($allDirs as $dir) {
            if (! File::isDirectory($dir)) {
                continue;
            }

            if (preg_match('#^[A-Za-z]:/?$#', $dir) || in_array(strtolower($dir), ['/var', '/usr', '/home', '/etc', 'c:/', 'd:/', 'c:', 'd:'])) {
                continue;
            }

            $items = @scandir($dir);
            if ($items === false) {
                continue;
            }

            $entries = array_diff($items, ['.', '..']);
            $hasEssentialFiles = false;

            foreach ($entries as $entry) {
                $fullPath = "{$dir}/{$entry}";
                if (File::isDirectory($fullPath)) {
                    $hasEssentialFiles = true;
                    break;
                }

                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                $nameLower = strtolower($entry);

                if (in_array($ext, $videoExtensions) || in_array($ext, ['srt', 'vtt', 'ass', 'sub'])) {
                    $hasEssentialFiles = true;
                    break;
                }

                $isJunk = in_array($nameLower, $disposableJunk)
                    || in_array($ext, $disposableExtensions)
                    || (in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) && (str_contains($nameLower, 'yts') || str_contains($nameLower, 'poster') || str_contains($nameLower, 'cover') || str_contains($nameLower, 'banner') || @filesize($fullPath) < 500000));

                if (! $isJunk) {
                    $hasEssentialFiles = true;
                    break;
                }
            }

            if (! $hasEssentialFiles) {
                foreach ($entries as $entry) {
                    $fullPath = "{$dir}/{$entry}";
                    if (File::isFile($fullPath)) {
                        @unlink($fullPath);
                    }
                }

                if (@rmdir($dir) || ! File::isDirectory($dir)) {
                    $cleanedCount++;
                    $logs[] = [
                        'time' => date('H:i:s'),
                        'type' => 'info',
                        'message' => '[CLEANUP] 🧹 Removed empty leftover folder: '.basename($dir),
                    ];
                }
            }
        }

        return $cleanedCount;
    }

    /**
     * Get Current Status
     */
    public function getExecutionStatus(): array
    {
        $state = Cache::get(self::CACHE_KEY);

        if (! $state) {
            return [
                'is_active' => false,
                'is_completed' => false,
                'is_cancelled' => false,
                'total_items' => 0,
                'processed_count' => 0,
                'successful_count' => 0,
                'failed_count' => 0,
                'cleaned_folders_count' => 0,
                'progress_percent' => 0,
                'current_action' => 'Idle',
                'current_file' => '',
                'logs' => [],
                'errors' => [],
                'completed_items' => [],
            ];
        }

        return $state;
    }

    /**
     * Cancel ongoing operation
     */
        /**
     * Pause ongoing execution
     */
    public function pauseExecution(): array
    {
        $state = Cache::get(self::CACHE_KEY);
        if ($state) {
            $state['is_paused'] = true;
            $state['current_action'] = 'Execution paused by user.';
            $state['logs'][] = [
                'time' => date('H:i:s'),
                'type' => 'warning',
                'message' => 'Execution paused by user.',
            ];
            Cache::put(self::CACHE_KEY, $state, now()->addHours(2));
        }

        return $this->getExecutionStatus();
    }

    /**
     * Resume ongoing execution
     */
    public function resumeExecution(): array
    {
        $state = Cache::get(self::CACHE_KEY);
        if ($state) {
            $state['is_paused'] = false;
            $state['current_action'] = 'Resuming execution...';
            $state['logs'][] = [
                'time' => date('H:i:s'),
                'type' => 'info',
                'message' => 'Execution resumed.',
            ];
            Cache::put(self::CACHE_KEY, $state, now()->addHours(2));
        }

        return $this->getExecutionStatus();
    }
    public function cancelExecution(): array
    {
        $state = Cache::get(self::CACHE_KEY);
        if ($state) {
            $state['is_active'] = false;
            $state['is_cancelled'] = true;
            $state['queue'] = [];
            $state['current_action'] = 'Operation cancelled by user.';
            $state['logs'][] = [
                'time' => date('H:i:s'),
                'type' => 'warning',
                'message' => 'Operation was cancelled by user.',
            ];
            Cache::put(self::CACHE_KEY, $state, now()->addHours(2));
        }

        return $this->getExecutionStatus();
    }

    /**
     * Legacy synchronous execution (for CLI / tests)
     */
    public function execute(array $plan, string $mode = 'move', bool $cleanup = true): array
    {
        $this->initExecution($plan, $mode, $cleanup);
        while (true) {
            $res = $this->processNextBatch(5);
            if (! $res['has_more']) {
                break;
            }
        }
        $status = $this->getExecutionStatus();

        return [
            'success' => $status['failed_count'] === 0,
            'processed' => $status['processed_count'],
            'failed' => $status['failed_count'],
            'cleaned_folders' => $status['cleaned_folders_count'] ?? 0,
            'errors' => $status['errors'],
        ];
    }

    protected function updateDatabasePath(string $oldPath, string $newPath): void
    {
        MediaItem::where('file_path', $oldPath)->update(['file_path' => $newPath]);
        Episode::where('file_path', $oldPath)->update(['file_path' => $newPath]);
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824 * 1024) {
            return round($bytes / (1073741824 * 1024), 2).' TB';
        }
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}
