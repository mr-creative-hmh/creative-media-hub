# 📁 Creative Media Hub — Codebase Structure & Component Map

> **Author**: Eng. Hasan Mohammad Hasan  
> **Repository**: [creative-media-hub](https://github.com/mr-creative-hmh/creative-media-hub)  
> **Version**: 2.0 (Consolidated Schema, Clean Seeder, LTR Cinema Player, Zero-Auth)

---

## 1. Directory Tree Overview

```text
creative-media-hub/
├── app/
│   ├── Console/Commands/       # Artisan CLI commands (subtitles:check, etc.)
│   ├── Http/
│   │   ├── Controllers/        # Thin HTTP controllers delegating to services
│   │   └── Middleware/         # Inertia share, locale & application middleware
│   ├── Models/                 # Eloquent domain models with relationships & scopes
│   └── Services/               # Domain & application service layer
│       ├── Download/           # Torrent/HTTP download managers & bencode parsers
│       ├── Media/              # Codec detection, FFprobe wrappers & media helpers
│       ├── Metadata/           # MetadataAggregator & provider adapters (TMDb, OMDb)
│       ├── Organizer/          # SceneNameParser & NTFS Hardlink organizer
│       ├── Database/           # Full & selective disaster recovery, snapshot management
│       ├── Scanner/            # VirtualLibraryScanner & batch queue workers
│       ├── Streaming/          # FFmpeg locator, process reaper & remux engine
│       └── Subtitles/          # SubDL, OpenSubtitles, health auditor & normalizer
├── config/                     # Laravel configuration files
├── database/
│   ├── migrations/             # Consolidated master migrations (system & media hub)
│   └── seeders/                # Production seeder (MediaLibrarySeeder)
├── desktop/                    # Electron main & preload scripts for Windows standalone
├── docs/                       # Comprehensive documentation suite
│   ├── API_REFERENCE.md        # REST & streaming endpoints reference
│   ├── ARCHITECTURE.md         # Deep layered architecture & SQLite WAL model
│   ├── DEVELOPER_GUIDE.md      # Setup, testing, and contribution guide
│   ├── PROCESSES.md            # The 9 core pipeline lifecycles
│   └── STRUCTURE.md            # Directory tree & component catalog (this document)
├── resources/
│   ├── css/                    # Tailwind CSS v4 styling & cinema keyframe animations
│   ├── js/
│   │   ├── app.ts              # Inertia & Vue 3 application bootstrap
│   │   ├── components/         # Modular Vue 3 components
│   │   │   ├── activity/       # UnifiedJobCenterModal (universal background task orchestrator)
│   │   │   ├── common/         # Orbit loaders, transit island, modal shells
│   │   │   ├── layout/         # Navigation bars, search drawers, page shells
│   │   │   ├── media/          # Hero banner, media cards, detail modals, trays
│   │   │   ├── player/         # CinemaPlayer, controls, equalizer, subtitle search
│   │   │   └── subtitles/      # Subtitle health checker & downloader modals
│   │   ├── composables/        # Shared Vue reactivity hooks (useI18n, useTheme)
│   │   ├── pages/              # Inertia page views
│   │   │   ├── Dashboard/      # Hero spotlight & top-rated showcase
│   │   │   ├── Movies/         # 4K films catalog with regional filters
│   │   │   ├── Series/         # TV shows, seasons, and episode browsing
│   │   │   ├── Collections/    # Verified franchise sagas & boxsets
│   │   │   ├── Subtitles/      # Subtitle health studio & cloud search
│   │   │   ├── Downloader/     # Multi-file torrent & direct HTTP downloader
│   │   │   ├── Organizer/      # Zero-copy NTFS hardlink organizer
│   │   │   ├── Settings/       # Library paths, transcode cache, provider keys
│   │   │   └── Analytics/      # Disk storage, codecs & resolution analytics
│   │   └── types/              # TypeScript contracts for Inertia props & domain entities
│   └── views/                  # Blade entrypoint (app.blade.php)
├── routes/
│   ├── web.php                 # Inertia SPA page routes & streaming endpoints
│   └── console.php             # Console routes and schedules
└── tests/
    └── Feature/                # Automated feature test suites (71 tests, 600 assertions)
```

---

## 2. Backend Architecture: Controllers & Services

### 2.1. HTTP Controllers (`app/Http/Controllers/`)
| Controller | Responsibilities |
| :--- | :--- |
| `DashboardController` | Aggregates hero spotlight media (explicitly tagged movies and TV shows with Season 1 Episode 1 pre-resolved) and top-rated shelves. |
| `MediaController` | Handles movie catalog listing, regional origin filtering, genre filtering, search, and continue watching records. |
| `SeriesController` | Manages TV show catalog, season/episode hierarchy, and episodic continue watching progress. |
| `CollectionController` | Groups multi-film movie franchises (`count >= 2`) into chronological saga timelines, integrated with Media Scout real-time gap tracking and completion badges. |
| `StreamController` | Serves HTTP 206 byte-range partial content and streams on-the-fly FFmpeg fragmented MP4 remuxes with process lifecycle cleanup. |
| `MetadataManagementController` | Powers Fix Match Studio, direct TMDb/IMDb ID lookup (`/api/metadata/lookup-id`), 1-click movie↔series conversion, and scene re-parsing. |
| `FixMatchCollectionController` | Powers franchise management in FixMatchModal, 1-click collection assignment, custom collection creation, TMDb collection ID linkage, and physical folder reorganization. |
| `DownloadManagerController` | Manages direct HTTP and torrent downloads, torrent file tree inspection, and target path routing. |
| `PhysicalOrganizerController` | Simulates and executes zero-copy NTFS hardlink migrations with dry-run verification and multi-episode `{Episode:02}` token formatting (`01-E02`). |
| `SubtitleController` | Handles SubDL and OpenSubtitles v3 search, on-the-fly archive decompression, WebVTT serving, and health checks. |
| `SettingsController` | Manages media library directories, scanner state, API keys, and transcode cache clearing. |
| `AnalyticsController` | Provides storage usage metrics, resolution distributions, codec breakdowns, and library counts. |

---

### 2.2. Service Layer (`app/Services/`)
| Service | Purpose |
| :--- | :--- |
| `Scanner/VirtualLibraryScannerService` | Discovers media files on disk, computes inode keys, ingests multi-episode files with individual database entity multiplication, and deduplicates relocated series folders without creating redundant rows. |
| `Organizer/SceneNameParserService` | Normalizes release titles, extracts Eastern/Western numerals, strips scene tags, parses multi-episode patterns (`S01E01-E02`, `S01E01E02`, `S01E01-02`, `S01E01.E02`), and inherits directory ancestor context. |
| `Organizer/PhysicalOrganizerService` | Generates NTFS hardlink hierarchies (`mklink /H`) maintaining active torrent seeding, formats multi-episode `{Episode:02}` tokens as `01-E02`, and synchronizes all sibling episode database paths atomically. |
| `Scout/LibraryGapService` | Audits movie sagas and TV seasons against TMDb, calculates missing parts/episodes, completion percentages, and gap severity. |
| `Scout/LibraryAcquisitionService` | Scrapes verified torrents, initiates batch downloads, and handles post-download pipeline (folder flattening, canonical renaming, companion subtitle movement). |
| `Metadata/MetadataAggregator` | Cascades metadata queries across TMDb, OMDb, AniList, and TVMaze with automated Arabic translation. |
| `Streaming/FfmpegLocatorService` | Auto-detects FFmpeg/FFprobe binaries across Windows, Linux, and macOS environments. |
| `Subtitles/SubtitleHealthCheckService` | Inspects subtitle cue text, detects true dialogue language, strips corrupt stubs, and standardizes file extensions. |
| `Subtitles/SubtitleManagerService` | Manages subtitle extraction, cloud search, archive decompression, and database association. |

---

## 3. Database Schema & Models

### 3.1. Consolidated Migrations
- `0001_01_01_000000_create_system_tables.php`: Standard Laravel system infrastructure (`sessions`, `cache`, `cache_locks`, `jobs`, `failed_jobs`).
- `2026_09_01_000000_create_media_hub_tables.php`: Complete cinema domain schema (`media_items`, `series`, `seasons`, `episodes`, `genres`, `media_genre`, `series_genre`, `people`, `media_person`, `series_person`, `subtitles`, `watch_history`, `downloads`, `app_settings`).

### 3.2. Eloquent Domain Models (`app/Models/`)
- `MediaItem`: Represents a movie or standalone media file with video/audio specs, artwork, and ratings.
- `Series`: Represents a TV series container with bilingual metadata and franchise relations.
- `Season`: Belongs to a series, containing season numbers and poster artwork.
- `Episode`: Belongs to a season, containing file paths, episode numbers, runtime, and technical specs.
- `Subtitle`: Stores internal/external subtitle tracks associated with a `MediaItem` or `Episode`.
- `WatchHistory`: Polymorphic progress tracker (`watchable_type = 'media_item' | 'episode'`) storing position, duration, and completed flags.
- `Genre`: Standardized genres with English and Arabic naming.
- `Person`: Cast and crew members linked to movies and series.
- `Download`: Tracks ongoing and completed direct or torrent downloads.
- `AppSetting`: Key-value configuration for library paths, transcode settings, and API credentials.

---

## 4. Frontend Vue 3 Component Catalog (`resources/js/`)

### 4.1. Cinema Player (`components/player/`)
- `CinemaPlayer.vue`: Fullscreen cinematic player with native keyboard shortcuts, audio equalizer, playback speed presets, subtitle selector, and in-player cloud subtitle downloader. Built with strict `dir="ltr"` container orientation for stable timeline scrubbers across all interface languages.
- `CinemaLoader.vue`: Celestial multi-orbit glowing loader with animated comet particles for buffering states.
- `PlayerSettingsMenu.vue`: Dropdown menu for audio boost, equalizer presets, and stream source switching.

### 4.2. Media Showcase (`components/media/`)
- `HeroBanner.vue`: Dynamic hero carousel with autoplay, rating badges, background backdrop cross-fading, and type-safe "Play Now" / "More Details" dispatchers.
- `MediaCard.vue`: Responsive media card with hover zoom, quick action triggers, rating badges, and resolution tags.
- `MediaDetailModal.vue`: Rich modal overlay displaying synopses, cast credits, technical codecs, audio channels, and direct playback triggers.
- `FixMatchModal.vue`: Comprehensive metadata correction modal with Direct ID lookup, Arabic translation toggle, movie/series converter, and dedicated Collection Studio tab for 1-click franchise linking and physical reorganization.
- `WatchHistoryBar.vue`: Context-segregated horizontal progress bar with instant resume buttons.

### 4.3. Subtitles & Health (`components/subtitles/`)
- `SubtitleSearchModal.vue`: In-player and standalone modal for searching and downloading real cloud subtitles from SubDL and OpenSubtitles v3.
- `SubtitleHealthModal.vue`: Interactive audit dialog for running dry-run simulations, language detection audits, and batch cleaning.
