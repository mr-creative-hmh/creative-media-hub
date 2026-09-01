# 🛠️ Creative Media Hub — Developer & Contributor Guide

> **Author**: Eng. Hasan Mohammad Hasan  
> **Repository**: [creative-media-hub](https://github.com/mr-creative-hmh/creative-media-hub)

---

## 1. Local Development Setup

### Prerequisites
- **PHP**: 8.2 or 8.3+ with `pdo_sqlite`, `fileinfo`, `curl`, `mbstring`, `openssl` extensions.
- **Node.js**: 20+ and npm 10+.
- **Composer**: 2.x
- **FFmpeg & FFprobe**: Version 6.x or 7.x (automatically detected from system PATH, Laravel Herd, or `storage/bin/`).
- **Laravel Herd / Valet / Web Server**: Configured with local HTTPS domain (e.g. `https://creative-media-hub.test`).

### Step-by-Step Installation
```bash
# 1. Clone the repository
git clone https://github.com/mr-creative-hmh/creative-media-hub.git
cd creative-media-hub

# 2. Install PHP backend dependencies
composer install

# 3. Install JavaScript frontend dependencies
npm install

# 4. Copy environment configuration
cp .env.example .env
php artisan key:generate

# 5. Initialize SQLite database & run schema migrations
touch database/database.sqlite
php artisan migrate

# 6. Build frontend assets & start development server
npm run dev
```

---

## 2. Running Automated Tests

Creative Media Hub includes automated unit and feature test suites covering parser behavior, route integrity, streaming responses, and metadata cascades.

```bash
# Run the complete test suite
php artisan test

# Run a specific test suite
php artisan test --filter=SceneNameParserServiceTest
php artisan test --filter=StreamingAndRoutesTest
php artisan test --filter=VirtualLibraryScannerTest

# Verify frontend build compilation
npm run build
```

---

## 3. Extending the System

### 3.1. Adding a New Metadata Provider
1. Create a new class in `app/Services/Metadata/Providers/` implementing `MetadataProviderInterface`.
2. Implement required methods:
   - `searchMovie(string $query, ?int $year): array`
   - `searchSeries(string $query, ?int $year): array`
   - `getMovieDetails(string|int $id, string $lang): ?array`
   - `getSeriesDetails(string|int $id, string $lang): ?array`
3. Register the provider in `app/Services/Metadata/MetadataAggregator.php` within `$this->providers`.

### 3.2. Adding Scene Parsing Rules
1. Open `app/Services/Organizer/SceneNameParserService.php`.
2. Add new regex patterns in `parse()` before the fallback patterns.
3. Always add unit test cases in `tests/Unit/SceneNameParserServiceTest.php` to verify no regressions.

---

## 4. Code Standards & Architecture Guidelines
- **Strict Types**: Always declare types for method parameters and return values.
- **Eloquent Relations**: Always eager-load relations (`with(['genres', 'subtitles'])`) in controllers to prevent N+1 query overhead.
- **Async Execution**: Avoid executing blocking operations (like heavy FFmpeg transcodes) inside synchronous web request loops; delegate to background workers or streaming generators.
- **Bilingual Consistency**: Ensure all new UI strings are added to both `resources/js/i18n/en.json` and `resources/js/i18n/ar.json`.
