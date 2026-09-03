<?php

namespace App\Services\Metadata;

use App\Services\Metadata\Contracts\MetadataProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TvMazeProvider implements MetadataProviderInterface
{
    protected string $baseUrl = 'https://api.tvmaze.com';

    public function getName(): string
    {
        return 'TVMaze (100% Free - No Key)';
    }

    public function searchMovie(string $title, ?int $year = null, string $lang = 'en'): array
    {
        return []; // TVMaze is TV-only
    }

    public function searchSeries(string $title, ?int $year = null, string $lang = 'en'): array
    {
        try {
            $response = Http::timeout(6)->get("{$this->baseUrl}/search/shows", [
                'q' => $title,
            ]);

            if ($response->successful()) {
                $results = [];
                foreach ($response->json() as $entry) {
                    $show = $entry['show'] ?? [];
                    if (empty($show)) {
                        continue;
                    }

                    $premieredYear = isset($show['premiered']) ? (int) substr($show['premiered'], 0, 4) : null;
                    if ($year && $premieredYear && abs($premieredYear - $year) > 1) {
                        continue;
                    }

                    $results[] = [
                        'provider' => 'TVMaze',
                        'id' => (string) $show['id'],
                        'tvmaze_id' => (string) $show['id'],
                        'title' => $show['name'] ?? '',
                        'release_year' => $premieredYear,
                        'overview' => strip_tags($show['summary'] ?? ''),
                        'poster_path' => $show['image']['original'] ?? ($show['image']['medium'] ?? null),
                        'backdrop_path' => null,
                        'rating' => isset($show['rating']['average']) ? (float) $show['rating']['average'] : null,
                    ];
                }

                return $results;
            }
        } catch (\Exception $e) {
            Log::warning('TVMaze searchSeries failed: '.$e->getMessage());
        }

        return [];
    }

    public function getMovieDetails(string|int $id, string $lang = 'en'): ?array
    {
        return null;
    }

    public function getSeriesDetails(string|int $id, string $lang = 'en'): ?array
    {
        try {
            $response = Http::timeout(6)->get("{$this->baseUrl}/shows/{$id}", [
                'embed' => ['cast', 'seasons'],
            ]);

            if ($response->successful()) {
                $show = $response->json();
                $premieredYear = isset($show['premiered']) ? (int) substr($show['premiered'], 0, 4) : null;

                $seasons = [];
                foreach ($show['_embedded']['seasons'] ?? [] as $s) {
                    $seasons[] = [
                        'season_number' => $s['number'],
                        'title' => $s['name'] ?: "Season {$s['number']}",
                        'overview' => strip_tags($s['summary'] ?? ''),
                        'poster_path' => $s['image']['original'] ?? null,
                        'air_date' => $s['premiereDate'] ?? null,
                        'episode_count' => $s['episodeOrder'] ?? 0,
                    ];
                }

                $cast = [];
                foreach ($show['_embedded']['cast'] ?? [] as $c) {
                    $cast[] = [
                        'name' => $c['person']['name'] ?? '',
                        'character' => $c['character']['name'] ?? '',
                        'profile_path' => $c['person']['image']['medium'] ?? null,
                        'order' => 0,
                    ];
                }

                return [
                    'provider' => 'TVMaze',
                    'tvmaze_id' => (string) $show['id'],
                    'title' => $show['name'] ?? '',
                    'overview' => strip_tags($show['summary'] ?? ''),
                    'release_year' => $premieredYear,
                    'rating' => isset($show['rating']['average']) ? (float) $show['rating']['average'] : null,
                    'status' => $show['status'] ?? 'Running',
                    'network' => $show['network']['name'] ?? ($show['webChannel']['name'] ?? null),
                    'total_seasons' => count($seasons),
                    'poster_path' => $show['image']['original'] ?? ($show['image']['medium'] ?? null),
                    'genres' => $show['genres'] ?? [],
                    'cast' => array_slice($cast, 0, 10),
                    'seasons' => $seasons,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('TVMaze getSeriesDetails failed: '.$e->getMessage());
        }

        return null;
    }

    public function getSeasonEpisodes(string|int $seriesId, int $seasonNumber, string $lang = 'en'): array
    {
        try {
            $response = Http::timeout(6)->get("{$this->baseUrl}/shows/{$seriesId}/episodes");

            if ($response->successful()) {
                $episodes = [];
                foreach ($response->json() as $ep) {
                    if (($ep['season'] ?? 0) === $seasonNumber) {
                        $episodes[] = [
                            'episode_number' => $ep['number'],
                            'title' => $ep['name'] ?? "Episode {$ep['number']}",
                            'overview' => strip_tags($ep['summary'] ?? ''),
                            'still_path' => $ep['image']['original'] ?? ($ep['image']['medium'] ?? null),
                            'air_date' => $ep['airdate'] ?? null,
                            'rating' => isset($ep['rating']['average']) ? (float) $ep['rating']['average'] : null,
                            'runtime_minutes' => $ep['runtime'] ?? null,
                        ];
                    }
                }

                return $episodes;
            }
        } catch (\Exception $e) {
            Log::warning('TVMaze getSeasonEpisodes failed: '.$e->getMessage());
        }

        return [];
    }
}
