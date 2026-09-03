<?php

namespace App\Services\Subtitles;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubDlService
{
    protected string $baseUrl = 'https://api.subdl.com/api/v1/subtitles';

    protected ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?: AppSetting::get('subdl_api_key', config('services.subdl.key'));
    }

    public function searchSubtitles(string $title, ?int $year = null, array $languages = ['AR', 'EN'], string $type = 'movie', ?int $season = null, ?int $episode = null): array
    {
        if (empty($this->apiKey)) {
            return [];
        }

        try {
            $params = [
                'api_key' => $this->apiKey,
                'film_name' => $title,
                'type' => ($type === 'series' || $type === 'tv' || $type === 'episode') ? 'tv' : 'movie',
                'languages' => implode(',', array_map('strtoupper', $languages)),
            ];

            if ($year) {
                $params['year'] = $year;
            }

            if ($season !== null && $season > 0) {
                $params['season_number'] = $season;
            }

            if ($episode !== null && $episode > 0) {
                $params['episode_number'] = $episode;
            }

            $response = Http::timeout(6)->get($this->baseUrl, $params);

            if ($response->successful() && $response->json('status')) {
                $subtitles = $response->json('subtitles', []);

                return array_map(function ($s) {
                    $dlUrl = $s['url'] ?? null;
                    if ($dlUrl && ! str_starts_with($dlUrl, 'http')) {
                        $dlUrl = 'https://dl.subdl.com'.$dlUrl;
                    }

                    return [
                        'provider' => 'SubDL',
                        'subtitle_id' => (string) ($s['id'] ?? uniqid()),
                        'language' => strtolower($s['language'] ?? 'en'),
                        'release' => $s['release_name'] ?? $s['name'] ?? '',
                        'file_name' => $s['name'] ?? 'subtitle.srt',
                        'download_url' => $dlUrl,
                        'downloads' => (int) ($s['download_count'] ?? 0),
                        'rating' => 9.5,
                    ];
                }, $subtitles);
            }
        } catch (\Exception $e) {
            Log::warning('SubDL search failed: '.$e->getMessage());
        }

        return [];
    }
}
