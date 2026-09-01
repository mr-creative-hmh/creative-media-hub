<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class MediaItem extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            if (empty($model->slug) && !empty($model->title)) {
                $base = \Illuminate\Support\Str::slug($model->title . ($model->release_year ? " {$model->release_year}" : ''));
                $model->slug = $base ?: 'movie-' . uniqid();
            }
        });
    }


    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'release_year' => 'integer',
        'rating' => 'float',
        'vote_count' => 'integer',
        'runtime_minutes' => 'integer',
        'file_size_bytes' => 'integer',
        'mood_tags' => 'array',
        'is_favorite' => 'boolean',
    ];

    public function genres(): MorphToMany
    {
        return $this->morphToMany(Genre::class, 'genreable');
    }

    public function people(): MorphToMany
    {
        return $this->morphToMany(Person::class, 'personable')
            ->withPivot(['role', 'character_name', 'order'])
            ->orderBy('order');
    }

    public function actors(): MorphToMany
    {
        return $this->people()->wherePivot('role', 'actor');
    }

    public function directors(): MorphToMany
    {
        return $this->people()->wherePivot('role', 'director');
    }

    public function subtitles(): MorphMany
    {
        return $this->morphMany(Subtitle::class, 'subtitlable');
    }

    public function watchHistories(): MorphMany
    {
        return $this->morphMany(WatchHistory::class, 'watchable');
    }

    public function scopeMovies($query)
    {
        return $query->where('type', 'movie');
    }

    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    public function scopeByGenre($query, $genreSlug)
    {
        return $query->whereHas('genres', fn($q) => $q->where('slug', $genreSlug));
    }

    public function scopeByResolution($query, $resolution)
    {
        return $query->where('resolution', 'like', "%{$resolution}%");
    }

    public function scopeByYearRange($query, $fromYear, $toYear)
    {
        return $query->whereBetween('release_year', [$fromYear, $toYear]);
    }
}
