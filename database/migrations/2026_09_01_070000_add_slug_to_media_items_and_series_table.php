<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            if (! Schema::hasColumn('media_items', 'slug')) {
                $table->string('slug')->nullable()->index()->after('title');
            }
        });

        Schema::table('series', function (Blueprint $table) {
            if (! Schema::hasColumn('series', 'slug')) {
                $table->string('slug')->nullable()->index()->after('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            if (Schema::hasColumn('media_items', 'slug')) {
                $table->dropColumn('slug');
            }
        });

        Schema::table('series', function (Blueprint $table) {
            if (Schema::hasColumn('series', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }
};
