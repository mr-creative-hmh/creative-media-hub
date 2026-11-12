<?php

namespace App\Services\Streaming;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Series;
use App\Models\WatchHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchHistoryService
{
    /**
     * Save playback progress for media item or episode.
     */
    public function saveProgress(Request $request): JsonResponse
    {
        $prog = (int) $request->input('progress_seconds', $request->input('position_seconds', 0));
        $dur = (int) $request->input('duration_seconds', 0);
        $wId = (int) $request->input('watchable_id', $request->input('id', 0));
        $wType = strtolower((string) $request->input('watchable_type', $request->input('type', 'movie')));

        if ($wId <= 0 || $dur <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid parameters'], 422);
        }

        $modelClass = ($wType === 'episode') ? Episode::class : MediaItem::class;
        $isCompleted = ($prog / max(1, $dur)) >= 0.92;
        $userId = $request->user()?->id;

        // Clean up duplicate rows if any exist
        $duplicates = WatchHistory::where('watchable_type', $modelClass)
            ->where('watchable_id', $wId)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('id')
            ->get();

        if ($duplicates->count() > 1) {
            $keep = $duplicates->first();
            WatchHistory::where('watchable_type', $modelClass)
                ->where('watchable_id', $wId)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->where('id', '!=', $keep->id)
                ->delete();
        }

        $lookup = [
            'watchable_type' => $modelClass,
            'watchable_id' => $wId,
        ];
        if ($userId) {
            $lookup['user_id'] = $userId;
        }

        $watchHistory = WatchHistory::updateOrCreate(
            $lookup,
            [
                'user_id' => $userId,
                'progress_seconds' => $prog,
                'duration_seconds' => $dur,
                'is_completed' => $isCompleted,
                'last_watched_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'progress' => $watchHistory,
        ]);
    }

    /**
     * Get continue-watching / watch history list.
     */
    public function getWatchHistory(Request $request): JsonResponse
    {
        $type = $request->input('type', 'all');
        $userId = $request->user()?->id;

        // Fetch all in-progress watch history items (scoped to active user profile if logged in)
        $rawHistory = WatchHistory::query()
            ->where('is_completed', false)
            ->where('progress_seconds', '>', 3)
            ->when($userId, fn ($q) => $q->where(function ($sub) use ($userId) {
                $sub->where('user_id', $userId)->orWhereNull('user_id');
            }))
            ->orderByDesc('last_watched_at')
            ->with(['watchable'])
            ->get();

        // 1. Deduplicate by watchable_type + watchable_id
        $deduped = $rawHistory->unique(function ($h) {
            return $h->watchable_type.'_'.$h->watchable_id;
        });

        // 2. Group episodes by series so only the latest watched episode of each series is shown
        $seriesSeen = [];
        $uniqueList = $deduped->filter(function ($h) use (&$seriesSeen) {
            $item = $h->watchable;
            if (! $item) {
                return false;
            }

            if ($item instanceof Episode) {
                $seriesId = $item->series_id ?? ($item->season?->series_id ?? 0);
                if ($seriesId > 0) {
                    if (isset($seriesSeen[$seriesId])) {
                        return false; // Skip older episodes for this series
                    }
                    $seriesSeen[$seriesId] = true;
                }
            }

            return true;
        });

        // 3. Compute accurate counts for category tabs
        $counts = [
            'all' => $uniqueList->count(),
            'movies' => $uniqueList->filter(fn ($h) => $h->watchable instanceof MediaItem)->count(),
            'series' => $uniqueList->filter(fn ($h) => $h->watchable instanceof Episode)->count(),
            'collections' => $uniqueList->filter(fn ($h) => $h->watchable instanceof MediaItem && ! empty($h->watchable->collection_name))->count(),
        ];

        // 4. Filter by requested type if specified and not 'all'
        $filtered = $uniqueList;
        if ($type === 'movie' || $type === 'movies') {
            $filtered = $filtered->filter(fn ($h) => $h->watchable instanceof MediaItem);
        } elseif ($type === 'series' || $type === 'episode') {
            $filtered = $filtered->filter(fn ($h) => $h->watchable instanceof Episode);
        } elseif ($type === 'collection' || $type === 'collections') {
            $filtered = $filtered->filter(fn ($h) => $h->watchable instanceof MediaItem && ! empty($h->watchable->collection_name));
        }

        // 5. Transform into rich UI objects
        $formatTime = function ($sec) {
            $hrs = floor($sec / 3600);
            $mins = floor(($sec % 3600) / 60);
            $secs = $sec % 60;

            return $hrs > 0 ? sprintf('%d:%02d:%02d', $hrs, $mins, $secs) : sprintf('%02d:%02d', $mins, $secs);
        };

        $items = $filtered->map(function ($h) use ($formatTime) {
            $item = $h->watchable;
            if (! $item) {
                return null;
            }

            $percent = $h->duration_seconds > 0 ? min(100, round(($h->progress_seconds / $h->duration_seconds) * 100)) : 0;
            $remaining = max(0, $h->duration_seconds - $h->progress_seconds);

            if ($item instanceof MediaItem) {
                $item->loadMissing('subtitles');
                $slug = $item->slug ?: "movie-{$item->id}";
                $isCollection = ! empty($item->collection_name);

                $playlist = [];
                if ($isCollection) {
                    $playlist = MediaItem::query()
                        ->where('collection_name', $item->collection_name)
                        ->orderBy('release_year', 'asc')
                        ->orderBy('title', 'asc')
                        ->with('subtitles')
                        ->get()
                        ->map(function ($m) {
                            return [
                                'id' => $m->id,
                                'title' => $m->title,
                                'title_ar' => $m->title_ar,
                                'stream_url' => route('stream.movie', $m->id),
                                'poster_path' => $m->poster_path,
                                'backdrop_path' => $m->backdrop_path,
                                'duration_seconds' => $m->duration_seconds,
                                'subtitles' => $m->subtitles ?? [],
                                'type' => 'movie',
                            ];
                        })->values()->all();
                }

                return [
                    'history_id' => $h->id,
                    'id' => $item->id,
                    'watchable_id' => $item->id,
                    'watchable_type' => 'movie',
                    'category' => $isCollection ? 'collection' : 'movie',
                    'collection_name' => $item->collection_name,
                    'title' => $item->title,
                    'title_ar' => $item->title_ar,
                    'type' => 'movie',
                    'slug' => $slug,
                    'slug_url' => route('movies.show.slug', $slug),
                    'poster_path' => $item->poster_path,
                    'backdrop_path' => $item->backdrop_path,
                    'progress_seconds' => $h->progress_seconds,
                    'initial_progress' => $h->progress_seconds,
                    'duration_seconds' => $h->duration_seconds,
                    'progress_percent' => $percent,
                    'percent' => $percent,
                    'current_time_formatted' => $formatTime($h->progress_seconds),
                    'duration_formatted' => $formatTime($h->duration_seconds),
                    'remaining_formatted' => $formatTime($remaining),
                    'subtitles' => $item->subtitles,
                    'resolution' => $item->resolution,
                    'video_codec' => $item->video_codec,
                    'audio_codec' => $item->audio_codec,
                    'last_watched_at' => $h->last_watched_at ? $h->last_watched_at->toIso8601String() : null,
                    'stream_url' => route('stream.movie', $item->id),
                    'playlist' => $playlist,
                ];
            }

            if ($item instanceof Episode) {
                $item->loadMissing(['series', 'season', 'subtitles']);
                $series = $item->series ?? ($item->season->series ?? null);
                $sNameEn = $series ? $series->title : 'Series';
                $sNameAr = $series ? ($series->title_ar ?: $series->title) : 'مسلسل';
                $sNum = $item->season_number ?? ($item->season->season_number ?? 1);
                $eNum = $item->episode_number ?? 1;
                $seriesSlug = $series ? ($series->slug ?: "series-{$series->id}") : "series-{$item->series_id}";

                $cleanEpTitle = function (?string $rawTitle, ?string $sName): string {
                    $t = trim($rawTitle ?? '');
                    if ($t === '') {
                        return '';
                    }
                    if ($sName && str_starts_with(mb_strtolower($t), mb_strtolower($sName))) {
                        $t = trim(preg_replace('/^'.preg_quote($sName, '/').'[\s\-:]*/ui', '', $t));
                    }
                    $t = trim(preg_replace('/^S\d+\s*E\d+[\s\-:]*/i', '', $t));
                    $t = trim(preg_replace('/^(?:Season|الموسم)\s*\d+.*?[-:]*(?:Episode|الحلقة)\s*\d+[\s\-:]*/ui', '', $t));
                    $t = trim(preg_replace('/^[\s\-:]+/', '', $t));
                    $t = trim(preg_replace('/[\s\-:]+$/', '', $t));
                    if (preg_match('/^(?:Episode|الحلقة|Ep|Part)\s*\d+$/ui', $t) || preg_match('/^S\d+\s*E\d+$/i', $t)) {
                        return '';
                    }

                    return $t;
                };

                $epTitleEn = $cleanEpTitle($item->title, $sNameEn);
                $epTitleAr = $cleanEpTitle($item->title_ar, $sNameAr);
                if (empty($epTitleAr) && ! empty($epTitleEn)) {
                    $epTitleAr = $epTitleEn;
                }

                $fullTitleEn = "{$sNameEn} - Season {$sNum} - Episode {$eNum}".($epTitleEn ? " - {$epTitleEn}" : '');
                $fullTitleAr = "{$sNameAr} - الموسم {$sNum} - الحلقة {$eNum}".($epTitleAr ? " - {$epTitleAr}" : '');

                $seriesId = $series?->id ?? $item->series_id;
                $allSeriesEpisodes = Episode::query()
                    ->where('episodes.series_id', $seriesId)
                    ->leftJoin('seasons', 'seasons.id', '=', 'episodes.season_id')
                    ->orderBy('seasons.season_number', 'asc')
                    ->orderBy('episodes.episode_number', 'asc')
                    ->select('episodes.*', 'seasons.season_number as computed_season_number')
                    ->with('subtitles')
                    ->get();

                if ($allSeriesEpisodes->isEmpty()) {
                    $allSeriesEpisodes = Episode::query()
                        ->where('season_id', $item->season_id)
                        ->orderBy('episode_number', 'asc')
                        ->with('subtitles')
                        ->get();
                }

                $epPlaylist = $allSeriesEpisodes->map(function ($ep) use ($sNameEn, $sNameAr, $series, $cleanEpTitle) {
                    $epSNum = $ep->computed_season_number ?? ($ep->season_number ?? 1);
                    $epTitleClean = $cleanEpTitle($ep->title, $sNameEn);
                    $epTitleArClean = $cleanEpTitle($ep->title_ar, $sNameAr);
                    if (empty($epTitleArClean) && ! empty($epTitleClean)) {
                        $epTitleArClean = $epTitleClean;
                    }
                    $pTitleEn = "{$sNameEn} - Season {$epSNum} - Episode {$ep->episode_number}".($epTitleClean ? " - {$epTitleClean}" : '');
                    $pTitleAr = "{$sNameAr} - الموسم {$epSNum} - الحلقة {$ep->episode_number}".($epTitleArClean ? " - {$epTitleArClean}" : '');

                    return [
                        'id' => $ep->id,
                        'title' => $pTitleEn,
                        'title_ar' => $pTitleAr,
                        'episode_title' => $epTitleClean,
                        'episode_title_ar' => $epTitleArClean,
                        'stream_url' => route('stream.episode', $ep->id),
                        'still_path' => $ep->still_path ?: ($series ? $series->poster_path : null),
                        'poster_path' => $ep->still_path ?: ($series ? $series->poster_path : null),
                        'duration_seconds' => $ep->duration_seconds,
                        'season_number' => $epSNum,
                        'episode_number' => $ep->episode_number,
                        'subtitles' => $ep->subtitles ?? [],
                        'type' => 'episode',
                    ];
                })->values()->all();

                return [
                    'history_id' => $h->id,
                    'id' => $item->id,
                    'watchable_id' => $item->id,
                    'watchable_type' => 'episode',
                    'category' => 'series',
                    'series_id' => $series?->id,
                    'series_slug' => $seriesSlug,
                    'series_title' => $sNameEn,
                    'series_title_ar' => $sNameAr,
                    'season_number' => $sNum,
                    'episode_number' => $eNum,
                    'title' => $fullTitleEn,
                    'title_ar' => $fullTitleAr,
                    'episode_title' => $epTitleEn,
                    'episode_title_ar' => $epTitleAr,
                    'type' => 'episode',
                    'poster_path' => $item->still_path ?: ($series ? $series->poster_path : null),
                    'backdrop_path' => $series ? $series->backdrop_path : null,
                    'progress_seconds' => $h->progress_seconds,
                    'initial_progress' => $h->progress_seconds,
                    'duration_seconds' => $h->duration_seconds,
                    'progress_percent' => $percent,
                    'percent' => $percent,
                    'current_time_formatted' => $formatTime($h->progress_seconds),
                    'duration_formatted' => $formatTime($h->duration_seconds),
                    'remaining_formatted' => $formatTime($remaining),
                    'subtitles' => $item->subtitles,
                    'resolution' => $item->resolution,
                    'video_codec' => $item->video_codec,
                    'audio_codec' => $item->audio_codec,
                    'last_watched_at' => $h->last_watched_at ? $h->last_watched_at->toIso8601String() : null,
                    'stream_url' => route('stream.episode', $item->id),
                    'playlist' => $epPlaylist,
                ];
            }

            return null;
        })->filter()->values();

        return response()->json([
            'items' => $items,
            'counts' => $counts,
        ]);
    }

    /**
     * Build in-player playlist for episode or movie collection.
     */
    public function buildPlaylistForWatchable($item): array
    {
        if (! $item) {
            return [];
        }

        if ($item instanceof Episode) {
            $seriesId = $item->series_id ?? $item->season?->series_id;
            if (! $seriesId) {
                return [];
            }

            $series = Series::find($seriesId);
            $sNameEn = $series?->title ?? '';
            $sNameAr = $series?->title_ar ?? $sNameEn;

            $episodes = Episode::query()
                ->where('episodes.series_id', $seriesId)
                ->leftJoin('seasons', 'seasons.id', '=', 'episodes.season_id')
                ->orderBy('seasons.season_number', 'asc')
                ->orderBy('episodes.episode_number', 'asc')
                ->select('episodes.*', 'seasons.season_number as computed_season_number')
                ->with('subtitles')
                ->get();

            if ($episodes->isEmpty()) {
                $episodes = Episode::query()
                    ->where('season_id', $item->season_id)
                    ->orderBy('episode_number', 'asc')
                    ->with('subtitles')
                    ->get();
            }

            $cleanEpTitle = function (?string $rawTitle, ?string $sName): string {
                $t = trim($rawTitle ?? '');
                if ($t === '') {
                    return '';
                }
                if ($sName && str_starts_with(mb_strtolower($t), mb_strtolower($sName))) {
                    $t = trim(preg_replace('/^'.preg_quote($sName, '/').'[\s\-:]*/ui', '', $t));
                }
                $t = trim(preg_replace('/^S\d+\s*E\d+[\s\-:]*/i', '', $t));
                $t = trim(preg_replace('/^(?:Season|الموسم)\s*\d+.*?[-:]*(?:Episode|الحلقة)\s*\d+[\s\-:]*/ui', '', $t));
                $t = trim(preg_replace('/^[\s\-:]+/', '', $t));
                $t = trim(preg_replace('/[\s\-:]+$/', '', $t));
                if (preg_match('/^(?:Episode|الحلقة|Ep|Part)\s*\d+$/ui', $t) || preg_match('/^S\d+\s*E\d+$/i', $t)) {
                    return '';
                }

                return $t;
            };

            return $episodes->map(function ($e) use ($series, $sNameEn, $sNameAr, $cleanEpTitle) {
                $seasonNum = $e->computed_season_number ?? ($e->season?->season_number ?? 1);
                $epNum = $e->episode_number ?? 1;
                $epTitleClean = $cleanEpTitle($e->title, $sNameEn);
                $epTitleArClean = $cleanEpTitle($e->title_ar, $sNameAr);
                if (empty($epTitleArClean) && ! empty($epTitleClean)) {
                    $epTitleArClean = $epTitleClean;
                }

                $pTitleEn = "{$sNameEn} - Season {$seasonNum} - Episode {$epNum}".($epTitleClean ? " - {$epTitleClean}" : '');
                $pTitleAr = "{$sNameAr} - الموسم {$seasonNum} - الحلقة {$epNum}".($epTitleArClean ? " - {$epTitleArClean}" : '');

                return [
                    'id' => $e->id,
                    'title' => $pTitleEn,
                    'title_ar' => $pTitleAr,
                    'episode_title' => $epTitleClean,
                    'episode_title_ar' => $epTitleArClean,
                    'stream_url' => route('stream.episode', $e->id),
                    'still_path' => $e->still_path ?: ($series ? $series->poster_path : null),
                    'poster_path' => $e->still_path ?: ($series ? $series->poster_path : null),
                    'duration_seconds' => $e->duration_seconds,
                    'season_number' => $seasonNum,
                    'episode_number' => $epNum,
                    'subtitles' => $e->subtitles ?? [],
                    'type' => 'episode',
                ];
            })->values()->all();
        }

        if ($item instanceof MediaItem && ! empty($item->collection_name)) {
            $movies = MediaItem::where('collection_name', $item->collection_name)
                ->orderBy('release_year', 'asc')
                ->orderBy('title', 'asc')
                ->with('subtitles')
                ->get();

            return $movies->map(function ($m) {
                return [
                    'id' => $m->id,
                    'title' => $m->title,
                    'title_ar' => $m->title_ar,
                    'stream_url' => route('stream.movie', $m->id),
                    'poster_path' => $m->poster_path,
                    'backdrop_path' => $m->backdrop_path,
                    'duration_seconds' => $m->duration_seconds,
                    'subtitles' => $m->subtitles ?? [],
                    'type' => 'movie',
                ];
            })->values()->all();
        }

        return [];
    }

    /**
     * Get in-player playlist endpoint response.
     */
    public function getPlaylist(Request $request): JsonResponse
    {
        $type = $request->input('type', 'episode');
        $id = (int) $request->input('id');

        if ($id <= 0) {
            return response()->json(['playlist' => []]);
        }

        if ($type === 'episode') {
            $ep = Episode::with(['season.series', 'series'])->find($id);
            if (! $ep) {
                return response()->json(['playlist' => []]);
            }

            $series = $ep->series ?? $ep->season?->series;

            return response()->json([
                'playlist' => $this->buildPlaylistForWatchable($ep),
                'series' => $series ? [
                    'id' => $series->id,
                    'title' => $series->title,
                    'title_ar' => $series->title_ar,
                ] : null,
            ]);
        }

        if ($type === 'movie') {
            $movie = MediaItem::find($id);
            if ($movie && ! empty($movie->collection_name)) {
                return response()->json([
                    'playlist' => $this->buildPlaylistForWatchable($movie),
                    'collection_name' => $movie->collection_name,
                ]);
            }
        }

        return response()->json(['playlist' => []]);
    }

    /**
     * Delete a single watch history entry or series history.
     */
    public function deleteWatchHistory(Request $request, $id): JsonResponse
    {
        $rawType = strtolower((string) $request->input('type', ''));
        $isSeriesRequest = ($rawType === 'series' || $request->boolean('is_series'));
        $isEpisodeRequest = ($rawType === 'episode' || $rawType === 'episodes');
        $isMovieRequest = ($rawType === 'movie' || $rawType === 'movies' || $rawType === 'collection' || $rawType === 'collections');

        $explicitWatchableType = $request->input('watchable_type');
        $seriesId = $request->input('series_id');
        $explicitWatchableId = $request->input('watchable_id');
        $userId = $request->user()?->id;

        if ($explicitWatchableType) {
            $modelClass = in_array(strtolower((string) $explicitWatchableType), ['episode', 'episodes', Episode::class])
                ? Episode::class
                : MediaItem::class;
        } elseif ($isSeriesRequest || $isEpisodeRequest) {
            $modelClass = Episode::class;
        } elseif ($isMovieRequest) {
            $modelClass = MediaItem::class;
        } else {
            $modelClass = null;
        }

        // 1. If explicit series_id provided, remove all watched episodes for that series
        if (! empty($seriesId)) {
            $epIds = Episode::where('series_id', $seriesId)->pluck('id');
            WatchHistory::where('watchable_type', Episode::class)
                ->when($userId, fn ($q) => $q->where(function ($sub) use ($userId) {
                    $sub->where('user_id', $userId)->orWhereNull('user_id');
                }))
                ->whereIn('watchable_id', $epIds)
                ->delete();
        }

        // 2. If caller explicitly passed watchable_id or watchable_type, prioritize deleting by watchable entity
        $targetWatchableId = $explicitWatchableId ?: ($explicitWatchableType ? $id : null);
        if ($targetWatchableId) {
            $targetClass = $modelClass ?? (($isSeriesRequest || $isEpisodeRequest) ? Episode::class : MediaItem::class);

            if ($targetClass === Episode::class) {
                $ep = Episode::find($targetWatchableId);
                $resolvedSeriesId = $ep?->series_id ?? ($ep?->season?->series_id ?? 0);
                if ($resolvedSeriesId > 0 && $isSeriesRequest) {
                    $epIds = Episode::where('series_id', $resolvedSeriesId)->pluck('id');
                    WatchHistory::where('watchable_type', Episode::class)
                        ->when($userId, fn ($q) => $q->where(function ($sub) use ($userId) {
                            $sub->where('user_id', $userId)->orWhereNull('user_id');
                        }))
                        ->whereIn('watchable_id', $epIds)
                        ->delete();
                }

                WatchHistory::where('watchable_type', Episode::class)
                    ->where('watchable_id', $targetWatchableId)
                    ->when($userId, fn ($q) => $q->where(function ($sub) use ($userId) {
                        $sub->where('user_id', $userId)->orWhereNull('user_id');
                    }))
                    ->delete();
            } else {
                WatchHistory::where('watchable_type', MediaItem::class)
                    ->where('watchable_id', $targetWatchableId)
                    ->when($userId, fn ($q) => $q->where(function ($sub) use ($userId) {
                        $sub->where('user_id', $userId)->orWhereNull('user_id');
                    }))
                    ->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Item removed from watch history',
            ]);
        }

        // 3. Check if $id matches a WatchHistory primary ID (with type collision guard)
        $record = WatchHistory::when($userId, fn ($q) => $q->where(function ($sub) use ($userId) {
            $sub->where('user_id', $userId)->orWhereNull('user_id');
        }))->find($id);

        if ($record && ($modelClass === null || $record->watchable_type === $modelClass)) {
            if ($record->watchable_type === Episode::class) {
                $ep = $record->watchable ?? Episode::find($record->watchable_id);
                $resolvedSeriesId = $ep?->series_id ?? ($ep?->season?->series_id ?? 0);
                if ($resolvedSeriesId > 0 && ($isSeriesRequest || ! empty($seriesId))) {
                    $epIds = Episode::where('series_id', $resolvedSeriesId)->pluck('id');
                    WatchHistory::where('watchable_type', Episode::class)
                        ->when($userId, fn ($q) => $q->where(function ($sub) use ($userId) {
                            $sub->where('user_id', $userId)->orWhereNull('user_id');
                        }))
                        ->whereIn('watchable_id', $epIds)
                        ->delete();
                }
            }

            $record->delete();

            return response()->json([
                'success' => true,
                'message' => 'Item removed from watch history',
            ]);
        }

        // 4. Fallback: treat $id as entity primary key
        $fallbackClass = $modelClass ?? (($isSeriesRequest || $isEpisodeRequest) ? Episode::class : MediaItem::class);
        if ($fallbackClass === Episode::class) {
            $ep = Episode::find($id);
            $resolvedSeriesId = $ep?->series_id ?? ($ep?->season?->series_id ?? 0);
            if ($resolvedSeriesId > 0 && $isSeriesRequest) {
                $epIds = Episode::where('series_id', $resolvedSeriesId)->pluck('id');
                WatchHistory::where('watchable_type', Episode::class)
                    ->when($userId, fn ($q) => $q->where(function ($sub) use ($userId) {
                        $sub->where('user_id', $userId)->orWhereNull('user_id');
                    }))
                    ->whereIn('watchable_id', $epIds)
                    ->delete();
            }

            WatchHistory::where('watchable_type', Episode::class)
                ->where('watchable_id', $id)
                ->when($userId, fn ($q) => $q->where(function ($sub) use ($userId) {
                    $sub->where('user_id', $userId)->orWhereNull('user_id');
                }))
                ->delete();
        } else {
            WatchHistory::where('watchable_type', MediaItem::class)
                ->where('watchable_id', $id)
                ->when($userId, fn ($q) => $q->where(function ($sub) use ($userId) {
                    $sub->where('user_id', $userId)->orWhereNull('user_id');
                }))
                ->delete();
        }

        // 5. If caller passed a series ID directly when $isSeriesRequest is true
        if ($isSeriesRequest) {
            $epIds = Episode::where('series_id', $id)->pluck('id');
            if ($epIds->isNotEmpty()) {
                WatchHistory::where('watchable_type', Episode::class)
                    ->when($userId, fn ($q) => $q->where(function ($sub) use ($userId) {
                        $sub->where('user_id', $userId)->orWhereNull('user_id');
                    }))
                    ->whereIn('watchable_id', $epIds)
                    ->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Item removed from watch history',
        ]);
    }

    /**
     * Clear all watch history.
     */
    public function clearWatchHistory(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        WatchHistory::query()
            ->when($userId, fn ($q) => $q->where(function ($sub) use ($userId) {
                $sub->where('user_id', $userId)->orWhereNull('user_id');
            }))
            ->delete();

        return response()->json(['success' => true]);
    }
}
