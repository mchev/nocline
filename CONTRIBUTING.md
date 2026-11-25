# Contributing to Nocline

Thanks for helping! Nocline is a self-hosted ops platform (uptime, performance, alerts, vault, renewals, wallboard) that must stay usable by someone who knows nothing about IT. Please read the [Code of Conduct](CODE_OF_CONDUCT.md). Security problems: see [SECURITY.md](SECURITY.md), not a public issue.

## Development setup

Requirements: PHP 8.3+ (with `sodium`), Composer, Node.js, and Go (only for `agent/`).

```bash
composer run setup   # composer install, .env, key, migrate, npm install, build
composer run dev     # web server, queue worker, scheduler and Vite
```

Checks to run before opening a pull request:

```bash
composer test                         # Pint + PHPStan + Pest (~20 s)
composer test:pest                    # Pest only (serial)
composer test:pest:parallel           # Pest on all cores (CI)
vendor/bin/pest --compact             # Same as test:pest (prefer over `php artisan test`: TTY can make that 10× slower in some terminals)
vendor/bin/pint --dirty --format agent  # code style (PHP)
vendor/bin/phpstan analyse            # static analysis, must stay at 0 errors
npm run check && npm run types:check  # front-end lint + vue-tsc
php artisan nocline:lang:missing        # UI strings missing a translation
cd agent && go test ./...             # agent
```

`composer run ci:check` runs the same steps as GitHub Actions (frontend check, `vue-tsc`, Pint, PHPStan, Pest en parallèle). **Évite `php artisan test` dans un terminal type agent/Cursor sans `--without-tty`** : Collision active le TTY et peut bloquer la sortie plusieurs minutes alors que la suite ne prend que ~15 s.

To try a real user journey in the browser, use `scripts/e2e-serve.sh`: it starts an isolated instance under `/tmp` and never touches your real database. Never run `migrate:fresh` or other destructive commands against a database you care about.

## Project structure

- `app/Modules/<Name>/` — one folder per domain (Checks, Hosts, Alertes, Coffre, Audit...), each with `Models`, `Http`, `Policies`, `Services`, `Console`, `Enums` and a `routes.php`. See [app/Modules/README.md](app/Modules/README.md). Modules talk to each other through events.
- `resources/js/pages/` — Inertia + Vue 3 pages; `resources/js/components/` shared UI.
- `agent/` — the Go `nocline-agent` (push-only: it never listens for connections).
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

## Documentation (VitePress)

User-facing site: [https://mchev.github.io/nocline/](https://mchev.github.io/nocline/) (built from `docs/` on push to `main`).

- **Product or UI change** → update `docs/user/` (French) and `docs/en/user/` when English is maintained (task-oriented). See [docs/user/traductions.md](docs/user/traductions.md#documentation-site-vitepress). See the Nocline skill § Documentation.
- **Install, deploy, API** → update the matching files under `docs/` and the root `README.md` when the operator path changes.
- **New doc page or section** → add it to `docs/.vitepress/config.ts` (sidebar).
- Before merging structural doc changes, run `npm run docs:build`. Local preview: `npm run docs:dev`.

Details: [docs/README.md](docs/README.md).

## Commits and pull requests

- Small, focused pull requests are reviewed faster. One topic per PR.
- Commit messages: short imperative subject ("Add audit log pruning"), details in the body if needed.
- Open an issue first for large changes, to agree on the direction.
- A good PR: explains what and why, links the issue, includes tests, passes the checks above, and has screenshots for UI changes. The PR template lists the checklist.

## Labels

| Label              | Meaning                                                 |
| ------------------ | ------------------------------------------------------- |
| `good first issue` | Small, well-described, a good start                     |
| `help wanted`      | We would love a contribution                            |
| `translation`      | New language or wording fix                             |
| `adapter`          | New notification channel                                |
| `needs-triage`     | Not yet reviewed (set automatically by the issue forms) |

## Releases

Maintainers bump the `VERSION` file, commit, then `git tag vX.Y.Z && git push origin vX.Y.Z`. The [`release.yml`](.github/workflows/release.yml) workflow builds `nocline-agent-linux-{amd64,arm64}` and **creates or updates** the GitHub release with those assets.

Do **not** create an empty release in the GitHub UI only — the install script expects downloadable assets on that tag. If that already happened, re-run **Actions → release → Run workflow** with the tag name, or locally: `scripts/publish-agent-assets.sh vX.Y.Z`.

Full checklist and ops guide: [docs/releases.md](docs/releases.md). Hub deployment: [docs/deploy/hub.md](docs/deploy/hub.md).

## Licence

Nocline is released under the [Elastic License 2.0](LICENSE). By submitting a contribution you agree that it is licensed under the same terms and that the maintainer may also offer it under other terms (for example a commercial hosted offer).
