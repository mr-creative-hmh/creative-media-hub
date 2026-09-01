<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\Genre;
use App\Models\MediaItem;
use App\Models\Person;
use App\Models\Season;
use App\Models\Series;
use App\Models\Subtitle;
use App\Models\WatchHistory;
use Illuminate\Database\Seeder;

class MediaLibrarySeeder extends Seeder
{
    public function run(): void
    {
        // 1. App Settings
        AppSetting::set('library_name', 'Creative Media Cinema', 'string');
        AppSetting::set('app_locale', 'en', 'string');
        AppSetting::set('enable_rtl', false, 'boolean');
        AppSetting::set('auto_download_subtitles', true, 'boolean');
        AppSetting::set('preferred_subtitle_languages', ['ar', 'en'], 'json');
        AppSetting::set('movie_naming_template', '{Type}/{Title} ({Year})/{Title} ({Year}) [{Resolution}].{ext}', 'string');
        AppSetting::set('series_naming_template', '{Type}/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{Resolution}].{ext}', 'string');
        AppSetting::set('tmdb_api_key', '', 'string');

        // 2. Genres (Bilingual)
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

        $genres = [];
        foreach ($genresData as $g) {
            $genres[$g['slug']] = Genre::create($g);
        }

        // 3. People (Actors & Directors)
        $peopleData = [
            'nolan' => Person::create([
                'name' => 'Christopher Nolan',
                'name_ar' => 'كريستوفر نولان',
                'profile_path' => 'https://image.tmdb.org/t/p/w500/xuAIuYSmsUzKlUMBFGVZaWsY3Z5.jpg',
                'known_for_department' => 'Directing',
            ]),
            'dicaprio' => Person::create([
                'name' => 'Leonardo DiCaprio',
                'name_ar' => 'ليوناردو دي كابريو',
                'profile_path' => 'https://image.tmdb.org/t/p/w500/wo2hJpn04vbtmh0B9utCFdsQhxM.jpg',
                'known_for_department' => 'Acting',
            ]),
            'murphy' => Person::create([
                'name' => 'Cillian Murphy',
                'name_ar' => 'كيليان مورفي',
                'profile_path' => 'https://image.tmdb.org/t/p/w500/dm6vT9t2f4rV5D1U4b1L4c4n1o3.jpg',
                'known_for_department' => 'Acting',
            ]),
            'mcconaughey' => Person::create([
                'name' => 'Matthew McConaughey',
                'name_ar' => 'ماثيو ماكونهي',
                'profile_path' => 'https://image.tmdb.org/t/p/w500/sY2mwpafcwqyYS1sRTSuCoYvVH5.jpg',
                'known_for_department' => 'Acting',
            ]),
            'hathaway' => Person::create([
                'name' => 'Anne Hathaway',
                'name_ar' => 'آن هاثاواي',
                'profile_path' => 'https://image.tmdb.org/t/p/w500/tLel4cuCreVVQ00FNeAcTevgCr.jpg',
                'known_for_department' => 'Acting',
            ]),
            'cranston' => Person::create([
                'name' => 'Bryan Cranston',
                'name_ar' => 'براين كرانستون',
                'profile_path' => 'https://image.tmdb.org/t/p/w500/7Jahy5LZX2Fo8fGJltMreAI49hC.jpg',
                'known_for_department' => 'Acting',
            ]),
            'paul' => Person::create([
                'name' => 'Aaron Paul',
                'name_ar' => 'آرون بول',
                'profile_path' => 'https://image.tmdb.org/t/p/w500/8k4FwG2J6w5G2Xn2v2kQ.jpg',
                'known_for_department' => 'Acting',
            ]),
            'milly' => Person::create([
                'name' => 'Millie Bobby Brown',
                'name_ar' => 'ميلي بوبي براون',
                'profile_path' => 'https://image.tmdb.org/t/p/w500/yzfxkQ42M631G0fUuD6e7PqR8Gq.jpg',
                'known_for_department' => 'Acting',
            ]),
            'villeneuve' => Person::create([
                'name' => 'Denis Villeneuve',
                'name_ar' => 'دينيس فيلنوف',
                'profile_path' => 'https://image.tmdb.org/t/p/w500/zdjX2k4K6bN2L1qV.jpg',
                'known_for_department' => 'Directing',
            ]),
            'chalamet' => Person::create([
                'name' => 'Timothée Chalamet',
                'name_ar' => 'تيموثي شالاماي',
                'profile_path' => 'https://image.tmdb.org/t/p/w500/BE2sdjpgsa2rNTFa66f7ikNV.jpg',
                'known_for_department' => 'Acting',
            ]),
            'zendaya' => Person::create([
                'name' => 'Zendaya',
                'name_ar' => 'زيندايا',
                'profile_path' => 'https://image.tmdb.org/t/p/w500/r3A7evZyTItqT93tfOF.jpg',
                'known_for_department' => 'Acting',
            ]),
        ];

        // 4. Sample Movies
        $interstellar = MediaItem::create([
            'title' => 'Interstellar',
            'original_title' => 'Interstellar',
            'title_ar' => 'بين النجوم',
            'release_year' => 2014,
            'tmdb_id' => '157336',
            'imdb_id' => 'tt0816692',
            'overview' => 'The adventures of a group of explorers who make use of a newly discovered wormhole to surpass the limitations on human space travel and conquer the vast distances involved in an interstellar voyage.',
            'overview_ar' => 'مغامرات فريق من المستكشفين الذين يستخدمون ثقباً دودياً تم اكتشافه حديثاً لتجاوز القيود المفروضة على السفر الفضائي البشري والتغلب على المسافات الشاسعة في رحلة بين النجوم لإنقاذ البشرية.',
            'poster_path' => 'https://image.tmdb.org/t/p/w780/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg',
            'backdrop_path' => 'https://image.tmdb.org/t/p/original/xJHokMbljvjADYdit5fK5VQsXEG.jpg',
            'trailer_url' => 'https://www.youtube.com/watch?v=zSWdZVtXT7E',
            'rating' => 8.7,
            'vote_count' => 34500,
            'runtime_minutes' => 169,
            'resolution' => '4K UHD HDR',
            'video_codec' => 'HEVC / H.265 (Main 10)',
            'audio_codec' => 'DTS-HD MA 5.1',
            'file_path' => 'C:/Media/Movies/Interstellar (2014)/Interstellar (2014) [4K UHD].mkv',
            'file_size_bytes' => 18450000000,
            'folder_path' => 'C:/Media/Movies/Interstellar (2014)',
            'mood_tags' => ['Mind-Bending', 'Emotional', 'Epic Sci-Fi', 'Space Exploration'],
            'is_favorite' => true,
        ]);
        $interstellar->genres()->sync([$genres['sci-fi']->id, $genres['adventure']->id, $genres['drama']->id]);
        $interstellar->people()->attach($peopleData['nolan']->id, ['role' => 'director', 'order' => 0]);
        $interstellar->people()->attach($peopleData['mcconaughey']->id, ['role' => 'actor', 'character_name' => 'Joseph Cooper', 'order' => 1]);
        $interstellar->people()->attach($peopleData['hathaway']->id, ['role' => 'actor', 'character_name' => 'Dr. Amelia Brand', 'order' => 2]);

        Subtitle::create([
            'subtitlable_id' => $interstellar->id,
            'subtitlable_type' => MediaItem::class,
            'language' => 'en',
            'language_name' => 'English',
            'format' => 'srt',
            'file_path' => 'C:/Media/Movies/Interstellar (2014)/Interstellar (2014).en.srt',
            'is_default' => true,
        ]);
        Subtitle::create([
            'subtitlable_id' => $interstellar->id,
            'subtitlable_type' => MediaItem::class,
            'language' => 'ar',
            'language_name' => 'Arabic',
            'format' => 'srt',
            'file_path' => 'C:/Media/Movies/Interstellar (2014)/Interstellar (2014).ar.srt',
        ]);

        $inception = MediaItem::create([
            'title' => 'Inception',
            'original_title' => 'Inception',
            'title_ar' => 'ازدراع الأحلام',
            'release_year' => 2010,
            'tmdb_id' => '27205',
            'imdb_id' => 'tt1375666',
            'overview' => 'Cobb, a skilled thief who commits corporate espionage by infiltrating the subconscious of his targets is offered a chance to regain his old life as payment for a task considered to be impossible: "inception", the implantation of another person\'s idea into a target\'s subconscious.',
            'overview_ar' => 'كوب هو لص محترف يمارس التجسس التجاري من خلال التسلل إلى العقل الباطن لأهدافه أثناء نومهم، يُعرض عليه فرصة لاستعادة حياته القديمة مقابل تنفيذ مهمة تبدو مستحيلة: زرع فكرة داخل عقل شخص آخر.',
            'poster_path' => 'https://image.tmdb.org/t/p/w780/oYuLEt3zVCKq57qu2F8dT7NIa6f.jpg',
            'backdrop_path' => 'https://image.tmdb.org/t/p/original/8ZTVqvKDQ8emSGUEMjsS4yUmCGe.jpg',
            'trailer_url' => 'https://www.youtube.com/watch?v=YoHD9XEInc0',
            'rating' => 8.8,
            'vote_count' => 36200,
            'runtime_minutes' => 148,
            'resolution' => '4K UHD HDR',
            'video_codec' => 'HEVC / H.265',
            'audio_codec' => 'Dolby Atmos 7.1',
            'file_path' => 'C:/Media/Movies/Inception (2010)/Inception (2010) [4K UHD].mkv',
            'file_size_bytes' => 16200000000,
            'folder_path' => 'C:/Media/Movies/Inception (2010)',
            'mood_tags' => ['Mind-Bending', 'Heist Thriller', 'Suspense', 'Late Night Adrenaline'],
            'is_favorite' => true,
        ]);
        $inception->genres()->sync([$genres['sci-fi']->id, $genres['action']->id, $genres['thriller']->id]);
        $inception->people()->attach($peopleData['nolan']->id, ['role' => 'director', 'order' => 0]);
        $inception->people()->attach($peopleData['dicaprio']->id, ['role' => 'actor', 'character_name' => 'Dom Cobb', 'order' => 1]);
        $inception->people()->attach($peopleData['murphy']->id, ['role' => 'actor', 'character_name' => 'Robert Fischer', 'order' => 2]);

        $dune2 = MediaItem::create([
            'title' => 'Dune: Part Two',
            'original_title' => 'Dune: Part Two',
            'title_ar' => 'كثيب: الجزء الثاني',
            'release_year' => 2024,
            'tmdb_id' => '693134',
            'imdb_id' => 'tt15239678',
            'overview' => 'Follow the mythic journey of Paul Atreides as he unites with Chani and the Fremen while on a path of revenge against the conspirators who destroyed his family.',
            'overview_ar' => 'تتبع الرحلة الأسطورية لبول آتريديز بينما يتحد مع تشاني وشعب الفريمن على طريق الانتقام ضد المتآمرين الذين دمروا عائلته.',
            'poster_path' => 'https://image.tmdb.org/t/p/w780/1pdfLvkbY9ohJlCjQH2CZjjYVvJ.jpg',
            'backdrop_path' => 'https://image.tmdb.org/t/p/original/xOMo8BRK7PfcJv9JCnx7s5hj0PX.jpg',
            'trailer_url' => 'https://www.youtube.com/watch?v=Way9Dexny3w',
            'rating' => 8.6,
            'vote_count' => 12800,
            'runtime_minutes' => 166,
            'resolution' => '4K UHD HDR',
            'video_codec' => 'HEVC / H.265 (HDR10+)',
            'audio_codec' => 'Dolby Atmos TrueHD',
            'file_path' => 'C:/Media/Movies/Dune Part Two (2024)/Dune Part Two (2024) [4K UHD].mkv',
            'file_size_bytes' => 22100000000,
            'folder_path' => 'C:/Media/Movies/Dune Part Two (2024)',
            'mood_tags' => ['Epic Masterpiece', 'Desert Mysticism', 'Adrenaline', 'Sci-Fi Action'],
            'is_favorite' => true,
        ]);
        $dune2->genres()->sync([$genres['sci-fi']->id, $genres['adventure']->id, $genres['action']->id]);
        $dune2->people()->attach($peopleData['villeneuve']->id, ['role' => 'director', 'order' => 0]);
        $dune2->people()->attach($peopleData['chalamet']->id, ['role' => 'actor', 'character_name' => 'Paul Atreides', 'order' => 1]);
        $dune2->people()->attach($peopleData['zendaya']->id, ['role' => 'actor', 'character_name' => 'Chani', 'order' => 2]);

        $oppenheimer = MediaItem::create([
            'title' => 'Oppenheimer',
            'original_title' => 'Oppenheimer',
            'title_ar' => 'أوبنهايمر',
            'release_year' => 2023,
            'tmdb_id' => '872585',
            'imdb_id' => 'tt15398776',
            'overview' => 'The story of J. Robert Oppenheimer’s role in the development of the atomic bomb during World War II.',
            'overview_ar' => 'قصة الفيزيائي الأمريكي روبرت أوبنهايمر ودوره المحوري في تطوير القنبلة الذرية ضمن مشروع مانهاتن خلال الحرب العالمية الثانية.',
            'poster_path' => 'https://image.tmdb.org/t/p/w780/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg',
            'backdrop_path' => 'https://image.tmdb.org/t/p/original/fm6KqXpk3M2HVveHwCrBSSBaO0V.jpg',
            'trailer_url' => 'https://www.youtube.com/watch?v=uYPbbksJxIg',
            'rating' => 8.9,
            'vote_count' => 22000,
            'runtime_minutes' => 180,
            'resolution' => '4K IMAX',
            'video_codec' => 'HEVC / H.265',
            'audio_codec' => 'DTS-HD MA 5.1',
            'file_path' => 'C:/Media/Movies/Oppenheimer (2023)/Oppenheimer (2023) [4K].mkv',
            'file_size_bytes' => 19800000000,
            'folder_path' => 'C:/Media/Movies/Oppenheimer (2023)',
            'mood_tags' => ['Intense Drama', 'Historical', 'Philosophical', 'Award Winner'],
            'is_favorite' => true,
        ]);
        $oppenheimer->genres()->sync([$genres['drama']->id, $genres['history']->id]);
        $oppenheimer->people()->attach($peopleData['nolan']->id, ['role' => 'director', 'order' => 0]);
        $oppenheimer->people()->attach($peopleData['murphy']->id, ['role' => 'actor', 'character_name' => 'J. Robert Oppenheimer', 'order' => 1]);

        // 5. Sample Series: Breaking Bad
        $breakingBad = Series::create([
            'title' => 'Breaking Bad',
            'original_title' => 'Breaking Bad',
            'title_ar' => 'اختلال ضال',
            'release_year' => 2008,
            'end_year' => 2013,
            'tmdb_id' => '1396',
            'tvmaze_id' => '169',
            'imdb_id' => 'tt0903747',
            'overview' => 'A chemistry teacher diagnosed with inoperable lung cancer turns to manufacturing and selling methamphetamine with a former student in order to secure his family\'s future.',
            'overview_ar' => 'مدرس كيمياء في المدرسة الثانوية يُصاب بسرطان الرئة غير القابل للعلاج، فيتحول إلى تصنيع وبيع الميثامفيتامين مع طالب سابق لتأمين المستقبل المالي لعائلته قبل وفاته.',
            'poster_path' => 'https://image.tmdb.org/t/p/w780/ztkUQFLlC19CCMYHW9o1zWhJRNq.jpg',
            'backdrop_path' => 'https://image.tmdb.org/t/p/original/tsRy63Mu5cu8etL1X7ZLyf7UP1M.jpg',
            'trailer_url' => 'https://www.youtube.com/watch?v=HhesaQXLuRY',
            'rating' => 9.5,
            'status' => 'Ended',
            'network' => 'AMC',
            'folder_path' => 'C:/Media/TV Shows/Breaking Bad (2008)',
            'mood_tags' => ['Intense Drama', 'Crime Thriller', 'Masterpiece', 'Suspense'],
            'is_favorite' => true,
        ]);
        $breakingBad->genres()->sync([$genres['drama']->id, $genres['crime']->id, $genres['thriller']->id]);
        $breakingBad->people()->attach($peopleData['cranston']->id, ['role' => 'actor', 'character_name' => 'Walter White', 'order' => 0]);
        $breakingBad->people()->attach($peopleData['paul']->id, ['role' => 'actor', 'character_name' => 'Jesse Pinkman', 'order' => 1]);

        $bbSeason1 = Season::create([
            'series_id' => $breakingBad->id,
            'season_number' => 1,
            'title' => 'Season 1',
            'title_ar' => 'الموسم الأول',
            'overview' => 'High school chemistry teacher Walter White\'s life is suddenly turned on its head when diagnosed with stage III terminal lung cancer.',
            'poster_path' => 'https://image.tmdb.org/t/p/w500/1yeAQyvYcMY6FjC0N2Xz2w5e6.jpg',
            'air_date' => '2008-01-20',
        ]);

        $ep1 = Episode::create([
            'series_id' => $breakingBad->id,
            'season_id' => $bbSeason1->id,
            'episode_number' => 1,
            'title' => 'Pilot',
            'title_ar' => 'الحلقة الافتتاحية',
            'overview' => 'When an unassuming chemistry teacher is diagnosed with terminal cancer, he teams up with a former student to manufacture high-grade crystal meth.',
            'overview_ar' => 'عندما يتم تشخيص مدرس كيمياء غير معروف بسرطان عضال، يتعاون مع طالب سابق لتصنيع مخدر الميثامفيتامين عالي النقاء.',
            'still_path' => 'https://image.tmdb.org/t/p/w500/ydlY3iPbg5Lt0RYvU8kX6tQj8sH.jpg',
            'runtime_minutes' => 58,
            'air_date' => '2008-01-20',
            'rating' => 9.1,
            'resolution' => '1080p FHD',
            'video_codec' => 'H.264 / AVC',
            'audio_codec' => 'AAC 5.1',
            'file_path' => 'C:/Media/TV Shows/Breaking Bad (2008)/Season 01/Breaking Bad - S01E01 - Pilot [1080p].mkv',
            'file_size_bytes' => 2400000000,
        ]);

        $ep2 = Episode::create([
            'series_id' => $breakingBad->id,
            'season_id' => $bbSeason1->id,
            'episode_number' => 2,
            'title' => 'Cat\'s in the Bag...',
            'title_ar' => 'القطة في الحقيبة...',
            'overview' => 'Walt and Jesse attempt to dispose of two bodies in the RV, which becomes complicated when one of them awakes.',
            'overview_ar' => 'يحاول والت وجيسي التخلص من جثتين في الشاحنة، الأمر الذي يتعقد عندما يستيقظ أحدهما فجأة.',
            'still_path' => 'https://image.tmdb.org/t/p/w500/3r0p0qG2t7eF9.jpg',
            'runtime_minutes' => 48,
            'air_date' => '2008-01-27',
            'rating' => 8.7,
            'resolution' => '1080p FHD',
            'video_codec' => 'H.264 / AVC',
            'audio_codec' => 'AAC 5.1',
            'file_path' => 'C:/Media/TV Shows/Breaking Bad (2008)/Season 01/Breaking Bad - S01E02 - Cats in the Bag [1080p].mkv',
            'file_size_bytes' => 2100000000,
        ]);

        // 6. Sample Series: Stranger Things
        $strangerThings = Series::create([
            'title' => 'Stranger Things',
            'original_title' => 'Stranger Things',
            'title_ar' => 'أشياء غريبة',
            'release_year' => 2016,
            'tmdb_id' => '66732',
            'tvmaze_id' => '18054',
            'imdb_id' => 'tt4574334',
            'overview' => 'When a young boy vanishes, a small town uncovers a mystery involving secret experiments, terrifying supernatural forces and one strange little girl.',
            'overview_ar' => 'عندما يختفي صبي صغير، تكشف بلدة صغيرة عن لغز ينطوي على تجارب سرية وقوى خارقة مرعبة وفتاة صغيرة ذات قدرات غامضة.',
            'poster_path' => 'https://image.tmdb.org/t/p/w780/49WJfeN0moxb9IPfGn8AIqMGskD.jpg',
            'backdrop_path' => 'https://image.tmdb.org/t/p/original/56v2KjBlU4XaOv9rVYEQypROD7P.jpg',
            'trailer_url' => 'https://www.youtube.com/watch?v=b9EkMc79ZSU',
            'rating' => 8.7,
            'status' => 'Returning Series',
            'network' => 'Netflix',
            'folder_path' => 'C:/Media/TV Shows/Stranger Things (2016)',
            'mood_tags' => ['80s Nostalgia', 'Supernatural Mystery', 'Cozy Thrills', 'Sci-Fi'],
            'is_favorite' => true,
        ]);
        $strangerThings->genres()->sync([$genres['sci-fi']->id, $genres['mystery']->id, $genres['drama']->id]);
        $strangerThings->people()->attach($peopleData['milly']->id, ['role' => 'actor', 'character_name' => 'Eleven', 'order' => 0]);

        $stSeason1 = Season::create([
            'series_id' => $strangerThings->id,
            'season_number' => 1,
            'title' => 'Season 1',
            'title_ar' => 'الموسم الأول',
            'overview' => 'A young boy vanishes without a trace. As the town searches, a girl with unusual powers appears.',
            'poster_path' => 'https://image.tmdb.org/t/p/w500/rb1xK79M53UqG.jpg',
            'air_date' => '2016-07-15',
        ]);

        Episode::create([
            'series_id' => $strangerThings->id,
            'season_id' => $stSeason1->id,
            'episode_number' => 1,
            'title' => 'Chapter One: The Vanishing of Will Byers',
            'title_ar' => 'الفصل الأول: اختفاء ويل بايرز',
            'overview' => 'On his way home from a friend’s house, young Will sees something terrifying. Nearby, a sinister secret lurks in the depths of a government lab.',
            'overview_ar' => 'في طريقه إلى المنزل من منزل صديقه، يرى الشاب ويل شيئاً مرعباً، وعلى مقربة من هناك، يكمن سر شرير في أعماق مختبر حكومي سري.',
            'still_path' => 'https://image.tmdb.org/t/p/w500/k8n2f9V8m4a7.jpg',
            'runtime_minutes' => 49,
            'air_date' => '2016-07-15',
            'rating' => 8.8,
            'resolution' => '4K UHD HDR',
            'video_codec' => 'HEVC / H.265',
            'audio_codec' => 'Dolby Atmos',
            'file_path' => 'C:/Media/TV Shows/Stranger Things (2016)/Season 01/Stranger Things - S01E01 - Chapter One The Vanishing of Will Byers [4K].mkv',
            'file_size_bytes' => 4500000000,
        ]);

        // 7. Watch History (for Continue Watching banner)
        WatchHistory::create([
            'watchable_id' => $interstellar->id,
            'watchable_type' => MediaItem::class,
            'progress_seconds' => 4320, // 1h 12m
            'duration_seconds' => 10140, // 2h 49m
            'is_completed' => false,
            'last_watched_at' => now()->subHours(2),
        ]);

        WatchHistory::create([
            'watchable_id' => $ep1->id,
            'watchable_type' => Episode::class,
            'progress_seconds' => 1800, // 30m
            'duration_seconds' => 3480, // 58m
            'is_completed' => false,
            'last_watched_at' => now()->subDays(1),
        ]);
    }
}
