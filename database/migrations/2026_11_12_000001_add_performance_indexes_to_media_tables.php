<?php

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
        Schema::table('media_items', function (Blueprint $table) {
            $table->index('collection_name', 'idx_media_items_collection');
            $table->index(['rating', 'release_year'], 'idx_media_items_rating_release');
            $table->index(['original_language', 'origin_country'], 'idx_media_items_lang_country');
            $table->index('file_path', 'idx_media_items_file_path');
        });

        Schema::table('episodes', function (Blueprint $table) {
            $table->index(['series_id', 'season_id', 'episode_number'], 'idx_episodes_series_season_ep');
            $table->index('file_path', 'idx_episodes_file_path');
        });

        Schema::table('series', function (Blueprint $table) {
            $table->index('rating', 'idx_series_rating');
            $table->index(['original_language', 'origin_country'], 'idx_series_lang_country');
        });

        Schema::table('subtitles', function (Blueprint $table) {
            $table->index(['subtitlable_type', 'subtitlable_id', 'language'], 'idx_subtitles_polymorphic_lang');
        });

        Schema::table('watch_histories', function (Blueprint $table) {
            $table->index(['user_id', 'watchable_type', 'watchable_id'], 'idx_watch_histories_user_watchable');
            $table->index(['is_completed', 'progress_seconds'], 'idx_watch_histories_completed_progress');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->dropIndex('idx_media_items_collection');
            $table->dropIndex('idx_media_items_rating_release');
            $table->dropIndex('idx_media_items_lang_country');
            $table->dropIndex('idx_media_items_file_path');
        });

        Schema::table('episodes', function (Blueprint $table) {
            $table->dropIndex('idx_episodes_series_season_ep');
            $table->dropIndex('idx_episodes_file_path');
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropIndex('idx_series_rating');
            $table->dropIndex('idx_series_lang_country');
        });

        Schema::table('subtitles', function (Blueprint $table) {
            $table->dropIndex('idx_subtitles_polymorphic_lang');
        });

        Schema::table('watch_histories', function (Blueprint $table) {
            $table->dropIndex('idx_watch_histories_user_watchable');
            $table->dropIndex('idx_watch_histories_completed_progress');
        });
    }
};
