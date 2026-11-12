<?php

declare(strict_types=1);

namespace App\Support;

class ByteFormatter
{
    /**
     * Format a byte count into a human-readable string (KB, MB, GB, TB).
     */
    public static function format(int|float $bytes, int $precision = 2): string
    {
        $bytes = (float) $bytes;

        if ($bytes >= 1099511627776) { // 1 TB
            return round($bytes / 1099511627776, $precision).' TB';
        }
        if ($bytes >= 1073741824) { // 1 GB
            return round($bytes / 1073741824, $precision).' GB';
        }
        if ($bytes >= 1048576) { // 1 MB
            return round($bytes / 1048576, min(1, $precision)).' MB';
        }
        if ($bytes > 0) {
            return round($bytes / 1024, min(1, $precision)).' KB';
        }

        return '0 GB';
    }
}
