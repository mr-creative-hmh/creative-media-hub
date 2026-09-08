<?php

namespace Tests\Unit;

use App\Services\Organizer\PhysicalOrganizerService;
use App\Services\Organizer\SceneNameParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_parser_handles_movies_with_resolutions_and_codecs(): void
    {
        $parser = new SceneNameParserService;
        $res = $parser->parse('Interstellar.2014.IMAX.2160p.UHD.HDR.BluRay.x265-SPARKS.mkv');

        $this->assertEquals('movie', $res['type']);
        $this->assertEquals('Interstellar', $res['clean_title']);
        $this->assertEquals(2014, $res['year']);
        $this->assertEquals('4K UHD', $res['resolution']);
        $this->assertEquals('HEVC / H.265', $res['codec']);
        $this->assertEquals('BluRay', $res['source']);
        $this->assertEquals('SPARKS', $res['group']);
    }

    public function test_scene_parser_handles_tv_series_with_seasons_and_episodes(): void
    {
        $parser = new SceneNameParserService;
        $res = $parser->parse('Stranger.Things.S04E07.The.Massacre.at.Hawkins.Lab.1080p.NF.WEB-DL.DDP5.1.Atmos.x264-FLUX.mkv');

        $this->assertEquals('series', $res['type']);
        $this->assertEquals('Stranger Things', $res['clean_title']);
        $this->assertEquals(4, $res['season']);
        $this->assertEquals(7, $res['episode']);
        $this->assertEquals('1080p FHD', $res['resolution']);
        $this->assertEquals('H.264 / AVC', $res['codec']);
        $this->assertEquals('Dolby Atmos', $res['audio']);
    }

    public function test_physical_organizer_generates_standard_dry_run_paths(): void
    {
        $parser = new SceneNameParserService;
        $organizer = new PhysicalOrganizerService($parser);

        $scanned = [
            [
                'path' => 'C:/Downloads/Inception.2010.1080p.BluRay.x264.mkv',
                'filename' => 'Inception.2010.1080p.BluRay.x264.mkv',
                'size_bytes' => 12000000000,
                'parsed' => $parser->parse('Inception.2010.1080p.BluRay.x264.mkv'),
            ],
        ];

        $plan = $organizer->generateDryRun($scanned, 'C:/Media');

        $this->assertCount(1, $plan);
        $this->assertEquals('C:/Downloads/Inception.2010.1080p.BluRay.x264.mkv', $plan[0]['source_path']);
        $this->assertEquals('C:/Media/Movies/Inception (2010)/Inception (2010) [1080p].mkv', $plan[0]['destination_path']);
    }

    public function test_physical_organizer_handles_movie_collections(): void
    {
        $parser = new SceneNameParserService;
        $organizer = new PhysicalOrganizerService($parser);

        $scanned = [
            [
                'path' => 'H:/Entertainment/Movies/Fast & Furious/1/The Fast and the Furious (2001).mkv',
                'filename' => 'The Fast and the Furious (2001).mkv',
                'size_bytes' => 5000000000,
                'parsed' => $parser->parse('H:/Entertainment/Movies/Fast & Furious/1/The Fast and the Furious (2001).mkv'),
            ],
            [
                'path' => 'H:/Entertainment/Movies/Inception (2010)/Inception (2010).mkv',
                'filename' => 'Inception (2010).mkv',
                'size_bytes' => 8000000000,
                'parsed' => $parser->parse('H:/Entertainment/Movies/Inception (2010)/Inception (2010).mkv'),
            ],
        ];

        $template = '{Type}/Collections/{Collection}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}';
        $plan = $organizer->generateDryRun($scanned, 'H:/Entertainment', $template);

        $this->assertCount(2, $plan);
        // Collected movie should be inside Collections/Fast & Furious
        $this->assertEquals(
            'H:/Entertainment/Movies/Collections/Fast & Furious/The Fast and the Furious (2001)/The Fast and the Furious (2001) [1080p].mkv',
            $plan[0]['destination_path']
        );
        // Non-collected movie should cleanly unwrap without Collections/
        $this->assertEquals(
            'H:/Entertainment/Movies/Inception (2010)/Inception (2010) [1080p].mkv',
            $plan[1]['destination_path']
        );
    }

    public function test_physical_organizer_classifies_genres_zero_key(): void
    {
        $parser = new SceneNameParserService;
        $organizer = new PhysicalOrganizerService($parser);

        $scanned = [
            [
                'path' => 'C:/Downloads/Oppenheimer.2023.1080p.mkv',
                'filename' => 'Oppenheimer.2023.1080p.mkv',
                'size_bytes' => 10000000000,
                'parsed' => $parser->parse('Oppenheimer.2023.1080p.mkv'),
            ],
            [
                'path' => 'C:/Downloads/Shrek.2001.1080p.mkv',
                'filename' => 'Shrek.2001.1080p.mkv',
                'size_bytes' => 4000000000,
                'parsed' => $parser->parse('Shrek.2001.1080p.mkv'),
            ],
        ];

        $template = '{Type}/{Genre}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}';
        $plan = $organizer->generateDryRun($scanned, 'C:/Media', $template);

        $this->assertCount(2, $plan);
        $this->assertStringContainsString('Movies/Drama & History/Oppenheimer (2023)', $plan[0]['destination_path']);
        $this->assertStringContainsString('Movies/Animation/Shrek (2001)', $plan[1]['destination_path']);
    }

    public function test_filesystem_scanner_browse_directory_root(): void
    {
        $scanner = app(\App\Services\Organizer\FilesystemScannerService::class);
        $result = $scanner->browseDirectory(null);

        $this->assertArrayHasKey('drives', $result);
        $this->assertArrayHasKey('shortcuts', $result);
        $this->assertArrayHasKey('directories', $result);
        $this->assertNotEmpty($result['drives']);
        $this->assertNull($result['current_path']);
    }

    public function test_filesystem_scanner_browse_directory_path(): void
    {
        $scanner = app(\App\Services\Organizer\FilesystemScannerService::class);
        $tempDir = str_replace('\\', '/', sys_get_temp_dir()) . '/cmh_test_' . uniqid();
        @mkdir($tempDir . '/SubFolderA/Nested', 0777, true);
        @mkdir($tempDir . '/SubFolderB', 0777, true);

        try {
            $result = $scanner->browseDirectory($tempDir);

            $this->assertEquals($tempDir, $result['current_path']);
            $this->assertCount(2, $result['directories']);
            $this->assertEquals('SubFolderA', $result['directories'][0]['name']);
            $this->assertTrue($result['directories'][0]['has_children']);
            $this->assertEquals('SubFolderB', $result['directories'][1]['name']);
            $this->assertFalse($result['directories'][1]['has_children']);
        } finally {
            @rmdir($tempDir . '/SubFolderA/Nested');
            @rmdir($tempDir . '/SubFolderA');
            @rmdir($tempDir . '/SubFolderB');
            @rmdir($tempDir);
        }
    }

    public function test_batch_plan_generation_lifecycle(): void
    {
        $parser = new SceneNameParserService;
        $organizer = new PhysicalOrganizerService($parser);

        $mockFiles = [
            [
                'path' => 'C:/Downloads/Gladiator.2000.1080p.mkv',
                'filename' => 'Gladiator.2000.1080p.mkv',
                'size_bytes' => 5000000000,
                'parsed' => $parser->parse('Gladiator.2000.1080p.mkv'),
            ],
            [
                'path' => 'C:/Downloads/Avatar.2009.1080p.mkv',
                'filename' => 'Avatar.2009.1080p.mkv',
                'size_bytes' => 8000000000,
                'parsed' => $parser->parse('Avatar.2009.1080p.mkv'),
            ],
        ];

        // 1. Init
        $init = $organizer->initPlanGeneration(
            'C:/Downloads',
            'C:/Media',
            '{Type}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}',
            null,
            true,
            ['files' => $mockFiles]
        );

        $this->assertTrue($init['is_active']);
        $this->assertEquals(2, $init['total_files']);

        // 2. Process batch of 1 item
        $batch1 = $organizer->processPlanBatch(1);
        $this->assertTrue($batch1['has_more']);
        $this->assertFalse($batch1['is_completed']);
        $this->assertEquals(1, $batch1['processed_count']);
        $this->assertEquals(50, $batch1['progress_percent']);

        // 3. Process remaining item
        $batch2 = $organizer->processPlanBatch(1);
        $this->assertFalse($batch2['has_more']);
        $this->assertTrue($batch2['is_completed']);
        $this->assertEquals(2, $batch2['processed_count']);
        $this->assertEquals(100, $batch2['progress_percent']);
        $this->assertCount(2, $batch2['plan']);

        // Check destination paths
        $this->assertStringContainsString('Movies/Gladiator (2000)/Gladiator (2000) [1080p].mkv', $batch2['plan'][0]['destination_path']);
        $this->assertStringContainsString('Movies/Avatar (2009)/Avatar (2009) [1080p].mkv', $batch2['plan'][1]['destination_path']);

        // 4. Cancel
        $cancel = $organizer->cancelPlanGeneration();
        $this->assertTrue($cancel['cancelled']);
    }
}
