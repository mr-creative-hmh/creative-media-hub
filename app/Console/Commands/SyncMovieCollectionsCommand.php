<?php

namespace App\Console\Commands;

use App\Http\Controllers\CollectionController;
use App\Models\MediaItem;
use App\Services\Metadata\LibraryMasterIndexService;
use App\Services\Metadata\TmdbProvider;
use App\Services\Scout\LibraryGapService;
use Illuminate\Console\Command;

class SyncMovieCollectionsCommand extends Command
{
    protected $signature = 'library:sync-collections 
                            {--force : Overwrite user custom collections} 
                            {--dry-run : Preview changes without writing to database}';

    protected $description = 'Synchronizes all library movies with official TMDb collections and heals any franchise discrepancies.';

    public function handle(
        TmdbProvider $tmdb,
        LibraryMasterIndexService $masterService,
        LibraryGapService $gapService
    ): int {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('=== Starting TMDb Movie Collections Synchronization ===');
        if ($dryRun) {
            $this->comment('Mode: DRY RUN (Preview only, no changes will be written)');
        }

        $movies = MediaItem::whereNotNull('tmdb_id')->get();
        $this->info("Scanning {$movies->count()} library movies against TMDb official collections...");

        $bar = $this->output->createProgressBar($movies->count());
        $bar->start();

        $updatedCount = 0;
        $healedCount = 0;
        $healedDetails = [];

        foreach ($movies as $movie) {
            $bar->advance();

            if (! $force && $movie->collection_id_source === 'custom') {
                continue;
            }

            try {
                $details = $tmdb->getMovieDetails((int) $movie->tmdb_id);
                if (! $details) {
                    continue;
                }

                $tmdbColId = ! empty($details['collection_id']) ? (int) $details['collection_id'] : null;
                $tmdbColName = ! empty($details['collection_name']) ? trim((string) $details['collection_name']) : null;
                $tmdbColPoster = ! empty($details['collection_poster']) ? (string) $details['collection_poster'] : null;

                if ($tmdbColId && $tmdbColName) {
                    $wasDifferent = ($movie->collection_id !== $tmdbColId)
                        || ($movie->collection_name !== $tmdbColName);

                    if ($wasDifferent) {
                        $oldDesc = $movie->collection_name
                            ? "{$movie->collection_name} (ID: {$movie->collection_id})"
                            : 'None';
                        $newDesc = "{$tmdbColName} (ID: {$tmdbColId})";

                        if ($movie->collection_id && $movie->collection_id !== $tmdbColId) {
                            $healedCount++;
                            $healedDetails[] = "Healed [ID {$movie->id}] '{$movie->title}' ({$movie->release_year}): {$oldDesc} -> {$newDesc}";
                        } else {
                            $updatedCount++;
                        }

                        if (! $dryRun) {
                            $movie->collection_id = $tmdbColId;
                            $movie->collection_name = $tmdbColName;
                            $movie->collection_id_source = 'tmdb';
                            if ($tmdbColPoster) {
                                $movie->collection_poster = $tmdbColPoster;
                            }
                            $movie->save();
                        }
                    }
                } elseif (! $tmdbColId && $movie->collection_id && $movie->collection_id_source !== 'custom') {
                    // Special heal for Glass misassigned to Knives Out
                    if ($movie->collection_id === 722971 && stripos($movie->title, 'Knives Out') === false && stripos($movie->title, 'Glass Onion') === false) {
                        if (stripos($movie->title, 'Glass') !== false) {
                            $healedCount++;
                            $healedDetails[] = "Healed [ID {$movie->id}] '{$movie->title}' ({$movie->release_year}) from Knives Out -> Unbreakable Collection (1769700)";
                            if (! $dryRun) {
                                $movie->collection_id = 1769700;
                                $movie->collection_name = 'Unbreakable Collection';
                                $movie->collection_id_source = 'tmdb';
                                $movie->save();
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore transient API error and keep going
            }
        }

        $bar->finish();
        $this->newLine(2);

        if (! empty($healedDetails)) {
            $this->info('--- Discrepancies Healed ---');
            foreach ($healedDetails as $detail) {
                $this->line("  ✓ {$detail}");
            }
            $this->newLine();
        }

        if (! $dryRun) {
            $this->info('Rebuilding master collection index and clearing caches...');
            try {
                $masterService->buildAll(false, false, false);
            } catch (\Throwable $e) {
            }
            CollectionController::clearCache();
            $gapService->clearCache();
        }

        $this->info("Synchronization Complete! Updated {$updatedCount} movies, healed {$healedCount} discrepancies.");

        return Command::SUCCESS;
    }
}
