<?php

namespace App\Services\Metadata;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebArtworkSearchService
{
    protected ArtworkDownloadService $artworkDownloader;

    public function __construct(ArtworkDownloadService $artworkDownloader)
    {
        $this->artworkDownloader = $artworkDownloader;
    }

    public function searchAndDownloadArtwork(string $title, ?int $year = null, string $type = 'movie'): ?string
    {
        $cleanTitle = trim($title);
        if (empty($cleanTitle)) return null;

        // 1. Try Wikipedia MediaWiki API image lookup
        $wikiImage = $this->searchWikipediaArtwork($cleanTitle, $year);
        if ($wikiImage) {
            $local = $this->artworkDownloader->downloadPoster($wikiImage);
            if ($local) return $local;
        }

        // 2. Try DuckDuckGo / Open Image Thumbnails API
        $ddgImage = $this->searchOpenWebArtwork($cleanTitle, $year, $type);
        if ($ddgImage) {
            $local = $this->artworkDownloader->downloadPoster($ddgImage);
            if ($local) return $local;
        }

        return null;
    }

    protected function searchWikipediaArtwork(string $title, ?int $year = null): ?string
    {
        try {
            $query = $year ? "{$title} ({$year} film)" : $title;
            $res = Http::timeout(6)->get("https://en.wikipedia.org/w/api.php", [
                'action' => 'query',
                'titles' => $query,
                'prop' => 'pageimages',
                'format' => 'json',
                'pithumbsize' => 800,
            ]);

            if ($res->successful()) {
                $pages = $res->json('query.pages') ?? [];
                foreach ($pages as $page) {
                    if (!empty($page['thumbnail']['source'])) {
                        return $page['thumbnail']['source'];
                    }
                }
            }

            // General search if exact title doesn't hit
            $searchRes = Http::timeout(6)->get("https://en.wikipedia.org/w/api.php", [
                'action' => 'query',
                'generator' => 'search',
                'gsrsearch' => "{$title} poster",
                'gsrlimit' => 3,
                'prop' => 'pageimages',
                'pithumbsize' => 800,
                'format' => 'json',
            ]);

            if ($searchRes->successful()) {
                $pages = $searchRes->json('query.pages') ?? [];
                foreach ($pages as $page) {
                    if (!empty($page['thumbnail']['source'])) {
                        return $page['thumbnail']['source'];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Wikipedia artwork search failed for {$title}: " . $e->getMessage());
        }

        return null;
    }

    protected function searchOpenWebArtwork(string $title, ?int $year = null, string $type = 'movie'): ?string
    {
        try {
            // DuckDuckGo instant answer or image endpoint
            $q = urlencode("{$title} {$year} {$type} poster");
            $url = "https://api.duckduckgo.com/?q={$q}&format=json&pretty=1";
            $res = Http::timeout(5)->get($url);

            if ($res->successful()) {
                $image = $res->json('Image');
                if (!empty($image) && filter_var($image, FILTER_VALIDATE_URL)) {
                    return $image;
                }
            }
        } catch (\Throwable $e) {}

        return null;
    }
}
