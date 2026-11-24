# Contributing to Phare

Thanks for helping! Phare is a self-hosted ops platform (uptime, performance, alerts, vault, renewals, wallboard) that must stay usable by someone who knows nothing about IT. Please read the [Code of Conduct](CODE_OF_CONDUCT.md). Security problems: see [SECURITY.md](SECURITY.md), not a public issue.

## Development setup

Requirements: PHP 8.3+ (with `sodium`), Composer, Node.js, and Go (only for `agent/`).

```bash
composer run setup   # composer install, .env, key, migrate, npm install, build
composer run dev     # web server, queue worker, scheduler and Vite
```

Checks to run before opening a pull request:

```bash
php artisan test --compact            # Pest tests (they use a throwaway test database)
vendor/bin/pint --dirty --format agent  # code style (PHP)
vendor/bin/phpstan analyse            # static analysis, must stay at 0 errors
npm run check && npm run types:check  # front-end lint + vue-tsc
php artisan phare:lang:missing        # UI strings missing a translation
cd agent && go test ./...             # agent
```

`composer run ci:check` runs most of this like the CI does. To try a real user journey in the browser, use `scripts/e2e-serve.sh`: it starts an isolated instance under `/tmp` and never touches your real database. Never run `migrate:fresh` or other destructive commands against a database you care about.

## Project structure

- `app/Modules/<Name>/` — one folder per domain (Checks, Hosts, Alertes, Coffre, Audit...), each with `Models`, `Http`, `Policies`, `Services`, `Console`, `Enums` and a `routes.php`. See [app/Modules/README.md](app/Modules/README.md). Modules talk to each other through events.
- `resources/js/pages/` — Inertia + Vue 3 pages; `resources/js/components/` shared UI.
- `agent/` — the Go `phare-agent` (push-only: it never listens for connections).
- `docs/` — documentation, start at [docs/index.md](docs/index.md).

## Conventions

- **Accessible by non-technical people**: plain words, guided flows, sensible defaults, empty states that suggest the next step.
- **Controlled storage**: current state + events + rollups. No unbounded time series in SQL.
- **Authorization** on every action through a policy (roles: admin / tech / viewer). Validate with FormRequests.
- **Never log or store secrets** (passwords, tokens, vault values), including in the activity log.
- **Translations**: UI strings are English text keys, written with `__()` in PHP and `t()` / `$t()` in Vue. Add French (and other locales) in `lang/<code>.json`; see [docs/user/traductions.md](docs/user/traductions.md).
- **Notification adapters**: see [docs/contributing/notification-adapters.md](docs/contributing/notification-adapters.md).
- **Tests**: add a Pest feature test for behaviour and failure modes.
- Code style is enforced by Pint; keep explicit types and PHPDoc array shapes.

## Commits and pull requests

- Small, focused pull requests are reviewed faster. One topic per PR.
- Commit messages: short imperative subject ("Add audit log pruning"), details in the body if needed.
- Open an issue first for large changes, to agree on the direction.
- A good PR: explains what and why, links the issue, includes tests, passes the checks above, and has screenshots for UI changes. The PR template lists the checklist.

## Labels

| Label | Meaning |
|-------|---------|
| `good first issue` | Small, well-described, a good start |
| `help wanted` | We would love a contribution |
| `translation` | New language or wording fix |
| `adapter` | New notification channel |
| `needs-triage` | Not yet reviewed (set automatically by the issue forms) |

## Releases

Maintainers bump the `VERSION` file, then `git tag vX.Y.Z && git push --tags`; the `release.yml` workflow builds the agent binaries and publishes the GitHub release. Details in [docs/deploy/hub.md](docs/deploy/hub.md).

## Licence

Phare is released under the [Elastic License 2.0](LICENSE). By submitting a contribution you agree that it is licensed under the same terms and that the maintainer may also offer it under other terms (for example a commercial hosted offer).
