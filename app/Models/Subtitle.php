<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $subtitlable_type
 * @property int $subtitlable_id
 * @property string $language
 * @property string $language_name
 * @property string $format
 * @property ?string $file_path
 * @property bool $is_embedded
 * @property bool $is_default
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read ?Model $subtitlable
 */
class Subtitle extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_embedded' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_embedded' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subtitlable(): MorphTo
    {
        return $this->morphTo();
    }
}
