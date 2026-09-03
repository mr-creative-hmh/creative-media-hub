<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');

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

        $allMoviesInCollections = $query->orderBy('release_year')->get();

        // Group movies by collection_name - ONLY include genuine collections with 2 or more movies!
        $grouped = $allMoviesInCollections->groupBy('collection_name');

        $collections = $grouped
            ->filter(fn ($movies) => $movies->count() >= 2)
            ->map(function ($movies, $name) {
                $first = $movies->first();
                $years = $movies->pluck('release_year')->filter()->sort()->values();
                $yearSpan = $years->isNotEmpty()
                    ? ($years->first() === $years->last() ? (string) $years->first() : "{$years->first()} - {$years->last()}")
                    : null;

                $poster = $movies->pluck('collection_poster')->filter()->first()
                    ?? $movies->pluck('poster_path')->filter()->first();
                $backdrop = $movies->pluck('backdrop_path')->filter()->first();
                $avgRating = round($movies->avg('rating'), 1);
                $slug = Str::slug($name);

                return [
                    'name' => $name,
                    'slug' => $slug,
                    'movies_count' => $movies->count(),
                    'year_span' => $yearSpan,
                    'avg_rating' => $avgRating,
                    'poster_path' => $poster,
                    'backdrop_path' => $backdrop,
                    'movies' => $movies->map(fn ($m) => [
                        'id' => $m->id,
                        'title' => $m->title,
                        'title_ar' => $m->title_ar,
                        'slug' => $m->slug ?: "movie-{$m->id}",
                        'release_year' => $m->release_year,
                        'rating' => $m->rating,
                        'poster_path' => $m->poster_path,
                        'backdrop_path' => $m->backdrop_path,
                        'resolution' => $m->resolution,
                        'runtime_minutes' => $m->runtime_minutes,
                    ])->values(),
                ];
            })->values()->sortByDesc('movies_count')->values();

        // Also fetch popular franchise suggestions that have at least 1 movie in library
        return Inertia::render('Collections/Index', [
            'collections' => $collections,
            'filters' => [
                'search' => $search,
            ],
            'total_collections' => $collections->count(),
            'total_franchise_movies' => $allMoviesInCollections->count(),
        ]);
    }

    public function show(string $slug): Response
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
            ->map(function ($m) {
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

        return Inertia::render('Collections/Show', [
            'collection' => [
                'name' => $matchedName,
                'slug' => Str::slug($matchedName),
                'movies_count' => $movies->count(),
                'year_span' => $yearSpan,
                'poster_path' => $poster,
                'backdrop_path' => $backdrop,
                'avg_rating' => round($movies->avg('rating'), 1),
                'movies' => $movies,
            ],
        ]);
    }
}
