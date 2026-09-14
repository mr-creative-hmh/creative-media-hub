<?php

namespace App\Console\Commands;

use App\Http\Controllers\CollectionController;
use App\Models\DownloadItem;
use App\Models\MediaItem;
use App\Models\Subtitle;
use App\Services\Organizer\ZeroKeyGenreClassifierService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AuditAndOrganizePhysicalMoviesCommand extends Command
{
    protected $signature = 'library:audit-physical-movies 
                            {--fix : Execute the physical reorganization and update database} 
                            {--dry-run : Only preview what would be changed}';

    protected $description = 'Audits and reorganizes physical movie folders into canonical TMDB genres and consolidated collections, syncing database records.';

    protected ZeroKeyGenreClassifierService $classifier;

    public function __construct(ZeroKeyGenreClassifierService $classifier)
    {
        parent::__construct();
        $this->classifier = $classifier;
    }

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('=== Physical Movie Library Audit & Canonical Reorganization ===');
        if ($dryRun || ! $fix) {
            $this->comment('Mode: DRY RUN / PREVIEW (No files will be moved)');
        } else {
            $this->comment('Mode: FIX (Moving physical folders and syncing database)');
        }

        $movieRoots = ['H:/Entertainment/Movies'];
        $existingRoots = array_filter($movieRoots, 'is_dir');

        if (empty($existingRoots)) {
            $this->error('No valid movie library roots found.');

            return Command::FAILURE;
        }

        $activeDownloadDirs = $this->getActiveDownloadDirs();
        if (! empty($activeDownloadDirs)) {
            $this->warn('Protected Active Downloads (will not be touched):');
            foreach ($activeDownloadDirs as $d) {
                $this->line("  * {$d}");
            }
        }

        $plan = $this->buildReorganizationPlan($existingRoots, $activeDownloadDirs);

        $this->displayPlanSummary($plan);

        if ($fix && ! $dryRun) {
            $this->executeReorganization($plan, $existingRoots);
            CollectionController::clearCache();
        } else {
            $this->newLine();
            $this->comment('To execute these moves, run with: php artisan library:audit-physical-movies --fix');
        }

        $this->newLine();
        $this->info('=== Audit Complete ===');

        return Command::SUCCESS;
    }

    /**
     * Get active download directories to protect them from being moved.
     */
    protected function getActiveDownloadDirs(): array
    {
        $activeItems = DownloadItem::whereIn('status', ['downloading', 'verifying', 'organizing', 'scanning'])->get();
        $dirs = [];

        foreach ($activeItems as $item) {
            if ($item->destination_folder) {
                $dirs[] = rtrim(str_replace('\\', '/', $item->destination_folder), '/');
            }
            if (! empty($item->download_metadata['save_path'])) {
                $dirs[] = rtrim(str_replace('\\', '/', $item->download_metadata['save_path']), '/');
            }
        }

        return array_unique(array_filter($dirs));
    }

    /**
     * Build reorganization plan comparing current physical location with canonical destination.
     */
    protected function buildReorganizationPlan(array $roots, array $protectedDirs): array
    {
        $plan = [];

        $movies = MediaItem::whereNotNull('file_path')->get();
        $collectionDominantGenres = $this->preResolveCollectionGenres($movies);

        foreach ($movies as $movie) {
            if (! $movie->file_path || ! file_exists($movie->file_path)) {
                continue;
            }

            $normPath = str_replace('\\', '/', $movie->file_path);
            $matchedRoot = null;

            foreach ($roots as $r) {
                if (str_starts_with($normPath, $r)) {
                    $matchedRoot = $r;
                    break;
                }
            }

            if (! $matchedRoot) {
                continue;
            }

            // Current movie directory
            $currentDir = str_replace('\\', '/', dirname($normPath));

            // Check if directory is protected
            $isProtected = false;
            foreach ($protectedDirs as $pd) {
                if (str_starts_with($currentDir, $pd) || str_starts_with($pd, $currentDir)) {
                    $isProtected = true;
                    break;
                }
            }

            if ($isProtected) {
                continue;
            }

            // Parse relative path inside movie root
            $rel = substr($normPath, strlen($matchedRoot) + 1);
            $parts = explode('/', $rel);

            if (count($parts) < 2) {
                continue;
            }

            $currentGenre = $parts[0];
            $movieFolderName = basename($currentDir);

            // Determine target collection folder
            $targetCollection = null;
            if (! empty($movie->collection_name)) {
                $targetCollection = $this->normalizeCollectionFolderName($movie->collection_name);
            } elseif (count($parts) >= 4) {
                // Currently in a collection subfolder on disk
                $targetCollection = $this->normalizeCollectionFolderName($parts[1]);
            }

            // Determine dominant canonical genre: Arabic > Indian > Animation > Official TMDB Genres
            // If in a collection, the entire collection shares a unified dominant genre!
            if ($targetCollection && isset($collectionDominantGenres[$targetCollection])) {
                $dominant = $collectionDominantGenres[$targetCollection];
            } else {
                $genres = $movie->genres()->pluck('name_en')->toArray();
                $meta = [
                    'language' => $movie->original_language,
                    'original_language' => $movie->original_language,
                    'original_title' => $movie->original_title,
                    'title_ar' => $movie->title_ar,
                    'origin_country' => $movie->origin_country,
                ];
                $dominant = $this->classifier->resolveDominantGenre($movie->title, $movie->collection_name, $genres, $meta);
            }

            // Build canonical target directory
            if ($targetCollection) {
                $targetDir = "{$matchedRoot}/{$dominant}/{$targetCollection}/{$movieFolderName}";
            } else {
                $targetDir = "{$matchedRoot}/{$dominant}/{$movieFolderName}";
            }

            if (rtrim($currentDir, '/') !== rtrim($targetDir, '/')) {
                // Categorize move
                $category = 'dominant_genre_reclassification';
                if ($dominant === 'Animation' && $currentGenre !== 'Animation') {
                    $category = 'animation_consolidation';
                } elseif (in_array($currentGenre, ['Crime & Mystery', 'Romantic', 'Sci-Fi'])) {
                    $category = 'legacy_folder_cleanup';
                } elseif ($targetCollection && ! str_contains($currentDir, $targetCollection)) {
                    $category = 'franchise_nesting';
                }

                $plan[] = [
                    'movie' => $movie,
                    'current_dir' => $currentDir,
                    'target_dir' => $targetDir,
                    'current_genre' => $currentGenre,
                    'dominant_genre' => $dominant,
                    'collection' => $targetCollection,
                    'category' => $category,
                ];
            }
        }

        return $plan;
    }

    /**
     * Clean and format canonical collection folder name.
     */
    protected function normalizeCollectionFolderName(string $rawName): string
    {
        $clean = str_replace([':', ' - ', ' – ', ' / ', '/'], ' ', $rawName);
        $clean = trim(preg_replace('[/\\\\:*?"<>|]', '', $clean));
        $clean = preg_replace('/\s+/', ' ', $clean);

        if (! preg_match('/(collection|trilogy|saga|anthology|boxset)$/i', $clean)) {
            $clean .= ' Collection';
        }

        return $clean;
    }

    /**
     * Pre-resolve unified dominant genres for collections to ensure 100% cohesion.
     */
    protected function preResolveCollectionGenres($movies): array
    {
        $map = [];
        $franchiseMap = $this->classifier->getFranchiseGenreMap();

        $groups = $movies->filter(function ($m) {
            return ! empty($m->collection_name);
        })->groupBy(function ($m) {
            return $this->normalizeCollectionFolderName($m->collection_name);
        });

        foreach ($groups as $normCol => $items) {
            $rawCol = $items->first()->collection_name;
            $cleanCol = $this->classifier->cleanFranchiseName($rawCol);

            // 1. Direct franchise map lookup
            $matched = null;
            foreach ($franchiseMap as $fName => $g) {
                if (strcasecmp($cleanCol, $this->classifier->cleanFranchiseName($fName)) === 0) {
                    $matched = $this->classifier->normalizeToCanonical($g);
                    break;
                }
            }
            if ($matched) {
                $map[$normCol] = $matched;

                continue;
            }

            // 2. Arabic priority
            if ($items->contains(function ($m) {
                $meta = ['language' => $m->original_language, 'original_language' => $m->original_language, 'original_title' => $m->original_title, 'title_ar' => $m->title_ar, 'origin_country' => $m->origin_country];

                return $this->classifier->isArabic($m->title, $m->genres()->pluck('name_en')->toArray(), $meta);
            })) {
                $map[$normCol] = 'Arabic';

                continue;
            }

            // 3. Indian priority
            if ($items->contains(function ($m) {
                $meta = ['language' => $m->original_language, 'original_language' => $m->original_language, 'original_title' => $m->original_title, 'origin_country' => $m->origin_country];

                return $this->classifier->isIndian($m->title, $m->genres()->pluck('name_en')->toArray(), $meta);
            })) {
                $map[$normCol] = 'Indian';

                continue;
            }

            // 4. Animation priority (if known animation franchise or >= 50% animated, excluding live-action)
            if (str_contains(strtolower($cleanCol), 'live-action')) {
                // Live action collections must not be Animation
            } else {
                $knownAnim = ['despicable me', 'minions', 'kung fu panda', 'moana', 'the bad guys', 'the wild robot', 'garfield', 'the garfield movie', 'hoppers', 'toy story', 'shrek', 'ice age', 'spider-verse', 'inside out', 'zootopia', 'finding nemo', 'cars', 'madagascar', 'hotel transylvania', 'spongebob', 'tom and jerry', 'the addams family animated'];
                if (in_array(strtolower($cleanCol), $knownAnim)) {
                    $map[$normCol] = 'Animation';

                    continue;
                }

                $animCount = $items->filter(fn ($m) => $this->classifier->isAnimation($m->title, $normCol, $m->genres()->pluck('name_en')->toArray()))->count();
                if ($animCount > 0 && $animCount >= ceil($items->count() / 2)) {
                    $map[$normCol] = 'Animation';

                    continue;
                }
            }

            // 5. Dominant genre votes across items in collection
            $votes = [];
            foreach ($items as $m) {
                $meta = ['language' => $m->original_language, 'original_language' => $m->original_language, 'original_title' => $m->original_title, 'title_ar' => $m->title_ar, 'origin_country' => $m->origin_country];
                $g = $this->classifier->resolveDominantGenre($m->title, $normCol, $m->genres()->pluck('name_en')->toArray(), $meta);
                $votes[$g] = ($votes[$g] ?? 0) + 1;
            }
            arsort($votes);
            $map[$normCol] = array_key_first($votes) ?: 'Action';
        }

        return $map;
    }

    /**
     * Display summary and breakdown of reorganization plan.
     */
    protected function displayPlanSummary(array $plan): void
    {
        $this->newLine();
        $this->info('Total movies to reorganize: '.count($plan));

        $byCategory = collect($plan)->groupBy('category');

        $labels = [
            'animation_consolidation' => '1. Animation Consolidation (Prioritizing Animation over Action/Family/Adventure)',
            'franchise_nesting' => '2. Franchise Nesting (Nesting loose movies into collection folders)',
            'legacy_folder_cleanup' => '3. Legacy Folder Cleanup (Romantic -> Romance, Sci-Fi -> Science Fiction, Crime & Mystery -> Crime/Mystery)',
            'dominant_genre_reclassification' => '4. Dominant Genre Reclassifications (Aligning with TMDB canonical genres)',
        ];

        foreach ($labels as $key => $title) {
            $items = $byCategory->get($key, collect());
            $this->newLine();
            $this->info("=== {$title} (".$items->count().' items) ===');

            foreach ($items->take(15) as $item) {
                $m = $item['movie'];
                $this->line("  * [ID {$m->id}] {$m->title} ({$m->year}):");
                $this->line("     From: {$item['current_dir']}");
                $this->line("     To  : {$item['target_dir']}");
            }

            if ($items->count() > 15) {
                $this->line('     ... and '.($items->count() - 15).' more.');
            }
        }
    }

    /**
     * Execute physical moves and update database records.
     */
    protected function executeReorganization(array $plan, array $roots = []): void
    {
        $this->newLine();
        $this->info('--- Executing Physical Moves & Database Synchronization ---');

        $bar = $this->output->createProgressBar(count($plan));
        $bar->start();

        $successCount = 0;
        $skipCount = 0;
        $errorCount = 0;
        $cleanupDirs = [];

        foreach ($plan as $item) {
            $movie = $item['movie'];
            $currentDir = $item['current_dir'];
            $targetDir = $item['target_dir'];
            $targetParent = dirname($targetDir);

            if (! file_exists($currentDir)) {
                Log::warning("Physical move skipped: Source directory does not exist: {$currentDir}");
                $skipCount++;
                $bar->advance();

                continue;
            }

            // Create target parent directories
            if (! is_dir($targetParent)) {
                if (! @mkdir($targetParent, 0777, true) && ! is_dir($targetParent)) {
                    Log::error("Failed to create target directory: {$targetParent}");
                    $errorCount++;
                    $bar->advance();

                    continue;
                }
            }

            // If target directory already exists
            if (file_exists($targetDir)) {
                // If it's the exact same directory (case differences on Windows), handle carefully
                if (strcasecmp($currentDir, $targetDir) === 0) {
                    // Just a case rename
                    $tempDir = $targetDir.'_renaming_'.uniqid();
                    @rename($currentDir, $tempDir);
                    $moved = @rename($tempDir, $targetDir);
                } else {
                    Log::warning("Target directory already exists, skipping move: {$targetDir}");
                    $skipCount++;
                    $bar->advance();

                    continue;
                }
            } else {
                $moved = @rename($currentDir, $targetDir);
            }

            if (! $moved) {
                Log::error("Failed to move directory from '{$currentDir}' to '{$targetDir}'");
                $errorCount++;
                $bar->advance();

                continue;
            }

            // Update MediaItem record
            $oldDirNorm = str_replace('\\', '/', $currentDir);
            $newDirNorm = str_replace('\\', '/', $targetDir);
            $oldFilePathNorm = str_replace('\\', '/', $movie->file_path);

            $newFilePath = str_replace($oldDirNorm, $newDirNorm, $oldFilePathNorm);
            $movie->file_path = $newFilePath;
            $movie->folder_path = $newDirNorm;
            if ($item['collection'] && empty($movie->collection_name)) {
                $movie->collection_name = $item['collection'];
            }
            $movie->save();

            // Update associated Subtitles
            $subtitles = Subtitle::where('subtitlable_type', MediaItem::class)
                ->where('subtitlable_id', $movie->id)
                ->get();

            foreach ($subtitles as $sub) {
                if ($sub->file_path && str_starts_with(str_replace('\\', '/', $sub->file_path), $oldDirNorm)) {
                    $sub->file_path = str_replace($oldDirNorm, $newDirNorm, str_replace('\\', '/', $sub->file_path));
                    $sub->save();
                }
            }

            $cleanupDirs[] = dirname($currentDir);
            $successCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Prune empty parent folders
        $this->pruneEmptyFolders(array_unique($cleanupDirs), $roots);

        $this->info("Reorganization finished: {$successCount} moved, {$skipCount} skipped, {$errorCount} errors.");
    }

    /**
     * Remove empty old directories left behind, cleaning up leftover metadata files in evacuated collection folders.
     */
    protected function pruneEmptyFolders(array $dirs, array $roots = []): void
    {
        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $items = array_diff(scandir($dir), ['.', '..']);
            $hasSubDirs = false;
            foreach ($items as $it) {
                if (is_dir($dir.'/'.$it)) {
                    $hasSubDirs = true;
                    break;
                }
            }

            // If an evacuated collection folder only has leftover loose files (e.g. info.txt, images), remove them
            if (! $hasSubDirs) {
                foreach ($items as $it) {
                    $f = $dir.'/'.$it;
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
                @rmdir($dir);
            }

            // Also check parent (e.g. legacy genre folder)
            $parent = dirname($dir);
            if (is_dir($parent)) {
                $parentItems = array_diff(scandir($parent), ['.', '..']);
                $parentHasSubDirs = false;
                foreach ($parentItems as $pit) {
                    if (is_dir($parent.'/'.$pit)) {
                        $parentHasSubDirs = true;
                        break;
                    }
                }
                if (! $parentHasSubDirs) {
                    foreach ($parentItems as $pit) {
                        $pf = $parent.'/'.$pit;
                        if (is_file($pf)) {
                            @unlink($pf);
                        }
                    }
                    @rmdir($parent);
                }
            }
        }

        // Specifically check legacy folders if they are evacuated
        foreach ($roots as $root) {
            $legacyDirs = ['Crime & Mystery', 'Romantic', 'Sci-Fi', 'Biography'];
            foreach ($legacyDirs as $ld) {
                $p = "{$root}/{$ld}";
                if (is_dir($p)) {
                    $subdirs = glob("{$p}/*");
                    $allEmptyOrEvacuated = true;
                    foreach ($subdirs as $sd) {
                        if (is_dir($sd)) {
                            $inner = array_diff(scandir($sd), ['.', '..']);
                            $innerHasMovieDirs = false;
                            foreach ($inner as $in) {
                                if (is_dir("{$sd}/{$in}")) {
                                    $innerHasMovieDirs = true;
                                    break;
                                }
                            }
                            if ($innerHasMovieDirs) {
                                $allEmptyOrEvacuated = false;
                                break;
                            }
                        }
                    }
                    if ($allEmptyOrEvacuated) {
                        foreach ($subdirs as $sd) {
                            if (is_dir($sd)) {
                                foreach (glob("{$sd}/*") as $sf) {
                                    @unlink($sf);
                                }
                                @rmdir($sd);
                            } elseif (is_file($sd)) {
                                @unlink($sd);
                            }
                        }
                        @rmdir($p);
                    }
                }
            }
        }
    }
}
