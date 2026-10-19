<?php

namespace Tests\Unit;

use App\Services\Organizer\PhysicalOrganizerService;
use App\Services\Organizer\SceneNameParserService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OrganizerHardlinkTest extends TestCase
{
    protected string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = storage_path('framework/testing/organizer_hardlink_test');
        if (! File::isDirectory($this->testDir)) {
            File::makeDirectory($this->testDir, 0755, true, true);
        }
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->testDir)) {
            File::deleteDirectory($this->testDir);
        }
        parent::tearDown();
    }

    public function test_is_same_volume_detects_drive_letters(): void
    {
        $organizer = new PhysicalOrganizerService(new SceneNameParserService);

        $this->assertTrue($organizer->isSameVolume('C:/Downloads/movie.mkv', 'C:/Media/Movies/movie.mkv'));
        $this->assertTrue($organizer->isSameVolume('H:\\Downloads\\movie.mkv', 'h:\\Media\\Movies\\movie.mkv'));
        $this->assertFalse($organizer->isSameVolume('C:/Downloads/movie.mkv', 'D:/Media/Movies/movie.mkv'));
        $this->assertFalse($organizer->isSameVolume('C:\\Downloads\\movie.mkv', 'H:\\Media\\Movies\\movie.mkv'));
    }

    public function test_hardlink_mode_creates_file_link_on_same_volume(): void
    {
        $organizer = new PhysicalOrganizerService(new SceneNameParserService);

        $source = "{$this->testDir}/source_movie.mkv";
        $dest = "{$this->testDir}/organized_movie.mkv";

        File::put($source, 'Simulated video stream data for zero-copy test');

        $method = new \ReflectionMethod(PhysicalOrganizerService::class, 'moveOrCopyFile');
        $method->setAccessible(true);

        $result = $method->invoke($organizer, $source, $dest, 'hardlink');

        $this->assertTrue($result);
        $this->assertFileExists($dest);
        $this->assertFileExists($source, 'Source file must remain intact to preserve seeding');
        $this->assertEquals(File::get($source), File::get($dest));
    }
}
