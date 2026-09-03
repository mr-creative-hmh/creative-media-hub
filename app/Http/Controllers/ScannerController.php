<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Series;
use App\Models\Subtitle;
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

        $scanStatus = $this->scannerService->getScanStatus();
        $stats = $this->getLibraryStats();
        $scanStatus['stats'] = $stats;

        return Inertia::render('Scanner/Index', [
            'directories' => $directories ?: [],
            'scanStatus' => $scanStatus,
            'stats' => $stats,
        ]);
    }

    public function getLibraryStats(): array
    {
        $totalMovies = MediaItem::count();
        $totalSeries = Series::count();
        $totalEpisodes = Episode::count();
        $totalSubs = Subtitle::count();
        $totalCollections = MediaItem::whereNotNull('collection_name')
            ->where('collection_name', '!=', '')
            ->distinct('collection_name')
            ->count('collection_name');

        $storageBytes = (int) MediaItem::sum('file_size_bytes') + (int) Episode::sum('file_size_bytes');

        return [
            'total_movies' => $totalMovies,
            'total_series' => $totalSeries,
            'total_episodes' => $totalEpisodes,
            'total_subtitles' => $totalSubs,
            'total_collections' => $totalCollections,
            'storage_size_formatted' => $this->formatBytes($storageBytes),
        ];
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
        if (! $setting) {
            return response()->json(['success' => true, 'directories' => []]);
        }

        $dirs = json_decode($setting->value, true) ?: [];

        if (is_numeric($index) && isset($dirs[$index])) {
            array_splice($dirs, (int) $index, 1);
        } else {
            $dirs = array_values(array_filter($dirs, fn ($d) => ($d['id'] ?? '') !== $index && ($d['path'] ?? '') !== $index));
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
        $scanMode = $request->input('scan_mode', 'incremental');

        if (empty($directories)) {
            $setting = AppSetting::where('key', 'scanner_monitored_directories')->first();
            $directories = $setting ? json_decode($setting->value, true) : [];
        }

        $initResult = $this->scannerService->initScan($directories ?: [], $scanMode);

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

        // 3. Initialize fresh scan (always uses 'fresh' mode)
        $initResult = $this->scannerService->initScan($directories ?: [], 'fresh');

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
            'scan_mode' => 'nullable|string|in:incremental,fresh',
        ]);

        $folderPath = rtrim(str_replace('\\', '/', trim($validated['path'])), '/');
        $scanMode = $validated['scan_mode'] ?? 'incremental';

        // If fresh is requested for this specific folder, wipe existing items from this path
        if (! empty($validated['fresh']) || $scanMode === 'fresh') {
            $this->wipeFolderMedia($folderPath);
        }

        $initResult = $this->scannerService->initScanForFolder($folderPath, $validated['type'] ?? 'mixed', $scanMode);

        return response()->json([
            'success' => true,
            'init' => $initResult,
            'status' => $this->scannerService->getScanStatus(),
        ]);
    }

    public function processBatch(Request $request): JsonResponse
    {
        $batchSize = (int) $request->input('batch_size', 6);
        $result = $this->scannerService->processNextBatch($batchSize);
        $fullStatus = $this->scannerService->getScanStatus();
        $stats = $this->getLibraryStats();
        $fullStatus['stats'] = $stats;

        return response()->json([
            'success' => true,
            'status' => $fullStatus,
            'stats' => $stats,
            'has_more' => $result['has_more'] ?? false,
            'result' => $result,
        ]);
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

        return response()->json([
            'success' => true,
            'status' => $this->scannerService->getScanStatus(),
        ]);
    }

    public function resumeScan(): JsonResponse
    {
        $this->scannerService->resumeScan();

        return response()->json([
            'success' => true,
            'status' => $this->scannerService->getScanStatus(),
        ]);
    }

    public function cancelScan(): JsonResponse
    {
        $this->scannerService->cancelScan();

        return response()->json([
            'success' => true,
            'status' => $this->scannerService->getScanStatus(),
        ]);
    }

    public function getStatus(): JsonResponse
    {
        $status = $this->scannerService->getScanStatus();
        $status['stats'] = $this->getLibraryStats();

        return response()->json($status);
    }

    public function clearDemoCatalog(): JsonResponse
    {
        $this->wipeAllScannedMedia();

        return response()->json([
            'success' => true,
            'message' => 'Library catalog cleared successfully.',
            'status' => $this->scannerService->getScanStatus(),
        ]);
    }

    public function deleteSingleMedia($id): JsonResponse
    {
        $item = MediaItem::find($id);
        if ($item) {
            $item->subtitles()->delete();
            $item->genres()->detach();
            $item->delete();

            return response()->json(['success' => true]);
        }

        $series = Series::find($id);
        if ($series) {
            $series->seasons()->each(function ($season) {
                $season->episodes()->each(function ($ep) {
                    $ep->subtitles()->delete();
                    $ep->delete();
                });
                $season->delete();
            });
            $series->genres()->detach();
            $series->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
    }

    protected function wipeAllScannedMedia(): void
    {
        retry(5, function () {
            DB::statement('PRAGMA foreign_keys = OFF;');
            DB::statement('DELETE FROM subtitles;');
            DB::statement('DELETE FROM watch_histories;');
            DB::statement('DELETE FROM personables;');
            DB::statement('DELETE FROM genreables;');
            DB::statement('DELETE FROM episodes;');
            DB::statement('DELETE FROM seasons;');
            DB::statement('DELETE FROM series;');
            DB::statement('DELETE FROM media_items;');
            DB::statement('PRAGMA foreign_keys = ON;');
        }, 150);

        Cache::forget('virtual_scanner_job_status');
    }

    protected function wipeFolderMedia(string $folderPath): void
    {
        $clean = rtrim($folderPath, '/');
        MediaItem::where('file_path', 'like', "{$clean}%")->delete();
        Episode::where('file_path', 'like', "{$clean}%")->delete();
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824 * 1024) {
            return round($bytes / (1073741824 * 1024), 2).' TB';
        }
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}
