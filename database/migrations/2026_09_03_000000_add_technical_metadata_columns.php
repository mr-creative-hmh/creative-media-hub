<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            // Video technical metadata
            $table->string('video_profile')->nullable()->after('video_codec'); // Main, High, High 10, etc.
            $table->unsignedBigInteger('video_bitrate')->default(0)->after('video_profile');

            // Audio technical metadata
            $table->unsignedSmallInteger('audio_channels')->default(0)->after('audio_codec'); // 2, 6, 8
            $table->string('audio_channel_layout')->nullable()->after('audio_channels'); // stereo, 5.1, 7.1, 7.1.2
            $table->unsignedBigInteger('audio_bitrate')->default(0)->after('audio_channel_layout');

            // Container & stream info
            $table->string('container_format')->nullable()->after('audio_bitrate'); // matroska, mp4, etc.
            $table->decimal('framerate', 5, 2)->nullable()->after('container_format'); // 23.976, 24, 25, 29.97, 30, 59.94, 60

            // HDR & Color metadata
            $table->string('hdr_format')->nullable()->after('framerate'); // HDR10, Dolby Vision, HLG, HDR10+
            $table->string('color_space')->nullable()->after('hdr_format'); // bt2020, bt709, etc.
            $table->string('color_transfer')->nullable()->after('color_space'); // smpte2084, arib-std-b67, bt709

            // Overall bitrate
            $table->unsignedBigInteger('total_bitrate')->default(0)->after('color_transfer');
        });

        Schema::table('episodes', function (Blueprint $table) {
            // Video technical metadata
            $table->string('video_profile')->nullable()->after('video_codec');
            $table->unsignedBigInteger('video_bitrate')->default(0)->after('video_profile');

            // Audio technical metadata
            $table->unsignedSmallInteger('audio_channels')->default(0)->after('audio_codec');
            $table->string('audio_channel_layout')->nullable()->after('audio_channels');
            $table->unsignedBigInteger('audio_bitrate')->default(0)->after('audio_channel_layout');

            // Container & stream info
            $table->string('container_format')->nullable()->after('audio_bitrate');
            $table->decimal('framerate', 5, 2)->nullable()->after('container_format');

            // HDR & Color metadata
            $table->string('hdr_format')->nullable()->after('framerate');
            $table->string('color_space')->nullable()->after('hdr_format');
            $table->string('color_transfer')->nullable()->after('color_space');

            // Overall bitrate
            $table->unsignedBigInteger('total_bitrate')->default(0)->after('color_transfer');
        });
    }

    public function down(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->dropColumn([
                'video_profile',
                'video_bitrate',
                'audio_channels',
                'audio_channel_layout',
                'audio_bitrate',
                'container_format',
                'framerate',
                'hdr_format',
                'color_space',
                'color_transfer',
                'total_bitrate',
            ]);
        });

        Schema::table('episodes', function (Blueprint $table) {
            $table->dropColumn([
                'video_profile',
                'video_bitrate',
                'audio_channels',
                'audio_channel_layout',
                'audio_bitrate',
                'container_format',
                'framerate',
                'hdr_format',
                'color_space',
                'color_transfer',
                'total_bitrate',
            ]);
        });
    }
};
