<?php

namespace App\Services\Streaming;

use App\Services\Media\FfmpegLocatorService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamMseService
{
    /**
     * Stream media file as an ISO BMFF fragmented MP4 (fMP4) stream tailored for MediaSource Extensions (MSE).
     * Uses zero-transcode video copy (-c:v copy) for H.264/AVC, achieving 0% server video CPU overhead.
     * Concurrently converts non-web audio (AC-3, E-AC-3, DTS) into browser-compliant AAC stereo.
     */
    public function streamMseFile(
        string $filePath,
        Request $request,
        ?string $videoCodec = null,
        ?string $audioCodec = null,
        string $modelType = 'media',
        int $modelId = 0
    ): StreamedResponse {
        if (! file_exists($filePath)) {
            abort(404, 'Media file not found');
        }

        $ffmpegPath = FfmpegLocatorService::getFfmpegPath() ?? 'ffmpeg';
        $startSeconds = max(0, (float) $request->query('start', 0));

        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $nullDevice = $isWin ? 'NUL' : '/dev/null';

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $vc = strtolower($videoCodec ?? '');
        $ac = strtolower($audioCodec ?? '');

        // REA bitstream inspection: Detect if video can be copied directly without transcode
        $isNativeH264 = (
            str_contains($vc, 'h.264') ||
            str_contains($vc, 'h264') ||
            str_contains($vc, 'avc')
        ) && ! str_contains($vc, 'hevc') && ($ext !== 'avi');

        if ($isNativeH264 && $request->query('transcode') !== '1') {
            $videoArgs = ['-c:v', 'copy'];
            $mimeVideoCodec = 'avc1.640028';
        } else {
            $videoArgs = [
                '-c:v', 'libx264',
                '-preset', 'ultrafast',
                '-tune', 'zerolatency',
                '-crf', '22',
                '-pix_fmt', 'yuv420p',
            ];
            $mimeVideoCodec = 'avc1.42e01e';
        }

        $audioDelayMs = (int) $request->query('audio_delay', 0);
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
                '-b:a', '192k',
                '-ac', '2',
                '-sn',
            ];
        }

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
                '-max_muxing_queue_size', '2048',
                '-movflags', 'frag_keyframe+empty_moov+default_base_moof',
                '-flush_packets', '1',
                '-f', 'mp4',
                'pipe:1',
            ]
        );

        $expectedMimeType = "video/mp4; codecs=\"{$mimeVideoCodec}, mp4a.40.2\"";

        return new StreamedResponse(function () use ($cmd, $nullDevice) {
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
            'Accept-Ranges' => 'none',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Range, Content-Type, Accept',
            'X-Stream-Start-Offset' => (string) $startSeconds,
            'X-Stream-Start-Seconds' => (string) $startSeconds,
            'X-MSE-Codec' => $expectedMimeType,
            'Access-Control-Expose-Headers' => 'Content-Type, X-Stream-Start-Offset, X-Stream-Start-Seconds, X-MSE-Codec',
        ]);
    }
}

