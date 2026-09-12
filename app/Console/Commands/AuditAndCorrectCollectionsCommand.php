<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MediaItem;
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
        $fix = (bool)$this->option('fix');
        $alignPhysical = (bool)$this->option('align-physical');
        $dryRun = (bool)$this->option('dry-run');

        $this->info("=== Starting Collections Audit & Correction ===");
        if ($dryRun) {
            $this->comment("Mode: DRY RUN (No changes will be written)");
        } else {
            $this->comment("Mode: " . ($fix ? "FIX DB " : "") . ($alignPhysical ? "ALIGN PHYSICAL " : "PREVIEW ONLY"));
        }

        $this->auditAndFixVirtualCollections($fix && !$dryRun);

        if ($alignPhysical) {
            $this->alignPhysicalCollections(!$dryRun);
        } else {
            $this->previewPhysicalAlignments();
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
        $this->info("--- 1. Virtual Collection Audit & ID Normalization ---");

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
        $batmanMovies = MediaItem::where(function($q) {
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

        $this->info("  ✓ Virtual collection checks and normalizations complete.");
    }

    /**
     * Preview physical moves needed for standalone collection movies.
     */
    protected function previewPhysicalAlignments(): void
    {
        $this->newLine();
        $this->info("--- 2. Physical Collection Alignment (Preview) ---");

        $plan = $this->buildPhysicalAlignmentPlan();
        $this->info("  Found " . count($plan) . " collection movies currently in standalone folders.");

        foreach (array_slice($plan, 0, 15) as $item) {
            $this->line("    * [ID {$item['movie']->id}] {$item['movie']->title} ({$item['movie']->collection_name}):");
            $this->line("      Current: {$item['current_dir']}");
            $this->line("      Target : {$item['target_dir']}");
        }

        if (count($plan) > 15) {
            $this->line("    ... and " . (count($plan) - 15) . " more.");
        }

        $this->comment("  To execute physical moves, run with --align-physical.");
    }

    /**
     * Execute physical moves for standalone collection movies.
     */
    protected function alignPhysicalCollections(bool $apply): void
    {
        $this->newLine();
        $this->info("--- 2. Executing Physical Collection Alignment ---");

        $plan = $this->buildPhysicalAlignmentPlan();
        $this->info("  Moving " . count($plan) . " collection movies into canonical collection directories...");

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
                if (!file_exists($currentDir)) {
                    $this->error("     Source directory does not exist: {$currentDir}");
                    continue;
                }

                if (!is_dir($targetParent)) {
                    @mkdir($targetParent, 0777, true);
                }

                // If targetDir already exists (e.g. partial previous run), don't overwrite blindly
                if (file_exists($targetDir)) {
                    $this->warn("     Target directory already exists: {$targetDir}");
                    continue;
                }

                // Atomic folder rename/move
                $moved = @rename($currentDir, $targetDir);
                if (!$moved) {
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

                $this->info("     ✓ Successfully moved and updated DB record.");
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
            if (!file_exists($m->file_path)) continue;

            $normPath = str_replace('\\', '/', $m->file_path);
            $parts = explode('/', $normPath);

            // Check if already in a collection subfolder
            $inCollectionSubfolder = false;
            foreach ($parts as $p) {
                if (stripos($p, 'collection') !== false || stripos($p, 'trilogy') !== false || stripos($p, 'saga') !== false || stripos($p, 'anthology') !== false) {
                    $inCollectionSubfolder = true;
                    break;
                }
            }

            if (!$inCollectionSubfolder) {
                $movieDir = str_replace('\\', '/', dirname($m->file_path));
                $genreDir = dirname($movieDir);
                $movieFolderName = basename($movieDir);

                // Find matching existing collection directory in this genre, or create a clean canonical one
                $collFolder = $this->resolveCanonicalCollectionFolder($genreDir, $m->collection_name);
                $targetDir = $genreDir . '/' . $collFolder . '/' . $movieFolderName;

                $plan[] = [
                    'movie' => $m,
                    'current_dir' => $movieDir,
                    'target_dir' => $targetDir,
                    'collection_name' => $collFolder,
                ];
            }
        }

        return $plan;
    }

    /**
     * Resolve existing physical collection directory name or produce clean sanitized name.
     */
    protected function resolveCanonicalCollectionFolder(string $genreDir, string $rawCollectionName): string
    {
        // 1. Check existing directories in $genreDir
        $existing = glob($genreDir . '/*', GLOB_ONLYDIR);
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

        if (!preg_match('/(collection|trilogy|saga|anthology)$/i', $clean)) {
            $clean .= ' Collection';
        }

        return $clean;
    }
}
