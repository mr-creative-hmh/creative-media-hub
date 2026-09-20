<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Services\Scout\QualityUpgradeAuditService;
use App\Services\Scout\TorrentDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScoutQualityUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AppSetting::set('tmdb_api_key', 'test_api_key');

        Http::fake([
            'api.themoviedb.org/*' => Http::response(['results' => []], 200),
            'torrentio.strem.fun/*' => Http::response([
                'streams' => [
                    [
                        'name' => '1080p BluRay Clean',
                        'title' => "Movie.Title.2021.1080p.BluRay.x264\n💾 2.1 GB\n👤 25",
                        'infoHash' => 'abc1112223334445556667778889990001112223',
                    ],
                    [
                        'name' => '720p HC Korean Sub Rip',
                        'title' => "Movie.Title.2021.720p.HC.HDRip.x264.KORSUB\n💾 1.2 GB\n👤 15",
                        'infoHash' => 'def1112223334445556667778889990001112224',
                    ],
                    [
                        'name' => 'HDCAM Cinema Recording',
                        'title' => "Movie.Title.2021.HDCAM.x264-TELESYNC\n💾 900 MB\n👤 30",
                        'infoHash' => 'ghi1112223334445556667778889990001112225',
                    ],
                ],
            ], 200),
            'yts.lt/*' => Http::response(['data' => ['movies' => []]], 200),
            'apibay.org/*' => Http::response([], 200),
            'eztv.re/*' => Http::response(['torrents' => []], 200),
        ]);
    }

    /**
     * Test that QualityUpgradeAuditService strictly flags sub-720p files
     * and strictly excludes 720p, 1080p, and 4K media.
     */
    public function test_low_resolution_audit_strictly_flags_below_720p(): void
    {
        // 1. Should be FLAGGED (< 720p)
        $movie480p = MediaItem::create([
            'title' => 'Classic SD Film',
            'release_year' => 1995,
            'resolution' => '480p SD',
            'video_codec' => 'h264',
            'file_path' => 'H:/Entertainment/Movies/Classic SD Film (1995)/film.mp4',
        ]);

        $movie576p = MediaItem::create([
            'title' => 'European PAL Film',
            'release_year' => 2002,
            'resolution' => '576p SD',
            'video_codec' => 'mpeg4',
            'file_path' => 'H:/Entertainment/Movies/European PAL Film (2002)/film.avi',
        ]);

        // 2. Should NOT be flagged (720p, 1080p, 4K)
        $movie720p = MediaItem::create([
            'title' => 'High Definition 720p Movie',
            'release_year' => 2015,
            'resolution' => '720p HD',
            'video_codec' => 'h264',
            'file_path' => 'H:/Entertainment/Movies/HD 720p (2015)/film.mkv',
        ]);

        $movie1080p = MediaItem::create([
            'title' => 'Full HD 1080p Movie',
            'release_year' => 2020,
            'resolution' => '1080p Full HD',
            'video_codec' => 'hevc',
            'file_path' => 'H:/Entertainment/Movies/FHD 1080p (2020)/film.mkv',
        ]);

        $movie4k = MediaItem::create([
            'title' => 'Ultra HD 4K Movie',
            'release_year' => 2023,
            'resolution' => '4K Ultra HD',
            'video_codec' => 'hevc',
            'file_path' => 'H:/Entertainment/Movies/UHD 4K (2023)/film.mkv',
        ]);

        // TV Episode tests
        $series = Series::create([
            'title' => 'Test Series',
            'release_year' => 2018,
        ]);
        $season = Season::create([
            'series_id' => $series->id,
            'season_number' => 1,
        ]);

        $episodeSD = Episode::create([
            'series_id' => $series->id,
            'season_id' => $season->id,
            'title' => 'Old Episode',
            'episode_number' => 1,
            'resolution' => '480p SD',
            'file_path' => 'H:/Entertainment/TV Shows/Test Series/S01E01.mkv',
        ]);

        $episode720p = Episode::create([
            'series_id' => $series->id,
            'season_id' => $season->id,
            'title' => 'Crisp 720p Episode',
            'episode_number' => 2,
            'resolution' => '720p HD',
            'file_path' => 'H:/Entertainment/TV Shows/Test Series/S01E02.mkv',
        ]);

        /** @var QualityUpgradeAuditService $auditService */
        $auditService = app(QualityUpgradeAuditService::class);
        $result = $auditService->getLowResolutionMedia(true);

        $flaggedMovieIds = collect($result['movies'])->pluck('local_id')->all();
        $flaggedEpisodeIds = collect($result['episodes'])->pluck('local_id')->all();

        // Assert 480p and 576p are flagged
        $this->assertContains($movie480p->id, $flaggedMovieIds);
        $this->assertContains($movie576p->id, $flaggedMovieIds);
        $this->assertContains($episodeSD->id, $flaggedEpisodeIds);

        // Assert 720p, 1080p, 4K are NEVER flagged
        $this->assertNotContains($movie720p->id, $flaggedMovieIds, '720p HD movie must not be flagged for low-res upgrade!');
        $this->assertNotContains($movie1080p->id, $flaggedMovieIds, '1080p movie must not be flagged for low-res upgrade!');
        $this->assertNotContains($movie4k->id, $flaggedMovieIds, '4K movie must not be flagged for low-res upgrade!');
        $this->assertNotContains($episode720p->id, $flaggedEpisodeIds, '720p episode must not be flagged for low-res upgrade!');
    }

    /**
     * Test QualityUpgradeAuditService flags movies with mono audio channel on modern films.
     */
    public function test_flags_poor_quality_mono_audio_on_modern_films(): void
    {
        // Modern 2021 release with 1-channel mono audio (typical of line-audio CAM or bad rip)
        $camAudioMovie = MediaItem::create([
            'title' => 'Suspicious Line Audio Movie',
            'release_year' => 2021,
            'resolution' => '720p HD',
            'audio_channels' => 1,
            'file_path' => 'H:/Entertainment/Movies/Suspicious (2021)/film.mkv',
        ]);

        /** @var QualityUpgradeAuditService $auditService */
        $auditService = app(QualityUpgradeAuditService::class);
        $poorQuality = $auditService->getPoorQualityMedia(true);

        $flaggedIds = collect($poorQuality['items'])->pluck('local_id')->all();
        $this->assertContains($camAudioMovie->id, $flaggedIds);

        $flaggedItem = collect($poorQuality['items'])->firstWhere('local_id', $camAudioMovie->id);
        $this->assertEquals('watermarked_or_low_audio', $flaggedItem['flag_category']);
        $this->assertStringContainsString('mono', strtolower($flaggedItem['evidence']));
    }

    /**
     * Test /api/scout/upgrades returns structured JSON with low-res and poor-quality items.
     */
    public function test_api_scout_upgrades_endpoint_returns_data(): void
    {
        MediaItem::create([
            'title' => 'Low Quality Item',
            'release_year' => 2005,
            'resolution' => '360p',
            'file_path' => 'H:/Entertainment/Movies/Low Quality Item (2005)/film.mp4',
        ]);

        $response = $this->getJson('/api/scout/upgrades?refresh=1');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'upgrade_metrics' => [
                'total_low_res',
                'low_res_movies',
                'low_res_episodes',
                'total_poor_quality',
                'cam_recorded',
                'hardcoded_subs',
                'watermarked_or_low_audio',
            ],
            'low_resolution' => [
                'movies',
                'episodes',
                'total_count',
            ],
            'poor_quality' => [
                'items',
                'total_count',
                'by_category',
            ],
        ]);
    }

    /**
     * Test Scout index inertia page includes initial upgrade props.
     */
    public function test_scout_index_page_shares_initial_upgrade_props(): void
    {
        $response = $this->get('/scout');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Scout/Index')
            ->has('initialUpgradeMetrics')
            ->has('initialLowRes')
            ->has('initialPoorQuality')
        );
    }

    /**
     * Test that TorrentDiscoveryService filters out CAM and HC torrents when clean_only is enabled.
     */
    public function test_torrent_discovery_filters_cam_and_hc_when_clean_only_enabled(): void
    {
        /** @var TorrentDiscoveryService $discoveryService */
        $discoveryService = app(TorrentDiscoveryService::class);

        // 1. Without clean_only, all returned streams are present
        $allTorrents = $discoveryService->searchMovieTorrents('Movie Title', 2021, 'tt1234567', null, false, false);
        $this->assertCount(3, $allTorrents);

        // 2. With clean_only = true, CAM and HC Korean sub releases are excluded!
        $cleanTorrents = $discoveryService->searchMovieTorrents('Movie Title', 2021, 'tt1234567', null, false, true);
        $this->assertCount(1, $cleanTorrents);
        $this->assertStringContainsString('1080p.BluRay', $cleanTorrents[0]['title']);
        $this->assertStringNotContainsString('HC', $cleanTorrents[0]['title']);
        $this->assertStringNotContainsString('CAM', $cleanTorrents[0]['title']);
    }
}
