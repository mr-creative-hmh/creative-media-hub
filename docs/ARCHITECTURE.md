# 🏛️ Creative Media Hub — Deep System Architecture

> **Author**: Eng. Hasan Mohammad Hasan  
> **Repository**: [creative-media-hub](https://github.com/mr-creative-hmh/creative-media-hub)  
> **Version**: 2.5 (Media Scout, Multi-Episode Engine, Fix Match Collection Studio, Zero-Lock SQLite WAL)

---

## 1. High-Level Architectural Paradigm

Creative Media Hub is architected following **Clean Layered Architecture** and **Ports & Adapters (Hexagonal Architecture)** principles. This design guarantees:
1. **Separation of Concerns**: Presentation (Inertia/Vue), Application Orchestration (Services), Domain Rules (Models/Parsers), and Infrastructure (Filesystem/FFmpeg/APIs) operate with strict dependency boundaries.
2. **High Throughput & Non-Blocking Operations**: Heavy disk I/O, parallel directory traversals, and media transformations are separated into asynchronous chunked queues and background workers.
3. **Decoupled Metadata Providers**: External metadata services (TMDb, OMDb, AniList, TVMaze, Wikipedia) implement a unified provider contract, allowing seamless fallback cascades without tight coupling.

---

## 2. Layered Architecture Diagram

```
+-----------------------------------------------------------------------------------+
|                                PRESENTATION LAYER                                 |
|   Inertia.js 3.0 • Vue 3.5 SPA • Tailwind CSS v4 • Lucide Icons • HTML5 Cinema    |
|   State Management: Vue 3 Reactivity + LocalStorage Sync + Web Worker Bridge      |
+-----------------------------------------+-----------------------------------------+
                                          | JSON Props / Inertia Responses
                                          v
+-----------------------------------------------------------------------------------+
|                            HTTP & CONTROLLERS LAYER                               |
|   ├── DashboardController (Hero Spotlight & Top Rated Showcase)                   |
|   ├── MediaController (Movies Catalog, Regional Cinema Filters, Watch History)|
|   ├── SeriesController (TV Shows, Seasons, Episodic Scoped Watch History)     |
|   ├── CollectionController (Movie Boxsets, Chronological Sagas, Franchise Resume) |
|   ├── StreamController (HTTP 206 Byte-Range & Non-Blocking FFmpeg Remuxer)        |
|   ├── MetadataManagementController (Fix Match Studio, Direct TMDb/IMDb Lookup)    |
|   ├── FixMatchCollectionController (Franchise Studio, 1-Click Collection Linking) |
|   ├── DownloadManagerController (Torrent/Direct Inspector, Multi-File Selection)  |
|   ├── PhysicalOrganizerController (Zero-Copy NTFS Hardlink Engine)               |
|   ├── SubtitleController (Embedded Extractor, SubDL/OpenSubtitles Sync & Checker) |
|   └── AnalyticsController (Storage Usage, Codec Breakdown, Resolution Stats)      |
+-----------------------------------------+-----------------------------------------+
                                          | Orchestration Calls
                                          v
+-----------------------------------------------------------------------------------+
|                            APPLICATION SERVICE LAYER                              |
|  +--------------------------------+  +------------------------------------------+  |
|  | VirtualLibraryScannerService   |  | SceneNameParserService (Arabic + En)     |  |
|  | - Parallel Directory Traversal |  | - Eastern Numeral Normalization          |  |
|  | - Multi-Episode Ingestion      |  | - Multi-Episode Patterns (S01E01-02)     |  |
|  | - Relocation Deduplication     |  | - Folder Ancestor Context Inheritance    |  |
|  | - Non-Blocking Batch Queues    |  | - Collection Prefix & Sequel Detection   |  |
|  +--------------------------------+  +------------------------------------------+  |
|  +--------------------------------+  +------------------------------------------+  |
|  | MetadataAggregator (Waterfall) |  | FfmpegLocatorService & Stream Engine     |  |
|  | - TMDb (Primary + /find ID)    |  | - HTTP 206 Byte-Range Partial Content    |  |
|  | - OMDb (IMDb Ratings/Awards)   |  | - On-the-Fly Fragmented MP4 Remuxing     |  |
|  | - AniList (Anime Specialist)   |  | - Background FastStart Disk Caching      |  |
|  | - Arabic Translation Engine    |  | - Process Lifecycle & Orphan Reaper      |  |
|  +--------------------------------+  +------------------------------------------+  |
|  +--------------------------------+  +------------------------------------------+  |
|  | LibraryGapService (Media Scout)|  | LibraryAcquisitionService                |  |
|  | - Real-Time Saga Gaps (TMDb)   |  | - Automated Torrent Scraping & Search    |  |
|  | - TV Season Missing Episodes   |  | - Batch Download Initiation & Tracking   |  |
|  | - Completion Percentage Engine |  | - Recursive Flattening & Canonical Rename|  |
|  +--------------------------------+  +------------------------------------------+  |
|  +--------------------------------+  +------------------------------------------+  |
|  | SubtitleHealth & Cloud Sync    |  | DownloadManagerService                   |  |
|  | - LanguageDetector (Unicode+Txt)| | - Intelligent URL & Torrent Inspector    |  |
|  | - Validator (Stub/HTML Purge)  |  | - Multi-File Bencode Video Parsing       |  |
|  | - HealthCheck & Ext Renamer    |  | - Default/Custom Folder Target Routing   |  |
|  | - SubDL & OpenSubtitles Engine |  | - Staging, Speed Limits & Concurrency    |  |
|  +--------------------------------+  +------------------------------------------+  |
|  +--------------------------------+  +------------------------------------------+  |
|  | EmbeddedSubtitleDetector       |  | PhysicalOrganizerService                 |  |
|  | - FFprobe Stream Analysis      |  | - Zero-Copy NTFS Hardlink Engine         |  |
|  | - WebVTT Conversion Pipeline   |  | - Multi-Episode {Episode:02} (01-E02)    |  |
|  | - Subtitle Language Tagging    |  | - Atomic Sibling Path Updates            |  |
|  +--------------------------------+  +------------------------------------------+  |
+-----------------------------------------+-----------------------------------------+
                                          | Eloquent ORM & Storage I/O
                                          v
+-----------------------------------------------------------------------------------+
|                        DOMAIN MODELS & INFRASTRUCTURE LAYER                       |
|   ├── MediaItem (Movies, Collections, Technical Specs, Audio/Video Codecs)        |
|   ├── Series, Season, Episode (Hierarchical TV Show Domain Model)                 |
|   ├── WatchHistory (Polymorphic Progress Tracking, Deduplicated Resume State)     |
|   ├── Subtitle (Internal & External WebVTT Track Synchronization)                 |
|   ├── Genre, Person, AppSetting (Taxonomy, Credits, Configuration)                |
|   ├── SQLite WAL Engine (Concurrent Reads + Single Writer with Zero-Locking)      |
|   └── Native Binaries (FFmpeg 7.x, FFprobe 7.x, Windows NTFS Hardlink Driver)     |
+-----------------------------------------------------------------------------------+
```

---

## 3. Core Subsystems & Architectural Boundaries

### 3.1. Virtual In-Memory Scanner vs Physical NTFS Organizer
- **Virtual Library Scanner (`VirtualLibraryScannerService`)**:
  - Operates purely in **read-only mode**.
  - Traverses directory trees, computes inode signatures, extracts audio/video technical metadata, queries online metadata providers, and populates the SQLite catalog.
  - Original disk files are never moved, renamed, or modified.
- **Physical Hardlink Organizer (`PhysicalOrganizerService`)**:
  - Uses NTFS zero-copy hardlinks (`mklink /H` or PHP `link()`).
  - Creates a clean, standard media directory structure (`Movies/Title (Year)/Title (Year) [1080p].ext`) without consuming additional disk space.
  - Keeps active torrent seeding intact by creating hardlink pointers rather than moving files.

### 3.2. Hybrid Video Streaming & Intelligent Remuxing Architecture
```
                                 [Client Playback Request]
                                             │
                                             ▼
                                 [Container / Codec Audit]
                                             │
                       ┌─────────────────────┴─────────────────────┐
                       │                                           │
             [Native Web Compatible]                    [Incompatible / Non-Web Audio]
           (MP4, MKV, H.264, VP9, AV1,                    (AVI, WMV, TS, FLV, XviD,
             AAC, MP3, AC3, E-AC3)                            DTS, TrueHD, WMA)
                       │                                           │
                       ▼                                           ▼
            [Direct Stream Engine]                     [On-The-Fly FFmpeg Remuxer]
             - HTTP 206 Partial Content                 - Output: Fragmented MP4
             - 0% Server CPU Consumption                - Seamless PTS presentation sync
             - Hardware-Accelerated Decode              - Automatic A/V delay compensation
             - Self-Healing Remux Fallback              - Async background transcode cache
```

### 3.3. Subtitle Typography Engine & Bidirectional Transit Architecture
- **Curated Arabic Typography**: Native web font cascade prioritizing **Cairo**, **Plus Jakarta Sans**, **IBM Plex Sans Arabic**, **Almarai**, and **Alexandria** with dynamic runtime font style selection.
- **Bilingual Cinema Subtitles**: Multi-pass high-contrast text outlines and drop shadows prevent scene color clash.
- **Cinema Player LTR Layout Stabilization**: While catalog and management pages adopt natural RTL flow when Arabic is active, `CinemaPlayer.vue` enforces a strict Left-to-Right component direction (`dir="ltr"`). This follows industry video player standards (YouTube, Netflix, Shahid), ensuring range inputs (timeline scrubber, volume bar) advance naturally from 0% (left) to 100% (right) without inverted touch/click math, while subtitle text displays using `dir="auto"` for proper bidirectional WebVTT rendering.
- **Bidirectional Mirrored Progress**: Uses CSS horizontal scale reflection (`[dir="rtl"] #nprogress { transform: scaleX(-1); }`) to accurately advance right-to-left in Arabic on catalog pages without JavaScript overhead.
- **Top-Center Cinema Transit Island**: An isolated, floating status component listening to Inertia navigation events (`router.on('start')` / `router.on('finish')`) positioned symmetrically at top-center to eliminate header collisions in both LTR and RTL.

### 3.4. Database Architecture & Schema Consolidation
- **Engine**: SQLite 3 with Write-Ahead Logging (`PRAGMA journal_mode=WAL;`).
- **Concurrency**:
  - WAL mode allows unlimited concurrent readers alongside a single active writer without lock contention.
  - `busy_timeout = 5000ms` prevents transient lock timeouts during bulk scanning operations.
- **Consolidated 2-Migration Master Schema**:
  - All legacy incremental migration fragments have been consolidated into two authoritative files:
    1. `database/migrations/0001_01_01_000000_create_system_tables.php`: Foundation system tables (`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`).
    2. `database/migrations/2026_09_01_000000_create_media_hub_tables.php`: Complete cinema domain schema (`media_items`, `series`, `seasons`, `episodes`, `genres`, `media_genre`, `series_genre`, `people`, `media_person`, `series_person`, `subtitles`, `watch_history`, `downloads`, `app_settings`).
- **Local-First Single-User Cinema Model**:
  - Eliminates multi-tenant authentication overhead, login screens, and token management for a high-performance local appliance experience.
  - `WatchHistory` uses polymorphic relations (`watchable_type = 'media_item' | 'episode'`) with null-safe queries, guaranteeing accurate resume state across all sessions.
- **Production-Only Clean Seeder**:
  - `MediaLibrarySeeder` provides idempotent initialization of essential application settings and 15 standard TMDb genres with Arabic and English localization, with zero fake mock movies or demo records.

### 3.5. Hero Spotlight & Media Resolution Pipeline
- **Explicit Type Tagging**: Hero carousel slides are tagged explicitly with `type: 'movie'` or `type: 'series'`.
- **Episodic Resolution**: For TV series slides, the backend pre-resolves Season 1 Episode 1 (`first_episode`) along with its season playlist and subtitles. When a user clicks "Play Now" on a series slide, the cinema player immediately launches Episode 1 rather than accidentally streaming a movie with a matching numeric ID.
- **Action Bifurcation**: "Play Now" initiates immediate playback, while "More Details" opens the modal for movies or transitions directly to the series view page (`/series/{slug}`).

### 3.6. Media Scout Gap Tracking & Automated Acquisition Pipeline
- **Real-Time Gap Engine (`LibraryGapService`)**:
  - Audits movie franchises against official TMDb collection parts, calculating completion percentage and surfacing missing movies with their original release dates.
  - Audits TV shows against official season episode counts, identifying missing episodes and season gaps.
- **Smart Acquisition (`LibraryAcquisitionService`)**:
  - Scrapes and verifies season packs or individual episode torrents with automated health rating.
  - Initiates batch downloads through the download manager.
  - Post-download automation: recursively unpacks/flattens nested torrent subfolders, applies canonical renaming (`Show - S01E01 - Title [1080p].ext`), relocates matching subtitle files (`.ar.srt`, `.en.srt`), and updates library catalog records without manual user intervention.

### 3.7. Multi-Episode Scene Ingestion & Database Architecture
- **Parser Canonical Formats**: `SceneNameParserService` parses combined episodes (`S01E01-E02`, `S01E01E02`, `S01E01-02`, `S01E01.E02`) through Pattern A, extracting `episode` and `episode_end`.
- **Database Multiplication**: `VirtualLibraryScannerService::indexSeriesEpisode()` loops over `range($episode, $episodeEnd)`, creating or updating distinct `Episode` database records for each constituent episode. Each record receives its individual title and synopsis from TMDb while referencing the shared physical file path.
- **Subtitle Link Replication**: Subtitle tracks associated with the multi-episode file are linked to all constituent episode database entities so that playback from any episode in the range displays full subtitles.
- **Physical Organizer Integration**: The `{Episode:02}` token detects `episode_end` and formats the segment as `01-E02`. During reorganization, `updateDatabasePath()` synchronizes all sibling episode records pointing to that file path in one atomic database query.

### 3.8. Strict Multi-Movie Collections (`owned >= 2`) & FixMatch Studio
- **Strict Franchise Threshold**: The Collections catalog (`/collections`) strictly enforces `count >= 2` to eliminate solitary single-movie collections.
- **Media Scout Completion Badges**: Displays dynamic progress badges (`In Progress` vs `Complete`) based on total parts in the franchise.
- **FixMatch Collection Studio (`FixMatchCollectionController`)**:
  - Dedicated Collection tab in `FixMatchModal.vue`.
  - Enables instant 1-click assignment of a movie to an existing collection or creation of a new custom franchise.
  - Automatically queries and links TMDb collection ID metadata and updates the movie's physical folder hierarchy on disk.
- **CLI Collection Auditor**: `php artisan library:audit-collections {--fix} {--align-physical}` scans the entire library for unlinked sequels, auto-assigns collection metadata, and reorganizes movie folders.

---

## 4. Key Design Patterns Applied

| Pattern | Component | Implementation Purpose |
| :--- | :--- | :--- |
| **Strategy & Waterfall** | `MetadataAggregator` | Cascades metadata lookup across TMDb → OMDb → AniList → TVMaze → Arabizer. |
| **Adapter Pattern** | `FfmpegLocatorService` | Abstracts platform differences between Windows, Linux, and macOS binary detection. |
| **Factory / Repository** | `SceneNameParserService` | Factory normalizing raw file/folder strings into structured domain entities. |
| **Pipelined Execution** | `VirtualLibraryScannerService` | Chunks discoveries into non-blocking batches for responsive UI streaming. |
| **Observer / Event** | `WatchHistoryBar.vue` | Reactive real-time sync with video player progress pings. |

---

## 5. Database Disaster Recovery & Selective Restore Architecture

Creative Media Hub includes an enterprise-grade disaster recovery and database backup subsystem designed for zero data loss and granular restore control.

### 5.1. Architecture & Table Mapping
The database consists of standalone catalog tables, hierarchical TV tables, polymorphic relationship pivots, and operational records:
- **Movies (`movies` section)**: Maps to `media_items` table, `genreables` (filtered by `genreable_type` = `App\Models\MediaItem`), and `personables`.
- **Series (`series` section)**: Maps to `series`, `seasons`, and `episodes` tables, plus corresponding `genreables` and `personables`.
- **Subtitles (`subtitles` section)**: Maps to `subtitles` table.
- **Settings (`settings` section)**: Maps to `app_settings` key-value table.
- **Watch History (`watch_history` section)**: Maps to `watch_histories` table.
- **Shared Entities (`genres`, `people`)**: Automatically synchronized using `updateOrInsert` whenever movies or series are restored.

### 5.2. Selective Overwrite Guarantees
When a user restores in **Clean Overwrite** mode with specific sections (e.g. `["movies"]`):
1. Foreign key constraints are safely bypassed (`PRAGMA foreign_keys = OFF;` in SQLite or `SET FOREIGN_KEY_CHECKS = 0;` in MySQL).
2. The entire restoration is wrapped in an atomic database transaction (`DB::transaction()`).
3. Only target records matching the selected sections are purged.
4. **All unselected sections remain 100% intact and untouched.**
5. Foreign key checks are re-enabled in a `finally` block regardless of transaction success or failure.

### 5.3. Dual-Format Support (JSON & SQLite)
- **JSON Format**: Human-readable, structured dump containing metadata counts and table records. Restores across different database engines.
- **SQLite Format**: Binary `.sqlite` / `.db` snapshots can be restored as full clones or selectively extracted table-by-table via PDO memory queries without overwriting the active database.

---

## 6. Universal Job Center & Unified Background Orchestration

All asynchronous operations in Creative Media Hub report to a unified state coordination layer (`UnifiedJobCenterModal.vue` backed by `useActivityCenter.ts` and `useActivityCenterState.ts`):
- **Universal Status Aggregation**: Tracks Scanner, Hardlink Organizer, Subtitle Auditor, and Folder Watcher states concurrently.
- **Pause & Resume Protocol**: Jobs maintain non-blocking execution loops with sleep yields and check cancellation tokens at the start of each iteration.
- **Replay Safety & Rollback**: Actions that alter disk state (such as file renames or hardlinks) maintain atomic execution logs with reverse-direction rollback capabilities.
