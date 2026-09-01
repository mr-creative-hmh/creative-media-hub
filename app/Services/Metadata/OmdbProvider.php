<?php

namespace App\Services\Metadata;

use App\Models\AppSetting;
use App\Services\Metadata\Contracts\MetadataProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OmdbProvider implements MetadataProviderInterface
{
    protected string $baseUrl = 'https://www.omdbapi.com';
    protected ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    protected function getApiKey(): ?string
    {
        return $this->apiKey ?: AppSetting::get('omdb_api_key', config('services.omdb.key', 'trilogy'));
    }

    public function getName(): string
    {
        return 'OMDb / IMDb API';
    }

    public function searchMovie(string $title, ?int $year = null, string $lang = 'en'): array
    {
        $key = $this->getApiKey();
        if (empty($key)) return [];

        try {
            $response = Http::timeout(6)->get($this->baseUrl, [
                'apikey' => $key,
                's' => $title,
                'y' => $year,
                'type' => 'movie',
            ]);

            if ($response->successful() && ($response['Response'] ?? '') === 'True') {
                return array_map(function ($item) {
                    return [
                        'provider' => 'OMDb',
                        'id' => $item['imdbID'],
                        'imdb_id' => $item['imdbID'],
                        'title' => $item['Title'] ?? '',
                        'release_year' => isset($item['Year']) ? (int) substr($item['Year'], 0, 4) : null,
                        'poster_path' => ($item['Poster'] ?? 'N/A') !== 'N/A' ? $item['Poster'] : null,
                    ];
                }, $response['Search'] ?? []);
            }
        } catch (\Exception $e) {
            Log::warning("OMDb searchMovie failed: " . $e->getMessage());
        }

        return [];
    }

    public function searchSeries(string $title, ?int $year = null, string $lang = 'en'): array
    {
        $key = $this->getApiKey();
        if (empty($key)) return [];

        try {
            $response = Http::timeout(6)->get($this->baseUrl, [
                'apikey' => $key,
                's' => $title,
                'y' => $year,
                'type' => 'series',
            ]);

            if ($response->successful() && ($response['Response'] ?? '') === 'True') {
                return array_map(function ($item) {
                    return [
                        'provider' => 'OMDb',
                        'id' => $item['imdbID'],
                        'imdb_id' => $item['imdbID'],
                        'title' => $item['Title'] ?? '',
                        'release_year' => isset($item['Year']) ? (int) substr($item['Year'], 0, 4) : null,
                        'poster_path' => ($item['Poster'] ?? 'N/A') !== 'N/A' ? $item['Poster'] : null,
                    ];
                }, $response['Search'] ?? []);
            }
        } catch (\Exception $e) {
            Log::warning("OMDb searchSeries failed: " . $e->getMessage());
        }

        return [];
    }

    public function getMovieDetails(string|int $id, string $lang = 'en'): ?array
    {
        $key = $this->getApiKey();
        if (empty($key)) return null;

        try {
            $response = Http::timeout(6)->get($this->baseUrl, [
                'apikey' => $key,
                'i' => $id,
                'plot' => 'full',
            ]);

            if ($response->successful() && ($response['Response'] ?? '') === 'True') {
                $data = $response->json();
                return [
                    'provider' => 'OMDb',
                    'imdb_id' => $data['imdbID'] ?? null,
                    'title' => $data['Title'] ?? '',
                    'release_year' => isset($data['Year']) ? (int) substr($data['Year'], 0, 4) : null,
                    'rating' => isset($data['imdbRating']) && is_numeric($data['imdbRating']) ? (float) $data['imdbRating'] : 7.0,
                    'vote_count' => isset($data['imdbVotes']) ? (int) str_replace(',', '', $data['imdbVotes']) : 0,
                    'runtime_minutes' => isset($data['Runtime']) ? (int) filter_var($data['Runtime'], FILTER_SANITIZE_NUMBER_INT) : null,
                    'overview' => ($data['Plot'] ?? 'N/A') !== 'N/A' ? $data['Plot'] : '',
                    'poster_path' => ($data['Poster'] ?? 'N/A') !== 'N/A' ? $data['Poster'] : null,
                    'genres' => isset($data['Genre']) ? array_map('trim', explode(',', $data['Genre'])) : [],
                    'director' => ($data['Director'] ?? 'N/A') !== 'N/A' ? $data['Director'] : null,
                    'actors' => isset($data['Actors']) ? array_map('trim', explode(',', $data['Actors'])) : [],
                ];
            }
        } catch (\Exception $e) {
            Log::warning("OMDb getMovieDetails failed: " . $e->getMessage());
        }

        return null;
    }

    public function getSeriesDetails(string|int $id, string $lang = 'en'): ?array
    {
        return $this->getMovieDetails($id, $lang);
    }

    public function getSeasonEpisodes(string|int $seriesId, int $seasonNumber, string $lang = 'en'): array
    {
        $key = $this->getApiKey();
        if (empty($key)) return [];

        try {
            $response = Http::timeout(6)->get($this->baseUrl, [
                'apikey' => $key,
                'i' => $seriesId,
                'Season' => $seasonNumber,
            ]);

            if ($response->successful() && ($response['Response'] ?? '') === 'True') {
                return array_map(function ($ep) {
                    return [
                        'episode_number' => (int) ($ep['Episode'] ?? 1),
                        'title' => $ep['Title'] ?? "Episode {$ep['Episode']}",
                        'air_date' => ($ep['Released'] ?? 'N/A') !== 'N/A' ? $ep['Released'] : null,
                        'rating' => isset($ep['imdbRating']) && is_numeric($ep['imdbRating']) ? (float) $ep['imdbRating'] : 7.5,
                    ];
                }, $response['Episodes'] ?? []);
            }
        } catch (\Exception $e) {
            Log::warning("OMDb getSeasonEpisodes failed: " . $e->getMessage());
        }

        return [];
    }
}
