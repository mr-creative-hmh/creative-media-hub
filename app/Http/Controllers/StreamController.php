<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Subtitle;
use App\Models\WatchHistory;
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
            if ($request->input('audio_mode') === 'aac' || $request->has('transcode')) {
                return $this->streamWithAacTranscode($filePath, $request);
            }
            return $this->streamFileRange($filePath, $request);
        }

        // High quality fallback sample stream
        $sampleUrl = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';
        return redirect()->away($sampleUrl);
    }

    public function streamEpisode(Episode $episode, Request $request)
    {
        $filePath = $episode->file_path;

        if ($filePath && File::exists($filePath)) {
            if ($request->input('audio_mode') === 'aac' || $request->has('transcode')) {
                return $this->streamWithAacTranscode($filePath, $request);
            }
            return $this->streamFileRange($filePath, $request);
        }

        // High quality fallback sample stream
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
        $isCompleted = ($validated['progress_seconds'] / max(1, $validated['duration_seconds'])) >= 0.90;

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

        $history = WatchHistory::where('user_id', $userId)
            ->where('is_completed', false)
            ->where('progress_seconds', '>', 30)
            ->orderByDesc('last_watched_at')
            ->with(['watchable'])
            ->limit(10)
            ->get();

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
            if (preg_match('/bytes=(\d+)-(\d+)?/', $range, $matches)) {
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
            $chunkSize = 1024 * 128; // 128 KB buffer

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

    protected function streamWithAacTranscode(string $path, Request $request)
    {
        $ffmpegBin = AppSetting::where('key', 'ffmpeg_path')->value('value') ?: 'ffmpeg';

        $isFfmpegAvailable = false;
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $testCmd = "where {$ffmpegBin} 2>NUL";
        } else {
            $testCmd = "which {$ffmpegBin} 2>/dev/null";
        }
        @exec($testCmd, $out, $code);
        if ($code === 0 && !empty($out)) {
            $isFfmpegAvailable = true;
        }

        if (!$isFfmpegAvailable) {
            return $this->streamFileRange($path, $request);
        }

        $start = (int) $request->input('start', 0);
        $escapedPath = escapeshellarg($path);
        $seekFlag = $start > 0 ? "-ss {$start}" : "";
        $cmd = "{$ffmpegBin} -nostats -loglevel error -hide_banner {$seekFlag} -i {$escapedPath} -c:v copy -c:a aac -b:a 192k -ac 2 -f mp4 -movflags frag_keyframe+empty_moov+default_base_moof pipe:1";

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
            @proc_close($process);
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Accept-Ranges' => 'none',
            'Cache-Control' => 'no-cache',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    protected function convertToCleanWebVTT(string $rawContent, string $format = 'srt'): string
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

                        // Strip SSA style tags like {\an8\c&H00FFFF&}
                        $cleanText = preg_replace('/\{[^}]+\}/', '', $text);
                        $cleanText = str_replace(['\N', '\n'], "\n", $cleanText);

                        $vtt .= "{$start} --> {$end}\n{$cleanText}\n\n";
                    }
                }
            }
            return $vtt;
        }

        // Standard SRT conversion: convert 00:01:20,500 to 00:01:20.500
        $cleanSrt = preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/', '$1.$2', $content);
        return $vtt . trim($cleanSrt) . "\n";
    }

    protected function convertAssTimestampToVtt(string $assTime): string
    {
        $parts = explode(':', trim($assTime));
        if (count($parts) === 3) {
            $hours = sprintf('%02d', (int) $parts[0]);
            $minutes = sprintf('%02d', (int) $parts[1]);
            $secsParts = explode('.', $parts[2]);
            $secs = sprintf('%02d', (int) ($secsParts[0] ?? 0));
            $millis = str_pad(substr($secsParts[1] ?? '000', 0, 3), 3, '0');

            return "{$hours}:{$minutes}:{$secs}.{$millis}";
        }
        return '00:00:00.000';
    }
}
