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
  Fetches full metadata by exact TMDb ID (e.g. `27205`) or IMDb ID (e.g. `tt1375666`).
  ```json
  { "id": 1, "type": "movie", "external_id": "27205" }
  ```
- **`POST /api/metadata/{type}/{id}/reparse`**  
  Re-parses the file/folder name using the latest `SceneNameParserService` rules and re-enriches metadata.
- **`POST /api/metadata/{type}/{id}/convert-type`**  
  1-Click conversion: converts an accidental Series to Movie or Movie to Series.

---

## 3. Subtitles API Endpoints

- **`GET /api/subtitles/search?media_id={id}&type={movie|series}&lang={ar|en}`**  
  Searches SubDL and OpenSubtitles for subtitles matching the file title/hash.
- **`POST /api/subtitles/download`**  
  Downloads and associates a subtitle file with the media record.
- **`GET /stream/subtitles/{id}`**  
  Serves WebVTT subtitle track with proper `Content-Type: text/vtt`.

---

## 4. Physical NTFS Hardlink Organizer Endpoints

- **`POST /api/organizer/preview`**  
  Generates a dry-run preview of original paths vs new organized paths according to selected naming template.
- **`POST /api/organizer/execute`**  
  Executes NTFS hardlink creation (`mklink /H`) and updates database paths without moving original files.
