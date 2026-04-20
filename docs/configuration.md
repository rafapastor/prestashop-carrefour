# Configuration

Every setting lives on the **Carrefour Marketplace → Configuration** screen. All settings are **per shop** — switch shops from the top selector in PS admin to edit each one independently.

## API connection

### API endpoint
The Mirakl base URL for your marketplace. Two values you'll likely use:

| Environment | URL |
|---|---|
| Production | `https://carrefoures-prod.mirakl.net/api` |
| Sandbox / Preprod | `https://carrefoures-preprod.mirakl.net/api` |

Both subdomains exist. Your credentials are separate per environment.

### Sandbox mode
Informational toggle. Currently it doesn't auto-switch the endpoint (you set the URL manually), but it's stored for the Logs/Jobs screens to mark which environment a call ran against. Useful when you eventually want to audit what ran in preprod vs prod.

### API key
Generate this in the Mirakl seller backoffice:

1. Log in to your Carrefour Hub seller account.
2. *Top-right menu → My user settings → API key*.
3. Click *Generate* (or *Reset* if one exists).
4. Copy the token.

Paste into the field and save. The key is **encrypted at rest** using AES-256-CBC with your PrestaShop's `_COOKIE_KEY_` as the key derivation source. It's never echoed back to the form; the field always shows empty.

To change the key later, just type the new value and save. Leaving the field empty keeps the existing saved key.

### Mirakl shop ID
Your numeric `shop_id` inside the Carrefour marketplace. Shown in the Mirakl backoffice dashboard. Required for endpoints that accept a `shop_id` query parameter (offer upserts, stock updates).

## Orders

### Auto-accept incoming orders
- **Off** (default): you manually accept or refuse order lines from the **Orders** screen.
- **On**: any `WAITING_ACCEPTANCE` order pulled from Mirakl will be auto-accepted via OR21 during the next sync cycle.

Use "On" only if your catalogue is stable and you trust orders. Carrefour imposes a shipping deadline from the moment an order is accepted; auto-accepting incurs that clock immediately.

### Default PrestaShop order state
When a Mirakl order is mirrored as a PrestaShop order, which OrderState to assign. Default: "first unpaid state at order time" (PS `PS_OS_PAYMENT` or equivalent). You can force a specific state here (e.g. "Processing in progress").

## Sync

### Stock sync enabled
When on, any PS stock change for a product that's tracked as a Carrefour offer is pushed to Mirakl (STO01 endpoint). Uses a debouncing mechanism to coalesce rapid changes.

### Stock sync debounce (seconds)
How long to wait after a stock change before pushing to Mirakl. Default 30.

- Lower (5-10s) = more real-time but more API calls.
- Higher (60-120s) = less chatty but stock on Mirakl lags behind PS briefly.

### Price sync enabled
When on, price changes can be pushed (manually for now; automatic price-watching is a v1.1+ feature).

### Order sync interval (minutes)
How often the `order_sync` cron job pulls new or updated orders from Mirakl. Default 15 minutes. Shorter = more responsive, longer = fewer API calls.

If you configure Mirakl webhooks pointed at your PS webhook URL, the sync triggers near-instantly on order events and the interval becomes a safety net.

## Catalog

### SKU strategy
Which PS field to use as the Mirakl `shop_sku` on uploaded offers:

- **Attribute reference, fallback to product reference** (default): best for stores with variants. Each combination's own reference is used; if empty, falls back to the product reference.
- **Product reference only**: simpler but means all variants of a product share the same SKU. Use only if you don't have variants or you manage variants outside PS.
- **Product EAN13**: uses the EAN barcode as SKU. Risky because EAN isn't unique per shop and Mirakl may reject duplicates across sellers.

Once you pick a strategy, keep it. Changing later renames SKUs and Mirakl may see them as brand-new offers (creating duplicates and orphaning the old ones).

## Logging

### Log level
- `debug`: everything, very verbose, use when investigating a bug.
- `info` (default): all important events, normal operation.
- `warn`: only warnings and errors.
- `error`: only errors.

Higher levels write less to DB/disk.

### Log retention (days)
How many days to keep `carrefour_log` rows. Default 30. The `cron/logs-cleanup.php` script trims older rows.

## Webhooks (optional, advanced)

When enabled, a URL like this becomes available:

```
https://yourshop.com/module/carrefourmarketplace/webhook?secret=<random>&shop=<id>
```

The `secret` is auto-generated per shop. Point Mirakl's webhook configuration at this URL and it'll trigger order syncs near-instantly instead of waiting for the cron interval.

Note: the current Mirakl webhook support varies by operator. Carrefour may or may not expose webhooks in the seller backoffice. If not, the periodic cron is your primary path — and it works fine.

## Multi-shop

See [multishop.md](multishop.md) for the per-shop configuration story.

## What you can change safely vs carefully

| Setting | Change freely | Careful |
|---|---|---|
| Log level, log retention | ✅ | |
| Stock/price/order sync enabled | ✅ | |
| Debounce and interval | ✅ | |
| Default order state | ✅ | |
| API endpoint | | ⚠ switch env → new key |
| API key | | ⚠ invalidates pending jobs if wrong |
| Mirakl shop ID | | ⚠ wrong ID = requests go to the wrong shop |
| SKU strategy | | ⚠ renames every offer on next sync |
