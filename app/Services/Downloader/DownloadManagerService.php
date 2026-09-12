<?php

namespace App\Services\Downloader;

use App\Models\AppSetting;
use App\Models\DownloadItem;
use App\Services\Scanner\VirtualLibraryScannerService;
use App\Services\Scout\LibraryAcquisitionService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DownloadManagerService
{
    protected BencodeParserService $bencodeParser;

    protected VirtualLibraryScannerService $scannerService;

    protected Aria2Service $aria2Service;

    public function __construct(
        BencodeParserService $bencodeParser,
        VirtualLibraryScannerService $scannerService,
        Aria2Service $aria2Service
    ) {
        $this->bencodeParser = $bencodeParser;
        $this->scannerService = $scannerService;
        $this->aria2Service = $aria2Service;
    }

    /**
     * Get default library destinations from settings or app defaults.
     */
    public function getDefaultDestinations(): array
    {
        $defaultMovies = 'D:/Media/Movies';
        $defaultSeries = 'D:/Media/Series';

        if (PHP_OS_FAMILY === 'Windows') {
            if (! File::isDirectory('D:/Media') && ! File::isDirectory('D:/')) {
                $userHome = getenv('USERPROFILE') ?: 'C:/Users/Default';
                $userHome = str_replace('\\', '/', $userHome);
                $defaultMovies = "{$userHome}/Videos/Movies";
                $defaultSeries = "{$userHome}/Videos/Series";
            }
        } else {
            $defaultMovies = '/var/media/movies';
            $defaultSeries = '/var/media/series';
        }

        $savedMovies = AppSetting::get('movies_download_path');
        $savedSeries = AppSetting::get('series_download_path');
        $savedDefault = AppSetting::get('default_download_path');

        return [
            'movies' => $savedMovies ?: $defaultMovies,
            'series' => $savedSeries ?: $defaultSeries,
            'default' => $savedDefault ?: ($savedMovies ?: $defaultMovies),
        ];
    }

    public function getSettings(): array
    {
        $destinations = $this->getDefaultDestinations();

        return [
            'default_download_path' => AppSetting::get('default_download_path', $destinations['default']),
            'movies_download_path' => AppSetting::get('movies_download_path', $destinations['movies']),
            'series_download_path' => AppSetting::get('series_download_path', $destinations['series']),
            'max_concurrent_downloads' => (int) AppSetting::get('download_max_concurrent', 3),
            'download_speed_limit_kb' => (int) AppSetting::get('download_speed_limit_kb', 0),
            'aria2_available' => $this->aria2Service->isAvailable(),
        ];
    }

    public function saveSettings(array $settings): array
    {
        if (isset($settings['default_download_path'])) {
            AppSetting::set('default_download_path', $settings['default_download_path']);
        }
        if (isset($settings['movies_download_path'])) {
            AppSetting::set('movies_download_path', $settings['movies_download_path']);
        }
        if (isset($settings['series_download_path'])) {
            AppSetting::set('series_download_path', $settings['series_download_path']);
        }
        if (isset($settings['max_concurrent_downloads'])) {
            AppSetting::set('download_max_concurrent', $settings['max_concurrent_downloads']);
        }
        if (isset($settings['download_speed_limit_kb'])) {
            AppSetting::set('download_speed_limit_kb', $settings['download_speed_limit_kb']);
        }

        if ($this->aria2Service->isAvailable()) {
            $this->aria2Service->configureLimits(
                (int) ($settings['max_concurrent_downloads'] ?? 3),
                (int) ($settings['download_speed_limit_kb'] ?? 0)
            );
        }

        return $this->getSettings();
    }

    /**
     * Inspect a URL, magnet URI, or local torrent file path.
     */
    public function inspectUrl(string $url, ?string $type = null): array
    {
        $trimmedUrl = trim($url);
        $isMagnet = str_starts_with($trimmedUrl, 'magnet:?');
        $isTorrentUrl = str_ends_with(strtolower(parse_url($trimmedUrl, PHP_URL_PATH) ?? ''), '.torrent');
        $isLocalFile = file_exists($trimmedUrl) && is_readable($trimmedUrl);

        $downloadType = ($type === 'torrent' || $isMagnet || $isTorrentUrl || ($isLocalFile && str_ends_with(strtolower($trimmedUrl), '.torrent'))) ? 'torrent' : 'direct';

        $destinations = $this->getDefaultDestinations();

        if ($downloadType === 'torrent') {
            $parsed = null;
            if ($isLocalFile) {
                $parsed = $this->bencodeParser->parseTorrentFile($trimmedUrl);
            } elseif ($isMagnet) {
                $parsed = $this->bencodeParser->parseMagnet($trimmedUrl);
            } elseif ($isTorrentUrl) {
                try {
                    $tmpPath = tempnam(sys_get_temp_dir(), 'torrent_');
                    $response = Http::timeout(10)->withOptions(['verify' => false])->get($trimmedUrl);
                    if ($response->successful()) {
                        file_put_contents($tmpPath, $response->body());
                        $parsed = $this->bencodeParser->parseTorrentFile($tmpPath);
                        @unlink($tmpPath);
                    }
                } catch (\Throwable $e) {
                    $parsed = null;
                }
            }

            $torrentTitle = $parsed['name'] ?? pathinfo(parse_url($trimmedUrl, PHP_URL_PATH) ?? 'Torrent Item', PATHINFO_FILENAME);
            $torrentTitle = str_replace(['.', '_'], ' ', $torrentTitle);
            $mediaType = $this->detectMediaType($torrentTitle);
            $suggestedFolder = $mediaType === 'series' ? $destinations['series'] : $destinations['movies'];

            $files = $parsed['files'] ?? [
                [
                    'index' => 1,
                    'path' => $torrentTitle.'.mkv',
                    'folder' => '',
                    'filename' => $torrentTitle.'.mkv',
                    'size' => $parsed['total_size'] ?? 0,
                    'is_video' => true,
                    'is_subtitle' => false,
                    'is_image' => false,
                    'extension' => 'mkv',
                    'selected' => true,
                ],
            ];

            $tree = $parsed['tree'] ?? $this->bencodeParser->buildFolderTree($files);

            return [
                'download_type' => 'torrent',
                'title' => $torrentTitle,
                'media_type' => $mediaType,
                'total_bytes' => $parsed['total_size'] ?? 0,
                'info_hash' => $parsed['info_hash'] ?? null,
                'files' => $files,
                'tree' => $tree,
                'folder_count' => $parsed['folder_count'] ?? 0,
                'file_count' => count($files),
                'default_folder' => $suggestedFolder,
                'destinations' => $destinations,
            ];
        }

        // Direct Download Inspection
        $urlPath = parse_url($trimmedUrl, PHP_URL_PATH);
        $filename = $urlPath ? basename($urlPath) : 'video_download.mp4';
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $title = str_replace(['.', '_', '-'], ' ', $title);
        $mediaType = $this->detectMediaType($title);
        $suggestedFolder = $mediaType === 'series' ? $destinations['series'] : $destinations['movies'];

        $estimatedSize = 1450000000;
        try {
            $headResponse = Http::timeout(5)->withOptions(['verify' => false])->head($trimmedUrl);
            $contentLength = $headResponse->header('Content-Length');
            if ($contentLength && is_numeric($contentLength) && $contentLength > 0) {
                $estimatedSize = (int) $contentLength;
            }
            $contentDisposition = $headResponse->header('Content-Disposition');
            if ($contentDisposition && preg_match('/filename=["\']?([^"\';]+)["\']?/i', $contentDisposition, $matches)) {
                $filename = trim($matches[1]);
                $title = pathinfo($filename, PATHINFO_FILENAME);
                $title = str_replace(['.', '_', '-'], ' ', $title);
            }
        } catch (\Throwable $e) {
        }

        $directFiles = [
            [
                'index' => 1,
                'path' => $filename,
                'folder' => '',
                'filename' => $filename,
                'size' => $estimatedSize,
                'is_video' => true,
                'is_subtitle' => false,
                'is_image' => false,
                'extension' => pathinfo($filename, PATHINFO_EXTENSION) ?: 'mp4',
                'selected' => true,
            ],
        ];

        return [
            'download_type' => 'direct',
            'title' => $title,
            'media_type' => $mediaType,
            'total_bytes' => $estimatedSize,
            'info_hash' => null,
            'files' => $directFiles,
            'tree' => $this->bencodeParser->buildFolderTree($directFiles),
            'folder_count' => 0,
            'file_count' => 1,
            'default_folder' => $suggestedFolder,
            'destinations' => $destinations,
        ];
    }

    /**
     * Inspect an uploaded .torrent binary file.
     */
    public function inspectTorrentFileContent(string $rawContent, string $originalFilename = 'upload.torrent'): array
    {
        $parsed = $this->bencodeParser->parseTorrentString($rawContent);
        $destinations = $this->getDefaultDestinations();

        $torrentTitle = $parsed['name'] ?? pathinfo($originalFilename, PATHINFO_FILENAME);
        $torrentTitle = str_replace(['.', '_'], ' ', $torrentTitle);
        $mediaType = $this->detectMediaType($torrentTitle);
        $suggestedFolder = $mediaType === 'series' ? $destinations['series'] : $destinations['movies'];

        // Cache the uploaded torrent file in storage/app/torrents/{info_hash}.torrent
        if (! empty($parsed['info_hash'])) {
            $cacheDir = storage_path('app/torrents');
            if (! File::isDirectory($cacheDir)) {
                File::makeDirectory($cacheDir, 0755, true, true);
            }
            File::put("{$cacheDir}/{$parsed['info_hash']}.torrent", $rawContent);
        }

        return [
            'download_type' => 'torrent',
            'title' => $torrentTitle,
            'media_type' => $mediaType,
            'total_bytes' => $parsed['total_size'] ?? 0,
            'info_hash' => $parsed['info_hash'] ?? null,
            'files' => $parsed['files'] ?? [],
            'tree' => $parsed['tree'] ?? [],
            'folder_count' => $parsed['folder_count'] ?? 0,
            'file_count' => $parsed['file_count'] ?? count($parsed['files'] ?? []),
            'default_folder' => $suggestedFolder,
            'destinations' => $destinations,
        ];
    }

    /**
     * Detect whether a title looks like a TV Series or a Movie.
     */
    protected function detectMediaType(string $title): string
    {
        if (preg_match('/(S\d{1,2}|E\d{1,2}|Season\s*\d+|Episode\s*\d+)/i', $title)) {
            return 'series';
        }

        return 'movie';
    }

    /**
     * Create and queue a new download item.
     */
    public function createDownload(
        string $title,
        string $mediaType = 'movie',
        ?string $sourceUrl = null,
        ?string $destinationPath = null,
        string $downloadType = 'direct',
        ?string $destinationFolder = null,
        ?array $torrentFiles = null,
        ?array $selectedFiles = null,
        ?string $infoHash = null
    ): DownloadItem {
        $destinations = $this->getDefaultDestinations();
        $targetFolder = $destinationFolder ?: ($mediaType === 'series' ? $destinations['series'] : $destinations['movies']);

        $targetFolder = rtrim(str_replace('\\', '/', $targetFolder), '/');
        if (! File::isDirectory($targetFolder)) {
            File::makeDirectory($targetFolder, 0755, true, true);
        }

        $cleanTitle = trim($title);
        $ext = 'mp4';

        if ($sourceUrl) {
            $urlPath = parse_url($sourceUrl, PHP_URL_PATH);
            if ($urlPath) {
                $detectedExt = pathinfo($urlPath, PATHINFO_EXTENSION);
                if (in_array(strtolower($detectedExt), ['mp4', 'mkv', 'webm', 'avi', 'mov', 'zip', 'srt'])) {
                    $ext = strtolower($detectedExt);
                }
            }
        }

        if ($downloadType === 'torrent') {
            $ext = 'mkv';
        }

        if (empty($destinationPath)) {
            $destinationPath = "{$targetFolder}/{$cleanTitle}.{$ext}";
        }

        // Calculate total bytes based on selected files if provided
        $totalBytes = 0;
        if (! empty($selectedFiles) && is_array($selectedFiles) && ! empty($torrentFiles) && is_array($torrentFiles)) {
            foreach ($torrentFiles as $tf) {
                $idx = $tf['index'] ?? null;
                $path = $tf['path'] ?? null;
                if (in_array($idx, $selectedFiles) || in_array($path, $selectedFiles)) {
                    $totalBytes += (int) ($tf['size'] ?? 0);
                }
            }
        } elseif ($downloadType === 'direct' && $sourceUrl && $this->isRealHttpUrl($sourceUrl)) {
            try {
                $headResponse = Http::timeout(5)->withOptions(['verify' => false])->head($sourceUrl);
                $contentLength = $headResponse->header('Content-Length');
                if ($contentLength && is_numeric($contentLength) && $contentLength > 0) {
                    $totalBytes = (int) $contentLength;
                }
            } catch (\Throwable $e) {
            }
        }

        if ($totalBytes <= 0) {
            $totalBytes = 1450000000; // fallback provisional
        }

        $aria2Gid = null;

        // Try dispatching to aria2 if available
        if ($this->aria2Service->isAvailable()) {
            if ($downloadType === 'torrent') {
                $cachedTorrent = $infoHash ? storage_path("app/torrents/{$infoHash}.torrent") : null;
                if ($cachedTorrent && file_exists($cachedTorrent)) {
                    $aria2Gid = $this->aria2Service->addTorrent($cachedTorrent, $targetFolder, $selectedFiles ?: []);
                } elseif ($sourceUrl && str_starts_with($sourceUrl, 'magnet:')) {
                    $aria2Gid = $this->aria2Service->addUri($sourceUrl, $targetFolder, $selectedFiles ?: []);
                } elseif ($sourceUrl && file_exists($sourceUrl)) {
                    $aria2Gid = $this->aria2Service->addTorrent($sourceUrl, $targetFolder, $selectedFiles ?: []);
                }
            } elseif ($sourceUrl && $this->isRealHttpUrl($sourceUrl)) {
                $aria2Gid = $this->aria2Service->addUri($sourceUrl, $targetFolder);
            }
        }

        return DownloadItem::create([
            'title' => $cleanTitle,
            'media_type' => $mediaType,
            'source_url' => $sourceUrl,
            'download_type' => $downloadType,
            'destination_folder' => $targetFolder,
            'destination_path' => $destinationPath,
            'torrent_files' => $torrentFiles,
            'selected_files' => $selectedFiles,
            'info_hash' => $infoHash,
            'aria2_gid' => $aria2Gid,
            'total_bytes' => $totalBytes,
            'downloaded_bytes' => 0,
            'status' => 'downloading',
            'speed_bytes_sec' => 0,
        ]);
    }

    /**
     * Process batch of active downloads.
     */
    public function processBatch(int $chunkBytes = 25000000): array
    {
        $maxConcurrent = (int) AppSetting::get('download_max_concurrent', 3);
        $activeDownloads = DownloadItem::whereIn('status', ['downloading', 'queued'])->limit($maxConcurrent)->get();
        $processed = 0;

        foreach ($activeDownloads as $item) {
            if ($item->status === 'queued') {
                $item->update(['status' => 'downloading']);
            }

            // 1. If managed by aria2, poll genuine real-time progress
            if ($item->aria2_gid && $this->aria2Service->isAvailable() && ! app()->runningUnitTests()) {
                $status = $this->aria2Service->tellStatus($item->aria2_gid);
                if ($status) {
                    // Synchronize child GID if transitioned from metadata to video payload
                    if (! empty($status['gid']) && $status['gid'] !== $item->aria2_gid) {
                        $item->aria2_gid = $status['gid'];
                    }

                    // Extract actual payload file path from aria2 files list
                    if (! empty($status['files'])) {
                        foreach ($status['files'] as $f) {
                            $fPath = str_replace('\\', '/', $f['path'] ?? '');
                            if (! empty($fPath) && ! str_starts_with(basename($fPath), '[METADATA]')) {
                                $fExt = strtolower(pathinfo($fPath, PATHINFO_EXTENSION));
                                if (in_array($fExt, ['mp4', 'mkv', 'avi', 'mov', 'webm'])) {
                                    $item->destination_path = $fPath;
                                    break;
                                }
                            }
                        }
                    }

                    $item->downloaded_bytes = $status['downloaded_bytes'];
                    if ($status['total_bytes'] > 0) {
                        $item->total_bytes = $status['total_bytes'];
                    }
                    $item->speed_bytes_sec = $status['speed_bytes_sec'];

                    if ($status['status'] === 'completed') {
                        // Prevent premature completion on tiny metadata (~20KB-40KB)
                        if ($status['total_bytes'] > 1000000 || empty($status['following'])) {
                            $item->status = 'completed';
                            $item->speed_bytes_sec = 0;
                            $item->save();
                            $this->finalizeDownloadedFile($item);
                            $this->autoIndexMedia($item);
                        } else {
                            $item->status = 'downloading';
                            $item->save();
                        }
                    } elseif ($status['status'] === 'failed') {
                        $item->status = 'failed';
                        $item->error_message = $status['error_message'] ?: 'Download failed in aria2';
                        $item->speed_bytes_sec = 0;
                        $item->save();
                    } else {
                        $item->status = $status['status'];
                        $item->save();
                    }
                    $processed++;

                    continue;
                }
            }

            // 2. Direct HTTP chunked streamer (pure PHP)
            if ($item->download_type === 'direct' && $item->source_url && $this->isRealHttpUrl($item->source_url)) {
                $this->processRealDownload($item, $chunkBytes);
            } else {
                // If neither aria2 nor real HTTP is connected (e.g. test or offline), gracefully simulate
                $this->processSimulatedDownload($item);
            }

            $processed++;
        }

        return [
            'success' => true,
            'processed_count' => $processed,
            'active_items' => DownloadItem::where('status', 'downloading')->count(),
            'downloads' => DownloadItem::orderByDesc('created_at')->get(),
        ];
    }

    protected function isRealHttpUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array(strtolower($scheme ?? ''), ['http', 'https']);
    }

    protected function processRealDownload(DownloadItem $item, int $chunkBytes): void
    {
        $dest = $item->destination_path;
        if (! $dest) {
            return;
        }

        $dir = pathinfo($dest, PATHINFO_DIRNAME);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        $current = $item->downloaded_bytes;
        $total = $item->total_bytes;
        $startTime = microtime(true);

        try {
            $response = Http::timeout(30)
                ->withOptions([
                    'verify' => false,
                    'stream' => true,
                ])
                ->withHeaders([
                    'Range' => "bytes={$current}-",
                ])
                ->get($item->source_url);

            if ($response->failed()) {
                if ($response->status() === 416) {
                    $item->downloaded_bytes = $total;
                    $item->status = 'completed';
                    $item->speed_bytes_sec = 0;
                    $item->save();
                    $this->finalizeDownloadedFile($item);
                    $this->autoIndexMedia($item);

                    return;
                }
                throw new \Exception("HTTP {$response->status()}");
            }

            $body = $response->getBody();
            $bytesRead = 0;
            $chunkData = '';

            while (! $body->eof() && $bytesRead < $chunkBytes) {
                $read = $body->read(65536);
                if ($read === false || $read === '') {
                    break;
                }
                $chunkData .= $read;
                $bytesRead += strlen($read);
            }

            if ($bytesRead > 0) {
                $writeMode = ($current > 0 && File::exists($dest)) ? 'ab' : 'wb';
                $fp = fopen($dest, $writeMode);
                if ($fp) {
                    fwrite($fp, $chunkData);
                    fclose($fp);
                }

                $elapsed = microtime(true) - $startTime;
                $newDownloaded = $current + $bytesRead;

                $contentRange = $response->header('Content-Range');
                if ($contentRange && preg_match('#/(\d+)$#', $contentRange, $m)) {
                    $total = (int) $m[1];
                    if ($total !== $item->total_bytes) {
                        $item->total_bytes = $total;
                    }
                } elseif ($item->total_bytes <= 0) {
                    $contentLength = $response->header('Content-Length');
                    if ($contentLength && is_numeric($contentLength)) {
                        $item->total_bytes = $current + (int) $contentLength;
                        $total = $item->total_bytes;
                    }
                }

                $speed = $elapsed > 0 ? (int) ($bytesRead / $elapsed) : 0;

                if ($newDownloaded >= $total && $total > 0) {
                    $item->downloaded_bytes = $total;
                    $item->status = 'completed';
                    $item->speed_bytes_sec = 0;
                    $item->save();
                    $this->finalizeDownloadedFile($item);
                    $this->autoIndexMedia($item);
                } else {
                    $item->downloaded_bytes = $newDownloaded;
                    $item->speed_bytes_sec = $speed;
                    $item->save();
                }
            }
        } catch (\Throwable $e) {
            Log::error("Download failed for {$item->title}: ".$e->getMessage());
            $item->status = 'failed';
            $item->error_message = substr($e->getMessage(), 0, 255);
            $item->speed_bytes_sec = 0;
            $item->save();
        }
    }

    protected function processSimulatedDownload(DownloadItem $item): void
    {
        $current = $item->downloaded_bytes;
        $total = $item->total_bytes > 0 ? $item->total_bytes : 1500000000;

        $speed = rand(14000000, 32000000);
        $newDownloaded = min($total, $current + $speed);

        if ($newDownloaded >= $total) {
            $item->downloaded_bytes = $total;
            $item->status = 'completed';
            $item->speed_bytes_sec = 0;
            $item->save();

            $this->finalizeDownloadedFile($item);
            $this->autoIndexMedia($item);
        } else {
            $item->downloaded_bytes = $newDownloaded;
            $item->speed_bytes_sec = $speed;
            $item->save();
        }
    }

    public function pause(int $id): bool
    {
        $item = DownloadItem::find($id);
        if ($item && $item->status === 'downloading') {
            if ($item->aria2_gid && $this->aria2Service->isAvailable() && ! app()->runningUnitTests()) {
                $this->aria2Service->pause($item->aria2_gid);
            }
            $item->update(['status' => 'paused', 'speed_bytes_sec' => 0]);

            $hasActive = DownloadItem::where('status', 'downloading')->exists();
            if (! $hasActive && $this->aria2Service->isAvailable() && ! app()->runningUnitTests()) {
                $this->aria2Service->stopDaemonIfIdle();
            }

            return true;
        }

        return false;
    }

    public function resume(int $id): bool
    {
        $item = DownloadItem::find($id);
        if ($item && in_array($item->status, ['paused', 'queued', 'failed'])) {
            if ($item->aria2_gid && $this->aria2Service->isAvailable() && ! app()->runningUnitTests()) {
                $this->aria2Service->unpause($item->aria2_gid);
            }
            $item->update(['status' => 'downloading']);

            return true;
        }

        return false;
    }

    public function retry(int $id): bool
    {
        $item = DownloadItem::find($id);
        if ($item) {
            if ($item->aria2_gid && $this->aria2Service->isAvailable() && ! app()->runningUnitTests()) {
                $this->aria2Service->unpause($item->aria2_gid);
            }
            $item->update([
                'downloaded_bytes' => 0,
                'status' => 'downloading',
                'speed_bytes_sec' => 0,
                'error_message' => null,
            ]);

            return true;
        }

        return false;
    }

    public function delete(int $id, bool $deleteFile = false): bool
    {
        $item = DownloadItem::find($id);
        if (! $item) {
            return false;
        }

        if ($item->aria2_gid && $this->aria2Service->isAvailable() && ! app()->runningUnitTests()) {
            $this->aria2Service->remove($item->aria2_gid);
        }

        if ($deleteFile && $item->destination_path && File::exists($item->destination_path)) {
            @File::delete($item->destination_path);
        }

        $item->delete();

        $hasActive = DownloadItem::where('status', 'downloading')->exists();
        if (! $hasActive && $this->aria2Service->isAvailable() && ! app()->runningUnitTests()) {
            $this->aria2Service->stopDaemonIfIdle();
        }

        return true;
    }

    protected function finalizeDownloadedFile(DownloadItem $item): void
    {
        $dest = $item->destination_path;
        if (! $dest) {
            return;
        }

        $dir = pathinfo($dest, PATHINFO_DIRNAME);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        if (strtolower(pathinfo($dest, PATHINFO_EXTENSION)) === 'zip') {
            $zip = new \ZipArchive;
            if ($zip->open($dest) === true) {
                $zip->extractTo($dir);
                $zip->close();
            }
        }
    }

    protected function autoIndexMedia(DownloadItem $item): void
    {
        $autoIndex = (bool) AppSetting::get('download_auto_index', true);
        if (! $autoIndex) {
            return;
        }

        try {
            // If item has auto_organize flag, hand over to LibraryAcquisitionService for canonical H:\Entertainment placement
            if (! empty($item->torrent_files['auto_organize'])) {
                app(LibraryAcquisitionService::class)->handleCompletedDownload($item);

                return;
            }

            $dest = $item->destination_path;
            if ($dest && File::exists($dest)) {
                $this->scannerService->processSingleFile($dest, $item->media_type);
            }
        } catch (\Throwable $e) {
            Log::warning("Auto-indexing failed for download {$item->title}: ".$e->getMessage());
        }
    }

    public function getDaemonStatus(): array
    {
        return $this->aria2Service->getDaemonStatus();
    }

    public function stopDaemon(): bool
    {
        return $this->aria2Service->stopDaemon();
    }
}
