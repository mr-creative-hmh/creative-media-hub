<?php

namespace App\Services\Subtitles;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Subtitle;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SubtitleHealthCheckService
{
    protected SubtitleLanguageDetectorService $languageDetector;

    protected SubtitleValidatorService $validator;

    protected array $subtitleExtensions = ['srt', 'vtt', 'sub', 'ass', 'ssa'];

    protected array $videoExtensions = ['mkv', 'mp4', 'avi', 'mov', 'm4v', 'webm', 'ts'];

    public function __construct(
        SubtitleLanguageDetectorService $languageDetector,
        SubtitleValidatorService $validator
    ) {
        $this->languageDetector = $languageDetector;
        $this->validator = $validator;
    }

    /**
     * Run subtitle health check across directories, prune invalid files, and standardize naming.
     *
     * @param  array{dry_run?: bool, delete_invalid?: bool, auto_rename?: bool, target_path?: ?string}  $options
     * @return array{total_scanned: int, valid_count: int, invalid_count: int, deleted_count: int, renamed_count: int, already_standard_count: int, dry_run: bool, language_breakdown: array, items: array}
     */
    public function checkAndNormalize(array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $deleteInvalid = (bool) ($options['delete_invalid'] ?? true);
        $autoRename = (bool) ($options['auto_rename'] ?? true);
        $targetPath = $options['target_path'] ?? null;

        $directories = $this->resolveDirectories($targetPath);
        $scannedFiles = $this->collectSubtitleFiles($directories);

        $results = [
            'total_scanned' => count($scannedFiles),
            'valid_count' => 0,
            'invalid_count' => 0,
            'deleted_count' => 0,
            'renamed_count' => 0,
            'already_standard_count' => 0,
            'encoding_fixed_count' => 0,
            'dry_run' => $dryRun,
            'language_breakdown' => [],
            'items' => [],
        ];

        foreach ($scannedFiles as $filePath) {
            $normalizedPath = str_replace('\\', '/', $filePath);
            $fileName = basename($normalizedPath);
            $dirName = dirname($normalizedPath);

            // 1. Validate file integrity and check for stubs/corruption
            $validation = $this->validator->validate($normalizedPath, $fileName);

            if (! $validation['is_valid']) {
                $results['invalid_count']++;
                $actionTaken = $dryRun ? 'flagged_for_deletion' : 'deleted_invalid';

                if (! $dryRun && $deleteInvalid) {
                    try {
                        if (File::exists($normalizedPath)) {
                            File::delete($normalizedPath);
                            $results['deleted_count']++;
                        }
                        // Prune database record if exists
                        Subtitle::where('file_path', $normalizedPath)->delete();
                        $actionTaken = 'deleted';
                    } catch (\Throwable $e) {
                        Log::error("Failed to delete invalid subtitle: {$normalizedPath}", ['error' => $e->getMessage()]);
                        $actionTaken = 'delete_failed';
                    }
                }

                $results['items'][] = [
                    'original_path' => $normalizedPath,
                    'file_name' => $fileName,
                    'is_valid' => false,
                    'cue_count' => $validation['cue_count'],
                    'file_size' => $validation['file_size'],
                    'issues' => $validation['issues'],
                    'detected_language' => 'und',
                    'language_name' => 'Invalid Stub',
                    'flag' => '⚠️',
                    'action' => $actionTaken,
                    'target_filename' => null,
                ];

                continue;
            }

            $results['valid_count']++;

            // 2. High-accuracy language detection from dialogue content
            $langResult = $this->languageDetector->detectLanguage($normalizedPath, $fileName);
            $langCode = $langResult['language'];

            if (! isset($results['language_breakdown'][$langCode])) {
                $results['language_breakdown'][$langCode] = [
                    'code' => $langCode,
                    'name_en' => $langResult['name_en'],
                    'name_ar' => $langResult['name_ar'],
                    'flag' => $langResult['flag'],
                    'count' => 0,
                ];
            }
            $results['language_breakdown'][$langCode]['count']++;

            // 3. Match adjacent media item (movie or episode)
            $mediaBaseName = $this->findAdjacentMediaBaseName($normalizedPath);

            // Determine standardized target filename: {mediaBaseName}.{langCode}.srt
            $isForced = (bool) preg_match('/\bforced\b/i', $fileName);
            $isSDH = (bool) preg_match('/\bsdh\b/i', $fileName);

            $modifier = '';
            if ($isForced) {
                $modifier = '.forced';
            } elseif ($isSDH) {
                $modifier = '.sdh';
            }

            $targetExt = 'srt';
            $targetFileName = "{$mediaBaseName}.{$langCode}{$modifier}.{$targetExt}";
            $targetPath = "{$dirName}/{$targetFileName}";

            // Check and normalize encoding (e.g. Windows-1256 / ISO-8859-6 to clean UTF-8)
            $rawContent = @file_get_contents($normalizedPath);
            $needsEncodingFix = ($rawContent !== false && ! mb_check_encoding($rawContent, 'UTF-8'));
            if ($needsEncodingFix && ! $dryRun) {
                try {
                    $cleanUtf8 = $this->languageDetector->sanitizeToUtf8($rawContent);
                    if (mb_check_encoding($cleanUtf8, 'UTF-8')) {
                        File::put($normalizedPath, $cleanUtf8);
                        $results['encoding_fixed_count']++;
                    }
                } catch (\Throwable $e) {
                    Log::warning("Failed to normalize encoding for {$normalizedPath}: {$e->getMessage()}");
                }
            }

            $issues = [];
            if ($needsEncodingFix) {
                $issues[] = $dryRun ? 'non_utf8_encoding' : 'converted_to_utf8';
            }

            $isAlreadyStandard = (strtolower($fileName) === strtolower($targetFileName));
            $mediaModel = $this->findAdjacentMediaModel($normalizedPath, $mediaBaseName);

            if ($isAlreadyStandard) {
                $results['already_standard_count']++;
                // Ensure database record has correct language and media linkage set
                if (! $dryRun) {
                    $updated = Subtitle::where('file_path', $normalizedPath)->update([
                        'language' => $langCode,
                        'language_name' => $langResult['name_en'],
                    ]);

                    if ($updated === 0 && $mediaModel) {
                        Subtitle::updateOrCreate(
                            ['file_path' => $normalizedPath],
                            [
                                'subtitlable_id' => $mediaModel->id,
                                'subtitlable_type' => get_class($mediaModel),
                                'language' => $langCode,
                                'language_name' => $langResult['name_en'],
                                'format' => strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION)),
                                'is_embedded' => false,
                                'is_default' => ($langCode === 'ar'),
                            ]
                        );
                    }
                }

                $results['items'][] = [
                    'original_path' => $normalizedPath,
                    'file_name' => $fileName,
                    'is_valid' => true,
                    'cue_count' => $validation['cue_count'],
                    'file_size' => $validation['file_size'],
                    'issues' => $issues,
                    'detected_language' => $langCode,
                    'language_name' => $langResult['name_en'],
                    'language_name_ar' => $langResult['name_ar'],
                    'flag' => $langResult['flag'],
                    'confidence' => $langResult['confidence'],
                    'action' => $needsEncodingFix ? ($dryRun ? 'would_normalize_utf8' : 'normalized_utf8') : 'already_standard',
                    'target_filename' => $targetFileName,
                ];
            } else {
                $actionTaken = $dryRun ? 'would_rename' : 'renamed';

                if (! $dryRun && $autoRename) {
                    try {
                        // Avoid overwriting an existing different file unless same file
                        if ($normalizedPath !== $targetPath && File::exists($targetPath)) {
                            // If target already exists, append counter index
                            $targetFileName = "{$mediaBaseName}.{$langCode}{$modifier}.2.{$targetExt}";
                            $targetPath = "{$dirName}/{$targetFileName}";
                        }

                        if ($normalizedPath !== $targetPath) {
                            File::move($normalizedPath, $targetPath);
                            $results['renamed_count']++;

                            // Update database record with new path and language, preserving model ID
                            $updated = Subtitle::where('file_path', $normalizedPath)->update([
                                'file_path' => $targetPath,
                                'language' => $langCode,
                                'language_name' => $langResult['name_en'],
                            ]);

                            if ($updated === 0 && $mediaModel) {
                                Subtitle::updateOrCreate(
                                    ['file_path' => $targetPath],
                                    [
                                        'subtitlable_id' => $mediaModel->id,
                                        'subtitlable_type' => get_class($mediaModel),
                                        'language' => $langCode,
                                        'language_name' => $langResult['name_en'],
                                        'format' => strtolower(pathinfo($targetPath, PATHINFO_EXTENSION)),
                                        'is_embedded' => false,
                                        'is_default' => ($langCode === 'ar'),
                                    ]
                                );
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::error("Failed to rename subtitle: {$normalizedPath} to {$targetPath}", ['error' => $e->getMessage()]);
                        $actionTaken = 'rename_failed';
                    }
                }

                $results['items'][] = [
                    'original_path' => $normalizedPath,
                    'file_name' => $fileName,
                    'is_valid' => true,
                    'cue_count' => $validation['cue_count'],
                    'file_size' => $validation['file_size'],
                    'issues' => $issues,
                    'detected_language' => $langCode,
                    'language_name' => $langResult['name_en'],
                    'language_name_ar' => $langResult['name_ar'],
                    'flag' => $langResult['flag'],
                    'confidence' => $langResult['confidence'],
                    'action' => $actionTaken,
                    'target_filename' => $targetFileName,
                ];
            }
        }

        // Sort breakdown by count descending
        uasort($results['language_breakdown'], fn ($a, $b) => $b['count'] <=> $a['count']);

        return $results;
    }

    /**
     * Resolve directories or single file to inspect.
     *
     * @return string[]
     */
    protected function resolveDirectories(?string $targetPath = null): array
    {
        if (! empty($targetPath)) {
            $cleaned = trim($targetPath, " \t\n\r\0\x0B\"'");
            $norm = rtrim(str_replace('\\', '/', $cleaned), '/');
            if (is_file($norm) || is_dir($norm)) {
                return [$norm];
            }
        }

        $dirs = [];

        // 1. Monitored scanner directories from settings
        $monitoredSetting = AppSetting::where('key', 'scanner_monitored_directories')->first();
        if ($monitoredSetting && ! empty($monitoredSetting->value)) {
            $parsed = json_decode($monitoredSetting->value, true) ?: [];
            foreach ($parsed as $item) {
                $p = rtrim(str_replace('\\', '/', $item['path'] ?? ''), '/');
                if (! empty($p) && is_dir($p)) {
                    $dirs[] = $p;
                }
            }
        }

        // 2. Download destinations
        $destMovies = AppSetting::get('download_folder_movies');
        if ($destMovies && is_dir($destMovies)) {
            $dirs[] = rtrim(str_replace('\\', '/', $destMovies), '/');
        }

        $destSeries = AppSetting::get('download_folder_series');
        if ($destSeries && is_dir($destSeries)) {
            $dirs[] = rtrim(str_replace('\\', '/', $destSeries), '/');
        }

        // 3. Root directories from indexed MediaItems & Episodes
        $allMediaPaths = MediaItem::whereNotNull('file_path')->pluck('file_path');
        $allEpisodePaths = Episode::whereNotNull('file_path')->pluck('file_path');

        $rootDirs = [];
        foreach ($allMediaPaths->concat($allEpisodePaths) as $fp) {
            $cleanP = str_replace('\\', '/', $fp);
            $d = dirname($cleanP);
            while (in_array(strtolower(basename($d)), ['s01', 's02', 's03', 's04', 's05', 's06', 's07', 's08', 's09', 's10', 'season 1', 'season 2', 'season 3', 'subs', 'subtitles'])) {
                $d = dirname($d);
            }
            $parentRoot = dirname($d);
            if ($parentRoot && is_dir($parentRoot) && strlen($parentRoot) > 3) {
                $rootDirs[$parentRoot] = true;
            } elseif (is_dir($d)) {
                $rootDirs[$d] = true;
            }
        }
        $dirs = array_merge($dirs, array_keys($rootDirs));

        // 4. Default media storage fallback
        $storageMedia = rtrim(str_replace('\\', '/', storage_path('app/media')), '/');
        if (is_dir($storageMedia)) {
            $dirs[] = $storageMedia;
        }

        $storageSubs = rtrim(str_replace('\\', '/', storage_path('app/subtitles')), '/');
        if (is_dir($storageSubs)) {
            $dirs[] = $storageSubs;
        }

        // Filter out redundant subdirectories if their parent is already included
        $uniqueDirs = array_values(array_unique(array_filter($dirs)));
        sort($uniqueDirs);

        $filtered = [];
        foreach ($uniqueDirs as $candidate) {
            $isSubdir = false;
            foreach ($filtered as $parent) {
                if (str_starts_with($candidate, $parent.'/')) {
                    $isSubdir = true;
                    break;
                }
            }
            if (! $isSubdir && is_dir($candidate)) {
                $filtered[] = $candidate;
            }
        }

        return $filtered;
    }

    /**
     * Recursively gather all subtitle files from directory list.
     *
     * @param  string[]  $directories
     * @return string[]
     */
    protected function collectSubtitleFiles(array $directories): array
    {
        $subFiles = [];
        $skipDirs = [
            '$recycle.bin', 'system volume information', '.git', 'node_modules',
            'vendor', '.cache', '__macosx', '.idea', '.vscode',
        ];

        foreach ($directories as $entry) {
            $normEntry = str_replace('\\', '/', $entry);

            // Handle direct single file target
            if (is_file($normEntry)) {
                $ext = strtolower(pathinfo($normEntry, PATHINFO_EXTENSION));
                if (in_array($ext, $this->subtitleExtensions, true)) {
                    $subFiles[] = realpath($normEntry) ?: $normEntry;
                }

                continue;
            }

            if (! is_dir($normEntry)) {
                continue;
            }

            try {
                $dirIterator = new \RecursiveDirectoryIterator(
                    $normEntry,
                    \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS
                );

                $filterIterator = new \RecursiveCallbackFilterIterator(
                    $dirIterator,
                    function ($current, $key, $iterator) use ($skipDirs) {
                        $filename = $current->getFilename();

                        // Skip hidden files, system files, and macOS resource forks
                        if (str_starts_with($filename, '.') || str_starts_with($filename, '._')) {
                            return false;
                        }

                        if ($current->isDir()) {
                            $lower = strtolower($filename);
                            if (in_array($lower, $skipDirs, true)) {
                                return false;
                            }

                            return true;
                        }

                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                        return in_array($ext, $this->subtitleExtensions, true);
                    }
                );

                $iterator = new \RecursiveIteratorIterator(
                    $filterIterator,
                    \RecursiveIteratorIterator::LEAVES_ONLY,
                    \RecursiveIteratorIterator::CATCH_GET_CHILD
                );

                foreach ($iterator as $fileInfo) {
                    if ($fileInfo->isFile()) {
                        $subFiles[] = str_replace('\\', '/', $fileInfo->getPathname());
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Error traversing directory {$normEntry} for subtitles: ".$e->getMessage());
            }
        }

        return array_values(array_unique($subFiles));
    }

    /**
     * Find adjacent media item or episode in the database.
     */
    protected function findAdjacentMediaModel(string $subPath, string $mediaBaseName): MediaItem|Episode|null
    {
        // 1. Try finding by file_path matching video filename
        $episode = Episode::where('file_path', 'LIKE', "%/{$mediaBaseName}.%")->first()
            ?? Episode::where('file_path', 'LIKE', "%\\{$mediaBaseName}.%")->first();
        if ($episode) {
            return $episode;
        }

        $movie = MediaItem::where('file_path', 'LIKE', "%/{$mediaBaseName}.%")->first()
            ?? MediaItem::where('file_path', 'LIKE', "%\\{$mediaBaseName}.%")->first();
        if ($movie) {
            return $movie;
        }

        // 2. Check by episode season/number if SxxExx pattern exists
        if (preg_match('/[sS](\d{1,2})[eE](\d{1,2})/i', $mediaBaseName, $m)) {
            $seasonNum = (int) $m[1];
            $episodeNum = (int) $m[2];

            $seriesFolder = basename(dirname(dirname($subPath)));
            $ep = Episode::where('season_number', $seasonNum)
                ->where('episode_number', $episodeNum)
                ->whereHas('series', function ($q) use ($seriesFolder) {
                    $q->where('title', 'LIKE', "%{$seriesFolder}%")
                        ->orWhere('folder_path', 'LIKE', "%{$seriesFolder}%");
                })
                ->first();

            if ($ep) {
                return $ep;
            }
        }

        return null;
    }

    /**
     * Find adjacent media filename (e.g. Inception (2010)) to pair subtitle with.
     */
    protected function findAdjacentMediaBaseName(string $subPath): string
    {
        $dir = dirname($subPath);
        $subName = basename($subPath);
        $subBase = pathinfo($subName, PATHINFO_FILENAME);

        // Strip existing language tokens or common suffixes from the subtitle name
        $cleanSubBase = preg_replace('/\.(?:ar|ara|arabic|en|eng|english|fr|fre|fra|french|es|spa|spanish|de|ger|german|it|ita|italian|pt|por|ru|tr|ja|ko|zh)(?:\.(?:forced|sdh|cc))?$/i', '', $subBase);

        // Check if there are video files in this same directory
        $parentDir = $dir;
        if (in_array(strtolower(basename($dir)), ['subs', 'subtitles', 'sub'])) {
            $parentDir = dirname($dir);
        }

        try {
            $siblingFiles = File::files($parentDir);
            $videoFiles = [];
            foreach ($siblingFiles as $f) {
                $ext = strtolower($f->getExtension());
                if (in_array($ext, $this->videoExtensions, true)) {
                    $videoFiles[] = pathinfo($f->getFilename(), PATHINFO_FILENAME);
                }
            }

            if (! empty($videoFiles)) {
                // If only 1 video file in folder, that's definitely the one!
                if (count($videoFiles) === 1) {
                    return $videoFiles[0];
                }

                // Check for episode match e.g. S01E04
                if (preg_match('/[sS](\d{1,2})[eE](\d{1,2})/i', $subName, $subEpMatches)) {
                    $subSeason = (int) $subEpMatches[1];
                    $subEpisode = (int) $subEpMatches[2];

                    foreach ($videoFiles as $vf) {
                        if (preg_match('/[sS](\d{1,2})[eE](\d{1,2})/i', $vf, $vEpMatches)) {
                            if ((int) $vEpMatches[1] === $subSeason && (int) $vEpMatches[2] === $subEpisode) {
                                return $vf;
                            }
                        }
                    }
                }

                // Match by string similarity
                foreach ($videoFiles as $vf) {
                    if (str_contains(strtolower($cleanSubBase), strtolower($vf)) || str_contains(strtolower($vf), strtolower($cleanSubBase))) {
                        return $vf;
                    }
                }

                return $videoFiles[0];
            }
        } catch (\Throwable $e) {
        }

        return $cleanSubBase;
    }
}
