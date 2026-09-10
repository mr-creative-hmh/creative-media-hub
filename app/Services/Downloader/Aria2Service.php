<?php

namespace App\Services\Downloader;

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
     * Ensure aria2 JSON-RPC daemon is running.
     */
    public function ensureDaemon(): bool
    {
        // 1. Quick check if already responding
        if ($this->ping()) {
            return true;
        }

        $bin = $this->getBinaryPath();
        if (! $bin) {
            return false;
        }

        // 2. Launch daemon in background
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $escapedBin = escapeshellarg($bin);
                pclose(popen("start /B \"\" {$escapedBin} --enable-rpc=true --rpc-listen-all=true --rpc-listen-port=6800 --quiet=true", 'r'));
            } else {
                $escapedBin = escapeshellarg($bin);
                exec("{$escapedBin} --enable-rpc=true --rpc-listen-all=true --rpc-listen-port=6800 --quiet=true > /dev/null 2>&1 &");
            }

            // Wait up to 2 seconds for socket bind
            for ($i = 0; $i < 10; $i++) {
                usleep(200000); // 200ms
                if ($this->ping()) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to auto-launch aria2 daemon: ' . $e->getMessage());
        }

        return $this->ping();
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
            Log::error("aria2 RPC call failed for {$method}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Add a .torrent file to aria2.
     *
     * @param string $torrentFilePath Path to .torrent on disk or raw binary
     * @param string $destFolder Target download folder
     * @param array $selectFiles 1-based indexes of files to download
     * @return string|null The download GID
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
            // Ensure indexes are 1-based comma separated string
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
     *
     * @param string $uri Magnet URI or HTTP URL
     * @param string $destFolder Target download folder
     * @param array $selectFiles 1-based indexes of files to download
     * @return string|null The download GID
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
     */
    public function tellStatus(string $gid): ?array
    {
        $res = $this->call('aria2.tellStatus', [$gid]);
        if (! $res || ! is_array($res)) {
            return null;
        }

        $totalLength = (int) ($res['totalLength'] ?? 0);
        $completedLength = (int) ($res['completedLength'] ?? 0);
        $downloadSpeed = (int) ($res['downloadSpeed'] ?? 0);
        $status = (string) ($res['status'] ?? 'unknown');

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
            'files' => $res['files'] ?? [],
            'error_code' => $res['errorCode'] ?? null,
            'error_message' => $res['errorMessage'] ?? null,
        ];
    }

    /**
     * Pause a download.
     */
    public function pause(string $gid): bool
    {
        $res = $this->call('aria2.pause', [$gid]);
        return $res !== null;
    }

    /**
     * Unpause / resume a download.
     */
    public function unpause(string $gid): bool
    {
        $res = $this->call('aria2.unpause', [$gid]);
        return $res !== null;
    }

    /**
     * Remove / cancel a download.
     */
    public function remove(string $gid): bool
    {
        $res = $this->call('aria2.forceRemove', [$gid]);
        if ($res === null) {
            $res = $this->call('aria2.remove', [$gid]);
        }
        return $res !== null;
    }

    /**
     * Change global speed limit and concurrent downloads.
     */
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
