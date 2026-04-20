# FAQ

## Is this really free?

Yes. The module is licensed under **AGPL-3.0** — free to use, modify, redistribute as long as you follow the licence's copyleft terms (which mostly apply if you run it as a network service and distribute modifications).

For businesses that can't comply with AGPL (e.g. SaaS resellers wanting to keep modifications private), commercial licensing is available — contact the maintainer.

## Why AGPL and not MIT?

AGPL protects the community. If someone takes this code, modifies it, and runs it as a hosted service, AGPL requires them to share those modifications back. MIT wouldn't. For a marketplace connector where someone could turn it into a SaaS and compete with the community version, AGPL is the right choice.

For end-users installing the module in their own shop: zero practical difference. You can use, modify, and resell as part of a PrestaShop service. You only need to publish changes if you turn it into a shared network service.

## How does it compare to Shoppingfeed / Lengow / Iziflux?

Honest comparison:

| | This module | Shoppingfeed / Lengow / Iziflux |
|---|---|---|
| **Cost** | Free (service/support paid) | €99–600+/month |
| **Carrefour support** | ✅ (primary target) | ✅ (alongside 50-100 other channels) |
| **Multi-marketplace** | Not today, planned v1.2 | ✅ |
| **Code visibility** | ✅ full source on GitHub | Closed SaaS |
| **Self-hosted** | ✅ you own your data | SaaS only |
| **Multishop PS** | ✅ per-shop credentials | Varies |
| **Sandbox support** | ✅ | ✅ |
| **UI polish** | Honest (functional, not pretty) | Polished |
| **Onboarding** | DIY or paid setup | Included |

Pick the SaaS if you need many channels and want no-ops. Pick this if you want control, transparency, and no monthly fee.

## Can I use this for other Mirakl marketplaces (Leroy Merlin, FNAC, Decathlon)?

The core API client is marketplace-agnostic — Mirakl is Mirakl. Changing the endpoint URL and picking the right shop_id may be enough for simple use. Category codes, attributes and some operator-specific quirks will differ.

v1.2 of the module (planned Q4 2026) aims to make multi-marketplace explicit: several simultaneous profiles per shop. Until then you can install the module and swap endpoints per environment, but you can't connect to Carrefour and Leroy Merlin at the same time.

If you need this sooner, paid custom development is the path.

## Does this work with PrestaShop 1.6?

Yes, it's designed to. Compatibility range is 1.6.0 → 9.x. The older the PrestaShop, the more rough edges; we test primarily on 1.7.8 and 8.x.

## Does it work with PrestaShop 9?

Yes, compat is declared up to 9.99.99. We test on 8.x primarily; 9.0+ changes are monitored. Report incompatibilities as issues.

## Does it work on shared hosting?

Yes, as long as:
- PHP 7.1+ is available.
- `curl` and `openssl` extensions enabled.
- You can set up a cron job (most shared hosts support this via their control panel).

Some shared hosts block outbound HTTPS to random domains. Verify with `curl -I https://carrefoures-prod.mirakl.net/api/account` that you get an HTTP 401 — if not, contact the host to whitelist `*.mirakl.net`.

## My seller account at Carrefour Hub was just approved. Do I need this module?

Carrefour provides **Mirakl Connect**, a free tool where you can upload products via CSV. If your catalog is small (few hundred SKUs), updates are rare, and you don't mind the manual work, that's enough — you don't need this module.

This module pays off when:
- You have 500+ SKUs.
- Stock fluctuates daily.
- You want order emails to flow back into PS with the customer data already present.
- You want to scale beyond Carrefour (preparing for v1.2 multi-marketplace).

## Will it slow down my shop?

No. The module:
- Loads CSS/JS only on its own admin screens.
- Doesn't touch the front office (no shop pages added, no scripts injected).
- All API traffic happens in cron jobs, not in user requests.
- The only runtime hook that fires during normal operation is `actionUpdateQuantity` (stock change), which does a single SELECT and optionally a single INSERT. Microseconds.

## What happens if the Mirakl API is down?

Failed API calls retry with exponential backoff (max 5 attempts, up to 1 hour between retries). After exhausting retries, jobs land in `failed` status and show on the Jobs screen. You can retry them manually later once Mirakl is back.

Meanwhile, your PS shop keeps working normally. Nothing in the front office touches Mirakl.

## What data is sent to Mirakl?

Per offer/upsert:
- SKU, EAN/reference, price, stock, category code, state (new/used), optional description and leadtime.

Per order (you receive, not send): Mirakl sends customer name, email, shipping + billing address, line items. This is mirrored to `carrefour_order` and, optionally, copied to a PS guest customer + order.

Per stock update: SKU + quantity only.

We don't send customer data in the outbound direction — orders are only received, never pushed.

## Is customer data from Mirakl-sourced orders shared with anyone?

No. The module only stores it in your own PS database (customer, address, order tables). Nothing is sent to any third party. Standard GDPR rules apply: the shop is the data controller for the imported order data.

## How do I upgrade to a newer version?

1. Back up your database (best practice).
2. Download the new ZIP from the Releases page.
3. Admin → Module Manager → Upload → pick the new ZIP. PS detects it's an update and runs the upgrade script.
4. Verify everything still works (hit a few screens, check the Jobs queue).

Upgrade scripts live in `modules/carrefourmarketplace/upgrade/`. They handle schema migrations.

## How can I contribute?

See [CONTRIBUTING.md](../CONTRIBUTING.md). Code, docs, translations, real-world reports of issues — all welcome.

## Where can I get paid support?

Contact the maintainer (see README). Typical services:
- Setup and onboarding (installation, category mapping, first sync).
- Custom features sponsored by a specific seller or agency.
- Monthly support contracts with guaranteed response times.
- Migration from the legacy Activesoft 2018 module to this one.
