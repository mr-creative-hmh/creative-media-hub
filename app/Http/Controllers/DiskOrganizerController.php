<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Services\Organizer\FilesystemScannerService;
use App\Services\Organizer\PhysicalOrganizerService;
use App\Services\Organizer\OrganizerWatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiskOrganizerController extends Controller
{
    protected FilesystemScannerService $scanner;

    protected PhysicalOrganizerService $organizer;

    protected OrganizerWatcherService $watcher;

    public function __construct(
        FilesystemScannerService $scanner,
        PhysicalOrganizerService $organizer,
        OrganizerWatcherService $watcher
    ) {
        $this->scanner = $scanner;
        $this->organizer = $organizer;
        $this->watcher = $watcher;
    }

    public function index(): Response
    {
        $defaultDir = str_replace('\\', '/', base_path('storage/app/media'));
        if (! file_exists($defaultDir)) {
            @mkdir($defaultDir, 0755, true);
        }

        return Inertia::render('Organizer/Index', [
            'defaultMovieTemplate' => AppSetting::get('movie_naming_template', '{Type}/{Genre}/{Title} ({Year})/{Title} ({Year}).{ext}'),
            'defaultSeriesTemplate' => AppSetting::get('series_naming_template', '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02}.{ext}'),
            'defaultWorkingDir' => $defaultDir,
            'watcherStatus' => $this->watcher->getStatus(),
        ]);
    }

    public function loadFromVirtualLibrary(): JsonResponse
    {
        $movies = MediaItem::with('subtitles')->get()->map(function ($m) {
            return [
                'path' => $m->file_path,
                'filename' => basename($m->file_path),
                'size_bytes' => $m->file_size_bytes,
                'size_formatted' => $m->file_size_bytes ? round($m->file_size_bytes / (1024 * 1024 * 1024), 2).' GB' : '1.4 GB',
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
                'size_formatted' => $ep->file_size_bytes ? round($ep->file_size_bytes / (1024 * 1024), 1).' MB' : '450 MB',
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

        $all = $movies->concat($episodes)->values()->filter(fn ($f) => ! empty($f['path']) && file_exists($f['path']))->values();

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
            'ready_count' => count(array_filter($plan, fn ($i) => ($i['status'] ?? '') === 'ready')),
        ]);
    }

    public function initExecution(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => 'required|array',
            'mode' => 'required|in:move,copy',
            'cleanup_empty_folders' => 'nullable|boolean',
        ]);

        $state = $this->organizer->initExecution(
            $validated['plan'],
            $validated['mode'],
            $validated['cleanup_empty_folders'] ?? true
        );

        return response()->json([
            'success' => true,
            'status' => $state,
        ]);
    }

    public function processBatch(Request $request): JsonResponse
    {
        $batchSize = (int) $request->input('batch_size', 2);
        $result = $this->organizer->processNextBatch($batchSize);

        return response()->json([
            'success' => true,
            'has_more' => $result['has_more'],
            'status' => $result['status'],
        ]);
    }

    public function getExecutionStatus(): JsonResponse
    {
        return response()->json($this->organizer->getExecutionStatus());
    }

    
    public function pauseExecution(): JsonResponse
    {
        return response()->json($this->organizer->pauseExecution());
    }

    public function resumeExecution(): JsonResponse
    {
        return response()->json($this->organizer->resumeExecution());
    }

    public function cancelExecution(): JsonResponse
    {
        return response()->json($this->organizer->cancelExecution());
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

    public function getWatcherStatus(): JsonResponse
    {
        return response()->json($this->watcher->getStatus());
    }

    public function toggleWatcher(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $this->watcher->setEnabled($validated['enabled']);

        return response()->json($this->watcher->getStatus());
    }

    public function updateWatchedFolders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:add,remove',
            'path' => 'required|string',
        ]);

        if ($validated['action'] === 'add') {
            $folders = $this->watcher->addFolder($validated['path']);
        } else {
            $folders = $this->watcher->removeFolder($validated['path']);
        }

        return response()->json([
            'success' => true,
            'folders' => $folders,
            'status' => $this->watcher->getStatus(),
        ]);
    }

    public function runWatcherNow(Request $request): JsonResponse
    {
        $dryRunOnly = (bool) $request->input('dry_run', true);
        $result = $this->watcher->checkAndOrganize($dryRunOnly);

        return response()->json($result);
    }

    public function browseDirectory(Request $request): JsonResponse
    {
        $path = $request->query('path');
        $result = $this->scanner->browseDirectory($path ? (string) $path : null);

        return response()->json($result);
    }

    public function startPlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_path' => 'nullable|string',
            'target_root' => 'required|string',
            'movie_template' => 'nullable|string',
            'series_template' => 'nullable|string',
            'source_mode' => 'nullable|string|in:virtual,folder',
            'recursive' => 'nullable|boolean',
            'files' => 'nullable|array',
        ]);

        $result = $this->organizer->startPlanJob(
            $validated['source_path'] ?? '',
            $validated['target_root'],
            $validated['movie_template'] ?? null,
            $validated['series_template'] ?? null,
            $validated['source_mode'] ?? 'folder',
            $validated['recursive'] ?? true,
            ['files' => $validated['files'] ?? null]
        );

        return response()->json($result, ($result['success'] ?? true) ? 200 : 422);
    }

    public function initPlan(Request $request): JsonResponse
    {
        return $this->startPlan($request);
    }

    public function processPlanBatch(Request $request): JsonResponse
    {
        $batchSize = (int) $request->input('batch_size', 15);
        $result = $this->organizer->processPlanJobBatch($batchSize);

        return response()->json($result);
    }

    public function getPlanStatus(): JsonResponse
    {
        return response()->json($this->organizer->getPlanJobStatus());
    }

    public function pausePlan(): JsonResponse
    {
        return response()->json($this->organizer->pausePlanJob());
    }

    public function resumePlan(): JsonResponse
    {
        return response()->json($this->organizer->resumePlanJob());
    }

    public function cancelPlan(): JsonResponse
    {
        return response()->json($this->organizer->cancelPlanJob());
    }
}
