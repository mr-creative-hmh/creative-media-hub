<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property int $id
 * @property string $name
 * @property ?string $name_ar
 * @property ?string $profile_path
 * @property ?string $tmdb_id
 * @property string $known_for_department
 * @property ?string $biography
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Collection<int, MediaItem> $mediaItems
 * @property-read Collection<int, Series> $series
 */
class Person extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $table = 'people';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * @return MorphToMany<MediaItem, $this>
     */
    public function mediaItems(): MorphToMany
    {
        return $this->morphedByMany(MediaItem::class, 'personable')
            ->withPivot(['role', 'character_name', 'order']);
    }

    /**
     * @return MorphToMany<Series, $this>
     */
    public function series(): MorphToMany
    {
        return $this->morphedByMany(Series::class, 'personable')
            ->withPivot(['role', 'character_name', 'order']);
    }
}
