<?php

namespace App\Services\Scout;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TorrentDiscoveryService
{
    protected const TORRENTIO_URL = 'https://torrentio.strem.fun';
    protected const EZTV_URL = 'https://eztv.re/api/get-torrents';
    protected const YTS_URL = 'https://yts.lt/api/v2/list_movies.json';
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
     * Search torrents for a movie.
     */
    public function searchMovieTorrents(string $title, ?int $year = null, ?string $imdbId = null, ?int $tmdbId = null): array
    {
        $cacheKey = 'scout_torrents_movie_' . md5("{$title}_{$year}_{$imdbId}_{$tmdbId}");
        return Cache::remember($cacheKey, 1800, function () use ($title, $year, $imdbId, $tmdbId) {
            $results = [];

            // 1. Resolve IMDB ID if missing
            $cleanImdb = $this->ensureImdbId($imdbId, $tmdbId, 'movie');

            // 2. Try Torrentio if IMDB ID available
            if (! empty($cleanImdb)) {
                $torrentioResults = $this->fetchFromTorrentio("stream/movie/{$cleanImdb}.json", $title);
                if (! empty($torrentioResults)) {
                    $results = array_merge($results, $torrentioResults);
                }
            }

            // 3. Try YTS Mirror
            $ytsResults = $this->fetchFromYts($title, $year);
            if (! empty($ytsResults)) {
                $results = array_merge($results, $ytsResults);
            }

            // 4. Fallback to Apibay if low results
            if (count($results) < 3) {
                $query = $year ? "{$title} {$year}" : $title;
                $apibayResults = $this->fetchFromApibay($query);
                if (! empty($apibayResults)) {
                    $results = array_merge($results, $apibayResults);
                }
            }

            return $this->rankAndDeduplicate($results);
        });
    }

    /**
     * Search torrents for a specific episode.
     */
    public function searchEpisodeTorrents(string $seriesTitle, int $season, int $episode, ?string $imdbId = null, ?int $tmdbId = null): array
    {
        $epCode = sprintf('S%02dE%02d', $season, $episode);
        $cacheKey = 'scout_torrents_ep_' . md5("{$seriesTitle}_{$epCode}_{$imdbId}_{$tmdbId}");

        return Cache::remember($cacheKey, 1800, function () use ($seriesTitle, $season, $episode, $epCode, $imdbId, $tmdbId) {
            $results = [];

            // 1. Resolve IMDB ID if missing
            $cleanImdb = $this->ensureImdbId($imdbId, $tmdbId, 'series');

            // 2. Try Torrentio
            if (! empty($cleanImdb)) {
                $endpoint = "stream/series/{$cleanImdb}:{$season}:{$episode}.json";
                $torrentioResults = $this->fetchFromTorrentio($endpoint, "{$seriesTitle} {$epCode}");
                if (! empty($torrentioResults)) {
                    $results = array_merge($results, $torrentioResults);
                }
            }

            // 3. Try EZTV if IMDB ID available
            if (! empty($cleanImdb)) {
                $numericImdb = preg_replace('/[^0-9]/', '', $cleanImdb);
                $eztvResults = $this->fetchFromEztv($numericImdb, $season, $episode);
                if (! empty($eztvResults)) {
                    $results = array_merge($results, $eztvResults);
                }
            }

            // 4. Fallback to Apibay
            if (count($results) < 3) {
                $query = "{$seriesTitle} {$epCode}";
                $apibayResults = $this->fetchFromApibay($query);
                if (! empty($apibayResults)) {
                    $results = array_merge($results, $apibayResults);
                }
            }

            return $this->rankAndDeduplicate($results);
        });
    }

    /**
     * Search torrents for an entire season pack.
     */
    public function searchSeasonTorrents(string $seriesTitle, int $season, ?string $imdbId = null, ?int $tmdbId = null): array
    {
        $seasonCode = sprintf('Season %02d', $season);
        $cacheKey = 'scout_torrents_season_' . md5("{$seriesTitle}_{$season}_{$imdbId}_{$tmdbId}");

        return Cache::remember($cacheKey, 1800, function () use ($seriesTitle, $season, $seasonCode) {
            $results = [];

            $query1 = "{$seriesTitle} S" . sprintf('%02d', $season);
            $query2 = "{$seriesTitle} Season {$season}";

            $apibay1 = $this->fetchFromApibay($query1);
            $apibay2 = $this->fetchFromApibay($query2);

            $merged = array_merge($apibay1, $apibay2);
            return $this->rankAndDeduplicate($merged);
        });
    }

    /**
     * Fetch streams from Torrentio.
     */
    protected function fetchFromTorrentio(string $path, string $fallbackTitle): array
    {
        try {
            $url = self::TORRENTIO_URL . "/{$path}";
            $res = Http::timeout(8)->withOptions(['verify' => false])->get($url);

            if (! $res->successful()) {
                return [];
            }

            $streams = $res->json('streams') ?? [];
            $results = [];

            foreach ($streams as $stream) {
                $rawTitle = $stream['title'] ?? ($stream['name'] ?? $fallbackTitle);
                $infoHash = $stream['infoHash'] ?? null;

                if (empty($infoHash)) {
                    continue;
                }

                // Parse details from Torrentio format:
                // title line 1: release name
                // line 2: 👤 seeds 💾 size ⚙️ source
                $lines = explode("\n", $rawTitle);
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

                $quality = $this->extractQualityTag($releaseName . ' ' . ($stream['name'] ?? ''));
                $resolution = $quality['resolution'];

                $magnet = $this->buildMagnetUri($infoHash, $releaseName);

                $results[] = $this->formatTorrentItem(
                    title: $releaseName,
                    resolution: $resolution,
                    qualityTag: $quality['tag'],
                    sizeHuman: $sizeHuman,
                    seeders: $seeds,
                    source: $source,
                    infoHash: $infoHash,
                    magnetUrl: $magnet
                );
            }

            return $results;
        } catch (\Throwable $e) {
            Log::warning("Torrentio search failed: " . $e->getMessage());
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
            $res = Http::timeout(8)->withOptions(['verify' => false])->get(self::EZTV_URL, [
                'imdb_id' => $paddedImdb,
                'limit' => 30,
            ]);

            if (! $res->successful()) {
                return [];
            }

            $torrents = $res->json('torrents') ?? [];
            $results = [];

            foreach ($torrents as $t) {
                $tSeason = (int) ($t['season'] ?? 0);
                $tEp = (int) ($t['episode'] ?? 0);

                if ($tSeason !== $season || $tEp !== $episode) {
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
        } catch (\Throwable $e) {
            Log::warning("EZTV search failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch from YTS mirror.
     */
    protected function fetchFromYts(string $title, ?int $year): array
    {
        try {
            $query = $title;
            $res = Http::timeout(8)->withOptions(['verify' => false])->get(self::YTS_URL, [
                'query_term' => $query,
                'limit' => 5,
            ]);

            if (! $res->successful() || $res->json('status') !== 'ok') {
                return [];
            }

            $movies = $res->json('data.movies') ?? [];
            $results = [];

            foreach ($movies as $movie) {
                // If year is provided, match within 1 year
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

                    $relTitle = "{$movie['title_long']} [{$quality}] [{$type}] [YTS]";
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
        } catch (\Throwable $e) {
            Log::warning("YTS search failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch from Apibay.
     */
    protected function fetchFromApibay(string $query): array
    {
        try {
            $res = Http::timeout(8)->withOptions(['verify' => false])->get(self::APIBAY_URL, [
                'q' => $query,
            ]);

            if (! $res->successful()) {
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
        } catch (\Throwable $e) {
            Log::warning("Apibay search failed: " . $e->getMessage());
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

        $apiKey = AppSetting::get('tmdb_api_key') ?? config('services.tmdb.key') ?? env('TMDB_API_KEY');
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
        $xt = "urn:btih:" . strtolower(trim($infoHash));
        $trackersStr = '';

        foreach ($this->trackers as $tr) {
            $trackersStr .= '&tr=' . rawurlencode($tr);
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
     * Format a torrent item with standard keys, seed health, and speed tier.
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
        int $leechers = 0
    ): array {
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
            'resolution' => $resolution,
            'quality_tag' => $qualityTag,
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
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}