# Troubleshooting

Common issues and how to fix them, roughly ordered by how often they come up.

## "Test connection" fails

### `Authentication failed — check your API key. (HTTP 401)`
Your API key is wrong, revoked, or for a different environment.

- Double-check you copied it from the correct backoffice (prod vs preprod).
- Generate a fresh key in Mirakl and paste it again.
- Make sure you're not mixing a preprod key with the prod endpoint or vice versa.

### `Endpoint not found — check the API URL. (HTTP 404)`
The endpoint URL is typo'd or the path doesn't exist for Carrefour.

- For production, use exactly `https://carrefoures-prod.mirakl.net/api` (no trailing slash).
- For sandbox: `https://carrefoures-preprod.mirakl.net/api`.

### `Network error: cURL error …`
DNS or firewall issue.

- From the shop host, run `curl -I https://carrefoures-prod.mirakl.net/api/account` — you should get an HTTP 401 (endpoint reachable, just not authenticated). If you get connection refused or timeout, your hosting is blocking outbound HTTPS to that domain.
- Ask your hosting provider to whitelist `*.mirakl.net` outbound if they have an egress firewall.

### `Unexpected error: _COOKIE_KEY_ is not defined`
Your PrestaShop `config/settings.inc.php` (or `config/parameters.php` on 1.7+) is missing the `_COOKIE_KEY_` constant. This shouldn't happen on a normal install; if it does, your PS install is corrupted.

## Jobs pile up without processing

### Symptom
The **Jobs** screen shows many rows with status `pending` that never move to `running` or `completed`.

### Cause
The cron worker isn't running.

### Fix
1. Verify the cron entry — see [installation.md](installation.md) → *Schedule the job worker*.
2. Test it manually from the shop host:
   ```bash
   php /path/to/prestashop/modules/carrefourmarketplace/cron/worker.php --max-jobs=5 --max-seconds=20 --verbose
   ```
   You should see `[carrefour-worker] starting … done — processed=N`. If it errors out, fix what it says.
3. Check the `carrefour_log` table for worker errors:
   ```sql
   SELECT date_add, level, channel, message FROM ps_carrefour_log
     WHERE channel IN ('job','api') ORDER BY id_carrefour_log DESC LIMIT 50;
   ```

## Offer upload fails with Mirakl errors

### `OF-23: Bad SKU` / `OF-01: Missing EAN` etc.
The **Jobs** row for that upload shows `status=failed` and `last_error_code`/`last_error_message`. Click on the **Offers** screen — offers with `status=error` show the same code.

Most common reasons:

- **No EAN13** on your PS products and your chosen SKU strategy requires one.
- **Category not mapped** — offers go out without a `category_code`, Mirakl rejects. Fix: go to *Category mapping* and bind the PS category.
- **Price = 0 or invalid** — Mirakl rejects free offers.
- **Quantity = 0** — fine (offer will show "out of stock") but check the strategy.

Fix the underlying PS product, then go to *Jobs* and click *Retry now* with the job ID.

### No `error_report` available
Some Mirakl imports complete with `has_error_report=false`. That means everything went through. If offers still show `status=syncing` forever, the polling is stuck — delete the job row and re-dispatch the listing.

## Stock doesn't sync

### Symptom
Changing stock in PS admin doesn't push to Carrefour.

### Checklist
1. **Stock sync enabled** in *Configuration* is ON.
2. The product is in a Listing, not just marked as an orphan offer.
3. A job with type `stock_update` appears in *Jobs* within a minute.
4. The cron worker is running (see above).
5. The Mirakl API key has permission on the STO01 endpoint (normally included in seller keys).

### If you see the job completed but Mirakl didn't update
Check the Logs tab for the `stock.push` entry. If the API responded 200 but stock didn't change on Carrefour side:
- The `shop_sku` sent doesn't match an existing offer in Carrefour — create the offer first (via catalog upload).
- The offer is not linked to your shop_id.

## Orders don't come in

### Symptom
You accepted a Mirakl order in the seller backoffice, 30 min later nothing in PS.

### Checklist
1. `carrefour_shop_config.order_sync_interval_minutes` is reasonable (default 15).
2. An `order_sync` job is enqueued and running.
3. `cron/worker.php` is actually running.

### Manual pull
On the *Orders* screen, click **Pull orders now**. This enqueues an immediate sync. If after a minute you still see nothing, check *Logs*:

```sql
SELECT * FROM ps_carrefour_log WHERE channel='orders' ORDER BY id_carrefour_log DESC LIMIT 10;
```

Look for `order.import_failed` entries.

### Mirakl order mirrored but PS order not created
The `carrefour_order` row exists but `id_order` is 0. The PS order creation step failed. Check the log for `order.ps_create_failed` — typical causes:

- No active **Carrier** configured in PS (the order creation needs one).
- The product SKU from Mirakl isn't mapped to any PS product (the cart line creation is skipped for that SKU).
- Currency mismatch (the Mirakl order is in EUR but your PS doesn't have EUR as a currency).
- Country ISO mismatch (we map `ESP` → `ES`; other countries may need additions in `CarrefourOrderService::iso3ToIso2()`).

Fix the underlying PS config and retry the sync.

## Category refresh fails

### `Failed to fetch hierarchy: Authentication failed`
Your API key is bad. See first section.

### Cached tree empty even after "Refresh" succeeded
The `data/shop_<id>/hierarchies.json` file couldn't be written. Check:

- `modules/carrefourmarketplace/data/` is writable by the web server.
- Disk isn't full.

## Webhook returns 403

### `{"ok":false,"error":"webhook_disabled"}`
The shop's `webhook_enabled` flag is off. Turn it on in Configuration and save.

### `{"ok":false,"error":"bad_secret"}`
The URL secret doesn't match `carrefour_shop_config.webhook_secret`. Regenerate the URL from Configuration and update Mirakl's webhook settings.

## After an upgrade, some tabs show a 500 error

Rare, but if your install predates a change in the tabs list and wasn't re-installed, you may have stale Tab rows. Workaround:

1. Uninstall the module.
2. Re-install.

All your data (in `carrefour_*` tables) survives only if you checked *Do not delete files and settings* during uninstall. Otherwise back up the tables first.

## Emergency: disable without uninstalling

Admin → Module Manager → find the module → **Disable**. All hooks stop firing, cron scripts still run but find no active shop config. Re-enable later.

## Last resort: inspect the raw state

```sql
SELECT * FROM ps_carrefour_shop_config;
SELECT status, COUNT(*) FROM ps_carrefour_job GROUP BY status;
SELECT level, channel, COUNT(*) FROM ps_carrefour_log GROUP BY level, channel;
SELECT status, COUNT(*) FROM ps_carrefour_offer GROUP BY status;
```

Attach these in any bug report.
