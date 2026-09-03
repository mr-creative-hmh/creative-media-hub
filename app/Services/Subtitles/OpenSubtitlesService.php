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
     * Resolve title to IMDb ID via Cinemeta catalog.
     */
    public function resolveImdbId(string $query, string $type = 'movie', ?int $year = null): ?string
    {
        $cleanQuery = trim($query);
        // Remove season/episode codes like S01E04 or 1080p, etc.
        $cleanQuery = preg_replace('/\s*[-_]?\s*S\d+E\d+.*$/i', '', $cleanQuery);
        $cleanQuery = preg_replace('/\s*\(\d{4}\).*$/', '', $cleanQuery);
        $cleanQuery = trim($cleanQuery);

        if (empty($cleanQuery)) {
            return null;
        }

        $catalogType = ($type === 'series' || $type === 'tv' || $type === 'episode') ? 'series' : 'movie';

        try {
            $encoded = rawurlencode($cleanQuery);
            $response = Http::timeout(6)->get("{$this->cinemetaUrl}/{$catalogType}/top/search={$encoded}.json");

            if ($response->successful()) {
                $metas = $response->json('metas', []);
                if (! empty($metas)) {
                    // Try to match by release year if available
                    if ($year) {
                        foreach ($metas as $meta) {
                            $metaYear = (int) substr($meta['year'] ?? '', 0, 4);
                            if ($metaYear > 0 && abs($metaYear - $year) <= 1) {
                                return $meta['imdb_id'] ?? $meta['id'] ?? null;
                            }
                        }
                    }

                    // Fallback to top ranked result
                    return $metas[0]['imdb_id'] ?? $metas[0]['id'] ?? null;
                }
            }
        } catch (\Exception $e) {
            Log::warning("Cinemeta IMDb resolution failed for '{$cleanQuery}': ".$e->getMessage());
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

                foreach ($subtitles as $s) {
                    $langCode3 = strtolower($s['lang'] ?? 'eng');
                    $langCode2 = self::$langMap3to2[$langCode3] ?? substr($langCode3, 0, 2);

                    // If language filter provided, match either 2-letter or 3-letter code
                    if ($lang) {
                        $targetLang = strtolower($lang);
                        if ($langCode2 !== $targetLang && $langCode3 !== $targetLang) {
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
     * Unified search supporting IMDb ID, title queries, TV series, and language filtering.
     */
    public function searchSubtitles(array $params): array
    {
        $imdbId = $params['imdb_id'] ?? null;
        $type = $params['type'] ?? $params['media_type'] ?? 'movie';
        $season = isset($params['season_number']) ? (int) $params['season_number'] : null;
        $episode = isset($params['episode_number']) ? (int) $params['episode_number'] : null;
        $lang = $params['language'] ?? ($params['languages'] ?? null);

        // If languages is comma separated, pick primary or handle string
        if ($lang && str_contains($lang, ',')) {
            $lang = explode(',', $lang)[0];
        }

        // 1. Resolve IMDb ID if not provided
        if (empty($imdbId) && ! empty($params['query'])) {
            $year = isset($params['year']) ? (int) $params['year'] : null;
            $imdbId = $this->resolveImdbId($params['query'], $type, $year);
        }

        // 2. Query OpenSubtitles v3 with IMDb ID
        if (! empty($imdbId)) {
            $results = $this->searchOpenSubtitlesV3($imdbId, $season, $episode, $lang);
            if (! empty($results)) {
                return $results;
            }
        }

        // 3. Fallback: Official OpenSubtitles REST API (if user has an API Key configured)
        if (! empty($this->apiKey)) {
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
                } elseif (! empty($params['query'])) {
                    $queryParams['query'] = $params['query'];
                }

                if ($season !== null && $season > 0) {
                    $queryParams['season_number'] = $season;
                }
                if ($episode !== null && $episode > 0) {
                    $queryParams['episode_number'] = $episode;
                }

                $response = Http::timeout(6)->withHeaders($headers)->get("{$this->baseUrl}/subtitles", $queryParams);

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
        }

        return [];
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
