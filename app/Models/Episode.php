<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $series_id
 * @property int $season_id
 * @property int $episode_number
 * @property string $title
 * @property ?string $title_ar
 * @property ?string $overview
 * @property ?string $overview_ar
 * @property ?string $still_path
 * @property ?int $runtime_minutes
 * @property ?CarbonImmutable $air_date
 * @property ?float $rating
 * @property ?string $resolution
 * @property ?string $video_codec
 * @property ?string $audio_codec
 * @property ?string $file_path
 * @property ?int $file_size_bytes
 * @property ?string $video_profile
 * @property int $video_bitrate
 * @property int $audio_channels
 * @property ?string $audio_channel_layout
 * @property int $audio_bitrate
 * @property ?string $container_format
 * @property ?string $framerate
 * @property ?string $hdr_format
 * @property ?string $color_space
 * @property ?string $color_transfer
 * @property int $total_bitrate
 * @property ?int $duration_seconds
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read ?Series $series
 * @property-read ?Season $season
 * @property-read Collection<int, Subtitle> $subtitles
 * @property-read Collection<int, WatchHistory> $watchHistories
 * @property-read int $season_number
 * @property-read ?string $clean_episode_title
 * @property-read ?string $clean_episode_title_ar
 * @property-read string $formatted_title
 * @property-read string $formatted_title_ar
 * @property-read string $formatted_season_episode_title
 */
class Episode extends Model
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
    }

    /**
     * @return BelongsTo<Series, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return MorphMany<Subtitle, $this>
     */
    public function subtitles(): MorphMany
    {
        return $this->morphMany(Subtitle::class, 'subtitlable');
    }

    /**
     * @return MorphMany<WatchHistory, $this>
     */
    public function watchHistories(): MorphMany
    {
        return $this->morphMany(WatchHistory::class, 'watchable');
    }

    /**
     * @return Attribute<int, never>
     */
    protected function seasonNumber(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->season ? $this->season->season_number : 1,
        );
    }

    /**
     * Get clean episode title without generic placeholder names like "Episode 1".
     *
     * @return Attribute<?string, never>
     */
    protected function cleanEpisodeTitle(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $raw = trim($this->title ?? '');
                if (empty($raw) || preg_match('/^(?:Episode|Ep|Part|الحلقة)\s*\d+$/i', $raw) || preg_match('/^S\d+E\d+$/i', $raw)) {
                    return null;
                }

                return $raw;
            },
        );
    }

    /**
     * Get clean Arabic episode title without generic placeholder names.
     *
     * @return Attribute<?string, never>
     */
    protected function cleanEpisodeTitleAr(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $raw = trim($this->title_ar ?? '');
                if (empty($raw) || preg_match('/^(?:Episode|Ep|Part|الحلقة)\s*\d+$/i', $raw) || preg_match('/^S\d+E\d+$/i', $raw)) {
                    return null;
                }

                return $raw;
            },
        );
    }

    /**
     * Standardized formatted title:
     * "TV show [name] - Season [Number] - Episode [Number] - [Episode title if available]"
     *
     * @return Attribute<string, never>
     */
    protected function formattedTitle(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $seriesTitle = $this->series ? $this->series->title : 'Series';
                $seasonNum = $this->season_number;
                $epNum = $this->episode_number;
                $cleanTitle = $this->clean_episode_title;

                $base = "{$seriesTitle} - Season {$seasonNum} - Episode {$epNum}";

                return $cleanTitle ? "{$base} - {$cleanTitle}" : $base;
            },
        );
    }

    /**
     * Standardized formatted title in Arabic:
     * "[اسم المسلسل] - الموسم [الرقم] - الحلقة [الرقم] - [عنوان الحلقة إذا توفر]"
     *
     * @return Attribute<string, never>
     */
    protected function formattedTitleAr(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $seriesTitle = $this->series?->title_ar ?: ($this->series ? $this->series->title : 'مسلسل');
                $seasonNum = $this->season_number;
                $epNum = $this->episode_number;
                $cleanTitle = $this->clean_episode_title_ar ?: $this->clean_episode_title;

                $base = "{$seriesTitle} - الموسم {$seasonNum} - الحلقة {$epNum}";

                return $cleanTitle ? "{$base} - {$cleanTitle}" : $base;
            },
        );
    }

    /**
     * Standardized season & episode title:
     * "Season [Number] - Episode [Number] - [Episode title if available]"
     *
     * @return Attribute<string, never>
     */
    protected function formattedSeasonEpisodeTitle(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $seasonNum = $this->season_number;
                $epNum = $this->episode_number;
                $cleanTitle = $this->clean_episode_title;

                $base = "Season {$seasonNum} - Episode {$epNum}";

                return $cleanTitle ? "{$base} - {$cleanTitle}" : $base;
            },
        );
    }
}
