<?php

namespace Tests\Unit;

use App\Services\Organizer\SceneNameParserService;
use App\Services\Organizer\ZeroKeyGenreClassifierService;
use Tests\TestCase;

class ZeroKeyGenreClassifierTest extends TestCase
{
    protected ZeroKeyGenreClassifierService $service;
    protected SceneNameParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ZeroKeyGenreClassifierService::class);
        $this->parser = app(SceneNameParserService::class);
    }

    public function test_classifies_major_titles()
    {
        $res1 = $this->service->resolveGenres('Oppenheimer');
        $this->assertEquals('Drama & History', $res1['primary']);

        $res2 = $this->service->resolveGenres('Shrek');
        $this->assertEquals('Animation', $res2['primary']);

        $res3 = $this->service->resolveGenres('The Hangover');
        $this->assertEquals('Comedy', $res3['primary']);

        $res4 = $this->service->resolveGenres('Interstellar');
        $this->assertEquals('Sci-Fi', $res4['primary']);

        $res5 = $this->service->resolveGenres('Harry Potter and the Goblet of Fire');
        $this->assertEquals('Fantasy', $res5['primary']);
    }

    public function test_franchise_inheritance()
    {
        $res = $this->service->resolveGenres('Tokyo Drift', 'Fast & Furious');
        $this->assertEquals('Action & Adventure', $res['primary']);
    }

    public function test_franchise_detection_from_numbered_folders()
    {
        $parsed = $this->parser->parse('H:/Entertainment/Movies/Fast & Furious/1/The Fast and the Furious (2001).mkv');
        $this->assertEquals('Fast & Furious', $parsed['collection_name']);
        $this->assertEquals('movie', $parsed['type']);
        $this->assertEquals(2001, $parsed['year']);

        $parsed2 = $this->parser->parse('H:/Entertainment/Movies/Transformers/Transformers.One.2024.mkv');
        $this->assertEquals('Transformers', $parsed2['collection_name']);
    }
}
