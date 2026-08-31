<?php

namespace App\Http\Controllers;

use App\Models\DownloadItem;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Series;
use App\Models\WatchHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // 1. Featured Spotlight Media for Hero Banner (Movies + Series with backdrop/poster)
        $featuredMovies = MediaItem::with('genres')
            ->whereNotNull('backdrop_path')
            ->orWhereNotNull('poster_path')
            ->orderByDesc('rating')
            ->limit(3)
            ->get();

        $featuredSeries = Series::with('genres')
            ->whereNotNull('backdrop_path')
            ->orWhereNotNull('poster_path')
            ->orderByDesc('rating')
            ->limit(2)
            ->get();

        $featuredMedia = $featuredMovies->concat($featuredSeries)->shuffle()->values();

        // 2. Continue Watching items (polymorphic watchable)
        $continueWatching = WatchHistory::with(['watchable'])
            ->where('is_completed', false)
            ->orderByDesc('last_watched_at')
            ->limit(6)
            ->get()
            ->map(function ($h) {
                $item = $h->watchable;
                if ($item instanceof MediaItem) {
                    $item->loadMissing('subtitles');
                    return [
                        'id' => $item->id,
                        'title' => $item->title,
                        'title_ar' => $item->title_ar,
                        'type' => 'movie',
                        'poster_path' => $item->poster_path,
                        'backdrop_path' => $item->backdrop_path,
                        'subtitles' => $item->subtitles,
                        'progress_percent' => $h->progress_percentage,
                        'current_time_formatted' => gmdate('H:i:s', $h->progress_seconds),
                        'stream_url' => route('stream.movie', $item->id),
                    ];
                } elseif ($item instanceof Episode) {
                    $item->loadMissing(['subtitles', 'season.series']);
                    $series = $item->season->series ?? null;
                    return [
                        'id' => $item->id,
                        'watchable_id' => $item->id,
                        'watchable_type' => 'episode',
                        'title' => ($series ? $series->title . ' - ' : '') . 'S' . ($item->season->season_number ?? 1) . 'E' . $item->episode_number . ' ' . $item->title,
                        'title_ar' => ($series ? $series->title_ar . ' - ' : '') . $item->title_ar,
                        'type' => 'episode',
                        'poster_path' => $series->poster_path ?? null,
                        'backdrop_path' => $item->still_path ?? ($series->backdrop_path ?? null),
                        'subtitles' => $item->subtitles,
                        'progress_percent' => $h->progress_percentage,
                        'current_time_formatted' => gmdate('H:i:s', $h->progress_seconds),
                        'stream_url' => route('stream.episode', $item->id),
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
            'total_episodes' => Episode::count(),
            'storage_formatted' => $totalStorageFormatted,
            'missing_subtitles_count' => $missingSubsCount,
            'active_downloads_count' => $activeDownloadsCount,
        ];

        // 6. Curated AI Mood Vibes
        $vibes = [
            ['id' => 'mind-bending', 'label_en' => 'Mind-Bending Sci-Fi', 'label_ar' => 'خيال علمي مشوق', 'icon' => 'Sparkles', 'genre' => 'Sci-Fi'],
            ['id' => 'adrenaline', 'label_en' => 'Adrenaline Rush', 'label_ar' => 'أكشن وإثارة', 'icon' => 'Zap', 'genre' => 'Action'],
            ['id' => 'thriller-noir', 'label_en' => 'Late-Night Noir', 'label_ar' => 'غموض وجريمة', 'icon' => 'Eye', 'genre' => 'Crime'],
            ['id' => 'drama-deep', 'label_en' => 'Emotional Resonance', 'label_ar' => 'دراما مؤثرة', 'icon' => 'Heart', 'genre' => 'Drama'],
            ['id' => 'fantasy-epic', 'label_en' => 'Epic Worlds', 'label_ar' => 'مغامرة وفانتازيا', 'icon' => 'Compass', 'genre' => 'Adventure'],
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
