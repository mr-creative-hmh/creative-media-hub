<?php

namespace App\Services\Subtitles;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YtsSubsService
{
    protected string $baseUrl = 'https://yts-subs.com';

    protected string $dlBaseUrl = 'https://subtitles.yts-subs.com/subtitles';

    protected static array $nameToCode = [
        'arabic' => 'ar',
        'english' => 'en',
        'spanish' => 'es',
        'french' => 'fr',
        'german' => 'de',
        'italian' => 'it',
        'portuguese' => 'pt',
        'brazilian portuguese' => 'pt',
        'brazillian portuguese' => 'pt',
        'russian' => 'ru',
        'turkish' => 'tr',
        'indonesian' => 'id',
        'malay' => 'ms',
        'chinese' => 'zh',
        'japanese' => 'ja',
        'korean' => 'ko',
        'hindi' => 'hi',
        'urdu' => 'ur',
        'dutch' => 'nl',
        'polish' => 'pl',
        'swedish' => 'sv',
        'norwegian' => 'no',
        'danish' => 'da',
        'finnish' => 'fi',
        'greek' => 'el',
        'hebrew' => 'he',
        'czech' => 'cs',
        'romanian' => 'ro',
        'hungarian' => 'hu',
        'bulgarian' => 'bg',
        'croatian' => 'hr',
        'serbian' => 'sr',
        'slovak' => 'sk',
        'ukrainian' => 'uk',
        'vietnamese' => 'vi',
        'thai' => 'th',
    ];

    /**
     * Search movie subtitles on YTS-Subs without requiring an API key.
     */
    public function searchSubtitles(string|array $imdbId, string|array $languages = ['ar', 'en']): array
    {
        if (is_array($imdbId)) {
            $params = $imdbId;
            $imdbId = (string) ($params['imdb_id'] ?? $params['id'] ?? '');
            $languages = $params['language'] ?? $params['languages'] ?? ['ar', 'en'];
        }

        if (empty($imdbId)) {
            return [];
        }

        if (! str_starts_with($imdbId, 'tt') && is_numeric($imdbId)) {
            $imdbId = 'tt'.str_pad($imdbId, 7, '0', STR_PAD_LEFT);
        }

        // Convert target languages to 2-letter codes
        $langList = is_array($languages)
            ? $languages
            : array_map('trim', explode(',', strtolower($languages)));

        $targetCodes = array_map('strtolower', $langList);

        $url = "{$this->baseUrl}/movie-imdb/{$imdbId}";

        try {
            $response = Http::timeout(6)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                ->get($url);

            if (! $response->successful()) {
                return [];
            }

            $html = $response->body();
            if (empty($html) || ! str_contains($html, 'sub-lang')) {
                return [];
            }

            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            $rows = $xpath->query('//tr[contains(@class, "high-rating") or contains(@class, "sub-row") or .//span[contains(@class, "sub-lang")]]');
            $results = [];

            foreach ($rows as $row) {
                // Extract language
                $langNode = $xpath->query('.//span[contains(@class, "sub-lang")]', $row)->item(0);
                if (! $langNode) {
                    continue;
                }

                $rawLangName = strtolower(trim($langNode->textContent));
                $code2 = self::$nameToCode[$rawLangName] ?? substr($rawLangName, 0, 2);

                // Check language filter
                if (! empty($targetCodes) && ! in_array($code2, $targetCodes, true)) {
                    continue;
                }

                // Extract link and slug
                $linkNode = $xpath->query('.//a[contains(@href, "/subtitles/")]', $row)->item(0);
                if (! $linkNode) {
                    continue;
                }

                $href = $linkNode->getAttribute('href');
                if (! preg_match('#/subtitles/([^/"]+)#i', $href, $m)) {
                    continue;
                }
                $slug = $m[1];

                // Extract rating
                $ratingNode = $xpath->query('.//span[contains(@class, "label")]', $row)->item(0);
                $rating = $ratingNode ? (float) trim($ratingNode->textContent) : 1.0;

                // Extract uploader or release title
                $titleText = trim($linkNode->textContent);
                $titleText = preg_replace('/^subtitle\s+/i', '', $titleText);

                $results[] = [
                    'provider' => 'YTS Subtitles',
                    'subtitle_id' => "yts_{$slug}",
                    'language' => $code2,
                    'language_raw' => $rawLangName,
                    'release' => $slug,
                    'file_name' => "{$slug}.srt",
                    'download_url' => "{$this->dlBaseUrl}/{$slug}.zip",
                    'source' => 'yts-subs',
                    'format' => 'srt',
                    'downloads' => 600,
                    'rating' => max(8.0, min(10.0, 8.0 + ($rating * 0.4))),
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            Log::warning("YTS-Subs search failed for '{$imdbId}': ".$e->getMessage());

            return [];
        }
    }
}
