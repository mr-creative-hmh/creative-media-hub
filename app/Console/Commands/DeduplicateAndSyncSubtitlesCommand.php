<?php

namespace App\Console\Commands;

use App\Models\Subtitle;
use App\Services\Subtitles\SubtitleLanguageDetectorService;
use App\Services\Subtitles\SubtitleValidatorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DeduplicateAndSyncSubtitlesCommand extends Command
{
    protected $signature = 'subtitles:deduplicate-and-sync 
                            {--dry-run : Preview duplicate files and DB records without modifying}
                            {--fix : Delete numbered duplicate subtitles on disk and clean duplicate DB records}
                            {--path= : Specific folder or disk path to scan}';

    protected $description = 'Prune numbered subtitle duplicates (* (1).srt, *.ar (2).srt), keep the best quality subtitle, and resync the database.';

    public function handle(SubtitleValidatorService $validator, SubtitleLanguageDetectorService $languageDetector): int
    {
        $fix = (bool) $this->option('fix');
        $dryRun = (bool) $this->option('dry-run');
        if (! $fix && ! $dryRun) {
            $dryRun = true;
        }

        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║        SUBTITLE DEDUPLICATION & DATABASE SYNCHRONIZER        ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->line('Mode: '.($dryRun ? '<fg=yellow>DRY-RUN (Simulating changes)</>' : '<fg=green>EXECUTE / FIX (Applying changes to disk & DB)</>'));

        $scanPath = $this->option('path') ?: 'H:/Entertainment/Movies';
        $this->line("Scanning directory: <fg=cyan>{$scanPath}</>");

        if (! File::isDirectory($scanPath)) {
            $this->error("Directory not found: {$scanPath}");

            return 1;
        }

        // 1. Scan disk for numbered duplicate subtitles
        $this->line('Searching for duplicate numbered subtitle files (* (1).srt, *.ar (2).srt)...');
        $allSrtFiles = File::allFiles($scanPath);
        $numberedFiles = [];
        $filesByGroup = [];

        foreach ($allSrtFiles as $file) {
            $pathname = str_replace('\\', '/', $file->getPathname());
            $filename = $file->getFilename();
            $dir = dirname($pathname);

            if (preg_match('/^(.*?)(?:\s*\(\d+\)|\.\d+)?(\.[a-z]{2,3}(?:-[a-z0-9]+)?)?(?:\s*\(\d+\)|\.\d+)?\.srt$/i', $filename, $matches)) {
                $base = $matches[1];
                $lang = ! empty($matches[2]) ? strtolower(trim($matches[2], '.')) : 'en';
                if (stripos($filename, '.ar') !== false || stripos($filename, 'arabic') !== false) {
                    $lang = 'ar';
                }

                $groupKey = "{$dir}/{$base}.{$lang}";
                $filesByGroup[$groupKey][] = [
                    'path' => $pathname,
                    'filename' => $filename,
                    'dir' => $dir,
                    'base' => $base,
                    'lang' => $lang,
                    'size' => $file->getSize(),
                    'is_numbered' => (bool) preg_match('/(?:\s*\(\d+\)|\.\d+)\.srt$/i', $filename),
                ];
            }
        }

        $diskPrunedCount = 0;
        $diskStandardizedCount = 0;
        $actionsTable = [];

        foreach ($filesByGroup as $groupKey => $groupFiles) {
            if (count($groupFiles) <= 1 && empty($groupFiles[0]['is_numbered'])) {
                continue;
            }

            // Find or establish the canonical target path
            $first = $groupFiles[0];
            $canonicalTarget = "{$first['dir']}/{$first['base']}.{$first['lang']}.srt";

            // Score each candidate to find the best file
            $bestFile = null;
            $bestScore = -1;

            foreach ($groupFiles as $candidate) {
                $validation = $validator->validate($candidate['path'], $candidate['filename']);
                $score = $validation['cue_count'] * 1000 + min($candidate['size'], 500000);
                if ($validation['is_valid']) {
                    $score += 10000000;
                }
                if ($candidate['path'] === $canonicalTarget) {
                    $score += 5000; // slight preference to existing canonical name
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestFile = $candidate;
                }
            }

            if (! $bestFile) {
                continue;
            }

            // Target action: rename best file to canonical if not already
            if ($bestFile['path'] !== $canonicalTarget) {
                $actionsTable[] = [
                    'File' => basename($bestFile['path']),
                    'Action' => '<fg=cyan>STANDARDIZE</>',
                    'Target' => basename($canonicalTarget),
                ];
                if (! $dryRun) {
                    try {
                        if (File::exists($canonicalTarget)) {
                            @File::delete($canonicalTarget);
                        }
                        File::move($bestFile['path'], $canonicalTarget);
                        $diskStandardizedCount++;
                    } catch (\Throwable $e) {
                        Log::error("Failed to rename {$bestFile['path']} to {$canonicalTarget}: ".$e->getMessage());
                    }
                } else {
                    $diskStandardizedCount++;
                }
            }

            // Delete redundant files in this group
            foreach ($groupFiles as $candidate) {
                if ($candidate['path'] === $bestFile['path']) {
                    continue;
                }

                $actionsTable[] = [
                    'File' => basename($candidate['path']),
                    'Action' => '<fg=red>PRUNE DUPLICATE</>',
                    'Target' => '-',
                ];

                if (! $dryRun) {
                    try {
                        if (File::exists($candidate['path'])) {
                            @File::delete($candidate['path']);
                            $diskPrunedCount++;
                        }
                    } catch (\Throwable $e) {
                        Log::error("Failed to delete duplicate {$candidate['path']}: ".$e->getMessage());
                    }
                } else {
                    $diskPrunedCount++;
                }
            }
        }

        if (! empty($actionsTable)) {
            $this->info('Disk Subtitle Actions ('.count($actionsTable).' items):');
            $this->table(['File', 'Action', 'Target'], array_slice($actionsTable, 0, 30));
            if (count($actionsTable) > 30) {
                $this->line('... and '.(count($actionsTable) - 30).' more file actions.');
            }
        } else {
            $this->info('No numbered subtitle duplicates found on disk.');
        }

        $this->line('');
        $this->info("Disk Summary: Standardized: {$diskStandardizedCount}, Pruned Duplicates: {$diskPrunedCount}");

        // 2. Clean up database duplicate subtitle records
        $this->line('');
        $this->line('Auditing database subtitles table for duplicate groups...');

        $duplicateGroups = Subtitle::select('subtitlable_type', 'subtitlable_id', 'language', DB::raw('count(*) as count'))
            ->where('is_embedded', false)
            ->groupBy('subtitlable_type', 'subtitlable_id', 'language')
            ->having('count', '>', 1)
            ->get();

        $this->info("Found {$duplicateGroups->count()} duplicate external subtitle groups in database.");
        $dbPrunedCount = 0;

        foreach ($duplicateGroups as $grp) {
            $records = Subtitle::where('subtitlable_type', $grp->subtitlable_type)
                ->where('subtitlable_id', $grp->subtitlable_id)
                ->where('language', $grp->language)
                ->where('is_embedded', false)
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->get();

            // Find valid existing file
            $keeper = null;
            foreach ($records as $r) {
                if (File::exists($r->file_path)) {
                    $keeper = $r;
                    break;
                }
            }

            if (! $keeper && $records->isNotEmpty()) {
                $keeper = $records->first();
            }

            foreach ($records as $r) {
                if ($keeper && $r->id === $keeper->id) {
                    continue;
                }

                if (! $dryRun) {
                    $r->delete();
                    $dbPrunedCount++;
                } else {
                    $dbPrunedCount++;
                }
            }
        }

        // 3. Remove orphaned subtitle records where file no longer exists on disk
        $orphansCount = 0;
        $allNonEmbedded = Subtitle::where('is_embedded', false)->get(['id', 'file_path']);
        foreach ($allNonEmbedded as $sub) {
            if ($sub->file_path && ! str_starts_with($sub->file_path, 'embedded:') && ! File::exists($sub->file_path)) {
                if (! $dryRun) {
                    $sub->delete();
                    $orphansCount++;
                } else {
                    $orphansCount++;
                }
            }
        }

        $this->info("Database Summary: Pruned Duplicate Records: {$dbPrunedCount}, Pruned Orphan Records: {$orphansCount}");
        $this->line('');
        $this->info('✓ Subtitle deduplication and database synchronization complete!');

        return 0;
    }
}
