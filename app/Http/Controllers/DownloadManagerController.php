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
        ]);
    }

    public function list(): JsonResponse
    {
        $downloads = DownloadItem::orderByDesc('created_at')->get();
        return response()->json($downloads);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'media_type' => 'required|in:movie,series,subtitle',
            'source_url' => 'nullable|string',
            'destination_path' => 'nullable|string',
        ]);

        $item = $this->downloadService->createDownload(
            $validated['title'],
            $validated['media_type'],
            $validated['source_url'] ?? null,
            $validated['destination_path'] ?? null
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
