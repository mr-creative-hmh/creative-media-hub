<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\MediaItem;
use App\Models\Person;
use App\Services\Metadata\MetadataAggregator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MediaController extends Controller
{
    protected MetadataAggregator $metadata;

    public function __construct(MetadataAggregator $metadata)
    {
        $this->metadata = $metadata;
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

        // Spotlight / Hero item (Top rated favorite or latest movie)
        $heroItem = MediaItem::with(['genres', 'directors', 'actors'])
            ->whereNotNull('backdrop_path')
            ->orderByDesc('rating')
            ->first();

        return Inertia::render('Movies/Index', [
            'movies' => $movies,
            'genres' => $genres,
            'heroItem' => $heroItem,
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
}
