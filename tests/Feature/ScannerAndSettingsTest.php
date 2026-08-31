<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Models\Subtitle;
use App\Services\Metadata\ArtworkDownloadService;
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

        $movieParsed = $parser->parse('C:/Media/Movies/[YTS.MX] Inception.2010.1080p.BluRay.x264-SPARKS.mkv');
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

        File::put("{$testDir}/Dark.Matter.S01E01.1080p.mkv", 'video1');
        File::put("{$testDir}/Dark.Matter.S01E01.ar.srt", 'sub1_ar');
        File::put("{$testDir}/Dark.Matter.S01E01.en.srt", 'sub1_en');
        File::put("{$testDir}/Dark.Matter.S01E02.1080p.mkv", 'video2');
        File::put("{$testDir}/Dark.Matter.S02E01.1080p.mkv", 'video3');

        $scanner = app(VirtualLibraryScannerService::class);
        $init = $scanner->initScan([['path' => $testDir, 'type' => 'series']]);
        $this->assertTrue($init['success']);

        do {
            $batchResult = $scanner->processNextBatch(5);
        } while (!empty($batchResult['has_more']));

        $this->assertEquals(1, Series::count());
        $series = Series::first();
        $this->assertEquals('Dark Matter', $series->title);

        $this->assertEquals(2, Season::where('series_id', $series->id)->count());
        $this->assertEquals(3, Episode::where('series_id', $series->id)->count());

        $ep1 = Episode::where('episode_number', 1)->where('season_id', Season::where('season_number', 1)->first()->id)->first();
        $this->assertNotNull($ep1);
        $this->assertEquals(2, Subtitle::where('subtitlable_id', $ep1->id)->where('subtitlable_type', Episode::class)->count());

        File::deleteDirectory($testDir);
    }

    public function test_physical_organizer_synchronizes_virtual_library_database(): void
    {
        $sourceDir = storage_path('app/test_organizer_src');
        $destDir = storage_path('app/test_organizer_dest');
        File::makeDirectory($sourceDir, 0755, true);
        File::makeDirectory($destDir, 0755, true);

        $sourceFile = "{$sourceDir}/Inception.2010.mkv";
        $sourceSub = "{$sourceDir}/Inception.2010.ar.srt";
        File::put($sourceFile, 'dummy_video');
        File::put($sourceSub, 'dummy_sub');

        $movie = MediaItem::create([
            'title' => 'Inception',
            'release_year' => 2010,
            'file_path' => str_replace('\\', '/', $sourceFile),
            'rating' => 8.8,
        ]);

        $sub = Subtitle::create([
            'subtitlable_id' => $movie->id,
            'subtitlable_type' => MediaItem::class,
            'language' => 'ar',
            'file_path' => str_replace('\\', '/', $sourceSub),
        ]);

        $organizer = app(PhysicalOrganizerService::class);
        $scannedFiles = [[
            'path' => $sourceFile,
            'filename' => 'Inception.2010.mkv',
            'subtitles' => [['path' => $sourceSub, 'language' => 'ar', 'extension' => 'srt']],
        ]];

        $dryRun = $organizer->generateDryRun($scannedFiles, $destDir);
        $this->assertNotEmpty($dryRun);

        $executeRes = $organizer->execute($dryRun, 'move');
        $this->assertEquals(1, $executeRes['success_count']);

        // Check that database record was synchronized with new destination path!
        $movie->refresh();
        $this->assertNotEquals($sourceFile, $movie->file_path);
        $this->assertTrue(File::exists($movie->file_path));

        // Clean up
        File::deleteDirectory($sourceDir);
        File::deleteDirectory($destDir);
    }

    public function test_can_fix_match_and_update_movie_metadata(): void
    {
        $movie = MediaItem::create([
            'title' => 'Unmatched Movie',
            'file_path' => 'C:/test_movie.mkv',
        ]);

        $response = $this->postJson(route('api.media.fix-match', $movie->id), [
            'title' => 'Interstellar',
            'year' => 2014,
            'overview' => 'A team of explorers travel through a wormhole in space.',
            'rating' => 8.7,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $movie->refresh();
        $this->assertEquals('Interstellar', $movie->title);
        $this->assertEquals(2014, $movie->release_year);
    }

    public function test_can_enrich_missing_metadata_batch(): void
    {
        MediaItem::create([
            'title' => 'Gladiator',
            'release_year' => 2000,
            'overview' => 'Enjoy watching Gladiator.',
            'file_path' => 'C:/gladiator.mkv',
        ]);

        $response = $this->postJson(route('api.library.enrich-missing'));
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
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
