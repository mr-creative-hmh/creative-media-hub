<?php

namespace App\Services\Scout;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Series;
use App\Services\Metadata\TmdbProvider;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LibraryGapService
{
    protected const CACHE_TTL = 86400; // 24 hours

    protected ?array $memoizedEpisodes = null;

    protected ?array $memoizedSeasons = null;

    protected ?array $memoizedCollections = null;

    protected ?array $memoizedGroupedCollections = null;

    protected ?array $memoizedSeriesGaps = null;

    protected ?array $memoizedMetrics = null;

    public function __construct(
        protected TmdbProvider $tmdbProvider
    ) {}

    /**
     * Get all missing released episodes from existing seasons in the library.
     */
    public function getMissingEpisodesSummary(bool $forceRefresh = false): array
    {
        if (! $forceRefresh && $this->memoizedEpisodes !== null) {
            return $this->memoizedEpisodes;
        }

        if ($forceRefresh) {
            Cache::forget('scout_missing_episodes');
        }

        $result = Cache::remember('scout_missing_episodes', self::CACHE_TTL, function () {
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

        $this->memoizedEpisodes = $result;

        return $result;
    }

    /**
     * Get missing entire seasons that have released on TMDB but do not exist in the library.
     */
    public function getMissingSeasonsSummary(bool $forceRefresh = false): array
    {
        if (! $forceRefresh && $this->memoizedSeasons !== null) {
            return $this->memoizedSeasons;
        }

        if ($forceRefresh) {
            Cache::forget('scout_missing_seasons');
        }

        $result = Cache::remember('scout_missing_seasons', self::CACHE_TTL, function () {
            $today = Carbon::today()->format('Y-m-d');
            $missing = [];

            $seriesList = Series::with(['seasons'])
                ->whereNotNull('tmdb_id')
                ->where(function ($q) {
                    $q->where('status', 'like', '%Continuing%')
                        ->orWhere('status', 'like', '%Returning%')
                        ->orWhereNull('status');
                })
                ->limit(25)
                ->get();

            foreach ($seriesList as $series) {
                $tmdbDetails = Cache::remember("scout_tmdb_series_{$series->tmdb_id}", 86400 * 7, function () use ($series) {
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

        $this->memoizedSeasons = $result;

        return $result;
    }

    /**
     * Get missing movies from genuine movie collections (franchises with >= 2 movies in library).
     */
    public function getMissingCollectionMoviesSummary(bool $forceRefresh = false): array
    {
        if (! $forceRefresh && $this->memoizedCollections !== null) {
            return $this->memoizedCollections;
        }

        if ($forceRefresh) {
            Cache::forget('scout_missing_collections');
        }

        $result = Cache::remember('scout_missing_collections', self::CACHE_TTL, function () use ($forceRefresh) {
            $grouped = $this->getGroupedCollectionGaps($forceRefresh);
            $flat = [];
            foreach ($grouped as $col) {
                foreach ($col['missing_movies'] as $mm) {
                    $flat[] = $mm;
                }
            }

            return $flat;
        });

        $this->memoizedCollections = $result;

        return $result;
    }

    /**
     * Get grouped collections with complete parts breakdown (owned vs missing).
     */
    public function getGroupedCollectionGaps(bool $forceRefresh = false): array
    {
        if (! $forceRefresh && $this->memoizedGroupedCollections !== null) {
            return $this->memoizedGroupedCollections;
        }

        if ($forceRefresh) {
            Cache::forget('scout_grouped_collections');
            Cache::forget('scout_missing_collections');
        }

        $result = Cache::remember('scout_grouped_collections', self::CACHE_TTL, function () {
            $today = Carbon::today()->format('Y-m-d');

            // Genuine collections having 1 or more movies in local library (Media Scout tracks franchise gaps even from a single owned movie)
            $allLibraryMovies = MediaItem::all();

            $collectionCounts = $allLibraryMovies
                ->filter(fn ($m) => ! empty($m->collection_id) && (int) $m->collection_id > 0)
                ->groupBy(fn ($m) => (int) $m->collection_id)
                ->map(fn ($group) => $group->count());

            $collectionIds = $collectionCounts->keys()->map(fn ($id) => (int) $id)->values()->all();

            // Also check for movies with collection_name where collection_id is null/0 and try to inherit from siblings
            $namedWithIdByCollectionName = $allLibraryMovies
                ->filter(fn ($m) => ! empty($m->collection_name) && ! empty($m->collection_id) && (int) $m->collection_id > 0)
                ->keyBy('collection_name');

            $namedWithoutId = $allLibraryMovies
                ->filter(fn ($m) => ! empty($m->collection_name) && (empty($m->collection_id) || (int) $m->collection_id === 0));

            foreach ($namedWithoutId as $item) {
                $sibling = $namedWithIdByCollectionName->get($item->collection_name);
                if ($sibling && $sibling->collection_id) {
                    $item->update([
                        'collection_id' => $sibling->collection_id,
                        'collection_id_source' => $sibling->collection_id_source ?: 'tmdb',
                    ]);
                    if (! in_array((int) $sibling->collection_id, $collectionIds, true)) {
                        $collectionIds[] = (int) $sibling->collection_id;
                    }
                }
            }

            $apiKey = AppSetting::get('tmdb_api_key') ?? config('services.tmdb.key');

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
                    try {
                        $responses = Http::pool(function ($pool) use ($toFetch, $apiKey) {
                            return array_map(function ($id) use ($pool, $apiKey) {
                                return $pool->as("col_{$id}")->timeout(2.5)->get("https://api.themoviedb.org/3/collection/{$id}", [
                                    'api_key' => $apiKey,
                                ]);
                            }, $toFetch);
                        });

                        foreach ($toFetch as $id) {
                            $response = $responses["col_{$id}"] ?? null;
                            if ($response instanceof Response && $response->successful()) {
                                Cache::put("scout_tmdb_col_{$id}", $response->json(), 86400 * 7);
                            }
                        }
                    } catch (\Throwable $e) {
                        // Fail silently and continue using local database info
                    }
                }
            }

            $groupedCollections = [];
            $moviesByCollectionId = $allLibraryMovies->whereNotNull('collection_id')->groupBy(fn ($m) => (int) $m->collection_id);
            $moviesByTmdbId = $allLibraryMovies->whereNotNull('tmdb_id')->keyBy(fn ($m) => (int) $m->tmdb_id);
            $moviesByLowerTitle = $allLibraryMovies
                ->filter(fn ($m) => strlen(trim((string) $m->title)) > 3)
                ->keyBy(fn ($m) => strtolower(trim((string) $m->title)));

            foreach ($collectionIds as $colId) {
                // Skip short cartoon anthologies (e.g. Tom and Jerry 1940s theatrical shorts)
                if ($colId == 1758656) {
                    continue;
                }

                $colDetails = Cache::get("scout_tmdb_col_{$colId}");
                if (empty($colDetails) || empty($colDetails['parts'])) {
                    continue;
                }

                $localMovies = $moviesByCollectionId->get((int) $colId, collect());
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

                    // Library-wide fallback check in memory so NO owned movie on disk is ever falsely reported missing
                    if (! $isOwned) {
                        $globalOwned = ($pId > 0 ? $moviesByTmdbId->get($pId) : null)
                            ?? (strlen($pTitle) > 3 ? $moviesByLowerTitle->get($pTitle) : null);

                        if ($globalOwned) {
                            $isOwned = true;
                            // Auto-heal collection metadata in DB
                            if ($globalOwned->collection_id !== $colId) {
                                $globalOwned->update([
                                    'collection_id' => $colId,
                                    'collection_name' => $colDetails['name'] ?? $globalOwned->collection_name,
                                ]);
                                $localMovies->push($globalOwned);
                            }
                        }
                    }

                    $partNumber = $index + 1;

                    $matchedLocalMovie = null;
                    if ($isOwned) {
                        $matchedLocalMovie = $localMovies->first(function ($lm) use ($pId, $pTitle) {
                            return ($pId && (int) $lm->tmdb_id === $pId)
                                || (strtolower(trim($lm->title)) === $pTitle);
                        });
                    }

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
                        'poster_path' => ! empty($part['poster_path'])
                            ? "https://image.tmdb.org/t/p/w500{$part['poster_path']}"
                            : ($matchedLocalMovie?->poster_path ?? null),
                        'backdrop_path' => ! empty($part['backdrop_path'])
                            ? "https://image.tmdb.org/t/p/w1280{$part['backdrop_path']}"
                            : ($matchedLocalMovie?->backdrop_path ?? null),
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

                $ownedCount = count($ownedParts);
                $totalCount = count($parts);

                // STRICT: Only include if there are missing parts AND at least 1 movie is actually owned in library!
                if (! empty($missingParts) && $ownedCount > 0) {
                    $localCollectionPoster = $localMovies->pluck('collection_poster')->filter()->first()
                        ?? $localMovies->pluck('poster_path')->filter()->first();
                    $localCollectionBackdrop = $localMovies->pluck('backdrop_path')->filter()->first();

                    $colPoster = ! empty($colDetails['poster_path'])
                        ? "https://image.tmdb.org/t/p/w780{$colDetails['poster_path']}"
                        : ($localCollectionPoster ?: ($ownedParts[0]['poster_path'] ?? null));

                    $colBackdrop = ! empty($colDetails['backdrop_path'])
                        ? "https://image.tmdb.org/t/p/w1280{$colDetails['backdrop_path']}"
                        : ($localCollectionBackdrop ?? null);

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

        $this->memoizedGroupedCollections = $result;

        return $result;
    }

    /**
     * Get grouped series gaps (series that have missing episodes or unacquired seasons).
     */
    public function getGroupedSeriesGaps(bool $forceRefresh = false): array
    {
        if (! $forceRefresh && $this->memoizedSeriesGaps !== null) {
            return $this->memoizedSeriesGaps;
        }

        if ($forceRefresh) {
            Cache::forget('scout_grouped_series');
        }

        $result = Cache::remember('scout_grouped_series', self::CACHE_TTL, function () use ($forceRefresh) {
            $episodes = $this->getMissingEpisodesSummary($forceRefresh);
            $seasons = $this->getMissingSeasonsSummary($forceRefresh);

            $allMissingBySeries = collect($episodes)->groupBy('series_id');
            $seasonsBySeries = collect($seasons)->groupBy('series_id');
            $seriesIds = $allMissingBySeries->keys()->merge($seasonsBySeries->keys())->unique()->values()->all();

            $seriesById = ! empty($seriesIds)
                ? Series::whereIn('id', $seriesIds)->get()->keyBy('id')
                : collect();

            $localCountsBySeries = ! empty($seriesIds)
                ? Episode::whereIn('series_id', $seriesIds)
                    ->whereNotNull('file_path')
                    ->selectRaw('series_id, count(*) as cnt')
                    ->groupBy('series_id')
                    ->pluck('cnt', 'series_id')
                : collect();

            $seriesGaps = [];
            foreach ($seriesIds as $sid) {
                $s = $seriesById->get($sid);
                if (! $s) {
                    continue;
                }

                $localCount = (int) ($localCountsBySeries->get($s->id) ?? 0);
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

        $this->memoizedSeriesGaps = $result;

        return $result;
    }

    /**
     * Get overall metrics and completion percentages.
     */
    public function getMetrics(bool $forceRefresh = false): array
    {
        if (! $forceRefresh && $this->memoizedMetrics !== null) {
            return $this->memoizedMetrics;
        }

        $episodes = $this->getMissingEpisodesSummary($forceRefresh);
        $seasons = $this->getMissingSeasonsSummary($forceRefresh);
        $groupedCollections = $this->getGroupedCollectionGaps($forceRefresh);
        $seriesGaps = $this->getGroupedSeriesGaps($forceRefresh);

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

        $metrics = [
            'missing_episodes_count' => count($episodes),
            'missing_seasons_count' => count($seasons),
            'missing_movies_count' => $missingMoviesCount,
            'incomplete_franchises_count' => count($groupedCollections),
            'incomplete_series_count' => count($seriesGaps),
            'total_gaps_count' => count($episodes) + count($seasons) + $missingMoviesCount,
            'series_completion_rate' => $seriesCompletionRate,
            'collections_completion_rate' => $collectionsCompletionRate,
            'total_library_series' => Series::count(),
            'total_library_movies' => $totalLocalMovies,
            'last_scanned_at' => Carbon::now()->toIso8601String(),
        ];

        $this->memoizedMetrics = $metrics;

        return $metrics;
    }

    /**
     * Clear all scout cache keys.
     */
    public function clearCache(): void
    {
        $this->memoizedEpisodes = null;
        $this->memoizedSeasons = null;
        $this->memoizedCollections = null;
        $this->memoizedGroupedCollections = null;
        $this->memoizedSeriesGaps = null;
        $this->memoizedMetrics = null;

        Cache::forget('scout_missing_episodes');
        Cache::forget('scout_missing_seasons');
        Cache::forget('scout_missing_collections');
        Cache::forget('scout_grouped_collections');
        Cache::forget('scout_grouped_series');
    }
}
