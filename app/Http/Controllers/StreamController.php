<?php

namespace App\Http\Controllers;

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

    /**
     * Direct high-performance streaming for movies.
     * Uses pure HTTP 206 Byte-Range partial content without FFmpeg transcoding overhead.
     */
    public function streamMovie(MediaItem $mediaItem, Request $request)
    {
        $filePath = $mediaItem->file_path;

        if ($filePath && File::exists($filePath)) {
            return $this->streamFileRange($filePath, $request);
        }

        $sampleUrl = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';
        return redirect()->away($sampleUrl);
    }

    /**
     * Direct high-performance streaming for series episodes.
     * Uses pure HTTP 206 Byte-Range partial content without FFmpeg transcoding overhead.
     */
    public function streamEpisode(Episode $episode, Request $request)
    {
        $filePath = $episode->file_path;

        if ($filePath && File::exists($filePath)) {
            return $this->streamFileRange($filePath, $request);
        }

        $sampleUrl = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/TearsOfSteel.mp4';
        return redirect()->away($sampleUrl);
    }

    /**
     * Stream WebVTT subtitle track.
     */
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
                    'progress_seconds' => $h->progress_seconds,
                    'duration_seconds' => $h->duration_seconds,
                    'progress_percent' => $percent,
                    'subtitles' => $item->subtitles,
                    'last_watched_at' => $h->last_watched_at,
                ];
            }

            if ($item instanceof Episode) {
                $item->loadMissing(['series', 'subtitles']);
                $series = $item->series;
                $percent = $h->duration_seconds > 0 ? min(100, round(($h->progress_seconds / $h->duration_seconds) * 100)) : 0;
                $seriesTitle = $series ? ($series->title_ar ?: $series->title) : 'Series';
                $epTitle = $item->title_ar ?: $item->title;

                return [
                    'id' => $item->id,
                    'watchable_id' => $item->id,
                    'watchable_type' => 'episode',
                    'title' => "{$seriesTitle} - S{$item->season_number}E{$item->episode_number} - {$epTitle}",
                    'type' => 'episode',
                    'poster_path' => $item->still_path ?: ($series ? $series->poster_path : null),
                    'backdrop_path' => $series ? $series->backdrop_path : null,
                    'progress_seconds' => $h->progress_seconds,
                    'duration_seconds' => $h->duration_seconds,
                    'progress_percent' => $percent,
                    'subtitles' => $item->subtitles,
                    'last_watched_at' => $h->last_watched_at,
                ];
            }

            return null;
        })->filter()->values();

        return response()->json([
            'success' => true,
            'items' => $history,
        ]);
    }

    public function getMediaDuration(Request $request)
    {
        $type = $request->input('type', 'movie');
        $id = (int) $request->input('id');

        $model = $type === 'episode' ? Episode::find($id) : MediaItem::find($id);
        if (!$model || !$model->file_path || !File::exists($model->file_path)) {
            return response()->json(['duration_seconds' => 0, 'runtime_minutes' => 0]);
        }

        if ($model->runtime_minutes && $model->runtime_minutes > 0) {
            return response()->json([
                'duration_seconds' => $model->runtime_minutes * 60,
                'runtime_minutes' => $model->runtime_minutes,
            ]);
        }

        $ffprobe = FfmpegLocatorService::getFfprobePath();
        if ($ffprobe) {
            $escaped = escapeshellarg($model->file_path);
            $cmd = escapeshellarg($ffprobe) . " -v quiet -print_format json -show_format {$escaped}";
            $out = @shell_exec($cmd);
            if ($out) {
                $data = @json_decode($out, true);
                if (!empty($data['format']['duration']) && is_numeric($data['format']['duration'])) {
                    $secs = (int) round((float) $data['format']['duration']);
                    $mins = max(1, (int) round($secs / 60));
                    $model->update(['runtime_minutes' => $mins]);
                    return response()->json([
                        'duration_seconds' => $secs,
                        'runtime_minutes' => $mins,
                    ]);
                }
            }
        }

        return response()->json(['duration_seconds' => 1320, 'runtime_minutes' => 22]);
    }

    /**
     * High-speed direct byte-range file streaming.
     * Complies with HTTP 206 Partial Content for native browser hardware playback and instant seeking.
     */
    protected function streamFileRange(string $path, Request $request)
    {
        $size = filesize($path);
        $file = fopen($path, 'rb');

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contentType = match ($ext) {
            'mp4', 'm4v' => 'video/mp4',
            'mkv' => 'video/mp4', // Serving as video/mp4 enables direct hardware decoding in modern browsers
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
            'Cache-Control' => 'public, max-age=3600',
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
            $chunkSize = 1024 * 256; // 256KB buffer for ultra-smooth throughput

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

    protected function convertToCleanWebVTT(string $content, string $format = 'srt'): string
    {
        $vtt = "WEBVTT\n\n";
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Normalize SRT timestamps to WebVTT
        $normalized = preg_replace_callback(
            '/(\d{2}:\d{2}:\d{2}),(\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2}),(\d{3})/',
            function ($m) {
                return "{$m[1]}.{$m[2]} --> {$m[3]}.{$m[4]}";
            },
            $content
        );

        return $vtt . $normalized;
    }
}
