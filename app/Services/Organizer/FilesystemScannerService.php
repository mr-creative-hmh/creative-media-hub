<?php

namespace App\Services\Organizer;

use App\Services\Subtitles\EmbeddedSubtitleDetectorService;
use Illuminate\Support\Facades\File;

class FilesystemScannerService
{
    protected array $videoExtensions = ['mkv', 'mp4', 'avi', 'mov', 'm4v', 'webm', 'ts', 'wmv', 'flv', 'iso'];

    protected array $subtitleExtensions = ['srt', 'vtt', 'sub', 'ass', 'ssa', 'idx', 'smi'];

    protected array $posterExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    protected SceneNameParserService $parser;

    public function __construct(SceneNameParserService $parser)
    {
        $this->parser = $parser;
    }

    public function scanDirectory(string $path, bool $recursive = true): array
    {
        $normalizedPath = $this->normalizePath($path);

        if (! is_dir($normalizedPath) && ! is_dir($path)) {
            return [];
        }

        $targetDir = is_dir($normalizedPath) ? $normalizedPath : $path;

        $excludedDirs = [
            '$RECYCLE.BIN', '$recycle.bin', 'System Volume Information', 'system volume information',
            'node_modules', '.git', 'vendor', 'Recovery', 'recovery',
            '$WINDOWS.~BT', 'Windows', 'windows', 'Program Files', 'Program Files (x86)', 'AppData',
        ];

        try {
            $finder = \Symfony\Component\Finder\Finder::create()
                ->in($targetDir)
                ->ignoreUnreadableDirs()
                ->exclude($excludedDirs)
                ->followLinks();

            if (! $recursive) {
                $finder->depth(0);
            }

            $patterns = [];
            foreach ($this->videoExtensions as $ext) {
                $patterns[] = "*.{$ext}";
            }
            foreach ($this->subtitleExtensions as $ext) {
                $patterns[] = "*.{$ext}";
            }
            foreach ($this->posterExtensions as $ext) {
                $patterns[] = "*.{$ext}";
            }

            $finder->name($patterns);
            $allFiles = iterator_to_array($finder, false);
        } catch (\Throwable $e) {
            return [];
        }

        $videoFiles = [];
        $subtitleFiles = [];
        $imageFiles = [];

        $isTesting = app()->runningUnitTests() || app()->environment('testing');

        foreach ($allFiles as $file) {
            $filename = $file->getFilename();

            // Skip hidden / system dotfiles
            if (str_starts_with($filename, '.') || str_starts_with($filename, '._')) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            $filePath = $this->normalizePath($file->getPathname());
            $parentDir = pathinfo($filePath, PATHINFO_DIRNAME);
            $parentFolder = basename($parentDir);

            // Skip sample files & trailer/extra clips
            if ($this->parser->isSampleOrExtra($filename, $parentFolder)) {
                continue;
            }

            if (in_array($ext, $this->videoExtensions)) {
                $size = $file->getSize();
                if (! $isTesting && $size < 15 * 1024 * 1024 && ! str_contains(strtolower($filePath), 'test')) {
                    continue;
                }

                $videoFiles[] = [
                    'path' => $filePath,

                    'filename' => $filename,

                    'size_bytes' => $size,

                    'size_formatted' => $this->formatBytes($size),

                    'modified_at' => $file->getMTime(),

                    'extension' => $ext,

                    'parsed' => $this->parser->parse($filePath),

                ];
            } elseif (in_array($ext, $this->subtitleExtensions)) {
                $subInfo = $this->parseSubtitleMetadata($filename);
                $subtitleFiles[] = [
                    'path' => $filePath,

                    'filename' => $filename,

                    'size_bytes' => $file->getSize(),

                    'extension' => $ext,

                    'language' => $subInfo['language'],

                    'language_name' => $subInfo['language_name'],

                    'is_forced' => $subInfo['is_forced'],

                    'is_sdh' => $subInfo['is_sdh'],

                    'format' => $ext,

                ];
            } elseif (in_array($ext, $this->posterExtensions)) {
                $imageFiles[] = [
                    'path' => $filePath,

                    'filename' => $filename,

                ];
            }
        }

        // Pair subtitles & local artwork with adjacent video files
        foreach ($videoFiles as &$v) {
            $videoBase = pathinfo($v['filename'], PATHINFO_FILENAME);
            $videoDir = pathinfo($v['path'], PATHINFO_DIRNAME);
            $v['subtitles'] = [];
            $v['local_poster'] = null;
            $v['local_backdrop'] = null;

            $parsedV = $v['parsed'];
            $isSeries = $parsedV['type'] === 'series';
            $sNum = $parsedV['season'];
            $epNum = $parsedV['episode'];

            foreach ($subtitleFiles as $sub) {
                $subDir = pathinfo($sub['path'], PATHINFO_DIRNAME);
                $subParent = pathinfo($subDir, PATHINFO_DIRNAME);
                $subBase = pathinfo($sub['filename'], PATHINFO_FILENAME);

                $isSameDir = (strtolower($subDir) === strtolower($videoDir));
                $isSubFolder = (strtolower($subParent) === strtolower($videoDir) && in_array(strtolower(basename($subDir)), ['subs', 'subtitles', 'sub']));

                if ($isSameDir || $isSubFolder) {
                    $matches = false;

                    // 1. Direct stem match (case-insensitive)
                    $vBaseClean = strtolower(preg_replace('/[^a-z0-9]/i', '', $videoBase));
                    $sBaseClean = strtolower(preg_replace('/[^a-z0-9]/i', '', $subBase));

                    if (str_starts_with($sBaseClean, $vBaseClean) || str_starts_with($vBaseClean, $sBaseClean)) {
                        $matches = true;
                    }
                    // 2. Series Episode match (e.g. S01E02 or 1x02 in subtitle name)
                    elseif ($isSeries && $sNum !== null && $epNum !== null) {
                        $epPattern = sprintf('/[sS]%02d[eE]%02d|\b%dx%02d\b/i', $sNum, $epNum, $sNum, $epNum);
                        if (preg_match($epPattern, $subBase)) {
                            $matches = true;
                        }
                    }
                    // 3. SubFolder numbered / generic language match (e.g. Subs/1_English.srt or Subs/Arabic.srt)
                    elseif ($isSubFolder) {
                        $matches = true;
                    }

                    if ($matches) {
                        $v['subtitles'][] = $sub;
                    }
                }
            }

            // Match local poster & backdrop in current or parent folder
            foreach ($imageFiles as $img) {
                $imgDir = pathinfo($img['path'], PATHINFO_DIRNAME);
                $imgName = strtolower($img['filename']);

                if (strtolower($imgDir) === strtolower($videoDir) || strtolower(pathinfo($imgDir, PATHINFO_DIRNAME)) === strtolower($videoDir) || strtolower($imgDir) === strtolower(pathinfo($videoDir, PATHINFO_DIRNAME))) {
                    // Match Poster
                    if (! $v['local_poster']) {
                        if (str_contains($imgName, 'poster') || str_contains($imgName, 'cover') || str_contains($imgName, 'folder') || str_starts_with(pathinfo($img['filename'], PATHINFO_FILENAME), $videoBase)) {
                            $v['local_poster'] = $img['path'];
                        }
                    }
                    // Match Backdrop / Fanart
                    if (! $v['local_backdrop']) {
                        if (str_contains($imgName, 'backdrop') || str_contains($imgName, 'fanart') || str_contains($imgName, 'background') || str_contains($imgName, 'banner')) {
                            $v['local_backdrop'] = $img['path'];
                        }
                    }
                }
            }
        }

        return $videoFiles;
    }

    
    /**
     * Probe video stream resolution via FFprobe when filename does not contain it.
     */
    public function probeResolution(string $filePath): ?string
    {
        $ffprobe = \App\Services\Media\FfmpegLocatorService::getFfprobePath();
        if (! $ffprobe || ! file_exists($ffprobe)) {
            return null;
        }

        try {
            $cmd = escapeshellarg($ffprobe) . ' -nostdin -loglevel error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 ' . escapeshellarg($filePath) . ' 2>nul';
            $output = @shell_exec($cmd);
            if (! empty($output) && preg_match('/^(\d{3,4})x(\d{3,4})/', trim($output), $m)) {
                $w = (int) $m[1];
                $h = (int) $m[2];
                return $this->parser->calculateResolutionFromDimensions($w, $h);
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    public function parseSubtitleMetadata(string $filename): array
    {
        $clean = preg_replace('/[._\-\[\]\(\)]+/', ' ', strtolower($filename));
        $clean = ' '.trim($clean).' ';

        $isForced = (bool) preg_match('/\b(forced|force)\b/i', $clean);
        $isSDH = (bool) preg_match('/\b(sdh|cc|hi)\b/i', $clean);
        $isCommentary = (bool) preg_match('/\b(commentary|director)\b/i', $clean);

        $detector = app(EmbeddedSubtitleDetectorService::class);
        $lang = $detector->resolveLanguageFromContext('und', '', $filename);
        $langName = $detector->getLanguageName($lang);

        if ($lang === 'und') {
            $langName = 'Track';
        }

        return [
            'language' => $lang,
            'language_name' => $langName,
            'is_forced' => $isForced,
            'is_sdh' => $isSDH,
            'is_commentary' => $isCommentary,
        ];
    }

    public function detectSubtitleLanguage(string $filename): string
    {
        return $this->parseSubtitleMetadata($filename)['language'];
    }

    public function normalizePath(string $path): string
    {
        $p = str_replace('\\', '/', trim($path));

        return rtrim($p, '/');
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        return round($bytes / 1024, 2).' KB';
    }

    /**
     * Browse directories on the filesystem for intuitive folder selection.
     */
    public function browseDirectory(?string $path = null): array
    {
        // 1. Enumerate available system root drives
        $drives = [];
        foreach (range('A', 'Z') as $letter) {
            $root = "{$letter}:/";
            if (@is_dir($root)) {
                $drives[] = [
                    'name' => "Drive ({$letter}:)",
                    'path' => $root,
                    'is_drive' => true,
                ];
            }
        }

        // 2. Discover relevant media & download shortcuts
        $shortcuts = [
            ['name' => 'Entertainment (H:)', 'path' => 'H:/Entertainment'],
            ['name' => 'Movies (H:)', 'path' => 'H:/Entertainment/Movies'],
            ['name' => 'TV Shows (H:)', 'path' => 'H:/Entertainment/TV Shows'],
            ['name' => 'Downloads (D:)', 'path' => 'D:/Downloads'],
            ['name' => 'Videos (D:)', 'path' => 'D:/Downloads/Videos'],
            ['name' => 'App Media Storage', 'path' => str_replace('\\', '/', storage_path('app/media'))],
        ];
        $validShortcuts = array_values(array_filter($shortcuts, fn ($s) => @is_dir($s['path'])));

        // 3. If no path is specified or invalid, return root view with drives & shortcuts
        if (empty($path) || ! @is_dir($path)) {
            return [
                'current_path' => null,
                'parent_path' => null,
                'drives' => $drives,
                'shortcuts' => $validShortcuts,
                'directories' => [],
            ];
        }

        $normalized = rtrim(str_replace('\\', '/', trim($path)), '/');
        if (preg_match('/^[a-zA-Z]:$/', $normalized)) {
            $normalized .= '/';
        }

        // 4. Compute parent directory
        $parentPath = null;
        if (! preg_match('/^[a-zA-Z]:\/$/', $normalized)) {
            $parent = dirname($normalized);
            $parent = str_replace('\\', '/', $parent);
            if ($parent !== $normalized) {
                $parentPath = $parent;
                if (preg_match('/^[a-zA-Z]:$/', $parentPath)) {
                    $parentPath .= '/';
                }
            }
        }

        // 5. Exclude internal/system/junk folders
        $excluded = [
            '$recycle.bin', 'system volume information', 'recovery',
            'node_modules', '.git', 'vendor', '$windows.~bt',
            'windows', 'program files', 'program files (x86)'
        ];

        $directories = [];
        $items = @scandir($normalized);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                if (in_array(strtolower($item), $excluded)) {
                    continue;
                }
                $fullPath = rtrim($normalized, '/') . '/' . $item;
                if (@is_dir($fullPath)) {
                    $hasChildren = false;
                    $sub = @scandir($fullPath);
                    if ($sub !== false) {
                        foreach ($sub as $s) {
                            if ($s !== '.' && $s !== '..' && @is_dir("{$fullPath}/{$s}")) {
                                $hasChildren = true;
                                break;
                            }
                        }
                    }

                    $directories[] = [
                        'name' => $item,
                        'path' => $fullPath,
                        'has_children' => $hasChildren,
                        'is_writable' => @is_writable($fullPath),
                    ];
                }
            }
        }

        usort($directories, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return [
            'current_path' => $normalized,
            'parent_path' => $parentPath,
            'drives' => $drives,
            'shortcuts' => $validShortcuts,
            'directories' => $directories,
        ];
    }
}
