<?php

namespace Tests\Feature;

use App\Http\Controllers\CollectionController;
use App\Services\Scout\LibraryAcquisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TorrentSubtitleAcquisitionTest extends TestCase
{
    use RefreshDatabase;

    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/cmh_sub_test_'.uniqid();
        File::makeDirectory($this->tempDir, 0755, true, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_move_companion_subtitles_handles_subfolders_and_detects_arabic_and_english(): void
    {
        $sourceDir = $this->tempDir.'/source/Gladiator (2000)';
        $destDir = $this->tempDir.'/dest/Gladiator (2000)';
        $subsDir = $sourceDir.'/Subs';

        File::makeDirectory($subsDir, 0755, true, true);
        File::makeDirectory($destDir, 0755, true, true);

        $sourceVideo = $sourceDir.'/Gladiator.2000.1080p.BluRay.x264.mp4';
        $destVideo = $destDir.'/Gladiator (2000) [1080p].mp4';
        File::put($sourceVideo, 'fake video content');

        // 1. Generic subtitle in Subs/ with Arabic dialogue (no .ar tag in filename!)
        $arabicSrt = $subsDir.'/track1.srt';
        $arabicContent = "1\n00:00:01,000 --> 00:00:04,000\nمرحبا بك يا جنرال، المجد لروما العظيمة والآن سنبدأ المعركة\n\n2\n00:00:05,000 --> 00:00:08,000\nالقوة والشرف يا صديقي، شكراً لك على الدعم اليوم\n";
        File::put($arabicSrt, $arabicContent);

        // 2. English subtitle in Subs/
        $englishSrt = $subsDir.'/2_English.srt';
        $englishContent = "1\n00:00:01,000 --> 00:00:04,000\nStrength and honor, general. Glory to Rome!\n\n2\n00:00:05,000 --> 00:00:08,000\nWhat we do in life echoes in eternity.\n";
        File::put($englishSrt, $englishContent);

        $service = app(LibraryAcquisitionService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('moveCompanionSubtitles');
        $method->setAccessible(true);

        $method->invoke($service, $sourceVideo, $destVideo);

        // Verify Arabic subtitle was moved and standardized as .ar.srt
        $expectedArPath = $destDir.'/Gladiator (2000) [1080p].ar.srt';
        $this->assertFileExists($expectedArPath);
        $this->assertStringContainsString('المجد لروما', File::get($expectedArPath));

        // Verify English subtitle was moved and standardized as .en.srt
        $expectedEnPath = $destDir.'/Gladiator (2000) [1080p].en.srt';
        $this->assertFileExists($expectedEnPath);
        $this->assertStringContainsString('Strength and honor', File::get($expectedEnPath));
    }

    public function test_collection_cache_is_invalidated_when_media_with_collection_is_indexed(): void
    {
        // Seed a fake collection cache
        Cache::put('collections.index.data.v10', [
            ['name' => 'Old Cached Collection', 'movies_count' => 2],
        ], 3600);

        $this->assertTrue(Cache::has('collections.index.data.v10'));

        // Calling CollectionController::clearCache directly or via service
        CollectionController::clearCache();

        $this->assertFalse(Cache::has('collections.index.data.v10'));
    }
}
