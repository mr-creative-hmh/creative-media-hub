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

            $cleanTitle = $parsed['clean_title'] ?? ($parsed['title'] ?? 'Unknown');
            $year = $parsed['year'] ? (string) $parsed['year'] : 'Unknown Year';
            $seasonNum = isset($parsed['season']) ? (int) $parsed['season'] : 1;
            $episodeNum = isset($parsed['episode']) ? (int) $parsed['episode'] : 1;

            $tokens = [
                '{Type}' => $typeDir,
                '{Title}' => $cleanTitle,
                '{Year}' => $year,
                '{Resolution}' => $parsed['resolution'] ?: '1080p',
                '{Codec}' => $parsed['codec'] ?: 'x264',
                '{Season:02}' => sprintf('%02d', $seasonNum),
                '{Episode:02}' => sprintf('%02d', $episodeNum),
                '{EpisodeTitle}' => "Episode {$episodeNum}",
                '{ext}' => $parsed['extension'] ?? 'mkv',
            ];

            $relPath = str_replace(array_keys($tokens), array_values($tokens), $pattern);
            // Clean double slashes or extra brackets
            $relPath = preg_replace('#/+#', '/', $relPath);
            $relPath = str_replace(['[]', '[ ]', '()', '( )'], '', $relPath);
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
                'type' => $parsed['type'],
                'year' => $parsed['year'],
                'season' => $isSeries ? $seasonNum : null,
                'episode' => $isSeries ? $episodeNum : null,
                'resolution' => $parsed['resolution'],
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
                    $subExt = $sub['extension'] ?? 'srt';
                    $lang = $sub['language'] ?? 'und';
                    $langSuffix = in_array($lang, ['ar', 'en', 'fr', 'es', 'de']) ? ".{$lang}" : '';

                    $subDest = "{$destDir}/{$destBase}{$langSuffix}.{$subExt}";
                    $item['subtitles'][] = [
                        'source' => $sub['path'],
                        'destination' => $subDest,
                        'language' => $lang,
                    ];
                }
            }

            $plan[] = $item;
        }

        return $plan;
    }

    public function execute(array $planItems, string $mode = 'move'): array
    {
        $executed = [];
        $failed = [];
        $journal = [
            'timestamp' => now()->toIso8601String(),
            'mode' => $mode,
            'operations' => [],
        ];

        foreach ($planItems as $item) {
            if (empty($item['selected'])) {
                continue;
            }

            $source = $item['source_path'];
            $dest = $item['destination_path'];

            try {
                $destDir = pathinfo($dest, PATHINFO_DIRNAME);
                if (!File::isDirectory($destDir)) {
                    File::makeDirectory($destDir, 0755, true);
                }

                if ($mode === 'move') {
                    File::move($source, $dest);
                    $journal['operations'][] = ['action' => 'move', 'from' => $source, 'to' => $dest];

                    // 🔄 Synchronize Virtual Library Database
                    MediaItem::where('file_path', $source)->update([
                        'file_path' => $dest,
                        'folder_path' => $destDir,
                    ]);
                    Episode::where('file_path', $source)->update([
                        'file_path' => $dest,
                    ]);
                } elseif ($mode === 'copy') {
                    File::copy($source, $dest);
                    $journal['operations'][] = ['action' => 'copy', 'from' => $source, 'to' => $dest];
                }

                // Handle Subtitles
                foreach ($item['subtitles'] ?? [] as $sub) {
                    if (File::exists($sub['source'])) {
                        if ($mode === 'move') {
                            File::move($sub['source'], $sub['destination']);
                            $journal['operations'][] = ['action' => 'move', 'from' => $sub['source'], 'to' => $sub['destination']];

                            Subtitle::where('file_path', $sub['source'])->update([
                                'file_path' => $sub['destination'],
                            ]);
                        } else {
                            File::copy($sub['source'], $sub['destination']);
                        }
                    }
                }

                $executed[] = $item;
            } catch (\Exception $e) {
                Log::error("Organizer operation failed for {$source}: " . $e->getMessage());
                $failed[] = [
                    'item' => $item,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Save Journal
        if (!empty($journal['operations'])) {
            $journalDir = storage_path('app/organizer_journals');
            if (!File::isDirectory($journalDir)) {
                File::makeDirectory($journalDir, 0755, true);
            }
            File::put("{$journalDir}/journal_" . time() . ".json", json_encode($journal, JSON_PRETTY_PRINT));
        }

        return [
            'success_count' => count($executed),
            'failed_count' => count($failed),
            'executed' => $executed,
            'failed' => $failed,
        ];
    }
}
