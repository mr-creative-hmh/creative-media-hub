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
        if (! empty($details['overview'])) {
            return $details['overview'];
        }

        // If not found in requested lang, search first
        $searchResults = $this->searchWiki($title, $lang === 'ar' ? 'فيلم' : 'film', $lang);
        if (! empty($searchResults[0]['id'])) {
            $details = $this->fetchWikiSummary($searchResults[0]['id'], $lang);

            return $details['overview'] ?? null;
        }

        return null;
    }

    public function searchFranchise(string $title): ?string
    {
        $clean = trim($title);
        if (strlen($clean) < 3) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'CreativeMediaHub/1.0 (contact@creativemediahub.local)',
            ])->timeout(2.5)->get('https://en.wikipedia.org/w/api.php', [
                'action' => 'query',
                'list' => 'search',
                'srsearch' => "\"{$clean}\" franchise OR \"film series\"",
                'format' => 'json',
                'utf8' => 1,
            ]);

            if ($response->successful()) {
                $items = $response->json('query.search', []);
                $cleanLower = strtolower($clean);

                foreach ($items as $item) {
                    $itemTitle = $item['title'] ?? '';
                    if (preg_match('/^([a-zA-Z0-9\s\':\-\.]+?)\s+\((?:film series|franchise)\)$/i', $itemTitle, $m)) {
                        $candidate = trim($m[1]);
                        $candLower = strtolower($candidate);

                        // Strict verification: Candidate must match the query title/stem
                        if ($candLower === $cleanLower || str_contains($candLower, $cleanLower) || str_contains($cleanLower, $candLower)) {
                            return $candidate.' Collection';
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Wikipedia searchFranchise failed: '.$e->getMessage());
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
            Log::warning('Wikipedia searchWiki failed: '.$e->getMessage());
        }

        return [];
    }

    protected function fetchWikiSummary(string $pageTitle, string $lang): ?array
    {
        $domain = $lang === 'ar' ? 'ar.wikipedia.org' : 'en.wikipedia.org';

        try {
            $response = Http::timeout(5)->get("https://{$domain}/api/rest_v1/page/summary/".urlencode($pageTitle));

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
            Log::warning('Wikipedia fetchWikiSummary failed: '.$e->getMessage());
        }

        return null;
    }
}
