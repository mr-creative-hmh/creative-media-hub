<?php

namespace App\Services\Organizer;

use Illuminate\Support\Facades\File;

class FilesystemScannerService
{
    protected array $videoExtensions = ['mkv', 'mp4', 'avi', 'mov', 'm4v', 'webm', 'ts', 'wmv', 'flv', 'iso'];
    protected array $subtitleExtensions = ['srt', 'vtt', 'sub', 'ass', 'idx', 'smi'];
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
                    'parsed' => $this->parser->parse($filePath),
                ];
            } elseif (in_array($ext, $this->subtitleExtensions)) {
                $subtitleFiles[] = [
                    'path' => $filePath,
                    'filename' => $file->getFilename(),
                    'size_bytes' => $file->getSize(),
                    'extension' => $ext,
                    'language' => $this->detectSubtitleLanguage($file->getFilename()),
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

            $parsedV = $v['parsed'];
            $isSeries = $parsedV['type'] === 'series';
            $sNum = $parsedV['season'];
            $epNum = $parsedV['episode'];

            foreach ($subtitleFiles as $sub) {
                $subDir = pathinfo($sub['path'], PATHINFO_DIRNAME);
                $subParent = pathinfo($subDir, PATHINFO_DIRNAME);
                $subBase = pathinfo($sub['filename'], PATHINFO_FILENAME);

                $isSameDir = ($subDir === $videoDir);
                $isSubFolder = ($subParent === $videoDir && in_array(strtolower(basename($subDir)), ['subs', 'subtitles', 'sub']));

                if ($isSameDir || $isSubFolder) {
                    $matches = false;

                    // 1. Direct prefix or base match
                    if (str_starts_with($subBase, $videoBase) || str_starts_with($videoBase, $subBase)) {
                        $matches = true;
                    }
                    // 2. Series Episode match (e.g. S01E02 in subtitle name)
                    elseif ($isSeries && $sNum !== null && $epNum !== null) {
                        $epPattern = sprintf('/[sS]%02d[eE]%02d|\b%dx%02d\b/i', $sNum, $epNum, $sNum, $epNum);
                        if (preg_match($epPattern, $subBase)) {
                            $matches = true;
                        }
                    }

                    if ($matches) {
                        $v['subtitles'][] = $sub;
                    }
                }
            }

            // Match poster
            foreach ($imageFiles as $img) {
                $imgDir = pathinfo($img['path'], PATHINFO_DIRNAME);
                $imgName = strtolower($img['filename']);
                if ($imgDir === $videoDir || pathinfo($imgDir, PATHINFO_DIRNAME) === $videoDir) {
                    if (str_contains($imgName, 'poster') || str_contains($imgName, 'cover') || str_contains($imgName, 'folder') || str_starts_with(pathinfo($img['filename'], PATHINFO_FILENAME), $videoBase)) {
                        $v['local_poster'] = $img['path'];
                        break;
                    }
                }
            }
        }

        return $videoFiles;
    }

    public function detectSubtitleLanguage(string $filename): string
    {
        $lower = strtolower($filename);
        if (preg_match('/\b(ar|ara|arabic|عربي)\b/i', $lower) || str_contains($lower, '.ar.') || str_ends_with($lower, '.ar.srt')) {
            return 'ar';
        }
        if (preg_match('/\b(en|eng|english|انجليزي)\b/i', $lower) || str_contains($lower, '.en.') || str_ends_with($lower, '.en.srt')) {
            return 'en';
        }
        if (preg_match('/\b(fr|fre|french)\b/i', $lower)) {
            return 'fr';
        }
        if (preg_match('/\b(es|spa|spanish)\b/i', $lower)) {
            return 'es';
        }
        if (preg_match('/\b(de|ger|german)\b/i', $lower)) {
            return 'de';
        }
        return 'und';
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
