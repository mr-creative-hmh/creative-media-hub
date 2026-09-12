<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\Series;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class FixMatchCollectionController extends Controller
{
    /**
     * Get all unique collections in the library with counts and external IDs.
     */
    public function getCollections(Request $request): JsonResponse
    {
        $items = MediaItem::whereNotNull('collection_name')
            ->where('collection_name', '!=', '')
            ->get(['collection_name', 'collection_id', 'collection_id_source']);

        $collectionsMap = [];
        foreach ($items as $item) {
            $name = trim($item->collection_name);
            if (! $name) {
                continue;
            }

            if (! isset($collectionsMap[$name])) {
                $collectionsMap[$name] = [
                    'name' => $name,
                    'id' => $item->collection_id,
                    'source' => $item->collection_id_source ?: 'tmdb',
                    'count' => 0,
                ];
            }

            $collectionsMap[$name]['count']++;
            if ($item->collection_id && ! $collectionsMap[$name]['id']) {
                $collectionsMap[$name]['id'] = $item->collection_id;
            }
            if ($item->collection_id_source && ! $collectionsMap[$name]['source']) {
                $collectionsMap[$name]['source'] = $item->collection_id_source;
            }
        }

        // Also check collections_extended.json for additional metadata
        $extendedPath = storage_path('app/metadata-index/collections_extended.json');
        if (File::exists($extendedPath)) {
            $extended = json_decode(File::get($extendedPath), true) ?: [];
            foreach ($extended as $ext) {
                $name = $ext['name'] ?? null;
                if ($name && isset($collectionsMap[$name])) {
                    if (! empty($ext['collection_id']) && ! $collectionsMap[$name]['id']) {
                        $collectionsMap[$name]['id'] = $ext['collection_id'];
                    }
                    if (! empty($ext['collection_id_source']) && ! $collectionsMap[$name]['source']) {
                        $collectionsMap[$name]['source'] = $ext['collection_id_source'];
                    }
                }
            }
        }

        $collections = array_values($collectionsMap);
        usort($collections, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return response()->json([
            'success' => true,
            'collections' => $collections,
        ]);
    }

    /**
     * Update or detach a media item's collection linkage, with optional disk relocation.
     */
    public function updateCollection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'media_id' => 'required|integer',
            'media_type' => 'required|string|in:movie,series',
            'collection_mode' => 'required|string|in:existing,new,none',
            'existing_collection_name' => 'nullable|string',
            'new_collection_name' => 'nullable|string',
            'collection_external_id' => 'nullable|string',
            'collection_source' => 'nullable|string',
            'reorganize_folder' => 'nullable|boolean',
        ]);

        if ($validated['media_type'] === 'movie') {
            $media = MediaItem::findOrFail($validated['media_id']);
        } else {
            $media = Series::findOrFail($validated['media_id']);
        }

        $mode = $validated['collection_mode'];
        $reorganize = $validated['reorganize_folder'] ?? false;
        $relocated = false;
        $newPath = null;

        if ($mode === 'none') {
            $media->collection_name = null;
            $media->collection_id = null;
            $media->collection_id_source = null;
            $media->save();

            CollectionController::clearCache();

            return response()->json([
                'success' => true,
                'collection_name' => null,
                'collection_id' => null,
                'collection_id_source' => null,
                'relocated' => false,
            ]);
        }

        $collectionName = '';
        $collectionId = null;
        $collectionSource = 'tmdb';

        if ($mode === 'existing') {
            $collectionName = trim($validated['existing_collection_name'] ?? '');
            if (! $collectionName) {
                return response()->json(['success' => false, 'error' => 'Existing collection name is required.'], 422);
            }

            // Look up collection_id from existing records
            $existing = MediaItem::where('collection_name', $collectionName)->whereNotNull('collection_id')->first();
            if ($existing) {
                $collectionId = $existing->collection_id;
                $collectionSource = $existing->collection_id_source ?: 'tmdb';
            }
        } elseif ($mode === 'new') {
            $collectionName = trim($validated['new_collection_name'] ?? '');
            if (! $collectionName) {
                return response()->json(['success' => false, 'error' => 'New collection name is required.'], 422);
            }
            $collectionId = $validated['collection_external_id'] ? trim($validated['collection_external_id']) : null;
            $collectionSource = $validated['collection_source'] ?: 'tmdb';
        }

        $media->collection_name = $collectionName;
        $media->collection_id = $collectionId;
        $media->collection_id_source = $collectionSource;

        // Physical folder relocation if requested
        if ($reorganize && $media instanceof MediaItem && $media->folder_path && File::isDirectory($media->folder_path)) {
            $sanitizedColName = trim(preg_replace('/[:*?"<>|]/', '', $collectionName));
            $currentFolder = str_replace('\\', '/', $media->folder_path);

            // Only relocate if not already inside the collection folder
            if (! str_contains($currentFolder, '/'.$sanitizedColName.'/')) {
                $parentDir = dirname($currentFolder);
                $targetColDir = $parentDir.'/'.$sanitizedColName;
                $folderName = basename($currentFolder);
                $targetFolder = $targetColDir.'/'.$folderName;

                if (! File::isDirectory($targetColDir)) {
                    File::makeDirectory($targetColDir, 0755, true);
                }

                if (! File::isDirectory($targetFolder)) {
                    if (rename($currentFolder, $targetFolder)) {
                        $oldFilePath = str_replace('\\', '/', $media->file_path);
                        $newFilePath = $targetFolder.'/'.basename($oldFilePath);

                        $media->folder_path = $targetFolder;
                        $media->file_path = $newFilePath;
                        $relocated = true;
                        $newPath = $newFilePath;
                    }
                }
            }
        }

        $media->save();

        CollectionController::clearCache();

        return response()->json([
            'success' => true,
            'collection_name' => $media->collection_name,
            'collection_id' => $media->collection_id,
            'collection_id_source' => $media->collection_id_source,
            'relocated' => $relocated,
            'new_path' => $newPath,
        ]);
    }
}
