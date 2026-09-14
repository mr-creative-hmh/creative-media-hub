<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstantSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_instant_search_returns_empty_for_short_query(): void
    {
        $response = $this->getJson('/api/search/instant?q=a');

        $response->assertOk()
            ->assertJson([
                'query' => 'a',
                'movies' => [],
                'collections' => [],
                'series' => [],
                'total' => 0,
            ]);
    }

    public function test_instant_search_matches_movies_collections_and_series(): void
    {
        $movie = MediaItem::create([
            'title' => 'The Dark Knight',
            'title_ar' => 'فارس الظلام',
            'release_year' => 2008,
            'rating' => 9.0,
            'resolution' => '1080p',
            'file_path' => 'D:/Media/Movies/The Dark Knight (2008).mkv',
            'file_size_bytes' => 1024000,
        ]);

        MediaItem::create([
            'title' => 'Dark Waters 1',
            'collection_name' => 'Dark Waters Collection',
            'release_year' => 2019,
            'rating' => 7.5,
            'file_path' => 'D:/Media/Movies/Dark Waters 1.mkv',
            'file_size_bytes' => 1024000,
        ]);
        MediaItem::create([
            'title' => 'Dark Waters 2',
            'collection_name' => 'Dark Waters Collection',
            'release_year' => 2021,
            'rating' => 7.8,
            'file_path' => 'D:/Media/Movies/Dark Waters 2.mkv',
            'file_size_bytes' => 1024000,
        ]);

        $series = Series::create([
            'title' => 'Dark Matter',
            'title_ar' => 'المادة المظلمة',
            'release_year' => 2024,
            'rating' => 8.2,
        ]);

        // Search English
        $response = $this->getJson('/api/search/instant?q=Dark');

        $response->assertOk()
            ->assertJsonStructure([
                'query',
                'movies' => [
                    '*' => ['id', 'slug', 'title', 'title_ar', 'year', 'rating', 'poster', 'quality', 'type', 'url'],
                ],
                'collections' => [
                    '*' => ['id', 'slug', 'title', 'title_ar', 'year', 'rating', 'poster', 'quality', 'count', 'type', 'url'],
                ],
                'series' => [
                    '*' => ['id', 'slug', 'title', 'title_ar', 'year', 'rating', 'poster', 'quality', 'type', 'url'],
                ],
                'total',
            ]);

        $this->assertGreaterThanOrEqual(3, $response->json('total'));
        $movieTitles = collect($response->json('movies'))->pluck('title')->toArray();
        $this->assertContains('The Dark Knight', $movieTitles);
        $this->assertContains('Dark Waters 1', $movieTitles);
        $this->assertEquals('Dark Waters Collection', $response->json('collections.0.title'));
        $this->assertEquals(2, $response->json('collections.0.count'));
        $this->assertEquals('Dark Matter', $response->json('series.0.title'));

        // Search Arabic
        $arResponse = $this->getJson('/api/search/instant?q=الظلام');
        $arResponse->assertOk();
        $this->assertGreaterThanOrEqual(1, $arResponse->json('total'));
        $this->assertEquals('The Dark Knight', $arResponse->json('movies.0.title'));
    }

    public function test_arabic_cinema_filter_does_not_match_western_movies(): void
    {
        // Western movie with translated Arabic title
        MediaItem::create([
            'title' => 'Inception',
            'title_ar' => 'استهلال',
            'release_year' => 2010,
            'origin_country' => 'US',
            'original_language' => 'en',
            'file_path' => 'D:/Media/Movies/Inception (2010).mkv',
            'file_size_bytes' => 1024000,
        ]);

        // Genuine Arabic movie
        MediaItem::create([
            'title' => 'Al Kanz',
            'title_ar' => 'الكنز',
            'release_year' => 2017,
            'origin_country' => 'EG',
            'original_language' => 'ar',
            'file_path' => 'D:/Media/Movies/Al Kanz (2017).mkv',
            'file_size_bytes' => 1024000,
        ]);

        $response = $this->get('/movies?origin=arabic');
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('Movies/Index')
            ->has('movies.data', 1)
            ->where('movies.data.0.title', 'Al Kanz')
        );
    }
}
