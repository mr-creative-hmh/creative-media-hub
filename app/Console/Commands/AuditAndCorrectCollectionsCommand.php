<?php

namespace App\Console\Commands;

use App\Http\Controllers\CollectionController;
use App\Models\MediaItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditAndCorrectCollectionsCommand extends Command
{
    protected $signature = 'library:audit-collections 
                            {--fix : Apply virtual database fixes} 
                            {--align-physical : Move physical folders into canonical collection folders} 
                            {--dry-run : Only show what would be changed}';

    protected $description = 'Audits and corrects collections virtually (IDs, names) and physically (folder alignments).';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $alignPhysical = (bool) $this->option('align-physical');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('=== Starting Collections Audit & Correction ===');
        if ($dryRun) {
            $this->comment('Mode: DRY RUN (No changes will be written)');
        } else {
            $this->comment('Mode: '.($fix ? 'FIX DB ' : '').($alignPhysical ? 'ALIGN PHYSICAL ' : 'PREVIEW ONLY'));
        }

        $this->auditAndFixVirtualCollections($fix && ! $dryRun);

        if ($alignPhysical) {
            $this->alignPhysicalCollections(! $dryRun);
        } else {
            $this->previewPhysicalAlignments();
        }

        if (($fix || $alignPhysical) && ! $dryRun) {
            CollectionController::clearCache();
        }

        $this->info("\n=== Collections Audit Complete ===");

        return Command::SUCCESS;
    }

    /**
     * Audit and correct collection IDs and names in the database.
     */
    protected function auditAndFixVirtualCollections(bool $apply): void
    {
        $this->newLine();
        $this->info('--- 1. Virtual Collection Audit & ID Normalization ---');

        // 1. Omar & Salma Collection
        $omarMovies = MediaItem::where('title', 'like', '%Omar%Salma%')
            ->orWhere('title_ar', 'like', '%عمر وسلمى%')
            ->orWhere('file_path', 'like', '%Omar and Salma%')
            ->get();

        foreach ($omarMovies as $m) {
            if ($m->collection_id !== 180255 || $m->collection_name !== 'Omar and Salma Collection') {
                $this->line("  * Fixing Omar & Salma [ID {$m->id}] {$m->title} -> collection_id: 180255, name: Omar and Salma Collection");
                if ($apply) {
                    $m->collection_id = 180255;
                    $m->collection_name = 'Omar and Salma Collection';
                    $m->save();
                }
            }
        }

        // 2. The Hunger Games Collection
        $hungerMovies = MediaItem::where('title', 'like', '%Hunger Games%')
            ->orWhere('file_path', 'like', '%Hunger Games%')
            ->get();

        foreach ($hungerMovies as $m) {
            if ($m->collection_id !== 131635) {
                $this->line("  * Fixing Hunger Games [ID {$m->id}] {$m->title} (was {$m->collection_id}) -> collection_id: 131635");
                if ($apply) {
                    $m->collection_id = 131635;
                    $m->collection_name = 'The Hunger Games Collection';
                    $m->save();
                }
            }
        }

        // 3. X-Men Collection
        $xmenMovies = MediaItem::where('title', 'like', '%X-Men%')
            ->orWhere('file_path', 'like', '%X-Men%')
            ->orWhere('title', 'like', '%X2%')
            ->orWhere('file_path', 'like', '%/X2 %')
            ->orWhere('id', 1317)
            ->get();

        foreach ($xmenMovies as $m) {
            if ($m->collection_id !== 748) {
                $this->line("  * Fixing X-Men [ID {$m->id}] {$m->title} (was {$m->collection_id}) -> collection_id: 748");
                if ($apply) {
                    $m->collection_id = 748;
                    $m->collection_name = 'X-Men Collection';
                    $m->save();
                }
            }
        }

        // 4. Despicable Me vs Minions Separation
        $dmMovies = MediaItem::where('title', 'like', '%Despicable Me%')
            ->orWhere('file_path', 'like', '%Despicable Me%')
            ->get();

        foreach ($dmMovies as $m) {
            if ($m->collection_id !== 86066 || $m->collection_name !== 'Despicable Me Collection') {
                $this->line("  * Ensuring Despicable Me [ID {$m->id}] {$m->title} -> collection_id: 86066, name: Despicable Me Collection");
                if ($apply) {
                    $m->collection_id = 86066;
                    $m->collection_name = 'Despicable Me Collection';
                    $m->save();
                }
            }
        }

        $minionMovies = MediaItem::where('title', 'like', '%Minion%')
            ->orWhere('file_path', 'like', '%Minion%')
            ->get();

        foreach ($minionMovies as $m) {
            if ($m->collection_id !== 544669 || $m->collection_name !== 'Minions Collection') {
                $this->line("  * Ensuring Minions [ID {$m->id}] {$m->title} -> collection_id: 544669, name: Minions Collection");
                if ($apply) {
                    $m->collection_id = 544669;
                    $m->collection_name = 'Minions Collection';
                    $m->save();
                }
            }
        }

        // 5. Christopher Nolan Grouping Disentanglement
        $batmanMovies = MediaItem::where(function ($q) {
            $q->where('title', 'Batman Begins')
                ->orWhere('title', 'The Dark Knight')
                ->orWhere('title', 'The Dark Knight Rises');
        })->get();

        foreach ($batmanMovies as $m) {
            if ($m->collection_id !== 263 || $m->collection_name !== 'The Dark Knight Collection') {
                $this->line("  * Disentangling Nolan [ID {$m->id}] {$m->title} -> collection_id: 263, name: The Dark Knight Collection");
                if ($apply) {
                    $m->collection_id = 263;
                    $m->collection_name = 'The Dark Knight Collection';
                    $m->save();
                }
            }
        }

        // 6. Men in Black Collection Unification
        $mibMovies = MediaItem::where('collection_name', 'like', '%Men in Black%')
            ->orWhere('title', 'like', '%Men in Black%')
            ->get();
        foreach ($mibMovies as $m) {
            if ($m->collection_id !== 86055 || $m->collection_name !== 'Men in Black Collection') {
                $this->line("  * Unifying Men in Black [ID {$m->id}] {$m->title} -> collection_id: 86055, name: Men in Black Collection");
                if ($apply) {
                    $m->collection_id = 86055;
                    $m->collection_name = 'Men in Black Collection';
                    $m->collection_id_source = 'tmdb';
                    $m->save();
                }
            }
        }

        // 7. Housefull Collection Normalization
        $housefullMovies = MediaItem::where('collection_name', 'like', '%Housefull%')
            ->orWhere('title', 'like', '%Housefull%')
            ->get();
        foreach ($housefullMovies as $m) {
            if ($m->collection_id !== 142015 || $m->collection_name !== 'Housefull Collection') {
                $this->line("  * Normalizing Housefull [ID {$m->id}] {$m->title} -> collection_id: 142015, name: Housefull Collection");
                if ($apply) {
                    $m->collection_id = 142015;
                    $m->collection_name = 'Housefull Collection';
                    $m->collection_id_source = 'tmdb';
                    $m->save();
                }
            }
        }

        // 8. My Spy Collection Normalization
        $mySpyMovies = MediaItem::where('collection_name', 'like', '%My Spy%')
            ->orWhere('title', 'like', '%My Spy%')
            ->get();
        foreach ($mySpyMovies as $m) {
            if ($m->collection_id !== 1090373 || $m->collection_name !== 'My Spy Collection') {
                $this->line("  * Normalizing My Spy [ID {$m->id}] {$m->title} -> collection_id: 1090373, name: My Spy Collection");
                if ($apply) {
                    $m->collection_id = 1090373;
                    $m->collection_name = 'My Spy Collection';
                    $m->collection_id_source = 'tmdb';
                    $m->save();
                }
            }
        }

        // 9. Sync movies physically inside collection folders with empty collection_name in DB
        $unlinkedMovies = MediaItem::where(function ($q) {
            $q->whereNull('collection_name')->orWhere('collection_name', '');
        })->get();

        foreach ($unlinkedMovies as $m) {
            if (! $m->file_path || ! file_exists($m->file_path)) {
                continue;
            }
            $path = str_replace('\\', '/', $m->file_path);
            $parentDir = dirname(dirname($path));
            $parentName = basename($parentDir);

            if (preg_match('/^(.*?)(?:\s+(?:Collection|Trilogy|Anthology|Saga|Boxset))$/ui', $parentName, $cMatch)) {
                $colName = trim($parentName);
                if ($colName === 'Christopher Nolan Collection') {
                    // Standalone director folder - handled in physical alignment
                    continue;
                }

                $sibling = MediaItem::where('collection_name', $colName)->whereNotNull('collection_id')->first();
                $colId = $sibling?->collection_id;
                $colSource = $sibling?->collection_id_source ?: 'tmdb';

                if (! $colId) {
                    $knownIds = [
                        'Housefull Collection' => 142015,
                        'My Spy Collection' => 1090373,
                        'Mardaani Collection' => 736592,
                        'Despicable Me Collection' => 86066,
                        'Transformers Collection' => 8650,
                        'A Quiet Place Collection' => 521226,
                        'Ip Man Collection' => 70068,
                    ];
                    $colId = $knownIds[$colName] ?? null;
                }

                $this->line("  * Syncing movie from disk collection [ID {$m->id}] {$m->title} -> collection: '{$colName}', id: ".($colId ?: 'null'));
                if ($apply) {
                    $m->collection_name = $colName;
                    $m->collection_id = $colId;
                    $m->collection_id_source = $colSource;
                    $m->save();
                }
            }
        }

        $this->info('  ✓ Virtual collection checks and normalizations complete.');
    }

    /**
     * Preview physical moves needed for standalone collection movies.
     */
    protected function previewPhysicalAlignments(): void
    {
        $this->newLine();
        $this->info('--- 2. Physical Collection Alignment (Preview) ---');

        $plan = $this->buildPhysicalAlignmentPlan();
        $this->info('  Found '.count($plan).' collection movies currently in standalone folders.');

        foreach (array_slice($plan, 0, 15) as $item) {
            $this->line("    * [ID {$item['movie']->id}] {$item['movie']->title} ({$item['movie']->collection_name}):");
            $this->line("      Current: {$item['current_dir']}");
            $this->line("      Target : {$item['target_dir']}");
        }

        if (count($plan) > 15) {
            $this->line('    ... and '.(count($plan) - 15).' more.');
        }

        $this->comment('  To execute physical moves, run with --align-physical.');
    }

    /**
     * Execute physical moves for standalone collection movies.
     */
    protected function alignPhysicalCollections(bool $apply): void
    {
        $this->newLine();
        $this->info('--- 2. Executing Physical Collection Alignment ---');

        $plan = $this->buildPhysicalAlignmentPlan();
        $this->info('  Moving '.count($plan).' collection movies into canonical collection directories...');

        $successCount = 0;

        foreach ($plan as $item) {
            $movie = $item['movie'];
            $currentDir = $item['current_dir'];
            $targetDir = $item['target_dir'];
            $targetParent = dirname($targetDir);

            $this->line("  -> Moving [ID {$movie->id}] {$movie->title}:");
            $this->line("     From: {$currentDir}");
            $this->line("     To  : {$targetDir}");

            if ($apply) {
                if (! file_exists($currentDir)) {
                    $this->error("     Source directory does not exist: {$currentDir}");

                    continue;
                }

                if (! is_dir($targetParent)) {
                    @mkdir($targetParent, 0777, true);
                }

                // If targetDir already exists (e.g. partial previous run), don't overwrite blindly
                if (file_exists($targetDir)) {
                    $this->warn("     Target directory already exists: {$targetDir}");

                    continue;
                }

                // Atomic folder rename/move
                $moved = @rename($currentDir, $targetDir);
                if (! $moved) {
                    $this->error("     Failed to move folder from {$currentDir} to {$targetDir}");

                    continue;
                }

                // Update database paths
                $oldFilePath = str_replace('\\', '/', $movie->file_path);
                $oldDir = str_replace('\\', '/', $currentDir);
                $newDir = str_replace('\\', '/', $targetDir);

                $newFilePath = str_replace($oldDir, $newDir, $oldFilePath);
                $movie->file_path = $newFilePath;
                $movie->folder_path = $newDir;
                $movie->save();

                $this->info('     ✓ Successfully moved and updated DB record.');
                $successCount++;
            }
        }

        $this->info("  ✓ Successfully aligned {$successCount} collection folders physically and in SQLite.");
    }

    /**
     * Build list of collection movies sitting in standalone folders.
     */
    protected function buildPhysicalAlignmentPlan(): array
    {
        $collMovies = MediaItem::whereNotNull('collection_name')
            ->where('collection_name', '!=', '')
            ->get();

        $plan = [];

        foreach ($collMovies as $m) {
            if (! file_exists($m->file_path)) {
                continue;
            }

            $normPath = str_replace('\\', '/', $m->file_path);
            $parts = explode('/', $normPath);

            // Check if already in a collection subfolder
            $inCollectionSubfolder = false;
            foreach ($parts as $p) {
                if ($p === 'Christopher Nolan Collection') {
                    // Non-franchise director folder: must be realigned!
                    continue;
                }
                if (stripos($p, 'collection') !== false || stripos($p, 'trilogy') !== false || stripos($p, 'saga') !== false || stripos($p, 'anthology') !== false) {
                    $inCollectionSubfolder = true;
                    break;
                }
            }

            if (! $inCollectionSubfolder) {
                $movieDir = str_replace('\\', '/', dirname($m->file_path));
                $parent = dirname($movieDir);
                $genreDir = basename($parent) === 'Christopher Nolan Collection' ? dirname($parent) : $parent;
                $movieFolderName = basename($movieDir);

                // Find matching existing collection directory in this genre, or create a clean canonical one
                $collFolder = $this->resolveCanonicalCollectionFolder($genreDir, $m->collection_name);
                $targetDir = $genreDir.'/'.$collFolder.'/'.$movieFolderName;

                $plan[] = [
                    'movie' => $m,
                    'current_dir' => $movieDir,
                    'target_dir' => $targetDir,
                    'collection_name' => $collFolder,
                ];
            }
        }

        // Also check standalone movies sitting in Christopher Nolan Collection (Inception, Interstellar, Memento, etc.)
        $nolanStandalone = MediaItem::where('file_path', 'like', '%Christopher Nolan Collection%')->get();
        foreach ($nolanStandalone as $m) {
            if (! file_exists($m->file_path)) {
                continue;
            }
            $movieDir = str_replace('\\', '/', dirname($m->file_path));
            $nolanDir = dirname($movieDir);
            if (basename($nolanDir) !== 'Christopher Nolan Collection') {
                continue;
            }
            $genreDir = dirname($nolanDir);
            $movieFolderName = basename($movieDir);

            // If already queued above (e.g. Batman movies), skip
            $alreadyQueued = collect($plan)->contains(fn ($p) => $p['movie']->id === $m->id);
            if ($alreadyQueued) {
                continue;
            }

            $targetDir = $genreDir.'/'.$movieFolderName;
            $plan[] = [
                'movie' => $m,
                'current_dir' => $movieDir,
                'target_dir' => $targetDir,
                'collection_name' => '(Standalone Movie Out of Director Folder)',
            ];
        }

        return $plan;
    }

    /**
     * Resolve existing physical collection directory name or produce clean sanitized name.
     */
    protected function resolveCanonicalCollectionFolder(string $genreDir, string $rawCollectionName): string
    {
        // 1. Check existing directories in $genreDir
        $existing = glob($genreDir.'/*', GLOB_ONLYDIR);
        $normSearch = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $rawCollectionName)));

        foreach ($existing as $dir) {
            $base = basename($dir);
            $normBase = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $base)));

            // Exact match on alphanumeric chars
            if ($normSearch === $normBase) {
                return $base;
            }

            // If base has "Collection" and search didn't, or vice-versa
            if (str_replace('collection', '', $normSearch) === str_replace('collection', '', $normBase)) {
                return $base;
            }
        }

        // 2. Otherwise generate clean canonical name (clean Windows invalid chars: \ / : * ? " < > |)
        $clean = str_replace([':', ' - ', ' – ', ' / ', '/'], ' ', $rawCollectionName);
        $clean = trim(preg_replace('[/\\\\:*?"<>|]', '', $clean));
        $clean = preg_replace('/\s+/', ' ', $clean);

        if (! preg_match('/(collection|trilogy|saga|anthology)$/i', $clean)) {
            $clean .= ' Collection';
        }

        return $clean;
    }
}
