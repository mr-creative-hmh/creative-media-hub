<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Models\Subtitle;
use App\Services\Organizer\FilesystemScannerService;
use App\Services\Organizer\PhysicalOrganizerService;
use App\Services\Organizer\SceneNameParserService;
use App\Services\Scanner\VirtualLibraryScannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ScannerAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_page_renders_successfully(): void
    {
        $response = $this->get(route('scanner.index'));
        $response->assertStatus(200);
    }

    public function test_scene_parser_handles_movies_and_series_correctly(): void
    {
        $parser = app(SceneNameParserService::class);

        $movieParsed = $parser->parse('C:/Media/Movies/Inception.2010.1080p.BluRay.x264-SPARKS.mkv');
        $this->assertEquals('movie', $movieParsed['type']);
        $this->assertEquals('Inception', $movieParsed['clean_title']);
        $this->assertEquals(2010, $movieParsed['year']);
        $this->assertEquals('1080p FHD', $movieParsed['resolution']);

        $seriesParsed = $parser->parse('C:/Media/TV/Breaking.Bad.S01E02.720p.HDTV.x264.mkv');
        $this->assertEquals('series', $seriesParsed['type']);
        $this->assertEquals('Breaking Bad', $seriesParsed['clean_title']);
        $this->assertEquals(1, $seriesParsed['season']);
        $this->assertEquals(2, $seriesParsed['episode']);

        $folderSeriesParsed = $parser->parse('C:/Media/TV Shows/Game of Thrones/Season 03/05 - Kissed by Fire.mkv');
        $this->assertEquals('series', $folderSeriesParsed['type']);
        $this->assertEquals('Game Of Thrones', $folderSeriesParsed['clean_title']);
        $this->assertEquals(3, $folderSeriesParsed['season']);
    }

    public function test_series_episodes_group_under_single_show_and_seasons(): void
    {
        $testDir = storage_path('app/test_series_media');
        if (!File::isDirectory($testDir)) {
            File::makeDirectory($testDir, 0755, true);
        }

        // Create mock episode video files and subtitle files
        File::put("{$testDir}/Dark.Matter.S01E01.1080p.mkv", 'video1');
        File::put("{$testDir}/Dark.Matter.S01E01.ar.srt", 'sub1_ar');
        File::put("{$testDir}/Dark.Matter.S01E01.en.srt", 'sub1_en');
        File::put("{$testDir}/Dark.Matter.S01E02.1080p.mkv", 'video2');
        File::put("{$testDir}/Dark.Matter.S02E01.1080p.mkv", 'video3');

        $scanner = app(VirtualLibraryScannerService::class);
        $init = $scanner->initScan([['path' => $testDir, 'type' => 'series']]);
        $this->assertTrue($init['success']);

        // Process all batches
        do {
            $batchResult = $scanner->processNextBatch(5);
        } while (!empty($batchResult['has_more']));

        // Verify only 1 Series was created!
        $this->assertEquals(1, Series::count());
        $series = Series::first();
        $this->assertEquals('Dark Matter', $series->title);

        // Verify 2 Seasons were created!
        $this->assertEquals(2, Season::where('series_id', $series->id)->count());

        // Verify 3 Episodes were created!
        $this->assertEquals(3, Episode::where('series_id', $series->id)->count());

        // Verify subtitles attached to Episode 1
        $ep1 = Episode::where('episode_number', 1)->where('season_id', Season::where('season_number', 1)->first()->id)->first();
        $this->assertNotNull($ep1);
        $this->assertEquals(2, Subtitle::where('subtitlable_id', $ep1->id)->where('subtitlable_type', Episode::class)->count());

        // Clean up test files
        File::deleteDirectory($testDir);
    }

    public function test_can_add_and_remove_monitored_directories(): void
    {
        $addResponse = $this->postJson(route('api.scanner.directories.add'), [
            'path' => 'C:/MyTestMovies',
            'type' => 'movies',
        ]);

        $addResponse->assertStatus(200);
        $addResponse->assertJson(['success' => true]);

        $dirs = $addResponse->json('directories');
        $this->assertNotEmpty($dirs);
        $addedId = $dirs[count($dirs) - 1]['id'];

        $removeResponse = $this->deleteJson(route('api.scanner.directories.remove'), [
            'id' => $addedId,
        ]);

        $removeResponse->assertStatus(200);
        $removeResponse->assertJson(['success' => true]);
    }

    public function test_can_control_scan_job_lifecycle(): void
    {
        $statusRes = $this->getJson(route('api.scanner.status'));
        $statusRes->assertStatus(200);
        $this->assertArrayHasKey('status', $statusRes->json());

        $pauseRes = $this->postJson(route('api.scanner.pause'));
        $pauseRes->assertStatus(200);
        $this->assertEquals('paused', $pauseRes->json('status.status'));

        $resumeRes = $this->postJson(route('api.scanner.resume'));
        $resumeRes->assertStatus(200);
        $this->assertEquals('running', $resumeRes->json('status.status'));

        $cancelRes = $this->postJson(route('api.scanner.cancel'));
        $cancelRes->assertStatus(200);
        $this->assertEquals('cancelled', $cancelRes->json('status.status'));
    }

    public function test_can_clear_demo_catalog(): void
    {
        MediaItem::create([
            'title' => 'Sample Demo Film',
            'rating' => 8.5,
            'file_path' => 'C:/demo.mkv',
        ]);

        $this->assertEquals(1, MediaItem::count());

        $response = $this->postJson(route('api.library.clear-demo'));
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertEquals(0, MediaItem::count());
    }

    public function test_settings_page_renders_and_updates(): void
    {
        $pageResponse = $this->get(route('settings.index'));
        $pageResponse->assertStatus(200);

        $updateResponse = $this->postJson(route('api.settings.update'), [
            'tmdb_api_key' => 'test-tmdb-key-12345',
            'omdb_api_key' => 'test-omdb-key-67890',
            'opensubtitles_api_key' => 'test-opensubs-token',
            'default_language' => 'ar',
            'auto_fetch_metadata' => true,
            'auto_fetch_subtitles' => true,
        ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJson(['success' => true]);
    }

    public function test_can_verify_free_subtitle_engine(): void
    {
        $response = $this->postJson(route('api.subtitles.verify-engine'), [
            'query' => 'Inception',
            'language' => 'ar',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertArrayHasKey('engine_status', $response->json());
        $this->assertArrayHasKey('results', $response->json());
    }
}
