<?php

namespace App\Services\Media;

use App\Models\AppSetting;
use Illuminate\Support\Facades\File;

class FfmpegLocatorService
{
    protected static ?string $cachedFfmpegPath = null;
    protected static ?string $cachedFfprobePath = null;

    /**
     * Get the absolute path for FFmpeg.
     */
    public static function getFfmpegPath(): ?string
    {
        if (self::$cachedFfmpegPath && file_exists(self::$cachedFfmpegPath)) {
            return self::$cachedFfmpegPath;
        }

        // 1. Check database setting
        try {
            if (class_exists(AppSetting::class)) {
                $custom = AppSetting::where('key', 'ffmpeg_path')->value('value');
                if ($custom && file_exists($custom)) {
                    return self::$cachedFfmpegPath = $custom;
                }
            }
        } catch (\Throwable $e) {}

        $userProfile = getenv('USERPROFILE') ?: 'C:\\Users\\hasan';
        $localAppData = getenv('LOCALAPPDATA') ?: "{$userProfile}\\AppData\\Local";

        // 2. Direct exact known paths
        $candidates = [
            "{$localAppData}\\Microsoft\\WinGet\\Packages\\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\\ffmpeg-9.0.1-full_build\\bin\\ffmpeg.exe",
            "C:\\Users\\hasan\\AppData\\Local\\Microsoft\\WinGet\\Packages\\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\\ffmpeg-9.0.1-full_build\\bin\\ffmpeg.exe",
            "{$localAppData}\\Microsoft\\WinGet\\Links\\ffmpeg.exe",
            "C:\\tools\\ffmpeg\\bin\\ffmpeg.exe",
            "C:\\ffmpeg\\bin\\ffmpeg.exe",
            "C:\\ProgramData\\chocolatey\\bin\\ffmpeg.exe",
            "{$userProfile}\\scoop\\shims\\ffmpeg.exe",
        ];

        foreach ($candidates as $cand) {
            if ($cand && file_exists($cand)) {
                return self::$cachedFfmpegPath = $cand;
            }
        }

        // 3. Recursive directory scan of WinGet packages
        $packagesDir = "{$localAppData}\\Microsoft\\WinGet\\Packages";
        if (is_dir($packagesDir)) {
            $files = self::findFileRecursive($packagesDir, 'ffmpeg.exe');
            if (!empty($files)) {
                return self::$cachedFfmpegPath = $files[0];
            }
        }

        // 4. Check system PATH via where command
        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $testCmd = $isWin ? "where ffmpeg 2>NUL" : "which ffmpeg 2>/dev/null";
        $out = [];
        @exec($testCmd, $out, $code);
        if ($code === 0 && !empty($out[0]) && trim($out[0]) !== '' && file_exists(trim($out[0]))) {
            return self::$cachedFfmpegPath = trim($out[0]);
        }

        return self::$cachedFfmpegPath = 'ffmpeg';
    }

    /**
     * Get the absolute path for FFprobe.
     */
    public static function getFfprobePath(): ?string
    {
        if (self::$cachedFfprobePath && file_exists(self::$cachedFfprobePath)) {
            return self::$cachedFfprobePath;
        }

        try {
            if (class_exists(AppSetting::class)) {
                $custom = AppSetting::where('key', 'ffprobe_path')->value('value');
                if ($custom && file_exists($custom)) {
                    return self::$cachedFfprobePath = $custom;
                }
            }
        } catch (\Throwable $e) {}

        $ffmpeg = self::getFfmpegPath();
        if ($ffmpeg && $ffmpeg !== 'ffmpeg' && file_exists($ffmpeg)) {
            $ffprobe = str_replace('ffmpeg.exe', 'ffprobe.exe', $ffmpeg);
            if (file_exists($ffprobe)) {
                return self::$cachedFfprobePath = $ffprobe;
            }
        }

        $userProfile = getenv('USERPROFILE') ?: 'C:\\Users\\hasan';
        $localAppData = getenv('LOCALAPPDATA') ?: "{$userProfile}\\AppData\\Local";

        $candidates = [
            "{$localAppData}\\Microsoft\\WinGet\\Packages\\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\\ffmpeg-9.0.1-full_build\\bin\\ffprobe.exe",
            "C:\\Users\\hasan\\AppData\\Local\\Microsoft\\WinGet\\Packages\\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\\ffmpeg-9.0.1-full_build\\bin\\ffprobe.exe",
            "{$localAppData}\\Microsoft\\WinGet\\Links\\ffprobe.exe",
            "C:\\tools\\ffmpeg\\bin\\ffprobe.exe",
            "C:\\ffmpeg\\bin\\ffprobe.exe",
        ];

        foreach ($candidates as $cand) {
            if ($cand && file_exists($cand)) {
                return self::$cachedFfprobePath = $cand;
            }
        }

        return self::$cachedFfprobePath = 'ffprobe';
    }

    protected static function findFileRecursive(string $dir, string $filename): array
    {
        $matches = [];
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isFile() && strcasecmp($item->getFilename(), $filename) === 0) {
                    $matches[] = $item->getPathname();
                }
            }
        } catch (\Throwable $e) {}
        return $matches;
    }

    public static function isAvailable(): bool
    {
        return self::getFfmpegPath() !== null;
    }
}
