<?php

namespace App\Services\Scout;

use App\Models\AppSetting;
use App\Models\DownloadItem;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Series;
use App\Services\Metadata\TmdbProvider;
use App\Services\Organizer\PhysicalOrganizerService;
use App\Services\Scanner\VirtualLibraryScannerService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LibraryAcquisitionService
{
    public function __construct(
        protected PhysicalOrganizerService $organizerService,
        protected VirtualLibraryScannerService $scannerService,
        protected TmdbProvider $tmdbProvider,
        protected LibraryGapService $gapService
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
            File::move($sourceFilePath, $destPath);

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
            Log::error("LibraryAcquisitionService organizeAndScanFile failed: " . $e->getMessage(), [
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
    public function handleCompletedDownload(DownloadItem $item): array
    {
        $filePath = $item->destination_path;
        if (! $filePath || ! File::exists($filePath)) {
            // Try searching target folder
            $folder = $item->destination_folder;
            if ($folder && File::isDirectory($folder)) {
                $files = File::files($folder);
                foreach ($files as $f) {
                    if (in_array(strtolower($f->getExtension()), ['mp4', 'mkv', 'avi', 'mov', 'webm'])) {
                        $filePath = $f->getPathname();
                        break;
                    }
                }
            }
        }

        if (! $filePath || ! File::exists($filePath)) {
            return ['success' => false, 'error' => 'No media file found for completed download'];
        }

        $meta = $item->torrent_files ?? [];
        return $this->organizeAndScanFile($filePath, $item->media_type ?: 'movie', $meta);
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
        $seasonNum = (int) ($metadata['season_number'] ?? 1);
        $episodeNum = (int) ($metadata['episode_number'] ?? 1);
        $episodeTitle = trim($metadata['episode_title'] ?? "Episode {$episodeNum}");
        $resolution = trim($metadata['resolution'] ?? '1080p');

        // Look for existing series in DB
        $series = null;
        if (! empty($metadata['series_id'])) {
            $series = Series::find($metadata['series_id']);
        } elseif (! empty($seriesTitle)) {
            $series = Series::where('title', $seriesTitle)->first();
        }

        $showFolder = '';
        if ($series && ! empty($series->folder_path) && File::isDirectory($series->folder_path)) {
            $showFolder = str_replace('\\', '/', $series->folder_path);
        } else {
            $yearStr = '';
            if ($series && $series->release_year) {
                $end = $series->end_year ?: ($series->status === 'Ended' ? $series->release_year : '');
                $yearStr = " ({$series->release_year}" . ($end && $end != $series->release_year ? " - {$end}" : '') . ")";
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
        $genre = trim($metadata['genre'] ?? 'Action');
        $collectionName = trim($metadata['collection_name'] ?? '');

        // If collection exists, check if existing collection folder is already on disk
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
                File::move($subFile, $destSubPath);
            } catch (\Throwable $e) {
                Log::warning("Could not move subtitle {$subFile}: " . $e->getMessage());
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
}