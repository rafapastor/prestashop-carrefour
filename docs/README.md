# Documentation

User-facing documentation. Markdown sources live here; we don't ship HTML versions inside the module (unlike Addons modules).

## Structure

- [`installation.md`](installation.md) — install the module in a PrestaShop store, set up the cron workers
- [`configuration.md`](configuration.md) — every config field explained, what's safe to change vs careful
- [`multishop.md`](multishop.md) — per-shop configuration story, topologies, known gotchas
- [`troubleshooting.md`](troubleshooting.md) — common errors and fixes (test connection, jobs piling up, orders not syncing…)
- [`faq.md`](faq.md) — licence, comparison with Shoppingfeed/Lengow, upgrades, GDPR, contributing
- [`mirakl-api-reference.md`](mirakl-api-reference.md) — Mirakl endpoints used by the module and why

## Translations

Written in English first. Spanish (`docs/es/`) translations added when the module is released. Other languages: contributions welcome.

## Format

Plain Markdown, readable on GitHub. No static site generator for now — if the project grows to need one, we'll consider MkDocs or Docusaurus.
