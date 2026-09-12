<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Services\Organizer\PhysicalOrganizerService;
use App\Services\Scanner\VirtualLibraryScannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScannerRelocationAndUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->testDir = storage_path('app/test_scanner_upgrade');

        if (File::isDirectory($this->testDir)) {
            File::deleteDirectory($this->testDir);
        }
        File::makeDirectory($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->testDir)) {
            File::deleteDirectory($this->testDir);
        }
        parent::tearDown();
    }

    public function test_series_episode_relocation_or_upgrade_does_not_duplicate_rows(): void
    {
        $scanner = app(VirtualLibraryScannerService::class);

        $oldFile = "{$this->testDir}/Breaking Bad S01E01 [720p].mkv";
        file_put_contents($oldFile, str_repeat('A', 1024));

        $reflectionMethod = new \ReflectionMethod($scanner, 'indexSeriesEpisode');
        $reflectionMethod->setAccessible(true);

        // Index first time at 720p
        $file1 = [
            'path' => $oldFile,
            'size' => 1024,
            'filename' => basename($oldFile),
            'extension' => 'mkv',
        ];
        $parsed1 = [
            'type' => 'series',
            'series_title' => 'Breaking Bad',
            'season' => 1,
            'episode' => 1,
            'resolution' => '720p HD',
        ];
        $ep1 = $reflectionMethod->invoke($scanner, $file1, $parsed1);

        $this->assertNotNull($ep1);
        $this->assertEquals(1, Episode::where('series_id', $ep1->series_id)->where('episode_number', 1)->count());

        // Now upgrade to 1080p at a different file path
        $upgradedFile = "{$this->testDir}/Breaking Bad - S01E01 - Pilot [1080p].mkv";
        file_put_contents($upgradedFile, str_repeat('B', 2048));

        $file2 = [
            'path' => $upgradedFile,
            'size' => 2048,
            'filename' => basename($upgradedFile),
            'extension' => 'mkv',
        ];
        $parsed2 = [
            'type' => 'series',
            'series_title' => 'Breaking Bad',
            'season' => 1,
            'episode' => 1,
            'resolution' => '1080p FHD',
        ];
        $ep2 = $reflectionMethod->invoke($scanner, $file2, $parsed2);

        // Must update existing record in-place: ZERO duplicate rows!
        $this->assertEquals(1, Episode::where('series_id', $ep1->series_id)->where('episode_number', 1)->count());
        $fresh = Episode::find($ep1->id);
        $this->assertEquals(str_replace('\\', '/', $upgradedFile), $fresh->file_path);
        $this->assertEquals('1080p FHD', $fresh->resolution);
    }

    public function test_physical_organizer_preserves_arabic_series_directory(): void
    {
        $organizer = app(PhysicalOrganizerService::class);

        $arabicEp = [
            'path' => 'H:/Entertainment/TV Shows/Arabic Series/The Four Seasons/Season 01/The.Four.Seasons.S01E01.mp4',
            'filename' => 'The.Four.Seasons.S01E01.mp4',
        ];

        $planItem = $organizer->generatePlanItem($arabicEp, 'H:/Entertainment');

        $this->assertStringContainsString('TV Shows/Arabic Series/', $planItem['destination_path']);
    }

    public function test_collection_controller_strictly_enforces_multi_movie_collection_rule(): void
    {
        $testColId = 999999;
        MediaItem::create([
            'title' => 'Test Movie 1',
            'slug' => 'test-movie-1-2024',
            'release_year' => 2024,
            'collection_name' => 'Test Multi Collection',
            'collection_id' => $testColId,
            'file_path' => 'H:/Entertainment/Movies/Action/Test Multi Collection/Test Movie 1 (2024)/Test Movie 1.mp4',
            'folder_path' => 'H:/Entertainment/Movies/Action/Test Multi Collection/Test Movie 1 (2024)',
        ]);

        $response = $this->get('/collections');
        $response->assertOk();

        $page = $response->viewData('page') ?? $response->original->getData()['page'] ?? null;
        $collections = collect($page['props']['collections']);
        $single = $collections->firstWhere('name', 'Test Multi Collection');
        // Single movie collection should NOT appear
        $this->assertNull($single);

        // Add second movie
        MediaItem::create([
            'title' => 'Test Movie 2',
            'slug' => 'test-movie-2-2025',
            'release_year' => 2025,
            'collection_name' => 'Test Multi Collection',
            'collection_id' => $testColId,
            'file_path' => 'H:/Entertainment/Movies/Action/Test Multi Collection/Test Movie 2 (2025)/Test Movie 2.mp4',
            'folder_path' => 'H:/Entertainment/Movies/Action/Test Multi Collection/Test Movie 2 (2025)',
        ]);

        $response2 = $this->get('/collections');
        $page2 = $response2->viewData('page') ?? $response2->original->getData()['page'] ?? null;
        $collections2 = collect($page2['props']['collections']);
        $multi = $collections2->firstWhere('name', 'Test Multi Collection');
        $this->assertNotNull($multi);
        $this->assertEquals(2, $multi['movies_count']);
    }
}
