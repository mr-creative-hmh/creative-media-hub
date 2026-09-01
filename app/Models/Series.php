<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Series extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            if (empty($model->slug) && !empty($model->title)) {
                $base = \Illuminate\Support\Str::slug($model->title . ($model->release_year ? " {$model->release_year}" : ''));
                $model->slug = $base ?: 'series-' . uniqid();
            }
        });
    }


    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'release_year' => 'integer',
        'end_year' => 'integer',
        'rating' => 'float',
        'mood_tags' => 'array',
        'is_favorite' => 'boolean',
    ];

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class)->orderBy('season_number');
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class)->orderBy('season_id')->orderBy('episode_number');
    }

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

    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if (is_numeric($value)) {
            $item = $this->where('id', $value)->first();
            if ($item) return $item;
        }

        $item = $this->where('slug', $value)->first();
        if ($item) return $item;

        return $this->where('title', str_replace('-', ' ', $value))->firstOrFail();
    }
}
