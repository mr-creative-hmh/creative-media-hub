<?php

namespace Tests\Feature;

use App\Http\Controllers\MetadataManagementController;
use App\Http\Middleware\EnableCrossOriginIsolation;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Models\Subtitle;
use App\Models\WatchHistory;
use App\Services\Metadata\TmdbProvider;
use App\Services\Subtitles\SubtitleManagerService;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase2BackendPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_connection_pragmas_are_configured_without_downgrading_busy_timeout(): void
    {
        $this->assertSame(60000, config('database.connections.sqlite.busy_timeout'));
        $this->assertSame(-64000, config('database.connections.sqlite.pragmas.cache_size'));
        $this->assertSame('MEMORY', config('database.connections.sqlite.pragmas.temp_store'));
        $this->assertSame(268435456, config('database.connections.sqlite.pragmas.mmap_size'));

        $cacheSize = DB::selectOne('PRAGMA cache_size');
        $this->assertNotNull($cacheSize);
        $this->assertSame(-64000, (int) ($cacheSize->cache_size ?? 0));
    }

    public function test_stream_and_api_routes_strip_session_and_inertia_middleware_while_web_routes_keep_them(): void
    {
        $this->get('/up');

        $router = app('router');
        $streamRoute = Route::getRoutes()->getByName('stream.movie');
        $apiRoute = Route::getRoutes()->getByName('api.search.instant');

        $this->assertNotNull($streamRoute);
        $this->assertNotNull($apiRoute);

        foreach ([$streamRoute, $apiRoute] as $statelessRoute) {
            $excluded = $statelessRoute->excludedMiddleware();
            $this->assertContains(StartSession::class, $excluded);
            $this->assertContains(EncryptCookies::class, $excluded);
            $this->assertContains(PreventRequestForgery::class, $excluded);
            $this->assertContains(HandleInertiaRequests::class, $excluded);
            $this->assertContains(HandleAppearance::class, $excluded);
            $this->assertContains(AddLinkHeadersForPreloadedAssets::class, $excluded);

            $resolved = $router->gatherRouteMiddleware($statelessRoute);
            $this->assertNotContains(StartSession::class, $resolved);
            $this->assertNotContains(HandleInertiaRequests::class, $resolved);
        }

        $dashboardRoute = Route::getRoutes()->getByName('dashboard');
        $favoriteRoute = Route::getRoutes()->getByName('movies.favorite');

        $this->assertNotNull($dashboardRoute);
        $this->assertNotNull($favoriteRoute);

        foreach ([$dashboardRoute, $favoriteRoute] as $webRoute) {
            $this->assertSame([], $webRoute->excludedMiddleware());
            $resolved = $router->gatherRouteMiddleware($webRoute);
            $this->assertContains(StartSession::class, $resolved);
            $this->assertContains(HandleInertiaRequests::class, $resolved);
            $this->assertContains(EnableCrossOriginIsolation::class, $resolved);
        }
    }

    public function test_phase2_index_migration_drops_duplicate_indexes_and_adds_missing_hot_indexes(): void
    {
        $dropped = [
            'media_items' => [
                'idx_media_items_collection',
                'idx_media_items_file_path',
                'media_items_rating_index',
                'media_items_original_language_index',
            ],
            'episodes' => ['idx_episodes_file_path'],
            'series' => ['idx_series_rating', 'series_original_language_index'],
            'subtitles' => ['subtitles_subtitlable_type_subtitlable_id_index'],
            'watch_histories' => ['watch_histories_watchable_type_watchable_id_index'],
        ];

        foreach ($dropped as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->assertFalse(
                    Schema::hasIndex($table, $index),
                    "Expected duplicate/redundant index [{$index}] on [{$table}] to be dropped."
                );
            }
        }

        $added = [
            'personables' => ['idx_personables_person_id'],
            'watch_histories' => ['idx_watch_histories_completed_last_watched'],
            'media_items' => [
                'idx_media_items_created_at',
                'idx_media_items_title',
                'idx_media_items_file_size_bytes',
            ],
            'series' => ['idx_series_created_at', 'idx_series_title'],
            'episodes' => ['idx_episodes_file_size_bytes'],
            'subtitles' => ['idx_subtitles_file_path'],
            'download_items' => [
                'idx_download_items_created_at',
                'idx_download_items_organized_path',
                'idx_download_items_indexed_id',
            ],
        ];

        foreach ($added as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->assertTrue(
                    Schema::hasIndex($table, $index),
                    "Expected hot index [{$index}] on [{$table}] to exist."
                );
            }
        }
    }

    public function test_app_setting_memoizes_repeated_reads_and_invalidates_on_save_or_delete(): void
    {
        AppSetting::create([
            'key' => 'perf_test_key',
            'value' => 'initial_value',
            'type' => 'string',
        ]);
        AppSetting::flushMemo();

        DB::enableQueryLog();
        DB::flushQueryLog();

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame('initial_value', AppSetting::get('perf_test_key'));
            $this->assertSame('default_missing', AppSetting::get('nonexistent_perf_key', 'default_missing'));
        }

        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();

        AppSetting::set('perf_test_key', 'updated_via_set');
        $this->assertSame('updated_via_set', AppSetting::get('perf_test_key'));

        $model = AppSetting::where('key', 'perf_test_key')->firstOrFail();
        $model->update(['value' => 'updated_via_eloquent']);
        $this->assertSame('updated_via_eloquent', AppSetting::get('perf_test_key'));

        $model->delete();
        $this->assertSame('after_delete', AppSetting::get('perf_test_key', 'after_delete'));
    }

    public function test_subtitle_manager_find_missing_subtitles_executes_bounded_queries_without_n_plus_1(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $movie = MediaItem::create([
                'title' => "Movie {$i}",
                'file_path' => "H:/Movies/movie_{$i}.mkv",
            ]);

            if ($i === 1) {
                Subtitle::create([
                    'subtitlable_type' => MediaItem::class,
                    'subtitlable_id' => $movie->id,
                    'language' => 'ar',
                    'format' => 'srt',
                ]);
                Subtitle::create([
                    'subtitlable_type' => MediaItem::class,
                    'subtitlable_id' => $movie->id,
                    'language' => 'en',
                    'format' => 'srt',
                ]);
            }
        }

        $series = Series::create([
            'title' => 'Silo',
            'folder_path' => 'H:/Series/Silo',
        ]);
        $season = Season::create([
            'series_id' => $series->id,
            'season_number' => 2,
        ]);

        for ($e = 1; $e <= 4; $e++) {
            Episode::create([
                'series_id' => $series->id,
                'season_id' => $season->id,
                'episode_number' => $e,
                'title' => "Episode {$e}",
                'file_path' => "H:/Series/Silo/S02E0{$e}.mkv",
            ]);
        }

        $manager = app(SubtitleManagerService::class);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $missing = $manager->findMissingSubtitles();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(4, $queryCount);
        $this->assertCount(8, $missing);

        $firstEpisode = collect($missing)->firstWhere('type', 'episode');
        $this->assertNotNull($firstEpisode);
        $this->assertSame('S2E1', $firstEpisode['season_info']);
    }

    public function test_metadata_list_collections_uses_single_grouped_query(): void
    {
        MediaItem::create(['title' => 'Dune 1', 'collection_name' => 'Dune Collection', 'collection_id' => 726871]);
        MediaItem::create(['title' => 'Dune 2', 'collection_name' => 'Dune Collection', 'collection_id' => 726871]);
        MediaItem::create(['title' => 'Blade Runner', 'collection_name' => 'Blade Runner Collection', 'collection_id' => 422837]);
        MediaItem::create(['title' => 'Avatar 1', 'collection_name' => 'Avatar Collection', 'collection_id' => 87096]);

        app(MetadataManagementController::class);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = $this->getJson('/api/collections/list');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertStatus(200);
        $this->assertSame(1, $queryCount);
        $this->assertCount(3, $response->json());
    }

    public function test_watch_history_batches_relations_without_per_item_n_plus_1(): void
    {
        for ($c = 1; $c <= 3; $c++) {
            $m1 = MediaItem::create([
                'title' => "Franchise {$c} Part 1",
                'collection_name' => "Franchise {$c}",
                'release_year' => 2020,
            ]);
            MediaItem::create([
                'title' => "Franchise {$c} Part 2",
                'collection_name' => "Franchise {$c}",
                'release_year' => 2022,
            ]);

            WatchHistory::create([
                'watchable_type' => MediaItem::class,
                'watchable_id' => $m1->id,
                'progress_seconds' => 300,
                'duration_seconds' => 3600,
                'is_completed' => false,
                'last_watched_at' => now(),
            ]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = $this->getJson('/api/watch-history');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('items'));
        $this->assertLessThanOrEqual(6, $queryCount);
    }

    public function test_subtitle_search_and_tmdb_bilingual_season_episodes_pool_concurrently(): void
    {
        AppSetting::set('tmdb_api_key', 'fake-tmdb-key');
        AppSetting::set('opensubtitles_api_key', 'fake-os-key');
        AppSetting::set('subdl_api_key', 'fake-subdl-key');

        Http::preventStrayRequests();
        Http::fake([
            'https://subsense.nepiraw.com/*' => Http::response([
                'subtitles' => [
                    ['id' => 'ss-1', 'lang' => 'ara', 'source' => 'opensubtitles', 'releaseName' => 'Inception.2010.1080p', 'url' => 'https://example.com/ss.srt'],
                ],
            ], 200),
            'https://api.opensubtitles.com/api/v1/subtitles*' => Http::response([
                'data' => [
                    [
                        'id' => 'os-rest-1',
                        'attributes' => [
                            'language' => 'ar',
                            'release' => 'Inception.REST',
                            'download_count' => 100,
                            'ratings' => 9.2,
                            'files' => [['file_id' => 555, 'file_name' => 'inception.ar.srt']],
                        ],
                    ],
                ],
            ], 200),
            'https://opensubtitles-v3.strem.io/subtitles/*' => Http::response([
                'subtitles' => [
                    ['id' => 'os-v3-1', 'lang' => 'eng', 'movieReleaseName' => 'Inception.V3', 'url' => 'https://example.com/v3.srt', 'g' => 50],
                ],
            ], 200),
            'https://api.subdl.com/api/v1/subtitles*' => Http::response([
                'status' => true,
                'subtitles' => [
                    ['id' => 'sdl-1', 'language' => 'AR', 'release_name' => 'Inception.SubDL', 'name' => 'subdl.srt', 'url' => '/subtitle/123.zip', 'download_count' => 80],
                ],
            ], 200),
            'https://yts-subs.com/*' => Http::response('', 404),
            'https://yifysubtitles.ch/*' => Http::response('', 404),
            'https://api.themoviedb.org/3/tv/1396/season/1*language=en-US*' => Http::response([
                'episodes' => [
                    ['episode_number' => 1, 'name' => 'Pilot', 'overview' => 'English overview', 'vote_average' => 9.0, 'air_date' => '2008-01-20', 'runtime' => 58],
                ],
            ], 200),
            'https://api.themoviedb.org/3/tv/1396/season/1*language=ar-SA*' => Http::response([
                'episodes' => [
                    ['episode_number' => 1, 'name' => 'الحلقة التجريبية', 'overview' => 'ملخص عربي'],
                ],
            ], 200),
        ]);

        $searchRes = $this->getJson('/api/subtitles/search?imdb_id=tt1375666&query=Inception&type=movie&language=ar,en');
        $searchRes->assertStatus(200)
            ->assertJsonPath('meta.count', 4);
        $this->assertCount(4, $searchRes->json('results'));

        $episodes = app(TmdbProvider::class)->getSeasonEpisodesBilingual(1396, 1);
        $this->assertArrayHasKey(1, $episodes);
        $this->assertSame('Pilot', $episodes[1]['title']);
        $this->assertSame('الحلقة التجريبية', $episodes[1]['title_ar']);
        $this->assertSame('ملخص عربي', $episodes[1]['overview_ar']);
    }
}
