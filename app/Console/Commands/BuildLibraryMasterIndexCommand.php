<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Metadata\LibraryMasterIndexService;

class BuildLibraryMasterIndexCommand extends Command
{
    protected $signature = 'library:build-index 
                            {--enrich : Enrich missing Arabic metadata, generic episode titles, and stills from TMDB} 
                            {--popular : Pre-seed top popular/trending movies and series into local cache}';

    protected $description = 'Compiles the persistent Local Master Metadata Index for movies, series, and extended collections.';

    public function handle(LibraryMasterIndexService $indexService): int
    {
        $enrich = (bool)$this->option('enrich');
        $popular = (bool)$this->option('popular');

        $this->info("=== Starting Master Metadata Index Compilation ===");
        $this->line("Enrich from Web: " . ($enrich ? "YES" : "NO"));
        $this->line("Pre-seed Popular: " . ($popular ? "YES" : "NO"));
        $this->newLine();

        $result = $indexService->buildMasterIndex(
            $enrich,
            $popular,
            fn($msg) => $this->line($msg)
        );

        $this->newLine();
        $this->table(
            ['Index Component', 'Items Indexed'],
            [
                ['Extended Collections', $result['collections_count'] ?? 0],
                ['Library Movies', $result['movies_count'] ?? 0],
                ['Library Series', $result['series_count'] ?? 0],
                ['Popular Movies Pre-seeded', $result['popular_movies_seeded'] ?? 0],
                ['Popular Series Pre-seeded', $result['popular_series_seeded'] ?? 0],
            ]
        );

        $this->info("Master Metadata Index saved in: " . $indexService->getIndexPath());
        return Command::SUCCESS;
    }
}
