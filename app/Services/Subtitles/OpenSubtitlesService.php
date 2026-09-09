<?php

namespace App\Services\Subtitles;

use App\Models\AppSetting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenSubtitlesService
{
    protected string $baseUrl = 'https://api.opensubtitles.com/api/v1';

    protected string $v3BaseUrl = 'https://opensubtitles-v3.strem.io/subtitles';

    protected string $cinemetaUrl = 'https://v3-cinemeta.strem.io/catalog';

    protected ?string $apiKey;

    protected static array $langMap3to2 = [
        'ara' => 'ar',
        'eng' => 'en',
        'fre' => 'fr',
        'fra' => 'fr',
        'spa' => 'es',
        'ger' => 'de',
        'deu' => 'de',
        'ita' => 'it',
        'tur' => 'tr',
        'por' => 'pt',
        'rus' => 'ru',
        'hin' => 'hi',
        'jpn' => 'ja',
        'kor' => 'ko',
        'chi' => 'zh',
        'zho' => 'zh',
        'zht' => 'zh',
        'heb' => 'he',
        'pol' => 'pl',
        'swe' => 'sv',
        'nor' => 'no',
        'dan' => 'da',
        'fin' => 'fi',
        'nld' => 'nl',
        'dut' => 'nl',
        'gre' => 'el',
        'ell' => 'el',
        'ind' => 'id',
        'may' => 'ms',
        'msa' => 'ms',
        'tha' => 'th',
        'vie' => 'vi',
        'ukr' => 'uk',
        'ces' => 'cs',
        'cze' => 'cs',
        'hun' => 'hu',
        'ron' => 'ro',
        'rum' => 'ro',
        'bul' => 'bg',
        'srp' => 'sr',
        'hrv' => 'hr',
        'slv' => 'sl',
    ];

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?: AppSetting::get('opensubtitles_api_key', config('services.opensubtitles.key'));
    }

    /**
     * Get the configured API key (used by controllers for diagnostic info).
     */
    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * Resolve IMDb ID from a movie/series title.
     * Tries: 1) Cinemeta, 2) TMDB Search API fallback.
     */
    public function resolveImdbId(string $query, string $type = 'movie', ?int $year = null): ?string
    {
        $cleanQuery = trim($query);
        if (empty($cleanQuery)) {
            return null;
        }

        // 1. Try Cinemeta Stremio Catalog (fastest, public)
        $imdbId = $this->resolveViaCinemeta($cleanQuery, $type, $year);
        if ($imdbId) {
            return $imdbId;
        }

        // 2. Fallback: TMDB Search API (handles non-English titles & precise year matching)
        $imdbId = $this->resolveViaTmdb($cleanQuery, $type, $year);
        if ($imdbId) {
            return $imdbId;
        }

        return null;
    }

    /**
     * Resolve IMDb ID via Stremio Cinemeta catalog.
     */
    protected function resolveViaCinemeta(string $query, string $type, ?int $year): ?string
    {
        try {
            $cinemetaType = ($type === 'series' || $type === 'tv' || $type === 'episode') ? 'series' : 'movie';
            $url = "{$this->cinemetaUrl}/{$cinemetaType}/top/search=".urlencode($query).'.json';

            $response = Http::timeout(5)->get($url);
            if ($response->successful()) {
                $metas = $response->json('metas', []);
                if (! empty($metas)) {
                    // Try to match release year if provided
                    if ($year) {
                        foreach ($metas as $meta) {
                            $metaYear = (int) ($meta['year'] ?? 0);
                            if (abs($metaYear - $year) <= 1 && ! empty($meta['imdb_id'])) {
                                return $meta['imdb_id'];
                            }
                        }
                    }

                    // Fallback to the top result
                    $first = $metas[0];
                    if (! empty($first['imdb_id'])) {
                        return $first['imdb_id'];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("Cinemeta IMDb resolution failed for '{$query}': ".$e->getMessage());
        }

        return null;
    }

    /**
     * Resolve IMDb ID via TMDB search API (fallback).
     */
    protected function resolveViaTmdb(string $query, string $type, ?int $year): ?string
    {
        $tmdbKey = AppSetting::get('tmdb_api_key', config('services.tmdb.key'));
        if (empty($tmdbKey)) {
            return null;
        }

        try {
            $tmdbType = ($type === 'series' || $type === 'tv' || $type === 'episode') ? 'tv' : 'movie';
            $params = [
                'api_key' => $tmdbKey,
                'query' => $query,
            ];
            if ($year) {
                $params[$tmdbType === 'movie' ? 'year' : 'first_air_date_year'] = $year;
            }

            $searchRes = Http::timeout(5)->get("https://api.themoviedb.org/3/search/{$tmdbType}", $params);
            if ($searchRes->successful()) {
                $results = $searchRes->json('results', []);
                if (! empty($results)) {
                    $tmdbId = $results[0]['id'] ?? null;
                    if ($tmdbId) {
                        // Fetch external IDs to get IMDb ID
                        $extRes = Http::timeout(5)->get("https://api.themoviedb.org/3/{$tmdbType}/{$tmdbId}/external_ids", [
                            'api_key' => $tmdbKey,
                        ]);
                        $imdbId = $extRes->json('imdb_id');
                        if (! empty($imdbId)) {
                            return $imdbId;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("TMDB IMDb resolution failed for '{$query}': ".$e->getMessage());
        }

        return null;
    }

    /**
     * Query Stremio OpenSubtitles v3 (Public High-Speed Endpoint with direct .srt download links).
     */
    public function searchOpenSubtitlesV3(string $imdbId, ?int $season = null, ?int $episode = null, ?string $lang = null): array
    {
        // Format IMDb ID to ttXXXXXXX
        if (! str_starts_with($imdbId, 'tt') && is_numeric($imdbId)) {
            $imdbId = 'tt'.str_pad($imdbId, 7, '0', STR_PAD_LEFT);
        }

        $isEpisode = ($season !== null && $episode !== null && $season > 0);
        $path = $isEpisode
            ? "series/{$imdbId}:{$season}:{$episode}.json"
            : "movie/{$imdbId}.json";

        try {
            $response = Http::timeout(8)->get("{$this->v3BaseUrl}/{$path}");

            if ($response->successful()) {
                $subtitles = $response->json('subtitles', []);
                $results = [];

                // Parse target languages (support comma-separated like 'ar,en')
                $targetLangs = null;
                if ($lang) {
                    $targetLangs = array_map('strtolower', array_map('trim', explode(',', $lang)));
                }

                foreach ($subtitles as $s) {
                    $langCode3 = strtolower($s['lang'] ?? 'eng');
                    $langCode2 = self::$langMap3to2[$langCode3] ?? substr($langCode3, 0, 2);

                    // If language filter provided, match against all target languages
                    if ($targetLangs) {
                        $matchFound = false;
                        foreach ($targetLangs as $target) {
                            if ($langCode2 === $target || $langCode3 === $target) {
                                $matchFound = true;
                                break;
                            }
                        }
                        if (! $matchFound) {
                            continue;
                        }
                    }

                    $results[] = [
                        'provider' => 'OpenSubtitles',
                        'subtitle_id' => (string) ($s['id'] ?? uniqid()),
                        'language' => $langCode2,
                        'language_raw' => $langCode3,
                        'release' => $s['movieReleaseName'] ?? $s['subtitleFileName'] ?? '',
                        'file_name' => $s['subtitleFileName'] ?? 'subtitle.srt',
                        'download_url' => $s['url'] ?? null,
                        'downloads' => (int) ($s['g'] ?? 0),
                        'rating' => 9.0,
                    ];
                }

                return $results;
            }
        } catch (\Exception $e) {
            Log::warning("OpenSubtitles v3 search failed for '{$imdbId}': ".$e->getMessage());
        }

        return [];
    }

    /**
     * Search via the Official OpenSubtitles.com REST API (requires API key, best Arabic coverage).
     */
    public function searchOpenSubtitlesREST(string $imdbId, ?string $lang = null, ?int $season = null, ?int $episode = null, ?string $query = null): array
    {
        if (empty($this->apiKey)) {
            return [];
        }

        try {
            $headers = [
                'User-Agent' => 'CreativeMediaCinema v1.0',
                'Api-Key' => $this->apiKey,
            ];

            $queryParams = [
                'languages' => $lang ?? 'ar,en',
            ];

            if (! empty($imdbId)) {
                $queryParams['imdb_id'] = preg_replace('/[^0-9]/', '', $imdbId);
            } elseif (! empty($query)) {
                $queryParams['query'] = $query;
            }

            if ($season !== null && $season > 0) {
                $queryParams['season_number'] = $season;
            }
            if ($episode !== null && $episode > 0) {
                $queryParams['episode_number'] = $episode;
            }

            $response = Http::timeout(8)->withHeaders($headers)->get("{$this->baseUrl}/subtitles", $queryParams);

            if ($response->successful()) {
                $data = $response->json('data', []);

                return array_map(function ($item) {
                    $attr = $item['attributes'] ?? [];
                    $file = $attr['files'][0] ?? [];

                    return [
                        'provider' => 'OpenSubtitles REST',
                        'subtitle_id' => (string) ($file['file_id'] ?? $item['id']),
                        'language' => $attr['language'] ?? 'en',
                        'release' => $attr['release'] ?? '',
                        'downloads' => $attr['download_count'] ?? 0,
                        'rating' => $attr['ratings'] ?? 0,
                        'file_name' => $file['file_name'] ?? 'subtitle.srt',
                        'download_url' => isset($file['file_id']) ? "{$this->baseUrl}/download/{$file['file_id']}" : null,
                    ];
                }, $data);
            }
        } catch (\Exception $e) {
            Log::warning('Official OpenSubtitles REST search failed: '.$e->getMessage());
        }

        return [];
    }

    /**
     * Download subtitle content using the official OpenSubtitles.com REST API.
     */
    public function downloadSubtitle(string|int $fileId): ?string
    {
        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $headers = [
                'User-Agent' => 'CreativeMediaCinema v1.0',
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->post("{$this->baseUrl}/download", [
                    'file_id' => (int) $fileId,
                ]);

            if ($response->successful()) {
                $link = $response->json('link');
                if ($link) {
                    $dlRes = Http::timeout(15)
                        ->withUserAgent('CreativeMediaCinema v1.0')
                        ->get($link);

                    if ($dlRes->successful()) {
                        return $dlRes->body();
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("OpenSubtitles REST download failed for file ID '{$fileId}': ".$e->getMessage());
        }

        return null;
    }

    /**
     * Unified search supporting IMDb ID, title queries, TV series, and language filtering.
     * Search priority: 1) REST API (best Arabic coverage), 2) Stremio v3 (free, limited langs)
     */
    public function searchSubtitles(array $params): array
    {
        $imdbId = $params['imdb_id'] ?? null;
        $type = $params['type'] ?? $params['media_type'] ?? 'movie';
        $season = isset($params['season_number']) ? (int) $params['season_number'] : null;
        $episode = isset($params['episode_number']) ? (int) $params['episode_number'] : null;
        $lang = $params['language'] ?? ($params['languages'] ?? null);

        // Normalize language: keep comma-separated for multi-language support
        if ($lang) {
            $lang = strtolower(trim($lang));
        }

        // 1. Resolve IMDb ID if not provided
        if (empty($imdbId) && ! empty($params['query'])) {
            $year = isset($params['year']) ? (int) $params['year'] : null;
            $imdbId = $this->resolveImdbId($params['query'], $type, $year);
        }

        $allResults = [];

        // 2. PRIMARY: Official OpenSubtitles REST API (best Arabic/multi-language coverage)
        if (! empty($this->apiKey)) {
            $restResults = $this->searchOpenSubtitlesREST(
                $imdbId ?? '',
                $lang,
                $season,
                $episode,
                $params['query'] ?? null
            );
            if (! empty($restResults)) {
                $allResults = array_merge($allResults, $restResults);
            }
        }

        // 3. SECONDARY: Stremio v3 free endpoint (supplements with direct download links)
        if (! empty($imdbId)) {
            $v3Results = $this->searchOpenSubtitlesV3($imdbId, $season, $episode, $lang);
            if (! empty($v3Results)) {
                $allResults = array_merge($allResults, $v3Results);
            }
        }

        // Deduplicate results by subtitle_id
        $seen = [];
        $deduped = [];
        foreach ($allResults as $result) {
            $key = $result['subtitle_id'] ?? uniqid();
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $deduped[] = $result;
            }
        }

        return $deduped;
    }

    public function computeFileHash(string $filePath): ?string
    {
        if (! File::exists($filePath) || File::size($filePath) < 65536) {
            return null;
        }

        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            return null;
        }

        $fileSize = filesize($filePath);
        $hash = [$fileSize & 0xFFFF, ($fileSize >> 16) & 0xFFFF, 0, 0];

        for ($i = 0; $i < 8192; $i++) {
            $tmp = unpack('v', fread($handle, 2))[1];
            $hash[0] += $tmp;
        }

        fseek($handle, $fileSize - 65536);
        for ($i = 0; $i < 8192; $i++) {
            $tmp = unpack('v', fread($handle, 2))[1];
            $hash[0] += $tmp;
        }

        fclose($handle);

        return sprintf('%04x%04x%04x%04x', $hash[3] & 0xFFFF, $hash[2] & 0xFFFF, $hash[1] & 0xFFFF, $hash[0] & 0xFFFF);
    }
}
