<?php

namespace Tests\Unit;

use App\Services\Organizer\SceneNameParserService;
use App\Services\Organizer\FilesystemScannerService;
use PHPUnit\Framework\TestCase;

class ComprehensiveSceneParserTest extends TestCase
{
    protected SceneNameParserService $parser;
    protected FilesystemScannerService $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SceneNameParserService();
        $this->scanner = new FilesystemScannerService($this->parser);
    }

    /**
     * 1. Standard Series Naming Variations
     */
    public function test_standard_series_variations()
    {
        $cases = [
            'Breaking Bad S01E01.mkv' => ['Breaking Bad', 1, 1],
            'Breaking Bad S01E02.mkv' => ['Breaking Bad', 1, 2],
            'Game of Thrones S08E06.mkv' => ['Game of Thrones', 8, 6],
            'The Office S05E12.mkv' => ['The Office', 5, 12],
            'Breaking Bad 1x01.mkv' => ['Breaking Bad', 1, 1],
            'Game of Thrones 1x01.mp4' => ['Game of Thrones', 1, 1],
            'The Office 5x12.avi' => ['The Office', 5, 12],
            'Attack on Titan S01E01.mkv' => ['Attack on Titan', 1, 1],
            'One Piece 1x01.mkv' => ['One Piece', 1, 1],
            'Naruto Shippuden S01E01.mp4' => ['Naruto Shippuden', 1, 1],
            'Dragon Ball Z S01E01 [1080p].mkv' => ['Dragon Ball Z', 1, 1],
            'Planet Earth S01E01.mkv' => ['Planet Earth', 1, 1],
            'Cosmos S01E01.mkv' => ['Cosmos', 1, 1],
        ];

        foreach ($cases as $file => $expected) {
            $res = $this->parser->parse($file);
            $this->assertEquals('series', $res['type'], "Failed type for $file");
            $this->assertEquals($expected[0], $res['series_title'], "Failed series title for $file");
            $this->assertEquals($expected[1], $res['season'], "Failed season for $file");
            $this->assertEquals($expected[2], $res['episode'], "Failed episode for $file");
        }
    }

    /**
     * 2. Series with Episode Titles & Separators
     */
    public function test_series_with_episode_titles_and_separators()
    {
        $res1 = $this->parser->parse('Breaking Bad S01E01 Pilot.mkv');
        $this->assertEquals('Breaking Bad', $res1['series_title']);
        $this->assertEquals('Pilot', $res1['episode_title']);

        $res2 = $this->parser->parse('Game of Thrones S01E01 Winter Is Coming.mkv');
        $this->assertEquals('Game of Thrones', $res2['series_title']);
        $this->assertEquals('Winter Is Coming', $res2['episode_title']);

        $res3 = $this->parser->parse('The Office S05E12 The Duel.mkv');
        $this->assertEquals('The Office', $res3['series_title']);
        $this->assertEquals('The Duel', $res3['episode_title']);

        $res4 = $this->parser->parse('Breaking Bad - S01E01 - Pilot.mkv');
        $this->assertEquals('Breaking Bad', $res4['series_title']);
        $this->assertEquals('Pilot', $res4['episode_title']);

        $res5 = $this->parser->parse('Game.of.Thrones.S01E01.Winter.Is.Coming.mkv');
        $this->assertEquals('Game of Thrones', $res5['series_title']);
        $this->assertEquals('Winter Is Coming', $res5['episode_title']);

        $res6 = $this->parser->parse('Show_Name_S01E01_Episode_Title.mkv');
        $this->assertEquals('Show Name', $res6['series_title']);
        $this->assertEquals('Episode Title', $res6['episode_title']);

        $res7 = $this->parser->parse('Show-Name-1x01-Episode_Title.mp4');
        $this->assertEquals('Show Name', $res7['series_title']);
        $this->assertEquals('Episode Title', $res7['episode_title']);
    }

    /**
     * 3. Folder Path Based Series Detection
     */
    public function test_folder_path_based_series_detection()
    {
        $res1 = $this->parser->parse('Breaking Bad/Season 01/Breaking Bad - S01E01 - Pilot.mkv');
        $this->assertEquals('Breaking Bad', $res1['series_title']);
        $this->assertEquals(1, $res1['season']);
        $this->assertEquals(1, $res1['episode']);

        $res2 = $this->parser->parse('Game of Thrones/Season 1/Game of Thrones - 1x01 - Winter Is Coming.mkv');
        $this->assertEquals('Game of Thrones', $res2['series_title']);
        $this->assertEquals(1, $res2['season']);
        $this->assertEquals(1, $res2['episode']);

        $res3 = $this->parser->parse('The Office/Season 05/The Office - S05E12 - The Duel.mp4');
        $this->assertEquals('The Office', $res3['series_title']);
        $this->assertEquals(5, $res3['season']);
        $this->assertEquals(12, $res3['episode']);

        $res4 = $this->parser->parse('Breaking Bad/Pilot.mkv');
        $this->assertEquals('Breaking Bad', $res4['series_title']);
    }

    /**
     * 4. Multi-Episode & Special Episodes
     */
    public function test_multiepisodes_and_specials()
    {
        $res1 = $this->parser->parse('Breaking Bad S01E01E02.mkv');
        $this->assertEquals(1, $res1['season']);
        $this->assertEquals(1, $res1['episode']);
        $this->assertEquals(2, $res1['episode_end']);

        $res2 = $this->parser->parse('The Office S05E12E13.mkv');
        $this->assertEquals(5, $res2['season']);
        $this->assertEquals(12, $res2['episode']);
        $this->assertEquals(13, $res2['episode_end']);

        $res3 = $this->parser->parse('Attack on Titan S00E01.mkv');
        $this->assertEquals(0, $res3['season']);
        $this->assertEquals(1, $res3['episode']);

        $res4 = $this->parser->parse('Show S00E00.mkv');
        $this->assertEquals(0, $res4['season']);
        $this->assertEquals(0, $res4['episode']);

        $res5 = $this->parser->parse('Show OVA.mkv');
        $this->assertEquals('OVA', $res5['edition']);
    }

    /**
     * 5. Date-based Daily Shows
     */
    public function test_date_based_shows()
    {
        $res1 = $this->parser->parse('The Daily Show 2023-01-15.mkv');
        $this->assertEquals('The Daily Show', $res1['series_title']);
        $this->assertEquals('2023-01-15', $res1['air_date']);

        $res2 = $this->parser->parse('Last Week Tonight 2023.01.15.mp4');
        $this->assertEquals('Last Week Tonight', $res2['series_title']);
        $this->assertEquals('2023-01-15', $res2['air_date']);
    }

    /**
     * 6. Movie Standard Formats & Remakes
     */
    public function test_movie_standard_and_remakes()
    {
        $cases = [
            'The Matrix (1999).mkv' => ['The Matrix', 1999],
            'Inception (2010).mp4' => ['Inception', 2010],
            'The Shawshank Redemption (1994).mkv' => ['The Shawshank Redemption', 1994],
            'The Matrix.mkv' => ['The Matrix', null],
            'Inception.mp4' => ['Inception', null],
            'Pulp Fiction.avi' => ['Pulp Fiction', null],
            'The Matrix [1999].mkv' => ['The Matrix', 1999],
            'Inception [2010].mp4' => ['Inception', 2010],
            'The Prestige (2006).mkv' => ['The Prestige', 2006],
            'The Departed (2006).mkv' => ['The Departed', 2006],
            'A Beautiful Mind (2001).mkv' => ['A Beautiful Mind', 2001],
            'The Batman (2022).mkv' => ['The Batman', 2022],
            'Batman (1989).mkv' => ['Batman', 1989],
            'Spider-Man (2002).mkv' => ['Spider-Man', 2002],
            'The Amazing Spider-Man (2012).mkv' => ['The Amazing Spider-Man', 2012],
            'Spider-Man: Homecoming (2017).mkv' => ['Spider-Man: Homecoming', 2017],
        ];

        foreach ($cases as $file => $expected) {
            $res = $this->parser->parse($file);
            $this->assertEquals('movie', $res['type'], "Failed type for $file");
            $this->assertEquals($expected[0], $res['title'], "Failed title for $file");
            if ($expected[1] !== null) {
                $this->assertEquals($expected[1], $res['year'], "Failed year for $file");
            }
        }
    }

    /**
     * 7. Movie Quality, Codecs, Sources & Release Groups
     */
    public function test_movie_quality_codec_and_groups()
    {
        $res1 = $this->parser->parse('The Matrix (1999) [1080p] [BluRay] [x264]-[YTS].mkv');
        $this->assertEquals('The Matrix', $res1['title']);
        $this->assertEquals(1999, $res1['year']);
        $this->assertEquals('1080p FHD', $res1['resolution']);
        $this->assertEquals('BluRay', $res1['source']);
        $this->assertEquals('H.264 / AVC', $res1['codec']);
        $this->assertEquals('YTS', $res1['group']);

        $res2 = $this->parser->parse('Inception (2010) [2160p] [BluRay] [x265]-[TERMiNAL].mkv');
        $this->assertEquals('Inception', $res2['title']);
        $this->assertEquals('4K UHD', $res2['resolution']);
        $this->assertEquals('BluRay', $res2['source']);
        $this->assertEquals('HEVC / H.265', $res2['codec']);
        $this->assertEquals('TERMiNAL', $res2['group']);

        $res3 = $this->parser->parse('Parasite (2019) 1080p WEB-DL x265-[RARBG].mp4');
        $this->assertEquals('Parasite', $res3['title']);
        $this->assertEquals('1080p FHD', $res3['resolution']);
        $this->assertEquals('WEBRip', $res3['source']);
        $this->assertEquals('HEVC / H.265', $res3['codec']);
        $this->assertEquals('RARBG', $res3['group']);

        $res4 = $this->parser->parse('The Dark Knight (2008) [4K] [HDR] [HEVC].mkv');
        $this->assertEquals('The Dark Knight', $res4['title']);
        $this->assertEquals('4K UHD', $res4['resolution']);
        $this->assertEquals('HEVC / H.265', $res4['codec']);

        $res5 = $this->parser->parse('Interstellar (2014) 2160p UHD BluRay REMUX.mkv');
        $this->assertEquals('Interstellar', $res5['title']);
        $this->assertEquals('4K UHD', $res5['resolution']);
        $this->assertEquals('BluRay', $res5['source']);
    }

    /**
     * 8. Movie Editions & Multi-Part
     */
    public function test_movie_editions_and_multipart()
    {
        $res1 = $this->parser->parse('Blade Runner (1982) [Final Cut].mkv');
        $this->assertEquals('Blade Runner', $res1['title']);
        $this->assertEquals('Final Cut', $res1['edition']);

        $res2 = $this->parser->parse("Blade Runner (1982) [Director's Cut].mkv");
        $this->assertEquals("Director's Cut", $res2['edition']);

        $res3 = $this->parser->parse('The Lord of the Rings: The Fellowship of the Ring (2001) [Extended Edition].mkv');
        $this->assertEquals('Extended Edition', $res3['edition']);

        $res4 = $this->parser->parse('Superman II (1980) [Richard Donner Cut].mkv');
        $this->assertEquals("Richard Donner Cut", $res4['edition']);

        $res5 = $this->parser->parse('The Lord of the Rings: The Return of the King (2003) [Part 1].mkv');
        $this->assertEquals(1, $res5['part']);

        $res6 = $this->parser->parse('The Lord of the Rings: The Return of the King (2003) [Part 2].mkv');
        $this->assertEquals(2, $res6['part']);
    }

    /**
     * 9. 3D Formats
     */
    public function test_3d_formats()
    {
        $res1 = $this->parser->parse('Avatar (2009) [3D] [1080p] [BluRay] [MVC].mkv');
        $this->assertTrue($res1['is_3d']);
        $this->assertEquals('Avatar', $res1['title']);

        $res2 = $this->parser->parse('Gravity (2013) [3D] [2160p].mkv');
        $this->assertTrue($res2['is_3d']);
        $this->assertEquals('Gravity', $res2['title']);
    }

    /**
     * 10. Unicode Titles & Special Characters
     */
    public function test_unicode_and_special_characters()
    {
        $cases = [
            'Crouching Tiger, Hidden Dragon (2000).mkv' => 'Crouching Tiger, Hidden Dragon',
            'Léon: The Professional (1994).mkv' => 'Léon: The Professional',
            'The Girl with the Dragon Tattoo (2011).mkv' => 'The Girl with the Dragon Tattoo',
            'Мёртвые души (1984).mkv' => 'Мёртвые души',
            '电影标题 (2020).mkv' => '电影标题',
            '영화 제목 (2020).mkv' => '영화 제목',
            '映画のタイトル (2020).mkv' => '映画のタイトル',
            'фильм (2020).mkv' => 'фильм',
            '進撃の巨人 S01E01.mkv' => '進撃の巨人',
            '鬼滅の刃 1x01.mp4' => '鬼滅の刃',
        ];

        foreach ($cases as $file => $expectedTitle) {
            $res = $this->parser->parse($file);
            $this->assertEquals($expectedTitle, $res['title'], "Failed unicode title for $file");
        }
    }

    /**
     * 11. Subtitle Language Detection, SDH & Forced Flags
     */
    public function test_subtitles_parsing()
    {
        $cases = [
            'Movie (2020).srt' => ['und', false, false],
            'Movie (2020).en.srt' => ['en', false, false],
            'Movie (2020).ar.srt' => ['ar', false, false],
            'Movie (2020).en.forced.srt' => ['en', true, false],
            'Movie (2020).ar.sdh.srt' => ['ar', false, true],
            'Movie (2020).en.sdh.vtt' => ['en', false, true],
            'Movie (2020).eng.srt' => ['en', false, false],
            'Movie (2020).eng.forced.srt' => ['en', true, false],
            'Movie (2020).ara.srt' => ['ar', false, false],
            'Movie (2020).arabic.srt' => ['ar', false, false],
            'Movie (2020).en-US.srt' => ['en', false, false],
            'Movie (2020).en-GB.srt' => ['en', false, false],
            'Movie (2020).fr.srt' => ['fr', false, false],
            'Movie (2020).es.srt' => ['es', false, false],
            'Movie (2020).he.srt' => ['he', false, false],
            '3_English_Forced.srt' => ['en', true, false],
            'English_SDH.srt' => ['en', false, true],
            'Movie (2020).en.[YTS].srt' => ['en', false, false],
            'Movie (2020).ar.[SubScene].srt' => ['ar', false, false],
            'Movie (2020).en.ass' => ['en', false, false],
            'Movie (2020).ar.ass' => ['ar', false, false],
            'Movie (2020).en.ssa' => ['en', false, false],
            'Movie (2020).en.vtt' => ['en', false, false],
            'Movie (2020).ar.vtt' => ['ar', false, false],
        ];

        foreach ($cases as $file => $expected) {
            $res = $this->scanner->parseSubtitleMetadata($file);
            $this->assertEquals($expected[0], $res['language'], "Failed sub language for $file");
            $this->assertEquals($expected[1], $res['is_forced'], "Failed is_forced for $file");
            $this->assertEquals($expected[2], $res['is_sdh'], "Failed is_sdh for $file");
        }
    }

    /**
     * 12. Edge Cases, Sample Filtering, Years at Start & Emojis
     */
    public function test_edge_cases_and_sample_skipping()
    {
        // Sample detection
        $this->assertTrue($this->parser->isSampleOrExtra('Sample.mkv', ''));
        $this->assertTrue($this->parser->isSampleOrExtra('Movie (2020)-Sample.mkv', ''));
        $this->assertTrue($this->parser->isSampleOrExtra('Sample-Movie (2020).mkv', ''));
        $this->assertTrue($this->parser->isSampleOrExtra('BehindTheScenes.mkv', 'Extras'));
        $this->assertTrue($this->parser->isSampleOrExtra('DeletedScenes.mkv', 'Featurettes'));
        $this->assertTrue($this->parser->isSampleOrExtra('Trailer.mkv', 'Trailers'));

        // Year at beginning
        $res1 = $this->parser->parse('2020 Movie Title.mkv');
        $this->assertEquals(2020, $res1['year']);
        $this->assertEquals('Movie Title', $res1['title']);

        $res2 = $this->parser->parse('2020 - Movie Title.mkv');
        $this->assertEquals(2020, $res2['year']);
        $this->assertEquals('Movie Title', $res2['title']);

        // Multiple years (take last)
        $res3 = $this->parser->parse('Movie (2019) (2020).mkv');
        $this->assertEquals(2020, $res3['year']);

        // Year-like numbers that are resolutions
        $res4 = $this->parser->parse('Movie 1080p.mkv');
        $this->assertEquals('1080p FHD', $res4['resolution']);

        // Case variations
        $res5 = $this->parser->parse('MOVIE TITLE (2020).MKV');
        $this->assertEquals('Movie Title', $res5['title']);

        $res6 = $this->parser->parse('SHOW NAME S01E01.MKV');
        $this->assertEquals('Show Name', $res6['series_title']);

        $res7 = $this->parser->parse('MoViE TiTlE (2020).MkV');
        $this->assertEquals('Movie Title', $res7['title']);

        // Emoji stripping
        $res8 = $this->parser->parse('Movie 🎬 (2020).mkv');
        $this->assertEquals('Movie', $res8['title']);

        $res9 = $this->parser->parse('Show 📺 S01E01.mkv');
        $this->assertEquals('Show', $res9['series_title']);
    }
}
