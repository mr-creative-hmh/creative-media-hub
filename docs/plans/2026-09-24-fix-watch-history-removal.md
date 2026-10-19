# Fix Watch History and Continue Watching Removal Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix the root causes preventing consistent removal of movies and TV series from Watch History and Continue Watching in Creative Media Hub and Creative Media Server.

**Architecture:** 
1. Make backend `StreamController::deleteWatchHistory` type-aware, collision-proof, and series-comprehensive so deleting a series removes all associated episode watch histories and prevents zombie resurrecting episodes.
2. Enrich frontend requests from `WatchHistoryBar.vue` and `WatchHistory/Index.vue` with explicit `series_id`, `watchable_id`, and `watchable_type` parameters.
3. Validate HTTP responses on the frontend (`res.ok`) and implement proper rollback and error toasts if deletion fails.
4. Add comprehensive feature tests covering single movie deletion, series cascade deletion (multiple episodes), and ID collision prevention.

**Tech Stack:** Laravel 11 / PHP 8.3, SQLite, Inertia.js v3, Vue 3, TypeScript, PHPUnit.

---

### Task 1: Add Comprehensive Failing Tests for Watch History Removal Edge Cases

**Files:**
- Modify: `tests/Feature/StreamingAndRoutesTest.php`

**Step 1: Write the failing tests**
Add unit and feature tests covering:
1. Deleting a series from continue watching deletes all watched episodes of that series so older episodes do not resurface.
2. Deleting a movie by `watchable_id` when an unrelated `WatchHistory` with the same primary key ID exists does not delete the wrong item (collision protection).
3. Deleting by `series_id` explicitly removes all watch history for that series.
4. Deleting with invalid or non-matching type returns a clean handled response without side effects.

```php
    public function test_delete_series_watch_history_removes_all_episodes_for_that_series(): void
    {
        $series = Series::create(['title' => 'Breaking Bad', 'release_year' => 2008]);
        $season = Season::create(['series_id' => $series->id, 'season_number' => 1]);
        $ep1 = Episode::create(['series_id' => $series->id, 'season_id' => $season->id, 'episode_number' => 1, 'title' => 'Pilot']);
        $ep2 = Episode::create(['series_id' => $series->id, 'season_id' => $season->id, 'episode_number' => 2, 'title' => 'Cat\'s in the Bag']);

        $h1 = WatchHistory::create([
            'watchable_id' => $ep1->id,
            'watchable_type' => Episode::class,
            'progress_seconds' => 100,
            'duration_seconds' => 3000,
            'is_completed' => false,
            'last_watched_at' => now()->subDay(),
        ]);

        $h2 = WatchHistory::create([
            'watchable_id' => $ep2->id,
            'watchable_type' => Episode::class,
            'progress_seconds' => 200,
            'duration_seconds' => 3000,
            'is_completed' => false,
            'last_watched_at' => now(),
        ]);

        // Removing latest episode watch history with type=series should clean up the entire series
        $res = $this->deleteJson('/api/watch-history/'.$h2->id.'?type=series');
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $this->assertDatabaseMissing('watch_histories', ['id' => $h2->id]);
        $this->assertDatabaseMissing('watch_histories', ['id' => $h1->id]);
    }

    public function test_delete_watch_history_avoids_id_collision_between_models(): void
    {
        $movie = MediaItem::create(['title' => 'Inception', 'release_year' => 2010]);
        $series = Series::create(['title' => 'Dark', 'release_year' => 2017]);
        $season = Season::create(['series_id' => $series->id, 'season_number' => 1]);
        $ep = Episode::create(['series_id' => $series->id, 'season_id' => $season->id, 'episode_number' => 1, 'title' => 'Secrets']);

        // Suppose WatchHistory #1 is an Episode
        $h1 = WatchHistory::create([
            'watchable_id' => $ep->id,
            'watchable_type' => Episode::class,
            'progress_seconds' => 300,
            'duration_seconds' => 3000,
            'is_completed' => false,
            'last_watched_at' => now(),
        ]);

        // And WatchHistory #2 is a Movie whose MediaItem ID happens to equal $h1->id
        // Set movie id manually or test deleting by watchable_id with type=movie
        $h2 = WatchHistory::create([
            'watchable_id' => $h1->id, // deliberate collision: watchable_id of movie equals id of episode watch history
            'watchable_type' => MediaItem::class,
            'progress_seconds' => 500,
            'duration_seconds' => 7000,
            'is_completed' => false,
            'last_watched_at' => now(),
        ]);

        // Attempting to delete the movie by passing movie ID and type=movie
        $res = $this->deleteJson('/api/watch-history/'.$h1->id.'?type=movie&watchable_type=movie');
        $res->assertStatus(200);

        // The Movie watch history should be removed, while Episode watch history $h1 should be intact
        $this->assertDatabaseMissing('watch_histories', ['id' => $h2->id]);
        $this->assertDatabaseHas('watch_histories', ['id' => $h1->id]);
    }
```

**Step 2: Run test to verify it fails**
Run: `php artisan test --compact --filter=test_delete_series_watch_history_removes_all_episodes_for_that_series`
Expected: FAIL because `$h1` is not deleted by the current single-row delete logic.

---

### Task 2: Refactor `StreamController::deleteWatchHistory` for Robust Multi-Type Resolution

**Files:**
- Modify: `app/Http/Controllers/StreamController.php:603-646`

**Step 1: Implement collision-proof, series-aware deletion**
1. Parse query and body inputs: `type`, `series_id`, `watchable_id`, `watchable_type`, `is_series`.
2. Map type strings (`movie`, `movies`, `collection`, `collections`, `series`, `episode`, `episodes`) to their corresponding Eloquent classes (`MediaItem::class` vs `Episode::class`).
3. If an explicit `series_id` is supplied OR if `$id` is identified as a `Series` ID:
   - Find all episode IDs for that series: `Episode::where('series_id', $seriesId)->pluck('id')`.
   - Delete all `watch_histories` records matching `watchable_type = Episode::class` and `watchable_id IN ($epIds)`.
4. If `$id` is a `WatchHistory` primary key:
   - Check if `$record = WatchHistory::find($id)`.
   - Verify whether `$type` was specified. If `$type` was specified, ensure `$record->watchable_type` matches `$modelClass` before assuming `$id` is the `WatchHistory` PK.
   - If `$record` is found and matches:
     - If `$record->watchable_type === Episode::class`: find the episode, get its `series_id`, and delete all watch history records for that entire series if `type === 'series'` or `is_series` or if the record represents an in-progress episode.
     - Delete `$record->delete()`.
5. If `$id` is an entity ID (`watchable_id`):
   - Delete `WatchHistory::where('watchable_type', $modelClass)->where('watchable_id', $id)->delete()`.
   - If the entity is an `Episode` and `type === 'series'`, also look up the series and delete all siblings.
   - If the entity is a `Series`, delete all episodes for that series.

**Step 2: Run tests to verify they pass**
Run: `php artisan test --compact --filter=StreamingAndRoutesTest`
Expected: PASS with 16 passed tests.

---

### Task 3: Enhance Frontend Removal Logic & Error Handling in `WatchHistoryBar.vue`

**Files:**
- Modify: `resources/js/components/layout/WatchHistoryBar.vue:47-78`

**Step 1: Update `removeItem`**
1. Send full contextual parameters in the DELETE request:
   - URL: `/api/watch-history/${item.history_id || item.watchable_id || item.id}`
   - Query parameters:
     - `type=${item.category || item.type || ''}`
     - `watchable_id=${item.watchable_id || item.id}`
     - `watchable_type=${item.watchable_type || (item.category === 'series' || item.type === 'episode' ? 'episode' : 'movie')}`
     - `series_id=${item.series_id || ''}`
2. Check `if (!res.ok)`:
   - If response is not ok, revert the optimistic removal from `items.value`.
   - Dispatch error toast to notify the user.
3. If response is ok:
   - If the removed item was a series, filter out all items belonging to that `series_id` from the bar.
   - Dispatch success toast.

---

### Task 4: Enhance Frontend Removal Logic & Error Handling in `WatchHistory/Index.vue`

**Files:**
- Modify: `resources/js/pages/WatchHistory/Index.vue:101-134`

**Step 1: Update `removeItem` and `executeClear`**
1. In `removeItem`, send `series_id`, `watchable_id`, and `watchable_type`.
2. Check `if (!res.ok)` on the fetch call. Revert `allItems` if deletion failed.
3. If a series was removed, filter out any items with that `series_id` from `allItems` locally.
4. Update `executeClear` to handle network errors gracefully with user feedback.

---

### Task 5: Code Formatting and Regression Testing

**Files:**
- Modify: PHP & Vue files modified in Tasks 1-4

**Step 1: Format PHP code with Laravel Pint**
Run: `vendor/bin/pint --dirty --format agent`

**Step 2: Run full test suite**
Run: `php artisan test --compact`

**Step 3: Test build validation**
Run: `npm run build` to verify frontend TypeScript and Vue SFC bundling.

---
