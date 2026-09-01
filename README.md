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

## 🌟 Visual Showcase

<div align="center">

### 🖥️ Dashboard & Cinema Hero Showcase
![Dashboard Showcase](docs/screenshots/dashboard.png)

### 🎬 4K Movies Collection & Intelligent Grid
![Movies Grid](docs/screenshots/movies.png)

### 📺 TV Series, Seasons & Multi-Episode Hub
![Series Hub](docs/screenshots/series.png)

### 📂 Smart Media Organizer & Precision Renamer
![Organizer](docs/screenshots/organizer.png)

### 🌐 Subtitle Management & Arabic Translation Waterfall
![Subtitles](docs/screenshots/subtitles.png)

### 🔍 Metadata Engine & Live Fix Match
![Metadata Management](docs/screenshots/metadata.png)

### 📊 Library Analytics & Codec Insights
![Analytics](docs/screenshots/analytics.png)

</div>

---

## ⚡ Key Features

- **🚀 Ultra-Fast Non-Blocking Scanner**:
  - Scans 200+ movies and episodes in **< 45 seconds** without freezing.
  - Multi-worker asynchronous architecture with non-blocking network queries.
  - Live progress feedback with real-time Pause, Resume, and Cancel actions.

- **🍿 Seamless Cinema Video Player**:
  - Hardware-accelerated HLS adaptive streaming + instant direct remux playback.
  - Native support for MP4, MKV, AVI, MOV, WebM, and HEVC formats.
  - Continue Watching resume state saved per-user with millisecond precision.
  - Full keyboard controls (Space, Left/Right Seek, F for Fullscreen, M for Mute).

- **🌍 Intelligent Subtitle & Translation Engine**:
  - Embedded subtitle extraction directly from MKV/MP4 streams (SubRip, ASS/SSA, WebVTT).
  - OpenSubtitles v3 automated search and synchronization.
  - Arabic Waterfall pipeline: Instant subtitle translation via LibreTranslate and DeepL engines.
  - Multi-encoding Arabic detector (`CP1256`, `CP1252`, `UTF-8`) with zero corruption.

- **🗂️ Smart Disk Organizer & Mass Renamer**:
  - Live preview of renaming changes before applying.
  - Industry-standard naming conventions (`Title (Year)` and `Show Name S01E02 - Episode Title`).
  - Automated directory restructuring, clean presets, and conflict handling.

- **💻 Windows Portable Mode & Standalone App**:
  - **Zero-installation portable executable** (`Creative Media Hub 1.0.0.exe`) for external hard drives.
  - Windows NSIS installer with desktop integration (`Creative Media Hub Setup 1.0.0.exe`).
  - Batch scripts (`Start-CreativeMediaHub.bat` & `Stop-CreativeMediaHub.bat`) for instant plug-and-play.

---

## 🏗️ Architecture & Technology Stack

```
Creative Media Hub
├── Backend: Laravel 12 / PHP 8.2+
│   ├── Services: VirtualLibraryScanner, EmbeddedSubtitleDetector, MetadataAggregator
│   ├── Streaming: StreamController (Chunked byte-range & on-the-fly MP4 remuxing)
│   └── Database: SQLite / MySQL with dynamic indexed search
├── Frontend: Vue 3 (Composition API) + TypeScript
│   ├── Framework: Inertia.js 3.0 (SPA seamless navigation)
│   ├── Styling: Tailwind CSS v4 (Pure Cinema Dark Aesthetic)
│   ├── Icons: Lucide Vue Next & Custom SVG Vector Cinema Branding
│   └── Internationalization: Custom Vue i18n (English & Arabic RTL/LTR)
└── Desktop: Electron 34 + electron-builder
    ├── Hardware Video Acceleration
    └── Self-contained background server lifecycle management
```

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.2+ with `pdo_sqlite`, `mbstring`, `fileinfo`, `curl` extensions enabled
- Composer 2.x
- Node.js 20+ & npm
- FFmpeg (optional, recommended for MKV/AVI instant remuxing)

### Installation

```bash
# 1. Clone repository
git clone https://github.com/mr-creative-hmh/creative-media-hub.git
cd creative-media-hub

# 2. Install PHP & JavaScript dependencies
composer install
npm install

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Run database migrations and seed default presets
php artisan migrate --seed

# 5. Build frontend assets
npm run build
```

---

## 💻 Running the Application

### Option 1: Web Mode (Local Dev Server)
```bash
php artisan serve
```
Visit `http://127.0.0.1:8000` in your web browser.

### Option 2: Desktop Mode (Electron Dev)
```bash
npm run desktop:dev
```

### Option 3: Windows Portable Mode (External Drive)
Double click `Start-CreativeMediaHub.bat` in the root folder.

### Option 4: Build Windows Standalone Executables
```bash
npm run desktop:build
```
This produces:
- `dist/Creative Media Hub 1.0.0.exe` (Standalone Portable Executable)
- `dist/Creative Media Hub Setup 1.0.0.exe` (Windows NSIS Installer)

---

## 🧪 Testing & Quality Assurance

Run the comprehensive test suite:
```bash
php artisan test
```
*61/61 test suites passing (428 assertions verified).*

---

## 👨‍💻 Creator & Lead Developer

<div align="center">

### **Eng. Hasan Mohammad Hasan**
**م. حسن محمد حسن**

*Created & Developed with Passion by Eng. Hasan Mohammad Hasan*  
*تم التصميم والتطوير وبرمجة النظام بالكامل بواسطة المهندس حسن محمد حسن*

[![GitHub](https://img.shields.io/badge/GitHub-mr--creative--hmh-181717?style=for-the-badge&logo=github)](https://github.com/mr-creative-hmh)

</div>

---

## 📄 License
This project is open-sourced under the [MIT License](LICENSE).
