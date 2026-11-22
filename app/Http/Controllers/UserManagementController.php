<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    /**
     * Display User Management Studio.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $users = User::withCount('watchHistories')
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'role' => $u->role,
                'avatar' => $u->avatar ?: 'popcorn',
                'is_kids' => (bool) $u->is_kids,
                'max_content_rating' => $u->max_content_rating ?: 'ALL',
                'blocked_genres' => $u->blocked_genres ?: [],
                'is_active' => (bool) $u->is_active,
                'has_pin' => $u->hasPin(),
                'pin_length' => (int) ($u->pin_length ?: 4),
                'watch_history_count' => (int) ($u->watch_histories_count ?? 0),
                'created_at' => $u->created_at?->toIso8601String(),
            ]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['users' => $users]);
        }

        return Inertia::render('Users/Index', [
            'initialUsers' => $users,
        ]);
    }

    /**
     * Create a new user profile.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'pin' => ['nullable', 'string', 'min:4', 'max:8'],
            'pin_length' => ['nullable', 'integer', 'in:4,6,8'],
            'role' => ['required', 'in:admin,user'],
            'avatar' => ['nullable', 'string', 'max:50'],
            'is_kids' => ['boolean'],
            'max_content_rating' => ['required', 'in:ALL,G,PG,PG-13,R,NC-17'],
            'blocked_genres' => ['nullable', 'array'],
            'blocked_genres.*' => ['string'],
        ]);

        $pin = ! empty($validated['pin']) ? (string) $validated['pin'] : null;
        $pinLength = ! empty($pin) ? (int) ($validated['pin_length'] ?? strlen($pin)) : 4;

        $user = User::create([
            'name' => $validated['name'],
            'username' => strtolower((string) $validated['username']),
            'pin' => ! empty($pin) ? Hash::make($pin) : null,
            'pin_length' => $pinLength,
            'role' => $validated['role'],
            'avatar' => $validated['avatar'] ?? (! empty($validated['is_kids']) ? 'robot' : 'popcorn'),
            'is_kids' => (bool) ($validated['is_kids'] ?? false),
            'max_content_rating' => $validated['max_content_rating'],
            'blocked_genres' => $validated['blocked_genres'] ?? [],
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Profile '{$user->name}' created successfully.",
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'is_kids' => (bool) $user->is_kids,
                'max_content_rating' => $user->max_content_rating,
                'blocked_genres' => $user->blocked_genres,
                'is_active' => (bool) $user->is_active,
                'has_pin' => $user->hasPin(),
                'pin_length' => (int) ($user->pin_length ?: 4),
                'watch_history_count' => 0,
            ],
        ], 201);
    }

    /**
     * Update an existing user profile.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'role' => ['required', 'in:admin,user'],
            'avatar' => ['nullable', 'string', 'max:50'],
            'is_kids' => ['boolean'],
            'max_content_rating' => ['required', 'in:ALL,G,PG,PG-13,R,NC-17'],
            'blocked_genres' => ['nullable', 'array'],
            'blocked_genres.*' => ['string'],
            'is_active' => ['boolean'],
        ]);

        // Safeguard: Do not allow demoting or deactivating the last active administrator
        if ($user->isAdmin() && ($validated['role'] !== 'admin' || ! ($validated['is_active'] ?? true))) {
            $otherAdminsCount = User::where('role', 'admin')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherAdminsCount === 0) {
                return response()->json([
                    'message' => 'Cannot demote or deactivate the only remaining active Administrator.',
                ], 422);
            }
        }

        $user->update([
            'name' => $validated['name'],
            'username' => strtolower((string) $validated['username']),
            'role' => $validated['role'],
            'avatar' => $validated['avatar'] ?? $user->avatar,
            'is_kids' => (bool) ($validated['is_kids'] ?? false),
            'max_content_rating' => $validated['max_content_rating'],
            'blocked_genres' => $validated['blocked_genres'] ?? [],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Profile '{$user->name}' updated successfully.",
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'is_kids' => (bool) $user->is_kids,
                'max_content_rating' => $user->max_content_rating,
                'blocked_genres' => $user->blocked_genres,
                'is_active' => (bool) $user->is_active,
                'has_pin' => $user->hasPin(),
                'pin_length' => (int) ($user->pin_length ?: 4),
                'watch_history_count' => (int) $user->watchHistories()->count(),
            ],
        ]);
    }

    /**
     * Reset user PIN.
     */
    public function resetPin(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['nullable', 'string', 'min:4', 'max:8'],
            'pin_length' => ['nullable', 'integer', 'in:4,6,8'],
        ]);

        $pin = ! empty($validated['pin']) ? (string) $validated['pin'] : null;

        $user->pin = ! empty($pin) ? Hash::make($pin) : null;
        if (! empty($pin)) {
            $user->pin_length = (int) ($validated['pin_length'] ?? strlen($pin));
        }
        $user->save();

        $statusText = ! empty($pin) ? 'PIN updated successfully.' : 'PIN removed (profile is now 1-click access).';

        return response()->json([
            'success' => true,
            'message' => $statusText,
            'has_pin' => $user->hasPin(),
            'pin_length' => (int) ($user->pin_length ?: 4),
        ]);
    }

    /**
     * Delete a profile.
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->id === Auth::id()) {
            return response()->json([
                'message' => 'You cannot delete your own active profile while logged in.',
            ], 422);
        }

        if ($user->isAdmin()) {
            $otherAdminsCount = User::where('role', 'admin')
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherAdminsCount === 0) {
                return response()->json([
                    'message' => 'Cannot delete the only remaining Administrator.',
                ], 422);
            }
        }

        $userName = $user->name;
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "Profile '{$userName}' deleted successfully.",
        ]);
    }
}
