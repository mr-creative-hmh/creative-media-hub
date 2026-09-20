<?php

namespace App\Services\Subtitles;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Subtitle;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubtitleTranslatorService
{
    protected SubtitleManagerService $subtitleManager;

    protected EmbeddedSubtitleDetectorService $embeddedDetector;

    public function __construct(
        SubtitleManagerService $subtitleManager,
        ?EmbeddedSubtitleDetectorService $embeddedDetector = null
    ) {
        $this->subtitleManager = $subtitleManager;
        $this->embeddedDetector = $embeddedDetector ?? app(EmbeddedSubtitleDetectorService::class);
    }

    /**
     * Convert WebVTT content (from embedded streams or web sources) to standard SRT format.
     * Accurately parses both MM:SS.mmm and HH:MM:SS.mmm, standardizing to HH:MM:SS,mmm.
     */
    public function convertVttToSrt(string $vtt): string
    {
        $vtt = str_replace(["\r\n", "\r"], "\n", trim($vtt));
        if (str_starts_with($vtt, "\xEF\xBB\xBF")) {
            $vtt = substr($vtt, 3);
        }

        $blocks = preg_split('/\n\s*\n/', $vtt);
        $srtBlocks = [];
        $counter = 1;

        $timeRegex = '/(?:(\d{1,2}):)?(\d{2}):(\d{2})[,\.](\d{2,3})\s*-->\s*(?:(\d{1,2}):)?(\d{2}):(\d{2})[,\.](\d{2,3})/';

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block) || str_starts_with($block, 'WEBVTT') || str_starts_with($block, 'NOTE')) {
                continue;
            }

            $lines = explode("\n", $block);
            $timeLineIndex = -1;
            $timeMatch = null;

            foreach ($lines as $idx => $line) {
                if (preg_match($timeRegex, $line, $m)) {
                    $timeLineIndex = $idx;
                    $timeMatch = $m;
                    break;
                }
            }

            if ($timeLineIndex === -1 || ! $timeMatch) {
                continue;
            }

            $h1 = ! empty($timeMatch[1]) ? str_pad($timeMatch[1], 2, '0', STR_PAD_LEFT) : '00';
            $m1 = $timeMatch[2];
            $s1 = $timeMatch[3];
            $ms1 = str_pad($timeMatch[4], 3, '0', STR_PAD_RIGHT);

            $h2 = ! empty($timeMatch[5]) ? str_pad($timeMatch[5], 2, '0', STR_PAD_LEFT) : '00';
            $m2 = $timeMatch[6];
            $s2 = $timeMatch[7];
            $ms2 = str_pad($timeMatch[8], 3, '0', STR_PAD_RIGHT);

            $timeline = "{$h1}:{$m1}:{$s1},{$ms1} --> {$h2}:{$m2}:{$s2},{$ms2}";
            $textLines = array_slice($lines, $timeLineIndex + 1);
            $text = trim(implode("\n", $textLines));
            $text = strip_tags($text);

            if (! empty($text)) {
                $srtBlocks[] = "{$counter}\n{$timeline}\n{$text}";
                $counter++;
            }
        }

        return implode("\n\n", $srtBlocks)."\n";
    }

    /**
     * Parse an SRT formatted string into structured cues.
     *
     * @return array<int, array{index: string|int, timeline: string, text: string}>
     */
    public function parseSrt(string $content): array
    {
        // Normalize line breaks
        $content = str_replace(["\r\n", "\r"], "\n", trim($content));
        // Strip UTF-8 BOM if present
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $rawBlocks = preg_split('/\n\s*\n/', $content);
        $cues = [];

        foreach ($rawBlocks as $block) {
            $lines = explode("\n", trim($block));
            if (count($lines) < 2) {
                continue;
            }

            $timeLineIndex = -1;
            for ($i = 0; $i < min(4, count($lines)); $i++) {
                if (str_contains($lines[$i], '-->')) {
                    $timeLineIndex = $i;
                    break;
                }
            }

            if ($timeLineIndex === -1) {
                continue;
            }

            $index = $timeLineIndex > 0 ? trim($lines[0]) : (count($cues) + 1);
            $timeline = trim($lines[$timeLineIndex]);
            $textLines = array_slice($lines, $timeLineIndex + 1);
            $text = trim(implode("\n", $textLines));

            $cues[] = [
                'index' => $index,
                'timeline' => $timeline,
                'text' => $text,
            ];
        }

        return $cues;
    }

    /**
     * Reconstruct structured cues into standard SRT file format.
     *
     * @param  array<int, array{index: string|int, timeline: string, text: string}>  $cues
     */
    public function reconstructSrt(array $cues): string
    {
        $blocks = [];
        $counter = 1;

        foreach ($cues as $cue) {
            $idx = ! empty($cue['index']) ? $cue['index'] : $counter;
            $timeline = $cue['timeline'];
            $text = trim($cue['text']);
            $blocks[] = "{$idx}\n{$timeline}\n{$text}";
            $counter++;
        }

        return implode("\n\n", $blocks)."\n";
    }

    /**
     * Translate an entire SRT content from English to cinema-standard Modern Standard Arabic.
     */
    public function translateSrtContent(string $englishSrtContent, ?callable $onProgress = null): string
    {
        $cues = $this->parseSrt($englishSrtContent);
        if (empty($cues)) {
            return '';
        }

        $translatedCues = $this->translateCuesToArabic($cues, $onProgress);

        return $this->reconstructSrt($translatedCues);
    }

    /**
     * Batch translate cues into Arabic while preserving timestamps and dialogue structure.
     *
     * @param  array<int, array{index: string|int, timeline: string, text: string}>  $cues
     * @return array<int, array{index: string|int, timeline: string, text: string}>
     */
    public function translateCuesToArabic(array $cues, ?callable $onProgress = null): array
    {
        $totalCues = count($cues);
        $batchSize = 35;
        $chunks = array_chunk($cues, $batchSize, true);
        $result = $cues;
        $processedCount = 0;

        foreach ($chunks as $chunk) {
            $taggedLines = [];
            foreach ($chunk as $idx => $cue) {
                $rawText = $cue['text'];
                // Clean empty or purely non-alphabetic noise
                if (empty(trim($rawText))) {
                    continue;
                }
                // Replace internal line breaks with a paragraph marker
                $singleLine = str_replace(["\r\n", "\n", "\r"], ' ¶ ', $rawText);
                $taggedLines[] = "[[[{$idx}]]] {$singleLine}";
            }

            if (empty($taggedLines)) {
                $processedCount += count($chunk);

                continue;
            }

            $joined = implode("\n", $taggedLines);
            $translatedBatch = $this->queryTranslationEndpoint($joined);

            if ($translatedBatch) {
                // Parse out the indexed items [[[index]]]
                preg_match_all('/\[\[\[(\d+)\]\]\]\s*(.*?)(?=\n\[\[\[\d+\]\]\]|$)/su', $translatedBatch, $matches, PREG_SET_ORDER);

                $matchedIndices = [];
                foreach ($matches as $m) {
                    $idx = (int) $m[1];
                    $transText = $this->cleanArabicDialogue($m[2]);
                    if (isset($result[$idx])) {
                        $result[$idx]['text'] = $transText;
                        $matchedIndices[] = $idx;
                    }
                }

                // If any cue in this chunk failed to match due to token distortion, translate individually
                foreach ($chunk as $idx => $cue) {
                    if (! in_array($idx, $matchedIndices, true) && ! empty(trim($cue['text']))) {
                        $singleTrans = $this->translateSingleText($cue['text']);
                        if ($singleTrans) {
                            $result[$idx]['text'] = $singleTrans;
                        }
                    }
                }
            } else {
                // Batch failed: fallback to single translation for each cue in chunk
                foreach ($chunk as $idx => $cue) {
                    if (! empty(trim($cue['text']))) {
                        $singleTrans = $this->translateSingleText($cue['text']);
                        if ($singleTrans) {
                            $result[$idx]['text'] = $singleTrans;
                        }
                    }
                }
            }

            $processedCount += count($chunk);
            if ($onProgress) {
                $onProgress($processedCount, $totalCues);
            }

            // Small delay to be polite to the translation service
            usleep(25000); // 25ms
        }

        return $result;
    }

    /**
     * Query translation endpoint with fallback mechanisms.
     */
    protected function queryTranslationEndpoint(string $text): ?string
    {
        try {
            $response = Http::timeout(12)->get('https://clients5.google.com/translate_a/t', [
                'client' => 'dict-chrome-ex',
                'sl' => 'en',
                'tl' => 'ar',
                'q' => $text,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data)) {
                    return implode("\n", $data);
                }

                return (string) $data;
            }
        } catch (\Throwable $e) {
            Log::warning('Google translation endpoint error: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Translate single text string with fallback to MyMemory.
     */
    protected function translateSingleText(string $text): ?string
    {
        $clean = trim($text);
        if (empty($clean)) {
            return $clean;
        }

        // Try primary
        $primary = $this->queryTranslationEndpoint($clean);
        if ($primary && trim($primary) !== $clean) {
            return $this->cleanArabicDialogue($primary);
        }

        // Fallback to MyMemory
        try {
            $res = Http::timeout(6)->get('https://api.mymemory.translated.net/get', [
                'q' => mb_substr($clean, 0, 450),
                'langpair' => 'en|ar',
            ]);

            if ($res->successful()) {
                $trans = $res->json('responseData.translatedText');
                if ($trans && ! str_contains($trans, 'MYMEMORY WARNING') && strtolower(trim($trans)) !== strtolower($clean)) {
                    return $this->cleanArabicDialogue(trim($trans));
                }
            }
        } catch (\Throwable $e) {
            // Silence fallback
        }

        return null;
    }

    /**
     * Clean and format Arabic dialogue line breaks, punctuation, and markers.
     */
    protected function cleanArabicDialogue(string $raw): string
    {
        $text = trim($raw);

        // Restore paragraph break marker
        $text = str_replace(' ¶ ', "\n", $text);
        $text = preg_replace('/\s*¶\s*/u', "\n", $text);

        // Normalize sound cue brackets
        $soundTranslations = [
            '/[\[\(]music[\]\)]/i' => '[موسيقى]',
            '/[\[\(]applause[\]\)]/i' => '[تصفيق]',
            '/[\[\(]cheering[\]\)]/i' => '[هتاف]',
            '/[\[\(]laughter[\]\)]/i' => '[ضحك]',
            '/[\[\(]chuckles[\]\)]/i' => '[ضحك]',
            '/[\[\(]sighs[\]\)]/i' => '[تنهد]',
            '/[\[\(]groans[\]\)]/i' => '[تأوه]',
            '/[\[\(]screaming[\]\)]/i' => '[صراخ]',
            '/[\[\(]gasping[\]\)]/i' => '[شهقة]',
            '/[\[\(]sobbing[\]\)]/i' => '[بكاء]',
        ];

        foreach ($soundTranslations as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        // Fix spaces before Arabic punctuation
        $text = preg_replace('/\s+([،؛؟!\.])/u', '$1', $text);

        return $text;
    }

    /**
     * Generate Arabic subtitle for a MediaItem or Episode by translating its English subtitle.
     * Prioritizes embedded subtitles directly from the container to guarantee 100% video sync
     * and prevents duplicate English subtitle registration.
     */
    public function generateArabicForMedia(MediaItem|Episode $media, ?int $sourceSubtitleId = null): ?Subtitle
    {
        $englishContent = null;
        $usedEmbedded = false;

        // 0. If specific source subtitle requested, prioritize it
        if ($sourceSubtitleId) {
            $sourceSub = Subtitle::find($sourceSubtitleId);
            if ($sourceSub) {
                if ($sourceSub->is_embedded || str_starts_with($sourceSub->file_path ?? '', 'embedded:')) {
                    $parts = explode(':', $sourceSub->file_path ?? '', 3);
                    $streamIdx = isset($parts[1]) ? (int) $parts[1] : 0;
                    $vPath = $parts[2] ?? $media->file_path;
                    if ($vPath && File::exists($vPath)) {
                        $rawVtt = $this->embeddedDetector->extractToWebVtt($vPath, $streamIdx, $sourceSub->format ?? 'srt');
                        $englishContent = $this->convertVttToSrt($rawVtt);
                        $usedEmbedded = true;
                    }
                } elseif ($sourceSub->file_path && File::exists($sourceSub->file_path)) {
                    $englishContent = File::get($sourceSub->file_path);
                }
            }
        }

        // 1. Find existing English subtitle in DB
        if (! $englishContent) {
            $englishSubs = Subtitle::where('subtitlable_id', $media->id)
                ->where('subtitlable_type', get_class($media))
                ->whereIn('language', ['en', 'eng'])
                ->get();

            // 1a. Prioritize embedded English subtitle from DB
            $embeddedSub = $englishSubs->first(fn ($s) => $s->is_embedded || str_starts_with($s->file_path ?? '', 'embedded:'));
            if ($embeddedSub) {
                $parts = explode(':', $embeddedSub->file_path ?? '', 3);
                $streamIdx = isset($parts[1]) ? (int) $parts[1] : 0;
                $vPath = $parts[2] ?? $media->file_path;
                if ($vPath && File::exists($vPath)) {
                    $rawVtt = $this->embeddedDetector->extractToWebVtt($vPath, $streamIdx, $embeddedSub->format ?? 'srt');
                    $englishContent = $this->convertVttToSrt($rawVtt);
                    $usedEmbedded = true;
                }
            }

            // 1b. Non-embedded local English subtitle in DB
            if (! $englishContent) {
                $fileSub = $englishSubs->first(fn ($s) => $s->file_path && ! str_starts_with($s->file_path, 'embedded:') && File::exists($s->file_path));
                if ($fileSub) {
                    $englishContent = File::get($fileSub->file_path);
                }
            }
        }

        // 2. Check embedded streams directly from media file container if not yet in DB
        if (! $englishContent && $media->file_path && File::exists($media->file_path)) {
            $detectedTracks = $this->embeddedDetector->detectEmbeddedSubtitles($media->file_path);
            foreach ($detectedTracks as $track) {
                if (in_array(strtolower($track['language'] ?? ''), ['en', 'eng'])) {
                    $streamIdx = (int) ($track['stream_index'] ?? 0);
                    $rawVtt = $this->embeddedDetector->extractToWebVtt($media->file_path, $streamIdx, $track['format'] ?? 'srt');
                    $englishContent = $this->convertVttToSrt($rawVtt);
                    $usedEmbedded = true;
                    break;
                }
            }
        }

        // 3. If not in DB or container, inspect filesystem next to media file
        if (! $englishContent && $media->file_path) {
            $mediaDir = pathinfo($media->file_path, PATHINFO_DIRNAME);
            $baseName = pathinfo($media->file_path, PATHINFO_FILENAME);

            $potentialPaths = [
                "{$mediaDir}/{$baseName}.en.srt",
                "{$mediaDir}/{$baseName}.eng.srt",
                "{$mediaDir}/{$baseName}.srt",
                "{$mediaDir}/Subs/{$baseName}.en.srt",
                "{$mediaDir}/Subs/English.srt",
            ];

            foreach ($potentialPaths as $p) {
                if (File::exists($p)) {
                    $englishContent = File::get($p);
                    break;
                }
            }
        }

        // 4. Only if still not available, attempt downloading English subtitle from web
        if (! $englishContent) {
            Log::info("No local or embedded English subtitle found for {$media->title}. Attempting to download English subtitle first...");
            $englishSub = $this->subtitleManager->downloadAndAttachRealSubtitle($media, 'en');
            if ($englishSub && $englishSub->file_path && File::exists($englishSub->file_path)) {
                $englishContent = File::get($englishSub->file_path);
            }
        }

        if (empty($englishContent) || ! str_contains($englishContent, '-->')) {
            Log::warning("Cannot generate Arabic subtitle for {$media->title}: No valid English subtitle content available to translate.");

            return null;
        }

        // Clean up redundant downloaded English subtitles if embedded English was used
        if ($usedEmbedded) {
            $redundantEnSubs = Subtitle::where('subtitlable_id', $media->id)
                ->where('subtitlable_type', get_class($media))
                ->whereIn('language', ['en', 'eng'])
                ->where('is_embedded', false)
                ->get();

            foreach ($redundantEnSubs as $redundant) {
                if ($redundant->file_path && File::exists($redundant->file_path)) {
                    @File::delete($redundant->file_path);
                }
                $redundant->delete();
            }
        }

        // 5. Translate to Arabic
        Log::info("Translating English subtitle for {$media->title} to Arabic...");
        $arabicContent = $this->translateSrtContent($englishContent);

        if (empty($arabicContent) || ! str_contains($arabicContent, '-->')) {
            Log::warning("Arabic translation failed to generate valid SRT content for {$media->title}.");

            return null;
        }

        // 6. Save target Arabic file
        $destDir = $media->file_path ? pathinfo($media->file_path, PATHINFO_DIRNAME) : storage_path('app/subtitles');
        if (! File::isDirectory($destDir) || ! is_writable($destDir)) {
            $destDir = storage_path('app/subtitles');
        }
        if (! File::isDirectory($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $baseName = $media->file_path ? pathinfo($media->file_path, PATHINFO_FILENAME) : "media_{$media->id}";
        $arabicPath = "{$destDir}/{$baseName}.ar.srt";

        // Clean up numbered duplicate Arabic files on disk (e.g. *.ar (1).srt, *.ar (2).srt)
        $pattern = "{$destDir}/{$baseName}*ar*.srt";
        foreach (glob($pattern) ?: [] as $existingFile) {
            $norm = str_replace('\\', '/', $existingFile);
            if ($norm !== str_replace('\\', '/', $arabicPath)) {
                @File::delete($existingFile);
            }
        }

        // Clean up duplicate non-embedded Arabic records in DB
        Subtitle::where('subtitlable_id', $media->id)
            ->where('subtitlable_type', get_class($media))
            ->where('language', 'ar')
            ->where('is_embedded', false)
            ->where('file_path', '!=', $arabicPath)
            ->delete();

        File::put($arabicPath, $arabicContent);

        // 7. Store / Update Subtitle in database
        $sub = Subtitle::updateOrCreate([
            'subtitlable_id' => $media->id,
            'subtitlable_type' => get_class($media),
            'language' => 'ar',
            'is_embedded' => false,
        ], [
            'language_name' => 'Arabic',
            'format' => 'srt',
            'file_path' => $arabicPath,
            'is_default' => true,
        ]);

        Log::info("Arabic subtitle successfully generated and attached for {$media->title} at {$arabicPath}");

        return $sub;
    }
}
