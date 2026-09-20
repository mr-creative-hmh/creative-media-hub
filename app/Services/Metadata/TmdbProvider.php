<?php

namespace App\Services\Metadata;

use App\Models\AppSetting;
use App\Services\Metadata\Contracts\MetadataProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TmdbProvider implements MetadataProviderInterface
{
    public static function inferCollectionFromTitle(string $title): ?string
    {
        $titleLower = strtolower($title);
        if (str_contains($titleLower, 'rings of power')) {
            return null;
        }

        $knownCollections = [
            'harry potter' => 'Harry Potter Collection',
            'lord of the rings' => 'The Lord of the Rings Collection',
            'hobbit' => 'The Hobbit Collection',
            'star wars' => 'Star Wars Collection',
            'fast & furious' => 'The Fast and the Furious Collection',
            'fast and furious' => 'The Fast and the Furious Collection',
            'fast five' => 'The Fast and the Furious Collection',
            'tokyo drift' => 'The Fast and the Furious Collection',
            'furious 7' => 'The Fast and the Furious Collection',
            'the fate of the furious' => 'The Fast and the Furious Collection',
            'mission: impossible' => 'Mission: Impossible Collection',
            'mission impossible' => 'Mission: Impossible Collection',
            'john wick' => 'John Wick Collection',
            'the matrix' => 'The Matrix Collection',
            'matrix re' => 'The Matrix Collection',
            'matrix resurrection' => 'The Matrix Collection',
            'pirates of the caribbean' => 'Pirates of the Caribbean Collection',
            'the dark knight' => 'The Dark Knight Trilogy',
            'batman begins' => 'The Dark Knight Trilogy',
            'avengers' => 'The Avengers Collection',
            'iron man' => 'Iron Man Collection',
            'captain america' => 'Captain America Collection',
            'thor' => 'Thor Collection',
            'guardians of the galaxy' => 'Guardians of the Galaxy Collection',
            'spider-man' => 'Spider-Man Collection',
            'spiderman' => 'Spider-Man Collection',
            'hunger games' => 'The Hunger Games Collection',
            'jurassic park' => 'Jurassic Park Collection',
            'jurassic world' => 'Jurassic Park Collection',
            'transformers' => 'Transformers Collection',
            'twilight' => 'The Twilight Saga Collection',
            'james bond' => 'James Bond 007 Collection',
            'die hard' => 'Die Hard Collection',
            'indiana jones' => 'Indiana Jones Collection',
            'shrek' => 'Shrek Collection',
            'despicable me' => 'Despicable Me Collection',
            'minions' => 'Minions Collection',
            'toy story' => 'Toy Story Collection',
            'the godfather' => 'The Godfather Trilogy',
            'terminator' => 'Terminator Collection',
            'alien' => 'Alien Collection',
            'predator' => 'Predator Collection',
            'planet of the apes' => 'Planet of the Apes Collection',
            'x-men' => 'X-Men Collection',
            'wolverine' => 'X-Men Collection',
            'deadpool' => 'Deadpool Collection',
            'ice age' => 'Ice Age Collection',
            'madagascar' => 'Madagascar Collection',
            'kung fu panda' => 'Kung Fu Panda Collection',
            'how to train your dragon' => 'How to Train Your Dragon Collection',
            'bad boys' => 'Bad Boys Collection',
            'rush hour' => 'Rush Hour Collection',
            'blade runner' => 'Blade Runner Collection',
            'dune' => 'Dune Collection',
            'godzilla' => 'MonsterVerse Collection',
            'kong' => 'MonsterVerse Collection',
            'back to the future' => 'Back to the Future Trilogy',
            'ip man' => 'Ip Man Collection',
            'rocky' => 'Rocky & Creed Collection',
            'creed' => 'Rocky & Creed Collection',
            "ocean's" => "Ocean's Collection",
            'the mummy' => 'The Mummy Collection',
            'saw' => 'Saw Collection',
            'scream' => 'Scream Collection',
            'final destination' => 'Final Destination Collection',
            'the conjuring' => 'The Conjuring Universe',
            'insidious' => 'Insidious Collection',
            'after' => 'After Collection',
            'kingsman' => 'Kingsman Collection',
            'fifty shades' => 'Fifty Shades Collection',
            'hotel transylvania' => 'Hotel Transylvania Collection',
            'cars' => 'Cars Collection',
            'the incredibles' => 'The Incredibles Collection',
            'monsters, inc.' => 'Monsters, Inc. Collection',
            'monsters university' => 'Monsters, Inc. Collection',
            'fantastic beasts' => 'Fantastic Beasts Collection',
            'star trek' => 'Star Trek Movies',
            'top gun' => 'Top Gun Collection',
            'gladiator' => 'Gladiator Collection',
            'knives out' => 'Knives Out Collection',
            'glass onion' => 'Knives Out Collection',
            'venom' => 'Venom Collection',
            'ghostbusters' => 'Ghostbusters Collection',
            'addams family' => 'The Addams Family Collection',
            'lethal weapon' => 'Lethal Weapon Collection',
            'beverly hills cop' => 'Beverly Hills Cop Collection',
            'nightmare on elm street' => 'A Nightmare on Elm Street Collection',
            'friday the 13th' => 'Friday the 13th Collection',
            'halloween' => 'Halloween Collection',
            'child\'s play' => 'Child\'s Play Collection',
            'chucky' => 'Child\'s Play Collection',
            'underworld' => 'Underworld Collection',
            'resident evil' => 'Resident Evil Collection',
            'the purge' => 'The Purge Collection',
            'annabelle' => 'The Conjuring Universe',
            'omar & salma' => 'Omar & Salma Collection',
            'عمر وسلمى' => 'Omar & Salma Collection',
        ];

        foreach ($knownCollections as $pattern => $colName) {
            if (str_contains($titleLower, $pattern)) {
                return $colName;
            }
        }

        return null;
    }

    protected string $baseUrl = 'https://api.themoviedb.org/3';

    protected ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    protected function getApiKey(): ?string
    {
        return $this->apiKey ?: AppSetting::get('tmdb_api_key', config('services.tmdb.key'));
    }

    public function getName(): string
    {
        return 'TMDb';
    }

    public function isConfigured(): bool
    {
        $key = $this->getApiKey();

        return ! empty($key);
    }

    public function searchMovie(string $title, ?int $year = null, string $lang = 'en'): array
    {
        $key = $this->getApiKey();
        if (! $key) {
            return [];
        }

        try {
            $response = Http::timeout(8)->get("{$this->baseUrl}/search/movie", [
                'api_key' => $key,
                'query' => $title,
                'year' => $year,
                'include_adult' => false,
            ]);

            if ($response->successful()) {
                return array_map(fn ($item) => $this->formatMovieSummary($item), $response->json('results', []));
            }
        } catch (\Exception $e) {
            Log::warning('TMDb searchMovie failed: '.$e->getMessage());
        }

        return [];
    }

    public function searchSeries(string $title, ?int $year = null, string $lang = 'en'): array
    {
        $key = $this->getApiKey();
        if (! $key) {
            return [];
        }

        try {
            $params = [
                'api_key' => $key,
                'query' => $title,
                'include_adult' => false,
            ];
            if ($year) {
                $params['first_air_date_year'] = $year;
            }

            $response = Http::retry(2, 250)->timeout(12)->get("{$this->baseUrl}/search/tv", $params);

            if ($response->successful()) {
                $results = array_map(fn ($item) => $this->formatSeriesSummary($item), $response->json('results', []));
                if (! empty($results)) {
                    return $results;
                }
            }

            // Smart fallback 1: Common spelling variations (e.g. Stewart <-> Stuart)
            $variants = [];
            if (stripos($title, 'Stewart') !== false) {
                $variants[] = preg_replace('/\bStewart\b/i', 'Stuart', $title);
            } elseif (stripos($title, 'Stuart') !== false) {
                $variants[] = preg_replace('/\bStuart\b/i', 'Stewart', $title);
            }

            foreach ($variants as $variant) {
                if ($variant && $variant !== $title) {
                    $vRes = $this->searchSeriesDirect($variant, $year, $key);
                    if (! empty($vRes)) {
                        return $vRes;
                    }
                }
            }

            // Smart fallback 2: If multi-word title, search without leading name/word
            $words = explode(' ', trim($title));
            if (count($words) >= 3) {
                $strippedFirst = implode(' ', array_slice($words, 1));
                $sRes = $this->searchSeriesDirect($strippedFirst, $year, $key);
                if (! empty($sRes)) {
                    $cleanTitleLower = strtolower($title);
                    $strippedLower = strtolower($strippedFirst);
                    $filtered = [];
                    foreach ($sRes as $cand) {
                        $candTitleLower = strtolower($cand['title'] ?? '');
                        similar_text($cleanTitleLower, $candTitleLower, $percent);
                        if ($percent >= 55 || str_contains($candTitleLower, $strippedLower)) {
                            $filtered[] = $cand;
                        }
                    }
                    if (! empty($filtered)) {
                        return $filtered;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('TMDb searchSeries failed: '.$e->getMessage());
        }

        return [];
    }

    protected function searchSeriesDirect(string $query, ?int $year, string $key): array
    {
        try {
            $params = [
                'api_key' => $key,
                'query' => $query,
                'include_adult' => false,
            ];
            if ($year) {
                $params['first_air_date_year'] = $year;
            }

            $response = Http::retry(2, 250)->timeout(12)->get("{$this->baseUrl}/search/tv", $params);
            if ($response->successful()) {
                return array_map(fn ($item) => $this->formatSeriesSummary($item), $response->json('results', []));
            }
        } catch (\Exception $e) {
        }

        return [];
    }

    /**
     * Fetch trending media (movies and TV series) from TMDb.
     */
    public function getTrendingMedia(string $mediaType = 'all', string $timeWindow = 'week'): array
    {
        $key = $this->getApiKey();
        if (! $key) {
            return [];
        }

        try {
            $endpoint = "{$this->baseUrl}/trending/{$mediaType}/{$timeWindow}";
            $response = Http::timeout(8)->get($endpoint, [
                'api_key' => $key,
            ]);

            if ($response->successful()) {
                $results = $response->json('results', []);

                return array_values(array_filter(array_map(function ($item) {
                    $type = $item['media_type'] ?? (isset($item['first_air_date']) ? 'series' : 'movie');
                    if ($type === 'movie') {
                        return $this->formatMovieSummary($item);
                    } elseif ($type === 'tv' || $type === 'series') {
                        return $this->formatSeriesSummary($item);
                    }

                    return null;
                }, $results)));
            }
        } catch (\Exception $e) {
            Log::warning('TMDb getTrendingMedia failed: '.$e->getMessage());
        }

        return [];
    }

    public function searchCollection(string $query, string $lang = 'en'): array
    {
        $key = $this->getApiKey();
        if (! $key || empty(trim($query))) {
            return [];
        }

        try {
            $response = Http::timeout(8)->get("{$this->baseUrl}/search/collection", [
                'api_key' => $key,
                'query' => trim($query),
                'language' => $lang === 'ar' ? 'ar-SA' : 'en-US',
            ]);

            if ($response->successful()) {
                $results = $response->json('results', []);

                return array_map(function ($item) {
                    return [
                        'provider' => 'TMDb',
                        'id' => (string) $item['id'],
                        'collection_id' => $item['id'],
                        'name' => $item['name'] ?? '',
                        'poster_path' => ! empty($item['poster_path']) ? "https://image.tmdb.org/t/p/w780{$item['poster_path']}" : null,
                        'backdrop_path' => ! empty($item['backdrop_path']) ? "https://image.tmdb.org/t/p/original{$item['backdrop_path']}" : null,
                        'overview' => $item['overview'] ?? '',
                    ];
                }, $results);
            }
        } catch (\Exception $e) {
            Log::warning('TMDb searchCollection failed: '.$e->getMessage());
        }

        return [];
    }

    public function getMovieDetails(string|int $id, string $lang = 'en'): ?array
    {
        $key = $this->getApiKey();
        if (! $key) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/movie/{$id}", [
                'api_key' => $key,
                'append_to_response' => 'credits,videos,keywords,translations,images',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Extract Arabic Title & Overview from translations
                $titleAr = null;
                $overviewAr = null;
                $taglineAr = null;
                if (! empty($data['translations']['translations'])) {
                    foreach ($data['translations']['translations'] as $tr) {
                        if (($tr['iso_639_1'] ?? '') === 'ar') {
                            $trData = $tr['data'] ?? [];
                            if (! empty($trData['title'])) {
                                $titleAr = $trData['title'];
                            }
                            if (! empty($trData['overview'])) {
                                $overviewAr = $trData['overview'];
                            }
                            if (! empty($trData['tagline'])) {
                                $taglineAr = $trData['tagline'];
                            }
                        }
                    }
                }

                // Available alternative artwork for Cover Studio
                $availablePosters = [];
                if (! empty($data['images']['posters'])) {
                    foreach (array_slice($data['images']['posters'], 0, 12) as $img) {
                        if (! empty($img['file_path'])) {
                            $availablePosters[] = "https://image.tmdb.org/t/p/w780{$img['file_path']}";
                        }
                    }
                }

                $availableBackdrops = [];
                if (! empty($data['images']['backdrops'])) {
                    foreach (array_slice($data['images']['backdrops'], 0, 10) as $img) {
                        if (! empty($img['file_path'])) {
                            $availableBackdrops[] = "https://image.tmdb.org/t/p/original{$img['file_path']}";
                        }
                    }
                }

                $collectionName = null;
                $collectionId = null;
                $collectionPoster = null;
                if (! empty($data['belongs_to_collection'])) {
                    $collectionId = $data['belongs_to_collection']['id'] ?? null;
                    $collectionName = $data['belongs_to_collection']['name'] ?? null;
                    if (! empty($data['belongs_to_collection']['poster_path'])) {
                        $collectionPoster = 'https://image.tmdb.org/t/p/w780'.$data['belongs_to_collection']['poster_path'];
                    }
                }
                if (empty($collectionName)) {
                    $collectionName = self::inferCollectionFromTitle($data['title'] ?? ($data['original_title'] ?? ''));
                }

                $origLang = $data['original_language'] ?? null;
                $originCountry = self::resolvePrimaryCountry($data, $origLang);

                return [
                    'provider' => 'TMDb',
                    'id' => (string) $data['id'],
                    'tmdb_id' => (string) $data['id'],
                    'imdb_id' => $data['imdb_id'] ?? null,
                    'title' => $data['title'] ?? '',
                    'original_title' => $data['original_title'] ?? '',
                    'title_ar' => $titleAr,
                    'overview' => $data['overview'] ?? '',
                    'overview_ar' => $overviewAr,
                    'tagline' => $data['tagline'] ?? null,
                    'tagline_ar' => $taglineAr,
                    'collection_name' => $collectionName,
                    'collection_id' => $collectionId,
                    'collection_poster' => $collectionPoster,
                    'original_language' => $origLang,
                    'origin_country' => $originCountry,
                    'release_year' => isset($data['release_date']) ? (int) substr($data['release_date'], 0, 4) : null,
                    'rating' => round($data['vote_average'] ?? 0, 1),
                    'vote_count' => $data['vote_count'] ?? 0,
                    'runtime_minutes' => $data['runtime'] ?? null,
                    'poster_path' => isset($data['poster_path']) ? "https://image.tmdb.org/t/p/w780{$data['poster_path']}" : null,
                    'backdrop_path' => isset($data['backdrop_path']) ? "https://image.tmdb.org/t/p/original{$data['backdrop_path']}" : null,
                    'available_posters' => $availablePosters,
                    'available_backdrops' => $availableBackdrops,
                    'genres' => array_column($data['genres'] ?? [], 'name'),
                    'trailer_url' => $this->extractTrailer($data['videos']['results'] ?? []),
                    'cast' => array_slice(array_map(fn ($c) => [
                        'name' => $c['name'],
                        'character' => $c['character'] ?? '',
                        'profile_path' => isset($c['profile_path']) ? "https://image.tmdb.org/t/p/w500{$c['profile_path']}" : null,
                        'order' => $c['order'] ?? 0,
                    ], $data['credits']['cast'] ?? []), 0, 12),
                    'directors' => array_map(fn ($d) => [
                        'name' => $d['name'],
                        'profile_path' => isset($d['profile_path']) ? "https://image.tmdb.org/t/p/w500{$d['profile_path']}" : null,
                    ], array_filter($data['credits']['crew'] ?? [], fn ($c) => ($c['job'] ?? '') === 'Director')),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('TMDb getMovieDetails failed: '.$e->getMessage());
        }

        return null;
    }

    public function getSeriesDetails(string|int $id, string $lang = 'en'): ?array
    {
        $key = $this->getApiKey();
        if (! $key) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/tv/{$id}", [
                'api_key' => $key,
                'append_to_response' => 'credits,videos,translations,images',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Extract Arabic Title & Overview from translations
                $titleAr = null;
                $overviewAr = null;
                if (! empty($data['translations']['translations'])) {
                    foreach ($data['translations']['translations'] as $tr) {
                        if (($tr['iso_639_1'] ?? '') === 'ar') {
                            $trData = $tr['data'] ?? [];
                            if (! empty($trData['name'])) {
                                $titleAr = $trData['name'];
                            }
                            if (! empty($trData['overview'])) {
                                $overviewAr = $trData['overview'];
                            }
                        }
                    }
                }

                $availablePosters = [];
                if (! empty($data['images']['posters'])) {
                    foreach (array_slice($data['images']['posters'], 0, 12) as $img) {
                        if (! empty($img['file_path'])) {
                            $availablePosters[] = "https://image.tmdb.org/t/p/w780{$img['file_path']}";
                        }
                    }
                }

                $availableBackdrops = [];
                if (! empty($data['images']['backdrops'])) {
                    foreach (array_slice($data['images']['backdrops'], 0, 10) as $img) {
                        if (! empty($img['file_path'])) {
                            $availableBackdrops[] = "https://image.tmdb.org/t/p/original{$img['file_path']}";
                        }
                    }
                }

                return [
                    'provider' => 'TMDb',
                    'id' => (string) $data['id'],
                    'tmdb_id' => (string) $data['id'],
                    'title' => $data['name'] ?? '',
                    'original_title' => $data['original_name'] ?? '',
                    'title_ar' => $titleAr,
                    'overview' => $data['overview'] ?? '',
                    'overview_ar' => $overviewAr,
                    'release_year' => isset($data['first_air_date']) ? (int) substr($data['first_air_date'], 0, 4) : null,
                    'end_year' => (! empty($data['last_air_date']) && in_array($data['status'] ?? '', ['Ended', 'Canceled'])) ? (int) substr($data['last_air_date'], 0, 4) : null,
                    'rating' => round($data['vote_average'] ?? 0, 1),
                    'status' => $data['status'] ?? 'Returning Series',
                    'network' => $data['networks'][0]['name'] ?? null,
                    'total_seasons' => $data['number_of_seasons'] ?? 1,
                    'poster_path' => isset($data['poster_path']) ? "https://image.tmdb.org/t/p/w780{$data['poster_path']}" : null,
                    'backdrop_path' => isset($data['backdrop_path']) ? "https://image.tmdb.org/t/p/original{$data['backdrop_path']}" : null,
                    'available_posters' => $availablePosters,
                    'available_backdrops' => $availableBackdrops,
                    'genres' => array_column($data['genres'] ?? [], 'name'),
                    'trailer_url' => $this->extractTrailer($data['videos']['results'] ?? []),
                    'seasons' => array_map(fn ($s) => [
                        'season_number' => $s['season_number'],
                        'title' => $s['name'],
                        'overview' => $s['overview'] ?? '',
                        'poster_path' => isset($s['poster_path']) ? "https://image.tmdb.org/t/p/w500{$s['poster_path']}" : null,
                        'air_date' => $s['air_date'] ?? null,
                        'episode_count' => $s['episode_count'] ?? 0,
                    ], $data['seasons'] ?? []),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('TMDb getSeriesDetails failed: '.$e->getMessage());
        }

        return null;
    }

    public function getSeasonEpisodes(string|int $seriesId, int $seasonNumber, string $lang = 'en'): array
    {
        $key = $this->getApiKey();
        if (! $key) {
            return [];
        }

        try {
            $response = Http::retry(2, 250)->timeout(12)->get("{$this->baseUrl}/tv/{$seriesId}/season/{$seasonNumber}", [
                'api_key' => $key,
                'language' => $lang === 'ar' ? 'ar-SA' : 'en-US',
            ]);

            if ($response->successful()) {
                return array_map(fn ($ep) => [
                    'episode_number' => (int) $ep['episode_number'],
                    'title' => ! empty($ep['name']) ? trim($ep['name']) : "Episode {$ep['episode_number']}",
                    'overview' => $ep['overview'] ?? '',
                    'still_path' => isset($ep['still_path']) && $ep['still_path'] ? "https://image.tmdb.org/t/p/w500{$ep['still_path']}" : null,
                    'air_date' => $ep['air_date'] ?? null,
                    'rating' => round($ep['vote_average'] ?? 0, 1),
                    'runtime_minutes' => $ep['runtime'] ?? null,
                ], $response->json('episodes', []));
            }
        } catch (\Exception $e) {
            Log::warning('TMDb getSeasonEpisodes failed: '.$e->getMessage());
        }

        return [];
    }

    /**
     * Fetch season episodes with bilingual English + Arabic metadata and artwork.
     */
    public function getSeasonEpisodesBilingual(string|int $seriesId, int $seasonNumber): array
    {
        $key = $this->getApiKey();
        if (! $key) {
            return [];
        }

        try {
            // 1. Fetch English details
            $resEn = Http::retry(2, 250)->timeout(12)->get("{$this->baseUrl}/tv/{$seriesId}/season/{$seasonNumber}", [
                'api_key' => $key,
                'language' => 'en-US',
            ]);

            if (! $resEn->successful()) {
                return [];
            }

            // 2. Fetch Arabic details
            $resAr = Http::retry(2, 250)->timeout(12)->get("{$this->baseUrl}/tv/{$seriesId}/season/{$seasonNumber}", [
                'api_key' => $key,
                'language' => 'ar-SA',
            ]);

            $arEps = [];
            if ($resAr->successful()) {
                foreach ($resAr->json('episodes', []) as $ep) {
                    $arEps[(int) $ep['episode_number']] = $ep;
                }
            }

            $results = [];
            foreach ($resEn->json('episodes', []) as $ep) {
                $epNum = (int) $ep['episode_number'];
                $arEp = $arEps[$epNum] ?? null;

                $titleEn = trim($ep['name'] ?? '');
                $titleAr = trim($arEp['name'] ?? '');

                if (empty($titleAr) || $titleAr === $titleEn || preg_match('/^(?:Episode|الحلقة)\s*\d+$/i', $titleAr)) {
                    $titleAr = null;
                }

                $overviewEn = trim($ep['overview'] ?? '');
                $overviewAr = trim($arEp['overview'] ?? '');
                if (empty($overviewAr) || $overviewAr === $overviewEn) {
                    $overviewAr = null;
                }

                $results[$epNum] = [
                    'episode_number' => $epNum,
                    'title' => ! empty($titleEn) ? $titleEn : "Episode {$epNum}",
                    'title_ar' => $titleAr,
                    'overview' => ! empty($overviewEn) ? $overviewEn : null,
                    'overview_ar' => $overviewAr,
                    'still_path' => ! empty($ep['still_path']) ? "https://image.tmdb.org/t/p/w500{$ep['still_path']}" : null,
                    'rating' => round($ep['vote_average'] ?? 0, 1),
                    'air_date' => $ep['air_date'] ?? null,
                    'runtime_minutes' => $ep['runtime'] ?? null,
                ];
            }

            return $results;
        } catch (\Exception $e) {
            Log::warning("TMDb getSeasonEpisodesBilingual failed for {$seriesId} S{$seasonNumber}: ".$e->getMessage());
        }

        return [];
    }

    /**
     * Fetch complete collection (franchise) details including parts.
     */
    public function getCollectionDetails(string|int $collectionId, string $lang = 'en'): ?array
    {
        $key = $this->getApiKey();
        if (! $key) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/collection/{$collectionId}", [
                'api_key' => $key,
                'language' => $lang === 'ar' ? 'ar-SA' : 'en-US',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $parts = [];
                foreach ($data['parts'] ?? [] as $p) {
                    $parts[] = [
                        'id' => $p['id'],
                        'tmdb_id' => $p['id'],
                        'title' => $p['title'] ?? ($p['original_title'] ?? ''),
                        'original_title' => $p['original_title'] ?? '',
                        'release_year' => ! empty($p['release_date']) ? (int) substr($p['release_date'], 0, 4) : null,
                        'release_date' => $p['release_date'] ?? null,
                        'overview' => $p['overview'] ?? '',
                        'poster_path' => ! empty($p['poster_path']) ? "https://image.tmdb.org/t/p/w500{$p['poster_path']}" : null,
                        'backdrop_path' => ! empty($p['backdrop_path']) ? "https://image.tmdb.org/t/p/w1280{$p['backdrop_path']}" : null,
                        'rating' => round($p['vote_average'] ?? 0, 1),
                    ];
                }

                return [
                    'id' => $data['id'],
                    'name' => $data['name'] ?? '',
                    'overview' => $data['overview'] ?? '',
                    'poster_path' => ! empty($data['poster_path']) ? "https://image.tmdb.org/t/p/w780{$data['poster_path']}" : null,
                    'backdrop_path' => ! empty($data['backdrop_path']) ? "https://image.tmdb.org/t/p/w1280{$data['backdrop_path']}" : null,
                    'parts' => $parts,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("TMDb getCollectionDetails failed for {$collectionId}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Find a movie or TV series on TMDb using an external ID (e.g., IMDb ID tt1375666).
     */
    public function findByExternalId(string $externalId, string $type = 'movie'): ?array
    {
        $key = $this->getApiKey();
        if (! $key) {
            return null;
        }

        $cleanId = trim($externalId);
        if (preg_match('/(tt\d+)/i', $cleanId, $m)) {
            $cleanId = strtolower($m[1]);
        }

        try {
            $response = Http::timeout(8)->get("{$this->baseUrl}/find/{$cleanId}", [
                'api_key' => $key,
                'external_source' => 'imdb_id',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($type === 'series') {
                    $results = $data['tv_results'] ?? [];
                    if (empty($results)) {
                        $results = $data['movie_results'] ?? [];
                    }
                } else {
                    $results = $data['movie_results'] ?? [];
                    if (empty($results)) {
                        $results = $data['tv_results'] ?? [];
                    }
                }

                if (! empty($results[0]['id'])) {
                    return $results[0];
                }
            }
        } catch (\Exception $e) {
            Log::warning("TMDb findByExternalId failed for {$cleanId}: ".$e->getMessage());
        }

        return null;
    }

    protected function formatMovieSummary(array $item): array
    {
        $genreIds = $item['genre_ids'] ?? [];
        $isAnimated = in_array(16, $genreIds)
            || str_contains(strtolower($item['title'] ?? ''), 'animated')
            || str_contains(strtolower($item['overview'] ?? ''), 'animated');

        return [
            'provider' => 'TMDb',
            'id' => (string) $item['id'],
            'tmdb_id' => (string) $item['id'],
            'media_type' => 'movie',
            'title' => $item['title'] ?? '',
            'original_title' => $item['original_title'] ?? '',
            'release_year' => isset($item['release_date']) ? (int) substr($item['release_date'], 0, 4) : null,
            'overview' => $item['overview'] ?? '',
            'poster_path' => isset($item['poster_path']) ? "https://image.tmdb.org/t/p/w500{$item['poster_path']}" : null,
            'backdrop_path' => isset($item['backdrop_path']) ? "https://image.tmdb.org/t/p/w1280{$item['backdrop_path']}" : null,
            'rating' => round($item['vote_average'] ?? 0, 1),
            'vote_count' => (int) ($item['vote_count'] ?? 0),
            'original_language' => $item['original_language'] ?? null,
            'genre_ids' => $genreIds,
            'is_animated' => $isAnimated,
        ];
    }

    protected function formatSeriesSummary(array $item): array
    {
        $genreIds = $item['genre_ids'] ?? [];
        $isAnimated = in_array(16, $genreIds)
            || str_contains(strtolower($item['name'] ?? ''), 'animated')
            || str_contains(strtolower($item['overview'] ?? ''), 'animated series')
            || str_contains(strtolower($item['overview'] ?? ''), 'anime');

        return [
            'provider' => 'TMDb',
            'id' => (string) $item['id'],
            'tmdb_id' => (string) $item['id'],
            'media_type' => 'series',
            'title' => $item['name'] ?? '',
            'original_title' => $item['original_name'] ?? '',
            'release_year' => isset($item['first_air_date']) ? (int) substr($item['first_air_date'], 0, 4) : null,
            'overview' => $item['overview'] ?? '',
            'poster_path' => isset($item['poster_path']) ? "https://image.tmdb.org/t/p/w500{$item['poster_path']}" : null,
            'backdrop_path' => isset($item['backdrop_path']) ? "https://image.tmdb.org/t/p/w1280{$item['backdrop_path']}" : null,
            'rating' => round($item['vote_average'] ?? 0, 1),
            'vote_count' => (int) ($item['vote_count'] ?? 0),
            'original_language' => $item['original_language'] ?? null,
            'genre_ids' => $genreIds,
            'is_animated' => $isAnimated,
        ];
    }

    protected function extractTrailer(array $videos): ?string
    {
        foreach ($videos as $v) {
            if (($v['site'] ?? '') === 'YouTube' && in_array($v['type'] ?? '', ['Trailer', 'Teaser'])) {
                return "https://www.youtube.com/watch?v={$v['key']}";
            }
        }

        return null;
    }

    /**
     * Intelligently resolve the primary origin country by correlating original_language
     * with production_countries and origin_country, preventing offshore/subcontracted
     * vendor locations from overriding the true country of origin.
     */
    public static function resolvePrimaryCountry(array $data, ?string $origLang): ?string
    {
        $productionCountries = array_column($data['production_countries'] ?? [], 'iso_3166_1');
        $originCountries = (array) ($data['origin_country'] ?? []);
        $allCountries = array_unique(array_filter(array_merge($productionCountries, $originCountries)));

        if ($origLang) {
            $langToCountries = [
                'es' => ['ES', 'MX', 'AR', 'CO', 'CL', 'PE'],
                'fr' => ['FR', 'BE', 'CA', 'CH'],
                'de' => ['DE', 'AT', 'CH'],
                'it' => ['IT'],
                'ja' => ['JP'],
                'ko' => ['KR'],
                'hi' => ['IN'],
                'te' => ['IN'],
                'ta' => ['IN'],
                'ml' => ['IN'],
                'kn' => ['IN'],
                'mr' => ['IN'],
                'bn' => ['IN', 'BD'],
                'pa' => ['IN', 'PK'],
                'ur' => ['PK', 'IN'],
                'tr' => ['TR'],
                'ru' => ['RU'],
                'zh' => ['CN', 'HK', 'TW'],
                'cn' => ['CN', 'HK'],
                'ar' => ['EG', 'SA', 'SY', 'LB', 'AE', 'KW', 'JO', 'MA', 'IQ', 'TN', 'DZ'],
                'en' => ['US', 'GB', 'AU', 'CA', 'NZ', 'IE'],
                'pt' => ['BR', 'PT'],
                'da' => ['DK'],
                'sv' => ['SE'],
                'no' => ['NO'],
                'nl' => ['NL', 'BE'],
                'pl' => ['PL'],
            ];

            if (isset($langToCountries[$origLang])) {
                foreach ($langToCountries[$origLang] as $c) {
                    if (in_array($c, $allCountries, true)) {
                        return $c;
                    }
                }
            }
        }

        return ! empty($data['production_countries'][0]['iso_3166_1'])
            ? $data['production_countries'][0]['iso_3166_1']
            : (! empty($data['origin_country'][0]) ? $data['origin_country'][0] : null);
    }
}
