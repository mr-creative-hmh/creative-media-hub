<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Series;
use App\Models\Season;
use App\Models\Episode;
use App\Services\Metadata\LibraryMasterIndexService;
use App\Services\Metadata\TmdbProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MediaSanitizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    public function test_audit_collections_separates_despicable_me_and_minions(): void
    {
        // Minions must be its own collection (544669) and NOT mapped to Despicable Me
        $minionsColl = TmdbProvider::inferCollectionFromTitle('Minions: The Rise of Gru');
        $this->assertEquals('Minions Collection', $minionsColl);

        $despColl = TmdbProvider::inferCollectionFromTitle('Despicable Me 4');
        $this->assertEquals('Despicable Me Collection', $despColl);
    }

    public function test_master_index_service_offline_lookup_performance(): void
    {
        $masterService = app(LibraryMasterIndexService::class);

        $t0 = microtime(true);
        $coll = $masterService->getExtendedCollection(86066); // Despicable Me
        $elapsedMs = (microtime(true) - $t0) * 1000;

        // Offline lookup must be ultra-fast
        $this->assertLessThan(100, $elapsedMs);
        if ($coll) {
            $this->assertEquals('Despicable Me Collection', $coll['name']);
            $this->assertNotEmpty($coll['parts']);
        }
    }

    public function test_mr_robot_stylized_episode_titles_are_preserved(): void
    {
        $series = Series::create([
            'title' => 'Mr. Robot',
            'slug' => 'mr-robot',
            'release_year' => 2015,
        ]);

        $season = Season::create([
            'series_id' => $series->id,
            'season_number' => 1,
            'title' => 'Season 1',
        ]);

        $ep = Episode::create([
            'series_id' => $series->id,
            'season_id' => $season->id,
            'episode_number' => 1,
            'title' => 'eps1.0_hellofriend.mov',
            'file_path' => 'H:/Entertainment/TV Shows/Mr. Robot/Season 01/Mr. Robot - S01E01 - eps1.0_hellofriend.mov.mkv',
        ]);

        // Official title must retain .mov suffix as intended by creator Sam Esmail
        $this->assertEquals('eps1.0_hellofriend.mov', $ep->title);
    }
}