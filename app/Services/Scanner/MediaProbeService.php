<?php

namespace App\Services\Scanner;

use App\Services\Media\FfmpegLocatorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class MediaProbeService
{
    protected FfmpegLocatorService $ffmpegLocator;

    protected string $probePath;

    protected bool $ffprobeAvailable;

    protected int $cacheTtl = 86400; // 24 hours

    public function __construct(FfmpegLocatorService $ffmpegLocator)
    {
        $this->ffmpegLocator = $ffmpegLocator;
        $this->probePath = $ffmpegLocator->getFfprobePath();
        $this->ffprobeAvailable = $this->checkFfprobeAvailability();
    }

    /**
     * Check if ffprobe is actually available and executable.
     */
    protected function checkFfprobeAvailability(): bool
    {
        if (! $this->probePath || $this->probePath === 'ffprobe') {
            // Try to resolve via PATH
            $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $testCmd = $isWin ? 'where ffprobe 2>NUL' : 'which ffprobe 2>/dev/null';
            $out = [];
            @exec($testCmd, $out, $code);
            if ($code === 0 && ! empty($out[0]) && trim($out[0]) !== '' && file_exists(trim($out[0]))) {
                $this->probePath = trim($out[0]);

                return true;
            }

            return false;
        }

        return file_exists($this->probePath) && is_executable($this->probePath);
    }

    /**
     * Check if ffprobe is available for probing.
     */
    public function isAvailable(): bool
    {
        return $this->ffprobeAvailable;
    }

    /**
     * Probe a media file and return comprehensive metadata.
     * Returns an empty-metadata array (with 'Unknown' defaults) if ffprobe is not available or probing fails.
     * Callers can rely on non-null return; check resolution === 'Unknown' to detect missing data.
     */
    public function probe(string $filePath, bool $useCache = true): array
    {
        // Return default empty metadata when ffprobe is not available
        if (! $this->ffprobeAvailable) {
            Log::debug("ffprobe not available, skipping media probe for {$filePath}");

            return $this->emptyMetadata();
        }

        if (! file_exists($filePath)) {
            return $this->emptyMetadata();
        }

        $cacheKey = 'media_probe_'.md5($filePath).'_'.filesize($filePath);

        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $process = new Process([
                $this->probePath,
                '-v', 'quiet',
                '-print_format', 'json',
                '-show_format',
                '-show_streams',
                $filePath,
            ]);

            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::warning("ffprobe failed for {$filePath}: ".$process->getErrorOutput());

                return $this->emptyMetadata();
            }

            $output = json_decode($process->getOutput(), true);
            if (! $output) {
                return $this->emptyMetadata();
            }

            $metadata = $this->parseProbeOutput($output);

            if ($useCache) {
                Cache::put($cacheKey, $metadata, $this->cacheTtl);
            }

            return $metadata;
        } catch (\Exception $e) {
            Log::error("MediaProbeService error for {$filePath}: ".$e->getMessage());

            return $this->emptyMetadata();
        }
    }

    /**
     * Return a default empty metadata array so callers never receive null.
     */
    protected function emptyMetadata(): array
    {
        return [
            'resolution' => null,
            'width' => 0,
            'height' => 0,
            'video_codec' => null,
            'video_profile' => null,
            'video_bitrate' => 0,
            'audio_codec' => null,
            'audio_channels' => 0,
            'audio_channel_layout' => null,
            'audio_bitrate' => 0,
            'duration' => 0.0,
            'container' => null,
            'hdr_format' => null,
            'color_space' => null,
            'color_transfer' => null,
            'framerate' => 0.0,
            'total_bitrate' => 0,
        ];
    }

    /**
     * Parse ffprobe JSON output into structured metadata.
     */
    protected function parseProbeOutput(array $output): array
    {
        $streams = $output['streams'] ?? [];
        $format = $output['format'] ?? [];

        // Find video stream
        $videoStream = null;
        $audioStreams = [];
        foreach ($streams as $stream) {
            if ($stream['codec_type'] === 'video' && $videoStream === null) {
                $videoStream = $stream;
            } elseif ($stream['codec_type'] === 'audio') {
                $audioStreams[] = $stream;
            }
        }

        // Default values
        $metadata = [
            'resolution' => 'Unknown',
            'width' => 0,
            'height' => 0,
            'video_codec' => 'Unknown',
            'video_profile' => 'Unknown',
            'video_bitrate' => 0,
            'audio_codec' => 'Unknown',
            'audio_channels' => 0,
            'audio_channel_layout' => 'Unknown',
            'audio_bitrate' => 0,
            'duration' => 0.0,
            'container' => $format['format_name'] ?? 'Unknown',
            'hdr_format' => null,
            'color_space' => null,
            'color_transfer' => null,
            'framerate' => 0.0,
            'total_bitrate' => (int) ($format['bit_rate'] ?? 0),
        ];

        // Parse video stream
        if ($videoStream) {
            $width = (int) ($videoStream['width'] ?? 0);
            $height = (int) ($videoStream['height'] ?? 0);

            $metadata['width'] = $width;
            $metadata['height'] = $height;
            $metadata['resolution'] = $this->calculateResolution($width, $height);
            $metadata['video_codec'] = $this->normalizeVideoCodec($videoStream['codec_name'] ?? 'Unknown');
            $metadata['video_profile'] = $videoStream['profile'] ?? 'Unknown';
            $metadata['video_bitrate'] = (int) ($videoStream['bit_rate'] ?? 0);

            // Framerate
            if (isset($videoStream['r_frame_rate'])) {
                $parts = explode('/', $videoStream['r_frame_rate']);
                if (count($parts) === 2 && (int) $parts[1] > 0) {
                    $metadata['framerate'] = round((int) $parts[0] / (int) $parts[1], 2);
                }
            }

            // HDR detection
            $metadata['hdr_format'] = $this->detectHdrFormat($videoStream);
            $metadata['color_space'] = $videoStream['color_space'] ?? null;
            $metadata['color_transfer'] = $videoStream['color_transfer'] ?? null;
        }

        // Parse audio stream (prefer primary audio track)
        if (! empty($audioStreams)) {
            // Prefer language-specific audio (English first, then any)
            $primaryAudio = $this->selectPrimaryAudio($audioStreams);
            $metadata['audio_codec'] = $this->normalizeAudioCodec($primaryAudio['codec_name'] ?? 'Unknown');
            $metadata['audio_channels'] = (int) ($primaryAudio['channels'] ?? 0);
            $metadata['audio_channel_layout'] = $primaryAudio['channel_layout'] ?? 'Unknown';
            $metadata['audio_bitrate'] = (int) ($primaryAudio['bit_rate'] ?? 0);
        }

        // Duration from format
        $metadata['duration'] = (float) ($format['duration'] ?? 0);

        return $metadata;
    }

    /**
     * Calculate human-readable resolution from dimensions.
     */
    protected function calculateResolution(int $width, int $height): string
    {
        // Use height for standard naming (1080, 720, etc.)
        $resolutions = [
            4320 => '8K UHD',
            2160 => '4K UHD',
            1440 => '1440p 2K',
            1080 => '1080p FHD',
            720 => '720p HD',
            576 => '576p SD',
            480 => '480p SD',
            360 => '360p',
            240 => '240p',
        ];

        foreach ($resolutions as $minHeight => $label) {
            if ($height >= $minHeight) {
                return $label;
            }
        }

        return "{$width}x{$height}";
    }

    /**
     * Normalize video codec names to common labels.
     */
    protected function normalizeVideoCodec(string $codec): string
    {
        $codec = strtolower(trim($codec));

        $mapping = [
            'hevc' => 'HEVC / H.265',
            'h265' => 'HEVC / H.265',
            'h264' => 'H.264 / AVC',
            'avc' => 'H.264 / AVC',
            'av1' => 'AV1',
            'vp9' => 'VP9',
            'vp8' => 'VP8',
            'mpeg4' => 'MPEG-4',
            'mpeg2video' => 'MPEG-2',
            'mpeg2' => 'MPEG-2',
            'xvid' => 'XviD',
            'divx' => 'DivX',
            'prores' => 'Apple ProRes',
            'dnxhd' => 'DNxHD',
            'dnxhr' => 'DNxHR',
            'rawvideo' => 'RAW Video',
        ];

        return $mapping[$codec] ?? strtoupper($codec);
    }

    /**
     * Normalize audio codec names to common labels.
     */
    protected function normalizeAudioCodec(string $codec): string
    {
        $codec = strtolower(trim($codec));

        $mapping = [
            'aac' => 'AAC',
            'mp3' => 'MP3',
            'flac' => 'FLAC',
            'opus' => 'Opus',
            'vorbis' => 'Vorbis',
            'ac3' => 'Dolby Digital',
            'eac3' => 'Dolby Digital Plus',
            'truehd' => 'Dolby TrueHD',
            'dts' => 'DTS',
            'dca' => 'DTS',
            'pcm_s16le' => 'PCM',
            'pcm_s24le' => 'PCM',
            'pcm_s32le' => 'PCM',
            'alac' => 'Apple Lossless',
        ];

        return $mapping[$codec] ?? strtoupper($codec);
    }

    /**
     * Detect HDR format from video stream side data and color info.
     */
    protected function detectHdrFormat(array $videoStream): ?string
    {
        $colorTransfer = strtolower($videoStream['color_transfer'] ?? '');
        $colorSpace = strtolower($videoStream['color_space'] ?? '');
        $colorPrimaries = strtolower($videoStream['color_primaries'] ?? '');

        // Check side data for HDR info
        $sideData = $videoStream['side_data_list'] ?? [];
        foreach ($sideData as $sideDataItem) {
            $type = $sideDataItem['side_data_type'] ?? '';
            if (str_contains($type, 'Mastering display')) {
                return 'Dolby Vision / HDR10+';
            }
            if (str_contains($type, 'Content light level')) {
                return 'HDR10';
            }
        }

        // Check color transfer characteristics
        if (str_contains($colorTransfer, 'smpte2084') || str_contains($colorTransfer, 'pq')) {
            return 'HDR10';
        }
        if (str_contains($colorTransfer, 'arib-std-b67') || str_contains($colorTransfer, 'hlg')) {
            return 'HLG';
        }
        if (str_contains($colorSpace, 'bt2020') && str_contains($colorPrimaries, 'bt2020')) {
            return 'HDR10';
        }

        return null;
    }

    /**
     * Select primary audio stream (prefer English, fallback to first).
     */
    protected function selectPrimaryAudio(array $audioStreams): array
    {
        // Try to find English audio first
        foreach ($audioStreams as $audio) {
            $tags = $audio['tags'] ?? [];
            $language = strtolower($tags['language'] ?? '');
            if ($language === 'eng' || $language === 'en') {
                return $audio;
            }
        }

        // Try to find any audio with more than 2 channels (surround)
        foreach ($audioStreams as $audio) {
            if ((int) ($audio['channels'] ?? 0) > 2) {
                return $audio;
            }
        }

        // Fallback to first audio stream
        return $audioStreams[0] ?? [];
    }
}
