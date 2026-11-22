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

### [x] Phase 4: Shared CreativeFlow UI Primitives
- [x] **Page Header & Banner:** `PageBanner.vue` with hero `.cf-glass rounded-3xl` container, `-end-16 -top-16` glow orb, icon badge, title, pill badge indicators, description, and `#actions` slot.
- [x] **Filter Controls:** `FilterPills.vue` with scrollable `role="tablist"` strip, `min-h-10` WCAG touch targets, locale-formatted `Intl.NumberFormat` count chips (`tabular-nums`), and active primary accent pill.
- [x] **Base & Confirm Modals:** `BaseModal.vue` built on `reka-ui` Dialog (`sm`–`4xl`, icon badge header, scrollable `max-h-[90dvh]` body, `#header`/`#footer` slots) and `ConfirmModal.vue` migrated from raw overlay div to accessible `reka-ui` Dialog (`sm` size, semantic `danger`/`warning`/`info` rings, 100% backward-compatible props/events/slots).
- [x] **Action Buttons, Spinner & State Machine:** `ActionButton.vue` (4-state animated presets: `save`, `download`, `generate`, `upload`, `send`, `delete`, `default` with `aria-busy`, `aria-live="polite"`, and progress percentage), `ui/spinner/Spinner.vue`, and `useActionState.ts` (`idle -> loading -> success | error -> idle` + `downloadWithProgress`).
- [x] **Cards, View Toggle, Data Tables & Pagination:** `ViewModeToggle.vue` (persisted `table` | `cards` switch), `DataTableCard.vue` (dual-view/single-card responsive container with `.cf-table-wrapper`), and `Pagination.vue` upgraded to `.cf-glass`, active primary tokens, and `tabular-nums`.
- [x] **RTL Shimmer Skeletons & Floating Glass Sidebar Polish:** `CinemaSkeleton.vue` upgraded to GPU-composited RTL-aware `.cf-shimmer` (`MediaCardSkeleton`, `HeroBannerSkeleton`, `TableRowSkeleton`, `ScoutFranchiseSkeleton`, `ScoutMetricSkeleton` aligned to `.cf-glass` and logical `end-2.5`); desktop `Sidebar.vue` converted to a floating rounded glass card (`rounded-3xl .cf-glass-elevated`); `AppLogo.vue` English subtitle truncation overflow fixed.

### [x] Phase 5: Cinema Catalog & Discovery Pages
- [x] **Dashboard (`Dashboard/Index.vue`):** Hero spotlight carousel (`HeroBanner.vue`), continue watching shelf (`WatchHistoryBar.vue`), recent additions, Vibe selector strip, and quick media stats upgraded to semantic Cinema Dark tokens (`.cf-glass`, `bg-primary`, `tabular-nums`).
- [x] **Movies (`Movies/Index.vue`):** High-density poster grid wrapped in `MotionList`, `FilterBar.vue` (`.cf-glass`, `.cf-field`, active primary pills), sort dropdown, and skeleton loading states.
- [x] **Series (`Series/Index.vue` & `Series/Show.vue`):** Show catalog unified with `MediaCard.vue` and `MotionList`; season/episode inspector upgraded with `.cf-glass`, `FilterPills`, `tabular-nums`, and Bandersnatch interactive launch.
- [x] **Collections (`Collections/Index.vue` & `Collections/Show.vue`):** Franchise collection cards with `PageBanner`, `MotionList`, completion progress bars, gap alerts, and accessible `ConfirmModal` replacing raw `window.confirm()`.
- [x] **Watch History (`WatchHistory/Index.vue`):** Interactive timeline with `PageBanner`, `FilterPills` (`all`, `movie`, `series`, `collection`), `.cf-field` search, `MotionList` grids, `.cf-shimmer` skeletons, and `ConfirmModal` clear confirmation.
- [x] **Media Cards & Modals:** Modernized `MediaCard.vue`, `HeroBanner.vue`, `FilterBar.vue`, `WatchHistoryCard.vue`, and `MediaDetailModal.vue` (accessible dialog with Escape guard for nested `FixMatchModal`, `.cf-glass-elevated`, and `tabular-nums` technical specs).

### [x] Phase 6: Acquisition & Studio Workspaces
- [x] **Scout (`Scout/Index.vue`):** Studio intelligence workspace with `PageBanner`, mode/provider pills, `.cf-glass` metric and franchise gap cards, `bg-primary` completion progress fill, `TorrentSuggestionsModal.vue`, and `FastOrganizeModal.vue` upgraded to `.cf-glass-elevated`, `.cf-field`, `.cf-shimmer`, and `tabular-nums`.
- [x] **Downloader (`Downloader/Index.vue`):** Torrent client manager with `PageBanner`, connection badge, global transfer speed cards, `TorrentTreeItem.vue`, `FilterPills`, `.cf-field` Add Torrent modal, and `tabular-nums` on speeds, sizes, seeds/peers, ETA, and percentages.
- [x] **Metadata Manager (`Metadata/Index.vue`):** Library enrichment workspace with `PageBanner`, health metrics (`tabular-nums`), Batch Enrich CTA, filter tabs, `.cf-field` search, and `.cf-glass` / `.cf-table-wrapper` table & cards.
- [x] **Fix Match (`FixMatchModal.vue`):** Split-pane visual match resolver upgraded to `.cf-glass-elevated border-border/80`, `.cf-field` inputs, `bg-primary` active tabs and Apply CTAs, and `tabular-nums`.
- [x] **Subtitles Workspace (`Subtitles/Index.vue`):** Multi-language subtitle hub with `PageBanner`, 6 `.cf-glass` coverage KPI cards (`tabular-nums`), segmented filter bar, `.cf-table-wrapper` coverage table, and `SubtitlePickerModal.vue` upgraded to `.cf-glass-elevated`.
- [x] **Scanner (`Scanner/Index.vue`):** Real-time directory crawler HUD with `PageBanner`, 5 `.cf-glass` storage & scan metric cards, live scan progress banner, root library cards, and accessible `ConfirmModal` replacing `window.confirm()`.
- [x] **Physical Organizer (`Organizer/Index.vue`):** 3-step wizard (`OrganizerConfigStep.vue`, `OrganizerDryRunStep.vue`, `OrganizerExecutionStep.vue`) + `OrganizerItemCorrectionModal.vue` and `OrganizerBatchCorrectionModal.vue` upgraded with `PageBanner`, `.cf-glass` stepper, `.cf-table-wrapper`, `.cf-field`, and `tabular-nums`.
- [x] **Unified Job Center (`UnifiedJobCenterModal.vue`):** Real-time background job monitor upgraded to `.cf-glass-elevated border-border/80 rounded-3xl`, active job cards (`border-primary/35`, `bg-primary` progress bar), and `tabular-nums` terminal logs.

### [/] Phase 7: System Pages, Audits, Verification & Documentation
- [ ] **System Analytics (`Analytics/Index.vue`):** Storage consumption charts, stream metrics, and library breakdown.
- [ ] **Settings (`Settings/Index.vue`):** Accent picker, storage paths, streaming transcode preferences, and API key management.
- [ ] **Documentation (`Docs/Index.vue`):** Developer and user manual with syntax highlighting and deep links.
- [ ] **UX Copy & Micro-Interactions Audit:** Empty states, error messages, tooltips, and confirmation dialogs.
- [ ] **Accessibility & RTL Verification:** WCAG 2.1 AA color contrast, touch target sizes (`2.75rem`), keyboard navigation, screen reader labels, and Arabic RTL layout audit.
- [ ] **Final Build & Test Suite:** Full passes for Pint, PHPUnit, Vue TypeScript check, Vite production build, and documentation updates.
