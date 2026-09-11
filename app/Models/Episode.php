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
        'duration_seconds' => 'integer',
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

        public function getSeasonNumberAttribute(): int
    {
        return $this->season?->season_number ?? 1;
    }

    /**
     * Get clean episode title without generic placeholder names like "Episode 1".
     */
    public function getCleanEpisodeTitleAttribute(): ?string
    {
        $raw = trim($this->title ?? '');
        if (empty($raw) || preg_match('/^(?:Episode|Ep|Part|الحلقة)\s*\d+$/i', $raw) || preg_match('/^S\d+E\d+$/i', $raw)) {
            return null;
        }
        return $raw;
    }

    /**
     * Get clean Arabic episode title without generic placeholder names.
     */
    public function getCleanEpisodeTitleArAttribute(): ?string
    {
        $raw = trim($this->title_ar ?? '');
        if (empty($raw) || preg_match('/^(?:Episode|Ep|Part|الحلقة)\s*\d+$/i', $raw) || preg_match('/^S\d+E\d+$/i', $raw)) {
            return null;
        }
        return $raw;
    }

    /**
     * Standardized formatted title:
     * "TV show [name] - Season [Number] - Episode [Number] - [Episode title if available]"
     */
    public function getFormattedTitleAttribute(): string
    {
        $seriesTitle = $this->series?->title ?? 'Series';
        $seasonNum = $this->season_number;
        $epNum = $this->episode_number;
        $cleanTitle = $this->clean_episode_title;

        $base = "{$seriesTitle} - Season {$seasonNum} - Episode {$epNum}";
        return $cleanTitle ? "{$base} - {$cleanTitle}" : $base;
    }

    /**
     * Standardized formatted title in Arabic:
     * "[اسم المسلسل] - الموسم [الرقم] - الحلقة [الرقم] - [عنوان الحلقة إذا توفر]"
     */
    public function getFormattedTitleArAttribute(): string
    {
        $seriesTitle = $this->series?->title_ar ?: ($this->series?->title ?? 'مسلسل');
        $seasonNum = $this->season_number;
        $epNum = $this->episode_number;
        $cleanTitle = $this->clean_episode_title_ar ?: $this->clean_episode_title;

        $base = "{$seriesTitle} - الموسم {$seasonNum} - الحلقة {$epNum}";
        return $cleanTitle ? "{$base} - {$cleanTitle}" : $base;
    }

    /**
     * Standardized season & episode title:
     * "Season [Number] - Episode [Number] - [Episode title if available]"
     */
    public function getFormattedSeasonEpisodeTitleAttribute(): string
    {
        $seasonNum = $this->season_number;
        $epNum = $this->episode_number;
        $cleanTitle = $this->clean_episode_title;

        $base = "Season {$seasonNum} - Episode {$epNum}";
        return $cleanTitle ? "{$base} - {$cleanTitle}" : $base;
    }
}
