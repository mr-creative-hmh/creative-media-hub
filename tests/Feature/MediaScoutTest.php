<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\MediaItem;
use App\Models\Series;
use App\Services\Scout\LibraryAcquisitionService;
use App\Services\Scout\TorrentDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MediaScoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AppSetting::set('tmdb_api_key', 'test_api_key');

        Http::fake([
            'api.themoviedb.org/*' => Http::response([
                'results' => [
                    [
                        'id' => 19995,
                        'title' => 'Avatar',
                        'name' => 'Avatar',
                        'overview' => 'In the 22nd century...',
                        'poster_path' => '/avatar.jpg',
                        'backdrop_path' => '/avatar_bg.jpg',
                        'release_date' => '2009-12-18',
                        'first_air_date' => '2009-12-18',
                        'vote_average' => 7.9,
                        'vote_count' => 28000,
                        'media_type' => 'movie',
                    ],
                ],
            ], 200),
            'torrentio.strem.fun/*' => Http::response(['streams' => []], 200),
            'yts.lt/*' => Http::response(['data' => ['movies' => []]], 200),
            'apibay.org/*' => Http::response([], 200),
            'eztv.re/*' => Http::response(['torrents' => []], 200),
        ]);
    }

    /**
     * Test Scout Inertia page loads successfully and marks owned items in initialTrending.
     */
    public function test_scout_page_loads_with_metrics(): void
    {
        $movie = MediaItem::create([
            'title' => 'Avatar',
            'release_year' => 2009,
            'tmdb_id' => '19995',
            'file_path' => 'H:/Entertainment/Movies/Action/Avatar (2009)/Avatar.mkv',
        ]);

        $response = $this->get('/scout');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Scout/Index')
            ->has('initialMetrics')
            ->has('initialTrending')
            ->where('initialTrending.0.in_library', true)
            ->where('initialTrending.0.local_id', $movie->id)
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

        $this->assertStringContainsString('H:/Entertainment/TV Shows', $seriesPath);
        $this->assertStringContainsString('Prison Break', $seriesPath);
        $this->assertStringContainsString('Season 02', $seriesPath);
        $this->assertStringContainsString('Prison Break - S02E15', $seriesPath);

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
        $this->assertStringContainsString('Bad Boys', $moviePath);
        $this->assertStringContainsString('(2003)', $moviePath);
        $this->assertStringEndsWith('.mkv', $moviePath);
    }

    /**
     * Test Discover Search API returns media with library ownership status.
     */
    public function test_scout_discover_search_api(): void
    {
        $movie = MediaItem::create([
            'title' => 'Avatar',
            'release_year' => 2009,
            'tmdb_id' => '19995',
            'file_path' => 'H:/Entertainment/Movies/Action/Avatar (2009)/Avatar.mkv',
        ]);

        $response = $this->getJson('/api/scout/discover/search?query=Avatar&type=movie');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'results',
        ]);
        $this->assertTrue($response->json('results.0.in_library'));
        $this->assertEquals($movie->id, $response->json('results.0.local_id'));
    }

    /**
     * Test TorrentDiscoveryService strictly differentiates episodes from season packs.
     */
    public function test_torrent_discovery_strictly_differentiates_episodes_from_season_packs(): void
    {
        $service = app(TorrentDiscoveryService::class);

        // Episode matching
        $this->assertTrue($service->matchesEpisode('Severance.S01E03.1080p.WEB-DL.x265-PSA', 1, 3));
        $this->assertTrue($service->matchesEpisode('Severance.1x03.720p.HDTV', 1, 3));
        $this->assertTrue($service->matchesEpisode('Severance.S01E01-E04.1080p', 1, 3));

        // Wrong episode must be false
        $this->assertFalse($service->matchesEpisode('Severance.S01E01.1080p.WEB-DL', 1, 3));
        $this->assertFalse($service->matchesEpisode('Severance.S02E03.1080p.WEB-DL', 1, 3));

        // Entire season pack must NOT be matched as single episode
        $this->assertFalse($service->matchesEpisode('Severance.Season.1.Complete.1080p.WEB-DL', 1, 3));
        $this->assertFalse($service->matchesEpisode('Severance.S01.Complete.720p', 1, 3));

        // Season pack detection
        $this->assertTrue($service->isSeasonPack('Severance.Season.1.Complete.1080p.WEB-DL', 1));
        $this->assertTrue($service->isSeasonPack('Severance.S01.Batch.x265', 1));
        $this->assertTrue($service->isSeasonPack('Severance.Season.1.1080p.BluRay', 1));

        // Single episode must NOT be identified as a complete season pack
        $this->assertFalse($service->isSeasonPack('Severance.S01E03.1080p.WEB-DL.x265-PSA', 1));
        $this->assertFalse($service->isSeasonPack('Severance.1x03.720p.HDTV', 1));
    }

    /**
     * Test Discover Search matches owned movies by title/year fallback and series with owned episode counts.
     */
    public function test_scout_discover_search_matches_by_title_and_series(): void
    {
        // Movie matched by title & year (with no tmdb_id on local record)
        $movie = MediaItem::create([
            'title' => 'Avatar',
            'release_year' => 2009,
            'tmdb_id' => null,
            'file_path' => 'H:/Entertainment/Movies/Action/Avatar (2009)/Avatar.mkv',
        ]);

        $response = $this->getJson('/api/scout/discover/search?query=Avatar&type=movie');
        $response->assertStatus(200);
        $this->assertTrue($response->json('results.0.in_library'));
        $this->assertEquals($movie->id, $response->json('results.0.local_id'));
    }
}
