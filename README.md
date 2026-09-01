# 🎬 Creative Media Hub

<div align="center">

![Creative Media Hub Banner](https://raw.githubusercontent.com/mr-creative-hmh/creative-media-streaming-library/main/public/favicon.svg)

### **Next-Generation Personal Streaming Server, Desktop App & Library Organizer**
*Engineered with Laravel 12, Inertia.js, Vue 3, Electron Desktop, Tailwind CSS, SQLite, FFmpeg & Video.js*

[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat&logo=php&logoColor=white)](https://php.net)
[![Laravel 12](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue 3](https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=flat&logo=vue.js&logoColor=white)](https://vuejs.org)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-2.x-9553E9?style=flat&logo=inertia&logoColor=white)](https://inertiajs.com)
[![Electron](https://img.shields.io/badge/Electron-Windows_Desktop-47848F?style=flat&logo=electron&logoColor=white)](https://electronjs.org)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4.x-06B6D4?style=flat&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![SQLite](https://img.shields.io/badge/SQLite-3.x-003B57?style=flat&logo=sqlite&logoColor=white)](https://sqlite.org)
[![FFmpeg](https://img.shields.io/badge/FFmpeg-Supported-007808?style=flat&logo=ffmpeg&logoColor=white)](https://ffmpeg.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

</div>

---

## 🌟 Overview

**Creative Media Hub** is a self-hosted personal media streaming platform, native Windows desktop application, physical library organizer, and metadata manager. It turns your local movie and series collection into a cinema experience with full Arabic and English metadata, real-time FFmpeg remuxing, embedded/external subtitle synchronization, and portable drive support.

---

## 🖥️ Native Windows Desktop App & Portable Mode

Creative Media Hub can run as a **Native Windows Desktop App (`.exe`)** or directly from an **External Hard Drive (USB / HDD / SSD)** with zero installation.

### 1. Launching as a Windows Desktop App
```bash
# Run in native Electron desktop window (with live hot-reload)
npm run desktop:dev

# Build standalone Windows Setup Installer & Portable .exe
npm run desktop:build
```
The output executables (`Creative Media Hub Setup.exe` and portable `Creative Media Hub.exe`) will be generated inside the `dist/` directory!

---

### 2. Running Portably from an External Hard Drive
You can place the entire folder on an external hard drive and run it on any Windows PC without installing PHP or Node.js on the host machine:

```
E:\CreativeMediaHub├── Start-CreativeMediaHub.bat       <-- Double click to start & launch app
├── Stop-CreativeMediaHub.bat        <-- Stop background server
├── Start-CreativeMediaHub.ps1       <-- PowerShell launcher
├── .env                             <-- Configured for SQLite
├── database/
│   └── database.sqlite              <-- Database travels with your drive!
├── public/                          <-- Pre-compiled static assets
├── app/                             <-- Application logic
├── vendor/                          <-- Composer dependencies
└── php/                             <-- (Optional) Portable PHP 8.2+ folder
    └── php.exe
```

1. **Copy the Folder**: Copy the project to your external hard drive (e.g. `E:\CreativeMediaHub\`).
2. **Add Portable PHP (Optional - Zero-Install for Any PC)**:
   - Download the **PHP 8.2 or 8.3 Non-Thread Safe (x64) Zip** from [windows.php.net](https://windows.php.net/download/).
   - Extract it into a `php` folder in the project (`E:\CreativeMediaHub\php\php.exe`).
   - In `php\php.ini`, ensure `pdo_sqlite`, `sqlite3`, `curl`, `mbstring`, `fileinfo`, `gd`, and `openssl` extensions are enabled.
3. **Double Click `Start-CreativeMediaHub.bat`**:
   - Automatically detects PHP, connects the SQLite database, and opens the application!

---

## ✨ Key Features

### 🎥 1. Cinema Player Engine
- **Direct & Remux Streaming**: Plays MP4, WebM, MKV, AVI, WMV, TS, and MOV files. Non-browser-native containers (AVI, WMV, TS) are remuxed in real time via FFmpeg into streamable fragmented MP4 pipelines.
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

## 💻 Standard Installation & Setup

### Prerequisites
- **PHP**: 8.2 or higher (with `pdo_sqlite`, `mbstring`, `curl`, `fileinfo`, `gd` extensions enabled)
- **Composer**: 2.x
- **Node.js**: 18.x or higher & **npm**
- **FFmpeg**: (Optional but recommended for Remux streaming & embedded subtitle extraction)

### 1. Clone the Repository
```bash
git clone https://github.com/mr-creative-hmh/creative-media-hub.git creative-media-hub
cd creative-media-hub
```

### 2. Install Dependencies
```bash
composer install --optimize-autoloader
npm install
```

### 3. Environment & Database Setup
```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

### 4. Build Assets & Start
```bash
# Build frontend assets
npm run build

# Start web development server
php artisan serve
```

---

## 🏷️ How to Rename GitHub Repository & Local Folder

### 1. Rename GitHub Repository:
1. Open your repository on GitHub: `https://github.com/mr-creative-hmh/creative-media-streaming-library/settings`
2. In the **Repository name** input, enter `creative-media-hub` and click **Rename**.
3. In your local terminal, update your git remote URL:
   ```bash
   git remote set-url origin https://github.com/mr-creative-hmh/creative-media-hub.git
   ```

### 2. Rename Local Folder in Laravel Herd:
1. Close any running IDE or terminal windows.
2. Rename the directory:
   `C:\Users\hasan\Herd\creative-media-streaming-library` $	o$ `C:\Users\hasan\Herd\creative-media-hub`
3. Laravel Herd will immediately serve it under `http://creative-media-hub.test`!

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

## 🧪 Automated Testing

To run the test suite:
```bash
php artisan test
```

---

## 📄 License

The Creative Media Hub project is open-source software licensed under the [MIT License](LICENSE).
