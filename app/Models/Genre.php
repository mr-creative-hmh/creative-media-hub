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
    public function getNameArAttribute($value): ?string
    {
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
    }
}