<?php

namespace App\Http\Controllers;

use App\Models\DownloadItem;
use App\Services\Downloader\DownloadManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DownloadManagerController extends Controller
{
    protected DownloadManagerService $downloadService;

    public function __construct(DownloadManagerService $downloadService)
    {
        $this->downloadService = $downloadService;
    }

    public function index(): Response
    {
        $downloads = DownloadItem::orderByDesc('created_at')->get();

        return Inertia::render('Downloader/Index', [
            'initialDownloads' => $downloads,
            'destinations' => $this->downloadService->getDefaultDestinations(),
            'settings' => $this->downloadService->getSettings(),
        ]);
    }

    public function list(): JsonResponse
    {
        $downloads = DownloadItem::orderByDesc('created_at')->get();

        return response()->json($downloads);
    }

    public function inspect(Request $request): JsonResponse
    {
        // Support direct .torrent file upload
        if ($request->hasFile('torrent_file')) {
            $file = $request->file('torrent_file');
            $rawContent = file_get_contents($file->getRealPath());
            $inspection = $this->downloadService->inspectTorrentFileContent($rawContent, $file->getClientOriginalName());

            return response()->json($inspection);
        }

        $validated = $request->validate([
            'url' => 'required|string',
            'type' => 'nullable|string|in:direct,torrent',
        ]);

        $inspection = $this->downloadService->inspectUrl($validated['url'], $validated['type'] ?? null);

        return response()->json($inspection);
    }

    public function getSettings(): JsonResponse
    {
        return response()->json([
            'settings' => $this->downloadService->getSettings(),
            'destinations' => $this->downloadService->getDefaultDestinations(),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'default_download_path' => 'nullable|string',
            'movies_download_path' => 'nullable|string',
            'series_download_path' => 'nullable|string',
            'max_concurrent_downloads' => 'nullable|integer|min:1|max:10',
            'download_speed_limit_kb' => 'nullable|integer|min:0',
        ]);

        $saved = $this->downloadService->saveSettings($validated);

        return response()->json([
            'success' => true,
            'settings' => $saved,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'media_type' => 'required|in:movie,series,subtitle',
            'source_url' => 'nullable|string',
            'destination_path' => 'nullable|string',
            'destination_folder' => 'nullable|string',
            'download_type' => 'nullable|string|in:direct,torrent',
            'selected_files' => 'nullable|array',
            'torrent_files' => 'nullable|array',
            'info_hash' => 'nullable|string',
        ]);

        $destFolder = $validated['destination_folder'] ?? null;

        $item = $this->downloadService->createDownload(
            $validated['title'],
            $validated['media_type'],
            $validated['source_url'] ?? null,
            $validated['destination_path'] ?? null,
            $validated['download_type'] ?? 'direct',
            $destFolder,
            $validated['torrent_files'] ?? null,
            $validated['selected_files'] ?? null,
            $validated['info_hash'] ?? null
        );

        return response()->json($item);
    }

    public function processBatch(): JsonResponse
    {
        $result = $this->downloadService->processBatch();

        return response()->json($result);
    }

    public function pause(int $id): JsonResponse
    {
        $success = $this->downloadService->pause($id);

        return response()->json(['success' => $success]);
    }

    public function resume(int $id): JsonResponse
    {
        $success = $this->downloadService->resume($id);

        return response()->json(['success' => $success]);
    }

    public function retry(int $id): JsonResponse
    {
        $success = $this->downloadService->retry($id);

        return response()->json(['success' => $success]);
    }

    public function destroy(int $id): JsonResponse
    {
        $success = $this->downloadService->delete($id, false);

        return response()->json(['success' => $success]);
    }
}
