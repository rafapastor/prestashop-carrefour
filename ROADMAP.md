# Roadmap

Public, product-focused roadmap. Dates are targets, not commitments. Feature scope may shift based on community feedback and real-world Mirakl API behaviour.

## v1.0.0 — MVP (target June 2026)

Core of the connector. What it takes to go from "nothing" to "production-ready for a small seller".

- [ ] Mirakl API client with auth, retry, and error normalisation
- [ ] Configuration screen (API key, endpoint, sandbox toggle, per-shop credentials)
- [ ] Catalog upload: offers CSV/JSON generation and push to Mirakl
- [ ] Catalog upload: products (P11 flow) for sellers that need to create the product
- [ ] Real-time stock sync: hook `actionUpdateQuantity` → push to Mirakl
- [ ] Price sync: manual and scheduled
- [ ] Order import: pull Mirakl orders, create PrestaShop orders, map customer and address
- [ ] Order status sync: shipped / cancelled / refunded back to Mirakl
- [ ] Multi-shop: credentials scoped per shop, isolated data
- [ ] Async job queue with worker cron
- [ ] Error dashboard with retry action per row
- [ ] Structured logging to `var/logs/`
- [ ] Docs: installation, configuration, multi-shop, troubleshooting, FAQ
- [ ] PHPUnit tests for core logic
- [ ] Compatible with PrestaShop 1.6 → 9.x

## v1.1 — Hardening (target Q3 2026)

- [ ] Mirakl webhooks support (OR-related events) to replace polling where possible
- [ ] Bulk operations UI (select N offers → action)
- [ ] Category mapping UI improvements (drag-drop, search)
- [ ] Better attribute mapping (colour, size, etc.) for variations
- [ ] Import of Mirakl messages into PrestaShop order notes
- [ ] Email notifications for failed jobs
- [ ] Per-shop theming of the admin tab (small QoL)

## v1.2 — Multi-Mirakl (target Q4 2026)

Generalise the connector so adding a second Mirakl marketplace is a matter of configuration, not a fork.

- [ ] Abstract `MiraklClient` to allow multiple simultaneous marketplaces
- [ ] Marketplace profiles (endpoint, credentials, category mapping) switchable
- [ ] Pilot with one additional marketplace (Leroy Merlin or FNAC) as a separate compatible module
- [ ] Shared core module + marketplace-specific thin wrappers

## v2.0 — Large catalogs (target 2027)

For sellers with 10k+ SKUs and high update frequency.

- [ ] Differential sync (only changed items since last sync)
- [ ] Parallel workers for job queue
- [ ] Dashboard analytics: sync health, SLA, stock divergences
- [ ] AI-assisted category / attribute mapping (optional, off by default)
- [ ] Public REST endpoints for external integrations

## Unscoped / ideas backlog

Things that could make sense later, in no particular order:

- Return (RMA) management UI
- Loyalty / voucher sync
- Carrefour Hub-specific features (Intégration CARY, offer freshness, etc.)
- Import of Mirakl seller reviews into PrestaShop

## How to influence the roadmap

- Open a feature request issue with your use case — concrete use cases move features up the list.
- Contribute a PR — the fastest way to get something in.
- Sponsor a feature — custom development is available; see README for contact.
