<?php

namespace Tests\Feature;

use App\Models\DownloadItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DownloadManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloader_settings_can_be_retrieved_and_saved(): void
    {
        $getResponse = $this->getJson('/api/downloads/settings');
        $getResponse->assertStatus(200);
        $getResponse->assertJsonStructure(['settings', 'destinations']);

        $saveResponse = $this->postJson('/api/downloads/settings', [
            'movies_download_path' => 'D:/Media/CustomMovies',
            'series_download_path' => 'D:/Media/CustomSeries',
            'max_concurrent_downloads' => 4,
            'download_speed_limit_kb' => 5000,
        ]);

        $saveResponse->assertStatus(200);
        $saveResponse->assertJson([
            'success' => true,
            'settings' => [
                'movies_download_path' => 'D:/Media/CustomMovies',
                'series_download_path' => 'D:/Media/CustomSeries',
                'max_concurrent_downloads' => 4,
                'download_speed_limit_kb' => 5000,
            ],
        ]);
    }

    public function test_url_inspection_handles_direct_and_magnet(): void
    {
        // Direct stream inspect
        $directRes = $this->postJson('/api/downloads/inspect', [
            'url' => 'https://example.com/videos/Inception.2010.1080p.BluRay.mp4',
            'type' => 'direct',
        ]);
        $directRes->assertStatus(200);
        $this->assertEquals('direct', $directRes->json('download_type'));
        $this->assertStringContainsString('Inception', $directRes->json('title'));
        $this->assertEquals('movie', $directRes->json('media_type'));
        $this->assertNotEmpty($directRes->json('files'));

        // Magnet inspect
        $magnetRes = $this->postJson('/api/downloads/inspect', [
            'url' => 'magnet:?xt=urn:btih:d6b9f71c42959828e8ab66fa5a60000000000000&dn=Breaking+Bad+S01E01+720p',
            'type' => 'torrent',
        ]);
        $magnetRes->assertStatus(200);
        $this->assertEquals('torrent', $magnetRes->json('download_type'));
        $this->assertEquals('series', $magnetRes->json('media_type'));
        $this->assertNotEmpty($magnetRes->json('files'));
    }

    public function test_creating_download_with_torrent_selection_and_folder(): void
    {
        $res = $this->postJson('/api/downloads', [
            'title' => 'Oppenheimer (2023)',
            'media_type' => 'movie',
            'download_type' => 'torrent',
            'source_url' => 'magnet:?xt=urn:btih:abcdef1234567890abcdef1234567890abcdef12&dn=Oppenheimer.2023',
            'destination_folder' => 'D:/Media/CustomMovies',
            'selected_files' => [0],
            'torrent_files' => [
                ['index' => 0, 'path' => 'Oppenheimer.2023.mkv', 'size' => 4500000000, 'is_video' => true, 'selected' => true],
                ['index' => 1, 'path' => 'Sample.mkv', 'size' => 50000000, 'is_video' => true, 'selected' => false],
            ],
            'info_hash' => 'abcdef1234567890abcdef1234567890abcdef12',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('download_items', [
            'title' => 'Oppenheimer (2023)',
            'media_type' => 'movie',
            'download_type' => 'torrent',
        ]);

        $item = DownloadItem::where('title', 'Oppenheimer (2023)')->first();
        $this->assertNotNull($item);
        $this->assertEquals('torrent', $item->download_type);
        $this->assertNotEmpty($item->selected_files);
        $this->assertEquals([0], $item->selected_files);

        // Pause
        $pauseRes = $this->postJson("/api/downloads/{$item->id}/pause");
        $pauseRes->assertStatus(200);
        $this->assertEquals('paused', $item->fresh()->status);

        // Resume
        $resumeRes = $this->postJson("/api/downloads/{$item->id}/resume");
        $resumeRes->assertStatus(200);
        $this->assertEquals('downloading', $item->fresh()->status);

        // Process batch
        $batchRes = $this->postJson('/api/downloads/process-batch');
        $batchRes->assertStatus(200);
        $this->assertGreaterThan(0, $item->fresh()->downloaded_bytes);

        // Delete
        $delRes = $this->deleteJson("/api/downloads/{$item->id}");
        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('download_items', ['id' => $item->id]);
    }
}
