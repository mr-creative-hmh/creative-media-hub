<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Duplicate and redundant prefix indexes to drop: table => list of index names.
     *
     * @var array<string, list<string>>
     */
    protected array $indexesToDrop = [
        'media_items' => [
            'idx_media_items_collection',
            'idx_media_items_file_path',
            'media_items_rating_index',
            'media_items_original_language_index',
        ],
        'episodes' => [
            'idx_episodes_file_path',
        ],
        'series' => [
            'idx_series_rating',
            'series_original_language_index',
        ],
        'subtitles' => [
            'subtitles_subtitlable_type_subtitlable_id_index',
        ],
        'watch_histories' => [
            'watch_histories_watchable_type_watchable_id_index',
        ],
    ];

    /**
     * Missing hot-query indexes to add: table => [index_name => columns].
     *
     * @var array<string, array<string, list<string>>>
     */
    protected array $indexesToAdd = [
        'personables' => [
            'idx_personables_person_id' => ['person_id'],
        ],
        'watch_histories' => [
            'idx_watch_histories_completed_last_watched' => ['is_completed', 'last_watched_at'],
        ],
        'media_items' => [
            'idx_media_items_created_at' => ['created_at'],
            'idx_media_items_title' => ['title'],
            'idx_media_items_file_size_bytes' => ['file_size_bytes'],
        ],
        'series' => [
            'idx_series_created_at' => ['created_at'],
            'idx_series_title' => ['title'],
        ],
        'episodes' => [
            'idx_episodes_file_size_bytes' => ['file_size_bytes'],
        ],
        'subtitles' => [
            'idx_subtitles_file_path' => ['file_path'],
        ],
        'download_items' => [
            'idx_download_items_created_at' => ['created_at'],
            'idx_download_items_organized_path' => ['organized_path'],
            'idx_download_items_indexed_id' => ['indexed_id'],
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->indexesToDrop as $tableName => $indexNames) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexNames as $indexName) {
                if (Schema::hasIndex($tableName, $indexName)) {
                    Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                        $table->dropIndex($indexName);
                    });
                }
            }
        }

        foreach ($this->indexesToAdd as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $indexName => $columns) {
                if (! Schema::hasIndex($tableName, $indexName)) {
                    Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
                        $table->index($columns, $indexName);
                    });
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->indexesToAdd as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach (array_keys($indexes) as $indexName) {
                if (Schema::hasIndex($tableName, $indexName)) {
                    Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                        $table->dropIndex($indexName);
                    });
                }
            }
        }

        $restorableIndexes = [
            'media_items' => [
                'idx_media_items_collection' => ['collection_name'],
                'idx_media_items_file_path' => ['file_path'],
                'media_items_rating_index' => ['rating'],
                'media_items_original_language_index' => ['original_language'],
            ],
            'episodes' => [
                'idx_episodes_file_path' => ['file_path'],
            ],
            'series' => [
                'idx_series_rating' => ['rating'],
                'series_original_language_index' => ['original_language'],
            ],
            'subtitles' => [
                'subtitles_subtitlable_type_subtitlable_id_index' => ['subtitlable_type', 'subtitlable_id'],
            ],
            'watch_histories' => [
                'watch_histories_watchable_type_watchable_id_index' => ['watchable_type', 'watchable_id'],
            ],
        ];

        foreach ($restorableIndexes as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $indexName => $columns) {
                if (! Schema::hasIndex($tableName, $indexName)) {
                    Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
                        $table->index($columns, $indexName);
                    });
                }
            }
        }
    }
};
