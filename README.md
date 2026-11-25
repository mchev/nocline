<p align="center">
  <img
    src="https://raw.githubusercontent.com/mchev/nocline/main/public/brand/nocline-symbole-signal.png"
    alt="Nocline"
    width="72"
  />
  <br />
  <img
    src="https://raw.githubusercontent.com/mchev/nocline/main/public/brand/nocline-wordmark.svg"
    alt="NOCLINE"
    width="180"
  />
</p>

<p align="center">
  <strong>Your NOC, without the team.</strong><br />
  See what matters. Watch what’s invisible — uptime, host metrics, incidents, and a wallboard you can trust.
</p>

<p align="center">
  <sub>Formerly <strong>Phare</strong> (renamed in 0.2.0).</sub>
</p>

<p align="center">
  <a href="LICENSE">Elastic License 2.0</a>
  · <a href="https://github.com/mchev/nocline/releases">Releases</a>
  · <a href="https://mchev.github.io/nocline/">Documentation</a>
  · <a href="CONTRIBUTING.md">Contributing</a>
  · <a href="https://github.com/sponsors/mchev">Sponsor</a>
</p>

<p align="center">
  <a href="docs/media/screenshots/02-dashboard.png">
    <img
      src="docs/media/screenshots/02-dashboard.png"
      alt="Nocline dashboard — uptime, heartbeats, servers, and renewals in one console"
      width="720"
    />
  </a>
</p>

<p align="center">
  <sub>Demo dataset · <a href="https://mchev.github.io/nocline/apercu">more screenshots</a></sub>
</p>

---

## Installation

**PHP 8.3+** only. SQLite is enough to try Nocline; use **PostgreSQL + Redis** when you scale checks and the wallboard. Full guide: [installation](https://mchev.github.io/nocline/user/installation).

<!-- GitHub README has no real tabs — anchor bar below is the usual pattern. -->
<table>
<tr>
<td align="center" width="33%"><a href="#custom-host--vps"><strong>Custom Host / VPS</strong></a></td>
<td align="center" width="33%"><a href="#laravel-forge"><strong>Laravel Forge</strong></a></td>
<td align="center" width="33%"><a href="#docker"><strong>Docker</strong></a></td>
</tr>
</table>

### Custom Host / VPS

Point the web server at `public/`.

```bash
git clone https://github.com/mchev/nocline.git
cd nocline
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

Open `https://your-domain/setup` and finish the wizard (database, admin, public URL).

Keep a **queue worker** and the **scheduler** running (Supervisor or systemd in production):

```bash
php artisan queue:work --tries=3
php artisan schedule:work
```

Checklist: [deploy/hub.md](docs/deploy/hub.md). Headless install: `php artisan nocline:install` — [installation.md](docs/user/installation.md).

### Laravel Forge

PHP **8.3** site. **PostgreSQL + Redis** recommended at scale; SQLite works for small teams. Environment: `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis` (or `database` for light use).

**Deploy script:**

```bash
cd $FORGE_SITE_PATH
git pull origin $FORGE_SITE_BRANCH
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan queue:restart
```

**Daemons:** `php artisan queue:work --sleep=3 --tries=3` and the **scheduler** (Forge scheduler or `schedule:work`).

First visit: `https://your-domain/setup` (or `nocline:install` once).

### Docker

Hub + PostgreSQL + Redis + worker + scheduler in one Compose file.

```bash
git clone https://github.com/mchev/nocline.git
cd nocline
cp .env.docker.example .env
docker compose up -d --build
```

Open `http://localhost:8080/setup` (or set `NOCLINE_AUTO_INSTALL=true` in `.env`). Details: [docs/deploy/docker.md](docs/deploy/docker.md).

---

## Getting started

1. **Sign in** with the account you created in setup.
2. **Add a monitor** → _website_, _server_, or _something else_ ([guide](docs/user/surveiller.md)). For a server, the app shows the install command when you need it.
3. **Alerts** — set up a channel when prompted ([alerts & incidents](docs/user/checks-incidents-alerts.md)).
4. **Wallboard** — optional TV link under _Wallboards_ ([sondes & wallboard](docs/user/sondes-wallboard.md)).

Full walkthrough: **[Your first hour](https://mchev.github.io/nocline/user/getting-started)** · all guides on [GitHub Pages](https://mchev.github.io/nocline/user/installation) (English default, **Français** in the language menu).

---

## Features

|                         |                                                                                    |
| ----------------------- | ---------------------------------------------------------------------------------- |
| **Websites & services** | HTTP/TCP/DNS checks, SSL and domain renewals, optional probes for outside-in views |
| **Servers**             | Lightweight **Go agent** — CPU, RAM, disk; metrics pushed to the hub               |
| **Incidents**           | Clear up/down state, acknowledgements, history                                     |
| **Alerts**              | Email and pluggable notification adapters                                          |
| **Wallboard**           | NOC view for a TV or kiosk                                                         |
| **Status pages**        | Public pages per client or service                                                 |
| **Vault**               | Encrypted secrets on your own server                                               |

One console instead of juggling separate uptime, metrics, and TV tools — **your data stays on your infrastructure**.

Coming from **Uptime Kuma**? **[Import guide](docs/migration-kuma.md)**.

---

## How Nocline compares

<table>
<tr>
<th align="left"></th>
<th align="center"><strong>Nocline</strong></th>
<th align="center"><a href="https://github.com/louislam/uptime-kuma"><strong>Uptime Kuma</strong></a></th>
<th align="center"><a href="https://www.netdata.cloud/"><strong>Netdata</strong></a></th>
<th align="center"><a href="https://ohdear.app/"><strong>Oh Dear</strong></a></th>
</tr>
<tr>
<td>Self-hosted on your infra</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#EAEEF2">—</td>
</tr>
<tr>
<td>No server to run (SaaS)</td>
<td align="center" bgcolor="#EAEEF2">—</td>
<td align="center" bgcolor="#EAEEF2">—</td>
<td align="center" bgcolor="#EAEEF2">—</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
</tr>
<tr>
<td>HTTP / TCP / DNS checks</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#EAEEF2">—</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
</tr>
<tr>
<td>ICMP / checks from probe nodes</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#EAEEF2">—</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
</tr>
<tr>
<td>Server CPU / RAM / disk (agent)</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#EAEEF2">—</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#FFF8C5">~</td>
</tr>
<tr>
<td>Deep per-second host metrics</td>
<td align="center" bgcolor="#EAEEF2">—</td>
<td align="center" bgcolor="#EAEEF2">—</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#EAEEF2">—</td>
</tr>
<tr>
<td>Wallboard / TV view</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#FFF8C5">~</td>
<td align="center" bgcolor="#EAEEF2">—</td>
</tr>
<tr>
<td>Public status pages</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#EAEEF2">—</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
</tr>
<tr>
<td>Incidents &amp; notifications</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
</tr>
<tr>
<td>Cron / heartbeat (dead man’s switch)</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#EAEEF2">—</td>
<td align="center" bgcolor="#FFF8C5">~</td>
</tr>
<tr>
<td>SSL &amp; domain expiry tracking</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#FFF8C5">~</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
</tr>
<tr>
<td>Encrypted secrets vault</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#EAEEF2">—</td>
<td align="center" bgcolor="#EAEEF2">—</td>
<td align="center" bgcolor="#EAEEF2">—</td>
</tr>
<tr>
<td>Import from Uptime Kuma</td>
<td align="center" bgcolor="#DDF4E4">✓</td>
<td align="center" bgcolor="#EAEEF2">—</td>
<td align="center" bgcolor="#EAEEF2">—</td>
<td align="center" bgcolor="#EAEEF2">—</td>
</tr>
</table>

<table>
<tr>
<td align="center" bgcolor="#DDF4E4" width="56">✓</td><td align="left">yes</td>
<td align="center" bgcolor="#FFF8C5" width="56">~</td><td align="left">partial</td>
<td align="center" bgcolor="#EAEEF2" width="56">—</td><td align="left">no / not the focus</td>
</tr>
</table>

---

## Advanced

| Topic                              | Where to read                                                                      |
| ---------------------------------- | ---------------------------------------------------------------------------------- |
| **Updates**                        | `scripts/update.sh` then `php artisan nocline:update` — [releases](docs/releases.md) |
| **Probes** (ICMP, external checks) | [Sondes & wallboard](docs/user/sondes-wallboard.md)                                |
| **API & tokens**                   | [docs/api.md](docs/api.md)                                                         |
| **Security & hardening**           | [docs/security.md](docs/security.md) · report: [SECURITY.md](SECURITY.md)          |
| **Backups (DB + vault key)**       | [docs/privacy.md](docs/privacy.md)                                                 |
| **Roadmap**                        | [docs/roadmap.md](docs/roadmap.md)                                                 |

---

## Developers

Architecture, modules, tests, and contribution workflow: **[CONTRIBUTING.md](CONTRIBUTING.md)** · technical index: **[docs/index.md](docs/index.md)** · agent binary: **[agent/README.md](agent/README.md)**.

---

## License

Nocline is released under the **[Elastic License 2.0](LICENSE)**.

| You can                                           | You cannot (without a separate agreement)                                                            |
| ------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Self-host and use Nocline for your own organisation | Offer Nocline (or a substantially similar service) **as a hosted / managed product for third parties** |
| Modify the code for your own deployment           | Use the Elastic License restrictions to bypass the hosted-service limitation                         |

**Commercial hosted or multi-tenant SaaS:** contact **martin@pegase.io**.
