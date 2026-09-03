<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Services\Organizer\PhysicalOrganizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemanticRoutesAndGenreTest extends TestCase
{
    use RefreshDatabase;

    public function test_movie_slug_generation()
    {
        $movie = MediaItem::create([
            'title' => 'Inception Test Movie',
            'release_year' => 2010,
            'file_path' => 'D:/Media/Inception.mkv',
            'file_size_bytes' => 1024000,
        ]);

        $this->assertEquals('inception-test-movie-2010', $movie->slug);
    }

    public function test_physical_organizer_resolves_genre_token()
    {
        $organizer = app(PhysicalOrganizerService::class);
        $dryRun = $organizer->generateDryRun([
            [
                'path' => 'D:/Downloads/Gladiator.2000.1080p.mkv',
                'genres' => ['Action', 'Drama', 'Adventure'],
            ],
        ], 'D:/Media', '{Type}/{Genre}/{Title} ({Year})/{Title} ({Year}) [{Resolution}].{ext}');

        $this->assertCount(1, $dryRun);
        $this->assertEquals('D:/Media/Movies/Action/Gladiator (2000)/Gladiator (2000) [1080p FHD].mkv', $dryRun[0]['destination_path']);
    }
}
