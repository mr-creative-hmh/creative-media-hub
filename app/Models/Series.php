<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property ?int $end_year
 * @property ?string $tmdb_id
 * @property ?string $tvmaze_id
 * @property ?string $imdb_id
 * @property ?string $overview
 * @property ?string $overview_ar
 * @property ?string $poster_path
 * @property ?string $backdrop_path
 * @property ?string $trailer_url
 * @property float $rating
 * @property string $status
 * @property ?string $network
 * @property ?string $folder_path
 * @property ?array<int, string> $mood_tags
 * @property bool $is_favorite
 * @property ?string $original_language
 * @property ?string $origin_country
 * @property ?string $content_rating
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Collection<int, Season> $seasons
 * @property-read Collection<int, Episode> $episodes
 * @property-read Collection<int, Genre> $genres
 * @property-read Collection<int, Person> $people
 * @property-read Collection<int, Person> $actors
 * @property-read Collection<int, UserFavorite> $userFavorites
 */
class Series extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if (empty($model->slug) && ! empty($model->title)) {
                $base = Str::slug($model->title.($model->release_year ? " {$model->release_year}" : ''));
                $model->slug = $base ?: 'series-'.uniqid();
            }
        });

        static::saved(function (): void {
            Cache::forget('series.hero_items.v3');
            Cache::forget('series.hero_items.v2');
        });

        static::deleted(function (): void {
            Cache::forget('series.hero_items.v3');
            Cache::forget('series.hero_items.v2');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'release_year' => 'integer',
            'end_year' => 'integer',
            'rating' => 'float',
            'mood_tags' => 'array',
            'is_favorite' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Season, $this>
     */
    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class)->orderBy('season_number');
    }

    /**
     * @return HasMany<Episode, $this>
     */
    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class)->orderBy('season_id')->orderBy('episode_number');
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
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFavorites(Builder $query): Builder
    {
        return $query->where('is_favorite', true);
    }

    /**
     * @return MorphMany<UserFavorite, $this>
     */
    public function userFavorites(): MorphMany
    {
        return $this->morphMany(UserFavorite::class, 'favoritable');
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
