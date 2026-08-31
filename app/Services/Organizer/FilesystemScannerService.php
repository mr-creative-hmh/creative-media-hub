<?php

namespace App\Services\Organizer;

use Illuminate\Support\Facades\File;

class FilesystemScannerService
{
    protected array $videoExtensions = ['mkv', 'mp4', 'avi', 'mov', 'm4v', 'webm', 'ts', 'wmv', 'flv', 'iso'];
    protected array $subtitleExtensions = ['srt', 'vtt', 'sub', 'ass', 'idx'];
    protected array $posterExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    protected SceneNameParserService $parser;

    public function __construct(SceneNameParserService $parser)
    {
        $this->parser = $parser;
    }

    public function scanDirectory(string $path, bool $recursive = true): array
    {
        $normalizedPath = $this->normalizePath($path);

        if (!is_dir($normalizedPath) && !is_dir($path)) {
            return [];
        }

        $targetDir = is_dir($normalizedPath) ? $normalizedPath : $path;

        try {
            $allFiles = $recursive ? File::allFiles($targetDir) : File::files($targetDir);
        } catch (\Throwable $e) {
            return [];
        }

        $videoFiles = [];
        $subtitleFiles = [];
        $imageFiles = [];

        foreach ($allFiles as $file) {
            $ext = strtolower($file->getExtension());
            $filePath = $this->normalizePath($file->getPathname());

            if (in_array($ext, $this->videoExtensions)) {
                $videoFiles[] = [
                    'path' => $filePath,
                    'filename' => $file->getFilename(),
                    'size_bytes' => $file->getSize(),
                    'size_formatted' => $this->formatBytes($file->getSize()),
                    'modified_at' => $file->getMTime(),
                    'extension' => $ext,
                    'parsed' => $this->parser->parse($file->getFilename()),
                ];
            } elseif (in_array($ext, $this->subtitleExtensions)) {
                $subtitleFiles[] = [
                    'path' => $filePath,
                    'filename' => $file->getFilename(),
                    'size_bytes' => $file->getSize(),
                    'extension' => $ext,
                ];
            } elseif (in_array($ext, $this->posterExtensions)) {
                $imageFiles[] = [
                    'path' => $filePath,
                    'filename' => $file->getFilename(),
                ];
            }
        }

        // Pair subtitles & local artwork with adjacent video files
        foreach ($videoFiles as &$v) {
            $videoBase = pathinfo($v['filename'], PATHINFO_FILENAME);
            $videoDir = pathinfo($v['path'], PATHINFO_DIRNAME);
            $v['subtitles'] = [];
            $v['local_poster'] = null;

            foreach ($subtitleFiles as $sub) {
                $subDir = pathinfo($sub['path'], PATHINFO_DIRNAME);
                if ($subDir === $videoDir && str_starts_with(pathinfo($sub['filename'], PATHINFO_FILENAME), $videoBase)) {
                    $v['subtitles'][] = $sub;
                }
            }

            foreach ($imageFiles as $img) {
                $imgDir = pathinfo($img['path'], PATHINFO_DIRNAME);
                $imgName = strtolower($img['filename']);
                if ($imgDir === $videoDir) {
                    if (str_contains($imgName, 'poster') || str_contains($imgName, 'cover') || str_contains($imgName, 'folder') || str_starts_with(pathinfo($img['filename'], PATHINFO_FILENAME), $videoBase)) {
                        $v['local_poster'] = $img['path'];
                        break;
                    }
                }
            }
        }

        return $videoFiles;
    }

    public function normalizePath(string $path): string
    {
        $p = str_replace('\\', '/', trim($path));
        return rtrim($p, '/');
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        return round($bytes / 1024, 2) . ' KB';
    }
}
