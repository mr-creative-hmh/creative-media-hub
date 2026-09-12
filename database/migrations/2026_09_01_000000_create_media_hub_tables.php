<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Media Items (Movies & Standalone Media)
        if (! Schema::hasTable('media_items')) {
            Schema::create('media_items', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('original_title')->nullable();
                $table->string('title_ar')->nullable();
                $table->string('slug')->nullable()->index();
                $table->integer('release_year')->nullable()->index();
                $table->string('tmdb_id')->nullable()->index();
                $table->string('imdb_id')->nullable()->index();
                $table->text('overview')->nullable();
                $table->text('overview_ar')->nullable();
                $table->string('poster_path')->nullable();
                $table->string('backdrop_path')->nullable();
                $table->string('trailer_url')->nullable();
                $table->decimal('rating', 3, 1)->default(0)->index();
                $table->integer('vote_count')->default(0);
                $table->integer('runtime_minutes')->nullable();
                $table->string('resolution')->nullable()->index(); // 4K UHD, 1080p, 720p
                $table->string('video_codec')->nullable();        // HEVC/H.265, AVC/H.264, AV1
                $table->string('audio_codec')->nullable();        // AAC, AC3, EAC3, TrueHD, DTS
                $table->string('file_path')->nullable()->index();
                $table->unsignedBigInteger('file_size_bytes')->nullable();
                $table->string('folder_path')->nullable()->index();
                $table->text('mood_tags')->nullable();
                $table->boolean('is_favorite')->default(false)->index();
                $table->string('collection_name')->nullable()->index();
                $table->unsignedBigInteger('collection_id')->nullable()->index();
                $table->string('collection_id_source')->nullable()->index();
                $table->string('collection_poster')->nullable();
                $table->string('original_language')->nullable()->index();
                $table->string('origin_country')->nullable();
                $table->string('video_profile')->nullable();
                $table->unsignedInteger('video_bitrate')->default(0);
                $table->unsignedSmallInteger('audio_channels')->default(0);
                $table->string('audio_channel_layout')->nullable();
                $table->unsignedInteger('audio_bitrate')->default(0);
                $table->string('container_format')->nullable();
                $table->decimal('framerate', 5, 2)->nullable();
                $table->string('hdr_format')->nullable();
                $table->string('color_space')->nullable();
                $table->string('color_transfer')->nullable();
                $table->unsignedInteger('total_bitrate')->default(0);
                $table->integer('duration_seconds')->nullable();
                $table->timestamps();
            });
        }

        // 2. Series (TV Shows, Anime, Documentaries)
        if (! Schema::hasTable('series')) {
            Schema::create('series', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('original_title')->nullable();
                $table->string('title_ar')->nullable();
                $table->string('slug')->nullable()->index();
                $table->integer('release_year')->nullable()->index();
                $table->integer('end_year')->nullable();
                $table->string('tmdb_id')->nullable()->index();
                $table->string('tvmaze_id')->nullable()->index();
                $table->string('imdb_id')->nullable()->index();
                $table->text('overview')->nullable();
                $table->text('overview_ar')->nullable();
                $table->string('poster_path')->nullable();
                $table->string('backdrop_path')->nullable();
                $table->string('trailer_url')->nullable();
                $table->decimal('rating', 3, 1)->default(0)->index();
                $table->string('status')->default('Returning Series');
                $table->string('network')->nullable();
                $table->string('folder_path')->nullable()->index();
                $table->text('mood_tags')->nullable();
                $table->boolean('is_favorite')->default(false)->index();
                $table->string('original_language')->nullable()->index();
                $table->string('origin_country')->nullable();
                $table->timestamps();
            });
        }

        // 3. Seasons
        if (! Schema::hasTable('seasons')) {
            Schema::create('seasons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
                $table->integer('season_number')->index();
                $table->string('title')->nullable();
                $table->string('title_ar')->nullable();
                $table->text('overview')->nullable();
                $table->string('poster_path')->nullable();
                $table->date('air_date')->nullable();
                $table->timestamps();

                $table->unique(['series_id', 'season_number']);
            });
        }

        // 4. Episodes
        if (! Schema::hasTable('episodes')) {
            Schema::create('episodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
                $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
                $table->integer('episode_number')->index();
                $table->string('title');
                $table->string('title_ar')->nullable();
                $table->text('overview')->nullable();
                $table->text('overview_ar')->nullable();
                $table->string('still_path')->nullable();
                $table->integer('runtime_minutes')->nullable();
                $table->date('air_date')->nullable();
                $table->decimal('rating', 3, 1)->nullable();
                $table->string('resolution')->nullable();
                $table->string('video_codec')->nullable();
                $table->string('audio_codec')->nullable();
                $table->string('file_path')->nullable()->index();
                $table->unsignedBigInteger('file_size_bytes')->nullable();
                $table->string('video_profile')->nullable();
                $table->unsignedInteger('video_bitrate')->default(0);
                $table->unsignedSmallInteger('audio_channels')->default(0);
                $table->string('audio_channel_layout')->nullable();
                $table->unsignedInteger('audio_bitrate')->default(0);
                $table->string('container_format')->nullable();
                $table->decimal('framerate', 5, 2)->nullable();
                $table->string('hdr_format')->nullable();
                $table->string('color_space')->nullable();
                $table->string('color_transfer')->nullable();
                $table->unsignedInteger('total_bitrate')->default(0);
                $table->integer('duration_seconds')->nullable();
                $table->timestamps();

                $table->unique(['season_id', 'episode_number']);
            });
        }

        // 5. Genres
        if (! Schema::hasTable('genres')) {
            Schema::create('genres', function (Blueprint $table) {
                $table->id();
                $table->string('name_en');
                $table->string('name_ar');
                $table->string('slug')->unique();
                $table->integer('tmdb_genre_id')->nullable()->index();
                $table->timestamps();
            });
        }

        // 6. Polymorphic Genre Relations (genreables)
        if (! Schema::hasTable('genreables')) {
            Schema::create('genreables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('genre_id')->constrained('genres')->cascadeOnDelete();
                $table->morphs('genreable');
                $table->timestamps();

                $table->unique(['genre_id', 'genreable_type', 'genreable_id']);
            });
        }

        // 7. People (Cast & Crew)
        if (! Schema::hasTable('people')) {
            Schema::create('people', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('name_ar')->nullable();
                $table->string('profile_path')->nullable();
                $table->string('tmdb_id')->nullable()->index();
                $table->string('known_for_department')->default('Acting');
                $table->text('biography')->nullable();
                $table->timestamps();
            });
        }

        // 8. Polymorphic Person Relations (personables)
        if (! Schema::hasTable('personables')) {
            Schema::create('personables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
                $table->morphs('personable');
                $table->string('role')->default('actor');
                $table->string('character_name')->nullable();
                $table->integer('order')->default(0);
                $table->timestamps();
            });
        }

        // 9. Subtitles (SRT, VTT, Embedded)
        if (! Schema::hasTable('subtitles')) {
            Schema::create('subtitles', function (Blueprint $table) {
                $table->id();
                $table->morphs('subtitlable');
                $table->string('language', 10)->default('en');
                $table->string('language_name', 50)->default('English');
                $table->string('format', 10)->default('srt');
                $table->string('file_path')->nullable();
                $table->boolean('is_embedded')->default(false);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        // 10. Watch Histories (Playback Progress)
        if (! Schema::hasTable('watch_histories')) {
            Schema::create('watch_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->morphs('watchable');
                $table->integer('progress_seconds')->default(0);
                $table->integer('duration_seconds')->default(0);
                $table->boolean('is_completed')->default(false)->index();
                $table->timestamp('last_watched_at')->useCurrent();
                $table->timestamps();

                $table->unique(['watchable_type', 'watchable_id'], 'watch_histories_watchable_unique');
            });
        }

        // 11. Download Items (Direct & Torrent Downloads)
        if (! Schema::hasTable('download_items')) {
            Schema::create('download_items', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('media_type')->default('movie');
                $table->text('source_url')->nullable();
                $table->string('destination_path')->nullable();
                $table->unsignedBigInteger('total_bytes')->default(0);
                $table->unsignedBigInteger('downloaded_bytes')->default(0);
                $table->string('status')->default('queued')->index();
                $table->unsignedBigInteger('speed_bytes_sec')->default(0);
                $table->text('error_message')->nullable();
                $table->string('download_type')->default('direct');
                $table->string('destination_folder')->nullable();
                $table->text('torrent_files')->nullable();
                $table->text('selected_files')->nullable();
                $table->string('info_hash')->nullable()->index();
                $table->string('aria2_gid')->nullable()->index();
                $table->timestamps();
            });
        }

        // 12. App Settings
        if (! Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->default('string');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('download_items');
        Schema::dropIfExists('watch_histories');
        Schema::dropIfExists('subtitles');
        Schema::dropIfExists('personables');
        Schema::dropIfExists('people');
        Schema::dropIfExists('genreables');
        Schema::dropIfExists('genres');
        Schema::dropIfExists('episodes');
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('series');
        Schema::dropIfExists('media_items');
    }
};
