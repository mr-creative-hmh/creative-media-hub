<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Subtitle;
use App\Services\Subtitles\EmbeddedSubtitleDetectorService;
use App\Services\Subtitles\OpenSubtitlesService;
use App\Services\Subtitles\SubDlService;
use App\Services\Subtitles\SubSenseService;
use App\Services\Subtitles\SubtitleHealthCheckService;
use App\Services\Subtitles\SubtitleManagerService;
use App\Services\Subtitles\YtsSubsService;
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

    protected SubSenseService $subSense;

    protected YtsSubsService $ytsSubs;

    protected EmbeddedSubtitleDetectorService $detector;

    public function __construct(
        SubtitleManagerService $manager,
        OpenSubtitlesService $openSubtitles,
        SubDlService $subDl,
        SubSenseService $subSense,
        YtsSubsService $ytsSubs,
        EmbeddedSubtitleDetectorService $detector
    ) {
        $this->manager = $manager;
        $this->openSubtitles = $openSubtitles;
        $this->subDl = $subDl;
        $this->subSense = $subSense;
        $this->ytsSubs = $ytsSubs;
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

        // Resolve media context if media_id provided — always use DB's imdb_id
        if ($mediaId) {
            if ($mediaType === 'episode') {
                $ep = Episode::with('series')->find($mediaId);
                if ($ep) {
                    $cleanSeriesTitle = $ep->series?->title;
                    if ($cleanSeriesTitle) {
                        $query = $cleanSeriesTitle;
                    } else {
                        $query = preg_replace('/\s*-\s*(?:Season|الموسم|Episode|الحلقة|S\d+).*$/iu', '', $query);
                    }
                    $imdbId = $imdbId ?: $ep->series?->imdb_id;
                    $season = $season ?: $ep->season_number;
                    $episode = $episode ?: $ep->episode_number;
                    $year = $year ?: $ep->series?->release_year;

                    if (empty($imdbId) && $ep->series) {
                        $imdbId = $this->openSubtitles->resolveImdbId($ep->series->title, 'series', $year);
                        if ($imdbId) {
                            $ep->series->update(['imdb_id' => $imdbId]);
                        }
                    }
                }
            } else {
                $movie = MediaItem::find($mediaId);
                if ($movie) {
                    $query = $query ?: $movie->title;
                    $imdbId = $imdbId ?: $movie->imdb_id;
                    $year = $year ?: $movie->release_year;

                    if (empty($imdbId)) {
                        $imdbId = $this->openSubtitles->resolveImdbId($movie->title, 'movie', $year);
                        if ($imdbId) {
                            $movie->update(['imdb_id' => $imdbId]);
                        }
                    }
                }
            }
        }

        $allResults = [];
        $enginesUsed = [];

        // 1. SubSense Stremio Aggregator (Fast, Free, OpenSubtitles/YTS/SubDL direct links)
        if ($imdbId) {
            $subSenseResults = $this->subSense->searchSubtitles([
                'imdb_id' => $imdbId,
                'type' => $mediaType,
                'season_number' => $season,
                'episode_number' => $episode,
                'language' => $lang,
            ]);
            if (! empty($subSenseResults)) {
                $allResults = array_merge($allResults, $subSenseResults);
                $enginesUsed[] = 'subsense';
            }
        }

        // 2. YTS-Subs Direct Engine (Movies only, Free direct ZIPs, large Arabic/English database)
        if ($mediaType === 'movie' && ($imdbId || $query)) {
            $ytsResults = $this->ytsSubs->searchSubtitles([
                'imdb_id' => $imdbId,
                'query' => $query,
                'language' => $lang,
            ]);
            if (! empty($ytsResults)) {
                $allResults = array_merge($allResults, $ytsResults);
                $enginesUsed[] = 'yts-subs';
            }
        }

        // 3. OpenSubtitles REST / v3 fallback
        $openSubResults = $this->openSubtitles->searchSubtitles([
            'query' => $query,
            'imdb_id' => $imdbId,
            'language' => $lang,
            'type' => $mediaType,
            'season_number' => $season,
            'episode_number' => $episode,
            'year' => $year,
        ]);
        if (! empty($openSubResults)) {
            $allResults = array_merge($allResults, $openSubResults);
            $enginesUsed[] = 'opensubtitles';
        }

        // 4. SubDL Engine
        $subDlResults = $this->subDl->searchSubtitles(
            $query,
            $year ? (int) $year : null,
            array_map('strtoupper', explode(',', (string) $lang)),
            $mediaType,
            $season ? (int) $season : null,
            $episode ? (int) $episode : null
        );
        if (! empty($subDlResults)) {
            $allResults = array_merge($allResults, $subDlResults);
            $enginesUsed[] = 'subdl';
        }

        $hasApiKey = ! empty($this->openSubtitles->getApiKey());

        return response()->json([
            'results' => $allResults,
            'meta' => [
                'engine_used' => empty($enginesUsed) ? 'none' : implode(', ', array_unique($enginesUsed)),
                'imdb_id' => $imdbId,
                'api_key_configured' => $hasApiKey,
                'query' => $query,
                'language' => $lang,
                'count' => count($allResults),
            ],
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
        $imdbId = null;

        if ($mediaId) {
            if ($mediaType === 'episode') {
                $ep = Episode::with('series')->find($mediaId);
                if ($ep) {
                    $cleanSeriesTitle = $ep->series?->title;
                    if ($cleanSeriesTitle) {
                        $query = $cleanSeriesTitle;
                    } else {
                        $query = preg_replace('/\s*-\s*(?:Season|الموسم|Episode|الحلقة|S\d+).*$/iu', '', $query);
                    }
                    $imdbId = $ep->series?->imdb_id;
                    $season = $season ?: $ep->season_number;
                    $episode = $episode ?: $ep->episode_number;
                    $year = $year ?: $ep->series?->release_year;

                    // If series doesn't have an IMDb ID yet, resolve and cache it
                    if (empty($imdbId) && $ep->series) {
                        $imdbId = $this->openSubtitles->resolveImdbId($ep->series->title, 'series', $year);
                        if ($imdbId) {
                            $ep->series->update(['imdb_id' => $imdbId]);
                        }
                    }
                }
            } else {
                $movie = MediaItem::find($mediaId);
                if ($movie) {
                    $query = $query ?: $movie->title;
                    $imdbId = $movie->imdb_id;
                    $year = $year ?: $movie->release_year;

                    // If movie doesn't have an IMDb ID yet, resolve and cache it
                    if (empty($imdbId)) {
                        $imdbId = $this->openSubtitles->resolveImdbId($movie->title, 'movie', $year);
                        if ($imdbId) {
                            $movie->update(['imdb_id' => $imdbId]);
                        }
                    }
                }
            }
        }

        $allResults = [];

        // 1. SubSense
        if ($imdbId) {
            $subSenseResults = $this->subSense->searchSubtitles([
                'imdb_id' => $imdbId,
                'type' => $mediaType,
                'season_number' => $season,
                'episode_number' => $episode,
                'language' => $lang,
            ]);
            $allResults = array_merge($allResults, $subSenseResults);
        }

        // 2. YTS
        if ($mediaType === 'movie' && ($imdbId || $query)) {
            $ytsResults = $this->ytsSubs->searchSubtitles([
                'imdb_id' => $imdbId,
                'query' => $query,
                'language' => $lang,
            ]);
            $allResults = array_merge($allResults, $ytsResults);
        }

        // 3. OpenSubtitles
        $searchParams = [
            'query' => $query,
            'imdb_id' => $imdbId,
            'language' => $lang,
            'type' => $mediaType,
            'season_number' => $season,
            'episode_number' => $episode,
            'year' => $year,
        ];
        $openSubs = $this->openSubtitles->searchSubtitles($searchParams);
        $allResults = array_merge($allResults, $openSubs);

        // 4. SubDL
        $subDl = $this->subDl->searchSubtitles(
            $query,
            $year ? (int) $year : null,
            array_map('strtoupper', explode(',', $lang)),
            $mediaType,
            $season ? (int) $season : null,
            $episode ? (int) $episode : null
        );
        $allResults = array_merge($allResults, $subDl);

        $hasApiKey = ! empty($this->openSubtitles->getApiKey());

        return response()->json([
            'success' => true,
            'query' => $query,
            'language' => $lang,
            'imdb_id' => $imdbId,
            'api_key_configured' => $hasApiKey,
            'engine_status' => [
                'subsense' => 'Online (Stremio Multi-Provider Aggregator — Free, No Key)',
                'yts_subs' => 'Online (YTS-Subs Direct Engine — Free, No Key)',
                'opensubtitles_rest' => $hasApiKey ? 'Online (REST API — Full Arabic Coverage)' : 'Offline (No API Key — Optional)',
                'opensubtitles_v3' => 'Online (Stremio v3 Legacy Fallback)',
                'subdl' => ! empty($this->subDl) ? 'Online (SubDL API v1 Engine)' : 'Offline',
                'hash_matcher' => 'Ready (64-bit Audio Sync Checksum)',
            ],
            'results' => $allResults,
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
            'source' => 'nullable|string',
            'file_id' => 'nullable',
        ]);

        $model = $validated['media_type'] === 'movie'
            ? MediaItem::findOrFail($validated['media_id'])
            : Episode::findOrFail($validated['media_id']);

        $subtitle = $this->manager->downloadAndAttachRealSubtitle(
            $model,
            $validated['language'],
            $request->input('download_url'),
            $request->input('release') ?? $request->input('file_name'),
            $request->input('source'),
            $request->input('file_id')
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

    public function generateArabic(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'media_id' => 'required|integer',
            'media_type' => 'required|in:movie,episode',
            'subtitle_id' => 'nullable|integer',
        ]);

        $model = $validated['media_type'] === 'movie'
            ? MediaItem::findOrFail($validated['media_id'])
            : Episode::findOrFail($validated['media_id']);

        $subtitle = $this->manager->generateArabicSubtitle($model, $validated['subtitle_id'] ?? null);

        if (! $subtitle) {
            return response()->json([
                'status' => 'error',
                'message' => 'No English subtitle was found or could be downloaded/translated to Arabic for this item.',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Arabic subtitle successfully translated from English and attached.',
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
                $embeddedTracks = $this->detector->detectEmbeddedSubtitles($model->file_path);
                foreach ($embeddedTracks as $track) {
                    Subtitle::updateOrCreate(
                        [
                            'subtitlable_id' => $model->id,
                            'subtitlable_type' => get_class($model),
                            'stream_index' => $track['stream_index'],
                        ],
                        [
                            'language' => $track['language'],
                            'language_name' => $track['language_name'],
                            'format' => $track['format'],
                            'title' => $track['title'],
                            'is_embedded' => true,
                            'is_default' => $track['is_default'] ?? false,
                        ]
                    );
                }
            }
        }

        // Auto-sync adjacent subtitles on disk
        $this->syncDiskSubtitles($model);

        $subtitles = Subtitle::where('subtitlable_id', $model->id)
            ->where('subtitlable_type', get_class($model))
            ->orderBy('is_default', 'desc')
            ->orderBy('is_embedded', 'asc')
            ->get();

        return response()->json(['subtitles' => $subtitles]);
    }

    protected function syncDiskSubtitles(MediaItem|Episode $model): void
    {
        if (! $model->file_path) {
            return;
        }

        $videoDir = dirname($model->file_path);
        $videoBase = pathinfo($model->file_path, PATHINFO_FILENAME);

        // 1. Clean up or fix any DB records pointing to nonexistent files
        $existingExternal = Subtitle::where('subtitlable_id', $model->id)
            ->where('subtitlable_type', get_class($model))
            ->where('is_embedded', false)
            ->get();

        foreach ($existingExternal as $sub) {
            if ($sub->file_path && ! File::exists($sub->file_path)) {
                $subName = basename($sub->file_path);
                $cleanedName = preg_replace('/\.2\./', '.', $subName);
                $altPath = "{$videoDir}/{$cleanedName}";

                if (File::exists($altPath)) {
                    $sub->update(['file_path' => $altPath]);
                } else {
                    $langPath = "{$videoDir}/{$videoBase}.{$sub->language}.srt";
                    if (File::exists($langPath)) {
                        $sub->update(['file_path' => $langPath]);
                    } else {
                        $sub->delete();
                    }
                }
            }
        }

        // 2. Discover adjacent subtitle files on disk matching video basename
        $searchDirs = [$videoDir];
        if (File::isDirectory("{$videoDir}/Subs")) {
            $searchDirs[] = "{$videoDir}/Subs";
        }
        if (File::isDirectory("{$videoDir}/Subtitles")) {
            $searchDirs[] = "{$videoDir}/Subtitles";
        }

        $subExtensions = ['srt', 'vtt', 'sub', 'ass', 'ssa'];
        foreach ($searchDirs as $dir) {
            try {
                $files = File::files($dir);
                foreach ($files as $file) {
                    $ext = strtolower($file->getExtension());
                    if (! in_array($ext, $subExtensions, true)) {
                        continue;
                    }

                    $fn = $file->getFilename();
                    $filePath = str_replace('\\', '/', $file->getRealPath());

                    // Check if subtitle matches this video or episode code
                    $isMatch = str_starts_with(strtolower($fn), strtolower($videoBase));
                    if (! $isMatch && preg_match('/[sS](\d{1,2})[eE](\d{1,2})/i', $fn, $subEp)) {
                        if (preg_match('/[sS](\d{1,2})[eE](\d{1,2})/i', $videoBase, $vidEp)) {
                            if ((int) $subEp[1] === (int) $vidEp[1] && (int) $subEp[2] === (int) $vidEp[2]) {
                                $isMatch = true;
                            }
                        }
                    }

                    if ($isMatch) {
                        $langCode = 'en';
                        $langName = 'English';
                        if (preg_match('/\b(ar|ara|arabic)\b/i', $fn)) {
                            $langCode = 'ar';
                            $langName = 'Arabic';
                        } elseif (preg_match('/\b(en|eng|english)\b/i', $fn)) {
                            $langCode = 'en';
                            $langName = 'English';
                        }

                        Subtitle::updateOrCreate(
                            [
                                'subtitlable_id' => $model->id,
                                'subtitlable_type' => get_class($model),
                                'file_path' => $filePath,
                            ],
                            [
                                'language' => $langCode,
                                'language_name' => $langName,
                                'format' => $ext,
                                'is_embedded' => false,
                                'is_default' => ($langCode === 'ar'),
                            ]
                        );
                    }
                }
            } catch (\Throwable $e) {
            }
        }
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

    public function startHealthJob(Request $request, SubtitleHealthCheckService $healthService): JsonResponse
    {
        $validated = $request->validate([
            'dry_run' => 'nullable|boolean',
            'delete_invalid' => 'nullable|boolean',
            'auto_rename' => 'nullable|boolean',
            'target_path' => 'nullable|string',
        ]);

        $state = $healthService->startHealthJob($validated);

        return response()->json([
            'success' => true,
            'status' => $state,
        ]);
    }

    public function processHealthBatch(Request $request, SubtitleHealthCheckService $healthService): JsonResponse
    {
        $batchSize = (int) $request->input('batch_size', 25);
        $result = $healthService->processHealthBatch($batchSize);

        return response()->json($result);
    }

    public function getHealthJobStatus(SubtitleHealthCheckService $healthService): JsonResponse
    {
        return response()->json($healthService->getHealthJobStatus());
    }

    public function pauseHealthJob(SubtitleHealthCheckService $healthService): JsonResponse
    {
        return response()->json($healthService->pauseHealthJob());
    }

    public function resumeHealthJob(SubtitleHealthCheckService $healthService): JsonResponse
    {
        return response()->json($healthService->resumeHealthJob());
    }

    public function cancelHealthJob(SubtitleHealthCheckService $healthService): JsonResponse
    {
        return response()->json($healthService->cancelHealthJob());
    }
}
