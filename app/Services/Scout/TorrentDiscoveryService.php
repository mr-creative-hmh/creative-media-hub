<?php

namespace App\Services\Scout;

use App\Models\AppSetting;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;

class TorrentDiscoveryService
{
    protected const TORRENTIO_URL = 'https://torrentio.strem.fun';

    protected const EZTV_URL = 'https://eztvx.to/api/get-torrents';

    protected const YTS_URL = 'https://yts.pw/api/v2/list_movies.json';

    protected const YTS_BACKUP_URL = 'https://movies-api.accel.li/api/v2/list_movies.json';

    protected const APIBAY_URL = 'https://apibay.org/q.php';

    protected array $trackers = [
        'udp://tracker.opentrackr.org:1337/announce',
        'udp://open.demonii.com:1337/announce',
        'udp://open.stealth.si:80/announce',
        'udp://tracker.torrent.eu.org:451/announce',
        'udp://explodie.org:6969/announce',
        'udp://tracker.moeking.me:6969/announce',
        'udp://p4p.arenabg.com:1337/announce',
        'udp://tracker.tiny-vps.com:6969/announce',
    ];

    /**
     * Resolve search query title, preferring clean ASCII English title if primary title contains non-ASCII characters.
     */
    protected function resolveSearchTitle(string $title, ?string $originalTitle = null): string
    {
        $hasNonAscii = (bool) preg_match('/[^\x20-\x7E]/', $title);
        if ($hasNonAscii && ! empty($originalTitle)) {
            return trim($originalTitle);
        }

        return trim($title);
    }

    /**
     * Search torrents for a movie.
     */
    public function searchMovieTorrents(string $title, ?int $year = null, ?string $imdbId = null, ?int $tmdbId = null, bool $isAnimated = false, bool $cleanOnly = false, ?string $originalTitle = null): array
    {
        $searchTitle = $this->resolveSearchTitle($title, $originalTitle);
        $cacheKey = 'scout_torrents_movie_'.md5("{$title}_{$searchTitle}_{$year}_{$imdbId}_{$tmdbId}_{$isAnimated}_{$cleanOnly}");

        return Cache::remember($cacheKey, 1800, function () use ($title, $searchTitle, $year, $imdbId, $tmdbId, $cleanOnly) {
            $cleanImdb = $this->ensureImdbId($imdbId, $tmdbId, 'movie');

            $responses = Http::pool(function (Pool $pool) use ($cleanImdb, $searchTitle, $year) {
                $requests = [];

                if (! empty($cleanImdb)) {
                    $requests['torrentio'] = $pool->as('torrentio')
                        ->timeout(4)
                        ->withOptions(['verify' => false])
                        ->get(self::TORRENTIO_URL."/stream/movie/{$cleanImdb}.json");
                }

                $requests['yts'] = $pool->as('yts')
                    ->timeout(4)
                    ->withOptions(['verify' => false])
                    ->get(self::YTS_URL, [
                        'query_term' => $searchTitle,
                        'limit' => 5,
                    ]);

                $requests['yts_backup'] = $pool->as('yts_backup')
                    ->timeout(4)
                    ->withOptions(['verify' => false])
                    ->get(self::YTS_BACKUP_URL, [
                        'query_term' => $searchTitle,
                        'limit' => 5,
                    ]);

                $query = $year ? "{$searchTitle} {$year}" : $searchTitle;
                $requests['apibay'] = $pool->as('apibay')
                    ->timeout(4)
                    ->withOptions(['verify' => false])
                    ->get(self::APIBAY_URL, [
                        'q' => $query,
                        'cat' => '201,207',
                    ]);

                return $requests;
            });

            $results = [];

            if (isset($responses['torrentio'])) {
                $results = array_merge($results, $this->parseTorrentioResponse($responses['torrentio'], $title));
            }

            $ytsResults = isset($responses['yts']) ? $this->parseYtsResponse($responses['yts'], $year) : [];
            if (empty($ytsResults) && isset($responses['yts_backup'])) {
                $ytsResults = $this->parseYtsResponse($responses['yts_backup'], $year);
            }
            if (! empty($ytsResults)) {
                $results = array_merge($results, $ytsResults);
            }

            if (isset($responses['apibay'])) {
                $results = array_merge($results, $this->parseApibayResponse($responses['apibay']));
            }

            // STRICT FILTERING FOR MOVIES:
            // 1. Exclude any TV series patterns (e.g. S01, S02E01, 1x02, Season 1, Episode 4)
            // 2. Exclude releases whose explicit 4-digit year differs by more than 1 (disentangling remakes from originals)
            $filtered = array_filter($results, function ($item) use ($year, $cleanOnly) {
                $tTitle = $item['title'] ?? '';

                if (preg_match('/(?i)\bS\d{1,2}(?:E\d{1,2})?\b|\b\d{1,2}x\d{1,2}\b|\bSeason\s*\d+\b|\bEpisode\s*\d+\b/', $tTitle)) {
                    return false;
                }

                if ($year && preg_match('/\b(19\d{2}|20\d{2})\b/', $tTitle, $m)) {
                    $relYear = (int) $m[1];
                    if (abs($relYear - $year) > 1) {
                        return false;
                    }
                }

                if ($cleanOnly && preg_match('/\b(cam|hdcam|camrip|ts|hdts|telesync|pdvd|telecine|tc|hdtc|dvdscr|scr|screener|r5|workprint|hc|korsub|subbed)\b/i', $tTitle)) {
                    return false;
                }

                return true;
            });

            $finalList = ! empty($filtered) ? array_values($filtered) : ($cleanOnly ? [] : $results);

            return $this->rankAndDeduplicate($finalList);
        });
    }

    /**
     * Determine if a torrent title matches a specific episode (e.g., S01E02, 1x02).
     */
    public function matchesEpisode(string $title, int $season, int $episode): bool
    {
        $cleanTitle = trim($title);

        // 1. Standard S01E02 / S1E2 / S01.E02 / S01_E02 / S01 - E02
        if (preg_match('/(?i)\bS0*'.$season.'[\.\_\-\s]*E0*'.$episode.'\b/', $cleanTitle)) {
            return true;
        }

        // 2. Standard 1x02 / 01x02 / 1x2
        if (preg_match('/(?i)\b0*'.$season.'x0*'.$episode.'\b/', $cleanTitle)) {
            return true;
        }

        // 3. Multi-episode range like S01E01-E04, S01E01-04, S01E01-E02
        if (preg_match('/(?i)\bS0*'.$season.'[\.\_\-\s]*E(\d+)[\-\_–~]+(?:E)?(\d+)\b/', $cleanTitle, $m)) {
            $start = (int) $m[1];
            $end = (int) $m[2];
            if ($episode >= $start && $episode <= $end) {
                return true;
            }
        }

        // 4. "Episode 2" or "Ep 02" if season is 1 or season is explicitly mentioned elsewhere in title
        if (preg_match('/(?i)\b(?:EP|EPISODE)[\.\_\-\s]*0*'.$episode.'\b/', $cleanTitle)) {
            if ($season === 1 || preg_match('/(?i)\b(?:S0*'.$season.'|Season[\.\_\-\s]*0*'.$season.')\b/', $cleanTitle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a torrent title represents a full season pack (e.g. S01 Complete, Season 1 Batch).
     */
    public function isSeasonPack(string $title, int $season): bool
    {
        $cleanTitle = trim($title);

        // Disqualify if it explicitly matches a single episode like S01E05 or 1x05 without being a complete pack
        if (preg_match('/(?i)\bS0*\d+[\.\_\-\s]*E\d+\b/', $cleanTitle) || preg_match('/(?i)\b\d+x\d+\b/', $cleanTitle)) {
            // Only allow if it's explicitly a multi-episode batch like E01-E24 or has Complete/Batch
            if (! preg_match('/(?i)\bE\d+[\-\_–~]+(?:E)?\d+\b/', $cleanTitle) && ! preg_match('/(?i)\b(?:Complete|Full|Batch)\b/', $cleanTitle)) {
                return false;
            }
        }

        // Must match Season number
        $hasSeason = preg_match('/(?i)\b(?:Season[\.\_\-\s]*0*'.$season.'|S0*'.$season.')\b/', $cleanTitle);
        if (! $hasSeason) {
            return false;
        }

        // Check for season pack keywords or absence of single episode
        $isPack = preg_match('/(?i)\b(?:Complete|Full|Batch|Season[\.\_\-\s]*Pack|All[\.\_\-\s]*Episodes|Boxset)\b/', $cleanTitle)
            || preg_match('/(?i)\b(?:Season[\.\_\-\s]*0*'.$season.'|S0*'.$season.')\b(?![.\s_-]*E\d+)/', $cleanTitle);

        return (bool) $isPack;
    }

    /**
     * Search torrents for a specific episode.
     */
    public function searchEpisodeTorrents(
        string $seriesTitle,
        int $season,
        int $episode,
        ?string $imdbId = null,
        ?int $tmdbId = null,
        ?int $year = null,
        bool $isAnimated = false,
        bool $cleanOnly = false,
        ?string $originalTitle = null
    ): array {
        $searchTitle = $this->resolveSearchTitle($seriesTitle, $originalTitle);
        $epCode = sprintf('S%02dE%02d', $season, $episode);
        $cacheKey = 'scout_torrents_ep_'.md5("{$seriesTitle}_{$searchTitle}_{$epCode}_{$imdbId}_{$tmdbId}_{$year}_{$isAnimated}_{$cleanOnly}");

        return Cache::remember($cacheKey, 1800, function () use ($seriesTitle, $searchTitle, $season, $episode, $epCode, $imdbId, $tmdbId, $year, $cleanOnly) {
            $cleanImdb = $this->ensureImdbId($imdbId, $tmdbId, 'series');

            $responses = Http::pool(function (Pool $pool) use ($cleanImdb, $searchTitle, $season, $episode, $epCode, $year) {
                $requests = [];

                if (! empty($cleanImdb)) {
                    $requests['torrentio'] = $pool->as('torrentio')
                        ->timeout(4)
                        ->withOptions(['verify' => false])
                        ->get(self::TORRENTIO_URL."/stream/series/{$cleanImdb}:{$season}:{$episode}.json");

                    $numericImdb = preg_replace('/[^0-9]/', '', $cleanImdb);
                    if (! empty($numericImdb)) {
                        $paddedImdb = str_pad($numericImdb, 7, '0', STR_PAD_LEFT);
                        $requests['eztv'] = $pool->as('eztv')
                            ->timeout(4)
                            ->withOptions(['verify' => false])
                            ->get(self::EZTV_URL, [
                                'imdb_id' => $paddedImdb,
                                'limit' => 30,
                            ]);
                    }
                }

                $requests['apibay_ep'] = $pool->as('apibay_ep')
                    ->timeout(4)
                    ->withOptions(['verify' => false])
                    ->get(self::APIBAY_URL, [
                        'q' => "{$searchTitle} {$epCode}",
                        'cat' => '205,208',
                    ]);

                if ($year) {
                    $requests['apibay_year'] = $pool->as('apibay_year')
                        ->timeout(4)
                        ->withOptions(['verify' => false])
                        ->get(self::APIBAY_URL, [
                            'q' => "{$searchTitle} {$year} {$epCode}",
                            'cat' => '205,208',
                        ]);
                }

                return $requests;
            });

            $results = [];

            if (isset($responses['torrentio'])) {
                $results = array_merge($results, $this->parseTorrentioResponse($responses['torrentio'], "{$seriesTitle} {$epCode}"));
            }

            if (isset($responses['eztv'])) {
                $results = array_merge($results, $this->parseEztvResponse($responses['eztv'], $season, $episode));
            }

            if (isset($responses['apibay_ep'])) {
                $results = array_merge($results, $this->parseApibayResponse($responses['apibay_ep']));
            }
            if (isset($responses['apibay_year'])) {
                $results = array_merge($results, $this->parseApibayResponse($responses['apibay_year']));
            }

            // STRICT FILTERING: Keep only torrents that match episode and release year
            $filtered = array_filter($results, function ($item) use ($season, $episode, $year, $cleanOnly) {
                $tTitle = $item['title'] ?? '';

                if (! $this->matchesEpisode($tTitle, $season, $episode)) {
                    return false;
                }

                if ($year && preg_match('/\b(19\d{2}|20\d{2})\b/', $tTitle, $m)) {
                    $relYear = (int) $m[1];
                    if (abs($relYear - $year) > 1) {
                        return false;
                    }
                }

                if ($cleanOnly && preg_match('/\b(cam|hdcam|camrip|ts|hdts|telesync|pdvd|telecine|tc|hdtc|dvdscr|scr|screener|r5|workprint|hc|korsub|subbed)\b/i', $tTitle)) {
                    return false;
                }

                return true;
            });

            $finalList = ! empty($filtered) ? array_values($filtered) : ($cleanOnly ? [] : $results);

            return $this->rankAndDeduplicate($finalList);
        });
    }

    /**
     * Search torrents for an entire season pack.
     */
    public function searchSeasonTorrents(
        string $seriesTitle,
        int $season,
        ?string $imdbId = null,
        ?int $tmdbId = null,
        ?int $year = null,
        bool $isAnimated = false,
        bool $cleanOnly = false,
        ?string $originalTitle = null
    ): array {
        $searchTitle = $this->resolveSearchTitle($seriesTitle, $originalTitle);
        $sPadded = sprintf('%02d', $season);
        $cacheKey = 'scout_torrents_season_'.md5("{$seriesTitle}_{$searchTitle}_{$season}_{$imdbId}_{$tmdbId}_{$year}_{$isAnimated}_{$cleanOnly}");

        return Cache::remember($cacheKey, 1800, function () use ($searchTitle, $season, $sPadded, $imdbId, $tmdbId, $year, $cleanOnly) {
            $cleanImdb = $this->ensureImdbId($imdbId, $tmdbId, 'series');

            $responses = Http::pool(function (Pool $pool) use ($cleanImdb, $searchTitle, $season, $sPadded) {
                $requests = [];

                if (! empty($cleanImdb)) {
                    $numericImdb = preg_replace('/[^0-9]/', '', $cleanImdb);
                    if (! empty($numericImdb)) {
                        $paddedImdb = str_pad($numericImdb, 7, '0', STR_PAD_LEFT);
                        $requests['eztv'] = $pool->as('eztv')
                            ->timeout(4)
                            ->withOptions(['verify' => false])
                            ->get(self::EZTV_URL, [
                                'imdb_id' => $paddedImdb,
                                'limit' => 50,
                            ]);
                    }
                }

                $requests['apibay_complete'] = $pool->as('apibay_complete')
                    ->timeout(4)
                    ->withOptions(['verify' => false])
                    ->get(self::APIBAY_URL, [
                        'q' => "{$searchTitle} S{$sPadded} Complete",
                        'cat' => '205,208',
                    ]);

                $requests['apibay_season_complete'] = $pool->as('apibay_season_complete')
                    ->timeout(4)
                    ->withOptions(['verify' => false])
                    ->get(self::APIBAY_URL, [
                        'q' => "{$searchTitle} Season {$season} Complete",
                        'cat' => '205,208',
                    ]);

                $requests['apibay_season'] = $pool->as('apibay_season')
                    ->timeout(4)
                    ->withOptions(['verify' => false])
                    ->get(self::APIBAY_URL, [
                        'q' => "{$searchTitle} Season {$season}",
                        'cat' => '205,208',
                    ]);

                $requests['apibay_spadded'] = $pool->as('apibay_spadded')
                    ->timeout(4)
                    ->withOptions(['verify' => false])
                    ->get(self::APIBAY_URL, [
                        'q' => "{$searchTitle} S{$sPadded}",
                        'cat' => '205,208',
                    ]);

                return $requests;
            });

            $results = [];

            if (isset($responses['eztv'])) {
                $results = array_merge($results, $this->parseEztvResponse($responses['eztv'], $season));
            }

            foreach (['apibay_complete', 'apibay_season_complete', 'apibay_season', 'apibay_spadded'] as $key) {
                if (isset($responses[$key])) {
                    $results = array_merge($results, $this->parseApibayResponse($responses[$key]));
                }
            }

            // STRICT FILTERING: Only include true season packs and respect release year
            $seasonPacks = array_filter($results, function ($item) use ($season, $year, $cleanOnly) {
                $tTitle = $item['title'] ?? '';

                if (! $this->isSeasonPack($tTitle, $season)) {
                    return false;
                }

                if ($year && preg_match('/\b(19\d{2}|20\d{2})\b/', $tTitle, $m)) {
                    $relYear = (int) $m[1];
                    if (abs($relYear - $year) > 1) {
                        return false;
                    }
                }

                if ($cleanOnly && preg_match('/\b(cam|hdcam|camrip|ts|hdts|telesync|pdvd|telecine|tc|hdtc|dvdscr|scr|screener|r5|workprint|hc|korsub|subbed)\b/i', $tTitle)) {
                    return false;
                }

                return true;
            });

            $finalList = ! empty($seasonPacks) ? array_values($seasonPacks) : $results;

            return $this->rankAndDeduplicate($finalList);
        });
    }

    /**
     * Parse streams from Torrentio response.
     */
    protected function parseTorrentioResponse($res, string $fallbackTitle): array
    {
        if (! ($res instanceof Response) || ! $res->successful()) {
            return [];
        }

        $streams = $res->json('streams') ?? [];
        if (! is_array($streams)) {
            return [];
        }

        $results = [];

        foreach ($streams as $stream) {
            $rawTitle = $stream['title'] ?? ($stream['name'] ?? $fallbackTitle);
            $infoHash = $stream['infoHash'] ?? null;

            if (empty($infoHash)) {
                continue;
            }

            $lines = explode("\n", (string) $rawTitle);
            $releaseName = trim($lines[0] ?? $fallbackTitle);

            $seeds = 0;
            $sizeHuman = 'Unknown';
            $source = 'Torrentio';

            foreach ($lines as $line) {
                if (preg_match('/👤\s*(\d+)/u', $line, $m)) {
                    $seeds = (int) $m[1];
                }
                if (preg_match('/💾\s*([\d\.]+\s*(?:GB|MB|KB|B))/iu', $line, $m)) {
                    $sizeHuman = trim($m[1]);
                }
                if (preg_match('/⚙️\s*([^\n\r]+)/u', $line, $m)) {
                    $source = trim($m[1]);
                }
            }

            $specs = $this->extractTechnicalSpecs($releaseName, $rawTitle.' '.($stream['name'] ?? ''));
            $magnet = $this->buildMagnetUri($infoHash, $releaseName);

            $results[] = $this->formatTorrentItem(
                title: $releaseName,
                resolution: $specs['resolution'],
                qualityTag: $specs['quality_tag'],
                sizeHuman: $sizeHuman,
                seeders: $seeds,
                source: $source,
                infoHash: $infoHash,
                magnetUrl: $magnet,
                technicalSpecs: $specs
            );
        }

        return $results;
    }

    /**
     * Parse EZTV response.
     */
    protected function parseEztvResponse($res, int $season, ?int $episode = null): array
    {
        if (! ($res instanceof Response) || ! $res->successful()) {
            return [];
        }

        $torrents = $res->json('torrents') ?? [];
        if (! is_array($torrents)) {
            return [];
        }

        $results = [];

        foreach ($torrents as $t) {
            $tSeason = (int) ($t['season'] ?? 0);
            $tEp = (int) ($t['episode'] ?? 0);

            if ($tSeason !== $season) {
                continue;
            }

            if ($episode !== null && $tEp !== $episode) {
                continue;
            }

            $title = $t['title'] ?? 'EZTV Release';
            $seeds = (int) ($t['seeds'] ?? 0);
            $sizeBytes = (int) ($t['size_bytes'] ?? 0);
            $sizeHuman = $this->formatBytes($sizeBytes);
            $hash = $t['hash'] ?? null;
            $magnet = $t['magnet_url'] ?? ($hash ? $this->buildMagnetUri($hash, $title) : null);

            if (empty($magnet)) {
                continue;
            }

            $quality = $this->extractQualityTag($title);

            $results[] = $this->formatTorrentItem(
                title: $title,
                resolution: $quality['resolution'],
                qualityTag: $quality['tag'],
                sizeHuman: $sizeHuman,
                seeders: $seeds,
                source: 'EZTV',
                infoHash: $hash,
                magnetUrl: $magnet
            );
        }

        return $results;
    }

    /**
     * Parse YTS response.
     */
    protected function parseYtsResponse($res, ?int $year): array
    {
        if (! ($res instanceof Response) || ! $res->successful() || $res->json('status') !== 'ok') {
            return [];
        }

        $movies = $res->json('data.movies') ?? [];
        if (! is_array($movies)) {
            return [];
        }

        $results = [];

        foreach ($movies as $movie) {
            $mYear = (int) ($movie['year'] ?? 0);
            if ($year && abs($mYear - $year) > 1) {
                continue;
            }

            foreach ($movie['torrents'] ?? [] as $t) {
                $quality = $t['quality'] ?? '1080p';
                $type = $t['type'] ?? 'BluRay';
                $hash = $t['hash'] ?? '';
                $seeds = (int) ($t['seeds'] ?? 0);
                $size = $t['size'] ?? 'Unknown';

                if (empty($hash)) {
                    continue;
                }

                $movieTitle = $movie['title_long'] ?? ($movie['title'] ?? 'Movie');
                $relTitle = "{$movieTitle} [{$quality}] [{$type}] [YTS]";
                $magnet = $this->buildMagnetUri($hash, $relTitle);

                $results[] = $this->formatTorrentItem(
                    title: $relTitle,
                    resolution: $quality,
                    qualityTag: "{$quality} {$type}",
                    sizeHuman: $size,
                    seeders: $seeds,
                    source: 'YTS',
                    infoHash: $hash,
                    magnetUrl: $magnet
                );
            }
        }

        return $results;
    }

    /**
     * Parse Apibay response.
     */
    protected function parseApibayResponse($res): array
    {
        if (! ($res instanceof Response) || ! $res->successful()) {
            return [];
        }

        $torrents = $res->json() ?? [];
        if (! is_array($torrents)) {
            return [];
        }

        $results = [];
        foreach ($torrents as $t) {
            $name = $t['name'] ?? '';
            if (empty($name) || $name === 'No results returned') {
                continue;
            }

            $hash = $t['info_hash'] ?? null;
            if (empty($hash)) {
                continue;
            }

            $seeds = (int) ($t['seeders'] ?? 0);
            $sizeBytes = (int) ($t['size'] ?? 0);
            $sizeHuman = $this->formatBytes($sizeBytes);
            $quality = $this->extractQualityTag($name);
            $magnet = $this->buildMagnetUri($hash, $name);

            $results[] = $this->formatTorrentItem(
                title: $name,
                resolution: $quality['resolution'],
                qualityTag: $quality['tag'],
                sizeHuman: $sizeHuman,
                seeders: $seeds,
                source: 'TPB',
                infoHash: $hash,
                magnetUrl: $magnet
            );
        }

        return $results;
    }

    /**
     * Fetch streams from Torrentio.
     */
    protected function fetchFromTorrentio(string $path, string $fallbackTitle): array
    {
        try {
            $url = self::TORRENTIO_URL."/{$path}";
            $res = Http::timeout(4)->withOptions(['verify' => false])->get($url);

            return $this->parseTorrentioResponse($res, $fallbackTitle);
        } catch (\Throwable $e) {
            Log::warning('Torrentio search failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Fetch from EZTV API.
     */
    protected function fetchFromEztv(string $numericImdb, int $season, int $episode): array
    {
        try {
            $paddedImdb = str_pad($numericImdb, 7, '0', STR_PAD_LEFT);
            $res = Http::timeout(4)->withOptions(['verify' => false])->get(self::EZTV_URL, [
                'imdb_id' => $paddedImdb,
                'limit' => 30,
            ]);

            return $this->parseEztvResponse($res, $season, $episode);
        } catch (\Throwable $e) {
            Log::warning('EZTV search failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Fetch from YTS mirror.
     */
    protected function fetchFromYts(string $title, ?int $year): array
    {
        try {
            $res = Http::timeout(4)->withOptions(['verify' => false])->get(self::YTS_URL, [
                'query_term' => $title,
                'limit' => 5,
            ]);

            $results = $this->parseYtsResponse($res, $year);
            if (! empty($results)) {
                return $results;
            }

            $resBackup = Http::timeout(4)->withOptions(['verify' => false])->get(self::YTS_BACKUP_URL, [
                'query_term' => $title,
                'limit' => 5,
            ]);

            return $this->parseYtsResponse($resBackup, $year);
        } catch (\Throwable $e) {
            Log::warning('YTS search failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Fetch from Apibay with optional category filtering (e.g. 201,207 for Movies, 205,208 for TV Shows).
     */
    protected function fetchFromApibay(string $query, ?string $category = null): array
    {
        try {
            $params = ['q' => $query];
            if ($category) {
                $params['cat'] = $category;
            }

            $res = Http::timeout(4)->withOptions(['verify' => false])->get(self::APIBAY_URL, $params);

            return $this->parseApibayResponse($res);
        } catch (\Throwable $e) {
            Log::warning('Apibay search failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Ensure we have a valid IMDB ID by querying TMDB if necessary.
     */
    protected function ensureImdbId(?string $imdbId, ?int $tmdbId, string $type = 'movie'): ?string
    {
        if (! empty($imdbId) && str_starts_with($imdbId, 'tt')) {
            return $imdbId;
        }

        if (empty($tmdbId)) {
            return null;
        }

        $apiKey = AppSetting::get('tmdb_api_key') ?? config('services.tmdb.key');
        if (empty($apiKey)) {
            return null;
        }

        return Cache::remember("tmdb_to_imdb_{$type}_{$tmdbId}", 86400 * 7, function () use ($apiKey, $tmdbId, $type) {
            try {
                $endpoint = $type === 'series'
                    ? "https://api.themoviedb.org/3/tv/{$tmdbId}/external_ids"
                    : "https://api.themoviedb.org/3/movie/{$tmdbId}";

                $res = Http::timeout(6)->get($endpoint, ['api_key' => $apiKey]);
                if ($res->successful()) {
                    $val = $res->json('imdb_id');
                    if (! empty($val) && str_starts_with($val, 'tt')) {
                        return $val;
                    }
                }
            } catch (\Throwable $e) {
            }

            return null;
        });
    }

    /**
     * Build standard magnet URI.
     */
    public function buildMagnetUri(string $infoHash, string $name): string
    {
        $dn = rawurlencode(trim($name));
        $xt = 'urn:btih:'.strtolower(trim($infoHash));
        $trackersStr = '';

        foreach ($this->trackers as $tr) {
            $trackersStr .= '&tr='.rawurlencode($tr);
        }

        return "magnet:?xt={$xt}&dn={$dn}{$trackersStr}";
    }

    /**
     * Parse resolution and quality from title string.
     */
    protected function extractQualityTag(string $title): array
    {
        $resolution = '1080p'; // sensible default
        if (preg_match('/(2160p|4k|uhd)/i', $title)) {
            $resolution = '4K';
        } elseif (preg_match('/(1080p|1080i|fhd)/i', $title)) {
            $resolution = '1080p';
        } elseif (preg_match('/(720p|hd)/i', $title)) {
            $resolution = '720p';
        } elseif (preg_match('/(480p|sd|dvdrip)/i', $title)) {
            $resolution = '480p';
        }

        $sourceTag = 'WEB-DL';
        if (preg_match('/(bluray|bdrip|remux)/i', $title)) {
            $sourceTag = 'BluRay';
        } elseif (preg_match('/(web-dl|webrip|web)/i', $title)) {
            $sourceTag = 'WEB-DL';
        } elseif (preg_match('/(hdtv)/i', $title)) {
            $sourceTag = 'HDTV';
        }

        return [
            'resolution' => $resolution,
            'tag' => "{$resolution} {$sourceTag}",
        ];
    }

    /**
     * Extract detailed torrent site specifications from release title and context.
     * Extracts resolution, source type, video codec, audio specs, HDR, languages, subs, and release group.
     */
    public function extractTechnicalSpecs(string $title, ?string $rawContext = null): array
    {
        $text = $title.' '.($rawContext ?? '');

        // 1. Resolution
        $resolution = '1080p';
        if (preg_match('/\b(2160p|4k|uhd)\b/i', $text)) {
            $resolution = '4K UHD';
        } elseif (preg_match('/\b(1080p|1080i|fhd)\b/i', $text)) {
            $resolution = '1080p FHD';
        } elseif (preg_match('/\b(720p|hd)\b/i', $text)) {
            $resolution = '720p HD';
        } elseif (preg_match('/\b(480p|sd|dvdrip)\b/i', $text)) {
            $resolution = '480p SD';
        }

        // 2. Source Type / Remux
        $sourceType = 'WEB-DL';
        if (preg_match('/\bremux\b/i', $text)) {
            $sourceType = 'Remux';
        } elseif (preg_match('/\b(bluray|bdrip|brrip)\b/i', $text)) {
            $sourceType = 'BluRay';
        } elseif (preg_match('/\b(web-?dl|webrip)\b/i', $text)) {
            $sourceType = preg_match('/\bwebrip\b/i', $text) ? 'WEBRip' : 'WEB-DL';
        } elseif (preg_match('/\bhdtv\b/i', $text)) {
            $sourceType = 'HDTV';
        } elseif (preg_match('/\b(dvd|dvd-?r)\b/i', $text)) {
            $sourceType = 'DVD';
        } elseif (preg_match('/\b(cam|hdts|telecine|telesync)\b/i', $text)) {
            $sourceType = 'CAM/TS';
        }

        // 3. Video Codec & Bit Depth
        $videoCodec = 'x264 (8-bit)';
        $is10bit = (bool) preg_match('/\b(10-?bit|main\s*10)\b/i', $text);
        if (preg_match('/\b(x265|hevc|h\.?265)\b/i', $text)) {
            $videoCodec = $is10bit ? 'HEVC / x265 (10-bit)' : 'HEVC / x265';
        } elseif (preg_match('/\b(av0?1)\b/i', $text)) {
            $videoCodec = 'AV1 (10-bit)';
        } elseif (preg_match('/\b(x264|h\.?264|avc)\b/i', $text)) {
            $videoCodec = $is10bit ? 'AVC / x264 (10-bit)' : 'AVC / x264';
        } elseif (preg_match('/\b(xvid|divx)\b/i', $text)) {
            $videoCodec = 'XviD';
        }

        // 4. HDR / Color Dynamics
        $hdr = 'SDR';
        $hasDv = (bool) preg_match('/\b(dv|dovi|dolby\s*vision)\b/i', $text);
        $hasHdr10Plus = (bool) preg_match('/\bhdr10\+\b/i', $text);
        $hasHdr10 = (bool) preg_match('/\bhdr(?:10)?\b/i', $text);

        if ($hasDv && ($hasHdr10Plus || $hasHdr10)) {
            $hdr = 'Dolby Vision + HDR10';
        } elseif ($hasDv) {
            $hdr = 'Dolby Vision';
        } elseif ($hasHdr10Plus) {
            $hdr = 'HDR10+';
        } elseif ($hasHdr10) {
            $hdr = 'HDR10';
        }

        // 5. Audio Codecs & Channels
        $audio = 'AAC 2.0';
        $channels = '2.0';
        if (preg_match('/\b7\.1\b/i', $text)) {
            $channels = '7.1';
        } elseif (preg_match('/\b5\.1\b/i', $text)) {
            $channels = '5.1';
        }

        if (preg_match('/\batmos\b/i', $text)) {
            $audio = "Dolby Atmos ({$channels})";
        } elseif (preg_match('/\btruehd\b/i', $text)) {
            $audio = "TrueHD ({$channels})";
        } elseif (preg_match('/\bdts-?hd(?:\s*ma)?\b/i', $text)) {
            $audio = "DTS-HD MA ({$channels})";
        } elseif (preg_match('/\bdts\b/i', $text)) {
            $audio = "DTS ({$channels})";
        } elseif (preg_match('/\b(ddp|eac3|dd\+|dolby\s*digital\s*plus)\b/i', $text)) {
            $audio = "Dolby Digital Plus ({$channels})";
        } elseif (preg_match('/\b(ac3|dd5\.1|dd2\.0)\b/i', $text)) {
            $audio = "Dolby Digital ({$channels})";
        } elseif (preg_match('/\bflac\b/i', $text)) {
            $audio = "FLAC Lossless ({$channels})";
        } elseif (preg_match('/\baac\b/i', $text)) {
            $audio = "AAC ({$channels})";
        }

        // 6. Dubs / Multi-Audio
        $audioLanguages = [];
        if (preg_match('/\bdual-?audio\b/i', $text)) {
            $audioLanguages[] = 'Dual-Audio';
        }
        if (preg_match('/\bmulti-?(?:audio|lang)\b/i', $text)) {
            $audioLanguages[] = 'Multi-Audio';
        }
        if (preg_match('/\b(hindi|hin)\b/i', $text)) {
            $audioLanguages[] = 'Hindi';
        }
        if (preg_match('/\b(arabic|ara)\b/i', $text)) {
            $audioLanguages[] = 'Arabic';
        }
        if (preg_match('/\b(tamil|tam)\b/i', $text)) {
            $audioLanguages[] = 'Tamil';
        }
        if (preg_match('/\b(telugu|tel)\b/i', $text)) {
            $audioLanguages[] = 'Telugu';
        }
        if (preg_match('/\b(japanese|jpn)\b/i', $text)) {
            $audioLanguages[] = 'Japanese';
        }
        if (preg_match('/\b(korean|kor)\b/i', $text)) {
            $audioLanguages[] = 'Korean';
        }

        // 7. Subtitles
        $subs = [];
        if (preg_match('/\bmulti-?subs?\b/i', $text)) {
            $subs[] = 'Multi-Subs';
        }
        if (preg_match('/\b(esubs?|eng-?subs?)\b/i', $text)) {
            $subs[] = 'English Subs';
        }
        if (preg_match('/\b(ar-?subs?|arabic-?subs?)\b/i', $text)) {
            $subs[] = 'Arabic Subs';
        }

        // 8. Release Group
        $releaseGroup = null;
        if (preg_match('/-([A-Za-z0-9]+)(?:\[[^\]]*\])*$/', trim($title), $m)) {
            $releaseGroup = $m[1];
        }
        if (empty($releaseGroup)) {
            $knownGroups = [
                'PSA', 'QxR', 'YIFY', 'YTS', 'RARBG', 'FLUX', 'GalaxyRG', 'NTb',
                'SWTYBLZ', 'Framestor', 'Tigole', 'UTR', 'Vyndros', 'ION10',
                'MeGusta', 'GECKOS', 'SPARKS', 'AMIABLE', 'LOST', 'CtrlHD',
                'DON', 'playBD', 'KOGi', 'SURCODE', 'SMURF', 'Pahe', 'TGx',
                'ETRG', 'BONE', 'CMRG', 'EVO', 'WAF', 'SEV', 'TEKNO3D',
            ];
            foreach ($knownGroups as $kg) {
                if (preg_match('/\b'.preg_quote($kg, '/').'\b/i', $text)) {
                    $releaseGroup = $kg;
                    break;
                }
            }
        }

        return [
            'resolution' => $resolution,
            'source_type' => $sourceType,
            'quality_tag' => "{$resolution} {$sourceType}",
            'video_codec' => $videoCodec,
            'audio_codec' => $audio,
            'audio_channels' => $channels,
            'hdr' => $hdr,
            'audio_languages' => $audioLanguages,
            'subtitles' => $subs,
            'release_group' => $releaseGroup ?? 'Scene/P2P',
        ];
    }

    /**
     * Format a torrent item with standard keys, seed health, speed tier, and rich technical specs.
     */
    protected function formatTorrentItem(
        string $title,
        string $resolution,
        string $qualityTag,
        string $sizeHuman,
        int $seeders,
        string $source,
        string $infoHash,
        string $magnetUrl,
        int $leechers = 0,
        ?array $technicalSpecs = null
    ): array {
        $specs = $technicalSpecs ?? $this->extractTechnicalSpecs($title);

        $health = match (true) {
            $seeders >= 15 => 'excellent',
            $seeders >= 5 => 'good',
            $seeders >= 2 => 'moderate',
            $seeders === 1 => 'low',
            default => 'dead',
        };

        $speedTier = match (true) {
            $seeders >= 15 => 'ultra_fast',
            $seeders >= 5 => 'fast',
            $seeders >= 2 => 'moderate',
            $seeders === 1 => 'slow',
            default => 'stalled',
        };

        return [
            'title' => $title,
            'resolution' => $specs['resolution'] ?? $resolution,
            'source_type' => $specs['source_type'] ?? 'WEB-DL',
            'quality_tag' => $specs['quality_tag'] ?? $qualityTag,
            'video_codec' => $specs['video_codec'] ?? 'x264',
            'audio_codec' => $specs['audio_codec'] ?? 'AAC 2.0',
            'audio_channels' => $specs['audio_channels'] ?? '2.0',
            'hdr' => $specs['hdr'] ?? 'SDR',
            'audio_languages' => $specs['audio_languages'] ?? [],
            'subtitles' => $specs['subtitles'] ?? [],
            'release_group' => $specs['release_group'] ?? 'Scene/P2P',
            'size_human' => $sizeHuman,
            'seeders' => $seeders,
            'seeds' => $seeders,
            'leechers' => $leechers,
            'peers' => $leechers,
            'source' => $source,
            'source_indexer' => $source,
            'info_hash' => strtolower($infoHash),
            'magnet_url' => $magnetUrl,
            'health' => $health,
            'speed_tier' => $speedTier,
            'is_recommended' => $seeders >= 5,
        ];
    }

    /**
     * Rank results by seeders and remove duplicate hashes.
     * Prioritizes active verified seeds over dead torrents (0 seeds).
     */
    protected function rankAndDeduplicate(array $items): array
    {
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $hash = strtolower($item['info_hash'] ?? '');
            if (! empty($hash) && isset($seen[$hash])) {
                continue;
            }
            if (! empty($hash)) {
                $seen[$hash] = true;
            }
            $unique[] = $item;
        }

        // Sort: Active seeders first, then highest seeds descending, with resolution tie-breaker
        usort($unique, function ($a, $b) {
            $seedsA = (int) ($a['seeders'] ?? 0);
            $seedsB = (int) ($b['seeders'] ?? 0);

            // Active seeds > 0 come strictly before 0-seed dead releases
            if (($seedsA > 0) !== ($seedsB > 0)) {
                return $seedsB > 0 ? 1 : -1;
            }

            // Descending seed count
            if ($seedsA !== $seedsB) {
                return $seedsB <=> $seedsA;
            }

            // Tie breaker: prefer 1080p / 4K over 480p
            $resRank = ['4K' => 4, '1080p' => 3, '720p' => 2, '480p' => 1];
            $rankA = $resRank[$a['resolution'] ?? ''] ?? 0;
            $rankB = $resRank[$b['resolution'] ?? ''] ?? 0;

            return $rankB <=> $rankA;
        });

        // Flag the top choice
        if (! empty($unique) && ($unique[0]['seeders'] ?? 0) > 0) {
            $unique[0]['is_top_choice'] = true;
        }

        return array_values($unique);
    }

    protected function formatBytes(int $bytes): string
    {
        return Number::fileSize($bytes, precision: 1);
    }
}
