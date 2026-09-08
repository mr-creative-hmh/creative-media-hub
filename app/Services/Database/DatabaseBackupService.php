<?php

namespace App\Services\Database;

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupService
{
    /**
     * Tables to include in library backup in dependency order.
     */
    protected array $libraryTables = [
        'app_settings',
        'genres',
        'people',
        'series',
        'seasons',
        'media_items',
        'episodes',
        'genreables',
        'personables',
        'subtitles',
        'watch_histories',
        'download_items',
    ];

    public function getBackupPath(): string
    {
        $path = storage_path('app/backups');
        File::ensureDirectoryExists($path);
        return str_replace('\\', '/', $path);
    }

    /**
     * Export the entire library database into structured array.
     */
    public function exportData(): array
    {
        $data = [
            'app' => 'Creative Media Hub',
            'version' => '1.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'database_driver' => config('database.default'),
            'counts' => [],
            'tables' => [],
        ];

        foreach ($this->libraryTables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $rows = DB::table($table)->get()->map(function ($row) {
                    return (array) $row;
                })->toArray();

                $data['tables'][$table] = $rows;
                $data['counts'][$table] = count($rows);
            }
        }

        return $data;
    }

    /**
     * Stream download the current database as a JSON backup file.
     */
    public function streamJsonDownload(): StreamedResponse
    {
        $filename = 'creative_media_hub_backup_' . date('Y-m-d_His') . '.json';
        $data = $this->exportData();

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Create a named or timestamped backup file on local disk.
     */
    public function createLocalBackup(?string $name = null, string $format = 'both'): array
    {
        $backupDir = $this->getBackupPath();
        $timestamp = date('Y-m-d_His');
        $safeName = $name ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $name) : "snapshot_{$timestamp}";

        $createdFiles = [];
        $data = $this->exportData();

        // 1. Create JSON backup
        if ($format === 'json' || $format === 'both') {
            $jsonFilename = "{$safeName}.json";
            $jsonFilePath = "{$backupDir}/{$jsonFilename}";
            File::put($jsonFilePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $createdFiles['json'] = [
                'filename' => $jsonFilename,
                'path' => $jsonFilePath,
                'size_bytes' => filesize($jsonFilePath),
                'size_formatted' => $this->formatBytes(filesize($jsonFilePath)),
            ];
        }

        // 2. Create SQLite binary snapshot if sqlite is in use
        if (($format === 'sqlite' || $format === 'both') && config('database.default') === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');
            if (File::exists($dbPath)) {
                $sqliteFilename = "{$safeName}.sqlite";
                $sqliteFilePath = "{$backupDir}/{$sqliteFilename}";
                File::copy($dbPath, $sqliteFilePath);
                $createdFiles['sqlite'] = [
                    'filename' => $sqliteFilename,
                    'path' => $sqliteFilePath,
                    'size_bytes' => filesize($sqliteFilePath),
                    'size_formatted' => $this->formatBytes(filesize($sqliteFilePath)),
                ];
            }
        }

        return [
            'success' => true,
            'name' => $safeName,
            'created_at' => date('Y-m-d H:i:s'),
            'counts' => $data['counts'],
            'files' => $createdFiles,
        ];
    }

    /**
     * List all existing backups stored on server.
     */
    public function listLocalBackups(): array
    {
        $backupDir = $this->getBackupPath();
        $files = File::files($backupDir);
        $backups = [];

        foreach ($files as $file) {
            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['json', 'sqlite', 'db'])) {
                continue;
            }

            $filename = $file->getFilename();
            $size = $file->getSize();
            $mtime = $file->getMTime();

            $item = [
                'filename' => $filename,
                'extension' => $ext,
                'size_bytes' => $size,
                'size_formatted' => $this->formatBytes($size),
                'created_at' => date('Y-m-d H:i:s', $mtime),
                'created_timestamp' => $mtime,
                'summary' => null,
            ];

            // If it is a JSON file, read counts summary
            if ($ext === 'json' && $size < 50 * 1024 * 1024) {
                try {
                    $content = File::get($file->getRealPath());
                    $decoded = json_decode($content, true);
                    if (is_array($decoded) && isset($decoded['counts'])) {
                        $item['summary'] = $decoded['counts'];
                        if (isset($decoded['exported_at'])) {
                            $item['created_at'] = $decoded['exported_at'];
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore parsing error for corrupted file
                }
            } elseif (in_array($ext, ['sqlite', 'db'])) {
                try {
                    $pdo = new \PDO("sqlite:" . $file->getRealPath());
                    $mediaCount = (int) $pdo->query("SELECT COUNT(*) FROM media_items")->fetchColumn();
                    $seriesCount = (int) $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn();
                    $subCount = (int) $pdo->query("SELECT COUNT(*) FROM subtitles")->fetchColumn();
                    $item['summary'] = [
                        'media_items' => $mediaCount,
                        'series' => $seriesCount,
                        'subtitles' => $subCount,
                    ];
                } catch (\Throwable $e) {
                    // Ignore
                }
            }

            $backups[] = $item;
        }

        // Sort latest first
        usort($backups, fn ($a, $b) => $b['created_timestamp'] <=> $a['created_timestamp']);

        return $backups;
    }

    /**
     * Delete a local backup file.
     */
    public function deleteLocalBackup(string $filename): bool
    {
        $clean = basename($filename);
        $path = $this->getBackupPath() . '/' . $clean;

        if (File::exists($path)) {
            return File::delete($path);
        }

        return false;
    }

    /**
     * Download a specific local backup file.
     */
    public function downloadLocalBackup(string $filename): BinaryFileResponse
    {
        $clean = basename($filename);
        $path = $this->getBackupPath() . '/' . $clean;

        if (! File::exists($path)) {
            abort(404, "Backup file {$clean} not found.");
        }

        return response()->download($path, $clean);
    }

    /**
     * Restore database from an uploaded file or local file with selective section support.
     * 
     * Supported sections: 'movies', 'series', 'subtitles', 'settings', 'watch_history'
     */
    public function restoreFromFile(string $filePath, string $mode = 'overwrite', array $includeSections = ['movies', 'series', 'subtitles', 'settings', 'watch_history']): array
    {
        if (! File::exists($filePath)) {
            throw new \InvalidArgumentException("Backup file not found at: {$filePath}");
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Create an automatic safety snapshot before restoring
        try {
            $this->createLocalBackup('auto_pre_restore_safety_backup');
        } catch (\Throwable $e) {
            Log::warning("Could not create pre-restore safety backup: " . $e->getMessage());
        }

        if ($ext === 'json') {
            $content = File::get($filePath);
            $data = json_decode($content, true);
            if (! is_array($data) || ! isset($data['tables'])) {
                throw new \InvalidArgumentException("Invalid JSON backup structure: missing 'tables' payload.");
            }
            return $this->restoreFromJson($data, $mode, $includeSections);
        } elseif (in_array($ext, ['sqlite', 'db'])) {
            // If full restore of all sections is requested, do fast binary swap
            $allSections = ['movies', 'series', 'subtitles', 'settings', 'watch_history'];
            $isAll = empty(array_diff($allSections, $includeSections)) && $mode === 'overwrite';

            if ($isAll) {
                return $this->restoreFromSqliteBinary($filePath);
            }

            // Otherwise extract tables from sqlite file and selectively restore
            return $this->restoreFromSqliteSelective($filePath, $mode, $includeSections);
        }

        throw new \InvalidArgumentException("Unsupported backup file format '.{$ext}'. Supported formats: .json, .sqlite, .db");
    }

    /**
     * Restore database records with fine-grained selective section filtering.
     */
    public function restoreFromJson(array $data, string $mode = 'overwrite', array $includeSections = ['movies', 'series', 'subtitles', 'settings', 'watch_history']): array
    {
        $tables = $data['tables'] ?? [];
        $restoredCounts = [];

        // Normalize sections
        $includeSections = array_map('strtolower', $includeSections);
        $restoreMovies = in_array('movies', $includeSections);
        $restoreSeries = in_array('series', $includeSections);
        $restoreSubtitles = in_array('subtitles', $includeSections);
        $restoreSettings = in_array('settings', $includeSections);
        $restoreHistory = in_array('watch_history', $includeSections);

        // Disable foreign keys for safe restoration
        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        }

        try {
            DB::transaction(function () use (
                $tables, $mode, $restoreMovies, $restoreSeries, $restoreSubtitles, $restoreSettings, $restoreHistory, &$restoredCounts
            ) {
                // 1. SELECTIVE DELETIONS (if overwrite mode)
                if ($mode === 'overwrite') {
                    if ($restoreMovies) {
                        DB::table('media_items')->delete();
                        DB::table('genreables')->where('genreable_type', 'like', '%MediaItem%')->delete();
                        if (DB::getSchemaBuilder()->hasTable('personables')) {
                            DB::table('personables')->where('personable_type', 'like', '%MediaItem%')->delete();
                        }
                    }

                    if ($restoreSeries) {
                        DB::table('episodes')->delete();
                        DB::table('seasons')->delete();
                        DB::table('series')->delete();
                        DB::table('genreables')->where('genreable_type', 'like', '%Series%')->delete();
                        if (DB::getSchemaBuilder()->hasTable('personables')) {
                            DB::table('personables')->where('personable_type', 'like', '%Series%')->delete();
                        }
                    }

                    if ($restoreSubtitles) {
                        DB::table('subtitles')->delete();
                    }

                    if ($restoreSettings) {
                        DB::table('app_settings')->delete();
                    }

                    if ($restoreHistory) {
                        DB::table('watch_histories')->delete();
                    }
                }

                // 2. RESTORE GENRES (shared between movies and series)
                if (($restoreMovies || $restoreSeries) && isset($tables['genres'])) {
                    foreach ($tables['genres'] as $genre) {
                        DB::table('genres')->updateOrInsert(['id' => $genre['id']], $genre);
                    }
                    $restoredCounts['genres'] = count($tables['genres']);
                }

                // 2.1 RESTORE PEOPLE (shared between movies and series)
                if (($restoreMovies || $restoreSeries) && isset($tables['people']) && DB::getSchemaBuilder()->hasTable('people')) {
                    foreach ($tables['people'] as $person) {
                        DB::table('people')->updateOrInsert(['id' => $person['id']], $person);
                    }
                    $restoredCounts['people'] = count($tables['people']);
                }

                // 3. RESTORE APP SETTINGS
                if ($restoreSettings && isset($tables['app_settings'])) {
                    $count = 0;
                    foreach ($tables['app_settings'] as $setting) {
                        DB::table('app_settings')->updateOrInsert(['key' => $setting['key']], $setting);
                        $count++;
                    }
                    $restoredCounts['app_settings'] = $count;
                }

                // 4. RESTORE MOVIES
                if ($restoreMovies && isset($tables['media_items'])) {
                    $count = 0;
                    $chunks = array_chunk($tables['media_items'], 100);
                    foreach ($chunks as $chunk) {
                        if ($mode === 'overwrite') {
                            DB::table('media_items')->insert($chunk);
                            $count += count($chunk);
                        } else {
                            foreach ($chunk as $item) {
                                DB::table('media_items')->updateOrInsert(['id' => $item['id']], $item);
                                $count++;
                            }
                        }
                    }
                    $restoredCounts['media_items'] = $count;

                    // Restore movie genreables
                    if (isset($tables['genreables'])) {
                        $movieGenreables = array_filter($tables['genreables'], fn ($g) => str_contains($g['genreable_type'] ?? '', 'MediaItem'));
                        foreach ($movieGenreables as $mg) {
                            DB::table('genreables')->updateOrInsert([
                                'genre_id' => $mg['genre_id'],
                                'genreable_type' => $mg['genreable_type'],
                                'genreable_id' => $mg['genreable_id'],
                            ], $mg);
                        }
                    }

                    // Restore movie personables
                    if (isset($tables['personables']) && DB::getSchemaBuilder()->hasTable('personables')) {
                        $moviePersonables = array_filter($tables['personables'], fn ($p) => str_contains($p['personable_type'] ?? '', 'MediaItem'));
                        foreach ($moviePersonables as $mp) {
                            DB::table('personables')->updateOrInsert([
                                'person_id' => $mp['person_id'],
                                'personable_type' => $mp['personable_type'],
                                'personable_id' => $mp['personable_id'],
                            ], $mp);
                        }
                    }
                }

                // 5. RESTORE SERIES, SEASONS & EPISODES
                if ($restoreSeries) {
                    if (isset($tables['series'])) {
                        $count = 0;
                        foreach ($tables['series'] as $series) {
                            if ($mode === 'overwrite') {
                                DB::table('series')->insert($series);
                            } else {
                                DB::table('series')->updateOrInsert(['id' => $series['id']], $series);
                            }
                            $count++;
                        }
                        $restoredCounts['series'] = $count;

                        // Restore series genreables
                        if (isset($tables['genreables'])) {
                            $seriesGenreables = array_filter($tables['genreables'], fn ($g) => str_contains($g['genreable_type'] ?? '', 'Series'));
                            foreach ($seriesGenreables as $sg) {
                                DB::table('genreables')->updateOrInsert([
                                    'genre_id' => $sg['genre_id'],
                                    'genreable_type' => $sg['genreable_type'],
                                    'genreable_id' => $sg['genreable_id'],
                                ], $sg);
                            }
                        }

                        // Restore series personables
                        if (isset($tables['personables']) && DB::getSchemaBuilder()->hasTable('personables')) {
                            $seriesPersonables = array_filter($tables['personables'], fn ($p) => str_contains($p['personable_type'] ?? '', 'Series'));
                            foreach ($seriesPersonables as $sp) {
                                DB::table('personables')->updateOrInsert([
                                    'person_id' => $sp['person_id'],
                                    'personable_type' => $sp['personable_type'],
                                    'personable_id' => $sp['personable_id'],
                                ], $sp);
                            }
                        }
                    }

                    if (isset($tables['seasons'])) {
                        $count = 0;
                        $chunks = array_chunk($tables['seasons'], 100);
                        foreach ($chunks as $chunk) {
                            if ($mode === 'overwrite') {
                                DB::table('seasons')->insert($chunk);
                                $count += count($chunk);
                            } else {
                                foreach ($chunk as $season) {
                                    DB::table('seasons')->updateOrInsert(['id' => $season['id']], $season);
                                    $count++;
                                }
                            }
                        }
                        $restoredCounts['seasons'] = $count;
                    }

                    if (isset($tables['episodes'])) {
                        $count = 0;
                        $chunks = array_chunk($tables['episodes'], 100);
                        foreach ($chunks as $chunk) {
                            if ($mode === 'overwrite') {
                                DB::table('episodes')->insert($chunk);
                                $count += count($chunk);
                            } else {
                                foreach ($chunk as $episode) {
                                    DB::table('episodes')->updateOrInsert(['id' => $episode['id']], $episode);
                                    $count++;
                                }
                            }
                        }
                        $restoredCounts['episodes'] = $count;
                    }
                }

                // 6. RESTORE SUBTITLES
                if ($restoreSubtitles && isset($tables['subtitles'])) {
                    $count = 0;
                    $chunks = array_chunk($tables['subtitles'], 100);
                    foreach ($chunks as $chunk) {
                        if ($mode === 'overwrite') {
                            DB::table('subtitles')->insert($chunk);
                            $count += count($chunk);
                        } else {
                            foreach ($chunk as $sub) {
                                DB::table('subtitles')->updateOrInsert(['id' => $sub['id']], $sub);
                                $count++;
                            }
                        }
                    }
                    $restoredCounts['subtitles'] = $count;
                }

                // 7. RESTORE WATCH HISTORIES
                if ($restoreHistory && isset($tables['watch_histories'])) {
                    $count = 0;
                    foreach ($tables['watch_histories'] as $history) {
                        DB::table('watch_histories')->updateOrInsert(['id' => $history['id']], $history);
                        $count++;
                    }
                    $restoredCounts['watch_histories'] = $count;
                }
            });
        } finally {
            // Re-enable foreign keys
            if (config('database.default') === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON;');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
            }
        }

        return [
            'success' => true,
            'message' => 'Selected library sections restored successfully.',
            'mode' => $mode,
            'sections' => $includeSections,
            'restored_counts' => $restoredCounts,
            'restored_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Restore selectively from SQLite file by extracting table records.
     */
    protected function restoreFromSqliteSelective(string $sqliteFilePath, string $mode, array $includeSections): array
    {
        $pdo = new \PDO("sqlite:" . $sqliteFilePath);
        $tables = [];

        foreach ($this->libraryTables as $table) {
            try {
                $stmt = $pdo->query("SELECT * FROM {$table}");
                if ($stmt) {
                    $tables[$table] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                }
            } catch (\Throwable $e) {
                // Table might not exist in backup
            }
        }

        return $this->restoreFromJson(['tables' => $tables], $mode, $includeSections);
    }

    /**
     * Full restore from SQLite binary database file.
     */
    protected function restoreFromSqliteBinary(string $sqliteFilePath): array
    {
        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException("Cannot restore SQLite binary file when database driver is not sqlite.");
        }

        $targetDb = config('database.connections.sqlite.database');

        // Validate header has "SQLite format 3"
        $handle = fopen($sqliteFilePath, 'rb');
        $header = fread($handle, 16);
        fclose($handle);

        if (! str_starts_with($header, "SQLite format 3\0")) {
            throw new \InvalidArgumentException("Invalid SQLite database file header.");
        }

        // Close active connections
        DB::disconnect();

        // Copy over active database
        File::copy($sqliteFilePath, $targetDb);

        // Reconnect and verify
        DB::reconnect();
        $mediaCount = DB::table('media_items')->count();
        $seriesCount = DB::table('series')->count();

        return [
            'success' => true,
            'message' => "SQLite binary database restored successfully ({$mediaCount} movies, {$seriesCount} series).",
            'mode' => 'sqlite_binary_clone',
            'sections' => ['all'],
            'restored_counts' => [
                'media_items' => $mediaCount,
                'series' => $seriesCount,
            ],
            'restored_at' => date('Y-m-d H:i:s'),
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
