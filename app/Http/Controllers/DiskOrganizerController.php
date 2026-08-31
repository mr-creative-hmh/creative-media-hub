<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\Organizer\FilesystemScannerService;
use App\Services\Organizer\PhysicalOrganizerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DiskOrganizerController extends Controller
{
    protected FilesystemScannerService $scanner;
    protected PhysicalOrganizerService $organizer;

    public function __construct(FilesystemScannerService $scanner, PhysicalOrganizerService $organizer)
    {
        $this->scanner = $scanner;
        $this->organizer = $organizer;
    }

    public function index()
    {
        return Inertia::render('Organizer/Index', [
            'defaultMovieTemplate' => AppSetting::get('movie_naming_template', '{Type}/{Title} ({Year})/{Title} ({Year}) [{Resolution}].{ext}'),
            'defaultSeriesTemplate' => AppSetting::get('series_naming_template', '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{Resolution}].{ext}'),
        ]);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'source_path' => 'required|string',
            'recursive' => 'boolean',
        ]);

        $files = $this->scanner->scanDirectory($validated['source_path'], $validated['recursive'] ?? true);

        return response()->json([
            'count' => count($files),
            'files' => $files,
        ]);
    }

    public function dryRun(Request $request)
    {
        $validated = $request->validate([
            'files' => 'required|array',
            'target_root' => 'required|string',
            'movie_template' => 'nullable|string',
            'series_template' => 'nullable|string',
        ]);

        $plan = $this->organizer->generateDryRun(
            $validated['files'],
            $validated['target_root'],
            $validated['movie_template'] ?? null,
            $validated['series_template'] ?? null
        );

        return response()->json([
            'plan' => $plan,
            'total_items' => count($plan),
            'ready_count' => count(array_filter($plan, fn($i) => $i['status'] === 'ready')),
        ]);
    }

    public function execute(Request $request)
    {
        $validated = $request->validate([
            'plan' => 'required|array',
            'mode' => 'required|in:move,copy',
        ]);

        $result = $this->organizer->execute($validated['plan'], $validated['mode']);

        return response()->json($result);
    }
}
