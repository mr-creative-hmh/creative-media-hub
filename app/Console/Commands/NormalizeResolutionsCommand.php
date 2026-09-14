<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Services\Organizer\SceneNameParserService;
use App\Services\Scanner\MediaProbeService;
use Illuminate\Console\Command;

class NormalizeResolutionsCommand extends Command
{
    protected $signature = 'library:normalize-resolutions {--fix : Apply corrections to the database}';

    protected $description = 'Audit and normalize video resolution metadata for movies and series episodes.';

    public function handle(MediaProbeService $mediaProbe, SceneNameParserService $parser): int
    {
        $isFix = (bool) $this->option('fix');

        $this->info('=== Auditing Video Resolutions (Movies & Episodes) ===');
        $this->comment($isFix ? 'Mode: FIX (Applying changes to database)' : 'Mode: DRY-RUN (Pass --fix to apply changes)');

        $movieUpdates = 0;
        $episodeUpdates = 0;

        // 1. Audit Movies
        $movies = MediaItem::query()->whereNotNull('file_path')->get();
        $this->info("Scanning {$movies->count()} movie records...");

        foreach ($movies as $movie) {
            $currentRes = $movie->resolution;
            $probeRes = null;

            if (file_exists($movie->file_path)) {
                $probeData = $mediaProbe->probe($movie->file_path);
                if (! empty($probeData['resolution']) && $probeData['resolution'] !== 'Unknown') {
                    $probeRes = $probeData['resolution'];
                }
            }

            $parsed = $parser->parse($movie->file_path);
            $parsedRes = ! empty($parsed['resolution']) && $parsed['resolution'] !== 'Unknown' ? $parsed['resolution'] : null;

            $newRes = $mediaProbe->resolveBestResolution($probeRes, $parsedRes);

            if ($newRes && $newRes !== 'Unknown' && $currentRes !== $newRes) {
                $this->line("Movie #{$movie->id} '{$movie->title}': [{$currentRes}] -> [{$newRes}]");
                $movieUpdates++;

                if ($isFix) {
                    $movie->resolution = $newRes;
                    $movie->save();
                }
            }
        }

        // 2. Audit Episodes
        $episodes = Episode::query()->whereNotNull('file_path')->get();
        $this->info("Scanning {$episodes->count()} episode records...");

        foreach ($episodes as $episode) {
            $currentRes = $episode->resolution;
            $probeRes = null;

            if (file_exists($episode->file_path)) {
                $probeData = $mediaProbe->probe($episode->file_path);
                if (! empty($probeData['resolution']) && $probeData['resolution'] !== 'Unknown') {
                    $probeRes = $probeData['resolution'];
                }
            }

            $parsed = $parser->parse($episode->file_path);
            $parsedRes = ! empty($parsed['resolution']) && $parsed['resolution'] !== 'Unknown' ? $parsed['resolution'] : null;

            $newRes = $mediaProbe->resolveBestResolution($probeRes, $parsedRes);

            if ($newRes && $newRes !== 'Unknown' && $currentRes !== $newRes) {
                $this->line("Episode #{$episode->id} '{$episode->title}': [{$currentRes}] -> [{$newRes}]");
                $episodeUpdates++;

                if ($isFix) {
                    $episode->resolution = $newRes;
                    $episode->save();
                }
            }
        }

        $this->newLine();
        $this->info("Audit finished! Found {$movieUpdates} movies and {$episodeUpdates} episodes with outdated resolutions.");
        if ($isFix) {
            $this->info('All records updated successfully in the database.');
        } else {
            $this->comment('Run with `php artisan library:normalize-resolutions --fix` to update these records.');
        }

        return Command::SUCCESS;
    }
}
