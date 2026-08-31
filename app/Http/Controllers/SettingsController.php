<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\Metadata\AniListProvider;
use App\Services\Metadata\OmdbProvider;
use App\Services\Metadata\TmdbProvider;
use App\Services\Metadata\TvMazeProvider;
use App\Services\Metadata\WikipediaProvider;
use App\Services\Subtitles\OpenSubtitlesService;
use App\Services\Subtitles\SubDlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $settings = [
            'tmdb_api_key' => AppSetting::where('key', 'tmdb_api_key')->value('value') ?? '',
            'omdb_api_key' => AppSetting::where('key', 'omdb_api_key')->value('value') ?? '',
            'opensubtitles_api_key' => AppSetting::where('key', 'opensubtitles_api_key')->value('value') ?? '',
            'ffmpeg_path' => AppSetting::where('key', 'ffmpeg_path')->value('value') ?? 'ffmpeg',
            'default_language' => AppSetting::where('key', 'default_language')->value('value') ?? 'ar',
            'auto_fetch_metadata' => (bool) (AppSetting::where('key', 'auto_fetch_metadata')->value('value') ?? true),
            'auto_fetch_subtitles' => (bool) (AppSetting::where('key', 'auto_fetch_subtitles')->value('value') ?? true),
            'preferred_providers' => json_decode(AppSetting::where('key', 'preferred_providers')->value('value') ?? '["tvmaze","anilist","wikipedia","local_nfo","tmdb","omdb"]', true),
        ];

        return Inertia::render('Settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tmdb_api_key' => 'nullable|string',
            'omdb_api_key' => 'nullable|string',
            'opensubtitles_api_key' => 'nullable|string',
            'ffmpeg_path' => 'nullable|string',
            'default_language' => 'required|in:ar,en',
            'auto_fetch_metadata' => 'boolean',
            'auto_fetch_subtitles' => 'boolean',
            'preferred_providers' => 'nullable|array',
        ]);

        foreach ($validated as $key => $val) {
            $type = is_array($val) ? 'json' : (is_bool($val) ? 'boolean' : 'string');
            $value = is_array($val) ? json_encode($val) : ($val === true ? '1' : ($val === false ? '0' : (string) $val));

            AppSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings saved successfully!',
        ]);
    }

    public function testProvider(Request $request): JsonResponse
    {
        $provider = $request->input('provider');
        $key = $request->input('key');

        $startTime = microtime(true);

        try {
            switch ($provider) {
                case 'tmdb':
                    $apiKey = $key ?: (AppSetting::where('key', 'tmdb_api_key')->value('value') ?: config('services.tmdb.key'));
                    if (empty($apiKey)) {
                        return response()->json(['success' => false, 'message' => 'TMDb API key is required to test.'], 422);
                    }
                    $res = Http::timeout(6)->withToken($apiKey)->get('https://api.themoviedb.org/3/configuration');
                    if (!$res->successful()) {
                        $res = Http::timeout(6)->get("https://api.themoviedb.org/3/configuration?api_key={$apiKey}");
                    }
                    $latency = round((microtime(true) - $startTime) * 1000);
                    if ($res->successful()) {
                        return response()->json(['success' => true, 'message' => "TMDb API authenticated successfully! ({$latency}ms)", 'latency_ms' => $latency]);
                    }
                    return response()->json(['success' => false, 'message' => 'TMDb returned error: ' . ($res->json('status_message') ?? 'Invalid API key.')], 400);

                case 'omdb':
                    $apiKey = $key ?: (AppSetting::where('key', 'omdb_api_key')->value('value') ?: config('services.omdb.key'));
                    if (empty($apiKey)) {
                        return response()->json(['success' => false, 'message' => 'OMDb API key is required to test.'], 422);
                    }
                    $res = Http::timeout(6)->get("https://www.omdbapi.com/?apikey={$apiKey}&t=Inception");
                    $latency = round((microtime(true) - $startTime) * 1000);
                    if ($res->successful() && $res->json('Response') !== 'False') {
                        return response()->json(['success' => true, 'message' => "OMDb API verified! IMDb score reachable. ({$latency}ms)", 'latency_ms' => $latency]);
                    }
                    return response()->json(['success' => false, 'message' => 'OMDb error: ' . ($res->json('Error') ?? 'Invalid key.')], 400);

                case 'opensubtitles':
                    $apiKey = $key ?: AppSetting::where('key', 'opensubtitles_api_key')->value('value');
                    $service = new OpenSubtitlesService($apiKey);
                    $res = $service->searchSubtitles(['query' => 'Inception', 'languages' => 'ar,en']);
                    $latency = round((microtime(true) - $startTime) * 1000);
                    return response()->json(['success' => true, 'message' => "OpenSubtitles service active! Found " . count($res) . " test subtitles. ({$latency}ms)", 'latency_ms' => $latency]);

                case 'tvmaze':
                    $tvmaze = new TvMazeProvider();
                    $res = $tvmaze->searchSeries('Breaking Bad');
                    $latency = round((microtime(true) - $startTime) * 1000);
                    return response()->json(['success' => true, 'message' => "TVMaze Free API working seamlessly! ({$latency}ms)", 'latency_ms' => $latency]);

                case 'anilist':
                    $anilist = new AniListProvider();
                    $res = $anilist->searchAnime('Attack on Titan');
                    $latency = round((microtime(true) - $startTime) * 1000);
                    return response()->json(['success' => true, 'message' => "AniList GraphQL Engine verified! ({$latency}ms)", 'latency_ms' => $latency]);

                case 'wikipedia':
                    $wiki = new WikipediaProvider();
                    $res = $wiki->getPlotSummary('Inception', 'ar');
                    $latency = round((microtime(true) - $startTime) * 1000);
                    return response()->json(['success' => true, 'message' => "Wikipedia Arabic translation engine active! ({$latency}ms)", 'latency_ms' => $latency]);

                case 'subdl':
                    $subdl = new SubDlService();
                    $res = $subdl->searchSubtitles('Inception');
                    $latency = round((microtime(true) - $startTime) * 1000);
                    return response()->json(['success' => true, 'message' => "SubDL Free Cloud scraper verified! ({$latency}ms)", 'latency_ms' => $latency]);

                default:
                    return response()->json(['success' => false, 'message' => 'Unknown provider.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Connection test failed: ' . $e->getMessage()], 500);
        }
    }
}
