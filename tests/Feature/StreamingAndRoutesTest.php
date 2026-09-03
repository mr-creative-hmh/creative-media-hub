<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Models\Subtitle;
use App\Models\WatchHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreamingAndRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_movie_streaming_endpoint_responds(): void
    {
        $movie = MediaItem::create([
            'title' => 'Interstellar',
            'release_year' => 2014,
        ]);

        $response = $this->get(route('stream.movie', $movie));
        $this->assertTrue(in_array($response->getStatusCode(), [200, 206, 302]));
    }

    public function test_subtitle_endpoint_returns_webvtt(): void
    {
        $movie = MediaItem::create([
            'title' => 'Inception',
            'release_year' => 2010,
        ]);

        $sub = Subtitle::create([
            'subtitlable_id' => $movie->id,
            'subtitlable_type' => MediaItem::class,
            'language' => 'en',
            'format' => 'srt',
        ]);

        $response = $this->get(route('stream.subtitle', $sub));
        $response->assertStatus(200);
        $this->assertStringContainsString('text/vtt', $response->headers->get('Content-Type'));
    }

    public function test_save_progress_records_timestamp(): void
    {
        $movie = MediaItem::create([
            'title' => 'Dune 2',
            'release_year' => 2024,
        ]);

        $response = $this->postJson(route('api.playback.progress'), [
            'watchable_id' => $movie->id,
            'watchable_type' => 'movie',
            'progress_seconds' => 1200,
            'duration_seconds' => 7200,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('watch_histories', [
            'watchable_id' => $movie->id,
            'progress_seconds' => 1200,
            'is_completed' => false,
        ]);
    }

    public function test_continue_watching_filters_by_type_correctly(): void
    {
        $movie = MediaItem::create([
            'title' => 'The Dark Knight',
            'release_year' => 2008,
            'collection_name' => 'The Dark Knight Trilogy',
        ]);

        $standaloneMovie = MediaItem::create([
            'title' => 'Gladiator',
            'release_year' => 2000,
            'collection_name' => null,
        ]);

        $series = Series::create([
            'title' => 'Breaking Bad',
            'release_year' => 2008,
        ]);

        $season = Season::create([
            'series_id' => $series->id,
            'season_number' => 1,
        ]);

        $episode = Episode::create([
            'series_id' => $series->id,
            'season_id' => $season->id,
            'episode_number' => 1,
            'title' => 'Pilot',
        ]);

        WatchHistory::create([
            'watchable_id' => $movie->id,
            'watchable_type' => MediaItem::class,
            'progress_seconds' => 500,
            'duration_seconds' => 9000,
            'is_completed' => false,
            'last_watched_at' => now(),
        ]);

        WatchHistory::create([
            'watchable_id' => $episode->id,
            'watchable_type' => Episode::class,
            'progress_seconds' => 300,
            'duration_seconds' => 3000,
            'is_completed' => false,
            'last_watched_at' => now(),
        ]);

        // Movie query should only return movies
        $resMovies = $this->getJson('/api/continue-watching?type=movie');
        $resMovies->assertStatus(200);
        $this->assertEquals(1, count($resMovies->json('items')));
        $this->assertEquals('movie', $resMovies->json('items.0.type'));

        // Series query should only return episodes
        $resSeries = $this->getJson('/api/continue-watching?type=series');
        $resSeries->assertStatus(200);
        $this->assertEquals(1, count($resSeries->json('items')));
        $this->assertEquals('episode', $resSeries->json('items.0.type'));

        // Collection query should only return collection items
        $resCol = $this->getJson('/api/continue-watching?type=collection');
        $resCol->assertStatus(200);
        $this->assertEquals(1, count($resCol->json('items')));
        $this->assertEquals('The Dark Knight', $resCol->json('items.0.title'));
    }
}
