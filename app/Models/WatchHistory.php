<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property ?int $user_id
 * @property string $watchable_type
 * @property int $watchable_id
 * @property int $progress_seconds
 * @property int $duration_seconds
 * @property bool $is_completed
 * @property CarbonImmutable $last_watched_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read ?Model $watchable
 * @property-read ?MediaItem $mediaItem
 * @property-read ?Episode $episode
 * @property-read int $progress_percentage
 */
class WatchHistory extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'progress_seconds' => 'integer',
            'duration_seconds' => 'integer',
            'is_completed' => 'boolean',
            'last_watched_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function watchable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<MediaItem, $this>
     */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'watchable_id');
    }

    /**
     * @return BelongsTo<Episode, $this>
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class, 'watchable_id');
    }

    /**
     * @return Attribute<int, never>
     */
    protected function progressPercentage(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                if ($this->duration_seconds > 0) {
                    return (int) round(($this->progress_seconds / $this->duration_seconds) * 100);
                }

                return 0;
            },
        );
    }
}
