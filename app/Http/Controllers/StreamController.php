<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Subtitle;
use App\Models\WatchHistory;
use App\Services\Media\FfmpegLocatorService;
use App\Services\Subtitles\EmbeddedSubtitleDetectorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    protected EmbeddedSubtitleDetectorService $embeddedSubDetector;

    public function __construct(EmbeddedSubtitleDetectorService $embeddedSubDetector)
    {
        $this->embeddedSubDetector = $embeddedSubDetector;
    }

    public function streamMovie(MediaItem $mediaItem, Request $request)
    {
        $filePath = $mediaItem->file_path;

        if ($filePath && File::exists($filePath)) {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $videoCodec = strtolower($mediaItem->video_codec ?? '');
            $audioCodec = strtolower($mediaItem->audio_codec ?? '');

            // Determine if transcode is needed for universal browser support
            $needsTranscode = $request->input('audio_mode') === 'aac'
                || $request->has('transcode')
                || in_array($ext, ['mkv', 'avi', 'wmv', 'flv', 'ts'])
                || str_contains($videoCodec, 'hevc')
                || str_contains($videoCodec, 'x265')
                || str_contains($videoCodec, '10bit')
                || str_contains($videoCodec, 'h.265')
                || str_contains($audioCodec, 'ddp')
                || str_contains($audioCodec, 'eac3')
                || str_contains($audioCodec, 'dts')
                || str_contains($audioCodec, 'ac3')
                || str_contains($audioCodec, 'truehd');

            if ($needsTranscode && FfmpegLocatorService::isAvailable()) {
                $needsVideoTranscode = str_contains($videoCodec, 'hevc')
                    || str_contains($videoCodec, 'x265')
                    || str_contains($videoCodec, '10bit')
                    || in_array($ext, ['avi', 'wmv', 'flv']);

                return $this->streamWithFfmpegUniversal($filePath, $request, $needsVideoTranscode);
            }

            return $this->streamFileRange($filePath, $request);
        }

        $sampleUrl = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';
        return redirect()->away($sampleUrl);
    }

    public function streamEpisode(Episode $episode, Request $request)
    {
        $filePath = $episode->file_path;

        if ($filePath && File::exists($filePath)) {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $videoCodec = strtolower($episode->video_codec ?? '');
            $audioCodec = strtolower($episode->audio_codec ?? '');

            $needsTranscode = $request->input('audio_mode') === 'aac'
                || $request->has('transcode')
                || in_array($ext, ['mkv', 'avi', 'wmv', 'flv', 'ts'])
                || str_contains($videoCodec, 'hevc')
                || str_contains($videoCodec, 'x265')
                || str_contains($videoCodec, '10bit')
                || str_contains($videoCodec, 'h.265')
                || str_contains($audioCodec, 'ddp')
                || str_contains($audioCodec, 'eac3')
                || str_contains($audioCodec, 'dts')
                || str_contains($audioCodec, 'ac3')
                || str_contains($audioCodec, 'truehd');

            if ($needsTranscode && FfmpegLocatorService::isAvailable()) {
                $needsVideoTranscode = str_contains($videoCodec, 'hevc')
                    || str_contains($videoCodec, 'x265')
                    || str_contains($videoCodec, '10bit')
                    || in_array($ext, ['avi', 'wmv', 'flv']);

                return $this->streamWithFfmpegUniversal($filePath, $request, $needsVideoTranscode);
            }

            return $this->streamFileRange($filePath, $request);
        }

        $sampleUrl = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/TearsOfSteel.mp4';
        return redirect()->away($sampleUrl);
    }

    public function streamSubtitle(Subtitle $subtitle)
    {
        $path = $subtitle->file_path;

        // Check if subtitle is embedded inside video container
        if ($subtitle->is_embedded || str_starts_with($path, 'embedded:')) {
            $parts = explode(':', $path, 3);
            $streamIndex = isset($parts[1]) ? (int) $parts[1] : 0;
            $videoPath = $parts[2] ?? '';

            if ($videoPath && File::exists($videoPath)) {
                $vtt = $this->embeddedSubDetector->extractToWebVtt($videoPath, $streamIndex, $subtitle->format ?? 'srt');
                return response($vtt, 200, [
                    'Content-Type' => 'text/vtt; charset=utf-8',
                    'Access-Control-Allow-Origin' => '*',
                    'Cache-Control' => 'no-cache',
                ]);
            }
        }

        // External Subtitle File
        if (!$path || !File::exists($path)) {
            return response("WEBVTT\n\n", 200, ['Content-Type' => 'text/vtt; charset=utf-8']);
        }

        $rawContent = File::get($path);
        $vtt = $this->convertToCleanWebVTT($rawContent, $subtitle->format ?? 'srt');

        return response($vtt, 200, [
            'Content-Type' => 'text/vtt; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function saveProgress(Request $request)
    {
        $validated = $request->validate([
            'watchable_id' => 'required|integer',
            'watchable_type' => 'required|string',
            'progress_seconds' => 'required|integer|min:0',
            'duration_seconds' => 'required|integer|min:1',
        ]);

        $modelClass = $validated['watchable_type'] === 'movie' ? MediaItem::class : Episode::class;
        $isCompleted = ($validated['progress_seconds'] / max(1, $validated['duration_seconds'])) >= 0.92;

        $userId = Auth::id();

        $watchHistory = WatchHistory::updateOrCreate(
            [
                'user_id' => $userId,
                'watchable_type' => $modelClass,
                'watchable_id' => $validated['watchable_id'],
            ],
            [
                'progress_seconds' => $validated['progress_seconds'],
                'duration_seconds' => $validated['duration_seconds'],
                'is_completed' => $isCompleted,
                'last_watched_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'progress' => $watchHistory,
        ]);
    }

    public function getContinueWatching(Request $request)
    {
        $userId = Auth::id();

        $query = WatchHistory::query()
            ->where('is_completed', false)
            ->where('progress_seconds', '>', 3)
            ->orderByDesc('last_watched_at')
            ->with(['watchable']);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $history = $query->limit(12)->get()->map(function ($h) {
            $item = $h->watchable;
            if (!$item) return null;

            if ($item instanceof MediaItem) {
                $item->loadMissing('subtitles');
                $percent = $h->duration_seconds > 0 ? min(100, round(($h->progress_seconds / $h->duration_seconds) * 100)) : 0;
                return [
                    'id' => $item->id,
                    'watchable_id' => $item->id,
                    'watchable_type' => 'movie',
                    'title' => $item->title,
                    'title_ar' => $item->title_ar,
                    'type' => 'movie',
                    'poster_path' => $item->poster_path,
                    'backdrop_path' => $item->backdrop_path,
                    'subtitles' => $item->subtitles,
                    'percent' => $percent,
                    'progress_seconds' => $h->progress_seconds,
                    'duration_seconds' => $h->duration_seconds,
                    'current_time_formatted' => gmdate('H:i:s', $h->progress_seconds),
                    'stream_url' => route('stream.movie', $item->id),
                ];
            } elseif ($item instanceof Episode) {
                $item->loadMissing(['subtitles', 'season.series']);
                $series = $item->season->series ?? null;
                $percent = $h->duration_seconds > 0 ? min(100, round(($h->progress_seconds / $h->duration_seconds) * 100)) : 0;
                return [
                    'id' => $item->id,
                    'watchable_id' => $item->id,
                    'watchable_type' => 'episode',
                    'title' => ($series ? $series->title . ' - ' : '') . 'S' . ($item->season->season_number ?? 1) . 'E' . $item->episode_number . ' ' . $item->title,
                    'title_ar' => ($series ? $series->title_ar . ' - ' : '') . $item->title_ar,
                    'type' => 'episode',
                    'poster_path' => $series->poster_path ?? null,
                    'backdrop_path' => $item->still_path ?? ($series->backdrop_path ?? null),
                    'subtitles' => $item->subtitles,
                    'percent' => $percent,
                    'progress_seconds' => $h->progress_seconds,
                    'duration_seconds' => $h->duration_seconds,
                    'current_time_formatted' => gmdate('H:i:s', $h->progress_seconds),
                    'stream_url' => route('stream.episode', $item->id),
                ];
            }
            return null;
        })->filter()->values();

        return response()->json($history);
    }

    protected function streamFileRange(string $path, Request $request)
    {
        $size = filesize($path);
        $file = fopen($path, 'rb');

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contentType = match ($ext) {
            'mp4', 'm4v' => 'video/mp4',
            'mkv' => 'video/x-matroska',
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
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ];

        if ($request->header('Range')) {
            $range = $request->header('Range');
            if (preg_match('/bytes=(\\d+)-(\\d+)?/', $range, $matches)) {
                $start = (int) $matches[1];
                if (!empty($matches[2])) {
                    $end = (int) $matches[2];
                }
                $status = 206;
                $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
            }
        }

        $length = $end - $start + 1;
        $headers['Content-Length'] = (string) $length;

        fseek($file, $start);

        return new StreamedResponse(function () use ($file, $length) {
            $remaining = $length;
            $chunkSize = 1024 * 128;

            while (!feof($file) && $remaining > 0 && (connection_status() === CONNECTION_NORMAL)) {
                $bytesToRead = min($chunkSize, $remaining);
                $buffer = fread($file, $bytesToRead);
                if ($buffer === false) break;

                echo $buffer;
                flush();
                $remaining -= strlen($buffer);
            }

            fclose($file);
        }, $status, $headers);
    }

    /**
     * High-speed universal streaming pipeline via FFmpeg.
     * Uses ultrafast H.264 + stereo AAC in fragmented MP4 format for 100% browser compatibility.
     */
    protected function streamWithFfmpegUniversal(string $path, Request $request, bool $transcodeVideo = true)
    {
        $ffmpegBin = FfmpegLocatorService::getFfmpegPath() ?: 'ffmpeg';

        $start = (int) $request->input('start', 0);
        $escapedPath = escapeshellarg($path);
        $escapedBin = escapeshellarg($ffmpegBin);
        $seekFlag = $start > 0 ? "-ss {$start}" : "";

        // Universal video encoding: if HEVC/10Bit, encode with ultrafast x264 yuv420p; if H264, copy bitstream
        $videoOpts = $transcodeVideo 
            ? "-c:v libx264 -preset ultrafast -tune zerolatency -crf 22 -pix_fmt yuv420p" 
            : "-c:v copy";

        $cmd = "{$escapedBin} -nostats -loglevel error -hide_banner {$seekFlag} -i {$escapedPath} {$videoOpts} -c:a aac -b:a 192k -ac 2 -f mp4 -movflags frag_keyframe+empty_moov+default_base_moof pipe:1";

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', 'NUL', 'a'],
        ];

        $process = @proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            return $this->streamFileRange($path, $request);
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        return new StreamedResponse(function () use ($pipes, $process) {
            if (isset($pipes[1]) && is_resource($pipes[1])) {
                while (!feof($pipes[1]) && (connection_status() === CONNECTION_NORMAL)) {
                    $chunk = fread($pipes[1], 1024 * 64);
                    if ($chunk !== false && strlen($chunk) > 0) {
                        echo $chunk;
                        flush();
                    }
                }
                fclose($pipes[1]);
            }
            if (is_resource($process)) {
                @proc_terminate($process);
                @proc_close($process);
            }
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function convertToCleanWebVTT(string $rawContent, string $format = 'srt'): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $rawContent);

        if (str_starts_with(trim($content), 'WEBVTT')) {
            return $content;
        }

        $vtt = "WEBVTT\n\n";

        if ($format === 'ass' || $format === 'ssa' || str_contains($content, '[Events]')) {
            $lines = explode("\n", $content);
            $inEvents = false;
            $eventFormat = [];

            foreach ($lines as $line) {
                $trimLine = trim($line);
                if ($trimLine === '[Events]') {
                    $inEvents = true;
                    continue;
                }

                if ($inEvents && str_starts_with($trimLine, 'Format:')) {
                    $eventFormat = array_map('trim', explode(',', substr($trimLine, 7)));
                    continue;
                }

                if ($inEvents && str_starts_with($trimLine, 'Dialogue:')) {
                    $parts = explode(',', substr($trimLine, 9), count($eventFormat));
                    if (count($parts) >= 9) {
                        $startIdx = array_search('Start', $eventFormat) ?: 1;
                        $endIdx = array_search('End', $eventFormat) ?: 2;
                        $textIdx = array_search('Text', $eventFormat) ?: (count($parts) - 1);

                        $start = $this->convertAssTimestampToVtt($parts[$startIdx] ?? '00:00:00.00');
                        $end = $this->convertAssTimestampToVtt($parts[$endIdx] ?? '00:00:05.00');
                        $text = $parts[$textIdx] ?? '';

                        $styledText = $this->convertAssDialogueToStyledVtt($text);

                        if ($styledText !== '') {
                            $vtt .= "{$start} --> {$end}\n{$styledText}\n\n";
                        }
                    }
                }
            }
            return $vtt;
        }

        $blocks = preg_split('/\n\s*\n/', trim($content));
        foreach ($blocks as $block) {
            $bLines = explode("\n", trim($block));
            if (count($bLines) >= 2) {
                $timeLine = $bLines[0];
                $textLines = array_slice($bLines, 1);

                if (is_numeric(trim($timeLine)) && isset($bLines[1])) {
                    $timeLine = $bLines[1];
                    $textLines = array_slice($bLines, 2);
                }

                if (str_contains($timeLine, '-->')) {
                    $vttTime = str_replace(',', '.', $timeLine);
                    $subText = implode("\n", $textLines);
                    $vtt .= "{$vttTime}\n{$subText}\n\n";
                }
            }
        }

        return $vtt;
    }

    protected function convertAssDialogueToStyledVtt(string $text): string
    {
        $text = str_replace(['\\N', '\\n'], "\n", $text);

        $text = preg_replace('/\\{\\\\b1\\}/i', '<b>', $text);
        $text = preg_replace('/\\{\\\\b0\\}/i', '</b>', $text);

        $text = preg_replace('/\\{\\\\i1\\}/i', '<i>', $text);
        $text = preg_replace('/\\{\\\\i0\\}/i', '</i>', $text);

        $text = preg_replace('/\\{\\\\u1\\}/i', '<u>', $text);
        $text = preg_replace('/\\{\\\\u0\\}/i', '</u>', $text);

        $colorOpen = false;
        $text = preg_replace_callback('/\\{\\\\(?:1?c)&H([0-9a-fA-F]+)&?\\}/i', function ($matches) use (&$colorOpen) {
            $hex = $this->assColorToRgbHex($matches[1]);
            $prefix = $colorOpen ? '</font>' : '';
            $colorOpen = true;
            return "{$prefix}<font color=\"{$hex}\">";
        }, $text);

        $text = preg_replace_callback('/\\{\\\\(?:1?c)\\}/i', function () use (&$colorOpen) {
            if ($colorOpen) {
                $colorOpen = false;
                return '</font>';
            }
            return '';
        }, $text);

        if ($colorOpen) {
            $text .= '</font>';
        }

        $text = preg_replace('/\\{[^}]+\\}/', '', $text);

        return trim($text);
    }

    protected function assColorToRgbHex(string $assHex): string
    {
        $cleaned = trim($assHex, '&Hh');
        if (strlen($cleaned) === 8) {
            $cleaned = substr($cleaned, 2);
        }
        $padded = str_pad($cleaned, 6, '0', STR_PAD_LEFT);
        $b = substr($padded, 0, 2);
        $g = substr($padded, 2, 2);
        $r = substr($padded, 4, 2);

        return strtoupper("#{$r}{$g}{$b}");
    }

    protected function convertAssTimestampToVtt(string $assTime): string
    {
        $assTime = trim($assTime);
        $parts = explode(':', $assTime);

        if (count($parts) === 3) {
            $h = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            $m = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
            $secParts = explode('.', $parts[2]);
            $s = str_pad($secParts[0] ?? '00', 2, '0', STR_PAD_LEFT);
            $ms = str_pad($secParts[1] ?? '000', 3, '0', STR_PAD_RIGHT);
            $ms = substr($ms, 0, 3);

            return "{$h}:{$m}:{$s}.{$ms}";
        }

        return '00:00:00.000';
    }
}
