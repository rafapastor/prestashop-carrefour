# Installation

This module connects a PrestaShop store to Carrefour Marketplace Spain (Mirakl). It's distributed as a ZIP you install from the PrestaShop admin.

## Requirements

- **PrestaShop** 1.6.0 → 9.x (tested up to 8.2).
- **PHP** 7.1 or newer (7.4 / 8.x recommended).
- **MySQL** 5.7+ or **MariaDB** 10.2+.
- **PHP extensions**: `curl`, `openssl`, `json`, `mbstring`.
- A Mirakl seller account with API access on Carrefour Hub (Spain).
- Ability to run **system cron** on your hosting (recommended, though the PrestaShop cron module also works).

## Step 1 — Download

Go to [Releases](../../releases) and download `carrefourmarketplace-X.Y.Z.zip` for the latest version.

Alternatively, clone the repo and package the module yourself:

```bash
git clone https://github.com/rafapastor/prestashop-carrefour.git
cd prestashop-carrefour
make package
# produces dist/carrefourmarketplace-X.Y.Z.zip
```

## Step 2 — Install via PrestaShop admin

1. Log into your PrestaShop admin.
2. Go to **Modules → Module Manager**.
3. Click **Upload a module** (top right).
4. Drop the ZIP.
5. Once uploaded, click **Install**.
6. You should see a new menu entry **Carrefour Marketplace** in the left sidebar with sub-entries: Configuration, Listings, Offers, Orders, Category mapping, Jobs, Logs.

If the install fails, check:
- Folder `modules/` is writable by the web server.
- `MySQL` can create tables (`CREATE TABLE` privilege).
- `openssl` extension is present (needed for API-key encryption).

## Step 3 — Schedule the job worker

The module queues all API-bound work into an internal job table so it doesn't block your admin. A worker needs to run periodically.

### Option A — system cron (recommended)

Edit the crontab on your server:

```bash
crontab -e
```

Add:

```cron
* * * * * php /path/to/your/prestashop/modules/carrefourmarketplace/cron/worker.php --max-jobs=50 --max-seconds=55 > /dev/null 2>&1
*/15 * * * * php /path/to/your/prestashop/modules/carrefourmarketplace/cron/orders-sync.php > /dev/null 2>&1
0 3 * * * php /path/to/your/prestashop/modules/carrefourmarketplace/cron/logs-cleanup.php > /dev/null 2>&1
```

Explanation:
- **worker.php** every minute: drains the job queue (offer upserts, stock pushes, etc.).
- **orders-sync.php** every 15 min: enqueues an `order_sync` job per configured shop (redundant safety net; webhooks handle it faster if enabled).
- **logs-cleanup.php** daily at 03:00: trims old log rows per your `log_retention_days` setting.

### Option B — PrestaShop cron module

Install the official [`cronjobs`](https://addons.prestashop.com/en/administrative-tools/25823-cron-tasks-manager.html) module. Then add the three URLs above (pointing to `modules/carrefourmarketplace/cron/*.php`) as scheduled tasks.

Note: PS cron module is convenient but depends on your shop receiving web traffic. System cron is more reliable.

## Step 4 — Configure the module

Open **Carrefour Marketplace → Configuration** and fill the form. See [configuration.md](configuration.md) for what each field means.

Minimum viable config:
- **API endpoint**: `https://carrefoures-prod.mirakl.net/api` (production) or `https://carrefoures-preprod.mirakl.net/api` (sandbox).
- **API key**: from your Mirakl seller backoffice (*My user settings → API key*).
- **Mirakl shop ID**: from the same backoffice (shop_id field).
- Leave the rest as defaults for a first test.

Then click **Save** and **Test connection**. A green confirmation with your shop_id means you're connected.

## Step 5 — Map categories

Before uploading any offer, map your PrestaShop categories to Carrefour's hierarchy.

1. Go to **Carrefour Marketplace → Category mapping**.
2. Click **Refresh Mirakl hierarchy** — this fetches Carrefour's category tree.
3. Pick a PS category, paste the matching Mirakl `code` (from the reference panel below), optionally a label.
4. Save mappings.

See [troubleshooting.md](troubleshooting.md) if the refresh fails.

## Step 6 — First listing

1. Go to **Carrefour Marketplace → Listings** → *Add new*.
2. Pick a name, choose price/stock modes, save.
3. Go to **Offers**, select your new listing, paste a few PrestaShop product IDs, click **Add to listing**.
4. Click **Dispatch listing to Mirakl**. A job enters the queue.
5. Wait a minute (cron worker cycle) then check **Jobs** to see it processed.

If something fails, see [troubleshooting.md](troubleshooting.md) or check the **Logs** tab.

## Uninstalling

Admin → Module Manager → find the module → Uninstall.

Uninstall drops the `carrefour_*` tables and removes admin tabs. Your PrestaShop orders that were imported from Carrefour stay — they're regular PS orders. Only the Mirakl mirror data is gone.
