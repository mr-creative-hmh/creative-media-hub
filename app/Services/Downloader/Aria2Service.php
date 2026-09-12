<?php

namespace App\Services\Downloader;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Aria2Service
{
    protected string $rpcUrl;

    protected ?string $binaryPath = null;

    public function __construct(string $rpcUrl = 'http://127.0.0.1:6800/jsonrpc')
    {
        $this->rpcUrl = $rpcUrl;
    }

    /**
     * Resolve the absolute path to the aria2c binary (preferring project bundled binary).
     */
    public function getBinaryPath(): ?string
    {
        if ($this->binaryPath !== null) {
            return $this->binaryPath;
        }

        // 1. Check project bundled windows binary
        $bundledWindows = base_path('bin/windows/aria2c.exe');
        if (file_exists($bundledWindows)) {
            return $this->binaryPath = $bundledWindows;
        }

        // 2. Check general bundled binary
        $bundledGeneral = base_path('bin/aria2c.exe');
        if (file_exists($bundledGeneral)) {
            return $this->binaryPath = $bundledGeneral;
        }

        // 3. Check WinGet link if present on host
        $wingetLink = 'C:\\Users\\hasan\\AppData\\Local\\Microsoft\\WinGet\\Links\\aria2c.exe';
        if (file_exists($wingetLink)) {
            return $this->binaryPath = $wingetLink;
        }

        // 4. Check system PATH
        $which = PHP_OS_FAMILY === 'Windows' ? 'where aria2c 2>NUL' : 'which aria2c 2>/dev/null';
        $output = @shell_exec($which);
        if ($output) {
            $first = trim(explode("\n", trim($output))[0]);
            if (! empty($first) && file_exists($first)) {
                return $this->binaryPath = $first;
            }
        }

        return null;
    }

    /**
     * Check if aria2c is available either bundled or on system.
     */
    public function isAvailable(): bool
    {
        return $this->getBinaryPath() !== null;
    }

    /**
     * Get all currently running OS PIDs for aria2c.
     *
     * @return array<int> List of PIDs
     */
    public function getRunningProcessPids(): array
    {
        $pids = [];
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $out = @shell_exec('tasklist /FI "IMAGENAME eq aria2c.exe" /FO CSV /NH 2>NUL');
                if ($out) {
                    $lines = explode("\n", trim($out));
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line) || str_contains($line, 'No tasks')) {
                            continue;
                        }
                        $parts = str_getcsv($line);
                        if (! empty($parts[1]) && is_numeric($parts[1])) {
                            $pids[] = (int) $parts[1];
                        }
                    }
                }
            } else {
                $out = @shell_exec('pgrep -x aria2c 2>/dev/null');
                if ($out) {
                    foreach (explode("\n", trim($out)) as $pid) {
                        if (is_numeric(trim($pid))) {
                            $pids[] = (int) trim($pid);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Error querying aria2c process list: '.$e->getMessage());
        }

        return array_values(array_unique($pids));
    }

    /**
     * Check if at least one aria2c process is active on the OS.
     */
    public function isProcessRunning(): bool
    {
        return count($this->getRunningProcessPids()) > 0;
    }

    /**
     * Kill duplicate orphan aria2c processes, keeping at most one.
     */
    public function killDuplicateProcesses(?int $keepPid = null): void
    {
        $pids = $this->getRunningProcessPids();
        if (count($pids) <= 1) {
            return;
        }

        $targetKeep = $keepPid ?? $pids[0];
        foreach ($pids as $pid) {
            if ($pid !== $targetKeep) {
                $this->killPid($pid);
            }
        }
    }

    /**
     * Terminate a specific PID safely.
     */
    public function killPid(int $pid): void
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                @shell_exec("taskkill /F /PID {$pid} 2>NUL");
            } else {
                @shell_exec("kill -9 {$pid} 2>/dev/null");
            }
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    /**
     * Ensure aria2 JSON-RPC daemon is running under a strict singleton lock.
     */
    public function ensureDaemon(): bool
    {
        // 1. Quick check: if already responding, clean up any duplicate orphans and return
        if ($this->ping()) {
            $this->killDuplicateProcesses();

            return true;
        }

        // 2. Use a cache lock to avoid race conditions when multiple requests check simultaneously
        $lock = Cache::lock('aria2_daemon_lifecycle', 5);
        try {
            return (bool) $lock->block(3, function () {
                if ($this->ping()) {
                    $this->killDuplicateProcesses();

                    return true;
                }

                $bin = $this->getBinaryPath();
                if (! $bin) {
                    return false;
                }

                // If processes are running on OS but not responding to RPC, kill them first (hung instances)
                $existingPids = $this->getRunningProcessPids();
                if (! empty($existingPids)) {
                    foreach ($existingPids as $pid) {
                        $this->killPid($pid);
                    }
                    usleep(100000); // 100ms
                }

                // Launch daemon in background
                try {
                    if (PHP_OS_FAMILY === 'Windows') {
                        $escapedBin = escapeshellarg($bin);
                        pclose(popen("start /B \"\" {$escapedBin} --enable-rpc=true --rpc-listen-all=true --rpc-listen-port=6800 --quiet=true", 'r'));
                    } else {
                        $escapedBin = escapeshellarg($bin);
                        exec("{$escapedBin} --enable-rpc=true --rpc-listen-all=true --rpc-listen-port=6800 --quiet=true > /dev/null 2>&1 &");
                    }

                    // Wait up to 1.5 seconds for socket bind
                    for ($i = 0; $i < 8; $i++) {
                        usleep(200000); // 200ms
                        if ($this->ping()) {
                            $this->killDuplicateProcesses();

                            return true;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to auto-launch aria2 daemon: '.$e->getMessage());
                }

                return $this->ping();
            });
        } catch (\Throwable $e) {
            Log::warning('Lock exception during aria2 ensureDaemon: '.$e->getMessage());

            return $this->ping();
        }
    }

    /**
     * Stop the aria2 daemon completely (graceful RPC shutdown + OS process kill fallback).
     */
    public function stopDaemon(): bool
    {
        $lock = Cache::lock('aria2_daemon_lifecycle', 5);
        try {
            return (bool) $lock->block(3, function () {
                // 1. Attempt graceful shutdown via JSON-RPC if responding
                if ($this->ping()) {
                    try {
                        Http::timeout(2)->post($this->rpcUrl, [
                            'jsonrpc' => '2.0',
                            'id' => 'shutdown',
                            'method' => 'aria2.forceShutdown',
                        ]);
                    } catch (\Throwable $e) {
                        // ignore
                    }
                    usleep(200000); // 200ms
                }

                // 2. Force-kill all remaining OS aria2c processes
                $pids = $this->getRunningProcessPids();
                foreach ($pids as $pid) {
                    $this->killPid($pid);
                }

                if (PHP_OS_FAMILY === 'Windows') {
                    @shell_exec('taskkill /F /IM aria2c.exe /T 2>NUL');
                } else {
                    @shell_exec('killall -9 aria2c 2>/dev/null');
                }

                usleep(150000); // 150ms

                return ! $this->isProcessRunning();
            });
        } catch (\Throwable $e) {
            Log::warning('Exception during stopDaemon: '.$e->getMessage());

            return ! $this->isProcessRunning();
        }
    }

    /**
     * Stop the daemon if there are no active or waiting downloads.
     */
    public function stopDaemonIfIdle(): bool
    {
        if (! $this->isProcessRunning()) {
            return true;
        }

        if (! $this->ping()) {
            // Hung process, stop it
            return $this->stopDaemon();
        }

        try {
            $stat = $this->call('aria2.getGlobalStat');
            $numActive = (int) ($stat['numActive'] ?? 0);
            $numWaiting = (int) ($stat['numWaiting'] ?? 0);

            if ($numActive === 0 && $numWaiting === 0) {
                Log::info('Aria2 daemon is idle with 0 active/waiting downloads. Stopping daemon.');

                return $this->stopDaemon();
            }
        } catch (\Throwable $e) {
            // If failed to fetch stat, don't kill arbitrarily unless not responding
        }

        return false;
    }

    /**
     * Ping the aria2 JSON-RPC server.
     */
    public function ping(): bool
    {
        try {
            $res = Http::timeout(1)->post($this->rpcUrl, [
                'jsonrpc' => '2.0',
                'id' => 'ping',
                'method' => 'aria2.getVersion',
            ]);

            return $res->successful() && isset($res->json()['result']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get real-time status of the daemon and processes.
     */
    public function getDaemonStatus(): array
    {
        $pids = $this->getRunningProcessPids();
        $isPingable = $this->ping();
        $globalStat = $isPingable ? $this->call('aria2.getGlobalStat') : null;

        return [
            'running' => count($pids) > 0,
            'responding' => $isPingable,
            'process_count' => count($pids),
            'pids' => $pids,
            'global_stat' => $globalStat,
            'num_active' => (int) ($globalStat['numActive'] ?? 0),
            'num_waiting' => (int) ($globalStat['numWaiting'] ?? 0),
            'download_speed' => (int) ($globalStat['downloadSpeed'] ?? 0),
            'upload_speed' => (int) ($globalStat['uploadSpeed'] ?? 0),
        ];
    }

    /**
     * Call an aria2 JSON-RPC method.
     */
    public function call(string $method, array $params = []): mixed
    {
        if (! $this->ensureDaemon()) {
            return null;
        }

        try {
            $res = Http::timeout(5)->post($this->rpcUrl, [
                'jsonrpc' => '2.0',
                'id' => uniqid('a2_'),
                'method' => $method,
                'params' => $params,
            ]);

            if ($res->successful()) {
                $data = $res->json();

                return $data['result'] ?? null;
            }
        } catch (\Throwable $e) {
            Log::error("aria2 RPC call failed for {$method}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Add a .torrent file to aria2.
     */
    public function addTorrent(string $torrentFilePath, string $destFolder, array $selectFiles = [], array $options = []): ?string
    {
        $raw = file_exists($torrentFilePath) ? file_get_contents($torrentFilePath) : $torrentFilePath;
        if (! $raw) {
            return null;
        }

        $base64 = base64_encode($raw);
        $cleanDest = str_replace('\\', '/', $destFolder);

        $opts = array_merge([
            'dir' => $cleanDest,
            'seed-time' => '0',
            'summary-interval' => '1',
            'auto-file-renaming' => 'false',
            'allow-overwrite' => 'true',
        ], $options);

        if (! empty($selectFiles)) {
            $indexes = array_map('intval', $selectFiles);
            $indexes = array_filter($indexes, fn ($i) => $i > 0);
            if (! empty($indexes)) {
                $opts['select-file'] = implode(',', $indexes);
            }
        }

        $result = $this->call('aria2.addTorrent', [$base64, [], $opts]);

        return is_string($result) ? $result : null;
    }

    /**
     * Add a magnet link or HTTP/HTTPS direct URL to aria2.
     */
    public function addUri(string $uri, string $destFolder, array $selectFiles = [], array $options = []): ?string
    {
        $cleanDest = str_replace('\\', '/', $destFolder);

        $opts = array_merge([
            'dir' => $cleanDest,
            'seed-time' => '0',
            'summary-interval' => '1',
            'auto-file-renaming' => 'false',
            'allow-overwrite' => 'true',
        ], $options);

        if (! empty($selectFiles) && str_starts_with($uri, 'magnet:')) {
            $indexes = array_map('intval', $selectFiles);
            $indexes = array_filter($indexes, fn ($i) => $i > 0);
            if (! empty($indexes)) {
                $opts['select-file'] = implode(',', $indexes);
            }
        }

        $result = $this->call('aria2.addUri', [[$uri], $opts]);

        return is_string($result) ? $result : null;
    }

    /**
     * Get detailed status of a download.
     * Recursively follows followedBy chains (magnet metadata -> payload download).
     */
    public function tellStatus(string $gid): ?array
    {
        $res = $this->call('aria2.tellStatus', [$gid]);
        if (! $res || ! is_array($res)) {
            return null;
        }

        // If this task spawned a child task (magnet metadata download -> actual payload download)
        if (! empty($res['followedBy']) && is_array($res['followedBy'])) {
            $childGid = $res['followedBy'][0];
            $childStatus = $this->tellStatus($childGid);
            if ($childStatus) {
                $childStatus['parent_gid'] = $gid;

                return $childStatus;
            }
        }

        $totalLength = (int) ($res['totalLength'] ?? 0);
        $completedLength = (int) ($res['completedLength'] ?? 0);
        $downloadSpeed = (int) ($res['downloadSpeed'] ?? 0);
        $status = (string) ($res['status'] ?? 'unknown');
        $numSeeders = (int) ($res['numSeeders'] ?? 0);

        // Map status: active -> downloading, waiting -> queued, complete -> completed
        $mappedStatus = match ($status) {
            'active' => 'downloading',
            'waiting' => 'queued',
            'paused' => 'paused',
            'complete' => 'completed',
            'error' => 'failed',
            'removed' => 'failed',
            default => $status,
        };

        return [
            'gid' => $gid,
            'raw_status' => $status,
            'status' => $mappedStatus,
            'total_bytes' => $totalLength,
            'downloaded_bytes' => $completedLength,
            'speed_bytes_sec' => $downloadSpeed,
            'num_seeders' => $numSeeders,
            'files' => $res['files'] ?? [],
            'error_code' => $res['errorCode'] ?? null,
            'error_message' => $res['errorMessage'] ?? null,
            'parent_gid' => null,
            'following' => $res['following'] ?? null,
        ];
    }

    /**
     * Pause a download (resolving active child GID if applicable).
     */
    public function pause(string $gid): bool
    {
        $status = $this->tellStatus($gid);
        $targetGid = $status['gid'] ?? $gid;
        $res = $this->call('aria2.pause', [$targetGid]);

        return $res !== null;
    }

    /**
     * Unpause / resume a download (resolving active child GID if applicable).
     */
    public function unpause(string $gid): bool
    {
        $status = $this->tellStatus($gid);
        $targetGid = $status['gid'] ?? $gid;
        $res = $this->call('aria2.unpause', [$targetGid]);

        return $res !== null;
    }

    /**
     * Remove / cancel a download (cleaning up parent and child).
     */
    public function remove(string $gid): bool
    {
        $status = $this->tellStatus($gid);
        $targetGid = $status['gid'] ?? $gid;
        $res = $this->call('aria2.forceRemove', [$targetGid]);
        if ($res === null) {
            $res = $this->call('aria2.remove', [$targetGid]);
        }
        if ($targetGid !== $gid) {
            $this->call('aria2.forceRemove', [$gid]);
        }

        return $res !== null;
    }

    public function configureLimits(int $maxConcurrent = 3, int $speedLimitKb = 0): bool
    {
        $opts = [
            'max-concurrent-downloads' => (string) max(1, $maxConcurrent),
        ];

        if ($speedLimitKb > 0) {
            $opts['max-overall-download-limit'] = (string) ($speedLimitKb * 1024);
        } else {
            $opts['max-overall-download-limit'] = '0';
        }

        $res = $this->call('aria2.changeGlobalOption', [$opts]);

        return $res !== null;
    }
}
