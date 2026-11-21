# PHP 8.5, Laravel 13 & Frontend Modernization Optimization Plan

> **Project:** Creative Media Hub (`c:\Users\hasan\Herd\creative-media-hub`)
> **Baseline Runtime:** PHP `8.5.12` (Herd), Composer `2.11.1`, Node `v24.18.0`, npm `11.16.0`
> **Baseline Stack:** Laravel `13.43.0`, Inertia `3.7.0` / `@inertiajs/vue3` `3.11.0`, Vue `3.5.46`, Vite `8.3.8` (Rolldown), `vite-plus` `0.3.0`, Tailwind CSS `4.3.5`
> **Audit Date:** 2026-11-21

---

## User-Confirmed Decisions

1. **Execution Scope:** Execute **Phases 0–3** now, then **pause for user review before Phase 4**.
2. **PHP Constraint:** Bump `composer.json` PHP requirement from `^8.3` to **`^8.5`** (in Phase 3B; `composer.lock` already resolves Symfony 8.1 requiring `>=8.4.1`).
3. **`@vueuse/core` Strategy:** Upgrade `@vueuse/core` from `12.8.2` to **`^15.0.0`** (in Phase 3B) rather than removing it.
4. **Laravel Wayfinder:** **Keep** `@laravel/vite-plugin-wayfinder` / `laravel/wayfinder` enabled in `vite.config.ts`.
5. **Cleanup Scope:** **Remove `laravel/sail`** (in Phase 3A) and **untrack `graphify-out/`** (132 tracked files) via `.gitignore` + `git rm -r --cached graphify-out` (in Phase 0).

---

## Phase Progress & Metrics Log

| Phase | Status | Commit | Test/Check Summary | Notes |
| :--- | :--- | :--- | :--- | :--- |
| **Phase 0: Environment & Tooling Unblock** | Complete | *(Phase 0 commit — SHA logged in Phase 1)* | PHP `8.5.12` (0 OPcache warnings); Pint PASS; PHPUnit **162/162 (1,273 assertions, 20.84s)** native 512M; `vue-tsc` 0 errors; `vp build` **8.07s** (`AppLayout` 285.29 kB / 77.16 kB gz, `app.js` 212.72 kB / 72.39 kB gz, `app.css` 221.30 kB / 27.45 kB gz, Fonts 54 files / 579.66 kB down from 60 files / 693 kB, -6 files / -113.3 kB); `vp lint` 67w/0e (no OOM); `vp check` 112 fmt drift (no OOM); PHPStan L7 baseline 884 errors | Herd `php85/php.ini` OPcache + 512M (+ `.bak`), CI PHP 8.5 / Node 24, `vite.config.ts` ignores + drop Bunny font, untrack `graphify-out/` (132 files -> 0), isolate `DatabaseBackupAndRestoreTest` |
| **Phase 1: P0 Runtime Crashes & Correctness** | Pending | — | — | Backend P0 bugfixes (`SearchController`, `buildMasterIndex`, `VirtualLibraryScannerService`, `DatabaseBackupService`, DB column/type bugs, route cache, PHP 8.5 deprecations) + Frontend P0s (`useI18n` SSR, `useToast`, `PageTransitionLoader`, `useDownloader`, 67 `vp lint` warnings, single-root SFCs) |
| **Phase 2: Backend & Frontend Performance** | Pending | — | — | Native SQLite `pragmas` config, middleware splitting for `/stream/*` & stateless `/api/*`, DB index dedup/additions, N+1 fixes, async-splitting players/modals/locales, `@fontsource` subsetting, Tailwind `@source not` |
| **Phase 3A: Unused Package & Dead Code Cleanup** | Pending | — | — | Remove verified unused Composer & npm packages (`chisel`, `fortify`, `sail`, `@laravel/passkeys`, `hls.js`, `reka-ui`, `cva`, `vue-input-otp`, `@rollup/rollup-*`, `vue-sonner`/`clsx`/`tailwind-merge` w/ dead `lib/` files), migrate `lucide-vue-next` -> `@lucide/vue` |
| **Phase 3B: Toolchain & Dependency Upgrades** | Pending | — | — | Bump `php` -> `^8.5`, `laravel/boost` -> `^2.10`, `phpunit/phpunit` -> `^13.4`, `@types/node` -> `^24`, `vue-tsc` -> `^3.3.14` (keep TS `<7`), `@vueuse/core` -> `^15.0.0`; isolated `vite-plus` `0.3.0` -> `^1.7.0` migration via `vp migrate` |
| **Phase 4: Architecture Modernization & Refactoring** | Paused (Awaiting User Review) | — | — | Persistent `AppLayout`, `<Link prefetch>`, Inertia v3 `useHttp`/`useForm`/`Inertia::defer`/`Inertia::once`, `Model::shouldBeStrict()`, Eloquent `casts()`/`Attribute`/`#[Scope]`, burn down 884 PHPStan L7 errors, deduplicate composables & `formatBytes()` |

---

## Package Audit Matrix

### Backend (Composer — `composer audit`: 0 advisories, 0 abandoned)

| Package | Section | Constraint | Installed | Latest | Action / Target Phase | Guardrails / Notes |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `php` | `require` | `^8.3` | `8.5.12` | `8.5.12` | Bump to `^8.5` (**Phase 3B**) | `composer.lock` already installs 18 Symfony 8.1 pkgs (`>=8.4.1`). Sync CI to PHP 8.5 first (Phase 0). |
| `laravel/framework` | `require` | `^13.17` | `13.43.0` | `13.43.0` | Keep (Up to date) | Full PHP 8.5 & PHPUnit 13 (`^13.0.3`) support; Laravel 14 slated Q1 2027. |
| `inertiajs/inertia-laravel` | `require` | `^3.0` | `3.7.0` | `3.7.0` | Keep (Up to date) | Supports Live Props, `QUERY` redirects, `BigInt`, v3 schema. |
| `laravel/tinker` | `require` | `^3.0` | `3.2.0` | `3.2.0` | Keep (Up to date) | Supports PHPUnit `^13.4`. |
| `laravel/wayfinder` | `require` | `^0.1.14` | `0.1.23` | `0.1.23` | Keep (User decision) | Keep enabled in `vite.config.ts`. |
| `laravel/chisel` | `require` | `^0.1.0` | `0.1.1` | `0.1.1` | **Remove** (**Phase 3A**) | Starter-kit scaffold removal tool; unused in runtime. |
| `laravel/fortify` | `require` | `^1.37.2` | `1.43.0` | `1.43.0` | **Remove** (**Phase 3A**) | Already in `dont-discover`; clean `tests/TestCase.php` (`Features` / `skipUnlessFortifyHas()`), `config/fortify.php`, and stale `dont-discover` entries (incl. `laravel/passkeys`) first. |
| `phpunit/phpunit` | `require-dev` | `^12.5.23` | `12.5.45` | `13.4.8` | Upgrade to `^13.4` (**Phase 3B**) | Requires PHP `>=8.4`. Verified compatible with `laravel/framework 13.43.0`, `collision 8.9.6`, `pao 1.1.6`, `larastan 3.15.0`. Clear PHPUnit 12 deprecations first. |
| `larastan/larastan` | `require-dev` | `^3.9` | `3.15.0` | `3.15.0` | Keep (Up to date) | Ships `phpstan/phpstan 2.3.7` with PHP 8.5 support. |
| `laravel/boost` | `require-dev` | `*` | `2.10.4` | `2.10.4` | Pin to `^2.10` (**Phase 3B**) | Avoid unguarded wildcard `*` major jumps. |
| `laravel/pail` | `require-dev` | `^1.2.5` | `1.3.0` | `1.3.0` | Keep (Up to date) | Used by local dev workflow. |
| `laravel/pao` | `require-dev` | `^1.0.6` | `1.1.6` | `1.1.6` | **Keep** (Up to date) | Agent-optimized PHPUnit/Artisan output; supports PHPUnit `^13.1.7`. |
| `laravel/pint` | `require-dev` | `^1.27` | `1.32.3` | `1.32.3` | Keep (Up to date) | Full PHP 8.5 syntax formatting support. |
| `laravel/sail` | `require-dev` | `^1.53` | `1.71.0` | `1.71.0` | **Remove** (**Phase 3A**) | User confirmed removal (no Docker Sail usage). |
| `nunomaduro/collision` | `require-dev` | `^8.9.3` | `8.9.6` | `8.9.6` | Keep (Up to date) | Supports PHPUnit `<14.0.0`. |
| `fakerphp/faker` | `require-dev` | `^1.24` | `1.24.1` | `1.24.1` | Keep (Up to date) | Used by factories/tests. |
| `mockery/mockery` | `require-dev` | `^1.6` | `1.6.15` | `1.6.15` | Keep (Up to date) | Used by tests. |

### Frontend (npm)

| Package | Section | Constraint | Installed | Latest | Action / Target Phase | Guardrails / Notes |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `vue` | `dependencies` | `^3.5.13` | `3.5.46` | `3.5.46` | Keep (Up to date) | Stay on `3.5.x` stable (`3.6` Vapor Mode is still RC). |
| `@inertiajs/vue3` | `dependencies` | `^3.0.0` | `3.11.0` | `3.11.0` | Keep (Up to date) | Includes `useHttp`, `useProp`, Live Props, `router.query()`, optimistic updates. |
| `@inertiajs/vite` | `dependencies` | `^3.0.0` | `3.11.0` | `3.11.0` | Keep (Up to date) | Zero-config dev SSR & page resolution. |
| `laravel-vite-plugin` | `dependencies` | `^3.0.0` | `3.2.1` | `3.2.1` | Keep (Up to date) | Remove unused `bunny('Instrument Sans')` call in Phase 0. |
| `tailwindcss` / `@tailwindcss/vite` | `deps` / `devDeps` | `^4.1.1` / `^4.1.11` | `4.3.5` | `4.3.5` | Keep (Up to date) | Native scrollbar utils, `@container-size`, Lightning CSS. |
| `@lucide/vue` | `dependencies` | `^1.17.0` | `1.77.0` | `1.77.0` | Keep (Up to date) | Canonical Vue 3 Lucide package. |
| `lucide-vue-next` | `dependencies` | `^1.0.0` | `1.0.0` | `1.0.0` | **Remove** (**Phase 3A**) | Officially **deprecated**; migrate all imports to `@lucide/vue`. |
| `@vueuse/core` | `dependencies` | `^12.8.2` | `12.8.2` | `15.0.0` | **Upgrade to `^15.0.0`** (**Phase 3B**) | User confirmed. Audit `templateRef`, `useThrottleFn` (`trailing: true`), `computedAsync`, `useClipboard`, `createSharedComposable` (currently only 1 call in `AccentColorPicker.vue:23`). |
| `@fontsource/{cairo,outfit,plus-jakarta-sans}` | `dependencies` | `^5.3.0` | `5.3.0` | `5.3.0` | Keep & subset (**Phase 2**) | Trim imports to `woff2` + `latin`/`arabic` subsets only. |
| `@laravel/passkeys` | `dependencies` | `^0.2.0` | `0.2.0` | `0.4.1` | **Remove** (**Phase 3A**) | Unused; backend `laravel/passkeys` is not installed. |
| `hls.js` | `dependencies` | `^1.7.3` | `1.7.4` | `1.7.4` | **Remove** (**Phase 3A**) | Unused in `resources/js/`. |
| `reka-ui` | `dependencies` | `^2.9.8` | `2.11.0` | `2.11.0` | **Remove** (**Phase 3A**) | Unused in `resources/js/`. |
| `class-variance-authority` | `dependencies` | `^0.7.1` | `0.7.1` | `0.7.1` | **Remove** (**Phase 3A**) | Unused in `resources/js/`. |
| `vue-input-otp` | `dependencies` | `^0.3.2` | `0.3.2` | `0.4.0` | **Remove** (**Phase 3A**) | Unused starter-kit 2FA leftover. |
| `vue-sonner`, `clsx`, `tailwind-merge` | `dependencies` | `^2.0.0`, `^2.1.1`, `^3.2.0` | `2.0.9`, `2.1.1`, `3.7.1` | Latest | **Remove** w/ dead `lib/` (**Phase 3A**) | Only referenced by dead `lib/flashToast.ts` & `lib/utils.ts`; `vite.config.ts:83` `sortTailwindcss.functions` is string-only. |
| `tw-animate-css` | `dependencies` | `^1.2.5` | `1.4.0` | `1.4.0` | Verify / Keep | Confirm usage in `resources/css/app.css` during Phase 2/3A. |
| `vite` | `devDependencies` | `^8.0.0` | `8.3.8` | `8.3.8` | Keep / Alias on `vite-plus` 1.7 (**Phase 3B**) | Uses Rolldown `1.2.19`. |
| `vite-plus` | `devDependencies` | `0.3.0` | `0.3.0` | `1.7.0` | **Upgrade to `^1.7.0`** (**Phase 3B**) | Isolate commit via `vp migrate`; add `"overrides": { "vite": "@voidzero-dev/vite-plus-core" }`, drop 22 deprecated `yuku-*` bindings, verify `vp check`/`vp build`/`vp dev`/Wayfinder. |
| `@vitejs/plugin-vue` | `devDependencies` | `^6.0.0` | `6.0.11` | `6.0.11` | Keep (Up to date) | Vite 8 compatible. |
| `@laravel/vite-plugin-wayfinder` | `devDependencies` | `^0.1.3` | `0.1.11` | `0.1.11` | Keep (User decision) | Vite 8 compatible. |
| `vue-tsc` | `devDependencies` | `^2.2.4` | `2.2.12` | `3.3.14` | **Upgrade to `^3.3.14`** (**Phase 3B**) | Aligns with Volar 3 & TS `5.9.x`/`6.0.x`. |
| `typescript` | `devDependencies` | `^5.2.2` | `5.9.3` | `7.0.2` | **Keep `<7` (`5.9.x` or `6.0.x`)** | **DO NOT upgrade to TS 7.0.x** — drops `./lib/tsc` and breaks `vue-tsc` (`ERR_PACKAGE_PATH_NOT_EXPORTED`). |
| `@types/node` | `devDependencies` | `^22.13.5` | `22.20.5` | `26.6.6` | **Upgrade to `^24`** (**Phase 3B**) | Match installed Node `v24.18.0` (do not jump to `26`). |
| `concurrently` | `devDependencies` | `^10.0.3` | `10.0.7` | `10.0.7` | **KEEP** | Required by Laravel 13 `php artisan dev` (`DevCommand::runViaConcurrently()`) on Windows! |
| `@laravel/multiplex` | `optionalDependencies` | `^0.4.1` | `MISSING` (win) | `0.4.5` | **KEEP** | `os: ["darwin","linux"]` 0-byte no-op on Windows; powers `php artisan dev` on macOS/Linux. |
| `@rollup/rollup-{linux,win32}-*` | `optionalDependencies` | `4.9.5` | `4.9.5` | `4.68.8` | **Remove** (**Phase 3A**) | Obsolete Rollup 4.9.5 workaround; Vite 8 uses Rolldown. |
| `react` (extraneous) | *(none)* | — | `19.3.1` | — | Prune (**Phase 3A**) | Extraneous `node_modules` artifact (`npm prune`). |

---

## Detailed 5-Phase Execution Plan

### Phase 0: Environment & Tooling Unblock *(Zero App Risk)*
- **Herd PHP 8.5 INI (`C:\Users\hasan\.config\herd\bin\php85\php.ini`):** Back up to `php.ini.bak`, comment out stale `zend_extension=opcache` (eliminating Zend Engine API `420240925` vs `420250925` mismatch; OPcache v8.5.12 is built into core), and raise `memory_limit` from `128M` to `512M`.
- **CI Alignment (`.github/workflows/tests.yml`):** Update `php-version` from `'8.3'` to `'8.5'` and `node-version` from `'22'` to `'24'`.
- **Git Cleanup (`.gitignore`):** Add `graphify-out/` and run `git rm -r --cached graphify-out` (untracking 132 generated files).
- **Vite / Vite+ Config (`vite.config.ts`):** Mirror full `ignorePatterns` across both `lint` and `fmt` to eliminate `vp check` V8 OOM crashes; remove unused `bunny('Instrument Sans', ...)` font plugin call; keep Wayfinder enabled.
- **Test Isolation (`tests/Feature/DatabaseBackupAndRestoreTest.php`):** Isolate all backup/restore test artifacts (including pre-restore safety backups) to a temporary directory cleaned up in `tearDown()` so tests never pollute `storage/app/backups/`.

### Phase 1: P0 Runtime Crashes, Correctness & PHP 8.5 Fixes
- **Backend P0 Fixes:**
  - `SearchController::instantSearch()` (`app/Http/Controllers/SearchController.php`): convert cached `Collection`s via `->values()->all()` so Laravel 13 `serializable_classes => false` (`config/cache.php`) never returns `__PHP_Incomplete_Class`.
  - Undefined methods & missing imports: replace `buildAll(false, false, false)` with `buildMasterIndex(false, false)` (3rd param is `?callable`, do NOT pass `false`!) in `CollectionManagementController.php:201` & `MetadataManagementController.php:1160`; fix `VirtualLibraryScannerService.php:404-412` (`SubtitleLanguageDetectorService::$languageInfo[$lang]['name_en']`), `:523` (polymorphic `subtitlable_id`/`subtitlable_type`), and `:800` (import `Log`).
  - `DatabaseBackupService`: avoid loading 50 MB JSON backups into memory in `listLocalBackups()` (streaming/metadata summary); add `PRAGMA wal_checkpoint(TRUNCATE)` / `VACUUM INTO` before SQLite backup; unlink stale `-wal`/`-shm` sidecars on binary restore.
  - Nonexistent columns / NOT NULL / validation bugs: fix `PhysicalOrganizerService.php:149`, `SubtitleHealthCheckService.php:261,266,428`, `MetadataManagementController.php:419-428,729-730,922,976-982`, `FixMatchCollectionController.php:89-145`, `ArtworkDownloadService.php:56`, `LibraryGapService.php:301,324-325`, `ScannerController.php:106`, `MediaController`/`SeriesController` sort direction validation.
  - Replace closure route at `routes/web.php:196` with `Route::inertia()` to unblock `php artisan route:cache`; centralize raw `env()` calls into `config/services.php`; resolve PHP 8.5 deprecation warnings (`Aria2Service.php:97`, `routes/console.php:20`, etc.).
- **Frontend P0 Fixes:**
  - `useI18n.ts`: guard `localStorage` with `typeof window !== 'undefined'` (fixes dev SSR `/__inertia_ssr` crash) and avoid rewriting `document.documentElement.dir/lang` on every setup call.
  - `WatchHistoryBar.vue` & `WatchHistory/Index.vue`: replace dead `CustomEvent('app-toast')` with `useToast()`.
  - `PageTransitionLoader.vue`: listen for Inertia v3 `'networkError'` event and clear previous `startTimer` before reassigning.
  - `Organizer/Index.vue`: clean up `cmh:navigate-step` and `organizer:navigate-step` window listeners in `onUnmounted`.
  - `useDownloader.ts`: pause/throttle background polling when tab is hidden (`visibilitychange`), clean up listeners, remove hardcoded fake upload speed.
  - Fix stray `Activity` in `Sidebar.vue:34`, replace internal `<a>` links with `<Link>` (`MediaDetailModal.vue:146`, `Docs/Index.vue:84`), eliminate direct prop mutations & `window.history.replaceState` Inertia desyncs, resolve all 67 `vp lint` warnings, and enforce single-root Vue SFCs across `Navbar.vue` + 11 pages.

### Phase 2: High-Impact Backend & Frontend Performance
- **SQLite & Middleware:** Move SQLite PRAGMAs (`journal_mode`, `synchronous`, `busy_timeout=60000`, `cache_size`, `mmap_size`, `temp_store`) into `config/database.php` `'pragmas'` (natively supported by Laravel 13 `SQLiteConnector`) and remove per-request boot PRAGMAs from `AppServiceProvider`. Split `/stream/*` and stateless `/api/*` off `StartSession`/`HandleInertiaRequests` (with `session_write_close()` guard; **keep** `POST /movies/{mediaItem}/favorite` and `POST /series/{series}/favorite` on `web` CSRF middleware, and keep `EnableCrossOriginIsolation` on HTML/Inertia routes).
- **Database Indexes & N+1s:** Drop 5 duplicate + 4 redundant indexes and add missing composite indexes via migration; eliminate N+1 queries in `SubtitleManagerService`, `app/Services/Streaming/WatchHistoryService.php`, `MetadataManagementController`, `DashboardController`, `AnalyticsController`, and `SettingsController`.
- **Frontend Bundle & Asset Wins:** Lazy-load `CinemaPlayer`, `BandersnatchPlayer`, `UnifiedJobCenterModal` (**with `v-if="isActivityCenterOpen"`**), `FixMatchModal` (**with `v-if="showFixMatch"`**), and non-active locale JSON via `defineAsyncComponent` / dynamic `import()`; trim `@fontsource` imports to `woff2` + `latin`/`arabic` subsets; add Tailwind v4 `@source not` exclusions.

### Phase 3: Dependency Cleanup & Toolchain Upgrades *(Isolated Commits)*
- **Phase 3A (Unused Packages & Dead Code):**
  - Backend: remove `tests/TestCase.php` Fortify references + `config/fortify.php` + stale `dont-discover` entries (`laravel/fortify`, `laravel/passkeys`), then remove `laravel/chisel`, `laravel/fortify`, and `laravel/sail`.
  - Frontend: remove `initializeTheme()` from `resources/js/app.ts` and prune `types/index.ts` before deleting `useAppearance.ts` / `types/navigation.ts`; migrate `lucide-vue-next` -> `@lucide/vue`; remove `lucide-vue-next`, `@laravel/passkeys`, `hls.js`, `reka-ui`, `class-variance-authority`, `vue-input-otp`, `@rollup/rollup-*`, plus `vue-sonner`/`clsx`/`tailwind-merge` alongside dead `lib/flashToast.ts` & `lib/utils.ts`; prune extraneous `react`.
- **Phase 3B (Upgrades):**
  - Bump `composer.json` `"php": "^8.5"` and `"laravel/boost": "^2.10"`.
  - Upgrade `phpunit/phpunit` to `^13.4` (after fixing any PHPUnit 12 deprecation notices).
  - Upgrade `@types/node` to `^24`, `vue-tsc` to `^3.3.14` (keeping `typescript` `<7`, i.e., `5.9.x` or `6.0.x`), and `@vueuse/core` to `^15.0.0`.
  - Dedicated isolated commit for `vite-plus` `0.3.0` -> `^1.7.0` via `vp migrate` (configuring `"overrides": { "vite": "@voidzero-dev/vite-plus-core" }`, verifying `vp check`, `vp build`, `vp dev`, and Wayfinder).

### Phase 4: Architecture Modernization & Refactoring *(Paused for User Review)*
- Persistent `AppLayout` + `usePage().url` + `<Link prefetch>` navigation.
- Inertia v3 adoption: `useHttp`, `useForm`, `Inertia::defer()` (e.g., Dashboard `continueWatching` if moved from client fetch), `Inertia::once()`.
- Laravel 13 / PHP 8.5 modernization: `Model::shouldBeStrict()`, Eloquent `casts()` method / `Attribute` / `#[Scope]` / PHP attributes, `Number::fileSize()`.
- Add `@property` and Eloquent relation generics across models to burn down the 884 PHPStan Level 7 errors.
- Deduplicate batch-job frontend composables and shared utilities (`formatBytes()`).
