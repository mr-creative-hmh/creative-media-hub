<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Series extends Model
{
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
}
