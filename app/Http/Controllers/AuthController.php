<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Show the cinematic login view.
     */
    public function loginView(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login');
    }

    /**
     * Get active profiles for the "Who's Watching?" tile picker.
     */
    public function getProfiles(): JsonResponse
    {
        $profiles = User::where('is_active', true)
            ->select(['id', 'name', 'username', 'role', 'avatar', 'is_kids', 'max_content_rating', 'pin', 'pin_length'])
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'avatar' => $user->avatar ?: 'popcorn',
                'is_kids' => (bool) $user->is_kids,
                'max_content_rating' => $user->max_content_rating ?: 'ALL',
                'has_pin' => $user->hasPin(),
                'pin_length' => (int) ($user->pin_length ?: 4),
            ]);

        return response()->json([
            'profiles' => $profiles,
        ]);
    }

    /**
     * Authenticate profile using 4-8 digit numeric PIN (or 1-click if PIN-free).
     */
    public function verifyPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'pin' => ['nullable', 'string', 'max:12'],
        ]);

        $user = User::whereKey($validated['user_id'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'Profile not found.',
            ], 404);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'This profile is currently disabled.',
            ], 403);
        }

        if ($user->hasPin()) {
            $inputPin = (string) ($validated['pin'] ?? '');
            if (empty($inputPin) || ! $user->verifyPin($inputPin)) {
                return response()->json([
                    'message' => 'Incorrect PIN. Please try again.',
                ], 422);
            }
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'redirect' => route('dashboard'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'is_kids' => (bool) $user->is_kids,
                'max_content_rating' => $user->max_content_rating,
                'is_admin' => $user->isAdmin(),
            ],
        ]);
    }

    /**
     * Log the current user out.
     */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => true, 'redirect' => route('login')]);
        }

        return redirect()->route('login');
    }

    /**
     * Get current authenticated profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['user' => null], 401);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'is_kids' => (bool) $user->is_kids,
                'max_content_rating' => $user->max_content_rating,
                'is_admin' => $user->isAdmin(),
            ],
        ]);
    }
}
