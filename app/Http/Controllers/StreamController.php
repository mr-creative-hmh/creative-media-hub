<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Subtitle;
use App\Services\Media\FfmpegLocatorService;
use App\Services\Streaming\StreamMseService;
use App\Services\Streaming\StreamRemuxService;
use App\Services\Streaming\SubtitleStreamService;
use App\Services\Streaming\WatchHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class StreamController extends Controller
{
    public function __construct(
        protected StreamRemuxService $remuxService,
        protected StreamMseService $mseService,
        protected SubtitleStreamService $subtitleService,
        protected WatchHistoryService $watchHistoryService
    ) {}

    /**
     * Stream movie directly via HTTP 206 Partial Content byte ranges.
     */
    public function streamMovie(MediaItem $mediaItem, Request $request)
    {
        $filePath = $mediaItem->file_path;

        if ($filePath && File::exists($filePath)) {
            return $this->remuxService->streamFileRange($filePath, $request);
        }

        $sampleUrl = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';

        return redirect()->away($sampleUrl);
    }

    /**
     * Stream episode directly via HTTP 206 Partial Content byte ranges.
     */
    public function streamEpisode(Episode $episode, Request $request)
    {
        $filePath = $episode->file_path;

        if ($filePath && File::exists($filePath)) {
            return $this->remuxService->streamFileRange($filePath, $request);
        }

        $sampleUrl = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/TearsOfSteel.mp4';

        return redirect()->away($sampleUrl);
    }

    /**
     * Stream clean WebVTT subtitle track.
     */
    public function streamSubtitle(Subtitle $subtitle, Request $request)
    {
        return $this->subtitleService->streamSubtitle($subtitle, $request);
    }

    /**
     * Stream movie with real-time FFmpeg remuxing / AAC audio transcode.
     */
    public function streamRemuxMovie(MediaItem $mediaItem, Request $request)
    {
        return $this->remuxService->streamRemuxFile(
            $mediaItem->file_path,
            $request,
            $mediaItem->video_codec,
            $mediaItem->audio_codec,
            'media',
            $mediaItem->id
        );
    }

    /**
     * Stream episode with real-time FFmpeg remuxing / AAC audio transcode.
     */
    public function streamRemuxEpisode(Episode $episode, Request $request)
    {
        return $this->remuxService->streamRemuxFile(
            $episode->file_path,
            $request,
            $episode->video_codec,
            $episode->audio_codec,
            'episode',
            $episode->id
        );
    }

    /**
     * Stream movie formatted as an ISO BMFF fMP4 stream tailored for MediaSource Extensions (MSE).
     */
    public function streamMseMovie(MediaItem $mediaItem, Request $request)
    {
        return $this->mseService->streamMseFile(
            $mediaItem->file_path,
            $request,
            $mediaItem->video_codec,
            $mediaItem->audio_codec,
            'media',
            $mediaItem->id
        );
    }

    /**
     * Stream episode formatted as an ISO BMFF fMP4 stream tailored for MediaSource Extensions (MSE).
     */
    public function streamMseEpisode(Episode $episode, Request $request)
    {
        return $this->mseService->streamMseFile(
            $episode->file_path,
            $request,
            $episode->video_codec,
            $episode->audio_codec,
            'episode',
            $episode->id
        );
    }

    /**
     * Save playback progress.
     */
    public function saveProgress(Request $request): JsonResponse
    {
        return $this->watchHistoryService->saveProgress($request);
    }

    /**
     * Get watch history / continue-watching items.
     */
    public function getWatchHistory(Request $request): JsonResponse
    {
        return $this->watchHistoryService->getWatchHistory($request);
    }

    /**
     * In-player playlist generator.
     */
    public function getPlaylist(Request $request): JsonResponse
    {
        return $this->watchHistoryService->getPlaylist($request);
    }

    /**
     * Delete watch history entry.
     */
    public function deleteWatchHistory(Request $request, $id): JsonResponse
    {
        return $this->watchHistoryService->deleteWatchHistory($request, $id);
    }

    /**
     * Clear all watch history.
     */
    public function clearWatchHistory(Request $request): JsonResponse
    {
        return $this->watchHistoryService->clearWatchHistory($request);
    }

    /**
     * Watch history Inertia page.
     */
    public function watchHistoryPage(Request $request): Response
    {
        return Inertia::render('WatchHistory/Index');
    }

    /**
     * Cache status of remuxed stream.
     */
    public function getCacheStatus(Request $request): JsonResponse
    {
        $type = $request->input('type', 'movie');
        $id = (int) $request->input('id');

        $model = $type === 'episode' ? Episode::find($id) : MediaItem::find($id);
        if (! $model || ! $model->file_path) {
            return response()->json(['is_cached' => false, 'cached_percent' => 0]);
        }

        $status = $this->remuxService->getCacheStatus($model->file_path, $type, $id);

        return response()->json($status);
    }

    /**
     * Stop active stream worker.
     */
    public function stopStream(Request $request): JsonResponse
    {
        $type = $request->input('type', 'movie');
        $id = (int) $request->input('id');

        $this->remuxService->stopStream($type, $id);

        return response()->json(['success' => true]);
    }

    /**
     * Seek keyframe alignment endpoint.
     */
    public function getSeekKeyframe(Request $request): JsonResponse
    {
        $type = $request->input('type', 'movie');
        $id = (int) $request->input('id');
        $time = max(0, (float) $request->input('time', 0));

        if ($time <= 2.0) {
            return response()->json(['keyframe' => 0.0, 'requested' => $time]);
        }

        $model = $type === 'episode' ? Episode::find($id) : MediaItem::find($id);
        if (! $model || ! $model->file_path || ! file_exists($model->file_path)) {
            return response()->json(['keyframe' => $time, 'requested' => $time]);
        }

        $keyframe = $this->remuxService->findPrecedingKeyframe($model->file_path, $time);

        return response()->json([
            'keyframe' => $keyframe,
            'requested' => $time,
        ]);
    }

    /**
     * Get or probe media duration.
     */
    public function getMediaDuration(Request $request): JsonResponse
    {
        $type = $request->input('type', 'movie');
        $id = (int) $request->input('id');

        $model = $type === 'episode' ? Episode::find($id) : MediaItem::find($id);
        if (! $model) {
            return response()->json(['duration_seconds' => 0, 'runtime_minutes' => 0, 'resolution' => null]);
        }

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

                        if (empty($model->resolution) || $model->resolution === 'Unknown') {
                            foreach ($data['streams'] ?? [] as $st) {
                                if (($st['codec_type'] ?? '') === 'video') {
                                    $w = (int) ($st['width'] ?? 0);
                                    $h = (int) ($st['height'] ?? 0);
                                    if ($w > 0 && $h > 0) {
                                        $model->resolution = $this->calculateProbeResolution($w, $h);
                                        break;
                                    }
                                }
                            }
                        }

                        $model->saveQuietly();

                        return response()->json([
                            'duration_seconds' => $secs,
                            'runtime_minutes' => $mins,
                            'resolution' => $model->resolution,
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'duration_seconds' => ($model->runtime_minutes ?: 45) * 60,
            'runtime_minutes' => $model->runtime_minutes ?: 45,
            'resolution' => $model->resolution,
        ]);
    }

    protected function calculateProbeResolution(int $width, int $height): string
    {
        $maxDim = max($width, $height);
        $minDim = min($width, $height);

        if ($maxDim >= 3800 || $minDim >= 2100) {
            return '4K UHD';
        }
        if ($maxDim >= 2500 || $minDim >= 1400) {
            return '1440p 2K';
        }
        if ($maxDim >= 1900 || $minDim >= 1000) {
            return '1080p FHD';
        }
        if ($maxDim >= 1200 || $minDim >= 700) {
            return '720p HD';
        }
        if ($minDim >= 540) {
            return '576p SD';
        }
        if ($minDim >= 450) {
            return '480p SD';
        }

        return "{$width}x{$height}";
    }

    // Compatibility proxies
    public function findPrecedingKeyframe(string $filePath, float $time): float
    {
        return $this->remuxService->findPrecedingKeyframe($filePath, $time);
    }

    public function buildPlaylistForWatchable($item): array
    {
        return $this->watchHistoryService->buildPlaylistForWatchable($item);
    }

    public function convertToCleanWebVTT(string $content, string $format = 'srt', float $startSeconds = 0.0): string
    {
        return $this->subtitleService->convertToCleanWebVTT($content, $format, $startSeconds);
    }

    public function streamFileRange(string $path, Request $request)
    {
        return $this->remuxService->streamFileRange($path, $request);
    }

    public function streamRemuxFile(string $filePath, Request $request, ?string $videoCodec = null, ?string $audioCodec = null, string $modelType = 'media', int $modelId = 0)
    {
        return $this->remuxService->streamRemuxFile($filePath, $request, $videoCodec, $audioCodec, $modelType, $modelId);
    }
}
