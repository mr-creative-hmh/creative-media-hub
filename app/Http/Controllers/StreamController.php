<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Subtitle;
use App\Models\WatchHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    public function streamMovie(MediaItem $mediaItem, Request $request)
    {
        $filePath = $mediaItem->file_path;

        if ($filePath && File::exists($filePath)) {
            return $this->streamFileRange($filePath, $request);
        }

        // High quality fallback sample stream if physical file not on current machine
        $sampleUrl = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';
        return redirect()->away($sampleUrl);
    }

    public function streamEpisode(Episode $episode, Request $request)
    {
        $filePath = $episode->file_path;

        if ($filePath && File::exists($filePath)) {
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
            // Return empty WebVTT
            return response("WEBVTT\n\n", 200, ['Content-Type' => 'text/vtt; charset=utf-8']);
        }

        $content = File::get($path);

        // Convert SRT to WebVTT format if needed
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
}
