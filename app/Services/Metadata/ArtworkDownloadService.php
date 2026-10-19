<?php

namespace App\Services\Metadata;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArtworkDownloadService
{
    protected string $postersDir;

    protected string $backdropsDir;

    protected string $stillsDir;

    protected string $profilesDir;

    public function __construct()
    {
        $this->postersDir = public_path('storage/posters');
        $this->backdropsDir = public_path('storage/backdrops');
        $this->stillsDir = public_path('storage/stills');
        $this->profilesDir = public_path('storage/profiles');

        $this->ensureDirectoriesExist();
    }

    protected function ensureDirectoriesExist(): void
    {
        foreach ([$this->postersDir, $this->backdropsDir, $this->stillsDir, $this->profilesDir] as $dir) {
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true, true);
            }
        }
    }

    /**
     * Normalize a potential TMDB relative path or remote URL into a full fetchable URL.
     */
    protected function normalizeRemoteUrl(?string $url, string $tmdbSize = 'w500'): ?string
    {
        if (empty($url)) {
            return null;
        }

        $trimmed = trim($url);

        // Already local storage
        if (str_starts_with($trimmed, '/storage/') || str_starts_with($trimmed, 'storage/')) {
            return null;
        }

        // Relative TMDB image path
        if (str_starts_with($trimmed, '/') && ! str_starts_with($trimmed, '//')) {
            return "https://image.tmdb.org/t/p/{$tmdbSize}".ltrim($trimmed, '/');
        }

        if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return $trimmed;
        }

        return null;
    }

    public function downloadPoster(?string $remoteUrl): ?string
    {
        return $this->downloadAndStore($remoteUrl, $this->postersDir, '/storage/posters', 'w500');
    }

    public function downloadBackdrop(?string $remoteUrl): ?string
    {
        return $this->downloadAndStore($remoteUrl, $this->backdropsDir, '/storage/backdrops', 'original');
    }

    public function downloadStill(?string $remoteUrl): ?string
    {
        return $this->downloadAndStore($remoteUrl, $this->stillsDir, '/storage/stills', 'w500');
    }

    public function downloadProfile(?string $remoteUrl): ?string
    {
        return $this->downloadAndStore($remoteUrl, $this->profilesDir, '/storage/profiles', 'w500');
    }

    /**
     * Download and store artwork locally with integrity checking.
     */
    protected function downloadAndStore(?string $url, string $targetDir, string $publicPrefix, string $tmdbSize = 'w500'): ?string
    {
        if (empty($url)) {
            return null;
        }

        $trimmed = trim($url);

        // If it's already a local storage path, verify and return
        if (str_starts_with($trimmed, '/storage/') || str_starts_with($trimmed, 'storage/')) {
            $relPath = ltrim(preg_replace('#^/?storage/#', '', $trimmed), '/');
            $fullLocal = public_path("storage/{$relPath}");
            if (File::exists($fullLocal) && File::size($fullLocal) > 500) {
                return '/storage/'.ltrim($relPath, '/');
            }
        }

        $fetchUrl = $this->normalizeRemoteUrl($trimmed, $tmdbSize);
        if (! $fetchUrl) {
            return $trimmed;
        }

        try {
            $hash = md5($fetchUrl);
            $parsedPath = parse_url($fetchUrl, PHP_URL_PATH);
            $ext = $parsedPath ? (pathinfo($parsedPath, PATHINFO_EXTENSION) ?: 'jpg') : 'jpg';
            $filename = "{$hash}.{$ext}";
            $localFilePath = "{$targetDir}/{$filename}";
            $publicUrl = "{$publicPrefix}/{$filename}";

            if (File::exists($localFilePath) && File::size($localFilePath) > 500) {
                return $publicUrl;
            }

            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'CreativeMediaHub/1.0'])
                ->get($fetchUrl);

            if ($response->successful() && strlen($response->body()) > 500) {
                File::put($localFilePath, $response->body());

                return $publicUrl;
            }
        } catch (\Throwable $e) {
            Log::warning("Artwork download failed for {$fetchUrl}: ".$e->getMessage());
        }

        // If local file was already present from a previous attempt
        if (isset($localFilePath) && File::exists($localFilePath) && File::size($localFilePath) > 500) {
            return $publicUrl;
        }

        // If offline and no local file exists, do not return broken remote URL that hangs client
        return null;
    }
}
