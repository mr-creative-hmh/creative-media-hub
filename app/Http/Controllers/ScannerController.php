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
        $directoriesSetting = AppSetting::where('key', 'monitored_directories')->first();
        $directories = $directoriesSetting ? (json_decode($directoriesSetting->value, true) ?: []) : [];

        $totalSizeBytes = MediaItem::sum('file_size_bytes') + Episode::sum('file_size_bytes');
        $formattedSize = $totalSizeBytes >= 1073741824
            ? round($totalSizeBytes / 1073741824, 2) . ' GB'
            : ($totalSizeBytes >= 1048576 ? round($totalSizeBytes / 1048576, 1) . ' MB' : '0 B');

        $stats = [
            'total_movies' => MediaItem::count(),
            'total_series' => Series::count(),
            'total_episodes' => Episode::count(),
            'total_subtitles' => Subtitle::count(),
            'storage_size_formatted' => $formattedSize,
        ];

        return Inertia::render('Scanner/Index', [
            'directories' => $directories,
            'scanStatus' => $this->scannerService->getScanStatus(),
            'stats' => $stats,
        ]);
    }

    public function addDirectory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string',
            'type' => 'required|in:movies,series,mixed',
        ]);

        $setting = AppSetting::firstOrCreate(['key' => 'monitored_directories'], ['value' => '[]', 'type' => 'json']);
        $directories = json_decode($setting->value, true) ?: [];

        $newDir = [
            'id' => 'dir-' . uniqid(),
            'path' => $validated['path'],
            'type' => $validated['type'],
            'auto_scan' => true,
        ];

        $directories[] = $newDir;
        $setting->update(['value' => json_encode($directories)]);

        return response()->json(['success' => true, 'directories' => $directories]);
    }

    public function removeDirectory(Request $request, $index = null): JsonResponse
    {
        $setting = AppSetting::where('key', 'monitored_directories')->first();
        if ($setting) {
            $directories = json_decode($setting->value, true) ?: [];

            $id = $request->input('id');
            if ($id) {
                $directories = array_values(array_filter($directories, fn ($d) => ($d['id'] ?? '') !== $id));
            } elseif ($index !== null && isset($directories[$index])) {
                array_splice($directories, (int) $index, 1);
            }

            $setting->update(['value' => json_encode(array_values($directories))]);
            return response()->json(['success' => true, 'directories' => array_values($directories)]);
        }

        return response()->json(['success' => true, 'directories' => []]);
    }

    public function startScan(Request $request): JsonResponse
    {
        $directories = $request->input('directories');
        if (empty($directories)) {
            $setting = AppSetting::where('key', 'monitored_directories')->first();
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
        // 1. Wipe previous scanned items
        $this->wipeAllScannedMedia();

        // 2. Load directories
        $setting = AppSetting::where('key', 'monitored_directories')->first();
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
        ]);

        $initResult = $this->scannerService->initScanForFolder($validated['path'], $validated['type'] ?? 'mixed');

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
            'message' => 'Library catalog and scanned media cleared successfully.',
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
                    $ep->delete();
                }
                Season::whereIn('id', $seasonIds)->delete();
                $series->delete();
                return response()->json(['success' => true, 'message' => "Series '{$series->title}' removed from library."]);
            }
        } else {
            $movie = MediaItem::find($id);
            if ($movie) {
                Subtitle::where('subtitlable_type', MediaItem::class)->where('subtitlable_id', $movie->id)->delete();
                WatchHistory::where('media_item_id', $movie->id)->delete();
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
                    'message' => 'Library catalog reset and cleared by user.',
                ],
            ],
        ], 86400);
    }
}
