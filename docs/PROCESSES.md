# ⚙️ Creative Media Hub — Core Processes & Pipeline Lifecycle

> **Author**: Eng. Hasan Mohammad Hasan  
> **Repository**: [creative-media-hub](https://github.com/mr-creative-hmh/creative-media-hub)  
> **Version**: 2.5 (Media Scout, Multi-Episode Pipeline, Fix Match Studio, Hardlink Zero-Copy)

---

## 1. Process Overview & Lifecycles

Creative Media Hub is powered by 14 interconnected pipelines designed for maximum data integrity, non-blocking performance, zero duplicate rows, and full bilingual (Arabic/English) support.

```
       [Storage Directory Tree / Inbound Torrents]
                           │
                           ▼
            1. Virtual Scanner & Crawler
                           │
                           ▼
            2. Intelligent Scene Parser ──► Multi-Episode Engine (S01E01-02)
                           │
                           ▼
            3. Metadata Waterfall (TMDb / OMDb / Arabizer)
                           │
            ┌──────────────┴──────────────┐
            ▼                             ▼
   4. Boxsets & Sagas (owned >= 2)   12. Media Scout & Gap Tracker
            │                             │
            │                             ▼
            │                        13. Automated Acquisition (P2P / Flatten)
            │                             │
            ├─────────────────────────────┴────────────────┐
            ▼                                              ▼
   5. Hybrid Stream & Remuxer                     6. Subtitle Extraction & Sync
            │                                              │
            ▼                                              ▼
   7. Watch History Hub & Engine                  8. Fix Match & Collection Studio
            │                                              │
            ▼                                              ▼
   9. NTFS Hardlink Organizer                     14. Relocation Deduplication
```

---

## 2. Deep Dive: Core Pipelines

### 2.1. Virtual Library Scanner & Multi-Worker Crawler
- **Location**: `App\Services\Scanner\VirtualLibraryScannerService`
- **Mechanism**:
  - Traverses specified directory paths (`Movies/`, `Series/`, custom folders) recursively using parallel iterators.
  - Computes file hashes and inode keys to avoid redundant disk I/O.
  - Chunks discovered items into non-blocking batches of 5, pushing them to asynchronous worker queues.
  - Features real-time state persistence (`storage/app/scanner_state.json`) with pause, resume, and cancellation support.

---

### 2.2. Intelligent Scene Name Parser & Multi-Episode Engine
- **Location**: `App\Services\Organizer\SceneNameParserService`
- **Key Capabilities**:
  - **Eastern Arabic Numeral Normalization**: Converts `١, ٢, ٣, ٤, ٥` to `1, 2, 3, 4, 5`.
  - **Canonical Multi-Episode Parsing**: Seamlessly recognizes combined episode formats (`S01E01-E02`, `S01E01E02`, `S01E01-02`, `S01E01.E02`) without misidentifying resolution tags like `.1080p` as episode numbers.
  - **Folder Ancestor Context Inheritance**: If an episode file is named `01.mp4` inside `Breaking Bad/Season 01/`, the parser ascends parent directories to extract series title and season number.
  - **Collection Sequel Parsing**: Recognizes sequence prefixes on movies (e.g. `1.Ip.Man.2008.mp4`, `2.Fast.2.Furious.2003.mkv`) inside movie trees without misclassifying them as TV show episodes.
  - **Scene Tag Stripping**: Cleans release group tags (`BluRay`, `1080p`, `x265`, `HEVC`, `AAC`, `DTS`, `YTS`, `RARBG`, `Elkady`, `Aflam`).

---

### 2.3. Metadata Waterfall & Arabization Engine
- **Location**: `App\Services\Metadata\MetadataAggregator`
- **Waterfall Cascade**:
  1. **Primary**: TMDb API v3 (Titles, release years, cast, genres, high-resolution backdrops, and collections).
  2. **Secondary**: OMDb / IMDb (Exact IMDb IDs, Rotten Tomatoes ratings, and awards).
  3. **Specialized**: AniList (For Japanese anime titles and romaji mappings) & TVMaze (For network airings).
  4. **Arabization Layer**:
     - TMDb Arabic translation endpoint (`language=ar-SA`).
     - Wikipedia / Wikidata interlanguage links (`langlinks` API for Arabic regional titles).
     - MyMemory Translated API for overview translation fallbacks.

---

### 2.4. Boxsets & Franchise Sagas Clustering Engine (Strict `count >= 2`)
- **Location**: `App\Http\Controllers\CollectionController` & `LibraryGapService`
- **Rules & Capabilities**:
  - Aggregates movies having `collection_name` or TMDb `belongs_to_collection`.
  - **Strict `count >= 2` Threshold**: Guarantees that single standalone movies never create solitary false-positive collections on `/collections`.
  - **Real-Time Saga Completion**: Integrates with `LibraryGapService` to compute true saga completion percentages against total released franchise parts, highlighting missing titles and displaying "In Progress" badges.
  - **Automated Collection Audit CLI**: Supported by `php artisan library:audit-collections {--fix} {--align-physical}` to fix unlinked sequels and align physical directory hierarchies.
  - Renders chronological timeline views showing release span (e.g. *Harry Potter: 2001 - 2022 (9 films)*) and total boxset duration.

---

### 2.5. Hybrid Streaming, Hero Resolution & Cinema Player Engine
- **Location**: `App\Http\Controllers\StreamController`, `DashboardController`, & `CinemaPlayer.vue`
- **Modes & Architecture**:
  - **Direct Stream (HTTP 206 Partial Content)**: For browser-native containers (MP4, WebM) with H.264/AAC codecs. Serves 256KB chunks with instant seeking and 0% CPU consumption.
  - **On-The-Fly Remuxer**: For unsupported formats (AVI, MKV, MPEG-4, DTS). Spawns an FFmpeg sub-process piping fragmented MP4 (`-movflags frag_keyframe+empty_moov+default_base_moof`) directly to stdout.
  - **Hero Spotlight Playback Resolution**: Distinguishes movies from TV series using explicit `type` attribution. Clicking "Play Now" on a movie launches direct playback; clicking "Play Now" on a TV series resolves Season 1 Episode 1 (`first_episode`) and streams with the full season playlist, avoiding ID collisions between movies and series sharing primary keys.
  - **Cinema Player LTR Scrubber Architecture**: Enforces `dir="ltr"` on the player container regardless of the interface locale. This aligns range inputs, seekbar progress, and volume sliders with universal media player ergonomics (matching YouTube, Netflix, Shahid), eliminating inverted thumb calculations while displaying full Arabic localized text and bidirectional WebVTT cues (`dir="auto"`).
  - **FastStart Disk Caching**: Concurrently transcode-caches remuxed streams into `storage/app/transcodes/` for instant re-play without re-encoding.
  - **Orphan Process Reaper**: Kills hanging FFmpeg processes automatically on client disconnect or via `POST /api/stream/stop`.

---

### 2.6. Real Online Subtitle Search & Ingestion Pipeline
- **Location**: `App\Services\Subtitles\SubtitleManagerService` & `CinemaPlayer.vue`
- **Workflow**:
  - **Dynamic IMDb Discovery**: Probes Cinemeta `/meta/movie/{title}.json` or OMDb to automatically resolve exact IMDb IDs (`ttXXXXXXX`) on the fly.
  - **Real Online Providers**: Queries Stremio OpenSubtitles v3 addon and SubDL APIs. Zero placeholder or fake subtitles.
  - **In-Player Subtitle Modal**: Triggered directly from `CinemaPlayer.vue`'s subtitle selector to search, preview, and download subtitles without leaving playback.
  - **Decompression & Ingestion**: Decompresses `.gz` and `.zip` archives, converts CP1256 (Windows Arabic) / UTF-16 to UTF-8, saves `.srt` adjacent to video file, and inserts a `Subtitle` database record.

---

### 2.7. Subtitle Health Checker & Normalizer Pipeline
- **Location**: `App\Services\Subtitles\SubtitleHealthCheckService` & `CheckSubtitlesCommand`
- **Workflow**:
  - **Lexical Dialogue Extraction**: Strips timestamps, formatting tags, and numeric indices to isolate raw spoken dialogue.
  - **Multi-Encoding Conversion**: Decodes Windows-1256, ISO-8859-6, Windows-1252, ISO-8859-1, and UTF-16 into clean UTF-8.
  - **Script & Stop-Words Language Identification**: Identifies Arabic via `\p{Arabic}` with stop-word cross-validation; identifies Cyrillic, CJK, Greek, Hebrew; evaluates Latin dialogue stop-word frequency matrices for English, French, Spanish, German, Italian, Portuguese, Turkish, Dutch.
  - **Integrity Validation**: Detects 0-byte corrupt files, HTML error pages (Cloudflare 404/503), and dummy stubs (< 5 cues or < 300 bytes).
  - **Standardized Renaming**: Renames adjacent subtitles to `{videoBase}.{lang}.srt` (e.g. `Inception (2010).ar.srt`) and synchronizes the database.
  - **Execution**: Can be run via CLI `php artisan subtitles:check {--fix} {--dry-run} {--path=}` or the interactive web studio.

---

### 2.8. Fix Match & Collection Studio Pipeline
- **Location**: `App\Http\Controllers\MetadataManagementController` & `FixMatchCollectionController`
- **Capabilities**:
  - **Instant Live Search**: Live title search with automatic release tag stripping.
  - **Direct ID Resolution**: Accepts numeric TMDb IDs (`27205`), IMDb IDs (`tt1375666`), or direct URLs (`themoviedb.org`, `imdb.com`).
  - **Waterfall Cascade**: Resolves IMDb IDs via TMDb `/find` API with automatic fallback to OMDb.
  - **Automated Arabization & Artwork Caching**: Downloads and caches local poster/backdrop images, fetches Arabic titles/synopses (`ensureArabicMetadata`), updates the database model, and syncs genres and seasons.
  - **Franchise Collection Studio**: Dedicated Collection tab in `FixMatchModal.vue`. Search existing collections, assign with 1-click, create custom franchises, link official TMDb collection IDs, and automatically reorganize physical movie folders.
  - **1-Click Utilities**: 1-Click Movie ↔ Series conversion and scene re-parsing.

---

### 2.9. Watch History Hub & Playback Progress Engine
- **Dedicated Page**: `/watch-history` (`resources/js/pages/WatchHistory/Index.vue`)
- **Components**: `WatchHistoryBar.vue` (inline scoped trays with remove button & View All link)
- **Database**: Enforces unique `['watchable_type', 'watchable_id']` index on `watch_histories`.
- **Deduplication**: Automatic deduplication and series episode grouping to latest watched episode.
- **Features**:
  - **Context-Segregated Trays**:
    - `type=movie`: Filtered strictly to feature films on the Movies page.
    - `type=series`: Filtered to TV shows on the Series page, automatically grouping by series to show only the latest in-progress episode.
    - `type=collection`: Filtered to franchise movies on the Collections page.
  - **Auto-Dismiss**: Automatically marks media as finished and clears it from the resume bar when progress exceeds 92%.

---

### 2.10. Download Manager & Torrent File Selection Pipeline
- **Location**: `App\Services\Downloader\DownloadManagerService` & `Downloader/Index.vue`
- **Features**:
  - **Intelligent URL & Torrent Inspector**: `POST /api/downloads/inspect` parses direct HTTP URLs, `.torrent` files, and `magnet:` links.
  - **Multi-File Video Checklist**: Decodes bencode metadata, extracts nested file trees, detects video streams, and provides 1-click batch selection (`Select All`, `Videos Only`, `Clear`).
  - **Target Routing**: Allows routing downloads to default library directories (`Movies/`, `Series/`) or custom user-defined paths.
  - **Speed Limits & Concurrency**: Manages staging paths, concurrency limits, and throttling via persistent user settings (`/api/downloads/settings`).

---

### 2.11. Zero-Copy NTFS Hardlink Physical Organizer
- **Location**: `App\Services\Organizer\PhysicalOrganizerService`
- **Features**:
  - Employs NTFS hardlinks (`mklink /H`) so files are organized into standard paths (`Movies/Title (Year)/Title (Year) [1080p].ext`) without duplicating disk space.
  - Continuous torrent seeding remains unaffected.
  - Includes a full **Dry-Run Simulation Mode** with side-by-side filename preview before execution.
  - Multi-Episode token support: Formats `{Episode:02}` as `01-E02` for multi-episode files and synchronizes all sibling episode records in the database.

---

### 2.12. Media Scout Gap Detection & Automated Acquisition Pipeline
- **Location**: `App\Services\Scout\LibraryGapService` & `App\Services\Scout\LibraryAcquisitionService`
- **Lifecycle**:
  1. **Franchise & Season Audit**: When a collection or TV series is loaded, `LibraryGapService` queries TMDb to determine all released movies in the franchise or episodes in the season.
  2. **Gap Calculation**: Compares TMDb parts with local media items to identify missing movies or missing episodes, calculating real completion percentage.
  3. **Automated Torrent Search**: When acquisition is initiated, `LibraryAcquisitionService` queries torrent providers for healthy torrents matching the missing content.
  4. **Batch Downloader Ingestion**: Enqueues verified magnet links into the download manager.
  5. **Post-Download Flattening & Canonical Renaming**:
     - Recursively flattens nested folder trees created by torrent clients.
     - Renames video files to canonical pattern: `Show - S01E01 - Title [1080p].ext` or `Genre/Collection/Movie (Year)/Movie (Year).ext`.
     - Moves companion subtitles (`.ar.srt`, `.en.srt`) alongside the renamed video file.
     - Removes leftover empty directories and triggers scanner refresh.

---

### 2.13. Multi-Episode Ingestion, Inode Mapping & Subtitle Linking Pipeline
- **Location**: `App\Services\Scanner\VirtualLibraryScannerService` & `PhysicalOrganizerService`
- **Lifecycle**:
  1. **Multi-Episode Scene Discovery**: When a file matching `S01E01-E02`, `S01E01E02`, `S01E01-02`, or `S01E01.E02` is encountered, `SceneNameParserService` extracts `episode = 1` and `episode_end = 2`.
  2. **Constituent Episode Ingestion**: The scanner enters a loop across `range($episode, $episodeEnd)`. For each episode number:
     - Resolves the individual episode title and overview from TMDb (e.g. Episode 1: "Pilot", Episode 2: "And the Break-up Scene").
     - Creates or updates an `Episode` record in the database with that episode number and metadata.
     - Sets `file_path` on each episode record pointing to the shared multi-episode video file.
  3. **Subtitle Synchronization**: Subtitle tracks linked to the video file are replicated across all constituent episode records in the range.
  4. **Organizer Synchronization**: When organizing files with `{Episode:02}`, `PhysicalOrganizerService` outputs canonical multi-episode naming (`01-E02`) and updates all sibling episodes pointing to the shared path in one atomic database query.

---

### 2.14. Rescan & In-Place Relocation Deduplication Pipeline
- **Location**: `App\Services\Scanner\VirtualLibraryScannerService`
- **Lifecycle**:
  1. **Title Normalization**: Converts series titles into normalized alphanumeric strings (e.g. `Sense 8` → `sense8`, `Sense8` → `sense8`).
  2. **Folder Relocation Detection**: When scanning a series folder that has been moved or renamed, the scanner matches the existing series record by normalized title and prior path before considering creating a new record.
  3. **In-Place Update**: Updates `folder_path` on the existing `Series` record, and updates `file_path` on existing `Episode` and `Subtitle` records.
  4. **Zero Duplicate Rows**: Prevents orphan duplicate series entries from cluttering the catalog.
