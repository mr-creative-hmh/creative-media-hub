<?php

namespace App\Services\Metadata;

use App\Models\AppSetting;
use App\Services\Metadata\Contracts\MetadataProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TmdbProvider implements MetadataProviderInterface
{
    protected string $baseUrl = 'https://api.themoviedb.org/3';
    protected ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?: AppSetting::get('tmdb_api_key', config('services.tmdb.key'));
    }

    public function getName(): string
    {
        return 'TMDb';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function searchMovie(string $title, ?int $year = null, string $lang = 'en'): array
    {
        if (!$this->isConfigured()) return [];

        try {
            $response = Http::timeout(6)->get("{$this->baseUrl}/search/movie", [
                'api_key' => $this->apiKey,
                'query' => $title,
                'year' => $year,
                'language' => $lang === 'ar' ? 'ar-SA' : 'en-US',
                'include_adult' => false,
            ]);

            if ($response->successful()) {
                return array_map(fn($item) => $this->formatMovieSummary($item), $response->json('results', []));
            }
        } catch (\Exception $e) {
            Log::warning("TMDb searchMovie failed: " . $e->getMessage());
        }

        return [];
    }

    public function searchSeries(string $title, ?int $year = null, string $lang = 'en'): array
    {
        if (!$this->isConfigured()) return [];

        try {
            $response = Http::timeout(6)->get("{$this->baseUrl}/search/tv", [
                'api_key' => $this->apiKey,
                'query' => $title,
                'first_air_date_year' => $year,
                'language' => $lang === 'ar' ? 'ar-SA' : 'en-US',
                'include_adult' => false,
            ]);

            if ($response->successful()) {
                return array_map(fn($item) => $this->formatSeriesSummary($item), $response->json('results', []));
            }
        } catch (\Exception $e) {
            Log::warning("TMDb searchSeries failed: " . $e->getMessage());
        }

        return [];
    }

    public function getMovieDetails(string|int $id, string $lang = 'en'): ?array
    {
        if (!$this->isConfigured()) return null;

        try {
            $response = Http::timeout(6)->get("{$this->baseUrl}/movie/{$id}", [
                'api_key' => $this->apiKey,
                'language' => $lang === 'ar' ? 'ar-SA' : 'en-US',
                'append_to_response' => 'credits,videos,keywords',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'provider' => 'TMDb',
                    'tmdb_id' => (string) $data['id'],
                    'imdb_id' => $data['imdb_id'] ?? null,
                    'title' => $data['title'] ?? '',
                    'original_title' => $data['original_title'] ?? '',
                    'overview' => $data['overview'] ?? '',
                    'tagline' => $data['tagline'] ?? null,
                    'release_year' => isset($data['release_date']) ? (int) substr($data['release_date'], 0, 4) : null,
                    'rating' => round($data['vote_average'] ?? 0, 1),
                    'vote_count' => $data['vote_count'] ?? 0,
                    'runtime_minutes' => $data['runtime'] ?? null,
                    'poster_path' => isset($data['poster_path']) ? "https://image.tmdb.org/t/p/w780{$data['poster_path']}" : null,
                    'backdrop_path' => isset($data['backdrop_path']) ? "https://image.tmdb.org/t/p/original{$data['backdrop_path']}" : null,
                    'genres' => array_column($data['genres'] ?? [], 'name'),
                    'trailer_url' => $this->extractTrailer($data['videos']['results'] ?? []),
                    'cast' => array_slice(array_map(fn($c) => [
                        'name' => $c['name'],
                        'character' => $c['character'] ?? '',
                        'profile_path' => isset($c['profile_path']) ? "https://image.tmdb.org/t/p/w500{$c['profile_path']}" : null,
                        'order' => $c['order'] ?? 0,
                    ], $data['credits']['cast'] ?? []), 0, 12),
                    'directors' => array_map(fn($d) => [
                        'name' => $d['name'],
                        'profile_path' => isset($d['profile_path']) ? "https://image.tmdb.org/t/p/w500{$d['profile_path']}" : null,
                    ], array_filter($data['credits']['crew'] ?? [], fn($c) => ($c['job'] ?? '') === 'Director')),
                ];
            }
        } catch (\Exception $e) {
            Log::warning("TMDb getMovieDetails failed: " . $e->getMessage());
        }

        return null;
    }

    public function getSeriesDetails(string|int $id, string $lang = 'en'): ?array
    {
        if (!$this->isConfigured()) return null;

        try {
            $response = Http::timeout(6)->get("{$this->baseUrl}/tv/{$id}", [
                'api_key' => $this->apiKey,
                'language' => $lang === 'ar' ? 'ar-SA' : 'en-US',
                'append_to_response' => 'credits,videos',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'provider' => 'TMDb',
                    'tmdb_id' => (string) $data['id'],
                    'title' => $data['name'] ?? '',
                    'original_title' => $data['original_name'] ?? '',
                    'overview' => $data['overview'] ?? '',
                    'release_year' => isset($data['first_air_date']) ? (int) substr($data['first_air_date'], 0, 4) : null,
                    'rating' => round($data['vote_average'] ?? 0, 1),
                    'status' => $data['status'] ?? 'Returning Series',
                    'network' => $data['networks'][0]['name'] ?? null,
                    'total_seasons' => $data['number_of_seasons'] ?? 1,
                    'poster_path' => isset($data['poster_path']) ? "https://image.tmdb.org/t/p/w780{$data['poster_path']}" : null,
                    'backdrop_path' => isset($data['backdrop_path']) ? "https://image.tmdb.org/t/p/original{$data['backdrop_path']}" : null,
                    'genres' => array_column($data['genres'] ?? [], 'name'),
                    'trailer_url' => $this->extractTrailer($data['videos']['results'] ?? []),
                    'seasons' => array_map(fn($s) => [
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
            Log::warning("TMDb getSeriesDetails failed: " . $e->getMessage());
        }

        return null;
    }

    public function getSeasonEpisodes(string|int $seriesId, int $seasonNumber, string $lang = 'en'): array
    {
        if (!$this->isConfigured()) return [];

        try {
            $response = Http::timeout(6)->get("{$this->baseUrl}/tv/{$seriesId}/season/{$seasonNumber}", [
                'api_key' => $this->apiKey,
                'language' => $lang === 'ar' ? 'ar-SA' : 'en-US',
            ]);

            if ($response->successful()) {
                return array_map(fn($ep) => [
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
            Log::warning("TMDb getSeasonEpisodes failed: " . $e->getMessage());
        }

        return [];
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
