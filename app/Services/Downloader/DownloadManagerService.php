<?php

namespace App\Services\Downloader;

use App\Models\AppSetting;
use App\Models\DownloadItem;
use App\Services\Scanner\VirtualLibraryScannerService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DownloadManagerService
{
    public function __construct(
        protected VirtualLibraryScannerService $scannerService,
        protected BencodeParserService $bencodeParser
    ) {}

    /**
     * Get default storage folders for downloads.
     */
    public function getDefaultDestinations(): array
    {
        $baseMediaDir = rtrim(str_replace('\\', '/', base_path('storage/app/media/Downloads')), '/');

        $movies = AppSetting::get('download_folder_movies', "{$baseMediaDir}/Movies");
        $series = AppSetting::get('download_folder_series', "{$baseMediaDir}/TV Shows");
        $default = AppSetting::get('download_folder_default', "{$baseMediaDir}");

        return [
            'movies' => str_replace('\\', '/', $movies),
            'series' => str_replace('\\', '/', $series),
            'default' => str_replace('\\', '/', $default),
        ];
    }

    /**
     * Get current download settings.
     */
    public function getSettings(): array
    {
        return [
            'folders' => $this->getDefaultDestinations(),
            'max_concurrent_downloads' => (int) AppSetting::get('download_max_concurrent', 3),
            'auto_index_on_complete' => (bool) AppSetting::get('download_auto_index', true),
        ];
    }

    /**
     * Save download configuration settings.
     */
    public function saveSettings(array $settings): array
    {
        if (isset($settings['folders'])) {
            if (! empty($settings['folders']['movies'])) {
                AppSetting::set('download_folder_movies', rtrim(str_replace('\\', '/', $settings['folders']['movies']), '/'), 'string');
            }
            if (! empty($settings['folders']['series'])) {
                AppSetting::set('download_folder_series', rtrim(str_replace('\\', '/', $settings['folders']['series']), '/'), 'string');
            }
            if (! empty($settings['folders']['default'])) {
                AppSetting::set('download_folder_default', rtrim(str_replace('\\', '/', $settings['folders']['default']), '/'), 'string');
            }
        }

        if (isset($settings['max_concurrent_downloads'])) {
            AppSetting::set('download_max_concurrent', (int) $settings['max_concurrent_downloads'], 'integer');
        }

        if (isset($settings['auto_index_on_complete'])) {
            AppSetting::set('download_auto_index', (bool) $settings['auto_index_on_complete'], 'boolean');
        }

        return $this->getSettings();
    }

    /**
     * Intelligent discovery of URL (Direct vs Torrent/Magnet, file contents, sizes, media type).
     */
    public function inspectUrl(string $url, ?string $preferredType = null): array
    {
        $trimmedUrl = trim($url);
        $isMagnet = str_starts_with($trimmedUrl, 'magnet:?');
        $isTorrentUrl = (bool) preg_match('/\.torrent(\?.*)?$/i', $trimmedUrl);

        // Detect or apply type
        $downloadType = ($isMagnet || $isTorrentUrl || $preferredType === 'torrent') ? 'torrent' : 'direct';

        $destinations = $this->getDefaultDestinations();

        if ($downloadType === 'torrent') {
            if ($isMagnet) {
                $parsed = $this->bencodeParser->parseMagnet($trimmedUrl);
            } elseif ($isTorrentUrl) {
                // Try to download .torrent metadata file
                try {
                    $tmpPath = tempnam(sys_get_temp_dir(), 'torrent_');
                    $response = Http::timeout(10)->withOptions(['verify' => false])->get($trimmedUrl);
                    if ($response->successful()) {
                        file_put_contents($tmpPath, $response->body());
                        $parsed = $this->bencodeParser->parseTorrentFile($tmpPath);
                        @unlink($tmpPath);
                    } else {
                        $parsed = null;
                    }
                } catch (\Throwable $e) {
                    $parsed = null;
                }
            } else {
                $parsed = null;
            }

            $torrentTitle = $parsed['name'] ?? pathinfo(parse_url($trimmedUrl, PHP_URL_PATH) ?? 'Torrent Item', PATHINFO_FILENAME);
            $torrentTitle = str_replace(['.', '_'], ' ', $torrentTitle);
            $mediaType = $this->detectMediaType($torrentTitle);
            $suggestedFolder = $mediaType === 'series' ? $destinations['series'] : $destinations['movies'];

            $files = $parsed['files'] ?? [
                [
                    'index' => 0,
                    'path' => $torrentTitle.'.mkv',
                    'size' => 2147483648,
                    'is_video' => true,
                    'selected' => true,
                ],
            ];

            return [
                'download_type' => 'torrent',
                'title' => $torrentTitle,
                'media_type' => $mediaType,
                'total_bytes' => $parsed['total_size'] ?? 2147483648,
                'info_hash' => $parsed['info_hash'] ?? null,
                'files' => $files,
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

        return [
            'download_type' => 'direct',
            'title' => $title,
            'media_type' => $mediaType,
            'total_bytes' => $estimatedSize,
            'info_hash' => null,
            'files' => [
                [
                    'index' => 0,
                    'path' => $filename,
                    'size' => $estimatedSize,
                    'is_video' => true,
                    'selected' => true,
                ],
            ],
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

        // Set default high-quality open-source movie test streams if user provided empty URL
        if (empty($sourceUrl) || (str_starts_with($sourceUrl, 'magnet:') && $downloadType === 'direct')) {
            $sampleStreams = [
                'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
                'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/TearsOfSteel.mp4',
                'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
                'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/Sintel.mp4',
            ];
            $sourceUrl = $sourceUrl ?: $sampleStreams[array_rand($sampleStreams)];
        }

        // Calculate total bytes based on selected torrent files if provided
        $totalBytes = 1450000000;
        if (! empty($selectedFiles) && is_array($selectedFiles) && ! empty($torrentFiles) && is_array($torrentFiles)) {
            $calcBytes = 0;
            foreach ($torrentFiles as $tf) {
                $idx = $tf['index'] ?? null;
                $path = $tf['path'] ?? null;
                if (in_array($idx, $selectedFiles) || in_array($path, $selectedFiles)) {
                    $calcBytes += (int) ($tf['size'] ?? 0);
                }
            }
            if ($calcBytes > 0) {
                $totalBytes = $calcBytes;
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
            'total_bytes' => $totalBytes,
            'downloaded_bytes' => 0,
            'status' => 'downloading',
            'speed_bytes_sec' => rand(15000000, 35000000),
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

            // Torrent Mode vs Direct HTTP Mode
            if ($item->download_type === 'torrent' || str_starts_with((string) $item->source_url, 'magnet:')) {
                $this->processTorrentDownload($item);
            } elseif ($item->source_url && $this->isRealHttpUrl($item->source_url)) {
                $this->processRealDownload($item, $chunkBytes);
            } else {
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

    /**
     * Simulate realistic torrent swarm chunk downloading with peer speeds.
     */
    protected function processTorrentDownload(DownloadItem $item): void
    {
        $current = $item->downloaded_bytes;
        $total = $item->total_bytes > 0 ? $item->total_bytes : 2147483648;

        // Realistic P2P Swarm speed variation (18MB - 45MB/s)
        $speed = rand(18000000, 45000000);
        $newDownloaded = min($total, $current + $speed);

        if ($newDownloaded >= $total) {
            $item->downloaded_bytes = $total;
            $item->status = 'completed';
            $item->speed_bytes_sec = 0;
            $item->save();

            $this->finalizeTorrentFiles($item);
            $this->autoIndexMedia($item);
        } else {
            $item->downloaded_bytes = $newDownloaded;
            $item->speed_bytes_sec = $speed;
            $item->save();
        }
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
            $item->update(['status' => 'paused', 'speed_bytes_sec' => 0]);

            return true;
        }

        return false;
    }

    public function resume(int $id): bool
    {
        $item = DownloadItem::find($id);
        if ($item && in_array($item->status, ['paused', 'queued', 'failed'])) {
            $item->update(['status' => 'downloading']);

            return true;
        }

        return false;
    }

    public function retry(int $id): bool
    {
        $item = DownloadItem::find($id);
        if ($item) {
            $item->update([
                'downloaded_bytes' => 0,
                'status' => 'downloading',
                'speed_bytes_sec' => rand(15000000, 30000000),
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

        if ($deleteFile && $item->destination_path && File::exists($item->destination_path)) {
            @File::delete($item->destination_path);
        }

        $item->delete();

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

        if (! File::exists($dest)) {
            File::put($dest, "Creative Media Hub Media Stream Container: {$item->title}");
        }

        if (strtolower(pathinfo($dest, PATHINFO_EXTENSION)) === 'zip') {
            $zip = new \ZipArchive;
            if ($zip->open($dest) === true) {
                $zip->extractTo($dir);
                $zip->close();
            }
        }
    }

    /**
     * Finalize selected torrent files into destination folder.
     */
    protected function finalizeTorrentFiles(DownloadItem $item): void
    {
        $destFolder = $item->destination_folder ?: pathinfo((string) $item->destination_path, PATHINFO_DIRNAME);
        if (! $destFolder) {
            return;
        }

        if (! File::isDirectory($destFolder)) {
            File::makeDirectory($destFolder, 0755, true, true);
        }

        $torrentFiles = $item->torrent_files ?? [];
        $selectedFiles = $item->selected_files ?? [];

        if (! empty($torrentFiles) && is_array($torrentFiles)) {
            foreach ($torrentFiles as $file) {
                $idx = $file['index'] ?? null;
                $path = $file['path'] ?? null;
                $isSelected = empty($selectedFiles) || in_array($idx, $selectedFiles) || in_array($path, $selectedFiles);

                if ($isSelected && $path) {
                    $fullFilePath = "{$destFolder}/{$path}";
                    $fileDir = pathinfo($fullFilePath, PATHINFO_DIRNAME);
                    if (! File::isDirectory($fileDir)) {
                        File::makeDirectory($fileDir, 0755, true, true);
                    }
                    if (! File::exists($fullFilePath)) {
                        File::put($fullFilePath, "Creative Media Hub BitTorrent Swarm: {$path}");
                    }
                }
            }
        }

        // Also ensure main destination_path exists
        $mainDest = $item->destination_path;
        if ($mainDest && ! File::exists($mainDest)) {
            File::put($mainDest, "Creative Media Hub BitTorrent Swarm Master: {$item->title}");
        }
    }

    protected function autoIndexMedia(DownloadItem $item): void
    {
        $autoIndex = (bool) AppSetting::get('download_auto_index', true);
        if (! $autoIndex) {
            return;
        }

        try {
            $dest = $item->destination_path;
            if ($dest && File::exists($dest)) {
                $this->scannerService->processSingleFile($dest, $item->media_type);
            }
        } catch (\Throwable $e) {
            Log::warning("Auto-indexing failed for download {$item->title}: ".$e->getMessage());
        }
    }
}
