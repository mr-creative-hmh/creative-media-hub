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

            $isAlreadyStandard = (strtolower($fileName) === strtolower($targetFileName));

            if ($isAlreadyStandard) {
                $results['already_standard_count']++;
                // Still ensure database record has correct language set
                if (! $dryRun) {
                    Subtitle::where('file_path', $normalizedPath)->update([
                        'language' => $langCode,
                        'language_name' => $langResult['name_en'],
                    ]);
                }

                $results['items'][] = [
                    'original_path' => $normalizedPath,
                    'file_name' => $fileName,
                    'is_valid' => true,
                    'cue_count' => $validation['cue_count'],
                    'file_size' => $validation['file_size'],
                    'issues' => [],
                    'detected_language' => $langCode,
                    'language_name' => $langResult['name_en'],
                    'language_name_ar' => $langResult['name_ar'],
                    'flag' => $langResult['flag'],
                    'confidence' => $langResult['confidence'],
                    'action' => 'already_standard',
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

                            // Update database record with new path and language
                            Subtitle::where('file_path', $normalizedPath)->update([
                                'file_path' => $targetPath,
                                'language' => $langCode,
                                'language_name' => $langResult['name_en'],
                            ]);
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
                    'issues' => [],
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
     * Resolve directories to inspect.
     *
     * @return string[]
     */
    protected function resolveDirectories(?string $targetPath = null): array
    {
        if (! empty($targetPath)) {
            $norm = rtrim(str_replace('\\', '/', trim($targetPath)), '/');
            if (File::isDirectory($norm)) {
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
                if (! empty($p) && File::isDirectory($p)) {
                    $dirs[] = $p;
                }
            }
        }

        // 2. Download destinations
        $destMovies = AppSetting::get('download_folder_movies');
        if ($destMovies && File::isDirectory($destMovies)) {
            $dirs[] = rtrim(str_replace('\\', '/', $destMovies), '/');
        }

        $destSeries = AppSetting::get('download_folder_series');
        if ($destSeries && File::isDirectory($destSeries)) {
            $dirs[] = rtrim(str_replace('\\', '/', $destSeries), '/');
        }

        // 3. Unique directories from indexed MediaItems & Episodes
        $mediaDirs = MediaItem::whereNotNull('file_path')
            ->limit(100)
            ->pluck('file_path')
            ->map(fn ($p) => dirname(str_replace('\\', '/', $p)))
            ->unique();

        foreach ($mediaDirs as $md) {
            if (File::isDirectory($md)) {
                $dirs[] = $md;
            }
        }

        $episodeDirs = Episode::whereNotNull('file_path')
            ->limit(100)
            ->pluck('file_path')
            ->map(fn ($p) => dirname(str_replace('\\', '/', $p)))
            ->unique();

        foreach ($episodeDirs as $ed) {
            if (File::isDirectory($ed)) {
                $dirs[] = $ed;
            }
        }

        // 4. Default media storage fallback
        $storageMedia = rtrim(str_replace('\\', '/', storage_path('app/media')), '/');
        if (File::isDirectory($storageMedia)) {
            $dirs[] = $storageMedia;
        }

        $storageSubs = rtrim(str_replace('\\', '/', storage_path('app/subtitles')), '/');
        if (File::isDirectory($storageSubs)) {
            $dirs[] = $storageSubs;
        }

        return array_values(array_unique($dirs));
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

        foreach ($directories as $dir) {
            if (! File::isDirectory($dir)) {
                continue;
            }

            try {
                $files = File::allFiles($dir);
                foreach ($files as $file) {
                    $ext = strtolower($file->getExtension());
                    if (in_array($ext, $this->subtitleExtensions, true)) {
                        $fn = $file->getFilename();
                        if (str_starts_with($fn, '.') || str_starts_with($fn, '._')) {
                            continue;
                        }
                        $subFiles[] = $file->getRealPath();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Error traversing directory {$dir} for subtitles: ".$e->getMessage());
            }
        }

        return array_values(array_unique($subFiles));
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
