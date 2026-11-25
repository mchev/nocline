# Installation

## Requirements

| Layer         | Try it            | Production                      |
| ------------- | ----------------- | ------------------------------- |
| **PHP**       | 8.3+              | 8.3+                            |
| **Database**  | **SQLite**        | PostgreSQL or MySQL             |
| **Queues**    | `database` driver | **Redis** + worker              |
| **Cache**     | `file`            | **Redis** (wallboard)           |
| **Scheduler** | required          | required (checks, SSL, rollups) |

PostgreSQL and Redis are **not** required for a first look. For the fastest path, use **[Docker](../deploy/docker)**.

## Pick a deployment path

| Path                                | When to use                                                                     |
| ----------------------------------- | ------------------------------------------------------------------------------- |
| [Docker](../deploy/docker)          | Try Nocline or run the full stack (Postgres + Redis + workers) with one command |
| [Custom VPS / Forge](../deploy/hub) | You already run PHP sites and want Supervisor + your own TLS                    |
| Setup wizard (`/setup`)             | Always — first visit configures DB, admin, URL, optional mail                   |

After install, continue with **[Your first hour with Nocline](getting-started)**.

## Setup wizard

First visit → `/setup` (database with SQLite option, admin, public URL, optional SMTP). When done, `/setup` is locked; keep `storage/` persistent (`storage/app/nocline-installed`).

## Non-interactive install

SQLite:

```bash
php artisan nocline:install --db=sqlite \
  --admin-name="Admin" --admin-email=admin@example.com --admin-password='ChangeMe-Secret1!' \
  --name="My NOC" --url=http://localhost:8080 --locale=en --timezone=UTC
```

PostgreSQL (Docker or VPS):

```bash
php artisan nocline:install --db=pgsql --db-host=postgres --db-name=nocline \
  --db-user=nocline --db-password=nocline \
  --admin-name="Admin" --admin-email=admin@example.com --admin-password='ChangeMe-Secret1!' \
  --name="My NOC" --url=http://localhost:8080 --locale=en
```

Already installed → command aborts. `NOCLINE_FORCE_INSTALLED=true` skips the wizard for pre-provisioned images.

No public sign-up — administrators create accounts. Locales: [Translations](translations).
