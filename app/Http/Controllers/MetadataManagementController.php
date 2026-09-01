<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\MediaItem;
use App\Models\Series;
use App\Services\Scanner\VirtualLibraryScannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MetadataManagementController extends Controller
{
    protected VirtualLibraryScannerService $scannerService;

    public function __construct(VirtualLibraryScannerService $scannerService)
    {
        $this->scannerService = $scannerService;
    }

    public function index(Request $request): Response
    {
        $filter = $request->input('filter', 'all'); // all, missing_posters, missing_arabic, movies, series, low_rated
        $search = $request->input('search');

        $moviesQuery = MediaItem::query()->with(['genres', 'subtitles']);
        $seriesQuery = Series::query()->with(['genres', 'seasons.episodes.subtitles']);

        if ($search) {
            $moviesQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('title_ar', 'like', "%{$search}%");
            });
            $seriesQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('title_ar', 'like', "%{$search}%");
            });
        }

        match ($filter) {
            'missing_posters' => [
                $moviesQuery->whereNull('poster_path'),
                $seriesQuery->whereNull('poster_path'),
            ],
            'missing_arabic' => [
                $moviesQuery->whereNull('overview_ar')->orWhereNull('title_ar'),
                $seriesQuery->whereNull('overview_ar')->orWhereNull('title_ar'),
            ],
            'movies' => [
                $seriesQuery->whereRaw('1 = 0'),
            ],
            'series' => [
                $moviesQuery->whereRaw('1 = 0'),
            ],
            default => null,
        };

        $movies = $moviesQuery->orderByDesc('created_at')->limit(40)->get()->map(function ($m) {
            return [
                'id' => $m->id,
                'title' => $m->title,
                'title_ar' => $m->title_ar,
                'type' => 'movie',
                'release_year' => $m->release_year,
                'rating' => $m->rating,
                'poster_path' => $m->poster_path,
                'backdrop_path' => $m->backdrop_path,
                'overview' => $m->overview,
                'overview_ar' => $m->overview_ar,
                'has_poster' => !empty($m->poster_path),
                'has_arabic' => !empty($m->title_ar) || !empty($m->overview_ar),
                'subtitles_count' => $m->subtitles->count(),
                'file_path' => $m->file_path,
            ];
        });

        $seriesList = $seriesQuery->orderByDesc('created_at')->limit(40)->get()->map(function ($s) {
            $episodesCount = $s->seasons->sum(fn ($sea) => $sea->episodes->count());
            return [
                'id' => $s->id,
                'title' => $s->title,
                'title_ar' => $s->title_ar,
                'type' => 'series',
                'release_year' => $s->release_year,
                'rating' => $s->rating,
                'poster_path' => $s->poster_path,
                'backdrop_path' => $s->backdrop_path,
                'overview' => $s->overview,
                'overview_ar' => $s->overview_ar,
                'has_poster' => !empty($s->poster_path),
                'has_arabic' => !empty($s->title_ar) || !empty($s->overview_ar),
                'seasons_count' => $s->seasons->count(),
                'episodes_count' => $episodesCount,
                'seasons' => $s->seasons,
            ];
        });

        $combined = $movies->concat($seriesList)->values();

        $stats = [
            'total_items' => MediaItem::count() + Series::count(),
            'missing_posters_count' => MediaItem::whereNull('poster_path')->count() + Series::whereNull('poster_path')->count(),
            'missing_arabic_count' => MediaItem::whereNull('overview_ar')->count() + Series::whereNull('overview_ar')->count(),
            'movies_count' => MediaItem::count(),
            'series_count' => Series::count(),
        ];

        return Inertia::render('Metadata/Index', [
            'items' => $combined,
            'stats' => $stats,
            'filters' => [
                'filter' => $filter,
                'search' => $search,
            ],
        ]);
    }

    public function batchEnrich(Request $request): JsonResponse
    {
        $limit = max(10, min(100, (int) $request->input('limit', 50)));
        $result = $this->scannerService->enrichMissingMetadata($limit);
        return response()->json([
            'success' => true,
            'message' => "Successfully enriched {$result['enriched_count']} items with bilingual metadata and artwork.",
            'result' => $result,
        ]);
    }
}
