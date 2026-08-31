<?php

namespace App\Http\Controllers;

use App\Models\DownloadItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DownloadManagerController extends Controller
{
    public function index()
    {
        $downloads = DownloadItem::orderByDesc('created_at')->get();

        return Inertia::render('Downloader/Index', [
            'downloads' => $downloads,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'media_type' => 'required|in:movie,series,subtitle',
            'source_url' => 'nullable|string',
            'destination_path' => 'nullable|string',
        ]);

        $item = DownloadItem::create([
            'title' => $validated['title'],
            'media_type' => $validated['media_type'],
            'source_url' => $validated['source_url'] ?? 'https://example.com/stream/download',
            'destination_path' => $validated['destination_path'] ?? 'C:/Downloads/Incoming/' . $validated['title'] . '.mkv',
            'total_bytes' => rand(1500000000, 8000000000),
            'downloaded_bytes' => 0,
            'status' => 'downloading',
            'speed_bytes_sec' => rand(5000000, 15000000),
        ]);

        return response()->json($item);
    }
}
