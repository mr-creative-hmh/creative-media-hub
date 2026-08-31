<?php

namespace App\Http\Controllers;

use App\Models\DownloadItem;
use App\Models\MediaItem;
use App\Models\Series;
use App\Models\Subtitle;
use App\Models\WatchHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // 1. Featured Spotlight Media for Hero Banner (Movies + Series with backdrop)
        $featuredMovies = MediaItem::with('genres')
            ->whereNotNull('backdrop_url')
            ->orderByDesc('rating')
            ->limit(3)
            ->get();

        $featuredSeries = Series::with('genres')
            ->whereNotNull('backdrop_url')
            ->orderByDesc('rating')
            ->limit(2)
            ->get();

        $featuredMedia = $featuredMovies->concat($featuredSeries)->shuffle()->values();

        // 2. Continue Watching items
        $continueWatching = WatchHistory::with(['mediaItem', 'episode.season.series'])
            ->where('is_completed', false)
            ->orderByDesc('last_watched_at')
            ->limit(6)
            ->get()
            ->map(function ($h) {
                if ($h->mediaItem) {
                    return [
                        'id' => $h->mediaItem->id,
                        'title' => $h->mediaItem->title,
                        'title_ar' => $h->mediaItem->title_ar,
                        'type' => 'movie',
                        'poster_url' => $h->mediaItem->poster_url,
                        'backdrop_url' => $h->mediaItem->backdrop_url,
                        'progress_percent' => $h->progress_percentage,
                        'current_time_formatted' => gmdate('H:i:s', $h->current_time_seconds),
                        'stream_url' => route('stream.movie', $h->mediaItem->id),
                    ];
                } elseif ($h->episode) {
                    $series = $h->episode->season->series ?? null;
                    return [
                        'id' => $h->episode->id,
                        'title' => ($series ? $series->title . ' - ' : '') . 'S' . $h->episode->season->season_number . 'E' . $h->episode->episode_number . ' ' . $h->episode->title,
                        'title_ar' => ($series ? $series->title_ar . ' - ' : '') . $h->episode->title_ar,
                        'type' => 'episode',
                        'poster_url' => $series->poster_url ?? null,
                        'backdrop_url' => $h->episode->still_url ?? ($series->backdrop_url ?? null),
                        'progress_percent' => $h->progress_percentage,
                        'current_time_formatted' => gmdate('H:i:s', $h->current_time_seconds),
                        'stream_url' => route('stream.episode', $h->episode->id),
                    ];
                }
                return null;
            })
            ->filter()
            ->values();

        // 3. Recently Added Movies
        $recentlyAddedMovies = MediaItem::with(['genres', 'subtitles'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        // 4. Popular Series
        $popularSeries = Series::with(['genres', 'seasons'])
            ->orderByDesc('rating')
            ->limit(8)
            ->get();

        // 5. Quick Stats
        $totalBytes = MediaItem::sum('file_size_bytes');
        $totalStorageFormatted = $totalBytes > 1099511627776
            ? round($totalBytes / 1099511627776, 2) . ' TB'
            : round($totalBytes / 1073741824, 2) . ' GB';

        $missingSubsCount = MediaItem::whereDoesntHave('subtitles', fn ($q) => $q->where('language', 'ar'))->count();
        $activeDownloadsCount = DownloadItem::whereIn('status', ['downloading', 'queued', 'extracting'])->count();

        $stats = [
            'total_movies' => MediaItem::count(),
            'total_series' => Series::count(),
            'total_episodes' => \App\Models\Episode::count(),
            'storage_formatted' => $totalStorageFormatted,
            'missing_subtitles_count' => $missingSubsCount,
            'active_downloads_count' => $activeDownloadsCount,
        ];

        // 6. Curated AI Mood Vibes
        $vibes = [
            ['id' => 'mind-bending', 'label_en' => 'Mind-Bending Sci-Fi', 'label_ar' => 'خيال علمي عميق', 'icon' => 'Sparkles', 'genre' => 'Sci-Fi'],
            ['id' => 'adrenaline', 'label_en' => 'Adrenaline Rush', 'label_ar' => 'أكشن وحماس', 'icon' => 'Zap', 'genre' => 'Action'],
            ['id' => 'thriller-noir', 'label_en' => 'Late-Night Noir', 'label_ar' => 'جريمة وغموض', 'icon' => 'Eye', 'genre' => 'Crime'],
            ['id' => 'drama-deep', 'label_en' => 'Emotional Resonance', 'label_ar' => 'دراما إنسانية مؤثرة', 'icon' => 'Heart', 'genre' => 'Drama'],
            ['id' => 'fantasy-epic', 'label_en' => 'Epic Worlds', 'label_ar' => 'عوالم وفانتازيا', 'icon' => 'Compass', 'genre' => 'Adventure'],
        ];

        return Inertia::render('Dashboard/Index', [
            'featuredMedia' => $featuredMedia,
            'continueWatching' => $continueWatching,
            'recentlyAddedMovies' => $recentlyAddedMovies,
            'popularSeries' => $popularSeries,
            'stats' => $stats,
            'vibes' => $vibes,
        ]);
    }
}
