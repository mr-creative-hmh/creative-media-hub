<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
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
}
