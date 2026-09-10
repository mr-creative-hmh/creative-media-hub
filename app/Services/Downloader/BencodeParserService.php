<?php

namespace App\Services\Downloader;

class BencodeParserService
{
    /**
     * Decode a bencoded string or file into PHP data structures.
     */
    public function decode(string $data): mixed
    {
        $pos = 0;

        return $this->decodeEntry($data, $pos);
    }

    /**
     * Parse a .torrent file from filesystem.
     */
    public function parseTorrentFile(string $filePath): ?array
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        return $this->parseTorrentString($content);
    }

    /**
     * Parse raw torrent content string.
     */
    public function parseTorrentString(string $data): ?array
    {
        try {
            $decoded = $this->decode($data);
            if (! is_array($decoded) || ! isset($decoded['info'])) {
                return null;
            }

            $info = $decoded['info'];
            $name = $info['name.utf-8'] ?? $info['name'] ?? 'Unknown Torrent';
            if (is_array($name)) {
                $name = implode(' ', $name);
            }
            $name = trim((string) $name);
            $infoHash = sha1($this->encode($info));

            $files = [];
            $totalSize = 0;
            $foldersSet = [];

            if (isset($info['files']) && is_array($info['files'])) {
                // Multi-file torrent
                foreach ($info['files'] as $originalIdx => $file) {
                    $length = (int) ($file['length'] ?? 0);
                    $pathParts = $file['path.utf-8'] ?? $file['path'] ?? [$name];
                    if (! is_array($pathParts)) {
                        $pathParts = [$pathParts];
                    }

                    // Normalize path parts
                    $cleanParts = [];
                    foreach ($pathParts as $part) {
                        $partStr = trim((string) $part, "/\\ ");
                        if ($partStr !== '') {
                            $cleanParts[] = $partStr;
                        }
                    }

                    if (empty($cleanParts)) {
                        continue;
                    }

                    $relativePath = implode('/', $cleanParts);
                    $filename = end($cleanParts);
                    $folder = count($cleanParts) > 1 ? implode('/', array_slice($cleanParts, 0, -1)) : '';
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                    // Skip empty directory markers (length 0 and no extension or ends with slash)
                    if ($length === 0 && ($ext === '' || str_ends_with($relativePath, '/'))) {
                        continue;
                    }

                    // Skip internal piece-alignment padding files (BEP 47)
                    if (str_contains($relativePath, '_____padding_file_') || str_contains($relativePath, '.pad/')) {
                        continue;
                    }

                    $isVideo = in_array($ext, ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'ts', 'm4v', 'iso', 'vob']);
                    $isSubtitle = in_array($ext, ['srt', 'vtt', 'sub', 'ass', 'ssa', 'idx']);
                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp']);

                    // aria2c uses 1-based indexing matching the original position in info['files']
                    $ariaIndex = $originalIdx + 1;

                    if ($folder !== '') {
                        $foldersSet[$folder] = true;
                    }

                    $files[] = [
                        'index' => $ariaIndex,
                        'path' => $relativePath,
                        'folder' => $folder,
                        'filename' => $filename,
                        'size' => $length,
                        'is_video' => $isVideo,
                        'is_subtitle' => $isSubtitle,
                        'is_image' => $isImage,
                        'extension' => $ext,
                        'selected' => $isVideo || $length > 20000000, // Select videos or files > 20MB by default
                    ];

                    $totalSize += $length;
                }
            } else {
                // Single-file torrent
                $length = (int) ($info['length'] ?? 0);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $isVideo = in_array($ext, ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'ts', 'm4v', 'iso', 'vob']);
                $isSubtitle = in_array($ext, ['srt', 'vtt', 'sub', 'ass', 'ssa', 'idx']);
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp']);

                $files[] = [
                    'index' => 1,
                    'path' => $name,
                    'folder' => '',
                    'filename' => $name,
                    'size' => $length,
                    'is_video' => $isVideo,
                    'is_subtitle' => $isSubtitle,
                    'is_image' => $isImage,
                    'extension' => $ext,
                    'selected' => true,
                ];
                $totalSize = $length;
            }

            $tree = $this->buildFolderTree($files);

            return [
                'name' => $name,
                'info_hash' => $infoHash,
                'total_size' => $totalSize,
                'files' => $files,
                'tree' => $tree,
                'folder_count' => count($foldersSet),
                'file_count' => count($files),
                'piece_length' => (int) ($info['piece length'] ?? 0),
                'announce' => $decoded['announce'] ?? null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Build hierarchical tree containing folders and files.
     */
    public function buildFolderTree(array $files): array
    {
        $treeRoot = [];

        foreach ($files as $file) {
            $parts = explode('/', $file['path']);
            $filename = array_pop($parts);
            $current = &$treeRoot;
            $currentPath = '';

            foreach ($parts as $folder) {
                $currentPath = $currentPath ? "{$currentPath}/{$folder}" : $folder;
                if (! isset($current[$folder])) {
                    $current[$folder] = [
                        'type' => 'folder',
                        'name' => $folder,
                        'path' => $currentPath,
                        'size' => 0,
                        'file_count' => 0,
                        'video_count' => 0,
                        'file_indexes' => [],
                        'children' => [],
                    ];
                }
                $current[$folder]['size'] += $file['size'];
                $current[$folder]['file_count']++;
                if (! empty($file['is_video'])) {
                    $current[$folder]['video_count']++;
                }
                $current[$folder]['file_indexes'][] = $file['index'];
                $current = &$current[$folder]['children'];
            }

            $current[$filename] = [
                'type' => 'file',
                'name' => $filename,
                'path' => $file['path'],
                'folder' => $file['folder'] ?? '',
                'size' => $file['size'],
                'index' => $file['index'],
                'is_video' => ! empty($file['is_video']),
                'is_subtitle' => ! empty($file['is_subtitle']),
                'extension' => $file['extension'] ?? pathinfo($filename, PATHINFO_EXTENSION),
            ];
        }

        $convert = function ($nodes) use (&$convert) {
            $folders = [];
            $fileNodes = [];
            foreach ($nodes as $node) {
                if ($node['type'] === 'folder') {
                    $node['children'] = $convert($node['children']);
                    $folders[] = $node;
                } else {
                    $fileNodes[] = $node;
                }
            }
            usort($folders, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
            usort($fileNodes, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

            return array_merge($folders, $fileNodes);
        };

        return $convert($treeRoot);
    }

    /**
     * Parse magnet URI string (magnet:?xt=urn:btih:...)
     */
    public function parseMagnet(string $magnetUri): ?array
    {
        if (! str_starts_with($magnetUri, 'magnet:?')) {
            return null;
        }

        $query = substr($magnetUri, 8);
        parse_str($query, $params);

        $xt = $params['xt'] ?? '';
        $infoHash = '';
        if (preg_match('/urn:btih:([a-fA-F0-9]{40}|[a-zA-Z2-7]{32})/i', $xt, $matches)) {
            $infoHash = strtolower($matches[1]);
        }

        $displayName = $params['dn'] ?? 'Torrent Download';
        $displayName = urldecode(str_replace('+', ' ', $displayName));

        // Check if we already cached the downloaded .torrent metadata for this hash
        $cachedTorrent = storage_path("app/torrents/{$infoHash}.torrent");
        if (file_exists($cachedTorrent)) {
            $parsed = $this->parseTorrentFile($cachedTorrent);
            if ($parsed) {
                return $parsed;
            }
        }

        // Clean magnet presentation without fake sample files
        $ext = strtolower(pathinfo($displayName, PATHINFO_EXTENSION));
        $isVideo = in_array($ext, ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'ts', 'm4v', 'iso']);
        $provisionalPath = $displayName.(($ext === '' && ! str_contains($displayName, '.')) ? '.mkv' : '');

        $files = [
            [
                'index' => 1,
                'path' => $provisionalPath,
                'folder' => '',
                'filename' => basename($provisionalPath),
                'size' => 0, // Resolved upon swarm connection
                'is_video' => true,
                'is_subtitle' => false,
                'is_image' => false,
                'extension' => pathinfo($provisionalPath, PATHINFO_EXTENSION) ?: 'mkv',
                'selected' => true,
            ],
        ];

        return [
            'name' => $displayName,
            'info_hash' => $infoHash,
            'total_size' => 0,
            'files' => $files,
            'tree' => $this->buildFolderTree($files),
            'folder_count' => 0,
            'file_count' => 1,
            'is_magnet' => true,
            'piece_length' => 0,
            'announce' => $params['tr'] ?? null,
        ];
    }

    /**
     * Internal recursive entry decoder.
     */
    protected function decodeEntry(string $data, int &$pos): mixed
    {
        if ($pos >= strlen($data)) {
            return null;
        }

        $char = $data[$pos];

        // Integer: i<integer>e
        if ($char === 'i') {
            $pos++;
            $end = strpos($data, 'e', $pos);
            if ($end === false) {
                return null;
            }
            $numStr = substr($data, $pos, $end - $pos);
            $pos = $end + 1;

            return (int) $numStr;
        }

        // List: l<entries>e
        if ($char === 'l') {
            $pos++;
            $list = [];
            while ($pos < strlen($data) && $data[$pos] !== 'e') {
                $list[] = $this->decodeEntry($data, $pos);
            }
            $pos++; // Skip 'e'

            return $list;
        }

        // Dictionary: d<key><val>e
        if ($char === 'd') {
            $pos++;
            $dict = [];
            while ($pos < strlen($data) && $data[$pos] !== 'e') {
                $key = $this->decodeEntry($data, $pos);
                if (! is_string($key)) {
                    break;
                }
                $dict[$key] = $this->decodeEntry($data, $pos);
            }
            $pos++; // Skip 'e'

            return $dict;
        }

        // Byte string: <length>:<string>
        if (ctype_digit($char)) {
            $colon = strpos($data, ':', $pos);
            if ($colon === false) {
                return null;
            }
            $len = (int) substr($data, $pos, $colon - $pos);
            $pos = $colon + 1;
            $str = substr($data, $pos, $len);
            $pos += $len;

            return $str;
        }

        return null;
    }

    /**
     * Bencode encode function for calculating info_hash.
     */
    public function encode(mixed $data): string
    {
        if (is_int($data)) {
            return 'i'.$data.'e';
        }

        if (is_string($data)) {
            return strlen($data).':'.$data;
        }

        if (is_array($data)) {
            $isAssoc = array_keys($data) !== range(0, count($data) - 1);
            if ($isAssoc) {
                ksort($data, SORT_STRING);
                $encoded = 'd';
                foreach ($data as $k => $v) {
                    $encoded .= $this->encode((string) $k);
                    $encoded .= $this->encode($v);
                }

                return $encoded.'e';
            } else {
                $encoded = 'l';
                foreach ($data as $v) {
                    $encoded .= $this->encode($v);
                }

                return $encoded.'e';
            }
        }

        return '';
    }
}
