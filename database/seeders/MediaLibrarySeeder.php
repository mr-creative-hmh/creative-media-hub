<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Genre;
use Illuminate\Database\Seeder;

class MediaLibrarySeeder extends Seeder
{
    /**
     * Seed production-ready defaults: Core App Settings & Bilingual Genre Catalog.
     * All demo media items, sample series, mock actors, and mock watch histories have been removed.
     */
    public function run(): void
    {
        // 1. Core App Settings
        AppSetting::set('library_name', 'Creative Media Cinema', 'string');
        AppSetting::set('app_locale', 'en', 'string');
        AppSetting::set('enable_rtl', false, 'boolean');
        AppSetting::set('auto_download_subtitles', true, 'boolean');
        AppSetting::set('preferred_subtitle_languages', ['ar', 'en'], 'json');
        AppSetting::set('movie_naming_template', '{Type}/{Title} ({Year})/{Title} ({Year}) [{Resolution}].{ext}', 'string');
        AppSetting::set('series_naming_template', '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{Resolution}].{ext}', 'string');
        AppSetting::set('tmdb_api_key', '', 'string');

        // 2. Standard Bilingual Genre Catalog with TMDB Genre IDs
        $genresData = [
            ['name_en' => 'Action', 'name_ar' => 'حركة وإثارة', 'slug' => 'action', 'tmdb_genre_id' => 28],
            ['name_en' => 'Adventure', 'name_ar' => 'مغامرة', 'slug' => 'adventure', 'tmdb_genre_id' => 12],
            ['name_en' => 'Animation', 'name_ar' => 'رسوم متحركة / أنمي', 'slug' => 'animation', 'tmdb_genre_id' => 16],
            ['name_en' => 'Comedy', 'name_ar' => 'كوميديا', 'slug' => 'comedy', 'tmdb_genre_id' => 35],
            ['name_en' => 'Crime', 'name_ar' => 'جريمة', 'slug' => 'crime', 'tmdb_genre_id' => 80],
            ['name_en' => 'Documentary', 'name_ar' => 'وثائقي', 'slug' => 'documentary', 'tmdb_genre_id' => 99],
            ['name_en' => 'Drama', 'name_ar' => 'دراما', 'slug' => 'drama', 'tmdb_genre_id' => 18],
            ['name_en' => 'Family', 'name_ar' => 'عائلي', 'slug' => 'family', 'tmdb_genre_id' => 10751],
            ['name_en' => 'Fantasy', 'name_ar' => 'فانتازيا وخيال', 'slug' => 'fantasy', 'tmdb_genre_id' => 14],
            ['name_en' => 'History', 'name_ar' => 'تاريخي', 'slug' => 'history', 'tmdb_genre_id' => 36],
            ['name_en' => 'Horror', 'name_ar' => 'رعب', 'slug' => 'horror', 'tmdb_genre_id' => 27],
            ['name_en' => 'Mystery', 'name_ar' => 'غموض', 'slug' => 'mystery', 'tmdb_genre_id' => 9648],
            ['name_en' => 'Romance', 'name_ar' => 'رومانسي', 'slug' => 'romance', 'tmdb_genre_id' => 10749],
            ['name_en' => 'Sci-Fi', 'name_ar' => 'خيال علمي', 'slug' => 'sci-fi', 'tmdb_genre_id' => 878],
            ['name_en' => 'Thriller', 'name_ar' => 'تشويق وإثارة', 'slug' => 'thriller', 'tmdb_genre_id' => 53],
        ];

        foreach ($genresData as $genre) {
            Genre::updateOrCreate(
                ['slug' => $genre['slug']],
                $genre
            );
        }
    }
}
