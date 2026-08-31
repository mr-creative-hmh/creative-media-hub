<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Subtitle extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_embedded' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function subtitlable(): MorphTo
    {
        return $this->morphTo();
    }
}
