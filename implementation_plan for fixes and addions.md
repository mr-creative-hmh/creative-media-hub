# Implementation Plan: Metadata Studio Renaming, Scanner Real-Time Cards, Series Slider Details, & Collections-Style Search

This plan addresses all items requested by the user:
1. **Metadata Studio Series Fix Match & Renaming**: Rename the series folder and all episode files (and subtitles) on disk to standard clean format (`Title - S01E01 - Title.ext` / `Title - S01E01.ext`), updating all database records.
2. **Metadata Studio Movie Renaming**: Rename the movie file AND its enclosing folder (if in a dedicated movie folder, not the root library folder).
3. **Virtual Scanner Real-Time Status & Collections Card**: When scan status updates, dynamically update the stats cards (indexed movies, TV series, indexed size, subtitles) and add a 5th card for **Collections**.
4. **Series Sliders Details Button**: Fix the details button on the series HeroBanner slider to navigate directly to the series details page (`/series/{slug || id}`).
5. **Search for Series and Movies**: Add a search bar matching the prominent Collections search design to Series and Movies pages.

---

## User Review Required

> [!IMPORTANT]
> - **Series Episode Renaming Pattern**: When renaming a series, episodes will be renamed to `{Series Title} - S{Season:02}E{Episode:02}{ - Episode Title}.{ext}`. Accompanying subtitle files (`.srt`, `.vtt`, `.sub`, `.ass`) will also be renamed to match.
> - **Movie Folder Renaming**: If a movie is stored inside a dedicated movie folder (e.g. `Movies/Inception (2010)/...` or `Movies/Inception.2010.1080p/...`), both the file and its folder will be renamed to clean standard `{Title} ({Year})`. If the movie is directly in the monitored library root without a dedicated subfolder, only the file is renamed to prevent altering the monitored library root.

---

## Proposed Changes

### 1. Metadata Management & Physical Renaming (Backend)

#### [MODIFY] [MetadataManagementController.php](file:///c:/Users/hasan/Herd/creative-media-hub/app/Http/Controllers/MetadataManagementController.php)
- **Series Renaming**:
  - Automatically resolve `$series->folder_path` from its episodes if `folder_path` is null in the database.
  - Rename the series directory on disk to `{Series Title} ({Year})` (or `{Series Title}`).
  - Rename all episode files in all seasons to `{Series Title} - S{Season:02}E{Episode:02}{ - Episode Title}.{ext}`.
  - Rename any matching external subtitle files (`.srt`, `.vtt`, `.sub`, `.ass`).
  - Update `episodes.file_path`, `series.folder_path`, and `subtitles.file_path` in the database.
- **Movie Renaming**:
  - Rename the movie file and matching subtitles to `{Title} ({Year}).{ext}`.
  - Detect whether the movie is inside a dedicated movie folder (by comparing against monitored root directories and checking for sibling media items).
  - If in a dedicated movie folder, rename that folder on disk to `{Title} ({Year})` and update `folder_path` and `file_path`.

#### [MODIFY] [VirtualLibraryScannerService.php](file:///c:/Users/hasan/Herd/creative-media-hub/app/Services/Scanner/VirtualLibraryScannerService.php)
- When indexing series episodes, set `folder_path` on the created/updated `Series` model so it is never null going forward.
- When indexing movies, set `folder_path` on the created/updated `MediaItem` model.

#### [NEW] [2026_09_03_010000_backfill_series_and_media_folder_paths.php](file:///c:/Users/hasan/Herd/creative-media-hub/database/migrations/2026_09_03_010000_backfill_series_and_media_folder_paths.php)
- A migration to safely backfill `folder_path` for any existing `series` and `media_items` in the database based on their current `file_path` records.

---

### 2. Virtual Scanner Real-Time Cards & Collections (Backend & Frontend)

#### [MODIFY] [ScannerController.php](file:///c:/Users/hasan/Herd/creative-media-hub/app/Http/Controllers/ScannerController.php)
- In `index()`: add `total_collections` to stats (`MediaItem::whereNotNull('collection_name')->where('collection_name', '!=', '')->distinct('collection_name')->count('collection_name')`).
- In `getStatus()`, `processBatch()`, `startScan()`, and `rescanFresh()`: attach the current live library stats (`total_movies`, `total_series`, `total_episodes`, `total_subtitles`, `total_collections`, `storage_size_formatted`) to the returned response.

#### [MODIFY] [useScanner.ts](file:///c:/Users/hasan/Herd/creative-media-hub/resources/js/composables/useScanner.ts)
- Add `liveStats` reactive reference to `useScanner`.
- When `fetchStatus()` or `processBatch()` receives updated stats, update `liveStats` in real time.
- Expose `liveStats` to consumers.

#### [MODIFY] [Scanner/Index.vue](file:///c:/Users/hasan/Herd/creative-media-hub/resources/js/pages/Scanner/Index.vue)
- Bind the stats Bento row to `currentStats` (initialized with `props.stats`, reactive to `liveStats` or `scanStatus.stats`).
- Add the **Collections** card (`total_collections`) with `Layers` icon, styled alongside Indexed Movies, TV Series, Indexed Size, and Subtitles.
- Responsive 5-column bento layout: `grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8`.

---

### 3. Metadata Studio & Fix Match Modal (Frontend)

#### [MODIFY] [FixMatchModal.vue](file:///c:/Users/hasan/Herd/creative-media-hub/resources/js/components/media/FixMatchModal.vue)
- When `autoRenameFile` is checked during `applyMatch`, `lookupAndApplyDirectId`, or `saveManualEdit`:
  - Await `renamePhysicalFile()` and await the response before closing the modal, so users get immediate visual confirmation.
  - Update `props.item` with the new file and folder paths and title.
- Show detailed rename status in the UI for both series (folder + episodes count) and movies (folder + file).

#### [MODIFY] [Metadata/Index.vue](file:///c:/Users/hasan/Herd/creative-media-hub/resources/js/pages/Metadata/Index.vue)
- Update `confirmRenameFile` for series to show:
  - Series folder name change and episode files rename explanation in both EN and AR.
  - On confirm, execute the rename and update the item in the list seamlessly.

---

### 4. Series Slider Details Button Fix

#### [MODIFY] [HeroBanner.vue](file:///c:/Users/hasan/Herd/creative-media-hub/resources/js/components/media/HeroBanner.vue)
- Ensure props accept both `items` and `featuredItems`.
- Emit both `details` and `info` events when the Details button is clicked.

#### [MODIFY] [Series/Index.vue](file:///c:/Users/hasan/Herd/creative-media-hub/resources/js/pages/Series/Index.vue)
- Fix `@details="(item) => router.visit('/series/' + (item.slug || item.id))"`.
- Handle `@play` on series: if first episode is available, play it; otherwise navigate to the series page.

#### [MODIFY] [Dashboard/Index.vue](file:///c:/Users/hasan/Herd/creative-media-hub/resources/js/pages/Dashboard/Index.vue)
- Ensure `@details` or `@info` on series navigates to `/series/{slug || id}` or opens detail modal.

---

### 5. Search for Series and Movies (Collections-Style)

#### [MODIFY] [FilterBar.vue](file:///c:/Users/hasan/Herd/creative-media-hub/resources/js/components/media/FilterBar.vue)
- Add a top-level Search Input Bar matching `Collections/Index.vue`:
  - Glass-panel background with `Search` icon on left/right (RTL-aware).
  - Clean text input with responsive placeholder.
  - Enter key triggering search.
  - Clear `(X)` button when search text is active.
  - Cyan `Search` / `بحث` action button on the side.
  - Seamlessly keeps existing filters (genre, resolution, sort, origin, vibe, favorites) while searching.

---

## Verification Plan

### Automated Tests
- Fix permission issue in `tests/Feature/MetadataRenameAndCollectionTest.php` by using `storage_path()`.
- Add test cases in `MetadataRenameAndCollectionTest.php`:
  - `test_series_rename_renames_folder_and_episode_files_and_updates_db()`
  - `test_movie_rename_renames_file_and_enclosing_folder_when_in_dedicated_folder()`
  - `test_scanner_get_status_includes_live_stats_and_collections_count()`
- Run tests:
  ```powershell
  php artisan test --filter=MetadataRenameAndCollectionTest
  php artisan test
  ```

### Code Quality & Build Verification
- Format code:
  ```powershell
  vendor/bin/pint --format agent
  ```
- Build frontend:
  ```powershell
  npm run build
  ```

### Manual Verification
1. Test Metadata Studio (`/metadata`):
   - Test Fix Match with "Automatically rename physical disk file" checked on a Series.
   - Verify folder is renamed and episode files are renamed on disk and updated in DB.
   - Test Rename button on a Movie in a subfolder and verify folder and file are renamed.
2. Test Virtual Scanner (`/scanner`):
   - Verify the 5 cards (Indexed Movies, TV Series, Movie Collections, Indexed Size, Subtitles) appear.
   - Verify cards update in real time when status updates.
3. Test Series Slider (`/series`):
   - Click "Details" on the HeroBanner slider and verify it opens `/series/{slug}`.
4. Test Search (`/series` and `/movies`):
   - Verify Collections-style search input appears.
   - Search for a movie/series and confirm filtered results and query string persistence.
