<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\Series;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    /**
     * Ultra-fast live instant search for YTS-style flyout dropdown.
     * Searches movies, collections, and series titles (English & Arabic) with index backing.
     */
    public function instantSearch(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', $request->input('query', '')));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'query' => $query,
                'movies' => [],
                'collections' => [],
                'series' => [],
                'total' => 0,
            ]);
        }

        $cacheKey = 'instant_search_v3_'.md5(mb_strtolower($query));

        $results = Cache::remember($cacheKey, 300, function () use ($query) {
            $prefixTerm = "{$query}%";
            $containsTerm = "%{$query}%";

            // 1. Top Matching Movies (up to 5)
            $movies = MediaItem::query()
                ->where(function ($q) use ($containsTerm) {
                    $q->where('title', 'like', $containsTerm)
                        ->orWhere('title_ar', 'like', $containsTerm);
                })
                ->select(['id', 'slug', 'title', 'title_ar', 'release_year', 'rating', 'poster_path', 'resolution'])
                ->orderByRaw('CASE WHEN title LIKE ? THEN 1 WHEN title_ar LIKE ? THEN 2 ELSE 3 END', [$prefixTerm, $prefixTerm])
                ->orderByDesc('rating')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    $res = 'HD';
                    if (! empty($item->resolution)) {
                        if (stripos($item->resolution, '2160') !== false || stripos($item->resolution, '4k') !== false) {
                            $res = '4K';
                        } elseif (stripos($item->resolution, '1080') !== false) {
                            $res = '1080p';
                        } elseif (stripos($item->resolution, '720') !== false) {
                            $res = '720p';
                        }
                    }

                    return [
                        'id' => $item->id,
                        'slug' => $item->slug ?: (string) $item->id,
                        'title' => $item->title,
                        'title_ar' => $item->title_ar,
                        'year' => $item->release_year,
                        'rating' => $item->rating ? round($item->rating, 1) : null,
                        'poster' => $item->poster_path,
                        'quality' => $res,
                        'type' => 'movie',
                        'url' => '/movies/'.($item->slug ?: $item->id),
                    ];
                });

            // 2. Top Matching Movie Collections / Franchises (up to 3)
            $collections = MediaItem::query()
                ->whereNotNull('collection_name')
                ->where('collection_name', '!=', '')
                ->where(function ($q) use ($containsTerm) {
                    $q->where('collection_name', 'like', $containsTerm)
                        ->orWhere('title', 'like', $containsTerm);
                })
                ->selectRaw('collection_name, count(*) as count, max(poster_path) as poster, max(release_year) as year, avg(rating) as rating')
                ->groupBy('collection_name')
                ->havingRaw('count(*) >= 2')
                ->orderByRaw('CASE WHEN collection_name LIKE ? THEN 1 ELSE 2 END', [$prefixTerm])
                ->orderByDesc('count')
                ->limit(3)
                ->get()
                ->map(function ($item) {
                    $slug = Str::slug($item->collection_name);

                    return [
                        'id' => $slug,
                        'slug' => $slug,
                        'title' => $item->collection_name,
                        'title_ar' => null,
                        'year' => $item->year,
                        'rating' => $item->rating ? round($item->rating, 1) : null,
                        'poster' => $item->poster,
                        'quality' => "{$item->count} Movies",
                        'count' => $item->count,
                        'type' => 'collection',
                        'url' => '/collections/'.$slug,
                    ];
                });

            // 3. Top Matching Series (up to 3)
            $series = Series::query()
                ->where(function ($q) use ($containsTerm) {
                    $q->where('title', 'like', $containsTerm)
                        ->orWhere('title_ar', 'like', $containsTerm);
                })
                ->withCount('seasons')
                ->select(['id', 'slug', 'title', 'title_ar', 'release_year', 'rating', 'poster_path'])
                ->orderByRaw('CASE WHEN title LIKE ? THEN 1 WHEN title_ar LIKE ? THEN 2 ELSE 3 END', [$prefixTerm, $prefixTerm])
                ->orderByDesc('rating')
                ->limit(3)
                ->get()
                ->map(function ($item) {
                    $seasonsLabel = $item->seasons_count ? "{$item->seasons_count}S" : 'TV';

                    return [
                        'id' => $item->id,
                        'slug' => $item->slug ?: (string) $item->id,
                        'title' => $item->title,
                        'title_ar' => $item->title_ar,
                        'year' => $item->release_year,
                        'rating' => $item->rating ? round($item->rating, 1) : null,
                        'poster' => $item->poster_path,
                        'quality' => $seasonsLabel,
                        'type' => 'series',
                        'url' => '/series/'.($item->slug ?: $item->id),
                    ];
                });

            return [
                'movies' => $movies,
                'collections' => $collections,
                'series' => $series,
                'total' => $movies->count() + $collections->count() + $series->count(),
            ];
        });

        return response()->json(array_merge(['query' => $query], $results))
            ->header('Cache-Control', 'public, max-age=60');
    }
}
