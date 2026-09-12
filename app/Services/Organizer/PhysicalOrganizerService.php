<?php

namespace App\Services\Organizer;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\Genre;
use App\Models\MediaItem;
use App\Models\Series;
use App\Models\Subtitle;
use App\Services\Metadata\LibraryMasterIndexService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PhysicalOrganizerService
{
    protected array $claimedDestinations = [];

    protected SceneNameParserService $parser;

    protected static ?array $seriesCache = null;

    protected static array $seriesYearCache = [];

    protected static array $episodesBySeries = [];

    protected static array $movieCache = [];

    protected const CACHE_KEY = 'organizer_execution_state';

    protected const PLAN_CACHE_KEY = 'organizer_plan_state';

    public function __construct(SceneNameParserService $parser)
    {
        $this->parser = $parser;
    }

    public function generateDryRun(array $scannedFiles, string $targetRoot, ?string $moviePattern = null, ?string $seriesPattern = null): array
    {
        $moviePattern = $moviePattern ?: AppSetting::get('movie_naming_template', '{Type}/{Genre}/{Collection}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}');
        $seriesPattern = $seriesPattern ?: AppSetting::get('series_naming_template', '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{CleanResolution}].{ext}');

        $targetRoot = rtrim(str_replace('\\', '/', $targetRoot), '/');
        $plan = [];

        foreach ($scannedFiles as $file) {
            $plan[] = $this->generatePlanItem($file, $targetRoot, $moviePattern, $seriesPattern);
        }

        return $plan;
    }

    public function resolveCachedSeries(string $cleanTitle, string $filePath): ?Series
    {
        if (self::$seriesCache === null) {
            $seriesList = Series::with('genres')->get();
            $map = [];
            foreach ($seriesList as $s) {
                $norm = strtolower(preg_replace('/[^a-z0-9]/i', '', $s->title));
                $map[$norm] = $s;
                if ($s->folder_path) {
                    $normFolder = strtolower(rtrim(str_replace('\\', '/', $s->folder_path), '/'));
                    $map[$normFolder] = $s;
                    $folderBase = strtolower(basename($normFolder));
                    $folderBaseClean = strtolower(preg_replace('/[^a-z0-9]/i', '', $folderBase));
                    $map[$folderBaseClean] = $s;
                }
            }
            self::$seriesCache = $map;
        }

        $normTitle = strtolower(preg_replace('/[^a-z0-9]/i', '', $cleanTitle));
        if (isset(self::$seriesCache[$normTitle])) {
            return self::$seriesCache[$normTitle];
        }

        $normPath = strtolower(str_replace('\\', '/', $filePath));
        foreach (self::$seriesCache as $key => $s) {
            if (str_contains($normPath, "/{$key}/") || str_contains($normPath, "/{$key} (") || str_contains($normPath, "/{$key}.")) {
                return $s;
            }
        }

        return Series::where('title', 'like', "%{$cleanTitle}%")->with('genres')->first();
    }

    public function resolveSeriesYearString(Series $series): string
    {
        if (isset(self::$seriesYearCache[$series->id])) {
            return self::$seriesYearCache[$series->id];
        }

        $minY = $series->release_year;
        $maxY = $series->end_year;

        if (! $maxY) {
            $maxAir = Episode::where('series_id', $series->id)->whereNotNull('air_date')->max('air_date');
            if ($maxAir) {
                $mYear = (int) substr($maxAir, 0, 4);
                if ($mYear > $minY) {
                    $maxY = $mYear;
                }
            }
        }

        if ($minY && $maxY && $maxY > $minY) {
            $yearStr = "{$minY} - {$maxY}";
        } elseif ($minY) {
            $yearStr = (string) $minY;
        } else {
            $yearStr = '';
        }

        return self::$seriesYearCache[$series->id] = $yearStr;
    }

    public function resolveCachedEpisode(?int $seriesId, int $seasonNum, int $episodeNum, string $filePath): ?Episode
    {
        if (! $seriesId) {
            return null;
        }

        if (! isset(self::$episodesBySeries[$seriesId])) {
            $eps = Episode::where('series_id', $seriesId)
                ->select('id', 'series_id', 'season_id', 'episode_number', 'title', 'clean_episode_title', 'resolution', 'file_path')
                ->with('season:id,season_number')
                ->get();

            $map = [];
            foreach ($eps as $ep) {
                $sNum = $ep->season ? (int) $ep->season->season_number : 1;
                $map["{$sNum}_{$ep->episode_number}"] = $ep;
                if ($ep->file_path) {
                    $map[strtolower(str_replace('\\', '/', $ep->file_path))] = $ep;
                }
            }
            self::$episodesBySeries[$seriesId] = $map;
        }

        $key = "{$seasonNum}_{$episodeNum}";
        if (isset(self::$episodesBySeries[$seriesId][$key])) {
            return self::$episodesBySeries[$seriesId][$key];
        }

        $normPath = strtolower(str_replace('\\', '/', $filePath));
        if (isset(self::$episodesBySeries[$seriesId][$normPath])) {
            return self::$episodesBySeries[$seriesId][$normPath];
        }

        return null;
    }

    public function resolveCachedMovie(string $cleanTitle, string $filePath): ?MediaItem
    {
        $norm = strtolower(preg_replace('/[^a-z0-9]/i', '', $cleanTitle));
        if (isset(self::$movieCache[$norm])) {
            return self::$movieCache[$norm];
        }

        $movie = MediaItem::where('title', 'like', "%{$cleanTitle}%")
            ->orWhere('original_title', 'like', "%{$cleanTitle}%")
            ->with('genres')
            ->first();

        return self::$movieCache[$norm] = $movie;
    }

    /**
     * Multi-tier collection detection:
     * Tier 1: Explicitly passed or pre-parsed collection_name
     * Tier 2: Existing MediaItem in local database
     * Tier 3: Parent/ancestor directory name containing Collection/Boxset/etc.
     * Tier 4: Known franchise pattern dictionary
     */
    public function resolveCollectionName(string $filePath, array $parsed, ?string $explicitColl = null): ?string
    {
        $isSeries = ($parsed['type'] ?? 'movie') === 'series';
        if ($isSeries) {
            return null;
        }

        $cleanTitle = $parsed['clean_title'] ?? ($parsed['title'] ?? '');

        // The Lord of the Rings: The Rings of Power is a TV series, never match movie collections
        if (stripos($cleanTitle, 'Rings of Power') !== false || stripos($filePath, 'Rings of Power') !== false) {
            return null;
        }

        if (! empty($explicitColl)) {
            return $this->formatCollectionName($explicitColl);
        }

        if (! empty($parsed['collection_name'])) {
            return $this->formatCollectionName($parsed['collection_name']);
        }

        // Tier 1: Local Master Metadata Index (<0.1ms offline lookup)
        try {
            $masterService = app(LibraryMasterIndexService::class);
            $year = ! empty($parsed['year']) ? (int) $parsed['year'] : null;
            $masterMovie = $masterService->lookupMovie($cleanTitle, $year);
            if ($masterMovie && ! empty($masterMovie['collection_name'])) {
                return $this->formatCollectionName($masterMovie['collection_name']);
            }
        } catch (\Throwable $e) {
            // Master index service unavailable
        }

        // Tier 2: Local Database MediaItem check
        if (! $isSeries && ! empty($cleanTitle)) {
            try {
                $normalizedSearch = preg_replace('/[^a-z0-9]/i', '', $cleanTitle);
                $dbMovie = MediaItem::where('title', 'like', "%{$cleanTitle}%")
                    ->orWhere('original_title', 'like', "%{$cleanTitle}%")
                    ->first();

                if ($dbMovie && ! empty($dbMovie->collection_name)) {
                    return $this->formatCollectionName($dbMovie->collection_name);
                }

                // Fallback normalized search for titles with colons/hyphens (e.g. Deathly Hallows: Part 1)
                if (strlen($normalizedSearch) >= 6) {
                    $candidate = MediaItem::whereNotNull('collection_name')
                        ->where('collection_name', '!=', '')
                        ->get()
                        ->first(function ($m) use ($normalizedSearch) {
                            $mNorm = preg_replace('/[^a-z0-9]/i', '', $m->title);

                            return str_contains($mNorm, $normalizedSearch) || str_contains($normalizedSearch, $mNorm);
                        });
                    if ($candidate && ! empty($candidate->collection_name)) {
                        return $this->formatCollectionName($candidate->collection_name);
                    }
                }
            } catch (\Throwable $e) {
                // Table might not exist or be unmigrated in isolated environments
            }
        }

        // Tier 3: Directory / Path Analysis (parent folder or up to 3 parent levels)
        $normalizedPath = str_replace(['\\', '/'], '/', $filePath);
        $pathParts = explode('/', $normalizedPath);
        $parentDirs = array_slice($pathParts, max(0, count($pathParts) - 4), -1);
        foreach (array_reverse($parentDirs) as $dir) {
            if (preg_match('/^([a-zA-Z0-9\s\':\-\.]+?)\s+(?:Collection|Boxset|Trilogy|Quadrilogy|Anthology|Saga|Franchise)\b/i', $dir, $m)) {
                return $this->formatCollectionName(trim($m[1]).' Collection');
            }
            if (preg_match('/\b([a-zA-Z0-9\s\':\-\.]+?)\s+Collection\b/i', $dir, $m)) {
                return $this->formatCollectionName(trim($m[1]).' Collection');
            }
        }

        // Tier 4: Known Franchise Pattern Dictionary
        $knownFranchises = [
            'Harry Potter' => 'Harry Potter Collection',
            'Lord of the Rings' => 'The Lord of the Rings Collection',
            'The Hobbit' => 'The Hobbit Collection',
            'Fantastic Beasts' => 'Fantastic Beasts Collection',
            'Fast & Furious' => 'Fast & Furious Collection',
            'Fast and Furious' => 'Fast & Furious Collection',
            'The Dark Knight' => 'The Dark Knight Collection',
            'Dark Knight' => 'The Dark Knight Collection',
            'Batman' => 'Batman Collection',
            'Star Wars' => 'Star Wars Collection',
            'Transformers' => 'Transformers Collection',
            'Pirates of the Caribbean' => 'Pirates of the Caribbean Collection',
            'Mission: Impossible' => 'Mission: Impossible Collection',
            'Mission Impossible' => 'Mission: Impossible Collection',
            'John Wick' => 'John Wick Collection',
            'Bad Boys' => 'Bad Boys Collection',
            'The Godfather' => 'The Godfather Collection',
            'Godfather' => 'The Godfather Collection',
            'The Hunger Games' => 'The Hunger Games Collection',
            'Hunger Games' => 'The Hunger Games Collection',
            'The Twilight Saga' => 'The Twilight Saga Collection',
            'Twilight' => 'The Twilight Saga Collection',
            'The Matrix' => 'The Matrix Collection',
            'Matrix' => 'The Matrix Collection',
            'Spider-Man' => 'Spider-Man Collection',
            'Spider Man' => 'Spider-Man Collection',
            'Jurassic Park' => 'Jurassic Park Collection',
            'Jurassic World' => 'Jurassic Park Collection',
            'Toy Story' => 'Toy Story Collection',
            'Shrek' => 'Shrek Collection',
            'Ice Age' => 'Ice Age Collection',
            'Minions' => 'Minions Collection',
            'Despicable Me' => 'Despicable Me Collection',
            'Omar & Salma' => 'Omar & Salma Collection',
            'عمر وسلمى' => 'Omar & Salma Collection',
            'Kung Fu Panda' => 'Kung Fu Panda Collection',
            'How to Train Your Dragon' => 'How to Train Your Dragon Collection',
            'Mad Max' => 'Mad Max Collection',
            'Bourne' => 'Bourne Collection',
            'Indiana Jones' => 'Indiana Jones Collection',
            'Die Hard' => 'Die Hard Collection',
            'James Bond' => 'James Bond 007 Collection',
            '007' => 'James Bond 007 Collection',
            'Planet of the Apes' => 'Planet of the Apes Collection',
            'Alien' => 'Alien Collection',
            'Predator' => 'Predator Collection',
            'Terminator' => 'The Terminator Collection',
            'Saw' => 'Saw Collection',
            'The Conjuring' => 'The Conjuring Universe Collection',
            'Insidious' => 'Insidious Collection',
        ];

        foreach ($knownFranchises as $frag => $fullColl) {
            if (stripos($cleanTitle, $frag) !== false || stripos($filePath, $frag) !== false) {
                return $fullColl;
            }
        }

        return null;
    }

    private function formatCollectionName(string $name): string
    {
        $name = trim($name);
        if (! preg_match('/collection$/i', $name)) {
            $name .= ' Collection';
        }

        return $name;
    }

    public function generatePlanItem(array $file, string $targetRoot, ?string $moviePattern = null, ?string $seriesPattern = null): array
    {
        $moviePattern = $moviePattern ?: AppSetting::get('movie_naming_template', '{Type}/{Genre}/{Collection}/{Title} ({Year})/{Title} ({Year}) [{CleanResolution}].{ext}');
        $seriesPattern = $seriesPattern ?: AppSetting::get('series_naming_template', '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{CleanResolution}].{ext}');
        $targetRoot = rtrim(str_replace('\\', '/', $targetRoot), '/');

        $filePath = $file['path'] ?? ($file['filename'] ?? '');
        $parsed = $file['parsed'] ?? $this->parser->parse($filePath);
        $isSeries = ($parsed['type'] ?? 'movie') === 'series';

        $pattern = $isSeries ? $seriesPattern : $moviePattern;
        $typeDir = $isSeries ? 'TV Shows' : 'Movies';

        $cleanTitle = $this->sanitizePathSegment($parsed['clean_title'] ?? ($parsed['title'] ?? ($parsed['series_title'] ?? 'Unknown')));
        $year = ! empty($parsed['year']) ? (string) $parsed['year'] : '';
        $seasonNum = isset($parsed['season']) ? (int) $parsed['season'] : 1;
        $episodeNum = isset($parsed['episode']) ? (int) $parsed['episode'] : null;

        if ($isSeries && $episodeNum === null) {
            $fn = pathinfo($filePath, PATHINFO_FILENAME);
            if (preg_match('/[sS]\d{1,2}[eE](\d{1,3})/i', $fn, $epM) ||
                preg_match('/\b\d{1,2}x(\d{1,3})\b/i', $fn, $epM) ||
                preg_match('/\b(?:ep|episode|حلقة|ح)\s*(\d{1,3})\b/ui', $fn, $epM) ||
                preg_match('/[._\-\s](\d{1,3})[._\-\s]/', $fn, $epM)) {
                $episodeNum = (int) $epM[1];
            } else {
                $episodeNum = 1;
            }
        } elseif ($episodeNum === null) {
            $episodeNum = 1;
        }
        $epTitle = ! empty($parsed['episode_title']) ? $this->sanitizePathSegment($parsed['episode_title']) : '';
        if (preg_match('/^(episode|ep|part)\s*\d+$/i', $epTitle)) {
            $epTitle = '';
        }

        $dbSeries = null;
        $dbEp = null;
        $dbMovie = null;

        if ($isSeries) {
            $dbSeries = $this->resolveCachedSeries($cleanTitle, $filePath);

            // Strict Arabic Series Structure Preservation
            $normFilePath = str_replace('\\', '/', $filePath);
            $isArabicSeries = false;
            if (stripos($normFilePath, '/Arabic Series/') !== false || stripos($normFilePath, 'Arabic Series') !== false) {
                $isArabicSeries = true;
            } elseif ($dbSeries) {
                $seriesFolder = str_replace('\\', '/', $dbSeries->folder_path ?? '');
                if (stripos($seriesFolder, 'Arabic Series') !== false || ($dbSeries->original_language ?? '') === 'ar') {
                    $isArabicSeries = true;
                } elseif (preg_match('/\p{Arabic}/u', $dbSeries->title ?? '') || preg_match('/\p{Arabic}/u', $cleanTitle)) {
                    $isArabicSeries = true;
                }
            }
            if ($isArabicSeries) {
                $typeDir = 'TV Shows/Arabic Series';
            }
            if ($dbSeries) {
                // Resolve Year for TV Series: "Title (2009 - 2011)" or "Title (2009)"
                $seriesYear = $this->resolveSeriesYearString($dbSeries);
                if (! empty($seriesYear)) {
                    $year = $seriesYear;
                }

                $dbEp = $this->resolveCachedEpisode($dbSeries->id, $seasonNum, $episodeNum, $filePath);
                if ($dbEp && empty($epTitle) && $dbEp->clean_episode_title) {
                    $epTitle = $this->sanitizePathSegment($dbEp->clean_episode_title);
                }
            }
        } else {
            $dbMovie = $this->resolveCachedMovie($cleanTitle, $filePath);
            if ($dbMovie && empty($year) && $dbMovie->release_year) {
                $year = (string) $dbMovie->release_year;
            }
        }

        // Fast resolution detection:
        // 1. Filename parser
        // 2. Database episode/movie resolution (instant <0.1ms)
        // 3. Probing fallback if file exists on disk
        $resTag = $parsed['resolution'] ?? null;
        if (empty($resTag)) {
            if ($isSeries && $dbEp && ! empty($dbEp->resolution)) {
                $resTag = $dbEp->resolution;
                $parsed['resolution'] = $dbEp->resolution;
            } elseif (! $isSeries && $dbMovie && ! empty($dbMovie->resolution)) {
                $resTag = $dbMovie->resolution;
                $parsed['resolution'] = $dbMovie->resolution;
            } elseif (file_exists($filePath)) {
                $probed = Cache::remember('organizer_probe_res_'.md5($filePath), 86400, function () use ($filePath) {
                    return app(FilesystemScannerService::class)->probeResolution($filePath);
                });
                if ($probed) {
                    $resTag = $probed;
                    $parsed['resolution'] = $probed;
                }
            }
        }

        // For movies without resolution anywhere, preserve 1080p fallback. For series, leave empty if unknown.
        if (! $isSeries && empty($resTag)) {
            $resTag = '1080p FHD';
        }

        $firstChar = mb_strtoupper(mb_substr($cleanTitle, 0, 1));
        $firstLetter = preg_match('/^[A-Z0-9]$/i', $firstChar) ? $firstChar : '#';

        // Multi-tier Collection Resolution (Movies only)
        $collName = ! $isSeries ? $this->resolveCollectionName($filePath, $parsed, $file['collection_name'] ?? null) : null;
        if ($collName) {
            $parsed['collection_name'] = $collName;
        }

        // Resolve genres for {Genre} and {Genres} tokens
        $genresList = $file['genres'] ?? [];
        if (empty($genresList)) {
            if ($isSeries && $dbSeries && $dbSeries->genres->isNotEmpty()) {
                $genresList = $dbSeries->genres->pluck('name_en')->toArray();
            } elseif (! $isSeries && $dbMovie && $dbMovie->genres->isNotEmpty()) {
                $genresList = $dbMovie->genres->pluck('name_en')->toArray();
            }
        }

        if (! empty($collName)) {
            // Unify franchise collections under a single consistent primary genre
            $classifier = app(ZeroKeyGenreClassifierService::class);
            $resolved = $classifier->resolveGenres($cleanTitle, $collName);
            $primaryGenre = $this->sanitizePathSegment($resolved['primary']);
            $joinedGenres = $this->sanitizePathSegment($resolved['joined']);
        } elseif (empty($genresList)) {
            $classifier = app(ZeroKeyGenreClassifierService::class);
            $resolved = $classifier->resolveGenres($cleanTitle, null);
            $primaryGenre = $this->sanitizePathSegment($resolved['primary']);
            $joinedGenres = $this->sanitizePathSegment($resolved['joined']);
        } else {
            $primaryGenre = ! empty($genresList[0]) ? $this->sanitizePathSegment($genresList[0]) : 'Action & Adventure';
            $joinedGenres = ! empty($genresList) ? $this->sanitizePathSegment(implode(' & ', array_slice($genresList, 0, 2))) : $primaryGenre;
        }
        $cleanRes = $this->cleanResolutionTag($resTag);

        $tokens = [
            '{Type}' => $typeDir,
            '{Title}' => $cleanTitle,
            '{Year}' => $year,
            '{Collection}' => $collName ? $this->sanitizePathSegment($collName) : '',
            '{Genre}' => $primaryGenre,
            '{Genres}' => $joinedGenres,
            '{Resolution}' => $resTag ? $this->sanitizePathSegment($resTag) : '',
            '{CleanResolution}' => $cleanRes,
            '{ResolutionClean}' => $cleanRes,
            '{Codec}' => $this->sanitizePathSegment(($parsed['codec'] ?? '') ?: 'x264'),
            '{Source}' => $this->sanitizePathSegment($parsed['source'] ?? ''),
            '{Edition}' => $this->sanitizePathSegment($parsed['edition'] ?? ''),
            '{Group}' => $this->sanitizePathSegment($parsed['group'] ?? 'MEDIA'),
            '{FirstLetter}' => $firstLetter,
            '{Season}' => (string) $seasonNum,
            '{Episode}' => (! empty($parsed['episode_end']) && (int) $parsed['episode_end'] > $episodeNum) ? "{$episodeNum}-{$parsed['episode_end']}" : (string) $episodeNum,
            '{Season:02}' => sprintf('%02d', $seasonNum),
            '{Episode:02}' => (! empty($parsed['episode_end']) && (int) $parsed['episode_end'] > $episodeNum) ? sprintf('%02d-E%02d', $episodeNum, (int) $parsed['episode_end']) : sprintf('%02d', $episodeNum),
            '{EpisodeTitle}' => $epTitle,
            '{ext}' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION) ?: ($parsed['extension'] ?? 'mkv')),
        ];

        $relPath = $pattern;
        if (empty($epTitle) || ! empty($file['omit_episode_title'])) {
            $relPath = str_replace([' - {EpisodeTitle}', ' - {EpisodeTitle} ', '{EpisodeTitle}'], '', $relPath);
        }
        // If movie has no collection, cleanly unwrap collection directory segment
        if (empty($collName)) {
            $relPath = str_replace(['/Collections/{Collection}', 'Collections/{Collection}/', 'Collections/{Collection}', '/{Collection}', '{Collection}/', '{Collection}'], '', $relPath);
        }
        // If year is empty, cleanly unwrap year segments to prevent empty () or trailing spaces
        if (empty($year)) {
            $relPath = str_replace([' ({Year})', '({Year})', ' [{Year}]', '[{Year}]', '{Year}'], '', $relPath);
        }
        // If clean resolution is empty, cleanly unwrap resolution segments to prevent empty []
        if (empty($cleanRes)) {
            $relPath = str_replace([' [{CleanResolution}]', '[{CleanResolution}]', ' [{ResolutionClean}]', '[{ResolutionClean}]', ' [{Resolution}]', '[{Resolution}]'], '', $relPath);
        }
        $relPath = str_replace(array_keys($tokens), array_values($tokens), $relPath);

        // Clean double slashes, unknown years, or empty brackets/dangling delimiters
        $relPath = preg_replace('#/+#', '/', $relPath);
        $relPath = preg_replace('/\s+-\s*\[/', ' [', $relPath);
        $relPath = preg_replace('/\s+-\s*\./', '.', $relPath);
        $relPath = str_replace(['(Unknown Year)', '[Unknown Year]', 'Unknown Year', '()', '[]', '( )', '[ ]', ' - .', ' .'], ['', '', '', '', '', '', '', '.', '.'], $relPath);
        $relPath = preg_replace('/\s+\//', '/', $relPath);
        $relPath = preg_replace('/\s+/', ' ', $relPath);
        $relPath = trim($relPath, '/');

        // Sanitize each directory level in relPath
        $segments = explode('/', $relPath);
        $cleanSegments = [];
        foreach ($segments as $idx => $segment) {
            if ($idx === count($segments) - 1) {
                $ext = pathinfo($segment, PATHINFO_EXTENSION);
                $base = pathinfo($segment, PATHINFO_FILENAME);
                $cleanSegments[] = $this->sanitizePathSegment($base).($ext ? ".{$ext}" : '');
            } else {
                $cleanSegments[] = $this->sanitizePathSegment($segment);
            }
        }
        $cleanRelPath = implode('/', $cleanSegments);

        $destination = "{$targetRoot}/{$cleanRelPath}";
        $source = str_replace('\\', '/', $filePath);

        // Collision avoidance: ensure no two files in the same plan share identical destination
        $normDest = strtolower(str_replace('\\', '/', $destination));
        if (isset($this->claimedDestinations[$normDest])) {
            $destDir = pathinfo($destination, PATHINFO_DIRNAME);
            $destBase = pathinfo($destination, PATHINFO_FILENAME);
            $destExt = pathinfo($destination, PATHINFO_EXTENSION);
            $counter = 2;
            do {
                $candidate = "{$destDir}/{$destBase} ({$counter})".($destExt ? ".{$destExt}" : '');
                $normCand = strtolower(str_replace('\\', '/', $candidate));
                $counter++;
            } while (isset($this->claimedDestinations[$normCand]));
            $destination = $candidate;
            $normDest = $normCand;
        }
        $this->claimedDestinations[$normDest] = true;

        $exists = File::exists($destination);
        $isIdentical = strtolower(trim($source)) === strtolower(trim($destination));

        $status = 'ready';
        if ($isIdentical) {
            $status = 'identical';
        } elseif ($exists) {
            $status = 'collision_exists';
        }

        $item = [
            'id' => uniqid('plan_'),
            'source_path' => $source,
            'destination_path' => $destination,
            'filename' => $file['filename'] ?? basename($filePath),
            'clean_title' => $cleanTitle,
            'collection_name' => $collName ?: null,
            'genre' => $primaryGenre,
            'type' => $parsed['type'] ?? ($isSeries ? 'series' : 'movie'),
            'year' => $year ?: ($parsed['year'] ?? null),
            'season' => $isSeries ? $seasonNum : null,
            'episode' => $isSeries ? $episodeNum : null,
            'episode_title' => $epTitle,
            'resolution' => $cleanRes ?: ($parsed['resolution'] ?? null),
            'size_bytes' => $file['size_bytes'] ?? 0,
            'size_formatted' => $file['size_formatted'] ?? '',
            'status' => $status,
            'selected' => $status === 'ready',
            'subtitles' => [],
        ];

        // Subtitle mapping with language preserving and standardization (.ar.srt / .en.srt)
        if (! empty($file['subtitles'])) {
            $destDir = pathinfo($destination, PATHINFO_DIRNAME);
            $destBase = pathinfo($destination, PATHINFO_FILENAME);

            foreach ($file['subtitles'] as $sub) {
                $subPath = $sub['path'] ?? '';
                $subExt = strtolower($sub['extension'] ?? (pathinfo($subPath, PATHINFO_EXTENSION) ?: 'srt'));
                $lang = $sub['language'] ?? 'und';

                // Robust language detection from filename or tags
                $subFilenameLower = strtolower(basename($subPath));
                if ($lang === 'und' || empty($lang)) {
                    if (preg_match('/(\.ar|\barabic\b|\bara\b|_ar\.)/i', $subFilenameLower)) {
                        $lang = 'ar';
                    } elseif (preg_match('/(\.en|\benglish\b|\beng\b|_en\.)/i', $subFilenameLower)) {
                        $lang = 'en';
                    }
                }

                $langSuffix = in_array($lang, ['ar', 'en', 'fr', 'es', 'de']) ? ".{$lang}" : '';

                $subDest = "{$destDir}/{$destBase}{$langSuffix}.{$subExt}";
                $subSource = str_replace('\\', '/', $subPath);

                $item['subtitles'][] = [
                    'source' => $subSource,
                    'destination' => $subDest,
                    'language' => $lang,
                    'exists' => File::exists($subDest),
                ];
            }
        }

        return $item;
    }

    /**
     * Initialize Stateful Batch Plan Generation (Zero 30s-timeout risk)
     */
    /**
     * Get real-time status of the background plan generation job.
     */
    public function getPlanJobStatus(): array
    {
        return Cache::get(self::PLAN_CACHE_KEY, [
            'status' => 'idle',
            'progress_percent' => 0,
            'total_files' => 0,
            'processed_count' => 0,
            'current_file' => null,
            'current_action' => 'Idle',
            'logs' => [],
            'plan_items' => [],
            'started_at' => null,
            'updated_at' => null,
        ]);
    }

    /**
     * Start background plan generation job (Mirroring Virtual Library Scanner).
     */
    public function startPlanJob(
        string $sourcePath,
        string $targetRoot,
        ?string $moviePattern = null,
        ?string $seriesPattern = null,
        string $sourceMode = 'folder',
        bool $recursive = true,
        array $options = []
    ): array {
        $files = $options['files'] ?? null;
        if ($files === null) {
            if ($sourceMode === 'virtual') {
                $movies = MediaItem::with('subtitles')->get()->map(function ($m) {
                    return [
                        'path' => $m->file_path,
                        'filename' => basename($m->file_path),
                        'size_bytes' => $m->file_size_bytes,
                        'size_formatted' => $m->file_size_bytes ? round($m->file_size_bytes / (1024 * 1024 * 1024), 2).' GB' : '1.4 GB',
                        'collection_name' => $m->collection_name,
                        'parsed' => [
                            'type' => 'movie',
                            'title' => $m->title,
                            'clean_title' => $m->title,
                            'year' => $m->release_year,
                            'resolution' => $m->resolution ?? '1080p',
                            'codec' => $m->video_codec ?? 'HEVC',
                            'collection_name' => $m->collection_name,
                        ],
                        'subtitles' => $m->subtitles->map(fn ($s) => ['path' => $s->file_path, 'language' => $s->language])->toArray(),
                    ];
                });

                $episodes = Episode::with(['season.series', 'subtitles'])->get()->map(function ($ep) {
                    return [
                        'path' => $ep->file_path,
                        'filename' => basename($ep->file_path),
                        'size_bytes' => $ep->file_size_bytes,
                        'size_formatted' => $ep->file_size_bytes ? round($ep->file_size_bytes / (1024 * 1024), 1).' MB' : '450 MB',
                        'parsed' => [
                            'type' => 'series',
                            'series_title' => $ep->season?->series?->title ?? 'TV Show',
                            'season' => $ep->season?->season_number ?? 1,
                            'episode' => $ep->episode_number,
                            'resolution' => $ep->resolution ?? '1080p',
                            'codec' => $ep->video_codec ?? 'HEVC',
                        ],
                        'subtitles' => $ep->subtitles->map(fn ($s) => ['path' => $s->file_path, 'language' => $s->language])->toArray(),
                    ];
                });

                $files = $movies->concat($episodes)->values()->filter(fn ($f) => ! empty($f['path']) && file_exists($f['path']))->values()->toArray();
            } else {
                $scanner = app(FilesystemScannerService::class);
                $files = $scanner->scanDirectory($sourcePath, $recursive);
            }
        }

        $total = count($files);

        if ($total === 0) {
            $state = [
                'status' => 'idle',
                'progress_percent' => 0,
                'total_files' => 0,
                'processed_count' => 0,
                'current_file' => null,
                'current_action' => 'No media files found in specified source.',
                'logs' => [
                    [
                        'time' => now()->format('H:i:s'),
                        'level' => 'error',
                        'message' => "No media files found in {$sourcePath}.",
                    ],
                ],
                'plan_items' => [],
                'started_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
            Cache::put(self::PLAN_CACHE_KEY, $state, now()->addHours(6));

            return [
                'success' => false,
                'message' => 'No media files found in the specified source.',
                'status' => $state,
            ];
        }

        $sourceLabel = $sourceMode === 'virtual' ? 'Virtual Library' : basename($sourcePath);

        $state = [
            'status' => 'generating',
            'progress_percent' => 0,
            'total_files' => $total,
            'processed_count' => 0,
            'current_file' => null,
            'current_action' => "Found {$total} media files. Starting organization analysis...",
            'source_path' => $sourcePath,
            'target_root' => $targetRoot,
            'movie_pattern' => $moviePattern,
            'series_pattern' => $seriesPattern,
            'pending_queue' => $files,
            'plan_items' => [],
            'logs' => [
                [
                    'time' => now()->format('H:i:s'),
                    'level' => 'info',
                    'message' => "Discovered {$total} media files in [{$sourceLabel}]. Background organization job started.",
                ],
            ],
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        Cache::put(self::PLAN_CACHE_KEY, $state, now()->addHours(6));

        return [
            'success' => true,
            'is_active' => true,
            'status' => $state,
            'total_files' => $total,
        ];
    }

    /**
     * Backward-compatibility alias for initPlanGeneration.
     */
    public function initPlanGeneration(string $sourcePath, string $targetRoot, ?string $moviePattern = null, ?string $seriesPattern = null, bool $recursive = true, array $options = []): array
    {
        $res = $this->startPlanJob($sourcePath, $targetRoot, $moviePattern, $seriesPattern, 'folder', $recursive, $options);
        $res['is_active'] = ($res['status']['status'] ?? '') === 'generating';
        $res['total_files'] = $res['total_files'] ?? ($res['status']['total_files'] ?? 0);

        return $res;
    }

    /**
     * Process next batch of the background plan job (Matches Virtual Scanner Worker).
     */
    public function processPlanJobBatch(int $batchSize = 15): array
    {
        $state = Cache::get(self::PLAN_CACHE_KEY);

        if (! $state) {
            return [
                'success' => true,
                'has_more' => false,
                'status' => $this->getPlanJobStatus(),
                'plan' => [],
                'total_files' => 0,
                'processed_count' => 0,
                'progress_percent' => 0,
                'is_completed' => false,
            ];
        }

        if (($state['status'] ?? '') === 'paused') {
            return [
                'success' => true,
                'has_more' => true,
                'status' => $state,
                'plan' => $state['plan_items'] ?? [],
                'total_files' => (int) ($state['total_files'] ?? 0),
                'processed_count' => (int) ($state['processed_count'] ?? 0),
                'progress_percent' => $state['progress_percent'] ?? 0,
                'is_completed' => false,
            ];
        }

        if (($state['status'] ?? '') === 'cancelled') {
            return [
                'success' => true,
                'has_more' => false,
                'status' => $state,
                'plan' => $state['plan_items'] ?? [],
                'total_files' => (int) ($state['total_files'] ?? 0),
                'processed_count' => (int) ($state['processed_count'] ?? 0),
                'progress_percent' => $state['progress_percent'] ?? 0,
                'is_completed' => false,
            ];
        }

        if (($state['status'] ?? '') !== 'generating' || empty($state['pending_queue'])) {
            if ($state && ($state['status'] ?? '') === 'generating') {
                $state['status'] = 'completed';
                $state['progress_percent'] = 100;
                $state['current_action'] = 'Organization plan completed successfully.';
                Cache::put(self::PLAN_CACHE_KEY, $state, now()->addHours(6));
            }

            return [
                'success' => true,
                'has_more' => false,
                'status' => $state ?: $this->getPlanJobStatus(),
            ];
        }

        $queue = $state['pending_queue'];
        $batch = array_splice($queue, 0, $batchSize);
        $state['pending_queue'] = $queue;

        $targetRoot = $state['target_root'];
        $moviePattern = $state['movie_pattern'];
        $seriesPattern = $state['series_pattern'];

        foreach ($batch as $file) {
            $filename = $file['filename'] ?? basename($file['path'] ?? '');
            $state['current_file'] = $filename;
            $state['current_action'] = "Analyzing {$filename}...";

            try {
                $item = $this->generatePlanItem($file, $targetRoot, $moviePattern, $seriesPattern);
                $state['plan_items'][] = $item;
                $state['processed_count']++;

                $destRel = basename(dirname($item['destination_path'])).'/'.basename($item['destination_path']);
                $state['logs'][] = [
                    'time' => now()->format('H:i:s'),
                    'level' => 'success',
                    'message' => "Mapped: {$item['clean_title']} ({$item['year']}) [{$item['resolution']}] -> {$destRel}",
                ];
            } catch (\Throwable $e) {
                $state['processed_count']++;
                $state['logs'][] = [
                    'time' => now()->format('H:i:s'),
                    'level' => 'error',
                    'message' => "Error analyzing {$filename}: ".$e->getMessage(),
                ];
            }
        }

        $total = max(1, (int) $state['total_files']);
        $processed = (int) $state['processed_count'];
        $state['progress_percent'] = min(100, (int) round(($processed / $total) * 100));

        if (count($state['logs']) > 150) {
            $state['logs'] = array_slice($state['logs'], -150);
        }

        $isDone = empty($state['pending_queue']) || $processed >= $total;
        if ($isDone) {
            $state['status'] = 'completed';
            $state['progress_percent'] = 100;
            $state['current_action'] = "Plan generated successfully for {$total} media files.";
            $state['logs'][] = [
                'time' => now()->format('H:i:s'),
                'level' => 'info',
                'message' => "Finished generating organization plan for all {$total} files.",
            ];
        }

        $state['updated_at'] = now()->toDateTimeString();

        $latestInCache = Cache::get(self::PLAN_CACHE_KEY);
        if ($latestInCache && ($latestInCache['status'] === 'paused')) {
            $state['status'] = 'paused';
        } elseif ($latestInCache && ($latestInCache['status'] === 'cancelled')) {
            $state['status'] = 'cancelled';
            $state['pending_queue'] = [];
            $isDone = true;
        }

        Cache::put(self::PLAN_CACHE_KEY, $state, now()->addHours(6));

        return [
            'success' => true,
            'has_more' => ! $isDone,
            'status' => $state,
            'plan' => $state['plan_items'] ?? [],
            'total_files' => $total,
            'processed_count' => $processed,
            'progress_percent' => $state['progress_percent'],
            'is_completed' => $isDone,
        ];
    }

    /**
     * Backward-compatibility alias for processPlanBatch.
     */
    public function processPlanBatch(int $batchSize = 25): array
    {
        return $this->processPlanJobBatch($batchSize);
    }

    public function getPlanGenerationStatus(): array
    {
        return $this->getPlanJobStatus();
    }

    public function cancelPlanGeneration(): array
    {
        return $this->cancelPlanJob();
    }

    public function pausePlanJob(): array
    {
        $state = Cache::get(self::PLAN_CACHE_KEY);
        if ($state) {
            $state['status'] = 'paused';
            $state['current_action'] = 'Job paused by user.';
            $state['logs'][] = [
                'time' => now()->format('H:i:s'),
                'level' => 'info',
                'message' => 'Organization analysis paused.',
            ];
            Cache::put(self::PLAN_CACHE_KEY, $state, now()->addHours(6));
        }

        return ['success' => true, 'status' => $state ?: $this->getPlanJobStatus()];
    }

    public function resumePlanJob(): array
    {
        $state = Cache::get(self::PLAN_CACHE_KEY);
        if ($state) {
            $state['status'] = 'generating';
            $state['current_action'] = 'Resuming organization analysis...';
            $state['logs'][] = [
                'time' => now()->format('H:i:s'),
                'level' => 'info',
                'message' => 'Organization analysis resumed.',
            ];
            Cache::put(self::PLAN_CACHE_KEY, $state, now()->addHours(6));
        }

        return ['success' => true, 'status' => $state ?: $this->getPlanJobStatus()];
    }

    public function cancelPlanJob(): array
    {
        $state = Cache::get(self::PLAN_CACHE_KEY);
        if ($state) {
            $state['status'] = 'cancelled';
            $state['current_action'] = 'Job cancelled by user.';
            $state['logs'][] = [
                'time' => now()->format('H:i:s'),
                'level' => 'info',
                'message' => 'Organization analysis cancelled.',
            ];
            Cache::put(self::PLAN_CACHE_KEY, $state, now()->addHours(6));
        }

        return ['success' => true, 'status' => $state ?: $this->getPlanJobStatus(), 'cancelled' => true];
    }

    public function cleanResolutionTag(?string $raw): string
    {
        if (empty($raw)) {
            return '';
        }
        $r = strtolower(trim($raw));
        if (str_contains($r, '4k') || str_contains($r, '2160') || str_contains($r, 'uhd')) {
            return '4K';
        }
        if (str_contains($r, '1440') || str_contains($r, '2k')) {
            return '1440p';
        }
        if (str_contains($r, '1080') || str_contains($r, 'fhd')) {
            return '1080p';
        }
        if (str_contains($r, '720') || str_contains($r, 'hd')) {
            return '720p';
        }
        if (str_contains($r, '576')) {
            return '576p';
        }
        if (str_contains($r, '540')) {
            return '540p';
        }
        if (str_contains($r, '480') || str_contains($r, 'sd')) {
            return '480p';
        }
        if (str_contains($r, '360')) {
            return '360p';
        }
        if (str_contains($r, '240')) {
            return '240p';
        }

        return $raw === '1080p FHD' ? '1080p' : $raw;
    }

    public function sanitizePathSegment(string $name): string
    {
        // Replace colons with hyphen separator
        $name = str_replace(':', ' - ', $name);

        // Strip illegal filesystem characters
        $name = str_replace(['<', '>', '"', '/', '\\', '|', '?', '*'], '', $name);

        // Collapse multiple spaces
        $name = preg_replace('/\s+/', ' ', $name);

        // Windows prohibits trailing dots and spaces in directory and file names
        $name = trim($name, " .\t\n\r\0\x0B");

        return $name ?: 'Unknown';
    }

    /**
     * Initialize Batch Execution (Stateful Queue like MediaScanner)
     */
    public function initExecution(array $plan, string $mode = 'move', bool $cleanupEmptyFolders = true): array
    {
        $selected = array_values(array_filter($plan, function ($item) {
            return ! empty($item['selected']) && ($item['status'] ?? 'ready') !== 'identical';
        }));

        $totalBytes = array_sum(array_column($selected, 'size_bytes'));

        $state = [
            'is_active' => count($selected) > 0,
            'is_completed' => count($selected) === 0,
            'is_cancelled' => false,
            'mode' => $mode,
            'cleanup_empty_folders' => $cleanupEmptyFolders,
            'total_items' => count($selected),
            'total_bytes' => $totalBytes,
            'total_bytes_formatted' => $this->formatBytes($totalBytes),
            'processed_count' => 0,
            'successful_count' => 0,
            'failed_count' => 0,
            'cleaned_folders_count' => 0,
            'current_file' => '',
            'current_destination' => '',
            'current_action' => count($selected) > 0 ? "Preparing {$mode} queue..." : 'No items selected.',
            'progress_percent' => 0,
            'started_at' => date('Y-m-d H:i:s'),
            'logs' => [
                [
                    'time' => date('H:i:s'),
                    'type' => 'info',
                    'message' => "Initialized {$mode} queue with ".count($selected).' files ('.$this->formatBytes($totalBytes).')'.($cleanupEmptyFolders ? ' [Auto-Cleanup Enabled]' : ''),
                ],
            ],
            'queue' => $selected,
            'source_dirs_to_clean' => [],
            'errors' => [],
            'completed_items' => [],
        ];

        Cache::put(self::CACHE_KEY, $state, now()->addHours(2));

        return $state;
    }

    /**
     * Process next batch of items (Non-blocking SSE / Ajax loop)
     */
    public function processNextBatch(int $batchSize = 1): array
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        ignore_user_abort(true);

        $state = Cache::get(self::CACHE_KEY);

        if (! $state || ! empty($state['is_cancelled'])) {
            return [
                'has_more' => false,
                'status' => $state ?: $this->getExecutionStatus(),
            ];
        }

        if (! empty($state['is_paused'])) {
            return [
                'has_more' => true,
                'status' => $state,
            ];
        }

        if (empty($state['is_active']) || empty($state['queue'])) {
            $state['is_active'] = false;
            $state['is_completed'] = true;
            $state['progress_percent'] = 100;
            $state['current_action'] = 'All operations completed!';
            Cache::put(self::CACHE_KEY, $state, now()->addHours(2));

            return [
                'has_more' => false,
                'status' => $state,
            ];
        }

        $queue = $state['queue'];
        $batch = array_splice($queue, 0, $batchSize);
        $mode = $state['mode'] ?? 'move';

        foreach ($batch as $item) {
            $source = $item['source_path'];
            $dest = $item['destination_path'];
            $filename = $item['filename'] ?? basename($source);

            $state['current_file'] = $filename;
            $state['current_destination'] = $dest;
            $state['current_action'] = ucfirst($mode)."ing {$filename}...";

            try {
                // If source doesn't exist, check if it was already moved in a previous attempt
                if (! File::exists($source)) {
                    if (File::exists($dest) && File::size($dest) > 0) {
                        $state['successful_count']++;
                        $state['logs'][] = [
                            'time' => date('H:i:s'),
                            'type' => 'info',
                            'message' => "[ALREADY ORGANIZED] {$filename} is already at destination.",
                        ];
                        $state['completed_items'][] = [
                            'source' => $source,
                            'destination' => $dest,
                            'title' => $item['clean_title'] ?? $filename,
                            'size_formatted' => $item['size_formatted'] ?? '',
                        ];
                        $state['processed_count']++;

                        continue;
                    }

                    $state['failed_count']++;
                    $errMsg = "Source not found: {$filename}";
                    $state['errors'][] = $errMsg;
                    $state['logs'][] = [
                        'time' => date('H:i:s'),
                        'type' => 'error',
                        'message' => "[ERROR] {$errMsg}",
                    ];
                    $state['processed_count']++;

                    continue;
                }

                $destDir = pathinfo($dest, PATHINFO_DIRNAME);
                if (! File::isDirectory($destDir)) {
                    File::makeDirectory($destDir, 0755, true, true);
                }

                $sourceDir = pathinfo($source, PATHINFO_DIRNAME);
                if (! $this->isProtectedDirectory($sourceDir) && ! in_array($sourceDir, $state['source_dirs_to_clean'])) {
                    $state['source_dirs_to_clean'][] = $sourceDir;
                }

                // Execute safe cross-drive move or copy with timeout immunity
                $this->moveOrCopyFile($source, $dest, $mode);
                if ($mode === 'move') {
                    $this->updateDatabasePath($source, $dest, $item['collection_name'] ?? null);
                }

                // Handle Companion Subtitles
                $subsCount = 0;
                if (! empty($item['subtitles'])) {
                    foreach ($item['subtitles'] as $sub) {
                        $subSource = $sub['source'] ?? ($sub['source_path'] ?? '');
                        $subDest = $sub['destination'] ?? ($sub['destination_path'] ?? '');

                        if (! empty($subSource) && ! empty($subDest) && File::exists($subSource)) {
                            $subDestDir = pathinfo($subDest, PATHINFO_DIRNAME);
                            if (! File::isDirectory($subDestDir)) {
                                File::makeDirectory($subDestDir, 0755, true, true);
                            }

                            $subSourceDir = pathinfo($subSource, PATHINFO_DIRNAME);
                            if (! $this->isProtectedDirectory($subSourceDir) && ! in_array($subSourceDir, $state['source_dirs_to_clean'])) {
                                $state['source_dirs_to_clean'][] = $subSourceDir;
                            }

                            $this->moveOrCopyFile($subSource, $subDest, $mode);
                            if ($mode === 'move') {
                                try {
                                    Subtitle::where('file_path', $subSource)->update(['file_path' => $subDest]);
                                } catch (\Throwable $te) {
                                }
                            }
                            $subsCount++;
                        }
                    }
                }

                $state['successful_count']++;
                $state['logs'][] = [
                    'time' => date('H:i:s'),
                    'type' => 'success',
                    'message' => '['.strtoupper($mode)."] {$filename} -> ".basename($dest).($subsCount > 0 ? " (+{$subsCount} subs)" : ''),
                ];
                $state['completed_items'][] = [
                    'source' => $source,
                    'destination' => $dest,
                    'title' => $item['clean_title'] ?? $filename,
                    'size_formatted' => $item['size_formatted'] ?? '',
                ];
            } catch (\Throwable $e) {
                $state['failed_count']++;
                $err = "Failed {$filename}: ".$e->getMessage();
                $state['errors'][] = $err;
                $state['logs'][] = [
                    'time' => date('H:i:s'),
                    'type' => 'error',
                    'message' => "[ERROR] {$err}",
                ];
                Log::error('PhysicalOrganizerService error: '.$e->getMessage());
            }

            $state['processed_count']++;
        }

        $state['queue'] = $queue;
        $total = max(1, $state['total_items']);
        $state['progress_percent'] = min(100, (int) round(($state['processed_count'] / $total) * 100));

        // When queue is empty, perform automatic cleanup of empty folders left behind!
        if (empty($queue)) {
            if ($mode === 'move' && ! empty($state['cleanup_empty_folders'])) {
                $cleanedCount = $this->cleanEmptyDirectories($state['source_dirs_to_clean'], $state['logs']);
                $state['cleaned_folders_count'] = $cleanedCount;
                if ($cleanedCount > 0) {
                    $state['logs'][] = [
                        'time' => date('H:i:s'),
                        'type' => 'success',
                        'message' => "🧹 Cleaned up {$cleanedCount} empty leftover source folder".($cleanedCount > 1 ? 's' : '').'.',
                    ];
                }
            }

            $state['is_active'] = false;
            $state['is_completed'] = true;
            $state['progress_percent'] = 100;
            $state['current_action'] = 'Organizing process complete!';
            $state['logs'][] = [
                'time' => date('H:i:s'),
                'type' => 'info',
                'message' => "Complete: {$state['successful_count']} succeeded, {$state['failed_count']} failed.",
            ];
        }

        Cache::put(self::CACHE_KEY, $state, now()->addHours(2));

        return [
            'has_more' => ! empty($queue),
            'status' => $state,
        ];
    }

    /**
     * Safe cross-drive move or copy with timeout prevention and buffer streaming
     */
    protected function moveOrCopyFile(string $source, string $dest, string $mode = 'move'): bool
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $destDir = pathinfo($dest, PATHINFO_DIRNAME);
        if (! File::isDirectory($destDir)) {
            File::makeDirectory($destDir, 0755, true, true);
        }

        // If source and destination paths are identical, skip
        if (realpath($source) === realpath($dest) && realpath($source) !== false) {
            return true;
        }

        // CRITICAL DATA PROTECTION: Never silently overwrite an existing destination file!
        if (file_exists($dest)) {
            $destDir = pathinfo($dest, PATHINFO_DIRNAME);
            $destBase = pathinfo($dest, PATHINFO_FILENAME);
            $destExt = pathinfo($dest, PATHINFO_EXTENSION);
            $counter = 1;
            do {
                $dest = "{$destDir}/{$destBase} ({$counter})".($destExt ? ".{$destExt}" : '');
                $counter++;
            } while (file_exists($dest));
        }

        if ($mode === 'move') {
            // Attempt fast filesystem rename (instantaneous on same partition)
            try {
                if (@rename($source, $dest)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Cross-device link or locked file
            }

            // Cross-drive copy with 8MB chunks, verification, and source unlink
            $copied = $this->streamCopy($source, $dest);
            if ($copied) {
                @unlink($source);

                return true;
            }

            throw new \RuntimeException("Failed to move file from [{$source}] to [{$dest}].");
        } else {
            $copied = $this->streamCopy($source, $dest);
            if (! $copied) {
                throw new \RuntimeException("Failed to copy file from [{$source}] to [{$dest}].");
            }

            return true;
        }
    }

    /**
     * Copy file using buffered streams with per-chunk timeout resets
     */
    /**
     * Determine if a directory is a protected root or system directory that should NEVER be cleaned or recursed.
     */
    public function isProtectedDirectory(string $dir): bool
    {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        $lower = strtolower($dir);

        // Windows drive letters e.g. C:, D:, C:/, D:/
        if (preg_match('#^[A-Za-z]:/?$#', $dir)) {
            return true;
        }

        // Standard root / system / media directories directly off a drive
        if (preg_match('#^[A-Za-z]:/(downloads|movies|series|entertainment|tv|anime|music|videos|users|program files|windows)$#i', $dir)) {
            return true;
        }

        // Unix system directories
        if (in_array($lower, ['/', '/var', '/usr', '/home', '/etc', '/bin', '/opt', '/tmp'])) {
            return true;
        }

        // Direct user personal folders: C:/Users/{user}/(Downloads|Desktop|Documents|Videos)
        if (preg_match('#^[A-Za-z]:/users/[^/]+/(downloads|desktop|documents|videos|music)$#i', $dir)) {
            return true;
        }

        // Safety: path must have at least 2 directory segments after drive (e.g. D:/Downloads/ReleaseName is OK, but D:/Downloads is NOT)
        $noDrive = preg_replace('#^[A-Za-z]:#', '', $dir);
        $parts = array_filter(explode('/', trim($noDrive, '/')));
        if (count($parts) < 2) {
            return true;
        }

        return false;
    }

    /**
     * Copy file using buffered streams with per-chunk timeout resets
     */
    protected function streamCopy(string $source, string $dest): bool
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        ignore_user_abort(true);

        $srcStream = @fopen($source, 'rb');
        if (! $srcStream) {
            return false;
        }

        $destStream = @fopen($dest, 'wb');
        if (! $destStream) {
            fclose($srcStream);

            return false;
        }

        // Stream in 16MB chunks while keeping timeout refreshed
        $bufferSize = 16 * 1024 * 1024;
        while (! feof($srcStream)) {
            @set_time_limit(180);
            $chunk = fread($srcStream, $bufferSize);
            if ($chunk === false) {
                break;
            }
            fwrite($destStream, $chunk);
        }

        fflush($destStream);
        fclose($srcStream);
        fclose($destStream);

        clearstatcache(true, $source);
        clearstatcache(true, $dest);

        return File::exists($dest) && File::size($dest) === File::size($source);
    }

    protected function cleanEmptyDirectories(array $sourceDirs, array &$logs): int
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        ignore_user_abort(true);

        $videoExtensions = ['mp4', 'mkv', 'avi', 'mov', 'm4v', 'webm', 'ts', 'wmv', 'flv', 'iso'];
        $disposableJunk = [
            'thumbs.db', 'desktop.ini', '.ds_store', 'ehthumbs.db',
            'www.yts.mx.txt', 'www.yts.lt.txt', 'www.yts.bz.txt', 'www.yify-torrents.com.txt',
            'yify.txt', 'torrent-downloaded-from.txt',
        ];
        $disposableExtensions = ['txt', 'nfo', 'url', 'website', 'lnk', 'ini', 'db', 'torrent', 'sample', 'log'];

        $cleanedCount = 0;
        $allDirs = [];

        // 1. Gather all subdirectories recursively inside every source directory
        foreach ($sourceDirs as $sourceDir) {
            $sourceDir = rtrim(str_replace('\\', '/', $sourceDir), '/');
            if ($this->isProtectedDirectory($sourceDir) || ! File::isDirectory($sourceDir)) {
                continue;
            }

            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($iterator as $item) {
                    if ($item->isDir()) {
                        $sub = str_replace('\\', '/', $item->getPathname());
                        if (! $this->isProtectedDirectory($sub)) {
                            $allDirs[] = $sub;
                        }
                    }
                }
            } catch (\Throwable $e) {
            }

            $allDirs[] = $sourceDir;
        }

        $allDirs = array_unique($allDirs);
        // Sort by length descending so child subfolders are evaluated and deleted before parents
        usort($allDirs, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($allDirs as $dir) {
            if ($this->isProtectedDirectory($dir) || ! File::isDirectory($dir)) {
                continue;
            }

            $items = @scandir($dir);
            if ($items === false) {
                continue;
            }

            $entries = array_diff($items, ['.', '..']);
            $hasEssentialFiles = false;

            foreach ($entries as $entry) {
                $fullPath = "{$dir}/{$entry}";
                if (File::isDirectory($fullPath)) {
                    $hasEssentialFiles = true;
                    break;
                }

                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                $nameLower = strtolower($entry);

                if (in_array($ext, $videoExtensions) || in_array($ext, ['srt', 'vtt', 'ass', 'sub'])) {
                    $hasEssentialFiles = true;
                    break;
                }

                $isJunk = in_array($nameLower, $disposableJunk)
                    || in_array($ext, $disposableExtensions)
                    || (in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) && (str_contains($nameLower, 'yts') || str_contains($nameLower, 'poster') || str_contains($nameLower, 'cover') || str_contains($nameLower, 'banner') || @filesize($fullPath) < 500000));

                if (! $isJunk) {
                    $hasEssentialFiles = true;
                    break;
                }
            }

            if (! $hasEssentialFiles) {
                foreach ($entries as $entry) {
                    $fullPath = "{$dir}/{$entry}";
                    if (File::isFile($fullPath)) {
                        @unlink($fullPath);
                    }
                }

                if (@rmdir($dir) || ! File::isDirectory($dir)) {
                    $cleanedCount++;
                    $logs[] = [
                        'time' => date('H:i:s'),
                        'type' => 'info',
                        'message' => '[CLEANUP] 🧹 Removed empty leftover folder: '.basename($dir),
                    ];
                }
            }
        }

        return $cleanedCount;
    }

    public function getExecutionStatus(): array
    {
        $state = Cache::get(self::CACHE_KEY);

        if (! $state) {
            return [
                'is_active' => false,
                'is_completed' => false,
                'is_cancelled' => false,
                'total_items' => 0,
                'processed_count' => 0,
                'successful_count' => 0,
                'failed_count' => 0,
                'cleaned_folders_count' => 0,
                'progress_percent' => 0,
                'current_action' => 'Idle',
                'current_file' => '',
                'logs' => [],
                'errors' => [],
                'completed_items' => [],
            ];
        }

        return $state;
    }

    /**
     * Cancel ongoing operation
     */
    /**
     * Pause ongoing execution
     */
    public function pauseExecution(): array
    {
        $state = Cache::get(self::CACHE_KEY);
        if ($state) {
            $state['is_paused'] = true;
            $state['current_action'] = 'Execution paused by user.';
            $state['logs'][] = [
                'time' => date('H:i:s'),
                'type' => 'warning',
                'message' => 'Execution paused by user.',
            ];
            Cache::put(self::CACHE_KEY, $state, now()->addHours(2));
        }

        return $this->getExecutionStatus();
    }

    /**
     * Resume ongoing execution
     */
    public function resumeExecution(): array
    {
        $state = Cache::get(self::CACHE_KEY);
        if ($state) {
            $state['is_paused'] = false;
            $state['current_action'] = 'Resuming execution...';
            $state['logs'][] = [
                'time' => date('H:i:s'),
                'type' => 'info',
                'message' => 'Execution resumed.',
            ];
            Cache::put(self::CACHE_KEY, $state, now()->addHours(2));
        }

        return $this->getExecutionStatus();
    }

    public function cancelExecution(): array
    {
        $state = Cache::get(self::CACHE_KEY);
        if ($state) {
            $state['is_active'] = false;
            $state['is_cancelled'] = true;
            $state['queue'] = [];
            $state['current_action'] = 'Operation cancelled by user.';
            $state['logs'][] = [
                'time' => date('H:i:s'),
                'type' => 'warning',
                'message' => 'Operation was cancelled by user.',
            ];
            Cache::put(self::CACHE_KEY, $state, now()->addHours(2));
        }

        return $this->getExecutionStatus();
    }

    /**
     * Legacy synchronous execution (for CLI / tests)
     */
    public function execute(array $plan, string $mode = 'move', bool $cleanup = true): array
    {
        $this->initExecution($plan, $mode, $cleanup);
        while (true) {
            $res = $this->processNextBatch(5);
            if (! $res['has_more']) {
                break;
            }
        }
        $status = $this->getExecutionStatus();

        return [
            'success' => $status['failed_count'] === 0,
            'processed' => $status['processed_count'],
            'failed' => $status['failed_count'],
            'cleaned_folders' => $status['cleaned_folders_count'] ?? 0,
            'errors' => $status['errors'],
        ];
    }

    protected function updateDatabasePath(string $oldPath, string $newPath, ?string $collectionName = null): void
    {
        try {
            $normOld = str_replace('\\', '/', $oldPath);
            $normNew = str_replace('\\', '/', $newPath);
            $newFolder = pathinfo($normNew, PATHINFO_DIRNAME);

            $updateData = [
                'file_path' => $normNew,
                'folder_path' => $newFolder,
            ];
            if ($collectionName !== null) {
                $updateData['collection_name'] = $collectionName ?: null;
            }

            MediaItem::where('file_path', $oldPath)
                ->orWhere('file_path', $normOld)
                ->update($updateData);

            Episode::where('file_path', $oldPath)
                ->orWhere('file_path', $normOld)
                ->update([
                    'file_path' => $normNew,
                ]);

            $episode = Episode::where('file_path', $normNew)->first();

            if ($episode && $episode->series_id) {
                // Also update parent Series folder_path if moved
                $series = Series::find($episode->series_id);
                if ($series) {
                    $epDir = dirname($normNew);
                    $epDirBase = strtolower(basename($epDir));
                    $seriesFolder = preg_match('/^(season\s*\d+|s\d+|specials?)$/i', $epDirBase) ? dirname($epDir) : $epDir;
                    if ($series->folder_path !== $seriesFolder) {
                        $series->update(['folder_path' => $seriesFolder]);
                    }
                }
            }

            // Synchronize any embedded subtitles attached to this video file to its new location
            $embeddedSubs = Subtitle::where('file_path', 'LIKE', "embedded:%:{$normOld}")
                ->orWhere('file_path', 'LIKE', "embedded:%:{$oldPath}")
                ->get();

            foreach ($embeddedSubs as $es) {
                if (preg_match('/^embedded:(\d+):/i', $es->file_path, $m)) {
                    $streamIdx = $m[1];
                    $es->update(['file_path' => "embedded:{$streamIdx}:{$normNew}"]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Could not update DB paths for {$oldPath}: ".$e->getMessage());
        }
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824 * 1024) {
            return round($bytes / (1073741824 * 1024), 2).' TB';
        }
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}
