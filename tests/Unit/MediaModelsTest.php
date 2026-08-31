<?php

namespace Tests\Unit;

use App\Models\Episode;
use App\Models\Genre;
use App\Models\MediaItem;
use App\Models\Person;
use App\Models\Season;
use App\Models\Series;
use App\Models\Subtitle;
use App\Models\WatchHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_item_can_be_created_with_genres_and_people(): void
    {
        $genre = Genre::create([
            'name_en' => 'Sci-Fi',
            'name_ar' => 'خيال علمي',
            'slug' => 'sci-fi',
        ]);

        $person = Person::create([
            'name' => 'Christopher Nolan',
            'name_ar' => 'كريستوفر نولان',
        ]);

        $movie = MediaItem::create([
            'title' => 'Interstellar',
            'title_ar' => 'بين النجوم',
            'release_year' => 2014,
            'rating' => 8.7,
            'resolution' => '4K UHD',
        ]);

        $movie->genres()->attach($genre->id);
        $movie->people()->attach($person->id, ['role' => 'director']);

        $this->assertCount(1, $movie->genres);
        $this->assertEquals('Sci-Fi', $movie->genres->first()->name_en);
        $this->assertCount(1, $movie->directors);
        $this->assertEquals('Christopher Nolan', $movie->directors->first()->name);
    }

    public function test_series_hierarchy_with_seasons_and_episodes(): void
    {
        $series = Series::create([
            'title' => 'Breaking Bad',
            'title_ar' => 'اختلال ضال',
            'release_year' => 2008,
        ]);

        $season = Season::create([
            'series_id' => $series->id,
            'season_number' => 1,
            'title' => 'Season 1',
        ]);

        $episode = Episode::create([
            'series_id' => $series->id,
            'season_id' => $season->id,
            'episode_number' => 1,
            'title' => 'Pilot',
        ]);

        $subtitle = Subtitle::create([
            'subtitlable_id' => $episode->id,
            'subtitlable_type' => Episode::class,
            'language' => 'ar',
            'language_name' => 'Arabic',
        ]);

        $this->assertCount(1, $series->seasons);
        $this->assertCount(1, $season->episodes);
        $this->assertCount(1, $episode->subtitles);
        $this->assertEquals('Pilot', $season->episodes->first()->title);
    }
}
