<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Subtitle;
use App\Services\Subtitles\SubtitleHealthCheckService;
use App\Services\Subtitles\SubtitleLanguageDetectorService;
use App\Services\Subtitles\SubtitleValidatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SubtitleHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = str_replace('\\', '/', storage_path('framework/testing/subtitle_check_test_'.uniqid()));
        File::makeDirectory($this->testDir, 0755, true, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->testDir)) {
            File::deleteDirectory($this->testDir);
        }
        parent::tearDown();
    }

    public function test_language_detector_identifies_arabic_and_latin_languages_accurately(): void
    {
        $detector = new SubtitleLanguageDetectorService;

        // 1. Arabic dialog
        $arabicText = <<<'SRT'
1
00:00:01,000 --> 00:00:04,000
مرحبا يا صديقي، كيف حالك اليوم؟

2
00:00:05,000 --> 00:00:08,000
أنا بخير والحمد لله، شكراً لك على المساعدة.

3
00:00:09,000 --> 00:00:12,000
ماذا سنفعل الآن في هذا المكان؟
SRT;
        $arRes = $detector->detectLanguage($arabicText);
        $this->assertEquals('ar', $arRes['language']);
        $this->assertGreaterThan(0.70, $arRes['confidence']);

        // 2. English dialog
        $englishText = <<<'SRT'
1
00:00:01,000 --> 00:00:04,000
Hello there, how are you doing today?

2
00:00:05,000 --> 00:00:08,000
I am doing great, thank you for your help.

3
00:00:09,000 --> 00:00:12,000
What should we do right now about that situation?
SRT;
        $enRes = $detector->detectLanguage($englishText);
        $this->assertEquals('en', $enRes['language']);

        // 3. French dialog
        $frenchText = <<<'SRT'
1
00:00:01,000 --> 00:00:04,000
Bonjour mon ami, comment allez-vous aujourd'hui?

2
00:00:05,000 --> 00:00:08,000
Je vais très bien, merci beaucoup pour votre aide.

3
00:00:09,000 --> 00:00:12,000
Qu'est-ce que nous allons faire avec cette situation dans la maison?
SRT;
        $frRes = $detector->detectLanguage($frenchText);
        $this->assertEquals('fr', $frRes['language']);

        // 4. Spanish dialog
        $spanishText = <<<'SRT'
1
00:00:01,000 --> 00:00:04,000
Hola amigo, ¿cómo estás hoy?

2
00:00:05,000 --> 00:00:08,000
Estoy muy bien, muchas gracias por toda tu ayuda.

3
00:00:09,000 --> 00:00:12,000
¿Qué vamos a hacer ahora con este problema que tenemos aquí?
SRT;
        $esRes = $detector->detectLanguage($spanishText);
        $this->assertEquals('es', $esRes['language']);
    }

    public function test_subtitle_validator_identifies_invalid_and_dummy_stubs(): void
    {
        $validator = new SubtitleValidatorService;

        // 1. Valid subtitle with 6 cues
        $validSrt = '';
        for ($i = 1; $i <= 6; $i++) {
            $s = sprintf('%02d', $i * 5);
            $e = sprintf('%02d', $i * 5 + 3);
            $validSrt .= "{$i}\n00:00:{$s},000 --> 00:00:{$e},000\nThis is a dialogue line number {$i} in the movie.\n\n";
        }
        $validRes = $validator->validate($validSrt);
        $this->assertTrue($validRes['is_valid']);
        $this->assertEquals(6, $validRes['cue_count']);

        // 2. Empty string or 0-byte file
        $emptyRes = $validator->validate('');
        $this->assertFalse($emptyRes['is_valid']);
        $this->assertEquals('delete', $emptyRes['recommended_action']);

        // 3. Dummy template stub (< 5 cues + placeholder text)
        $stubSrt = <<<'SRT'
1
00:00:01,000 --> 00:00:05,000
Welcome to the Creative Media Streaming Library
SRT;
        $stubRes = $validator->validate($stubSrt);
        $this->assertFalse($stubRes['is_valid']);
        $this->assertStringContainsString('Extremely short stub', implode(' ', $stubRes['issues']));
        $this->assertStringContainsString('dummy placeholder', implode(' ', $stubRes['issues']));

        // 4. Cloudflare / HTML error page disguised as .srt
        $htmlPage = <<<'HTML'
<!DOCTYPE html>
<html>
<head><title>503 Service Temporarily Unavailable</title></head>
<body><h1>503 Service Unavailable</h1><p>Cloudflare Ray ID: 89abcdef</p></body>
</html>
HTML;
        $htmlRes = $validator->validate($htmlPage);
        $this->assertFalse($htmlRes['is_valid']);
        $this->assertStringContainsString('HTML error page', implode(' ', $htmlRes['issues']));

        // 5. File without valid timecodes
        $noTimecode = 'Just some text without any subtitle timestamp lines at all.';
        $noTimeRes = $validator->validate($noTimecode);
        $this->assertFalse($noTimeRes['is_valid']);
    }

    public function test_health_check_service_deletes_invalid_and_standardizes_names(): void
    {
        // 1. Create simulated media item and subtitle files on disk
        $videoFile = "{$this->testDir}/Gladiator (2000).mkv";
        File::put($videoFile, 'FAKE_VIDEO_CONTENT_FOR_TESTING');

        $movie = MediaItem::create([
            'title' => 'Gladiator',
            'release_year' => 2000,
            'file_path' => $videoFile,
        ]);

        // A. Arabic subtitle needing rename (currently Gladiator.arabic.srt)
        $arabicSrtPath = "{$this->testDir}/Gladiator.arabic.srt";
        $arabicContent = '';
        for ($i = 1; $i <= 8; $i++) {
            $s = sprintf('%02d', $i * 5);
            $e = sprintf('%02d', $i * 5 + 3);
            $arabicContent .= "{$i}\n00:00:{$s},000 --> 00:00:{$e},000\nهذا هو الحوار العربي رقم {$i} في فيلم جلاديتور.\n\n";
        }
        File::put($arabicSrtPath, $arabicContent);

        $subAr = Subtitle::create([
            'subtitlable_id' => $movie->id,
            'subtitlable_type' => MediaItem::class,
            'file_path' => $arabicSrtPath,
            'language' => 'und',
            'format' => 'srt',
        ]);

        // B. Corrupt dummy stub (should be deleted)
        $stubPath = "{$this->testDir}/Gladiator.broken.srt";
        File::put($stubPath, "1\n00:00:01,000 --> 00:00:03,000\nWelcome to the Creative Media Streaming Library\n");

        $subStub = Subtitle::create([
            'subtitlable_id' => $movie->id,
            'subtitlable_type' => MediaItem::class,
            'file_path' => $stubPath,
            'language' => 'en',
            'format' => 'srt',
        ]);

        // C. English subtitle already in standard format (Gladiator (2000).en.srt)
        $enSrtPath = "{$this->testDir}/Gladiator (2000).en.srt";
        $enContent = '';
        for ($i = 1; $i <= 8; $i++) {
            $s = sprintf('%02d', $i * 5);
            $e = sprintf('%02d', $i * 5 + 3);
            $enContent .= "{$i}\n00:00:{$s},000 --> 00:00:{$e},000\nThis is English dialogue number {$i} in Gladiator.\n\n";
        }
        File::put($enSrtPath, $enContent);

        $subEn = Subtitle::create([
            'subtitlable_id' => $movie->id,
            'subtitlable_type' => MediaItem::class,
            'file_path' => $enSrtPath,
            'language' => 'en',
            'format' => 'srt',
        ]);

        $service = app(SubtitleHealthCheckService::class);

        // 2. Test Dry-Run: Files should NOT be deleted or renamed
        $dryResults = $service->checkAndNormalize([
            'dry_run' => true,
            'target_path' => $this->testDir,
        ]);

        $this->assertEquals(3, $dryResults['total_scanned']);
        $this->assertEquals(2, $dryResults['valid_count']);
        $this->assertEquals(1, $dryResults['invalid_count']);
        $this->assertTrue(File::exists($stubPath), 'Stub file should NOT be deleted in dry run');
        $this->assertTrue(File::exists($arabicSrtPath), 'Arabic file should NOT be renamed in dry run');

        // 3. Test Fix Mode: Delete invalid and rename to standard .ar.srt
        $fixResults = $service->checkAndNormalize([
            'dry_run' => false,
            'delete_invalid' => true,
            'auto_rename' => true,
            'target_path' => $this->testDir,
        ]);

        $this->assertFalse(File::exists($stubPath), 'Corrupt stub file should be deleted');
        $this->assertDatabaseMissing('subtitles', ['id' => $subStub->id]);

        $expectedStandardArPath = "{$this->testDir}/Gladiator (2000).ar.srt";
        $this->assertTrue(File::exists($expectedStandardArPath), 'Arabic subtitle should be renamed to Gladiator (2000).ar.srt');
        $this->assertDatabaseHas('subtitles', [
            'id' => $subAr->id,
            'file_path' => $expectedStandardArPath,
            'language' => 'ar',
        ]);

        $this->assertTrue(File::exists($enSrtPath), 'Already standard English subtitle should be kept');
    }

    public function test_api_check_endpoint_returns_json_report(): void
    {
        $videoFile = "{$this->testDir}/Sample.mp4";
        File::put($videoFile, 'VIDEO');

        $srtFile = "{$this->testDir}/Sample.ar.srt";
        $content = '';
        for ($i = 1; $i <= 6; $i++) {
            $s = sprintf('%02d', $i * 4);
            $content .= "{$i}\n00:00:{$s},000 --> 00:00:".($s + 2).",000\nحوار عربي جميل رقم {$i}.\n\n";
        }
        File::put($srtFile, $content);

        $response = $this->postJson('/api/subtitles/check', [
            'dry_run' => true,
            'target_path' => $this->testDir,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_scanned',
            'valid_count',
            'invalid_count',
            'deleted_count',
            'renamed_count',
            'dry_run',
            'language_breakdown',
            'items',
        ]);

        $this->assertEquals(1, $response->json('total_scanned'));
        $this->assertEquals(1, $response->json('valid_count'));
    }

    public function test_artisan_command_runs_successfully(): void
    {
        $this->artisan('subtitles:check', [
            '--dry-run' => true,
            '--path' => $this->testDir,
        ])->assertExitCode(0);
    }
}
