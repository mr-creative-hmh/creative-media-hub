<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Models\Subtitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubtitleDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_engine_returns_real_subtitles_from_opensubtitles_v3(): void
    {
        Http::fake([
            'https://v3-cinemeta.strem.io/*' => Http::response([
                'metas' => [
                    ['id' => 'tt0401079', 'imdb_id' => 'tt0401079', 'name' => 'Wicked Science', 'year' => '2004'],
                ],
            ], 200),
            'https://opensubtitles-v3.strem.io/subtitles/series/tt0401079:1:4.json' => Http::response([
                'subtitles' => [
                    [
                        'id' => '10295095',
                        'url' => 'https://subs.test/file/10295095.srt',
                        'lang' => 'eng',
                        'subtitleFileName' => 'Wicked Science S01E04 DVDRIP.srt',
                        'movieReleaseName' => 'Wicked Science S01E04',
                    ],
                ],
            ], 200),
        ]);

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
            'episode_number' => 4,
            'title' => 'Episode 4',
            'file_path' => storage_path('framework/testing/wicked_s01e04.mkv'),
        ]);

        $response = $this->postJson('/api/subtitles/verify-engine', [
            'media_id' => $episode->id,
            'media_type' => 'episode',
            'language' => 'en',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $results = $response->json('results');
        $this->assertNotEmpty($results);
        $this->assertEquals('OpenSubtitles', $results[0]['provider']);
        $this->assertEquals('en', $results[0]['language']);
        $this->assertEquals('https://subs.test/file/10295095.srt', $results[0]['download_url']);
    }

    public function test_download_for_media_downloads_actual_remote_srt_content(): void
    {
        $realSrtContent = "1\n00:00:01,000 --> 00:00:03,000\nHello from Toby.";

        Http::fake([
            'https://subs.test/download.srt' => Http::response($realSrtContent, 200),
        ]);

        $movie = MediaItem::create([
            'title' => 'Inception',
            'release_year' => 2010,
            'file_path' => storage_path('framework/testing/Inception.mkv'),
        ]);

        $response = $this->postJson('/api/subtitles/download', [
            'media_id' => $movie->id,
            'media_type' => 'movie',
            'language' => 'en',
            'download_url' => 'https://subs.test/download.srt',
            'file_name' => 'Inception.en.srt',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $subId = $response->json('subtitle.id');
        $this->assertNotNull($subId);

        $sub = Subtitle::find($subId);
        $this->assertNotNull($sub);
        $this->assertFileExists($sub->file_path);
        $this->assertEquals($realSrtContent, trim(File::get($sub->file_path)));

        // Clean up test file
        @unlink($sub->file_path);
    }
}
