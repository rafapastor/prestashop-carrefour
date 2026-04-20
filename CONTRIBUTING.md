# Contributing

Thanks for considering a contribution to this project. We keep the process light and contributor-friendly.

## Ways to help

- **Report bugs** — open an issue with reproduction steps, PrestaShop version, module version, log excerpts.
- **Suggest features** — open a discussion or a feature request issue. Say *why* before *what*.
- **Fix things** — pick an open issue (especially those labelled `good first issue`), comment that you're working on it, send a PR.
- **Improve docs** — typos, unclear sections, missing examples: all welcome.
- **Translations** — we ship with English and Spanish; other languages welcome via `translations/`.
- **Write tests** — increased test coverage is always appreciated.

## Quick start (dev environment)

```bash
git clone https://github.com/rafapastor/prestashop-carrefour.git
cd prestashop-carrefour
make dev
# PrestaShop at http://localhost:8081
# Admin login: admin@prestashop.com / prestashop_demo
```

The `carrefourmarketplace/` folder is mounted inside the container at `/var/www/html/modules/carrefourmarketplace`. Edit files locally → refresh the admin → changes are live.

To install the module in the running container:

1. Log into the PrestaShop admin.
2. Go to **Modules → Module Manager**.
3. Search for "Carrefour" and click **Install**.

Tear everything down with `make stop`.

## Coding standards

We follow the [PrestaShop coding standards](https://devdocs.prestashop-project.org/9/development/coding-standards/). The `.php-cs-fixer.php` config is already set up.

```bash
make lint      # check only (non-destructive)
make format    # apply fixes
```

Please run `make format` before committing.

## Tests

```bash
make test
```

New business logic should come with tests. We use PHPUnit. Unit tests for pure logic, integration tests for anything that talks to the Mirakl API (against a sandbox).

## Commit messages

Conventional-ish, but we're pragmatic:

- `feat: add bulk offer upload`
- `fix: handle OR-23 Mirakl error code`
- `docs: clarify multishop setup`
- `refactor: extract MiraklClient from saveOrders`
- `test: cover offer mapping edge cases`
- `chore: bump php-cs-fixer`

One logical change per commit. If in doubt, smaller is better.

## Pull requests

1. Fork the repo.
2. Create a branch: `git checkout -b fix/short-description`.
3. Make your changes + tests.
4. Run `make format && make lint && make test`.
5. Update `CHANGELOG.md` in the `[Unreleased]` section.
6. Open the PR against `main`, filling the template.
7. Respond to review feedback. No one merges their own PR to `main`.

Small PRs get merged fast. Huge PRs that change 40 files get stuck forever — split them.

## Contributor License Agreement (CLA)

By submitting a PR you agree that your contribution will be licensed under the project's AGPL-3.0 license. If we later offer a commercial licence alongside AGPL, a standard CLA will be introduced; you'll be asked to sign it before your contributions are included in the commercial edition.

## Code of Conduct

Be respectful. See [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). Harassment or personal attacks get you banned. Simple.

## Reporting security issues

Please **don't** open public issues for security vulnerabilities. See [SECURITY.md](SECURITY.md).

## What you won't get support for (free)

- "How do I install this?" — read [docs/installation.md](docs/installation.md) first. If it's genuinely unclear, open a discussion.
- "Help me configure my Mirakl account" — that's support territory. Try the community first; paid support is available.
- "Add this feature just for me" — use the feature request template. If it fits the roadmap, it'll happen. If it doesn't, custom development is available.

Keeping this line explicit helps the project stay sustainable.

## Getting help as a contributor

- Open a [Discussion](../../discussions) — we're happy to help you get oriented.
- Join the chat (link coming).
- If you want to work on something big, propose it first in an issue before coding — saves wasted effort.

Thanks!
