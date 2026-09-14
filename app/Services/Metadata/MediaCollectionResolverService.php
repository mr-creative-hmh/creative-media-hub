<?php

namespace App\Services\Metadata;

use App\Models\MediaItem;
use Illuminate\Support\Facades\Log;

class MediaCollectionResolverService
{
    protected LibraryMasterIndexService $masterIndex;

    protected TmdbProvider $tmdb;

    protected WikipediaProvider $wikipedia;

    protected MetadataAggregator $aggregator;

    protected static array $runtimeCache = [];

    protected array $knownFranchises = [
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
        'Final Destination' => 'Final Destination Collection',
        'Addams Family' => 'The Addams Family Collection',
        'The Addams Family' => 'The Addams Family Collection',
        'Lethal Weapon' => 'Lethal Weapon Collection',
        'Beverly Hills Cop' => 'Beverly Hills Cop Collection',
        'Nightmare on Elm Street' => 'A Nightmare on Elm Street Collection',
        'Friday the 13th' => 'Friday the 13th Collection',
        'Halloween' => 'Halloween Collection',
        'Child\'s Play' => 'Child\'s Play Collection',
        'Chucky' => 'Child\'s Play Collection',
        'Underworld' => 'Underworld Collection',
        'Resident Evil' => 'Resident Evil Collection',
        'The Purge' => 'The Purge Collection',
        'Gladiator' => 'Gladiator Collection',
        'Ghostbusters' => 'Ghostbusters Collection',
        'Knives Out' => 'Knives Out Collection',
        'Rush Hour' => 'Rush Hour Collection',
        'Blade Runner' => 'Blade Runner Collection',
        'Dune' => 'Dune Collection',
        'MonsterVerse' => 'MonsterVerse Collection',
        'Godzilla' => 'MonsterVerse Collection',
        'King Kong' => 'MonsterVerse Collection',
        'Back to the Future' => 'Back to the Future Trilogy',
        'Ip Man' => 'Ip Man Collection',
        'Rocky' => 'Rocky & Creed Collection',
        'Creed' => 'Rocky & Creed Collection',
        'The Mummy' => 'The Mummy Collection',
        'Scream' => 'Scream Collection',
        'Kingsman' => 'Kingsman Collection',
        'Fifty Shades' => 'Fifty Shades Collection',
        'Hotel Transylvania' => 'Hotel Transylvania Collection',
        'Unbreakable' => 'Unbreakable Collection',
        'Split' => 'Unbreakable Collection',
        'Glass' => 'Unbreakable Collection',
        'Batman Begins' => 'The Dark Knight Collection',
        'The Dark Knight Rises' => 'The Dark Knight Collection',
        'Logan' => 'The Wolverine Collection',
        'The Wolverine' => 'The Wolverine Collection',
        'Glass Onion' => 'Knives Out Collection',
        'Shaun of the Dead' => 'Three Flavours Cornetto Collection',
        'Hot Fuzz' => 'Three Flavours Cornetto Collection',
        'The World\'s End' => 'Three Flavours Cornetto Collection',
    ];

    public function __construct(
        LibraryMasterIndexService $masterIndex,
        TmdbProvider $tmdb,
        WikipediaProvider $wikipedia,
        MetadataAggregator $aggregator
    ) {
        $this->masterIndex = $masterIndex;
        $this->tmdb = $tmdb;
        $this->wikipedia = $wikipedia;
        $this->aggregator = $aggregator;
    }

    /**
     * Resolve movie collection using the 4-tier engine:
     * Tier 1: Local Master Index & Database Sibling Stem Match (<0.1ms)
     * Tier 2: Sibling Batch Co-Occurrence Detection (for flat folders e.g. Downloads/)
     * Tier 3: Multi-Source Online Waterfall (TMDb details, TMDb collection search, Wikipedia, OMDb)
     * Tier 4: Directory / Path Analysis & Known Franchise Dictionary
     */
    public function resolveCollection(
        string $filePath,
        array $parsed,
        ?string $explicitColl = null,
        bool $allowOnline = true,
        array $siblingBatchFiles = []
    ): ?array {
        $isSeries = ($parsed['type'] ?? 'movie') === 'series';
        if ($isSeries) {
            return null;
        }

        $cleanTitle = $parsed['clean_title'] ?? ($parsed['title'] ?? '');
        if (empty($cleanTitle)) {
            return null;
        }

        // The Lord of the Rings: The Rings of Power is a TV series, never match movie collections
        if (stripos($cleanTitle, 'Rings of Power') !== false || stripos($filePath, 'Rings of Power') !== false) {
            return null;
        }

        // 0. Explicit Collection Name
        if (! empty($explicitColl)) {
            return $this->buildResult($this->formatCollectionName($explicitColl), null, null, 'explicit');
        }

        $cacheKey = strtolower(trim($cleanTitle).'_'.($parsed['year'] ?? ''));
        if (isset(self::$runtimeCache[$cacheKey])) {
            return self::$runtimeCache[$cacheKey];
        }

        $year = ! empty($parsed['year']) ? (int) $parsed['year'] : null;
        $tmdbId = ! empty($parsed['tmdb_id']) ? (int) $parsed['tmdb_id'] : null;
        $stem = $this->extractFranchiseStem($cleanTitle);

        // =========================================================================
        // TIER 1: Local Master Index & Indexed Library Database (<0.1ms)
        // =========================================================================

        // 1A. Master Metadata Index Lookup
        try {
            $masterMovie = $this->masterIndex->lookupMovie($cleanTitle, $year, $tmdbId);
            if ($masterMovie && ! empty($masterMovie['collection_name'])) {
                $res = $this->buildResult(
                    $this->formatCollectionName($masterMovie['collection_name']),
                    $masterMovie['collection_id'] ?? null,
                    $masterMovie['collection_poster'] ?? null,
                    'master_index'
                );

                return self::$runtimeCache[$cacheKey] = $res;
            }
        } catch (\Throwable $e) {
        }

        // 1B. Local Database Sibling & Stem Match
        try {
            if (strlen($stem) >= 3) {
                $candidates = MediaItem::whereNotNull('collection_name')
                    ->where('collection_name', '!=', '')
                    ->where(function ($q) use ($cleanTitle, $stem) {
                        $q->where('title', '=', $cleanTitle)
                            ->orWhere('original_title', '=', $cleanTitle)
                            ->orWhere('title', 'like', "{$stem}%")
                            ->orWhere('original_title', 'like', "{$stem}%");
                    })
                    ->orderByRaw('CASE WHEN title = ? THEN 0 WHEN release_year = ? THEN 1 WHEN title LIKE ? THEN 2 ELSE 3 END', [
                        $cleanTitle,
                        $year ?? 0,
                        "{$stem}%",
                    ])
                    ->limit(10)
                    ->get();

                foreach ($candidates as $cand) {
                    if (empty($cand->collection_name)) {
                        continue;
                    }

                    // Direct title equality
                    if (strcasecmp($cand->title, $cleanTitle) === 0 || strcasecmp($cand->original_title ?? '', $cleanTitle) === 0) {
                        $res = $this->buildResult(
                            $this->formatCollectionName($cand->collection_name),
                            $cand->collection_id,
                            $cand->collection_poster,
                            'db_sibling'
                        );

                        return self::$runtimeCache[$cacheKey] = $res;
                    }

                    // Strict Stem equality
                    $candStem = $this->extractFranchiseStem($cand->title);
                    if (strcasecmp($candStem, $stem) === 0) {
                        $res = $this->buildResult(
                            $this->formatCollectionName($cand->collection_name),
                            $cand->collection_id,
                            $cand->collection_poster,
                            'db_sibling'
                        );

                        return self::$runtimeCache[$cacheKey] = $res;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        // 1C. Extended Collections Registry
        try {
            $extended = $this->masterIndex->getAllExtendedCollections();
            foreach ($extended as $col) {
                $colName = $col['name'] ?? '';
                $cleanColName = trim(preg_replace('/\s+(?:Collection|Trilogy|Boxset|Saga|Series)$/i', '', $colName));
                $colStem = $this->extractFranchiseStem($cleanColName);

                if (strcasecmp($colStem, $stem) === 0 || strcasecmp($cleanColName, $cleanTitle) === 0) {
                    $res = $this->buildResult(
                        $this->formatCollectionName($colName),
                        $col['collection_id'] ?? ($col['id'] ?? null),
                        $col['poster_path'] ?? null,
                        'master_extended'
                    );

                    return self::$runtimeCache[$cacheKey] = $res;
                }
            }
        } catch (\Throwable $e) {
        }

        // =========================================================================
        // TIER 2: Sibling Batch Co-Occurrence (For Flat Folders e.g. Downloads/)
        // =========================================================================
        if (! empty($siblingBatchFiles)) {
            $batchResult = $this->detectBatchCoOccurrence($cleanTitle, $stem, $siblingBatchFiles, $allowOnline);
            if ($batchResult) {
                return self::$runtimeCache[$cacheKey] = $batchResult;
            }
        }

        // =========================================================================
        // TIER 3: Multi-Source Online Metadata Waterfall
        // =========================================================================
        if ($allowOnline) {
            $onlineResult = $this->resolveOnlineMultiSource($cleanTitle, $stem, $year, $tmdbId);
            if ($onlineResult) {
                return self::$runtimeCache[$cacheKey] = $onlineResult;
            }
        }

        // =========================================================================
        // TIER 4: Path Heuristics & Known Franchise Dictionary
        // =========================================================================

        // 4A. Directory / Path Analysis (parent + ancestor levels)
        $dirColl = $this->detectCollectionFromPath($filePath);
        if ($dirColl) {
            $res = $this->buildResult($this->formatCollectionName($dirColl), null, null, 'path_heuristics');

            return self::$runtimeCache[$cacheKey] = $res;
        }

        // 4B. Known Franchise Dictionary
        $known = $this->lookupKnownFranchise($cleanTitle, $filePath);
        if ($known) {
            $res = $this->buildResult($this->formatCollectionName($known), null, null, 'known_dictionary');

            return self::$runtimeCache[$cacheKey] = $res;
        }

        // 4C. Scene Parser Detected Collection Name
        if (! empty($parsed['collection_name'])) {
            $cleaned = $this->cleanCollectionDirectoryName($parsed['collection_name']);
            if (strlen($cleaned) >= 2) {
                $res = $this->buildResult($this->formatCollectionName($cleaned), null, null, 'parsed_scene');

                return self::$runtimeCache[$cacheKey] = $res;
            }
        }

        return self::$runtimeCache[$cacheKey] = null;
    }

    /**
     * Extract clean franchise stem from title.
     * Strips sequel numbers (1, 2, 3), Roman numerals (I, II, III), Part/Vol/Chapter, and colon subtitles.
     */
    public function extractFranchiseStem(string $title): string
    {
        // Strip subtitle colon/dash if preceded by a meaningful prefix
        $stem = preg_replace('/\s*[:\-–].*$/', '', $title);
        // Strip trailing numeral or Part tag (e.g. "Final Destination 2" -> "Final Destination")
        $stem = preg_replace('/\s+(?:\d+|[IVXLCDM]+|Part\s*\d+|Vol(?:ume)?\s*\d+|Chapter\s*\d+|الجزء\s*[\d\p{Arabic}]+)$/ui', '', $stem);

        return trim($stem);
    }

    /**
     * Sibling Batch Co-Occurrence: Detects collections when all files are in a flat folder.
     */
    protected function detectBatchCoOccurrence(string $cleanTitle, string $stem, array $siblingFiles, bool $allowOnline): ?array
    {
        if (strlen($stem) < 3) {
            return null;
        }

        $stemLower = strtolower($stem);
        $matches = [];

        foreach ($siblingFiles as $file) {
            $fTitle = $file['parsed']['clean_title'] ?? ($file['parsed']['title'] ?? ($file['filename'] ?? ''));
            $fStem = strtolower($this->extractFranchiseStem($fTitle));
            if ($fStem === $stemLower || str_starts_with($fStem, $stemLower) || str_starts_with($stemLower, $fStem)) {
                $matches[] = $file;
            }
        }

        // If 2 or more files in the current scan batch share the franchise stem
        if (count($matches) >= 2) {
            // First check if an online collection exists for this stem
            if ($allowOnline) {
                $online = $this->searchOnlineCollectionByStem($stem);
                if ($online) {
                    return $this->buildResult($online['name'], $online['id'] ?? null, $online['poster'] ?? null, 'batch_cooccurrence_online');
                }
            }

            return $this->buildResult($this->formatCollectionName($stem.' Collection'), null, null, 'batch_cooccurrence');
        }

        return null;
    }

    /**
     * Multi-Source Online Waterfall: TMDb, Wikipedia, OMDb.
     */
    protected function resolveOnlineMultiSource(string $cleanTitle, string $stem, ?int $year, ?int $tmdbId = null): ?array
    {
        $hasSequelMarker = ($stem !== $cleanTitle && strlen($stem) >= 3);

        // 1. Direct TMDb Details Lookup (if tmdb_id is provided)
        if ($tmdbId) {
            try {
                $details = $this->tmdb->getMovieDetails($tmdbId);
                if (! empty($details['collection_name'])) {
                    return $this->buildResult(
                        $this->formatCollectionName($details['collection_name']),
                        $details['collection_id'] ?? null,
                        $details['collection_poster'] ?? null,
                        'tmdb_movie_details'
                    );
                }
                // If TMDb explicitly returned details and movie is not a numbered sequel, it is standalone
                if (! $hasSequelMarker) {
                    return null;
                }
            } catch (\Throwable $e) {
            }
        }

        // 2. TMDb Movie Search + Details
        try {
            $results = $this->tmdb->searchMovie($cleanTitle, $year);
            if (empty($results) && $hasSequelMarker) {
                $results = $this->tmdb->searchMovie($stem, $year);
            }

            if (! empty($results)) {
                $chosen = null;
                if ($year) {
                    foreach ($results as $r) {
                        if (! empty($r['release_year']) && abs((int) $r['release_year'] - $year) <= 1) {
                            $chosen = $r;
                            break;
                        }
                    }
                }
                if (! $chosen) {
                    $chosen = $results[0];
                }

                $details = $this->tmdb->getMovieDetails($chosen['id']);
                if (! empty($details['collection_name'])) {
                    return $this->buildResult(
                        $this->formatCollectionName($details['collection_name']),
                        $details['collection_id'] ?? null,
                        $details['collection_poster'] ?? null,
                        'tmdb_movie_details'
                    );
                }

                // If found on TMDb and not a numbered sequel, it is standalone
                if (! $hasSequelMarker) {
                    return null;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('MediaCollectionResolver TMDb movie lookup failed: '.$e->getMessage());
        }

        // 3. Fallback for Numbered Sequels (only if title indicates a sequel)
        if ($hasSequelMarker) {
            $colSearch = $this->searchOnlineCollectionByStem($stem);
            if ($colSearch) {
                return $this->buildResult($colSearch['name'], $colSearch['id'] ?? null, $colSearch['poster'] ?? null, 'tmdb_collection_search');
            }

            $inferred = TmdbProvider::inferCollectionFromTitle($stem);
            if ($inferred) {
                return $this->buildResult($this->formatCollectionName($inferred), null, null, 'tmdb_inferred');
            }

            try {
                $wikiColl = $this->wikipedia->searchFranchise($stem);
                if ($wikiColl) {
                    return $this->buildResult($this->formatCollectionName($wikiColl), null, null, 'wikipedia_franchise');
                }
            } catch (\Throwable $e) {
                Log::warning('MediaCollectionResolver Wikipedia franchise lookup failed: '.$e->getMessage());
            }
        }

        return null;
    }

    /**
     * Search TMDb collection endpoint by stem.
     */
    protected function searchOnlineCollectionByStem(string $stem): ?array
    {
        try {
            $collections = $this->tmdb->searchCollection($stem);
            if (! empty($collections[0]['name'])) {
                return [
                    'name' => $this->formatCollectionName($collections[0]['name']),
                    'id' => $collections[0]['collection_id'] ?? null,
                    'poster' => $collections[0]['poster_path'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /**
     * Detect collection from directory and ancestor paths.
     */
    public function detectCollectionFromPath(string $filePath): ?string
    {
        $normalizedPath = str_replace(['\\', '/'], '/', $filePath);
        $pathParts = explode('/', $normalizedPath);
        $parentDirs = array_slice($pathParts, max(0, count($pathParts) - 5), -1);

        $terms = 'Collection|Boxset|Trilogy|Quadrilogy|Pentalogy|Hexalogy|Heptalogy|Octalogy|Ennealogy|Decalogy|Anthology|Saga|Franchise|Series|Duology|Tetralogy|سلسلة|أفلام|مجموعة';

        foreach (array_reverse($parentDirs) as $dir) {
            if (preg_match('/^([a-zA-Z0-9\s\':\-\.]+?)\s+(?:'.$terms.')\b/iu', $dir, $m)) {
                $cleanFranchise = $this->cleanCollectionDirectoryName(trim($m[1]));
                if (strlen($cleanFranchise) >= 2) {
                    return $cleanFranchise.' Collection';
                }
            }
            if (preg_match('/\b([a-zA-Z0-9\s\':\-\.]+?)\s+Collection\b/i', $dir, $m)) {
                $cleanFranchise = $this->cleanCollectionDirectoryName(trim($m[1]));
                if (strlen($cleanFranchise) >= 2) {
                    return $cleanFranchise.' Collection';
                }
            }
        }

        return null;
    }

    /**
     * Clean directory names by stripping number ranges (1-5, Part 1-5), year spans (2000-2011), and tags.
     */
    public function cleanCollectionDirectoryName(string $dir): string
    {
        $dir = preg_replace('/\b(?:Part\s*)?\d+\s*[-–]\s*\d+\b/i', '', $dir);
        $dir = preg_replace('/\b\d{4}\s*[-–]\s*\d{4}\b/', '', $dir);
        $dir = preg_replace('/\b(?:1080p|720p|2160p|4k|bluray|web-dl|webrip|remux|hdr|dvdrip|x264|x265|hevc)\b/i', '', $dir);

        return trim(preg_replace('/\s+/', ' ', $dir));
    }

    protected function lookupKnownFranchise(string $cleanTitle, string $filePath): ?string
    {
        foreach ($this->knownFranchises as $frag => $fullColl) {
            $pattern = '/\b'.preg_quote($frag, '/').'\b/iu';
            if (preg_match($pattern, $cleanTitle) || preg_match($pattern, $filePath)) {
                return $fullColl;
            }
        }

        return null;
    }

    public function formatCollectionName(string $name): string
    {
        $name = trim($name);

        // Normalize common alias variations to canonical franchise names
        if (preg_match('/^(?:The\s+)?Fast and (?:the\s+)?Furious(?:\s+Collection)?$/i', $name)) {
            return 'Fast & Furious Collection';
        }

        if (! preg_match('/collection$/i', $name)) {
            $name .= ' Collection';
        }

        return $name;
    }

    protected function buildResult(string $name, ?int $id, ?string $poster, string $source): array
    {
        $formatted = $this->formatCollectionName($name);

        return [
            'name' => $formatted,
            'collection_name' => $formatted,
            'id' => $id,
            'collection_id' => $id,
            'poster' => $poster,
            'collection_poster' => $poster,
            'source' => $source,
        ];
    }
}
