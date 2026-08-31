# 🎬 Creative Media Streaming Library (مكتبة البث والوسائط الإبداعية)

A next-generation personal media server, physical disk organizer, and automated cataloging platform for movies and TV series, built with **Laravel 11/12**, **Vue 3 (Inertia.js)**, **Tailwind CSS**, and running natively on **Laravel Herd**.

---

## 🌟 Key Features

1. **✨ Virtual Media Hub & Discovery**:
   - High-impact glassmorphic cinema UI with ambient backdrop glow.
   - Separate, dedicated interfaces for **Movies** and **TV Series** (multi-seasons & episode guides).
   - Multi-axis filtering by Genre, Decade/Year, Quality (`4K HDR`, `1080p`, `720p`), Audio Codec, and Watch Status (`Unwatched`, `In Progress`, `Completed`).
   - Universal fast fuzzy search across titles, directors, actors, keywords, and studios.
   - **AI Mood & Vibe Matcher**: Categorize and discover media based on emotional atmospheres (*Mind-Bending*, *Cozy Comfort*, *High Adrenaline*, *Cyberpunk Noir*).
   - **Interactive Cast Connection Explorer**: Click any actor/director to browse their full filmography cross-referenced against your local library.

2. **🗂️ Physical Disk Organizer & Renaming Studio**:
   - **Scene Release Parser**: Intelligently cleans scene tags (`x265`, `1080p`, `BluRay`, `NF.WEB-DL`) into standardized filenames.
   - **Plex / Jellyfin / Kodi / Sonarr / Radarr standard structures**:
     - Movies: `Movies/{Title} ({Year})/{Title} ({Year}) [{Resolution}].{ext}`
     - TV Shows: `TV Shows/{Title} ({Year})/Season {Season:02}/{Title} - S{Season:02}E{Episode:02} - {EpisodeTitle} [{Resolution}].{ext}`
   - **Interactive Dry-Run & Diff Preview**: Visual before-and-after comparison with collision detection before making any disk modifications.
   - Safe operations: **Move**, **Copy**, and **Hardlink** modes + Execution Undo history.

3. **🌐 Free Multi-Tier Metadata Scraper Fallback**:
   - **Tier 1**: The Movie Database (TMDb) with full English & Arabic (`ar-SA`) metadata, HD posters, backdrops, and trailer embeds.
   - **Tier 2**: TVMaze API (100% Free & No API key needed for TV shows and episode guides).
   - **Tier 3**: OMDb API (IMDb scores, Metacritic, Rotten Tomatoes).
   - **Tier 4**: AniList GraphQL & Jikan API (100% Free anime specialist).
   - **Tier 5**: Wikipedia / Wikidata & Local `.nfo` offline fallback.

4. **💬 Free Subtitle Downloader & Missing Subtitle Auto-Scanner**:
   - Integrated OpenSubtitles REST API (file hash exact sync) & SubDL / Subscene scrapers.
   - Automatic library scanner flagging missing Arabic (`.ar.srt`) and English (`.en.srt`) subtitles.
   - 1-click single download and background batch downloader.

5. **🍿 Built-in Cinema Video Player**:
   - `HTTP 206 Partial Content` binary range streaming with ultra-smooth seek and scrub.
   - Subtitle selector (embedded subtitle streams + external `.srt` / `.vtt`).
   - Multi-speed playback (`0.5x` to `2.0x`), Picture-in-Picture, Fullscreen, and Next-Episode auto-countdown.
   - Resume playback ("Continue Watching") with persistent timestamp sync.

6. **🌍 Bilingual (Arabic / English) & RTL Engine**:
   - Full Right-to-Left (**RTL**) layout for Arabic with Arabic typography (**Cairo / Tajawal**).
   - Left-to-Right (**LTR**) layout for English (**Inter / Outfit**).
   - Instant live language switcher without page reload.

7. **📊 Storage & Codec Analytics Dashboard**:
   - Visual breakdown of drive usage, resolution ratios (4K vs 1080p), video/audio codecs (HEVC vs H264 vs AV1), and total watch hours.

---

## 🚀 Quick Start in Laravel Herd

```bash
# 1. Start Herd (Make sure PHP 8.3+ is active)
# The application is available at:
http://creative-media-streaming-library.test

# 2. Run Database Migrations & Sample Seeders
php artisan migrate:fresh --seed

# 3. Compile Frontend Assets / Hot-Reload
npm run dev

# 4. Run Test Suite
php artisan test
```
