<?php

namespace App\Services\Subtitles;

use App\Services\Media\FfmpegLocatorService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class EmbeddedSubtitleDetectorService
{
    protected static array $languageMap = [
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
        'id' => ['name' => 'Indonesian', 'ar' => 'الإندونيسية', 'flag' => '🇮🇩'],
        'nl' => ['name' => 'Dutch', 'ar' => 'الهولندية', 'flag' => '🇳🇱'],
        'pl' => ['name' => 'Polish', 'ar' => 'البولندية', 'flag' => '🇵🇱'],
        'sv' => ['name' => 'Swedish', 'ar' => 'السويدية', 'flag' => '🇸🇪'],
        'da' => ['name' => 'Danish', 'ar' => 'الدانماركية', 'flag' => '🇩🇰'],
        'no' => ['name' => 'Norwegian', 'ar' => 'النرويجية', 'flag' => '🇳🇴'],
        'fi' => ['name' => 'Finnish', 'ar' => 'الفنلندية', 'flag' => '🇫🇮'],
        'el' => ['name' => 'Greek', 'ar' => 'اليونانية', 'flag' => '🇬🇷'],
        'he' => ['name' => 'Hebrew', 'ar' => 'العبرية', 'flag' => '🇮🇱'],
        'vi' => ['name' => 'Vietnamese', 'ar' => 'الفيتنامية', 'flag' => '🇻🇳'],
        'th' => ['name' => 'Thai', 'ar' => 'التايلاندية', 'flag' => '🇹🇭'],
        'ur' => ['name' => 'Urdu', 'ar' => 'الأردية', 'flag' => '🇵🇰'],
        'ro' => ['name' => 'Romanian', 'ar' => 'الرومانية', 'flag' => '🇷🇴'],
        'cs' => ['name' => 'Czech', 'ar' => 'التشيكية', 'flag' => '🇨🇿'],
        'hu' => ['name' => 'Hungarian', 'ar' => 'المجرية', 'flag' => '🇭🇺'],
        'uk' => ['name' => 'Ukrainian', 'ar' => 'الأوكرانية', 'flag' => '🇺🇦'],
        'ms' => ['name' => 'Malay', 'ar' => 'الماليزية', 'flag' => '🇲🇾'],
        'bn' => ['name' => 'Bengali', 'ar' => 'البنغالية', 'flag' => '🇧🇩'],
        'tl' => ['name' => 'Tagalog', 'ar' => 'الفلبينية', 'flag' => '🇵🇭'],
    ];

    public function detectEmbeddedSubtitles(string $videoPath): array
    {
        if (!File::exists($videoPath) || filesize($videoPath) < 1024) {
            return [];
        }

        $ext = strtolower(pathinfo($videoPath, PATHINFO_EXTENSION));

        // 1. FFprobe Detection
        $ffprobeTracks = $this->detectViaFfprobe($videoPath);
        if (!empty($ffprobeTracks)) {
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
        if (!File::isDirectory($cacheDir)) {
            File::makeDirectory($cacheDir, 0755, true, true);
        }

        $cacheKey = md5($videoPath . '_' . $streamIndex . '_' . filemtime($videoPath));
        $cacheFile = "{$cacheDir}/{$cacheKey}.vtt";

        if (File::exists($cacheFile) && filesize($cacheFile) > 20) {
            return File::get($cacheFile);
        }

        $vtt = $this->extractViaFfmpeg($videoPath, $streamIndex);

        if (empty($vtt) || !str_starts_with(trim($vtt), 'WEBVTT')) {
            $vtt = $this->extractMatroskaBlocksToVtt($videoPath, $streamIndex);
        }

        if (empty($vtt)) {
            $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:05.000\n[Embedded Subtitle Track Active]\n\n";
        }

        File::put($cacheFile, $vtt);
        return $vtt;
    }

    protected function detectViaFfprobe(string $videoPath): array
    {
        $ffprobeBin = FfmpegLocatorService::getFfprobePath();
        if (!$ffprobeBin) {
            return [];
        }

        $escaped = escapeshellarg($videoPath);
        $cmd = escapeshellarg($ffprobeBin) . " -v quiet -print_format json -show_streams -select_streams s {$escaped}";
        $output = @shell_exec($cmd);

        if (!$output) return [];

        $data = @json_decode($output, true);
        if (empty($data['streams'])) return [];

        $tracks = [];
        foreach ($data['streams'] as $idx => $stream) {
            $lang = $stream['tags']['language'] ?? 'und';
            $title = $stream['tags']['title'] ?? ($stream['tags']['handler_name'] ?? '');
            $codec = $stream['codec_name'] ?? 'srt';

            // Smart language deduction from title/handler if lang is und
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
        if (!$ffmpegBin) {
            return null;
        }

        $escaped = escapeshellarg($videoPath);
        $cmd = escapeshellarg($ffmpegBin) . " -nostats -loglevel error -hide_banner -i {$escaped} -map 0:s:{$streamIndex} -f webvtt -";
        $vtt = @shell_exec($cmd);

        return $vtt ?: null;
    }

    protected function parseMatroskaSubtitles(string $videoPath): array
    {
        $fp = @fopen($videoPath, 'rb');
        if (!$fp) return [];

        $headerChunk = fread($fp, 1024 * 1024 * 3);
        fclose($fp);

        if (!$headerChunk || strlen($headerChunk) < 50) {
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
                if (preg_match('/\b(ara|eng|fre|fra|spa|ger|deu|ita|jpn|kor|chi|zho|rus|por|tur|fas|hin|ind|nld|pol|swe|dan|nor|fin|ell|heb|vie|tha|urd|ron|ces|hun|ukr|und)\b/i', $context, $lMatch)) {
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
        if (!$fp) return [];

        $headerChunk = fread($fp, 1024 * 1024 * 2);
        fclose($fp);

        if (!$headerChunk) return [];

        $tracks = [];
        $streamIndex = 0;

        if (str_contains($headerChunk, 'sbtl') || str_contains($headerChunk, 'subt') || str_contains($headerChunk, 'tx3g')) {
            $lang = 'und';
            if (preg_match('/\b(ara|eng|fre|spa|ger|ita|por|rus|tur|jpn|kor|chi|und)\b/i', $headerChunk, $lMatch)) {
                $lang = strtolower($lMatch[1]);
            }

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
        if (!$fp) return $vtt;

        $content = fread($fp, 1024 * 1024 * 4);
        fclose($fp);

        if (preg_match_all('/(\d{2}:\d{2}:\d{2}[,\.]\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2}[,\.]\d{3})\r?\n(.*?)(?=\r?\n\r?\n|\Z)/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $cue) {
                $start = str_replace(',', '.', $cue[1]);
                $end = str_replace(',', '.', $cue[2]);
                $text = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $cue[3]));
                if (!empty($text)) {
                    $vtt .= "{$start} --> {$end}\n{$text}\n\n";
                }
            }
        }

        return $vtt;
    }

    public function resolveLanguageFromContext(string $rawLang, string $title = '', string $filePath = '', int $trackIndex = 0): string
    {
        $norm = $this->normalizeLanguageCode($rawLang);
        if ($norm !== 'und') {
            return $norm;
        }

        $haystack = strtolower("{$title} " . basename($filePath));

        // Arabic patterns
        if (preg_match('/\b(ar|ara|arabic|arabi|3arabi)\b/i', $haystack) || str_contains($haystack, 'عربي') || str_contains($haystack, 'مترجم')) {
            return 'ar';
        }
        // English patterns
        if (preg_match('/\b(en|eng|english|sdh|cc|full)\b/i', $haystack)) {
            return 'en';
        }
        // Spanish patterns
        if (preg_match('/\b(es|spa|spanish|latino|castellano|español)\b/i', $haystack)) {
            return 'es';
        }
        // French patterns
        if (preg_match('/\b(fr|fre|fra|french|français)\b/i', $haystack)) {
            return 'fr';
        }
        // German patterns
        if (preg_match('/\b(de|ger|deu|german|deutsch)\b/i', $haystack)) {
            return 'de';
        }
        // Italian patterns
        if (preg_match('/\b(it|ita|italian|italiano)\b/i', $haystack)) {
            return 'it';
        }
        // Portuguese patterns
        if (preg_match('/\b(pt|por|portuguese|português|brazil|brasileiro)\b/i', $haystack)) {
            return 'pt';
        }
        // Russian patterns
        if (preg_match('/\b(ru|rus|russian|русский)\b/i', $haystack)) {
            return 'ru';
        }
        // Turkish patterns
        if (preg_match('/\b(tr|tur|turkish|türkçe)\b/i', $haystack)) {
            return 'tr';
        }
        // Persian patterns
        if (preg_match('/\b(fa|fas|per|persian|farsi)\b/i', $haystack) || str_contains($haystack, 'فارسی')) {
            return 'fa';
        }
        // Japanese patterns
        if (preg_match('/\b(ja|jpn|japanese)\b/i', $haystack)) {
            return 'ja';
        }
        // Korean patterns
        if (preg_match('/\b(ko|kor|korean)\b/i', $haystack)) {
            return 'ko';
        }
        // Chinese patterns
        if (preg_match('/\b(zh|chi|zho|chinese|chs|cht)\b/i', $haystack)) {
            return 'zh';
        }

        // If Track 0 in western releases and no tags, default to English
        if ($trackIndex === 0 && (str_contains($haystack, 'bluray') || str_contains($haystack, 'webrip') || str_contains($haystack, 'web-dl'))) {
            return 'en';
        }

        return 'und';
    }

    protected function buildHumanTrackLabel(string $langCode, string $langName, string $title, int $idx, string $codec): string
    {
        $codecUpper = strtoupper($codec);

        if ($langCode !== 'und') {
            if ($title && !str_starts_with(strtolower($title), 'subtitle') && strtolower($title) !== strtolower($langName)) {
                return "{$langName} - {$title}";
            }
            return "{$langName}";
        }

        // Undefined track - make it friendly instead of 'und'
        if ($title && trim($title) !== '') {
            return "{$title} (Track " . ($idx + 1) . ")";
        }

        return "Subtitle Track " . ($idx + 1) . " ({$codecUpper})";
    }

    public function normalizeLanguageCode(string $code): string
    {
        $code = strtolower(trim($code));
        $map = [
            'ara' => 'ar', 'arabic' => 'ar', 'ar' => 'ar',
            'eng' => 'en', 'english' => 'en', 'en' => 'en',
            'spa' => 'es', 'spanish' => 'es', 'es' => 'es',
            'fre' => 'fr', 'fra' => 'fr', 'french' => 'fr', 'fr' => 'fr',
            'ger' => 'de', 'deu' => 'de', 'german' => 'de', 'de' => 'de',
            'ita' => 'it', 'italian' => 'it', 'it' => 'it',
            'por' => 'pt', 'portuguese' => 'pt', 'pt' => 'pt',
            'rus' => 'ru', 'russian' => 'ru', 'ru' => 'ru',
            'tur' => 'tr', 'turkish' => 'tr', 'tr' => 'tr',
            'fas' => 'fa', 'per' => 'fa', 'persian' => 'fa', 'fa' => 'fa', 'farsi' => 'fa',
            'jpn' => 'ja', 'japanese' => 'ja', 'ja' => 'ja',
            'kor' => 'ko', 'korean' => 'ko', 'ko' => 'ko',
            'zho' => 'zh', 'chi' => 'zh', 'chinese' => 'zh', 'zh' => 'zh',
            'hin' => 'hi', 'hindi' => 'hi', 'hi' => 'hi',
            'ind' => 'id', 'indonesian' => 'id', 'id' => 'id',
            'nld' => 'nl', 'dut' => 'nl', 'dutch' => 'nl', 'nl' => 'nl',
            'pol' => 'pl', 'polish' => 'pl', 'pl' => 'pl',
            'swe' => 'sv', 'swedish' => 'sv', 'sv' => 'sv',
            'dan' => 'da', 'danish' => 'da', 'da' => 'da',
            'nor' => 'no', 'norwegian' => 'no', 'no' => 'no',
            'fin' => 'fi', 'finnish' => 'fi', 'fi' => 'fi',
            'ell' => 'el', 'gre' => 'el', 'greek' => 'el', 'el' => 'el',
            'heb' => 'he', 'hebrew' => 'he', 'he' => 'he',
            'vie' => 'vi', 'vietnamese' => 'vi', 'vi' => 'vi',
            'tha' => 'th', 'thai' => 'th', 'th' => 'th',
            'urd' => 'ur', 'urdu' => 'ur', 'ur' => 'ur',
            'ron' => 'ro', 'rum' => 'ro', 'romanian' => 'ro', 'ro' => 'ro',
            'ces' => 'cs', 'cze' => 'cs', 'czech' => 'cs', 'cs' => 'cs',
            'hun' => 'hu', 'hungarian' => 'hu', 'hu' => 'hu',
            'ukr' => 'uk', 'ukrainian' => 'uk', 'uk' => 'uk',
            'msa' => 'ms', 'may' => 'ms', 'malay' => 'ms', 'ms' => 'ms',
            'ben' => 'bn', 'bengali' => 'bn', 'bn' => 'bn',
            'tgl' => 'tl', 'fil' => 'tl', 'tagalog' => 'tl', 'tl' => 'tl',
        ];

        return $map[$code] ?? (strlen($code) === 2 ? $code : 'und');
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
