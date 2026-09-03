<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Episode extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'episode_number' => 'integer',
        'runtime_minutes' => 'integer',
        'rating' => 'float',
        'air_date' => 'date',
        'file_size_bytes' => 'integer',
        'video_bitrate' => 'integer',
        'audio_channels' => 'integer',
        'audio_bitrate' => 'integer',
        'framerate' => 'decimal:2',
        'total_bitrate' => 'integer',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function subtitles(): MorphMany
    {
        return $this->morphMany(Subtitle::class, 'subtitlable');
    }

    public function watchHistories(): MorphMany
    {
        return $this->morphMany(WatchHistory::class, 'watchable');
    }
}
