<p align="center">
  <img
    src="https://raw.githubusercontent.com/mchev/phare/main/public/brand/phare-logo-wordmark-light.png"
    alt="phare."
    width="200"
  />
</p>

<p align="center">
  <strong>Your lighthouse in the noise.</strong><br />
  Self-hosted operations center — uptime, host metrics, incidents, and a wallboard your team can trust.
</p>

<p align="center">
  <a href="LICENSE">Elastic License 2.0</a>
  · <a href="https://github.com/mchev/phare/releases">Releases</a>
  · <a href="https://mchev.github.io/phare/">Documentation</a>
  · <a href="CONTRIBUTING.md">Contributing</a>
  · <a href="https://github.com/sponsors/mchev">Sponsor</a>
</p>

<p align="center">
  <a href="docs/media/screenshots/02-dashboard.png">
    <img
      src="docs/media/screenshots/02-dashboard.png"
      alt="Phare dashboard — uptime, heartbeats, servers, and renewals in one console"
      width="720"
    />
  </a>
</p>

<p align="center">
  <sub>Demo dataset · <a href="https://mchev.github.io/phare/apercu">more screenshots</a></sub>
</p>

---

## Installation

Needs **PHP 8.3+**, **PostgreSQL** (recommended), **Redis**, and a **queue worker** + **scheduler** in production. Full checklist: [docs/deploy/hub.md](docs/deploy/hub.md).

### Linux server (VPS)

Web root → `public/`.

```bash
git clone https://github.com/mchev/phare.git
cd phare
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

Open `https://your-domain/setup` and finish the wizard (database, admin, public URL).

Then keep a worker and the scheduler running, for example:

```bash
php artisan queue:work --tries=3
php artisan schedule:work
```

(Use Supervisor or systemd in production — see [deploy/hub.md](docs/deploy/hub.md).)

Headless install instead of the wizard: `php artisan phare:install` — [installation.md](docs/user/installation.md).

### Laravel Forge

PHP **8.3** site, **PostgreSQL** + **Redis**. In the environment: `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`.

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

First visit: `https://your-domain/setup` (or `phare:install` once).

### Local try

```bash
git clone https://github.com/mchev/phare.git
cd phare
composer run setup
composer run dev
```

Open the URL shown in the terminal → `/setup` (SQLite is fine).

---

## Getting started

1. **Sign in** with the account you created in setup.
2. **Add a monitor** → _website_, _server_, or _something else_ ([guide](docs/user/surveiller.md)). For a server, the app shows the install command when you need it.
3. **Alerts** — set up a channel when prompted ([alerts & incidents](docs/user/checks-incidents-alertes.md)).
4. **Wallboard** — optional TV link under _Wallboards_ ([sondes & wallboard](docs/user/sondes-wallboard.md)).

User guides (mostly **French**): **[docs/user/README.md](docs/user/README.md)** · UI defaults to **English**.

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

## How Phare compares

<table>
<tr>
<th align="left"></th>
<th align="center"><strong>Phare</strong></th>
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
| **Updates**                        | `scripts/update.sh` then `php artisan phare:update` — [releases](docs/releases.md) |
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

Phare is released under the **[Elastic License 2.0](LICENSE)**.

| You can                                           | You cannot (without a separate agreement)                                                            |
| ------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Self-host and use Phare for your own organisation | Offer Phare (or a substantially similar service) **as a hosted / managed product for third parties** |
| Modify the code for your own deployment           | Use the Elastic License restrictions to bypass the hosted-service limitation                         |

**Commercial hosted or multi-tenant SaaS:** contact **martin@pegase.io**.
