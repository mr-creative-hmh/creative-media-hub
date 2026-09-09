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
}
