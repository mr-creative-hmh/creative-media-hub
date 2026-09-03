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
            'minions' => 'Despicable Me Collection',
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
            $response = Http::timeout(8)->get("{$this->baseUrl}/search/tv", [
                'api_key' => $key,
                'query' => $title,
                'first_air_date_year' => $year,
                'include_adult' => false,
            ]);

            if ($response->successful()) {
                return array_map(fn ($item) => $this->formatSeriesSummary($item), $response->json('results', []));
            }
        } catch (\Exception $e) {
            Log::warning('TMDb searchSeries failed: '.$e->getMessage());
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
                $originCountry = ! empty($data['production_countries'][0]['iso_3166_1'])
                    ? $data['production_countries'][0]['iso_3166_1']
                    : (! empty($data['origin_country'][0]) ? $data['origin_country'][0] : null);

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
            $response = Http::timeout(8)->get("{$this->baseUrl}/tv/{$seriesId}/season/{$seasonNumber}", [
                'api_key' => $key,
            ]);

            if ($response->successful()) {
                return array_map(fn ($ep) => [
                    'episode_number' => $ep['episode_number'],
                    'title' => $ep['name'] ?? "Episode {$ep['episode_number']}",
                    'overview' => $ep['overview'] ?? '',
                    'still_path' => isset($ep['still_path']) ? "https://image.tmdb.org/t/p/w500{$ep['still_path']}" : null,
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
        return [
            'provider' => 'TMDb',
            'id' => (string) $item['id'],
            'tmdb_id' => (string) $item['id'],
            'title' => $item['title'] ?? '',
            'original_title' => $item['original_title'] ?? '',
            'release_year' => isset($item['release_date']) ? (int) substr($item['release_date'], 0, 4) : null,
            'overview' => $item['overview'] ?? '',
            'poster_path' => isset($item['poster_path']) ? "https://image.tmdb.org/t/p/w500{$item['poster_path']}" : null,
            'backdrop_path' => isset($item['backdrop_path']) ? "https://image.tmdb.org/t/p/w1280{$item['backdrop_path']}" : null,
            'rating' => round($item['vote_average'] ?? 0, 1),
        ];
    }

    protected function formatSeriesSummary(array $item): array
    {
        return [
            'provider' => 'TMDb',
            'id' => (string) $item['id'],
            'tmdb_id' => (string) $item['id'],
            'title' => $item['name'] ?? '',
            'original_title' => $item['original_name'] ?? '',
            'release_year' => isset($item['first_air_date']) ? (int) substr($item['first_air_date'], 0, 4) : null,
            'overview' => $item['overview'] ?? '',
            'poster_path' => isset($item['poster_path']) ? "https://image.tmdb.org/t/p/w500{$item['poster_path']}" : null,
            'backdrop_path' => isset($item['backdrop_path']) ? "https://image.tmdb.org/t/p/w1280{$item['backdrop_path']}" : null,
            'rating' => round($item['vote_average'] ?? 0, 1),
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
}
