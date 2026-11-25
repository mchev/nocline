# Deploying the Phare hub (VPS / Forge)

## Requirements

**PHP 8.3+** is mandatory. Everything else depends on scale:

| Component | Small / internal NOC     | Production (many checks, wallboard)        |
| --------- | ------------------------ | ------------------------------------------ |
| Database  | SQLite or MySQL          | **PostgreSQL** (jsonb, rollups)            |
| Redis     | Optional                 | **Recommended** — queues, cache, wallboard |
| Workers   | `queue:work` + scheduler | Supervisor daemons for both                |

SQLite and the `database` queue driver work for trials and small teams. Move to PostgreSQL + Redis when checks, metrics rollups, and wallboard traffic grow.

For a bundled stack, use **[Docker](docker)** first, then adapt the same env vars on a VPS.

## Environment (beyond Laravel defaults)

```env
APP_NAME=Phare

# Vault — from /setup or phare:install; back up separately from the DB
# VAULT_MASTER_KEY=

# Production example (Redis):
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Public URL for agent enrollment commands:
# PHARE_PUBLIC_URL=https://phare.example.com
```

First deploy: open the URL (`/setup`) or run `php artisan phare:install` — see [installation](../user/installation).

## Backups

Back up the database **and** `VAULT_MASTER_KEY` separately. Never run `migrate:fresh` in production.

## Forge (summary)

1. PHP 8.3 site — deploy: `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan queue:restart`.
2. Daemons: `php artisan queue:work --tries=3` and the scheduler.
3. Database: PostgreSQL or MySQL + Redis (add-ons).

## Probes

Install `phare-agent` with `role=probe` on an external VPS for outside-in checks; ICMP needs `CAP_NET_RAW` (see [technical reference](../guideline/reference)).

## Updates

- Daily release check (`phare:update:check`); disable with `PHARE_UPDATE_CHECK=false`.
- Apply: `scripts/update.sh` then `php artisan phare:update` — see [releases](../fr/releases).
