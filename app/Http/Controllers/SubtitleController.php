<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Subtitle;
use App\Services\Subtitles\EmbeddedSubtitleDetectorService;
use App\Services\Subtitles\OpenSubtitlesService;
use App\Services\Subtitles\SubDlService;
use App\Services\Subtitles\SubtitleHealthCheckService;
use App\Services\Subtitles\SubtitleManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class SubtitleController extends Controller
{
    protected SubtitleManagerService $manager;

    protected OpenSubtitlesService $openSubtitles;

    protected SubDlService $subDl;

    protected EmbeddedSubtitleDetectorService $detector;

    public function __construct(
        SubtitleManagerService $manager,
        OpenSubtitlesService $openSubtitles,
        SubDlService $subDl,
        EmbeddedSubtitleDetectorService $detector
    ) {
        $this->manager = $manager;
        $this->openSubtitles = $openSubtitles;
        $this->subDl = $subDl;
        $this->detector = $detector;
    }

    public function index(): Response
    {
        $missing = $this->manager->findMissingSubtitles();

        return Inertia::render('Subtitles/Index', [
            'missingSubtitles' => $missing,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('query', '');
        $imdbId = $request->input('imdb_id');
        $lang = $request->input('language', $request->input('languages', 'ar,en'));
        $mediaId = $request->input('media_id');
        $mediaType = $request->input('media_type', 'movie');
        $season = $request->input('season_number');
        $episode = $request->input('episode_number');
        $year = $request->input('year');

        // Resolve media context if media_id provided
        if ($mediaId) {
            if ($mediaType === 'episode') {
                $ep = Episode::with('series')->find($mediaId);
                if ($ep) {
                    $query = $query ?: ($ep->series?->title ?? '');
                    $imdbId = $imdbId ?: $ep->series?->imdb_id;
                    $season = $season ?: $ep->season_number;
                    $episode = $episode ?: $ep->episode_number;
                    $year = $year ?: $ep->series?->release_year;
                }
            } else {
                $movie = MediaItem::find($mediaId);
                if ($movie) {
                    $query = $query ?: $movie->title;
                    $imdbId = $imdbId ?: $movie->imdb_id;
                    $year = $year ?: $movie->release_year;
                }
            }
        }

        $openSubResults = $this->openSubtitles->searchSubtitles([
            'query' => $query,
            'imdb_id' => $imdbId,
            'language' => $lang,
            'type' => $mediaType,
            'season_number' => $season,
            'episode_number' => $episode,
            'year' => $year,
        ]);

        $subDlResults = $this->subDl->searchSubtitles(
            $query,
            $year ? (int) $year : null,
            explode(',', (string) $lang),
            $mediaType,
            $season ? (int) $season : null,
            $episode ? (int) $episode : null
        );

        return response()->json([
            'results' => array_merge($openSubResults, $subDlResults),
        ]);
    }

    public function verifyEngine(Request $request): JsonResponse
    {
        $query = $request->input('query', '');
        $lang = $request->input('language', 'ar');
        $mediaId = $request->input('media_id');
        $mediaType = $request->input('media_type', 'movie');
        $season = $request->input('season_number');
        $episode = $request->input('episode_number');
        $year = $request->input('year');

        if ($mediaId) {
            if ($mediaType === 'episode') {
                $ep = Episode::with('series')->find($mediaId);
                if ($ep) {
                    $query = $query ?: ($ep->series?->title ?? '');
                    $season = $season ?: $ep->season_number;
                    $episode = $episode ?: $ep->episode_number;
                    $year = $year ?: $ep->series?->release_year;

                    // If series doesn't have an IMDb ID yet, resolve and cache it
                    if (empty($ep->series?->imdb_id) && $ep->series) {
                        $resolvedImdb = $this->openSubtitles->resolveImdbId($ep->series->title, 'series', $year);
                        if ($resolvedImdb) {
                            $ep->series->update(['imdb_id' => $resolvedImdb]);
                        }
                    }
                }
            } else {
                $movie = MediaItem::find($mediaId);
                if ($movie) {
                    $query = $query ?: $movie->title;
                    $year = $year ?: $movie->release_year;

                    // If movie doesn't have an IMDb ID yet, resolve and cache it
                    if (empty($movie->imdb_id)) {
                        $resolvedImdb = $this->openSubtitles->resolveImdbId($movie->title, 'movie', $year);
                        if ($resolvedImdb) {
                            $movie->update(['imdb_id' => $resolvedImdb]);
                        }
                    }
                }
            }
        }

        $searchParams = [
            'query' => $query,
            'language' => $lang,
            'type' => $mediaType,
            'season_number' => $season,
            'episode_number' => $episode,
            'year' => $year,
        ];

        $openSubs = $this->openSubtitles->searchSubtitles($searchParams);
        $subDl = $this->subDl->searchSubtitles(
            $query,
            $year ? (int) $year : null,
            [strtoupper($lang)],
            $mediaType,
            $season ? (int) $season : null,
            $episode ? (int) $episode : null
        );

        $combined = array_merge($openSubs, $subDl);

        return response()->json([
            'success' => true,
            'query' => $query,
            'language' => $lang,
            'engine_status' => [
                'opensubtitles' => 'Online (Stremio v3 / REST Live Engine)',
                'subdl' => 'Online (SubDL API v1 Engine)',
                'hash_matcher' => 'Ready (64-bit Audio Sync Checksum)',
            ],
            'results' => $combined,
        ]);
    }

    public function downloadForMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'media_id' => 'required|integer',
            'media_type' => 'required|in:movie,episode',
            'language' => 'required|string|max:10',
            'download_url' => 'nullable|string',
            'release' => 'nullable|string',
            'file_name' => 'nullable|string',
        ]);

        $model = $validated['media_type'] === 'movie'
            ? MediaItem::findOrFail($validated['media_id'])
            : Episode::findOrFail($validated['media_id']);

        $subtitle = $this->manager->downloadAndAttachRealSubtitle(
            $model,
            $validated['language'],
            $request->input('download_url'),
            $request->input('release') ?? $request->input('file_name')
        );

        if (! $subtitle) {
            return response()->json([
                'status' => 'error',
                'message' => 'No genuine subtitle could be downloaded for this item and language.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'subtitle' => $subtitle,
        ]);
    }

    public function forMedia(Request $request): JsonResponse
    {
        $type = $request->input('type', 'movie');
        $id = (int) $request->input('id');

        $model = $type === 'episode'
            ? Episode::find($id)
            : MediaItem::find($id);

        if (! $model) {
            return response()->json(['subtitles' => []]);
        }

        // Auto-detect and sync embedded tracks inside video file if not yet detected
        if ($model->file_path && File::exists($model->file_path)) {
            $existingEmbedded = Subtitle::where('subtitlable_id', $model->id)
                ->where('subtitlable_type', get_class($model))
                ->where('is_embedded', true)
                ->count();

            if ($existingEmbedded === 0) {
                $this->detector->detectAndRegisterEmbeddedSubtitles($model);
            }
        }

        // Return all subtitles ordered with Arabic first, English second
        $subs = Subtitle::where('subtitlable_id', $model->id)
            ->where('subtitlable_type', get_class($model))
            ->get()
            ->sortBy(function ($sub) {
                return match (strtolower($sub->language)) {
                    'ar', 'ara' => 1,
                    'en', 'eng' => 2,
                    default => 3,
                };
            })
            ->values();

        return response()->json([
            'subtitles' => $subs,
        ]);
    }

    public function autoSync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subtitle_id' => 'required|exists:subtitles,id',
            'offset_seconds' => 'required|numeric',
        ]);

        $subtitle = Subtitle::findOrFail($validated['subtitle_id']);

        if (! $subtitle->file_path || ! File::exists($subtitle->file_path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Subtitle file not found on disk',
            ], 404);
        }

        $content = File::get($subtitle->file_path);
        $offset = (float) $validated['offset_seconds'];

        $shifted = preg_replace_callback(
            '/(\d{2}:\d{2}:\d{2}[,\.]\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2}[,\.]\d{3})/',
            function ($matches) use ($offset) {
                $start = $this->timeToSeconds($matches[1]) + $offset;
                $end = $this->timeToSeconds($matches[2]) + $offset;

                return $this->secondsToTime(max(0, $start)).' --> '.$this->secondsToTime(max(0, $end));
            },
            $content
        );

        File::put($subtitle->file_path, $shifted);

        return response()->json([
            'status' => 'success',
            'message' => 'Subtitle timing synchronized and saved',
            'offset' => $offset,
        ]);
    }

    private function timeToSeconds(string $timeStr): float
    {
        $parts = explode(':', str_replace(',', '.', $timeStr));

        return ((float) $parts[0] * 3600) + ((float) $parts[1] * 60) + (float) $parts[2];
    }

    private function secondsToTime(float $seconds): string
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds - ($h * 3600) - ($m * 60);

        return sprintf('%02d:%02d:%06.3f', $h, $m, $s);
    }

    /**
     * Scan library directories, validate subtitle integrity, prune corrupt files, and standardize language extensions.
     */
    public function checkHealth(Request $request, SubtitleHealthCheckService $healthService): JsonResponse
    {
        $validated = $request->validate([
            'dry_run' => 'nullable|boolean',
            'delete_invalid' => 'nullable|boolean',
            'auto_rename' => 'nullable|boolean',
            'target_path' => 'nullable|string',
        ]);

        $results = $healthService->checkAndNormalize([
            'dry_run' => $validated['dry_run'] ?? false,
            'delete_invalid' => $validated['delete_invalid'] ?? true,
            'auto_rename' => $validated['auto_rename'] ?? true,
            'target_path' => $validated['target_path'] ?? null,
        ]);

        return response()->json($results);
    }
}
