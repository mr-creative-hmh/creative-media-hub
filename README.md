# 🎬 Creative Media Hub

<div align="center">

![Creative Media Hub Banner](https://raw.githubusercontent.com/mr-creative-hmh/creative-media-streaming-library/main/public/favicon.svg)

### **Next-Generation Personal Streaming Server, Library Organizer & Cinema Player**
*Engineered with Laravel 12, Inertia.js, Vue 3, Tailwind CSS, SQLite, FFmpeg & Video.js*

[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat&logo=php&logoColor=white)](https://php.net)
[![Laravel 12](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue 3](https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=flat&logo=vue.js&logoColor=white)](https://vuejs.org)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-2.x-9553E9?style=flat&logo=inertia&logoColor=white)](https://inertiajs.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4.x-06B6D4?style=flat&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![SQLite](https://img.shields.io/badge/SQLite-3.x-003B57?style=flat&logo=sqlite&logoColor=white)](https://sqlite.org)
[![FFmpeg](https://img.shields.io/badge/FFmpeg-Supported-007808?style=flat&logo=ffmpeg&logoColor=white)](https://ffmpeg.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

</div>

---

## 🌟 Overview

**Creative Media Hub** is a self-hosted media streaming platform, physical library organizer, and metadata manager. It turns any local movie/series collection into a streaming experience with full Arabic and English metadata, real-time remuxing, embedded and external subtitle sync, and portable drive support.

---

## ✨ Key Features

### 🎥 1. Cinema Player Engine
- **Direct & Remux Streaming Modes**: Plays MP4, WebM, MKV, AVI, WMV, TS, and MOV files. Non-browser-native formats (AVI, WMV, TS) are remuxed in real time via FFmpeg into streamable fragmented MP4 pipelines.
- **Real-Time Buffer Bar**: YouTube-style dual-layer progress bar displaying server cache progress and client download buffer in real-time.
- **Smart Subtitle Engine**:
  - Auto-detection of external `.srt`, `.vtt`, `.ass`, `.ssa`, and `.sub` files.
  - On-the-fly extraction of embedded subtitle tracks from MKV/MP4 containers via FFmpeg.
  - Subtitle styling, offset sync ($\pm 100$ms), font sizing, and Arabic/English language recognition.
  - Subtitle default set to **OFF** with standard baseline positioning.
- **Audio Track Switcher**: Switch between multi-language audio streams (English, Arabic, Commentary, etc.) seamlessly.
- **Playback Memory**: Automatically remembers playback progress per movie/episode and powers the **"Continue Watching"** shelf.

### 🌐 2. Multi-Provider Arabic & English Metadata Waterfall
- **Waterfall Architecture**: Cascades across multiple providers to guarantee 100% complete metadata:
  1. **TMDb (The Movie Database)**: High-resolution posters, backdrops, ratings, official release year, overview, and cast.
  2. **Wikipedia & Wikidata LangLinks**: Automated English-to-Arabic language link extraction and summary fetching.
  3. **MyMemory Translation API**: Automatic fallback translation for missing Arabic titles and overviews.
  4. **TVMaze & OMDb & AniList**: Fallback providers for television series, anime, and niche releases.
  5. **Local NFO & Image Cache**: Uses local `poster.jpg`, `backdrop.jpg`, and `.nfo` files when offline.
- **Fix Match Studio**: Intuitive UI modal to manually search, re-match, edit titles/overviews in English & Arabic, or upload custom artwork.

### ⚡ 3. High-Speed Virtual Library Scanner
- **Sub-Second File Indexing**: Indexes hundreds of media files in seconds using local filename parsing (`SceneNameParserService`) without blocking on network queries.
- **Live Interactive Controls**: Start, pause, resume, and cancel scans on the fly with live progress bars and activity logs.
- **Directory Monitoring**: Multi-folder monitoring for Movies, Series, and Mixed collections with automatic subfolder discovery.

### 📁 4. Physical Library Organizer
- **Scene Name Parser**: Cleans complex scene releases (e.g. `[YTS.MX] Inception.2010.1080p.BluRay.x264-SPARKS.mkv` $	o$ `Inception (2010) [1080p FHD].mkv`).
- **Dry-Run Preview**: Preview exact renaming and folder restructuring actions before applying any disk changes.
- **Preset Rules**: Customizable templates for standard Plex/Kodi naming conventions.

### 📑 5. Playlists & Library Management
- **Custom Playlists**: Create, reorder, and stream custom movie and series playlists.
- **Favorites & Watchlist**: One-click bookmarking for movies and TV series.
- **Responsive Dark UI**: Designed with Tailwind CSS, custom fonts (Cairo, Outfit, Plus Jakarta Sans), glassmorphism, and fluid animations.

---

## 🚀 Running as a Windows Portable App (External Hard Drive)

You can run **Creative Media Hub** directly from an external hard drive (USB HDD / SSD) on any Windows computer **without installing PHP, Node.js, or Composer on the host machine!**

### Portable Folder Structure
Place the project on your external drive (e.g., `E:\CreativeMediaHub\`):
```
E:\CreativeMediaHub├── Start-CreativeMediaHub.bat       <-- Double click to start & open browser
├── Stop-CreativeMediaHub.bat        <-- Stop background server
├── Start-CreativeMediaHub.ps1       <-- PowerShell launcher
├── .env                             <-- Configured for SQLite
├── database/
│   └── database.sqlite              <-- Database travels with your drive!
├── public/                          <-- Pre-built static assets (Vite)
├── app/                             <-- Application logic
├── vendor/                          <-- Composer dependencies
└── php/                             <-- (Optional) Portable PHP 8.2+ folder
    └── php.exe
```

### Steps to Run Portably:
1. **Build Assets Once**: Run `npm run build` on your development PC before copying to the external drive.
2. **Copy to External Drive**: Copy the entire project folder to your external drive.
3. *(Optional)* **Add Portable PHP**: If the target computer does not have PHP installed:
   - Download the **PHP 8.2 or 8.3 Non-Thread Safe (x64) Zip** from [windows.php.net](https://windows.php.net/download/).
   - Extract it into a `php` folder inside the project (so `E:\CreativeMediaHub\php\php.exe` exists).
   - In `php\php.ini`, ensure `extension=pdo_sqlite`, `extension=sqlite3`, `extension=curl`, `extension=mbstring`, `extension=fileinfo`, and `extension=openssl` are enabled.
4. **Launch**:
   - Double-click **`Start-CreativeMediaHub.bat`**.
   - It will detect the portable PHP, configure the local SQLite database, start the server at `http://127.0.0.1:8088`, and automatically open your default browser!
5. **Stop**:
   - Close the terminal window or run **`Stop-CreativeMediaHub.bat`**.

---

## 💻 Standard Installation & Setup

### Prerequisites
- **PHP**: 8.2 or higher (with `pdo_sqlite`, `mbstring`, `curl`, `fileinfo`, `gd` extensions enabled)
- **Composer**: 2.x
- **Node.js**: 18.x or higher & **npm**
- **FFmpeg**: (Optional but recommended for Remux streaming & embedded subtitle extraction)

### 1. Clone the Repository
```bash
git clone https://github.com/mr-creative-hmh/creative-media-streaming-library.git creative-media-hub
cd creative-media-hub
```

### 2. Install PHP & JavaScript Dependencies
```bash
composer install --optimize-autoloader
npm install
```

### 3. Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Database Setup (SQLite)
```bash
touch database/database.sqlite
php artisan migrate
```

### 5. Build Assets & Start Development Server
```bash
# Build frontend assets
npm run build

# Start the Laravel application
php artisan serve
```
Open **`http://localhost:8000`** in your browser.

---

## ⌨️ Player Keyboard Shortcuts

| Shortcut | Action |
|---|---|
| **Space** / **K** | Play / Pause |
| **Left Arrow** / **J** | Seek backward 10 seconds |
| **Right Arrow** / **L** | Seek forward 10 seconds |
| **Up Arrow** | Increase Volume (+10%) |
| **Down Arrow** | Decrease Volume (-10%) |
| **F** | Toggle Fullscreen |
| **M** | Mute / Unmute Audio |
| **C** | Toggle Subtitles On / Off |
| **Escape** | Exit Fullscreen / Close Modals |

---

## 🛠️ Tech Stack & Architecture

- **Backend**: [Laravel 12](https://laravel.com) with Artisan CLI, Cache API, and Eloquent ORM.
- **Frontend**: [Inertia.js](https://inertiajs.com) + [Vue 3](https://vuejs.org) (Composition API, `<script setup>`, TypeScript).
- **Styling**: [Tailwind CSS 4](https://tailwindcss.com) with dark cinema theme and custom typography.
- **Player**: HTML5 Video API + [Video.js](https://videojs.com) with custom HUD and stream chunking.
- **Database**: [SQLite](https://sqlite.org) with write-ahead logging (WAL) for concurrency.
- **Transcoding & Remuxing**: [FFmpeg](https://ffmpeg.org) / `ffprobe` for stream analysis, on-the-fly MP4 remuxing, and subtitle extraction.

---

## 🧪 Automated Testing

To run the complete automated test suite (61 tests, 428 assertions):

```bash
php artisan test
```

---

## 📄 License

The Creative Media Hub project is open-source software licensed under the [MIT License](LICENSE).
