# Mirakl API — research notes

Snapshot as of **2026-04-20**. This document summarises what we learned from the Mirakl developer portal, the Mirakl PHP SDK repo, and third-party connectors (Channable, ChannelEngine, Webkul, Synchron, Sellercloud, JThyroff/miraklconnector).

It's **internal memory**, not user-facing docs. Update when we discover new things.

## Sources

- **Official docs**: https://developer.mirakl.com/content/product/mmp/rest/seller/openapi3 (Mirakl Marketplace Platform — MMP Seller API, OpenAPI 3).
- **PHP SDK (official)**: https://github.com/mirakl/sdk-php-shop (Composer package `mirakl/sdk-php-shop`).
- **Carrefour FR Postman**: https://www.postman.com/mission-engineer-50706761/carrefour-fr/collection/y4fug51/mirakl-seller-api-mcm (community collection, useful as reference even though FR not ES).
- **Third-party connector examples**: Channable Carrefour Spain guide, ChannelEngine guide, Webkul PrestaShop Mirakl Connector, Synchron.io, JThyroff/miraklconnector (GitHub).

## TL;DR

Mirakl exposes a REST+JSON API. Every operator (Carrefour, Leroy Merlin, FNAC…) runs their own Mirakl instance at a dedicated subdomain. A seller authenticates with an **API key** from their seller backoffice, sends it in the `Authorization` header (raw value, **not** `Bearer xxx`), and all requests go over HTTPS.

The API is asynchronous by nature: most write operations return an **import_id**. You poll for status and download an **error_report CSV** for failed rows.

An **official Mirakl PHP SDK** exists (`mirakl/sdk-php-shop`), installable via Composer.

## Authentication

### Header

```http
Authorization: YOUR_API_KEY
```

The API key is the **raw value**, not prefixed with `Bearer`. This is different from most modern APIs.

> Note: the Mirakl docs mention a "legacy API key" with the same header format marked as deprecated somewhere, but the newer APIs still use the same `Authorization: YOUR_API_KEY` pattern. Track this — may change over time.

### Per-environment keys

- **Test environment**: seller generates one API key here first.
- **Production**: once integration is validated by the operator (Carrefour in our case), a new prod key is generated. Test and prod are isolated; keys are not interchangeable.

### How the seller gets the key

From the Mirakl seller backoffice (Carrefour Hub seller portal): user menu → *My user settings* → *API key* section. Generate and copy.

## Base URL pattern

Each operator has its own subdomain:

```
https://{operator-env}.mirakl.net/api
```

### Carrefour ES (Carrefour Hub)

Verified 2026-04-20 (endpoint probing without credentials):

| Environment | URL | Verification |
|---|---|---|
| **Production** | `https://carrefoures-prod.mirakl.net/api` | `GET /api/account` returns HTTP 401 (live, needs auth) |
| **Sandbox / Preprod** | `https://carrefoures-preprod.mirakl.net/api` | `/login` serves Mirakl "Sign in with Inicio SSO" page |

Both subdomains are live in 2026. Same pattern as most Mirakl operators (`{slug}-prod` vs `{slug}-preprod`).

Auth for both: seller-generated API key in the respective backoffice (test key for preprod, separate prod key once integration is validated by Carrefour).

Store as `CARREFOUR_API_ENDPOINT` + `CARREFOUR_API_KEY` per shop; ship the prod URL as default with a sandbox toggle that swaps to preprod automatically.

## HTTP conventions

- Content-Type: `application/json` for most requests; `multipart/form-data` for file uploads (CSV imports).
- All responses JSON unless you explicitly request CSV (e.g. `error_report`).
- Dates in ISO 8601 UTC: `2026-04-20T14:15:22Z`.
- Pagination: `offset` + `limit` query params, max 100 per page. Iterate.

## Key endpoints for the MVP

### Offers (catalog as seller-provided SKUs)

| Code | Method + Path | Purpose |
|------|---|---|
| `OF01` | `POST /api/offers/imports` | Import a file (CSV/Excel) to create/update/delete offers. Async. Returns `import_id`. |
| `OF24` | `POST /api/offers?shop={id}` | Create/update/delete offers via JSON body. Returns `import_id`. **⚠ Un-sent fields are reset to defaults.** |
| `OF21` | `GET /api/offers` | List shop's offers with filters. Paginated. |
| `STO01` | `POST /api/offers/stocks` | Update stock levels only (hot path, lightweight). |
| `PRI01` | `POST /api/offers/pricing/imports` | Update prices only via CSV. |
| `P11` | `GET /api/products/{sku}/offers` | List offers for a product (when Carrefour product exists and you want to match). |
| `export` | `POST /api/offers/export/async` then `GET /api/offers/imports/{id}/error_report` | Async export + download result. |

### Orders

| Code | Method + Path | Purpose |
|------|---|---|
| `OR11` | `GET /api/orders` | List orders with many filters (state, date range, etc.). **Main incremental sync endpoint**. |
| `OR12` | `GET /api/orders/{order_id}` | Single order details. |
| `OR21` / `OR41` | `PUT /api/orders/{order_id}/accept` | Accept / refuse order lines (required for `WAITING_ACCEPTANCE` orders). |
| `OR23` | `PUT /api/orders/{order_id}/tracking` | Update carrier tracking. |
| `OR24` | `PUT /api/orders/{order_id}/ship` | Confirm shipment. |
| `OR28` | `POST /api/orders/{order_id}/refund` | Refund on lines. |
| `OR07` | `PUT /api/orders/shipping_from` | Set shipping origin. |
| `OR72` | `GET /api/orders/{order_id}/documents` | List invoice/label documents. |

Incremental sync pattern: `OR11` with `start_update_date=<last_sync_datetime>` + pagination, filtered by `order_state_codes` if you only care about certain states.

### Order lifecycle states

```
STAGING
  → WAITING_ACCEPTANCE  (seller must accept within N days or auto-cancel)
  → WAITING_DEBIT       (customer payment in flight)
  → WAITING_DEBIT_PAYMENT
  → SHIPPING            (accepted, not yet shipped)
  → SHIPPED
  → TO_COLLECT / RECEIVED
  → CLOSED

Parallel terminal states:
  CANCELED   (either side cancelled)
  REFUSED    (seller refused)
  REFUNDED
  INCIDENT_OPEN (in dispute)
```

### Products (Carrefour catalog items)

| Code | Method + Path | Purpose |
|------|---|---|
| `P41` | `POST /api/products/imports` | Submit products to operator (P11 flow, create product in Carrefour catalog). |
| `P31` | `GET /api/products` | Retrieve products by reference / SKU. |
| `PM11` | `GET /api/products/attributes` | Product attribute configuration (what attributes Carrefour accepts). |
| `H11` | `GET /api/hierarchies` | Carrefour's category tree (their own taxonomy; NOT PrestaShop's). |

The **P11 flow** is the legal path to create a product in Carrefour's catalog when it doesn't exist yet. For the MVP we focus on offers only — we assume the Carrefour product already exists and we just post an offer against its EAN/SKU. P11 flow is a v1.1+ feature.

### Messages (communication with buyer)

| Code | Path | Purpose |
|------|---|---|
| `M11` | `GET /api/inbox/threads` | List conversation threads. |
| `M12` | `POST /api/inbox/threads/{id}/message` | Reply to thread. |
| `M14` | `POST /api/inbox/threads` | Start new thread with operator. |

Can be integrated as PS order notes in a later version.

### Returns

`RT11`, `RT21`, `RT26` — listed, approved, compliance check. **Scope for v1.1 or later.**

### Shop info

| Code | Path | Purpose |
|------|---|---|
| `A01` | `GET /api/account` | Current shop info (useful for "Test connection" button — if A01 returns 200 with the expected shop_id, credentials are valid). |

## Async import pattern

Every bulk write is asynchronous. The sequence is:

1. `POST /api/offers` (or `/imports`) with payload → response has `import_id`.
2. Client polls `GET /api/offers/imports/{import_id}` (typical delay 2-60s depending on size).
3. When `status == COMPLETE`, client downloads `GET /api/offers/imports/{import_id}/error_report` for any rows that failed.

Our module's job queue wraps this naturally: enqueue import, cron worker polls status until complete, then persists results + errors to the DB for the Error Dashboard to surface.

## Webhooks

### Event types
- `ORDER` — order created, status changed, cancelled, refunded, etc.
- `OFFER` — offer created, updated, deleted (upsert/delete actions).

### Connector modes
- **HTTP connector** — Mirakl POSTs events to a URL you provide. Simplest. **Recommended for our MVP.**
- **Cloud connectors** — AWS SQS, GCP Pub/Sub, Azure Service Bus. For high-volume sellers. Not needed for v1.0.

### Payload shape (HTTP)
```json
{
  "event_type": "ORDER",
  "payload": [
    {
      "details": { "order_id": "Order_00001-A", "order_state": "WAITING_ACCEPTANCE", "order_lines": [...], "customer": {...} }
    }
  ]
}
```
Full example in Mirakl docs. Orders include embedded customer + address data — useful: we don't need another round-trip for most sync operations.

### Security
Mirakl does **not** (as of this research) sign webhook payloads with HMAC. Mitigations:
- Use a long random URL path (`/module/carrefourmarketplace/webhook/{random_32_chars}`).
- Restrict by source IP if Mirakl publishes their IPs (verify).
- Idempotency: webhook events may be duplicated — the handler should be idempotent via `order_id` or `offer_id` as key.

### Polling as backup
Even with webhooks enabled, run an `OR11` incremental sync every 15-30 min as safety net. Deduplicate by `order_id + event_date`.

## Rate limits

Not publicly documented per endpoint. Observed in third-party guides:
- Ballpark 5-10 req/s per seller.
- `HTTP 429` when exceeded → exponential backoff with jitter (start 2s, double up to 60s max, 5 retries).
- Bulk imports preferred over individual calls (`OF01` over many `OF24`).

## Error handling

Standard HTTP codes:
- `200/201` — OK.
- `400` — payload malformed or business rule violated. **Do NOT retry.** Surface to user.
- `401` — unauthorized (bad / expired API key).
- `403` — forbidden (endpoint not available for this seller type).
- `404` — resource not found.
- `429` — rate limit → backoff.
- `5xx` — server error → retry with backoff.

Mirakl-specific error codes appear in responses as strings like `OF-23` or `IP-015`; they're per-endpoint. `error_report` CSV decodes them row-by-row for imports.

## PHP SDK vs custom HTTP client

### Option A — Official Mirakl PHP SDK (`mirakl/sdk-php-shop`)

```php
composer require mirakl/sdk-php-shop

use Mirakl\MMP\Shop\Client\ShopApiClient;
use Mirakl\MMP\Shop\Request\Offer\GetOfferRequest;

$api = new ShopApiClient($url, $apiKey, $shopId);
$result = $api->getOffer(new GetOfferRequest($offerId));
```

**Pros**: typed request/response objects, maintained by Mirakl, covers most endpoints, less boilerplate.

**Cons**: large dependency tree (Guzzle + assorted), Composer required (may be awkward for PS 1.6 native installs), fixed to Mirakl's release cadence, can be overkill for simple module needs.

### Option B — Thin custom HTTP client

A single `MiraklClient` class wrapping cURL or `Tools::file_get_contents`, with methods mirroring the endpoints we actually use (`listOrders`, `importOffers`, `updateStock`, etc.). Transparent JSON (no opaque DTOs). No Composer dependency required.

**Pros**: zero external deps (works cleanly on PS 1.6 hosts without Composer), small surface area, easy to audit, easy to mock in tests, bundles fit in a ZIP with nothing extra.

**Cons**: we reimplement some boilerplate (retries, serialization, error handling), need to track Mirakl API changes manually.

### Recommendation

**Custom HTTP client** for v1.0.0:
- PS 1.6 / 1.7 hosts often don't have Composer. An OSS module that installs with a ZIP upload should Just Work.
- The API surface we need for MVP is ~12-15 endpoints. Manageable.
- We can expose a provider interface (`MiraklClientInterface`) so a future v2 can swap in the SDK as an optional dependency for power users.

The Mirakl SDK stays as an **optional** path documented in `docs/advanced.md` for developers who want typed objects and are comfortable with Composer in their PS install.

## Carrefour ES / Carrefour Hub specifics

- Marketplace name: **Carrefour Marketplace** (consumer-facing), **Carrefour Hub** (seller portal).
- Website: `marketplace.carrefour.es`.
- Operator seller portal: via Carrefour Hub, URL obtained after seller approval.
- Runs on Mirakl. Third-party connectors (Channable, ChannelEngine, Sellercloud) confirm Mirakl-standard endpoints apply.
- **Category taxonomy**: Carrefour's own (H11 endpoint). Must be mapped to PrestaShop categories during setup.
- **Attribute requirements**: Carrefour may require specific custom attributes per category (inspect via PM11). Varies.

Open questions (need real credentials or seller-side access to verify):
- [x] Exact prod API URL — **confirmed** `carrefoures-prod.mirakl.net/api`.
- [x] Exact sandbox/preprod API URL — **confirmed** `carrefoures-preprod.mirakl.net/api`.
- [ ] Rate limit numbers (watch `X-RateLimit-*` headers if present — requires a valid API key to probe).
- [ ] Webhooks available in Carrefour Hub seller backoffice? (Mirakl core supports them; Carrefour may or may not enable the UI.)
- [ ] Are there custom attributes Carrefour mandates across all categories? (Inspect PM11 once we have credentials.)
- [ ] Does Carrefour Hub expose `X-Mirakl-*` response headers we can rely on?

Strategy for unknowns: use Google + context7. When we lack primary source, mark assumptions explicitly in code comments.

## Decisions we propose for the module

(Still to confirm with Rafa before coding — each is a design choice.)

1. **HTTP client**: custom thin client (Option B above). SDK optional, documented separately.
2. **MVP catalog flow**: **OF24** (JSON offer upsert) for up to ~500 SKUs per import. Fallback to **OF01** (CSV import) when user uploads bigger batches.
3. **Stock sync**: hook `actionUpdateQuantity` → enqueue `STO01` update job → worker flushes every 30s (debounced). Real-time-ish without hammering the API.
4. **Order sync**: OR11 incremental polling cron every 15 min + optional HTTP webhook if Carrefour supports it. Poll is always-on as backup.
5. **P11 product creation flow**: **out of v1.0.0 scope**. Assume the product already exists in Carrefour catalog and we only post offers.
6. **Returns (RT*)**: out of v1.0.0 scope.
7. **Messages (M*)**: out of v1.0.0 scope.

## When to update this doc

- When Rafa provides the real Carrefour ES endpoint URL → write it in.
- When we discover Mirakl changed an endpoint or response shape.
- When we use a new endpoint not listed here.
- Every major Mirakl API version bump.
