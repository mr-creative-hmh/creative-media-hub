<?php

namespace App\Services\Subtitles;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubSenseService
{
    protected string $baseUrl = 'https://subsense.nepiraw.com';

    protected static array $langMap2to3 = [
        'ar' => 'ara',
        'en' => 'eng',
        'fr' => 'fre',
        'es' => 'spa',
        'de' => 'ger',
        'it' => 'ita',
        'tr' => 'tur',
        'pt' => 'por',
        'ru' => 'rus',
        'hi' => 'hin',
        'ja' => 'jpn',
        'ko' => 'kor',
        'zh' => 'chi',
        'nl' => 'nld',
        'pl' => 'pol',
        'sv' => 'swe',
        'el' => 'gre',
        'he' => 'heb',
        'id' => 'ind',
    ];

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
        'ko' => 'ko',
        'chi' => 'zh',
        'zho' => 'zh',
        'nld' => 'nl',
        'dut' => 'nl',
        'pol' => 'pl',
        'swe' => 'sv',
        'gre' => 'el',
        'ell' => 'el',
        'heb' => 'he',
        'ind' => 'id',
    ];

    /**
     * Search subtitles via SubSense multi-source aggregator without requiring an API key.
     * Aggregates OpenSubtitles, SubDL, SubSource, Yts-subs, Gestdown, AnimeTosho.
     */
    public function searchSubtitles(
        string|array $imdbId,
        string $type = 'movie',
        ?int $season = null,
        ?int $episode = null,
        string|array $languages = ['ar', 'en'],
        int $maxPerLang = 20
    ): array {
        if (is_array($imdbId)) {
            $params = $imdbId;
            $imdbId = (string) ($params['imdb_id'] ?? $params['id'] ?? '');
            $type = $params['type'] ?? 'movie';
            $season = isset($params['season_number']) ? (int) $params['season_number'] : ($params['season'] ?? null);
            $episode = isset($params['episode_number']) ? (int) $params['episode_number'] : ($params['episode'] ?? null);
            $languages = $params['language'] ?? $params['languages'] ?? ['ar', 'en'];
            $maxPerLang = (int) ($params['max'] ?? $maxPerLang);
        }

        if (empty($imdbId)) {
            return [];
        }

        // Standardize IMDb ID format (e.g. tt0241527)
        if (! str_starts_with($imdbId, 'tt') && is_numeric($imdbId)) {
            $imdbId = 'tt'.str_pad($imdbId, 7, '0', STR_PAD_LEFT);
        }

        // Convert languages to array of 3-letter codes
        $langList = is_array($languages)
            ? $languages
            : array_map('trim', explode(',', strtolower($languages)));

        $lang3Codes = [];
        foreach ($langList as $l) {
            $l = strtolower(trim($l));
            if (strlen($l) === 2) {
                $lang3Codes[] = self::$langMap2to3[$l] ?? $l;
            } elseif (strlen($l) === 3) {
                $lang3Codes[] = $l;
            }
        }
        $lang3Codes = array_values(array_unique($lang3Codes));

        if (empty($lang3Codes)) {
            $lang3Codes = ['ara', 'eng'];
        }

        // Construct SubSense encoded config
        $configObj = [
            'languages' => $lang3Codes,
            'maxSubtitles' => $maxPerLang,
        ];
        $configStr = rawurlencode(json_encode($configObj));

        $isEpisode = ($type === 'series' || $type === 'episode' || $type === 'tv')
            && $season !== null && $episode !== null && $season > 0 && $episode > 0;

        $path = $isEpisode
            ? "subtitles/series/{$imdbId}:{$season}:{$episode}.json"
            : "subtitles/movie/{$imdbId}.json";

        $url = "{$this->baseUrl}/{$configStr}/{$path}";

        try {
            $response = Http::timeout(8)
                ->withUserAgent('CreativeMediaHub/1.0 (Windows NT 10.0; Win64; x64)')
                ->get($url);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();
            $subtitles = $data['subtitles'] ?? [];
            $results = [];

            foreach ($subtitles as $s) {
                $rawLang = strtolower($s['lang'] ?? 'eng');
                $code2 = self::$langMap3to2[$rawLang] ?? substr($rawLang, 0, 2);

                $sourceName = $s['source'] ?? 'Aggregator';
                // Clean source label
                $displaySource = match (strtolower($sourceName)) {
                    'opensubtitles' => 'OpenSubtitles',
                    'subdl' => 'SubDL',
                    'subsource' => 'SubSource',
                    'yts-subs', 'yify' => 'YTS Subtitles',
                    'gestdown' => 'Gestdown',
                    'animetosho' => 'AnimeTosho',
                    default => ucfirst($sourceName),
                };

                $rel = $s['releaseName'] ?? $s['label'] ?? $s['fileName'] ?? '';
                $cleanRel = preg_replace('/^(OpenSubtitles|SubDL|SubSource|Yts-subs)\s*·\s*\[[A-Z]+\]\s*·\s*/i', '', $rel);

                $isAss = str_contains(strtolower($s['url'] ?? ''), '.ass') || str_contains(strtolower($s['url'] ?? ''), '.ssa');
                $isVtt = str_contains(strtolower($s['id'] ?? ''), 'vtt') || str_contains(strtolower($s['url'] ?? ''), '.vtt');

                $results[] = [
                    'provider' => "SubSense ({$displaySource})",
                    'subtitle_id' => (string) ($s['id'] ?? uniqid('subsense_')),
                    'language' => $code2,
                    'language_raw' => $rawLang,
                    'title' => $cleanRel ?: ($s['fileName'] ?? 'Subtitle Release'),
                    'release' => $cleanRel ?: ($s['fileName'] ?? 'Subtitle Release'),
                    'file_name' => $s['fileName'] ?? ($cleanRel ? "{$cleanRel}.srt" : 'subtitle.srt'),
                    'download_url' => $s['url'] ?? null,
                    'source' => $sourceName,
                    'format' => $isVtt ? 'vtt' : ($isAss ? 'ass' : 'srt'),
                    'downloads' => (int) ($s['downloads'] ?? 850),
                    'rating' => 9.5,
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            Log::warning("SubSense search failed for '{$imdbId}': ".$e->getMessage());

            return [];
        }
    }
}
