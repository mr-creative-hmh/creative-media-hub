<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Series;
use App\Services\Scout\LibraryAcquisitionService;
use App\Services\Scout\LibraryGapService;
use App\Services\Scout\TorrentDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaScoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Scout Inertia page loads successfully.
     */
    public function test_scout_page_loads_with_metrics(): void
    {
        $response = $this->get('/scout');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Scout/Index')
                 ->has('initialMetrics')
        );
    }

    /**
     * Test Gaps API returns structured json.
     */
    public function test_scout_gaps_api_returns_paginated_data(): void
    {
        $response = $this->getJson('/api/scout/gaps?type=all&page=1&per_page=12');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'total',
            'current_page',
            'last_page',
            'per_page',
        ]);
    }

    /**
     * Test Refresh API returns updated metrics.
     */
    public function test_scout_refresh_api_returns_metrics(): void
    {
        $response = $this->postJson('/api/scout/refresh');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'metrics' => [
                'missing_episodes_count',
                'missing_seasons_count',
                'missing_movies_count',
                'total_gaps_count',
                'series_completion_rate',
                'collections_completion_rate',
            ],
        ]);
    }

    /**
     * Test Torrent Search API validation and structure.
     */
    public function test_scout_torrents_api_validation(): void
    {
        $response = $this->getJson('/api/scout/torrents');
        $response->assertStatus(422);

        $validResponse = $this->getJson('/api/scout/torrents?type=movie&title=Bad+Boys&year=1995');
        $validResponse->assertStatus(200);
        $validResponse->assertJsonStructure([
            'torrents',
            'count',
        ]);
    }

    /**
     * Test LibraryAcquisitionService canonical path calculations.
     */
    public function test_library_acquisition_canonical_paths(): void
    {
        $acq = app(LibraryAcquisitionService::class);

        // Test Series Destination
        $seriesPath = $acq->calculateCanonicalDestination(
            'C:/Downloads/prison.break.s02e15.1080p.mkv',
            'H:/Entertainment/TV Shows',
            'series',
            [
                'series_title' => 'Prison Break',
                'season_number' => 2,
                'episode_number' => 15,
                'episode_title' => 'The Message',
                'resolution' => '1080p',
            ]
        );

        $this->assertStringContainsString('H:/Entertainment/TV Shows/Prison Break', $seriesPath);
        $this->assertStringContainsString('Season 02', $seriesPath);
        $this->assertStringContainsString('Prison Break - S02E15 - The Message [1080p].mkv', $seriesPath);

        // Test Movie Franchise Destination
        $moviePath = $acq->calculateCanonicalDestination(
            'C:/Downloads/bad.boys.ii.2003.mkv',
            'H:/Entertainment/Movies',
            'movie',
            [
                'movie_title' => 'Bad Boys II',
                'release_year' => 2003,
                'genre' => 'Action',
                'collection_name' => 'Bad Boys Collection',
            ]
        );

        $this->assertStringContainsString('H:/Entertainment/Movies/Action', $moviePath);
        $this->assertStringContainsString('Bad Boys Collection', $moviePath);
        $this->assertStringContainsString('Bad Boys II (2003)', $moviePath);
        $this->assertStringEndsWith('Bad Boys II (2003).mkv', $moviePath);
    }
}