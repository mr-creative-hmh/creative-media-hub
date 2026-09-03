<?php

namespace App\Services\Metadata\Contracts;

interface MetadataProviderInterface
{
    public function getName(): string;

    public function searchMovie(string $title, ?int $year = null, string $lang = 'en'): array;

    public function searchSeries(string $title, ?int $year = null, string $lang = 'en'): array;

    public function getMovieDetails(string|int $id, string $lang = 'en'): ?array;

    public function getSeriesDetails(string|int $id, string $lang = 'en'): ?array;

    public function getSeasonEpisodes(string|int $seriesId, int $seasonNumber, string $lang = 'en'): array;
}
