<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Series;
use App\Services\Downloader\DownloadManagerService;
use App\Services\Metadata\TmdbProvider;
use App\Services\Organizer\SceneNameParserService;
use App\Services\Scout\LibraryAcquisitionService;
use App\Services\Scout\LibraryGapService;
use App\Services\Scout\TorrentDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class MediaScoutController extends Controller
{
    public function __construct(
        protected LibraryGapService $gapService,
        protected TorrentDiscoveryService $torrentService,
        protected LibraryAcquisitionService $acquisitionService,
        protected DownloadManagerService $downloadManager,
        protected SceneNameParserService $parserService,
        protected TmdbProvider $tmdb
    ) {}

    /**
     * Display the Media Scout Hub dashboard.
     */
    public function index(): Response
    {
        $metrics = $this->gapService->getMetrics(false);
        $seriesGaps = $this->gapService->getGroupedSeriesGaps(false);
        $collectionGaps = $this->gapService->getGroupedCollectionGaps(false);

        return Inertia::render('Scout/Index', [
            'initialMetrics' => $metrics,
            'initialSeriesGaps' => $seriesGaps,
            'initialCollectionGaps' => $collectionGaps,
            'initialEpisodes' => $this->gapService->getMissingEpisodesSummary(false),
            'initialSeasons' => $this->gapService->getMissingSeasonsSummary(false),
            'initialMovies' => $this->gapService->getMissingCollectionMoviesSummary(false),
            'initialTrending' => $this->enrichWithLibraryStatus($this->tmdb->getTrendingMedia('all', 'week')),
        ]);
    }

    /**
     * API: Get categorized gap list with filtering, search, pagination, and grouped data.
     */
    public function getGaps(Request $request): JsonResponse
    {
        $type = $request->query('type', 'all');
        $query = strtolower(trim($request->query('query', '')));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 24)));

        $seriesGaps = $this->gapService->getGroupedSeriesGaps();
        $collectionGaps = $this->gapService->getGroupedCollectionGaps();
        $episodes = $this->gapService->getMissingEpisodesSummary();
        $seasons = $this->gapService->getMissingSeasonsSummary();
        $collections = $this->gapService->getMissingCollectionMoviesSummary();

        $items = [];
        if ($type === 'episodes') {
            $items = $episodes;
        } elseif ($type === 'seasons') {
            $items = $seasons;
        } elseif ($type === 'collections') {
            $items = $collections;
        } else {
            $items = array_merge($episodes, $seasons, $collections);
        }

        // Apply search filter if provided
        if (! empty($query)) {
            $items = array_values(array_filter($items, function ($item) use ($query) {
                $searchable = strtolower(
                    ($item['series_title'] ?? '').' '.
                    ($item['series_title_ar'] ?? '').' '.
                    ($item['episode_title'] ?? '').' '.
                    ($item['episode_code'] ?? '').' '.
                    ($item['collection_name'] ?? '').' '.
                    ($item['movie_title'] ?? '')
                );

                return str_contains($searchable, $query);
            }));

            $seriesGaps = array_values(array_filter($seriesGaps, function ($s) use ($query) {
                $searchable = strtolower(($s['series_title'] ?? '').' '.($s['series_title_ar'] ?? ''));
                foreach ($s['missing_episodes'] as $ep) {
                    $searchable .= ' '.strtolower(($ep['episode_title'] ?? '').' '.($ep['episode_code'] ?? ''));
                }

                return str_contains($searchable, $query);
            }));

            $collectionGaps = array_values(array_filter($collectionGaps, function ($c) use ($query) {
                $searchable = strtolower($c['collection_name'] ?? '');
                foreach ($c['parts'] as $part) {
                    $searchable .= ' '.strtolower(($part['movie_title'] ?? '').' '.($part['original_title'] ?? ''));
                }

                return str_contains($searchable, $query);
            }));

            $collections = array_values(array_filter($collections, function ($item) use ($query) {
                $searchable = strtolower(
                    ($item['collection_name'] ?? '').' '.
                    ($item['movie_title'] ?? '').' '.
                    ($item['original_title'] ?? '')
                );

                return str_contains($searchable, $query);
            }));

            $episodes = array_values(array_filter($episodes, function ($item) use ($query) {
                $searchable = strtolower(
                    ($item['series_title'] ?? '').' '.
                    ($item['series_title_ar'] ?? '').' '.
                    ($item['episode_title'] ?? '').' '.
                    ($item['episode_code'] ?? '')
                );

                return str_contains($searchable, $query);
            }));

            $seasons = array_values(array_filter($seasons, function ($item) use ($query) {
                $searchable = strtolower(
                    ($item['series_title'] ?? '').' '.
                    ($item['series_title_ar'] ?? '').' '.
                    ($item['season_title'] ?? '')
                );

                return str_contains($searchable, $query);
            }));
        }

        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $sliced = array_slice($items, $offset, $perPage);

        return response()->json([
            'data' => $sliced,
            'total' => $total,
            'current_page' => $page,
            'last_page' => (int) ceil($total / max(1, $perPage)),
            'per_page' => $perPage,
            'metrics' => $this->gapService->getMetrics(),
            'series_gaps' => $seriesGaps,
            'collection_gaps' => $collectionGaps,
            'episodes' => $episodes,
            'seasons' => $seasons,
            'collections' => $collections,
        ]);
    }

    /**
     * API: Force refresh library gap analysis cache.
     */
    public function refresh(): JsonResponse
    {
        $this->gapService->clearCache();
        CollectionController::clearCache();
        $metrics = $this->gapService->getMetrics(true);
        $seriesGaps = $this->gapService->getGroupedSeriesGaps(true);
        $collectionGaps = $this->gapService->getGroupedCollectionGaps(true);

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
            'series_gaps' => $seriesGaps,
            'collection_gaps' => $collectionGaps,
            'episodes' => $this->gapService->getMissingEpisodesSummary(true),
            'seasons' => $this->gapService->getMissingSeasonsSummary(true),
            'collections' => $this->gapService->getMissingCollectionMoviesSummary(true),
        ]);
    }

    /**
     * API: Search torrent suggestions for a specific gap or new media item.
     */
    public function getTorrents(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:movie,collection_movie,episode,season,series,season_pack',
            'title' => 'required|string',
        ]);

        $type = $request->input('type');
        $title = $request->input('title');
        $year = $request->filled('year') ? (int) $request->input('year') : ($request->filled('release_year') ? (int) $request->input('release_year') : null);
        $imdbId = $request->input('imdb_id');
        $tmdbId = $request->filled('tmdb_id') ? (int) $request->input('tmdb_id') : null;
        $season = $request->filled('season') ? (int) $request->input('season') : 1;
        $episode = $request->filled('episode') ? (int) $request->input('episode') : 1;
        $isAnimated = $request->boolean('is_animated', false);

        $torrents = [];

        if ($type === 'episode') {
            $torrents = $this->torrentService->searchEpisodeTorrents($title, $season, $episode, $imdbId, $tmdbId, $year, $isAnimated);
        } elseif ($type === 'season' || $type === 'season_pack') {
            $torrents = $this->torrentService->searchSeasonTorrents($title, $season, $imdbId, $tmdbId, $year, $isAnimated);
        } elseif ($type === 'series') {
            if ($request->filled('episode')) {
                $torrents = $this->torrentService->searchEpisodeTorrents($title, $season, $episode, $imdbId, $tmdbId, $year, $isAnimated);
            } else {
                $torrents = $this->torrentService->searchSeasonTorrents($title, $season, $imdbId, $tmdbId, $year, $isAnimated);
            }
        } else {
            $torrents = $this->torrentService->searchMovieTorrents($title, $year, $imdbId, $tmdbId, $isAnimated);
        }

        return response()->json([
            'torrents' => $torrents,
            'count' => count($torrents),
        ]);
    }

    /**
     * API: Discover & live search new media across TMDb with library status badges.
     */
    public function discoverSearch(Request $request): JsonResponse
    {
        $query = trim($request->input('query', ''));
        $type = $request->input('type', 'all'); // 'all', 'movie', 'tv'

        if (empty($query)) {
            $trending = $this->tmdb->getTrendingMedia($type === 'tv' ? 'tv' : ($type === 'movie' ? 'movie' : 'all'), 'week');

            return response()->json([
                'results' => $this->enrichWithLibraryStatus($trending),
            ]);
        }

        $results = [];
        if ($type === 'movie' || $type === 'all') {
            $movieResults = $this->tmdb->searchMovie($query);
            $results = array_merge($results, $movieResults);
        }

        if ($type === 'tv' || $type === 'all') {
            $seriesResults = $this->tmdb->searchSeries($query);
            $results = array_merge($results, $seriesResults);
        }

        return response()->json([
            'results' => $this->enrichWithLibraryStatus($results),
        ]);
    }

    /**
     * API: Get detailed series seasons & episodes breakdown for discover inspector.
     */
    public function discoverSeriesDetails(Request $request): JsonResponse
    {
        $request->validate(['tmdb_id' => 'required|integer']);
        $tmdbId = (int) $request->input('tmdb_id');

        $details = $this->tmdb->getSeriesDetails($tmdbId);
        if (empty($details)) {
            return response()->json(['error' => 'Series not found on TMDb'], 404);
        }

        $localSeries = Series::with(['seasons'])->where('tmdb_id', $tmdbId)->first();
        $localOwnedSeasonNumbers = $localSeries ? $localSeries->seasons->pluck('season_number')->map(fn ($n) => (int) $n)->toArray() : [];

        $seasons = [];
        foreach ($details['seasons'] ?? [] as $s) {
            $sNum = (int) ($s['season_number'] ?? 0);
            if ($sNum <= 0) {
                continue;
            }

            $isSeasonOwned = in_array($sNum, $localOwnedSeasonNumbers, true);
            $seasons[] = [
                'season_number' => $sNum,
                'name' => $s['name'] ?? "Season {$sNum}",
                'episode_count' => $s['episode_count'] ?? 0,
                'air_date' => $s['air_date'] ?? null,
                'poster_path' => ! empty($s['poster_path']) ? "https://image.tmdb.org/t/p/w500{$s['poster_path']}" : null,
                'in_library' => $isSeasonOwned,
            ];
        }

        return response()->json([
            'series' => [
                'tmdb_id' => $tmdbId,
                'title' => $details['name'] ?? ($details['title'] ?? ''),
                'overview' => $details['overview'] ?? '',
                'poster_path' => ! empty($details['poster_path']) ? "https://image.tmdb.org/t/p/w500{$details['poster_path']}" : null,
                'backdrop_path' => ! empty($details['backdrop_path']) ? "https://image.tmdb.org/t/p/w1280{$details['backdrop_path']}" : null,
                'first_air_date' => $details['first_air_date'] ?? null,
                'in_library' => (bool) $localSeries,
                'local_id' => $localSeries?->id,
            ],
            'seasons' => $seasons,
        ]);
    }

    /**
     * Cross-reference TMDb media items with the local library to mark owned status.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function enrichWithLibraryStatus(array $items): array
    {
        return array_map(function ($item) {
            $mediaType = $item['media_type'] ?? (isset($item['first_air_date']) ? 'series' : 'movie');

            // Normalize release_year and year for uniform access across views
            $year = $item['release_year'] ?? ($item['year'] ?? null);
            $item['release_year'] = $year;
            $item['year'] = $year;

            if ($mediaType === 'movie') {
                $match = $this->findMatchingMovie($item);

                $item['in_library'] = (bool) $match;
                $item['local_id'] = $match?->id;
                $item['media_type'] = 'movie';
            } else {
                $match = $this->findMatchingSeries($item);

                $item['in_library'] = (bool) $match;
                $item['local_id'] = $match?->id;
                $item['media_type'] = 'series';
                $item['owned_episodes_count'] = $match ? Episode::where('series_id', $match->id)->whereNotNull('file_path')->count() : 0;
            }

            return $item;
        }, $items);
    }

    /**
     * Intelligently find matching movie in the local library using TMDB ID, IMDb ID,
     * exact/localized title matching, and normalized alphanumeric comparison.
     */
    protected function findMatchingMovie(array $item): ?MediaItem
    {
        $tmdbId = ! empty($item['tmdb_id']) ? (string) $item['tmdb_id'] : (! empty($item['id']) ? (string) $item['id'] : null);
        if ($tmdbId) {
            $match = MediaItem::where('tmdb_id', $tmdbId)->first();
            if ($match) {
                return $match;
            }
        }

        if (! empty($item['imdb_id'])) {
            $match = MediaItem::where('imdb_id', (string) $item['imdb_id'])->first();
            if ($match) {
                return $match;
            }
        }

        $title = trim($item['title'] ?? '');
        $originalTitle = trim($item['original_title'] ?? '');
        $year = ! empty($item['release_year']) ? (int) $item['release_year'] : (! empty($item['year']) ? (int) $item['year'] : null);

        if (! empty($title) || ! empty($originalTitle)) {
            $candidates = MediaItem::query()
                ->where(function ($q) use ($title, $originalTitle) {
                    if (! empty($title)) {
                        $q->where('title', 'like', $title)
                            ->orWhere('original_title', 'like', $title)
                            ->orWhere('title_ar', 'like', $title);
                    }
                    if (! empty($originalTitle) && $originalTitle !== $title) {
                        $q->orWhere('title', 'like', $originalTitle)
                            ->orWhere('original_title', 'like', $originalTitle);
                    }
                })
                ->get();

            if ($candidates->isNotEmpty()) {
                if ($year) {
                    $yearMatch = $candidates->first(function ($c) use ($year) {
                        return $c->release_year && abs((int) $c->release_year - $year) <= 1;
                    });
                    if ($yearMatch) {
                        return $yearMatch;
                    }
                    // Year mismatch: DO NOT match across animated originals vs live-action remakes
                } elseif ($candidates->count() === 1) {
                    return $candidates->first();
                }
            }

            // Normalized alphanumeric title comparison with strict year matching
            $cleanTitle = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($title));
            if (strlen($cleanTitle) >= 3) {
                $yearQuery = MediaItem::query();
                if ($year) {
                    $yearQuery->whereBetween('release_year', [$year - 1, $year + 1]);
                }
                $potentialMovies = $yearQuery->get(['id', 'title', 'original_title', 'release_year']);
                foreach ($potentialMovies as $m) {
                    if ($year && abs((int) $m->release_year - $year) > 1) {
                        continue;
                    }
                    $mClean = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($m->title));
                    $mCleanOrig = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($m->original_title ?? ''));
                    if ($mClean === $cleanTitle || ($mCleanOrig && $mCleanOrig === $cleanTitle)) {
                        return $m;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Intelligently find matching series in the local library using TMDB ID, IMDb ID,
     * exact/localized title matching, and normalized alphanumeric comparison.
     */
    protected function findMatchingSeries(array $item): ?Series
    {
        $tmdbId = ! empty($item['tmdb_id']) ? (string) $item['tmdb_id'] : (! empty($item['id']) ? (string) $item['id'] : null);
        if ($tmdbId) {
            $match = Series::where('tmdb_id', $tmdbId)->first();
            if ($match) {
                return $match;
            }
        }

        if (! empty($item['imdb_id'])) {
            $match = Series::where('imdb_id', (string) $item['imdb_id'])->first();
            if ($match) {
                return $match;
            }
        }

        $title = trim($item['title'] ?? '');
        $originalTitle = trim($item['original_title'] ?? '');
        $year = ! empty($item['release_year']) ? (int) $item['release_year'] : (! empty($item['year']) ? (int) $item['year'] : null);

        if (! empty($title) || ! empty($originalTitle)) {
            $candidates = Series::query()
                ->where(function ($q) use ($title, $originalTitle) {
                    if (! empty($title)) {
                        $q->where('title', 'like', $title)
                            ->orWhere('original_title', 'like', $title)
                            ->orWhere('title_ar', 'like', $title);
                    }
                    if (! empty($originalTitle) && $originalTitle !== $title) {
                        $q->orWhere('title', 'like', $originalTitle)
                            ->orWhere('original_title', 'like', $originalTitle);
                    }
                })
                ->get();

            if ($candidates->isNotEmpty()) {
                if ($year) {
                    $yearMatch = $candidates->first(function ($c) use ($year) {
                        return $c->release_year && abs((int) $c->release_year - $year) <= 1;
                    });
                    if ($yearMatch) {
                        return $yearMatch;
                    }
                    // Year mismatch: DO NOT match across animated originals vs live-action remakes
                } elseif ($candidates->count() === 1) {
                    return $candidates->first();
                }
            }

            $cleanTitle = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($title));
            if (strlen($cleanTitle) >= 3) {
                $yearQuery = Series::query();
                if ($year) {
                    $yearQuery->whereBetween('release_year', [$year - 1, $year + 1]);
                }
                $potentialSeries = $yearQuery->get(['id', 'title', 'original_title', 'release_year']);
                foreach ($potentialSeries as $s) {
                    if ($year && abs((int) $s->release_year - $year) > 1) {
                        continue;
                    }
                    $sClean = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($s->title));
                    $sCleanOrig = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($s->original_title ?? ''));
                    if ($sClean === $cleanTitle || ($sCleanOrig && $sCleanOrig === $cleanTitle)) {
                        return $s;
                    }
                }
            }
        }

        return null;
    }

    /**
     * API: 1-Click Download via Aria2 with auto-organize flag.
     */
    public function download(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string',
            'magnet_url' => 'required|string',
            'media_type' => 'required|in:movie,series',
        ]);

        $title = $request->input('title');
        $magnetUrl = $request->input('magnet_url');
        $mediaType = $request->input('media_type');
        $autoOrganize = $request->boolean('auto_organize', true);
        $metadata = $request->input('metadata', []);
        $infoHash = $request->input('info_hash');

        $metadata['auto_organize'] = $autoOrganize;

        // Create download item in DownloadManager
        $downloadItem = $this->downloadManager->createDownload(
            $title,
            $mediaType,
            $magnetUrl,
            null,
            'torrent',
            null,
            $metadata,
            null,
            $infoHash
        );

        return response()->json([
            'success' => true,
            'download_id' => $downloadItem->id,
            'title' => $downloadItem->title,
            'message' => 'Download queued successfully in Media Hub',
        ]);
    }

    /**
     * API: Fast "Organize and Add to Library (Scan)" action for downloaded files.
     */
    public function organizeAndScan(Request $request): JsonResponse
    {
        $filePath = $request->input('file_path');
        $mediaType = $request->input('media_type', 'movie');
        $metadata = $request->input('metadata', []);

        if (! empty($filePath)) {
            $parsed = $this->parserService->parse($filePath);
            $detectedType = $request->input('media_type') ?: ($parsed['type'] === 'series' ? 'series' : 'movie');
            $res = $this->acquisitionService->organizeAndScanFile($filePath, $detectedType, $metadata);

            return response()->json($res);
        }

        // Batch organize all unorganized downloads in download directory
        $downloadSettings = $this->downloadManager->getSettings();
        $foldersToCheck = array_unique([
            $downloadSettings['movies_download_path'] ?? null,
            $downloadSettings['series_download_path'] ?? null,
            $downloadSettings['default_download_path'] ?? null,
        ]);

        $processed = [];
        foreach ($foldersToCheck as $folder) {
            if (! $folder || ! File::isDirectory($folder)) {
                continue;
            }

            $files = File::files($folder);
            foreach ($files as $file) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, ['mp4', 'mkv', 'avi', 'mov', 'webm'])) {
                    $parsed = $this->parserService->parse($file->getPathname());
                    $detectedType = ($parsed['type'] === 'series') ? 'series' : 'movie';
                    $res = $this->acquisitionService->organizeAndScanFile($file->getPathname(), $detectedType, $metadata);
                    if ($res['success']) {
                        $processed[] = $res;
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'processed_count' => count($processed),
            'items' => $processed,
        ]);
    }
}
