<?php

namespace App\Services\Metadata;

use App\Models\MediaItem;
use App\Models\Series;
use App\Models\Season;
use App\Models\Episode;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LibraryMasterIndexService
{
    protected string $indexPath;
    protected TmdbProvider $tmdb;
    protected MetadataAggregator $aggregator;

    protected ?array $moviesIndex = null;
    protected ?array $seriesIndex = null;
    protected ?array $collectionsIndex = null;
    protected ?array $popularCache = null;

    public function __construct(TmdbProvider $tmdb, MetadataAggregator $aggregator)
    {
        $this->tmdb = $tmdb;
        $this->aggregator = $aggregator;
        $this->indexPath = storage_path('app/metadata-index');

        if (!File::isDirectory($this->indexPath)) {
            File::makeDirectory($this->indexPath, 0755, true, true);
        }
    }

    /**
     * Get index storage directory path.
     */
    public function getIndexPath(): string
    {
        return $this->indexPath;
    }

    /**
     * Convert absolute disk path to canonical relative library path (no drive letter).
     */
    public function toRelativePath(?string $path): ?string
    {
        if (!$path) return null;
        $norm = str_replace('\\', '/', $path);
        $clean = preg_replace('#^[a-zA-Z]:/(?:Entertainment/)?#i', '', $norm);
        return ltrim($clean, '/');
    }

    /**
     * Resolve canonical relative path to absolute disk path dynamically.
     */
    public function resolveAbsolutePath(string $relativePath): string
    {
        $root = config('media.library_root');
        if ($root && is_dir($root)) {
            return rtrim(str_replace('\\', '/', $root), '/') . '/' . ltrim($relativePath, '/');
        }
        foreach (['H:', 'D:', 'E:', 'C:'] as $drive) {
            $candidate = "{$drive}/Entertainment/" . ltrim($relativePath, '/');
            if (file_exists($candidate) || is_dir(dirname($candidate))) {
                return $candidate;
            }
        }
        return "H:/Entertainment/" . ltrim($relativePath, '/');
    }

    // =========================================================================
    // IN-MEMORY FAST LOOKUPS (< 1ms)
    // =========================================================================

    public function lookupMovie(string $title, ?int $year = null, ?int $tmdbId = null): ?array
    {
        $this->loadMoviesIndex();

        // 1. Lookup by TMDB ID
        if ($tmdbId && isset($this->moviesIndex['by_tmdb'][$tmdbId])) {
            return $this->moviesIndex['by_tmdb'][$tmdbId];
        }

        // 2. Lookup by Slug + Year
        $slug = Str::slug($title);
        if ($year && isset($this->moviesIndex['by_slug']["{$slug}-{$year}"])) {
            return $this->moviesIndex['by_slug']["{$slug}-{$year}"];
        }

        // 3. Lookup by Slug only
        if (isset($this->moviesIndex['by_slug'][$slug])) {
            return $this->moviesIndex['by_slug'][$slug];
        }

        // 4. Check popular cache fallback
        $this->loadPopularCache();
        if ($tmdbId && isset($this->popularCache['movies_by_tmdb'][$tmdbId])) {
            return $this->popularCache['movies_by_tmdb'][$tmdbId];
        }
        if ($year && isset($this->popularCache['movies_by_slug']["{$slug}-{$year}"])) {
            return $this->popularCache['movies_by_slug']["{$slug}-{$year}"];
        }
        if (isset($this->popularCache['movies_by_slug'][$slug])) {
            return $this->popularCache['movies_by_slug'][$slug];
        }

        return null;
    }

    public function lookupSeries(string $title, ?int $year = null, ?int $tmdbId = null): ?array
    {
        $this->loadSeriesIndex();

        // 1. Lookup by TMDB ID
        if ($tmdbId && isset($this->seriesIndex['by_tmdb'][$tmdbId])) {
            return $this->seriesIndex['by_tmdb'][$tmdbId];
        }

        // 2. Lookup by Slug
        $slug = Str::slug($title);
        if (isset($this->seriesIndex['by_slug'][$slug])) {
            return $this->seriesIndex['by_slug'][$slug];
        }

        // 3. Check popular cache fallback
        $this->loadPopularCache();
        if ($tmdbId && isset($this->popularCache['series_by_tmdb'][$tmdbId])) {
            return $this->popularCache['series_by_tmdb'][$tmdbId];
        }
        if (isset($this->popularCache['series_by_slug'][$slug])) {
            return $this->popularCache['series_by_slug'][$slug];
        }

        return null;
    }

    public function lookupEpisode(int|string $seriesIdentifier, int $seasonNumber, int $episodeNumber): ?array
    {
        $series = is_numeric($seriesIdentifier) 
            ? ($this->seriesIndex['by_tmdb'][$seriesIdentifier] ?? null)
            : $this->lookupSeries($seriesIdentifier);

        if (!$series) {
            return null;
        }

        $epKey = sprintf('S%02dE%02d', $seasonNumber, $episodeNumber);
        return $series['episodes'][$epKey] ?? null;
    }

    public function getExtendedCollection(int $collectionId): ?array
    {
        $this->loadCollectionsIndex();
        return $this->collectionsIndex[$collectionId] ?? null;
    }

    public function getAllExtendedCollections(): array
    {
        $this->loadCollectionsIndex();
        return array_values($this->collectionsIndex ?? []);
    }

    // =========================================================================
    // DYNAMIC ADD / UPDATE APIS
    // =========================================================================

    public function addOrUpdateMovie(array $data): void
    {
        $this->loadMoviesIndex();

        $tmdbId = $data['tmdb_id'] ?? null;
        $title = $data['title'] ?? ($data['title_en'] ?? '');
        $year = $data['release_year'] ?? ($data['year'] ?? null);
        $slug = Str::slug($title);

        $entry = array_merge([
            'tmdb_id' => $tmdbId,
            'imdb_id' => $data['imdb_id'] ?? null,
            'title' => $title,
            'title_en' => $title,
            'title_ar' => $data['title_ar'] ?? null,
            'release_year' => $year,
            'overview' => $data['overview'] ?? null,
            'overview_ar' => $data['overview_ar'] ?? null,
            'poster_path' => $data['poster_path'] ?? null,
            'backdrop_path' => $data['backdrop_path'] ?? null,
            'rating' => $data['rating'] ?? null,
            'runtime_minutes' => $data['runtime_minutes'] ?? null,
            'collection_id' => $data['collection_id'] ?? null,
            'collection_name' => $data['collection_name'] ?? null,
            'is_owned' => $data['is_owned'] ?? true,
        ], $data);

        if ($tmdbId) {
            $this->moviesIndex['by_tmdb'][$tmdbId] = $entry;
        }
        if ($year) {
            $this->moviesIndex['by_slug']["{$slug}-{$year}"] = $entry;
        }
        $this->moviesIndex['by_slug'][$slug] = $entry;

        $this->saveJson('movies_master_index.json', $this->moviesIndex);
    }

    public function addOrUpdateSeries(array $data): void
    {
        $this->loadSeriesIndex();

        $tmdbId = $data['tmdb_id'] ?? null;
        $title = $data['title'] ?? ($data['title_en'] ?? '');
        $slug = Str::slug($title);

        $entry = array_merge([
            'tmdb_id' => $tmdbId,
            'imdb_id' => $data['imdb_id'] ?? null,
            'title' => $title,
            'title_en' => $title,
            'title_ar' => $data['title_ar'] ?? null,
            'release_year' => $data['release_year'] ?? ($data['year'] ?? null),
            'overview' => $data['overview'] ?? null,
            'overview_ar' => $data['overview_ar'] ?? null,
            'poster_path' => $data['poster_path'] ?? null,
            'backdrop_path' => $data['backdrop_path'] ?? null,
            'rating' => $data['rating'] ?? null,
            'status' => $data['status'] ?? null,
            'episodes' => $data['episodes'] ?? [],
            'is_owned' => $data['is_owned'] ?? true,
        ], $data);

        if ($tmdbId) {
            $this->seriesIndex['by_tmdb'][$tmdbId] = $entry;
        }
        $this->seriesIndex['by_slug'][$slug] = $entry;

        $this->saveJson('series_master_index.json', $this->seriesIndex);
    }

    public function addOrUpdateCollection(array $collectionData): void
    {
        $this->loadCollectionsIndex();

        $collId = $collectionData['collection_id'] ?? ($collectionData['id'] ?? null);
        if (!$collId) return;

        $this->collectionsIndex[$collId] = $collectionData;
        $this->saveJson('collections_extended.json', $this->collectionsIndex);
    }

    // =========================================================================
    // ENRICHMENT OF DATABASE NULLS & GENERIC TITLES
    // =========================================================================

    /**
     * Query TMDB & web translation to fill all database nulls, generic titles, and missing images.
     */
    public function enrichMissingDatabaseMetadata(?callable $logger = null): array
    {
        $log = $logger ?: fn($msg) => null;
        $stats = [
            'movies_ar_titles_fixed' => 0,
            'movies_backdrops_fixed' => 0,
            'episodes_titles_enriched' => 0,
            'episodes_ar_enriched' => 0,
            'episodes_stills_fixed' => 0,
        ];

        $log("Starting Web Enrichment for Database Records...");

        // 1. Fill 34 Movies Missing Arabic Titles
        $moviesNoAr = MediaItem::where(function($q) {
            $q->whereNull('title_ar')->orWhere('title_ar', '');
        })->whereNotNull('tmdb_id')->get();

        $log("  Processing " . $moviesNoAr->count() . " movies missing Arabic title...");
        foreach ($moviesNoAr as $m) {
            $arTitle = $this->fetchArabicTitleFromTmdb('movie', $m->tmdb_id, $m->title);
            if ($arTitle) {
                $m->title_ar = $arTitle;
                $m->save();
                $stats['movies_ar_titles_fixed']++;
                $log("    ✓ Movie [ID {$m->id}] {$m->title} -> {$arTitle}");
            }
        }

        // 2. Fill Missing Movie Backdrops
        $moviesNoBackdrop = MediaItem::where(function($q) {
            $q->whereNull('backdrop_path')->orWhere('backdrop_path', '');
        })->whereNotNull('tmdb_id')->get();

        foreach ($moviesNoBackdrop as $m) {
            $details = $this->tmdb->getMovieDetails($m->tmdb_id);
            if (!empty($details['backdrop_path'])) {
                $m->backdrop_path = $details['backdrop_path'];
                $m->save();
                $stats['movies_backdrops_fixed']++;
                $log("    ✓ Movie [ID {$m->id}] {$m->title} backdrop restored.");
            }
        }

        // 2b. Fill Missing Series Arabic Titles & Movie Overviews
        $seriesNoAr = Series::where(function($q) {
            $q->whereNull('title_ar')->orWhere('title_ar', '');
        })->whereNotNull('tmdb_id')->get();
        foreach ($seriesNoAr as $s) {
            $ar = $this->fetchArabicTitleFromTmdb('tv', $s->tmdb_id, $s->title);
            if ($ar) {
                $s->title_ar = $ar;
                $s->save();
                $log("    ✓ Series [ID {$s->id}] {$s->title} -> {$ar}");
            }
        }

        $moviesNoOverview = MediaItem::where(function($q) {
            $q->whereNull('overview')->orWhere('overview', '');
        })->whereNotNull('tmdb_id')->get();
        foreach ($moviesNoOverview as $m) {
            $details = $this->tmdb->getMovieDetails($m->tmdb_id);
            if (!empty($details['overview'])) {
                $m->overview = $details['overview'];
                $m->save();
                $log("    ✓ Movie [ID {$m->id}] {$m->title} overview restored.");
            }
        }

        // 3. Fix 622 Generic Episode Titles & 232 Missing Arabic Episode Metadata
        $genericEps = Episode::where(function($q) {
            $q->where('title', 'like', 'Episode %')
              ->orWhere('title', 'like', 'Ep %')
              ->orWhereNull('title')
              ->orWhere('title', '')
              ->orWhereNull('title_ar')
              ->orWhere('title_ar', '');
        })->with('series')->get();

        $log("  Processing " . $genericEps->count() . " episodes with generic/missing titles...");
        
        // Group by series to batch TMDB season requests
        $bySeries = $genericEps->groupBy('series_id');

        foreach ($bySeries as $seriesId => $eps) {
            $series = $eps->first()->series;
            if (!$series || !$series->tmdb_id) continue;

            // Group episodes by season number
            $seasons = Season::whereIn('id', $eps->pluck('season_id')->unique())->get()->keyBy('id');
            $epsBySeasonNum = $eps->groupBy(fn($ep) => $seasons[$ep->season_id]->season_number ?? 1);

            foreach ($epsBySeasonNum as $seasonNum => $seasonEps) {
                // Fetch TMDB season data in English and Arabic
                $enSeasonData = $this->fetchTmdbSeasonData($series->tmdb_id, (int)$seasonNum, 'en');
                $arSeasonData = $this->fetchTmdbSeasonData($series->tmdb_id, (int)$seasonNum, 'ar');

                $enMap = collect($enSeasonData['episodes'] ?? [])->keyBy('episode_number');
                $arMap = collect($arSeasonData['episodes'] ?? [])->keyBy('episode_number');

                foreach ($seasonEps as $ep) {
                    $epNum = (int)$ep->episode_number;
                    $tmdbEn = $enMap[$epNum] ?? null;
                    $tmdbAr = $arMap[$epNum] ?? null;

                    $changed = false;

                    // Update English title if current is generic
                    if ($tmdbEn && !empty($tmdbEn['name']) && !preg_match('/^Episode \d+$/i', $tmdbEn['name'])) {
                        if (empty($ep->title) || preg_match('/^Episode \d+$/i', $ep->title)) {
                            $ep->title = $tmdbEn['name'];
                            $stats['episodes_titles_enriched']++;
                            $changed = true;
                        }
                    }

                    // Update Arabic title & overview
                    if ($tmdbAr && !empty($tmdbAr['name']) && !preg_match('/^الحلقة \d+$/i', $tmdbAr['name'])) {
                        if (empty($ep->title_ar)) {
                            $ep->title_ar = $tmdbAr['name'];
                            $stats['episodes_ar_enriched']++;
                            $changed = true;
                        }
                    } elseif (empty($ep->title_ar) && !empty($ep->title)) {
                        // Fallback translate title
                        $translated = $this->translateText($ep->title);
                        if ($translated) {
                            $ep->title_ar = $translated;
                            $stats['episodes_ar_enriched']++;
                            $changed = true;
                        }
                    }

                    if (empty($ep->overview_ar)) {
                        if ($tmdbAr && !empty($tmdbAr['overview'])) {
                            $ep->overview_ar = $tmdbAr['overview'];
                            $changed = true;
                        } elseif (!empty($ep->overview)) {
                            $translatedOv = $this->translateText($ep->overview);
                            if ($translatedOv) {
                                $ep->overview_ar = $translatedOv;
                                $changed = true;
                            }
                        }
                    }

                    // Restore still image if missing
                    if (empty($ep->still_path) && $tmdbEn && !empty($tmdbEn['still_path'])) {
                        $ep->still_path = "https://image.tmdb.org/t/p/w780" . $tmdbEn['still_path'];
                        $stats['episodes_stills_fixed']++;
                        $changed = true;
                    }

                    if ($changed) {
                        $ep->save();
                    }
                }
            }
        }

        $log("  ✓ Database Enrichment Complete: " . json_encode($stats));
        return $stats;
    }

    // =========================================================================
    // MASTER INDEX BUILDER
    // =========================================================================

    /**
     * Build the entire master index from local DB + web enrichment + popular pre-seeding.
     */
    public function buildMasterIndex(bool $enrichFromWeb = true, bool $includePopular = true, ?callable $logger = null): array
    {
        $log = $logger ?: fn($msg) => null;
        $log("=== Building Local Master Metadata Index ===");

        if ($enrichFromWeb) {
            $this->enrichMissingDatabaseMetadata($log);
        }

        // 1. Build collections_extended.json
        $log("Step 1: Compiling collections_extended.json...");
        $collectionsIndex = $this->compileExtendedCollections($enrichFromWeb, $log);
        $this->saveJson('collections_extended.json', $collectionsIndex);
        $this->collectionsIndex = $collectionsIndex;

        // 2. Build movies_master_index.json
        $log("Step 2: Compiling movies_master_index.json...");
        $moviesIndex = $this->compileMoviesIndex($log);
        $this->saveJson('movies_master_index.json', $moviesIndex);
        $this->moviesIndex = $moviesIndex;

        // 3. Build series_master_index.json
        $log("Step 3: Compiling series_master_index.json...");
        $seriesIndex = $this->compileSeriesIndex($log);
        $this->saveJson('series_master_index.json', $seriesIndex);
        $this->seriesIndex = $seriesIndex;

        // 4. Pre-seed popular titles ("Just in case for later")
        if ($includePopular) {
            $log("Step 4: Pre-seeding popular & trending movies and series...");
            $popularCache = $this->compilePopularCache($log);
            $this->saveJson('popular_cache.json', $popularCache);
            $this->popularCache = $popularCache;
        }

        $summary = [
            'collections_count' => count($collectionsIndex),
            'movies_count' => count($moviesIndex['by_tmdb'] ?? []),
            'series_count' => count($seriesIndex['by_tmdb'] ?? []),
            'popular_movies_seeded' => count($this->popularCache['movies_by_tmdb'] ?? []),
            'popular_series_seeded' => count($this->popularCache['series_by_tmdb'] ?? []),
        ];

        $log("=== Master Index Generation Complete: " . json_encode($summary) . " ===");
        return $summary;
    }

    protected function compileExtendedCollections(bool $enrichFromWeb, callable $log): array
    {
        $collections = [];
        $uniqueCollections = MediaItem::whereNotNull('collection_id')
            ->where('collection_id', '>', 0)
            ->select('collection_id', 'collection_name')
            ->distinct()
            ->get();

        $log("  Processing " . $uniqueCollections->count() . " collections...");

        $indianCollections = [
            'Housefull Collection', 'Mardaani Collection', '3 Idiots Collection', 'Dabangg Collection',
            'Dhoom Collection', 'Race Collection', 'Raid Collection', 'Tiger Collection',
            'Aashiqui Collection', 'Goodachari Collection', 'Baby Collection', 'Taare Zameen Par Collection', 'Student of the Year Collection'
        ];

        foreach ($uniqueCollections as $col) {
            $colId = (int)$col->collection_id;
            $colName = $col->collection_name;

            if (in_array($colName, $indianCollections)) continue;

            // Fetch owned movies
            $ownedMovies = MediaItem::where('collection_id', $colId)->get();
            if ($ownedMovies->count() < 2) continue; // Enforce strict >= 2 owned movies rule
            $ownedTmdbIds = $ownedMovies->pluck('tmdb_id')->filter()->toArray();

            $colData = [
                'collection_id' => $colId,
                'name' => $colName,
                'name_en' => $colName,
                'name_ar' => null,
                'overview' => null,
                'overview_ar' => null,
                'poster_path' => $ownedMovies->first()->collection_poster ?? null,
                'backdrop_path' => null,
                'total_parts' => $ownedMovies->count(),
                'owned_count' => $ownedMovies->count(),
                'parts' => [],
            ];

            // Fetch extended parts from TMDB
            if ($enrichFromWeb) {
                $tmdbColEn = $this->tmdb->getCollectionDetails($colId, 'en');
                $tmdbColAr = $this->tmdb->getCollectionDetails($colId, 'ar');

                if ($tmdbColEn) {
                    $colData['name_en'] = $tmdbColEn['name'] ?? $colName;
                    $colData['name_ar'] = $tmdbColAr['name'] ?? null;
                    $colData['overview'] = $tmdbColEn['overview'] ?? null;
                    $colData['overview_ar'] = $tmdbColAr['overview'] ?? null;
                    $colData['poster_path'] = $tmdbColEn['poster_path'] ?? $colData['poster_path'];
                    $colData['backdrop_path'] = $tmdbColEn['backdrop_path'] ?? null;

                    $parts = [];
                    foreach ($tmdbColEn['parts'] ?? [] as $idx => $p) {
                        $pTmdbId = $p['tmdb_id'] ?? $p['id'];
                        $isOwned = in_array($pTmdbId, $ownedTmdbIds);
                        $matchedOwned = $isOwned ? $ownedMovies->firstWhere('tmdb_id', $pTmdbId) : null;

                        $arPart = ($tmdbColAr['parts'] ?? [])[$idx] ?? [];

                        $parts[] = [
                            'tmdb_id' => $pTmdbId,
                            'title' => $p['title'] ?? '',
                            'title_en' => $p['title'] ?? '',
                            'title_ar' => $arPart['title'] ?? null,
                            'release_year' => $p['release_year'] ?? null,
                            'release_date' => $p['release_date'] ?? null,
                            'poster_path' => $p['poster_path'] ?? null,
                            'backdrop_path' => $p['backdrop_path'] ?? null,
                            'overview' => $p['overview'] ?? null,
                            'overview_ar' => $arPart['overview'] ?? null,
                            'rating' => $p['rating'] ?? null,
                            'is_owned' => $isOwned,
                            'local_media_id' => $matchedOwned->id ?? null,
                        ];
                    }

                    $colData['parts'] = $parts;
                    $colData['total_parts'] = count($parts);
                }
            }

            // Fallback if TMDB didn't provide parts
            if (empty($colData['parts'])) {
                foreach ($ownedMovies as $om) {
                    $colData['parts'][] = [
                        'tmdb_id' => $om->tmdb_id,
                        'title' => $om->title,
                        'title_en' => $om->title,
                        'title_ar' => $om->title_ar,
                        'release_year' => $om->release_year,
                        'poster_path' => $om->poster_path,
                        'rating' => $om->rating,
                        'is_owned' => true,
                        'local_media_id' => $om->id,
                    ];
                }
            }

            $collections[$colId] = $colData;
        }

        return $collections;
    }

    protected function compileMoviesIndex(callable $log): array
    {
        $byTmdb = [];
        $bySlug = [];

        $movies = MediaItem::all();
        $log("  Indexing " . $movies->count() . " movies from database...");

        foreach ($movies as $m) {
            $slug = Str::slug($m->title);
            $year = $m->release_year;

            $entry = [
                'id' => $m->id,
                'tmdb_id' => $m->tmdb_id,
                'imdb_id' => $m->imdb_id,
                'title' => $m->title,
                'title_en' => $m->title,
                'title_ar' => $m->title_ar,
                'original_title' => $m->original_title,
                'release_year' => $year,
                'runtime_minutes' => $m->runtime_minutes,
                'rating' => $m->rating,
                'overview' => $m->overview,
                'overview_ar' => $m->overview_ar,
                'poster_path' => $m->poster_path,
                'backdrop_path' => $m->backdrop_path,
                'trailer_url' => $m->trailer_url,
                'collection_id' => $m->collection_id,
                'collection_name' => $m->collection_name,
                'relative_path' => $this->toRelativePath($m->file_path),
                'relative_folder' => $this->toRelativePath($m->folder_path),
                'relative_file_blueprint' => basename($m->file_path),
                'file_path' => $this->toRelativePath($m->file_path),
                'folder_path' => $this->toRelativePath($m->folder_path),
                'resolution' => $m->resolution,
                'file_size_bytes' => $m->file_size_bytes,
                'is_owned' => true,
            ];

            if ($m->tmdb_id) {
                $byTmdb[$m->tmdb_id] = $entry;
            }
            if ($year) {
                $bySlug["{$slug}-{$year}"] = $entry;
            }
            $bySlug[$slug] = $entry;
        }

        return [
            'by_tmdb' => $byTmdb,
            'by_slug' => $bySlug,
        ];
    }

    protected function compileSeriesIndex(callable $log): array
    {
        $byTmdb = [];
        $bySlug = [];

        $allSeries = Series::with(['seasons', 'episodes'])->get();
        $log("  Indexing " . $allSeries->count() . " series from database...");

        foreach ($allSeries as $s) {
            $slug = Str::slug($s->title);

            $episodesMap = [];
            $seasonsMap = $s->seasons->keyBy('id');

            foreach ($s->episodes as $ep) {
                $seasonNum = $seasonsMap[$ep->season_id]->season_number ?? 1;
                $epKey = sprintf('S%02dE%02d', $seasonNum, $ep->episode_number);

                $episodesMap[$epKey] = [
                    'id' => $ep->id,
                    'season_number' => $seasonNum,
                    'episode_number' => $ep->episode_number,
                    'title' => $ep->title,
                    'title_en' => $ep->title,
                    'title_ar' => $ep->title_ar,
                    'overview' => $ep->overview,
                    'overview_ar' => $ep->overview_ar,
                    'still_path' => $ep->still_path,
                    'runtime_minutes' => $ep->runtime_minutes,
                    'air_date' => $ep->air_date,
                    'resolution' => $ep->resolution,
                    'relative_path' => $this->toRelativePath($ep->file_path),
                    'relative_file_blueprint' => basename($ep->file_path),
                    'file_path' => $this->toRelativePath($ep->file_path),
                    'is_owned' => true,
                ];
            }

            $entry = [
                'id' => $s->id,
                'tmdb_id' => $s->tmdb_id,
                'imdb_id' => $s->imdb_id,
                'title' => $s->title,
                'title_en' => $s->title,
                'title_ar' => $s->title_ar,
                'release_year' => $s->release_year,
                'end_year' => $s->end_year,
                'rating' => $s->rating,
                'status' => $s->status,
                'overview' => $s->overview,
                'overview_ar' => $s->overview_ar,
                'poster_path' => $s->poster_path,
                'backdrop_path' => $s->backdrop_path,
                'relative_folder' => $this->toRelativePath($s->folder_path),
                'folder_path' => $this->toRelativePath($s->folder_path),
                'episodes' => $episodesMap,
                'is_owned' => true,
            ];

            if ($s->tmdb_id) {
                $byTmdb[$s->tmdb_id] = $entry;
            }
            $bySlug[$slug] = $entry;
        }

        return [
            'by_tmdb' => $byTmdb,
            'by_slug' => $bySlug,
        ];
    }

    protected function compilePopularCache(callable $log): array
    {
        $key = config('services.tmdb.key') ?: \App\Models\AppSetting::get('tmdb_api_key');
        if (!$key) {
            $log("  TMDb API key not found, skipping web popular cache.");
            return ['movies_by_tmdb' => [], 'movies_by_slug' => [], 'series_by_tmdb' => [], 'series_by_slug' => []];
        }

        $moviesByTmdb = [];
        $moviesBySlug = [];
        $seriesByTmdb = [];
        $seriesBySlug = [];

        // Fetch Top 500 popular movies (25 pages x 20)
        $log("  Fetching top popular movies from TMDb...");
        for ($page = 1; $page <= 15; $page++) {
            try {
                $res = Http::timeout(6)->get("https://api.themoviedb.org/3/movie/popular", [
                    'api_key' => $key,
                    'page' => $page,
                    'language' => 'en-US',
                ]);
                if ($res->successful()) {
                    foreach ($res->json('results', []) as $m) {
                        $tmdbId = $m['id'];
                        $title = $m['title'] ?? '';
                        $year = !empty($m['release_date']) ? (int)substr($m['release_date'], 0, 4) : null;
                        $slug = Str::slug($title);

                        $genreIds = $m['genre_ids'] ?? [];
                        $genreMap = [28=>'Action',12=>'Adventure',16=>'Animation',35=>'Comedy',80=>'Crime & Mystery',99=>'Documentary',18=>'Drama',10751=>'Family',14=>'Fantasy',36=>'History',27=>'Horror',10402=>'Music',9648=>'Crime & Mystery',10749=>'Romance',878=>'Sci-Fi',53=>'Thriller',10752=>'Action',37=>'Western'];
                        $genre = $genreMap[$genreIds[0] ?? 28] ?? 'Action';
                        $cleanTitle = preg_replace('/[\/:*?"<>|]/', '', $title);
                        $yearStr = $year ? " ({$year})" : '';
                        $relFolder = "Movies/{$genre}/{$cleanTitle}{$yearStr}";
                        $relBlueprint = "{$cleanTitle}{$yearStr} [{ProbedResolution}].mp4";

                        $entry = [
                            'tmdb_id' => $tmdbId,
                            'title' => $title,
                            'title_en' => $title,
                            'title_ar' => null,
                            'release_year' => $year,
                            'genre' => $genre,
                            'relative_folder' => $relFolder,
                            'relative_file_blueprint' => $relBlueprint,
                            'relative_path' => "{$relFolder}/{$relBlueprint}",
                            'overview' => $m['overview'] ?? '',
                            'poster_path' => !empty($m['poster_path']) ? "https://image.tmdb.org/t/p/w780{$m['poster_path']}" : null,
                            'backdrop_path' => !empty($m['backdrop_path']) ? "https://image.tmdb.org/t/p/w1280{$m['backdrop_path']}" : null,
                            'rating' => $m['vote_average'] ?? null,
                            'is_owned' => false,
                        ];

                        $moviesByTmdb[$tmdbId] = $entry;
                        if ($year) $moviesBySlug["{$slug}-{$year}"] = $entry;
                        $moviesBySlug[$slug] = $entry;
                    }
                }
            } catch (\Throwable $e) {
                break;
            }
        }

        // Fetch Top 200 popular series (10 pages x 20)
        $log("  Fetching top popular TV series from TMDb...");
        for ($page = 1; $page <= 10; $page++) {
            try {
                $res = Http::timeout(6)->get("https://api.themoviedb.org/3/tv/popular", [
                    'api_key' => $key,
                    'page' => $page,
                    'language' => 'en-US',
                ]);
                if ($res->successful()) {
                    foreach ($res->json('results', []) as $s) {
                        $tmdbId = $s['id'];
                        $title = $s['name'] ?? '';
                        $year = !empty($s['first_air_date']) ? (int)substr($s['first_air_date'], 0, 4) : null;
                        $slug = Str::slug($title);

                        $cleanTitle = preg_replace('/[\/:*?"<>|]/', '', $title);
                        $yearStr = $year ? " ({$year})" : '';
                        $isArabic = ($s['original_language'] ?? '') === 'ar' || in_array('EG', $s['origin_country'] ?? []) || in_array('SY', $s['origin_country'] ?? []);
                        $relFolder = $isArabic ? "TV Shows/Arabic Series/{$cleanTitle}{$yearStr}" : "TV Shows/{$cleanTitle}{$yearStr}";
                        $epBlueprint = "{$cleanTitle} - S{Season:02d}E{Episode:02d} - {EpisodeTitle} [{ProbedResolution}].mp4";

                        $entry = [
                            'tmdb_id' => $tmdbId,
                            'title' => $title,
                            'title_en' => $title,
                            'title_ar' => null,
                            'release_year' => $year,
                            'is_arabic' => $isArabic,
                            'relative_folder' => $relFolder,
                            'season_blueprint' => 'Season {Season:02d}',
                            'episode_file_blueprint' => $epBlueprint,
                            'overview' => $s['overview'] ?? '',
                            'poster_path' => !empty($s['poster_path']) ? "https://image.tmdb.org/t/p/w780{$s['poster_path']}" : null,
                            'backdrop_path' => !empty($s['backdrop_path']) ? "https://image.tmdb.org/t/p/w1280{$s['backdrop_path']}" : null,
                            'rating' => $s['vote_average'] ?? null,
                            'is_owned' => false,
                        ];

                        $seriesByTmdb[$tmdbId] = $entry;
                        if ($year) $seriesBySlug["{$slug}-{$year}"] = $entry;
                        $seriesBySlug[$slug] = $entry;
                    }
                }
            } catch (\Throwable $e) {
                break;
            }
        }

        return [
            'movies_by_tmdb' => $moviesByTmdb,
            'movies_by_slug' => $moviesBySlug,
            'series_by_tmdb' => $seriesByTmdb,
            'series_by_slug' => $seriesBySlug,
        ];
    }

    // =========================================================================
    // HELPER METHODS (TMDB API & TRANSLATION)
    // =========================================================================

    protected function fetchArabicTitleFromTmdb(string $type, int $tmdbId, string $fallbackTitle): ?string
    {
        $key = config('services.tmdb.key') ?: \App\Models\AppSetting::get('tmdb_api_key');
        if (!$key) return $this->translateText($fallbackTitle);

        try {
            $url = "https://api.themoviedb.org/3/{$type}/{$tmdbId}?api_key={$key}&language=ar-SA";
            $res = Http::timeout(6)->get($url);
            if ($res->successful()) {
                $arName = $res->json($type === 'movie' ? 'title' : 'name');
                if (!empty($arName) && $arName !== $fallbackTitle) {
                    return $arName;
                }
            }
        } catch (\Throwable $e) {}

        return $this->translateText($fallbackTitle);
    }

    protected function fetchTmdbSeasonData(int $seriesTmdbId, int $seasonNumber, string $lang): ?array
    {
        $key = config('services.tmdb.key') ?: \App\Models\AppSetting::get('tmdb_api_key');
        if (!$key) return null;

        try {
            $langCode = $lang === 'ar' ? 'ar-SA' : 'en-US';
            $res = Http::timeout(8)->get("https://api.themoviedb.org/3/tv/{$seriesTmdbId}/season/{$seasonNumber}", [
                'api_key' => $key,
                'language' => $langCode,
            ]);
            if ($res->successful()) {
                return $res->json();
            }
        } catch (\Throwable $e) {}

        return null;
    }

    protected function translateText(string $text): ?string
    {
        if (empty($text)) return null;

        try {
            $res = Http::timeout(5)->get('https://clients5.google.com/translate_a/t', [
                'client' => 'dict-chrome-ex',
                'sl' => 'en',
                'tl' => 'ar',
                'q' => $text,
            ]);
            if ($res->successful()) {
                $data = $res->json();
                if (is_array($data) && !empty($data[0])) {
                    return is_array($data[0]) ? ($data[0][0] ?? null) : $data[0];
                }
            }
        } catch (\Throwable $e) {}

        return null;
    }

    // =========================================================================
    // FILE I/O & LAZY LOADING
    // =========================================================================

    protected function loadMoviesIndex(): void
    {
        if ($this->moviesIndex !== null) return;
        $file = $this->indexPath . '/movies_master_index.json';
        $this->moviesIndex = File::exists($file) ? json_decode(File::get($file), true) : ['by_tmdb' => [], 'by_slug' => []];
    }

    protected function loadSeriesIndex(): void
    {
        if ($this->seriesIndex !== null) return;
        $file = $this->indexPath . '/series_master_index.json';
        $this->seriesIndex = File::exists($file) ? json_decode(File::get($file), true) : ['by_tmdb' => [], 'by_slug' => []];
    }

    protected function loadCollectionsIndex(): void
    {
        if ($this->collectionsIndex !== null) return;
        $file = $this->indexPath . '/collections_extended.json';
        $this->collectionsIndex = File::exists($file) ? json_decode(File::get($file), true) : [];
    }

    protected function loadPopularCache(): void
    {
        if ($this->popularCache !== null) return;
        $file = $this->indexPath . '/popular_cache.json';
        $this->popularCache = File::exists($file) ? json_decode(File::get($file), true) : ['movies_by_tmdb' => [], 'movies_by_slug' => [], 'series_by_tmdb' => [], 'series_by_slug' => []];
    }

    protected function saveJson(string $filename, array $data): void
    {
        File::put($this->indexPath . '/' . $filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
