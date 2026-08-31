<?php

namespace App\Services\Subtitles;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenSubtitlesService
{
    protected string $baseUrl = 'https://api.opensubtitles.com/api/v1';
    protected ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?: config('services.opensubtitles.key', 't8p0K0p0W8xQ4X1c0w');
    }

    public function searchSubtitles(array $params): array
    {
        try {
            $headers = [
                'User-Agent' => 'CreativeMediaCinema v1.0',
                'Api-Key' => $this->apiKey,
            ];

            $queryParams = [
                'languages' => $params['languages'] ?? 'ar,en',
            ];

            if (!empty($params['imdb_id'])) {
                $queryParams['imdb_id'] = preg_replace('/[^0-9]/', '', $params['imdb_id']);
            } elseif (!empty($params['tmdb_id'])) {
                $queryParams['tmdb_id'] = $params['tmdb_id'];
            } elseif (!empty($params['query'])) {
                $queryParams['query'] = $params['query'];
            }

            if (!empty($params['season_number'])) {
                $queryParams['season_number'] = $params['season_number'];
            }
            if (!empty($params['episode_number'])) {
                $queryParams['episode_number'] = $params['episode_number'];
            }

            $response = Http::timeout(6)->withHeaders($headers)->get("{$this->baseUrl}/subtitles", $queryParams);

            if ($response->successful()) {
                $data = $response->json('data', []);
                return array_map(function ($item) {
                    $attr = $item['attributes'] ?? [];
                    return [
                        'provider' => 'OpenSubtitles',
                        'subtitle_id' => (string) ($attr['files'][0]['file_id'] ?? $item['id']),
                        'language' => $attr['language'] ?? 'en',
                        'release' => $attr['release'] ?? '',
                        'downloads' => $attr['download_count'] ?? 0,
                        'rating' => $attr['ratings'] ?? 0,
                        'file_name' => $attr['files'][0]['file_name'] ?? 'subtitle.srt',
                        'download_url' => $attr['files'][0]['file_id'] ?? null,
                    ];
                }, $data);
            }
        } catch (\Exception $e) {
            Log::warning("OpenSubtitles search failed: " . $e->getMessage());
        }

        return [];
    }

    public function computeFileHash(string $filePath): ?string
    {
        if (!File::exists($filePath) || File::size($filePath) < 65536) {
            return null;
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) return null;

        $fileSize = filesize($filePath);
        $hash = [$fileSize & 0xFFFF, ($fileSize >> 16) & 0xFFFF, 0, 0];

        for ($i = 0; $i < 8192; $i++) {
            $tmp = unpack('v', fread($handle, 2))[1];
            $hash[0] += $tmp;
        }

        fseek($handle, $fileSize - 65536);
        for ($i = 0; $i < 8192; $i++) {
            $tmp = unpack('v', fread($handle, 2))[1];
            $hash[0] += $tmp;
        }

        fclose($handle);

        return sprintf('%04x%04x%04x%04x', $hash[3] & 0xFFFF, $hash[2] & 0xFFFF, $hash[1] & 0xFFFF, $hash[0] & 0xFFFF);
    }
}
