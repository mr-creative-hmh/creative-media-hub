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

class PhysicalOrganizerService
{
    protected SceneNameParserService $parser;

    protected const CACHE_KEY = 'organizer_execution_state';

    public function __construct(SceneNameParserService $parser)
    {
        $this->parser = $parser;
    }

    public function generateDryRun(array $scannedFiles, string $targetRoot, ?string $moviePattern = null, ?string $seriesPattern = null): array
    {
        $moviePattern = $moviePattern ?: AppSetting::get('movie_naming_template', '{Type}/{Title} ({Year})/{Title} ({Year}) [{Resolution}].{ext}');
        $seriesPattern = $seriesPattern ?: AppSetting::get('series_naming_template', '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{Resolution}].{ext}');

        $targetRoot = rtrim(str_replace('\\', '/', $targetRoot), '/');
        $plan = [];

        foreach ($scannedFiles as $file) {
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
            $resTag = $parsed['resolution'] ?: '1080p FHD';

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

            $primaryGenre = ! empty($genresList[0]) ? $this->sanitizePathSegment($genresList[0]) : 'General';
            $joinedGenres = ! empty($genresList) ? $this->sanitizePathSegment(implode(' & ', array_slice($genresList, 0, 2))) : $primaryGenre;

            $tokens = [
                '{Type}' => $typeDir,
                '{Title}' => $cleanTitle,
                '{Year}' => $year,
                '{Genre}' => $primaryGenre,
                '{Genres}' => $joinedGenres,
                '{Resolution}' => $this->sanitizePathSegment($resTag),
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
            if (empty($epTitle)) {
                $relPath = str_replace(' - {EpisodeTitle}', '', $relPath);
                $relPath = str_replace('{EpisodeTitle}', '', $relPath);
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
                    // File name with extension
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

            // Subtitle mapping with language preserving
            if (! empty($file['subtitles'])) {
                $destDir = pathinfo($destination, PATHINFO_DIRNAME);
                $destBase = pathinfo($destination, PATHINFO_FILENAME);

                foreach ($file['subtitles'] as $sub) {
                    $subPath = $sub['path'] ?? '';
                    $subExt = strtolower($sub['extension'] ?? (pathinfo($subPath, PATHINFO_EXTENSION) ?: 'srt'));
                    $lang = $sub['language'] ?? 'und';
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

            $plan[] = $item;
        }

        return $plan;
    }

    /**
     * Sanitize folder or file segment for Windows/Linux filesystems.
     */
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

        if (! $state || empty($state['is_active']) || empty($state['queue'])) {
            if ($state) {
                $state['is_active'] = false;
                $state['is_completed'] = true;
                $state['progress_percent'] = 100;
                $state['current_action'] = 'All operations completed!';
                Cache::put(self::CACHE_KEY, $state, now()->addHours(2));
            }

            return [
                'has_more' => false,
                'status' => $state ?: $this->getExecutionStatus(),
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

        Cache::put(self::CACHE_KEY, $state, now()->addHours(2));

        return [
            'has_more' => ! empty($queue),
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
