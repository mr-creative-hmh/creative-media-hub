<?php

use App\Models\MediaItem;
use App\Models\Series;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Backfill folder_path for Series
        $seriesList = Series::with('episodes')->get();
        foreach ($seriesList as $series) {
            if (! empty($series->folder_path)) {
                continue;
            }

            $firstEp = $series->episodes->first();
            if ($firstEp && ! empty($firstEp->file_path)) {
                $epPath = str_replace('\\', '/', $firstEp->file_path);
                $epDir = dirname($epPath);
                $epDirBase = strtolower(basename($epDir));

                // If inside Season folder (e.g. Season 01, S01, Specials), series folder is one level up
                if (preg_match('/^(season\s*\d+|s\d+|specials?)$/i', $epDirBase)) {
                    $seriesFolder = dirname($epDir);
                } else {
                    $seriesFolder = $epDir;
                }

                $series->folder_path = $seriesFolder;
                $series->save();
            }
        }

        // 2. Backfill folder_path for MediaItem (movies)
        $mediaItems = MediaItem::whereNull('folder_path')->orWhere('folder_path', '')->get();
        foreach ($mediaItems as $item) {
            if (! empty($item->file_path)) {
                $item->folder_path = str_replace('\\', '/', dirname($item->file_path));
                $item->save();
            }
        }
    }

    public function down(): void
    {
        // No-op for data backfill
    }
};
