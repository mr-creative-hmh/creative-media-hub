<?php

namespace App\Services\Metadata;

use App\Services\Metadata\Contracts\MetadataProviderInterface;
use Illuminate\Support\Facades\Log;

class MetadataAggregator
{
    /** @var MetadataProviderInterface[] */
    protected array $providers = [];

    public function __construct(
        TmdbProvider $tmdb,
        TvMazeProvider $tvmaze,
        OmdbProvider $omdb,
        AniListProvider $anilist,
        WikipediaProvider $wikipedia,
        LocalNfoProvider $local
    ) {
        $this->providers = [
            'tmdb' => $tmdb,
            'tvmaze' => $tvmaze,
            'omdb' => $omdb,
            'anilist' => $anilist,
            'wikipedia' => $wikipedia,
            'local' => $local,
        ];
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
            } catch (\Exception $e) {
                Log::warning("Provider {$key} searchMovie failed: " . $e->getMessage());
            }
        }

        return $results;
    }

    public function searchSeries(string $title, ?int $year = null, string $lang = 'en'): array
    {
        $chain = ['tmdb', 'tvmaze', 'omdb', 'anilist', 'wikipedia', 'local'];
        $results = [];

        foreach ($chain as $key) {
            $provider = $this->providers[$key] ?? null;
            if (!$provider) continue;

            try {
                $res = $provider->searchSeries($title, $year, $lang);
                if (!empty($res)) {
                    $results = array_merge($results, $res);
                    if ($key === 'tmdb' || count($results) >= 5) {
                        break;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Provider {$key} searchSeries failed: " . $e->getMessage());
            }
        }

        return $results;
    }

    public function getMovieDetails(string|int $id, string $providerKey = 'tmdb', string $lang = 'en'): ?array
    {
        if (isset($this->providers[$providerKey])) {
            $data = $this->providers[$providerKey]->getMovieDetails($id, $lang);
            if ($data) return $data;
        }

        foreach ($this->providers as $p) {
            $data = $p->getMovieDetails($id, $lang);
            if ($data) return $data;
        }

        return null;
    }

    public function getSeriesDetails(string|int $id, string $providerKey = 'tmdb', string $lang = 'en'): ?array
    {
        if (isset($this->providers[$providerKey])) {
            $data = $this->providers[$providerKey]->getSeriesDetails($id, $lang);
            if ($data) return $data;
        }

        foreach ($this->providers as $p) {
            $data = $p->getSeriesDetails($id, $lang);
            if ($data) return $data;
        }

        return null;
    }

    public function getSeasonEpisodes(string|int $seriesId, int $seasonNumber, string $providerKey = 'tmdb', string $lang = 'en'): array
    {
        if (isset($this->providers[$providerKey])) {
            return $this->providers[$providerKey]->getSeasonEpisodes($seriesId, $seasonNumber, $lang);
        }

        return [];
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
