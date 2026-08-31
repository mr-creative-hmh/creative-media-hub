<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Genre extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function mediaItems(): MorphToMany
    {
        return $this->morphedByMany(MediaItem::class, 'genreable');
    }

    public function series(): MorphToMany
    {
        return $this->morphedByMany(Series::class, 'genreable');
    }
}
