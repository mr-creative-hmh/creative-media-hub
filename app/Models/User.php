<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string|null $pin
 * @property int $pin_length
 * @property string|null $password
 * @property string $role
 * @property string $avatar
 * @property bool $is_kids
 * @property string $max_content_rating
 * @property array<string>|null $blocked_genres
 * @property bool $is_active
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'pin',
        'pin_length',
        'password',
        'role',
        'avatar',
        'is_kids',
        'max_content_rating',
        'blocked_genres',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'pin',
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pin' => 'hashed',
            'pin_length' => 'integer',
            'is_kids' => 'boolean',
            'is_active' => 'boolean',
            'blocked_genres' => 'array',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Determine if user has administrator privileges.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Determine if profile has a configured PIN.
     */
    public function hasPin(): bool
    {
        return ! empty($this->pin);
    }

    /**
     * Verify submitted PIN.
     */
    public function verifyPin(string $inputPin): bool
    {
        if (empty($this->pin)) {
            return true;
        }

        if (str_starts_with($this->pin, '$2y$') || str_starts_with($this->pin, '$argon2')) {
            return Hash::check($inputPin, $this->pin);
        }

        return hash_equals((string) $this->pin, (string) $inputPin);
    }

    /**
     * Set a new PIN (hashes automatically).
     */
    public function setPin(?string $plainPin): void
    {
        $this->pin = ! empty($plainPin) ? Hash::make($plainPin) : null;
        if (! empty($plainPin)) {
            $this->pin_length = strlen($plainPin);
        }
    }

    /**
     * Check if user is allowed to watch content with the given rating.
     */
    public function canWatchRating(?string $rating): bool
    {
        if (empty($rating) || $this->max_content_rating === 'ALL') {
            return true;
        }

        $tiers = [
            'G' => 1,
            'TV-Y' => 1,
            'TV-G' => 1,
            'PG' => 2,
            'TV-PG' => 2,
            'PG-13' => 3,
            'TV-14' => 3,
            'R' => 4,
            'NC-17' => 4,
            'TV-MA' => 4,
            'ALL' => 99,
        ];

        $userTier = $tiers[strtoupper($this->max_content_rating)] ?? 99;
        $contentTier = $tiers[strtoupper($rating)] ?? 3;

        return $contentTier <= $userTier;
    }

    /**
     * Watch histories belonging to this user.
     *
     * @return HasMany<WatchHistory, $this>
     */
    public function watchHistories(): HasMany
    {
        return $this->hasMany(WatchHistory::class);
    }

    /**
     * Favorites belonging to this user.
     *
     * @return HasMany<UserFavorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(UserFavorite::class);
    }
}
