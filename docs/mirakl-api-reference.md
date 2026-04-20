# Mirakl endpoints used by this module

Reference for developers who want to understand or extend the module. The full Mirakl developer portal is at [developer.mirakl.com](https://developer.mirakl.com/).

All endpoints use `Authorization: <api_key>` header (no `Bearer` prefix) and base URL `https://carrefoures-prod.mirakl.net/api` (or `carrefoures-preprod.mirakl.net` for sandbox).

## Authentication / account

| Code | Method | Path | Used for |
|---|---|---|---|
| `A01` | `GET` | `/account` | *Test connection* button, returns current shop info |

## Catalog (offers)

| Code | Method | Path | Used for |
|---|---|---|---|
| `OF24` | `POST` | `/offers?shop={id}` | Create/update/delete offers (JSON). Async: returns `import_id`. |
| — | `GET` | `/offers/imports/{id}` | Poll import status |
| — | `GET` | `/offers/imports/{id}/error_report` | Download CSV of failed rows |
| `STO01` | `POST` | `/offers/stocks?shop={id}` | Lightweight stock-only update |

Planned for later phases (not yet called in the code):

| Code | Method | Path | Future use |
|---|---|---|---|
| `OF01` | `POST` | `/offers/imports` | CSV bulk import for 5000+ SKUs |
| `PRI01` | `POST` | `/offers/pricing/imports` | Dedicated price-only batch |

## Catalog (products)

Not called in v1.0.0. Assumes products already exist in Carrefour's catalog (matched by EAN).

Planned for v1.1+:

| Code | Method | Path | Future use |
|---|---|---|---|
| `P11` | `GET` | `/products/{sku}/offers` | Discover offers for a Carrefour product |
| `P41` | `POST` | `/products/imports` | Submit new product for Carrefour catalog (P11 flow) |

## Hierarchies (categories)

| Code | Method | Path | Used for |
|---|---|---|---|
| `H11` | `GET` | `/hierarchies` | Fetch Carrefour category tree for mapping UI |

## Orders

| Code | Method | Path | Used for |
|---|---|---|---|
| `OR11` | `GET` | `/orders` | List orders with pagination, filterable by `start_update_date`, `order_state_codes`, `shop_ids` |
| `OR21` | `PUT` | `/orders/{order_id}/accept?shop_id={id}` | Accept/refuse order lines |
| `OR23` | `PUT` | `/orders/{order_id}/tracking?shop_id={id}` | Update tracking number + carrier |
| `OR24` | `PUT` | `/orders/{order_id}/ship?shop_id={id}` | Confirm shipment |

Planned for later phases:

| Code | Method | Path | Future use |
|---|---|---|---|
| `OR12` | `GET` | `/orders/{id}` | Single-order detail |
| `OR28` | `POST` | `/orders/{id}/refund` | Trigger refund |
| `OR72` | `GET` | `/orders/{id}/documents` | Invoice/label documents |

## Messages, returns, invoicing

Not implemented in v1.0.0. Planned for v1.1 and later.

## Response conventions

All successful responses are JSON. Error responses typically look like:

```json
{
  "message": "Human-readable summary",
  "errors": [
    { "error_code": "OF-23", "message": "specifics", "field": "price" }
  ]
}
```

The `MiraklClient` exception classes carry both the HTTP status code and the Mirakl-specific `error_code` when available (see `MiraklException::getErrorCode()`).

## Pagination

`GET /orders` and other listing endpoints use `offset` + `max` (default 50, max 100) query params. `OrderService::pullRecentOrders()` iterates until a page returns fewer than `max` rows.

## Error report CSV format

Offer imports that partially fail produce a CSV like:

```csv
shop_sku;product_id;error_code;error_message
ABC-01;EAN-123;OF-23;Price invalid
```

Parsed by `MiraklErrorReport::parse()`. Delimiter is usually `;` but we auto-detect `,` as a fallback.

## Rate limits

Not documented publicly per endpoint. Observed in practice: ~5-10 req/s per seller. The client handles `HTTP 429` with exponential backoff (default max 5 attempts, starting at 2s).

## Where this is implemented

- HTTP client: `classes/Api/MiraklClient.php`
- Exceptions: `classes/Api/MiraklException.php`
- CSV parser: `classes/Api/MiraklErrorReport.php`
- Endpoint-specific logic: `classes/Service/*.php` and `classes/Queue/Jobs/*.php`
- Memory-bank research notes: `memory-bank/miraklApiResearch.md`

## Upgrading when Mirakl changes

Mirakl announces API changes 3-6 months in advance. When they do:

1. Update `memory-bank/miraklApiResearch.md` with the new facts.
2. Update the relevant `Service` or `Job` class.
3. Bump the module version (at least a minor).
4. Note the required Mirakl API version in `README.md` and `CHANGELOG.md`.

If you're running on your own version of Mirakl and endpoints differ from this doc, please open an issue with the details — it helps the whole community.
