<?php

namespace Tests\Feature;

use App\Http\Controllers\MetadataManagementController;
use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Models\Subtitle;
use App\Services\Metadata\ArtworkDownloadService;
use App\Services\Metadata\LibraryMasterIndexService;
use App\Services\Organizer\PhysicalOrganizerService;
use App\Services\Scanner\VirtualLibraryScannerService;
use App\Services\Scout\LibraryGapService;
use App\Services\Streaming\SubtitleStreamService;
use App\Services\Subtitles\SubtitleHealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase1BackendBugFixesTest extends TestCase
{
    use RefreshDatabase;

    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = str_replace('\\', '/', sys_get_temp_dir().'/cmh_phase1_test_'.uniqid('', true));
        File::ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (isset($this->tempDir) && File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    public function test_instant_search_caches_plain_arrays_compatible_with_serializable_classes_false(): void
    {
        MediaItem::create([
            'title' => 'The Matrix',
            'file_path' => $this->tempDir.'/matrix.mkv',
            'release_year' => 1999,
            'rating' => 8.7,
        ]);

        Series::create([
            'title' => 'Matrix Chronicles',
            'folder_path' => $this->tempDir.'/matrix-series',
            'release_year' => 2003,
            'rating' => 7.9,
        ]);

        $response = $this->getJson('/api/search/instant?q=Matrix');
        $response->assertStatus(200)
            ->assertJsonPath('total', 2);

        $cached = Cache::get('instant_search_v4_'.md5('matrix'));
        $this->assertIsArray($cached);
        $this->assertIsArray($cached['movies']);
        $this->assertIsArray($cached['collections']);
        $this->assertIsArray($cached['series']);

        $roundTripped = unserialize(serialize($cached), ['allowed_classes' => false]);
        $this->assertIsArray($roundTripped);
        $this->assertIsArray($roundTripped['movies']);
        $this->assertSame('The Matrix', $roundTripped['movies'][0]['title']);
        $this->assertSame('Matrix Chronicles', $roundTripped['series'][0]['title']);
    }

    public function test_metadata_update_movie_collection_uses_build_master_index(): void
    {
        $movie = MediaItem::create([
            'title' => 'Batman Begins',
            'file_path' => $this->tempDir.'/batman-begins.mkv',
            'folder_path' => $this->tempDir,
        ]);

        $this->mock(LibraryMasterIndexService::class, function ($mock): void {
            $mock->shouldReceive('buildMasterIndex')
                ->once()
                ->with(false, false)
                ->andReturn([]);
        });

        $response = $this->postJson("/api/metadata/movie/{$movie->id}/collection", [
            'collection_name' => 'The Dark Knight Collection',
            'collection_id' => '263',
            'relocate_on_disk' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('movie.collection_name', 'The Dark Knight Collection');
    }

    public function test_metadata_convert_movie_to_series_populates_episode_series_id(): void
    {
        $movie = MediaItem::create([
            'title' => 'Pilot Special',
            'file_path' => $this->tempDir.'/pilot.mkv',
            'resolution' => '1080p FHD',
        ]);

        $response = $this->postJson("/api/metadata/movie/{$movie->id}/convert-type");
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('new_type', 'series');

        $newSeriesId = $response->json('new_id');
        $this->assertDatabaseHas('episodes', [
            'series_id' => $newSeriesId,
            'episode_number' => 1,
            'title' => 'Pilot Special',
        ]);
    }

    public function test_metadata_relocate_file_updates_file_size_bytes_column(): void
    {
        $videoPath = $this->tempDir.'/relocated_movie.mkv';
        File::put($videoPath, str_repeat('X', 2048));

        $movie = MediaItem::create([
            'title' => 'Relocated Film',
            'file_path' => $this->tempDir.'/old_missing.mkv',
            'file_size_bytes' => 0,
        ]);

        $response = $this->postJson("/api/metadata/movie/{$movie->id}/relocate-file", [
            'new_path' => $videoPath,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $movie->refresh();
        $this->assertSame(2048, (int) $movie->file_size_bytes);
    }

    public function test_metadata_verify_file_handles_associative_monitored_directories(): void
    {
        $moviesDir = $this->tempDir.'/Movies';
        File::ensureDirectoryExists($moviesDir);
        $candidate = $moviesDir.'/Arrival (2016).mkv';
        File::put($candidate, 'fake video');

        AppSetting::create([
            'key' => 'scanner_monitored_directories',
            'value' => json_encode([
                ['id' => 'dir-1', 'path' => $moviesDir, 'type' => 'movies'],
            ]),
        ]);

        $movie = MediaItem::create([
            'title' => 'Arrival',
            'release_year' => 2016,
            'file_path' => $this->tempDir.'/missing/Arrival.mkv',
        ]);

        $response = $this->postJson("/api/metadata/movie/{$movie->id}/verify-file");
        $response->assertStatus(200)
            ->assertJsonPath('exists', false)
            ->assertJsonPath('candidate_path', $candidate);

        $controller = app(MetadataManagementController::class);
        $method = new \ReflectionMethod($controller, 'isDedicatedMovieFolder');
        $this->assertFalse($method->invoke($controller, $moviesDir, $movie->id));
    }

    public function test_fix_match_collection_rejects_series_media_type(): void
    {
        $series = Series::create([
            'title' => 'Severance',
            'folder_path' => $this->tempDir.'/Severance',
        ]);

        $response = $this->postJson('/api/fix-match/collection', [
            'media_id' => $series->id,
            'media_type' => 'series',
            'collection_mode' => 'new',
            'new_collection_name' => 'Lumon Collection',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_physical_organizer_resolve_cached_episode_succeeds_without_accessor_column(): void
    {
        $series = Series::create([
            'title' => 'Andor',
            'folder_path' => $this->tempDir.'/Andor',
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
            'title' => 'Kassa',
            'file_path' => $this->tempDir.'/Andor/S01/Andor S01E01.mkv',
        ]);

        $organizer = app(PhysicalOrganizerService::class);
        $resolved = $organizer->resolveCachedEpisode($series->id, 1, 1, $episode->file_path);

        $this->assertNotNull($resolved);
        $this->assertSame($episode->id, $resolved->id);
    }

    public function test_virtual_scanner_relocates_polymorphic_subtitles_for_movie(): void
    {
        $newDir = $this->tempDir.'/NewFolder';
        File::ensureDirectoryExists($newDir);

        $newVideo = $newDir.'/Dune (2021).mkv';
        $newSub = $newDir.'/Dune (2021).en.srt';
        File::put($newVideo, 'video');
        File::put($newSub, "1\n00:00:01,000 --> 00:00:03,000\nHello\n");

        $movie = MediaItem::create([
            'title' => 'Dune',
            'file_path' => $newVideo,
        ]);

        $subtitle = Subtitle::create([
            'subtitlable_type' => MediaItem::class,
            'subtitlable_id' => $movie->id,
            'language' => 'en',
            'language_name' => 'English',
            'file_path' => $this->tempDir.'/OldFolder/Dune (2021).en.srt',
            'format' => 'srt',
        ]);

        $scanner = app(VirtualLibraryScannerService::class);
        $method = new \ReflectionMethod($scanner, 'relocateSubtitlesForMovie');
        $method->invoke($scanner, $movie, $newVideo);

        $subtitle->refresh();
        $this->assertSame($newSub, $subtitle->file_path);
    }

    public function test_subtitle_health_check_resolves_episode_via_season_relationship(): void
    {
        $series = Series::create([
            'title' => 'Dark',
            'folder_path' => $this->tempDir.'/Dark',
        ]);

        $season = Season::create([
            'series_id' => $series->id,
            'season_number' => 2,
            'title' => 'Season 2',
        ]);

        $episode = Episode::create([
            'series_id' => $series->id,
            'season_id' => $season->id,
            'episode_number' => 5,
            'title' => 'Lost and Found',
            'file_path' => $this->tempDir.'/Dark/S02/Dark.2017.S02E05.mkv',
        ]);

        $healthService = app(SubtitleHealthCheckService::class);
        $method = new \ReflectionMethod($healthService, 'findAdjacentMediaModel');
        $owner = $method->invoke(
            $healthService,
            $this->tempDir.'/Dark/S02/Dark.S02E05.en.srt',
            'Dark.S02E05'
        );

        $this->assertInstanceOf(Episode::class, $owner);
        $this->assertSame($episode->id, $owner->id);
    }

    public function test_artwork_download_service_normalizes_tmdb_relative_path_with_slash(): void
    {
        $service = app(ArtworkDownloadService::class);
        $method = new \ReflectionMethod($service, 'normalizeRemoteUrl');

        $normalized = $method->invoke($service, '/kqjL17yufvn9OVLyXYpvtyrFfak.jpg', 'w500');

        $this->assertSame('https://image.tmdb.org/t/p/w500/kqjL17yufvn9OVLyXYpvtyrFfak.jpg', $normalized);
    }

    public function test_scanner_remove_directory_handles_missing_index_without_server_error(): void
    {
        $response = $this->deleteJson('/api/scanner/directories');

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_movies_and_series_index_fallback_on_invalid_sort_direction(): void
    {
        $this->get('/movies?sort=year&direction=invalid')->assertStatus(200);
        $this->get('/series?sort=year&direction=invalid')->assertStatus(200);
    }

    public function test_docs_route_is_cacheable_and_renders(): void
    {
        $route = Route::getRoutes()->getByName('docs.index');

        $this->assertNotNull($route);
        $this->assertIsString($route->getAction('uses'));
        $this->get('/docs')->assertStatus(200);
    }

    public function test_library_gap_service_guards_pooled_connection_exceptions(): void
    {
        config(['services.tmdb.key' => 'test-tmdb-key']);

        MediaItem::create([
            'title' => 'Broken Collection Movie',
            'file_path' => $this->tempDir.'/broken.mkv',
            'collection_id' => 101,
            'collection_name' => 'Broken Collection',
            'tmdb_id' => 1001,
        ]);

        MediaItem::create([
            'title' => 'Healthy Collection Movie',
            'file_path' => $this->tempDir.'/healthy.mkv',
            'collection_id' => 202,
            'collection_name' => 'Healthy Collection',
            'tmdb_id' => 2002,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.themoviedb.org/3/collection/101*' => fn () => throw new ConnectionException('Simulated timeout'),
            'https://api.themoviedb.org/3/collection/202*' => Http::response([
                'id' => 202,
                'name' => 'Healthy Collection',
                'parts' => [
                    ['id' => 2002, 'title' => 'Healthy Collection Movie', 'release_date' => '2020-01-01'],
                    ['id' => 2003, 'title' => 'Healthy Collection Sequel', 'release_date' => '2022-01-01'],
                ],
            ], 200),
        ]);

        $gapService = app(LibraryGapService::class);
        $gapService->getGroupedCollectionGaps(true);

        $this->assertFalse(Cache::has('scout_tmdb_col_101'));
        $this->assertTrue(Cache::has('scout_tmdb_col_202'));
    }

    public function test_subtitle_stream_service_shifts_fractional_timestamps_cleanly(): void
    {
        $service = app(SubtitleStreamService::class);
        $srt = "1\n00:01:05,750 --> 00:01:09,250\nShifted line\n";

        $vtt = $service->convertToCleanWebVTT($srt, 'srt', 5.5);

        $this->assertStringContainsString('00:01:00.250 --> 00:01:03.750', $vtt);
    }
}
