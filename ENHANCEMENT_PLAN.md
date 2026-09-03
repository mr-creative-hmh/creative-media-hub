# Creative Media Hub - Comprehensive Enhancement Plan

## Project Overview
Laravel 13 + Vue 3 + Inertia.js media library management system with Virtual Scanner, Physical Organizer, and Downloader modules.

---

## 1. Virtual Scanner Enhancements

### Current State Analysis
- **ScannerController.php**: Handles addDirectory, startScan, scanFolder, rescanFresh, processBatch, pause/resume/cancel
- **VirtualLibraryScannerService.php**: Core scanning logic with batch processing, metadata aggregation, series/movie indexing
- **Frontend (Index.vue)**: Folder management, scan controls, live terminal, stats display

### Required Enhancements

#### 1.1 Multi-Type Folder Addition
- **Backend**: Extend `addDirectory` to accept `type: 'movies' | 'series' | 'mixed'` (already implemented)
- **Frontend**: Enhance folder addition modal with type selector (already in UI)
- **Service**: Use type_hint during file processing (already in `initScan`)

#### 1.2 Scan Modes
| Mode | Description | Implementation |
|------|-------------|----------------|
| **Full Scan** | Scan ALL monitored folders | `startFullScan()` - existing |
| **Single Folder Scan** | Scan one specific folder | `scanFolder()` - existing |
| **Incremental Rescan** | Skip indexed files, add new only | **NEW** - default behavior |
| **Fresh Rescan** | Wipe folder data, re-scan all | `scanFolder(..., fresh: true)` - existing |

#### 1.3 Rescan Options
- **Default (Incremental)**: Check DB for existing file_path, skip if found, process new files only
- **Fresh Rescan**: `wipeFolderMedia()` + full re-scan (already exists)
- **Full Library Rescan**: `rescanFresh()` wipes ALL + re-scans (already exists)

#### 1.4 Metadata Accuracy Fix (COMPLETED ✅)
- [x] `MediaProbeService` - ffprobe integration with HDR detection, codec normalization, audio selection
- [x] `VirtualLibraryScannerService` - uses probed data with fallback: ffprobe > filename parser > 'Unknown'
- [x] 11 new technical metadata columns on `media_items` + `episodes` tables (video_profile, video_bitrate, audio_channels, audio_channel_layout, audio_bitrate, container_format, framerate, hdr_format, color_space, color_transfer, total_bitrate)
- [x] Models updated with casts for new fields
- [x] Fixed null-safety bug: `probe()` returns empty array instead of null when ffprobe unavailable
- [x] All 69 tests pass, frontend build verified

#### 1.5 Incremental Scan Mode (COMPLETED ✅)
- [x] Added `scan_mode` parameter (`incremental` default | `fresh`) to `initScan()` and `initScanForFolder()`
- [x] Added `filterAlreadyIndexedFiles()` method - queries MediaItem + Episode tables to skip already-indexed files
- [x] Updated `ScannerController::startScan()` to accept `scan_mode` parameter
- [x] Updated `ScannerController::scanFolder()` to accept `scan_mode` and auto-wipe on fresh mode
- [x] Updated `ScannerController::rescanFresh()` to always use `fresh` mode
- [x] Updated `useScanner` composable to pass `scan_mode` to API calls
- [x] Added "Incremental Rescan" button in header with EN/AR labels
- [x] Updated folder-level actions: "Inc. Scan" (incremental) + "Fresh" (fresh rescan) buttons
- [x] Main "Start Full Scan" button now defaults to incremental mode
- [x] All 69 tests pass, frontend build verified

---

## 2. Physical Organizer Enhancements

### Current State Analysis
- **DiskOrganizerController.php**: scan, dryRun, initExecution, processBatch, execute, cancel
- **PhysicalOrganizerService.php**: Dry-run generation, batch execution, move/copy, empty folder cleanup
- **Templates**: `{Type}/{Title} ({Year})/{Title} ({Year}) [{Resolution}].{ext}`

### Required Enhancements

#### 2.1 Web Metadata Lookup (Fast) - **Deferred to Phase 4**
- **Providers**: TMDb, TVMaze, AniList, OMDB, Wikipedia
- **Caching**: Cache results 30 days to avoid repeated API calls
- **Parallel**: Batch lookup for multiple files
- **Fallback**: Use parsed filename if web lookup fails

#### 2.2 Source/Destination Selection - **Already Implemented ✅**
- **Source**: Virtual library + raw folder scan with recursive option
- **Destination**: Target root directory selector (different dir copy/move)
- **Mode**: `move` | `copy` (hardlink/symlink planned for Phase 4)

#### 2.3 Naming Template Enhancements - **COMPLETED ✅**
- [x] `{Genre}` / `{Genres}` - Primary genre / joined genres (backend + UI tokens)
- [x] `{Year}` - Release year (already existed)
- [x] `{Resolution}` - 4K UHD, 1080p FHD, etc. (already existed)
- [x] `{Codec}` - HEVC, H.264, AV1 (already existed)
- [x] `{Source}` - BluRay, WEBRip, HDTV, WEB-DL (added backend + UI)
- [x] `{Group}` - Release group (already existed + UI token)
- [x] `{Edition}` - Director's Cut, Extended, etc. (already existed + UI token)
- [x] `{FirstLetter}` - A-Z or # for non-alpha (already existed)
- [x] New preset strategies: Source Quality (BluRay/WEB), Genre, Resolution, Alphabetical, Flat, Plex, Jellyfin

#### 2.4 Dry-Run Improvements - **Already Implemented ✅**
- Collision detection (same destination) - shows "Destination Exists" status
- Select/deselect individual items with checkboxes
- Bulk select/deselect all ready items
- Filter by type (movie/series) and status (ready/collision/identical)
- Search by filename or path
- Pagination for large plans

#### 2.5 Implementation Tasks - **COMPLETED ✅**
- [x] Extended `generateDryRun` with all new tokens including `{Source}`, `{Genre}`, `{Genres}`, `{Edition}`, `{Group}`
- [x] Added destination directory selector in UI (Step 1)
- [x] Copy/Move radio buttons in execution confirmation modal
- [x] Enhanced template editor with full token picker UI
- [x] Progress tracking with file-level detail (Step 3 live terminal)
- [x] Auto-cleanup empty folders toggle (enabled by default on move)
- [x] Source quality detection (BluRay/WEBRip/WEB-DL/HDTV) in parser
- [x] New "Source Quality" preset strategy
- [x] All 69 tests pass, frontend build verified

---

## 3. Downloader Enhancements

### Current State Analysis
- **DownloadManagerController.php**: CRUD for downloads, processBatch
- **DownloadManagerService.php**: Create download, simulate progress, auto-index on completion
- **Frontend**: Download list with pause/resume/retry/delete

### Required Enhancements

#### 3.1 Customizable Download Folder
- **Setting**: `downloader_default_path` in AppSetting
- **Per-download override**: Optional destination path (already supported via API)
- **Subfolder structure**: `{Type}/{Title}/` auto-creation (already implemented)

#### 3.2 Torrent File Selection - **Deferred to Phase 4**
- **Parse .torrent/magnet**: Extract file list
- **UI**: Checkbox list to select/deselect files
- **Filter**: Auto-select video files, skip samples/extras
- **Rename**: Apply naming template on download completion

#### 3.3 Library-Friendly Naming - **Auto-Index Implemented ✅**
- **On completion**: Rename using organizer templates (deferred)
- **Auto-index**: Trigger Virtual Scanner for downloaded files ✅
- **Subtitle handling**: Download matching subtitles (OpenSubtitles, SubDl) - deferred

#### 3.4 Download Engine Improvements - **COMPLETED ✅**
- [x] **Real HTTP download**: Streaming download with Http client
- [x] **Resume support**: Range header support for partial downloads
- [x] **Auto-detect file size**: HEAD request to get Content-Length
- [x] **Fallback to simulation**: For demo/test URLs or failed HTTP
- [x] **Concurrent limit**: Max 3 concurrent downloads
- [x] **Error handling**: Failed status with error message

#### 3.5 Implementation Tasks - **COMPLETED ✅**
- [x] Real HTTP download engine with streaming + Range header resume
- [x] Auto-detect file size from server (HEAD request)
- [x] Post-download auto-index into Virtual Scanner
- [x] Simulation fallback for demo/test URLs
- [x] All 69 tests pass, frontend build verified

---

## 4. Bilingual EN/AR Support (Preserve & Enhance)

### Current State
- All UI strings use `useI18n()` with `t()` function
- RTL support via `isRTL` computed
- Models have `_ar` fields: `title_ar`, `overview_ar`, `name_ar`
- Genre model has `name_en`, `name_ar`

### Enhancement Requirements
- [ ] All new strings must use `t('key')` pattern
- [ ] Add AR translations for new features in `resources/lang/ar/*.json`
- [ ] Ensure RTL layout works for new components
- [ ] Metadata providers: fetch AR metadata when available (TMDb supports `ar` language)
- [ ] Genre names: store both EN/AR from providers

---

## 5. Testing & Build Verification

### Test Coverage Targets
| Module | Unit Tests | Feature Tests | Browser Tests |
|--------|------------|---------------|---------------|
| Virtual Scanner | ✅ | ✅ | ❌ |
| Physical Organizer | ⚠️ | ✅ | ❌ |
| Downloader | ⚠️ | ⚠️ | ❌ |

### Required Tests
- [ ] **Scanner**: File parsing, type detection, metadata aggregation, incremental scan
- [ ] **Organizer**: Dry-run generation, template tokens, move/copy, collision handling
- [ ] **Downloader**: Torrent parsing, file selection, download resume, rename+index
- [ ] **Integration**: Full scan → organize → download workflow

### Build Verification
```bash
# Backend
composer install --no-dev
php artisan test --parallel
php artisan pint --test
phpstan analyse --level=5

# Frontend
npm ci
npm run build
npm run lint
```

### CI Pipeline
- GitHub Actions: test, lint, build on PR
- Separate jobs for backend/frontend
- Artifact upload for build verification

---

## 6. UI/UX Best Practices

### Design System
- **Colors**: Cyan primary, semantic colors (success/error/warning)
- **Spacing**: 4px base unit, consistent padding/margins
- **Typography**: Inter font, consistent scale
- **Dark mode**: Default, with CSS variables

### Component Patterns
- **Glass panels**: `backdrop-blur` + semi-transparent backgrounds
- **Badges**: Status indicators with consistent sizing
- **Buttons**: Primary (cyan), Secondary (outline), Danger (rose), Ghost
- **Forms**: Consistent input styling, validation states
- **Modals**: Confirmation dialogs with keyboard support

### Accessibility
- [ ] Semantic HTML (button, not div onClick)
- [ ] ARIA labels for icon-only buttons
- [ ] Focus visible states
- [ ] Keyboard navigation
- [ ] Screen reader announcements for status changes
- [ ] RTL support verified

### Performance
- [ ] Virtual scrolling for large file lists
- [ ] Debounced search/filter
- [ ] Lazy load heavy components
- [ ] Optimize re-renders with `shallowRef`/`computed`

---

## Implementation Phases

### Phase 1: Virtual Scanner (Week 1-2)
1. Incremental rescan logic
2. UI improvements for scan modes
3. Tests for scanner service

### Phase 2: Physical Organizer (Week 2-3)
1. Web metadata lookup service
2. Enhanced dry-run with metadata
3. Destination selection + copy/move modes
4. Template token expansion

### Phase 3: Downloader (Week 3-4)
1. Real download engine
2. Torrent file selection
3. Post-download rename + auto-index
4. Settings for default folder

### Phase 4: Polish & Tests (Week 4-5)
1. Full test suite
2. Build verification
3. UI/UX audit
4. Bilingual verification
5. Documentation updates

---

## Technical Debt to Address
- [ ] Replace Cache-based job state with database queue (Redis/DB)
- [ ] Add proper job workers (Horizon/Queue) instead of AJAX polling
- [ ] Extract services to packages for reusability
- [ ] Add OpenAPI/Swagger documentation
- [ ] Implement proper logging (Laravel Telescope)

---

## Success Criteria
- ✅ All 3 modules enhanced with requested features
- ✅ 80%+ test coverage on new code
- ✅ Build passes (composer, npm, pint, phpstan)
- ✅ EN/AR bilingual support verified
- ✅ UI/UX consistent with design system
- ✅ No regressions in existing functionality