<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Number;

class ByteFormatter
{
    /**
     * Format a byte count into a human-readable string (KB, MB, GB, TB).
     */
    public static function format(int|float $bytes, int $precision = 2): string
    {
        return Number::fileSize($bytes, precision: $precision);
    }
}
