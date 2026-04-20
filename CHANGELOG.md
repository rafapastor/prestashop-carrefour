# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Project scaffolding (OSS-first structure).
- AGPL-3.0 license.
- Docker-based local dev environment.
- CI workflow (PHP CS Fixer + PHPUnit skeleton).
- Phase 1 foundation: 8 database tables, ObjectModel classes, autoloader for `classes/` subfolders, AES-256-CBC API key encryption (`CarrefourCrypto`), structured logger (`CarrefourLogger`), installable module shell with admin tabs and Configuration screen.
- Phase 2 Mirakl client: `MiraklClient` with cURL transport, retry-with-backoff on 429 and 5xx, typed exception hierarchy (`MiraklAuthException`, `MiraklNotFoundException`, `MiraklRateLimitException`, `MiraklServerException`, `MiraklValidationException`, `MiraklNetworkException`). CSV `error_report` parser (`MiraklErrorReport`). "Test connection" button on the config screen calling Mirakl A01 (`GET /account`) with clear per-error-type feedback. PHPUnit test suite (composer + phpunit.xml + bootstrap + 17 tests covering client and error report).
- Phase 3a catalog foundation: `CarrefourOfferService` payload builder (OF24-compatible, configurable SKU strategy, price/stock modes, category resolution), `CarrefourCategoryMapper` data layer, `CarrefourJobQueue` with retry/backoff/rescheduling, `CarrefourAbstractJob` + `CarrefourOfferUpsertJob` (two-phase submit + poll lifecycle, parses Mirakl `error_report` and updates per-offer status). Admin tabs for Listings, Offers and Category mapping (plus full CRUD controller for Listings). OfferService unit test suite (18 new tests).
- Phase 3b catalog operations: Offers controller with "Add products to listing" form and "Dispatch to Mirakl" button that enqueues `CarrefourOfferUpsertJob`. Categories controller with "Refresh Mirakl hierarchy" button (H11) and editable PS-category → Mirakl-code mapping table, plus cached hierarchy viewer for reference. `CarrefourHierarchyService` caches H11 tree on disk under `data/shop_{id}/`.
- Phase 4 stock sync: `actionUpdateQuantity` hook debounces rapid PS stock changes into a single pending `CarrefourStockUpdateJob` per shop within a configurable window (default 30s). `CarrefourJobQueue` claim semantics, `CarrefourJobRunner` dispatches by type with typed exception → retryable/non-retryable mapping, `CarrefourJobWorker` loop with SIGTERM handling, `cron/worker.php` CLI entry point. Stock job uses Mirakl STO01 (`POST /offers/stocks`) and always reads latest PS stock at run time so coalesced changes send the newest value.
- Phase 5 order import: `CarrefourOrderService` pulls Mirakl orders via OR11 with pagination + `start_update_date` incremental watermark, mirrors into `carrefour_order` / `carrefour_order_line`, optionally creates a matching PrestaShop order (guest customer, addresses, cart, `PaymentModule::validateOrder`). Jobs for periodic sync (`CarrefourOrderSyncJob` with self-rescheduling), OR21 accept (`CarrefourOrderAcceptJob`), OR23 tracking + OR24 ship (`CarrefourOrderShipJob`). Main module now extends `PaymentModule`. `actionOrderStatusPostUpdate` hook enqueues `order_ship` when a PS order linked to a Mirakl order transitions to shipped. Admin tab for Orders with "Pull now / Accept / Ship" action buttons. `cron/orders-sync.php` CLI entry.
- Phase 6 webhooks, jobs and logs: `WebhookController` front controller at `/module/carrefourmarketplace/webhook?secret=…&shop=…` validating per-shop secret and enqueuing an `order_sync` job on ORDER events. `CarrefourWebhookHandler` parses payloads. AdminCarrefourJobs controller with retry-from-ID action, AdminCarrefourLogs read-only viewer with shop scoping. `cron/logs-cleanup.php` trims rows older than `log_retention_days`.

### Docs
- Complete user-facing documentation in `docs/`: installation (with cron setup), configuration (every field explained), multishop topologies, troubleshooting, FAQ (with honest comparison against Shoppingfeed/Lengow/Iziflux/Mirakl Connect), Mirakl API reference.
- Spanish README (`README.es.md`) + expanded English README with comparison table and audience guidance.

### Hardening
- `CarrefourCrypto` unit test suite (8 tests): encrypt/decrypt roundtrip, base64 shape, IV randomisation, unicode, long strings, tamper detection, empty-input handling.
- Empty but present `views/css/admin.css` (with status-pill styling) and `views/js/admin.js` so the admin header hook doesn't 404 when loading module assets.
- `hookPaymentOptions()` returns an empty array as a safety net: the module extends `PaymentModule` so it can call `validateOrder()` when importing Mirakl orders, but must never appear as a checkout option for front-office customers.

### Fixed
- Admin controllers with `bulk_actions` initialised before `parent::__construct()` caused a PS 8.2 fatal `Call to a member function trans() on null` — moved initialisation after `parent::__construct()` in Listings, Offers, Jobs, Logs and Categories controllers.
- Docker admin URL renamed to `/admindev` (default `/admin` is blocked by PS security on direct access); `docker-compose.yml` and `Makefile` updated.
- Module verified end-to-end via Chrome DevTools: install/uninstall cycle clean, all 7 admin tabs render, CRUD on Listings works, zero JS console errors.

### Performance
- `CarrefourCategoryMapper` now lazy-loads the shop's complete mapping table on first lookup and serves subsequent calls from an in-memory cache. Eliminates the N+1 query pattern in `OfferService::buildBatchPayload` where each product triggered its own SELECT. Verified with a unit test: 300 lookups → 1 DB query.

### Accessibility
- Custom HTML forms in the Offers, Orders, Jobs and Category-mapping controllers now have explicit `<label for="…">` / `id="…"` associations on every input. The per-category mapping inputs carry `aria-label` so screen readers announce the PS category name alongside the code and label fields.

### Tooling and repo
- `composer.json` + pinned `composer.lock` committed; dev dependencies installable with `composer install`. The Makefile still downloads `phpunit.phar` on demand for zero-setup testing.
- `.github/dependabot.yml` keeps Composer, GitHub Actions and Docker base image updated weekly/monthly with labeled PRs.
- `scripts/` folder with developer utilities: `dev-reset.sh` (wipe + restart), `dump-state.sh` (DB summary for bug reports), `logs.sh` (tail module logs), `run-worker.sh` (one-shot worker run).
- `upgrade/upgrade-1.1.0.php` skeleton with idempotent-migration pattern in comments for the next minor release.

## [1.0.0] — Target 2026-06

Initial public release. Not yet shipped; milestone targets:

- Mirakl client abstraction with retry and structured error handling.
- Catalog upload (offers).
- Real-time stock sync via PrestaShop hooks.
- Order import (Mirakl orders → PrestaShop orders).
- Multi-shop support with per-shop credentials.
- Async job queue for bulk operations.
- Error dashboard with retry actions.
- Sandbox / production toggle.
- Admin UI in English and Spanish.
- Compatible with PrestaShop 1.6 → 9.x.

[Unreleased]: ../../compare/v1.0.0...HEAD
[1.0.0]: ../../releases/tag/v1.0.0
