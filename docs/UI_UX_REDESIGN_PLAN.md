# Creative Media Hub — CreativeFlow UI/UX Redesign & Multi-Profile Auth Plan

> **Design Reference:** `C:\Users\hasan\Herd\creativeflow` (Production-grade Glassmorphism, 8pt Grid, Motion, WCAG Touch Targets, Sonner HUD, Dialog/Tooltip Primitives)  
> **Auth Reference:** `C:\Users\hasan\Herd\creative-media-server` (Multi-Profile PIN Authentication, Parental Controls & User Management)  
> **Project:** Creative Media Hub (`c:\Users\hasan\Herd\creative-media-hub`)  
> **Target Theme:** Pure Cinema Dark Mode with 8 dynamic Cinema Accents (`cyan`, `red`, `amber`, `emerald`, `violet`, `rose`, `orange`, `blue`) wired into `--primary`, `--ring`, and `--sidebar-primary`.

---

## Architectural Decisions & Guardrails

1. **Pure Cinema Dark Base:** The application is dedicated to cinema streaming and studio operations; default and enforced aesthetic is pure dark (`hsl(222 47% 7%)`) with ambient radial sheens and subtle dot-crosshair grid backgrounds.
2. **Dynamic Cinema Accents:** 8 accents fully wired through CSS custom properties so switching accent instantly updates buttons, badges, glows, active nav items, borders, rings, and Sonner toasts.
3. **Component Foundations:** Standardized on `reka-ui` (headless accessible primitives) + `vue-sonner` (modern stacked HUD notifications) + `clsx` / `tailwind-merge` (`cn()` utility).
4. **Motion System:** Staggered entrance animations via `MotionPage` and `MotionList` with graceful fallback for `prefers-reduced-motion`.
5. **RTL & Internationalization First:** Full bidirectional support (English LTR and Arabic RTL) with responsive mirrored layouts, translated copy, and font pairings (`Plus Jakarta Sans` / `Outfit` for Latin, `Cairo` for Arabic).
6. **Isolated Verified Commits:** Each phase verified with `npm run types:check`, `npx vp check resources/js`, `npm run build`, and `php artisan test --compact` before git commit and push.

---

## Phase Execution Checklist

### [x] Phase 1: Dependencies, Design Tokens, Ambient Backdrops & HUD Toasts
- [x] **1A. Dependencies:** Installed `reka-ui@^2.11.0`, `vue-sonner@^2.0.9`, `clsx@^2.1.1`, `tailwind-merge@^3.7.1`, and `class-variance-authority@^0.7.1`.
- [x] **1A. Core Utilities:** Created `resources/js/lib/utils.ts` (`cn()`, `toUrl()`).
- [x] **1A. Reactive Locale Adapter:** Created `resources/js/composables/useLocale.ts` wrapping `@/i18n/useI18n`.
- [x] **1B. Design System & CSS Tokens:** Port CreativeFlow tokens to `resources/css/app.css` (8pt grid, WCAG touch targets `--size-touch-target: 2.75rem`, motion tokens, semantic variables, 8 cinema accents wired to `--primary`/`--ring`, `.cf-glass`, `.cf-table`, `.cf-shimmer`, `tabular-nums`).
- [x] **1B. Ambient Background:** Implement `resources/js/components/common/AppAmbientBackground.vue` (cinema-grade top radial sheen + dot grid).
- [x] **1B. Motion Primitives:** Port `resources/js/components/motion/MotionPage.vue` and `MotionList.vue`.
- [x] **1B. UI Primitives:** Port `resources/js/components/ui/dialog/*`, `resources/js/components/ui/tooltip/*`, and `resources/js/components/ui/sonner/*`.
- [x] **1B. Toast Modernization:** Wire `resources/js/composables/useToast.ts` and `resources/js/components/common/GlobalToaster.vue` to `vue-sonner`.

### [x] Phase 2: Multi-Profile PIN Auth, Parental Controls & User Management
- [x] **Database & Models:** Migrated `users` table with PIN authentication, avatar customization, role (`admin` / `user`), and parental rating limits (`G`, `PG`, `PG-13`, `R`, `NC-17`). Created `UserFavorite` model with polymorphic relations.
- [x] **Authentication Backend:** Implemented `AuthController` (profile selection, PIN verification, session token, quick profile switch) and `EnsureAdmin` middleware. Fixed `config/auth.php` default guard/provider for `User` model.
- [x] **User Management Backend:** Implemented `UserManagementController` (CRUD profiles, avatar selection, parental age limit controls, PIN reset, self-delete/last-admin safeguards).
- [x] **Auth Frontend Views:** Built `Auth/Login.vue` (theatrical Netflix/Plex-style avatar selector) and `PinPad.vue` (keyboard & touch-friendly PIN pad).
- [x] **Profile Settings & Management Views:** Built `Users/Index.vue` for admin management with profile creation, editing, avatar grid, and PIN resets.
- [x] **Feature Tests & Static Analysis:** Full PHPUnit test suite passing: **205 / 205 tests passed** (1,519 assertions); `vendor/bin/phpstan analyse` passing with 0 errors at Level 7.

### [x] Phase 3: Application Shell & Navigation Redesign
- [x] **Responsive Sidebar:** Full-height sticky aside (`h-screen`) with anchored top brand logo (`AppLogo`), collapsible grouped sidebar with icon-rail mode (`w-20` vs `w-64`), Reka UI tooltips (`:side="isRTL ? 'left' : 'right'"`), middle `min-h-0 flex-1` scroll area, admin-guarded Fast Scan CTA, and permanently pinned bottom User Profile Bento Card (aligned squircle avatar, name, role/kids badge, Switch Profile / Logout action).
- [x] **Floating Glass Navbar:** Fixed floating glass navbar (`.cf-glass-elevated`) with live search dropdown (`Ctrl+K` / `⌘K` global shortcut), active profile indicator, accent color picker, quick downloads drawer pill, compact mobile language toggle, and desktop sidebar collapse button. Mobile logo icon-only rendering on `< lg`.
- [x] **Mobile Bottom Navigation & Drawer:** Floating mobile bottom navigation dock + RTL-aware sliding menu drawer (`MobileMenuDrawer.vue`) with backdrop blur, accordion nav sections, and ESC/swipe dismiss.
- [x] **AppLayout Shell:** Modern two-column desktop shell (full-height sticky sidebar + content column with floating navbar, main slot wrapped in `MotionPage`, and aligned footer) with `overflow-x-clip`, SSR-safe persisted sidebar collapse, and ambient backdrop.

### [/] Phase 4: Shared CreativeFlow UI Primitives
- [ ] **Page Header & Banner:** `PageBanner.vue` with breadcrumbs, title, badge indicators, and action slots.
- [ ] **Filter Controls:** `FilterPills.vue` with smooth scroll and active accent badges.
- [ ] **Base Modal:** `BaseModal.vue` built on `reka-ui` Dialog with backdrop blur, keyboard trap, and responsive sheets on mobile.
- [ ] **Action Buttons & Badges:** `ActionButton.vue` and semantic status badges with consistent variants.
- [ ] **Cards & Data Tables:** `DataTableCard.vue` and `.cf-table` styling for list/grid layouts.
- [ ] **RTL Shimmer Skeletons:** Animated loading placeholders with `.cf-shimmer`.

### [ ] Phase 5: Cinema Catalog & Discovery Pages
- [ ] **Dashboard (`Dashboard/Index.vue`):** Hero spotlight carousel, continue watching shelf, recent additions, and quick media stats.
- [ ] **Movies (`Movies/Index.vue`):** High-density poster grid, advanced genre/year/rating filters, sort dropdown, and batch selection.
- [ ] **Series (`Series/Index.vue` & `Series/Show.vue`):** Show catalog and season/episode inspector with episode preview cards and missing episode markers.
- [ ] **Collections (`Collections/Index.vue` & `Collections/Show.vue`):** Franchise collection cards, completion progress bars, and gap alerts.
- [ ] **Watch History (`WatchHistory/Index.vue`):** Interactive timeline, resume controls, and clearing utilities.
- [ ] **Media Cards & Modals:** Modernize `MediaCard.vue`, `HeroBanner.vue`, `FilterBar.vue`, and `MediaDetailModal.vue`.

### [ ] Phase 6: Acquisition & Studio Workspaces
- [ ] **Scout (`Scout/Index.vue`):** Studio intelligence workspace, Discover trending releases, franchise gaps, quality upgrade scanner, and `TorrentSuggestionsModal.vue` overhaul.
- [ ] **Downloader (`Downloader/Index.vue`):** Torrent client manager, speed gauges, torrent tree inspect, priority controls, and pause/resume buttons.
- [ ] **Metadata Manager (`Metadata/Index.vue`):** Bulk poster fetcher, NFO inspector, and artwork refreshers.
- [ ] **Fix Match (`FixMatchModal.vue`):** Split-pane visual match resolver with TMDb poster comparison.
- [ ] **Subtitles Workspace (`Subtitles/Index.vue`):** Multi-language subtitle downloader, sync analyzer, and language badges.
- [ ] **Scanner (`Scanner/Index.vue`):** Real-time directory crawler HUD, discovery counters, and error resolution.
- [ ] **Physical Organizer (`Organizer/Index.vue`):** 4-step wizard with dry-run diff viewer, batch corrections, and audit logs.
- [ ] **Unified Job Center (`UnifiedJobCenterModal.vue`):** Real-time background job monitor with progress bars and abort triggers.

### [ ] Phase 7: System Pages, Audits, Verification & Documentation
- [ ] **System Analytics (`Analytics/Index.vue`):** Storage consumption charts, stream metrics, and library breakdown.
- [ ] **Settings (`Settings/Index.vue`):** Accent picker, storage paths, streaming transcode preferences, and API key management.
- [ ] **Documentation (`Docs/Index.vue`):** Developer and user manual with syntax highlighting and deep links.
- [ ] **UX Copy & Micro-Interactions Audit:** Empty states, error messages, tooltips, and confirmation dialogs.
- [ ] **Accessibility & RTL Verification:** WCAG 2.1 AA color contrast, touch target sizes (`2.75rem`), keyboard navigation, screen reader labels, and Arabic RTL layout audit.
- [ ] **Final Build & Test Suite:** Full passes for Pint, PHPUnit, Vue TypeScript check, Vite production build, and documentation updates.
