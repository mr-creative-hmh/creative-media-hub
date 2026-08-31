<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WatchHistory extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'progress_seconds' => 'integer',
        'duration_seconds' => 'integer',
        'is_completed' => 'boolean',
        'last_watched_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function watchable(): MorphTo
    {
        return $this->morphTo();
    }

    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'watchable_id');
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class, 'watchable_id');
    }

    public function getProgressPercentageAttribute(): int
    {
        if ($this->duration_seconds > 0) {
            return (int) round(($this->progress_seconds / $this->duration_seconds) * 100);
        }

        return 0;
    }
}
