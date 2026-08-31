<?php

namespace App\Services\Metadata;

use App\Services\Metadata\Contracts\MetadataProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OmdbProvider implements MetadataProviderInterface
{
    protected string $baseUrl = 'https://www.omdbapi.com';
    protected ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?: config('services.omdb.key', 'trilogy');
    }

    public function getName(): string
    {
        return 'OMDb / IMDb API';
    }

    public function searchMovie(string $title, ?int $year = null, string $lang = 'en'): array
    {
        if (empty($this->apiKey)) return [];

        try {
            $response = Http::timeout(5)->get($this->baseUrl, [
                'apikey' => $this->apiKey,
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
        if (empty($this->apiKey)) return [];

        try {
            $response = Http::timeout(5)->get($this->baseUrl, [
                'apikey' => $this->apiKey,
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
        return $this->fetchByImdbId($id);
    }

    public function getSeriesDetails(string|int $id, string $lang = 'en'): ?array
    {
        return $this->fetchByImdbId($id);
    }

    public function getSeasonEpisodes(string|int $seriesId, int $seasonNumber, string $lang = 'en'): array
    {
        if (empty($this->apiKey)) return [];

        try {
            $response = Http::timeout(5)->get($this->baseUrl, [
                'apikey' => $this->apiKey,
                'i' => $seriesId,
                'Season' => $seasonNumber,
            ]);

            if ($response->successful() && ($response['Response'] ?? '') === 'True') {
                return array_map(fn($ep) => [
                    'episode_number' => (int) ($ep['Episode'] ?? 1),
                    'title' => $ep['Title'] ?? '',
                    'air_date' => $ep['Released'] !== 'N/A' ? $ep['Released'] : null,
                    'rating' => is_numeric($ep['imdbRating'] ?? null) ? (float) $ep['imdbRating'] : null,
                ], $response['Episodes'] ?? []);
            }
        } catch (\Exception $e) {
            Log::warning("OMDb getSeasonEpisodes failed: " . $e->getMessage());
        }

        return [];
    }

    protected function fetchByImdbId(string $imdbId): ?array
    {
        if (empty($this->apiKey)) return null;

        try {
            $response = Http::timeout(5)->get($this->baseUrl, [
                'apikey' => $this->apiKey,
                'i' => $imdbId,
                'plot' => 'full',
            ]);

            if ($response->successful() && ($response['Response'] ?? '') === 'True') {
                $data = $response->json();
                return [
                    'provider' => 'OMDb',
                    'imdb_id' => $data['imdbID'],
                    'title' => $data['Title'] ?? '',
                    'overview' => $data['Plot'] !== 'N/A' ? $data['Plot'] : '',
                    'release_year' => is_numeric(substr($data['Year'] ?? '', 0, 4)) ? (int) substr($data['Year'], 0, 4) : null,
                    'rating' => is_numeric($data['imdbRating'] ?? null) ? (float) $data['imdbRating'] : null,
                    'poster_path' => ($data['Poster'] ?? 'N/A') !== 'N/A' ? $data['Poster'] : null,
                    'genres' => explode(', ', $data['Genre'] ?? ''),
                    'director' => $data['Director'] !== 'N/A' ? $data['Director'] : null,
                    'actors' => explode(', ', $data['Actors'] ?? ''),
                ];
            }
        } catch (\Exception $e) {
            Log::warning("OMDb fetchByImdbId failed: " . $e->getMessage());
        }

        return null;
    }
}
