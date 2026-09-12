<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Services\Metadata\LibraryMasterIndexService;
use App\Services\Scout\LibraryGapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function index(Request $request, LibraryMasterIndexService $masterService, LibraryGapService $gapService): Response
    {
        $search = $request->input('search');

        if ($request->boolean('refresh')) {
            Cache::forget('collections.index.data.v5');
            $gapService->getGroupedCollectionGaps(true);
        }

        // Query all movies that have a collection_name
        $query = MediaItem::query()
            ->whereNotNull('collection_name')
            ->where('collection_name', '!=', '')
            ->with(['genres', 'subtitles']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('collection_name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%");
            });
        }

        if (empty($search) && ! app()->environment('testing')) {
            $raw = Cache::remember('collections.index.data.v5', 3600, function () use ($masterService, $gapService) {
                $allMovies = MediaItem::query()
                    ->whereNotNull('collection_name')
                    ->where('collection_name', '!=', '')
                    ->with(['genres', 'subtitles'])
                    ->orderBy('release_year')
                    ->get();

                return $this->compileCollections($allMovies, $masterService, $gapService)->toArray();
            });
            $collections = collect(array_values($raw));
            $totalFranchiseMovies = $collections->sum('movies_count');
        } else {
            $allMoviesInCollections = $query->orderBy('release_year')->get();
            $collections = $this->compileCollections($allMoviesInCollections, $masterService, $gapService);
            $totalFranchiseMovies = $collections->sum('movies_count');
        }

        return Inertia::render('Collections/Index', [
            'collections' => $collections,
            'filters' => [
                'search' => $search,
            ],
            'total_collections' => $collections->count(),
            'total_franchise_movies' => $totalFranchiseMovies,
        ]);
    }

    public function show(string $slug, LibraryMasterIndexService $masterService, LibraryGapService $gapService): Response
    {
        // Find collection by slug
        $all = MediaItem::whereNotNull('collection_name')->where('collection_name', '!=', '')->get();
        $matchedName = null;
        foreach ($all as $m) {
            if (Str::slug($m->collection_name) === $slug || strtolower(trim($m->collection_name)) === strtolower(str_replace('-', ' ', $slug))) {
                $matchedName = $m->collection_name;
                break;
            }
        }

        if (! $matchedName) {
            abort(404, 'Movie collection not found');
        }

        $movies = MediaItem::where('collection_name', $matchedName)
            ->with(['genres', 'subtitles', 'people', 'directors', 'actors', 'watchHistories'])
            ->orderBy('release_year')
            ->get()
            ->unique(fn ($m) => $m->tmdb_id ?: strtolower(trim($m->title)))
            ->values();

        if ($movies->count() < 2) {
            abort(404, 'Movie collection not found');
        }

        $movies = $movies->map(function ($m) {
            $slug = $m->slug ?: "movie-{$m->id}";

            return [
                'id' => $m->id,
                'type' => 'movie',
                'title' => $m->title,
                'title_ar' => $m->title_ar,
                'original_title' => $m->original_title,
                'slug' => $slug,
                'slug_url' => route('movies.show.slug', $slug),
                'release_year' => $m->release_year,
                'rating' => $m->rating,
                'poster_path' => $m->poster_path,
                'backdrop_path' => $m->backdrop_path,
                'overview' => $m->overview,
                'overview_ar' => $m->overview_ar,
                'runtime_minutes' => $m->runtime_minutes,
                'resolution' => $m->resolution,
                'video_codec' => $m->video_codec,
                'audio_codec' => $m->audio_codec,
                'file_path' => $m->file_path,
                'collection_name' => $m->collection_name,
                'is_favorite' => (bool) $m->is_favorite,
                'trailer_url' => $m->trailer_url,
                'genres' => $m->genres,
                'people' => $m->people,
                'directors' => $m->directors,
                'actors' => $m->actors,
                'subtitles' => $m->subtitles,
                'subtitles_count' => $m->subtitles->count(),
                'watch_history' => $m->watchHistories->first(),
                'stream_url' => route('stream.movie', $m->id),
                'remux_url' => route('stream.remux.movie', $m->id),
            ];
        });

        $years = $movies->pluck('release_year')->filter()->sort()->values();
        $yearSpan = $years->isNotEmpty()
            ? ($years->first() === $years->last() ? (string) $years->first() : "{$years->first()} - {$years->last()}")
            : null;

        $poster = $movies->first()['collection_poster'] ?? $movies->pluck('poster_path')->filter()->first();
        $backdrop = $movies->pluck('backdrop_path')->filter()->first();

        $colId = (int) ($movies->first()['collection_id'] ?? 0);
        $scoutGaps = $gapService->getGroupedCollectionGaps(false);
        $gapsByColId = collect($scoutGaps)->keyBy('collection_id');
        $gapsBySlug = collect($scoutGaps)->keyBy(fn ($g) => Str::slug($g['collection_name'] ?? ''));

        $gap = $gapsByColId->get($colId) ?? $gapsBySlug->get(Str::slug($matchedName));

        if ($gap && ! empty($gap['missing_movies'])) {
            $totalParts = max((int) ($gap['total_count'] ?? 0), $movies->count());
            $ownedCount = $movies->count();
            $isComplete = false;
            $completionPct = $totalParts > 0 ? round(($ownedCount / $totalParts) * 100) : 100;
            $missingParts = array_map(function ($m) {
                return [
                    'tmdb_id' => $m['tmdb_id'] ?? null,
                    'title' => $m['movie_title'] ?? ($m['title'] ?? ''),
                    'title_ar' => $m['title_ar'] ?? null,
                    'release_year' => $m['release_year'] ?? null,
                    'poster_path' => $m['poster_path'] ?? null,
                    'overview' => $m['overview'] ?? null,
                ];
            }, $gap['missing_movies']);
        } else {
            // Check Master Index for franchise parts
            $ext = $masterService->getExtendedCollection($colId);
            if (! $ext) {
                $extSlug = Str::slug($matchedName);
                $extendedMap = collect($masterService->getAllExtendedCollections())->keyBy(fn ($c) => Str::slug($c['name'] ?? ''));
                $ext = $extendedMap->get($extSlug);
            }

            $totalParts = $ext ? count($ext['parts'] ?? []) : $movies->count();
            $totalParts = max($totalParts, $movies->count());
            $ownedCount = $movies->count();
            $missingParts = [];

            if ($ext && ! empty($ext['parts'])) {
                $ownedTmdb = $movies->pluck('tmdb_id')->filter()->map(fn ($id) => (int) $id)->toArray();
                $ownedTitles = $movies->pluck('title')->map(fn ($t) => strtolower(trim($t)))->toArray();

                foreach ($ext['parts'] as $part) {
                    $pTmdbId = (int) ($part['tmdb_id'] ?? 0);
                    $pTitle = strtolower(trim($part['title'] ?? ''));

                    $isOwned = (! empty($part['is_owned']))
                        || ($pTmdbId > 0 && in_array($pTmdbId, $ownedTmdb, true))
                        || in_array($pTitle, $ownedTitles, true);

                    if (! $isOwned) {
                        $missingParts[] = [
                            'tmdb_id' => $part['tmdb_id'] ?? null,
                            'title' => $part['title'] ?? '',
                            'title_ar' => $part['title_ar'] ?? null,
                            'release_year' => $part['release_year'] ?? null,
                            'poster_path' => $part['poster_path'] ?? null,
                            'overview' => $part['overview'] ?? null,
                        ];
                    }
                }
            }

            $isComplete = empty($missingParts) && ($ownedCount >= $totalParts);
            $totalParts = max($totalParts, $ownedCount + count($missingParts));
            $completionPct = $totalParts > 0 ? round(($ownedCount / $totalParts) * 100) : 100;
        }

        return Inertia::render('Collections/Show', [
            'collection' => [
                'name' => $matchedName,
                'slug' => Str::slug($matchedName),
                'movies_count' => $ownedCount,
                'total_parts' => $totalParts,
                'is_complete' => $isComplete,
                'completion_percentage' => $completionPct,
                'missing_parts' => $missingParts,
                'year_span' => $yearSpan,
                'poster_path' => $poster,
                'backdrop_path' => $backdrop,
                'avg_rating' => round($movies->avg('rating'), 1),
                'movies' => $movies,
            ],
        ]);
    }

    protected function compileCollections($allMoviesInCollections, $masterService, ?LibraryGapService $gapService = null)
    {
        $gapService = $gapService ?? app(LibraryGapService::class);
        $scoutGaps = $gapService->getGroupedCollectionGaps(false);
        $gapsByColId = collect($scoutGaps)->keyBy('collection_id');
        $gapsBySlug = collect($scoutGaps)->keyBy(fn ($g) => Str::slug($g['collection_name'] ?? ''));

        $grouped = $allMoviesInCollections->groupBy('collection_name');
        $extendedMap = collect($masterService->getAllExtendedCollections())->keyBy(function ($c) {
            return Str::slug($c['name'] ?? '');
        });
        $extendedById = collect($masterService->getAllExtendedCollections())->keyBy('collection_id');

        return $grouped
            ->filter(function ($movies) {
                return $movies->count() >= 2;
            })
            ->map(function ($movies, $name) use ($extendedMap, $extendedById, $gapsByColId, $gapsBySlug) {
                $years = $movies->pluck('release_year')->filter()->sort()->values();
                $yearSpan = $years->isNotEmpty()
                    ? ($years->first() === $years->last() ? (string) $years->first() : "{$years->first()} - {$years->last()}")
                    : null;

                $poster = $movies->pluck('collection_poster')->filter()->first()
                    ?? $movies->pluck('poster_path')->filter()->first();
                $backdrop = $movies->pluck('backdrop_path')->filter()->first();
                $avgRating = round($movies->avg('rating'), 1);
                $slug = Str::slug($name);
                $colId = (int) ($movies->first()->collection_id ?? 0);

                // Priority 1: Check Media Scout Gaps (real-time TMDB gap detection)
                $gap = $gapsByColId->get($colId) ?? $gapsBySlug->get($slug);

                if ($gap && ! empty($gap['missing_movies'])) {
                    $totalParts = max((int) ($gap['total_count'] ?? 0), $movies->count());
                    $ownedCount = $movies->count();
                    $isComplete = false;
                    $completionPct = $totalParts > 0 ? round(($ownedCount / $totalParts) * 100) : 100;
                    $missingParts = array_map(function ($m) {
                        return [
                            'tmdb_id' => $m['tmdb_id'] ?? null,
                            'title' => $m['movie_title'] ?? ($m['title'] ?? ''),
                            'title_ar' => $m['title_ar'] ?? null,
                            'release_year' => $m['release_year'] ?? null,
                            'poster_path' => $m['poster_path'] ?? null,
                            'overview' => $m['overview'] ?? null,
                        ];
                    }, $gap['missing_movies']);
                } else {
                    // Priority 2: Check Master Index collections_extended.json
                    $ext = $extendedById->get($colId) ?? $extendedMap->get($slug);
                    $hasExtended = $ext && (! empty($ext['parts']) || ! empty($ext['total_parts']));
                    $totalParts = $hasExtended ? max((int) ($ext['total_parts'] ?? 0), count($ext['parts'] ?? []), $movies->count()) : $movies->count();
                    $ownedCount = $movies->count();

                    $missingParts = [];
                    if ($ext && ! empty($ext['parts'])) {
                        $ownedTmdb = $movies->pluck('tmdb_id')->filter()->map(fn ($id) => (int) $id)->toArray();
                        $ownedTitles = $movies->pluck('title')->map(fn ($t) => strtolower(trim($t)))->toArray();

                        foreach ($ext['parts'] as $part) {
                            $pTmdbId = (int) ($part['tmdb_id'] ?? 0);
                            $pTitle = strtolower(trim($part['title'] ?? ''));

                            $isOwned = (! empty($part['is_owned']))
                                || ($pTmdbId > 0 && in_array($pTmdbId, $ownedTmdb, true))
                                || in_array($pTitle, $ownedTitles, true);

                            if (! $isOwned) {
                                $missingParts[] = [
                                    'tmdb_id' => $part['tmdb_id'] ?? null,
                                    'title' => $part['title'] ?? '',
                                    'title_ar' => $part['title_ar'] ?? null,
                                    'release_year' => $part['release_year'] ?? null,
                                    'poster_path' => $part['poster_path'] ?? null,
                                    'overview' => $part['overview'] ?? null,
                                ];
                            }
                        }
                    }

                    $isComplete = empty($missingParts) && ($ownedCount >= $totalParts);
                    $totalParts = max($totalParts, $ownedCount + count($missingParts));
                    $completionPct = $totalParts > 0 ? round(($ownedCount / $totalParts) * 100) : 100;
                }

                return [
                    'name' => $name,
                    'slug' => $slug,
                    'movies_count' => $ownedCount,
                    'total_parts' => $totalParts,
                    'is_complete' => $isComplete,
                    'completion_percentage' => $completionPct,
                    'missing_parts' => $missingParts,
                    'year_span' => $yearSpan,
                    'avg_rating' => $avgRating,
                    'poster_path' => $poster,
                    'backdrop_path' => $backdrop,
                    'movies' => array_values($movies->unique(fn ($m) => (is_array($m) ? ($m['tmdb_id'] ?? null) : $m->tmdb_id) ?: strtolower(trim(is_array($m) ? ($m['title'] ?? '') : $m->title)))->map(fn ($m) => [
                        'id' => is_array($m) ? $m['id'] : $m->id,
                        'title' => is_array($m) ? $m['title'] : $m->title,
                        'title_ar' => is_array($m) ? ($m['title_ar'] ?? null) : $m->title_ar,
                        'slug' => is_array($m) ? ($m['slug'] ?? "movie-{$m['id']}") : ($m->slug ?: "movie-{$m->id}"),
                        'release_year' => is_array($m) ? ($m['release_year'] ?? null) : $m->release_year,
                        'rating' => is_array($m) ? ($m['rating'] ?? null) : $m->rating,
                        'poster_path' => is_array($m) ? ($m['poster_path'] ?? null) : $m->poster_path,
                        'backdrop_path' => is_array($m) ? ($m['backdrop_path'] ?? null) : $m->backdrop_path,
                        'resolution' => is_array($m) ? ($m['resolution'] ?? null) : $m->resolution,
                        'runtime_minutes' => is_array($m) ? ($m['runtime_minutes'] ?? null) : $m->runtime_minutes,
                    ])->values()->all()),
                ];
            })->values()->sortByDesc('movies_count')->values();
    }
}
