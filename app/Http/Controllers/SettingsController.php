<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $settings = [
            'tmdb_api_key' => AppSetting::where('key', 'tmdb_api_key')->value('value') ?? '',
            'omdb_api_key' => AppSetting::where('key', 'omdb_api_key')->value('value') ?? '',
            'opensubtitles_api_key' => AppSetting::where('key', 'opensubtitles_api_key')->value('value') ?? '',
            'default_language' => AppSetting::where('key', 'default_language')->value('value') ?? 'ar',
            'auto_fetch_metadata' => (bool) (AppSetting::where('key', 'auto_fetch_metadata')->value('value') ?? true),
            'auto_fetch_subtitles' => (bool) (AppSetting::where('key', 'auto_fetch_subtitles')->value('value') ?? true),
            'preferred_providers' => json_decode(AppSetting::where('key', 'preferred_providers')->value('value') ?? '["tmdb","tvmaze","omdb","anilist","wikipedia","local_nfo"]', true),
        ];

        return Inertia::render('Settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tmdb_api_key' => 'nullable|string',
            'omdb_api_key' => 'nullable|string',
            'opensubtitles_api_key' => 'nullable|string',
            'default_language' => 'required|in:ar,en',
            'auto_fetch_metadata' => 'boolean',
            'auto_fetch_subtitles' => 'boolean',
            'preferred_providers' => 'nullable|array',
        ]);

        foreach ($validated as $key => $val) {
            $type = is_array($val) ? 'json' : (is_bool($val) ? 'boolean' : 'string');
            $value = is_array($val) ? json_encode($val) : ($val === true ? '1' : ($val === false ? '0' : (string) $val));

            AppSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings saved successfully!',
        ]);
    }
}
