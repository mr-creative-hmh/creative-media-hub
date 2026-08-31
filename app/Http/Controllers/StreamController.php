<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Subtitle;
use App\Models\WatchHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
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

        if (!$path || !File::exists($path)) {
            return response("WEBVTT\n\n", 200, ['Content-Type' => 'text/vtt; charset=utf-8']);
        }

        $content = File::get($path);

        if ($subtitle->format === 'srt' || !str_starts_with(trim($content), 'WEBVTT')) {
            $content = "WEBVTT\n\n" . preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/', '$1.$2', $content);
        }

        return response($content, 200, [
            'Content-Type' => 'text/vtt; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
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

        $history = WatchHistory::updateOrCreate([
            'user_id' => $userId,
            'watchable_id' => $validated['watchable_id'],
            'watchable_type' => $modelClass,
        ], [
            'progress_seconds' => $validated['progress_seconds'],
            'duration_seconds' => $validated['duration_seconds'],
            'is_completed' => $isCompleted,
            'last_watched_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'history' => $history,
        ]);
    }

    public function getContinueWatching()
    {
        $userId = Auth::id();

        $query = WatchHistory::where('is_completed', false)
            ->where('progress_seconds', '>', 10)
            ->with(['watchable']);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $histories = $query->orderByDesc('last_watched_at')
            ->limit(10)
            ->get();

        $items = $histories->map(function ($h) {
            $watchable = $h->watchable;
            if (!$watchable) return null;

            $isEpisode = $h->watchable_type === Episode::class;
            $title = $isEpisode ? ($watchable->series->title ?? '') . " - S{$watchable->season_id}E{$watchable->episode_number}" : $watchable->title;
            $titleAr = $isEpisode ? ($watchable->series->title_ar ?? '') . " - حلقة {$watchable->episode_number}" : $watchable->title_ar;

            return [
                'id' => $h->id,
                'watchable_id' => $h->watchable_id,
                'watchable_type' => $isEpisode ? 'episode' : 'movie',
                'title' => $title,
                'title_ar' => $titleAr,
                'progress_seconds' => $h->progress_seconds,
                'duration_seconds' => $h->duration_seconds,
                'percent' => round(($h->progress_seconds / max(1, $h->duration_seconds)) * 100),
                'backdrop_path' => $isEpisode ? ($watchable->series->backdrop_path ?? $watchable->still_path) : $watchable->backdrop_path,
                'poster_path' => $isEpisode ? ($watchable->series->poster_path ?? null) : $watchable->poster_path,
                'last_watched_at' => $h->last_watched_at->diffForHumans(),
            ];
        })->filter()->values();

        return response()->json($items);
    }

    protected function streamFileRange(string $path, Request $request)
    {
        $size = filesize($path);
        $start = 0;
        $end = $size - 1;
        $length = $size;
        $status = 200;
        $headers = [
            'Content-Type' => 'video/mp4',
            'Accept-Ranges' => 'bytes',
        ];

        if ($request->hasHeader('Range')) {
            $range = $request->header('Range');
            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = (int) $matches[1];
                if (!empty($matches[2])) {
                    $end = (int) $matches[2];
                }
                $length = $end - $start + 1;
                $status = 206;
                $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
            }
        }

        $headers['Content-Length'] = (string) $length;

        $response = new StreamedResponse(function () use ($path, $start, $length) {
            $handle = fopen($path, 'rb');
            fseek($handle, $start);
            $chunkSize = 1024 * 1024; // 1MB buffer
            $remaining = $length;

            while (!feof($handle) && $remaining > 0 && (connection_status() === CONNECTION_NORMAL)) {
                $readLength = min($chunkSize, $remaining);
                echo fread($handle, $readLength);
                flush();
                $remaining -= $readLength;
            }

            fclose($handle);
        }, $status, $headers);

        return $response;
    }

    protected function streamWithAacTranscode(string $path, Request $request)
    {
        $ffmpegBin = AppSetting::where('key', 'ffmpeg_path')->value('value') ?: 'ffmpeg';
        $start = (int) $request->input('start', 0);

        // FFmpeg on-the-fly fast audio transcode to AAC with zero video re-encoding
        $escapedPath = escapeshellarg($path);
        $seekFlag = $start > 0 ? "-ss {$start}" : "";
        $cmd = "{$ffmpegBin} {$seekFlag} -i {$escapedPath} -c:v copy -c:a aac -b:a 192k -ac 2 -f mp4 -movflags frag_keyframe+empty_moov pipe:1";

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            // Fallback to direct range stream if FFmpeg process cannot be spawned
            return $this->streamFileRange($path, $request);
        }

        fclose($pipes[0]);

        return new StreamedResponse(function () use ($pipes, $process) {
            while (!feof($pipes[1]) && (connection_status() === CONNECTION_NORMAL)) {
                $chunk = fread($pipes[1], 1024 * 64);
                if ($chunk !== false && strlen($chunk) > 0) {
                    echo $chunk;
                    flush();
                }
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
}
