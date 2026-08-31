<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('movie');
            $table->string('title');
            $table->string('original_title')->nullable();
            $table->string('title_ar')->nullable();
            $table->integer('release_year')->nullable()->index();
            $table->string('tmdb_id')->nullable()->index();
            $table->string('imdb_id')->nullable()->index();
            $table->text('overview')->nullable();
            $table->text('overview_ar')->nullable();
            $table->string('tagline')->nullable();
            $table->string('tagline_ar')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->string('trailer_url')->nullable();
            $table->decimal('rating', 3, 1)->default(0)->index();
            $table->integer('vote_count')->default(0);
            $table->integer('runtime_minutes')->nullable();
            $table->string('resolution')->nullable()->index(); // 4K UHD, 1080p FHD, 720p HD
            $table->string('video_codec')->nullable(); // HEVC / H.265, H.264 / AVC, AV1
            $table->string('audio_codec')->nullable(); // Dolby Atmos, DTS-HD, AAC 5.1
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('folder_path')->nullable();
            $table->json('mood_tags')->nullable();
            $table->boolean('is_favorite')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('original_title')->nullable();
            $table->string('title_ar')->nullable();
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
            $table->string('folder_path')->nullable();
            $table->json('mood_tags')->nullable();
            $table->boolean('is_favorite')->default(false)->index();
            $table->timestamps();
        });

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
        });

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
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->timestamps();
        });

        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name_en')->unique();
            $table->string('name_ar');
            $table->string('slug')->unique();
            $table->integer('tmdb_genre_id')->nullable();
            $table->timestamps();
        });

        Schema::create('genreables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('genre_id')->constrained('genres')->cascadeOnDelete();
            $table->morphs('genreable');
            $table->timestamps();
        });

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

        Schema::create('personables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->morphs('personable');
            $table->string('role')->default('actor'); // actor, director, writer
            $table->string('character_name')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('subtitles', function (Blueprint $table) {
            $table->id();
            $table->morphs('subtitlable');
            $table->string('language', 10)->default('en')->index(); // 'en', 'ar'
            $table->string('language_name')->default('English');
            $table->string('format', 10)->default('srt');
            $table->string('file_path')->nullable();
            $table->boolean('is_embedded')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('watch_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->morphs('watchable');
            $table->integer('progress_seconds')->default(0);
            $table->integer('duration_seconds')->default(0);
            $table->boolean('is_completed')->default(false)->index();
            $table->timestamp('last_watched_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('download_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('media_type')->default('movie'); // movie, series, subtitle
            $table->text('source_url')->nullable();
            $table->string('destination_path')->nullable();
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->unsignedBigInteger('downloaded_bytes')->default(0);
            $table->string('status')->default('queued')->index(); // queued, downloading, completed, failed, paused
            $table->integer('speed_bytes_sec')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
        });
    }

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
