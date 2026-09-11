<?php

namespace App\Console\Commands;

use App\Models\Episode;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranslateMissingEpisodeDataCommand extends Command
{
    protected $signature = 'media:translate-episodes 
                            {--force : Force re-translation even if Arabic data already exists}
                            {--limit= : Limit the number of episodes to process}
                            {--chunk=15 : Number of concurrent translations per batch}';

    protected $description = 'Supercharged bilingual Arabic metadata translation for all library episodes';

    protected array $titleCache = [
        'pilot' => 'الحلقة التجريبية',
        'series premiere' => 'العرض الأول للمسلسل',
        'season premiere' => 'افتتاحية الموسم',
        'season finale' => 'ختام الموسم',
        'series finale' => 'ختام المسلسل',
        'part 1' => 'الجزء الأول',
        'part 2' => 'الجزء الثاني',
        'part 3' => 'الجزء الثالث',
        'part 4' => 'الجزء الرابع',
        'part 5' => 'الجزء الخامس',
        'part i' => 'الجزء الأول',
        'part ii' => 'الجزء الثاني',
        'part iii' => 'الجزء الثالث',
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $batchSize = max(5, min(30, (int) $this->option('chunk')));

        $query = Episode::query();

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('title_ar')
                  ->orWhere('title_ar', '')
                  ->orWhereNull('overview_ar')
                  ->orWhere('overview_ar', '');
            });
        }

        $totalCount = $query->count();
        $total = $limit ? min($limit, $totalCount) : $totalCount;

        if ($total === 0) {
            $this->info('All episodes already have complete Arabic metadata! Nothing to translate.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} episodes needing bilingual Arabic enrichment (Batch size: {$batchSize}).");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $titlesTranslated = 0;
        $overviewsTranslated = 0;

        $targetEpisodes = $limit ? $query->take($limit)->get() : $query->get();

        foreach ($targetEpisodes->chunk($batchSize) as $chunk) {
            $poolCalls = [];

            foreach ($chunk as $ep) {
                $needsTitleAr = $force || empty(trim($ep->title_ar ?? ''));
                $needsOverviewAr = $force || empty(trim($ep->overview_ar ?? ''));

                // Check title
                if ($needsTitleAr && ! empty($ep->title)) {
                    $rawTitle = trim($ep->title);
                    $lowerTitle = strtolower($rawTitle);

                    if (preg_match('/[\x{0600}-\x{06FF}]/u', $rawTitle)) {
                        $ep->title_ar = $rawTitle;
                        $ep->save();
                        $titlesTranslated++;
                    } elseif (isset($this->titleCache[$lowerTitle])) {
                        $ep->title_ar = $this->titleCache[$lowerTitle];
                        $ep->save();
                        $titlesTranslated++;
                    } elseif (preg_match('/^(?:Episode|Ep\.?|Part|الحلقة)\s*(\d+)$/i', $rawTitle, $m)) {
                        $arTitle = "الحلقة {$m[1]}";
                        $this->titleCache[$lowerTitle] = $arTitle;
                        $ep->title_ar = $arTitle;
                        $ep->save();
                        $titlesTranslated++;
                    } else {
                        // Queue for pool request
                        $poolCalls["t_{$ep->id}"] = [
                            'type' => 'title',
                            'ep' => $ep,
                            'text' => $rawTitle,
                        ];
                    }
                }

                // Check overview
                if ($needsOverviewAr && ! empty($ep->overview)) {
                    $rawOv = trim(strip_tags($ep->overview));
                    if (preg_match('/^[\x{0600}-\x{06FF}\s\d\p{P}]+$/u', $rawOv)) {
                        $ep->overview_ar = $rawOv;
                        $ep->save();
                        $overviewsTranslated++;
                    } elseif (! empty($rawOv)) {
                        $poolCalls["o_{$ep->id}"] = [
                            'type' => 'overview',
                            'ep' => $ep,
                            'text' => mb_substr($rawOv, 0, 700),
                        ];
                    }
                }
            }

            // Execute concurrent requests if any queued
            if (! empty($poolCalls)) {
                try {
                    $responses = Http::pool(function (Pool $pool) use ($poolCalls) {
                        $reqs = [];
                        foreach ($poolCalls as $key => $call) {
                            $reqs[] = $pool->as($key)->timeout(7)->get('https://clients5.google.com/translate_a/t', [
                                'client' => 'dict-chrome-ex',
                                'sl' => 'en',
                                'tl' => 'ar',
                                'q' => $call['text'],
                            ]);
                        }
                        return $reqs;
                    });

                    foreach ($poolCalls as $key => $call) {
                        $res = $responses[$key] ?? null;
                        $ep = $call['ep'];
                        $translated = null;

                        if ($res && $res->successful()) {
                            $d = $res->json();
                            $str = is_array($d) ? implode(' ', $d) : (string) $d;
                            if (! empty(trim($str)) && trim($str) !== $call['text']) {
                                $translated = trim($str);
                            }
                        }

                        // MyMemory fallback if Google failed
                        if (! $translated) {
                            $translated = $this->fallbackTranslate($call['text']);
                        }

                        if ($translated) {
                            if ($call['type'] === 'title') {
                                $ep->title_ar = $translated;
                                $this->titleCache[strtolower($call['text'])] = $translated;
                                $titlesTranslated++;
                            } elseif ($call['type'] === 'overview') {
                                $ep->overview_ar = $translated;
                                $overviewsTranslated++;
                            }
                            $ep->save();
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Translation pool batch exception: '.$e->getMessage());
                }
            }

            $bar->advance($chunk->count());
            usleep(20000); // 20ms
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Translation & Bilingual Enrichment Complete!');
        $this->table(['Metric', 'Count'], [
            ['Total Episodes Checked', $total],
            ['Titles Translated to Arabic', $titlesTranslated],
            ['Overviews Translated to Arabic', $overviewsTranslated],
        ]);

        return self::SUCCESS;
    }

    protected function fallbackTranslate(string $text): ?string
    {
        try {
            $res = Http::timeout(5)->get('https://api.mymemory.translated.net/get', [
                'q' => mb_substr($text, 0, 450),
                'langpair' => 'en|ar',
            ]);

            if ($res->successful()) {
                $trans = $res->json('responseData.translatedText');
                if ($trans && ! str_contains($trans, 'MYMEMORY WARNING') && strtolower(trim($trans)) !== strtolower($text)) {
                    return trim($trans);
                }
            }
        } catch (\Throwable $e) {
            // Silence fallback errors
        }

        return null;
    }
}
