<?php

namespace App\Services\Scout;

use App\Http\Controllers\CollectionController;
use App\Models\AppSetting;
use App\Models\DownloadItem;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Series;
use App\Services\Downloader\Aria2Service;
use App\Services\Metadata\LibraryMasterIndexService;
use App\Services\Metadata\TmdbProvider;
use App\Services\Organizer\PhysicalOrganizerService;
use App\Services\Organizer\SceneNameParserService;
use App\Services\Organizer\ZeroKeyGenreClassifierService;
use App\Services\Scanner\VirtualLibraryScannerService;
use App\Services\Subtitles\SubtitleLanguageDetectorService;
use App\Services\Subtitles\SubtitleManagerService;
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

            // Invalidate scout cache and collection cache so gaps and collections update immediately
            $this->gapService->clearCache();
            CollectionController::clearCache();

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

        // 1. Gather all active downloading paths from other tasks to protect them
        $activeDownloadingPaths = DownloadItem::where('status', 'downloading')
            ->where('id', '!=', $item->id)
            ->pluck('destination_path')
            ->filter()
            ->map(fn ($p) => str_replace('\\', '/', $p))
            ->toArray();

        // 2. Discover exact files belonging ONLY to this specific completed item
        // A) If Aria2 GID is present, query Aria2 for exact files
        if ($item->aria2_gid && app(Aria2Service::class)->isAvailable()) {
            $status = app(Aria2Service::class)->tellStatus($item->aria2_gid);
            if (! empty($status['files'])) {
                foreach ($status['files'] as $f) {
                    $p = str_replace('\\', '/', $f['path'] ?? '');
                    if ($p && File::isFile($p) && in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), $validExts)) {
                        $mediaFiles[] = $p;
                    }
                }
            }
        }

        // B) If item has direct destination_path to a file
        if (empty($mediaFiles) && $item->destination_path && File::isFile($item->destination_path)) {
            $mediaFiles[] = str_replace('\\', '/', $item->destination_path);
        }

        // C) If destination_path is a dedicated subdirectory (NEVER scan parent destination_folder D:/Media/Movies!)
        if (empty($mediaFiles) && $item->destination_path && File::isDirectory($item->destination_path)) {
            $subFolder = str_replace('\\', '/', $item->destination_path);
            $destFolder = str_replace('\\', '/', $item->destination_folder);
            // Ensure destination_path is a sub-folder and not the shared root movies folder itself
            if (rtrim($subFolder, '/') !== rtrim($destFolder, '/')) {
                $sourceDir = $subFolder;
                $discovered = File::allFiles($subFolder);
                foreach ($discovered as $f) {
                    if (in_array(strtolower($f->getExtension()), $validExts)) {
                        $filename = strtolower($f->getFilename());
                        if (str_contains($filename, 'sample') && $f->getSize() < 50 * 1024 * 1024) {
                            continue;
                        }
                        $mediaFiles[] = str_replace('\\', '/', $f->getPathname());
                    }
                }
            }
        }

        // Filter out any file that belongs to another active downloading item
        $mediaFiles = array_values(array_filter(array_unique($mediaFiles), function ($path) use ($activeDownloadingPaths) {
            $norm = str_replace('\\', '/', $path);
            foreach ($activeDownloadingPaths as $active) {
                if ($norm === $active || str_starts_with($norm, rtrim($active, '/').'/')) {
                    Log::warning("LibraryAcquisitionService: Skipping {$norm} because it belongs to an active downloading task.");

                    return false;
                }
            }

            return true;
        }));

        if (empty($mediaFiles)) {
            return [
                'success' => false,
                'error' => 'No isolated media files found for completed download task',
            ];
        }

        $results = [];
        $hasErrors = false;

        foreach ($mediaFiles as $filePath) {
            $parsed = $this->parserService->parse($filePath);

            // Check if this specific file is an episode or a movie
            $fileType = $parsed['type'] ?? 'movie';
            $isExplicitSeries = ($item->media_type === 'series' && (($item->torrent_files['season_number'] ?? null) !== null || ($parsed['season'] ?? null) !== null));
            $mediaType = ($fileType === 'series' || $isExplicitSeries) ? 'series' : 'movie';

            $meta = array_merge($item->torrent_files ?? [], [
                'movie_title' => $parsed['title'] ?? ($parsed['clean_title'] ?? null),
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

        if (empty($sourceDir) && ! empty($mediaFiles)) {
            $firstDir = dirname($mediaFiles[0]);
            $destFolder = str_replace('\\', '/', $item->destination_folder ?: 'D:/Media/Movies');
            if ($firstDir && rtrim(str_replace('\\', '/', $firstDir), '/') !== rtrim($destFolder, '/') && File::isDirectory($firstDir)) {
                $sourceDir = $firstDir;
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

        // Extract base library root (e.g. H:/Entertainment from H:/Entertainment/Movies)
        $organizerRoot = $targetRoot;
        if (str_ends_with(strtolower($organizerRoot), '/movies') || str_ends_with(strtolower($organizerRoot), '/tv shows')) {
            $organizerRoot = dirname($organizerRoot);
        }

        $parsed = $this->parserService->parse($sourcePath);
        if ($mediaType === 'series') {
            $parsed['type'] = 'series';
        }
        $fileItem = [
            'path' => $sourcePath,
            'filename' => basename($sourcePath),
            'size' => file_exists($sourcePath) ? filesize($sourcePath) : 0,
            'parsed' => $parsed,
            'subtitles' => [],
        ];

        $planItem = $this->organizerService->generatePlanItem($fileItem, $organizerRoot);

        if (! empty($planItem['destination_path'])) {
            return str_replace('\\', '/', $planItem['destination_path']);
        }

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
            $parsed = $this->parserService->parse($sourcePath);
            $collectionName = $this->organizerService->resolveCollectionName($sourcePath, $parsed) ?? '';
        }
        if (empty($collectionName)) {
            $collectionName = TmdbProvider::inferCollectionFromTitle($movieTitle) ?? '';
        }
        if (empty($collectionName)) {
            $masterMovie = $this->masterIndexService->lookupMovie($movieTitle, $year);
            if (! empty($masterMovie['collection_name'])) {
                $collectionName = $masterMovie['collection_name'];
            }
        }

        // Determine dominant canonical genre (Arabic > Indian > Animation > Canonical TMDB Genre)
        $classifier = app(ZeroKeyGenreClassifierService::class);
        $existingGenres = [];
        $existing = MediaItem::where('title', $movieTitle)->with('genres')->first();
        if ($existing && $existing->genres->isNotEmpty()) {
            $existingGenres = $existing->genres->pluck('name_en')->toArray();
        } elseif (! empty($masterMovie['genres'])) {
            $existingGenres = $masterMovie['genres'];
        } elseif (! empty($genre)) {
            $existingGenres = [$genre];
        }

        $genre = $classifier->resolveDominantGenre($movieTitle, $collectionName, $existingGenres, $metadata);

        // If collection exists, place it under the dominant canonical genre folder
        $collectionFolder = '';
        if (! empty($collectionName)) {
            $cleanCol = $this->organizerService->sanitizePathSegment($collectionName);
            if (! str_ends_with(strtolower($cleanCol), 'collection') && ! str_ends_with(strtolower($cleanCol), 'trilogy') && ! str_ends_with(strtolower($cleanCol), 'saga')) {
                $cleanCol .= ' Collection';
            }
            $collectionFolder = "{$targetRoot}/{$genre}/{$cleanCol}";
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
     * Move companion subtitles (e.g. `.ar.srt`, `.en.srt`) alongside video without (1)/(2) duplication.
     * Supports subtitles in the same directory or within Subs/ / Subtitles/ subfolders.
     * Accurately detects language from filename or content analysis via SubtitleLanguageDetectorService.
     */
    protected function moveCompanionSubtitles(string $sourceVideoPath, string $destVideoPath): void
    {
        $dir = dirname($sourceVideoPath);
        $baseName = pathinfo($sourceVideoPath, PATHINFO_FILENAME);
        $destDir = dirname($destVideoPath);
        $destBase = pathinfo($destVideoPath, PATHINFO_FILENAME);

        $subtitleDetector = app(SubtitleLanguageDetectorService::class);
        $validSubExts = ['srt', 'vtt', 'ass', 'ssa', 'sub'];

        // 1. Collect all candidate subtitle files from immediate folder and Subs/ / Subtitles/ subfolders
        $candidateFiles = [];
        if (File::isDirectory($dir)) {
            // Direct folder
            foreach (File::files($dir) as $f) {
                if (in_array(strtolower($f->getExtension()), $validSubExts, true)) {
                    $candidateFiles[] = str_replace('\\', '/', $f->getPathname());
                }
            }

            // Common subfolders like Subs, Subtitles, Sub
            foreach (File::directories($dir) as $subDir) {
                $subDirName = strtolower(basename($subDir));
                if (in_array($subDirName, ['subs', 'subtitles', 'sub'], true)) {
                    foreach (File::allFiles($subDir) as $f) {
                        if (in_array(strtolower($f->getExtension()), $validSubExts, true)) {
                            $candidateFiles[] = str_replace('\\', '/', $f->getPathname());
                        }
                    }
                }
            }
        }

        // 2. Count video files in source directory to know if this is a single movie or multi-episode torrent
        $videoExts = ['mp4', 'mkv', 'avi', 'mov', 'webm', 'm4v', 'ts'];
        $videoCount = 0;
        if (File::isDirectory($dir)) {
            foreach (File::files($dir) as $f) {
                if (in_array(strtolower($f->getExtension()), $videoExts, true)) {
                    $videoCount++;
                }
            }
        }

        // 3. Filter candidate subtitles that belong to this specific video file
        $matchedSubtitles = [];
        $cleanVideoBase = strtolower(preg_replace('/[^a-z0-9]/i', '', $baseName));
        preg_match('/[sS](\d{1,2})[eE](\d{1,2})|\b(\d{1,2})x(\d{1,2})\b/i', $baseName, $epMatch);
        $epCode = ! empty($epMatch[0]) ? strtolower($epMatch[0]) : null;

        foreach ($candidateFiles as $subFile) {
            $subBase = pathinfo($subFile, PATHINFO_FILENAME);
            $cleanSubBase = strtolower(preg_replace('/[^a-z0-9]/i', '', $subBase));

            $matches = false;

            if ($epCode) {
                // For TV episodes, match by episode code (e.g. S01E02)
                if (str_contains(strtolower($subBase), $epCode)) {
                    $matches = true;
                }
            } elseif ($videoCount <= 1) {
                // For single video releases (movies), any subtitle in the release or Subs/ belongs to it
                $matches = true;
            } else {
                // Multi-video: stem match
                if (str_starts_with($cleanSubBase, $cleanVideoBase) || str_starts_with($cleanVideoBase, $cleanSubBase)) {
                    $matches = true;
                }
            }

            if ($matches) {
                $matchedSubtitles[] = $subFile;
            }
        }

        $seenLangs = [];

        foreach ($matchedSubtitles as $subFile) {
            $subFilename = basename($subFile);
            $subExt = strtolower(pathinfo($subFile, PATHINFO_EXTENSION));

            // Detect language accurately using SubtitleLanguageDetectorService (content + filename analysis)
            $detection = $subtitleDetector->detectLanguage($subFile, $subFilename);
            $langCode = $detection['language'] ?? 'und';

            if ($langCode === 'und') {
                $langCode = 'en';
            }

            // Standardize format to .srt unless it's vtt
            $targetExt = in_array($subExt, ['vtt', 'srt'], true) ? $subExt : 'srt';
            $destSubPath = "{$destDir}/{$destBase}.{$langCode}.{$targetExt}";

            // If we already moved a subtitle for this language, avoid duplicate clutter
            if (isset($seenLangs[$langCode])) {
                @unlink($subFile);

                continue;
            }

            try {
                if (File::exists($destSubPath)) {
                    @File::delete($destSubPath);
                }

                // If ASS / SSA format, convert to standard SRT
                if (in_array($subExt, ['ass', 'ssa'], true)) {
                    $raw = File::get($subFile);
                    $converted = app(SubtitleManagerService::class)->convertAssOrSsaToSrt($raw);
                    File::put($destSubPath, $converted);
                    @unlink($subFile);
                } else {
                    $this->crossDriveMove($subFile, $destSubPath);
                }

                $seenLangs[$langCode] = true;
                Log::info("LibraryAcquisitionService: Moved companion subtitle [{$langCode}] {$subFilename} -> {$destSubPath}");
            } catch (\Throwable $e) {
                Log::warning("Could not move subtitle {$subFile}: ".$e->getMessage());
            }
        }

        // Clean up empty Subs/ directories if any were used
        if (File::isDirectory($dir)) {
            foreach (File::directories($dir) as $subDir) {
                $subDirName = strtolower(basename($subDir));
                if (in_array($subDirName, ['subs', 'subtitles', 'sub'], true)) {
                    if (empty(File::allFiles($subDir))) {
                        @File::deleteDirectory($subDir);
                    }
                }
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
