<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Services\Scout\LibraryAcquisitionService;
use App\Services\Scout\LibraryGapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetadataRenameAndCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_show_returns_rich_movie_data(): void
    {
        MediaItem::create([
            'title' => 'The Matrix',
            'collection_name' => 'The Matrix Collection',
            'release_year' => 1999,
            'file_path' => 'C:/test/matrix.mkv',
        ]);

        MediaItem::create([
            'title' => 'The Matrix Reloaded',
            'collection_name' => 'The Matrix Collection',
            'release_year' => 2003,
            'file_path' => 'C:/test/reloaded.mkv',
        ]);

        $response = $this->get(route('collections.show', 'the-matrix-collection'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Collections/Show')
            ->has('collection.movies', 2)
            ->where('collection.name', 'The Matrix Collection')
        );
    }

    public function test_physical_file_rename_movie_updates_disk_and_database(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cmh_test_'.uniqid();
        $movieFolder = $root.DIRECTORY_SEPARATOR.'2.Fast.2.Furious.2003.BluRay.720p';
        mkdir($movieFolder, 0777, true);

        $oldFile = $movieFolder.DIRECTORY_SEPARATOR.'2.Fast.2.Furious.2003.BluRay.720p.mkv';
        file_put_contents($oldFile, 'fake video stream data');

        $movie = MediaItem::create([
            'title' => '2 Fast 2 Furious',
            'release_year' => 2003,
            'file_path' => str_replace('\\', '/', $oldFile),
            'folder_path' => str_replace('\\', '/', $movieFolder),
        ]);

        $response = $this->postJson("/api/metadata/movie/{$movie->id}/rename-file");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $movie->refresh();
        $expectedFolder = str_replace('\\', '/', $root.DIRECTORY_SEPARATOR.'2 Fast 2 Furious (2003)');
        $expectedNewFile = str_replace('\\', '/', $expectedFolder.DIRECTORY_SEPARATOR.'2 Fast 2 Furious (2003).mkv');

        $this->assertEquals($expectedNewFile, $movie->file_path);
        $this->assertEquals($expectedFolder, $movie->folder_path);
        $this->assertTrue(file_exists($expectedNewFile));
        $this->assertFalse(file_exists($oldFile));

        // Clean up
        @unlink($expectedNewFile);
        @rmdir($expectedFolder);
        @rmdir($root);
    }

    public function test_physical_folder_rename_series_updates_disk_and_database(): void
    {
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cmh_series_test_'.uniqid();
        $oldFolder = $tempDir.DIRECTORY_SEPARATOR.'Breaking.Bad.Complete.Series';
        mkdir($oldFolder, 0777, true);

        $series = Series::create([
            'title' => 'Breaking Bad',
            'release_year' => 2008,
            'folder_path' => str_replace('\\', '/', $oldFolder),
        ]);

        $season = Season::create([
            'series_id' => $series->id,
            'season_number' => 1,

        ]);

        $epFile = $oldFolder.DIRECTORY_SEPARATOR.'S01E01.mkv';
        file_put_contents($epFile, 'fake ep data');

        $episode = Episode::create([
            'series_id' => $series->id,
            'season_id' => $season->id,
            'episode_number' => 1,
            'title' => 'Pilot',
            'file_path' => str_replace('\\', '/', $epFile),
        ]);

        $response = $this->postJson("/api/metadata/series/{$series->id}/rename-file");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $series->refresh();
        $episode->refresh();

        $expectedNewFolder = str_replace('\\', '/', $tempDir.DIRECTORY_SEPARATOR.'Breaking Bad (2008)');
        $this->assertEquals($expectedNewFolder, $series->folder_path);
        $this->assertTrue(is_dir($expectedNewFolder));
        $this->assertFalse(is_dir($oldFolder));

        // Clean up
        @unlink($expectedNewFolder.DIRECTORY_SEPARATOR.'S01E01.mkv');
        @rmdir($expectedNewFolder);
        @rmdir($tempDir);
    }

    public function test_delete_item_from_library_index_movie(): void
    {
        $movie = MediaItem::create([
            'title' => 'Garbage Movie',
            'release_year' => 2020,
            'file_path' => 'C:/test/garbage.mkv',
        ]);

        $response = $this->deleteJson("/api/metadata/movie/{$movie->id}");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('media_items', ['id' => $movie->id]);
    }

    public function test_delete_item_from_library_index_series(): void
    {
        $series = Series::create([
            'title' => 'Garbage Series',
            'release_year' => 2020,
            'folder_path' => 'C:/test/garbage_series',
        ]);

        $season = Season::create([
            'series_id' => $series->id,
            'season_number' => 1,
        ]);

        $episode = Episode::create([
            'series_id' => $series->id,
            'season_id' => $season->id,
            'episode_number' => 1,
            'title' => 'Pilot',
            'file_path' => 'C:/test/garbage_series/ep1.mkv',
        ]);

        $response = $this->deleteJson("/api/metadata/series/{$series->id}");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('series', ['id' => $series->id]);
        $this->assertDatabaseMissing('seasons', ['id' => $season->id]);
        $this->assertDatabaseMissing('episodes', ['id' => $episode->id]);
    }

    public function test_fix_match_get_collections_and_update_collection(): void
    {
        $movie = MediaItem::create([
            'title' => 'Test Action Movie',
            'release_year' => 2024,
            'collection_name' => 'Test Franchise',
            'collection_id' => '99999',
            'collection_id_source' => 'tmdb',
            'file_path' => 'C:/test/action/Test Action Movie (2024).mkv',
            'folder_path' => 'C:/test/action',
        ]);

        // 1. Test GET /api/fix-match/collections
        $getRes = $this->getJson('/api/fix-match/collections');
        $getRes->assertStatus(200);
        $getRes->assertJsonStructure([
            'success',
            'collections' => [
                '*' => ['name', 'id', 'source', 'count'],
            ],
        ]);

        // 2. Test updating collection to a new collection
        $postRes = $this->postJson('/api/fix-match/collection', [
            'media_id' => $movie->id,
            'media_type' => 'movie',
            'collection_mode' => 'new',
            'new_collection_name' => 'Awesome New Saga',
            'collection_external_id' => '123456',
            'collection_source' => 'tmdb',
            'reorganize_folder' => false,
        ]);
        $postRes->assertStatus(200);
        $postRes->assertJson([
            'success' => true,
            'collection_name' => 'Awesome New Saga',
            'collection_id' => '123456',
            'collection_id_source' => 'tmdb',
        ]);

        $this->assertDatabaseHas('media_items', [
            'id' => $movie->id,
            'collection_name' => 'Awesome New Saga',
            'collection_id' => '123456',
            'collection_id_source' => 'tmdb',
        ]);

        // 3. Test detaching from collection
        $detachRes = $this->postJson('/api/fix-match/collection', [
            'media_id' => $movie->id,
            'media_type' => 'movie',
            'collection_mode' => 'none',
        ]);
        $detachRes->assertStatus(200);
        $detachRes->assertJson([
            'success' => true,
            'collection_name' => null,
            'collection_id' => null,
            'collection_id_source' => null,
        ]);

        $this->assertDatabaseHas('media_items', [
            'id' => $movie->id,
            'collection_name' => null,
            'collection_id' => null,
            'collection_id_source' => null,
        ]);
    }

    public function test_collection_index_and_show_strictly_enforce_two_or_more_movies(): void
    {
        // Franchise with only 1 movie: must NOT appear in collections
        MediaItem::create([
            'title' => 'Single Franchise Movie',
            'collection_name' => 'Single Movie Collection',
            'release_year' => 2021,
            'file_path' => 'C:/test/single.mkv',
        ]);

        // Franchise with 2 movies: MUST appear in collections
        MediaItem::create([
            'title' => 'Dune: Part One',
            'collection_name' => 'Dune Collection',
            'release_year' => 2021,
            'file_path' => 'C:/test/dune1.mkv',
        ]);
        MediaItem::create([
            'title' => 'Dune: Part Two',
            'collection_name' => 'Dune Collection',
            'release_year' => 2024,
            'file_path' => 'C:/test/dune2.mkv',
        ]);

        // Collections Index test
        $resIndex = $this->get(route('collections.index'));
        $resIndex->assertStatus(200);
        $resIndex->assertInertia(fn ($page) => $page->component('Collections/Index')
            ->has('collections', 1)
            ->where('collections.0.name', 'Dune Collection')
        );

        // Direct show route on 1-movie franchise must 404
        $resSingle = $this->get(route('collections.show', 'single-movie-collection'));
        $resSingle->assertStatus(404);

        // Direct show route on 2-movie franchise must 200
        $resDune = $this->get(route('collections.show', 'dune-collection'));
        $resDune->assertStatus(200);
    }

    public function test_incomplete_collection_displays_missing_parts_and_in_progress_status(): void
    {
        // Create 2 movies in Avengers Collection
        MediaItem::create([
            'title' => 'Avengers: Age of Ultron',
            'collection_name' => 'The Avengers Collection',
            'collection_id' => 86311,
            'collection_id_source' => 'tmdb',
            'tmdb_id' => 99861,
            'release_year' => 2015,
            'file_path' => 'C:/test/avengers2.mkv',
        ]);
        MediaItem::create([
            'title' => 'Avengers: Infinity War',
            'collection_name' => 'The Avengers Collection',
            'collection_id' => 86311,
            'collection_id_source' => 'tmdb',
            'tmdb_id' => 299536,
            'release_year' => 2018,
            'file_path' => 'C:/test/avengers3.mkv',
        ]);

        // Mock LibraryGapService to return gap for Avengers Collection
        $mockGapService = $this->mock(LibraryGapService::class);
        $mockGapService->shouldReceive('getGroupedCollectionGaps')->andReturn([
            [
                'collection_id' => 86311,
                'collection_name' => 'The Avengers Collection',
                'total_count' => 4,
                'owned_count' => 2,
                'missing_count' => 2,
                'completion_percent' => 50,
                'missing_movies' => [
                    [
                        'tmdb_id' => 24428,
                        'movie_title' => 'The Avengers',
                        'title' => 'The Avengers',
                        'title_ar' => 'المنتقمون',
                        'release_year' => 2012,
                        'poster_path' => 'https://image.tmdb.org/t/p/w500/avengers1.jpg',
                        'overview' => 'Earths mightiest heroes.',
                    ],
                    [
                        'tmdb_id' => 299534,
                        'movie_title' => 'Avengers: Endgame',
                        'title' => 'Avengers: Endgame',
                        'title_ar' => 'المنتقمون: نهاية اللعبة',
                        'release_year' => 2019,
                        'poster_path' => 'https://image.tmdb.org/t/p/w500/endgame.jpg',
                        'overview' => 'The grave course of events.',
                    ],
                ],
            ],
        ]);

        // 1. Collections Index should show it as in-progress with missing parts
        $resIndex = $this->get(route('collections.index'));
        $resIndex->assertStatus(200);
        $resIndex->assertInertia(fn ($page) => $page->component('Collections/Index')
            ->has('collections', 1)
            ->where('collections.0.name', 'The Avengers Collection')
            ->where('collections.0.is_complete', false)
            ->where('collections.0.movies_count', 2)
            ->where('collections.0.total_parts', 4)
            ->where('collections.0.completion_percentage', 50)
            ->has('collections.0.missing_parts', 2)
            ->where('collections.0.missing_parts.0.title', 'The Avengers')
        );

        // 2. Collections Show should show it as in-progress with missing parts list
        $resShow = $this->get(route('collections.show', 'the-avengers-collection'));
        $resShow->assertStatus(200);
        $resShow->assertInertia(fn ($page) => $page->component('Collections/Show')
            ->where('collection.name', 'The Avengers Collection')
            ->where('collection.is_complete', false)
            ->where('collection.movies_count', 2)
            ->where('collection.total_parts', 4)
            ->where('collection.completion_percentage', 50)
            ->has('collection.missing_parts', 2)
            ->where('collection.missing_parts.0.title', 'The Avengers')
            ->where('collection.missing_parts.1.title', 'Avengers: Endgame')
        );
    }

    public function test_library_acquisition_canonical_destination_and_real_episode_title(): void
    {
        $service = app(LibraryAcquisitionService::class);

        // 1. Pre-seed Series with years & real Episode title in DB
        $series = Series::create([
            'title' => 'Sense8',
            'release_year' => 2015,
            'end_year' => 2018,
            'status' => 'Ended',
        ]);

        $season = Season::create([
            'series_id' => $series->id,
            'season_number' => 1,
        ]);

        Episode::create([
            'series_id' => $series->id,
            'season_id' => $season->id,
            'episode_number' => 1,
            'title' => 'Limbic Resonance',
            'file_path' => 'H:/Entertainment/TV Shows/Sense8 (2015 - 2018)/Season 01/Sense8 - S01E01 - Limbic Resonance [1080p].mkv',
        ]);

        // Destination calculation for episode without title in metadata
        $dest = $service->calculateCanonicalDestination(
            'D:/Downloads/Sense8.S01E01.1080p.mkv',
            'H:/Entertainment/TV Shows',
            'series',
            [
                'series_title' => 'Sense 8', // Test normalization
                'season_number' => 1,
                'episode_number' => 1,
                'resolution' => '1080p',
            ]
        );

        $this->assertEquals(
            'H:/Entertainment/TV Shows/Sense8 (2015 - 2018)/Season 01/Sense8 - S01E01 - Limbic Resonance [1080p].mkv',
            $dest
        );

        // 2. Test Franchise Movie routing to {Collection Name} Collection
        $movieDest = $service->calculateCanonicalDestination(
            'D:/Downloads/Bad.Boys.Ride.or.Die.2024.1080p.mkv',
            'H:/Entertainment/Movies',
            'movie',
            [
                'movie_title' => 'Bad Boys: Ride or Die',
                'release_year' => 2024,
                'genre' => 'Action',
                'collection_name' => 'Bad Boys Collection',
            ]
        );

        $this->assertEquals(
            'H:/Entertainment/Movies/Action/Bad Boys Collection/Bad Boys - Ride or Die (2024)/Bad Boys - Ride or Die (2024).mkv',
            $movieDest
        );
    }
}
