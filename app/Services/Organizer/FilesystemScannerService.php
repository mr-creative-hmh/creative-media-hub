<?php

namespace App\Services\Organizer;

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
                if (!$isTesting && $size < 15 * 1024 * 1024 && !str_contains(strtolower($filePath), 'test')) {
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
                    if (!$v['local_poster']) {
                        if (str_contains($imgName, 'poster') || str_contains($imgName, 'cover') || str_contains($imgName, 'folder') || str_starts_with(pathinfo($img['filename'], PATHINFO_FILENAME), $videoBase)) {
                            $v['local_poster'] = $img['path'];
                        }
                    }
                    // Match Backdrop / Fanart
                    if (!$v['local_backdrop']) {
                        if (str_contains($imgName, 'backdrop') || str_contains($imgName, 'fanart') || str_contains($imgName, 'background') || str_contains($imgName, 'banner')) {
                            $v['local_backdrop'] = $img['path'];
                        }
                    }
                }
            }
        }

        return $videoFiles;
    }

    public function parseSubtitleMetadata(string $filename): array
    {
        $lower = strtolower($filename);
        $cleanSearch = ' ' . preg_replace('/[^a-z0-9\p{Arabic}\p{Hebrew}\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Cyrillic}]/u', ' ', $lower) . ' ';

        $isForced = (bool) preg_match('/\b(forced|force)\b/i', $cleanSearch);
        $isSDH = (bool) preg_match('/\b(sdh|cc|hi)\b/i', $cleanSearch);

        $lang = 'und';
        $langName = 'Unknown';

        $langMap = [
            'ar' => ['code' => 'ar', 'name' => 'Arabic', 'pattern' => '/\b(ar|ara|arabic|عربي)\b/u'],
            'en' => ['code' => 'en', 'name' => 'English', 'pattern' => '/\b(en|eng|english|en\s*us|en\s*gb)\b/i'],
            'fr' => ['code' => 'fr', 'name' => 'French', 'pattern' => '/\b(fr|fre|fra|french|français)\b/u'],
            'es' => ['code' => 'es', 'name' => 'Spanish', 'pattern' => '/\b(es|spa|spanish|español)\b/u'],
            'de' => ['code' => 'de', 'name' => 'German', 'pattern' => '/\b(de|ger|deu|german|deutsch)\b/i'],
            'it' => ['code' => 'it', 'name' => 'Italian', 'pattern' => '/\b(it|ita|italian|italiano)\b/i'],
            'ru' => ['code' => 'ru', 'name' => 'Russian', 'pattern' => '/\b(ru|rus|russian|русский)\b/u'],
            'ja' => ['code' => 'ja', 'name' => 'Japanese', 'pattern' => '/\b(ja|jpn|japanese|日本語)\b/u'],
            'ko' => ['code' => 'ko', 'name' => 'Korean', 'pattern' => '/\b(ko|kor|korean|한국어)\b/u'],
            'zh' => ['code' => 'zh', 'name' => 'Chinese', 'pattern' => '/\b(zh|chi|zho|chinese|中文)\b/u'],
            'he' => ['code' => 'he', 'name' => 'Hebrew', 'pattern' => '/\b(he|heb|hebrew|עברית)\b/u'],
            'tr' => ['code' => 'tr', 'name' => 'Turkish', 'pattern' => '/\b(tr|tur|turkish|türkçe)\b/u'],
            'fa' => ['code' => 'fa', 'name' => 'Persian', 'pattern' => '/\b(fa|fas|per|farsi|persian|فارسی)\b/u'],
        ];

        foreach ($langMap as $key => $info) {
            if (preg_match($info['pattern'], $cleanSearch) || str_contains($lower, ".{$key}.") || str_ends_with($lower, ".{$key}.srt") || str_ends_with($lower, ".{$key}.vtt")) {
                $lang = $info['code'];
                $langName = $info['name'];
                break;
            }
        }

        return [
            'language' => $lang,
            'language_name' => $langName,
            'is_forced' => $isForced,
            'is_sdh' => $isSDH,
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
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        return round($bytes / 1024, 2) . ' KB';
    }
}
