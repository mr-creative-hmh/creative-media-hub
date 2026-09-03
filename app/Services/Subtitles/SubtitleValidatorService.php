<?php

namespace App\Services\Subtitles;

class SubtitleValidatorService
{
    /**
     * Known dummy template strings that indicate fake or stub subtitles.
     */
    protected array $placeholderPhrases = [
        'welcome to the creative media streaming library',
        'sample subtitle',
        'dummy subtitle',
        'placeholder subtitle',
        'lorem ipsum',
        'test subtitle file',
        'subtitle template',
    ];

    /**
     * Validate subtitle file integrity, cue counts, and detect dummy or corrupt stubs.
     *
     * @return array{is_valid: bool, cue_count: int, file_size: int, issues: string[], clean_sample: string, recommended_action: string}
     */
    public function validate(string $filePathOrContent, ?string $filenameHint = null): array
    {
        $issues = [];
        $raw = '';
        $fileSize = 0;

        if (@file_exists($filePathOrContent)) {
            $fileSize = (int) @filesize($filePathOrContent);
            $raw = (string) @file_get_contents($filePathOrContent, false, null, 0, 1048576); // Up to 1MB
        } else {
            $raw = $filePathOrContent;
            $fileSize = strlen($raw);
        }

        // 1. Zero byte or unreadable file
        if ($fileSize === 0 || empty(trim($raw))) {
            return [
                'is_valid' => false,
                'cue_count' => 0,
                'file_size' => $fileSize,
                'issues' => ['Empty 0-byte file or unreadable content'],
                'clean_sample' => '',
                'recommended_action' => 'delete',
            ];
        }

        // 2. Binary / Garbage Detection
        $nonPrintable = preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', substr($raw, 0, 4096));
        if ($nonPrintable > 50) {
            return [
                'is_valid' => false,
                'cue_count' => 0,
                'file_size' => $fileSize,
                'issues' => ['Corrupted binary data disguised as subtitle'],
                'clean_sample' => '',
                'recommended_action' => 'delete',
            ];
        }

        // 3. HTML Error Page Detection (e.g. Cloudflare / 404 / 503 saved as .srt)
        $lowerRaw = strtolower(substr($raw, 0, 2048));
        if (
            str_contains($lowerRaw, '<!doctype html') ||
            str_contains($lowerRaw, '<html') ||
            str_contains($lowerRaw, 'cloudflare') ||
            str_contains($lowerRaw, '503 service temporarily unavailable') ||
            str_contains($lowerRaw, '404 not found') ||
            str_contains($lowerRaw, 'access denied') ||
            str_contains($lowerRaw, '<title>error</title>')
        ) {
            return [
                'is_valid' => false,
                'cue_count' => 0,
                'file_size' => $fileSize,
                'issues' => ['HTML error page saved with subtitle extension'],
                'clean_sample' => substr(strip_tags($raw), 0, 100),
                'recommended_action' => 'delete',
            ];
        }

        // 4. Count valid subtitle timecodes
        // Standard SRT / VTT timecodes: 00:01:23,456 --> 00:01:25,789 or ASS Dialogue:
        preg_match_all('/\d{1,2}:\d{2}:\d{2}[,\.]\d{3}\s*-->\s*\d{1,2}:\d{2}:\d{2}[,\.]\d{3}/', $raw, $srtMatches);
        $srtCueCount = count($srtMatches[0] ?? []);

        preg_match_all('/Dialogue:\s*[^,]+,[^,]+,[^,]+,[^,]+/i', $raw, $assMatches);
        $assCueCount = count($assMatches[0] ?? []);

        $cueCount = max($srtCueCount, $assCueCount);

        if ($cueCount === 0) {
            $issues[] = 'No valid subtitle timestamp timecodes found';
        }

        // 5. Short / Stub Check: A genuine movie or TV series episode subtitle typically has 200-1800 dialogue cues.
        // If it has fewer than 5 cues or is smaller than 300 bytes, it is a stub or incomplete test file.
        if ($cueCount < 5) {
            $issues[] = "Extremely short stub (only {$cueCount} cues found, minimum required: 5)";
        }

        if ($fileSize < 300) {
            $issues[] = "File size ({$fileSize} bytes) is below minimum threshold (300 bytes)";
        }

        // 6. Placeholder / Dummy template text detection
        foreach ($this->placeholderPhrases as $phrase) {
            if (str_contains($lowerRaw, $phrase)) {
                $issues[] = "Contains dummy placeholder/template text: '{$phrase}'";
                break;
            }
        }

        // 7. Extract clean text sample for diagnostics
        $detector = app(SubtitleLanguageDetectorService::class);
        $cleanSample = $detector->extractDialogueText($raw);
        $cleanSample = mb_substr($cleanSample, 0, 150, 'UTF-8');

        $isValid = empty($issues);

        return [
            'is_valid' => $isValid,
            'cue_count' => $cueCount,
            'file_size' => $fileSize,
            'issues' => $issues,
            'clean_sample' => $cleanSample,
            'recommended_action' => $isValid ? 'keep' : 'delete',
        ];
    }
}
