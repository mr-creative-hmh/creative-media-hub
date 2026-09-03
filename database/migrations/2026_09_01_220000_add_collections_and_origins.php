<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            if (! Schema::hasColumn('media_items', 'collection_name')) {
                $table->string('collection_name')->nullable()->index();
            }
            if (! Schema::hasColumn('media_items', 'collection_id')) {
                $table->unsignedBigInteger('collection_id')->nullable()->index();
            }
            if (! Schema::hasColumn('media_items', 'collection_poster')) {
                $table->string('collection_poster')->nullable();
            }
            if (! Schema::hasColumn('media_items', 'original_language')) {
                $table->string('original_language', 10)->nullable()->index();
            }
            if (! Schema::hasColumn('media_items', 'origin_country')) {
                $table->string('origin_country', 10)->nullable()->index();
            }
        });

        Schema::table('series', function (Blueprint $table) {
            if (! Schema::hasColumn('series', 'original_language')) {
                $table->string('original_language', 10)->nullable()->index();
            }
            if (! Schema::hasColumn('series', 'origin_country')) {
                $table->string('origin_country', 10)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->dropColumn(['collection_name', 'collection_id', 'collection_poster', 'original_language', 'origin_country']);
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn(['original_language', 'origin_country']);
        });
    }
};
