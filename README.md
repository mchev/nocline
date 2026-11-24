# Phare

**Self-hosted operations center** for teams who run many websites and servers: uptime checks, lightweight host metrics, incidents, alerting, TV wallboards, status pages, and renewal tracking — without shipping your data to a third-party SaaS.

- **Product name:** Phare · **UI wordmark:** **phare.**
- **License:** [Elastic License 2.0](LICENSE) (source-available; self-host and modify freely; hosting Phare *as a service for others* is not allowed — see license and [commercial contact](#license)).
- **Current version:** see [`VERSION`](VERSION) · binaries: [GitHub Releases](https://github.com/mchev/phare/releases)

---

## Why Phare?

| Need | Phare |
|------|--------|
| HTTP/TCP/DNS/ICMP checks from internal or external vantage points | **Checks** module + **probes** (`phare-agent` in `probe` role) |
| Server CPU/RAM/disk without a heavy APM stack | **Go agent** push-only (`host` role) + rollups in the hub |
| Incidents, acknowledgements, notifications | **Incidents** + **Alertes** (mail, adapters) |
| NOC / office TV view | **Wallboard** (read-only, cached) |
| Customer-facing availability | **Status pages** |
| SSL/domain renewal reminders | **Renewals** (RDAP + certificate scan) |
| Secrets (phase 2) | **Vault** (libsodium, audit trail) |

Phare is designed for **~tens of sites across multiple providers**: controlled database growth (current state + events + metric rollups, not infinite raw heartbeats), and agents that **only call out** to your hub — no inbound ports on monitored servers.

**Inspiration (not a fork):** ideas from Uptime Kuma (checks, wallboard), Netdata (host metrics), and ops consoles — unified in one Laravel hub.

---

## Architecture

```
┌─────────────────────┐         HTTPS (push)          ┌──────────────────────┐
│  Monitored servers  │ ────────────────────────────► │  Phare hub (Laravel) │
│  phare-agent host   │   metrics + agent heartbeat     │  Inertia + Vue UI    │
└─────────────────────┘                                 │  PostgreSQL + Redis  │
                                                        └──────────▲───────────┘
┌─────────────────────┐         pull work + push results           │
│  External probe VPS │ ───────────────────────────────────────────┘
│  phare-agent probe  │   runs HTTP/TCP/DNS/ICMP checks
└─────────────────────┘
```

- **Hub:** Laravel 13, Inertia, Vue 3, Fortify. Modules under `app/Modules/`.
- **Agent:** single Go binary [`phare-agent`](agent/README.md) — config role `host` or `probe`.
- **API:** REST ` /api/v1/…` (ingest, enroll, probe work, personal tokens) — see [docs/api.md](docs/api.md) and [docs/openapi.yaml](docs/openapi.yaml).

---

## Requirements

### Hub (production)

| Component | Recommendation |
|-----------|----------------|
| PHP | 8.3+ |
| Database | **PostgreSQL** (SQLite/MySQL supported for small setups) |
| Redis | Queues, cache, wallboard |
| Processes | `php artisan queue:work` **and** scheduler (`schedule:run` every minute or `schedule:work`) |

Details: [docs/deploy/hub.md](docs/deploy/hub.md).

### Hub (local development)

PHP 8.3+, Composer, Node.js (Vite build). SQLite is fine.

### Monitored servers / probes

Linux (amd64 or arm64), `curl` or `wget`, outbound HTTPS to your hub **and** to [GitHub Releases](https://github.com/mchev/phare/releases) (agent install downloads the official `phare-agent` binary matching the hub `VERSION` file).

---

## Installation

### 1. Get the code

```bash
git clone https://github.com/mchev/phare.git
cd phare
```

### 2. Local quick start

```bash
composer run setup
# equivalent to: composer install, .env, key, migrate, npm install, npm run build

composer run dev
# HTTP server, queue listener, and Vite
```

Open the app URL in your browser. On first visit you are redirected to the **setup wizard** (`/setup`).

### 3. Web setup wizard (recommended)

Guided steps: language → database (with connection test) → admin account → instance name & public URL → optional SMTP → summary.

After completion, `/setup` is locked and you sign in. Configuration is written to `.env`; install marker: `storage/app/phare-installed` (keep `storage/` persistent in Docker).

Full guide: **[docs/user/installation.md](docs/user/installation.md)** (French; UI defaults to English).

### 4. Non-interactive install (Docker, Forge, CI)

```bash
php artisan phare:install \
  --db=pgsql --db-host=127.0.0.1 --db-name=phare --db-user=phare --db-password='***' \
  --admin-name="Admin" --admin-email=admin@example.com --admin-password='your-secure-password' \
  --name="My NOC" --url=https://noc.example.com --locale=en --timezone=UTC
```

Set `PHARE_FORCE_INSTALLED=true` to skip the wizard when the instance is pre-provisioned.

There is **no public sign-up**; admins create user accounts.

### 5. Production deploy

Example Forge/VPS flow: `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force`, queue worker + scheduler, Redis.

**Back up** the database and `VAULT_MASTER_KEY` separately. Never run `migrate:fresh` on production data.

→ **[docs/deploy/hub.md](docs/deploy/hub.md)**

---

## Add your first server (one-line install)

1. In the UI: **Add a monitor** → **Server** → copy the command shown.
2. On the server (as root):

   ```bash
   curl -fsSL https://your-hub.example.com/install/<token> | sudo sh
   ```

The script downloads `phare-agent` from the **official GitHub release** for your hub version (`PHARE_OFFICIAL_REPOSITORY`, default `mchev/phare`), enrolls the host, and enables a systemd unit when available.

→ **[docs/user/hosts-agent.md](docs/user/hosts-agent.md)** · **[docs/user/surveiller.md](docs/user/surveiller.md)**

**External probe** (e.g. public ICMP/HTTP vantage point): enroll with a probe token — same binary, `role: probe`.

→ **[docs/user/sondes-wallboard.md](docs/user/sondes-wallboard.md)**

---

## Updating Phare

Git-based installs:

```bash
scripts/update.sh           # latest release tag
scripts/update.sh v0.2.0    # specific tag
```

Runs `composer install`, asset build, then `php artisan phare:update` (backup, maintenance mode, migrate, cache, queue restart).

Hub version banner: daily check against GitHub releases (`PHARE_UPDATE_CHECK`, `PHARE_REPOSITORY`).

**Agents:** re-run the install command from the UI to pull the matching release binary.

---

## Publishing agent releases (maintainers)

Step-by-step (hub + agent): **[docs/releases.md](docs/releases.md)**.

Agent binaries are **not** committed to git. Publish via GitHub Actions:

1. Bump [`VERSION`](VERSION).
2. Commit and push.
3. Tag and push: `git tag v0.1.0 && git push origin v0.1.0`

Push the tag (do not create an empty release in the GitHub UI). Workflow [`.github/workflows/release.yml`](.github/workflows/release.yml) builds Linux `amd64` / `arm64` and **creates or updates** the GitHub release with assets `phare-agent-linux-amd64` and `phare-agent-linux-arm64`.

If a tag already exists without binaries: **Actions → release → Run workflow** (enter the tag), or run `scripts/publish-agent-assets.sh vX.Y.Z` locally.

Local build (optional, for testing):

```bash
scripts/build-agent.sh
# or: php artisan phare:agent:build
```

---

## Documentation

| Topic | Link |
|--------|------|
| **Documentation index** | [docs/index.md](docs/index.md) |
| Product & technical guideline | [docs/guideline/SKILL.md](docs/guideline/SKILL.md) |
| Schemas, APIs, storage, probes | [docs/guideline/reference.md](docs/guideline/reference.md) |
| Roadmap | [docs/roadmap.md](docs/roadmap.md) |
| **User guides** (French, maintained with features) | [docs/user/README.md](docs/user/README.md) |
| Installation | [docs/user/installation.md](docs/user/installation.md) |
| Monitoring wizard (sites, servers, services) | [docs/user/surveiller.md](docs/user/surveiller.md) |
| Checks, incidents, alerts | [docs/user/checks-incidents-alertes.md](docs/user/checks-incidents-alertes.md) |
| Wallboard & probes | [docs/user/sondes-wallboard.md](docs/user/sondes-wallboard.md) |
| Maintenance & status pages | [docs/user/maintenance-statut.md](docs/user/maintenance-statut.md) |
| REST API & tokens | [docs/api.md](docs/api.md) · [docs/openapi.yaml](docs/openapi.yaml) |
| Migrate from Uptime Kuma | [docs/migration-kuma.md](docs/migration-kuma.md) |
| Hub deployment | [docs/deploy/hub.md](docs/deploy/hub.md) |
| **Releases (hub + agent)** | [docs/releases.md](docs/releases.md) |
| Security hardening | [docs/security.md](docs/security.md) |
| Privacy (self-hosted vault) | [docs/privacy.md](docs/privacy.md) |
| Notification adapters | [docs/contributing/notification-adapters.md](docs/contributing/notification-adapters.md) |
| Go agent (build, config) | [agent/README.md](agent/README.md) |
| Laravel modules layout | [app/Modules/README.md](app/Modules/README.md) |
| Client package `phare/laravel` | [packages/phare-laravel/README.md](packages/phare-laravel/README.md) |

---

## Repository layout

```
phare/
├── app/Modules/          # Domain modules (Checks, Hosts, Incidents, …)
├── agent/                # phare-agent (Go)
├── docs/                 # Product, user, and deploy documentation
├── packages/phare-laravel/
├── resources/js/         # Inertia / Vue console
└── public/brand/         # Logo and favicon assets
```

---

## Contributing

[CONTRIBUTING.md](CONTRIBUTING.md) — issues, PRs, releases, and community expectations.

Report vulnerabilities: [SECURITY.md](SECURITY.md) (not public issues).

---

## License

Phare is released under the **[Elastic License 2.0](LICENSE)**.

You may use, modify, and self-host Phare for your own organization. You **may not** offer Phare to third parties as a hosted or managed service. For commercial hosted offerings, contact **martin@pegase.io**.