<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Models\Subtitle;
use App\Models\WatchHistory;
use App\Services\Scanner\VirtualLibraryScannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScannerController extends Controller
{
    protected VirtualLibraryScannerService $scannerService;

    public function __construct(VirtualLibraryScannerService $scannerService)
    {
        $this->scannerService = $scannerService;
    }

    public function index(): Response
    {
        $setting = AppSetting::where('key', 'scanner_monitored_directories')->first();
        $directories = $setting ? json_decode($setting->value, true) : [];

        $totalMovies = MediaItem::count();
        $totalSeries = Series::count();
        $totalEpisodes = Episode::count();
        $totalSubs = Subtitle::count();

        $storageBytes = (int) MediaItem::sum('file_size_bytes') + (int) Episode::sum('file_size_bytes');

        return Inertia::render('Scanner/Index', [
            'directories' => $directories ?: [],
            'scanStatus' => $this->scannerService->getScanStatus(),
            'stats' => [
                'total_movies' => $totalMovies,
                'total_series' => $totalSeries,
                'total_episodes' => $totalEpisodes,
                'total_subtitles' => $totalSubs,
                'storage_size_formatted' => $this->formatBytes($storageBytes),
            ],
        ]);
    }

    public function addDirectory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string',
            'type' => 'required|string|in:movies,series,mixed',
        ]);

        $setting = AppSetting::firstOrCreate(
            ['key' => 'scanner_monitored_directories'],
            ['value' => json_encode([])]
        );

        $dirs = json_decode($setting->value, true) ?: [];

        $cleanPath = rtrim(str_replace('\\', '/', trim($validated['path'])), '/');

        // Prevent duplicate directory entries
        foreach ($dirs as $d) {
            if (rtrim(str_replace('\\', '/', $d['path']), '/') === $cleanPath) {
                return response()->json(['success' => true, 'directories' => $dirs]);
            }
        }

        $dirs[] = [
            'id' => uniqid('dir_'),
            'path' => $cleanPath,
            'type' => $validated['type'],
        ];

        $setting->update(['value' => json_encode($dirs)]);

        return response()->json([
            'success' => true,
            'message' => 'Directory added to monitored list.',
            'directories' => $dirs,
        ]);
    }

    public function removeDirectory($index): JsonResponse
    {
        $setting = AppSetting::where('key', 'scanner_monitored_directories')->first();
        if (!$setting) {
            return response()->json(['success' => true, 'directories' => []]);
        }

        $dirs = json_decode($setting->value, true) ?: [];

        if (is_numeric($index) && isset($dirs[$index])) {
            array_splice($dirs, (int) $index, 1);
        } else {
            $dirs = array_values(array_filter($dirs, fn($d) => ($d['id'] ?? '') !== $index && ($d['path'] ?? '') !== $index));
        }

        $setting->update(['value' => json_encode($dirs)]);

        return response()->json([
            'success' => true,
            'message' => 'Directory removed from monitored list.',
            'directories' => $dirs,
        ]);
    }

    public function startScan(Request $request): JsonResponse
    {
        $directories = $request->input('directories');

        if (empty($directories)) {
            $setting = AppSetting::where('key', 'scanner_monitored_directories')->first();
            $directories = $setting ? json_decode($setting->value, true) : [];
        }

        $initResult = $this->scannerService->initScan($directories ?: []);

        return response()->json([
            'success' => true,
            'init' => $initResult,
            'status' => $this->scannerService->getScanStatus(),
        ]);
    }

    public function rescanFresh(Request $request): JsonResponse
    {
        // 1. Wipe previous scanned database entries
        $this->wipeAllScannedMedia();

        // 2. Fetch monitored directories
        $setting = AppSetting::where('key', 'scanner_monitored_directories')->first();
        $directories = $setting ? json_decode($setting->value, true) : [];

        // 3. Initialize fresh scan
        $initResult = $this->scannerService->initScan($directories ?: []);

        return response()->json([
            'success' => true,
            'message' => 'Previous library wiped. Fresh scan started!',
            'init' => $initResult,
            'status' => $this->scannerService->getScanStatus(),
        ]);
    }

    public function scanFolder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string',
            'type' => 'nullable|string|in:movies,series,mixed',
            'fresh' => 'nullable|boolean',
        ]);

        $folderPath = rtrim(str_replace('\\', '/', trim($validated['path'])), '/');

        // If fresh is requested for this specific folder, wipe existing items from this path
        if (!empty($validated['fresh'])) {
            $this->wipeFolderMedia($folderPath);
        }

        $initResult = $this->scannerService->initScanForFolder($folderPath, $validated['type'] ?? 'mixed');

        return response()->json([
            'success' => true,
            'init' => $initResult,
            'status' => $this->scannerService->getScanStatus(),
        ]);
    }

    public function processBatch(): JsonResponse
    {
        $result = $this->scannerService->processNextBatch(4);
        return response()->json($result);
    }

    public function enrichMissing(): JsonResponse
    {
        $result = $this->scannerService->enrichMissingMetadata(25);
        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }

    public function pauseScan(): JsonResponse
    {
        $this->scannerService->pauseScan();
        return response()->json(['success' => true, 'status' => $this->scannerService->getScanStatus()]);
    }

    public function resumeScan(): JsonResponse
    {
        $this->scannerService->resumeScan();
        return response()->json(['success' => true, 'status' => $this->scannerService->getScanStatus()]);
    }

    public function cancelScan(): JsonResponse
    {
        $this->scannerService->cancelScan();
        return response()->json(['success' => true, 'status' => $this->scannerService->getScanStatus()]);
    }

    public function getStatus(): JsonResponse
    {
        return response()->json($this->scannerService->getScanStatus());
    }

    public function clearDemoCatalog(): JsonResponse
    {
        $this->wipeAllScannedMedia();

        return response()->json([
            'success' => true,
            'message' => 'Library catalog, analytics, and watch history cleared successfully.',
            'status' => $this->scannerService->getScanStatus(),
        ]);
    }

    public function deleteSingleMedia(Request $request, $id): JsonResponse
    {
        $type = $request->input('type', 'movie');

        if ($type === 'series') {
            $series = Series::find($id);
            if ($series) {
                $seasonIds = $series->seasons()->pluck('id');
                $episodes = Episode::whereIn('season_id', $seasonIds)->get();
                foreach ($episodes as $ep) {
                    Subtitle::where('subtitlable_type', Episode::class)->where('subtitlable_id', $ep->id)->delete();
                    WatchHistory::where('watchable_type', Episode::class)->where('watchable_id', $ep->id)->delete();
                    $ep->delete();
                }
                Season::whereIn('id', $seasonIds)->delete();
                DB::table('genre_series')->where('series_id', $series->id)->delete();
                $series->delete();
                return response()->json(['success' => true, 'message' => "Series '{$series->title}' removed from library."]);
            }
        } else {
            $movie = MediaItem::find($id);
            if ($movie) {
                Subtitle::where('subtitlable_type', MediaItem::class)->where('subtitlable_id', $movie->id)->delete();
                WatchHistory::where('watchable_type', MediaItem::class)->where('watchable_id', $movie->id)->delete();
                WatchHistory::where('media_item_id', $movie->id)->delete();
                DB::table('genre_media_item')->where('media_item_id', $movie->id)->delete();
                $movie->delete();
                return response()->json(['success' => true, 'message' => "Movie '{$movie->title}' removed from library."]);
            }
        }

        return response()->json(['success' => false, 'message' => 'Media item not found.'], 404);
    }

    protected function wipeAllScannedMedia(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF;');
        WatchHistory::truncate();
        Subtitle::truncate();
        Episode::truncate();
        Season::truncate();
        Series::truncate();
        MediaItem::truncate();
        try {
            DB::table('genre_media_item')->truncate();
            DB::table('genre_series')->truncate();
        } catch (\Throwable $e) {}
        DB::statement('PRAGMA foreign_keys = ON;');

        Cache::put('virtual_scan_job', [
            'status' => 'idle',
            'total_files' => 0,
            'processed_files' => 0,
            'progress_percent' => 0,
            'current_file' => '',
            'queue' => [],
            'scanned_items' => [],
            'logs' => [
                [
                    'time' => now()->format('H:i:s'),
                    'level' => 'info',
                    'message' => 'Library catalog, media items, and analytics wiped.',
                ]
            ],
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ], 86400);
    }

    protected function wipeFolderMedia(string $folderPath): void
    {
        $cleanPath = rtrim(str_replace('\\', '/', $folderPath), '/');

        // Delete movies originating from this folder
        $movies = MediaItem::where('file_path', 'like', "{$cleanPath}%")->orWhere('folder_path', 'like', "{$cleanPath}%")->get();
        foreach ($movies as $m) {
            Subtitle::where('subtitlable_type', MediaItem::class)->where('subtitlable_id', $m->id)->delete();
            WatchHistory::where('watchable_type', MediaItem::class)->where('watchable_id', $m->id)->delete();
            $m->delete();
        }

        // Delete episodes originating from this folder
        $episodes = Episode::where('file_path', 'like', "{$cleanPath}%")->get();
        foreach ($episodes as $ep) {
            Subtitle::where('subtitlable_type', Episode::class)->where('subtitlable_id', $ep->id)->delete();
            WatchHistory::where('watchable_type', Episode::class)->where('watchable_id', $ep->id)->delete();
            $ep->delete();
        }

        // Delete orphan seasons & series
        Season::doesntHave('episodes')->delete();
        Series::doesntHave('seasons')->delete();
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes > 0) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return '0 GB';
    }
}
