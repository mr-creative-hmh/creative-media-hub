<?php

namespace App\Http\Controllers;

use App\Models\DownloadItem;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Series;
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

        // 2. Recently Added Movies
        $recentlyAddedMovies = MediaItem::with(['genres', 'subtitles'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        // 3. Popular Series
        $popularSeries = Series::with(['genres', 'seasons'])
            ->orderByDesc('rating')
            ->limit(8)
            ->get();

        // 5. Quick Stats
        $totalBytes = MediaItem::sum('file_size_bytes') + Episode::sum('file_size_bytes');
        if ($totalBytes >= 1099511627776) {
            $totalStorageFormatted = round($totalBytes / 1099511627776, 2).' TB';
        } elseif ($totalBytes >= 1073741824) {
            $totalStorageFormatted = round($totalBytes / 1073741824, 2).' GB';
        } elseif ($totalBytes > 0) {
            $totalStorageFormatted = round($totalBytes / 1048576, 2).' MB';
        } else {
            $totalStorageFormatted = '0 GB';
        }

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

        // 6. Curated AI Mood Vibes with pre-fetched matches
        $vibesList = [
            [
                'id' => 'mind-bending',
                'label_en' => 'Mind-Bending Sci-Fi',
                'label_ar' => 'خيال علمي مشوق',
                'icon' => 'Sparkles',
                'genre' => 'Sci-Fi',
                'description_en' => 'Deep concept stories, time paradoxes, and futuristic twists.',
                'description_ar' => 'قصص فلسفية معقدة، عوالم مستقبلية، وحبكات ذكية تأسر العقل.',
            ],
            [
                'id' => 'adrenaline',
                'label_en' => 'Adrenaline Rush',
                'label_ar' => 'أكشن وإثارة قصوى',
                'icon' => 'Zap',
                'genre' => 'Action',
                'description_en' => 'High-octane action, thrilling car chases, and non-stop pace.',
                'description_ar' => 'مطاردات سريعة، أكشن ممتع وقتال عالي الطاقة بلا توقف.',
            ],
            [
                'id' => 'thriller-noir',
                'label_en' => 'Late-Night Noir & Crime',
                'label_ar' => 'غموض وجريمة ليلية',
                'icon' => 'Eye',
                'genre' => 'Crime',
                'description_en' => 'Dark mysteries, gritty investigations, and atmospheric tension.',
                'description_ar' => 'أسرار مظلمة، تحقيقات غامضة، وأجواء ليلية مليئة بالتوتر.',
            ],
            [
                'id' => 'drama-deep',
                'label_en' => 'Emotional Resonance',
                'label_ar' => 'دراما إنسانية مؤثرة',
                'icon' => 'Heart',
                'genre' => 'Drama',
                'description_en' => 'Heartfelt human stories, deep character studies, and touching moments.',
                'description_ar' => 'قصص إنسانية عميقة تلامس المشاعر وتبقى في الذاكرة.',
            ],
            [
                'id' => 'fantasy-epic',
                'label_en' => 'Epic Worlds & Fantasy',
                'label_ar' => 'عوالم ومغامرات أسطورية',
                'icon' => 'Compass',
                'genre' => 'Adventure',
                'description_en' => 'Breathtaking fantasy realms, majestic quests, and grand journeys.',
                'description_ar' => 'عوالم خيالية شاسعة، رحلات ملحمية، وأساطير خارقة للعادة.',
            ],
        ];

        // Attach matching items for each vibe
        $vibes = array_map(function ($vibe) {
            $genreSlug = strtolower($vibe['genre']);
            $movies = MediaItem::with(['genres', 'subtitles'])
                ->whereHas('genres', fn ($q) => $q->where('slug', 'like', "%{$genreSlug}%")->orWhere('name_en', 'like', "%{$vibe['genre']}%"))
                ->orderByDesc('rating')
                ->limit(6)
                ->get()
                ->map(fn ($m) => array_merge($m->toArray(), ['type' => 'movie']));

            $series = Series::with(['genres'])
                ->whereHas('genres', fn ($q) => $q->where('slug', 'like', "%{$genreSlug}%")->orWhere('name_en', 'like', "%{$vibe['genre']}%"))
                ->orderByDesc('rating')
                ->limit(4)
                ->get()
                ->map(fn ($s) => array_merge($s->toArray(), ['type' => 'series']));

            $vibe['matches'] = $movies->concat($series)->shuffle()->values()->all();
            $vibe['match_count'] = count($vibe['matches']);

            return $vibe;
        }, $vibesList);

        return Inertia::render('Dashboard/Index', [
            'featuredMedia' => $featuredMedia,
            'recentlyAddedMovies' => $recentlyAddedMovies,
            'popularSeries' => $popularSeries,
            'stats' => $stats,
            'vibes' => $vibes,
        ]);
    }
}
