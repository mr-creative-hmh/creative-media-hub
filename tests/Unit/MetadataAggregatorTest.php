<?php

namespace Tests\Unit;

use App\Services\Metadata\AniListProvider;
use App\Services\Metadata\ArtworkDownloadService;
use App\Services\Metadata\LocalNfoProvider;
use App\Services\Metadata\MetadataAggregator;
use App\Services\Metadata\OmdbProvider;
use App\Services\Metadata\TmdbProvider;
use App\Services\Metadata\TvMazeProvider;
use App\Services\Metadata\WikipediaProvider;
use Tests\TestCase;

class MetadataAggregatorTest extends TestCase
{
    public function test_local_provider_always_returns_fallback_movie_summary(): void
    {
        $local = new LocalNfoProvider;
        $results = $local->searchMovie('Inception', 2010);

        $this->assertNotEmpty($results);
        $this->assertEquals('Inception', $results[0]['title']);
        $this->assertEquals(2010, $results[0]['release_year']);
    }

    public function test_metadata_aggregator_falls_back_gracefully(): void
    {
        $aggregator = new MetadataAggregator(
            new TmdbProvider('invalid_key_for_testing'),
            new TvMazeProvider,
            new OmdbProvider('invalid_key'),
            new AniListProvider,
            new WikipediaProvider,
            new LocalNfoProvider,
            new ArtworkDownloadService
        );

        $providers = $aggregator->getProvidersList();
        $this->assertArrayHasKey('tmdb', $providers);
        $this->assertArrayHasKey('tvmaze', $providers);
        $this->assertArrayHasKey('anilist', $providers);

        $results = $aggregator->searchMovie('Inception', 2010);
        $this->assertNotEmpty($results);
    }

    public function test_sanitize_arabic_title_strips_commentary_and_footnotes(): void
    {
        $aggregator = app(MetadataAggregator::class);

        $crowdsourcedJunk = 'أونابومبر (Unabomber) ,تيد كازينسكي (ولد عام 1942 باسم تيودور جون كازينسكي) ,رجل كاره للتكنولوجيا أرسل العديد من الرسائل المتفجرة للجامعات وخطوط الطيران في الولايات المتحدة (بدأ في أواخر 1970 وأنتهي نشاطه في بدايات 1990) قبل أن تقبض علية FBI في أبريل 1996 (حكم عليه بالسجن مدى الحياة في مايو 1998)';
        $clean = $aggregator->sanitizeArabicTitle($crowdsourcedJunk, 'UNABOMBER');

        $this->assertEquals('أونابومبر', $clean);

        $wikiTitle = 'فارس الظلام (فيلم)';
        $this->assertEquals('فارس الظلام', $aggregator->sanitizeArabicTitle($wikiTitle, 'The Dark Knight'));
    }

    public function test_sanitize_arabic_title_rejects_warnings_and_non_arabic(): void
    {
        $aggregator = app(MetadataAggregator::class);

        $this->assertNull($aggregator->sanitizeArabicTitle('MYMEMORY WARNING: QUOTA EXCEEDED', 'Sample'));
        $this->assertNull($aggregator->sanitizeArabicTitle('Sample Movie', 'Sample Movie'));
        $this->assertNull($aggregator->sanitizeArabicTitle(null, 'Sample Movie'));
    }
}
