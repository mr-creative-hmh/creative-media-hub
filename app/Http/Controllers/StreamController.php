<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Subtitle;
use App\Models\WatchHistory;
use App\Services\Media\FfmpegLocatorService;
use App\Services\Subtitles\EmbeddedSubtitleDetectorService;
use App\Services\Subtitles\SubtitleLanguageDetectorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    protected EmbeddedSubtitleDetectorService $embeddedSubDetector;

    protected SubtitleLanguageDetectorService $subLanguageDetector;

    public function __construct(
        EmbeddedSubtitleDetectorService $embeddedSubDetector,
        SubtitleLanguageDetectorService $subLanguageDetector
    ) {
        $this->embeddedSubDetector = $embeddedSubDetector;
        $this->subLanguageDetector = $subLanguageDetector;
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
        if (! $path || ! File::exists($path)) {
            if ($path) {
                $dir = dirname(str_replace('\\', '/', $path));
                $fn = basename($path);
                $candidates = [
                    $dir.'/'.preg_replace('/\.2\./', '.', $fn),
                    $dir.'/'.preg_replace('/(\.[a-z]{2,3})\.srt$/i', '.2$1.srt', $fn),
                ];
                foreach ($candidates as $cand) {
                    if (File::exists($cand)) {
                        $path = $cand;
                        $subtitle->update(['file_path' => $cand]);
                        break;
                    }
                }
            }
        }

        if (! $path || ! File::exists($path)) {
            return response("WEBVTT\n\n", 200, [
                'Content-Type' => 'text/vtt; charset=utf-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        }

        $rawContent = File::get($path);
        $cleanUtf8 = $this->subLanguageDetector->sanitizeToUtf8($rawContent);

        // Self-heal file on disk if it was non-UTF-8 and is writable
        if ($cleanUtf8 !== $rawContent && is_writable($path)) {
            @file_put_contents($path, $cleanUtf8);
        }

        $vtt = $this->convertToCleanWebVTT($cleanUtf8, $subtitle->format ?? 'srt');

        return response($vtt, 200, [
            'Content-Type' => 'text/vtt; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function saveProgress(Request $request)
    {
        $prog = (int) $request->input('progress_seconds', $request->input('position_seconds', 0));
        $dur = (int) $request->input('duration_seconds', 0);
        $wId = (int) $request->input('watchable_id', $request->input('id', 0));
        $wType = strtolower((string) $request->input('watchable_type', $request->input('type', 'movie')));

        if ($wId <= 0 || $dur <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid parameters'], 422);
        }

        $modelClass = ($wType === 'episode') ? Episode::class : MediaItem::class;
        $isCompleted = ($prog / max(1, $dur)) >= 0.92;

        // Clean up any stray duplicate rows if any exist
        $duplicates = WatchHistory::where('watchable_type', $modelClass)
            ->where('watchable_id', $wId)
            ->orderByDesc('id')
            ->get();

        if ($duplicates->count() > 1) {
            $keep = $duplicates->first();
            WatchHistory::where('watchable_type', $modelClass)
                ->where('watchable_id', $wId)
                ->where('id', '!=', $keep->id)
                ->delete();
        }

        $watchHistory = WatchHistory::updateOrCreate(
            [
                'watchable_type' => $modelClass,
                'watchable_id' => $wId,
            ],
            [
                'progress_seconds' => $prog,
                'duration_seconds' => $dur,
                'is_completed' => $isCompleted,
                'last_watched_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'progress' => $watchHistory,
        ]);
    }

    public function getWatchHistory(Request $request)
    {
        $type = $request->input('type', 'all');

        // Fetch all in-progress watch history items
        $rawHistory = WatchHistory::query()
            ->where('is_completed', false)
            ->where('progress_seconds', '>', 3)
            ->orderByDesc('last_watched_at')
            ->with(['watchable'])
            ->get();

        // 1. Deduplicate by watchable_type + watchable_id
        $deduped = $rawHistory->unique(function ($h) {
            return $h->watchable_type . '_' . $h->watchable_id;
        });

        // 2. Group episodes by series so only the latest watched episode of each series is shown
        $seriesSeen = [];
        $uniqueList = $deduped->filter(function ($h) use (&$seriesSeen) {
            $item = $h->watchable;
            if (! $item) {
                return false;
            }

            if ($item instanceof Episode) {
                $seriesId = $item->series_id ?? ($item->season?->series_id ?? 0);
                if ($seriesId > 0) {
                    if (isset($seriesSeen[$seriesId])) {
                        return false; // Skip older episodes for this series
                    }
                    $seriesSeen[$seriesId] = true;
                }
            }

            return true;
        });

        // 3. Compute accurate counts for category tabs
        $counts = [
            'all' => $uniqueList->count(),
            'movies' => $uniqueList->filter(fn ($h) => $h->watchable instanceof MediaItem)->count(),
            'series' => $uniqueList->filter(fn ($h) => $h->watchable instanceof Episode)->count(),
            'collections' => $uniqueList->filter(fn ($h) => $h->watchable instanceof MediaItem && !empty($h->watchable->collection_name))->count(),
        ];

        // 4. Filter by requested type if specified and not 'all'
        $filtered = $uniqueList;
        if ($type === 'movie' || $type === 'movies') {
            $filtered = $filtered->filter(fn ($h) => $h->watchable instanceof MediaItem);
        } elseif ($type === 'series' || $type === 'episode') {
            $filtered = $filtered->filter(fn ($h) => $h->watchable instanceof Episode);
        } elseif ($type === 'collection' || $type === 'collections') {
            $filtered = $filtered->filter(fn ($h) => $h->watchable instanceof MediaItem && !empty($h->watchable->collection_name));
        }

        // 5. Transform into rich UI objects
        $formatTime = function ($sec) {
            $hrs = floor($sec / 3600);
            $mins = floor(($sec % 3600) / 60);
            $secs = $sec % 60;
            return $hrs > 0 ? sprintf('%d:%02d:%02d', $hrs, $mins, $secs) : sprintf('%02d:%02d', $mins, $secs);
        };

        $items = $filtered->map(function ($h) use ($formatTime) {
            $item = $h->watchable;
            if (! $item) return null;

            $percent = $h->duration_seconds > 0 ? min(100, round(($h->progress_seconds / $h->duration_seconds) * 100)) : 0;
            $remaining = max(0, $h->duration_seconds - $h->progress_seconds);

            if ($item instanceof MediaItem) {
                $item->loadMissing('subtitles');
                $slug = $item->slug ?: "movie-{$item->id}";
                $isCollection = !empty($item->collection_name);

                return [
                    'history_id' => $h->id,
                    'id' => $item->id,
                    'watchable_id' => $item->id,
                    'watchable_type' => 'movie',
                    'category' => $isCollection ? 'collection' : 'movie',
                    'collection_name' => $item->collection_name,
                    'title' => $item->title,
                    'title_ar' => $item->title_ar,
                    'type' => 'movie',
                    'slug' => $slug,
                    'slug_url' => route('movies.show.slug', $slug),
                    'poster_path' => $item->poster_path,
                    'backdrop_path' => $item->backdrop_path,
                    'progress_seconds' => $h->progress_seconds,
                    'initial_progress' => $h->progress_seconds,
                    'duration_seconds' => $h->duration_seconds,
                    'progress_percent' => $percent,
                    'percent' => $percent,
                    'current_time_formatted' => $formatTime($h->progress_seconds),
                    'duration_formatted' => $formatTime($h->duration_seconds),
                    'remaining_formatted' => $formatTime($remaining),
                    'subtitles' => $item->subtitles,
                    'resolution' => $item->resolution,
                    'video_codec' => $item->video_codec,
                    'audio_codec' => $item->audio_codec,
                    'last_watched_at' => $h->last_watched_at ? $h->last_watched_at->toIso8601String() : null,
                    'stream_url' => route('stream.movie', $item->id),
                ];
            }

            if ($item instanceof Episode) {
                $item->loadMissing(['series', 'season', 'subtitles']);
                $series = $item->series ?? ($item->season->series ?? null);
                $sNameEn = $series ? $series->title : 'Series';
                $sNameAr = $series ? ($series->title_ar ?: $series->title) : 'مسلسل';
                $sNum = $item->season_number ?? ($item->season->season_number ?? 1);
                $eNum = $item->episode_number ?? 1;
                $seriesSlug = $series ? ($series->slug ?: "series-{$series->id}") : "series-{$item->series_id}";

                return [
                    'history_id' => $h->id,
                    'id' => $item->id,
                    'watchable_id' => $item->id,
                    'watchable_type' => 'episode',
                    'category' => 'series',
                    'series' => $series,
                    'series_id' => $series?->id ?? $item->series_id,
                    'series_title' => $sNameEn,
                    'series_title_ar' => $sNameAr,
                    'season_number' => $sNum,
                    'episode_number' => $eNum,
                    'episode_title' => $item->title,
                    'title' => "{$sNameEn} - Season {$sNum} - Episode {$eNum}",
                    'title_ar' => "{$sNameAr} - الموسم {$sNum} - الحلقة {$eNum}",
                    'type' => 'episode',
                    'series_slug' => $seriesSlug,
                    'slug_url' => route('series.episode.show', [$seriesSlug, $sNum, $eNum]),
                    'poster_path' => $item->still_path ?: ($series ? $series->poster_path : null),
                    'backdrop_path' => $series ? $series->backdrop_path : null,
                    'progress_seconds' => $h->progress_seconds,
                    'initial_progress' => $h->progress_seconds,
                    'duration_seconds' => $h->duration_seconds,
                    'progress_percent' => $percent,
                    'percent' => $percent,
                    'current_time_formatted' => $formatTime($h->progress_seconds),
                    'duration_formatted' => $formatTime($h->duration_seconds),
                    'remaining_formatted' => $formatTime($remaining),
                    'subtitles' => $item->subtitles,
                    'resolution' => $item->resolution,
                    'video_codec' => $item->video_codec,
                    'audio_codec' => $item->audio_codec,
                    'last_watched_at' => $h->last_watched_at ? $h->last_watched_at->toIso8601String() : null,
                    'stream_url' => route('stream.episode', $item->id),
                ];
            }

            return null;
        })->filter()->values();

        return response()->json([
            'success' => true,
            'counts' => $counts,
            'items' => $items,
        ]);
    }

    public function getContinueWatching(Request $request)
    {
        return $this->getWatchHistory($request);
    }

    public function deleteWatchHistory(Request $request, $id)
    {
        $type = $request->input('type');

        // Check if $id matches WatchHistory primary ID
        $record = WatchHistory::find($id);
        if ($record) {
            $record->delete();
            return response()->json([
                'success' => true,
                'message' => 'Item removed from watch history',
            ]);
        }

        // Otherwise check if watchable_id and watchable_type match
        $modelClass = ($type === 'series' || $type === 'episode') ? Episode::class : MediaItem::class;

        WatchHistory::where('watchable_id', $id)
            ->when($type, fn ($q) => $q->where('watchable_type', $modelClass))
            ->delete();

        // If removing an entire series by series_id
        if ($type === 'series' || $request->boolean('is_series')) {
            $epIds = Episode::where('series_id', $id)->pluck('id');
            WatchHistory::where('watchable_type', Episode::class)->whereIn('watchable_id', $epIds)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Item removed from watch history',
        ]);
    }

    public function clearWatchHistory(Request $request)
    {
        $type = $request->input('type', 'all');

        if ($type === 'movie' || $type === 'movies') {
            WatchHistory::where('watchable_type', MediaItem::class)->delete();
        } elseif ($type === 'series' || $type === 'episode') {
            WatchHistory::where('watchable_type', Episode::class)->delete();
        } elseif ($type === 'collection' || $type === 'collections') {
            $colIds = MediaItem::whereNotNull('collection_name')->where('collection_name', '!=', '')->pluck('id');
            WatchHistory::where('watchable_type', MediaItem::class)->whereIn('watchable_id', $colIds)->delete();
        } else {
            WatchHistory::query()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Watch history cleared successfully',
        ]);
    }

    public function watchHistoryPage(Request $request): \Inertia\Response
    {
        return \Inertia\Inertia::render('WatchHistory/Index');
    }

    public function getCacheStatus(Request $request)
    {
        $type = $request->input('type', 'movie');
        $id = (int) $request->input('id');

        $model = $type === 'episode' ? Episode::find($id) : MediaItem::find($id);
        if (! $model || ! $model->file_path || ! file_exists($model->file_path)) {
            return response()->json(['is_cached' => false, 'cached_percent' => 0]);
        }

        $mtime = filemtime($model->file_path);
        $cacheDir = storage_path('app/cache/media_streams');
        $cacheKey = "stream_{$type}_{$id}_{$mtime}";
        $cachedFile = "{$cacheDir}/{$cacheKey}.mp4";
        $partFile1 = "{$cacheDir}/{$cacheKey}.part.mp4";
        $partFile2 = "{$cacheDir}/{$cacheKey}.mp4.part";

        if (file_exists($cachedFile) && filesize($cachedFile) > 1024 * 1024) {
            return response()->json([
                'is_cached' => true,
                'cached_percent' => 100,
                'file_size' => filesize($cachedFile),
            ]);
        }

        $activePart = file_exists($partFile1) ? $partFile1 : (file_exists($partFile2) ? $partFile2 : null);
        if ($activePart) {
            $origSize = filesize($model->file_path);
            $partSize = filesize($activePart);
            $pct = $origSize > 0 ? min(99, max(5, round(($partSize / $origSize) * 100))) : 0;

            return response()->json([
                'is_cached' => false,
                'cached_percent' => $pct,
                'part_size' => $partSize,
            ]);
        }

        return response()->json(['is_cached' => false, 'cached_percent' => 0]);
    }

    public function stopStream(Request $request)
    {
        $type = $request->input('type', 'movie');
        $id = (int) $request->input('id');

        $cacheDir = storage_path('app/cache/media_streams');
        if (is_dir($cacheDir)) {
            $pattern = "{$cacheDir}/stream_{$type}_{$id}_*.lock";
            foreach (glob($pattern) as $lock) {
                @unlink($lock);
            }
        }

        return response()->json(['success' => true]);
    }

    public function getMediaDuration(Request $request)
    {
        $type = $request->input('type', 'movie');
        $id = (int) $request->input('id');

        $model = $type === 'episode' ? Episode::find($id) : MediaItem::find($id);
        if (! $model) {
            return response()->json(['duration_seconds' => 0, 'runtime_minutes' => 0, 'resolution' => null]);
        }

        // Return cached exact duration from database if present
        if (! empty($model->duration_seconds) && $model->duration_seconds > 0) {
            return response()->json([
                'duration_seconds' => (float) $model->duration_seconds,
                'runtime_minutes' => max(1, (int) round($model->duration_seconds / 60)),
                'resolution' => $model->resolution,
            ]);
        }

        if (! $model->file_path || ! File::exists($model->file_path)) {
            $fallbackMins = $model->runtime_minutes ?: 45;

            return response()->json([
                'duration_seconds' => $fallbackMins * 60,
                'runtime_minutes' => $fallbackMins,
                'resolution' => $model->resolution,
            ]);
        }

        // Directly probe the ORIGINAL video file on disk for exact seconds & technical specs
        $ffprobe = FfmpegLocatorService::getFfprobePath();
        if ($ffprobe) {
            $escaped = escapeshellarg($model->file_path);
            $cmd = escapeshellarg($ffprobe)." -v quiet -print_format json -show_format -show_streams {$escaped}";
            $out = @shell_exec($cmd);
            if ($out) {
                $data = @json_decode($out, true);
                if (! empty($data['format']['duration']) && is_numeric($data['format']['duration'])) {
                    $secs = (float) $data['format']['duration'];
                    if ($secs > 0) {
                        $mins = max(1, (int) round($secs / 60));
                        $model->duration_seconds = (int) round($secs);
                        $model->runtime_minutes = $mins;

                        // Check resolution if missing or Unknown
                        if (empty($model->resolution) || $model->resolution === 'Unknown') {
                            foreach ($data['streams'] ?? [] as $st) {
                                if (($st['codec_type'] ?? '') === 'video') {
                                    $w = (int) ($st['width'] ?? 0);
                                    $h = (int) ($st['height'] ?? 0);
                                    if ($h > 0) {
                                        $model->resolution = $this->calculateProbeResolution($w, $h);
                                        break;
                                    }
                                }
                            }
                        }

                        $model->save();

                        return response()->json([
                            'duration_seconds' => $secs,
                            'runtime_minutes' => $mins,
                            'resolution' => $model->resolution,
                        ]);
                    }
                }
            }
        }

        $fallbackMins = $model->runtime_minutes ?: 45;

        return response()->json([
            'duration_seconds' => $fallbackMins * 60,
            'runtime_minutes' => $fallbackMins,
            'resolution' => $model->resolution,
        ]);
    }

    protected function calculateProbeResolution(int $width, int $height): string
    {
        $resolutions = [
            4320 => '8K UHD',
            2160 => '4K UHD',
            1440 => '1440p 2K',
            1080 => '1080p FHD',
            720 => '720p HD',
            576 => '576p SD',
            480 => '480p SD',
            360 => '360p',
            240 => '240p',
        ];

        foreach ($resolutions as $minHeight => $label) {
            if ($height >= $minHeight) {
                return $label;
            }
        }

        return "{$width}x{$height}";
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

        return new StreamedResponse(function () use ($file, $length) {
            $remaining = $length;
            $chunkSize = 1024 * 256; // 256KB buffer for ultra-smooth throughput

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

    protected function convertToCleanWebVTT(string $content, string $format = 'srt'): string
    {
        $content = $this->subLanguageDetector->sanitizeToUtf8($content);
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

        return $vtt.$normalized;
    }

    public function streamRemuxMovie(MediaItem $mediaItem, Request $request)
    {
        return $this->streamRemuxFile($mediaItem->file_path, $request, $mediaItem->video_codec, 'movie', $mediaItem->id);
    }

    public function streamRemuxEpisode(Episode $episode, Request $request)
    {
        return $this->streamRemuxFile($episode->file_path, $request, $episode->video_codec, 'episode', $episode->id);
    }

    protected function streamRemuxFile(string $filePath, Request $request, ?string $videoCodec = null, string $modelType = 'media', int $modelId = 0)
    {
        if (! file_exists($filePath)) {
            abort(404, 'Media file not found');
        }

        $ffmpegPath = FfmpegLocatorService::getFfmpegPath() ?? 'ffmpeg';
        $startSeconds = max(0, (float) $request->query('start', 0));

        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $nullDevice = $isWin ? 'NUL' : '/dev/null';

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $vc = strtolower($videoCodec ?? '');

        // Native H.264 (AVC) in MP4 or MKV can be copied directly with 0 CPU overhead.
        // All legacy formats (AVI, MPEG-4 / XviD, DivX, WMV, VC-1, MPEG-2) and 10-bit HEVC (H.265)
        // must be transcoded to standard 8-bit H.264 (yuv420p) so every browser can render video frames.
        $isNativeH264 = (
            str_contains($vc, 'h.264') ||
            str_contains($vc, 'h264') ||
            str_contains($vc, 'avc')
        ) && ! str_contains($vc, 'hevc') && ($ext !== 'avi');

        if ($isNativeH264 && $request->query('transcode') !== '1') {
            $videoArgs = ['-c:v', 'copy'];
        } else {
            $videoArgs = [
                '-c:v', 'libx264',
                '-preset', 'ultrafast',
                '-tune', 'zerolatency',
                '-crf', '22',
                '-pix_fmt', 'yuv420p',
            ];
        }

        $cacheDir = storage_path('app/cache/media_streams');
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $mtime = file_exists($filePath) ? filemtime($filePath) : 0;
        $cacheKey = "stream_{$modelType}_{$modelId}_{$mtime}";
        $cachedFile = "{$cacheDir}/{$cacheKey}.mp4";
        $lockFile = "{$cacheDir}/{$cacheKey}.lock";

        $audioDelayMs = (int) $request->query('audio_delay', 0);

        // If complete cached file is ready on disk and we are starting from 0 AND no audio delay was requested, stream directly with Byte-Range HTTP 206
        if (file_exists($cachedFile) && filesize($cachedFile) > 1024 * 1024 && $startSeconds == 0 && $audioDelayMs == 0) {
            return $this->streamFileRange($cachedFile, $request);
        }

        // Launch background transcode worker to cache the full file to disk so caching continues even when paused
        $partFile = "{$cacheDir}/{$cacheKey}.part.mp4";
        if (! file_exists($lockFile) && ! file_exists($cachedFile)) {
            @file_put_contents($lockFile, date('Y-m-d H:i:s'));
            $bgCmd = array_merge(
                [
                    escapeshellarg($ffmpegPath),
                    '-nostdin',
                    '-hide_banner',
                    '-loglevel', 'error',
                    '-y',
                    '-i', escapeshellarg($filePath),
                    '-map', '0:v:0',
                    '-map', '0:a:0?',
                ],
                $videoArgs,
                [
                    '-af', 'aresample=async=1000:min_hard_comp=0.100000',
                    '-c:a', 'aac',
                    '-b:a', '192k',
                    '-ac', '2',
                    '-sn',
                    '-avoid_negative_ts', 'make_zero',
                    '-max_muxing_queue_size', '1024',
                    '-movflags', '+faststart',
                    '-f', 'mp4',
                    escapeshellarg($partFile),
                ]
            );

            $bgCmdStr = implode(' ', $bgCmd);
            if ($isWin) {
                $batFile = "{$cacheDir}/run_{$cacheKey}.bat";
                $batWinCached = str_replace('/', '\\', $cachedFile);
                $batWinPart = str_replace('/', '\\', $partFile);
                $batWinLock = str_replace('/', '\\', $lockFile);
                $batWinBat = str_replace('/', '\\', $batFile);

                $batContent = "@echo off\r\n".$bgCmdStr."\r\nmove /Y \"{$batWinPart}\" \"{$batWinCached}\" > NUL 2>&1\r\ndel /F /Q \"{$batWinLock}\" \"{$batWinBat}\" > NUL 2>&1\r\n";
                @file_put_contents($batFile, $batContent);
                @pclose(popen("start \"\" /B cmd /c \"\"{$batFile}\"\"", 'r'));
            } else {
                $finalBgCmd = "({$bgCmdStr} && mv '{$partFile}' '{$cachedFile}' && rm -f '{$lockFile}') > /dev/null 2>&1 &";
                @exec($finalBgCmd);
            }
        }

        // Build audio filter chain supporting audio-to-video delay compensation
        $audioFilters = [];
        if ($audioDelayMs > 0) {
            // Audio leads video -> delay audio by $audioDelayMs
            $audioFilters[] = "adelay={$audioDelayMs}|{$audioDelayMs}";
        } elseif ($audioDelayMs < 0) {
            // Audio lags behind video -> advance audio by trimming the start
            $trimSec = abs($audioDelayMs) / 1000.0;
            $audioFilters[] = "atrim=start={$trimSec},asetpts=PTS-STARTPTS";
        }
        $audioFilters[] = 'aresample=async=1000:min_hard_comp=0.100000';
        $audioFilterStr = implode(',', $audioFilters);

        $cmd = array_merge(
            [
                $ffmpegPath,
                '-nostdin',
                '-hide_banner',
                '-loglevel', 'error',
                '-ss', (string) $startSeconds,
                '-i', $filePath,
                '-map', '0:v:0',
                '-map', '0:a:0?',
            ],
            $videoArgs,
            [
                '-af', $audioFilterStr,
                '-c:a', 'aac',
                '-b:a', '192k',
                '-ac', '2',
                '-sn',
                '-avoid_negative_ts', 'make_zero',
                '-max_muxing_queue_size', '1024',
                '-movflags', 'frag_keyframe+empty_moov+default_base_moof',
                '-flush_packets', '1',
                '-f', 'mp4',
                'pipe:1',
            ]
        );

        return response()->stream(function () use ($cmd, $nullDevice) {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['file', $nullDevice, 'w'],
            ];

            // Increase PHP execution time limit for long video playback streams
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
            'Access-Control-Expose-Headers' => 'Content-Length, Content-Range, Accept-Ranges',
        ]);
    }
}
