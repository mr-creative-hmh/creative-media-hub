<?php

namespace App\Services\Metadata;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArtworkDownloadService
{
    protected string $postersDir;

    protected string $backdropsDir;

    public function __construct()
    {
        $this->postersDir = public_path('storage/posters');
        $this->backdropsDir = public_path('storage/backdrops');

        $this->ensureDirectoriesExist();
    }

    protected function ensureDirectoriesExist(): void
    {
        if (! File::isDirectory($this->postersDir)) {
            File::makeDirectory($this->postersDir, 0755, true);
        }
        if (! File::isDirectory($this->backdropsDir)) {
            File::makeDirectory($this->backdropsDir, 0755, true);
        }
    }

    public function downloadPoster(?string $remoteUrl): ?string
    {
        if (empty($remoteUrl)) {
            return null;
        }

        // If it's already a local storage path, return as is
        if (str_starts_with($remoteUrl, '/storage/') || str_starts_with($remoteUrl, 'storage/') || File::exists($remoteUrl)) {
            return $remoteUrl;
        }

        if (! filter_var($remoteUrl, FILTER_VALIDATE_URL)) {
            return $remoteUrl;
        }

        try {
            $hash = md5($remoteUrl);
            $ext = pathinfo(parse_url($remoteUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = "{$hash}.{$ext}";
            $localFilePath = "{$this->postersDir}/{$filename}";
            $publicUrl = "/storage/posters/{$filename}";

            if (File::exists($localFilePath) && File::size($localFilePath) > 1000) {
                return $publicUrl;
            }

            $response = Http::timeout(8)->get($remoteUrl);
            if ($response->successful()) {
                File::put($localFilePath, $response->body());

                return $publicUrl;
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to download poster from {$remoteUrl}: ".$e->getMessage());
        }

        return $remoteUrl; // Fallback to remote URL if download fails
    }

    public function downloadBackdrop(?string $remoteUrl): ?string
    {
        if (empty($remoteUrl)) {
            return null;
        }

        if (str_starts_with($remoteUrl, '/storage/') || str_starts_with($remoteUrl, 'storage/') || File::exists($remoteUrl)) {
            return $remoteUrl;
        }

        if (! filter_var($remoteUrl, FILTER_VALIDATE_URL)) {
            return $remoteUrl;
        }

        try {
            $hash = md5($remoteUrl);
            $ext = pathinfo(parse_url($remoteUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = "{$hash}.{$ext}";
            $localFilePath = "{$this->backdropsDir}/{$filename}";
            $publicUrl = "/storage/backdrops/{$filename}";

            if (File::exists($localFilePath) && File::size($localFilePath) > 1000) {
                return $publicUrl;
            }

            $response = Http::timeout(8)->get($remoteUrl);
            if ($response->successful()) {
                File::put($localFilePath, $response->body());

                return $publicUrl;
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to download backdrop from {$remoteUrl}: ".$e->getMessage());
        }

        return $remoteUrl;
    }
}
