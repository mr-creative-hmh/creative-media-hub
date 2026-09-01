<?php

namespace App\Services\Media;

use App\Models\AppSetting;
use Illuminate\Support\Facades\File;

class FfmpegLocatorService
{
    protected static ?string $cachedFfmpegPath = null;
    protected static ?string $cachedFfprobePath = null;

    /**
     * Get the absolute path or command name for FFmpeg.
     */
    public static function getFfmpegPath(): ?string
    {
        if (self::$cachedFfmpegPath && (self::$cachedFfmpegPath === 'ffmpeg' || File::exists(self::$cachedFfmpegPath))) {
            return self::$cachedFfmpegPath;
        }

        // 1. Check custom user setting in DB
        try {
            $custom = AppSetting::where('key', 'ffmpeg_path')->value('value');
            if ($custom && File::exists($custom)) {
                return self::$cachedFfmpegPath = $custom;
            }
        } catch (\Throwable $e) {}

        // 2. Check system PATH
        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $testCmd = $isWin ? "where ffmpeg 2>NUL" : "which ffmpeg 2>/dev/null";
        $out = [];
        @exec($testCmd, $out, $code);
        if ($code === 0 && !empty($out[0]) && trim($out[0]) !== '') {
            return self::$cachedFfmpegPath = trim($out[0]);
        }

        // 3. Check Windows locations
        if ($isWin) {
            $userProfile = getenv('USERPROFILE') ?: 'C:\Users\hasan';
            $localAppData = getenv('LOCALAPPDATA') ?: "{$userProfile}\\AppData\\Local";

            $candidates = [
                "{$localAppData}\\Microsoft\\WinGet\\Links\\ffmpeg.exe",
                "{$userProfile}\\AppData\\Local\\Microsoft\\WinGet\\Links\\ffmpeg.exe",
                "C:\\tools\\ffmpeg\\bin\\ffmpeg.exe",
                "C:\\ProgramData\\chocolatey\\bin\\ffmpeg.exe",
                "{$userProfile}\\scoop\\shims\\ffmpeg.exe",
                storage_path('app/bin/ffmpeg.exe'),
            ];

            // Search winget packages
            $packages = glob("{$localAppData}\\Microsoft\\WinGet\\Packages\\*ffmpeg*\\*\\bin\\ffmpeg.exe");
            if (!empty($packages)) {
                $candidates = array_merge($candidates, $packages);
            }
            $packages2 = glob("{$userProfile}\\AppData\\Local\\Microsoft\\WinGet\\Packages\\*ffmpeg*\\*\\bin\\ffmpeg.exe");
            if (!empty($packages2)) {
                $candidates = array_merge($candidates, $packages2);
            }

            foreach ($candidates as $cand) {
                if ($cand && File::exists($cand)) {
                    return self::$cachedFfmpegPath = $cand;
                }
            }
        }

        return null;
    }

    /**
     * Get the absolute path or command name for FFprobe.
     */
    public static function getFfprobePath(): ?string
    {
        if (self::$cachedFfprobePath && (self::$cachedFfprobePath === 'ffprobe' || File::exists(self::$cachedFfprobePath))) {
            return self::$cachedFfprobePath;
        }

        try {
            $custom = AppSetting::where('key', 'ffprobe_path')->value('value');
            if ($custom && File::exists($custom)) {
                return self::$cachedFfprobePath = $custom;
            }
        } catch (\Throwable $e) {}

        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $testCmd = $isWin ? "where ffprobe 2>NUL" : "which ffprobe 2>/dev/null";
        $out = [];
        @exec($testCmd, $out, $code);
        if ($code === 0 && !empty($out[0]) && trim($out[0]) !== '') {
            return self::$cachedFfprobePath = trim($out[0]);
        }

        $ffmpeg = self::getFfmpegPath();
        if ($ffmpeg && File::exists($ffmpeg)) {
            $ffprobe = str_replace('ffmpeg.exe', 'ffprobe.exe', $ffmpeg);
            if (File::exists($ffprobe)) {
                return self::$cachedFfprobePath = $ffprobe;
            }
        }

        if ($isWin) {
            $userProfile = getenv('USERPROFILE') ?: 'C:\Users\hasan';
            $localAppData = getenv('LOCALAPPDATA') ?: "{$userProfile}\\AppData\\Local";
            $probeCandidates = [
                "{$localAppData}\\Microsoft\\WinGet\\Links\\ffprobe.exe",
                "{$userProfile}\\AppData\\Local\\Microsoft\\WinGet\\Links\\ffprobe.exe",
                "C:\\tools\\ffmpeg\\bin\\ffprobe.exe",
                storage_path('app/bin/ffprobe.exe'),
            ];
            foreach ($probeCandidates as $cand) {
                if ($cand && File::exists($cand)) {
                    return self::$cachedFfprobePath = $cand;
                }
            }
        }

        return null;
    }

    public static function isAvailable(): bool
    {
        return self::getFfmpegPath() !== null;
    }
}
