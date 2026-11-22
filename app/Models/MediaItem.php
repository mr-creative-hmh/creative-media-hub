<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property ?string $original_title
 * @property ?string $title_ar
 * @property ?string $slug
 * @property ?int $release_year
 * @property ?string $tmdb_id
 * @property ?string $imdb_id
 * @property ?string $overview
 * @property ?string $overview_ar
 * @property ?string $poster_path
 * @property ?string $backdrop_path
 * @property ?string $trailer_url
 * @property float $rating
 * @property int $vote_count
 * @property ?int $runtime_minutes
 * @property ?string $resolution
 * @property ?string $video_codec
 * @property ?string $audio_codec
 * @property ?string $file_path
 * @property ?int $file_size_bytes
 * @property ?string $folder_path
 * @property ?array<int, string> $mood_tags
 * @property bool $is_favorite
 * @property ?string $collection_name
 * @property ?int $collection_id
 * @property ?string $collection_id_source
 * @property ?string $collection_poster
 * @property ?string $original_language
 * @property ?string $origin_country
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
 * @property-read Collection<int, Genre> $genres
 * @property-read Collection<int, Person> $people
 * @property-read Collection<int, Person> $actors
 * @property-read Collection<int, Person> $directors
 * @property-read Collection<int, Subtitle> $subtitles
 * @property-read Collection<int, WatchHistory> $watchHistories
 */
class MediaItem extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if (empty($model->slug) && ! empty($model->title)) {
                $base = Str::slug($model->title.($model->release_year ? " {$model->release_year}" : ''));
                $model->slug = $base ?: 'movie-'.uniqid();
            }
        });

        static::saved(function (): void {
            Cache::forget('movies.hero_items.v4');
            Cache::forget('movies.hero_items.v3');
            Cache::forget('movies.hero_items.v2');
        });

        static::deleted(function (): void {
            Cache::forget('movies.hero_items.v4');
            Cache::forget('movies.hero_items.v3');
            Cache::forget('movies.hero_items.v2');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'release_year' => 'integer',
            'rating' => 'float',
            'vote_count' => 'integer',
            'runtime_minutes' => 'integer',
            'duration_seconds' => 'integer',
            'file_size_bytes' => 'integer',
            'mood_tags' => 'array',
            'is_favorite' => 'boolean',
            'video_bitrate' => 'integer',
            'audio_channels' => 'integer',
            'audio_bitrate' => 'integer',
            'framerate' => 'decimal:2',
            'total_bitrate' => 'integer',
        ];
    }

    /**
     * @return MorphToMany<Genre, $this>
     */
    public function genres(): MorphToMany
    {
        return $this->morphToMany(Genre::class, 'genreable');
    }

    /**
     * @return MorphToMany<Person, $this>
     */
    public function people(): MorphToMany
    {
        return $this->morphToMany(Person::class, 'personable')
            ->withPivot(['role', 'character_name', 'order'])
            ->orderBy('order');
    }

    /**
     * @return MorphToMany<Person, $this>
     */
    public function actors(): MorphToMany
    {
        return $this->people()->wherePivot('role', 'actor');
    }

    /**
     * @return MorphToMany<Person, $this>
     */
    public function directors(): MorphToMany
    {
        return $this->people()->wherePivot('role', 'director');
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
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeMovies(Builder $query): Builder
    {
        return $query;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFavorites(Builder $query): Builder
    {
        return $query->where('is_favorite', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeByGenre(Builder $query, string $genreSlug): Builder
    {
        return $query->whereHas('genres', fn ($q) => $q->where('slug', $genreSlug));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeByResolution(Builder $query, string $resolution): Builder
    {
        return $query->where('resolution', 'like', "%{$resolution}%");
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeByYearRange(Builder $query, int $fromYear, int $toYear): Builder
    {
        return $query->whereBetween('release_year', [$fromYear, $toYear]);
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        if (is_numeric($value)) {
            $item = $this->where('id', $value)->first();
            if ($item) {
                return $item;
            }
        }

        $item = $this->where('slug', $value)->first();
        if ($item) {
            return $item;
        }

        return $this->where('title', str_replace('-', ' ', $value))->firstOrFail();
    }
}
