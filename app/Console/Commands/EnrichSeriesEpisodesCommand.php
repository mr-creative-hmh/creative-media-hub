<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Series;
use App\Services\Metadata\MetadataAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnrichSeriesEpisodesCommand extends Command
{
    protected $signature = 'media:enrich-episodes 
                            {--series= : Specific Series ID or TMDb ID to enrich} 
                            {--force : Force overwrite existing episode titles even if already custom}';

    protected $description = 'Enrich TV series episodes with real titles (English & Arabic), overviews, stills, and ratings from TMDb';

    public function handle(MetadataAggregator $metadata): int
    {
        $seriesOption = $this->option('series');
        $force = (bool) $this->option('force');

        $query = Series::with(['seasons.episodes'])->whereNotNull('tmdb_id');

        if ($seriesOption) {
            $query->where(function ($q) use ($seriesOption) {
                $q->where('id', $seriesOption)
                  ->orWhere('tmdb_id', $seriesOption)
                  ->orWhere('title', 'like', "%{$seriesOption}%");
            });
        }

        $seriesList = $query->orderBy('title')->get();
        $totalSeries = $seriesList->count();

        if ($totalSeries === 0) {
            $this->warn('No series found with a valid TMDb ID.');
            return self::SUCCESS;
        }

        $this->info("Starting episode enrichment for {$totalSeries} series...");

        $totalEnriched = 0;
        $totalSkipped = 0;

        $bar = $this->output->createProgressBar($totalSeries);
        $bar->start();

        foreach ($seriesList as $series) {
            $tmdbId = $series->tmdb_id;
            if (! $tmdbId) {
                $bar->advance();
                continue;
            }

            foreach ($series->seasons as $season) {
                $sNum = (int) $season->season_number;
                $episodes = $season->episodes;

                if ($episodes->isEmpty()) {
                    continue;
                }

                // Check if any episode needs enrichment (or force is on)
                $needsEnrichment = $force || $episodes->contains(function ($ep) {
                    $raw = trim($ep->title ?? '');
                    return empty($raw) || preg_match('/^(?:Episode|Ep|Part|الحلقة)\s*\d+$/i', $raw) || preg_match('/^S\d+E\d+$/i', $raw);
                });

                if (! $needsEnrichment) {
                    $totalSkipped += $episodes->count();
                    continue;
                }

                // Fetch season episodes from TMDb bilingual
                $tmdbEps = $metadata->getSeasonEpisodesBilingual($tmdbId, $sNum, 'tmdb');

                foreach ($episodes as $episode) {
                    $eNum = (int) $episode->episode_number;
                    $tmdbEp = $tmdbEps[$eNum] ?? null;

                    $isGeneric = preg_match('/^(?:Episode|Ep|Part|الحلقة)\s*\d+$/i', trim($episode->title ?? '')) || preg_match('/^S\d+E\d+$/i', trim($episode->title ?? '')) || empty(trim($episode->title ?? ''));

                    // Special case for Black Mirror S00E01 Bandersnatch
                    if ($sNum === 0 && $eNum === 1 && stripos($series->title, 'Black Mirror') !== false) {
                        $episode->update([
                            'title' => 'Bandersnatch',
                            'title_ar' => 'باندرسناتش',
                            'overview' => $episode->overview ?: 'In 1984, a young programmer begins to question reality as he adapts a dark fantasy novel into a video game.',
                            'overview_ar' => $episode->overview_ar ?: 'في عام 1984، يبدأ مبرمج شاب بالتشكيك في واقعه أثناء تحويله رواية خيال علمي سوداوية إلى لعبة فيديو.',
                        ]);
                        $totalEnriched++;
                        continue;
                    }

                    if ($tmdbEp) {
                        $updateData = [];

                        if ($force || $isGeneric) {
                            $updateData['title'] = $tmdbEp['title'];
                        }
                        if ($force || empty($episode->title_ar)) {
                            if (! empty($tmdbEp['title_ar'])) {
                                $updateData['title_ar'] = $tmdbEp['title_ar'];
                            }
                        }
                        if ($force || empty($episode->overview) || preg_match('/^Episode\s*\d+$/i', trim($episode->overview ?? ''))) {
                            if (! empty($tmdbEp['overview'])) {
                                $updateData['overview'] = $tmdbEp['overview'];
                            }
                        }
                        if ($force || empty($episode->overview_ar)) {
                            if (! empty($tmdbEp['overview_ar'])) {
                                $updateData['overview_ar'] = $tmdbEp['overview_ar'];
                            }
                        }
                        if ($force || empty($episode->still_path)) {
                            if (! empty($tmdbEp['still_path'])) {
                                $updateData['still_path'] = $tmdbEp['still_path'];
                            }
                        }
                        if ($force || empty($episode->rating) || $episode->rating == 0) {
                            if (! empty($tmdbEp['rating'])) {
                                $updateData['rating'] = $tmdbEp['rating'];
                            }
                        }
                        if ($force || empty($episode->air_date)) {
                            if (! empty($tmdbEp['air_date'])) {
                                $updateData['air_date'] = $tmdbEp['air_date'];
                            }
                        }
                        if (! empty($tmdbEp['runtime_minutes']) && ($force || empty($episode->runtime_minutes) || $episode->runtime_minutes == 45)) {
                            $updateData['runtime_minutes'] = $tmdbEp['runtime_minutes'];
                        }

                        if (! empty($updateData)) {
                            $episode->update($updateData);
                            $totalEnriched++;
                        } else {
                            $totalSkipped++;
                        }
                    } else {
                        $totalSkipped++;
                    }
                }

                // Small gentle sleep (30ms) to respect TMDb rate limits nicely
                usleep(30000);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Enrichment complete! {$totalEnriched} episodes enriched, {$totalSkipped} untouched/skipped.");

        return self::SUCCESS;
    }
}