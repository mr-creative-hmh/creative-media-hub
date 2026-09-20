<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Genre;
use App\Models\MediaItem;
use App\Models\Series;
use App\Models\WatchHistory;
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

        $formatBytes = function (int|float $bytes): string {
            if ($bytes >= 1099511627776) {
                return round($bytes / 1099511627776, 2).' TB';
            }
            if ($bytes >= 1073741824) {
                return round($bytes / 1073741824, 2).' GB';
            }
            if ($bytes >= 1048576) {
                return round($bytes / 1048576, 2).' MB';
            }
            if ($bytes > 0) {
                return round($bytes / 1024, 2).' KB';
            }

            return '0 GB';
        };

        // 1. Physical Host Drives Detection
        $detectedDriveLetters = ['C:', 'H:'];
        $samplePaths = MediaItem::whereNotNull('file_path')->take(5)->pluck('file_path');
        foreach ($samplePaths as $p) {
            if (preg_match('/^([A-Za-z]:)/', $p, $m)) {
                $detectedDriveLetters[] = strtoupper($m[1]);
            }
        }
        $detectedDriveLetters = array_unique($detectedDriveLetters);

        $hostDrives = [];
        foreach ($detectedDriveLetters as $letter) {
            if (@file_exists($letter)) {
                $total = @disk_total_space($letter);
                $free = @disk_free_space($letter);
                if ($total) {
                    $used = $total - $free;
                    $usedPct = round(($used / $total) * 100, 1);
                    $hostDrives[] = [
                        'drive' => $letter,
                        'total_formatted' => $formatBytes($total),
                        'free_formatted' => $formatBytes($free),
                        'used_formatted' => $formatBytes($used),
                        'total_bytes' => $total,
                        'free_bytes' => $free,
                        'used_bytes' => $used,
                        'used_percent' => $usedPct,
                        'status' => $usedPct >= 95 ? 'critical' : ($usedPct >= 85 ? 'warning' : 'normal'),
                    ];
                }
            }
        }

        // 2. Average File Sizes
        $avgMovieBytes = $totalMovies > 0 ? (int) round($movieBytes / $totalMovies) : 0;
        $avgEpisodeBytes = $totalEpisodes > 0 ? (int) round($episodeBytes / $totalEpisodes) : 0;
        $avgSeriesBytes = $totalSeries > 0 ? (int) round($episodeBytes / $totalSeries) : 0;

        // 3. Storage by Resolution (Bytes, Counts, Percentages)
        $resolutions = [
            '4k' => ['label' => '4K Ultra HD', 'bytes' => 0, 'count' => 0, 'formatted' => '0 GB', 'percent' => 0],
            '1080p' => ['label' => '1080p Full HD', 'bytes' => 0, 'count' => 0, 'formatted' => '0 GB', 'percent' => 0],
            '720p' => ['label' => '720p HD', 'bytes' => 0, 'count' => 0, 'formatted' => '0 GB', 'percent' => 0],
            'sd' => ['label' => 'SD / 480p', 'bytes' => 0, 'count' => 0, 'formatted' => '0 GB', 'percent' => 0],
        ];
        $mRes = MediaItem::selectRaw('resolution, count(*) as cnt, sum(file_size_bytes) as total_bytes')->groupBy('resolution')->get();
        $eRes = Episode::selectRaw('resolution, count(*) as cnt, sum(file_size_bytes) as total_bytes')->groupBy('resolution')->get();

        foreach ($mRes->concat($eRes) as $row) {
            $res = strtolower($row->resolution ?? '');
            $cat = 'sd';
            if (str_contains($res, '4k') || str_contains($res, '2160') || str_contains($res, 'uhd')) {
                $cat = '4k';
            } elseif (str_contains($res, '1080') || str_contains($res, 'fhd')) {
                $cat = '1080p';
            } elseif (str_contains($res, '720')) {
                $cat = '720p';
            }
            $resolutions[$cat]['count'] += (int) $row->cnt;
            $resolutions[$cat]['bytes'] += (int) $row->total_bytes;
        }
        foreach ($resolutions as $k => $data) {
            $resolutions[$k]['formatted'] = $formatBytes($data['bytes']);
            $resolutions[$k]['percent'] = $totalStorageBytes > 0 ? round(($data['bytes'] / $totalStorageBytes) * 100, 1) : 0;
        }

        // 4. Storage by Codec & Modern Efficiency
        $codecs = [
            'hevc' => ['label' => 'HEVC / H.265', 'badge' => 'Efficient', 'bytes' => 0, 'count' => 0, 'formatted' => '0 GB', 'percent' => 0],
            'h264' => ['label' => 'AVC / H.264', 'badge' => 'Standard', 'bytes' => 0, 'count' => 0, 'formatted' => '0 GB', 'percent' => 0],
            'av1' => ['label' => 'AV1 Next-Gen', 'badge' => 'Ultra', 'bytes' => 0, 'count' => 0, 'formatted' => '0 GB', 'percent' => 0],
            'other' => ['label' => 'Other / Legacy', 'badge' => 'Legacy', 'bytes' => 0, 'count' => 0, 'formatted' => '0 GB', 'percent' => 0],
        ];
        $mCod = MediaItem::selectRaw('video_codec, count(*) as cnt, sum(file_size_bytes) as total_bytes')->groupBy('video_codec')->get();
        $eCod = Episode::selectRaw('video_codec, count(*) as cnt, sum(file_size_bytes) as total_bytes')->groupBy('video_codec')->get();

        foreach ($mCod->concat($eCod) as $row) {
            $vc = strtolower($row->video_codec ?? '');
            $cat = 'other';
            if (str_contains($vc, 'hevc') || str_contains($vc, 'h.265') || str_contains($vc, 'h265')) {
                $cat = 'hevc';
            } elseif (str_contains($vc, 'av1')) {
                $cat = 'av1';
            } elseif (str_contains($vc, 'h.264') || str_contains($vc, 'h264') || str_contains($vc, 'avc')) {
                $cat = 'h264';
            }
            $codecs[$cat]['count'] += (int) $row->cnt;
            $codecs[$cat]['bytes'] += (int) $row->total_bytes;
        }
        foreach ($codecs as $k => $data) {
            $codecs[$k]['formatted'] = $formatBytes($data['bytes']);
            $codecs[$k]['percent'] = $totalStorageBytes > 0 ? round(($data['bytes'] / $totalStorageBytes) * 100, 1) : 0;
        }

        $modernBytes = $codecs['hevc']['bytes'] + $codecs['av1']['bytes'];
        $modernAdoptionPercent = $totalStorageBytes > 0 ? round(($modernBytes / $totalStorageBytes) * 100, 1) : 0;
        // Estimated reclaimable space (~35% savings if legacy H.264 is converted to HEVC)
        $potentialSavingsBytes = (int) round($codecs['h264']['bytes'] * 0.35);

        // 5. Audio Distribution
        $surroundCount = MediaItem::where(function ($q) {
            $q->where('audio_channels', '>=', 6)
                ->orWhere('audio_channel_layout', 'like', '%5.1%')
                ->orWhere('audio_channel_layout', 'like', '%7.1%');
        })->count() + Episode::where(function ($q) {
            $q->where('audio_channels', '>=', 6)
                ->orWhere('audio_channel_layout', 'like', '%5.1%')
                ->orWhere('audio_channel_layout', 'like', '%7.1%');
        })->count();

        $stereoCount = MediaItem::where(function ($q) {
            $q->where('audio_channels', '<=', 2)
                ->orWhere('audio_channel_layout', 'like', '%stereo%')
                ->orWhere('audio_channel_layout', 'like', '%2.0%');
        })->count() + Episode::where(function ($q) {
            $q->where('audio_channels', '<=', 2)
                ->orWhere('audio_channel_layout', 'like', '%stereo%')
                ->orWhere('audio_channel_layout', 'like', '%2.0%');
        })->count();

        // 6. Top 10 Storage Heavyweights (Largest Files on Disk)
        $heavyMovies = MediaItem::orderByDesc('file_size_bytes')->take(8)->get()->map(fn ($m) => [
            'id' => $m->id,
            'title' => $m->title,
            'title_ar' => $m->title_ar,
            'type' => 'movie',
            'size_bytes' => (int) $m->file_size_bytes,
            'size_formatted' => $formatBytes((int) $m->file_size_bytes),
            'resolution' => $m->resolution ?: 'Unknown',
            'video_codec' => $m->video_codec ?: 'Unknown',
            'audio_codec' => $m->audio_codec ?: 'Unknown',
        ]);

        $heavyEpisodes = Episode::with('series:id,title,title_ar')->orderByDesc('file_size_bytes')->take(8)->get()->map(fn ($e) => [
            'id' => $e->id,
            'title' => ($e->series?->title ?? 'Series')." S{$e->season_number}E{$e->episode_number}".($e->title ? " - {$e->title}" : ''),
            'title_ar' => ($e->series?->title_ar ?? $e->series?->title ?? 'مسلسل')." م{$e->season_number} ح{$e->episode_number}",
            'type' => 'episode',
            'size_bytes' => (int) $e->file_size_bytes,
            'size_formatted' => $formatBytes((int) $e->file_size_bytes),
            'resolution' => $e->resolution ?: 'Unknown',
            'video_codec' => $e->video_codec ?: 'Unknown',
            'audio_codec' => $e->audio_codec ?: 'Unknown',
        ]);

        $heavyweights = $heavyMovies->concat($heavyEpisodes)->sortByDesc('size_bytes')->take(10)->values();

        // 7. Recent Storage Inflow
        $recent7DaysBytes = (int) MediaItem::where('created_at', '>=', now()->subDays(7))->sum('file_size_bytes')
            + (int) Episode::where('created_at', '>=', now()->subDays(7))->sum('file_size_bytes');

        $recent30DaysBytes = (int) MediaItem::where('created_at', '>=', now()->subDays(30))->sum('file_size_bytes')
            + (int) Episode::where('created_at', '>=', now()->subDays(30))->sum('file_size_bytes');

        // 8. Total Watch Time in Hours
        $totalWatchSeconds = (int) WatchHistory::sum('progress_seconds');
        $totalWatchHours = round($totalWatchSeconds / 3600, 1);

        // 9. Top Genres
        $genres = Genre::withCount(['mediaItems', 'series'])
            ->get()
            ->map(fn ($g) => [
                'name_en' => $g->name_en,
                'name_ar' => $g->name_ar,
                'count' => $g->media_items_count + $g->series_count,
            ])
            ->filter(fn ($g) => $g['count'] > 0)
            ->sortByDesc('count')
            ->values()
            ->take(8);

        return Inertia::render('Analytics/Index', [
            'stats' => [
                'total_movies' => $totalMovies,
                'total_series' => $totalSeries,
                'total_episodes' => $totalEpisodes,
                'total_storage_bytes' => $totalStorageBytes,
                'total_storage_formatted' => $formatBytes($totalStorageBytes),
                'movie_storage_bytes' => $movieBytes,
                'movie_storage_formatted' => $formatBytes($movieBytes),
                'movie_storage_percent' => $totalStorageBytes > 0 ? round(($movieBytes / $totalStorageBytes) * 100, 1) : 0,
                'episode_storage_bytes' => $episodeBytes,
                'episode_storage_formatted' => $formatBytes($episodeBytes),
                'episode_storage_percent' => $totalStorageBytes > 0 ? round(($episodeBytes / $totalStorageBytes) * 100, 1) : 0,
                'avg_movie_formatted' => $formatBytes($avgMovieBytes),
                'avg_episode_formatted' => $formatBytes($avgEpisodeBytes),
                'avg_series_formatted' => $formatBytes($avgSeriesBytes),
                'total_watch_hours' => $totalWatchHours,
                'host_drives' => $hostDrives,
                'resolutions' => $resolutions,
                'codecs' => $codecs,
                'modern_adoption_percent' => $modernAdoptionPercent,
                'potential_savings_formatted' => $formatBytes($potentialSavingsBytes),
                'audio' => [
                    'surround_count' => $surroundCount,
                    'stereo_count' => $stereoCount,
                    'total_audio_tracks' => $surroundCount + $stereoCount,
                ],
                'heavyweights' => $heavyweights,
                'recent_inflow' => [
                    'days_7_formatted' => $formatBytes($recent7DaysBytes),
                    'days_30_formatted' => $formatBytes($recent30DaysBytes),
                ],
                'top_genres' => $genres,
            ],
        ]);
    }
}
