# Creative Media Streaming Library — Master Plan & Execution Roadmap

A high-performance personal media streaming platform, physical disk organizer, and automated cataloging engine for movies and TV series, built with **Laravel + Vue 3 (Inertia.js) Starter Kit** running natively on **Laravel Herd**.

---

## 🧭 Milestone Progress

- [x] **Phase 0: Project Scaffolding & Git Setup**
  - [x] Sub-phase 0.1: Scaffold Laravel Vue starter kit in Herd (`creative-media-streaming-library`)
  - [x] Sub-phase 0.2: Configure Git remote repository (`mr-creative-hmh/creative-media-streaming-library`)
  - [x] Sub-phase 0.3: Create Master Documentation (`PLAN.md`, `README.md`, `ARCHITECTURE.md`, `TASKS.md`)
  - [x] Sub-phase 0.4: Base build & test verification (`php artisan test` & `npm run build`)

- [ ] **Phase 1: Database Architecture & Eloquent Relational Models**
  - [ ] Sub-phase 1.1: Migrations for Media Items, Series, Seasons, Episodes, Genres, People, Subtitles, Watch History, Downloads, Settings
  - [ ] Sub-phase 1.2: Eloquent Models with relationships, scopes, and accessors
  - [ ] Sub-phase 1.3: Database seeders with realistic sample media catalog
  - [ ] Sub-phase 1.4: Unit & Feature tests for database entities and relationships

- [ ] **Phase 2: Metadata Aggregator & Free Multi-Provider Fallback Chain**
  - [ ] Sub-phase 2.1: `MetadataAggregatorService` fallback orchestrator
  - [ ] Sub-phase 2.2: TMDb Scraper (Arabic & English metadata, HD posters, backdrops, cast, trailers)
  - [ ] Sub-phase 2.3: TVMaze Scraper (100% free TV series & episode guide)
  - [ ] Sub-phase 2.4: OMDb Scraper (IMDb scores, ratings, Metacritic)
  - [ ] Sub-phase 2.5: AniList / Jikan Scraper (Anime specialist)
  - [ ] Sub-phase 2.6: Wikipedia / Wikidata & Local NFO offline fallback
  - [ ] Sub-phase 2.7: Unit tests for all metadata providers

- [ ] **Phase 3: Filesystem Scanner, Scene Regex Parser & Physical Disk Organizer**
  - [ ] Sub-phase 3.1: Scene filename regex parser (Title, Year, Resolution, Codec, Audio, SxxExx)
  - [ ] Sub-phase 3.2: Recursive directory scanner for video and subtitle files
  - [ ] Sub-phase 3.3: Physical Organizer Engine (Plex/Jellyfin standard & customizable patterns)
  - [ ] Sub-phase 3.4: Dry-Run Diff Simulation Engine (Before ➔ After mapping, collision detection)
  - [ ] Sub-phase 3.5: Undo history and execution log
  - [ ] Sub-phase 3.6: Feature tests for scanner and organizer operations

- [ ] **Phase 4: Free Subtitle Engine & Auto-Missing Subtitle Downloader**
  - [ ] Sub-phase 4.1: OpenSubtitles REST API service (hash matching & query search)
  - [ ] Sub-phase 4.2: SubDL & Subscene Arabic/English subtitle grabber
  - [ ] Sub-phase 4.3: Missing subtitle auto-scanner & 1-click bulk downloader
  - [ ] Sub-phase 4.4: Feature tests for subtitle services

- [ ] **Phase 5: Cinema Streaming Video Server & Player Backend**
  - [ ] Sub-phase 5.1: `StreamController` with `HTTP 206 Partial Content` binary range requests
  - [ ] Sub-phase 5.2: WebVTT / SRT subtitle stream converter
  - [ ] Sub-phase 5.3: Playback progress sync API (timestamps, continue watching, mark completed)
  - [ ] Sub-phase 5.4: Streaming API feature tests

- [ ] **Phase 6: Frontend Design System, Bilingual (Arabic / English) RTL & Layouts**
  - [ ] Sub-phase 6.1: Obsidian & Glassmorphic dark cinema theme (`app.css` design system)
  - [ ] Sub-phase 6.2: Bilingual & RTL Engine (Vue I18n with `ar.json` & `en.json`, Cairo & Inter fonts)
  - [ ] Sub-phase 6.3: App Layout: Sidebar, Header with universal search, Hero Spotlight Carousel with ambient glow
  - [ ] Sub-phase 6.4: Frontend build verification & RTL toggle test

- [ ] **Phase 7: Virtual Library Views, Search, Filter Studio & Discovery**
  - [ ] Sub-phase 7.1: Movies grid view (`Movies/Index.vue`) with quality badges & hover previews
  - [ ] Sub-phase 7.2: Series grid & show views (`Series/Index.vue`, `Series/Show.vue`) with season tabs
  - [ ] Sub-phase 7.3: Multi-axis FilterBar (genres, year slider, 4K/1080p, unwatched, sort options)
  - [ ] Sub-phase 7.4: Media Detail Modal with backdrop banner, cast list, and trailer player
  - [ ] Sub-phase 7.5: AI Mood & Vibe Matcher (Mind-Bending, Cozy, Adrenaline, Noir)
  - [ ] Sub-phase 7.6: Cast Connection Explorer (filmography & library cross-match)

- [ ] **Phase 8: Cinema Video Player & Subtitle Switcher**
  - [ ] Sub-phase 8.1: Cinema video player with glass controls, scrub bar, speed, PiP, fullscreen
  - [ ] Sub-phase 8.2: Subtitle selector with Arabic/English switching and timing sync
  - [ ] Sub-phase 8.3: Next Episode auto-countdown for TV binge watching
  - [ ] Sub-phase 8.4: Playback resume prompt and live timestamp persistence

- [ ] **Phase 9: Physical Organizer Studio, Health Inspector & Download Center**
  - [ ] Sub-phase 9.1: Organizer Hub UI with source/target picker and pattern builder
  - [ ] Sub-phase 9.2: Visual Dry-Run Diff Table with color-coded paths & collision warnings
  - [ ] Sub-phase 9.3: Health Inspector (missing episodes, missing subtitles, duplicates)
  - [ ] Sub-phase 9.4: Download Manager (incoming watch folder & grabber queue)
  - [ ] Sub-phase 9.5: Bento Storage & Codec Analytics Dashboard

- [ ] **Phase 10: Complete Verification, Build Validation, Test Suites & Git Remote Readiness**
  - [ ] Sub-phase 10.1: Run all PHPUnit / Pest tests (`php artisan test`)
  - [ ] Sub-phase 10.2: Run Vue 3 / TypeScript build (`npm run build`)
  - [ ] Sub-phase 10.3: Finalize documentation & commit to git
