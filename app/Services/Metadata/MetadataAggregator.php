<?php

namespace App\Services\Metadata;

use App\Services\Metadata\Contracts\MetadataProviderInterface;
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

    public function aggregateMovieMetadata(string $title, ?int $year = null, string $lang = 'en'): array
    {
        $default = [
            'title' => $title,
            'original_title' => $title,
            'title_ar' => null,
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

        // 1. Search across metadata chain (TMDb first for rich Arabic & English data)
        $searchResults = $this->searchMovie($title, $year, $lang);

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

            $merged['year'] = $merged['release_year'] ?? ($merged['year'] ?? $year);
            $merged['release_year'] = $merged['year'];

            // Download & cache poster locally
            if (!empty($merged['poster_path']) && filter_var($merged['poster_path'], FILTER_VALIDATE_URL)) {
                $merged['poster_path'] = $this->artwork->downloadPoster($merged['poster_path']);
            }
            if (!empty($merged['backdrop_path']) && filter_var($merged['backdrop_path'], FILTER_VALIDATE_URL)) {
                $merged['backdrop_path'] = $this->artwork->downloadBackdrop($merged['backdrop_path']);
            }

            return $merged;
        }

        return $default;
    }

    public function aggregateSeriesMetadata(string $title, ?int $year = null, string $lang = 'en'): array
    {
        $default = [
            'title' => $title,
            'original_title' => $title,
            'title_ar' => null,
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

        // 1. Search across metadata chain (TMDb prioritized for Arabic)
        $searchResults = $this->searchSeries($title, $year, $lang);

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

            $merged['year'] = $merged['release_year'] ?? ($merged['year'] ?? $year);
            $merged['release_year'] = $merged['year'];

            // Download & cache poster locally
            if (!empty($merged['poster_path']) && filter_var($merged['poster_path'], FILTER_VALIDATE_URL)) {
                $merged['poster_path'] = $this->artwork->downloadPoster($merged['poster_path']);
            }
            if (!empty($merged['backdrop_path']) && filter_var($merged['backdrop_path'], FILTER_VALIDATE_URL)) {
                $merged['backdrop_path'] = $this->artwork->downloadBackdrop($merged['backdrop_path']);
            }

            return $merged;
        }

        return $default;
    }

    public function searchMovie(string $title, ?int $year = null, string $lang = 'en'): array
    {
        $chain = ['tmdb', 'omdb', 'anilist', 'wikipedia', 'local'];
        $results = [];

        foreach ($chain as $key) {
            $provider = $this->providers[$key] ?? null;
            if (!$provider) continue;

            try {
                $res = $provider->searchMovie($title, $year, $lang);
                if (!empty($res)) {
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

    public function searchSeries(string $title, ?int $year = null, string $lang = 'en'): array
    {
        $chain = ['tmdb', 'omdb', 'tvmaze', 'anilist', 'wikipedia', 'local'];
        $results = [];

        foreach ($chain as $key) {
            $provider = $this->providers[$key] ?? null;
            if (!$provider) continue;

            try {
                $res = $provider->searchSeries($title, $year, $lang);
                if (!empty($res)) {
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
        if (isset($this->providers[$providerKey])) {
            try {
                $data = $this->providers[$providerKey]->getMovieDetails($id, $lang);
                if ($data) return $data;
            } catch (\Throwable $e) {
                Log::warning("Provider {$providerKey} getMovieDetails failed: " . $e->getMessage());
            }
        }

        foreach ($this->providers as $p) {
            try {
                $data = $p->getMovieDetails($id, $lang);
                if ($data) return $data;
            } catch (\Throwable $e) {}
        }

        return null;
    }

    public function getSeriesDetails(string|int $id, string $providerKey = 'tmdb', string $lang = 'en'): ?array
    {
        $providerKey = strtolower($providerKey);
        if (isset($this->providers[$providerKey])) {
            try {
                $data = $this->providers[$providerKey]->getSeriesDetails($id, $lang);
                if ($data) return $data;
            } catch (\Throwable $e) {
                Log::warning("Provider {$providerKey} getSeriesDetails failed: " . $e->getMessage());
            }
        }

        foreach ($this->providers as $p) {
            try {
                $data = $p->getSeriesDetails($id, $lang);
                if ($data) return $data;
            } catch (\Throwable $e) {}
        }

        return null;
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
