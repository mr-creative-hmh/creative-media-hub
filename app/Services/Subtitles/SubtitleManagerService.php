<?php

namespace App\Services\Subtitles;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Subtitle;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SubtitleManagerService
{
    protected OpenSubtitlesService $openSubtitles;
    protected SubDlService $subDl;

    public function __construct(OpenSubtitlesService $openSubtitles, SubDlService $subDl)
    {
        $this->openSubtitles = $openSubtitles;
        $this->subDl = $subDl;
    }

    public function findMissingSubtitles(): array
    {
        $missing = [];

        // Check Movies
        $movies = MediaItem::with('subtitles')->get();
        foreach ($movies as $movie) {
            $hasAr = $movie->subtitles->where('language', 'ar')->isNotEmpty();
            $hasEn = $movie->subtitles->where('language', 'en')->isNotEmpty();

            if (!$hasAr || !$hasEn) {
                $missing[] = [
                    'id' => $movie->id,
                    'type' => 'movie',
                    'title' => $movie->title,
                    'title_ar' => $movie->title_ar,
                    'release_year' => $movie->release_year,
                    'file_path' => $movie->file_path,
                    'missing_ar' => !$hasAr,
                    'missing_en' => !$hasEn,
                ];
            }
        }

        // Check Series Episodes
        $episodes = Episode::with(['series', 'subtitles'])->get();
        foreach ($episodes as $ep) {
            $hasAr = $ep->subtitles->where('language', 'ar')->isNotEmpty();
            $hasEn = $ep->subtitles->where('language', 'en')->isNotEmpty();

            if (!$hasAr || !$hasEn) {
                $missing[] = [
                    'id' => $ep->id,
                    'type' => 'episode',
                    'series_title' => $ep->series->title ?? 'Series',
                    'season_number' => $ep->season_id,
                    'episode_number' => $ep->episode_number,
                    'title' => $ep->title,
                    'file_path' => $ep->file_path,
                    'missing_ar' => !$hasAr,
                    'missing_en' => !$hasEn,
                ];
            }
        }

        return $missing;
    }

    public function downloadAndAttachMockSubtitle(MediaItem|Episode $media, string $lang = 'ar'): Subtitle
    {
        $langCode = strtolower($lang);
        $detector = app(EmbeddedSubtitleDetectorService::class);
        $langName = $detector->getLanguageName($langCode);

        $destDir = $media->file_path ? pathinfo($media->file_path, PATHINFO_DIRNAME) : storage_path('app/subtitles');
        $baseName = $media->file_path ? pathinfo($media->file_path, PATHINFO_FILENAME) : "media_{$media->id}";

        if (!File::isDirectory($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $srtPath = "{$destDir}/{$baseName}.{$langCode}.srt";

        // Sample SRT content
        $sampleContent = $langCode === 'ar' ?
"1
00:00:01,000 --> 00:00:04,500
[موسيقى سينمائية تصويرية]

2
00:00:05,000 --> 00:00:09,000
مرحباً بكم في مكتبة الوسائط الإبداعية.

3
00:00:10,000 --> 00:00:15,000
الترجمة متزامنة بنجاح باللغة العربية.
" :
"1
00:00:01,000 --> 00:00:04,500
[Cinematic Score Playing]

2
00:00:05,000 --> 00:00:09,000
Welcome to the Creative Media Streaming Library.

3
00:00:10,000 --> 00:00:15,000
English subtitles synchronized successfully.
";

        File::put($srtPath, $sampleContent);

        return Subtitle::updateOrCreate([
            'subtitlable_id' => $media->id,
            'subtitlable_type' => get_class($media),
            'language' => $langCode,
        ], [
            'language_name' => $langName,
            'format' => 'srt',
            'file_path' => $srtPath,
            'is_default' => $langCode === 'en',
        ]);
    }
}
