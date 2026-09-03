<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\Series;
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
        $this->assertEquals('Game of Thrones', $folderSeriesParsed['clean_title']);
        $this->assertEquals(3, $folderSeriesParsed['season']);
    }

    public function test_series_episodes_group_under_single_show_and_seasons(): void
    {
        $testDir = storage_path('app/test_series_media');
        if (! File::isDirectory($testDir)) {
            File::makeDirectory($testDir, 0755, true);
        }

        // Create files > 15MB to pass the scanner's size filter (test env may not be detected correctly)
        File::put("{$testDir}/Dark.Matter.S01E01.1080p.mkv", str_repeat('x', 20 * 1024 * 1024));
        File::put("{$testDir}/Dark.Matter.S01E01.ar.srt", 'sub1_ar');
        File::put("{$testDir}/Dark.Matter.S01E01.en.srt", 'sub1_en');
        File::put("{$testDir}/Dark.Matter.S01E02.1080p.mkv", str_repeat('x', 20 * 1024 * 1024));
        File::put("{$testDir}/Dark.Matter.S02E01.1080p.mkv", str_repeat('x', 20 * 1024 * 1024));

        $scanner = app(VirtualLibraryScannerService::class);
        $scanner->initScan([
            ['path' => $testDir, 'type' => 'series'],
        ]);

        // Process batches until done
        while (true) {
            $batchRes = $scanner->processBatch(10);
            if (! $batchRes['has_more']) {
                break;
            }
        }

        $this->assertGreaterThanOrEqual(1, Series::count());
        $series = Series::where('title', 'like', '%Dark Matter%')->first();
        $this->assertNotNull($series);
        $this->assertEquals(2, $series->seasons()->count());
        $this->assertEquals(3, $series->episodes()->count());

        // Check subtitles linked to S01E01
        $ep1 = Episode::where('series_id', $series->id)->where('episode_number', 1)->first();
        $this->assertNotNull($ep1);
        $this->assertGreaterThanOrEqual(2, $ep1->subtitles()->count());

        // Cleanup
        File::deleteDirectory($testDir);
    }

    public function test_physical_organizer_dry_run_generates_correct_actions(): void
    {
        $testDir = storage_path('app/test_organizer_media');
        if (! File::isDirectory($testDir)) {
            File::makeDirectory($testDir, 0755, true);
        }

        File::put("{$testDir}/Inception.2010.1080p.mkv", 'dummy');
        File::put("{$testDir}/Inception.2010.ar.srt", 'dummy_sub');

        $fsScanner = app(FilesystemScannerService::class);
        $scanned = $fsScanner->scanDirectory($testDir);

        $organizer = app(PhysicalOrganizerService::class);
        $plan = $organizer->generateDryRun($scanned, 'C:/MediaLibrary');

        $this->assertNotEmpty($plan);
        $firstItem = $plan[0];
        $this->assertStringContainsString('Inception (2010)', $firstItem['destination_path']);
        $this->assertStringContainsString('Movies', $firstItem['destination_path']);

        // Cleanup
        File::deleteDirectory($testDir);
    }
}
