<?php

namespace App\Services\Streaming;

use App\Models\Subtitle;
use App\Services\Subtitles\EmbeddedSubtitleDetectorService;
use App\Services\Subtitles\SubtitleLanguageDetectorService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class SubtitleStreamService
{
    public function __construct(
        protected EmbeddedSubtitleDetectorService $embeddedSubDetector,
        protected SubtitleLanguageDetectorService $subLanguageDetector
    ) {}

    /**
     * Stream clean WebVTT subtitle track with UTF-8 normalization and dynamic start offset shifting.
     */
    public function streamSubtitle(Subtitle $subtitle, Request $request): Response
    {
        $path = $subtitle->file_path;
        $startSeconds = max(0, (float) $request->query('start', 0));

        // Check if subtitle is embedded inside video container
        if ($subtitle->is_embedded || str_starts_with($path ?? '', 'embedded:')) {
            $parts = explode(':', $path ?? '', 3);
            $streamIndex = isset($parts[1]) ? (int) $parts[1] : 0;
            $videoPath = $parts[2] ?? '';

            if (! $videoPath || ! File::exists($videoPath)) {
                $parentPath = $subtitle->subtitlable?->file_path;
                if ($parentPath && File::exists($parentPath)) {
                    $videoPath = str_replace('\\', '/', $parentPath);
                    $subtitle->update(['file_path' => "embedded:{$streamIndex}:{$videoPath}"]);
                }
            }

            if ($videoPath && File::exists($videoPath)) {
                $rawVtt = $this->embeddedSubDetector->extractToWebVtt($videoPath, $streamIndex, $subtitle->format ?? 'srt');
                $vtt = $this->convertToCleanWebVTT($rawVtt, $subtitle->format ?? 'srt', $startSeconds);

                return response($vtt, 200, [
                    'Content-Type' => 'text/vtt; charset=utf-8',
                    'Access-Control-Allow-Origin' => '*',
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                ]);
            }
        }

        // External Subtitle File
        if (! $path || ! File::exists($path)) {
            if ($path) {
                $dir = dirname(str_replace('\\', '/', $path));
                $fn = basename($path);
                $candidates = [
                    $dir.'/'.preg_replace('/\.2\./', '.', $fn),
                    $dir.'/'.preg_replace('/(\.[a-z]{2,3})\.srt$/i', '.2$1.srt', $fn),
                ];
                foreach ($candidates as $cand) {
                    if (File::exists($cand)) {
                        $path = $cand;
                        $subtitle->update(['file_path' => $cand]);
                        break;
                    }
                }
            }
        }

        if (! $path || ! File::exists($path)) {
            return response("WEBVTT\n\n", 200, [
                'Content-Type' => 'text/vtt; charset=utf-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        }

        $rawContent = File::get($path);
        $cleanUtf8 = $this->subLanguageDetector->sanitizeToUtf8($rawContent);

        // Self-heal file on disk if it was non-UTF-8 and is writable
        if ($cleanUtf8 !== $rawContent && is_writable($path)) {
            @file_put_contents($path, $cleanUtf8);
        }

        $vtt = $this->convertToCleanWebVTT($cleanUtf8, $subtitle->format ?? 'srt', $startSeconds);

        return response($vtt, 200, [
            'Content-Type' => 'text/vtt; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Convert SRT or raw WebVTT content into clean, standard WebVTT with accurate timing.
     */
    public function convertToCleanWebVTT(string $content, string $format = 'srt', float $startSeconds = 0.0): string
    {
        $content = $this->subLanguageDetector->sanitizeToUtf8($content);
        $vtt = "WEBVTT\n\n";
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Strip existing WEBVTT header and initial comments
        $content = preg_replace('/^WEBVTT[^\n]*\n+/i', '', trim($content));

        $timeRegex = '/(?:(\d{1,2}):)?(\d{2}):(\d{2})[,\.](\d{2,3})\s*-->\s*(?:(\d{1,2}):)?(\d{2}):(\d{2})[,\.](\d{2,3})/';

        if ($startSeconds <= 0) {
            $normalized = preg_replace_callback(
                $timeRegex,
                function ($m) {
                    $h1 = ! empty($m[1]) ? str_pad($m[1], 2, '0', STR_PAD_LEFT) : '00';
                    $m1 = $m[2];
                    $s1 = $m[3];
                    $ms1 = str_pad($m[4], 3, '0', STR_PAD_RIGHT);

                    $h2 = ! empty($m[5]) ? str_pad($m[5], 2, '0', STR_PAD_LEFT) : '00';
                    $m2 = $m[6];
                    $s2 = $m[7];
                    $ms2 = str_pad($m[8], 3, '0', STR_PAD_RIGHT);

                    return "{$h1}:{$m1}:{$s1}.{$ms1} --> {$h2}:{$m2}:{$s2}.{$ms2}";
                },
                $content
            );

            return $vtt.trim($normalized)."\n";
        }

        // Split into subtitle blocks and shift by $startSeconds
        $blocks = preg_split('/\n\s*\n/', trim($content));
        $outputBlocks = [];

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block) || str_starts_with($block, 'WEBVTT') || str_starts_with($block, 'NOTE')) {
                continue;
            }

            if (preg_match($timeRegex, $block, $m)) {
                $h1 = ! empty($m[1]) ? (int) $m[1] : 0;
                $m1 = (int) $m[2];
                $s1 = (int) $m[3];
                $ms1 = (float) str_pad($m[4], 3, '0', STR_PAD_RIGHT);

                $h2 = ! empty($m[5]) ? (int) $m[5] : 0;
                $m2 = (int) $m[6];
                $s2 = (int) $m[7];
                $ms2 = (float) str_pad($m[8], 3, '0', STR_PAD_RIGHT);

                $start = ($h1 * 3600) + ($m1 * 60) + $s1 + ($ms1 / 1000.0);
                $end = ($h2 * 3600) + ($m2 * 60) + $s2 + ($ms2 / 1000.0);

                if ($end <= $startSeconds) {
                    continue;
                }

                $newStart = max(0, $start - $startSeconds);
                $newEnd = max(0.1, $end - $startSeconds);

                $formatTime = function (float $sec): string {
                    $wholeSeconds = (int) floor($sec);
                    $h = intdiv($wholeSeconds, 3600);
                    $m = intdiv($wholeSeconds % 3600, 60);
                    $s = $wholeSeconds % 60;
                    $ms = (int) round(($sec - $wholeSeconds) * 1000);

                    if ($ms >= 1000) {
                        $ms -= 1000;
                        $s++;
                    }

                    return sprintf('%02d:%02d:%02d.%03d', $h, $m, $s, $ms);
                };

                $newTimecode = $formatTime($newStart).' --> '.$formatTime($newEnd);
                $adjustedBlock = preg_replace($timeRegex, $newTimecode, $block, 1);
                $outputBlocks[] = $adjustedBlock;
            }
        }

        return $vtt.implode("\n\n", $outputBlocks)."\n";
    }
}
