<?php

namespace App\Services\Scout;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Series;
use App\Services\Metadata\TmdbProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LibraryGapService
{
    protected const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        protected TmdbProvider $tmdbProvider
    ) {}

    /**
     * Get all missing released episodes from existing seasons in the library.
     */
    public function getMissingEpisodesSummary(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget('scout_missing_episodes');
        }

        return Cache::remember('scout_missing_episodes', self::CACHE_TTL, function () {
            $today = Carbon::today()->format('Y-m-d');
            $candidateGaps = [];

            // 1. Fast local gap detection across series with tmdb_id
            $allSeries = Series::with(['seasons.episodes'])->whereNotNull('tmdb_id')->get();
            foreach ($allSeries as $s) {
                foreach ($s->seasons as $season) {
                    $sNum = (int) $season->season_number;
                    if ($sNum <= 0) {
                        continue; // Skip specials
                    }

                    $localEps = $season->episodes
                        ->whereNotNull('file_path')
                        ->pluck('episode_number')
                        ->map(fn ($n) => (int) $n)
                        ->toArray();

                    if (empty($localEps)) {
                        continue;
                    }

                    $maxEp = max($localEps);
                    if ($maxEp > 50 && count($localEps) < 15) {
                        $filtered = array_filter($localEps, fn ($n) => $n < 50);
                        $maxEp = ! empty($filtered) ? max($filtered) : 24;
                    }

                    $expected = range(1, $maxEp);
                    $diff = array_diff($expected, $localEps);

                    if (! empty($diff)) {
                        $candidateGaps[] = [
                            'series' => $s,
                            'season_num' => $sNum,
                            'missing_numbers' => array_values($diff),
                        ];
                    }
                }
            }

            // 2. Query TMDB only for candidate seasons with detected gaps
            $missing = [];
            foreach ($candidateGaps as $cg) {
                $s = $cg['series'];
                $sNum = $cg['season_num'];

                $tmdbEpisodes = Cache::remember("scout_tmdb_s_eps_{$s->tmdb_id}_{$sNum}", 86400, function () use ($s, $sNum) {
                    return $this->tmdbProvider->getSeasonEpisodesBilingual($s->tmdb_id, $sNum);
                });

                foreach ($cg['missing_numbers'] as $mNum) {
                    $epMeta = $tmdbEpisodes[$mNum] ?? null;
                    $airDate = $epMeta['air_date'] ?? null;

                    if ($airDate && $airDate > $today) {
                        continue;
                    }

                    $missing[] = [
                        'id' => "ep_{$s->id}_{$sNum}_{$mNum}",
                        'type' => 'episode',
                        'series_id' => $s->id,
                        'series_title' => $s->title,
                        'series_title_ar' => $s->title_ar,
                        'series_poster' => $s->poster_path,
                        'season_number' => $sNum,
                        'episode_number' => $mNum,
                        'episode_code' => sprintf('S%02dE%02d', $sNum, $mNum),
                        'episode_title' => $epMeta['title'] ?? "Episode {$mNum}",
                        'episode_title_ar' => $epMeta['title_ar'] ?? null,
                        'air_date' => $airDate,
                        'overview' => $epMeta['overview'] ?? null,
                        'overview_ar' => $epMeta['overview_ar'] ?? null,
                        'still_path' => $epMeta['still_path'] ?? null,
                        'rating' => $epMeta['rating'] ?? 0,
                        'tmdb_id' => $s->tmdb_id,
                        'imdb_id' => $s->imdb_id,
                    ];
                }
            }

            return $missing;
        });
    }

    /**
     * Get missing entire seasons that have released on TMDB but do not exist in the library.
     */
    public function getMissingSeasonsSummary(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget('scout_missing_seasons');
        }

        return Cache::remember('scout_missing_seasons', self::CACHE_TTL, function () {
            $today = Carbon::today()->format('Y-m-d');
            $missing = [];

            $seriesList = Series::with(['seasons'])
                ->whereNotNull('tmdb_id')
                ->where(function ($q) {
                    $q->where('status', 'like', '%Continuing%')
                        ->orWhere('status', 'like', '%Returning%')
                        ->orWhereNull('status');
                })
                ->limit(40)
                ->get();

            foreach ($seriesList as $series) {
                $tmdbDetails = Cache::remember("scout_tmdb_series_{$series->tmdb_id}", 86400, function () use ($series) {
                    return $this->tmdbProvider->getSeriesDetails($series->tmdb_id);
                });

                if (empty($tmdbDetails) || empty($tmdbDetails['seasons'])) {
                    continue;
                }

                $localSeasonNumbers = $series->seasons
                    ->pluck('season_number')
                    ->map(fn ($n) => (int) $n)
                    ->toArray();

                foreach ($tmdbDetails['seasons'] as $tmdbSeason) {
                    $sNum = (int) ($tmdbSeason['season_number'] ?? 0);
                    $epCount = (int) ($tmdbSeason['episode_count'] ?? 0);
                    $airDate = $tmdbSeason['air_date'] ?? null;

                    if ($sNum <= 0 || $epCount <= 0) {
                        continue;
                    }

                    if (empty($airDate) || $airDate > $today) {
                        continue;
                    }

                    if (! in_array($sNum, $localSeasonNumbers, true)) {
                        $missing[] = [
                            'id' => "season_{$series->id}_{$sNum}",
                            'type' => 'season',
                            'series_id' => $series->id,
                            'series_title' => $series->title,
                            'series_title_ar' => $series->title_ar,
                            'series_poster' => $series->poster_path,
                            'season_number' => $sNum,
                            'season_name' => "Season {$sNum}",
                            'episode_count' => $epCount,
                            'air_date' => $airDate,
                            'poster_path' => ! empty($tmdbSeason['poster_path']) ? "https://image.tmdb.org/t/p/w500{$tmdbSeason['poster_path']}" : $series->poster_path,
                            'tmdb_id' => $series->tmdb_id,
                            'imdb_id' => $series->imdb_id,
                        ];
                    }
                }
            }

            return $missing;
        });
    }

    /**
     * Get missing movies from genuine movie collections (franchises with >= 2 movies in library).
     */
    public function getMissingCollectionMoviesSummary(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget('scout_missing_collections');
        }

        return Cache::remember('scout_missing_collections', self::CACHE_TTL, function () use ($forceRefresh) {
            $grouped = $this->getGroupedCollectionGaps($forceRefresh);
            $flat = [];
            foreach ($grouped as $col) {
                foreach ($col['missing_movies'] as $mm) {
                    $flat[] = $mm;
                }
            }

            return $flat;
        });
    }

    /**
     * Get grouped collections with complete parts breakdown (owned vs missing).
     */
    public function getGroupedCollectionGaps(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget('scout_grouped_collections');
        }

        return Cache::remember('scout_grouped_collections', self::CACHE_TTL, function () {
            $today = Carbon::today()->format('Y-m-d');

            // Genuine collections having 1 or more movies in local library (Media Scout tracks franchise gaps even from a single owned movie)
            $collectionCounts = MediaItem::whereNotNull('collection_id')
                ->where('collection_id', '>', 0)
                ->groupBy('collection_id')
                ->selectRaw('collection_id, count(*) as cnt')
                ->having('cnt', '>=', 1)
                ->pluck('cnt', 'collection_id');

            $collectionIds = $collectionCounts->keys()->toArray();
            $apiKey = AppSetting::get('tmdb_api_key') ?? config('services.tmdb.key') ?? env('TMDB_API_KEY');

            // Parallel batch fetch missing cache in chunks of 25
            $chunks = array_chunk($collectionIds, 25);
            foreach ($chunks as $chunk) {
                $toFetch = [];
                foreach ($chunk as $id) {
                    if (! Cache::has("scout_tmdb_col_{$id}")) {
                        $toFetch[] = $id;
                    }
                }

                if (! empty($toFetch) && ! empty($apiKey)) {
                    $responses = Http::pool(function ($pool) use ($toFetch, $apiKey) {
                        return array_map(function ($id) use ($pool, $apiKey) {
                            return $pool->as("col_{$id}")->timeout(6)->get("https://api.themoviedb.org/3/collection/{$id}", [
                                'api_key' => $apiKey,
                            ]);
                        }, $toFetch);
                    });

                    foreach ($toFetch as $id) {
                        if (isset($responses["col_{$id}"]) && $responses["col_{$id}"]->successful()) {
                            Cache::put("scout_tmdb_col_{$id}", $responses["col_{$id}"]->json(), 86400 * 7);
                        }
                    }
                }
            }

            $groupedCollections = [];

            foreach ($collectionIds as $colId) {
                // Skip short cartoon anthologies (e.g. Tom and Jerry 1940s theatrical shorts)
                if ($colId == 1758656) {
                    continue;
                }

                $colDetails = Cache::get("scout_tmdb_col_{$colId}");
                if (empty($colDetails) || empty($colDetails['parts'])) {
                    continue;
                }

                $localMovies = MediaItem::where('collection_id', $colId)->get();
                $localTmdbIds = $localMovies->pluck('tmdb_id')->filter()->map(fn ($id) => (int) $id)->toArray();
                $localTitles = $localMovies->pluck('title')->map(fn ($t) => strtolower(trim($t)))->toArray();

                $parts = [];
                $missingParts = [];
                $ownedParts = [];

                $sortedParts = collect($colDetails['parts'])->sortBy('release_date')->values();

                foreach ($sortedParts as $index => $part) {
                    $relDate = $part['release_date'] ?? null;
                    if (empty($relDate) || $relDate > $today) {
                        continue;
                    }

                    $pId = (int) $part['id'];
                    $pTitle = strtolower(trim($part['title'] ?? ''));

                    $isOwned = in_array($pId, $localTmdbIds, true) || in_array($pTitle, $localTitles, true);

                    // Library-wide fallback check so NO owned movie on disk is ever falsely reported missing
                    if (! $isOwned) {
                        $globalOwned = MediaItem::where('tmdb_id', $pId)
                            ->orWhere(function ($q) use ($pTitle) {
                                if (strlen($pTitle) > 3) {
                                    $q->whereRaw('LOWER(title) = ?', [$pTitle]);
                                }
                            })->first();

                        if ($globalOwned) {
                            $isOwned = true;
                            // Auto-heal collection metadata in DB
                            if ($globalOwned->collection_id !== $colId) {
                                $globalOwned->update([
                                    'collection_id' => $colId,
                                    'collection_name' => $colDetails['name'] ?? $globalOwned->collection_name,
                                ]);
                            }
                        }
                    }

                    $partNumber = $index + 1;

                    $partItem = [
                        'id' => "movie_col_{$colId}_{$pId}",
                        'type' => 'collection_movie',
                        'collection_id' => $colId,
                        'collection_name' => $colDetails['name'] ?? 'Collection',
                        'movie_title' => $part['title'] ?? '',
                        'original_title' => $part['original_title'] ?? ($part['title'] ?? ''),
                        'part_number' => $partNumber,
                        'release_year' => ! empty($relDate) ? (int) substr($relDate, 0, 4) : null,
                        'release_date' => $relDate,
                        'poster_path' => ! empty($part['poster_path']) ? "https://image.tmdb.org/t/p/w500{$part['poster_path']}" : null,
                        'backdrop_path' => ! empty($part['backdrop_path']) ? "https://image.tmdb.org/t/p/w1280{$part['backdrop_path']}" : null,
                        'overview' => $part['overview'] ?? '',
                        'rating' => round($part['vote_average'] ?? 0, 1),
                        'tmdb_id' => $pId,
                        'imdb_id' => null,
                        'in_library' => $isOwned,
                    ];

                    $parts[] = $partItem;
                    if ($isOwned) {
                        $ownedParts[] = $partItem;
                    } else {
                        $missingParts[] = $partItem;
                    }
                }

                if (! empty($missingParts)) {
                    $colPoster = ! empty($colDetails['poster_path'])
                        ? "https://image.tmdb.org/t/p/w780{$colDetails['poster_path']}"
                        : ($ownedParts[0]['poster_path'] ?? null);
                    $colBackdrop = ! empty($colDetails['backdrop_path'])
                        ? "https://image.tmdb.org/t/p/w1280{$colDetails['backdrop_path']}"
                        : null;

                    $totalCount = count($parts);
                    $ownedCount = count($ownedParts);

                    foreach ($missingParts as &$m) {
                        $m['owned_parts_count'] = $ownedCount;
                        $m['total_parts_count'] = $totalCount;
                        $m['completion_percent'] = $totalCount > 0 ? round(($ownedCount / $totalCount) * 100) : 0;
                        $m['collection_poster'] = $colPoster;
                    }
                    unset($m);

                    $groupedCollections[] = [
                        'collection_id' => $colId,
                        'collection_name' => $colDetails['name'] ?? 'Collection',
                        'poster_path' => $colPoster,
                        'backdrop_path' => $colBackdrop,
                        'owned_count' => $ownedCount,
                        'missing_count' => count($missingParts),
                        'total_count' => $totalCount,
                        'completion_percent' => $totalCount > 0 ? round(($ownedCount / $totalCount) * 100) : 100,
                        'parts' => $parts,
                        'missing_movies' => $missingParts,
                        'owned_movies' => $ownedParts,
                    ];
                }
            }

            usort($groupedCollections, fn ($a, $b) => $b['completion_percent'] <=> $a['completion_percent']);

            return $groupedCollections;
        });
    }

    /**
     * Get grouped series gaps (series that have missing episodes or unacquired seasons).
     */
    public function getGroupedSeriesGaps(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget('scout_grouped_series');
        }

        return Cache::remember('scout_grouped_series', self::CACHE_TTL, function () use ($forceRefresh) {
            $episodes = $this->getMissingEpisodesSummary($forceRefresh);
            $seasons = $this->getMissingSeasonsSummary($forceRefresh);

            $allMissingBySeries = collect($episodes)->groupBy('series_id');
            $seasonsBySeries = collect($seasons)->groupBy('series_id');
            $seriesIds = $allMissingBySeries->keys()->merge($seasonsBySeries->keys())->unique();

            $seriesGaps = [];
            foreach ($seriesIds as $sid) {
                $s = Series::find($sid);
                if (! $s) {
                    continue;
                }

                $localCount = Episode::where('series_id', $s->id)->whereNotNull('file_path')->count();
                $missingEps = $allMissingBySeries->get($sid, collect())->values()->toArray();
                $missingSeas = $seasonsBySeries->get($sid, collect())->values()->toArray();
                $totalEps = $localCount + count($missingEps);
                $percent = $totalEps > 0 ? round(($localCount / $totalEps) * 100) : 100;

                $seriesGaps[] = [
                    'series_id' => $s->id,
                    'series_title' => $s->title,
                    'series_title_ar' => $s->title_ar,
                    'poster_path' => $s->poster_path,
                    'backdrop_path' => $s->backdrop_path,
                    'local_episodes_count' => $localCount,
                    'missing_episodes_count' => count($missingEps),
                    'missing_seasons_count' => count($missingSeas),
                    'total_episodes_count' => $totalEps,
                    'completion_percent' => $percent,
                    'missing_episodes' => $missingEps,
                    'missing_seasons' => $missingSeas,
                ];
            }

            usort($seriesGaps, fn ($a, $b) => $b['completion_percent'] <=> $a['completion_percent']);

            return $seriesGaps;
        });
    }

    /**
     * Get overall metrics and completion percentages.
     */
    public function getMetrics(bool $forceRefresh = false): array
    {
        $episodes = $this->getMissingEpisodesSummary($forceRefresh);
        $seasons = $this->getMissingSeasonsSummary($forceRefresh);
        $groupedCollections = $this->getGroupedCollectionGaps($forceRefresh);
        $missingMoviesCount = 0;
        foreach ($groupedCollections as $col) {
            $missingMoviesCount += $col['missing_count'];
        }

        $totalLocalEpisodes = Episode::whereNotNull('file_path')->count();
        $totalLocalMovies = MediaItem::count();

        $totalTrackedEpisodes = $totalLocalEpisodes + count($episodes);
        $seriesCompletionRate = $totalTrackedEpisodes > 0
            ? round(($totalLocalEpisodes / $totalTrackedEpisodes) * 100, 1)
            : 100;

        $collectionsCompletionRate = 100;
        if (! empty($groupedCollections)) {
            $rates = array_column($groupedCollections, 'completion_percent');
            $collectionsCompletionRate = round(array_sum($rates) / count($rates), 1);
        }

        return [
            'missing_episodes_count' => count($episodes),
            'missing_seasons_count' => count($seasons),
            'missing_movies_count' => $missingMoviesCount,
            'incomplete_franchises_count' => count($groupedCollections),
            'incomplete_series_count' => count($this->getGroupedSeriesGaps($forceRefresh)),
            'total_gaps_count' => count($episodes) + count($seasons) + $missingMoviesCount,
            'series_completion_rate' => $seriesCompletionRate,
            'collections_completion_rate' => $collectionsCompletionRate,
            'total_library_series' => Series::count(),
            'total_library_movies' => $totalLocalMovies,
            'last_scanned_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Clear all scout cache keys.
     */
    public function clearCache(): void
    {
        Cache::forget('scout_missing_episodes');
        Cache::forget('scout_missing_seasons');
        Cache::forget('scout_missing_collections');
        Cache::forget('scout_grouped_collections');
        Cache::forget('scout_grouped_series');
    }
}
