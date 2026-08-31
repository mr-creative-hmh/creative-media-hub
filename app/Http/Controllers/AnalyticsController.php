<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Genre;
use App\Models\MediaItem;
use App\Models\Series;
use App\Models\WatchHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index()
    {
        $totalMovies = MediaItem::count();
        $totalSeries = Series::count();
        $totalEpisodes = Episode::count();

        $movieBytes = (int) MediaItem::sum('file_size_bytes');
        $episodeBytes = (int) Episode::sum('file_size_bytes');
        $totalStorageBytes = $movieBytes + $episodeBytes;

        // Format storage
        if ($totalStorageBytes >= 1073741824) {
            $storageFormatted = round($totalStorageBytes / 1073741824, 2) . ' GB';
        } elseif ($totalStorageBytes >= 1048576) {
            $storageFormatted = round($totalStorageBytes / 1048576, 2) . ' MB';
        } elseif ($totalStorageBytes > 0) {
            $storageFormatted = round($totalStorageBytes / 1024, 2) . ' KB';
        } else {
            $storageFormatted = '0 GB';
        }

        // Resolution Distribution
        $res4k = MediaItem::where('resolution', 'like', '%4K%')->count() + Episode::where('resolution', 'like', '%4K%')->count();
        $res1080p = MediaItem::where('resolution', 'like', '%1080p%')->count() + Episode::where('resolution', 'like', '%1080p%')->count();
        $res720p = MediaItem::where('resolution', 'like', '%720p%')->count() + Episode::where('resolution', 'like', '%720p%')->count();

        // Codec Distribution
        $hevc = MediaItem::where('video_codec', 'like', '%HEVC%')->count() + Episode::where('video_codec', 'like', '%HEVC%')->count();
        $h264 = MediaItem::where('video_codec', 'like', '%H.264%')->count() + Episode::where('video_codec', 'like', '%H.264%')->count();
        $av1 = MediaItem::where('video_codec', 'like', '%AV1%')->count() + Episode::where('video_codec', 'like', '%AV1%')->count();

        // Total Watch Time in Hours
        $totalWatchSeconds = (int) WatchHistory::sum('progress_seconds');
        $totalWatchHours = round($totalWatchSeconds / 3600, 1);

        // Top Genres
        $genres = Genre::withCount(['mediaItems', 'series'])
            ->get()
            ->map(fn($g) => [
                'name_en' => $g->name_en,
                'name_ar' => $g->name_ar,
                'count' => $g->media_items_count + $g->series_count,
            ])
            ->filter(fn($g) => $g['count'] > 0)
            ->sortByDesc('count')
            ->values()
            ->take(8);

        return Inertia::render('Analytics/Index', [
            'stats' => [
                'total_movies' => $totalMovies,
                'total_series' => $totalSeries,
                'total_episodes' => $totalEpisodes,
                'total_storage_bytes' => $totalStorageBytes,
                'total_storage_formatted' => $storageFormatted,
                'total_watch_hours' => $totalWatchHours,
                'resolutions' => [
                    '4k' => $res4k,
                    '1080p' => $res1080p,
                    '720p' => $res720p,
                ],
                'codecs' => [
                    'hevc' => $hevc,
                    'h264' => $h264,
                    'av1' => $av1,
                ],
                'top_genres' => $genres,
            ],
        ]);
    }
}
