<?php

namespace Tests\Unit;

use App\Services\Organizer\PhysicalOrganizerService;
use App\Services\Organizer\SceneNameParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_parser_handles_movies_with_resolutions_and_codecs(): void
    {
        $parser = new SceneNameParserService();
        $res = $parser->parse('Interstellar.2014.IMAX.2160p.UHD.HDR.BluRay.x265-SPARKS.mkv');

        $this->assertEquals('movie', $res['type']);
        $this->assertEquals('Interstellar', $res['clean_title']);
        $this->assertEquals(2014, $res['year']);
        $this->assertEquals('4K UHD', $res['resolution']);
        $this->assertEquals('HEVC / H.265', $res['codec']);
        $this->assertEquals('BluRay', $res['source']);
        $this->assertEquals('SPARKS', $res['group']);
    }

    public function test_scene_parser_handles_tv_series_with_seasons_and_episodes(): void
    {
        $parser = new SceneNameParserService();
        $res = $parser->parse('Stranger.Things.S04E07.The.Massacre.at.Hawkins.Lab.1080p.NF.WEB-DL.DDP5.1.Atmos.x264-FLUX.mkv');

        $this->assertEquals('series', $res['type']);
        $this->assertEquals('Stranger Things', $res['clean_title']);
        $this->assertEquals(4, $res['season']);
        $this->assertEquals(7, $res['episode']);
        $this->assertEquals('1080p FHD', $res['resolution']);
        $this->assertEquals('H.264 / AVC', $res['codec']);
        $this->assertEquals('Dolby Atmos', $res['audio']);
    }

    public function test_physical_organizer_generates_standard_dry_run_paths(): void
    {
        $parser = new SceneNameParserService();
        $organizer = new PhysicalOrganizerService($parser);

        $scanned = [
            [
                'path' => 'C:/Downloads/Inception.2010.1080p.BluRay.x264.mkv',
                'filename' => 'Inception.2010.1080p.BluRay.x264.mkv',
                'size_bytes' => 12000000000,
                'parsed' => $parser->parse('Inception.2010.1080p.BluRay.x264.mkv'),
            ]
        ];

        $plan = $organizer->generateDryRun($scanned, 'C:/Media');

        $this->assertCount(1, $plan);
        $this->assertEquals('C:/Downloads/Inception.2010.1080p.BluRay.x264.mkv', $plan[0]['source_path']);
        $this->assertEquals('C:/Media/Movies/Inception (2010)/Inception (2010) [1080p FHD].mkv', $plan[0]['destination_path']);
    }
}
