<div align="center">

<img src="public/favicon.svg" alt="Creative Media Hub Logo" width="140" height="140" />

# 🎬 Creative Media Hub
### **Next-Generation Personal Cinema Streaming Server & Smart Media Library**
*The high-performance, self-hosted media platform for movies, TV series, real-time subtitle translation, and instant Windows portable streaming.*

[![Developer](https://img.shields.io/badge/Developer-Eng._Hasan_Mohammad_Hasan-06B6D4?style=for-the-badge&logo=github&logoColor=white)](https://github.com/mr-creative-hmh)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue 3](https://img.shields.io/badge/Vue.js-3.5-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-3.0-9553E9?style=for-the-badge&logo=inertia&logoColor=white)](https://inertiajs.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-v4-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![Electron](https://img.shields.io/badge/Electron-34-47848F?style=for-the-badge&logo=electron&logoColor=white)](https://www.electronjs.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](https://opensource.org/licenses/MIT)

</div>

---

## 📚 Complete Technical Documentation Suite

For deep architectural specifications, internal pipeline lifecycles, directory layouts, developer setup, and REST APIs, explore our modular documentation suite:

| Document | Description |
| :--- | :--- |
| 🏛️ **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)** | Deep Clean Layered Architecture, Ports & Adapters, Hexagonal boundaries, and SQLite WAL concurrency model. |
| 📁 **[docs/STRUCTURE.md](docs/STRUCTURE.md)** | Full directory tree, controller responsibilities, service boundaries, domain models, and Vue 3 components map. |
| ⚙️ **[docs/PROCESSES.md](docs/PROCESSES.md)** | In-depth walkthrough of all 9 core pipelines (Virtual Scanner, Scene Parser, Metadata Waterfall, Boxsets Clustering, Remuxer, Hardlinks). |
| 🛠️ **[docs/DEVELOPER_GUIDE.md](docs/DEVELOPER_GUIDE.md)** | Contributor guide, local setup, running PHPUnit/Pest automated tests, adding new metadata providers, and coding standards. |
| 📡 **[docs/API_REFERENCE.md](docs/API_REFERENCE.md)** | Complete REST & Streaming API reference with query parameters, request payloads, and response structures. |

---

## 🌟 Visual Showcase

<div align="center">

### 🖥️ Dashboard & Cinema Hero Showcase
![Dashboard Showcase](docs/screenshots/dashboard.png)

### 🎬 4K Movies Catalog with Regional Cinema Filters
![Movies Showcase](docs/screenshots/movies.png)

### 🍿 Movie Boxsets & Franchise Sagas (2+ Films Verified)
![Collections Showcase](docs/screenshots/collections.png)

### 📺 TV Series & Episodic Hub
![Series Showcase](docs/screenshots/series.png)

### 🎯 Fix Match & Error Resolution Studio
![Metadata Fix Match](docs/screenshots/metadata.png)

### ⚡ Zero-Copy NTFS Hardlink Physical Organizer
![Organizer Showcase](docs/screenshots/organizer.png)

### 💬 Subtitle Synchronization & Download Center
![Subtitles Showcase](docs/screenshots/subtitles.png)

</div>

---

## ✨ Key Features & Capabilities

### 1. 🍿 Movie Boxsets & Franchise Sagas
- Clusters multi-film movie franchises (e.g. *Harry Potter (9 films)*, *Fast & Furious (11 films)*, *The Dark Knight Trilogy*, *Knives Out*, *Ip Man Collection*) with chronological release timelines.
- Intelligent **`count >= 2`** threshold eliminates solitary 1-movie false positives from `/collections`.

### 2. 🌍 Regional Cinema Origin Filtering
- 1-Click regional filtering:
  - **Arabic Cinema (عربي)**: Egypt, Saudi Arabia, UAE, Syria, Lebanon, Jordan, Maghreb.
  - **Bollywood (بوليوود)**: Hindi, Tamil, Telugu cinema.
  - **Asian Cinema (آسيوي)**: Japan (Anime), South Korea, Hong Kong, China, Thailand.
  - **Turkish Cinema (تركي)**: Turkish dramas and feature films.
  - **Hollywood & Western**: US, UK, Australia, Canada.
  - **European Cinema**: France, Germany, Italy, Spain, Scandinavia.

### 3. 🎯 Fix Match & Error Resolution Studio
- Instant online metadata search with automated query cleaning (strips scene tags and dots).
- **Direct ID Lookup**: Instantly fetch and apply full bilingual metadata by exact TMDb numeric ID (e.g. `27205`) or IMDb ID (`tt1375666`).
- **1-Click Movie ↔ Series Converter**: Instantly fixes accidental type classification.
- **Intelligent Scene Re-Parser**: Re-evaluates raw filenames on demand.

### 4. 🧠 Intelligent Scene Name Parser (Arabic & Multilingual Engine)
- Normalizes Eastern Arabic numerals (`١, ٢, ٣ → 1, 2, 3`).
- Folder ancestor context inheritance: resolves episode numbers from nested structures (`Breaking Bad/Season 01/01.mp4`).
- Distinguishes movie franchise sequence numbers from TV episodes inside movie folders (`1.Ip.Man.2008.mp4` → Movie Part 1).

### 5. ⚡ Hybrid Video Streaming & Remuxing
- **Direct Stream (HTTP 206 Partial Content)**: Zero-CPU byte-range streaming for MP4 / H.264 / AAC.
- **On-The-Fly FFmpeg Remuxer**: Real-time stdout remuxing for legacy containers (AVI, MKV, MPEG-4, DTS) into fragmented MP4.
- **FastStart Disk Caching**: Transcode-caches remuxed streams in background for instantaneous seeking upon replay.

### 6. 🔗 Zero-Copy NTFS Hardlink Organizer
- Restructures chaotic folders into pristine paths (`Movies/Title (Year)/Title (Year) [1080p].ext`) using NTFS hardlinks (`mklink /H`).
- **0 bytes** duplicated on disk and continuous torrent seeding remains 100% active.
- Includes side-by-side Dry-Run simulation before execution.

---

## 🚀 Quick Start & Installation

### 1. Prerequisites
- **PHP 8.2+** with extensions: `pdo_sqlite`, `fileinfo`, `curl`, `mbstring`, `openssl`.
- **Node.js 20+** and **npm 10+**.
- **Composer 2.x**.
- **FFmpeg & FFprobe 6.x / 7.x** (detected from PATH or Herd).

### 2. Setup Commands
```bash
# 1. Clone repository
git clone https://github.com/mr-creative-hmh/creative-media-hub.git
cd creative-media-hub

# 2. Install PHP and JS dependencies
composer install
npm install

# 3. Environment & Key Generation
cp .env.example .env
php artisan key:generate

# 4. Database Initialization & Schema Migration
touch database/database.sqlite
php artisan migrate

# 5. Build Assets & Start Server
npm run build
php artisan serve
```

---

## 🧪 Testing & Verification

Creative Media Hub comes with a test suite covering parsers, streaming responses, and metadata cascades.

```bash
# Run all automated tests
php artisan test

# Verify frontend assets compilation
npm run build
```

---

## 💻 Windows Desktop App (Electron Standalone)

Run Creative Media Hub as a standalone desktop cinema application:

```bash
# Start in Electron development mode
npm run electron:dev

# Package as a portable Windows executable (.exe)
npm run electron:build
```

---

## 👨‍💻 Author & Lead Architect

**Eng. Hasan Mohammad Hasan**  
- GitHub: [@mr-creative-hmh](https://github.com/mr-creative-hmh)  
- Project: [Creative Media Hub](https://github.com/mr-creative-hmh/creative-media-hub)  

---

## 📜 License

Creative Media Hub is open-source software licensed under the **[MIT License](LICENSE)**.
