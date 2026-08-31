<?php

namespace App\Services\Organizer;

use Illuminate\Support\Facades\File;

class FilesystemScannerService
{
    protected array $videoExtensions = ['mkv', 'mp4', 'avi', 'mov', 'm4v', 'webm', 'ts', 'wmv'];
    protected array $subtitleExtensions = ['srt', 'vtt', 'sub', 'ass', 'idx'];

    protected SceneNameParserService $parser;

    public function __construct(SceneNameParserService $parser)
    {
        $this->parser = $parser;
    }

    public function scanDirectory(string $path, bool $recursive = true): array
    {
        if (!File::isDirectory($path)) {
            return [];
        }

        $allFiles = $recursive ? File::allFiles($path) : File::files($path);
        $videoFiles = [];
        $subtitleFiles = [];

        foreach ($allFiles as $file) {
            $ext = strtolower($file->getExtension());
            if (in_array($ext, $this->videoExtensions)) {
                $videoFiles[] = [
                    'path' => str_replace('\\', '/', $file->getPathname()),
                    'filename' => $file->getFilename(),
                    'size_bytes' => $file->getSize(),
                    'size_formatted' => $this->formatBytes($file->getSize()),
                    'modified_at' => $file->getMTime(),
                    'extension' => $ext,
                    'parsed' => $this->parser->parse($file->getFilename()),
                ];
            } elseif (in_array($ext, $this->subtitleExtensions)) {
                $subtitleFiles[] = [
                    'path' => str_replace('\\', '/', $file->getPathname()),
                    'filename' => $file->getFilename(),
                    'size_bytes' => $file->getSize(),
                    'extension' => $ext,
                ];
            }
        }

        // Pair subtitles with adjacent video files
        foreach ($videoFiles as &$v) {
            $videoBase = pathinfo($v['filename'], PATHINFO_FILENAME);
            $videoDir = pathinfo($v['path'], PATHINFO_DIRNAME);
            $v['subtitles'] = [];

            foreach ($subtitleFiles as $sub) {
                $subDir = pathinfo($sub['path'], PATHINFO_DIRNAME);
                if ($subDir === $videoDir && str_starts_with(pathinfo($sub['filename'], PATHINFO_FILENAME), $videoBase)) {
                    $v['subtitles'][] = $sub;
                }
            }
        }

        return $videoFiles;
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
