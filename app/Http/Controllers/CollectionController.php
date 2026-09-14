<?php

namespace App\Http\Controllers;

use App\Models\Genre;
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
    public static function clearCache(): void
    {
        Cache::forget('collections.index.data.v5');
        Cache::forget('collections.index.data.v6');
        Cache::forget('collections.index.data.v7');
        Cache::forget('collections.index.data.v8');
        Cache::forget('collections.index.data.v9');
        Cache::forget('collections.index.data.v10');
    }

    public function index(Request $request, LibraryMasterIndexService $masterService, LibraryGapService $gapService): Response
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'name');
        $direction = strtolower($request->input('direction', ''));
        $status = $request->input('status', 'all');
        $genre = $request->input('genre');

        // Sensible default direction based on sort key
        if (empty($direction)) {
            $direction = in_array($sort, ['rating', 'movies_count', 'count', 'year', 'release_year', 'completion']) ? 'desc' : 'asc';
        }

        if ($request->boolean('refresh')) {
            self::clearCache();
            $gapService->clearCache();
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
            $raw = Cache::remember('collections.index.data.v10', 3600, function () use ($masterService, $gapService) {
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

        // Extract all distinct genres across all compiled collections
        $allGenres = $collections->flatMap(fn ($col) => $col['genres'] ?? [])
            ->unique('id')
            ->values()
            ->sortBy('name_en')
            ->values()
            ->all();

        if (empty($allGenres)) {
            $allGenres = Genre::orderBy('name_en')->get()->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name_en,
                'name_en' => $g->name_en,
                'name_ar' => $g->name_ar,
                'slug' => $g->slug,
            ])->values()->all();
        }

        // Optional server-side genre filtering
        if (! empty($genre)) {
            $genreLower = strtolower($genre);
            $collections = $collections->filter(function ($col) use ($genre, $genreLower) {
                $colGenres = collect($col['genres'] ?? []);

                return $colGenres->contains(function ($g) use ($genre, $genreLower) {
                    return (string) ($g['id'] ?? '') === (string) $genre ||
                        strtolower($g['slug'] ?? '') === $genreLower ||
                        strtolower($g['name_en'] ?? '') === $genreLower ||
                        strtolower($g['name'] ?? '') === $genreLower;
                });
            })->values();
        }

        // Optional server-side status filtering
        if ($status === 'complete') {
            $collections = $collections->filter(fn ($col) => ! empty($col['is_complete']))->values();
        } elseif ($status === 'in_progress') {
            $collections = $collections->filter(fn ($col) => empty($col['is_complete']))->values();
        } elseif ($status === 'top_rated') {
            $collections = $collections->filter(fn ($col) => ($col['avg_rating'] ?? 0) >= 8.0)->values();
        } elseif ($status === 'large') {
            $collections = $collections->filter(fn ($col) => ($col['movies_count'] ?? 0) >= 4)->values();
        }

        // Server-side sorting
        $collections = match ($sort) {
            'rating' => $direction === 'asc'
                ? $collections->sortBy('avg_rating')->values()
                : $collections->sortByDesc('avg_rating')->values(),
            'movies_count', 'count' => $direction === 'asc'
                ? $collections->sortBy('movies_count')->values()
                : $collections->sortByDesc('movies_count')->values(),
            'year', 'release_year' => $direction === 'asc'
                ? $collections->sortBy('latest_year')->values()
                : $collections->sortByDesc('latest_year')->values(),
            'completion' => $direction === 'asc'
                ? $collections->sortBy('completion_percentage')->values()
                : $collections->sortByDesc('completion_percentage')->values(),
            default => $direction === 'desc'
                ? $collections->sortByDesc('name', SORT_NATURAL | SORT_FLAG_CASE)->values()
                : $collections->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
        };

        return Inertia::render('Collections/Index', [
            'collections' => $collections,
            'all_genres' => $allGenres,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'status' => $status,
                'genre' => $genre,
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
            ->unique(fn ($m) => $m->tmdb_id ? ($m->tmdb_id.'_'.strtolower(trim($m->title))) : strtolower(trim($m->title)))
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

        $scoutGaps = $gapService->getGroupedCollectionGaps(false);
        $gapsByColId = collect($scoutGaps)->keyBy('collection_id');
        $gapsBySlug = collect($scoutGaps)->keyBy(fn ($g) => Str::slug($g['collection_name'] ?? ''));

        $status = $this->resolveCollectionStatus($matchedName, $movies, $masterService, $gapsByColId, $gapsBySlug);

        return Inertia::render('Collections/Show', [
            'collection' => [
                'name' => $matchedName,
                'slug' => Str::slug($matchedName),
                'movies_count' => $status['owned_count'],
                'total_parts' => $status['total_parts'],
                'is_complete' => $status['is_complete'],
                'completion_percentage' => $status['completion_percentage'],
                'missing_parts' => $status['missing_parts'],
                'year_span' => $yearSpan,
                'poster_path' => $poster,
                'backdrop_path' => $backdrop,
                'avg_rating' => round($movies->avg('rating'), 1),
                'movies' => $movies,
            ],
        ]);
    }

    /**
     * Resolve unified completion status, total parts, and missing parts for a collection.
     */
    protected function resolveCollectionStatus(string $name, $movies, LibraryMasterIndexService $masterService, $gapsByColId, $gapsBySlug): array
    {
        $today = date('Y-m-d');
        $currentYear = (int) date('Y');
        $colId = (int) ($movies->first()['collection_id'] ?? ($movies->first()->collection_id ?? 0));
        $slug = Str::slug($name);

        // Priority 1: Media Scout Gap Service (real-time TMDB gap detection with date filtering)
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

            return [
                'total_parts' => $totalParts,
                'owned_count' => $ownedCount,
                'is_complete' => $isComplete,
                'completion_percentage' => $completionPct,
                'missing_parts' => $missingParts,
            ];
        }

        // Priority 2: Master Index collections_extended.json
        $ext = $masterService->getExtendedCollection($colId);
        if (! $ext) {
            $extendedMap = collect($masterService->getAllExtendedCollections())->keyBy(fn ($c) => Str::slug($c['name'] ?? ''));
            $ext = $extendedMap->get($slug);
        }

        // Ignore cartoon short anthologies (e.g. Tom and Jerry 1940s shorts)
        $isShortsAnthology = ($colId == 1758656) || str_contains(strtolower($name), 'tom and jerry');

        $totalParts = $movies->count();
        $ownedCount = $movies->count();
        $missingParts = [];

        if ($ext && ! empty($ext['parts']) && ! $isShortsAnthology) {
            $ownedTmdb = $movies->pluck('tmdb_id')->filter()->map(fn ($id) => (int) $id)->toArray();
            $ownedTitles = $movies->pluck('title')->map(fn ($t) => strtolower(trim($t)))->toArray();

            $validPartsCount = 0;
            foreach ($ext['parts'] as $part) {
                $relDate = $part['release_date'] ?? null;
                $relYear = (int) ($part['release_year'] ?? 0);

                // Filter out future unreleased movies
                if ((! empty($relDate) && $relDate > $today) || ($relYear > $currentYear)) {
                    continue;
                }

                $validPartsCount++;
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
            $totalParts = max($validPartsCount, $ownedCount);
        }

        $isComplete = empty($missingParts) && ($ownedCount >= $totalParts);
        $totalParts = max($totalParts, $ownedCount + count($missingParts));
        $completionPct = $totalParts > 0 ? round(($ownedCount / $totalParts) * 100) : 100;

        return [
            'total_parts' => $totalParts,
            'owned_count' => $ownedCount,
            'is_complete' => $isComplete,
            'completion_percentage' => $completionPct,
            'missing_parts' => $missingParts,
        ];
    }

    protected function compileCollections($allMoviesInCollections, $masterService, ?LibraryGapService $gapService = null)
    {
        $gapService = $gapService ?? app(LibraryGapService::class);
        $scoutGaps = $gapService->getGroupedCollectionGaps(false);
        $gapsByColId = collect($scoutGaps)->keyBy('collection_id');
        $gapsBySlug = collect($scoutGaps)->keyBy(fn ($g) => Str::slug($g['collection_name'] ?? ''));

        $grouped = $allMoviesInCollections->groupBy('collection_name');

        return $grouped
            ->filter(function ($movies) {
                return $movies->count() >= 2;
            })
            ->map(function ($movies, $name) use ($masterService, $gapsByColId, $gapsBySlug) {
                $years = $movies->pluck('release_year')->filter()->sort()->values();
                $yearSpan = $years->isNotEmpty()
                    ? ($years->first() === $years->last() ? (string) $years->first() : "{$years->first()} - {$years->last()}")
                    : null;

                $poster = $movies->pluck('collection_poster')->filter()->first()
                    ?? $movies->pluck('poster_path')->filter()->first();
                $backdrop = $movies->pluck('backdrop_path')->filter()->first();
                $avgRating = round($movies->avg('rating'), 1);
                $slug = Str::slug($name);

                $status = $this->resolveCollectionStatus($name, $movies, $masterService, $gapsByColId, $gapsBySlug);

                $genres = $movies->flatMap(function ($m) {
                    return is_array($m) ? ($m['genres'] ?? []) : $m->genres;
                })->filter()->unique('id')->values()->map(function ($g) {
                    $nameEn = is_array($g)
                        ? ($g['name_en'] ?? $g['name'] ?? '')
                        : ($g->name_en ?? $g->name ?? '');
                    $nameAr = is_array($g)
                        ? ($g['name_ar'] ?? null)
                        : $g->name_ar;
                    $slug = is_array($g)
                        ? ($g['slug'] ?? '')
                        : ($g->slug ?? '');
                    $id = is_array($g)
                        ? ($g['id'] ?? 0)
                        : $g->id;

                    return [
                        'id' => (int) $id,
                        'name' => $nameEn,
                        'name_en' => $nameEn,
                        'name_ar' => $nameAr,
                        'slug' => $slug,
                    ];
                })->all();

                return [
                    'name' => $name,
                    'slug' => $slug,
                    'movies_count' => $status['owned_count'],
                    'total_parts' => $status['total_parts'],
                    'is_complete' => $status['is_complete'],
                    'completion_percentage' => $status['completion_percentage'],
                    'missing_parts' => $status['missing_parts'],
                    'year_span' => $yearSpan,
                    'earliest_year' => $years->first() ?: 0,
                    'latest_year' => $years->last() ?: 0,
                    'avg_rating' => $avgRating,
                    'poster_path' => $poster,
                    'backdrop_path' => $backdrop,
                    'genres' => $genres,
                    'movies' => array_values($movies->unique(fn ($m) => ((is_array($m) ? ($m['tmdb_id'] ?? null) : $m->tmdb_id) ? ((is_array($m) ? $m['tmdb_id'] : $m->tmdb_id).'_'.strtolower(trim(is_array($m) ? ($m['title'] ?? '') : $m->title))) : strtolower(trim(is_array($m) ? ($m['title'] ?? '') : $m->title))))->map(fn ($m) => [
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
            })->values()->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }
}
