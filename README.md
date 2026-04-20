# Carrefour Marketplace Connector for PrestaShop

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](LICENSE)
[![PrestaShop](https://img.shields.io/badge/PrestaShop-1.6%20%E2%86%92%209.x-pink.svg)](https://www.prestashop.com)
[![PHP](https://img.shields.io/badge/PHP-7.1%2B-777bb4.svg)](https://php.net)
[![Tests](https://img.shields.io/badge/tests-45%20passing-success.svg)](tests/)

Connect your PrestaShop store to **Carrefour Hub Spain** (Mirakl) without paying a monthly SaaS fee. Push your catalog, sync stock in real time, pull orders back into PrestaShop. Free, open source, maintained.

> **Status**: Work in progress. First stable release (v1.0.0) targeted for **June 2026**.

👉 [Léelo en español](README.es.md)

## Why

- Shoppingfeed, Lengow, Iziflux and friends charge €100–600/month just to operate.
- The previous community module (Activesoft 2018) was abandoned and never supported modern PrestaShop versions.
- Sellers with PrestaShop stores who want to list on Carrefour Hub had no free, maintained alternative.

This module fills that gap.

## Is this right for you?

|  | This module | Shoppingfeed / Lengow / Iziflux | Mirakl Connect (CSV) |
|---|---|---|---|
| **Cost** | Free (+ paid services optional) | €100–600/month | Free |
| **Setup time** | 30-60 min (or paid onboarding) | Provided | Manual every upload |
| **Catalog size** | Fits any size | Fits any size | Best <1000 SKU |
| **Real-time stock** | ✅ | ✅ | ❌ (manual CSV) |
| **Multi-marketplace** | Carrefour only (v1.2 multi) | 50+ channels | Any Mirakl marketplace |
| **Self-hosted / code visible** | ✅ AGPL | Closed SaaS | N/A |
| **Multi-shop PS** | ✅ per-shop credentials | Varies | N/A |

Good fit if: you sell on Carrefour, have a mid-size catalog, run PrestaShop, and prefer paying once for setup over paying monthly to a SaaS.

Not a good fit if: you want a single tool covering 20 marketplaces out of the box — go SaaS.

## Features (v1.0.0 scope)

- **Catalog upload** to Carrefour Hub via Mirakl API (offers + products).
- **Real-time stock sync**: hook into PrestaShop stock updates, push to Mirakl immediately.
- **Order import**: Carrefour orders pulled into PrestaShop as native orders.
- **Multi-shop support**: each PrestaShop shop can connect to its own Carrefour account, credentials scoped per shop.
- **Error dashboard** with retry buttons: no more silent failures.
- **Async job queue**: bulk operations don't block your admin.
- **Structured logging** to `var/logs/` and PrestaShop logger.
- **Sandbox / Production** toggle for safe testing.
- **Compatible with PrestaShop 1.6 → 9.x**.

See [ROADMAP.md](ROADMAP.md) for what comes next.

## Quickstart

### For merchants (using the module)

1. Download the latest release ZIP from [Releases](../../releases).
2. In PrestaShop admin: **Modules → Module Manager → Upload a module** → select the ZIP.
3. Install, then open **Sell on Carrefour → Configuration**.
4. Paste your Mirakl API key and endpoint (get them from your Carrefour Hub seller account).
5. Test the connection and start uploading offers.

Detailed guides in [`docs/`](docs/).

### For developers (contributing or running locally)

```bash
git clone https://github.com/rafapastor/prestashop-carrefour.git
cd prestashop-carrefour
make dev          # spins up PrestaShop + MySQL via Docker
# PrestaShop at http://localhost:8081
# Admin:   admin@prestashop.com / prestashop_demo
make test         # run PHPUnit
make lint         # check coding standards
make format       # apply PS coding standards
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full contributor guide.

## Documentation

- [Installation](docs/installation.md) — install, schedule cron workers, first config
- [Configuration](docs/configuration.md) — every setting explained
- [Multi-shop setup](docs/multishop.md) — how per-shop credentials work
- [Troubleshooting](docs/troubleshooting.md) — common errors and fixes
- [FAQ](docs/faq.md) — licence, comparison, GDPR, upgrades
- [Mirakl API reference](docs/mirakl-api-reference.md) — endpoints used by the module

## Community and support

- 🐛 **Bug reports**: [open an issue](../../issues/new?template=bug_report.yml)
- 💡 **Feature requests**: [open an issue](../../issues/new?template=feature_request.yml)
- 💬 **Questions**: [GitHub Discussions](../../discussions)
- 📬 **Professional support, setup & custom development**: [carrefour@smart-shop-ai.com](mailto:carrefour@smart-shop-ai.com) or [book a 30-min intro call](https://calendly.com/rafapas22/30min)

Community support is best-effort. If you need guaranteed response times, custom integration or turnkey onboarding, paid services are available.

## License

This project is licensed under the **GNU AGPL v3.0** — see [LICENSE](LICENSE).

AGPL protects the community: if you modify the module and run it as a network service, you must share your changes back. For commercial use cases where AGPL is not workable, contact the maintainer to discuss a commercial licence.

## Acknowledgements

Based on the abandoned 2018 Activesoft Carrefour module (full rewrite for modern PrestaShop versions).
