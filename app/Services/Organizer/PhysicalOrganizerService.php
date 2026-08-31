<?php

namespace App\Services\Organizer;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Subtitle;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PhysicalOrganizerService
{
    protected SceneNameParserService $parser;

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

            $cleanTitle = $parsed['clean_title'] ?? ($parsed['title'] ?? ($parsed['series_title'] ?? 'Unknown'));
            $year = !empty($parsed['year']) ? (string) $parsed['year'] : 'Unknown Year';
            $seasonNum = isset($parsed['season']) ? (int) $parsed['season'] : 1;
            $episodeNum = isset($parsed['episode']) ? (int) $parsed['episode'] : 1;

            $firstChar = mb_strtoupper(mb_substr($cleanTitle, 0, 1));
            $firstLetter = preg_match('/^[A-Z0-9]$/i', $firstChar) ? $firstChar : '#';

            $tokens = [
                '{Type}' => $typeDir,
                '{Title}' => $cleanTitle,
                '{Year}' => $year,
                '{Resolution}' => $parsed['resolution'] ?: '1080p',
                '{Codec}' => $parsed['codec'] ?: 'x264',
                '{Edition}' => $parsed['edition'] ?? '',
                '{Group}' => $parsed['group'] ?? 'MEDIA',
                '{FirstLetter}' => $firstLetter,
                '{Season:02}' => sprintf('%02d', $seasonNum),
                '{Episode:02}' => sprintf('%02d', $episodeNum),
                '{EpisodeTitle}' => "Episode {$episodeNum}",
                '{ext}' => $parsed['extension'] ?? (pathinfo($filePath, PATHINFO_EXTENSION) ?: 'mkv'),
            ];

            $relPath = str_replace(array_keys($tokens), array_values($tokens), $pattern);
            // Clean double slashes or extra empty brackets
            $relPath = preg_replace('#/+#', '/', $relPath);
            $relPath = str_replace(['[]', '[ ]', '()', '( )', ' - .', ' .'], ['', '', '', '', '.', '.'], $relPath);
            $relPath = trim($relPath, '/');

            $destination = "{$targetRoot}/{$relPath}";
            $source = str_replace('\\', '/', $filePath);

            $exists = File::exists($destination);
            $isIdentical = $source === $destination;

            $status = 'ready';
            if ($isIdentical) {
                $status = 'identical';
            } elseif ($exists) {
                $status = 'collision_exists';
            }

            $item = [
                'source_path' => $source,
                'destination_path' => $destination,
                'filename' => $file['filename'] ?? basename($filePath),
                'clean_title' => $cleanTitle,
                'type' => $parsed['type'] ?? ($isSeries ? 'series' : 'movie'),
                'year' => $parsed['year'] ?? null,
                'season' => $isSeries ? $seasonNum : null,
                'episode' => $isSeries ? $episodeNum : null,
                'resolution' => $parsed['resolution'] ?? '1080p',
                'size_bytes' => $file['size_bytes'] ?? 0,
                'size_formatted' => $file['size_formatted'] ?? '',
                'status' => $status,
                'selected' => $status === 'ready',
                'subtitles' => [],
            ];

            // Subtitle mapping with language preserving
            if (!empty($file['subtitles'])) {
                $destDir = pathinfo($destination, PATHINFO_DIRNAME);
                $destBase = pathinfo($destination, PATHINFO_FILENAME);

                foreach ($file['subtitles'] as $sub) {
                    $subPath = $sub['path'] ?? '';
                    $subExt = $sub['extension'] ?? (pathinfo($subPath, PATHINFO_EXTENSION) ?: 'srt');
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

    public function execute(array $plan, string $mode = 'move'): array
    {
        $processed = 0;
        $failed = 0;
        $errors = [];

        foreach ($plan as $item) {
            if (empty($item['selected'])) {
                continue;
            }

            $source = $item['source_path'];
            $dest = $item['destination_path'];

            try {
                if (!File::exists($source)) {
                    $failed++;
                    $errors[] = "Source file does not exist: {$source}";
                    continue;
                }

                $destDir = pathinfo($dest, PATHINFO_DIRNAME);
                if (!File::isDirectory($destDir)) {
                    File::makeDirectory($destDir, 0755, true, true);
                }

                if ($mode === 'move') {
                    File::move($source, $dest);
                    $this->updateDatabasePath($source, $dest);
                } else {
                    File::copy($source, $dest);
                }

                // Process linked subtitles
                if (!empty($item['subtitles'])) {
                    foreach ($item['subtitles'] as $sub) {
                        $subSource = $sub['source'];
                        $subDest = $sub['destination'];

                        if (File::exists($subSource)) {
                            if ($mode === 'move') {
                                File::move($subSource, $subDest);
                                Subtitle::where('file_path', $subSource)->update(['file_path' => $subDest]);
                            } else {
                                File::copy($subSource, $subDest);
                            }
                        }
                    }
                }

                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Failed processing {$source}: " . $e->getMessage();
                Log::error("Organizer execute failed: " . $e->getMessage());
            }
        }

        return [
            'success' => $failed === 0,
            'processed' => $processed,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    protected function updateDatabasePath(string $oldPath, string $newPath): void
    {
        MediaItem::where('file_path', $oldPath)->update(['file_path' => $newPath]);
        Episode::where('file_path', $oldPath)->update(['file_path' => $newPath]);
    }
}
