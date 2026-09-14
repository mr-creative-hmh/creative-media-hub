<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'total_bytes' => 'integer',
        'downloaded_bytes' => 'integer',
        'speed_bytes_sec' => 'integer',
        'num_seeders' => 'integer',
        'connections' => 'integer',
        'upload_speed_bytes_sec' => 'integer',
        'indexed_id' => 'integer',
        'torrent_files' => 'array',
        'selected_files' => 'array',
    ];

    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'indexed_id');
    }
}
