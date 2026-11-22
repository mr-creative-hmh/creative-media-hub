<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property string $slug
 * @property ?int $tmdb_genre_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read string $name
 * @property-read Collection<int, MediaItem> $mediaItems
 * @property-read Collection<int, Series> $series
 */
class Genre extends Model
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
            'tmdb_genre_id' => 'integer',
        ];
    }

    /**
     * @return MorphToMany<MediaItem, $this>
     */
    public function mediaItems(): MorphToMany
    {
        return $this->morphedByMany(MediaItem::class, 'genreable');
    }

    /**
     * @return MorphToMany<Series, $this>
     */
    public function series(): MorphToMany
    {
        return $this->morphedByMany(Series::class, 'genreable');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->name_en,
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function nameAr(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): string {
                if (! empty($value) && $value !== $this->name_en) {
                    return $value;
                }

                $arabicMap = [
                    'action' => 'أكشن',
                    'drama' => 'دراما',
                    'comedy' => 'كوميديا',
                    'thriller' => 'إثارة',
                    'crime' => 'جريمة',
                    'mystery' => 'غموض',
                    'science-fiction' => 'خيال علمي',
                    'adventure' => 'مغامرة',
                    'romance' => 'رومانسي',
                    'history' => 'تاريخي',
                    'fantasy' => 'فانتازيا',
                    'family' => 'عائلي',
                    'war' => 'حرب',
                    'horror' => 'رعب',
                    'western' => 'وسترن',
                    'documentary' => 'وثائقي',
                    'music' => 'موسيقى',
                    'animation' => 'رسوم متحركة',
                    'tv-movie' => 'فيلم تلفزيوني',
                    'sci-fi-fantasy' => 'خيال علمي وفانتازيا',
                    'action-adventure' => 'حركة ومغامرة',
                    'biography' => 'سيرة ذاتية',
                    'war-politics' => 'حرب وسياسة',
                    'kids' => 'أطفال',
                ];

                $slug = strtolower($this->slug ?? '');

                return $arabicMap[$slug] ?? ($arabicMap[strtolower($this->name_en ?? '')] ?? ($value ?: $this->name_en));
            },
        );
    }
}
