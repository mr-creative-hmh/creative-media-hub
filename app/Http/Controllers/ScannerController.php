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
        $directories = $directoriesSetting ? json_decode($directoriesSetting->value, true) : [
            ['id' => 'dir-1', 'path' => 'C:/Media/Movies', 'type' => 'movies', 'auto_scan' => true],
            ['id' => 'dir-2', 'path' => 'C:/Media/TV Shows', 'type' => 'series', 'auto_scan' => true],
        ];

        $stats = [
            'total_movies' => MediaItem::count(),
            'total_series' => Series::count(),
            'total_episodes' => Episode::count(),
        ];

        return Inertia::render('Scanner/Index', [
            'directories' => $directories,
            'initialScanStatus' => $this->scannerService->getScanStatus(),
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

    public function removeDirectory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|string',
        ]);

        $setting = AppSetting::where('key', 'monitored_directories')->first();
        if ($setting) {
            $directories = json_decode($setting->value, true) ?: [];
            $filtered = array_values(array_filter($directories, fn ($d) => ($d['id'] ?? '') !== $validated['id']));
            $setting->update(['value' => json_encode($filtered)]);
            return response()->json(['success' => true, 'directories' => $filtered]);
        }

        return response()->json(['success' => false, 'message' => 'Settings not found']);
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
        WatchHistory::truncate();
        Subtitle::truncate();
        Episode::truncate();
        Season::truncate();
        Series::truncate();
        MediaItem::truncate();

        return response()->json([
            'success' => true,
            'message' => 'Catalog cleared.',
        ]);
    }
}
