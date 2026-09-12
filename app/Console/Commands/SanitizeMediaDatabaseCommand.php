<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MediaItem;
use App\Models\Episode;
use App\Models\Season;
use App\Models\Series;
use Illuminate\Support\Facades\DB;

class SanitizeMediaDatabaseCommand extends Command
{
    protected $signature = 'library:sanitize {--fix : Apply the sanitation changes to the database} {--dry-run : Only show what would be sanitized}';
    protected $description = 'Sanitizes media database: prunes phantom/corrupted episode rows, syncs resolutions from physical files, and validates titles.';

    public function handle(): int
    {
        $fix = (bool)$this->option('fix');
        $this->info("=== Starting Media Database Sanitation " . ($fix ? "[APPLY MODE]" : "[DRY RUN MODE]") . " ===");

        $this->sanitizePhantomEpisodes($fix);
        $this->syncMovieResolutions($fix);
        $this->syncEpisodeResolutions($fix);

        $this->info("\n=== Sanitation complete ===");
        return Command::SUCCESS;
    }

    /**
     * Prune corrupted/phantom duplicate episode records.
     * Specifically: The Four Seasons (IDs 5061-5095) and Breaking Bones (IDs 5223-5254)
     * which were created pointing to Season 1 files instead of their authentic Season 2 files.
     */
    protected function sanitizePhantomEpisodes(bool $fix): void
    {
        $this->newLine();
        $this->info("--- 1. Checking for Phantom / Corrupted Duplicate Episodes ---");

        // Identify duplicate files assigned to multiple episode records
        $duplicates = DB::select("
            SELECT episodes.file_path, COUNT(*) as count, 
                   GROUP_CONCAT(episodes.id) as ids, 
                   GROUP_CONCAT(seasons.season_number) as seasons, 
                   GROUP_CONCAT(episodes.episode_number) as episodes
            FROM episodes
            JOIN seasons ON episodes.season_id = seasons.id
            WHERE episodes.file_path IS NOT NULL AND episodes.file_path != ''
            GROUP BY episodes.file_path
            HAVING COUNT(*) > 1
        ");

        if (empty($duplicates)) {
            $this->line("  ✓ No duplicate episode file paths found.");
            return;
        }

        $this->warn("  Found " . count($duplicates) . " physical files assigned to multiple episode records.");
        $pruneIds = [];

        foreach ($duplicates as $row) {
            $ids = explode(',', $row->ids);
            $seasons = explode(',', $row->seasons);
            $episodes = explode(',', $row->episodes);
            $fn = basename($row->file_path);

            // Detect actual season from filename
            if (preg_match('/S0?(\d+)E0?(\d+)/i', $fn, $m)) {
                $actualSeason = (int)$m[1];
                $actualEp = (int)$m[2];

                // For each ID, see which one does NOT match the physical filename
                foreach ($ids as $idx => $id) {
                    $sNum = (int)$seasons[$idx];
                    $eNum = (int)$episodes[$idx];

                    if ($sNum !== $actualSeason) {
                        // This row is a phantom attachment!
                        $pruneIds[] = (int)$id;
                        $this->line("    * [PHANTOM] Episode ID {$id} marked Season {$sNum}E{$eNum} but file is {$fn} (S{$actualSeason}E{$actualEp})");
                    }
                }
            }
        }

        $this->info("  Total phantom episode records to prune: " . count($pruneIds));

        if ($fix && count($pruneIds) > 0) {
            DB::transaction(function () use ($pruneIds) {
                Episode::whereIn('id', $pruneIds)->delete();
            });
            $this->info("  ✓ Successfully pruned " . count($pruneIds) . " phantom episode records from database.");
        } elseif (!$fix && count($pruneIds) > 0) {
            $this->comment("  [Dry-run] Run with --fix to delete these " . count($pruneIds) . " phantom records.");
        }
    }

    /**
     * Synchronize movie resolutions with physical video files.
     */
    protected function syncMovieResolutions(bool $fix): void
    {
        $this->newLine();
        $this->info("--- 2. Checking Movie Resolutions Against Physical Files ---");

        $movies = MediaItem::all(['id', 'title', 'resolution', 'file_path']);
        $updates = 0;

        foreach ($movies as $m) {
            if (!file_exists($m->file_path)) continue;

            $fn = basename($m->file_path);
            $fnRes = $this->detectResolutionFromFilename($fn);
            if (!$fnRes) continue;

            $currentRes = $m->resolution ?? '';
            $currentNorm = strtolower($currentRes);

            $needsUpdate = false;
            if (empty($currentNorm) || $currentNorm === 'unknown') {
                $needsUpdate = true;
            } elseif ($fnRes === '4K' && strpos($currentNorm, '4k') === false && strpos($currentNorm, '2160') === false) {
                $needsUpdate = true;
            } elseif ($fnRes === '1080p' && strpos($currentNorm, '1080') === false) {
                $needsUpdate = true;
            } elseif ($fnRes === '720p' && strpos($currentNorm, '720') === false) {
                $needsUpdate = true;
            } elseif ($fnRes === '480p' && strpos($currentNorm, '480') === false && strpos($currentNorm, '576') === false && strpos($currentNorm, 'sd') === false) {
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $formattedRes = $this->formatStandardResolution($fnRes);
                $this->line("    * [ID {$m->id}] {$m->title}: '{$currentRes}' -> '{$formattedRes}' (File: {$fn})");
                $updates++;

                if ($fix) {
                    $m->resolution = $formattedRes;
                    $m->save();
                }
            }
        }

        $this->info("  Movies with updated resolutions: {$updates}");
    }

    /**
     * Synchronize episode resolutions with physical video files.
     */
    protected function syncEpisodeResolutions(bool $fix): void
    {
        $this->newLine();
        $this->info("--- 3. Checking Episode Resolutions Against Physical Files ---");

        $episodes = Episode::all(['id', 'series_id', 'season_id', 'episode_number', 'resolution', 'file_path']);
        $updates = 0;

        foreach ($episodes as $ep) {
            if (!file_exists($ep->file_path)) continue;

            $fn = basename($ep->file_path);
            $fnRes = $this->detectResolutionFromFilename($fn);
            if (!$fnRes) continue;

            $currentRes = $ep->resolution ?? '';
            $currentNorm = strtolower($currentRes);

            $needsUpdate = false;
            if (empty($currentNorm) || $currentNorm === 'unknown') {
                $needsUpdate = true;
            } elseif ($fnRes === '4K' && strpos($currentNorm, '4k') === false && strpos($currentNorm, '2160') === false) {
                $needsUpdate = true;
            } elseif ($fnRes === '1080p' && strpos($currentNorm, '1080') === false) {
                $needsUpdate = true;
            } elseif ($fnRes === '720p' && strpos($currentNorm, '720') === false) {
                $needsUpdate = true;
            } elseif ($fnRes === '480p' && strpos($currentNorm, '480') === false && strpos($currentNorm, '576') === false && strpos($currentNorm, 'sd') === false) {
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $formattedRes = $this->formatStandardResolution($fnRes);
                $updates++;

                if ($fix) {
                    $ep->resolution = $formattedRes;
                    $ep->save();
                }
            }
        }

        $this->info("  Episodes with updated resolutions: {$updates}");
    }

    protected function detectResolutionFromFilename(string $filename): ?string
    {
        if (preg_match('/(2160p|4k|uhd)/i', $filename)) return '4K';
        if (preg_match('/(1080p|fhd)/i', $filename)) return '1080p';
        if (preg_match('/(720p|hd)/i', $filename)) return '720p';
        if (preg_match('/(480p|576p|sd)/i', $filename)) return '480p';
        return null;
    }

    protected function formatStandardResolution(string $res): string
    {
        return match($res) {
            '4K' => '4K UHD',
            '1080p' => '1080p FHD',
            '720p' => '720p HD',
            '480p' => '480p SD',
            default => $res,
        };
    }
}
