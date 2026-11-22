<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property ?string $value
 * @property string $type
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AppSetting extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Request-level memoized settings cache (`key => ['value' => ?string, 'type' => string]|null`).
     *
     * @var array<string, array{value: ?string, type: string}|null>
     */
    protected static array $memo = [];

    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            static::$memo[$setting->key] = [
                'value' => $setting->value,
                'type' => (string) ($setting->type ?: 'string'),
            ];
        });

        static::deleted(function (self $setting): void {
            unset(static::$memo[$setting->key]);
        });
    }

    public static function flushMemo(): void
    {
        static::$memo = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, static::$memo)) {
            $setting = static::where('key', $key)->first(['value', 'type']);
            static::$memo[$key] = $setting
                ? ['value' => $setting->value, 'type' => (string) ($setting->type ?: 'string')]
                : null;
        }

        $cached = static::$memo[$key];
        if ($cached === null) {
            return $default;
        }

        return match ($cached['type']) {
            'boolean' => filter_var($cached['value'], FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $cached['value'],
            'json' => json_decode((string) $cached['value'], true),
            default => $cached['value'],
        };
    }

    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        $storedValue = is_array($value) ? json_encode($value) : (string) $value;
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $storedValue, 'type' => $type]
        );
        static::$memo[$key] = [
            'value' => (string) $storedValue,
            'type' => $type,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'string',
            'type' => 'string',
        ];
    }
}
