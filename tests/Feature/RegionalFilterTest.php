<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Services\Metadata\TmdbProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RegionalFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_indian_movies_filter_behavior(): void
    {
        // 1. Indian movie by language and folder
        $kick = MediaItem::create([
            'title' => 'Kick',
            'release_year' => 2014,
            'original_language' => 'hi',
            'origin_country' => 'IN',
            'file_path' => 'H:/Entertainment/Movies/Indian/Kick (2014)/Kick (2014).mkv',
            'file_size_bytes' => 1024000,
        ]);

        // 2. Indian movie by folder and language
        $zero = MediaItem::create([
            'title' => 'Zero',
            'release_year' => 2018,
            'original_language' => 'hi',
            'origin_country' => 'IN',
            'file_path' => 'H:/Entertainment/Movies/Indian/Zero (2018)/Zero (2018).mp4',
            'file_size_bytes' => 1024000,
        ]);

        // 3. Spanish animated movie (Dogtanian) - should NOT be matched under Indian
        $dogtanian = MediaItem::create([
            'title' => 'Dogtanian and the Three Muskehounds',
            'release_year' => 2021,
            'original_language' => 'es',
            'origin_country' => 'ES',
            'file_path' => 'H:/Entertainment/Movies/Animation/Dogtanian and the Three Muskehounds (2021)/Dogtanian.mkv',
            'file_size_bytes' => 1024000,
        ]);

        // 4. US action movie - should NOT be matched under Indian
        $needForSpeed = MediaItem::create([
            'title' => 'Need for Speed',
            'release_year' => 2014,
            'original_language' => 'en',
            'origin_country' => 'US',
            'file_path' => 'H:/Entertainment/Movies/Crime/Need for Speed (2014)/Need for Speed (2014).mp4',
            'file_size_bytes' => 1024000,
        ]);

        $response = $this->get('/movies?origin=indian');
        $response->assertStatus(200);

        $response->assertInertia(function (AssertableInertia $page) {
            $page->component('Movies/Index')
                ->has('movies.data', 2)
                ->where('movies.data', function ($movies) {
                    $titles = collect($movies)->pluck('title')->toArray();

                    return in_array('Kick', $titles)
                        && in_array('Zero', $titles)
                        && ! in_array('Dogtanian and the Three Muskehounds', $titles)
                        && ! in_array('Need for Speed', $titles);
                });
        });
    }

    public function test_tmdb_provider_resolves_primary_country_intelligently(): void
    {
        // Spanish film with subcontracted Indian VFX company at index 0
        $data = [
            'production_countries' => [
                ['iso_3166_1' => 'IN', 'name' => 'India'],
                ['iso_3166_1' => 'ES', 'name' => 'Spain'],
            ],
            'origin_country' => ['IN', 'ES'],
        ];

        $resolved = TmdbProvider::resolvePrimaryCountry($data, 'es');
        $this->assertEquals('ES', $resolved);

        // Hindi film with foreign shooting location at index 0
        $bollywoodData = [
            'production_countries' => [
                ['iso_3166_1' => 'CZ', 'name' => 'Czech Republic'],
                ['iso_3166_1' => 'IN', 'name' => 'India'],
            ],
            'origin_country' => ['IN'],
        ];

        $resolvedBollywood = TmdbProvider::resolvePrimaryCountry($bollywoodData, 'hi');
        $this->assertEquals('IN', $resolvedBollywood);
    }
}
