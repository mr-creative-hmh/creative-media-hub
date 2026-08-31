# System Architecture Specification

## 1. High-Level Architecture Diagram

```mermaid
graph TD
    subgraph Browser ["Client SPA (Vue 3 + Inertia.js + Tailwind CSS)"]
        UI_Nav["Sidebar & Bilingual Toggle (AR / EN - RTL/LTR)"]
        UI_Grid["Virtual Catalog (Movies / Series / Seasons)"]
        UI_Filter["Multi-Axis Filter Studio & Fuzzy Search"]
        UI_Player["Cinema Video Player (HTTP 206, SRT/VTT Subtitles)"]
        UI_Org["Disk Organizer & Dry-Run Diff Studio"]
        UI_Sub["Subtitle Downloader & Missing Scanner"]
        UI_Stats["Storage & Codec Analytics Dashboard"]
    end

    subgraph Laravel ["Backend Application (Laravel 11/12 on PHP 8.3 Herd)"]
        Inertia_Layer["Inertia Page Controllers"]
        Media_Ctrl["Media & Series Controllers"]
        Stream_Ctrl["StreamController (HTTP 206 Partial Content)"]
        Org_Ctrl["DiskOrganizerController"]
        Sub_Ctrl["SubtitleController"]
        
        Scanner_Svc["FilesystemScannerService (Scene Regex Parser)"]
        Org_Svc["PhysicalOrganizerService (Move/Copy/Hardlink)"]
        Meta_Aggregator["MetadataAggregatorService"]
        Sub_Svc["SubtitleManagerService"]
        
        DB_Layer[(SQLite / MySQL Database via Eloquent ORM)]
    end

    subgraph External ["External Metadata & Subtitle APIs"]
        TMDb["The Movie Database (TMDb) API (Arabic/English)"]
        TVMaze["TVMaze API (Free TV Data)"]
        OMDb["OMDb / IMDb API"]
        AniList["AniList GraphQL (Anime Specialist)"]
        OpenSub["OpenSubtitles REST API (Hash Sync)"]
        SubDL["SubDL & Subscene APIs"]
        LocalFS["Local Filesystem (Video & Subtitle Files)"]
    end

    UI_Grid --> Inertia_Layer
    UI_Player --> Stream_Ctrl
    UI_Org --> Org_Ctrl
    UI_Sub --> Sub_Ctrl

    Inertia_Layer --> Media_Ctrl
    Media_Ctrl --> Meta_Aggregator
    Media_Ctrl --> DB_Layer
    Stream_Ctrl --> LocalFS
    Org_Ctrl --> Org_Svc
    Org_Ctrl --> Scanner_Svc
    Scanner_Svc --> LocalFS
    Org_Svc --> LocalFS
    Sub_Ctrl --> Sub_Svc

    Meta_Aggregator --> TMDb
    Meta_Aggregator --> TVMaze
    Meta_Aggregator --> OMDb
    Meta_Aggregator --> AniList
    Sub_Svc --> OpenSub
    Sub_Svc --> SubDL
```

## 2. Database Schema Design

- **`media_items`**: id, type (`movie`), title, original_title, title_ar, release_year, tmdb_id, imdb_id, overview, overview_ar, poster_path, backdrop_path, trailer_url, rating, runtime_minutes, resolution (`4K`, `1080p`, `720p`), video_codec, audio_codec, file_path, file_size_bytes, mood_tags, is_favorite, created_at, updated_at.
- **`series`**: id, title, title_ar, release_year, tmdb_id, tvmaze_id, overview, overview_ar, poster_path, backdrop_path, rating, status, total_seasons, network, is_favorite.
- **`seasons`**: id, series_id, season_number, title, overview, poster_path, air_date, episode_count.
- **`episodes`**: id, season_id, series_id, episode_number, title, title_ar, overview, overview_ar, still_path, runtime_minutes, air_date, resolution, video_codec, audio_codec, file_path, file_size_bytes.
- **`genres`**: id, name_en, name_ar, slug.
- **`genre_media`**: genre_id, media_id, series_id.
- **`people`**: id, name, name_ar, profile_path, tmdb_id, role (`actor`, `director`, `writer`).
- **`media_person`**: media_id, series_id, person_id, character_name, department.
- **`subtitles`**: id, media_item_id, episode_id, language (`en`, `ar`), format (`srt`, `vtt`), file_path, is_embedded, is_default.
- **`watch_histories`**: id, user_id, media_item_id, episode_id, progress_seconds, duration_seconds, is_completed, last_watched_at.
- **`download_items`**: id, title, type, source_url, destination_path, total_bytes, downloaded_bytes, status (`queued`, `downloading`, `completed`, `failed`), error_message.
- **`app_settings`**: key, value, type.
