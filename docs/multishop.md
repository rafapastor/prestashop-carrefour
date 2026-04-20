# Multi-shop

PrestaShop lets you run many shops from one install. This module is designed for that — each shop has its own Carrefour credentials, its own listings, its own job queue, its own logs. Nothing bleeds across shops.

## How it works

Every data table in this module carries an `id_shop` column. Every query filters by the current shop. Every cron job iterates over all configured shops. The main module file registers the same set of tabs for each shop-group.

Concretely:

- Each shop has **one** `carrefour_shop_config` row. Settings (API key, endpoint, flags) are per-shop.
- `carrefour_listing`, `carrefour_offer`, `carrefour_order`, `carrefour_category_mapping`, `carrefour_log`, `carrefour_job` all key on `id_shop`.
- The admin screens filter the grid by the shop you've selected in the top-right shop selector.

## Typical topologies

### One PrestaShop → one Carrefour seller account
Simplest case. Single shop, single `carrefour_shop_config` row. Nothing special.

### One PrestaShop → multiple Carrefour seller accounts (multi-shop PS)
You run two or more shops from one PrestaShop install (for instance EN and FR fronts, same backend). Each is a separate Carrefour seller account because Carrefour Spain is single-country.

- Configure each shop separately: top shop selector → pick a shop → open *Configuration* → enter that shop's API key.
- `shop_id_mirakl` differs per shop.
- Each shop can have its own `stock_sync_debounce_seconds`, `order_sync_interval_minutes`, etc.
- Listings, offers, orders are fully isolated between shops.

### Multiple PrestaShop installs → one Carrefour seller account
Unusual. If you need this (one Carrefour account but two separate PS databases), install the module on each PS and use the same API key. Be careful with SKU collisions — Mirakl treats `offer_reference` (SKU) as your shop's global key. If both PS installs push the same SKU, the second write overrides the first.

## Switching shops in the admin

At the top of the PS admin, there's a dropdown showing the current shop context. It has three levels:

- **All shops** — multi-shop mode. Most of our screens show a warning here and ask you to pick a specific shop. We do this because Carrefour settings are per-shop; there's no "all shops" API key.
- **Shop group** — same warning.
- **Shop** — this is the target context. All the module's screens (Config, Listings, Offers, Orders, Category mapping, Jobs, Logs) operate on that shop's data.

If you see the warning "Select a specific shop…", switch the selector from "All shops" to a concrete shop and the form reappears.

## Multi-shop specifics

### Installing the module
Install happens once at the PS level. Tabs are created for all shops automatically via `Shop::setContext(Shop::CONTEXT_ALL)` during install.

### Assigning the module to shops
Some PrestaShop screens ask you which shops the module is "active" in. This module is designed to be active on all shops where you want Carrefour sync. Leaving it active on a shop you don't want to sync is harmless as long as no `carrefour_shop_config` row exists for that shop — no API calls will be made.

### Cron workers
The worker script (`cron/worker.php`) iterates over **all** shops with a config row. You don't need multiple cron entries per shop. One cron = all shops.

If a specific shop's API is down, its jobs fail and get retried according to the queue's backoff. Other shops keep flowing.

### Categories mapping
PS categories themselves can be shared across shops (depending on your multi-shop config). The mapping table stores `(id_shop, id_category_ps) → mirakl_code`, so the same PS category can map to different Mirakl codes per shop. Unlikely in practice but supported.

## Known gotchas

- **New shop added after initial setup**: you need to re-visit the module's Configuration screen for the new shop and enter its credentials. The module doesn't auto-populate new shops.
- **Default shop vs explicit shop**: PS admin may default to "All shops" on first login. Our config form enforces selecting a single shop before showing the editable form.
- **`_COOKIE_KEY_` is shared across shops in the same PS install**: encrypted API keys can be decrypted by any shop in the same install (there's only one key). This is fine for our threat model (someone with DB access already has `config/settings.inc.php`), but note it.
- **Shop groups with shared `carrefour_shop_config`**: not supported. Each shop is independent.

## Testing multi-shop locally

PrestaShop's multi-shop feature can be enabled from *Advanced Parameters → Multistore*. With our Docker setup you can add a second shop after install, configure it separately, and verify the isolation by:

1. Set different `shop_id_mirakl` values per shop.
2. Create a Listing in shop 1 and another in shop 2 with the same name.
3. Verify each only appears in its own shop's Listings grid.
4. Check in the DB: `SELECT id_shop, name FROM ps_carrefour_listing;` — each row has the correct `id_shop`.
