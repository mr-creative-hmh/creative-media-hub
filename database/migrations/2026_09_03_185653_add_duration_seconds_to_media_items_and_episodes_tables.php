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
        if (Schema::hasTable('media_items') && ! Schema::hasColumn('media_items', 'duration_seconds')) {
            Schema::table('media_items', function (Blueprint $table) {
                $table->unsignedInteger('duration_seconds')->nullable()->after('runtime_minutes');
            });
        }

        if (Schema::hasTable('episodes') && ! Schema::hasColumn('episodes', 'duration_seconds')) {
            Schema::table('episodes', function (Blueprint $table) {
                $table->unsignedInteger('duration_seconds')->nullable()->after('runtime_minutes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('media_items') && Schema::hasColumn('media_items', 'duration_seconds')) {
            Schema::table('media_items', function (Blueprint $table) {
                $table->dropColumn('duration_seconds');
            });
        }

        if (Schema::hasTable('episodes') && Schema::hasColumn('episodes', 'duration_seconds')) {
            Schema::table('episodes', function (Blueprint $table) {
                $table->dropColumn('duration_seconds');
            });
        }
    }
};
