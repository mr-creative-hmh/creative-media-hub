<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_successfully(): void
    {
        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        $response->assertDontSee('@routes');
    }

    public function test_movies_index_renders_successfully(): void
    {
        $response = $this->get(route('movies.index'));
        $response->assertStatus(200);
        $response->assertDontSee('@routes');
    }

    public function test_dashboard_featured_media_contains_correct_types_and_series_first_episode(): void
    {
        $movie = MediaItem::create([
            'title' => 'Inception',
            'backdrop_path' => '/backdrop.jpg',
            'rating' => 9.0,
            'release_year' => 2010,
        ]);

        $series = Series::create([
            'title' => 'Breaking Bad',
            'backdrop_path' => '/bb-backdrop.jpg',
            'rating' => 9.5,
            'release_year' => 2008,
        ]);

        $season = Season::create([
            'series_id' => $series->id,
            'season_number' => 1,
            'title' => 'Season 1',
        ]);

        $episode = Episode::create([
            'series_id' => $series->id,
            'season_id' => $season->id,
            'episode_number' => 1,
            'title' => 'Pilot',
        ]);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);

        $featured = $response->viewData('page')['props']['featuredMedia'];
        $this->assertNotEmpty($featured);

        $seriesFeatured = collect($featured)->firstWhere('type', 'series');
        $this->assertNotNull($seriesFeatured);
        $this->assertEquals('Breaking Bad', $seriesFeatured['title']);
        $this->assertNotNull($seriesFeatured['first_episode']);
        $this->assertEquals('Pilot', $seriesFeatured['first_episode']['title']);

        $movieFeatured = collect($featured)->firstWhere('type', 'movie');
        $this->assertNotNull($movieFeatured);
        $this->assertEquals('Inception', $movieFeatured['title']);
        $this->assertEquals('media_item', $movieFeatured['watchable_type']);
    }
}
