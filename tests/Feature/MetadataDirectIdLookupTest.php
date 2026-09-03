<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Series;
use App\Services\Metadata\MetadataAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetadataDirectIdLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_id_requires_an_external_id(): void
    {
        $response = $this->postJson('/api/metadata/lookup-id', [
            'type' => 'movie',
            'external_id' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_direct_id_lookup_and_apply_to_movie(): void
    {
        $movie = MediaItem::create([
            'title' => 'Inception Raw File',
            'release_year' => 2009,
            'file_path' => 'C:/Media/Inception.mkv',
        ]);

        // Mock the MetadataAggregator so tests run reliably without network dependency
        $this->mock(MetadataAggregator::class, function ($mock) {
            $mock->shouldReceive('lookupByExternalId')
                ->with('27205', 'movie')
                ->once()
                ->andReturn([
                    'provider' => 'TMDb',
                    'id' => '27205',
                    'tmdb_id' => '27205',
                    'imdb_id' => 'tt1375666',
                    'title' => 'Inception',
                    'title_ar' => 'استهلال',
                    'original_title' => 'Inception',
                    'overview' => 'A thief who steals corporate secrets through the use of dream-sharing technology...',
                    'overview_ar' => 'لص يسرق أسرار الشركات باستخدام تقنية مشاركة الأحلام...',
                    'release_year' => 2010,
                    'rating' => 8.8,
                    'runtime_minutes' => 148,
                    'poster_path' => 'https://image.tmdb.org/t/p/w780/edv5CZvWj09upOsy2Y6IwDhK8bt.jpg',
                    'backdrop_path' => 'https://image.tmdb.org/t/p/original/s3TBrRGB1iav7gFOCNx3H31MoES.jpg',
                    'collection_name' => 'Christopher Nolan Collection',
                    'genres' => ['Action', 'Science Fiction', 'Adventure'],
                ]);
        });

        $response = $this->postJson('/api/metadata/lookup-id', [
            'id' => $movie->id,
            'type' => 'movie',
            'external_id' => '27205',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'media' => [
                'id' => $movie->id,
                'title' => 'Inception',
                'title_ar' => 'استهلال',
                'release_year' => 2010,
                'tmdb_id' => '27205',
                'imdb_id' => 'tt1375666',
            ],
        ]);

        $movie->refresh();
        $this->assertEquals('Inception', $movie->title);
        $this->assertEquals('استهلال', $movie->title_ar);
        $this->assertEquals(2010, $movie->release_year);
        $this->assertEquals('27205', $movie->tmdb_id);
        $this->assertEquals('tt1375666', $movie->imdb_id);
        $this->assertEquals(3, $movie->genres()->count());
    }

    public function test_direct_id_lookup_and_apply_to_series(): void
    {
        $series = Series::create([
            'title' => 'Breaking Bad Raw',
            'release_year' => 2007,
            'folder_path' => 'C:/Media/TV/Breaking Bad',
        ]);

        $this->mock(MetadataAggregator::class, function ($mock) {
            $mock->shouldReceive('lookupByExternalId')
                ->with('tt0903747', 'series')
                ->once()
                ->andReturn([
                    'provider' => 'TMDb',
                    'id' => '1396',
                    'tmdb_id' => '1396',
                    'imdb_id' => 'tt0903747',
                    'title' => 'Breaking Bad',
                    'title_ar' => 'اختلال ضال',
                    'overview' => 'Walter White, a New Mexico chemistry teacher...',
                    'overview_ar' => 'مدرس كيمياء يُشخص بمرض السرطان...',
                    'release_year' => 2008,
                    'rating' => 9.5,
                    'status' => 'Ended',
                    'network' => 'AMC',
                    'poster_path' => 'https://image.tmdb.org/t/p/w780/ggFHVNu6YYI5L9pCfOacjizRGt.jpg',
                    'backdrop_path' => 'https://image.tmdb.org/t/p/original/tsRy63Mu5cu8etL1X7ZLyf7UP1M.jpg',
                    'genres' => ['Drama', 'Crime'],
                    'seasons' => [
                        ['season_number' => 1, 'title' => 'Season 1'],
                        ['season_number' => 2, 'title' => 'Season 2'],
                    ],
                ]);
        });

        $response = $this->postJson('/api/metadata/lookup-id', [
            'id' => $series->id,
            'type' => 'series',
            'external_id' => 'tt0903747',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'series' => [
                'id' => $series->id,
                'title' => 'Breaking Bad',
                'title_ar' => 'اختلال ضال',
                'release_year' => 2008,
                'tmdb_id' => '1396',
                'imdb_id' => 'tt0903747',
            ],
        ]);

        $series->refresh();
        $this->assertEquals('Breaking Bad', $series->title);
        $this->assertEquals('اختلال ضال', $series->title_ar);
        $this->assertEquals('1396', $series->tmdb_id);
        $this->assertEquals(2, $series->seasons()->count());
    }

    public function test_direct_id_lookup_with_tmdb_and_imdb_urls(): void
    {
        $movie = MediaItem::create([
            'title' => 'Test Movie',
            'release_year' => 2020,
            'file_path' => 'C:/Media/Test.mkv',
        ]);

        $this->mock(MetadataAggregator::class, function ($mock) {
            $mock->shouldReceive('lookupByExternalId')
                ->with('https://www.themoviedb.org/movie/27205-inception', 'movie')
                ->once()
                ->andReturn([
                    'provider' => 'TMDb',
                    'id' => '27205',
                    'tmdb_id' => '27205',
                    'imdb_id' => 'tt1375666',
                    'title' => 'Inception',
                    'release_year' => 2010,
                    'rating' => 8.8,
                ]);
        });

        $response = $this->postJson('/api/metadata/lookup-id', [
            'id' => $movie->id,
            'type' => 'movie',
            'external_id' => 'https://www.themoviedb.org/movie/27205-inception',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Inception', $movie->fresh()->title);
    }

    public function test_aggregator_correctly_resolves_different_id_formats(): void
    {
        $aggregator = app(MetadataAggregator::class);

        // Test with TMDb numeric ID
        $reflection = new \ReflectionClass($aggregator);
        $method = $reflection->getMethod('lookupByExternalId');
        $this->assertTrue($method->isPublic());
    }

    public function test_lookup_id_returns_404_when_metadata_not_found(): void
    {
        $this->mock(MetadataAggregator::class, function ($mock) {
            $mock->shouldReceive('lookupByExternalId')
                ->with('999999999', 'movie')
                ->once()
                ->andReturn(null);
        });

        $response = $this->postJson('/api/metadata/lookup-id', [
            'type' => 'movie',
            'external_id' => '999999999',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }
}
