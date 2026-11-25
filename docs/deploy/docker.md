# Docker

Official Compose stack: **web app + PostgreSQL + Redis + queue worker + scheduler**.

## Quick start

```bash
git clone https://github.com/mchev/nocline.git
cd nocline
cp .env.docker.example .env
docker compose up -d --build
```

Open `http://localhost:8080/setup` and finish the wizard (or set `NOCLINE_AUTO_INSTALL=true` in `.env` before `up` — see below).

Persistent data: Docker volumes `nocline-storage`, `nocline-postgres`. Back up volumes before upgrades.

## Auto-install (optional)

In `.env` (copied from `.env.docker.example`):

```env
NOCLINE_AUTO_INSTALL=true
NOCLINE_INSTALL_ADMIN_EMAIL=admin@example.com
NOCLINE_INSTALL_ADMIN_PASSWORD=ChangeMe-Secret1!
NOCLINE_INSTALL_URL=http://localhost:8080
```

Password must meet the same rules as the wizard (12+ chars, mixed case, digit, symbol). On first boot the container runs `nocline:install` against the `postgres` service, then locks `/setup`.

## Architecture

| Service     | Role                              |
| ----------- | --------------------------------- |
| `app`       | Nginx + PHP-FPM, serves `public/` |
| `worker`    | `php artisan queue:work`          |
| `scheduler` | `php artisan schedule:work`       |
| `postgres`  | Application database              |
| `redis`     | Cache, sessions, queues           |

All PHP containers share the same image built from the repo `Dockerfile` (assets compiled at build time).

## Customize

- **Port:** change `APP_PORT` in `.env` (default `8080`).
- **Public URL** for agents: set `NOCLINE_PUBLIC_URL` after you put a reverse proxy in front.
- **SQLite-only experiment:** not the default Compose profile — see [installation](../user/installation) for manual SQLite on a VPS.

## Production notes

- Put TLS in front (Caddy, Traefik, cloud load balancer).
- Increase Postgres backups and monitor disk for `storage/`.
- For high check volume, give Redis and the worker container enough CPU.

See also: [VPS / Forge deployment](hub).
