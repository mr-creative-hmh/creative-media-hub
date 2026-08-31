<?php

namespace App\Services\Metadata;

use App\Services\Metadata\Contracts\MetadataProviderInterface;
use Illuminate\Support\Facades\File;
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
            'tvmaze' => $tvmaze,
            'omdb' => $omdb,
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
        ];

        // 1. Search across metadata chain
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

            // Download & cache poster locally
            if (!empty($merged['poster_path'])) {
                $merged['poster_path'] = $this->artwork->downloadPoster($merged['poster_path']);
            }
            if (!empty($merged['backdrop_path'])) {
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
        ];

        // 1. Search across metadata chain
        $searchResults = $this->searchSeries($title, $year, $lang);

        if (!empty($searchResults)) {
            $first = $searchResults[0];
            $providerKey = strtolower($first['provider'] ?? 'tvmaze');
            $id = $first['id'] ?? ($first['tvmaze_id'] ?? ($first['tmdb_id'] ?? null));

            $merged = array_merge($default, $first);

            if ($id) {
                $details = $this->getSeriesDetails($id, $providerKey, $lang);
                if ($details) {
                    $merged = array_merge($merged, $details);
                }
            }

            // Download & cache poster locally
            if (!empty($merged['poster_path'])) {
                $merged['poster_path'] = $this->artwork->downloadPoster($merged['poster_path']);
            }
            if (!empty($merged['backdrop_path'])) {
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
                    if ($key === 'tmdb' || count($results) >= 5) {
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
        $chain = ['tvmaze', 'tmdb', 'omdb', 'anilist', 'wikipedia', 'local'];
        $results = [];

        foreach ($chain as $key) {
            $provider = $this->providers[$key] ?? null;
            if (!$provider) continue;

            try {
                $res = $provider->searchSeries($title, $year, $lang);
                if (!empty($res)) {
                    $results = array_merge($results, $res);
                    if (count($results) >= 5) {
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

    public function getSeriesDetails(string|int $id, string $providerKey = 'tvmaze', string $lang = 'en'): ?array
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
