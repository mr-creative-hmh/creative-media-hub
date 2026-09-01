<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\MediaItem;
use App\Models\Person;
use App\Services\Metadata\ArtworkDownloadService;
use App\Services\Metadata\MetadataAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MediaController extends Controller
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
        $query = MediaItem::query()->with(['genres', 'directors', 'subtitles']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('title_ar', 'like', "%{$search}%")
                  ->orWhere('overview', 'like', "%{$search}%")
                  ->orWhereHas('people', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        if ($genre = $request->input('genre')) {
            $query->byGenre($genre);
        }

        if ($resolution = $request->input('resolution')) {
            $query->byResolution($resolution);
        }

        if ($fromYear = $request->input('from_year')) {
            $toYear = $request->input('to_year', date('Y') + 1);
            $query->byYearRange((int) $fromYear, (int) $toYear);
        }

        if ($request->boolean('favorite_only')) {
            $query->favorites();
        }

        if ($vibe = $request->input('vibe')) {
            $query->where('mood_tags', 'like', "%{$vibe}%");
        }

        $sort = $request->input('sort', 'rating');
        $direction = $request->input('direction', 'desc');

        match ($sort) {
            'year' => $query->orderBy('release_year', $direction),
            'title' => $query->orderBy('title', $direction === 'desc' ? 'desc' : 'asc'),
            'runtime' => $query->orderBy('runtime_minutes', $direction),
            'date_added' => $query->orderBy('created_at', $direction),
            default => $query->orderBy('rating', 'desc'),
        };

        $movies = $query->paginate(24)->withQueryString();
        $genres = Genre::orderBy('name_en')->get();

        // Spotlight / Hero items (Latest added & top rated movies for slides carousel)
        $heroItems = MediaItem::with(['genres', 'directors', 'actors', 'subtitles'])
            ->where(function ($q) {
                $q->whereNotNull('backdrop_path')->orWhereNotNull('poster_path');
            })
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        if ($heroItems->isEmpty()) {
            $heroItems = MediaItem::with(['genres', 'directors', 'actors', 'subtitles'])
                ->orderByDesc('rating')
                ->limit(6)
                ->get();
        }

        $heroItem = $heroItems->first();

        return Inertia::render('Movies/Index', [
            'movies' => $movies,
            'genres' => $genres,
            'heroItem' => $heroItem,
            'heroItems' => $heroItems,
            'filters' => $request->only(['search', 'genre', 'resolution', 'from_year', 'to_year', 'sort', 'direction', 'favorite_only', 'vibe']),
        ]);
    }

    public function show(MediaItem $mediaItem)
    {
        $mediaItem->load(['genres', 'people', 'subtitles', 'watchHistories']);

        return response()->json($mediaItem);
    }

    public function toggleFavorite(MediaItem $mediaItem)
    {
        $mediaItem->update(['is_favorite' => !$mediaItem->is_favorite]);

        return response()->json([
            'status' => 'success',
            'is_favorite' => $mediaItem->is_favorite,
        ]);
    }

    public function searchMetadata(Request $request): JsonResponse
    {
        $query = $request->input('query');
        $year = $request->input('year') ? (int) $request->input('year') : null;

        if (empty($query)) {
            return response()->json(['results' => []]);
        }

        $results = $this->metadata->searchMovie($query, $year);
        return response()->json(['results' => $results]);
    }

    public function fixMatch(Request $request, MediaItem $mediaItem): JsonResponse
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
            'runtime_minutes' => 'nullable|numeric',
        ]);

        $posterUrl = $this->artwork->downloadPoster($validated['poster_path'] ?? null);
        $backdropUrl = $this->artwork->downloadBackdrop($validated['backdrop_path'] ?? null);

        $mediaItem->update([
            'title' => $validated['title'],
            'release_year' => $validated['year'] ?? $mediaItem->release_year,
            'overview' => $validated['overview'] ?? $mediaItem->overview,
            'overview_ar' => $validated['overview_ar'] ?? $mediaItem->overview_ar,
            'poster_path' => $posterUrl ?? $mediaItem->poster_path,
            'backdrop_path' => $backdropUrl ?? $mediaItem->backdrop_path,
            'rating' => $validated['rating'] ?? $mediaItem->rating,
            'runtime_minutes' => $validated['runtime_minutes'] ?? $mediaItem->runtime_minutes,
        ]);

        $mediaItem->load(['genres', 'subtitles']);

        return response()->json([
            'success' => true,
            'message' => 'Metadata successfully matched and saved!',
            'media' => $mediaItem,
        ]);
    }

    public function updateMetadata(Request $request, MediaItem $mediaItem): JsonResponse
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

        $mediaItem->update($validated);
        $mediaItem->load(['genres', 'subtitles']);

        return response()->json([
            'success' => true,
            'message' => 'Movie metadata manually updated.',
            'media' => $mediaItem,
        ]);
    }

    public function getVibes()
    {
        $allMoods = MediaItem::whereNotNull('mood_tags')->pluck('mood_tags')->flatten()->unique()->values();
        return response()->json($allMoods);
    }

    public function getCastExplorer(Person $person)
    {
        $person->load(['mediaItems.genres', 'series.genres']);

        return response()->json([
            'person' => $person,
            'movies' => $person->mediaItems,
            'series' => $person->series,
        ]);
    }

    public function showBySlug(Request $request, string $slug)
    {
        $movie = is_numeric($slug)
            ? MediaItem::with(['genres', 'subtitles', 'people'])->find($slug)
            : MediaItem::with(['genres', 'subtitles', 'people'])->where('slug', $slug)->first();

        if (!$movie) {
            $movie = MediaItem::with(['genres', 'subtitles', 'people'])->where('title', str_replace('-', ' ', $slug))->firstOrFail();
        }

        $query = MediaItem::query()->with(['genres', 'subtitles']);
        $movies = $query->paginate(24)->withQueryString();
        $genres = Genre::orderBy('name_en')->get();

        return Inertia::render('Movies/Index', [
            'movies' => $movies,
            'genres' => $genres,
            'filters' => [],
            'activeMovie' => $movie,
            'autoPlay' => $request->boolean('play'),
        ]);
    }

}
