<?php

namespace App\Console\Commands;

use App\Models\Subtitle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RepairEmbeddedSubtitlesCommand extends Command
{
    protected $signature = 'subtitles:repair-embedded 
                            {--dry-run : Preview broken paths and resolution without saving to database}';

    protected $description = 'Audit and repair broken or stale video paths in embedded subtitle records across the entire library.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║             EMBEDDED SUBTITLE PATH REPAIR TOOL              ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->line('Mode: '.($dryRun ? '<fg=yellow>DRY-RUN (Simulating changes)</>' : '<fg=green>EXECUTE (Updating database records)</>'));

        $embeddedSubs = Subtitle::with('subtitlable')
            ->where('is_embedded', true)
            ->orWhere('file_path', 'LIKE', 'embedded:%')
            ->get();

        $total = $embeddedSubs->count();
        $this->info("Scanning {$total} embedded subtitle records...");

        $healthyCount = 0;
        $repairedCount = 0;
        $orphanCount = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($embeddedSubs as $sub) {
            $path = $sub->file_path ?? '';
            $parts = explode(':', $path, 3);
            $streamIndex = isset($parts[1]) ? (int) $parts[1] : 0;
            $videoPath = $parts[2] ?? '';

            if ($videoPath && File::exists($videoPath)) {
                $healthyCount++;
                $bar->advance();

                continue;
            }

            // Path is stale or broken, attempt parent resolution
            $parent = $sub->subtitlable;
            $parentPath = $parent?->file_path;

            if ($parentPath && File::exists($parentPath)) {
                $normParentPath = str_replace('\\', '/', $parentPath);
                $newPath = "embedded:{$streamIndex}:{$normParentPath}";

                if (! $dryRun) {
                    $sub->update(['file_path' => $newPath]);
                }
                $repairedCount++;
            } else {
                $orphanCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Embedded Subtitles', $total],
                ['Already Healthy', $healthyCount],
                [$dryRun ? 'Repairable (Broken Paths)' : 'Repaired Successfully', $repairedCount],
                ['Orphaned (No Video on Disk)', $orphanCount],
            ]
        );

        if ($dryRun && $repairedCount > 0) {
            $this->info("Run <fg=yellow>php artisan subtitles:repair-embedded</> without --dry-run to apply these {$repairedCount} repairs.");
        } elseif (! $dryRun && $repairedCount > 0) {
            $this->info("<fg=green>Successfully repaired {$repairedCount} embedded subtitle records!</>");
        } else {
            $this->info('<fg=green>All embedded subtitle records are in a healthy state.</>');
        }

        return 0;
    }
}
