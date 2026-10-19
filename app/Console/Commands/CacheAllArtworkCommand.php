<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Person;
use App\Models\Season;
use App\Models\Series;
use App\Services\Metadata\ArtworkDownloadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CacheAllArtworkCommand extends Command
{
    protected $signature = 'library:cache-all-artwork
                            {--stills : Also download and cache all episode stills}
                            {--force : Force re-verification of all artwork}';

    protected $description = 'Downloads and caches all remote posters, backdrops, and cast profiles locally for 100% offline usage.';

    public function handle(ArtworkDownloadService $downloader): int
    {
        $this->info('=== Creative Media Hub: Offline Artwork Cacher ===');
        $this->line('Verifying and caching library artwork locally for 100% offline playback...');
        $this->newLine();

        $withStills = (bool) $this->option('stills');

        $stats = [
            'movie_posters' => 0,
            'movie_backdrops' => 0,
            'series_posters' => 0,
            'series_backdrops' => 0,
            'season_posters' => 0,
            'episode_stills' => 0,
            'people_profiles' => 0,
            'collection_posters' => 0,
        ];

        // 1. Movies (MediaItem)
        $this->info('Scanning Movies needing artwork cache...');
        $movies = MediaItem::query()
            ->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNotNull('poster_path')->where('poster_path', 'not like', '/storage/posters/%');
                })->orWhere(function ($sq) {
                    $sq->whereNotNull('backdrop_path')->where('backdrop_path', 'not like', '/storage/backdrops/%');
                })->orWhere(function ($sq) {
                    $sq->whereNotNull('collection_poster')->where('collection_poster', 'not like', '/storage/posters/%');
                });
            })
            ->get();

        $this->line("Found {$movies->count()} movies with non-local artwork.");

        foreach ($movies as $movie) {
            $changed = false;

            if ($movie->poster_path && ! str_starts_with($movie->poster_path, '/storage/posters/')) {
                $local = $downloader->downloadPoster($movie->poster_path);
                if ($local && $local !== $movie->poster_path) {
                    $movie->poster_path = $local;
                    $changed = true;
                    $stats['movie_posters']++;
                }
            }

            if ($movie->backdrop_path && ! str_starts_with($movie->backdrop_path, '/storage/backdrops/')) {
                $local = $downloader->downloadBackdrop($movie->backdrop_path);
                if ($local && $local !== $movie->backdrop_path) {
                    $movie->backdrop_path = $local;
                    $changed = true;
                    $stats['movie_backdrops']++;
                }
            }

            if ($movie->collection_poster && ! str_starts_with($movie->collection_poster, '/storage/posters/')) {
                $local = $downloader->downloadPoster($movie->collection_poster);
                if ($local && $local !== $movie->collection_poster) {
                    $movie->collection_poster = $local;
                    $changed = true;
                    $stats['collection_posters']++;
                }
            }

            if ($changed) {
                $movie->saveQuietly();
            }
        }

        // 2. TV Series
        $this->info('Scanning TV Series needing artwork cache...');
        $seriesList = Series::query()
            ->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNotNull('poster_path')->where('poster_path', 'not like', '/storage/posters/%');
                })->orWhere(function ($sq) {
                    $sq->whereNotNull('backdrop_path')->where('backdrop_path', 'not like', '/storage/backdrops/%');
                });
            })
            ->get();

        $this->line("Found {$seriesList->count()} series with non-local artwork.");

        foreach ($seriesList as $series) {
            $changed = false;

            if ($series->poster_path && ! str_starts_with($series->poster_path, '/storage/posters/')) {
                $local = $downloader->downloadPoster($series->poster_path);
                if ($local && $local !== $series->poster_path) {
                    $series->poster_path = $local;
                    $changed = true;
                    $stats['series_posters']++;
                }
            }

            if ($series->backdrop_path && ! str_starts_with($series->backdrop_path, '/storage/backdrops/')) {
                $local = $downloader->downloadBackdrop($series->backdrop_path);
                if ($local && $local !== $series->backdrop_path) {
                    $series->backdrop_path = $local;
                    $changed = true;
                    $stats['series_backdrops']++;
                }
            }

            if ($changed) {
                $series->saveQuietly();
            }
        }

        // 3. TV Seasons
        $this->info('Scanning TV Seasons needing artwork cache...');
        $seasons = Season::whereNotNull('poster_path')
            ->where('poster_path', 'not like', '/storage/posters/%')
            ->get();

        $this->line("Found {$seasons->count()} seasons with non-local artwork.");

        foreach ($seasons as $season) {
            $local = $downloader->downloadPoster($season->poster_path);
            if ($local && $local !== $season->poster_path) {
                $season->poster_path = $local;
                $season->saveQuietly();
                $stats['season_posters']++;
            }
        }

        // 4. TV Episodes (if requested)
        if ($withStills) {
            $this->info('Scanning TV Episodes needing still cache...');
            $episodes = Episode::whereNotNull('still_path')
                ->where('still_path', 'not like', '/storage/stills/%')
                ->get();

            $this->line("Found {$episodes->count()} episode stills to cache.");

            foreach ($episodes as $episode) {
                $local = $downloader->downloadStill($episode->still_path);
                if ($local && $local !== $episode->still_path) {
                    $episode->still_path = $local;
                    $episode->saveQuietly();
                    $stats['episode_stills']++;
                }
            }
        }

        // 5. Cast & Crew (People)
        $this->info('Scanning Cast & Crew Profiles...');
        $people = Person::whereNotNull('profile_path')
            ->where('profile_path', 'not like', '/storage/profiles/%')
            ->get();

        $this->line("Found {$people->count()} cast profiles with non-local artwork.");

        foreach ($people as $person) {
            $local = $downloader->downloadProfile($person->profile_path);
            if ($local && $local !== $person->profile_path) {
                $person->profile_path = $local;
                $person->saveQuietly();
                $stats['people_profiles']++;
            }
        }

        // 6. Master Index Extended Collections
        $extendedPath = storage_path('app/metadata-index/collections_extended.json');
        if (File::exists($extendedPath)) {
            $this->info('Processing Master Index Extended Collections...');
            $collections = json_decode(File::get($extendedPath), true) ?: [];
            $modified = false;

            foreach ($collections as &$col) {
                if (! empty($col['poster_path']) && ! str_starts_with($col['poster_path'], '/storage/posters/')) {
                    $local = $downloader->downloadPoster($col['poster_path']);
                    if ($local) {
                        $col['poster_path'] = $local;
                        $modified = true;
                        $stats['collection_posters']++;
                    }
                }

                if (! empty($col['backdrop_path']) && ! str_starts_with($col['backdrop_path'], '/storage/backdrops/')) {
                    $local = $downloader->downloadBackdrop($col['backdrop_path']);
                    if ($local) {
                        $col['backdrop_path'] = $local;
                        $modified = true;
                    }
                }

                if (! empty($col['parts']) && is_array($col['parts'])) {
                    foreach ($col['parts'] as &$part) {
                        if (! empty($part['poster_path']) && ! str_starts_with($part['poster_path'], '/storage/posters/')) {
                            $local = $downloader->downloadPoster($part['poster_path']);
                            if ($local) {
                                $part['poster_path'] = $local;
                                $modified = true;
                                $stats['collection_posters']++;
                            }
                        }
                    }
                }
            }

            if ($modified) {
                File::put($extendedPath, json_encode($collections, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }

        $this->newLine();
        $this->table(
            ['Artwork Category', 'Newly Cached Locally'],
            [
                ['Movie Posters', $stats['movie_posters']],
                ['Movie Backdrops', $stats['movie_backdrops']],
                ['Series Posters', $stats['series_posters']],
                ['Series Backdrops', $stats['series_backdrops']],
                ['Season Posters', $stats['season_posters']],
                ['Episode Stills', $stats['episode_stills']],
                ['Cast & Crew Profiles', $stats['people_profiles']],
                ['Collection & Missing Part Posters', $stats['collection_posters']],
            ]
        );

        $this->info('Offline artwork verification and caching completed successfully.');

        return Command::SUCCESS;
    }
}
