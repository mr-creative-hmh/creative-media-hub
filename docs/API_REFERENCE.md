# 📡 Creative Media Hub — REST & Streaming API Reference

> **Author**: Eng. Hasan Mohammad Hasan  
> **Repository**: [creative-media-hub](https://github.com/mr-creative-hmh/creative-media-hub)

---

## 1. Streaming & Playback Endpoints

### 1.1. Direct Stream (HTTP 206 Partial Content)
- **`GET /stream/movie/{id}`**  
  Streams a movie file directly using byte-range headers (`Range: bytes=0-`).
- **`GET /stream/episode/{id}`**  
  Streams a TV series episode file directly using byte-range headers.

### 1.2. On-The-Fly FFmpeg Remuxer
- **`GET /stream/remux/movie/{id}`**  
  Remuxes an unsupported container/codec (e.g. AVI, MKV, DTS) into fragmented MP4 on the fly and streams it immediately to the browser.
- **`GET /stream/remux/episode/{id}`**  
  Remuxes an episode on the fly into fragmented MP4.

### 1.3. Stream Management & Transcode Cache Status
- **`GET /api/stream/cache-status?file_path={path}`**  
  Checks if a background transcode cache file exists and returns its size and status.
- **`POST /api/stream/stop`**  
  Gracefully terminates any running FFmpeg sub-processes for a given client stream.

---

## 2. Metadata & Fix Match Studio Endpoints

### 2.1. Live Metadata Search
- **`GET /api/media/search-metadata?query={title}&year={year}`**  
  Searches online providers (TMDb, OMDb, etc.) for movie metadata candidates.
- **`GET /api/series/search-metadata?query={title}&year={year}`**  
  Searches online providers for TV series metadata candidates.

### 2.2. Fix Match & Manual Update
- **`POST /api/media/{id}/fix-match`**  
  Applies selected metadata candidate (title, Arabic title, TMDb ID, poster, backdrop, rating, overview) to the movie record.
- **`POST /api/series/{id}/fix-match`**  
  Applies selected metadata candidate to the TV series record.
- **`POST /api/media/{id}/update-metadata`**  
  Saves manual metadata edits made by the user.

### 2.3. Direct ID Lookup & 1-Click Utilities
- **`POST /api/metadata/lookup-id`**  
  Fetches full metadata by exact TMDb numeric ID (`27205`), IMDb ID (`tt1375666`), or direct TMDb/IMDb URLs, updates the local database model, caches high-res artwork, and syncs genres and seasons.
  ```json
  {
    "id": 1,
    "type": "movie",
    "external_id": "27205"
  }
  ```
  **Response:**
  ```json
  {
    "success": true,
    "message": "Metadata updated successfully from TMDb",
    "media": { "id": 1, "title": "Inception", "title_ar": "استهلال", ... },
    "details": { ... }
  }
  ```
- **`POST /api/metadata/{type}/{id}/reparse`**  
  Re-parses the file/folder name using the latest `SceneNameParserService` rules and re-enriches metadata.
- **`POST /api/metadata/{type}/{id}/convert-type`**  
  1-Click conversion: converts an accidental Series to Movie or Movie to Series.

---

## 3. Subtitles API Endpoints

### 3.1. Subtitle Search & Ingestion
- **`GET /api/subtitles/search?media_id={id}&type={movie|series}&lang={ar|en}`**  
  Searches real online providers (SubDL and OpenSubtitles v3 via Cinemeta IMDb lookup) for subtitles matching the title or IMDb ID.
- **`POST /api/subtitles/download`**  
  Downloads, decompresses (.gz / .zip), normalizes encoding (CP1256 / UTF-8), saves adjacent to the video file, and registers the subtitle track in the database.
- **`GET /stream/subtitles/{id}`**  
  Serves WebVTT subtitle track with proper `Content-Type: text/vtt`.

### 3.2. Subtitle Health Checker & Normalizer
- **`POST /api/subtitles/check`**  
  Scans directories for subtitles, detects dialogue languages, removes corrupt stubs, and standardizes file extensions.
  ```json
  {
    "fix": true,
    "dry_run": false,
    "delete_invalid": true,
    "path": "C:/Media"
  }
  ```
  **Response:**
  ```json
  {
    "success": true,
    "total_scanned": 142,
    "valid_count": 138,
    "invalid_count": 4,
    "renamed_count": 89,
    "deleted_count": 4,
    "languages": { "ar": 65, "en": 70, "fr": 3 },
    "results": [ ... ]
  }
  ```

---

## 4. Scoped Continue Watching Endpoints

- **`GET /api/continue-watching?type={movie|series|collection}`**  
  Returns deduplicated, in-progress items scoped strictly by media context:
  - `type=movie`: In-progress standalone feature films.
  - `type=series`: Most recently watched in-progress episode per TV series.
  - `type=collection`: In-progress movies belonging to a franchise collection.
- **`POST /api/watch-history`**  
  Records current playback timestamp and marks media as completed once progress exceeds 92%.

---

## 5. Downloader & Torrent Inspection Endpoints

- **`POST /api/downloads/inspect`**  
  Inspects a direct HTTP URL, torrent file, or magnet link. Returns detected media title, inferred type (`movie` or `series`), total size, and a multi-file selection tree with video badges.
  ```json
  {
    "source": "magnet:?xt=urn:btih:...",
    "type": "torrent"
  }
  ```
- **`POST /api/downloads/add`**  
  Enqueues an inspected download with user-selected file indices, mode (`direct` vs `torrent`), and destination folder (`default` vs `custom`).
- **`GET /api/downloads/settings`** & **`POST /api/downloads/settings`**  
  Retrieves and persists user preferences for default staging paths, movies path, TV shows path, max concurrent downloads, and rate limits.

---

## 6. Physical NTFS Hardlink Organizer Endpoints

- **`POST /api/organizer/preview`**  
  Generates a dry-run preview of original paths vs new organized paths according to selected naming template.
- **`POST /api/organizer/execute`**  
  Executes NTFS hardlink creation (`mklink /H`) and updates database paths without moving original files.

---

## 7. Settings & Storage Maintenance Endpoints

### 7.1. Media Cache Statistics
- **`GET /api/settings/media-cache-stats`**  
  Calculates the current disk footprint and file count of generated video stream chunks and transcode cache in `storage/app/cache/media_streams/`.
  **Response:**
  ```json
  {
    "success": true,
    "stats": {
      "size_bytes": 147582048,
      "size_formatted": "140.75 MB",
      "file_count": 3
    }
  }
  ```

### 7.2. Clean Media Streams Cache
- **`POST /api/settings/clear-media-cache`**  
  Safely wipes all cached remux/transcode MP4 and chunk files from disk and returns the total freed space.
  **Response:**
  ```json
  {
    "success": true,
    "message": "Media transcode cache cleared successfully.",
    "freed_bytes": 147582048,
    "freed_formatted": "140.75 MB",
    "stats": {
      "size_bytes": 0,
      "size_formatted": "0 B",
      "file_count": 0
    }
  }
  ```

