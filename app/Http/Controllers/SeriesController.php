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

        if ($origin = $request->input('origin')) {
            match ($origin) {
                'arabic' => $query->where(function ($q) {
                    $q->where('original_language', 'ar')
                      ->orWhereIn('origin_country', ['EG', 'SA', 'SY', 'LB', 'AE', 'KW', 'JO', 'MA', 'IQ', 'TN', 'DZ', 'SD', 'YE', 'OM', 'QA', 'BH'])
                      ->orWhereNotNull('title_ar')
                      ->orWhere('title', 'like', '%مسلسل%');
                }),
                'indian' => $query->where(function ($q) {
                    $q->whereIn('original_language', ['hi', 'te', 'ta', 'ml', 'kn', 'mr', 'bn', 'pa', 'ur'])
                      ->orWhere('origin_country', 'IN');
                }),
                'asian' => $query->where(function ($q) {
                    $q->whereIn('original_language', ['ja', 'ko', 'zh', 'cn', 'hk', 'tw', 'th'])
                      ->orWhereIn('origin_country', ['JP', 'KR', 'CN', 'HK', 'TW', 'TH'])
                      ->orWhereHas('genres', fn($g) => $g->where('slug', 'like', '%anime%'));
                }),
                'turkish' => $query->where(function ($q) {
                    $q->where('original_language', 'tr')
                      ->orWhere('origin_country', 'TR');
                }),
                'hollywood' => $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('original_language', 'en')
                            ->orWhereIn('origin_country', ['US', 'GB', 'CA', 'AU']);
                    });
                }),
                'european' => $query->where(function ($q) {
                    $q->whereIn('original_language', ['fr', 'de', 'it', 'es', 'pt', 'ru', 'sv', 'da', 'no', 'nl', 'pl'])
                      ->orWhereIn('origin_country', ['FR', 'DE', 'IT', 'ES', 'SE', 'DK', 'NO', 'NL', 'PL', 'RU']);
                }),
                default => null,
            };
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
            'title_ar' => 'nullable|string',
            'provider' => 'nullable|string',
            'id' => 'nullable|string',
            'year' => 'nullable|numeric',
            'overview' => 'nullable|string',
            'overview_ar' => 'nullable|string',
            'poster_path' => 'nullable|string',
            'backdrop_path' => 'nullable|string',
            'rating' => 'nullable|numeric',
        ]);

        $providerKey = strtolower($validated['provider'] ?? 'tmdb');
        $providerId = $validated['id'] ?? null;

        $titleAr = $validated['title_ar'] ?? null;
        $overviewAr = $validated['overview_ar'] ?? null;
        $tmdbId = $series->tmdb_id;
        $imdbId = $series->imdb_id;

        if ($providerId) {
            $details = $this->metadata->getSeriesDetails($providerId, $providerKey);
            if ($details) {
                $titleAr = $titleAr ?: ($details['title_ar'] ?? null);
                $overviewAr = $overviewAr ?: ($details['overview_ar'] ?? null);
                $tmdbId = $details['tmdb_id'] ?? $tmdbId;
                $imdbId = $details['imdb_id'] ?? $imdbId;
            }
        }

        $metaDataPayload = [
            'title' => $validated['title'],
            'title_ar' => $titleAr,
            'overview' => $validated['overview'] ?? $series->overview,
            'overview_ar' => $overviewAr,
        ];
        $this->metadata->ensureArabicMetadata($metaDataPayload, 'series');
        $titleAr = $metaDataPayload['title_ar'] ?? $titleAr;
        $overviewAr = $metaDataPayload['overview_ar'] ?? $overviewAr;

        $posterUrl = $this->artwork->downloadPoster($validated['poster_path'] ?? null);
        $backdropUrl = $this->artwork->downloadBackdrop($validated['backdrop_path'] ?? null);

        $series->update([
            'title' => $validated['title'],
            'title_ar' => $titleAr ?? $series->title_ar,
            'release_year' => $validated['year'] ?? $series->release_year,
            'overview' => $validated['overview'] ?? $series->overview,
            'overview_ar' => $overviewAr ?? $series->overview_ar,
            'tmdb_id' => $tmdbId,
            'imdb_id' => $imdbId,
            'poster_path' => $posterUrl ?? $series->poster_path,
            'backdrop_path' => $backdropUrl ?? $series->backdrop_path,
            'rating' => $validated['rating'] ?? $series->rating,
        ]);

        $series->load(['genres', 'seasons.episodes.subtitles']);

        return response()->json([
            'success' => true,
            'message' => 'Series metadata successfully matched and saved with bilingual details!',
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

    public function showSeason(Series $series, int $seasonNumber)
    {
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

    public function showEpisode(Series $series, int $seasonNumber, int $episodeNumber)
    {
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
