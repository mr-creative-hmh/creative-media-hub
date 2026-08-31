<?php

namespace App\Services\Subtitles;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubDlService
{
    protected string $baseUrl = 'https://api.subdl.com/api/v1/subtitles';

    public function searchSubtitles(string $title, ?int $year = null, array $languages = ['AR', 'EN']): array
    {
        try {
            $response = Http::timeout(6)->get($this->baseUrl, [
                'film_name' => $title,
                'type' => 'movie',
                'languages' => implode(',', $languages),
            ]);

            if ($response->successful() && $response->json('status')) {
                $subtitles = $response->json('subtitles', []);
                return array_map(function ($s) {
                    return [
                        'provider' => 'SubDL',
                        'subtitle_id' => (string) ($s['id'] ?? uniqid()),
                        'language' => strtolower($s['language'] ?? 'en'),
                        'release' => $s['release_name'] ?? $s['name'] ?? '',
                        'file_name' => $s['name'] ?? 'subtitle.srt',
                        'url' => $s['url'] ?? null,
                    ];
                }, $subtitles);
            }
        } catch (\Exception $e) {
            Log::warning("SubDL search failed: " . $e->getMessage());
        }

        return [];
    }
}
