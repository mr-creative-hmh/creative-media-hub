<?php

namespace App\Services\Metadata;

use App\Services\Metadata\Contracts\MetadataProviderInterface;

class LocalNfoProvider implements MetadataProviderInterface
{
    public function getName(): string
    {
        return 'Local NFO & Regex Parser (Offline Fallback)';
    }

    public function searchMovie(string $title, ?int $year = null, string $lang = 'en'): array
    {
        return [
            [
                'provider' => 'Local Parser',
                'id' => 'local-'.md5($title.$year),
                'title' => $title,
                'release_year' => $year,
                'overview' => "Locally organized media: {$title} (".($year ?? 'Unknown').')',
            ],
        ];
    }

    public function searchSeries(string $title, ?int $year = null, string $lang = 'en'): array
    {
        return [
            [
                'provider' => 'Local Parser',
                'id' => 'local-'.md5($title.$year),
                'title' => $title,
                'release_year' => $year,
                'overview' => "Locally organized series: {$title} (".($year ?? 'Unknown').')',
            ],
        ];
    }

    public function getMovieDetails(string|int $id, string $lang = 'en'): ?array
    {
        return null;
    }

    public function getSeriesDetails(string|int $id, string $lang = 'en'): ?array
    {
        return null;
    }

    public function getSeasonEpisodes(string|int $seriesId, int $seasonNumber, string $lang = 'en'): array
    {
        return [];
    }
}
