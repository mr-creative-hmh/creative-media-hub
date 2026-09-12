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

# 5. Initialize SQLite database, run migrations & seed base taxonomy
touch database/database.sqlite
php artisan migrate
php artisan db:seed --class=MediaLibrarySeeder

# 6. Build frontend assets & start development server
npm run dev
```

---

## 2. Running Automated Tests & Quality Checks

Creative Media Hub includes automated unit and feature test suites covering parser behavior, route integrity, streaming responses, metadata cascades, subtitle health verification, direct ID lookup, multi-episode ingestion, and collection auditing.

```bash
# Run the complete PHPUnit test suite
php artisan test

# Run specific feature and unit test suites
php artisan test --filter=ComprehensiveSceneParserTest
php artisan test --filter=ScannerRelocationAndUpgradeTest
php artisan test --filter=MetadataRenameAndCollectionTest
php artisan test --filter=MediaScoutTest
php artisan test --filter=SubtitleHealthCheckTest
php artisan test --filter=MetadataDirectIdLookupTest
php artisan test --filter=OrganizerServicesTest
php artisan test --filter=StreamingAndRoutesTest
php artisan test --filter=DashboardTest

# Static Type Checking (Vue 3 + TypeScript)
npm run types:check

# Verify production frontend assets compilation
npm run build

# Code style formatting (Laravel Pint)
vendor/bin/pint --format agent
```

---

## 3. Subtitle Health Checker & Normalizer CLI

To audit, clean, and standardize subtitles from the command line:

```bash
# Dry-run audit (prints findings without modifying disk)
php artisan subtitles:check --dry-run

# Execute cleanup & renaming across entire library
php artisan subtitles:check --fix --delete-invalid

# Target a specific directory
php artisan subtitles:check --path="D:/Media/Movies" --fix
```

---

## 4. Collection Franchise Auditor CLI

To audit movie collections, fix unlinked franchise sequels, and align physical directories:

```bash
# Audit collections and identify unlinked movies / single-movie false positives
php artisan library:audit-collections

# Automatically associate unlinked sequels and save TMDb collection IDs
php artisan library:audit-collections --fix

# Perform both database association and physical disk folder realignment
php artisan library:audit-collections --fix --align-physical
```

---

## 5. Multi-Episode Scene Parsing & Hardlink Rules

- **Parser Support**: `SceneNameParserService` recognizes `S01E01-E02`, `S01E01E02`, `S01E01-02`, and `S01E01.E02`.
- **Scanner Entity Creation**: `VirtualLibraryScannerService` creates separate `Episode` records for each episode in the range with distinct TMDb titles and synopses.
- **Physical Hardlink Tokens**: In `PhysicalOrganizerService`, `{Episode:02}` automatically expands to `01-E02` for multi-episode files, and `updateDatabasePath()` updates all sibling episode database records in one query.

---

## 6. Extending the System

### 6.1. Adding a New Metadata Provider
1. Create a new class in `app/Services/Metadata/Providers/` implementing `MetadataProviderInterface`.
2. Implement required methods:
   - `searchMovie(string $query, ?int $year): array`
   - `searchSeries(string $query, ?int $year): array`
   - `getMovieDetails(string|int $id, string $lang): ?array`
   - `getSeriesDetails(string|int $id, string $lang): ?array`
3. Register the provider in `app/Services/Metadata/MetadataAggregator.php` within `$this->providers`.

### 6.2. Adding Scene Parsing Rules
1. Open `app/Services/Organizer/SceneNameParserService.php`.
2. Add new regex patterns in `parse()` before the fallback patterns.
3. Always add unit test cases in `tests/Unit/ComprehensiveSceneParserTest.php` to verify no regressions.

---

## 7. Code Standards & Architecture Guidelines
- **Strict Types**: Always declare types for method parameters and return values.
- **Eloquent Relations**: Always eager-load relations (`with(['genres', 'subtitles'])`) in controllers to prevent N+1 query overhead.
- **Async Execution**: Avoid executing blocking operations (like heavy FFmpeg transcodes) inside synchronous web request loops; delegate to background workers or streaming generators.
- **Bilingual Consistency**: Ensure all new UI strings are added to both `resources/js/i18n/en.json` and `resources/js/i18n/ar.json`.
- **Laravel Pint**: Run `vendor/bin/pint --dirty --format agent` before finalizing any PHP code changes.
