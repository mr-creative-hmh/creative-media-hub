<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DownloadItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'total_bytes' => 'integer',
        'downloaded_bytes' => 'integer',
        'speed_bytes_sec' => 'integer',
    ];
}
