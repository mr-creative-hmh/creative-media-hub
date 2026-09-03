<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Series;
use App\Services\Scanner\VirtualLibraryScannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for the incremental scan mode (default behavior).
 *
 * Incremental scans should skip files that are already indexed in the
 * MediaItem or Episode tables, while indexing brand-new files. This is
 * the behind-the-scenes fix that lets users add a folder to the monitored
 * list without dupicating existing library entries on re-scan.
 */
class IncrementalScanTest extends TestCase
{
    use RefreshDatabase;

    protected string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->testDir = storage_path('app/test_incremental_media');

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

    /**
     * Helper: create a dummy video file large enough to pass the scanner's
     * size filter (>15MB in non-testing env). The scanner skips files under
     * 15MB unless the path contains "test".
     */
    protected function createVideo(string $filename, int $mb = 20): string
    {
        $path = "{$this->testDir}/{$filename}";
        File::put($path, 'dummy test video content '.$filename);

        return $path;
    }

    /**
     * Helper: run the scanner's queue to completion.
     */
    protected function drainScanner(VirtualLibraryScannerService $scanner): void
    {
        $guard = 0;
        while (true) {
            $batchRes = $scanner->processBatch(10);
            if (! $batchRes['has_more']) {
                break;
            }
            if (++$guard > 50) {
                $this->fail('Scanner did not drain within 50 batches.');
            }
        }
    }

    public function test_incremental_scan_indexes_new_files_but_skips_existing(): void
    {
        // --- First scan: index two movies ---
        $this->createVideo('Inception.2010.1080p.mkv');
        $this->createVideo('Interstellar.2014.1080p.mkv');

        $scanner = app(VirtualLibraryScannerService::class);
        $init = $scanner->initScan([
            ['path' => $this->testDir, 'type' => 'movies'],
        ], 'incremental');

        // Both new files should be queued
        $this->assertEquals('scanning', $init['status']);
        $this->assertEquals(2, $init['total_files']);

        $this->drainScanner($scanner);
        $this->assertEquals(2, MediaItem::count());

        // --- Second scan: add a new file, then re-run incremental ---
        $this->createVideo('Dune.2021.1080p.mkv');

        $scanner->initScan([
            ['path' => $this->testDir, 'type' => 'movies'],
        ], 'incremental');

        // Only the NEW file (Dune) should be queued; existing two are skipped
        $status = $scanner->getScanStatus();
        $this->assertEquals(1, $status['total_files']);

        $this->drainScanner($scanner);
        $this->assertEquals(3, MediaItem::count());
        $this->assertNotNull(MediaItem::where('file_path', 'like', '%Dune%')->first());
    }

    public function test_incremental_mode_is_the_default(): void
    {
        $this->createVideo('Mad.Max.2015.1080p.mkv');

        // No scan_mode param passed -> should default to incremental
        $scanner = app(VirtualLibraryScannerService::class);
        $init = $scanner->initScan([
            ['path' => $this->testDir, 'type' => 'movies'],
        ]);

        $this->assertEquals('incremental', $init['scan_mode']);
    }

    public function test_fresh_scan_reindexes_everything_including_existing(): void
    {
        $this->createVideo('Inception.2010.1080p.mkv');
        $scanner = app(VirtualLibraryScannerService::class);

        // First scan indexes the file
        $scanner->initScan([['path' => $this->testDir, 'type' => 'movies']], 'incremental');
        $this->drainScanner($scanner);
        $this->assertEquals(1, MediaItem::count());

        // Fresh scan (queue not wiped, but scan_mode='fresh') re-queues the file.
        // Note: fresh mode skips the filterAlreadyIndexedFiles() step, re-queueing
        // the existing file. The service's indexMovie() will find the existing item
        // by file_path and update/return it rather than duplicating.
        $init = $scanner->initScan([['path' => $this->testDir, 'type' => 'movies']], 'fresh');
        $this->assertEquals('fresh', $init['scan_mode']);
        $this->assertEquals(1, $init['total_files']);

        $this->drainScanner($scanner);

        // Should NOT duplicate the movie
        $this->assertEquals(1, MediaItem::count());
    }

    public function test_incremental_scan_skips_already_indexed_series_episodes(): void
    {
        // Two episodes of the same show
        $this->createVideo('Breaking.Bad.S01E01.1080p.mkv');
        $this->createVideo('Breaking.Bad.S01E02.1080p.mkv');

        $scanner = app(VirtualLibraryScannerService::class);
        $scanner->initScan([['path' => $this->testDir, 'type' => 'series']], 'incremental');
        $this->drainScanner($scanner);

        $this->assertEquals(1, Series::count());
        $this->assertEquals(2, Episode::count());

        // Re-run incremental scan on same dir -> nothing new to index
        $scanner->initScan([['path' => $this->testDir, 'type' => 'series']], 'incremental');
        $status = $scanner->getScanStatus();
        $this->assertEquals(0, $status['total_files']);
        $this->assertEquals('completed', $status['status']);

        $this->assertEquals(2, Episode::count());
    }

    public function test_scan_folder_incremental_skips_existing(): void
    {
        $this->createVideo('BladeRunner.2049.1080p.mkv');

        $scanner = app(VirtualLibraryScannerService::class);
        $scanner->initScanForFolder($this->testDir, 'movies', 'incremental');
        $this->drainScanner($scanner);
        $this->assertEquals(1, MediaItem::count());

        // Re-scan the same folder incrementally -> nothing new
        $scanner->initScanForFolder($this->testDir, 'movies', 'incremental');
        $status = $scanner->getScanStatus();
        $this->assertEquals(0, $status['total_files']);

        // Add a second file and confirm only that one is picked up
        $this->createVideo('Arrival.2016.1080p.mkv');
        $scanner->initScanForFolder($this->testDir, 'movies', 'incremental');
        $status = $scanner->getScanStatus();
        $this->assertEquals(1, $status['total_files']);

        $this->drainScanner($scanner);
        $this->assertEquals(2, MediaItem::count());
    }
}
