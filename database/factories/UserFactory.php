<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->regexify('[a-z0-9_]{6,12}'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'pin' => Hash::make('1234'),
            'pin_length' => 4,
            'role' => 'user',
            'avatar' => 'popcorn',
            'is_kids' => false,
            'max_content_rating' => 'ALL',
            'blocked_genres' => null,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'name' => 'Administrator',
            'username' => $attributes['username'] ?? fake()->unique()->regexify('admin_[a-z0-9]{5}'),
            'avatar' => 'director',
            'is_kids' => false,
            'max_content_rating' => 'ALL',
            'blocked_genres' => null,
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the user is a kids profile.
     */
    public function kids(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'user',
            'is_kids' => true,
            'max_content_rating' => 'PG',
            'avatar' => 'robot',
        ]);
    }

    /**
     * Configure custom PIN.
     */
    public function withPin(string $pin = '1234'): static
    {
        return $this->state(fn (array $attributes) => [
            'pin' => Hash::make($pin),
            'pin_length' => strlen($pin),
        ]);
    }

    /**
     * Configure PIN-free profile.
     */
    public function withoutPin(): static
    {
        return $this->state(fn (array $attributes) => [
            'pin' => null,
            'pin_length' => 4,
        ]);
    }
}
