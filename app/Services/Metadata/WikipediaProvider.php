<?php

namespace App\Services\Metadata;

use App\Services\Metadata\Contracts\MetadataProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikipediaProvider implements MetadataProviderInterface
{
    public function getName(): string
    {
        return 'Wikipedia / Wikidata (Multilingual Arabic & English)';
    }

    public function searchMovie(string $title, ?int $year = null, string $lang = 'en'): array
    {
        return $this->searchWiki($title, 'film', $lang);
    }

    public function searchSeries(string $title, ?int $year = null, string $lang = 'en'): array
    {
        return $this->searchWiki($title, 'TV series', $lang);
    }

    public function getMovieDetails(string|int $id, string $lang = 'en'): ?array
    {
        return $this->fetchWikiSummary((string) $id, $lang);
    }

    public function getSeriesDetails(string|int $id, string $lang = 'en'): ?array
    {
        return $this->fetchWikiSummary((string) $id, $lang);
    }

    public function getSeasonEpisodes(string|int $seriesId, int $seasonNumber, string $lang = 'en'): array
    {
        return [];
    }

    public function getPlotSummary(string $title, string $lang = 'ar'): ?string
    {
        $details = $this->fetchWikiSummary($title, $lang);
        if (!empty($details['overview'])) {
            return $details['overview'];
        }

        // If not found in requested lang, search first
        $searchResults = $this->searchWiki($title, $lang === 'ar' ? 'فيلم' : 'film', $lang);
        if (!empty($searchResults[0]['id'])) {
            $details = $this->fetchWikiSummary($searchResults[0]['id'], $lang);
            return $details['overview'] ?? null;
        }

        return null;
    }

    protected function searchWiki(string $title, string $tag, string $lang): array
    {
        $domain = $lang === 'ar' ? 'ar.wikipedia.org' : 'en.wikipedia.org';

        try {
            $query = "{$title} {$tag}";
            $response = Http::timeout(5)->get("https://{$domain}/w/api.php", [
                'action' => 'opensearch',
                'search' => $query,
                'limit' => 3,
                'namespace' => 0,
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $titles = $response->json(1, []);
                $results = [];
                foreach ($titles as $t) {
                    $results[] = [
                        'provider' => 'Wikipedia',
                        'id' => $t,
                        'title' => $t,
                    ];
                }
                return $results;
            }
        } catch (\Exception $e) {
            Log::warning("Wikipedia searchWiki failed: " . $e->getMessage());
        }

        return [];
    }

    protected function fetchWikiSummary(string $pageTitle, string $lang): ?array
    {
        $domain = $lang === 'ar' ? 'ar.wikipedia.org' : 'en.wikipedia.org';

        try {
            $response = Http::timeout(5)->get("https://{$domain}/api/rest_v1/page/summary/" . urlencode($pageTitle));

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'provider' => 'Wikipedia',
                    'title' => $data['title'] ?? '',
                    'overview' => $data['extract'] ?? '',
                    'poster_path' => $data['thumbnail']['source'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::warning("Wikipedia fetchWikiSummary failed: " . $e->getMessage());
        }

        return null;
    }
}
