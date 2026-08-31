<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\MediaItem;
use App\Services\Subtitles\OpenSubtitlesService;
use App\Services\Subtitles\SubDlService;
use App\Services\Subtitles\SubtitleManagerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubtitleController extends Controller
{
    protected SubtitleManagerService $manager;
    protected OpenSubtitlesService $openSubtitles;
    protected SubDlService $subDl;

    public function __construct(
        SubtitleManagerService $manager,
        OpenSubtitlesService $openSubtitles,
        SubDlService $subDl
    ) {
        $this->manager = $manager;
        $this->openSubtitles = $openSubtitles;
        $this->subDl = $subDl;
    }

    public function index()
    {
        $missing = $this->manager->findMissingSubtitles();

        return Inertia::render('Subtitles/Index', [
            'missingSubtitles' => $missing,
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->input('query');
        $imdbId = $request->input('imdb_id');
        $languages = $request->input('languages', 'ar,en');

        $openSubResults = $this->openSubtitles->searchSubtitles([
            'query' => $query,
            'imdb_id' => $imdbId,
            'languages' => $languages,
        ]);

        return response()->json([
            'results' => $openSubResults,
        ]);
    }

    public function downloadForMedia(Request $request)
    {
        $validated = $request->validate([
            'media_id' => 'required|integer',
            'media_type' => 'required|in:movie,episode',
            'language' => 'required|in:ar,en',
        ]);

        $model = $validated['media_type'] === 'movie'
            ? MediaItem::findOrFail($validated['media_id'])
            : Episode::findOrFail($validated['media_id']);

        $subtitle = $this->manager->downloadAndAttachMockSubtitle($model, $validated['language']);

        return response()->json([
            'status' => 'success',
            'subtitle' => $subtitle,
        ]);
    }
}
