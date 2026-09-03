<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Subtitle;
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
}
