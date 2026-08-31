<?php

namespace Tests\Unit;

use App\Services\Metadata\AniListProvider;
use App\Services\Metadata\ArtworkDownloadService;
use App\Services\Metadata\LocalNfoProvider;
use App\Services\Metadata\MetadataAggregator;
use App\Services\Metadata\OmdbProvider;
use App\Services\Metadata\TmdbProvider;
use App\Services\Metadata\TvMazeProvider;
use App\Services\Metadata\WikipediaProvider;
use Tests\TestCase;

class MetadataAggregatorTest extends TestCase
{
    public function test_local_provider_always_returns_fallback_movie_summary(): void
    {
        $local = new LocalNfoProvider();
        $results = $local->searchMovie('Inception', 2010);

        $this->assertNotEmpty($results);
        $this->assertEquals('Inception', $results[0]['title']);
        $this->assertEquals(2010, $results[0]['release_year']);
    }

    public function test_metadata_aggregator_falls_back_gracefully(): void
    {
        $aggregator = new MetadataAggregator(
            new TmdbProvider('invalid_key_for_testing'),
            new TvMazeProvider(),
            new OmdbProvider('invalid_key'),
            new AniListProvider(),
            new WikipediaProvider(),
            new LocalNfoProvider(),
            new ArtworkDownloadService()
        );

        $providers = $aggregator->getProvidersList();
        $this->assertArrayHasKey('tmdb', $providers);
        $this->assertArrayHasKey('tvmaze', $providers);
        $this->assertArrayHasKey('anilist', $providers);

        $results = $aggregator->searchMovie('Inception', 2010);
        $this->assertNotEmpty($results);
    }
}
