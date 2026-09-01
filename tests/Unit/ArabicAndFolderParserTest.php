<?php

namespace Tests\Unit;

use App\Services\Organizer\SceneNameParserService;
use PHPUnit\Framework\TestCase;

class ArabicAndFolderParserTest extends TestCase
{
    protected SceneNameParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SceneNameParserService();
    }

    /**
     * Test Arabic TV Series Parsing
     */
    public function test_arabic_series_parsing()
    {
        // Series with Arabic episode marker in filename
        $res1 = $this->parser->parse('C:/Media/Series/مسلسل الاختيار/الحلقة 01.mp4');
        $this->assertEquals('series', $res1['type']);
        $this->assertEquals('الاختيار', $res1['series_title']);
        $this->assertEquals(1, $res1['season']);
        $this->assertEquals(1, $res1['episode']);

        // Series with Arabic ordinal season and episode
        $res2 = $this->parser->parse('C:/Media/Series/مسلسل رأفت الهجان/الموسم الأول/الحلقة 05.mkv');
        $this->assertEquals('series', $res2['type']);
        $this->assertEquals('رأفت الهجان', $res2['series_title']);
        $this->assertEquals(1, $res2['season']);
        $this->assertEquals(5, $res2['episode']);

        // Series with Season 2 in Arabic and episode number
        $res3 = $this->parser->parse('C:/Media/Series/باب الحارة/الموسم 2/حلقة 12.mp4');
        $this->assertEquals('series', $res3['type']);
        $this->assertEquals('باب الحارة', $res3['series_title']);
        $this->assertEquals(2, $res3['season']);
        $this->assertEquals(12, $res3['episode']);

        // Series with single number episode inside series folder
        $res4 = $this->parser->parse('C:/Media/Series/عمر/03.mp4');
        $this->assertEquals('series', $res4['type']);
        $this->assertEquals('عمر', $res4['series_title']);
        $this->assertEquals(1, $res4['season']);
        $this->assertEquals(3, $res4['episode']);

        // Series with S01E05 and Arabic release tag
        $res5 = $this->parser->parse('C:/Media/Series/مسلسل الحفرة S01E05 مترجم.mkv');
        $this->assertEquals('series', $res5['type']);
        $this->assertEquals('الحفرة', $res5['series_title']);
        $this->assertEquals(1, $res5['season']);
        $this->assertEquals(5, $res5['episode']);

        // Single digit episode
        $res6 = $this->parser->parse('C:/Media/Series/جعفر العمدة/1.mp4');
        $this->assertEquals('series', $res6['type']);
        $this->assertEquals('جعفر العمدة', $res6['series_title']);
        $this->assertEquals(1, $res6['season']);
        $this->assertEquals(1, $res6['episode']);
    }

    /**
     * Test Folder-Only Context (English Series)
     */
    public function test_folder_only_english_series()
    {
        $res1 = $this->parser->parse('C:/Media/Series/Chernobyl/01.mkv');
        $this->assertEquals('series', $res1['type']);
        $this->assertEquals('Chernobyl', $res1['series_title']);
        $this->assertEquals(1, $res1['season']);
        $this->assertEquals(1, $res1['episode']);

        $res2 = $this->parser->parse('C:/Media/Series/Breaking Bad/Episode 04.mp4');
        $this->assertEquals('series', $res2['type']);
        $this->assertEquals('Breaking Bad', $res2['series_title']);
        $this->assertEquals(1, $res2['season']);
        $this->assertEquals(4, $res2['episode']);

        $res3 = $this->parser->parse('C:/Media/Series/Stranger Things/Season 1/01.mp4');
        $this->assertEquals('series', $res3['type']);
        $this->assertEquals('Stranger Things', $res3['series_title']);
        $this->assertEquals(1, $res3['season']);
        $this->assertEquals(1, $res3['episode']);
    }

    /**
     * Test Arabic Movies and Generic Movie Folder Names
     */
    public function test_arabic_movies_and_generic_folders()
    {
        $res1 = $this->parser->parse('C:/Media/Movies/فيلم الحريف 1983 HD.mp4');
        $this->assertEquals('movie', $res1['type']);
        $this->assertEquals('الحريف', $res1['title']);
        $this->assertEquals(1983, $res1['year']);

        $res2 = $this->parser->parse('C:/Media/Movies/فيلم كيرة والجن (2022) 1080p.mkv');
        $this->assertEquals('movie', $res2['type']);
        $this->assertEquals('كيرة والجن', $res2['title']);
        $this->assertEquals(2022, $res2['year']);

        $res3 = $this->parser->parse('C:/Media/Movies/The Godfather (1972)/movie.mp4');
        $this->assertEquals('movie', $res3['type']);
        $this->assertEquals('The Godfather', $res3['title']);
        $this->assertEquals(1972, $res3['year']);
    }
}
