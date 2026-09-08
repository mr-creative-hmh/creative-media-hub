<?php

namespace App\Services\Organizer;

use App\Models\AppSetting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class OrganizerWatcherService
{
    public function __construct(
        protected FilesystemScannerService $scanner,
        protected PhysicalOrganizerService $organizer
    ) {}

    /**
     * Get list of monitored folder paths.
     */
    public function getWatchedFolders(): array
    {
        $setting = AppSetting::where('key', 'organizer_watched_folders')->first();
        if ($setting !== null) {
            $val = $setting->value;
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } elseif (is_array($val)) {
                return $val;
            }
        }

        $defaultDownloads = 'D:/Downloads';
        $mediaDownloads = str_replace('\\', '/', base_path('storage/app/media/Downloads'));

        $defaults = [];
        if (File::isDirectory($defaultDownloads)) {
            $defaults[] = $defaultDownloads;
        }
        $defaults[] = $mediaDownloads;

        return array_values(array_unique($defaults));
    }

    /**
     * Check if the auto-watcher is enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) AppSetting::get('organizer_watcher_enabled', false);
    }

    /**
     * Enable or disable directory watcher.
     */
    public function setEnabled(bool $enabled): void
    {
        AppSetting::set('organizer_watcher_enabled', $enabled ? '1' : '0');
    }

    /**
     * Add a folder to the watch list.
     */
    public function addFolder(string $path): array
    {
        $path = rtrim(str_replace('\\', '/', trim($path)), '/');
        $current = $this->getWatchedFolders();
        $normalizedExisting = array_map(fn ($f) => strtolower(rtrim(str_replace('\\', '/', trim($f)), '/')), $current);

        if (! in_array(strtolower($path), $normalizedExisting)) {
            $current[] = $path;
            AppSetting::set('organizer_watched_folders', $current, 'json');
        }

        return $current;
    }

    /**
     * Remove a folder from the watch list.
     */
    public function removeFolder(string $path): array
    {
        $normalizedTarget = strtolower(rtrim(str_replace('\\', '/', trim($path)), '/'));
        $current = array_values(array_filter(
            $this->getWatchedFolders(),
            function ($f) use ($normalizedTarget) {
                $norm = strtolower(rtrim(str_replace('\\', '/', trim($f)), '/'));
                return $norm !== $normalizedTarget;
            }
        ));
        AppSetting::set('organizer_watched_folders', $current, 'json');

        return $current;
    }

    /**
     * Get complete watcher status.
     */
    public function getStatus(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'watched_folders' => $this->getWatchedFolders(),
            'target_root' => AppSetting::get('organizer_default_target_root', 'H:/Entertainment'),
            'last_check' => AppSetting::get('organizer_watcher_last_check', null),
            'auto_execute' => (bool) AppSetting::get('organizer_watcher_auto_execute', false),
            'items_organized_count' => (int) AppSetting::get('organizer_watcher_total_organized', 0),
        ];
    }

    /**
     * Check watched folders and organize any stable completed media.
     */
    public function checkAndOrganize(bool $dryRunOnly = false): array
    {
        $folders = $this->getWatchedFolders();
        $targetRoot = AppSetting::get('organizer_default_target_root', 'H:/Entertainment');
        $movieTemplate = AppSetting::get('movie_naming_template');
        $seriesTemplate = AppSetting::get('series_naming_template');

        $allScanned = [];
        foreach ($folders as $folder) {
            if (File::isDirectory($folder)) {
                $scanned = $this->scanner->scanDirectory($folder, true);
                $allScanned = array_merge($allScanned, $scanned);
            }
        }

        // Filter out files currently being written / unstable (modified < 20s ago)
        $now = time();
        $stableFiles = array_values(array_filter($allScanned, function ($f) use ($now) {
            $mtime = $f['modified_at'] ?? 0;
            return ($now - $mtime) >= 15;
        }));

        $plan = $this->organizer->generateDryRun($stableFiles, $targetRoot, $movieTemplate, $seriesTemplate);

        $readyItems = array_values(array_filter($plan, fn ($item) => ($item['status'] ?? '') === 'ready'));

        AppSetting::set('organizer_watcher_last_check', date('Y-m-d H:i:s'));

        $executed = false;
        $executionResult = null;
        if (! $dryRunOnly && ! empty($readyItems) && (bool) AppSetting::get('organizer_watcher_auto_execute', false)) {
            $executionResult = $this->organizer->execute($readyItems, 'move');
            $prevTotal = (int) AppSetting::get('organizer_watcher_total_organized', 0);
            AppSetting::set('organizer_watcher_total_organized', (string) ($prevTotal + count($readyItems)));
            $executed = true;
        }

        return [
            'total_scanned' => count($allScanned),
            'stable_count' => count($stableFiles),
            'ready_count' => count($readyItems),
            'executed' => $executed,
            'plan' => $plan,
            'execution_result' => $executionResult,
        ];
    }
}
