<?php

namespace App\Services\Metadata;

use App\Services\Metadata\Contracts\MetadataProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetadataAggregator
{
    /** @var MetadataProviderInterface[] */
    protected array $providers = [];
    protected ArtworkDownloadService $artwork;

    public function __construct(
        TmdbProvider $tmdb,
        TvMazeProvider $tvmaze,
        OmdbProvider $omdb,
        AniListProvider $anilist,
        WikipediaProvider $wikipedia,
        LocalNfoProvider $local,
        ArtworkDownloadService $artwork
    ) {
        $this->providers = [
            'tmdb' => $tmdb,
            'omdb' => $omdb,
            'tvmaze' => $tvmaze,
            'anilist' => $anilist,
            'wikipedia' => $wikipedia,
            'local' => $local,
        ];
        $this->artwork = $artwork;
    }

    public function aggregateMovieMetadata(string $title, ?int $year = null, string $lang = 'en', bool $fastScan = false): array
    {
        $default = [
            'title' => $title,
            'original_title' => $title,
            'title_ar' => null,
            'collection_name' => null,
            'collection_id' => null,
            'collection_poster' => null,
            'original_language' => null,
            'origin_country' => null,
            'year' => $year,
            'tmdb_id' => null,
            'imdb_id' => null,
            'overview' => "Enjoy watching {$title}.",
            'overview_ar' => null,
            'poster_path' => null,
            'backdrop_path' => null,
            'rating' => 7.5,
            'runtime_minutes' => 110,
            'genres' => ['Action', 'Drama'],
            'director' => null,
            'cast' => [],
            'trailer_url' => null,
            'available_posters' => [],
            'available_backdrops' => [],
        ];

        // 1. Search across metadata chain (TMDb, OMDb, AniList, Wikipedia, Local)
        $searchResults = $this->searchMovie($title, $year, $lang, !$fastScan);

        if (!empty($searchResults)) {
            $first = $searchResults[0];
            $providerKey = strtolower($first['provider'] ?? 'tmdb');
            $id = $first['id'] ?? ($first['tmdb_id'] ?? null);

            $merged = array_merge($default, $first);

            if ($id) {
                $details = $this->getMovieDetails($id, $providerKey, $lang);
                if ($details) {
                    $merged = array_merge($merged, $details);
                }
            }

            if (!$fastScan) {
                // Fill missing fields from other providers in the waterfall
                $this->fillMissingFieldsFromOtherProviders($merged, $title, $year, 'movie');

                // Ensure Arabic metadata via multi-provider waterfall
                $this->ensureArabicMetadata($merged, 'movie');

                // Download & cache poster locally
                if (!empty($merged['poster_path']) && filter_var($merged['poster_path'], FILTER_VALIDATE_URL)) {
                    $merged['poster_path'] = $this->artwork->downloadPoster($merged['poster_path']);
                }
                if (!empty($merged['backdrop_path']) && filter_var($merged['backdrop_path'], FILTER_VALIDATE_URL)) {
                    $merged['backdrop_path'] = $this->artwork->downloadBackdrop($merged['backdrop_path']);
                }
            }

            $merged['year'] = $merged['release_year'] ?? ($merged['year'] ?? $year);
            $merged['release_year'] = $merged['year'];

            return $merged;
        }

        if (!$fastScan) {
            $this->ensureArabicMetadata($default, 'movie');
        }
        return $default;
    }

    public function aggregateSeriesMetadata(string $title, ?int $year = null, string $lang = 'en', bool $fastScan = false): array
    {
        $default = [
            'title' => $title,
            'original_title' => $title,
            'title_ar' => null,
            'collection_name' => null,
            'collection_id' => null,
            'collection_poster' => null,
            'original_language' => null,
            'origin_country' => null,
            'year' => $year,
            'tmdb_id' => null,
            'tvmaze_id' => null,
            'imdb_id' => null,
            'overview' => "Experience the complete story of {$title}.",
            'overview_ar' => null,
            'poster_path' => null,
            'backdrop_path' => null,
            'rating' => 8.0,
            'status' => 'Returning Series',
            'genres' => ['Drama', 'Thriller'],
            'cast' => [],
            'seasons' => [],
            'available_posters' => [],
            'available_backdrops' => [],
        ];

        // 1. Search across metadata chain (TMDb, TVMaze, OMDb, AniList, Wikipedia)
        $searchResults = $this->searchSeries($title, $year, $lang, !$fastScan);

        if (!empty($searchResults)) {
            $first = $searchResults[0];
            $providerKey = strtolower($first['provider'] ?? 'tmdb');
            $id = $first['id'] ?? ($first['tmdb_id'] ?? ($first['tvmaze_id'] ?? null));

            $merged = array_merge($default, $first);

            if ($id) {
                $details = $this->getSeriesDetails($id, $providerKey, $lang);
                if ($details) {
                    $merged = array_merge($merged, $details);
                }
            }

            if (!$fastScan) {
                // Fill missing fields from other providers in the waterfall
                $this->fillMissingFieldsFromOtherProviders($merged, $title, $year, 'series');

                // Ensure Arabic metadata via multi-provider waterfall
                $this->ensureArabicMetadata($merged, 'series');

                // Download & cache poster locally
                if (!empty($merged['poster_path']) && filter_var($merged['poster_path'], FILTER_VALIDATE_URL)) {
                    $merged['poster_path'] = $this->artwork->downloadPoster($merged['poster_path']);
                }
                if (!empty($merged['backdrop_path']) && filter_var($merged['backdrop_path'], FILTER_VALIDATE_URL)) {
                    $merged['backdrop_path'] = $this->artwork->downloadBackdrop($merged['backdrop_path']);
                }
            }

            $merged['year'] = $merged['release_year'] ?? ($merged['year'] ?? $year);
            $merged['release_year'] = $merged['year'];

            return $merged;
        }

        if (!$fastScan) {
            $this->ensureArabicMetadata($default, 'series');
        }
        return $default;
    }

    public function searchMovie(string $title, ?int $year = null, string $lang = 'en', bool $withArabicWaterfall = true): array
    {
        $chain = ['tmdb', 'omdb', 'anilist', 'wikipedia', 'local'];
        $results = [];

        foreach ($chain as $key) {
            $provider = $this->providers[$key] ?? null;
            if (!$provider) continue;

            try {
                $res = $provider->searchMovie($title, $year, $lang);
                if (!empty($res)) {
                    if ($withArabicWaterfall) {
                        foreach ($res as &$item) {
                            $this->ensureArabicMetadata($item, 'movie', false);
                        }
                    }
                    $results = array_merge($results, $res);
                    if ($key === 'tmdb' || count($results) >= 6) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Provider {$key} searchMovie failed: " . $e->getMessage());
            }
        }

        return $results;
    }

    public function searchSeries(string $title, ?int $year = null, string $lang = 'en', bool $withArabicWaterfall = true): array
    {
        $chain = ['tmdb', 'omdb', 'tvmaze', 'anilist', 'wikipedia', 'local'];
        $results = [];

        foreach ($chain as $key) {
            $provider = $this->providers[$key] ?? null;
            if (!$provider) continue;

            try {
                $res = $provider->searchSeries($title, $year, $lang);
                if (!empty($res)) {
                    if ($withArabicWaterfall) {
                        foreach ($res as &$item) {
                            $this->ensureArabicMetadata($item, 'series', false);
                        }
                    }
                    $results = array_merge($results, $res);
                    if (count($results) >= 6) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Provider {$key} searchSeries failed: " . $e->getMessage());
            }
        }

        return $results;
    }

    public function getMovieDetails(string|int $id, string $providerKey = 'tmdb', string $lang = 'en'): ?array
    {
        $providerKey = strtolower($providerKey);
        $data = null;
        if (isset($this->providers[$providerKey])) {
            try {
                $data = $this->providers[$providerKey]->getMovieDetails($id, $lang);
            } catch (\Throwable $e) {
                Log::warning("Provider {$providerKey} getMovieDetails failed: " . $e->getMessage());
            }
        }

        if (!$data) {
            foreach ($this->providers as $p) {
                try {
                    $data = $p->getMovieDetails($id, $lang);
                    if ($data) break;
                } catch (\Throwable $e) {}
            }
        }

        if ($data) {
            $this->ensureArabicMetadata($data, 'movie');
        }

        return $data;
    }

    public function getSeriesDetails(string|int $id, string $providerKey = 'tmdb', string $lang = 'en'): ?array
    {
        $providerKey = strtolower($providerKey);
        $data = null;
        if (isset($this->providers[$providerKey])) {
            try {
                $data = $this->providers[$providerKey]->getSeriesDetails($id, $lang);
            } catch (\Throwable $e) {
                Log::warning("Provider {$providerKey} getSeriesDetails failed: " . $e->getMessage());
            }
        }

        if (!$data) {
            foreach ($this->providers as $p) {
                try {
                    $data = $p->getSeriesDetails($id, $lang);
                    if ($data) break;
                } catch (\Throwable $e) {}
            }
        }

        if ($data) {
            $this->ensureArabicMetadata($data, 'series');
        }

        return $data;
    }

    public function getSeasonEpisodes(string|int $seriesId, int $seasonNumber, string $providerKey = 'tmdb', string $lang = 'en'): array
    {
        $providerKey = strtolower($providerKey);
        if (isset($this->providers[$providerKey])) {
            try {
                return $this->providers[$providerKey]->getSeasonEpisodes($seriesId, $seasonNumber, $lang);
            } catch (\Throwable $e) {}
        }

        foreach ($this->providers as $p) {
            try {
                $eps = $p->getSeasonEpisodes($seriesId, $seasonNumber, $lang);
                if (!empty($eps)) return $eps;
            } catch (\Throwable $e) {}
        }

        return [];
    }

    /**
     * Multi-Provider Waterfall: Fills any missing fields (cast, ratings, poster, backdrop, overview) from secondary providers
     */
    protected function fillMissingFieldsFromOtherProviders(array &$data, string $title, ?int $year, string $type): void
    {
        $needsPoster = empty($data['poster_path']);
        $needsBackdrop = empty($data['backdrop_path']);
        $needsOverview = empty($data['overview']) || str_starts_with($data['overview'], 'Enjoy watching') || str_starts_with($data['overview'], 'Experience the complete');
        $needsImdb = empty($data['imdb_id']);

        if (!$needsPoster && !$needsBackdrop && !$needsOverview && !$needsImdb) {
            return;
        }

        $chain = $type === 'movie' ? ['omdb', 'wikipedia', 'anilist'] : ['tvmaze', 'omdb', 'wikipedia'];
        foreach ($chain as $key) {
            $provider = $this->providers[$key] ?? null;
            if (!$provider) continue;

            try {
                $results = $type === 'movie'
                    ? $provider->searchMovie($title, $year)
                    : $provider->searchSeries($title, $year);

                if (!empty($results[0])) {
                    $match = $results[0];
                    if ($needsPoster && !empty($match['poster_path'])) {
                        $data['poster_path'] = $match['poster_path'];
                        $needsPoster = false;
                    }
                    if ($needsBackdrop && !empty($match['backdrop_path'])) {
                        $data['backdrop_path'] = $match['backdrop_path'];
                        $needsBackdrop = false;
                    }
                    if ($needsOverview && !empty($match['overview']) && !str_starts_with($match['overview'], 'Enjoy watching')) {
                        $data['overview'] = $match['overview'];
                        $needsOverview = false;
                    }
                    if ($needsImdb && !empty($match['imdb_id'])) {
                        $data['imdb_id'] = $match['imdb_id'];
                        $needsImdb = false;
                    }
                }
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Robust Multi-Provider Arabic Metadata Waterfall:
     * 1. TMDb translations
     * 2. Wikipedia / Wikidata LangLinks & Summary
     * 3. MyMemory Translated API
     */
    public function ensureArabicMetadata(array &$data, string $type = 'movie', bool $translateOverview = true): void
    {
        $title = $data['title'] ?? ($data['name'] ?? '');
        if (empty($title)) return;

        $hasArabicTitle = !empty($data['title_ar']);
        $hasArabicOverview = !empty($data['overview_ar']);

        if ($hasArabicTitle && ($hasArabicOverview || !$translateOverview)) {
            return;
        }

        // 1. Check Wikipedia / Wikidata LangLinks
        try {
            $res = Http::withHeaders(['User-Agent' => 'CreativeMediaLibrary/1.0 (contact@creativemedia.test)'])
                ->timeout(3)
                ->get("https://en.wikipedia.org/w/api.php", [
                    'action' => 'query',
                    'prop' => 'langlinks',
                    'titles' => $title,
                    'lllang' => 'ar',
                    'format' => 'json',
                ]);

            if ($res->successful()) {
                $pages = $res->json('query.pages', []);
                foreach ($pages as $p) {
                    if (!empty($p['langlinks'][0]['*'])) {
                        $wikiArTitle = $p['langlinks'][0]['*'];
                        if (!$hasArabicTitle) {
                            $data['title_ar'] = $wikiArTitle;
                            $hasArabicTitle = true;
                        }

                        if (!$hasArabicOverview && $translateOverview) {
                            $arSummary = Http::withHeaders(['User-Agent' => 'CreativeMediaLibrary/1.0 (contact@creativemedia.test)'])
                                ->timeout(3)
                                ->get("https://ar.wikipedia.org/api/rest_v1/page/summary/" . urlencode($wikiArTitle));
                            if ($arSummary->successful() && !empty($arSummary->json('extract'))) {
                                $data['overview_ar'] = $arSummary->json('extract');
                                $hasArabicOverview = true;
                            }
                        }
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {}

        // 2. MyMemory Translation for Title
        if (!$hasArabicTitle) {
            try {
                $res = Http::timeout(5)->get("https://api.mymemory.translated.net/get", [
                    'q' => $title,
                    'langpair' => 'en|ar',
                ]);
                $trans = $res->json('responseData.translatedText');
                if ($trans && strtolower(trim($trans)) !== strtolower(trim($title)) && !str_contains($trans, 'MYMEMORY WARNING')) {
                    $data['title_ar'] = $trans;
                    $hasArabicTitle = true;
                }
            } catch (\Throwable $e) {}
        }

        // 3. MyMemory Translation for Overview
        if (!$hasArabicOverview && $translateOverview && !empty($data['overview'])) {
            try {
                $cleanOverview = substr(strip_tags($data['overview']), 0, 450);
                if (!str_starts_with($cleanOverview, 'Enjoy watching') && !str_starts_with($cleanOverview, 'Experience the complete')) {
                    $res = Http::timeout(5)->get("https://api.mymemory.translated.net/get", [
                        'q' => $cleanOverview,
                        'langpair' => 'en|ar',
                    ]);
                    $trans = $res->json('responseData.translatedText');
                    if ($trans && !str_contains($trans, 'MYMEMORY WARNING')) {
                        $data['overview_ar'] = $trans;
                        $hasArabicOverview = true;
                    }
                }
            } catch (\Throwable $e) {}
        }
    }

    public function getArtworkService(): ArtworkDownloadService
    {
        return $this->artwork;
    }

    public function getProvidersList(): array
    {
        $list = [];
        foreach ($this->providers as $key => $provider) {
            $list[$key] = [
                'key' => $key,
                'name' => $provider->getName(),
            ];
        }
        return $list;
    }
}
