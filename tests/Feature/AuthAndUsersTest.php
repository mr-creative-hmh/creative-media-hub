<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Genre;
use App\Models\MediaItem;
use App\Models\User;
use App\Models\WatchHistory;
use App\Services\ParentalControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndUsersTest extends TestCase
{
    use RefreshDatabase;

    protected bool $authenticateAsAdmin = false;

    public function test_login_page_renders_for_guests_and_redirects_authenticated_users(): void
    {
        $guestResponse = $this->get('/login');
        $guestResponse->assertOk();

        $user = User::factory()->create();
        $authResponse = $this->actingAs($user)->get('/login');
        $authResponse->assertRedirect(route('dashboard'));
    }

    public function test_profiles_endpoint_lists_active_profiles_without_leaking_pin(): void
    {
        User::query()->delete();

        User::factory()->create([
            'name' => 'Dad',
            'username' => 'dad',
            'pin' => Hash::make('1234'),
            'pin_length' => 4,
            'role' => 'admin',
            'avatar' => 'director',
            'is_kids' => false,
            'max_content_rating' => 'ALL',
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Junior',
            'username' => 'junior',
            'pin' => null,
            'pin_length' => 4,
            'role' => 'user',
            'avatar' => 'robot',
            'is_kids' => true,
            'max_content_rating' => 'PG',
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Disabled Profile',
            'username' => 'disabled',
            'pin' => Hash::make('9999'),
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/auth/profiles');
        $response->assertOk();

        $profiles = $response->json('profiles');
        $this->assertCount(2, $profiles);

        $this->assertNull(collect($profiles)->firstWhere('username', 'disabled'));

        $dad = collect($profiles)->firstWhere('username', 'dad');
        $this->assertNotNull($dad);
        $this->assertTrue($dad['has_pin']);
        $this->assertEquals(4, $dad['pin_length']);
        $this->assertArrayNotHasKey('pin', $dad);

        $junior = collect($profiles)->firstWhere('username', 'junior');
        $this->assertNotNull($junior);
        $this->assertFalse($junior['has_pin']);
        $this->assertTrue($junior['is_kids']);
        $this->assertArrayNotHasKey('pin', $junior);
    }

    public function test_verify_pin_authenticates_with_valid_pin_and_rejects_invalid_pin(): void
    {
        $user = User::factory()->create([
            'pin' => Hash::make('4321'),
            'pin_length' => 4,
            'is_active' => true,
        ]);

        $invalidResponse = $this->postJson('/api/auth/verify-pin', [
            'user_id' => $user->id,
            'pin' => '0000',
        ]);
        $invalidResponse->assertStatus(422);
        $this->assertGuest();

        $validResponse = $this->postJson('/api/auth/verify-pin', [
            'user_id' => $user->id,
            'pin' => '4321',
        ]);
        $validResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $user->id);

        $this->assertAuthenticatedAs($user);
    }

    public function test_verify_pin_allows_pinless_profiles(): void
    {
        $user = User::factory()->withoutPin()->create([
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/verify-pin', [
            'user_id' => $user->id,
            'pin' => null,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertAuthenticatedAs($user);
    }

    public function test_verify_pin_rejects_inactive_profiles(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/verify-pin', [
            'user_id' => $user->id,
            'pin' => '1234',
        ]);

        $response->assertStatus(403);
        $this->assertGuest();
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/logout');
        $response->assertOk()->assertJsonPath('success', true);
        $this->assertGuest();
    }

    public function test_me_endpoint_returns_authenticated_user_payload(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);

        $user = User::factory()->admin()->create();
        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.is_admin', true);
    }

    public function test_guests_are_redirected_to_login_from_cinema_and_studio_pages(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/movies')->assertRedirect('/login');
        $this->get('/series')->assertRedirect('/login');
        $this->get('/scanner')->assertRedirect('/login');
        $this->get('/organizer')->assertRedirect('/login');
        $this->get('/settings')->assertRedirect('/login');
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_standard_users_cannot_access_studio_admin_routes(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get('/users')->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get('/scanner')->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get('/organizer')->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get('/settings')->assertRedirect(route('dashboard'));
        $this->actingAs($user)->getJson('/api/users')->assertStatus(403);
    }

    public function test_admin_can_access_studio_routes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/users')->assertOk();
        $this->actingAs($admin)->get('/scanner')->assertOk();
        $this->actingAs($admin)->get('/organizer')->assertOk();
        $this->actingAs($admin)->get('/settings')->assertOk();
        $this->actingAs($admin)->getJson('/api/users')->assertOk();
    }

    public function test_user_management_index_returns_users_with_watch_history_count(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->create();

        $media = MediaItem::create([
            'title' => 'Test Inception',
            'rating' => 8.8,
            'release_year' => 2010,
        ]);

        WatchHistory::create([
            'user_id' => $viewer->id,
            'watchable_type' => MediaItem::class,
            'watchable_id' => $media->id,
            'progress_seconds' => 120,
            'duration_seconds' => 3600,
            'is_completed' => false,
            'last_watched_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/users');
        $response->assertOk();

        $users = $response->json('users');
        $viewerItem = collect($users)->firstWhere('id', $viewer->id);
        $this->assertNotNull($viewerItem);
        $this->assertEquals(1, $viewerItem['watch_history_count']);
    }

    public function test_admin_can_create_user_profile(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = [
            'name' => 'Cinema Guest',
            'username' => 'cinemaguest',
            'pin' => '7890',
            'pin_length' => 4,
            'role' => 'user',
            'avatar' => 'popcorn',
            'is_kids' => false,
            'max_content_rating' => 'PG-13',
            'blocked_genres' => ['Horror'],
        ];

        $response = $this->actingAs($admin)->postJson('/api/users', $payload);
        $response->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'username' => 'cinemaguest',
            'role' => 'user',
            'max_content_rating' => 'PG-13',
        ]);
    }

    public function test_admin_can_update_user_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Original Name']);

        $payload = [
            'name' => 'Updated Name',
            'username' => $user->username,
            'role' => 'user',
            'avatar' => 'star',
            'is_kids' => false,
            'max_content_rating' => 'R',
            'blocked_genres' => [],
            'is_active' => true,
        ];

        $response = $this->actingAs($admin)->putJson("/api/users/{$user->id}", $payload);
        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'avatar' => 'star',
        ]);
    }

    public function test_admin_can_reset_user_pin(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['pin' => Hash::make('1111')]);

        $response = $this->actingAs($admin)->postJson("/api/users/{$user->id}/reset-pin", [
            'pin' => '9999',
            'pin_length' => 4,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('has_pin', true);

        $user->refresh();
        $this->assertTrue($user->verifyPin('9999'));

        // Removing PIN to make 1-click access
        $removeResponse = $this->actingAs($admin)->postJson("/api/users/{$user->id}/reset-pin", [
            'pin' => null,
        ]);
        $removeResponse->assertOk()->assertJsonPath('has_pin', false);
        $user->refresh();
        $this->assertFalse($user->hasPin());
    }

    public function test_admin_can_delete_user_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/users/{$targetUser->id}");
        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/users/{$admin->id}");
        $response->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_cannot_demote_or_delete_last_active_admin(): void
    {
        User::query()->delete();
        $soleAdmin = User::factory()->admin()->create(['is_active' => true]);

        // Attempt demote
        $demoteResponse = $this->actingAs($soleAdmin)->putJson("/api/users/{$soleAdmin->id}", [
            'name' => $soleAdmin->name,
            'username' => $soleAdmin->username,
            'role' => 'user',
            'max_content_rating' => 'ALL',
            'is_active' => true,
        ]);
        $demoteResponse->assertStatus(422);

        // Add second admin, then demote should pass
        $secondAdmin = User::factory()->admin()->create(['is_active' => true]);
        $demoteResponse2 = $this->actingAs($secondAdmin)->putJson("/api/users/{$soleAdmin->id}", [
            'name' => $soleAdmin->name,
            'username' => $soleAdmin->username,
            'role' => 'user',
            'max_content_rating' => 'ALL',
            'is_active' => true,
        ]);
        $demoteResponse2->assertOk();
    }

    public function test_parental_controls_filter_out_unallowed_ratings_and_genres(): void
    {
        $parentalService = app(ParentalControlService::class);

        $this->assertEquals(['G', 'TV-Y', 'TV-G'], $parentalService->getAllowedRatings('G'));
        $this->assertEquals(['G', 'TV-Y', 'TV-G', 'PG', 'TV-PG'], $parentalService->getAllowedRatings('PG'));
        $this->assertEquals(['G', 'TV-Y', 'TV-G', 'PG', 'TV-PG', 'PG-13', 'TV-14'], $parentalService->getAllowedRatings('PG-13'));
        $this->assertEquals(['G', 'TV-Y', 'TV-G', 'PG', 'TV-PG', 'PG-13', 'TV-14', 'R', 'NC-17', 'TV-MA'], $parentalService->getAllowedRatings('R'));
        $this->assertNull($parentalService->getAllowedRatings('ALL'));

        $kid = User::factory()->kids()->create();

        $gMovie = MediaItem::create(['title' => 'Toy Story', 'content_rating' => 'G', 'rating' => 8.3]);
        $rMovie = MediaItem::create(['title' => 'Pulp Fiction', 'content_rating' => 'R', 'rating' => 8.9]);

        $this->assertTrue($parentalService->canAccess($kid, $gMovie));
        $this->assertFalse($parentalService->canAccess($kid, $rMovie));

        // Blocked genre
        $actionGenre = Genre::create(['name_en' => 'Action', 'name_ar' => 'أكشن', 'slug' => 'action']);
        $kidWithBlockedGenre = User::factory()->create([
            'is_kids' => false,
            'max_content_rating' => 'ALL',
            'blocked_genres' => ['Action'],
        ]);

        $actionMovie = MediaItem::create(['title' => 'Die Hard', 'content_rating' => 'R', 'rating' => 8.2]);
        $actionMovie->genres()->attach($actionGenre->id);

        $this->assertFalse($parentalService->canAccess($kidWithBlockedGenre, $actionMovie));
    }

    public function test_user_can_toggle_and_filter_favorites(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $movie = MediaItem::create([
            'title' => 'Favorite Movie',
            'rating' => 9.0,
            'release_year' => 2024,
        ]);

        // Toggle favorite for User A
        $toggleRes = $this->actingAs($userA)->postJson("/movies/{$movie->id}/favorite");
        $toggleRes->assertOk()->assertJsonPath('is_favorite', true);

        $this->assertDatabaseHas('user_favorites', [
            'user_id' => $userA->id,
            'favoritable_type' => MediaItem::class,
            'favoritable_id' => $movie->id,
        ]);

        // User A filter favorites returns movie
        $listA = $this->actingAs($userA)->getJson('/movies?favorite_only=1');
        $listA->assertOk();

        // User B has not favorited it
        $this->assertDatabaseMissing('user_favorites', [
            'user_id' => $userB->id,
            'favoritable_type' => MediaItem::class,
            'favoritable_id' => $movie->id,
        ]);

        // Toggle again un-favorites
        $unfavRes = $this->actingAs($userA)->postJson("/movies/{$movie->id}/favorite");
        $unfavRes->assertOk()->assertJsonPath('is_favorite', false);
        $this->assertDatabaseMissing('user_favorites', [
            'user_id' => $userA->id,
            'favoritable_type' => MediaItem::class,
            'favoritable_id' => $movie->id,
        ]);
    }
}
