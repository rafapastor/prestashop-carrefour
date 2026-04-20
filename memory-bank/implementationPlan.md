# Implementation Plan — v1.0.0 MVP

Canonical plan for implementing v1.0.0. Updated as we progress. Maps directly to the tasks tracked in the Claude Code session.

## Design decisions (approved 2026-04-20)

### Architecture / API
1. HTTP client: **custom thin client** (no Composer deps). Optional SDK path documented later.
2. Catalog flow: **OF24 JSON** for up to ~500 SKUs per batch; **OF01 CSV** fallback for bulk imports.
3. Stock sync: hook `actionUpdateQuantity` + **30s debounce** → `STO01` update job.
4. Order sync: **OR11 incremental cron every 15 min** + optional HTTP webhook.
5. P11 (product creation): **out of v1.0** — assume products exist in Carrefour catalog.
6. Returns / Messages: **out of v1.0**.
7. Multi-shop: **credentials and endpoint per shop**.

### DB / code architecture
8. Category mapping: **custom admin UI with H11 data cached locally**.
9. API key storage: **encrypted** with `_COOKIE_KEY_` via `Util/Crypto`.
10. Job worker: support **both** system cron and PS cron module.
11. Order customer: always **guest customer** per Mirakl order, email as identifier.
12. Order payment: custom **`CarrefourPayment`** payment module registered inactive, used only to record Mirakl payment state.
13. SKU sent to Mirakl: `product_attribute.reference` → fallback `product.reference` (per-shop configurable).
14. Currency: **warn** if shop default ≠ EUR, but don't block.

## Database schema — 8 tables

All tables use `utf8mb4` / `utf8mb4_unicode_ci`, `InnoDB` engine (`_MYSQL_ENGINE_` placeholder). Every row has `date_add` + `date_upd`. All multi-shop tables include `id_shop`.

### `carrefour_shop_config`
- `id_carrefour_shop_config` PK
- `id_shop` UNIQUE
- `api_endpoint` VARCHAR(255), default `https://carrefoures-prod.mirakl.net/api`
- `api_key_encrypted` VARBINARY(1024) — cipher text
- `sandbox_mode` TINYINT(1)
- `shop_id_mirakl` VARCHAR(50) — used as `?shop_id=` query param on OF24 etc.
- `auto_accept_orders` TINYINT(1)
- `default_order_state_id` INT — PS order state to create orders with
- `stock_sync_enabled` TINYINT(1), `stock_sync_debounce_seconds` SMALLINT default 30
- `price_sync_enabled` TINYINT(1)
- `order_sync_interval_minutes` SMALLINT default 15
- `webhook_enabled` TINYINT(1)
- `webhook_secret` VARCHAR(64) — random 32-char string for URL path
- `sku_strategy` ENUM('attribute_ref_fallback_product','product_ref','ean13')
- `log_level` ENUM('debug','info','warn','error')

### `carrefour_listing`
- `id_carrefour_listing` PK, `id_shop`, `name`, `status` ENUM('active','paused','archived')
- `category_mapping_mode` ENUM('category_mapping','single_category','custom_attribute'), `category_mapping_value`
- `price_mode` ENUM('product','custom'), `price_variation_operator` ENUM('none','%_up','%_down','fixed_up','fixed_down'), `price_variation_value` DECIMAL(10,2)
- `stock_mode` ENUM('product','custom'), `stock_custom_value` INT

### `carrefour_offer`
- `id_carrefour_offer` PK, `id_shop`, `id_carrefour_listing` FK, `id_product`, `id_product_attribute` DEFAULT 0
- `sku` VARCHAR(50), `ean` VARCHAR(20), `offer_id_mirakl` BIGINT, `product_sku_mirakl` VARCHAR(50)
- `status` ENUM('pending','syncing','listed','error','paused','deleted')
- `price_sent` DECIMAL(15,6), `stock_sent` INT, `last_synced_at`, `last_error_code`, `last_error_message`, `last_error_at`
- UNIQUE `(id_shop, id_product, id_product_attribute)`

### `carrefour_order`
- `id_carrefour_order` PK, `id_shop`, `order_id_mirakl` VARCHAR(50), `commercial_id`, `id_order` (PS)
- `state` VARCHAR(50), `payment_type`, `total_price` DECIMAL(15,6), `currency_iso_code` CHAR(3)
- `customer_email`, `raw_payload` JSON, `shipping_deadline`, `created_date_mirakl`, `last_synced_at`
- UNIQUE `(id_shop, order_id_mirakl)`

### `carrefour_order_line`
- `id_carrefour_order_line` PK, `id_carrefour_order` FK, `order_line_id_mirakl`
- `offer_sku`, `product_sku_mirakl`, `product_title`, `quantity`, `unit_price`, `total_price`, `shipping_price`, `commission_amount`
- `line_state`, `accepted_at`, `shipped_at`, `tracking_number`, `carrier_name`
- UNIQUE `(id_carrefour_order, order_line_id_mirakl)`

### `carrefour_job`
- `id_carrefour_job` PK (BIGINT), `id_shop`, `type` VARCHAR(50), `status` ENUM, `priority` TINYINT
- `payload` JSON, `result` JSON, `mirakl_import_id` VARCHAR(50)
- `attempts` SMALLINT, `max_attempts` SMALLINT DEFAULT 5, `last_error_code`, `last_error_message`
- `scheduled_at`, `started_at`, `completed_at`
- INDEX `(status, scheduled_at)` — worker query

### `carrefour_category_mapping`
- `id_carrefour_category_mapping` PK, `id_shop`, `id_category_ps`, `category_code_mirakl`, `category_label_mirakl`
- UNIQUE `(id_shop, id_category_ps)`

### `carrefour_log`
- `id_carrefour_log` PK (BIGINT), `id_shop` nullable, `level` ENUM, `channel` VARCHAR(50)
- `message` VARCHAR(500), `context` JSON, `id_job` nullable
- INDEX `(id_shop, level, date_add)`, `(channel)`, `(id_job)`

## Class architecture

See also `agents/mirakl-api.md` for API-specific context.

```
classes/
├── Api/
│   ├── MiraklClient.php       # cURL wrapper, rate-limit + retry
│   ├── MiraklException.php    # MiraklException, MiraklAuthException, MiraklRateLimitException, MiraklValidationException
│   └── ErrorReport.php        # parse error_report CSV
├── Service/
│   ├── ConfigService.php      # get/set per-shop config, reads encrypted api_key
│   ├── OfferService.php       # buildPayload, diff, dispatchJob
│   ├── StockService.php       # debounce + enqueue StockUpdateJob
│   ├── OrderService.php       # pull OR11, create PS order, accept/ship
│   ├── CategoryMapper.php     # H11 fetch, cache, PS↔Mirakl mapping
│   └── WebhookHandler.php     # verify secret, parse payload, enqueue
├── Queue/
│   ├── JobQueue.php           # enqueue / dequeue / claim (SELECT … FOR UPDATE)
│   ├── JobWorker.php          # run loop
│   ├── JobRunner.php          # dispatch by job.type
│   └── Jobs/
│       ├── AbstractJob.php
│       ├── OfferUpsertJob.php
│       ├── StockUpdateJob.php
│       ├── OrderSyncJob.php
│       ├── OrderAcceptJob.php
│       └── OrderShipJob.php
├── Model/
│   ├── CarrefourShopConfig.php
│   ├── CarrefourListing.php
│   ├── CarrefourOffer.php
│   ├── CarrefourOrder.php
│   ├── CarrefourOrderLine.php
│   ├── CarrefourJob.php
│   ├── CarrefourCategoryMapping.php
│   └── CarrefourLog.php
├── Logger/
│   └── CarrefourLogger.php    # levels, writes to carrefour_log + file when level≥warn
└── Util/
    ├── Crypto.php             # encrypt/decrypt with _COOKIE_KEY_ (AES-256-CBC)
    └── Debounce.php           # tracks last-flush per shop for stock
```

## Phases

Each phase is a separate task tracked in the session. Phases are sequential; do not start phase N+1 until phase N is merged and smoke-tested locally.

### Phase 1 — Foundation

No API calls. All scaffolding to make the module installable and show a useful "not configured" state.

**Deliverables**
- `sql/install.php` with 8 `CREATE TABLE` statements + seeds if any.
- `sql/uninstall.php` with corresponding `DROP TABLE`.
- 8 ObjectModel classes in `classes/Model/`, one per table.
- `carrefourmarketplace.php`:
  - `install()`: run SQL, register hooks (`actionUpdateQuantity`, `actionOrderStatusPostUpdate`, `displayBackOfficeHeader`), create admin tabs (parent + 7 children), register inactive `CarrefourPayment` payment module (as a module of its own? or as a PaymentModule subclass bundled here? — decide during implementation).
  - `uninstall()`: remove tabs, unregister hooks, drop tables via uninstall.php, delete config values.
- `classes/Logger/CarrefourLogger.php`: levels, DB + file fallback.
- `classes/Util/Crypto.php`: AES-256-CBC with `_COOKIE_KEY_` as key.
- `controllers/admin/AdminCarrefourParentController.php`: minimal stub that redirects to config.
- `controllers/admin/AdminCarrefourConfigController.php`: form with endpoint + api_key + sandbox toggle + shop_id_mirakl + log_level. Save encrypts api_key via Crypto. No "test connection" yet (that's Phase 2).

**Smoke tests**
- Install on fresh PS 8 via Docker (`make dev`), check tabs appear.
- Set config, reopen, verify api_key decrypts.
- Uninstall cleanly, verify tables dropped.

### Phase 2 — Mirakl client

**Deliverables**
- `classes/Api/MiraklClient.php`:
  - `__construct($endpoint, $apiKey, $shopIdMirakl)`.
  - `request($method, $path, $query, $body, $options)` — returns normalized response.
  - Retry with exponential backoff on 429 and 5xx (max 5 attempts).
  - Throws typed exceptions on 4xx.
  - Supports multipart uploads for CSV imports.
  - `testConnection()` calling `A01` → returns shop info or throws.
- `classes/Api/MiraklException.php` + subclasses.
- `classes/Api/ErrorReport.php`: parse CSV returned by `/imports/{id}/error_report`.
- ConfigController extended with "Test connection" button → calls `testConnection` and displays result.
- `tests/Unit/Api/MiraklClientTest.php`: mock cURL, cover 200/401/429/500, multipart body shape.
- `tests/Unit/Api/ErrorReportTest.php`: CSV fixture → structured errors.
- Composer setup for dev (`composer.json` with `phpunit/phpunit` + autoload-dev). Module itself still has no runtime Composer deps.

**Smoke tests**
- On sandbox (when we have credentials), "Test connection" succeeds and shows Mirakl shop_id.
- With bad key, shows a clear error.

### Phase 3 — Catalog

**Deliverables**
- `classes/Service/CategoryMapper.php`: fetch H11, cache in `carrefour_category_mapping` + a serialized Mirakl tree cached per shop.
- `controllers/admin/AdminCarrefourCategoriesController.php`: side-by-side tree (PS categories vs Mirakl H11) with dropdowns.
- `classes/Service/OfferService.php`:
  - `buildPayload(Listing $listing, array $products)` → Mirakl OF24 JSON body.
  - `dispatchUpsert(Listing $listing)` → enqueue `OfferUpsertJob`.
  - Diff logic: skip offers where `price_sent`, `stock_sent` match current.
- `classes/Queue/Jobs/OfferUpsertJob.php`:
  - Build payload, POST via client, save `mirakl_import_id` on job row.
  - Poll completion (`GET /offers/imports/{id}`) — if not complete, re-schedule self.
  - On complete, fetch `error_report`, update `carrefour_offer.status` + `last_error_*` per row.
- `controllers/admin/AdminCarrefourListingsController.php`: CRUD for listings.
- `controllers/admin/AdminCarrefourOffersController.php`:
  - List offers per listing with status, last error, last sync time.
  - Bulk actions: "Re-sync", "Pause", "Remove from listing".
  - Upload: select PS products → add to listing.

**Smoke tests**
- Create listing, add 5 PS products, dispatch upsert on sandbox, verify listed in Carrefour preprod.

### Phase 4 — Stock sync

**Deliverables**
- Hook `actionUpdateQuantity` in `carrefourmarketplace.php`:
  - If shop has an offer for that product, enqueue `StockUpdateJob` (debounced).
- `classes/Service/StockService.php`: handles debouncing. Debouncing means coalescing rapid updates into one job per (shop, product) flushed after N seconds.
- `classes/Queue/Jobs/StockUpdateJob.php`: uses `STO01`.
- `classes/Queue/JobWorker.php`:
  - Loop: claim job (row lock), run, update status, sleep N seconds on empty.
  - Graceful exit on SIGTERM.
- `classes/Queue/JobRunner.php`: dispatch by `type`.
- `cron/worker.php`: PHP entry point for system cron. Takes `--max-jobs` and `--max-seconds` args to avoid running forever.
- PS cron module integration via `hookCronBackground` (or equivalent).

**Smoke tests**
- Change product stock in PS admin, wait 30s, confirm stock updated in Carrefour sandbox.
- Stress: update 100 products in 5s, verify debouncing coalesces into ≤ 100 STO01 calls (1 per product, not 1 per change).

### Phase 5 — Order import

**Deliverables**
- `classes/Service/OrderService.php`:
  - `pullRecent($sinceDate)` → OR11 paginated.
  - `createPrestaShopOrder($miraklOrder)`: creates guest Customer, Address (billing + shipping), Cart, Order with `CarrefourPayment` module.
  - `accept($orderId, $lineIds)` → OR21.
  - `ship($orderId, $lineIds, $tracking)` → OR23 + OR24.
- `classes/Queue/Jobs/OrderSyncJob.php`: scheduled job, runs every 15 min (re-schedules itself).
- `classes/Queue/Jobs/OrderAcceptJob.php`, `OrderShipJob.php`.
- `controllers/admin/AdminCarrefourOrdersController.php`:
  - List Mirakl orders with state, PS order link, actions.
  - Action buttons: Accept, Refuse, Ship, Refresh.
- Hook `actionOrderStatusPostUpdate`:
  - If a PS order linked to a Mirakl order transitions to "shipped", enqueue `OrderShipJob` with PS tracking number.
- `cron/orders-sync.php`: system cron entry point (alternative to JobWorker for just order sync).

**Smoke tests**
- Manually create a test order in Carrefour sandbox → run sync → PS order appears with correct lines, customer, address.
- Accept in PS admin → accepted in Carrefour.
- Mark as shipped in PS → shipped in Carrefour with tracking.

### Phase 6 — Webhooks, logs, polish

**Deliverables**
- `controllers/front/WebhookController.php`:
  - Endpoint `/module/carrefourmarketplace/webhook/{secret}`.
  - Validate secret against `carrefour_shop_config.webhook_secret`.
  - Returns 200 ASAP after enqueuing, does NOT process inline.
- `classes/Service/WebhookHandler.php`:
  - Parse `ORDER` event → enqueue `OrderSyncJob` scoped to that specific order (faster path).
  - Parse `OFFER` event → log for diagnostics (we don't do anything since offers are driven by us).
- `controllers/admin/AdminCarrefourLogsController.php`:
  - Filterable log viewer (shop, level, channel, date range).
  - Pagination.
  - Download as CSV.
- `controllers/admin/AdminCarrefourJobsController.php`:
  - Job dashboard, filter by status.
  - Retry button (resets attempts, schedules now).
  - Cancel button.
- `cron/logs-cleanup.php`: trim `carrefour_log` older than `log_retention_days` (default 30).
- End-to-end smoke testing on sandbox — full day: upload catalog, simulate order, ship, refund.
- Documentation pass: complete `docs/installation.md`, `configuration.md`, `multishop.md`, `troubleshooting.md`, `faq.md`.
- README screenshots / GIF.

**Release**
- Bump to `1.0.0`, move `[Unreleased]` to `[1.0.0]` in CHANGELOG.
- Tag `v1.0.0` → GitHub Actions `release.yml` publishes ZIP.

## Testing strategy

- **Unit tests** (PHPUnit): pure logic in `Api/`, `Service/`, `Util/`, `Queue/Jobs/`. Mock `MiraklClient` and `Db`.
- **Integration tests**: against Mirakl preprod sandbox (Carrefour). Separate PHPUnit suite tagged `@group integration`, opt-in via env var `CARREFOUR_SANDBOX_KEY`.
- **Smoke tests**: manual, listed per phase.
- **CI**: unit tests only. Integration tests run manually before release.

## Risks / open questions during implementation

- **Inactive `CarrefourPayment` module**: PS's API for registering a "payment module that is never displayed in front" is slightly fiddly. Research during Phase 1 — may need a minimal separate module class in the same module folder.
- **PS 1.6 quirks**: `ObjectModel` API is mostly stable but some methods differ. Test install on 1.6 early.
- **`actionUpdateQuantity` variations**: hook name + signature changed between PS versions (`actionUpdateQuantity`, `hookActionProductUpdate`, stock-specific hooks). Handle across 1.6/1.7/8/9.
- **JSON column types**: PS 1.6 on MySQL 5.5 may not support native JSON. Use `LONGTEXT` with application-level JSON encode/decode as fallback when `MySQL < 5.7` or `MariaDB < 10.2`.
- **`Order::add()` with guest customer**: PS handles it, but the Cart must be built correctly (Address IDs, Carrier, currency, taxes).

These get decided / resolved phase-by-phase. Log them in this file when we hit them.

## Definition of done for v1.0.0

- [ ] All 6 phases complete.
- [ ] Smoke tests pass on PS 1.7.8, PS 8.1, PS 9.0 (minimum one each).
- [ ] Unit test coverage ≥ 50% on `classes/Api/`, `classes/Service/`, `classes/Queue/Jobs/`.
- [ ] Integration tests against sandbox pass (when Rafa has credentials).
- [ ] Documentation for installation, configuration, multishop published.
- [ ] README updated with real screenshots and GIF.
- [ ] CHANGELOG entry for 1.0.0 written.
- [ ] GitHub release published with ZIP attached.
