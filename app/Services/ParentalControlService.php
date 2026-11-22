<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ParentalControlService
{
    /**
     * Map of content ratings to cumulative hierarchy.
     *
     * @var array<string, list<string>>
     */
    protected static array $ratingTiers = [
        'G' => ['G', 'TV-Y', 'TV-G'],
        'PG' => ['G', 'TV-Y', 'TV-G', 'PG', 'TV-PG'],
        'PG-13' => ['G', 'TV-Y', 'TV-G', 'PG', 'TV-PG', 'PG-13', 'TV-14'],
        'R' => ['G', 'TV-Y', 'TV-G', 'PG', 'TV-PG', 'PG-13', 'TV-14', 'R', 'NC-17', 'TV-MA'],
    ];

    /**
     * Get allowed content rating tiers for a given rating cap.
     *
     * @return list<string>|null
     */
    public function getAllowedRatings(string $cap): ?array
    {
        $cap = strtoupper($cap);
        if ($cap === 'ALL') {
            return null;
        }

        return self::$ratingTiers[$cap] ?? self::$ratingTiers['PG-13'];
    }

    /**
     * Apply both rating and genre restrictions to a query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyScope(Builder $query, ?User $user): Builder
    {
        if (! $user || $user->isAdmin()) {
            return $query;
        }

        $query = $this->applyRatingScope($query, $user);

        return $this->applyGenreScope($query, $user);
    }

    /**
     * Filter query by user maturity rating cap.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyRatingScope(Builder $query, ?User $user): Builder
    {
        if (! $user || $user->isAdmin()) {
            return $query;
        }

        $cap = strtoupper($user->max_content_rating ?: 'ALL');

        if ($user->is_kids) {
            $cap = 'PG';
        }

        if ($cap === 'ALL') {
            return $query;
        }

        $allowedRatings = self::$ratingTiers[$cap] ?? self::$ratingTiers['PG-13'];

        return $query->where(function (Builder $q) use ($allowedRatings, $user): void {
            $q->whereIn('content_rating', $allowedRatings);
            if (! $user->is_kids) {
                $q->orWhereNull('content_rating')->orWhere('content_rating', '');
            }
        });
    }

    /**
     * Filter query by user blocked genres.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyGenreScope(Builder $query, ?User $user): Builder
    {
        if (! $user || $user->isAdmin()) {
            return $query;
        }

        $blocked = $user->blocked_genres ?? [];
        if (empty($blocked)) {
            return $query;
        }

        return $query->whereDoesntHave('genres', function (Builder $g) use ($blocked): void {
            $g->whereIn('name_en', $blocked)
                ->orWhereIn('name_ar', $blocked)
                ->orWhereIn('slug', array_map('strtolower', $blocked));
        });
    }

    /**
     * Check if a specific media item can be viewed by the user.
     *
     * @param  mixed  $mediaItem
     */
    public function canAccess(User $user, $mediaItem): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $cap = strtoupper($user->max_content_rating ?: 'ALL');
        if ($user->is_kids) {
            $cap = 'PG';
        }

        if ($cap !== 'ALL') {
            $allowedRatings = self::$ratingTiers[$cap] ?? self::$ratingTiers['PG-13'];
            $rating = strtoupper($mediaItem->content_rating ?? '');
            if (! empty($rating) && ! in_array($rating, $allowedRatings, true)) {
                return false;
            }
            if (empty($rating) && $user->is_kids) {
                return false;
            }
        }

        $blocked = $user->blocked_genres ?? [];
        if (! empty($blocked) && method_exists($mediaItem, 'genres')) {
            $genres = $mediaItem->genres ?? [];
            foreach ($genres as $genre) {
                if (in_array($genre->name_en, $blocked, true) ||
                    in_array($genre->name_ar, $blocked, true) ||
                    in_array(strtolower((string) $genre->slug), array_map('strtolower', $blocked), true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
