<?php

namespace App\Services\Metadata;

use App\Services\Metadata\Contracts\MetadataProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AniListProvider implements MetadataProviderInterface
{
    protected string $graphqlUrl = 'https://graphql.anilist.co';

    public function getName(): string
    {
        return 'AniList (100% Free Anime Specialist)';
    }

    public function searchAnime(string $title, ?int $year = null): array
    {
        return $this->searchSeries($title, $year);
    }

    public function searchMovie(string $title, ?int $year = null, string $lang = 'en'): array
    {
        return $this->searchMedia($title, 'MOVIE', $year);
    }

    public function searchSeries(string $title, ?int $year = null, string $lang = 'en'): array
    {
        return $this->searchMedia($title, 'TV', $year);
    }

    public function getMovieDetails(string|int $id, string $lang = 'en'): ?array
    {
        return $this->getMediaDetails((int) $id);
    }

    public function getSeriesDetails(string|int $id, string $lang = 'en'): ?array
    {
        return $this->getMediaDetails((int) $id);
    }

    public function getSeasonEpisodes(string|int $seriesId, int $seasonNumber, string $lang = 'en'): array
    {
        return [];
    }

    protected function searchMedia(string $title, string $format, ?int $year = null): array
    {
        $query = '
        query ($search: String, $format: MediaFormat, $year: Int) {
            Page(page: 1, perPage: 6) {
                media(search: $search, format: $format, seasonYear: $year, type: ANIME) {
                    id
                    title { romaji english native }
                    seasonYear
                    description(asHtml: false)
                    coverImage { large extraLarge }
                    bannerImage
                    averageScore
                    genres
                }
            }
        }';

        try {
            $response = Http::timeout(6)->post($this->graphqlUrl, [
                'query' => $query,
                'variables' => [
                    'search' => $title,
                    'format' => $format,
                    'year' => $year,
                ],
            ]);

            if ($response->successful()) {
                $items = $response->json('data.Page.media', []);
                return array_map(function ($item) {
                    return [
                        'provider' => 'AniList',
                        'id' => (string) $item['id'],
                        'title' => $item['title']['english'] ?: $item['title']['romaji'],
                        'original_title' => $item['title']['native'] ?: $item['title']['romaji'],
                        'release_year' => $item['seasonYear'] ?? null,
                        'overview' => strip_tags($item['description'] ?? ''),
                        'poster_path' => $item['coverImage']['extraLarge'] ?? $item['coverImage']['large'],
                        'backdrop_path' => $item['bannerImage'] ?? null,
                        'rating' => isset($item['averageScore']) ? round($item['averageScore'] / 10, 1) : null,
                        'genres' => $item['genres'] ?? [],
                    ];
                }, $items);
            }
        } catch (\Exception $e) {
            Log::warning("AniList searchMedia failed: " . $e->getMessage());
        }

        return [];
    }

    protected function getMediaDetails(int $id): ?array
    {
        $query = '
        query ($id: Int) {
            Media(id: $id, type: ANIME) {
                id
                title { romaji english native }
                seasonYear
                description(asHtml: false)
                coverImage { extraLarge }
                bannerImage
                averageScore
                genres
                status
                episodes
                studios(isMain: true) { nodes { name } }
            }
        }';

        try {
            $response = Http::timeout(6)->post($this->graphqlUrl, [
                'query' => $query,
                'variables' => ['id' => $id],
            ]);

            if ($response->successful()) {
                $item = $response->json('data.Media');
                if (!$item) return null;

                return [
                    'provider' => 'AniList',
                    'id' => (string) $item['id'],
                    'title' => $item['title']['english'] ?: $item['title']['romaji'],
                    'original_title' => $item['title']['native'] ?: $item['title']['romaji'],
                    'release_year' => $item['seasonYear'] ?? null,
                    'overview' => strip_tags($item['description'] ?? ''),
                    'poster_path' => $item['coverImage']['extraLarge'] ?? null,
                    'backdrop_path' => $item['bannerImage'] ?? null,
                    'rating' => isset($item['averageScore']) ? round($item['averageScore'] / 10, 1) : null,
                    'genres' => $item['genres'] ?? [],
                    'status' => $item['status'] ?? 'FINISHED',
                    'studio' => $item['studios']['nodes'][0]['name'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::warning("AniList getMediaDetails failed: " . $e->getMessage());
        }

        return null;
    }
}
