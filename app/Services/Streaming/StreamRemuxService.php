<?php

namespace App\Services\Streaming;

use App\Services\Media\FfmpegLocatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamRemuxService
{
    /**
     * High-speed direct byte-range file streaming.
     * Complies with HTTP 206 Partial Content for native browser hardware playback and instant seeking.
     */
    public function streamFileRange(string $path, Request $request): StreamedResponse
    {
        $size = filesize($path);
        $file = fopen($path, 'rb');

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contentType = match ($ext) {
            'mp4', 'm4v' => 'video/mp4',
            'mkv' => 'video/mp4',
            'webm' => 'video/webm',
            'ogv', 'ogg' => 'video/ogg',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            default => 'video/mp4',
        };

        $start = 0;
        $end = $size - 1;
        $status = 200;

        $headers = [
            'Content-Type' => $contentType,
            'Accept-Ranges' => 'bytes',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Range, Content-Type, Accept',
            'Access-Control-Expose-Headers' => 'Content-Length, Content-Range, Accept-Ranges',
            'Cache-Control' => 'public, max-age=3600',
        ];

        if ($request->header('Range')) {
            $range = $request->header('Range');
            if (preg_match('/bytes=(\d+)-(\d+)?/', $range, $matches)) {
                $start = (int) $matches[1];
                if (! empty($matches[2])) {
                    $end = (int) $matches[2];
                }
                $status = 206;
                $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
            }
        }

        $length = $end - $start + 1;
        $headers['Content-Length'] = (string) $length;

        fseek($file, $start);

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        return new StreamedResponse(function () use ($file, $length) {
            $remaining = $length;
            $chunkSize = 1024 * 256;

            while (! feof($file) && $remaining > 0 && (connection_status() === CONNECTION_NORMAL)) {
                $bytesToRead = min($chunkSize, $remaining);
                $buffer = fread($file, $bytesToRead);
                if ($buffer === false) {
                    break;
                }

                echo $buffer;
                flush();
                $remaining -= strlen($buffer);
            }

            fclose($file);
        }, $status, $headers);
    }

    /**
     * Resolve FFmpeg video stream arguments for remuxing, enabling copy for H.264 and browser-supported HEVC/AV1.
     *
     * @return array<int, string>
     */
    public function resolveVideoEncodeArgs(?string $videoCodec, string $ext, Request $request): array
    {
        $vc = strtolower($videoCodec ?? '');
        $capHevc = $request->query('cap_hevc') === '1';
        $capAv1 = $request->query('cap_av1') === '1';

        $isNativeH264 = (
            str_contains($vc, 'h.264') ||
            str_contains($vc, 'h264') ||
            str_contains($vc, 'avc')
        ) && ! str_contains($vc, 'hevc') && ! str_contains($vc, '265') && ($ext !== 'avi');

        $isNativeHevc = $capHevc && (
            str_contains($vc, 'hevc') ||
            str_contains($vc, 'h265') ||
            str_contains($vc, 'h.265')
        );

        $isNativeAv1 = $capAv1 && str_contains($vc, 'av1');

        if ($request->query('transcode') !== '1' && ($isNativeH264 || $isNativeHevc || $isNativeAv1)) {
            $videoArgs = ['-c:v', 'copy'];
            if ($isNativeHevc) {
                $videoArgs[] = '-tag:v';
                $videoArgs[] = 'hvc1';
            }

            return $videoArgs;
        }

        return FfmpegLocatorService::getVideoEncodeArgs();
    }

    /**
     * Stream media file with real-time FFmpeg remuxing / transcoding to fragmented MP4.
     */
    public function streamRemuxFile(string $filePath, Request $request, ?string $videoCodec = null, ?string $audioCodec = null, string $modelType = 'media', int $modelId = 0)
    {
        if (! file_exists($filePath)) {
            abort(404, 'Media file not found');
        }

        $ffmpegPath = FfmpegLocatorService::getFfmpegPath() ?? 'ffmpeg';
        $startSeconds = max(0, (float) $request->query('start', 0));

        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $nullDevice = $isWin ? 'NUL' : '/dev/null';

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $ac = strtolower($audioCodec ?? '');

        $videoArgs = $this->resolveVideoEncodeArgs($videoCodec, $ext, $request);

        $cacheDir = storage_path('app/cache/media_streams');
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $mtime = file_exists($filePath) ? filemtime($filePath) : 0;
        $cacheKey = "stream_{$modelType}_{$modelId}_{$mtime}";
        $cachedFile = "{$cacheDir}/{$cacheKey}.mp4";
        $completeFile = "{$cachedFile}.complete";
        $lockFile = "{$cacheDir}/{$cacheKey}.lock";
        $pidFile = "{$cacheDir}/run_{$cacheKey}.pid";

        $audioDelayMs = (int) $request->query('audio_delay', 0);

        if (file_exists($cachedFile) && file_exists($completeFile) && $audioDelayMs == 0) {
            return $this->streamFileRange($cachedFile, $request);
        }

        $isNativeAac = (str_contains($ac, 'aac') || str_contains($ac, 'mp4a')) && $audioDelayMs == 0;

        if ($isNativeAac) {
            $audioArgs = [
                '-c:a', 'copy',
                '-sn',
            ];
        } else {
            $audioFilters = [];
            if ($audioDelayMs > 0) {
                $audioFilters[] = "adelay={$audioDelayMs}|{$audioDelayMs}";
            } elseif ($audioDelayMs < 0) {
                $trimSec = abs($audioDelayMs) / 1000.0;
                $audioFilters[] = "atrim=start={$trimSec},asetpts=PTS-STARTPTS";
            }
            $audioFilters[] = 'aresample=async=1:first_pts=0';
            $audioFilterStr = implode(',', $audioFilters);

            $audioArgs = [
                '-af', $audioFilterStr,
                '-c:a', 'aac',
                '-center_mix_level', '0.707',
                '-surround_mix_level', '0.707',
                '-lfe_mix_level', '0.0',
                '-b:a', '256k',
                '-ac', '2',
                '-sn',
            ];
        }

        if ($startSeconds > 0 && in_array('copy', $videoArgs)) {
            $cacheKey = "seek_kf_{$modelType}_{$modelId}_".round($startSeconds, 1);
            $keyframe = Cache::remember($cacheKey, 3600, function () use ($filePath, $startSeconds) {
                return $this->findPrecedingKeyframe($filePath, $startSeconds);
            });
            if (abs($keyframe - $startSeconds) > 0.15) {
                $startSeconds = $keyframe;
            }
        }

        // Accurate seek without arbitrary -noaccurate_seek demuxer desync
        $seekArgs = [];
        if ($startSeconds > 0) {
            $seekArgs = ['-ss', (string) $startSeconds];
        }

        $cmd = array_merge(
            [
                $ffmpegPath,
                '-nostdin',
                '-hide_banner',
                '-loglevel', 'error',
            ],
            $seekArgs,
            [
                '-i', $filePath,
                '-map', '0:v:0',
                '-map', '0:a:0?',
            ],
            $videoArgs,
            $audioArgs,
            [
                '-avoid_negative_ts', 'make_zero',
                '-max_muxing_queue_size', '1024',
                '-movflags', 'frag_keyframe+empty_moov+default_base_moof+separate_moof',
                '-frag_duration', '500000',
                '-min_frag_duration', '200000',
                '-flush_packets', '1',
                '-f', 'mp4',
                'pipe:1',
            ]
        );

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        return response()->stream(function () use ($cmd, $nullDevice) {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['file', $nullDevice, 'w'],
            ];

            @set_time_limit(0);
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            @ini_set('zlib.output_compression', 'Off');

            $process = proc_open($cmd, $descriptors, $pipes);
            if (is_resource($process)) {
                fclose($pipes[0]);

                while (! feof($pipes[1])) {
                    $chunk = fread($pipes[1], 65536);
                    if ($chunk !== false && strlen($chunk) > 0) {
                        echo $chunk;
                        flush();
                    } else {
                        break;
                    }
                    if (connection_aborted()) {
                        break;
                    }
                }

                fclose($pipes[1]);
                proc_terminate($process);
                proc_close($process);
            }
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
            'Accept-Ranges' => 'bytes',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Range, Content-Type, Accept',
            'X-Stream-Start-Offset' => (string) $startSeconds,
            'X-Stream-Start-Seconds' => (string) $startSeconds,
            'Access-Control-Expose-Headers' => 'Content-Length, Content-Range, Accept-Ranges, X-Stream-Start-Offset, X-Stream-Start-Seconds',
        ]);
    }

    /**
     * Find preceding keyframe (I-frame) timestamp for seamless seeking.
     */
    public function findPrecedingKeyframe(string $filePath, float $time): float
    {
        if ($time <= 2.0) {
            return 0.0;
        }

        $ffprobe = FfmpegLocatorService::getFfprobePath();
        if (! $ffprobe) {
            return $time;
        }

        $tMin = max(0, (float) $time - 15.0);
        $tMax = (float) $time + 0.25;
        $interval = $tMin.'%'.$tMax;
        $escapedFile = escapeshellarg($filePath);
        $escapedFfprobe = escapeshellarg($ffprobe);

        $cmd = "{$escapedFfprobe} -v error -select_streams v:0 -show_packets -show_entries packet=pts_time,flags -read_intervals \"{$interval}\" -of csv=p=0 {$escapedFile}";

        $out = @shell_exec($cmd);
        if ($out) {
            $lines = explode("\n", trim($out));
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = trim($lines[$i]);
                if (str_contains($line, 'K')) {
                    $parts = explode(',', $line);
                    if (isset($parts[0]) && is_numeric($parts[0])) {
                        $kf = (float) $parts[0];
                        if ($kf <= ($time + 0.15)) {
                            return round($kf, 3);
                        }
                    }
                }
            }
        }

        return $time;
    }

    /**
     * Check cache status of media stream.
     */
    public function getCacheStatus(string $filePath, string $modelType, int $modelId): array
    {
        $cacheDir = storage_path('app/cache/media_streams');
        $mtime = file_exists($filePath) ? filemtime($filePath) : 0;
        $cacheKey = "stream_{$modelType}_{$modelId}_{$mtime}";

        $cachedFile = "{$cacheDir}/{$cacheKey}.mp4";
        $completeFile = "{$cachedFile}.complete";
        $partFile = "{$cacheDir}/{$cacheKey}.part.mp4";

        if (file_exists($cachedFile) && file_exists($completeFile)) {
            $streamUrl = $modelType === 'episode'
                ? route('stream.remux.episode', ['episode' => $modelId])
                : route('stream.remux.movie', ['mediaItem' => $modelId]);

            return [
                'is_cached' => true,
                'cached_percent' => 100,
                'file_size' => filesize($cachedFile),
                'stream_url' => $streamUrl,
            ];
        }

        if (file_exists($partFile)) {
            $origSize = file_exists($filePath) ? filesize($filePath) : 1;
            $partSize = filesize($partFile);
            $pct = min(99, round(($partSize / $origSize) * 100));

            return [
                'is_cached' => false,
                'cached_percent' => $pct,
                'part_size' => $partSize,
            ];
        }

        return ['is_cached' => false, 'cached_percent' => 0];
    }

    /**
     * Terminate active background FFmpeg process for an item.
     */
    public function stopStream(string $type, int $id): bool
    {
        $cacheDir = storage_path('app/cache/media_streams');
        if (is_dir($cacheDir)) {
            $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

            $pidPattern = "{$cacheDir}/run_stream_{$type}_{$id}_*.pid";
            foreach (glob($pidPattern) as $pidFile) {
                $pid = (int) trim(@file_get_contents($pidFile));
                if ($pid > 0) {
                    if ($isWin) {
                        @exec("taskkill /F /T /PID {$pid} > NUL 2>&1");
                    } else {
                        @posix_kill($pid, SIGTERM);
                    }
                }
                @unlink($pidFile);
            }

            $lockPattern = "{$cacheDir}/stream_{$type}_{$id}_*.lock";
            foreach (glob($lockPattern) as $lock) {
                $base = preg_replace('/\.lock$/', '', $lock);
                if (! file_exists("{$base}.complete")) {
                    @unlink("{$base}.part.mp4");
                    @unlink("{$base}.mp4.part");
                }
                @unlink($lock);
            }

            $batPattern = "{$cacheDir}/run_stream_{$type}_{$id}_*.bat";
            foreach (glob($batPattern) as $bat) {
                @unlink($bat);
            }
        }

        return true;
    }
}
