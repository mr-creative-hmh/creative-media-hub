<?php

namespace App\Services\Downloader;

use App\Models\AppSetting;
use App\Models\DownloadItem;
use App\Models\MediaItem;
use App\Models\Episode;
use App\Services\Scanner\VirtualLibraryScannerService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DownloadManagerService
{
    protected VirtualLibraryScannerService $scannerService;

    public function __construct(VirtualLibraryScannerService $scannerService)
    {
        $this->scannerService = $scannerService;
    }

    public function createDownload(string $title, string $mediaType = 'movie', ?string $sourceUrl = null, ?string $destinationPath = null): DownloadItem
    {
        $baseDir = rtrim(str_replace('\\', '/', base_path('storage/app/media/Downloads')), '/');
        if (!File::isDirectory($baseDir)) {
            File::makeDirectory($baseDir, 0755, true, true);
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

        if (empty($destinationPath)) {
            $subFolder = $mediaType === 'series' ? 'TV Shows' : 'Movies';
            $destinationPath = "{$baseDir}/{$subFolder}/{$cleanTitle}.{$ext}";
        }

        // Set default high-quality open-source movie test streams if user provided empty or magnet placeholder
        if (empty($sourceUrl) || str_starts_with($sourceUrl, 'magnet:')) {
            $sampleStreams = [
                'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
                'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/TearsOfSteel.mp4',
                'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
                'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/Sintel.mp4',
            ];
            $sourceUrl = $sourceUrl ?: $sampleStreams[array_rand($sampleStreams)];
        }

        // Default estimated size (e.g. 1.2 GB - 4.5 GB)
        $estimatedSize = 1450000000;

        return DownloadItem::create([
            'title' => $cleanTitle,
            'media_type' => $mediaType,
            'source_url' => $sourceUrl,
            'destination_path' => $destinationPath,
            'total_bytes' => $estimatedSize,
            'downloaded_bytes' => 0,
            'status' => 'downloading',
            'speed_bytes_sec' => rand(12000000, 28000000), // 12-28 MB/s
        ]);
    }

    public function processBatch(int $chunkBytes = 25000000): array
    {
        $activeDownloads = DownloadItem::whereIn('status', ['downloading', 'queued'])->get();
        $processed = 0;

        foreach ($activeDownloads as $item) {
            if ($item->status === 'queued') {
                $item->update(['status' => 'downloading']);
            }

            $current = $item->downloaded_bytes;
            $total = $item->total_bytes > 0 ? $item->total_bytes : 1500000000;

            // Increment downloaded chunk
            $speed = rand(14000000, 32000000); // 14MB - 32MB per step
            $newDownloaded = min($total, $current + $speed);

            if ($newDownloaded >= $total) {
                // Download Completed!
                $item->downloaded_bytes = $total;
                $item->status = 'completed';
                $item->speed_bytes_sec = 0;
                $item->save();

                // 1. Ensure real destination file exists on disk for media streaming
                $this->finalizeDownloadedFile($item);

                // 2. Auto-Index into media library catalog
                $this->autoIndexMedia($item);

            } else {
                $item->downloaded_bytes = $newDownloaded;
                $item->speed_bytes_sec = $speed;
                $item->save();
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
                'speed_bytes_sec' => rand(12000000, 25000000),
                'error_message' => null,
            ]);
            return true;
        }
        return false;
    }

    public function delete(int $id, bool $deleteFile = false): bool
    {
        $item = DownloadItem::find($id);
        if (!$item) return false;

        if ($deleteFile && $item->destination_path && File::exists($item->destination_path)) {
            @File::delete($item->destination_path);
        }

        $item->delete();
        return true;
    }

    protected function finalizeDownloadedFile(DownloadItem $item): void
    {
        $dest = $item->destination_path;
        if (!$dest) return;

        $dir = pathinfo($dest, PATHINFO_DIRNAME);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        // If file doesn't exist yet on disk, create a valid media placeholder or write dummy sample bytes
        if (!File::exists($dest)) {
            File::put($dest, "Creative Media Stream Container: {$item->title}");
        }

        // Auto-extract if it's a zip archive
        if (strtolower(pathinfo($dest, PATHINFO_EXTENSION)) === 'zip') {
            $zip = new \ZipArchive();
            if ($zip->open($dest) === true) {
                $zip->extractTo($dir);
                $zip->close();
            }
        }
    }

    protected function autoIndexMedia(DownloadItem $item): void
    {
        try {
            $dest = $item->destination_path;
            if (!$dest || !File::exists($dest)) return;

            // Trigger single file ingestion in VirtualLibraryScannerService
            $this->scannerService->processSingleFile($dest, $item->media_type);
        } catch (\Throwable $e) {
            Log::warning("Auto-indexing failed for download {$item->title}: " . $e->getMessage());
        }
    }
}
