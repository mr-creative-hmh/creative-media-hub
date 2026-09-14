<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Services\Metadata\LibraryMasterIndexService;
use App\Services\Metadata\TmdbProvider;
use App\Services\Scout\LibraryGapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CollectionManagementController extends Controller
{
    /**
     * Get a comprehensive overview of all collections and their assigned movies.
     */
    public function overview(Request $request, LibraryMasterIndexService $masterService, LibraryGapService $gapService): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $query = MediaItem::query()
            ->whereNotNull('collection_name')
            ->where('collection_name', '!=', '')
            ->with(['genres', 'subtitles']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('collection_name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%");
            });
        }

        $allMovies = $query->orderBy('release_year')->get();
        $grouped = $allMovies->groupBy('collection_name');

        $scoutGaps = $gapService->getGroupedCollectionGaps(false);
        $gapsByColId = collect($scoutGaps)->keyBy('collection_id');
        $gapsBySlug = collect($scoutGaps)->keyBy(fn ($g) => Str::slug($g['collection_name'] ?? ''));

        $collections = $grouped->map(function ($movies, $name) use ($gapsByColId, $gapsBySlug) {
            $slug = Str::slug($name);
            $firstMovie = $movies->first();
            $colId = $firstMovie->collection_id;
            $source = $firstMovie->collection_id_source ?: ($colId ? 'tmdb' : 'custom');

            // Find poster
            $poster = $movies->pluck('collection_poster')->filter()->first()
                ?? $movies->pluck('poster_path')->filter()->first();

            // Gap status
            $gap = ($colId ? $gapsByColId->get((int) $colId) : null) ?? $gapsBySlug->get($slug);
            $totalParts = max($movies->count(), (int) ($gap['total_count'] ?? $movies->count()));
            $isComplete = (bool) ($gap['is_complete'] ?? ($movies->count() >= $totalParts));
            $completionPercentage = $totalParts > 0 ? round(($movies->count() / $totalParts) * 100) : 100;

            $years = $movies->pluck('release_year')->filter()->sort()->values();
            $yearSpan = $years->isNotEmpty()
                ? ($years->first() === $years->last() ? (string) $years->first() : "{$years->first()} - {$years->last()}")
                : null;

            return [
                'name' => $name,
                'slug' => $slug,
                'collection_id' => $colId,
                'source' => $source,
                'poster_path' => $poster,
                'backdrop_path' => $movies->pluck('backdrop_path')->filter()->first(),
                'year_span' => $yearSpan,
                'movies_count' => $movies->count(),
                'total_parts' => $totalParts,
                'is_complete' => $isComplete,
                'completion_percentage' => $completionPercentage,
                'movies' => $movies->map(fn ($m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'title_ar' => $m->title_ar,
                    'release_year' => $m->release_year,
                    'rating' => $m->rating,
                    'poster_path' => $m->poster_path,
                    'resolution' => $m->resolution,
                    'collection_id' => $m->collection_id,
                    'collection_id_source' => $m->collection_id_source,
                    'file_path' => $m->file_path,
                    'folder_path' => $m->folder_path,
                ])->values()->all(),
            ];
        })->values()->sortBy('name')->values();

        $stats = [
            'total_collections' => $collections->count(),
            'total_franchise_movies' => $allMovies->count(),
            'total_standalone_movies' => MediaItem::whereNull('collection_name')->orWhere('collection_name', '')->count(),
            'complete_collections' => $collections->where('is_complete', true)->count(),
            'incomplete_collections' => $collections->where('is_complete', false)->count(),
        ];

        return response()->json([
            'success' => true,
            'collections' => $collections,
            'stats' => $stats,
        ]);
    }

    /**
     * Synchronize all library movies with official TMDb collections and heal any metadata discrepancies.
     */
    public function syncTmdb(
        Request $request,
        TmdbProvider $tmdb,
        LibraryMasterIndexService $masterService,
        LibraryGapService $gapService
    ): JsonResponse {
        $force = $request->boolean('force', false);
        $movies = MediaItem::whereNotNull('tmdb_id')->get();

        $scannedCount = 0;
        $updatedCount = 0;
        $healedCount = 0;
        $healedDetails = [];

        foreach ($movies as $movie) {
            $scannedCount++;

            // Preserve custom user collections unless force requested
            if (! $force && $movie->collection_id_source === 'custom') {
                continue;
            }

            try {
                $details = $tmdb->getMovieDetails((int) $movie->tmdb_id);
                if (! $details) {
                    continue;
                }

                $tmdbColId = ! empty($details['collection_id']) ? (int) $details['collection_id'] : null;
                $tmdbColName = ! empty($details['collection_name']) ? trim((string) $details['collection_name']) : null;
                $tmdbColPoster = ! empty($details['collection_poster']) ? (string) $details['collection_poster'] : null;

                // Case 1: TMDb specifies a collection for this movie
                if ($tmdbColId && $tmdbColName) {
                    $wasDifferent = ($movie->collection_id !== $tmdbColId)
                        || ($movie->collection_name !== $tmdbColName);

                    if ($wasDifferent) {
                        $oldDesc = $movie->collection_name
                            ? "{$movie->collection_name} (ID: {$movie->collection_id})"
                            : 'None';
                        $newDesc = "{$tmdbColName} (ID: {$tmdbColId})";

                        if ($movie->collection_id && $movie->collection_id !== $tmdbColId) {
                            $healedCount++;
                            $healedDetails[] = "Healed '{$movie->title}' ({$movie->release_year}): {$oldDesc} -> {$newDesc}";
                        } else {
                            $updatedCount++;
                        }

                        $movie->collection_id = $tmdbColId;
                        $movie->collection_name = $tmdbColName;
                        $movie->collection_id_source = 'tmdb';
                        if ($tmdbColPoster) {
                            $movie->collection_poster = $tmdbColPoster;
                        }
                        $movie->save();
                    }
                }
                // Case 2: TMDb specifies NO collection, but movie currently has an ID
                elseif (! $tmdbColId && $movie->collection_id && $movie->collection_id_source !== 'custom') {
                    // Check if current collection_id matches any part of that collection
                    $colParts = $tmdb->searchCollection($movie->collection_name ?? '');
                    $belongs = false;
                    if (! empty($colParts)) {
                        foreach ($colParts as $cp) {
                            if ((int) ($cp['collection_id'] ?? 0) === (int) $movie->collection_id) {
                                $belongs = true;
                                break;
                            }
                        }
                    }

                    if (! $belongs && $movie->collection_id === 722971 && stripos($movie->title, 'Knives Out') === false && stripos($movie->title, 'Glass Onion') === false) {
                        // Special auto-heal for Glass or other misassigned items to Knives Out
                        if (stripos($movie->title, 'Glass') !== false) {
                            $movie->collection_id = 1769700;
                            $movie->collection_name = 'Unbreakable Collection';
                            $movie->collection_id_source = 'tmdb';
                            $movie->save();
                            $healedCount++;
                            $healedDetails[] = "Healed '{$movie->title}' ({$movie->release_year}) from Knives Out -> Unbreakable Collection (1769700)";
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Continue scanning remaining movies
            }
        }

        // Rebuild master index and flush caches
        try {
            $masterService->buildAll(false, false, false);
        } catch (\Throwable $e) {
        }

        CollectionController::clearCache();
        $gapService->clearCache();
        $gapService->getGroupedCollectionGaps(true);

        $totalCollections = MediaItem::whereNotNull('collection_name')->where('collection_name', '!=', '')->distinct('collection_name')->count('collection_name');

        return response()->json([
            'success' => true,
            'message' => "TMDb collection synchronization complete! Processed {$scannedCount} movies. Updated {$updatedCount} items, healed {$healedCount} discrepancies across {$totalCollections} franchises.",
            'stats' => [
                'total_scanned' => $scannedCount,
                'updated_count' => $updatedCount,
                'healed_count' => $healedCount,
                'total_collections' => $totalCollections,
            ],
            'healed_details' => $healedDetails,
        ]);
    }

    /**
     * 1-Click Detach a movie from its collection.
     */
    public function detachMovie(Request $request, LibraryGapService $gapService): JsonResponse
    {
        $validated = $request->validate([
            'media_item_id' => 'required|integer|exists:media_items,id',
            'reorganize_folder' => 'nullable|boolean',
        ]);

        $movie = MediaItem::findOrFail($validated['media_item_id']);
        $oldColName = $movie->collection_name;

        $movie->collection_name = null;
        $movie->collection_id = null;
        $movie->collection_id_source = null;
        $movie->save();

        CollectionController::clearCache();
        $gapService->clearCache();

        return response()->json([
            'success' => true,
            'message' => "'{$movie->title}' was successfully detached from '{$oldColName}'.",
            'movie' => $movie->fresh(),
        ]);
    }

    /**
     * Assign or reassign a movie to a collection.
     */
    public function assignMovie(Request $request, LibraryGapService $gapService): JsonResponse
    {
        $validated = $request->validate([
            'media_item_id' => 'required|integer|exists:media_items,id',
            'collection_name' => 'required|string|max:255',
            'collection_id' => 'nullable|numeric',
            'collection_id_source' => 'nullable|string|in:tmdb,imdb,anilist,tvdb,custom',
            'reorganize_folder' => 'nullable|boolean',
        ]);

        $movie = MediaItem::findOrFail($validated['media_item_id']);
        $colName = trim($validated['collection_name']);
        $colId = $validated['collection_id'] ?? null;
        $source = $validated['collection_id_source'] ?? null;

        // If collection_id wasn't provided, inherit from existing movie in this collection
        if (! $colId) {
            $sibling = MediaItem::where('collection_name', $colName)
                ->whereNotNull('collection_id')
                ->first();
            if ($sibling) {
                $colId = $sibling->collection_id;
                $source = $source ?: ($sibling->collection_id_source ?: 'tmdb');
            }
        }

        $movie->collection_name = $colName;
        $movie->collection_id = $colId ?: null;
        $movie->collection_id_source = $source ?: ($colId ? 'tmdb' : 'custom');

        // Copy poster from sibling if not set
        if (empty($movie->collection_poster)) {
            $siblingPoster = MediaItem::where('collection_name', $colName)
                ->whereNotNull('collection_poster')
                ->value('collection_poster');
            if ($siblingPoster) {
                $movie->collection_poster = $siblingPoster;
            }
        }

        $movie->save();

        CollectionController::clearCache();
        $gapService->clearCache();

        return response()->json([
            'success' => true,
            'message' => "'{$movie->title}' successfully assigned to '{$colName}'.",
            'movie' => $movie->fresh(),
        ]);
    }

    /**
     * Batch update collection metadata (name, TMDb ID, poster) across all assigned movies.
     */
    public function updateCollection(Request $request, LibraryGapService $gapService): JsonResponse
    {
        $validated = $request->validate([
            'current_name' => 'required|string',
            'new_name' => 'required|string|max:255',
            'collection_id' => 'nullable|numeric',
            'collection_id_source' => 'nullable|string',
            'collection_poster' => 'nullable|string',
        ]);

        $currentName = trim($validated['current_name']);
        $newName = trim($validated['new_name']);
        $colId = $validated['collection_id'] ?? null;
        $poster = $validated['collection_poster'] ?? null;
        $source = $validated['collection_id_source'] ?? ($colId ? 'tmdb' : 'custom');

        $movies = MediaItem::where('collection_name', $currentName)->get();
        if ($movies->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "No movies found in collection '{$currentName}'.",
            ], 404);
        }

        foreach ($movies as $movie) {
            $movie->collection_name = $newName;
            if ($colId !== null) {
                $movie->collection_id = $colId ?: null;
                $movie->collection_id_source = $source;
            }
            if ($poster) {
                $movie->collection_poster = $poster;
            }
            $movie->save();
        }

        CollectionController::clearCache();
        $gapService->clearCache();

        return response()->json([
            'success' => true,
            'message' => "Updated collection '{$newName}' across {$movies->count()} movies.",
            'updated_count' => $movies->count(),
        ]);
    }

    /**
     * Search movies in library to easily add them to a collection.
     */
    public function searchMovies(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        if (strlen($query) < 2) {
            return response()->json(['movies' => []]);
        }

        $movies = MediaItem::query()
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('title_ar', 'like', "%{$query}%")
                    ->orWhere('original_title', 'like', "%{$query}%");
            })
            ->select(['id', 'title', 'title_ar', 'release_year', 'poster_path', 'collection_name', 'collection_id'])
            ->limit(20)
            ->get();

        return response()->json([
            'movies' => $movies,
        ]);
    }
}
