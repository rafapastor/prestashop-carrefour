# CLAUDE.md — project instructions

## What this is

**Carrefour Marketplace Connector** — a PrestaShop module that connects PS stores to **Carrefour Hub Spain** (Mirakl API). Open-source under **AGPL-3.0**. Compatible with PrestaShop 1.6 → 9.x.

Module name / folder: `carrefourmarketplace` (lowercase, no underscores — PS convention).

## Project type: OSS, not Addons

This is a **truly open-source project** (public repo, community-driven), not a PrestaShop Addons commercial module. That distinction shapes priorities:

- **Primary audience**: developers, agencies and sellers who find the repo on GitHub, will clone/install manually, may contribute PRs.
- **Distribution**: GitHub releases (ZIP + source). No Addons submission.
- **License**: AGPL-3.0 (not OSL-3.0).
- **Priorities**: readable code, good tests, clear docs, helpful CI, contributor-friendly tooling.
- **Non-priorities**: passing Addons validator pre-release checklist, 9-language HTML docs embedded in admin, Addons-specific `module_key`.

The PrestaShop official coding standards and validator rules are still **useful guidelines** — just not release blockers here.

## Business side is private

All marketing, sales, pricing, leads and strategy material lives in `../_private/` (sibling folder, never in this repo). Do **not** reference prices, client names or lead lists in any file under `prestashop-carrefour/`.

The only public mention of services is a generic line in README: *"Professional support, setup & custom development available"*.

## Tech stack

- PHP 7.1+ (PS 1.6 compatibility).
- PrestaShop Module API (Smarty templates, Symfony controllers where needed in 8.x/9.x).
- MySQL 5.7+ / MariaDB 10.3+.
- Guzzle or Tools::file_get_contents for Mirakl HTTP client (decide during API research).
- PHPUnit for tests.
- PHP CS Fixer for formatting (PrestaShop coding standards).
- Docker Compose for local dev environment.

## Code conventions

Follow PrestaShop coding standards:
- PSR-2 base + PrestaShop adjustments (see `.php-cs-fixer.php`).
- Single quotes for strings.
- HTML in Smarty `.tpl` files, **never** embedded in PHP.
- Smarty escaping: `{$var|escape:'htmlall':'UTF-8'}` for HTML, `|escape:'javascript':'UTF-8'` for JS.
- SQL: `pSQL()` for strings, `(int)` for ints, `bqSQL()` for table/column names.
- Always `Validate::isLoadedObject($obj)` after `new Order()`, `new Customer()`, `new Carrier()`, etc.
- Every directory has `index.php` security stub.
- Module root has `.htaccess` restricting direct PHP access.

No forbidden functions: no `serialize`/`unserialize` (use JSON), no `eval`.

## Multi-shop

- Credentials scoped **per shop** via `Configuration::updateValue($key, $value, false, $id_shop_group, $id_shop)`.
- All shop-specific tables carry `id_shop`.
- Install with `Shop::setContext(Shop::CONTEXT_ALL)` for tabs / shared tables.

## Version compatibility

- `ps_versions_compliancy` = `{min: 1.6.0.0, max: 9.99.99}`.
- Use `version_compare(_PS_VERSION_, '...', ...)` for version-specific branches.
- Hybrid translation: `trans()` on PS 1.7.8+, fallback to `l()` on older — pattern already used in the chatgptbot codebase, copy it.

## Development workflow

1. **Plan first**: explain approach, confirm with user before touching code (user's personal rule). Design decisions always get asked.
2. **Work in small, reviewable changes**. Prefer many small PRs over one giant one (once repo is on GitHub and there are external contributors).
3. **Tests for new core logic**. At minimum: Mirakl client, stock sync, order parser.
4. **Update `CHANGELOG.md`** (Keep a Changelog format) with every user-facing change.
5. **Format before commit**: `make format`.
6. **Lint and test in CI**: `make lint && make test` must pass before merging.

## Release workflow

1. Update `CHANGELOG.md` — move `[Unreleased]` entries to a new version section.
2. Bump version in `carrefourmarketplace/carrefourmarketplace.php` and `config.xml`.
3. `make package` → produces installable ZIP in `dist/`.
4. `git tag vX.Y.Z && git push --tags`.
5. GitHub Actions `release.yml` attaches the ZIP to the GitHub release automatically.
6. Announce the release (LinkedIn, blog) — plan lives in `../_private/marketing/content-calendar.md`.

## Agents

See `agents/`:
- `orchestrator.md` — Claude's default role on this project. Coordinates the rest.
- `developer.md` — for technical implementation tasks.
- `docs.md` — for keeping user-facing docs in sync with features.
- `mirakl-api.md` — specialist for Mirakl API / Carrefour Hub questions (endpoints, payloads, error handling).

When a feature is user-facing and visible in the admin, the Docs agent should update `docs/` after the Developer is done. The Sales agent pattern from chatgptbot does **not** apply here — marketing lives in `../_private/`, Rafael handles it manually with Claude's help when needed.

## What to never do

- Never put prices, client names, lead lists, competitor analysis or go-to-market plans in this repo. They live in `../_private/`.
- Never reference Addons validator checklist as a release blocker. It's a guideline, not a gate.
- Never break backwards compat in a minor release. Breaking changes require a major version bump.
- Never remove multi-shop support.
- Never include or suggest destructive git operations (force push to main, reset --hard on shared branches) without explicit user approval.

## Useful files

- `README.md` — public face of the project
- `CONTRIBUTING.md` — how contributors get started
- `CHANGELOG.md` — what changed (Keep a Changelog)
- `ROADMAP.md` — what's planned (public)
- `memory-bank/miraklApiResearch.md` — internal Mirakl API knowledge (read before API work)
- `memory-bank/implementationPlan.md` — approved design + phase plan for v1.0.0
- `../_private/strategy/business-roadmap.md` — business milestones (private)
- `docs/` — user-facing documentation (Markdown)
- `carrefourmarketplace/` — the actual PrestaShop module
