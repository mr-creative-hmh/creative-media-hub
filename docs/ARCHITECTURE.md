# 🏛️ Creative Media Hub — Deep System Architecture

> **Author**: Eng. Hasan Mohammad Hasan  
> **Repository**: [creative-media-hub](https://github.com/mr-creative-hmh/creative-media-hub)  
> **Version**: 2.0 (Boxsets, Regional Cinema, Intelligent Parser, Hybrid Remuxer)

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
|   ├── DashboardController (Hero Spotlight & Deduplicated Watch Progress)          |
|   ├── MediaController (Movies Catalog, Regional Cinema Filters, Favorites)       |
|   ├── SeriesController (TV Shows, Seasons, Multi-Episode Deep Links)              |
|   ├── CollectionController (Movie Boxsets, Chronological Franchise Sagas)         |
|   ├── StreamController (HTTP 206 Byte-Range & Non-Blocking FFmpeg Remuxer)        |
|   ├── MetadataManagementController (Fix Match Studio, Direct ID Lookup)           |
|   ├── PhysicalOrganizerController (Zero-Copy NTFS Hardlink Engine)               |
|   ├── SubtitleController (Embedded Track Extractor & SubDL/OpenSubtitles Sync)    |
|   └── AnalyticsController (Storage Usage, Codec Breakdown, Resolution Stats)      |
+-----------------------------------------+-----------------------------------------+
                                          | Orchestration Calls
                                          v
+-----------------------------------------------------------------------------------+
|                            APPLICATION SERVICE LAYER                              |
|  +--------------------------------+  +------------------------------------------+  |
|  | VirtualLibraryScannerService   |  | SceneNameParserService (Arabic + En)     |  |
|  | - Parallel Directory Traversal |  | - Eastern Numeral Normalization          |  |
|  | - Non-Blocking Batch Queues    |  | - Folder Ancestor Context Inheritance    |  |
|  | - State Store & Pause/Resume   |  | - Collection Prefix & Sequel Detection   |  |
|  +--------------------------------+  +------------------------------------------+  |
|  +--------------------------------+  +------------------------------------------+  |
|  | MetadataAggregator (Waterfall) |  | FfmpegLocatorService & Stream Engine     |  |
|  | - TMDb (Primary + Collections) |  | - HTTP 206 Byte-Range Partial Content    |  |
|  | - OMDb (IMDb Ratings/Awards)   |  | - On-the-Fly Fragmented MP4 Remuxing     |  |
|  | - AniList (Anime Specialist)   |  | - Background FastStart Disk Caching      |  |
|  | - Arabic Translation Engine    |  | - Process Lifecycle & Orphan Reaper      |  |
|  +--------------------------------+  +------------------------------------------+  |
|  +--------------------------------+  +------------------------------------------+  |
|  | EmbeddedSubtitleDetector       |  | PhysicalOrganizerService                 |  |
|  | - FFprobe Stream Analysis      |  | - Zero-Copy NTFS Hardlink Engine         |  |
|  | - WebVTT Conversion Pipeline   |  | - Replay Protection & Conflict Matrix    |  |
|  | - SubDL & OpenSubtitles Sync   |  | - Dry-Run Simulation & Reversal Safety   |  |
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

### 3.2. Hybrid Video Streaming & Remuxing Architecture
```
                                 [Client Playback Request]
                                             │
                                             ▼
                                 [Container / Codec Audit]
                                             │
                       ┌─────────────────────┴─────────────────────┐
                       │                                           │
             [Native Web Compatible]                    [Unsupported / Legacy]
             (MP4, WebM, H.264, AAC)                     (AVI, MPEG-4, DTS, MKV)
                       │                                           │
                       ▼                                           ▼
            [HTTP 206 Partial Content]                 [On-The-Fly FFmpeg Remuxer]
             - 256KB Buffered Chunks                    - Output: fragmented MP4
             - 0% CPU Consumption                       - Direct stdout pipe to HTTP response
             - Instant Hardware Seek                    - Async background disk caching worker
```

### 3.3. Database Architecture & Concurrency Model
- **Engine**: SQLite 3 with Write-Ahead Logging (`PRAGMA journal_mode=WAL;`).
- **Concurrency**:
  - WAL mode allows unlimited concurrent readers alongside a single active writer without lock contention.
  - `busy_timeout = 5000ms` prevents transient lock timeouts during bulk scanning operations.
- **Polymorphic Progress Model**:
  - `WatchHistory` uses polymorphic relations (`watchable_type = 'movie' | 'episode'`) with null-safe queries, guaranteeing accurate progress persistence across both guest and authenticated sessions.

---

## 4. Key Design Patterns Applied

| Pattern | Component | Implementation Purpose |
| :--- | :--- | :--- |
| **Strategy & Waterfall** | `MetadataAggregator` | Cascades metadata lookup across TMDb → OMDb → AniList → TVMaze → Arabizer. |
| **Adapter Pattern** | `FfmpegLocatorService` | Abstracts platform differences between Windows, Linux, and macOS binary detection. |
| **Factory / Repository** | `SceneNameParserService` | Factory normalizing raw file/folder strings into structured domain entities. |
| **Pipelined Execution** | `VirtualLibraryScannerService` | Chunks discoveries into non-blocking batches for responsive UI streaming. |
| **Observer / Event** | `ContinueWatchingBar.vue` | Reactive real-time sync with video player progress pings. |
