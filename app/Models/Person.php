<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Person extends Model
{
    use HasFactory;

    protected $table = 'people';
    protected $guarded = [];

    public function mediaItems(): MorphToMany
    {
        return $this->morphedByMany(MediaItem::class, 'personable')
            ->withPivot(['role', 'character_name', 'order']);
    }

    public function series(): MorphToMany
    {
        return $this->morphedByMany(Series::class, 'personable')
            ->withPivot(['role', 'character_name', 'order']);
    }
}
