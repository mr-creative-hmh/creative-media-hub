<?php

namespace App\Services\Media;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class FfmpegLocatorService
{
    protected static ?string $cachedFfmpegPath = null;

    protected static ?string $cachedFfprobePath = null;

    protected static ?string $cachedMediaInfoPath = null;

    protected static ?string $cachedH264Encoder = null;

    /**
     * Get the absolute path for FFmpeg.
     */
    public static function getFfmpegPath(): ?string
    {
        if (self::$cachedFfmpegPath && file_exists(self::$cachedFfmpegPath)) {
            return self::$cachedFfmpegPath;
        }

        try {
            $cached = Cache::get('cmh_locator_ffmpeg_path');
            if (is_string($cached) && $cached !== '' && ($cached === 'ffmpeg' || file_exists($cached))) {
                return self::$cachedFfmpegPath = $cached;
            }
        } catch (\Throwable $e) {
        }

        $resolved = self::resolveFfmpegPath();

        try {
            if ($resolved) {
                Cache::forever('cmh_locator_ffmpeg_path', $resolved);
            }
        } catch (\Throwable $e) {
        }

        return self::$cachedFfmpegPath = $resolved;
    }

    /**
     * Resolve FFmpeg path from database setting, known locations, or system PATH.
     */
    protected static function resolveFfmpegPath(): string
    {
        // 1. Check database setting
        try {
            if (class_exists(AppSetting::class)) {
                $custom = AppSetting::where('key', 'ffmpeg_path')->value('value');
                if ($custom && file_exists($custom)) {
                    return $custom;
                }
            }
        } catch (\Throwable $e) {
        }

        $userProfile = getenv('USERPROFILE') ?: (getenv('HOMEDRIVE') && getenv('HOMEPATH') ? getenv('HOMEDRIVE').getenv('HOMEPATH') : null);
        $localAppData = getenv('LOCALAPPDATA') ?: ($userProfile ? "{$userProfile}\\AppData\\Local" : null);

        // 2. Direct exact known paths
        $candidates = array_values(array_filter([
            $localAppData ? "{$localAppData}\\Microsoft\\WinGet\\Links\\ffmpeg.exe" : null,
            'C:\\tools\\ffmpeg\\bin\\ffmpeg.exe',
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
            'C:\\ProgramData\\chocolatey\\bin\\ffmpeg.exe',
            $userProfile ? "{$userProfile}\\scoop\\shims\\ffmpeg.exe" : null,
        ]));

        if ($localAppData) {
            $wingetMatches = glob("{$localAppData}\\Microsoft\\WinGet\\Packages\\*FFmpeg*\\*\\bin\\ffmpeg.exe") ?: [];
            if (! empty($wingetMatches)) {
                array_unshift($candidates, ...$wingetMatches);
            }
        }

        foreach ($candidates as $cand) {
            if ($cand && file_exists($cand)) {
                return $cand;
            }
        }

        // 3. Recursive directory scan of WinGet packages
        if ($localAppData) {
            $packagesDir = "{$localAppData}\\Microsoft\\WinGet\\Packages";
            if (is_dir($packagesDir)) {
                $files = self::findFileRecursive($packagesDir, 'ffmpeg.exe');
                if (! empty($files)) {
                    return $files[0];
                }
            }
        }

        // 4. Check system PATH via where command
        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $testCmd = $isWin ? 'where ffmpeg 2>NUL' : 'which ffmpeg 2>/dev/null';
        $out = [];
        @exec($testCmd, $out, $code);
        if ($code === 0 && ! empty($out[0]) && trim($out[0]) !== '' && file_exists(trim($out[0]))) {
            return trim($out[0]);
        }

        return 'ffmpeg';
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
            $cached = Cache::get('cmh_locator_ffprobe_path');
            if (is_string($cached) && $cached !== '' && ($cached === 'ffprobe' || file_exists($cached))) {
                return self::$cachedFfprobePath = $cached;
            }
        } catch (\Throwable $e) {
        }

        $resolved = self::resolveFfprobePath();

        try {
            if ($resolved) {
                Cache::forever('cmh_locator_ffprobe_path', $resolved);
            }
        } catch (\Throwable $e) {
        }

        return self::$cachedFfprobePath = $resolved;
    }

    /**
     * Resolve FFprobe path from database setting, sibling binary, or known locations.
     */
    protected static function resolveFfprobePath(): string
    {
        try {
            if (class_exists(AppSetting::class)) {
                $custom = AppSetting::where('key', 'ffprobe_path')->value('value');
                if ($custom && file_exists($custom)) {
                    return $custom;
                }
            }
        } catch (\Throwable $e) {
        }

        $ffmpeg = self::getFfmpegPath();
        if ($ffmpeg && $ffmpeg !== 'ffmpeg' && file_exists($ffmpeg)) {
            $ffprobe = str_replace('ffmpeg.exe', 'ffprobe.exe', $ffmpeg);
            if (file_exists($ffprobe)) {
                return $ffprobe;
            }
        }

        $userProfile = getenv('USERPROFILE') ?: (getenv('HOMEDRIVE') && getenv('HOMEPATH') ? getenv('HOMEDRIVE').getenv('HOMEPATH') : null);
        $localAppData = getenv('LOCALAPPDATA') ?: ($userProfile ? "{$userProfile}\\AppData\\Local" : null);

        $candidates = array_values(array_filter([
            $localAppData ? "{$localAppData}\\Microsoft\\WinGet\\Links\\ffprobe.exe" : null,
            'C:\\tools\\ffmpeg\\bin\\ffprobe.exe',
            'C:\\ffmpeg\\bin\\ffprobe.exe',
            'C:\\ProgramData\\chocolatey\\bin\\ffprobe.exe',
            $userProfile ? "{$userProfile}\\scoop\\shims\\ffprobe.exe" : null,
        ]));

        if ($localAppData) {
            $wingetMatches = glob("{$localAppData}\\Microsoft\\WinGet\\Packages\\*FFmpeg*\\*\\bin\\ffprobe.exe") ?: [];
            if (! empty($wingetMatches)) {
                array_unshift($candidates, ...$wingetMatches);
            }
        }

        foreach ($candidates as $cand) {
            if ($cand && file_exists($cand)) {
                return $cand;
            }
        }

        return 'ffprobe';
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
        } catch (\Throwable $e) {
        }

        return $matches;
    }

    public static function isAvailable(): bool
    {
        return self::getFfmpegPath() !== null;
    }

    /**
     * Get the absolute path for K-Lite Codec Pack MediaInfo CLI (if installed).
     */
    public static function getMediaInfoPath(): ?string
    {
        if (self::$cachedMediaInfoPath && file_exists(self::$cachedMediaInfoPath)) {
            return self::$cachedMediaInfoPath;
        }

        try {
            $cached = Cache::get('cmh_locator_mediainfo_path');
            if (is_string($cached) && $cached !== '' && file_exists($cached)) {
                return self::$cachedMediaInfoPath = $cached;
            }
        } catch (\Throwable $e) {
        }

        $resolved = self::resolveMediaInfoPath();

        if ($resolved) {
            try {
                Cache::forever('cmh_locator_mediainfo_path', $resolved);
            } catch (\Throwable $e) {
            }
        }

        return self::$cachedMediaInfoPath = $resolved;
    }

    /**
     * Resolve MediaInfo CLI path from K-Lite Codec Pack installations or system PATH.
     */
    protected static function resolveMediaInfoPath(): ?string
    {
        try {
            if (class_exists(AppSetting::class)) {
                $custom = AppSetting::where('key', 'mediainfo_path')->value('value');
                if ($custom && file_exists($custom)) {
                    return $custom;
                }
            }
        } catch (\Throwable $e) {
        }

        $candidates = [
            'C:\\Program Files (x86)\\K-Lite Codec Pack\\Tools\\mediainfo.exe',
            'C:\\Program Files\\K-Lite Codec Pack\\Tools\\mediainfo.exe',
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                return $cand;
            }
        }

        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $testCmd = $isWin ? 'where.exe mediainfo 2>NUL' : 'which mediainfo 2>/dev/null';
        $out = [];
        @exec($testCmd, $out, $code);
        if ($code === 0 && ! empty($out[0]) && trim($out[0]) !== '' && file_exists(trim($out[0]))) {
            return trim($out[0]);
        }

        return null;
    }

    /**
     * Probe and persistently cache the fastest available H.264 encoder (NVENC -> QSV -> MediaFoundation -> libx264).
     */
    public static function getBestH264Encoder(): string
    {
        if (self::$cachedH264Encoder !== null) {
            return self::$cachedH264Encoder;
        }

        $validEncoders = ['h264_nvenc', 'h264_qsv', 'h264_mf', 'libx264'];

        try {
            $cached = Cache::get('cmh_hw_h264_encoder');
            if (is_string($cached) && in_array($cached, $validEncoders, true)) {
                return self::$cachedH264Encoder = $cached;
            }
        } catch (\Throwable $e) {
        }

        $ffmpeg = self::getFfmpegPath();
        $selected = 'libx264';

        if ($ffmpeg) {
            $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $nullDevice = $isWin ? 'NUL' : '/dev/null';
            $escapedFfmpeg = escapeshellarg($ffmpeg);

            foreach (['h264_nvenc', 'h264_qsv', 'h264_mf'] as $candidate) {
                $cmd = "{$escapedFfmpeg} -hide_banner -loglevel error -f lavfi -i color=black:s=64x64:d=0.1 -c:v {$candidate} -f null {$nullDevice} 2>&1";
                $out = [];
                $code = 1;
                @exec($cmd, $out, $code);
                if ($code === 0) {
                    $selected = $candidate;
                    break;
                }
            }
        }

        try {
            Cache::forever('cmh_hw_h264_encoder', $selected);
        } catch (\Throwable $e) {
        }

        return self::$cachedH264Encoder = $selected;
    }

    /**
     * Return low-latency FFmpeg encoding flags for the selected H.264 encoder.
     *
     * @return array<int, string>
     */
    public static function getVideoEncodeArgs(?string $encoder = null): array
    {
        $resolved = $encoder ?? self::getBestH264Encoder();

        return match ($resolved) {
            'h264_nvenc' => [
                '-c:v', 'h264_nvenc',
                '-preset', 'p1',
                '-tune', 'll',
                '-rc', 'vbr',
                '-cq', '23',
                '-pix_fmt', 'yuv420p',
            ],
            'h264_qsv' => [
                '-c:v', 'h264_qsv',
                '-preset', 'veryfast',
                '-global_quality', '23',
                '-pix_fmt', 'yuv420p',
            ],
            'h264_mf' => [
                '-c:v', 'h264_mf',
                '-rate_control', 'quality',
                '-quality', '75',
                '-pix_fmt', 'yuv420p',
            ],
            default => [
                '-c:v', 'libx264',
                '-preset', 'ultrafast',
                '-tune', 'zerolatency',
                '-crf', '22',
                '-pix_fmt', 'yuv420p',
            ],
        };
    }
}
