<?php

namespace App\Services\Streaming;

use App\Services\Media\FfmpegLocatorService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamMseService
{
    public function __construct(
        protected ?StreamRemuxService $remuxService = null
    ) {}

    /**
     * Stream media file as an ISO BMFF fragmented MP4 (fMP4) stream tailored for MediaSource Extensions (MSE).
     * Uses zero-transcode video copy (-c:v copy) for H.264/AVC and browser-supported HEVC/AV1/VP9,
     * achieving 0% server video CPU overhead while converting incompatible audio into AAC stereo.
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

        ['command' => $cmd, 'mime_type' => $expectedMimeType, 'start_seconds' => $startSeconds] = $this->buildFfmpegCommand(
            $filePath,
            $request,
            $videoCodec,
            $audioCodec,
            $modelType,
            $modelId
        );

        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $nullDevice = $isWin ? 'NUL' : '/dev/null';

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

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

                $chunksSent = 0;
                while (! feof($pipes[1])) {
                    $readSize = $chunksSent < 16 ? 65536 : 262144;
                    $chunk = fread($pipes[1], $readSize);
                    if ($chunk !== false && strlen($chunk) > 0) {
                        echo $chunk;
                        if (ob_get_level() > 0) {
                            @ob_flush();
                        }
                        flush();
                        $chunksSent++;
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

    /**
     * Build the FFmpeg fMP4 command arguments, MIME codec string, and seek offset.
     *
     * @return array{command: array<int, string>, mime_type: string, start_seconds: float}
     */
    public function buildFfmpegCommand(
        string $filePath,
        Request $request,
        ?string $videoCodec = null,
        ?string $audioCodec = null,
        string $modelType = 'media',
        int $modelId = 0
    ): array {
        $ffmpegPath = FfmpegLocatorService::getFfmpegPath() ?? 'ffmpeg';
        $startSeconds = max(0, (float) $request->query('start', 0));

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $vc = strtolower($videoCodec ?? '');
        $ac = strtolower($audioCodec ?? '');

        $capHevc = $request->query('cap_hevc') === '1';
        $capAv1 = $request->query('cap_av1') === '1';
        $capVp9 = $request->query('cap_vp9') === '1';

        // REA bitstream inspection: Detect if video can be copied directly without transcode
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
        $isNativeVp9 = $capVp9 && str_contains($vc, 'vp9');

        $forceTranscode = $request->query('transcode') === '1';
        $isCopyVideo = ! $forceTranscode && ($isNativeH264 || $isNativeHevc || $isNativeAv1 || $isNativeVp9);

        $videoArgs = [];
        $mimeVideoCodec = 'avc1.42e01e';

        if ($isCopyVideo) {
            $videoArgs = ['-c:v', 'copy'];
            if ($isNativeHevc) {
                $videoArgs[] = '-tag:v';
                $videoArgs[] = 'hvc1';
                $mimeVideoCodec = 'hvc1.1.6.L93.B0';
            } elseif ($isNativeAv1) {
                $mimeVideoCodec = 'av01.0.04M.08';
            } elseif ($isNativeVp9) {
                $mimeVideoCodec = 'vp09.00.10.08';
            } else {
                $mimeVideoCodec = 'avc1.640028';
            }
        } else {
            $videoArgs = FfmpegLocatorService::getVideoEncodeArgs();
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
            // In video stream-copy seeks, avoid pinning audio to first_pts=0 independently
            // of the preceding video keyframe so -avoid_negative_ts make_zero aligns both tracks together.
            if ($isCopyVideo && $startSeconds > 0) {
                $audioFilters[] = 'aresample=async=1';
            } else {
                $audioFilters[] = 'aresample=async=1:first_pts=0';
            }
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

        $seekArgs = [];
        if ($startSeconds > 0) {
            if ($isCopyVideo) {
                $seekArgs = ['-noaccurate_seek', '-ss', (string) $startSeconds];
            } else {
                $seekArgs = ['-ss', (string) $startSeconds];
            }
        }

        $cmd = array_merge(
            [
                $ffmpegPath,
                '-nostdin',
                '-hide_banner',
                '-loglevel', 'error',
                '-fflags', '+nobuffer+genpts+discardcorrupt',
                '-probesize', '1000000',
                '-analyzeduration', '1000000',
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
                '-movflags', 'frag_keyframe+empty_moov+default_base_moof+separate_moof',
                '-frag_duration', '500000',
                '-min_frag_duration', '200000',
                '-flush_packets', '1',
                '-f', 'mp4',
                'pipe:1',
            ]
        );

        return [
            'command' => $cmd,
            'mime_type' => "video/mp4; codecs=\"{$mimeVideoCodec}, mp4a.40.2\"",
            'start_seconds' => $startSeconds,
        ];
    }
}
