# Development Task Tracker

## 🏁 Phase 0: Project Setup & Git Configuration
- [x] Initialized Laravel 11/12 with Vue 3 Starter Kit
- [x] Configured Git remote `https://github.com/mr-creative-hmh/creative-media-streaming-library.git`
- [x] Validated Herd environment & Vite compilation
- [x] Authored Master Documentation (`PLAN.md`, `README.md`, `ARCHITECTURE.md`, `TASKS.md`)

## 🏁 Phase 1: Database Architecture & Eloquent Relational Models
- [ ] Create migration for `media_items` (Movies)
- [ ] Create migration for `series`, `seasons`, and `episodes`
- [ ] Create migration for `genres` and `genre_media`
- [ ] Create migration for `people` and `media_person`
- [ ] Create migration for `subtitles`
- [ ] Create migration for `watch_histories`
- [ ] Create migration for `download_items` & `app_settings`
- [ ] Create Eloquent Models with relationships & query scopes
- [ ] Create comprehensive Database Seeders with realistic movie & series catalog (Arabic & English)
- [ ] Run test suite & verify migrations

## 🏁 Phase 2: Multi-Provider Metadata Scraper Engine
- [ ] Build `MetadataAggregatorService`
- [ ] Implement `TmdbProvider` (Arabic & English, HD posters/backdrops, trailers)
- [ ] Implement `TvMazeProvider` (100% Free TV Series data)
- [ ] Implement `OmdbProvider` (IMDb & Rotten Tomatoes ratings)
- [ ] Implement `AniListProvider` (Anime specialist)
- [ ] Implement `LocalNfoProvider` (Offline parsing)
- [ ] Write unit tests for metadata providers

## 🏁 Phase 3: Filesystem Scanner, Regex Parser & Physical Organizer
- [ ] Implement `SceneNameParserService` (regex tokens for 4K/1080p/x265/SxxExx)
- [ ] Implement `FilesystemScannerService` (recursive video & subtitle discovery)
- [ ] Implement `PhysicalOrganizerService` (Plex/Jellyfin naming, customizable patterns)
- [ ] Implement `DryRunSimulationEngine` (Diff table & collision checks)
- [ ] Implement `UndoHistoryService`
- [ ] Write unit & feature tests for organizer

## 🏁 Phase 4: Free Subtitle Engine & Auto-Missing Subtitle Downloader
- [ ] Implement `OpenSubtitlesService` (Hash sync & REST API)
- [ ] Implement `SubDlService` (Arabic & English subtitles)
- [ ] Implement `SubtitleManagerService` (Missing subtitle scanner & 1-click download)
- [ ] Write tests for subtitle downloads

## 🏁 Phase 5: Cinema Streaming Video Server
- [ ] Implement `StreamController` with `HTTP 206 Partial Content` Range requests
- [ ] Implement WebVTT / SRT subtitle streamer
- [ ] Implement Playback Progress Sync API (`/api/playback/progress`)
- [ ] Write streaming & range header tests

## 🏁 Phase 6: Design System, Bilingual RTL Engine & Layouts
- [ ] Configure Tailwind CSS & `app.css` cinema dark design tokens (Obsidian `#0B0E14`, glassmorphism)
- [ ] Configure Vue I18n with `ar.json` & `en.json`
- [ ] Build App Layouts with Sidebar, Header, Language Switcher, and Ambient Glow Hero Banner
- [ ] Test RTL layout switching & build assets

## 🏁 Phase 7: Virtual Library Views, Search & Discovery
- [ ] Build `Movies/Index.vue` grid with hover previews & quality badges
- [ ] Build `Series/Index.vue` & `Series/Show.vue` with season tabs & episode cards
- [ ] Build `FilterBar.vue` with multi-axis filters & fast fuzzy search
- [ ] Build `MediaDetailModal.vue` with cast gallery & trailer player
- [ ] Build `VibeRecommender.vue` (AI mood categorizer)
- [ ] Build `CastExplorer.vue` (actor cross-reference)

## 🏁 Phase 8: Cinema Video Player & Subtitles
- [ ] Build `CinemaPlayer.vue` with glass controls, scrub bar, speed selector, fullscreen
- [ ] Build Subtitle Track Selector with live Arabic/English switching
- [ ] Build Next-Episode countdown & auto-play
- [ ] Test player controls & keyboard shortcuts

## 🏁 Phase 9: Organizer Studio, Health Inspector & Analytics
- [ ] Build `Organizer/Index.vue` with folder picker & template builder
- [ ] Build `DryRunDiffTable.vue` with Before/After preview & execution trigger
- [ ] Build `HealthInspector.vue` for missing episodes & duplicate files
- [ ] Build `DownloadManager.vue` with incoming watch folder monitor
- [ ] Build `Analytics/Index.vue` Bento dashboard for storage & codecs

## 🏁 Phase 10: Complete Verification & Git Push
- [ ] Run `php artisan test` (100% pass)
- [ ] Run `npm run build` (Clean production bundle)
- [ ] Push all commits to GitHub remote
