<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('download_items', function (Blueprint $table) {
            if (! Schema::hasColumn('download_items', 'download_type')) {
                $table->string('download_type')->default('direct')->after('media_type'); // direct, torrent
            }
            if (! Schema::hasColumn('download_items', 'destination_folder')) {
                $table->string('destination_folder')->nullable()->after('destination_path');
            }
            if (! Schema::hasColumn('download_items', 'torrent_files')) {
                $table->json('torrent_files')->nullable()->after('destination_folder');
            }
            if (! Schema::hasColumn('download_items', 'selected_files')) {
                $table->json('selected_files')->nullable()->after('torrent_files');
            }
            if (! Schema::hasColumn('download_items', 'info_hash')) {
                $table->string('info_hash')->nullable()->after('selected_files');
            }
        });
    }

    public function down(): void
    {
        Schema::table('download_items', function (Blueprint $table) {
            $table->dropColumn(['download_type', 'destination_folder', 'torrent_files', 'selected_files', 'info_hash']);
        });
    }
};
