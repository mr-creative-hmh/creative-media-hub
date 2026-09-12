<?php

namespace App\Services\Scout;

use App\Models\AppSetting;
use App\Models\DownloadItem;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Series;
use App\Services\Metadata\LibraryMasterIndexService;
use App\Services\Metadata\TmdbProvider;
use App\Services\Organizer\PhysicalOrganizerService;
use App\Services\Organizer\SceneNameParserService;
use App\Services\Scanner\VirtualLibraryScannerService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LibraryAcquisitionService
{
    public function __construct(
        protected PhysicalOrganizerService $organizerService,
        protected VirtualLibraryScannerService $scannerService,
        protected TmdbProvider $tmdbProvider,
        protected LibraryGapService $gapService,
        protected SceneNameParserService $parserService,
        protected LibraryMasterIndexService $masterIndexService
    ) {}

    /**
     * Organize a downloaded file and immediately scan it into the library.
     */
    public function organizeAndScanFile(
        string $sourceFilePath,
        string $mediaType = 'movie',
        array $metadata = []
    ): array {
        if (! File::exists($sourceFilePath)) {
            return [
                'success' => false,
                'error' => "Source file does not exist: {$sourceFilePath}",
            ];
        }

        try {
            // Determine monitored target root
            $targetRoot = $this->resolveTargetRoot($mediaType);
            if (empty($targetRoot) || ! File::isDirectory($targetRoot)) {
                return [
                    'success' => false,
                    'error' => "Target library directory not found: {$targetRoot}",
                ];
            }

            // Compute canonical destination in H:\Entertainment
            $destPath = $this->calculateCanonicalDestination($sourceFilePath, $targetRoot, $mediaType, $metadata);
            if (empty($destPath)) {
                return [
                    'success' => false,
                    'error' => "Could not determine canonical destination for {$sourceFilePath}",
                ];
            }

            // Ensure destination directory exists
            $destDir = dirname($destPath);
            if (! File::isDirectory($destDir)) {
                File::makeDirectory($destDir, 0755, true, true);
            }

            // Move the media file
            $this->crossDriveMove($sourceFilePath, $destPath);

            // Move any matching subtitles if present
            $this->moveCompanionSubtitles($sourceFilePath, $destPath);

            // Immediately scan and index the single file into the virtual library
            $scannedModel = $this->scannerService->processSingleFile($destPath, $mediaType);

            // Invalidate scout cache so gaps clear immediately
            $this->gapService->clearCache();

            return [
                'success' => true,
                'source_path' => $sourceFilePath,
                'destination_path' => $destPath,
                'media_type' => $mediaType,
                'indexed_id' => $scannedModel?->id,
            ];
        } catch (\Throwable $e) {
            Log::error('LibraryAcquisitionService organizeAndScanFile failed: '.$e->getMessage(), [
                'source' => $sourceFilePath,
                'metadata' => $metadata,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process completed DownloadItem if marked for auto-organize.
     */
    /**
     * Process completed DownloadItem if marked for auto-organize.
     * Supports single-file downloads and multi-file full season torrents.
     */
    public function handleCompletedDownload(DownloadItem $item): array
    {
        $validExts = ['mp4', 'mkv', 'avi', 'mov', 'webm', 'm4v', 'ts'];
        $mediaFiles = [];
        $sourceDir = null;

        // 1. Check if item has a direct destination_path to a file
        if ($item->destination_path && File::isFile($item->destination_path)) {
            $mediaFiles[] = $item->destination_path;
        }

        // 2. Discover files in destination_folder or directory destination_path
        $candidateFolders = array_filter([
            $item->destination_folder,
            $item->destination_path && File::isDirectory($item->destination_path) ? $item->destination_path : null,
        ]);

        foreach ($candidateFolders as $folder) {
            if (File::isDirectory($folder)) {
                $sourceDir = $folder;
                $discovered = File::allFiles($folder);
                foreach ($discovered as $f) {
                    if (in_array(strtolower($f->getExtension()), $validExts)) {
                        $filename = strtolower($f->getFilename());
                        // Exclude small sample files (< 50MB)
                        if (str_contains($filename, 'sample') && $f->getSize() < 50 * 1024 * 1024) {
                            continue;
                        }
                        $mediaFiles[] = $f->getPathname();
                    }
                }
            }
        }

        $mediaFiles = array_values(array_unique($mediaFiles));

        if (empty($mediaFiles)) {
            return [
                'success' => false,
                'error' => 'No media files found for completed download',
            ];
        }

        $results = [];
        $hasErrors = false;

        foreach ($mediaFiles as $filePath) {
            $parsed = $this->parserService->parse($filePath);
            $mediaType = $item->media_type ?: ($parsed['type'] === 'series' ? 'series' : 'movie');

            $meta = array_merge($item->torrent_files ?? [], [
                'series_title' => ! empty($parsed['series_title']) ? $parsed['series_title'] : ($item->torrent_files['series_title'] ?? $item->title),
                'season_number' => $parsed['season'] ?? ($item->torrent_files['season_number'] ?? null),
                'episode_number' => $parsed['episode'] ?? ($item->torrent_files['episode_number'] ?? null),
                'episode_title' => $parsed['episode_title'] ?? null,
                'resolution' => $parsed['resolution'] ?? ($item->torrent_files['resolution'] ?? null),
                'release_year' => $parsed['year'] ?? ($item->torrent_files['release_year'] ?? null),
            ]);

            $res = $this->organizeAndScanFile($filePath, $mediaType, $meta);
            $results[] = $res;
            if (! ($res['success'] ?? false)) {
                $hasErrors = true;
            }
        }

        // Clean up empty download folder if all media files were moved
        if ($sourceDir && File::isDirectory($sourceDir)) {
            $remaining = File::allFiles($sourceDir);
            $remainingMedia = array_filter($remaining, fn ($f) => in_array(strtolower($f->getExtension()), $validExts));
            if (empty($remainingMedia)) {
                try {
                    File::deleteDirectory($sourceDir);
                } catch (\Throwable $e) {
                    Log::warning("Could not clean up source download directory {$sourceDir}: ".$e->getMessage());
                }
            }
        }

        return [
            'success' => ! $hasErrors,
            'processed_count' => count($results),
            'files' => $results,
        ];
    }

    /**
     * Calculate destination path matching H:\Entertainment naming standards.
     */
    public function calculateCanonicalDestination(
        string $sourceFilePath,
        string $targetRoot,
        string $mediaType,
        array $metadata = []
    ): string {
        $sourcePath = str_replace('\\', '/', $sourceFilePath);
        $targetRoot = rtrim(str_replace('\\', '/', $targetRoot), '/');
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        if ($mediaType === 'series') {
            return $this->buildSeriesDestination($sourcePath, $targetRoot, $ext, $metadata);
        }

        return $this->buildMovieDestination($sourcePath, $targetRoot, $ext, $metadata);
    }

    /**
     * Build TV Series destination: H:/Entertainment/TV Shows/{Show} ({Years})/Season {SS}/{Show} - S{SS}E{EE} - {Title} [{Res}].{ext}
     */
    protected function buildSeriesDestination(string $sourcePath, string $targetRoot, string $ext, array $metadata): string
    {
        $seriesTitle = trim($metadata['series_title'] ?? '');
        $seasonNum = (int) ($metadata['season_number'] ?? 0);
        $episodeNum = (int) ($metadata['episode_number'] ?? 0);
        $episodeTitle = trim($metadata['episode_title'] ?? '');
        $resolution = trim($metadata['resolution'] ?? '');

        // Parse file name to fill in any missing components
        $parsed = $this->parserService->parse($sourcePath);
        if (empty($seriesTitle) && ! empty($parsed['series_title'])) {
            $seriesTitle = $parsed['series_title'];
        }
        if (! $seasonNum && ! empty($parsed['season'])) {
            $seasonNum = (int) $parsed['season'];
        }
        if (! $episodeNum && ! empty($parsed['episode'])) {
            $episodeNum = (int) $parsed['episode'];
        }
        if (empty($resolution) && ! empty($parsed['resolution'])) {
            $resolution = $parsed['resolution'];
        }
        if (empty($episodeTitle) && ! empty($parsed['episode_title']) && ! preg_match('/^Episode\s+\d+$/i', $parsed['episode_title'])) {
            $episodeTitle = $parsed['episode_title'];
        }

        $seasonNum = max(1, $seasonNum);
        $episodeNum = max(1, $episodeNum);
        $resolution = ! empty($resolution) ? $resolution : '1080p';

        // Look for existing series in DB (support exact & normalized title)
        $series = null;
        if (! empty($metadata['series_id'])) {
            $series = Series::find($metadata['series_id']);
        } elseif (! empty($seriesTitle)) {
            $series = Series::where('title', $seriesTitle)->first();
            if (! $series) {
                $normTitle = preg_replace('/[^a-z0-9]/i', '', $seriesTitle);
                $series = Series::all()->first(fn ($s) => preg_replace('/[^a-z0-9]/i', '', $s->title) === $normTitle);
            }
        }

        // Resolve real episode title if generic or missing
        if (empty($episodeTitle) || preg_match('/^Episode\s+\d+$/i', $episodeTitle)) {
            if ($series) {
                // 1. Check local DB
                $existingEp = Episode::where('series_id', $series->id)
                    ->whereHas('season', fn ($q) => $q->where('season_number', $seasonNum))
                    ->where('episode_number', $episodeNum)
                    ->first();
                if ($existingEp && ! empty($existingEp->title) && ! preg_match('/^Episode\s+\d+$/i', $existingEp->title)) {
                    $episodeTitle = $existingEp->title;
                }

                // 2. Check master index
                if (empty($episodeTitle) || preg_match('/^Episode\s+\d+$/i', $episodeTitle)) {
                    $masterEp = $this->masterIndexService->lookupEpisode($series->tmdb_id ?: $series->title, $seasonNum, $episodeNum);
                    if ($masterEp && ! empty($masterEp['title']) && ! preg_match('/^Episode\s+\d+$/i', $masterEp['title'])) {
                        $episodeTitle = $masterEp['title'];
                    }
                }

                // 3. Check TMDb API
                if ((empty($episodeTitle) || preg_match('/^Episode\s+\d+$/i', $episodeTitle)) && $series->tmdb_id) {
                    try {
                        $tmdbEpisodes = $this->tmdbProvider->getSeasonEpisodes($series->tmdb_id, $seasonNum);
                        foreach ($tmdbEpisodes as $tep) {
                            if ((int) $tep['episode_number'] === $episodeNum && ! empty($tep['title']) && ! preg_match('/^Episode\s+\d+$/i', $tep['title'])) {
                                $episodeTitle = $tep['title'];
                                break;
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore API error
                    }
                }
            }
        }

        if (empty($episodeTitle)) {
            $episodeTitle = "Episode {$episodeNum}";
        }

        $showFolder = '';
        if ($series && ! empty($series->folder_path) && File::isDirectory($series->folder_path)) {
            $showFolder = str_replace('\\', '/', $series->folder_path);
        } else {
            $yearStr = '';
            if ($series && $series->release_year) {
                $end = $series->end_year ?: ($series->status === 'Ended' ? $series->release_year : '');
                $yearStr = " ({$series->release_year}".($end && $end != $series->release_year ? " - {$end}" : '').')';
            }
            $cleanShow = $this->organizerService->sanitizePathSegment($series ? $series->title : $seriesTitle);
            $showFolder = "{$targetRoot}/{$cleanShow}{$yearStr}";
        }

        $seasonFolder = sprintf('%s/Season %02d', rtrim($showFolder, '/'), $seasonNum);
        $showName = $series ? $series->title : $seriesTitle;
        $epCode = sprintf('S%02dE%02d', $seasonNum, $episodeNum);
        $cleanEpTitle = $this->organizerService->sanitizePathSegment($episodeTitle);
        $resTag = "[{$resolution}]";

        $fileName = "{$showName} - {$epCode} - {$cleanEpTitle} {$resTag}.{$ext}";

        return "{$seasonFolder}/{$fileName}";
    }

    /**
     * Build Movie destination: H:/Entertainment/Movies/{Genre}/{Collection}/{Title} ({Year})/{Title} ({Year}).{ext}
     */
    protected function buildMovieDestination(string $sourcePath, string $targetRoot, string $ext, array $metadata): string
    {
        $movieTitle = trim($metadata['movie_title'] ?? ($metadata['title'] ?? pathinfo($sourcePath, PATHINFO_FILENAME)));
        $year = (int) ($metadata['release_year'] ?? ($metadata['year'] ?? 0));
        $genre = trim($metadata['genre'] ?? '');
        $collectionName = trim($metadata['collection_name'] ?? '');

        // If title needs parsing from filename
        if (empty($metadata['movie_title']) && empty($metadata['title'])) {
            $parsed = $this->parserService->parse($sourcePath);
            $movieTitle = ! empty($parsed['title']) ? $parsed['title'] : $movieTitle;
            $year = $year ?: ($parsed['year'] ?? 0);
        }

        // If collectionName is empty, attempt inference
        if (empty($collectionName)) {
            $collectionName = TmdbProvider::inferCollectionFromTitle($movieTitle) ?? '';
        }
        if (empty($collectionName)) {
            $masterMovie = $this->masterIndexService->lookupMovie($movieTitle, $year);
            if (! empty($masterMovie['collection_name'])) {
                $collectionName = $masterMovie['collection_name'];
            }
        }

        // Determine genre if missing
        if (empty($genre)) {
            $existing = MediaItem::where('title', $movieTitle)->with('genres')->first();
            if ($existing && $existing->genres->isNotEmpty()) {
                $genre = $existing->genres->first()->name;
            } elseif (! empty($masterMovie['genres'][0])) {
                $genre = $masterMovie['genres'][0];
            } else {
                $genre = 'Action';
            }
        }

        // If collection exists, ensure canonical folder naming '{Collection Name} Collection' or existing folder
        $collectionFolder = '';
        if (! empty($collectionName)) {
            // Find existing movies in this collection
            $existing = MediaItem::where('collection_name', $collectionName)->whereNotNull('file_path')->first();
            if ($existing && $existing->file_path) {
                $normExisting = str_replace('\\', '/', $existing->file_path);
                // Parent of parent is collection folder
                $collectionFolder = dirname(dirname($normExisting));
            }

            if (empty($collectionFolder)) {
                $cleanCol = $this->organizerService->sanitizePathSegment($collectionName);
                if (! str_ends_with(strtolower($cleanCol), 'collection') && ! str_ends_with(strtolower($cleanCol), 'trilogy') && ! str_ends_with(strtolower($cleanCol), 'saga')) {
                    $cleanCol .= ' Collection';
                }
                $collectionFolder = "{$targetRoot}/{$genre}/{$cleanCol}";
            }
        }

        $cleanTitle = $this->organizerService->sanitizePathSegment($movieTitle);
        $yearTag = $year > 0 ? " ({$year})" : '';
        $movieDirName = "{$cleanTitle}{$yearTag}";

        if (! empty($collectionFolder)) {
            $finalDir = "{$collectionFolder}/{$movieDirName}";
        } else {
            $finalDir = "{$targetRoot}/{$genre}/{$movieDirName}";
        }

        $fileName = "{$cleanTitle}{$yearTag}.{$ext}";

        return "{$finalDir}/{$fileName}";
    }

    /**
     * Move companion subtitles (e.g. `.ar.srt`, `.en.srt`) alongside video.
     */
    protected function moveCompanionSubtitles(string $sourceVideoPath, string $destVideoPath): void
    {
        $dir = dirname($sourceVideoPath);
        $baseName = pathinfo($sourceVideoPath, PATHINFO_FILENAME);
        $destDir = dirname($destVideoPath);
        $destBase = pathinfo($destVideoPath, PATHINFO_FILENAME);

        $files = glob("{$dir}/{$baseName}*.srt");
        foreach ($files ?: [] as $subFile) {
            $subFilename = basename($subFile);
            $suffix = substr($subFilename, strlen($baseName)); // e.g. .ar.srt or .srt
            $destSubPath = "{$destDir}/{$destBase}{$suffix}";
            try {
                $this->crossDriveMove($subFile, $destSubPath);
            } catch (\Throwable $e) {
                Log::warning("Could not move subtitle {$subFile}: ".$e->getMessage());
            }
        }
    }

    /**
     * Resolve target root path for Movies or TV Shows.
     */
    protected function resolveTargetRoot(string $mediaType): string
    {
        $monitored = AppSetting::get('scanner_monitored_directories');
        if ($monitored) {
            $dirs = is_array($monitored) ? $monitored : json_decode($monitored, true);
            foreach ($dirs ?: [] as $d) {
                if (($d['type'] ?? '') === ($mediaType === 'series' ? 'series' : 'movies')) {
                    if (! empty($d['path']) && File::isDirectory($d['path'])) {
                        return $d['path'];
                    }
                }
            }
        }

        // Fallbacks matching user's drive
        if ($mediaType === 'series') {
            return File::isDirectory('H:/Entertainment/TV Shows') ? 'H:/Entertainment/TV Shows' : 'D:/Media/Series';
        }

        return File::isDirectory('H:/Entertainment/Movies') ? 'H:/Entertainment/Movies' : 'D:/Media/Movies';
    }

    /**
     * Move file safely across different drives / mount points.
     */
    protected function crossDriveMove(string $source, string $destination): void
    {
        try {
            File::move($source, $destination);

            return;
        } catch (\Throwable $e) {
            // Fallback for cross-device move
        }

        $in = fopen($source, 'rb');
        $out = fopen($destination, 'wb');
        if (! $in || ! $out) {
            if ($in) {
                fclose($in);
            }
            if ($out) {
                fclose($out);
            }
            throw new \RuntimeException("Failed to open file streams to move {$source} to {$destination}");
        }

        while (! feof($in)) {
            $buffer = fread($in, 8388608); // 8MB
            if ($buffer !== false) {
                fwrite($out, $buffer);
            }
        }
        fclose($in);
        fclose($out);

        if (filesize($source) === filesize($destination)) {
            @unlink($source);
        } else {
            @unlink($destination);
            throw new \RuntimeException('Cross-drive move verification failed: file size mismatch');
        }
    }
}
