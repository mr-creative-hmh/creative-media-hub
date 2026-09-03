<?php

namespace App\Console\Commands;

use App\Services\Subtitles\SubtitleHealthCheckService;
use Illuminate\Console\Command;

class CheckSubtitlesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subtitles:check 
                            {--fix : Perform deletions of invalid subtitles and renaming to standard .lang.srt extensions}
                            {--dry-run : Simulate and preview actions without modifying disk or database}
                            {--delete-invalid : Delete corrupt and stub subtitle files}
                            {--path= : Specific folder or file path to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan library directories, validate subtitle integrity, prune corrupt/stub subtitles, and standardize language extensions (.ar.srt, .en.srt)';

    /**
     * Execute the console command.
     */
    public function handle(SubtitleHealthCheckService $healthService): int
    {
        $fix = (bool) $this->option('fix');
        $dryRun = (bool) $this->option('dry-run');
        if (! $fix && ! $dryRun) {
            // Default to dry-run preview if --fix is not specified for user safety
            $dryRun = true;
        }

        $deleteInvalid = (bool) ($this->option('delete-invalid') || $fix);
        $autoRename = $fix;
        $path = $this->option('path');

        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║             SUBTITLE HEALTH CHECKER & NORMALIZER             ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
        $this->line('Mode: '.($dryRun ? '<fg=yellow>PREVIEW / DRY-RUN (No changes applied)</>' : '<fg=green>EXECUTE / FIX (Modifications will be applied)</>'));
        if ($path) {
            $this->line("Target Directory: <fg=cyan>{$path}</>");
        }
        $this->line('Scanning subtitle files...');

        $results = $healthService->checkAndNormalize([
            'dry_run' => $dryRun,
            'delete_invalid' => $deleteInvalid,
            'auto_rename' => $autoRename,
            'target_path' => $path,
        ]);

        $this->line('');
        $this->info("Found {$results['total_scanned']} total subtitle files across library.");
        $this->line("• Valid Subtitles: <fg=green>{$results['valid_count']}</>");
        $this->line("• Invalid/Corrupt Stubs: <fg=red>{$results['invalid_count']}</>");
        $this->line('• Deleted Files: <fg=red>'.($dryRun ? "{$results['invalid_count']} (Flagged for deletion)" : "{$results['deleted_count']} deleted").'</>');
        $this->line('• Standardized Renames: <fg=cyan>'.($dryRun ? "{$results['renamed_count']} (Would rename)" : "{$results['renamed_count']} renamed").'</>');
        $this->line("• Already Standard: <fg=gray>{$results['already_standard_count']}</>");

        if (! empty($results['language_breakdown'])) {
            $this->line('');
            $this->info('Language Distribution:');
            foreach ($results['language_breakdown'] as $lang) {
                $this->line("  {$lang['flag']} {$lang['name_en']} ({$lang['code']}): {$lang['count']} file(s)");
            }
        }

        if (! empty($results['items'])) {
            $this->line('');
            $this->info('Subtitle Inspection Details:');
            $tableRows = [];
            foreach (array_slice($results['items'], 0, 50) as $item) {
                $statusColor = $item['is_valid'] ? 'green' : 'red';
                $tableRows[] = [
                    $item['file_name'],
                    "<fg={$statusColor}>".($item['is_valid'] ? 'VALID' : 'INVALID').'</>',
                    $item['cue_count'],
                    $item['flag'].' '.$item['detected_language'],
                    $item['action'],
                    $item['target_filename'] ?: '-',
                ];
            }

            $this->table(
                ['File', 'Status', 'Cues', 'Language', 'Action', 'Target Name'],
                $tableRows
            );

            if (count($results['items']) > 50) {
                $remaining = count($results['items']) - 50;
                $this->comment("... and {$remaining} more files.");
            }
        }

        if ($dryRun && $results['invalid_count'] > 0) {
            $this->line('');
            $this->warn('This was a preview. Run with `--fix` to apply deletions and standardize file names.');
        }

        return Command::SUCCESS;
    }
}
