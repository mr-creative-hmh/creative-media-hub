<?php

declare(strict_types=1);

namespace App\Services\Scout;

use App\Models\DownloadItem;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Services\Media\FfmpegLocatorService;
use App\Support\ByteFormatter;
use Illuminate\Support\Facades\Cache;

class QualityUpgradeAuditService
{
    protected const CACHE_TTL = 86400; // 24 hours

    protected const LOW_RES_CACHE_KEY = 'scout_low_res_media_v2';

    protected const POOR_QUALITY_CACHE_KEY = 'scout_poor_quality_media_v2';

    protected ?string $ffprobePath = null;

    protected ?string $ffmpegPath = null;

    protected ?array $memoizedLowRes = null;

    protected ?array $memoizedPoorQuality = null;

    protected ?array $memoizedUpgradeMetrics = null;

    public function __construct()
    {
        $this->ffprobePath = FfmpegLocatorService::getFfprobePath();
        $this->ffmpegPath = FfmpegLocatorService::getFfmpegPath();
    }

    /**
     * Get all media strictly below 720p (movies and TV episodes).
     * Preserves 720p, 1080p, 1440p, 4K, 8K unless filename explicitly reveals sub-720p source.
     *
     * @return array{movies: array<int, array<string, mixed>>, episodes: array<int, array<string, mixed>>, series_groups: array<int, array<string, mixed>>, total_count: int}
     */
    public function getLowResolutionMedia(bool $forceRefresh = false): array
    {
        if (! $forceRefresh && $this->memoizedLowRes !== null) {
            return $this->memoizedLowRes;
        }

        if ($forceRefresh) {
            Cache::forget(self::LOW_RES_CACHE_KEY);
            Cache::forget('scout_low_res_media');
        }

        $result = Cache::remember(self::LOW_RES_CACHE_KEY, self::CACHE_TTL, function () {
            $lowResValues = ['480p SD', '360p', '240p', '576p SD', '540p', '540p SD', 'SD', '480p', '576p', '360p SD'];
            $lowResTokens = ['[480p]', '[576p]', '[540p]', '[360p]', '[240p]', '.480p.', '.576p.', '.360p.', '.240p.'];

            // 1. Movies below 720p (by DB column or explicit filename resolution tag)
            $movies = MediaItem::query()
                ->whereNotNull('file_path')
                ->where(function ($q) use ($lowResValues, $lowResTokens) {
                    $q->whereIn('resolution', $lowResValues)
                        ->orWhereNull('resolution')
                        ->orWhere('resolution', 'Unknown')
                        ->orWhere('resolution', '');
                    foreach ($lowResTokens as $tok) {
                        $q->orWhere('file_path', 'like', "%{$tok}%");
                    }
                })
                ->get();

            $formattedMovies = [];
            foreach ($movies as $m) {
                $effectiveRes = $this->resolveEffectiveResolution($m->resolution, $m->file_path);
                if ($this->is720pOrHigher($effectiveRes)) {
                    continue;
                }

                $sizeBytes = (int) ($m->file_size_bytes ?: ($m->file_path && file_exists($m->file_path) ? filesize($m->file_path) : 0));

                $formattedMovies[] = [
                    'id' => "movie_{$m->id}",
                    'local_id' => $m->id,
                    'type' => 'movie',
                    'title' => $m->title,
                    'title_ar' => $m->title_ar,
                    'original_title' => $m->original_title,
                    'release_year' => $m->release_year,
                    'year' => $m->release_year,
                    'tmdb_id' => $m->tmdb_id,
                    'imdb_id' => $m->imdb_id,
                    'poster_path' => $m->poster_path,
                    'backdrop_path' => $m->backdrop_path,
                    'current_resolution' => $effectiveRes,
                    'resolution' => $effectiveRes,
                    'video_codec' => $m->video_codec ?: 'Unknown',
                    'audio_codec' => $m->audio_codec ?: 'Unknown',
                    'audio_channels' => $m->audio_channels,
                    'file_path' => $m->file_path,
                    'file_size_bytes' => $sizeBytes,
                    'file_size_human' => ByteFormatter::format($sizeBytes),
                    'collection_name' => $m->collection_name,
                ];
            }

            // 2. Episodes below 720p (by DB column or explicit filename resolution tag)
            $episodes = Episode::query()
                ->with(['series', 'season'])
                ->whereNotNull('file_path')
                ->where(function ($q) use ($lowResValues, $lowResTokens) {
                    $q->whereIn('resolution', $lowResValues)
                        ->orWhereNull('resolution')
                        ->orWhere('resolution', 'Unknown')
                        ->orWhere('resolution', '');
                    foreach ($lowResTokens as $tok) {
                        $q->orWhere('file_path', 'like', "%{$tok}%");
                    }
                })
                ->get();

            $formattedEpisodes = [];
            $seriesGroupsMap = [];

            foreach ($episodes as $ep) {
                $effectiveRes = $this->resolveEffectiveResolution($ep->resolution, $ep->file_path);
                if ($this->is720pOrHigher($effectiveRes)) {
                    continue;
                }

                $series = $ep->series;
                $sNum = (int) ($ep->season?->season_number ?? 1);
                $epNum = (int) $ep->episode_number;
                $sizeBytes = (int) ($ep->file_size_bytes ?: ($ep->file_path && file_exists($ep->file_path) ? filesize($ep->file_path) : 0));

                $epEntry = [
                    'id' => "ep_{$ep->id}",
                    'local_id' => $ep->id,
                    'series_id' => $series?->id,
                    'type' => 'episode',
                    'title' => $ep->title ?: ($series?->title ? "{$series->title} - ".sprintf('S%02dE%02d', $sNum, $epNum) : 'Episode'),
                    'title_ar' => $ep->title_ar,
                    'episode_title' => $ep->title,
                    'series_title' => $series?->title ?? 'TV Series',
                    'series_title_ar' => $series?->title_ar,
                    'season_number' => $sNum,
                    'episode_number' => $epNum,
                    'episode_code' => sprintf('S%02dE%02d', $sNum, $epNum),
                    'release_year' => $series?->release_year,
                    'year' => $series?->release_year,
                    'tmdb_id' => $series?->tmdb_id,
                    'imdb_id' => $series?->imdb_id,
                    'poster_path' => $ep->still_path ?: ($series?->poster_path),
                    'series_poster_path' => $series?->poster_path,
                    'still_path' => $ep->still_path,
                    'current_resolution' => $effectiveRes,
                    'resolution' => $effectiveRes,
                    'video_codec' => $ep->video_codec ?: 'Unknown',
                    'audio_codec' => $ep->audio_codec ?: 'Unknown',
                    'audio_channels' => $ep->audio_channels,
                    'file_path' => $ep->file_path,
                    'file_size_bytes' => $sizeBytes,
                    'file_size_human' => ByteFormatter::format($sizeBytes),
                ];

                $formattedEpisodes[] = $epEntry;

                $sKey = $series?->id ? "series_{$series->id}" : 'unknown_series';
                if (! isset($seriesGroupsMap[$sKey])) {
                    $seriesGroupsMap[$sKey] = [
                        'id' => $sKey,
                        'series_id' => $series?->id,
                        'series_title' => $series?->title ?? 'TV Series',
                        'series_title_ar' => $series?->title_ar,
                        'poster_path' => $series?->poster_path ?: $ep->still_path,
                        'release_year' => $series?->release_year,
                        'tmdb_id' => $series?->tmdb_id,
                        'imdb_id' => $series?->imdb_id,
                        'episodes_count' => 0,
                        'resolutions' => [],
                        'total_size_bytes' => 0,
                        'episodes' => [],
                    ];
                }

                $seriesGroupsMap[$sKey]['episodes_count']++;
                $seriesGroupsMap[$sKey]['total_size_bytes'] += $sizeBytes;
                if (! in_array($effectiveRes, $seriesGroupsMap[$sKey]['resolutions'], true)) {
                    $seriesGroupsMap[$sKey]['resolutions'][] = $effectiveRes;
                }
                $seriesGroupsMap[$sKey]['episodes'][] = $epEntry;
            }

            $seriesGroups = array_values(array_map(function (array $group) {
                $group['total_size_human'] = ByteFormatter::format($group['total_size_bytes']);

                return $group;
            }, $seriesGroupsMap));

            usort($seriesGroups, fn (array $a, array $b) => $b['episodes_count'] <=> $a['episodes_count']);

            return [
                'movies' => $formattedMovies,
                'episodes' => $formattedEpisodes,
                'series_groups' => $seriesGroups,
                'total_count' => count($formattedMovies) + count($formattedEpisodes),
            ];
        });

        $this->memoizedLowRes = $result;

        return $result;
    }

    /**
     * Get all detected CAM, Telesync, Hardcoded Subtitle (HC/Korean/Chinese), and Bitrate/Audio anomalous items.
     * Audits 100% of movies and TV episodes, including already-organized and renamed files.
     *
     * @return array{items: array<int, array<string, mixed>>, total_count: int, by_category: array<string, int>}
     */
    public function getPoorQualityMedia(bool $forceRefresh = false): array
    {
        if (! $forceRefresh && $this->memoizedPoorQuality !== null) {
            return $this->memoizedPoorQuality;
        }

        if ($forceRefresh) {
            Cache::forget(self::POOR_QUALITY_CACHE_KEY);
            Cache::forget('scout_poor_quality_media');
        }

        $result = Cache::remember(self::POOR_QUALITY_CACHE_KEY, self::CACHE_TTL, function () {
            $flaggedItems = [];
            $categoriesCount = [
                'cam_recorded' => 0,
                'hardcoded_subs' => 0,
                'watermarked_or_low_audio' => 0,
                'watermarked_rip' => 0,
            ];

            // Preload all DownloadItem records to eliminate N+1 SQL queries across 5,700+ items
            $downloadByPath = [];
            $downloadByIndexId = [];
            foreach (DownloadItem::query()->select(['id', 'title', 'organized_path', 'indexed_id'])->get() as $dl) {
                if (! empty($dl->organized_path)) {
                    $downloadByPath[strtolower(str_replace('\\', '/', $dl->organized_path))] = $dl;
                }
                if (! empty($dl->indexed_id)) {
                    $downloadByIndexId[(string) $dl->indexed_id] = $dl;
                }
            }

            // 1. Audit all MediaItems (Movies)
            $movies = MediaItem::query()
                ->whereNotNull('file_path')
                ->get();

            foreach ($movies as $m) {
                $normPath = strtolower(str_replace('\\', '/', (string) $m->file_path));
                $matchedDownload = $downloadByPath[$normPath] ?? ($downloadByIndexId[(string) $m->id] ?? null);

                $inspection = $this->inspectFileForQualityIssues((string) $m->file_path, 'movie', [
                    'id' => $m->id,
                    'title' => $m->title,
                    'original_title' => $m->original_title,
                    'release_year' => $m->release_year,
                    'audio_channels' => $m->audio_channels,
                    'audio_codec' => $m->audio_codec,
                    'audio_bitrate' => $m->audio_bitrate,
                    'video_codec' => $m->video_codec,
                    'video_profile' => $m->video_profile,
                    'framerate' => $m->framerate,
                    'original_language' => $m->original_language,
                    'origin_country' => $m->origin_country,
                    'resolution' => $m->resolution,
                    'video_bitrate' => $m->video_bitrate,
                    'total_bitrate' => $m->total_bitrate,
                    'file_size_bytes' => $m->file_size_bytes,
                    'duration_seconds' => $m->duration_seconds,
                    'runtime_minutes' => $m->runtime_minutes,
                    'download_item' => $matchedDownload,
                    'inspect_binary' => true,
                ]);

                if ($inspection !== null) {
                    $category = $inspection['category'];
                    $categoriesCount[$category] = ($categoriesCount[$category] ?? 0) + 1;

                    $sizeBytes = (int) ($m->file_size_bytes ?: ($m->file_path && file_exists($m->file_path) ? filesize($m->file_path) : 0));
                    $effectiveRes = $this->resolveEffectiveResolution($m->resolution, $m->file_path);

                    $flaggedItems[] = [
                        'id' => "poor_movie_{$m->id}",
                        'local_id' => $m->id,
                        'type' => 'movie',
                        'title' => $m->title,
                        'title_ar' => $m->title_ar,
                        'original_title' => $m->original_title,
                        'release_year' => $m->release_year,
                        'year' => $m->release_year,
                        'tmdb_id' => $m->tmdb_id,
                        'imdb_id' => $m->imdb_id,
                        'poster_path' => $m->poster_path,
                        'backdrop_path' => $m->backdrop_path,
                        'current_resolution' => $effectiveRes,
                        'resolution' => $effectiveRes,
                        'video_codec' => $m->video_codec ?: 'Unknown',
                        'audio_codec' => $m->audio_codec ?: 'Unknown',
                        'file_path' => $m->file_path,
                        'file_size_bytes' => $sizeBytes,
                        'file_size_human' => ByteFormatter::format($sizeBytes),
                        'flag_category' => $category,
                        'badge_en' => $inspection['badge_en'],
                        'badge_ar' => $inspection['badge_ar'],
                        'severity' => $inspection['severity'],
                        'evidence' => is_array($inspection['evidence']) ? implode('; ', $inspection['evidence']) : (string) $inspection['evidence'],
                    ];
                }
            }

            // 2. Audit ALL TV Series Episodes (no restrictive SQL pre-filter that misses renamed files)
            $episodes = Episode::query()
                ->with(['series', 'season'])
                ->whereNotNull('file_path')
                ->get();

            foreach ($episodes as $ep) {
                $series = $ep->series;
                $normPath = strtolower(str_replace('\\', '/', (string) $ep->file_path));
                $matchedDownload = $downloadByPath[$normPath] ?? null;

                $seriesYear = (int) ($series?->release_year ?? 0);
                $epAudioCh = (int) ($ep->audio_channels ?? 2);
                $shouldInspectBinary = $seriesYear >= 2024
                    || $epAudioCh === 1
                    || $matchedDownload !== null
                    || Cache::has('cmh_bin_hdr_v3_'.md5((string) $ep->file_path));

                $inspection = $this->inspectFileForQualityIssues((string) $ep->file_path, 'episode', [
                    'id' => $ep->id,
                    'title' => $ep->title ?: ($series?->title ?? ''),
                    'series_title' => $series?->title ?? '',
                    'release_year' => $series?->release_year,
                    'audio_channels' => $ep->audio_channels,
                    'audio_codec' => $ep->audio_codec,
                    'video_codec' => $ep->video_codec,
                    'framerate' => $ep->framerate,
                    'original_language' => $series?->original_language,
                    'origin_country' => $series?->origin_country,
                    'resolution' => $ep->resolution,
                    'video_bitrate' => $ep->video_bitrate,
                    'total_bitrate' => $ep->total_bitrate,
                    'file_size_bytes' => $ep->file_size_bytes,
                    'duration_seconds' => $ep->duration_seconds,
                    'download_item' => $matchedDownload,
                    'inspect_binary' => $shouldInspectBinary,
                ]);

                if ($inspection !== null) {
                    $category = $inspection['category'];
                    $categoriesCount[$category] = ($categoriesCount[$category] ?? 0) + 1;

                    $sNum = (int) ($ep->season?->season_number ?? 1);
                    $epNum = (int) $ep->episode_number;
                    $sizeBytes = (int) ($ep->file_size_bytes ?: ($ep->file_path && file_exists($ep->file_path) ? filesize($ep->file_path) : 0));
                    $effectiveRes = $this->resolveEffectiveResolution($ep->resolution, $ep->file_path);

                    $flaggedItems[] = [
                        'id' => "poor_ep_{$ep->id}",
                        'local_id' => $ep->id,
                        'series_id' => $series?->id,
                        'type' => 'episode',
                        'title' => $ep->title ?: ($series?->title ? "{$series->title} - ".sprintf('S%02dE%02d', $sNum, $epNum) : 'Episode'),
                        'title_ar' => $ep->title_ar,
                        'episode_title' => $ep->title,
                        'series_title' => $series?->title ?? 'TV Series',
                        'series_title_ar' => $series?->title_ar,
                        'season_number' => $sNum,
                        'episode_number' => $epNum,
                        'episode_code' => sprintf('S%02dE%02d', $sNum, $epNum),
                        'release_year' => $series?->release_year,
                        'year' => $series?->release_year,
                        'tmdb_id' => $series?->tmdb_id,
                        'imdb_id' => $series?->imdb_id,
                        'poster_path' => $ep->still_path ?: ($series?->poster_path),
                        'still_path' => $ep->still_path,
                        'current_resolution' => $effectiveRes,
                        'resolution' => $effectiveRes,
                        'video_codec' => $ep->video_codec ?: 'Unknown',
                        'audio_codec' => $ep->audio_codec ?: 'Unknown',
                        'file_path' => $ep->file_path,
                        'file_size_bytes' => $sizeBytes,
                        'file_size_human' => ByteFormatter::format($sizeBytes),
                        'flag_category' => $category,
                        'badge_en' => $inspection['badge_en'],
                        'badge_ar' => $inspection['badge_ar'],
                        'severity' => $inspection['severity'],
                        'evidence' => is_array($inspection['evidence']) ? implode('; ', $inspection['evidence']) : (string) $inspection['evidence'],
                    ];
                }
            }

            return [
                'items' => $flaggedItems,
                'total_count' => count($flaggedItems),
                'by_category' => $categoriesCount,
            ];
        });

        $this->memoizedPoorQuality = $result;

        return $result;
    }

    /**
     * Inspect a single video file on disk for CAM, Hardcoded Subtitles, and Watermarked/Bitrate-Starved Rips.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    public function inspectFileForQualityIssues(string $filePath, string $type = 'movie', array $meta = []): ?array
    {
        $title = (string) ($meta['title'] ?? '');
        $seriesTitle = (string) ($meta['series_title'] ?? '');

        if ($this->isFalsePositivePath($filePath, $title, $seriesTitle)) {
            return null;
        }

        $evidence = [];
        $category = null;
        $badgeEn = '';
        $badgeAr = '';
        $severity = 'warning';

        $year = (int) ($meta['release_year'] ?? 0);
        $audioChannels = (int) ($meta['audio_channels'] ?? 2);
        $audioCodec = strtolower((string) ($meta['audio_codec'] ?? ''));
        $framerate = $meta['framerate'] ?? null;
        $origLang = strtolower((string) ($meta['original_language'] ?? ''));
        $effectiveRes = $this->resolveEffectiveResolution($meta['resolution'] ?? null, $filePath);

        $camRegex = '/\b(camrip|cam-rip|hdcam|hd-cam|hqcam|hq-cam|newcam|telesync|hdts|hd-ts|pdvd|predvd|telecine|hdtc|hd-tc|dvdscr|dvd-scr|screener|bdscr|1xbet|melbet|linebet|mostbet|c1nem4|will187|slotlights|cam\.audio|line\.audio|mic\.dubbed)\b/i';
        $hcRegex = '/\b(korsub|kor-sub|chsub|zh-sub|chinesesub|hardcoded|hc-rip|hcrip|hdrip\.hc|web-?dl\.hc|webrip\.hc|egybest|cima4u|mycima|wecima|faselhd|arabseed|akoam|movs4u|shahid4u)\b|(?:^|[\.\-\_\[\(\s])hc(?:[\.\-\_\]\)\s]|$)/i';

        // 1. Acoustic Forensic Check: Mono Audio on modern theatrical movies (post-1990)
        if ($audioChannels === 1 && $year >= 1990 && $type === 'movie') {
            $evidence[] = "Mono audio (1 channel) on {$year} movie (indicates microphone cinema recording or low-audio rip)";
            $category = 'watermarked_or_low_audio';
            $badgeEn = 'Mono Audio / Possible CAM';
            $badgeAr = 'صوت مونو أحادي / جودة منخفضة';
            $severity = 'high';
        }

        // Legacy MP3 audio track inside a recent HD movie container
        if ($type === 'movie' && $year >= 2015 && str_contains($audioCodec, 'mp3') && in_array($effectiveRes, ['1080p FHD', '720p HD'], true)) {
            $evidence[] = "Legacy MP3 audio track in {$year} HD release (indicates line-dubbed or heavily re-encoded rip)";
            if (! $category) {
                $category = 'watermarked_or_low_audio';
                $badgeEn = 'Line-Dubbed / Low Audio';
                $badgeAr = 'صوت مسجل / إعادة ترميز رديئة';
                $severity = 'high';
            }
        }

        // 2. Stripped File Path Pattern Check (remove clean media title so titles like "Cam" or "Line" don't false-trigger)
        $pathToCheck = $this->stripCleanTitleFromPath($filePath, $title, $seriesTitle);
        if (preg_match($camRegex, $pathToCheck, $pm)) {
            $evidence[] = "File path contains release tag: '{$pm[0]}'";
            if (! $category) {
                $category = 'cam_recorded';
                $badgeEn = 'CAM / Telesync';
                $badgeAr = 'تصوير سينما / Telesync';
                $severity = 'high';
            }
        } elseif (preg_match($hcRegex, $pathToCheck, $pm)) {
            $matchedTok = trim($pm[0], ". \t\n\r\0\x0B-_[]()");
            $evidence[] = "File path contains hardcoded/watermark tag: '{$matchedTok}'";
            if (! $category) {
                $category = 'hardcoded_subs';
                $badgeEn = 'Hardcoded Subtitles (HC)';
                $badgeAr = 'ترجمة مدمجة في الصورة (HC)';
                $severity = 'high';
            }
        }

        // 3. Framerate Cadence Anomaly Check
        if ($framerate) {
            $fps = (float) $framerate;
            if (($fps > 0 && $fps < 23.0) || ($fps > 25.2 && $fps < 29.5) || ($fps > 30.5 && $fps < 47.5)) {
                $evidence[] = "Abnormal framerate ({$framerate} fps, irregular camera/screen capture cadence)";
                if (! $category) {
                    $category = 'cam_recorded';
                    $badgeEn = 'Abnormal Cadence (CAM/Screen)';
                    $badgeAr = 'معدل إطارات غير قياسي (تصوير)';
                    $severity = 'high';
                }
            }
        }

        // 4. Bitrate Starvation Check on feature-length movies (re-encoded bootlegs / upscaled low-res CAMs)
        $durationSec = (int) ($meta['duration_seconds'] ?? 0);
        if ($durationSec <= 0 && ! empty($meta['runtime_minutes'])) {
            $durationSec = (int) $meta['runtime_minutes'] * 60;
        }
        $videoBitrate = (int) ($meta['video_bitrate'] ?? 0);
        $totalBitrate = (int) ($meta['total_bitrate'] ?? 0);
        $sizeBytes = (int) ($meta['file_size_bytes'] ?? 0);

        $kbps = 0;
        if ($videoBitrate > 0) {
            $kbps = (int) round($videoBitrate / 1000);
        } elseif ($totalBitrate > 0) {
            $kbps = (int) round($totalBitrate / 1000);
        } elseif ($sizeBytes > 0 && $durationSec >= 1800) {
            $kbps = (int) round(($sizeBytes * 8) / 1000 / $durationSec);
        }

        if ($type === 'movie' && $durationSec >= 4500 && $kbps > 0) {
            if ($effectiveRes === '1080p FHD' && $kbps < 950) {
                $evidence[] = "Severe bitrate starvation: Claimed 1080p at only {$kbps} kbps";
                if (! $category) {
                    $category = 'watermarked_or_low_audio';
                    $badgeEn = 'Bitrate-Starved 1080p';
                    $badgeAr = 'جودة مضغوطة بشدة (1080p وهمي)';
                    $severity = 'warning';
                }
            } elseif ($effectiveRes === '720p HD' && $kbps < 550) {
                $evidence[] = "Severe bitrate starvation: Claimed 720p at only {$kbps} kbps";
                if (! $category) {
                    $category = 'watermarked_or_low_audio';
                    $badgeEn = 'Bitrate-Starved 720p';
                    $badgeAr = 'جودة مضغوطة بشدة (720p وهمي)';
                    $severity = 'warning';
                }
            }
        }

        // 5. Historical Download Records in download_items (preloaded or queried on demand)
        $downloadItem = $meta['download_item'] ?? null;
        if ($downloadItem === null && ! array_key_exists('download_item', $meta)) {
            $downloadItem = DownloadItem::where('organized_path', $filePath)
                ->orWhere(function ($q) use ($meta) {
                    if (! empty($meta['id'])) {
                        $q->where('indexed_id', $meta['id']);
                    }
                })
                ->first();
        }

        if ($downloadItem && ! empty($downloadItem->title)) {
            $rawTitle = (string) $downloadItem->title;
            $rawClean = $this->stripCleanTitleFromPath($rawTitle, $title, $seriesTitle);
            if (preg_match('/\b(cam|camrip|hdcam|ts|telesync|hdts|tc|telecine|dvdscr|scr|screener|1xbet)\b/i', $rawClean, $m)) {
                $evidence[] = "Original download release was '{$m[0]}': \"{$rawTitle}\"";
                $category = 'cam_recorded';
                $badgeEn = 'CAM / Recorded Release';
                $badgeAr = 'إصدار سينمائي مسجل';
                $severity = 'high';
            }
            if (preg_match('/\b(hc|korsub|chsub|hardcoded|subbed|cht)\b/i', $rawClean, $m)) {
                $evidence[] = "Original download release had hardcoded subs '{$m[0]}': \"{$rawTitle}\"";
                if (! $category) {
                    $category = 'hardcoded_subs';
                    $badgeEn = 'Hardcoded Subtitles (HC)';
                    $badgeAr = 'ترجمة مدمجة في الصورة (HC)';
                    $severity = 'high';
                }
            }
        }

        // 6. Pure-PHP Container Header & Trailer Metadata Inspection (First 128KB + Last 128KB)
        $shouldInspectBinary = $meta['inspect_binary'] ?? true;
        if ($shouldInspectBinary && ! empty($filePath) && file_exists($filePath)) {
            $printableMeta = $this->getFastHeaderChunk($filePath);
            if ($printableMeta !== '') {
                $cleanMeta = $this->stripCleanTitleFromPath($printableMeta, $title, $seriesTitle);

                // Strictly match long release tags case-insensitively, and short 4-char tags only in exact UPPERCASE/lowercase
                // to prevent random mixed-case compressed bitstream entropy ('PdvD', 'hC', 'Hc') from false-triggering.
                $camBinaryLongRegex = '/\b(camrip|cam-rip|hdcam|hd-cam|hqcam|hq-cam|newcam|telesync|telecine|dvdscr|dvd-scr|screener|bdscr|predvd|1xbet|melbet|linebet|mostbet|c1nem4|will187|slotlights)\b/i';
                $camBinaryExactRegex = '/\b(HDTS|hdts|HD-TS|hd-ts|PDVD|pdvd|HDTC|hdtc|HD-TC|hd-tc)\b/';
                $hcBinaryLongRegex = '/\b(korsub|kor-sub|chsub|zh-sub|chinesesub|hardcoded|hc-rip|hcrip|hdrip\.hc|web-?dl\.hc|webrip\.hc|egybest|cima4u|mycima|wecima|faselhd|arabseed|akoam|movs4u|shahid4u)\b/i';
                $hcBinaryExactRegex = '/(?:[\.\-\_\[])(?:HC|hc)(?:[\.\-\_\]]|\b)/';

                if (preg_match($camBinaryLongRegex, $cleanMeta, $hm) || preg_match($camBinaryExactRegex, $cleanMeta, $hm)) {
                    $evidence[] = "Internal container metadata matches CAM/Telesync signature: '{$hm[0]}'";
                    $category = 'cam_recorded';
                    $badgeEn = 'CAM / Telesync';
                    $badgeAr = 'تصوير سينما / Telesync';
                    $severity = 'high';
                } elseif (preg_match($hcBinaryLongRegex, $cleanMeta, $hm) || preg_match($hcBinaryExactRegex, $cleanMeta, $hm)) {
                    $matchedTok = trim($hm[0], ". \t\n\r\0\x0B-_[]()");
                    $evidence[] = "Internal container metadata matches Hardcoded Subtitles/Watermark: '{$matchedTok}'";
                    if (! $category) {
                        $category = 'hardcoded_subs';
                        $badgeEn = 'Hardcoded Subtitles (HC)';
                        $badgeAr = 'ترجمة مدمجة في الصورة (HC)';
                        $severity = 'high';
                    }
                } elseif (preg_match('/[\x{4e00}-\x{9fa5}\x{ac00}-\x{d7af}]{3,}/u', $cleanMeta)) {
                    $isNativeAsian = in_array($origLang, ['zh', 'chi', 'zho', 'ja', 'jpn', 'ko', 'kor'], true);
                    if (! $isNativeAsian) {
                        $evidence[] = 'Internal container contains Asian watermark/subtitle metadata tags';
                        if (! $category) {
                            $category = 'hardcoded_subs';
                            $badgeEn = 'Hardcoded Foreign Subs / Watermark';
                            $badgeAr = 'ترجمة آسيوية مدمجة / علامة مائية';
                            $severity = 'high';
                        }
                    }
                }
            }
        }

        if (empty($evidence) || ! $category) {
            return null;
        }

        return [
            'category' => $category,
            'badge_en' => $badgeEn,
            'badge_ar' => $badgeAr,
            'severity' => $severity,
            'evidence' => $evidence,
        ];
    }

    /**
     * Resolve effective resolution from explicit filename tags or DB column.
     */
    public function resolveEffectiveResolution(?string $dbResolution, ?string $filePath): string
    {
        if (! empty($filePath) && preg_match('/[\[\.\(\s\_](2160p|1440p|1080p|720p|576p|540p|480p|360p|240p)[\]\.\)\s\_]/i', $filePath, $m)) {
            return match (strtolower($m[1])) {
                '2160p' => '4K UHD',
                '1440p' => '1440p 2K',
                '1080p' => '1080p FHD',
                '720p' => '720p HD',
                '576p' => '576p SD',
                '540p' => '540p SD',
                '480p' => '480p SD',
                '360p' => '360p',
                '240p' => '240p',
                default => $dbResolution ?: 'SD',
            };
        }

        return $dbResolution ?: 'SD';
    }

    /**
     * Detect known false-positive episode/movie naming schemes (e.g. Mr. Robot cryptographic .tc / .hc titles).
     */
    protected function isFalsePositivePath(string $filePath, string $title = '', string $seriesTitle = ''): bool
    {
        $combined = strtolower($filePath.' '.$title.' '.$seriesTitle);

        // Mr. Robot episode filenames use cryptographic extensions (.tc, .hc, .aes, .dat)
        if (str_contains($combined, 'mr robot') || str_contains($combined, 'mr. robot')) {
            if (preg_match('/eps\d+\.\d+.*\.(tc|hc)\b/i', $combined)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove legitimate media/series titles from a string before matching release tokens.
     */
    protected function stripCleanTitleFromPath(string $text, string $title = '', string $seriesTitle = ''): string
    {
        $result = $text;
        foreach ([$title, $seriesTitle] as $candidate) {
            $clean = trim($candidate);
            if ($clean !== '' && strlen($clean) >= 3) {
                $result = str_ireplace($clean, ' ', $result);
            }
        }

        return $result;
    }

    /**
     * Extract printable ASCII strings (length >= 6) and multi-character CJK/Hangul sequences
     * from the first 128KB and last 128KB of a media container.
     * Avoids matching random compressed video bitstream noise.
     */
    protected function getFastHeaderChunk(string $filePath): string
    {
        $cacheKey = 'cmh_bin_hdr_v3_'.md5($filePath);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filePath) {
            $h = @fopen($filePath, 'rb');
            if (! $h) {
                return '';
            }

            $raw = fread($h, 131072) ?: '';
            $size = @filesize($filePath);
            if ($size !== false && $size > 262144) {
                if (@fseek($h, $size - 131072) === 0) {
                    $raw .= "\n".(fread($h, 131072) ?: '');
                }
            }
            fclose($h);

            if ($raw === '') {
                return '';
            }

            $tokens = [];
            if (preg_match_all('/[\x20-\x7E]{6,}/', $raw, $ascii)) {
                $tokens = array_merge($tokens, $ascii[0]);
            }
            if (preg_match_all('/[\x{4e00}-\x{9fa5}\x{ac00}-\x{d7af}]{3,}/u', $raw, $cjk)) {
                $tokens = array_merge($tokens, $cjk[0]);
            }

            return implode(' ', $tokens);
        });
    }

    /**
     * Get aggregate metrics for Media Scout hero counters.
     *
     * @return array<string, int>
     */
    public function getUpgradeMetrics(bool $forceRefresh = false): array
    {
        if (! $forceRefresh && $this->memoizedUpgradeMetrics !== null) {
            return $this->memoizedUpgradeMetrics;
        }

        $lowRes = $this->getLowResolutionMedia($forceRefresh);
        $poor = $this->getPoorQualityMedia($forceRefresh);

        $camCount = $poor['by_category']['cam_recorded'] ?? 0;
        $hcCount = $poor['by_category']['hardcoded_subs'] ?? 0;
        $lowAudioCount = $poor['by_category']['watermarked_or_low_audio'] ?? 0;
        $wmCount = $poor['by_category']['watermarked_rip'] ?? 0;

        $metrics = [
            'total_low_res' => $lowRes['total_count'],
            'low_res_movies' => count($lowRes['movies']),
            'low_res_episodes' => count($lowRes['episodes']),
            'total_poor_quality' => $poor['total_count'],
            'cam_recorded' => $camCount,
            'hardcoded_subs' => $hcCount,
            'watermarked_or_low_audio' => $lowAudioCount + $wmCount,
            'audited_movies_count' => count($lowRes['movies']),
            'audited_episodes_count' => count($lowRes['episodes']),

            'sub720_count' => $lowRes['total_count'],
            'sub720_movies_count' => count($lowRes['movies']),
            'sub720_episodes_count' => count($lowRes['episodes']),
            'poor_quality_count' => $poor['total_count'],
            'cam_count' => $camCount,
            'hardcoded_subs_count' => $hcCount,
            'watermarked_count' => $wmCount,
        ];

        $this->memoizedUpgradeMetrics = $metrics;

        return $metrics;
    }

    /**
     * Clear all upgrade caches.
     */
    public function clearCache(): void
    {
        $this->memoizedLowRes = null;
        $this->memoizedPoorQuality = null;
        $this->memoizedUpgradeMetrics = null;

        Cache::forget(self::LOW_RES_CACHE_KEY);
        Cache::forget(self::POOR_QUALITY_CACHE_KEY);
        Cache::forget('scout_low_res_media');
        Cache::forget('scout_poor_quality_media');
    }

    /**
     * Determine if resolution string is 720p, 1080p, 1440p, 4K, or 8K.
     */
    protected function is720pOrHigher(?string $resolution): bool
    {
        if (empty($resolution)) {
            return false;
        }

        $res = strtolower(trim($resolution));

        return str_contains($res, '720p')
            || str_contains($res, '1080p')
            || str_contains($res, '1440p')
            || str_contains($res, '2k')
            || str_contains($res, '4k')
            || str_contains($res, '2160p')
            || str_contains($res, '8k');
    }
}
