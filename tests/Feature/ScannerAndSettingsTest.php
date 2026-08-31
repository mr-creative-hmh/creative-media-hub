<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScannerAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_page_renders_successfully(): void
    {
        $response = $this->get(route('scanner.index'));
        $response->assertStatus(200);
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
