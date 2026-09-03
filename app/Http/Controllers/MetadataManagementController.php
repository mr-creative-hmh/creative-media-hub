<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Models\Subtitle;
use App\Services\Metadata\ArtworkDownloadService;
use App\Services\Metadata\MetadataAggregator;
use App\Services\Metadata\TmdbProvider;
use App\Services\Organizer\SceneNameParserService;
use App\Services\Scanner\VirtualLibraryScannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MetadataManagementController extends Controller
{
    protected VirtualLibraryScannerService $scannerService;

    protected MetadataAggregator $metadata;

    protected ArtworkDownloadService $artwork;

    protected SceneNameParserService $parser;

    public function __construct(
        VirtualLibraryScannerService $scannerService,
        MetadataAggregator $metadata,
        ArtworkDownloadService $artwork,
        SceneNameParserService $parser
    ) {
        $this->scannerService = $scannerService;
        $this->metadata = $metadata;
        $this->artwork = $artwork;
        $this->parser = $parser;
    }

    public function index(Request $request): Response
    {
        $filter = $request->input('filter', 'all'); // all, unmatched, missing_posters, missing_arabic, movies, series
        $search = $request->input('search');

        $moviesQuery = MediaItem::query()->with(['genres', 'subtitles']);
        $seriesQuery = Series::query()->with(['genres', 'seasons.episodes.subtitles']);

        if ($search) {
            $moviesQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%")
                    ->orWhere('file_path', 'like', "%{$search}%");
            });
            $seriesQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%")
                    ->orWhere('folder_path', 'like', "%{$search}%");
            });
        }

        match ($filter) {
            'unmatched' => [
                $moviesQuery->where(function ($q) {
                    $q->whereNull('tmdb_id')
                        ->orWhereNull('poster_path')
                        ->orWhere('overview', 'like', 'Enjoy watching%');
                }),
                $seriesQuery->where(function ($q) {
                    $q->whereNull('tmdb_id')
                        ->orWhereNull('poster_path')
                        ->orWhere('overview', 'like', 'Experience the complete series%');
                }),
            ],
            'missing_posters' => [
                $moviesQuery->whereNull('poster_path'),
                $seriesQuery->whereNull('poster_path'),
            ],
            'missing_arabic' => [
                $moviesQuery->whereNull('overview_ar')->orWhereNull('title_ar'),
                $seriesQuery->whereNull('overview_ar')->orWhereNull('title_ar'),
            ],
            'movies' => [
                $seriesQuery->whereRaw('1 = 0'),
            ],
            'series' => [
                $moviesQuery->whereRaw('1 = 0'),
            ],
            default => null,
        };

        $movies = $moviesQuery->orderByDesc('created_at')->limit(50)->get()->map(function ($m) {
            return [
                'id' => $m->id,
                'title' => $m->title,
                'title_ar' => $m->title_ar,
                'original_title' => $m->original_title,
                'type' => 'movie',
                'release_year' => $m->release_year,
                'rating' => $m->rating,
                'collection_name' => $m->collection_name,
                'poster_path' => $m->poster_path,
                'backdrop_path' => $m->backdrop_path,
                'overview' => $m->overview,
                'overview_ar' => $m->overview_ar,
                'tmdb_id' => $m->tmdb_id,
                'imdb_id' => $m->imdb_id,
                'has_poster' => ! empty($m->poster_path),
                'has_arabic' => ! empty($m->title_ar) || ! empty($m->overview_ar),
                'is_matched' => ! empty($m->tmdb_id),
                'subtitles_count' => $m->subtitles->count(),
                'file_path' => $m->file_path,
            ];
        });

        $seriesList = $seriesQuery->orderByDesc('created_at')->limit(50)->get()->map(function ($s) {
            $episodesCount = $s->seasons->sum(fn ($sea) => $sea->episodes->count());

            return [
                'id' => $s->id,
                'title' => $s->title,
                'title_ar' => $s->title_ar,
                'original_title' => $s->original_title,
                'type' => 'series',
                'release_year' => $s->release_year,
                'rating' => $s->rating,
                'poster_path' => $s->poster_path,
                'backdrop_path' => $s->backdrop_path,
                'overview' => $s->overview,
                'overview_ar' => $s->overview_ar,
                'tmdb_id' => $s->tmdb_id,
                'imdb_id' => $s->imdb_id,
                'has_poster' => ! empty($s->poster_path),
                'has_arabic' => ! empty($s->title_ar) || ! empty($s->overview_ar),
                'is_matched' => ! empty($s->tmdb_id),
                'seasons_count' => $s->seasons->count(),
                'episodes_count' => $episodesCount,
                'file_path' => $s->folder_path,
            ];
        });

        $combined = $movies->concat($seriesList)->values();

        $stats = [
            'total_items' => MediaItem::count() + Series::count(),
            'unmatched_count' => MediaItem::whereNull('tmdb_id')->count() + Series::whereNull('tmdb_id')->count(),
            'missing_posters_count' => MediaItem::whereNull('poster_path')->count() + Series::whereNull('poster_path')->count(),
            'missing_arabic_count' => MediaItem::whereNull('overview_ar')->count() + Series::whereNull('overview_ar')->count(),
            'movies_count' => MediaItem::count(),
            'series_count' => Series::count(),
        ];

        return Inertia::render('Metadata/Index', [
            'items' => $combined,
            'stats' => $stats,
            'filters' => [
                'filter' => $filter,
                'search' => $search,
            ],
        ]);
    }

    public function lookupId(Request $request): JsonResponse
    {
        $id = trim((string) $request->input('id'));
        $type = strtolower((string) $request->input('type', 'movie'));

        if (empty($id)) {
            return response()->json(['success' => false, 'message' => 'Please provide an ID.'], 422);
        }

        // Clean up prefixes (e.g. tmdb:123 or tt0241527)
        $cleanId = preg_replace('/^(tmdb:|imdb:)/i', '', $id);

        if ($type === 'series' || $type === 'tv') {
            $details = $this->metadata->getSeriesDetails($cleanId, 'tmdb');
        } else {
            $details = $this->metadata->getMovieDetails($cleanId, 'tmdb');
        }

        if (! $details) {
            return response()->json(['success' => false, 'message' => 'No media found with that ID.'], 404);
        }

        return response()->json([
            'success' => true,
            'details' => $details,
        ]);
    }

    public function reparseItem(Request $request, string $type, int $id): JsonResponse
    {
        if ($type === 'movie') {
            $item = MediaItem::findOrFail($id);
            $parsed = $this->parser->parse($item->file_path ?? $item->title);
            $cleanTitle = $parsed['clean_title'] ?? $parsed['title'];
            $year = $parsed['year'] ?? $item->release_year;

            $meta = $this->metadata->aggregateMovieMetadata($cleanTitle, $year);

            $item->update([
                'title' => $meta['title'] ?? $cleanTitle,
                'title_ar' => $meta['title_ar'] ?? $item->title_ar,
                'release_year' => $meta['release_year'] ?? $year,
                'overview' => $meta['overview'] ?? $item->overview,
                'overview_ar' => $meta['overview_ar'] ?? $item->overview_ar,
                'tmdb_id' => $meta['tmdb_id'] ?? $item->tmdb_id,
                'imdb_id' => $meta['imdb_id'] ?? $item->imdb_id,
                'poster_path' => $meta['poster_path'] ?? $item->poster_path,
                'backdrop_path' => $meta['backdrop_path'] ?? $item->backdrop_path,
                'collection_name' => $meta['collection_name'] ?? TmdbProvider::inferCollectionFromTitle($cleanTitle),
                'rating' => $meta['rating'] ?? $item->rating,
                'runtime_minutes' => $meta['runtime_minutes'] ?? $item->runtime_minutes,
            ]);

            return response()->json(['success' => true, 'message' => "Reparsed as: {$cleanTitle} ({$year})", 'item' => $item]);
        } else {
            $item = Series::findOrFail($id);
            $parsed = $this->parser->parse($item->title);
            $cleanTitle = $parsed['series_title'] ?? ($parsed['clean_title'] ?? $item->title);
            $year = $parsed['year'] ?? $item->release_year;

            $meta = $this->metadata->aggregateSeriesMetadata($cleanTitle, $year);

            $item->update([
                'title' => $meta['title'] ?? $cleanTitle,
                'title_ar' => $meta['title_ar'] ?? $item->title_ar,
                'release_year' => $meta['release_year'] ?? $year,
                'overview' => $meta['overview'] ?? $item->overview,
                'overview_ar' => $meta['overview_ar'] ?? $item->overview_ar,
                'tmdb_id' => $meta['tmdb_id'] ?? $item->tmdb_id,
                'imdb_id' => $meta['imdb_id'] ?? $item->imdb_id,
                'poster_path' => $meta['poster_path'] ?? $item->poster_path,
                'backdrop_path' => $meta['backdrop_path'] ?? $item->backdrop_path,
                'rating' => $meta['rating'] ?? $item->rating,
            ]);

            return response()->json(['success' => true, 'message' => "Reparsed as: {$cleanTitle} ({$year})", 'item' => $item]);
        }
    }

    public function convertType(Request $request, string $type, int $id): JsonResponse
    {
        if ($type === 'movie') {
            // Convert Movie to Series
            $movie = MediaItem::findOrFail($id);
            $filePath = $movie->file_path;
            $title = $movie->title;

            $series = Series::create([
                'title' => $title,
                'title_ar' => $movie->title_ar,
                'release_year' => $movie->release_year,
                'overview' => $movie->overview,
                'overview_ar' => $movie->overview_ar,
                'rating' => $movie->rating,
                'poster_path' => $movie->poster_path,
                'backdrop_path' => $movie->backdrop_path,
                'status' => 'Continuing',
            ]);

            $season = Season::create([
                'series_id' => $series->id,
                'season_number' => 1,
                'name' => 'Season 1',
            ]);

            Episode::create([
                'season_id' => $season->id,
                'episode_number' => 1,
                'title' => $title,
                'file_path' => $filePath,
                'resolution' => $movie->resolution,
                'video_codec' => $movie->video_codec,
                'audio_codec' => $movie->audio_codec,
                'runtime_minutes' => $movie->runtime_minutes,
            ]);

            $movie->delete();

            return response()->json([
                'success' => true,
                'message' => "Converted '{$title}' to Series.",
                'new_type' => 'series',
                'new_id' => $series->id,
            ]);
        } else {
            // Convert Series to Movie
            $series = Series::with('episodes')->findOrFail($id);
            $firstEp = $series->episodes->first();
            $filePath = $firstEp?->file_path ?? '';

            $movie = MediaItem::create([
                'title' => $series->title,
                'title_ar' => $series->title_ar,
                'release_year' => $series->release_year,
                'overview' => $series->overview,
                'overview_ar' => $series->overview_ar,
                'rating' => $series->rating,
                'poster_path' => $series->poster_path,
                'backdrop_path' => $series->backdrop_path,
                'file_path' => $filePath,
                'resolution' => $firstEp?->resolution ?? '1080p FHD',
                'video_codec' => $firstEp?->video_codec ?? 'H.264',
                'audio_codec' => $firstEp?->audio_codec ?? 'AAC',
                'runtime_minutes' => $firstEp?->runtime_minutes ?? 110,
            ]);

            $series->delete();

            return response()->json([
                'success' => true,
                'message' => "Converted '{$series->title}' to Movie.",
                'new_type' => 'movie',
                'new_id' => $movie->id,
            ]);
        }
    }

    public function renameFile(Request $request, string $type, int $id): JsonResponse
    {
        if ($type === 'movie') {
            $item = MediaItem::find($id);
            if (! $item) {
                return response()->json(['success' => false, 'message' => 'Movie not found.'], 404);
            }

            $oldPath = str_replace('\\', '/', $item->file_path);
            if (! file_exists($oldPath)) {
                return response()->json([
                    'success' => false,
                    'message' => "Physical file does not exist on disk: {$oldPath}",
                ], 404);
            }

            $dir = dirname($oldPath);
            $ext = pathinfo($oldPath, PATHINFO_EXTENSION);

            $cleanTitle = trim(preg_replace('~[\\/:*?"<>|]~', ' ', $item->title));
            $cleanTitle = preg_replace('/\s+/', ' ', $cleanTitle);

            $newFilename = $cleanTitle;
            if ($item->release_year) {
                $newFilename .= " ({$item->release_year})";
            }
            $newFilename .= ".{$ext}";
            $newFilePath = str_replace('\\', '/', $dir.'/'.$newFilename);

            // 1. Rename the movie file if needed
            if ($oldPath !== $newFilePath) {
                if (file_exists($newFilePath)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Target file already exists: {$newFilename}",
                    ], 400);
                }

                if (! @rename($oldPath, $newFilePath)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to rename physical file on disk. Check filesystem permissions.',
                    ], 500);
                }

                // Also rename associated subtitle files with matching basename
                $oldBase = pathinfo($oldPath, PATHINFO_FILENAME);
                $newBase = pathinfo($newFilePath, PATHINFO_FILENAME);
                foreach (['srt', 'vtt', 'sub', 'ass'] as $subExt) {
                    $oldSub = $dir.'/'."{$oldBase}.{$subExt}";
                    $newSub = $dir.'/'."{$newBase}.{$subExt}";
                    if (file_exists($oldSub) && ! file_exists($newSub)) {
                        @rename($oldSub, $newSub);
                    }
                }

                $item->update([
                    'file_path' => $newFilePath,
                    'folder_path' => $dir,
                ]);

                // Update subtitle database records
                foreach ($item->subtitles as $sub) {
                    if ($sub->file_path && str_contains($sub->file_path, $oldBase)) {
                        $updatedSubPath = str_replace($oldBase, $newBase, $sub->file_path);
                        $sub->update(['file_path' => $updatedSubPath]);
                    }
                }
            }

            // 2. Check if movie is inside a dedicated movie folder (not monitored root and not containing other media)
            $currentFolder = str_replace('\\', '/', dirname($item->file_path));
            $folderRenamed = false;
            if ($this->isDedicatedMovieFolder($currentFolder, $item->id)) {
                $parentDir = dirname($currentFolder);
                $cleanFolderTarget = $cleanTitle;
                if ($item->release_year) {
                    $cleanFolderTarget .= " ({$item->release_year})";
                }
                $newFolder = str_replace('\\', '/', $parentDir.'/'.$cleanFolderTarget);

                if ($currentFolder !== $newFolder && ! file_exists($newFolder)) {
                    if (@rename($currentFolder, $newFolder)) {
                        $folderRenamed = true;
                        $newFilePathInRenamedFolder = str_replace($currentFolder, $newFolder, $item->file_path);
                        $item->update([
                            'folder_path' => $newFolder,
                            'file_path' => $newFilePathInRenamedFolder,
                        ]);

                        foreach ($item->subtitles as $sub) {
                            if ($sub->file_path && str_contains($sub->file_path, $currentFolder)) {
                                $sub->update([
                                    'file_path' => str_replace($currentFolder, $newFolder, $sub->file_path),
                                ]);
                            }
                        }
                    }
                }
            }

            $item->refresh();
            $item->load(['genres', 'subtitles']);

            return response()->json([
                'success' => true,
                'message' => $folderRenamed
                    ? 'Movie file and folder successfully renamed!'
                    : "Physical file successfully renamed to '{$newFilename}'!",
                'old_path' => $oldPath,
                'new_path' => $item->file_path,
                'media' => $item,
            ]);
        } else {
            $series = Series::with('seasons.episodes.subtitles')->find($id);
            if (! $series) {
                return response()->json(['success' => false, 'message' => 'Series not found.'], 404);
            }

            $seriesFolder = $this->resolveSeriesFolder($series);
            if (! $seriesFolder || ! is_dir($seriesFolder)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Series folder could not be resolved or does not exist on disk.',
                ], 404);
            }

            $seriesFolderNorm = str_replace('\\', '/', $seriesFolder);
            $parentDir = dirname($seriesFolderNorm);

            $cleanSeriesTitle = trim(preg_replace('~[\\/:*?"<>|]~', ' ', $series->title));
            $cleanSeriesTitle = preg_replace('/\s+/', ' ', $cleanSeriesTitle);

            $newFolderName = $cleanSeriesTitle;
            if ($series->release_year) {
                $newFolderName .= " ({$series->release_year})";
            }
            $newFolder = str_replace('\\', '/', $parentDir.'/'.$newFolderName);

            // 1. Rename series folder if name changed
            if ($seriesFolderNorm !== $newFolder) {
                if (file_exists($newFolder)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Target series folder already exists: {$newFolderName}",
                    ], 400);
                }

                if (! @rename($seriesFolderNorm, $newFolder)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to rename series folder. Check permissions.',
                    ], 500);
                }

                $series->update(['folder_path' => $newFolder]);

                // Update all episodes file_path in DB before renaming files
                foreach ($series->seasons as $season) {
                    foreach ($season->episodes as $episode) {
                        if ($episode->file_path) {
                            $updatedEpPath = str_replace($seriesFolderNorm, $newFolder, str_replace('\\', '/', $episode->file_path));
                            $episode->update(['file_path' => $updatedEpPath]);
                            foreach ($episode->subtitles as $sub) {
                                if ($sub->file_path) {
                                    $sub->update([
                                        'file_path' => str_replace($seriesFolderNorm, $newFolder, str_replace('\\', '/', $sub->file_path)),
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            // 2. Rename physical episode files & subtitles across all seasons
            $renamedEpisodesCount = 0;
            foreach ($series->seasons as $season) {
                $sNum = str_pad((string) ($season->season_number ?? 1), 2, '0', STR_PAD_LEFT);

                foreach ($season->episodes as $episode) {
                    if (! $episode->file_path) {
                        continue;
                    }

                    $currentEpPath = str_replace('\\', '/', $episode->file_path);
                    if (! file_exists($currentEpPath)) {
                        continue;
                    }

                    $epDir = dirname($currentEpPath);
                    $ext = pathinfo($currentEpPath, PATHINFO_EXTENSION);
                    $eNum = str_pad((string) ($episode->episode_number ?? 1), 2, '0', STR_PAD_LEFT);

                    $epName = "{$cleanSeriesTitle} - S{$sNum}E{$eNum}";
                    if (! empty($episode->title) && ! preg_match('/^Episode \d+$/i', $episode->title)) {
                        $cleanEpTitle = trim(preg_replace('~[\\/:*?"<>|]~', ' ', $episode->title));
                        $cleanEpTitle = preg_replace('/\s+/', ' ', $cleanEpTitle);
                        if ($cleanEpTitle) {
                            $epName .= " - {$cleanEpTitle}";
                        }
                    }
                    $newEpFilename = "{$epName}.{$ext}";
                    $newEpPath = str_replace('\\', '/', "{$epDir}/{$newEpFilename}");

                    if ($currentEpPath !== $newEpPath) {
                        if (! file_exists($newEpPath)) {
                            if (@rename($currentEpPath, $newEpPath)) {
                                $renamedEpisodesCount++;

                                // Rename associated subtitles
                                $oldEpBase = pathinfo($currentEpPath, PATHINFO_FILENAME);
                                $newEpBase = pathinfo($newEpPath, PATHINFO_FILENAME);
                                foreach (['srt', 'vtt', 'sub', 'ass'] as $subExt) {
                                    $oldSub = "{$epDir}/{$oldEpBase}.{$subExt}";
                                    $newSub = "{$epDir}/{$newEpBase}.{$subExt}";
                                    if (file_exists($oldSub) && ! file_exists($newSub)) {
                                        @rename($oldSub, $newSub);
                                    }
                                }

                                $episode->update(['file_path' => $newEpPath]);

                                foreach ($episode->subtitles as $sub) {
                                    if ($sub->file_path && str_contains($sub->file_path, $oldEpBase)) {
                                        $sub->update([
                                            'file_path' => str_replace($oldEpBase, $newEpBase, $sub->file_path),
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $series->refresh();
            $series->load(['genres', 'seasons.episodes']);

            return response()->json([
                'success' => true,
                'message' => "Series folder and {$renamedEpisodesCount} episode files successfully renamed!",
                'old_path' => $seriesFolderNorm,
                'new_path' => $series->folder_path,
                'renamed_episodes_count' => $renamedEpisodesCount,
                'series' => $series,
            ]);
        }
    }

    protected function isDedicatedMovieFolder(string $dir, int $movieId): bool
    {
        $dirNorm = str_replace('\\', '/', realpath($dir) ?: $dir);

        // Monitored library root directories should never be renamed
        $monitoredJson = AppSetting::where('key', 'scanner_monitored_directories')->first()?->value;
        $monitored = $monitoredJson ? json_decode($monitoredJson, true) : [];
        if (is_array($monitored)) {
            foreach ($monitored as $mDir) {
                $mNorm = str_replace('\\', '/', realpath($mDir) ?: $mDir);
                if (strcasecmp($dirNorm, $mNorm) === 0) {
                    return false;
                }
            }
        }

        // Check if other MediaItems have files inside this directory
        $otherMediaCount = MediaItem::where('id', '!=', $movieId)
            ->where(function ($q) use ($dirNorm) {
                $q->where('folder_path', $dirNorm)
                    ->orWhere('file_path', 'like', $dirNorm.'/%');
            })->count();

        if ($otherMediaCount > 0) {
            return false;
        }

        // Check if directory contains series episodes
        $episodeCount = Episode::where('file_path', 'like', $dirNorm.'/%')->count();
        if ($episodeCount > 0) {
            return false;
        }

        return true;
    }

    protected function resolveSeriesFolder(Series $series): ?string
    {
        if (! empty($series->folder_path) && is_dir($series->folder_path)) {
            return str_replace('\\', '/', $series->folder_path);
        }

        $firstEp = Episode::where('series_id', $series->id)->whereNotNull('file_path')->first();
        if (! $firstEp || ! file_exists($firstEp->file_path)) {
            return null;
        }

        $epPath = str_replace('\\', '/', $firstEp->file_path);
        $epDir = dirname($epPath);
        $epDirBase = strtolower(basename($epDir));

        if (preg_match('/^(season\s*\d+|s\d+|specials?)$/i', $epDirBase)) {
            $candidate = dirname($epDir);
        } else {
            $candidate = $epDir;
        }

        if (is_dir($candidate)) {
            $series->folder_path = $candidate;
            $series->save();

            return $candidate;
        }

        return null;
    }

    public function deleteItem(Request $request, string $type, int $id): JsonResponse
    {
        if ($type === 'movie') {
            $item = MediaItem::find($id);
            if (! $item) {
                return response()->json(['success' => false, 'message' => 'Movie not found.'], 404);
            }

            $title = $item->title;
            // Delete associated subtitles and watch history
            $item->subtitles()->delete();
            $item->watchHistories()->delete();
            $item->delete();

            return response()->json([
                'success' => true,
                'message' => "Movie '{$title}' removed from library index.",
            ]);
        } else {
            $series = Series::with(['seasons.episodes.subtitles'])->find($id);
            if (! $series) {
                return response()->json(['success' => false, 'message' => 'Series not found.'], 404);
            }

            $title = $series->title;
            foreach ($series->seasons as $season) {
                foreach ($season->episodes as $episode) {
                    $episode->subtitles()->delete();
                    $episode->watchHistories()->delete();
                    $episode->delete();
                }
                $season->delete();
            }
            $series->delete();

            return response()->json([
                'success' => true,
                'message' => "Series '{$title}' and its episodes removed from library index.",
            ]);
        }
    }

    public function batchEnrich(Request $request): JsonResponse
    {
        $limit = max(10, min(100, (int) $request->input('limit', 50)));
        $result = $this->scannerService->enrichMissingMetadata($limit);

        return response()->json([
            'success' => true,
            'message' => "Successfully enriched {$result['enriched_count']} items with bilingual metadata, collections, and artwork.",
            'result' => $result,
        ]);
    }
}
