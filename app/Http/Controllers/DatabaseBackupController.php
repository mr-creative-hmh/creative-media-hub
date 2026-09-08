<?php

namespace App\Http\Controllers;

use App\Services\Database\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DatabaseBackupController extends Controller
{
    public function __construct(
        protected DatabaseBackupService $backupService
    ) {}

    /**
     * List all available local database backups.
     */
    public function listBackups(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'backups' => $this->backupService->listLocalBackups(),
        ]);
    }

    /**
     * Create a new backup snapshot on the server.
     */
    public function createBackup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:64',
            'format' => 'nullable|in:json,sqlite,both',
        ]);

        $result = $this->backupService->createLocalBackup(
            $validated['name'] ?? null,
            $validated['format'] ?? 'both'
        );

        return response()->json($result);
    }

    /**
     * Download the live database directly as a JSON file.
     */
    public function exportJson(): Response
    {
        return $this->backupService->streamJsonDownload();
    }

    /**
     * Download a saved local backup file.
     */
    public function downloadLocal(string $filename): Response
    {
        return $this->backupService->downloadLocalBackup($filename);
    }

    /**
     * Delete a saved local backup file.
     */
    public function deleteLocal(string $filename): JsonResponse
    {
        $deleted = $this->backupService->deleteLocalBackup($filename);
        return response()->json([
            'success' => $deleted,
            'message' => $deleted ? 'Backup deleted successfully.' : 'Backup not found.',
        ]);
    }

    /**
     * Restore database from an uploaded JSON or SQLite file.
     */
    public function restoreUpload(Request $request): JsonResponse
    {
        $request->validate([
            'backup_file' => 'required|file|max:512000', // max 500MB
            'mode' => 'nullable|in:overwrite,merge',
            'sections' => 'nullable',
        ]);

        $file = $request->file('backup_file');
        $mode = $request->input('mode', 'overwrite');
        $sections = $this->parseSections($request->input('sections'));

        $tempPath = $file->getRealPath();
        $originalExt = strtolower($file->getClientOriginalExtension());

        // Ensure temp file has extension for format detection
        $storedTemp = tempnam(sys_get_temp_dir(), 'restore_') . '.' . $originalExt;
        copy($tempPath, $storedTemp);

        try {
            $result = $this->backupService->restoreFromFile($storedTemp, $mode, $sections);
            @unlink($storedTemp);
            return response()->json($result);
        } catch (\Throwable $e) {
            @unlink($storedTemp);
            return response()->json([
                'success' => false,
                'message' => 'Restoration failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Restore database from a previously saved local backup.
     */
    public function restoreLocal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filename' => 'required|string',
            'mode' => 'nullable|in:overwrite,merge',
            'sections' => 'nullable',
        ]);

        $filename = basename($validated['filename']);
        $mode = $validated['mode'] ?? 'overwrite';
        $sections = $this->parseSections($request->input('sections'));
        $path = $this->backupService->getBackupPath() . '/' . $filename;

        if (! file_exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'Local backup file not found.',
            ], 404);
        }

        try {
            $result = $this->backupService->restoreFromFile($path, $mode, $sections);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Restoration failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Safely parse sections list from request.
     */
    protected function parseSections(mixed $sections): array
    {
        $default = ['movies', 'series', 'subtitles', 'settings', 'watch_history'];
        if (empty($sections)) {
            return $default;
        }

        if (is_string($sections)) {
            $decoded = json_decode($sections, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, 'is_string'));
            }
            return array_values(array_filter(array_map('trim', explode(',', $sections))));
        }

        if (is_array($sections)) {
            return array_values(array_filter($sections, 'is_string'));
        }

        return $default;
    }
}
