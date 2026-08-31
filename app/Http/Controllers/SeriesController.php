<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\Series;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SeriesController extends Controller
{
    public function index(Request $request)
    {
        $query = Series::query()->with(['genres', 'seasons.episodes', 'actors']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('title_ar', 'like', "%{$search}%")
                  ->orWhere('overview', 'like', "%{$search}%")
                  ->orWhereHas('people', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        if ($genre = $request->input('genre')) {
            $query->whereHas('genres', fn($q) => $q->where('slug', $genre));
        }

        if ($request->boolean('favorite_only')) {
            $query->favorites();
        }

        $sort = $request->input('sort', 'rating');
        $direction = $request->input('direction', 'desc');

        match ($sort) {
            'year' => $query->orderBy('release_year', $direction),
            'title' => $query->orderBy('title', $direction === 'desc' ? 'desc' : 'asc'),
            default => $query->orderBy('rating', 'desc'),
        };

        $seriesList = $query->paginate(24)->withQueryString();
        $genres = Genre::orderBy('name_en')->get();

        $heroSeries = Series::with(['genres', 'actors'])
            ->whereNotNull('backdrop_path')
            ->orderByDesc('rating')
            ->first();

        return Inertia::render('Series/Index', [
            'seriesList' => $seriesList,
            'genres' => $genres,
            'heroSeries' => $heroSeries,
            'filters' => $request->only(['search', 'genre', 'sort', 'direction', 'favorite_only']),
        ]);
    }

    public function show(Series $series)
    {
        $series->load([
            'genres',
            'people',
            'seasons.episodes.subtitles',
            'seasons.episodes.watchHistories'
        ]);

        return Inertia::render('Series/Show', [
            'series' => $series,
        ]);
    }

    public function toggleFavorite(Series $series)
    {
        $series->update(['is_favorite' => !$series->is_favorite]);

        return response()->json([
            'status' => 'success',
            'is_favorite' => $series->is_favorite,
        ]);
    }
}
