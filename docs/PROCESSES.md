# ⚙️ Creative Media Hub — Core Processes & Pipeline Lifecycle

> **Author**: Eng. Hasan Mohammad Hasan  
> **Repository**: [creative-media-hub](https://github.com/mr-creative-hmh/creative-media-hub)

---

## 1. Process Overview & Lifecycles

Creative Media Hub is powered by 9 interconnected pipelines designed for maximum data integrity, non-blocking performance, and full bilingual (Arabic/English) support.

```
       [Storage Directory Tree]
                  │
                  ▼
   1. Virtual Scanner & Crawler ──► 2. Intelligent Scene Parser
                                              │
                                              ▼
   4. Boxsets & Sagas Clustering ◄── 3. Metadata Waterfall (TMDb / OMDb / Arabizer)
                  │
                  ├──────────────────────────────┐
                  ▼                              ▼
   5. Hybrid Stream & Remuxer       6. Subtitle Extraction & Sync
                  │                              │
                  ▼                              ▼
   7. Continue Watching Engine      8. Fix Match & Error Studio
                  │
                  ▼
   9. Zero-Copy NTFS Hardlink Organizer
```

---

## 2. Deep Dive: The 9 Core Pipelines

### 2.1. Virtual Library Scanner & Multi-Worker Crawler
- **Location**: `App\Services\Scanner\VirtualLibraryScannerService`
- **Mechanism**:
  - Traverses specified directory paths (`Movies/`, `Series/`, custom folders) recursively using parallel iterators.
  - Computes file hashes / inode keys to avoid redundant disk I/O.
  - Chunks discovered items into batches of 5, pushing them to asynchronous worker queues.
  - Features real-time state persistence (`storage/app/scanner_state.json`) with pause, resume, and cancellation support.

---

### 2.2. Intelligent Scene Name Parser (Arabic & Multilingual Engine)
- **Location**: `App\Services\Organizer\SceneNameParserService`
- **Key Capabilities**:
  - **Eastern Arabic Numeral Normalization**: Converts `١, ٢, ٣, ٤, ٥` to `1, 2, 3, 4, 5`.
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

### 2.4. Boxsets & Franchise Sagas Clustering Engine
- **Location**: `App\Http\Controllers\CollectionController`
- **Rules**:
  - Aggregates movies having `collection_name` or TMDb `belongs_to_collection`.
  - Enforces a strict threshold of **`count >= 2`** to eliminate false-positive single-movie collections from `/collections`.
  - Renders chronological timeline views showing release span (e.g. *Harry Potter: 2001 - 2022 (9 films)*) and total boxset duration.

---

### 2.5. Hybrid Streaming & On-The-Fly FFmpeg Remuxer
- **Location**: `App\Http\Controllers\StreamController`
- **Modes**:
  - **Direct Stream (HTTP 206 Partial Content)**: For browser-native containers (MP4, WebM) with H.264/AAC codecs. Serves 256KB chunks with instant seeking and 0% CPU consumption.
  - **On-The-Fly Remuxer**: For unsupported formats (AVI, MKV, MPEG-4, DTS). Spawns an FFmpeg sub-process piping fragmented MP4 (`-movflags frag_keyframe+empty_moov+default_base_moof`) directly to stdout.
  - **FastStart Disk Caching**: Concurrently transcode-caches remuxed streams into `storage/app/transcodes/` for instant re-play without re-encoding.
  - **Orphan Process Reaper**: Kills hanging FFmpeg processes automatically on client disconnect or via `POST /api/stream/stop`.

---

### 2.6. Subtitle Extraction & Sync Pipeline
- **Location**: `App\Services\Subtitles\EmbeddedSubtitleDetectorService`
- **Workflow**:
  - Probes video files using `ffprobe` to identify internal subtitle tracks (SRT, ASS, PGS).
  - Extracts embedded text tracks to `.vtt` format on demand.
  - Integrates with SubDL and OpenSubtitles APIs for automated 1-click external Arabic/English subtitle downloading.

---

### 2.7. Zero-Copy NTFS Hardlink Physical Organizer
- **Location**: `App\Services\Organizer\PhysicalOrganizerService`
- **Features**:
  - Employs NTFS hardlinks (`mklink /H`) so files are organized into standard paths (`Movies/Title (Year)/Title (Year) [1080p].ext`) without duplicating disk space.
  - Continuous torrent seeding remains unaffected.
  - Includes a full **Dry-Run Simulation Mode** with side-by-side filename preview before execution.

---

### 2.8. Fix Match & Error Resolution Studio
- **Location**: `App\Http\Controllers\MetadataManagementController`
- **Capabilities**:
  - Instant live search with automatic query normalization.
  - Direct TMDb numeric ID and IMDb `tt...` ID lookup with automatic metadata replacement.
  - 1-Click Movie ↔ Series type conversion.
  - Intelligent scene re-parser button.

---

### 2.9. Deduplicated Continue Watching & Watch History Engine
- **Location**: `App\Models\WatchHistory` & `ContinueWatchingBar.vue`
- **Features**:
  - Polymorphic relation across `MediaItem` and `Episode`.
  - Grouped by parent entity to prevent clutter (e.g. only the most recently watched episode of a TV show is displayed).
  - Automatically clears items from the tray once watched percentage exceeds 92%.
