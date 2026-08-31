<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Services\Organizer\FilesystemScannerService;
use App\Services\Organizer\PhysicalOrganizerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiskOrganizerController extends Controller
{
    protected FilesystemScannerService $scanner;
    protected PhysicalOrganizerService $organizer;

    public function __construct(FilesystemScannerService $scanner, PhysicalOrganizerService $organizer)
    {
        $this->scanner = $scanner;
        $this->organizer = $organizer;
    }

    public function index(): Response
    {
        return Inertia::render('Organizer/Index', [
            'defaultMovieTemplate' => AppSetting::get('movie_naming_template', '{Type}/{Title} ({Year})/{Title} ({Year}) [{Resolution}].{ext}'),
            'defaultSeriesTemplate' => AppSetting::get('series_naming_template', '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} [{Resolution}].{ext}'),
        ]);
    }

    public function loadFromVirtualLibrary(): JsonResponse
    {
        $movies = MediaItem::with('subtitles')->get()->map(function ($m) {
            return [
                'path' => $m->file_path,
                'filename' => basename($m->file_path),
                'size_bytes' => $m->file_size_bytes,
                'size_formatted' => $m->file_size_bytes ? round($m->file_size_bytes / (1024 * 1024 * 1024), 2) . ' GB' : '1.4 GB',
                'parsed' => [
                    'type' => 'movie',
                    'title' => $m->title,
                    'clean_title' => $m->title,
                    'year' => $m->release_year,
                    'resolution' => $m->resolution ?? '1080p',
                    'codec' => $m->video_codec ?? 'HEVC',
                ],
                'subtitles' => $m->subtitles->map(fn ($s) => ['path' => $s->file_path, 'language' => $s->language])->toArray(),
            ];
        });

        $episodes = Episode::with(['season.series', 'subtitles'])->get()->map(function ($ep) {
            return [
                'path' => $ep->file_path,
                'filename' => basename($ep->file_path),
                'size_bytes' => $ep->file_size_bytes,
                'size_formatted' => $ep->file_size_bytes ? round($ep->file_size_bytes / (1024 * 1024), 1) . ' MB' : '450 MB',
                'parsed' => [
                    'type' => 'series',
                    'series_title' => $ep->season?->series?->title ?? 'TV Show',
                    'season' => $ep->season?->season_number ?? 1,
                    'episode' => $ep->episode_number,
                    'resolution' => $ep->resolution ?? '1080p',
                    'codec' => $ep->video_codec ?? 'HEVC',
                ],
                'subtitles' => $ep->subtitles->map(fn ($s) => ['path' => $s->file_path, 'language' => $s->language])->toArray(),
            ];
        });

        $all = $movies->concat($episodes)->values()->filter(fn ($f) => !empty($f['path']) && file_exists($f['path']))->values();

        return response()->json([
            'count' => $all->count(),
            'files' => $all,
        ]);
    }

    public function scan(Request $request): JsonResponse
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

    public function dryRun(Request $request): JsonResponse
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
            'ready_count' => count(array_filter($plan, fn ($i) => $i['status'] === 'ready')),
        ]);
    }

    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => 'required|array',
            'mode' => 'required|in:move,copy',
        ]);

        $result = $this->organizer->execute($validated['plan'], $validated['mode']);

        return response()->json($result);
    }
}
