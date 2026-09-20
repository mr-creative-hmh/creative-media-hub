<?php

declare(strict_types=1);

namespace App\Services\Scout;

use App\Models\DownloadItem;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Services\Media\FfmpegLocatorService;
use Illuminate\Support\Facades\Cache;

class QualityUpgradeAuditService
{
    protected const CACHE_TTL = 3600; // 1 hour

    protected ?string $ffprobePath = null;

    protected ?string $ffmpegPath = null;

    public function __construct()
    {
        $this->ffprobePath = FfmpegLocatorService::getFfprobePath();
        $this->ffmpegPath = FfmpegLocatorService::getFfmpegPath();
    }

    /**
     * Get all media strictly below 720p (movies and TV episodes).
     * Preserves 720p, 1080p, 1440p, 4K, 8K.
     *
     * @return array{movies: array<int, array<string, mixed>>, episodes: array<int, array<string, mixed>>, total_count: int}
     */
    public function getLowResolutionMedia(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget('scout_low_res_media');
        }

        return Cache::remember('scout_low_res_media', self::CACHE_TTL, function () {
            // 1. Movies below 720p
            $movies = MediaItem::query()
                ->whereNotNull('file_path')
                ->where(function ($q) {
                    $q->whereIn('resolution', ['480p SD', '360p', '240p', '576p SD', 'SD', '480p', '576p', '360p SD'])
                        ->orWhereNull('resolution')
                        ->orWhere('resolution', 'Unknown')
                        ->orWhere('resolution', '');
                })
                ->get();

            $formattedMovies = [];
            foreach ($movies as $m) {
                // Double-check: ensure it is not 720p or 1080p or 4K
                if ($this->is720pOrHigher($m->resolution)) {
                    continue;
                }

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
                    'current_resolution' => $m->resolution ?: 'SD',
                    'resolution' => $m->resolution ?: 'SD',
                    'video_codec' => $m->video_codec ?: 'Unknown',
                    'audio_codec' => $m->audio_codec ?: 'Unknown',
                    'audio_channels' => $m->audio_channels,
                    'file_path' => $m->file_path,
                    'file_size_bytes' => $m->file_size_bytes ?: ($m->file_path && file_exists($m->file_path) ? filesize($m->file_path) : 0),
                    'file_size_human' => $this->formatBytes($m->file_size_bytes ?: ($m->file_path && file_exists($m->file_path) ? filesize($m->file_path) : 0)),
                    'collection_name' => $m->collection_name,
                ];
            }

            // 2. Episodes below 720p
            $episodes = Episode::query()
                ->with(['series', 'season'])
                ->whereNotNull('file_path')
                ->where(function ($q) {
                    $q->whereIn('resolution', ['480p SD', '360p', '240p', '576p SD', 'SD', '480p', '576p', '360p SD'])
                        ->orWhereNull('resolution')
                        ->orWhere('resolution', 'Unknown')
                        ->orWhere('resolution', '');
                })
                ->get();

            $formattedEpisodes = [];
            foreach ($episodes as $ep) {
                if ($this->is720pOrHigher($ep->resolution)) {
                    continue;
                }

                $series = $ep->series;
                $sNum = (int) ($ep->season?->season_number ?? 1);
                $epNum = (int) $ep->episode_number;

                $formattedEpisodes[] = [
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
                    'still_path' => $ep->still_path,
                    'current_resolution' => $ep->resolution ?: 'SD',
                    'resolution' => $ep->resolution ?: 'SD',
                    'video_codec' => $ep->video_codec ?: 'Unknown',
                    'audio_codec' => $ep->audio_codec ?: 'Unknown',
                    'audio_channels' => $ep->audio_channels,
                    'file_path' => $ep->file_path,
                    'file_size_bytes' => $ep->file_size_bytes ?: ($ep->file_path && file_exists($ep->file_path) ? filesize($ep->file_path) : 0),
                    'file_size_human' => $this->formatBytes($ep->file_size_bytes ?: ($ep->file_path && file_exists($ep->file_path) ? filesize($ep->file_path) : 0)),
                ];
            }

            return [
                'movies' => $formattedMovies,
                'episodes' => $formattedEpisodes,
                'total_count' => count($formattedMovies) + count($formattedEpisodes),
            ];
        });
    }

    /**
     * Get all detected CAM, Telesync, Hardcoded Subtitle (HC/Korean/Chinese), and Watermarked items.
     * Works accurately on already-organized and renamed files.
     *
     * @return array{items: array<int, array<string, mixed>>, total_count: int, by_category: array<string, int>}
     */
    public function getPoorQualityMedia(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget('scout_poor_quality_media');
        }

        return Cache::remember('scout_poor_quality_media', self::CACHE_TTL, function () {
            $flaggedItems = [];
            $categoriesCount = [
                'cam_recorded' => 0,
                'hardcoded_subs' => 0,
                'watermarked_or_low_audio' => 0,
                'watermarked_rip' => 0,
            ];

            // 1. Audit all MediaItems (Movies)
            $movies = MediaItem::query()
                ->whereNotNull('file_path')
                ->get();

            foreach ($movies as $m) {
                $inspection = $this->inspectFileForQualityIssues($m->file_path, 'movie', [
                    'id' => $m->id,
                    'title' => $m->title,
                    'release_year' => $m->release_year,
                    'audio_channels' => $m->audio_channels,
                    'framerate' => $m->framerate,
                    'original_language' => $m->original_language,
                    'origin_country' => $m->origin_country,
                    'resolution' => $m->resolution,
                    'video_bitrate' => $m->video_bitrate,
                    'total_bitrate' => $m->total_bitrate,
                ]);

                if ($inspection !== null) {
                    $category = $inspection['category'];
                    if (isset($categoriesCount[$category])) {
                        $categoriesCount[$category]++;
                    } else {
                        $categoriesCount[$category] = 1;
                    }

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
                        'current_resolution' => $m->resolution ?: 'Unknown',
                        'resolution' => $m->resolution ?: 'Unknown',
                        'video_codec' => $m->video_codec ?: 'Unknown',
                        'audio_codec' => $m->audio_codec ?: 'Unknown',
                        'file_path' => $m->file_path,
                        'file_size_bytes' => $m->file_size_bytes ?: ($m->file_path && file_exists($m->file_path) ? filesize($m->file_path) : 0),
                        'file_size_human' => $this->formatBytes($m->file_size_bytes ?: ($m->file_path && file_exists($m->file_path) ? filesize($m->file_path) : 0)),
                        'flag_category' => $category,
                        'badge_en' => $inspection['badge_en'],
                        'badge_ar' => $inspection['badge_ar'],
                        'severity' => $inspection['severity'],
                        'evidence' => is_array($inspection['evidence']) ? implode('; ', $inspection['evidence']) : (string) $inspection['evidence'],
                    ];
                }
            }

            // 2. Audit TV Series Episodes with suspicious traits
            $episodes = Episode::query()
                ->with('series')
                ->whereNotNull('file_path')
                ->where(function ($q) {
                    $q->where('audio_channels', 1)
                        ->orWhere('file_path', 'like', '%.hc.%')
                        ->orWhere('file_path', 'like', '%cam%')
                        ->orWhere('file_path', 'like', '%telesync%')
                        ->orWhere('file_path', 'like', '%korsub%');
                })
                ->get();

            foreach ($episodes as $ep) {
                $series = $ep->series;
                $inspection = $this->inspectFileForQualityIssues($ep->file_path, 'episode', [
                    'id' => $ep->id,
                    'title' => $series?->title ?? $ep->title,
                    'release_year' => $series?->release_year,
                    'audio_channels' => $ep->audio_channels,
                    'framerate' => $ep->framerate,
                    'original_language' => $series?->original_language,
                    'origin_country' => $series?->origin_country,
                    'resolution' => $ep->resolution,
                    'video_bitrate' => $ep->video_bitrate,
                    'total_bitrate' => $ep->total_bitrate,
                ]);

                if ($inspection !== null) {
                    $category = $inspection['category'];
                    if (isset($categoriesCount[$category])) {
                        $categoriesCount[$category]++;
                    } else {
                        $categoriesCount[$category] = 1;
                    }

                    $sNum = (int) ($ep->season?->season_number ?? 1);
                    $epNum = (int) $ep->episode_number;

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
                        'current_resolution' => $ep->resolution ?: 'Unknown',
                        'resolution' => $ep->resolution ?: 'Unknown',
                        'video_codec' => $ep->video_codec ?: 'Unknown',
                        'audio_codec' => $ep->audio_codec ?: 'Unknown',
                        'file_path' => $ep->file_path,
                        'file_size_bytes' => $ep->file_size_bytes ?: ($ep->file_path && file_exists($ep->file_path) ? filesize($ep->file_path) : 0),
                        'file_size_human' => $this->formatBytes($ep->file_size_bytes ?: ($ep->file_path && file_exists($ep->file_path) ? filesize($ep->file_path) : 0)),
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
    }

    /**
     * Inspect a single video file on disk for CAM, Hardcoded Subtitles, and Watermarked Rips.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    public function inspectFileForQualityIssues(string $filePath, string $type = 'movie', array $meta = []): ?array
    {
        $evidence = [];
        $category = null;
        $badgeEn = '';
        $badgeAr = '';
        $severity = 'warning';

        $year = (int) ($meta['release_year'] ?? 0);
        $audioChannels = (int) ($meta['audio_channels'] ?? 2);
        $framerate = $meta['framerate'] ?? null;
        $origLang = strtolower((string) ($meta['original_language'] ?? ''));

        // 1. Audio Forensic Check: Mono Audio on modern films (post-1990)
        // Cinema movies never have mono audio; cameras recording in halls capture mono microphone audio.
        if ($audioChannels === 1 && $year >= 1990 && $type === 'movie') {
            $evidence[] = "Mono audio (1 channel) on {$year} movie (indicates microphone cinema recording)";
            $category = 'watermarked_or_low_audio';
            $badgeEn = 'CAM / Recorded Audio';
            $badgeAr = 'تسجيل سينمائي / صوت كاميرا';
            $severity = 'high';
        }

        // 2. File path pattern check
        if (preg_match('/\b(camrip|hdcam|cam-rip|telesync|hdts|\bhd-ts\b|korsub|\.hc\b|hardcoded)\b/i', $filePath, $pm)) {
            $isHc = str_contains(strtolower($pm[0]), 'hc') || str_contains(strtolower($pm[0]), 'korsub') || str_contains(strtolower($pm[0]), 'hardcoded');
            $evidence[] = "File path contains release tag: '{$pm[0]}'";
            if (! $category) {
                $category = $isHc ? 'hardcoded_subs' : 'cam_recorded';
                $badgeEn = $isHc ? 'Hardcoded Subtitles (HC)' : 'CAM / Telesync';
                $badgeAr = $isHc ? 'ترجمة مدمجة في الصورة (HC)' : 'تصوير سينما / Telesync';
                $severity = 'high';
            }
        }

        // 3. Framerate Anomaly Check
        if ($framerate) {
            $fps = (float) $framerate;
            if (($fps < 23.0 && $fps > 0) || ($fps > 26.0 && $fps < 29.0)) {
                $evidence[] = "Abnormal framerate ({$framerate} fps, irregular screen capture cadence)";
                if (! $category) {
                    $category = 'cam_recorded';
                    $badgeEn = 'Abnormal Cadence (CAM/Screen)';
                    $badgeAr = 'معدل إطارات غير قياسي (تصوير)';
                }
            }
        }

        // 4. Check Historical Download Records in download_items
        $downloadItem = DownloadItem::where('organized_path', $filePath)
            ->orWhere(function ($q) use ($meta) {
                if (! empty($meta['id'])) {
                    $q->where('indexed_id', $meta['id']);
                }
            })
            ->first();

        if ($downloadItem && ! empty($downloadItem->title)) {
            $rawTitle = $downloadItem->title;
            if (preg_match('/\b(cam|hdcam|ts|telesync|tc|dvdscr|scr)\b/i', $rawTitle, $m)) {
                $evidence[] = "Original download release was '{$m[0]}': \"{$rawTitle}\"";
                if (! $category) {
                    $category = 'cam_recorded';
                    $badgeEn = 'CAM / Recorded Release';
                    $badgeAr = 'إصدار سينمائي مسجل';
                    $severity = 'high';
                }
            }
            if (preg_match('/\b(hc|korsub|subbed|cht)\b/i', $rawTitle, $m)) {
                $evidence[] = "Original download release had hardcoded subs '{$m[0]}': \"{$rawTitle}\"";
                if (! $category) {
                    $category = 'hardcoded_subs';
                    $badgeEn = 'Hardcoded Subtitles (HC)';
                    $badgeAr = 'ترجمة مدمجة في الصورة (HC)';
                    $severity = 'high';
                }
            }
        }

        // 5. Fast Pure-PHP Binary Header Inspection (128 KB)
        if (! empty($filePath) && file_exists($filePath)) {
            $chunk = $this->getFastHeaderChunk($filePath);
            if (! empty($chunk)) {
                if (preg_match('/\b(camrip|hdcam|telesync|hdts|\bhd-ts\b|pdvd|telecine|1xbet|c1nem4|will187)\b/i', $chunk, $hm)) {
                    $evidence[] = "Internal container tag matches CAM/Telesync signature: '{$hm[0]}'";
                    $category = 'cam_recorded';
                    $badgeEn = 'CAM / Telesync';
                    $badgeAr = 'تصوير سينما / Telesync';
                    $severity = 'high';
                } elseif (preg_match('/\b(korsub|chinesesub|\.hc\b|hdrip\.hc|web-?dl\.hc|hardcoded)\b/i', $chunk, $hm)) {
                    $evidence[] = "Internal container tag matches Hardcoded Subtitles (HC): '{$hm[0]}'";
                    $category = 'hardcoded_subs';
                    $badgeEn = 'Hardcoded Subtitles (HC)';
                    $badgeAr = 'ترجمة مدمجة في الصورة (HC)';
                    $severity = 'high';
                } elseif (preg_match('/[\x{4e00}-\x{9fa5}]/u', $chunk)) {
                    $isNativeAsian = in_array($origLang, ['zh', 'chi', 'zho', 'ja', 'jpn', 'ko', 'kor']);
                    if (! $isNativeAsian) {
                        $evidence[] = 'Internal container contains Asian watermark/subtitle tags';
                        if (! $category) {
                            $category = 'hardcoded_subs';
                            $badgeEn = 'Hardcoded Foreign Subs / Watermark';
                            $badgeAr = 'ترجمة صينية مدمجة / علامة مائية';
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
     * Fast header inspection reading first 128KB of file in pure PHP without external processes.
     */
    protected function getFastHeaderChunk(string $filePath): string
    {
        $cacheKey = 'cmh_bin_hdr_'.md5($filePath);

        return Cache::remember($cacheKey, 86400, function () use ($filePath) {
            $h = @fopen($filePath, 'rb');
            if (! $h) {
                return '';
            }
            $chunk = fread($h, 131072);
            fclose($h);

            return $chunk ?: '';
        });
    }

    /**
     * Get aggregate metrics for Media Scout hero counters.
     *
     * @return array<string, int>
     */
    public function getUpgradeMetrics(bool $forceRefresh = false): array
    {
        $lowRes = $this->getLowResolutionMedia($forceRefresh);
        $poor = $this->getPoorQualityMedia($forceRefresh);

        $camCount = $poor['by_category']['cam_recorded'] ?? 0;
        $hcCount = $poor['by_category']['hardcoded_subs'] ?? 0;
        $lowAudioCount = $poor['by_category']['watermarked_or_low_audio'] ?? 0;
        $wmCount = $poor['by_category']['watermarked_rip'] ?? 0;

        return [
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
    }

    /**
     * Clear all upgrade caches.
     */
    public function clearCache(): void
    {
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

    /**
     * Format bytes into human-readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / pow(1024, $i), 2).' '.$units[$i];
    }
}
