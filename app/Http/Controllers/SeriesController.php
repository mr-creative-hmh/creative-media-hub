<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\Series;
use App\Services\Metadata\ArtworkDownloadService;
use App\Services\Metadata\MetadataAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SeriesController extends Controller
{
    protected MetadataAggregator $metadata;
    protected ArtworkDownloadService $artwork;

    public function __construct(MetadataAggregator $metadata, ArtworkDownloadService $artwork)
    {
        $this->metadata = $metadata;
        $this->artwork = $artwork;
    }

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

        $heroSeriesList = Series::with(['genres', 'actors', 'seasons.episodes.subtitles'])
            ->where(function ($q) {
                $q->whereNotNull('backdrop_path')->orWhereNotNull('poster_path');
            })
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        if ($heroSeriesList->isEmpty()) {
            $heroSeriesList = Series::with(['genres', 'actors', 'seasons.episodes.subtitles'])
                ->orderByDesc('rating')
                ->limit(6)
                ->get();
        }

        $heroSeries = $heroSeriesList->first();

        return Inertia::render('Series/Index', [
            'seriesList' => $seriesList,
            'genres' => $genres,
            'heroSeries' => $heroSeries,
            'heroItems' => $heroSeriesList,
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

    public function searchMetadata(Request $request): JsonResponse
    {
        $query = $request->input('query');
        $year = $request->input('year') ? (int) $request->input('year') : null;

        if (empty($query)) {
            return response()->json(['results' => []]);
        }

        $results = $this->metadata->searchSeries($query, $year);
        return response()->json(['results' => $results]);
    }

    public function fixMatch(Request $request, Series $series): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'provider' => 'nullable|string',
            'id' => 'nullable|string',
            'year' => 'nullable|numeric',
            'overview' => 'nullable|string',
            'overview_ar' => 'nullable|string',
            'poster_path' => 'nullable|string',
            'backdrop_path' => 'nullable|string',
            'rating' => 'nullable|numeric',
        ]);

        $posterUrl = $this->artwork->downloadPoster($validated['poster_path'] ?? null);
        $backdropUrl = $this->artwork->downloadBackdrop($validated['backdrop_path'] ?? null);

        $series->update([
            'title' => $validated['title'],
            'release_year' => $validated['year'] ?? $series->release_year,
            'overview' => $validated['overview'] ?? $series->overview,
            'overview_ar' => $validated['overview_ar'] ?? $series->overview_ar,
            'poster_path' => $posterUrl ?? $series->poster_path,
            'backdrop_path' => $backdropUrl ?? $series->backdrop_path,
            'rating' => $validated['rating'] ?? $series->rating,
        ]);

        $series->load(['genres', 'seasons.episodes.subtitles']);

        return response()->json([
            'success' => true,
            'message' => 'TV Series metadata successfully matched and saved!',
            'series' => $series,
        ]);
    }

    public function updateMetadata(Request $request, Series $series): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'title_ar' => 'nullable|string',
            'release_year' => 'nullable|numeric',
            'rating' => 'nullable|numeric',
            'overview' => 'nullable|string',
            'overview_ar' => 'nullable|string',
            'poster_path' => 'nullable|string',
            'backdrop_path' => 'nullable|string',
        ]);

        if (!empty($validated['poster_path']) && filter_var($validated['poster_path'], FILTER_VALIDATE_URL)) {
            $validated['poster_path'] = $this->artwork->downloadPoster($validated['poster_path']);
        }
        if (!empty($validated['backdrop_path']) && filter_var($validated['backdrop_path'], FILTER_VALIDATE_URL)) {
            $validated['backdrop_path'] = $this->artwork->downloadBackdrop($validated['backdrop_path']);
        }

        $series->update($validated);
        $series->load(['genres', 'seasons.episodes.subtitles']);

        return response()->json([
            'success' => true,
            'message' => 'Series metadata manually updated.',
            'series' => $series,
        ]);
    }

    public function showBySlug(string $seriesSlug)
    {
        $series = is_numeric($seriesSlug)
            ? Series::find($seriesSlug)
            : Series::where('slug', $seriesSlug)->first();

        if (!$series) {
            $series = Series::where('title', str_replace('-', ' ', $seriesSlug))->firstOrFail();
        }

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

    public function showSeason(string $seriesSlug, int $seasonNumber)
    {
        $series = is_numeric($seriesSlug)
            ? Series::find($seriesSlug)
            : Series::where('slug', $seriesSlug)->first();

        if (!$series) {
            $series = Series::where('title', str_replace('-', ' ', $seriesSlug))->firstOrFail();
        }

        $series->load([
            'genres',
            'people',
            'seasons.episodes.subtitles',
            'seasons.episodes.watchHistories'
        ]);

        return Inertia::render('Series/Show', [
            'series' => $series,
            'initialSeasonNumber' => (int) $seasonNumber,
        ]);
    }

    public function showEpisode(string $seriesSlug, int $seasonNumber, int $episodeNumber)
    {
        $series = is_numeric($seriesSlug)
            ? Series::find($seriesSlug)
            : Series::where('slug', $seriesSlug)->first();

        if (!$series) {
            $series = Series::where('title', str_replace('-', ' ', $seriesSlug))->firstOrFail();
        }

        $series->load([
            'genres',
            'people',
            'seasons.episodes.subtitles',
            'seasons.episodes.watchHistories'
        ]);

        $episode = \App\Models\Episode::where('series_id', $series->id)
            ->where('episode_number', $episodeNumber)
            ->whereHas('season', fn($q) => $q->where('season_number', $seasonNumber))
            ->with(['subtitles'])
            ->first();

        return Inertia::render('Series/Show', [
            'series' => $series,
            'initialSeasonNumber' => (int) $seasonNumber,
            'initialEpisode' => $episode,
            'autoPlay' => true,
        ]);
    }

}
