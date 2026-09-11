<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Organizer\PhysicalOrganizerService;
use App\Services\Organizer\FilesystemScannerService;
use App\Models\MediaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrganizerCollectionDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected PhysicalOrganizerService $organizerService;
    protected FilesystemScannerService $scannerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizerService = app(PhysicalOrganizerService::class);
        $this->scannerService = app(FilesystemScannerService::class);
    }

    public function test_detects_collection_from_parent_directory_name(): void
    {
        $fakeFile = [
            'path' => 'D:/Downloads/Harry Potter Collection 2001-2011 1080p BluRay HEVC x265 5.1 BONE/Harry Potter and the Sorcerer\'s Stone (2001)/Harry Potter and the Sorcerer\'s Stone (2001).mkv',
            'filename' => 'Harry Potter and the Sorcerer\'s Stone (2001).mkv',
            'size' => 2500000000,
            'extension' => 'mkv',
            'parsed' => [
                'type' => 'movie',
                'clean_title' => 'Harry Potter and the Sorcerer\'s Stone',
                'year' => 2001,
                'resolution' => '1080p',
                'codec' => 'HEVC',
                'source' => 'BluRay',
            ],
            'subtitles' => [],
        ];

        $targetRoot = 'H:/Entertainment';
        $moviePattern = '{Type}/{Genre}/{Collection}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}';
        $seriesPattern = '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} [{CleanResolution}].{ext}';

        $planItem = $this->organizerService->generatePlanItem($fakeFile, $targetRoot, $moviePattern, $seriesPattern);

        $this->assertEquals('Harry Potter Collection', $planItem['collection_name']);
        $this->assertEquals('Fantasy', $planItem['genre']);
        $this->assertStringContainsString('Fantasy/Harry Potter Collection', str_replace('\\', '/', $planItem['destination_path']));
    }

    public function test_detects_collection_from_database_media_item(): void
    {
        MediaItem::create([
            'title' => 'Harry Potter and the Chamber of Secrets',
            'release_year' => 2002,
            'collection_name' => 'Harry Potter Collection',
            'file_path' => 'D:/dummy/path.mkv',
            'slug' => 'harry-potter-chamber-secrets-2002',
        ]);

        $fakeFile = [
            'path' => 'C:/Temp/Harry Potter and the Chamber of Secrets (2002) 1080p.mkv',
            'filename' => 'Harry Potter and the Chamber of Secrets (2002) 1080p.mkv',
            'size' => 2000000000,
            'extension' => 'mkv',
            'parsed' => [
                'type' => 'movie',
                'clean_title' => 'Harry Potter and the Chamber of Secrets',
                'year' => 2002,
                'resolution' => '1080p',
            ],
            'subtitles' => [],
        ];

        $targetRoot = 'H:/Entertainment';
        $moviePattern = '{Type}/{Genre}/{Collection}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}';
        $seriesPattern = '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02}.{ext}';

        $planItem = $this->organizerService->generatePlanItem($fakeFile, $targetRoot, $moviePattern, $seriesPattern);

        $this->assertEquals('Harry Potter Collection', $planItem['collection_name']);
        $this->assertStringContainsString('Harry Potter Collection', str_replace('\\', '/', $planItem['destination_path']));
    }

    public function test_standalone_movie_unwraps_cleanly_without_empty_collection_directory(): void
    {
        $fakeFile = [
            'path' => 'D:/Downloads/Inception (2010)/Inception (2010) [1080p].mkv',
            'filename' => 'Inception (2010) [1080p].mkv',
            'size' => 3000000000,
            'extension' => 'mkv',
            'parsed' => [
                'type' => 'movie',
                'clean_title' => 'Inception',
                'year' => 2010,
                'resolution' => '1080p',
            ],
            'subtitles' => [],
        ];

        $targetRoot = 'H:/Entertainment';
        $moviePattern = '{Type}/{Genre}/{Collection}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}';
        $seriesPattern = '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02}.{ext}';

        $planItem = $this->organizerService->generatePlanItem($fakeFile, $targetRoot, $moviePattern, $seriesPattern);

        $this->assertNull($planItem['collection_name']);
        $destPath = str_replace('\\', '/', $planItem['destination_path']);
        // Verify no double slashes or empty {Collection} in path
        $this->assertStringNotContainsString('//', $destPath);
        $this->assertStringNotContainsString('{Collection}', $destPath);
        $this->assertStringContainsString('Movies/Sci-Fi/Inception (2010)/Inception (2010) [1080p].mkv', $destPath);
    }

    public function test_pairs_companion_subtitles(): void
    {
        $fakeFile = [
            'path' => 'D:/Downloads/Interstellar (2014)/Interstellar (2014).mkv',
            'filename' => 'Interstellar (2014).mkv',
            'size' => 4000000000,
            'extension' => 'mkv',
            'parsed' => [
                'type' => 'movie',
                'clean_title' => 'Interstellar',
                'year' => 2014,
                'resolution' => '1080p',
            ],
            'subtitles' => [
                [
                    'path' => 'D:/Downloads/Interstellar (2014)/Interstellar (2014).ar.srt',
                    'filename' => 'Interstellar (2014).ar.srt',
                    'language' => 'ar',
                ],
                [
                    'path' => 'D:/Downloads/Interstellar (2014)/Interstellar (2014).en.srt',
                    'filename' => 'Interstellar (2014).en.srt',
                    'language' => 'en',
                ],
            ],
        ];

        $targetRoot = 'H:/Entertainment';
        $moviePattern = '{Type}/{Genre}/{Collection}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}';
        $seriesPattern = '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02}.{ext}';

        $planItem = $this->organizerService->generatePlanItem($fakeFile, $targetRoot, $moviePattern, $seriesPattern);

        $this->assertCount(2, $planItem['subtitles']);
        $sub1Dest = str_replace('\\', '/', $planItem['subtitles'][0]['destination']);
        $this->assertStringEndsWith('.ar.srt', $sub1Dest);
        $this->assertStringContainsString('Movies/Sci-Fi/Interstellar (2014)', $sub1Dest);
    }

    public function test_tv_show_year_formats_correctly_with_range_or_single_year_or_omitted(): void
    {
        $series1 = \App\Models\Series::create([
            'title' => 'Test Show A',
            'release_year' => 2004,
            'end_year' => 2006,
        ]);
        $season1 = \App\Models\Season::create([
            'series_id' => $series1->id,
            'season_number' => 1,
        ]);
        \App\Models\Episode::create([
            'series_id' => $series1->id,
            'season_id' => $season1->id,
            'episode_number' => 1,
            'title' => 'Pilot',
            'resolution' => '360p',
            'file_path' => 'D:/Media/Test Show A/Season 01/Test.Show.A.S01E01.avi',
        ]);

        $series2 = \App\Models\Series::create([
            'title' => 'Test Show B',
            'release_year' => 2022,
        ]);
        $season2 = \App\Models\Season::create([
            'series_id' => $series2->id,
            'season_number' => 1,
        ]);
        \App\Models\Episode::create([
            'series_id' => $series2->id,
            'season_id' => $season2->id,
            'episode_number' => 1,
            'title' => 'First Episode',
            'resolution' => '720p',
            'file_path' => 'D:/Media/Test Show B/Season 01/Test.Show.B.S01E01.mkv',
        ]);

        // 1. Show A with year range 2004 - 2006
        $fileA = [
            'path' => 'D:/Media/Test Show A/Season 01/Test.Show.A.S01E01.avi',
            'filename' => 'Test.Show.A.S01E01.avi',
            'parsed' => [
                'type' => 'series',
                'clean_title' => 'Test Show A',
                'season' => 1,
                'episode' => 1,
            ],
        ];
        $planA = $this->organizerService->generatePlanItem($fileA, 'H:/Organized');
        $this->assertEquals('2004 - 2006', $planA['year']);
        $this->assertEquals('360p', $planA['resolution']);
        $this->assertStringContainsString('TV Shows/Test Show A (2004 - 2006)/Season 01/Test Show A - S01E01 - Pilot [360p].avi', str_replace('\\', '/', $planA['destination_path']));

        // 2. Show B with single year 2022
        $fileB = [
            'path' => 'D:/Media/Test Show B/Season 01/Test.Show.B.S01E01.mkv',
            'filename' => 'Test.Show.B.S01E01.mkv',
            'parsed' => [
                'type' => 'series',
                'clean_title' => 'Test Show B',
                'season' => 1,
                'episode' => 1,
            ],
        ];
        $planB = $this->organizerService->generatePlanItem($fileB, 'H:/Organized');
        $this->assertEquals('2022', $planB['year']);
        $this->assertEquals('720p', $planB['resolution']);
        $this->assertStringContainsString('TV Shows/Test Show B (2022)/Season 01/Test Show B - S01E01 - First Episode [720p].mkv', str_replace('\\', '/', $planB['destination_path']));

        // 3. Unknown show without year: MUST NOT have empty parens () or trailing space
        $fileC = [
            'path' => 'D:/Media/Unknown Mystery Show/Season 01/Unknown.Mystery.Show.S01E01.mkv',
            'filename' => 'Unknown.Mystery.Show.S01E01.mkv',
            'parsed' => [
                'type' => 'series',
                'clean_title' => 'Unknown Mystery Show',
                'season' => 1,
                'episode' => 1,
            ],
        ];
        $planC = $this->organizerService->generatePlanItem($fileC, 'H:/Organized');
        $destC = str_replace('\\', '/', $planC['destination_path']);
        $this->assertStringContainsString('TV Shows/Unknown Mystery Show/Season 01/', $destC);
        $this->assertStringNotContainsString('Unknown Mystery Show /', $destC);
        $this->assertStringNotContainsString('()', $destC);
    }

    public function test_rings_of_power_tv_series_is_not_matched_to_movie_collection(): void
    {
        $file = [
            'path' => 'H:/Entertainment/TV Shows/The Lord of the Rings The Rings of Power/Season 01/The.Lord.of.the.Rings.The.Rings.of.Power.S01E01.1080p.mkv',
            'filename' => 'The.Lord.of.the.Rings.The.Rings.of.Power.S01E01.1080p.mkv',
            'parsed' => [
                'type' => 'series',
                'clean_title' => 'The Lord of the Rings the Rings of Power',
                'season' => 1,
                'episode' => 1,
                'resolution' => '1080p',
            ],
        ];

        $plan = $this->organizerService->generatePlanItem($file, 'H:/Organized');

        $this->assertNull($plan['collection_name']);
        $this->assertStringNotContainsString('Collection', $plan['destination_path']);
        $this->assertStringNotContainsString('The Lord of the Rings Collection', $plan['destination_path']);
    }
}
