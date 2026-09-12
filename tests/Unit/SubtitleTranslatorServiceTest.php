<?php

namespace Tests\Unit;

use App\Models\MediaItem;
use App\Models\Subtitle;
use App\Services\Subtitles\SubtitleTranslatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubtitleTranslatorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_and_reconstruct_srt(): void
    {
        $translator = app(SubtitleTranslatorService::class);

        $srtInput = "1\n00:01:23,456 --> 00:01:26,789\nHello, world!\nSecond line.\n\n2\n00:01:28,000 --> 00:01:31,500\nHow are you doing today?\n";

        $cues = $translator->parseSrt($srtInput);

        $this->assertCount(2, $cues);
        $this->assertEquals('1', $cues[0]['index']);
        $this->assertEquals('00:01:23,456 --> 00:01:26,789', $cues[0]['timeline']);
        $this->assertEquals("Hello, world!\nSecond line.", $cues[0]['text']);

        $this->assertEquals('2', $cues[1]['index']);
        $this->assertEquals('00:01:28,000 --> 00:01:31,500', $cues[1]['timeline']);
        $this->assertEquals('How are you doing today?', $cues[1]['text']);

        $reconstructed = $translator->reconstructSrt($cues);
        $this->assertStringContainsString('00:01:23,456 --> 00:01:26,789', $reconstructed);
        $this->assertStringContainsString('Hello, world!', $reconstructed);
        $this->assertStringContainsString('00:01:28,000 --> 00:01:31,500', $reconstructed);
    }

    public function test_translate_srt_content_preserves_timing_and_structure(): void
    {
        Http::fake([
            'https://clients5.google.com/*' => Http::response([
                '[[[0]]] مرحباً، بالعالم! ¶ السطر الثاني.',
                '[[[1]]] كيف حالك اليوم؟',
            ], 200),
        ]);

        $translator = app(SubtitleTranslatorService::class);
        $srtInput = "1\n00:01:23,456 --> 00:01:26,789\nHello, world!\nSecond line.\n\n2\n00:01:28,000 --> 00:01:31,500\nHow are you doing today?\n";

        $translatedSrt = $translator->translateSrtContent($srtInput);

        $this->assertNotEmpty($translatedSrt);
        $this->assertStringContainsString('00:01:23,456 --> 00:01:26,789', $translatedSrt);
        $this->assertStringContainsString('00:01:28,000 --> 00:01:31,500', $translatedSrt);
        $this->assertStringContainsString('مرحباً', $translatedSrt);
    }

    public function test_generate_arabic_for_media_creates_arabic_file_and_db_record(): void
    {
        $testDir = storage_path('framework/testing/sub_trans_test');
        if (! File::isDirectory($testDir)) {
            File::makeDirectory($testDir, 0755, true);
        }

        $mediaPath = "{$testDir}/TestMovie.mkv";
        $enSrtPath = "{$testDir}/TestMovie.en.srt";
        File::put($mediaPath, 'dummy video');
        File::put($enSrtPath, "1\n00:00:05,000 --> 00:00:09,000\nWelcome to Creative Media Hub.\n");

        $movie = MediaItem::create([
            'title' => 'Test Movie',
            'release_year' => 2024,
            'file_path' => $mediaPath,
        ]);

        Subtitle::create([
            'subtitlable_id' => $movie->id,
            'subtitlable_type' => MediaItem::class,
            'language' => 'en',
            'language_name' => 'English',
            'format' => 'srt',
            'file_path' => $enSrtPath,
            'is_default' => false,
        ]);

        Http::fake([
            'https://clients5.google.com/*' => Http::response([
                '[[[0]]] مرحباً بكم في مكتبة الوسائط الإبداعية.',
            ], 200),
        ]);

        $translator = app(SubtitleTranslatorService::class);
        $arabicSub = $translator->generateArabicForMedia($movie);

        $this->assertNotNull($arabicSub);
        $this->assertEquals('ar', $arabicSub->language);
        $this->assertEquals('Arabic', $arabicSub->language_name);
        $this->assertTrue($arabicSub->is_default);
        $this->assertFileExists($arabicSub->file_path);

        $arabicContent = File::get($arabicSub->file_path);
        $this->assertStringContainsString('00:00:05,000 --> 00:00:09,000', $arabicContent);
        $this->assertStringContainsString('مرحباً بكم في مكتبة الوسائط الإبداعية', $arabicContent);

        // Cleanup test directory
        File::deleteDirectory($testDir);
    }
}
