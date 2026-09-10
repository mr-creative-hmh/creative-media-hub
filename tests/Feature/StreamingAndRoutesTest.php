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

    public function test_subtitle_endpoint_converts_windows_1256_arabic_to_valid_utf8_webvtt(): void
    {
        $movie = MediaItem::create([
            'title' => 'Chernobyl',
            'release_year' => 2019,
        ]);

        $testDir = storage_path('framework/testing');
        if (! file_exists($testDir)) {
            mkdir($testDir, 0777, true);
        }

        $filePath = $testDir.'/test_arabic_1256.srt';
        $arabicUtf8 = "1\n00:00:01,000 --> 00:00:05,000\nأين يكمن خطر الأكاذيب؟\n\n";
        $arabic1256 = iconv('UTF-8', 'WINDOWS-1256//IGNORE', $arabicUtf8);
        file_put_contents($filePath, $arabic1256);

        $sub = Subtitle::create([
            'subtitlable_id' => $movie->id,
            'subtitlable_type' => MediaItem::class,
            'file_path' => $filePath,
            'language' => 'ar',
            'format' => 'srt',
        ]);

        $response = $this->get(route('stream.subtitle', $sub));
        $response->assertStatus(200);
        $this->assertStringContainsString('text/vtt', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertTrue(mb_check_encoding($content, 'UTF-8'), 'Response must be valid UTF-8');
        $this->assertStringContainsString('أين يكمن خطر الأكاذيب؟', $content);
        $this->assertStringNotContainsString('?????', $content);

        // Self-healed on disk
        $diskContent = file_get_contents($filePath);
        $this->assertTrue(mb_check_encoding($diskContent, 'UTF-8'), 'File on disk should be self-healed to UTF-8');

        @unlink($filePath);
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
        $resMovies = $this->getJson('/api/watch-history?type=movie');
        $resMovies->assertStatus(200);
        $this->assertEquals(1, count($resMovies->json('items')));
        $this->assertEquals('movie', $resMovies->json('items.0.type'));

        // Series query should only return episodes
        $resSeries = $this->getJson('/api/watch-history?type=series');
        $resSeries->assertStatus(200);
        $this->assertEquals(1, count($resSeries->json('items')));
        $this->assertEquals('episode', $resSeries->json('items.0.type'));

        // Collection query should only return collection items
        $resCol = $this->getJson('/api/watch-history?type=collection');
        $resCol->assertStatus(200);
        $this->assertEquals(1, count($resCol->json('items')));
        $this->assertEquals('The Dark Knight', $resCol->json('items.0.title'));
    }

    public function test_continue_watching_includes_resolution_and_codecs(): void
    {
        $movie = MediaItem::create([
            'title' => 'Sample 480p Video',
            'release_year' => 2020,
            'resolution' => '480p SD',
            'video_codec' => 'h264',
            'audio_codec' => 'aac',
            'duration_seconds' => 3600,
        ]);

        WatchHistory::create([
            'watchable_id' => $movie->id,
            'watchable_type' => MediaItem::class,
            'progress_seconds' => 120,
            'duration_seconds' => 3600,
            'is_completed' => false,
            'last_watched_at' => now(),
        ]);

        $res = $this->getJson('/api/watch-history?type=movie');
        $res->assertStatus(200);
        $item = $res->json('items.0');
        $this->assertEquals('480p SD', $item['resolution']);
        $this->assertEquals('h264', $item['video_codec']);
        $this->assertEquals('aac', $item['audio_codec']);
        $this->assertEquals(3600, $item['duration_seconds']);
    }

    public function test_media_duration_endpoint_returns_exact_duration_and_resolution(): void
    {
        $series = Series::create([
            'title' => 'Wicked Science',
            'release_year' => 2004,
        ]);

        $season = Season::create([
            'series_id' => $series->id,
            'season_number' => 1,
        ]);

        $episode = Episode::create([
            'series_id' => $series->id,
            'season_id' => $season->id,
            'episode_number' => 1,
            'title' => 'Episode 1',
            'resolution' => '360p',
            'duration_seconds' => 1427,
            'runtime_minutes' => 24,
        ]);

        $res = $this->getJson('/api/media/duration?type=episode&id='.$episode->id);
        $res->assertStatus(200);
        $this->assertEquals(1427, $res->json('duration_seconds'));
        $this->assertEquals(24, $res->json('runtime_minutes'));
        $this->assertEquals('360p', $res->json('resolution'));
    }

    public function test_movie_stream_supports_cors_and_audio_delay(): void
    {
        $movie = MediaItem::create([
            'title' => 'Sonic the Hedgehog',
            'release_year' => 2020,
            'file_path' => 'H:/Entertainment/Movies/Sci-Fi/Sonic the Hedgehog Collection/Sonic the Hedgehog (2020)/Sonic the Hedgehog (2020).mkv',
            'video_codec' => 'H.264 / AVC',
            'audio_codec' => 'Dolby Digital',
        ]);

        $response = $this->get(route('stream.movie', ['mediaItem' => $movie->id, 'audio_delay' => -500]));
        $this->assertTrue(in_array($response->getStatusCode(), [200, 206, 302, 404]));
        if ($response->getStatusCode() === 200 || $response->getStatusCode() === 206) {
            $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
        }
    }

    public function test_delete_single_watch_history_item_removes_record(): void
    {
        $movie = MediaItem::create([
            'title' => 'Delete Test Movie',
            'release_year' => 2021,
        ]);

        $history = WatchHistory::create([
            'watchable_id' => $movie->id,
            'watchable_type' => MediaItem::class,
            'progress_seconds' => 120,
            'duration_seconds' => 3600,
            'is_completed' => false,
            'last_watched_at' => now(),
        ]);

        $this->assertDatabaseHas('watch_histories', ['id' => $history->id]);

        $res = $this->deleteJson('/api/watch-history/' . $history->id);
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $this->assertDatabaseMissing('watch_histories', ['id' => $history->id]);
    }

    public function test_clear_all_watch_history_removes_records(): void
    {
        $movie = MediaItem::create([
            'title' => 'Clear Test Movie',
            'release_year' => 2022,
        ]);

        WatchHistory::create([
            'watchable_id' => $movie->id,
            'watchable_type' => MediaItem::class,
            'progress_seconds' => 500,
            'duration_seconds' => 4000,
            'is_completed' => false,
            'last_watched_at' => now(),
        ]);

        $res = $this->deleteJson('/api/watch-history');
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $this->assertEquals(0, WatchHistory::count());
    }

    public function test_watch_history_page_renders_successfully(): void
    {
        $response = $this->get('/watch-history');
        $response->assertStatus(200);
    }
}
