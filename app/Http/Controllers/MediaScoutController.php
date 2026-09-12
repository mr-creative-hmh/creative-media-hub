<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\DownloadItem;
use App\Services\Downloader\DownloadManagerService;
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
        protected DownloadManagerService $downloadManager
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
                    ($item['series_title'] ?? '') . ' ' .
                    ($item['series_title_ar'] ?? '') . ' ' .
                    ($item['episode_title'] ?? '') . ' ' .
                    ($item['episode_code'] ?? '') . ' ' .
                    ($item['collection_name'] ?? '') . ' ' .
                    ($item['movie_title'] ?? '')
                );
                return str_contains($searchable, $query);
            }));

            $seriesGaps = array_values(array_filter($seriesGaps, function ($s) use ($query) {
                $searchable = strtolower(($s['series_title'] ?? '') . ' ' . ($s['series_title_ar'] ?? ''));
                foreach ($s['missing_episodes'] as $ep) {
                    $searchable .= ' ' . strtolower(($ep['episode_title'] ?? '') . ' ' . ($ep['episode_code'] ?? ''));
                }
                return str_contains($searchable, $query);
            }));

            $collectionGaps = array_values(array_filter($collectionGaps, function ($c) use ($query) {
                $searchable = strtolower($c['collection_name'] ?? '');
                foreach ($c['parts'] as $part) {
                    $searchable .= ' ' . strtolower($part['movie_title'] ?? '');
                }
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
     * API: Search torrent suggestions for a specific gap.
     */
    public function getTorrents(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:movie,collection_movie,episode,season',
            'title' => 'required|string',
        ]);

        $type = $request->input('type');
        $title = $request->input('title');
        $year = $request->filled('year') ? (int) $request->input('year') : null;
        $imdbId = $request->input('imdb_id');
        $tmdbId = $request->filled('tmdb_id') ? (int) $request->input('tmdb_id') : null;
        $season = $request->filled('season') ? (int) $request->input('season') : 1;
        $episode = $request->filled('episode') ? (int) $request->input('episode') : 1;

        $torrents = [];

        if ($type === 'episode') {
            $torrents = $this->torrentService->searchEpisodeTorrents($title, $season, $episode, $imdbId, $tmdbId);
        } elseif ($type === 'season') {
            $torrents = $this->torrentService->searchSeasonTorrents($title, $season, $imdbId, $tmdbId);
        } else {
            $torrents = $this->torrentService->searchMovieTorrents($title, $year, $imdbId, $tmdbId);
        }

        return response()->json([
            'torrents' => $torrents,
            'count' => count($torrents),
        ]);
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
            $res = $this->acquisitionService->organizeAndScanFile($filePath, $mediaType, $metadata);
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
                    $res = $this->acquisitionService->organizeAndScanFile($file->getPathname(), $mediaType, $metadata);
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
