<?php

namespace App\Console\Commands;

use App\Services\Metadata\LibraryMasterIndexService;
use Illuminate\Console\Command;

class BackfillMovieCollectionsCommand extends Command
{
    protected $signature = 'media:backfill-collections';

    protected $description = 'Backfill missing movie collection names and posters in the database and local master index.';

    public function handle(LibraryMasterIndexService $masterIndex): int
    {
        $this->info('=== Backfilling Movie Collections ===');

        $stats = $masterIndex->enrichMissingCollections(fn (string $msg) => $this->line($msg));

        $this->newLine();
        $this->info("Done! Checked: {$stats['total_checked']}, Collections Assigned: {$stats['collections_assigned']}");

        return Command::SUCCESS;
    }
}
