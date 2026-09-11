<?php

namespace App\Services\Subtitles;

use App\Services\Media\FfmpegLocatorService;
use Illuminate\Support\Facades\File;

class EmbeddedSubtitleDetectorService
{
    public static array $languageMap = [
        'ar' => ['name' => 'Arabic', 'ar' => 'العربية', 'flag' => '🇸🇦'],
        'en' => ['name' => 'English', 'ar' => 'الإنجليزية', 'flag' => '🇬🇧'],
        'es' => ['name' => 'Spanish', 'ar' => 'الإسبانية', 'flag' => '🇪🇸'],
        'fr' => ['name' => 'French', 'ar' => 'الفرنسية', 'flag' => '🇫🇷'],
        'de' => ['name' => 'German', 'ar' => 'الألمانية', 'flag' => '🇩🇪'],
        'it' => ['name' => 'Italian', 'ar' => 'الإيطالية', 'flag' => '🇮🇹'],
        'pt' => ['name' => 'Portuguese', 'ar' => 'البرتغالية', 'flag' => '🇵🇹'],
        'ru' => ['name' => 'Russian', 'ar' => 'الروسية', 'flag' => '🇷🇺'],
        'tr' => ['name' => 'Turkish', 'ar' => 'التركية', 'flag' => '🇹🇷'],
        'fa' => ['name' => 'Persian', 'ar' => 'الفارسية', 'flag' => '🇮🇷'],
        'ja' => ['name' => 'Japanese', 'ar' => 'اليابانية', 'flag' => '🇯🇵'],
        'ko' => ['name' => 'Korean', 'ar' => 'الكورية', 'flag' => '🇰🇷'],
        'zh' => ['name' => 'Chinese', 'ar' => 'الصينية', 'flag' => '🇨🇳'],
        'hi' => ['name' => 'Hindi', 'ar' => 'الهندية', 'flag' => '🇮🇳'],
        'hr' => ['name' => 'Croatian', 'ar' => 'الكرواتية', 'flag' => '🇭🇷'],
        'cs' => ['name' => 'Czech', 'ar' => 'التشيكية', 'flag' => '🇨🇿'],
        'da' => ['name' => 'Danish', 'ar' => 'الدانماركية', 'flag' => '🇩🇰'],
        'nl' => ['name' => 'Dutch', 'ar' => 'الهولندية', 'flag' => '🇳🇱'],
        'tl' => ['name' => 'Filipino', 'ar' => 'الفلبينية', 'flag' => '🇵🇭'],
        'fi' => ['name' => 'Finnish', 'ar' => 'الفنلندية', 'flag' => '🇫🇮'],
        'el' => ['name' => 'Greek', 'ar' => 'اليونانية', 'flag' => '🇬🇷'],
        'he' => ['name' => 'Hebrew', 'ar' => 'العبرية', 'flag' => '🇮🇱'],
        'hu' => ['name' => 'Hungarian', 'ar' => 'المجرية', 'flag' => '🇭🇺'],
        'id' => ['name' => 'Indonesian', 'ar' => 'الإندونيسية', 'flag' => '🇮🇩'],
        'ms' => ['name' => 'Malay', 'ar' => 'الماليزية', 'flag' => '🇲🇾'],
        'no' => ['name' => 'Norwegian', 'ar' => 'النرويجية', 'flag' => '🇳🇴'],
        'pl' => ['name' => 'Polish', 'ar' => 'البولندية', 'flag' => '🇵🇱'],
        'ro' => ['name' => 'Romanian', 'ar' => 'الرومانية', 'flag' => '🇷🇴'],
        'sv' => ['name' => 'Swedish', 'ar' => 'السويدية', 'flag' => '🇸🇪'],
        'th' => ['name' => 'Thai', 'ar' => 'التايلاندية', 'flag' => '🇹🇭'],
        'uk' => ['name' => 'Ukrainian', 'ar' => 'الأوكرانية', 'flag' => '🇺🇦'],
        'vi' => ['name' => 'Vietnamese', 'ar' => 'الفيتنامية', 'flag' => '🇻🇳'],
        'ur' => ['name' => 'Urdu', 'ar' => 'الأردية', 'flag' => '🇵🇰'],
        'bn' => ['name' => 'Bengali', 'ar' => 'البنغالية', 'flag' => '🇧🇩'],
        'bg' => ['name' => 'Bulgarian', 'ar' => 'البلغارية', 'flag' => '🇧🇬'],
        'ca' => ['name' => 'Catalan', 'ar' => 'الكتالونية', 'flag' => '🇪🇸'],
        'et' => ['name' => 'Estonian', 'ar' => 'الإستونية', 'flag' => '🇪🇪'],
        'is' => ['name' => 'Icelandic', 'ar' => 'الأيسلندية', 'flag' => '🇮🇸'],
        'lt' => ['name' => 'Lithuanian', 'ar' => 'الليتوانية', 'flag' => '🇱🇹'],
        'lv' => ['name' => 'Latvian', 'ar' => 'اللاتفية', 'flag' => '🇱🇻'],
        'mk' => ['name' => 'Macedonian', 'ar' => 'المقدونية', 'flag' => '🇲🇰'],
        'sk' => ['name' => 'Slovak', 'ar' => 'السلوفاكية', 'flag' => '🇸🇰'],
        'sl' => ['name' => 'Slovenian', 'ar' => 'السلوفينية', 'flag' => '🇸🇮'],
        'sr' => ['name' => 'Serbian', 'ar' => 'الصربية', 'flag' => '🇷🇸'],
        'az' => ['name' => 'Azerbaijani', 'ar' => 'الأذربيجانية', 'flag' => '🇦🇿'],
        'sq' => ['name' => 'Albanian', 'ar' => 'الألبانية', 'flag' => '🇦🇱'],
        'bs' => ['name' => 'Bosnian', 'ar' => 'البوسنية', 'flag' => '🇧🇦'],
        'eu' => ['name' => 'Basque', 'ar' => 'الباسكية', 'flag' => '🇪🇸'],
        'gl' => ['name' => 'Galician', 'ar' => 'الجاليكية', 'flag' => '🇪🇸'],
        'ka' => ['name' => 'Georgian', 'ar' => 'الجورجية', 'flag' => '🇬🇪'],
        'mn' => ['name' => 'Mongolian', 'ar' => 'المنغولية', 'flag' => '🇲🇳'],
        'kk' => ['name' => 'Kazakh', 'ar' => 'الكازاخستانية', 'flag' => '🇰🇿'],
        'uz' => ['name' => 'Uzbek', 'ar' => 'الأوزبكية', 'flag' => '🇺🇿'],
        'und' => ['name' => 'Undetermined', 'ar' => 'غير محدد', 'flag' => '🌐'],
    ];

    public static array $langDefinitions = [
        'ar' => ['ar', 'ara', 'arabic', 'arabi', '3arabi', 'عربي', 'العربية', 'مترجم'],
        'zh' => ['zh', 'chi', 'zho', 'chinese', 'chs', 'cht', 'mandarin', '中文', '简体', '繁體'],
        'es' => ['es', 'spa', 'spanish', 'latino', 'castellano', 'español', 'espanol'],
        'fr' => ['fr', 'fre', 'fra', 'french', 'français', 'francais'],
        'de' => ['de', 'ger', 'deu', 'german', 'deutsch'],
        'it' => ['it', 'ita', 'italian', 'italiano'],
        'pt' => ['pt', 'por', 'portuguese', 'português', 'portugues', 'brazil', 'brasileiro', 'portugal'],
        'ru' => ['ru', 'rus', 'russian', 'русский'],
        'tr' => ['tr', 'tur', 'turkish', 'türkçe', 'turkce'],
        'fa' => ['fa', 'fas', 'per', 'persian', 'farsi', 'فارسی'],
        'ja' => ['ja', 'jpn', 'japanese', '日本語'],
        'ko' => ['ko', 'kor', 'korean', '한국어'],
        'hi' => ['hi', 'hin', 'hindi', 'हिन्दी'],
        'hr' => ['hr', 'hrv', 'croatian', 'hrvatski'],
        'cs' => ['cs', 'cze', 'ces', 'czech', 'cesky', 'česky'],
        'da' => ['da', 'dan', 'danish', 'dansk'],
        'nl' => ['nl', 'dut', 'nld', 'dutch', 'nederlands'],
        'tl' => ['tl', 'fil', 'tgl', 'filipino', 'tagalog'],
        'fi' => ['fi', 'fin', 'finnish', 'suomi'],
        'el' => ['el', 'gre', 'ell', 'greek', 'ελληνικά'],
        'hu' => ['hu', 'hun', 'hungarian', 'magyar'],
        'id' => ['id', 'ind', 'indonesian', 'bahasa'],
        'ms' => ['ms', 'may', 'msa', 'malay', 'melayu'],
        'no' => ['no', 'nob', 'nno', 'nor', 'norwegian', 'norsk'],
        'pl' => ['pl', 'pol', 'polish', 'polski'],
        'ro' => ['ro', 'rum', 'ron', 'romanian', 'română', 'romana'],
        'sv' => ['sv', 'swe', 'swedish', 'svenska'],
        'th' => ['th', 'tha', 'thai', 'ไทย'],
        'uk' => ['uk', 'ukr', 'ukrainian', 'українська'],
        'vi' => ['vi', 'vie', 'vietnamese', 'tiếng việt', 'tieng viet'],
        'he' => ['he', 'heb', 'hebrew', 'עבריت'],
        'en' => ['en', 'eng', 'english'],
        'bg' => ['bg', 'bul', 'bulgarian', 'български'],
        'ca' => ['ca', 'cat', 'catalan', 'català'],
        'et' => ['et', 'est', 'estonian', 'eesti', 'ekk'],
        'is' => ['is', 'ice', 'isl', 'icelandic', 'íslenska'],
        'lt' => ['lt', 'lit', 'lithuanian', 'lietuvių', 'lietuviu'],
        'lv' => ['lv', 'lav', 'latvian', 'latviešu', 'lvs'],
        'mk' => ['mk', 'mac', 'mkd', 'macedonian', 'македонски'],
        'sk' => ['sk', 'slo', 'slk', 'slovak', 'slovenčina', 'slovencina'],
        'sl' => ['sl', 'slv', 'slovenian', 'slovenščina', 'slovenscina'],
        'sr' => ['sr', 'srp', 'scc', 'serbian', 'srpski'],
        'az' => ['az', 'aze', 'azerbaijani', 'azəri', 'azeri'],
        'sq' => ['sq', 'sqi', 'alb', 'albanian', 'shqip'],
        'bs' => ['bs', 'bos', 'bosnian', 'bosanski'],
        'eu' => ['eu', 'eus', 'baq', 'basque', 'euskara'],
        'gl' => ['gl', 'glg', 'galician', 'galego'],
        'ka' => ['ka', 'kat', 'geo', 'georgian', 'ქართული'],
        'mn' => ['mn', 'mon', 'khk', 'mongolian'],
        'kk' => ['kk', 'kaz', 'kazakh'],
        'uz' => ['uz', 'uzb', 'uzbek'],
    ];

    public function detectEmbeddedSubtitles(string $videoPath): array
    {
        if (! File::exists($videoPath) || filesize($videoPath) < 1024) {
            return [];
        }

        $ext = strtolower(pathinfo($videoPath, PATHINFO_EXTENSION));

        // 1. FFprobe Detection
        $ffprobeTracks = $this->detectViaFfprobe($videoPath);
        if (! empty($ffprobeTracks)) {
            return $ffprobeTracks;
        }

        // 2. Pure PHP EBML Parser Fallback
        if (in_array($ext, ['mkv', 'webm', 'mka'])) {
            return $this->parseMatroskaSubtitles($videoPath);
        } elseif (in_array($ext, ['mp4', 'm4v', 'mov'])) {
            return $this->parseMp4Subtitles($videoPath);
        }

        return [];
    }

    public function extractToWebVtt(string $videoPath, int $streamIndex, string $format = 'srt'): string
    {
        $cacheDir = storage_path('app/subtitles/cache');
        if (! File::isDirectory($cacheDir)) {
            File::makeDirectory($cacheDir, 0755, true, true);
        }

        $cacheKey = md5($videoPath.'_'.$streamIndex.'_'.filemtime($videoPath));
        $cacheFile = "{$cacheDir}/{$cacheKey}.vtt";

        if (File::exists($cacheFile) && filesize($cacheFile) > 20) {
            return File::get($cacheFile);
        }

        $vtt = $this->extractViaFfmpeg($videoPath, $streamIndex);

        if (empty($vtt) || ! str_starts_with(trim($vtt), 'WEBVTT')) {
            $vtt = $this->extractMatroskaBlocksToVtt($videoPath, $streamIndex);
        }

        if (empty($vtt)) {
            $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:05.000\n[Embedded Subtitle Track Active]\n\n";
        }

        $vtt = app(SubtitleLanguageDetectorService::class)->sanitizeToUtf8($vtt);

        File::put($cacheFile, $vtt);

        return $vtt;
    }

    protected function detectViaFfprobe(string $videoPath): array
    {
        $ffprobeBin = FfmpegLocatorService::getFfprobePath();
        if (! $ffprobeBin) {
            return [];
        }

        $escaped = escapeshellarg($videoPath);
        $cmd = escapeshellarg($ffprobeBin)." -v quiet -print_format json -show_streams -select_streams s {$escaped}";
        $output = @shell_exec($cmd);

        if (! $output) {
            return [];
        }

        $data = @json_decode($output, true);
        if (empty($data['streams'])) {
            return [];
        }

        $tracks = [];
        foreach ($data['streams'] as $idx => $stream) {
            $lang = $stream['tags']['language'] ?? 'und';
            $title = $stream['tags']['title'] ?? ($stream['tags']['handler_name'] ?? '');
            $codec = $stream['codec_name'] ?? 'srt';

            $resolvedLang = $this->resolveLanguageFromContext($lang, $title, $videoPath, $idx);
            $langName = $this->getLanguageName($resolvedLang);

            $label = $this->buildHumanTrackLabel($resolvedLang, $langName, $title, $idx, $codec);

            $tracks[] = [
                'stream_index' => $idx,
                'track_number' => $stream['index'] ?? ($idx + 1),
                'language' => $resolvedLang,
                'language_name' => $label,
                'codec' => $codec,
                'is_embedded' => true,
            ];
        }

        return $tracks;
    }

    protected function extractViaFfmpeg(string $videoPath, int $streamIndex): ?string
    {
        $ffmpegBin = FfmpegLocatorService::getFfmpegPath();
        if (! $ffmpegBin) {
            return null;
        }

        $escaped = escapeshellarg($videoPath);
        $cmd = escapeshellarg($ffmpegBin)." -nostats -loglevel error -hide_banner -i {$escaped} -map 0:s:{$streamIndex} -f webvtt -";
        $vtt = @shell_exec($cmd);

        return $vtt ?: null;
    }

    protected function parseMatroskaSubtitles(string $videoPath): array
    {
        $fp = @fopen($videoPath, 'rb');
        if (! $fp) {
            return [];
        }

        $headerChunk = fread($fp, 1024 * 1024 * 3);
        fclose($fp);

        if (! $headerChunk || strlen($headerChunk) < 50) {
            return [];
        }

        $tracks = [];
        $streamIndex = 0;

        $codecPatterns = [
            'S_TEXT/UTF8' => 'srt',
            'S_TEXT/ASS' => 'ass',
            'S_TEXT/SSA' => 'ssa',
            'S_HDMV/PGS' => 'pgs',
            'S_VOBSUB' => 'sub',
        ];

        foreach ($codecPatterns as $codecTag => $format) {
            $offset = 0;
            while (($pos = strpos($headerChunk, $codecTag, $offset)) !== false) {
                $context = substr($headerChunk, max(0, $pos - 150), 300);

                $lang = 'und';
                if (preg_match('/\b([a-z]{2,3})\b/i', $context, $lMatch)) {
                    $lang = strtolower($lMatch[1]);
                }

                $resolvedLang = $this->resolveLanguageFromContext($lang, $context, $videoPath, $streamIndex);
                $langName = $this->getLanguageName($resolvedLang);

                $label = $this->buildHumanTrackLabel($resolvedLang, $langName, '', $streamIndex, $format);

                $tracks[] = [
                    'stream_index' => $streamIndex++,
                    'language' => $resolvedLang,
                    'language_name' => $label,
                    'codec' => $format,
                    'is_embedded' => true,
                ];

                $offset = $pos + strlen($codecTag);
            }
        }

        return $tracks;
    }

    protected function parseMp4Subtitles(string $videoPath): array
    {
        $fp = @fopen($videoPath, 'rb');
        if (! $fp) {
            return [];
        }

        $headerChunk = fread($fp, 1024 * 1024 * 2);
        fclose($fp);

        if (! $headerChunk) {
            return [];
        }

        $tracks = [];
        $streamIndex = 0;

        if (str_contains($headerChunk, 'sbtl') || str_contains($headerChunk, 'subt') || str_contains($headerChunk, 'tx3g')) {
            $lang = 'und';
            $resolvedLang = $this->resolveLanguageFromContext($lang, '', $videoPath, $streamIndex);
            $langName = $this->getLanguageName($resolvedLang);

            $tracks[] = [
                'stream_index' => $streamIndex,
                'language' => $resolvedLang,
                'language_name' => "{$langName} (Embedded MP4)",
                'codec' => 'tx3g',
                'is_embedded' => true,
            ];
        }

        return $tracks;
    }

    protected function extractMatroskaBlocksToVtt(string $videoPath, int $streamIndex): string
    {
        $vtt = "WEBVTT\n\n";
        $fp = @fopen($videoPath, 'rb');
        if (! $fp) {
            return $vtt;
        }

        $content = fread($fp, 1024 * 1024 * 4);
        fclose($fp);

        if (preg_match_all('/(\d{2}:\d{2}:\d{2}[,\.]\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2}[,\.]\d{3})\r?\n(.*?)(?=\r?\n\r?\n|\Z)/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $cue) {
                $start = str_replace(',', '.', $cue[1]);
                $end = str_replace(',', '.', $cue[2]);
                $text = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $cue[3]));
                if (! empty($text)) {
                    $vtt .= "{$start} --> {$end}\n{$text}\n\n";
                }
            }
        }

        return $vtt;
    }

    /**
     * Inspect file contents on disk to accurately classify language (Arabic, English, etc.)
     */
    public function detectLanguageFromFileContent(string $filePath): string
    {
        if (! file_exists($filePath) || filesize($filePath) === 0) {
            return 'und';
        }

        $filename = strtolower(basename($filePath));

        // 1. Explicit extension check takes highest precedence (e.g., .en.srt, .ar.srt, .en-US.srt, .en.forced.srt)
        $baseNameWithoutExt = preg_replace('/\.(?:srt|vtt|ass|ssa|sub)$/i', '', $filename);
        $tokens = preg_split('/[\._\-\s]+/', $baseNameWithoutExt);
        foreach (array_reverse($tokens) as $tok) {
            $tok = trim($tok);
            if (in_array($tok, ['forced', 'sdh', 'cc', 'default', 'sub', 'subs'], true)) {
                continue;
            }
            if (str_contains($tok, '-')) {
                $subtoks = explode('-', $tok);
                $tok = $subtoks[0];
            }
            foreach (self::$langDefinitions as $code => $names) {
                if ($tok === $code || in_array($tok, $names, true)) {
                    return $code;
                }
            }
        }

        // 2. Statistical content analysis for untagged files (.srt, .vtt)
        $raw = @file_get_contents($filePath, false, null, 0, 8192);
        if (! $raw) {
            return 'und';
        }

        // Detect and normalize encoding
        try {
            $raw = app(SubtitleLanguageDetectorService::class)->sanitizeToUtf8($raw);
        } catch (\Throwable $e) {
        }

        // Clean subtitle headers, timestamps, and formatting markup
        $clean = preg_replace('/\d{2}:\d{2}:\d{2}[,\.]\d{3}\s*-->\s*\d{2}:\d{2}:\d{2}[,\.]\d{3}/', ' ', $raw);
        $clean = preg_replace('/<[^>]+>|\{[^\}]+\}/', ' ', $clean);
        $clean = preg_replace('/[0-9]+/', ' ', $clean);

        // Count Arabic vs Latin letters
        preg_match_all('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $clean, $arMatches);
        $arabicCount = count($arMatches[0] ?? []);

        preg_match_all('/[a-zA-Z]/', $clean, $latinMatches);
        $latinCount = count($latinMatches[0] ?? []);

        if ($arabicCount > 25 && $arabicCount > ($latinCount * 0.35)) {
            return 'ar';
        }

        if ($latinCount > 40) {
            return 'en';
        }

        return 'und';
    }

    public function resolveLanguageFromContext(string $rawLang, string $title = '', string $filePath = '', int $trackIndex = 0): string
    {
        $filename = ! empty($filePath) ? strtolower(basename($filePath)) : '';

        // 1. Explicit filename extension pattern takes priority
        if (! empty($filename)) {
            $baseNameWithoutExt = preg_replace('/\.(?:srt|vtt|ass|ssa|sub)$/i', '', $filename);
            $tokens = preg_split('/[\._\-\s]+/', $baseNameWithoutExt);
            foreach (array_reverse($tokens) as $tok) {
                $tok = trim($tok);
                if (in_array($tok, ['forced', 'sdh', 'cc', 'default', 'sub', 'subs'], true)) {
                    continue;
                }
                if (str_contains($tok, '-')) {
                    $subtoks = explode('-', $tok);
                    $tok = $subtoks[0];
                }
                foreach (self::$langDefinitions as $code => $names) {
                    if ($tok === $code || in_array($tok, $names, true)) {
                        return $code;
                    }
                }
            }
        }

        // 2. Direct language code matching from container track metadata
        $lang = strtolower(trim($rawLang));
        if (! empty($lang) && $lang !== 'und') {
            foreach (self::$langDefinitions as $code => $names) {
                if ($lang === $code || in_array($lang, $names, true)) {
                    return $code;
                }
            }
        }

        // 3. Inspect file contents on disk if available (ONLY for subtitle text files, NEVER video files)
        if (! empty($filePath) && file_exists($filePath)) {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if (in_array($ext, ['srt', 'vtt', 'ass', 'ssa', 'sub'], true)) {
                $contentLang = $this->detectLanguageFromFileContent($filePath);
                if ($contentLang !== 'und') {
                    return $contentLang;
                }
            }
        }

        $haystack = strtolower("{$title} {$filename}");
        $haystack = preg_replace('/[._\-\[\]\(\)]+/', ' ', $haystack);

        foreach (self::$langDefinitions as $code => $names) {
            foreach ($names as $name) {
                if (preg_match('/\b'.preg_quote($name, '/').'\b/i', $haystack)) {
                    return $code;
                }
            }
        }

        return ! empty($rawLang) ? $rawLang : 'und';
    }

    protected function buildHumanTrackLabel(string $langCode, string $langName, string $title, int $idx, string $codec): string
    {
        $codecUpper = strtoupper($codec);

        if ($langCode !== 'und') {
            if ($title && ! str_starts_with(strtolower($title), 'subtitle') && strtolower($title) !== strtolower($langName)) {
                return "{$langName} - {$title}";
            }

            return "{$langName}";
        }

        if ($title && trim($title) !== '') {
            return "{$title} (Track ".($idx + 1).')';
        }

        return 'Subtitle Track '.($idx + 1)." ({$codecUpper})";
    }

    public function normalizeLanguageCode(string $code): string
    {
        $code = strtolower(trim($code));
        foreach (self::$langDefinitions as $canonical => $keywords) {
            if (in_array($code, $keywords, true)) {
                return $canonical;
            }
        }

        return strlen($code) === 2 ? $code : 'und';
    }

    public function getLanguageName(string $code): string
    {
        $normalized = $this->normalizeLanguageCode($code);

        return self::$languageMap[$normalized]['name'] ?? 'Track';
    }

    public function getLanguageArabicName(string $code): string
    {
        $normalized = $this->normalizeLanguageCode($code);

        return self::$languageMap[$normalized]['ar'] ?? 'ترجمة';
    }
}
