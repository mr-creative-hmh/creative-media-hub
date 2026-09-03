<?php

namespace Tests\Unit;

use App\Models\MediaItem;
use App\Models\Subtitle;
use App\Services\Subtitles\OpenSubtitlesService;
use App\Services\Subtitles\SubDlService;
use App\Services\Subtitles\SubtitleManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubtitleManagerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_missing_subtitles_detects_untranslated_movies(): void
    {
        $movie = MediaItem::create([
            'title' => 'Dune 2',
            'release_year' => 2024,
            'file_path' => 'C:/Media/Movies/Dune 2/Dune2.mkv',
        ]);

        $manager = new SubtitleManagerService(new OpenSubtitlesService, new SubDlService);
        $missing = $manager->findMissingSubtitles();

        $this->assertNotEmpty($missing);
        $this->assertTrue($missing[0]['missing_ar']);
        $this->assertTrue($missing[0]['missing_en']);
    }

    public function test_download_and_attach_subtitle_creates_record_and_file(): void
    {
        $movie = MediaItem::create([
            'title' => 'Inception',
            'release_year' => 2010,
            'file_path' => storage_path('framework/testing/Inception.mkv'),
        ]);

        $manager = new SubtitleManagerService(new OpenSubtitlesService, new SubDlService);
        $sub = $manager->downloadAndAttachMockSubtitle($movie, 'ar');

        $this->assertInstanceOf(Subtitle::class, $sub);
        $this->assertEquals('ar', $sub->language);
        $this->assertEquals('Arabic', $sub->language_name);
        $this->assertFileExists($sub->file_path);
    }
}
